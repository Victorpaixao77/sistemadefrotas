<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
}

try {
    $conn = getConnection();

    $tableExistsStmt = $conn->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ");

    $colExistsStmt = $conn->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");

    $tableExists = static function (string $table) use ($tableExistsStmt): bool {
        $tableExistsStmt->execute([$table]);
        return ((int)$tableExistsStmt->fetchColumn()) > 0;
    };

    $colExists = static function (string $table, string $column) use ($colExistsStmt): bool {
        $colExistsStmt->execute([$table, $column]);
        return ((int)$colExistsStmt->fetchColumn()) > 0;
    };

    $infraChecks = [
        'fiscal_mdfe' => $tableExists('fiscal_mdfe'),
        'fiscal_mdfe_cte' => $tableExists('fiscal_mdfe_cte'),
        'fiscal_mdfe_nfe' => $tableExists('fiscal_mdfe_nfe'),
        'mdfe_envios' => $tableExists('mdfe_envios'),
        'mdfe_validacao_log' => $tableExists('mdfe_validacao_log'),
        'cte_validacao_log' => $tableExists('cte_validacao_log'),
        'fiscal_fila_processamento' => $tableExists('fiscal_fila_processamento'),
    ];

    $dadosMdfeCols = [
        'numero_mdfe', 'serie_mdfe', 'chave_acesso', 'data_emissao',
        'uf_inicio', 'municipio_carregamento', 'uf_fim', 'municipio_descarregamento',
        'tipo_viagem', 'total_cte', 'valor_total_carga', 'peso_total_carga',
        'qtd_total_volumes', 'veiculo_id', 'motorista_id', 'status',
        'protocolo_autorizacao', 'data_autorizacao', 'data_encerramento',
    ];
    $missingDadosCols = [];
    foreach ($dadosMdfeCols as $col) {
        if (!$colExists('fiscal_mdfe', $col)) {
            $missingDadosCols[] = $col;
        }
    }

    // Cobertura de persistencia do wizard manual (grupos avançados).
    // Se não existir tabela dedicada, o dado fica apenas em validação/snapshot.
    $wizardGroups = [
        'rodoviario_ciot' => ['fiscal_mdfe_ciot'],
        'rodoviario_vale_pedagio' => ['fiscal_mdfe_vale_pedagio'],
        'rodoviario_contratantes' => ['fiscal_mdfe_contratantes'],
        'rodoviario_pagamento_frete' => ['fiscal_mdfe_pagamentos', 'fiscal_mdfe_pagamento_componentes'],
        'seguros' => ['fiscal_mdfe_seguros', 'fiscal_mdfe_seguros_averbacoes'],
        'produto_predominante' => ['fiscal_mdfe_produtos'],
        'totalizadores_lacres' => ['fiscal_mdfe_lacres'],
        'totalizadores_autorizados_download' => ['fiscal_mdfe_autorizados_download'],
    ];

    $wizardCoverage = [];
    foreach ($wizardGroups as $group => $tables) {
        $missing = [];
        foreach ($tables as $t) {
            if (!$tableExists($t)) {
                $missing[] = $t;
            }
        }
        $wizardCoverage[$group] = [
            'ok' => empty($missing),
            'expected_tables' => $tables,
            'missing_tables' => $missing,
        ];
    }

    $missingCoverageGroups = array_values(array_filter(array_keys($wizardCoverage), function ($g) use ($wizardCoverage) {
        return empty($wizardCoverage[$g]['ok']);
    }));

    $readyManual = true;
    foreach ($infraChecks as $ok) {
        if (!$ok) {
            $readyManual = false;
            break;
        }
    }
    if (!empty($missingDadosCols) || !empty($missingCoverageGroups)) {
        $readyManual = false;
    }

    $result = [
        'ok' => true,
        'database' => $conn->query("SELECT DATABASE()")->fetchColumn(),
        'ready_for_manual_mdfe_full_persistence' => $readyManual,
        'infra_checks' => $infraChecks,
        'dados_mdfe_columns_missing' => $missingDadosCols,
        'wizard_persistence_coverage' => $wizardCoverage,
        'missing_coverage_groups' => $missingCoverageGroups,
        'notes' => [
            'Se um grupo do wizard estiver em missing_coverage_groups, os dados podem estar só no snapshot/log de validação.',
            'Para produção robusta, ideal é persistir cada grupo crítico em tabela própria para auditoria e reprocesso de XML.',
        ],
    ];

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(1);
}

