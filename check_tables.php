<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

try {
    $conn = getConnection();
    
    // Get all tables
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>Estrutura do Banco de Dados</h2>";
    
    foreach ($tables as $table) {
        echo "<h3>Tabela: $table</h3>";
        
        // Get table structure
        $stmt = $conn->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th><th>Padrão</th><th>Extra</th></tr>";
        
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . $column['Field'] . "</td>";
            echo "<td>" . $column['Type'] . "</td>";
            echo "<td>" . $column['Null'] . "</td>";
            echo "<td>" . $column['Key'] . "</td>";
            echo "<td>" . $column['Default'] . "</td>";
            echo "<td>" . $column['Extra'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table><br>";
    }
    
} catch(PDOException $e) {
    echo "Erro: " . $e->getMessage();
} 