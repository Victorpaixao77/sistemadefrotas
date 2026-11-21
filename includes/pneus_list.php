<?php
/**
 * Listagem paginada de pneus e métricas da página (compartilhado entre API e SSR).
 */

require_once __DIR__ . '/pneus_helpers.php';

if (!function_exists('pneus_detect_allocation_tables')) {
  /**
   * @return array{has_inst:bool,has_eixo:bool,has_apf:bool,has_apf_posicao:bool}
   */
  function pneus_detect_allocation_tables(PDO $pdo): array
  {
    $has_inst = false;
    $has_eixo = false;
    $has_apf = false;
    $has_apf_posicao = false;
    try {
      $r = $pdo->query("SHOW TABLES LIKE 'instalacoes_pneus'");
      $has_inst = $r && $r->rowCount() > 0;
      $r = $pdo->query("SHOW TABLES LIKE 'eixo_pneus'");
      $has_eixo = $r && $r->rowCount() > 0;
      $r = $pdo->query("SHOW TABLES LIKE 'alocacoes_pneus_flexiveis'");
      $has_apf = $r && $r->rowCount() > 0;
      if ($has_apf) {
        $r = $pdo->query("SHOW COLUMNS FROM alocacoes_pneus_flexiveis LIKE 'posicao_id'");
        $has_apf_posicao = $r && $r->rowCount() > 0;
      }
    } catch (Exception $e) {
      // ignora
    }
    return compact('has_inst', 'has_eixo', 'has_apf', 'has_apf_posicao');
  }
}

if (!function_exists('pneus_build_list_join_sql')) {
  /**
   * @return array{join:string,veiculo_placa_sql:string,posicao_nome_sql:string}
   */
  function pneus_build_list_join_sql(array $alloc): array
  {
    $parts_v = [];
    $parts_p = [];
    $join = '';
    if ($alloc['has_inst']) {
      $join .= "
                LEFT JOIN instalacoes_pneus ip ON p.id = ip.pneu_id AND ip.data_remocao IS NULL
                LEFT JOIN veiculos v_inst ON v_inst.id = ip.veiculo_id AND v_inst.empresa_id = p.empresa_id
                LEFT JOIN posicoes_pneus pp_inst ON pp_inst.id = ip.posicao_id
            ";
      $parts_v[] = 'v_inst.placa';
      $parts_p[] = 'pp_inst.nome';
    }
    if ($alloc['has_apf']) {
      $join .= "
                LEFT JOIN alocacoes_pneus_flexiveis apf ON p.id = apf.pneu_id AND apf.ativo = 1
                LEFT JOIN eixos_veiculos ev ON apf.eixo_veiculo_id = ev.id
                LEFT JOIN veiculos v_flex ON v_flex.id = ev.veiculo_id AND v_flex.empresa_id = p.empresa_id
                " . ($alloc['has_apf_posicao'] ? "LEFT JOIN posicoes_pneus pp_flex ON pp_flex.id = apf.posicao_id" : '') . "
            ";
      $parts_v[] = 'v_flex.placa';
      if ($alloc['has_apf_posicao']) {
        $parts_p[] = 'pp_flex.nome';
      }
    }
    if ($alloc['has_eixo']) {
      $join .= "
                LEFT JOIN eixo_pneus ep ON p.id = ep.pneu_id AND ep.status = 'alocado'
                LEFT JOIN eixos e ON ep.eixo_id = e.id
                LEFT JOIN veiculos v ON v.id = e.veiculo_id AND v.empresa_id = p.empresa_id
                LEFT JOIN posicoes_pneus pp ON pp.id = ep.posicao_id
            ";
      $parts_v[] = 'v.placa';
      $parts_p[] = 'pp.nome';
    }
    $veiculo_placa_sql = count($parts_v) > 0 ? 'COALESCE(' . implode(', ', $parts_v) . ')' : 'NULL';
    $posicao_nome_sql = count($parts_p) > 0 ? 'COALESCE(' . implode(', ', $parts_p) . ')' : 'NULL';
    if ($join === '') {
      $veiculo_placa_sql = 'NULL';
      $posicao_nome_sql = 'NULL';
    }
    return [
      'join' => $join,
      'veiculo_placa_sql' => $veiculo_placa_sql,
      'posicao_nome_sql' => $posicao_nome_sql,
    ];
  }
}

