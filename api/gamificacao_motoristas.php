<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/cache.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/api_json.php';

// Configurar sessão antes de iniciá-la
configure_session();

// Iniciar sessão
session_start();

// Verificar se o usuário está autenticado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];

header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

try {
    $conn = getConnection();

    // Protege mutações (POST/PUT/PATCH/DELETE) com CSRF e retorno JSON padronizado.
    api_require_csrf_json();
    
    // Verificar se as tabelas existem
    $tabelas_existem = verificarTabelasGamificacao($conn);
    
    if (!$tabelas_existem) {
        echo json_encode([
            'error' => 'Sistema de gamificação não configurado. Execute o setup primeiro.',
            'setup_required' => true
        ]);
        exit;
    }
    
    switch ($action) {
        case 'get_leaderboard':
            getLeaderboard($conn, $empresa_id);
            break;
            
        case 'get_detalhes':
            $motorista_id = isset($_GET['motorista_id']) ? (int)$_GET['motorista_id'] : 0;
            getDetalhesGamificacao($conn, $empresa_id, $motorista_id);
            break;
            
        case 'get_desafios':
            getDesafios($conn, $empresa_id);
            break;
            
        case 'get_conquistas':
            $motorista_id = isset($_GET['motorista_id']) ? (int)$_GET['motorista_id'] : 0;
            getConquistas($conn, $empresa_id, $motorista_id);
            break;
            
        case 'calcular_gamificacao':
            calcularGamificacao($conn, $empresa_id);
            break;
            
        case 'get_niveis':
            getNiveis($conn, $empresa_id);
            break;
            
        case 'get_badges':
            getBadges($conn, $empresa_id);
            break;
            
        case 'create_desafio':
            createDesafio($conn, $empresa_id);
            break;
            
        case 'get_stats':
            getEstatisticasGamificacao($conn, $empresa_id);
            break;
            
        default:
            echo json_encode(['error' => 'Ação não especificada']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Erro na API gamificacao_motoristas.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor']);
}

/**
 * Verificar se as tabelas de gamificação existem
 */
function verificarTabelasGamificacao($conn) {
    try {
        $stmt = $conn->prepare("SHOW TABLES LIKE 'gamificacao_motoristas'");
        $stmt->execute();
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Obter leaderboard de gamificação
 */
function getLeaderboard($conn, $empresa_id) {
    $periodo = isset($_GET['periodo']) ? $_GET['periodo'] : date('Y-m-d');
    $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 20;
    
    // Chave única para o cache
    $cache_key = "leaderboard_gamificacao_{$empresa_id}_{$periodo}_{$limite}";
    
    // Tentar obter do cache primeiro (TTL de 5 minutos)
    $cached_data = getCachedData($cache_key, function() use ($conn, $empresa_id, $limite, $periodo) {
        try {
            $sql = "SELECT 
                        g.*,
                        m.nome as motorista_nome,
                        m.telefone,
                        CASE 
                            WHEN g.pontos_totais >= 600 THEN 'Diamante'
                            WHEN g.pontos_totais >= 300 THEN 'Ouro'
                            WHEN g.pontos_totais >= 100 THEN 'Prata'
                            ELSE 'Bronze'
                        END as nivel_calculado,
                        COALESCE(COUNT(r.id), 0) as total_rotas_reais
                    FROM gamificacao_motoristas g
                    JOIN motoristas m ON g.motorista_id = m.id
                    LEFT JOIN rotas r ON r.motorista_id = g.motorista_id AND r.status IN ('aprovado', 'finalizada')
                    WHERE g.empresa_id = :empresa_id
                    GROUP BY g.id, m.id
                    ORDER BY g.pontos_totais DESC, g.streak_atual DESC
                    LIMIT :limite";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':empresa_id', $empresa_id);
            $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            
            $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Adicionar posição no ranking
            foreach ($leaderboard as $index => &$motorista) {
                $motorista['posicao'] = $index + 1;
                $motorista['posicao_emoji'] = $index < 3 ? ['🥇', '🥈', '🥉'][$index] : '#' . ($index + 1);
            }
            
            return [
                'leaderboard' => $leaderboard,
                'periodo' => $periodo,
                'total_motoristas' => count($leaderboard)
            ];
        } catch (Exception $e) {
            throw $e;
        }
    }, 300); // Cache por 5 minutos
    
    echo json_encode([
        'success' => true,
        'data' => $cached_data,
        'cached' => true
    ]);
}

/**
 * Obter detalhes de gamificação de um motorista
 */
function getDetalhesGamificacao($conn, $empresa_id, $motorista_id) {
    if (!$motorista_id) {
        echo json_encode(['error' => 'ID do motorista não especificado']);
        return;
    }
    
    try {
        // Buscar dados atuais de gamificação
        $sql = "SELECT 
                    g.*,
                    m.nome as motorista_nome,
                    m.telefone
                FROM gamificacao_motoristas g
                JOIN motoristas m ON g.motorista_id = m.id
                WHERE g.motorista_id = :motorista_id
                AND g.empresa_id = :empresa_id
                AND g.periodo >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                ORDER BY g.data_atualizacao DESC
                LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':motorista_id', $motorista_id);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        
        $gamificacao = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$gamificacao) {
            // Criar registro inicial se não existir
            $gamificacao = criarRegistroGamificacao($conn, $empresa_id, $motorista_id);
        }
        
        // Buscar conquistas recentes
        $sql_conquistas = "SELECT 
                             tipo_conquista,
                             nome_conquista,
                             descricao,
                             pontos_recebidos,
                             badge_recebida,
                             data_conquista
                           FROM conquistas_motoristas 
                           WHERE motorista_id = :motorista_id
                           AND empresa_id = :empresa_id
                           ORDER BY data_conquista DESC
                           LIMIT 10";
        
        $stmt_conquistas = $conn->prepare($sql_conquistas);
        $stmt_conquistas->bindParam(':motorista_id', $motorista_id);
        $stmt_conquistas->bindParam(':empresa_id', $empresa_id);
        $stmt_conquistas->execute();
        
        $conquistas = $stmt_conquistas->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar desafios ativos
        $sql_desafios = "SELECT 
                           d.*,
                           CASE 
                               WHEN d.tipo = 'diario' THEN 'Diário'
                               WHEN d.tipo = 'semanal' THEN 'Semanal'
                               WHEN d.tipo = 'mensal' THEN 'Mensal'
                               ELSE 'Trimestral'
                           END as tipo_descricao
                         FROM desafios_motoristas d
                         WHERE d.empresa_id = :empresa_id
                         AND d.ativo = 1
                         AND (d.data_fim IS NULL OR d.data_fim >= CURDATE())
                         ORDER BY d.pontos_recompensa DESC";
        
        $stmt_desafios = $conn->prepare($sql_desafios);
        $stmt_desafios->bindParam(':empresa_id', $empresa_id);
        $stmt_desafios->execute();
        
        $desafios = $stmt_desafios->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular progresso para próximo nível
        $niveis = [
            'Bronze' => ['min' => 0, 'max' => 99],
            'Prata' => ['min' => 100, 'max' => 299],
            'Ouro' => ['min' => 300, 'max' => 599],
            'Diamante' => ['min' => 600, 'max' => 999]
        ];
        
        $nivel_atual = $gamificacao['nivel_atual'];
        $pontos_atuais = $gamificacao['pontos_totais'];
        
        $proximo_nivel = '';
        $pontos_para_proximo = 0;
        $progresso_nivel = 0;
        
        if ($nivel_atual === 'Bronze') {
            $proximo_nivel = 'Prata';
            $pontos_para_proximo = 100 - $pontos_atuais;
            $progresso_nivel = ($pontos_atuais / 100) * 100;
        } elseif ($nivel_atual === 'Prata') {
            $proximo_nivel = 'Ouro';
            $pontos_para_proximo = 300 - $pontos_atuais;
            $progresso_nivel = (($pontos_atuais - 100) / 200) * 100;
        } elseif ($nivel_atual === 'Ouro') {
            $proximo_nivel = 'Diamante';
            $pontos_para_proximo = 600 - $pontos_atuais;
            $progresso_nivel = (($pontos_atuais - 300) / 300) * 100;
        } else {
            $proximo_nivel = 'Máximo';
            $pontos_para_proximo = 0;
            $progresso_nivel = 100;
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'gamificacao' => $gamificacao,
                'conquistas' => $conquistas,
                'desafios' => $desafios,
                'progresso' => [
                    'nivel_atual' => $nivel_atual,
                    'proximo_nivel' => $proximo_nivel,
                    'pontos_para_proximo' => max(0, $pontos_para_proximo),
                    'progresso_nivel' => min(100, max(0, $progresso_nivel))
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Erro ao buscar detalhes de gamificação: " . $e->getMessage());
        echo json_encode(['error' => 'Erro ao buscar detalhes']);
    }
}

/**
 * Criar registro inicial de gamificação
 */
function criarRegistroGamificacao($conn, $empresa_id, $motorista_id) {
    try {
        $sql = "INSERT INTO gamificacao_motoristas (
                    motorista_id, empresa_id, periodo, 
                    pontos_totais, nivel_atual, 
                    data_calculo, data_atualizacao
                ) VALUES (
                    :motorista_id, :empresa_id, CURDATE(),
                    0, 'Bronze',
                    NOW(), NOW()
                )";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':motorista_id', $motorista_id);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        
        // Buscar o registro criado
        $sql_select = "SELECT * FROM gamificacao_motoristas 
                       WHERE motorista_id = :motorista_id 
                       AND empresa_id = :empresa_id 
                       ORDER BY data_calculo DESC LIMIT 1";
        
        $stmt_select = $conn->prepare($sql_select);
        $stmt_select->bindParam(':motorista_id', $motorista_id);
        $stmt_select->bindParam(':empresa_id', $empresa_id);
        $stmt_select->execute();
        
        return $stmt_select->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Erro ao criar registro de gamificação: " . $e->getMessage());
        return null;
    }
}

/**
 * Obter desafios ativos
 */
function getDesafios($conn, $empresa_id) {
    try {
        $sql = "SELECT 
                    d.*,
                    CASE 
                        WHEN d.tipo = 'diario' THEN 'Diário'
                        WHEN d.tipo = 'semanal' THEN 'Semanal'
                        WHEN d.tipo = 'mensal' THEN 'Mensal'
                        ELSE 'Trimestral'
                    END as tipo_descricao,
                    CASE 
                        WHEN d.criterio_tipo = 'pontualidade' THEN 'Pontualidade'
                        WHEN d.criterio_tipo = 'consumo' THEN 'Consumo'
                        WHEN d.criterio_tipo = 'multas' THEN 'Multas'
                        WHEN d.criterio_tipo = 'checklist' THEN 'Checklist'
                        WHEN d.criterio_tipo = 'eficiencia' THEN 'Eficiência'
                        ELSE 'Combinado'
                    END as criterio_descricao
                FROM desafios_motoristas d
                WHERE d.empresa_id = :empresa_id
                AND d.ativo = 1
                AND (d.data_fim IS NULL OR d.data_fim >= CURDATE())
                ORDER BY d.pontos_recompensa DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        
        $desafios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $desafios
        ]);
        
    } catch (Exception $e) {
        error_log("Erro ao buscar desafios: " . $e->getMessage());
        echo json_encode(['error' => 'Erro ao buscar desafios']);
    }
}

/**
 * Obter conquistas de um motorista
 */
function getConquistas($conn, $empresa_id, $motorista_id) {
    try {
        $sql = "SELECT 
                    c.*,
                    CASE 
                        WHEN c.tipo_conquista = 'badge' THEN '🏆'
                        WHEN c.tipo_conquista = 'nivel' THEN '⭐'
                        WHEN c.tipo_conquista = 'desafio' THEN '🎯'
                        ELSE '💰'
                    END as tipo_emoji
                FROM conquistas_motoristas c
                WHERE c.motorista_id = :motorista_id
                AND c.empresa_id = :empresa_id
                ORDER BY c.data_conquista DESC
                LIMIT 50";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':motorista_id', $motorista_id);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        
        $conquistas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $conquistas
        ]);
        
    } catch (Exception $e) {
        error_log("Erro ao buscar conquistas: " . $e->getMessage());
        echo json_encode(['error' => 'Erro ao buscar conquistas']);
    }
}

