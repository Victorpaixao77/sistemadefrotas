<?php
/**
 * 🧪 TESTE RÁPIDO DO SIDEBAR_PAGES
 */

echo "<h1>🧪 Teste Rápido do Sidebar Pages</h1>";

// Verificar se o sidebar_pages existe
$arquivo_sidebar = 'includes/sidebar_pages.php';
if (file_exists($arquivo_sidebar)) {
    echo "<p style='color: green;'>✅ Arquivo sidebar_pages.php encontrado</p>";
    
    // Verificar se contém o menu fiscal
    $conteudo_sidebar = file_get_contents($arquivo_sidebar);
    if (strpos($conteudo_sidebar, 'Sistema Fiscal') !== false) {
        echo "<p style='color: green;'>✅ Menu fiscal encontrado no sidebar_pages</p>";
        
        // Verificar a posição
        $linhas = explode("\n", $conteudo_sidebar);
        $posicao_fiscal = -1;
        $posicao_financeiro = -1;
        $posicao_manutencoes = -1;
        
        foreach ($linhas as $i => $linha) {
            if (strpos($linha, 'Sistema Fiscal') !== false) {
                $posicao_fiscal = $i;
            }
            if (strpos($linha, 'Financeiro') !== false) {
                $posicao_financeiro = $i;
            }
            if (strpos($linha, 'Manutenções') !== false) {
                $posicao_manutencoes = $i;
            }
        }
        
        echo "<p><strong>Posições encontradas:</strong></p>";
        echo "<ul>";
        echo "<li>Manutenções: linha " . ($posicao_manutencoes + 1) . "</li>";
        echo "<li>Sistema Fiscal: linha " . ($posicao_fiscal + 1) . "</li>";
        echo "<li>Financeiro: linha " . ($posicao_financeiro + 1) . "</li>";
        echo "</ul>";
        
        if ($posicao_manutencoes < $posicao_fiscal && $posicao_fiscal < $posicao_financeiro) {
            echo "<p style='color: green;'>✅ Ordem correta: Manutenções → Sistema Fiscal → Financeiro</p>";
        } else {
            echo "<p style='color: red;'>❌ Ordem incorreta</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Menu fiscal não encontrado no sidebar_pages</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Arquivo sidebar_pages.php não encontrado</p>";
}

// Verificar se o sidebar.php principal não tem menu fiscal duplicado
$arquivo_sidebar_principal = 'includes/sidebar.php';
if (file_exists($arquivo_sidebar_principal)) {
    $conteudo_principal = file_get_contents($arquivo_sidebar_principal);
    $ocorrencias_fiscal = substr_count($conteudo_principal, 'Sistema Fiscal');
    
    if ($ocorrencias_fiscal == 0) {
        echo "<p style='color: green;'>✅ Sidebar principal não tem menu fiscal duplicado</p>";
    } else {
        echo "<p style='color: red;'>❌ Sidebar principal ainda tem {$ocorrencias_fiscal} menu(s) fiscal(is)</p>";
    }
}

echo "<h2>🔗 Teste da Página</h2>";
echo "<p><a href='pages/fiscal.php' target='_blank'>📄 Testar Página Fiscal</a></p>";
echo "<p><a href='includes/sidebar_pages.php' target='_blank'>📄 Ver Sidebar Pages</a></p>";
echo "<p><a href='includes/sidebar.php' target='_blank'>📄 Ver Sidebar Principal</a></p>";

echo "<h2>✅ Teste Concluído</h2>";
echo "<p>O menu fiscal deve aparecer apenas no sidebar_pages.php!</p>";
?>
