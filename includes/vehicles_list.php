<?php
/**
 * Listagem paginada de veículos (compartilhada entre API e SSR da página).
 */

if (!function_exists('vehicles_h')) {
    function vehicles_h(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('vehicles_fmt_km')) {
    function vehicles_fmt_km($km): string
    {
        if ($km === null || $km === '') {
            return '0 km';
        }
        return number_format((float) $km, 0, ',', '.') . ' km';
    }
}

if (!function_exists('vehicles_build_list_filter')) {
    /**
     * @return array{where: string[], params: array<string, mixed>}
     */
    function vehicles_build_list_filter(int $empresa_id, array $opts): array
    {
        $where = ['v.empresa_id = :empresa_id'];
        $params = [':empresa_id' => $empresa_id];

        $search = isset($opts['search']) ? trim((string) $opts['search']) : '';
        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = '(v.placa LIKE :search_placa OR v.modelo LIKE :search_modelo OR v.marca LIKE :search_marca)';
            $params[':search_placa'] = $like;
            $params[':search_modelo'] = $like;
            $params[':search_marca'] = $like;
        }

        $status = isset($opts['status']) ? (string) $opts['status'] : '';
        if ($status !== '' && in_array($status, ['1', '2', '3'], true)) {
            $where[] = 'v.status_id = :status_id';
            $params[':status_id'] = (int) $status;
        }

        $marca = isset($opts['marca']) ? trim((string) $opts['marca']) : '';
        if ($marca !== '') {
            $where[] = 'v.marca LIKE :marca';
            $params[':marca'] = '%' . $marca . '%';
        }

        return ['where' => $where, 'params' => $params];
    }
}

if (!function_exists('vehicles_fetch_summary')) {
    function vehicles_fetch_summary(PDO $conn, int $empresa_id): array
    {
        $sql = "SELECT
            COUNT(*) AS total_veiculos,
            SUM(CASE WHEN v.status_id = 1 THEN 1 ELSE 0 END) AS veiculos_ativos,
            SUM(CASE WHEN v.status_id = 2 THEN 1 ELSE 0 END) AS veiculos_manutencao,
            COALESCE(SUM(v.km_atual), 0) AS quilometragem_total
            FROM veiculos v
            WHERE v.empresa_id = :empresa_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'totalVehicles' => (int) ($row['total_veiculos'] ?? 0),
            'activeVehicles' => (int) ($row['veiculos_ativos'] ?? 0),
            'maintenanceVehicles' => (int) ($row['veiculos_manutencao'] ?? 0),
            'totalMileage' => (float) ($row['quilometragem_total'] ?? 0),
        ];
    }
}

if (!function_exists('vehicles_allowed_sort')) {
    function vehicles_allowed_sort(): array
    {
        return [
            'placa' => 'v.placa',
            'modelo' => 'v.modelo',
            'marca' => 'v.marca',
            'ano' => 'v.ano',
            'status_nome' => 's.nome',
            'cavalo_nome' => 'cv.nome',
            'carreta_nome' => 'cr.nome',
            'km_atual' => 'v.km_atual',
        ];
    }
}

if (!function_exists('vehicles_fetch_list')) {
    /**
     * @param array{page?:int,limit?:int,search?:string,status?:string,marca?:string,sort?:string,dir?:string} $opts
     */
    function vehicles_fetch_list(PDO $conn, int $empresa_id, array $opts = []): array
    {
        $page = isset($opts['page']) ? max(1, (int) $opts['page']) : 1;
        $limit = isset($opts['limit']) ? (int) $opts['limit'] : 10;
        if (!in_array($limit, [5, 10, 25, 50, 100], true)) {
            $limit = 10;
        }

        $filter = vehicles_build_list_filter($empresa_id, $opts);
        $whereClause = implode(' AND ', $filter['where']);
        $params = $filter['params'];

        $bind = static function (PDOStatement $stmt, array $params): void {
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
        };

        $sql_count = 'SELECT COUNT(*) AS total FROM veiculos v WHERE ' . $whereClause;
        $stmt_count = $conn->prepare($sql_count);
        $bind($stmt_count, $params);
        $stmt_count->execute();
        $total = (int) ($stmt_count->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $totalPages = $limit > 0 ? (int) ceil($total / $limit) : 1;
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $limit;

        $sortKey = isset($opts['sort']) ? trim((string) $opts['sort']) : 'placa';
        $allowedSort = vehicles_allowed_sort();
        $orderCol = $allowedSort[$sortKey] ?? 'v.placa';
        $dir = (isset($opts['dir']) && strtoupper(trim((string) $opts['dir'])) === 'DESC') ? 'DESC' : 'ASC';

        $sql = "SELECT v.*,
                s.nome AS status_nome,
                tc.nome AS tipo_combustivel_nome,
                cv.nome AS cavalo_nome,
                cv.eixos AS cavalo_eixos,
                cv.tracao AS cavalo_tracao,
                cr.nome AS carreta_nome,
                cr.capacidade_media AS carreta_capacidade
                FROM veiculos v
                LEFT JOIN status_veiculos s ON v.status_id = s.id
                LEFT JOIN tipos_combustivel tc ON v.tipo_combustivel_id = tc.id
                LEFT JOIN tipos_cavalos cv ON v.id_cavalo = cv.id
                LEFT JOIN tipos_carretas cr ON v.id_carreta = cr.id
                WHERE $whereClause
                ORDER BY $orderCol $dir, v.id ASC
                LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset;

        $stmt = $conn->prepare($sql);
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
            'summary' => vehicles_fetch_summary($conn, $empresa_id),
        ];
    }
}

