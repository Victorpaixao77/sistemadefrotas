<?php
// Verificar se está sendo executado via web
if (php_sapi_name() === 'cli') {
    echo "Esta API deve ser executada via web server\n";
    exit();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/db_connect.php';
    require_once __DIR__ . '/../../includes/functions.php';

    // Configure session
    configure_session();
    session_start();

    // Verificar autenticação
    if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autorizado']);
        exit();
    }

    $empresa_id = $_SESSION['empresa_id'];
    $conn = getConnection();
    
    // Métricas em tempo real
    $metrics = [];
    
    // 1. Veículos Ativos
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM veiculos WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $metrics['veiculos_ativos'] = intval($stmt->fetch()['total']);
    
    // 2. Rotas Hoje
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM rotas r 
        JOIN veiculos v ON r.veiculo_id = v.id 
        WHERE v.empresa_id = ? 
        AND DATE(r.data_saida) = CURDATE()
    ");
    $stmt->execute([$empresa_id]);
    $metrics['rotas_hoje'] = intval($stmt->fetch()['total']);
    
    // 3. Litros Hoje (abastecimentos)
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(a.litros), 0) as total 
        FROM abastecimentos a 
        JOIN rotas r ON a.rota_id = r.id 
        JOIN veiculos v ON r.veiculo_id = v.id 
        WHERE v.empresa_id = ? 
        AND DATE(a.data_abastecimento) = CURDATE()
    ");
    $stmt->execute([$empresa_id]);
    $metrics['consumo_hoje'] = floatval($stmt->fetch()['total']);
    
    // 4. Manutenções Pendentes
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM alertas_sistema 
        WHERE empresa_id = ? 
        AND tipo = 'manutencao' 
        AND status = 'ativo'
    ");
    $stmt->execute([$empresa_id]);
    $metrics['manutencoes_pendentes'] = intval($stmt->fetch()['total']);
    
    // 5. Alertas Ativos
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM alertas_sistema 
        WHERE empresa_id = ? 
        AND status = 'ativo'
    ");
    $stmt->execute([$empresa_id]);
    $metrics['alertas_ativos'] = intval($stmt->fetch()['total']);
    
    // 6. Eficiência Média (baseada em dados reais)
    $stmt = $conn->prepare("
        SELECT 
            AVG(
                CASE 
                    WHEN r.distancia_km > 0 AND a.litros > 0 
                    THEN (r.distancia_km / a.litros) 
                    ELSE NULL 
                END
            ) as eficiencia_media
        FROM rotas r
        JOIN veiculos v ON r.veiculo_id = v.id
        LEFT JOIN abastecimentos a ON r.id = a.rota_id
        WHERE v.empresa_id = ?
        AND r.data_saida >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND a.litros IS NOT NULL
        AND a.litros > 0
    ");
    $stmt->execute([$empresa_id]);
    $eficiencia = $stmt->fetch()['eficiencia_media'];
    $metrics['eficiencia_media'] = $eficiencia ? round(floatval($eficiencia), 1) : 0;
    
    // 7. Métricas adicionais
    $metrics['total_rotas_mes'] = 0;
    $metrics['total_combustivel_mes'] = 0;
    $metrics['custo_total_mes'] = 0;
    
    // Rotas do mês
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM rotas r 
        JOIN veiculos v ON r.veiculo_id = v.id 
        WHERE v.empresa_id = ? 
        AND MONTH(r.data_saida) = MONTH(CURDATE())
        AND YEAR(r.data_saida) = YEAR(CURDATE())
    ");
    $stmt->execute([$empresa_id]);
    $metrics['total_rotas_mes'] = intval($stmt->fetch()['total']);
    
    // Combustível do mês
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(a.litros), 0) as total 
        FROM abastecimentos a 
        JOIN rotas r ON a.rota_id = r.id 
        JOIN veiculos v ON r.veiculo_id = v.id 
        WHERE v.empresa_id = ? 
        AND MONTH(a.data_abastecimento) = MONTH(CURDATE())
        AND YEAR(a.data_abastecimento) = YEAR(CURDATE())
    ");
    $stmt->execute([$empresa_id]);
    $metrics['total_combustivel_mes'] = floatval($stmt->fetch()['total']);
    
    // Custo total do mês
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(a.valor_total), 0) as total 
        FROM abastecimentos a 
        JOIN rotas r ON a.rota_id = r.id 
        JOIN veiculos v ON r.veiculo_id = v.id 
        WHERE v.empresa_id = ? 
        AND MONTH(a.data_abastecimento) = MONTH(CURDATE())
        AND YEAR(a.data_abastecimento) = YEAR(CURDATE())
    ");
    $stmt->execute([$empresa_id]);
    $metrics['custo_total_mes'] = floatval($stmt->fetch()['total']);
    
    echo json_encode([
        'success' => true,
        'metrics' => $metrics,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Erro na API de métricas: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno do servidor',
        'message' => $e->getMessage()
    ]);
}
?>
