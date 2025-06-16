<?php
require_once '../includes/db_connect.php';
session_start();
$empresa_id = $_SESSION['empresa_id'] ?? 1;
header('Content-Type: application/json');
try {
    $conn = getConnection();
    $stmt = $conn->prepare('UPDATE notificacoes SET lida = 1 WHERE empresa_id = ?');
    $stmt->execute([$empresa_id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 