/**
 * Calcular gamificação de motoristas
 */
function calcularGamificacao($conn, $empresa_id) {
    try {
        $periodo = isset($_GET['periodo']) ? $_GET['periodo'] : date('Y-m-d');
        
        // Buscar motoristas da empresa
        $sql_motoristas = "SELECT id FROM motoristas WHERE empresa_id = :empresa_id";
        $stmt_motoristas = $conn->prepare($sql_motoristas);
        $stmt_motoristas->bindParam(':empresa_id', $empresa_id);
        $stmt_motoristas->execute();
        
        $motoristas = $stmt_motoristas->fetchAll(PDO::FETCH_COLUMN);
        
        $total_calculados = 0;
        
        foreach ($motoristas as $motorista_id) {
            $pontos = calcularPontosMotorista($conn, $empresa_id, $motorista_id, $periodo);
            
            if ($pontos > 0) {
                atualizarGamificacaoMotorista($conn, $empresa_id, $motorista_id, $periodo, $pontos);
                $total_calculados++;
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Gamificação calculada para {$total_calculados} motoristas",
            'periodo' => $periodo
        ]);
        
    } catch (Exception $e) {
        error_log("Erro ao calcular gamificação: " . $e->getMessage());
        echo json_encode(['error' => 'Erro ao calcular gamificação']);
    }
}

