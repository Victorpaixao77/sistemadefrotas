<?php
require_once __DIR__ . '/sf_paths.php';
require_once __DIR__ . '/notificacoes_helpers.php';
/**
 * Sincroniza alertas de CNH vencendo (30/60/90 dias) para o sino (tabela notificacoes).
 * Incluído por pages/motorists.php e contagens/notificacoes.php.
 */
if (!isset($conn)) {
    header('Content-Type: text/plain; charset=utf-8');
    die('Este arquivo não deve ser acessado diretamente.');
}
if (!isset($_SESSION['empresa_id']) || empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    return;
}

$empresa_id = (int) $_SESSION['empresa_id'];
if ($empresa_id <= 0) {
    return;
}

try {
    sf_notif_sync_cnh($conn, $empresa_id);
} catch (Throwable $e) {
    error_log('sync_cnh_notificacoes: ' . $e->getMessage());
}
