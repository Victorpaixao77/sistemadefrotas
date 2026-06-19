<?php
require_once __DIR__ . '/calendario_api_base.php';

$empresa_id = calendario_api_require_post_json();
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['id']) || empty($input['start'])) {
    http_response_code(400);
    echo json_encode(['error' => 'id e start são obrigatórios']);
    exit;
}

$id = (int) $input['id'];
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

/** @param mixed $v */
function calendario_parse_dt($v)
{
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
    if (!calendario_evento_manual_only($conn, $id, $empresa_id)) {
        http_response_code(403);
        echo json_encode(['error' => 'Evento automático não pode ser movido']);
        exit;
    }

    $stmt = $conn->prepare('UPDATE calendario_eventos SET data_inicio = :di, data_fim = :df
        WHERE id = :id AND empresa_id = :eid AND (origem_tipo = \'personalizado\' OR origem_tipo IS NULL)');
    $stmt->bindValue(':di', $data_inicio);
    $stmt->bindValue(':df', $data_fim, $data_fim === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->bindValue(':eid', $empresa_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() < 1) {
        http_response_code(404);
        echo json_encode(['error' => 'Evento não encontrado']);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    calendario_api_fail('calendario_update_date', $e);
}
