<?php
/**
 * Exportação da lista de veículos em CSV (Excel).
 * Mesmos filtros da listagem: search, status, marca.
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

    $where = ['v.empresa_id = :empresa_id'];
    $params = [':empresa_id' => $empresa_id];

    $search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
    if ($search !== '') {
        $like = '%' . $search . '%';
        $where[] = '(v.placa LIKE :search_placa OR v.modelo LIKE :search_modelo OR v.marca LIKE :search_marca)';
        $params[':search_placa'] = $like;
        $params[':search_modelo'] = $like;
        $params[':search_marca'] = $like;
    }

    $status = isset($_GET['status']) ? $_GET['status'] : '';
    if ($status !== '' && in_array($status, ['1', '2', '3'], true)) {
        $where[] = 'v.status_id = :status_id';
        $params[':status_id'] = (int) $status;
    }

    $marca = isset($_GET['marca']) ? trim((string) $_GET['marca']) : '';
    if ($marca !== '') {
        $where[] = 'v.marca LIKE :marca';
        $params[':marca'] = '%' . $marca . '%';
    }

    $whereClause = implode(' AND ', $where);

    $sql = "SELECT v.id, v.placa, v.modelo, v.marca, v.ano, v.km_atual,
                s.nome AS status_nome,
                cv.nome AS cavalo_nome,
                cv.eixos AS cavalo_eixos,
                cv.tracao AS cavalo_tracao,
                cr.nome AS carreta_nome,
                cr.capacidade_media AS carreta_capacidade
            FROM veiculos v
            LEFT JOIN status_veiculos s ON v.status_id = s.id
            LEFT JOIN tipos_cavalos cv ON v.id_cavalo = cv.id
            LEFT JOIN tipos_carretas cr ON v.id_carreta = cr.id
            WHERE $whereClause
            ORDER BY v.id DESC
            LIMIT 10000";

    $stmt = $conn->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'veiculos_' . date('Y-m-d_H-i') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");

    fputcsv($out, ['Sistema de Frotas — Veículos'], ';');
    fputcsv($out, ['Gerado em', date('d/m/Y H:i')], ';');
    $filterParts = [];
    if ($search !== '') {
        $filterParts[] = 'Busca: ' . $search;
    }
    if ($status !== '') {
        $filterParts[] = 'Status ID: ' . $status;
    }
    if ($marca !== '') {
        $filterParts[] = 'Marca: ' . $marca;
    }
    if (!empty($filterParts)) {
        fputcsv($out, ['Filtros aplicados', implode(' · ', $filterParts)], ';');
    }
    fputcsv($out, [], ';');

    $headers = ['ID', 'Placa', 'Modelo', 'Marca', 'Ano', 'Status', 'Cavalo', 'Carreta', 'Quilometragem'];
    fputcsv($out, $headers, ';');

    foreach ($rows as $row) {
        $cavalo = '';
        if (!empty($row['cavalo_nome'])) {
            $cavalo = $row['cavalo_nome'] . ' (' . ($row['cavalo_eixos'] ?? '') . ' eixos, ' . ($row['cavalo_tracao'] ?? '') . ')';
        }
        $carreta = '';
        if (!empty($row['carreta_nome'])) {
            $carreta = $row['carreta_nome'] . ' (' . ($row['carreta_capacidade'] ?? '') . ' ton)';
        }
        $km = $row['km_atual'];
        if ($km !== null && $km !== '') {
            $km = number_format((float) $km, 0, ',', '.') . ' km';
        } else {
            $km = '';
        }
        fputcsv($out, [
            $row['id'] ?? '',
            $row['placa'] ?? '',
            $row['modelo'] ?? '',
            $row['marca'] ?? '',
            $row['ano'] ?? '',
            $row['status_nome'] ?? '',
            $cavalo,
            $carreta,
            $km,
        ], ';');
    }

    fclose($out);
    exit;
} catch (Throwable $e) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log('Erro na exportação de veículos: ' . $e->getMessage());
    }
    http_response_code(500);
    exit('Erro ao gerar exportação de veículos.');
}
