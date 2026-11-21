<?php
/**
 * Cabeçalho padrão para páginas CC que consomem API via AdmCC.
 * Variáveis: $cc_title, $cc_subtitle, $cc_api, $cc_poll_ms (opcional), $cc_render_js (função body string)
 */
if (!isset($cc_poll_ms)) {
    $cc_poll_ms = 0;
}
require_once __DIR__ . '/../includes/layout_start.php';
include __DIR__ . '/page_header.php';
?>
<div id="cc-loading" class="adm-cc-support-banner">Carregando…</div>
<div id="cc-content"></div>
<?php
$api = addslashes($cc_api ?? '');
$poll = (int) $cc_poll_ms;
$adm_extra_scripts = '<script>
(function(){
    var api = "' . $api . '";
    function render(d) {
        document.getElementById("cc-loading").style.display = "none";
        (' . ($cc_render_js ?? 'function(x){ document.getElementById("cc-content").textContent = JSON.stringify(x); }') . ')(d);
    }
    function err(e) {
        document.getElementById("cc-loading").textContent = "Erro: " + (e.message || "falha");
    }
    ' . ($poll > 0
        ? 'AdmCC.poll(api, render, ' . $poll . ', err);'
        : 'AdmCC.fetch(api).then(function(r){ render(r.data); }).catch(err);') . '
})();
</script>';
require_once __DIR__ . '/../includes/layout_end.php';
