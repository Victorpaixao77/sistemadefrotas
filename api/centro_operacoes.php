<?php
/**
 * Centro de Operações (Torre de Controle) — agrega, em tempo real, dados REAIS
 * já existentes (frota, viagens, KPIs, financeiro, alertas, vencimentos, rankings,
 * pendências, timeline, gráficos e um resumo em linguagem natural).
 * Somente leitura. NÃO inventa dados: onde não há dado, retorna 0/vazio.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php';

configure_session();
session_start();
require_authentication();

header('Content-Type: application/json; charset=UTF-8');

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if ($empresa_id < 1) {
    echo json_encode(['success' => false, 'error' => 'Empresa não identificada.']);
    exit;
}

$can_fin = function_exists('can_view_financial_data') && can_view_financial_data();
$can_fiscal = function_exists('can_access_fiscal_system') && can_access_fiscal_system();

/** Executa uma query preparada e devolve fetchColumn, silenciando tabela ausente. */
function co_col(PDO $c, string $sql, array $p = [], $def = 0) {
    try { $st = $c->prepare($sql); $st->execute($p); $v = $st->fetchColumn(); return $v === false ? $def : $v; }
    catch (Throwable $e) { return $def; }
}
function co_all(PDO $c, string $sql, array $p = []) {
    try { $st = $c->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable $e) { return []; }
}

function co_gps_estado(int $idadeSeg, ?float $velocidade): string
{
    if ($idadeSeg > 7200) {
        return 'offline';
    }
    if ($velocidade !== null && $velocidade > 5) {
        return 'rodando';
    }
    return 'parado';
}

