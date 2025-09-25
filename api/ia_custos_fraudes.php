<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

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

header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : '';
$empresa_id = $_SESSION['empresa_id'];

try {
    $conn = getConnection();
    
    switch ($action) {
        case 'previsao_lucro':
            $rota_id = isset($_GET['rota_id']) ? (int)$_GET['rota_id'] : 0;
            getPrevisaoLucro($conn, $empresa_id, $rota_id);
            break;
            
        case 'detectar_fraude':
            $abastecimento_id = isset($_GET['abastecimento_id']) ? (int)$_GET['abastecimento_id'] : 0;
            detectarFraude($conn, $empresa_id, $abastecimento_id);
            break;
            
        case 'ranking_risco':
            getRankingRisco($conn, $empresa_id);
            break;
            
        case 'analise_custos':
            getAnaliseCustos($conn, $empresa_id);
            break;
            
        case 'dashboard_ia':
            getDashboardIA($conn, $empresa_id);
            break;
            
        case 'executar_analise':
            executarAnaliseCompleta($conn, $empresa_id);
            break;
            
        default:
            echo json_encode(['error' => 'Ação não especificada']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Erro na API ia_custos_fraudes.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor']);
}

/**
 * Obter previsão de lucro para uma rota
 */
function getPrevisaoLucro($conn, $empresa_id, $rota_id) {
    if (!$rota_id) {
        echo json_encode(['error' => 'ID da rota não especificado']);
        return;
    }
    
    try {
        // Buscar dados da rota
        $sql = "SELECT 
                    r.*,
                    v.tipo as tipo_veiculo,
                    v.tipo_combustivel,
                    m.nome as motorista_nome,
                    co.nome as cidade_origem,
                    cd.nome as cidade_destino
                FROM rotas r
                JOIN veiculos v ON r.veiculo_id = v.id
                JOIN motoristas m ON r.motorista_id = m.id
                LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                WHERE r.id = :rota_id AND r.empresa_id = :empresa_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':rota_id', $rota_id);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        
        $rota = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$rota) {
            echo json_encode(['error' => 'Rota não encontrada']);
            return;
        }
        
        // Calcular previsão de lucro
        $previsao = calcularPrevisaoLucroCompleta($rota);
        
        echo json_encode([
            'success' => true,
            'data' => $previsao
        ]);
        
    } catch (Exception $e) {
        error_log("Erro ao calcular previsão de lucro: " . $e->getMessage());
        echo json_encode(['error' => 'Erro ao calcular previsão de lucro']);
    }
}

/**
 * Calcular previsão de lucro completa
 */
function calcularPrevisaoLucroCompleta($rota) {
    global $conn;
    
    // Calcular custos variáveis
    $custos_variaveis = calcularCustosVariaveis($rota);
    
    // Calcular custos fixos
    $custos_fixos = calcularCustosFixos($rota);
    
    // Calcular receita
    $receita = floatval($rota['frete'] ?? 0);
    
    // Calcular lucro previsto
    $lucro_previsto = $receita - $custos_variaveis - $custos_fixos;
    $margem_lucro = ($receita > 0) ? ($lucro_previsto / $receita) * 100 : 0;
    
    return [
        'rota_id' => $rota['id'],
        'motorista' => $rota['motorista_nome'],
        'origem_destino' => $rota['cidade_origem'] . ' → ' . $rota['cidade_destino'],
        'distancia' => $rota['distancia_km'],
        'receita' => $receita,
        'custos_variaveis' => $custos_variaveis,
        'custos_fixos' => $custos_fixos,
        'lucro_previsto' => $lucro_previsto,
        'margem_lucro' => $margem_lucro,
        'status' => $lucro_previsto > 0 ? 'lucrativa' : 'prejuizo',
        'recomendacao' => $lucro_previsto > 0 ? 'Aprovar rota' : 'Revisar custos ou frete'
    ];
}

/**
 * Calcular custos variáveis
 */
