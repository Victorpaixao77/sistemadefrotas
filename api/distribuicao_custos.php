<?php
require_once __DIR__ . '/../includes/lucratividade_api_base.php';

try {
    $conn = getConnection();
    $data = lucra_get_distribuicao_custos($conn, $empresa_id, $mes, $ano);
    echo json_encode([
        'success' => true,
        'labels' => $data['labels'],
        'values' => $data['values'],
        'empty' => empty($data['labels']),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('distribuicao_custos: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível carregar a distribuição.', 'labels' => [], 'values' => []], JSON_UNESCAPED_UNICODE);
}
