<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();
if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) { header('Location: ../login.php'); exit; }

$page_title = 'SLA Operacional';
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
        .sla-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:14px; margin:6px 0 18px; }
        .sla-k { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:12px; padding:14px 16px; }
        .sla-k .l { font-size:.78rem; color:var(--text-secondary); } .sla-k .v { font-size:1.6rem; font-weight:800; margin-top:4px; }
        .sla-k .v.big{font-size:2rem;} .sla-k .v.ok{color:#16a34a;} .sla-k .v.warn{color:#f59e0b;} .sla-k .v.bad{color:#dc2626;}
        .sla-chart { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:12px; padding:16px; margin-bottom:18px; }
        .sla-chart h3 { font-size:.95rem; margin:0 0 10px; } .sla-chart-box { position:relative; height:260px; }
        .sla-bar { height:8px; border-radius:6px; background:var(--bg-primary); overflow:hidden; } .sla-bar > i { display:block; height:100%; }
    </style>
</head>
<body class="sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content fornc-page">
                <div class="fornc-toolbar">
                    <div class="fornc-search-block" style="flex:1"><i class="fas fa-gauge-high"></i><strong style="margin-left:8px">SLA Operacional</strong><span style="color:var(--text-secondary);font-size:.85rem;margin-left:6px">nível de serviço das viagens (entregas no prazo)</span></div>
                    <input type="hidden" data-sf-periodo-from id="slaIni" value="<?php echo htmlspecialchars($per_ini); ?>">
                    <input type="hidden" data-sf-periodo-to id="slaFim" value="<?php echo htmlspecialchars($per_fim); ?>">
                    <div class="fornc-btn-row">
                        <button type="button" class="fornc-btn fornc-btn--ghost" data-sf-periodo data-sf-periodo-onapply="slaPeriodo" title="Filtrar por período"><i class="fas fa-calendar-alt"></i> Período</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>

                <div class="sla-kpis">
                    <div class="sla-k"><div class="l"><i class="fas fa-bullseye"></i> SLA (no prazo)</div><div class="v big" id="slaPct">—</div></div>
                    <div class="sla-k"><div class="l">Viagens</div><div class="v" id="slaTotal">—</div></div>
                    <div class="sla-k"><div class="l">No prazo</div><div class="v ok" id="slaOk">—</div></div>
                    <div class="sla-k"><div class="l">Atrasadas</div><div class="v bad" id="slaAtraso">—</div></div>
                    <div class="sla-k"><div class="l">Tempo médio de viagem</div><div class="v" id="slaHoras">—</div></div>
                </div>

                <div class="sla-chart">
                    <h3><i class="fas fa-chart-line"></i> Evolução do SLA (% no prazo) por mês</h3>
                    <div class="sla-chart-box"><canvas id="slaChart"></canvas></div>
                </div>

                <div class="fornc-table-wrap">
                    <table class="fornc-table"><thead><tr><th>Motorista</th><th>Viagens</th><th>SLA</th><th></th></tr></thead>
                    <tbody id="slaTbody"><tr><td colspan="4" style="text-align:center;padding:24px;color:var(--text-secondary)">Carregando…</td></tr></tbody></table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
    <script src="../js/theme.js?v=<?php echo $v; ?>"></script>
    <script src="../js/sidebar.js?v=<?php echo $v; ?>"></script>
    <script>
    (function () {
        var esc=function(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;};
        var chart=null;
        function cls(p){ return p>=90?'ok':(p>=75?'warn':'bad'); }
        function cor(p){ return p>=90?'#16a34a':(p>=75?'#f59e0b':'#dc2626'); }
        function carregar(){
            var qs='?data_inicio='+document.getElementById('slaIni').value+'&data_fim='+document.getElementById('slaFim').value;
            fetch('../api/sla_operacional.php'+qs,{credentials:'same-origin'}).then(function(r){return r.json();})
                .then(function(d){ if(!d.success) throw 0; render(d); }).catch(function(){ document.getElementById('slaTbody').innerHTML='<tr><td colspan="4" style="text-align:center;padding:20px;color:#dc3545">Erro ao carregar.</td></tr>'; });
        }
        function render(d){
            var r=d.resumo;
            var pe=document.getElementById('slaPct'); pe.textContent=r.pct_no_prazo+'%'; pe.className='v big '+cls(r.pct_no_prazo);
            document.getElementById('slaTotal').textContent=r.total;
            document.getElementById('slaOk').textContent=r.no_prazo;
            document.getElementById('slaAtraso').textContent=r.atrasadas;
            document.getElementById('slaHoras').textContent=(r.horas_media||0)+' h';
            document.getElementById('slaTbody').innerHTML=(d.motoristas||[]).map(function(m){
                var p=+m.pct;
                return '<tr><td><strong>'+esc(m.nome)+'</strong></td><td>'+m.rotas+'</td><td style="color:'+cor(p)+';font-weight:700">'+p+'%</td>'+
                    '<td style="width:160px"><div class="sla-bar"><i style="width:'+p+'%;background:'+cor(p)+'"></i></div></td></tr>';
            }).join('')||'<tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text-secondary)">Sem viagens no período.</td></tr>';
            var ctx=document.getElementById('slaChart').getContext('2d'); if(chart)chart.destroy();
            chart=new Chart(ctx,{type:'line',data:{labels:(d.evolucao||[]).map(function(e){return e.ym;}),datasets:[{label:'SLA %',data:(d.evolucao||[]).map(function(e){return e.pct;}),borderColor:'#16a34a',backgroundColor:'rgba(22,163,74,.12)',fill:true,tension:.3,pointRadius:3}]},
                options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{min:0,max:100,ticks:{callback:function(v){return v+'%';}}}}}});
        }
        window.slaPeriodo=carregar;
        carregar();
    })();
    </script>
    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'sla_operacional'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
    <script src="../js/sf-periodo.js?v=<?php echo $v; ?>"></script>
</body>
</html>
