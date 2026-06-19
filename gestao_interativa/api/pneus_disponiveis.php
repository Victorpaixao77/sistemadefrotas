<?php
require_once dirname(__DIR__, 2) . '/includes/pneus_api_base.php';

header('Content-Type: application/json; charset=utf-8');

$empresa_id = pneus_empresa_id();

try {
    $conn = getConnection();
    $idsDisp = pneus_status_ids_disponiveis($conn);
    $inPlaceholders = implode(',', array_fill(0, count($idsDisp), '?'));
    $naoAlocado = pneus_sql_nao_alocado('p.id');

    $sql = "SELECT p.id, p.numero_serie, p.marca, p.modelo, p.medida, p.sulco_inicial,
            p.dot, p.km_instalacao, p.status_id, sp.nome AS status_nome
        FROM pneus p
        LEFT JOIN status_pneus sp ON sp.id = p.status_id
        WHERE p.empresa_id = ? AND p.status_id IN ({$inPlaceholders}) AND {$naoAlocado}
        ORDER BY p.numero_serie";

    $stmt = $conn->prepare($sql);
    $stmt->execute(array_merge([$empresa_id], $idsDisp));
    $pneus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'pneus' => $pneus], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('gestao_interativa/pneus_disponiveis.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar pneus disponíveis'], JSON_UNESCAPED_UNICODE);
}
