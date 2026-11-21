// Simulador de Rotas (baseado no example.html)
let simulationMap = null;
let simulationRouteManager = null;
/** id do veículo → nome da carreta cadastrada (tipos_carretas) */
window.__simVehicleCarreta = window.__simVehicleCarreta || {};

/** Motor do mapa: 'leaflet' (padrão, sem chave) ou 'google' (opcional, requer chave). */
var simMapEngine = null;
var simLeafletMap = null;
var simLeafletLayers = [];
/** Chave Google em cache (só para modo opcional / backend). */
var simGoogleApiKeyCache = null;

/** Marcadores laranja nos passos da rota que citam pedágio (aproximação — não são as praças oficiais). */
var simulationTollMarkers = [];

function simEsc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

/** Decodifica uma polyline codificada do Google (algoritmo padrão) em [[lat,lng], ...]. */
function simDecodePolyline(encoded) {
    if (!encoded) return [];
    var points = [];
    var index = 0, lat = 0, lng = 0;
    var len = encoded.length;
    while (index < len) {
        var b, shift = 0, result = 0;
        do { b = encoded.charCodeAt(index++) - 63; result |= (b & 0x1f) << shift; shift += 5; } while (b >= 0x20);
        var dlat = (result & 1) ? ~(result >> 1) : (result >> 1);
        lat += dlat;
        shift = 0; result = 0;
        do { b = encoded.charCodeAt(index++) - 63; result |= (b & 0x1f) << shift; shift += 5; } while (b >= 0x20);
        var dlng = (result & 1) ? ~(result >> 1) : (result >> 1);
        lng += dlng;
        points.push([lat / 1e5, lng / 1e5]);
    }
    return points;
}

function simClearLeafletLayers() {
    if (!simLeafletMap) return;
    (simLeafletLayers || []).forEach(function (l) { try { simLeafletMap.removeLayer(l); } catch (e) {} });
    simLeafletLayers = [];
}

function simDestroyGoogleMap() {
    simClearTollMarkers();
    if (simulationRouteManager && typeof simulationRouteManager.clearRoutePolylines === 'function') {
        try { simulationRouteManager.clearRoutePolylines(); } catch (e) {}
    }
    if (simulationRouteManager && typeof simulationRouteManager.clearRouteMarkers === 'function') {
        try { simulationRouteManager.clearRouteMarkers(); } catch (e) {}
    }
    simulationRouteManager = null;
    simulationMap = null;
    if (simMapEngine === 'google') simMapEngine = null;
    var el = document.getElementById('simulationMap');
    if (el) el.innerHTML = '';
}

function simDestroyLeafletMap() {
    if (!simLeafletMap) return;
    try { simLeafletMap.remove(); } catch (e) {}
    simLeafletMap = null;
    simLeafletLayers = [];
    if (simMapEngine === 'leaflet') simMapEngine = null;
}

/** Inicializa o mapa base Leaflet/OpenStreetMap (padrão — não precisa de chave). */
function simInitLeafletMap() {
    if (simLeafletMap) { simMapEngine = 'leaflet'; return true; }
    if (typeof L === 'undefined') {
        window.__SF_DEBUG__ && console.warn('Leaflet não carregado.');
        return false;
    }
    var el = document.getElementById('simulationMap');
    if (!el) return false;
    simLeafletMap = L.map('simulationMap', { zoomControl: true, attributionControl: true }).setView([-15.78, -47.93], 4);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(simLeafletMap);
    simMapEngine = 'leaflet';
    setTimeout(function () { try { simLeafletMap.invalidateSize(); } catch (e) {} }, 150);
    return true;
}

/** @deprecated use simInitLeafletMap */
function simInitLeafletFallback() { return simInitLeafletMap(); }

function simWantGoogleMap() {
    var el = document.getElementById('simUseGoogleMap');
    return !!(el && el.checked);
}

async function simFetchGoogleApiKey() {
    if (simGoogleApiKeyCache !== null) return simGoogleApiKeyCache;
    try {
        var response = await fetch(sfAppUrl('google-maps/api.php?action=get_config'), { credentials: 'include' });
        var data = await response.json();
        simGoogleApiKeyCache = data && data.data && data.data.google_maps_api_key
            ? String(data.data.google_maps_api_key).trim()
            : '';
    } catch (e) {
        simGoogleApiKeyCache = '';
    }
    return simGoogleApiKeyCache;
}

/** Desenha a rota no mapa Leaflet a partir da polyline codificada retornada pela Routes API. */
function simDrawLeafletRoute(encoded, origin, destination) {
    if (!simLeafletMap || typeof L === 'undefined') return;
    simClearLeafletLayers();
    var pts = simDecodePolyline(encoded);
    if (!pts.length) return;
    var line = L.polyline(pts, { color: '#1a73e8', weight: 5, opacity: 0.85 }).addTo(simLeafletMap);
    simLeafletLayers.push(line);
    var a = pts[0], z = pts[pts.length - 1];
    var mA = L.circleMarker(a, { radius: 8, color: '#0f9d58', fillColor: '#0f9d58', fillOpacity: 0.95, weight: 2 })
        .addTo(simLeafletMap).bindPopup('<b>Origem</b><br>' + simEsc(origin));
    var mZ = L.circleMarker(z, { radius: 8, color: '#db4437', fillColor: '#db4437', fillOpacity: 0.95, weight: 2 })
        .addTo(simLeafletMap).bindPopup('<b>Destino</b><br>' + simEsc(destination));
    simLeafletLayers.push(mA, mZ);
    try { simLeafletMap.fitBounds(line.getBounds(), { padding: [24, 24] }); } catch (e) {}
    setTimeout(function () { try { simLeafletMap.invalidateSize(); } catch (e) {} }, 120);
}

/** routeInfo sintético (mesmo formato do Google) a partir da resposta do backend, p/ modo Leaflet. */
function simRouteInfoFromBackend(calc, origin, destination) {
    var km = Number(calc.distance_km || 0);
    return {
        distance: { text: km.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' km', value: Number(calc.distance_meters || km * 1000) },
        duration: { text: calc.duration_text || '', value: Number(calc.duration_seconds || 0) },
        start_address: origin,
        end_address: destination,
        steps: []
    };
}

function simClearTollMarkers() {
    simulationTollMarkers.forEach(function (m) { m.setMap(null); });
    simulationTollMarkers = [];
}

function simStepMentionsToll(step) {
    var raw = (step.instructions != null ? step.instructions : '') + ' ' +
        (step.html_instructions != null ? step.html_instructions : '');
    var s = String(raw).replace(/<[^>]+>/gi, ' ').toLowerCase();
    if (s.indexOf('sem pedágio') >= 0 || s.indexOf('sem pedagio') >= 0) return false;
    if (s.indexOf('avoid toll') >= 0) return false;
    return /ped[aá]gio|pra[cç]a\s+de\s+ped|toll\s+road|\btoll\b/.test(s);
}

function simPlaceTollStepMarkers(routeResult) {
    simClearTollMarkers();
    if (!simulationMap || !routeResult || !routeResult.routes || !routeResult.routes[0]) return;
    var leg = routeResult.routes[0].legs && routeResult.routes[0].legs[0];
    if (!leg || !leg.steps || !window.google || !google.maps) return;
    var steps = leg.steps;
    var n = 0;
    for (var i = 0; i < steps.length; i++) {
        if (!simStepMentionsToll(steps[i]) || !steps[i].start_location) continue;
        n++;
        var plain = String(steps[i].instructions || '').replace(/<[^>]+>/g, ' ').trim();
        if (plain.length > 100) plain = plain.substring(0, 97) + '…';
        var marker = new google.maps.Marker({
            position: steps[i].start_location,
            map: simulationMap,
            title: 'Pedágio (aprox.): ' + plain,
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 9,
                fillColor: '#f97316',
                fillOpacity: 0.92,
                strokeColor: '#9a3412',
                strokeWeight: 2
            },
            zIndex: google.maps.Marker.MAX_ZINDEX + 1
        });
        simulationTollMarkers.push(marker);
    }
}

/**
 * Desenha a rota no mapa (polilinha + origem/destino + marcadores aproximados de pedágio nos passos).
 * O calculateRoute do RouteManager só guarda o resultado; não renderiza sozinho.
 */
