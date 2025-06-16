<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Iniciar sessão
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['empresa_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Usuário não autenticado'
    ]);
    exit;
}

// Configurar cabeçalhos
header('Content-Type: application/json');

try {
    $conn = getConnection();
    $empresa_id = $_SESSION['empresa_id'];
    
    // Obter parâmetros de filtro
    $ano = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    $mes = isset($_GET['month']) ? intval($_GET['month']) : date('m');
    
    // Consulta simplificada para calcular o rendimento por veículo
    $sql = "SELECT 
        v.placa as veiculo,
        SUM(a.litros) as total_litros,
        MAX(a.km_atual) as km_maxima,
        MIN(a.km_atual) as km_minima,
        COUNT(DISTINCT a.data_abastecimento) as num_abastecimentos,
        CASE 
            WHEN SUM(a.litros) > 0 THEN (MAX(a.km_atual) - MIN(a.km_atual)) / SUM(a.litros)
            ELSE 0 
        END as rendimento_km_litro
    FROM abastecimentos a
    JOIN veiculos v ON a.veiculo_id = v.id
    WHERE a.empresa_id = :empresa_id
    AND YEAR(a.data_abastecimento) = :ano
    AND MONTH(a.data_abastecimento) = :mes
    GROUP BY v.id, v.placa
    HAVING SUM(a.litros) > 0
    ORDER BY rendimento_km_litro DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->bindParam(':ano', $ano, PDO::PARAM_INT);
    $stmt->bindParam(':mes', $mes, PDO::PARAM_INT);
    $stmt->execute();
    
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Preparar dados para o gráfico
    $labels = [];
    $valores = [];
    
    foreach ($resultados as $resultado) {
        $labels[] = $resultado['veiculo'];
        $valores[] = round($resultado['rendimento_km_litro'], 2);
    }
    
    echo json_encode([
        'success' => true,
        'labels' => $labels,
        'values' => $valores,
        'data' => $resultados
    ]);
    
} catch (PDOException $e) {
    error_log("Erro PDO ao calcular rendimento por veículo: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao calcular rendimento por veículo: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Erro ao calcular rendimento por veículo: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao calcular rendimento por veículo: ' . $e->getMessage()
    ]);
} 