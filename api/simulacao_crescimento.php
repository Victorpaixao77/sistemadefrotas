<?php
/**
 * API — Base para a Simulação de Crescimento.
 * Devolve as médias reais da operação (12 meses) por veículo/mês, para o simulador
 * projetar faturamento, lucro, ROI e payback ao adicionar frota/motoristas. Só leitura.
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
    $one = function (string $sql) use ($conn, $empresa_id, $di, $df) {
        $st = $conn->prepare($sql); $st->execute([':e' => $empresa_id, ':di' => $di, ':df' => $df]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: [];
    };
    $rot = $one("SELECT ROUND(SUM(frete),2) fat, ROUND(SUM(COALESCE(comissao,0)),2) com,
            COUNT(*) rotas, COUNT(DISTINCT veiculo_id) veic_ativos
        FROM rotas WHERE empresa_id=:e AND data_rota BETWEEN :di AND :df");
    $comb = (float) ($one("SELECT ROUND(SUM(valor_total),2) t FROM abastecimentos WHERE empresa_id=:e AND data_abastecimento BETWEEN :di AND :df")['t'] ?? 0);
    $man = (float) ($one("SELECT ROUND(SUM(COALESCE(custo_total,0)),2) t FROM manutencoes WHERE empresa_id=:e AND data_manutencao BETWEEN :di AND :df")['t'] ?? 0);
    $nveic = (int) ($conn->query("SELECT COUNT(*) FROM veiculos WHERE empresa_id={$empresa_id}")->fetchColumn() ?: 0);

    $fat = (float) ($rot['fat'] ?? 0);
    $custo = $comb + $man + (float) ($rot['com'] ?? 0);
    $lucro = $fat - $custo;
    $veicBase = max(1, (int) ($rot['veic_ativos'] ?? 0) ?: $nveic); // base de veículos que geram receita

    echo json_encode([
        'success' => true,
        'base' => [
            'veiculos' => $nveic,
            'veiculos_ativos' => (int) ($rot['veic_ativos'] ?? 0),
            'rotas_ano' => (int) ($rot['rotas'] ?? 0),
            'faturamento_ano' => round($fat, 2),
            'custo_ano' => round($custo, 2),
            'lucro_ano' => round($lucro, 2),
            'margem' => $fat > 0 ? round($lucro / $fat * 100, 1) : 0,
            // médias por veículo/mês (o simulador multiplica pela nova frota)
            'fat_veic_mes' => round($fat / $veicBase / 12, 2),
            'custo_veic_mes' => round($custo / $veicBase / 12, 2),
            'lucro_veic_mes' => round($lucro / $veicBase / 12, 2),
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('simulacao_crescimento.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar a base.']);
}
