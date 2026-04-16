// Função para redimensionar o canvas responsivamente
function resizeCanvas() {
    const canvas = document.getElementById('mapCanvas');
    const container = canvas.parentElement;
    const containerWidth = container.clientWidth;
    
    // Manter proporção 800x700
    const aspectRatio = 800 / 700;
    let newWidth = Math.min(containerWidth - 20, 800); // 20px de margem
    let newHeight = newWidth / aspectRatio;
    
    // Limitar altura máxima
    if (newHeight > 700) {
        newHeight = 700;
        newWidth = newHeight * aspectRatio;
    }
    
    // Aplicar novos tamanhos
    canvas.style.width = newWidth + 'px';
    canvas.style.height = newHeight + 'px';
    
    // Redesenhar o mapa se necessário
    if (typeof desenhaMapaComRotas === 'function') {
        desenhaMapaComRotas();
    }
}

// Redimensionar quando a janela mudar de tamanho
window.addEventListener('resize', resizeCanvas);

// Redimensionar quando a página carregar
window.addEventListener('load', resizeCanvas);

// Namespace para evitar conflitos
window.MapRoutes = window.MapRoutes || {};

// Verificar se as variáveis já foram declaradas
if (typeof window.MapRoutes.googleMap === 'undefined') {
    window.MapRoutes.googleMap = null;
}
if (typeof window.MapRoutes.googleMapManager === 'undefined') {
    window.MapRoutes.googleMapManager = null;
}
if (typeof window.MapRoutes.routeManager === 'undefined') {
    window.MapRoutes.routeManager = null;
}
if (typeof window.MapRoutes.isGoogleMapActive === 'undefined') {
    window.MapRoutes.isGoogleMapActive = false;
}
if (typeof window.MapRoutes.routesData === 'undefined') {
    window.MapRoutes.routesData = [];
}

// Usar as variáveis do namespace
let googleMap = window.MapRoutes.googleMap;
let googleMapManager = window.MapRoutes.googleMapManager;
let routeManager = window.MapRoutes.routeManager;
let isGoogleMapActive = window.MapRoutes.isGoogleMapActive;
let routesData = window.MapRoutes.routesData;

function formatDatePtBrLocal(dateString) {
    if (!dateString) return '-';
    // `YYYY-MM-DD` pode ser interpretado como UTC pelo JS → desloca dia.
    if (/^\d{4}-\d{2}-\d{2}$/.test(dateString)) {
        const [y, m, d] = dateString.split('-').map(Number);
        return new Date(y, m - 1, d).toLocaleDateString('pt-BR');
    }
    return new Date(dateString).toLocaleDateString('pt-BR');
}

// Função para mostrar o modal de ajuda
function showHelpModal() {
    const modal = document.getElementById('helpRouteModal');
    if (modal) {
        modal.style.display = 'block';
    }
}

// Função para fechar o modal
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}


function fecharModalMapa() {
    document.getElementById('modalMapaRotas').style.display = 'none';
}

function getColor(index) {
    const colors = [
        '#e6194b', '#3cb44b', '#ffe119', '#4363d8', '#f58231',
        '#911eb4', '#46f0f0', '#f032e6', '#bcf60c', '#fabebe',
        '#008080', '#e6beff', '#9a6324', '#fffac8', '#800000',
        '#aaffc3', '#808000', '#ffd8b1', '#000075', '#808080'
    ];
    return colors[index % colors.length];
}

let pointCount = {};
function getOffset(x, y) {
    const key = `${x}_${y}`;
    if (!pointCount[key]) pointCount[key] = 0;
    const offset = pointCount[key] * 10; // 10px de deslocamento por ponto sobreposto
    pointCount[key]++;
    return offset;
}

