<?php
declare(strict_types=1);

/**
 * Gates SaaS: status empresa, manutenção plataforma, marketplace de módulos, limites de licença.
 */

function sf_saas_schema_ready(PDO $pdo): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $ok = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa_adm' AND COLUMN_NAME = 'status_saas'")->fetchColumn() > 0;
    } catch (Throwable $e) {
        $ok = false;
    }
    return $ok;
}

function sf_platform_maintenance_mode(PDO $pdo): bool
{
    try {
        $st = $pdo->prepare("SELECT valor FROM adm_platform_config WHERE chave = 'maintenance_mode' LIMIT 1");
        $st->execute();
        $v = $st->fetchColumn();
        return $v !== false && (string) $v === '1';
    } catch (Throwable $e) {
        return false;
    }
}

/** @return array{ok:bool, code?:string, message?:string, status?:string} */
function sf_saas_status_check(array $row): array
{
    $status = (string) ($row['status_saas'] ?? 'ativa');
    $motivo = trim((string) ($row['bloqueado_motivo'] ?? ''));
    $trialAte = $row['trial_ate'] ?? null;

    if ($status === 'cancelada') {
        return ['ok' => false, 'code' => 'cancelada', 'status' => $status, 'message' => 'Conta cancelada. Entre em contato com o suporte Frotec.'];
    }
    if ($status === 'bloqueada') {
        return ['ok' => false, 'code' => 'bloqueada', 'status' => $status, 'message' => $motivo !== '' ? $motivo : 'Conta bloqueada. Regularize a situação com o suporte.'];
    }
    if ($status === 'suspensa') {
        return ['ok' => false, 'code' => 'suspensa', 'status' => $status, 'message' => $motivo !== '' ? $motivo : 'Conta suspensa temporariamente.'];
    }
    if ($status === 'trial' && $trialAte && strtotime((string) $trialAte) < strtotime('today')) {
        return ['ok' => false, 'code' => 'trial_expirado', 'status' => $status, 'message' => 'Período de trial encerrado em ' . date('d/m/Y', strtotime((string) $trialAte)) . '.'];
    }
    return ['ok' => true, 'status' => $status];
}