function calcularCustosVariaveis($rota) {
    $custos = 0;
    
    // 1. Custo de combustível estimado
    $distancia = floatval($rota['distancia_km'] ?? 0);
    $tipo_veiculo = $rota['tipo_veiculo'] ?? 'Caminhão';
    $tipo_combustivel = $rota['tipo_combustivel'] ?? 'Diesel';
    
    $consumos = [
        'Caminhão' => ['Diesel' => 0.35, 'Gasolina' => 0.45, 'Etanol' => 0.65],
        'Van' => ['Diesel' => 0.25, 'Gasolina' => 0.30, 'Etanol' => 0.40],
        'Carreta' => ['Diesel' => 0.40, 'Gasolina' => 0.50, 'Etanol' => 0.70]
    ];
    
    $precos = ['Diesel' => 4.50, 'Gasolina' => 5.80, 'Etanol' => 4.20];
    
    $consumo_por_km = $consumos[$tipo_veiculo][$tipo_combustivel] ?? 0.35;
    $preco_combustivel = $precos[$tipo_combustivel] ?? 4.50;
    
    $custo_combustivel = $distancia * $consumo_por_km * $preco_combustivel;
    $custos += $custo_combustivel;
    
    // 2. Comissão do motorista
    $comissao = floatval($rota['comissao'] ?? 0);
    $custos += $comissao;
    
    // 3. Custos de manutenção estimados
    $custo_manutencao = $distancia * 0.15; // R$ 0.15 por km
    $custos += $custo_manutencao;
    
    return $custos;
}

/**
 * Calcular custos fixos
 */
function calcularCustosFixos($rota) {
    $distancia = floatval($rota['distancia_km'] ?? 0);
    
    // Custo de depreciação
    $custo_depreciacao = $distancia * 0.25;
    
    // Custo de seguro
    $custo_seguro = $distancia * 0.08;
    
    // Custo de licenciamento
    $custo_licenciamento = $distancia * 0.05;
    
    return $custo_depreciacao + $custo_seguro + $custo_licenciamento;
}

/**
 * Detectar fraude em abastecimento
 */
function detectarFraude($conn, $empresa_id, $abastecimento_id) {
    if (!$abastecimento_id) {
        echo json_encode(['error' => 'ID do abastecimento não especificado']);
        return;
    }
    
    try {
        // Buscar dados do abastecimento
        $sql = "SELECT 
                    a.*,
                    r.distancia_km,
                    v.placa,
                    v.tipo,
                    v.tipo_combustivel,
                    m.nome as motorista_nome
                FROM abastecimentos a
                JOIN rotas r ON a.rota_id = r.id
                JOIN veiculos v ON r.veiculo_id = v.id
                JOIN motoristas m ON r.motorista_id = m.id
                WHERE a.id = :abastecimento_id AND a.empresa_id = :empresa_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':abastecimento_id', $abastecimento_id);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        
        $abastecimento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$abastecimento) {
            echo json_encode(['error' => 'Abastecimento não encontrado']);
            return;
        }
        
        // Analisar fraude
        $fraudes_detectadas = [];
        $score_risco = 0;
        
        // 1. Verificar consumo excessivo
        $consumo_por_km = $abastecimento['litros'] / $abastecimento['distancia_km'];
        $consumo_esperado = obterConsumoMedio($abastecimento['tipo'], $abastecimento['tipo_combustivel']);
        
        if ($consumo_por_km > $consumo_esperado * 1.5) {
            $fraudes_detectadas[] = [
                'tipo' => 'consumo_excessivo',
                'descricao' => 'Consumo muito acima do esperado',
                'valor_atual' => $consumo_por_km,
                'valor_esperado' => $consumo_esperado,
                'severidade' => 'alta'
            ];
            $score_risco += 30;
        }
        
        // 2. Verificar preço do combustível
        $preco_por_litro = $abastecimento['valor_total'] / $abastecimento['litros'];
        $preco_esperado = obterPrecoCombustivel($abastecimento['tipo_combustivel']);
        
        if ($preco_por_litro > $preco_esperado * 1.3) {
            $fraudes_detectadas[] = [
                'tipo' => 'preco_excessivo',
                'descricao' => 'Preço do combustível muito acima do mercado',
                'valor_atual' => $preco_por_litro,
                'valor_esperado' => $preco_esperado,
                'severidade' => 'media'
            ];
            $score_risco += 20;
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'abastecimento_id' => $abastecimento_id,
                'motorista' => $abastecimento['motorista_nome'],
                'veiculo' => $abastecimento['placa'],
                'score_risco' => min(100, $score_risco),
                'fraudes_detectadas' => $fraudes_detectadas,
                'status' => $score_risco > 50 ? 'alto_risco' : ($score_risco > 25 ? 'medio_risco' : 'baixo_risco')
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Erro ao detectar fraude: " . $e->getMessage());
        echo json_encode(['error' => 'Erro ao detectar fraude']);
    }
}

