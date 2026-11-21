<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();
if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) { header('Location: ../login.php'); exit; }

$page_title = 'Painel Executivo';
$v = function_exists('sf_asset_v') ? sf_asset_v() : '1';
$per_ini = date('Y-m-d', strtotime('-12 months'));
$per_fim = date('Y-m-d');
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
        .pe-hero { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:16px; }
        .pe-hero h1 { font-size:1.35rem; margin:0; display:flex; align-items:center; gap:10px; }
        .pe-hero .pe-period { display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; }
        .pe-period .fg label { display:block; font-size:.72rem; color:var(--text-secondary); margin-bottom:3px; }
        .pe-period input { padding:7px 9px; border:1px solid var(--border-color); border-radius:8px; background:var(--bg-secondary); color:var(--text-primary); }
        .pe-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:14px; margin-bottom:18px; }
        .pe-card { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:14px; padding:16px 18px; }
        .pe-card--hero { grid-column:span 2; }
        .pe-card .lbl { font-size:.78rem; color:var(--text-secondary); display:flex; align-items:center; gap:6px; }
        .pe-card .val { font-size:1.8rem; font-weight:800; margin-top:4px; color:var(--text-primary); }
        .pe-card .val.pos { color:#16a34a; } .pe-card .val.neg { color:#dc2626; }
        .pe-card .sub { font-size:.74rem; color:var(--text-muted,var(--text-secondary)); margin-top:2px; }
        .pe-chart { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:14px; padding:16px; margin-bottom:18px; }
        .pe-chart h3 { font-size:.95rem; margin:0 0 10px; }
        .pe-chart-box { position:relative; height:280px; width:100%; }
        .pe-rankings { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:14px; }
        .pe-rank { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:14px; padding:16px; }
        .pe-rank h3 { font-size:.9rem; margin:0 0 10px; display:flex; align-items:center; gap:7px; }
        .pe-rank ol { margin:0; padding-left:20px; } .pe-rank li { padding:4px 0; font-size:.86rem; border-bottom:1px dashed var(--border-color); }
        .pe-rank li:last-child { border-bottom:none; } .pe-rank .v { float:right; font-weight:700; color:var(--text-primary); }
    </style>
</head>
<body class="sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content fornc-page">
                <div class="pe-hero">
                    <h1><i class="fas fa-chart-pie" style="color:var(--accent-primary,#3b82f6)"></i> Painel Executivo</h1>
                    <div class="pe-period">
                        <input type="hidden" data-sf-periodo-from id="peInicio" value="<?php echo htmlspecialchars($per_ini); ?>">
                        <input type="hidden" data-sf-periodo-to id="peFim" value="<?php echo htmlspecialchars($per_fim); ?>">
                        <button type="button" class="fornc-btn fornc-btn--ghost" data-sf-periodo data-sf-periodo-onapply="pePeriodo" title="Filtrar por período"><i class="fas fa-calendar-alt"></i> Período</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>

                <div class="pe-grid" id="peKpis">
                    <div class="pe-card pe-card--hero"><div class="lbl"><i class="fas fa-dollar-sign"></i> Faturamento</div><div class="val" id="pFat">—</div><div class="sub">No período</div></div>
                    <div class="pe-card pe-card--hero"><div class="lbl"><i class="fas fa-hand-holding-dollar"></i> Lucro</div><div class="val" id="pLucro">—</div><div class="sub" id="pMargem">margem —</div></div>
                    <div class="pe-card"><div class="lbl"><i class="fas fa-route"></i> Custo / km</div><div class="val" id="pCkm">—</div><div class="sub">Custo total ÷ km</div></div>
                    <div class="pe-card"><div class="lbl"><i class="fas fa-truck-fast"></i> KM rodado</div><div class="val" id="pKm">—</div><div class="sub" id="pRotas">— rotas</div></div>
                    <div class="pe-card"><div class="lbl"><i class="fas fa-receipt"></i> Ticket médio</div><div class="val" id="pTicket">—</div><div class="sub">Frete por rota</div></div>
                    <div class="pe-card"><div class="lbl"><i class="fas fa-truck"></i> Frota ativa</div><div class="val" id="pFrota">—</div><div class="sub">Veículos com rota / total</div></div>
                    <div class="pe-card"><div class="lbl"><i class="fas fa-gas-pump"></i> Combustível</div><div class="val" id="pComb">—</div><div class="sub">Custo no período</div></div>
                    <div class="pe-card"><div class="lbl"><i class="fas fa-wrench"></i> Manutenção</div><div class="val" id="pManut">—</div><div class="sub">Custo no período</div></div>
                </div>

                <div class="pe-chart">
                    <h3><i class="fas fa-chart-line"></i> Faturamento × Lucro por mês</h3>
                    <div class="pe-chart-box"><canvas id="peChart"></canvas></div>
                </div>

                <div class="pe-rankings">
                    <div class="pe-rank"><h3><i class="fas fa-medal"></i> Top motoristas (faturamento)</h3><ol id="rMot"></ol></div>
                    <div class="pe-rank"><h3><i class="fas fa-truck"></i> Top veículos (faturamento)</h3><ol id="rVeic"></ol></div>
                    <div class="pe-rank"><h3><i class="fas fa-map-location-dot"></i> Top rotas (frete)</h3><ol id="rRota"></ol></div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
    <script src="../js/theme.js?v=<?php echo $v; ?>"></script>
    <script src="../js/sidebar.js?v=<?php echo $v; ?>"></script>
    <script>
    (function () {
        var brl = function (n) { return 'R$ ' + (Number(n)||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); };
        var brlk = function (n) { n=Number(n)||0; return n>=1000000?('R$ '+(n/1000000).toFixed(2)+'M'):(n>=1000?('R$ '+(n/1000).toFixed(0)+'k'):brl(n)); };
        var num = function (n) { return (Number(n)||0).toLocaleString('pt-BR'); };
        var esc = function (s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;};
        var chart=null;
        function carregar(){
            var qs='?data_inicio='+document.getElementById('peInicio').value+'&data_fim='+document.getElementById('peFim').value;
            fetch('../api/painel_executivo.php'+qs,{credentials:'same-origin'}).then(function(r){return r.json();})
                .then(function(d){ if(!d.success) throw 0; render(d); }).catch(function(){});
        }
        function render(d){
            var k=d.kpis;
            document.getElementById('pFat').textContent=brl(k.faturamento);
            var l=document.getElementById('pLucro'); l.textContent=brl(k.lucro); l.className='val '+(k.lucro>=0?'pos':'neg');
            document.getElementById('pMargem').textContent='margem '+k.margem+'%';
            document.getElementById('pCkm').textContent=brl(k.custo_km);
            document.getElementById('pKm').textContent=num(k.km)+' km';
            document.getElementById('pRotas').textContent=k.rotas+' rotas';
            document.getElementById('pTicket').textContent=brl(k.ticket_medio);
            document.getElementById('pFrota').textContent=k.veic_ativos+' / '+k.veic_total;
            document.getElementById('pComb').textContent=brl(k.custo_combustivel);
            document.getElementById('pManut').textContent=brl(k.custo_manutencao);
            document.getElementById('rMot').innerHTML=(d.rankings.motoristas||[]).map(function(x){return '<li>'+esc(x.nome)+'<span class="v">'+brlk(x.fat)+'</span></li>';}).join('')||'<li>Sem dados</li>';
            document.getElementById('rVeic').innerHTML=(d.rankings.veiculos||[]).map(function(x){return '<li>'+esc(x.placa)+' <small>'+esc(x.modelo||'')+'</small><span class="v">'+brlk(x.fat)+'</span></li>';}).join('')||'<li>Sem dados</li>';
            document.getElementById('rRota').innerHTML=(d.rankings.rotas||[]).map(function(x){return '<li>'+esc((x.estado_origem||'')+' → '+(x.estado_destino||''))+'<span class="v">'+brlk(x.frete)+'</span></li>';}).join('')||'<li>Sem dados</li>';
            var ctx=document.getElementById('peChart').getContext('2d'); if(chart)chart.destroy();
            chart=new Chart(ctx,{type:'bar',data:{labels:d.tendencia.map(function(t){return t.ym;}),datasets:[
                {label:'Faturamento',data:d.tendencia.map(function(t){return t.faturamento;}),backgroundColor:'rgba(59,130,246,.6)'},
                {label:'Lucro',type:'line',data:d.tendencia.map(function(t){return t.lucro;}),borderColor:'#16a34a',backgroundColor:'rgba(22,163,74,.15)',tension:.3,fill:true}
            ]},options:{responsive:true,maintainAspectRatio:false,scales:{y:{ticks:{callback:function(v){return brlk(v);}}}}}});
        }
        window.pePeriodo = carregar;               // botão Período (sf-periodo.js)
        carregar();
    })();
    </script>
    <script src="../js/sf-periodo.js?v=<?php echo $v; ?>"></script>
    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'painel_executivo'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
