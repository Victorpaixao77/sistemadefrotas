<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();
if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) { header('Location: ../login.php'); exit; }

$page_title = 'Scores · Motoristas, Veículos e Clientes';
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
        .sc-tabs { display:flex; gap:8px; margin:6px 0 16px; flex-wrap:wrap; }
        .sc-tab { padding:8px 16px; border:1px solid var(--border-color); border-radius:999px; background:var(--bg-secondary); color:var(--text-secondary); cursor:pointer; font-weight:600; font-size:.85rem; }
        .sc-tab.active { background:var(--accent-primary,#3b82f6); color:#fff; border-color:transparent; }
        .sc-panel { display:none; } .sc-panel.active { display:block; }
        .sc-ball { width:52px; height:52px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:1.05rem; color:#fff; }
        .sc-b-exc{background:#16a34a;} .sc-b-bom{background:#3b82f6;} .sc-b-reg{background:#f59e0b;} .sc-b-at{background:#dc2626;}
        .sc-faixa { font-size:.72rem; font-weight:700; }
        .sc-pos { color:#16a34a; } .sc-neg { color:#dc2626; }
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
                        <label for="scBusca">Busca rápida</label>
                        <div class="fornc-search-inner">
                            <i class="fas fa-search"></i>
                            <input type="text" id="scBusca" placeholder="Motorista, veículo ou cliente...">
                        </div>
                    </div>
                    <input type="hidden" data-sf-periodo-from id="scInicio" value="<?php echo htmlspecialchars($per_ini); ?>">
                    <input type="hidden" data-sf-periodo-to id="scFim" value="<?php echo htmlspecialchars($per_fim); ?>">
                    <div class="fornc-btn-row">
                        <button type="button" class="fornc-btn fornc-btn--ghost" data-sf-periodo data-sf-periodo-onapply="scPeriodo" title="Filtrar por período"><i class="fas fa-calendar-alt"></i> Período</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>

                <div class="sc-tabs">
                    <button type="button" class="sc-tab active" data-p="mot"><i class="fas fa-id-card"></i> Motoristas</button>
                    <button type="button" class="sc-tab" data-p="veic"><i class="fas fa-truck"></i> Veículos</button>
                    <button type="button" class="sc-tab" data-p="cli"><i class="fas fa-handshake"></i> Clientes</button>
                </div>

                <div class="sc-panel active" id="panel-mot">
                    <div class="fornc-table-wrap"><table class="fornc-table"><thead><tr><th>Score</th><th>Motorista</th><th>Rotas</th><th>No prazo</th><th>Multas</th></tr></thead><tbody id="tbMot"></tbody></table></div>
                </div>
                <div class="sc-panel" id="panel-veic">
                    <div class="fornc-table-wrap"><table class="fornc-table"><thead><tr><th>Score</th><th>Veículo</th><th>Rotas</th><th>Manutenções</th></tr></thead><tbody id="tbVeic"></tbody></table></div>
                </div>
                <div class="sc-panel" id="panel-cli">
                    <div class="fornc-table-wrap"><table class="fornc-table"><thead><tr><th>Score</th><th>Cliente</th><th>OTs</th><th>Faturamento</th><th>Margem</th></tr></thead><tbody id="tbCli"></tbody></table></div>
                </div>
            </div>
        </div>
    </div>
    <script src="../js/theme.js?v=<?php echo $v; ?>"></script>
    <script src="../js/sidebar.js?v=<?php echo $v; ?>"></script>
    <script>
    (function () {
        var brl=function(n){return 'R$ '+(Number(n)||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});};
        var esc=function(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;};
        var data=null;
        function ball(score,faixa){var c=score>=85?'sc-b-exc':score>=70?'sc-b-bom':score>=50?'sc-b-reg':'sc-b-at';return '<span class="sc-ball '+c+'">'+score+'</span>';}

        function carregar(){
            var qs='?data_inicio='+document.getElementById('scInicio').value+'&data_fim='+document.getElementById('scFim').value;
            fetch('../api/scores_consolidado.php'+qs,{credentials:'same-origin'}).then(function(r){return r.json();})
                .then(function(d){ if(!d.success) throw 0; data=d; render(); }).catch(function(){});
        }
        function filtro(rows,campos){var t=(document.getElementById('scBusca').value||'').toLowerCase();if(!t)return rows;return rows.filter(function(x){return campos.some(function(c){return (''+(x[c]||'')).toLowerCase().indexOf(t)>=0;});});}
        function render(){
            var mot=filtro(data.motoristas||[],['nome']);
            document.getElementById('tbMot').innerHTML=mot.map(function(x){return '<tr><td>'+ball(x.score,x.faixa)+' <span class="sc-faixa">'+esc(x.faixa)+'</span></td><td><strong>'+esc(x.nome)+'</strong></td><td>'+x.rotas+'</td><td>'+x.pct_prazo+'%</td><td>'+x.multas+'</td></tr>';}).join('')||'<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-secondary)">Sem dados no período.</td></tr>';
            var veic=filtro(data.veiculos||[],['placa','modelo']);
            document.getElementById('tbVeic').innerHTML=veic.map(function(x){return '<tr><td>'+ball(x.score,x.faixa)+' <span class="sc-faixa">'+esc(x.faixa)+'</span></td><td><strong>'+esc(x.placa)+'</strong> <small>'+esc(x.modelo||'')+'</small></td><td>'+x.rotas+'</td><td>'+x.manutencoes+'</td></tr>';}).join('')||'<tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text-secondary)">Sem dados.</td></tr>';
            var cli=filtro(data.clientes||[],['nome']);
            document.getElementById('tbCli').innerHTML=cli.map(function(x){return '<tr><td>'+ball(x.score,x.faixa)+' <span class="sc-faixa">'+esc(x.faixa)+'</span></td><td><strong>'+esc(x.nome)+'</strong></td><td>'+x.ots+'</td><td>'+brl(x.faturamento)+'</td><td class="'+(x.margem>=0?'sc-pos':'sc-neg')+'">'+brl(x.margem)+'</td></tr>';}).join('')||'<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-secondary)">Sem ordens de transporte com cliente.</td></tr>';
        }
        document.querySelectorAll('.sc-tab').forEach(function(t){t.addEventListener('click',function(){
            document.querySelectorAll('.sc-tab').forEach(function(x){x.classList.remove('active');}); t.classList.add('active');
            document.querySelectorAll('.sc-panel').forEach(function(x){x.classList.remove('active');});
            document.getElementById('panel-'+t.dataset.p).classList.add('active');
        });});
        window.scPeriodo = carregar;               // botão Período (sf-periodo.js)
        document.getElementById('scBusca').addEventListener('input',function(){ if(data) render(); });
        carregar();
    })();
    </script>
    <script src="../js/sf-periodo.js?v=<?php echo $v; ?>"></script>
    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'scores'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
