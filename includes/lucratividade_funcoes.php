<?php
/**
 * Funções de dados para a página de Lucratividade.
 * Pode ser incluído por páginas ou APIs que precisem dos mesmos KPIs (ex.: relatórios).
 * Não usa globals; $conn, $empresa_id, $mes, $ano são passados por parâmetro.
 */

if (!function_exists('getKPIsOptimized')) {
function getKPIsOptimized($conn, $empresa_id, $mes, $ano) {
    try {
        $sql = "
        SELECT 
            DATE_FORMAT(r.data_rota, '%Y-%m') AS mes_ano,
            COALESCE(SUM(r.frete), 0) AS total_frete,
            COALESCE(SUM(r.comissao), 0) AS total_comissao,
            (
                SELECT COALESCE(SUM(total_despviagem), 0)
                FROM despesas_viagem dv
                WHERE dv.rota_id IN (
                    SELECT id FROM rotas r2 
                    WHERE r2.empresa_id = ? AND ((MONTH(r2.data_rota) = ? AND YEAR(r2.data_rota) = ?) OR (MONTH(r2.data_saida) = ? AND YEAR(r2.data_saida) = ?))
                )
            ) AS total_despesas_viagem,
            (
                SELECT COALESCE(SUM(valor_total), 0)
                FROM abastecimentos a
                WHERE a.empresa_id = ? AND MONTH(a.data_abastecimento) = ? AND YEAR(a.data_abastecimento) = ?
            ) AS total_abastecimentos,
            (
                SELECT COALESCE(SUM(df.valor), 0)
                FROM despesas_fixas df
                WHERE df.empresa_id = ? AND df.status_pagamento_id = 2
                  AND MONTH(df.data_pagamento) = ? AND YEAR(df.data_pagamento) = ?
            ) AS total_despesas_fixas,
            (
                SELECT COALESCE(SUM(pf.valor), 0)
                FROM parcelas_financiamento pf
                WHERE pf.empresa_id = ? AND pf.status_id = 2
                  AND MONTH(pf.data_pagamento) = ? AND YEAR(pf.data_pagamento) = ?
            ) AS total_parcelas_financiamento,
            (
                SELECT COALESCE(SUM(cp.valor), 0)
                FROM contas_pagar cp
                WHERE cp.empresa_id = ? AND cp.status_id = 2
                  AND MONTH(cp.data_pagamento) = ? AND YEAR(cp.data_pagamento) = ?
            ) AS total_contas_pagas,
            (
                SELECT COALESCE(SUM(m.valor), 0)
                FROM manutencoes m
                WHERE m.empresa_id = ? AND MONTH(m.data_manutencao) = ? AND YEAR(m.data_manutencao) = ?
            ) AS total_manutencoes,
            (
                SELECT COALESCE(SUM(pm.custo), 0)
                FROM pneu_manutencao pm
                WHERE pm.empresa_id = ? AND MONTH(pm.data_manutencao) = ? AND YEAR(pm.data_manutencao) = ?
            ) AS total_pneu_manutencao
        FROM rotas r
        WHERE r.empresa_id = ? AND ((MONTH(r.data_rota) = ? AND YEAR(r.data_rota) = ?) OR (MONTH(r.data_saida) = ? AND YEAR(r.data_saida) = ?))
        GROUP BY DATE_FORMAT(COALESCE(r.data_rota, r.data_saida), '%Y-%m')
        ";
        $params = [
            $empresa_id, $mes, $ano, $mes, $ano, // despesas_viagem com data_rota/data_saida
            $empresa_id, $mes, $ano,              // abastecimentos
            $empresa_id, $mes, $ano,              // despesas_fixas
            $empresa_id, $mes, $ano,              // parcelas_financiamento
            $empresa_id, $mes, $ano,              // contas_pagar
            $empresa_id, $mes, $ano,              // manutencoes
            $empresa_id, $mes, $ano,              // pneu_manutencao
            $empresa_id, $mes, $ano, $mes, $ano   // rotas mes/ano flexível
        ];
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $receita = floatval($result['total_frete'] ?? 0);
            $despesas = floatval($result['total_comissao'] ?? 0) + floatval($result['total_abastecimentos'] ?? 0)
                + floatval($result['total_despesas_viagem'] ?? 0) + floatval($result['total_despesas_fixas'] ?? 0)
                + floatval($result['total_parcelas_financiamento'] ?? 0) + floatval($result['total_contas_pagas'] ?? 0)
                + floatval($result['total_manutencoes'] ?? 0) + floatval($result['total_pneu_manutencao'] ?? 0);
            $result['lucro_liquido'] = $receita - $despesas;
        }
        return $result;
    } catch (Exception $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) error_log("getKPIsOptimized: " . $e->getMessage());
        return null;
    }
}
}

