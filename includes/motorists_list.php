<?php
/**
 * Listagem paginada de motoristas (compartilhada entre API e SSR da página).
 */

if (!function_exists('motorists_fetch_list')) {
    /**
     * @param array{limit?:int,page?:int,status?:string|null,search?:string|null,categoria_cnh?:string|null,tipo_contrato?:string|null,sort?:string,order?:string,only_favoritos?:bool} $opts
     */
    function motorists_fetch_list(PDO $conn, int $empresa_id, ?int $usuario_id, array $opts = []): array
    {
        try {
            $limit = isset($opts['limit']) ? (int) $opts['limit'] : 10;
            if (!in_array($limit, [5, 10, 25, 50, 100], true)) {
                $limit = 10;
            }
            $page = isset($opts['page']) ? max(1, (int) $opts['page']) : 1;
            $status = isset($opts['status']) && $opts['status'] !== '' ? (string) $opts['status'] : null;
            $search = isset($opts['search']) && $opts['search'] !== '' ? trim((string) $opts['search']) : null;
            $categoria_cnh = isset($opts['categoria_cnh']) && $opts['categoria_cnh'] !== '' ? (string) $opts['categoria_cnh'] : null;
            $tipo_contrato = isset($opts['tipo_contrato']) && $opts['tipo_contrato'] !== '' ? (string) $opts['tipo_contrato'] : null;
            $sort = isset($opts['sort']) ? (string) $opts['sort'] : 'nome';
            $order = (isset($opts['order']) && strtoupper((string) $opts['order']) === 'DESC') ? 'DESC' : 'ASC';
            $only_favoritos = !empty($opts['only_favoritos']);

            $offset = ($page - 1) * $limit;

            $where = ['m.empresa_id = :empresa_id'];
            $params = [':empresa_id' => $empresa_id];

            if ($status) {
                $where[] = 'm.disponibilidade_id = :status';
                $params[':status'] = $status;
            }
            if ($categoria_cnh) {
                $where[] = 'm.categoria_cnh_id = :categoria_cnh';
                $params[':categoria_cnh'] = $categoria_cnh;
            }
            if ($tipo_contrato) {
                $where[] = 'm.tipo_contrato_id = :tipo_contrato';
                $params[':tipo_contrato'] = $tipo_contrato;
            }

            if ($only_favoritos && $usuario_id) {
                $st = $conn->prepare('SHOW TABLES LIKE \'motorista_favoritos\'');
                $st->execute();
                if ($st->rowCount() > 0) {
                    $st = $conn->prepare('SELECT motorista_id FROM motorista_favoritos WHERE usuario_id = ? AND empresa_id = ?');
                    $st->execute([$usuario_id, $empresa_id]);
                    $fav_ids = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'motorista_id');
                    if (!empty($fav_ids)) {
                        $where[] = 'm.id IN (' . implode(',', array_map('intval', $fav_ids)) . ')';
                    } else {
                        $where[] = '1=0';
                    }
                }
            }

            if ($search) {
                $searchLike = '%' . $search . '%';
                $searchConditions = [];
                $params[':search_nome'] = $searchLike;
                $searchConditions[] = 'm.nome LIKE :search_nome';
                $params[':search_email'] = $searchLike;
                $searchConditions[] = 'm.email LIKE :search_email';
                $params[':search_cnh'] = $searchLike;
                $searchConditions[] = 'm.cnh LIKE :search_cnh';
                $params[':search_cpf_mask'] = $searchLike;
                $searchConditions[] = 'm.cpf LIKE :search_cpf_mask';
                $params[':search_phone_mask'] = $searchLike;
                $searchConditions[] = 'm.telefone LIKE :search_phone_mask';

                $searchDigits = preg_replace('/\D+/', '', $search);
                if ($searchDigits !== '') {
                    $params[':search_cpf_digits'] = '%' . $searchDigits . '%';
                    $searchConditions[] = "REPLACE(REPLACE(REPLACE(REPLACE(m.cpf, '.', ''), '-', ''), ' ', ''), '/', '') LIKE :search_cpf_digits";
                    $params[':search_phone_digits'] = '%' . $searchDigits . '%';
                    $searchConditions[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(m.telefone, '(', ''), ')', ''), '-', ''), ' ', ''), '+', '') LIKE :search_phone_digits";
                }
                if (!empty($searchConditions)) {
                    $where[] = '(' . implode(' OR ', $searchConditions) . ')';
                }
            }

            $whereClause = implode(' AND ', $where);

            $sortMap = [
                'nome' => 'm.nome',
                'cpf' => 'm.cpf',
                'cnh' => 'm.cnh',
                'telefone' => 'm.telefone',
                'email' => 'm.email',
                'porcentagem_comissao' => 'm.porcentagem_comissao',
                'disponibilidade_nome' => 'dm.nome',
                'categoria_cnh_nome' => 'cc.nome',
                'tipo_contrato_nome' => 'tc.nome',
            ];
            $sortCol = isset($sortMap[$sort]) ? $sortMap[$sort] : 'm.nome';

            $sql_count = "SELECT COUNT(*) as total FROM motoristas m WHERE $whereClause";
            $stmt_count = $conn->prepare($sql_count);
            foreach ($params as $key => $value) {
                $stmt_count->bindValue($key, $value);
            }
            $stmt_count->execute();
            $total = (int) $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
            $totalPages = $total > 0 ? (int) ceil($total / $limit) : 1;
            if ($page > $totalPages) {
                $page = $totalPages;
                $offset = ($page - 1) * $limit;
            }

            $sql = "SELECT m.*,
                dm.nome as disponibilidade_nome,
                tc.nome as tipo_contrato_nome,
                cc.nome as categoria_cnh_nome
                FROM motoristas m
                LEFT JOIN disponibilidades_motoristas dm ON m.disponibilidade_id = dm.id
                LEFT JOIN tipos_contrato tc ON m.tipo_contrato_id = tc.id
                LEFT JOIN categorias_cnh cc ON m.categoria_cnh_id = cc.id
                WHERE $whereClause
                ORDER BY $sortCol $order
                LIMIT :limit OFFSET :offset";

            $stmt = $conn->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $motorists = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $favoritos_ids = [];
            if ($usuario_id) {
                try {
                    $st = $conn->prepare('SHOW TABLES LIKE \'motorista_favoritos\'');
                    $st->execute();
                    if ($st->rowCount() > 0) {
                        $st = $conn->prepare('SELECT motorista_id FROM motorista_favoritos WHERE usuario_id = ? AND empresa_id = ?');
                        $st->execute([$usuario_id, $empresa_id]);
                        $favoritos_ids = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'motorista_id');
                    }
                } catch (Exception $e) {
                    // ignore
                }
            }
            foreach ($motorists as &$m) {
                $m['is_favorite'] = in_array((int) $m['id'], array_map('intval', $favoritos_ids), true);
            }
            unset($m);

            $sql_summary = "SELECT
                COUNT(*) as total_motorists,
                SUM(CASE WHEN disponibilidade_id = 1 THEN 1 ELSE 0 END) as motorists_ativos,
                SUM(CASE WHEN disponibilidade_id = 2 THEN 1 ELSE 0 END) as motorists_ferias,
                SUM(CASE WHEN disponibilidade_id = 3 THEN 1 ELSE 0 END) as motorists_licenca,
                SUM(CASE WHEN disponibilidade_id = 4 THEN 1 ELSE 0 END) as motorists_inativos,
                SUM(CASE WHEN disponibilidade_id = 5 THEN 1 ELSE 0 END) as motorists_afastados,
                AVG(CASE WHEN porcentagem_comissao IS NOT NULL THEN porcentagem_comissao END) as media_comissao_pct
                FROM motoristas
                WHERE empresa_id = :empresa_id";
            $stmt_summary = $conn->prepare($sql_summary);
            $stmt_summary->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt_summary->execute();
            $summary = $stmt_summary->fetch(PDO::FETCH_ASSOC) ?: [];

            $sql_commission = "SELECT
                SUM(CASE WHEN r.comissao IS NOT NULL AND r.comissao > 0 THEN r.comissao ELSE 0 END) as total_comissao_mes,
                COUNT(CASE WHEN r.comissao IS NOT NULL AND r.comissao > 0 THEN 1 END) as rotas_com_comissao_mes,
                COUNT(DISTINCT r.motorista_id) as motorists_com_comissao_mes
                FROM rotas r
                INNER JOIN motoristas m ON r.motorista_id = m.id
                WHERE r.empresa_id = :empresa_id
                AND r.status = 'aprovado'
                AND MONTH(r.data_rota) = MONTH(CURRENT_DATE())
                AND YEAR(r.data_rota) = YEAR(CURRENT_DATE())";
            $stmt_commission = $conn->prepare($sql_commission);
            $stmt_commission->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt_commission->execute();
            $commission = $stmt_commission->fetch(PDO::FETCH_ASSOC) ?: [];
            $summary = array_merge($summary, $commission);

            return [
                'success' => true,
                'motorists' => $motorists,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => max(1, $totalPages),
                ],
                'summary' => $summary,
            ];
        } catch (PDOException $e) {
            error_log('motorists_fetch_list: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Erro ao buscar lista de motoristas'];
        }
    }
}

