<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/adm_api.php';
require_once dirname(__DIR__, 2) . '/lib/adm_phase2.php';
$pdo = adm_api_boot();
$data = adm_deploy_overview($pdo);
adm_api_ok([
    'commits' => $data['commits'] ?? [],
    'versao_atual' => $data['versao_atual'] ?? null,
    'git_hash' => $data['git_hash'] ?? null,
    'ultimo_deploy' => $data['ultimo_deploy'] ?? null,
]);