if (!function_exists('getIntelligentAlerts')) {
function getIntelligentAlerts($conn, $empresa_id, $mes, $ano) {
    try {
        $alerts = [];
        $kpis = getKPIsOptimized($conn, $empresa_id, $mes, $ano);
        if ($kpis !== null && is_array($kpis)) {
            $lucro = isset($kpis['lucro_liquido']) ? floatval($kpis['lucro_liquido']) : 0;
            $receita = isset($kpis['total_frete']) ? floatval($kpis['total_frete']) : 0;
            $abastecimentos = isset($kpis['total_abastecimentos']) ? floatval($kpis['total_abastecimentos']) : 0;
            if ($receita > 0) {
                $margem = ($lucro / $receita) * 100;
                if ($margem < 10) {
                    $alerts[] = ['type' => 'warning', 'title' => 'Margem Baixa', 'message' => "Margem de lucro está em " . number_format($margem, 1) . "%. Considere revisar custos.", 'icon' => 'fas fa-exclamation-triangle'];
                } elseif ($margem >= 30) {
                    $alerts[] = ['type' => 'success', 'title' => 'Margem Excelente', 'message' => "Margem de lucro está em " . number_format($margem, 1) . "%. Parabéns!", 'icon' => 'fas fa-check-circle'];
                }
            }
            if ($lucro < 0) {
                $alerts[] = ['type' => 'danger', 'title' => 'Prejuízo Detectado', 'message' => "O mês está fechando com prejuízo de R$ " . number_format(abs($lucro), 2, ',', '.') . ".", 'icon' => 'fas fa-times-circle'];
            } elseif ($lucro > 0 && $receita > 0) {
                $alerts[] = ['type' => 'success', 'title' => 'Lucro Positivo', 'message' => "O mês está fechando com lucro de R$ " . number_format($lucro, 2, ',', '.') . ".", 'icon' => 'fas fa-check-circle'];
            }
            if ($receita > 0 && $abastecimentos > 0) {
                $pct = ($abastecimentos / $receita) * 100;
                if ($pct > 40) $alerts[] = ['type' => 'info', 'title' => 'Alto Consumo de Combustível', 'message' => "Combustível representa " . number_format($pct, 1) . "% da receita.", 'icon' => 'fas fa-gas-pump'];
                elseif ($pct < 25) $alerts[] = ['type' => 'success', 'title' => 'Eficiência de Combustível', 'message' => "Combustível representa apenas " . number_format($pct, 1) . "% da receita.", 'icon' => 'fas fa-check-circle'];
            }
        }
        $mes_ant = $mes == 1 ? 12 : $mes - 1;
        $ano_ant = $mes == 1 ? $ano - 1 : $ano;
        $kpis_ant = getKPIsOptimized($conn, $empresa_id, $mes_ant, $ano_ant);
        if ($kpis_ant !== null && is_array($kpis_ant) && $kpis !== null && is_array($kpis)) {
            $lucro_ant = floatval($kpis_ant['lucro_liquido'] ?? 0);
            $lucro_atual = floatval($kpis['lucro_liquido'] ?? 0);
            if ($lucro_ant != 0) {
                $cresc = (($lucro_atual - $lucro_ant) / abs($lucro_ant)) * 100;
                if ($cresc < -20) $alerts[] = ['type' => 'warning', 'title' => 'Queda na Lucratividade', 'message' => "Lucro caiu " . number_format(abs($cresc), 1) . "% em relação ao mês anterior.", 'icon' => 'fas fa-chart-line'];
                elseif ($cresc > 20) $alerts[] = ['type' => 'success', 'title' => 'Crescimento na Lucratividade', 'message' => "Lucro aumentou " . number_format($cresc, 1) . "% em relação ao mês anterior!", 'icon' => 'fas fa-chart-line'];
            } elseif ($lucro_ant <= 0 && $lucro_atual > 0) {
                $alerts[] = ['type' => 'success', 'title' => 'Recuperação Financeira', 'message' => "Lucro positivo após período negativo. Parabéns!", 'icon' => 'fas fa-chart-line'];
            }
        }
        return $alerts;
    } catch (Exception $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) error_log("getIntelligentAlerts: " . $e->getMessage());
        return [];
    }
}
}

