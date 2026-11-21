<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();
if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) { header('Location: ../login.php'); exit; }

$page_title = 'Planejamento de Carga';
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
        .pc-top { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; margin-bottom:18px; } @media(max-width:800px){.pc-top{grid-template-columns:1fr;}}
        .pc-k { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:12px; padding:14px 16px; }
        .pc-k .l { font-size:.78rem; color:var(--text-secondary); } .pc-k .v { font-size:1.5rem; font-weight:800; margin-top:4px; }
        .pc-bar { height:16px; border-radius:8px; background:var(--bg-primary); overflow:hidden; margin-top:8px; } .pc-bar > i { display:block; height:100%; transition:.2s; }
        .pc-add { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:14px; padding:14px 16px; margin-bottom:16px; display:flex; gap:10px; flex-wrap:wrap; align-items:end; }
        .pc-add .fg label { display:block; font-size:.74rem; color:var(--text-secondary); margin-bottom:4px; }
        .pc-add .fg input { padding:8px 10px; border:1px solid var(--border-color); border-radius:8px; background:var(--bg-primary); color:var(--text-primary); width:150px; }
        .pc-alert { padding:12px 14px; border-radius:10px; margin-bottom:16px; font-weight:600; }
        .pc-alert.ok{background:rgba(22,163,74,.12);color:#16a34a;} .pc-alert.warn{background:rgba(245,158,11,.12);color:#f59e0b;} .pc-alert.bad{background:rgba(220,38,38,.12);color:#dc2626;}
        .pc-del{background:none;border:none;color:var(--text-secondary);cursor:pointer;}
    </style>
</head>
<body class="sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content fornc-page">
                <div class="fornc-toolbar">
                    <div class="fornc-filters-inline">
                        <div class="fg" style="min-width:260px">
                            <label for="pcVeic">Veículo</label>
                            <select id="pcVeic"><option value="">Selecione…</option></select>
                        </div>
                    </div>
                    <div class="fornc-btn-row">
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="pcLimpar"><i class="fas fa-eraser"></i> Limpar carga</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>

                <div class="pc-alert ok" id="pcAlert">Selecione um veículo e adicione a carga para verificar peso e distribuição por eixo.</div>

                <div class="pc-top">
                    <div class="pc-k"><div class="l">Capacidade do veículo</div><div class="v" id="pcCap">—</div><div style="font-size:.74rem;color:var(--text-secondary)" id="pcEixos"></div></div>
                    <div class="pc-k"><div class="l">Carga total</div><div class="v" id="pcTotal">0 kg</div><div class="pc-bar"><i id="pcBar" style="width:0%;background:#16a34a"></i></div></div>
                    <div class="pc-k"><div class="l">Peso por eixo (médio)</div><div class="v" id="pcEixo">—</div><div style="font-size:.74rem;color:var(--text-secondary)">estimativa (carga ÷ eixos)</div></div>
                </div>

                <div class="pc-add">
                    <div class="fg"><label>Item / carga</label><input type="text" id="iNome" placeholder="Ex.: Pallet de bebidas"></div>
                    <div class="fg"><label>Peso (kg)</label><input type="number" id="iPeso" step="1" placeholder="0"></div>
                    <div class="fg"><label>Volume (m³)</label><input type="number" id="iVol" step="0.1" placeholder="opcional"></div>
                    <button type="button" class="fornc-btn fornc-btn--primary" id="iAdd"><i class="fas fa-plus"></i> Adicionar</button>
                </div>

                <div class="fornc-table-wrap">
                    <table class="fornc-table"><thead><tr><th>Item</th><th>Peso (kg)</th><th>Volume (m³)</th><th>% da capacidade</th><th></th></tr></thead>
                    <tbody id="pcTbody"><tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-secondary)">Nenhum item adicionado.</td></tr></tbody></table>
                </div>
            </div>
        </div>
    </div>
    <script src="../js/theme.js?v=<?php echo $v; ?>"></script>
    <script src="../js/sidebar.js?v=<?php echo $v; ?>"></script>
    <script>
    (function () {
        var esc=function(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;};
        var kg=function(n){return (Number(n)||0).toLocaleString('pt-BR')+' kg';};
        var veic=null, itens=[];
        fetch('../api/planejamento_carga.php',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
            var sel=document.getElementById('pcVeic'); window.__veics={};
            (d.veiculos||[]).forEach(function(v){ window.__veics[v.id]=v; var o=document.createElement('option'); o.value=v.id; o.textContent=v.placa+(v.modelo?(' · '+v.modelo):'')+' — '+(v.capacidade_carga||0).toLocaleString('pt-BR')+' kg'; sel.appendChild(o); });
        });
        document.getElementById('pcVeic').addEventListener('change',function(){ veic=window.__veics[this.value]||null; calc(); });
        document.getElementById('iAdd').addEventListener('click',function(){
            var nome=document.getElementById('iNome').value.trim(), peso=+document.getElementById('iPeso').value||0, vol=+document.getElementById('iVol').value||0;
            if(!nome||peso<=0){ return; }
            itens.push({nome:nome,peso:peso,vol:vol});
            document.getElementById('iNome').value=''; document.getElementById('iPeso').value=''; document.getElementById('iVol').value='';
            calc();
        });
        document.getElementById('pcLimpar').addEventListener('click',function(){ itens=[]; calc(); });
        function calc(){
            var cap=veic?veic.capacidade_carga:0, eixos=veic?veic.numero_eixos:0;
            var total=itens.reduce(function(a,b){return a+b.peso;},0);
            document.getElementById('pcCap').textContent=veic?(cap?kg(cap):'não informada'):'—';
            document.getElementById('pcEixos').textContent=veic?((eixos||'?')+' eixos'):'';
            document.getElementById('pcTotal').textContent=kg(total);
            document.getElementById('pcEixo').textContent=(veic&&eixos)?kg(Math.round(total/eixos)):'—';
            var pct=cap>0?(total/cap*100):0;
            var bar=document.getElementById('pcBar'); bar.style.width=Math.min(100,pct)+'%';
            bar.style.background=pct>100?'#dc2626':(pct>90?'#f59e0b':'#16a34a');
            var al=document.getElementById('pcAlert');
            if(!veic){ al.className='pc-alert ok'; al.textContent='Selecione um veículo e adicione a carga.'; }
            else if(!cap){ al.className='pc-alert warn'; al.textContent='Este veículo não tem capacidade de carga cadastrada — cadastre para validar o peso.'; }
            else if(pct>100){ al.className='pc-alert bad'; al.innerHTML='<i class="fas fa-triangle-exclamation"></i> EXCESSO DE PESO — '+kg(Math.round(total-cap))+' acima da capacidade ('+pct.toFixed(0)+'%). Reduza a carga.'; }
            else if(pct>90){ al.className='pc-alert warn'; al.innerHTML='<i class="fas fa-circle-exclamation"></i> Perto do limite ('+pct.toFixed(0)+'%). Margem de '+kg(Math.round(cap-total))+'.'; }
            else { al.className='pc-alert ok'; al.innerHTML='<i class="fas fa-circle-check"></i> Dentro da capacidade ('+pct.toFixed(0)+'%). Ainda cabe '+kg(Math.round(cap-total))+'.'; }
            var tb=document.getElementById('pcTbody');
            if(!itens.length){ tb.innerHTML='<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-secondary)">Nenhum item adicionado.</td></tr>'; return; }
            tb.innerHTML=itens.map(function(it,i){
                var p=cap>0?(it.peso/cap*100):0;
                return '<tr><td><strong>'+esc(it.nome)+'</strong></td><td>'+kg(it.peso)+'</td><td>'+(it.vol?it.vol+' m³':'—')+'</td><td>'+p.toFixed(1)+'%</td>'+
                    '<td class="actions"><button class="pc-del" data-i="'+i+'"><i class="fas fa-trash"></i></button></td></tr>';
            }).join('');
            document.querySelectorAll('.pc-del').forEach(function(b){ b.addEventListener('click',function(){ itens.splice(+b.dataset.i,1); calc(); }); });
        }
        calc();
    })();
    </script>
    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'planejamento_carga'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