let pontosRotas = [];
function desenhaMapaComRotas() {
    const canvas = document.getElementById("mapCanvas");
    const ctx = canvas.getContext("2d");
    const img = new Image();
    img.src = window.__SF_ROUTES_MAP_IMG__ || '';

    img.onload = () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

        // Pega o mês/ano do filtro, ou usa o atual
        let mes, ano;
        const filtro = document.getElementById('filtroMesMapa');
        if (filtro && filtro.value) {
            [ano, mes] = filtro.value.split('-');
        } else {
            const data = new Date();
            mes = data.getMonth() + 1;
            ano = data.getFullYear();
        }

        fetch(sfApiUrl('rotas_mapa.php?mes=' + mes + '&ano=' + ano), { credentials: 'include' })
            .then(res => res.json())
            .then(rotas => {
                pontosRotas = [];
                pointCount = {};
                if (!Array.isArray(rotas)) {
                    console.error('Resposta inesperada da API:', rotas);
                    return;
                }
                rotas.forEach((r, idx) => {
                    const color = getColor(idx);

                    // Origem
                    let offsetO = getOffset(r.origem_x, r.origem_y);
                    ctx.beginPath();
                    ctx.arc(r.origem_x + offsetO, r.origem_y + offsetO, 8, 0, 2 * Math.PI);
                    ctx.fillStyle = color;
                    ctx.globalAlpha = 0.85;
                    ctx.fill();
                    ctx.globalAlpha = 1.0;

                    // Destino
                    let offsetD = getOffset(r.destino_x, r.destino_y);
                    ctx.beginPath();
                    ctx.arc(r.destino_x + offsetD, r.destino_y + offsetD, 8, 0, 2 * Math.PI);
                    ctx.fillStyle = color;
                    ctx.globalAlpha = 0.85;
                    ctx.fill();
                    ctx.globalAlpha = 1.0;

                    // Linha curva tracejada
                    ctx.save();
                    ctx.beginPath();
                    const mx = (r.origem_x + r.destino_x) / 2;
                    const my = (r.origem_y + r.destino_y) / 2 - 40;
                    ctx.setLineDash([8, 8]);
                    ctx.moveTo(r.origem_x + offsetO, r.origem_y + offsetO);
                    ctx.quadraticCurveTo(mx, my, r.destino_x + offsetD, r.destino_y + offsetD);
                    ctx.strokeStyle = color;
                    ctx.lineWidth = 2;
                    ctx.stroke();
                    ctx.setLineDash([]);
                    ctx.restore();

                    // Salva os pontos de origem e destino para hover
                    pontosRotas.push({
                        x: r.origem_x + offsetO,
                        y: r.origem_y + offsetO,
                        tipo: 'origem',
                        estado: r.estado_origem,
                        cidade: r.cidade_origem_nome,
                        estado_destino: r.estado_destino,
                        cidade_destino: r.cidade_destino_nome,
                        color: color
                    });
                    pontosRotas.push({
                        x: r.destino_x + offsetD,
                        y: r.destino_y + offsetD,
                        tipo: 'destino',
                        estado: r.estado_destino,
                        cidade: r.cidade_destino_nome,
                        estado_origem: r.estado_origem,
                        cidade_origem: r.cidade_origem_nome,
                        color: color
                    });
                });
            });
    };
}

// Evento de mousemove para mostrar o tooltip
const canvasEl = document.getElementById('mapCanvas');
canvasEl.addEventListener('mousemove', function(e) {
    const rect = this.getBoundingClientRect();
    const mouseX = e.clientX - rect.left;
    const mouseY = e.clientY - rect.top;
    let found = false;
    for (const p of pontosRotas) {
        if (Math.sqrt((mouseX - p.x) ** 2 + (mouseY - p.y) ** 2) < 12) {
            found = true;
            let html = '';
            if (p.tipo === 'origem') {
                html = `<strong>Origem</strong><br>Estado: ${p.estado}<br>Cidade: ${p.cidade}<br>` +
                       `<strong>Destino</strong><br>Estado: ${p.estado_destino}<br>Cidade: ${p.cidade_destino}`;
            } else {
                html = `<strong>Destino</strong><br>Estado: ${p.estado}<br>Cidade: ${p.cidade}<br>` +
                       `<strong>Origem</strong><br>Estado: ${p.estado_origem}<br>Cidade: ${p.cidade_origem}`;
            }
            const tooltip = document.getElementById('mapTooltip');
            tooltip.innerHTML = html;
            tooltip.style.display = 'block';
            tooltip.style.left = (e.clientX + 12) + 'px';
            tooltip.style.top = (e.clientY + 12) + 'px';
            tooltip.style.borderColor = p.color;
            break;
        }
    }
    if (!found) {
        const tooltip = document.getElementById('mapTooltip');
        tooltip.style.display = 'none';
        tooltip.style.borderColor = '';
    }
});
canvasEl.addEventListener('mouseleave', function() {
    document.getElementById('mapTooltip').style.display = 'none';
});

