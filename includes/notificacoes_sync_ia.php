<?php
/**
 * Sincronização de notificações IA (anomalias, GPS, insights).
 */
require_once __DIR__ . '/notificacoes_helpers.php';

function sf_notif_sync_ia_completo(PDO $conn, int $empresa_id): int
{
    if (!sf_notificacoes_sistema_ativo($conn, $empresa_id)) {
        return 0;
    }
    if (!sf_notif_sync_pode_executar($conn, $empresa_id, 'sync_ia', 60)) {
        return 0;
    }
    $n = 0;
    $n += sf_notif_sync_anomalias_abastecimento($conn, $empresa_id);
    $n += sf_notif_sync_anomalias_rotas($conn, $empresa_id);
    $n += sf_notif_sync_gps_alertas($conn, $empresa_id);
    $n += sf_notif_sync_gps_cerca($conn, $empresa_id);
    $n += sf_notif_sync_lucratividade($conn, $empresa_id);
    $n += sf_notif_sync_pneus_insights($conn, $empresa_id);
    $n += sf_notif_sync_resumo_consolidado($conn, $empresa_id);
    $n += sf_notif_sync_briefing_diario($conn, $empresa_id);
    sf_notif_sync_registrar($conn, $empresa_id, 'sync_ia');
    return $n;
}

