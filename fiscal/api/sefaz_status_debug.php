<?php
/**
 * 🐛 API Status SEFAZ - Debug
 * 📋 Versão simplificada para testar autenticação
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Configurar sessão
configure_session();
session_start();

// Debug: Mostrar todas as variáveis de sessão
$debug_info = [
    'session_status' => session_status(),
    'session_id' => session_id(),
    'session_data' => $_SESSION,
    'cookies' => $_COOKIE,
    'server' => [
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'N/A',
        'HTTP_REFERER' => $_SERVER['HTTP_REFERER'] ?? 'N/A',
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'N/A'
    ]
];

// Verificar autenticação
$auth_status = 'unknown';
$auth_message = '';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $auth_status = 'no_session';
    $auth_message = 'Sessão não ativa';
} elseif (!isset($_SESSION['empresa_id'])) {
    $auth_status = 'no_empresa_id';
    $auth_message = 'empresa_id não encontrado na sessão';
} elseif (!isset($_SESSION['loggedin'])) {
    $auth_status = 'no_loggedin';
    $auth_message = 'loggedin não encontrado na sessão';
} elseif ($_SESSION['loggedin'] !== true) {
    $auth_status = 'not_logged_in';
    $auth_message = 'Usuário não está logado';
} else {
    $auth_status = 'authenticated';
    $auth_message = 'Usuário autenticado com sucesso';
}

// Retornar informações de debug
echo json_encode([
    'success' => ($auth_status === 'authenticated'),
    'auth_status' => $auth_status,
    'auth_message' => $auth_message,
    'debug_info' => $debug_info,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
