<?php
/**
 * POST JSON — grava ponto GPS (app motorista).
 * Body: veiculo_id, latitude, longitude, opcional: velocidade (km/h), data_hora
 * motorista_id vem do token (ignora corpo se diferente).
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
    $input = $_POST;
}

$veiculo_id = isset($input['veiculo_id']) ? (int) $input['veiculo_id'] : 0;
$lat = isset($input['latitude']) ? (float) $input['latitude'] : null;
$lng = isset($input['longitude']) ? (float) $input['longitude'] : null;

if ($veiculo_id <= 0 || $lat === null || $lng === null) {
    api_error('veiculo_id, latitude e longitude são obrigatórios.', 400);
}

if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    api_error('Coordenadas fora do intervalo válido.', 400);
}

$vel = null;
if (isset($input['velocidade']) && $input['velocidade'] !== '' && is_numeric($input['velocidade'])) {
    $vel = (float) $input['velocidade'];
}

$dh = null;
if (!empty($input['data_hora']) && is_string($input['data_hora'])) {
    $dh = trim($input['data_hora']);
}

if (!empty($input['motorista_id']) && (int) $input['motorista_id'] !== $motorista_id) {
    api_error('motorista_id não confere com o token.', 403);
}

$rlMax = (int) (getenv('SF_GPS_RATE_LIMIT_MAX') ?: 360);
$rlWin = (int) (getenv('SF_GPS_RATE_LIMIT_WINDOW') ?: 60);
if (!sf_rate_limit_allow_custom('gps_salvar_m' . $motorista_id, $rlMax, $rlWin)) {
    api_error('Muitas requisições GPS. Aguarde um instante.', 429);
}

$ignicao = null;
if (array_key_exists('ignicao', $input) && $input['ignicao'] !== '' && $input['ignicao'] !== null) {
    $ignicao = ((int) $input['ignicao'] !== 0) ? 1 : 0;
}
$endereco = null;
if (!empty($input['endereco']) && is_string($input['endereco'])) {
    $endereco = trim($input['endereco']);
    if ($endereco === '') {
        $endereco = null;
    }
}

$bateria_pct = null;
$bRaw = $input['bateria_pct'] ?? $input['nivel_bateria'] ?? null;
if ($bRaw !== null && $bRaw !== '' && is_numeric($bRaw)) {
    $bateria_pct = (int) round((float) $bRaw);
}

$accuracy_metros = null;
$aRaw = $input['accuracy_metros'] ?? $input['accuracy'] ?? null;
if ($aRaw !== null && $aRaw !== '' && is_numeric($aRaw)) {
    $accuracy_metros = (float) $aRaw;
    if ($accuracy_metros < 0 || $accuracy_metros > 50000) {
        $accuracy_metros = null;
    }
}

$provider = null;
if (!empty($input['provider']) && is_string($input['provider'])) {
    $provider = trim($input['provider']);
}

$location_mock = null;
if (array_key_exists('location_mock', $input) && $input['location_mock'] !== '' && $input['location_mock'] !== null) {
    $location_mock = ((int) $input['location_mock'] !== 0) ? 1 : 0;
} elseif (array_key_exists('is_mock', $input) && $input['is_mock'] !== '' && $input['is_mock'] !== null) {
    $location_mock = ((int) $input['is_mock'] !== 0) ? 1 : 0;
}

try {
    $conn = getConnection();
    $out = gps_motorista_salvar_posicao(
        $conn,
        $empresa_id,
        $motorista_id,
        $veiculo_id,
        $lat,
        $lng,
        $vel,
        $dh,
        $ignicao,
        $endereco,
        $bateria_pct,
        $accuracy_metros,
        $provider,
        $location_mock
    );
    api_success($out, 'Posição registrada.');
} catch (InvalidArgumentException $e) {
    api_error($e->getMessage(), 400);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'gps_logs') !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
        api_error('Tabelas GPS não instaladas. Execute sql/create_gps_tracking.sql no banco.', 503);
    }
    error_log('gps_salvar: ' . $e->getMessage());
    api_error('Erro ao salvar posição.', 500);
}
