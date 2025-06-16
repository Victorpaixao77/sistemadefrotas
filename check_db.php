<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

try {
    $conn = getConnection();
    
    // Get all tables
    $sql = "SHOW TABLES";
    $stmt = $conn->query($sql);
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>Tables in database:</h2>";
    foreach ($tables as $table) {
        echo "<h3>Table: $table</h3>";
        
        // Get table structure
        $sql = "DESCRIBE $table";
        $stmt = $conn->query($sql);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<pre>";
        print_r($columns);
        echo "</pre>";
        
        // Get first row as sample
        $sql = "SELECT * FROM $table LIMIT 1";
        $stmt = $conn->query($sql);
        $sample = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($sample) {
            echo "<h4>Sample data:</h4>";
            echo "<pre>";
            print_r($sample);
            echo "</pre>";
        }
        
        echo "<hr>";
    }
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
} 