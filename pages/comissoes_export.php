<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';

configure_session();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_authentication();

$empresa_id = $_SESSION['empresa_id'];

$filters = [
    'mes' => '',
    'motorista' => '',
    'veiculo' => ''
];

if (!empty($_GET['mes']) && preg_match('/^\d{4}-\d{2}$/', $_GET['mes'])) {
    $filters['mes'] = $_GET['mes'];
}

if (!empty($_GET['motorista'])) {
    $filters['motorista'] = $_GET['motorista'];
}

if (!empty($_GET['veiculo'])) {
    $filters['veiculo'] = $_GET['veiculo'];
}

$conn = getConnection();
$conditions = [
    "r.empresa_id = :empresa_id",
    "r.comissao > 0",
    "r.status = 'aprovado'"
];
$params = [
    ':empresa_id' => $empresa_id
];

if (!empty($filters['mes'])) {
    $conditions[] = "DATE_FORMAT(r.data_rota, '%Y-%m') = :mes";
    $params[':mes'] = $filters['mes'];
}

if (!empty($filters['motorista'])) {
    $conditions[] = "r.motorista_id = :motorista_id";
    $params[':motorista_id'] = (int) $filters['motorista'];
}

if (!empty($filters['veiculo'])) {
    $conditions[] = "r.veiculo_id = :veiculo_id";
    $params[':veiculo_id'] = (int) $filters['veiculo'];
}

$whereClause = implode(' AND ', $conditions);

$sql = "
    SELECT 
        r.id,
        r.data_rota,
        r.data_saida,
        r.frete,
        r.comissao,
        r.no_prazo,
        r.status,
        v.placa AS veiculo_placa,
        v.modelo AS veiculo_modelo,
        m.nome AS motorista_nome,
        cp.status AS status_pagamento,
        cp.data_pagamento
    FROM rotas r
    LEFT JOIN veiculos v ON r.veiculo_id = v.id
    LEFT JOIN motoristas m ON r.motorista_id = m.id
    LEFT JOIN comissoes_pagamentos cp ON cp.rota_id = r.id
    WHERE {$whereClause}
    ORDER BY r.data_rota DESC, r.id DESC";

$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="comissoes.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

fputcsv($output, [
    'Data',
    'Motorista',
    'Veículo',
    'Frete (R$)',
    'Comissão (R$)',
    '% Comissão',
    'Status Pagamento',
    'Data Pagamento',
    'Prazo'
], ';');

foreach ($registros as $reg) {
    $dataRota = !empty($reg['data_rota']) ? date('d/m/Y', strtotime($reg['data_rota'])) : date('d/m/Y', strtotime($reg['data_saida']));
    $percentual = ($reg['frete'] > 0) ? ($reg['comissao'] / $reg['frete']) * 100 : 0;
    $statusPagamento = $reg['status_pagamento'] ?? 'pendente';
    $labelPagamento = $statusPagamento === 'pago' ? 'Pago' : 'Pendente';
    $dataPagamento = !empty($reg['data_pagamento']) ? date('d/m/Y', strtotime($reg['data_pagamento'])) : '-';

    fputcsv($output, [
        $dataRota,
        $reg['motorista_nome'] ?? 'Motorista não informado',
        trim(($reg['veiculo_placa'] ?? '-') . ' ' . ($reg['veiculo_modelo'] ?? '')),
        number_format($reg['frete'], 2, ',', '.'),
        number_format($reg['comissao'], 2, ',', '.'),
        number_format($percentual, 2, ',', '.') . '%',
        $labelPagamento,
        $dataPagamento,
        !empty($reg['no_prazo']) ? 'No prazo' : 'Fora do prazo'
    ], ';');
}

fclose($output);
exit;

