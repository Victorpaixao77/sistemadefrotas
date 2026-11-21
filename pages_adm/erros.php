<?php
require_once __DIR__ . '/includes/adm_auth.php';
$page_title = 'Central de erros';
require_once __DIR__ . '/includes/layout_start.php';
$cc_title = 'Central de erros';
$cc_subtitle = 'PHP · SQL · API — por frequência';
include __DIR__ . '/components/page_header.php';
?>
<div class="adm-panel"><div class="adm-panel__head"><h2>php_error.log</h2></div><div class="adm-panel__body adm-cc-terminal" id="err-file"></div></div>
<div class="adm-panel"><div class="adm-panel__head"><h2>Agregado (adm_eventos)</h2></div><div class="adm-panel__body adm-table-wrap"><table class="adm-table" id="tbl-agg"><thead><tr><th>Mensagem</th><th>Freq</th><th>Último</th></tr></thead><tbody></tbody></table></div></div>
<?php
$adm_extra_scripts = <<<'JS'
<script>
AdmCC.fetch('errors.php').then(function (r) {
    var d = r.data;
    document.getElementById('err-file').innerHTML = (d.file_errors || []).slice(0, 50).map(function (e) {
        return '<div class="adm-cc-terminal__line"><span class="adm-cc-terminal__time">' + e.tipo + '</span><span>' + AdmCC.escapeHtml(e.linha) + '</span></div>';
    }).join('') || 'Log vazio ou inacessível.';
    document.querySelector('#tbl-agg tbody').innerHTML = (d.aggregated || []).map(function (a) {
        return '<tr><td>' + AdmCC.escapeHtml(a.mensagem) + '</td><td>' + a.freq + '</td><td>' + a.ultimo + '</td></tr>';
    }).join('') || '<tr><td colspan="3">Sem erros agregados.</td></tr>';
});
</script>
JS;
require_once __DIR__ . '/includes/layout_end.php';
