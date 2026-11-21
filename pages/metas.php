<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
require_once '../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();
if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) { header('Location: ../login.php'); exit; }

$page_title = 'Metas';
$v = function_exists('sf_asset_v') ? sf_asset_v() : '1';
$csrf = csrf_token_get();
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
        .mt-form { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:14px; padding:16px; margin-bottom:18px; display:none; }
        .mt-form.open { display:block; }
        .mt-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; }
        .mt-grid label { display:block; font-size:.78rem; color:var(--text-secondary); margin-bottom:4px; }
        .mt-grid input, .mt-grid select { width:100%; padding:8px 10px; border:1px solid var(--border-color); border-radius:8px; background:var(--bg-primary); color:var(--text-primary); }
        .mt-cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:14px; }
        .mt-card { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:14px; padding:16px; }
        .mt-card .h { display:flex; justify-content:space-between; align-items:start; gap:8px; }
        .mt-card .t { font-weight:800; color:var(--text-primary); } .mt-card .m { font-size:.76rem; color:var(--text-secondary); }
        .mt-bar { height:12px; border-radius:8px; background:var(--bg-primary); overflow:hidden; margin:10px 0 6px; } .mt-bar > i { display:block; height:100%; }
        .mt-nums { display:flex; justify-content:space-between; font-size:.85rem; } .mt-nums b { color:var(--text-primary); }
        .mt-pct { font-weight:800; font-size:1.1rem; } .mt-del { background:none; border:none; color:var(--text-secondary); cursor:pointer; }
        .mt-msg { font-size:.85rem; margin-top:8px; }
    </style>
