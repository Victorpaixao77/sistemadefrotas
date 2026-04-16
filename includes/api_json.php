<?php
/**
 * Respostas JSON padronizadas para APIs internas do painel.
 * Formato: { "success": bool, "message"?: string, "data"?: mixed, "error"?: string }
 */

if (!function_exists('api_json_send')) {
    function api_json_send(array $payload, int $httpCode = 200): void
    {
        http_response_code($httpCode);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    function api_json_unauthorized(string $message = 'Não autorizado'): void
    {
        api_json_send([
            'success' => false,
            'message' => $message,
            'error' => 'unauthorized',
        ], 401);
    }

    function api_json_method_not_allowed(string $message = 'Método não permitido'): void
    {
        api_json_send([
            'success' => false,
            'message' => $message,
            'error' => 'method_not_allowed',
        ], 405);
    }

    function api_json_error(string $message, int $httpCode = 400, ?string $errorCode = null, array $extra = []): void
    {
        $payload = array_merge([
            'success' => false,
            'message' => $message,
        ], $extra);
        if ($errorCode !== null) {
            $payload['error'] = $errorCode;
        }
        api_json_send($payload, $httpCode);
    }
}
