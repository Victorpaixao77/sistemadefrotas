<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();

$page_title = 'Central de Alertas';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <link rel="stylesheet" href="../css/fornc-modern-page.css">
    <link rel="stylesheet" href="../css/sf-premium.css?v=1.0.2">
    <?php sf_page_help_css('..'); ?>
    <link rel="icon" type="image/png" href="../logo.png">
    <style>
        html, body { overflow-x: hidden; max-width: 100%; }
        .ca-lead { color: var(--text-secondary); margin: 0 0 16px; }
        .ca-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .ca-chip { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 999px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-secondary); font-size: 13px; font-weight: 500; cursor: pointer; }
        .ca-chip:hover { border-color: var(--accent-primary); color: var(--text-primary); }
        .ca-chip.active { background: var(--accent-primary); border-color: var(--accent-primary); color: #fff; }
        .ca-chip .badge { background: rgba(255,255,255,.25); border-radius: 999px; padding: 0 7px; font-size: 11px; font-weight: 700; }
        .ca-chip:not(.active) .badge { background: var(--bg-tertiary); color: var(--text-secondary); }
        .ca-list { display: flex; flex-direction: column; gap: 10px; }
        .ca-item { display: flex; align-items: center; gap: 14px; padding: 14px 16px; border-radius: 12px; border: 1px solid var(--border-color); background: var(--bg-secondary); text-decoration: none; border-left-width: 4px; }
        .ca-item:hover { filter: brightness(1.05); }
        .ca-item.critico { border-left-color: #dc2626; }
        .ca-item.aviso { border-left-color: #f59e0b; }
        .ca-item .ic { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
        .ca-item.critico .ic { background: rgba(239,68,68,.15); color: #dc2626; }
        .ca-item.aviso .ic { background: rgba(245,158,11,.15); color: #f59e0b; }
        .ca-item .tx { flex: 1; min-width: 0; }
        .ca-item .tx .t { font-size: 14px; color: var(--text-primary); font-weight: 600; }
        .ca-item .tx .s { font-size: 12px; color: var(--text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ca-item .cat { font-size: 11px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); border: 1px solid var(--border-color); border-radius: 999px; padding: 2px 10px; flex-shrink: 0; }
        .ca-empty { padding: 40px; text-align: center; color: var(--text-muted); border: 1px dashed var(--border-color); border-radius: 12px; }
        .ca-empty i { color: #16a34a; font-size: 1.4rem; display: block; margin-bottom: 8px; }
        .ca-updated { font-size: 12px; color: var(--text-muted); margin-top: 14px; }
    </style>
</head>
<body class="sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content">
                <p class="ca-lead">Todos os alertas do sistema num só lugar — CNH, documentos, multas, pneus e GPS. Clique para resolver na tela de origem.</p>

                <div class="fornc-toolbar">
                    <div class="fornc-search-block">
                        <label for="caBusca">Busca rápida</label>
                        <div class="fornc-search-inner">
                            <i class="fas fa-search"></i>
                            <input type="text" id="caBusca" placeholder="Buscar alerta..." autocomplete="off">
                        </div>
                    </div>
                    <div class="ca-bar" id="caChips" style="flex:1;"></div>
                    <div class="fornc-btn-row">
                        <button type="button" id="helpBtn" class="fornc-btn fornc-btn--ghost fornc-btn--icon" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>

                <div class="ca-list" id="caLista"></div>
                <p class="ca-updated" id="caUpdated"></p>
            </div>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
    var CA_ICONS = { CNH: 'fa-id-card', 'Documentos': 'fa-file-lines', Multas: 'fa-ticket', Pneus: 'fa-circle-dot', GPS: 'fa-location-dot', Implementos: 'fa-trailer', 'Manutenção': 'fa-wrench' };
    var caDados = [], caFiltro = 'todos', caBusca = '';

    function carregar() {
        fetch('../api/central_alertas.php').then(function (r) { return r.json(); }).then(function (d) {
            if (!d || !d.success) { document.getElementById('caLista').innerHTML = '<div class="ca-empty">Não foi possível carregar.</div>'; return; }
            caDados = d.alertas || [];
            document.getElementById('caUpdated').textContent = 'Total: ' + d.total + ' alerta(s) · ' + d.criticos + ' crítico(s).';
            montarChips(d);
            render();
        }).catch(function () { document.getElementById('caLista').innerHTML = '<div class="ca-empty">Erro de conexão.</div>'; });
    }

    function montarChips(d) {
        var chips = [['todos', 'Todos', d.total], ['criticos', 'Críticos', d.criticos]];
        Object.keys(d.por_categoria || {}).forEach(function (c) { chips.push([c, c, d.por_categoria[c]]); });
        document.getElementById('caChips').innerHTML = chips.map(function (c) {
            var active = (caFiltro === c[0]) ? ' active' : '';
            return '<button class="ca-chip' + active + '" data-f="' + c[0] + '">' + c[1] + ' <span class="badge">' + c[2] + '</span></button>';
        }).join('');
        document.querySelectorAll('.ca-chip').forEach(function (b) {
            b.addEventListener('click', function () { caFiltro = b.dataset.f; render(); montarChipsActive(); });
        });
    }
    function montarChipsActive() {
        document.querySelectorAll('.ca-chip').forEach(function (b) { b.classList.toggle('active', b.dataset.f === caFiltro); });
    }

    function render() {
        var lista = document.getElementById('caLista');
        var itens = caDados.filter(function (a) {
            var okF = caFiltro === 'todos' || (caFiltro === 'criticos' ? a.nivel === 'critico' : a.categoria === caFiltro);
            var okB = caBusca === '' || (a.titulo + ' ' + a.detalhe + ' ' + a.categoria).toLowerCase().indexOf(caBusca) !== -1;
            return okF && okB;
        });
        // críticos primeiro
        itens.sort(function (a, b) { return (a.nivel === 'critico' ? 0 : 1) - (b.nivel === 'critico' ? 0 : 1); });
        if (!itens.length) {
            lista.innerHTML = '<div class="ca-empty"><i class="fas fa-check-circle"></i> Nenhum alerta ' + (caFiltro === 'todos' ? '' : 'nesse filtro') + '. Tudo em dia.</div>';
            return;
        }
        lista.innerHTML = itens.map(function (a) {
            var ic = CA_ICONS[a.categoria] || 'fa-bell';
            var esc = function (s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; };
            return '<a class="ca-item ' + a.nivel + '" href="' + a.link + '">' +
                '<div class="ic"><i class="fas ' + ic + '"></i></div>' +
                '<div class="tx"><div class="t">' + esc(a.titulo) + '</div><div class="s">' + esc(a.detalhe) + '</div></div>' +
                '<span class="cat">' + esc(a.categoria) + '</span></a>';
        }).join('');
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('caBusca').addEventListener('input', function (e) { caBusca = e.target.value.trim().toLowerCase(); render(); });
        carregar();
        setInterval(carregar, 120000);
    });
    </script>
    <?php $sf_help_key = 'central_alertas'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
    <?php include '../includes/scroll_to_top.php'; ?>
</body>
</html>
