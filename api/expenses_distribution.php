<?php
/**
 * Expenses Distribution API — pizza de custos no período (?period=).
 */
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

    // Dashboard: 7 agregações por período. Cacheado (APCu/arquivo) por 120s, por empresa+período.
    require_once __DIR__ . '/../includes/sf_cache.php';
    $ckey = 'dash:exp_dist:' . $empresa_id . ':' . $ctx['d1'] . ':' . $ctx['d2'];
    $payload = sf_cache_remember($ckey, 120, function () use ($conn, $empresa_id, $ctx) {

    $parts = [
        'Despesas de Viagem' => "SELECT COALESCE(SUM(total_despviagem), 0) FROM despesas_viagem WHERE empresa_id = :e AND DATE(created_at) BETWEEN :d1 AND :d2",
        'Despesas Fixas' => "SELECT COALESCE(SUM(valor), 0) FROM despesas_fixas WHERE empresa_id = :e AND data_pagamento IS NOT NULL AND DATE(data_pagamento) BETWEEN :d1 AND :d2",
        'Manutenções de Veículos' => "SELECT COALESCE(SUM(valor), 0) FROM manutencoes WHERE empresa_id = :e AND DATE(data_manutencao) BETWEEN :d1 AND :d2",
        'Manutenções de Pneus' => "SELECT COALESCE(SUM(custo), 0) FROM pneu_manutencao WHERE empresa_id = :e AND DATE(data_manutencao) BETWEEN :d1 AND :d2",
        'Contas Pagas' => "SELECT COALESCE(SUM(valor), 0) FROM contas_pagar WHERE empresa_id = :e AND status_id = 2 AND data_pagamento IS NOT NULL AND DATE(data_pagamento) BETWEEN :d1 AND :d2",
        'Parcelas de Financiamento' => "SELECT COALESCE(SUM(pf.valor), 0) FROM parcelas_financiamento pf INNER JOIN financiamentos f ON f.id = pf.financiamento_id WHERE f.empresa_id = :e AND pf.status_id = 2 AND pf.data_pagamento IS NOT NULL AND DATE(pf.data_pagamento) BETWEEN :d1 AND :d2",
        'Abastecimentos' => "SELECT COALESCE(SUM(valor_total + COALESCE(valor_total_arla, 0)), 0) FROM abastecimentos WHERE empresa_id = :e AND status = 'aprovado' AND DATE(data_abastecimento) BETWEEN :d1 AND :d2",
    ];

    $labels = [];
    $data = [];
    $colors = ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899'];
    $i = 0;

    foreach ($parts as $label => $sql) {
        $st = $conn->prepare($sql);
        $st->execute([':e' => $empresa_id, ':d1' => $ctx['d1'], ':d2' => $ctx['d2']]);
        $val = (float) $st->fetchColumn();
        if ($val > 0) {
            $labels[] = $label;
            $data[] = $val;
            $i++;
        }
    }

        return [
            'labels' => $labels,
            'datasets' => [[
                'data' => $data,
                'backgroundColor' => array_slice($colors, 0, count($labels)),
                'borderWidth' => 0,
            ]],
            'period_label' => $ctx['label'],
        ];
    });

    echo json_encode($payload);
} catch (Throwable $e) {
    error_log('Expenses Distribution API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar distribuição de despesas']);
}
