<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
require_once __DIR__ . '/../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();

$page_title = 'Aprovações';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <link rel="stylesheet" href="../css/fornc-modern-page.css">
    <link rel="stylesheet" href="../css/sf-premium.css?v=1.0.2">
    <?php sf_page_help_css('..'); ?>
    <link rel="icon" type="image/png" href="../logo.png">
    <style>
        html, body { overflow-x: hidden; max-width: 100%; }
        .ap-tabs { display: flex; gap: 8px; margin-bottom: 18px; }
        .ap-tab { padding: 9px 16px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-secondary); font-size: 14px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; }
        .ap-tab.active { background: var(--accent-primary); border-color: var(--accent-primary); color: #fff; }
        .ap-tab .badge { background: rgba(255,255,255,.25); border-radius: 999px; padding: 0 8px; font-size: 12px; }
        .ap-tab:not(.active) .badge { background: var(--bg-tertiary); }
        .ap-list { display: flex; flex-direction: column; gap: 12px; }
        .ap-card { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 14px; padding: 16px 18px; }
        .ap-card .top { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
        .ap-num { font-weight: 800; color: var(--text-primary); }
        .ap-frete { font-size: 1.15rem; font-weight: 800; color: #16a34a; }
        .ap-meta { color: var(--text-secondary); font-size: .88rem; margin-top: 4px; }
        .ap-meta i { color: var(--accent-primary); }
        .ap-actions { display: flex; gap: 8px; margin-top: 12px; align-items: center; flex-wrap: wrap; }
        .ap-actions input { flex: 1; min-width: 180px; padding: 8px 11px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-tertiary); color: var(--text-primary); font-size: 13px; }
        .ap-btn { border: none; border-radius: 8px; padding: 8px 14px; font-weight: 700; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .ap-ok { background: #16a34a; color: #fff; } .ap-no { background: #dc2626; color: #fff; }
        .ap-empty { padding: 40px; text-align: center; color: var(--text-muted); border: 1px dashed var(--border-color); border-radius: 12px; }
        .ap-empty i { color: #16a34a; font-size: 1.4rem; display: block; margin-bottom: 8px; }
        .ap-hist { border-left: 4px solid var(--border-color); }
        .ap-hist.aprovado { border-left-color: #16a34a; } .ap-hist.rejeitado { border-left-color: #dc2626; }
        .ap-tag { font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 2px 9px; border-radius: 999px; }
        .t-aprovado { background: rgba(16,185,129,.16); color: #16a34a; } .t-rejeitado { background: rgba(239,68,68,.16); color: #dc2626; }
    </style>
</head>
<body class="sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content">
                <p style="color:var(--text-secondary);margin:0 0 16px;">Aprove ou rejeite as Ordens de Transporte em cotação. Toda decisão fica registrada com autor, data e comentário (trilha de auditoria).</p>

                <div class="ap-tabs">
                    <button class="ap-tab active" data-t="pendentes" onclick="tab('pendentes')"><i class="fas fa-inbox"></i> Pendentes <span class="badge" id="apCount">0</span></button>
                    <button class="ap-tab" data-t="historico" onclick="tab('historico')"><i class="fas fa-clock-rotate-left"></i> Histórico</button>
                    <button type="button" id="helpBtn" class="fornc-btn fornc-btn--ghost fornc-btn--icon" title="Ajuda" aria-label="Ajuda" style="margin-left:auto;"><i class="fas fa-question-circle"></i></button>
                </div>

                <div class="ap-list" id="apList"><div class="ap-empty">Carregando…</div></div>
            </div>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
    var AP_CSRF = '<?php echo htmlspecialchars(csrf_token_get()); ?>';
    var apTab = 'pendentes';
    function esc(s){ var d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
    function moeda(v){ return 'R$ ' + Number(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); }
    function dt(s){ return s ? s.substring(0,16).replace('T',' ').split('-').length? s.substring(8,10)+'/'+s.substring(5,7)+'/'+s.substring(0,4)+' '+s.substring(11,16) : s : ''; }

    function tab(t){ apTab=t; document.querySelectorAll('.ap-tab').forEach(function(x){x.classList.toggle('active',x.dataset.t===t);}); carregar(); }

    function carregar(){
        var list=document.getElementById('apList'); list.innerHTML='<div class="ap-empty">Carregando…</div>';
        if(apTab==='pendentes'){
            fetch('../api/aprovacoes.php?action=pendentes').then(function(r){return r.json();}).then(function(d){
                var arr=(d&&d.pendentes)||[]; document.getElementById('apCount').textContent=arr.length;
                if(!arr.length){ list.innerHTML='<div class="ap-empty"><i class="fas fa-check-circle"></i> Nenhuma OT pendente de aprovação.</div>'; return; }
                list.innerHTML=arr.map(function(o){
                    var trecho=(o.origem||o.estado_origem||'—')+' → '+(o.destino||o.estado_destino||'—');
                    var vm=(o.placa?('<i class="fas fa-truck"></i> '+esc(o.placa)+'  '):'')+(o.motorista?('<i class="fas fa-user"></i> '+esc(o.motorista)):'');
                    return '<div class="ap-card" id="apc'+o.id+'"><div class="top"><div>'+
                        '<div class="ap-num">'+esc(o.numero)+(o.cliente_nome?(' · '+esc(o.cliente_nome)):'')+'</div>'+
                        '<div class="ap-meta"><i class="fas fa-route"></i> '+esc(trecho)+(vm?('<br>'+vm):'')+'</div></div>'+
                        '<div class="ap-frete">'+moeda(o.valor_frete)+'</div></div>'+
                        '<div class="ap-actions"><input type="text" id="apcom'+o.id+'" placeholder="Comentário (opcional)">'+
                        '<button class="ap-btn ap-ok" onclick="decidir('+o.id+',\'aprovado\')"><i class="fas fa-check"></i> Aprovar</button>'+
                        '<button class="ap-btn ap-no" onclick="decidir('+o.id+',\'rejeitado\')"><i class="fas fa-xmark"></i> Rejeitar</button></div></div>';
                }).join('');
            });
        } else {
            fetch('../api/aprovacoes.php?action=historico').then(function(r){return r.json();}).then(function(d){
                var arr=(d&&d.historico)||[];
                if(!arr.length){ list.innerHTML='<div class="ap-empty">Nenhuma decisão registrada ainda.</div>'; return; }
                list.innerHTML=arr.map(function(h){
                    return '<div class="ap-card ap-hist '+h.decisao+'"><div class="top"><div>'+
                        '<div class="ap-num">'+esc(h.entity_ref||'')+' <span class="ap-tag t-'+h.decisao+'">'+(h.decisao==='aprovado'?'Aprovado':'Rejeitado')+'</span></div>'+
                        '<div class="ap-meta">'+esc(h.descricao||'')+'</div>'+
                        (h.comentario?('<div class="ap-meta" style="font-style:italic">“'+esc(h.comentario)+'”</div>'):'')+'</div>'+
                        '<div class="ap-meta" style="text-align:right">'+esc(h.aprovador_nome||'—')+'<br><small>'+esc((h.created_at||'').substring(0,16).replace('T',' '))+'</small></div>'+
                        '</div></div>';
                }).join('');
            });
        }
    }

    function decidir(id, decisao){
        if(decisao==='rejeitado' && !confirm('Rejeitar esta OT? Ela será cancelada.')) return;
        var com=(document.getElementById('apcom'+id)||{}).value||'';
        fetch('../api/aprovacoes.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'decidir',ot_id:id,decisao:decisao,comentario:com,csrf_token:AP_CSRF}).toString()})
            .then(function(r){return r.json();}).then(function(d){ if(d.success){ var c=document.getElementById('apc'+id); if(c) c.remove(); document.getElementById('apCount').textContent=Math.max(0,parseInt(document.getElementById('apCount').textContent||'0')-1); } else { alert(d.error||'Erro.'); } });
    }

    document.addEventListener('DOMContentLoaded', carregar);
    </script>
    <?php $sf_help_key = 'aprovacoes'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
    <?php include '../includes/scroll_to_top.php'; ?>
</body>
</html>
