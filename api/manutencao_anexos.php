<?php
/**
 * Anexos de manutenção: listar, upload, excluir.
 * GET ?manutencao_id=X → lista anexos
 * POST (multipart) manutencao_id + file → upload
 * POST action=delete & id=X → excluir
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_authentication();

$conn = getConnection();
$empresa_id = (int)($_SESSION['empresa_id'] ?? 0);
if ($empresa_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$base_dir = dirname(__DIR__) . '/uploads/manutencao_anexos';
if (!is_dir($base_dir)) {
    @mkdir($base_dir, 0755, true);
}

function listAnexos($conn, $manutencao_id, $empresa_id) {
    $stmt = $conn->prepare("SELECT id, nome_original, caminho, tipo, tamanho, data_upload FROM manutencao_anexos WHERE manutencao_id = :mid AND empresa_id = :eid ORDER BY data_upload DESC");
    $stmt->bindValue(':mid', $manutencao_id, PDO::PARAM_INT);
    $stmt->bindValue(':eid', $empresa_id, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['url'] = '../api/download_anexo.php?id=' . $r['id'];
    }
    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $manutencao_id = isset($_GET['manutencao_id']) ? (int)$_GET['manutencao_id'] : 0;
    if ($manutencao_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'manutencao_id obrigatório']);
        exit;
    }
    // Verificar se a manutenção pertence à empresa
    $st = $conn->prepare("SELECT id FROM manutencoes WHERE id = :id AND empresa_id = :eid");
    $st->execute(['id' => $manutencao_id, 'eid' => $empresa_id]);
    if (!$st->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Manutenção não encontrada']);
        exit;
    }
    $list = listAnexos($conn, $manutencao_id, $empresa_id);
    echo json_encode(['success' => true, 'data' => $list]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';

    if ($action === 'delete') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            echo json_encode(['success' => false, 'error' => 'id obrigatório']);
            exit;
        }
        $stmt = $conn->prepare("SELECT id, caminho, empresa_id FROM manutencao_anexos WHERE id = :id AND empresa_id = :eid");
        $stmt->execute(['id' => $id, 'eid' => $empresa_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['success' => false, 'error' => 'Anexo não encontrado']);
            exit;
        }
        $full_path = dirname(__DIR__) . '/' . $row['caminho'];
        if (file_exists($full_path)) {
            @unlink($full_path);
        }
        $conn->prepare("DELETE FROM manutencao_anexos WHERE id = :id")->execute(['id' => $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // Upload
    $manutencao_id = isset($_POST['manutencao_id']) ? (int)$_POST['manutencao_id'] : 0;
    if ($manutencao_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'manutencao_id obrigatório']);
        exit;
    }
    $st = $conn->prepare("SELECT id FROM manutencoes WHERE id = :id AND empresa_id = :eid");
    $st->execute(['id' => $manutencao_id, 'eid' => $empresa_id]);
    if (!$st->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Manutenção não encontrada']);
        exit;
    }
    if (empty($_FILES['file']['name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Nenhum arquivo enviado ou erro no upload']);
        exit;
    }
    $nome_original = basename($_FILES['file']['name']);
    $ext = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx'];
    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'error' => 'Tipo de arquivo não permitido. Use: ' . implode(', ', $allowed)]);
        exit;
    }
    $dir = $base_dir . '/' . $empresa_id . '/' . $manutencao_id;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($nome_original, PATHINFO_FILENAME));
    $filename = $safe_name . '_' . time() . '.' . $ext;
    $relative = 'uploads/manutencao_anexos/' . $empresa_id . '/' . $manutencao_id . '/' . $filename;
    $full_path = dirname(__DIR__) . '/' . $relative;
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $full_path)) {
        echo json_encode(['success' => false, 'error' => 'Falha ao salvar arquivo']);
        exit;
    }
    $tamanho = (int)filesize($full_path);
    $stmt = $conn->prepare("INSERT INTO manutencao_anexos (manutencao_id, empresa_id, nome_original, caminho, tipo, tamanho) VALUES (:mid, :eid, :nome, :caminho, :tipo, :tamanho)");
    $stmt->execute([
        'mid' => $manutencao_id,
        'eid' => $empresa_id,
        'nome' => $nome_original,
        'caminho' => $relative,
        'tipo' => $_FILES['file']['type'] ?? $ext,
        'tamanho' => $tamanho
    ]);
    $id = (int)$conn->lastInsertId();
    $row = ['id' => $id, 'nome_original' => $nome_original, 'caminho' => $relative, 'tipo' => $ext, 'tamanho' => $tamanho, 'data_upload' => date('Y-m-d H:i:s'), 'url' => '../api/download_anexo.php?id=' . $id];
    echo json_encode(['success' => true, 'data' => $row]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Método não permitido']);
