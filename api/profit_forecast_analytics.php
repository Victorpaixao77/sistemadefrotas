<?php
require_once __DIR__ . '/../includes/lucratividade_api_base.php';

try {
    $conn = getConnection();
    $data = lucra_get_evolucao_meses($conn, $empresa_id, $mes, $ano, 6);

    $labels = $data['labels'];
    $lucros = $data['lucro'];
    $n = count($lucros);
    $x = range(0, max(0, $n - 1));
    $y = $lucros;

    if ($n < 2 || array_sum(array_map('abs', $y)) <= 0) {
        echo json_encode([
            'success' => true,
            'empty' => true,
            'warning' => 'Dados insuficientes para projeção. Cadastre rotas e despesas do período.',
            'labels' => $labels,
            'datasets' => [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sum_x = array_sum($x);
    $sum_y = array_sum($y);
    $sum_xy = 0;
    $sum_xx = 0;
    for ($i = 0; $i < $n; $i++) {
        $sum_xy += $x[$i] * $y[$i];
        $sum_xx += $x[$i] * $x[$i];
    }
    $den = ($n * $sum_xx) - ($sum_x * $sum_x);
    $slope = $den != 0 ? (($n * $sum_xy) - ($sum_x * $sum_y)) / $den : 0;
    $intercept = ($sum_y - ($slope * $sum_x)) / $n;

    $meses = [1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'];
    $projLabels = $labels;
    $historical = $y;
    $forecast = array_fill(0, $n, null);

    for ($i = 1; $i <= 3; $i++) {
        [$m, $y2] = lucra_shift_mes_ano($mes, $ano, $i);
        $projLabels[] = ($meses[$m] ?? $m) . '/' . substr((string) $y2, -2);
        $historical[] = null;
        $forecast[] = round($intercept + ($slope * ($n - 1 + $i)), 2);
    }

    echo json_encode([
        'success' => true,
        'empty' => false,
        'labels' => $projLabels,
        'datasets' => [
            [
                'label' => 'Lucro real',
                'data' => $historical,
                'borderColor' => '#2ecc40',
                'backgroundColor' => 'rgba(46, 204, 64, 0.1)',
                'fill' => true,
                'tension' => 0.35,
            ],
            [
                'label' => 'Projeção (tendência)',
                'data' => $forecast,
                'borderColor' => '#fd7e14',
                'backgroundColor' => 'rgba(253, 126, 20, 0.08)',
                'borderDash' => [6, 4],
                'fill' => false,
                'tension' => 0.35,
            ],
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('profit_forecast_analytics: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível gerar a projeção.', 'labels' => [], 'datasets' => []], JSON_UNESCAPED_UNICODE);
}
