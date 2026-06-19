<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/pneus_helpers.php';

header('Content-Type: application/json; charset=utf-8');

configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_authentication();
pneus_require_page_access();

try {
    $pdo = getConnection();
    $empresa_id = pneus_empresa_id();

    // Em uso: alocações ativas (mais confiável que só status_id)
    $emUso = 0;
    $queriesUso = [
        'SELECT COUNT(DISTINCT pneu_id) AS c FROM instalacoes_pneus ip INNER JOIN pneus p ON p.id = ip.pneu_id WHERE p.empresa_id = ? AND ip.data_remocao IS NULL',
        'SELECT COUNT(DISTINCT apf.pneu_id) AS c FROM alocacoes_pneus_flexiveis apf INNER JOIN pneus p ON p.id = apf.pneu_id WHERE p.empresa_id = ? AND apf.ativo = 1',
        "SELECT COUNT(DISTINCT pa.pneu_id) AS c FROM pneus_alocacao pa INNER JOIN pneus p ON p.id = pa.pneu_id WHERE p.empresa_id = ? AND pa.status = 'alocado'",
        "SELECT COUNT(DISTINCT ep.pneu_id) AS c FROM eixo_pneus ep INNER JOIN pneus p ON p.id = ep.pneu_id WHERE p.empresa_id = ? AND ep.status = 'alocado'",
    ];
    $pneusEmUsoIds = [];
    foreach ($queriesUso as $sql) {
        try {
            $st = $pdo->prepare($sql);
            $st->execute([$empresa_id]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                if (!empty($row['pneu_id'])) {
                    $pneusEmUsoIds[(int) $row['pneu_id']] = true;
                }
            }
            if (isset($row['c'])) {
                $emUso += (int) $row['c'];
            }
        } catch (Throwable $e) {
            // ignora tabela ausente
        }
    }
    // Recalcular distinct se possível
    $st = $pdo->prepare("
        SELECT COUNT(DISTINCT x.pneu_id) AS total FROM (
            SELECT ip.pneu_id FROM instalacoes_pneus ip INNER JOIN pneus p ON p.id = ip.pneu_id WHERE p.empresa_id = ? AND ip.data_remocao IS NULL
            UNION
            SELECT apf.pneu_id FROM alocacoes_pneus_flexiveis apf INNER JOIN pneus p ON p.id = apf.pneu_id WHERE p.empresa_id = ? AND apf.ativo = 1
            UNION
            SELECT pa.pneu_id FROM pneus_alocacao pa INNER JOIN pneus p ON p.id = pa.pneu_id WHERE p.empresa_id = ? AND pa.status = 'alocado'
            UNION
            SELECT ep.pneu_id FROM eixo_pneus ep INNER JOIN pneus p ON p.id = ep.pneu_id WHERE p.empresa_id = ? AND ep.status = 'alocado'
        ) x
    ");
    try {
        $st->execute([$empresa_id, $empresa_id, $empresa_id, $empresa_id]);
        $emUso = (int) ($st->fetch(PDO::FETCH_ASSOC)['total'] ?? $emUso);
    } catch (Throwable $e) {
        $emUso = count($pneusEmUsoIds);
    }

    $st = $pdo->prepare('SELECT COUNT(*) AS total FROM pneus WHERE empresa_id = ?');
    $st->execute([$empresa_id]);
    $total_pneus = (int) $st->fetch(PDO::FETCH_ASSOC)['total'];

    $st = $pdo->prepare("
        SELECT COALESCE(AVG(LEAST(100, GREATEST(0, (sulco_inicial / 10.0) * 100))), 0) AS vida_media
        FROM pneus WHERE empresa_id = ? AND sulco_inicial IS NOT NULL
    ");
    $st->execute([$empresa_id]);
    $vida_media = (int) round((float) $st->fetch(PDO::FETCH_ASSOC)['vida_media']);

    $st = $pdo->prepare('
        SELECT COUNT(*) AS total FROM pneus
        WHERE empresa_id = ? AND sulco_inicial IS NOT NULL AND sulco_inicial <= 2
    ');
    $st->execute([$empresa_id]);
    $pneus_alerta = (int) $st->fetch(PDO::FETCH_ASSOC)['total'];

    $st = $pdo->prepare('
        SELECT COALESCE(SUM(custo), 0) AS total FROM pneu_movimentacoes
        WHERE empresa_id = ? AND tipo IN (\'entrada_estoque\', \'recapagem\', \'manutencao\')
    ');
    $custo_total = 0.0;
    try {
        $st->execute([$empresa_id]);
        $custo_total = (float) $st->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Throwable $e) {
        $custo_total = $total_pneus * 2500;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'total_em_uso' => $emUso,
            'total_pneus' => $total_pneus,
            'vida_media' => $vida_media,
            'pneus_alerta' => $pneus_alerta,
            'custo_total' => $custo_total,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('get_tire_metrics.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar métricas dos pneus'], JSON_UNESCAPED_UNICODE);
}