if (!function_exists('getAdvancedKPIs')) {
function getAdvancedKPIs($conn, $empresa_id, $mes, $ano, $kpis) {
    try {
        $advanced = [];
        $lucro = isset($kpis['lucro_liquido']) ? floatval($kpis['lucro_liquido']) : 0;
        $receita = isset($kpis['total_frete']) ? floatval($kpis['total_frete']) : 0;
        $investimento_total = 0;
        try {
            $stmt = $conn->prepare("SELECT COALESCE(SUM(valor_total), 0) as total FROM financiamentos WHERE empresa_id = ?");
            $stmt->execute([$empresa_id]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            $investimento_total = floatval($r['total'] ?? 0);
        } catch (Exception $e) {}
        $advanced['roi'] = $investimento_total > 0 ? ($lucro / $investimento_total) * 100 : 0;
        $total_rotas = 0;
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) as total_rotas FROM rotas WHERE empresa_id = ? AND ((MONTH(data_rota) = ? AND YEAR(data_rota) = ?) OR (MONTH(data_saida) = ? AND YEAR(data_saida) = ?))");
            $stmt->execute([$empresa_id, $mes, $ano, $mes, $ano]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_rotas = intval($r['total_rotas'] ?? 0);
        } catch (Exception $e) {}
        $advanced['ticket_medio'] = $total_rotas > 0 ? $receita / $total_rotas : 0;
        $total_km = 0;
        try {
            $stmt = $conn->prepare("SELECT COALESCE(SUM(distancia_km), 0) as total_km FROM rotas WHERE empresa_id = ? AND ((MONTH(data_rota) = ? AND YEAR(data_rota) = ?) OR (MONTH(data_saida) = ? AND YEAR(data_saida) = ?))");
            $stmt->execute([$empresa_id, $mes, $ano, $mes, $ano]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_km = floatval($r['total_km'] ?? 0);
        } catch (Exception $e) {
            try {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(quilometragem), 0) as total_km FROM rotas WHERE empresa_id = ? AND ((MONTH(data_rota) = ? AND YEAR(data_rota) = ?) OR (MONTH(data_saida) = ? AND YEAR(data_saida) = ?))");
                $stmt->execute([$empresa_id, $mes, $ano, $mes, $ano]);
                $r = $stmt->fetch(PDO::FETCH_ASSOC);
                $total_km = floatval($r['total_km'] ?? 0);
            } catch (Exception $e2) {}
        }
        $despesas = floatval($kpis['total_abastecimentos'] ?? 0) + floatval($kpis['total_despesas_viagem'] ?? 0) + floatval($kpis['total_despesas_fixas'] ?? 0) + floatval($kpis['total_manutencoes'] ?? 0);
        $advanced['custo_km'] = $total_km > 0 ? $despesas / $total_km : 0;
        $advanced['margem_operacional'] = $receita > 0 ? ($lucro / $receita) * 100 : 0;
        $rotas_carga = 0;
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) as c FROM rotas WHERE empresa_id = ? AND ((MONTH(data_rota) = ? AND YEAR(data_rota) = ?) OR (MONTH(data_saida) = ? AND YEAR(data_saida) = ?)) AND frete > 0");
            $stmt->execute([$empresa_id, $mes, $ano, $mes, $ano]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            $rotas_carga = intval($r['c'] ?? 0);
        } catch (Exception $e) {}
        $advanced['taxa_ocupacao'] = $total_rotas > 0 ? ($rotas_carga / $total_rotas) * 100 : 0;
        return $advanced;
    } catch (Exception $e) {
        return ['roi' => 0, 'ticket_medio' => 0, 'custo_km' => 0, 'margem_operacional' => 0, 'taxa_ocupacao' => 0];
    }
}
}

