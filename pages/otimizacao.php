<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();
if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) { header('Location: ../login.php'); exit; }

$page_title = 'Otimização Inteligente';
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
        .ot-rec { background:linear-gradient(135deg,rgba(59,130,246,.12),rgba(16,163,74,.10)); border:1px solid var(--border-color); border-radius:16px; padding:18px; margin-bottom:18px; }
        .ot-rec h3 { margin:0 0 12px; font-size:1rem; display:flex; align-items:center; gap:8px; }
        .ot-rec-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; } @media(max-width:700px){.ot-rec-grid{grid-template-columns:1fr;}}
        .ot-pick { background:var(--bg-secondary); border-radius:12px; padding:14px 16px; }
        .ot-pick .lbl { font-size:.76rem; color:var(--text-secondary); } .ot-pick .nome { font-size:1.25rem; font-weight:800; color:var(--text-primary); margin:3px 0; }
        .ot-pick .m { font-size:.82rem; color:var(--text-secondary); }
        .ot-sim { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:14px; padding:16px; margin-bottom:18px; display:flex; gap:16px; flex-wrap:wrap; align-items:end; }
        .ot-sim .fg label { display:block; font-size:.76rem; color:var(--text-secondary); margin-bottom:4px; }
        .ot-sim .fg input { padding:8px 10px; border:1px solid var(--border-color); border-radius:8px; background:var(--bg-primary); color:var(--text-primary); width:140px; }
        .ot-sim .out { margin-left:auto; text-align:right; } .ot-sim .out .v { font-size:1.4rem; font-weight:800; } .ot-sim .out .v.pos{color:#16a34a;} .ot-sim .out .v.neg{color:#dc2626;}
        .ot-cols { display:grid; grid-template-columns:1fr 1fr; gap:16px; } @media(max-width:800px){.ot-cols{grid-template-columns:1fr;}}
        .ot-col { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:14px; padding:16px; }
        .ot-col h3 { font-size:.92rem; margin:0 0 10px; }
        .ot-item { display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px dashed var(--border-color); font-size:.88rem; } .ot-item:last-child{border-bottom:none;}
        .ot-item .nm { flex:1; } .ot-item .badge { font-size:.68rem; padding:2px 7px; border-radius:999px; } .ot-b-livre{background:rgba(22,163,74,.15);color:#16a34a;} .ot-b-uso{background:var(--bg-primary);color:var(--text-secondary);}
    </style>
</head>
<body class="sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content fornc-page">
                <div class="fornc-toolbar">
                    <div class="fornc-search-block" style="flex:1"><i class="fas fa-wand-magic-sparkles"></i><strong style="margin-left:8px">Otimização Inteligente</strong><span style="color:var(--text-secondary);font-size:.85rem;margin-left:6px">o melhor veículo e motorista para a próxima viagem</span></div>
                    <div class="fornc-btn-row">
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="otRefresh"><i class="fas fa-arrows-rotate"></i> Recalcular</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>

                <div class="ot-rec">
                    <h3><i class="fas fa-star"></i> Combinação recomendada</h3>
                    <div class="ot-rec-grid">
                        <div class="ot-pick"><div class="lbl"><i class="fas fa-truck"></i> Veículo (menor custo/km disponível)</div><div class="nome" id="recVeic">—</div><div class="m" id="recVeicM"></div></div>
                        <div class="ot-pick"><div class="lbl"><i class="fas fa-id-card"></i> Motorista (melhor score disponível)</div><div class="nome" id="recMot">—</div><div class="m" id="recMotM"></div></div>
                    </div>
                </div>

                <div class="ot-sim">
                    <div class="fg"><label>Distância (km)</label><input type="number" id="simKm" value="800" step="10"></div>
                    <div class="fg"><label>Frete (R$)</label><input type="number" id="simFrete" value="3500" step="100"></div>
                    <div class="out"><div style="font-size:.76rem;color:var(--text-secondary)">Custo estimado / Lucro (veículo recomendado)</div><div><span id="simCusto" style="font-size:1rem;color:var(--text-secondary)">—</span> · <span class="v" id="simLucro">—</span></div></div>
                </div>

                <div class="ot-cols">
                    <div class="ot-col"><h3><i class="fas fa-truck"></i> Melhores veículos (custo/km)</h3><div id="otVeics"></div></div>
                    <div class="ot-col"><h3><i class="fas fa-id-card"></i> Melhores motoristas (score)</h3><div id="otMots"></div></div>
                </div>
            </div>
        </div>
    </div>
    <script src="../js/theme.js?v=<?php echo $v; ?>"></script>
    <script src="../js/sidebar.js?v=<?php echo $v; ?>"></script>
    <script>
    (function () {
        var esc=function(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;};
        var brl=function(n){return 'R$ '+(Number(n)||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});};
        var recCkm=0;
        function badge(d){ return d? '<span class="badge ot-b-livre">livre</span>' : '<span class="badge ot-b-uso">em uso</span>'; }
        function carregar(){
            fetch('../api/otimizacao.php',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
                if(!d.success) throw 0; render(d);
            }).catch(function(){ document.getElementById('otVeics').innerHTML='<p style="color:#dc3545">Erro ao carregar.</p>'; });
        }
        function render(d){
            var rv=(d.recomendado||{}).veiculo, rm=(d.recomendado||{}).motorista;
            if(rv){ document.getElementById('recVeic').textContent=rv.placa+(rv.modelo?(' · '+rv.modelo):''); document.getElementById('recVeicM').textContent='Custo/km '+brl(rv.custo_km)+' · '+(rv.disponivel?'disponível':'em uso'); recCkm=rv.custo_km||0; }
            if(rm){ document.getElementById('recMot').textContent=rm.nome; document.getElementById('recMotM').textContent='Score '+rm.score+' · '+rm.prazo+'% no prazo · '+rm.multas+' multas'; }
            document.getElementById('otVeics').innerHTML=(d.veiculos||[]).map(function(x){
                return '<div class="ot-item"><span class="nm"><strong>'+esc(x.placa)+'</strong> <small style="color:var(--text-secondary)">'+esc(x.modelo||'')+'</small></span>'+
                    '<span>'+(x.custo_km>0?brl(x.custo_km)+'/km':'sem base')+'</span>'+badge(x.disponivel)+'</div>';
            }).join('')||'<p style="color:var(--text-secondary)">Sem veículos.</p>';
            document.getElementById('otMots').innerHTML=(d.motoristas||[]).map(function(x){
                return '<div class="ot-item"><span class="nm"><strong>'+esc(x.nome)+'</strong></span><span>score '+x.score+'</span>'+badge(x.disponivel)+'</div>';
            }).join('')||'<p style="color:var(--text-secondary)">Sem motoristas.</p>';
            sim();
        }
        function sim(){
            var km=+document.getElementById('simKm').value, frete=+document.getElementById('simFrete').value;
            var custo=km*recCkm, lucro=frete-custo;
            document.getElementById('simCusto').textContent=brl(custo);
            var l=document.getElementById('simLucro'); l.textContent=brl(lucro); l.className='v '+(lucro>=0?'pos':'neg');
        }
        document.getElementById('simKm').addEventListener('input',sim);
        document.getElementById('simFrete').addEventListener('input',sim);
        document.getElementById('otRefresh').addEventListener('click',carregar);
        carregar();
    })();
    </script>
    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'otimizacao'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
