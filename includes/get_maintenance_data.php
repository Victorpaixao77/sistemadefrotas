<?php
// Error handling and output buffering
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

require_once 'config.php';
require_once 'functions.php';
require_once 'db_connect.php';
require_once __DIR__ . '/maintenance_metrics.php';

try {
    configure_session();
    session_start();
    require_authentication();

    $conn = getConnection();

    if (!isset($_SESSION['empresa_id'])) {
        throw new Exception('Empresa ID não encontrado na sessão');
    }
    $empresa_id = (int) $_SESSION['empresa_id'];

    $period = isset($_GET['period']) ? trim((string) $_GET['period']) : '6';
    if (!in_array($period, ['3', '6', '12', 'ano_atual'], true)) {
        $period = '6';
    }

    $mntInterval = sf_mnt_period_sql($period, 'm.data_manutencao');
    $prevCase = 'CASE WHEN ' . sf_mnt_sql_is_preventiva('tm') . ' THEN m.valor ELSE 0 END';
    $corrCase = 'CASE WHEN ' . sf_mnt_sql_is_corretiva('tm') . ' THEN m.valor ELSE 0 END';

    function getMaintenanceCosts($conn, $empresa_id, $period, $mntInterval, $prevCase, $corrCase) {
        $sql = "SELECT 
                    DATE_FORMAT(m.data_manutencao, '%Y-%m') as mes,
                    SUM({$prevCase}) as preventiva,
                    SUM({$corrCase}) as corretiva
                FROM manutencoes m
                LEFT JOIN tipos_manutencao tm ON m.tipo_manutencao_id = tm.id
                WHERE m.empresa_id = :empresa_id
                AND {$mntInterval}
                GROUP BY DATE_FORMAT(m.data_manutencao, '%Y-%m')
                ORDER BY mes ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function getMaintenanceTypes($conn, $empresa_id, $mntInterval) {
        $sql = "SELECT tm.nome as tipo, COUNT(*) as total
                FROM manutencoes m
                JOIN tipos_manutencao tm ON m.tipo_manutencao_id = tm.id
                WHERE m.empresa_id = :empresa_id AND {$mntInterval}
                GROUP BY tm.id, tm.nome";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function getMaintenanceStatus($conn, $empresa_id, $mntInterval) {
        $sql = "SELECT sm.nome as status, COUNT(*) as total
                FROM manutencoes m
                JOIN status_manutencao sm ON m.status_manutencao_id = sm.id
                WHERE m.empresa_id = :empresa_id AND {$mntInterval}
                GROUP BY sm.id, sm.nome";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function getMonthlyEvolution($conn, $empresa_id, $mntInterval) {
        $sql = "SELECT DATE_FORMAT(data_manutencao, '%Y-%m') as mes, COUNT(*) as total
                FROM manutencoes m
                WHERE m.empresa_id = :empresa_id AND {$mntInterval}
                GROUP BY DATE_FORMAT(data_manutencao, '%Y-%m')
                ORDER BY mes ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function getTopVehicles($conn, $empresa_id, $mntInterval) {
        $sql = "SELECT v.placa, COUNT(*) as total_manutencoes, SUM(m.valor) as custo_total
                FROM manutencoes m
                JOIN veiculos v ON m.veiculo_id = v.id
                WHERE m.empresa_id = :empresa_id AND {$mntInterval}
                GROUP BY v.id, v.placa
                ORDER BY custo_total DESC
                LIMIT 5";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function getComponentFailures($conn, $empresa_id, $mntInterval) {
        $sql = "SELECT cm.nome as componente, COUNT(*) as total_falhas
                FROM manutencoes m
                JOIN componentes_manutencao cm ON m.componente_id = cm.id
                WHERE m.empresa_id = :empresa_id AND {$mntInterval}
                GROUP BY cm.id, cm.nome
                ORDER BY total_falhas DESC
                LIMIT 10";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $maintenanceCosts = getMaintenanceCosts($conn, $empresa_id, $period, $mntInterval, $prevCase, $corrCase);
    $maintenanceTypes = getMaintenanceTypes($conn, $empresa_id, $mntInterval);
    $maintenanceStatus = getMaintenanceStatus($conn, $empresa_id, $mntInterval);
    $monthlyEvolution = getMonthlyEvolution($conn, $empresa_id, $mntInterval);
    $topVehicles = getTopVehicles($conn, $empresa_id, $mntInterval);
    $componentFailures = getComponentFailures($conn, $empresa_id, $mntInterval);
    $mtbfMttr = sf_mnt_compute_mtbf_mttr($conn, $empresa_id, $period);
    $custoKmData = sf_mnt_compute_custo_km($conn, $empresa_id, $period);

    $costChartData = ['labels' => [], 'preventiva' => [], 'corretiva' => []];
    foreach ($maintenanceCosts as $cost) {
        $date = new DateTime($cost['mes'] . '-01');
        $costChartData['labels'][] = $date->format('M/Y');
        $costChartData['preventiva'][] = (float) $cost['preventiva'];
        $costChartData['corretiva'][] = (float) $cost['corretiva'];
    }

    $typeChartData = [
        'labels' => array_column($maintenanceTypes, 'tipo'),
        'data' => array_column($maintenanceTypes, 'total'),
    ];

    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'period' => $period,
        'period_label' => sf_mnt_period_label($period),
        'costs' => $costChartData,
        'types' => $typeChartData,
        'status' => $maintenanceStatus,
        'evolution' => $monthlyEvolution,
        'top_vehicles' => $topVehicles,
        'components' => $componentFailures,
        'mtbf' => $mtbfMttr['mtbf'],
        'mttr' => $mtbfMttr['mttr'],
        'cost_per_km' => $custoKmData['custo_km'],
    ]);
} catch (Exception $e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    error_log('Error in get_maintenance_data.php: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao carregar dados de manutenção.',
    ]);
}
