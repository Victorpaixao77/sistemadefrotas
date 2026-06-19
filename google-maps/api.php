<?php
/**
 * API para Google Maps
 * Sistema de Gestão de Frotas
 */

require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Configure session
configure_session();
session_start();

// Debug: verificar sessão (apenas em caso de erro)
// error_log("Google Maps API - Sessão debug:");
// error_log("usuario_id: " . ($_SESSION['usuario_id'] ?? 'NOT_SET'));
// error_log("empresa_id: " . ($_SESSION['empresa_id'] ?? 'NOT_SET'));
// error_log("session_id: " . session_id());

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['empresa_id'])) {
    error_log("Google Maps API - Usuário não autenticado");
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Usuário não autenticado'
    ]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_config':
            getGoogleMapsConfig();
            break;
            
        case 'save_config':
            saveGoogleMapsConfig();
            break;
            
        case 'test_api_key':
            testGoogleMapsApiKey();
            break;

        case 'compute_routes_tolls':
            computeRoutesWithTolls();
            break;
            
        default:
            throw new Exception('Ação não reconhecida');
    }
} catch (Exception $e) {
    error_log("Google Maps API - Erro geral: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'debug' => [
            'action' => $action,
            'file' => __FILE__,
            'line' => __LINE__
        ]
    ]);
}

function getGoogleMapsConfig() {
    $pdo = getConnection();
    $empresa_id = $_SESSION['empresa_id'];
    
    $sql = "SELECT google_maps_api_key, google_vision_api_key FROM configuracoes WHERE empresa_id = :empresa_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    
    $config = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $maps = trim((string) ($config['google_maps_api_key'] ?? ''));
    $vision = trim((string) ($config['google_vision_api_key'] ?? ''));
    $key = $maps !== '' ? $maps : $vision;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'google_maps_api_key' => $key,
        ]
    ]);
}

function saveGoogleMapsConfig() {
    try {
        $pdo = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        $google_maps_api_key = $_POST['google_maps_api_key'] ?? '';
        
        // Validar se a chave não está vazia
        if (empty($google_maps_api_key)) {
            throw new Exception('Chave da API do Google Maps é obrigatória');
        }
        
        // Verificar se já existe configuração para esta empresa
        $sql = "SELECT id FROM configuracoes WHERE empresa_id = :empresa_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Atualizar configuração existente
            $sql = "UPDATE configuracoes SET google_maps_api_key = :api_key, updated_at = NOW() WHERE empresa_id = :empresa_id";
        } else {
            // Criar nova configuração
            $sql = "INSERT INTO configuracoes (empresa_id, google_maps_api_key, cor_menu, nome_personalizado, data_criacao, updated_at) 
                    VALUES (:empresa_id, :api_key, '#343a40', 'Empresa', NOW(), NOW())";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->bindParam(':api_key', $google_maps_api_key);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Configuração do Google Maps salva com sucesso!'
            ]);
        } else {
            $errorInfo = $stmt->errorInfo();
            throw new Exception('Erro ao salvar configuração: ' . $errorInfo[2]);
        }
    } catch (PDOException $e) {
        error_log("Google Maps API - Erro PDO: " . $e->getMessage());
        throw new Exception('Erro de banco de dados: ' . $e->getMessage());
    } catch (Exception $e) {
        error_log("Google Maps API - Erro: " . $e->getMessage());
        throw $e;
    }
}

function testGoogleMapsApiKey() {
    $api_key = $_POST['api_key'] ?? '';
    
    if (empty($api_key)) {
        throw new Exception('Chave da API é obrigatória');
    }
    
    // Verificar formato básico da chave
    if (strpos($api_key, 'AIza') !== 0) {
        throw new Exception('Formato de chave inválido. Deve começar com "AIza"');
    }
    
    // Testar a chave fazendo uma requisição simples para a API do Google Maps
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address=Brasil&key=" . urlencode($api_key);
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'method' => 'GET'
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        throw new Exception('Erro ao conectar com a API do Google Maps');
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['error_message'])) {
        throw new Exception('Chave inválida: ' . $data['error_message']);
    }
    
    if (isset($data['status']) && $data['status'] === 'OK') {
        echo json_encode([
            'success' => true,
            'message' => 'Chave da API válida!'
        ]);
    } else {
        throw new Exception('Chave da API inválida ou sem permissões');
    }
}

/**
 * Chaves candidatas para Routes API (server-side). Ordem: Vision primeiro — costuma ser o projeto
 * onde o usuário habilitou Routes + Vision; a chave “só Maps” antiga pode apontar para outro projeto.
 *
 * @return array<int, array{key: string, label: string}>
 */
