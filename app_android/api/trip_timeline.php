<?php
/**
 * Timeline do Modo Viagem — sync app Android.
 * GET  ?rota_id=X — lista eventos da rota
 * POST { "eventos": [ { event_uuid, rota_id, tipo, titulo, detalhe?, latitude?, longitude?, evento_em } ] }
 */

require_once __DIR__ . '/../config.php';
require_motorista_token();

$motorista_id = get_motorista_id();
$empresa_id = get_empresa_id();
$method = $_SERVER['REQUEST_METHOD'] ?? '';

try {
    $conn = getConnection();

    $conn->exec(
        'CREATE TABLE IF NOT EXISTS trip_timeline_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            empresa_id INT(11) NOT NULL,
            motorista_id INT(11) NOT NULL,
            rota_id INT(11) NOT NULL,
            event_uuid VARCHAR(36) NOT NULL,
            tipo VARCHAR(40) NOT NULL,
            titulo VARCHAR(160) NOT NULL,
            detalhe TEXT NULL,
            latitude DECIMAL(10,7) NULL,
            longitude DECIMAL(10,7) NULL,
            evento_em DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_trip_event_uuid (event_uuid),
            KEY idx_trip_rota (empresa_id, rota_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    if ($method === 'GET') {
        $rota_id = (int) ($_GET['rota_id'] ?? 0);
        if ($rota_id < 1) {
            api_error('Informe rota_id.');
        }
        $chk = $conn->prepare('SELECT id FROM rotas WHERE id = :id AND empresa_id = :e AND motorista_id = :m LIMIT 1');
        $chk->execute([':id' => $rota_id, ':e' => $empresa_id, ':m' => $motorista_id]);
        if (!$chk->fetch()) {
            api_error('Rota não encontrada.', 404);
        }
        $stmt = $conn->prepare('
            SELECT event_uuid, rota_id, tipo, titulo, detalhe, latitude, longitude, evento_em
            FROM trip_timeline_events
            WHERE empresa_id = :e AND motorista_id = :m AND rota_id = :r
            ORDER BY evento_em ASC
            LIMIT 500
        ');
        $stmt->execute([':e' => $empresa_id, ':m' => $motorista_id, ':r' => $rota_id]);
        api_success(['eventos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            api_error('JSON inválido.');
        }
        $eventos = $body['eventos'] ?? [];
        if (!is_array($eventos) || empty($eventos)) {
            api_error('Informe eventos[].');
        }
        $ins = $conn->prepare('
            INSERT IGNORE INTO trip_timeline_events
                (empresa_id, motorista_id, rota_id, event_uuid, tipo, titulo, detalhe, latitude, longitude, evento_em)
            VALUES
                (:e, :m, :r, :uuid, :tipo, :titulo, :det, :lat, :lng, :em)
        ');
        // Preparado uma única vez e reexecutado no loop (evita re-preparar por evento).
        $chkRota = $conn->prepare('SELECT id FROM rotas WHERE id = :id AND empresa_id = :e AND motorista_id = :m LIMIT 1');
        $synced = 0;
        foreach ($eventos as $ev) {
            if (!is_array($ev)) {
                continue;
            }
            $uuid = trim((string) ($ev['event_uuid'] ?? $ev['id'] ?? ''));
            $rota_id = (int) ($ev['rota_id'] ?? 0);
            $tipo = trim((string) ($ev['tipo'] ?? ''));
            $titulo = trim((string) ($ev['titulo'] ?? ''));
            if ($uuid === '' || $rota_id < 1 || $tipo === '' || $titulo === '') {
                continue;
            }
            $chkRota->execute([':id' => $rota_id, ':e' => $empresa_id, ':m' => $motorista_id]);
            if (!$chkRota->fetch()) {
                continue;
            }
            $det = trim((string) ($ev['detalhe'] ?? ''));
            $lat = isset($ev['latitude']) && $ev['latitude'] !== '' ? (float) $ev['latitude'] : null;
            $lng = isset($ev['longitude']) && $ev['longitude'] !== '' ? (float) $ev['longitude'] : null;
            $em = !empty($ev['evento_em']) ? (string) $ev['evento_em'] : date('Y-m-d H:i:s');
            $ins->execute([
                ':e' => $empresa_id,
                ':m' => $motorista_id,
                ':r' => $rota_id,
                ':uuid' => $uuid,
                ':tipo' => substr($tipo, 0, 40),
                ':titulo' => substr($titulo, 0, 160),
                ':det' => $det !== '' ? $det : null,
                ':lat' => $lat,
                ':lng' => $lng,
                ':em' => $em,
            ]);
            $synced += (int) $ins->rowCount();
        }
        api_success(['sincronizados' => $synced], 'Timeline sincronizada.');
    }

    api_error('Método não permitido.', 405);
} catch (PDOException $e) {
    error_log('trip_timeline.php: ' . $e->getMessage());
    api_error('Erro ao processar timeline.', 500);
}
