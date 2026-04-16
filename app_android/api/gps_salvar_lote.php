<?php
/**
 * POST JSON — lote de pontos GPS (até 25). Body: { "pontos": [ { veiculo_id, latitude, longitude, ... }, ... ] }
 * Campos por item iguais a gps_salvar.php; veiculo_id pode ser omitido se único no lote (usa o primeiro).
 */

require_once __DIR__ . '/../config.php';
require_once dirname(__DIR__, 2) . '/includes/rate_limit.php';
require_once dirname(__DIR__, 2) . '/includes/gps_motorista_save.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_error('Método não permitido', 405);
}

require_motorista_token();
$empresa_id = (int) get_empresa_id();
$motorista_id = (int) get_motorista_id();

$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '', true);
if (!is_array($input)) {
    api_error('JSON inválido.', 400);
}

$pontos = $input['pontos'] ?? $input['points'] ?? null;
if (!is_array($pontos) || $pontos === []) {
    api_error('Informe pontos (array "pontos").', 400);
}

$max = (int) (getenv('SF_GPS_LOTE_MAX') ?: 25);
if ($max < 1) {
    $max = 25;
}
if (count($pontos) > $max) {
    api_error('Máximo de ' . $max . ' pontos por lote.', 400);
}

$rlMax = (int) (getenv('SF_GPS_RATE_LIMIT_LOTE_MAX') ?: 30);
$rlWin = (int) (getenv('SF_GPS_RATE_LIMIT_WINDOW') ?: 60);
if (!sf_rate_limit_allow_custom('gps_lote_m' . $motorista_id, $rlMax, $rlWin)) {
    api_error('Muitas requisições GPS (lote). Aguarde.', 429);
}

$defaultVid = 0;
foreach ($pontos as $p) {
    if (is_array($p) && !empty($p['veiculo_id'])) {
        $defaultVid = (int) $p['veiculo_id'];
        break;
    }
}

function gps_salvar_lote_parse_item(array $p, int $motorista_id, int $defaultVid): ?array
{
    $veiculo_id = isset($p['veiculo_id']) ? (int) $p['veiculo_id'] : $defaultVid;
    $lat = isset($p['latitude']) ? (float) $p['latitude'] : null;
    $lng = isset($p['longitude']) ? (float) $p['longitude'] : null;
    if ($veiculo_id <= 0 || $lat === null || $lng === null) {
        return null;
    }
    if (!empty($p['motorista_id']) && (int) $p['motorista_id'] !== $motorista_id) {
        return null;
    }
    $vel = null;
    if (isset($p['velocidade']) && $p['velocidade'] !== '' && is_numeric($p['velocidade'])) {
        $vel = (float) $p['velocidade'];
    }
    $dh = null;
    if (!empty($p['data_hora']) && is_string($p['data_hora'])) {
        $dh = trim($p['data_hora']);
    }
    $ignicao = null;
    if (array_key_exists('ignicao', $p) && $p['ignicao'] !== '' && $p['ignicao'] !== null) {
        $ignicao = ((int) $p['ignicao'] !== 0) ? 1 : 0;
    }
    $endereco = null;
    if (!empty($p['endereco']) && is_string($p['endereco'])) {
        $endereco = trim($p['endereco']) ?: null;
    }
    $bat = null;
    $br = $p['bateria_pct'] ?? $p['nivel_bateria'] ?? null;
    if ($br !== null && $br !== '' && is_numeric($br)) {
        $bat = (int) round((float) $br);
    }
    $acc = null;
    $ar = $p['accuracy_metros'] ?? $p['accuracy'] ?? null;
    if ($ar !== null && $ar !== '' && is_numeric($ar)) {
        $acc = (float) $ar;
    }
    $prov = null;
    if (!empty($p['provider']) && is_string($p['provider'])) {
        $prov = trim($p['provider']);
    }
    $mock = null;
    if (array_key_exists('location_mock', $p) && $p['location_mock'] !== '' && $p['location_mock'] !== null) {
        $mock = ((int) $p['location_mock'] !== 0) ? 1 : 0;
    } elseif (array_key_exists('is_mock', $p) && $p['is_mock'] !== '' && $p['is_mock'] !== null) {
        $mock = ((int) $p['is_mock'] !== 0) ? 1 : 0;
    }

    return [
        'veiculo_id' => $veiculo_id,
        'lat' => $lat,
        'lng' => $lng,
        'vel' => $vel,
        'dh' => $dh,
        'ignicao' => $ignicao,
        'endereco' => $endereco,
        'bat' => $bat,
        'acc' => $acc,
        'prov' => $prov,
        'mock' => $mock,
    ];
}

try {
    $conn = getConnection();
    $ids = [];
    $indices_ok = [];
    $erros = 0;
    foreach ($pontos as $idx => $p) {
        if (!is_array($p)) {
            $erros++;
            continue;
        }
        $item = gps_salvar_lote_parse_item($p, $motorista_id, $defaultVid);
        if ($item === null) {
            $erros++;
            continue;
        }
        if ($item['lat'] < -90 || $item['lat'] > 90 || $item['lng'] < -180 || $item['lng'] > 180) {
            $erros++;
            continue;
        }
        try {
            $out = gps_motorista_salvar_posicao(
                $conn,
                $empresa_id,
                $motorista_id,
                $item['veiculo_id'],
                $item['lat'],
                $item['lng'],
                $item['vel'],
                $item['dh'],
                $item['ignicao'],
                $item['endereco'],
                $item['bat'],
                $item['acc'],
                $item['prov'],
                $item['mock']
            );
            $ids[] = $out['id'];
            $indices_ok[] = (int) $idx;
        } catch (InvalidArgumentException $e) {
            $erros++;
        }
    }
    api_success(
        [
            'ids' => $ids,
            'salvos' => count($ids),
            'erros' => $erros,
            'indices_ok' => $indices_ok,
        ],
        'Lote processado.'
    );
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'gps_logs') !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
        api_error('Tabelas GPS não instaladas.', 503);
    }
    error_log('gps_salvar_lote: ' . $e->getMessage());
    api_error('Erro ao salvar lote.', 500);
}
