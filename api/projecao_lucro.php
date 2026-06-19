<?php
require_once __DIR__ . '/../includes/lucratividade_api_base.php';

try {
    $conn = getConnection();
    $historico = [];
    for ($i = 5; $i >= 0; $i--) {
        [$m, $y] = lucra_shift_mes_ano($mes, $ano, -$i);
        $k = getKPIsOptimized($conn, $empresa_id, $m, $y);
        $historico[] = (float) ($k['lucro_liquido'] ?? 0);
    }

    $crescimento_medio = 0.0;
    if (count($historico) >= 2) {
        $crescimentos = [];
        for ($i = 1; $i < count($historico); $i++) {
            if ($historico[$i - 1] != 0.0) {
                $crescimentos[] = (($historico[$i] - $historico[$i - 1]) / abs($historico[$i - 1])) * 100;
            }
        }
        if ($crescimentos !== []) {
            $crescimento_medio = array_sum($crescimentos) / count($crescimentos);
        }
    }

    $lucro_atual = (float) end($historico);
    $projecao = [$lucro_atual];
    $meta = [$lucro_atual > 0 ? $lucro_atual * 1.1 : 0];

    for ($i = 1; $i <= 3; $i++) {
        $base = $projecao[$i - 1];
        $projecao[] = $base * (1 + ($crescimento_medio / 100));
        $meta[] = ($meta[$i - 1] ?? 0) * 1.05;
    }

    $meses = [1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'];
    $labels = ['Mês atual'];
    [$m0, $y0] = [$mes, $ano];
    for ($i = 1; $i <= 3; $i++) {
        [$m0, $y0] = lucra_shift_mes_ano($mes, $ano, $i);
        $labels[] = ($meses[$m0] ?? $m0) . '/' . substr((string) $y0, -2);
    }

    $tem_dados = array_sum(array_map('abs', $historico)) > 0;

    echo json_encode([
        'success' => true,
        'labels' => $labels,
        'projecao' => array_map(static fn($v) => round($v, 2), $projecao),
        'meta' => array_map(static fn($v) => round($v, 2), $meta),
        'empty' => !$tem_dados,
        'crescimento_medio' => round($crescimento_medio, 2),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('projecao_lucro: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível calcular a projeção.', 'labels' => [], 'projecao' => [], 'meta' => []], JSON_UNESCAPED_UNICODE);
}
