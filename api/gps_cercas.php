<?php
/**
 * Cercas eletrônicas (CRUD JSON, sessão painel).
 * GET: lista. POST JSON: criar { nome, latitude, longitude, raio_metros } ou { action: delete, id }.
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db_connect.php';
require_once dirname(__DIR__) . '/includes/api_json.php';
require_once dirname(__DIR__) . '/includes/csrf.php';

configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_authentication();
$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if ($empresa_id <= 0) {
    api_json_unauthorized();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    try {
        $conn = getConnection();
        $stmt = $conn->prepare('
            SELECT id, nome, latitude, longitude, raio_metros, ativo, created_at
            FROM gps_cercas
            WHERE empresa_id = :eid
            ORDER BY nome ASC
        ');
        $stmt->execute([':eid' => $empresa_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
            $r['latitude'] = (float) $r['latitude'];
            $r['longitude'] = (float) $r['longitude'];
            $r['raio_metros'] = (int) $r['raio_metros'];
            $r['ativo'] = (int) $r['ativo'];
        }
        unset($r);
        api_json_send(['success' => true, 'message' => 'OK', 'data' => ['cercas' => $rows]]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'gps_cercas') !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
            api_json_send(['success' => true, 'message' => 'OK', 'data' => ['cercas' => []]]);
        }
        error_log('gps_cercas GET: ' . $e->getMessage());
        api_json_error('Erro ao listar cercas', 500);
    }
}

if ($method !== 'POST') {
    api_json_method_not_allowed();
}

api_require_csrf_json();

$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '', true);
if (!is_array($input)) {
    $input = $_POST;
}

try {
    $conn = getConnection();
} catch (PDOException $e) {
    api_json_error('Erro de conexão', 500);
}

if (($input['action'] ?? '') === 'delete') {
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) {
        api_json_error('id inválido', 400);
    }
    $stmt = $conn->prepare('DELETE FROM gps_cercas WHERE id = :id AND empresa_id = :eid');
    $stmt->execute([':id' => $id, ':eid' => $empresa_id]);
    api_json_send(['success' => true, 'message' => 'Cerca removida.', 'data' => ['deleted' => $stmt->rowCount()]]);
}

$nome = isset($input['nome']) ? trim((string) $input['nome']) : '';
$lat = isset($input['latitude']) ? (float) $input['latitude'] : null;
$lng = isset($input['longitude']) ? (float) $input['longitude'] : null;
$raio = isset($input['raio_metros']) ? (int) $input['raio_metros'] : 500;

if ($nome === '' || $lat === null || $lng === null) {
    api_json_error('nome, latitude e longitude são obrigatórios.', 400);
}
if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    api_json_error('Coordenadas inválidas.', 400);
}
$raio = max(50, min(50000, $raio));

try {
    $stmt = $conn->prepare('
        INSERT INTO gps_cercas (empresa_id, nome, latitude, longitude, raio_metros, ativo)
        VALUES (:eid, :nome, :lat, :lng, :raio, 1)
    ');
    $stmt->execute([
        ':eid' => $empresa_id,
        ':nome' => $nome,
        ':lat' => round($lat, 8),
        ':lng' => round($lng, 8),
        ':raio' => $raio,
    ]);
    $newId = (int) $conn->lastInsertId();
    api_json_send(['success' => true, 'message' => 'Cerca criada.', 'data' => ['id' => $newId]]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'gps_cercas') !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
        api_json_error('Execute sql/create_gps_cercas.sql no banco.', 503);
    }
    error_log('gps_cercas POST: ' . $e->getMessage());
    api_json_error('Erro ao salvar cerca.', 500);
}
