<?php
/**
 * Helpers compartilhados — Comissões (listagem, gráficos, pagamentos).
 */

if (!function_exists('ensureCommissionPaymentsTable')) {
    function ensureCommissionPaymentsTable(PDO $conn): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $chk = $conn->query("
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = 'comissoes_pagamentos'
            LIMIT 1
        ");
        if (!$chk || !$chk->fetchColumn()) {
            throw new RuntimeException(
                'Tabela comissoes_pagamentos não encontrada. Execute sql/comissoes_pagamentos.sql'
            );
        }
        $ready = true;
    }
}

if (!function_exists('comissoes_table_has_empresa_id')) {
    function comissoes_table_has_empresa_id(PDO $conn): bool
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        $db = (string) $conn->query('SELECT DATABASE()')->fetchColumn();
        $st = $conn->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = ? AND table_name = ? AND column_name = ?'
        );
        $st->execute([$db, 'comissoes_pagamentos', 'empresa_id']);
        $has = (int) $st->fetchColumn() > 0;
        return $has;
    }
}

if (!function_exists('normalizeSearchValue')) {
    function normalizeSearchValue(string $input): string
    {
        $input = trim($input);
        if ($input === '') {
            return '';
        }
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($input, 'UTF-8');
        }
        return strtolower($input);
    }
}

