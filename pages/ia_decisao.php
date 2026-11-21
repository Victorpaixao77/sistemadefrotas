<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();
if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) { header('Location: ../login.php'); exit; }

$page_title = 'IA de Decisão · Consultor Estratégico';
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
        .id-hero { display:flex; align-items:center; gap:12px; margin-bottom:6px; }
        .id-hero h1 { font-size:1.35rem; margin:0; display:flex; align-items:center; gap:10px; }
        .id-sub { color:var(--text-secondary); font-size:.9rem; margin:0 0 18px; }
        .id-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); gap:16px; }
        .id-card { background:var(--bg-secondary); border:1px solid var(--border-color); border-left-width:5px; border-radius:14px; padding:18px; display:flex; flex-direction:column; }
        .id-card.critico{border-left-color:#dc2626;} .id-card.aviso{border-left-color:#f59e0b;} .id-card.info{border-left-color:#16a34a;}
        .id-q { font-size:.8rem; color:var(--text-secondary); display:flex; align-items:center; gap:8px; }
        .id-q i { width:30px;height:30px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;background:var(--bg-primary); }
        .id-verd { font-size:1.15rem; font-weight:800; color:var(--text-primary); margin:10px 0 6px; }
        .id-fatos { list-style:none; padding:0; margin:6px 0 14px; }
        .id-fatos li { font-size:.85rem; color:var(--text-secondary); padding:3px 0 3px 20px; position:relative; }
        .id-fatos li::before { content:"\f058"; font-family:"Font Awesome 6 Free"; font-weight:900; position:absolute; left:0; color:var(--accent-primary,#3b82f6); font-size:.75rem; top:5px; }
        .id-acao { background:var(--bg-primary); border-radius:10px; padding:10px 12px; font-size:.85rem; color:var(--text-primary); margin-top:auto; }
        .id-acao b { color:var(--accent-primary,#3b82f6); }
        .id-link { margin-top:10px; align-self:flex-start; }
        .id-empty { text-align:center; padding:40px; color:var(--text-secondary); }
    </style>
</head>
<body class="sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content fornc-page">
                <div class="fornc-toolbar">
                    <div class="fornc-search-block" style="flex:1">
                        <i class="fas fa-lightbulb"></i>
                        <strong style="margin-left:8px">IA de Decisão</strong>
                        <span style="color:var(--text-secondary);font-size:.85rem;margin-left:6px">recomendações de ação baseadas nos seus dados reais</span>
                    </div>
                    <div class="fornc-btn-row">
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="idRefresh"><i class="fas fa-arrows-rotate"></i> Recalcular</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>
                <p class="id-sub">O consultor analisa frota, motoristas, clientes e finanças (últimos 12 meses) e sugere o que fazer. Sem achismo — só o que os dados mostram.</p>
                <div class="id-grid" id="idGrid"><p class="id-empty">Analisando os dados…</p></div>
            </div>
        </div>
    </div>
    <script src="../js/theme.js?v=<?php echo $v; ?>"></script>
    <script src="../js/sidebar.js?v=<?php echo $v; ?>"></script>
    <script>
    (function () {
        var esc=function(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;};
        var LABEL={critico:'Atenção alta',aviso:'Atenção',info:'Oportunidade'};
        function carregar(){
            document.getElementById('idGrid').innerHTML='<p class="id-empty">Analisando os dados…</p>';
            fetch('../api/ia_decisao.php',{credentials:'same-origin'}).then(function(r){return r.json();})
                .then(function(d){ if(!d.success) throw 0; render(d.decisoes||[]); })
                .catch(function(){ document.getElementById('idGrid').innerHTML='<p class="id-empty" style="color:#dc3545">Erro ao gerar recomendações.</p>'; });
        }
        function render(cards){
            if(!cards.length){ document.getElementById('idGrid').innerHTML='<div class="id-empty"><h3>Tudo sob controle 👍</h3><p>Nenhuma recomendação crítica no momento — a operação está saudável.</p></div>'; return; }
            document.getElementById('idGrid').innerHTML=cards.map(function(c){
                var fatos=(c.fatos||[]).map(function(f){return '<li>'+esc(f)+'</li>';}).join('');
                return '<div class="id-card '+esc(c.nivel)+'">'+
                    '<div class="id-q"><i class="fas '+esc(c.icone)+'"></i> '+esc(c.pergunta)+'</div>'+
                    '<div class="id-verd">'+esc(c.veredito)+'</div>'+
                    '<ul class="id-fatos">'+fatos+'</ul>'+
                    '<div class="id-acao"><b>Sugestão:</b> '+esc(c.acao)+'</div>'+
                    (c.link?('<a class="fornc-btn fornc-btn--primary id-link" href="'+esc(c.link)+'"><i class="fas fa-arrow-right"></i> Abrir tela</a>'):'')+
                    '</div>';
            }).join('');
        }
        document.getElementById('idRefresh').addEventListener('click',carregar);
        carregar();
    })();
    </script>
    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'ia_decisao'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
