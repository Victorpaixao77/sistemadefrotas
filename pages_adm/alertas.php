<?php
require_once __DIR__ . '/includes/adm_auth.php';
$page_title = 'Central de alertas';
require_once __DIR__ . '/includes/layout_start.php';
$cc_title = 'Central de alertas';
$cc_subtitle = 'Informação · Aviso · Crítico · Emergência';
include __DIR__ . '/components/page_header.php';
?>
<div id="alertas-list"></div>
<?php
$adm_extra_scripts = <<<'JS'
<script>
(function () {
    function render(d) {
        var el = document.getElementById('alertas-list');
        if (!d.alertas || !d.alertas.length) {
            el.innerHTML = '<div class="adm-cc-empty"><i class="fas fa-bell-slash"></i><p>Nenhum alerta aberto.</p></div>';
            return;
        }
        el.innerHTML = d.alertas.map(function (a) {
            return '<div class="adm-cc-alert adm-cc-alert--' + a.prioridade + '"><div><p class="adm-cc-alert__title">' + AdmCC.escapeHtml(a.titulo) + '</p><p class="adm-cc-alert__body">' + AdmCC.escapeHtml(a.mensagem) + (a.razao_social ? ' · ' + AdmCC.escapeHtml(a.razao_social) : '') + '</p></div><button type="button" class="adm-cc-btn adm-resolve" data-id="' + a.id + '">Resolver</button></div>';
        }).join('');
        el.querySelectorAll('.adm-resolve').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var fd = new FormData();
                fd.append('id', btn.dataset.id);
                fd.append('acao', 'resolver');
                AdmCC.fetch('alerts.php', { method: 'POST', body: fd }).then(function () { AdmCC.fetch('alerts.php').then(function (r) { render(r.data); }); });
            });
        });
    }
    AdmCC.poll('alerts.php', render, 30000);
})();
</script>
JS;
require_once __DIR__ . '/includes/layout_end.php';
