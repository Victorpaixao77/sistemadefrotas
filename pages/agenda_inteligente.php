<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();
if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) { header('Location: ../login.php'); exit; }

$page_title = 'Agenda Inteligente';
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
        .ag-summary { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; margin-bottom:16px; }
        .ag-sum { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:12px; padding:12px 14px; }
        .ag-sum b { display:block; font-size:1.5rem; font-weight:800; } .ag-sum small { color:var(--text-secondary); font-size:.76rem; }
        .ag-sum--venc b{color:#dc2626;} .ag-sum--sem b{color:#f59e0b;} .ag-sum--mes b{color:#3b82f6;}
        .ag-group { margin-bottom:20px; } .ag-group > h3 { font-size:.95rem; margin:0 0 10px; display:flex; align-items:center; gap:8px; }
        .ag-item { display:flex; align-items:center; gap:12px; padding:11px 14px; background:var(--bg-secondary); border:1px solid var(--border-color); border-left-width:4px; border-radius:10px; margin-bottom:8px; text-decoration:none; }
        .ag-item:hover { border-color:var(--accent-primary,#3b82f6); }
        .ag-item.crit{border-left-color:#dc2626;} .ag-item.aviso{border-left-color:#f59e0b;} .ag-item.info{border-left-color:#3b82f6;}
        .ag-date { min-width:64px; text-align:center; } .ag-date .d{font-size:1.3rem;font-weight:800;color:var(--text-primary);line-height:1;} .ag-date .m{font-size:.68rem;text-transform:uppercase;color:var(--text-secondary);}
        .ag-body { flex:1; } .ag-body .t{font-weight:700;color:var(--text-primary);} .ag-body .s{font-size:.8rem;color:var(--text-secondary);}
        .ag-cat { font-size:.68rem; font-weight:700; padding:2px 8px; border-radius:999px; background:var(--bg-primary); color:var(--text-secondary); }
        .ag-chips { display:flex; gap:7px; flex-wrap:wrap; margin-bottom:14px; }
        .ag-chip { padding:6px 12px; border-radius:999px; border:1px solid var(--border-color); background:var(--bg-secondary); color:var(--text-secondary); cursor:pointer; font-size:.8rem; font-weight:600; }
        .ag-chip.active { background:var(--accent-primary,#3b82f6); color:#fff; border-color:transparent; }
    </style>
</head>
<body class="sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content fornc-page">
                <div class="fornc-toolbar">
                    <div class="fornc-search-block" style="flex:1"><i class="fas fa-calendar-check"></i><strong style="margin-left:8px">Agenda Inteligente</strong> <span style="color:var(--text-secondary);font-size:.85rem;margin-left:6px">vencimentos e compromissos da frota</span></div>
                    <div class="fornc-btn-row">
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="agRefresh"><i class="fas fa-arrows-rotate"></i> Atualizar</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>

                <div class="ag-summary">
                    <div class="ag-sum ag-sum--venc"><b id="agVenc">—</b><small>Vencidos</small></div>
                    <div class="ag-sum ag-sum--sem"><b id="agSem">—</b><small>Próx. 7 dias</small></div>
                    <div class="ag-sum ag-sum--mes"><b id="agMes">—</b><small>Este mês</small></div>
                    <div class="ag-sum"><b id="agTot">—</b><small>Total</small></div>
                </div>

                <div class="ag-chips" id="agChips"></div>
                <div id="agLista"><p style="color:var(--text-secondary)">Carregando…</p></div>
            </div>
        </div>
    </div>
    <script src="../js/theme.js?v=<?php echo $v; ?>"></script>
    <script src="../js/sidebar.js?v=<?php echo $v; ?>"></script>
    <script>
    (function () {
        var esc=function(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;};
        var MES=['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
        var todos=[], catAtiva='';
        function dias(dstr){ if(!dstr) return 99999; var d=new Date(dstr+'T00:00:00'), h=new Date(); h.setHours(0,0,0,0); return Math.round((d-h)/86400000); }
        function nivelCls(n){ return n==='critico'?'crit':(n==='aviso'?'aviso':'info'); }

        function carregar(){
            fetch('../api/central_alertas.php',{credentials:'same-origin'}).then(function(r){return r.json();})
                .then(function(d){ todos=(d.alertas||[]).filter(function(a){return a.data;}); render(); })
                .catch(function(){ document.getElementById('agLista').innerHTML='<p style="color:#dc3545">Erro ao carregar.</p>'; });
        }
        function render(){
            var venc=0,sem=0,mes=0, cats={};
            todos.forEach(function(a){ var dd=dias(a.data); if(dd<0)venc++; else if(dd<=7)sem++; if(dd<=31&&dd>=0||dd<0)mes++; cats[a.categoria]=(cats[a.categoria]||0)+1; });
            document.getElementById('agVenc').textContent=venc; document.getElementById('agSem').textContent=sem;
            document.getElementById('agMes').textContent=mes; document.getElementById('agTot').textContent=todos.length;
            // chips por categoria
            var chipsHtml='<span class="ag-chip '+(catAtiva===''?'active':'')+'" data-c="">Todos ('+todos.length+')</span>';
            Object.keys(cats).sort().forEach(function(c){ chipsHtml+='<span class="ag-chip '+(catAtiva===c?'active':'')+'" data-c="'+esc(c)+'">'+esc(c)+' ('+cats[c]+')</span>'; });
            document.getElementById('agChips').innerHTML=chipsHtml;
            document.querySelectorAll('.ag-chip').forEach(function(ch){ ch.addEventListener('click',function(){ catAtiva=ch.dataset.c; render(); }); });
            // grupos por urgência
            var lista=todos.filter(function(a){ return !catAtiva || a.categoria===catAtiva; }).slice().sort(function(a,b){ return dias(a.data)-dias(b.data); });
            var grupos=[['Vencidos',function(dd){return dd<0;},'#dc2626'],['Próximos 7 dias',function(dd){return dd>=0&&dd<=7;}],['Este mês',function(dd){return dd>7&&dd<=31;}],['Depois',function(dd){return dd>31;}]];
            var html='';
            grupos.forEach(function(g){
                var itens=lista.filter(function(a){return g[1](dias(a.data));});
                if(!itens.length) return;
                html+='<div class="ag-group"><h3>'+g[0]+' <span class="ag-cat">'+itens.length+'</span></h3>';
                itens.forEach(function(a){
                    var dt=new Date(a.data+'T00:00:00'); var dd=dias(a.data);
                    var quando=dd<0?('vencido há '+Math.abs(dd)+'d'):(dd===0?'hoje':('em '+dd+'d'));
                    html+='<a class="ag-item '+nivelCls(a.nivel)+'" href="'+esc(a.link||'#')+'">'+
                        '<div class="ag-date"><div class="d">'+dt.getDate()+'</div><div class="m">'+MES[dt.getMonth()]+'</div></div>'+
                        '<div class="ag-body"><div class="t">'+esc(a.titulo)+'</div><div class="s">'+esc(a.detalhe||'')+' · '+quando+'</div></div>'+
                        '<span class="ag-cat">'+esc(a.categoria)+'</span></a>';
                });
                html+='</div>';
            });
            document.getElementById('agLista').innerHTML=html||'<div class="fornc-empty-state"><h3>Nada na agenda 🎉</h3><p>Nenhum vencimento pendente no momento.</p></div>';
        }
        document.getElementById('agRefresh').addEventListener('click',carregar);
        carregar();
    })();
    </script>
    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'agenda_inteligente'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
