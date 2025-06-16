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
            throw new Exception('Ação não suportada');
    }
} catch (Exception $e) {
    error_log("Error in formas_pagamento.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 