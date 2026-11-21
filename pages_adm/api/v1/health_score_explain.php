<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/adm_api.php';
require_once dirname(__DIR__, 2) . '/lib/adm_phase2.php';

$pdo = adm_api_boot();
$empresaId = (int) ($_GET['empresa_adm_id'] ?? 0);
if ($empresaId < 1) {
    api_json_send(['success' => false, 'message' => 'empresa_adm_id obrigatório'], 400);
}
adm_api_ok(adm_health_score_explain($pdo, $empresaId));
