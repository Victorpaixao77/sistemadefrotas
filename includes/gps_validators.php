<?php
/**
 * Validação de coordenadas GPS na entrada (evita emulador / local de teste no mapa).
 *
 * Variáveis de ambiente:
 * - SF_GPS_BLOQUEAR_MOCK — não "1" (padrão): bloqueia região típica do emulador Android (Google).
 *   Defina "0" para desativar (ex.: testes em Mountain View).
 * - SF_GPS_MOCK_RAIO_METROS — raio em metros ao redor do padrão do emulador (padrão 4000).
 * - SF_GPS_APENAS_BRASIL — "1" para recusar pontos fora do retângulo aproximado do Brasil.
 */

require_once __DIR__ . '/gps_geofence.php';

if (!function_exists('gps_validar_coordenacao_entrada')) {
    /**
     * @throws InvalidArgumentException
     */
    function gps_validar_coordenacao_entrada(float $latitude, float $longitude): void
    {
        if (getenv('SF_GPS_BLOQUEAR_MOCK') !== '0') {
            if (gps_coordenada_parece_mock_emulador($latitude, $longitude)) {
                throw new InvalidArgumentException(
                    'Coordenadas recusadas: posição típica de emulador/dispositivo de teste (GPS fictício). '
                    . 'No Android: desative "Local fictício" ou use um aparelho real. '
                    . 'No servidor: SF_GPS_BLOQUEAR_MOCK=0 apenas para testes.'
                );
            }
        }

        if (getenv('SF_GPS_APENAS_BRASIL') === '1') {
            if (!gps_coordenada_retangulo_brasil($latitude, $longitude)) {
                throw new InvalidArgumentException(
                    'Coordenadas fora do território brasileiro (SF_GPS_APENAS_BRASIL=1).'
                );
            }
        }
    }
}

if (!function_exists('gps_coordenada_parece_mock_emulador')) {
    /**
     * Padrão clássico do Android Emulator (Google) e variações próximas (~km).
     * Ver também: 37.424802, -122.077895 (mesma região).
     */
    function gps_coordenada_parece_mock_emulador(float $lat, float $lng): bool
    {
        $raio = (float) (getenv('SF_GPS_MOCK_RAIO_METROS') ?: 4000);
        if ($raio <= 0) {
            $raio = 4000;
        }

        // Emulador Android padrão (região Mountain View / amostras Google); ~4 km cobre o jitter entre pontos de teste.
        $mockLat = 37.4219983;
        $mockLng = -122.084;
        if (gps_haversine_metros($lat, $lng, $mockLat, $mockLng) <= $raio) {
            return true;
        }

        // "Null Island" (0,0) — comum em falha de GPS
        if (gps_haversine_metros($lat, $lng, 0.0, 0.0) <= 400) {
            return true;
        }

        return false;
    }
}

if (!function_exists('gps_coordenada_retangulo_brasil')) {
    function gps_coordenada_retangulo_brasil(float $lat, float $lng): bool
    {
        return $lat >= -33.85 && $lat <= 5.35 && $lng >= -74.0 && $lng <= -34.7;
    }
}
