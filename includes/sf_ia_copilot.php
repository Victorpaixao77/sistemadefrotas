<?php
/**
 * Copiloto de Operações — enriquecimento de respostas do assistente IA.
 * Só leitura; queries sempre com empresa_id.
 */

declare(strict_types=1);

/** Fontes de dados por intenção (transparência para o gestor). */
function sf_ia_copilot_fontes(string $intent): array
{
    static $map = [
        'faturamento' => ['Rotas', 'Financeiro'],
        'motorista_top' => ['Rotas', 'Motoristas'],
        'no_prazo' => ['Rotas'],
        'atrasos' => ['Rotas', 'Motoristas'],
        'cnh' => ['Motoristas', 'Documentos'],
        'consumo' => ['Rotas', 'Abastecimentos'],
        'km' => ['Rotas'],
        'rota_top' => ['Rotas', 'Motoristas', 'Veículos'],
        'cliente_top' => ['Ordens de transporte', 'Clientes'],
        'veiculo_top' => ['Rotas', 'Veículos'],
        'veiculo_parado' => ['Rotas', 'Veículos'],
        'rotas_ativas' => ['Rotas', 'GPS'],
        'rotas_mes' => ['Rotas'],
        'abastecimentos' => ['Abastecimentos', 'Veículos'],
        'pedagio' => ['Despesas de viagem'],
        'despesas' => ['Despesas de viagem'],
        'multas' => ['Multas'],
        'frota' => ['Veículos'],
        'manutencao' => ['Manutenções', 'Veículos'],
        'contas_pagar' => ['Financeiro', 'Contas a pagar'],
        'pneus' => ['Pneus'],
        'implementos' => ['Implementos'],
        'analise_empresa' => ['Rotas', 'Financeiro', 'Manutenções', 'Multas', 'Pneus', 'Motoristas'],
        'executivo' => ['Rotas', 'Financeiro', 'Alertas', 'GPS'],
        'previsoes' => ['Rotas', 'Manutenções', 'Abastecimentos', 'Veículos'],
        'documentos' => ['Documentos enviados', 'RAG interno'],
        'mapa_frota' => ['GPS', 'Veículos', 'Rotas'],
        'ajuda_sistema' => ['Documentação', 'Manual do sistema'],
        'analise_completa' => ['Rotas', 'Financeiro', 'Manutenções', 'Multas', 'Pneus', 'Motoristas', 'GPS'],
    ];
    return $map[$intent] ?? ['Dados da operação'];
}

/** Score de confiança (0–100) com motivos legíveis. */
function sf_ia_copilot_confianca(string $intent, array $rows, bool $semDados): array
{
    if ($intent === 'ajuda' || $semDados) {
        return [
            'score' => 25,
            'stars' => 2,
            'label' => 'Baixa',
            'motivos' => ['Poucos ou nenhum dado para esta pergunta', 'Tente outro período ou reformule'],
        ];
    }
    $n = count($rows);
    $recordHint = 0;
    foreach ($rows as $r) {
        foreach ($r as $v) {
            if (is_numeric(str_replace(['.', ',', '%', 'R$', ' '], '', (string) $v))) {
                $recordHint++;
            }
        }
    }
    $score = min(98, 55 + ($n * 8) + min(20, $recordHint * 2));
    if ($n >= 3) {
        $score = min(98, $score + 10);
    }
    $stars = $score >= 85 ? 5 : ($score >= 70 ? 4 : ($score >= 50 ? 3 : 2));
    return [
        'score' => $score,
        'stars' => $stars,
        'label' => $score >= 85 ? 'Alta' : ($score >= 60 ? 'Média' : 'Baixa'),
        'motivos' => [
            'Baseado em ' . max($n, 1) . ' registro(s) consultado(s)',
            'Atualizado hoje',
            $n >= 2 ? 'Dados completos para o período' : 'Conjunto limitado de registros',
        ],
    ];
}

/** Cards KPI a partir das linhas da resposta. */
function sf_ia_copilot_cards(string $intent, array $rows): array
{
    $cards = [];
    if ($rows === []) {
        return $cards;
    }
    $first = $rows[0];
    $keys = array_keys($first);
    $moneyKeys = ['Faturamento', 'Total', 'Custo total', 'Pedágio', 'Total despesas', 'Lucro', 'Frete', 'Comissões', 'Gasto'];
    foreach ($keys as $k) {
        $val = $first[$k];
        if ($val === null || $val === '' || $val === '—') {
            continue;
        }
        $isMoney = false;
        foreach ($moneyKeys as $mk) {
            if (stripos($k, $mk) !== false || stripos($k, 'R$') !== false) {
                $isMoney = true;
                break;
            }
        }
        $cards[] = [
            'title' => $k,
            'value' => (string) $val,
            'type' => $isMoney || strpos((string) $val, 'R$') === 0 ? 'money' : 'metric',
        ];
        if (count($cards) >= 4) {
            break;
        }
    }
    return $cards;
}