function sf_saas_empresa_adm_by_cliente(PDO $pdo, int $empresaClienteId): ?array
{
    if ($empresaClienteId < 1 || !sf_saas_schema_ready($pdo)) {
        return null;
    }
    try {
        $st = $pdo->prepare('SELECT ea.id, ea.razao_social, ea.status_saas, ea.trial_ate, ea.bloqueado_motivo
            FROM empresa_adm ea JOIN empresa_clientes ec ON ec.empresa_adm_id = ea.id WHERE ec.id = ? LIMIT 1');
        $st->execute([$empresaClienteId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/** @return array{ok:bool, code?:string, message?:string} */
function sf_saas_gate_empresa_cliente(PDO $pdo, int $empresaClienteId, bool $allowImpersonation = true): array
{
    if ($allowImpersonation && !empty($_SESSION['adm_impersonating'])) {
        return ['ok' => true];
    }
    $ea = sf_saas_empresa_adm_by_cliente($pdo, $empresaClienteId);
    if (!$ea) {
        return ['ok' => true];
    }
    return sf_saas_status_check($ea);
}

function sf_saas_enforce_session(PDO $pdo): void
{
    if (empty($_SESSION['loggedin']) || empty($_SESSION['empresa_id'])) {
        return;
    }
    if (!empty($_SESSION['acesso_todas_empresas']) && empty($_SESSION['empresa_id'])) {
        return;
    }
    $check = sf_saas_gate_empresa_cliente($pdo, (int) $_SESSION['empresa_id']);
    if ($check['ok']) {
        return;
    }
    $msg = $check['message'] ?? 'Acesso indisponível.';
    $_SESSION = [];
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $check['code'] ?? 'bloqueado', 'message' => $msg]);
        exit;
    }
    session_start();
    $_SESSION['login_error'] = $msg;
    header('Location: ' . sf_app_url('login.php'));
    exit;
}

function sf_modulo_empresa_ativo(PDO $pdo, int $empresaAdmId, string $slug): bool
{
    static $cache = [];
    $key = $empresaAdmId . ':' . $slug;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    try {
        $st = $pdo->prepare('SELECT id FROM adm_modulos WHERE slug = ? AND ativo_global = 1 LIMIT 1');
        $st->execute([$slug]);
        $modId = $st->fetchColumn();
        if (!$modId) {
            return $cache[$key] = true;
        }
        $st2 = $pdo->prepare('SELECT ativo FROM adm_empresa_modulos WHERE empresa_adm_id = ? AND modulo_id = ? LIMIT 1');
        $st2->execute([$empresaAdmId, (int) $modId]);
        $ativo = $st2->fetchColumn();
        if ($ativo === false) {
            return $cache[$key] = true;
        }
        return $cache[$key] = ((int) $ativo) === 1;
    } catch (Throwable $e) {
        return $cache[$key] = true;
    }
}

function sf_empresa_adm_id_from_cliente(PDO $pdo, int $empresaClienteId): ?int
{
    $ea = sf_saas_empresa_adm_by_cliente($pdo, $empresaClienteId);
    return $ea ? (int) $ea['id'] : null;
}

function sf_modulo_ativo_sessao(PDO $pdo, string $slug): bool
{
    $ec = (int) ($_SESSION['empresa_id'] ?? 0);
    if ($ec < 1) {
        return true;
    }
    $eaId = sf_empresa_adm_id_from_cliente($pdo, $ec);
    if (!$eaId) {
        return true;
    }
    return sf_modulo_empresa_ativo($pdo, $eaId, $slug);
}

/** @return array{ok:bool, message?:string, limite?:?int, uso?:int} */
function sf_licenca_limite_get(PDO $pdo, int $empresaAdmId, string $recurso): ?int
{
    try {
        $st = $pdo->prepare('SELECT limite_valor FROM adm_licenca_limites WHERE empresa_adm_id = ? AND recurso = ? LIMIT 1');
        $st->execute([$empresaAdmId, $recurso]);
        $lim = $st->fetchColumn();
        if ($lim !== false && $lim !== null) {
            return (int) $lim;
        }
        if ($recurso === 'veiculos') {
            $st2 = $pdo->prepare('SELECT limite_veiculos FROM adm_planos p JOIN empresa_adm e ON e.plano_id = p.id WHERE e.id = ?');
            $st2->execute([$empresaAdmId]);
            $v = $st2->fetchColumn();
            return ($v !== false && $v !== null) ? (int) $v : null;
        }
    } catch (Throwable $e) {
    }
    return null;
}

function sf_licenca_uso_atual(PDO $pdo, int $empresaClienteId, string $recurso): int
{
    try {
        switch ($recurso) {
            case 'veiculos':
                $st = $pdo->prepare('SELECT COUNT(*) FROM veiculos WHERE empresa_id = ? AND status_id IN (1,2)');
                $st->execute([$empresaClienteId]);
                return (int) $st->fetchColumn();
            case 'motoristas':
                $st = $pdo->prepare('SELECT COUNT(*) FROM motoristas WHERE empresa_id = ?');
                $st->execute([$empresaClienteId]);
                return (int) $st->fetchColumn();
            case 'usuarios':
                $st = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE empresa_id = ? AND status = 'ativo'");
                $st->execute([$empresaClienteId]);
                return (int) $st->fetchColumn();
            case 'ia':
                $st = $pdo->prepare('SELECT COUNT(*) FROM adm_ia_usage WHERE empresa_cliente_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)');
                $st->execute([$empresaClienteId]);
                return (int) $st->fetchColumn();
            case 'api_calls':
                $st = $pdo->prepare('SELECT COUNT(*) FROM adm_request_metrics WHERE empresa_cliente_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)');
                $st->execute([$empresaClienteId]);
                return (int) $st->fetchColumn();
            case 'storage_mb':
                $eaId = sf_empresa_adm_id_from_cliente($pdo, $empresaClienteId);
                if (!$eaId) {
                    return 0;
                }
                if (is_file(__DIR__ . '/../pages_adm/lib/adm_storage_sync.php')) {
                    require_once __DIR__ . '/../pages_adm/lib/adm_storage_sync.php';
                    $cached = adm_storage_cached_mb($pdo, $eaId);
                    if ($cached !== null) {
                        return $cached;
                    }
                }
                if (is_file(__DIR__ . '/../pages_adm/lib/adm_phase2.php')) {
                    require_once __DIR__ . '/../pages_adm/lib/adm_phase2.php';
                    return adm_storage_empresa_mb($eaId);
                }
                return 0;
        }
    } catch (Throwable $e) {
    }
    return 0;
}

/** @return array{ok:bool, message?:string, limite?:?int, uso?:int} */
function sf_licenca_dentro_limite(PDO $pdo, int $empresaClienteId, string $recurso, int $incremento = 1): array
{
    if (!empty($_SESSION['adm_impersonating'])) {
        return ['ok' => true];
    }
    $eaId = sf_empresa_adm_id_from_cliente($pdo, $empresaClienteId);
    if (!$eaId) {
        return ['ok' => true];
    }
    $limite = sf_licenca_limite_get($pdo, $eaId, $recurso);
    if ($limite === null) {
        return ['ok' => true];
    }
    $uso = sf_licenca_uso_atual($pdo, $empresaClienteId, $recurso);
    if ($uso + max(0, $incremento) > $limite) {
        $labels = [
            'veiculos' => 'veículos',
            'motoristas' => 'motoristas',
            'usuarios' => 'usuários',
            'ia' => 'chamadas de IA (30 dias)',
            'storage_mb' => 'armazenamento (MB)',
            'api_calls' => 'requisições API (30 dias)',
        ];
        $label = $labels[$recurso] ?? $recurso;
        return [
            'ok' => false,
            'limite' => $limite,
            'uso' => $uso,
            'message' => "Limite de {$label} do plano atingido ({$uso}/{$limite}).",
        ];
    }
    return ['ok' => true, 'limite' => $limite, 'uso' => $uso];
}

/** @return array{ok:bool, message?:string, limite?:?int, uso?:int} */
function sf_licenca_pode_adicionar(PDO $pdo, int $empresaClienteId, string $recurso = 'veiculos'): array
{
    return sf_licenca_dentro_limite($pdo, $empresaClienteId, $recurso, 1);
}

/** Bloqueia request JSON quando limite excedido. */
function sf_licenca_enforce_json(PDO $pdo, int $empresaClienteId, string $recurso, int $incremento = 1): void
{
    $check = sf_licenca_dentro_limite($pdo, $empresaClienteId, $recurso, $incremento);
    if ($check['ok']) {
        return;
    }
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'limite_plano',
        'message' => $check['message'] ?? 'Limite do plano atingido.',
        'limite' => $check['limite'] ?? null,
        'uso' => $check['uso'] ?? null,
    ]);
    exit;
}