if (!function_exists('vehicles_render_table_row')) {
    function vehicles_render_table_row(array $vehicle, bool $modern = true): string
    {
        $id = (int) ($vehicle['id'] ?? 0);
        $statusId = (int) ($vehicle['status_id'] ?? 1);
        $cavalo = !empty($vehicle['cavalo_nome'])
            ? vehicles_h($vehicle['cavalo_nome']) . ' (' . vehicles_h((string) ($vehicle['cavalo_eixos'] ?? '')) . ' eixos)'
            : '-';
        $carreta = !empty($vehicle['carreta_nome'])
            ? vehicles_h($vehicle['carreta_nome']) . ' (' . vehicles_h((string) ($vehicle['carreta_capacidade'] ?? '')) . ' ton)'
            : '-';

        if (!$modern) {
            return '<tr>'
                . '<td>' . vehicles_h((string) ($vehicle['placa'] ?? '-')) . '</td>'
                . '<td>' . vehicles_h((string) ($vehicle['modelo'] ?? '-')) . '</td>'
                . '<td>' . vehicles_h((string) ($vehicle['marca'] ?? '-')) . '</td>'
                . '<td>' . vehicles_h((string) ($vehicle['ano'] ?? '-')) . '</td>'
                . '<td><span class="status-badge status-' . $statusId . '">' . vehicles_h((string) ($vehicle['status_nome'] ?? 'Ativo')) . '</span></td>'
                . '<td>' . $cavalo . '</td>'
                . '<td>' . $carreta . '</td>'
                . '<td>' . vehicles_h(vehicles_fmt_km($vehicle['km_atual'] ?? 0)) . '</td>'
                . '<td class="actions"><div class="table-actions">'
                . '<button class="btn-icon view-btn" data-id="' . $id . '" title="Ver detalhes"><i class="fas fa-eye"></i></button>'
                . '<button class="btn-icon edit-btn" data-id="' . $id . '" title="Editar"><i class="fas fa-edit"></i></button>'
                . '<button class="btn-icon delete-btn" data-id="' . $id . '" title="Excluir"><i class="fas fa-trash"></i></button>'
                . '</div></td></tr>';
        }

        return '<tr>'
            . '<td data-col="placa">' . vehicles_h((string) ($vehicle['placa'] ?? '-')) . '</td>'
            . '<td data-col="modelo">' . vehicles_h((string) ($vehicle['modelo'] ?? '-')) . '</td>'
            . '<td data-col="marca">' . vehicles_h((string) ($vehicle['marca'] ?? '-')) . '</td>'
            . '<td data-col="ano">' . vehicles_h((string) ($vehicle['ano'] ?? '-')) . '</td>'
            . '<td data-col="status"><span class="status-badge status-' . $statusId . '">' . vehicles_h((string) ($vehicle['status_nome'] ?? 'Ativo')) . '</span></td>'
            . '<td data-col="cavalo">' . $cavalo . '</td>'
            . '<td data-col="carreta">' . $carreta . '</td>'
            . '<td data-col="km">' . vehicles_h(vehicles_fmt_km($vehicle['km_atual'] ?? 0)) . '</td>'
            . '<td class="actions" data-col="acoes"><div class="table-actions">'
            . '<button class="btn-icon view-btn" data-id="' . $id . '" title="Ver detalhes"><i class="fas fa-eye"></i></button>'
            . '<button class="btn-icon edit-btn" data-id="' . $id . '" title="Editar"><i class="fas fa-edit"></i></button>'
            . '<button class="btn-icon delete-btn" data-id="' . $id . '" title="Excluir"><i class="fas fa-trash"></i></button>'
            . '</div></td></tr>';
    }
}
