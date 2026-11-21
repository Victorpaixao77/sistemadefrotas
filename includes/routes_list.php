<?php
/**
 * Consulta paginada de rotas (compartilhada entre API e SSR da página).
 */

if (!function_exists('routes_h')) {
    function routes_h(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('routes_fmt_date')) {
    function routes_fmt_date(?string $d): string
    {
        if ($d === null || $d === '') {
            return '-';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            $dt = DateTime::createFromFormat('Y-m-d', $d);
            return $dt ? $dt->format('d/m/Y') : '-';
        }
        try {
            $dt = new DateTime($d);
            return $dt->format('d/m/Y');
        } catch (Exception $e) {
            return '-';
        }
    }
}

if (!function_exists('routes_fmt_datetime')) {
    function routes_fmt_datetime(?string $v): string
    {
        if ($v === null || $v === '') {
            return '-';
        }
        try {
            $dt = new DateTime($v);
            return $dt->format('d/m/Y H:i');
        } catch (Exception $e) {
            return '-';
        }
    }
}

if (!function_exists('routes_fmt_distance')) {
    function routes_fmt_distance($km): string
    {
        return number_format((float) ($km ?: 0), 0, ',', '.') . ' km';
    }
}

if (!function_exists('routes_fmt_currency')) {
    function routes_fmt_currency($value): string
    {
        return 'R$ ' . number_format((float) ($value ?: 0), 2, ',', '.');
    }
}

if (!function_exists('routes_fmt_pct')) {
    function routes_fmt_pct($v): string
    {
        if ($v === null || $v === '') {
            return '-';
        }
        return number_format((float) $v, 1, ',', '.') . '%';
    }
}

if (!function_exists('routes_fmt_km_field')) {
    function routes_fmt_km_field($v): string
    {
        if ($v === null || $v === '') {
            return '-';
        }
        return number_format((float) $v, 0, ',', '.');
    }
}

if (!function_exists('routes_fmt_peso')) {
    function routes_fmt_peso($v): string
    {
        if ($v === null || $v === '') {
            return '-';
        }
        return number_format((float) $v, 2, ',', '.');
    }
}

if (!function_exists('routes_truncate_desc')) {
    function routes_truncate_desc(?string $s, int $max = 48): string
    {
        if ($s === null || $s === '') {
            return '-';
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($s) > $max ? mb_substr($s, 0, $max) . '…' : $s;
        }
        return strlen($s) > $max ? substr($s, 0, $max) . '…' : $s;
    }
}

if (!function_exists('routes_build_list_filter')) {
    /**
     * @return array{joinSql: string, whereSql: string, params: array<string, mixed>}
     */
    function routes_build_list_filter(int $empresa_id, array $opts): array
    {
        $joinSql = ' FROM rotas r
                LEFT JOIN motoristas m ON r.motorista_id = m.id
                LEFT JOIN veiculos v ON r.veiculo_id = v.id
                LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id';
        $whereSql = ' WHERE r.empresa_id = :empresa_id AND r.status = \'aprovado\'';
        $params = [':empresa_id' => $empresa_id];

        $search = isset($opts['search']) ? trim((string) $opts['search']) : '';
        if ($search !== '') {
            $normalizedSearch = function_exists('mb_strtolower')
                ? mb_strtolower($search, 'UTF-8')
                : strtolower($search);
            $searchConditions = [];
            $searchLike = '%' . $normalizedSearch . '%';

            $params[':search_origin'] = $searchLike;
            $searchConditions[] = 'LOWER(COALESCE(co.nome, "")) LIKE :search_origin';
            $params[':search_destination'] = $searchLike;
            $searchConditions[] = 'LOWER(COALESCE(cd.nome, "")) LIKE :search_destination';
            $params[':search_driver_name'] = $searchLike;
            $searchConditions[] = 'LOWER(COALESCE(m.nome, "")) LIKE :search_driver_name';
            $params[':search_vehicle_plate'] = $searchLike;
            $searchConditions[] = 'LOWER(COALESCE(v.placa, "")) LIKE :search_vehicle_plate';
            $params[':search_vehicle_model'] = $searchLike;
            $searchConditions[] = 'LOWER(COALESCE(v.modelo, "")) LIKE :search_vehicle_model';
            $params[':search_combined_route'] = $searchLike;
            $searchConditions[] = 'LOWER(CONCAT_WS(" ", COALESCE(co.nome, ""), COALESCE(cd.nome, ""))) LIKE :search_combined_route';

            $rawLike = '%' . $search . '%';
            $params[':search_route_id'] = $rawLike;
            $searchConditions[] = 'CAST(r.id AS CHAR) LIKE :search_route_id';
            $params[':search_driver_cpf_mask'] = $rawLike;
            $searchConditions[] = 'm.cpf LIKE :search_driver_cpf_mask';
            $params[':search_date_br'] = $rawLike;
            $searchConditions[] = "DATE_FORMAT(r.data_rota, '%d/%m/%Y') LIKE :search_date_br";
            $params[':search_date_iso'] = $rawLike;
            $searchConditions[] = "DATE_FORMAT(r.data_rota, '%Y-%m-%d') LIKE :search_date_iso";

            $searchDigits = preg_replace('/\D+/', '', $search);
            if ($searchDigits !== '') {
                $digitsLike = '%' . $searchDigits . '%';
                $params[':search_driver_cpf_digits'] = $digitsLike;
                $searchConditions[] = "REPLACE(REPLACE(REPLACE(m.cpf, '.', ''), '-', ''), ' ', '') LIKE :search_driver_cpf_digits";
                $params[':search_plate_digits'] = $digitsLike;
                $searchConditions[] = "REPLACE(REPLACE(REPLACE(REPLACE(v.placa, '-', ''), ' ', ''), '.', ''), '/', '') LIKE :search_plate_digits";
                $params[':search_route_id_digits'] = $digitsLike;
                $searchConditions[] = 'CAST(r.id AS CHAR) LIKE :search_route_id_digits';
            }

            if ($searchConditions !== []) {
                $whereSql .= ' AND (' . implode(' OR ', $searchConditions) . ')';
            }
        }

        $delivery = isset($opts['delivery']) ? trim((string) $opts['delivery']) : '';
        if ($delivery === 'no_prazo') {
            $whereSql .= ' AND r.no_prazo = 1';
        } elseif ($delivery === 'atrasado') {
            $whereSql .= ' AND r.no_prazo = 0';
        }

        $driver = isset($opts['driver']) ? trim((string) $opts['driver']) : '';
        if ($driver !== '') {
            $whereSql .= ' AND r.motorista_id = :driver';
            $params[':driver'] = $driver;
        }

        $date = isset($opts['date']) ? trim((string) $opts['date']) : '';
        if ($date !== '') {
            $whereSql .= ' AND DATE(r.data_rota) = :date';
            $params[':date'] = $date;
        }

        $dateFrom = isset($opts['date_from']) ? trim((string) $opts['date_from']) : '';
        if ($dateFrom !== '') {
            $whereSql .= ' AND DATE(r.data_rota) >= :date_from';
            $params[':date_from'] = $dateFrom;
        }

        $dateTo = isset($opts['date_to']) ? trim((string) $opts['date_to']) : '';
        if ($dateTo !== '') {
            $whereSql .= ' AND DATE(r.data_rota) <= :date_to';
            $params[':date_to'] = $dateTo;
        }

        return ['joinSql' => $joinSql, 'whereSql' => $whereSql, 'params' => $params];
    }
}

if (!function_exists('routes_allowed_sort')) {
    function routes_allowed_sort(): array
    {
        return [
            'data_rota' => 'r.data_rota',
            'motorista_nome' => 'm.nome',
            'veiculo_placa' => 'v.placa',
            'rota' => 'CONCAT(COALESCE(co.nome, ""), " ", COALESCE(cd.nome, ""))',
            'distancia_km' => 'r.distancia_km',
            'frete' => 'r.frete',
            'id' => 'r.id',
            'data_saida' => 'r.data_saida',
            'data_chegada' => 'r.data_chegada',
            'km_saida' => 'r.km_saida',
            'km_chegada' => 'r.km_chegada',
            'km_vazio' => 'r.km_vazio',
            'total_km' => 'r.total_km',
            'comissao' => 'r.comissao',
            'eficiencia_viagem' => 'r.eficiencia_viagem',
            'percentual_vazio' => 'r.percentual_vazio',
            'peso_carga' => 'r.peso_carga',
            'descricao_carga' => 'r.descricao_carga',
            'no_prazo' => 'r.no_prazo',
        ];
    }
}

if (!function_exists('routes_fetch_list')) {
    /**
     * @param array{page?:int,limit?:int,search?:string,delivery?:string,driver?:string,date?:string,date_from?:string,date_to?:string,sort?:string,dir?:string} $opts
     */
    function routes_fetch_list(PDO $conn, int $empresa_id, array $opts = []): array
    {
        $page = isset($opts['page']) ? max(1, (int) $opts['page']) : 1;
        $limit = isset($opts['limit']) ? (int) $opts['limit'] : 10;
        if (!in_array($limit, [5, 10, 25, 50, 100], true)) {
            $limit = 10;
        }

        $filter = routes_build_list_filter($empresa_id, $opts);
        $params = $filter['params'];

        $bind = static function (PDOStatement $stmt, array $params): void {
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
        };

        $countSql = 'SELECT COUNT(*) AS total' . $filter['joinSql'] . $filter['whereSql'];
        $countStmt = $conn->prepare($countSql);
        $bind($countStmt, $params);
        $countStmt->execute();
        $total = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 1;
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $limit;

        $sortKey = isset($opts['sort']) ? trim((string) $opts['sort']) : 'data_rota';
        $allowedSort = routes_allowed_sort();
        $orderCol = $allowedSort[$sortKey] ?? 'r.data_rota';
        $dir = (isset($opts['dir']) && strtoupper(trim((string) $opts['dir'])) === 'ASC') ? 'ASC' : 'DESC';

        $listSql = 'SELECT r.*,
                    m.nome AS motorista_nome,
                    v.placa AS veiculo_placa,
                    co.nome AS cidade_origem_nome,
                    cd.nome AS cidade_destino_nome'
            . $filter['joinSql']
            . $filter['whereSql']
            . ' ORDER BY ' . $orderCol . ' ' . $dir . ', r.id DESC'
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
                'total_pages' => max(1, $totalPages),
            ],
        ];
    }
}

