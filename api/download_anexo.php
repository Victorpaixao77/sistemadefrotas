<?php
/**
 * Download de anexo de manutenção (verifica sessão e empresa).
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';

configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_authentication();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('ID inválido');
}

$conn = getConnection();
$empresa_id = (int)($_SESSION['empresa_id'] ?? 0);
$stmt = $conn->prepare("SELECT nome_original, caminho FROM manutencao_anexos WHERE id = :id AND empresa_id = :eid");
$stmt->execute(['id' => $id, 'eid' => $empresa_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    exit('Anexo não encontrado');
}

$full_path = dirname(__DIR__) . '/' . $row['caminho'];
if (!is_file($full_path)) {
    http_response_code(404);
    exit('Arquivo não encontrado');
}

$nome = $row['nome_original'];
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($nome) . '"');
header('Content-Length: ' . filesize($full_path));
readfile($full_path);
exit;
