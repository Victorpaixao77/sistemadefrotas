<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/api_json.php';
require_once __DIR__ . '/pneus_helpers.php';

header('Content-Type: application/json; charset=utf-8');

configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_authentication();
pneus_require_page_access();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_json_method_not_allowed('Use POST para excluir pneu.');
}
api_require_csrf_json();

try {
    $pdo = getConnection();
    $empresa_id = pneus_empresa_id();
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        $data = [];
    }
    $id = (int) ($data['id'] ?? $_GET['id'] ?? 0);

    if ($id <= 0) {
        throw new InvalidArgumentException('ID do pneu não fornecido');
    }
    if (!pneus_validar_pneu_empresa($pdo, $id, $empresa_id)) {
        throw new InvalidArgumentException('Pneu não encontrado');
    }
    if (pneus_esta_alocado($pdo, $id)) {
        throw new InvalidArgumentException('Não é possível excluir pneu alocado em veículo. Remova a alocação primeiro.');
    }

    $stmt = $pdo->prepare('DELETE FROM pneus WHERE id = ? AND empresa_id = ?');
    $stmt->execute([$id, $empresa_id]);

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('delete_tire.php: ' . $e->getMessage());
    $code = $e instanceof InvalidArgumentException ? 400 : 500;
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Erro ao excluir pneu',
    ], JSON_UNESCAPED_UNICODE);
}
