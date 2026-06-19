<?php
require_once __DIR__ . '/calendario_api_base.php';

$empresa_id = calendario_api_require_post_json();
$usuario_id = (int) ($_SESSION['usuario_id'] ?? $_SESSION['id'] ?? 0);

$title = trim((string) ($_POST['title'] ?? ''));
$category = trim((string) ($_POST['category'] ?? ''));
$start = trim((string) ($_POST['start'] ?? ''));
$end = trim((string) ($_POST['end'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$color = trim((string) ($_POST['color'] ?? '#3788d8'));

if ($title === '' || $category === '' || $start === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Campos obrigatórios faltando']);
    exit;
}

try {
    $conn = getConnection();
    calendario_ensure_categorias($conn, $empresa_id);
    $categoria_id = calendario_categoria_id($conn, $empresa_id, $category);
    if ($categoria_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Categoria inválida']);
        exit;
    }

    $sql = "INSERT INTO calendario_eventos (
                titulo, categoria_id, data_inicio, data_fim, descricao, cor,
                empresa_id, usuario_id, origem_tipo
            ) VALUES (
                :titulo, :categoria_id, :data_inicio, :data_fim, :descricao, :cor,
                :empresa_id, :usuario_id, 'personalizado'
            )";
    $data_fim = $end !== '' ? $end : null;
    $descricao = $description !== '' ? $description : null;
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':titulo' => $title,
        ':categoria_id' => $categoria_id,
        ':data_inicio' => $start,
        ':data_fim' => $data_fim,
        ':descricao' => $descricao,
        ':cor' => $color,
        ':empresa_id' => $empresa_id,
        ':usuario_id' => $usuario_id > 0 ? $usuario_id : null,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Evento criado com sucesso',
        'event_id' => (int) $conn->lastInsertId(),
    ]);
} catch (Throwable $e) {
    calendario_api_fail('calendario_create_simples', $e);
}
