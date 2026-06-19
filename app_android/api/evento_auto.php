<?php
/**
 * Eventos automáticos de jornada - App Android.
 * POST: registra um evento detectado pelo app (parada/descanso) em jornada_motorista
 *       com fonte = 'auto'. Faz dedup para não duplicar quando o serviço reinicia.
 * Campos: tipo (parada|descanso), latitude, longitude, observacoes, data_hora, rota_id?
 */

require_once __DIR__ . '/../config.php';
require_motorista_token();

$motorista_id = get_motorista_id();
$empresa_id = get_empresa_id();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_error('Use POST.', 405);
}

try {
    $conn = getConnection();
    $raw = file_get_contents('php://input');
    $d = json_decode($raw, true);
    if (!is_array($d)) {
        $d = $_POST;
    }

    $tipo = strtolower(trim((string) ($d['tipo'] ?? '')));
    if (!in_array($tipo, ['parada', 'descanso'], true)) {
        api_error('Tipo inválido (use parada ou descanso).');
    }
    $latitude = isset($d['latitude']) && $d['latitude'] !== '' ? (float) $d['latitude'] : null;
    $longitude = isset($d['longitude']) && $d['longitude'] !== '' ? (float) $d['longitude'] : null;
    $observacoes = trim((string) ($d['observacoes'] ?? ''));
    $rota_id = !empty($d['rota_id']) ? (int) $d['rota_id'] : null;
    $data_hora = !empty($d['data_hora']) ? (string) $d['data_hora'] : date('Y-m-d H:i:s');

    if ($rota_id !== null) {
        $chk = $conn->prepare('SELECT id FROM rotas WHERE id = :id AND empresa_id = :e AND motorista_id = :m');
        $chk->execute([':id' => $rota_id, ':e' => $empresa_id, ':m' => $motorista_id]);
        if (!$chk->fetch()) {
            $rota_id = null; // ignora vínculo inválido em evento automático
        }
    }

    // Dedup: mesmo tipo automático nos últimos 10 minutos → não duplica.
    $dedup = $conn->prepare("
        SELECT id FROM jornada_motorista
        WHERE empresa_id = :e AND motorista_id = :m AND tipo = :t AND fonte = 'auto'
          AND data_hora >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        LIMIT 1
    ");
    $dedup->execute([':e' => $empresa_id, ':m' => $motorista_id, ':t' => $tipo]);
    if ($existing = $dedup->fetch(PDO::FETCH_ASSOC)) {
        api_success(['id' => (int) $existing['id'], 'duplicado' => true], 'Evento já registrado.');
    }

    $stmt = $conn->prepare("
        INSERT INTO jornada_motorista
            (empresa_id, motorista_id, rota_id, tipo, data_hora, latitude, longitude, observacoes, fonte)
        VALUES
            (:e, :m, :rota, :tipo, :dh, :lat, :lng, :obs, 'auto')
    ");
    $stmt->execute([
        ':e' => $empresa_id,
        ':m' => $motorista_id,
        ':rota' => $rota_id,
        ':tipo' => $tipo,
        ':dh' => $data_hora,
        ':lat' => $latitude,
        ':lng' => $longitude,
        ':obs' => $observacoes !== '' ? $observacoes : null,
    ]);

    api_success(['id' => (int) $conn->lastInsertId(), 'duplicado' => false], 'Evento automático registrado.');
} catch (PDOException $e) {
    error_log('evento_auto.php: ' . $e->getMessage());
    api_error('Erro ao registrar evento automático.', 500);
}
