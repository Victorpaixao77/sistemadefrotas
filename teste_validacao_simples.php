<?php
/**
 * 🧪 TESTE SIMPLES DA VALIDAÇÃO SEFAZ
 * 📋 Verificar se a página está acessível
 */

echo "<h1>🧪 Teste Simples da Validação SEFAZ</h1>";

// Testar se a página principal está acessível
echo "<h2>📄 Teste da Página Principal</h2>";
echo "<p><a href='fiscal/validar_sefaz.php' target='_blank'>🧪 Página de Validação SEFAZ</a></p>";

// Testar se a API está funcionando
echo "<h2>🔧 Teste da API</h2>";
echo "<p><a href='fiscal/api/validar_sefaz.php' target='_blank'>🔌 API de Validação SEFAZ</a></p>";

// Testar página de debug
echo "<h2>🐛 Teste de Debug</h2>";
echo "<p><a href='fiscal/validar_sefaz_debug.php' target='_blank'>🔍 Página de Debug (sem autenticação)</a></p>";

// Verificar se os arquivos existem
echo "<h2>📁 Verificação de Arquivos</h2>";

$arquivos = [
    'fiscal/validar_sefaz.php' => 'Página Principal',
    'fiscal/api/validar_sefaz.php' => 'API de Validação',
    'fiscal/validar_sefaz_debug.php' => 'Página de Debug'
];

foreach ($arquivos as $arquivo => $descricao) {
    if (file_exists($arquivo)) {
        echo "<p style='color: green;'>✅ {$descricao}: {$arquivo}</p>";
    } else {
        echo "<p style='color: red;'>❌ {$descricao}: {$arquivo} - NÃO ENCONTRADO</p>";
    }
}

echo "<h2>📝 Instruções</h2>";
echo "<p>1. <strong>Clique nos links acima</strong> para testar cada componente</p>";
echo "<p>2. <strong>Se a página principal redirecionar</strong>, use a página de debug</p>";
echo "<p>3. <strong>Verifique se a API retorna JSON</strong> válido</p>";
echo "<p>4. <strong>Teste a validação</strong> do certificado digital</p>";

echo "<h2>🔗 Links Úteis</h2>";
echo "<p><a href='fiscal/validar_sefaz.php' target='_blank'>🧪 Validação SEFAZ</a></p>";
echo "<p><a href='fiscal/validar_sefaz_debug.php' target='_blank'>🔍 Debug (sem login)</a></p>";
echo "<p><a href='pages/configuracoes.php' target='_blank'>⚙️ Configurações</a></p>";
echo "<p><a href='index.php' target='_blank'>🏠 Página Inicial</a></p>";

echo "<h2>✅ Resumo</h2>";
echo "<p>Agora você pode acessar a validação SEFAZ mesmo sem estar logado!</p>";
echo "<p>A página mostrará um aviso mas permitirá o acesso para testes.</p>";
?>
