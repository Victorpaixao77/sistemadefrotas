<?php
/**
 * API — Digital Twin: estado vivo de cada veículo da frota (posição, status, motorista).
 * Reusa gps_ultima_posicao. Só leitura, por empresa. Atualização por polling.
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

try {
    $st = $conn->prepare("SELECT v.id, v.placa, v.modelo,
            g.latitude, g.longitude, g.velocidade, g.status AS gstatus, g.endereco,
            COALESCE(g.ultima_atualizacao, g.data_hora) AS ultima,
            m.nome AS motorista
        FROM veiculos v
        LEFT JOIN gps_ultima_posicao g ON g.veiculo_id = v.id AND g.empresa_id = :e1
        LEFT JOIN motoristas m ON m.id = g.motorista_id
        WHERE v.empresa_id = :e2
        ORDER BY v.placa");
    $st->execute([':e1' => $empresa_id, ':e2' => $empresa_id]);

    $agora = time();
    $veiculos = [];
    $resumo = ['total' => 0, 'movimento' => 0, 'parado' => 0, 'sem_sinal' => 0];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $resumo['total']++;
        $temPos = $r['latitude'] !== null && $r['longitude'] !== null && (float) $r['latitude'] != 0;
        $mins = $r['ultima'] ? floor(($agora - strtotime($r['ultima'])) / 60) : null;
        $estado = 'sem_sinal';
        if ($temPos && $mins !== null && $mins <= 120) {
            $estado = ((float) $r['velocidade'] > 3) ? 'movimento' : 'parado';
        }
        $resumo[$estado] = ($resumo[$estado] ?? 0) + 1;
        $veiculos[] = [
            'id' => (int) $r['id'], 'placa' => $r['placa'], 'modelo' => $r['modelo'],
            'motorista' => $r['motorista'] ?: null,
            'estado' => $estado,
            'velocidade' => $temPos ? round((float) $r['velocidade'], 0) : null,
            'lat' => $temPos ? (float) $r['latitude'] : null,
            'lng' => $temPos ? (float) $r['longitude'] : null,
            'endereco' => $r['endereco'] ?: null,
            'ultima' => $r['ultima'],
            'ha_min' => $mins,
        ];
    }

    echo json_encode(['success' => true, 'ts' => date('H:i:s'), 'resumo' => $resumo, 'veiculos' => $veiculos], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('digital_twin.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar o estado da frota.']);
}
