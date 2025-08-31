<?php
/**
 * 🧪 PÁGINA DE DEBUG - VALIDAÇÃO SEFAZ
 * 📋 Versão sem autenticação para debug
 */

// Iniciar sessão
session_start();

// Debug da sessão
echo "<h1>🧪 Debug da Validação SEFAZ</h1>";
echo "<h2>📊 Informações da Sessão</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>🔍 Status da Autenticação</h2>";

// Verificar se está logado
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    echo "<p style='color: green;'>✅ Sessão 'loggedin' = true</p>";
} else {
    echo "<p style='color: red;'>❌ Sessão 'loggedin' = " . ($_SESSION['loggedin'] ?? 'NÃO DEFINIDA') . "</p>";
}

if (isset($_SESSION['empresa_id']) && !empty($_SESSION['empresa_id'])) {
    echo "<p style='color: green;'>✅ Sessão 'empresa_id' = " . $_SESSION['empresa_id'] . "</p>";
} else {
    echo "<p style='color: red;'>❌ Sessão 'empresa_id' = " . ($_SESSION['empresa_id'] ?? 'NÃO DEFINIDA') . "</p>";
}

if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    echo "<p style='color: green;'>✅ Sessão 'user_id' = " . $_SESSION['user_id'] . "</p>";
} else {
    echo "<p style='color: red;'>❌ Sessão 'user_id' = " . ($_SESSION['user_id'] ?? 'NÃO DEFINIDA') . "</p>";
}

// Verificar se os includes funcionam
echo "<h2>📁 Teste de Includes</h2>";

try {
    require_once '../includes/config.php';
    echo "<p style='color: green;'>✅ includes/config.php carregado</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro ao carregar config.php: " . $e->getMessage() . "</p>";
}

try {
    require_once '../includes/db_connect.php';
    echo "<p style='color: green;'>✅ includes/db_connect.php carregado</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro ao carregar db_connect.php: " . $e->getMessage() . "</p>";
}

try {
    require_once '../includes/functions.php';
    echo "<p style='color: green;'>✅ includes/functions.php carregado</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro ao carregar functions.php: " . $e->getMessage() . "</p>";
}

// Testar função isLoggedIn
echo "<h2>🔐 Teste da Função isLoggedIn()</h2>";

if (function_exists('isLoggedIn')) {
    $resultado = isLoggedIn();
    echo "<p>Função isLoggedIn() retornou: " . ($resultado ? 'true' : 'false') . "</p>";
    
    if (!$resultado) {
        echo "<p><strong>Motivos possíveis:</strong></p>";
        echo "<ul>";
        if (!isset($_SESSION['loggedin'])) {
            echo "<li>❌ Sessão 'loggedin' não existe</li>";
        } elseif ($_SESSION['loggedin'] !== true) {
            echo "<li>❌ Sessão 'loggedin' não é true</li>";
        }
        if (!isset($_SESSION['empresa_id'])) {
            echo "<li>❌ Sessão 'empresa_id' não existe</li>";
        } elseif (empty($_SESSION['empresa_id'])) {
            echo "<li>❌ Sessão 'empresa_id' está vazia</li>";
        }
        echo "</ul>";
    }
} else {
    echo "<p style='color: red;'>❌ Função isLoggedIn() não encontrada</p>";
}

// Testar conexão com banco
echo "<h2>🗄️ Teste de Conexão com Banco</h2>";

if (function_exists('getConnection')) {
    try {
        $conn = getConnection();
        echo "<p style='color: green;'>✅ Conexão com banco estabelecida</p>";
        
        // Verificar se há certificado
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM fiscal_certificados_digitais WHERE ativo = 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<p><strong>Certificados ativos:</strong> " . $result['total'] . "</p>";
        
        // Verificar configuração fiscal
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM fiscal_config_empresa");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<p><strong>Configurações fiscais:</strong> " . $result['total'] . "</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erro na conexão com banco: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Função getConnection() não encontrada</p>";
}

// Links para teste
echo "<h2>🔗 Links para Teste</h2>";
echo "<p><a href='validar_sefaz.php' target='_blank'>🧪 Página Original (com autenticação)</a></p>";
echo "<p><a href='../pages/configuracoes.php' target='_blank'>⚙️ Configurações</a></p>";
echo "<p><a href='../index.php' target='_blank'>🏠 Página Inicial</a></p>";

// Formulário para simular login
echo "<h2>🔑 Simular Login</h2>";
echo "<form method='post' action=''>";
echo "<p><strong>Definir empresa_id:</strong> <input type='number' name='empresa_id' value='1' min='1'></p>";
echo "<p><strong>Definir loggedin:</strong> <input type='checkbox' name='loggedin' value='1' checked></p>";
echo "<p><strong>Definir user_id:</strong> <input type='number' name='user_id' value='1' min='1'></p>";
echo "<input type='submit' name='simular_login' value='Simular Login' class='btn btn-primary'>";
echo "</form>";

// Processar simulação de login
if (isset($_POST['simular_login'])) {
    $_SESSION['empresa_id'] = $_POST['empresa_id'];
    $_SESSION['loggedin'] = isset($_POST['loggedin']);
    $_SESSION['user_id'] = $_POST['user_id'];
    
    echo "<p style='color: green;'>✅ Sessão atualizada! Recarregue a página para ver as mudanças.</p>";
    echo "<script>setTimeout(() => location.reload(), 2000);</script>";
}

echo "<h2>📝 Como Resolver</h2>";
echo "<p>1. <strong>Faça login</strong> na página inicial do sistema</p>";
echo "<p>2. <strong>Verifique</strong> se as sessões estão sendo criadas corretamente</p>";
echo "<p>3. <strong>Teste</strong> a página original após o login</p>";
echo "<p>4. <strong>Use esta página de debug</strong> para verificar o status da sessão</p>";

echo "<h2>✅ Resumo</h2>";
echo "<p>Se você está sendo redirecionado para index.php, significa que:</p>";
echo "<ul>";
echo "<li>A função isLoggedIn() está retornando false</li>";
echo "<li>As sessões necessárias não estão definidas</li>";
echo "<li>Você precisa fazer login primeiro</li>";
echo "</ul>";
?>
