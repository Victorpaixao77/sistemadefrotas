<?php
/**
 * Analytics legado — redireciona lógica para helpers unificados.
 */
require_once __DIR__ . '/../includes/lucratividade_api_base.php';

$action = isset($_GET['action']) ? (string) $_GET['action'] : 'charts';

try {
    if ($action !== 'charts') {
        throw new InvalidArgumentException('Ação inválida.');
    }
    $conn = getConnection();
    $evolucao = lucra_get_evolucao_meses($conn, $empresa_id, $mes, $ano, 12);
    $dist = lucra_get_distribuicao_custos($conn, $empresa_id, $mes, $ano);

    $distMap = [];
    foreach ($dist['labels'] as $i => $label) {
        $distMap[$label] = $dist['values'][$i] ?? 0;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'fretes_vs_despesas' => array_map(static function ($i) use ($evolucao) {
                return [
                    'mes' => $i + 1,
                    'fretes' => $evolucao['receita'][$i] ?? 0,
                    'despesas' => max(0, ($evolucao['receita'][$i] ?? 0) - ($evolucao['lucro'][$i] ?? 0)),
                ];
            }, array_keys($evolucao['labels'] ?? [])),
            'distribuicao_despesas' => $distMap,
            'evolucao_lucratividade' => array_map(static function ($i) use ($evolucao) {
                return ['lucro' => $evolucao['lucro'][$i] ?? 0];
            }, array_keys($evolucao['labels'] ?? [])),
            'composicao_frete' => [],
            'lucro_por_veiculo' => getRankings($conn, $empresa_id, $mes, $ano)['veiculos'] ?? [],
            'lucro_por_dia' => [],
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('lucratividade_analytics: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Não foi possível processar a requisição.'], JSON_UNESCAPED_UNICODE);
}
