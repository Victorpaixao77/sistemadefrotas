<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Configura a sessão
configure_session();

// Inicializa a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define o tipo de conteúdo como JSON
header('Content-Type: application/json');

// Garante que a requisição está autenticada
require_authentication();

// Obtém o ID do motorista da requisição
$motoristId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$motoristId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID do motorista é obrigatório']);
    exit;
}

try {
    $conn = getConnection();
    if (!$conn) {
        throw new Exception('Falha ao conectar ao banco de dados');
    }
    
    $empresa_id = intval($_SESSION['empresa_id']);
    if (!$empresa_id) {
        error_log("Invalid empresa_id in session: " . $_SESSION['empresa_id']);
        throw new Exception('ID da empresa inválido');
    }
    
    // Verificar se o motorista existe
    $check_sql = "SELECT id FROM motoristas WHERE id = :motorista_id AND empresa_id = :empresa_id";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindValue(':motorista_id', $motoristId, PDO::PARAM_INT);
    $check_stmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        error_log("Motorist not found. ID: $motoristId, Empresa: $empresa_id");
        throw new Exception('Motorista não encontrado');
    }
    
    // Buscar métricas mensais dos últimos 6 meses
    $sql = "SELECT 
            DATE_FORMAT(r.data_rota, '%Y-%m') as month,
            COUNT(r.id) as trips,
            SUM(CASE WHEN r.no_prazo = 1 THEN 1 ELSE 0 END) as rotas_no_prazo,
            COALESCE(SUM(r.frete), 0) as total_faturamento,
            COALESCE(SUM(r.comissao), 0) as total_comissao
        FROM rotas r
        WHERE r.motorista_id = :motorista_id 
        AND r.empresa_id = :empresa_id
        AND r.data_rota >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(r.data_rota, '%Y-%m')
        ORDER BY month DESC";
        
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':motorista_id', $motoristId, PDO::PARAM_INT);
    $stmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Processar dados mensais para incluir avaliação
    $monthly_metrics = array_map(function($item) {
        $total_trips = intval($item['trips']);
        $pontualidade = ($total_trips > 0) ? ($item['rotas_no_prazo'] / $total_trips) * 100 : 0;
        $rentabilidade = ($item['total_faturamento'] > 0) ? 
            (($item['total_faturamento'] - $item['total_comissao']) / $item['total_faturamento']) * 100 : 0;
        
        $rating = ($pontualidade * 0.6 + $rentabilidade * 0.4) / 10;
        
        return [
            'month' => $item['month'],
            'trips' => $total_trips,
            'rating' => round($rating, 1)
        ];
    }, $monthly_data);
    
    // Buscar métricas gerais
    $sql_metrics = "SELECT 
            COUNT(r.id) as total_trips,
            SUM(CASE WHEN r.no_prazo = 1 THEN 1 ELSE 0 END) as rotas_no_prazo,
            COALESCE(SUM(CASE WHEN r.km_chegada > r.km_saida THEN (r.km_chegada - r.km_saida) ELSE 0 END), 0) as total_distance,
            COALESCE(SUM(r.frete), 0) as total_faturamento,
            COALESCE(SUM(r.comissao), 0) as total_comissao
        FROM rotas r
        WHERE r.motorista_id = :motorista_id 
        AND r.empresa_id = :empresa_id";
        
    $stmt_metrics = $conn->prepare($sql_metrics);
    $stmt_metrics->bindValue(':motorista_id', $motoristId, PDO::PARAM_INT);
    $stmt_metrics->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt_metrics->execute();
    
    $metrics = $stmt_metrics->fetch(PDO::FETCH_ASSOC);
    
    // Calcular avaliação média
    $total_trips = intval($metrics['total_trips']);
    $pontualidade = ($total_trips > 0) ? ($metrics['rotas_no_prazo'] / $total_trips) * 100 : 0;
    $rentabilidade = ($metrics['total_faturamento'] > 0) ? 
        (($metrics['total_faturamento'] - $metrics['total_comissao']) / $metrics['total_faturamento']) * 100 : 0;
    
    $average_rating = ($pontualidade * 0.6 + $rentabilidade * 0.4) / 10;
    
    // Buscar dados de consumo médio
    $sql_consumption = "SELECT 
            COALESCE(AVG(CASE 
                WHEN a.litros > 0 AND (r.km_chegada - r.km_saida) > 0 
                THEN (a.litros * 100) / (r.km_chegada - r.km_saida)
                ELSE NULL 
            END), 0) as average_consumption
        FROM abastecimentos a
        JOIN rotas r ON a.rota_id = r.id
        WHERE a.motorista_id = :motorista_id 
        AND a.empresa_id = :empresa_id";
        
    $stmt_consumption = $conn->prepare($sql_consumption);
    $stmt_consumption->bindValue(':motorista_id', $motoristId, PDO::PARAM_INT);
    $stmt_consumption->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt_consumption->execute();
    
    $consumption = $stmt_consumption->fetch(PDO::FETCH_ASSOC);
    
    // Preparar resposta
    $response = [
        'success' => true,
        'data' => [
            'average_rating' => round($average_rating, 1),
            'total_trips' => $total_trips,
            'total_distance' => floatval($metrics['total_distance']),
            'average_consumption' => round(floatval($consumption['average_consumption']), 1),
            'monthly_metrics' => $monthly_metrics
        ]
    ];
    
    // Log da resposta para debug
    error_log("Performance data for motorist $motoristId: " . json_encode($response));
    
    echo json_encode($response);
    
} catch(PDOException $e) {
    error_log("Database error in motorist_performance_chart: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar dados de desempenho: ' . $e->getMessage()
    ]);
} catch(Exception $e) {
    error_log("General error in motorist_performance_chart: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar dados de desempenho: ' . $e->getMessage()
    ]);
} 