function simApplyRouteToSimulationMap(routeResult) {
    if (!routeResult || !routeResult.routes || !routeResult.routes.length) return;
    if (!simulationRouteManager || !simulationMap) return;
    simulationRouteManager.clearRoutePolylines();
    simulationRouteManager.clearRouteMarkers();
    simClearTollMarkers();
    if (window.google && window.google.maps && window.google.maps.event) {
        google.maps.event.trigger(simulationMap, 'resize');
    }
    simulationRouteManager.drawRoutePolyline(routeResult, '#1a73e8');
    simulationRouteManager.addRouteMarkers(routeResult);
    simPlaceTollStepMarkers(routeResult);
    var route = routeResult.routes[0];
    var b = new google.maps.LatLngBounds();
    if (route.overview_path && route.overview_path.length) {
        route.overview_path.forEach(function (pt) { b.extend(pt); });
    } else if (route.bounds) {
        simulationMap.fitBounds(route.bounds);
        return;
    } else if (route.legs && route.legs[0]) {
        var L = route.legs[0];
        if (L.start_location) b.extend(L.start_location);
        if (L.end_location) b.extend(L.end_location);
    }
    simulationTollMarkers.forEach(function (m) {
        var p = m.getPosition();
        if (p) b.extend(p);
    });
    var empty = typeof b.isEmpty === 'function' ? b.isEmpty() : false;
    if (!empty) {
        simulationMap.fitBounds(b);
    }
}

function simEstimateTollStepCount(steps) {
    if (!steps || !steps.length) return 0;
    var n = 0;
    for (var i = 0; i < steps.length; i++) {
        if (simStepMentionsToll(steps[i])) n++;
    }
    return n;
}

function simTipoCombustivelToEmission(nome) {
    const n = String(nome || '').toLowerCase();
    if (n.indexOf('elétr') >= 0 || n.indexOf('eletr') >= 0) return 'ELECTRIC';
    if (n.indexOf('híbr') >= 0 || n.indexOf('hibr') >= 0) return 'HYBRID';
    if (n.indexOf('gasolina') >= 0 || n.indexOf('etanol') >= 0 || n.indexOf('flex') >= 0 || n.indexOf('gnv') >= 0) {
        return 'GASOLINE';
    }
    return 'DIESEL';
}

function simKmPerLiterFromVehicleRow(v) {
    const cr = v && v.id_carreta != null && parseInt(String(v.id_carreta), 10) > 0;
    return cr ? 4.0 : 5.0;
}

function simVehicleTypeFromRow(v) {
    const cr = v && v.id_carreta != null && parseInt(String(v.id_carreta), 10) > 0;
    return cr ? 'CARRETA' : 'TRUCK';
}

function simBuildAddressFromSelects(cidadeSelectId, estadoSelectId) {
    const cSel = document.getElementById(cidadeSelectId);
    const eSel = document.getElementById(estadoSelectId);
    if (!cSel || !eSel) return '';
    const cid = cSel.value;
    const uf = eSel.value;
    if (!cid || !uf) return '';
    const opt = cSel.options[cSel.selectedIndex];
    const cityName = opt ? String(opt.textContent || '').trim() : '';
    if (!cityName) return '';
    return cityName + ', ' + uf + ', Brasil';
}

function simLoadCidades(uf, targetSelectId) {
    if (typeof sfApiUrl !== 'function') {
        return Promise.reject(new Error('sfApiUrl não disponível'));
    }
    return fetch(sfApiUrl('route_actions.php?action=get_cidades&uf=' + encodeURIComponent(uf)))
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var cidadeSelect = document.getElementById(targetSelectId);
            if (!data.success || !cidadeSelect) {
                throw new Error((data && data.error) ? data.error : 'Erro ao carregar cidades');
            }
            cidadeSelect.disabled = false;
            var options = (data.data || []).map(function (cidade) {
                return '<option value="' + cidade.id + '">' + cidade.nome + '</option>';
            }).join('');
            cidadeSelect.innerHTML = '<option value="">Selecione a cidade</option>' + options;
            return data.data;
        })
        .catch(function (err) {
            var cidadeSelect = document.getElementById(targetSelectId);
            if (cidadeSelect) {
                cidadeSelect.innerHTML = '<option value="">Erro ao carregar cidades</option>';
                cidadeSelect.disabled = true;
            }
            throw err;
        });
}

function simSetupEstadoCidadeOnce() {
    var modal = document.getElementById('routeSimulationModal');
    if (!modal || modal.dataset.sfSimGeoBound) return;
    modal.dataset.sfSimGeoBound = '1';

    var eo = document.getElementById('sim_estado_origem');
    var co = document.getElementById('sim_cidade_origem_id');
    var ed = document.getElementById('sim_estado_destino');
    var cd = document.getElementById('sim_cidade_destino_id');

    if (eo && co) {
        eo.addEventListener('change', function () {
            var uf = eo.value;
            if (uf) {
                simLoadCidades(uf, 'sim_cidade_origem_id');
                co.disabled = false;
            } else {
                co.innerHTML = '<option value="">Selecione primeiro o estado</option>';
                co.disabled = true;
            }
        });
    }
    if (ed && cd) {
        ed.addEventListener('change', function () {
            var uf = ed.value;
            if (uf) {
                simLoadCidades(uf, 'sim_cidade_destino_id');
                cd.disabled = false;
            } else {
                cd.innerHTML = '<option value="">Selecione primeiro o estado</option>';
                cd.disabled = true;
            }
        });
    }
}

function simFillEstados(estados) {
    var eo = document.getElementById('sim_estado_origem');
    var ed = document.getElementById('sim_estado_destino');
    var co = document.getElementById('sim_cidade_origem_id');
    var cd = document.getElementById('sim_cidade_destino_id');
    var options = (estados || []).map(function (estado) {
        return '<option value="' + estado.uf + '">' + estado.nome + '</option>';
    }).join('');
    if (eo) eo.innerHTML = '<option value="">Selecione o estado</option>' + options;
    if (ed) ed.innerHTML = '<option value="">Selecione o estado</option>' + options;
    if (co) {
        co.innerHTML = '<option value="">Selecione primeiro o estado</option>';
        co.disabled = true;
    }
    if (cd) {
        cd.innerHTML = '<option value="">Selecione primeiro o estado</option>';
        cd.disabled = true;
    }
}

async function simLoadEstadosIfNeeded() {
    var eo = document.getElementById('sim_estado_origem');
    if (!eo || eo.dataset.sfEstadosLoaded === '1') return;
    if (typeof sfApiUrl !== 'function') {
        showSimulationError('Configuração da página incompleta (sfApiUrl).');
        return;
    }
    var res = await fetch(sfApiUrl('route_actions.php?action=get_estados'), { credentials: 'include' });
    var data = await res.json();
    if (!data.success || !Array.isArray(data.data)) {
        throw new Error((data && data.message) ? data.message : 'Erro ao carregar estados');
    }
    simFillEstados(data.data);
    eo.dataset.sfEstadosLoaded = '1';
}

function simUpdateVehicleCarretaLine() {
    var sel = document.getElementById('simVehicle');
    var el = document.getElementById('simVehicleCarretaLine');
    if (!el) return;
    if (!sel || !sel.value) {
        el.textContent = '';
        return;
    }
    var nome = (window.__simVehicleCarreta && window.__simVehicleCarreta[sel.value])
        ? String(window.__simVehicleCarreta[sel.value]).trim()
        : '';
    if (nome) {
        el.textContent = 'Carreta no cadastro: ' + nome + ' (conjunto usado na estimativa de consumo).';
    } else {
        el.textContent = 'Sem carreta vinculada no cadastro — consumo estimado como cavalo só.';
    }
}

/** Capacidade de tanque padrão por tipo de veículo (L), usada só como sugestão. */
function simDefaultTankByType(vehicleType) {
    if (vehicleType === 'CARRETA') return 600;
    if (vehicleType === 'TRUCK') return 400;
    return 200;
}

/**
 * Busca consumo médio real e último preço de combustível do veículo e preenche
 * os campos (sem sobrescrever valores que o usuário já digitou manualmente).
 */
function simFetchVehicleStats(vehicleId) {
    if (!vehicleId || typeof sfApiUrl !== 'function') return;
    var consEl = document.getElementById('simConsumption');
    var consMeta = document.getElementById('simConsumptionMeta');
    var priceEl = document.getElementById('simFuelPrice');
    var priceMeta = document.getElementById('simFuelPriceMeta');
    fetch(sfApiUrl('route_actions.php?action=get_vehicle_sim_stats&veiculo_id=' + encodeURIComponent(vehicleId)), { credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res || !res.success || !res.data) return;
            var d = res.data;
            if (consEl && !consEl.dataset.userEdited) {
                if (d.consumo_medio_km_l) {
                    consEl.value = d.consumo_medio_km_l;
                    if (consMeta) consMeta.textContent = 'Média real do veículo (' + d.consumo_amostras + ' abastec.).';
                } else if (consMeta) {
                    consMeta.textContent = 'Sem histórico suficiente — usando estimativa por tipo.';
                }
            }
            if (priceEl && !priceEl.dataset.userEdited && d.ultimo_valor_litro) {
                priceEl.value = d.ultimo_valor_litro;
                if (priceMeta) {
                    priceMeta.textContent = (d.valor_litro_origem === 'empresa_media')
                        ? 'Média da empresa (90 dias).'
                        : 'Último preço pago por este veículo.';
                }
            }
        })
        .catch(function () {});
}

