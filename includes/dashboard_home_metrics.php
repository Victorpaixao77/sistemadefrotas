<?php
/**
 * Métricas do dashboard principal (index.php) com filtro de período e comparação.
 */

if (!function_exists('dashboard_home_normalize_period')) {

    function dashboard_home_normalize_period($p)
    {
        $p = strtolower((string) $p);
        $ok = ['all', 'month', 'quarter', 'year'];
        return in_array($p, $ok, true) ? $p : 'all';
    }

    /**
     * @return array{0:?string,1:?string,2:?string,3:?string,4:string} cur_start, cur_end, prev_start, prev_end, label
     */
    function dashboard_home_period_ranges($period)
    {
        if ($period === 'all') {
            return [null, null, null, null, 'Todo o período'];
        }
        $tzName = @date_default_timezone_get() ?: 'America/Sao_Paulo';
        $tz = new DateTimeZone($tzName);
        $today = new DateTime('today', $tz);

        if ($period === 'month') {
            $cur_s = (clone $today)->modify('first day of this month');
            $cur_e = (clone $today)->modify('last day of this month');
            $prev_e = (clone $cur_s)->modify('-1 day');
            $prev_s = (clone $prev_e)->modify('first day of this month');
            return [
                $cur_s->format('Y-m-d'),
                $cur_e->format('Y-m-d'),
                $prev_s->format('Y-m-d'),
                $prev_e->format('Y-m-d'),
                $cur_s->format('m/Y'),
            ];
        }

        if ($period === 'year') {
            $y = (int) $today->format('Y');
            $cur_s = new DateTime($y . '-01-01', $tz);
            $cur_e = new DateTime($y . '-12-31', $tz);
            $prev_s = new DateTime(($y - 1) . '-01-01', $tz);
            $prev_e = new DateTime(($y - 1) . '-12-31', $tz);
            return [
                $cur_s->format('Y-m-d'),
                $cur_e->format('Y-m-d'),
                $prev_s->format('Y-m-d'),
                $prev_e->format('Y-m-d'),
                (string) $y,
            ];
        }

        // trimestre
        $y = (int) $today->format('Y');
        $m = (int) $today->format('n');
        $q = (int) ceil($m / 3);
        $startMonth = ($q - 1) * 3 + 1;
        $cur_s = DateTime::createFromFormat('Y-n-j', $y . '-' . $startMonth . '-1', $tz);
        $cur_e = clone $cur_s;
        $cur_e->modify('+2 months')->modify('last day of this month');
        $prev_e = clone $cur_s;
        $prev_e->modify('-1 day');
        $py = (int) $prev_e->format('Y');
        $pm = (int) $prev_e->format('n');
        $pq = (int) ceil($pm / 3);
        $psm = ($pq - 1) * 3 + 1;
        $prev_s = DateTime::createFromFormat('Y-n-j', $py . '-' . $psm . '-1', $tz);
        $prev_end = clone $prev_s;
        $prev_end->modify('+2 months')->modify('last day of this month');

        return [
            $cur_s->format('Y-m-d'),
            $cur_e->format('Y-m-d'),
            $prev_s->format('Y-m-d'),
            $prev_end->format('Y-m-d'),
            'T' . $q . '/' . $y,
        ];
    }

    function dashboard_home_pct_change($cur, $prev)
    {
        if ($prev === null) {
            return null;
        }
        $prev = (float) $prev;
        $cur = (float) $cur;
        if (abs($prev) < 1e-6) {
            if (abs($cur) < 1e-6) {
                return 0.0;
            }
            return $cur > 0 ? 100.0 : -100.0;
        }
        return (($cur - $prev) / abs($prev)) * 100.0;
    }

    function dashboard_home_format_delta($pct)
    {
        if ($pct === null) {
            return '';
        }
        $arrow = $pct >= 0 ? '▲' : '▼';
        return $arrow . ' ' . number_format(abs($pct), 1, ',', '.') . '% vs período anterior';
    }

    /**
     * Métricas agregadas no intervalo [d1,d2] (inclusive) ou todo o histórico se d1/d2 nulos.
     *
     * @return array<string, float|int>
     */
    function dashboard_home_metrics_for_range(PDO $conn, int $empresa_id, ?string $d1, ?string $d2)
    {
        $filter = ($d1 !== null && $d2 !== null);
        $e = $empresa_id;

        $dateRotas = 'DATE(COALESCE(r.data_saida, r.data_rota))';

        // Rotas
        $sqlR = "SELECT 
                SUM(CASE WHEN status = 'aprovado' THEN 1 ELSE 0 END) AS concluidas,
                COUNT(*) AS total_rotas 
            FROM rotas r
            WHERE r.empresa_id = :e";
        if ($filter) {
            $sqlR .= " AND {$dateRotas} BETWEEN :d1 AND :d2";
        }
        $st = $conn->prepare($sqlR);
        $st->bindValue(':e', $e, PDO::PARAM_INT);
        if ($filter) {
            $st->bindValue(':d1', $d1);
            $st->bindValue(':d2', $d2);
        }
        $st->execute();
        $rowR = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $total_rotas_concluidas = (int) ($rowR['concluidas'] ?? 0);
        $total_rotas = (int) ($rowR['total_rotas'] ?? 0);

        // Abastecimentos
        $sqlA = "SELECT COUNT(*) AS total, COALESCE(SUM(a.valor_total + COALESCE(a.valor_total_arla, 0)), 0) AS valor_total
            FROM abastecimentos a WHERE a.empresa_id = :e AND a.status = 'aprovado'";
        if ($filter) {
            $sqlA .= ' AND DATE(a.data_abastecimento) BETWEEN :d1 AND :d2';
        }
        $st = $conn->prepare($sqlA);
        $st->bindValue(':e', $e, PDO::PARAM_INT);
        if ($filter) {
            $st->bindValue(':d1', $d1);
            $st->bindValue(':d2', $d2);
        }
        $st->execute();
        $rowA = $st->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'valor_total' => 0];
        $total_abastecimentos = (int) $rowA['total'];
        $valor_total_abastecimentos = (float) $rowA['valor_total'];

        // Despesas viagem (via data da rota)
        $sqlDv = "SELECT COALESCE(SUM(dv.total_despviagem), 0) FROM despesas_viagem dv
            INNER JOIN rotas r ON r.id = dv.rota_id AND r.empresa_id = dv.empresa_id
            WHERE dv.empresa_id = :e";
        if ($filter) {
            $sqlDv .= " AND {$dateRotas} BETWEEN :d1 AND :d2";
        }
        $st = $conn->prepare($sqlDv);
        $st->bindValue(':e', $e, PDO::PARAM_INT);
        if ($filter) {
            $st->bindValue(':d1', $d1);
            $st->bindValue(':d2', $d2);
        }
        $st->execute();
        $total_desp_viagem = (float) $st->fetchColumn();

        // Despesas fixas pagas
        $sqlDf = 'SELECT COALESCE(SUM(valor), 0) FROM despesas_fixas WHERE empresa_id = :e AND data_pagamento IS NOT NULL';
        if ($filter) {
            $sqlDf .= ' AND DATE(data_pagamento) BETWEEN :d1 AND :d2';
        }
        $st = $conn->prepare($sqlDf);
        $st->bindValue(':e', $e, PDO::PARAM_INT);
        if ($filter) {
            $st->bindValue(':d1', $d1);
            $st->bindValue(':d2', $d2);
        }
        $st->execute();
        $total_desp_fixas = (float) $st->fetchColumn();

        // Contas pagas
        $sqlCp = 'SELECT COALESCE(SUM(valor), 0) FROM contas_pagar WHERE empresa_id = :e AND status_id = 2';
        if ($filter) {
            $sqlCp .= ' AND data_pagamento IS NOT NULL AND DATE(data_pagamento) BETWEEN :d1 AND :d2';
        }
        $st = $conn->prepare($sqlCp);
        $st->bindValue(':e', $e, PDO::PARAM_INT);
        if ($filter) {
            $st->bindValue(':d1', $d1);
            $st->bindValue(':d2', $d2);
        }
        $st->execute();
        $total_contas_pagas = (float) $st->fetchColumn();

        // Manutenções veículos
        $sqlM = 'SELECT COALESCE(SUM(valor), 0) FROM manutencoes WHERE empresa_id = :e';
        if ($filter) {
            $sqlM .= ' AND DATE(data_manutencao) BETWEEN :d1 AND :d2';
        }
        $st = $conn->prepare($sqlM);
        $st->bindValue(':e', $e, PDO::PARAM_INT);
        if ($filter) {
            $st->bindValue(':d1', $d1);
            $st->bindValue(':d2', $d2);
        }
        $st->execute();
        $total_manutencoes = (float) $st->fetchColumn();

        // Manutenções pneus
        $sqlP = 'SELECT COALESCE(SUM(custo), 0) FROM pneu_manutencao WHERE empresa_id = :e';
        if ($filter) {
            $sqlP .= ' AND DATE(data_manutencao) BETWEEN :d1 AND :d2';
        }
        $st = $conn->prepare($sqlP);
        $st->bindValue(':e', $e, PDO::PARAM_INT);
        if ($filter) {
            $st->bindValue(':d1', $d1);
            $st->bindValue(':d2', $d2);
        }
        $st->execute();
        $total_pneu_manutencao = (float) $st->fetchColumn();

        // Parcelas financiamento pagas
        $sqlPf = "SELECT COALESCE(SUM(pf.valor), 0) FROM parcelas_financiamento pf
            INNER JOIN financiamentos f ON f.id = pf.financiamento_id
            WHERE f.empresa_id = :e AND pf.status_id = 2";
        if ($filter) {
            $sqlPf .= ' AND pf.data_pagamento IS NOT NULL AND DATE(pf.data_pagamento) BETWEEN :d1 AND :d2';
        }
        $st = $conn->prepare($sqlPf);
        $st->bindValue(':e', $e, PDO::PARAM_INT);
        if ($filter) {
            $st->bindValue(':d1', $d1);
            $st->bindValue(':d2', $d2);
        }
        $st->execute();
        $total_parcelas_financiamento = (float) $st->fetchColumn();

        // Fretes e comissões (rotas aprovadas)
        $sqlF = "SELECT COALESCE(SUM(frete), 0) AS frete, COALESCE(SUM(comissao), 0) AS comissao FROM rotas r
            WHERE r.empresa_id = :e AND r.status = 'aprovado'";
        if ($filter) {
            $sqlF .= " AND {$dateRotas} BETWEEN :d1 AND :d2";
        }
        $st = $conn->prepare($sqlF);
        $st->bindValue(':e', $e, PDO::PARAM_INT);
        if ($filter) {
            $st->bindValue(':d1', $d1);
            $st->bindValue(':d2', $d2);
        }
        $st->execute();
        $rowF = $st->fetch(PDO::FETCH_ASSOC) ?: ['frete' => 0, 'comissao' => 0];
        $total_fretes = (float) $rowF['frete'];
        $total_comissoes = (float) $rowF['comissao'];

        $lucro_liquido = $total_fretes
            - $total_comissoes
            - $total_desp_viagem
            - $valor_total_abastecimentos
            - $total_desp_fixas
            - $total_parcelas_financiamento
            - $total_contas_pagas
            - $total_manutencoes
            - $total_pneu_manutencao;

        return [
            'total_rotas_concluidas' => $total_rotas_concluidas,
            'total_rotas' => $total_rotas,
            'total_abastecimentos' => $total_abastecimentos,
            'valor_total_abastecimentos' => $valor_total_abastecimentos,
            'total_desp_viagem' => $total_desp_viagem,
            'total_desp_fixas' => $total_desp_fixas,
            'total_contas_pagas' => $total_contas_pagas,
            'total_manutencoes' => $total_manutencoes,
            'total_pneu_manutencao' => $total_pneu_manutencao,
            'total_parcelas_financiamento' => $total_parcelas_financiamento,
            'total_fretes' => $total_fretes,
            'total_comissoes' => $total_comissoes,
            'lucro_liquido' => $lucro_liquido,
        ];
    }

    function dashboard_home_load_extras(PDO $conn, int $empresa_id)
    {
        $rotas_pendentes_count = 0;
        $contas_vencidas_count = 0;
        $contas_vencidas_valor = 0.0;
        $proximas_rotas = [];

        try {
            $st = $conn->prepare("SELECT COUNT(*) FROM rotas WHERE empresa_id = :e AND status = 'pendente'");
            $st->execute([':e' => $empresa_id]);
            $rotas_pendentes_count = (int) $st->fetchColumn();

            $st = $conn->prepare("SELECT COUNT(*) AS c, COALESCE(SUM(valor), 0) AS v FROM contas_pagar 
                WHERE empresa_id = :e AND status_id = 1 AND data_vencimento < CURDATE()");
            $st->execute([':e' => $empresa_id]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: ['c' => 0, 'v' => 0];
            $contas_vencidas_count = (int) $row['c'];
            $contas_vencidas_valor = (float) $row['v'];

            $sql = "SELECT r.id, r.data_rota, r.data_saida, r.status, m.nome AS motorista, v.placa,
                    co.nome AS cidade_origem, cd.nome AS cidade_destino, r.estado_origem, r.estado_destino
                FROM rotas r
                INNER JOIN motoristas m ON r.motorista_id = m.id
                INNER JOIN veiculos v ON r.veiculo_id = v.id
                LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                WHERE r.empresa_id = :e
                AND DATE(COALESCE(r.data_saida, r.data_rota)) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                ORDER BY COALESCE(r.data_saida, r.data_rota) ASC
                LIMIT 10";
            $st = $conn->prepare($sql);
            $st->execute([':e' => $empresa_id]);
            $proximas_rotas = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            if (function_exists('sf_log_debug')) {
                sf_log_debug('dashboard_home_load_extras: ' . $e->getMessage());
            }
        }

        $gps_veiculos_sinal_recente = 0;
        $gps_pontos_hoje = 0;
        try {
            $stG = $conn->prepare('
                SELECT COUNT(*) FROM gps_ultima_posicao g
                WHERE g.empresa_id = :e
                  AND COALESCE(g.ultima_atualizacao, g.data_hora) >= DATE_SUB(NOW(), INTERVAL 45 MINUTE)
            ');
            $stG->execute([':e' => $empresa_id]);
            $gps_veiculos_sinal_recente = (int) $stG->fetchColumn();
        } catch (Exception $e) {
            try {
                $stG2 = $conn->prepare('
                    SELECT COUNT(*) FROM gps_ultima_posicao g
                    WHERE g.empresa_id = :e
                      AND g.data_hora >= DATE_SUB(NOW(), INTERVAL 45 MINUTE)
                ');
                $stG2->execute([':e' => $empresa_id]);
                $gps_veiculos_sinal_recente = (int) $stG2->fetchColumn();
            } catch (Exception $e2) {
                $gps_veiculos_sinal_recente = 0;
            }
        }
        try {
            $stP = $conn->prepare('
                SELECT COUNT(*) FROM gps_logs
                WHERE empresa_id = :e AND DATE(data_hora) = CURDATE()
            ');
            $stP->execute([':e' => $empresa_id]);
            $gps_pontos_hoje = (int) $stP->fetchColumn();
        } catch (Exception $e) {
            $gps_pontos_hoje = 0;
        }

        return [
            'rotas_pendentes_count' => $rotas_pendentes_count,
            'contas_vencidas_count' => $contas_vencidas_count,
            'contas_vencidas_valor' => $contas_vencidas_valor,
            'proximas_rotas' => $proximas_rotas,
            'gps_veiculos_sinal_recente' => $gps_veiculos_sinal_recente,
            'gps_pontos_hoje' => $gps_pontos_hoje,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    function dashboard_home_load_all(PDO $conn, int $empresa_id, string $period_key, $can_view_financial_data)
    {
        $period = dashboard_home_normalize_period($period_key);
        $finFlag = $can_view_financial_data ? '1' : '0';
        $cacheKey = 'dh_' . $empresa_id . '_' . $period . '_' . $finFlag;
        $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sf_dash_' . md5($cacheKey) . '.json';
        $cacheTtl = 90;
        if (is_readable($cacheFile) && (time() - (int) @filemtime($cacheFile)) < $cacheTtl) {
            $raw = @file_get_contents($cacheFile);
            if ($raw !== false && $raw !== '') {
                $cached = json_decode($raw, true);
                if (is_array($cached) && isset($cached['home_period_key'])) {
                    return $cached;
                }
            }
        }

        [$d1, $d2, $p1, $p2, $home_period_label] = dashboard_home_period_ranges($period);
        $home_compare_available = ($period !== 'all');

        $defaults = [
            'total_veiculos' => 0,
            'total_motoristas' => 0,
            'total_rotas_concluidas' => 0,
            'total_rotas' => 0,
            'total_abastecimentos' => 0,
            'valor_total_abastecimentos' => 0.0,
            'total_desp_viagem' => 0.0,
            'total_desp_fixas' => 0.0,
            'total_contas_pagas' => 0.0,
            'total_manutencoes' => 0.0,
            'total_pneu_manutencao' => 0.0,
            'total_parcelas_financiamento' => 0.0,
            'total_fretes' => 0.0,
            'total_comissoes' => 0.0,
            'lucro_liquido' => 0.0,
            'home_period_key' => $period,
            'home_period_label' => $home_period_label,
            'home_compare_available' => $home_compare_available,
            'delta_rotas_concl_pct' => null,
            'delta_fretes_pct' => null,
            'delta_lucro_pct' => null,
            'delta_abast_valor_pct' => null,
            'rotas_pendentes_count' => 0,
            'contas_vencidas_count' => 0,
            'contas_vencidas_valor' => 0.0,
            'proximas_rotas' => [],
            'gps_veiculos_sinal_recente' => 0,
            'gps_pontos_hoje' => 0,
        ];

        try {
            // PDO MySQL: o mesmo nome de placeholder não pode repetir na mesma SQL.
            $st = $conn->prepare('SELECT 
                (SELECT COUNT(*) FROM veiculos WHERE empresa_id = :e1) AS tv,
                (SELECT COUNT(*) FROM motoristas WHERE empresa_id = :e2) AS tm');
            $st->bindValue(':e1', $empresa_id, PDO::PARAM_INT);
            $st->bindValue(':e2', $empresa_id, PDO::PARAM_INT);
            $st->execute();
            $fr = $st->fetch(PDO::FETCH_ASSOC) ?: ['tv' => 0, 'tm' => 0];
            $defaults['total_veiculos'] = (int) $fr['tv'];
            $defaults['total_motoristas'] = (int) $fr['tm'];

            $cur = dashboard_home_metrics_for_range($conn, $empresa_id, $d1, $d2);
            $defaults = array_merge($defaults, $cur);

            if ($home_compare_available) {
                $prev = dashboard_home_metrics_for_range($conn, $empresa_id, $p1, $p2);
                $defaults['delta_rotas_concl_pct'] = dashboard_home_pct_change(
                    $cur['total_rotas_concluidas'],
                    $prev['total_rotas_concluidas']
                );
                $defaults['delta_fretes_pct'] = dashboard_home_pct_change($cur['total_fretes'], $prev['total_fretes']);
                $defaults['delta_lucro_pct'] = dashboard_home_pct_change($cur['lucro_liquido'], $prev['lucro_liquido']);
                $defaults['delta_abast_valor_pct'] = dashboard_home_pct_change(
                    $cur['valor_total_abastecimentos'],
                    $prev['valor_total_abastecimentos']
                );
            }

            $extras = dashboard_home_load_extras($conn, $empresa_id);
            $defaults = array_merge($defaults, $extras);
        } catch (Exception $e) {
            if (function_exists('sf_log_debug')) {
                sf_log_debug('dashboard_home_load_all: ' . $e->getMessage());
            }
        }

        if (!empty($defaults)) {
            @file_put_contents($cacheFile, json_encode($defaults, JSON_UNESCAPED_UNICODE));
        }

        return $defaults;
    }
}
