/**
 * Route Manager - Gerenciador de Rotas com Google Maps
 * Sistema de Gestão de Frotas
 */

class RouteManager {
    constructor() {
        this.map = null;
        this.directionsService = null;
        this.directionsRenderer = null;
        this.routeMarkers = [];
        this.routePolyline = null;
        this.routePolylines = [];
        this.currentRoute = null; // Armazenar rota atual
    }

    /**
     * Inicializa o gerenciador de rotas
     */
    init(map) {
        this.map = map;
        this.directionsService = new google.maps.DirectionsService();
        this.directionsRenderer = new google.maps.DirectionsRenderer({
            suppressMarkers: true // Usar marcadores customizados
        });
        this.directionsRenderer.setMap(map);
    }

    /**
     * Calcula e desenha uma rota
     */
    async calculateRoute(origin, destination, options = {}) {
        if (!this.directionsService) {
            throw new Error('RouteManager não foi inicializado');
        }

        // Separar opções de estilo das opções de rota
        const { polylineOptions, ...routeOptions } = options;
        
        const request = {
            origin: origin,
            destination: destination,
            travelMode: google.maps.TravelMode.DRIVING,
            avoidHighways: false,
            avoidTolls: false,
            ...routeOptions
        };

        try {
            const result = await new Promise((resolve, reject) => {
                this.directionsService.route(request, (result, status) => {
                    if (status === 'OK') {
                        resolve(result);
                    } else if (status === 'ZERO_RESULTS') {
                        // Tratar ZERO_RESULTS graciosamente
                        console.warn(`Nenhuma rota encontrada entre ${origin} e ${destination}`);
                        resolve(null);
                    } else {
                        reject(new Error(`Erro ao calcular rota: ${status}`));
                    }
                });
            });

            if (result) {
                console.log('Rota calculada com sucesso, armazenando...');
                console.log('Result:', result);
                
                // Armazenar rota atual para getRouteInfo()
                this.currentRoute = result;
                console.log('Rota armazenada em currentRoute');
                
                // Não usar DirectionsRenderer para múltiplas rotas
                // this.directionsRenderer.setDirections(result);
                // this.addRouteMarkers(result);
                
                // Aplicar estilo da polilinha se fornecido
                if (polylineOptions) {
                    this.applyPolylineStyle(polylineOptions);
                }
            }
            
            return result;
        } catch (error) {
            console.error('Erro ao calcular rota:', error);
            throw error;
        }
    }

    /**
     * Calcula múltiplas rotas e desenha como polilinhas
     */
    async calculateMultipleRoutes(routes, options = {}) {
        if (!this.map) {
            throw new Error('Mapa não foi inicializado');
        }

        // Limpar rotas anteriores
        this.clearRoutePolylines();
        
        const results = [];
        const colors = ['#e6194b', '#4363d8', '#f58231', '#ffe119', '#3cb44b', '#46f0f0', '#f032e6', '#800000', '#808000', '#000075'];
        
        for (let i = 0; i < routes.length; i++) {
            const route = routes[i];
            const color = colors[i % colors.length];
            
            try {
                const result = await this.calculateRoute(route.origin, route.destination, {
                    ...options,
                    polylineOptions: {
                        strokeColor: color,
                        strokeWeight: 4,
                        strokeOpacity: 0.8
                    }
                });
                
                if (result) {
                    // Desenhar como polilinha customizada
                    this.drawRoutePolyline(result, color);
                    results.push(result);
                }
            } catch (error) {
                console.warn(`Erro ao calcular rota ${i + 1}:`, error.message);
            }
        }
        
        return results;
    }

    /**
     * Desenha uma rota como polilinha customizada
     */
    drawRoutePolyline(routeResult, color = '#4285F4') {
        if (!routeResult || !routeResult.routes || routeResult.routes.length === 0) {
            return;
        }

        const route = routeResult.routes[0];
        const path = route.overview_path;
        
        const polyline = new google.maps.Polyline({
            path: path,
            geodesic: true,
            strokeColor: color,
            strokeOpacity: 0.8,
            strokeWeight: 4,
            map: this.map
        });
        
        this.routePolylines.push(polyline);
    }

