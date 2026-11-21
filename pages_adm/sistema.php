<?php
require_once __DIR__ . '/includes/adm_auth.php';
$page_title = 'Sistema';
require_once __DIR__ . '/includes/layout_start.php';
$cc_title = 'Informações do sistema';
$cc_subtitle = 'PHP · MySQL · cache · deploy';
include __DIR__ . '/components/page_header.php';
?>
<div id="sys-grid" class="adm-cc-kpi-grid"></div>
<?php
$adm_extra_scripts = <<<'JS'
<script>
AdmCC.fetch('system_info.php').then(function (r) {
    var d = r.data;
    var items = [
        ['PHP', d.php], ['Apache', d.apache], ['MySQL', d.mysql || '—'],
        ['Redis', d.redis === null ? 'N/A' : (d.redis ? 'OK' : 'Falha')],
        ['Composer', d.composer ? 'Instalado' : 'Não'],
        ['Git commit', d.git_commit || '—'],
        ['OPcache', d.opcache ? 'Ativo' : 'Off'],
        ['APCu', d.apcu ? 'Ativo' : 'Off'],
        ['Memory limit', d.memory_limit],
        ['Disco', d.disco_pct != null ? d.disco_pct + '%' : '—']
    ];
    document.getElementById('sys-grid').innerHTML = items.map(function (x) {
        return '<div class="adm-cc-kpi"><div class="adm-cc-kpi__label">' + x[0] + '</div><div class="adm-cc-kpi__value" style="font-size:1rem">' + AdmCC.escapeHtml(String(x[1])) + '</div></div>';
    }).join('');
});
</script>
JS;
require_once __DIR__ . '/includes/layout_end.php';
