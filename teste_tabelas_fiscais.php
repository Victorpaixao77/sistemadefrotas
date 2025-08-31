<?php
/**
 * 🧪 TESTE DAS TABELAS FISCAIS
 * 📋 Verificar se as duas tabelas estão funcionando para o salvamento
 */

echo "<h1>🧪 Teste das Tabelas Fiscais</h1>";

try {
    require_once 'includes/config.php';
    require_once 'includes/db_connect.php';
    
    $conn = getConnection();
    
    echo "<h2>🔍 Verificando Tabela empresa_clientes</h2>";
    
    // Verificar se a empresa ID 1 existe
    $stmt = $conn->prepare("SELECT id, cnpj, razao_social, nome_fantasia FROM empresa_clientes WHERE id = 1");
    $stmt->execute();
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($empresa) {
        echo "<p style='color: green;'>✅ Empresa ID 1 existe</p>";
        echo "<p><strong>CNPJ:</strong> " . ($empresa['cnpj'] ?? 'N/A') . "</p>";
        echo "<p><strong>Razão Social:</strong> " . ($empresa['razao_social'] ?? 'N/A') . "</p>";
        echo "<p><strong>Nome Fantasia:</strong> " . ($empresa['nome_fantasia'] ?? 'N/A') . "</p>";
    } else {
        echo "<p style='color: red;'>❌ Empresa ID 1 NÃO existe</p>";
        
        // Tentar criar uma empresa de teste
        echo "<h3>🔧 Criando Empresa de Teste</h3>";
        try {
            $create_empresa = "INSERT INTO empresa_clientes (empresa_adm_id, razao_social, nome_fantasia, cnpj, status) VALUES (1, 'Empresa Teste LTDA', 'Empresa Teste', '12345678901234', 'ativo')";
            $conn->exec($create_empresa);
            echo "<p style='color: green;'>✅ Empresa de teste criada com sucesso!</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Erro ao criar empresa: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<h2>🔍 Verificando Tabela fiscal_config_empresa</h2>";
    
    // Verificar se há configuração fiscal para empresa 1
    $stmt = $conn->prepare("SELECT id, ambiente_sefaz FROM fiscal_config_empresa WHERE empresa_id = 1");
    $stmt->execute();
    $config_fiscal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($config_fiscal) {
        echo "<p style='color: green;'>✅ Configuração fiscal para empresa 1 existe</p>";
        echo "<p><strong>Ambiente:</strong> " . ($config_fiscal['ambiente_sefaz'] ?? 'N/A') . "</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Configuração fiscal para empresa 1 NÃO existe (será criada ao salvar)</p>";
    }
    
    echo "<h2>🧪 Testando Queries de UPDATE/INSERT</h2>";
    
    // Testar UPDATE na empresa_clientes
    echo "<h3>📝 Testando UPDATE empresa_clientes</h3>";
    try {
        $test_update = $conn->prepare("UPDATE empresa_clientes SET data_atualizacao = NOW() WHERE id = 1");
        $result = $test_update->execute();
        
        if ($result) {
            echo "<p style='color: green;'>✅ UPDATE em empresa_clientes funcionando</p>";
        } else {
            echo "<p style='color: red;'>❌ UPDATE em empresa_clientes falhou</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro no UPDATE empresa_clientes: " . $e->getMessage() . "</p>";
    }
    
    // Testar INSERT na fiscal_config_empresa
    echo "<h3>📝 Testando INSERT fiscal_config_empresa</h3>";
    try {
        // Primeiro remover se existir para testar INSERT
        $delete_test = $conn->prepare("DELETE FROM fiscal_config_empresa WHERE empresa_id = 999");
        $delete_test->execute();
        
        $test_insert = $conn->prepare("INSERT INTO fiscal_config_empresa (empresa_id, ambiente_sefaz, cnpj, razao_social) VALUES (999, 'homologacao', '99999999999999', 'Teste Insert')");
        $result = $test_insert->execute();
        
        if ($result) {
            echo "<p style='color: green;'>✅ INSERT em fiscal_config_empresa funcionando</p>";
            
            // Limpar o teste
            $cleanup = $conn->prepare("DELETE FROM fiscal_config_empresa WHERE empresa_id = 999");
            $cleanup->execute();
        } else {
            echo "<p style='color: red;'>❌ INSERT em fiscal_config_empresa falhou</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro no INSERT fiscal_config_empresa: " . $e->getMessage() . "</p>";
    }
    
    echo "<h2>🔍 Verificar Estrutura das Tabelas</h2>";
    
    // Verificar campos obrigatórios
    echo "<h3>📋 Campos Obrigatórios empresa_clientes</h3>";
    $stmt = $conn->prepare("DESCRIBE empresa_clientes");
    $stmt->execute();
    $colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th><th>Padrão</th></tr>";
    
    foreach ($colunas as $coluna) {
        $style = $coluna['Null'] === 'NO' ? 'background-color: #ffe6e6;' : '';
        echo "<tr style='{$style}'>";
        echo "<td>{$coluna['Field']}</td>";
        echo "<td>{$coluna['Type']}</td>";
        echo "<td>{$coluna['Null']}</td>";
        echo "<td>{$coluna['Key']}</td>";
        echo "<td>{$coluna['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>📋 Campos Obrigatórios fiscal_config_empresa</h3>";
    $stmt = $conn->prepare("DESCRIBE fiscal_config_empresa");
    $stmt->execute();
    $colunas_fiscal = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Chave</th><th>Padrão</th></tr>";
    
    foreach ($colunas_fiscal as $coluna) {
        $style = $coluna['Null'] === 'NO' ? 'background-color: #ffe6e6;' : '';
        echo "<tr style='{$style}'>";
        echo "<td>{$coluna['Field']}</td>";
        echo "<td>{$coluna['Type']}</td>";
        echo "<td>{$coluna['Null']}</td>";
        echo "<td>{$coluna['Key']}</td>";
        echo "<td>{$coluna['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro geral: " . $e->getMessage() . "</p>";
    echo "<p><strong>Arquivo:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Linha:</strong> " . $e->getLine() . "</p>";
}

echo "<h2>📝 Próximos Passos</h2>";
echo "<p>1. Verificar se as tabelas têm a estrutura correta</p>";
echo "<p>2. Verificar se há campos obrigatórios que não estão sendo preenchidos</p>";
echo "<p>3. Verificar se há problemas de foreign key</p>";
echo "<p>4. Testar novamente o salvamento na página</p>";
?>
