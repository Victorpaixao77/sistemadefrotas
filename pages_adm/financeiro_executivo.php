<?php
require_once __DIR__ . '/includes/adm_auth.php';
require_once __DIR__ . '/includes/adm_paths.php';
sf_project_include('includes/sf_paths.php');
$page_title = 'Financeiro SaaS';
$adm_extra_scripts = '<script src="' . htmlspecialchars(sf_app_url('pages_adm/js/adm-charts.js?v=' . sf_asset_v())) . '"></script>';
require_once __DIR__ . '/includes/layout_start.php';
$cc_title = 'Financeiro executivo';
$cc_subtitle = 'MRR · ARR · Churn · LTV · Inadimplência';
$cc_actions = '<span class="adm-cc-live"><span class="adm-cc-live__dot"></span> Ao vivo</span>';
include __DIR__ . '/components/page_header.php';
?>

<div class="adm-cc-dashboard adm-cc-skeleton" id="fin-dashboard">

<section class="adm-cc-section">
    <h2 class="adm-cc-section__title">Indicadores SaaS</h2>
    <div class="adm-cc-kpi-grid">
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-coins"></i></span><span class="adm-cc-kpi__label">MRR</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="fin-mrr">—</div></div>
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-chart-line"></i></span><span class="adm-cc-kpi__label">ARR</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="fin-arr">—</div></div>
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-arrow-trend-down"></i></span><span class="adm-cc-kpi__label">Churn</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="fin-churn">—</div></div>
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-gem"></i></span><span class="adm-cc-kpi__label">LTV</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="fin-ltv">—</div></div>
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-receipt"></i></span><span class="adm-cc-kpi__label">Ticket médio</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="fin-ticket">—</div></div>
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-triangle-exclamation"></i></span><span class="adm-cc-kpi__label">Inadimplentes</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="fin-inadimplentes">—</div></div>
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-circle-xmark"></i></span><span class="adm-cc-kpi__label">Receita perdida</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="fin-perdida">—</div></div>
    </div>
</section>

<div class="adm-cc-charts-grid">
    <div class="adm-cc-chart-wrap">
        <h2 class="adm-cc-section__title">Receita diária (30d)</h2>
        <div class="adm-cc-chart-wrap__body">
            <canvas id="chart-dia"></canvas>
        </div>
    </div>
    <div class="adm-cc-chart-wrap">
        <h2 class="adm-cc-section__title">Receita por plano</h2>
        <div class="adm-cc-chart-wrap__body">
            <canvas id="chart-plano"></canvas>
        </div>
    </div>
</div>

</div>

<?php
$adm_extra_scripts .= <<<'JS'
<script>
(function () {
    function apply(d) {
        AdmCC.skeleton(false);
        AdmCC.setText('fin-mrr', AdmCC.formatBRL(d.mrr));
        AdmCC.setText('fin-arr', AdmCC.formatBRL(d.arr));
        AdmCC.setText('fin-churn', (d.churn_pct != null ? d.churn_pct : '—') + (d.churn_pct != null ? '%' : ''));
        AdmCC.setText('fin-ltv', AdmCC.formatBRL(d.ltv));
        AdmCC.setText('fin-ticket', AdmCC.formatBRL(d.ticket_medio));
        AdmCC.setText('fin-inadimplentes', AdmCC.formatNum(d.clientes_inadimplentes));
        AdmCC.setText('fin-perdida', AdmCC.formatBRL(d.receita_perdida));
        if (!window.AdmCharts) return;
        if (d.receita_diaria && d.receita_diaria.length) {
            AdmCharts.line(
                'chart-dia',
                d.receita_diaria.map(function (r) { return r.dia; }),
                d.receita_diaria.map(function (r) { return parseFloat(r.total) || 0; }),
                'Receita'
            );
        }
        if (d.receita_por_plano && d.receita_por_plano.length) {
            AdmCharts.doughnut(
                'chart-plano',
                d.receita_por_plano.map(function (p) { return p.plano; }),
                d.receita_por_plano.map(function (p) { return parseFloat(p.receita) || 0; })
            );
        }
    }
    AdmCC.poll('finance_metrics.php', apply, 45000);
})();
</script>
JS;
require_once __DIR__ . '/includes/layout_end.php';
