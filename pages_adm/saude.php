<?php
require_once __DIR__ . '/includes/adm_auth.php';
$page_title = 'Saúde do sistema';
require_once __DIR__ . '/includes/layout_start.php';
$cc_title = 'NOC — Saúde do sistema';
$cc_subtitle = 'Status dos serviços da plataforma';
$cc_actions = '<a href="alertas.php" class="adm-cc-btn">Ver alertas</a>';
include __DIR__ . '/components/page_header.php';
?>
<div class="adm-cc-noc-grid" id="noc-grid"></div>
<p class="adm-cc-page-head__sub" id="noc-updated" style="margin-top:1rem"></p>
<?php
$adm_extra_scripts = <<<'JS'
<script>
(function () {
    function go(d) {
        AdmCC.renderNocGrid(document.getElementById('noc-grid'), d.services);
        document.getElementById('noc-updated').textContent = 'Última verificação: ' + (d.checked_at || '');
    }
    AdmCC.poll('health_status.php', go, 30000);
})();
</script>
JS;
require_once __DIR__ . '/includes/layout_end.php';
