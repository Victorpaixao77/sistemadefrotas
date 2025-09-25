/**
 * Google Maps Integration
 * Sistema de Gestão de Frotas
 */

class GoogleMapsManager {
    constructor() {
        this.map = null;
        this.markers = [];
        this.apiKey = null;
        this.isLoaded = false;
    }

    /**
     * Inicializa o Google Maps com a chave da API
     */
    async init(apiKey) {
        if (!apiKey) {
            throw new Error('Chave da API do Google Maps é obrigatória');
        }

        this.apiKey = apiKey;

        // Carregar o script do Google Maps se ainda não foi carregado
        if (!window.google || !window.google.maps) {
            await this.loadGoogleMapsScript();
        }

        this.isLoaded = true;
    }

    /**
     * Carrega o script do Google Maps dinamicamente
     */
    loadGoogleMapsScript() {
        return new Promise((resolve, reject) => {
            // Verificar se já existe
            if (window.google && window.google.maps) {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${this.apiKey}&libraries=places,geometry&loading=async`;
            script.async = true;
            script.defer = true;
            
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Erro ao carregar Google Maps'));
            
            document.head.appendChild(script);
        });
    }

    /**
     * Cria um mapa em um elemento específico
     */
    createMap(elementId, options = {}) {
        if (!this.isLoaded) {
            throw new Error('Google Maps não foi inicializado');
        }

        // Verificar se o Google Maps está realmente carregado
        if (!window.google || !window.google.maps || !window.google.maps.Map) {
            throw new Error('Google Maps API não está carregada. Aguarde o carregamento completo.');
        }

        const element = document.getElementById(elementId);
        if (!element) {
            throw new Error(`Elemento com ID '${elementId}' não encontrado`);
        }

        const defaultOptions = {
            zoom: 10,
            center: { lat: -23.5505, lng: -46.6333 }, // São Paulo
            mapTypeId: 'roadmap' // Usar string em vez de google.maps.MapTypeId
        };

        const mapOptions = { ...defaultOptions, ...options };
        this.map = new google.maps.Map(element, mapOptions);

        return this.map;
    }

    /**
     * Adiciona um marcador no mapa
     */
    addMarker(position, options = {}) {
        if (!this.map) {
            throw new Error('Mapa não foi criado');
        }

        // Usar Marker tradicional (mais compatível com ícones customizados)
        const defaultOptions = {
            position: position,
            map: this.map,
            title: 'Marcador'
        };

        const markerOptions = { ...defaultOptions, ...options };
        const marker = new google.maps.Marker(markerOptions);
        
        this.markers.push(marker);
        return marker;
    }

    /**
     * Adiciona um marcador avançado (sem ícone customizado)
     */
    addAdvancedMarker(position, options = {}) {
        if (!this.map) {
            throw new Error('Mapa não foi criado');
        }

        // Verificar se AdvancedMarkerElement está disponível
        if (window.google && window.google.maps && window.google.maps.marker && window.google.maps.marker.AdvancedMarkerElement) {
            const markerOptions = {
                position: position,
                map: this.map,
                title: options.title || 'Marcador',
                ...options
            };
            
            const marker = new google.maps.marker.AdvancedMarkerElement(markerOptions);
            this.markers.push(marker);
            return marker;
        } else {
            // Fallback para Marker tradicional
            return this.addMarker(position, options);
        }
    }

    /**
     * Remove todos os marcadores
     */
    clearMarkers() {
        this.markers.forEach(marker => marker.setMap(null));
        this.markers = [];
    }

    /**
     * Centraliza o mapa em uma posição específica
     */
    setCenter(position, zoom = null) {
        if (!this.map) {
            throw new Error('Mapa não foi criado');
        }

        this.map.setCenter(position);
        if (zoom !== null) {
            this.map.setZoom(zoom);
        }
    }

    /**
     * Busca coordenadas de um endereço (Geocoding)
     */
    async geocodeAddress(address) {
        if (!this.isLoaded) {
            throw new Error('Google Maps não foi inicializado');
        }

        const geocoder = new google.maps.Geocoder();
        
        return new Promise((resolve, reject) => {
            geocoder.geocode({ address: address }, (results, status) => {
                if (status === 'OK' && results[0]) {
                    const location = results[0].geometry.location;
                    resolve({
                        lat: location.lat(),
                        lng: location.lng(),
                        formatted_address: results[0].formatted_address
                    });
                } else {
                    reject(new Error(`Geocoding falhou: ${status}`));
                }
            });
        });
    }

    /**
     * Busca endereço de coordenadas (Reverse Geocoding)
     */
    async reverseGeocode(lat, lng) {
        if (!this.isLoaded) {
            throw new Error('Google Maps não foi inicializado');
        }

        const geocoder = new google.maps.Geocoder();
        const latlng = { lat: parseFloat(lat), lng: parseFloat(lng) };
        
        return new Promise((resolve, reject) => {
            geocoder.geocode({ location: latlng }, (results, status) => {
                if (status === 'OK' && results[0]) {
                    resolve({
                        formatted_address: results[0].formatted_address,
                        lat: latlng.lat,
                        lng: latlng.lng
                    });
                } else {
                    reject(new Error(`Reverse Geocoding falhou: ${status}`));
                }
            });
        });
    }

    /**
     * Calcula distância entre dois pontos
     */
    calculateDistance(point1, point2) {
        if (!this.isLoaded) {
            throw new Error('Google Maps não foi inicializado');
        }

        const R = 6371; // Raio da Terra em km
        const dLat = this.toRad(point2.lat - point1.lat);
        const dLon = this.toRad(point2.lng - point1.lng);
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(this.toRad(point1.lat)) * Math.cos(this.toRad(point2.lat)) *
                Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        const distance = R * c;
        
        return distance;
    }

    /**
     * Converte graus para radianos
     */
    toRad(deg) {
        return deg * (Math.PI/180);
    }

    /**
     * Cria um campo de busca de endereços (Autocomplete)
     */
    createAutocomplete(inputElementId, options = {}) {
        if (!this.isLoaded) {
            throw new Error('Google Maps não foi inicializado');
        }

        const input = document.getElementById(inputElementId);
        if (!input) {
            throw new Error(`Elemento com ID '${inputElementId}' não encontrado`);
        }

        const defaultOptions = {
            types: ['address'],
            componentRestrictions: { country: 'br' }
        };

        const autocompleteOptions = { ...defaultOptions, ...options };
        const autocomplete = new google.maps.places.Autocomplete(input, autocompleteOptions);

        return autocomplete;
    }

    /**
     * Desenha uma rota entre dois pontos
     */
    async drawRoute(origin, destination, options = {}) {
        if (!this.map) {
            throw new Error('Mapa não foi criado');
        }

        const directionsService = new google.maps.DirectionsService();
        const directionsRenderer = new google.maps.DirectionsRenderer();

        const request = {
            origin: origin,
            destination: destination,
            travelMode: google.maps.TravelMode.DRIVING,
            ...options
        };

        return new Promise((resolve, reject) => {
            directionsService.route(request, (result, status) => {
                if (status === 'OK') {
                    directionsRenderer.setDirections(result);
                    directionsRenderer.setMap(this.map);
                    resolve(result);
                } else {
                    reject(new Error(`Erro ao calcular rota: ${status}`));
                }
            });
        });
    }
}

// Instância global do Google Maps Manager
window.googleMapsManager = new GoogleMapsManager();

// Função utilitária para obter a chave da API
async function getGoogleMapsApiKey() {
    try {
        const response = await fetch('../google-maps/api.php?action=get_config');
        const data = await response.json();
        
        if (data.success && data.data.google_maps_api_key) {
            return data.data.google_maps_api_key;
        }
        throw new Error('Chave da API não configurada');
    } catch (error) {
        console.error('Erro ao obter chave da API:', error);
        throw error;
    }
}

// Função utilitária para inicializar o Google Maps automaticamente
async function initGoogleMaps(elementId, options = {}) {
    try {
        const apiKey = await getGoogleMapsApiKey();
        await window.googleMapsManager.init(apiKey);
        return window.googleMapsManager.createMap(elementId, options);
    } catch (error) {
        console.error('Erro ao inicializar Google Maps:', error);
        throw error;
    }
}
