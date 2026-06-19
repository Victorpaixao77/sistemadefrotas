<?php
require_once __DIR__ . '/../includes/lucratividade_api_base.php';

try {
    $conn = getConnection();
    $rotaPeriod = lucra_rota_period_sql('r');
    $meses = [1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'];

    $labels = [];
    $data = [];
    $current_value = 0.0;

    for ($i = 5; $i >= 0; $i--) {
        [$m, $y] = lucra_shift_mes_ano($mes, $ano, -$i);
        $kpis = getKPIsOptimized($conn, $empresa_id, $m, $y);
        $stmt = $conn->prepare("SELECT COALESCE(SUM(distancia_km), 0) AS km FROM rotas r WHERE r.empresa_id = ? AND {$rotaPeriod}");
        $stmt->execute([$empresa_id, $m, $y]);
        $km = (float) ($stmt->fetch(PDO::FETCH_ASSOC)['km'] ?? 0);
        $lucro = (float) ($kpis['lucro_liquido'] ?? 0);
        $lpk = $km > 0 ? $lucro / $km : 0;
        $labels[] = ($meses[$m] ?? $m) . '/' . substr((string) $y, -2);
        $data[] = round($lpk, 4);
        if ($i === 0) {
            $current_value = $lpk;
        }
    }

    $has_data = array_sum(array_map('abs', $data)) > 0;

    if (!$has_data) {
        echo json_encode([
            'success' => true,
            'empty' => true,
            'warning' => 'Sem quilometragem ou lucro no período para calcular lucro por km.',
            'gauge' => ['value' => 0, 'min' => -2, 'max' => 2, 'thresholds' => ['red' => -0.5, 'yellow' => 0, 'green' => 0.5]],
            'line' => ['labels' => $labels, 'datasets' => [['label' => 'Lucro por KM', 'data' => $data, 'borderColor' => '#94a3b8', 'backgroundColor' => 'rgba(148,163,184,0.1)', 'fill' => true, 'tension' => 0.4]]],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success' => true,
        'empty' => false,
        'gauge' => [
            'value' => round($current_value, 4),
            'min' => -2,
            'max' => 2,
            'thresholds' => ['red' => -0.5, 'yellow' => 0, 'green' => 0.5],
        ],
        'line' => [
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Lucro por KM',
                'data' => $data,
                'borderColor' => '#2ecc40',
                'backgroundColor' => 'rgba(46, 204, 64, 0.1)',
                'fill' => true,
                'tension' => 0.4,
            ]],
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('profit_per_km_analytics: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível calcular lucro por km.'], JSON_UNESCAPED_UNICODE);
}