/**
 * Obter consumo médio
 */
function obterConsumoMedio($tipo_veiculo, $tipo_combustivel) {
    $consumos = [
        'Caminhão' => ['Diesel' => 0.35, 'Gasolina' => 0.45, 'Etanol' => 0.65],
        'Van' => ['Diesel' => 0.25, 'Gasolina' => 0.30, 'Etanol' => 0.40],
        'Carreta' => ['Diesel' => 0.40, 'Gasolina' => 0.50, 'Etanol' => 0.70]
    ];
    
    return $consumos[$tipo_veiculo][$tipo_combustivel] ?? 0.35;
}

/**
 * Obter preço do combustível
 */
function obterPrecoCombustivel($tipo_combustivel) {
    $precos = ['Diesel' => 4.50, 'Gasolina' => 5.80, 'Etanol' => 4.20];
    return $precos[$tipo_combustivel] ?? 4.50;
}

/**
 * Obter ranking de risco
 */
function getRankingRisco($conn, $empresa_id) {
    try {
        $sql = "SELECT 
                    m.id,
                    m.nome,
                    COUNT(r.id) as total_rotas,
                    AVG(CASE WHEN r.no_prazo = 0 THEN 1 ELSE 0 END) as taxa_atraso,
                    COUNT(mult.id) as total_multas,
                    COUNT(a.id) as total_abastecimentos,
                    AVG(a.valor_total / NULLIF(r.distancia_km, 0)) as custo_medio_km
                FROM motoristas m
                LEFT JOIN rotas r ON m.id = r.motorista_id AND r.empresa_id = :empresa_id
                LEFT JOIN multas mult ON m.id = mult.motorista_id AND mult.empresa_id = :empresa_id
                LEFT JOIN abastecimentos a ON r.id = a.rota_id
                WHERE m.empresa_id = :empresa_id
                GROUP BY m.id, m.nome
                HAVING total_rotas > 0
                ORDER BY (taxa_atraso * 100 + total_multas * 10 + custo_medio_km * 5) DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        
        $motoristas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $ranking = [];
        foreach ($motoristas as $index => $motorista) {
            $score_risco = calcularScoreRisco($motorista);
            
            $ranking[] = [
                'motorista_id' => $motorista['id'],
                'motorista_nome' => $motorista['nome'],
                'posicao' => $index + 1,
                'score_risco' => $score_risco,
                'categoria_risco' => $score_risco > 70 ? 'Alto Risco' : ($score_risco > 40 ? 'Médio Risco' : 'Baixo Risco'),
                'total_rotas' => $motorista['total_rotas'],
                'taxa_atraso' => round($motorista['taxa_atraso'] * 100, 1),
                'total_multas' => $motorista['total_multas'],
                'custo_medio_km' => round($motorista['custo_medio_km'], 2)
            ];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $ranking
        ]);
        
    } catch (Exception $e) {
        error_log("Erro ao calcular ranking de risco: " . $e->getMessage());
        echo json_encode(['error' => 'Erro ao calcular ranking de risco']);
    }
}

/**
 * Calcular score de risco
 */
function calcularScoreRisco($motorista) {
    $score = 0;
    
    // Taxa de atraso (0-40 pontos)
    $score += min(40, $motorista['taxa_atraso'] * 100 * 0.4);
    
    // Multas (0-30 pontos)
    $score += min(30, $motorista['total_multas'] * 5);
    
    // Custo médio por km (0-30 pontos)
    $custo_km = floatval($motorista['custo_medio_km'] ?? 0);
    if ($custo_km > 3.0) {
        $score += min(30, ($custo_km - 3.0) * 10);
    }
    
    return min(100, $score);
}

/**
 * Obter análise de custos
 */
