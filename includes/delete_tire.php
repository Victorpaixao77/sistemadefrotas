<?php
require_once 'config.php';
require_once 'functions.php';

session_start();

try {
    $pdo = getConnection();
    
    $id = $_GET['id'] ?? null;
    $empresa_id = $_SESSION['empresa_id'] ?? 0;
    
    if (!$id) {
        throw new Exception('ID do pneu não fornecido');
    }
    
    // Verifica se o pneu pertence à empresa
    $stmt = $pdo->prepare("SELECT id FROM pneus WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$id, $empresa_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Pneu não encontrado ou não pertence à sua empresa');
    }
    
    // Exclui o pneu
    $stmt = $pdo->prepare("DELETE FROM pneus WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$id, $empresa_id]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 