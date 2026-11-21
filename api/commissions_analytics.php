<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';
require_once '../includes/dashboard_analytics_period.php';

configure_session();
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

if (!can_view_financial_data()) {
    http_response_code(403);
    echo json_encode(['error' => 'Sem permissão financeira']);
    exit;
}

$empresa_id = (int) $_SESSION['empresa_id'];
if (function_exists('session_write_close')) { session_write_close(); }

try {
    $conn = getConnection();
    $ctx = sf_dashboard_analytics_context();

    // Dashboard cacheado (APCu/arquivo) por 120s, por empresa+período.
    require_once __DIR__ . '/../includes/sf_cache.php';
    $ckey = 'dash:comissoes:' . $empresa_id . ':' . $ctx['d1'] . ':' . $ctx['d2'] . ':' . $ctx['bucket'];
    if (($cached = sf_cache_get($ckey)) !== null) { echo json_encode($cached); exit; }

    $series = sf_dashboard_analytics_series($ctx['d1'], $ctx['d2'], $ctx['bucket']);
    $values = array_fill_keys($series['keys'], 0.0);

    $st = $conn->prepare("
        SELECT DATE(COALESCE(data_saida, data_rota)) AS d, SUM(comissao) AS total
        FROM rotas
        WHERE empresa_id = :e AND status = 'aprovado'
        AND DATE(COALESCE(data_saida, data_rota)) BETWEEN :d1 AND :d2
        GROUP BY DATE(COALESCE(data_saida, data_rota))
    ");
    $st->execute([':e' => $empresa_id, ':d1' => $ctx['d1'], ':d2' => $ctx['d2']]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $k = sf_dashboard_analytics_bucket_key((string) $row['d'], $ctx['bucket']);
        if ($k !== '' && isset($values[$k])) {
            $values[$k] += (float) $row['total'];
        }
    }

    $payload = [
        'labels' => $series['labels'],
        'datasets' => [[
            'label' => 'Comissões',
            'data' => array_values($values),
            'borderColor' => '#FF6384',
            'backgroundColor' => 'rgba(255, 99, 132, 0.1)',
            'fill' => true,
            'tension' => 0.4,
        ]],
        'period_label' => $ctx['label'],
    ];
    sf_cache_set($ckey, $payload, 120);
    echo json_encode($payload);
} catch (Throwable $e) {
    error_log('Commissions Analytics API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar comissões']);
}