if (!function_exists('logComissoesDebug')) {
    function logComissoesDebug(string $context, string $sql, array $params = []): void
    {
        if (!isset($_GET['debug_search']) || !defined('DEBUG_MODE') || !DEBUG_MODE) {
            return;
        }
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $message = sprintf(
            "[%s] [comissoes][%s]\nSQL: %s\nParametros: %s\n\n",
            date('Y-m-d H:i:s'),
            $context,
            $sql,
            json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
        file_put_contents($logDir . '/comissoes_debug.log', $message, FILE_APPEND);
    }
}

if (!function_exists('executeWithDebug')) {
    function executeWithDebug(PDOStatement $stmt, array $params, string $context, string $sql): void
    {
        try {
            foreach ($params as $key => $value) {
                $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($key, $value, $type);
            }
            logComissoesDebug($context, $sql, $params);
            $stmt->execute();
        } catch (PDOException $e) {
            logComissoesDebug($context . '_erro', $sql, [
                'mensagem' => $e->getMessage(),
                'params' => $params,
            ]);
            throw $e;
        }
    }
}

if (!function_exists('buildComissoesQueryParts')) {
    function buildComissoesQueryParts(array $filters, int $empresa_id): array
    {
        $conditions = [
            'r.empresa_id = :empresa_id',
            'r.comissao > 0',
            "r.status = 'aprovado'",
        ];
        $params = [':empresa_id' => $empresa_id];

        if (!empty($filters['mes'])) {
            $conditions[] = "DATE_FORMAT(r.data_rota, '%Y-%m') = :mes";
            $params[':mes'] = $filters['mes'];
        }

        if (!empty($filters['motorista'])) {
            $conditions[] = 'r.motorista_id = :motorista_id';
            $params[':motorista_id'] = (int) $filters['motorista'];
        }

        if (!empty($filters['veiculo'])) {
            $conditions[] = 'r.veiculo_id = :veiculo_id';
            $params[':veiculo_id'] = (int) $filters['veiculo'];
        }

        if (!empty($filters['status_pagamento']) && in_array($filters['status_pagamento'], ['pago', 'pendente'], true)) {
            if ($filters['status_pagamento'] === 'pago') {
                $conditions[] = "cp.status = 'pago'";
            } else {
                $conditions[] = '(cp.status IS NULL OR cp.status = \'pendente\')';
            }
        }

        if (!empty($filters['search'])) {
            $searchTermRaw = trim($filters['search']);
            $searchTermLower = normalizeSearchValue($filters['search']);
            $numericValue = preg_replace('/[^0-9]/', '', $searchTermRaw);
            $decimalValue = str_replace(',', '.', preg_replace('/[^0-9,]/', '', $searchTermRaw));

            $conditions[] = '(
                EXISTS (
                    SELECT 1 FROM motoristas m_busca
                    WHERE m_busca.id = r.motorista_id
                    AND LOWER(m_busca.nome) LIKE :search_motorista
                )
                OR EXISTS (
                    SELECT 1 FROM veiculos v_busca
                    WHERE v_busca.id = r.veiculo_id
                    AND (
                        LOWER(v_busca.placa) LIKE :search_placa
                        OR LOWER(COALESCE(v_busca.modelo, \'\')) LIKE :search_modelo
                        OR LOWER(CONCAT_WS(\' \', v_busca.placa, COALESCE(v_busca.modelo, \'\'))) LIKE :search_veiculo_completo
                    )
                )
                OR CAST(r.id AS CHAR) LIKE :search_id
                OR DATE_FORMAT(r.data_rota, \'%d/%m/%Y\') LIKE :search_data_completa
                OR DATE_FORMAT(r.data_rota, \'%Y-%m\') LIKE :search_data_mes
                OR CAST(r.frete AS CHAR) LIKE :search_frete
                OR CAST(r.comissao AS CHAR) LIKE :search_comissao
            )';

            $searchMotorista = '%' . $searchTermLower . '%';
            $params[':search_motorista'] = $searchMotorista;
            $params[':search_placa'] = $searchMotorista;
            $params[':search_modelo'] = $searchMotorista;
            $params[':search_veiculo_completo'] = $searchMotorista;
            $params[':search_id'] = '%' . $searchTermRaw . '%';
            $params[':search_data_completa'] = '%' . $searchTermRaw . '%';
            $params[':search_data_mes'] = '%' . $searchTermRaw . '%';
            $numericSearch = $decimalValue !== '' ? $decimalValue : $numericValue;
            $numericLike = $numericSearch !== '' ? '%' . $numericSearch . '%' : '%';
            $params[':search_frete'] = $numericLike;
            $params[':search_comissao'] = $numericLike;
        }

        return [$conditions, $params];
    }
}

if (!function_exists('parseComissoesPaymentDate')) {
    function parseComissoesPaymentDate(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return date('Y-m-d');
        }
        $raw = trim($raw);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            throw new InvalidArgumentException('Data de pagamento inválida.');
        }
        return date('Y-m-d', $ts);
    }
}

if (!function_exists('markCommissionStatus')) {
    function markCommissionStatus(
        PDO $conn,
        int $rota_id,
        int $empresa_id,
        string $status,
        float $valorComissao,
        ?string $dataPagamento = null
    ): void {
        if (!in_array($status, ['pago', 'pendente'], true)) {
            throw new InvalidArgumentException('Status inválido.');
        }

        $data = null;
        if ($status === 'pago') {
            $data = parseComissoesPaymentDate($dataPagamento);
        }

        $hasEmpresa = comissoes_table_has_empresa_id($conn);
        if ($hasEmpresa) {
            $sql = "
                INSERT INTO comissoes_pagamentos (rota_id, empresa_id, status, valor, data_pagamento)
                VALUES (:rota_id, :empresa_id, :status, :valor, :data_pagamento)
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    valor = VALUES(valor),
                    data_pagamento = VALUES(data_pagamento),
                    empresa_id = VALUES(empresa_id),
                    updated_at = CURRENT_TIMESTAMP
            ";
        } else {
            $sql = "
                INSERT INTO comissoes_pagamentos (rota_id, status, valor, data_pagamento)
                VALUES (:rota_id, :status, :valor, :data_pagamento)
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    valor = VALUES(valor),
                    data_pagamento = VALUES(data_pagamento),
                    updated_at = CURRENT_TIMESTAMP
            ";
        }

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':rota_id', $rota_id, PDO::PARAM_INT);
        if ($hasEmpresa) {
            $stmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
        }
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':valor', $valorComissao, PDO::PARAM_STR);
        if ($data === null) {
            $stmt->bindValue(':data_pagamento', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':data_pagamento', $data, PDO::PARAM_STR);
        }
        $stmt->execute();
    }
}

if (!function_exists('markCommissionsBulk')) {
    /**
     * @param int[] $rotaIds
     * @return array{updated:int, skipped:int}
     */
    function markCommissionsBulk(
        PDO $conn,
        int $empresa_id,
        array $rotaIds,
        string $status,
        ?string $dataPagamento = null
    ): array {
        $updated = 0;
        $skipped = 0;
        foreach ($rotaIds as $rid) {
            $rid = (int) $rid;
            if ($rid < 1) {
                $skipped++;
                continue;
            }
            $stmt = $conn->prepare(
                'SELECT id, comissao FROM rotas WHERE id = :id AND empresa_id = :empresa_id AND comissao > 0 AND status = \'aprovado\''
            );
            $stmt->execute([':id' => $rid, ':empresa_id' => $empresa_id]);
            $rota = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$rota) {
                $skipped++;
                continue;
            }
            markCommissionStatus($conn, $rid, $empresa_id, $status, (float) $rota['comissao'], $dataPagamento);
            $updated++;
        }
        return ['updated' => $updated, 'skipped' => $skipped];
    }
}

if (!function_exists('getComissoesChartsData')) {
    function getComissoesChartsData(array $conditions, array $params, PDO $conn): array
    {
        $whereClause = implode(' AND ', $conditions);

        $sqlStatus = "
            SELECT COALESCE(cp.status, 'pendente') AS status_pagamento,
                   COUNT(*) AS total_viagens,
                   COALESCE(SUM(r.comissao), 0) AS total_comissao
            FROM rotas r
            LEFT JOIN comissoes_pagamentos cp ON cp.rota_id = r.id
            WHERE {$whereClause}
            GROUP BY status_pagamento";
        $stmtStatus = $conn->prepare($sqlStatus);
        executeWithDebug($stmtStatus, $params, 'grafico_status', $sqlStatus);
        $statusRows = $stmtStatus->fetchAll(PDO::FETCH_ASSOC);

        $statusTotals = [
            'pago' => ['viagens' => 0, 'valor' => 0.0],
            'pendente' => ['viagens' => 0, 'valor' => 0.0],
        ];
        foreach ($statusRows as $row) {
            $st = $row['status_pagamento'] === 'pago' ? 'pago' : 'pendente';
            $statusTotals[$st]['viagens'] += (int) $row['total_viagens'];
            $statusTotals[$st]['valor'] += (float) $row['total_comissao'];
        }

        $sqlMonthly = "
            SELECT DATE_FORMAT(r.data_rota, '%Y-%m') AS periodo,
                   COALESCE(SUM(r.comissao), 0) AS total_comissao,
                   COALESCE(SUM(CASE WHEN cp.status = 'pago' THEN r.comissao ELSE 0 END), 0) AS total_pago,
                   COALESCE(SUM(CASE WHEN cp.status = 'pendente' OR cp.status IS NULL THEN r.comissao ELSE 0 END), 0) AS total_pendente
            FROM rotas r
            LEFT JOIN comissoes_pagamentos cp ON cp.rota_id = r.id
            WHERE {$whereClause}
            GROUP BY periodo
            ORDER BY periodo ASC";
        $stmtMonthly = $conn->prepare($sqlMonthly);
        executeWithDebug($stmtMonthly, $params, 'grafico_mensal', $sqlMonthly);
        $monthlyRows = $stmtMonthly->fetchAll(PDO::FETCH_ASSOC);

        $monthlyData = ['labels' => [], 'total' => [], 'pagas' => [], 'pendentes' => []];
        foreach ($monthlyRows as $row) {
            $monthlyData['labels'][] = date('m/Y', strtotime($row['periodo'] . '-01'));
            $monthlyData['total'][] = (float) $row['total_comissao'];
            $monthlyData['pagas'][] = (float) $row['total_pago'];
            $monthlyData['pendentes'][] = (float) $row['total_pendente'];
        }

        $sqlMotoristas = "
            SELECT COALESCE(m.nome, 'Não informado') AS motorista_nome,
                   COALESCE(SUM(r.comissao), 0) AS total_comissao,
                   COALESCE(SUM(CASE WHEN cp.status = 'pago' THEN r.comissao ELSE 0 END), 0) AS total_pago
            FROM rotas r
            LEFT JOIN motoristas m ON r.motorista_id = m.id
            LEFT JOIN comissoes_pagamentos cp ON cp.rota_id = r.id
            WHERE {$whereClause}
            GROUP BY m.id, motorista_nome
            ORDER BY total_comissao DESC
            LIMIT 8";
        $stmtMotoristas = $conn->prepare($sqlMotoristas);
        executeWithDebug($stmtMotoristas, $params, 'grafico_motoristas', $sqlMotoristas);
        $motoristaRows = $stmtMotoristas->fetchAll(PDO::FETCH_ASSOC);

        $motoristaData = ['labels' => [], 'totais' => [], 'pagas' => [], 'pendentes' => [], 'top_list' => []];
        foreach ($motoristaRows as $row) {
            $total = (float) $row['total_comissao'];
            $pago = (float) $row['total_pago'];
            $pendente = $total - $pago;
            $motoristaData['labels'][] = $row['motorista_nome'];
            $motoristaData['totais'][] = $total;
            $motoristaData['pagas'][] = $pago;
            $motoristaData['pendentes'][] = $pendente;
            $motoristaData['top_list'][] = [
                'nome' => $row['motorista_nome'],
                'total' => $total,
                'pago' => $pago,
                'pendente' => $pendente,
            ];
        }

        return [
            'status' => [
                'labels' => ['Pagas', 'Pendentes'],
                'values' => [
                    round($statusTotals['pago']['valor'], 2),
                    round($statusTotals['pendente']['valor'], 2),
                ],
            ],
            'monthly' => $monthlyData,
            'motoristas' => $motoristaData,
        ];
    }
}

if (!function_exists('getComissoes')) {
    function getComissoes(
        array $filters,
        int $page,
        int $empresa_id,
        PDO $conn,
        int $per_page = 10,
        string $sortKey = 'data_rota',
        string $sortDir = 'DESC'
    ): array {
        $limit = in_array($per_page, [5, 10, 25, 50, 100], true) ? $per_page : 10;
        $offset = ($page - 1) * $limit;

        [$conditions, $params] = buildComissoesQueryParts($filters, $empresa_id);
        $whereClause = implode(' AND ', $conditions);

        $sqlResumo = "
            SELECT COUNT(*) AS total_viagens,
                   COALESCE(SUM(r.comissao), 0) AS total_comissao,
                   COALESCE(SUM(r.frete), 0) AS total_frete,
                   COALESCE(SUM(CASE WHEN cp.status = 'pago' THEN r.comissao ELSE 0 END), 0) AS total_pago,
                   COALESCE(SUM(CASE WHEN cp.status = 'pago' THEN 1 ELSE 0 END), 0) AS viagens_pagas
            FROM rotas r
            LEFT JOIN comissoes_pagamentos cp ON cp.rota_id = r.id
            WHERE {$whereClause}";
        $stmtResumo = $conn->prepare($sqlResumo);
        executeWithDebug($stmtResumo, $params, 'resumo', $sqlResumo);
        $resumo = $stmtResumo->fetch(PDO::FETCH_ASSOC);

        $sqlCount = "
            SELECT COUNT(*)
            FROM rotas r
            LEFT JOIN comissoes_pagamentos cp ON cp.rota_id = r.id
            WHERE {$whereClause}";
        $stmtCount = $conn->prepare($sqlCount);
        executeWithDebug($stmtCount, $params, 'contagem', $sqlCount);
        $totalRegistros = (int) $stmtCount->fetchColumn();

        $sql = "
            SELECT r.id, r.data_rota, r.data_saida, r.frete, r.comissao, r.no_prazo, r.status,
                   v.placa AS veiculo_placa, v.modelo AS veiculo_modelo, m.nome AS motorista_nome,
                   cp.status AS status_pagamento, cp.data_pagamento, cp.valor AS valor_pago
            FROM rotas r
            LEFT JOIN veiculos v ON r.veiculo_id = v.id
            LEFT JOIN motoristas m ON r.motorista_id = m.id
            LEFT JOIN comissoes_pagamentos cp ON cp.rota_id = r.id
            WHERE {$whereClause}";

        $allowedSort = [
            'data_rota' => 'r.data_rota',
            'motorista_nome' => 'm.nome',
            'veiculo_placa' => 'v.placa',
            'frete' => 'r.frete',
            'comissao' => 'r.comissao',
            'pct_comissao' => '(CASE WHEN r.frete > 0 THEN (r.comissao / r.frete) * 100 ELSE 0 END)',
            'status_pagamento' => "COALESCE(cp.status, 'pendente')",
            'no_prazo' => 'r.no_prazo',
        ];
        if (!isset($allowedSort[$sortKey])) {
            $sortKey = 'data_rota';
        }
        $orderCol = $allowedSort[$sortKey];
        $dir = ($sortDir === 'ASC') ? 'ASC' : 'DESC';

        $sql .= " ORDER BY {$orderCol} {$dir}, r.id DESC LIMIT :limit OFFSET :offset";
        $stmt = $conn->prepare($sql);
        executeWithDebug($stmt, $params + [':limit' => $limit, ':offset' => $offset], 'listagem', $sql);
        $comissoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resumo['total_pago'] = $resumo['total_pago'] ?? 0;
        $resumo['total_pendente'] = ($resumo['total_comissao'] ?? 0) - $resumo['total_pago'];
        $resumo['viagens_pagas'] = $resumo['viagens_pagas'] ?? 0;
        $resumo['viagens_pendentes'] = ($resumo['total_viagens'] ?? 0) - $resumo['viagens_pagas'];
        $resumo['percentual_pago'] = ($resumo['total_comissao'] ?? 0) > 0
            ? ($resumo['total_pago'] / $resumo['total_comissao']) * 100
            : 0;
        $resumo['percentual_medio'] = ($resumo['total_frete'] ?? 0) > 0
            ? ($resumo['total_comissao'] / $resumo['total_frete']) * 100
            : 0;

        $chartFilters = $filters;
        unset($chartFilters['search']);
        [$chartConditions, $chartParams] = buildComissoesQueryParts($chartFilters, $empresa_id);
        $charts = getComissoesChartsData($chartConditions, $chartParams, $conn);

        return [
            'comissoes' => $comissoes,
            'total' => $totalRegistros,
            'pagina_atual' => $page,
            'total_paginas' => max(1, (int) ceil($totalRegistros / $limit)),
            'resumo' => $resumo,
            'charts' => $charts,
        ];
    }
}

if (!function_exists('getComissoesFilterData')) {
    function getComissoesFilterData(int $empresa_id): array
    {
        $conn = getConnection();

        $motoristasStmt = $conn->prepare(
            'SELECT id, nome FROM motoristas WHERE empresa_id = :empresa_id ORDER BY nome ASC'
        );
        $motoristasStmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $motoristasStmt->execute();
        $motoristas = $motoristasStmt->fetchAll(PDO::FETCH_ASSOC);

        $veiculosStmt = $conn->prepare(
            'SELECT id, placa, modelo FROM veiculos WHERE empresa_id = :empresa_id ORDER BY placa ASC'
        );
        $veiculosStmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $veiculosStmt->execute();
        $veiculos = $veiculosStmt->fetchAll(PDO::FETCH_ASSOC);

        $periodosStmt = $conn->prepare("
            SELECT DISTINCT DATE_FORMAT(data_rota, '%Y-%m') AS periodo
            FROM rotas
            WHERE empresa_id = :empresa_id AND comissao > 0 AND status = 'aprovado'
            ORDER BY periodo DESC
            LIMIT 18");
        $periodosStmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $periodosStmt->execute();
        $periodos = $periodosStmt->fetchAll(PDO::FETCH_COLUMN);

        return [
            'motoristas' => $motoristas,
            'veiculos' => $veiculos,
            'periodos' => $periodos,
        ];
    }
}
