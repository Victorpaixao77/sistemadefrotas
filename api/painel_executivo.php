<?php
/**
 * API — Painel Executivo (visão estratégica da frota para diretoria).
 * KPIs consolidados + tendência faturamento/lucro + rankings. Somente leitura, por empresa.
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

$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-12 months'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_inicio)) { $data_inicio = date('Y-m-d', strtotime('-12 months')); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_fim)) { $data_fim = date('Y-m-d'); }
$di = $data_inicio . ' 00:00:00';
$df = $data_fim . ' 23:59:59';

try {
    $q = function (string $sql) use ($conn, $empresa_id, $di, $df) {
        $st = $conn->prepare($sql);
        $st->bindValue(':e', $empresa_id, PDO::PARAM_INT);
        $st->bindValue(':di', $di);
        $st->bindValue(':df', $df);
        $st->execute();
        return $st;
    };

    // Faturamento / km / comissão / rotas (base: rotas por data_rota).
    $rot = $q("SELECT
            ROUND(SUM(frete), 2) AS faturamento,
            ROUND(SUM(COALESCE(distancia_km, 0)), 0) AS km,
            ROUND(SUM(COALESCE(comissao, 0)), 2) AS comissao,
            COUNT(*) AS rotas,
            COUNT(DISTINCT veiculo_id) AS veic_ativos,
            COUNT(DISTINCT motorista_id) AS mot_ativos
        FROM rotas WHERE empresa_id = :e AND data_rota BETWEEN :di AND :df")->fetch(PDO::FETCH_ASSOC) ?: [];

    $custo_comb = (float) ($q("SELECT ROUND(SUM(valor_total),2) t FROM abastecimentos
        WHERE empresa_id = :e AND data_abastecimento BETWEEN :di AND :df")->fetchColumn() ?: 0);
    $custo_manut = (float) ($q("SELECT ROUND(SUM(COALESCE(custo_total,0)),2) t FROM manutencoes
        WHERE empresa_id = :e AND data_manutencao BETWEEN :di AND :df")->fetchColumn() ?: 0);

    $faturamento = (float) ($rot['faturamento'] ?? 0);
    $comissao = (float) ($rot['comissao'] ?? 0);
    $km = (float) ($rot['km'] ?? 0);
    $rotas = (int) ($rot['rotas'] ?? 0);
    $custo_total = $custo_comb + $custo_manut + $comissao;
    $lucro = $faturamento - $custo_total;

    $veic_total = (int) ($conn->query("SELECT COUNT(*) FROM veiculos WHERE empresa_id = {$empresa_id}")->fetchColumn() ?: 0);

    // Tendência: faturamento e custo por mês (mescla no PHP).
    $fatMes = $q("SELECT DATE_FORMAT(data_rota,'%Y-%m') ym, ROUND(SUM(frete),2) v, ROUND(SUM(COALESCE(comissao,0)),2) c
        FROM rotas WHERE empresa_id = :e AND data_rota BETWEEN :di AND :df GROUP BY ym")->fetchAll(PDO::FETCH_ASSOC);
    $combMes = $q("SELECT DATE_FORMAT(data_abastecimento,'%Y-%m') ym, ROUND(SUM(valor_total),2) v
        FROM abastecimentos WHERE empresa_id = :e AND data_abastecimento BETWEEN :di AND :df GROUP BY ym")->fetchAll(PDO::FETCH_ASSOC);
    $manMes = $q("SELECT DATE_FORMAT(data_manutencao,'%Y-%m') ym, ROUND(SUM(COALESCE(custo_total,0)),2) v
        FROM manutencoes WHERE empresa_id = :e AND data_manutencao BETWEEN :di AND :df GROUP BY ym")->fetchAll(PDO::FETCH_ASSOC);
    $meses = [];
    foreach ($fatMes as $r) { $meses[$r['ym']]['fat'] = (float) $r['v']; $meses[$r['ym']]['custo'] = ($meses[$r['ym']]['custo'] ?? 0) + (float) $r['c']; }
    foreach ($combMes as $r) { $meses[$r['ym']]['custo'] = ($meses[$r['ym']]['custo'] ?? 0) + (float) $r['v']; }
    foreach ($manMes as $r) { $meses[$r['ym']]['custo'] = ($meses[$r['ym']]['custo'] ?? 0) + (float) $r['v']; }
    ksort($meses);
    $tendencia = [];
    foreach ($meses as $ym => $m) {
        $f = $m['fat'] ?? 0; $c = $m['custo'] ?? 0;
        $tendencia[] = ['ym' => $ym, 'faturamento' => round($f, 2), 'lucro' => round($f - $c, 2)];
    }

    // Rankings (top 5).
    $topRotas = $q("SELECT id, CONCAT(COALESCE(cidade_origem_id,''),' → ',COALESCE(cidade_destino_id,'')) rota,
            estado_origem, estado_destino, ROUND(frete,2) frete
        FROM rotas WHERE empresa_id = :e AND data_rota BETWEEN :di AND :df AND frete > 0
        ORDER BY frete DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $topMot = $q("SELECT m.nome, ROUND(SUM(r.frete),2) fat, COUNT(*) rotas
        FROM rotas r JOIN motoristas m ON m.id = r.motorista_id
        WHERE r.empresa_id = :e AND r.data_rota BETWEEN :di AND :df
        GROUP BY r.motorista_id, m.nome ORDER BY fat DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $topVeic = $q("SELECT v.placa, v.modelo, ROUND(SUM(r.frete),2) fat, COUNT(*) rotas
        FROM rotas r JOIN veiculos v ON v.id = r.veiculo_id
        WHERE r.empresa_id = :e AND r.data_rota BETWEEN :di AND :df
        GROUP BY r.veiculo_id, v.placa, v.modelo ORDER BY fat DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'periodo' => ['inicio' => $data_inicio, 'fim' => $data_fim],
        'kpis' => [
            'faturamento' => $faturamento,
            'custo_total' => round($custo_total, 2),
            'lucro' => round($lucro, 2),
            'margem' => $faturamento > 0 ? round($lucro / $faturamento * 100, 1) : 0,
            'km' => $km,
            'custo_km' => $km > 0 ? round($custo_total / $km, 2) : 0,
            'rotas' => $rotas,
            'ticket_medio' => $rotas > 0 ? round($faturamento / $rotas, 2) : 0,
            'veic_ativos' => (int) ($rot['veic_ativos'] ?? 0),
            'veic_total' => $veic_total,
            'mot_ativos' => (int) ($rot['mot_ativos'] ?? 0),
            'custo_combustivel' => $custo_comb,
            'custo_manutencao' => $custo_manut,
            'custo_comissao' => $comissao,
        ],
        'tendencia' => $tendencia,
        'rankings' => ['rotas' => $topRotas, 'motoristas' => $topMot, 'veiculos' => $topVeic],
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('painel_executivo.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar o painel executivo.']);
}
