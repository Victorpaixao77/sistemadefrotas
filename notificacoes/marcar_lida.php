<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db_connect.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/notificacoes_helpers.php';

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

api_require_csrf_json();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$empresa_id = (int) $_SESSION['empresa_id'];
$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}

try {
    $conn = getConnection();
    $stmt = $conn->prepare('UPDATE notificacoes SET lida = 1 WHERE id = :id AND empresa_id = :empresa_id AND lida = 0');
    $stmt->execute([':id' => $id, ':empresa_id' => $empresa_id]);

    echo json_encode([
        'success' => true,
        'updated' => $stmt->rowCount() > 0,
        'operacional' => sf_notif_contar_nao_lidas_usuario($conn, $empresa_id, sf_notif_usuario_id(), 'operacional'),
        'ia' => sf_notif_contar_nao_lidas_usuario($conn, $empresa_id, sf_notif_usuario_id(), 'ia'),
    ]);
} catch (Throwable $e) {
    error_log('notificacoes/marcar_lida.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao marcar notificação']);
}
