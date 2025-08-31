<?php
// Configuração da aplicação

// Configuração da sessão
function configure_session() {
    // Configurações básicas da sessão
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_secure', 0); // Define como 0 para HTTP
    ini_set('session.gc_maxlifetime', 86400); // 24 horas
    ini_set('session.cookie_lifetime', 86400); // 24 horas
    
    // Define o nome da sessão
    session_name('sistema_frotas_session');
    
    // Define parâmetros do cookie da sessão
    session_set_cookie_params([
        'lifetime' => 86400,          // 24 horas
        'path' => '/sistema-frotas',  // Caminho da aplicação
        'domain' => '',
        'secure' => false,            // Define como false para HTTP
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// Configura a sessão antes de qualquer coisa
configure_session();

// Previne qualquer saída antes da resposta JSON pretendida
ob_start();

// Database Configuration
define('DB_SERVER', 'localhost:3307');  // Servidor e porta
define('DB_USERNAME', 'root');          // Usuário do XAMPP
define('DB_PASSWORD', '');              // Senha vazia para XAMPP
define('DB_NAME', 'sistema_frotas');    // Nome do banco de dados

// Configuração da Aplicação
define('APP_NAME', 'Sistema de Gestão de Frotas');
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'America/Sao_Paulo');

// Define o fuso horário padrão
date_default_timezone_set(TIMEZONE);

// Modo de debug (definir como false em produção)
define('DEBUG_MODE', true);

// Configuração de logs
ini_set('log_errors', 1);
// Corrigindo o caminho do error_log para usar caminho absoluto
$log_path = __DIR__ . '/../logs/php_errors.log';
ini_set('error_log', $log_path);
error_reporting(E_ALL);
ini_set('display_errors', 0); // Desativa a exibição de erros na saída

// Garantir que o diretório de logs existe
$log_dir = dirname($log_path);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Função para retornar resposta JSON
function json_response($data, $status = 200) {
    http_response_code($status);
        header('Content-Type: application/json');
    ob_clean(); // Limpa qualquer saída anterior
    echo json_encode($data);
        exit;
}

// Função para verificar se o usuário está logado
function is_logged_in() {
    if (session_status() === PHP_SESSION_NONE) {
        configure_session();
        session_start();
    }
    
    if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
        return false;
    }
    
    if (!isset($_SESSION["empresa_id"])) {
        return false;
    }
    
    return true;
}

// Função para requerer autenticação
function require_authentication() {
    if (!is_logged_in()) {
        // Armazena a URL atual para redirecionamento após o login
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        
        if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
            // Se for uma chamada da API, retorna 401
            http_response_code(401);
            echo json_encode(['error' => 'Acesso não autorizado']);
            exit;
        } else {
            // Se for uma requisição de página, redireciona para o login
            header("location: /sistema-frotas/login.php");
            exit;
        }
    }
}
