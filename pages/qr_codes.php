<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();
if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) { header('Location: ../login.php'); exit; }

$page_title = 'QR Codes';
$v = function_exists('sf_asset_v') ? sf_asset_v() : '1';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" type="image/png" href="../logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="../css/theme.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="../css/responsive.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="../css/fornc-modern-page.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="../css/sf-premium.css?v=<?php echo $v; ?>">
    <?php sf_page_help_css('..'); ?>
    <style>
        .qr-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(170px,1fr)); gap:14px; }
        .qr-card { background:#fff; border:1px solid var(--border-color); border-radius:12px; padding:14px; text-align:center; }
        .qr-card .qr { display:flex; justify-content:center; margin-bottom:8px; }
        .qr-card .lbl { font-weight:800; color:#111; font-size:.95rem; word-break:break-word; }
        .qr-card .sub { color:#555; font-size:.76rem; }
        @media print {
            .app-container > .sidebar, .top-header, .fornc-toolbar, #scroll-to-top { display:none !important; }
            .main-content { margin:0 !important; } body, .main-content, .dashboard-content { background:#fff !important; }
            .qr-grid { grid-template-columns:repeat(4,1fr); }
        }
    </style>
</head>
<body class="sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content fornc-page">
                <div class="fornc-toolbar">
                    <div class="fornc-search-block">
                        <label for="qrBusca">Busca rápida</label>
                        <div class="fornc-search-inner">
                            <i class="fas fa-search"></i>
                            <input type="text" id="qrBusca" placeholder="Filtrar itens...">
                        </div>
                    </div>
                    <div class="fornc-filters-inline">
                        <div class="fg">
                            <label for="qrTipo">Tipo</label>
                            <select id="qrTipo">
                                <option value="veiculos">Veículos</option>
                                <option value="pneus">Pneus</option>
                                <option value="implementos">Implementos</option>
                                <option value="motoristas">Motoristas</option>
                            </select>
                        </div>
                    </div>
                    <div class="fornc-btn-row">
                        <button type="button" class="fornc-btn fornc-btn--primary" id="qrPrint"><i class="fas fa-print"></i> Imprimir</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>
                <div class="qr-grid" id="qrGrid"><p style="color:var(--text-secondary)">Carregando…</p></div>
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="../js/theme.js?v=<?php echo $v; ?>"></script>
    <script src="../js/sidebar.js?v=<?php echo $v; ?>"></script>
    <script>
    (function () {
        var esc=function(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;};
        var itens=[], base=window.location.origin + '<?php echo htmlspecialchars(rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/sistema-frotas/pages/x')), '/')); ?>';
        function carregar(){
            document.getElementById('qrGrid').innerHTML='<p style="color:var(--text-secondary)">Carregando…</p>';
            fetch('../api/qr_entidades.php?tipo='+document.getElementById('qrTipo').value,{credentials:'same-origin'})
                .then(function(r){return r.json();}).then(function(d){ if(!d.success) throw 0; itens=d.itens||[]; render(); })
                .catch(function(){ document.getElementById('qrGrid').innerHTML='<p style="color:#dc3545">Erro ao carregar.</p>'; });
        }
        function render(){
            var t=(document.getElementById('qrBusca').value||'').toLowerCase();
            var lista=itens.filter(function(x){ return !t || (x.label+' '+(x.sub||'')).toLowerCase().indexOf(t)>=0; });
            var grid=document.getElementById('qrGrid');
            if(!lista.length){ grid.innerHTML='<div class="fornc-empty-state"><h3>Nada para gerar</h3><p>Nenhum item cadastrado deste tipo.</p></div>'; return; }
            grid.innerHTML='';
            lista.forEach(function(x){
                var card=document.createElement('div'); card.className='qr-card';
                var q=document.createElement('div'); q.className='qr';
                card.appendChild(q);
                var lbl=document.createElement('div'); lbl.className='lbl'; lbl.textContent=x.label; card.appendChild(lbl);
                if(x.sub){ var s=document.createElement('div'); s.className='sub'; s.textContent=x.sub; card.appendChild(s); }
                grid.appendChild(card);
                var url=base+'/'+x.path;
                try { new QRCode(q,{text:url,width:120,height:120,correctLevel:QRCode.CorrectLevel.M}); } catch(e){ q.textContent='QR'; }
            });
        }
        document.getElementById('qrTipo').addEventListener('change',carregar);
        document.getElementById('qrBusca').addEventListener('input',render);
        document.getElementById('qrPrint').addEventListener('click',function(){ window.print(); });
        carregar();
    })();
    </script>
    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'qr_codes'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
