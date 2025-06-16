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
error_log("Session state in vehicles.php: " . print_r($_SESSION, true));

// Get the action from the request
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    // Check if empresa_id exists in session
    if (!isset($_SESSION['empresa_id'])) {
        throw new Exception('Empresa ID não encontrado na sessão');
    }

    switch ($action) {
        case 'list':
            // Get all vehicles for the company
            $sql = "SELECT v.id, v.placa, v.modelo 
                   FROM veiculos v
                   WHERE v.empresa_id = :empresa_id 
                   ORDER BY v.placa";
            
            error_log("SQL Query: " . $sql);
            error_log("empresa_id: " . $_SESSION['empresa_id']);
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':empresa_id', $_SESSION['empresa_id']);
            $stmt->execute();
            
            $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Found vehicles: " . print_r($vehicles, true));
            
            if (empty($vehicles)) {
                echo json_encode([]);
            } else {
                echo json_encode($vehicles);
            }
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
} catch (Exception $e) {
    error_log('Error in vehicles.php: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 