/**
 * Calcular pontos de um motorista
 */
function calcularPontosMotorista($conn, $empresa_id, $motorista_id, $periodo) {
    $pontos = 0;
    
    try {
        // Pontos por pontualidade (últimos 30 dias)
        $sql_pontualidade = "SELECT 
                               COUNT(*) as total,
                               SUM(CASE WHEN no_prazo = 1 THEN 1 ELSE 0 END) as pontuais
                             FROM rotas 
                             WHERE motorista_id = :motorista_id
                             AND data_rota >= DATE_SUB(:periodo, INTERVAL 30 DAY)
                             AND status = 'aprovado'";
        
        $stmt_pontualidade = $conn->prepare($sql_pontualidade);
        $stmt_pontualidade->bindParam(':motorista_id', $motorista_id);
        $stmt_pontualidade->bindParam(':periodo', $periodo);
        $stmt_pontualidade->execute();
        
        $result_pontualidade = $stmt_pontualidade->fetch(PDO::FETCH_ASSOC);
        
        if ($result_pontualidade['total'] > 0) {
            $percentual_pontualidade = ($result_pontualidade['pontuais'] / $result_pontualidade['total']) * 100;
            $pontos += round($percentual_pontualidade * 0.1); // 10 pontos por 100% pontualidade
        }
        
        // Pontos por checklist (últimos 30 dias)
        $sql_checklist = "SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'aprovado' THEN 1 ELSE 0 END) as completos
                          FROM checklist_viagem 
                          WHERE motorista_id = :motorista_id
                          AND data_checklist >= DATE_SUB(:periodo, INTERVAL 30 DAY)";
        
        $stmt_checklist = $conn->prepare($sql_checklist);
        $stmt_checklist->bindParam(':motorista_id', $motorista_id);
        $stmt_checklist->bindParam(':periodo', $periodo);
        $stmt_checklist->execute();
        
        $result_checklist = $stmt_checklist->fetch(PDO::FETCH_ASSOC);
        
        if ($result_checklist['total'] > 0) {
            $percentual_checklist = ($result_checklist['completos'] / $result_checklist['total']) * 100;
            $pontos += round($percentual_checklist * 0.05); // 5 pontos por 100% checklist
        }
        
        // Pontos por não ter multas (últimos 30 dias)
        $sql_multas = "SELECT COUNT(*) as total 
                       FROM multas 
                       WHERE motorista_id = :motorista_id
                       AND data_infracao >= DATE_SUB(:periodo, INTERVAL 30 DAY)";
        
        $stmt_multas = $conn->prepare($sql_multas);
        $stmt_multas->bindParam(':motorista_id', $motorista_id);
        $stmt_multas->bindParam(':periodo', $periodo);
        $stmt_multas->execute();
        
        $result_multas = $stmt_multas->fetch(PDO::FETCH_ASSOC);
        
        if ($result_multas['total'] == 0) {
            $pontos += 20; // 20 pontos por não ter multas
        }
        
        // Pontos por eficiência de consumo
        $sql_consumo = "SELECT 
                          AVG(a.litros / NULLIF(r.distancia_km, 0)) as consumo_medio
                        FROM rotas r
                        JOIN abastecimentos a ON r.id = a.rota_id
                        WHERE r.motorista_id = :motorista_id
                        AND r.data_rota >= DATE_SUB(:periodo, INTERVAL 30 DAY)
                        AND r.status = 'aprovado'";
        
        $stmt_consumo = $conn->prepare($sql_consumo);
        $stmt_consumo->bindParam(':motorista_id', $motorista_id);
        $stmt_consumo->bindParam(':periodo', $periodo);
        $stmt_consumo->execute();
        
        $result_consumo = $stmt_consumo->fetch(PDO::FETCH_ASSOC);
        
        if ($result_consumo['consumo_medio'] && $result_consumo['consumo_medio'] <= 0.5) {
            $pontos += 15; // 15 pontos por consumo eficiente
        }
        
        return $pontos;
        
    } catch (Exception $e) {
        error_log("Erro ao calcular pontos do motorista: " . $e->getMessage());
        return 0;
    }
}

