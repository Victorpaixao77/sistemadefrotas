<?php
/**
 * 🧪 TESTE DA API CORRIGIDA
 * 📋 Testar se a API está funcionando após as correções
 */

echo "<h1>🧪 Teste da API Corrigida</h1>";

// Simular dados de teste
$dados_teste = [
    'ambiente_sefaz' => 'producao',
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

// Fazer requisição para a API corrigida
echo "<h2>🚀 Testando API Corrigida</h2>";

$url = 'http://localhost/sistema-frotas/api/configuracoes.php?action=save_config_fiscal';
$data = json_encode($dados_teste);

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
                echo "<p style='color: green;'>✅ Sucesso! API funcionando</p>";
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

echo "<h2>🔍 Análise</h2>";
if ($http_code === 200) {
    echo "<p style='color: green;'>✅ Sucesso! API retornou 200</p>";
    echo "<p>O problema foi resolvido! Agora você pode testar na página de configurações.</p>";
} elseif ($http_code === 500) {
    echo "<p style='color: red;'>❌ Erro 500: Ainda há problemas na API</p>";
    echo "<p>Verifique os logs do Apache para mais detalhes.</p>";
} elseif ($http_code === 401) {
    echo "<p style='color: red;'>❌ Erro 401: Problema de autenticação</p>";
    echo "<p>A autenticação ainda não foi corrigida.</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Código HTTP inesperado: {$http_code}</p>";
}

echo "<h2>📝 Próximos Passos</h2>";
echo "<p>1. Se a API retornou 200, teste na página de configurações</p>";
echo "<p>2. Se ainda há erro 500, verifique os logs do Apache</p>";
echo "<p>3. Se há erro 401, a autenticação precisa ser corrigida</p>";
?>
