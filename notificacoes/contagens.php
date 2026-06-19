<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db_connect.php';
require_once dirname(__DIR__) . '/includes/notificacoes_helpers.php';
require_once dirname(__DIR__) . '/includes/notificacoes_sync_operacional.php';

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
$usuario_id = sf_notif_usuario_id();

try {
    $conn = getConnection();
    $ativo = sf_notificacoes_sistema_ativo($conn, $empresa_id);

    if (!$ativo) {
        echo json_encode(['success' => true, 'sistema_ativo' => false, 'operacional' => 0, 'ia' => 0]);
        exit;
    }

    sf_notif_sync_cnh($conn, $empresa_id);
    if (sf_notif_sync_pode_executar($conn, $empresa_id, 'sync_op_light', 15)) {
        sf_notif_sync_multas($conn, $empresa_id);
        sf_notif_sync_despesas_viagem($conn, $empresa_id);
        sf_notif_sync_registrar($conn, $empresa_id, 'sync_op_light');
    }

    echo json_encode([
        'success' => true,
        'sistema_ativo' => true,
        'operacional' => sf_notif_contar_nao_lidas_usuario($conn, $empresa_id, $usuario_id, 'operacional'),
        'ia' => sf_notif_contar_nao_lidas_usuario($conn, $empresa_id, $usuario_id, 'ia'),
    ]);
} catch (Throwable $e) {
    error_log('notificacoes/contagens.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao obter contagens']);
}