function simApplyVehicleMetaFromSelect() {
    var sel = document.getElementById('simVehicle');
    var ft = document.getElementById('simFuelType');
    if (!sel || !ft || !sel.value) {
        simUpdateVehicleCarretaLine();
        return;
    }
    var opt = sel.options[sel.selectedIndex];
    if (!opt) return;
    var em = opt.getAttribute('data-emission');
    if (em) ft.value = em;
    var vt = opt.getAttribute('data-vehicle-type') || 'TRUCK';
    var kmL = opt.getAttribute('data-km-l');
    var tanqueCad = parseFloat(opt.getAttribute('data-tanque-cad'));
    var consumoCad = parseFloat(opt.getAttribute('data-consumo-cad'));
    var custoKmCad = parseFloat(opt.getAttribute('data-custo-km-cad'));

    // Consumo: cadastro do veículo, senão estimativa por tipo (o histórico refina depois).
    var consEl = document.getElementById('simConsumption');
    var consMeta = document.getElementById('simConsumptionMeta');
    if (consEl && !consEl.dataset.userEdited) {
        if (Number.isFinite(consumoCad) && consumoCad > 0) {
            consEl.value = consumoCad;
            if (consMeta) consMeta.textContent = 'Consumo do cadastro do veículo.';
        } else if (kmL) {
            consEl.value = kmL;
            if (consMeta) consMeta.textContent = '';
        }
    }
    // Tanque: capacidade do cadastro, senão padrão por tipo.
    var tankEl = document.getElementById('simTankCapacity');
    if (tankEl && !tankEl.dataset.userEdited) {
        tankEl.value = (Number.isFinite(tanqueCad) && tanqueCad > 0) ? tanqueCad : simDefaultTankByType(vt);
    }
    // Custo operacional R$/km: do cadastro (não sobrescreve edição manual).
    var opCostEl = document.getElementById('simOpCostPerKm');
    if (opCostEl && !opCostEl.dataset.userEdited && Number.isFinite(custoKmCad) && custoKmCad > 0) {
        opCostEl.value = custoKmCad;
    }

    simUpdateVehicleCarretaLine();
    simFetchVehicleStats(sel.value);
    simRecomputeProfit();
}

async function simLoadVehicles() {
    var select = document.getElementById('simVehicle');
    if (!select || typeof sfApiUrl !== 'function') return;
    var res = await fetch(sfApiUrl('route_actions.php?action=get_veiculos'), { credentials: 'include' });
    var data = await res.json();
    if (!data.success || !Array.isArray(data.data)) {
        select.innerHTML = '<option value="">Erro ao carregar veículos</option>';
        return;
    }
    window.__simVehicleCarreta = {};
    var opts = data.data.map(function (v) {
        window.__simVehicleCarreta[String(v.id)] = (v.carreta_nome != null ? String(v.carreta_nome) : '').trim();
        var kmL = simKmPerLiterFromVehicleRow(v);
        var vt = simVehicleTypeFromRow(v);
        var em = simTipoCombustivelToEmission(v.tipo_combustivel_nome);
        var eixos = Math.max(2, parseInt(String(v.eixos_conjunto != null ? v.eixos_conjunto : 6), 10) || 6);
        var label = (v.placa || '') + (v.modelo ? ' (' + v.modelo + ')' : '');
        // Valores do cadastro do veículo (vazio = "" para o front cair no padrão).
        var tanqueCad = (v.capacidade_tanque != null && Number(v.capacidade_tanque) > 0) ? Number(v.capacidade_tanque) : '';
        var consumoCad = (v.consumo_medio != null && Number(v.consumo_medio) > 0) ? Number(v.consumo_medio) : '';
        var custoKmCad = (v.custo_operacional_km != null && Number(v.custo_operacional_km) > 0) ? Number(v.custo_operacional_km) : '';
        var cargaCad = (v.capacidade_carga != null && Number(v.capacidade_carga) > 0) ? Number(v.capacidade_carga) : '';
        return '<option value="' + v.id + '" data-vehicle-type="' + vt + '" data-km-l="' + kmL + '" data-emission="' + em + '" data-eixos="' + eixos +
            '" data-tanque-cad="' + tanqueCad + '" data-consumo-cad="' + consumoCad + '" data-custo-km-cad="' + custoKmCad + '" data-capacidade="' + cargaCad + '">' + label + '</option>';
    }).join('');
    select.innerHTML = '<option value="">Selecione um veículo</option>' + opts;
    simUpdateVehicleCarretaLine();
}

/** Carrega motoristas no select da simulação (com a comissão embutida). */
async function simLoadDrivers() {
    var select = document.getElementById('simDriver');
    if (!select || typeof sfApiUrl !== 'function') return;
    try {
        var res = await fetch(sfApiUrl('route_actions.php?action=get_motoristas'), { credentials: 'include' });
        var data = await res.json();
        if (!data.success || !Array.isArray(data.data)) return;
        var opts = data.data.map(function (m) {
            var pct = (m.porcentagem_comissao != null) ? Number(m.porcentagem_comissao) : '';
            return '<option value="' + m.id + '" data-comissao="' + pct + '">' + (m.nome || '') + '</option>';
        }).join('');
        select.innerHTML = '<option value="">Selecione (preenche a comissão)</option>' + opts;
    } catch (e) { /* silencioso */ }
}

/** Ao escolher o motorista, preenche a comissão %. */
function simApplyDriverCommission() {
    var sel = document.getElementById('simDriver');
    var pctEl = document.getElementById('simCommissionPct');
    if (!sel || !pctEl || !sel.value) return;
    var opt = sel.options[sel.selectedIndex];
    var pct = opt ? parseFloat(opt.getAttribute('data-comissao')) : NaN;
    if (Number.isFinite(pct) && pct > 0) {
        pctEl.value = pct;
        simRecomputeProfit();
    }
}

/** Avisa se o peso da carga ultrapassa a capacidade do veículo. */
function simCheckCargoCapacity() {
    var warn = document.getElementById('simCargoWarn');
    if (!warn) return;
    var pesoEl = document.getElementById('simCargoWeight');
    var vehSel = document.getElementById('simVehicle');
    var peso = parseFloat(pesoEl && pesoEl.value);
    var opt = vehSel && vehSel.value ? vehSel.options[vehSel.selectedIndex] : null;
    var cap = opt ? parseFloat(opt.getAttribute('data-capacidade')) : NaN;
    if (!Number.isFinite(peso) || peso <= 0 || !Number.isFinite(cap) || cap <= 0) {
        warn.textContent = '';
        warn.style.color = '';
        return;
    }
    if (peso > cap) {
        warn.textContent = '⚠️ Carga ' + peso.toLocaleString('pt-BR') + ' kg acima da capacidade do veículo (' + cap.toLocaleString('pt-BR') + ' kg).';
        warn.style.color = '#c62828';
    } else {
        var uso = (peso / cap) * 100;
        warn.textContent = 'Ocupação: ' + uso.toFixed(0) + '% da capacidade (' + cap.toLocaleString('pt-BR') + ' kg).';
        warn.style.color = '#2e7d32';
    }
}

