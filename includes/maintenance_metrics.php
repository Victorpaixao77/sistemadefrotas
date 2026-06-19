<?php
/**
 * Métricas e listagem de manutenções — regras unificadas (preventiva/corretiva, MTBF, filtros).
 */

if (!function_exists('sf_mnt_sql_is_preventiva')) {
    function sf_mnt_sql_is_preventiva(string $tmAlias = 'tm'): string
    {
        return "({$tmAlias}.nome IS NOT NULL AND LOWER(TRIM({$tmAlias}.nome)) LIKE '%preventiva%')";
    }
}

if (!function_exists('sf_mnt_sql_is_corretiva')) {
    function sf_mnt_sql_is_corretiva(string $tmAlias = 'tm'): string
    {
        return "({$tmAlias}.nome IS NULL OR LOWER(TRIM({$tmAlias}.nome)) NOT LIKE '%preventiva%')";
    }
}

if (!function_exists('sf_mnt_period_months')) {
    function sf_mnt_period_months(string $period): int
    {
        return in_array($period, ['3', '6', '12'], true) ? (int) $period : 6;
    }
}

if (!function_exists('sf_mnt_period_sql')) {
    /** Condição SQL para coluna de data conforme período (3|6|12|ano_atual). */
    function sf_mnt_period_sql(string $period, string $column = 'm.data_manutencao'): string
    {
        if ($period === 'ano_atual') {
            return "YEAR({$column}) = YEAR(CURRENT_DATE())";
        }
        $months = sf_mnt_period_months($period);
        return "{$column} >= DATE_SUB(CURRENT_DATE(), INTERVAL {$months} MONTH)";
    }
}

if (!function_exists('sf_mnt_period_label')) {
    function sf_mnt_period_label(string $period): string
    {
        if ($period === 'ano_atual') {
            return 'Ano atual';
        }
        return 'Últimos ' . sf_mnt_period_months($period) . ' meses';
    }
}

if (!function_exists('sf_mnt_parse_list_opts')) {
    /**
     * @param array<string,mixed> $source GET ou array manual
     * @return array<string,mixed>
     */
    function sf_mnt_parse_list_opts(array $source): array
    {
        $per_page = isset($source['per_page']) ? max(5, min(100, (int) $source['per_page'])) : 10;
        if (!in_array($per_page, [5, 10, 25, 50, 100], true)) {
            $per_page = 10;
        }

        $allowed_ob = ['data_manutencao', 'valor', 'veiculo_placa', 'tipo_nome', 'status_nome', 'fornecedor', 'descricao', 'custo_12m'];
        $order_by = isset($source['order']) && in_array($source['order'], $allowed_ob, true)
            ? $source['order']
            : 'data_manutencao';
        $order_dir = isset($source['dir']) && strtoupper((string) $source['dir']) === 'ASC' ? 'ASC' : 'DESC';

        $data_inicio = isset($source['data_inicio']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $source['data_inicio'])
            ? $source['data_inicio'] : null;
        $data_fim = isset($source['data_fim']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $source['data_fim'])
            ? $source['data_fim'] : null;

        $q = isset($source['q']) ? trim((string) $source['q']) : '';
        $veiculo_id = isset($source['veiculo_id']) && $source['veiculo_id'] !== '' ? (int) $source['veiculo_id'] : null;
        if ($veiculo_id !== null && $veiculo_id <= 0) {
            $veiculo_id = null;
        }

        $tipo_classe = isset($source['tipo_classe']) ? strtolower(trim((string) $source['tipo_classe'])) : '';
        if (!in_array($tipo_classe, ['preventiva', 'corretiva'], true)) {
            $tipo_classe = '';
        }

        $status_id = isset($source['status_id']) && $source['status_id'] !== '' ? (int) $source['status_id'] : null;
        if ($status_id !== null && $status_id <= 0) {
            $status_id = null;
        }

        $fornecedor = isset($source['fornecedor']) ? trim((string) $source['fornecedor']) : '';

        return [
            'per_page' => $per_page,
            'data_inicio' => $data_inicio,
            'data_fim' => $data_fim,
            'order_by' => $order_by,
            'order_dir' => $order_dir,
            'q' => $q,
            'veiculo_id' => $veiculo_id,
            'tipo_classe' => $tipo_classe,
            'status_id' => $status_id,
            'fornecedor' => $fornecedor,
        ];
    }
}

