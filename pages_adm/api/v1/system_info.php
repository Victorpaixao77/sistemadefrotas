<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/adm_api.php';
require_once dirname(__DIR__, 2) . '/lib/adm_health.php';

$pdo = adm_api_boot();
$info = adm_system_info();

try {
    $info['mysql'] = $pdo->query('SELECT VERSION()')->fetchColumn();
} catch (Throwable $e) {
    $info['mysql'] = null;
}

$disk = adm_health_disk();
$info['disco_pct'] = $disk['pct'] ?? null;

adm_api_ok($info);
