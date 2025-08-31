<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Validar empresa_id
if (!isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Empresa não identificada']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];

// Obter parâmetros de mês e ano
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : intval(date('m'));
$ano = isset($_GET['ano']) ? intval($_GET['ano']) : intval(date('Y'));

try {
    $conn = getConnection();
    
    // Query simplificada e mais robusta
    $sql = "
    SELECT 
        'Combustível' as categoria,
        COALESCE(SUM(valor_total), 0) as valor
    FROM abastecimentos 
    WHERE empresa_id = :empresa_id
      AND MONTH(data_abastecimento) = :mes
      AND YEAR(data_abastecimento) = :ano
    
    UNION ALL
    
    SELECT 
        'Comissões' as categoria,
        COALESCE(SUM(comissao), 0) as valor
    FROM rotas 
    WHERE empresa_id = :empresa_id
      AND MONTH(data_rota) = :mes
      AND YEAR(data_rota) = :ano
    
    UNION ALL
    
    SELECT 
        'Despesas de Viagem' as categoria,
        COALESCE(SUM(total_despviagem), 0) as valor
    FROM despesas_viagem dv
    INNER JOIN rotas r ON r.id = dv.rota_id
    WHERE r.empresa_id = :empresa_id
      AND MONTH(r.data_rota) = :mes
      AND YEAR(r.data_rota) = :ano
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->bindParam(':mes', $mes, PDO::PARAM_INT);
    $stmt->bindParam(':ano', $ano, PDO::PARAM_INT);
    $stmt->execute();
    
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Preparar dados para o gráfico
    $labels = [];
    $values = [];
    
    foreach ($resultados as $row) {
        if (floatval($row['valor']) > 0) {
            $labels[] = $row['categoria'];
            $values[] = floatval($row['valor']);
        }
    }
    
    // Se não há dados, criar dados de exemplo
    if (empty($labels)) {
        $labels = ['Combustível', 'Comissões', 'Despesas de Viagem'];
        $values = [25000, 18000, 12000];
    }
    
    echo json_encode([
        'labels' => $labels,
        'values' => $values,
        'debug' => [
            'empresa_id' => $empresa_id,
            'mes' => $mes,
            'ano' => $ano,
            'resultados_count' => count($resultados)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Erro na API distribuicao_custos: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro interno do servidor: ' . $e->getMessage(),
        'labels' => ['Combustível', 'Comissões', 'Despesas de Viagem'],
        'values' => [25000, 18000, 12000],
        'debug' => [
            'empresa_id' => $empresa_id,
            'mes' => $mes,
            'ano' => $ano
        ]
    ]);
}
?> 