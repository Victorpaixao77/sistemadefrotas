<?php
/**
 * Repositório de domínio — Gestão de Pneus (KPIs, histórico, custo/km, insights).
 */
require_once __DIR__ . '/pneus_helpers.php';

if (!function_exists('pneus_get_sulco_atual')) {
    function pneus_get_sulco_atual(PDO $conn, int $pneu_id): ?float
    {
        try {
            $st = $conn->prepare('
                SELECT sulco_mm FROM pneu_movimentacoes
                WHERE pneu_id = ? AND sulco_mm IS NOT NULL
                ORDER BY data_movimentacao DESC, id DESC LIMIT 1
            ');
            $st->execute([$pneu_id]);
            $v = $st->fetchColumn();
            if ($v !== false && $v !== null) {
                return (float) $v;
            }
        } catch (Throwable $e) {
            // tabela pode não existir
        }
        $st = $conn->prepare('SELECT sulco_inicial FROM pneus WHERE id = ? LIMIT 1');
        $st->execute([$pneu_id]);
        $s = $st->fetchColumn();
        return $s !== false && $s !== null ? (float) $s : null;
    }
}

if (!function_exists('pneus_atualizar_sulco_registro')) {
    function pneus_atualizar_sulco_registro(PDO $conn, int $pneu_id, float $sulco_mm): void
    {
        if ($sulco_mm <= 0) {
            return;
        }
        $st = $conn->prepare('UPDATE pneus SET sulco_inicial = ?, updated_at = NOW() WHERE id = ?');
        $st->execute([$sulco_mm, $pneu_id]);
    }
}

if (!function_exists('pneus_get_custo_km')) {
    /**
     * Custo/km = (compra + recapagens + manutenções) / km rodado.
     */
    function pneus_get_custo_km(PDO $conn, int $pneu_id, int $empresa_id): array
    {
        if (!pneus_validar_pneu_empresa($conn, $pneu_id, $empresa_id)) {
            return ['custo_total' => 0, 'km_rodado' => 0, 'custo_km' => null, 'formula' => ''];
        }

        $custo_mov = 0.0;
        $km_mov = 0;
        try {
            $st = $conn->prepare("
                SELECT COALESCE(SUM(custo), 0) AS c,
                       COALESCE(SUM(km_rodado), 0) AS k
                FROM pneu_movimentacoes
                WHERE pneu_id = ? AND empresa_id = ?
                  AND tipo IN ('entrada_estoque', 'recapagem', 'manutencao')
            ");
            $st->execute([$pneu_id, $empresa_id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            $custo_mov = (float) ($row['c'] ?? 0);
            $km_mov = (int) ($row['k'] ?? 0);
        } catch (Throwable $e) {
            // fallback manutencao
        }

        if ($custo_mov <= 0) {
            $st = $conn->prepare('SELECT COALESCE(SUM(custo), 0) FROM pneu_manutencao WHERE pneu_id = ? AND empresa_id = ?');
            $st->execute([$pneu_id, $empresa_id]);
            $custo_mov = (float) $st->fetchColumn();
        }

        $st = $conn->prepare('SELECT km_instalacao, vida_util_km FROM pneus WHERE id = ?');
        $st->execute([$pneu_id]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        $km_pneu = (float) ($p['km_instalacao'] ?? 0);
        if ($km_mov <= 0 && $km_pneu > 0) {
            $km_mov = (int) $km_pneu;
        }

        $custo_km = ($km_mov > 0 && $custo_mov > 0) ? round($custo_mov / $km_mov, 4) : null;

        return [
            'custo_total' => round($custo_mov, 2),
            'km_rodado' => $km_mov,
            'custo_km' => $custo_km,
            'formula' => 'Custo/km = (entrada + recapagens + manutenções) ÷ km rodado',
            'sulco_atual_mm' => pneus_get_sulco_atual($conn, $pneu_id),
        ];
    }
}

if (!function_exists('pneus_get_timeline')) {
    function pneus_get_timeline(PDO $conn, int $pneu_id, int $empresa_id, int $limit = 50): array
    {
        if (!pneus_validar_pneu_empresa($conn, $pneu_id, $empresa_id)) {
            return [];
        }
        $items = [];
        try {
            $st = $conn->prepare("
                SELECT pm.id, pm.tipo, pm.data_movimentacao, pm.km_odometro, pm.km_rodado,
                       pm.sulco_mm, pm.custo, pm.observacoes,
                       v.placa AS veiculo_placa, pp.nome AS posicao_nome
                FROM pneu_movimentacoes pm
                LEFT JOIN veiculos v ON v.id = pm.veiculo_id
                LEFT JOIN posicoes_pneus pp ON pp.id = pm.posicao_id
                WHERE pm.pneu_id = ? AND pm.empresa_id = ?
                ORDER BY pm.data_movimentacao DESC, pm.id DESC
                LIMIT " . (int) $limit
            );
            $st->execute([$pneu_id, $empresa_id]);
            $items = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            // sem tabela
        }

        $st = $conn->prepare("
            SELECT m.id, m.data_manutencao, m.custo, m.km_veiculo, m.observacoes,
                   t.nome AS tipo_manutencao, v.placa AS veiculo_placa
            FROM pneu_manutencao m
            LEFT JOIN tipo_manutencao_pneus t ON t.id = m.tipo_manutencao_id
            LEFT JOIN veiculos v ON v.id = m.veiculo_id
            WHERE m.pneu_id = ? AND m.empresa_id = ?
            ORDER BY m.data_manutencao DESC
            LIMIT " . (int) $limit
        );
        $st->execute([$pneu_id, $empresa_id]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $m) {
            $items[] = [
                'id' => 'm-' . $m['id'],
                'tipo' => (stripos($m['tipo_manutencao'] ?? '', 'recap') !== false) ? 'recapagem' : 'manutencao',
                'data_movimentacao' => $m['data_manutencao'],
                'km_odometro' => $m['km_veiculo'],
                'custo' => $m['custo'],
                'observacoes' => ($m['tipo_manutencao'] ?? 'Manutenção') . ($m['observacoes'] ? ': ' . $m['observacoes'] : ''),
                'veiculo_placa' => $m['veiculo_placa'],
            ];
        }

        usort($items, static function ($a, $b) {
            return strcmp($b['data_movimentacao'] ?? '', $a['data_movimentacao'] ?? '');
        });

        return array_slice($items, 0, $limit);
    }
}

if (!function_exists('pneus_count_em_uso')) {
    function pneus_count_em_uso(PDO $conn, int $empresa_id): int
    {
        try {
            $st = $conn->prepare("
                SELECT COUNT(DISTINCT x.pneu_id) AS total FROM (
                    SELECT ip.pneu_id FROM instalacoes_pneus ip
                    INNER JOIN pneus p ON p.id = ip.pneu_id AND p.empresa_id = ?
                    WHERE ip.data_remocao IS NULL
                    UNION
                    SELECT apf.pneu_id FROM alocacoes_pneus_flexiveis apf
                    INNER JOIN pneus p ON p.id = apf.pneu_id AND p.empresa_id = ?
                    WHERE apf.ativo = 1
                    UNION
                    SELECT pa.pneu_id FROM pneus_alocacao pa
                    INNER JOIN pneus p ON p.id = pa.pneu_id AND p.empresa_id = ?
                    WHERE pa.status = 'alocado'
                    UNION
                    SELECT ep.pneu_id FROM eixo_pneus ep
                    INNER JOIN pneus p ON p.id = ep.pneu_id AND p.empresa_id = ?
                    WHERE ep.status = 'alocado'
                ) x
            ");
            $st->execute([$empresa_id, $empresa_id, $empresa_id, $empresa_id]);
            return (int) ($st->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        } catch (Throwable $e) {
            $st = $conn->prepare('SELECT COUNT(*) FROM pneus WHERE empresa_id = ? AND status_id = 1');
            $st->execute([$empresa_id]);
            return (int) $st->fetchColumn();
        }
    }
}

if (!function_exists('pneus_get_summary')) {
    function pneus_get_summary(PDO $conn, int $empresa_id): array
    {
        $st = $conn->prepare('SELECT COUNT(*) FROM pneus WHERE empresa_id = ?');
        $st->execute([$empresa_id]);
        $total = (int) $st->fetchColumn();

        $em_uso = pneus_count_em_uso($conn, $empresa_id);

        $idsDisp = pneus_status_ids_disponiveis($conn);
        $inPh = implode(',', array_fill(0, count($idsDisp), '?'));
        $naoAlocado = pneus_sql_nao_alocado('p.id');
        $sql = "SELECT COUNT(*) FROM pneus p WHERE p.empresa_id = ? AND p.status_id IN ({$inPh}) AND {$naoAlocado}";
        $st = $conn->prepare($sql);
        $st->execute(array_merge([$empresa_id], $idsDisp));
        $estoque = (int) $st->fetchColumn();

        $st = $conn->prepare('
            SELECT COUNT(*) FROM pneus
            WHERE empresa_id = ? AND sulco_inicial IS NOT NULL AND sulco_inicial <= 2
        ');
        $st->execute([$empresa_id]);
        $alerta_sulco = (int) $st->fetchColumn();

        $custo_mes = 0.0;
        $st = $conn->prepare("
            SELECT COALESCE(SUM(custo), 0) FROM pneu_manutencao
            WHERE empresa_id = ? AND MONTH(data_manutencao) = MONTH(CURDATE()) AND YEAR(data_manutencao) = YEAR(CURDATE())
        ");
        $st->execute([$empresa_id]);
        $custo_mes = (float) $st->fetchColumn();

        $custo_km_frota = null;
        $st = $conn->prepare('SELECT id FROM pneus WHERE empresa_id = ? ORDER BY updated_at DESC LIMIT 20');
        $st->execute([$empresa_id]);
        $soma = 0.0;
        $n = 0;
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $pid) {
            $ck = pneus_get_custo_km($conn, (int) $pid, $empresa_id);
            if ($ck['custo_km'] !== null) {
                $soma += $ck['custo_km'];
                $n++;
            }
        }
        if ($n > 0) {
            $custo_km_frota = round($soma / $n, 4);
        }

        return [
            'total_pneus' => $total,
            'em_uso' => $em_uso,
            'estoque_disponivel' => $estoque,
            'alerta_sulco' => $alerta_sulco,
            'custo_manutencao_mes' => round($custo_mes, 2),
            'custo_km_medio_frota' => $custo_km_frota,
            'iep' => $custo_km_frota > 0 ? round(1 / $custo_km_frota, 0) : null,
        ];
    }
}

if (!function_exists('pneus_previsao_compras')) {
    function pneus_previsao_compras(PDO $conn, int $empresa_id, int $dias = 90): array
    {
        $dias = max(30, min(365, $dias));
        $st = $conn->prepare("
            SELECT p.medida,
                COUNT(*) AS total,
                SUM(CASE WHEN p.sulco_inicial <= 2 OR LOWER(TRIM(sp.nome)) = 'descartado' THEN 1 ELSE 0 END) AS criticos
            FROM pneus p
            LEFT JOIN status_pneus sp ON sp.id = p.status_id
            WHERE p.empresa_id = ?
            GROUP BY p.medida
            HAVING total > 0
            ORDER BY criticos DESC, total DESC
        ");
        $st->execute([$empresa_id]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $idsDisp = pneus_status_ids_disponiveis($conn);
        $inPh = implode(',', array_fill(0, count($idsDisp), '?'));
        $naoAlocado = pneus_sql_nao_alocado('p.id');
        $stDisp = $conn->prepare("
            SELECT medida, COUNT(*) AS disponiveis FROM pneus p
            WHERE p.empresa_id = ? AND p.status_id IN ({$inPh}) AND {$naoAlocado}
            GROUP BY medida
        ");
        $stDisp->execute(array_merge([$empresa_id], $idsDisp));
        $dispMap = [];
        foreach ($stDisp->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $dispMap[$d['medida'] ?: ''] = (int) $d['disponiveis'];
        }

        $previsao = [];
        foreach ($rows as $r) {
            $medida = $r['medida'] ?: 'Não informada';
            $criticos = (int) $r['criticos'];
            $disp = $dispMap[$r['medida'] ?? ''] ?? 0;
            $faltam = max(0, $criticos - $disp);
            if ($faltam > 0 || $criticos > 0) {
                $previsao[] = [
                    'medida' => $medida,
                    'total_cadastro' => (int) $r['total'],
                    'disponiveis' => $disp,
                    'criticos_ou_descarte' => $criticos,
                    'sugestao_compra' => max($faltam, (int) ceil($criticos * ($dias / 180))),
                    'horizonte_dias' => $dias,
                ];
            }
        }
        return $previsao;
    }
}

if (!function_exists('pneus_ranking_recapagem')) {
    function pneus_ranking_recapagem(PDO $conn, int $empresa_id, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        try {
            $st = $conn->prepare("
                SELECT COALESCE(f.nome, 'Não informado') AS fornecedor,
                    COUNT(*) AS qtd,
                    ROUND(AVG(m.custo), 2) AS custo_medio,
                    ROUND(SUM(m.custo), 2) AS custo_total
                FROM pneu_manutencao m
                INNER JOIN tipo_manutencao_pneus t ON t.id = m.tipo_manutencao_id
                LEFT JOIN fornecedores f ON f.id = m.fornecedor_id
                WHERE m.empresa_id = ? AND LOWER(t.nome) LIKE '%recap%'
                GROUP BY f.id, f.nome
                ORDER BY qtd DESC
                LIMIT {$limit}
            ");
            $st->execute([$empresa_id]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            try {
                $st = $conn->prepare("
                    SELECT 'Recapagem' AS fornecedor, COUNT(*) AS qtd,
                        ROUND(AVG(m.custo), 2) AS custo_medio, ROUND(SUM(m.custo), 2) AS custo_total
                    FROM pneu_manutencao m
                    INNER JOIN tipo_manutencao_pneus t ON t.id = m.tipo_manutencao_id
                    WHERE m.empresa_id = ? AND LOWER(t.nome) LIKE '%recap%'
                ");
                $st->execute([$empresa_id]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                return $row && (int) $row['qtd'] > 0 ? [$row] : [];
            } catch (Throwable $e2) {
                error_log('pneus_ranking_recapagem: ' . $e2->getMessage());
                return [];
            }
        }
    }
}

if (!function_exists('pneus_get_executive')) {
    function pneus_get_executive(PDO $conn, int $empresa_id): array
    {
        $summary = pneus_get_summary($conn, $empresa_id);

        $st = $conn->prepare("
            SELECT DATE_FORMAT(data_manutencao, '%Y-%m') AS mes,
                   ROUND(SUM(custo), 2) AS custo
            FROM pneu_manutencao
            WHERE empresa_id = ? AND data_manutencao >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(data_manutencao, '%Y-%m')
            ORDER BY mes
        ");
        $st->execute([$empresa_id]);
        $custo_mensal = $st->fetchAll(PDO::FETCH_ASSOC);

        $st = $conn->prepare("
            SELECT v.placa, COUNT(DISTINCT m.pneu_id) AS pneus_manut, ROUND(SUM(m.custo), 2) AS custo
            FROM pneu_manutencao m
            INNER JOIN veiculos v ON v.id = m.veiculo_id AND v.empresa_id = m.empresa_id
            WHERE m.empresa_id = ? AND m.data_manutencao >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY v.id, v.placa
            ORDER BY custo DESC
            LIMIT 8
        ");
        $st->execute([$empresa_id]);
        $top_veiculos = $st->fetchAll(PDO::FETCH_ASSOC);

        $st = $conn->prepare("
            SELECT sp.nome AS status, COUNT(*) AS qtd
            FROM pneus p
            LEFT JOIN status_pneus sp ON sp.id = p.status_id
            WHERE p.empresa_id = ?
            GROUP BY sp.id, sp.nome
            ORDER BY qtd DESC
        ");
        $st->execute([$empresa_id]);
        $por_status = $st->fetchAll(PDO::FETCH_ASSOC);

        return [
            'summary' => $summary,
            'custo_mensal' => $custo_mensal,
            'top_veiculos_custo' => $top_veiculos,
            'por_status' => $por_status,
            'previsao_compras' => pneus_previsao_compras($conn, $empresa_id, 90),
            'ranking_recapagem' => pneus_ranking_recapagem($conn, $empresa_id),
            'ranking_marcas' => pneus_ranking_marcas($conn, $empresa_id),
            'financeiro' => pneus_get_financeiro_resumo($conn, $empresa_id),
        ];
    }
}

if (!function_exists('pneus_get_financeiro_resumo')) {
    function pneus_get_financeiro_resumo(PDO $conn, int $empresa_id): array
    {
        $compra = 0.0;
        if (pneus_column_exists($conn, 'pneus', 'valor_compra')) {
            $st = $conn->prepare('SELECT COALESCE(SUM(valor_compra), 0) FROM pneus WHERE empresa_id = ?');
            $st->execute([$empresa_id]);
            $compra = (float) $st->fetchColumn();
        }
        $st = $conn->prepare('SELECT COALESCE(SUM(custo), 0) FROM pneu_manutencao WHERE empresa_id = ?');
        $st->execute([$empresa_id]);
        $manut = (float) $st->fetchColumn();
        $recap = 0.0;
        try {
            $st = $conn->prepare("
                SELECT COALESCE(SUM(m.custo), 0) FROM pneu_manutencao m
                INNER JOIN tipo_manutencao_pneus t ON t.id = m.tipo_manutencao_id
                WHERE m.empresa_id = ? AND LOWER(t.nome) LIKE '%recap%'
            ");
            $st->execute([$empresa_id]);
            $recap = (float) $st->fetchColumn();
        } catch (Throwable $e) {
        }
        return [
            'investimento_compra' => round($compra, 2),
            'custo_manutencao_total' => round($manut, 2),
            'custo_recapagens' => round($recap, 2),
            'custo_vida_total' => round($compra + $manut, 2),
        ];
    }
}

if (!function_exists('pneus_build_insights_payload')) {
    function pneus_build_insights_payload(PDO $conn, int $empresa_id): array
    {
        $summary = pneus_get_summary($conn, $empresa_id);
        $qualidade = [];
        $q_erros = 0;
        $q_avisos = 0;

        if ($summary['alerta_sulco'] > 0) {
            $qualidade[] = [
                'id' => 1,
                'nome' => 'Sulco crítico (≤ 2 mm)',
                'severidade' => 'erro',
                'motivos' => [['msg' => $summary['alerta_sulco'] . ' pneu(s) com sulco no limite legal ou abaixo.']],
            ];
            $q_erros++;
        }

        $previsao = pneus_previsao_compras($conn, $empresa_id, 90);
        foreach (array_slice($previsao, 0, 5) as $i => $p) {
            if ((int) $p['sugestao_compra'] <= 0) {
                continue;
            }
            $qualidade[] = [
                'id' => 10 + $i,
                'nome' => 'Reposição medida ' . $p['medida'],
                'severidade' => 'aviso',
                'motivos' => [[
                    'msg' => 'Sugestão: comprar ' . $p['sugestao_compra'] . ' pneu(s) nos próximos 90 dias (' . $p['disponiveis'] . ' disponível(is), ' . $p['criticos_ou_descarte'] . ' crítico(s)).',
                ]],
            ];
            $q_avisos++;
        }

        $st = $conn->prepare("
            SELECT p.id, p.numero_serie, p.medida
            FROM pneus p
            WHERE p.empresa_id = ? AND " . pneus_sql_nao_alocado('p.id') . "
              AND p.status_id IN (SELECT id FROM status_pneus WHERE LOWER(nome) = 'em uso')
            LIMIT 5
        ");
        try {
            $st->execute([$empresa_id]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $i => $row) {
                $qualidade[] = [
                    'id' => 30 + $i,
                    'nome' => 'Inconsistência: ' . $row['numero_serie'],
                    'severidade' => 'aviso',
                    'motivos' => [['msg' => 'Status “em uso” mas sem alocação ativa — revisar cadastro.']],
                ];
                $q_avisos++;
            }
        } catch (Throwable $e) {
            // ignora
        }

        if ($summary['estoque_disponivel'] < 2 && $summary['em_uso'] > 5) {
            $qualidade[] = [
                'id' => 50,
                'nome' => 'Estoque baixo',
                'severidade' => 'aviso',
                'motivos' => [['msg' => 'Apenas ' . $summary['estoque_disponivel'] . ' pneu(s) disponível(is) para ' . $summary['em_uso'] . ' em uso.']],
            ];
            $q_avisos++;
        }

        if (file_exists(__DIR__ . '/pneus_mercado_helpers.php')) {
            require_once __DIR__ . '/pneus_mercado_helpers.php';
            $dot = pneus_dot_alertas_frota($conn, $empresa_id);
            $vencidos = count($dot['vencido'] ?? []);
            $atencao = count($dot['atencao_5anos'] ?? []);
            if ($vencidos > 0) {
                $qualidade[] = [
                    'id' => 60,
                    'nome' => 'DOT — pneus vencidos (7+ anos)',
                    'severidade' => 'erro',
                    'motivos' => [['msg' => $vencidos . ' pneu(s) com DOT indicando mais de 7 anos.']],
                ];
                $q_erros++;
            }
            if ($atencao > 0) {
                $qualidade[] = [
                    'id' => 61,
                    'nome' => 'DOT — atenção (5+ anos)',
                    'severidade' => 'aviso',
                    'motivos' => [['msg' => $atencao . ' pneu(s) com DOT acima de 5 anos.']],
                ];
                $q_avisos++;
            }
            $prev60 = pneus_previsao_compra_ia($conn, $empresa_id, 60);
            if (($prev60['quantidade_sugerida'] ?? 0) > 0) {
                $qualidade[] = [
                    'id' => 62,
                    'nome' => 'Previsão de compra (60 dias)',
                    'severidade' => 'aviso',
                    'motivos' => [['msg' => $prev60['mensagem_ia']]],
                ];
                $q_avisos++;
            }
            $abast = pneus_abastecimento_insights($conn, $empresa_id);
            foreach (array_slice($abast, 0, 3) as $i => $ins) {
                $qualidade[] = [
                    'id' => 70 + $i,
                    'nome' => 'Consumo × pneus — ' . ($ins['placa'] ?? ''),
                    'severidade' => 'aviso',
                    'motivos' => array_map(static fn ($a) => ['msg' => $a], $ins['alertas'] ?? []),
                ];
                $q_avisos++;
            }
        }

        return [
            'success' => true,
            'q_erros' => $q_erros,
            'q_avisos' => $q_avisos,
            'qualidade' => $qualidade,
            'summary' => $summary,
            'previsao_compras' => $previsao,
            'custo_km_medio' => $summary['custo_km_medio_frota'],
            'iep' => $summary['iep'],
        ];
    }
}

if (!function_exists('pneus_get_medicoes_sulco')) {
    function pneus_get_medicoes_sulco(PDO $conn, int $pneu_id, int $empresa_id, int $limit = 30): array
    {
        if (!pneus_validar_pneu_empresa($conn, $pneu_id, $empresa_id)) {
            return [];
        }
        try {
            $st = $conn->prepare(
                'SELECT ms.id, ms.sulco_mm, ms.km_veiculo, ms.data_medicao, ms.observacoes, v.placa AS veiculo_placa
                 FROM pneu_medicoes_sulco ms
                 LEFT JOIN veiculos v ON v.id = ms.veiculo_id
                 WHERE ms.pneu_id = ? AND ms.empresa_id = ?
                 ORDER BY ms.data_medicao DESC, ms.id DESC
                 LIMIT ' . (int) $limit
            );
            $st->execute([$pneu_id, $empresa_id]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('pneus_registrar_medicao_sulco')) {
    function pneus_registrar_medicao_sulco(PDO $conn, int $empresa_id, array $data): array
    {
        require_once __DIR__ . '/pneu_movimentacoes_helper.php';

        $pneu_id = (int) ($data['pneu_id'] ?? 0);
        $sulco = (float) ($data['sulco_mm'] ?? 0);
        if ($pneu_id <= 0 || $sulco <= 0) {
            return ['success' => false, 'error' => 'pneu_id e sulco_mm obrigatórios'];
        }
        if (!pneus_validar_pneu_empresa($conn, $pneu_id, $empresa_id)) {
            return ['success' => false, 'error' => 'Pneu não encontrado'];
        }

        $veiculo_id = isset($data['veiculo_id']) ? (int) $data['veiculo_id'] : pneus_get_veiculo_alocado($conn, $pneu_id, $empresa_id);
        $km = isset($data['km_veiculo']) ? (int) $data['km_veiculo'] : null;
        if ($km === null && $veiculo_id) {
            $km = pneus_get_veiculo_km($conn, $veiculo_id, $empresa_id);
        }
        $obs = trim((string) ($data['observacoes'] ?? ''));
        $usuario_id = isset($_SESSION['id']) ? (int) $_SESSION['id'] : null;

        $conn->beginTransaction();
        try {
            $st = $conn->prepare(
                'INSERT INTO pneu_medicoes_sulco (empresa_id, pneu_id, sulco_mm, km_veiculo, veiculo_id, observacoes, usuario_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $st->execute([$empresa_id, $pneu_id, $sulco, $km, $veiculo_id ?: null, $obs ?: null, $usuario_id]);
            pneus_atualizar_sulco_registro($conn, $pneu_id, $sulco);
            pneu_movimentacao_inserir($conn, [
                'empresa_id' => $empresa_id,
                'pneu_id' => $pneu_id,
                'tipo' => 'manutencao',
                'veiculo_id' => $veiculo_id,
                'km_odometro' => $km,
                'sulco_mm' => $sulco,
                'observacoes' => 'Medição de sulco (TWI)' . ($obs ? ': ' . $obs : ''),
            ]);
            $conn->commit();
            return ['success' => true, 'sulco_mm' => $sulco, 'previsao' => pneus_previsao_troca_sulco($conn, $pneu_id, $empresa_id)];
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log('pneus_registrar_medicao_sulco: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Erro ao registrar medição'];
        }
    }
}

if (!function_exists('pneus_previsao_troca_sulco')) {
    /** Estima km e dias até sulco mínimo (1.6 mm) com base nas últimas medições. */
    function pneus_previsao_troca_sulco(PDO $conn, int $pneu_id, int $empresa_id, float $sulco_min = 1.6): array
    {
        $medicoes = pneus_get_medicoes_sulco($conn, $pneu_id, $empresa_id, 10);
        $sulco_atual = pneus_get_sulco_atual($conn, $pneu_id);
        if ($sulco_atual === null) {
            return ['sulco_atual_mm' => null, 'dias_restantes' => null, 'km_restantes' => null, 'alerta' => 'sem_dados'];
        }
        if ($sulco_atual <= $sulco_min) {
            return ['sulco_atual_mm' => $sulco_atual, 'dias_restantes' => 0, 'km_restantes' => 0, 'alerta' => 'troca_imediata'];
        }
        $taxa_mm_km = null;
        if (count($medicoes) >= 2) {
            $a = $medicoes[1];
            $b = $medicoes[0];
            $km_a = (int) ($a['km_veiculo'] ?? 0);
            $km_b = (int) ($b['km_veiculo'] ?? 0);
            $sulco_a = (float) $a['sulco_mm'];
            $sulco_b = (float) $b['sulco_mm'];
            $dkm = $km_b - $km_a;
            $dsulco = $sulco_a - $sulco_b;
            if ($dkm > 0 && $dsulco > 0) {
                $taxa_mm_km = $dsulco / $dkm;
            }
        }
        $km_restantes = null;
        if ($taxa_mm_km) {
            $km_restantes = (int) floor(($sulco_atual - $sulco_min) / $taxa_mm_km);
        }
        $dias = $km_restantes !== null ? (int) max(1, round($km_restantes / 150)) : null;
        return [
            'sulco_atual_mm' => $sulco_atual,
            'km_restantes' => $km_restantes,
            'dias_restantes' => $dias,
            'alerta' => ($sulco_atual <= 3) ? 'atencao' : 'ok',
        ];
    }
}

if (!function_exists('pneus_sync_km_pneu')) {
    /** Atualiza km do pneu a partir do odômetro do veículo alocado. */
    function pneus_sync_km_pneu(PDO $conn, int $pneu_id, int $empresa_id): array
    {
        $veiculo_id = pneus_get_veiculo_alocado($conn, $pneu_id, $empresa_id);
        if (!$veiculo_id) {
            return ['success' => false, 'error' => 'Pneu não está alocado em veículo'];
        }
        $km_veiculo = pneus_get_veiculo_km($conn, $veiculo_id, $empresa_id);
        if ($km_veiculo === null) {
            return ['success' => false, 'error' => 'Veículo sem KM cadastrado'];
        }
        $st = $conn->prepare('SELECT km_instalacao, km_rodado_total FROM pneus WHERE id = ? AND empresa_id = ?');
        $st->execute([$pneu_id, $empresa_id]);
        $p = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $km_inst = (int) ($p['km_instalacao'] ?? 0);
        $km_rodado = max(0, $km_veiculo - $km_inst);
        $sets = ['km_instalacao = CASE WHEN km_instalacao IS NULL OR km_instalacao = 0 THEN ? ELSE km_instalacao END', 'updated_at = NOW()'];
        $params = [$km_veiculo, $pneu_id, $empresa_id];
        if (pneus_column_exists($conn, 'pneus', 'km_rodado_total')) {
            $sets[] = 'km_rodado_total = ?';
            array_splice($params, 2, 0, [$km_rodado]);
        }
        $sql = 'UPDATE pneus SET ' . implode(', ', $sets) . ' WHERE id = ? AND empresa_id = ?';
        $st = $conn->prepare($sql);
        $st->execute($params);
        return [
            'success' => true,
            'veiculo_id' => $veiculo_id,
            'km_veiculo' => $km_veiculo,
            'km_rodado' => $km_rodado,
        ];
    }
}

if (!function_exists('pneus_ranking_marcas')) {
    function pneus_ranking_marcas(PDO $conn, int $empresa_id, int $limit = 15): array
    {
        $limit = max(1, min(50, $limit));
        $hasKmTotal = pneus_column_exists($conn, 'pneus', 'km_rodado_total');
        $hasValor = pneus_column_exists($conn, 'pneus', 'valor_compra');
        $kmExpr = $hasKmTotal
            ? 'ROUND(AVG(NULLIF(p.km_rodado_total, 0)), 0)'
            : 'ROUND(AVG(NULLIF(p.km_instalacao, 0)), 0)';
        $valorExpr = $hasValor ? 'ROUND(AVG(NULLIF(p.valor_compra, 0)), 2)' : 'NULL';
        $st = $conn->prepare("
            SELECT COALESCE(NULLIF(TRIM(p.marca), ''), 'Não informada') AS marca,
                   COUNT(*) AS total_pneus,
                   {$kmExpr} AS km_medio,
                   {$valorExpr} AS valor_medio_compra
            FROM pneus p
            WHERE p.empresa_id = ?
            GROUP BY COALESCE(NULLIF(TRIM(p.marca), ''), 'Não informada')
            HAVING total_pneus > 0
            ORDER BY km_medio DESC, total_pneus DESC
            LIMIT {$limit}
        ");
        $st->execute([$empresa_id]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('pneus_get_ficha')) {
    function pneus_get_ficha(PDO $conn, int $pneu_id, int $empresa_id): ?array
    {
        if (!pneus_validar_pneu_empresa($conn, $pneu_id, $empresa_id)) {
            return null;
        }
        $st = $conn->prepare(
            'SELECT p.*, sp.nome AS status_nome FROM pneus p
             LEFT JOIN status_pneus sp ON sp.id = p.status_id
             WHERE p.id = ? AND p.empresa_id = ? LIMIT 1'
        );
        $st->execute([$pneu_id, $empresa_id]);
        $pneu = $st->fetch(PDO::FETCH_ASSOC);
        if (!$pneu) {
            return null;
        }
        $veiculo_id = pneus_get_veiculo_alocado($conn, $pneu_id, $empresa_id);
        $placa = null;
        if ($veiculo_id) {
            $sv = $conn->prepare('SELECT placa FROM veiculos WHERE id = ? AND empresa_id = ?');
            $sv->execute([$veiculo_id, $empresa_id]);
            $placa = $sv->fetchColumn() ?: null;
        }
        $token = pneus_ensure_qr_token($conn, $pneu_id, $empresa_id);
        $mercado = [];
        if (file_exists(__DIR__ . '/pneus_mercado_helpers.php')) {
            require_once __DIR__ . '/pneus_mercado_helpers.php';
            $mercado = [
                'carcaca' => pneus_carcaca_resumo($conn, $pneu_id, $empresa_id),
                'anexos' => pneus_anexos_listar($conn, $pneu_id, $empresa_id),
                'garantias' => pneus_garantias_listar($conn, $pneu_id, $empresa_id),
                'dot' => pneus_dot_parse($pneu['dot'] ?? null),
            ];
        }
        return [
            'pneu' => $pneu,
            'veiculo_id' => $veiculo_id,
            'veiculo_placa' => $placa,
            'sulco_atual_mm' => pneus_get_sulco_atual($conn, $pneu_id),
            'medicoes_sulco' => pneus_get_medicoes_sulco($conn, $pneu_id, $empresa_id, 20),
            'previsao_sulco' => pneus_previsao_troca_sulco($conn, $pneu_id, $empresa_id),
            'custo_km' => pneus_get_custo_km($conn, $pneu_id, $empresa_id),
            'timeline' => pneus_get_timeline($conn, $pneu_id, $empresa_id, 40),
            'qr_token' => $token,
            'mercado' => $mercado,
        ];
    }
}

if (!function_exists('pneus_ia_responder')) {
    /** Respostas determinísticas para perguntas frequentes (complementa LLM). */
    function pneus_ia_responder(PDO $conn, int $empresa_id, string $pergunta): array
    {
        $q = mb_strtolower(trim($pergunta), 'UTF-8');
        $summary = pneus_get_summary($conn, $empresa_id);

        if (preg_match('/custo|mais caro|gastou/', $q)) {
            $st = $conn->prepare('SELECT p.id, p.numero_serie, p.marca FROM pneus p WHERE p.empresa_id = ? ORDER BY p.updated_at DESC LIMIT 30');
            $st->execute([$empresa_id]);
            $top = null;
            $max = 0;
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $ck = pneus_get_custo_km($conn, (int) $row['id'], $empresa_id);
                $c = (float) ($ck['custo_total'] ?? 0);
                if ($c > $max) {
                    $max = $c;
                    $top = $row;
                }
            }
            if ($top) {
                return [
                    'resposta' => sprintf(
                        'O pneu %s (%s) acumula o maior custo registrado entre os analisados: R$ %s.',
                        $top['numero_serie'],
                        $top['marca'] ?? '',
                        number_format($max, 2, ',', '.')
                    ),
                    'fonte' => 'custo_movimentacoes',
                ];
            }
        }

        if (preg_match('/marca|desempenho|melhor/', $q)) {
            $rank = pneus_ranking_marcas($conn, $empresa_id, 5);
            if ($rank) {
                $linhas = array_map(static function ($r) {
                    return ($r['marca'] ?? '') . ' — KM médio ' . number_format((int) ($r['km_medio'] ?? 0), 0, '', '.');
                }, $rank);
                return ['resposta' => "Ranking de marcas (KM médio):\n• " . implode("\n• ", $linhas), 'fonte' => 'ranking_marcas'];
            }
        }

        if (preg_match('/troc|30 dias|próxim|sulco/', $q)) {
            $st = $conn->prepare('SELECT id, numero_serie FROM pneus WHERE empresa_id = ? LIMIT 50');
            $st->execute([$empresa_id]);
            $criticos = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $prev = pneus_previsao_troca_sulco($conn, (int) $row['id'], $empresa_id);
                if (($prev['alerta'] ?? '') === 'troca_imediata' || (($prev['dias_restantes'] ?? 999) <= 30)) {
                    $criticos[] = $row['numero_serie'] . ' (sulco ' . ($prev['sulco_atual_mm'] ?? '?') . ' mm)';
                }
            }
            if ($criticos) {
                return ['resposta' => 'Pneus com troca prevista em até 30 dias ou sulco crítico: ' . implode(', ', array_slice($criticos, 0, 10)), 'fonte' => 'previsao_sulco'];
            }
            return ['resposta' => 'Nenhum pneu com previsão crítica de troca nos próximos 30 dias (com base nas medições de sulco).', 'fonte' => 'previsao_sulco'];
        }

        if (preg_match('/veículo|veiculo|consumo|acima/', $q)) {
            $st = $conn->prepare("
                SELECT v.placa, COUNT(DISTINCT m.pneu_id) AS pneus, ROUND(SUM(m.custo), 2) AS custo
                FROM pneu_manutencao m
                INNER JOIN veiculos v ON v.id = m.veiculo_id AND v.empresa_id = m.empresa_id
                WHERE m.empresa_id = ? AND m.data_manutencao >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY v.id, v.placa ORDER BY custo DESC LIMIT 5
            ");
            $st->execute([$empresa_id]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                $linhas = array_map(static fn ($r) => $r['placa'] . ' — R$ ' . number_format((float) $r['custo'], 2, ',', '.'), $rows);
                return ['resposta' => "Veículos com maior custo de pneus (6 meses):\n• " . implode("\n• ", $linhas), 'fonte' => 'manutencao_veiculo'];
            }
        }

        return [
            'resposta' => sprintf(
                'Resumo da frota: %d pneus, %d em uso, %d em estoque, %d alerta de sulco. Custo/km médio: %s.',
                $summary['total_pneus'],
                $summary['em_uso'],
                $summary['estoque_disponivel'],
                $summary['alerta_sulco'],
                $summary['custo_km_medio_frota'] !== null ? ('R$ ' . number_format($summary['custo_km_medio_frota'], 4, ',', '.')) : 'sem dados'
            ),
            'fonte' => 'summary',
        ];
    }
}
