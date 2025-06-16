<?php
require_once '../includes/db_connect.php';
require_once '../IA/ia_regras.php';
session_start();
$empresa_id = $_SESSION['empresa_id'] ?? 1; // Ajuste conforme sua lógica de sessão
header('Content-Type: application/json');
$conn = getConnection();

$todas = isset($_GET['todas']) && $_GET['todas'] == '1';

try {
    if ($todas) {
        $stmt = $conn->prepare("SELECT * FROM notificacoes WHERE empresa_id = ? ORDER BY data_criacao DESC LIMIT 50");
        $stmt->execute([$empresa_id]);
    } else {
        $stmt = $conn->prepare("SELECT * FROM notificacoes WHERE empresa_id = ? AND lida = 0 ORDER BY data_criacao DESC LIMIT 50");
        $stmt->execute([$empresa_id]);
    }
    $notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'notificacoes' => $notificacoes]);
} catch (Exception $e) {
    error_log("Erro ao buscar notificações: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar notificações']);
} 