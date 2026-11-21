<?php
$f = dirname(__DIR__) . '/index.php';
$lines = file($f);

$dash_despesas = ($valor_total_abastecimentos ?? 0) + ($total_desp_viagem ?? 0) + ($total_desp_fixas ?? 0)
    + ($total_contas_pagas ?? 0) + ($total_manutencoes ?? 0) + ($total_pneu_manutencao ?? 0) + ($total_parcelas_financiamento ?? 0);

// Re-read metrics from file context - variables not in scope here. Embed via reading index is wrong.
// Use placeholder and fix in index.php before patch manually.

$insert = <<<'BLOCK'
    <script>
        window.sfDashPeriod = <?php echo json_encode($home_period, JSON_UNESCAPED_UNICODE); ?>;
        window.__SF_DASH_CHART_FALLBACK__ = {
            faturamento: <?php echo json_encode((float) ($total_fretes ?? 0)); ?>,
            despesas: <?php echo json_encode((float) (($valor_total_abastecimentos ?? 0) + ($total_desp_viagem ?? 0) + ($total_desp_fixas ?? 0) + ($total_contas_pagas ?? 0) + ($total_manutencoes ?? 0) + ($total_pneu_manutencao ?? 0) + ($total_parcelas_financiamento ?? 0))); ?>
        };
    </script>
    <script src="<?php echo htmlspecialchars(sf_app_url('js/dashboard-home-charts.js?v=1.0')); ?>"></script>

BLOCK;

// Lines 207-611 are 0-indexed 206-610
$out = array_merge(
    array_slice($lines, 0, 206),
    [$insert],
    array_slice($lines, 611)
);
file_put_contents($f, implode('', $out));
echo "OK\n";
