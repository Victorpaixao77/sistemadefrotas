<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

try {
    // Configure session
    configure_session();
    session_start();
    
    // Set a test empresa_id if not in session
    if (!isset($_SESSION['empresa_id'])) {
        $_SESSION['empresa_id'] = 1; // Use a valid empresa_id for testing
    }
    
    // Create database connection
    $conn = getConnection();
    
    echo "<pre>\n";
    
    // Test database connection
    $stmt = $conn->query("SELECT DATABASE()");
    $database = $stmt->fetchColumn();
    echo "Connected to database: " . $database . "\n\n";
    
    // Check veiculos table structure
    echo "Checking veiculos table structure:\n";
    $stmt = $conn->query("SHOW CREATE TABLE veiculos");
    $tableInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $tableInfo['Create Table'] . "\n\n";
    
    // Check sample data from veiculos
    echo "Sample data from veiculos table:\n";
    $stmt = $conn->query("SELECT id, placa, km_atual FROM veiculos LIMIT 5");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    echo "\n";
    
    // Test the actual query from get_maintenance_data.php
    echo "Testing maintenance query:\n";
    $empresa_id = $_SESSION['empresa_id'];
    $sql = "SELECT 
        COALESCE(SUM(v.km_atual), 0) as total_km,
        COUNT(DISTINCT m.id) as total_falhas,
        SUM(
            CASE 
                WHEN m.data_conclusao IS NOT NULL 
                THEN TIMESTAMPDIFF(HOUR, m.data_manutencao, m.data_conclusao)
                WHEN sm.nome = 'Concluída' 
                THEN TIMESTAMPDIFF(HOUR, m.data_manutencao, m.data_manutencao)
                ELSE TIMESTAMPDIFF(HOUR, m.data_manutencao, CURRENT_TIMESTAMP)
            END
        ) as total_horas_manutencao
        FROM manutencoes m
        LEFT JOIN veiculos v ON m.veiculo_id = v.id
        LEFT JOIN status_manutencao sm ON m.status_manutencao_id = sm.id
        WHERE m.empresa_id = :empresa_id 
        AND m.data_manutencao >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)";
    
    // Also check the manutencoes table structure
    echo "\nChecking manutencoes table structure:\n";
    $stmt = $conn->query("SHOW CREATE TABLE manutencoes");
    $tableInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $tableInfo['Create Table'] . "\n\n";
    
    // Show sample maintenance records
    echo "Sample maintenance records:\n";
    $stmt = $conn->query("SELECT id, data_manutencao, data_conclusao, status_manutencao_id FROM manutencoes LIMIT 5");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($result);
    
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<pre>\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    echo "</pre>";
} 