<?php
require_once 'config.php';
require_once 'functions.php';

// Iniciar a sessão
session_start();

try {
    $pdo = getConnection();
    
    $type = isset($_GET['type']) ? $_GET['type'] : null;
    
    switch($type) {
        case 'status':
            $stmt = $pdo->prepare("SELECT id, nome FROM status_pneus ORDER BY nome");
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'posicoes':
            $stmt = $pdo->prepare("SELECT id, nome FROM posicoes_pneus ORDER BY nome");
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'veiculos':
            // Buscar veículos da empresa atual
            $empresa_id = $_SESSION['empresa_id'] ?? 0;
            $stmt = $pdo->prepare("SELECT id, placa as nome FROM veiculos WHERE empresa_id = ? ORDER BY placa");
            $stmt->execute([$empresa_id]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        default:
            throw new Exception('Tipo de dados não especificado');
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $data]);
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
} 