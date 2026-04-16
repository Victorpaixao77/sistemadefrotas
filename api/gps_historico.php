<?php
/**
 * GET — pontos GPS de um veículo no período (sessão painel web).
 * Query: veiculo_id (obrigatório), data_inicio, data_fim (Y-m-d ou Y-m-d H:i:s),
 *        opcional limite (default 2000, máx 5000).
 * Se data_inicio/data_fim omitidos: últimas 24 horas.
 * Intervalo máximo entre início e fim: 7 dias.
 *
 * Resposta: { success, data: { pontos: [ { latitude, longitude, velocidade, data_hora } ], placa, total } }
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

$veiculo_id = isset($_GET['veiculo_id']) ? (int) $_GET['veiculo_id'] : 0;
if ($veiculo_id <= 0) {
    api_json_error('Informe veiculo_id.', 400);
}

$limite = isset($_GET['limite']) ? (int) $_GET['limite'] : 2000;
$limite = max(1, min(5000, $limite));

$veiculo = null;
try {
    $conn = getConnection();

    $stV = $conn->prepare('SELECT id, placa FROM veiculos WHERE id = :id AND empresa_id = :eid LIMIT 1');
    $stV->execute([':id' => $veiculo_id, ':eid' => $empresa_id]);
    $veiculo = $stV->fetch(PDO::FETCH_ASSOC);
    if (!$veiculo) {
        api_json_error('Veículo não encontrado.', 404);
    }

    $inStr = isset($_GET['data_inicio']) ? trim((string) $_GET['data_inicio']) : '';
    $fimStr = isset($_GET['data_fim']) ? trim((string) $_GET['data_fim']) : '';

    $now = new DateTimeImmutable('now');
    try {
        if ($inStr === '' && $fimStr === '') {
            $dtFim = $now;
            $dtIni = $now->sub(new DateInterval('P1D'));
        } elseif ($inStr !== '' && $fimStr === '') {
            $dtIni = new DateTimeImmutable($inStr);
            $dtFim = $now;
        } elseif ($inStr === '' && $fimStr !== '') {
            $dtFim = new DateTimeImmutable($fimStr);
            $dtIni = $dtFim->sub(new DateInterval('P1D'));
        } else {
            $dtIni = new DateTimeImmutable($inStr);
            $dtFim = new DateTimeImmutable($fimStr);
        }
    } catch (Exception $e) {
        api_json_error('Datas inválidas. Use YYYY-MM-DD ou YYYY-MM-DD HH:MM:SS.', 400);
    }

    if ($dtIni > $dtFim) {
        $tmp = $dtIni;
        $dtIni = $dtFim;
        $dtFim = $tmp;
    }

    $maxSpan = new DateInterval('P7D');
    $spanLimit = $dtIni->add($maxSpan);
    if ($dtFim > $spanLimit) {
        $dtFim = $spanLimit;
    }

    $sqlExt = '
        SELECT latitude, longitude, velocidade, data_hora, accuracy_metros, status, provider, location_mock
        FROM gps_logs
        WHERE empresa_id = :eid
          AND veiculo_id = :vid
          AND data_hora >= :ini
          AND data_hora <= :fim
        ORDER BY data_hora ASC
        LIMIT ' . (int) $limite;
    $sqlBase = '
        SELECT latitude, longitude, velocidade, data_hora
        FROM gps_logs
        WHERE empresa_id = :eid
          AND veiculo_id = :vid
          AND data_hora >= :ini
          AND data_hora <= :fim
        ORDER BY data_hora ASC
        LIMIT ' . (int) $limite;

    $paramsH = [
        ':eid' => $empresa_id,
        ':vid' => $veiculo_id,
        ':ini' => $dtIni->format('Y-m-d H:i:s'),
        ':fim' => $dtFim->format('Y-m-d H:i:s'),
    ];
    try {
        $stmt = $conn->prepare($sqlExt);
        $stmt->execute($paramsH);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Unknown column') === false) {
            throw $e;
        }
        $stmt = $conn->prepare($sqlBase);
        $stmt->execute($paramsH);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    foreach ($rows as &$r) {
        $r['latitude'] = $r['latitude'] !== null ? (float) $r['latitude'] : null;
        $r['longitude'] = $r['longitude'] !== null ? (float) $r['longitude'] : null;
        $r['velocidade'] = $r['velocidade'] !== null ? (float) $r['velocidade'] : null;
        if (array_key_exists('accuracy_metros', $r) && $r['accuracy_metros'] !== null && $r['accuracy_metros'] !== '') {
            $r['accuracy_metros'] = (float) $r['accuracy_metros'];
        }
        if (array_key_exists('location_mock', $r) && $r['location_mock'] !== null && $r['location_mock'] !== '') {
            $r['location_mock'] = (int) $r['location_mock'];
        }
    }
    unset($r);

    api_json_send([
        'success' => true,
        'message' => 'OK',
        'data' => [
            'pontos' => $rows,
            'placa' => $veiculo['placa'],
            'veiculo_id' => $veiculo_id,
            'total' => count($rows),
            'periodo' => [
                'data_inicio' => $dtIni->format('Y-m-d H:i:s'),
                'data_fim' => $dtFim->format('Y-m-d H:i:s'),
            ],
        ],
    ]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'gps_logs') !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
        api_json_send([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'pontos' => [],
                'placa' => is_array($veiculo) ? ($veiculo['placa'] ?? '') : '',
                'veiculo_id' => $veiculo_id,
                'total' => 0,
                'periodo' => [],
            ],
        ]);
    }
    error_log('gps_historico: ' . $e->getMessage());
    api_json_error('Erro ao carregar histórico', 500);
}
