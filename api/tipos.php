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
    
    // Get empresa_id from session
    $empresa_id = $_SESSION['empresa_id'];
    
    // Prepare and execute query to get tipos for the company
    $stmt = $conn->prepare("SELECT id, nome FROM tipos_veiculos ORDER BY nome");
    $stmt->execute();
    
    $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($tipos)) {
        // If no tipos found, insert default ones
        $default_tipos = [
            ['nome' => 'Carro'],
            ['nome' => 'Moto'],
            ['nome' => 'Caminhão'],
            ['nome' => 'Ônibus'],
            ['nome' => 'Outro']
        ];
        
        $insert_stmt = $conn->prepare("INSERT INTO tipos_veiculos (nome) VALUES (:nome)");
        foreach ($default_tipos as $tipo) {
            $insert_stmt->execute([':nome' => $tipo['nome']]);
        }
        
        // Fetch the inserted tipos
        $stmt->execute();
        $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode(['success' => true, 'data' => $tipos]);
    
} catch (PDOException $e) {
    error_log("Error in tipos.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar tipos: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in tipos.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno do servidor']);
} 