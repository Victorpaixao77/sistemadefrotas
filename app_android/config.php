<?php
/**
 * Configuração da API Android - Motoristas
 * Reutiliza conexão e regras do sistema principal.
 */

if (php_sapi_name() === 'cli') {
    return;
}

// Evitar saída antes do JSON
if (ob_get_length()) {
    ob_clean();
}
header('Content-Type: application/json; charset=utf-8');

// Raiz do projeto (uma pasta acima de app_android)
$root = dirname(__DIR__);

require_once $root . '/includes/config.php';
require_once $root . '/includes/db_connect.php';

// Helpers de resposta
function api_success($data = null, $message = 'OK') {
    $out = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $out['data'] = $data;
    }
    echo json_encode($out);
    exit;
}

function api_error($message = 'Erro', $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

/**
 * Obtém o token do header Authorization ou X-Authorization (fallback quando o Apache remove Authorization).
 * Aceita também ?token= na query string (fallback para alguns servidores).
 */
function get_bearer_token() {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (empty($header) && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower($name) === 'authorization') {
                $header = $value;
                break;
            }
        }
    }
    if (empty($header)) {
        $header = $_SERVER['HTTP_X_AUTHORIZATION'] ?? '';
    }
    if (preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
        return $m[1];
    }
    // Fallback: token na query string (alguns Apache removem Authorization)
    $tokenQuery = $_GET['token'] ?? '';
    if (is_string($tokenQuery) && preg_match('/^[a-f0-9]{64}$/i', trim($tokenQuery))) {
        return trim($tokenQuery);
    }
    return null;
}

/**
 * Valida o token e preenche motorista_id e empresa_id no escopo global.
 * Encerra com 401 JSON se token inválido ou expirado.
 */
function require_motorista_token() {
    $token = get_bearer_token();
    if (empty($token)) {
        api_error('Token não informado', 401);
    }

    try {
        $conn = getConnection();
        $stmt = $conn->prepare('
            SELECT motorista_id, empresa_id
            FROM api_tokens_motorista
            WHERE token = :token AND expira_em > NOW()
        ');
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            api_error('Sessão expirada. Por favor, faça login novamente.', 401);
        }

        $GLOBALS['motorista_id'] = (int) $row['motorista_id'];
        $GLOBALS['empresa_id'] = (int) $row['empresa_id'];
    } catch (PDOException $e) {
        error_log('API token: ' . $e->getMessage());
        api_error('Erro ao validar token', 500);
    }
}

function get_motorista_id() {
    return isset($GLOBALS['motorista_id']) ? $GLOBALS['motorista_id'] : null;
}

function get_empresa_id() {
    return isset($GLOBALS['empresa_id']) ? $GLOBALS['empresa_id'] : null;
}