</head>
<body class="sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content fornc-page">
                <div class="fornc-toolbar">
                    <div class="fornc-search-block" style="flex:1"><i class="fas fa-bullseye"></i><strong style="margin-left:8px">Metas</strong><span style="color:var(--text-secondary);font-size:.85rem;margin-left:6px">defina alvos e acompanhe o realizado (dado real)</span></div>
                    <div class="fornc-btn-row">
                        <button type="button" class="fornc-btn fornc-btn--primary" id="mtNova"><i class="fas fa-plus"></i> Nova meta</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>

                <div class="mt-form" id="mtForm">
                    <div class="mt-grid">
                        <div style="grid-column:1/-1"><label>Título</label><input type="text" id="fTitulo" placeholder="Ex.: Faturamento de março"></div>
                        <div><label>Tipo</label><select id="fTipo"><option value="geral">Geral (empresa)</option><option value="motorista">Por motorista</option><option value="veiculo">Por veículo</option></select></div>
                        <div id="fAlvoWrap" style="display:none"><label>Alvo</label><select id="fAlvo"></select></div>
                        <div><label>Indicador</label><select id="fInd"><option value="faturamento">Faturamento (R$)</option><option value="rotas">Nº de rotas</option><option value="km">KM rodado</option><option value="sla">SLA (% no prazo)</option></select></div>
                        <div><label>Mês</label><input type="month" id="fMes"></div>
                        <div><label>Valor da meta</label><input type="number" id="fValor" step="0.01" placeholder="ex.: 50000"></div>
                    </div>
                    <div style="margin-top:12px;display:flex;gap:8px;align-items:center">
                        <button type="button" class="fornc-btn fornc-btn--primary" id="fSalvar"><i class="fas fa-check"></i> Salvar meta</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="fCancelar">Cancelar</button>
                        <span class="mt-msg" id="fMsg"></span>
                    </div>
                </div>

                <div class="mt-cards" id="mtCards"><p style="color:var(--text-secondary)">Carregando…</p></div>
            </div>
        </div>
    </div>
    <script src="../js/theme.js?v=<?php echo $v; ?>"></script>
    <script src="../js/sidebar.js?v=<?php echo $v; ?>"></script>
    <script>
    (function () {
        var CSRF='<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>';
        var esc=function(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;};
        var IND={faturamento:'R$',rotas:'rotas',km:'km',sla:'%'};
        function fmt(ind,v){ v=Number(v)||0; if(ind==='faturamento') return 'R$ '+v.toLocaleString('pt-BR',{minimumFractionDigits:0,maximumFractionDigits:0}); if(ind==='sla') return v.toFixed(1)+'%'; return v.toLocaleString('pt-BR')+' '+IND[ind]; }
        function cor(p){ return p>=100?'#16a34a':(p>=60?'#3b82f6':(p>=30?'#f59e0b':'#dc2626')); }

        document.getElementById('fMes').value=new Date().toISOString().slice(0,7);
        document.getElementById('mtNova').addEventListener('click',function(){ document.getElementById('mtForm').classList.toggle('open'); });
        document.getElementById('fCancelar').addEventListener('click',function(){ document.getElementById('mtForm').classList.remove('open'); });
        document.getElementById('fTipo').addEventListener('change',function(){
            var t=this.value, w=document.getElementById('fAlvoWrap');
            if(t==='geral'){ w.style.display='none'; return; }
            w.style.display='';
            fetch('../api/metas.php?action=alvos&tipo='+t,{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
                document.getElementById('fAlvo').innerHTML=(d.alvos||[]).map(function(a){return '<option value="'+a.id+'">'+esc(a.nome)+'</option>';}).join('');
            });
        });
        document.getElementById('fSalvar').addEventListener('click',function(){
            var fd=new FormData();
            fd.append('csrf_token',CSRF); fd.append('titulo',document.getElementById('fTitulo').value);
            fd.append('tipo_alvo',document.getElementById('fTipo').value); fd.append('indicador',document.getElementById('fInd').value);
            fd.append('periodo_mes',document.getElementById('fMes').value); fd.append('valor_meta',document.getElementById('fValor').value);
            if(document.getElementById('fTipo').value!=='geral') fd.append('alvo_id',document.getElementById('fAlvo').value);
            fetch('../api/metas.php?action=criar',{method:'POST',credentials:'same-origin',body:fd}).then(function(r){return r.json();}).then(function(d){
                var msg=document.getElementById('fMsg');
                if(d.success){ msg.style.color='#16a34a'; msg.textContent='Meta criada!'; document.getElementById('fTitulo').value=''; document.getElementById('fValor').value=''; setTimeout(function(){document.getElementById('mtForm').classList.remove('open');msg.textContent='';},900); carregar(); }
                else { msg.style.color='#dc3545'; msg.textContent=d.message||'Erro.'; }
            });
        });

        function carregar(){
            fetch('../api/metas.php',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
                if(!d.success) throw 0; render(d.metas||[]);
            }).catch(function(){ document.getElementById('mtCards').innerHTML='<p style="color:#dc3545">Erro ao carregar.</p>'; });
        }
        function render(metas){
            if(!metas.length){ document.getElementById('mtCards').innerHTML='<div class="fornc-empty-state"><h3>Nenhuma meta ainda</h3><p>Clique em "Nova meta" para definir um alvo e acompanhar o realizado.</p></div>'; return; }
            document.getElementById('mtCards').innerHTML=metas.map(function(m){
                var p=Math.min(100,Math.max(0,m.pct)); var c=cor(m.pct);
                return '<div class="mt-card"><div class="h"><div><div class="t">'+esc(m.titulo)+'</div>'+
                    '<div class="m">'+esc(m.alvo_nome)+' · '+esc(m.periodo_mes)+' · '+esc(m.indicador)+'</div></div>'+
                    '<button class="mt-del" data-id="'+m.id+'" title="Excluir"><i class="fas fa-trash"></i></button></div>'+
                    '<div class="mt-bar"><i style="width:'+p+'%;background:'+c+'"></i></div>'+
                    '<div class="mt-nums"><span>Realizado <b>'+fmt(m.indicador,m.realizado)+'</b></span><span class="mt-pct" style="color:'+c+'">'+m.pct+'%</span><span>Meta <b>'+fmt(m.indicador,m.valor_meta)+'</b></span></div></div>';
            }).join('');
            document.querySelectorAll('.mt-del').forEach(function(b){ b.addEventListener('click',function(){
                if(!confirm('Excluir esta meta?')) return;
                var fd=new FormData(); fd.append('csrf_token',CSRF); fd.append('id',b.dataset.id);
                fetch('../api/metas.php?action=excluir',{method:'POST',credentials:'same-origin',body:fd}).then(function(){ carregar(); });
            });});
        }
        carregar();
    })();
    </script>
    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'metas'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
