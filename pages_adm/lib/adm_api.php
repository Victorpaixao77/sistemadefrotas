<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/adm_paths.php';
require_once SF_PROJECT_ROOT . '/includes/conexao.php';
require_once dirname(__DIR__) . '/includes/adm_session.php';
require_once SF_PROJECT_ROOT . '/includes/csrf.php';
require_once SF_PROJECT_ROOT . '/includes/api_json.php';
require_once __DIR__ . '/adm_schema.php';

function adm_api_require_auth(): void
{
    if (!isset($_SESSION['admin_id'])) {
        api_json_unauthorized();
    }
}

function adm_api_boot(bool $requireCsrf = false): PDO
{
    global $pdo;
    adm_api_require_auth();
    adm_schema_ensure($pdo);
    if ($requireCsrf) {
        api_require_csrf_json();
    }
    return $pdo;
}

function adm_api_ok($data = null, int $code = 200): void
{
    $payload = ['success' => true];
    if ($data !== null) {
        $payload['data'] = $data;
    }
    api_json_send($payload, $code);
}
