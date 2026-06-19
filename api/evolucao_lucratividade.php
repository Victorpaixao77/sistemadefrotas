<?php
require_once __DIR__ . '/../includes/lucratividade_api_base.php';

try {
    $conn = getConnection();
    $qtd = isset($_GET['meses']) ? max(1, min(24, (int) $_GET['meses'])) : 6;
    $data = lucra_get_evolucao_meses($conn, $empresa_id, $mes, $ano, $qtd);
    echo json_encode([
        'success' => true,
        'labels' => $data['labels'],
        'lucro' => $data['lucro'],
        'receita' => $data['receita'],
        'empty' => array_sum($data['receita']) <= 0 && array_sum(array_map('abs', $data['lucro'])) <= 0,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('evolucao_lucratividade: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível carregar a evolução.', 'labels' => [], 'lucro' => [], 'receita' => []], JSON_UNESCAPED_UNICODE);
}
