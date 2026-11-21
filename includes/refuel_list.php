<?php
/**
 * Listagem e KPIs de abastecimentos (compartilhado entre API e SSR).
 */

if (!function_exists('refuel_list_join_sql')) {
    function refuel_list_join_sql(): string
    {
        return ' FROM abastecimentos a
                LEFT JOIN veiculos v ON a.veiculo_id = v.id
                LEFT JOIN motoristas m ON a.motorista_id = m.id
                LEFT JOIN rotas r ON a.rota_id = r.id
                LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id';
    }
}

if (!function_exists('refuel_apply_list_filters')) {
    /** @param array<string, mixed> $params */
    function refuel_apply_list_filters(array $opts, string &$extraWhere, array &$params): void
    {
        $search = isset($opts['search']) ? trim((string) $opts['search']) : '';
        if ($search !== '') {
            $rawSearch = $search;
            $normalizedSearch = function_exists('mb_strtolower')
                ? mb_strtolower($rawSearch, 'UTF-8')
                : strtolower($rawSearch);
            $normalizedSearch = trim($normalizedSearch);
            $searchConditions = [];
            $searchLike = '%' . $normalizedSearch . '%';
            $rawLike = '%' . $rawSearch . '%';

            $params[':search_vehicle_plate'] = $searchLike;
            $searchConditions[] = 'LOWER(COALESCE(v.placa, "")) LIKE :search_vehicle_plate';
            $params[':search_vehicle_model'] = $searchLike;
            $searchConditions[] = 'LOWER(COALESCE(v.modelo, "")) LIKE :search_vehicle_model';
            $params[':search_driver_name'] = $searchLike;
            $searchConditions[] = 'LOWER(COALESCE(m.nome, "")) LIKE :search_driver_name';
            $params[':search_driver_cpf_mask'] = $rawLike;
            $searchConditions[] = 'm.cpf LIKE :search_driver_cpf_mask';
            $params[':search_station'] = $searchLike;
            $searchConditions[] = 'LOWER(COALESCE(a.posto, "")) LIKE :search_station';
            $params[':search_payment'] = $searchLike;
            $searchConditions[] = 'LOWER(COALESCE(a.forma_pagamento, "")) LIKE :search_payment';
            $params[':search_fuel'] = $searchLike;
            $searchConditions[] = 'LOWER(COALESCE(a.tipo_combustivel, "")) LIKE :search_fuel';
            $params[':search_origin'] = $searchLike;
            $searchConditions[] = 'LOWER(COALESCE(co.nome, "")) LIKE :search_origin';
            $params[':search_destination'] = $searchLike;
            $searchConditions[] = 'LOWER(COALESCE(cd.nome, "")) LIKE :search_destination';
            $params[':search_route_concat'] = $searchLike;
            $searchConditions[] = 'LOWER(CONCAT_WS(" ", COALESCE(co.nome, ""), COALESCE(cd.nome, ""))) LIKE :search_route_concat';
            $params[':search_refuel_id'] = $rawLike;
            $searchConditions[] = 'CAST(a.id AS CHAR) LIKE :search_refuel_id';
            $params[':search_route_id'] = $rawLike;
            $searchConditions[] = 'CAST(r.id AS CHAR) LIKE :search_route_id';
            $params[':search_date_br'] = $rawLike;
            $searchConditions[] = "DATE_FORMAT(a.data_abastecimento, '%d/%m/%Y') LIKE :search_date_br";
            $params[':search_date_iso'] = $rawLike;
            $searchConditions[] = "DATE_FORMAT(a.data_abastecimento, '%Y-%m-%d') LIKE :search_date_iso";

            $searchDigits = preg_replace('/\D+/', '', $rawSearch);
            if ($searchDigits !== '') {
                $digitsLike = '%' . $searchDigits . '%';
                $params[':search_driver_cpf_digits'] = $digitsLike;
                $searchConditions[] = "REPLACE(REPLACE(REPLACE(m.cpf, '.', ''), '-', ''), ' ', '') LIKE :search_driver_cpf_digits";
                $params[':search_plate_digits'] = $digitsLike;
                $searchConditions[] = "REPLACE(REPLACE(REPLACE(v.placa, '-', ''), ' ', ''), '.', '') LIKE :search_plate_digits";
                $params[':search_id_digits'] = $digitsLike;
                $searchConditions[] = 'CAST(a.id AS CHAR) LIKE :search_id_digits';
            }

            $normalizedNumber = str_replace(['R$', ' '], '', str_replace(',', '.', $rawSearch));
            if (is_numeric($normalizedNumber)) {
                $params[':search_numeric_exact'] = (float) $normalizedNumber;
                $searchConditions[] = 'ABS(a.valor_total - :search_numeric_exact) < 0.01';
                $searchConditions[] = 'ABS(a.valor_litro - :search_numeric_exact) < 0.01';
                $searchConditions[] = 'ABS(a.litros - :search_numeric_exact) < 0.01';
            }

            if ($searchConditions !== []) {
                $params[':search_general'] = $searchLike;
                $searchConditions[] = "LOWER(CONCAT_WS(' ',
                        COALESCE(v.placa, ''),
                        COALESCE(v.modelo, ''),
                        COALESCE(m.nome, ''),
                        COALESCE(a.posto, ''),
                        COALESCE(a.tipo_combustivel, ''),
                        COALESCE(a.forma_pagamento, '')
                    )) LIKE :search_general";
                $extraWhere .= ' AND (' . implode(' OR ', $searchConditions) . ')';
            }
        }

        if (!empty($opts['veiculo'])) {
            $extraWhere .= ' AND a.veiculo_id = :veiculo';
            $params[':veiculo'] = $opts['veiculo'];
        }
        if (!empty($opts['motorista'])) {
            $extraWhere .= ' AND a.motorista_id = :motorista';
            $params[':motorista'] = $opts['motorista'];
        }
        if (!empty($opts['rota_id'])) {
            $extraWhere .= ' AND a.rota_id = :rota_id';
            $params[':rota_id'] = $opts['rota_id'];
        }
        if (!empty($opts['combustivel'])) {
            $extraWhere .= ' AND a.tipo_combustivel = :combustivel';
            $params[':combustivel'] = $opts['combustivel'];
        }
        if (!empty($opts['pagamento'])) {
            $extraWhere .= ' AND a.forma_pagamento = :pagamento';
            $params[':pagamento'] = $opts['pagamento'];
        }
        if (!empty($opts['year']) && !empty($opts['month'])) {
            $extraWhere .= ' AND YEAR(a.data_abastecimento) = :year AND MONTH(a.data_abastecimento) = :month';
            $params[':year'] = (int) $opts['year'];
            $params[':month'] = (int) $opts['month'];
        }
    }
}

