<?php
/**
 * Detalhes do pneu — API autenticada (gestão interativa).
 */
require_once dirname(__DIR__, 2) . '/includes/pneus_api_base.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    api_json_method_not_allowed('Use GET.');
}

try {
    $pneu_id = (int) ($_GET['pneu_id'] ?? 0);
    $empresa_id = pneus_empresa_id();
    if ($pneu_id <= 0) {
        throw new InvalidArgumentException('ID do pneu não fornecido');
    }

    $conn = getConnection();
    if (!pneus_validar_pneu_empresa($conn, $pneu_id, $empresa_id)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Pneu não encontrado'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $st = $conn->prepare("
        SELECT p.id, p.numero_serie, p.marca, p.modelo, p.medida, p.km_instalacao,
               p.data_instalacao, p.vida_util_km, p.sulco_inicial, p.observacoes, p.empresa_id,
               sp.nome AS status
        FROM pneus p
        LEFT JOIN status_pneus sp ON sp.id = p.status_id
        WHERE p.id = ? AND p.empresa_id = ?
    ");
    $st->execute([$pneu_id, $empresa_id]);
    $pneu = $st->fetch(PDO::FETCH_ASSOC);
    if (!$pneu) {
        throw new RuntimeException('Pneu não encontrado');
    }

    $instalacao = null;
    $stI = $conn->prepare("
        SELECT ip.id, ip.veiculo_id, ip.posicao_id, ip.data_instalacao,
               v.placa, v.modelo AS modelo_veiculo, pp.nome AS posicao_nome
        FROM instalacoes_pneus ip
        LEFT JOIN veiculos v ON ip.veiculo_id = v.id AND v.empresa_id = ?
        LEFT JOIN posicoes_pneus pp ON ip.posicao_id = pp.id
        WHERE ip.pneu_id = ? AND ip.data_remocao IS NULL
        ORDER BY ip.data_instalacao DESC LIMIT 1
    ");
    $stI->execute([$empresa_id, $pneu_id]);
    $instalacao = $stI->fetch(PDO::FETCH_ASSOC);
    if ($instalacao) {
        $pneu['instalacao_atual'] = $instalacao;
        $pneu['posicao_nome'] = $instalacao['posicao_nome'] ?? null;
        $pneu['posicao_id'] = $instalacao['posicao_id'] ?? null;
    }

    $stM = $conn->prepare("
        SELECT pm.id, tmp.nome AS tipo_manutencao, pm.data_manutencao,
               pm.km_veiculo AS quilometragem_manutencao, pm.observacoes, pm.custo
        FROM pneu_manutencao pm
        LEFT JOIN tipo_manutencao_pneus tmp ON tmp.id = pm.tipo_manutencao_id
        WHERE pm.pneu_id = ? AND pm.empresa_id = ?
        ORDER BY pm.data_manutencao DESC LIMIT 5
    ");
    $stM->execute([$pneu_id, $empresa_id]);
    $manutencoes = $stM->fetchAll(PDO::FETCH_ASSOC);
    if ($manutencoes) {
        $pneu['manutencoes'] = $manutencoes;
    }

    echo json_encode(['success' => true, 'pneu' => $pneu], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('pneu_detalhes.php: ' . $e->getMessage());
    $code = $e instanceof InvalidArgumentException ? 400 : 500;
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => 'Erro ao carregar detalhes do pneu'], JSON_UNESCAPED_UNICODE);
}
