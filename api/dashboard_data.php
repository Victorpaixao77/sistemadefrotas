<?php
/**
 * Dashboard data API — dados reais (legado para expand card / manutenções).
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';
require_once '../includes/dashboard_home_metrics.php';

configure_session();
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    $conn = getConnection();
    $empresa_id = (int) $_SESSION['empresa_id'];
    $canFin = can_view_financial_data();
    $detail = isset($_GET['detail']) ? (string) $_GET['detail'] : null;

    if ($detail !== null && $detail !== '') {
        echo json_encode(getDashboardDetailData($conn, $empresa_id, $detail, $canFin));
        exit;
    }

    $period = dashboard_home_normalize_period($_GET['period'] ?? 'year');
    $dash = dashboard_home_load_all($conn, $empresa_id, $period, $canFin);

    $maintenanceData = [];
    $st = $conn->prepare("
        SELECT m.data_manutencao, v.placa, COALESCE(tm.nome, 'Manutenção') AS tipo, m.valor
        FROM manutencoes m
        INNER JOIN veiculos v ON v.id = m.veiculo_id AND v.empresa_id = m.empresa_id
        LEFT JOIN tipos_manutencao tm ON tm.id = m.tipo_manutencao_id
        WHERE m.empresa_id = :e
        ORDER BY m.data_manutencao DESC
        LIMIT 10
    ");
    $st->execute([':e' => $empresa_id]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $maintenanceData[] = [
            'date' => $row['data_manutencao'],
            'vehicle' => $row['placa'],
            'type' => $row['tipo'] ?? 'manutenção',
            'value' => (float) $row['valor'],
        ];
    }

    echo json_encode([
        'vehicleCount' => (int) ($dash['total_veiculos'] ?? 0),
        'motoristCount' => (int) ($dash['total_motoristas'] ?? 0),
        'supplyData' => [
            'count' => (int) ($dash['total_abastecimentos'] ?? 0),
            'total' => (float) ($dash['valor_total_abastecimentos'] ?? 0),
        ],
        'liquidValue' => $canFin ? (float) ($dash['lucro_liquido'] ?? 0) : null,
        'routes' => [
            'completed' => (int) ($dash['total_rotas_concluidas'] ?? 0),
            'total' => (int) ($dash['total_rotas'] ?? 0),
        ],
        'maintenanceData' => $maintenanceData,
        'period' => $period,
        'period_label' => $dash['home_period_label'] ?? '',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    error_log('Dashboard API Error: ' . $e->getMessage());
}

function getDashboardDetailData(PDO $conn, int $empresa_id, string $widget, bool $canFin): array
{
    switch ($widget) {
        case 'kpi-veiculos':
        case 'vehicles':
            $st = $conn->prepare('SELECT placa, modelo, ano, status FROM veiculos WHERE empresa_id = :e ORDER BY placa LIMIT 50');
            $st->execute([':e' => $empresa_id]);
            return ['vehicles' => $st->fetchAll(PDO::FETCH_ASSOC)];
        case 'kpi-motoristas':
        case 'motorists':
            $st = $conn->prepare('SELECT nome, cpf, telefone FROM motoristas WHERE empresa_id = :e ORDER BY nome LIMIT 50');
            $st->execute([':e' => $empresa_id]);
            return ['motorists' => $st->fetchAll(PDO::FETCH_ASSOC)];
        case 'kpi-rotas':
        case 'routes':
            $st = $conn->prepare("SELECT id, status, frete, comissao, DATE(COALESCE(data_saida, data_rota)) AS data_ref FROM rotas WHERE empresa_id = :e ORDER BY COALESCE(data_saida, data_rota) DESC LIMIT 30");
            $st->execute([':e' => $empresa_id]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            if (!$canFin) {
                foreach ($rows as &$r) {
                    unset($r['frete'], $r['comissao']);
                }
                unset($r);
            }
            return ['routes' => $rows];
        default:
            return ['error' => 'Widget não suportado'];
    }
}
