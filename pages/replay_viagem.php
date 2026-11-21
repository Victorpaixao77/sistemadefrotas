<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();
if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) { header('Location: ../login.php'); exit; }

$page_title = 'Replay de Viagem';
$v = function_exists('sf_asset_v') ? sf_asset_v() : '1';
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
        .rp-kpis { display:flex; gap:14px; flex-wrap:wrap; margin:6px 0 14px; }
        .rp-k { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:10px; padding:10px 16px; } .rp-k b{display:block;font-size:1.2rem;font-weight:800;} .rp-k small{color:var(--text-secondary);font-size:.74rem;}
        #rpMap { height:460px; border-radius:14px; border:1px solid var(--border-color); }
        .rp-ctrl { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:12px; padding:12px 16px; margin-top:12px; display:flex; gap:14px; align-items:center; flex-wrap:wrap; }
        .rp-ctrl input[type=range]{flex:1;min-width:180px;} .rp-play{width:44px;height:44px;border-radius:50%;border:none;background:var(--accent-primary,#3b82f6);color:#fff;cursor:pointer;font-size:1rem;}
        .rp-info{font-size:.85rem;color:var(--text-secondary);} .rp-info b{color:var(--text-primary);}
        .rp-vel{font-size:.85rem;padding:4px 10px;border-radius:8px;background:var(--bg-primary);}
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
                        <div class="fg" style="min-width:240px"><label for="rpVeic">Veículo</label><select id="rpVeic"><option value="">Selecione…</option></select></div>
                        <div class="fg"><label for="rpDia">Dia</label><select id="rpDia"><option value="">Todos os dias</option></select></div>
                    </div>
                    <div class="fornc-btn-row">
                        <button type="button" class="fornc-btn fornc-btn--primary" id="rpCarregar"><i class="fas fa-play"></i> Carregar trajeto</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>

                <div class="rp-kpis" id="rpKpis" style="display:none">
                    <div class="rp-k"><b id="kPontos">—</b><small>Posições</small></div>
                    <div class="rp-k"><b id="kDist">—</b><small>Distância</small></div>
                    <div class="rp-k"><b id="kVmax">—</b><small>Vel. máx.</small></div>
                    <div class="rp-k"><b id="kPeriodo">—</b><small>Período</small></div>
                </div>

                <div id="rpMap"></div>

                <div class="rp-ctrl" id="rpControls" style="display:none">
                    <button class="rp-play" id="rpPlay"><i class="fas fa-play"></i></button>
                    <input type="range" id="rpSlider" min="0" max="0" value="0">
                    <span class="rp-vel" id="rpVel">0 km/h</span>
                    <span class="rp-info" id="rpHora"></span>
                    <label class="rp-info">Velocidade: <select id="rpSpeed"><option value="200">1x</option><option value="80">2.5x</option><option value="30" selected>6x</option><option value="8">25x</option></select></label>
                </div>
            </div>
        </div>
    </div>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="../js/theme.js?v=<?php echo $v; ?>"></script>
    <script src="../js/sidebar.js?v=<?php echo $v; ?>"></script>
    <script>
    (function () {
        var map, poly, marker, startMarker, pontos=[], idx=0, timer=null;
        function initMap(){ map=L.map('rpMap').setView([-15.78,-47.93],4); L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(map); }
        initMap();

        fetch('../api/gps_replay.php?action=veiculos',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
            var sel=document.getElementById('rpVeic');
            (d.veiculos||[]).forEach(function(v){ var o=document.createElement('option'); o.value=v.veiculo_id; o.textContent=(v.placa||('Veículo '+v.veiculo_id))+' ('+v.pontos+' pontos)'; sel.appendChild(o); });
        });
        document.getElementById('rpVeic').addEventListener('change',function(){
            var vid=this.value, sd=document.getElementById('rpDia'); sd.innerHTML='<option value="">Todos os dias</option>';
            if(!vid) return;
            fetch('../api/gps_replay.php?action=dias&veiculo_id='+vid,{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
                (d.dias||[]).forEach(function(x){ var o=document.createElement('option'); o.value=x.dia; o.textContent=x.dia+' ('+x.pontos+')'; sd.appendChild(o); });
            });
        });
        document.getElementById('rpCarregar').addEventListener('click',carregar);

        function carregar(){
            var vid=document.getElementById('rpVeic').value; if(!vid){ alert('Selecione um veículo.'); return; }
            var dia=document.getElementById('rpDia').value;
            fetch('../api/gps_replay.php?action=trajeto&veiculo_id='+vid+(dia?('&data='+dia):''),{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
                if(!d.success||!d.pontos.length){ alert('Sem posições para este veículo/dia.'); return; }
                pontos=d.pontos; render(d.resumo);
            });
        }
        function render(res){
            document.getElementById('rpKpis').style.display=''; document.getElementById('rpControls').style.display='';
            document.getElementById('kPontos').textContent=res.total; document.getElementById('kDist').textContent=res.distancia_km+' km';
            document.getElementById('kVmax').textContent=res.vel_max+' km/h';
            document.getElementById('kPeriodo').textContent=(res.inicio||'').slice(11,16)+'–'+(res.fim||'').slice(11,16);
            if(poly) map.removeLayer(poly); if(marker) map.removeLayer(marker); if(startMarker) map.removeLayer(startMarker);
            var latlngs=pontos.map(function(p){return [p.lat,p.lng];});
            poly=L.polyline(latlngs,{color:'#3b82f6',weight:4,opacity:.7}).addTo(map);
            startMarker=L.circleMarker(latlngs[0],{radius:6,color:'#16a34a',fillOpacity:1}).addTo(map);
            marker=L.circleMarker(latlngs[0],{radius:9,color:'#dc2626',fillColor:'#dc2626',fillOpacity:1}).addTo(map);
            try{ map.fitBounds(poly.getBounds().pad(0.2)); }catch(e){ map.setView(latlngs[0],14); }
            var sl=document.getElementById('rpSlider'); sl.max=pontos.length-1; sl.value=0; idx=0; pause(); mostrar(0);
            sl.oninput=function(){ pause(); mostrar(+sl.value); };
        }
        function mostrar(i){
            idx=i; var p=pontos[i]; if(!p) return;
            marker.setLatLng([p.lat,p.lng]);
            document.getElementById('rpVel').textContent=(p.vel||0)+' km/h';
            document.getElementById('rpHora').innerHTML='<b>'+(p.hora||'').slice(11,19)+'</b> · '+(p.status||'');
            document.getElementById('rpSlider').value=i;
        }
        function play(){
            document.getElementById('rpPlay').innerHTML='<i class="fas fa-pause"></i>';
            var ms=+document.getElementById('rpSpeed').value;
            timer=setInterval(function(){ if(idx>=pontos.length-1){ pause(); return; } mostrar(idx+1); },ms);
        }
        function pause(){ if(timer){ clearInterval(timer); timer=null; } document.getElementById('rpPlay').innerHTML='<i class="fas fa-play"></i>'; }
        document.getElementById('rpPlay').addEventListener('click',function(){ if(timer) pause(); else { if(idx>=pontos.length-1) idx=0; play(); } });
        setTimeout(function(){ map.invalidateSize(); },300);
    })();
    </script>
    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'replay_viagem'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
