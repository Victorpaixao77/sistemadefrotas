<?php
/**
 * GET — posição(ões) atual(ais) na tabela gps_ultima_posicao (token motorista).
 * Query opcional: veiculo_id (int). Sem parâmetro: todos os veículos da empresa.
 */
require_once dirname(__DIR__, 2) . '/app_android/config.php';
require_once dirname(__DIR__, 2) . '/includes/gps_motorista_api_read.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    api_error('Método não permitido', 405);
}

require_motorista_token();
$empresa_id = (int) get_empresa_id();
$veiculo_id = isset($_GET['veiculo_id']) ? (int) $_GET['veiculo_id'] : 0;

try {
    $conn = getConnection();

    if ($veiculo_id > 0) {
        $v = gps_motorista_api_veiculo_empresa($conn, $veiculo_id, $empresa_id);
        if (!$v) {
            api_error('Veículo não encontrado.', 404);
        }
        $sqlExt = '
            SELECT g.veiculo_id, g.latitude, g.longitude, g.velocidade, g.motorista_id, m.nome AS motorista_nome,
                   g.data_hora, g.ultima_atualizacao, g.status, g.endereco, g.bateria_pct, g.accuracy_metros, g.provider, g.location_mock
            FROM gps_ultima_posicao g
            LEFT JOIN motoristas m ON m.id = g.motorista_id AND m.empresa_id = :eid2
            WHERE g.empresa_id = :eid AND g.veiculo_id = :vid
            LIMIT 1';
        $sqlBase = '
            SELECT g.veiculo_id, g.latitude, g.longitude, g.velocidade, g.motorista_id, m.nome AS motorista_nome, g.data_hora
            FROM gps_ultima_posicao g
            LEFT JOIN motoristas m ON m.id = g.motorista_id AND m.empresa_id = :eid2
            WHERE g.empresa_id = :eid AND g.veiculo_id = :vid
            LIMIT 1';
        $params = [':eid' => $empresa_id, ':eid2' => $empresa_id, ':vid' => $veiculo_id];
        try {
            $st = $conn->prepare($sqlExt);
            $st->execute($params);
            $row = $st->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Unknown column') === false) {
                throw $e;
            }
            $st = $conn->prepare($sqlBase);
            $st->execute($params);
            $row = $st->fetch(PDO::FETCH_ASSOC);
        }
        if (!$row) {
            api_success([
                'veiculo' => $v,
                'posicao' => null,
            ], 'Sem posição registrada para este veículo.');
        }
        $row['latitude'] = $row['latitude'] !== null ? (float) $row['latitude'] : null;
        $row['longitude'] = $row['longitude'] !== null ? (float) $row['longitude'] : null;
        $row['velocidade'] = $row['velocidade'] !== null ? (float) $row['velocidade'] : null;
        api_success([
            'veiculo' => $v,
            'posicao' => $row,
        ]);
    }

    $params = [':eid' => $empresa_id, ':eid2' => $empresa_id, ':eid3' => $empresa_id];
    $sqlExt = '
        SELECT g.veiculo_id, v.placa, v.modelo, g.motorista_id, m.nome AS motorista_nome,
               g.latitude, g.longitude, g.velocidade, g.data_hora, g.ultima_atualizacao, g.status, g.endereco
        FROM gps_ultima_posicao g
        INNER JOIN veiculos v ON v.id = g.veiculo_id AND v.empresa_id = :eid
        LEFT JOIN motoristas m ON m.id = g.motorista_id AND m.empresa_id = :eid2
        WHERE g.empresa_id = :eid3
        ORDER BY v.placa ASC';
    $sqlBase = '
        SELECT g.veiculo_id, v.placa, v.modelo, g.motorista_id, m.nome AS motorista_nome,
               g.latitude, g.longitude, g.velocidade, g.data_hora
        FROM gps_ultima_posicao g
        INNER JOIN veiculos v ON v.id = g.veiculo_id AND v.empresa_id = :eid
        LEFT JOIN motoristas m ON m.id = g.motorista_id AND m.empresa_id = :eid2
        WHERE g.empresa_id = :eid3
        ORDER BY v.placa ASC';
    try {
        $st = $conn->prepare($sqlExt);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Unknown column') === false) {
            throw $e;
        }
        $st = $conn->prepare($sqlBase);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    }
    foreach ($rows as &$r) {
        $r['latitude'] = $r['latitude'] !== null ? (float) $r['latitude'] : null;
        $r['longitude'] = $r['longitude'] !== null ? (float) $r['longitude'] : null;
        $r['velocidade'] = $r['velocidade'] !== null ? (float) $r['velocidade'] : null;
    }
    unset($r);

    api_success(['posicoes' => $rows, 'total' => count($rows)]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'gps_ultima_posicao') !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
        api_error('Tabelas GPS não instaladas.', 503);
    }
    error_log('gps posicao_atual: ' . $e->getMessage());
    api_error('Erro ao ler posições.', 500);
}
