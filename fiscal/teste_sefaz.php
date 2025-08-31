<?php
/**
 * 🚀 TESTE DE INTEGRAÇÃO COM SEFAZ
 * 📋 Sistema de Gestão de Frotas
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

echo "<h1>🚀 Teste de Integração com SEFAZ</h1>";

try {
    $conn = getConnection();
    echo "<p>✅ Conexão com banco estabelecida</p>";
    
    // Verificar se existe alguma NF-e
    $stmt = $conn->query("SELECT id, numero_nfe, status FROM fiscal_nfe_clientes LIMIT 1");
    $nfe = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($nfe) {
        echo "<p>✅ NF-e encontrada:</p>";
        echo "<ul>";
        echo "<li><strong>ID:</strong> {$nfe['id']}</li>";
        echo "<li><strong>Número:</strong> {$nfe['numero_nfe']}</li>";
        echo "<li><strong>Status:</strong> {$nfe['status']}</li>";
        echo "</ul>";
        
        echo "<h2>🧪 Teste de Validações</h2>";
        echo "<p><strong>CNPJ Válido:</strong> 11.222.333/0001-81</p>";
        echo "<p><strong>CNPJ Inválido:</strong> 11.111.111/1111-11</p>";
        
        echo "<h2>🔗 Links de Teste</h2>";
        echo "<p><a href='pages/nfe.php'>📄 Página Principal de NF-e</a></p>";
        echo "<p><a href='teste_visualizacao.php'>👁️ Teste de Visualização</a></p>";
        
        // Testar API de envio para SEFAZ
        echo "<h2>🚀 Teste de API SEFAZ</h2>";
        echo "<form method='post' action='teste_api_sefaz.php'>";
        echo "<input type='hidden' name='nfe_id' value='{$nfe['id']}'>";
        echo "<button type='submit' style='padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;'>";
        echo "🧪 Testar Envio para SEFAZ";
        echo "</button>";
        echo "</form>";
        
    } else {
        echo "<p>❌ Nenhuma NF-e encontrada no banco</p>";
        echo "<p><a href='teste_nfe.php'>🧪 Criar NF-e de teste primeiro</a></p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erro: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h1 { color: #333; }
    h2 { color: #666; margin-top: 30px; }
    p { margin: 10px 0; }
    ul { margin: 10px 0; padding-left: 20px; }
    li { margin: 5px 0; }
    a { color: #007bff; text-decoration: none; }
    a:hover { text-decoration: underline; }
    .success { color: #28a745; }
    .error { color: #dc3545; }
    form { margin: 20px 0; }
</style>
