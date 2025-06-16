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
    
    // Prepare and execute query to get categories
    $stmt = $conn->prepare("SELECT id, nome FROM categorias_veiculos ORDER BY nome");
    $stmt->execute();
    
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($categorias)) {
        // If no categories found, insert default ones
        $default_categorias = [
            ['nome' => 'Leve'],
            ['nome' => 'Pesado'],
            ['nome' => 'Passeio'],
            ['nome' => 'Escolar'],
            ['nome' => 'Caminhão'],
            ['nome' => 'Utilitário'],
            ['nome' => 'Especial']
        ];
        
        $insert_stmt = $conn->prepare("INSERT INTO categorias_veiculos (nome) VALUES (:nome)");
        foreach ($default_categorias as $categoria) {
            $insert_stmt->execute([':nome' => $categoria['nome']]);
        }
        
        // Fetch the inserted categories
        $stmt->execute();
        $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode(['success' => true, 'data' => $categorias]);
    
} catch (PDOException $e) {
    error_log("Error in categorias.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar categorias: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in categorias.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno do servidor']);
} 