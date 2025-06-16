<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Habilitar exibição de erros
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    session_start();
    
    // Testar sessão
    $session_info = [
        'session_id' => session_id(),
        'loggedin' => isset($_SESSION['loggedin']) ? $_SESSION['loggedin'] : false,
        'empresa_id' => isset($_SESSION['empresa_id']) ? $_SESSION['empresa_id'] : null,
        'all_session_data' => $_SESSION
    ];
    
    // Testar conexão com banco
    $pdo = getConnection();
    
    // Testar tabela pneus
    $stmt = $pdo->query("SHOW TABLES LIKE 'pneus'");
    $table_exists = $stmt->rowCount() > 0;
    
    if ($table_exists) {
        // Verificar estrutura da tabela
        $stmt = $pdo->query("DESCRIBE pneus");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    echo json_encode([
        'success' => true,
        'session' => $session_info,
        'database' => [
            'connected' => true,
            'pneus_table_exists' => $table_exists,
            'pneus_columns' => $columns ?? null
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
} 