if (!function_exists('sf_mnt_build_list_where')) {
    /**
     * @param array<string,mixed> $opts
     * @return array{where: string, params: array<string,mixed>}
     */
    function sf_mnt_build_list_where(int $empresa_id, array $opts): array
    {
        $where = 'm.empresa_id = :empresa_id';
        $params = [':empresa_id' => $empresa_id];

        if (!empty($opts['data_inicio'])) {
            $where .= ' AND m.data_manutencao >= :data_inicio';
            $params[':data_inicio'] = $opts['data_inicio'];
        }
        if (!empty($opts['data_fim'])) {
            $where .= ' AND m.data_manutencao <= :data_fim';
            $params[':data_fim'] = $opts['data_fim'];
        }
        if (!empty($opts['veiculo_id'])) {
            $where .= ' AND m.veiculo_id = :veiculo_id';
            $params[':veiculo_id'] = (int) $opts['veiculo_id'];
        }
        if (!empty($opts['status_id'])) {
            $where .= ' AND m.status_manutencao_id = :status_id';
            $params[':status_id'] = (int) $opts['status_id'];
        }
        if (!empty($opts['fornecedor'])) {
            $where .= ' AND m.fornecedor = :fornecedor';
            $params[':fornecedor'] = $opts['fornecedor'];
        }
        if (($opts['tipo_classe'] ?? '') === 'preventiva') {
            $where .= ' AND ' . sf_mnt_sql_is_preventiva('tm');
        } elseif (($opts['tipo_classe'] ?? '') === 'corretiva') {
            $where .= ' AND ' . sf_mnt_sql_is_corretiva('tm');
        }
        if (!empty($opts['q'])) {
            $where .= ' AND (v.placa LIKE :q OR m.descricao LIKE :q OR m.fornecedor LIKE :q'
                . ' OR tm.nome LIKE :q OR sm.nome LIKE :q OR CAST(m.id AS CHAR) LIKE :q)';
            $params[':q'] = '%' . $opts['q'] . '%';
        }

        return ['where' => $where, 'params' => $params];
    }
}

