<?php
/**
 * Alertas e Score de Manutenção
 * - Próximas manutenções (planos por km/dias)
 * - Alertas inteligentes (3 corretivas mesmo componente, preventiva vencida, etc.)
 * - Score técnico do veículo (0-100) e status (Saudável / Atenção / Crítico)
 * - Impacto da manutenção no lucro (últimos 12 meses)
 */

if (!isset($conn) || !isset($_SESSION['empresa_id'])) {
    return;
}
$empresa_id = (int)$_SESSION['empresa_id'];

$alertas_proximas = [];
$alertas_inteligentes = [];
$score_veiculos = [];
$impacto_lucro = null;
$previsao_proximo_mes = null;
$tem_planos = false;

try {
    // Verificar se tabela planos_manutencao existe
    $stmt = $conn->query("SHOW TABLES LIKE 'planos_manutencao'");
    $tem_planos = $stmt->rowCount() > 0;

    if ($tem_planos) {
        // Próximas manutenções (vence por km ou por data)
        $sql = "SELECT p.*, v.placa, v.km_atual as km_atual_veiculo,
                cm.nome as componente_nome, tm.nome as tipo_nome,
                (p.ultimo_km + p.intervalo_km) as proximo_km,
                DATE_ADD(p.ultima_data, INTERVAL p.intervalo_dias DAY) as proxima_data
                FROM planos_manutencao p
                JOIN veiculos v ON v.id = p.veiculo_id AND v.empresa_id = p.empresa_id
                JOIN componentes_manutencao cm ON cm.id = p.componente_id
                JOIN tipos_manutencao tm ON tm.id = p.tipo_manutencao_id
                WHERE p.empresa_id = :eid AND p.ativo = 1
                AND (p.ultimo_km IS NOT NULL OR p.ultima_data IS NOT NULL)";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':eid', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $planos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $hoje = date('Y-m-d');
        foreach ($planos as $p) {
            $vencido_km = false;
            $vencido_data = false;
            $msg = [];
            if ($p['intervalo_km'] && $p['proximo_km'] !== null) {
                $km_atual = (int)($p['km_atual_veiculo'] ?? 0);
                if ($km_atual >= (int)$p['proximo_km']) {
                    $vencido_km = true;
                    $msg[] = 'Venceu por km (' . number_format($km_atual - (int)$p['proximo_km'], 0, '', '.') . ' km além)';
                } else {
                    $msg[] = 'Próximo em ' . number_format((int)$p['proximo_km'] - $km_atual, 0, '', '.') . ' km';
                }
            }
            if ($p['intervalo_dias'] && $p['proxima_data']) {
                if ($p['proxima_data'] < $hoje) {
                    $vencido_data = true;
                    $msg[] = 'Venceu em ' . $p['proxima_data'];
                } else {
                    $msg[] = 'Vence em ' . date('d/m/Y', strtotime($p['proxima_data']));
                }
            }
            if ($vencido_km || $vencido_data) {
                $alertas_proximas[] = [
                    'placa' => $p['placa'],
                    'componente' => $p['componente_nome'],
                    'tipo' => $p['tipo_nome'],
                    'vencido' => true,
                    'msg' => implode('; ', $msg)
                ];
            } elseif (!empty($msg)) {
                $alertas_proximas[] = [
                    'placa' => $p['placa'],
                    'componente' => $p['componente_nome'],
                    'tipo' => $p['tipo_nome'],
                    'vencido' => false,
                    'msg' => implode('; ', $msg)
                ];
            }
        }
        // Ordenar: vencidos primeiro
        usort($alertas_proximas, function ($a, $b) {
            if ($a['vencido'] !== $b['vencido']) return $a['vencido'] ? -1 : 1;
            return 0;
        });
        $alertas_proximas = array_slice($alertas_proximas, 0, 15);
    }

    // Alertas inteligentes
    // 1) Veículo com 3+ corretivas no mesmo componente
    $sql = "SELECT v.placa, cm.nome as componente, COUNT(*) as qtd
            FROM manutencoes m
            JOIN veiculos v ON v.id = m.veiculo_id AND v.empresa_id = m.empresa_id
            JOIN componentes_manutencao cm ON cm.id = m.componente_id
            LEFT JOIN tipos_manutencao tm ON tm.id = m.tipo_manutencao_id
            WHERE m.empresa_id = :eid
            AND (tm.nome IS NULL OR LOWER(TRIM(tm.nome)) NOT LIKE '%preventiva%')
            AND m.data_manutencao >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
            GROUP BY v.id, m.componente_id
            HAVING qtd >= 3";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':eid', $empresa_id, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $alertas_inteligentes[] = [
            'tipo' => 'corretivas_componente',
            'nivel' => 'warning',
            'mensagem' => 'Veículo ' . $row['placa'] . ': 3 ou mais corretivas no componente "' . $row['componente'] . '" nos últimos 12 meses.'
        ];
    }

    // 2) Preventivas vencidas já aparecem em $alertas_proximas (card "Próximas Manutenções")

    // 3) Corretiva em menos de X km após preventiva no mesmo componente (ex: 5000 km)
    $sql = "SELECT m1.veiculo_id, v.placa, cm.nome as componente, m1.km_atual as km_preventiva, m2.km_atual as km_corretiva
            FROM manutencoes m1
            JOIN manutencoes m2 ON m2.veiculo_id = m1.veiculo_id AND m2.componente_id = m1.componente_id
                AND m2.data_manutencao > m1.data_manutencao
                AND m2.km_atual > m1.km_atual
                AND (m2.km_atual - m1.km_atual) < 5000
            JOIN veiculos v ON v.id = m1.veiculo_id AND v.empresa_id = m1.empresa_id
            JOIN componentes_manutencao cm ON cm.id = m1.componente_id
            LEFT JOIN tipos_manutencao t1 ON t1.id = m1.tipo_manutencao_id
            LEFT JOIN tipos_manutencao t2 ON t2.id = m2.tipo_manutencao_id
            WHERE m1.empresa_id = :eid
            AND (t1.nome IS NOT NULL AND LOWER(TRIM(t1.nome)) LIKE '%preventiva%')
            AND (t2.nome IS NULL OR LOWER(TRIM(t2.nome)) NOT LIKE '%preventiva%')
            AND m1.data_manutencao >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
            LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':eid', $empresa_id, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $alertas_inteligentes[] = [
            'tipo' => 'corretiva_apos_preventiva',
            'nivel' => 'warning',
            'mensagem' => $row['placa'] . ': corretiva no componente "' . $row['componente'] . '" em menos de 5.000 km após preventiva.'
        ];
    }

    // Score técnico do veículo (0-100) – primeiro tenta com rotas (km rodado); se falhar, usa só manutenções
    $veiculos = [];
    try {
        $sql = "SELECT v.id, v.placa, v.modelo, v.km_atual,
                COALESCE(SUM(CASE WHEN (tm.nome IS NULL OR LOWER(TRIM(tm.nome)) NOT LIKE '%preventiva%') THEN 1 ELSE 0 END), 0) as qtd_corretivas,
                COALESCE(SUM(m.valor), 0) as custo_total,
                COALESCE(SUM(r.distancia_km), 0) as km_rodado_12m
                FROM veiculos v
                LEFT JOIN manutencoes m ON m.veiculo_id = v.id AND m.data_manutencao >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
                LEFT JOIN tipos_manutencao tm ON tm.id = m.tipo_manutencao_id
                LEFT JOIN rotas r ON r.veiculo_id = v.id AND r.data_saida >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH) AND r.data_saida IS NOT NULL
                WHERE v.empresa_id = :eid
                GROUP BY v.id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':eid', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("maintenance_alertas_score (score com rotas): " . $e->getMessage());
        // Fallback: só veículos + manutenções (sem rotas), km_rodado = 0
        try {
            $sql = "SELECT v.id, v.placa, v.modelo, v.km_atual,
                    COALESCE(SUM(CASE WHEN (tm.nome IS NULL OR LOWER(TRIM(tm.nome)) NOT LIKE '%preventiva%') THEN 1 ELSE 0 END), 0) as qtd_corretivas,
                    COALESCE(SUM(m.valor), 0) as custo_total,
                    0 as km_rodado_12m
                    FROM veiculos v
                    LEFT JOIN manutencoes m ON m.veiculo_id = v.id AND m.data_manutencao >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
                    LEFT JOIN tipos_manutencao tm ON tm.id = m.tipo_manutencao_id
                    WHERE v.empresa_id = :eid
                    GROUP BY v.id";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':eid', $empresa_id, PDO::PARAM_INT);
            $stmt->execute();
            $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e2) {
            error_log("maintenance_alertas_score (score fallback): " . $e2->getMessage());
        }
    }

    $custo_km_list = [];
    foreach ($veiculos as $v) {
        $km = (float)($v['km_rodado_12m'] ?? 0);
        $custo = (float)($v['custo_total'] ?? 0);
        $custo_km_list[] = $km > 0 ? $custo / $km : 0;
    }
    $max_custo_km = count($custo_km_list) ? max($custo_km_list) : 0;
    if ($max_custo_km <= 0) $max_custo_km = 1;

    foreach ($veiculos as $v) {
        $km = (float)($v['km_rodado_12m'] ?? 0);
        $custo = (float)($v['custo_total'] ?? 0);
        $custo_km = $km > 0 ? $custo / $km : 0;
        $qtd_corretivas = (int)($v['qtd_corretivas'] ?? 0);

        $pontos_custo = 100 - min(100, ($custo_km / $max_custo_km) * 50);
        $pontos_corretivas = max(0, 100 - $qtd_corretivas * 15);
        $score = ($pontos_custo + $pontos_corretivas) / 2;
        $score = max(0, min(100, round($score)));

        if ($tem_planos) {
            try {
                $stmt2 = $conn->prepare("SELECT COUNT(*) as c FROM planos_manutencao p
                    JOIN veiculos v2 ON v2.id = p.veiculo_id
                    WHERE p.veiculo_id = :vid AND p.empresa_id = :eid AND p.ativo = 1
                    AND ( (p.intervalo_km IS NOT NULL AND v2.km_atual >= (p.ultimo_km + p.intervalo_km))
                       OR (p.intervalo_dias IS NOT NULL AND (DATE_ADD(p.ultima_data, INTERVAL p.intervalo_dias DAY) < CURDATE()))");
                $stmt2->bindValue(':vid', $v['id'], PDO::PARAM_INT);
                $stmt2->bindValue(':eid', $empresa_id, PDO::PARAM_INT);
                $stmt2->execute();
                $preventivas_atrasadas = (int)($stmt2->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
                $score = max(0, $score - $preventivas_atrasadas * 10);
            } catch (Exception $e) { /* ignora */ }
        }

        $status = 'saudavel';
        if ($score < 40) $status = 'critico';
        elseif ($score < 70) $status = 'atencao';

        $score_veiculos[] = [
            'placa' => $v['placa'],
            'modelo' => $v['modelo'] ?? '',
            'score' => (int)$score,
            'status' => $status,
            'custo_km_12m' => round($custo_km, 2),
            'qtd_corretivas_12m' => $qtd_corretivas
        ];
    }
    usort($score_veiculos, function ($a, $b) { return $a['score'] - $b['score']; });

    // Sugestões automáticas: custo/km acima da média e sugestão de troca
    if (count($score_veiculos) > 0) {
        $custos_km = array_column($score_veiculos, 'custo_km_12m');
        $media_custo_km = array_sum($custos_km) / count($custos_km);
        foreach ($score_veiculos as $s) {
            $ck = (float)$s['custo_km_12m'];
            if ($media_custo_km > 0 && $ck > $media_custo_km * 1.5) {
                $alertas_inteligentes[] = [
                    'tipo' => 'custo_acima_media',
                    'nivel' => 'warning',
                    'mensagem' => 'Veículo ' . $s['placa'] . ': custo manutenção/km (R$ ' . number_format($ck, 2, ',', '.') . ') acima da média da frota.'
                ];
            }
            if ($s['status'] === 'critico' && $media_custo_km > 0 && $ck >= $media_custo_km) {
                $alertas_inteligentes[] = [
                    'tipo' => 'avaliar_troca',
                    'nivel' => 'warning',
                    'mensagem' => 'Avaliar troca do veículo ' . $s['placa'] . ' – custo operacional elevado (score ' . $s['score'] . ').'
                ];
            }
        }
    }

    // Impacto: sempre calcula total_manut; lucro_bruto pode falhar se rotas/despesas_viagem não existirem
    $total_manut = 0;
    try {
        $stmt = $conn->prepare("SELECT COALESCE(SUM(valor), 0) as total_manut FROM manutencoes WHERE empresa_id = :eid AND data_manutencao >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)");
        $stmt->bindValue(':eid', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $total_manut = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_manut'] ?? 0);
    } catch (Exception $e) {
        error_log("maintenance_alertas_score (total_manut): " . $e->getMessage());
    }

    // Previsão próximo mês: média dos últimos 6 meses
    try {
        $stmt = $conn->prepare("SELECT YEAR(data_manutencao) as y, MONTH(data_manutencao) as m, COALESCE(SUM(valor), 0) as total FROM manutencoes WHERE empresa_id = :eid AND data_manutencao >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH) GROUP BY y, m");
        $stmt->bindValue(':eid', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $totais = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($totais) > 0) {
            $soma = array_sum(array_column($totais, 'total'));
            $previsao_proximo_mes = round($soma / count($totais), 2);
        }
    } catch (Exception $e) {
        error_log("maintenance_alertas_score (previsao_proximo_mes): " . $e->getMessage());
    }

    $lucro_bruto = 0;
    try {
        $sql = "SELECT 
                COALESCE(SUM(r.frete - COALESCE(r.comissao, 0)), 0) - COALESCE(SUM(dv.total_desp), 0) as lucro_bruto
                FROM rotas r
                LEFT JOIN (SELECT rota_id, SUM(COALESCE(total_despviagem, 0)) as total_desp FROM despesas_viagem GROUP BY rota_id) dv ON dv.rota_id = r.id
                WHERE r.empresa_id = :eid AND r.data_saida >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH) AND r.data_saida IS NOT NULL";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':eid', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $lucro_bruto = (float)($row['lucro_bruto'] ?? 0);
    } catch (Exception $e) {
        error_log("maintenance_alertas_score (lucro_bruto): " . $e->getMessage());
    }

    $lucro_liquido = $lucro_bruto - $total_manut;
    $impacto_pct = ($lucro_bruto > 0 && $total_manut > 0) ? round(($total_manut / $lucro_bruto) * 100, 1) : 0;
    $impacto_lucro = [
        'total_manutencao_12m' => $total_manut,
        'lucro_bruto_12m' => round($lucro_bruto, 2),
        'lucro_liquido_12m' => round($lucro_liquido, 2),
        'impacto_pct' => $impacto_pct,
        'mensagem' => $impacto_pct > 0 ? "Manutenção representou {$impacto_pct}% do lucro bruto (antes da manutenção) nos últimos 12 meses." : ($total_manut > 0 && $lucro_bruto <= 0 ? "Total de R$ " . number_format($total_manut, 2, ',', '.') . " em manutenções (12 meses). Sem rotas no período para calcular % sobre lucro." : null)
    ];
} catch (Exception $e) {
    error_log("maintenance_alertas_score: " . $e->getMessage());
}
