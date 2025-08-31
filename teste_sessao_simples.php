<?php
/**
 * 🧪 TESTE DE SESSÃO SIMPLES
 * 📋 Verificar se a sessão está funcionando corretamente
 */

echo "<h1>🧪 Teste de Sessão Simples</h1>";

// Iniciar sessão
session_start();

echo "<h2>🔑 Estado da Sessão</h2>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Name:</strong> " . session_name() . "</p>";
echo "<p><strong>Session Status:</strong> " . session_status() . "</p>";

// Verificar se há dados na sessão
echo "<h2>📊 Dados da Sessão</h2>";
if (empty($_SESSION)) {
    echo "<p style='color: orange;'>⚠️ Sessão vazia</p>";
} else {
    echo "<p style='color: green;'>✅ Sessão contém dados:</p>";
    echo "<pre>" . print_r($_SESSION, true) . "</pre>";
}

// Definir dados de teste
echo "<h2>✏️ Definindo Dados de Teste</h2>";
$_SESSION['user_id'] = 1;
$_SESSION['empresa_id'] = 1;
$_SESSION['loggedin'] = true;

echo "<p style='color: green;'>✅ Dados definidos na sessão</p>";

// Verificar novamente
echo "<h2>📊 Dados da Sessão (Após Definição)</h2>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

// Verificar se a função is_logged_in funciona
echo "<h2>🔍 Testando Função is_logged_in</h2>";

// Incluir as funções
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (function_exists('is_logged_in')) {
    $resultado = is_logged_in();
    echo "<p><strong>is_logged_in():</strong> " . ($resultado ? 'true' : 'false') . "</p>";
    
    if ($resultado) {
        echo "<p style='color: green;'>✅ Usuário está logado</p>";
    } else {
        echo "<p style='color: red;'>❌ Usuário NÃO está logado</p>";
        
        // Verificar por que
        echo "<h3>🔍 Análise da Função</h3>";
        echo "<p><strong>SESSION['loggedin']:</strong> " . ($_SESSION['loggedin'] ?? 'NÃO DEFINIDO') . "</p>";
        echo "<p><strong>SESSION['empresa_id']:</strong> " . ($_SESSION['empresa_id'] ?? 'NÃO DEFINIDO') . "</p>";
        echo "<p><strong>SESSION['user_id']:</strong> " . ($_SESSION['user_id'] ?? 'NÃO DEFINIDO') . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Função is_logged_in não existe</p>";
}

// Verificar se a função require_authentication funciona
echo "<h2>🔍 Testando Função require_authentication</h2>";

if (function_exists('require_authentication')) {
    echo "<p style='color: green;'>✅ Função require_authentication existe</p>";
    
    // Capturar output
    ob_start();
    
    try {
        require_authentication();
        $output = ob_get_contents();
        ob_end_clean();
        
        if (empty($output)) {
            echo "<p style='color: green;'>✅ require_authentication passou (sem erro)</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ require_authentication retornou output:</p>";
            echo "<pre>" . htmlspecialchars($output) . "</pre>";
        }
    } catch (Exception $e) {
        ob_end_clean();
        echo "<p style='color: red;'>❌ Erro ao executar require_authentication: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Função require_authentication não existe</p>";
}

echo "<h2>📝 Resumo</h2>";
echo "<p>Se a sessão está funcionando corretamente, o problema pode estar em:</p>";
echo "<ul>";
echo "<li>Cookie de sessão não sendo enviado na requisição AJAX</li>";
echo "<li>Problema de CORS ou domínio</li>";
echo "<li>Problema na função require_authentication</li>";
echo "</ul>";

echo "<p><strong>Próximo passo:</strong> Testar a página no navegador e verificar o console para erros JavaScript.</p>";
?>
