<?php
/**
 * Agrega gps_logs do dia anterior em gps_logs_resumido e apaga o bruto desse dia.
 * Cron (diário): php cron/gps_agregar_diario.php
 * Opcional: php cron/gps_agregar_diario.php 2026-04-04
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = dirname(__DIR__);
require_once $root . '/includes/config.php';
require_once $root . '/includes/db_connect.php';
require_once $root . '/includes/gps_agrega_diario.php';

$arg = $argv[1] ?? null;
if (is_string($arg) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $arg)) {
    $dia = $arg;
} else {
    $dia = date('Y-m-d', strtotime('-1 day'));
}

try {
    $conn = getConnection();
    $conn->beginTransaction();
    $out = gps_agrega_dia($conn, $dia);
    $conn->commit();
    fwrite(STDOUT, "GPS agregado {$dia}: resumos={$out['linhas_resumo']} pontos_removidos={$out['pontos_removidos']}\n");
} catch (Throwable $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . "\n");
    exit(1);
}
