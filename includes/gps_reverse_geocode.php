<?php
/**
 * Reverse geocoding com cache em gps_geocode_cache.
 * SF_GPS_REVERSE_GEOCODE=1 para ativar (Nominatim — respeite política de uso).
 */

if (!function_exists('gps_reverse_geocode_endereco')) {
    function gps_reverse_geocode_endereco(PDO $conn, float $latitude, float $longitude): ?string
    {
        if (getenv('SF_GPS_REVERSE_GEOCODE') !== '1') {
            return null;
        }

        $latK = round($latitude, 4);
        $lngK = round($longitude, 4);

        try {
            $st = $conn->prepare('
                SELECT endereco FROM gps_geocode_cache
                WHERE lat_key = :lat AND lng_key = :lng LIMIT 1
            ');
            $st->execute([':lat' => $latK, ':lng' => $lngK]);
            $hit = $st->fetchColumn();
            if (is_string($hit) && $hit !== '') {
                return mb_substr($hit, 0, 255);
            }
        } catch (PDOException $e) {
            return null;
        }

        $url = sprintf(
            'https://nominatim.openstreetmap.org/reverse?format=json&lat=%s&lon=%s&accept-language=pt-BR',
            rawurlencode((string) $latK),
            rawurlencode((string) $lngK)
        );

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 6,
                'header' => "User-Agent: SistemaFrotas-GPS/1.0 (fleet management; contact: admin)\r\n",
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $j = json_decode($raw, true);
        if (!is_array($j)) {
            return null;
        }

        $addr = $j['display_name'] ?? $j['name'] ?? null;
        if (!is_string($addr) || $addr === '') {
            return null;
        }
        $addr = mb_substr(trim($addr), 0, 255);

        try {
            $ins = $conn->prepare('
                INSERT INTO gps_geocode_cache (lat_key, lng_key, endereco, atualizado_em)
                VALUES (:lat, :lng, :end, NOW())
                ON DUPLICATE KEY UPDATE endereco = VALUES(endereco), atualizado_em = NOW()
            ');
            $ins->execute([':lat' => $latK, ':lng' => $lngK, ':end' => $addr]);
        } catch (PDOException $e) {
            // tabela ausente: ignora cache
        }

        return $addr;
    }
}
