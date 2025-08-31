<?php
/**
 * 🧪 TESTE COMPLETO DO SISTEMA FISCAL
 * 📋 Verificar se sidebar, tema e funcionalidades estão funcionando
 */

echo "<h1>🧪 Teste Completo do Sistema Fiscal</h1>";

// Verificar arquivos JavaScript necessários
$arquivos_js = [
    'js/sidebar.js',
    'js/theme.js',
    'fiscal/assets/js/fiscal.js'
];

echo "<h2>📁 Verificação de Arquivos JavaScript</h2>";
foreach ($arquivos_js as $arquivo) {
    if (file_exists($arquivo)) {
        echo "<p style='color: green;'>✅ {$arquivo} - Encontrado</p>";
    } else {
        echo "<p style='color: red;'>❌ {$arquivo} - NÃO ENCONTRADO</p>";
    }
}

// Verificar arquivos CSS necessários
$arquivos_css = [
    'css/styles.css',
    'css/theme.css',
    'css/responsive.css'
];

echo "<h2>🎨 Verificação de Arquivos CSS</h2>";
foreach ($arquivos_css as $arquivo) {
    if (file_exists($arquivo)) {
        echo "<p style='color: green;'>✅ {$arquivo} - Encontrado</p>";
    } else {
        echo "<p style='color: red;'>❌ {$arquivo} - NÃO ENCONTRADO</p>";
    }
}

// Verificar arquivos de includes
$arquivos_includes = [
    'includes/sidebar_pages.php',
    'includes/header.php',
    'includes/config.php',
    'includes/functions.php'
];

echo "<h2>🔗 Verificação de Arquivos de Includes</h2>";
foreach ($arquivos_includes as $arquivo) {
    if (file_exists($arquivo)) {
        echo "<p style='color: green;'>✅ {$arquivo} - Encontrado</p>";
    } else {
        echo "<p style='color: red;'>❌ {$arquivo} - NÃO ENCONTRADO</p>";
    }
}

// Verificar páginas fiscais
$paginas_fiscais = [
    'fiscal/pages/nfe.php',
    'fiscal/pages/cte.php',
    'fiscal/pages/mdfe.php',
    'fiscal/pages/eventos.php'
];

echo "<h2>📄 Verificação de Páginas Fiscais</h2>";
foreach ($paginas_fiscais as $pagina) {
    if (file_exists($pagina)) {
        echo "<p style='color: green;'>✅ {$pagina} - Encontrada</p>";
        
        // Verificar se inclui os arquivos JavaScript necessários
        $conteudo = file_get_contents($pagina);
        if (strpos($conteudo, 'sidebar.js') !== false) {
            echo "<p style='color: green;'>  ✅ Inclui sidebar.js</p>";
        } else {
            echo "<p style='color: red;'>  ❌ NÃO inclui sidebar.js</p>";
        }
        
        if (strpos($conteudo, 'theme.js') !== false) {
            echo "<p style='color: green;'>  ✅ Inclui theme.js</p>";
        } else {
            echo "<p style='color: red;'>  ❌ NÃO inclui theme.js</p>";
        }
        
        if (strpos($conteudo, 'sidebar_pages.php') !== false) {
            echo "<p style='color: green;'>  ✅ Inclui sidebar_pages.php</p>";
        } else {
            echo "<p style='color: red;'>  ❌ NÃO inclui sidebar_pages.php</p>";
        }
        
        if (strpos($conteudo, 'header.php') !== false) {
            echo "<p style='color: green;'>  ✅ Inclui header.php</p>";
        } else {
            echo "<p style='color: red;'>  ❌ NÃO inclui header.php</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ {$pagina} - NÃO ENCONTRADA</p>";
    }
}

echo "<hr>";
echo "<h2>🔗 Links de Teste</h2>";
echo "<ul>";
echo "<li><a href='fiscal/pages/nfe.php' target='_blank'>📄 Testar NF-e (com sidebar e tema)</a></li>";
echo "<li><a href='fiscal/pages/cte.php' target='_blank'>📄 Testar CT-e (com sidebar e tema)</a></li>";
echo "<li><a href='fiscal/pages/mdfe.php' target='_blank'>📄 Testar MDF-e (com sidebar e tema)</a></li>";
echo "<li><a href='fiscal/pages/eventos.php' target='_blank'>📄 Testar Eventos (com sidebar e tema)</a></li>";
echo "</ul>";

echo "<h2>✅ Teste Concluído</h2>";
echo "<p><strong>Próximo passo:</strong> Teste as páginas fiscais no navegador!</p>";
echo "<p><strong>Dica:</strong> Agora o sidebar e o modo claro/escuro devem funcionar corretamente.</p>";
echo "<p><strong>Verifique:</strong></p>";
echo "<ul>";
echo "<li>✅ Menu lateral clicável e funcional</li>";
echo "<li>✅ Botão de tema (claro/escuro) funcionando</li>";
echo "<li>✅ Dropdowns do menu funcionando</li>";
echo "<li>✅ Layout igual às outras páginas do sistema</li>";
echo "</ul>";
?>
