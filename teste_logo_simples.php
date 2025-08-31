<?php
/**
 * 🧪 TESTE SIMPLES DA LOGO
 * 📋 Verificar se a logo está sendo carregada sem login
 */

echo "<h1>🧪 Teste Simples da Logo</h1>";

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

// Simular uma logo
$logo_path = '68a96954c24e9_paixao.logo.jpeg';
echo "<p><strong>Logo path:</strong> {$logo_path}</p>";

$logo_full_path = $base_path . $logo_path;
echo "<p><strong>Caminho completo da logo:</strong> {$logo_full_path}</p>";

// Verificar se o arquivo existe
$caminhos_teste = [
    'uploads/logos/' . $logo_path,
    '../uploads/logos/' . $logo_path,
    '../../uploads/logos/' . $logo_path
];

echo "<h2>🔍 Verificação de Arquivos</h2>";
foreach ($caminhos_teste as $caminho) {
    echo "<p><strong>{$caminho}:</strong> ";
    if (file_exists($caminho)) {
        echo "<span style='color: green;'>✅ Arquivo existe</span>";
        
        // Mostrar a logo
        echo " - <img src='{$caminho}' alt='Logo Teste' style='max-width: 100px; max-height: 100px; border: 1px solid #ccc;'>";
    } else {
        echo "<span style='color: red;'>❌ Arquivo NÃO existe</span>";
    }
    echo "</p>";
}

echo "<hr>";
echo "<h2>🔗 Links de Teste</h2>";
echo "<ul>";
echo "<li><a href='fiscal/pages/nfe.php' target='_blank'>📄 Testar NF-e (ver código fonte para debug)</a></li>";
echo "<li><a href='fiscal/pages/cte.php' target='_blank'>📄 Testar CT-e (ver código fonte para debug)</a></li>";
echo "</ul>";

echo "<h2>📋 Instruções para Debug</h2>";
echo "<ol>";
echo "<li>Clique em um dos links acima</li>";
echo "<li>Pressione F12 para abrir as ferramentas do desenvolvedor</li>";
echo "<li>Vá para a aba 'Elements' ou 'Elementos'</li>";
echo "<li>Procure por comentários HTML que começam com '<!-- Debug:'</li>";
echo "<li>Verifique se os caminhos estão sendo calculados corretamente</li>";
echo "</ol>";

echo "<h2>✅ Teste Concluído</h2>";
echo "<p><strong>Próximo passo:</strong> Verifique o código fonte das páginas fiscais para ver os comentários de debug!</p>";
?>
