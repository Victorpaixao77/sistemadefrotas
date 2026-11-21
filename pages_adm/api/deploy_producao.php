<?php
/**
 * API — Central de Deploy Inteligente (Produção)
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/adm_auth.php';
require_once dirname(__DIR__) . '/lib/adm_deploy_prod.php';

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? 'overview'));

if ($action === 'backup_download') {
    adm_deploy_schema_ensure($pdo);
    try {
        adm_deploy_backup_download_send($pdo, (int) ($_GET['backup_id'] ?? 0), trim((string) ($_GET['file'] ?? 'database')));
    } catch (Throwable $e) {
        error_log('deploy backup_download: ' . $e->getMessage());
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Erro ao baixar backup.';
    }
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$adminId = (int) ($_SESSION['admin_id'] ?? 0);
$adminNome = (string) ($_SESSION['admin_nome'] ?? 'Admin');

// Libera o lock da sessão: passos longos (envio/backup) não travam mais o polling/telemetria da tela.
// O $_SESSION continua legível (a validação de CSRF ainda funciona).
if (function_exists('session_write_close')) {
    session_write_close();
}

if (in_array($action, ['server_status', 'compare_files', 'compare_db', 'backup', 'db_status', 'preflight'], true)) {
    @set_time_limit(300);
}

try {
    adm_deploy_schema_ensure($pdo);

    switch ($action) {
        case 'overview':
            echo json_encode([
                'success' => true,
                'data' => [
                    'servers' => adm_deploy_servers_list($pdo),
                    'history' => adm_deploy_history($pdo, 20),
                    'backups' => adm_deploy_backups_list($pdo),
                    'steps' => adm_deploy_run_steps_def(),
                    'homolog_root' => adm_phase2_root(),
                    'ssh_available' => adm_deploy_ssh_available(),
                ],
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'servers':
            echo json_encode(['success' => true, 'data' => adm_deploy_servers_list($pdo)], JSON_UNESCAPED_UNICODE);
            break;

        case 'server_save':
            api_require_csrf_json();
            $raw = file_get_contents('php://input') ?: '';
            $body = json_decode($raw, true);
            if (!is_array($body) || $body === []) {
                $body = $_POST;
            }
            $res = adm_deploy_server_save($pdo, $body, $adminId);
            if (!$res['ok']) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $res['error'] ?? 'Dados inválidos', 'data' => $res], JSON_UNESCAPED_UNICODE);
                break;
            }
            echo json_encode(['success' => true, 'data' => $res], JSON_UNESCAPED_UNICODE);
            break;

        case 'server_status':
            $sid = (int) ($_GET['server_id'] ?? 0);
            echo json_encode(['success' => true, 'data' => adm_deploy_server_status($pdo, $sid)], JSON_UNESCAPED_UNICODE);
            break;

        case 'compare_files':
            $sid = (int) ($_GET['server_id'] ?? $_POST['server_id'] ?? 0);
            $res = adm_deploy_compare_files($pdo, $sid);
            echo json_encode(['success' => $res['ok'], 'data' => $res, 'error' => $res['error'] ?? null], JSON_UNESCAPED_UNICODE);
            break;

        case 'db_status':
            $sid = (int) ($_GET['server_id'] ?? $_POST['server_id'] ?? 0);
            $res = adm_deploy_db_status($pdo, $sid);
            echo json_encode(['success' => $res['ok'] ?? false, 'data' => $res, 'error' => $res['error'] ?? null], JSON_UNESCAPED_UNICODE);
            break;

        case 'compare_db':
            $sid = (int) ($_GET['server_id'] ?? $_POST['server_id'] ?? 0);
            $res = adm_deploy_compare_database($pdo, $sid);
            echo json_encode(['success' => $res['ok'], 'data' => $res, 'error' => $res['error'] ?? null], JSON_UNESCAPED_UNICODE);
            break;

        case 'git':
            $sid = (int) ($_GET['server_id'] ?? $_POST['server_id'] ?? 0);
            $server = adm_deploy_server_get($pdo, $sid);
            if (!$server) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Servidor não encontrado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $sub = trim((string) ($_GET['sub'] ?? $_POST['sub'] ?? 'overview'));
            if ($sub === 'overview') {
                echo json_encode(['success' => true, 'data' => adm_deploy_git_overview($server)], JSON_UNESCAPED_UNICODE);
            } else {
                api_require_csrf_json();
                $msg = trim((string) ($_POST['message'] ?? ''));
                echo json_encode(['success' => true, 'data' => adm_deploy_git_action($server, $sub, $msg ?: null)], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'backup':
            api_require_csrf_json();
            $raw = file_get_contents('php://input') ?: '';
            $body = json_decode($raw, true);
            if (!is_array($body) || $body === []) {
                $body = $_POST;
            }
            $sid = (int) ($body['server_id'] ?? $_POST['server_id'] ?? 0);
            if ($sid <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Selecione um servidor de produção.'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $res = adm_deploy_create_backup($pdo, $sid, null, $adminId);
            echo json_encode([
                'success' => (bool) ($res['ok'] ?? false),
                'data' => $res,
                'error' => $res['error'] ?? null,
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'backups':
            $sid = (int) ($_GET['server_id'] ?? 0);
            echo json_encode(['success' => true, 'data' => adm_deploy_backups_list($pdo, $sid)], JSON_UNESCAPED_UNICODE);
            break;

        case 'history':
            echo json_encode(['success' => true, 'data' => adm_deploy_history($pdo, (int) ($_GET['limit'] ?? 50))], JSON_UNESCAPED_UNICODE);
            break;

        case 'run_start':
            api_require_csrf_json();
            $body = json_decode(file_get_contents('php://input') ?: '{}', true) ?: $_POST;
            $sid = (int) ($body['server_id'] ?? 0);
            $server = adm_deploy_server_get($pdo, $sid);
            if (!$server) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Servidor não encontrado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $err2fa = adm_deploy_require_2fa($pdo, $adminId, $body['totp_code'] ?? null, (string) $server['ambiente']);
            if ($err2fa) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => $err2fa, 'require_2fa' => true], JSON_UNESCAPED_UNICODE);
                break;
            }
            if (empty($body['confirm'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Confirme o deploy em produção.'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $versao = substr(trim((string) ($body['versao'] ?? 'v' . date('Y.m.d.Hi'))), 0, 32);
            $res = adm_deploy_run_create($pdo, $sid, $adminId, $adminNome, $versao);
            adm_deploy_audit($pdo, $adminId, 'deploy_start', 'Deploy iniciado run #' . $res['run_id'], ['server_id' => $sid]);
            echo json_encode(['success' => true, 'data' => $res], JSON_UNESCAPED_UNICODE);
            break;

        case 'run_step':
            api_require_csrf_json();
            $body = json_decode(file_get_contents('php://input') ?: '{}', true) ?: $_POST;
            $runId = (int) ($body['run_id'] ?? 0);
            $step = trim((string) ($body['step'] ?? ''));
            if ($runId < 1 || $step === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'run_id e step obrigatórios'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $opts = [
                'approve_sql' => !empty($body['approve_sql']),
                'allow_dirty' => !empty($body['allow_dirty']),
                'auto_rollback' => !empty($body['auto_rollback']),
                'ctx' => is_array($body['ctx'] ?? null) ? $body['ctx'] : [],
            ];
            $res = adm_deploy_execute_step($pdo, $runId, $step, $opts);
            echo json_encode(['success' => $res['ok'], 'data' => $res], JSON_UNESCAPED_UNICODE);
            break;

        case 'run_cancel':
            api_require_csrf_json();
            $body = json_decode(file_get_contents('php://input') ?: '{}', true) ?: $_POST;
            $runId = (int) ($body['run_id'] ?? 0);
            if ($runId < 1) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'run_id obrigatório'], JSON_UNESCAPED_UNICODE);
                break;
            }
            echo json_encode(['success' => true, 'data' => adm_deploy_run_cancel($pdo, $runId, $adminId)], JSON_UNESCAPED_UNICODE);
            break;

        case 'run_poll':
            $runId = (int) ($_GET['run_id'] ?? 0);
            $run = adm_deploy_run_get($pdo, $runId);
            echo json_encode(['success' => (bool) $run, 'data' => $run], JSON_UNESCAPED_UNICODE);
            break;

        case 'rollback':
            api_require_csrf_json();
            $runId = (int) ($_POST['run_id'] ?? 0);
            echo json_encode(['success' => true, 'data' => adm_deploy_rollback_run($pdo, $runId, $adminId)], JSON_UNESCAPED_UNICODE);
            break;

        case 'preflight':
            $sid = (int) ($_GET['server_id'] ?? $_POST['server_id'] ?? 0);
            echo json_encode(['success' => true, 'data' => adm_deploy_preflight($pdo, $sid)], JSON_UNESCAPED_UNICODE);
            break;

        case 'validate_sql':
            $sql = $_POST['sql'] ?? '';
            $lines = is_array($sql) ? $sql : array_filter(array_map('trim', explode("\n", (string) $sql)));
            echo json_encode(['success' => true, 'data' => adm_deploy_validate_sql_commands($lines)], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ação inválida'], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    error_log('deploy_producao API [' . $action . ']: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    $msg = 'Erro interno no deploy.';
    if (getenv('SF_DEBUG') === '1' || !empty($_SESSION['adm_impersonating'])) {
        $msg .= ' ' . $e->getMessage();
    }
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
}
