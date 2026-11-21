<?php
/**
 * Helpers — Relatórios (validação, período, tenant).
 */

if (!function_exists('relatorio_empresa_id')) {
    function relatorio_empresa_id(): int
    {
        return (int) ($_SESSION['empresa_id'] ?? 0);
    }
}

if (!function_exists('relatorio_rota_period_sql')) {
    /** Filtro de período em rotas (data_rota com fallback data_saida). */
    function relatorio_rota_period_sql(string $alias = 'r'): string
    {
        $a = preg_replace('/[^a-z_]/', '', $alias) ?: 'r';
        return "YEAR(COALESCE({$a}.data_rota, {$a}.data_saida)) = :year AND MONTH(COALESCE({$a}.data_rota, {$a}.data_saida)) = :month";
    }
}

if (!function_exists('relatorio_rota_period_literal')) {
    function relatorio_rota_period_literal(int $year, int $month, string $alias = 'r'): string
    {
        $a = preg_replace('/[^a-z_]/', '', $alias) ?: 'r';
        $y = (int) $year;
        $m = (int) $month;
        return "YEAR(COALESCE({$a}.data_rota, {$a}.data_saida)) = {$y} AND MONTH(COALESCE({$a}.data_rota, {$a}.data_saida)) = {$m}";
    }
}

if (!function_exists('relatorio_valid_date')) {
    function relatorio_valid_date(string $d): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            return false;
        }
        [$y, $m, $dd] = array_map('intval', explode('-', $d));
        return checkdate($m, $dd, $y);
    }
}

if (!function_exists('relatorio_period_state')) {
    /**
     * Estado do período (modo mês/ano — padrão — ou intervalo de datas).
     * Opt-in: enquanto não configurado, tudo continua no modo mês/ano de sempre.
     */
    function &relatorio_period_state(): array
    {
        static $state = ['mode' => 'mes', 'dini' => null, 'dfim' => null];
        return $state;
    }
}

if (!function_exists('relatorio_period_configure')) {
    function relatorio_period_configure(string $mode, ?string $dini = null, ?string $dfim = null): void
    {
        $state = &relatorio_period_state();
        if ($mode === 'intervalo' && $dini && $dfim) {
            $state['mode'] = 'intervalo';
            $state['dini'] = $dini;
            $state['dfim'] = $dfim;
        } else {
            $state['mode'] = 'mes';
            $state['dini'] = null;
            $state['dfim'] = null;
        }
    }
}

if (!function_exists('relatorio_period_is_range')) {
    function relatorio_period_is_range(): bool
    {
        $state = relatorio_period_state();
        return $state['mode'] === 'intervalo' && !empty($state['dini']) && !empty($state['dfim']);
    }
}

if (!function_exists('relatorio_apply_daterange')) {
    /**
     * Converte os filtros de período mês/ano de um SQL para um intervalo de datas.
     * Troca `YEAR(EXPR) = :param AND MONTH(EXPR) = :param2` e a versão literal
     * (`= 2026 AND MONTH(...) = 7`) por `DATE(EXPR) BETWEEN :dini_x AND :dfim_x`
     * (ou literais). Só é chamado no modo intervalo — no modo mês/ano o SQL fica intacto.
     */
    function relatorio_apply_daterange(string $sql): string
    {
        // EXPR pode ter um nível de parênteses (ex.: COALESCE(a.data_rota, a.data_saida)).
        $expr = '((?:[^()]|\([^()]*\))+)';

        // 1) Predicados com placeholders nomeados (:year, :year_abastecimento, :year2, ...).
        $sql = preg_replace_callback(
            '/YEAR\(' . $expr . '\)\s*=\s*:(\w+)\s+AND\s+MONTH\(\1\)\s*=\s*:(\w+)/s',
            function ($m) {
                $e = $m[1];
                $suffix = preg_replace('/^year/', '', $m[2]); // year -> '', year_abastecimento -> _abastecimento, year2 -> 2
                return "DATE($e) BETWEEN :dini$suffix AND :dfim$suffix";
            },
            $sql
        );

        // 2) Predicados com literais numéricos (subqueries com {$year}/{$month} interpolados).
        $state = relatorio_period_state();
        $dini = $state['dini'];
        $dfim = $state['dfim'];
        $sql = preg_replace_callback(
            '/YEAR\(' . $expr . '\)\s*=\s*\d+\s+AND\s+MONTH\(\1\)\s*=\s*\d+/s',
            function ($m) use ($dini, $dfim) {
                $e = $m[1];
                return "DATE($e) BETWEEN '$dini' AND '$dfim'";
            },
            $sql
        );

        return $sql;
    }
}

