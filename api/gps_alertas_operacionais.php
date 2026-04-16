<?php
/**
 * GET — alertas operacionais GPS (bateria, velocidade, mock, perda de sinal, salto suspeito, ignição parado, etc.).
 * Query: limite (default 50, máx 200), opcional veiculo_id.
 * Requer tabela gps_alertas_operacionais (sql/alter_gps_profissional.sql) e SF_GPS_ALERTAS_OPERACIONAIS=1 nos envios.
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db_connect.php';
require_once dirname(__DIR__) . '/includes/api_json.php';

configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    api_json_method_not_allowed();
}

require_authentication();

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if ($empresa_id <= 0) {
    api_json_unauthorized();
}

$lim = isset($_GET['limite']) ? (int) $_GET['limite'] : 50;
$lim = max(1, min(200, $lim));
$vid = isset($_GET['veiculo_id']) ? (int) $_GET['veiculo_id'] : 0;

try {
    $conn = getConnection();
    $baseFrom = '
        FROM gps_alertas_operacionais a
        LEFT JOIN veiculos v ON v.id = a.veiculo_id AND v.empresa_id = :eid2
        LEFT JOIN motoristas m ON m.id = a.motorista_id AND m.empresa_id = :eid3
    ';
    $baseSelect = '
        SELECT a.id, a.veiculo_id, a.motorista_id, a.tipo, a.mensagem, a.latitude, a.longitude, a.data_hora, a.extra_json,
               v.placa, m.nome AS motorista_nome
    ';
    if ($vid > 0) {
        $st = $conn->prepare(
            $baseSelect . $baseFrom . '
            WHERE a.empresa_id = :eid AND a.veiculo_id = :vid
            ORDER BY a.data_hora DESC
            LIMIT ' . (int) $lim
        );
        $st->execute([':eid' => $empresa_id, ':eid2' => $empresa_id, ':eid3' => $empresa_id, ':vid' => $vid]);
    } else {
        $st = $conn->prepare(
            $baseSelect . $baseFrom . '
            WHERE a.empresa_id = :eid
            ORDER BY a.data_hora DESC
            LIMIT ' . (int) $lim
        );
        $st->execute([':eid' => $empresa_id, ':eid2' => $empresa_id, ':eid3' => $empresa_id]);
    }
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        if (!empty($r['extra_json']) && is_string($r['extra_json'])) {
            $j = json_decode($r['extra_json'], true);
            $r['extra'] = is_array($j) ? $j : null;
        } else {
            $r['extra'] = null;
        }
        unset($r['extra_json']);
    }
    unset($r);
    api_json_send(['success' => true, 'message' => 'OK', 'data' => ['alertas' => $rows]]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'gps_alertas_operacionais') !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
        api_json_send(['success' => true, 'message' => 'OK', 'data' => ['alertas' => []]]);
    }
    error_log('gps_alertas_operacionais: ' . $e->getMessage());
    api_json_error('Erro ao carregar alertas', 500);
}
