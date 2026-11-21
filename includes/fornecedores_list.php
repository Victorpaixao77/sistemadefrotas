<?php
/**
 * Consulta paginada de fornecedores (compartilhada entre API e SSR da página).
 */

if (!function_exists('fornecedores_only_digits')) {
    function fornecedores_only_digits(?string $s): string
    {
        return preg_replace('/\D/', '', (string) $s);
    }
}

if (!function_exists('fornecedores_build_list_filter')) {
    /**
     * @return array{whereSql: string, params: array<string, mixed>}
     */
    function fornecedores_build_list_filter(int $empresa_id, string $situacao, ?string $tipo, ?string $qRaw): array
    {
        $params = [':eid' => $empresa_id];
        $sql = ' FROM fornecedores WHERE empresa_id = :eid';
        if ($situacao === 'A' || $situacao === 'I') {
            $sql .= ' AND situacao = :sit';
            $params[':sit'] = $situacao;
        } elseif ($situacao !== 'all') {
            $sql .= " AND situacao = 'A'";
        }
        if ($tipo === 'F' || $tipo === 'J') {
            $sql .= ' AND tipo = :tipo_f';
            $params[':tipo_f'] = $tipo;
        }
        $qRaw = trim((string) $qRaw);
        if ($qRaw !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $qRaw) . '%';
            $params[':q_like'] = $like;
            $qd = fornecedores_only_digits($qRaw);
            $docClause = '';
            if (strlen($qd) >= 1) {
                $params[':q_doc'] = '%' . $qd . '%';
                $docClause = " OR REPLACE(REPLACE(REPLACE(COALESCE(cpf, ''), '.', ''), '-', ''), ' ', '') LIKE :q_doc
                OR REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(cnpj, ''), '.', ''), '/', ''), '-', ''), ' ', '') LIKE :q_doc";
            }
            $sql .= ' AND (
            nome LIKE :q_like OR COALESCE(cidade, \'\') LIKE :q_like OR COALESCE(uf, \'\') LIKE :q_like
            OR COALESCE(email, \'\') LIKE :q_like OR COALESCE(bairro, \'\') LIKE :q_like OR COALESCE(endereco, \'\') LIKE :q_like
            ' . $docClause . '
        )';
        }
        return ['whereSql' => $sql, 'params' => $params];
    }
}

if (!function_exists('fornecedores_fetch_list')) {
    /**
     * @param array{situacao?:string,tipo?:string,q?:string,page?:int,per_page?:int,sort?:string,dir?:string,all?:bool} $opts
     */
    function fornecedores_fetch_list(PDO $conn, int $empresa_id, array $opts = []): array
    {
        $situacao = $opts['situacao'] ?? 'A';
        if (!in_array($situacao, ['A', 'I', 'all'], true)) {
            $situacao = 'A';
        }
        $tipo = $opts['tipo'] ?? '';
        $tipo = in_array($tipo, ['F', 'J'], true) ? $tipo : '';
        $qRaw = isset($opts['q']) ? (string) $opts['q'] : '';

        $filter = fornecedores_build_list_filter($empresa_id, $situacao, $tipo ?: null, $qRaw);
        $whereSql = $filter['whereSql'];
        $params = $filter['params'];

        $bind = static function (PDOStatement $stmt, array $params): void {
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
        };

        // KPI + total em uma única agregação (elimina COUNT(*) duplicado).
        $kpiSql = 'SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN situacao = \'A\' THEN 1 ELSE 0 END) AS ativos,
        SUM(CASE WHEN situacao = \'I\' THEN 1 ELSE 0 END) AS inativos,
        SUM(CASE WHEN tipo = \'J\' THEN 1 ELSE 0 END) AS pj,
        SUM(CASE WHEN tipo = \'F\' THEN 1 ELSE 0 END) AS pf,
        SUM(CASE WHEN LENGTH(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(codigo_municipio_ibge, \'\'), \' \', \'\'), \'.\', \'\'), \'-\', \'\'), \'/\', \'\')) = 7 THEN 1 ELSE 0 END) AS ibge_ok,
        SUM(CASE WHEN email IS NOT NULL AND TRIM(email) <> \'\' THEN 1 ELSE 0 END) AS email_ok
        ' . $whereSql;
        $kpiStmt = $conn->prepare($kpiSql);
        $bind($kpiStmt, $params);
        $kpiStmt->execute();
        $kpi = $kpiStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $total = (int) ($kpi['total'] ?? 0);

        $all = !empty($opts['all']);
        $perPage = isset($opts['per_page']) ? (int) $opts['per_page'] : 10;
        if (!in_array($perPage, [5, 10, 25, 50, 100], true)) {
            $perPage = 10;
        }
        $page = isset($opts['page']) ? max(1, (int) $opts['page']) : 1;

        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $sortKey = isset($opts['sort']) ? trim((string) $opts['sort']) : 'nome';
        $allowedSort = [
            'nome' => 'nome',
            'tipo' => 'tipo',
            'documento' => '(CASE WHEN tipo = \'J\' THEN COALESCE(cnpj, \'\') ELSE COALESCE(cpf, \'\') END)',
            'cidade_uf' => 'CONCAT(COALESCE(cidade, \'\'), COALESCE(uf, \'\'))',
            'situacao' => 'situacao',
        ];
        $orderCol = $allowedSort[$sortKey] ?? 'nome';
        $dir = (isset($opts['dir']) && strtoupper(trim((string) $opts['dir'])) === 'DESC') ? 'DESC' : 'ASC';

        $cols = 'id, tipo, nome, cpf, cnpj, email, cidade, uf, bairro, endereco, codigo_municipio_ibge, situacao';
        $sqlList = 'SELECT ' . $cols . $whereSql . ' ORDER BY ' . $orderCol . ' ' . $dir . ', id ASC';
        if (!$all) {
            $sqlList .= ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
        } else {
            $sqlList .= ' LIMIT 5000';
        }

        $stmt = $conn->prepare($sqlList);
        $bind($stmt, $params);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [
            'success' => true,
            'fornecedores' => $rows,
            'kpi' => [
                'total' => $total,
                'ativos' => (int) ($kpi['ativos'] ?? 0),
                'inativos' => (int) ($kpi['inativos'] ?? 0),
                'pj' => (int) ($kpi['pj'] ?? 0),
                'pf' => (int) ($kpi['pf'] ?? 0),
                'ibge_ok' => (int) ($kpi['ibge_ok'] ?? 0),
                'email_ok' => (int) ($kpi['email_ok'] ?? 0),
            ],
        ];
        if (!$all) {
            $out['pagination'] = [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => max(1, $totalPages),
            ];
        }
        return $out;
    }
}

if (!function_exists('fornecedores_fmt_doc')) {
    function fornecedores_fmt_doc(string $tipo, ?string $cpf, ?string $cnpj): string
    {
        if ($tipo === 'F' && $cpf) {
            $d = fornecedores_only_digits($cpf);
            if (strlen($d) === 11) {
                return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d);
            }
        }
        if ($tipo === 'J' && $cnpj) {
            $d = fornecedores_only_digits($cnpj);
            if (strlen($d) === 14) {
                return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d);
            }
        }
        return $cpf ?: ($cnpj ?: '—');
    }
}

if (!function_exists('fornecedores_kpi_cell_text')) {
    function fornecedores_kpi_cell_text(int $value, int $total, bool $showPct): string
    {
        if ($showPct && $total > 0) {
            return (string) $value . ' · ' . (string) (int) round($value * 100 / $total) . '%';
        }
        return (string) $value;
    }
}
