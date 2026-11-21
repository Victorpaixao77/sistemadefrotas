<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/adm_api.php';
require_once dirname(__DIR__, 2) . '/lib/adm_phase2.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = adm_api_boot(true);
    adm_api_ok(adm_deploy_rollback_prepare($pdo));
}

$pdo = adm_api_boot();
adm_api_ok(adm_deploy_overview($pdo));
