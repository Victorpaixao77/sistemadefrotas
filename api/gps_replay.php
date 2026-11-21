<?php
/**
 * API — Replay de Viagem: trajeto GPS gravado (gps_logs) de um veículo, para reprodução.
 * Só leitura, por empresa.
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';

configure_session();
session_start();
require_authentication();
if (function_exists('session_write_close')) { session_write_close(); }

header('Content-Type: application/json; charset=utf-8');

$conn = getConnection();
$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if ($empresa_id < 1) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Não autorizado']); exit; }

$action = $_GET['action'] ?? 'trajeto';

try {
    if ($action === 'veiculos') {
        // veículos que têm posições + dias disponíveis
        $st = $conn->prepare("SELECT g.veiculo_id, v.placa, v.modelo, COUNT(*) pontos, MAX(g.data_hora) ultima
            FROM gps_logs g LEFT JOIN veiculos v ON v.id = g.veiculo_id
            WHERE g.empresa_id = :e AND g.latitude IS NOT NULL AND g.longitude IS NOT NULL
            GROUP BY g.veiculo_id, v.placa, v.modelo ORDER BY ultima DESC");
        $st->execute([':e' => $empresa_id]);
        echo json_encode(['success' => true, 'veiculos' => $st->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'dias') {
        $vid = (int) ($_GET['veiculo_id'] ?? 0);
        $st = $conn->prepare("SELECT DATE(data_hora) dia, COUNT(*) pontos FROM gps_logs
            WHERE empresa_id = :e AND veiculo_id = :v AND latitude IS NOT NULL
            GROUP BY dia ORDER BY dia DESC LIMIT 60");
        $st->execute([':e' => $empresa_id, ':v' => $vid]);
        echo json_encode(['success' => true, 'dias' => $st->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // trajeto
    $vid = (int) ($_GET['veiculo_id'] ?? 0);
    if ($vid < 1) { echo json_encode(['success' => false, 'message' => 'Informe o veículo.']); exit; }
    $dia = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['data'] ?? '')) ? $_GET['data'] : null;

    $sql = "SELECT latitude, longitude, velocidade, status, data_hora FROM gps_logs
            WHERE empresa_id = :e AND veiculo_id = :v AND latitude IS NOT NULL AND longitude IS NOT NULL
              AND latitude <> 0 AND longitude <> 0 ";
    $params = [':e' => $empresa_id, ':v' => $vid];
    if ($dia) { $sql .= " AND DATE(data_hora) = :d "; $params[':d'] = $dia; }
    $sql .= " ORDER BY data_hora ASC LIMIT 3000";
    $st = $conn->prepare($sql);
    $st->execute($params);
    $pontos = [];
    $vmax = 0; $dist = 0; $prev = null;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $lat = (float) $r['latitude']; $lng = (float) $r['longitude']; $vel = (float) $r['velocidade'];
        $vmax = max($vmax, $vel);
        if ($prev) {
            // distância aproximada (haversine simplificada) em km
            $dlat = deg2rad($lat - $prev[0]); $dlng = deg2rad($lng - $prev[1]);
            $a = sin($dlat / 2) ** 2 + cos(deg2rad($prev[0])) * cos(deg2rad($lat)) * sin($dlng / 2) ** 2;
            $dist += 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
        }
        $prev = [$lat, $lng];
        $pontos[] = ['lat' => $lat, 'lng' => $lng, 'vel' => round($vel, 1), 'status' => $r['status'], 'hora' => $r['data_hora']];
    }

    echo json_encode(['success' => true, 'pontos' => $pontos, 'resumo' => [
        'total' => count($pontos), 'vel_max' => round($vmax, 1), 'distancia_km' => round($dist, 1),
        'inicio' => $pontos[0]['hora'] ?? null, 'fim' => end($pontos)['hora'] ?? null,
    ]], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('gps_replay.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar o trajeto.']);
}
