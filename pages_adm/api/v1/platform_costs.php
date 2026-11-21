<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/adm_api.php';
require_once dirname(__DIR__, 2) . '/lib/adm_phase2.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = adm_api_boot(true);
    $body = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
    $mes = preg_match('/^\d{4}-\d{2}$/', (string) ($body['mes'] ?? '')) ? $body['mes'] : date('Y-m');
    $action = (string) ($body['action'] ?? 'save');

    if ($action === 'add') {
        $res = adm_cost_add(
            $pdo,
            (string) ($body['nome'] ?? ''),
            (float) ($body['valor'] ?? 0),
            $mes,
            isset($body['observacao']) ? (string) $body['observacao'] : null
        );
        if (empty($res['success'])) {
            api_json_send(['success' => false, 'message' => $res['message'] ?? 'Erro'], 400);
        }
        adm_api_ok($res);
    }

    if ($action === 'delete') {
        $res = adm_cost_delete($pdo, (string) ($body['categoria'] ?? ''), $mes);
        if (empty($res['success'])) {
            api_json_send(['success' => false, 'message' => $res['message'] ?? 'Erro'], 400);
        }
        adm_api_ok($res);
    }

    $itens = $body['itens'] ?? [];
    if (!is_array($itens)) {
        api_json_send(['success' => false, 'message' => 'itens inválido'], 400);
    }
    adm_api_ok(adm_costs_save($pdo, $itens, $mes));
}

$pdo = adm_api_boot();
$mes = isset($_GET['mes']) && preg_match('/^\d{4}-\d{2}$/', (string) $_GET['mes']) ? (string) $_GET['mes'] : null;
adm_api_ok(adm_platform_costs($pdo, $mes));
