<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // Test database connection
    $pdo = getConnection();
    echo "Database connection successful!\n";
    
    // Check session status
    session_start();
    echo "\nSession Info:\n";
    echo "Session ID: " . session_id() . "\n";
    echo "Logged in: " . (isset($_SESSION['loggedin']) ? 'Yes' : 'No') . "\n";
    echo "Empresa ID: " . ($_SESSION['empresa_id'] ?? 'Not set') . "\n";
    
    // Test a simple query
    $stmt = $pdo->query("SHOW TABLES");
    echo "\nDatabase Tables:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} 