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
    $period = isset($_GET['period']) ? intval($_GET['period']) : 30;
    $conn = getConnection();
    
    // Log para debug
    error_log("API Charts - Período solicitado: " . $period . " dias");
    
    $charts = [];
    
    // 1. Gráfico de Consumo de Combustível por Veículo (simplificado)
    $stmt = $conn->prepare("
        SELECT 
            v.placa,
            COALESCE(SUM(a.litros), 0) as total_litros
        FROM veiculos v
        LEFT JOIN rotas r ON v.id = r.veiculo_id
        LEFT JOIN abastecimentos a ON r.id = a.rota_id
        WHERE v.empresa_id = ?
        GROUP BY v.id, v.placa
        ORDER BY total_litros DESC
        LIMIT 5
    ");
    $stmt->execute([$empresa_id]);
    $consumo_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $charts['consumo'] = [
        'labels' => array_column($consumo_data, 'placa'),
        'data' => array_map('floatval', array_column($consumo_data, 'total_litros'))
    ];
    
    // Log para debug
    error_log("API Charts - Consumo para período {$period}: " . json_encode($charts['consumo']));
    
    // 2. Gráfico de Eficiência dos Motoristas (simplificado)
    $eficiencia_labels = ['Eficiente', 'Médio', 'Baixo'];
    $eficiencia_values = [2, 3, 1]; // Dados mockados por enquanto
    
    $charts['eficiencia'] = [
        'labels' => $eficiencia_labels,
        'data' => $eficiencia_values
    ];
    
    // 3. Gráfico de Custos por Categoria (simplificado)
    $stmt = $conn->prepare("
        SELECT 
            'Combustível' as categoria,
            COALESCE(SUM(a.valor_total), 0) as total
        FROM abastecimentos a
        JOIN rotas r ON a.rota_id = r.id
        JOIN veiculos v ON r.veiculo_id = v.id
        WHERE v.empresa_id = ?
    ");
    $stmt->execute([$empresa_id]);
    $combustivel = $stmt->fetch()['total'];
    
    $stmt = $conn->prepare("
        SELECT 
            'Manutenção' as categoria,
            COALESCE(SUM(m.valor), 0) as total
        FROM manutencoes m
        JOIN veiculos v ON m.veiculo_id = v.id
        WHERE v.empresa_id = ?
    ");
    $stmt->execute([$empresa_id]);
    $manutencao = $stmt->fetch()['total'];
    
    $stmt = $conn->prepare("
        SELECT 
            'Multas' as categoria,
            COALESCE(SUM(mt.valor), 0) as total
        FROM multas mt
        JOIN veiculos v ON mt.veiculo_id = v.id
        WHERE v.empresa_id = ?
    ");
    $stmt->execute([$empresa_id]);
    $multas = $stmt->fetch()['total'];
    
    $charts['custos'] = [
        'labels' => ['Combustível', 'Manutenção', 'Multas'],
        'data' => [floatval($combustivel), floatval($manutencao), floatval($multas)]
    ];
    
    // 4. Gráfico de Manutenções por Mês (simplificado)
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(m.data_manutencao, '%Y-%m') as mes,
            COUNT(*) as quantidade
        FROM manutencoes m
        JOIN veiculos v ON m.veiculo_id = v.id
        WHERE v.empresa_id = ?
        AND m.data_manutencao >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(m.data_manutencao, '%Y-%m')
        ORDER BY mes
    ");
    $stmt->execute([$empresa_id]);
    $manutencao_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $charts['manutencao'] = [
        'labels' => array_column($manutencao_data, 'mes'),
        'data' => array_map('intval', array_column($manutencao_data, 'quantidade'))
    ];
    
    // 5. Gráfico de Previsões (simplificado)
    $charts['prediction'] = [
        'labels' => ['Semana 1', 'Semana 2', 'Semana 3', 'Semana 4'],
        'combustivel' => [1000, 1200, 1100, 1300],
        'manutencao' => [500, 600, 550, 650]
    ];
    
    echo json_encode([
        'success' => true,
        'charts' => $charts,
        'period' => $period,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Erro na API de gráficos: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno do servidor',
        'message' => $e->getMessage()
    ]);
}
?>
