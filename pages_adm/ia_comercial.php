<?php
require_once __DIR__ . '/includes/adm_auth.php';
$page_title = 'IA comercial';
require_once __DIR__ . '/includes/layout_start.php';
$cc_title = 'Inteligência comercial';
$cc_subtitle = 'Oportunidades de upgrade e risco de churn';
include __DIR__ . '/components/page_header.php';
?>
<div id="comercial-list"></div>
<?php
$adm_extra_scripts = <<<'JS'
<script>
AdmCC.fetch('intel.php?tipo=comercial').then(function (r) {
    var ops = r.data.oportunidades || [];
    var el = document.getElementById('comercial-list');
    if (!ops.length) { el.innerHTML = '<div class="adm-cc-empty">Nenhuma oportunidade detectada no momento.</div>'; return; }
    el.innerHTML = ops.map(function (o) {
        return '<div class="adm-cc-alert adm-cc-alert--info"><p class="adm-cc-alert__title">' + AdmCC.escapeHtml(o.empresa) + '</p><p class="adm-cc-alert__body">' + o.sugestoes.map(AdmCC.escapeHtml).join(' · ') + '</p></div>';
    }).join('');
});
</script>
JS;
require_once __DIR__ . '/includes/layout_end.php';
