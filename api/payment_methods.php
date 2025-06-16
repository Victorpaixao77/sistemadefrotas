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

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_error.log');

// Debug session state
error_log("Session state in payment_methods.php: " . print_r($_SESSION, true));

// Get the action from the request
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'list':
            // Get all payment methods
            $sql = "SELECT id, nome as name FROM formas_pagamento ORDER BY nome";
            
            error_log("SQL Query: " . $sql);
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Found payment methods: " . print_r($methods, true));
            
            if (empty($methods)) {
                echo json_encode([]);
            } else {
                echo json_encode($methods);
            }
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
} catch (Exception $e) {
    error_log('Error in payment_methods.php: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 