<?php
/**
 * 🧪 TESTE DE VISUALIZAÇÃO DE NF-e
 * 📋 Sistema de Gestão de Frotas
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

echo "<h1>🧪 Teste de Visualização de NF-e</h1>";

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
        
        echo "<h2>🔗 Links de Teste</h2>";
        echo "<p><a href='visualizar_nfe.php?id={$nfe['id']}' target='_blank'>👁️ Visualizar NF-e #{$nfe['numero_nfe']}</a></p>";
        echo "<p><a href='impressao/nfe.php?id={$nfe['id']}' target='_blank'>🖨️ Imprimir NF-e #{$nfe['numero_nfe']}</a></p>";
        echo "<p><a href='pages/nfe.php'>📄 Voltar para Página Principal</a></p>";
        
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
</style>
