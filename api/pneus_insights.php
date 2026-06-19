<?php
require_once __DIR__ . '/../includes/pneus_api_base.php';
require_once __DIR__ . '/../includes/pneus_repository.php';
require_once __DIR__ . '/../includes/sf_insights_cache.php';

try {
    $conn = getConnection();
    $eid = pneus_empresa_id();
    sf_insights_json_cached($eid, 'pneus', 300, static function () use ($conn, $eid) {
        return pneus_build_insights_payload($conn, $eid);
    });
} catch (Throwable $e) {
    error_log('pneus_insights: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível carregar insights.'], JSON_UNESCAPED_UNICODE);
}