/**
 * Atualizar gamificação de um motorista
 */
function atualizarGamificacaoMotorista($conn, $empresa_id, $motorista_id, $periodo, $pontos) {
    try {
        // Determinar nível baseado nos pontos
        $nivel = 'Bronze';
        if ($pontos >= 600) {
            $nivel = 'Diamante';
        } elseif ($pontos >= 300) {
            $nivel = 'Ouro';
        } elseif ($pontos >= 100) {
            $nivel = 'Prata';
        }
        
        // Inserir ou atualizar gamificação
        $sql = "INSERT INTO gamificacao_motoristas (
                    motorista_id, empresa_id, periodo,
                    pontos_totais, nivel_atual, rotas_concluidas,
                    data_calculo, data_atualizacao
                ) VALUES (
                    :motorista_id, :empresa_id, :periodo,
                    :pontos, :nivel, 0,
                    NOW(), NOW()
                ) ON DUPLICATE KEY UPDATE
                    pontos_totais = :pontos,
                    nivel_atual = :nivel,
                    data_atualizacao = NOW()";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':motorista_id', $motorista_id);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->bindParam(':periodo', $periodo);
        $stmt->bindParam(':pontos', $pontos);
        $stmt->bindParam(':nivel', $nivel);
        $stmt->execute();
        
    } catch (Exception $e) {
        error_log("Erro ao atualizar gamificação: " . $e->getMessage());
    }
}