if (!function_exists('routes_render_table_row')) {
    function routes_render_table_row(array $route): string
    {
        $id = (int) ($route['id'] ?? 0);
        $noPrazo = !empty($route['no_prazo']);
        $badgeClass = $noPrazo ? 'status-ok' : 'status-late';
        $badgeText = $noPrazo ? 'No Prazo' : 'Atrasado';
        $orig = routes_h((string) ($route['cidade_origem_nome'] ?? '-'));
        $dest = routes_h((string) ($route['cidade_destino_nome'] ?? '-'));
        $comissao = ($route['comissao'] ?? '') !== '' && $route['comissao'] !== null
            ? routes_fmt_currency($route['comissao'])
            : '-';

        return '<tr>'
            . '<td class="col-sel" data-col="sel"><input type="checkbox" class="route-row-check" value="' . $id . '" aria-label="Selecionar rota"></td>'
            . '<td data-col="data">' . routes_h(routes_fmt_date($route['data_rota'] ?? null)) . '</td>'
            . '<td data-col="motorista">' . routes_h((string) ($route['motorista_nome'] ?? '-')) . '</td>'
            . '<td data-col="veiculo">' . routes_h((string) ($route['veiculo_placa'] ?? '-')) . '</td>'
            . '<td data-col="rota">' . $orig . ' → ' . $dest . '</td>'
            . '<td data-col="dist">' . routes_h(routes_fmt_distance($route['distancia_km'] ?? 0)) . '</td>'
            . '<td data-col="frete">' . routes_h(routes_fmt_currency($route['frete'] ?? 0)) . '</td>'
            . '<td data-col="col_id">' . routes_h((string) $id) . '</td>'
            . '<td data-col="col_saida">' . routes_h(routes_fmt_datetime($route['data_saida'] ?? null)) . '</td>'
            . '<td data-col="col_chegada">' . routes_h(routes_fmt_datetime($route['data_chegada'] ?? null)) . '</td>'
            . '<td data-col="col_km_saida">' . routes_h(routes_fmt_km_field($route['km_saida'] ?? null)) . '</td>'
            . '<td data-col="col_km_chegada">' . routes_h(routes_fmt_km_field($route['km_chegada'] ?? null)) . '</td>'
            . '<td data-col="col_km_vazio">' . routes_h(routes_fmt_km_field($route['km_vazio'] ?? null)) . '</td>'
            . '<td data-col="col_total_km">' . routes_h(routes_fmt_km_field($route['total_km'] ?? null)) . '</td>'
            . '<td data-col="col_comissao">' . routes_h($comissao) . '</td>'
            . '<td data-col="col_eficiencia">' . routes_h(routes_fmt_pct($route['eficiencia_viagem'] ?? null)) . '</td>'
            . '<td data-col="col_pct_vazio">' . routes_h(routes_fmt_pct($route['percentual_vazio'] ?? null)) . '</td>'
            . '<td data-col="col_peso">' . routes_h(routes_fmt_peso($route['peso_carga'] ?? null)) . '</td>'
            . '<td data-col="col_desc_carga">' . routes_h(routes_truncate_desc($route['descricao_carga'] ?? null)) . '</td>'
            . '<td data-col="status"><span class="status-badge ' . $badgeClass . '">' . $badgeText . '</span></td>'
            . '<td class="actions" data-col="acoes">'
            . '<button class="btn-icon view-btn" data-id="' . $id . '" title="Ver" aria-label="Ver detalhes da rota"><i class="fas fa-eye"></i></button>'
            . '<button class="btn-icon edit-btn" data-id="' . $id . '" title="Editar" aria-label="Editar rota"><i class="fas fa-edit"></i></button>'
            . '<button class="btn-icon expenses-btn" data-id="' . $id . '" title="Despesas da viagem" aria-label="Despesas da viagem"><i class="fas fa-money-bill"></i></button>'
            . '<button class="btn-icon duplicate-btn" data-id="' . $id . '" title="Duplicar" aria-label="Duplicar rota"><i class="fas fa-copy"></i></button>'
            . '<button class="btn-icon delete-btn" data-id="' . $id . '" title="Excluir" aria-label="Excluir rota"><i class="fas fa-trash"></i></button>'
            . '</td>'
            . '</tr>';
    }
}

if (!function_exists('routes_kpi_display_value')) {
    function routes_kpi_display_value(string $kpiKey, array $dash): string
    {
        switch ($kpiKey) {
            case 'total':
                return (string) (int) ($dash['total_rotas'] ?? 0);
            case 'concluidas':
                return (string) (int) ($dash['rotas_concluidas'] ?? 0);
            case 'distancia':
                return routes_fmt_distance($dash['distancia_total'] ?? 0);
            case 'frete':
                return routes_fmt_currency($dash['frete_total'] ?? 0);
            case 'no_prazo':
                return (string) (int) ($dash['rotas_no_prazo'] ?? 0);
            case 'atrasadas':
                return (string) (int) ($dash['rotas_atrasadas'] ?? 0);
            case 'eficiencia':
                return number_format((float) ($dash['media_eficiencia'] ?? 0), 1, ',', '.') . '%';
            case 'km_vazio':
                return number_format((float) ($dash['percentual_vazio'] ?? 0), 1, ',', '.') . '%';
            default:
                return '0';
        }
    }
}
