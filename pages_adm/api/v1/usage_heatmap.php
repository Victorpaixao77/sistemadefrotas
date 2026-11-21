<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/adm_api.php';
require_once dirname(__DIR__, 2) . '/lib/adm_phase2.php';
$pdo = adm_api_boot();
adm_api_ok(adm_usage_heatmap($pdo));
