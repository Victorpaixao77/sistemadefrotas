<?php
/**
 * 🧪 TESTE DA PÁGINA REAL
 * 📋 Verificar se a página de configurações está funcionando
 */

echo "<h1>🧪 Teste da Página Real</h2>";

echo "<h2>🔍 Verificar Arquivos</h2>";

// Verificar se a página existe
$pagina_config = 'pages/configuracoes.php';
if (file_exists($pagina_config)) {
    echo "<p style='color: green;'>✅ Página configuracoes.php existe</p>";
} else {
    echo "<p style='color: red;'>❌ Página configuracoes.php NÃO existe</p>";
    exit;
}

// Verificar se a API existe
$api_config = 'api/configuracoes.php';
if (file_exists($api_config)) {
    echo "<p style='color: green;'>✅ API configuracoes.php existe</p>";
} else {
    echo "<p style='color: red;'>❌ API configuracoes.php NÃO existe</p>";
    exit;
}

// Verificar se os includes existem
$includes = [
    'includes/config.php',
    'includes/db_connect.php',
    'includes/functions.php'
];

foreach ($includes as $include) {
    if (file_exists($include)) {
        echo "<p style='color: green;'>✅ {$include} existe</p>";
    } else {
        echo "<p style='color: red;'>❌ {$include} NÃO existe</p>";
    }
}

echo "<h2>📋 Conteúdo da Página</h2>";

// Ler o início da página para verificar includes
$conteudo = file_get_contents($pagina_config);
$primeiras_linhas = array_slice(explode("\n", $conteudo), 0, 20);

echo "<h3>Primeiras 20 linhas:</h3>";
echo "<pre>";
foreach ($primeiras_linhas as $i => $linha) {
    echo ($i + 1) . ": " . htmlspecialchars($linha) . "\n";
}
echo "</pre>";

echo "<h2>📋 Conteúdo da API</h2>";

// Ler o início da API para verificar includes
$conteudo_api = file_get_contents($api_config);
$primeiras_linhas_api = array_slice(explode("\n", $conteudo_api), 0, 20);

echo "<h3>Primeiras 20 linhas da API:</h3>";
echo "<pre>";
foreach ($primeiras_linhas_api as $i => $linha) {
    echo ($i + 1) . ": " . htmlspecialchars($linha) . "\n";
}
echo "</pre>";

echo "<h2>🔍 Verificar JavaScript</h2>";

// Verificar se há JavaScript na página
if (strpos($conteudo, 'saveConfiguracoesFiscais') !== false) {
    echo "<p style='color: green;'>✅ Função saveConfiguracoesFiscais encontrada na página</p>";
} else {
    echo "<p style='color: red;'>❌ Função saveConfiguracoesFiscais NÃO encontrada na página</p>";
}

if (strpos($conteudo, 'fetch') !== false) {
    echo "<p style='color: green;'>✅ Fetch API encontrada na página</p>";
} else {
    echo "<p style='color: red;'>❌ Fetch API NÃO encontrada na página</p>";
}

echo "<h2>📝 Próximos Passos</h2>";
echo "<p>1. Abrir a página no navegador: <a href='http://localhost/sistema-frotas/pages/configuracoes.php' target='_blank'>http://localhost/sistema-frotas/pages/configuracoes.php</a></p>";
echo "<p>2. Preencher o formulário 'Ambiente do Sistema Fiscal'</p>";
echo "<p>3. Clicar em Salvar</p>";
echo "<p>4. Verificar o console do navegador para erros</p>";
echo "<p>5. Verificar a aba Network para ver a requisição</p>";
?>