if (!function_exists('refuel_allowed_sort')) {
    function refuel_allowed_sort(): array
    {
        return [
            'data_abastecimento' => 'a.data_abastecimento',
            'veiculo_placa' => 'v.placa',
            'motorista_nome' => 'm.nome',
            'posto' => 'a.posto',
            'litros' => 'a.litros',
            'valor_litro' => 'a.valor_litro',
            'valor_total' => 'a.valor_total',
            'km_atual' => 'a.km_atual',
            'forma_pagamento' => 'a.forma_pagamento',
            'tipo_combustivel' => 'a.tipo_combustivel',
            'litros_arla' => 'a.litros_arla',
            'rota' => 'CONCAT(COALESCE(co.nome, ""), " ", COALESCE(cd.nome, ""))',
            'id' => 'a.id',
        ];
    }
}

if (!function_exists('refuel_fetch_list')) {
    /**
     * @param array{page?:int,limit?:int,search?:string,veiculo?:mixed,motorista?:mixed,rota_id?:mixed,combustivel?:string,pagamento?:string,year?:int,month?:int,sort?:string,dir?:string} $opts
     */
    function refuel_fetch_list(PDO $conn, int $empresa_id, array $opts = []): array
    {
        $page = isset($opts['page']) ? max(1, (int) $opts['page']) : 1;
        $limit = isset($opts['limit']) ? (int) $opts['limit'] : 10;
        if (!in_array($limit, [5, 10, 25, 50, 100], true)) {
            $limit = 10;
        }

        $params = [':empresa_id' => $empresa_id];
        $baseWhere = ' WHERE a.empresa_id = :empresa_id AND a.status = \'aprovado\'';
        $extraWhere = '';
        refuel_apply_list_filters($opts, $extraWhere, $params);
        $whereSql = $baseWhere . $extraWhere;
        $joinSql = refuel_list_join_sql();

        $bind = static function (PDOStatement $stmt, array $params): void {
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
        };

        $countSql = 'SELECT COUNT(*) AS total' . $joinSql . $whereSql;
        $countStmt = $conn->prepare($countSql);
        $bind($countStmt, $params);
        $countStmt->execute();
        $total = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $totalPages = $limit > 0 ? (int) ceil($total / $limit) : 1;
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $limit;

        $sortKey = isset($opts['sort']) ? trim((string) $opts['sort']) : 'data_abastecimento';
        $allowedSort = refuel_allowed_sort();
        $orderCol = $allowedSort[$sortKey] ?? 'a.data_abastecimento';
        $dir = (isset($opts['dir']) && strtoupper(trim((string) $opts['dir'])) === 'ASC') ? 'ASC' : 'DESC';

        $listSql = 'SELECT a.*,
                    v.placa AS veiculo_placa,
                    m.nome AS motorista_nome,
                    co.nome AS cidade_origem_nome,
                    cd.nome AS cidade_destino_nome'
            . $joinSql . $whereSql
            . ' ORDER BY ' . $orderCol . ' ' . $dir . ', a.id DESC'
            . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        $stmt = $conn->prepare($listSql);
        $bind($stmt, $params);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'data' => $rows,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => max(1, $totalPages),
            ],
        ];
    }
}