/**
 * Obter níveis de gamificação
 */
function getNiveis($conn, $empresa_id) {
    $niveis = [
        [
            'nome' => 'Bronze',
            'pontos_min' => 0,
            'pontos_max' => 99,
            'emoji' => '🥉',
            'cor' => '#CD7F32',
            'descricao' => 'Iniciante - Começando a jornada'
        ],
        [
            'nome' => 'Prata',
            'pontos_min' => 100,
            'pontos_max' => 299,
            'emoji' => '🥈',
            'cor' => '#C0C0C0',
            'descricao' => 'Intermediário - Mostrando progresso'
        ],
        [
            'nome' => 'Ouro',
            'pontos_min' => 300,
            'pontos_max' => 599,
            'emoji' => '🥇',
            'cor' => '#FFD700',
            'descricao' => 'Avançado - Excelência em performance'
        ],
        [
            'nome' => 'Diamante',
            'pontos_min' => 600,
            'pontos_max' => 999,
            'emoji' => '💎',
            'cor' => '#B9F2FF',
            'descricao' => 'Expert - Mestre da eficiência'
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $niveis
    ]);
}

/**
 * Obter badges disponíveis
 */
function getBadges($conn, $empresa_id) {
    $badges = [
        [
            'nome' => 'Primeira Viagem',
            'descricao' => 'Completou sua primeira viagem',
            'emoji' => '🚛',
            'requisito' => '1 rota completada'
        ],
        [
            'nome' => 'Pontualidade Perfeita',
            'descricao' => '100% de pontualidade no mês',
            'emoji' => '⏰',
            'requisito' => 'Todas as rotas no prazo'
        ],
        [
            'nome' => 'Economia de Combustível',
            'descricao' => 'Consumo eficiente de combustível',
            'emoji' => '⛽',
            'requisito' => 'Consumo abaixo de 0.5L/km'
        ],
        [
            'nome' => 'Cidadão Exemplar',
            'descricao' => 'Sem multas no período',
            'emoji' => '🏆',
            'requisito' => 'Zero multas em 30 dias'
        ],
        [
            'nome' => 'Checklist Master',
            'descricao' => 'Todos os checklists aprovados',
            'emoji' => '✅',
            'requisito' => '100% de checklists aprovados'
        ],
        [
            'nome' => 'Streak de Fogo',
            'descricao' => 'Sequência de dias com boa performance',
            'emoji' => '🔥',
            'requisito' => '7 dias consecutivos de excelência'
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $badges
    ]);
}

