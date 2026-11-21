<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Require authentication
require_authentication();
if (function_exists('session_write_close')) { session_write_close(); }

// Create database connection
$conn = getConnection();

// Set content type to JSON
header('Content-Type: application/json');

// Get the action from the request
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'list':
            $sql = "SELECT id, nome FROM formas_pagamento ORDER BY nome";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
            
        default:
            throw new Exception('Ação não suportada', 400);
    }
} catch (Throwable $e) {
    // Ação inválida (400) → mensagem clara; erro real → log + mensagem genérica (500).
    if ($e->getCode() === 400) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        error_log("Error in formas_pagamento.php: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro interno.']);
    }
}
