<?php
/**
 * Drill-down do BI: por mês (YYYY-MM) ou por veículo / fatia de pizza no período dos filtros.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/api_json.php';
require_once __DIR__ . '/../includes/permissions.php';

configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_authentication();

api_require_csrf_json();

if (!isset($_SESSION['empresa_id'])) {
    api_json_error('Não autorizado', 401, 'unauthorized');
}

if (!can_access_advanced_reports()) {
    api_json_error('Acesso negado', 403, 'forbidden');
}

$empresa_id = (int) $_SESSION['empresa_id'];
$mes_ano = isset($_GET['mes_ano']) ? trim((string) $_GET['mes_ano']) : '';

$serie = isset($_GET['serie']) ? trim(strtolower((string) $_GET['serie'])) : '';
$monthlySeries = ['rotas', 'abastecimentos', 'manutencoes', 'despesas_viagem', 'despesas_fixas'];
$vehicleSeries = ['rotas_veiculo', 'abast_veiculo', 'manut_veiculo'];

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 200;
$limit = max(1, min(500, $limit));

/**
 * @return array{type: 'range'|'twelve', ini: ?string, fim: ?string}
 */
function bi_drill_bounds(): array
{
    $ano = isset($_GET['ano']) ? (int) $_GET['ano'] : 0;
    $mes = isset($_GET['mes']) ? trim((string) $_GET['mes']) : '';
    if ($ano >= 2000 && $ano <= 2100) {
        $di = ($ano - 1) . '-01-01';
        $df = $ano . '-12-31';
        if ($mes !== '' && preg_match('/^([1-9]|1[0-2])$/', $mes)) {
            $mm = strlen($mes) === 1 ? '0' . $mes : $mes;
            $di = $ano . '-' . $mm . '-01';
            $df = date('Y-m-t', strtotime($di));
        }
        return ['type' => 'range', 'ini' => $di, 'fim' => $df];
    }
    return ['type' => 'twelve', 'ini' => null, 'fim' => null];
}

function bi_assert_veiculo_empresa(PDO $conn, int $empresa_id, int $veiculo_id): void
{
    $st = $conn->prepare('SELECT id FROM veiculos WHERE id = ? AND empresa_id = ? LIMIT 1');
    $st->execute([$veiculo_id, $empresa_id]);
    if (!$st->fetch()) {
        api_json_error('Veículo não encontrado', 404, 'not_found');
    }
}