    /**
     * Adiciona marcadores customizados para origem e destino
     */
    addRouteMarkers(routeResult) {
        this.clearRouteMarkers();

        const route = routeResult.routes[0];
        const leg = route.legs[0];

        // Marcador de origem
        const originMarker = new google.maps.Marker({
            position: leg.start_location,
            map: this.map,
            title: 'Origem',
            icon: {
                url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                    <svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="16" cy="16" r="12" fill="#34A853" stroke="#fff" stroke-width="2"/>
                        <text x="16" y="20" text-anchor="middle" fill="white" font-family="Arial" font-size="12" font-weight="bold">A</text>
                    </svg>
                `),
                scaledSize: new google.maps.Size(32, 32),
                anchor: new google.maps.Point(16, 16)
            }
        });

        // Marcador de destino
        const destinationMarker = new google.maps.Marker({
            position: leg.end_location,
            map: this.map,
            title: 'Destino',
            icon: {
                url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                    <svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="16" cy="16" r="12" fill="#EA4335" stroke="#fff" stroke-width="2"/>
                        <text x="16" y="20" text-anchor="middle" fill="white" font-family="Arial" font-size="12" font-weight="bold">B</text>
                    </svg>
                `),
                scaledSize: new google.maps.Size(32, 32),
                anchor: new google.maps.Point(16, 16)
            }
        });

        this.routeMarkers.push(originMarker, destinationMarker);

        // Info windows
        const originInfoWindow = new google.maps.InfoWindow({
            content: `
                <div style="padding: 8px;">
                    <strong>Origem</strong><br>
                    ${leg.start_address}<br>
                    <small>Distância: ${leg.distance.text}</small>
                </div>
            `
        });

        const destinationInfoWindow = new google.maps.InfoWindow({
            content: `
                <div style="padding: 8px;">
                    <strong>Destino</strong><br>
                    ${leg.end_address}<br>
                    <small>Distância: ${leg.distance.text}</small>
                </div>
            `
        });

        originMarker.addListener('click', () => {
            originInfoWindow.open(this.map, originMarker);
        });

