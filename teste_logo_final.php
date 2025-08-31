<?php
/**
 * 🧪 TESTE FINAL DA LOGO
 * 📋 Verificar se a logo está sendo carregada corretamente após as correções
 */

echo "<h1>🧪 Teste Final da Logo</h1>";

// Simular o que acontece no sidebar
$current_path = '/sistema-frotas/fiscal/pages/nfe.php';
echo "<p><strong>Caminho atual:</strong> {$current_path}</p>";

// Determinar o caminho base para a logo
$base_path = '';
if (strpos($current_path, '/fiscal/pages/') !== false) {
    $base_path = '../../';
} elseif (strpos($current_path, '/pages/') !== false) {
    $base_path = '../';
} elseif (strpos($current_path, '/calendario/') !== false) {
    $base_path = '../';
} else {
    $base_path = '';
}

echo "<p><strong>Caminho base calculado:</strong> {$base_path}</p>";

// Simular uma logo (como seria no banco)
$logo_empresa = '68a96954c24e9_paixao.logo.jpeg';
echo "<p><strong>Logo do banco:</strong> {$logo_empresa}</p>";

// Construir o caminho completo da logo (como no sidebar)
if (strpos($logo_empresa, 'uploads/') !== 0) {
    $logo_path = 'uploads/logos/' . $logo_empresa;
} else {
    $logo_path = $logo_empresa;
}

echo "<p><strong>Logo path construído:</strong> {$logo_path}</p>";

// Caminho final para a imagem
$logo_full_path = $base_path . $logo_path;
echo "<p><strong>Caminho final da logo:</strong> {$logo_full_path}</p>";

// Verificar se o arquivo existe
echo "<h2>🔍 Verificação do Arquivo</h2>";
if (file_exists($logo_path)) {
    echo "<p style='color: green;'>✅ Arquivo encontrado: {$logo_path}</p>";
    
    // Mostrar a logo
    echo "<h3>🖼️ Logo Teste</h3>";
    echo "<img src='{$logo_path}' alt='Logo Teste' style='max-width: 200px; max-height: 200px; border: 1px solid #ccc;'>";
    
    // Testar o caminho final
    echo "<h3>🖼️ Logo com Caminho Final</h3>";
    echo "<img src='{$logo_full_path}' alt='Logo Final' style='max-width: 200px; max-height: 200px; border: 1px solid #ccc;'>";
    
} else {
    echo "<p style='color: red;'>❌ Arquivo NÃO encontrado: {$logo_path}</p>";
}

echo "<hr>";
echo "<h2>🔗 Links de Teste</h2>";
echo "<ul>";
echo "<li><a href='fiscal/pages/nfe.php' target='_blank'>📄 Testar NF-e (verificar logo)</a></li>";
echo "<li><a href='fiscal/pages/cte.php' target='_blank'>📄 Testar CT-e (verificar logo)</a></li>";
echo "<li><a href='fiscal/pages/mdfe.php' target='_blank'>📄 Testar MDF-e (verificar logo)</a></li>";
echo "<li><a href='fiscal/pages/eventos.php' target='_blank'>📄 Testar Eventos (verificar logo)</a></li>";
echo "</ul>";

echo "<h2>✅ Teste Concluído</h2>";
echo "<p><strong>Próximo passo:</strong> Teste as páginas fiscais no navegador!</p>";
echo "<p><strong>Verifique:</strong></p>";
echo "<ul>";
echo "<li>✅ Logo aparecendo no sidebar</li>";
echo "<li>✅ Caminho correto sendo gerado</li>";
echo "<li>✅ Imagem carregando sem erros 404</li>";
echo "</ul>";

echo "<h2>🔧 Correções Aplicadas</h2>";
echo "<ul>";
echo "<li>✅ Caminho base corrigido para páginas fiscais (../../)</li>";
echo "<li>✅ Lógica de construção do caminho da logo simplificada</li>";
echo "<li>✅ Debug removido para limpeza do código</li>";
echo "</ul>";
?>
