<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/adm_api.php';
require_once dirname(__DIR__, 2) . '/lib/adm_phase2.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = adm_api_boot(true);
    $body = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
    $chave = trim((string) ($body['chave'] ?? ''));
    $valor = (string) ($body['valor'] ?? '');
    if ($chave === '') {
        api_json_send(['success' => false, 'message' => 'chave obrigatória'], 400);
    }
    adm_api_ok(adm_platform_config_save($pdo, $chave, $valor));
}

$pdo = adm_api_boot();
adm_api_ok(adm_platform_config_hub($pdo));
