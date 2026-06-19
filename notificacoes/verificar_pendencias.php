<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db_connect.php';
require_once dirname(__DIR__) . '/includes/notificacoes_helpers.php';
require_once dirname(__DIR__) . '/includes/notificacoes_sync_operacional.php';
require_once dirname(__DIR__) . '/includes/notificacoes_sync_ia.php';

configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || empty($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$empresa_id = (int) $_SESSION['empresa_id'];

if (!sf_notificacoes_sistema_ativo(getConnection(), $empresa_id)) {
    echo json_encode(['success' => true, 'message' => 'Sistema de notificações desativado', 'skipped' => true]);
    exit;
}

try {
    $conn = getConnection();
    sf_notif_sync_operacional_completo($conn, $empresa_id);
    sf_notif_sync_ia_completo($conn, $empresa_id);

    $uid = sf_notif_usuario_id();
    echo json_encode([
        'success' => true,
        'message' => 'Verificação concluída',
        'operacional' => sf_notif_contar_nao_lidas_usuario($conn, $empresa_id, $uid, 'operacional'),
        'ia' => sf_notif_contar_nao_lidas_usuario($conn, $empresa_id, $uid, 'ia'),
    ]);
} catch (Throwable $e) {
    error_log('notificacoes/verificar_pendencias.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao verificar pendências']);
}
