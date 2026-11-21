<?php
require_once __DIR__ . '/includes/adm_auth.php';
$page_title = 'Central de logs';
require_once __DIR__ . '/includes/layout_start.php';
$cc_title = 'Central de logs';
$cc_subtitle = 'Eventos da plataforma em tempo real';
include __DIR__ . '/components/page_header.php';
?>
<div class="adm-cc-dashboard">
<div class="adm-cc-filters adm-cc-filters--flat">
    <input type="text" id="log-q" placeholder="Buscar…" aria-label="Buscar">
    <select id="log-modulo" aria-label="Módulo"><option value="">Todos módulos</option>
        <option value="auth">auth</option><option value="api">api</option><option value="ia">ia</option><option value="financeiro">financeiro</option><option value="empresas">empresas</option><option value="planos">planos</option><option value="usuarios">usuarios</option></select>
    <button type="button" class="adm-cc-btn" id="log-filter-btn">Filtrar</button>
</div>
<div class="adm-cc-terminal" id="log-terminal" aria-live="polite"></div>
</div>
<?php
$adm_extra_scripts = <<<'JS'
<script>
(function () {
    function load() {
        var q = document.getElementById('log-q').value;
        var mod = document.getElementById('log-modulo').value;
        var url = 'logs_stream.php?limit=80' + (q ? '&q=' + encodeURIComponent(q) : '') + (mod ? '&modulo=' + encodeURIComponent(mod) : '');
        AdmCC.fetch(url).then(function (r) { AdmCC.renderTerminal(document.getElementById('log-terminal'), r.data.lines); });
    }
    document.getElementById('log-filter-btn').addEventListener('click', load);
    load();
    setInterval(load, 15000);
})();
</script>
JS;
require_once __DIR__ . '/includes/layout_end.php';
