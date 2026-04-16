<?php
/**
 * Atualiza apenas data_inicio / data_fim (ex.: arrastar evento no FullCalendar).
 */
session_name('sistema_frotas_session');
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/sistema-frotas',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['id']) || empty($input['start'])) {
    http_response_code(400);
    echo json_encode(['error' => 'id e start são obrigatórios']);
    exit;
}

$empresa_id = (int) $_SESSION['empresa_id'];
$id = (int) $input['id'];

/** @param mixed $v */
function calendario_parse_dt($v) {
    if ($v === null || $v === '') {
        return null;
    }
    $s = str_replace('T', ' ', trim((string) $v));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        return $s . ' 00:00:00';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $s)) {
        return $s . ':00';
    }
    $t = strtotime($s);
    return $t ? date('Y-m-d H:i:s', $t) : null;
}

$data_inicio = calendario_parse_dt($input['start']);
$data_fim = isset($input['end']) ? calendario_parse_dt($input['end']) : null;

if (!$data_inicio) {
    http_response_code(400);
    echo json_encode(['error' => 'Data de início inválida']);
    exit;
}

try {
    $conn = getConnection();
    $check = $conn->prepare('SELECT id FROM calendario_eventos WHERE id = :id AND empresa_id = :eid');
    $check->execute([':id' => $id, ':eid' => $empresa_id]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Evento não encontrado']);
        exit;
    }

    $sql = 'UPDATE calendario_eventos SET data_inicio = :di, data_fim = :df WHERE id = :id AND empresa_id = :eid';
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':di', $data_inicio);
    $stmt->bindValue(':df', $data_fim, $data_fim === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->bindValue(':eid', $empresa_id, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atualizar datas']);
}
