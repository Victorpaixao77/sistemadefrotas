<?php
require_once '../includes/pneus_api_base.php';

header('Content-Type: application/json; charset=utf-8');

$empresa_id = pneus_empresa_id();
$action = $_GET['action'] ?? '';

try {
    $conn = getConnection();

    switch ($action) {
        case 'get_pneus':
            $veiculo_id = isset($_GET['veiculo_id']) ? (int) $_GET['veiculo_id'] : 0;
            if ($veiculo_id <= 0) {
                throw new InvalidArgumentException('ID do veículo não fornecido');
            }
            if (!pneus_validar_veiculo_empresa($conn, $veiculo_id, $empresa_id)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Veículo não encontrado']);
                exit;
            }

            $sql = "SELECT 
                    ep.id as alocacao_id,
                    ep.eixo_id,
                    ep.veiculo_id,
                    ep.posicao_id,
                    ep.status as alocacao_status,
                    ep.data_alocacao,
                    ep.km_alocacao,
                    p.id as pneu_id,
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
                    v.placa,
                    v.modelo as modelo_veiculo,
                    sp.nome as status_nome,
                    LOWER(TRIM(sp.nome)) as status,
                    pp.nome as posicao_nome,
                    CASE WHEN p.sulco_inicial <= 2.0 THEN 1 ELSE 0 END as alerta,
                    CASE WHEN p.data_ultima_recapagem IS NULL OR DATEDIFF(CURRENT_DATE, p.data_ultima_recapagem) > 90 THEN 1 ELSE 0 END as rodizio
                FROM eixo_pneus ep
                LEFT JOIN pneus p ON ep.pneu_id = p.id
                INNER JOIN eixos e ON ep.eixo_id = e.id
                INNER JOIN veiculos v ON v.id = e.veiculo_id AND v.empresa_id = :empresa_id
                LEFT JOIN status_pneus sp ON sp.id = p.status_id
                LEFT JOIN posicoes_pneus pp ON pp.id = ep.posicao_id
                WHERE e.veiculo_id = :veiculo_id
                AND p.empresa_id = :empresa_id
                ORDER BY ep.id";

            $stmt = $conn->prepare($sql);
            $stmt->execute([':veiculo_id' => $veiculo_id, ':empresa_id' => $empresa_id]);
            $pneus = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $eixos_stmt = $conn->prepare('SELECT e.* FROM eixos e INNER JOIN veiculos v ON v.id = e.veiculo_id AND v.empresa_id = ? WHERE e.veiculo_id = ? ORDER BY e.id');
            $eixos_stmt->execute([$empresa_id, $veiculo_id]);
            $eixos = $eixos_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($pneus as &$pneu) {
                if ($pneu['sulco_inicial'] !== null) {
                    $pneu['sulco_inicial'] = number_format((float) $pneu['sulco_inicial'], 1);
                }
                foreach (['data_ultima_recapagem', 'data_instalacao', 'data_alocacao'] as $df) {
                    if (!empty($pneu[$df])) {
                        $pneu[$df] = date('d/m/Y', strtotime($pneu[$df]));
                    }
                }
            }
            unset($pneu);

            echo json_encode(['success' => true, 'pneus' => $pneus, 'eixos' => $eixos], JSON_UNESCAPED_UNICODE);
            break;

        default:
            throw new InvalidArgumentException('Ação inválida');
    }
} catch (Throwable $e) {
    error_log('pneus_data.php: ' . $e->getMessage());
    $code = $e instanceof InvalidArgumentException ? 400 : 500;
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar dados de pneus'], JSON_UNESCAPED_UNICODE);
}
