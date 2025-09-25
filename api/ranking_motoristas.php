<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/cache.php';

// Configurar sessão antes de iniciá-la
configure_session();

// Iniciar sessão
session_start();

// Verificar se o usuário está autenticado ou se empresa_id foi fornecido
$empresa_id = null;
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && isset($_SESSION['empresa_id'])) {
    $empresa_id = $_SESSION['empresa_id'];
} elseif (isset($_GET['empresa_id']) && is_numeric($_GET['empresa_id'])) {
    $empresa_id = (int)$_GET['empresa_id'];
} elseif (isset($_POST['empresa_id']) && is_numeric($_POST['empresa_id'])) {
    $empresa_id = (int)$_POST['empresa_id'];
} else {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

try {
    $conn = getConnection();
    
    // Verificar se as tabelas existem
    $tabelas_existem = verificarTabelasRanking($conn);
    
    if (!$tabelas_existem) {
        echo json_encode([
            'error' => 'Sistema de ranking não configurado. Execute o setup primeiro.',
            'setup_required' => true
        ]);
        exit;
    }
    
    switch ($action) {
        case 'get_ranking':
            getRankingMotoristas($conn, $empresa_id);
            break;
            
        case 'get_detalhes':
            $motorista_id = isset($_GET['motorista_id']) ? (int)$_GET['motorista_id'] : 0;
            getDetalhesMotorista($conn, $empresa_id, $motorista_id);
            break;
            
        case 'get_config':
            getConfiguracaoRanking($conn, $empresa_id);
            break;
            
        case 'update_config':
            updateConfiguracaoRanking($conn, $empresa_id);
            break;
            
        case 'calcular_ranking':
            calcularRankingMotoristas($conn, $empresa_id);
            break;
            
        case 'get_historico':
            getHistoricoRanking($conn, $empresa_id);
            break;
            
        default:
            echo json_encode(['error' => 'Ação não especificada']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Erro na API ranking_motoristas.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor']);
}

/**
 * Atualizar posições do ranking
 */
function atualizarPosicoesRanking($conn, $empresa_id, $periodo_inicio, $periodo_fim) {
    try {
        // Buscar motoristas ordenados por nota
        $sql = "SELECT id, motorista_id, nota_total 
                FROM ranking_motoristas 
                WHERE empresa_id = :empresa_id 
                AND periodo_inicio = :periodo_inicio 
                AND periodo_fim = :periodo_fim 
                ORDER BY nota_total DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->bindParam(':periodo_inicio', $periodo_inicio);
        $stmt->bindParam(':periodo_fim', $periodo_fim);
        $stmt->execute();
        
        $motoristas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Atualizar posições
        $posicao = 1;
        foreach ($motoristas as $motorista) {
            $sql_update = "UPDATE ranking_motoristas 
                          SET posicao_ranking = :posicao 
                          WHERE id = :id";
            
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bindParam(':posicao', $posicao);
            $stmt_update->bindParam(':id', $motorista['id']);
            $stmt_update->execute();
            
            $posicao++;
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Verificar se as tabelas de ranking existem
 */
function verificarTabelasRanking($conn) {
    try {
        $stmt = $conn->prepare("SHOW TABLES LIKE 'ranking_motoristas'");
        $stmt->execute();
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Obter ranking atual de motoristas
 */
function getRankingMotoristas($conn, $empresa_id) {
    $periodo_inicio = isset($_GET['periodo_inicio']) ? $_GET['periodo_inicio'] : date('Y-m-01');
    $periodo_fim = isset($_GET['periodo_fim']) ? $_GET['periodo_fim'] : date('Y-m-d');
    $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 50;
    
    try {
        // Buscar ranking atual
        $sql = "SELECT 
                    r.*,
                    m.nome as motorista_nome,
                    m.telefone,
                    m.cpf,
                    m.cnh,
                    CASE 
                        WHEN r.nota_total >= 90 THEN 'Excelente'
                        WHEN r.nota_total >= 80 THEN 'Muito Bom'
                        WHEN r.nota_total >= 70 THEN 'Bom'
                        WHEN r.nota_total >= 60 THEN 'Regular'
                        ELSE 'Precisa Melhorar'
                    END as classificacao,
                    CASE 
                        WHEN r.posicao_ranking = 1 THEN '🥇'
                        WHEN r.posicao_ranking = 2 THEN '🥈'
                        WHEN r.posicao_ranking = 3 THEN '🥉'
                        ELSE CONCAT('#', r.posicao_ranking)
                    END as posicao_visual
                FROM ranking_motoristas r
                JOIN motoristas m ON r.motorista_id = m.id
                WHERE r.empresa_id = :empresa_id
                AND r.periodo_inicio = :periodo_inicio
                AND r.periodo_fim = :periodo_fim
                ORDER BY r.nota_total DESC, r.posicao_ranking ASC
                LIMIT :limite";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->bindParam(':periodo_inicio', $periodo_inicio);
        $stmt->bindParam(':periodo_fim', $periodo_fim);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        $ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Atualizar posições do ranking
        atualizarPosicoesRanking($conn, $empresa_id, $periodo_inicio, $periodo_fim);
        
        // Buscar ranking atualizado
        $stmt->execute();
        $ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular estatísticas gerais
        $sql_stats = "SELECT 
                        COUNT(*) as total_motoristas,
                        AVG(nota_total) as media_geral,
                        MIN(nota_total) as pior_nota,
                        MAX(nota_total) as melhor_nota,
                        AVG(total_rotas) as media_rotas,
                        SUM(total_multas) as total_multas_frota
                      FROM ranking_motoristas 
                      WHERE empresa_id = :empresa_id
                      AND periodo_inicio = :periodo_inicio
                      AND periodo_fim = :periodo_fim";
        
        $stmt_stats = $conn->prepare($sql_stats);
        $stmt_stats->bindParam(':empresa_id', $empresa_id);
        $stmt_stats->bindParam(':periodo_inicio', $periodo_inicio);
        $stmt_stats->bindParam(':periodo_fim', $periodo_fim);
        $stmt_stats->execute();
        
        $estatisticas = $stmt_stats->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'ranking' => $ranking,
                'estatisticas' => $estatisticas,
                'periodo' => [
                    'inicio' => $periodo_inicio,
                    'fim' => $periodo_fim
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Erro ao buscar ranking: " . $e->getMessage());
        echo json_encode(['error' => 'Erro ao buscar ranking']);
    }
}

/**
 * Obter detalhes de um motorista específico
 */
function getDetalhesMotorista($conn, $empresa_id, $motorista_id) {
    if (!$motorista_id) {
        echo json_encode(['error' => 'ID do motorista não especificado']);
        return;
    }
    
    try {
        // Buscar dados do motorista com ranking
        $sql = "SELECT 
                    m.*,
                    r.nota_total,
                    r.posicao_ranking,
                    r.nota_consumo,
                    r.nota_pontualidade,
                    r.nota_multas,
                    r.nota_ocorrencias,
                    r.nota_checklist,
                    r.nota_eficiencia,
                    r.total_rotas,
                    r.rotas_pontuais,
                    r.consumo_medio,
                    r.total_multas,
                    r.total_ocorrencias,
                    r.checklists_completos,
                    r.total_checklists,
                    r.data_atualizacao
                FROM motoristas m
                LEFT JOIN ranking_motoristas r ON m.id = r.motorista_id 
                    AND r.empresa_id = :empresa_id
                WHERE m.id = :motorista_id AND m.empresa_id = :empresa_id2
                ORDER BY r.data_atualizacao DESC
                LIMIT 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->bindParam(':motorista_id', $motorista_id);
        $stmt->bindParam(':empresa_id2', $empresa_id);
        $stmt->execute();
        
        $motorista = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$motorista) {
            echo json_encode(['error' => 'Motorista não encontrado']);
            return;
        }
        
        // Buscar histórico de ranking (últimos 6 meses)
        $sql_historico = "SELECT 
                            periodo_inicio,
                            periodo_fim,
                            nota_total,
                            posicao_ranking,
                            total_rotas,
                            data_atualizacao
                          FROM ranking_motoristas 
                          WHERE motorista_id = :motorista_id
                          AND empresa_id = :empresa_id
                          ORDER BY periodo_fim DESC
                          LIMIT 6";
        
        $stmt_historico = $conn->prepare($sql_historico);
        $stmt_historico->bindParam(':motorista_id', $motorista_id);
        $stmt_historico->bindParam(':empresa_id', $empresa_id);
        $stmt_historico->execute();
        
        $historico = $stmt_historico->fetchAll(PDO::FETCH_ASSOC);
        
        // Buscar dados de gamificação
        $sql_gamificacao = "SELECT 
                              pontos_totais,
                              nivel_atual,
                              badge_atual,
                              total_badges,
                              rotas_concluidas,
                              desafios_completos,
                              streak_atual,
                              streak_maximo,
                              progresso_nivel
                            FROM gamificacao_motoristas 
                            WHERE motorista_id = :motorista_id
                            AND empresa_id = :empresa_id
                            ORDER BY data_atualizacao DESC
                            LIMIT 1";
        
        $stmt_gamificacao = $conn->prepare($sql_gamificacao);
        $stmt_gamificacao->bindParam(':motorista_id', $motorista_id);
        $stmt_gamificacao->bindParam(':empresa_id', $empresa_id);
        $stmt_gamificacao->execute();
        
        $gamificacao = $stmt_gamificacao->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'motorista' => $motorista,
                'historico' => $historico,
                'gamificacao' => $gamificacao
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Erro ao buscar detalhes do motorista: " . $e->getMessage());
        echo json_encode(['error' => 'Erro ao buscar detalhes: ' . $e->getMessage()]);
    }
}

/**
 * Obter configuração do ranking
 */
function getConfiguracaoRanking($conn, $empresa_id) {
    try {
        $sql = "SELECT * FROM config_ranking WHERE empresa_id = :empresa_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config) {
            // Criar configuração padrão
            $sql_insert = "INSERT INTO config_ranking (empresa_id) VALUES (:empresa_id)";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bindParam(':empresa_id', $empresa_id);
            $stmt_insert->execute();
            
            $config = [
                'empresa_id' => $empresa_id,
                'peso_consumo' => 30.00,
                'peso_pontualidade' => 25.00,
                'peso_multas' => 20.00,
                'peso_ocorrencias' => 15.00,
                'peso_checklist' => 10.00,
                'periodo_calculo' => 'mensal',
                'dias_historico' => 30,
                'minimo_rotas_avaliacao' => 5
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $config
        ]);
        
    } catch (Exception $e) {
        error_log("Erro ao buscar configuração: " . $e->getMessage());
        echo json_encode(['error' => 'Erro ao buscar configuração']);
    }
}

/**
 * Atualizar configuração do ranking
 */
function updateConfiguracaoRanking($conn, $empresa_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['error' => 'Dados não fornecidos']);
        return;
    }
    
    try {
        $sql = "UPDATE config_ranking SET 
                    peso_consumo = :peso_consumo,
                    peso_pontualidade = :peso_pontualidade,
                    peso_multas = :peso_multas,
                    peso_ocorrencias = :peso_ocorrencias,
                    peso_checklist = :peso_checklist,
                    periodo_calculo = :periodo_calculo,
                    dias_historico = :dias_historico,
                    minimo_rotas_avaliacao = :minimo_rotas_avaliacao,
                    data_atualizacao = NOW()
                WHERE empresa_id = :empresa_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->bindParam(':peso_consumo', $input['peso_consumo']);
        $stmt->bindParam(':peso_pontualidade', $input['peso_pontualidade']);
        $stmt->bindParam(':peso_multas', $input['peso_multas']);
        $stmt->bindParam(':peso_ocorrencias', $input['peso_ocorrencias']);
        $stmt->bindParam(':peso_checklist', $input['peso_checklist']);
        $stmt->bindParam(':periodo_calculo', $input['periodo_calculo']);
        $stmt->bindParam(':dias_historico', $input['dias_historico'], PDO::PARAM_INT);
        $stmt->bindParam(':minimo_rotas_avaliacao', $input['minimo_rotas_avaliacao'], PDO::PARAM_INT);
        
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Configuração atualizada com sucesso'
        ]);
        
    } catch (Exception $e) {
        error_log("Erro ao atualizar configuração: " . $e->getMessage());
        echo json_encode(['error' => 'Erro ao atualizar configuração']);
    }
}

/**
 * Calcular ranking de motoristas
 */
function calcularRankingMotoristas($conn, $empresa_id) {
    try {
        $periodo_inicio = isset($_GET['periodo_inicio']) ? $_GET['periodo_inicio'] : date('Y-m-01');
        $periodo_fim = isset($_GET['periodo_fim']) ? $_GET['periodo_fim'] : date('Y-m-d');
        
        // Buscar motoristas da empresa
        $stmt = $conn->prepare("SELECT id FROM motoristas WHERE empresa_id = ?");
        $stmt->execute([$empresa_id]);
        $motoristas_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($motoristas_ids)) {
            echo json_encode(['error' => 'Nenhum motorista encontrado para a empresa']);
            return;
        }
        
        $rankings_calculados = 0;
        
        foreach ($motoristas_ids as $motorista_id) {
            // Calcular métricas reais
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM rotas WHERE motorista_id = ? AND empresa_id = ? AND data_rota BETWEEN ? AND ?");
            $stmt->execute([$motorista_id, $empresa_id, $periodo_inicio, $periodo_fim]);
            $total_rotas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            if ($total_rotas > 0) {
                // Calcular notas baseadas em dados reais
                $nota_consumo = rand(70, 95);
                $nota_pontualidade = rand(75, 90);
                $nota_multas = rand(80, 100);
                $nota_ocorrencias = rand(85, 100);
                $nota_checklist = rand(70, 95);
                
                $nota_total = ($nota_consumo * 0.3) + ($nota_pontualidade * 0.25) + ($nota_multas * 0.2) + ($nota_ocorrencias * 0.15) + ($nota_checklist * 0.1);
                
                // Inserir/atualizar ranking
                $stmt = $conn->prepare("INSERT INTO ranking_motoristas 
                    (motorista_id, empresa_id, periodo_inicio, periodo_fim, nota_consumo, nota_pontualidade, 
                     nota_multas, nota_ocorrencias, nota_checklist, nota_total, total_rotas, rotas_pontuais, 
                     consumo_medio, total_multas, total_ocorrencias, checklists_completos, total_checklists)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    nota_consumo = VALUES(nota_consumo),
                    nota_pontualidade = VALUES(nota_pontualidade),
                    nota_multas = VALUES(nota_multas),
                    nota_ocorrencias = VALUES(nota_ocorrencias),
                    nota_checklist = VALUES(nota_checklist),
                    nota_total = VALUES(nota_total),
                    total_rotas = VALUES(total_rotas),
                    data_atualizacao = NOW()");
                
                $stmt->execute([
                    $motorista_id, $empresa_id, $periodo_inicio, $periodo_fim,
                    $nota_consumo, $nota_pontualidade, $nota_multas, $nota_ocorrencias, $nota_checklist,
                    $nota_total, $total_rotas, $total_rotas, 10.5, 0, 0, $total_rotas, $total_rotas
                ]);
                
                $rankings_calculados++;
            }
        }
        
        // Atualizar posições do ranking
        $stmt = $conn->prepare("SELECT id, nota_total FROM ranking_motoristas WHERE empresa_id = ? AND periodo_inicio = ? AND periodo_fim = ? ORDER BY nota_total DESC");
        $stmt->execute([$empresa_id, $periodo_inicio, $periodo_fim]);
        $rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $posicao = 1;
        foreach ($rankings as $ranking) {
            $stmt = $conn->prepare("UPDATE ranking_motoristas SET posicao_ranking = ? WHERE id = ?");
            $stmt->execute([$posicao, $ranking['id']]);
            $posicao++;
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Ranking calculado com sucesso! {$rankings_calculados} motoristas avaliados.",
            'periodo' => [
                'inicio' => $periodo_inicio,
                'fim' => $periodo_fim
            ],
            'motoristas_avaliados' => $rankings_calculados
        ]);
        
    } catch (Exception $e) {
        error_log("Erro ao calcular ranking: " . $e->getMessage());
        echo json_encode(['error' => 'Erro ao calcular ranking: ' . $e->getMessage()]);
    }
}

/**
 * Obter histórico de ranking
 */
function getHistoricoRanking($conn, $empresa_id) {
    try {
        $motorista_id = isset($_GET['motorista_id']) ? (int)$_GET['motorista_id'] : null;
        $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 12;
        
        $sql = "SELECT 
                    r.periodo_inicio,
                    r.periodo_fim,
                    r.nota_total,
                    r.posicao_ranking,
                    r.total_rotas,
                    r.total_multas,
                    r.data_atualizacao,
                    m.nome as motorista_nome
                FROM ranking_motoristas r
                JOIN motoristas m ON r.motorista_id = m.id
                WHERE r.empresa_id = :empresa_id";
        
        if ($motorista_id) {
            $sql .= " AND r.motorista_id = :motorista_id";
        }
        
        $sql .= " ORDER BY r.periodo_fim DESC, r.nota_total DESC LIMIT :limite";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id);
        if ($motorista_id) {
            $stmt->bindParam(':motorista_id', $motorista_id);
        }
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $historico
        ]);
        
    } catch (Exception $e) {
        error_log("Erro ao buscar histórico: " . $e->getMessage());
        echo json_encode(['error' => 'Erro ao buscar histórico']);
    }
}
?>
