<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['empresa_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sessão expirada']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';

if (!$id || !in_array($status, ['aprovado', 'rejeitado'])) {
    echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
    exit;
}

try {
    $conn = getConnection();
    $empresa_id = $_SESSION['empresa_id'];
    $stmt = $conn->prepare("UPDATE abastecimentos SET status = :status WHERE id = :id AND empresa_id = :empresa_id");
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 