<?php
require_once '../includes/db_connect.php';
session_start();
$empresa_id = $_SESSION['empresa_id'] ?? 1;
header('Content-Type: application/json');

try {
    $conn = getConnection();
    
    // Marcar todas as notificações como lidas
    $stmt = $conn->prepare('UPDATE notificacoes SET lida = 1 WHERE empresa_id = ?');
    $stmt->execute([$empresa_id]);
    
    // LIMPEZA AUTOMÁTICA: Remover notificações antigas (mais de 30 dias)
    $stmt_cleanup = $conn->prepare('DELETE FROM notificacoes 
                                   WHERE empresa_id = ? 
                                   AND data_criacao < DATE_SUB(NOW(), INTERVAL 30 DAY)');
    $stmt_cleanup->execute([$empresa_id]);
    
    // Obter estatísticas de limpeza
    $stmt_count = $conn->prepare('SELECT COUNT(*) as total FROM notificacoes WHERE empresa_id = ?');
    $stmt_count->execute([$empresa_id]);
    $total_restantes = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true, 
        'message' => 'Notificações limpas com sucesso',
        'total_restantes' => $total_restantes
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 