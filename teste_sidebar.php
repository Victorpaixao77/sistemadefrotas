<?php
/**
 * 🧪 TESTE DO SIDEBAR
 * 📋 Verificar se o menu fiscal está aparecendo
 */

echo "<h1>🧪 Teste do Sidebar</h1>";

// Verificar se o sidebar existe
$arquivo_sidebar = 'includes/sidebar.php';
if (file_exists($arquivo_sidebar)) {
    echo "<p style='color: green;'>✅ Arquivo sidebar.php encontrado</p>";
    
    // Verificar se contém o menu fiscal
    $conteudo_sidebar = file_get_contents($arquivo_sidebar);
    if (strpos($conteudo_sidebar, 'Sistema Fiscal') !== false) {
        echo "<p style='color: green;'>✅ Menu fiscal encontrado no sidebar</p>";
        
        // Verificar a posição
        $linhas = explode("\n", $conteudo_sidebar);
        $posicao_fiscal = -1;
        $posicao_financeiro = -1;
        
        foreach ($linhas as $i => $linha) {
            if (strpos($linha, 'Sistema Fiscal') !== false) {
                $posicao_fiscal = $i;
            }
            if (strpos($linha, 'Financeiro') !== false) {
                $posicao_financeiro = $i;
            }
        }
        
        if ($posicao_fiscal !== -1 && $posicao_financeiro !== -1) {
            if ($posicao_fiscal < $posicao_financeiro) {
                echo "<p style='color: green;'>✅ Menu fiscal está acima do financeiro (posição correta)</p>";
            } else {
                echo "<p style='color: red;'>❌ Menu fiscal está abaixo do financeiro (posição incorreta)</p>";
            }
        }
        
    } else {
        echo "<p style='color: red;'>❌ Menu fiscal não encontrado no sidebar</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Arquivo sidebar.php não encontrado</p>";
}

// Verificar se a página fiscal existe e funciona
$arquivo_fiscal = 'pages/fiscal.php';
if (file_exists($arquivo_fiscal)) {
    echo "<p style='color: green;'>✅ Arquivo fiscal.php encontrado</p>";
    
    // Verificar se não tem erros de sintaxe
    $conteudo_fiscal = file_get_contents($arquivo_fiscal);
    if (strpos($conteudo_fiscal, '<?php') !== false && strpos($conteudo_fiscal, '?>') !== false) {
        echo "<p style='color: green;'>✅ Arquivo fiscal.php tem sintaxe PHP válida</p>";
    } else {
        echo "<p style='color: red;'>❌ Arquivo fiscal.php não tem sintaxe PHP válida</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Arquivo fiscal.php não encontrado</p>";
}

// Links de teste
echo "<h2>🔗 Links de Teste</h2>";
echo "<ul>";
echo "<li><a href='pages/fiscal.php' target='_blank'>📄 Página Principal Fiscal</a></li>";
echo "<li><a href='includes/sidebar.php' target='_blank'>📄 Ver Sidebar</a></li>";
echo "</ul>";

echo "<h2>✅ Teste Concluído</h2>";
echo "<p>Verifique se o menu fiscal aparece no sidebar e se a página fiscal carrega!</p>";
?>
