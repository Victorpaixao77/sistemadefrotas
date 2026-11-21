<?php
/**
 * Métricas do dashboard principal (index.php) com filtro de período e comparação.
 */

if (!function_exists('dashboard_home_normalize_period')) {

    function dashboard_home_normalize_period($p)
    {
        $p = strtolower((string) $p);
        $ok = ['all', 'month', 'quarter', 'year'];
        return in_array($p, $ok, true) ? $p : 'year';
    }

    function dashboard_home_cache_file(int $empresa_id, string $period, string $finFlag): string
    {
        $cacheKey = 'dh_' . $empresa_id . '_' . $period . '_' . $finFlag;
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sf_dash_' . md5($cacheKey) . '.json';
    }

    function dashboard_home_cache_key(int $empresa_id, string $period, string $finFlag): string
    {
        return 'dash_home_v3:' . $empresa_id . ':' . $period . ':' . $finFlag;
    }

    function dashboard_home_cache_clear_empresa(int $empresa_id): void
    {
        require_once __DIR__ . '/sf_cache.php';
        foreach (['all', 'month', 'quarter', 'year'] as $period) {
            foreach (['0', '1'] as $finFlag) {
                $file = dashboard_home_cache_file($empresa_id, $period, $finFlag);
                if (is_file($file)) {
                    @unlink($file);
                }
                sf_cache_forget(dashboard_home_cache_key($empresa_id, $period, $finFlag));
            }
        }
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
        $dr = 'DATE(COALESCE(r.data_saida, r.data_rota))';

        $wfR = $filter ? " AND {$dr} BETWEEN :d1_r AND :d2_r" : '';
        $wfA = $filter ? ' AND DATE(a.data_abastecimento) BETWEEN :d1_a AND :d2_a' : '';
        $wfDv = $filter ? " AND {$dr} BETWEEN :d1_dv AND :d2_dv" : '';
        $wfDf = $filter ? ' AND DATE(df.data_pagamento) BETWEEN :d1_df AND :d2_df' : '';
        $wfCp = $filter ? ' AND cp.data_pagamento IS NOT NULL AND DATE(cp.data_pagamento) BETWEEN :d1_cp AND :d2_cp' : '';
        $wfM = $filter ? ' AND DATE(m.data_manutencao) BETWEEN :d1_m AND :d2_m' : '';
        $wfP = $filter ? ' AND DATE(p.data_manutencao) BETWEEN :d1_p AND :d2_p' : '';
        $wfPf = $filter ? ' AND pf.data_pagamento IS NOT NULL AND DATE(pf.data_pagamento) BETWEEN :d1_pf AND :d2_pf' : '';

        $sql = "SELECT
            r.concluidas AS total_rotas_concluidas,
            r.total_rotas,
            r.frete AS total_fretes,
            r.comissao AS total_comissoes,
            a.cnt AS total_abastecimentos,
            a.val AS valor_total_abastecimentos,
            dv.v AS total_desp_viagem,
            df.v AS total_desp_fixas,
            cp.v AS total_contas_pagas,
            m.v AS total_manutencoes,
            p.v AS total_pneu_manutencao,
            pf.v AS total_parcelas_financiamento
        FROM
            (SELECT
                COALESCE(SUM(CASE WHEN r.status = 'aprovado' THEN 1 ELSE 0 END), 0) AS concluidas,
                COUNT(*) AS total_rotas,
                COALESCE(SUM(CASE WHEN r.status = 'aprovado' THEN r.frete ELSE 0 END), 0) AS frete,
                COALESCE(SUM(CASE WHEN r.status = 'aprovado' THEN r.comissao ELSE 0 END), 0) AS comissao
             FROM rotas r WHERE r.empresa_id = :e_r{$wfR}) r
            CROSS JOIN
            (SELECT COUNT(*) AS cnt,
                COALESCE(SUM(a.valor_total + COALESCE(a.valor_total_arla, 0)), 0) AS val
             FROM abastecimentos a WHERE a.empresa_id = :e_a AND a.status = 'aprovado'{$wfA}) a
            CROSS JOIN
            (SELECT COALESCE(SUM(dv.total_despviagem), 0) AS v FROM despesas_viagem dv
             INNER JOIN rotas r ON r.id = dv.rota_id AND r.empresa_id = dv.empresa_id
             WHERE dv.empresa_id = :e_dv{$wfDv}) dv
            CROSS JOIN
            (SELECT COALESCE(SUM(df.valor), 0) AS v FROM despesas_fixas df
             WHERE df.empresa_id = :e_df AND df.data_pagamento IS NOT NULL{$wfDf}) df
            CROSS JOIN
            (SELECT COALESCE(SUM(cp.valor), 0) AS v FROM contas_pagar cp
             WHERE cp.empresa_id = :e_cp AND cp.status_id = 2{$wfCp}) cp
            CROSS JOIN
            (SELECT COALESCE(SUM(m.valor), 0) AS v FROM manutencoes m
             WHERE m.empresa_id = :e_m{$wfM}) m
            CROSS JOIN
            (SELECT COALESCE(SUM(p.custo), 0) AS v FROM pneu_manutencao p
             WHERE p.empresa_id = :e_p{$wfP}) p
            CROSS JOIN
            (SELECT COALESCE(SUM(pf.valor), 0) AS v FROM parcelas_financiamento pf
             INNER JOIN financiamentos f ON f.id = pf.financiamento_id
             WHERE f.empresa_id = :e_pf AND pf.status_id = 2{$wfPf}) pf";

        $st = $conn->prepare($sql);
        $st->bindValue(':e_r', $e, PDO::PARAM_INT);
        $st->bindValue(':e_a', $e, PDO::PARAM_INT);
        $st->bindValue(':e_dv', $e, PDO::PARAM_INT);
        $st->bindValue(':e_df', $e, PDO::PARAM_INT);
        $st->bindValue(':e_cp', $e, PDO::PARAM_INT);
        $st->bindValue(':e_m', $e, PDO::PARAM_INT);
        $st->bindValue(':e_p', $e, PDO::PARAM_INT);
        $st->bindValue(':e_pf', $e, PDO::PARAM_INT);
        if ($filter) {
            foreach (['r', 'a', 'dv', 'df', 'cp', 'm', 'p', 'pf'] as $pfx) {
                $st->bindValue(':d1_' . $pfx, $d1);
                $st->bindValue(':d2_' . $pfx, $d2);
            }
        }
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $total_rotas_concluidas = (int) ($row['total_rotas_concluidas'] ?? 0);
        $total_rotas = (int) ($row['total_rotas'] ?? 0);
        $total_abastecimentos = (int) ($row['total_abastecimentos'] ?? 0);
        $valor_total_abastecimentos = (float) ($row['valor_total_abastecimentos'] ?? 0);
        $total_desp_viagem = (float) ($row['total_desp_viagem'] ?? 0);
        $total_desp_fixas = (float) ($row['total_desp_fixas'] ?? 0);
        $total_contas_pagas = (float) ($row['total_contas_pagas'] ?? 0);
        $total_manutencoes = (float) ($row['total_manutencoes'] ?? 0);
        $total_pneu_manutencao = (float) ($row['total_pneu_manutencao'] ?? 0);
        $total_parcelas_financiamento = (float) ($row['total_parcelas_financiamento'] ?? 0);
        $total_fretes = (float) ($row['total_fretes'] ?? 0);
        $total_comissoes = (float) ($row['total_comissoes'] ?? 0);

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

    function dashboard_home_load_cnh_counts(PDO $conn, int $empresa_id): array
    {
        $cnh_vencidas = 0;
        $cnh_vencendo = 0;
        try {
            $st = $conn->prepare("SELECT
                    SUM(CASE WHEN data_validade_cnh IS NOT NULL AND data_validade_cnh <> '0000-00-00'
                             AND data_validade_cnh < CURRENT_DATE THEN 1 ELSE 0 END) AS vencidas,
                    SUM(CASE WHEN data_validade_cnh IS NOT NULL AND data_validade_cnh <> '0000-00-00'
                             AND data_validade_cnh BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS vencendo
                FROM motoristas WHERE empresa_id = :e");
            $st->execute([':e' => $empresa_id]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $cnh_vencidas = (int) ($row['vencidas'] ?? 0);
            $cnh_vencendo = (int) ($row['vencendo'] ?? 0);
        } catch (Exception $e) {
            if (function_exists('sf_log_debug')) {
                sf_log_debug('dashboard_home_load_cnh: ' . $e->getMessage());
            }
        }
        return ['cnh_vencidas' => $cnh_vencidas, 'cnh_vencendo' => $cnh_vencendo];
    }

    /**
     * @return list<array{icon:string,color:string,title:string,meta:string,value:string}>
     */
    function dashboard_home_load_atividades_recentes(PDO $conn, int $empresa_id): array
    {
        $atividades = [];
        try {
            $sql_rotas = "SELECT r.estado_origem, r.estado_destino, r.data_saida, r.data_rota, m.nome AS motorista,
                    co.nome AS cidade_origem, cd.nome AS cidade_destino
                FROM rotas r
                JOIN motoristas m ON r.motorista_id = m.id
                LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                WHERE r.empresa_id = :e
                ORDER BY COALESCE(r.data_saida, r.data_rota) DESC LIMIT 2";
            $st = $conn->prepare($sql_rotas);
            $st->execute([':e' => $empresa_id]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $rota) {
                $origem = $rota['cidade_origem'] ? $rota['cidade_origem'] . '/' . $rota['estado_origem'] : $rota['estado_origem'];
                $destino = $rota['cidade_destino'] ? $rota['cidade_destino'] . '/' . $rota['estado_destino'] : $rota['estado_destino'];
                $dataRef = $rota['data_saida'] ?: $rota['data_rota'];
                $atividades[] = [
                    'icon' => 'route',
                    'color' => '#3b82f6',
                    'title' => 'Nova rota — ' . $origem . ' → ' . $destino,
                    'meta' => ($dataRef ? date('d/m/Y H:i', strtotime($dataRef)) : '—') . ' · ' . $rota['motorista'],
                    'value' => '',
                ];
            }
            $sql_ab = "SELECT a.data_abastecimento, a.litros, a.valor_total, v.placa
                FROM abastecimentos a JOIN veiculos v ON a.veiculo_id = v.id
                WHERE a.empresa_id = :e ORDER BY a.data_abastecimento DESC LIMIT 2";
            $st = $conn->prepare($sql_ab);
            $st->execute([':e' => $empresa_id]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $ab) {
                $atividades[] = [
                    'icon' => 'gas-pump',
                    'color' => '#06b6d4',
                    'title' => 'Abastecimento — ' . $ab['placa'],
                    'meta' => date('d/m/Y', strtotime($ab['data_abastecimento'])) . ' · ' . $ab['litros'] . 'L',
                    'value' => 'R$ ' . number_format((float) $ab['valor_total'], 2, ',', '.'),
                ];
            }
        } catch (Exception $e) {
            if (function_exists('sf_log_debug')) {
                sf_log_debug('dashboard_home_load_atividades: ' . $e->getMessage());
            }
        }
        return $atividades;
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
        $despesas_pendentes_count = 0;
        $checklists_problema_count = 0;
        $notif_nao_lidas_operacional = 0;
        $notif_nao_lidas_ia = 0;
        $veiculos_ativos = 0;
        $rotas_hoje = 0;
        $manutencoes_30d = 0;

        try {
            $stVa = $conn->prepare('SELECT COUNT(*) FROM veiculos WHERE empresa_id = :e AND status_id = 1');
            $stVa->execute([':e' => $empresa_id]);
            $veiculos_ativos = (int) $stVa->fetchColumn();
        } catch (Exception $e) {
            $veiculos_ativos = 0;
        }

        try {
            $stRh = $conn->prepare("SELECT COUNT(*) FROM rotas WHERE empresa_id = :e AND DATE(COALESCE(data_saida, data_rota)) = CURDATE()");
            $stRh->execute([':e' => $empresa_id]);
            $rotas_hoje = (int) $stRh->fetchColumn();
        } catch (Exception $e) {
            $rotas_hoje = 0;
        }

        try {
            $stM = $conn->prepare('SELECT COUNT(*) FROM manutencoes WHERE empresa_id = :e AND data_manutencao >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)');
            $stM->execute([':e' => $empresa_id]);
            $manutencoes_30d = (int) $stM->fetchColumn();
        } catch (Exception $e) {
            $manutencoes_30d = 0;
        }

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

        try {
            $st = $conn->prepare("SELECT COUNT(*) FROM despesas_viagem WHERE empresa_id = :e AND status = 'pendente'");
            $st->execute([':e' => $empresa_id]);
            $despesas_pendentes_count = (int) $st->fetchColumn();
        } catch (Exception $e) {
            $despesas_pendentes_count = 0;
        }

        try {
            $st = $conn->prepare("
                SELECT COUNT(*) FROM checklist_viagem c
                WHERE c.empresa_id = :e
                AND c.data_checklist >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                AND (c.freios = 0 OR c.pneus = 0 OR c.extintor = 0 OR c.cnh = 0 OR c.luzes = 0
                     OR c.doc_veiculo = 0 OR c.motorista_sobrio = 0 OR c.vazamentos = 0 OR c.triangulo = 0)
            ");
            $st->execute([':e' => $empresa_id]);
            $checklists_problema_count = (int) $st->fetchColumn();
        } catch (Exception $e) {
            $checklists_problema_count = 0;
        }

        if (!function_exists('sf_notif_contar_nao_lidas_usuario')) {
            require_once __DIR__ . '/notificacoes_helpers.php';
        }
        $uid = function_exists('sf_notif_usuario_id') ? sf_notif_usuario_id() : (int) ($_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? 0);
        if ($uid > 0) {
            try {
                $notif_nao_lidas_operacional = sf_notif_contar_nao_lidas_usuario($conn, $empresa_id, $uid, 'operacional');
                $notif_nao_lidas_ia = sf_notif_contar_nao_lidas_usuario($conn, $empresa_id, $uid, 'ia');
            } catch (Exception $e) {
                $notif_nao_lidas_operacional = 0;
                $notif_nao_lidas_ia = 0;
            }
        }

        $cnh = dashboard_home_load_cnh_counts($conn, $empresa_id);

        return [
            'rotas_pendentes_count' => $rotas_pendentes_count,
            'contas_vencidas_count' => $contas_vencidas_count,
            'contas_vencidas_valor' => $contas_vencidas_valor,
            'proximas_rotas' => $proximas_rotas,
            'gps_veiculos_sinal_recente' => $gps_veiculos_sinal_recente,
            'gps_pontos_hoje' => $gps_pontos_hoje,
            'despesas_pendentes_count' => $despesas_pendentes_count,
            'checklists_problema_count' => $checklists_problema_count,
            'notif_nao_lidas_operacional' => $notif_nao_lidas_operacional,
            'notif_nao_lidas_ia' => $notif_nao_lidas_ia,
            'veiculos_ativos' => $veiculos_ativos,
            'rotas_hoje' => $rotas_hoje,
            'manutencoes_30d' => $manutencoes_30d,
            'cnh_vencidas' => $cnh['cnh_vencidas'],
            'cnh_vencendo' => $cnh['cnh_vencendo'],
            'spark_rotas' => dashboard_home_spark_daily($conn, $empresa_id, "
                SELECT DATE(COALESCE(data_saida, data_rota)) AS d, COUNT(*) AS v
                FROM rotas WHERE empresa_id = :e
                  AND DATE(COALESCE(data_saida, data_rota)) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                GROUP BY DATE(COALESCE(data_saida, data_rota))
                ORDER BY d ASC
            "),
            'spark_abastecimentos' => dashboard_home_spark_daily($conn, $empresa_id, "
                SELECT DATE(data_abastecimento) AS d, COUNT(*) AS v
                FROM abastecimentos WHERE empresa_id = :e
                  AND data_abastecimento >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                GROUP BY DATE(data_abastecimento)
                ORDER BY d ASC
            "),
            'spark_manutencoes' => dashboard_home_spark_daily($conn, $empresa_id, "
                SELECT DATE(data_manutencao) AS d, COUNT(*) AS v
                FROM manutencoes WHERE empresa_id = :e
                  AND data_manutencao >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                GROUP BY DATE(data_manutencao)
                ORDER BY d ASC
            "),
            'atividades_recentes' => dashboard_home_load_atividades_recentes($conn, $empresa_id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    function dashboard_home_load_all(PDO $conn, int $empresa_id, string $period_key, $can_view_financial_data)
    {
        $period = dashboard_home_normalize_period($period_key);
        $finFlag = $can_view_financial_data ? '1' : '0';
        $cacheFile = dashboard_home_cache_file($empresa_id, $period, $finFlag);
        $cacheKey = dashboard_home_cache_key($empresa_id, $period, $finFlag);
        $cacheTtl = 120;
        $skipCache = !empty($_GET['refresh']) || !empty($GLOBALS['sf_dashboard_skip_cache']);

        if (!$skipCache) {
            require_once __DIR__ . '/sf_cache.php';
            $apcuHit = sf_cache_get($cacheKey);
            if (is_array($apcuHit) && isset($apcuHit['home_period_key'])) {
                return $apcuHit;
            }
            if (is_readable($cacheFile) && (time() - (int) @filemtime($cacheFile)) < $cacheTtl) {
                $raw = @file_get_contents($cacheFile);
                if ($raw !== false && $raw !== '') {
                    $cached = json_decode($raw, true);
                    if (is_array($cached) && isset($cached['home_period_key'])) {
                        sf_cache_set($cacheKey, $cached, $cacheTtl);
                        return $cached;
                    }
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
            'despesas_pendentes_count' => 0,
            'checklists_problema_count' => 0,
            'notif_nao_lidas_operacional' => 0,
            'notif_nao_lidas_ia' => 0,
            'veiculos_ativos' => 0,
            'rotas_hoje' => 0,
            'manutencoes_30d' => 0,
            'cnh_vencidas' => 0,
            'cnh_vencendo' => 0,
            'spark_rotas' => [],
            'spark_abastecimentos' => [],
            'spark_manutencoes' => [],
            'spark_lucro' => [],
            'atividades_recentes' => [],
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

            if ($can_view_financial_data) {
                $defaults['spark_lucro'] = dashboard_home_spark_daily($conn, $empresa_id, "
                    SELECT DATE(COALESCE(data_rota, data_saida)) AS d,
                           COALESCE(SUM(frete - comissao), 0) AS v
                    FROM rotas WHERE empresa_id = :e AND status = 'aprovado'
                      AND DATE(COALESCE(data_rota, data_saida)) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                    GROUP BY DATE(COALESCE(data_rota, data_saida))
                    ORDER BY d ASC
                ");
            }
        } catch (Exception $e) {
            if (function_exists('sf_log_debug')) {
                sf_log_debug('dashboard_home_load_all: ' . $e->getMessage());
            }
        }

        if (!empty($defaults) && !$skipCache) {
            require_once __DIR__ . '/sf_cache.php';
            sf_cache_set($cacheKey, $defaults, $cacheTtl);
            @file_put_contents($cacheFile, json_encode($defaults, JSON_UNESCAPED_UNICODE));
        }

        return $defaults;
    }

    /**
     * Mini sparkline SVG (7 pontos) para cards do dashboard.
     *
     * @param list<float|int> $points
     */
    function dashboard_home_spark_svg(array $points, string $color = '#3b82f6'): string
    {
        if (count($points) < 2) {
            return '';
        }
        $w = 88;
        $h = 32;
        $pad = 3;
        $mn = min($points);
        $mx = max($points);
        $range = ($mx - $mn) ?: 1;
        $step = ($w - $pad * 2) / (count($points) - 1);
        $coords = [];
        foreach ($points as $i => $p) {
            $x = $pad + $i * $step;
            $y = $h - $pad - (($p - $mn) / $range) * ($h - $pad * 2);
            $coords[] = round($x, 1) . ',' . round($y, 1);
        }
        $last = explode(',', end($coords));
        $poly = htmlspecialchars(implode(' ', $coords), ENT_QUOTES, 'UTF-8');
        $color = htmlspecialchars($color, ENT_QUOTES, 'UTF-8');
        return '<svg class="dh-kpi__spark" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" aria-hidden="true">'
            . '<polyline fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="' . $poly . '"/>'
            . '<circle cx="' . $last[0] . '" cy="' . $last[1] . '" r="2.5" fill="' . $color . '"/></svg>';
    }

    /**
     * @return list<float>
     */
    function dashboard_home_spark_daily(PDO $conn, int $empresa_id, string $sql, string $paramKey = ':e'): array
    {
        $out = [];
        try {
            $st = $conn->prepare($sql);
            $st->bindValue($paramKey, $empresa_id, PDO::PARAM_INT);
            $st->execute();
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $out[] = (float) ($row['v'] ?? $row['total'] ?? 0);
            }
        } catch (Exception $e) {
            return [];
        }
        return $out;
    }
}