// Função para inicializar o simulador
function initRouteSimulator() {
    window.__SF_DEBUG__ && console.log('Inicializando simulador de rotas...');
    
    if (typeof GoogleMapsManager === 'undefined') {
        console.error('GoogleMapsManager não está disponível');
        showSimulationError('GoogleMapsManager não está disponível');
        return;
    }
    
    if (typeof RouteManager === 'undefined') {
        console.error('RouteManager não está disponível');
        showSimulationError('RouteManager não está disponível');
        return;
    }
    
    var simulateBtn = document.getElementById('simulateRouteBtnModal');
    if (simulateBtn) {
        if (!simulateBtn.dataset.sfSimBound) {
            simulateBtn.dataset.sfSimBound = '1';
            simulateBtn.addEventListener('click', simulateRoute);
        }
    }

    simSetupEstadoCidadeOnce();
    var veh = document.getElementById('simVehicle');
    if (veh && !veh.dataset.sfSimVehBound) {
        veh.dataset.sfSimVehBound = '1';
        veh.addEventListener('change', simApplyVehicleMetaFromSelect);
    }

    // Marca campos como "editados pelo usuário" para o auto-preenchimento não sobrescrever.
    ['simConsumption', 'simFuelPrice', 'simTankCapacity'].forEach(function (id) {
        var inp = document.getElementById(id);
        if (inp && !inp.dataset.sfSimEditBound) {
            inp.dataset.sfSimEditBound = '1';
            inp.addEventListener('input', function () { inp.dataset.userEdited = '1'; });
        }
    });

    // Recalcula lucro ao mudar frete/comissão/custo sem precisar refazer a rota.
    ['simFreteValue', 'simCommissionPct', 'simOpCostPerKm'].forEach(function (id) {
        var inp = document.getElementById(id);
        if (inp && !inp.dataset.sfSimEditBound) {
            inp.dataset.sfSimEditBound = '1';
            inp.addEventListener('input', function () {
                if (id === 'simOpCostPerKm') inp.dataset.userEdited = '1';
                simRecomputeProfit();
            });
        }
    });

    // Recalcula o frete sugerido / lucro ao mudar a margem-alvo, ARLA ou pedágio real.
    ['simTargetMargin', 'simArlaPrice', 'simTollManual'].forEach(function (id) {
        var inp = document.getElementById(id);
        if (inp && !inp.dataset.sfSimEditBound) {
            inp.dataset.sfSimEditBound = '1';
            inp.addEventListener('input', simRecomputeProfit);
        }
    });

    var drvSel = document.getElementById('simDriver');
    if (drvSel && !drvSel.dataset.sfSimBound) {
        drvSel.dataset.sfSimBound = '1';
        drvSel.addEventListener('change', simApplyDriverCommission);
    }
    var cargoTypeSel = document.getElementById('simCargoType');
    if (cargoTypeSel && !cargoTypeSel.dataset.sfSimBound) {
        cargoTypeSel.dataset.sfSimBound = '1';
        cargoTypeSel.addEventListener('change', simFetchAnttPiso);
    }
    var altoSel = document.getElementById('simAltoDesempenho');
    if (altoSel && !altoSel.dataset.sfSimBound) {
        altoSel.dataset.sfSimBound = '1';
        altoSel.addEventListener('change', simFetchAnttPiso);
    }
    var cargoInp = document.getElementById('simCargoWeight');
    if (cargoInp && !cargoInp.dataset.sfSimBound) {
        cargoInp.dataset.sfSimBound = '1';
        cargoInp.addEventListener('input', simCheckCargoCapacity);
    }
    // Reverifica a carga quando troca o veículo.
    if (veh && !veh.dataset.sfSimCargoBound) {
        veh.dataset.sfSimCargoBound = '1';
        veh.addEventListener('change', simCheckCargoCapacity);
    }

    var createBtn = document.getElementById('simCreateRouteBtn');
    if (createBtn && !createBtn.dataset.sfSimBound) {
        createBtn.dataset.sfSimBound = '1';
        createBtn.addEventListener('click', simCreateRouteFromSimulation);
    }
    var saveBtn = document.getElementById('simSaveBtn');
    if (saveBtn && !saveBtn.dataset.sfSimBound) {
        saveBtn.dataset.sfSimBound = '1';
        saveBtn.addEventListener('click', simSaveCurrent);
    }
    var clearSavedBtn = document.getElementById('simClearSavedBtn');
    if (clearSavedBtn && !clearSavedBtn.dataset.sfSimBound) {
        clearSavedBtn.dataset.sfSimBound = '1';
        clearSavedBtn.addEventListener('click', function () {
            try { localStorage.removeItem('sfSimSaved'); } catch (e) {}
            simRenderSaved();
        });
    }
    simRenderSaved();

    simBindModalDismiss();

    // Mostra o mapa já na abertura da página (igual ao Mapa da Frota), sem esperar simular.
    simPrewarmMap();

    simLoadEstadosIfNeeded()
        .then(function () { return simLoadVehicles(); })
        .then(function () { return simLoadDrivers(); })
        .catch(function (e) {
            console.error(e);
            showSimulationError('Não foi possível carregar estados/veículos: ' + (e && e.message ? e.message : String(e)));
        });

    window.__SF_DEBUG__ && console.log('Simulador inicializado');
}

// Carrega o mapa Leaflet na abertura (padrão, sem chave Google).
async function simPrewarmMap() {
    var mapEl = document.getElementById('simulationMap');
    if (!mapEl) return simMapEngine;
    if (simMapEngine === 'leaflet' && simLeafletMap) return 'leaflet';
    if (window.__sfSimMapPrewarmPromise) return window.__sfSimMapPrewarmPromise;

    window.__sfSimMapPrewarmPromise = (async function () {
        if (simInitLeafletMap()) {
            return 'leaflet';
        }
        showSimulationError('Não foi possível carregar o mapa.');
        return null;
    })();

    try {
        return await window.__sfSimMapPrewarmPromise;
    } finally {
        window.__sfSimMapPrewarmPromise = null;
    }
}

// Fallback de consumo (tipo lógico) se o backend não receber consumption_km_l
function getVehicleConsumption(vehicleType) {
    var consumptions = { TRUCK: 5.0, CARRETA: 4.0, CAR: 10.0, SUV: 8.5 };
    return consumptions[vehicleType] || 7.0;
}

