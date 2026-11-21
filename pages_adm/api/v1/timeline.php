<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/adm_api.php';
require_once dirname(__DIR__, 2) . '/lib/adm_phase2.php';
$pdo = adm_api_boot();
$empresaId = isset($_GET['empresa_adm_id']) ? (int) $_GET['empresa_adm_id'] : null;
if ($empresaId !== null && $empresaId < 1) {
    $empresaId = null;
}
adm_api_ok(adm_timeline($pdo, $empresaId));
