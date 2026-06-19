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

try {
    $conn = getConnection();
    $empresa_id = (int) $_SESSION['empresa_id'];
    $ctx = sf_dashboard_analytics_context();

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
    $colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#2ecc71'];
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

    echo json_encode([
        'labels' => $labels,
        'datasets' => [[
            'data' => $data,
            'backgroundColor' => array_slice($colors, 0, count($labels)),
            'borderWidth' => 1,
        ]],
        'period_label' => $ctx['label'],
    ]);
} catch (Throwable $e) {
    error_log('Expenses Distribution API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar distribuição de despesas']);
}
