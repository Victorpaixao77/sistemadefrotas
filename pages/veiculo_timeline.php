<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();
if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) { header('Location: ../login.php'); exit; }

$page_title = 'Timeline do Veículo';
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
        .tl-sum { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:12px; margin:6px 0 18px; }
        .tl-s { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:12px; padding:12px 14px; }
        .tl-s b { display:block; font-size:1.4rem; font-weight:800; } .tl-s small { color:var(--text-secondary); font-size:.76rem; }
        .tl-chips { display:flex; gap:7px; flex-wrap:wrap; margin-bottom:16px; }
        .tl-chip { padding:6px 12px; border-radius:999px; border:1px solid var(--border-color); background:var(--bg-secondary); color:var(--text-secondary); cursor:pointer; font-size:.8rem; font-weight:600; }
        .tl-chip.active { background:var(--accent-primary,#3b82f6); color:#fff; border-color:transparent; }
        .tl-line { position:relative; margin-left:14px; padding-left:26px; border-left:2px solid var(--border-color); }
        .tl-ev { position:relative; padding:0 0 20px; }
        .tl-ev .dot { position:absolute; left:-35px; width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-size:.8rem; }
        .tl-ev .box { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:10px; padding:10px 14px; }
        .tl-ev .top { display:flex; justify-content:space-between; gap:10px; align-items:baseline; flex-wrap:wrap; }
        .tl-ev .t { font-weight:700; color:var(--text-primary); } .tl-ev .d { font-size:.75rem; color:var(--text-secondary); }
        .tl-ev .s { font-size:.84rem; color:var(--text-secondary); margin-top:2px; }
        .tl-ev .v { font-weight:700; } .tl-ev a { color:inherit; text-decoration:none; }
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
                        <label for="tlBusca">Busca rápida</label>
                        <div class="fornc-search-inner">
                            <i class="fas fa-search"></i>
                            <input type="text" id="tlBusca" placeholder="Filtrar eventos (posto, descrição...)">
                        </div>
                    </div>
                    <div class="fornc-filters-inline">
                        <div class="fg">
                            <label for="tlVeic">Veículo</label>
                            <select id="tlVeic"><option value="">Selecione um veículo…</option></select>
                        </div>
                        <div class="fg">
                            <label for="tlTipo">Tipo de evento</label>
                            <select id="tlTipo">
                                <option value="">Todos</option>
                                <option value="rota">Rotas</option>
                                <option value="abastecimento">Abastecimentos</option>
                                <option value="manutencao">Manutenções</option>
                                <option value="multa">Multas</option>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" data-sf-periodo-from id="tlIni">
                    <input type="hidden" data-sf-periodo-to id="tlFim">
                    <div class="fornc-btn-row">
                        <button type="button" class="fornc-btn fornc-btn--ghost" data-sf-periodo data-sf-periodo-onapply="tlPeriodo" title="Filtrar por período"><i class="fas fa-calendar-alt"></i> Período</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="tlRefresh"><i class="fas fa-arrows-rotate"></i> Atualizar</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>

                <div class="tl-sum" id="tlSum" style="display:none">
                    <div class="tl-s"><b id="tlRota" style="color:#16a34a">—</b><small>Rotas</small></div>
                    <div class="tl-s"><b id="tlAbast" style="color:#3b82f6">—</b><small>Abastecimentos</small></div>
                    <div class="tl-s"><b id="tlManut" style="color:#f59e0b">—</b><small>Manutenções</small></div>
                    <div class="tl-s"><b id="tlMulta" style="color:#dc2626">—</b><small>Multas</small></div>
                </div>

                <div id="tlLista"><div class="fornc-empty-state"><h3>Selecione um veículo</h3><p>Escolha um veículo para ver o histórico completo (rotas, abastecimentos, manutenções e multas) em ordem cronológica.</p></div></div>
            </div>
        </div>
    </div>
    <script src="../js/theme.js?v=<?php echo $v; ?>"></script>
    <script src="../js/sidebar.js?v=<?php echo $v; ?>"></script>
    <script>
    (function () {
        var brl=function(n){return 'R$ '+(Number(n)||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});};
        var esc=function(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;};
        var MES=['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
        var eventos=[];

        fetch('../api/veiculo_timeline.php?action=veiculos',{credentials:'same-origin'}).then(function(r){return r.json();})
            .then(function(d){ var sel=document.getElementById('tlVeic'); (d.veiculos||[]).forEach(function(v){ var o=document.createElement('option'); o.value=v.id; o.textContent=v.placa+(v.modelo?(' · '+v.modelo):''); sel.appendChild(o); }); });

        function carregar(){
            var vid=document.getElementById('tlVeic').value;
            if(!vid){ return; }
            document.getElementById('tlLista').innerHTML='<p style="color:var(--text-secondary)">Carregando…</p>';
            fetch('../api/veiculo_timeline.php?veiculo_id='+vid,{credentials:'same-origin'}).then(function(r){return r.json();})
                .then(function(d){ if(!d.success) throw 0; eventos=d.eventos||[]; resumo(d.resumo||{}); render(); })
                .catch(function(){ document.getElementById('tlLista').innerHTML='<p style="color:#dc3545">Erro ao carregar.</p>'; });
        }
        function resumo(r){
            document.getElementById('tlSum').style.display='';
            document.getElementById('tlRota').textContent=r.rota||0; document.getElementById('tlAbast').textContent=r.abastecimento||0;
            document.getElementById('tlManut').textContent=r.manutencao||0; document.getElementById('tlMulta').textContent=r.multa||0;
        }
        function render(){
            var tipo=document.getElementById('tlTipo').value;
            var termo=(document.getElementById('tlBusca').value||'').toLowerCase();
            var ini=document.getElementById('tlIni').value, fim=document.getElementById('tlFim').value;
            var lista=eventos.filter(function(e){
                if(tipo && e.tipo!==tipo) return false;
                if(termo && ((e.titulo||'')+' '+(e.detalhe||'')).toLowerCase().indexOf(termo)<0) return false;
                var d=(e.dt||'').slice(0,10);
                if(ini && d && d<ini) return false;
                if(fim && d && d>fim) return false;
                return true;
            });
            if(!lista.length){ document.getElementById('tlLista').innerHTML='<div class="fornc-empty-state"><h3>Sem eventos</h3><p>Nenhum evento para este filtro.</p></div>'; return; }
            var html='<div class="tl-line">';
            lista.forEach(function(e){
                var dt=new Date((e.dt||'').replace(' ','T'));
                var data=isNaN(dt)?esc(e.dt):(dt.getDate()+' '+MES[dt.getMonth()]+' '+dt.getFullYear());
                html+='<div class="tl-ev"><div class="dot" style="background:'+esc(e.cor)+'"><i class="fas '+esc(e.icone)+'"></i></div>'+
                    '<a href="'+esc(e.link||'#')+'"><div class="box"><div class="top"><span class="t">'+esc(e.titulo)+'</span><span class="d">'+data+'</span></div>'+
                    (e.detalhe?('<div class="s">'+esc(e.detalhe)+'</div>'):'')+
                    (e.valor?('<div class="s"><span class="v" style="color:'+esc(e.cor)+'">'+brl(e.valor)+'</span></div>'):'')+
                    '</div></a></div>';
            });
            html+='</div>';
            document.getElementById('tlLista').innerHTML=html;
        }
        window.tlPeriodo=function(){ if(eventos.length) render(); };   // botão Período (sf-periodo.js)
        document.getElementById('tlVeic').addEventListener('change',carregar);
        document.getElementById('tlTipo').addEventListener('change',function(){ if(eventos.length) render(); });
        document.getElementById('tlBusca').addEventListener('input',function(){ if(eventos.length) render(); });
        document.getElementById('tlRefresh').addEventListener('click',carregar);
    })();
    </script>
    <script src="../js/sf-periodo.js?v=<?php echo $v; ?>"></script>
    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'veiculo_timeline'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
