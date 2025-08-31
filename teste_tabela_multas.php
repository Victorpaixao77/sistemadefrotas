<?php
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

echo "<h2>Teste da Tabela Multas</h2>";

try {
    $conn = getConnection();
    echo "<p>✅ Conexão com banco estabelecida</p>";
    
    // Verificar se a tabela existe
    $sql_check = "SHOW TABLES LIKE 'multas'";
    $stmt = $conn->prepare($sql_check);
    $stmt->execute();
    $table_exists = $stmt->rowCount() > 0;
    
    if ($table_exists) {
        echo "<p>✅ Tabela 'multas' existe</p>";
        
        // Verificar estrutura da tabela
        $sql_structure = "DESCRIBE multas";
        $stmt = $conn->prepare($sql_structure);
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Estrutura da Tabela:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>{$column['Field']}</td>";
            echo "<td>{$column['Type']}</td>";
            echo "<td>{$column['Null']}</td>";
            echo "<td>{$column['Key']}</td>";
            echo "<td>{$column['Default']}</td>";
            echo "<td>{$column['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Verificar se há dados
        $sql_count = "SELECT COUNT(*) as total FROM multas";
        $stmt = $conn->prepare($sql_count);
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "<p>📊 Total de registros na tabela: <strong>{$count}</strong></p>";
        
    } else {
        echo "<p>❌ Tabela 'multas' NÃO existe</p>";
        
        // Verificar tabelas existentes
        $sql_tables = "SHOW TABLES";
        $stmt = $conn->prepare($sql_tables);
        $stmt->execute();
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<h3>Tabelas existentes no banco:</h3>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>{$table}</li>";
        }
        echo "</ul>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erro: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<h3>Próximos Passos:</h3>";
echo "<ul>";
echo "<li>Se a tabela não existir, execute o script de criação</li>";
echo "<li>Se a tabela existir mas estiver vazia, teste inserir um registro manual</li>";
echo "<li>Verifique os logs de erro do PHP para mais detalhes</li>";
echo "</ul>";
?>
