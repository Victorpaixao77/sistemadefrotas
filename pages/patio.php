<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
require_once '../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();
if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) { header('Location: ../login.php'); exit; }

$page_title = 'Gestão de Pátio';
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
        .pt-sum { display:flex; gap:14px; flex-wrap:wrap; margin:6px 0 18px; }
        .pt-s { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:10px; padding:10px 16px; text-align:center; } .pt-s b{display:block;font-size:1.4rem;font-weight:800;} .pt-s small{color:var(--text-secondary);font-size:.74rem;}
        .pt-setor { margin-bottom:20px; } .pt-setor h3 { font-size:.9rem; margin:0 0 10px; color:var(--text-secondary); }
        .pt-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:12px; }
        .pt-vaga { border-radius:12px; padding:14px; border:2px solid; cursor:pointer; min-height:96px; display:flex; flex-direction:column; justify-content:space-between; transition:.15s; }
        .pt-vaga:hover { transform:translateY(-2px); }
        .pt-vaga.livre{border-color:#16a34a;background:rgba(22,163,74,.08);} .pt-vaga.ocupada{border-color:#3b82f6;background:rgba(59,130,246,.10);} .pt-vaga.manutencao{border-color:#f59e0b;background:rgba(245,158,11,.10);} .pt-vaga.reservada{border-color:#8b5cf6;background:rgba(139,92,246,.10);}
        .pt-vaga .n { font-weight:800; font-size:1.05rem; color:var(--text-primary); }
        .pt-vaga .st { font-size:.7rem; text-transform:uppercase; font-weight:700; }
        .pt-vaga.livre .st{color:#16a34a;} .pt-vaga.ocupada .st{color:#3b82f6;} .pt-vaga.manutencao .st{color:#f59e0b;} .pt-vaga.reservada .st{color:#8b5cf6;}
        .pt-vaga .car { font-size:.85rem; color:var(--text-primary); font-weight:600; }
        .pt-modal { position:fixed; inset:0; background:rgba(0,0,0,.5); display:none; align-items:center; justify-content:center; z-index:1000; }
        .pt-modal.open{display:flex;} .pt-modal .box{background:var(--bg-secondary);border-radius:14px;padding:20px;width:340px;max-width:92vw;}
        .pt-modal h4{margin:0 0 14px;} .pt-modal label{display:block;font-size:.78rem;color:var(--text-secondary);margin:8px 0 4px;}
        .pt-modal select,.pt-modal input{width:100%;padding:8px 10px;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-primary);color:var(--text-primary);}
        .pt-modal .acts{display:flex;gap:8px;margin-top:16px;flex-wrap:wrap;}
    </style>
</head>
<body class="sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content fornc-page">
                <div class="fornc-toolbar">
                    <div class="fornc-search-block" style="flex:1"><i class="fas fa-warehouse"></i><strong style="margin-left:8px">Gestão de Pátio</strong><span style="color:var(--text-secondary);font-size:.85rem;margin-left:6px">mapa de vagas e veículos no pátio</span></div>
                    <div class="fornc-btn-row">
                        <button type="button" class="fornc-btn fornc-btn--primary" id="ptNova"><i class="fas fa-plus"></i> Nova vaga</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>
                <div class="pt-sum">
                    <div class="pt-s"><b id="sTotal">—</b><small>Vagas</small></div>
                    <div class="pt-s"><b id="sLivre" style="color:#16a34a">—</b><small>Livres</small></div>
                    <div class="pt-s"><b id="sOcup" style="color:#3b82f6">—</b><small>Ocupadas</small></div>
                    <div class="pt-s"><b id="sMan" style="color:#f59e0b">—</b><small>Manutenção</small></div>
                    <div class="pt-s"><b id="sRes" style="color:#8b5cf6">—</b><small>Reservadas</small></div>
                </div>
                <div id="ptSetores"><p style="color:var(--text-secondary)">Carregando…</p></div>
            </div>
        </div>
    </div>

    <div class="pt-modal" id="ptModal"><div class="box">
        <h4 id="mTitulo">Vaga</h4>
        <div id="mAlocar">
            <label>Alocar veículo</label>
            <select id="mVeic"></select>
        </div>
        <div class="acts">
            <button type="button" class="fornc-btn fornc-btn--primary" id="mBtnAlocar"><i class="fas fa-truck"></i> Alocar</button>
            <button type="button" class="fornc-btn fornc-btn--ghost" id="mBtnLiberar"><i class="fas fa-door-open"></i> Liberar</button>
            <button type="button" class="fornc-btn fornc-btn--ghost" id="mBtnManut" title="Marcar manutenção"><i class="fas fa-wrench"></i></button>
            <button type="button" class="fornc-btn fornc-btn--ghost" id="mBtnExcluir" title="Excluir vaga"><i class="fas fa-trash"></i></button>
            <button type="button" class="fornc-btn fornc-btn--ghost" id="mFechar">Fechar</button>
        </div>
    </div></div>

    <script src="../js/theme.js?v=<?php echo $v; ?>"></script>
    <script src="../js/sidebar.js?v=<?php echo $v; ?>"></script>
    <script>
    (function () {
        var CSRF='<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>';
        var esc=function(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;};
        var vagas=[], veiculos=[], atual=null;
        var ST={livre:'Livre',ocupada:'Ocupada',manutencao:'Manutenção',reservada:'Reservada'};
        function post(action,data){ var fd=new FormData(); fd.append('csrf_token',CSRF); for(var k in data) fd.append(k,data[k]); return fetch('../api/patio.php?action='+action,{method:'POST',credentials:'same-origin',body:fd}).then(function(r){return r.json();}); }

        fetch('../api/patio.php?action=veiculos',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){ veiculos=d.veiculos||[]; });

        function carregar(){
            fetch('../api/patio.php',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
                if(!d.success) throw 0; var r=d.resumo;
                document.getElementById('sTotal').textContent=r.total; document.getElementById('sLivre').textContent=r.livre;
                document.getElementById('sOcup').textContent=r.ocupada; document.getElementById('sMan').textContent=r.manutencao; document.getElementById('sRes').textContent=r.reservada;
                vagas=d.vagas||[]; render();
            }).catch(function(){ document.getElementById('ptSetores').innerHTML='<p style="color:#dc3545">Erro ao carregar.</p>'; });
        }
        function render(){
            if(!vagas.length){ document.getElementById('ptSetores').innerHTML='<div class="fornc-empty-state"><h3>Nenhuma vaga</h3><p>Clique em "Nova vaga" para montar o mapa do seu pátio.</p></div>'; return; }
            var setores={};
            vagas.forEach(function(v){ var s=v.setor||'Pátio'; (setores[s]=setores[s]||[]).push(v); });
            var html='';
            Object.keys(setores).forEach(function(s){
                html+='<div class="pt-setor"><h3><i class="fas fa-layer-group"></i> '+esc(s)+'</h3><div class="pt-grid">';
                setores[s].forEach(function(v){
                    var car=v.placa?('<div class="car">'+esc(v.placa)+'</div>'):'<div class="car" style="color:var(--text-secondary)">vazia</div>';
                    html+='<div class="pt-vaga '+v.status+'" data-id="'+v.id+'"><div class="n">'+esc(v.nome)+'</div>'+car+'<div class="st">'+ST[v.status]+'</div></div>';
                });
                html+='</div></div>';
            });
            document.getElementById('ptSetores').innerHTML=html;
            document.querySelectorAll('.pt-vaga').forEach(function(el){ el.addEventListener('click',function(){ abrir(+el.dataset.id); }); });
        }
        function abrir(id){
            atual=vagas.find(function(v){return v.id===id;}); if(!atual) return;
            document.getElementById('mTitulo').textContent='Vaga '+atual.nome+(atual.setor?(' · '+atual.setor):'');
            var sel=document.getElementById('mVeic');
            sel.innerHTML=veiculos.map(function(v){return '<option value="'+v.id+'"'+(v.id===atual.veiculo_id?' selected':'')+'>'+esc(v.nome)+'</option>';}).join('');
            document.getElementById('ptModal').classList.add('open');
        }
        function fechar(){ document.getElementById('ptModal').classList.remove('open'); }
        document.getElementById('mFechar').addEventListener('click',fechar);
        document.getElementById('ptModal').addEventListener('click',function(e){ if(e.target===this) fechar(); });
        document.getElementById('mBtnAlocar').addEventListener('click',function(){ post('alocar',{id:atual.id,veiculo_id:document.getElementById('mVeic').value}).then(function(){ fechar(); carregar(); }); });
        document.getElementById('mBtnLiberar').addEventListener('click',function(){ post('liberar',{id:atual.id}).then(function(){ fechar(); carregar(); }); });
        document.getElementById('mBtnManut').addEventListener('click',function(){ post('status',{id:atual.id,status:'manutencao'}).then(function(){ fechar(); carregar(); }); });
        document.getElementById('mBtnExcluir').addEventListener('click',function(){ if(confirm('Excluir a vaga '+atual.nome+'?')) post('excluir',{id:atual.id}).then(function(){ fechar(); carregar(); }); });
        document.getElementById('ptNova').addEventListener('click',function(){
            var nome=prompt('Nome/número da vaga (ex.: A1, Doca 3):'); if(!nome) return;
            var setor=prompt('Setor (opcional, ex.: Carregamento):')||'';
            post('criar',{nome:nome,setor:setor}).then(function(d){ if(d.success) carregar(); else alert(d.message||'Erro'); });
        });
        carregar();
    })();
    </script>
    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'patio'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