let modoCoordenadas = false;

document.getElementById('mapCanvas').addEventListener('click', function(e) {
    if (!modoCoordenadas) return;
    const rect = this.getBoundingClientRect();
    const x = Math.round(e.clientX - rect.left);
    const y = Math.round(e.clientY - rect.top);
    document.getElementById('coordenadaInfo').textContent = `Coordenada: X=${x} Y=${y} (copie e preencha na tabela)`;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(`${x},${y}`);
    }
});

// ===== GOOGLE MAPS INTEGRATION =====
function sfWaitForGoogleMapsReady(maxMs) {
    maxMs = maxMs || 8000;
    var start = Date.now();
    return new Promise(function(resolve, reject) {
        function tick() {
            if (window.google && window.google.maps && window.google.maps.Map) {
                return resolve();
            }
            if (Date.now() - start > maxMs) {
                return reject(new Error('Google Maps API não carregou completamente'));
            }
            requestAnimationFrame(tick);
        }
        tick();
    });
}

// Aguardar o DOM estar carregado
    document.addEventListener('DOMContentLoaded', function() {
    window.__SF_DEBUG__ && console.log('DOM carregado - Configurando event listeners...');
    
    requestAnimationFrame(function() {
        requestAnimationFrame(function() {
        const btnMapaRotas = document.getElementById('btnMapaRotas');
        if (btnMapaRotas) {
            window.__SF_DEBUG__ && console.log('Botão do mapa encontrado, configurando click...');
            btnMapaRotas.onclick = function() {
                window.__SF_DEBUG__ && console.log('Botão do mapa clicado!');
                document.getElementById('modalMapaRotas').style.display = 'flex';
                desenhaMapaComRotas();
            };
        } else {
            console.error('Botão do mapa não encontrado!');
        }

// Função para alternar entre mapas
        const btnAlternarMapa = document.getElementById('btnAlternarMapa');
        if (btnAlternarMapa) {
            window.__SF_DEBUG__ && console.log('Botão alternar mapa encontrado');
            btnAlternarMapa.addEventListener('click', function() {
                if (window.MapRoutes.isGoogleMapActive) {
        // Voltar para mapa Canvas
        document.getElementById('mapCanvas').style.display = 'block';
        document.getElementById('googleMap').style.display = 'none';
                    this.innerHTML = '<i class="fas fa-map-marked-alt"></i> Google Maps';
        this.style.background = '#1976d2';
                    window.MapRoutes.isGoogleMapActive = false;
        isGoogleMapActive = false;
    } else {
        // Usar Google Maps
        document.getElementById('mapCanvas').style.display = 'none';
        document.getElementById('googleMap').style.display = 'block';
                    this.innerHTML = '<i class="fas fa-map"></i> Mapa Canvas';
        this.style.background = '#4caf50';
                    window.MapRoutes.isGoogleMapActive = true;
        isGoogleMapActive = true;
        
        // Inicializar Google Maps se ainda não foi inicializado
                    if (!window.MapRoutes.googleMap) {
            initGoogleMapsForRoutes();
        } else {
            // Atualizar dados se já foi inicializado
            updateGoogleMapsWithRoutes();
        }
    }
});
        }

        // Função para limpar o mapa
        const btnLimparMapa = document.getElementById('btnLimparMapa');
        if (btnLimparMapa) {
            btnLimparMapa.addEventListener('click', function() {
                if (window.MapRoutes.isGoogleMapActive) {
                    // Limpar Google Maps
                    if (window.googleMapsManager) {
                        window.googleMapsManager.clearMarkers();
                    }
                    if (window.MapRoutes.routeManager) {
                        window.MapRoutes.routeManager.clearRoute();
                    }
                } else {
                    // Limpar Canvas
                    const canvas = document.getElementById('mapCanvas');
                    const ctx = canvas.getContext('2d');
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    
                    // Redesenhar apenas o mapa base
                    const img = new Image();
                    img.src = window.__SF_ROUTES_MAP_IMG__ || '';
                    img.onload = () => {
                        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                    };
                }
            });
        }

        // Modo coordenadas
        const btnModoCoordenadas = document.getElementById('btnModoCoordenadas');
        if (btnModoCoordenadas) {
            btnModoCoordenadas.addEventListener('click', function() {
                modoCoordenadas = !modoCoordenadas;
                this.style.background = modoCoordenadas ? '#1976d2' : '';
                this.style.color = modoCoordenadas ? '#fff' : '';
                document.getElementById('coordenadaInfo').textContent = modoCoordenadas ? 'Clique no mapa para capturar X/Y' : '';
            });
        }

        // Configurar botão de simular rota
        const btnSimularRota = document.getElementById('simulateRouteBtn');
        if (btnSimularRota) {
            window.__SF_DEBUG__ && console.log('Botão simular rota encontrado');
            btnSimularRota.addEventListener('click', function() {
                window.__SF_DEBUG__ && console.log('Abrindo simulador de rotas...');
                const modal = document.getElementById('routeSimulationModal');
                if (modal) {
                    modal.style.display = 'block';
                    window.__SF_DEBUG__ && console.log('Modal aberto');
                    initRouteSimulator();
                } else {
                    console.error('Modal não encontrado');
                }
            });
        } else {
            console.error('Botão simular rota não encontrado');
        }
        }); // fim rAF interno
        }); // fim rAF externo
}); // Fim do DOMContentLoaded

