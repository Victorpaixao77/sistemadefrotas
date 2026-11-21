<?php
/**
 * Insights de contas a pagar: vencidas e valores altos pendentes.
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/api_json.php';
require_once '../includes/permissions.php';
require_once '../includes/sf_insights_cache.php';

configure_session();
session_start();
require_authentication();
if (function_exists('session_write_close')) { session_write_close(); }

if (!can_view_financial_data()) {
    api_json_error('Sem permissão.', 403);
}

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if ($empresa_id < 1) {
    api_json_error('Não autorizado.', 401);
}

try {
    $conn = getConnection();

    sf_insights_json_cached($empresa_id, 'contas_pagar', 300, function () use ($conn, $empresa_id) {
    $stmt = $conn->prepare("
        SELECT cp.id, cp.descricao, cp.valor, cp.data_vencimento, s.nome AS status_nome,
               DATEDIFF(CURDATE(), cp.data_vencimento) AS dias_atraso
        FROM contas_pagar cp
        LEFT JOIN status_contas_pagar s ON cp.status_id = s.id
        WHERE cp.empresa_id = :e
          AND s.nome IN ('Pendente', 'Vencida')
        ORDER BY cp.data_vencimento ASC, cp.valor DESC
        LIMIT 80
    ");
    $stmt->execute([':e' => $empresa_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $qualidade = [];
    $qErros = 0;
    $qAvisos = 0;
    $totalAberto = 0.0;

    foreach ($rows as $row) {
        $valor = (float) ($row['valor'] ?? 0);
        $totalAberto += $valor;
        $dias = (int) ($row['dias_atraso'] ?? 0);
        $status = trim((string) ($row['status_nome'] ?? ''));
        $desc = trim((string) ($row['descricao'] ?? 'Conta'));
        $rotulo = $desc . ' · R$ ' . number_format($valor, 2, ',', '.');
        $motivos = [];
        $sev = 'aviso';

        if ($status === 'Vencida' || $dias > 0) {
            $motivos[] = ['msg' => 'Vencida há ' . max(0, $dias) . ' dia(s).'];
            $sev = $dias >= 15 ? 'erro' : 'aviso';
            if ($dias >= 15) {
                $qErros++;
            } else {
                $qAvisos++;
            }
        } elseif ($valor >= 3000) {
            $motivos[] = ['msg' => 'Valor elevado pendente (R$ ' . number_format($valor, 2, ',', '.') . ').'];
            $qAvisos++;
        } else {
            continue;
        }

        $qualidade[] = [
            'id' => (int) $row['id'],
            'nome' => $rotulo,
            'severidade' => $sev,
            'motivos' => $motivos,
        ];
    }

    return [
        'success' => true,
        'q_erros' => $qErros,
        'q_avisos' => $qAvisos,
        'qualidade' => $qualidade,
        'total_aberto' => round($totalAberto, 2),
        'total_itens' => count($rows),
    ];
    });
} catch (Throwable $e) {
    error_log('contas_pagar_insights: ' . $e->getMessage());
    api_json_error('Não foi possível carregar insights.', 500);
}
