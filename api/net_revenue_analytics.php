<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';
require_once '../includes/dashboard_analytics_period.php';

configure_session();
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

if (!can_view_financial_data()) {
    http_response_code(403);
    echo json_encode(['error' => 'Sem permissão financeira']);
    exit;
}

$empresa_id = (int) $_SESSION['empresa_id'];
if (function_exists('session_write_close')) { session_write_close(); }

try {
    $conn = getConnection();
    $ctx = sf_dashboard_analytics_context();

    require_once __DIR__ . '/../includes/sf_cache.php';
    $ckey = 'dash:net_revenue:' . $empresa_id . ':' . $ctx['d1'] . ':' . $ctx['d2'] . ':' . $ctx['bucket'];
    if (($cached = sf_cache_get($ckey)) !== null) { echo json_encode($cached); exit; }

    $series = sf_dashboard_analytics_series($ctx['d1'], $ctx['d2'], $ctx['bucket']);
    $receitas = array_fill_keys($series['keys'], 0.0);
    $despesas = array_fill_keys($series['keys'], 0.0);
    $comissoes = array_fill_keys($series['keys'], 0.0);

    $st = $conn->prepare("
        SELECT DATE(COALESCE(data_saida, data_rota)) AS d, SUM(frete) AS t, SUM(comissao) AS c
        FROM rotas WHERE empresa_id = :e AND status = 'aprovado'
        AND DATE(COALESCE(data_saida, data_rota)) BETWEEN :d1 AND :d2
        GROUP BY DATE(COALESCE(data_saida, data_rota))
    ");
    $st->execute([':e' => $empresa_id, ':d1' => $ctx['d1'], ':d2' => $ctx['d2']]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $k = sf_dashboard_analytics_bucket_key((string) $row['d'], $ctx['bucket']);
        if ($k !== '' && isset($receitas[$k])) {
            $receitas[$k] += (float) $row['t'];
            $comissoes[$k] += (float) $row['c'];
        }
    }

    $expenseQueries = [
        "SELECT DATE(created_at) AS d, SUM(COALESCE(total_despviagem, 0)) AS t FROM despesas_viagem WHERE empresa_id = :e AND DATE(created_at) BETWEEN :d1 AND :d2 GROUP BY DATE(created_at)",
        "SELECT DATE(data_pagamento) AS d, SUM(valor) AS t FROM despesas_fixas WHERE empresa_id = :e AND data_pagamento IS NOT NULL AND DATE(data_pagamento) BETWEEN :d1 AND :d2 GROUP BY DATE(data_pagamento)",
        "SELECT DATE(data_manutencao) AS d, SUM(valor) AS t FROM manutencoes WHERE empresa_id = :e AND DATE(data_manutencao) BETWEEN :d1 AND :d2 GROUP BY DATE(data_manutencao)",
        "SELECT DATE(data_abastecimento) AS d, SUM(valor_total + COALESCE(valor_total_arla, 0)) AS t FROM abastecimentos WHERE empresa_id = :e AND status = 'aprovado' AND DATE(data_abastecimento) BETWEEN :d1 AND :d2 GROUP BY DATE(data_abastecimento)",
    ];
    foreach ($expenseQueries as $sql) {
        $st = $conn->prepare($sql);
        $st->execute([':e' => $empresa_id, ':d1' => $ctx['d1'], ':d2' => $ctx['d2']]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $k = sf_dashboard_analytics_bucket_key((string) $row['d'], $ctx['bucket']);
            if ($k !== '' && isset($despesas[$k])) {
                $despesas[$k] += (float) $row['t'];
            }
        }
    }

    $lucro = [];
    foreach ($series['keys'] as $k) {
        $lucro[] = $receitas[$k] - $despesas[$k] - $comissoes[$k];
    }

    $payload = [
        'labels' => $series['labels'],
        'datasets' => [[
            'label' => 'Lucro líquido',
            'data' => $lucro,
            'borderColor' => '#2ecc40',
            'backgroundColor' => 'rgba(46, 204, 64, 0.1)',
            'fill' => true,
            'tension' => 0.4,
        ]],
        'period_label' => $ctx['label'],
    ];
    sf_cache_set($ckey, $payload, 120);
    echo json_encode($payload);
} catch (Throwable $e) {
    error_log('Net Revenue Analytics API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar faturamento líquido']);
}
