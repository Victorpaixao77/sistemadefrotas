<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
require_once '../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();
if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) { header('Location: ../login.php'); exit; }

$page_title = 'Workflow de Aprovação';
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
        .wf-sum { display:flex; gap:14px; flex-wrap:wrap; margin:6px 0 16px; }
        .wf-s { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:10px; padding:10px 16px; text-align:center; } .wf-s b{display:block;font-size:1.4rem;font-weight:800;} .wf-s small{color:var(--text-secondary);font-size:.74rem;}
        .wf-cfg { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:14px; padding:16px; margin-bottom:18px; }
        .wf-cfg h3 { font-size:.92rem; margin:0 0 10px; }
        .wf-chain { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .wf-nivel { background:var(--bg-primary); border:1px solid var(--border-color); border-radius:999px; padding:6px 14px; display:flex; align-items:center; gap:8px; font-weight:600; font-size:.85rem; }
        .wf-nivel .x { cursor:pointer; color:var(--text-secondary); } .wf-arrow { color:var(--text-secondary); }
        .wf-form { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:14px; padding:16px; margin-bottom:18px; display:none; } .wf-form.open{display:block;}
        .wf-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; }
        .wf-grid label{display:block;font-size:.76rem;color:var(--text-secondary);margin-bottom:4px;} .wf-grid input,.wf-grid textarea{width:100%;padding:8px 10px;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-primary);color:var(--text-primary);}
        .wf-item { background:var(--bg-secondary); border:1px solid var(--border-color); border-left:4px solid #3b82f6; border-radius:12px; padding:14px 16px; margin-bottom:10px; }
        .wf-item.aprovada{border-left-color:#16a34a;} .wf-item.rejeitada{border-left-color:#dc2626;}
        .wf-item .top{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;} .wf-item .t{font-weight:700;color:var(--text-primary);}
        .wf-item .badge{font-size:.7rem;font-weight:700;padding:2px 9px;border-radius:999px;} .wf-b-pend{background:rgba(59,130,246,.15);color:#3b82f6;} .wf-b-apr{background:rgba(22,163,74,.15);color:#16a34a;} .wf-b-rej{background:rgba(220,38,38,.15);color:#dc2626;}
        .wf-item .meta{font-size:.82rem;color:var(--text-secondary);margin-top:3px;} .wf-item .acts{margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;}
    </style>
</head>
<body class="sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content fornc-page">
                <div class="fornc-toolbar">
                    <div class="fornc-search-block" style="flex:1"><i class="fas fa-diagram-project"></i><strong style="margin-left:8px">Workflow de Aprovação</strong><span style="color:var(--text-secondary);font-size:.85rem;margin-left:6px">níveis configuráveis + fila de solicitações</span></div>
                    <div class="fornc-btn-row">
                        <button type="button" class="fornc-btn fornc-btn--primary" id="wfNova"><i class="fas fa-plus"></i> Nova solicitação</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>

                <div class="wf-cfg">
                    <h3><i class="fas fa-sliders"></i> Fluxo de aprovação (níveis)</h3>
                    <div class="wf-chain" id="wfChain"></div>
                    <div style="margin-top:10px;display:flex;gap:8px"><input type="text" id="wfNovoNivel" placeholder="Novo nível (ex.: Diretor)" style="padding:7px 10px;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-primary);color:var(--text-primary)"><button type="button" class="fornc-btn fornc-btn--ghost" id="wfAddNivel"><i class="fas fa-plus"></i> Adicionar nível</button></div>
                </div>

                <div class="wf-sum">
                    <div class="wf-s"><b id="wfPend" style="color:#3b82f6">—</b><small>Pendentes</small></div>
                    <div class="wf-s"><b id="wfApr" style="color:#16a34a">—</b><small>Aprovadas</small></div>
                    <div class="wf-s"><b id="wfRej" style="color:#dc2626">—</b><small>Rejeitadas</small></div>
                </div>

                <div class="wf-form" id="wfForm">
                    <div class="wf-grid">
                        <div style="grid-column:1/-1"><label>Título *</label><input type="text" id="sTitulo" placeholder="Ex.: Compra de 4 pneus"></div>
                        <div><label>Tipo</label><input type="text" id="sTipo" placeholder="Ex.: Compra"></div>
                        <div><label>Valor (R$)</label><input type="number" id="sValor" step="0.01" placeholder="opcional"></div>
                        <div style="grid-column:1/-1"><label>Descrição</label><input type="text" id="sDesc" placeholder="opcional"></div>
                    </div>
                    <div style="margin-top:12px;display:flex;gap:8px;align-items:center"><button type="button" class="fornc-btn fornc-btn--primary" id="sSalvar"><i class="fas fa-paper-plane"></i> Enviar para aprovação</button><button type="button" class="fornc-btn fornc-btn--ghost" id="sCancelar">Cancelar</button><span id="sMsg" style="font-size:.85rem"></span></div>
                </div>

                <div id="wfLista"><p style="color:var(--text-secondary)">Carregando…</p></div>
            </div>
        </div>
    </div>
    <script src="../js/theme.js?v=<?php echo $v; ?>"></script>
    <script src="../js/sidebar.js?v=<?php echo $v; ?>"></script>
    <script>
    (function () {
        var CSRF='<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>';
        var esc=function(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;};
        var brl=function(n){return n==null?'':'R$ '+(Number(n)||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});};
        function post(a,d){ var fd=new FormData(); fd.append('csrf_token',CSRF); for(var k in d) fd.append(k,d[k]); return fetch('../api/workflow.php?action='+a,{method:'POST',credentials:'same-origin',body:fd}).then(function(r){return r.json();}); }

        document.getElementById('wfNova').addEventListener('click',function(){ document.getElementById('wfForm').classList.toggle('open'); });
        document.getElementById('sCancelar').addEventListener('click',function(){ document.getElementById('wfForm').classList.remove('open'); });
        document.getElementById('wfAddNivel').addEventListener('click',function(){
            var n=document.getElementById('wfNovoNivel').value.trim(); if(!n) return;
            post('nivel_criar',{nome:n}).then(function(d){ if(d.success){ document.getElementById('wfNovoNivel').value=''; carregar(); } else alert(d.message||'Erro'); });
        });
        document.getElementById('sSalvar').addEventListener('click',function(){
            post('solicitar',{titulo:document.getElementById('sTitulo').value,tipo:document.getElementById('sTipo').value,valor:document.getElementById('sValor').value,descricao:document.getElementById('sDesc').value}).then(function(d){
                var m=document.getElementById('sMsg');
                if(d.success){ m.style.color='#16a34a'; m.textContent='Enviada!'; document.getElementById('sTitulo').value=''; document.getElementById('sValor').value=''; setTimeout(function(){document.getElementById('wfForm').classList.remove('open');m.textContent='';},900); carregar(); }
                else { m.style.color='#dc3545'; m.textContent=d.message||'Erro.'; }
            });
        });

        function carregar(){
            fetch('../api/workflow.php',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
                if(!d.success) throw 0; var r=d.resumo;
                document.getElementById('wfPend').textContent=r.pendentes; document.getElementById('wfApr').textContent=r.aprovadas; document.getElementById('wfRej').textContent=r.rejeitadas;
                renderChain(d.niveis||[]); renderLista(d.solicitacoes||[]);
            }).catch(function(){ document.getElementById('wfLista').innerHTML='<p style="color:#dc3545">Erro ao carregar.</p>'; });
        }
        function renderChain(niveis){
            var c=document.getElementById('wfChain');
            if(!niveis.length){ c.innerHTML='<span style="color:var(--text-secondary);font-size:.85rem">Nenhum nível — adicione ao menos um (ex.: Gerente) para poder solicitar.</span>'; return; }
            c.innerHTML=niveis.map(function(n,i){ return (i>0?'<span class="wf-arrow"><i class="fas fa-arrow-right"></i></span>':'')+'<span class="wf-nivel">'+esc(n.nome)+' <i class="fas fa-times x" data-id="'+n.id+'" title="Remover"></i></span>'; }).join('');
            c.querySelectorAll('.x').forEach(function(x){ x.addEventListener('click',function(){ if(confirm('Remover este nível?')) post('nivel_excluir',{id:x.dataset.id}).then(carregar); }); });
        }
        function renderLista(sols){
            if(!sols.length){ document.getElementById('wfLista').innerHTML='<div class="fornc-empty-state"><h3>Nenhuma solicitação</h3><p>Clique em "Nova solicitação" para enviar algo à aprovação.</p></div>'; return; }
            var B={pendente:'wf-b-pend',aprovada:'wf-b-apr',rejeitada:'wf-b-rej'};
            document.getElementById('wfLista').innerHTML=sols.map(function(x){
                var acts= x.status==='pendente' ? '<div class="acts"><button class="fornc-btn fornc-btn--primary wf-apr" data-id="'+x.id+'"><i class="fas fa-check"></i> Aprovar</button><button class="fornc-btn fornc-btn--ghost wf-rej" data-id="'+x.id+'"><i class="fas fa-xmark"></i> Rejeitar</button></div>' : '';
                var nivel= x.status==='pendente' ? (' · aguarda <strong>'+esc(x.nivel_nome)+'</strong>') : '';
                return '<div class="wf-item '+x.status+'"><div class="top"><span class="t">'+esc(x.titulo)+'</span><span class="badge '+B[x.status]+'">'+x.status+'</span></div>'+
                    '<div class="meta">'+esc(x.tipo||'—')+(x.valor?(' · '+brl(x.valor)):'')+' · por '+esc(x.solicitante||'—')+nivel+'</div>'+
                    (x.descricao?('<div class="meta">'+esc(x.descricao)+'</div>'):'')+acts+'</div>';
            }).join('');
            document.querySelectorAll('.wf-apr').forEach(function(b){ b.addEventListener('click',function(){ var c=prompt('Comentário (opcional):')||''; post('decidir',{id:b.dataset.id,decisao:'aprovar',comentario:c}).then(carregar); }); });
            document.querySelectorAll('.wf-rej').forEach(function(b){ b.addEventListener('click',function(){ var c=prompt('Motivo da rejeição (opcional):')||''; post('decidir',{id:b.dataset.id,decisao:'rejeitar',comentario:c}).then(carregar); }); });
        }
        carregar();
    })();
    </script>
    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'workflow'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