function getAnaliseCustos($conn, $empresa_id) {
    try {
        $periodo_inicio = isset($_GET['periodo_inicio']) ? $_GET['periodo_inicio'] : date('Y-m-01');
        $periodo_fim = isset($_GET['periodo_fim']) ? $_GET['periodo_fim'] : date('Y-m-d');
        
        // Análise de custos por categoria
        $sql = "SELECT 
                    'Combustível' as categoria,
                    SUM(a.valor_total) as total,
                    COUNT(*) as quantidade
                FROM abastecimentos a
                JOIN rotas r ON a.rota_id = r.id
                WHERE r.empresa_id = :empresa_id
                AND r.data_rota BETWEEN :periodo_inicio AND :periodo_fim
                
                UNION ALL
                
                SELECT 
                    'Comissões' as categoria,
                    SUM(r.comissao) as total,
                    COUNT(*) as quantidade
                FROM rotas r
                WHERE r.empresa_id = :empresa_id
                AND r.data_rota BETWEEN :periodo_inicio AND :periodo_fim
                
                UNION ALL
                
                SELECT 
                    'Manutenções' as categoria,
                    SUM(m.valor_total) as total,
                    COUNT(*) as quantidade
                FROM manutencoes m
                WHERE m.empresa_id = :empresa_id
                AND m.data_manutencao BETWEEN :periodo_inicio AND :periodo_fim";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->bindParam(':periodo_inicio', $periodo_inicio);
        $stmt->bindParam(':periodo_fim', $periodo_fim);
        $stmt->execute();
        
        $custos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular totais
        $total_custos = array_sum(array_column($custos, 'total'));
        
        echo json_encode([
            'success' => true,
            'data' => [
                'custos' => $custos,
                'total_custos' => $total_custos,
                'periodo' => [
                    'inicio' => $periodo_inicio,
                    'fim' => $periodo_fim
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Erro ao analisar custos: " . $e->getMessage());
        echo json_encode(['error' => 'Erro ao analisar custos']);
    }
}

/**
 * Obter dashboard da IA
 */
function getDashboardIA($conn, $empresa_id) {
    try {
        // Estatísticas gerais
        $sql_stats = "SELECT 
                        (SELECT COUNT(*) FROM rotas WHERE empresa_id = :empresa_id AND status = 'aprovado') as total_rotas,
                        (SELECT COUNT(*) FROM abastecimentos a JOIN rotas r ON a.rota_id = r.id WHERE r.empresa_id = :empresa_id) as total_abastecimentos,
                        (SELECT COUNT(*) FROM motoristas WHERE empresa_id = :empresa_id) as total_motoristas,
                        (SELECT COUNT(*) FROM notificacoes WHERE empresa_id = :empresa_id AND data_criacao >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as alertas_recentes";
        
        $stmt_stats = $conn->prepare($sql_stats);
        $stmt_stats->bindParam(':empresa_id', $empresa_id);
        $stmt_stats->execute();
        
        $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
        
        // Alertas recentes
        $sql_alertas = "SELECT 
                          tipo,
                          titulo,
                          mensagem,
                          data_criacao
                        FROM notificacoes 
                        WHERE empresa_id = :empresa_id 
                        AND data_criacao >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        ORDER BY data_criacao DESC
                        LIMIT 10";
        
        $stmt_alertas = $conn->prepare($sql_alertas);
        $stmt_alertas->bindParam(':empresa_id', $empresa_id);
        $stmt_alertas->execute();
        
        $alertas = $stmt_alertas->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'estatisticas' => $stats,
                'alertas_recentes' => $alertas
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Erro ao obter dashboard da IA: " . $e->getMessage());
        echo json_encode(['error' => 'Erro ao obter dashboard da IA']);
    }
}

/**
 * Executar análise completa
 */
function executarAnaliseCompleta($conn, $empresa_id) {
    try {
        // Executar o script de IA avançada
        $output = [];
        $return_code = 0;
        
        $command = "php " . __DIR__ . "/../IA/ia_avancada_custos_fraudes.php empresa_id={$empresa_id}";
        exec($command, $output, $return_code);
        
        echo json_encode([
            'success' => true,
            'message' => 'Análise completa executada com sucesso',
            'output' => $output,
            'return_code' => $return_code
        ]);
        
    } catch (Exception $e) {
        error_log("Erro ao executar análise completa: " . $e->getMessage());
        echo json_encode(['error' => 'Erro ao executar análise completa']);
    }
}
?>
