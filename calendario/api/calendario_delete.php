<?php
require_once __DIR__ . '/calendario_api_base.php';

$empresa_id = calendario_api_require_post_json();
$input = json_decode(file_get_contents('php://input'), true);
$event_id = isset($input['id']) ? (int) $input['id'] : 0;

if ($event_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID do evento não fornecido']);
    exit;
}

try {
    $conn = getConnection();
    if (!calendario_evento_manual_only($conn, $event_id, $empresa_id)) {
        http_response_code(403);
        echo json_encode(['error' => 'Evento automático não pode ser excluído']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM calendario_eventos
        WHERE id = :id AND empresa_id = :empresa_id
        AND (origem_tipo = 'personalizado' OR origem_tipo IS NULL)");
    $stmt->execute([':id' => $event_id, ':empresa_id' => $empresa_id]);

    if ($stmt->rowCount() < 1) {
        http_response_code(404);
        echo json_encode(['error' => 'Evento não encontrado']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Evento excluído com sucesso']);
} catch (Throwable $e) {
    calendario_api_fail('calendario_delete', $e);
}
