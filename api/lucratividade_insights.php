<?php
require_once __DIR__ . '/../includes/lucratividade_api_base.php';
require_once __DIR__ . '/../includes/sf_insights_cache.php';

try {
    $conn = getConnection();
    $keyExtra = ['mes' => $mes, 'ano' => $ano];
    sf_insights_json_cached($empresa_id, 'lucratividade', 300, static function () use ($conn, $empresa_id, $mes, $ano) {
        return lucra_build_insights_payload($conn, $empresa_id, $mes, $ano);
    }, $keyExtra);
} catch (Throwable $e) {
    error_log('lucratividade_insights: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível carregar insights.'], JSON_UNESCAPED_UNICODE);
}
