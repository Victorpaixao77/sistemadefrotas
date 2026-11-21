<?php
// Configuração da aplicação
require_once __DIR__ . '/sf_paths.php';

// Configuração da sessão
function configure_session() {
    // Só configura se a sessão não estiver ativa
    if (session_status() === PHP_SESSION_NONE) {
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
        $cookieSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
        session_set_cookie_params([
            'lifetime' => 86400,          // 24 horas
            'path' => sf_session_cookie_path(),
            'domain' => '',
            'secure' => $cookieSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}

// Configura a sessão antes de qualquer coisa
configure_session();

// Previne qualquer saída antes da resposta JSON pretendida
ob_start();

// Database Configuration
// Prioriza variáveis de ambiente (produção); cai no padrão do XAMPP local se ausentes.
// Em produção defina: SF_DB_HOST, SF_DB_USER, SF_DB_PASS, SF_DB_NAME.
$sf_db_host = getenv('SF_DB_HOST');
$sf_db_user = getenv('SF_DB_USER');
$sf_db_pass = getenv('SF_DB_PASS');
$sf_db_name = getenv('SF_DB_NAME');
define('DB_SERVER', ($sf_db_host !== false && $sf_db_host !== '') ? $sf_db_host : 'localhost:3307');
define('DB_USERNAME', ($sf_db_user !== false && $sf_db_user !== '') ? $sf_db_user : 'root');
define('DB_PASSWORD', ($sf_db_pass !== false) ? $sf_db_pass : '');
define('DB_NAME', ($sf_db_name !== false && $sf_db_name !== '') ? $sf_db_name : 'sistema_frotas');

// Configuração da Aplicação
define('APP_NAME', 'Sistema de Gestão de Frotas');
define('APP_VERSION', '1.0.1');
/** Site institucional (ex.: “Voltar ao site” no login). Sobrescrito por SF_MARKETING_SITE_URL no servidor. */
if (!defined('SF_MARKETING_SITE')) {
    define('SF_MARKETING_SITE', 'https://frotec.online');
}
define('TIMEZONE', 'America/Sao_Paulo');

/**
 * Chave OpenAI para leitura de fichas de viagem (OCR por visão).
 * Preferir variável de ambiente SF_OPENAI_API_KEY (não versionar a chave).
 * Opcional: constante SF_OPENAI_API_KEY em um arquivo local não versionado.
 */
if (!function_exists('sf_openai_api_key')) {
    function sf_openai_api_key(): string
    {
        $env = getenv('SF_OPENAI_API_KEY');
        if (is_string($env) && trim($env) !== '') {
            return trim($env);
        }
        if (defined('SF_OPENAI_API_KEY') && is_string(SF_OPENAI_API_KEY) && SF_OPENAI_API_KEY !== '') {
            return trim(SF_OPENAI_API_KEY);
        }
        return '';
    }
}

/** Modelo visão (chat/completions). Ex.: gpt-4o-mini, gpt-4o */
if (!function_exists('sf_openai_vision_model')) {
    function sf_openai_vision_model(): string
    {
        $m = getenv('SF_OPENAI_VISION_MODEL');
        if (is_string($m) && trim($m) !== '') {
            return trim($m);
        }
        if (defined('SF_OPENAI_VISION_MODEL') && is_string(SF_OPENAI_VISION_MODEL) && SF_OPENAI_VISION_MODEL !== '') {
            return trim(SF_OPENAI_VISION_MODEL);
        }
        return 'gpt-4o-mini';
    }
}

/**
 * Detalhe da imagem em Vision API: low | auto | high.
 * "high" gasta muitos tokens (TPM) e estoura 429 em contas com limite baixo; padrão "auto".
 * Sobrescrever: SF_OPENAI_IMAGE_DETAIL=high no ambiente (ou constante SF_OPENAI_IMAGE_DETAIL).
 */
if (!function_exists('sf_openai_vision_image_detail')) {
    function sf_openai_vision_image_detail(): string
    {
        $d = getenv('SF_OPENAI_IMAGE_DETAIL');
        if (is_string($d)) {
            $d = strtolower(trim($d));
            if (in_array($d, ['low', 'high', 'auto'], true)) {
                return $d;
            }
        }
        if (defined('SF_OPENAI_IMAGE_DETAIL') && is_string(SF_OPENAI_IMAGE_DETAIL)) {
            $d = strtolower(trim(SF_OPENAI_IMAGE_DETAIL));
            if (in_array($d, ['low', 'high', 'auto'], true)) {
                return $d;
            }
        }

        return 'auto';
    }
}

/**
 * Chave API DeepSeek (leitura de ficha de abastecimento por visão).
 * Variáveis: SF_DEEPSEEK_API_KEY ou DEEPSEEK_API_KEY; opcional SF_DEEPSEEK_BASE_URL (padrão https://api.deepseek.com).
 */
if (!function_exists('sf_deepseek_api_key')) {
    function sf_deepseek_api_key(): string
    {
        foreach (['SF_DEEPSEEK_API_KEY', 'DEEPSEEK_API_KEY'] as $name) {
            $v = getenv($name);
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }
        if (defined('SF_DEEPSEEK_API_KEY') && is_string(SF_DEEPSEEK_API_KEY) && SF_DEEPSEEK_API_KEY !== '') {
            return trim(SF_DEEPSEEK_API_KEY);
        }

        return '';
    }
}

if (!function_exists('sf_deepseek_api_base')) {
    function sf_deepseek_api_base(): string
    {
        $b = getenv('SF_DEEPSEEK_BASE_URL');
        if (!is_string($b) || trim($b) === '') {
            return 'https://api.deepseek.com';
        }

        return rtrim(trim($b), '/');
    }
}

if (!function_exists('sf_deepseek_chat_completions_url')) {
    function sf_deepseek_chat_completions_url(): string
    {
        $base = sf_deepseek_api_base();
        $suffix = '/v1/chat/completions';
        $len = strlen($base);
        if ($len >= 3 && substr($base, -3) === '/v1') {
            return $base . '/chat/completions';
        }

        return $base . $suffix;
    }
}

/** Modelo DeepSeek para visão/JSON (padrão deepseek-chat). */
if (!function_exists('sf_deepseek_refuel_model')) {
    function sf_deepseek_refuel_model(): string
    {
        $m = getenv('SF_DEEPSEEK_VISION_MODEL');
        if (is_string($m) && trim($m) !== '') {
            return trim($m);
        }

        return 'deepseek-chat';
    }
}

// Define o fuso horário padrão
date_default_timezone_set(TIMEZONE);

// Modo debug: true apenas se SF_DEBUG=1 ou SF_DEBUG=true (padrão produção: off)
define('DEBUG_MODE', in_array(strtolower((string) getenv('SF_DEBUG')), ['1', 'true', 'yes'], true));

// Configuração de logs
ini_set('log_errors', 1);
// Corrigindo o caminho do error_log para usar caminho absoluto
$log_path = __DIR__ . '/../logs/php_errors.log';
ini_set('error_log', $log_path);
if (DEBUG_MODE) {
    error_reporting(E_ALL);
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE & ~E_WARNING & ~E_USER_NOTICE & ~E_USER_WARNING);
}
ini_set('display_errors', 0);

if (!function_exists('sf_log_debug')) {
    function sf_log_debug(string $message): void
    {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log($message);
        }
    }
}

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

/**
 * Encerra a sessão por inatividade conforme a política da empresa.
 * O tempo (em minutos) fica em cache em $_SESSION['idle_timeout_min']; 0 = desativado.
 * Deve ser chamada apenas quando o usuário está autenticado.
 */
function sf_enforce_idle_timeout() {
    // Resolve o timeout (cacheado na sessão) na primeira requisição após o login.
    if (!isset($_SESSION['idle_timeout_min']) && !empty($_SESSION['empresa_id'])) {
        if (is_file(__DIR__ . '/security.php')) {
            require_once __DIR__ . '/security.php';
            $cfg = sf_security_config((int) $_SESSION['empresa_id']);
            $_SESSION['idle_timeout_min'] = (int) $cfg['idle_timeout_min'];
        } else {
            $_SESSION['idle_timeout_min'] = 0;
        }
    }

    $timeout = (int) ($_SESSION['idle_timeout_min'] ?? 0);
    if ($timeout <= 0) {
        $_SESSION['ultima_atividade'] = time();
        return;
    }

    $agora = time();
    $ultima = (int) ($_SESSION['ultima_atividade'] ?? $agora);
    if (($agora - $ultima) > ($timeout * 60)) {
        // Sessão expirada por inatividade.
        if (function_exists('registrarLogAcesso') && !empty($_SESSION['usuario_id'])) {
            @registrarLogAcesso($_SESSION['usuario_id'], $_SESSION['empresa_id'] ?? 0, 'sessao_expirada', 'sucesso', 'Logout automático por inatividade');
        }
        $_SESSION = [];
        @session_destroy();

        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
            http_response_code(401);
            echo json_encode(['error' => 'Sessão expirada por inatividade', 'session_expired' => true]);
        } else {
            header('location: ' . sf_app_url('login.php?timeout=1'));
        }
        exit;
    }

    $_SESSION['ultima_atividade'] = $agora;
}

/**
 * Libera o lock da sessão PHP após leituras/escritas iniciais.
 * Evita que navegação + APIs paralelas (notificações, sync) fiquem serializadas.
 */
function sf_release_session_lock(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

// Função para requerer autenticação
function require_authentication() {
    if (is_logged_in()) {
        sf_enforce_idle_timeout();
        if (is_file(__DIR__ . '/sf_saas.php')) {
            require_once __DIR__ . '/sf_saas.php';
            $connSaas = getConnection();
            sf_saas_enforce_session($connSaas);
            sf_saas_enforce_api_quota($connSaas);
        }
        if (!function_exists('csrf_token_get')) {
            require_once __DIR__ . '/csrf.php';
        }
        csrf_token_get();
        sf_release_session_lock();
        return;
    }
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
            header('location: ' . sf_app_url('login.php'));
            exit;
        }
    }
}

// Telemetria de performance (Control Center ADM)
if (is_file(__DIR__ . '/../pages_adm/lib/adm_perf.php')) {
    require_once __DIR__ . '/../pages_adm/lib/adm_perf.php';
    adm_perf_bootstrap();
}
