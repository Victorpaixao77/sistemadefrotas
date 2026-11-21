<?php
require_once __DIR__ . '/../includes/pneus_api_base.php';
require_once __DIR__ . '/../includes/pneu_manutencao_analytics_data.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $conn = getConnection();
    $empresa_id = pneus_empresa_id();
    $mes = isset($_GET['mes']) ? (int) $_GET['mes'] : null;
    $ano = isset($_GET['ano']) ? (int) $_GET['ano'] : null;

    echo json_encode(pneu_manutencao_fetch_analytics($conn, $empresa_id, $mes ?: null, $ano ?: null));
} catch (PDOException $e) {
    error_log('pneu_manutencao_analytics: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados']);
} catch (Exception $e) {
    error_log('pneu_manutencao_analytics: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar dados']);
}