try {
    $conn = getConnection();
    $ano = (int) date('Y'); $mes = (int) date('n');
    $E = [':e' => $empresa_id];

    /* ---------------- Frota ---------------- */
    $frota = ['ativo' => 0, 'manutencao' => 0, 'inativo' => 0, 'total' => 0];
    foreach (co_all($conn, 'SELECT status_id, COUNT(*) q FROM veiculos WHERE empresa_id = :e GROUP BY status_id', $E) as $r) {
        $q = (int) $r['q']; $frota['total'] += $q;
        if ((int) $r['status_id'] === 2) $frota['manutencao'] = $q;
        elseif ((int) $r['status_id'] === 3) $frota['inativo'] = $q;
        else $frota['ativo'] += $q;
    }

    /* ---------------- Viagens ---------------- */
    $emAndamento = (int) co_col($conn, "SELECT COUNT(*) FROM rotas WHERE empresa_id=:e AND status='aprovado' AND data_saida<=NOW() AND (data_chegada IS NULL OR data_chegada>=NOW())", $E);
    $programadas = (int) co_col($conn, "SELECT COUNT(*) FROM rotas WHERE empresa_id=:e AND status='aprovado' AND data_saida>NOW()", $E);
    $pendentesAprov = (int) co_col($conn, "SELECT COUNT(*) FROM rotas WHERE empresa_id=:e AND status='pendente'", $E);
    $atrasadas = (int) co_col($conn, "SELECT COUNT(*) FROM rotas WHERE empresa_id=:e AND status='aprovado' AND no_prazo=0 AND data_chegada IS NOT NULL AND data_chegada<NOW() AND YEAR(COALESCE(data_rota,data_saida))=:y AND MONTH(COALESCE(data_rota,data_saida))=:m", $E + [':y' => $ano, ':m' => $mes]);

    /* ---------------- KPIs do mês ---------------- */
    $kr = co_all($conn, "SELECT COALESCE(SUM(frete),0) fat, COALESCE(SUM(comissao),0) com, COALESCE(SUM(total_km),0) km, COUNT(*) via FROM rotas WHERE empresa_id=:e AND status='aprovado' AND YEAR(COALESCE(data_rota,data_saida))=:y AND MONTH(COALESCE(data_rota,data_saida))=:m", $E + [':y' => $ano, ':m' => $mes]);
    $kr = $kr[0] ?? ['fat' => 0, 'com' => 0, 'km' => 0, 'via' => 0];
    $litrosMes = (float) co_col($conn, "SELECT COALESCE(SUM(litros),0) FROM abastecimentos WHERE empresa_id=:e AND YEAR(data_abastecimento)=:y AND MONTH(data_abastecimento)=:m AND status='aprovado'", $E + [':y' => $ano, ':m' => $mes]);
    $custoComb = (float) co_col($conn, "SELECT COALESCE(SUM(valor_total),0) FROM abastecimentos WHERE empresa_id=:e AND YEAR(data_abastecimento)=:y AND MONTH(data_abastecimento)=:m AND status='aprovado'", $E + [':y' => $ano, ':m' => $mes]);
    $motoristasAtivos = (int) co_col($conn, "SELECT COUNT(DISTINCT motorista_id) FROM rotas WHERE empresa_id=:e AND status='aprovado' AND YEAR(COALESCE(data_rota,data_saida))=:y AND MONTH(COALESCE(data_rota,data_saida))=:m", $E + [':y' => $ano, ':m' => $mes]);

    $fat = (float) $kr['fat']; $com = (float) $kr['com']; $km = (float) $kr['km']; $via = (int) $kr['via'];
    $lucro = $fat - $com - $custoComb;
    $kpis = [
        'receita' => $fat,
        'lucro' => $lucro,
        'lucro_km' => $km > 0 ? $lucro / $km : 0,
        'receita_km' => $km > 0 ? $fat / $km : 0,
        'km' => $km,
        'consumo_medio' => $litrosMes > 0 ? $km / $litrosMes : 0,
        'disponibilidade' => $frota['total'] > 0 ? round($frota['ativo'] / $frota['total'] * 100) : 0,
        'ocupacao' => $frota['total'] > 0 ? round($emAndamento / $frota['total'] * 100) : 0,
        'viagens' => $via,
        'viagens_andamento' => $emAndamento,
        'viagens_atraso' => $atrasadas,
        'motoristas_ativos' => $motoristasAtivos,
    ];

    /* ---------------- Situação operacional ---------------- */
    $situacao = [
        'disponiveis' => max(0, $frota['ativo'] - $emAndamento),
        'rodando' => $emAndamento,
        'programadas' => $programadas,
        'manutencao' => $frota['manutencao'],
        'atraso' => $atrasadas,
        'inativos' => $frota['inativo'],
    ];

    /* ---------------- Financeiro ---------------- */
    $aReceber = (float) co_col($conn, "SELECT COALESCE(SUM(frete),0) FROM rotas WHERE empresa_id=:e AND status='pendente'", $E);
    $aPagar = (float) co_col($conn, "SELECT COALESCE(SUM(valor),0) FROM contas_pagar WHERE empresa_id=:e AND status_id<>2", $E);
    $pedagioMes = (float) co_col($conn, "SELECT COALESCE(SUM(pedagios),0) FROM despesas_viagem dv JOIN rotas r ON r.id=dv.rota_id WHERE r.empresa_id=:e AND YEAR(COALESCE(r.data_rota,r.data_saida))=:y AND MONTH(COALESCE(r.data_rota,r.data_saida))=:m", $E + [':y' => $ano, ':m' => $mes]);
    $manutMes = (float) co_col($conn, "SELECT COALESCE(SUM(valor),0) FROM manutencoes WHERE empresa_id=:e AND YEAR(data_manutencao)=:y AND MONTH(data_manutencao)=:m", $E + [':y' => $ano, ':m' => $mes]);
    $financeiro = [
        'receita' => $fat, 'lucro' => $lucro, 'a_receber' => $aReceber, 'a_pagar' => $aPagar,
        'margem' => $fat > 0 ? round($lucro / $fat * 100, 1) : 0,
        'combustivel' => $custoComb, 'pedagio' => $pedagioMes, 'manutencao' => $manutMes, 'comissao' => $com,
    ];

    /* ---------------- Alertas (crit/atencao/info) ---------------- */
    $criticos = []; $atencao = []; $info = [];
    $cnh = co_all($conn, "SELECT nome, DATEDIFF(data_validade_cnh,CURDATE()) d FROM motoristas WHERE empresa_id=:e AND data_validade_cnh IS NOT NULL AND data_validade_cnh<>'0000-00-00' AND data_validade_cnh<=DATE_ADD(CURDATE(),INTERVAL 30 DAY) ORDER BY data_validade_cnh LIMIT 30", $E);
    foreach ($cnh as $r) { $t = ['titulo' => 'CNH ' . ((int)$r['d'] < 0 ? 'vencida' : 'vencendo'), 'sub' => $r['nome'], 'link' => 'gestao_motoristas.php']; (int)$r['d'] < 0 ? $criticos[] = $t : $atencao[] = $t; }
    $mult = (int) co_col($conn, "SELECT COUNT(*) FROM multas WHERE empresa_id=:e AND status_pagamento='pendente'", $E);
    if ($mult > 0) $atencao[] = ['titulo' => 'Multas pendentes', 'sub' => $mult . ' multa(s)', 'link' => 'multas.php'];
    $pneu = (int) co_col($conn, "SELECT COUNT(*) FROM pneus WHERE empresa_id=:e AND ((vida_util_km>0 AND km_instalacao>=vida_util_km) OR km_instalacao>80000)", $E);
    if ($pneu > 0) $criticos[] = ['titulo' => 'Pneus em estado crítico', 'sub' => $pneu . ' pneu(s)', 'link' => 'pneus_dashboard.php'];
    $revAtras = (int) co_col($conn, "SELECT COUNT(*) FROM veiculos WHERE empresa_id=:e AND (status_id=1 OR status_id IS NULL) AND km_atual IS NOT NULL AND km_ultima_manutencao IS NOT NULL AND (km_atual-km_ultima_manutencao)>=10000", $E);
    if ($revAtras > 0) $atencao[] = ['titulo' => 'Revisão próxima/atrasada', 'sub' => $revAtras . ' veículo(s)', 'link' => 'manutencao_preditiva.php'];
    foreach (co_all($conn, "SELECT placa, tipo, DATEDIFF(vencimento_seguro,CURDATE()) d FROM implementos WHERE empresa_id=:e AND status<>'inativo' AND vencimento_seguro IS NOT NULL AND vencimento_seguro<=DATE_ADD(CURDATE(),INTERVAL 30 DAY) LIMIT 20", $E) as $r) {
        $t = ['titulo' => 'Seguro ' . ((int)$r['d'] < 0 ? 'vencido' : 'vence em ' . (int)$r['d'] . 'd'), 'sub' => $r['placa'] . ' · ' . $r['tipo'], 'link' => 'implementos.php'];
        (int)$r['d'] < 0 ? $criticos[] = $t : $atencao[] = $t;
    }
    // Informativos: itens recentes
    foreach (co_all($conn, "SELECT numero FROM ordens_transporte WHERE empresa_id=:e ORDER BY created_at DESC LIMIT 3", $E) as $r) {
        $info[] = ['titulo' => 'Ordem de transporte', 'sub' => $r['numero'], 'link' => 'ordens_transporte.php'];
    }
    $alertas = ['criticos' => $criticos, 'atencao' => $atencao, 'informativos' => $info];

    /* ---------------- Próximos vencimentos ---------------- */
    $vencimentos = [];
    $cv = co_all($conn, "SELECT MIN(DATEDIFF(data_validade_cnh,CURDATE())) dmin, COUNT(*) q FROM motoristas WHERE empresa_id=:e AND data_validade_cnh IS NOT NULL AND data_validade_cnh<>'0000-00-00' AND data_validade_cnh<=DATE_ADD(CURDATE(),INTERVAL 60 DAY)", $E);
    if (!empty($cv[0]['q'])) $vencimentos[] = ['tipo' => 'CNH', 'dias' => (int)$cv[0]['dmin'], 'qtd' => (int)$cv[0]['q'], 'link' => 'gestao_motoristas.php'];
    $sv = co_all($conn, "SELECT MIN(DATEDIFF(vencimento_seguro,CURDATE())) dmin, COUNT(*) q FROM implementos WHERE empresa_id=:e AND vencimento_seguro IS NOT NULL AND vencimento_seguro<=DATE_ADD(CURDATE(),INTERVAL 60 DAY) AND status<>'inativo'", $E);
    if (!empty($sv[0]['q'])) $vencimentos[] = ['tipo' => 'Seguro', 'dias' => (int)$sv[0]['dmin'], 'qtd' => (int)$sv[0]['q'], 'link' => 'implementos.php'];
    $lv = co_all($conn, "SELECT MIN(DATEDIFF(vencimento_licenciamento,CURDATE())) dmin, COUNT(*) q FROM implementos WHERE empresa_id=:e AND vencimento_licenciamento IS NOT NULL AND vencimento_licenciamento<=DATE_ADD(CURDATE(),INTERVAL 60 DAY) AND status<>'inativo'", $E);
    if (!empty($lv[0]['q'])) $vencimentos[] = ['tipo' => 'Licenciamento', 'dias' => (int)$lv[0]['dmin'], 'qtd' => (int)$lv[0]['q'], 'link' => 'implementos.php'];

    /* ---------------- Rankings ---------------- */
    $topMot = co_all($conn, "SELECT m.nome, COALESCE(SUM(r.total_km),0) km,
        (SELECT COALESCE(SUM(a.litros),0) FROM abastecimentos a WHERE a.motorista_id=m.id AND a.empresa_id=m.empresa_id AND YEAR(a.data_abastecimento)=:y AND MONTH(a.data_abastecimento)=:m) litros
        FROM motoristas m JOIN rotas r ON r.motorista_id=m.id AND r.empresa_id=m.empresa_id AND r.status='aprovado' AND YEAR(COALESCE(r.data_rota,r.data_saida))=:y2 AND MONTH(COALESCE(r.data_rota,r.data_saida))=:m2
        WHERE m.empresa_id=:e GROUP BY m.id, m.nome HAVING km>0 ORDER BY km DESC LIMIT 5",
        $E + [':y' => $ano, ':m' => $mes, ':y2' => $ano, ':m2' => $mes]);
    $rankMot = array_map(function ($r) {
        $kml = ((float)$r['litros'] > 0) ? round((float)$r['km'] / (float)$r['litros'], 1) : null;
        return ['nome' => $r['nome'], 'km' => (float)$r['km'], 'kml' => $kml];
    }, $topMot);
    $topVei = co_all($conn, "SELECT v.placa, COALESCE(SUM(r.frete),0)-COALESCE(SUM(r.comissao),0) lucro FROM veiculos v JOIN rotas r ON r.veiculo_id=v.id AND r.empresa_id=v.empresa_id AND r.status='aprovado' AND YEAR(COALESCE(r.data_rota,r.data_saida))=:y AND MONTH(COALESCE(r.data_rota,r.data_saida))=:m WHERE v.empresa_id=:e GROUP BY v.id,v.placa HAVING lucro<>0 ORDER BY lucro DESC LIMIT 5", $E + [':y' => $ano, ':m' => $mes]);
    $rankings = ['motoristas' => $rankMot, 'veiculos' => array_map(fn($r) => ['placa' => $r['placa'], 'lucro' => (float)$r['lucro']], $topVei)];

    /* ---------------- Pendências ---------------- */
    $pend = [
        'cte' => (int) co_col($conn, "SELECT COUNT(*) FROM fiscal_cte WHERE empresa_id=:e AND status='rascunho'", $E),
        'mdfe' => (int) co_col($conn, "SELECT COUNT(*) FROM fiscal_mdfe WHERE empresa_id=:e AND status IN('rascunho','pendente')", $E),
        'ordens' => (int) co_col($conn, "SELECT COUNT(*) FROM ordens_transporte WHERE empresa_id=:e AND status='cotacao'", $E),
        'aprovacoes' => (int) co_col($conn, "SELECT COUNT(*) FROM rotas WHERE empresa_id=:e AND status='pendente'", $E),
        'abastecimentos' => (int) co_col($conn, "SELECT COUNT(*) FROM abastecimentos WHERE empresa_id=:e AND status='pendente'", $E),
        'manutencoes' => (int) co_col($conn, "SELECT COUNT(*) FROM manutencoes m JOIN status_manutencao s ON s.id=m.status_manutencao_id WHERE m.empresa_id=:e AND s.nome NOT IN('Concluída','Concluido','Finalizada')", $E),
    ];

    /* ---------------- Timeline (eventos recentes reais) ---------------- */
    $tl = [];
    foreach (co_all($conn, "SELECT COALESCE(data_saida,data_rota) dh, 'viagem' tipo, CONCAT('Viagem ', COALESCE(estado_origem,''),'→',COALESCE(estado_destino,'')) txt FROM rotas WHERE empresa_id=:e ORDER BY dh DESC LIMIT 6", $E) as $r) $tl[] = ['dh' => $r['dh'], 'tipo' => 'viagem', 'txt' => $r['txt']];
    foreach (co_all($conn, "SELECT data_abastecimento dh, CONCAT('Abastecimento R$ ', FORMAT(valor_total,2)) txt FROM abastecimentos WHERE empresa_id=:e ORDER BY dh DESC LIMIT 5", $E) as $r) $tl[] = ['dh' => $r['dh'], 'tipo' => 'abastecimento', 'txt' => $r['txt']];
    foreach (co_all($conn, "SELECT data_emissao dh, CONCAT('CT-e ', numero_cte, ' — ', status) txt FROM fiscal_cte WHERE empresa_id=:e ORDER BY dh DESC LIMIT 4", $E) as $r) $tl[] = ['dh' => $r['dh'], 'tipo' => 'cte', 'txt' => $r['txt']];
    usort($tl, fn($a, $b) => strcmp((string)$b['dh'], (string)$a['dh']));
    $timeline = array_slice($tl, 0, 12);

    /* ---------------- Gráficos (30 dias) ---------------- */
    $fatDia = co_all($conn, "SELECT DATE(COALESCE(data_rota,data_saida)) d, SUM(frete) v FROM rotas WHERE empresa_id=:e AND status='aprovado' AND COALESCE(data_rota,data_saida)>=DATE_SUB(CURDATE(),INTERVAL 30 DAY) GROUP BY d ORDER BY d", $E);
    $conDia = co_all($conn, "SELECT DATE(data_abastecimento) d, SUM(litros) v FROM abastecimentos WHERE empresa_id=:e AND data_abastecimento>=DATE_SUB(CURDATE(),INTERVAL 30 DAY) GROUP BY d ORDER BY d", $E);
    $charts = [
        'faturamento' => ['labels' => array_map(fn($r) => date('d/m', strtotime($r['d'])), $fatDia), 'values' => array_map(fn($r) => (float)$r['v'], $fatDia)],
        'consumo' => ['labels' => array_map(fn($r) => date('d/m', strtotime($r['d'])), $conDia), 'values' => array_map(fn($r) => (float)$r['v'], $conDia)],
    ];

    /* ---------------- Resumo IA (linguagem natural, de dado real) ---------------- */
    $h = (int) date('H'); $saud = $h < 12 ? 'Bom dia' : ($h < 18 ? 'Boa tarde' : 'Boa noite');
    $frases = [];
    if ($emAndamento > 0) {
        $frases[] = "$emAndamento viagem(ns) em andamento agora.";
    } else {
        $frases[] = 'Nenhuma viagem em andamento no momento.';
    }
    if ($pneu > 0) {
        $frases[] = "$pneu pneu(s) em estado crítico.";
    }
    if (!empty($cv[0]['q'])) {
        $frases[] = $cv[0]['q'] . ' CNH vencendo/vencida(s).';
    }
    if ($can_fin) {
        if ($fat > 0) {
            $frases[] = 'Faturamento do mês: R$ ' . number_format($fat, 2, ',', '.') . ' (lucro R$ ' . number_format($lucro, 2, ',', '.') . ').';
        } else {
            $frases[] = 'Ainda não há faturamento lançado neste mês.';
        }
    }
    if ($revAtras > 0) {
        $frases[] = "Recomendo agendar revisão de $revAtras veículo(s).";
    }
    $ia = ['saudacao' => $saud, 'frases' => $frases];

    /* ---------------- GPS mapa (últimas posições) ---------------- */
    $gpsPosicoes = [];
    foreach (co_all($conn, "
        SELECT g.veiculo_id, v.placa, g.latitude, g.longitude, g.velocidade,
               COALESCE(g.ultima_atualizacao, g.data_hora) AS ref_hora,
               TIMESTAMPDIFF(SECOND, COALESCE(g.ultima_atualizacao, g.data_hora), NOW()) AS idade_seg
        FROM gps_ultima_posicao g
        INNER JOIN veiculos v ON v.id = g.veiculo_id AND v.empresa_id = :e
        WHERE g.empresa_id = :e
          AND g.latitude IS NOT NULL AND g.longitude IS NOT NULL
          AND ABS(g.latitude) > 0.0001 AND ABS(g.longitude) > 0.0001
        ORDER BY ref_hora DESC
        LIMIT 80
    ", $E) as $r) {
        $vel = isset($r['velocidade']) && $r['velocidade'] !== null && $r['velocidade'] !== '' ? (float) $r['velocidade'] : null;
        $idade = (int) ($r['idade_seg'] ?? 999999);
        $gpsPosicoes[] = [
            'veiculo_id' => (int) $r['veiculo_id'],
            'placa' => (string) ($r['placa'] ?? ''),
            'lat' => (float) $r['latitude'],
            'lng' => (float) $r['longitude'],
            'velocidade' => $vel,
            'idade_seg' => $idade,
            'estado' => co_gps_estado($idade, $vel),
        ];
    }

    /* ---------------- Status de sistema (somente o que é verificável) ---------------- */
    $gpsRecent = (int) co_col($conn, "SELECT COUNT(*) FROM gps_logs WHERE empresa_id=:e AND data_hora>=DATE_SUB(NOW(),INTERVAL 2 HOUR)", $E);
    $gpsUltima = (int) co_col($conn, "SELECT COUNT(*) FROM gps_ultima_posicao WHERE empresa_id=:e", $E);
    $gpsStatus = 'off';
    if ($gpsRecent > 0) {
        $gpsStatus = 'online';
    } elseif ($gpsUltima > 0) {
        $gpsStatus = 'idle';
    }
    $status = [
        'sistema' => 'online',
        'gps' => $gpsStatus,
        'gps_veiculos' => count($gpsPosicoes),
    ];
    if ($can_fiscal) {
        $status['cte'] = $pend['cte'] > 0 ? 'warn' : 'ok';
        $status['mdfe'] = $pend['mdfe'] > 0 ? 'warn' : 'ok';
        $status['cte_pendentes'] = $pend['cte'];
        $status['mdfe_pendentes'] = $pend['mdfe'];
    }

    if (!$can_fin) {
        $kpis = [
            'km' => $kpis['km'],
            'consumo_medio' => $kpis['consumo_medio'],
            'disponibilidade' => $kpis['disponibilidade'],
            'ocupacao' => $kpis['ocupacao'],
            'viagens' => $kpis['viagens'],
            'viagens_andamento' => $kpis['viagens_andamento'],
            'viagens_atraso' => $kpis['viagens_atraso'],
            'motoristas_ativos' => $kpis['motoristas_ativos'],
        ];
        $financeiro = null;
        $charts['faturamento'] = ['labels' => [], 'values' => []];
        $rankings['veiculos'] = [];
    }

    echo json_encode([
        'success' => true,
        'atualizado_em' => date('H:i:s'),
        'permissoes' => ['financeiro' => $can_fin, 'fiscal' => $can_fiscal],
        'frota' => $frota,
        'situacao' => $situacao,
        'kpis' => $kpis,
        'financeiro' => $financeiro,
        'alertas' => $alertas,
        'vencimentos' => $vencimentos,
        'rankings' => $rankings,
        'pendencias' => $pend,
        'timeline' => $timeline,
        'charts' => $charts,
        'ia' => $ia,
        'status' => $status,
        'gps_posicoes' => $gpsPosicoes,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('api/centro_operacoes.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao carregar o Centro de Operações.']);
}