function sf_google_routes_api_key_candidates(PDO $pdo, int $empresa_id): array
{
    $stmt = $pdo->prepare(
        'SELECT google_maps_api_key, google_vision_api_key FROM configuracoes WHERE empresa_id = :e LIMIT 1'
    );
    $stmt->execute([':e' => $empresa_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $pairs = [
        [trim((string) ($row['google_vision_api_key'] ?? '')), 'Google Vision (Configurações)'],
        [trim((string) ($row['google_maps_api_key'] ?? '')), 'Google Maps (Configurações)'],
        [trim((string) getenv('SF_GOOGLE_VISION_API_KEY')), 'SF_GOOGLE_VISION_API_KEY (servidor)'],
        [trim((string) getenv('SF_GOOGLE_MAPS_API_KEY')), 'SF_GOOGLE_MAPS_API_KEY (servidor)'],
        [trim((string) getenv('GOOGLE_MAPS_API_KEY')), 'GOOGLE_MAPS_API_KEY (servidor)'],
    ];

    $seen = [];
    $out = [];
    foreach ($pairs as [$k, $label]) {
        if ($k === '' || isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $out[] = ['key' => $k, 'label' => $label];
    }

    return $out;
}

/**
 * Soma estimatedPrice em BRL a partir de um objeto tollInfo da Routes API.
 */
function sf_google_routes_toll_info_sum_brl(?array $tollInfo): float
{
    if (!$tollInfo || empty($tollInfo['estimatedPrice']) || !is_array($tollInfo['estimatedPrice'])) {
        return 0.0;
    }
    $sum = 0.0;
    foreach ($tollInfo['estimatedPrice'] as $p) {
        if (!is_array($p)) {
            continue;
        }
        if (strtoupper((string) ($p['currencyCode'] ?? 'BRL')) !== 'BRL') {
            continue;
        }
        $units = (float) ((string) ($p['units'] ?? '0'));
        $nanos = (float) ((int) ($p['nanos'] ?? 0)) / 1000000000;
        $sum += ($units + $nanos);
    }
    return $sum;
}

/**
 * TollPass aceitos na Routes API (subset seguro; evita enviar strings arbitrárias ao Google).
 *
 * @return array<string, true>
 */
function sf_google_routes_toll_pass_allowlist(): array
{
    static $set = null;
    if ($set === null) {
        $set = array_fill_keys([
            'BR_AUTO_EXPRESO',
            'BR_CONECTCAR',
            'BR_MOVE_MAIS',
            'BR_PASSA_RAPIDO',
            'BR_SEM_PARAR',
            'BR_TAGGY',
            'BR_VELOE',
        ], true);
    }
    return $set;
}

/**
 * Lê POST: toll_pass (um) ou toll_passes (JSON array ou vírgulas).
 *
 * @return list<string>
 */
function sf_google_routes_parse_toll_passes_from_post(): array
{
    $out = [];
    $single = strtoupper(trim((string) ($_POST['toll_pass'] ?? '')));
    if ($single !== '' && isset(sf_google_routes_toll_pass_allowlist()[$single])) {
        return [$single];
    }
    $raw = $_POST['toll_passes'] ?? null;
    if ($raw === null || $raw === '') {
        return [];
    }
    if (is_array($raw)) {
        $candidates = $raw;
    } else {
        $s = trim((string) $raw);
        if ($s === '') {
            return [];
        }
        if ($s[0] === '[') {
            $decoded = json_decode($s, true);
            $candidates = is_array($decoded) ? $decoded : [];
        } else {
            $candidates = array_map('trim', explode(',', $s));
        }
    }
    $allow = sf_google_routes_toll_pass_allowlist();
    foreach ($candidates as $x) {
        $u = strtoupper(trim((string) $x));
        if ($u !== '' && isset($allow[$u]) && !in_array($u, $out, true)) {
            $out[] = $u;
        }
    }
    return $out;
}

/**
 * @return array{http:int, resp:?array, raw:string}
 */
function sf_google_routes_compute_raw(string $apiKey, array $payload, ?string $fieldMask = null): array
{
    $mask = $fieldMask !== null && $fieldMask !== ''
        ? $fieldMask
        : 'routes.distanceMeters,routes.duration,routes.travelAdvisory.tollInfo,routes.legs.travelAdvisory.tollInfo,routes.localizedValues';
    $ch = curl_init('https://routes.googleapis.com/directions/v2:computeRoutes');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Goog-Api-Key: ' . $apiKey,
            'X-Goog-FieldMask: ' . $mask,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        throw new Exception('Falha ao consultar Google Routes API: ' . $err);
    }
    $resp = is_string($raw) ? json_decode($raw, true) : null;

    return ['http' => $http, 'resp' => is_array($resp) ? $resp : null, 'raw' => is_string($raw) ? $raw : ''];
}

function computeRoutesWithTolls() {
    $origin = trim((string) ($_POST['origin'] ?? ''));
    $destination = trim((string) ($_POST['destination'] ?? ''));
    $emissionType = strtoupper(trim((string) ($_POST['emission_type'] ?? 'DIESEL')));
    $vehicleType = strtoupper(trim((string) ($_POST['vehicle_type'] ?? 'TRUCK')));
    $fuelPrice = (float) ($_POST['fuel_price'] ?? 0);
    $consumptionKmLPost = isset($_POST['consumption_km_l']) ? (float) $_POST['consumption_km_l'] : 0.0;
    $eixosConjunto = isset($_POST['eixos_conjunto'])
        ? (float) $_POST['eixos_conjunto']
        : (in_array($vehicleType, ['TRUCK', 'CARRETA'], true) ? 6.0 : 2.0);
    if ($eixosConjunto < 2.0) {
        $eixosConjunto = 2.0;
    }

    if ($origin === '' || $destination === '') {
        throw new Exception('Origem e destino são obrigatórios');
    }
    if (!in_array($emissionType, ['GASOLINE', 'DIESEL', 'ELECTRIC', 'HYBRID'], true)) {
        $emissionType = 'DIESEL';
    }

    $pdo = getConnection();
    $empresa_id = (int) $_SESSION['empresa_id'];
    $candidates = sf_google_routes_api_key_candidates($pdo, $empresa_id);
    if ($candidates === []) {
        throw new Exception(
            'Nenhuma chave Google configurada para calcular rotas com pedágio. ' .
            'Salve a chave em Configurações → Google Vision ou Google Maps (a mesma chave pode servir para ambos se o projeto tiver Routes API ativa).'
        );
    }

    $tollPasses = sf_google_routes_parse_toll_passes_from_post();
    $routeModifiers = [
        // VehicleInfo na Routes API v2 só aceita emissionType (não existe vehicleType aqui).
        'vehicleInfo' => ['emissionType' => $emissionType],
    ];
    if ($tollPasses !== []) {
        // Documentação: com passes a API tenta retornar preço do passe; sem passes, preço à vista / desconhecido.
        $routeModifiers['tollPasses'] = $tollPasses;
    }

    $payload = [
        'origin' => ['address' => $origin],
        'destination' => ['address' => $destination],
        'travelMode' => 'DRIVE',
        'routingPreference' => 'TRAFFIC_UNAWARE',
        'computeAlternativeRoutes' => false,
        'languageCode' => 'pt-BR',
        'regionCode' => 'BR',
        'extraComputations' => ['TOLLS'],
        'routeModifiers' => $routeModifiers,
    ];

    $lastHttp = 0;
    $lastMsg = '';
    $lastLabel = '';
    $resp = null;
    foreach ($candidates as $cand) {
        $apiKey = $cand['key'];
        $lastLabel = $cand['label'];
        $pack = sf_google_routes_compute_raw($apiKey, $payload);
        $lastHttp = $pack['http'];
        $resp = $pack['resp'];
        if (!is_array($resp)) {
            throw new Exception('Resposta inválida da Google Routes API (origem: ' . $lastLabel . ').');
        }
        if ($lastHttp < 400) {
            break;
        }
        $lastMsg = trim((string) (($resp['error']['message'] ?? '') ?: ''));
        $retry403 = $lastHttp === 403 && (
            stripos($lastMsg, 'has not been used') !== false
            || stripos($lastMsg, 'disabled') !== false
            || stripos($lastMsg, 'SERVICE_DISABLED') !== false
        );
        if ($retry403) {
            continue;
        }
        if ($lastHttp === 403) {
            $projectMatch = [];
            $projectId = '';
            if (preg_match('/project[= #](\d+)/', $lastMsg, $projectMatch)) {
                $projectId = $projectMatch[1];
            }
            $enableUrl = $projectId !== ''
                ? 'https://console.developers.google.com/apis/api/routes.googleapis.com/overview?project=' . $projectId
                : 'https://console.cloud.google.com/apis/library/routes.googleapis.com';
            throw new Exception(
                'Routes API recusou a chave usada em "' . $lastLabel . '". ' .
                'O número de projeto no link é o da chave que o Google recebeu (não necessariamente o seu projeto atual). ' .
                'Se você usa outro projeto, salve a chave correta em Configurações → Google Vision (prioridade para pedágios) ou substitua a chave em Google Maps. ' .
                'Link: ' . $enableUrl . ' — Aguarde ~2 minutos após habilitar.'
            );
        }
        throw new Exception(
            'Google Routes API retornou HTTP ' . $lastHttp .
            ($lastMsg !== '' ? (': ' . $lastMsg) : '') .
            ' (chave: ' . $lastLabel . ')'
        );
    }

    if ($lastHttp >= 400 || !is_array($resp)) {
        $projectMatch = [];
        $projectId = '';
        if (preg_match('/project[= #](\d+)/', $lastMsg, $projectMatch)) {
            $projectId = $projectMatch[1];
        }
        $enableUrl = $projectId !== ''
            ? 'https://console.developers.google.com/apis/api/routes.googleapis.com/overview?project=' . $projectId
            : 'https://console.cloud.google.com/apis/library/routes.googleapis.com';
        throw new Exception(
            'Nenhuma chave funcionou para Routes API. Última tentativa: "' . $lastLabel . '". ' .
            'Habilite Routes API no projeto da chave ou use a chave do projeto onde a API já está ativa. ' .
            $enableUrl
        );
    }

    $route = (is_array($resp['routes'] ?? null) && isset($resp['routes'][0]) && is_array($resp['routes'][0])) ? $resp['routes'][0] : null;
    if (!$route) {
        throw new Exception('Nenhuma rota encontrada para os pontos informados');
    }

    $distanceMeters = (int) ($route['distanceMeters'] ?? 0);
    $durationRaw = (string) ($route['duration'] ?? '0s');
    $durationSec = 0;
    if (preg_match('/^(\d+)s$/', $durationRaw, $m)) {
        $durationSec = (int) $m[1];
    }
    // FIX 2: converte "7320s" → "2h 2min" em vez de expor o formato bruto da API
    $durationHours = (int) floor($durationSec / 3600);
    $durationMins  = (int) floor(($durationSec % 3600) / 60);
    $durationText  = $durationHours > 0
        ? "{$durationHours}h {$durationMins}min"
        : "{$durationMins}min";

    $tollTotal = sf_google_routes_toll_info_sum_brl($route['travelAdvisory']['tollInfo'] ?? null);
    $tollsLegsOut = [];
    if (isset($route['legs']) && is_array($route['legs'])) {
        foreach ($route['legs'] as $i => $leg) {
            if (!is_array($leg)) {
                continue;
            }
            $legBrl = sf_google_routes_toll_info_sum_brl($leg['travelAdvisory']['tollInfo'] ?? null);
            if ($legBrl > 0) {
                $tollsLegsOut[] = [
                    'leg_index' => (int) $i,
                    'tolls_brl' => round($legBrl, 2),
                ];
            }
        }
    }
    if ($tollTotal <= 0 && $tollsLegsOut !== []) {
        $tollTotal = 0.0;
        foreach ($tollsLegsOut as $row) {
            $tollTotal += (float) $row['tolls_brl'];
        }
    }

    $distanceKm = $distanceMeters / 1000;
    $consumptionMap = [
        'TRUCK' => 5.0,
        'CARRETA' => 4.0,
        'CAR' => 10.0,
        'SUV' => 8.5,
    ];
    if ($consumptionKmLPost > 0 && is_finite($consumptionKmLPost)) {
        $consumption = $consumptionKmLPost;
    } else {
        $consumption = $consumptionMap[$vehicleType] ?? 7.0;
    }
    $fuelLiters = ($consumption > 0) ? ($distanceKm / $consumption) : 0.0;
    $fuelCost = max(0.0, $fuelPrice) * $fuelLiters;

    // Google documenta que o toll costuma ser referência para veículo não comercial.
    // No Brasil o pedágio de caminhão é por eixo/praça — não há campo de eixos na Routes API.
    // Estimativa interna linear: referência × (eixos ÷ 2) — só ordem de grandeza (não substitui Qualp/WebRouter).
    $tollsGoogle = round($tollTotal, 2);
    $tollsTruckEstimate = ($tollsGoogle > 0) ? round($tollsGoogle * ($eixosConjunto / 2.0), 2) : 0.0;
    $useTruckToll = ($eixosConjunto >= 4.0 && $tollsTruckEstimate > 0);
    $tollsForCost = $useTruckToll ? $tollsTruckEstimate : $tollsGoogle;

    echo json_encode([
        'success' => true,
        'data' => [
            'distance_meters' => $distanceMeters,
            'distance_km' => round($distanceKm, 2),
            'duration_seconds' => $durationSec,
            'duration_text' => $durationText,   // FIX 2: "2h 5min" em vez de "7520s"
            'fuel_consumption_km_l' => $consumption,
            'fuel_liters' => round($fuelLiters, 2),
            'fuel_cost' => round($fuelCost, 2),
            'tolls_total' => $tollsForCost,
            'tolls_google_reference_brl' => $tollsGoogle,
            'tolls_truck_estimate_brl' => $tollsTruckEstimate,
            'eixos_conjunto' => (int) round($eixosConjunto),
            'toll_estimate_method' => $useTruckToll ? 'google_scaled_eixos_div2' : 'google_raw',
            'toll_passes_requested' => $tollPasses,
            'tolls_legs_brl' => $tollsLegsOut,
            'total_cost' => round($fuelCost + $tollsForCost, 2),
            'route' => $route,
        ],
    ]);
}
?>