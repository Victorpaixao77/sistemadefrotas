<?php
/**
 * Bloqueia endpoints de debug em produção (SF_DEBUG desligado).
 * Incluir no topo de arquivos *debug*.php e diagnósticos internos.
 */
if (!defined('DEBUG_MODE')) {
    require_once __DIR__ . '/config.php';
}
if (!DEBUG_MODE && PHP_SAPI !== 'cli') {
    if (!headers_sent()) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['success' => false, 'error' => 'Recurso não disponível.'], JSON_UNESCAPED_UNICODE);
    exit;
}
