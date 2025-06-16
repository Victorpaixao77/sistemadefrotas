<?php
// Error handling and output buffering
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

require_once 'config.php';
require_once 'functions.php';
require_once 'db_connect.php';

try {
    // Configure session before starting it
    configure_session();

    // Initialize the session
    session_start();

    // Require authentication
    require_authentication();

    // Create database connection
    $conn = getConnection();

    // Get empresa_id from session
    if (!isset($_SESSION['empresa_id'])) {
        throw new Exception('Empresa ID não encontrado na sessão');
    }
    $empresa_id = $_SESSION['empresa_id'];

    // Get maintenance costs for the last 6 months
    function getMaintenanceCosts($conn, $empresa_id) {
        $sql = "SELECT 
                    DATE_FORMAT(data_manutencao, '%Y-%m') as mes,
                    SUM(CASE WHEN tm.nome = 'Preventiva' THEN m.valor ELSE 0 END) as preventiva,
                    SUM(CASE WHEN tm.nome = 'Corretiva' THEN m.valor ELSE 0 END) as corretiva
                FROM manutencoes m
                JOIN tipos_manutencao tm ON m.tipo_manutencao_id = tm.id
                WHERE m.empresa_id = :empresa_id
                AND data_manutencao >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(data_manutencao, '%Y-%m')
                ORDER BY mes ASC";
                
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get maintenance types distribution
    function getMaintenanceTypes($conn, $empresa_id) {
        $sql = "SELECT 
                    tm.nome as tipo,
                    COUNT(*) as total
                FROM manutencoes m
                JOIN tipos_manutencao tm ON m.tipo_manutencao_id = tm.id
                WHERE m.empresa_id = :empresa_id
                GROUP BY tm.nome";
                
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get maintenance status distribution
    function getMaintenanceStatus($conn, $empresa_id) {
        $sql = "SELECT 
                    sm.nome as status,
                    COUNT(*) as total
                FROM manutencoes m
                JOIN status_manutencao sm ON m.status_manutencao_id = sm.id
                WHERE m.empresa_id = :empresa_id
                GROUP BY sm.nome";
                
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get monthly evolution
    function getMonthlyEvolution($conn, $empresa_id) {
        $sql = "SELECT 
                    DATE_FORMAT(data_manutencao, '%Y-%m') as mes,
                    COUNT(*) as total
                FROM manutencoes
                WHERE empresa_id = :empresa_id
                AND data_manutencao >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(data_manutencao, '%Y-%m')
                ORDER BY mes ASC";
                
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get top 5 vehicles by maintenance cost
    function getTopVehicles($conn, $empresa_id) {
        $sql = "SELECT 
                    v.placa,
                    COUNT(*) as total_manutencoes,
                    SUM(m.valor) as custo_total
                FROM manutencoes m
                JOIN veiculos v ON m.veiculo_id = v.id
                WHERE m.empresa_id = :empresa_id
                GROUP BY v.id, v.placa
                ORDER BY custo_total DESC
                LIMIT 5";
                
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get components with most failures
    function getComponentFailures($conn, $empresa_id) {
        $sql = "SELECT 
                    cm.nome as componente,
                    COUNT(*) as total_falhas
                FROM manutencoes m
                JOIN componentes_manutencao cm ON m.componente_id = cm.id
                WHERE m.empresa_id = :empresa_id
                GROUP BY cm.id, cm.nome
                ORDER BY total_falhas DESC
                LIMIT 10";
                
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get MTBF and MTTR
    function getMTBFMTTR($conn, $empresa_id) {
        $sql = "SELECT 
                    COALESCE(SUM(v.km_atual), 0) as total_km,
                    COUNT(DISTINCT m.id) as total_falhas,
                    SUM(
                        CASE 
                            WHEN m.data_conclusao IS NOT NULL 
                            THEN TIMESTAMPDIFF(HOUR, m.data_manutencao, m.data_conclusao)
                            WHEN sm.nome = 'Concluída' 
                            THEN TIMESTAMPDIFF(HOUR, m.data_manutencao, m.data_manutencao)
                            ELSE TIMESTAMPDIFF(HOUR, m.data_manutencao, CURRENT_TIMESTAMP)
                        END
                    ) as total_horas_manutencao
                FROM manutencoes m
                LEFT JOIN veiculos v ON m.veiculo_id = v.id
                LEFT JOIN status_manutencao sm ON m.status_manutencao_id = sm.id
                WHERE m.empresa_id = :empresa_id 
                AND m.data_manutencao >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)";
                
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'mtbf' => $result['total_falhas'] > 0 ? ($result['total_km'] / $result['total_falhas']) : 0,
            'mttr' => $result['total_falhas'] > 0 ? ($result['total_horas_manutencao'] / $result['total_falhas']) : 0
        ];
    }

    // Get cost per kilometer
    function getCostPerKm($conn, $empresa_id) {
        $sql = "SELECT 
                    SUM(m.valor) as custo_total,
                    COALESCE(SUM(v.km_atual), 0) as km_total
                FROM manutencoes m
                LEFT JOIN veiculos v ON m.veiculo_id = v.id
                WHERE m.empresa_id = :empresa_id";
                
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['km_total'] > 0 ? ($result['custo_total'] / $result['km_total']) : 0;
    }

    // Get all data
    $maintenanceCosts = getMaintenanceCosts($conn, $empresa_id);
    $maintenanceTypes = getMaintenanceTypes($conn, $empresa_id);
    $maintenanceStatus = getMaintenanceStatus($conn, $empresa_id);
    $monthlyEvolution = getMonthlyEvolution($conn, $empresa_id);
    $topVehicles = getTopVehicles($conn, $empresa_id);
    $componentFailures = getComponentFailures($conn, $empresa_id);
    $mtbfMttr = getMTBFMTTR($conn, $empresa_id);
    $costPerKm = getCostPerKm($conn, $empresa_id);

    // Format data for charts
    $costChartData = [
        'labels' => [],
        'preventiva' => [],
        'corretiva' => []
    ];

    foreach ($maintenanceCosts as $cost) {
        $date = new DateTime($cost['mes'] . '-01');
        $costChartData['labels'][] = $date->format('M/Y');
        $costChartData['preventiva'][] = floatval($cost['preventiva']);
        $costChartData['corretiva'][] = floatval($cost['corretiva']);
    }

    $typeChartData = [
        'labels' => array_column($maintenanceTypes, 'tipo'),
        'data' => array_column($maintenanceTypes, 'total')
    ];

    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'costs' => $costChartData,
        'types' => $typeChartData,
        'status' => $maintenanceStatus,
        'evolution' => $monthlyEvolution,
        'top_vehicles' => $topVehicles,
        'components' => $componentFailures,
        'mtbf' => $mtbfMttr['mtbf'],
        'mttr' => $mtbfMttr['mttr'],
        'cost_per_km' => $costPerKm
    ]);

} catch (Exception $e) {
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Log the error
    error_log('Error in get_maintenance_data.php: ' . $e->getMessage());

    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao carregar dados: ' . $e->getMessage()
    ]);
} 