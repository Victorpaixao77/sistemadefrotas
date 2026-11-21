<?php
/**
 * Listagem, KPIs e gráficos de despesas fixas (compartilhado entre API e SSR).
 */

if (!function_exists('despesas_fixas_get_metrics')) {
    function despesas_fixas_get_metrics(PDO $conn, int $empresa_id, ?int $year, ?int $month, bool $filterByMonth = false): array
    {
        $sql = "SELECT COUNT(*) as total_despesas,
                   SUM(CASE WHEN status_pagamento_id = 1 THEN 1 ELSE 0 END) as total_pendentes,
                   SUM(CASE WHEN status_pagamento_id = 3 THEN 1 ELSE 0 END) as total_vencidas,
                   SUM(valor) as valor_total
            FROM despesas_fixas
            WHERE empresa_id = :empresa_id";
        if ($filterByMonth && $year && $month) {
            $sql .= ' AND YEAR(vencimento) = :year AND MONTH(vencimento) = :month';
        }

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
        if ($filterByMonth && $year && $month) {
            $stmt->bindValue(':year', $year, PDO::PARAM_INT);
            $stmt->bindValue(':month', $month, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('despesas_fixas_get_chart_data')) {
    function despesas_fixas_get_chart_data(PDO $conn, int $empresa_id, ?int $year, ?int $month, bool $filterByMonth = false): array
    {
        try {
            $monthSql = ($filterByMonth && $year && $month)
                ? ' AND YEAR(df.vencimento) = :year AND MONTH(df.vencimento) = :month'
                : '';

            $bindMonth = static function (PDOStatement $stmt) use ($filterByMonth, $year, $month, $empresa_id): void {
                $stmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
                if ($filterByMonth && $year && $month) {
                    $stmt->bindValue(':year', $year, PDO::PARAM_INT);
                    $stmt->bindValue(':month', $month, PDO::PARAM_INT);
                }
            };

            $sql_tipos = "SELECT td.nome, COUNT(*) as total, SUM(df.valor) as valor_total
            FROM despesas_fixas df
            JOIN tipos_despesa_fixa td ON df.tipo_despesa_id = td.id
            WHERE df.empresa_id = :empresa_id" . $monthSql . "
            GROUP BY td.id, td.nome ORDER BY valor_total DESC";
            $stmt = $conn->prepare($sql_tipos);
            $bindMonth($stmt);
            $stmt->execute();
            $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sql_status = "SELECT sp.nome as status, COUNT(*) as total, SUM(df.valor) as valor_total
            FROM despesas_fixas df
            JOIN status_pagamento sp ON df.status_pagamento_id = sp.id
            WHERE df.empresa_id = :empresa_id" . $monthSql . "
            GROUP BY sp.id, sp.nome";
            $stmt = $conn->prepare($sql_status);
            $bindMonth($stmt);
            $stmt->execute();
            $status = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sql_veiculos = "SELECT v.placa, COUNT(*) as total_despesas, SUM(df.valor) as valor_total
            FROM despesas_fixas df
            JOIN veiculos v ON df.veiculo_id = v.id
            WHERE df.empresa_id = :empresa_id" . $monthSql . "
            GROUP BY v.id, v.placa ORDER BY valor_total DESC LIMIT 5";
            $stmt = $conn->prepare($sql_veiculos);
            $bindMonth($stmt);
            $stmt->execute();
            $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sql_pagamento = "SELECT fp.nome, COUNT(*) as total, SUM(df.valor) as valor_total
            FROM despesas_fixas df
            JOIN formas_pagamento fp ON df.forma_pagamento_id = fp.id
            WHERE df.empresa_id = :empresa_id" . $monthSql . "
            GROUP BY fp.id, fp.nome ORDER BY valor_total DESC";
            $stmt = $conn->prepare($sql_pagamento);
            $bindMonth($stmt);
            $stmt->execute();
            $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'tipos' => [
                    'labels' => array_map(static function ($item) { return $item['nome']; }, $tipos),
                    'valores' => array_map(static function ($item) { return (float) $item['valor_total']; }, $tipos),
                ],
                'status' => [
                    'labels' => array_map(static function ($item) { return $item['status']; }, $status),
                    'valores' => array_map(static function ($item) { return (float) $item['valor_total']; }, $status),
                ],
                'veiculos' => [
                    'labels' => array_map(static function ($item) { return $item['placa']; }, $veiculos),
                    'valores' => array_map(static function ($item) { return (float) $item['valor_total']; }, $veiculos),
                ],
                'pagamentos' => [
                    'labels' => array_map(static function ($item) { return $item['nome']; }, $pagamentos),
                    'valores' => array_map(static function ($item) { return (float) $item['valor_total']; }, $pagamentos),
                ],
            ];
        } catch (PDOException $e) {
            if (function_exists('sf_log_debug')) {
                sf_log_debug('despesas_fixas_get_chart_data: ' . $e->getMessage());
            }
            return [
                'tipos' => ['labels' => [], 'valores' => []],
                'status' => ['labels' => [], 'valores' => []],
                'veiculos' => ['labels' => [], 'valores' => []],
                'pagamentos' => ['labels' => [], 'valores' => []],
            ];
        }
    }
}

if (!function_exists('despesas_fixas_fetch_list')) {
    /**
     * @param array{search?:string,veiculo?:string,tipo?:string,status?:string,pagamento?:string,year?:int|null,month?:int|null,filter_by_month?:bool,page?:int,per_page?:int,sort?:string,dir?:string} $opts
     */
    function despesas_fixas_fetch_list(PDO $conn, int $empresa_id, array $opts = []): array
    {
        $search = isset($opts['search']) ? (string) $opts['search'] : '';
        $veiculo = isset($opts['veiculo']) ? (string) $opts['veiculo'] : '';
        $tipo = isset($opts['tipo']) ? (string) $opts['tipo'] : '';
        $status = isset($opts['status']) ? (string) $opts['status'] : '';
        $pagamento = isset($opts['pagamento']) ? (string) $opts['pagamento'] : '';
        $filterByMonth = !empty($opts['filter_by_month']);
        $year = $filterByMonth && isset($opts['year']) ? (int) $opts['year'] : null;
        $month = $filterByMonth && isset($opts['month']) ? (int) $opts['month'] : null;
        $page = isset($opts['page']) ? max(1, (int) $opts['page']) : 1;
        $per_page = isset($opts['per_page']) ? (int) $opts['per_page'] : 10;
        if (!in_array($per_page, [5, 10, 25, 50, 100], true)) {
            $per_page = 10;
        }
        $limit = $per_page;
        $offset = ($page - 1) * $limit;

        $sql_count = "SELECT COUNT(*) as total
                         FROM despesas_fixas df
                         LEFT JOIN veiculos v ON df.veiculo_id = v.id
                         LEFT JOIN tipos_despesa_fixa td ON df.tipo_despesa_id = td.id
                         WHERE df.empresa_id = :empresa_id";

        $sql = "SELECT df.*, v.placa as veiculo_placa, td.nome as tipo_nome,
                           sp.nome as status_nome, fp.nome as forma_pagamento_nome
                    FROM despesas_fixas df
                    LEFT JOIN veiculos v ON df.veiculo_id = v.id
                    LEFT JOIN tipos_despesa_fixa td ON df.tipo_despesa_id = td.id
                    LEFT JOIN status_pagamento sp ON df.status_pagamento_id = sp.id
                    LEFT JOIN formas_pagamento fp ON df.forma_pagamento_id = fp.id
                    WHERE df.empresa_id = :empresa_id";

        $params = [':empresa_id' => $empresa_id];

        if ($search !== '') {
            $sql_count .= ' AND (v.placa LIKE :search OR td.nome LIKE :search OR df.descricao LIKE :search)';
            $sql .= ' AND (v.placa LIKE :search OR td.nome LIKE :search OR df.descricao LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        if ($veiculo !== '') {
            $sql_count .= ' AND df.veiculo_id = :veiculo';
            $sql .= ' AND df.veiculo_id = :veiculo';
            $params[':veiculo'] = $veiculo;
        }
        if ($tipo !== '') {
            $sql_count .= ' AND df.tipo_despesa_id = :tipo';
            $sql .= ' AND df.tipo_despesa_id = :tipo';
            $params[':tipo'] = $tipo;
        }
        if ($status !== '') {
            $sql_count .= ' AND df.status_pagamento_id = :status';
            $sql .= ' AND df.status_pagamento_id = :status';
            $params[':status'] = $status;
        }
        if ($pagamento !== '') {
            $sql_count .= ' AND df.forma_pagamento_id = :pagamento';
            $sql .= ' AND df.forma_pagamento_id = :pagamento';
            $params[':pagamento'] = $pagamento;
        }
        if ($filterByMonth && $year && $month) {
            $sql_count .= ' AND YEAR(df.vencimento) = :year AND MONTH(df.vencimento) = :month';
            $sql .= ' AND YEAR(df.vencimento) = :year AND MONTH(df.vencimento) = :month';
            $params[':year'] = $year;
            $params[':month'] = $month;
        }

        $allowedSort = [
            'vencimento' => 'df.vencimento',
            'veiculo_placa' => 'v.placa',
            'tipo_nome' => 'td.nome',
            'descricao' => 'df.descricao',
            'valor' => 'df.valor',
            'status_nome' => 'sp.nome',
            'data_pagamento' => 'df.data_pagamento',
            'forma_pagamento_nome' => 'fp.nome',
            'repetir' => 'df.repetir_automaticamente',
        ];
        $sortKey = isset($opts['sort']) ? trim((string) $opts['sort']) : 'vencimento';
        if ($sortKey === '' || !isset($allowedSort[$sortKey])) {
            $sortKey = 'vencimento';
        }
        $orderCol = $allowedSort[$sortKey];
        $dir = (isset($opts['dir']) && strtoupper(trim((string) $opts['dir'])) === 'ASC') ? 'ASC' : 'DESC';

        $stmt_count = $conn->prepare($sql_count);
        foreach ($params as $key => $val) {
            $stmt_count->bindValue($key, $val);
        }
        $stmt_count->execute();
        $total_records = (int) $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
        $total_pages = $total_records > 0 ? (int) ceil($total_records / $limit) : 1;
        if ($page > $total_pages) {
            $page = $total_pages;
            $offset = ($page - 1) * $limit;
        }

        $sql .= ' ORDER BY ' . $orderCol . ' ' . $dir . ', df.id DESC LIMIT :limit OFFSET :offset';
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $val) {
            if ($key === ':limit' || $key === ':offset') {
                $stmt->bindValue($key, $val, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $val);
            }
        }
        $stmt->execute();
        $despesas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $metrics = despesas_fixas_get_metrics($conn, $empresa_id, $year, $month, $filterByMonth);
        $charts = despesas_fixas_get_chart_data($conn, $empresa_id, $year, $month, $filterByMonth);

        $stmtTc = $conn->prepare('SELECT COUNT(*) FROM despesas_fixas WHERE empresa_id = :empresa_id');
        $stmtTc->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmtTc->execute();
        $total_cadastrados = (int) $stmtTc->fetchColumn();

        return [
            'success' => true,
            'despesas' => $despesas,
            'metrics' => $metrics,
            'charts' => $charts,
            'pagina_atual' => $page,
            'total_paginas' => max(1, $total_pages),
            'total_registros' => $total_records,
            'total_cadastrados' => $total_cadastrados,
        ];
    }
}

if (!function_exists('despesas_fixas_fmt_date')) {
    function despesas_fixas_fmt_date(?string $date): string
    {
        if (!$date || $date === '0000-00-00') {
            return '-';
        }
        $ts = strtotime($date);
        return $ts ? date('d/m/Y', $ts) : '-';
    }
}

if (!function_exists('despesas_fixas_fmt_money')) {
    function despesas_fixas_fmt_money($value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }
}

if (!function_exists('despesas_fixas_render_table_row')) {
    function despesas_fixas_render_table_row(array $despesa): string
    {
        $id = (int) ($despesa['id'] ?? 0);
        $comprovante = !empty($despesa['comprovante'])
            ? htmlspecialchars((string) $despesa['comprovante'], ENT_QUOTES, 'UTF-8')
            : '';
        $venc = htmlspecialchars(despesas_fixas_fmt_date($despesa['vencimento'] ?? null), ENT_QUOTES, 'UTF-8');
        $placa = htmlspecialchars((string) ($despesa['veiculo_placa'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $tipo = htmlspecialchars((string) ($despesa['tipo_nome'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $desc = htmlspecialchars((string) ($despesa['descricao'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $valor = htmlspecialchars(despesas_fixas_fmt_money($despesa['valor'] ?? 0), ENT_QUOTES, 'UTF-8');
        $status = htmlspecialchars((string) ($despesa['status_nome'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $pag = !empty($despesa['data_pagamento'])
            ? htmlspecialchars(despesas_fixas_fmt_date($despesa['data_pagamento']), ENT_QUOTES, 'UTF-8')
            : '-';
        $forma = htmlspecialchars((string) ($despesa['forma_pagamento_nome'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $repetir = !empty($despesa['repetir_automaticamente']) ? 'Sim' : 'Não';
        $compBtn = $comprovante
            ? '<button class="btn-icon view-comprovante-btn" data-comprovante="' . $comprovante . '" title="Ver Comprovante"><i class="fas fa-file-alt"></i></button>'
            : '';

        return '<tr>'
            . '<td>' . $venc . '</td>'
            . '<td>' . $placa . '</td>'
            . '<td>' . $tipo . '</td>'
            . '<td>' . $desc . '</td>'
            . '<td>' . $valor . '</td>'
            . '<td>' . $status . '</td>'
            . '<td>' . $pag . '</td>'
            . '<td>' . $forma . '</td>'
            . '<td>' . $repetir . '</td>'
            . '<td class="actions">'
            . '<button class="btn-icon edit-btn" data-id="' . $id . '" title="Editar"><i class="fas fa-edit"></i></button>'
            . $compBtn
            . '<button class="btn-icon delete-btn" data-id="' . $id . '" title="Excluir"><i class="fas fa-trash"></i></button>'
            . '</td>'
            . '</tr>';
    }
}
