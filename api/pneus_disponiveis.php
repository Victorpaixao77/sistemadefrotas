<?php
require_once '../includes/pneus_api_base.php';

$empresa_id = pneus_empresa_id();
header('Content-Type: application/json; charset=utf-8');

try {
    $conn = getConnection();
    $idsDisp = pneus_status_ids_disponiveis($conn);
    $inPlaceholders = implode(',', array_fill(0, count($idsDisp), '?'));
    $naoAlocado = pneus_sql_nao_alocado('p.id');

    $sql = "SELECT 
            p.id,
            p.numero_serie,
            p.marca,
            p.modelo,
            p.medida,
            p.sulco_inicial,
            p.dot,
            p.km_instalacao,
            p.data_instalacao,
            p.vida_util_km,
            p.numero_recapagens,
            p.data_ultima_recapagem,
            p.lote,
            p.data_entrada,
            p.observacoes,
            p.status_id,
            sp.nome AS status_nome,
            LOWER(TRIM(sp.nome)) AS status
        FROM pneus p
        LEFT JOIN status_pneus sp ON sp.id = p.status_id
        WHERE p.empresa_id = ?
        AND p.status_id IN ({$inPlaceholders})
        AND {$naoAlocado}
        ORDER BY p.numero_serie";

    $params = array_merge([$empresa_id], $idsDisp);
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $pneus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pneus as &$pneu) {
        if ($pneu['sulco_inicial'] !== null) {
            $pneu['sulco_inicial'] = number_format((float) $pneu['sulco_inicial'], 1, '.', '');
        }
        foreach (['data_ultima_recapagem', 'data_instalacao', 'data_entrada'] as $df) {
            if (!empty($pneu[$df])) {
                $pneu[$df] = date('d/m/Y', strtotime($pneu[$df]));
            }
        }
    }
    unset($pneu);

    echo json_encode(['success' => true, 'pneus' => $pneus], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('pneus_disponiveis.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar pneus disponíveis'], JSON_UNESCAPED_UNICODE);
}