if (!function_exists('sf_mnt_fetch_list')) {
    /**
     * @param array<string,mixed> $opts
     * @return array<string,mixed>
     */
    function sf_mnt_fetch_list(PDO $conn, int $empresa_id, int $page = 1, array $opts = []): array
    {
        try {
            $opts = array_merge(sf_mnt_parse_list_opts([]), $opts);
            $per_page = (int) $opts['per_page'];
            $page = max(1, $page);
            $offset = ($page - 1) * $per_page;

            $built = sf_mnt_build_list_where($empresa_id, $opts);
            $where = $built['where'];
            $params = $built['params'];

            $custo12expr = '(SELECT COALESCE(SUM(m2.valor),0) FROM manutencoes m2 WHERE m2.veiculo_id = m.veiculo_id AND m2.empresa_id = m.empresa_id AND m2.data_manutencao >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH))';
            $cols = [
                'data_manutencao' => 'm.data_manutencao',
                'valor' => 'm.valor',
                'veiculo_placa' => 'v.placa',
                'tipo_nome' => 'tm.nome',
                'status_nome' => 'sm.nome',
                'fornecedor' => 'm.fornecedor',
                'descricao' => 'm.descricao',
                'custo_12m' => $custo12expr,
            ];
            $order_col = $cols[$opts['order_by']] ?? 'm.data_manutencao';
            $order_dir = $opts['order_dir'];

            $joins = ' FROM manutencoes m'
                . ' LEFT JOIN veiculos v ON m.veiculo_id = v.id'
                . ' LEFT JOIN tipos_manutencao tm ON m.tipo_manutencao_id = tm.id'
                . ' LEFT JOIN status_manutencao sm ON m.status_manutencao_id = sm.id';

            $sql_count = 'SELECT COUNT(*) as total' . $joins . ' WHERE ' . $where;
            $stmt_count = $conn->prepare($sql_count);
            foreach ($params as $k => $v) {
                $stmt_count->bindValue($k, $v);
            }
            $stmt_count->execute();
            $total = (int) $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];

            $sql = 'SELECT m.*, v.placa as veiculo_placa, tm.nome as tipo_nome, sm.nome as status_nome'
                . $joins
                . ' WHERE ' . $where
                . " ORDER BY {$order_col} {$order_dir}, m.id {$order_dir}"
                . ' LIMIT ' . (int) $per_page . ' OFFSET ' . (int) $offset;

            $stmt = $conn->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $custo_12m = [];
            try {
                $st = $conn->prepare(
                    'SELECT veiculo_id, COALESCE(SUM(valor), 0) as custo_12m FROM manutencoes'
                    . ' WHERE empresa_id = :eid AND data_manutencao >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH) GROUP BY veiculo_id'
                );
                $st->bindValue(':eid', $empresa_id, PDO::PARAM_INT);
                $st->execute();
                while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $custo_12m[(int) $row['veiculo_id']] = (float) $row['custo_12m'];
                }
            } catch (PDOException $e) {
                // ignore
            }
            foreach ($result as &$r) {
                $r['custo_veiculo_12m'] = $custo_12m[(int) ($r['veiculo_id'] ?? 0)] ?? 0;
            }
            unset($r);

            return [
                'manutencoes' => $result,
                'total' => $total,
                'pagina_atual' => $page,
                'total_paginas' => $per_page > 0 ? (int) ceil($total / $per_page) : 1,
                'per_page' => $per_page,
            ];
        } catch (PDOException $e) {
            error_log('sf_mnt_fetch_list: ' . $e->getMessage());
            return [
                'manutencoes' => [],
                'total' => 0,
                'pagina_atual' => 1,
                'total_paginas' => 1,
                'per_page' => (int) ($opts['per_page'] ?? 10),
            ];
        }
    }
}

if (!function_exists('sf_mnt_compute_mtbf_mttr')) {
    /**
     * MTBF (km entre corretivas) = km rodado no período / nº corretivas.
     * MTTR (h) = média de horas entre abertura e conclusão (somente com data_conclusao).
     *
     * @return array{mtbf: float, mttr: float}
     */
    function sf_mnt_compute_mtbf_mttr(PDO $conn, int $empresa_id, string $period = '6'): array
    {
        try {
            $mntInterval = sf_mnt_period_sql($period, 'm.data_manutencao');
            $rotaInterval = sf_mnt_period_sql($period, 'r.data_saida');

            $stKm = $conn->prepare(
                "SELECT COALESCE(SUM(r.distancia_km), 0) AS km FROM rotas r"
                . " WHERE r.empresa_id = :eid AND r.data_saida IS NOT NULL AND {$rotaInterval}"
            );
            $stKm->bindValue(':eid', $empresa_id, PDO::PARAM_INT);
            $stKm->execute();
            $km_periodo = (float) ($stKm->fetch(PDO::FETCH_ASSOC)['km'] ?? 0);

            $stCorr = $conn->prepare(
                'SELECT COUNT(*) AS n FROM manutencoes m'
                . ' LEFT JOIN tipos_manutencao tm ON m.tipo_manutencao_id = tm.id'
                . " WHERE m.empresa_id = :eid AND {$mntInterval} AND " . sf_mnt_sql_is_corretiva('tm')
            );
            $stCorr->bindValue(':eid', $empresa_id, PDO::PARAM_INT);
            $stCorr->execute();
            $corretivas = (int) ($stCorr->fetch(PDO::FETCH_ASSOC)['n'] ?? 0);

            $stMttr = $conn->prepare(
                'SELECT COALESCE(SUM(TIMESTAMPDIFF(HOUR, m.data_manutencao, m.data_conclusao)), 0) AS horas,'
                . ' COUNT(*) AS n FROM manutencoes m'
                . " WHERE m.empresa_id = :eid AND m.data_conclusao IS NOT NULL AND {$mntInterval}"
            );
            $stMttr->bindValue(':eid', $empresa_id, PDO::PARAM_INT);
            $stMttr->execute();
            $mttrRow = $stMttr->fetch(PDO::FETCH_ASSOC);
            $horas = (float) ($mttrRow['horas'] ?? 0);
            $conclusoes = (int) ($mttrRow['n'] ?? 0);

            return [
                'mtbf' => $corretivas > 0 ? ($km_periodo / $corretivas) : 0,
                'mttr' => $conclusoes > 0 ? ($horas / $conclusoes) : 0,
            ];
        } catch (PDOException $e) {
            error_log('sf_mnt_compute_mtbf_mttr: ' . $e->getMessage());
            return ['mtbf' => 0, 'mttr' => 0];
        }
    }
}

