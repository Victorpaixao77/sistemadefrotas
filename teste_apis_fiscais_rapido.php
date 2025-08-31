<?php
/**
 * 🧪 TESTE RÁPIDO DAS APIS FISCAIS
 * 📋 Verificar se as APIs estão funcionando
 */

echo "<h1>🧪 Teste Rápido das APIs Fiscais</h1>";

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
    
    if ($http_code == 401) {
        echo "<p style='color: orange;'>⚠️ Status 401 - API funcionando, mas precisa de autenticação (esperado)</p>";
    } elseif ($http_code == 200) {
        echo "<p style='color: green;'>✅ Status 200 - API funcionando perfeitamente!</p>";
    } else {
        echo "<p style='color: red;'>❌ Status {$http_code} - Problema na API</p>";
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
?>
