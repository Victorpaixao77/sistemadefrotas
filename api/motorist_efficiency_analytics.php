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
    
    // Query para buscar dados de eficiência dos motoristas
    $query = "
        SELECT 
            m.nome as motorista,
            COALESCE(SUM(r.frete), 0) as faturamento,
            COALESCE(SUM(r.comissao), 0) as comissao,
            COALESCE(SUM(
                COALESCE(dv.arla, 0) + 
                COALESCE(dv.pedagios, 0) + 
                COALESCE(dv.caixinha, 0) + 
                COALESCE(dv.estacionamento, 0) + 
                COALESCE(dv.lavagem, 0) + 
                COALESCE(dv.borracharia, 0) + 
                COALESCE(dv.eletrica_mecanica, 0) + 
                COALESCE(dv.adiantamento, 0)
            ), 0) as despesas_viagem,
            COALESCE(SUM(a.valor_total), 0) as abastecimentos
        FROM motoristas m
        LEFT JOIN rotas r ON r.motorista_id = m.id
        LEFT JOIN despesas_viagem dv ON dv.rota_id = r.id
        LEFT JOIN abastecimentos a ON a.motorista_id = m.id
        WHERE m.empresa_id = :empresa_id
        AND (r.data_rota >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY) OR r.data_rota IS NULL)
        GROUP BY m.id, m.nome
        ORDER BY faturamento DESC
        LIMIT 10";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Preparar dados para o gráfico
    $labels = [];
    $faturamento = [];
    $despesas = [];
    $lucro = [];
    
    foreach ($results as $row) {
        $labels[] = $row['motorista'];
        $faturamento[] = floatval($row['faturamento']);
        $total_despesas = floatval($row['comissao']) + floatval($row['despesas_viagem']) + floatval($row['abastecimentos']);
        $despesas[] = $total_despesas;
        $lucro[] = floatval($row['faturamento']) - $total_despesas;
    }
    
    // Retornar os dados no formato esperado pelo gráfico
    echo json_encode([
        'labels' => $labels,
        'datasets' => [
            'faturamento' => $faturamento,
            'despesas' => $despesas,
            'lucro' => $lucro
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Motorist Efficiency Analytics API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar dados de eficiência dos motoristas: ' . $e->getMessage()]);
    exit;
} 