// Simular rota (baseado no example.html)
async function simulateRoute() {
    window.__SF_DEBUG__ && console.log('Função simulateRoute chamada');

    var origin = simBuildAddressFromSelects('sim_cidade_origem_id', 'sim_estado_origem');
    var destination = simBuildAddressFromSelects('sim_cidade_destino_id', 'sim_estado_destino');
    var fuelPrice = parseFloat(document.getElementById('simFuelPrice').value);
    var vehSel = document.getElementById('simVehicle');
    var fuelType = document.getElementById('simFuelType') ? document.getElementById('simFuelType').value : 'DIESEL';

    var vehicleId = vehSel && vehSel.value ? vehSel.value : '';
    var opt = vehSel && vehSel.value ? vehSel.options[vehSel.selectedIndex] : null;
    // Consumo: o campo (preenchido pelo histórico/edição manual) tem prioridade sobre o data-km-l.
    var consField = parseFloat((document.getElementById('simConsumption') || {}).value);
    var consumptionKmL = (Number.isFinite(consField) && consField > 0)
        ? consField
        : (opt && opt.getAttribute('data-km-l') ? parseFloat(opt.getAttribute('data-km-l')) : 0);
    var vehicleType = (opt && opt.getAttribute('data-vehicle-type')) ? opt.getAttribute('data-vehicle-type') : 'TRUCK';
    var tripTypeEl = document.querySelector('input[name="simTripType"]:checked');
    var isRoundTrip = tripTypeEl && tripTypeEl.value === 'round_trip';

    window.__SF_DEBUG__ && console.log('Valores:', { origin, destination, fuelPrice, vehicleId, vehicleType, consumptionKmL });

    if (!origin || !destination) {
        showSimulationError('Selecione estado e cidade de origem e de destino.');
        return;
    }
    if (!vehicleId) {
        showSimulationError('Selecione um veículo cadastrado.');
        return;
    }

    try {
        showSimulationInfo('Preparando o mapa...');
        await simPrewarmMap();

        var apiKey = await simFetchGoogleApiKey();
        var useGoogleMap = simWantGoogleMap() && !!apiKey;
        var engine = 'leaflet';

        if (useGoogleMap) {
            try {
                simDestroyLeafletMap();
                simDestroyGoogleMap();
                await initSimulationMap(apiKey);
                if (simulationMap) {
                    simMapEngine = 'google';
                    engine = 'google';
                }
            } catch (e) {
                window.__SF_DEBUG__ && console.warn('Google Maps indisponível, mantendo OpenStreetMap:', e);
                simDestroyGoogleMap();
                if (!simLeafletMap) simInitLeafletMap();
                engine = 'leaflet';
                showSimulationInfo('Não foi possível usar o mapa Google; exibindo OpenStreetMap.');
            }
        } else {
            if (simMapEngine === 'google' || !simLeafletMap) {
                simDestroyGoogleMap();
                simInitLeafletMap();
            }
            engine = 'leaflet';
        }

        // Rota desenhada pelo Google (opcional) — passos p/ marcadores de pedágio no mapa.
        var routeInfo = null;
        var googleOk = false;
        if (engine === 'google' && simulationRouteManager) {
            showSimulationInfo('Calculando rota no Google Maps...');
            var routePromise = simulationRouteManager.calculateRoute(origin, destination);
            var timeoutPromise = new Promise(function (_, reject) {
                setTimeout(function () { reject(new Error('Timeout: Cálculo da rota demorou muito')); }, 30000);
            });
            try {
                var result = await Promise.race([routePromise, timeoutPromise]);
                if (result) {
                    simApplyRouteToSimulationMap(result);
                    routeInfo = simulationRouteManager.getRouteInfo();
                    googleOk = !!routeInfo;
                }
            } catch (e) {
                window.__SF_DEBUG__ && console.warn('Falha ao traçar rota no Google:', e);
            }
        }

        if (!apiKey) {
            showSimulationError(
                'Para calcular distância e pedágios automaticamente, configure a chave Google (Routes API) em Configurações. ' +
                'O mapa OpenStreetMap funciona sem chave; use a simulação rápida (what-if) para estimar custos manualmente.'
            );
            return;
        }

        showSimulationInfo('Consultando distância e pedágios...');
        var fd = new FormData();
        fd.append('action', 'compute_routes_tolls');
        fd.append('origin', origin);
        fd.append('destination', destination);
        fd.append('fuel_price', Number.isFinite(fuelPrice) ? String(fuelPrice) : '0');
        fd.append('vehicle_type', vehicleType);
        fd.append('emission_type', fuelType || 'DIESEL');
        if (Number.isFinite(consumptionKmL) && consumptionKmL > 0) {
            fd.append('consumption_km_l', String(consumptionKmL));
        }
        var eixosPed = opt && opt.getAttribute('data-eixos') ? parseFloat(opt.getAttribute('data-eixos')) : 6;
        if (Number.isFinite(eixosPed) && eixosPed >= 2) {
            fd.append('eixos_conjunto', String(eixosPed));
        }
        var tollPassEl = document.getElementById('simTollPass');
        var tollPassVal = tollPassEl && tollPassEl.value ? String(tollPassEl.value).trim() : '';
        if (tollPassVal) {
            fd.append('toll_pass', tollPassVal);
        }
        var backendResp = await fetch(sfAppUrl('google-maps/api.php'), {
            method: 'POST',
            body: fd,
            credentials: 'include'
        });
        var backendData = await backendResp.json();
        if (!backendResp.ok || !backendData.success || !backendData.data) {
            throw new Error((backendData && backendData.message) ? backendData.message : 'Falha ao consultar Routes API');
        }
        var calc = backendData.data;

        // Padrão: desenha no OpenStreetMap com a polyline do backend.
        if (!googleOk) {
            routeInfo = simRouteInfoFromBackend(calc, origin, destination);
            if (!simLeafletMap) simInitLeafletMap();
            simDrawLeafletRoute(calc.encoded_polyline || '', origin, destination);
        }
        if (!routeInfo) {
            showSimulationError('Informações da rota não disponíveis');
            return;
        }

        var fuelConsumption = Number(calc.fuel_consumption_km_l || getVehicleConsumption(vehicleType));
        // Trecho único calculado pela API; "ida e volta" duplica os valores físicos.
        var legMult = isRoundTrip ? 2 : 1;
        var distanceOneWay = Number(calc.distance_km || 0);
        var distance = distanceOneWay * legMult;
        // Eixos do conjunto e tipo de contratação (tem carreta = composição).
        var eixosConj = (opt && opt.getAttribute('data-eixos')) ? parseInt(opt.getAttribute('data-eixos'), 10) : 6;
        var temCarreta = !!(window.__simVehicleCarreta && window.__simVehicleCarreta[vehicleId]);
        var contratacao = temCarreta ? 'COMPOSICAO' : 'TRACAO';
        var fuelLiters = Number(calc.fuel_liters || 0) * legMult;
        var fuelCost = Number(calc.fuel_cost || 0) * legMult;
        var tollCost = Number(calc.tolls_total || 0) * legMult;
        var totalCost = (fuelCost + tollCost);
        var tollStepCount = googleOk ? simEstimateTollStepCount(routeInfo.steps || []) : 0;

        var durSec = (routeInfo.duration && routeInfo.duration.value) ? Number(routeInfo.duration.value) : 0;
        if (isRoundTrip) durSec *= 2;
        var vehLabel = (opt && opt.textContent) ? opt.textContent.trim() : '';

        // Guarda o contexto da simulação para o cálculo de lucro e para criar a rota.
        window.__simLastResult = {
            origin: origin,
            destination: destination,
            vehicleId: vehicleId,
            vehicleLabel: vehLabel,
            isRoundTrip: isRoundTrip,
            distance: distance,
            fuelConsumption: fuelConsumption,
            fuelLiters: fuelLiters,
            fuelCost: fuelCost,
            tollCost: tollCost,
            operationalCost: 0,
            arlaCost: 0,
            commission: 0,
            durationSec: durSec,
            durationText: routeInfo.duration ? routeInfo.duration.text : '',
            distanceOneWay: distanceOneWay,
            eixos: eixosConj,
            contratacao: contratacao,
            anttPiso: null
        };

        displaySimulationResults(routeInfo, distance, fuelLiters, fuelCost, tollCost, totalCost, fuelConsumption, calc, tollStepCount, isRoundTrip);
        simRecomputeProfit();
        simFetchAnttPiso();
        var okMsg = isRoundTrip ? 'Rota (ida e volta) calculada com sucesso!' : 'Rota calculada com sucesso!';
        if (!googleOk) okMsg += ' (mapa OpenStreetMap)';
        showSimulationInfo(okMsg);

    } catch (error) {
        console.error('Erro na simulação:', error);
        showSimulationError('Erro ao simular rota: ' + error.message);
    }
}

// Inicializar mapa de simulação (baseado no example.html)
async function initSimulationMap(apiKey) {
    if (simulationMap) return;

    window.__SF_DEBUG__ && console.log('Inicializando mapa de simulação...');

    // Verificar se Google Maps API está carregada
    if (!window.google || !window.google.maps) {
        window.__SF_DEBUG__ && console.log('Google Maps API não carregada, carregando...');
        await loadGoogleMapsAPI(apiKey);
    }

    // Aguardar um pouco mais para garantir que a API esteja totalmente carregada
    await new Promise(resolve => setTimeout(resolve, 1000));

    // Inicializar GoogleMapsManager
    if (!window.googleMapsManager) {
        window.googleMapsManager = new GoogleMapsManager();
    }

    // Inicializar com a chave da API
    window.__SF_DEBUG__ && console.log('Inicializando GoogleMapsManager...');
    await window.googleMapsManager.init(apiKey);

    // Verificar se o elemento do mapa existe
    const mapElement = document.getElementById('simulationMap');
    if (!mapElement) {
        throw new Error('Elemento simulationMap não encontrado');
    }
    
    window.__SF_DEBUG__ && console.log('Elemento do mapa encontrado, criando mapa...');
    
    // Criar mapa
    simulationMap = await window.googleMapsManager.createMap('simulationMap', {
        zoom: 4,
        center: { lat: -15.78, lng: -47.93 }
    });

    // Inicializar RouteManager
    window.__SF_DEBUG__ && console.log('Inicializando RouteManager...');
    
    // Verificar se RouteManager está disponível
    if (typeof RouteManager === 'undefined') {
        window.__SF_DEBUG__ && console.log('RouteManager não encontrado, aguardando carregamento...');
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        if (typeof RouteManager === 'undefined') {
            throw new Error('RouteManager não está disponível. Verifique se route-manager.js está carregado.');
        }
    }
    
    simulationRouteManager = new RouteManager();
    window.__SF_DEBUG__ && console.log('RouteManager criado:', simulationRouteManager);
    
    simulationRouteManager.init(simulationMap);
    window.__SF_DEBUG__ && console.log('RouteManager inicializado com mapa');
    
    window.__SF_DEBUG__ && console.log('Mapa de simulação inicializado com sucesso');
}

