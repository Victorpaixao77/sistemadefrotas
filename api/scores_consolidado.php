<?php
/**
 * API — Score Consolidado (nota 0–100 para Motoristas, Veículos e Clientes).
 * Fórmulas transparentes sobre dados reais; somente leitura, por empresa.
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

/** Faixa textual da nota. */
function score_band(float $s): string {
    if ($s >= 85) return 'Excelente';
    if ($s >= 70) return 'Bom';
    if ($s >= 50) return 'Regular';
    return 'Atenção';
}

try {
    // ---- MOTORISTAS: pontualidade (no_prazo) + multas ----
    $st = $conn->prepare("SELECT m.id, m.nome,
            COUNT(r.id) AS rotas,
            AVG(CASE WHEN r.no_prazo = 1 THEN 100 ELSE 0 END) AS pct_prazo,
            (SELECT COUNT(*) FROM multas mu WHERE mu.motorista_id = m.id AND mu.empresa_id = :e2) AS multas
        FROM motoristas m
        LEFT JOIN rotas r ON r.motorista_id = m.id AND r.empresa_id = :e1
            AND r.data_rota BETWEEN :di AND :df
        WHERE m.empresa_id = :e3
        GROUP BY m.id, m.nome
        HAVING rotas > 0
        ORDER BY m.nome");
    $st->execute([':e1' => $empresa_id, ':e2' => $empresa_id, ':e3' => $empresa_id, ':di' => $di, ':df' => $df]);
    $motoristas = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $prazo = (float) $r['pct_prazo'];
        $multas = (int) $r['multas'];
        $score = round(0.70 * $prazo + 0.30 * max(0, 100 - $multas * 10));
        $motoristas[] = [
            'id' => (int) $r['id'], 'nome' => $r['nome'], 'score' => (int) $score, 'faixa' => score_band($score),
            'rotas' => (int) $r['rotas'], 'pct_prazo' => round($prazo, 0), 'multas' => $multas,
        ];
    }
    usort($motoristas, fn($a, $b) => $b['score'] <=> $a['score']);

    // ---- VEÍCULOS: uso (rotas) + poucas manutenções ----
    $st = $conn->prepare("SELECT v.id, v.placa, v.modelo,
            COUNT(r.id) AS rotas,
            (SELECT COUNT(*) FROM manutencoes mn WHERE mn.veiculo_id = v.id AND mn.empresa_id = :e2
                AND mn.data_manutencao BETWEEN :di2 AND :df2) AS manut
        FROM veiculos v
        LEFT JOIN rotas r ON r.veiculo_id = v.id AND r.empresa_id = :e1
            AND r.data_rota BETWEEN :di1 AND :df1
        WHERE v.empresa_id = :e3
        GROUP BY v.id, v.placa, v.modelo
        ORDER BY v.placa");
    $st->execute([':e1' => $empresa_id, ':e2' => $empresa_id, ':e3' => $empresa_id,
        ':di1' => $di, ':df1' => $df, ':di2' => $di, ':df2' => $df]);
    $veiculos = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rotas = (int) $r['rotas']; $manut = (int) $r['manut'];
        $score = round(0.50 * min(100, $rotas * 10) + 0.50 * max(0, 100 - $manut * 12));
        $veiculos[] = [
            'id' => (int) $r['id'], 'placa' => $r['placa'], 'modelo' => $r['modelo'],
            'score' => (int) $score, 'faixa' => score_band($score), 'rotas' => $rotas, 'manutencoes' => $manut,
        ];
    }
    usort($veiculos, fn($a, $b) => $b['score'] <=> $a['score']);

    // ---- CLIENTES: faturamento (relativo) + recorrência + margem ----
    $st = $conn->prepare("SELECT cliente_nome,
            COUNT(*) AS ots,
            ROUND(SUM(valor_frete), 2) AS faturamento,
            ROUND(SUM(valor_frete - COALESCE(valor_custo_estimado, 0)), 2) AS margem
        FROM ordens_transporte
        WHERE empresa_id = :e AND cliente_nome IS NOT NULL AND cliente_nome <> ''
            AND (data_coleta IS NULL OR data_coleta BETWEEN :di AND :df)
        GROUP BY cliente_nome ORDER BY faturamento DESC");
    $st->execute([':e' => $empresa_id, ':di' => $di, ':df' => $df]);
    $rowsCli = $st->fetchAll(PDO::FETCH_ASSOC);
    $maxFat = 0.0;
    foreach ($rowsCli as $r) { $maxFat = max($maxFat, (float) $r['faturamento']); }
    $clientes = [];
    foreach ($rowsCli as $r) {
        $fat = (float) $r['faturamento']; $ots = (int) $r['ots']; $margem = (float) $r['margem'];
        $sFat = $maxFat > 0 ? ($fat / $maxFat) * 100 : 0;
        $sMargem = $fat > 0 ? max(0, min(100, ($margem / $fat) * 100 + 50)) : 40;
        $score = round(0.50 * $sFat + 0.30 * min(100, $ots * 20) + 0.20 * $sMargem);
        $clientes[] = [
            'nome' => $r['cliente_nome'], 'score' => (int) $score, 'faixa' => score_band($score),
            'ots' => $ots, 'faturamento' => $fat, 'margem' => $margem,
        ];
    }
    usort($clientes, fn($a, $b) => $b['score'] <=> $a['score']);

    echo json_encode([
        'success' => true,
        'periodo' => ['inicio' => $data_inicio, 'fim' => $data_fim],
        'motoristas' => $motoristas,
        'veiculos' => $veiculos,
        'clientes' => $clientes,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('scores_consolidado.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao calcular os scores.']);
}
