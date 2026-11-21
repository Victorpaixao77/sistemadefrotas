<?php
/**
 * Funções de dados para Lucratividade (página + APIs).
 */

if (!function_exists('lucra_sql_alias')) {
    function lucra_sql_alias(string $alias, string $default): string
    {
        $a = preg_replace('/[^a-z0-9_]/', '', strtolower($alias));
        return ($a !== '' && preg_match('/^[a-z][a-z0-9_]*$/', $a)) ? $a : $default;
    }
}

if (!function_exists('lucra_rota_period_sql')) {
    function lucra_rota_period_sql(string $alias = 'r'): string
    {
        $a = lucra_sql_alias($alias, 'r');
        return "MONTH(COALESCE({$a}.data_rota, {$a}.data_saida)) = ? AND YEAR(COALESCE({$a}.data_rota, {$a}.data_saida)) = ?";
    }
}

if (!function_exists('lucra_abastecimento_expr')) {
    function lucra_abastecimento_expr(string $alias = 'a'): string
    {
        $a = lucra_sql_alias($alias, 'a');
        return "{$a}.valor_total + COALESCE(CASE WHEN {$a}.inclui_arla = 1 THEN {$a}.valor_total_arla ELSE 0 END, 0)";
    }
}

if (!function_exists('lucra_abastecimento_where')) {
    function lucra_abastecimento_where(string $alias = 'a'): string
    {
        $a = lucra_sql_alias($alias, 'a');
        return "{$a}.empresa_id = ? AND MONTH({$a}.data_abastecimento) = ? AND YEAR({$a}.data_abastecimento) = ? AND {$a}.status = 'aprovado'";
    }
}

if (!function_exists('lucra_calc_lucro_liquido')) {
    function lucra_calc_lucro_liquido(array $k): float
    {
        $receita = (float) ($k['total_frete'] ?? 0);
        $despesas = (float) ($k['total_comissao'] ?? 0)
            + (float) ($k['total_abastecimentos'] ?? 0)
            + (float) ($k['total_despesas_viagem'] ?? 0)
            + (float) ($k['total_despesas_fixas'] ?? 0)
            + (float) ($k['total_parcelas_financiamento'] ?? 0)
            + (float) ($k['total_contas_pagas'] ?? 0)
            + (float) ($k['total_manutencoes'] ?? 0)
            + (float) ($k['total_pneu_manutencao'] ?? 0);
        return $receita - $despesas;
    }
}

if (!function_exists('lucra_kpis_vazios')) {
    function lucra_kpis_vazios(int $mes, int $ano): array
    {
        return [
            'mes_ano' => sprintf('%04d-%02d', $ano, $mes),
            'total_frete' => 0,
            'total_comissao' => 0,
            'total_despesas_viagem' => 0,
            'total_abastecimentos' => 0,
            'total_despesas_fixas' => 0,
            'total_parcelas_financiamento' => 0,
            'total_contas_pagas' => 0,
            'total_manutencoes' => 0,
            'total_pneu_manutencao' => 0,
            'lucro_liquido' => 0,
        ];
    }
}

if (!function_exists('getKPIsOptimized')) {
    function getKPIsOptimized($conn, $empresa_id, $mes, $ano): array
    {
        $mes = (int) $mes;
        $ano = (int) $ano;
        $empresa_id = (int) $empresa_id;

        if (!function_exists('sf_cache_remember')) {
            require_once __DIR__ . '/sf_cache.php';
        }
        $cacheKey = 'lucra:kpi:' . $empresa_id . ':' . $ano . '-' . sprintf('%02d', $mes);

        return sf_cache_remember($cacheKey, 300, static function () use ($conn, $empresa_id, $mes, $ano) {
            return lucra_compute_kpis($conn, $empresa_id, $mes, $ano);
        });
    }
}

