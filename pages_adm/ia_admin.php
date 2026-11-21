<?php
require_once __DIR__ . '/includes/adm_auth.php';
$page_title = 'IA administrativa';
require_once __DIR__ . '/includes/layout_start.php';
$cc_title = 'IA administrativa';
$cc_subtitle = 'Análise inteligente de toda a plataforma';
$cc_actions = '<button type="button" class="adm-cc-btn adm-cc-btn--primary" id="btn-analyze">Analisar sistema</button>';
include __DIR__ . '/components/page_header.php';
?>
<div id="ia-result" class="adm-panel"><div class="adm-panel__body"><p class="adm-cc-empty">Clique em <strong>Analisar sistema</strong> para gerar insights.</p></div></div>
<?php
$adm_extra_scripts = <<<'JS'
<script>
(function () {
    document.getElementById('btn-analyze').addEventListener('click', function () {
        var el = document.getElementById('ia-result');
        el.querySelector('.adm-panel__body').innerHTML = '<p>Analisando…</p>';
        AdmCC.fetch('ia_admin_analyze.php').then(function (r) {
            var d = r.data;
            var html = '<h3>Resumo</h3><ul>';
            html += '<li>Empresas ativas: ' + d.resumo.empresas_ativas + '</li>';
            html += '<li>MRR: ' + AdmCC.formatBRL(d.resumo.mrr) + '</li>';
            html += '<li>Inadimplentes: ' + d.resumo.inadimplentes + '</li>';
            html += '<li>Alertas abertos: ' + d.resumo.alertas_abertos + '</li></ul>';
            html += '<h3>Risco de cancelamento</h3><ul>';
            (d.risco_cancelamento || []).forEach(function (x) {
                html += '<li>' + AdmCC.escapeHtml(x.empresa) + ': ' + x.sugestoes.join(', ') + '</li>';
            });
            html += '</ul><h3>Segurança baixa</h3><ul>';
            (d.seguranca_baixa || []).forEach(function (x) {
                html += '<li>' + AdmCC.escapeHtml(x.razao_social) + ' — score ' + x.score + '</li>';
            });
            html += '</ul><h3>Sugestões</h3><ul>';
            (d.sugestoes || []).filter(Boolean).forEach(function (s) { html += '<li>' + AdmCC.escapeHtml(s) + '</li>'; });
            html += '</ul>';
            el.querySelector('.adm-panel__body').innerHTML = html;
        });
    });
})();
</script>
JS;
require_once __DIR__ . '/includes/layout_end.php';
