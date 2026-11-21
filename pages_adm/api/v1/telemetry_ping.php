<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/conexao.php';
require_once dirname(__DIR__, 2) . '/includes/adm_session.php';
require_once dirname(__DIR__, 3) . '/includes/api_json.php';
require_once dirname(__DIR__, 2) . '/lib/adm_schema.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json_method_not_allowed();
}

$isFleet = !empty($_SESSION['loggedin']) && !empty($_SESSION['usuario_id']);
$isAdmin = !empty($_SESSION['admin_id']);

if (!$isFleet && !$isAdmin) {
    api_json_unauthorized();
}

global $pdo;
adm_schema_ensure($pdo);

$pagina = substr((string) ($_POST['pagina'] ?? $_SERVER['HTTP_REFERER'] ?? ''), 0, 255);
$sid = session_id();

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
if (is_string($ip) && strpos($ip, ',') !== false) {
    $ip = trim(explode(',', $ip)[0]);
}
$ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500) : null;

$uid = null;
$ecId = null;
$eaId = null;
$adminId = null;

if ($isFleet) {
    $uid = (int) $_SESSION['usuario_id'];
    $ecId = isset($_SESSION['empresa_id']) ? (int) $_SESSION['empresa_id'] : null;
    if ($ecId) {
        $st = $pdo->prepare('SELECT empresa_adm_id FROM empresa_clientes WHERE id = ? LIMIT 1');
        $st->execute([$ecId]);
        $eaId = $st->fetchColumn() ?: null;
    }
} else {
    $adminId = (int) $_SESSION['admin_id'];
}

$stmt = $pdo->prepare("INSERT INTO adm_sessoes_ativas
    (session_id, usuario_id, admin_id, empresa_cliente_id, empresa_adm_id, pagina, user_agent, ip_address, ultimo_ping)
    VALUES (?,?,?,?,?,?,?,?,NOW())
    ON DUPLICATE KEY UPDATE
        pagina = VALUES(pagina),
        ultimo_ping = NOW(),
        user_agent = VALUES(user_agent),
        usuario_id = COALESCE(VALUES(usuario_id), usuario_id),
        admin_id = COALESCE(VALUES(admin_id), admin_id),
        empresa_cliente_id = COALESCE(VALUES(empresa_cliente_id), empresa_cliente_id),
        empresa_adm_id = COALESCE(VALUES(empresa_adm_id), empresa_adm_id)");

$stmt->execute([$sid, $uid, $adminId, $ecId, $eaId, $pagina, $ua, $ip]);

$pdo->exec('DELETE FROM adm_sessoes_ativas WHERE ultimo_ping < DATE_SUB(NOW(), INTERVAL 5 MINUTE)');

api_json_send(['success' => true]);
