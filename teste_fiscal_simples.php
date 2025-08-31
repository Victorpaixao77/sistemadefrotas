<?php
/**
 * 🧪 TESTE SIMPLES DO SISTEMA FISCAL
 * 📋 Verificar se as APIs estão funcionando e retornando dados
 */

echo "<h1>🧪 Teste Simples do Sistema Fiscal</h1>";

// Testar uma API específica
$api_path = 'fiscal/api/fiscal_dashboard.php';

if (file_exists($api_path)) {
    echo "<p style='color: green;'>✅ API encontrada: {$api_path}</p>";
    
    // Simular requisição POST simples
    $post_data = json_encode(['empresa_id' => 1]);
    
    // Usar cURL para testar
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://localhost/sistema-frotas/{$api_path}");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "<p><strong>Status HTTP:</strong> {$http_code}</p>";
    
    if ($http_code == 200) {
        echo "<p style='color: green;'>✅ API funcionando perfeitamente!</p>";
        
        $data = json_decode($response, true);
        if ($data && isset($data['success']) && $data['success']) {
            echo "<p style='color: green;'>✅ Dados retornados com sucesso!</p>";
            echo "<p><strong>Mensagem:</strong> {$data['message']}</p>";
            
            if (isset($data['data'])) {
                echo "<h3>📊 Dados do Dashboard:</h3>";
                echo "<ul>";
                if (isset($data['data']['nfe'])) {
                    echo "<li><strong>NF-e:</strong> Total: {$data['data']['nfe']['total']}, Pendentes: {$data['data']['nfe']['pendentes']}</li>";
                }
                if (isset($data['data']['cte'])) {
                    echo "<li><strong>CT-e:</strong> Total: {$data['data']['cte']['total']}, Pendentes: {$data['data']['cte']['pendentes']}</li>";
                }
                if (isset($data['data']['mdfe'])) {
                    echo "<li><strong>MDF-e:</strong> Total: {$data['data']['mdfe']['total']}, Pendentes: {$data['data']['mdfe']['pendentes']}</li>";
                }
                echo "</ul>";
            }
        } else {
            echo "<p style='color: orange;'>⚠️ API retornou dados, mas success = false</p>";
            echo "<p><strong>Resposta:</strong> " . substr($response, 0, 200) . "...</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Status {$http_code} - Problema na API</p>";
        echo "<p><strong>Resposta:</strong> " . substr($response, 0, 200) . "...</p>";
    }
    
} else {
    echo "<p style='color: red;'>❌ API não encontrada: {$api_path}</p>";
}

echo "<hr>";
echo "<h2>🔗 Links de Teste</h2>";
echo "<ul>";
echo "<li><a href='fiscal/pages/nfe.php' target='_blank'>📄 Testar NF-e</a></li>";
echo "<li><a href='fiscal/pages/cte.php' target='_blank'>📄 Testar CT-e</a></li>";
echo "<li><a href='fiscal/pages/mdfe.php' target='_blank'>📄 Testar MDF-e</a></li>";
echo "<li><a href='fiscal/pages/eventos.php' target='_blank'>📄 Testar Eventos</a></li>";
echo "</ul>";

echo "<h2>✅ Teste Concluído</h2>";
echo "<p><strong>Próximo passo:</strong> Teste as páginas fiscais no navegador!</p>";
echo "<p><strong>Dica:</strong> Abra o Console do navegador (F12) para ver se há erros JavaScript.</p>";
?>
