<?php
require_once __DIR__ . '/includes/adm_auth.php';
require_once __DIR__ . '/includes/adm_paths.php';
sf_project_include('includes/sf_paths.php');
$page_title = 'Analytics';
$adm_extra_scripts = '<script src="' . htmlspecialchars(sf_app_url('pages_adm/js/adm-charts.js?v=' . sf_asset_v())) . '"></script>';
require_once __DIR__ . '/includes/layout_start.php';
$cc_title = 'Analytics da plataforma';
$cc_subtitle = 'Filtros por período e visão consolidada';
include __DIR__ . '/components/page_header.php';
?>
<div class="adm-cc-dashboard">
<div class="adm-cc-filters adm-cc-filters--flat">
    <select id="an-periodo"><option value="12">12 meses</option><option value="6">6 meses</option><option value="3">3 meses</option></select>
</div>
<div class="adm-cc-chart-wrap">
    <h2 class="adm-cc-section__title">Receita por período</h2>
    <div class="adm-cc-chart-wrap__body">
        <canvas id="chart-analytics"></canvas>
    </div>
</div>
</div>
<?php
$adm_extra_scripts .= <<<'JS'
<script>
(function () {
    function load() {
        AdmCC.fetch('dashboard_metrics.php').then(function (r) {
            var d = r.data;
            if (d.receita_meses) {
                var n = parseInt(document.getElementById('an-periodo').value, 10);
                var slice = d.receita_meses.slice(-n);
                AdmCharts.line('chart-analytics', slice.map(function (x) { return x.mes; }), slice.map(function (x) { return x.total; }), 'Receita');
            }
        });
    }
    document.getElementById('an-periodo').addEventListener('change', load);
    load();
})();
</script>
JS;
require_once __DIR__ . '/includes/layout_end.php';
