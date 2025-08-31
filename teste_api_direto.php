<?php
/**
 * 🧪 TESTE DA API DIRETO
 * 📋 Executar a API diretamente no mesmo contexto PHP
 */

// Simular que estamos na página de configurações
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['empresa_id'] = 1;
$_SESSION['loggedin'] = true;

echo "<h1>🧪 Teste da API Direto</h1>";

echo "<h2>🔑 Sessão</h2>";
echo "<p><strong>User ID:</strong> " . $_SESSION['user_id'] . "</p>";
echo "<p><strong>Empresa ID:</strong> " . $_SESSION['empresa_id'] . "</p>";
echo "<p><strong>Logged In:</strong> " . ($_SESSION['loggedin'] ? 'Sim' : 'Não') . "</p>";

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

// Simular o ambiente da API
echo "<h2>🚀 Executando API Diretamente</h2>";

// Simular variáveis globais
$_GET['action'] = 'save_config_fiscal';
$_SERVER['REQUEST_METHOD'] = 'POST';

// Simular dados JSON no input
$input_json = json_encode($dados_teste);
file_put_contents('php://temp', $input_json);
rewind(fopen('php://temp', 'r'));

// Capturar output
ob_start();

try {
    // Incluir a API diretamente
    require_once 'api/configuracoes.php';
    
    $output = ob_get_contents();
    ob_end_clean();
    
    echo "<h3>📥 Resposta da API</h3>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
    // Tentar decodificar JSON
    $json_response = json_decode($output, true);
    if ($json_response) {
        echo "<h3>📋 Resposta Decodificada</h3>";
        echo "<pre>" . json_encode($json_response, JSON_PRETTY_PRINT) . "</pre>";
        
        if (isset($json_response['success'])) {
            if ($json_response['success']) {
                echo "<p style='color: green;'>✅ Sucesso!</p>";
                echo "<p><strong>Mensagem:</strong> " . ($json_response['message'] ?? 'N/A') . "</p>";
            } else {
                echo "<p style='color: red;'>❌ Falha!</p>";
                echo "<p><strong>Erro:</strong> " . ($json_response['error'] ?? 'N/A') . "</p>";
            }
        }
    } else {
        echo "<p style='color: orange;'>⚠️ Resposta não é JSON válido</p>";
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo "<p style='color: red;'>❌ Erro ao executar API: " . $e->getMessage() . "</p>";
    echo "<p><strong>Arquivo:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Linha:</strong> " . $e->getLine() . "</p>";
}

echo "<h2>🔍 Verificar Logs</h2>";
echo "<p>Verifique os logs do Apache para ver os logs de debug:</p>";
echo "<p><code>C:\\xampp\\apache\\logs\\error.log</code></p>";
?>
