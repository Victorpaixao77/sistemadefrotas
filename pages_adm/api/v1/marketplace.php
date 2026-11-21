<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/adm_api.php';
require_once dirname(__DIR__, 2) . '/lib/adm_phase2.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = adm_api_boot(true);
    $body = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
    $empresaId = (int) ($body['empresa_adm_id'] ?? 0);
    $moduloId = (int) ($body['modulo_id'] ?? 0);
    $ativo = !empty($body['ativo']);
    if ($empresaId < 1 || $moduloId < 1) {
        api_json_send(['success' => false, 'message' => 'Parâmetros inválidos'], 400);
    }
    adm_api_ok(['ok' => adm_marketplace_toggle($pdo, $empresaId, $moduloId, $ativo)]);
}

$pdo = adm_api_boot();
adm_api_ok(adm_marketplace($pdo));
