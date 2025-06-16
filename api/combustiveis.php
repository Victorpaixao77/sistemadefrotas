<?php
// Initialize session and include necessary files
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Ensure the request is authenticated
require_authentication();

try {
    $conn = getConnection();
    
    // Prepare and execute query to get fuel types
    $stmt = $conn->prepare("SELECT id, nome FROM tipos_combustivel ORDER BY nome");
    $stmt->execute();
    
    $combustiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($combustiveis)) {
        // If no fuel types found, insert default ones
        $default_combustiveis = [
            ['nome' => 'Gasolina'],
            ['nome' => 'Diesel'],
            ['nome' => 'Etanol'],
            ['nome' => 'GNV'],
            ['nome' => 'Elétrico']
        ];
        
        $insert_stmt = $conn->prepare("INSERT INTO tipos_combustivel (nome) VALUES (:nome)");
        foreach ($default_combustiveis as $combustivel) {
            $insert_stmt->execute([':nome' => $combustivel['nome']]);
        }
        
        // Fetch the inserted fuel types
        $stmt->execute();
        $combustiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode(['success' => true, 'data' => $combustiveis]);
    
} catch (PDOException $e) {
    error_log("Error in combustiveis.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar tipos de combustível: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in combustiveis.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno do servidor']);
} 