// Função para inicializar Google Maps
async function initGoogleMapsForRoutes() {
    try {
        // Obter chave da API
        const response = await fetch(sfAppUrl('google-maps/api.php?action=get_config'), {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (!data.success || !data.data.google_maps_api_key) {
            if (typeof showToast === 'function') {
                showToast('Chave da API do Google Maps não configurada. Configure em Configurações > Google Maps', 'error');
            }
            return;
        }

        // Inicializar Google Maps
        await window.googleMapsManager.init(data.data.google_maps_api_key);
        
        await sfWaitForGoogleMapsReady(8000);
        
        if (!window.google || !window.google.maps || !window.google.maps.Map) {
            throw new Error('Google Maps API não carregou completamente');
        }
        
        window.MapRoutes.googleMap = await window.googleMapsManager.createMap('googleMap', {
            zoom: 5,
            center: { lat: -14.2350, lng: -51.9253 }, // Centro do Brasil
            mapTypeId: 'roadmap' // Usar string em vez de google.maps.MapTypeId
        });
        googleMap = window.MapRoutes.googleMap;

        // Inicializar gerenciador de rotas
        window.MapRoutes.routeManager = new RouteManager();
        window.MapRoutes.routeManager.init(googleMap);
        routeManager = window.MapRoutes.routeManager;

        // Carregar dados das rotas
        await loadRoutesData();
        updateGoogleMapsWithRoutes();

    } catch (error) {
        console.error('Erro ao inicializar Google Maps:', error);
        if (typeof showToast === 'function') {
            showToast('Erro ao carregar Google Maps: ' + error.message, 'error');
        }
    }
}

// Função para carregar dados das rotas
async function loadRoutesData() {
    try {
        // Pega o mês/ano do filtro, ou usa o atual
        let mes, ano;
        const filtro = document.getElementById('filtroMesMapa');
        if (filtro && filtro.value) {
            [ano, mes] = filtro.value.split('-');
        } else {
            const data = new Date();
            mes = data.getMonth() + 1;
            ano = data.getFullYear();
        }

        const response = await fetch(sfApiUrl(`rotas_google_maps.php?mes=${mes}&ano=${ano}`), {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            window.MapRoutes.routesData = data.data;
            routesData = window.MapRoutes.routesData;
        } else {
            console.error('Erro ao carregar dados das rotas:', data.error);
            window.MapRoutes.routesData = [];
            routesData = window.MapRoutes.routesData;
        }
    } catch (error) {
        console.error('Erro ao carregar dados das rotas:', error);
        routesData = [];
    }
}

// Função para atualizar Google Maps com as rotas
function updateGoogleMapsWithRoutes() {
    if (!window.MapRoutes.googleMap || !window.MapRoutes.routesData.length) {
        window.__SF_DEBUG__ && console.log('Google Maps não inicializado ou sem dados de rotas');
        return;
    }

    // Limpar marcadores existentes
    window.googleMapsManager.clearMarkers();

    // Adicionar marcadores para cada rota
    window.__SF_DEBUG__ && console.log('Dados das rotas:', window.MapRoutes.routesData);
    window.MapRoutes.routesData.forEach((route, index) => {
        const color = getColor(index);
        window.__SF_DEBUG__ && console.log(`Processando rota ${index + 1}:`, route);
        
        // Marcador de origem
        if (route.origem.latitude && route.origem.longitude) {
            const originMarker = window.googleMapsManager.addMarker(
                { lat: route.origem.latitude, lng: route.origem.longitude },
                {
                    title: `Origem: ${route.origem.cidade}, ${route.origem.estado}`,
                    icon: {
                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                            <svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="16" cy="16" r="12" fill="${color}" stroke="#fff" stroke-width="2"/>
                                <text x="16" y="20" text-anchor="middle" fill="white" font-family="Arial" font-size="12" font-weight="bold">O</text>
                            </svg>
                        `),
                        scaledSize: new google.maps.Size(32, 32),
                        anchor: new google.maps.Point(16, 16)
                    }
                }
            );

            // Info window para origem
            const originInfoWindow = new google.maps.InfoWindow({
                content: `
                    <div style="padding: 12px; min-width: 250px; background: #f8f9fa; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                        <h4 style="margin: 0 0 12px 0; color: ${color}; font-size: 16px; font-weight: bold; border-bottom: 2px solid ${color}; padding-bottom: 8px;">📍 Origem</h4>
                        <div style="color: #333; line-height: 1.6;">
                            <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Cidade:</strong> ${route.origem.cidade || 'N/A'}, ${route.origem.estado || 'N/A'}</p>
                            <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Motorista:</strong> ${route.motorista.nome || 'N/A'}</p>
                            <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Veículo:</strong> ${route.veiculo.placa || 'N/A'}</p>
                            <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Data:</strong> ${route.data_rota ? formatDatePtBrLocal(route.data_rota) : 'N/A'}</p>
                            <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Distância:</strong> ${route.distancia_km || 0} km</p>
                            <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Frete:</strong> R$ ${route.frete ? route.frete.toFixed(2) : '0.00'}</p>
                            <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Status:</strong> <span style="color: ${route.no_prazo ? '#28a745' : '#dc3545'}; font-weight: bold;">${route.no_prazo ? 'No Prazo' : 'Atrasado'}</span></p>
                        </div>
                    </div>
                `
            });

            originMarker.addListener('click', () => {
                originInfoWindow.open(window.MapRoutes.googleMap, originMarker);
            });
        }

        // Marcador de destino
        if (route.destino.latitude && route.destino.longitude) {
            const destinationMarker = window.googleMapsManager.addMarker(
                { lat: route.destino.latitude, lng: route.destino.longitude },
                {
                    title: `Destino: ${route.destino.cidade}, ${route.destino.estado}`,
                    icon: {
                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                            <svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="16" cy="16" r="12" fill="${color}" stroke="#fff" stroke-width="2"/>
                                <text x="16" y="20" text-anchor="middle" fill="white" font-family="Arial" font-size="12" font-weight="bold">D</text>
                            </svg>
                        `),
                        scaledSize: new google.maps.Size(32, 32),
                        anchor: new google.maps.Point(16, 16)
                    }
                }
            );

            // Info window para destino
            const destinationInfoWindow = new google.maps.InfoWindow({
                content: `
                    <div style="padding: 12px; min-width: 250px; background: #f8f9fa; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                        <h4 style="margin: 0 0 12px 0; color: ${color}; font-size: 16px; font-weight: bold; border-bottom: 2px solid ${color}; padding-bottom: 8px;">🎯 Destino</h4>
                        <div style="color: #333; line-height: 1.6;">
                            <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Cidade:</strong> ${route.destino.cidade || 'N/A'}, ${route.destino.estado || 'N/A'}</p>
                            <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Motorista:</strong> ${route.motorista.nome || 'N/A'}</p>
                            <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Veículo:</strong> ${route.veiculo.placa || 'N/A'}</p>
                            <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Data:</strong> ${route.data_rota ? formatDatePtBrLocal(route.data_rota) : 'N/A'}</p>
                            <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Distância:</strong> ${route.distancia_km || 0} km</p>
                            <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Frete:</strong> R$ ${route.frete ? route.frete.toFixed(2) : '0.00'}</p>
                            <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Status:</strong> <span style="color: ${route.no_prazo ? '#28a745' : '#dc3545'}; font-weight: bold;">${route.no_prazo ? 'No Prazo' : 'Atrasado'}</span></p>
                        </div>
                    </div>
                `
            });

            destinationMarker.addListener('click', () => {
                destinationInfoWindow.open(window.MapRoutes.googleMap, destinationMarker);
            });
        }

        // Desenhar rota se ambos os pontos existirem
        if (route.origem.latitude && route.origem.longitude && route.destino.latitude && route.destino.longitude) {
            const origin = `${route.origem.latitude},${route.origem.longitude}`;
            const destination = `${route.destino.latitude},${route.destino.longitude}`;
            
            // Usar o RouteManager para desenhar a rota
            if (window.MapRoutes.routeManager) {
                window.MapRoutes.routeManager.calculateRoute(origin, destination, {
                    polylineOptions: {
                        strokeColor: color,
                        strokeWeight: 4,
                        strokeOpacity: 0.8
                    }
                })
                .then(result => {
                    if (result) {
                    window.__SF_DEBUG__ && console.log('Rota calculada:', result);
                        // Desenhar polilinha customizada
                        window.MapRoutes.routeManager.drawRoutePolyline(result, color);
                    } else {
                        console.warn(`Nenhuma rota encontrada entre ${origin} e ${destination}`);
                    }
                })
                .catch(error => {
                    console.warn('Erro ao calcular rota:', error.message);
                });
            }
        }
    });

    // Ajustar zoom para mostrar todas as rotas
    if (window.MapRoutes.routesData.length > 0) {
        const bounds = new google.maps.LatLngBounds();
        window.MapRoutes.routesData.forEach(route => {
            if (route.origem.latitude && route.origem.longitude) {
                bounds.extend(new google.maps.LatLng(route.origem.latitude, route.origem.longitude));
            }
            if (route.destino.latitude && route.destino.longitude) {
                bounds.extend(new google.maps.LatLng(route.destino.latitude, route.destino.longitude));
            }
        });
        window.MapRoutes.googleMap.fitBounds(bounds);
    }
}

// Função para atualizar estatísticas do mapa
function updateMapStats() {
    if (!window.MapRoutes.routesData || window.MapRoutes.routesData.length === 0) {
        document.getElementById('totalRotasMapa').textContent = '0';
        document.getElementById('totalKmMapa').textContent = '0';
        document.getElementById('totalFreteMapa').textContent = 'R$ 0,00';
        return;
    }

    const totalRotas = window.MapRoutes.routesData.length;
    const totalKm = window.MapRoutes.routesData.reduce((sum, route) => sum + (route.distancia_km || 0), 0);
    const totalFrete = window.MapRoutes.routesData.reduce((sum, route) => sum + (route.frete || 0), 0);

    document.getElementById('totalRotasMapa').textContent = totalRotas;
    document.getElementById('totalKmMapa').textContent = totalKm.toFixed(0);
    document.getElementById('totalFreteMapa').textContent = `R$ ${totalFrete.toFixed(2)}`;
}

// Modificar a função de filtrar para funcionar com ambos os mapas
const originalDesenhaMapaComRotas = desenhaMapaComRotas;
desenhaMapaComRotas = function() {
    if (window.MapRoutes.isGoogleMapActive) {
        // Se estiver usando Google Maps, recarregar dados
        loadRoutesData().then(() => {
            updateGoogleMapsWithRoutes();
            updateMapStats();
        });
    } else {
        // Usar função original do Canvas
        originalDesenhaMapaComRotas();
        // Atualizar estatísticas para o mapa Canvas também
        loadRoutesData().then(() => {
            updateMapStats();
        });
    }
};

// ===== VALIDAÇÃO DE QUILOMETRAGEM =====

// Função para validar KM Saída da rota
async function validarKmSaidaRota(veiculoId, kmSaida) {
    if (!veiculoId || !kmSaida) {
        return { valido: false, mensagem: 'Dados insuficientes para validação' };
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'validar_km_saida_rota');
        formData.append('veiculo_id', veiculoId);
        formData.append('km_saida', kmSaida);
        
        const response = await fetch(sfApiUrl('validar_quilometragem.php'), {
            method: 'POST',
            credentials: 'include',
            headers: typeof sfMutationHeaders === 'function' ? sfMutationHeaders() : {},
            body: formData
        });
        
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Erro na validação de quilometragem:', error);
        return { valido: false, mensagem: 'Erro na validação' };
    }
}

// Função para obter quilometragem atual do veículo
async function obterKmAtualVeiculo(veiculoId) {
    if (!veiculoId) return null;
    
    try {
        const response = await fetch(sfApiUrl('validar_quilometragem.php?action=obter_km_atual_veiculo&veiculo_id=' + encodeURIComponent(veiculoId)), { credentials: 'include' });
        const data = await response.json();
        return data.success ? data.km_atual : null;
    } catch (error) {
        console.error('Erro ao obter quilometragem do veículo:', error);
        return null;
    }
}

// Configurar validação quando veículo for selecionado
function configurarValidacaoKmSaida() {
    const veiculoSelect = document.getElementById('veiculo_id');
    const kmSaidaInput = document.getElementById('km_saida');
    const kmSaidaHelp = document.getElementById('km_saida_help');
    const kmSaidaValidation = document.getElementById('km_saida_validation');
    
    if (!veiculoSelect || !kmSaidaInput) return;
    
    // Quando veículo for selecionado
    veiculoSelect.addEventListener('change', async function() {
        const veiculoId = this.value;
        
        if (veiculoId) {
            const kmAtual = await obterKmAtualVeiculo(veiculoId);
            if (kmAtual !== null) {
                kmSaidaHelp.textContent = `Quilometragem atual do veículo: ${kmAtual.toLocaleString('pt-BR')} km`;
                kmSaidaInput.placeholder = `Mín: ${kmAtual.toLocaleString('pt-BR')}`;
                kmSaidaInput.min = kmAtual;
            }
        } else {
            kmSaidaHelp.textContent = 'Selecione veículo para valida KM';
            kmSaidaInput.placeholder = 'Ex: 150000';
            kmSaidaInput.min = '';
        }
        
        // Limpar validação anterior
        kmSaidaValidation.innerHTML = '';
    });
    
    // Quando KM Saída for digitado
    kmSaidaInput.addEventListener('blur', async function() {
        const veiculoId = veiculoSelect.value;
        const kmSaida = this.value;
        
        if (veiculoId && kmSaida) {
            const validacao = await validarKmSaidaRota(veiculoId, kmSaida);
            
            if (validacao.valido) {
                        kmSaidaValidation.innerHTML = `<div class="route-km-msg route-km-msg--ok"><i class="fas fa-check-circle"></i> ${validacao.mensagem}</div>`;
                kmSaidaInput.style.borderColor = '#28a745';
            } else {
                        kmSaidaValidation.innerHTML = `<div class="route-km-msg route-km-msg--err"><i class="fas fa-exclamation-triangle"></i> ${validacao.mensagem}</div>`;
                kmSaidaInput.style.borderColor = '#dc3545';
            }
        } else {
            kmSaidaValidation.innerHTML = '';
            kmSaidaInput.style.borderColor = '';
        }
    });
}

// Inicializar validação quando DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    requestAnimationFrame(function() {
        requestAnimationFrame(function() {
            configurarValidacaoKmSaida();
        });
    });
});
