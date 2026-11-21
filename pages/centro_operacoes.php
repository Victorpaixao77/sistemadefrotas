<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/../includes/sf_api_base.php';
require_once __DIR__ . '/../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();

$page_title = 'Centro de Operações';
$u = fn($p) => htmlspecialchars(sf_app_url($p));
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" crossorigin="anonymous">
    <link rel="icon" type="image/png" href="../logo.png">
    <?php sf_page_help_css('..'); ?>
    <style>
        html, body { overflow-x: hidden; max-width: 100%; }
        .co-statusbar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 10px 14px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 12px; margin-bottom: 14px; }
        .co-pill { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-secondary); padding: 4px 10px; border-radius: 999px; background: var(--bg-tertiary); }
        .co-pill .dot { width: 8px; height: 8px; border-radius: 50%; background: #16a34a; }
        .co-pill.idle .dot { background: #f59e0b; } .co-pill.off .dot { background: #dc2626; } .co-pill.warn .dot { background: #f59e0b; }
        .co-pill small { opacity: .85; font-weight: 500; }
        .co-upd { margin-left: auto; font-size: 12px; color: var(--text-muted); display: flex; gap: 14px; align-items: center; flex-wrap: wrap; }
        .co-upd .live { display: inline-flex; align-items: center; gap: 5px; } .co-upd .live .dot { width: 7px; height: 7px; border-radius: 50%; background: #16a34a; animation: copulse 2s infinite; }
        .co-upd-btn { border: 1px solid var(--border-color); background: var(--bg-tertiary); color: var(--text-secondary); border-radius: 8px; padding: 4px 10px; font-size: 12px; cursor: pointer; }
        .co-upd-btn:hover { border-color: var(--accent-primary); color: var(--text-primary); }
        .co-upd .co-help-btn { padding: 4px 9px; }
        .co-dash-link { font-size: 12px; color: var(--accent-primary); text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
        .co-dash-link:hover { text-decoration: underline; }
        .co-tile-link { text-decoration: none; color: inherit; display: block; border-radius: 10px; transition: transform .12s; }
        .co-tile-link:hover { transform: translateY(-1px); }
        @keyframes copulse { 0%,100%{opacity:1} 50%{opacity:.35} }

        .co-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
        .co-action { display: inline-flex; align-items: center; gap: 7px; padding: 9px 15px; border-radius: 10px; font-size: 13px; font-weight: 600; text-decoration: none; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); transition: .15s; }
        .co-action:hover { border-color: var(--accent-primary); transform: translateY(-1px); }
        .co-action.primary { background: var(--accent-primary); border-color: var(--accent-primary); color: #fff; }
        .co-action.ok { background: #16a34a; border-color: #16a34a; color: #fff; }
        .co-action.amber { background: #d97706; border-color: #d97706; color: #fff; }
        .co-action.purple { background: #7c3aed; border-color: #7c3aed; color: #fff; }

        .co-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 14px; align-items: start; }
        @media (max-width: 1100px) { .co-grid { grid-template-columns: 1fr; } }
        .co-col { display: flex; flex-direction: column; gap: 14px; min-width: 0; }
        .card { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 14px; padding: 16px; }
        .card h3 { margin: 0 0 12px; font-size: .95rem; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
        .card h3 .more { margin-left: auto; font-size: 12px; font-weight: 500; }
        .card h3 .more a { color: var(--accent-primary); text-decoration: none; }

        /* IA */
        .co-ia { background: linear-gradient(145deg, var(--bg-secondary), color-mix(in srgb, var(--accent-primary) 8%, var(--bg-secondary))); }
        .co-ia .frase { display: flex; gap: 8px; align-items: flex-start; font-size: 13px; color: var(--text-secondary); margin-bottom: 6px; }
        .co-ia .frase i { color: var(--accent-primary); margin-top: 3px; font-size: 11px; }

        /* KPIs */
        .co-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; }
        .kpi { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 14px; padding: 14px 16px; }
        .kpi .ic { width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 15px; margin-bottom: 8px; }
        .kpi .lbl { font-size: 11px; text-transform: uppercase; letter-spacing: .03em; color: var(--text-muted); }
        .kpi .val { font-size: 1.4rem; font-weight: 800; color: var(--text-primary); margin-top: 3px; }
        .kpi .sub { font-size: 11px; color: var(--text-muted); margin-top: 3px; }

        /* Mapa */
        .co-map-wrap { position: relative; min-height: 280px; border-radius: 12px; overflow: hidden; border: 1px solid var(--border-color); background: #0f1824; }
        #coMapLeaflet { height: 280px; width: 100%; z-index: 1; }
        .co-map-empty { position: absolute; inset: 0; z-index: 2; display: flex; align-items: center; justify-content: center; text-align: center; padding: 16px; color: var(--text-secondary); background: rgba(15,24,36,.72); pointer-events: none; }
        .co-map-empty i { font-size: 2rem; color: var(--accent-primary); display: block; margin-bottom: 8px; }
        .co-map-wrap .legend { position: absolute; bottom: 10px; left: 10px; z-index: 3; display: flex; gap: 12px; font-size: 11px; color: #cbd5e1; background: rgba(0,0,0,.45); padding: 6px 10px; border-radius: 8px; pointer-events: none; }
        .co-map-wrap .legend span { display: inline-flex; align-items: center; gap: 5px; }
        .co-map-wrap .legend .d { width: 8px; height: 8px; border-radius: 50%; }
        .co-fin-locked { font-size: 13px; color: var(--text-muted); padding: 8px 0; }

        /* listas / tiles */
        .co-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 10px; }
        .co-tile { background: var(--bg-tertiary); border-radius: 10px; padding: 12px; text-align: center; }
        .co-tile .n { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); } .co-tile .l { font-size: 11px; color: var(--text-muted); margin-top: 3px; }
        .co-tile.green .n { color: #16a34a; } .co-tile.amber .n { color: #f59e0b; } .co-tile.red .n { color: #dc2626; } .co-tile.accent .n { color: var(--accent-primary); }

        .co-fin { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 18px; }
        .co-fin .row { display: flex; justify-content: space-between; font-size: 13px; padding: 5px 0; border-bottom: 1px dashed var(--border-color); }
        .co-fin .row .k { color: var(--text-secondary); } .co-fin .row .v { color: var(--text-primary); font-weight: 700; }

        .co-list .it { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 1px solid var(--border-color); text-decoration: none; }
        .co-list .it:last-child { border-bottom: none; }
        .co-list .it .ic { width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0; }
        .co-list .it .tx { flex: 1; min-width: 0; } .co-list .it .tx .t { font-size: 13px; color: var(--text-primary); font-weight: 600; } .co-list .it .tx .s { font-size: 11px; color: var(--text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .co-list .it .q { font-weight: 800; color: var(--text-primary); }
        .lv-crit .ic { background: rgba(239,68,68,.15); color: #dc2626; } .lv-warn .ic { background: rgba(245,158,11,.15); color: #f59e0b; } .lv-info .ic { background: rgba(59,130,246,.15); color: #3b82f6; }

        .co-tl .ev { display: flex; gap: 10px; padding: 8px 0; }
        .co-tl .ev .h { font-size: 12px; color: var(--text-muted); width: 42px; flex-shrink: 0; font-weight: 600; }
        .co-tl .ev .d { width: 9px; height: 9px; border-radius: 50%; background: var(--accent-primary); margin-top: 4px; flex-shrink: 0; }
        .co-tl .ev .t { font-size: 12px; color: var(--text-secondary); }

        .co-rank .r { display: flex; align-items: center; gap: 10px; padding: 7px 0; font-size: 13px; }
        .co-rank .r .pos { width: 22px; height: 22px; border-radius: 6px; background: var(--bg-tertiary); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; color: var(--text-secondary); }
        .co-rank .r .nm { flex: 1; color: var(--text-primary); } .co-rank .r .vv { font-weight: 700; color: #16a34a; }
        .co-venc { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--border-color); font-size: 13px; }
        .co-venc:last-child { border-bottom: none; } .co-venc .d { font-weight: 700; } .co-venc .d.red { color: #dc2626; } .co-venc .d.amber { color: #f59e0b; }
        .co-pend { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
        .co-pend a { display: flex; justify-content: space-between; align-items: center; padding: 8px 10px; border-radius: 8px; background: var(--bg-tertiary); text-decoration: none; color: var(--text-secondary); font-size: 12px; }
        .co-pend a .b { min-width: 22px; text-align: center; background: var(--accent-primary); color: #fff; border-radius: 999px; padding: 1px 7px; font-weight: 700; }
        .co-pend a .b.zero { background: var(--border-color); color: var(--text-muted); }
        .empty { color: var(--text-muted); font-size: 12px; padding: 12px 0; text-align: center; }
        canvas { max-height: 200px; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content fornc-page">

                <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px">
                    <div>
                        <h1 class="page-title" style="margin:0;font-size:1.35rem">Centro de Operações</h1>
                        <p style="margin:4px 0 0;font-size:13px;color:var(--text-muted)">Torre de controle em tempo real da frota</p>
                    </div>
                    <a href="<?php echo $u('index.php'); ?>" class="co-dash-link"><i class="fas fa-chart-line"></i> Ver histórico no Dashboard</a>
                </div>

                <!-- 1. Barra de status -->
                <div class="co-statusbar">
                    <span class="co-pill" id="stSistema"><span class="dot"></span> Sistema</span>
                    <span class="co-pill off" id="stGps"><span class="dot"></span> GPS <small id="stGpsN"></small></span>
                    <span class="co-pill" id="stCte" style="display:none"><span class="dot"></span> CT-e <small id="stCteN"></small></span>
                    <span class="co-pill" id="stMdfe" style="display:none"><span class="dot"></span> MDF-e <small id="stMdfeN"></small></span>
                    <span class="co-upd">
                        <span class="live"><span class="dot"></span> Tempo real</span>
                        <span>Atualizado: <b id="coUpd">—</b></span>
                        <button type="button" class="co-upd-btn" id="coRefreshBtn" title="Atualizar agora" aria-label="Atualizar agora"><i class="fas fa-rotate"></i></button>
                        <button type="button" class="co-upd-btn co-help-btn fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda sobre o Centro de Operações"><i class="fas fa-question-circle"></i></button>
                    </span>
                </div>

                <!-- 2. Ações rápidas -->
                <div class="co-actions" id="coActions">
                    <a class="co-action primary" href="<?php echo $u('pages/routes.php'); ?>"><i class="fas fa-plus"></i> Nova Viagem</a>
                    <a class="co-action purple" href="<?php echo $u('pages/ordens_transporte.php'); ?>"><i class="fas fa-file-signature"></i> Nova Ordem</a>
                    <a class="co-action ok co-act-fiscal" href="<?php echo $u('fiscal/pages/cte.php'); ?>"><i class="fas fa-file-invoice"></i> Emitir CT-e</a>
                    <a class="co-action amber co-act-fiscal" href="<?php echo $u('fiscal/pages/mdfe.php'); ?>"><i class="fas fa-file-lines"></i> Emitir MDF-e</a>
                    <a class="co-action" href="<?php echo $u('pages/abastecimentos.php'); ?>"><i class="fas fa-gas-pump"></i> Abastecimento</a>
                    <a class="co-action" href="<?php echo $u('pages/mapa_frota.php'); ?>"><i class="fas fa-map-marked-alt"></i> Abrir GPS</a>
                    <a class="co-action" href="<?php echo $u('pages/central_alertas.php'); ?>"><i class="fas fa-bell"></i> Alertas</a>
                    <a class="co-action purple" href="<?php echo $u('pages/assistente_ia.php'); ?>"><i class="fas fa-robot"></i> Perguntar à IA</a>
                </div>

                <div class="co-grid">
                    <!-- COLUNA PRINCIPAL -->
                    <div class="co-col">
                        <div class="card co-ia">
                            <h3><i class="fas fa-robot"></i> Resumo automático <span id="iaSaud" style="color:var(--text-muted);font-weight:500;font-size:12px"></span></h3>
                            <div id="iaFrases"><div class="empty">Carregando…</div></div>
                        </div>

                        <div class="co-kpis" id="coKpis"></div>

                        <div class="card">
                            <h3><i class="fas fa-map-marked-alt"></i> Mapa da Frota <span class="more"><a href="<?php echo $u('pages/mapa_frota.php'); ?>">Abrir completo</a></span></h3>
                            <div class="co-map-wrap">
                                <div id="coMapLeaflet"></div>
                                <div class="co-map-empty" id="coMapEmpty">
                                    <div><i class="fas fa-satellite-dish"></i> Nenhuma posição GPS recente.<br><small>Veículos aparecem aqui quando o app envia localização.</small></div>
                                </div>
                                <div class="legend">
                                    <span><span class="d" style="background:#16a34a"></span>Rodando</span>
                                    <span><span class="d" style="background:#f59e0b"></span>Parado</span>
                                    <span><span class="d" style="background:#94a3b8"></span>Sem sinal</span>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <h3><i class="fas fa-gauge-high"></i> Situação Operacional</h3>
                            <div class="co-tiles" id="coSituacao"></div>
                        </div>

                        <div class="card" id="coFinCard">
                            <h3><i class="fas fa-sack-dollar"></i> Financeiro — mês <span class="more"><a href="<?php echo $u('pages/lucratividade.php'); ?>">Análise</a></span></h3>
                            <div class="co-fin" id="coFin"></div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px" class="co-charts-wrap" id="coChartsWrap">
                            <div class="card" id="coChartFatCard"><h3><i class="fas fa-chart-line"></i> Faturamento (30d)</h3><canvas id="chFat"></canvas></div>
                            <div class="card"><h3><i class="fas fa-gas-pump"></i> Consumo (30d)</h3><canvas id="chCon"></canvas></div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px" class="co-charts-wrap">
                            <div class="card"><h3><i class="fas fa-medal"></i> Top Motoristas <span class="more"><a href="<?php echo $u('pages/indicadores_desempenho.php'); ?>">Ranking</a></span></h3><div class="co-rank" id="coTopMot"></div></div>
                            <div class="card" id="coTopVeiCard"><h3><i class="fas fa-trophy"></i> Top Veículos (lucro)</h3><div class="co-rank" id="coTopVei"></div></div>
                        </div>
                    </div>

                    <!-- COLUNA LATERAL -->
                    <div class="co-col">
                        <div class="card">
                            <h3><i class="fas fa-triangle-exclamation" style="color:#dc2626"></i> Alertas Críticos <span class="more"><a href="<?php echo $u('pages/central_alertas.php'); ?>">Ver todos</a></span></h3>
                            <div class="co-list" id="coCrit"></div>
                        </div>
                        <div class="card">
                            <h3><i class="fas fa-circle-exclamation" style="color:#f59e0b"></i> Atenção</h3>
                            <div class="co-list" id="coAtencao"></div>
                        </div>
                        <div class="card">
                            <h3><i class="fas fa-satellite-dish"></i> Eventos ao vivo</h3>
                            <div class="co-tl" id="coTimeline"></div>
                        </div>
                        <div class="card">
                            <h3><i class="fas fa-calendar-check"></i> Próximos Vencimentos</h3>
                            <div id="coVenc"></div>
                        </div>
                        <div class="card">
                            <h3><i class="fas fa-list-check"></i> Pendências</h3>
                            <div class="co-pend" id="coPend"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <?php sf_render_api_scripts('..'); ?>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
    function moeda(v){ return 'R$ ' + Number(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); }
    function moedaK(v){ return 'R$ ' + Number(v||0).toLocaleString('pt-BR',{maximumFractionDigits:0}); }
    function num(v){ return Number(v||0).toLocaleString('pt-BR'); }
    function esc(s){ var d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
    var chFat=null, chCon=null, coMap=null, coMapMarkers=[], coMapDidFit=false, coRefreshTimer=null;

    function kpi(ic, cor, lbl, val, sub){
        return '<div class="kpi"><div class="ic" style="background:'+cor+'22;color:'+cor+'"><i class="fas '+ic+'"></i></div>'+
               '<div class="lbl">'+lbl+'</div><div class="val">'+val+'</div>'+(sub?'<div class="sub">'+sub+'</div>':'')+'</div>';
    }
    function listItem(cls, ic, t, s, link){
        return '<a class="it '+cls+'" href="'+(link||'#')+'"><div class="ic"><i class="fas '+ic+'"></i></div>'+
               '<div class="tx"><div class="t">'+esc(t)+'</div>'+(s?'<div class="s">'+esc(s)+'</div>':'')+'</div></a>';
    }

    function pillClass(st){ return st==='online'||st==='ok'?'':(st==='warn'||st==='idle'?' warn':' off'); }
    function applyStatusPills(st, perm){
        function set(id, state, note){
            var el=document.getElementById(id); if(!el) return;
            el.className='co-pill'+pillClass(state||'off');
            if(note){ var sm=el.querySelector('small'); if(sm) sm.textContent=note; }
        }
        set('stSistema', st.sistema||'online');
        var gpsNote = st.gps_veiculos!=null ? (' · '+st.gps_veiculos+' veíc.') : '';
        set('stGps', st.gps||'off', gpsNote);
        var showFiscal = perm && perm.fiscal;
        ['stCte','stMdfe'].forEach(function(id){
            var el=document.getElementById(id); if(el) el.style.display=showFiscal?'inline-flex':'none';
        });
        if(showFiscal){
            var cteN = st.cte_pendentes>0 ? (' · '+st.cte_pendentes+' rascunho') : ' · ok';
            var mdfeN = st.mdfe_pendentes>0 ? (' · '+st.mdfe_pendentes+' pend.') : ' · ok';
            set('stCte', st.cte||'ok', cteN);
            set('stMdfe', st.mdfe||'ok', mdfeN);
        }
    }

    function gpsColor(estado){
        if(estado==='rodando') return '#16a34a';
        if(estado==='parado') return '#f59e0b';
        return '#94a3b8';
    }

    function renderCoMap(posicoes){
        var emptyEl=document.getElementById('coMapEmpty');
        var list=posicoes||[];
        if(typeof L==='undefined' || !document.getElementById('coMapLeaflet')) return;
        if(!coMap){
            coMap=L.map('coMapLeaflet',{zoomControl:true, attributionControl:true}).setView([-15.78,-47.93],4);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19, attribution:'&copy; OpenStreetMap'}).addTo(coMap);
        }
        coMapMarkers.forEach(function(m){ coMap.removeLayer(m); });
        coMapMarkers=[];
        if(!list.length){
            if(emptyEl) emptyEl.style.display='flex';
            return;
        }
        if(emptyEl) emptyEl.style.display='none';
        var bounds=[];
        list.forEach(function(p){
            if(!p.lat||!p.lng) return;
            var c=gpsColor(p.estado);
            var mk=L.circleMarker([p.lat,p.lng],{radius:7,color:c,fillColor:c,fillOpacity:.85,weight:2})
                .bindPopup('<b>'+esc(p.placa||'Veículo')+'</b><br>'+(p.velocidade!=null?Math.round(p.velocidade)+' km/h':'Parado')+'<br><small>'+esc(p.estado||'')+'</small>');
            mk.addTo(coMap); coMapMarkers.push(mk); bounds.push([p.lat,p.lng]);
        });
        if(bounds.length===1) { coMap.setView(bounds[0], 12); coMapDidFit=true; }
        else if(bounds.length>1 && !coMapDidFit) { coMap.fitBounds(bounds,{padding:[24,24], maxZoom:12}); coMapDidFit=true; }
        setTimeout(function(){ coMap.invalidateSize(); }, 120);
    }

    function tileLink(href, cls, n, lbl){
        return '<a class="co-tile-link" href="'+href+'"><div class="co-tile '+cls+'"><div class="n">'+n+'</div><div class="l">'+lbl+'</div></div></a>';
    }

    function carregar(){
        fetch('../api/centro_operacoes.php').then(function(r){return r.json();}).then(function(d){
            if(!d||!d.success){
                document.getElementById('coUpd').textContent='erro';
                return;
            }
            var canFin = !!(d.permissoes && d.permissoes.financeiro);
            document.getElementById('coUpd').textContent = d.atualizado_em || '—';
            applyStatusPills(d.status||{}, d.permissoes||{});
            var showFiscal = !!(d.permissoes && d.permissoes.fiscal);
            document.querySelectorAll('.co-act-fiscal').forEach(function(el){ el.style.display = showFiscal ? '' : 'none'; });

            document.getElementById('iaSaud').textContent = (d.ia && d.ia.saudacao) ? '· '+d.ia.saudacao : '';
            document.getElementById('iaFrases').innerHTML = (d.ia && d.ia.frases && d.ia.frases.length)
                ? d.ia.frases.map(function(f){ return '<div class="frase"><i class="fas fa-circle"></i> '+esc(f)+'</div>'; }).join('')
                : '<div class="empty">Sem dados para resumir.</div>';

            var k=d.kpis||{};
            var kpiHtml = '';
            if(canFin){
                kpiHtml += kpi('fa-sack-dollar','#3b82f6','Receita (mês)', moedaK(k.receita)) +
                    kpi('fa-chart-line','#16a34a','Lucro', moedaK(k.lucro)) +
                    kpi('fa-coins','#16a34a','Lucro / km', moeda(k.lucro_km));
            }
            kpiHtml += kpi('fa-road','#8b5cf6','KM rodados', num(Math.round(k.km||0))) +
                kpi('fa-gas-pump','#f59e0b','Consumo médio', (k.consumo_medio? k.consumo_medio.toFixed(2):'0')+' km/L') +
                kpi('fa-truck','#16a34a','Disponibilidade', (k.disponibilidade||0)+'%') +
                kpi('fa-boxes-packing','#3b82f6','Ocupação', (k.ocupacao||0)+'%') +
                kpi('fa-route','#f59e0b','Em andamento', num(k.viagens_andamento||0));
            document.getElementById('coKpis').innerHTML = kpiHtml;

            var s=d.situacao||{};
            document.getElementById('coSituacao').innerHTML =
                tileLink('<?php echo $u('pages/vehicles.php'); ?>', 'green', num(s.disponiveis), 'Disponíveis') +
                tileLink('<?php echo $u('pages/routes.php'); ?>', 'accent', num(s.rodando), 'Rodando') +
                tileLink('<?php echo $u('pages/planejamento.php'); ?>', '', num(s.programadas), 'Programadas') +
                tileLink('<?php echo $u('pages/manutencoes.php'); ?>', 'amber', num(s.manutencao), 'Manutenção') +
                tileLink('<?php echo $u('pages/routes.php'); ?>', 'red', num(s.atraso), 'Atrasadas') +
                tileLink('<?php echo $u('pages/vehicles.php'); ?>', '', num(s.inativos), 'Inativos');

            var finCard=document.getElementById('coFinCard');
            var chartFat=document.getElementById('coChartFatCard');
            var topVeiCard=document.getElementById('coTopVeiCard');
            if(canFin && d.financeiro){
                finCard.style.display='';
                if(chartFat) chartFat.style.display='';
                if(topVeiCard) topVeiCard.style.display='';
                var f=d.financeiro;
                document.getElementById('coFin').innerHTML =
                    frow('Receita', moeda(f.receita)) + frow('Lucro', moeda(f.lucro)) +
                    frow('A receber', moeda(f.a_receber)) + frow('A pagar', moeda(f.a_pagar)) +
                    frow('Combustível', moeda(f.combustivel)) + frow('Pedágio', moeda(f.pedagio)) +
                    frow('Manutenção', moeda(f.manutencao)) + frow('Margem', (f.margem||0)+'%');
            } else {
                finCard.style.display='';
                if(chartFat) chartFat.style.display='none';
                if(topVeiCard) topVeiCard.style.display='none';
                document.getElementById('coFin').innerHTML = '<div class="co-fin-locked"><i class="fas fa-lock"></i> Dados financeiros restritos ao seu perfil de usuário.</div>';
            }

            renderList('coCrit', d.alertas.criticos, 'lv-crit', 'fa-triangle-exclamation', 'Nenhum alerta crítico. ✔');
            renderList('coAtencao', d.alertas.atencao, 'lv-warn', 'fa-circle-exclamation', 'Nada em atenção.');

            var tl=d.timeline||[];
            document.getElementById('coTimeline').innerHTML = tl.length ? tl.map(function(e){
                var h = e.dh ? e.dh.substring(11,16) : '--:--';
                return '<div class="ev"><div class="h">'+h+'</div><div class="d"></div><div class="t">'+esc(e.txt)+'</div></div>';
            }).join('') : '<div class="empty">Sem eventos recentes.</div>';

            var vc=d.vencimentos||[];
            document.getElementById('coVenc').innerHTML = vc.length ? vc.map(function(v){
                var cls = v.dias<0?'red':(v.dias<=15?'amber':'');
                var txt = v.dias<0?('vencido há '+Math.abs(v.dias)+'d'):(v.dias+' dias');
                return '<a class="co-venc" style="text-decoration:none" href="../pages/'+esc(v.link)+'"><span style="color:var(--text-secondary)">'+esc(v.tipo)+' <small style="color:var(--text-muted)">('+v.qtd+')</small></span><span class="d '+cls+'">'+txt+'</span></a>';
            }).join('') : '<div class="empty">Nenhum vencimento próximo.</div>';

            var rm=(d.rankings&&d.rankings.motoristas)||[];
            document.getElementById('coTopMot').innerHTML = rm.length ? rm.map(function(m,i){
                return '<div class="r"><span class="pos">'+(i+1)+'</span><span class="nm">'+esc(m.nome)+'</span><span class="vv">'+(m.kml?m.kml+' km/L':num(Math.round(m.km))+' km')+'</span></div>';
            }).join('') : '<div class="empty">Sem dados no mês.</div>';
            var rv=(d.rankings&&d.rankings.veiculos)||[];
            document.getElementById('coTopVei').innerHTML = rv.length ? rv.map(function(v,i){
                return '<div class="r"><span class="pos">'+(i+1)+'</span><span class="nm">'+esc(v.placa)+'</span><span class="vv">'+moedaK(v.lucro)+'</span></div>';
            }).join('') : '<div class="empty">Sem dados no mês.</div>';

            var p=d.pendencias||{};
            var pmap=[['CT-e','cte','fiscal/pages/cte.php', showFiscal],['MDF-e','mdfe','fiscal/pages/mdfe.php', showFiscal],['Ordens','ordens','pages/ordens_transporte.php', true],['Aprovações','aprovacoes','pages/aprovacoes.php', true],['Abastec.','abastecimentos','pages/abastecimentos_motoristas.php', true],['Manutenções','manutencoes','pages/manutencoes.php', true]];
            document.getElementById('coPend').innerHTML = pmap.filter(function(it){ return it[3]; }).map(function(it){
                var q=p[it[1]]||0;
                return '<a href="../'+it[2]+'"><span>'+it[0]+'</span><span class="b'+(q?'':' zero')+'">'+q+'</span></a>';
            }).join('');

            if(canFin && d.charts && d.charts.faturamento) drawChart('chFat', d.charts.faturamento, '#3b82f6', 'line');
            else if(chFat){ chFat.destroy(); chFat=null; }
            drawChart('chCon', d.charts.consumo, '#f59e0b', 'bar');
            renderCoMap(d.gps_posicoes||[]);
        }).catch(function(){
            document.getElementById('coUpd').textContent='falha';
        });
    }
    function frow(k,v){ return '<div class="row"><span class="k">'+k+'</span><span class="v">'+v+'</span></div>'; }
    function renderList(id, arr, cls, ic, emptyMsg){
        var el=document.getElementById(id);
        el.innerHTML = (arr&&arr.length) ? arr.slice(0,6).map(function(a){
            var link = a.link || 'central_alertas.php';
            if (link.indexOf('fiscal/') === 0 || link.indexOf('pages/') === 0) link = '../' + link;
            else link = '../pages/' + link;
            return listItem(cls, ic, a.titulo, a.sub, link);
        }).join('') : '<div class="empty">'+emptyMsg+'</div>';
    }
    function drawChart(id, data, cor, tipo){
        if(typeof Chart==='undefined' || !data) return;
        var ctx=document.getElementById(id).getContext('2d');
        var ex = id==='chFat'?chFat:chCon;
        if(ex){ ex.destroy(); }
        var c=new Chart(ctx,{ type:tipo, data:{ labels:data.labels||[], datasets:[{ label:'', data:data.values||[], borderColor:cor, backgroundColor:cor+'55', fill:tipo==='line', tension:.35, borderWidth:2, pointRadius:0 }]},
            options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{ x:{ ticks:{ maxTicksLimit:6, color:'#94a3b8', font:{size:10} }, grid:{display:false} }, y:{ ticks:{ color:'#94a3b8', font:{size:10} }, grid:{ color:'rgba(148,163,184,.1)' } } } } });
        if(id==='chFat') chFat=c; else chCon=c;
    }

    document.addEventListener('DOMContentLoaded', function(){
        document.getElementById('coRefreshBtn')?.addEventListener('click', carregar);
        carregar();
        coRefreshTimer = setInterval(function(){
            if (!document.hidden) carregar();
        }, 60000);
    });
    </script>
    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'centro_operacoes'; include '../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
