// Simulador de Rotas (baseado no example.html)
let simulationMap = null;
let simulationRouteManager = null;
/** id do veículo → nome da carreta cadastrada (tipos_carretas) */
window.__simVehicleCarreta = window.__simVehicleCarreta || {};

/** Marcadores laranja nos passos da rota que citam pedágio (aproximação — não são as praças oficiais). */
var simulationTollMarkers = [];

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
    simUpdateVehicleCarretaLine();
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
        return '<option value="' + v.id + '" data-vehicle-type="' + vt + '" data-km-l="' + kmL + '" data-emission="' + em + '" data-eixos="' + eixos + '">' + label + '</option>';
    }).join('');
    select.innerHTML = '<option value="">Selecione um veículo</option>' + opts;
    simUpdateVehicleCarretaLine();
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

    simLoadEstadosIfNeeded()
        .then(function () { return simLoadVehicles(); })
        .catch(function (e) {
            console.error(e);
            showSimulationError('Não foi possível carregar estados/veículos: ' + (e && e.message ? e.message : String(e)));
        });

    window.__SF_DEBUG__ && console.log('Simulador inicializado');
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
    var consumptionKmL = opt && opt.getAttribute('data-km-l') ? parseFloat(opt.getAttribute('data-km-l')) : 0;
    var vehicleType = (opt && opt.getAttribute('data-vehicle-type')) ? opt.getAttribute('data-vehicle-type') : 'TRUCK';

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
        showSimulationInfo('Inicializando simulador...');
        
        showSimulationInfo('Obtendo chave da API...');
        var response = await fetch(sfAppUrl('google-maps/api.php?action=get_config'), {
            credentials: 'include'
        });
        var data = await response.json();
        var apiKey = data && data.data && data.data.google_maps_api_key ? String(data.data.google_maps_api_key).trim() : '';
        
        if (!data.success || !apiKey) {
            showSimulationError('Nenhuma chave Google configurada (Maps ou Vision em Configurações).');
            return;
        }

        showSimulationInfo('Carregando Google Maps...');
        await initSimulationMap(apiKey);
        
        showSimulationInfo('Calculando rota...');
        
        var routePromise = simulationRouteManager.calculateRoute(origin, destination);
        var timeoutPromise = new Promise(function (_, reject) {
            setTimeout(function () { reject(new Error('Timeout: Cálculo da rota demorou muito')); }, 30000);
        });
        
        var result = await Promise.race([routePromise, timeoutPromise]);
        
        if (result) {
            simApplyRouteToSimulationMap(result);
            var routeInfo = simulationRouteManager.getRouteInfo();
            
            if (routeInfo) {
                showSimulationInfo('Consultando pedágios reais na Google Routes API...');
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
                var distance = Number(calc.distance_km || 0);
                var fuelConsumption = Number(calc.fuel_consumption_km_l || getVehicleConsumption(vehicleType));
                var fuelLiters = Number(calc.fuel_liters || 0);
                var fuelCost = Number(calc.fuel_cost || 0);
                var tollCost = Number(calc.tolls_total || 0);
                var totalCost = Number(calc.total_cost || (fuelCost + tollCost));
                var tollStepCount = simEstimateTollStepCount(routeInfo.steps || []);
                
                displaySimulationResults(routeInfo, distance, fuelLiters, fuelCost, tollCost, totalCost, fuelConsumption, calc, tollStepCount);
                showSimulationInfo('Rota calculada com sucesso!');
            } else {
                showSimulationError('Informações da rota não disponíveis');
            }
        } else {
            showSimulationError('Não foi possível calcular a rota');
        }
        
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
        zoom: 6,
        center: { lat: -23.5505, lng: -46.6333 }
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
function displaySimulationResults(routeInfo, distance, fuelLiters, fuelCost, tollCost, totalCost, fuelConsumption, calcPack, tollStepCount) {
    tollStepCount = Number(tollStepCount) || 0;
    // Atualizar cards de resultados
    document.getElementById('simDistance').textContent = `${distance.toFixed(1)} km`;
    document.getElementById('simDuration').textContent = routeInfo.duration.text;
    document.getElementById('simFuelCost').textContent = `R$ ${fuelCost.toFixed(2)}`;
    document.getElementById('simFuelLiters').textContent = `${fuelLiters.toFixed(1)} litros`;
    document.getElementById('simTolls').textContent = 'R$ ' + tollCost.toFixed(2);
    var gRef = calcPack && calcPack.tolls_google_reference_brl != null ? Number(calcPack.tolls_google_reference_brl) : null;
    var eixosU = calcPack && calcPack.eixos_conjunto != null ? Number(calcPack.eixos_conjunto) : null;
    var tollSubParts = [];
    if (gRef != null && tollCost > 0 && Math.abs(gRef - tollCost) > 0.02) {
        tollSubParts.push('Ref. Google (não comercial): R$ ' + gRef.toFixed(2));
        tollSubParts.push('Estimativa caminhão: Google × (eixos ÷ 2), eixos ' + (eixosU || '—'));
    } else if (tollCost > 0) {
        tollSubParts.push('Google Routes API (pode ser veículo leve)');
    }
    var tpReq = calcPack && Array.isArray(calcPack.toll_passes_requested) ? calcPack.toll_passes_requested : [];
    if (tpReq.length) {
        tollSubParts.push('TAG na API: ' + tpReq.join(', '));
    }
    if (tollStepCount > 0) {
        tollSubParts.push('≈ ' + tollStepCount + ' ponto(s) no mapa (passos com “pedágio”)');
    } else if (tollCost > 0) {
        tollSubParts.push('Praças exatas não vêm na API');
    } else {
        tollSubParts.push('Sem pedágios no trajeto');
    }
    document.getElementById('simTollCount').textContent = tollSubParts.join(' · ');
    document.getElementById('simTotalCost').textContent = `R$ ${totalCost.toFixed(2)}`;
    document.getElementById('simCostPerKm').textContent = (distance > 0) ? `R$ ${(totalCost / distance).toFixed(2)}/km` : '-';
    
    // Detalhes da rota
    displayRouteDetails(routeInfo, distance, fuelLiters, fuelConsumption, calcPack, tollStepCount);
    
    // Mostrar resultados
    document.getElementById('simulationResults').style.display = 'block';
}

// Exibir detalhes da rota
function displayRouteDetails(routeInfo, distance, fuelLiters, fuelConsumption, calcPack, tollStepCount) {
    tollStepCount = Number(tollStepCount) || 0;
    const detailsDiv = document.getElementById('routeDetails');
    detailsDiv.innerHTML = '';
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
            <p class="route-sim-box__p"><strong class="route-sim-box__strong--accent">Autonomia:</strong> ${(fuelConsumption * 50).toFixed(0)} km (tanque de 50L)</p>
            <p class="route-sim-box__p"><strong class="route-sim-box__strong--accent">Recomendação:</strong> ${distance > (fuelConsumption * 50) ? 'Abastecer antes da viagem' : 'Tanque suficiente para a viagem'}</p>
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

// Função para fechar o modal de simulação
function closeSimulationModal() {
    const modal = document.getElementById('routeSimulationModal');
    if (modal) {
        modal.style.display = 'none';
        window.__SF_DEBUG__ && console.log('Modal de simulação fechado');
    }
}