function sf_saas_enforce_api_quota(PDO $pdo): void
{
    if (empty($_SESSION['empresa_id']) || !empty($_SESSION['adm_impersonating'])) {
        return;
    }
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if (strpos($uri, '/api/') === false || strpos($uri, 'pages_adm') !== false) {
        return;
    }
    if (preg_match('#/(healthcheck|telemetry_ping)\.php#i', $uri)) {
        return;
    }
    sf_licenca_enforce_json($pdo, (int) $_SESSION['empresa_id'], 'api_calls', 1);
}

/** @return array{ok:bool, message?:string} */
function sf_upload_storage_ok(PDO $pdo, int $empresaClienteId, int $bytes): array
{
    if ($bytes < 1) {
        return ['ok' => true];
    }
    $mb = (int) ceil($bytes / 1024 / 1024);
    if ($mb < 1) {
        $mb = 1;
    }
    return sf_licenca_dentro_limite($pdo, $empresaClienteId, 'storage_mb', $mb);
}

function sf_ia_enforce_quota(PDO $pdo, int $empresaClienteId): void
{
    if (!sf_modulo_ativo_sessao($pdo, 'ia')) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'modulo_ia', 'message' => 'Módulo IA não está ativo para esta empresa.']);
        exit;
    }
    sf_licenca_enforce_json($pdo, $empresaClienteId, 'ia', 1);
}

function sf_sair_modo_suporte(PDO $pdo): void
{
    if (empty($_SESSION['adm_impersonating'])) {
        return;
    }
    try {
        $adminId = (int) ($_SESSION['adm_impersonate_admin_id'] ?? 0);
        $usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
        $ecId = (int) ($_SESSION['empresa_id'] ?? 0);
        if ($adminId && $usuarioId && $ecId) {
            $pdo->prepare("UPDATE adm_auditoria_suporte SET encerrado_em = NOW()
                WHERE admin_id = ? AND usuario_id = ? AND empresa_cliente_id = ? AND encerrado_em IS NULL
                ORDER BY id DESC LIMIT 1")->execute([$adminId, $usuarioId, $ecId]);
        }
        if (function_exists('adm_event_log') && is_file(__DIR__ . '/../pages_adm/lib/adm_events.php')) {
            require_once __DIR__ . '/../pages_adm/lib/adm_events.php';
            adm_event_log($pdo, 'suporte', 'impersonate_end', 'Modo suporte encerrado', 'info', $ecId, null, $usuarioId);
        }
    } catch (Throwable $e) {
        error_log('sf_sair_modo_suporte: ' . $e->getMessage());
    }
    $backup = $_SESSION['_adm_session_backup'] ?? null;
    $_SESSION = is_array($backup) ? $backup : [];
    unset($_SESSION['adm_impersonating'], $_SESSION['adm_impersonate_admin_id'], $_SESSION['_adm_session_backup']);
}