if (!function_exists('pneus_fetch_list')) {
  /**
   * @param array{page?:int,per_page?:int,status?:string,veiculo?:string,search?:string} $opts
   */
  function pneus_fetch_list(PDO $pdo, int $empresa_id, array $opts = []): array
  {
    $page = isset($opts['page']) ? max(1, (int) $opts['page']) : 1;
    $per_page = isset($opts['per_page']) ? (int) $opts['per_page'] : 10;
    if (!in_array($per_page, [5, 10, 25, 50, 100], true)) {
      $per_page = 10;
    }
    $offset = ($page - 1) * $per_page;

    $status = isset($opts['status']) ? trim((string) $opts['status']) : '';
    $veiculo = isset($opts['veiculo']) ? trim((string) $opts['veiculo']) : '';
    $search = isset($opts['search']) ? trim((string) $opts['search']) : '';

    $where = ['p.empresa_id = ?'];
    $params = [$empresa_id];

    if ($status !== '') {
      $where[] = 'p.status_id = ?';
      $params[] = $status;
    }
    if ($search !== '') {
      $term = '%' . $search . '%';
      $where[] = '(p.numero_serie LIKE ? OR p.marca LIKE ? OR p.modelo LIKE ? OR p.dot LIKE ? OR p.medida LIKE ?)';
      $params[] = $term;
      $params[] = $term;
      $params[] = $term;
      $params[] = $term;
      $params[] = $term;
    }

    $where_sql = implode(' AND ', $where);
    $alloc = pneus_detect_allocation_tables($pdo);
    $joinParts = pneus_build_list_join_sql($alloc);
    $join = $joinParts['join'];
    $veiculo_placa_sql = $joinParts['veiculo_placa_sql'];
    $posicao_nome_sql = $joinParts['posicao_nome_sql'];

    if ($veiculo !== '') {
      $veiculo_cond = [];
      if ($alloc['has_inst']) {
        $veiculo_cond[] = 'ip.veiculo_id = ?';
      }
      if ($alloc['has_apf']) {
        $veiculo_cond[] = 'ev.veiculo_id = ?';
      }
      if ($alloc['has_eixo']) {
        $veiculo_cond[] = 'e.veiculo_id = ?';
      }
      if (count($veiculo_cond) > 0) {
        $where_sql .= ' AND (' . implode(' OR ', $veiculo_cond) . ')';
        for ($i = 0; $i < count($veiculo_cond); $i++) {
          $params[] = $veiculo;
        }
      }
    }

    $count_sql = "SELECT COUNT(DISTINCT p.id) as total FROM pneus p $join WHERE $where_sql";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = $total > 0 ? (int) ceil($total / $per_page) : 1;
    if ($page > $total_pages) {
      $page = $total_pages;
      $offset = ($page - 1) * $per_page;
    }

    $sql = "
            SELECT p.*,
                   s.nome as status_nome,
                   $veiculo_placa_sql as veiculo_placa,
                   $posicao_nome_sql as posicao_nome
            FROM pneus p
            LEFT JOIN status_pneus s ON s.id = p.status_id
            $join
            WHERE $where_sql
            ORDER BY p.id DESC
            LIMIT ? OFFSET ?
        ";
    $listParams = array_merge($params, [$per_page, $offset]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($listParams);
    $tires = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tires as &$row) {
      if (!array_key_exists('posicao_nome', $row) || $row['posicao_nome'] === null) {
        $row['posicao_nome'] = '-';
      }
    }
    unset($row);

    return [
      'success' => true,
      'tires' => $tires,
      'pagination' => [
        'total' => $total,
        'current_page' => $page,
        'per_page' => $per_page,
        'total_pages' => max(1, $total_pages),
      ],
    ];
  }
}

