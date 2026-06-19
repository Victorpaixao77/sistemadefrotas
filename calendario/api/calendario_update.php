<?php
require_once __DIR__ . '/calendario_api_base.php';

$empresa_id = calendario_api_require_post_json();
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['id']) || empty($input['title']) || empty($input['category']) || empty($input['start'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Campos obrigatórios faltando']);
    exit;
}

$id = (int) $input['id'];

try {
    $conn = getConnection();
    if (!calendario_evento_manual_only($conn, $id, $empresa_id)) {
        http_response_code(403);
        echo json_encode(['error' => 'Evento automático não pode ser editado aqui']);
        exit;
    }

    calendario_ensure_categorias($conn, $empresa_id);
    $categoria_id = calendario_categoria_id($conn, $empresa_id, (string) $input['category']);
    $data_fim = !empty($input['end']) ? $input['end'] : null;
    $descricao = !empty($input['description']) ? $input['description'] : null;

    $stmt = $conn->prepare('UPDATE calendario_eventos SET
        titulo = :titulo, categoria_id = :categoria_id, data_inicio = :data_inicio,
        data_fim = :data_fim, descricao = :descricao, cor = :cor
        WHERE id = :id AND empresa_id = :empresa_id AND (origem_tipo = \'personalizado\' OR origem_tipo IS NULL)');
    $stmt->execute([
        ':titulo' => $input['title'],
        ':categoria_id' => $categoria_id,
        ':data_inicio' => $input['start'],
        ':data_fim' => $data_fim,
        ':descricao' => $descricao,
        ':cor' => $input['color'] ?? '#3788d8',
        ':id' => $id,
        ':empresa_id' => $empresa_id,
    ]);

    if ($stmt->rowCount() < 1) {
        http_response_code(404);
        echo json_encode(['error' => 'Evento não encontrado']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Evento atualizado com sucesso']);
} catch (Throwable $e) {
    calendario_api_fail('calendario_update', $e);
}
