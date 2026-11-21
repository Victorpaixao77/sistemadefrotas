<?php
/**
 * Helpers compartilhados das APIs do painel BI.
 */

if (!function_exists('bi_month_range_exclusive')) {
    /** @return array{ini: string, excl: string} primeiro dia inclusivo, primeiro dia do mês seguinte (exclusivo) */
    function bi_month_range_exclusive(string $mes_ano): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $mes_ano)) {
            return ['ini' => '1970-01-01', 'excl' => '1970-02-01'];
        }
        $ini = $mes_ano . '-01';
        $excl = date('Y-m-d', strtotime($ini . ' +1 month'));
        return ['ini' => $ini, 'excl' => $excl];
    }
}

if (!function_exists('bi_map_ranking_route_rows')) {
    function bi_map_ranking_route_rows(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $od = trim(($r['origem_nome'] ?? '') . ' → ' . ($r['destino_nome'] ?? ''));
            if ($od === ' → ') {
                $od = 'Rota #' . ($r['id'] ?? '');
            }
            $out[] = [
                'id' => (int) ($r['id'] ?? 0),
                'km' => round((float) ($r['km'] ?? 0), 2),
                'frete' => round((float) ($r['frete'] ?? 0), 2),
                'comissao' => round((float) ($r['comissao'] ?? 0), 2),
                'desp_viagem' => round((float) ($r['desp_viagem'] ?? 0), 2),
                'lucro' => round((float) ($r['lucro'] ?? 0), 2),
                'margem_pct' => round((float) ($r['margem_pct'] ?? 0), 2),
                'origem_destino' => $od,
                'custo_total' => round((float) ($r['comissao'] ?? 0) + (float) ($r['desp_viagem'] ?? 0), 2),
            ];
        }
        return $out;
    }
}

if (!function_exists('bi_pi_ranking_sql_core')) {
    function bi_pi_ranking_sql_core(string $cond_rotas_r): string
    {
        return "
            SELECT
                r.id,
                r.distancia_km AS km,
                r.frete,
                r.comissao,
                COALESCE(dv.total_desp, 0) AS desp_viagem,
                (r.frete - r.comissao - COALESCE(dv.total_desp, 0)) AS lucro,
                CASE WHEN r.frete > 0
                    THEN ((r.frete - r.comissao - COALESCE(dv.total_desp, 0)) / r.frete * 100)
                    ELSE 0 END AS margem_pct,
                COALESCE(co.nome, '') AS origem_nome,
                COALESCE(cd.nome, '') AS destino_nome
            FROM rotas r
            LEFT JOIN (
                SELECT rota_id, SUM(COALESCE(total_despviagem, 0)) AS total_desp
                FROM despesas_viagem
                GROUP BY rota_id
            ) dv ON dv.rota_id = r.id
            LEFT JOIN cidades co ON co.id = r.cidade_origem_id
            LEFT JOIN cidades cd ON cd.id = r.cidade_destino_id
            WHERE r.empresa_id = :empresa_id
            AND r.data_saida IS NOT NULL
            {$cond_rotas_r}
            AND r.frete IS NOT NULL
        ";
    }
}

if (!function_exists('bi_trim_pi_data_for_visao')) {
    /**
     * Remove blocos não usados pela visão ativa (menor payload JSON).
     */
    function bi_trim_pi_data_for_visao(array $data, string $visao): array
    {
        $always = ['historico_mensal', 'comparacao', 'mes_atual', 'labels', 'dados_incompletos', 'metas'];
        $byVisao = [
            'geral' => ['veiculos_top', 'veiculos_top_abastecimento', 'veiculos_top_manutencao', 'ranking_rotas', 'despesas_viagem_tipos', 'manut_preventiva_corretiva', 'veiculos_criticos', 'veiculos_consumo', 'ponto_equilibrio', 'previsao_proximo_mes'],
            'rotas' => ['veiculos_top', 'ranking_rotas', 'ponto_equilibrio'],
            'abastecimento' => ['veiculos_top_abastecimento', 'veiculos_consumo'],
            'manutencao' => ['veiculos_top_manutencao', 'manut_preventiva_corretiva', 'veiculos_criticos', 'previsao_proximo_mes'],
            'despesas_viagem' => ['despesas_viagem_tipos'],
            'despesas_fixas' => [],
        ];
        $keep = array_merge($always, $byVisao[$visao] ?? $byVisao['geral']);
        $trimmed = [];
        foreach ($keep as $k) {
            if (array_key_exists($k, $data)) {
                $trimmed[$k] = $data[$k];
            }
        }
        return $trimmed;
    }
}

if (!function_exists('bi_ia_dto_from_payload')) {
    /**
     * DTO compacto e saneado para o resumo IA (últimos meses + totais + comparação).
     */
    function bi_ia_dto_from_payload(array $raw): array
    {
        $hist = is_array($raw['historico_mensal'] ?? null) ? $raw['historico_mensal'] : [];
        $hist = array_slice($hist, -6);
        $sum = static function (array $rows, string $key): float {
            $t = 0.0;
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $t += (float) ($row[$key] ?? 0);
            }
            return round($t, 2);
        };
        $dto = [
            'historico_mensal' => $hist,
            'totais_periodo' => [
                'frete' => $sum($hist, 'total_frete'),
                'lucro_operacional' => $sum($hist, 'lucro_operacional'),
                'km' => $sum($hist, 'total_km_rodados'),
                'gasto_abast' => $sum($hist, 'total_gasto_abastecimentos'),
                'manutencoes' => $sum($hist, 'total_manutencoes'),
                'despesas_viagem' => $sum($hist, 'total_despesas_viagem'),
                'despesas_fixas' => $sum($hist, 'total_despesas_fixas'),
            ],
        ];
        if (isset($raw['comparacao']) && is_array($raw['comparacao'])) {
            $dto['comparacao'] = $raw['comparacao'];
        }
        if (isset($raw['ranking_rotas']) && is_array($raw['ranking_rotas'])) {
            $dto['ranking_rotas'] = [
                'top5' => array_slice($raw['ranking_rotas']['top5'] ?? [], 0, 3),
                'bottom5' => array_slice($raw['ranking_rotas']['bottom5'] ?? [], 0, 3),
            ];
        }
        if (isset($raw['veiculos_top']) && is_array($raw['veiculos_top'])) {
            $dto['veiculos_top'] = array_slice($raw['veiculos_top'], 0, 5);
        }
        if (isset($raw['dados_incompletos']) && is_array($raw['dados_incompletos'])) {
            $dto['dados_incompletos'] = $raw['dados_incompletos'];
        }
        return $dto;
    }
}

if (!function_exists('bi_resumo_ia_rate_limit')) {
    /** Limite: 1 requisição / 60s por empresa (sessão). */
    function bi_resumo_ia_rate_limit(int $empresa_id): void
    {
        if ($empresa_id <= 0) {
            return;
        }
        $key = 'bi_resumo_ia_ts_' . $empresa_id;
        $now = time();
        $last = isset($_SESSION[$key]) ? (int) $_SESSION[$key] : 0;
        if ($last > 0 && ($now - $last) < 60) {
            if (!function_exists('api_json_error')) {
                require_once __DIR__ . '/api_json.php';
            }
            api_json_error('Aguarde um minuto antes de gerar outro resumo.', 429, 'ia_throttled');
        }
        $_SESSION[$key] = $now;
    }
}
