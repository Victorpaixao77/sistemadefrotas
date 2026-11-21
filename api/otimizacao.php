<?php
/**
 * API — Otimização Inteligente (melhor veículo/motorista para uma viagem).
 * Ranqueia veículos por custo/km real e disponibilidade, e motoristas por score.
 * Só leitura, por empresa. Sem dado fictício.
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

$di = date('Y-m-d', strtotime('-12 months')) . ' 00:00:00';
$df = date('Y-m-d') . ' 23:59:59';

try {
    // ---- Veículos: custo/km real + disponibilidade ----
    $veic = $conn->prepare("SELECT v.id, v.placa, v.modelo, v.custo_operacional_km,
            (SELECT COALESCE(SUM(distancia_km),0) FROM rotas r WHERE r.veiculo_id=v.id AND r.empresa_id=:e1 AND r.data_rota BETWEEN :di1 AND :df1) km,
            (SELECT COALESCE(SUM(a.valor_total),0) FROM abastecimentos a WHERE a.veiculo_id=v.id AND a.empresa_id=:e2 AND a.data_abastecimento BETWEEN :di2 AND :df2) comb,
            (SELECT COALESCE(SUM(COALESCE(m.custo_total,0)),0) FROM manutencoes m WHERE m.veiculo_id=v.id AND m.empresa_id=:e3 AND m.data_manutencao BETWEEN :di3 AND :df3) manut,
            (SELECT MAX(r2.data_rota) FROM rotas r2 WHERE r2.veiculo_id=v.id AND r2.empresa_id=:e4) ultima
        FROM veiculos v WHERE v.empresa_id=:e5");
    $veic->execute([':e1'=>$empresa_id,':di1'=>$di,':df1'=>$df,':e2'=>$empresa_id,':di2'=>$di,':df2'=>$df,
        ':e3'=>$empresa_id,':di3'=>$di,':df3'=>$df,':e4'=>$empresa_id,':e5'=>$empresa_id]);
    $veiculos = [];
    foreach ($veic->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $km = (float) $r['km'];
        $custo = (float) $r['comb'] + (float) $r['manut'];
        $ckm = $km > 0 ? round($custo / $km, 2) : (float) ($r['custo_operacional_km'] ?: 0);
        $dias = $r['ultima'] ? floor((time() - strtotime($r['ultima'])) / 86400) : null;
        $veiculos[] = [
            'id' => (int) $r['id'], 'placa' => $r['placa'], 'modelo' => $r['modelo'],
            'custo_km' => $ckm, 'km' => $km,
            'disponivel' => ($dias === null || $dias >= 2), // sem rota há 2+ dias = livre
            'dias_parado' => $dias,
        ];
    }
    // Ordena: disponíveis primeiro, depois menor custo/km (com custo>0 no topo, custo 0 = sem base ao fim)
    usort($veiculos, function ($a, $b) {
        if ($a['disponivel'] !== $b['disponivel']) return $a['disponivel'] ? -1 : 1;
        $ca = $a['custo_km'] > 0 ? $a['custo_km'] : 1e9;
        $cb = $b['custo_km'] > 0 ? $b['custo_km'] : 1e9;
        return $ca <=> $cb;
    });

    // ---- Motoristas: score (pontualidade + multas) + disponibilidade ----
    $mot = $conn->prepare("SELECT m.id, m.nome,
            COUNT(r.id) rotas,
            AVG(CASE WHEN r.no_prazo=1 THEN 100 ELSE 0 END) prazo,
            (SELECT COUNT(*) FROM multas mu WHERE mu.motorista_id=m.id AND mu.empresa_id=:e2) multas,
            (SELECT MAX(r2.data_rota) FROM rotas r2 WHERE r2.motorista_id=m.id AND r2.empresa_id=:e3) ultima
        FROM motoristas m LEFT JOIN rotas r ON r.motorista_id=m.id AND r.empresa_id=:e1 AND r.data_rota BETWEEN :di AND :df
        WHERE m.empresa_id=:e4 GROUP BY m.id, m.nome");
    $mot->execute([':e1'=>$empresa_id,':e2'=>$empresa_id,':e3'=>$empresa_id,':e4'=>$empresa_id,':di'=>$di,':df'=>$df]);
    $motoristas = [];
    foreach ($mot->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $score = (int) round(0.7 * (float) $r['prazo'] + 0.3 * max(0, 100 - (int) $r['multas'] * 10));
        $dias = $r['ultima'] ? floor((time() - strtotime($r['ultima'])) / 86400) : null;
        $motoristas[] = [
            'id' => (int) $r['id'], 'nome' => $r['nome'], 'score' => $score,
            'rotas' => (int) $r['rotas'], 'prazo' => round((float) $r['prazo']), 'multas' => (int) $r['multas'],
            'disponivel' => ($dias === null || $dias >= 2), 'dias_parado' => $dias,
        ];
    }
    usort($motoristas, function ($a, $b) {
        if ($a['disponivel'] !== $b['disponivel']) return $a['disponivel'] ? -1 : 1;
        return $b['score'] <=> $a['score'];
    });

    // Recomendados (melhor disponível de cada)
    $recVeic = null; foreach ($veiculos as $x) { if ($x['disponivel'] && $x['custo_km'] > 0) { $recVeic = $x; break; } }
    if (!$recVeic) { $recVeic = $veiculos[0] ?? null; }
    $recMot = null; foreach ($motoristas as $x) { if ($x['disponivel'] && $x['rotas'] > 0) { $recMot = $x; break; } }
    if (!$recMot) { $recMot = $motoristas[0] ?? null; }

    echo json_encode([
        'success' => true,
        'recomendado' => ['veiculo' => $recVeic, 'motorista' => $recMot],
        'veiculos' => array_slice($veiculos, 0, 10),
        'motoristas' => array_slice($motoristas, 0, 10),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('otimizacao.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao calcular a otimização.']);
}
