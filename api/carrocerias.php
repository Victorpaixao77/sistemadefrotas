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
    
    // First check if the table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'carrocerias'");
    if ($check_table->rowCount() === 0) {
        // Create the table if it doesn't exist
        $conn->exec("CREATE TABLE IF NOT EXISTS carrocerias (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            descricao TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    }
    
    // Get vehicle body types
    $stmt = $conn->prepare("SELECT id, nome FROM carrocerias ORDER BY nome");
    $stmt->execute();
    
    $carrocerias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($carrocerias)) {
        // Insert default body types if none exist
        $default_carrocerias = [
            ['nome' => 'Baú'],
            ['nome' => 'Graneleiro'],
            ['nome' => 'Tanque'],
            ['nome' => 'Plataforma'],
            ['nome' => 'Sider'],
            ['nome' => 'Basculante']
        ];
        
        $insert_stmt = $conn->prepare("INSERT INTO carrocerias (nome) VALUES (:nome)");
        foreach ($default_carrocerias as $carroceria) {
            $insert_stmt->execute([':nome' => $carroceria['nome']]);
        }
        
        // Fetch the inserted body types
        $stmt->execute();
        $carrocerias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode(['success' => true, 'data' => $carrocerias]);
    
} catch (PDOException $e) {
    error_log("Error in carrocerias.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar carrocerias: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error in carrocerias.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno do servidor']);
} 