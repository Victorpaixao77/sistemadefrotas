<?php
declare(strict_types=1);

/**
 * Manutenção Control Center: storage cache, purge logs, cron jobs vencidos.
 * Crontab: */15 * * * * php C:/xampp/htdocs/sistema-frotas/cron/adm_cc_maintenance.php
 */

$root = dirname(__DIR__);
require_once $root . '/includes/config.php';
require_once $root . '/includes/db_connect.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    fwrite(STDERR, "PDO indisponível\n");
    exit(1);
}

require_once $root . '/pages_adm/lib/adm_schema.php';
adm_schema_ensure($pdo);

require_once $root . '/pages_adm/lib/adm_maintenance.php';
adm_maintenance_purge($pdo);

if (is_file($root . '/pages_adm/lib/adm_storage_sync.php')) {
    require_once $root . '/pages_adm/lib/adm_storage_sync.php';
    $r = adm_storage_sync_all($pdo, 500);
    echo 'storage_synced=' . ($r['synced'] ?? 0) . "\n";
}

if (is_file($root . '/pages_adm/lib/adm_phase2.php')) {
    require_once $root . '/pages_adm/lib/adm_phase2.php';
    adm_cron_seed($pdo);
    try {
        $jobs = $pdo->query("SELECT id FROM adm_cron_jobs WHERE ativo = 1 AND slug = 'adm_cc_maintenance'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($jobs as $jobId) {
            adm_job_run_now($pdo, (int) $jobId);
        }
    } catch (Throwable $e) {
    }
}

echo "adm_cc_maintenance ok\n";
