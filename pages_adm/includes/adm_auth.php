<?php
/**
 * Autenticação do painel administrativo (pages_adm).
 */
declare(strict_types=1);

require_once __DIR__ . '/adm_paths.php';
require_once SF_PROJECT_ROOT . '/includes/conexao.php';
require_once __DIR__ . '/adm_session.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

sf_project_include('includes/csrf.php');
csrf_token_get();

require_once __DIR__ . '/../lib/adm_schema.php';
adm_schema_ensure($pdo);

if (is_file(__DIR__ . '/../lib/adm_perf.php')) {
    require_once __DIR__ . '/../lib/adm_perf.php';
    adm_perf_bootstrap();
}
