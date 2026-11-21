<?php
declare(strict_types=1);

/**
 * Geocoding com cache em arquivo (Nominatim OSM) + fallback estático.
 */
function adm_geocode_cache_path(): string
{
    $dir = dirname(__DIR__, 2) . '/cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir . '/adm_geo_cache.json';
}

/** @return array{lat:float,lng:float,fonte:string}|null */
function adm_geocode_load_cache(string $cidade, string $estado): ?array
{
    $path = adm_geocode_cache_path();
    if (!is_readable($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return null;
    }
    $key = mb_strtolower(trim($cidade) . '|' . strtoupper(trim($estado)), 'UTF-8');
    if (!isset($data[$key]) || !is_array($data[$key])) {
        return null;
    }
    return [
        'lat' => (float) $data[$key]['lat'],
        'lng' => (float) $data[$key]['lng'],
        'fonte' => (string) ($data[$key]['fonte'] ?? 'cache'),
    ];
}

function adm_geocode_save_cache(string $cidade, string $estado, float $lat, float $lng, string $fonte = 'nominatim'): void
{
    $path = adm_geocode_cache_path();
    $data = [];
    if (is_readable($path)) {
        $decoded = json_decode((string) file_get_contents($path), true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }
    $key = mb_strtolower(trim($cidade) . '|' . strtoupper(trim($estado)), 'UTF-8');
    $data[$key] = ['lat' => $lat, 'lng' => $lng, 'fonte' => $fonte, 'ts' => time()];
    @file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE));
}

/** @return array<string, array{0:float,1:float}> */
function adm_geocode_static_coords(): array
{
    return [
        'São Paulo' => [-23.55, -46.63],
        'Rio de Janeiro' => [-22.90, -43.17],
        'Belo Horizonte' => [-19.92, -43.94],
        'Curitiba' => [-25.43, -49.27],
        'Brasília' => [-15.79, -47.88],
        'Porto Alegre' => [-30.03, -51.23],
        'Salvador' => [-12.97, -38.51],
        'Fortaleza' => [-3.73, -38.52],
        'Recife' => [-8.05, -34.87],
        'Manaus' => [-3.10, -60.02],
        'Goiânia' => [-16.68, -49.25],
        'Campinas' => [-22.90, -47.06],
    ];
}

/** @return array<string, array{0:float,1:float}> */
function adm_geocode_estado_coords(): array
{
    return [
        'SP' => [-23.55, -46.63], 'RJ' => [-22.90, -43.17], 'MG' => [-19.92, -43.94],
        'PR' => [-25.43, -49.27], 'DF' => [-15.79, -47.88], 'RS' => [-30.03, -51.23],
        'BA' => [-12.97, -38.51], 'CE' => [-3.73, -38.52], 'PE' => [-8.05, -34.87],
        'AM' => [-3.10, -60.02], 'GO' => [-16.68, -49.25],
    ];
}

/** @return array{lat:float,lng:float,fonte:string} */
function adm_geocode_resolve(string $cidade, string $estado): array
{
    $cidade = trim($cidade);
    $estado = strtoupper(trim($estado));
    $default = ['lat' => -15.78, 'lng' => -47.93, 'fonte' => 'default'];

    if ($cidade !== '') {
        $cached = adm_geocode_load_cache($cidade, $estado);
        if ($cached) {
            return $cached;
        }
    }

    foreach (adm_geocode_static_coords() as $nome => $c) {
        if ($cidade !== '' && stripos($cidade, $nome) !== false) {
            return ['lat' => $c[0], 'lng' => $c[1], 'fonte' => 'static'];
        }
    }
    if ($estado !== '' && isset(adm_geocode_estado_coords()[$estado])) {
        $c = adm_geocode_estado_coords()[$estado];
        return ['lat' => $c[0], 'lng' => $c[1], 'fonte' => 'estado'];
    }

    if ($cidade === '') {
        return $default;
    }

    $q = $cidade . ($estado !== '' ? ', ' . $estado . ', Brasil' : ', Brasil');
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $q,
        'format' => 'json',
        'limit' => 1,
    ]);
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 4,
            'header' => "User-Agent: FrotecControlCenter/1.0 (contact@frotec.com.br)\r\n",
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw !== false) {
        $rows = json_decode($raw, true);
        if (is_array($rows) && isset($rows[0]['lat'], $rows[0]['lon'])) {
            $lat = (float) $rows[0]['lat'];
            $lng = (float) $rows[0]['lon'];
            adm_geocode_save_cache($cidade, $estado, $lat, $lng);
            return ['lat' => $lat, 'lng' => $lng, 'fonte' => 'nominatim'];
        }
    }

    return $default;
}
