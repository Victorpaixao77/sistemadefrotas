<?php
/**
 * GET — últimos alertas de cerca (entrou/saiu), sessão painel.
 * Query opcional: limite (default 100, máx 500)
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

$limite = isset($_GET['limite']) ? (int) $_GET['limite'] : 100;
$limite = max(1, min(500, $limite));

try {
    $conn = getConnection();
    $sql = '
        SELECT a.id, a.cerca_id, c.nome AS cerca_nome, a.veiculo_id, v.placa,
               a.motorista_id, m.nome AS motorista_nome, a.tipo,
               a.latitude, a.longitude, a.data_hora
        FROM gps_cerca_alertas a
        INNER JOIN gps_cercas c ON c.id = a.cerca_id AND c.empresa_id = :eid
        LEFT JOIN veiculos v ON v.id = a.veiculo_id AND v.empresa_id = :eid2
        LEFT JOIN motoristas m ON m.id = a.motorista_id AND m.empresa_id = :eid3
        WHERE a.empresa_id = :eid4
        ORDER BY a.data_hora DESC, a.id DESC
        LIMIT ' . (int) $limite;

    $stmt = $conn->prepare($sql);
    $stmt->execute([':eid' => $empresa_id, ':eid2' => $empresa_id, ':eid3' => $empresa_id, ':eid4' => $empresa_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['cerca_id'] = (int) $r['cerca_id'];
        $r['veiculo_id'] = (int) $r['veiculo_id'];
        $r['motorista_id'] = (int) $r['motorista_id'];
        $r['latitude'] = $r['latitude'] !== null ? (float) $r['latitude'] : null;
        $r['longitude'] = $r['longitude'] !== null ? (float) $r['longitude'] : null;
    }
    unset($r);
    api_json_send(['success' => true, 'message' => 'OK', 'data' => ['alertas' => $rows]]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'gps_cerca_alertas') !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
        api_json_send(['success' => true, 'message' => 'OK', 'data' => ['alertas' => []]]);
    }
    error_log('gps_cerca_alertas: ' . $e->getMessage());
    api_json_error('Erro ao carregar alertas', 500);
}
