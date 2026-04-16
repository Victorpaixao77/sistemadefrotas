<?php
/**
 * Token CSRF simples (sessão). Use em formulários POST sensíveis.
 */

if (!function_exists('csrf_token_get')) {
    function csrf_token_get(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf_token'];
    }

    function csrf_token_validate(?string $token): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        if (!is_string($token) || $token === '') {
            return false;
        }
        $expected = $_SESSION['csrf_token'] ?? '';
        if ($expected === '') {
            return false;
        }
        return hash_equals($expected, $token);
    }

    /**
     * Valida CSRF em requisições que alteram dados (POST, PUT, PATCH, DELETE).
     * Token: campo POST csrf_token ou header HTTP X-CSRF-Token (use o header quando o corpo for JSON puro).
     */
    function api_require_csrf_json(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $mutation = ['POST', 'PUT', 'PATCH', 'DELETE'];
        if (!in_array($method, $mutation, true)) {
            return;
        }
        if (!function_exists('api_json_error')) {
            require_once __DIR__ . '/api_json.php';
        }
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!csrf_token_validate(is_string($token) ? $token : null)) {
            api_json_error(
                'Sessão expirada ou token CSRF inválido. Recarregue a página.',
                403,
                'csrf_invalid'
            );
        }
    }
}