if (!function_exists('pneus_fetch_page_metrics')) {
  function pneus_fetch_page_metrics(PDO $pdo, int $empresa_id): array
  {
    $emUso = 0;
    $st = $pdo->prepare("
        SELECT COUNT(DISTINCT x.pneu_id) AS total FROM (
            SELECT ip.pneu_id FROM instalacoes_pneus ip INNER JOIN pneus p ON p.id = ip.pneu_id WHERE p.empresa_id = ? AND ip.data_remocao IS NULL
            UNION
            SELECT apf.pneu_id FROM alocacoes_pneus_flexiveis apf INNER JOIN pneus p ON p.id = apf.pneu_id WHERE p.empresa_id = ? AND apf.ativo = 1
            UNION
            SELECT pa.pneu_id FROM pneus_alocacao pa INNER JOIN pneus p ON p.id = pa.pneu_id WHERE p.empresa_id = ? AND pa.status = 'alocado'
            UNION
            SELECT ep.pneu_id FROM eixo_pneus ep INNER JOIN pneus p ON p.id = ep.pneu_id WHERE p.empresa_id = ? AND ep.status = 'alocado'
        ) x
    ");
    try {
      $st->execute([$empresa_id, $empresa_id, $empresa_id, $empresa_id]);
      $emUso = (int) ($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    } catch (Throwable $e) {
      $emUso = 0;
    }

    $st = $pdo->prepare('SELECT COUNT(*) AS total FROM pneus WHERE empresa_id = ?');
    $st->execute([$empresa_id]);
    $total_pneus = (int) $st->fetch(PDO::FETCH_ASSOC)['total'];

    $st = $pdo->prepare("
        SELECT COALESCE(AVG(LEAST(100, GREATEST(0, (sulco_inicial / 10.0) * 100))), 0) AS vida_media
        FROM pneus WHERE empresa_id = ? AND sulco_inicial IS NOT NULL
    ");
    $st->execute([$empresa_id]);
    $vida_media = (int) round((float) $st->fetch(PDO::FETCH_ASSOC)['vida_media']);

    $st = $pdo->prepare('
        SELECT COUNT(*) AS total FROM pneus
        WHERE empresa_id = ? AND sulco_inicial IS NOT NULL AND sulco_inicial <= 2
    ');
    $st->execute([$empresa_id]);
    $pneus_alerta = (int) $st->fetch(PDO::FETCH_ASSOC)['total'];

    if (function_exists('pneus_get_summary')) {
      $summary = pneus_get_summary($pdo, $empresa_id);
      $total_pneus = (int) ($summary['total_pneus'] ?? $total_pneus);
      $emUso = (int) ($summary['em_uso'] ?? $emUso);
      $pneus_alerta = (int) ($summary['alerta_sulco'] ?? $pneus_alerta);
    }

    return [
      'total_pneus' => $total_pneus,
      'total_em_uso' => $emUso,
      'vida_media' => $vida_media,
      'pneus_alerta' => $pneus_alerta,
    ];
  }
}

if (!function_exists('pneus_fmt_date_br')) {
  function pneus_fmt_date_br(?string $date): string
  {
    if (!$date || $date === '0000-00-00') {
      return '-';
    }
    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : '-';
  }
}

if (!function_exists('pneus_fmt_number_br')) {
  function pneus_fmt_number_br($number): string
  {
    if ($number === null || $number === '') {
      return '-';
    }
    return number_format((float) $number, 0, ',', '.');
  }
}

if (!function_exists('pneus_render_table_row')) {
  function pneus_render_table_row(array $tire): string
  {
    $id = (int) ($tire['id'] ?? 0);
    $serie = htmlspecialchars((string) ($tire['numero_serie'] ?? ''), ENT_QUOTES, 'UTF-8');
    $marcaModelo = htmlspecialchars(trim((string) ($tire['marca'] ?? '') . ' ' . (string) ($tire['modelo'] ?? '')), ENT_QUOTES, 'UTF-8');
    $dot = htmlspecialchars((string) ($tire['dot'] ?? ''), ENT_QUOTES, 'UTF-8');
    $placa = htmlspecialchars((string) ($tire['veiculo_placa'] ?? '-'), ENT_QUOTES, 'UTF-8');
    $pos = htmlspecialchars((string) ($tire['posicao_nome'] ?? '-'), ENT_QUOTES, 'UTF-8');
    $dataInst = htmlspecialchars(pneus_fmt_date_br($tire['data_instalacao'] ?? null), ENT_QUOTES, 'UTF-8');
    $km = htmlspecialchars(pneus_fmt_number_br($tire['km_instalacao'] ?? null), ENT_QUOTES, 'UTF-8');
    $statusId = (int) ($tire['status_id'] ?? 0);
    $statusNome = htmlspecialchars((string) ($tire['status_nome'] ?? ''), ENT_QUOTES, 'UTF-8');

    return '<tr>'
      . '<td>' . $serie . '</td>'
      . '<td>' . $marcaModelo . '</td>'
      . '<td>' . $dot . '</td>'
      . '<td>' . $placa . '</td>'
      . '<td>' . $pos . '</td>'
      . '<td>' . $dataInst . '</td>'
      . '<td>' . $km . '</td>'
      . '<td><span class="status-badge status-' . $statusId . '">' . $statusNome . '</span></td>'
      . '<td><div class="action-buttons">'
      . '<button class="btn-action view" onclick="showTireDetails(' . $id . ')" title="Detalhes"><i class="fas fa-eye"></i></button>'
      . '<button class="btn-action" onclick="PneuTimeline.openFichaModal(' . $id . ')" title="Ficha completa"><i class="fas fa-id-card"></i></button>'
      . '<button class="btn-action edit" onclick="showEditTireModal(' . $id . ')" title="Editar"><i class="fas fa-edit"></i></button>'
      . '<button class="btn-action delete" onclick="showDeleteConfirmation(' . $id . ')"><i class="fas fa-trash"></i></button>'
      . '</div></td>'
      . '</tr>';
  }
}
