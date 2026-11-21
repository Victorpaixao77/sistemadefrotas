<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
require_once __DIR__ . '/../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();

$page_title = 'Manutenção Preditiva';
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
        .mp-lead { color: var(--text-secondary); margin: 0 0 16px; }
        .mp-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .mp-c { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 14px; padding: 16px; }
        .mp-c .n { font-size: 1.7rem; font-weight: 800; } .mp-c .l { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
        .mp-c.red .n { color: #dc2626; } .mp-c.amber .n { color: #f59e0b; } .mp-c.green .n { color: #16a34a; } .mp-c.gray .n { color: var(--text-muted); }
        .mp-table .placa { font-weight: 700; color: var(--text-primary); }
        .mp-bar { height: 7px; border-radius: 999px; background: var(--bg-tertiary); overflow: hidden; min-width: 90px; }
        .mp-bar > span { display: block; height: 100%; }
        .mp-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 999px; text-transform: uppercase; }
        .b-atrasada { background: rgba(239,68,68,.16); color: #dc2626; } .b-proxima { background: rgba(245,158,11,.16); color: #f59e0b; }
        .b-em_dia { background: rgba(16,185,129,.16); color: #16a34a; } .b-sem_dados { background: var(--bg-tertiary); color: var(--text-muted); }
        .mp-fonte { font-size: 10px; color: var(--text-muted); }
        .mp-empty { padding: 40px; text-align: center; color: var(--text-muted); }
        .mp-note { font-size: 12px; color: var(--text-muted); margin-top: 12px; }
    </style>
</head>
<body class="sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content">
                <p class="mp-lead">Previsão de <strong>quando revisar cada veículo</strong>, calculada com dados reais: KM desde a última revisão + KM/dia médio (rotas dos últimos 90 dias). O intervalo vem do <strong>histórico do próprio veículo</strong> quando há; senão, um intervalo padrão (indicado).</p>

                <div class="mp-cards" id="mpCards"></div>

                <div class="fornc-toolbar">
                    <div class="fornc-search-block">
                        <label for="mpBusca">Busca rápida</label>
                        <div class="fornc-search-inner">
                            <i class="fas fa-search"></i>
                            <input type="text" id="mpBusca" placeholder="Placa ou modelo..." autocomplete="off">
                        </div>
                    </div>
                    <div class="fornc-filters-inline">
                        <div class="fg">
                            <label for="mpStatusFilter">Status</label>
                            <select id="mpStatusFilter">
                                <option value="">Todos</option>
                                <option value="atrasada">Atrasada</option>
                                <option value="proxima">Próxima</option>
                                <option value="em_dia">Em dia</option>
                                <option value="sem_dados">Sem dados</option>
                            </select>
                        </div>
                    </div>
                    <div class="fornc-btn-row">
                        <button id="mpBtnKm" class="fornc-btn fornc-btn--accent" onclick="atualizarKm()"><i class="fas fa-arrows-rotate"></i> Atualizar KM da frota</button>
                        <button type="button" id="helpBtn" class="fornc-btn fornc-btn--ghost fornc-btn--icon" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                        <span id="mpKmMsg" style="font-size:12px;color:var(--text-muted);align-self:center;"></span>
                    </div>
                </div>

                <div class="fornc-table-wrap">
                    <table class="fornc-table mp-table">
                        <thead><tr><th>Veículo</th><th>KM atual</th><th>Desde a revisão</th><th>Progresso</th><th>KM restante</th><th>Previsão</th><th>Status</th></tr></thead>
                        <tbody id="mpBody"><tr><td colspan="7" class="mp-empty">Carregando…</td></tr></tbody>
                    </table>
                </div>
                <p class="mp-note" id="mpNote"></p>
            </div>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
    function esc(s){ var d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
    function num(v){ return Number(v||0).toLocaleString('pt-BR'); }
    function dt(s){ if(!s) return '—'; var p=s.substring(0,10).split('-'); return p[2]+'/'+p[1]+'/'+p[0]; }
    var LBL={atrasada:'Atrasada',proxima:'Próxima',em_dia:'Em dia',sem_dados:'Sem dados'};
    var COR={atrasada:'#dc2626',proxima:'#f59e0b',em_dia:'#16a34a',sem_dados:'#64748b'};
    var mpItens=[], mpBusca='', mpStatus='';

    function carregar(){
        fetch('../api/manutencao_preditiva.php').then(function(r){return r.json();}).then(function(d){
            if(!d||!d.success){ document.getElementById('mpBody').innerHTML='<tr><td colspan="7" class="mp-empty">Não foi possível calcular.</td></tr>'; return; }
            var r=d.resumo;
            document.getElementById('mpCards').innerHTML=
                '<div class="mp-c red"><div class="n">'+num(r.atrasada)+'</div><div class="l">Revisão atrasada</div></div>'+
                '<div class="mp-c amber"><div class="n">'+num(r.proxima)+'</div><div class="l">Revisão próxima</div></div>'+
                '<div class="mp-c green"><div class="n">'+num(r.em_dia)+'</div><div class="l">Em dia</div></div>'+
                '<div class="mp-c gray"><div class="n">'+num(r.sem_dados)+'</div><div class="l">Sem dados de KM</div></div>';
            document.getElementById('mpNote').textContent='Intervalo padrão usado quando não há histórico: '+num(d.intervalo_padrao)+' km. Cadastre KM atual e KM da última manutenção nos veículos para previsões mais precisas.';
            mpItens=d.itens||[];
            renderMp();
        });
    }
    function renderMp(){
            var itens=mpItens.filter(function(o){
                var okS = !mpStatus || o.status===mpStatus;
                var okB = !mpBusca || ((o.placa||'')+' '+(o.modelo||'')).toLowerCase().indexOf(mpBusca)>-1;
                return okS && okB;
            });
            if(!itens.length){ document.getElementById('mpBody').innerHTML='<tr><td colspan="7" class="mp-empty">Nenhum veículo'+((mpStatus||mpBusca)?' com esse filtro':'')+'.</td></tr>'; return; }
            document.getElementById('mpBody').innerHTML=itens.map(function(o){
                if(o.status==='sem_dados'){
                    return '<tr><td class="placa">'+esc(o.placa)+(o.modelo?('<br><small style="color:var(--text-muted)">'+esc(o.modelo)+'</small>'):'')+'</td>'+
                        '<td colspan="5" style="color:var(--text-muted)">'+esc(o.detalhe)+'</td>'+
                        '<td><span class="mp-badge b-sem_dados">Sem dados</span></td></tr>';
                }
                var pct=Math.max(0,Math.min(100, Math.round(o.km_desde/o.intervalo*100)));
                var prev = o.km_restante<=0 ? 'Revisar agora' : ((o.dias_restante!=null?('~'+num(o.dias_restante)+' dias'):('~'+num(o.km_restante)+' km'))+(o.data_prevista?(' · '+dt(o.data_prevista)):''));
                return '<tr><td class="placa">'+esc(o.placa)+(o.modelo?('<br><small style="color:var(--text-muted)">'+esc(o.modelo)+'</small>'):'')+'</td>'+
                    '<td>'+num(o.km_atual)+' km</td>'+
                    '<td>'+num(o.km_desde)+' / '+num(o.intervalo)+' km<br><span class="mp-fonte">intervalo: '+(o.intervalo_fonte==='historico'?'histórico do veículo':'padrão')+'</span></td>'+
                    '<td><div class="mp-bar"><span style="width:'+pct+'%;background:'+COR[o.status]+'"></span></div></td>'+
                    '<td>'+(o.km_restante<=0?('<b style="color:#dc2626">'+num(o.km_restante)+' km</b>'):num(o.km_restante)+' km')+'</td>'+
                    '<td>'+prev+'</td>'+
                    '<td><span class="mp-badge b-'+o.status+'">'+LBL[o.status]+'</span></td></tr>';
            }).join('');
    }
    document.addEventListener('DOMContentLoaded', function(){
        document.getElementById('mpBusca').addEventListener('input', function(e){ mpBusca=e.target.value.trim().toLowerCase(); renderMp(); });
        document.getElementById('mpStatusFilter').addEventListener('change', function(e){ mpStatus=e.target.value; renderMp(); });
    });
    var MP_CSRF = '<?php echo htmlspecialchars(csrf_token_get()); ?>';
    function atualizarKm(){
        var btn=document.getElementById('mpBtnKm'), msg=document.getElementById('mpKmMsg');
        btn.disabled=true; msg.style.color='var(--text-muted)'; msg.textContent='Atualizando…';
        fetch('../api/manutencao_preditiva.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'atualizar_km',csrf_token:MP_CSRF}).toString()})
            .then(function(r){return r.json();}).then(function(d){
                btn.disabled=false;
                if(d.success){ msg.style.color='#16a34a'; msg.textContent='KM atualizado ('+d.atualizados+' veículo(s)). Recalculando…'; carregar(); }
                else { msg.style.color='#dc2626'; msg.textContent=d.error||'Erro.'; }
            }).catch(function(){ btn.disabled=false; msg.style.color='#dc2626'; msg.textContent='Erro de conexão.'; });
    }
    document.addEventListener('DOMContentLoaded', carregar);
    </script>
    <?php $sf_help_key = 'manutencao_preditiva'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
    <?php include '../includes/scroll_to_top.php'; ?>
</body>
</html>
