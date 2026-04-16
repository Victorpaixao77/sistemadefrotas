// Simulador de Rotas (baseado no example.html)
let simulationMap = null;
let simulationRouteManager = null;

// Função para inicializar o simulador
function initRouteSimulator() {
    window.__SF_DEBUG__ && console.log('Inicializando simulador de rotas...');
    
    // Verificar se os scripts estão carregados
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
    
    // Event listeners do simulador (uma vez — initRouteSimulator pode ser chamado várias vezes ao abrir o modal)
    const simulateBtn = document.getElementById('simulateRouteBtnModal');
    if (simulateBtn) {
        window.__SF_DEBUG__ && console.log('Botão de simular encontrado no modal');
        if (!simulateBtn.dataset.sfSimBound) {
            simulateBtn.dataset.sfSimBound = '1';
            simulateBtn.addEventListener('click', simulateRoute);
        }
    } else {
        console.error('Botão de simular não encontrado no modal');
    }
    
    // Preencher opções de veículos padrão
    loadDefaultVehicles();
    window.__SF_DEBUG__ && console.log('Simulador inicializado');
}

// Carregar veículos padrão para simulação
function loadDefaultVehicles() {
    const select = document.getElementById('simVehicle');
    select.innerHTML = `
        <option value="">Selecione um tipo de veículo</option>
        <option value="caminhao_pequeno">Caminhão Pequeno (8-10 km/L)</option>
        <option value="caminhao_medio">Caminhão Médio (6-8 km/L)</option>
        <option value="caminhao_grande">Caminhão Grande (4-6 km/L)</option>
        <option value="carreta">Carreta (3-5 km/L)</option>
        <option value="van">Van (10-12 km/L)</option>
        <option value="pickup">Pickup (8-10 km/L)</option>
    `;
}

// Obter consumo baseado no tipo de veículo
function getVehicleConsumption(vehicleType) {
    const consumptions = {
        'caminhao_pequeno': 9.0,
        'caminhao_medio': 7.0,
        'caminhao_grande': 5.0,
        'carreta': 4.0,
        'van': 11.0,
        'pickup': 9.0
    };
    
    return consumptions[vehicleType] || 8.0; // Padrão se não selecionado
}

// Simular rota (baseado no example.html)
async function simulateRoute() {
    window.__SF_DEBUG__ && console.log('Função simulateRoute chamada');
    
    const origin = document.getElementById('simOrigin').value;
    const destination = document.getElementById('simDestination').value;
    const fuelPrice = parseFloat(document.getElementById('simFuelPrice').value);
    const vehicleType = document.getElementById('simVehicle').value;

    window.__SF_DEBUG__ && console.log('Valores:', { origin, destination, fuelPrice, vehicleType });

    if (!origin || !destination) {
        showSimulationError('Por favor, preencha origem e destino');
        return;
    }

    try {
        showSimulationInfo('Inicializando simulador...');
        window.__SF_DEBUG__ && console.log('Iniciando simulação...');
        
        // Obter chave da API do Google Maps
        showSimulationInfo('Obtendo chave da API...');
        const response = await fetch(sfAppUrl('google-maps/api.php?action=get_config'), {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (!data.success || !data.data.google_maps_api_key) {
            showSimulationError('Chave da API do Google Maps não configurada');
            return;
        }

        window.__SF_DEBUG__ && console.log('Chave da API obtida:', data.data.google_maps_api_key);

        // Inicializar Google Maps para simulação
        showSimulationInfo('Carregando Google Maps...');
        await initSimulationMap(data.data.google_maps_api_key);
        
        showSimulationInfo('Calculando rota...');
        
        // Calcular rota usando RouteManager
        window.__SF_DEBUG__ && console.log('Iniciando cálculo da rota...');
        
        // Adicionar timeout para evitar travamento
        const routePromise = simulationRouteManager.calculateRoute(origin, destination);
        const timeoutPromise = new Promise((_, reject) => 
            setTimeout(() => reject(new Error('Timeout: Cálculo da rota demorou muito')), 30000)
        );
        
        const result = await Promise.race([routePromise, timeoutPromise]);
        window.__SF_DEBUG__ && console.log('Resultado do cálculo:', result);
        
        if (result) {
            window.__SF_DEBUG__ && console.log('Rota calculada com sucesso, obtendo informações...');
            // Obter informações da rota
            const routeInfo = simulationRouteManager.getRouteInfo();
            window.__SF_DEBUG__ && console.log('Informações da rota:', routeInfo);
            
            if (routeInfo) {
                // Calcular custos
                const fuelConsumption = getVehicleConsumption(vehicleType);
                const distance = parseFloat(routeInfo.distance.text.replace(/[^\d.]/g, ''));
                const fuelLiters = distance / fuelConsumption;
                const fuelCost = fuelLiters * fuelPrice;
                const tollCost = calculateTollCost(distance);
                const totalCost = fuelCost + tollCost;
                
                window.__SF_DEBUG__ && console.log('Custos calculados:', { distance, fuelLiters, fuelCost, tollCost, totalCost });
                
                // Exibir resultados
                displaySimulationResults(routeInfo, distance, fuelLiters, fuelCost, tollCost, totalCost, fuelConsumption);
                showSimulationInfo('Rota calculada com sucesso!');
            } else {
                console.error('Informações da rota não disponíveis');
                showSimulationError('Informações da rota não disponíveis');
            }
        } else {
            console.error('Resultado do cálculo é null');
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
function displaySimulationResults(routeInfo, distance, fuelLiters, fuelCost, tollCost, totalCost, fuelConsumption) {
    // Atualizar cards de resultados
    document.getElementById('simDistance').textContent = `${distance.toFixed(1)} km`;
    document.getElementById('simDuration').textContent = routeInfo.duration.text;
    document.getElementById('simFuelCost').textContent = `R$ ${fuelCost.toFixed(2)}`;
    document.getElementById('simFuelLiters').textContent = `${fuelLiters.toFixed(1)} litros`;
    document.getElementById('simTolls').textContent = `R$ ${tollCost.toFixed(2)}`;
    document.getElementById('simTollCount').textContent = `${Math.floor(distance / 100)} pedágios`;
    document.getElementById('simTotalCost').textContent = `R$ ${totalCost.toFixed(2)}`;
    document.getElementById('simCostPerKm').textContent = `R$ ${(totalCost / distance).toFixed(2)}/km`;
    
    // Detalhes da rota
    displayRouteDetails(routeInfo, distance, fuelLiters, fuelConsumption);
    
    // Mostrar resultados
    document.getElementById('simulationResults').style.display = 'block';
}

// Calcular custo de pedágios (simulação)
function calculateTollCost(distance) {
    let tollCost = 0;
    
    if (distance > 50) tollCost += 5.00;
    if (distance > 150) tollCost += 8.50;
    if (distance > 300) tollCost += 12.00;
    if (distance > 500) tollCost += 15.00;
    if (distance > 800) tollCost += 20.00;
    
    return tollCost;
}

// Exibir detalhes da rota
function displayRouteDetails(routeInfo, distance, fuelLiters, fuelConsumption) {
    const detailsDiv = document.getElementById('routeDetails');
    detailsDiv.innerHTML = '';
    
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
