<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();
if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) { header('Location: ../login.php'); exit; }

$page_title = 'Simulação de Crescimento';
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
        .sg-wrap { display:grid; grid-template-columns:1fr 1fr; gap:18px; } @media(max-width:900px){.sg-wrap{grid-template-columns:1fr;}}
        .sg-card { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:14px; padding:18px; }
        .sg-card h3 { font-size:.95rem; margin:0 0 14px; display:flex; align-items:center; gap:8px; }
        .sg-ctrl { margin-bottom:14px; } .sg-ctrl label { display:block; font-size:.82rem; color:var(--text-secondary); margin-bottom:5px; }
        .sg-ctrl label b { color:var(--text-primary); }
        .sg-row { display:flex; gap:8px; align-items:center; } .sg-row input[type=range]{flex:1;} .sg-row input[type=number]{width:110px;padding:6px 8px;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-primary);color:var(--text-primary);}
        .sg-res { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .sg-kpi { background:var(--bg-primary); border-radius:10px; padding:12px 14px; }
        .sg-kpi .l { font-size:.76rem; color:var(--text-secondary); } .sg-kpi .v { font-size:1.35rem; font-weight:800; margin-top:3px; }
        .sg-kpi .v.pos{color:#16a34a;} .sg-kpi .v.neg{color:#dc2626;}
        .sg-verdict { margin-top:14px; padding:12px 14px; border-radius:10px; font-size:.9rem; }
        .sg-verdict.ok{background:rgba(22,163,74,.12);color:#16a34a;} .sg-verdict.no{background:rgba(220,38,38,.12);color:#dc2626;}
        .sg-base { font-size:.78rem; color:var(--text-muted,var(--text-secondary)); margin-top:8px; }
    </style>
</head>
<body class="sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content fornc-page">
                <div class="fornc-toolbar">
                    <div class="fornc-search-block" style="flex:1"><i class="fas fa-arrow-trend-up"></i><strong style="margin-left:8px">Simulação de Crescimento</strong><span style="color:var(--text-secondary);font-size:.85rem;margin-left:6px">e se eu expandir a frota? Veja lucro, ROI e payback</span></div>
                    <div class="fornc-btn-row">
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>

                <div class="sg-wrap">
                    <div class="sg-card">
                        <h3><i class="fas fa-sliders"></i> Cenário</h3>
                        <div class="sg-ctrl"><label>Novos caminhões: <b><span id="lNveic">2</span></b></label>
                            <div class="sg-row"><input type="range" id="nveic" min="0" max="50" step="1" value="2"><input type="number" id="nveicN" min="0" value="2"></div></div>
                        <div class="sg-ctrl"><label>Investimento por caminhão: <b>R$ <span id="lPreco">450.000</span></b></label>
                            <div class="sg-row"><input type="range" id="preco" min="80000" max="1200000" step="10000" value="450000"><input type="number" id="precoN" step="10000" value="450000"></div></div>
                        <div class="sg-ctrl"><label>Lucro médio por caminhão/mês: <b>R$ <span id="lLucro">8.000</span></b></label>
                            <div class="sg-row"><input type="range" id="lucro" min="-5000" max="60000" step="500" value="8000"><input type="number" id="lucroN" step="500" value="8000"></div>
                            <div class="sg-base" id="baseLucro"></div></div>
                        <div class="sg-ctrl"><label>Novos motoristas: <b><span id="lNmot">2</span></b> · custo/mês cada: <b>R$ <span id="lCmot">4.500</span></b></label>
                            <div class="sg-row"><input type="range" id="nmot" min="0" max="60" step="1" value="2"><input type="number" id="cmotN" step="100" value="4500"></div></div>
                    </div>

                    <div class="sg-card">
                        <h3><i class="fas fa-chart-simple"></i> Projeção</h3>
                        <div class="sg-res">
                            <div class="sg-kpi"><div class="l">Investimento</div><div class="v" id="rInv">—</div></div>
                            <div class="sg-kpi"><div class="l">Lucro adicional / mês</div><div class="v" id="rLucroMes">—</div></div>
                            <div class="sg-kpi"><div class="l">Lucro adicional / ano</div><div class="v" id="rLucroAno">—</div></div>
                            <div class="sg-kpi"><div class="l">ROI ao ano</div><div class="v" id="rRoi">—</div></div>
                            <div class="sg-kpi"><div class="l">Payback</div><div class="v" id="rPayback">—</div></div>
                            <div class="sg-kpi"><div class="l">Faturamento adicional/ano</div><div class="v" id="rFat">—</div></div>
                        </div>
                        <div class="sg-verdict" id="rVerdict">—</div>
                        <p class="sg-base" id="baseInfo"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../js/theme.js?v=<?php echo $v; ?>"></script>
    <script src="../js/sidebar.js?v=<?php echo $v; ?>"></script>
    <script>
    (function () {
        var brl=function(n){return 'R$ '+(Number(n)||0).toLocaleString('pt-BR',{minimumFractionDigits:0,maximumFractionDigits:0});};
        var g=function(id){return document.getElementById(id);};
        function calc(){
            var nv=+g('nveic').value, preco=+g('preco').value, lucroV=+g('lucro').value, nm=+g('nmot').value, cmot=+g('cmotN').value;
            g('lNveic').textContent=nv; g('lPreco').textContent=(+preco).toLocaleString('pt-BR'); g('lLucro').textContent=(+lucroV).toLocaleString('pt-BR');
            g('lNmot').textContent=nm; g('lCmot').textContent=(+cmot).toLocaleString('pt-BR');
            var inv=nv*preco;
            var lucroMes=nv*lucroV - nm*cmot;
            var lucroAno=lucroMes*12;
            var roi= inv>0 ? (lucroAno/inv*100) : 0;
            var payback= lucroMes>0 ? (inv/lucroMes) : null;   // meses
            var fatAdd= nv * (window.__fatVeicMes||0) * 12;
            g('rInv').textContent=brl(inv);
            var lm=g('rLucroMes'); lm.textContent=brl(lucroMes); lm.className='v '+(lucroMes>=0?'pos':'neg');
            var la=g('rLucroAno'); la.textContent=brl(lucroAno); la.className='v '+(lucroAno>=0?'pos':'neg');
            g('rRoi').textContent=(inv>0?roi.toFixed(1)+'%':'—');
            g('rPayback').textContent= payback? (payback<12? payback.toFixed(1)+' meses' : (payback/12).toFixed(1)+' anos') : 'não paga';
            g('rFat').textContent=brl(fatAdd);
            var vd=g('rVerdict');
            if(nv===0){ vd.className='sg-verdict'; vd.textContent='Ajuste o cenário para simular.'; }
            else if(lucroMes>0 && roi>=15){ vd.className='sg-verdict ok'; vd.innerHTML='<i class="fas fa-circle-check"></i> Faz sentido — ROI de '+roi.toFixed(0)+'% ao ano e payback em '+(payback<12?payback.toFixed(0)+' meses':(payback/12).toFixed(1)+' anos')+'.'; }
            else { vd.className='sg-verdict no'; vd.innerHTML='<i class="fas fa-triangle-exclamation"></i> Cuidado — com essas premissas o retorno é baixo ou negativo. Reveja lucro/veículo ou custo dos motoristas.'; }
        }
        // sincroniza range<->number
        [['nveic','nveicN'],['preco','precoN'],['lucro','lucroN']].forEach(function(p){
            g(p[0]).addEventListener('input',function(){ g(p[1]).value=g(p[0]).value; calc(); });
            g(p[1]).addEventListener('input',function(){ g(p[0]).value=g(p[1]).value; calc(); });
        });
        g('nmot').addEventListener('input',calc); g('cmotN').addEventListener('input',calc);

        // base real
        fetch('../api/simulacao_crescimento.php',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
            if(!d.success) return; var b=d.base; window.__fatVeicMes=b.fat_veic_mes;
            g('baseInfo').textContent='Base real (12m): '+b.veiculos+' veículos, '+brl(b.faturamento_ano)+' de faturamento, margem '+b.margem+'%.';
            g('baseLucro').textContent='Sua média por veículo/mês hoje: '+brl(b.lucro_veic_mes)+' (ajuste acima se houver dados de teste distorcendo).';
            // pré-preenche o lucro/veículo com a base se for positivo e plausível
            if(b.lucro_veic_mes>0 && b.lucro_veic_mes<60000){ g('lucro').value=Math.round(b.lucro_veic_mes); g('lucroN').value=Math.round(b.lucro_veic_mes); }
            calc();
        }).catch(function(){ calc(); });
        calc();
    })();
    </script>
    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'simulacao_crescimento'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
