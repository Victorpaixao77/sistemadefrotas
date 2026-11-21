<?php
/**
 * API — Timeline do Veículo (histórico cronológico unificado).
 * Agrega abastecimentos, manutenções, multas e rotas de um veículo. Só leitura, por empresa.
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

$action = $_GET['action'] ?? 'timeline';

try {
    if ($action === 'veiculos') {
        $st = $conn->prepare("SELECT id, placa, modelo FROM veiculos WHERE empresa_id = :e ORDER BY placa");
        $st->execute([':e' => $empresa_id]);
        echo json_encode(['success' => true, 'veiculos' => $st->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $veiculo_id = (int) ($_GET['veiculo_id'] ?? 0);
    if ($veiculo_id < 1) { echo json_encode(['success' => false, 'message' => 'Informe o veículo.']); exit; }

    // Confere que o veículo é da empresa (isolamento).
    $chk = $conn->prepare("SELECT placa, modelo FROM veiculos WHERE id = :v AND empresa_id = :e LIMIT 1");
    $chk->execute([':v' => $veiculo_id, ':e' => $empresa_id]);
    $veic = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$veic) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'Veículo não encontrado.']); exit; }

    $limit = min(500, max(20, (int) ($_GET['limit'] ?? 200)));
    $eventos = [];

    // Abastecimentos
    $st = $conn->prepare("SELECT data_abastecimento AS dt, posto, litros, valor_total FROM abastecimentos
        WHERE veiculo_id = :v AND empresa_id = :e AND data_abastecimento IS NOT NULL ORDER BY data_abastecimento DESC LIMIT {$limit}");
    $st->execute([':v' => $veiculo_id, ':e' => $empresa_id]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $eventos[] = ['dt' => $r['dt'], 'tipo' => 'abastecimento', 'icone' => 'fa-gas-pump', 'cor' => '#3b82f6',
            'titulo' => 'Abastecimento' . ($r['posto'] ? ' · ' . $r['posto'] : ''),
            'detalhe' => rtrim(rtrim((string) $r['litros'], '0'), '.') . ' L', 'valor' => (float) $r['valor_total'], 'link' => 'abastecimentos.php'];
    }
    // Manutenções
    $st = $conn->prepare("SELECT data_manutencao AS dt, descricao, custo_total FROM manutencoes
        WHERE veiculo_id = :v AND empresa_id = :e AND data_manutencao IS NOT NULL ORDER BY data_manutencao DESC LIMIT {$limit}");
    $st->execute([':v' => $veiculo_id, ':e' => $empresa_id]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $eventos[] = ['dt' => $r['dt'], 'tipo' => 'manutencao', 'icone' => 'fa-wrench', 'cor' => '#f59e0b',
            'titulo' => 'Manutenção', 'detalhe' => (string) ($r['descricao'] ?: 'Serviço'), 'valor' => (float) $r['custo_total'], 'link' => 'manutencoes.php'];
    }
    // Multas
    $st = $conn->prepare("SELECT data_infracao AS dt, tipo_infracao, descricao, valor FROM multas
        WHERE veiculo_id = :v AND empresa_id = :e AND data_infracao IS NOT NULL ORDER BY data_infracao DESC LIMIT {$limit}");
    $st->execute([':v' => $veiculo_id, ':e' => $empresa_id]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $eventos[] = ['dt' => $r['dt'], 'tipo' => 'multa', 'icone' => 'fa-triangle-exclamation', 'cor' => '#dc2626',
            'titulo' => 'Multa' . ($r['tipo_infracao'] ? ' · ' . $r['tipo_infracao'] : ''),
            'detalhe' => (string) ($r['descricao'] ?: ''), 'valor' => (float) $r['valor'], 'link' => 'multas.php'];
    }
    // Rotas
    $st = $conn->prepare("SELECT data_rota AS dt, estado_origem, estado_destino, frete FROM rotas
        WHERE veiculo_id = :v AND empresa_id = :e AND data_rota IS NOT NULL ORDER BY data_rota DESC LIMIT {$limit}");
    $st->execute([':v' => $veiculo_id, ':e' => $empresa_id]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $eventos[] = ['dt' => $r['dt'], 'tipo' => 'rota', 'icone' => 'fa-route', 'cor' => '#16a34a',
            'titulo' => 'Rota', 'detalhe' => trim((string) $r['estado_origem'] . ' → ' . (string) $r['estado_destino'], ' →'),
            'valor' => (float) $r['frete'], 'link' => 'routes.php'];
    }

    // Ordena por data desc.
    usort($eventos, fn($a, $b) => strcmp((string) $b['dt'], (string) $a['dt']));
    $eventos = array_slice($eventos, 0, $limit);

    // Resumo por tipo.
    $resumo = ['abastecimento' => 0, 'manutencao' => 0, 'multa' => 0, 'rota' => 0];
    foreach ($eventos as $e) { $resumo[$e['tipo']] = ($resumo[$e['tipo']] ?? 0) + 1; }

    echo json_encode(['success' => true, 'veiculo' => $veic, 'resumo' => $resumo, 'eventos' => $eventos], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('veiculo_timeline.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar a timeline.']);
}