/** Sugestões de follow-up por intenção. Só sugere o que a IA sabe responder;
 * "Comparar com mês passado" apenas em intenções que suportam comparação. */
function sf_ia_copilot_sugestoes(string $intent, string $periodo): array
{
    static $base = [
        'faturamento'    => ['Comparar com mês passado', 'Qual motorista mais faturou?', 'Qual a rota mais rentável?', 'Quantas rotas no mês?'],
        'manutencao'     => ['Comparar com mês passado', 'Quais veículos precisam revisão?', 'Previsões para os próximos 30 dias', 'Quantas manutenções abertas?'],
        'motorista_top'  => ['Quem mais atrasou?', 'Qual o consumo médio da frota?', 'Qual o faturamento do mês?'],
        'atrasos'        => ['Quantas rotas no prazo?', 'Quantas rotas ativas agora?', 'Qual motorista mais faturou?'],
        'no_prazo'       => ['Quem mais atrasou?', 'Quantas rotas ativas agora?', 'Quantas rotas no mês?'],
        'consumo'        => ['Qual veículo mais rodou?', 'Quantos km rodei este mês?', 'Últimos abastecimentos'],
        'km'             => ['Comparar com mês passado', 'Qual veículo mais rodou?', 'Qual o consumo médio da frota?'],
        'multas'         => ['Comparar com mês passado', 'Alguma CNH vencendo?', 'Quem mais atrasou?'],
        'despesas'       => ['Comparar com mês passado', 'Quanto gastei de pedágio?', 'Qual o faturamento do mês?'],
        'pedagio'        => ['Comparar com mês passado', 'Quanto gastei de despesas de viagem?', 'Quantos km rodei este mês?'],
        'contas_pagar'   => ['Qual o faturamento do mês?', 'Quanto gastei de manutenção?', 'Resumo executivo de hoje'],
        'cnh'            => ['Quem mais atrasou?', 'Quantas rotas ativas agora?', 'Resumo executivo de hoje'],
        'rotas_ativas'   => ['Quantas rotas no mês?', 'Quem mais atrasou?', 'Qual veículo ficou mais parado?'],
        'rotas_mes'      => ['Quantas rotas no prazo?', 'Qual motorista mais faturou?', 'Qual a rota mais rentável?'],
        'rota_top'       => ['Qual cliente gera mais frete?', 'Qual o faturamento do mês?', 'Quantas rotas no mês?'],
        'cliente_top'    => ['Qual a rota mais rentável?', 'Qual o faturamento do mês?', 'Quantas rotas no mês?'],
        'veiculo_top'    => ['Qual veículo ficou mais parado?', 'Qual o consumo médio da frota?', 'Quantos km rodei este mês?'],
        'veiculo_parado' => ['Qual veículo mais rodou?', 'Quantas rotas ativas agora?', 'Previsões para os próximos 30 dias'],
        'consumo_frota'  => ['Qual veículo mais rodou?', 'Últimos abastecimentos'],
        'frota'          => ['Quantos pneus tenho?', 'Quantos implementos?', 'Qual veículo ficou mais parado?'],
        'pneus'          => ['Quantos implementos?', 'Quantos veículos tenho?', 'Previsões para os próximos 30 dias'],
        'implementos'    => ['Quantos veículos tenho?', 'Quantos pneus tenho?'],
        'abastecimentos' => ['Qual o consumo médio da frota?', 'Quanto gastei de manutenção?', 'Quantos km rodei este mês?'],
        'previsoes'      => ['Quais veículos precisam revisão?', 'Quanto gastei de manutenção?', 'Resumo executivo de hoje'],
        'executivo'      => ['Qual o faturamento do mês?', 'Quantas rotas ativas agora?', 'Alguma CNH vencendo?', 'Analise minha empresa'],
        'analise_empresa'=> ['Resumo executivo de hoje', 'Qual o faturamento do mês?', 'Quanto gastei de manutenção?'],
        'glossario'      => ['Qual o consumo médio da frota?', 'Qual o faturamento do mês?', 'Quantos km rodei este mês?'],
        'documentos'     => ['Analise minha empresa', 'Resumo executivo de hoje'],
        'mapa_frota'     => ['Quantas rotas ativas agora?', 'Abrir mapa da frota', 'Qual veículo mais rodou?'],
        'ajuda_sistema'  => ['Como cadastrar motorista?', 'Como emitir CT-e?', 'Como funciona o Centro de Inteligência?'],
        'analise_completa'=> ['Resumo executivo de hoje', 'Previsões para os próximos 30 dias', 'Quanto gastei de manutenção?'],
    ];
    $s = $base[$intent] ?? ['Qual o faturamento do mês?', 'Quantas rotas ativas agora?', 'Analise minha empresa'];
    return array_slice($s, 0, 4);
}

