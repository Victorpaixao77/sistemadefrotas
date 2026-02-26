<?php
/**
 * Exportação de rotas em CSV (Excel).
 * Respeita os mesmos filtros da listagem: search, driver, status, date, date_from/date_to, month.
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
        r.id,
        r.data_rota,
        r.data_saida,
        r.data_chegada,
        m.nome AS motorista_nome,
        v.placa AS veiculo_placa,
        co.nome AS cidade_origem_nome,
        cd.nome AS cidade_destino_nome,
        r.distancia_km,
        r.frete,
        r.no_prazo,
        r.eficiencia_viagem,
        r.percentual_vazio,
        r.comissao,
        r.observacoes
    FROM rotas r
    LEFT JOIN motoristas m ON r.motorista_id = m.id
    LEFT JOIN veiculos v ON r.veiculo_id = v.id
    LEFT JOIN cidades co ON r.cidade_origem_id = co.id
    LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
    WHERE r.empresa_id = :empresa_id AND r.status = 'aprovado'";

    $params = [':empresa_id' => $empresa_id];

    if (!empty($_GET['search'])) {
        $search = '%' . trim($_GET['search']) . '%';
        $sql .= " AND (
            COALESCE(co.nome, '') LIKE :search
            OR COALESCE(cd.nome, '') LIKE :search
            OR COALESCE(m.nome, '') LIKE :search
            OR COALESCE(v.placa, '') LIKE :search
            OR COALESCE(v.modelo, '') LIKE :search
            OR CAST(r.id AS CHAR) LIKE :search
        )";
        $params[':search'] = $search;
    }

    if (!empty($_GET['driver'])) {
        $sql .= " AND r.motorista_id = :driver";
        $params[':driver'] = $_GET['driver'];
    }

    if (!empty($_GET['status'])) {
        $sql .= " AND r.no_prazo = :status";
        $params[':status'] = $_GET['status'] === 'no_prazo' ? 1 : 0;
    }

    if (!empty($_GET['date'])) {
        $sql .= " AND DATE(r.data_rota) = :date";
        $params[':date'] = $_GET['date'];
    }

    if (!empty($_GET['date_from'])) {
        $sql .= " AND DATE(r.data_rota) >= :date_from";
        $params[':date_from'] = $_GET['date_from'];
    }

    if (!empty($_GET['date_to'])) {
        $sql .= " AND DATE(r.data_rota) <= :date_to";
        $params[':date_to'] = $_GET['date_to'];
    }

    if (!empty($_GET['year']) && !empty($_GET['month'])) {
        $sql .= " AND YEAR(r.data_rota) = :year AND MONTH(r.data_rota) = :month";
        $params[':year'] = (int) $_GET['year'];
        $params[':month'] = (int) $_GET['month'];
    }

    $sql .= " ORDER BY r.data_rota DESC, r.id DESC LIMIT 10000";

    $stmt = $conn->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'rotas_' . date('Y-m-d_H-i') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");

    $headers = ['ID', 'Data Rota', 'Saída', 'Chegada', 'Motorista', 'Veículo', 'Origem', 'Destino', 'Distância (km)', 'Frete', 'No Prazo', 'Eficiência', '% Vazio', 'Comissão', 'Observações'];
    fputcsv($out, $headers, ';');

    foreach ($rows as $row) {
        $dataRota = !empty($row['data_rota']) ? date('d/m/Y', strtotime($row['data_rota'])) : '';
        $saida = !empty($row['data_saida']) ? date('d/m/Y H:i', strtotime($row['data_saida'])) : '';
        $chegada = !empty($row['data_chegada']) ? date('d/m/Y H:i', strtotime($row['data_chegada'])) : '';
        $noPrazo = isset($row['no_prazo']) ? ($row['no_prazo'] ? 'Sim' : 'Não') : '';
        fputcsv($out, [
            $row['id'],
            $dataRota,
            $saida,
            $chegada,
            $row['motorista_nome'] ?? '',
            $row['veiculo_placa'] ?? '',
            $row['cidade_origem_nome'] ?? '',
            $row['cidade_destino_nome'] ?? '',
            $row['distancia_km'] !== null ? str_replace('.', ',', (string) $row['distancia_km']) : '',
            $row['frete'] !== null ? str_replace('.', ',', number_format((float) $row['frete'], 2, '.', '')) : '',
            $noPrazo,
            $row['eficiencia_viagem'] !== null ? str_replace('.', ',', (string) $row['eficiencia_viagem']) : '',
            $row['percentual_vazio'] !== null ? str_replace('.', ',', (string) $row['percentual_vazio']) : '',
            $row['comissao'] !== null ? str_replace('.', ',', number_format((float) $row['comissao'], 2, '.', '')) : '',
            $row['observacoes'] ?? ''
        ], ';');
    }

    fclose($out);
    exit;
} catch (Throwable $e) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log('Erro na exportação de rotas: ' . $e->getMessage());
    }
    http_response_code(500);
    exit('Erro ao gerar exportação de rotas.');
}
