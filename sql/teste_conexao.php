<?php
// Teste básico de conexão
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Teste de Conexão</h1>";

// Configurações
$host = 'localhost:3307';  // Porta 3307 conforme config.php
$dbname = 'sistema_frotas';
$user = 'root';
$pass = 'mudar123';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✅ CONEXÃO OK!</p>";
    
    // Listar tabelas
    $stmt = $conn->query("SHOW TABLES");
    $tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>Tabelas encontradas: " . count($tabelas) . "</h2>";
    echo "<ul>";
    foreach ($tabelas as $tabela) {
        echo "<li>" . htmlspecialchars($tabela) . "</li>";
    }
    echo "</ul>";
    
    // Verificar empresa_clientes
    if (in_array('empresa_clientes', $tabelas)) {
        echo "<h2>Estrutura empresa_clientes:</h2>";
        $stmt = $conn->query("DESCRIBE empresa_clientes");
        $campos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($campos as $campo) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($campo['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($campo['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($campo['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($campo['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($campo['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ ERRO: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Verifique as configurações do banco de dados.</p>";
}

?>
