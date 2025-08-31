<?php
/**
 * 🧪 TESTE DA API DE CONFIGURAÇÕES FISCAIS
 * 📋 Testar a API save_config_fiscal
 */

echo "<h1>🧪 Teste da API de Configurações Fiscais</h1>";

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

// Simular POST request
$url = 'http://localhost/sistema-frotas/api/configuracoes.php';
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

echo "<h2>🚀 Enviando Requisição</h2>";
echo "<p><strong>URL:</strong> {$url}</p>";
echo "<p><strong>Método:</strong> POST</p>";
echo "<p><strong>Action:</strong> save_config_fiscal</p>";

// Adicionar action como parâmetro GET
$url_with_action = $url . '?action=save_config_fiscal';
curl_setopt($ch, CURLOPT_URL, $url_with_action);

echo "<p><strong>URL com Action:</strong> {$url_with_action}</p>";

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "<h2>📥 Resposta</h2>";
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
?>
