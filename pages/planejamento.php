<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
require_once __DIR__ . '/../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();

$page_title = 'Planejamento';
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
        .pl-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .pl-kpi { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 14px; padding: 16px; }
        .pl-kpi .n { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); }
        .pl-kpi .l { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
        .pl-kpi.accent .n { color: var(--accent-primary); } .pl-kpi.green .n { color: #16a34a; }
        .pl-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--text-muted); margin: 8px 0 12px; }
        .pl-list { display: flex; flex-direction: column; gap: 12px; }
        .pl-card { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 14px; padding: 16px 18px; }
        .pl-card .top { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .pl-num { font-weight: 800; color: var(--text-primary); }
        .pl-meta { color: var(--text-secondary); font-size: .88rem; margin-top: 4px; }
        .pl-meta i { color: var(--accent-primary); }
        .pl-frete { font-size: 1.1rem; font-weight: 800; color: #16a34a; }
        .pl-assign { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; align-items: center; }
        .pl-assign select, .pl-assign input { padding: 8px 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-tertiary); color: var(--text-primary); font-size: 13px; }
        .pl-assign select { min-width: 150px; }
        .pl-btn { border: none; border-radius: 8px; padding: 9px 16px; font-weight: 700; font-size: 13px; cursor: pointer; background: var(--accent-primary); color: #fff; display: inline-flex; align-items: center; gap: 6px; }
        .pl-btn:hover { filter: brightness(1.08); }
        .pl-empty { padding: 40px; text-align: center; color: var(--text-muted); border: 1px dashed var(--border-color); border-radius: 12px; }
        .pl-empty i { color: #16a34a; font-size: 1.4rem; display: block; margin-bottom: 8px; }
        .pl-msg { font-size: 12px; margin-left: 6px; }
    </style>
</head>
<body class="sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content">
                <p style="color:var(--text-secondary);margin:0 0 16px;">Quadro de planejamento: ordens <strong>aprovadas</strong> aguardando veículo/motorista. Atribua e <strong>gere a rota</strong> direto daqui. Use o <a href="<?php echo htmlspecialchars(sf_app_url('pages/simulador_rota.php')); ?>" style="color:var(--accent-primary);">Simulador</a> para o custo.</p>

                <div class="pl-kpis" id="plKpis"></div>

                <div class="pl-title" style="display:flex;align-items:center;">Ordens a planejar
                    <button type="button" id="helpBtn" class="fornc-btn fornc-btn--ghost fornc-btn--icon" title="Ajuda" aria-label="Ajuda" style="margin-left:auto;"><i class="fas fa-question-circle"></i></button>
                </div>
                <div class="pl-list" id="plList"><div class="pl-empty">Carregando…</div></div>
            </div>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
    var PL_CSRF = '<?php echo htmlspecialchars(csrf_token_get()); ?>';
    var plVeics = [], plMots = [];
    function esc(s){ var d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
    function moeda(v){ return 'R$ ' + Number(v||0).toLocaleString('pt-BR',{minimumFractionDigits:0,maximumFractionDigits:0}); }
    function num(v){ return Number(v||0).toLocaleString('pt-BR'); }

    function optsVeic(sel){ return '<option value="">Veículo…</option>' + plVeics.map(function(v){ return '<option value="'+v.id+'"'+(String(v.id)===String(sel)?' selected':'')+'>'+esc(v.placa)+(v.ocupado?' (em viagem)':'')+'</option>'; }).join(''); }
    function optsMot(sel){ return '<option value="">Motorista…</option>' + plMots.map(function(m){ return '<option value="'+m.id+'"'+(String(m.id)===String(sel)?' selected':'')+'>'+esc(m.nome)+'</option>'; }).join(''); }

    function carregar(){
        fetch('../api/planejamento.php').then(function(r){return r.json();}).then(function(d){
            if(!d||!d.success) return;
            plVeics=d.veiculos||[]; plMots=d.motoristas||[];
            var k=d.kpis;
            document.getElementById('plKpis').innerHTML =
                '<div class="pl-kpi accent"><div class="n">'+num(k.ots)+'</div><div class="l">OTs a planejar</div></div>'+
                '<div class="pl-kpi"><div class="n">'+num(Math.round(k.km))+'</div><div class="l">KM a rodar</div></div>'+
                '<div class="pl-kpi"><div class="n">'+num(Math.round(k.peso/1000))+' t</div><div class="l">Peso total</div></div>'+
                '<div class="pl-kpi green"><div class="n">'+moeda(k.frete)+'</div><div class="l">Frete a executar</div></div>'+
                '<div class="pl-kpi"><div class="n">'+num(k.veiculos_livres)+'/'+num(k.veiculos_total)+'</div><div class="l">Veículos livres</div></div>';

            var list=document.getElementById('plList');
            var arr=d.a_planejar||[];
            if(!arr.length){ list.innerHTML='<div class="pl-empty"><i class="fas fa-check-circle"></i> Nada a planejar. Todas as ordens aprovadas já têm rota.</div>'; return; }
            list.innerHTML=arr.map(function(o){
                var trecho=(o.origem||o.estado_origem||'—')+' → '+(o.destino||o.estado_destino||'—');
                var dcoleta=o.data_coleta?o.data_coleta.replace(' ','T').substring(0,16):'';
                return '<div class="pl-card" id="plc'+o.id+'"><div class="top"><div>'+
                    '<div class="pl-num">'+esc(o.numero)+(o.cliente_nome?(' · '+esc(o.cliente_nome)):'')+'</div>'+
                    '<div class="pl-meta"><i class="fas fa-route"></i> '+esc(trecho)+' · '+num(Math.round(o.distancia_km))+' km · '+num(Math.round((o.peso_carga||0)/1000))+' t</div></div>'+
                    '<div class="pl-frete">'+moeda(o.valor_frete)+'</div></div>'+
                    '<div class="pl-assign">'+
                        '<select id="plv'+o.id+'">'+optsVeic(o.veiculo_id)+'</select>'+
                        '<select id="plm'+o.id+'">'+optsMot(o.motorista_id)+'</select>'+
                        '<input type="datetime-local" id="pld'+o.id+'" value="'+dcoleta+'" title="Coleta">'+
                        '<button class="pl-btn" onclick="planejar('+o.id+')"><i class="fas fa-route"></i> Planejar</button>'+
                        '<span class="pl-msg" id="plmsg'+o.id+'"></span>'+
                    '</div></div>';
            }).join('');
        });
    }

    function otApi(p){ return fetch('../api/ordens_transporte.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(p).toString()}).then(function(r){return r.json();}); }

    function planejar(id){
        var v=document.getElementById('plv'+id).value, m=document.getElementById('plm'+id).value, dc=document.getElementById('pld'+id).value;
        var msg=document.getElementById('plmsg'+id); msg.style.color='var(--text-muted)';
        if(!v||!m){ msg.style.color='#dc2626'; msg.textContent='Escolha veículo e motorista.'; return; }
        msg.textContent='Atribuindo…';
        // 1) grava só veículo/motorista/coleta na OT (ação atribuir — não toca no resto)
        otApi({action:'atribuir', id:id, veiculo_id:v, motorista_id:m, data_coleta:dc, csrf_token:PL_CSRF}).then(function(r){
            if(!r.success){ msg.style.color='#dc2626'; msg.textContent=r.error||'Erro.'; return; }
            msg.textContent='Gerando rota…';
            // 2) gera a rota (reusa a ação gerar_rota da OT)
            return otApi({action:'gerar_rota', id:id, csrf_token:PL_CSRF}).then(function(r2){
                if(r2.success){ msg.style.color='#16a34a'; msg.textContent='Rota #'+r2.rota_id+' criada!'; var c=document.getElementById('plc'+id); setTimeout(function(){ if(c)c.remove(); carregar(); }, 700); }
                else { msg.style.color='#dc2626'; msg.textContent=r2.error||'Erro ao gerar rota.'; }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', carregar);
    </script>
    <?php $sf_help_key = 'planejamento'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
    <?php include '../includes/scroll_to_top.php'; ?>
</body>
</html>
