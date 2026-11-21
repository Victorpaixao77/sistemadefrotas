<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();
if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) { header('Location: ../login.php'); exit; }

$page_title = 'Digital Twin da Frota';
$v = function_exists('sf_asset_v') ? sf_asset_v() : '1';
$empresa_nome = (string) ($_SESSION['empresa_nome'] ?? 'Minha empresa');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" type="image/png" href="../logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="../css/theme.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="../css/responsive.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="../css/fornc-modern-page.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="../css/sf-premium.css?v=<?php echo $v; ?>">
    <?php sf_page_help_css('..'); ?>
    <style>
        .dt-head { display:flex; align-items:center; gap:10px; margin-bottom:6px; }
        .dt-head h1 { font-size:1.3rem; margin:0; } .dt-live { font-size:.72rem; color:#16a34a; font-weight:700; display:flex; align-items:center; gap:5px; }
        .dt-live .dot { width:8px; height:8px; border-radius:50%; background:#16a34a; animation:dtp 1.5s infinite; } @keyframes dtp{0%,100%{opacity:1}50%{opacity:.3}}
        .dt-sum { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:12px; margin:12px 0 18px; }
        .dt-s { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:12px; padding:12px 14px; } .dt-s b{display:block;font-size:1.6rem;font-weight:800;} .dt-s small{color:var(--text-secondary);font-size:.74rem;}
        .dt-s.mov b{color:#16a34a;} .dt-s.par b{color:#f59e0b;} .dt-s.off b{color:#94a3b8;}
        .dt-wrap { display:grid; grid-template-columns:1.1fr 1fr; gap:16px; } @media(max-width:900px){.dt-wrap{grid-template-columns:1fr;}}
        #dtMap { height:440px; border-radius:14px; border:1px solid var(--border-color); }
        .dt-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:12px; align-content:start; max-height:440px; overflow:auto; }
        .dt-card { border-radius:12px; padding:12px 14px; border:1px solid var(--border-color); border-left:4px solid #94a3b8; background:var(--bg-secondary); }
        .dt-card.movimento{border-left-color:#16a34a;} .dt-card.parado{border-left-color:#f59e0b;} .dt-card.sem_sinal{border-left-color:#94a3b8;opacity:.85;}
        .dt-card .p { font-weight:800; color:var(--text-primary); }
        .dt-card .st { font-size:.7rem; text-transform:uppercase; font-weight:700; margin-top:2px; }
        .dt-card.movimento .st{color:#16a34a;} .dt-card.parado .st{color:#f59e0b;} .dt-card.sem_sinal .st{color:#94a3b8;}
        .dt-card .m { font-size:.78rem; color:var(--text-secondary); margin-top:4px; }
    </style>
</head>
<body class="sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content fornc-page">
                <div class="fornc-toolbar">
                    <div class="fornc-search-block" style="flex:1">
                        <div class="dt-head"><i class="fas fa-diagram-project" style="color:var(--accent-primary,#3b82f6);font-size:1.2rem"></i>
                        <h1>Digital Twin · <?php echo htmlspecialchars($empresa_nome); ?></h1>
                        <span class="dt-live"><span class="dot"></span> AO VIVO <span id="dtTs" style="color:var(--text-secondary);font-weight:400"></span></span></div>
                    </div>
                    <div class="fornc-btn-row">
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>

                <div class="dt-sum">
                    <div class="dt-s"><b id="dtTotal">—</b><small>Veículos</small></div>
                    <div class="dt-s mov"><b id="dtMov">—</b><small>Em movimento</small></div>
                    <div class="dt-s par"><b id="dtPar">—</b><small>Parados</small></div>
                    <div class="dt-s off"><b id="dtOff">—</b><small>Sem sinal</small></div>
                </div>

                <div class="dt-wrap">
                    <div id="dtMap"></div>
                    <div class="dt-grid" id="dtGrid"><p style="color:var(--text-secondary)">Carregando…</p></div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="../js/theme.js?v=<?php echo $v; ?>"></script>
    <script src="../js/sidebar.js?v=<?php echo $v; ?>"></script>
    <script>
    (function () {
        var esc=function(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;};
        var ST={movimento:'Em movimento',parado:'Parado',sem_sinal:'Sem sinal'};
        var COR={movimento:'#16a34a',parado:'#f59e0b',sem_sinal:'#94a3b8'};
        var map, markers=[];
        map=L.map('dtMap').setView([-15.78,-47.93],4); L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(map);
        setTimeout(function(){ map.invalidateSize(); },300);

        function carregar(){
            fetch('../api/digital_twin.php',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
                if(!d.success) return; render(d);
            }).catch(function(){});
        }
        function render(d){
            var r=d.resumo; document.getElementById('dtTs').textContent='· '+d.ts;
            document.getElementById('dtTotal').textContent=r.total; document.getElementById('dtMov').textContent=r.movimento;
            document.getElementById('dtPar').textContent=r.parado; document.getElementById('dtOff').textContent=r.sem_sinal;
            document.getElementById('dtGrid').innerHTML=(d.veiculos||[]).map(function(v){
                var extra = v.estado==='sem_sinal' ? (v.ha_min!=null?('sem sinal há '+(v.ha_min>1440?Math.round(v.ha_min/1440)+'d':Math.round(v.ha_min/60)+'h')):'sem posição') : ((v.velocidade||0)+' km/h'+(v.endereco?(' · '+v.endereco):''));
                return '<div class="dt-card '+v.estado+'"><div class="p">'+esc(v.placa)+'</div><div class="st">'+ST[v.estado]+'</div>'+
                    '<div class="m">'+esc(v.motorista||v.modelo||'—')+'</div><div class="m">'+esc(extra)+'</div></div>';
            }).join('')||'<p style="color:var(--text-secondary)">Nenhum veículo.</p>';
            markers.forEach(function(m){ map.removeLayer(m); }); markers=[];
            var bounds=[];
            (d.veiculos||[]).forEach(function(v){ if(v.lat&&v.lng){
                var mk=L.circleMarker([v.lat,v.lng],{radius:8,color:COR[v.estado],fillColor:COR[v.estado],fillOpacity:.9}).addTo(map)
                    .bindPopup('<b>'+esc(v.placa)+'</b><br>'+ST[v.estado]+(v.motorista?('<br>'+esc(v.motorista)):''));
                markers.push(mk); bounds.push([v.lat,v.lng]);
            }});
            if(bounds.length){ try{ map.fitBounds(bounds,{padding:[40,40],maxZoom:14}); }catch(e){} }
        }
        carregar();
        setInterval(carregar, 30000);   // atualiza a cada 30s (tempo real)
    })();
    </script>
    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'digital_twin'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
