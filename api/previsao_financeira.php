<?php
/**
 * API — Previsão Financeira (tendência de faturamento, custo e lucro).
 * Série mensal histórica + projeção dos próximos 3 meses por regressão linear.
 * Só leitura, por empresa. Não usa dado fictício: se não houver histórico, projeta vazio.
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

$di = date('Y-m-d', strtotime('-18 months')) . ' 00:00:00';
$df = date('Y-m-d') . ' 23:59:59';

/** Regressão linear simples: retorna [a, b] de y = a + b*x. */
function linreg(array $ys): array {
    $n = count($ys); if ($n < 2) return [$n ? $ys[0] : 0, 0];
    $sx = $sy = $sxy = $sxx = 0;
    foreach ($ys as $i => $y) { $sx += $i; $sy += $y; $sxy += $i * $y; $sxx += $i * $i; }
    $den = ($n * $sxx - $sx * $sx);
    $b = $den != 0 ? ($n * $sxy - $sx * $sy) / $den : 0;
    $a = ($sy - $b * $sx) / $n;
    return [$a, $b];
}

try {
    $q = function (string $sql) use ($conn, $empresa_id, $di, $df) {
        $st = $conn->prepare($sql); $st->execute([':e' => $empresa_id, ':di' => $di, ':df' => $df]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $out[$r['ym']] = (float) $r['v']; }
        return $out;
    };
    $fat = $q("SELECT DATE_FORMAT(data_rota,'%Y-%m') ym, ROUND(SUM(frete),2) v FROM rotas
        WHERE empresa_id=:e AND data_rota BETWEEN :di AND :df GROUP BY ym");
    $com = $q("SELECT DATE_FORMAT(data_rota,'%Y-%m') ym, ROUND(SUM(COALESCE(comissao,0)),2) v FROM rotas
        WHERE empresa_id=:e AND data_rota BETWEEN :di AND :df GROUP BY ym");
    $comb = $q("SELECT DATE_FORMAT(data_abastecimento,'%Y-%m') ym, ROUND(SUM(valor_total),2) v FROM abastecimentos
        WHERE empresa_id=:e AND data_abastecimento BETWEEN :di AND :df GROUP BY ym");
    $man = $q("SELECT DATE_FORMAT(data_manutencao,'%Y-%m') ym, ROUND(SUM(COALESCE(custo_total,0)),2) v FROM manutencoes
        WHERE empresa_id=:e AND data_manutencao BETWEEN :di AND :df GROUP BY ym");

    // Universo de meses (união) ordenado.
    $meses = array_unique(array_merge(array_keys($fat), array_keys($com), array_keys($comb), array_keys($man)));
    sort($meses);

    $hist = [];
    foreach ($meses as $ym) {
        $f = $fat[$ym] ?? 0;
        $c = ($com[$ym] ?? 0) + ($comb[$ym] ?? 0) + ($man[$ym] ?? 0);
        $hist[] = ['ym' => $ym, 'faturamento' => round($f, 2), 'custo' => round($c, 2), 'lucro' => round($f - $c, 2)];
    }

    // Projeção dos próximos 3 meses (regressão sobre faturamento e custo).
    $prev = [];
    if (count($hist) >= 3) {
        [$af, $bf] = linreg(array_column($hist, 'faturamento'));
        [$ac, $bc] = linreg(array_column($hist, 'custo'));
        $n = count($hist);
        $ultimo = $hist[$n - 1]['ym'];
        for ($k = 1; $k <= 3; $k++) {
            $x = $n - 1 + $k;
            $pf = max(0, $af + $bf * $x);
            $pc = max(0, $ac + $bc * $x);
            $dt = date('Y-m', strtotime($ultimo . '-01 +' . $k . ' month'));
            $prev[] = ['ym' => $dt, 'faturamento' => round($pf, 2), 'custo' => round($pc, 2), 'lucro' => round($pf - $pc, 2)];
        }
    }

    echo json_encode([
        'success' => true,
        'historico' => $hist,
        'previsao' => $prev,
        'tem_previsao' => count($prev) > 0,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('previsao_financeira.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao calcular a previsão.']);
}
