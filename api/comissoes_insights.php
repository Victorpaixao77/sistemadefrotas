<?php
/**
 * Insights de comissões: pendências altas e motoristas com maior valor em aberto.
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

    sf_insights_json_cached($empresa_id, 'comissoes', 300, function () use ($conn, $empresa_id) {
    $stmt = $conn->prepare("
        SELECT r.id, r.comissao, r.data_rota, m.nome AS motorista_nome,
               DATEDIFF(CURDATE(), COALESCE(r.data_rota, r.data_saida)) AS dias
        FROM rotas r
        LEFT JOIN motoristas m ON m.id = r.motorista_id
        LEFT JOIN comissoes_pagamentos cp ON cp.rota_id = r.id
        WHERE r.empresa_id = :e
          AND r.comissao > 0
          AND r.status = 'aprovado'
          AND (cp.status IS NULL OR cp.status = 'pendente')
        ORDER BY r.comissao DESC, r.data_rota ASC
        LIMIT 50
    ");
    $stmt->execute([':e' => $empresa_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $qualidade = [];
    $qErros = 0;
    $qAvisos = 0;
    $totalPendente = 0.0;

    foreach ($rows as $row) {
        $valor = (float) ($row['comissao'] ?? 0);
        $totalPendente += $valor;
        $dias = (int) ($row['dias'] ?? 0);
        $nome = trim((string) ($row['motorista_nome'] ?? 'Motorista'));
        $rotulo = $nome . ' · R$ ' . number_format($valor, 2, ',', '.');
        $motivos = [];
        $sev = 'aviso';

        if ($dias >= 45) {
            $motivos[] = ['msg' => 'Pendente há ' . $dias . ' dias.'];
            $sev = 'erro';
            $qErros++;
        } elseif ($dias >= 21) {
            $motivos[] = ['msg' => 'Pendente há ' . $dias . ' dias.'];
            $qAvisos++;
        } elseif ($valor >= 500) {
            $motivos[] = ['msg' => 'Valor elevado (R$ ' . number_format($valor, 2, ',', '.') . ').'];
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
        'total_pendente' => round($totalPendente, 2),
        'total_pendentes' => count($rows),
    ];
    });
} catch (Throwable $e) {
    error_log('comissoes_insights: ' . $e->getMessage());
    api_json_error('Não foi possível carregar insights.', 500);
}