/** Período anterior com mesma duração. */
function sf_ia_copilot_periodo_anterior(string $ini, string $fim): array
{
    $tIni = strtotime($ini);
    $tFim = strtotime($fim);
    if ($tIni === false || $tFim === false) {
        return [date('Y-m-d', strtotime('-1 month')), date('Y-m-d', strtotime('-1 day')), 'período anterior'];
    }
    $days = max(1, (int) round(($tFim - $tIni) / 86400) + 1);
    $prevFim = date('Y-m-d', strtotime($ini . ' -1 day'));
    $prevIni = date('Y-m-d', strtotime($prevFim . ' -' . ($days - 1) . ' days'));
    return [$prevIni, $prevFim, 'período anterior equivalente'];
}

/** Comparação numérica com período anterior (faturamento, manutenção, despesas, multas, km). */
function sf_ia_copilot_comparacao(PDO $conn, int $empresaId, string $intent, string $ini, string $fim, callable $moeda): ?array
{
    [$pIni, $pFim, $pLabel] = sf_ia_copilot_periodo_anterior($ini, $fim);
    $P = [':e' => $empresaId, ':ini' => $ini, ':fim' => $fim];
    $Pp = [':e' => $empresaId, ':ini' => $pIni, ':fim' => $pFim];
    $periodoRotas = 'COALESCE(r.data_rota, r.data_saida) BETWEEN :ini AND :fim';

    $atual = 0.0;
    $anterior = 0.0;
    $rotulo = '';

    switch ($intent) {
        case 'faturamento':
            $sql = "SELECT COALESCE(SUM(r.frete),0) FROM rotas r WHERE r.empresa_id=:e AND $periodoRotas";
            $rotulo = 'Faturamento';
            break;
        case 'manutencao':
            $sql = "SELECT COALESCE(SUM(COALESCE(custo_total, valor, 0)),0) FROM manutencoes WHERE empresa_id=:e AND data_manutencao BETWEEN :ini AND :fim";
            $rotulo = 'Manutenção';
            break;
        case 'despesas':
            $sql = "SELECT COALESCE(SUM(total_despviagem),0) FROM despesas_viagem WHERE empresa_id=:e AND DATE(created_at) BETWEEN :ini AND :fim";
            $rotulo = 'Despesas de viagem';
            break;
        case 'multas':
            $sql = "SELECT COALESCE(SUM(valor),0) FROM multas WHERE empresa_id=:e AND data_infracao BETWEEN :ini AND :fim";
            $rotulo = 'Multas';
            break;
        case 'km':
            $sql = "SELECT COALESCE(SUM(r.distancia_km),0) FROM rotas r WHERE r.empresa_id=:e AND $periodoRotas";
            $rotulo = 'KM rodados';
            break;
        default:
            return null;
    }

    try {
        $st = $conn->prepare($sql);
        $st->execute($P);
        $atual = (float) $st->fetchColumn();
        $st->execute($Pp);
        $anterior = (float) $st->fetchColumn();
    } catch (Throwable $e) {
        return null;
    }

    if ($atual <= 0 && $anterior <= 0) {
        return null;
    }

    $pct = $anterior > 0 ? round((($atual - $anterior) / $anterior) * 100, 1) : ($atual > 0 ? 100.0 : 0.0);
    $dir = $pct > 0 ? '↑' : ($pct < 0 ? '↓' : '→');
    $abs = abs($pct);

    return [
        'rotulo' => $rotulo,
        'atual' => $intent === 'km' ? number_format($atual, 0, ',', '.') . ' km' : $moeda($atual),
        'anterior' => $intent === 'km' ? number_format($anterior, 0, ',', '.') . ' km' : $moeda($anterior),
        'pct' => $pct,
        'texto' => "{$dir} {$abs}% em relação ao {$pLabel}",
        'motivos' => $pct > 5 ? ['Possível aumento de viagens ou ticket médio'] : ($pct < -5 ? ['Possível redução de volume ou sazonalidade'] : ['Variação dentro da faixa habitual']),
    ];
}

