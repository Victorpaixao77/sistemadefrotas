<?php
/**
 * 🧪 TESTE DA TABELA EMPRESA
 * 📋 Verificar se a tabela empresa_clientes existe e sua estrutura
 */

require_once 'includes/config.php';
require_once 'includes/db_connect.php';

echo "<h1>🧪 Teste da Tabela Empresa</h1>";

try {
    $conn = getConnection();
    
    echo "<h2>🔍 Verificando Tabela empresa_clientes</h2>";
    
    // Verificar se a tabela existe
    $stmt = $conn->prepare("SHOW TABLES LIKE 'empresa_clientes'");
    $stmt->execute();
    $tabela_existe = $stmt->rowCount() > 0;
    
    if ($tabela_existe) {
        echo "<p style='color: green;'>✅ Tabela empresa_clientes EXISTE</p>";
        
        // Mostrar estrutura da tabela
        echo "<h3>📋 Estrutura da Tabela</h3>";
        $stmt = $conn->prepare("DESCRIBE empresa_clientes");
        $stmt->execute();
        $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th><th>Padrão</th><th>Extra</th></tr>";
        
        foreach ($colunas as $coluna) {
            echo "<tr>";
            echo "<td>{$coluna['Field']}</td>";
            echo "<td>{$coluna['Type']}</td>";
            echo "<td>{$coluna['Null']}</td>";
            echo "<td>{$coluna['Key']}</td>";
            echo "<td>{$coluna['Default']}</td>";
            echo "<td>{$coluna['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Verificar se há dados
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM empresa_clientes");
        $stmt->execute();
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo "<p><strong>Total de registros:</strong> {$total}</p>";
        
        if ($total > 0) {
            echo "<h3>📊 Dados de Exemplo</h3>";
            $stmt = $conn->prepare("SELECT * FROM empresa_clientes LIMIT 3");
            $stmt->execute();
            $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>CNPJ</th><th>Razão Social</th><th>Nome Fantasia</th><th>Status</th></tr>";
            
            foreach ($empresas as $empresa) {
                echo "<tr>";
                echo "<td>{$empresa['id']}</td>";
                echo "<td>{$empresa['cnpj']}</td>";
                echo "<td>{$empresa['razao_social']}</td>";
                echo "<td>{$empresa['nome_fantasia']}</td>";
                echo "<td>{$empresa['status']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Tabela empresa_clientes NÃO EXISTE</p>";
        
        // Verificar quais tabelas existem
        echo "<h3>📋 Tabelas Existentes</h3>";
        $stmt = $conn->prepare("SHOW TABLES");
        $stmt->execute();
        $tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<ul>";
        foreach ($tabelas as $tabela) {
            if (strpos($tabela, 'empresa') !== false) {
                echo "<li><strong>{$tabela}</strong> (contém 'empresa')</li>";
            } else {
                echo "<li>{$tabela}</li>";
            }
        }
        echo "</ul>";
    }
    
    echo "<h2>🔍 Verificando Tabela fiscal_config_empresa</h2>";
    
    // Verificar se a tabela fiscal existe
    $stmt = $conn->prepare("SHOW TABLES LIKE 'fiscal_config_empresa'");
    $stmt->execute();
    $tabela_fiscal_existe = $stmt->rowCount() > 0;
    
    if ($tabela_fiscal_existe) {
        echo "<p style='color: green;'>✅ Tabela fiscal_config_empresa EXISTE</p>";
        
        // Mostrar estrutura da tabela fiscal
        $stmt = $conn->prepare("DESCRIBE fiscal_config_empresa");
        $stmt->execute();
        $colunas_fiscal = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>📋 Estrutura da Tabela Fiscal</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th><th>Padrão</th><th>Extra</th></tr>";
        
        foreach ($colunas_fiscal as $coluna) {
            echo "<tr>";
            echo "<td>{$coluna['Field']}</td>";
            echo "<td>{$coluna['Type']}</td>";
            echo "<td>{$coluna['Null']}</td>";
            echo "<td>{$coluna['Key']}</td>";
            echo "<td>{$coluna['Default']}</td>";
            echo "<td>{$coluna['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<p style='color: red;'>❌ Tabela fiscal_config_empresa NÃO EXISTE</p>";
    }
    
    echo "<h2>🔍 Verificando Sessão</h2>";
    
    // Verificar dados da sessão
    session_start();
    echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
    echo "<p><strong>User ID:</strong> " . ($_SESSION['user_id'] ?? 'NÃO DEFINIDO') . "</p>";
    echo "<p><strong>Empresa ID:</strong> " . ($_SESSION['empresa_id'] ?? 'NÃO DEFINIDO') . "</p>";
    
    // Verificar se a empresa existe
    if (isset($_SESSION['empresa_id'])) {
        $empresa_id = $_SESSION['empresa_id'];
        $stmt = $conn->prepare("SELECT * FROM empresa_clientes WHERE id = :empresa_id");
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($empresa) {
            echo "<p style='color: green;'>✅ Empresa ID {$empresa_id} encontrada na tabela</p>";
            echo "<p><strong>CNPJ:</strong> {$empresa['cnpj']}</p>";
            echo "<p><strong>Razão Social:</strong> {$empresa['razao_social']}</p>";
        } else {
            echo "<p style='color: red;'>❌ Empresa ID {$empresa_id} NÃO encontrada na tabela</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
    echo "<p><strong>Arquivo:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Linha:</strong> " . $e->getLine() . "</p>";
}
?>