if (!function_exists('getRankings')) {
function getRankings($conn, $empresa_id, $mes, $ano) {
    try {
        $rankings = ['veiculos' => [], 'motoristas' => [], 'clientes' => [], 'tipos_frete' => []];
        $stmt = $conn->prepare("
            SELECT v.placa, v.modelo,
                COALESCE(SUM(r.frete), 0) as receita, COALESCE(SUM(r.comissao), 0) as comissao,
                COALESCE((SELECT SUM(a2.valor_total) FROM abastecimentos a2 WHERE a2.rota_id IN (SELECT r2.id FROM rotas r2 WHERE r2.veiculo_id = v.id AND r2.empresa_id = ? AND ((MONTH(r2.data_rota) = ? AND YEAR(r2.data_rota) = ?) OR (MONTH(r2.data_saida) = ? AND YEAR(r2.data_saida) = ?))) AND a2.empresa_id = ?), 0) as custo_abastecimento,
                COALESCE((SELECT SUM(dv2.total_despviagem) FROM despesas_viagem dv2 WHERE dv2.rota_id IN (SELECT r3.id FROM rotas r3 WHERE r3.veiculo_id = v.id AND r3.empresa_id = ? AND ((MONTH(r3.data_rota) = ? AND YEAR(r3.data_rota) = ?) OR (MONTH(r3.data_saida) = ? AND YEAR(r3.data_saida) = ?))) AND dv2.empresa_id = ?), 0) as despesas_viagem,
                COALESCE((SELECT SUM(m2.valor) FROM manutencoes m2 WHERE m2.veiculo_id = v.id AND m2.empresa_id = ? AND MONTH(m2.data_manutencao) = ? AND YEAR(m2.data_manutencao) = ?), 0) as custo_manutencao,
                (COALESCE(SUM(r.frete), 0) - COALESCE(SUM(r.comissao), 0)
                    - COALESCE((SELECT SUM(a2.valor_total) FROM abastecimentos a2 WHERE a2.rota_id IN (SELECT r2.id FROM rotas r2 WHERE r2.veiculo_id = v.id AND r2.empresa_id = ? AND ((MONTH(r2.data_rota) = ? AND YEAR(r2.data_rota) = ?) OR (MONTH(r2.data_saida) = ? AND YEAR(r2.data_saida) = ?))) AND a2.empresa_id = ?), 0)
                    - COALESCE((SELECT SUM(dv2.total_despviagem) FROM despesas_viagem dv2 WHERE dv2.rota_id IN (SELECT r3.id FROM rotas r3 WHERE r3.veiculo_id = v.id AND r3.empresa_id = ? AND ((MONTH(r3.data_rota) = ? AND YEAR(r3.data_rota) = ?) OR (MONTH(r3.data_saida) = ? AND YEAR(r3.data_saida) = ?))) AND dv2.empresa_id = ?), 0)
                    - COALESCE((SELECT SUM(m2.valor) FROM manutencoes m2 WHERE m2.veiculo_id = v.id AND m2.empresa_id = ? AND MONTH(m2.data_manutencao) = ? AND YEAR(m2.data_manutencao) = ?), 0)) as lucro
            FROM rotas r INNER JOIN veiculos v ON v.id = r.veiculo_id AND v.empresa_id = ?
            WHERE r.empresa_id = ? AND ((MONTH(r.data_rota) = ? AND YEAR(r.data_rota) = ?) OR (MONTH(r.data_saida) = ? AND YEAR(r.data_saida) = ?))
            GROUP BY v.id, v.placa, v.modelo HAVING lucro > 0 ORDER BY lucro DESC LIMIT 5
        ");
        $stmt->execute([
            $empresa_id, $mes, $ano, $empresa_id, $empresa_id, $mes, $ano, $empresa_id, $empresa_id, $mes, $ano,
            $empresa_id, $mes, $ano, $empresa_id, $empresa_id, $mes, $ano, $empresa_id, $empresa_id, $mes, $ano, $empresa_id,
            $empresa_id, $mes, $ano, $empresa_id, $empresa_id, $mes, $ano
        ]);
        $rankings['veiculos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $conn->prepare("
            SELECT m.nome, COALESCE(SUM(r.frete), 0) as receita, COALESCE(SUM(r.comissao), 0) as comissao,
                COALESCE(SUM(dv.total_despviagem), 0) as despesas_viagem,
                (COALESCE(SUM(r.frete), 0) - COALESCE(SUM(r.comissao), 0) - COALESCE(SUM(dv.total_despviagem), 0)) as lucro
            FROM rotas r INNER JOIN motoristas m ON m.id = r.motorista_id AND m.empresa_id = ?
            LEFT JOIN despesas_viagem dv ON dv.rota_id = r.id AND dv.empresa_id = ?
            WHERE r.empresa_id = ? AND ((MONTH(r.data_rota) = ? AND YEAR(r.data_rota) = ?) OR (MONTH(r.data_saida) = ? AND YEAR(r.data_saida) = ?))
            GROUP BY m.id, m.nome HAVING lucro > 0 ORDER BY lucro DESC LIMIT 5
        ");
        $stmt->execute([$empresa_id, $empresa_id, $empresa_id, $mes, $ano, $mes, $ano]);
        $rankings['motoristas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        try {
            $chk = $conn->query("SHOW TABLES LIKE 'tipos_frete'");
            if ($chk && $chk->rowCount() > 0) {
                $st = $conn->prepare("SELECT COALESCE(tf.nome, 'Não especificado') as tipo_frete, COALESCE(SUM(r.frete), 0) as receita, COALESCE(SUM(r.comissao), 0) as comissao, (COALESCE(SUM(r.frete), 0) - COALESCE(SUM(r.comissao), 0)) as lucro FROM rotas r LEFT JOIN tipos_frete tf ON r.tipo_frete_id = tf.id WHERE r.empresa_id = ? AND ((MONTH(r.data_rota) = ? AND YEAR(r.data_rota) = ?) OR (MONTH(r.data_saida) = ? AND YEAR(r.data_saida) = ?)) GROUP BY tf.id, tf.nome HAVING lucro > 0 ORDER BY lucro DESC");
                $st->execute([$empresa_id, $mes, $ano, $mes, $ano]);
                $rankings['tipos_frete'] = $st->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {}
        return $rankings;
    } catch (Exception $e) {
        return ['veiculos' => [], 'motoristas' => [], 'clientes' => [], 'tipos_frete' => []];
    }
}
}
