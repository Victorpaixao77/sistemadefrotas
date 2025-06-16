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

try {
    $conn = getConnection();
    $empresa_id = $_SESSION['empresa_id'];
    
    // Query para buscar dados de desempenho dos motoristas
    $query = "
        SELECT 
            m.nome as motorista,
            COUNT(r.id) as total_rotas,
            SUM(CASE WHEN r.no_prazo = 1 THEN 1 ELSE 0 END) as rotas_no_prazo,
            AVG(CASE WHEN r.km_chegada > r.km_saida THEN (r.km_chegada - r.km_saida) ELSE 0 END) as media_km,
            COALESCE(SUM(r.frete), 0) as total_faturamento,
            COALESCE(SUM(r.comissao), 0) as total_comissao
        FROM motoristas m
        LEFT JOIN rotas r ON r.motorista_id = m.id
        WHERE m.empresa_id = :empresa_id
        AND (r.data_rota >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY) OR r.data_rota IS NULL)
        GROUP BY m.id, m.nome
        HAVING total_rotas > 0
        ORDER BY total_rotas DESC
        LIMIT 5";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Preparar dados para o gráfico
    $labels = ['Pontualidade', 'Eficiência', 'Economia', 'Produtividade', 'Rentabilidade'];
    $datasets = [];
    
    foreach ($results as $row) {
        // Calcular métricas de desempenho
        $pontualidade = ($row['total_rotas'] > 0) ? ($row['rotas_no_prazo'] / $row['total_rotas']) * 100 : 0;
        $eficiencia = ($row['media_km'] > 0) ? min(100, ($row['media_km'] / 1000) * 10) : 0; // Eficiência baseada na média de km
        $economia = ($row['total_rotas'] > 0) ? min(100, ($row['total_rotas'] / 30) * 100) : 0;
        $produtividade = ($row['total_rotas'] > 0) ? min(100, ($row['total_rotas'] / 20) * 100) : 0;
        $rentabilidade = ($row['total_faturamento'] > 0) ? 
            min(100, (($row['total_faturamento'] - $row['total_comissao']) / $row['total_faturamento']) * 100) : 0;
        
        // Gerar cor aleatória para o motorista
        $r = rand(0, 255);
        $g = rand(0, 255);
        $b = rand(0, 255);
        
        $datasets[] = [
            'label' => $row['motorista'],
            'data' => [
                round($pontualidade, 1),
                round($eficiencia, 1),
                round($economia, 1),
                round($produtividade, 1),
                round($rentabilidade, 1)
            ],
            'backgroundColor' => "rgba($r, $g, $b, 0.2)",
            'borderColor' => "rgba($r, $g, $b, 1)"
        ];
    }
    
    // Retornar os dados no formato esperado pelo gráfico
    echo json_encode([
        'labels' => $labels,
        'datasets' => $datasets
    ]);
    
} catch (Exception $e) {
    error_log("Motorist Performance Analytics API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar dados de desempenho dos motoristas: ' . $e->getMessage()]);
    exit;
} 