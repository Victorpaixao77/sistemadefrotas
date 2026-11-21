<?php
require_once __DIR__ . '/includes/adm_auth.php';
$page_title = 'Auditoria inteligente';
require_once __DIR__ . '/includes/layout_start.php';
$cc_title = 'Auditoria de segurança';
$cc_subtitle = 'Pontuação por empresa · 2FA · inadimplência · login';
include __DIR__ . '/components/page_header.php';
?>
<div class="adm-panel"><div class="adm-panel__body adm-table-wrap">
<table class="adm-table" id="tbl-audit"><thead><tr><th>Empresa</th><th>Score</th><th>Problemas</th></tr></thead><tbody></tbody></table>
</div></div>
<?php
$adm_extra_scripts = <<<'JS'
<script>
AdmCC.fetch('intel.php?tipo=auditoria').then(function (r) {
    document.querySelector('#tbl-audit tbody').innerHTML = (r.data.empresas || []).map(function (e) {
        var cls = e.score >= 80 ? 'high' : (e.score >= 60 ? 'mid' : 'low');
        var issues = (e.issues || []).map(function (i) { return i.msg; }).join('; ') || 'OK';
        return '<tr><td>' + AdmCC.escapeHtml(e.razao_social) + '</td><td><span class="adm-cc-score adm-cc-score--' + cls + '">' + e.score + '</span></td><td>' + AdmCC.escapeHtml(issues) + '</td></tr>';
    }).join('');
});
</script>
JS;
require_once __DIR__ . '/includes/layout_end.php';