if (!function_exists('relatorio_allowed_types')) {
    function relatorio_allowed_types(): array
    {
        return [
            'rotas', 'despesas_viagem', 'despesas_fixas', 'manutencoes', 'abastecimentos',
            'lucro_viagem', 'lucro_total', 'lucro_veiculo', 'lucro_completo_veiculo',
            'pneus', 'vida_util_pneus', 'custos_veiculo', 'eficiencia_frota',
            'historico_manutencoes', 'analise_preditiva', 'otimizacao_pneus', 'veiculos_status',
            'quilometragem', 'recebimentos', 'contas_pagar', 'contas_pagas', 'financiamentos',
            'ocorrencias', 'desempenho', 'produtividade_motoristas', 'consumo_combustivel',
            'custo_por_km', 'rentabilidade_rotas', 'multas_motorista_veiculo', 'ocupacao_frota',
            'veiculos_ociosos', 'custos_manutencao_veiculo', 'comparacao_mensal',
            'tendencia_6', 'tendencia_12', 'margem_veiculo', 'veiculos_prejuizo', 'ranking',
            'sla_manutencao', 'custo_tipo_manutencao', 'desvio_consumo', 'executivo_resumido',
            // Motoristas & Viagem
            'jornada_motorista', 'ocorrencias_viagem', 'pedagios_viagem', 'entregas_pod',
            'telemetria_gps', 'comissoes_motorista', 'vencimentos_cnh',
            // Novos: checklists, modo viagem, ESG e DRE
            'checklists', 'modo_viagem', 'co2_emissoes', 'dre_mensal',
        ];
    }
}

if (!function_exists('relatorio_validate_request')) {
    /**
     * @return array{report_type:string, month:int, year:int, format:string}
     */
    function relatorio_validate_request(string $reportType, $month, $year, string $format): array
    {
        if (strpos($reportType, 'fiscal_') === 0) {
            $tipo = substr($reportType, 7);
            if ($tipo === '') {
                throw new InvalidArgumentException('Tipo de relatório fiscal inválido.');
            }
            return [
                'report_type' => $reportType,
                'month' => max(1, min(12, (int) $month)),
                'year' => (int) $year,
                'format' => in_array($format, ['pdf', 'excel', 'csv'], true) ? $format : 'pdf',
            ];
        }

        if (!in_array($reportType, relatorio_allowed_types(), true)) {
            throw new InvalidArgumentException('Tipo de relatório inválido.');
        }

        $m = (int) $month;
        $y = (int) $year;
        if ($m < 1 || $m > 12) {
            throw new InvalidArgumentException('Mês inválido.');
        }
        if ($y < 2000 || $y > 2100) {
            throw new InvalidArgumentException('Ano inválido.');
        }
        if (!in_array($format, ['pdf', 'excel', 'csv'], true)) {
            $format = 'pdf';
        }

        return [
            'report_type' => $reportType,
            'month' => $m,
            'year' => $y,
            'format' => $format,
        ];
    }
}

if (!function_exists('relatorio_bind_stmt_params')) {
    function relatorio_bind_stmt_params(PDOStatement $stmt, string $reportType, int $month, int $year, int $empresa_id, string $sql): void
    {
        // Modo intervalo: liga os placeholders de data (:dini*/:dfim*) presentes no SQL já transformado.
        if (relatorio_period_is_range()) {
            $state = relatorio_period_state();
            if (preg_match_all('/:(?:dini|dfim)\w*/', $sql, $mm)) {
                foreach (array_unique($mm[0]) as $ph) {
                    $isDini = strpos($ph, 'dini') !== false;
                    $stmt->bindValue($ph, $isDini ? $state['dini'] : $state['dfim']);
                }
            }
            if (strpos($sql, ':empresa_id') !== false) {
                $stmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
            }
            return;
        }

        $noPeriod = ['veiculos_status', 'vida_util_pneus', 'otimizacao_pneus', 'analise_preditiva', 'vencimentos_cnh'];

        if (!in_array($reportType, $noPeriod, true)) {
            if ($reportType === 'eficiencia_frota') {
                $stmt->bindValue(':year_rota', $year, PDO::PARAM_INT);
                $stmt->bindValue(':month_rota', $month, PDO::PARAM_INT);
                $stmt->bindValue(':year_abastecimento', $year, PDO::PARAM_INT);
                $stmt->bindValue(':month_abastecimento', $month, PDO::PARAM_INT);
            } elseif ($reportType === 'custos_veiculo') {
                $stmt->bindValue(':year_abastecimento', $year, PDO::PARAM_INT);
                $stmt->bindValue(':month_abastecimento', $month, PDO::PARAM_INT);
                $stmt->bindValue(':year_manutencao', $year, PDO::PARAM_INT);
                $stmt->bindValue(':month_manutencao', $month, PDO::PARAM_INT);
                $stmt->bindValue(':year_rota', $year, PDO::PARAM_INT);
                $stmt->bindValue(':month_rota', $month, PDO::PARAM_INT);
                $stmt->bindValue(':year_despesa', $year, PDO::PARAM_INT);
                $stmt->bindValue(':month_despesa', $month, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':year', $year, PDO::PARAM_INT);
                $stmt->bindValue(':month', $month, PDO::PARAM_INT);
            }
        }

        if (strpos($sql, ':empresa_id') !== false) {
            $stmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
        }

        if ($reportType === 'custo_por_km') {
            $stmt->bindValue(':year2', $year, PDO::PARAM_INT);
            $stmt->bindValue(':month2', $month, PDO::PARAM_INT);
        }
    }
}
