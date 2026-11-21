<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();
if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) { header('Location: ../login.php'); exit; }

$page_title = 'Previsão Financeira';
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
        .pf-chart { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:14px; padding:16px; margin-bottom:18px; }
        .pf-chart h3 { font-size:.95rem; margin:0 0 10px; } .pf-chart-box { position:relative; height:300px; }
        .pf-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:14px; }
        .pf-card { background:var(--bg-secondary); border:1px solid var(--border-color); border-left:4px solid #8b5cf6; border-radius:12px; padding:16px; }
        .pf-card .m { font-size:.8rem; color:var(--text-secondary); text-transform:capitalize; }
        .pf-card .row { display:flex; justify-content:space-between; font-size:.9rem; padding:3px 0; }
        .pf-card .row b { font-weight:700; } .pf-card .lucro.pos{color:#16a34a;} .pf-card .lucro.neg{color:#dc2626;}
        .pf-note { font-size:.8rem; color:var(--text-muted,var(--text-secondary)); margin-top:6px; }
    </style>
</head>
<body class="sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content fornc-page">
                <div class="fornc-toolbar">
                    <div class="fornc-search-block" style="flex:1"><i class="fas fa-chart-line"></i><strong style="margin-left:8px">Previsão Financeira</strong><span style="color:var(--text-secondary);font-size:.85rem;margin-left:6px">tendência e projeção dos próximos 3 meses</span></div>
                    <div class="fornc-btn-row">
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="pfRefresh"><i class="fas fa-arrows-rotate"></i> Atualizar</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>

                <div class="pf-chart">
                    <h3><i class="fas fa-wave-square"></i> Histórico e projeção — Faturamento e Lucro</h3>
                    <div class="pf-chart-box"><canvas id="pfChart"></canvas></div>
                    <p class="pf-note">Linha tracejada = projeção por tendência (regressão linear sobre o histórico). É uma estimativa — quanto mais consistente o histórico, mais confiável.</p>
                </div>

                <h3 style="font-size:.95rem;margin:0 0 10px"><i class="fas fa-forward"></i> Projeção dos próximos meses</h3>
                <div class="pf-cards" id="pfCards"><p style="color:var(--text-secondary)">Carregando…</p></div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
    <script src="../js/theme.js?v=<?php echo $v; ?>"></script>
    <script src="../js/sidebar.js?v=<?php echo $v; ?>"></script>
    <script>
    (function () {
        var brl=function(n){return 'R$ '+(Number(n)||0).toLocaleString('pt-BR',{minimumFractionDigits:0,maximumFractionDigits:0});};
        var chart=null;
        function carregar(){
            fetch('../api/previsao_financeira.php',{credentials:'same-origin'}).then(function(r){return r.json();})
                .then(function(d){ if(!d.success) throw 0; render(d); })
                .catch(function(){ document.getElementById('pfCards').innerHTML='<p style="color:#dc3545">Erro ao carregar.</p>'; });
        }
        function render(d){
            var hist=d.historico||[], prev=d.previsao||[];
            var labels=hist.map(function(h){return h.ym;}).concat(prev.map(function(p){return p.ym;}));
            var nH=hist.length;
            // faturamento: histórico sólido + projeção (null no histórico, valores na projeção, com ponte)
            var fatH=hist.map(function(h){return h.faturamento;});
            var fatP=labels.map(function(l,i){ if(i<nH-1) return null; if(i===nH-1) return hist[nH-1].faturamento; var p=prev[i-nH]; return p?p.faturamento:null; });
            var lucH=hist.map(function(h){return h.lucro;});
            var lucP=labels.map(function(l,i){ if(i<nH-1) return null; if(i===nH-1) return hist[nH-1].lucro; var p=prev[i-nH]; return p?p.lucro:null; });
            var ctx=document.getElementById('pfChart').getContext('2d'); if(chart)chart.destroy();
            chart=new Chart(ctx,{data:{labels:labels,datasets:[
                {type:'line',label:'Faturamento',data:fatH.concat(new Array(prev.length).fill(null)),borderColor:'#3b82f6',backgroundColor:'rgba(59,130,246,.1)',fill:true,tension:.3},
                {type:'line',label:'Faturamento (previsão)',data:fatP,borderColor:'#3b82f6',borderDash:[6,4],pointStyle:'triangle',tension:.3},
                {type:'line',label:'Lucro',data:lucH.concat(new Array(prev.length).fill(null)),borderColor:'#16a34a',tension:.3},
                {type:'line',label:'Lucro (previsão)',data:lucP,borderColor:'#16a34a',borderDash:[6,4],pointStyle:'triangle',tension:.3}
            ]},options:{responsive:true,maintainAspectRatio:false,scales:{y:{ticks:{callback:function(v){return brl(v);}}}}}});

            document.getElementById('pfCards').innerHTML = prev.length ? prev.map(function(p){
                var lc=p.lucro>=0?'pos':'neg';
                return '<div class="pf-card"><div class="m">'+p.ym+'</div>'+
                    '<div class="row"><span>Faturamento</span><b>'+brl(p.faturamento)+'</b></div>'+
                    '<div class="row"><span>Custo</span><b>'+brl(p.custo)+'</b></div>'+
                    '<div class="row lucro '+lc+'"><span>Lucro previsto</span><b>'+brl(p.lucro)+'</b></div></div>';
            }).join('') : '<div class="fornc-empty-state"><h3>Histórico insuficiente</h3><p>São necessários pelo menos 3 meses de dados para projetar.</p></div>';
        }
        document.getElementById('pfRefresh').addEventListener('click',carregar);
        carregar();
    })();
    </script>
    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'previsao_financeira'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
