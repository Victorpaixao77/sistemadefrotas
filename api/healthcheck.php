<?php
declare(strict_types=1);

/**
 * Healthcheck público para monitor externo (UptimeRobot, etc.).
 * Não requer autenticação. Retorna 200 se MySQL responde.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$t0 = microtime(true);
$status = 'ok';
$checks = [];

try {
    require_once __DIR__ . '/includes/db_connect.php';
    $pdo = getConnection();
    $pdo->query('SELECT 1');
    $checks['mysql'] = 'ok';
} catch (Throwable $e) {
    $checks['mysql'] = 'fail';
    $status = 'degraded';
}

$ms = (int) round((microtime(true) - $t0) * 1000);
$checks['latency_ms'] = $ms;

if ($status !== 'ok') {
    http_response_code(503);
}

echo json_encode([
    'status' => $status,
    'service' => 'frotec',
    'checks' => $checks,
    'ts' => date('c'),
], JSON_UNESCAPED_UNICODE);
