<?php
/**
 * 🧪 TESTE DAS APIS FISCAIS
 * 📋 Verificar se as APIs estão retornando dados corretamente
 */

echo "<h1>🧪 Teste das APIs Fiscais</h2>";

// Testar cada API
$apis = [
    'fiscal/api/fiscal_dashboard.php' => 'Dashboard',
    'fiscal/api/fiscal_documents.php' => 'Documentos',
    'fiscal/api/fiscal_sefaz_status.php' => 'Status SEFAZ',
    'fiscal/api/fiscal_motoristas_veiculos.php' => 'Motoristas/Veículos',
    'fiscal/api/fiscal_nfe.php' => 'NF-e',
    'fiscal/api/fiscal_cte.php' => 'CT-e',
    'fiscal/api/fiscal_mdfe.php' => 'MDF-e',
    'fiscal/api/fiscal_events.php' => 'Eventos'
];

foreach ($apis as $api_path => $nome) {
    echo "<h3>🔌 Testando {$nome}</h3>";
    
    if (!file_exists($api_path)) {
        echo "<p style='color: red;'>❌ Arquivo não encontrado: {$api_path}</p>";
        continue;
    }
    
    // Simular requisição POST
    $post_data = json_encode(['empresa_id' => 1]);
    
    // Usar cURL para testar a API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://localhost/sistema-frotas/{$api_path}");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($post_data)
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "<p style='color: red;'>❌ Erro cURL: {$error}</p>";
    } else {
        echo "<p style='color: green;'>✅ Status HTTP: {$http_code}</p>";
        
        if ($http_code == 200) {
            $data = json_decode($response, true);
            if ($data && isset($data['success'])) {
                echo "<p style='color: green;'>✅ API funcionando! Success: " . ($data['success'] ? 'true' : 'false') . "</p>";
                if (isset($data['message'])) {
                    echo "<p>📝 Mensagem: {$data['message']}</p>";
                }
            } else {
                echo "<p style='color: orange;'>⚠️ Resposta inválida ou não-JSON</p>";
                echo "<p>📄 Resposta: " . substr($response, 0, 200) . "...</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ Erro HTTP: {$http_code}</p>";
            echo "<p>📄 Resposta: " . substr($response, 0, 200) . "...</p>";
        }
    }
    
    echo "<hr>";
}

echo "<h2>✅ Teste Concluído</h2>";
echo "<p><strong>Próximo passo:</strong> Teste as páginas fiscais no navegador!</p>";
?>