if (!function_exists('motorists_foto_html')) {
    function motorists_foto_html(array $motorist): string
    {
        if (!empty($motorist['foto_motorista'])) {
            $fname = basename((string) $motorist['foto_motorista']);
            $src = htmlspecialchars('../uploads/motoristas/foto/' . $fname, ENT_QUOTES, 'UTF-8');
            return '<img src="' . $src . '" alt="" class="motorist-thumb" style="width:36px;height:36px;object-fit:cover;border-radius:50%;">';
        }
        return '<span class="motorist-thumb-placeholder" style="width:36px;height:36px;display:inline-flex;align-items:center;justify-content:center;background:var(--bg-tertiary);border-radius:50%;font-size:0.8rem;color:var(--text-muted);"><i class="fas fa-user"></i></span>';
    }
}

if (!function_exists('motorists_render_table_row')) {
    function motorists_render_table_row(array $motorist): string
    {
        $id = (int) ($motorist['id'] ?? 0);
        $dispNome = htmlspecialchars((string) ($motorist['disponibilidade_nome'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
        $dispClass = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($motorist['disponibilidade_nome'] ?? '')));
        $nome = htmlspecialchars((string) ($motorist['nome'] ?? ''), ENT_QUOTES, 'UTF-8');
        $cpf = htmlspecialchars((string) ($motorist['cpf'] ?? ''), ENT_QUOTES, 'UTF-8') ?: '-';
        $cnh = htmlspecialchars((string) ($motorist['cnh'] ?? ''), ENT_QUOTES, 'UTF-8') ?: '-';
        $cat = htmlspecialchars((string) ($motorist['categoria_cnh_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?: '-';
        $tel = htmlspecialchars((string) ($motorist['telefone'] ?? ''), ENT_QUOTES, 'UTF-8') ?: '-';
        $email = htmlspecialchars((string) ($motorist['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?: '-';
        $comissao = isset($motorist['porcentagem_comissao']) && $motorist['porcentagem_comissao'] !== ''
            ? htmlspecialchars((string) $motorist['porcentagem_comissao'], ENT_QUOTES, 'UTF-8') . '%'
            : '-';
        $favClass = !empty($motorist['is_favorite']) ? ' is-favorite' : '';
        $favTitle = !empty($motorist['is_favorite']) ? 'Remover dos favoritos' : 'Adicionar aos favoritos';

        return '<tr>'
            . '<td>' . motorists_foto_html($motorist) . '</td>'
            . '<td>' . $nome . '</td>'
            . '<td>' . $cpf . '</td>'
            . '<td>' . $cnh . '</td>'
            . '<td>' . $cat . '</td>'
            . '<td>' . $tel . '</td>'
            . '<td>' . $email . '</td>'
            . '<td><span class="status-badge ' . htmlspecialchars($dispClass, ENT_QUOTES, 'UTF-8') . '">' . $dispNome . '</span></td>'
            . '<td>' . $comissao . '</td>'
            . '<td class="actions">'
            . '<button class="btn-icon view-btn" data-id="' . $id . '" title="Ver detalhes"><i class="fas fa-eye"></i></button>'
            . '<button class="btn-icon favorite-btn' . $favClass . '" data-id="' . $id . '" title="' . htmlspecialchars($favTitle, ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-star"></i></button>'
            . '<button class="btn-icon history-btn" data-id="' . $id . '" title="Histórico de alterações"><i class="fas fa-history"></i></button>'
            . '<button class="btn-icon edit-btn" data-id="' . $id . '" title="Editar"><i class="fas fa-edit"></i></button>'
            . '<button class="btn-icon delete-btn" data-id="' . $id . '" title="Excluir"><i class="fas fa-trash"></i></button>'
            . '</td>'
            . '</tr>';
    }
}