if (!function_exists('lucra_compute_kpis')) {
    function lucra_compute_kpis($conn, $empresa_id, $mes, $ano): array
    {
        $empresa_id = (int) $empresa_id;
        $mes = (int) $mes;
        $ano = (int) $ano;
        $abast = lucra_abastecimento_expr('a');
        $rotaPeriod = lucra_rota_period_sql('r');

        try {
            $sql = "
            SELECT
                (SELECT COALESCE(SUM(r.frete), 0) FROM rotas r
                    WHERE r.empresa_id = ? AND {$rotaPeriod}) AS total_frete,
                (SELECT COALESCE(SUM(r.comissao), 0) FROM rotas r
                    WHERE r.empresa_id = ? AND {$rotaPeriod}) AS total_comissao,
                (SELECT COALESCE(SUM(dv.total_despviagem), 0)
                    FROM despesas_viagem dv
                    INNER JOIN rotas r ON r.id = dv.rota_id AND r.empresa_id = dv.empresa_id
                    WHERE dv.empresa_id = ? AND {$rotaPeriod}) AS total_despesas_viagem,
                (SELECT COALESCE(SUM({$abast}), 0) FROM abastecimentos a
                    WHERE " . lucra_abastecimento_where('a') . ") AS total_abastecimentos,
                (SELECT COALESCE(SUM(df.valor), 0) FROM despesas_fixas df
                    WHERE df.empresa_id = ? AND df.status_pagamento_id = 2
                      AND MONTH(df.data_pagamento) = ? AND YEAR(df.data_pagamento) = ?) AS total_despesas_fixas,
                (SELECT COALESCE(SUM(pf.valor), 0) FROM parcelas_financiamento pf
                    WHERE pf.empresa_id = ? AND pf.status_id = 2
                      AND MONTH(pf.data_pagamento) = ? AND YEAR(pf.data_pagamento) = ?) AS total_parcelas_financiamento,
                (SELECT COALESCE(SUM(cp.valor), 0) FROM contas_pagar cp
                    WHERE cp.empresa_id = ? AND cp.status_id = 2
                      AND MONTH(cp.data_pagamento) = ? AND YEAR(cp.data_pagamento) = ?) AS total_contas_pagas,
                (SELECT COALESCE(SUM(m.valor), 0) FROM manutencoes m
                    WHERE m.empresa_id = ? AND MONTH(m.data_manutencao) = ? AND YEAR(m.data_manutencao) = ?) AS total_manutencoes,
                (SELECT COALESCE(SUM(pm.custo), 0) FROM pneu_manutencao pm
                    WHERE pm.empresa_id = ? AND MONTH(pm.data_manutencao) = ? AND YEAR(pm.data_manutencao) = ?) AS total_pneu_manutencao
            FROM (SELECT 1 AS x) lucra_anchor
            ";

            $p = [
                $empresa_id, $mes, $ano,
                $empresa_id, $mes, $ano,
                $empresa_id, $mes, $ano,
                $empresa_id, $mes, $ano,
                $empresa_id, $mes, $ano,
                $empresa_id, $mes, $ano,
                $empresa_id, $mes, $ano,
                $empresa_id, $mes, $ano,
                $empresa_id, $mes, $ano,
            ];

            $stmt = $conn->prepare($sql);
            $stmt->execute($p);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return lucra_kpis_vazios($mes, $ano);
            }

            $result['mes_ano'] = sprintf('%04d-%02d', $ano, $mes);
            $result['lucro_liquido'] = lucra_calc_lucro_liquido($result);
            return $result;
        } catch (Throwable $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log('getKPIsOptimized: ' . $e->getMessage());
            }
            return lucra_kpis_vazios($mes, $ano);
        }
    }
}

