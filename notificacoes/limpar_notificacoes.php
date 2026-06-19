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

try {
    $conn = getConnection();

    $tipos = sf_notif_tipos_operacional();
    $ph = [];
    $params = [':empresa_id' => $empresa_id];
    foreach ($tipos as $i => $t) {
        $key = ':tipo' . $i;
        $ph[] = $key;
        $params[$key] = $t;
    }

    $sql = 'UPDATE notificacoes SET lida = 1
            WHERE empresa_id = :empresa_id AND lida = 0 AND tipo IN (' . implode(', ', $ph) . ')';
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $stmt_cleanup = $conn->prepare(
        'DELETE FROM notificacoes
         WHERE empresa_id = :empresa_id AND lida = 1
         AND data_criacao < DATE_SUB(NOW(), INTERVAL 30 DAY)'
    );
    $stmt_cleanup->execute([':empresa_id' => $empresa_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Notificações operacionais marcadas como lidas',
        'marcadas' => $stmt->rowCount(),
        'operacional' => sf_notif_contar_nao_lidas_usuario($conn, $empresa_id, sf_notif_usuario_id(), 'operacional'),
        'ia' => sf_notif_contar_nao_lidas_usuario($conn, $empresa_id, sf_notif_usuario_id(), 'ia'),
    ]);
} catch (Throwable $e) {
    error_log('notificacoes/limpar_notificacoes.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao limpar notificações']);
}
