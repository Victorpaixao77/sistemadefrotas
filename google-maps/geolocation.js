/**
 * Geolocation Manager - Gerenciador de Geolocalização
 * Sistema de Gestão de Frotas
 */

class GeolocationManager {
    constructor() {
        this.currentPosition = null;
        this.watchId = null;
        this.isWatching = false;
    }

    /**
     * Obtém a posição atual do usuário
     */
    async getCurrentPosition(options = {}) {
        if (!navigator.geolocation) {
            throw new Error('Geolocalização não é suportada por este navegador');
        }

        const defaultOptions = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 300000 // 5 minutos
        };

        const geolocationOptions = { ...defaultOptions, ...options };

        return new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.currentPosition = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                        accuracy: position.coords.accuracy,
                        timestamp: position.timestamp
                    };
                    resolve(this.currentPosition);
                },
                (error) => {
                    let errorMessage;
                    switch (error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage = 'Permissão de localização negada pelo usuário';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage = 'Informações de localização indisponíveis';
                            break;
                        case error.TIMEOUT:
                            errorMessage = 'Tempo limite para obter localização excedido';
                            break;
                        default:
                            errorMessage = 'Erro desconhecido ao obter localização';
                            break;
                    }
                    reject(new Error(errorMessage));
                },
                geolocationOptions
            );
        });
    }

    /**
     * Inicia o monitoramento contínuo da posição
     */
    startWatching(callback, options = {}) {
        if (!navigator.geolocation) {
            throw new Error('Geolocalização não é suportada por este navegador');
        }

        if (this.isWatching) {
            this.stopWatching();
        }

        const defaultOptions = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 30000 // 30 segundos
        };

        const geolocationOptions = { ...defaultOptions, ...options };

        this.watchId = navigator.geolocation.watchPosition(
            (position) => {
                this.currentPosition = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                    accuracy: position.coords.accuracy,
                    timestamp: position.timestamp
                };
                callback(this.currentPosition);
            },
            (error) => {
                console.error('Erro no monitoramento de localização:', error);
                callback(null, error);
            },
            geolocationOptions
        );

        this.isWatching = true;
    }

    /**
     * Para o monitoramento contínuo da posição
     */
    stopWatching() {
        if (this.watchId !== null) {
            navigator.geolocation.clearWatch(this.watchId);
            this.watchId = null;
            this.isWatching = false;
        }
    }

    /**
     * Verifica se a geolocalização está disponível
     */
    isAvailable() {
        return 'geolocation' in navigator;
    }

    /**
     * Solicita permissão de geolocalização
     */
    async requestPermission() {
        if (!this.isAvailable()) {
            throw new Error('Geolocalização não é suportada por este navegador');
        }

        try {
            await this.getCurrentPosition();
            return true;
        } catch (error) {
            if (error.message.includes('Permissão de localização negada')) {
                return false;
            }
            throw error;
        }
    }

    /**
     * Calcula a distância entre a posição atual e um ponto específico
     */
    calculateDistanceToPoint(targetLat, targetLng) {
        if (!this.currentPosition) {
            throw new Error('Posição atual não disponível');
        }

        return this.calculateDistance(
            this.currentPosition.lat,
            this.currentPosition.lng,
            targetLat,
            targetLng
        );
    }

    /**
     * Calcula a distância entre dois pontos usando a fórmula de Haversine
     */
    calculateDistance(lat1, lng1, lat2, lng2) {
        const R = 6371; // Raio da Terra em km
        const dLat = this.toRad(lat2 - lat1);
        const dLng = this.toRad(lng2 - lng1);
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(this.toRad(lat1)) * Math.cos(this.toRad(lat2)) *
                Math.sin(dLng/2) * Math.sin(dLng/2);
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
     * Obtém a posição atual (se já foi obtida)
     */
    getCurrentPositionSync() {
        return this.currentPosition;
    }

    /**
     * Verifica se a posição atual está dentro de um raio específico de um ponto
     */
    isWithinRadius(targetLat, targetLng, radiusKm) {
        if (!this.currentPosition) {
            return false;
        }

        const distance = this.calculateDistanceToPoint(targetLat, targetLng);
        return distance <= radiusKm;
    }

    /**
     * Obtém informações detalhadas da posição atual
     */
    getPositionInfo() {
        if (!this.currentPosition) {
            return null;
        }

        return {
            ...this.currentPosition,
            isWatching: this.isWatching,
            accuracyText: this.getAccuracyText(this.currentPosition.accuracy)
        };
    }

    /**
     * Retorna uma descrição textual da precisão
     */
    getAccuracyText(accuracy) {
        if (accuracy <= 10) {
            return 'Muito alta';
        } else if (accuracy <= 50) {
            return 'Alta';
        } else if (accuracy <= 100) {
            return 'Média';
        } else if (accuracy <= 500) {
            return 'Baixa';
        } else {
            return 'Muito baixa';
        }
    }
}

// Instância global do Geolocation Manager
window.geolocationManager = new GeolocationManager();
