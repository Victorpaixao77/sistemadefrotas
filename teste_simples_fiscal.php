<?php
/**
 * 🧪 TESTE SIMPLES FISCAL
 * 📋 Verificar se há problemas na validação dos dados
 */

echo "<h1>🧪 Teste Simples Fiscal</h1>";

// Dados de teste
$dados_teste = [
    'ambiente_sefaz' => 'homologacao',
    'cnpj' => '12345678901234',
    'razao_social' => 'Empresa Teste LTDA',
    'nome_fantasia' => 'Empresa Teste',
    'inscricao_estadual' => '123456789',
    'codigo_municipio' => '3550308',
    'cep' => '01234-567',
    'endereco' => 'Rua Teste, 123',
    'telefone' => '(11) 1234-5678',
    'email' => 'teste@empresa.com'
];

echo "<h2>📊 Dados de Teste</h2>";
echo "<pre>" . json_encode($dados_teste, JSON_PRETTY_PRINT) . "</pre>";

// Validar dados obrigatórios
echo "<h2>✅ Validação dos Dados</h2>";

if (!isset($dados_teste['ambiente_sefaz']) || !isset($dados_teste['cnpj']) || !isset($dados_teste['razao_social'])) {
    echo "<p style='color: red;'>❌ Dados obrigatórios não informados</p>";
    exit;
} else {
    echo "<p style='color: green;'>✅ Dados obrigatórios informados</p>";
}

// Validar ambiente
if (!in_array($dados_teste['ambiente_sefaz'], ['homologacao', 'producao'])) {
    echo "<p style='color: red;'>❌ Ambiente inválido: " . $dados_teste['ambiente_sefaz'] . "</p>";
    exit;
} else {
    echo "<p style='color: green;'>✅ Ambiente válido: " . $dados_teste['ambiente_sefaz'] . "</p>";
}

// Validar CNPJ (14 dígitos)
if (strlen($dados_teste['cnpj']) !== 14 || !ctype_digit($dados_teste['cnpj'])) {
    echo "<p style='color: red;'>❌ CNPJ inválido: " . $dados_teste['cnpj'] . " (tamanho: " . strlen($dados_teste['cnpj']) . ")</p>";
    exit;
} else {
    echo "<p style='color: green;'>✅ CNPJ válido: " . $dados_teste['cnpj'] . "</p>";
}

echo "<h2>🔍 Verificar Estrutura da Tabela</h2>";

try {
    require_once 'includes/config.php';
    require_once 'includes/db_connect.php';
    
    $conn = getConnection();
    
    // Verificar se a empresa existe
    $stmt = $conn->prepare('SELECT id FROM empresa_clientes WHERE id = 1 LIMIT 1');
    $stmt->execute();
    $empresa_existe = $stmt->fetch();
    
    if ($empresa_existe) {
        echo "<p style='color: green;'>✅ Empresa ID 1 existe</p>";
    } else {
        echo "<p style='color: red;'>❌ Empresa ID 1 NÃO existe</p>";
    }
    
    // Verificar se há configuração fiscal
    $stmt = $conn->prepare('SELECT id FROM fiscal_config_empresa WHERE empresa_id = 1 LIMIT 1');
    $stmt->execute();
    $config_existe = $stmt->fetch();
    
    if ($config_existe) {
        echo "<p style='color: green;'>✅ Configuração fiscal para empresa 1 existe</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Configuração fiscal para empresa 1 NÃO existe (será criada)</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro ao conectar ao banco: " . $e->getMessage() . "</p>";
}

echo "<h2>📝 Próximos Passos</h2>";
echo "<p>1. Verificar se a sessão está sendo iniciada corretamente na página</p>";
echo "<p>2. Verificar se o empresa_id está sendo passado corretamente</p>";
echo "<p>3. Verificar se há erros de SQL na execução das queries</p>";
echo "<p>4. Verificar os logs do Apache para mais detalhes</p>";
?>
