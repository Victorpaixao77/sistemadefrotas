<?php
/**
 * GET — últimas posições GPS dos veículos da empresa (sessão painel web).
 * Resposta: { success, data: { posicoes: [ { veiculo_id, placa, modelo, motorista_id, motorista_nome, latitude, longitude, velocidade, data_hora } ] } }
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db_connect.php';
require_once dirname(__DIR__) . '/includes/api_json.php';
require_once dirname(__DIR__) . '/includes/gps_redis_queue.php';

configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    api_json_method_not_allowed();
}

if (!function_exists('require_authentication')) {
    api_json_error('Configuração inválida', 500);
}

require_authentication();

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if ($empresa_id <= 0) {
    api_json_unauthorized();
}

try {
    $conn = getConnection();
    $params = [':eid' => $empresa_id, ':eid2' => $empresa_id, ':eid3' => $empresa_id];
    $sqlExt = '
        SELECT
            g.veiculo_id,
            v.placa,
            v.modelo,
            g.motorista_id,
            m.nome AS motorista_nome,
            g.latitude,
            g.longitude,
            g.velocidade,
            g.status,
            g.ignicao,
            g.ultima_atualizacao,
            g.endereco,
            g.bateria_pct,
            g.accuracy_metros,
            g.provider,
            g.location_mock,
            g.data_hora
        FROM gps_ultima_posicao g
        INNER JOIN veiculos v ON v.id = g.veiculo_id AND v.empresa_id = :eid
        LEFT JOIN motoristas m ON m.id = g.motorista_id AND m.empresa_id = :eid2
        WHERE g.empresa_id = :eid3
        ORDER BY v.placa ASC
    ';
    $sqlBase = '
        SELECT
            g.veiculo_id,
            v.placa,
            v.modelo,
            g.motorista_id,
            m.nome AS motorista_nome,
            g.latitude,
            g.longitude,
            g.velocidade,
            g.data_hora
        FROM gps_ultima_posicao g
        INNER JOIN veiculos v ON v.id = g.veiculo_id AND v.empresa_id = :eid
        LEFT JOIN motoristas m ON m.id = g.motorista_id AND m.empresa_id = :eid2
        WHERE g.empresa_id = :eid3
        ORDER BY v.placa ASC
    ';
    try {
        $stmt = $conn->prepare($sqlExt);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Unknown column') === false) {
            throw $e;
        }
        $stmt = $conn->prepare($sqlBase);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $ids = array_map(static function ($row) {
        return (int) $row['veiculo_id'];
    }, $rows);
    $cached = gps_redis_cache_mget_ultimas($empresa_id, $ids);

    foreach ($rows as &$r) {
        $vid = (int) $r['veiculo_id'];
        $c = $cached[$vid] ?? null;
        if (is_array($c)) {
            if (isset($c['latitude'])) {
                $r['latitude'] = (float) $c['latitude'];
            }
            if (isset($c['longitude'])) {
                $r['longitude'] = (float) $c['longitude'];
            }
            if (array_key_exists('velocidade', $c)) {
                $r['velocidade'] = $c['velocidade'] !== null ? (float) $c['velocidade'] : null;
            }
            if (!empty($c['data_hora'])) {
                $r['data_hora'] = (string) $c['data_hora'];
            }
            if (!empty($c['ultima_atualizacao'])) {
                $r['ultima_atualizacao'] = (string) $c['ultima_atualizacao'];
            }
            if (isset($c['status']) && is_string($c['status'])) {
                $r['status'] = $c['status'];
            }
            if (isset($c['ignicao'])) {
                $r['ignicao'] = $c['ignicao'] !== null ? (int) $c['ignicao'] : null;
            }
            if (isset($c['endereco']) && $c['endereco'] !== null) {
                $r['endereco'] = (string) $c['endereco'];
            }
            if (array_key_exists('bateria_pct', $c) && $c['bateria_pct'] !== null && $c['bateria_pct'] !== '') {
                $r['bateria_pct'] = (int) $c['bateria_pct'];
            }
            if (array_key_exists('accuracy_metros', $c) && $c['accuracy_metros'] !== null && $c['accuracy_metros'] !== '') {
                $r['accuracy_metros'] = (float) $c['accuracy_metros'];
            }
            if (isset($c['provider']) && $c['provider'] !== null) {
                $r['provider'] = (string) $c['provider'];
            }
            if (isset($c['location_mock'])) {
                $r['location_mock'] = $c['location_mock'] !== null ? (int) $c['location_mock'] : null;
            }
        }
        $r['veiculo_id'] = $vid;
        $r['motorista_id'] = (int) $r['motorista_id'];
        $r['latitude'] = $r['latitude'] !== null ? (float) $r['latitude'] : null;
        $r['longitude'] = $r['longitude'] !== null ? (float) $r['longitude'] : null;
        $r['velocidade'] = $r['velocidade'] !== null ? (float) $r['velocidade'] : null;
        if (!array_key_exists('status', $r)) {
            $r['status'] = null;
        }
        if (!array_key_exists('ultima_atualizacao', $r)) {
            $r['ultima_atualizacao'] = null;
        }
        if (!array_key_exists('endereco', $r)) {
            $r['endereco'] = null;
        }
        if (!array_key_exists('bateria_pct', $r)) {
            $r['bateria_pct'] = null;
        } elseif ($r['bateria_pct'] !== null && $r['bateria_pct'] !== '') {
            $r['bateria_pct'] = (int) $r['bateria_pct'];
        } else {
            $r['bateria_pct'] = null;
        }
        $r['ignicao'] = array_key_exists('ignicao', $r) && $r['ignicao'] !== null && $r['ignicao'] !== '' ? (int) $r['ignicao'] : null;
        if (!array_key_exists('accuracy_metros', $r)) {
            $r['accuracy_metros'] = null;
        } elseif ($r['accuracy_metros'] !== null && $r['accuracy_metros'] !== '') {
            $r['accuracy_metros'] = (float) $r['accuracy_metros'];
        } else {
            $r['accuracy_metros'] = null;
        }
        if (!array_key_exists('provider', $r)) {
            $r['provider'] = null;
        }
        if (!array_key_exists('location_mock', $r)) {
            $r['location_mock'] = null;
        } elseif ($r['location_mock'] !== null && $r['location_mock'] !== '') {
            $r['location_mock'] = (int) $r['location_mock'];
        } else {
            $r['location_mock'] = null;
        }
    }
    unset($r);
    api_json_send(['success' => true, 'message' => 'OK', 'data' => ['posicoes' => $rows]]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'gps_ultima_posicao') !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
        api_json_send([
            'success' => true,
            'message' => 'OK',
            'data' => ['posicoes' => []],
        ]);
    }
    error_log('gps_posicoes: ' . $e->getMessage());
    api_json_error('Erro ao carregar posições', 500);
}
