<?php
require_once __DIR__ . '/includes/adm_auth.php';
$page_title = 'Performance';
require_once __DIR__ . '/includes/layout_start.php';
$cc_title = 'Monitor de performance';
$cc_subtitle = 'Tempo de resposta por módulo (últimas 24h)';
include __DIR__ . '/components/page_header.php';
?>
<div id="perf-hint" class="adm-cc-support-banner" style="display:none;margin-bottom:1rem"></div>
<div class="adm-cc-kpi-grid" style="margin-bottom:1rem">
    <div class="adm-cc-kpi"><div class="adm-cc-kpi__label">Requisições 24h</div><div class="adm-cc-kpi__value" id="perf-total">—</div></div>
    <div class="adm-cc-kpi"><div class="adm-cc-kpi__label">Média global</div><div class="adm-cc-kpi__value" id="perf-avg">—</div></div>
    <div class="adm-cc-kpi"><div class="adm-cc-kpi__label">Cache HIT</div><div class="adm-cc-kpi__value" id="perf-hit">—</div></div>
    <div class="adm-cc-kpi"><div class="adm-cc-kpi__label">Cache MISS</div><div class="adm-cc-kpi__value" id="perf-miss">—</div></div>
</div>
<div class="adm-panel">
    <div class="adm-panel__head"><h2>Módulos</h2></div>
    <div class="adm-panel__body adm-table-wrap">
        <table class="adm-table" id="tbl-perf">
            <thead><tr><th>Módulo</th><th>Requisições</th><th>Média</th><th>Máx</th></tr></thead>
            <tbody><tr><td colspan="4" style="text-align:center">Carregando…</td></tr></tbody>
        </table>
    </div>
</div>
<div class="adm-panel">
    <div class="adm-panel__head"><h2>Rotas mais lentas (7 dias)</h2></div>
    <div class="adm-panel__body adm-table-wrap">
        <table class="adm-table" id="tbl-slow"><thead><tr><th>Rota</th><th>Módulo</th><th>ms</th><th>Quando</th></tr></thead><tbody></tbody></table>
    </div>
</div>
<?php
$adm_extra_scripts = <<<'JS'
<script>
(function () {
    function go(d) {
        AdmCC.setText('perf-total', AdmCC.formatNum(d.total_queries_24h));
        AdmCC.setText('perf-avg', d.avg_global_ms != null ? d.avg_global_ms + ' ms' : '—');
        AdmCC.setText('perf-hit', d.cache_hit != null ? AdmCC.formatNum(d.cache_hit) : '—');
        AdmCC.setText('perf-miss', d.cache_miss != null ? AdmCC.formatNum(d.cache_miss) : '—');

        var hint = document.getElementById('perf-hint');
        if (!d.instrumented) {
            hint.style.display = 'block';
            hint.textContent = 'Coletando métricas… Navegue pelo sistema de frotas e recarregue esta página em alguns minutos.';
        } else {
            hint.style.display = 'none';
        }

        var mods = (d.modulos || []).filter(function (m) { return m.queries > 0 || m.avg_ms != null; });
        if (!mods.length) {
            mods = d.modulos || [];
        }
        document.querySelector('#tbl-perf tbody').innerHTML = mods.map(function (m) {
            var avg = m.avg_ms != null ? m.avg_ms + ' ms' : '—';
            var max = m.max_ms != null ? m.max_ms + ' ms' : '—';
            var dim = m.queries === 0 ? ' style="opacity:0.55"' : '';
            return '<tr' + dim + '><td>' + AdmCC.escapeHtml(m.modulo) + '</td><td>' + m.queries + '</td><td>' + avg + '</td><td>' + max + '</td></tr>';
        }).join('');

        document.querySelector('#tbl-slow tbody').innerHTML = (d.slow_queries || []).map(function (s) {
            return '<tr><td><code>' + AdmCC.escapeHtml(s.rota) + '</code></td><td>' + AdmCC.escapeHtml(s.modulo || '') + '</td><td>' + s.duracao_ms + '</td><td>' + AdmCC.escapeHtml(s.created_at) + '</td></tr>';
        }).join('') || '<tr><td colspan="4" class="adm-cc-empty">Nenhuma rota lenta registrada ainda.</td></tr>';
    }
    AdmCC.poll('performance.php', go, 60000, function (err) {
        document.querySelector('#tbl-perf tbody').innerHTML =
            '<tr><td colspan="4" class="adm-cc-empty">Erro: ' + AdmCC.escapeHtml(err.message || 'falha na API') + '</td></tr>';
    });
})();
</script>
JS;
require_once __DIR__ . '/includes/layout_end.php';
