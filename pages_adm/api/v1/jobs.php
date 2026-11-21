<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/adm_api.php';
require_once dirname(__DIR__, 2) . '/lib/adm_phase2.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = adm_api_boot(true);
    $body = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
    $jobId = (int) ($body['job_id'] ?? 0);
    if ($jobId < 1) {
        api_json_send(['success' => false, 'message' => 'job_id inválido'], 400);
    }
    adm_api_ok(adm_job_run_now($pdo, $jobId));
}

$pdo = adm_api_boot();
adm_api_ok(adm_jobs_list($pdo));
