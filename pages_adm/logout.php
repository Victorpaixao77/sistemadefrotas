<?php
session_start();

// Registrar logout no log
if (isset($_SESSION['admin_id'])) {
    $log_file = '../logs/admin_login.log';
    $log_message = date('Y-m-d H:i:s') . " - Admin ID: {$_SESSION['admin_id']} - Email: {$_SESSION['admin_email']} - IP: {$_SERVER['REMOTE_ADDR']} - Logout\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// Destruir todas as variáveis de sessão
$_SESSION = array();

// Destruir o cookie da sessão
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destruir a sessão
session_destroy();

// Redirecionar para a página de login
header('Location: login.php');
exit; 