try {
    $conn = getConnection();
    $bounds = bi_drill_bounds();
    $columns = [];
    $rows = [];

    if (in_array($serie, $monthlySeries, true)) {
        if (!preg_match('/^\d{4}-\d{2}$/', $mes_ano)) {
            api_json_error('Parâmetro mes_ano inválido (use YYYY-MM)', 400, 'invalid_param');
        }
    } elseif (in_array($serie, $vehicleSeries, true) || $serie === 'desp_viagem_pie' || $serie === 'manut_pie') {
        // mes_ano opcional
    } else {
        api_json_error('Parâmetro serie inválido', 400, 'invalid_param');
    }

    if ($serie === 'rotas') {
        $columns = [
            ['key' => 'id', 'label' => 'ID'],
            ['key' => 'data_saida', 'label' => 'Data saída'],
            ['key' => 'rota', 'label' => 'Origem → Destino'],
            ['key' => 'placa', 'label' => 'Veículo'],
            ['key' => 'motorista', 'label' => 'Motorista'],
            ['key' => 'distancia_km', 'label' => 'KM'],
            ['key' => 'frete', 'label' => 'Frete'],
            ['key' => 'comissao', 'label' => 'Comissão'],
            ['key' => 'status', 'label' => 'Status'],
        ];
        $sql = "
            SELECT r.id,
                   DATE(r.data_saida) AS data_saida,
                   CONCAT(COALESCE(co.nome, ''), '/', COALESCE(r.estado_origem, ''), ' → ', COALESCE(cd.nome, ''), '/', COALESCE(r.estado_destino, '')) AS rota,
                   v.placa AS placa,
                   mot.nome AS motorista,
                   r.distancia_km,
                   r.frete,
                   r.comissao,
                   r.status
            FROM rotas r
            LEFT JOIN veiculos v ON v.id = r.veiculo_id AND v.empresa_id = r.empresa_id
            LEFT JOIN motoristas mot ON mot.id = r.motorista_id AND mot.empresa_id = r.empresa_id
            LEFT JOIN cidades co ON co.id = r.cidade_origem_id
            LEFT JOIN cidades cd ON cd.id = r.cidade_destino_id
            WHERE r.empresa_id = :eid
            AND r.data_saida IS NOT NULL
            AND DATE_FORMAT(r.data_saida, '%Y-%m') = :mes
            ORDER BY r.data_saida DESC, r.id DESC
            LIMIT {$limit}
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['eid' => $empresa_id, 'mes' => $mes_ano]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($serie === 'abastecimentos') {
        $columns = [
            ['key' => 'id', 'label' => 'ID'],
            ['key' => 'data_abastecimento', 'label' => 'Data'],
            ['key' => 'placa', 'label' => 'Veículo'],
            ['key' => 'motorista', 'label' => 'Motorista'],
            ['key' => 'posto', 'label' => 'Posto'],
            ['key' => 'litros', 'label' => 'Litros'],
            ['key' => 'valor_total', 'label' => 'Valor'],
            ['key' => 'km_atual', 'label' => 'KM'],
        ];
        $sql = "
            SELECT a.id,
                   DATE(a.data_abastecimento) AS data_abastecimento,
                   v.placa AS placa,
                   mot.nome AS motorista,
                   a.posto,
                   a.litros,
                   a.valor_total,
                   a.km_atual
            FROM abastecimentos a
            LEFT JOIN veiculos v ON v.id = a.veiculo_id AND v.empresa_id = a.empresa_id
            LEFT JOIN motoristas mot ON mot.id = a.motorista_id AND mot.empresa_id = a.empresa_id
            WHERE a.empresa_id = :eid
            AND a.status = 'aprovado'
            AND DATE_FORMAT(a.data_abastecimento, '%Y-%m') = :mes
            ORDER BY a.data_abastecimento DESC, a.id DESC
            LIMIT {$limit}
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['eid' => $empresa_id, 'mes' => $mes_ano]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($serie === 'manutencoes') {
        $columns = [
            ['key' => 'id', 'label' => 'ID'],
            ['key' => 'data_manutencao', 'label' => 'Data'],
            ['key' => 'placa', 'label' => 'Veículo'],
            ['key' => 'tipo', 'label' => 'Tipo'],
            ['key' => 'valor', 'label' => 'Valor'],
            ['key' => 'fornecedor', 'label' => 'Fornecedor'],
            ['key' => 'descricao', 'label' => 'Descrição'],
        ];
        $sql = "
            SELECT m.id,
                   DATE(m.data_manutencao) AS data_manutencao,
                   v.placa AS placa,
                   tm.nome AS tipo,
                   m.valor,
                   m.fornecedor,
                   m.descricao
            FROM manutencoes m
            LEFT JOIN veiculos v ON v.id = m.veiculo_id AND v.empresa_id = m.empresa_id
            LEFT JOIN tipos_manutencao tm ON tm.id = m.tipo_manutencao_id
            WHERE m.empresa_id = :eid
            AND DATE_FORMAT(m.data_manutencao, '%Y-%m') = :mes
            ORDER BY m.data_manutencao DESC, m.id DESC
            LIMIT {$limit}
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['eid' => $empresa_id, 'mes' => $mes_ano]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($serie === 'despesas_viagem') {
        $columns = [
            ['key' => 'rota_id', 'label' => 'Rota'],
            ['key' => 'data_saida', 'label' => 'Data saída'],
            ['key' => 'placa', 'label' => 'Veículo'],
            ['key' => 'total_despviagem', 'label' => 'Total'],
            ['key' => 'pedagios', 'label' => 'Pedágios'],
            ['key' => 'caixinha', 'label' => 'Caixinha'],
            ['key' => 'descarga', 'label' => 'Descarga'],
        ];
        $sql = "
            SELECT r.id AS rota_id,
                   DATE(r.data_saida) AS data_saida,
                   v.placa AS placa,
                   dv.total_despviagem,
                   dv.pedagios,
                   dv.caixinha,
                   dv.descarga
            FROM despesas_viagem dv
            INNER JOIN rotas r ON r.id = dv.rota_id AND r.empresa_id = dv.empresa_id
            LEFT JOIN veiculos v ON v.id = r.veiculo_id AND v.empresa_id = r.empresa_id
            WHERE dv.empresa_id = :eid
            AND r.data_saida IS NOT NULL
            AND DATE_FORMAT(r.data_saida, '%Y-%m') = :mes
            ORDER BY r.data_saida DESC, r.id DESC
            LIMIT {$limit}
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['eid' => $empresa_id, 'mes' => $mes_ano]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($serie === 'despesas_fixas') {
        $columns = [
            ['key' => 'id', 'label' => 'ID'],
            ['key' => 'vencimento', 'label' => 'Vencimento'],
            ['key' => 'data_pagamento', 'label' => 'Pagamento'],
            ['key' => 'placa', 'label' => 'Veículo'],
            ['key' => 'tipo', 'label' => 'Tipo'],
            ['key' => 'valor', 'label' => 'Valor'],
        ];
        $sql = "
            SELECT df.id,
                   DATE(df.vencimento) AS vencimento,
                   DATE(df.data_pagamento) AS data_pagamento,
                   v.placa AS placa,
                   td.nome AS tipo,
                   df.valor
            FROM despesas_fixas df
            LEFT JOIN veiculos v ON v.id = df.veiculo_id AND v.empresa_id = df.empresa_id
            LEFT JOIN tipos_despesa_fixa td ON td.id = df.tipo_despesa_id
            WHERE df.empresa_id = :eid
            AND df.status_pagamento_id = 2
            AND (df.data_pagamento IS NOT NULL OR df.vencimento IS NOT NULL)
            AND DATE_FORMAT(COALESCE(df.data_pagamento, df.vencimento), '%Y-%m') = :mes
            ORDER BY COALESCE(df.data_pagamento, df.vencimento) DESC, df.id DESC
            LIMIT {$limit}
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['eid' => $empresa_id, 'mes' => $mes_ano]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($serie === 'rotas_veiculo') {
        $vid = isset($_GET['veiculo_id']) ? (int) $_GET['veiculo_id'] : 0;
        if ($vid <= 0) {
            api_json_error('veiculo_id obrigatório', 400, 'invalid_param');
        }
        bi_assert_veiculo_empresa($conn, $empresa_id, $vid);
        $columns = [
            ['key' => 'id', 'label' => 'ID'],
            ['key' => 'data_saida', 'label' => 'Data saída'],
            ['key' => 'rota', 'label' => 'Origem → Destino'],
            ['key' => 'motorista', 'label' => 'Motorista'],
            ['key' => 'distancia_km', 'label' => 'KM'],
            ['key' => 'frete', 'label' => 'Frete'],
            ['key' => 'status', 'label' => 'Status'],
        ];
        if ($bounds['type'] === 'range') {
            $sql = "
                SELECT r.id, DATE(r.data_saida) AS data_saida,
                       CONCAT(COALESCE(co.nome, ''), '/', COALESCE(r.estado_origem, ''), ' → ', COALESCE(cd.nome, ''), '/', COALESCE(r.estado_destino, '')) AS rota,
                       mot.nome AS motorista, r.distancia_km, r.frete, r.status
                FROM rotas r
                LEFT JOIN motoristas mot ON mot.id = r.motorista_id AND mot.empresa_id = r.empresa_id
                LEFT JOIN cidades co ON co.id = r.cidade_origem_id
                LEFT JOIN cidades cd ON cd.id = r.cidade_destino_id
                WHERE r.empresa_id = :eid AND r.veiculo_id = :vid AND r.data_saida IS NOT NULL
                AND r.data_saida >= :di AND r.data_saida <= :df
                ORDER BY r.data_saida DESC, r.id DESC
                LIMIT {$limit}
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['eid' => $empresa_id, 'vid' => $vid, 'di' => $bounds['ini'], 'df' => $bounds['fim']]);
        } else {
            $sql = "
                SELECT r.id, DATE(r.data_saida) AS data_saida,
                       CONCAT(COALESCE(co.nome, ''), '/', COALESCE(r.estado_origem, ''), ' → ', COALESCE(cd.nome, ''), '/', COALESCE(r.estado_destino, '')) AS rota,
                       mot.nome AS motorista, r.distancia_km, r.frete, r.status
                FROM rotas r
                LEFT JOIN motoristas mot ON mot.id = r.motorista_id AND mot.empresa_id = r.empresa_id
                LEFT JOIN cidades co ON co.id = r.cidade_origem_id
                LEFT JOIN cidades cd ON cd.id = r.cidade_destino_id
                WHERE r.empresa_id = :eid AND r.veiculo_id = :vid AND r.data_saida IS NOT NULL
                AND r.data_saida >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                ORDER BY r.data_saida DESC, r.id DESC
                LIMIT {$limit}
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['eid' => $empresa_id, 'vid' => $vid]);
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($serie === 'abast_veiculo') {
        $vid = isset($_GET['veiculo_id']) ? (int) $_GET['veiculo_id'] : 0;
        if ($vid <= 0) {
            api_json_error('veiculo_id obrigatório', 400, 'invalid_param');
        }
        bi_assert_veiculo_empresa($conn, $empresa_id, $vid);
        $columns = [
            ['key' => 'id', 'label' => 'ID'],
            ['key' => 'data_abastecimento', 'label' => 'Data'],
            ['key' => 'motorista', 'label' => 'Motorista'],
            ['key' => 'posto', 'label' => 'Posto'],
            ['key' => 'litros', 'label' => 'Litros'],
            ['key' => 'valor_total', 'label' => 'Valor'],
        ];
        if ($bounds['type'] === 'range') {
            $sql = "
                SELECT a.id, DATE(a.data_abastecimento) AS data_abastecimento, mot.nome AS motorista, a.posto, a.litros, a.valor_total
                FROM abastecimentos a
                LEFT JOIN motoristas mot ON mot.id = a.motorista_id AND mot.empresa_id = a.empresa_id
                WHERE a.empresa_id = :eid AND a.veiculo_id = :vid AND a.status = 'aprovado'
                AND a.data_abastecimento >= :di AND a.data_abastecimento <= :df
                ORDER BY a.data_abastecimento DESC, a.id DESC
                LIMIT {$limit}
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['eid' => $empresa_id, 'vid' => $vid, 'di' => $bounds['ini'], 'df' => $bounds['fim']]);
        } else {
            $sql = "
                SELECT a.id, DATE(a.data_abastecimento) AS data_abastecimento, mot.nome AS motorista, a.posto, a.litros, a.valor_total
                FROM abastecimentos a
                LEFT JOIN motoristas mot ON mot.id = a.motorista_id AND mot.empresa_id = a.empresa_id
                WHERE a.empresa_id = :eid AND a.veiculo_id = :vid AND a.status = 'aprovado'
                AND a.data_abastecimento >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                ORDER BY a.data_abastecimento DESC, a.id DESC
                LIMIT {$limit}
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['eid' => $empresa_id, 'vid' => $vid]);
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($serie === 'manut_veiculo') {
        $vid = isset($_GET['veiculo_id']) ? (int) $_GET['veiculo_id'] : 0;
        if ($vid <= 0) {
            api_json_error('veiculo_id obrigatório', 400, 'invalid_param');
        }
        bi_assert_veiculo_empresa($conn, $empresa_id, $vid);
        $columns = [
            ['key' => 'id', 'label' => 'ID'],
            ['key' => 'data_manutencao', 'label' => 'Data'],
            ['key' => 'tipo', 'label' => 'Tipo'],
            ['key' => 'valor', 'label' => 'Valor'],
            ['key' => 'fornecedor', 'label' => 'Fornecedor'],
        ];
        if ($bounds['type'] === 'range') {
            $sql = "
                SELECT m.id, DATE(m.data_manutencao) AS data_manutencao, tm.nome AS tipo, m.valor, m.fornecedor
                FROM manutencoes m
                LEFT JOIN tipos_manutencao tm ON tm.id = m.tipo_manutencao_id
                WHERE m.empresa_id = :eid AND m.veiculo_id = :vid
                AND m.data_manutencao >= :di AND m.data_manutencao <= :df
                ORDER BY m.data_manutencao DESC, m.id DESC
                LIMIT {$limit}
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['eid' => $empresa_id, 'vid' => $vid, 'di' => $bounds['ini'], 'df' => $bounds['fim']]);
        } else {
            $sql = "
                SELECT m.id, DATE(m.data_manutencao) AS data_manutencao, tm.nome AS tipo, m.valor, m.fornecedor
                FROM manutencoes m
                LEFT JOIN tipos_manutencao tm ON tm.id = m.tipo_manutencao_id
                WHERE m.empresa_id = :eid AND m.veiculo_id = :vid
                AND m.data_manutencao >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                ORDER BY m.data_manutencao DESC, m.id DESC
                LIMIT {$limit}
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['eid' => $empresa_id, 'vid' => $vid]);
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($serie === 'desp_viagem_pie') {
        $slice = isset($_GET['slice']) ? (int) $_GET['slice'] : -1;
        if ($slice < 0 || $slice > 3) {
            api_json_error('slice deve ser 0 a 3', 400, 'invalid_param');
        }
        $conds = [
            '(COALESCE(dv.pedagios,0) + 0) > 0',
            '(COALESCE(dv.caixinha,0) + 0) > 0',
            '((COALESCE(dv.estacionamento,0) + COALESCE(dv.lavagem,0)) + 0) > 0',
            '((COALESCE(dv.descarga,0) + COALESCE(dv.borracharia,0) + COALESCE(dv.eletrica_mecanica,0) + COALESCE(dv.adiantamento,0)) + 0) > 0',
        ];
        $whereExtra = $conds[$slice];
        $columns = [
            ['key' => 'rota_id', 'label' => 'Rota'],
            ['key' => 'data_saida', 'label' => 'Data saída'],
            ['key' => 'placa', 'label' => 'Veículo'],
            ['key' => 'pedagios', 'label' => 'Pedágios'],
            ['key' => 'caixinha', 'label' => 'Caixinha'],
            ['key' => 'total_despviagem', 'label' => 'Total'],
        ];
        if ($bounds['type'] === 'range') {
            $sql = "
                SELECT r.id AS rota_id, DATE(r.data_saida) AS data_saida, v.placa AS placa,
                       dv.pedagios, dv.caixinha, dv.total_despviagem
                FROM despesas_viagem dv
                INNER JOIN rotas r ON r.id = dv.rota_id AND r.empresa_id = dv.empresa_id
                LEFT JOIN veiculos v ON v.id = r.veiculo_id AND v.empresa_id = r.empresa_id
                WHERE dv.empresa_id = :eid AND r.data_saida IS NOT NULL
                AND r.data_saida >= :di AND r.data_saida <= :df
                AND {$whereExtra}
                ORDER BY r.data_saida DESC, r.id DESC
                LIMIT {$limit}
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['eid' => $empresa_id, 'di' => $bounds['ini'], 'df' => $bounds['fim']]);
        } else {
            $sql = "
                SELECT r.id AS rota_id, DATE(r.data_saida) AS data_saida, v.placa AS placa,
                       dv.pedagios, dv.caixinha, dv.total_despviagem
                FROM despesas_viagem dv
                INNER JOIN rotas r ON r.id = dv.rota_id AND r.empresa_id = dv.empresa_id
                LEFT JOIN veiculos v ON v.id = r.veiculo_id AND v.empresa_id = r.empresa_id
                WHERE dv.empresa_id = :eid AND r.data_saida IS NOT NULL
                AND r.data_saida >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                AND {$whereExtra}
                ORDER BY r.data_saida DESC, r.id DESC
                LIMIT {$limit}
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['eid' => $empresa_id]);
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($serie === 'manut_pie') {
        $tipo = isset($_GET['tipo_pie']) ? trim(strtolower((string) $_GET['tipo_pie'])) : '';
        if (!in_array($tipo, ['preventiva', 'corretiva'], true)) {
            api_json_error('tipo_pie deve ser preventiva ou corretiva', 400, 'invalid_param');
        }
        $like = $tipo === 'preventiva' ? '%prevent%' : '%corret%';
        $columns = [
            ['key' => 'id', 'label' => 'ID'],
            ['key' => 'data_manutencao', 'label' => 'Data'],
            ['key' => 'placa', 'label' => 'Veículo'],
            ['key' => 'tipo', 'label' => 'Tipo'],
            ['key' => 'valor', 'label' => 'Valor'],
        ];
        if ($bounds['type'] === 'range') {
            $sql = "
                SELECT m.id, DATE(m.data_manutencao) AS data_manutencao, v.placa AS placa, tm.nome AS tipo, m.valor
                FROM manutencoes m
                LEFT JOIN tipos_manutencao tm ON tm.id = m.tipo_manutencao_id
                LEFT JOIN veiculos v ON v.id = m.veiculo_id AND v.empresa_id = m.empresa_id
                WHERE m.empresa_id = :eid
                AND m.data_manutencao >= :di AND m.data_manutencao <= :df
                AND tm.nome IS NOT NULL AND LOWER(tm.nome) LIKE :likepat
                ORDER BY m.data_manutencao DESC, m.id DESC
                LIMIT {$limit}
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['eid' => $empresa_id, 'di' => $bounds['ini'], 'df' => $bounds['fim'], 'likepat' => $like]);
        } else {
            $sql = "
                SELECT m.id, DATE(m.data_manutencao) AS data_manutencao, v.placa AS placa, tm.nome AS tipo, m.valor
                FROM manutencoes m
                LEFT JOIN tipos_manutencao tm ON tm.id = m.tipo_manutencao_id
                LEFT JOIN veiculos v ON v.id = m.veiculo_id AND v.empresa_id = m.empresa_id
                WHERE m.empresa_id = :eid
                AND m.data_manutencao >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                AND tm.nome IS NOT NULL AND LOWER(tm.nome) LIKE :likepat
                ORDER BY m.data_manutencao DESC, m.id DESC
                LIMIT {$limit}
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['eid' => $empresa_id, 'likepat' => $like]);
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'mes_ano' => $mes_ano !== '' ? $mes_ano : null,
        'serie' => $serie,
        'columns' => $columns,
        'rows' => $rows,
        'total' => count($rows),
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log('bi_drill_down: ' . $e->getMessage());
    }
    api_json_error('Erro ao carregar detalhes', 500, 'server_error');
}
