<?php
/**
 * GET — mesmo histórico que historico.php, com foco em playback de mapa.
 * Query: veiculo_id, data_inicio, data_fim, limite
 * format=geojson → data.geojson = Feature com LineString (WGS84 lon,lat)
 * format=json (default) → mesmo pacote que historico + data.duration_hint_sec estimado
 */
require_once dirname(__DIR__, 2) . '/app_android/config.php';
require_once dirname(__DIR__, 2) . '/includes/gps_motorista_api_read.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    api_error('Método não permitido', 405);
}

require_motorista_token();
$empresa_id = (int) get_empresa_id();
$format = strtolower(trim((string) ($_GET['format'] ?? 'json')));

$veiculo_id = isset($_GET['veiculo_id']) ? (int) $_GET['veiculo_id'] : 0;
if ($veiculo_id <= 0) {
    api_error('Informe veiculo_id.', 400);
}

$limite = isset($_GET['limite']) ? (int) $_GET['limite'] : 2000;
$veiculo = null;

try {
    $conn = getConnection();
    $veiculo = gps_motorista_api_veiculo_empresa($conn, $veiculo_id, $empresa_id);
    if (!$veiculo) {
        api_error('Veículo não encontrado.', 404);
    }

    $inStr = isset($_GET['data_inicio']) ? trim((string) $_GET['data_inicio']) : '';
    $fimStr = isset($_GET['data_fim']) ? trim((string) $_GET['data_fim']) : '';

    $now = new DateTimeImmutable('now');
    try {
        if ($inStr === '' && $fimStr === '') {
            $dtFim = $now;
            $dtIni = $now->sub(new DateInterval('P1D'));
        } elseif ($inStr !== '' && $fimStr === '') {
            $dtIni = new DateTimeImmutable($inStr);
            $dtFim = $now;
        } elseif ($inStr === '' && $fimStr !== '') {
            $dtFim = new DateTimeImmutable($fimStr);
            $dtIni = $dtFim->sub(new DateInterval('P1D'));
        } else {
            $dtIni = new DateTimeImmutable($inStr);
            $dtFim = new DateTimeImmutable($fimStr);
        }
    } catch (Exception $e) {
        api_error('Datas inválidas. Use YYYY-MM-DD ou YYYY-MM-DD HH:MM:SS.', 400);
    }

    if ($dtIni > $dtFim) {
        $tmp = $dtIni;
        $dtIni = $dtFim;
        $dtFim = $tmp;
    }

    $maxSpan = new DateInterval('P7D');
    $spanLimit = $dtIni->add($maxSpan);
    if ($dtFim > $spanLimit) {
        $dtFim = $spanLimit;
    }

    $rows = gps_motorista_api_historico_pontos($conn, $empresa_id, $veiculo_id, $dtIni, $dtFim, $limite);

    $durationHint = null;
    if (count($rows) >= 2) {
        $t0 = strtotime($rows[0]['data_hora'] ?? '');
        $t1 = strtotime($rows[count($rows) - 1]['data_hora'] ?? '');
        if ($t0 !== false && $t1 !== false && $t1 >= $t0) {
            $durationHint = $t1 - $t0;
        }
    }

    $coords = [];
    foreach ($rows as $r) {
        if ($r['longitude'] === null || $r['latitude'] === null) {
            continue;
        }
        $coords[] = [(float) $r['longitude'], (float) $r['latitude']];
    }

    $geojson = [
        'type' => 'Feature',
        'geometry' => [
            'type' => 'LineString',
            'coordinates' => $coords,
        ],
        'properties' => [
            'veiculo_id' => $veiculo_id,
            'placa' => $veiculo['placa'],
            'pontos' => count($coords),
            'periodo' => [
                'data_inicio' => $dtIni->format('Y-m-d H:i:s'),
                'data_fim' => $dtFim->format('Y-m-d H:i:s'),
            ],
        ],
    ];

    $payload = [
        'pontos' => $rows,
        'placa' => $veiculo['placa'],
        'veiculo_id' => $veiculo_id,
        'total' => count($rows),
        'periodo' => [
            'data_inicio' => $dtIni->format('Y-m-d H:i:s'),
            'data_fim' => $dtFim->format('Y-m-d H:i:s'),
        ],
        'duration_hint_sec' => $durationHint,
    ];

    if ($format === 'geojson') {
        $payload['geojson'] = $geojson;
    }

    api_success($payload);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'gps_logs') !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
        $empty = [
            'pontos' => [],
            'placa' => is_array($veiculo) ? ($veiculo['placa'] ?? '') : '',
            'veiculo_id' => $veiculo_id,
            'total' => 0,
            'periodo' => [],
            'duration_hint_sec' => null,
        ];
        if ($format === 'geojson') {
            $empty['geojson'] = [
                'type' => 'Feature',
                'geometry' => ['type' => 'LineString', 'coordinates' => []],
                'properties' => ['veiculo_id' => $veiculo_id, 'placa' => $empty['placa'], 'pontos' => 0],
            ];
        }
        api_success($empty);
    }
    error_log('gps track_playback: ' . $e->getMessage());
    api_error('Erro ao carregar trilha.', 500);
}
