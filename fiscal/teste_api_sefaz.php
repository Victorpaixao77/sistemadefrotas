<?php
/**
 * 🧪 TESTE DA API DE ENVIO PARA SEFAZ
 * 📋 Sistema de Gestão de Frotas
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

echo "<h1>🧪 Teste da API de Envio para SEFAZ</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nfe_id = $_POST['nfe_id'] ?? null;
    
    if (!$nfe_id) {
        echo "<p>❌ ID da NF-e não fornecido</p>";
        exit;
    }
    
    echo "<p><strong>NF-e ID:</strong> {$nfe_id}</p>";
    
    // Simular chamada para a API
    echo "<h2>📡 Simulando chamada para API</h2>";
    
    $url = 'http://localhost/sistema-frotas/fiscal/api/documentos_fiscais_v2.php';
    $data = [
        'action' => 'enviar_sefaz',
        'id' => $nfe_id,
        'tipo_documento' => 'nfe'
    ];
    
    echo "<p><strong>URL:</strong> {$url}</p>";
    echo "<p><strong>Dados:</strong> " . json_encode($data, JSON_PRETTY_PRINT) . "</p>";
    
    // Fazer a chamada real
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "<h2>📥 Resposta da API</h2>";
    echo "<p><strong>HTTP Code:</strong> {$http_code}</p>";
    
    if ($error) {
        echo "<p>❌ <strong>Erro cURL:</strong> {$error}</p>";
    } else {
        echo "<p>✅ <strong>Resposta:</strong></p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        
        // Tentar decodificar JSON
        $json_data = json_decode($response, true);
        if ($json_data) {
            echo "<h3>📊 Dados Decodificados:</h3>";
            echo "<pre>" . json_encode($json_data, JSON_PRETTY_PRINT) . "</pre>";
            
            if (isset($json_data['success']) && $json_data['success']) {
                echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
                echo "<strong>✅ Sucesso!</strong><br>";
                echo "Status: " . ($json_data['status'] ?? 'N/A') . "<br>";
                echo "Protocolo: " . ($json_data['protocolo'] ?? 'N/A');
                echo "</div>";
            } else {
                echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
                echo "<strong>❌ Erro:</strong> " . ($json_data['error'] ?? 'Erro desconhecido');
                echo "</div>";
            }
        }
    }
    
} else {
    echo "<p>❌ Método não permitido</p>";
}

echo "<hr>";
echo "<p><a href='teste_sefaz.php'>← Voltar ao Teste SEFAZ</a></p>";
echo "<p><a href='pages/nfe.php'>📄 Ir para Página de NF-e</a></p>";
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h1 { color: #333; }
    h2 { color: #666; margin-top: 30px; }
    h3 { color: #888; margin-top: 20px; }
    p { margin: 10px 0; }
    pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    a { color: #007bff; text-decoration: none; }
    a:hover { text-decoration: underline; }
    .success { color: #28a745; }
    .error { color: #dc3545; }
    hr { margin: 30px 0; border: none; border-top: 1px solid #ddd; }
</style>
