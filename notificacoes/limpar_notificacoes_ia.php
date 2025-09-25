<?php
require_once '../includes/db_connect.php';
session_start();
$empresa_id = $_SESSION['empresa_id'];
header('Content-Type: application/json');

try {
    $conn = getConnection();
    
    // Marcar apenas notificações de IA como lidas
    // Tipos de notificações de IA: manutencao, alerta, pneu, documento, financeiro, rota, abastecimento, consumo, custo, seguranca, insights, recomendacao
    $stmt = $conn->prepare('UPDATE notificacoes SET lida = 1 WHERE empresa_id = ? AND tipo IN ("manutencao", "alerta", "pneu", "documento", "financeiro", "rota", "abastecimento", "consumo", "custo", "seguranca", "insights", "recomendacao")');
    $stmt->execute([$empresa_id]);
    
    // Obter estatísticas de limpeza
    $stmt_count = $conn->prepare('SELECT COUNT(*) as total FROM notificacoes WHERE empresa_id = ? AND lida = 0');
    $stmt_count->execute([$empresa_id]);
    $total_restantes = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Contar notificações de IA não lidas
    $stmt_ia = $conn->prepare('SELECT COUNT(*) as total FROM notificacoes WHERE empresa_id = ? AND lida = 0 AND tipo IN ("manutencao", "alerta", "pneu", "documento", "financeiro", "rota", "abastecimento", "consumo", "custo", "seguranca", "insights", "recomendacao")');
    $stmt_ia->execute([$empresa_id]);
    $total_ia_restantes = $stmt_ia->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true, 
        'message' => 'Notificações de IA limpas com sucesso',
        'total_restantes' => $total_restantes,
        'total_ia_restantes' => $total_ia_restantes
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