/**
 * Criar novo desafio
 */
function createDesafio($conn, $empresa_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['error' => 'Dados não fornecidos']);
        return;
    }
    
    try {
        $sql = "INSERT INTO desafios_motoristas (
                    empresa_id, nome, descricao, tipo,
                    criterio_tipo, criterio_valor, criterio_periodo,
                    pontos_recompensa, badge_recompensa, bonus_recompensa,
                    data_inicio, ativo
                ) VALUES (
                    :empresa_id, :nome, :descricao, :tipo,
                    :criterio_tipo, :criterio_valor, :criterio_periodo,
                    :pontos_recompensa, :badge_recompensa, :bonus_recompensa,
                    :data_inicio, 1
                )";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->bindParam(':nome', $input['nome']);
        $stmt->bindParam(':descricao', $input['descricao']);
        $stmt->bindParam(':tipo', $input['tipo']);
        $stmt->bindParam(':criterio_tipo', $input['criterio_tipo']);
        $stmt->bindParam(':criterio_valor', $input['criterio_valor']);
        $stmt->bindParam(':criterio_periodo', $input['criterio_periodo'], PDO::PARAM_INT);
        $stmt->bindParam(':pontos_recompensa', $input['pontos_recompensa'], PDO::PARAM_INT);
        $stmt->bindParam(':badge_recompensa', $input['badge_recompensa']);
        $stmt->bindParam(':bonus_recompensa', $input['bonus_recompensa']);
        $stmt->bindParam(':data_inicio', $input['data_inicio']);
        
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Desafio criado com sucesso',
            'id' => $conn->lastInsertId()
        ]);
        
    } catch (Exception $e) {
        error_log("Erro ao criar desafio: " . $e->getMessage());
        echo json_encode(['error' => 'Erro ao criar desafio']);
    }
}

/**
 * Obter estatísticas de gamificação
 */
function getEstatisticasGamificacao($conn, $empresa_id) {
    try {
        $sql = "SELECT 
                    COUNT(DISTINCT g.motorista_id) as total_motoristas,
                    AVG(g.pontos_totais) as media_pontos,
                    MAX(g.pontos_totais) as max_pontos,
                    MIN(g.pontos_totais) as min_pontos,
                    COUNT(CASE WHEN g.nivel_atual = 'Diamante' THEN 1 END) as total_diamante,
                    COUNT(CASE WHEN g.nivel_atual = 'Ouro' THEN 1 END) as total_ouro,
                    COUNT(CASE WHEN g.nivel_atual = 'Prata' THEN 1 END) as total_prata,
                    COUNT(CASE WHEN g.nivel_atual = 'Bronze' THEN 1 END) as total_bronze,
                    SUM(g.rotas_concluidas) as total_rotas_concluidas,
                    SUM(g.desafios_completos) as total_desafios_completos
                FROM gamificacao_motoristas g
                WHERE g.empresa_id = :empresa_id
                AND g.periodo >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        
        $estatisticas = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $estatisticas
        ]);
        
    } catch (Exception $e) {
        error_log("Erro ao buscar estatísticas: " . $e->getMessage());
        echo json_encode(['error' => 'Erro ao buscar estatísticas']);
    }
}
?>
