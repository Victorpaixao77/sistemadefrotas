<?php
/**
 * API — IA de Decisão (consultor estratégico).
 * NÃO usa LLM livre: responde perguntas de negócio pré-definidas rodando queries
 * parametrizadas sobre dados reais e devolvendo RECOMENDAÇÕES de ação.
 * Somente leitura, filtrado por empresa.
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';

configure_session();
session_start();
require_authentication();
if (function_exists('session_write_close')) { session_write_close(); }

header('Content-Type: application/json; charset=utf-8');

$conn = getConnection();
$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if ($empresa_id < 1) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Não autorizado']); exit; }

$data_fim = date('Y-m-d');
$data_inicio = date('Y-m-d', strtotime('-12 months'));
$di = $data_inicio . ' 00:00:00';
$df = $data_fim . ' 23:59:59';
$brl = fn($n) => 'R$ ' . number_format((float) $n, 2, ',', '.');

try {
    $run = function (string $sql, array $params = []) use ($conn) {
        $st = $conn->prepare($sql); $st->execute($params); return $st->fetchAll(PDO::FETCH_ASSOC);
    };
    $cards = [];

    // ---- Veículos: uso, manutenção e ociosidade ----
    $veic = $run("SELECT v.id, v.placa, v.modelo,
            (SELECT COUNT(*) FROM rotas r WHERE r.veiculo_id = v.id AND r.empresa_id = :e1 AND r.data_rota BETWEEN :di1 AND :df1) AS rotas,
            (SELECT MAX(r2.data_rota) FROM rotas r2 WHERE r2.veiculo_id = v.id AND r2.empresa_id = :e2) AS ultima_rota,
            (SELECT COUNT(*) FROM manutencoes m WHERE m.veiculo_id = v.id AND m.empresa_id = :e3 AND m.data_manutencao BETWEEN :di2 AND :df2) AS manut,
            (SELECT ROUND(SUM(COALESCE(m2.custo_total,0)),2) FROM manutencoes m2 WHERE m2.veiculo_id = v.id AND m2.empresa_id = :e4 AND m2.data_manutencao BETWEEN :di3 AND :df3) AS custo_manut
        FROM veiculos v WHERE v.empresa_id = :e5",
        [':e1'=>$empresa_id,':di1'=>$di,':df1'=>$df,':e2'=>$empresa_id,':e3'=>$empresa_id,':di2'=>$di,':df2'=>$df,
         ':e4'=>$empresa_id,':di3'=>$di,':df3'=>$df,':e5'=>$empresa_id]);

    // Trocar/vender: pior relação manutenção × uso (muita manutenção, pouco uso).
    $piorVeic = null; $piorScore = -1;
    $ociosos = [];
    foreach ($veic as $v) {
        $rotas = (int) $v['rotas']; $manut = (int) $v['manut']; $custo = (float) $v['custo_manut'];
        $indice = ($manut * 8 + $custo / 1000) / max(1, $rotas); // quanto maior, pior
        if ($rotas + $manut > 0 && $indice > $piorScore) { $piorScore = $indice; $piorVeic = $v; $piorVeic['indice'] = $indice; }
        $diasSemRodar = $v['ultima_rota'] ? floor((time() - strtotime($v['ultima_rota'])) / 86400) : null;
        if ($diasSemRodar === null || $diasSemRodar > 60) { $ociosos[] = ['placa'=>$v['placa'],'modelo'=>$v['modelo'],'dias'=>$diasSemRodar]; }
    }
    if ($piorVeic && ((int)$piorVeic['manut'] >= 2 || (float)$piorVeic['custo_manut'] > 0)) {
        $cards[] = ['id'=>'trocar_veiculo','icone'=>'fa-truck-arrow-right','pergunta'=>'Qual veículo devo avaliar para troca/venda?',
            'veredito'=>$piorVeic['placa'].' — '.($piorVeic['modelo'] ?: 'veículo'),
            'nivel'=> ((int)$piorVeic['manut'] >= 4 ? 'critico' : 'aviso'),
            'fatos'=>[(int)$piorVeic['manut'].' manutenções no período', 'Custo de manutenção '.$brl($piorVeic['custo_manut']), (int)$piorVeic['rotas'].' rotas (uso)'],
            'acao'=>'Compare o custo de manter com o valor de mercado — pode compensar trocar.', 'link'=>'manutencoes.php'];
    }
    if (count($ociosos)) {
        $lista = array_slice(array_map(fn($o)=>$o['placa'].($o['dias']!==null?(' ('.(int)$o['dias'].'d)'):' (sem rotas)'), $ociosos), 0, 6);
        $cards[] = ['id'=>'veiculo_ocioso','icone'=>'fa-truck-ramp-box','pergunta'=>'Tenho veículos ociosos?',
            'veredito'=>count($ociosos).' veículo(s) sem rodar há +60 dias', 'nivel'=> (count($ociosos) > 2 ? 'aviso' : 'info'),
            'fatos'=>$lista, 'acao'=>'Realoque para rotas, alugue ou considere vender — parado é só custo.', 'link'=>'vehicles.php'];
    }

    // ---- Motoristas: melhor (promover) e pior (treinar) ----
    $mot = $run("SELECT m.id, m.nome, COUNT(r.id) rotas,
            AVG(CASE WHEN r.no_prazo=1 THEN 100 ELSE 0 END) pct_prazo,
            (SELECT COUNT(*) FROM multas mu WHERE mu.motorista_id=m.id AND mu.empresa_id=:e2) multas
        FROM motoristas m LEFT JOIN rotas r ON r.motorista_id=m.id AND r.empresa_id=:e1 AND r.data_rota BETWEEN :di AND :df
        WHERE m.empresa_id=:e3 GROUP BY m.id, m.nome HAVING rotas > 0",
        [':e1'=>$empresa_id,':e2'=>$empresa_id,':e3'=>$empresa_id,':di'=>$di,':df'=>$df]);
    $scoreM = function($r){ return round(0.7*(float)$r['pct_prazo'] + 0.3*max(0,100-(int)$r['multas']*10)); };
    if (count($mot)) {
        usort($mot, fn($a,$b)=>$scoreM($b)<=>$scoreM($a));
        $best = $mot[0]; $worst = end($mot);
        if ($scoreM($best) >= 70) {
            $cards[] = ['id'=>'promover_motorista','icone'=>'fa-medal','pergunta'=>'Qual motorista merece reconhecimento?',
                'veredito'=>$best['nome'].' — score '.$scoreM($best),'nivel'=>'info',
                'fatos'=>[round((float)$best['pct_prazo']).'% das rotas no prazo', (int)$best['multas'].' multas', (int)$best['rotas'].' rotas'],
                'acao'=>'Reconheça/premie — retém talento e serve de referência.', 'link'=>'scores.php'];
        }
        if (count($mot) > 1 && $scoreM($worst) < 60) {
            $cards[] = ['id'=>'treinar_motorista','icone'=>'fa-user-graduate','pergunta'=>'Quem precisa de treinamento?',
                'veredito'=>$worst['nome'].' — score '.$scoreM($worst),'nivel'=> ($scoreM($worst) < 40 ? 'critico':'aviso'),
                'fatos'=>[round((float)$worst['pct_prazo']).'% no prazo', (int)$worst['multas'].' multas', (int)$worst['rotas'].' rotas'],
                'acao'=>'Direcione treinamento (direção defensiva/pontualidade) e acompanhe.', 'link'=>'scores.php'];
        }
    }

    // ---- Clientes deficitários (margem negativa nas OTs) ----
    $cli = $run("SELECT cliente_nome, COUNT(*) ots, ROUND(SUM(valor_frete),2) fat,
            ROUND(SUM(valor_frete - COALESCE(valor_custo_estimado,0)),2) margem
        FROM ordens_transporte WHERE empresa_id=:e AND cliente_nome IS NOT NULL AND cliente_nome<>''
        GROUP BY cliente_nome HAVING margem < 0 ORDER BY margem ASC", [':e'=>$empresa_id]);
    if (count($cli)) {
        $c = $cli[0];
        $cards[] = ['id'=>'cliente_prejuizo','icone'=>'fa-triangle-exclamation','pergunta'=>'Qual cliente está dando prejuízo?',
            'veredito'=>$c['cliente_nome'].' — margem '.$brl($c['margem']),'nivel'=>'critico',
            'fatos'=>[(int)$c['ots'].' ordens', 'Faturou '.$brl($c['fat']), count($cli).' cliente(s) no prejuízo'],
            'acao'=>'Renegocie o frete ou reveja o custo — hoje esse cliente custa mais do que paga.', 'link'=>'clientes.php'];
    }

    // ---- Expansão de frota (utilização × margem) ----
    $tot = $run("SELECT COUNT(*) rotas, ROUND(SUM(frete),2) fat, ROUND(SUM(COALESCE(comissao,0)),2) com
        FROM rotas WHERE empresa_id=:e AND data_rota BETWEEN :di AND :df", [':e'=>$empresa_id,':di'=>$di,':df'=>$df])[0] ?? [];
    $comb = (float) ($run("SELECT ROUND(SUM(valor_total),2) t FROM abastecimentos WHERE empresa_id=:e AND data_abastecimento BETWEEN :di AND :df",[':e'=>$empresa_id,':di'=>$di,':df'=>$df])[0]['t'] ?? 0);
    $man = (float) ($run("SELECT ROUND(SUM(COALESCE(custo_total,0)),2) t FROM manutencoes WHERE empresa_id=:e AND data_manutencao BETWEEN :di AND :df",[':e'=>$empresa_id,':di'=>$di,':df'=>$df])[0]['t'] ?? 0);
    $nveic = (int) ($conn->query("SELECT COUNT(*) FROM veiculos WHERE empresa_id={$empresa_id}")->fetchColumn() ?: 0);
    $fat = (float) ($tot['fat'] ?? 0); $custo = $comb + $man + (float)($tot['com'] ?? 0);
    $margem = $fat > 0 ? ($fat - $custo) / $fat * 100 : 0;
    $rotasVeic = $nveic > 0 ? ((int)($tot['rotas'] ?? 0)) / $nveic : 0;
    if ($nveic > 0) {
        $expandir = $margem > 8 && $rotasVeic > 15 && count($ociosos) === 0;
        $cards[] = ['id'=>'expansao','icone'=>'fa-arrow-trend-up','pergunta'=>'Vale a pena comprar mais caminhões?',
            'veredito'=> $expandir ? 'Sim — a operação comporta expansão' : 'Ainda não — otimize antes de expandir',
            'nivel'=> $expandir ? 'info' : 'aviso',
            'fatos'=>['Margem operacional '.round($margem,1).'%', round($rotasVeic,1).' rotas por veículo', count($ociosos).' veículo(s) ocioso(s)'],
            'acao'=> $expandir ? 'Margem e utilização saudáveis — simule o ROI da compra no Simulador.' : 'Primeiro elimine ociosidade e melhore a margem; depois expanda.',
            'link'=>'simulador_rota.php'];
    }

    echo json_encode(['success' => true, 'periodo' => ['inicio'=>$data_inicio,'fim'=>$data_fim], 'decisoes' => $cards], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('ia_decisao.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao gerar as recomendações.']);
}
