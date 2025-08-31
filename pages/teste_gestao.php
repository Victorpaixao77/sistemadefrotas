<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

configure_session();
session_start();

require_authentication();

$page_title = "Teste de Gestão";

// Debug: Verificar se a sessão está funcionando
echo "<h1>Teste de Sessão</h1>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>User ID: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NÃO DEFINIDO') . "</p>";
echo "<p>Empresa ID: " . (isset($_SESSION['empresa_id']) ? $_SESSION['empresa_id'] : 'NÃO DEFINIDO') . "</p>";
echo "<p>Logged in: " . (isset($_SESSION['loggedin']) ? ($_SESSION['loggedin'] ? 'SIM' : 'NÃO') : 'NÃO DEFINIDO') . "</p>";

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['empresa_id'])) {
    echo "<p style='color: red;'>Usuário não está logado - redirecionando...</p>";
    header('Location: ../login.php');
    exit;
}

echo "<p style='color: green;'>Usuário está logado!</p>";

// Testar conexão com banco
try {
    // Usar o sistema de conexão centralizado
    $conn = getConnection();
    
    // Testar busca de veículos
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM veiculos WHERE empresa_id = ?");
    $stmt->execute([$_SESSION['empresa_id']]);
    $result = $stmt->fetch();
    echo "<p>Total de veículos: " . $result['total'] . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erro no banco: " . $e->getMessage() . "</p>";
}

echo "<h2>Teste Concluído</h2>";
echo "<p><a href='gestao_interativa.php'>Ir para Gestão Interativa</a></p>";
?> 