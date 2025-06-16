<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

try {
    $conn = getConnection();
    echo "Database connection successful!<br>";
    
    // Show all tables
    $result = $conn->query("SHOW TABLES");
    $tables = $result->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Tables in database:</h3>";
    echo "<pre>";
    print_r($tables);
    echo "</pre>";
    
    // Try to find motorista/motoristas table
    $motoristTable = in_array('motorista', $tables) ? 'motorista' : (in_array('motoristas', $tables) ? 'motoristas' : null);
    
    if ($motoristTable) {
        echo "<h3>Structure of table '$motoristTable':</h3>";
        $result = $conn->query("DESCRIBE $motoristTable");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($columns);
        echo "</pre>";
        
        // Show a sample row
        $result = $conn->query("SELECT * FROM $motoristTable LIMIT 1");
        $sample = $result->fetch(PDO::FETCH_ASSOC);
        if ($sample) {
            echo "<h3>Sample row:</h3>";
            echo "<pre>";
            print_r($sample);
            echo "</pre>";
        }
    } else {
        echo "<h3>No motorista/motoristas table found!</h3>";
    }
    
} catch (PDOException $e) {
    echo "<h3>Database Error:</h3>";
    echo $e->getMessage();
} 