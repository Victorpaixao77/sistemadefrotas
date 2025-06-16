<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

try {
    $conn = getConnection();
    
    // Get table structure
    $sql = "DESCRIBE motorista";
    $stmt = $conn->query($sql);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Estrutura da tabela 'motorista':</h2>";
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    // Get sample data
    $sql = "SELECT * FROM motorista LIMIT 1";
    $stmt = $conn->query($sql);
    $sample = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($sample) {
        echo "<h2>Exemplo de dados:</h2>";
        echo "<pre>";
        print_r($sample);
        echo "</pre>";
    }
    
} catch (PDOException $e) {
    // Try with plural form if singular fails
    try {
        $sql = "DESCRIBE motoristas";
        $stmt = $conn->query($sql);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h2>Estrutura da tabela 'motoristas':</h2>";
        echo "<pre>";
        print_r($columns);
        echo "</pre>";
        
        // Get sample data
        $sql = "SELECT * FROM motoristas LIMIT 1";
        $stmt = $conn->query($sql);
        $sample = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($sample) {
            echo "<h2>Exemplo de dados:</h2>";
            echo "<pre>";
            print_r($sample);
            echo "</pre>";
        }
    } catch (PDOException $e2) {
        echo "<h2>Erro ao acessar as tabelas:</h2>";
        echo "<pre>";
        echo "Erro motorista: " . $e->getMessage() . "\n";
        echo "Erro motoristas: " . $e2->getMessage();
        echo "</pre>";
        
        // List all tables in database
        echo "<h2>Tabelas disponíveis no banco:</h2>";
        $sql = "SHOW TABLES";
        $stmt = $conn->query($sql);
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<pre>";
        print_r($tables);
        echo "</pre>";
    }
} 