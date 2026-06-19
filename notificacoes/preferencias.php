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

$empresa_id = (int) $_SESSION['empresa_id'];
$usuario_id = sf_notif_usuario_id();
$conn = getConnection();
sf_notif_ensure_schema($conn);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'success' => true,
        'preferencias' => sf_notif_preferencias($conn, $usuario_id, $empresa_id),
        'tipos_operacional' => sf_notif_tipos_para_usuario('operacional'),
        'tipos_ia' => sf_notif_tipos_para_usuario('ia'),
        'labels' => sf_notif_tipo_labels(),
    ]);
    exit;
}

api_require_csrf_json();

$tiposOff = $_POST['tipos_desativados'] ?? '[]';
if (is_string($tiposOff)) {
    $tiposOff = json_decode($tiposOff, true);
}
if (!is_array($tiposOff)) {
    $tiposOff = [];
}
$tiposOff = array_values(array_unique(array_map('strval', $tiposOff)));
$digest = isset($_POST['email_digest']) ? (int) $_POST['email_digest'] : 0;
$hora = (string) ($_POST['email_digest_hora'] ?? '08:00:00');
if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora)) {
    $hora = '08:00:00';
}

try {
    $st = $conn->prepare('INSERT INTO notificacoes_preferencias (usuario_id, empresa_id, tipos_desativados, email_digest, email_digest_hora)
        VALUES (:u, :e, :t, :d, :h)
        ON DUPLICATE KEY UPDATE tipos_desativados = :t2, email_digest = :d2, email_digest_hora = :h2');
    $json = json_encode($tiposOff, JSON_UNESCAPED_UNICODE);
    $st->execute([
        ':u' => $usuario_id, ':e' => $empresa_id, ':t' => $json, ':d' => $digest, ':h' => $hora,
        ':t2' => $json, ':d2' => $digest, ':h2' => $hora,
    ]);
    echo json_encode(['success' => true, 'preferencias' => sf_notif_preferencias($conn, $usuario_id, $empresa_id)]);
} catch (Throwable $e) {
    error_log('notificacoes/preferencias.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao salvar preferências']);
}