if (!function_exists('lucra_get_evolucao_meses')) {
    /**
     * @return array{labels:array,lucro:array,receita:array}
     */
    function lucra_get_evolucao_meses(PDO $conn, int $empresa_id, int $mes, int $ano, int $qtd = 6): array
    {
        $labels = [];
        $lucro = [];
        $receita = [];
        $meses_pt = [1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun',
            7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'];

        for ($i = $qtd - 1; $i >= 0; $i--) {
            $m = $mes - $i;
            $y = $ano;
            while ($m < 1) {
                $m += 12;
                $y--;
            }
            $kpis = getKPIsOptimized($conn, $empresa_id, $m, $y);
            $labels[] = ($meses_pt[$m] ?? (string) $m) . '/' . substr((string) $y, -2);
            $receita[] = round((float) ($kpis['total_frete'] ?? 0), 2);
            $lucro[] = round((float) ($kpis['lucro_liquido'] ?? 0), 2);
        }

        return compact('labels', 'lucro', 'receita');
    }
}

if (!function_exists('lucra_get_distribuicao_custos')) {
    /**
     * @return array{labels:array,values:array}
     */
    function lucra_get_distribuicao_custos(PDO $conn, int $empresa_id, int $mes, int $ano): array
    {
        $kpis = getKPIsOptimized($conn, $empresa_id, $mes, $ano);
        $map = [
            'Combustível' => (float) ($kpis['total_abastecimentos'] ?? 0),
            'Comissões' => (float) ($kpis['total_comissao'] ?? 0),
            'Despesas de Viagem' => (float) ($kpis['total_despesas_viagem'] ?? 0),
            'Despesas Fixas' => (float) ($kpis['total_despesas_fixas'] ?? 0),
            'Financiamento' => (float) ($kpis['total_parcelas_financiamento'] ?? 0),
            'Contas Pagas' => (float) ($kpis['total_contas_pagas'] ?? 0),
            'Manutenções' => (float) ($kpis['total_manutencoes'] ?? 0),
            'Pneus' => (float) ($kpis['total_pneu_manutencao'] ?? 0),
        ];
        $labels = [];
        $values = [];
        foreach ($map as $label => $valor) {
            if ($valor > 0) {
                $labels[] = $label;
                $values[] = round($valor, 2);
            }
        }
        return compact('labels', 'values');
    }
}

if (!function_exists('lucra_shift_mes_ano')) {
    function lucra_shift_mes_ano(int $mes, int $ano, int $delta): array
    {
        $m = $mes + $delta;
        $y = $ano;
        while ($m < 1) {
            $m += 12;
            $y--;
        }
        while ($m > 12) {
            $m -= 12;
            $y++;
        }
        return [$m, $y];
    }
}

if (!function_exists('getIntelligentAlerts')) {
    function getIntelligentAlerts($conn, $empresa_id, $mes, $ano): array
    {
        try {
            $alerts = [];
            $kpis = getKPIsOptimized($conn, $empresa_id, $mes, $ano);
            $lucro = (float) ($kpis['lucro_liquido'] ?? 0);
            $receita = (float) ($kpis['total_frete'] ?? 0);
            $abastecimentos = (float) ($kpis['total_abastecimentos'] ?? 0);

            if ($receita > 0) {
                $margem = ($lucro / $receita) * 100;
                if ($margem < 10) {
                    $alerts[] = ['type' => 'warning', 'title' => 'Margem Baixa', 'message' => 'Margem de lucro está em ' . number_format($margem, 1) . '%. Considere revisar custos.', 'icon' => 'fas fa-exclamation-triangle'];
                } elseif ($margem >= 30) {
                    $alerts[] = ['type' => 'success', 'title' => 'Margem Excelente', 'message' => 'Margem de lucro está em ' . number_format($margem, 1) . '%.', 'icon' => 'fas fa-check-circle'];
                }
            }
            if ($lucro < 0) {
                $alerts[] = ['type' => 'danger', 'title' => 'Prejuízo Detectado', 'message' => 'O mês está fechando com prejuízo de R$ ' . number_format(abs($lucro), 2, ',', '.') . '.', 'icon' => 'fas fa-times-circle'];
            } elseif ($lucro > 0 && $receita > 0) {
                $alerts[] = ['type' => 'success', 'title' => 'Lucro Positivo', 'message' => 'Lucro de R$ ' . number_format($lucro, 2, ',', '.') . '.', 'icon' => 'fas fa-check-circle'];
            }
            if ($receita > 0 && $abastecimentos > 0) {
                $pct = ($abastecimentos / $receita) * 100;
                if ($pct > 40) {
                    $alerts[] = ['type' => 'info', 'title' => 'Alto Consumo de Combustível', 'message' => 'Combustível representa ' . number_format($pct, 1) . '% da receita.', 'icon' => 'fas fa-gas-pump'];
                } elseif ($pct < 25) {
                    $alerts[] = ['type' => 'success', 'title' => 'Eficiência de Combustível', 'message' => 'Combustível representa ' . number_format($pct, 1) . '% da receita.', 'icon' => 'fas fa-check-circle'];
                }
            }

            [$mes_ant, $ano_ant] = lucra_shift_mes_ano((int) $mes, (int) $ano, -1);
            $kpis_ant = getKPIsOptimized($conn, $empresa_id, $mes_ant, $ano_ant);
            $lucro_ant = (float) ($kpis_ant['lucro_liquido'] ?? 0);
            if ($lucro_ant != 0) {
                $cresc = (($lucro - $lucro_ant) / abs($lucro_ant)) * 100;
                if ($cresc < -20) {
                    $alerts[] = ['type' => 'warning', 'title' => 'Queda na Lucratividade', 'message' => 'Lucro caiu ' . number_format(abs($cresc), 1) . '% em relação ao mês anterior.', 'icon' => 'fas fa-chart-line'];
                } elseif ($cresc > 20) {
                    $alerts[] = ['type' => 'success', 'title' => 'Crescimento na Lucratividade', 'message' => 'Lucro aumentou ' . number_format($cresc, 1) . '% em relação ao mês anterior.', 'icon' => 'fas fa-chart-line'];
                }
            } elseif ($lucro_ant <= 0 && $lucro > 0) {
                $alerts[] = ['type' => 'success', 'title' => 'Recuperação Financeira', 'message' => 'Lucro positivo após período negativo.', 'icon' => 'fas fa-chart-line'];
            }

            return $alerts;
        } catch (Throwable $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log('getIntelligentAlerts: ' . $e->getMessage());
            }
            return [];
        }
    }
}