if (!function_exists('refuel_fetch_summary')) {
    function refuel_fetch_summary(PDO $conn, int $empresa_id, ?int $year = null, ?int $month = null): array
    {
        $year = ($year !== null && $year > 0) ? (int) $year : (int) date('Y');
        $month = ($month !== null && $month >= 1 && $month <= 12) ? (int) $month : (int) date('n');

        $sql = "SELECT
                COUNT(*) AS total_abastecimentos,
                COALESCE(SUM(litros), 0) AS total_litros,
                COALESCE(SUM(valor_total), 0) AS total_gasto,
                COALESCE(AVG(valor_litro), 0) AS media_valor_litro,
                CASE
                    WHEN COUNT(*) > 1
                    AND MAX(km_atual) > MIN(km_atual)
                    AND SUM(litros) > 0
                    THEN (MAX(km_atual) - MIN(km_atual)) / SUM(litros)
                    ELSE 0
                END AS media_km_litro
            FROM abastecimentos
            WHERE empresa_id = :empresa_id AND status = 'aprovado'
              AND YEAR(data_abastecimento) = :year AND MONTH(data_abastecimento) = :month";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindValue(':year', $year, PDO::PARAM_INT);
        $stmt->bindValue(':month', $month, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_abastecimentos' => (int) ($row['total_abastecimentos'] ?? 0),
            'total_litros' => (float) ($row['total_litros'] ?? 0),
            'total_gasto' => (float) ($row['total_gasto'] ?? 0),
            'media_valor_litro' => (float) ($row['media_valor_litro'] ?? 0),
            'media_km_litro' => (float) ($row['media_km_litro'] ?? 0),
            'year' => $year,
            'month' => $month,
        ];
    }
}

if (!function_exists('refuel_kpi_display')) {
    function refuel_kpi_display(string $key, array $summary): string
    {
        switch ($key) {
            case 'abastecimentos':
                return number_format((float) ($summary['total_abastecimentos'] ?? 0), 0, ',', '.');
            case 'litros':
                return number_format((float) ($summary['total_litros'] ?? 0), 2, ',', '.') . ' L';
            case 'valor':
                return 'R$ ' . number_format((float) ($summary['total_gasto'] ?? 0), 2, ',', '.');
            case 'media_litro':
                return 'R$ ' . number_format((float) ($summary['media_valor_litro'] ?? 0), 2, ',', '.') . '/L';
            default:
                return '0';
        }
    }
}
