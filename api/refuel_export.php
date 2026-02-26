<?php
/**
 * Exportação de abastecimentos em CSV (Excel).
 * Respeita os mesmos filtros da listagem: search, veiculo, motorista,
 * combustivel, pagamento, year/month e date_from/date_to.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_authentication();

$empresa_id = isset($_SESSION['empresa_id']) ? $_SESSION['empresa_id'] : null;
if (!$empresa_id) {
    http_response_code(403);
    exit('Acesso negado');
}

try {
    $conn = getConnection();

    $sql = "SELECT 
        a.id,
        a.data_abastecimento,
        v.placa AS veiculo_placa,
        m.nome AS motorista_nome,
        a.posto,
        a.tipo_combustivel,
        a.litros,
        a.valor_litro,
        a.valor_total,
        a.inclui_arla,
        a.litros_arla,
        a.valor_total_arla,
        a.km_atual,
        a.forma_pagamento,
        co.nome AS cidade_origem_nome,
        cd.nome AS cidade_destino_nome,
        a.observacoes
    FROM abastecimentos a
    LEFT JOIN veiculos v ON a.veiculo_id = v.id
    LEFT JOIN motoristas m ON a.motorista_id = m.id
    LEFT JOIN rotas r ON a.rota_id = r.id
    LEFT JOIN cidades co ON r.cidade_origem_id = co.id
    LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
    WHERE a.empresa_id = :empresa_id
      AND a.status = 'aprovado'";

    $params = [':empresa_id' => $empresa_id];

    if (!empty($_GET['search'])) {
        $search = '%' . trim($_GET['search']) . '%';
        $sql .= " AND (
            COALESCE(v.placa, '') LIKE :search
            OR COALESCE(m.nome, '') LIKE :search
            OR COALESCE(a.posto, '') LIKE :search
            OR COALESCE(a.tipo_combustivel, '') LIKE :search
            OR COALESCE(a.forma_pagamento, '') LIKE :search
            OR COALESCE(co.nome, '') LIKE :search
            OR COALESCE(cd.nome, '') LIKE :search
        )";
        $params[':search'] = $search;
    }

    if (!empty($_GET['veiculo'])) {
        $sql .= " AND a.veiculo_id = :veiculo";
        $params[':veiculo'] = $_GET['veiculo'];
    }

    if (!empty($_GET['motorista'])) {
        $sql .= " AND a.motorista_id = :motorista";
        $params[':motorista'] = $_GET['motorista'];
    }

    if (!empty($_GET['combustivel'])) {
        $sql .= " AND a.tipo_combustivel = :combustivel";
        $params[':combustivel'] = $_GET['combustivel'];
    }

    if (!empty($_GET['pagamento'])) {
        $sql .= " AND a.forma_pagamento = :pagamento";
        $params[':pagamento'] = $_GET['pagamento'];
    }

    if (!empty($_GET['year']) && !empty($_GET['month'])) {
        $sql .= " AND YEAR(a.data_abastecimento) = :year AND MONTH(a.data_abastecimento) = :month";
        $params[':year'] = (int) $_GET['year'];
        $params[':month'] = (int) $_GET['month'];
    }

    if (!empty($_GET['date_from'])) {
        $sql .= " AND DATE(a.data_abastecimento) >= :date_from";
        $params[':date_from'] = $_GET['date_from'];
    }

    if (!empty($_GET['date_to'])) {
        $sql .= " AND DATE(a.data_abastecimento) <= :date_to";
        $params[':date_to'] = $_GET['date_to'];
    }

    $sql .= " ORDER BY a.data_abastecimento DESC, a.id DESC LIMIT 10000";

    $stmt = $conn->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'abastecimentos_' . date('Y-m-d_H-i') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');

    // BOM UTF-8 para Excel
    fprintf($out, "\xEF\xBB\xBF");

    $headers = [
        'ID',
        'Data',
        'Veículo',
        'Motorista',
        'Posto',
        'Combustível',
        'Litros',
        'Valor/L',
        'Valor Total',
        'Inclui ARLA',
        'Litros ARLA',
        'Valor ARLA',
        'Km',
        'Forma Pgto',
        'Origem',
        'Destino',
        'Observações'
    ];

    fputcsv($out, $headers, ';');

    foreach ($rows as $row) {
        $data_br = '';
        if (!empty($row['data_abastecimento'])) {
            $ts = strtotime($row['data_abastecimento']);
            $data_br = $ts ? date('d/m/Y H:i', $ts) : '';
        }

        $valor_total = (float) ($row['valor_total'] ?? 0);
        if (!empty($row['inclui_arla']) && (int) $row['inclui_arla'] === 1) {
            $valor_total += (float) ($row['valor_total_arla'] ?? 0);
        }

        fputcsv($out, [
            $row['id'],
            $data_br,
            $row['veiculo_placa'] ?? '',
            $row['motorista_nome'] ?? '',
            $row['posto'] ?? '',
            $row['tipo_combustivel'] ?? '',
            $row['litros'] !== null ? str_replace('.', ',', (string) $row['litros']) : '',
            $row['valor_litro'] !== null ? str_replace('.', ',', (string) $row['valor_litro']) : '',
            str_replace('.', ',', number_format($valor_total, 2, '.', '')),
            !empty($row['inclui_arla']) ? 'Sim' : 'Não',
            $row['litros_arla'] !== null ? str_replace('.', ',', (string) $row['litros_arla']) : '',
            $row['valor_total_arla'] !== null ? str_replace('.', ',', (string) $row['valor_total_arla']) : '',
            $row['km_atual'] ?? '',
            $row['forma_pagamento'] ?? '',
            $row['cidade_origem_nome'] ?? '',
            $row['cidade_destino_nome'] ?? '',
            $row['observacoes'] ?? ''
        ], ';');
    }

    fclose($out);
    exit;
} catch (Throwable $e) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log('Erro na exportação de abastecimentos: ' . $e->getMessage());
    }
    http_response_code(500);
    exit('Erro ao gerar exportação de abastecimentos.');
}