if (!function_exists('getAdvancedKPIs')) {
    function getAdvancedKPIs($conn, $empresa_id, $mes, $ano, $kpis): array
    {
        try {
            $mes = (int) $mes;
            $ano = (int) $ano;
            $empresa_id = (int) $empresa_id;
            $lucro = (float) ($kpis['lucro_liquido'] ?? 0);
            $receita = (float) ($kpis['total_frete'] ?? 0);
            $rotaPeriod = lucra_rota_period_sql('r');

            $investimento_total = 0.0;
            $stmt = $conn->prepare('SELECT COALESCE(SUM(valor_total), 0) AS total FROM financiamentos WHERE empresa_id = ?');
            $stmt->execute([$empresa_id]);
            $investimento_total = (float) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

            $stmt = $conn->prepare("SELECT COUNT(*) AS total_rotas FROM rotas r WHERE r.empresa_id = ? AND {$rotaPeriod}");
            $stmt->execute([$empresa_id, $mes, $ano]);
            $total_rotas = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total_rotas'] ?? 0);

            $stmt = $conn->prepare("SELECT COALESCE(SUM(distancia_km), 0) AS total_km FROM rotas r WHERE r.empresa_id = ? AND {$rotaPeriod}");
            $stmt->execute([$empresa_id, $mes, $ano]);
            $total_km = (float) ($stmt->fetch(PDO::FETCH_ASSOC)['total_km'] ?? 0);

            $despesas_var = (float) ($kpis['total_abastecimentos'] ?? 0)
                + (float) ($kpis['total_despesas_viagem'] ?? 0)
                + (float) ($kpis['total_despesas_fixas'] ?? 0)
                + (float) ($kpis['total_manutencoes'] ?? 0)
                + (float) ($kpis['total_pneu_manutencao'] ?? 0);

            $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM rotas r WHERE r.empresa_id = ? AND {$rotaPeriod} AND COALESCE(r.km_vazio, 0) < COALESCE(r.distancia_km, 0)");
            $stmt->execute([$empresa_id, $mes, $ano]);
            $rotas_carga = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

            return [
                'roi' => $investimento_total > 0 ? ($lucro / $investimento_total) * 100 : 0,
                'ticket_medio' => $total_rotas > 0 ? $receita / $total_rotas : 0,
                'custo_km' => $total_km > 0 ? $despesas_var / $total_km : 0,
                'margem_operacional' => $receita > 0 ? ($lucro / $receita) * 100 : 0,
                'taxa_ocupacao' => $total_rotas > 0 ? ($rotas_carga / $total_rotas) * 100 : 0,
            ];
        } catch (Throwable $e) {
            return ['roi' => 0, 'ticket_medio' => 0, 'custo_km' => 0, 'margem_operacional' => 0, 'taxa_ocupacao' => 0];
        }
    }
}