function sf_notif_sync_anomalias_abastecimento(PDO $conn, int $empresa_id): int
{
    $PRECO_MIN = 1.5;
    $PRECO_MAX = 12.0;
    $criadas = 0;
    try {
        $st = $conn->prepare("
            SELECT a.id, a.veiculo_id, a.data_abastecimento, a.km_atual, a.litros, a.valor_litro, v.placa
            FROM abastecimentos a
            LEFT JOIN veiculos v ON a.veiculo_id = v.id
            WHERE a.empresa_id = :e
            ORDER BY a.veiculo_id, a.data_abastecimento, a.id
            LIMIT 3000
        ");
        $st->execute([':e' => $empresa_id]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $prevKm = [];
        $erros = [];
        foreach ($rows as $a) {
            $vid = (int) $a['veiculo_id'];
            $km = (float) $a['km_atual'];
            $vl = (float) $a['valor_litro'];
            $msg = null;
            $sev = 'aviso';
            if (isset($prevKm[$vid]) && $prevKm[$vid] > 0 && $km > 0 && $km < $prevKm[$vid]) {
                $msg = "KM regressivo no abastecimento do veículo {$a['placa']}";
                $sev = 'critico';
            } elseif ($vl > 0 && ($vl < $PRECO_MIN || $vl > $PRECO_MAX)) {
                $msg = "Preço/litro atípico (R$ " . number_format($vl, 2, ',', '.') . ") — {$a['placa']}";
            }
            if ($km > 0) {
                $prevKm[$vid] = $km;
            }
            if ($msg === null) {
                continue;
            }
            $erros[] = ['id' => (int) $a['id'], 'msg' => $msg, 'sev' => $sev, 'placa' => $a['placa']];
        }
        usort($erros, static fn($x, $y) => ($x['sev'] === 'critico' ? 0 : 1) <=> ($y['sev'] === 'critico' ? 0 : 1));
        foreach (array_slice($erros, 0, 3) as $item) {
            if (sf_notif_inserir($conn, $empresa_id, 'fraudes', 'Anomalia em abastecimento', $item['msg'], [
                'referencia_id' => $item['id'], 'severidade' => $item['sev'],
                'ia_mensagem' => 'Revise o lançamento e confronte com cupom fiscal ou bomba.',
                'grupo_chave' => 'anom_abast_' . $item['id'], 'horas_janela' => 24,
            ])) {
                $criadas++;
            }
        }
        if (count($erros) > 3) {
            if (sf_notif_upsert_grupo_hoje($conn, $empresa_id, 'anomalias_abast_resumo', 'fraudes',
                'Mais anomalias em abastecimentos',
                count($erros) . ' inconsistência(s) detectada(s) — veja Abastecimentos',
                'Use a análise de anomalias para auditar possíveis erros ou fraudes.',
                'aviso', ['total' => count($erros)])) {
                $criadas++;
            }
        }
    } catch (Throwable $e) {
        error_log('sf_notif_sync_anomalias_abast: ' . $e->getMessage());
    }
    return $criadas;
}

function sf_notif_sync_anomalias_rotas(PDO $conn, int $empresa_id): int
{
    $criadas = 0;
    try {
        $st = $conn->prepare("
            SELECT r.id, r.km_saida, r.km_chegada, r.data_saida, r.data_chegada, v.placa
            FROM rotas r
            LEFT JOIN veiculos v ON r.veiculo_id = v.id
            WHERE r.empresa_id = :e AND r.data_rota >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ORDER BY r.data_rota DESC
            LIMIT 500
        ");
        $st->execute([':e' => $empresa_id]);
        $erros = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $msg = null;
            $sev = 'aviso';
            $ks = (float) ($r['km_saida'] ?? 0);
            $kc = (float) ($r['km_chegada'] ?? 0);
            if ($ks > 0 && $kc > 0 && $kc < $ks) {
                $msg = "KM chegada menor que saída — {$r['placa']}";
                $sev = 'critico';
            }
            if ($msg && sf_notif_inserir($conn, $empresa_id, 'alerta', 'Inconsistência em rota', $msg, [
                'referencia_id' => (int) $r['id'], 'severidade' => $sev,
                'ia_mensagem' => 'Corrija odômetros ou datas para não distorcer custo/km.',
                'grupo_chave' => 'anom_rota_' . $r['id'], 'horas_janela' => 48,
            ])) {
                $erros[] = $r;
                $criadas++;
            }
            if (count($erros) >= 3) {
                break;
            }
        }
    } catch (Throwable $e) {
        error_log('sf_notif_sync_anomalias_rotas: ' . $e->getMessage());
    }
    return $criadas;
}

function sf_notif_sync_gps_alertas(PDO $conn, int $empresa_id): int
{
    $criadas = 0;
    try {
        $conn->query('SELECT 1 FROM gps_alertas_operacionais LIMIT 1');
        $st = $conn->prepare("
            SELECT a.id, a.tipo, a.mensagem, v.placa
            FROM gps_alertas_operacionais a
            LEFT JOIN veiculos v ON v.id = a.veiculo_id AND v.empresa_id = :e2
            WHERE a.empresa_id = :e AND a.data_hora >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY a.data_hora DESC
            LIMIT 10
        ");
        $st->execute([':e' => $empresa_id, ':e2' => $empresa_id]);
        $criticos = ['mock_location', 'salto_suspeito', 'velocidade_excessiva', 'bateria_baixa'];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $tipo = (string) ($a['tipo'] ?? 'alerta');
            $sev = in_array($tipo, $criticos, true) ? 'critico' : 'aviso';
            $titulo = 'Alerta GPS: ' . str_replace('_', ' ', $tipo);
            $msg = trim(($a['placa'] ? $a['placa'] . ' — ' : '') . ($a['mensagem'] ?? ''));
            if (sf_notif_inserir($conn, $empresa_id, 'gps', $titulo, $msg, [
                'referencia_id' => (int) $a['id'], 'severidade' => $sev,
                'ia_mensagem' => 'Verifique no mapa da frota o contexto do alerta.',
                'grupo_chave' => 'gps_' . $a['id'], 'horas_janela' => 24,
            ])) {
                $criadas++;
            }
        }
    } catch (Throwable $e) {
        // tabela GPS opcional
    }
    return $criadas;
}

function sf_notif_sync_gps_cerca(PDO $conn, int $empresa_id): int
{
    $criadas = 0;
    try {
        $conn->query('SELECT 1 FROM gps_cerca_alertas LIMIT 1');
        $st = $conn->prepare("
            SELECT a.id, a.tipo, a.data_hora, c.nome AS cerca_nome, v.placa, m.nome AS motorista
            FROM gps_cerca_alertas a
            INNER JOIN gps_cercas c ON c.id = a.cerca_id AND c.empresa_id = :e
            LEFT JOIN veiculos v ON v.id = a.veiculo_id AND v.empresa_id = :e2
            LEFT JOIN motoristas m ON m.id = a.motorista_id AND m.empresa_id = :e3
            WHERE a.empresa_id = :e4
            AND a.data_hora >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY a.data_hora DESC
            LIMIT 8
        ");
        $st->execute([':e' => $empresa_id, ':e2' => $empresa_id, ':e3' => $empresa_id, ':e4' => $empresa_id]);
        $labels = ['entrou' => 'entrou na cerca', 'saiu' => 'saiu da cerca', 'permanencia' => 'permanência na cerca'];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $tipo = (string) ($a['tipo'] ?? 'entrou');
            $acao = $labels[$tipo] ?? $tipo;
            $placa = trim((string) ($a['placa'] ?? ''));
            $msg = ($placa !== '' ? "{$placa} " : '') . $acao . ' · ' . ($a['cerca_nome'] ?? 'Cerca');
            $sev = $tipo === 'permanencia' ? 'aviso' : 'info';
            if (sf_notif_inserir($conn, $empresa_id, 'gps', 'Cerca GPS: ' . str_replace('_', ' ', $tipo),
                $msg,
                ['referencia_id' => (int) $a['id'], 'severidade' => $sev,
                 'ia_mensagem' => 'Confira o mapa da frota para o contexto do veículo.',
                 'grupo_chave' => 'gps_cerca_' . $a['id'], 'horas_janela' => 24])) {
                $criadas++;
            }
        }
    } catch (Throwable $e) {
        // tabela opcional
    }
    return $criadas;
}

function sf_notif_sync_lucratividade(PDO $conn, int $empresa_id): int
{
    if (!is_file(__DIR__ . '/lucratividade_funcoes.php')) {
        return 0;
    }
    require_once __DIR__ . '/lucratividade_funcoes.php';
    $criadas = 0;
    try {
        $mes = (int) date('n');
        $ano = (int) date('Y');
        $payload = lucra_build_insights_payload($conn, $empresa_id, $mes, $ano);
        foreach (array_slice($payload['qualidade'] ?? [], 0, 2) as $item) {
            $sev = ($item['severidade'] ?? '') === 'erro' ? 'critico' : 'aviso';
            $motivo = $item['motivos'][0]['msg'] ?? $item['nome'];
            if (sf_notif_inserir($conn, $empresa_id, 'custo', (string) ($item['nome'] ?? 'Lucratividade'),
                (string) $motivo,
                ['severidade' => $sev,
                 'ia_mensagem' => 'Abra Lucratividade para ver margens, custos e tendências do mês.',
                 'grupo_chave' => 'lucra_' . ($item['id'] ?? 0) . '_' . $mes . $ano,
                 'horas_janela' => 24])) {
                $criadas++;
            }
        }
    } catch (Throwable $e) {
        error_log('sf_notif_sync_lucratividade: ' . $e->getMessage());
    }
    return $criadas;
}

function sf_notif_sync_resumo_consolidado(PDO $conn, int $empresa_id): int
{
    $bullets = [];
    $sev = 'info';

    if (is_file(__DIR__ . '/lucratividade_funcoes.php')) {
        require_once __DIR__ . '/lucratividade_funcoes.php';
        try {
            $payload = lucra_build_insights_payload($conn, $empresa_id, (int) date('n'), (int) date('Y'));
            if ((int) ($payload['q_erros'] ?? 0) > 0) {
                $bullets[] = 'Lucratividade: ' . (int) $payload['q_erros'] . ' alerta(s) crítico(s) no mês';
                $sev = 'critico';
            } elseif ((int) ($payload['q_avisos'] ?? 0) > 0) {
                $bullets[] = 'Lucratividade: ' . (int) $payload['q_avisos'] . ' ponto(s) de atenção';
                $sev = 'aviso';
            }
            $lucro = (float) ($payload['lucro_liquido'] ?? 0);
            if ($lucro < 0) {
                $bullets[] = 'Prejuízo estimado no mês: R$ ' . number_format(abs($lucro), 2, ',', '.');
                $sev = 'critico';
            }
        } catch (Throwable $e) {
            // ignora
        }
    }

    try {
        $st = $conn->prepare("SELECT COUNT(*) FROM despesas_viagem WHERE empresa_id = :e AND status = 'pendente'");
        $st->execute([':e' => $empresa_id]);
        $pendDesp = (int) $st->fetchColumn();
        if ($pendDesp > 0) {
            $bullets[] = "{$pendDesp} despesa(s) de viagem aguardando aprovação";
            if ($pendDesp >= 5) {
                $sev = 'critico';
            } elseif ($sev === 'info') {
                $sev = 'aviso';
            }
        }
    } catch (Throwable $e) {
        // ignora
    }

    try {
        $st = $conn->prepare("
            SELECT COUNT(*) FROM rotas
            WHERE empresa_id = :e AND status = 'pendente'
            AND data_rota >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $st->execute([':e' => $empresa_id]);
        $pendRotas = (int) $st->fetchColumn();
        if ($pendRotas > 0) {
            $bullets[] = "{$pendRotas} rota(s) pendente(s) de aprovação";
        }
    } catch (Throwable $e) {
        // ignora
    }

    if ($bullets === []) {
        return 0;
    }

    $hoje = date('Y-m-d');
    $msg = implode(' · ', array_slice($bullets, 0, 4));
    $ia = "• " . implode("\n• ", array_slice($bullets, 0, 6));
    return sf_notif_upsert_grupo_hoje($conn, $empresa_id, 'resumo_consolidado_' . $hoje, 'insights',
        'Resumo executivo da frota',
        $msg,
        $ia,
        $sev,
        ['bullets' => $bullets]) ? 1 : 0;
}

function sf_notif_sync_pneus_insights(PDO $conn, int $empresa_id): int
{
    if (!is_file(__DIR__ . '/pneus_repository.php')) {
        return 0;
    }
    require_once __DIR__ . '/pneus_repository.php';
    try {
        $payload = pneus_build_insights_payload($conn, $empresa_id);
        $criadas = 0;
        foreach (array_slice($payload['qualidade'] ?? [], 0, 3) as $item) {
            $sev = ($item['severidade'] ?? '') === 'erro' ? 'critico' : 'aviso';
            $motivo = $item['motivos'][0]['msg'] ?? $item['nome'];
            if (sf_notif_inserir($conn, $empresa_id, 'pneu', (string) $item['nome'], (string) $motivo, [
                'severidade' => $sev,
                'ia_mensagem' => 'Consulte o dashboard de pneus para plano de reposição.',
                'grupo_chave' => 'pneu_insight_' . ($item['id'] ?? 0),
                'horas_janela' => 24,
            ])) {
                $criadas++;
            }
        }
        return $criadas;
    } catch (Throwable $e) {
        return 0;
    }
}

function sf_notif_sync_briefing_diario(PDO $conn, int $empresa_id): int
{
    $op = sf_notif_contar_nao_lidas($conn, $empresa_id, 'operacional');
    $ia = sf_notif_contar_nao_lidas($conn, $empresa_id, 'ia');
    if ($op + $ia < 1) {
        return 0;
    }
    $hoje = date('Y-m-d');
    $msg = "Hoje você tem {$op} alerta(s) operacional(is) e {$ia} insight(s) de IA.";
    return sf_notif_upsert_grupo_hoje($conn, $empresa_id, 'briefing_' . $hoje, 'insights',
        'Briefing da frota',
        $msg,
        'Comece pelos itens críticos no centro de notificações.',
        $op >= 5 ? 'critico' : 'info',
        ['operacional' => $op, 'ia' => $ia]) ? 1 : 0;
}