if (!function_exists('sf_mnt_compute_custo_km')) {
    function sf_mnt_compute_custo_km(PDO $conn, int $empresa_id, string $period = '12'): array
    {
        try {
            $mntInterval = sf_mnt_period_sql($period, 'm.data_manutencao');
            $rotaInterval = sf_mnt_period_sql($period, 'r.data_saida');

            $st = $conn->prepare(
                "SELECT COALESCE(SUM(m.valor), 0) AS c FROM manutencoes m WHERE m.empresa_id = :eid AND {$mntInterval}"
            );
            $st->bindValue(':eid', $empresa_id, PDO::PARAM_INT);
            $st->execute();
            $custo = (float) ($st->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

            $st = $conn->prepare(
                "SELECT COALESCE(SUM(r.distancia_km), 0) AS k FROM rotas r"
                . " WHERE r.empresa_id = :eid AND r.data_saida IS NOT NULL AND {$rotaInterval}"
            );
            $st->bindValue(':eid', $empresa_id, PDO::PARAM_INT);
            $st->execute();
            $km = (float) ($st->fetch(PDO::FETCH_ASSOC)['k'] ?? 0);

            return [
                'custo_km' => $km > 0 ? ($custo / $km) : 0,
                'custo_total' => $custo,
                'km_total' => $km,
            ];
        } catch (PDOException $e) {
            error_log('sf_mnt_compute_custo_km: ' . $e->getMessage());
            return ['custo_km' => 0, 'custo_total' => 0, 'km_total' => 0];
        }
    }
}

if (!function_exists('sf_mnt_filter_options')) {
    /**
     * @return array{veiculos: array, fornecedores: array, status: array}
     */
    function sf_mnt_filter_options(PDO $conn, int $empresa_id): array
    {
        $veiculos = [];
        $fornecedores = [];
        $status = [];
        try {
            $st = $conn->prepare('SELECT id, placa FROM veiculos WHERE empresa_id = :eid ORDER BY placa ASC');
            $st->bindValue(':eid', $empresa_id, PDO::PARAM_INT);
            $st->execute();
            $veiculos = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('sf_mnt_filter_options veiculos: ' . $e->getMessage());
        }
        try {
            $st = $conn->prepare(
                "SELECT DISTINCT fornecedor FROM manutencoes WHERE empresa_id = :eid"
                . " AND fornecedor IS NOT NULL AND TRIM(fornecedor) <> '' ORDER BY fornecedor ASC"
            );
            $st->bindValue(':eid', $empresa_id, PDO::PARAM_INT);
            $st->execute();
            $fornecedores = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'fornecedor');
        } catch (PDOException $e) {
            error_log('sf_mnt_filter_options fornecedores: ' . $e->getMessage());
        }
        try {
            $st = $conn->prepare('SELECT id, nome FROM status_manutencao ORDER BY nome ASC');
            $st->execute();
            $status = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('sf_mnt_filter_options status: ' . $e->getMessage());
        }
        return ['veiculos' => $veiculos, 'fornecedores' => $fornecedores, 'status' => $status];
    }
}
