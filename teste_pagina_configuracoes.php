<?php
/**
 * 🧪 TESTE DA PÁGINA DE CONFIGURAÇÕES
 * 📋 Simular exatamente o que a página está fazendo
 */

// Simular que estamos na página de configurações
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['empresa_id'] = 1;
$_SESSION['loggedin'] = true;

echo "<h1>🧪 Teste da Página de Configurações</h1>";

echo "<h2>🔑 Sessão</h2>";
echo "<p><strong>User ID:</strong> " . $_SESSION['user_id'] . "</p>";
echo "<p><strong>Empresa ID:</strong> " . $_SESSION['empresa_id'] . "</p>";

// Simular o formulário sendo enviado
echo "<h2>📝 Simulando Envio do Formulário</h2>";

// Dados que seriam enviados pelo formulário
$form_data = [
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

echo "<p><strong>Dados do Formulário:</strong></p>";
echo "<pre>" . json_encode($form_data, JSON_PRETTY_PRINT) . "</pre>";

// Simular a requisição AJAX
echo "<h2>🚀 Simulando Requisição AJAX</h2>";

$url = 'http://localhost/sistema-frotas/api/configuracoes.php?action=save_config_fiscal';
$data = json_encode($form_data);

echo "<p><strong>URL:</strong> {$url}</p>";
echo "<p><strong>Dados:</strong> {$data}</p>";

// Fazer a requisição real
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($data)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=' . session_id());

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "<h2>📥 Resposta da API</h2>";
echo "<p><strong>HTTP Code:</strong> {$http_code}</p>";

if ($error) {
    echo "<p style='color: red;'><strong>❌ Erro cURL:</strong> {$error}</p>";
} else {
    echo "<p><strong>Resposta:</strong></p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    // Tentar decodificar JSON
    $json_response = json_decode($response, true);
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
}

echo "<h2>🔍 Verificar Logs</h2>";
echo "<p>Verifique os logs do Apache para ver os logs de debug:</p>";
echo "<p><code>C:\\xampp\\apache\\logs\\error.log</code></p>";

echo "<h2>📝 Análise</h2>";
if ($http_code === 401) {
    echo "<p style='color: red;'>❌ Erro 401: Problema de autenticação</p>";
    echo "<p>Possíveis causas:</p>";
    echo "<ul>";
    echo "<li>Sessão não está sendo passada corretamente</li>";
    echo "<li>Cookie de sessão não está sendo enviado</li>";
    echo "<li>Função require_authentication() está falhando</li>";
    echo "</ul>";
} elseif ($http_code === 500) {
    echo "<p style='color: red;'>❌ Erro 500: Erro interno do servidor</p>";
    echo "<p>Verifique os logs do Apache para mais detalhes</p>";
} elseif ($http_code === 200) {
    echo "<p style='color: green;'>✅ Sucesso! API funcionando</p>";
}
?>
