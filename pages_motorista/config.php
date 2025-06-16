<?php
// Configurações de sessão
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.cookie_lifetime', 3600);
ini_set('session.cookie_secure', 0);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

// Define o nome da sessão
session_name('sistema_frotas_session');

// Define parâmetros do cookie da sessão
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/sistema-frotas',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Inicia a sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log da sessão para debug
error_log("=== Início da sessão ===");
error_log("Session ID: " . session_id());
error_log("Session Name: " . session_name());
error_log("Session Status: " . session_status());
error_log("Session Data: " . print_r($_SESSION, true));
error_log("HTTP_REFERER: " . ($_SERVER['HTTP_REFERER'] ?? 'não definido'));
error_log("HTTP_COOKIE: " . ($_SERVER['HTTP_COOKIE'] ?? 'não definido'));

// Configurações do módulo
define('MOTORISTA_MODULE_ENABLED', true);
define('MOTORISTA_SESSION_LIFETIME', 3600);
define('MOTORISTA_COOKIE_NAME', 'motorista_session');

// Inclui arquivo de conexão com o banco de dados
require_once 'db.php';

// Funções de validação
function validar_sessao_motorista() {
    error_log("=== Validação de sessão ===");
    error_log("Session ID: " . session_id());
    error_log("Session Name: " . session_name());
    error_log("Session Status: " . session_status());
    error_log("Session Data: " . print_r($_SESSION, true));
    error_log("HTTP_REFERER: " . ($_SERVER['HTTP_REFERER'] ?? 'não definido'));
    error_log("HTTP_COOKIE: " . ($_SERVER['HTTP_COOKIE'] ?? 'não definido'));
    
    if (!isset($_SESSION['motorista_id']) || !isset($_SESSION['empresa_id'])) {
        error_log("Sessão inválida - Dados da sessão:");
        error_log("motorista_id: " . (isset($_SESSION['motorista_id']) ? $_SESSION['motorista_id'] : 'não definido'));
        error_log("empresa_id: " . (isset($_SESSION['empresa_id']) ? $_SESSION['empresa_id'] : 'não definido'));
        error_log("tipo_usuario: " . (isset($_SESSION['tipo_usuario']) ? $_SESSION['tipo_usuario'] : 'não definido'));
        
        if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
            // Se for uma chamada da API, retorna 401
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Sessão inválida ou expirada']);
            exit;
        } else {
            // Se for uma requisição de página, redireciona para o login
            header('Location: login.php');
            exit;
        }
    }
}

function validar_empresa_id($empresa_id) {
    if (!is_numeric($empresa_id) || $empresa_id <= 0) {
        return false;
    }
    return true;
}

// Funções de formatação
function formatar_moeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function formatar_data($data) {
    return date('d/m/Y', strtotime($data));
}

function formatar_data_hora($data) {
    return date('d/m/Y H:i', strtotime($data));
}

// Funções de segurança
function sanitizar_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function gerar_token_csrf() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validar_token_csrf($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

// Funções de resposta
function json_response($data, $status = 200) {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function json_success($data = null, $message = 'Operação realizada com sucesso') {
    json_response([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

function json_error($message = 'Erro ao processar a requisição', $status = 400) {
    json_response([
        'success' => false,
        'message' => $message
    ], $status);
}

// Funções de autenticação
function login_motorista($nome, $senha) {
    try {
        $conn = getConnection();
        $stmt = $conn->prepare('
            SELECT id, nome, empresa_id, motorista_id, status, senha 
            FROM usuarios_motoristas 
            WHERE nome = :nome 
            AND status = "ativo"
        ');
        
        $stmt->bindParam(':nome', $nome);
        $stmt->execute();
        
        $motorista = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($motorista && password_verify($senha, $motorista['senha'])) {
            $_SESSION['motorista_id'] = $motorista['motorista_id'];
            $_SESSION['motorista_nome'] = $motorista['nome'];
            $_SESSION['empresa_id'] = $motorista['empresa_id'];
            $_SESSION['tipo_usuario'] = 'motorista';
            $_SESSION['last_activity'] = time();
            
            // Log do login bem-sucedido
            error_log("=== Login bem-sucedido ===");
            error_log("Session ID: " . session_id());
            error_log("Session Name: " . session_name());
            error_log("Session Status: " . session_status());
            error_log("Session Data: " . print_r($_SESSION, true));
            
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log('Erro ao fazer login: ' . $e->getMessage());
        return false;
    }
}

function logout_motorista() {
    // Log antes do logout
    error_log("=== Logout ===");
    error_log("Session ID: " . session_id());
    error_log("Session Name: " . session_name());
    error_log("Session Status: " . session_status());
    error_log("Session Data: " . print_r($_SESSION, true));
    
    session_unset();
    session_destroy();
    
    // Limpa o cookie da sessão
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/sistema-frotas');
    }
    
    header('Location: login.php');
    exit;
}

// Função para verificar timeout da sessão
function verificar_timeout_sessao() {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > MOTORISTA_SESSION_LIFETIME)) {
        logout_motorista();
    }
    $_SESSION['last_activity'] = time();
} 