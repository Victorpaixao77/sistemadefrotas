<?php
// Incluir arquivos de configuração e funções
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Iniciar a sessão, se ainda não estiver iniciada
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Registrar log de logout antes de limpar a sessão
if (isset($_SESSION['usuario_id']) && isset($_SESSION['empresa_id'])) {
    registrarLogAcesso($_SESSION['usuario_id'], $_SESSION['empresa_id'], 'logout', 'sucesso', 'Logout realizado pelo usuário');
}

// Limpar todas as variáveis de sessão
$_SESSION = [];

// Remover o cookie da sessão (se existir)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Limpar o cookie "Lembre-me" se existir (apenas o de login automático)
if (isset($_COOKIE['remember_user'])) {
    setcookie("remember_user", "", time() - 3600, "/", "", false, true);
}

// NÃO limpar o cookie 'saved_login' - ele deve permanecer para restaurar o formulário

// Destruir a sessão
session_destroy();

// Limpar qualquer saída pendente
while (ob_get_level()) {
    ob_end_clean();
}

// Evitar cache no navegador
header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirecionar para a página de login com parâmetro de logout
header("Location: /sistema-frotas/login.php?logout=1");
exit();
?>
