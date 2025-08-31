<?php
/**
 * 🧪 TESTE DE AUTENTICAÇÃO FISCAL
 * 📋 Verificar se as páginas fiscais estão autenticando corretamente
 */

echo "<h1>🧪 Teste de Autenticação Fiscal</h1>";

// Verificar se as páginas fiscais existem
$paginas_fiscais = [
    'fiscal/pages/nfe.php',
    'fiscal/pages/cte.php', 
    'fiscal/pages/mdfe.php',
    'fiscal/pages/eventos.php'
];

echo "<h2>📄 Verificação de Páginas</h2>";
foreach ($paginas_fiscais as $pagina) {
    if (file_exists($pagina)) {
        echo "<p style='color: green;'>✅ {$pagina} - Encontrada</p>";
        
        // Verificar se contém as funções de autenticação corretas
        $conteudo = file_get_contents($pagina);
        if (strpos($conteudo, 'require_authentication()') !== false) {
            echo "<p style='color: green;'>   ✅ Contém require_authentication()</p>";
        } else {
            echo "<p style='color: red;'>   ❌ Não contém require_authentication()</p>";
        }
        
        if (strpos($conteudo, 'configure_session()') !== false) {
            echo "<p style='color: green;'>   ✅ Contém configure_session()</p>";
        } else {
            echo "<p style='color: red;'>   ❌ Não contém configure_session()</p>";
        }
        
        if (strpos($conteudo, 'session_start()') !== false) {
            echo "<p style='color: green;'>   ✅ Contém session_start()</p>";
        } else {
            echo "<p style='color: red;'>   ❌ Não contém session_start()</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ {$pagina} - Não encontrada</p>";
    }
}

// Verificar se os arquivos de configuração existem
echo "<h2>⚙️ Verificação de Configuração</h2>";
$arquivos_config = [
    'includes/config.php',
    'includes/functions.php'
];

foreach ($arquivos_config as $arquivo) {
    if (file_exists($arquivo)) {
        echo "<p style='color: green;'>✅ {$arquivo} - Encontrado</p>";
    } else {
        echo "<p style='color: red;'>❌ {$arquivo} - Não encontrado</p>";
    }
}

// Verificar se a função require_authentication existe
echo "<h2>🔐 Verificação de Funções</h2>";
if (file_exists('includes/config.php')) {
    $conteudo_config = file_get_contents('includes/config.php');
    if (strpos($conteudo_config, 'function require_authentication()') !== false) {
        echo "<p style='color: green;'>✅ Função require_authentication() encontrada</p>";
    } else {
        echo "<p style='color: red;'>❌ Função require_authentication() não encontrada</p>";
    }
}

// Links de teste
echo "<h2>🔗 Links de Teste</h2>";
echo "<ul>";
echo "<li><a href='fiscal/pages/nfe.php' target='_blank'>📄 Testar NF-e</a></li>";
echo "<li><a href='fiscal/pages/cte.php' target='_blank'>📄 Testar CT-e</a></li>";
echo "<li><a href='fiscal/pages/mdfe.php' target='_blank'>📄 Testar MDF-e</a></li>";
echo "<li><a href='fiscal/pages/eventos.php' target='_blank'>📄 Testar Eventos</a></li>";
echo "<li><a href='pages/routes.php' target='_blank'>📄 Comparar com Routes (funcionando)</a></li>";
echo "</ul>";

echo "<h2>✅ Teste Concluído</h2>";
echo "<p>Verifique se as páginas fiscais não estão mais redirecionando para index.php!</p>";
?>
