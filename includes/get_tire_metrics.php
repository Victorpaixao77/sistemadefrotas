<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/pneus_helpers.php';
require_once __DIR__ . '/pneus_list.php';
require_once __DIR__ . '/pneus_repository.php';

header('Content-Type: application/json; charset=utf-8');

configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_authentication();
pneus_require_page_access();

try {
    $pdo = getConnection();
    $empresa_id = pneus_empresa_id();
    $metrics = pneus_fetch_page_metrics($pdo, (int) $empresa_id);

    echo json_encode([
        'success' => true,
        'data' => $metrics,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('get_tire_metrics.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar métricas dos pneus'], JSON_UNESCAPED_UNICODE);
}
