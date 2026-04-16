<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $conn = getConnection();
    $tables = [
        'fiscal_mdfe',
        'fiscal_mdfe_cte',
        'fiscal_mdfe_nfe',
        'mdfe_envios',
        'mdfe_validacao_log',
        'fiscal_fila_processamento',
    ];

    $checkTable = $conn->prepare("
        SELECT COUNT(*) 
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ");

    $result = [
        'ok' => true,
        'database' => $conn->query("SELECT DATABASE()")->fetchColumn(),
        'tables' => [],
        'mdfe_missing_columns' => [],
        'mdfe_status_enum' => null,
        'mdfe_status_has_em_envio' => false,
    ];

    foreach ($tables as $table) {
        $checkTable->execute([$table]);
        $result['tables'][$table] = ((int)$checkTable->fetchColumn()) > 0;
    }

    $requiredCols = [
        'id', 'empresa_id', 'numero_mdfe', 'serie_mdfe', 'chave_acesso', 'data_emissao',
        'protocolo_autorizacao', 'status', 'valor_total_carga', 'peso_total_carga',
        'qtd_total_volumes', 'xml_mdfe', 'observacoes', 'motorista_id', 'veiculo_id',
        'uf_inicio', 'uf_fim', 'municipio_carregamento', 'municipio_descarregamento',
        'tipo_viagem', 'total_cte', 'data_autorizacao', 'data_encerramento',
        'created_at', 'updated_at',
    ];

    $colsStmt = $conn->query("
        SELECT COLUMN_NAME, COLUMN_TYPE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fiscal_mdfe'
    ");
    $cols = [];
    foreach ($colsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cols[(string)$row['COLUMN_NAME']] = (string)$row['COLUMN_TYPE'];
    }

    foreach ($requiredCols as $col) {
        if (!isset($cols[$col])) {
            $result['mdfe_missing_columns'][] = $col;
        }
    }

    if (isset($cols['status'])) {
        $result['mdfe_status_enum'] = $cols['status'];
        $result['mdfe_status_has_em_envio'] = stripos($cols['status'], 'em_envio') !== false;
    }

    if (!$result['mdfe_status_has_em_envio']) {
        $result['ok'] = false;
    }
    foreach ($result['tables'] as $exists) {
        if (!$exists) {
            $result['ok'] = false;
            break;
        }
    }
    if (!empty($result['mdfe_missing_columns'])) {
        $result['ok'] = false;
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

