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
                'format' => in_array($format, ['pdf', 'excel'], true) ? $format : 'pdf',
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
        if (!in_array($format, ['pdf', 'excel'], true)) {
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
        $noPeriod = ['veiculos_status', 'vida_util_pneus', 'otimizacao_pneus', 'analise_preditiva'];

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
