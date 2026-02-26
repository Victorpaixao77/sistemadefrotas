<?php
// Verificação completa da estrutura das tabelas
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost:3307';
$dbname = 'sistema_frotas';
$user = 'root';
$pass = 'mudar123';

echo "<h1>Estrutura Completa das Tabelas</h1>";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $tabelas = ['empresa_clientes', 'veiculos', 'motoristas', 'rotas', 'abastecimentos', 'despesas_viagem', 'despesas_fixas'];
    
    foreach ($tabelas as $tabela) {
        echo "<h2>Tabela: $tabela</h2>";
        
        try {
            $stmt = $conn->query("DESCRIBE `$tabela`");
            $campos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse; margin-bottom: 30px;'>";
            echo "<tr style='background: #007bff; color: white;'>";
            echo "<th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
            echo "</tr>";
            
            foreach ($campos as $campo) {
                $bg = '';
                if ($campo['Null'] === 'NO' && $campo['Default'] === null && $campo['Extra'] !== 'auto_increment') {
                    $bg = "background: #fff3cd;"; // Amarelo para obrigatório
                }
                
                echo "<tr style='$bg'>";
                echo "<td><strong>" . htmlspecialchars($campo['Field']) . "</strong></td>";
                echo "<td>" . htmlspecialchars($campo['Type']) . "</td>";
                echo "<td>" . ($campo['Null'] === 'YES' ? '✅ Sim' : '❌ Não') . "</td>";
                echo "<td>" . ($campo['Key'] ? htmlspecialchars($campo['Key']) : '-') . "</td>";
                echo "<td>" . ($campo['Default'] !== null ? htmlspecialchars($campo['Default']) : 'NULL') . "</td>";
                echo "<td>" . ($campo['Extra'] ? htmlspecialchars($campo['Extra']) : '-') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Verificar dados existentes
            $stmt = $conn->query("SELECT COUNT(*) as total FROM `$tabela`");
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            if ($tabela !== 'empresa_clientes') {
                $stmt = $conn->query("SELECT COUNT(*) as total FROM `$tabela` WHERE empresa_id = 1");
                $total_emp1 = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                echo "<p><strong>Total de registros:</strong> $total | <strong>Empresa ID 1:</strong> $total_emp1</p>";
            } else {
                $stmt = $conn->query("SELECT COUNT(*) as total FROM `$tabela` WHERE id = 1");
                $total_id1 = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                echo "<p><strong>Total de registros:</strong> $total | <strong>ID = 1:</strong> " . ($total_id1 > 0 ? '✅ Existe' : '❌ Não existe') . "</p>";
            }
            
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    
    // Verificar ENUMs específicos
    echo "<h2>Verificação de ENUMs Importantes</h2>";
    
    // Verificar campo fonte da tabela rotas
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM rotas WHERE Field = 'fonte'");
        $campo = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($campo) {
            echo "<p><strong>rotas.fonte:</strong> " . htmlspecialchars($campo['Type']) . "</p>";
            preg_match("/enum\((.*)\)/i", $campo['Type'], $matches);
            if (isset($matches[1])) {
                echo "<p><strong>Valores permitidos:</strong> " . htmlspecialchars($matches[1]) . "</p>";
            }
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>Erro ao verificar ENUM: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Verificar campo status da tabela rotas
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM rotas WHERE Field = 'status'");
        $campo = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($campo) {
            echo "<p><strong>rotas.status:</strong> " . htmlspecialchars($campo['Type']) . "</p>";
            preg_match("/enum\((.*)\)/i", $campo['Type'], $matches);
            if (isset($matches[1])) {
                echo "<p><strong>Valores permitidos:</strong> " . htmlspecialchars($matches[1]) . "</p>";
            }
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>Erro ao verificar ENUM: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Erro de conexão: " . htmlspecialchars($e->getMessage()) . "</p>";
}

?>