        destinationMarker.addListener('click', () => {
            destinationInfoWindow.open(this.map, destinationMarker);
        });
    }

    /**
     * Remove todos os marcadores de rota
     */
    clearRouteMarkers() {
        this.routeMarkers.forEach(marker => marker.setMap(null));
        this.routeMarkers = [];
    }

    /**
     * Remove todas as polilinhas de rota
     */
    clearRoutePolylines() {
        this.routePolylines.forEach(polyline => polyline.setMap(null));
        this.routePolylines = [];
    }

    /**
     * Limpa a rota atual
     */
    clearRoute() {
        this.directionsRenderer.setDirections({ routes: [] });
        this.clearRouteMarkers();
        this.clearRoutePolylines();
    }

    /**
     * Obtém informações da rota atual
     */
    getRouteInfo() {
        console.log('getRouteInfo chamado');
        console.log('currentRoute:', this.currentRoute);
        
        // Usar rota armazenada se disponível
        if (this.currentRoute && this.currentRoute.routes && this.currentRoute.routes.length > 0) {
            console.log('Usando rota armazenada');
            const route = this.currentRoute.routes[0];
            const leg = route.legs[0];

            const routeInfo = {
                distance: leg.distance,
                duration: leg.duration,
                start_address: leg.start_address,
                end_address: leg.end_address,
                steps: leg.steps
            };
            
            console.log('RouteInfo retornado:', routeInfo);
            return routeInfo;
        }

        // Fallback para directionsRenderer
        const directions = this.directionsRenderer.getDirections();
        if (!directions || !directions.routes.length) {
            return null;
        }

        const route = directions.routes[0];
        const leg = route.legs[0];

        return {
            distance: leg.distance,
            duration: leg.duration,
            start_address: leg.start_address,
            end_address: leg.end_address,
            steps: leg.steps.map(step => ({
                instruction: step.instructions,
                distance: step.distance,
                duration: step.duration
            }))
        };
    }

    /**
     * Adiciona pontos intermediários na rota
     */
    async calculateRouteWithWaypoints(origin, destination, waypoints, options = {}) {
        if (!this.directionsService) {
            throw new Error('RouteManager não foi inicializado');
        }

        const request = {
            origin: origin,
            destination: destination,
            waypoints: waypoints.map(wp => ({
                location: wp,
                stopover: true
            })),
            travelMode: google.maps.TravelMode.DRIVING,
            optimizeWaypoints: true,
            ...options
        };

        try {
            const result = await new Promise((resolve, reject) => {
                this.directionsService.route(request, (result, status) => {
                    if (status === 'OK') {
                        resolve(result);
                    } else {
                        reject(new Error(`Erro ao calcular rota com waypoints: ${status}`));
                    }
                });
            });

            this.directionsRenderer.setDirections(result);
            this.addRouteMarkersWithWaypoints(result);
            
            return result;
        } catch (error) {
            console.error('Erro ao calcular rota com waypoints:', error);
            throw error;
        }
    }

    /**
     * Adiciona marcadores para rota com waypoints
     */
    addRouteMarkersWithWaypoints(routeResult) {
        this.clearRouteMarkers();

        const route = routeResult.routes[0];
        const legs = route.legs;

        legs.forEach((leg, index) => {
            const isFirst = index === 0;
            const isLast = index === legs.length - 1;
            
            let marker;
            if (isFirst) {
                // Marcador de origem
                marker = new google.maps.Marker({
                    position: leg.start_location,
                    map: this.map,
                    title: 'Origem',
                    icon: {
                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                            <svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="16" cy="16" r="12" fill="#34A853" stroke="#fff" stroke-width="2"/>
                                <text x="16" y="20" text-anchor="middle" fill="white" font-family="Arial" font-size="12" font-weight="bold">A</text>
                            </svg>
                        `),
                        scaledSize: new google.maps.Size(32, 32),
                        anchor: new google.maps.Point(16, 16)
                    }
                });
            } else if (isLast) {
                // Marcador de destino
                marker = new google.maps.Marker({
                    position: leg.end_location,
                    map: this.map,
                    title: 'Destino',
                    icon: {
                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                            <svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="16" cy="16" r="12" fill="#EA4335" stroke="#fff" stroke-width="2"/>
                                <text x="16" y="20" text-anchor="middle" fill="white" font-family="Arial" font-size="12" font-weight="bold">B</text>
                            </svg>
                        `),
                        scaledSize: new google.maps.Size(32, 32),
                        anchor: new google.maps.Point(16, 16)
                    }
                });
            } else {
                // Marcador de waypoint
                marker = new google.maps.Marker({
                    position: leg.start_location,
                    map: this.map,
                    title: `Parada ${index}`,
                    icon: {
                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                            <svg width="24" height="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="10" fill="#FFC107" stroke="#fff" stroke-width="2"/>
                                <text x="12" y="16" text-anchor="middle" fill="white" font-family="Arial" font-size="10" font-weight="bold">${index}</text>
                            </svg>
                        `),
                        scaledSize: new google.maps.Size(24, 24),
                        anchor: new google.maps.Point(12, 12)
                    }
                });
            }

            this.routeMarkers.push(marker);
        });
    }

    /**
     * Aplica estilo personalizado à polilinha da rota
     */
    applyPolylineStyle(styleOptions = {}) {
        // O DirectionsRenderer não suporta polylineOptions diretamente
        // Mas podemos usar o estilo padrão que já é aplicado
        // Para estilos mais avançados, seria necessário usar Polyline diretamente
        if (styleOptions.strokeColor || styleOptions.strokeWeight || styleOptions.strokeOpacity) {
            console.log('Estilo da polilinha solicitado:', styleOptions);
            console.log('Nota: DirectionsRenderer usa estilo padrão. Para estilos customizados, use Polyline diretamente.');
        } else {
            console.log('Estilo da polilinha aplicado (padrão do DirectionsRenderer)');
        }
    }
}

// Instância global do Route Manager
window.routeManager = new RouteManager();
