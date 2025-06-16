<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Habilitar exibição de erros
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    $pdo = getConnection();
    
    // Testar tabelas
    $tables = ['pneus', 'veiculos'];
    $results = [];
    
    foreach ($tables as $table) {
        // Verificar se a tabela existe
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        
        if ($exists) {
            // Verificar estrutura da tabela
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Verificar se tem dados
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM $table");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            $results[$table] = [
                'exists' => true,
                'columns' => $columns,
                'record_count' => (int)$count
            ];
        } else {
            $results[$table] = [
                'exists' => false
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'tables' => $results
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