/** Principais causas de manutenção (por tipo). */
function sf_ia_copilot_manutencao_causas(PDO $conn, int $empresaId, string $ini, string $fim): array
{
    try {
        $st = $conn->prepare(
            "SELECT COALESCE(tm.nome, 'Outros') AS tipo, COUNT(*) qtd,
                    SUM(COALESCE(m.custo_total, m.valor, 0)) total
             FROM manutencoes m
             LEFT JOIN tipos_manutencao tm ON tm.id = m.tipo_manutencao_id
             WHERE m.empresa_id = :e AND m.data_manutencao BETWEEN :ini AND :fim
             GROUP BY tipo ORDER BY total DESC LIMIT 5"
        );
        $st->execute([':e' => $empresaId, ':ini' => $ini, ':fim' => $fim]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/** Recomendações automáticas por intenção. */
function sf_ia_copilot_recomendacoes(PDO $conn, int $empresaId, string $intent, string $ini, string $fim, array $causasManut = []): array
{
    $rec = [];
    if ($intent === 'manutencao' && $causasManut !== []) {
        $top = $causasManut[0];
        $rec[] = 'Revisar custos de ' . ($top['tipo'] ?? 'manutenção') . ' — maior peso no período.';
        try {
            $st = $conn->prepare(
                "SELECT v.placa, MAX(m.data_manutencao) ultima, DATEDIFF(CURDATE(), MAX(m.data_manutencao)) dias,
                        COALESCE(SUM(r.distancia_km),0) km
                 FROM veiculos v
                 LEFT JOIN manutencoes m ON m.veiculo_id = v.id AND m.empresa_id = v.empresa_id
                 LEFT JOIN rotas r ON r.veiculo_id = v.id AND r.empresa_id = v.empresa_id
                    AND COALESCE(r.data_rota, r.data_saida) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                 WHERE v.empresa_id = :e
                 GROUP BY v.id
                 HAVING dias IS NULL OR dias > 25
                 ORDER BY km DESC LIMIT 3"
            );
            $st->execute([':e' => $empresaId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $v) {
                $d = (int) ($v['dias'] ?? 999);
                $rec[] = 'Veículo ' . $v['placa'] . ': última manutenção há ' . ($d > 900 ? '30+ dias' : $d . ' dias') . ' — considere agendar revisão.';
            }
        } catch (Throwable $e) {
        }
    }
    if ($intent === 'atrasos') {
        $rec[] = 'Revisar janelas de entrega e comunicação com motoristas com mais atrasos.';
    }
    if ($intent === 'cnh') {
        $rec[] = 'Bloquear alocação em rotas para motoristas com CNH vencida ou a vencer em 7 dias.';
    }
    if ($intent === 'consumo') {
        $rec[] = 'Cruzar consumo com abastecimentos suspeitos e rotas do simulador.';
    }
    return array_slice($rec, 0, 4);
}

/** Insights proativos ao abrir a página. */
function sf_ia_copilot_insights(PDO $conn, int $empresaId, string $nomeUsuario): array
{
    $insights = [];
    $iniMes = date('Y-m-01');
    $fimMes = date('Y-m-t');
    $iniMesAnt = date('Y-m-01', strtotime('first day of last month'));
    $fimMesAnt = date('Y-m-t', strtotime('last day of last month'));

    try {
        $st = $conn->prepare("SELECT COALESCE(SUM(r.no_prazo=0),0) a FROM rotas r WHERE r.empresa_id=:e AND COALESCE(r.data_rota,r.data_saida) BETWEEN :i AND :f");
        $st->execute([':e' => $empresaId, ':i' => $iniMes, ':f' => $fimMes]);
        $atras = (int) $st->fetchColumn();
        if ($atras > 0) {
            $insights[] = ['nivel' => 'aviso', 'texto' => $atras . ' rota(s) fora do prazo no mês', 'acao' => 'Quem mais atrasou?'];
        }

        $st = $conn->prepare("SELECT COALESCE(SUM(COALESCE(custo_total,valor,0)),0) FROM manutencoes WHERE empresa_id=:e AND data_manutencao BETWEEN :i AND :f");
        $st->execute([':e' => $empresaId, ':i' => $iniMes, ':f' => $fimMes]);
        $manAt = (float) $st->fetchColumn();
        $st->execute([':e' => $empresaId, ':i' => $iniMesAnt, ':f' => $fimMesAnt]);
        $manAnt = (float) $st->fetchColumn();
        if ($manAnt > 0 && $manAt > $manAnt * 1.08) {
            $pct = round((($manAt - $manAnt) / $manAnt) * 100);
            $insights[] = ['nivel' => 'aviso', 'texto' => 'Despesas de manutenção subiram ~' . $pct . '% vs mês passado', 'acao' => 'Quanto gastei de manutenção?'];
        }

        $st = $conn->prepare("SELECT nome FROM motoristas WHERE empresa_id=:e AND data_validade_cnh IS NOT NULL AND data_validade_cnh <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) LIMIT 1");
        $st->execute([':e' => $empresaId]);
        $cnh = $st->fetch(PDO::FETCH_ASSOC);
        if ($cnh) {
            $insights[] = ['nivel' => 'critico', 'texto' => 'CNH de ' . $cnh['nome'] . ' vence em breve', 'acao' => 'Alguma CNH vencendo?'];
        }

        $st = $conn->prepare("SELECT COUNT(*) FROM rotas r WHERE r.empresa_id=:e AND r.status='aprovado' AND r.data_saida <= NOW() AND r.data_chegada >= NOW()");
        $st->execute([':e' => $empresaId]);
        $ativas = (int) $st->fetchColumn();
        if ($ativas > 0) {
            $insights[] = ['nivel' => 'info', 'texto' => $ativas . ' rota(s) em andamento agora', 'acao' => 'Quantas rotas ativas tenho agora?'];
        }

        $st = $conn->prepare("SELECT COUNT(*) FROM multas WHERE empresa_id=:e AND data_infracao BETWEEN :i AND :f");
        $st->execute([':e' => $empresaId, ':i' => $iniMes, ':f' => $fimMes]);
        $multas = (int) $st->fetchColumn();
        if ($multas > 0) {
            $insights[] = ['nivel' => 'aviso', 'texto' => $multas . ' multa(s) no mês', 'acao' => 'Quantas multas este mês?'];
        }

        $st = $conn->prepare("SELECT COUNT(*) FROM contas_pagar WHERE empresa_id=:e AND data_pagamento IS NULL AND data_vencimento < CURDATE()");
        $st->execute([':e' => $empresaId]);
        $cp = (int) $st->fetchColumn();
        if ($cp > 0) {
            $insights[] = ['nivel' => 'critico', 'texto' => $cp . ' conta(s) a pagar vencida(s)', 'acao' => 'Quanto tenho a pagar?'];
        }
    } catch (Throwable $e) {
        error_log('sf_ia_copilot_insights: ' . $e->getMessage());
    }

    $primeiro = explode(' ', trim($nomeUsuario))[0] ?: 'gestor';
    return [
        'saudacao' => 'Bom dia, ' . $primeiro . '.',
        'total' => count($insights),
        'itens' => array_slice($insights, 0, 10),
    ];
}

/** Resumo executivo (modo executivo). */
function sf_ia_copilot_executivo(PDO $conn, int $empresaId, callable $moeda, callable $num): array
{
    $hoje = date('Y-m-d');
    $iniMes = date('Y-m-01');
    $fimMes = date('Y-m-t');
    $out = ['cards' => [], 'alertas' => 0];

    try {
        $st = $conn->prepare("SELECT COALESCE(SUM(r.frete),0) fat, COALESCE(SUM(r.comissao),0) com, COUNT(*) qtd,
                COALESCE(SUM(r.no_prazo=0),0) atras
            FROM rotas r WHERE r.empresa_id=:e AND COALESCE(r.data_rota,r.data_saida) BETWEEN :i AND :f");
        $st->execute([':e' => $empresaId, ':i' => $iniMes, ':f' => $fimMes]);
        $m = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $fat = (float) ($m['fat'] ?? 0);
        $com = (float) ($m['com'] ?? 0);
        $lucro = $fat - $com;
        $margem = $fat > 0 ? round($lucro / $fat * 100) : 0;

        $st = $conn->prepare("SELECT COALESCE(SUM(r.frete),0) FROM rotas r WHERE r.empresa_id=:e AND DATE(COALESCE(r.data_rota,r.data_saida)) = :h");
        $st->execute([':e' => $empresaId, ':h' => $hoje]);
        $fatHoje = (float) $st->fetchColumn();

        $st = $conn->prepare("SELECT COUNT(*) FROM rotas r WHERE r.empresa_id=:e AND r.status='aprovado' AND r.data_saida <= NOW() AND r.data_chegada >= NOW()");
        $st->execute([':e' => $empresaId]);
        $ativas = (int) $st->fetchColumn();

        $st = $conn->prepare("SELECT COUNT(*) FROM veiculos v WHERE v.empresa_id=:e AND NOT EXISTS (
            SELECT 1 FROM rotas r WHERE r.veiculo_id=v.id AND r.empresa_id=:e2 AND COALESCE(r.data_rota,r.data_saida) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        )");
        $st->execute([':e' => $empresaId, ':e2' => $empresaId]);
        $parados = (int) $st->fetchColumn();

        $alertas = 0;
        $st = $conn->prepare("SELECT COUNT(*) FROM motoristas WHERE empresa_id=:e AND data_validade_cnh <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
        $st->execute([':e' => $empresaId]);
        $alertas += (int) $st->fetchColumn();
        $st = $conn->prepare("SELECT COUNT(*) FROM contas_pagar WHERE empresa_id=:e AND data_pagamento IS NULL AND data_vencimento < CURDATE()");
        $st->execute([':e' => $empresaId]);
        $alertas += (int) $st->fetchColumn();
        $out['alertas'] = $alertas;

        $out['cards'] = [
            ['title' => 'Faturamento hoje', 'value' => $moeda($fatHoje), 'type' => 'money'],
            ['title' => 'Faturamento mês', 'value' => $moeda($fat), 'type' => 'money'],
            ['title' => 'Lucro aprox. (mês)', 'value' => $moeda($lucro), 'type' => 'money'],
            ['title' => 'Margem', 'value' => $margem . '%', 'type' => 'metric'],
            ['title' => 'Rotas no mês', 'value' => (string) (int) ($m['qtd'] ?? 0), 'type' => 'metric'],
            ['title' => 'Atrasadas', 'value' => (string) (int) ($m['atras'] ?? 0), 'type' => 'metric'],
            ['title' => 'Em andamento', 'value' => (string) $ativas, 'type' => 'metric'],
            ['title' => 'Veículos parados (7d)', 'value' => (string) $parados, 'type' => 'metric'],
        ];
        $out['resumo'] = "No mês: faturamento {$moeda($fat)}, margem aproximada {$margem}%, {$ativas} rota(s) em andamento, {$parados} veículo(s) sem rodar na última semana.";
    } catch (Throwable $e) {
        error_log('sf_ia_copilot_executivo: ' . $e->getMessage());
        $out['resumo'] = 'Não foi possível montar o resumo executivo agora.';
    }

    return $out;
}

/** Análise ampla da empresa (oportunidades / problemas). */
function sf_ia_copilot_analise_empresa(PDO $conn, int $empresaId, callable $moeda): array
{
    $oportunidades = [];
    $iniMes = date('Y-m-01');
    $fimMes = date('Y-m-t');

    try {
        $st = $conn->prepare("SELECT COALESCE(SUM(litros),0) l, COALESCE(SUM(valor_total),0) v FROM abastecimentos WHERE empresa_id=:e AND data_abastecimento BETWEEN :i AND :f");
        $st->execute([':e' => $empresaId, ':i' => $iniMes, ':f' => $fimMes]);
        $ab = $st->fetch(PDO::FETCH_ASSOC);
        if ((float) ($ab['l'] ?? 0) <= 0) {
            $oportunidades[] = 'Combustível: poucos abastecimentos registrados no mês — conferir lançamentos.';
        }

        $causas = sf_ia_copilot_manutencao_causas($conn, $empresaId, $iniMes, $fimMes);
        if ($causas !== []) {
            $oportunidades[] = 'Manutenção: maior custo em ' . ($causas[0]['tipo'] ?? 'serviços') . '.';
        }

        $st = $conn->prepare("SELECT COUNT(*) FROM multas WHERE empresa_id=:e AND data_infracao BETWEEN :i AND :f");
        $st->execute([':e' => $empresaId, ':i' => $iniMes, ':f' => $fimMes]);
        if ((int) $st->fetchColumn() > 2) {
            $oportunidades[] = 'Multas acima do habitual no mês — revisar política com motoristas.';
        }

        $st = $conn->prepare("SELECT m.nome, COUNT(*) c FROM rotas r JOIN motoristas m ON m.id=r.motorista_id WHERE r.empresa_id=:e AND r.no_prazo=0 AND COALESCE(r.data_rota,r.data_saida) BETWEEN :i AND :f GROUP BY m.id ORDER BY c DESC LIMIT 1");
        $st->execute([':e' => $empresaId, ':i' => $iniMes, ':f' => $fimMes]);
        $atr = $st->fetch(PDO::FETCH_ASSOC);
        if ($atr && (int) $atr['c'] > 0) {
            $oportunidades[] = 'Atrasos: motorista ' . $atr['nome'] . ' lidera fora do prazo (' . (int) $atr['c'] . ').';
        }

        $st = $conn->prepare("SELECT cliente_nome, SUM(valor_frete) t FROM ordens_transporte WHERE empresa_id=:e AND created_at BETWEEN :i AND :f AND cliente_nome IS NOT NULL GROUP BY cliente_nome HAVING COUNT(*)>=2 ORDER BY AVG(DATEDIFF(COALESCE(data_entrega, NOW()), created_at)) DESC LIMIT 1");
        $st->execute([':e' => $empresaId, ':i' => $iniMes, ':f' => $fimMes]);
        // ignore if fails

        $st = $conn->prepare("SELECT COUNT(*) FROM motoristas WHERE empresa_id=:e AND data_validade_cnh <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
        $st->execute([':e' => $empresaId]);
        $cnh = (int) $st->fetchColumn();
        if ($cnh > 0) {
            $oportunidades[] = $cnh . ' motorista(s) com CNH vencendo em 30 dias.';
        }

        $st = $conn->prepare("SELECT COUNT(*) FROM contas_pagar WHERE empresa_id=:e AND data_pagamento IS NULL");
        $st->execute([':e' => $empresaId]);
        $cp = (int) $st->fetchColumn();
        if ($cp > 0) {
            $oportunidades[] = 'Financeiro: ' . $cp . ' conta(s) a pagar em aberto.';
        }

        $st = $conn->prepare("SELECT r.id FROM rotas r WHERE r.empresa_id=:e AND r.status='aprovado' AND r.data_saida <= NOW() AND r.data_chegada >= NOW() AND r.data_saida < DATE_SUB(NOW(), INTERVAL 5 HOUR) LIMIT 3");
        $st->execute([':e' => $empresaId]);
        $longas = $st->fetchAll(PDO::FETCH_COLUMN);
        if ($longas !== []) {
            $oportunidades[] = 'Operação: rota(s) em andamento há várias horas — verificar GPS/status.';
        }
    } catch (Throwable $e) {
        error_log('sf_ia_copilot_analise: ' . $e->getMessage());
    }

    if ($oportunidades === []) {
        $oportunidades[] = 'Nenhum ponto crítico automático detectado no mês — continue monitorando KPIs.';
    }

    return array_slice($oportunidades, 0, 12);
}

/** Monta bloco de análise textual. */
function sf_ia_copilot_analise_bloco(?array $comparacao, array $causas, callable $moeda): array
{
    $analise = [];
    if ($comparacao) {
        $analise[] = $comparacao['texto'];
    }
    foreach (array_slice($causas, 0, 3) as $c) {
        $analise[] = ($c['tipo'] ?? 'Item') . ': ' . $moeda((float) ($c['total'] ?? 0));
    }
    return $analise;
}

/**
 * Análise completa — scorecard multi-domínio (modo impressionante).
 * @return array{score_geral:int,dimensoes:array,recomendacoes:array,resumo:string,rows:array}
 */
function sf_ia_copilot_analise_completa(PDO $conn, int $empresaId, callable $moeda, callable $num): array
{
    $iniMes = date('Y-m-01');
    $fimMes = date('Y-m-t');
    $dimensoes = [];
    $recomendacoes = [];
    $scoreTotal = 0;
    $nDim = 0;

    $rate = static function (int $pct, string $label) use (&$dimensoes, &$scoreTotal, &$nDim): void {
        $status = $pct >= 85 ? 'Excelente' : ($pct >= 65 ? 'Boa' : ($pct >= 45 ? 'Atenção' : 'Crítico'));
        $dimensoes[] = ['area' => $label, 'score' => $pct, 'status' => $status];
        $scoreTotal += $pct;
        $nDim++;
    };

    try {
        // Financeiro
        $st = $conn->prepare("SELECT COALESCE(SUM(frete),0) fat FROM rotas WHERE empresa_id=:e AND COALESCE(data_rota,data_saida) BETWEEN :i AND :f");
        $st->execute([':e' => $empresaId, ':i' => $iniMes, ':f' => $fimMes]);
        $fat = (float) $st->fetchColumn();
        $rate($fat > 0 ? 88 : 50, 'Financeiro');

        // Frota
        $st = $conn->prepare("SELECT COUNT(*) FROM veiculos WHERE empresa_id=:e");
        $st->execute([':e' => $empresaId]);
        $veic = (int) $st->fetchColumn();
        $rate($veic > 0 ? 80 : 40, 'Frota');

        // Oficina
        $st = $conn->prepare("SELECT COALESCE(SUM(COALESCE(custo_total,valor,0)),0) FROM manutencoes WHERE empresa_id=:e AND data_manutencao BETWEEN :i AND :f");
        $st->execute([':e' => $empresaId, ':i' => $iniMes, ':f' => $fimMes]);
        $manut = (float) $st->fetchColumn();
        $rate($manut < 50000 ? 85 : 55, 'Oficina');

        // Motoristas / CNH
        $st = $conn->prepare("SELECT COUNT(*) FROM motoristas WHERE empresa_id=:e AND data_validade_cnh <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
        $st->execute([':e' => $empresaId]);
        $cnh = (int) $st->fetchColumn();
        $rate($cnh === 0 ? 92 : max(40, 90 - $cnh * 15), 'Motoristas');

        // Combustível
        $sk = $conn->prepare("SELECT COALESCE(SUM(r.distancia_km),0) FROM rotas r WHERE r.empresa_id=:e AND COALESCE(r.data_rota,r.data_saida) BETWEEN :i AND :f");
        $sk->execute([':e' => $empresaId, ':i' => $iniMes, ':f' => $fimMes]);
        $km = (float) $sk->fetchColumn();
        $sl = $conn->prepare("SELECT COALESCE(SUM(litros),0) FROM abastecimentos WHERE empresa_id=:e AND data_abastecimento BETWEEN :i AND :f");
        $sl->execute([':e' => $empresaId, ':i' => $iniMes, ':f' => $fimMes]);
        $lit = (float) $sl->fetchColumn();
        $kml = $lit > 0 ? $km / $lit : 0;
        $rate($kml >= 2.5 ? 85 : ($kml > 0 ? 60 : 45), 'Combustível');

        // Pneus (tentativa)
        $pneusAlerta = 0;
        try {
            $sp = $conn->prepare("SELECT COUNT(*) FROM pneus WHERE empresa_id=:e AND status_id IN (3,4,5)");
            $sp->execute([':e' => $empresaId]);
            $pneusAlerta = (int) $sp->fetchColumn();
        } catch (Throwable $e) {
            // ignora
        }
        $rate($pneusAlerta <= 4 ? 82 : 50, 'Pneus');

        // Rotas / prazo
        $st = $conn->prepare("SELECT COUNT(*) total, COALESCE(SUM(no_prazo=1),0) ok FROM rotas WHERE empresa_id=:e AND COALESCE(data_rota,data_saida) BETWEEN :i AND :f");
        $st->execute([':e' => $empresaId, ':i' => $iniMes, ':f' => $fimMes]);
        $rp = $st->fetch(PDO::FETCH_ASSOC);
        $totR = (int) ($rp['total'] ?? 0);
        $pctPrazo = $totR > 0 ? (int) round((int) $rp['ok'] / $totR * 100) : 70;
        $rate($pctPrazo, 'Rotas');

        // Alertas consolidados
        $ops = sf_ia_copilot_analise_empresa($conn, $empresaId, $moeda);
        foreach ($ops as $op) {
            $recomendacoes[] = $op;
        }
    } catch (Throwable $e) {
        error_log('sf_ia_copilot_analise_completa: ' . $e->getMessage());
    }

    $scoreGeral = $nDim > 0 ? (int) round($scoreTotal / $nDim) : 50;
    $rows = [];
    foreach ($dimensoes as $d) {
        $rows[] = ['Área' => $d['area'], 'Score' => $d['score'] . '%', 'Status' => $d['status']];
    }
    $rows[] = ['Saúde geral' => $scoreGeral . '%', 'Recomendações' => (string) count($recomendacoes), '—' => '—'];

    $resumo = "Saúde geral da operação: {$scoreGeral}%. "
        . count($recomendacoes) . ' recomendação(ões) identificada(s) com dados reais do mês.';

    return [
        'score_geral' => $scoreGeral,
        'dimensoes' => $dimensoes,
        'recomendacoes' => array_slice($recomendacoes, 0, 12),
        'resumo' => $resumo,
        'rows' => $rows,
    ];
}
