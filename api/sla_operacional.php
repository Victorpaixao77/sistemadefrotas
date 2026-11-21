<?php
/**
 * API — SLA Operacional (nível de serviço das viagens).
 * Baseado em rotas: % no prazo, tempo médio de viagem, evolução e ranking. Só leitura, por empresa.
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
        $st = $conn->prepare($sql); $st->execute([':e' => $empresa_id, ':di' => $di, ':df' => $df]); return $st;
    };

    // Resumo
    $r = $q("SELECT COUNT(*) total,
            SUM(CASE WHEN no_prazo = 1 THEN 1 ELSE 0 END) no_prazo,
            ROUND(AVG(CASE WHEN data_saida IS NOT NULL AND data_chegada IS NOT NULL
                AND data_chegada >= data_saida THEN TIMESTAMPDIFF(HOUR, data_saida, data_chegada) END), 1) horas_media
        FROM rotas WHERE empresa_id = :e AND data_rota BETWEEN :di AND :df")->fetch(PDO::FETCH_ASSOC) ?: [];
    $total = (int) ($r['total'] ?? 0);
    $noPrazo = (int) ($r['no_prazo'] ?? 0);
    $pct = $total > 0 ? round($noPrazo / $total * 100, 1) : 0;

    // Evolução mensal do SLA
    $evol = $q("SELECT DATE_FORMAT(data_rota,'%Y-%m') ym,
            ROUND(AVG(CASE WHEN no_prazo = 1 THEN 100 ELSE 0 END), 1) pct
        FROM rotas WHERE empresa_id = :e AND data_rota BETWEEN :di AND :df GROUP BY ym ORDER BY ym")->fetchAll(PDO::FETCH_ASSOC);

    // Ranking por motorista (SLA)
    $mot = $q("SELECT m.nome, COUNT(*) rotas,
            ROUND(AVG(CASE WHEN r.no_prazo = 1 THEN 100 ELSE 0 END), 0) pct
        FROM rotas r JOIN motoristas m ON m.id = r.motorista_id
        WHERE r.empresa_id = :e AND r.data_rota BETWEEN :di AND :df
        GROUP BY r.motorista_id, m.nome HAVING rotas > 0 ORDER BY pct DESC, rotas DESC")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'periodo' => ['inicio' => $data_inicio, 'fim' => $data_fim],
        'resumo' => [
            'total' => $total, 'no_prazo' => $noPrazo, 'atrasadas' => $total - $noPrazo,
            'pct_no_prazo' => $pct, 'horas_media' => (float) ($r['horas_media'] ?? 0),
        ],
        'evolucao' => $evol,
        'motoristas' => $mot,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('sla_operacional.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar o SLA.']);
}
