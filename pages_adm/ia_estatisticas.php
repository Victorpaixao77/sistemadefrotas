<?php
require_once __DIR__ . '/includes/adm_auth.php';
require_once __DIR__ . '/includes/adm_paths.php';
sf_project_include('includes/sf_paths.php');
$page_title = 'Estatísticas IA';
$adm_extra_scripts = '<script src="' . htmlspecialchars(sf_app_url('pages_adm/js/adm-charts.js?v=' . sf_asset_v())) . '"></script>';
require_once __DIR__ . '/includes/layout_start.php';
$cc_title = 'Estatísticas da IA';
$cc_subtitle = 'Consultas · tokens · custo · modelos';
include __DIR__ . '/components/page_header.php';
?>
<div class="adm-cc-dashboard">
    <section class="adm-cc-kpi-band">
        <h2 class="adm-cc-section__title">Últimos 30 dias</h2>
        <div class="adm-cc-kpi-grid" id="ia-kpis"></div>
    </section>
    <div class="adm-cc-chart-wrap">
        <h2 class="adm-cc-section__title">Uso por modelo</h2>
        <div class="adm-cc-chart-wrap__body">
            <canvas id="chart-modelos"></canvas>
        </div>
    </div>
</div>
<?php
$adm_extra_scripts .= <<<'JS'
<script>
AdmCC.fetch('ia_stats.php').then(function (r) {
    var t = r.data.totais || {};
    document.getElementById('ia-kpis').innerHTML = [
        ['Consultas (30d)', t.consultas || 0],
        ['Tokens in', t.tokens_in || 0],
        ['Tokens out', t.tokens_out || 0],
        ['Tempo médio', (t.avg_ms || '—') + (t.avg_ms ? ' ms' : '')],
        ['Custo est.', 'R$ ' + parseFloat(t.custo || 0).toFixed(2)],
        ['Erros', t.erros || 0]
    ].map(function (x) {
        return '<div class="adm-cc-kpi"><div class="adm-cc-kpi__label">' + x[0] + '</div><div class="adm-cc-kpi__value" style="font-size:1.35rem">' + x[1] + '</div></div>';
    }).join('');
    var mods = r.data.modelos || [];
    if (mods.length) AdmCharts.bar('chart-modelos', mods.map(function (m) { return m.modelo || '?'; }), mods.map(function (m) { return m.qtd; }), 'Uso');
});
</script>
JS;
require_once __DIR__ . '/includes/layout_end.php';