if (!function_exists('getRankings')) {
    function getRankings($conn, $empresa_id, $mes, $ano): array
    {
        $rankings = ['veiculos' => [], 'motoristas' => [], 'clientes' => [], 'tipos_frete' => []];
        $mes = (int) $mes;
        $ano = (int) $ano;
        $empresa_id = (int) $empresa_id;
        $rotaPeriod = lucra_rota_period_sql('r');
        $rotaPeriodR2 = lucra_rota_period_sql('r2');
        $rotaPeriodR3 = lucra_rota_period_sql('r3');
        $abast = lucra_abastecimento_expr('a2');

        $sqlVeic = "
                SELECT v.placa, v.modelo,
                    COALESCE(SUM(r.frete), 0) AS receita,
                    COALESCE(SUM(r.comissao), 0) AS comissao,
                    COALESCE((
                        SELECT SUM({$abast}) FROM abastecimentos a2
                        WHERE a2.rota_id IN (
                            SELECT r2.id FROM rotas r2
                            WHERE r2.veiculo_id = v.id AND r2.empresa_id = ? AND {$rotaPeriodR2}
                        ) AND a2.empresa_id = ? AND a2.status = 'aprovado'
                    ), 0) AS custo_abastecimento,
                    COALESCE((
                        SELECT SUM(dv2.total_despviagem) FROM despesas_viagem dv2
                        WHERE dv2.rota_id IN (
                            SELECT r3.id FROM rotas r3
                            WHERE r3.veiculo_id = v.id AND r3.empresa_id = ? AND {$rotaPeriodR3}
                        ) AND dv2.empresa_id = ?
                    ), 0) AS despesas_viagem,
                    COALESCE((
                        SELECT SUM(m2.valor) FROM manutencoes m2
                        WHERE m2.veiculo_id = v.id AND m2.empresa_id = ?
                          AND MONTH(m2.data_manutencao) = ? AND YEAR(m2.data_manutencao) = ?
                    ), 0) AS custo_manutencao,
                    (
                        COALESCE(SUM(r.frete), 0) - COALESCE(SUM(r.comissao), 0)
                        - COALESCE((
                            SELECT SUM({$abast}) FROM abastecimentos a2
                            WHERE a2.rota_id IN (
                                SELECT r2.id FROM rotas r2
                                WHERE r2.veiculo_id = v.id AND r2.empresa_id = ? AND {$rotaPeriodR2}
                            ) AND a2.empresa_id = ? AND a2.status = 'aprovado'
                        ), 0)
                        - COALESCE((
                            SELECT SUM(dv2.total_despviagem) FROM despesas_viagem dv2
                            WHERE dv2.rota_id IN (
                                SELECT r3.id FROM rotas r3
                                WHERE r3.veiculo_id = v.id AND r3.empresa_id = ? AND {$rotaPeriodR3}
                            ) AND dv2.empresa_id = ?
                        ), 0)
                        - COALESCE((
                            SELECT SUM(m2.valor) FROM manutencoes m2
                            WHERE m2.veiculo_id = v.id AND m2.empresa_id = ?
                              AND MONTH(m2.data_manutencao) = ? AND YEAR(m2.data_manutencao) = ?
                        ), 0)
                    ) AS lucro
                FROM rotas r
                INNER JOIN veiculos v ON v.id = r.veiculo_id AND v.empresa_id = r.empresa_id
                WHERE r.empresa_id = ? AND {$rotaPeriod}
                GROUP BY v.id, v.placa, v.modelo
                HAVING receita > 0
                ORDER BY lucro DESC
                LIMIT 5
            ";

        try {
            $stmt = $conn->prepare($sqlVeic);
            $stmt->execute([
                $empresa_id, $mes, $ano, $empresa_id,
                $empresa_id, $mes, $ano, $empresa_id,
                $empresa_id, $mes, $ano,
                $empresa_id, $mes, $ano, $empresa_id,
                $empresa_id, $mes, $ano, $empresa_id,
                $empresa_id, $mes, $ano,
                $empresa_id, $mes, $ano,
            ]);
            $rankings['veiculos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('getRankings veiculos: ' . $e->getMessage());
        }

        try {
            $rotaPeriodRm = lucra_rota_period_sql('rm');
            $stmt = $conn->prepare("
                SELECT m.nome,
                    COALESCE(SUM(r.frete), 0) AS receita,
                    COALESCE(SUM(r.comissao), 0) AS comissao,
                    COALESCE((
                        SELECT SUM(dv2.total_despviagem) FROM despesas_viagem dv2
                        WHERE dv2.rota_id IN (
                            SELECT rm.id FROM rotas rm
                            WHERE rm.motorista_id = m.id AND rm.empresa_id = ? AND {$rotaPeriodRm}
                        ) AND dv2.empresa_id = ?
                    ), 0) AS despesas_viagem,
                    (
                        COALESCE(SUM(r.frete), 0) - COALESCE(SUM(r.comissao), 0)
                        - COALESCE((
                            SELECT SUM(dv2.total_despviagem) FROM despesas_viagem dv2
                            WHERE dv2.rota_id IN (
                                SELECT rm.id FROM rotas rm
                                WHERE rm.motorista_id = m.id AND rm.empresa_id = ? AND {$rotaPeriodRm}
                            ) AND dv2.empresa_id = ?
                        ), 0)
                    ) AS lucro
                FROM rotas r
                INNER JOIN motoristas m ON m.id = r.motorista_id AND m.empresa_id = r.empresa_id
                WHERE r.empresa_id = ? AND {$rotaPeriod}
                GROUP BY m.id, m.nome
                HAVING receita > 0
                ORDER BY lucro DESC
                LIMIT 5
            ");
            $stmt->execute([
                $empresa_id, $mes, $ano, $empresa_id,
                $empresa_id, $mes, $ano, $empresa_id,
                $empresa_id, $mes, $ano,
            ]);
            $rankings['motoristas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('getRankings motoristas: ' . $e->getMessage());
        }

        try {
            $chk = $conn->query("SHOW TABLES LIKE 'tipos_frete'");
            if ($chk && $chk->rowCount() > 0) {
                $st = $conn->prepare("
                    SELECT COALESCE(tf.nome, 'Não especificado') AS tipo_frete,
                        COALESCE(SUM(r.frete), 0) AS receita,
                        COALESCE(SUM(r.comissao), 0) AS comissao,
                        (COALESCE(SUM(r.frete), 0) - COALESCE(SUM(r.comissao), 0)) AS lucro
                    FROM rotas r
                    LEFT JOIN tipos_frete tf ON r.tipo_frete_id = tf.id
                    WHERE r.empresa_id = ? AND {$rotaPeriod}
                    GROUP BY tf.id, tf.nome
                    HAVING receita > 0
                    ORDER BY lucro DESC
                ");
                $st->execute([$empresa_id, $mes, $ano]);
                $rankings['tipos_frete'] = $st->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) {
            error_log('getRankings tipos_frete: ' . $e->getMessage());
        }

        return $rankings;
    }
}

if (!function_exists('lucra_build_insights_payload')) {
    function lucra_build_insights_payload(PDO $conn, int $empresa_id, int $mes, int $ano): array
    {
        $kpis = getKPIsOptimized($conn, $empresa_id, $mes, $ano);
        $advanced = getAdvancedKPIs($conn, $empresa_id, $mes, $ano, $kpis);
        $alerts = getIntelligentAlerts($conn, $empresa_id, $mes, $ano);

        $qualidade = [];
        $qErros = 0;
        $qAvisos = 0;
        foreach ($alerts as $i => $alert) {
            $type = $alert['type'] ?? 'info';
            $sev = ($type === 'danger') ? 'erro' : (($type === 'warning' || $type === 'info') ? 'aviso' : 'ok');
            if ($sev === 'erro') {
                $qErros++;
            } elseif ($sev === 'aviso') {
                $qAvisos++;
            }
            if ($sev === 'ok') {
                continue;
            }
            $qualidade[] = [
                'id' => $i + 1,
                'nome' => (string) ($alert['title'] ?? 'Alerta'),
                'severidade' => $sev,
                'motivos' => [['msg' => (string) ($alert['message'] ?? '')]],
            ];
        }

        $lucro = (float) ($kpis['lucro_liquido'] ?? 0);
        $receita = (float) ($kpis['total_frete'] ?? 0);
        if ($receita > 0 && $lucro < 0 && empty($qualidade)) {
            $qualidade[] = [
                'id' => 99,
                'nome' => 'Prejuízo no período',
                'severidade' => 'erro',
                'motivos' => [['msg' => 'Despesas superam a receita de fretes no mês selecionado.']],
            ];
            $qErros++;
        }

        return [
            'success' => true,
            'q_erros' => $qErros,
            'q_avisos' => $qAvisos,
            'qualidade' => $qualidade,
            'mes' => $mes,
            'ano' => $ano,
            'kpis' => $kpis,
            'advanced' => $advanced,
            'lucro_liquido' => $lucro,
            'receita' => $receita,
            'margem' => (float) ($advanced['margem_operacional'] ?? 0),
        ];
    }
}
