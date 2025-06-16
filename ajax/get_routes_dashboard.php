<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// Get month and year from request
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$empresa_id = $_SESSION['empresa_id'];

try {
    $conn = getConnection();
    
    // Preparar o primeiro dia do mês e o último dia do mês
    $data_inicio = "$year-$month-01";
    $data_fim = date('Y-m-t', strtotime($data_inicio));
    
    // Total de rotas no período
    $sql = "SELECT 
                COUNT(*) as total_rotas,
                SUM(CASE WHEN status = 'Concluída' THEN 1 ELSE 0 END) as rotas_concluidas,
                SUM(distancia_km) as distancia_total,
                SUM(frete) as frete_total,
                SUM(CASE WHEN no_prazo = 1 THEN 1 ELSE 0 END) as rotas_no_prazo,
                SUM(CASE WHEN no_prazo = 0 THEN 1 ELSE 0 END) as rotas_atrasadas,
                AVG(eficiencia_viagem) as media_eficiencia,
                AVG(percentual_vazio) as percentual_vazio
            FROM rotas 
            WHERE empresa_id = :empresa_id 
            AND data_rota BETWEEN :data_inicio AND :data_fim
            AND status = 'aprovado'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->bindParam(':data_inicio', $data_inicio);
    $stmt->bindParam(':data_fim', $data_fim);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Dados para o gráfico de status
    $sql_status = "SELECT 
                    SUM(CASE WHEN status = 'Concluída' THEN 1 ELSE 0 END) as concluidas,
                    SUM(CASE WHEN status = 'Em andamento' THEN 1 ELSE 0 END) as em_andamento,
                    SUM(CASE WHEN status = 'Programada' THEN 1 ELSE 0 END) as programadas,
                    SUM(CASE WHEN status = 'Cancelada' THEN 1 ELSE 0 END) as canceladas
                FROM rotas 
                WHERE empresa_id = :empresa_id 
                AND data_rota BETWEEN :data_inicio AND :data_fim
                AND status = 'aprovado'";
    
    $stmt = $conn->prepare($sql_status);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->bindParam(':data_inicio', $data_inicio);
    $stmt->bindParam(':data_fim', $data_fim);
    $stmt->execute();
    $status_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Dados para o gráfico de tendências mensais (últimos 6 meses)
    $sql_trends = "SELECT 
                    DATE_FORMAT(data_rota, '%Y-%m') as mes,
                    COUNT(*) as total_rotas,
                    SUM(frete) as total_frete
                FROM rotas 
                WHERE empresa_id = :empresa_id 
                AND data_rota >= DATE_SUB(:data_inicio, INTERVAL 5 MONTH)
                AND data_rota <= :data_fim
                AND status = 'aprovado'
                GROUP BY DATE_FORMAT(data_rota, '%Y-%m')
                ORDER BY mes ASC";
    
    $stmt = $conn->prepare($sql_trends);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->bindParam(':data_inicio', $data_inicio);
    $stmt->bindParam(':data_fim', $data_fim);
    $stmt->execute();
    $trends_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Preparar dados para o gráfico de tendências
    $labels = [];
    $rotas_data = [];
    $frete_data = [];
    
    foreach ($trends_data as $trend) {
        $month_year = explode('-', $trend['mes']);
        $month_name = date('M', mktime(0, 0, 0, $month_year[1], 1));
        $labels[] = $month_name . '/' . substr($month_year[0], 2);
        $rotas_data[] = intval($trend['total_rotas']);
        $frete_data[] = floatval($trend['total_frete']);
    }
    
    // Preparar resposta
    $response = [
        'total_rotas' => $result['total_rotas'],
        'rotas_concluidas' => $result['rotas_concluidas'],
        'distancia_total' => $result['distancia_total'],
        'frete_total' => $result['frete_total'],
        'rotas_no_prazo' => $result['rotas_no_prazo'],
        'rotas_atrasadas' => $result['rotas_atrasadas'],
        'media_eficiencia' => $result['media_eficiencia'],
        'percentual_vazio' => $result['percentual_vazio'],
        'status_chart_data' => $status_data,
        'monthly_trends_data' => [
            'labels' => $labels,
            'rotas' => $rotas_data,
            'frete' => $frete_data
        ]
    ];
    
    // Enviar resposta
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar dados: ' . $e->getMessage()]);
}
?> 