// Função para carregar Google Maps API
function loadGoogleMapsAPI(apiKey) {
    return new Promise((resolve, reject) => {
        // Verificar se já está carregando
        if (window.googleMapsLoading) {
            window.googleMapsLoading.then(resolve).catch(reject);
            return;
        }

        // Verificar se já está carregada
        if (window.google && window.google.maps && window.google.maps.Map) {
            window.__SF_DEBUG__ && console.log('Google Maps API já está carregada');
            resolve();
            return;
        }

        window.__SF_DEBUG__ && console.log('Carregando Google Maps API...');
        window.googleMapsLoading = new Promise((resolveLoading, rejectLoading) => {
            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places,geometry&loading=async`;
            script.async = true;
            script.defer = true;
            
            script.onload = () => {
                window.__SF_DEBUG__ && console.log('Script Google Maps carregado, aguardando API...');
                
                // Aguardar um pouco para garantir que a API esteja totalmente carregada
                const checkAPI = () => {
                    if (window.google && window.google.maps && window.google.maps.Map) {
                        window.__SF_DEBUG__ && console.log('Google Maps API carregada com sucesso');
                        resolveLoading();
                    } else {
                        window.__SF_DEBUG__ && console.log('Aguardando Google Maps API...');
                        setTimeout(checkAPI, 100);
                    }
                };
                
                setTimeout(checkAPI, 500);
            };
            
            script.onerror = () => {
                console.error('Erro ao carregar Google Maps API');
                rejectLoading(new Error('Erro ao carregar Google Maps API'));
            };
            
            document.head.appendChild(script);
        });

        window.googleMapsLoading.then(resolve).catch(reject);
    });
}

// Exibir resultados da simulação
function displaySimulationResults(routeInfo, distance, fuelLiters, fuelCost, tollCost, totalCost, fuelConsumption, calcPack, tollStepCount, isRoundTrip) {
    tollStepCount = Number(tollStepCount) || 0;
    // Atualizar cards de resultados
    document.getElementById('simDistance').textContent = `${distance.toFixed(1)} km` + (isRoundTrip ? ' (ida e volta)' : '');
    document.getElementById('simDuration').textContent = (routeInfo.duration ? routeInfo.duration.text : '-') + (isRoundTrip ? ' (x2)' : '');
    document.getElementById('simFuelCost').textContent = `R$ ${fuelCost.toFixed(2)}`;
    document.getElementById('simFuelLiters').textContent = `${fuelLiters.toFixed(1)} litros`;
    document.getElementById('simTolls').textContent = 'R$ ' + tollCost.toFixed(2);
    var eixosU = calcPack && calcPack.eixos_conjunto != null ? Number(calcPack.eixos_conjunto) : null;
    // Legenda enxuta: uma frase clara (detalhe completo fica no painel "Detalhes da Rota").
    var tollSub;
    if (tollCost <= 0) {
        tollSub = 'Sem pedágios no trajeto';
    } else if (eixosU) {
        tollSub = 'Estimativa p/ caminhão (' + eixosU + ' eixos)' + (tollStepCount > 0 ? ' · ≈ ' + tollStepCount + ' praça(s)' : '');
    } else {
        tollSub = 'Estimativa Google Routes' + (tollStepCount > 0 ? ' · ≈ ' + tollStepCount + ' praça(s)' : '');
    }
    document.getElementById('simTollCount').textContent = tollSub;
    if (window.__simLastResult) window.__simLastResult.tollSubText = tollSub;
    // Custo total e R$/km são definidos por simRecomputeProfit (incluem custo operacional).
    document.getElementById('simTotalCost').textContent = `R$ ${totalCost.toFixed(2)}`;
    document.getElementById('simCostPerKm').textContent = (distance > 0) ? `R$ ${(totalCost / distance).toFixed(2)}/km` : '-';

    // Detalhes da rota
    displayRouteDetails(routeInfo, distance, fuelLiters, fuelConsumption, calcPack, tollStepCount);

    // Mostrar resultados
    document.getElementById('simulationResults').style.display = 'block';
}

/**
 * Recalcula custo operacional, comissão, custo total, lucro e margem a partir dos
 * campos financeiros + da última simulação. Chamado após simular e a cada edição
 * de frete/comissão/custo-por-km (não refaz a chamada à API).
 */
function simRecomputeProfit() {
    var s = window.__simLastResult;
    if (!s) return;

    var frete = parseFloat((document.getElementById('simFreteValue') || {}).value) || 0;
    var commissionPct = parseFloat((document.getElementById('simCommissionPct') || {}).value) || 0;
    var opPerKm = parseFloat((document.getElementById('simOpCostPerKm') || {}).value) || 0;
    var arlaPrice = parseFloat((document.getElementById('simArlaPrice') || {}).value) || 0;
    var targetMargin = parseFloat((document.getElementById('simTargetMargin') || {}).value);

    var operationalCost = opPerKm * s.distance;
    // ARLA 32 ≈ 5% do volume de diesel.
    var arlaCost = (arlaPrice > 0) ? (s.fuelLiters * 0.05 * arlaPrice) : 0;
    var commission = frete * (commissionPct / 100);
    s.operationalCost = operationalCost;
    s.arlaCost = arlaCost;
    s.commission = commission;

    // Pedágio: valor real informado tem prioridade sobre a estimativa do Google.
    var tollManual = parseFloat((document.getElementById('simTollManual') || {}).value);
    var effectiveToll = (Number.isFinite(tollManual) && tollManual > 0) ? tollManual : s.tollCost;
    s.tollEffective = effectiveToll;
    var tollEl = document.getElementById('simTolls');
    var tollSubEl = document.getElementById('simTollCount');
    if (tollEl) tollEl.textContent = 'R$ ' + effectiveToll.toFixed(2);
    if (tollSubEl) {
        tollSubEl.textContent = (Number.isFinite(tollManual) && tollManual > 0)
            ? '✔ valor real informado por você'
            : (s.tollSubText || tollSubEl.textContent);
    }

    // Custo total operacional (independe do frete): combustível + ARLA + pedágio + operacional.
    var totalCost = s.fuelCost + arlaCost + effectiveToll + operationalCost;
    document.getElementById('simTotalCost').textContent = 'R$ ' + totalCost.toFixed(2);
    document.getElementById('simCostPerKm').textContent = (s.distance > 0) ? ('R$ ' + (totalCost / s.distance).toFixed(2) + '/km') : '-';
    s.totalCost = totalCost;

    // Frete sugerido para atingir a margem-alvo (considera a comissão como % do frete).
    var sugEl = document.getElementById('simSuggestedFreight');
    if (sugEl) {
        var c = commissionPct / 100;
        var m = (Number.isFinite(targetMargin) ? targetMargin : NaN) / 100;
        var breakEven = (1 - c) > 0 ? (totalCost / (1 - c)) : null;
        var partes = [];
        if (Number.isFinite(m) && (1 - c - m) > 0) {
            var freteSug = totalCost / (1 - c - m);
            partes.push('Frete sugerido p/ ' + targetMargin + '%: R$ ' + freteSug.toFixed(2)
                + (s.distance > 0 ? ' (R$ ' + (freteSug / s.distance).toFixed(2) + '/km)' : ''));
        } else if (Number.isFinite(m)) {
            partes.push('Margem-alvo + comissão ≥ 100%: impossível.');
        }
        if (breakEven !== null) {
            partes.push('Frete de equilíbrio (lucro 0): R$ ' + breakEven.toFixed(2));
        }
        sugEl.innerHTML = partes.join('<br>');
        sugEl.style.color = '#1565c0';
    }

    simRenderAntt();

    var cards = document.getElementById('simProfitCards');
    if (frete <= 0) {
        if (cards) cards.style.display = 'none';
        return;
    }
    if (cards) cards.style.display = '';

    var profit = frete - (totalCost + commission);
    var margin = frete > 0 ? (profit / frete) * 100 : 0;
    s.frete = frete;
    s.profit = profit;
    s.margin = margin;

    var profitEl = document.getElementById('simProfit');
    profitEl.textContent = 'R$ ' + profit.toFixed(2);
    profitEl.classList.toggle('rc-val--loss', profit < 0);
    profitEl.classList.toggle('rc-val--gain', profit >= 0);
    document.getElementById('simProfitPerKm').textContent = (s.distance > 0)
        ? ('R$ ' + (profit / s.distance).toFixed(2) + '/km') : '-';
    document.getElementById('simMargin').textContent = margin.toFixed(1) + '%';
    document.getElementById('simMarginHint').textContent = profit < 0 ? 'prejuízo na rota' : 'sobre o frete';
    document.getElementById('simCommission').textContent = 'R$ ' + commission.toFixed(2);
    document.getElementById('simFreightPerKm').textContent = (s.distance > 0)
        ? ('R$ ' + (frete / s.distance).toFixed(2) + '/km') : '-';

    // Lucro por dia (pela duração da viagem; jornada de 8h/dia dirigindo).
    var perDayEl = document.getElementById('simProfitPerDay');
    var perDayHint = document.getElementById('simProfitPerDayHint');
    if (perDayEl) {
        if (s.durationSec > 0) {
            var diasViagem = Math.max(1, Math.ceil(s.durationSec / 3600 / 8));
            perDayEl.textContent = 'R$ ' + (profit / diasViagem).toFixed(2);
            if (perDayHint) perDayHint.textContent = '≈ ' + diasViagem + (diasViagem === 1 ? ' dia' : ' dias') + ' (jornada 8h/dia)';
        } else {
            perDayEl.textContent = '-';
        }
    }
}

/** Busca o piso mínimo ANTT no servidor e atualiza o painel. */
function simFetchAnttPiso() {
    var s = window.__simLastResult;
    if (!s || !s.distanceOneWay || typeof sfApiUrl !== 'function') return;
    var tipo = (document.getElementById('simCargoType') || {}).value || 'geral';
    var altoEl = document.getElementById('simAltoDesempenho');
    var alto = (altoEl && altoEl.checked) ? 'S' : 'N';
    var url = sfApiUrl('route_actions.php?action=get_antt_piso'
        + '&tipo_carga=' + encodeURIComponent(tipo)
        + '&eixos=' + encodeURIComponent(s.eixos || 6)
        + '&distancia=' + encodeURIComponent(s.distanceOneWay.toFixed(2))
        + '&contratacao=' + encodeURIComponent(s.contratacao || 'COMPOSICAO')
        + '&alto=' + alto);
    fetch(url, { credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res && res.success && res.data) {
                s.anttPiso = res.data;
            } else {
                s.anttPiso = null;
            }
            simRenderAntt();
        })
        .catch(function () { s.anttPiso = null; simRenderAntt(); });
}

/** Compara o frete informado com o piso ANTT e renderiza o alerta legal. */
function simRenderAntt() {
    var s = window.__simLastResult;
    var panel = document.getElementById('simAnttPanel');
    if (!panel) return;
    if (!s) { panel.style.display = 'none'; return; }
    if (!s.anttPiso) {
        // Sem coeficiente ativo (ex.: contratação só de tração, desabilitada).
        if (s.contratacao === 'TRACAO') {
            panel.style.display = '';
            document.getElementById('simAnttPiso').textContent = '—';
            document.getElementById('simAnttFrete').textContent = '—';
            document.getElementById('simAnttDiff').textContent = '—';
            var al = document.getElementById('simAnttAlert');
            al.className = 'route-sim-antt-alert';
            al.textContent = 'Piso da contratação só da unidade de tração (Tabela B/D) está em validação e desabilitado. Para conjunto cavalo+carreta o piso é calculado normalmente.';
            var mt = document.getElementById('simAnttMeta'); if (mt) mt.textContent = '';
        } else {
            panel.style.display = 'none';
        }
        return;
    }
    panel.style.display = '';

    var piso = Number(s.anttPiso.piso || 0);
    var frete = parseFloat((document.getElementById('simFreteValue') || {}).value) || 0;
    var brl = function (v) { return 'R$ ' + Number(v || 0).toFixed(2); };

    document.getElementById('simAnttPiso').textContent = brl(piso);
    document.getElementById('simAnttFrete').textContent = frete > 0 ? brl(frete) : '—';

    var diffEl = document.getElementById('simAnttDiff');
    var alertEl = document.getElementById('simAnttAlert');
    if (frete <= 0) {
        diffEl.textContent = '—';
        alertEl.className = 'route-sim-antt-alert';
        alertEl.textContent = 'Informe o valor do frete para comparar com o piso legal.';
    } else {
        var diff = frete - piso;
        diffEl.textContent = brl(diff);
        if (diff < 0) {
            alertEl.className = 'route-sim-antt-alert is-below';
            alertEl.innerHTML = '⚠️ <strong>Frete abaixo do piso mínimo legal da ANTT.</strong> Faltam ' + brl(-diff)
                + '. Contratar abaixo do piso é infração (Lei 13.703/2018).';
        } else {
            alertEl.className = 'route-sim-antt-alert is-ok';
            alertEl.innerHTML = '✔ Frete <strong>acima</strong> do piso mínimo ANTT (folga de ' + brl(diff) + ').';
        }
    }
    var metaEl = document.getElementById('simAnttMeta');
    if (metaEl) {
        metaEl.textContent = 'Piso = (CCD ' + Number(s.anttPiso.ccd).toFixed(4) + ' × ' + s.distanceOneWay.toFixed(0)
            + ' km) + CC ' + brl(s.anttPiso.cc) + ' · ' + (s.contratacao === 'TRACAO' ? 'só veículo' : 'composição')
            + ' · ' + s.anttPiso.eixos_usado + ' eixos · vigência ' + s.anttPiso.vigencia
            + ' · pedágio entra à parte (Art. 3º §3º).';
    }
}

/**
 * Pré-preenche o formulário de cadastro de rota (routeModal) com os dados da
 * simulação e abre o modal, para o gestor salvar sem redigitar.
 */
function simCreateRouteFromSimulation() {
    var s = window.__simLastResult;
    if (!s) {
        showSimulationError('Faça uma simulação antes de criar a rota.');
        return;
    }
    var setVal = function (id, val) {
        var el = document.getElementById(id);
        if (el != null && val != null && val !== '') el.value = val;
    };

    // Origem/destino: replica estado+cidade dos selects do simulador para o cadastro.
    var copySelect = function (fromId, toId) {
        var from = document.getElementById(fromId);
        var to = document.getElementById(toId);
        if (from && to && from.value) {
            to.value = from.value;
            to.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };

    if (typeof showAddRouteModal === 'function') {
        showAddRouteModal();
    } else {
        var addBtn = document.getElementById('addRouteBtn');
        if (addBtn) addBtn.click();
    }

    // Aguarda o modal montar/popular os selects antes de aplicar os valores.
    setTimeout(function () {
        copySelect('sim_estado_origem', 'estado_origem');
        copySelect('sim_estado_destino', 'estado_destino');
        // Cidades dependem do change de estado (carregamento assíncrono).
        setTimeout(function () {
            copySelect('sim_cidade_origem_id', 'cidade_origem_id');
            copySelect('sim_cidade_destino_id', 'cidade_destino_id');
        }, 600);

        setVal('veiculo_id', s.vehicleId);
        setVal('distancia_km', s.distance.toFixed(1));
        if (s.frete != null && s.frete > 0) setVal('frete', s.frete.toFixed(2));
        if (s.commission != null && s.commission > 0) setVal('comissao', s.commission.toFixed(2));
        // Motorista e peso da carga, se informados na simulação.
        var drv = document.getElementById('simDriver');
        if (drv && drv.value) setVal('motorista_id', drv.value);
        var cargo = document.getElementById('simCargoWeight');
        if (cargo && cargo.value) setVal('peso_carga', cargo.value);
        var dataRota = document.getElementById('data_rota');
        if (dataRota && !dataRota.value) dataRota.value = new Date().toISOString().slice(0, 10);
        var veiculoSel = document.getElementById('veiculo_id');
        if (veiculoSel) veiculoSel.dispatchEvent(new Event('change', { bubbles: true }));
    }, 300);

    closeSimulationModal();
    if (typeof showToast === 'function') {
        showToast('Dados da simulação aplicados ao cadastro de rota. Revise e salve.', 'info');
    }
}

/** Fecha o modal de simulação com ESC e clique no fundo. */
function simBindModalDismiss() {
    var modal = document.getElementById('routeSimulationModal');
    if (!modal || modal.dataset.sfSimDismissBound) return;
    modal.dataset.sfSimDismissBound = '1';
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeSimulationModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var disp = window.getComputedStyle(modal).display;
            if (disp !== 'none') closeSimulationModal();
        }
    });
}

// Exibir detalhes da rota
function displayRouteDetails(routeInfo, distance, fuelLiters, fuelConsumption, calcPack, tollStepCount) {
    tollStepCount = Number(tollStepCount) || 0;
    const detailsDiv = document.getElementById('routeDetails');
    detailsDiv.innerHTML = '';
    // Autonomia real pela capacidade do tanque informada (corrige o antigo fixo de 50L).
    var tankEl = document.getElementById('simTankCapacity');
    var simTankCapacity = parseFloat(tankEl && tankEl.value) || 400;
    var autonomyKm = fuelConsumption * simTankCapacity;
    var simRefuelStops = (autonomyKm > 0) ? Math.max(0, Math.ceil(distance / autonomyKm) - 1) : 0;
    var refGoogleBrl = 0;
    var tollsForCostBrl = 0;
    var tollPassLine = 'Nenhuma (preço padrão da Google Routes API)';
    var legsTollLine = '';
    if (calcPack) {
        tollsForCostBrl = Number(calcPack.tolls_total != null ? calcPack.tolls_total : 0) || 0;
        refGoogleBrl = calcPack.tolls_google_reference_brl != null
            ? Number(calcPack.tolls_google_reference_brl)
            : tollsForCostBrl;
        if (Array.isArray(calcPack.toll_passes_requested) && calcPack.toll_passes_requested.length) {
            tollPassLine = calcPack.toll_passes_requested.join(', ');
        }
        if (Array.isArray(calcPack.tolls_legs_brl) && calcPack.tolls_legs_brl.length) {
            legsTollLine = calcPack.tolls_legs_brl.map(function (row) {
                return 'Trecho ' + (Number(row.leg_index) + 1) + ': R$ ' + Number(row.tolls_brl).toFixed(2);
            }).join(' · ');
        }
    }
    
    let html = '<div class="route-sim-details-wrap">';
    html += `
        <div class="route-sim-box route-sim-box--fuel">
            <h4 class="route-sim-box__title">⛽ Informações de Abastecimento</h4>
            <p class="route-sim-box__p"><strong class="route-sim-box__strong--accent">Consumo estimado:</strong> ${fuelConsumption} km/L</p>
            <p class="route-sim-box__p"><strong class="route-sim-box__strong--accent">Combustível necessário:</strong> ${fuelLiters.toFixed(1)} litros</p>
            <p class="route-sim-box__p"><strong class="route-sim-box__strong--accent">Autonomia:</strong> ${(fuelConsumption * simTankCapacity).toFixed(0)} km (tanque de ${simTankCapacity.toFixed(0)}L)</p>
            <p class="route-sim-box__p"><strong class="route-sim-box__strong--accent">Reabastecimentos:</strong> ${simRefuelStops} ${simRefuelStops === 1 ? 'parada' : 'paradas'} de combustível na viagem</p>
        </div>
        <div class="route-sim-box route-sim-box--route">
            <h4 class="route-sim-box__title route-sim-box__title--green">🛣️ Informações da Rota</h4>
            <p class="route-sim-box__p"><strong class="route-sim-box__strong--green">Origem:</strong> ${routeInfo.start_address}</p>
            <p class="route-sim-box__p"><strong class="route-sim-box__strong--green">Destino:</strong> ${routeInfo.end_address}</p>
            <p class="route-sim-box__p"><strong class="route-sim-box__strong--green">Distância:</strong> ${routeInfo.distance.text}</p>
            <p class="route-sim-box__p"><strong class="route-sim-box__strong--green">Duração:</strong> ${routeInfo.duration.text}</p>
            <p class="route-sim-box__p"><strong class="route-sim-box__strong--green">Pedágio (uso no custo):</strong> R$ ${tollsForCostBrl.toFixed(2)}</p>
            <p class="route-sim-box__p"><strong class="route-sim-box__strong--green">Referência Google (leve):</strong> R$ ${refGoogleBrl.toFixed(2)} — a documentação indica perfil não comercial; no Brasil o valor real por eixo/praça costuma ser maior (ex.: Qualp/WebRouter).</p>
            <p class="route-sim-box__p"><strong class="route-sim-box__strong--green">TAG enviada à Routes API:</strong> ${tollPassLine}</p>
            <p class="route-sim-box__p"><strong class="route-sim-box__strong--green">Pedágio por trecho (API):</strong> ${legsTollLine || '—'}</p>
            <p class="route-sim-box__p"><strong class="route-sim-box__strong--green">Mapa:</strong> marcadores laranja nos passos em que a navegação cita pedágio (${tollStepCount > 0 ? '≈ ' + tollStepCount : '—'}).</p>
        </div>
    `;

    // Jornada do motorista (Lei do Motorista) + emissão de CO2.
    var s = window.__simLastResult || {};
    var drivingH = (s.durationSec > 0) ? (s.durationSec / 3600) : 0;
    var restStops = Math.floor(drivingH / 5.5);          // 30 min a cada 5h30 dirigindo
    var diasViagem = Math.max(1, Math.ceil(drivingH / 8)); // jornada de 8h/dia
    var co2Kg = fuelLiters * 2.68;                         // diesel ≈ 2,68 kg CO2/L
    html += `
        <div class="route-sim-box route-sim-box--journey">
            <h4 class="route-sim-box__title">🧑‍✈️ Jornada &amp; ambiental</h4>
            <p class="route-sim-box__p"><strong class="route-sim-box__strong--accent">Tempo dirigindo:</strong> ${drivingH > 0 ? drivingH.toFixed(1) + ' h' : '—'}${s.isRoundTrip ? ' (ida e volta)' : ''}</p>
            <p class="route-sim-box__p"><strong class="route-sim-box__strong--accent">Descansos (Lei do Motorista):</strong> ${restStops} parada(s) de 30 min (a cada 5h30)</p>
            <p class="route-sim-box__p"><strong class="route-sim-box__strong--accent">Duração estimada da viagem:</strong> ${diasViagem} ${diasViagem === 1 ? 'dia' : 'dias'} (jornada de 8h/dia + descanso)</p>
            <p class="route-sim-box__p"><strong class="route-sim-box__strong--accent">Emissão estimada:</strong> ${co2Kg > 0 ? (co2Kg / 1000).toFixed(2) + ' t de CO₂' : '—'} (~2,68 kg/L diesel)</p>
        </div>
    `;
    html += '</div>';
    detailsDiv.innerHTML = html;
}

// Funções de exibição de mensagens (baseadas no example.html)
function showSimulationInfo(message) {
    const infoDiv = document.getElementById('simulationInfo');
    const errorDiv = document.getElementById('simulationError');
    
    if (infoDiv) {
        infoDiv.innerHTML = message;
        infoDiv.style.display = 'block';
    }
    if (errorDiv) {
        errorDiv.style.display = 'none';
    }
}

function showSimulationError(message) {
    const infoDiv = document.getElementById('simulationInfo');
    const errorDiv = document.getElementById('simulationError');
    
    if (errorDiv) {
        errorDiv.innerHTML = message;
        errorDiv.style.display = 'block';
    }
    if (infoDiv) {
        infoDiv.style.display = 'none';
    }
}

/** Lê as simulações salvas (localStorage). */
function simGetSaved() {
    try {
        var raw = localStorage.getItem('sfSimSaved');
        var arr = raw ? JSON.parse(raw) : [];
        return Array.isArray(arr) ? arr : [];
    } catch (e) { return []; }
}

/** Salva a simulação atual para comparação. */
function simSaveCurrent() {
    var s = window.__simLastResult;
    if (!s || !s.totalCost) {
        showSimulationError('Faça uma simulação (com frete) antes de salvar.');
        return;
    }
    var trim = function (a) { return String(a || '').replace(/,\s*Brasil$/i, ''); };
    var rec = {
        rota: trim(s.origin) + ' → ' + trim(s.destination),
        veiculo: s.vehicleLabel || '',
        distance: s.distance || 0,
        totalCost: s.totalCost || 0,
        frete: s.frete || 0,
        profit: (s.profit != null) ? s.profit : null,
        margin: (s.margin != null) ? s.margin : null,
        roundTrip: !!s.isRoundTrip,
        ts: Date.now()
    };
    var arr = simGetSaved();
    arr.unshift(rec);
    if (arr.length > 10) arr = arr.slice(0, 10);
    try { localStorage.setItem('sfSimSaved', JSON.stringify(arr)); } catch (e) {}
    simRenderSaved();
    if (typeof showToast === 'function') showToast('Simulação salva para comparação.', 'success');
}

/** Renderiza a tabela de simulações salvas. */
function simRenderSaved() {
    var panel = document.getElementById('simSavedPanel');
    var body = document.getElementById('simSavedBody');
    if (!panel || !body) return;
    var arr = simGetSaved();
    if (!arr.length) {
        panel.style.display = 'none';
        body.innerHTML = '';
        return;
    }
    panel.style.display = '';
    var brl = function (v) { return 'R$ ' + Number(v || 0).toFixed(2); };
    body.innerHTML = arr.map(function (r, i) {
        var lucro = (r.profit != null) ? brl(r.profit) : '—';
        var margem = (r.margin != null) ? r.margin.toFixed(1) + '%' : '—';
        var lossCls = (r.profit != null && r.profit < 0) ? ' style="color:#c62828"' : '';
        return '<tr>'
            + '<td>' + (r.rota || '—') + (r.roundTrip ? ' <small>(i/v)</small>' : '') + '</td>'
            + '<td>' + (r.veiculo || '—') + '</td>'
            + '<td class="rd-num">' + Number(r.distance || 0).toFixed(0) + ' km</td>'
            + '<td class="rd-num">' + brl(r.totalCost) + '</td>'
            + '<td class="rd-num">' + brl(r.frete) + '</td>'
            + '<td class="rd-num"' + lossCls + '>' + lucro + '</td>'
            + '<td class="rd-num">' + margem + '</td>'
            + '<td class="rd-num"><button type="button" class="btn-link-sm sim-saved-del" data-idx="' + i + '" title="Remover">✕</button></td>'
            + '</tr>';
    }).join('');
    // Remoção por linha.
    body.querySelectorAll('.sim-saved-del').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var idx = parseInt(btn.getAttribute('data-idx'), 10);
            var list = simGetSaved();
            if (idx >= 0 && idx < list.length) {
                list.splice(idx, 1);
                try { localStorage.setItem('sfSimSaved', JSON.stringify(list)); } catch (e) {}
                simRenderSaved();
            }
        });
    });
}

// Função para fechar o modal de simulação
function closeSimulationModal() {
    const modal = document.getElementById('routeSimulationModal');
    if (modal) {
        modal.style.display = 'none';
        window.__SF_DEBUG__ && console.log('Modal de simulação fechado');
    }
}
