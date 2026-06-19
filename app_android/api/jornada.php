<?php
/**
 * API Controle de Jornada do Motorista (Lei do Motorista) - App Android
 * GET:  lista eventos de jornada do motorista (mais recentes) + resumo do dia
 * POST: registra um evento de jornada
 *
 * Tipos: inicio, parada, descanso, refeicao, fim
 */

require_once __DIR__ . '/../config.php';
require_motorista_token();

$motorista_id = get_motorista_id();
$empresa_id = get_empresa_id();
$method = $_SERVER['REQUEST_METHOD'] ?? '';

const JORNADA_TIPOS = ['inicio', 'parada', 'descanso', 'refeicao', 'fim'];

try {
    $conn = getConnection();

    if ($method === 'GET') {
        $limite = min(200, max(1, (int) ($_GET['limite'] ?? 50)));
        $stmt = $conn->prepare('
            SELECT id, tipo, data_hora, rota_id, latitude, longitude, observacoes
            FROM jornada_motorista
            WHERE empresa_id = :e AND motorista_id = :m
            ORDER BY data_hora DESC
            LIMIT ' . $limite
        );
        $stmt->execute([':e' => $empresa_id, ':m' => $motorista_id]);
        $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Resumo do dia: minutos dirigindo (entre inicio/fim/parada) e em descanso/refeicao.
        $stmt = $conn->prepare('
            SELECT tipo, data_hora
            FROM jornada_motorista
            WHERE empresa_id = :e AND motorista_id = :m AND DATE(data_hora) = CURDATE()
            ORDER BY data_hora ASC
        ');
        $stmt->execute([':e' => $empresa_id, ':m' => $motorista_id]);
        $hoje = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dirigindoSeg = 0;
        $descansoSeg = 0;
        $ultimoTipo = null;
        $ultimoTs = null;
        foreach ($hoje as $ev) {
            $ts = strtotime((string) $ev['data_hora']);
            if ($ultimoTipo !== null && $ultimoTs !== null && $ts >= $ultimoTs) {
                $dur = $ts - $ultimoTs;
                if ($ultimoTipo === 'inicio') {
                    $dirigindoSeg += $dur;
                } elseif ($ultimoTipo === 'descanso' || $ultimoTipo === 'refeicao' || $ultimoTipo === 'parada') {
                    $descansoSeg += $dur;
                }
            }
            $ultimoTipo = $ev['tipo'];
            $ultimoTs = $ts;
        }

        api_success([
            'eventos' => $eventos,
            'resumo_hoje' => [
                'dirigindo_min' => (int) round($dirigindoSeg / 60),
                'descanso_min' => (int) round($descansoSeg / 60),
                'estado_atual' => $ultimoTipo,
            ],
        ]);
    }

    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $d = json_decode($raw, true);
        if (!is_array($d)) {
            $d = $_POST;
        }

        $tipo = strtolower(trim((string) ($d['tipo'] ?? '')));
        if (!in_array($tipo, JORNADA_TIPOS, true)) {
            api_error('Tipo de evento de jornada inválido.');
        }

        $rota_id = !empty($d['rota_id']) ? (int) $d['rota_id'] : null;
        $latitude = isset($d['latitude']) && $d['latitude'] !== '' ? (float) $d['latitude'] : null;
        $longitude = isset($d['longitude']) && $d['longitude'] !== '' ? (float) $d['longitude'] : null;
        $observacoes = trim((string) ($d['observacoes'] ?? ''));
        $data_hora = !empty($d['data_hora']) ? (string) $d['data_hora'] : date('Y-m-d H:i:s');

        if ($rota_id !== null) {
            $stmt = $conn->prepare('SELECT id FROM rotas WHERE id = :id AND empresa_id = :e AND motorista_id = :m');
            $stmt->execute([':id' => $rota_id, ':e' => $empresa_id, ':m' => $motorista_id]);
            if (!$stmt->fetch()) {
                api_error('Rota não encontrada ou não pertence ao motorista.');
            }
        }

        $stmt = $conn->prepare('
            INSERT INTO jornada_motorista
                (empresa_id, motorista_id, rota_id, tipo, data_hora, latitude, longitude, observacoes, fonte)
            VALUES
                (:e, :m, :rota, :tipo, :dh, :lat, :lng, :obs, "motorista")
        ');
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

        api_success(['id' => (int) $conn->lastInsertId()], 'Evento de jornada registrado.');
    }

    api_error('Método não suportado.', 405);
} catch (PDOException $e) {
    error_log('jornada.php: ' . $e->getMessage());
    api_error('Erro ao processar a jornada.', 500);
}
