<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/adm_api.php';
require_once dirname(__DIR__, 2) . '/lib/adm_intel.php';
require_once dirname(__DIR__, 2) . '/lib/adm_ia_usage.php';

$pdo = adm_api_boot();
$data = adm_ia_usage_timed($pdo, 'rules-engine/ia-admin', static fn () => adm_ia_admin_analyze($pdo));
adm_api_ok($data);
