<?php
declare(strict_types=1);

require_once __DIR__ . '/adm_deploy_crypto.php';
require_once __DIR__ . '/adm_deploy_ssh.php';
require_once __DIR__ . '/adm_phase2.php';

/** Pastas ignoradas na comparação/sync */
function adm_deploy_ignore_paths(): array
{
    return [
        '.git', 'vendor', 'node_modules', 'logs', 'cache', 'tmp', 'sessions', 'uploads',
        '.cursor', '.idea', '.vscode', '.claude', '.agents',
        'app_android', // app Kotlin separado — não faz parte do deploy web
        'site', // site institucional / landing (não é o app)
        'storage', 'backup', 'backups', 'coverage', 'dist', 'build',
        // Pastas de desenvolvimento — não precisam ir para produção (e não têm dependência em runtime):
        'docs',   // documentação (só citada como texto na ajuda)
        'tools',  // ferramentas de dev
        'tests', 'test', '.github',
        // OBS: 'sql' e 'examples' NÃO entram aqui — a app lê alguns arquivos deles em runtime
        // (sql/ia_copilot.sql; examples/*_import_modelo.csv|json baixados pelo usuário).
    ];
}

/**
 * Arquivos que NUNCA são enviados à produção — têm config/credenciais próprias do ambiente,
 * e sobrescrevê-los quebra o sistema em produção (ex.: includes/conexao.php, que tem host/senha do banco).
 * Base fixa de segurança + a lista editável do servidor (campo sync_exclude, um por linha).
 * @return string[] caminhos relativos (barra normal)
 */
function adm_deploy_protected_files(array $server): array
{
    $base = [
        'includes/conexao.php',
        'includes/config.php',
        'includes/db_connect.php',
        '.env', '.env.local', '.env.production', '.env.prod', '.env.producao',
    ];
    $raw = trim((string) ($server['sync_exclude'] ?? ''));
    if ($raw !== '') {
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim(trim(str_replace('\\', '/', (string) $line)), "/ \t");
            if ($line !== '' && strpos($line, '..') === false) {
                $base[] = $line;
            }
        }
    }
    return array_values(array_unique($base));
}

/** True se o arquivo relativo está na lista de protegidos (arquivo exato ou dentro de pasta protegida). */
function adm_deploy_is_protected(string $rel, array $protected): bool
{
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    foreach ($protected as $p) {
        $p = ltrim(str_replace('\\', '/', (string) $p), '/');
        if ($p === '') {
            continue;
        }
        if ($rel === $p || str_starts_with($rel, rtrim($p, '/') . '/')) {
            return true;
        }
    }
    return false;
}

/** Extensões relevantes para deploy web (evita milhares de assets irrelevantes). */
function adm_deploy_compare_extensions(): array
{
    return [
        'php', 'js', 'css', 'sql', 'html', 'htm', 'json', 'xml', 'txt',
        'htaccess', 'ini', 'yml', 'yaml', 'conf', 'sh', 'svg',
    ];
}

function adm_deploy_schema_ensure(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $sqlFile = dirname(__DIR__, 2) . '/sql/adm_deploy_producao.sql';
    if (!is_readable($sqlFile)) {
        return;
    }
    foreach (preg_split('/;\s*\n/', file_get_contents($sqlFile)) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') {
            continue;
        }
        // Remove linhas de comentário — blocos que começam com -- não podem ser descartados inteiros.
        $lines = preg_split('/\r\n|\r|\n/', $stmt);
        $lines = array_filter($lines, static fn($ln) => !preg_match('/^\s*--/', $ln));
        $stmt = trim(implode("\n", $lines));
        if ($stmt === '' || !preg_match('/^(CREATE|ALTER|INSERT|UPDATE|DROP)\s/i', $stmt)) {
            continue;
        }
        try {
            $pdo->exec($stmt);
        } catch (Throwable $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false && strpos($e->getMessage(), 'already exists') === false) {
                error_log('adm_deploy_schema: ' . $e->getMessage());
            }
        }
    }

    // Coluna site_url (health-check HTTP pós-deploy) — idempotente, aplica sozinho em produção.
    try {
        $has = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'adm_deploy_servers' AND column_name = 'site_url'")->fetchColumn();
        if ($has === 0) {
            $pdo->exec("ALTER TABLE adm_deploy_servers ADD COLUMN site_url VARCHAR(255) NULL AFTER project_path");
        }
    } catch (Throwable $e) {
        error_log('adm_deploy_schema site_url: ' . $e->getMessage());
    }

    // Coluna sync_exclude (arquivos adicionais protegidos, não sincronizados) — idempotente.
    try {
        $has = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'adm_deploy_servers' AND column_name = 'sync_exclude'")->fetchColumn();
        if ($has === 0) {
            $pdo->exec("ALTER TABLE adm_deploy_servers ADD COLUMN sync_exclude TEXT NULL AFTER site_url");
        }
    } catch (Throwable $e) {
        error_log('adm_deploy_schema sync_exclude: ' . $e->getMessage());
    }
}

function adm_deploy_server_public(array $row): array
{
    unset($row['ssh_key_enc'], $row['password_enc'], $row['db_pass_enc']);
    $row['has_ssh_key'] = !empty($row['ssh_key_enc'] ?? null) || !empty($row['has_ssh_key']);
    $row['has_password'] = !empty($row['password_enc'] ?? null) || !empty($row['has_password']);
    $row['has_db_pass'] = !empty($row['db_pass_enc'] ?? null) || !empty($row['has_db_pass']);
    return $row;
}

function adm_deploy_servers_list(PDO $pdo): array
{
    adm_deploy_schema_ensure($pdo);
    try {
        $rows = $pdo->query('SELECT * FROM adm_deploy_servers ORDER BY ambiente, nome')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('adm_deploy_servers_list: ' . $e->getMessage());
        return [];
    }
    return array_map(static function ($r) {
        $r['has_ssh_key'] = !empty($r['ssh_key_enc']);
        $r['has_password'] = !empty($r['password_enc']);
        $r['has_db_pass'] = !empty($r['db_pass_enc']);
        return adm_deploy_server_public($r);
    }, $rows);
}

function adm_deploy_server_get(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM adm_deploy_servers WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function adm_deploy_server_save(PDO $pdo, array $data, int $adminId): array
{
    adm_deploy_schema_ensure($pdo);
    $id = (int) ($data['id'] ?? 0);
    $nome = substr(trim((string) ($data['nome'] ?? '')), 0, 120);
    $host = substr(trim((string) ($data['host'] ?? '')), 0, 255);
    $project = substr(trim((string) ($data['project_path'] ?? '')), 0, 500);
    if ($nome === '' || $host === '' || $project === '') {
        return ['ok' => false, 'error' => 'Nome, host e caminho do projeto são obrigatórios.'];
    }

    $ambientes = ['desenvolvimento', 'homologacao', 'staging', 'producao'];
    $ambiente = in_array($data['ambiente'] ?? '', $ambientes, true) ? $data['ambiente'] : 'producao';
    $auth = ($data['auth_method'] ?? '') === 'password' ? 'password' : 'ssh_key';

    $fields = [
        'nome' => $nome,
        'ambiente' => $ambiente,
        'host' => $host,
        'porta_ssh' => max(1, min(65535, (int) ($data['porta_ssh'] ?? 22))),
        'usuario' => substr(trim((string) ($data['usuario'] ?? 'root')), 0, 64),
        'project_path' => $project,
        'site_url' => substr(trim((string) ($data['site_url'] ?? '')), 0, 255) ?: null,
        'sync_exclude' => substr(trim((string) ($data['sync_exclude'] ?? '')), 0, 4000) ?: null,
        'git_path' => substr(trim((string) ($data['git_path'] ?? $project)), 0, 500) ?: $project,
        'git_branch' => substr(trim((string) ($data['git_branch'] ?? 'main')), 0, 120),
        'auth_method' => $auth,
        'homolog_path' => substr(trim((string) ($data['homolog_path'] ?? '')), 0, 500) ?: null,
        'db_host' => substr(trim((string) ($data['db_host'] ?? '')), 0, 255) ?: null,
        'db_port' => (int) ($data['db_port'] ?? 3306) ?: 3306,
        'db_name' => substr(trim((string) ($data['db_name'] ?? '')), 0, 120) ?: null,
        'db_user' => substr(trim((string) ($data['db_user'] ?? '')), 0, 120) ?: null,
        'ativo' => isset($data['ativo']) && ($data['ativo'] === 1 || $data['ativo'] === '1' || $data['ativo'] === true),
    ];

    $sshKeyEnc = null;
    $passEnc = null;
    $dbPassEnc = null;
    if ($id > 0) {
        $cur = adm_deploy_server_get($pdo, $id);
        if ($cur) {
            $sshKeyEnc = $cur['ssh_key_enc'];
            $passEnc = $cur['password_enc'];
            $dbPassEnc = $cur['db_pass_enc'];
        }
    }
    if (!empty($data['ssh_key']) && trim((string) $data['ssh_key']) !== '' && trim((string) $data['ssh_key']) !== '********') {
        $sshKeyEnc = adm_deploy_encrypt(trim((string) $data['ssh_key']));
    }
    if (!empty($data['password']) && trim((string) $data['password']) !== '' && trim((string) $data['password']) !== '********') {
        $passEnc = adm_deploy_encrypt(trim((string) $data['password']));
    }
    if (!empty($data['db_pass']) && trim((string) $data['db_pass']) !== '' && trim((string) $data['db_pass']) !== '********') {
        $dbPassEnc = adm_deploy_encrypt(trim((string) $data['db_pass']));
    }

    if ($id > 0) {
        try {
            $pdo->prepare('UPDATE adm_deploy_servers SET nome=?, ambiente=?, host=?, porta_ssh=?, usuario=?, project_path=?, git_path=?, git_branch=?, auth_method=?, ssh_key_enc=?, password_enc=?, homolog_path=?, db_host=?, db_port=?, db_name=?, db_user=?, db_pass_enc=?, ativo=?, site_url=?, sync_exclude=? WHERE id=?')
                ->execute([
                    $fields['nome'], $fields['ambiente'], $fields['host'], $fields['porta_ssh'], $fields['usuario'],
                    $fields['project_path'], $fields['git_path'], $fields['git_branch'], $fields['auth_method'],
                    $sshKeyEnc, $passEnc, $fields['homolog_path'], $fields['db_host'], $fields['db_port'],
                    $fields['db_name'], $fields['db_user'], $dbPassEnc, $fields['ativo'] ? 1 : 0, $fields['site_url'], $fields['sync_exclude'], $id,
                ]);
        } catch (Throwable $e) {
            error_log('adm_deploy_server_save update: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Erro ao atualizar servidor. Verifique se as tabelas de deploy existem.'];
        }
        adm_deploy_audit($pdo, $adminId, 'server_update', 'Servidor atualizado: ' . $nome, ['server_id' => $id]);
        return ['ok' => true, 'id' => $id];
    }

    try {
        $pdo->prepare('INSERT INTO adm_deploy_servers (nome, ambiente, host, porta_ssh, usuario, project_path, git_path, git_branch, auth_method, ssh_key_enc, password_enc, homolog_path, db_host, db_port, db_name, db_user, db_pass_enc, ativo, site_url, sync_exclude) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                $fields['nome'], $fields['ambiente'], $fields['host'], $fields['porta_ssh'], $fields['usuario'],
                $fields['project_path'], $fields['git_path'], $fields['git_branch'], $fields['auth_method'],
                $sshKeyEnc, $passEnc, $fields['homolog_path'], $fields['db_host'], $fields['db_port'],
                $fields['db_name'], $fields['db_user'], $dbPassEnc, $fields['ativo'] ? 1 : 0, $fields['site_url'], $fields['sync_exclude'],
            ]);
    } catch (Throwable $e) {
        error_log('adm_deploy_server_save insert: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Erro ao salvar servidor. Execute sql/adm_deploy_producao.sql se a tabela não existir.'];
    }
    $newId = (int) $pdo->lastInsertId();
    adm_deploy_audit($pdo, $adminId, 'server_create', 'Servidor criado: ' . $nome, ['server_id' => $newId]);
    return ['ok' => true, 'id' => $newId];
}

function adm_deploy_audit(PDO $pdo, int $adminId, string $tipo, string $msg, ?array $meta = null): void
{
    if (!function_exists('adm_event_log')) {
        require_once __DIR__ . '/adm_events.php';
    }
    adm_event_log($pdo, 'deploy_prod', $tipo, $msg, 'info', null, null, null, $meta);
}

function adm_deploy_require_2fa(PDO $pdo, int $adminId, ?string $code, string $ambiente): ?string
{
    if ($ambiente !== 'producao') {
        return null;
    }
    $st = $pdo->prepare('SELECT two_factor_enabled, two_factor_confirmed_at, two_factor_secret FROM usuarios_adm WHERE id = ?');
    $st->execute([$adminId]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u || empty($u['two_factor_enabled']) || empty($u['two_factor_confirmed_at']) || empty($u['two_factor_secret'])) {
        return 'Deploy em produção exige 2FA ativo na sua conta ADM.';
    }
    if ($code === null || $code === '') {
        return 'Informe o código 2FA para publicar em produção.';
    }
    if (!function_exists('sf_totp_verify')) {
        require_once dirname(__DIR__, 2) . '/includes/security.php';
    }
    if (!sf_totp_verify((string) $u['two_factor_secret'], $code)) {
        return 'Código 2FA inválido.';
    }
    return null;
}

function adm_deploy_server_status(PDO $pdo, int $serverId): array
{
    @set_time_limit(180);
    $server = adm_deploy_server_get($pdo, $serverId);
    if (!$server) {
        return ['online' => false, 'error' => 'Servidor não encontrado'];
    }

    $metrics = ['cpu' => '—', 'ram' => '—', 'disk' => '—', 'uptime' => '—', 'load' => '—'];
    $versions = ['php' => '—', 'mysql' => '—', 'mariadb' => '—', 'git' => '—', 'composer' => '—'];
    $writable = false;
    $online = false;
    $ping = ['ok' => false, 'stdout' => '', 'stderr' => ''];

    if (adm_deploy_is_local_server($server)) {
        $ping = adm_deploy_ssh_exec($server, 'echo OK', 20);
        $online = $ping['ok'] && str_contains($ping['stdout'], 'OK');
        if ($online && PHP_OS_FAMILY === 'Windows') {
            $metrics['cpu'] = 'N/A (host Windows local)';
            $metrics['ram'] = round(memory_get_usage(true) / 1024 / 1024, 1) . ' MB (PHP)';
            $metrics['disk'] = round(disk_free_space(adm_deploy_homolog_root($server)) / 1024 / 1024 / 1024, 1) . ' GB livre';
        } elseif ($online) {
            $batch = adm_deploy_server_status_remote_batch($server);
            $online = $batch['online'];
            $metrics = $batch['metrics'];
            $versions = $batch['versions'];
            $writable = $batch['writable'];
            if (!$online && ($batch['error'] ?? '') !== '') {
                $ping['stderr'] = $batch['error'];
            }
        }
        $path = rtrim((string) $server['project_path'], '/\\');
        if (adm_deploy_is_local_server($server)) {
            $writable = is_dir($path) && is_writable($path);
        }
    } else {
        $batch = adm_deploy_server_status_remote_batch($server);
        $online = $batch['online'];
        $metrics = $batch['metrics'];
        $versions = $batch['versions'];
        $writable = $batch['writable'];
        if (!$online) {
            $ping['stderr'] = $batch['error'] ?? 'Falha ao conectar via SSH.';
        } else {
            $ping = ['ok' => true, 'stdout' => 'OK', 'stderr' => ''];
        }
    }

    $ultimoDeploy = null;
    $st = $pdo->prepare('SELECT * FROM adm_deploy_runs WHERE server_id = ? AND tipo = "deploy" AND status = "success" ORDER BY id DESC LIMIT 1');
    $st->execute([$serverId]);
    $ultimoDeploy = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    $connectionError = null;
    if (!$online) {
        $connectionError = trim((string) ($ping['stderr'] ?? ''));
        if ($connectionError === '') {
            $connectionError = 'Não foi possível conectar ao servidor remoto.';
        }
        if ((string) ($server['auth_method'] ?? '') === 'password' && PHP_OS_FAMILY === 'Windows' && !adm_deploy_plink_available()) {
            $connectionError .= ' Dica: no Windows, instale PuTTY (plink/pscp) ou use chave SSH para conexão automática.';
        }
    }

    return [
        'online' => $online,
        'status_label' => $online ? '🟢 Online' : '🔴 Offline',
        'connection_error' => $connectionError,
        'metrics' => $metrics,
        'versions' => $versions,
        'writable' => $writable,
        'ultima_sync' => $server['ultima_sync'],
        'ultimo_deploy' => $ultimoDeploy,
        'ssh_client' => adm_deploy_ssh_available(),
        'plink_client' => adm_deploy_plink_available(),
    ];
}

/** Coleta métricas/versões do servidor remoto em uma única sessão SSH. */
function adm_deploy_server_status_remote_batch(array $server): array
{
    $path = rtrim((string) $server['project_path'], '/\\');
    $pathQ = adm_deploy_sh_quote($path);
    $script = implode('; ', [
        "echo '@@PING@@ OK'",
        "top -bn1 2>/dev/null | grep 'Cpu(s)' | head -1 | sed 's/^/@@CPU@@ /'",
        "free -h 2>/dev/null | awk 'NR==2{print \"@@RAM@@ \"\$3\"/\"\$2}'",
        "df -h / 2>/dev/null | awk 'NR==2{print \"@@DISK@@ \"\$5}'",
        "(uptime -p 2>/dev/null || uptime) | sed 's/^/@@UPTIME@@ /'",
        "cat /proc/loadavg 2>/dev/null | sed 's/^/@@LOAD@@ /'",
        "php -v 2>&1 | head -1 | sed 's/^/@@PHP@@ /'",
        "mysql --version 2>&1 | head -1 | sed 's/^/@@MYSQL@@ /'",
        "mariadb --version 2>&1 | head -1 | sed 's/^/@@MARIADB@@ /'",
        "git --version 2>&1 | sed 's/^/@@GIT@@ /'",
        "composer --version 2>&1 | head -1 | sed 's/^/@@COMPOSER@@ /'",
        "if test -w {$pathQ}; then echo '@@WRITABLE@@ yes'; else echo '@@WRITABLE@@ no'; fi",
    ]);

    $r = adm_deploy_ssh_exec($server, $script, 45);
    $out = trim((string) $r['stdout']);
    if (!$r['ok'] || !str_contains($out, '@@PING@@')) {
        return [
            'online' => false,
            'error' => trim((string) ($r['stderr'] ?: $out)) ?: 'Falha na conexão SSH.',
            'metrics' => ['cpu' => '—', 'ram' => '—', 'disk' => '—', 'uptime' => '—', 'load' => '—'],
            'versions' => ['php' => '—', 'mysql' => '—', 'mariadb' => '—', 'git' => '—', 'composer' => '—'],
            'writable' => false,
        ];
    }

    $metrics = ['cpu' => '—', 'ram' => '—', 'disk' => '—', 'uptime' => '—', 'load' => '—'];
    $versions = ['php' => '—', 'mysql' => '—', 'mariadb' => '—', 'git' => '—', 'composer' => '—'];
    $writable = false;
    $map = [
        '@@CPU@@' => 'cpu',
        '@@RAM@@' => 'ram',
        '@@DISK@@' => 'disk',
        '@@UPTIME@@' => 'uptime',
        '@@LOAD@@' => 'load',
        '@@PHP@@' => 'php',
        '@@MYSQL@@' => 'mysql',
        '@@MARIADB@@' => 'mariadb',
        '@@GIT@@' => 'git',
        '@@COMPOSER@@' => 'composer',
    ];

    foreach (explode("\n", $out) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '@@PING@@')) {
            continue;
        }
        if (str_starts_with($line, '@@WRITABLE@@')) {
            $writable = str_contains($line, 'yes');
            continue;
        }
        foreach ($map as $prefix => $key) {
            if (str_starts_with($line, $prefix)) {
                $val = trim(substr($line, strlen($prefix)));
                if ($val !== '') {
                    if (isset($metrics[$key])) {
                        $metrics[$key] = $val;
                    } else {
                        $versions[$key] = $val;
                    }
                }
                break;
            }
        }
    }

    return ['online' => true, 'metrics' => $metrics, 'versions' => $versions, 'writable' => $writable, 'error' => null];
}

/** Lista arquivos relativos com hash md5 */
function adm_deploy_scan_files(string $root, array $ignore): array
{
    $out = [];
    $root = rtrim(str_replace('\\', '/', $root), '/');
    if (!is_dir($root)) {
        return $out;
    }
    $extOk = array_flip(adm_deploy_compare_extensions());
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    $n = 0;
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $full = str_replace('\\', '/', $file->getPathname());
        $rel = ltrim(substr($full, strlen($root)), '/');
        $skip = false;
        foreach ($ignore as $ig) {
            if (str_starts_with($rel, $ig . '/') || $rel === $ig) {
                $skip = true;
                break;
            }
        }
        if ($skip) {
            continue;
        }
        $base = basename($rel);
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        if ($ext === '' && $base === '.htaccess') {
            $ext = 'htaccess';
        }
        if ($ext !== '' && !isset($extOk[$ext])) {
            continue;
        }
        if ($file->getSize() > 3 * 1024 * 1024) {
            continue; // ignora arquivos > 3MB
        }
        $out[$rel] = [
            'hash' => @md5_file($full) ?: '',
            'size' => (int) $file->getSize(),
            'ext' => $ext,
        ];
        if (++$n >= 20000) {
            break;
        }
    }
    return $out;
}

/**
 * Lista remota de arquivos com md5 em UMA sessão SSH (find + md5sum).
 * @return array{ok:bool, files?:array<string,array>, error?:string}
 */
function adm_deploy_scan_files_remote(array $server, array $ignore): array
{
    $path = rtrim(str_replace('\\', '/', (string) $server['project_path']), '/');
    if ($path === '') {
        return ['ok' => false, 'error' => 'Caminho do projeto remoto vazio.'];
    }
    $prune = [];
    foreach ($ignore as $ig) {
        $ig = trim(str_replace('\\', '/', (string) $ig), '/');
        if ($ig === '') {
            continue;
        }
        $prune[] = '-path ' . adm_deploy_sh_quote('./' . $ig) . ' -o -path ' . adm_deploy_sh_quote('./' . $ig . '/*');
    }
    $pruneExpr = $prune ? '\\( ' . implode(' -o ', $prune) . ' \\) -prune -o ' : '';
    $nameParts = [];
    foreach (adm_deploy_compare_extensions() as $ext) {
        if ($ext === 'htaccess') {
            $nameParts[] = '-name ' . adm_deploy_sh_quote('.htaccess');
        } else {
            $nameParts[] = '-name ' . adm_deploy_sh_quote('*.' . $ext);
        }
    }
    $nameExpr = '\\( ' . implode(' -o ', $nameParts) . ' \\)';
    // Um único find+md5sum só com extensões de deploy.
    $cmd = 'cd ' . adm_deploy_sh_quote($path)
        . ' && find . ' . $pruneExpr . '-type f ' . $nameExpr . ' -print0 2>/dev/null'
        . ' | xargs -0 -r md5sum 2>/dev/null'
        . ' | head -n 15000';

    $r = adm_deploy_ssh_exec($server, $cmd, 120);
    $out = trim((string) $r['stdout']);
    if (!$r['ok'] && $out === '') {
        return ['ok' => false, 'error' => trim((string) ($r['stderr'] ?: 'Falha ao listar arquivos remotos via SSH.'))];
    }
    if ($out === '') {
        return ['ok' => true, 'files' => []];
    }

    $files = [];
    $extOk = array_flip(adm_deploy_compare_extensions());
    foreach (explode("\n", $out) as $line) {
        $line = trim($line);
        if ($line === '' || !preg_match('/^([0-9a-f]{32})\s+(\.?\/?)(.+)$/i', $line, $m)) {
            continue;
        }
        $rel = ltrim(str_replace('\\', '/', $m[3]), './');
        if ($rel === '') {
            continue;
        }
        $skip = false;
        foreach ($ignore as $ig) {
            if (str_starts_with($rel, $ig . '/') || $rel === $ig) {
                $skip = true;
                break;
            }
        }
        if ($skip) {
            continue;
        }
        $base = basename($rel);
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        if ($ext === '' && $base === '.htaccess') {
            $ext = 'htaccess';
        }
        if ($ext !== '' && !isset($extOk[$ext])) {
            continue;
        }
        $files[$rel] = [
            'hash' => strtolower($m[1]),
            'size' => 0,
            'ext' => $ext,
        ];
    }
    return ['ok' => true, 'files' => $files];
}

function adm_deploy_compare_files(PDO $pdo, int $serverId): array
{
    @set_time_limit(300);
    $server = adm_deploy_server_get($pdo, $serverId);
    if (!$server) {
        return ['ok' => false, 'error' => 'Servidor não encontrado'];
    }
    $ignore = adm_deploy_ignore_paths();
    $local = adm_deploy_scan_files(adm_deploy_homolog_root($server), $ignore);

    $remote = [];
    if (adm_deploy_is_local_server($server)) {
        $remote = adm_deploy_scan_files(rtrim((string) $server['project_path'], '/\\'), $ignore);
    } else {
        $scan = adm_deploy_scan_files_remote($server, $ignore);
        if (!$scan['ok']) {
            return ['ok' => false, 'error' => $scan['error'] ?? 'Erro ao escanear arquivos remotos.'];
        }
        $remote = $scan['files'] ?? [];
    }

    $novos = [];
    $alterados = [];
    $removidos = [];
    $iguais = [];
    foreach ($local as $rel => $meta) {
        if (!isset($remote[$rel])) {
            $novos[] = $rel;
        } elseif (($remote[$rel]['hash'] ?? '') !== $meta['hash'] && $meta['hash'] !== '') {
            $alterados[] = $rel;
        } else {
            $iguais[] = $rel;
        }
    }
    foreach ($remote as $rel => $_) {
        if (!isset($local[$rel])) {
            $removidos[] = $rel;
        }
    }

    // Remove arquivos protegidos (conexão/config/segredos) — nunca vão para produção.
    $protected = adm_deploy_protected_files($server);
    $protegidos = 0;
    $rmProt = static function (array $list) use ($protected, &$protegidos): array {
        return array_values(array_filter($list, static function ($rel) use ($protected, &$protegidos): bool {
            if (adm_deploy_is_protected($rel, $protected)) {
                $protegidos++;
                return false;
            }
            return true;
        }));
    };
    $novos = $rmProt($novos);
    $alterados = $rmProt($alterados);

    $byType = static function (array $list) use ($local): array {
        $g = ['php' => 0, 'js' => 0, 'css' => 0, 'img' => 0, 'other' => 0];
        foreach ($list as $rel) {
            $ext = $local[$rel]['ext'] ?? pathinfo($rel, PATHINFO_EXTENSION);
            if (in_array($ext, ['php'], true)) {
                $g['php']++;
            } elseif (in_array($ext, ['js'], true)) {
                $g['js']++;
            } elseif (in_array($ext, ['css'], true)) {
                $g['css']++;
            } elseif (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico'], true)) {
                $g['img']++;
            } else {
                $g['other']++;
            }
        }
        return $g;
    };

    $summary = [
        'novos' => count($novos),
        'alterados' => count($alterados),
        'removidos' => count($removidos),
        'iguais' => count($iguais),
        'protegidos' => $protegidos,
        'total_alteracoes' => count($novos) + count($alterados) + count($removidos),
        'por_tipo' => $byType(array_merge($novos, $alterados)),
    ];
    $detail = [
        'novos' => array_slice($novos, 0, 200),
        'alterados' => array_slice($alterados, 0, 200),
        'removidos' => array_slice($removidos, 0, 100),
        // Lista COMPLETA para o envio (sem o corte de 200 usado só na exibição).
        'sync_all' => array_slice(array_merge($novos, $alterados), 0, 20000),
    ];

    $pdo->prepare('INSERT INTO adm_deploy_comparisons (server_id, tipo, summary_json, detail_json) VALUES (?, "files", ?, ?)')
        ->execute([$serverId, json_encode($summary, JSON_UNESCAPED_UNICODE), json_encode($detail, JSON_UNESCAPED_UNICODE)]);

    return ['ok' => true, 'summary' => $summary, 'detail' => $detail];
}

/**
 * Empacota os arquivos num único .tar.gz, envia de uma vez e extrai no servidor.
 * MUITO mais rápido que arquivo-a-arquivo. @return array{ok:bool, count?:int, error?:string}
 */
function adm_deploy_sync_via_tar(array $server, string $localRoot, array $files): array
{
    if (adm_deploy_is_local_server($server)) {
        return ['ok' => false, 'error' => 'servidor local (tar não aplicável)'];
    }
    if (!class_exists('PharData')) {
        return ['ok' => false, 'error' => 'PharData indisponível'];
    }
    $localRoot = rtrim(str_replace('\\', '/', $localRoot), '/');
    $tarPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sf_deploy_' . bin2hex(random_bytes(6)) . '.tar';
    $count = 0;
    try {
        // Mapa: caminho-no-pacote => arquivo-em-disco
        $map = [];
        foreach ($files as $rel) {
            $rel = ltrim(str_replace('\\', '/', (string) $rel), '/');
            $abs = $localRoot . '/' . $rel;
            if ($rel !== '' && is_file($abs)) {
                $map[$rel] = $abs;
            }
        }
        $count = count($map);
        if ($count === 0) {
            @unlink($tarPath);
            return ['ok' => false, 'error' => 'nenhum arquivo para empacotar'];
        }
        $phar = new PharData($tarPath);
        // buildFromIterator monta o tar em UMA passada. (addFile em loop é O(n²) e trava com centenas de arquivos.)
        $phar->buildFromIterator(new ArrayIterator($map));
        $phar->compress(Phar::GZ);
        unset($phar);
        @unlink($tarPath); // remove o .tar; fica o .tar.gz
    } catch (Throwable $e) {
        @unlink($tarPath);
        @unlink($tarPath . '.gz');
        return ['ok' => false, 'error' => 'falha ao empacotar: ' . $e->getMessage()];
    }
    $gz = $tarPath . '.gz';
    if (!is_file($gz)) {
        return ['ok' => false, 'error' => 'pacote .tar.gz não gerado'];
    }
    $remoteTmp = '/tmp/sf_deploy_' . bin2hex(random_bytes(6)) . '.tar.gz';
    $up = adm_deploy_scp_upload($server, $gz, $remoteTmp);
    @unlink($gz);
    if (!$up['ok']) {
        return ['ok' => false, 'error' => 'falha ao enviar o pacote: ' . ($up['stderr'] ?? '')];
    }
    $proj = rtrim((string) $server['project_path'], '/');
    $cmd = 'mkdir -p ' . adm_deploy_sh_quote($proj)
        . ' && tar -xzf ' . adm_deploy_sh_quote($remoteTmp) . ' -C ' . adm_deploy_sh_quote($proj)
        . '; rm -f ' . adm_deploy_sh_quote($remoteTmp);
    $ex = adm_deploy_ssh_exec($server, $cmd, 180);
    if (!$ex['ok']) {
        return ['ok' => false, 'error' => 'falha ao extrair no servidor: ' . ($ex['stderr'] ?: $ex['stdout'])];
    }
    return ['ok' => true, 'count' => $count];
}

/**
 * Envia a lista de arquivos: tenta o pacote tar.gz (rápido); se falhar, cai para arquivo-a-arquivo.
 * @return array{enviados:int, falhas:array, metodo:string}
 */
function adm_deploy_sync_upload_batch(PDO $pdo, int $runId, array $server, string $localRoot, array $files): array
{
    if (adm_deploy_run_is_cancelled($pdo, $runId)) {
        return ['enviados' => 0, 'falhas' => [], 'metodo' => 'tar', 'cancelled' => true];
    }
    $tar = adm_deploy_sync_via_tar($server, $localRoot, $files);
    if ($tar['ok']) {
        return ['enviados' => (int) $tar['count'], 'falhas' => [], 'metodo' => 'tar'];
    }
    // Fallback: arquivo por arquivo — checa cancelamento a CADA arquivo (envio por senha é lento).
    $enviados = 0;
    $falhas = [];
    foreach ($files as $rel) {
        if (adm_deploy_run_is_cancelled($pdo, $runId)) {
            return ['enviados' => $enviados, 'falhas' => $falhas, 'metodo' => 'scp', 'cancelled' => true];
        }
        $local = $localRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, (string) $rel);
        if (!is_file($local)) {
            continue;
        }
        $r = adm_deploy_scp_upload($server, $local, rtrim((string) $server['project_path'], '/') . '/' . $rel);
        if ($r['ok']) {
            $enviados++;
        } else {
            $falhas[] = $rel;
        }
    }
    return ['enviados' => $enviados, 'falhas' => $falhas, 'metodo' => 'scp', 'tar_error' => $tar['error'] ?? null];
}

function adm_deploy_db_connect_local(): ?PDO
{
    try {
        if (function_exists('getConnection')) {
            require_once dirname(__DIR__, 2) . '/includes/db_connect.php';
            return getConnection();
        }
    } catch (Throwable $e) {
        error_log('adm_deploy_db local: ' . $e->getMessage());
    }
    return null;
}

/**
 * Lê o schema do banco remoto executando mysql via SSH no próprio servidor
 * (não exige abrir a porta do MySQL para a internet).
 * @return array{ok:bool, schema?:array, db?:string, error?:string}
 */
function adm_deploy_db_schema_snapshot_via_ssh(array $server): array
{
    $dbName = trim((string) ($server['db_name'] ?? ''));
    $dbUser = trim((string) ($server['db_user'] ?? 'root'));
    $dbPass = adm_deploy_decrypt($server['db_pass_enc'] ?? null) ?? '';
    $dbHost = strtolower(trim((string) ($server['db_host'] ?? '')));
    $dbPort = (int) ($server['db_port'] ?? 3306);
    if ($dbName === '' || $dbUser === '') {
        return ['ok' => false, 'error' => 'Configure db_name e db_user do banco de produção.'];
    }

    $sql = 'SELECT t.TABLE_NAME, c.COLUMN_NAME, c.COLUMN_TYPE, c.IS_NULLABLE, c.EXTRA '
        . 'FROM information_schema.TABLES t '
        . 'JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=t.TABLE_SCHEMA AND c.TABLE_NAME=t.TABLE_NAME '
        . "WHERE t.TABLE_SCHEMA=DATABASE() AND t.TABLE_TYPE='BASE TABLE' "
        . 'ORDER BY t.TABLE_NAME, c.ORDINAL_POSITION;';

    $hostPart = '';
    if ($dbHost !== '' && !in_array($dbHost, ['localhost', '127.0.0.1'], true)) {
        $hostPart = ' -h ' . adm_deploy_sh_quote($dbHost) . ' -P ' . $dbPort;
    }
    $cmd = 'MYSQL_PWD=' . adm_deploy_sh_quote($dbPass)
        . ' mysql -u ' . adm_deploy_sh_quote($dbUser) . $hostPart . ' ' . adm_deploy_sh_quote($dbName)
        . ' -N -B -e ' . adm_deploy_sh_quote($sql) . ' 2>&1';

    $r = adm_deploy_ssh_exec($server, $cmd, 90);
    $out = trim((string) $r['stdout']);
    if (!$r['ok'] || stripos($out, 'ERROR ') === 0 || stripos($out, 'ERROR ') !== false && $out !== '' && !str_contains($out, "\t")) {
        $msg = $out !== '' ? $out : ($r['stderr'] ?: 'Falha ao ler schema remoto via SSH.');
        return ['ok' => false, 'error' => 'Banco remoto: ' . $msg];
    }

    $schema = [];
    foreach (explode("\n", $out) as $line) {
        $line = rtrim($line, "\r");
        if ($line === '') {
            continue;
        }
        $parts = explode("\t", $line);
        if (count($parts) < 4) {
            continue;
        }
        $tbl = $parts[0];
        if (!isset($schema[$tbl])) {
            $schema[$tbl] = ['columns' => []];
        }
        $schema[$tbl]['columns'][] = [
            'COLUMN_NAME' => $parts[1],
            'COLUMN_TYPE' => $parts[2],
            'IS_NULLABLE' => $parts[3],
            'EXTRA' => $parts[4] ?? '',
        ];
    }
    return ['ok' => true, 'schema' => $schema, 'db' => $dbName];
}

/**
 * Conta tabelas do banco remoto via SSH (checagem rápida de conexão).
 * @return array{ok:bool, db?:string, tables?:int, error?:string}
 */
function adm_deploy_db_ssh_count(array $server): array
{
    $dbName = trim((string) ($server['db_name'] ?? ''));
    $dbUser = trim((string) ($server['db_user'] ?? 'root'));
    $dbPass = adm_deploy_decrypt($server['db_pass_enc'] ?? null) ?? '';
    $dbHost = strtolower(trim((string) ($server['db_host'] ?? '')));
    $dbPort = (int) ($server['db_port'] ?? 3306);
    if ($dbName === '' || $dbUser === '') {
        return ['ok' => false, 'error' => 'Configure db_name e db_user do banco de produção.'];
    }
    $hostPart = '';
    if ($dbHost !== '' && !in_array($dbHost, ['localhost', '127.0.0.1'], true)) {
        $hostPart = ' -h ' . adm_deploy_sh_quote($dbHost) . ' -P ' . $dbPort;
    }
    $sql = 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE();';
    $cmd = 'MYSQL_PWD=' . adm_deploy_sh_quote($dbPass)
        . ' mysql -u ' . adm_deploy_sh_quote($dbUser) . $hostPart . ' ' . adm_deploy_sh_quote($dbName)
        . ' -N -B -e ' . adm_deploy_sh_quote($sql) . ' 2>&1';
    $r = adm_deploy_ssh_exec($server, $cmd, 40);
    $out = trim((string) $r['stdout']);
    if (!$r['ok'] || !preg_match('/^\d+$/', $out)) {
        $msg = $out !== '' ? $out : ($r['stderr'] ?: 'Falha ao conectar ao banco remoto via SSH.');
        return ['ok' => false, 'error' => 'Banco remoto: ' . $msg];
    }
    return ['ok' => true, 'db' => $dbName, 'tables' => (int) $out];
}

/**
 * Status de conexão dos dois bancos (homologação local + produção).
 */
function adm_deploy_db_status(PDO $pdo, int $serverId): array
{
    $server = adm_deploy_server_get($pdo, $serverId);
    if (!$server) {
        return ['ok' => false, 'error' => 'Servidor não encontrado'];
    }

    $homolog = ['connected' => false, 'label' => 'Homologação (local)', 'info' => '', 'error' => null];
    $lp = $pdo ?: adm_deploy_db_connect_local();
    if ($lp) {
        try {
            $db = (string) $lp->query('SELECT DATABASE()')->fetchColumn();
            $n = (int) $lp->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn();
            $homolog = ['connected' => true, 'label' => 'Homologação (local)', 'info' => $db . ' · ' . $n . ' tabelas', 'error' => null];
        } catch (Throwable $e) {
            $homolog['error'] = 'Erro ao ler banco local.';
        }
    } else {
        $homolog['error'] = 'Sem conexão ao banco local (homologação).';
    }

    $prod = ['connected' => false, 'label' => 'Produção', 'info' => '', 'error' => null, 'via' => null];
    if (adm_deploy_is_local_server($server)) {
        $rp = adm_deploy_db_connect_remote($server);
        if ($rp) {
            try {
                $db = (string) $rp->query('SELECT DATABASE()')->fetchColumn();
                $n = (int) $rp->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn();
                $prod = ['connected' => true, 'label' => 'Produção', 'info' => $db . ' · ' . $n . ' tabelas', 'error' => null, 'via' => 'MySQL direto'];
            } catch (Throwable $e) {
                $prod['error'] = 'Erro ao ler banco remoto.';
            }
        } else {
            $prod['error'] = 'Configure credenciais do banco (db_name, db_user, db_pass).';
        }
    } else {
        $c = adm_deploy_db_ssh_count($server);
        if ($c['ok']) {
            $prod = ['connected' => true, 'label' => 'Produção', 'info' => $c['db'] . ' · ' . $c['tables'] . ' tabelas', 'error' => null, 'via' => 'SSH'];
        } else {
            $prod['error'] = $c['error'];
        }
    }

    return [
        'ok' => true,
        'homolog' => $homolog,
        'producao' => $prod,
        'both' => $homolog['connected'] && $prod['connected'],
    ];
}

function adm_deploy_db_connect_remote(array $server): ?PDO
{
    $host = $server['db_host'] ?: $server['host'];
    $port = (int) ($server['db_port'] ?? 3306);
    $name = (string) ($server['db_name'] ?? '');
    $user = (string) ($server['db_user'] ?? '');
    $pass = adm_deploy_decrypt($server['db_pass_enc'] ?? null) ?? '';
    if ($name === '' || $user === '') {
        return null;
    }
    if (adm_deploy_is_local_server($server)) {
        $host = '127.0.0.1';
    }
    try {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (Throwable $e) {
        error_log('adm_deploy_db remote: ' . $e->getMessage());
        return null;
    }
}

/**
 * Para um "ALTER TABLE x MODIFY COLUMN y <tipo> NOT NULL", devolve um UPDATE que preenche
 * os NULL existentes com um padrão seguro (0 / '' / zero-date) para o NOT NULL poder aplicar.
 * Retorna null se não casar ou se a coluna for ENUM/SET (deixamos de fora — evita rótulo errado).
 */
function adm_deploy_null_fix_for_modify(string $stmt): ?string
{
    if (!preg_match('/^\s*ALTER\s+TABLE\s+`?([A-Za-z0-9_]+)`?\s+MODIFY\s+(?:COLUMN\s+)?`?([A-Za-z0-9_]+)`?\s+(.+?)\s+NOT\s+NULL\s*;?\s*$/is', $stmt, $m)) {
        return null;
    }
    $table = $m[1];
    $col = $m[2];
    $type = strtolower(trim($m[3]));
    if (str_starts_with($type, 'enum') || str_starts_with($type, 'set')) {
        // Enums só recebem padrão quando temos um valor de negócio conhecido E ele existe no enum.
        $enumDefaults = ['status_saas' => 'ativa'];
        if (isset($enumDefaults[$col]) && str_contains($type, "'" . strtolower($enumDefaults[$col]) . "'")) {
            $val = str_replace("'", "''", $enumDefaults[$col]);
            return 'UPDATE `' . $table . '` SET `' . $col . '` = \'' . $val . '\' WHERE `' . $col . '` IS NULL;';
        }
        return null;
    }
    if (preg_match('/^(tinyint|smallint|mediumint|int|integer|bigint|decimal|numeric|float|double|real|bit|year|bool)/', $type)) {
        $default = '0';
    } elseif (preg_match('/^(datetime|timestamp)/', $type)) {
        $default = "'1970-01-01 00:00:00'";
    } elseif (str_starts_with($type, 'date')) {
        $default = "'1970-01-01'";
    } elseif (str_starts_with($type, 'time')) {
        $default = "'00:00:00'";
    } else {
        $default = "''";
    }
    return 'UPDATE `' . $table . '` SET `' . $col . '` = ' . $default . ' WHERE `' . $col . '` IS NULL;';
}

/**
 * Aplica as migrations NO BANCO DA PRODUÇÃO via SSH (mysql no próprio servidor).
 * É o caminho correto: o banco de produção fica em localhost do VPS (não exposto à internet).
 * Usa --force para seguir após erros idempotentes (coluna/tabela já existe).
 * @param string[] $sqlLines
 * @return array{ok:bool, total:int, aplicados:int, ja_existiam:int, erros:array, error?:string}
 */
function adm_deploy_run_migration_via_ssh(array $server, array $sqlLines): array
{
    $dbName = trim((string) ($server['db_name'] ?? ''));
    $dbUser = trim((string) ($server['db_user'] ?? 'root'));
    $dbPass = adm_deploy_decrypt($server['db_pass_enc'] ?? null) ?? '';
    $dbHost = strtolower(trim((string) ($server['db_host'] ?? '')));
    $dbPort = (int) ($server['db_port'] ?? 3306);
    if ($dbName === '' || $dbUser === '') {
        return ['ok' => false, 'total' => 0, 'aplicados' => 0, 'ja_existiam' => 0, 'erros' => [], 'error' => 'Configure db_name e db_user do banco de produção.'];
    }

    $script = '';
    $total = 0;
    foreach ($sqlLines as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }
        if (substr($line, -1) !== ';') {
            $line .= ';';
        }
        // Antes de um MODIFY ... NOT NULL, preenche os NULL existentes com um padrão seguro
        // (0/''); assim a mudança de nulabilidade aplica sem "Data truncated". Enum fica de fora.
        $nullFix = adm_deploy_null_fix_for_modify($line);
        if ($nullFix !== null) {
            $script .= $nullFix . "\n";
        }
        $script .= $line . "\n";
        $total++;
    }
    if ($total === 0) {
        return ['ok' => true, 'total' => 0, 'aplicados' => 0, 'ja_existiam' => 0, 'erros' => []];
    }

    $hostPart = '';
    if ($dbHost !== '' && !in_array($dbHost, ['localhost', '127.0.0.1'], true)) {
        $hostPart = ' -h ' . adm_deploy_sh_quote($dbHost) . ' -P ' . $dbPort;
    }
    $mysqlBase = 'MYSQL_PWD=' . adm_deploy_sh_quote($dbPass)
        . ' mysql -u ' . adm_deploy_sh_quote($dbUser) . $hostPart . ' ' . adm_deploy_sh_quote($dbName);

    // Grava o SQL em arquivo local, envia por SCP e aplica DO ARQUIVO no servidor.
    // (Passar o SQL inline no comando SSH falha quando o script é grande — dezenas de tabelas.)
    $localTmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sf_mig_' . bin2hex(random_bytes(6)) . '.sql';
    if (@file_put_contents($localTmp, $script) === false) {
        return ['ok' => false, 'total' => $total, 'aplicados' => 0, 'ja_existiam' => 0, 'erros' => ['não foi possível gravar o script SQL local']];
    }
    $remoteTmp = '/tmp/sf_mig_' . bin2hex(random_bytes(6)) . '.sql';
    $up = adm_deploy_scp_upload($server, $localTmp, $remoteTmp);
    @unlink($localTmp);
    if (empty($up['ok'])) {
        return ['ok' => false, 'total' => $total, 'aplicados' => 0, 'ja_existiam' => 0, 'erros' => ['falha ao enviar o script SQL: ' . trim((string) ($up['stderr'] ?? ''))]];
    }

    // Aplica com --force e, no MESMO comando, conta as tabelas depois (prova real de que rodou).
    $countSql = 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE();';
    $cmd = $mysqlBase . ' --force < ' . adm_deploy_sh_quote($remoteTmp) . ' 2>&1'
        . '; echo __SFCOUNT__'
        . '; ' . $mysqlBase . ' -N -B -e ' . adm_deploy_sh_quote($countSql) . ' 2>&1'
        . '; rm -f ' . adm_deploy_sh_quote($remoteTmp);
    $r = adm_deploy_ssh_exec($server, $cmd, 240);
    $out = (string) $r['stdout'];

    // Separa a saída da aplicação da contagem de tabelas.
    $parts = explode('__SFCOUNT__', $out, 2);
    $applyOut = trim($parts[0] ?? '');
    $countOut = trim($parts[1] ?? '');
    $tabelasProd = null;
    if (preg_match('/(\d+)/', $countOut, $m)) {
        $tabelasProd = (int) $m[1];
    }

    $erros = [];
    $jaExistiam = 0;
    $naoAplicados = 0; // pulados sem falhar (ex.: MODIFY NOT NULL numa coluna com dados NULL)
    // "Já existe" (idempotente): a mudança já está aplicada.
    $idempotentes = ['1050', '1060', '1061', '1091', 'already exists', 'Duplicate column', 'Duplicate key', 'Duplicate foreign key'];
    // Exigiriam alterar DADOS existentes (nulabilidade/truncamento) — pulamos em vez de falhar o deploy.
    $dadosSkip = ['1265', '1048', '1138', 'Data truncated', 'cannot be null', 'Invalid use of NULL'];
    foreach (explode("\n", $applyOut) as $ln) {
        $ln = trim($ln);
        if ($ln === '' || strncasecmp($ln, 'ERROR', 5) !== 0) {
            continue;
        }
        $cls = 'erro';
        foreach ($idempotentes as $ig) {
            if (stripos($ln, $ig) !== false) { $cls = 'idem'; break; }
        }
        if ($cls === 'erro') {
            foreach ($dadosSkip as $ig) {
                if (stripos($ln, $ig) !== false) { $cls = 'dados'; break; }
            }
        }
        if ($cls === 'idem') {
            $jaExistiam++;
        } elseif ($cls === 'dados') {
            $naoAplicados++;
        } else {
            $erros[] = $ln;
        }
    }
    // Falha REAL (conexão/SSH/banco): o sinal confiável é a contagem de tabelas NÃO ter voltado.
    // (Não uso o código de saída: `mysql --force` sai != 0 mesmo quando só houve erros já tratados.)
    if ($erros === [] && $tabelasProd === null) {
        $motivo = trim((string) ($r['stderr'] ?? ''));
        if ($motivo === '') {
            $motivo = $applyOut !== '' ? substr($applyOut, 0, 200) : 'não foi possível confirmar a aplicação (sem contagem de tabelas)';
        }
        $erros[] = $motivo;
    }
    $aplicados = max(0, $total - $jaExistiam - $naoAplicados - count($erros));
    return [
        'ok' => $erros === [],
        'total' => $total,
        'aplicados' => $aplicados,
        'ja_existiam' => $jaExistiam,
        'nao_aplicados' => $naoAplicados,
        'erros' => $erros,
        'tabelas_prod' => $tabelasProd,
    ];
}

function adm_deploy_db_schema_snapshot(PDO $pdo): array
{
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $tables = [];
    $st = $pdo->query("SELECT TABLE_NAME, TABLE_TYPE, ENGINE, TABLE_COLLATION
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = " . $pdo->quote((string) $db) . " AND TABLE_TYPE = 'BASE TABLE'");
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $name = $t['TABLE_NAME'];
        $cols = $pdo->query("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA, COLUMN_DEFAULT, COLLATION_NAME
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = " . $pdo->quote((string) $db) . " AND TABLE_NAME = " . $pdo->quote($name) . " ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC);
        $idx = $pdo->query("SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, INDEX_TYPE
            FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = " . $pdo->quote((string) $db) . " AND TABLE_NAME = " . $pdo->quote($name) . " ORDER BY INDEX_NAME, SEQ_IN_INDEX")->fetchAll(PDO::FETCH_ASSOC);
        $fks = $pdo->query("SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = " . $pdo->quote((string) $db) . " AND TABLE_NAME = " . $pdo->quote($name) . " AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
        $tables[$name] = [
            'meta' => $t,
            'columns' => $cols,
            'indexes' => $idx,
            'fks' => $fks,
        ];
    }
    return $tables;
}

function adm_deploy_compare_database(PDO $pdo, int $serverId): array
{
    @set_time_limit(300);
    $server = adm_deploy_server_get($pdo, $serverId);
    if (!$server) {
        return ['ok' => false, 'error' => 'Servidor não encontrado'];
    }
    $connection = [
        'homolog' => ['connected' => false, 'label' => 'Homologação (local)', 'info' => '', 'via' => 'MySQL local'],
        'producao' => ['connected' => false, 'label' => 'Produção', 'info' => '', 'via' => null],
    ];

    $localPdo = $pdo ?: adm_deploy_db_connect_local();
    if (!$localPdo) {
        return ['ok' => false, 'error' => 'Não foi possível conectar ao banco local (homologação).', 'connection' => $connection];
    }
    $local = adm_deploy_db_schema_snapshot($localPdo);
    $connection['homolog']['connected'] = true;
    try {
        $connection['homolog']['info'] = (string) $localPdo->query('SELECT DATABASE()')->fetchColumn() . ' · ' . count($local) . ' tabelas';
    } catch (Throwable $e) {
        $connection['homolog']['info'] = count($local) . ' tabelas';
    }

    if (adm_deploy_is_local_server($server)) {
        $remotePdo = adm_deploy_db_connect_remote($server);
        if (!$remotePdo) {
            return ['ok' => false, 'error' => 'Configure credenciais do banco remoto ou use host local com mesmo MySQL.', 'connection' => $connection];
        }
        $remote = adm_deploy_db_schema_snapshot($remotePdo);
        $connection['producao']['via'] = 'MySQL direto';
    } else {
        $snap = adm_deploy_db_schema_snapshot_via_ssh($server);
        if (!$snap['ok']) {
            $remotePdo = adm_deploy_db_connect_remote($server);
            if (!$remotePdo) {
                return ['ok' => false, 'error' => $snap['error'] ?? 'Não foi possível ler o banco de produção.', 'connection' => $connection];
            }
            $remote = adm_deploy_db_schema_snapshot($remotePdo);
            $connection['producao']['via'] = 'MySQL direto';
        } else {
            $remote = $snap['schema'];
            $connection['producao']['via'] = 'SSH';
        }
    }
    $connection['producao']['connected'] = true;
    $prodDb = trim((string) ($server['db_name'] ?? ''));
    $connection['producao']['info'] = ($prodDb !== '' ? $prodDb . ' · ' : '') . count($remote) . ' tabelas';

    $tabelasNovas = [];
    $tabelasRemovidas = [];
    $colunasNovas = [];
    $colunasRemovidas = [];
    $colunasAlteradas = [];
    $indicesDiff = [];

    foreach ($local as $tbl => $def) {
        if (!isset($remote[$tbl])) {
            $tabelasNovas[] = $tbl;
            continue;
        }
        $lcols = [];
        foreach ($def['columns'] as $c) {
            $lcols[$c['COLUMN_NAME']] = $c;
        }
        $rcols = [];
        foreach ($remote[$tbl]['columns'] as $c) {
            $rcols[$c['COLUMN_NAME']] = $c;
        }
        foreach ($lcols as $cn => $c) {
            if (!isset($rcols[$cn])) {
                $colunasNovas[] = $tbl . '.' . $cn;
            } elseif ($rcols[$cn]['COLUMN_TYPE'] !== $c['COLUMN_TYPE'] || $rcols[$cn]['IS_NULLABLE'] !== $c['IS_NULLABLE']) {
                $colunasAlteradas[] = ['table' => $tbl, 'column' => $cn, 'local' => $c['COLUMN_TYPE'], 'remote' => $rcols[$cn]['COLUMN_TYPE']];
            }
        }
        foreach ($rcols as $cn => $_) {
            if (!isset($lcols[$cn])) {
                $colunasRemovidas[] = $tbl . '.' . $cn;
            }
        }
    }
    foreach ($remote as $tbl => $_) {
        if (!isset($local[$tbl])) {
            $tabelasRemovidas[] = $tbl;
        }
    }

    $migrationSql = adm_deploy_generate_migration_sql($local, $remote, $tabelasNovas, $colunasNovas, $colunasAlteradas);

    $summary = [
        'tabelas_novas' => count($tabelasNovas),
        'tabelas_removidas' => count($tabelasRemovidas),
        'colunas_novas' => count($colunasNovas),
        'colunas_removidas' => count($colunasRemovidas),
        'colunas_alteradas' => count($colunasAlteradas),
        'indices_diff' => count($indicesDiff),
        'total_diffs' => count($tabelasNovas) + count($colunasNovas) + count($colunasAlteradas),
    ];
    $detail = [
        'tabelas_novas' => $tabelasNovas,
        'tabelas_removidas' => $tabelasRemovidas,
        'colunas_novas' => array_slice($colunasNovas, 0, 100),
        'colunas_removidas' => array_slice($colunasRemovidas, 0, 50),
        'colunas_alteradas' => array_slice($colunasAlteradas, 0, 50),
        'migration_sql' => $migrationSql,
    ];

    $pdo->prepare('INSERT INTO adm_deploy_comparisons (server_id, tipo, summary_json, detail_json) VALUES (?, "database", ?, ?)')
        ->execute([$serverId, json_encode($summary, JSON_UNESCAPED_UNICODE), json_encode($detail, JSON_UNESCAPED_UNICODE)]);

    return ['ok' => true, 'summary' => $summary, 'detail' => $detail, 'connection' => $connection];
}

function adm_deploy_generate_migration_sql(array $local, array $remote, array $newTables, array $newCols, array $alteredCols): array
{
    $sql = [];
    foreach ($newTables as $tbl) {
        if (!isset($local[$tbl])) {
            continue;
        }
        // Views / sem colunas: não gerar CREATE TABLE
        if (empty($local[$tbl]['columns'])) {
            continue;
        }
        $parts = [];
        $pk = [];
        foreach ($local[$tbl]['columns'] as $c) {
            $line = '`' . $c['COLUMN_NAME'] . '` ' . $c['COLUMN_TYPE'];
            $line .= $c['IS_NULLABLE'] === 'NO' ? ' NOT NULL' : ' NULL';
            if (!empty($c['EXTRA'])) {
                $line .= ' ' . $c['EXTRA'];
            }
            $parts[] = $line;
            if (($c['COLUMN_KEY'] ?? '') === 'PRI') {
                $pk[] = '`' . $c['COLUMN_NAME'] . '`';
            }
        }
        if ($pk) {
            $parts[] = 'PRIMARY KEY (' . implode(',', $pk) . ')';
        }
        if ($parts) {
            $sql[] = 'CREATE TABLE IF NOT EXISTS `' . $tbl . '` (' . implode(', ', $parts) . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
        }
    }
    foreach ($newCols as $ref) {
        [$tbl, $col] = explode('.', $ref, 2);
        if (!isset($local[$tbl])) {
            continue;
        }
        foreach ($local[$tbl]['columns'] as $c) {
            if ($c['COLUMN_NAME'] === $col) {
                $line = 'ALTER TABLE `' . $tbl . '` ADD COLUMN `' . $col . '` ' . $c['COLUMN_TYPE'];
                // Sem DEFAULT, NOT NULL em coluna nova quebra tabelas com dados — preferir NULL se não for auto_increment
                $extra = (string) ($c['EXTRA'] ?? '');
                if (stripos($extra, 'auto_increment') !== false) {
                    $line .= ' NOT NULL ' . $extra;
                } elseif ($c['IS_NULLABLE'] === 'NO') {
                    $line .= ' NULL'; // evita falha em tabelas populadas; ajuste manual se precisar
                } else {
                    $line .= ' NULL';
                }
                $line .= ';';
                $sql[] = $line;
                break;
            }
        }
    }
    foreach ($alteredCols as $ch) {
        $tbl = $ch['table'];
        $col = $ch['column'];
        if (!isset($local[$tbl])) {
            continue;
        }
        foreach ($local[$tbl]['columns'] as $c) {
            if ($c['COLUMN_NAME'] === $col) {
                $sql[] = 'ALTER TABLE `' . $tbl . '` MODIFY COLUMN `' . $col . '` ' . $c['COLUMN_TYPE']
                    . ($c['IS_NULLABLE'] === 'NO' ? ' NOT NULL' : ' NULL') . ';';
                break;
            }
        }
    }
    return $sql;
}

function adm_deploy_validate_sql_commands(array $sqlLines): array
{
    $safe = [];
    $blocked = [];
    $danger = ['DROP DATABASE', 'DROP TABLE', 'TRUNCATE', 'DELETE FROM', 'DROP COLUMN'];
    foreach ($sqlLines as $line) {
        $u = strtoupper(trim($line));
        $isDanger = false;
        foreach ($danger as $d) {
            if (str_contains($u, $d)) {
                $isDanger = true;
                $blocked[] = ['sql' => $line, 'reason' => 'Comando destrutivo bloqueado: ' . $d];
                break;
            }
        }
        if (!$isDanger && preg_match('/^(CREATE|ALTER|INSERT|UPDATE|GRANT|CREATE OR REPLACE)/i', trim($line))) {
            $safe[] = $line;
        } elseif (!$isDanger) {
            $blocked[] = ['sql' => $line, 'reason' => 'Comando não permitido automaticamente'];
        }
    }
    return ['safe' => $safe, 'blocked' => $blocked];
}

function adm_deploy_backup_dir(): string
{
    $dir = dirname(__DIR__, 2) . '/tmp/deploy_backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function adm_deploy_create_backup(PDO $pdo, int $serverId, ?int $runId, int $adminId): array
{
    @set_time_limit(600);
    $server = adm_deploy_server_get($pdo, $serverId);
    if (!$server) {
        return ['ok' => false, 'error' => 'Servidor não encontrado.'];
    }
    if ($serverId <= 0) {
        return ['ok' => false, 'error' => 'Selecione um servidor de produção antes de gerar backup.'];
    }

    $ts = date('Ymd_His');
    $localBase = adm_deploy_backup_dir() . '/srv' . $serverId . '_' . $ts;
    @mkdir($localBase, 0755, true);

    $files = [];
    $remotePath = null;
    $status = 'failed';
    $sizeBytes = 0;
    $meta = ['server_nome' => $server['nome'], 'ambiente' => $server['ambiente']];

    if (adm_deploy_is_local_server($server)) {
        $dbName = (string) ($server['db_name'] ?? '');
        if ($dbName !== '') {
            $dumpFile = $localBase . '/database.sql';
            $host = '127.0.0.1';
            $port = (int) ($server['db_port'] ?? 3307);
            $user = $server['db_user'] ?? 'root';
            $pass = adm_deploy_decrypt($server['db_pass_enc'] ?? null) ?? '';
            $mysqldump = PHP_OS_FAMILY === 'Windows' ? 'C:\\xampp\\mysql\\bin\\mysqldump.exe' : 'mysqldump';
            if (is_file($mysqldump) || PHP_OS_FAMILY !== 'Windows') {
                $cmd = sprintf(
                    '%s -h%s -P%d -u%s %s %s > %s',
                    escapeshellarg($mysqldump),
                    escapeshellarg($host),
                    $port,
                    escapeshellarg((string) $user),
                    $pass !== '' ? '-p' . escapeshellarg($pass) : '',
                    escapeshellarg($dbName),
                    escapeshellarg($dumpFile)
                );
                adm_deploy_shell_run($cmd, 300);
                if (is_file($dumpFile) && filesize($dumpFile) > 0) {
                    $files[] = $dumpFile;
                    $status = 'ok';
                }
            }
        }
        $meta['local'] = true;
    } else {
        $bk = adm_deploy_create_backup_remote($server, $ts);
        if (!$bk['ok']) {
            return ['ok' => false, 'error' => $bk['error'] ?? 'Falha ao gerar backup no servidor remoto.'];
        }
        $remotePath = $bk['remote_path'];
        $meta['remote'] = true;
        $meta['remote_path'] = $remotePath;
        $meta['db_dump'] = $bk['has_db'] ?? false;
        $meta['files_archive'] = $bk['has_files'] ?? false;
        $sizeBytes = (int) ($bk['size_bytes'] ?? 0);

        if (!empty($bk['local_sql']) && is_file($bk['local_sql'])) {
            $files[] = $bk['local_sql'];
            $meta['local_sql'] = $bk['local_sql'];
        }

        $status = (!empty($bk['has_db']) || !empty($bk['has_files'])) ? 'ok' : 'failed';
    }

    if ($status === 'ok') {
        $sizeBytes = $sizeBytes > 0 ? $sizeBytes : array_sum(array_map(static fn($f) => is_file($f) ? filesize($f) : 0, $files));
    }

    $storagePath = $remotePath ?: $localBase;

    try {
        $pdo->prepare('INSERT INTO adm_deploy_backups (server_id, run_id, tipo, storage_path, size_bytes, status, admin_id, meta) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([
                $serverId,
                $runId,
                'full',
                $storagePath,
                $sizeBytes,
                $status,
                $adminId,
                json_encode($meta, JSON_UNESCAPED_UNICODE),
            ]);
    } catch (Throwable $e) {
        error_log('adm_deploy_create_backup insert: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Erro ao registrar backup no banco.'];
    }

    $backupId = (int) $pdo->lastInsertId();
    if ($backupId <= 0) {
        return ['ok' => false, 'error' => 'Backup criado mas ID não retornado.'];
    }

    return [
        'ok' => $status === 'ok',
        'backup_id' => $backupId,
        'path' => $storagePath,
        'local_path' => $localBase,
        'remote_path' => $remotePath,
        'status' => $status,
        'size_bytes' => $sizeBytes,
        'files' => $files,
        'message' => $status === 'ok'
            ? 'Backup do servidor ' . $server['nome'] . ' gerado com sucesso.'
            : 'Backup registrado com falha parcial.',
    ];
}

/**
 * Backup no servidor de produção via SSH (banco + arquivos compactados no VPS).
 * @return array{ok:bool, remote_path?:string, has_db?:bool, has_files?:bool, size_bytes?:int, local_sql?:string, error?:string}
 */
function adm_deploy_create_backup_remote(array $server, string $ts): array
{
    $projectPath = rtrim(str_replace('\\', '/', (string) $server['project_path']), '/');
    if ($projectPath === '') {
        return ['ok' => false, 'error' => 'Caminho do projeto remoto não configurado.'];
    }

    $remoteDir = $projectPath . '/tmp/deploy_backups/' . $ts;
    $dbName = trim((string) ($server['db_name'] ?? ''));
    $dbUser = trim((string) ($server['db_user'] ?? 'root'));
    $dbPass = adm_deploy_decrypt($server['db_pass_enc'] ?? null) ?? '';
    $dbHost = strtolower(trim((string) ($server['db_host'] ?? '')));
    $dbPort = (int) ($server['db_port'] ?? 3306);

    $steps = ['mkdir -p ' . adm_deploy_sh_quote($remoteDir)];

    if ($dbName !== '') {
        $hostPart = '';
        if ($dbHost !== '' && !in_array($dbHost, ['localhost', '127.0.0.1'], true)) {
            $hostPart = ' -h ' . adm_deploy_sh_quote($dbHost) . ' -P ' . $dbPort;
        }
        $steps[] = 'MYSQL_PWD=' . adm_deploy_sh_quote($dbPass)
            . ' mysqldump --single-transaction --skip-lock-tables -u ' . adm_deploy_sh_quote($dbUser) . $hostPart . ' '
            . adm_deploy_sh_quote($dbName)
            . ' > ' . adm_deploy_sh_quote($remoteDir . '/database.sql') . ' 2>/dev/null';
    }

    $exclude = '';
    foreach (adm_deploy_ignore_paths() as $ig) {
        $exclude .= ' --exclude=' . adm_deploy_sh_quote('./' . $ig);
    }
    $steps[] = 'tar -czf ' . adm_deploy_sh_quote($remoteDir . '/files.tar.gz')
        . $exclude . ' -C ' . adm_deploy_sh_quote($projectPath) . ' . 2>/dev/null';

    $steps[] = 'test -s ' . adm_deploy_sh_quote($remoteDir . '/database.sql') . ' && echo @@HAS_DB@@ yes || echo @@HAS_DB@@ no';
    $steps[] = 'test -s ' . adm_deploy_sh_quote($remoteDir . '/files.tar.gz') . ' && echo @@HAS_FILES@@ yes || echo @@HAS_FILES@@ no';
    $steps[] = 'du -sb ' . adm_deploy_sh_quote($remoteDir) . ' 2>/dev/null | awk \'{print "@@SIZE@@ "$1}\'';
    $steps[] = 'echo @@BACKUP_OK@@ ' . adm_deploy_sh_quote($remoteDir);

    $r = adm_deploy_ssh_exec($server, implode(' && ', $steps), 600);
    $out = (string) $r['stdout'];
    if (!$r['ok'] || !str_contains($out, '@@BACKUP_OK@@')) {
        return ['ok' => false, 'error' => trim((string) ($r['stderr'] ?: $out)) ?: 'Falha SSH ao gerar backup remoto.'];
    }

    $hasDb = str_contains($out, '@@HAS_DB@@ yes');
    $hasFiles = str_contains($out, '@@HAS_FILES@@ yes');
    $sizeBytes = 0;
    if (preg_match('/@@SIZE@@\s+(\d+)/', $out, $m)) {
        $sizeBytes = (int) $m[1];
    }

    $localBase = adm_deploy_backup_dir() . '/srv' . (int) $server['id'] . '_' . $ts;
    @mkdir($localBase, 0755, true);
    $localSql = $localBase . '/database.sql';

    if ($hasDb) {
        $remoteSql = $remoteDir . '/database.sql';
        $scp = adm_deploy_scp_download($server, $remoteSql, $localSql);
        if (!$scp['ok']) {
            $localSql = '';
        }
    } else {
        $localSql = '';
    }

    return [
        'ok' => $hasDb || $hasFiles,
        'remote_path' => $remoteDir,
        'has_db' => $hasDb,
        'has_files' => $hasFiles,
        'size_bytes' => $sizeBytes,
        'local_sql' => $localSql ?: null,
        'error' => ($hasDb || $hasFiles) ? null : 'Backup remoto vazio (verifique mysqldump e permissões).',
    ];
}

function adm_deploy_git_overview(array $server): array
{
    $root = adm_deploy_homolog_root($server);
    $git = adm_git_info();
    $branch = @shell_exec('git -C ' . escapeshellarg($root) . ' branch --show-current 2>nul');
    $status = @shell_exec('git -C ' . escapeshellarg($root) . ' status --porcelain 2>nul');
    $diff = adm_git_diff_stats();
    return [
        'branch' => trim((string) $branch) ?: ($server['git_branch'] ?? 'main'),
        'hash' => $git['hash'],
        'autor' => $git['autor'],
        'mensagem' => $git['mensagem'],
        'dirty' => trim((string) $status) !== '',
        'status_lines' => array_slice(array_filter(explode("\n", trim((string) $status))), 0, 30),
        'diff' => $diff,
    ];
}

function adm_deploy_git_action(array $server, string $action, ?string $message = null): array
{
    $root = adm_deploy_homolog_root($server);
    $gitPath = rtrim((string) ($server['git_path'] ?? $server['project_path']), '/\\');
    $branch = $server['git_branch'] ?? 'main';
    $cmds = [
        'fetch' => 'git fetch --all',
        'pull' => 'git pull --no-edit origin ' . escapeshellarg($branch),
        'status' => 'git status -sb',
        'diff' => 'git diff --stat',
    ];
    if ($action === 'commit' && $message) {
        $cmds['commit'] = 'git add -A && git commit -m ' . escapeshellarg($message);
    }
    if ($action === 'push') {
        $cmds['push'] = 'git push origin ' . escapeshellarg($branch);
    }
    if ($action === 'tag' && $message) {
        $cmds['tag'] = 'git tag -a ' . escapeshellarg($message) . ' -m ' . escapeshellarg('Release ' . $message);
    }
    if (!isset($cmds[$action])) {
        return ['ok' => false, 'error' => 'Ação Git inválida'];
    }
    $cmd = $cmds[$action];
    if (adm_deploy_is_local_server($server)) {
        $r = adm_deploy_shell_run('cd ' . escapeshellarg($root) . ' && ' . $cmd, 120);
    } else {
        // Ações de rede (fetch/pull/push) rodam NÃO-INTERATIVAS e com LIMITE RÍGIDO de tempo no servidor:
        // 'timeout 45' mata o git se ele travar (rede/credencial/host inacessível) — nunca pendura o deploy.
        if (in_array($action, ['fetch', 'pull', 'push'], true)) {
            $cmd = "timeout 45 env GIT_TERMINAL_PROMPT=0 GIT_SSH_COMMAND='ssh -o BatchMode=yes -o ConnectTimeout=15 -o StrictHostKeyChecking=accept-new' " . $cmd;
        }
        $r = adm_deploy_ssh_exec($server, 'cd ' . escapeshellarg($gitPath) . ' && ' . $cmd, 60);
    }
    return ['ok' => $r['ok'], 'output' => $r['stdout'] . ($r['stderr'] ? "\n" . $r['stderr'] : ''), 'code' => $r['code']];
}

function adm_deploy_run_steps_def(): array
{
    return [
        ['key' => 'validate_server', 'label' => '1. Validar servidor', 'pct' => 5],
        ['key' => 'validate_permissions', 'label' => '2. Validar permissões', 'pct' => 8],
        ['key' => 'git_check', 'label' => '3. Verificar Git', 'pct' => 12],
        ['key' => 'git_pull', 'label' => '4. Git Pull', 'pct' => 18],
        ['key' => 'compare_files', 'label' => '5. Comparar arquivos', 'pct' => 28],
        ['key' => 'compare_db', 'label' => '6. Comparar banco', 'pct' => 38],
        ['key' => 'backup', 'label' => '7. Backup automático', 'pct' => 48],
        ['key' => 'validate_sql', 'label' => '8. Validar SQL', 'pct' => 55],
        ['key' => 'migration', 'label' => '9. Executar migrations', 'pct' => 65],
        ['key' => 'sync_files', 'label' => '10. Enviar arquivos', 'pct' => 78],
        ['key' => 'clear_cache', 'label' => '11. Limpar cache', 'pct' => 85],
        ['key' => 'composer', 'label' => '12. Composer', 'pct' => 88],
        ['key' => 'validate_app', 'label' => '13. Validar aplicação', 'pct' => 92],
        ['key' => 'auto_tests', 'label' => '14. Testes automáticos', 'pct' => 96],
        ['key' => 'git_tag', 'label' => '15. Tag Git / Release', 'pct' => 98],
        ['key' => 'finalize', 'label' => '16. Finalizar deploy', 'pct' => 100],
    ];
}

function adm_deploy_run_create(PDO $pdo, int $serverId, int $adminId, string $adminNome, string $versao): array
{
    $git = adm_git_info();
    $pdo->prepare('INSERT INTO adm_deploy_runs (server_id, tipo, status, progress_pct, git_hash, versao, admin_id, admin_nome, started_at) VALUES (?, "deploy", "pending", 0, ?, ?, ?, ?, NOW())')
        ->execute([$serverId, $git['hash'], $versao, $adminId, $adminNome]);
    $runId = (int) $pdo->lastInsertId();
    foreach (adm_deploy_run_steps_def() as $s) {
        $pdo->prepare('INSERT INTO adm_deploy_run_logs (run_id, step_key, step_label, status) VALUES (?,?,?,?)')
            ->execute([$runId, $s['key'], $s['label'], 'pending']);
    }
    return ['ok' => true, 'run_id' => $runId];
}

function adm_deploy_run_get(PDO $pdo, int $runId): ?array
{
    $st = $pdo->prepare('SELECT r.*, s.nome AS server_nome, s.ambiente FROM adm_deploy_runs r JOIN adm_deploy_servers s ON s.id = r.server_id WHERE r.id = ?');
    $st->execute([$runId]);
    $run = $st->fetch(PDO::FETCH_ASSOC);
    if (!$run) {
        return null;
    }
    $st2 = $pdo->prepare('SELECT * FROM adm_deploy_run_logs WHERE run_id = ? ORDER BY id');
    $st2->execute([$runId]);
    $run['steps'] = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return $run;
}

function adm_deploy_log_step(PDO $pdo, int $runId, string $key, string $status, string $msg, ?int $ms = null, ?array $meta = null): void
{
    $pdo->prepare('UPDATE adm_deploy_run_logs SET status=?, mensagem=?, duracao_ms=?, meta=? WHERE run_id=? AND step_key=?')
        ->execute([$status, $msg, $ms, $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null, $runId, $key]);
}

function adm_deploy_run_update(PDO $pdo, int $runId, array $fields): void
{
    $sets = [];
    $vals = [];
    foreach ($fields as $k => $v) {
        $sets[] = $k . '=?';
        $vals[] = $v;
    }
    $vals[] = $runId;
    $pdo->prepare('UPDATE adm_deploy_runs SET ' . implode(',', $sets) . ' WHERE id=?')->execute($vals);
}

function adm_deploy_run_is_cancelled(PDO $pdo, int $runId): bool
{
    try {
        $st = $pdo->prepare('SELECT status FROM adm_deploy_runs WHERE id = ?');
        $st->execute([$runId]);
        return $st->fetchColumn() === 'cancelled';
    } catch (Throwable $e) {
        return false;
    }
}

function adm_deploy_run_cancel(PDO $pdo, int $runId, int $adminId): array
{
    $run = adm_deploy_run_get($pdo, $runId);
    if (!$run) {
        return ['ok' => false, 'error' => 'Run não encontrado'];
    }
    if (in_array($run['status'], ['success', 'failed', 'rolled_back', 'cancelled'], true)) {
        return ['ok' => true, 'already' => true, 'run' => $run];
    }
    adm_deploy_run_update($pdo, $runId, [
        'status' => 'cancelled',
        'erro_msg' => 'Cancelado pelo usuário',
        'finished_at' => date('Y-m-d H:i:s'),
    ]);
    adm_deploy_audit($pdo, $adminId, 'deploy_cancel', 'Deploy cancelado run #' . $runId, ['run_id' => $runId]);
    return ['ok' => true, 'run' => adm_deploy_run_get($pdo, $runId)];
}

/**
 * Health-check HTTP pós-deploy: acessa a URL pública do site e confere que respondeu
 * (não 5xx/4xx, sem erro PHP na página). Retorna checked=false se não há site_url.
 * @return array{checked:bool, ok:bool, status:int, ms:int, error:string, url:string}
 */
function adm_deploy_http_healthcheck(array $server): array
{
    $url = trim((string) ($server['site_url'] ?? ''));
    if ($url === '') {
        return ['checked' => false, 'ok' => false, 'status' => 0, 'ms' => 0, 'error' => 'sem URL de site cadastrada', 'url' => ''];
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    $t0 = microtime(true);
    $status = 0;
    $body = '';
    $err = '';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT => 'SF-Deploy-HealthCheck/1.0',
        ]);
        $body = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => ['timeout' => 20, 'ignore_errors' => true],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $body = (string) @file_get_contents($url, false, $ctx);
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
    }
    $ms = (int) ((microtime(true) - $t0) * 1000);
    $fatal = $body !== '' && preg_match('/(Fatal error|Parse error|Uncaught\s+\w+Error)/i', $body);
    if ($status === 0) {
        return ['checked' => true, 'ok' => false, 'status' => 0, 'ms' => $ms, 'error' => 'sem resposta (' . ($err ?: 'timeout/DNS') . ')', 'url' => $url];
    }
    if ($status >= 400) {
        return ['checked' => true, 'ok' => false, 'status' => $status, 'ms' => $ms, 'error' => 'site retornou HTTP ' . $status, 'url' => $url];
    }
    if ($fatal) {
        return ['checked' => true, 'ok' => false, 'status' => $status, 'ms' => $ms, 'error' => 'erro PHP detectado na página inicial', 'url' => $url];
    }
    return ['checked' => true, 'ok' => true, 'status' => $status, 'ms' => $ms, 'error' => '', 'url' => $url];
}

/**
 * Health-check NO PRÓPRIO SERVIDOR (curl localhost via SSH): testa se o app responde ali,
 * sem depender de domínio público/SSL. Ideal quando o site ainda não tem DNS/SSL.
 */
function adm_deploy_http_healthcheck_server_local(array $server): array
{
    if (adm_deploy_is_local_server($server)) {
        return ['checked' => false, 'ok' => false, 'status' => 0, 'error' => 'servidor local', 'via' => 'servidor'];
    }
    $cmd = "curl -s -o /dev/null -w '%{http_code}' -m 15 http://127.0.0.1/ 2>/dev/null || echo 000";
    $r = adm_deploy_ssh_exec($server, $cmd, 25);
    $code = (int) trim((string) $r['stdout']);
    if ($code === 0) {
        return ['checked' => true, 'ok' => false, 'status' => 0, 'error' => 'app não respondeu no servidor (curl 127.0.0.1)', 'via' => 'servidor'];
    }
    if ($code >= 500) {
        return ['checked' => true, 'ok' => false, 'status' => $code, 'error' => 'app retornou HTTP ' . $code . ' no servidor', 'via' => 'servidor'];
    }
    return ['checked' => true, 'ok' => true, 'status' => $code, 'error' => '', 'via' => 'servidor'];
}

/**
 * Health-check inteligente: tenta a URL pública; se não houver ou falhar (domínio/SSL fora),
 * cai para o teste no próprio servidor. Assim funciona mesmo sem domínio/SSL configurado.
 */
function adm_deploy_healthcheck_smart(array $server): array
{
    $pub = adm_deploy_http_healthcheck($server);
    if (!empty($pub['checked']) && !empty($pub['ok'])) {
        $pub['via'] = 'público';
        return $pub;
    }
    $loc = adm_deploy_http_healthcheck_server_local($server);
    if (!empty($loc['checked'])) {
        // guarda também o motivo do público, se houve, para contexto
        if (!empty($pub['checked'])) {
            $loc['pub_error'] = $pub['error'] ?? '';
        }
        return $loc;
    }
    $pub['via'] = 'público';
    return $pub;
}

/**
 * Pré-voo (dry-run): roda SÓ verificações de leitura e devolve um resumo go/no-go.
 * Nada é enviado ao servidor — serve para conferir antes de publicar.
 */
function adm_deploy_preflight(PDO $pdo, int $serverId): array
{
    @set_time_limit(300);
    $server = adm_deploy_server_get($pdo, $serverId);
    if (!$server) {
        return ['ok' => false, 'error' => 'Servidor não encontrado'];
    }
    $items = [];

    // 1. Conexão + permissão + disco
    $st = adm_deploy_server_status($pdo, $serverId);
    $items[] = ['label' => 'Servidor acessível', 'status' => $st['online'] ? 'ok' : 'bad',
        'detail' => $st['online'] ? (string) ($st['status_label'] ?? 'online') : (string) ($st['connection_error'] ?? 'offline')];
    $items[] = ['label' => 'Permissão de escrita', 'status' => !empty($st['writable']) ? 'ok' : ($st['online'] ? 'bad' : 'warn'),
        'detail' => !empty($st['writable']) ? 'gravável' : 'sem permissão de escrita no projeto'];
    $items[] = ['label' => 'Espaço em disco', 'status' => 'ok', 'detail' => (string) ($st['metrics']['disk'] ?? '—')];

    // 2. Git
    $git = adm_deploy_git_overview($server);
    $items[] = ['label' => 'Git limpo', 'status' => empty($git['dirty']) ? 'ok' : 'warn',
        'detail' => 'branch ' . ($git['branch'] ?? '?') . ' @ ' . ($git['hash'] ?? '—') . (empty($git['dirty']) ? '' : ' · alterações não commitadas')];

    // 3. Backup recente
    $bk = null;
    try {
        $stb = $pdo->prepare("SELECT * FROM adm_deploy_backups WHERE server_id = ? AND status = 'ok' ORDER BY id DESC LIMIT 1");
        $stb->execute([$serverId]);
        $bk = $stb->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        // tabela pode não existir ainda
    }
    if ($bk) {
        $ageH = (time() - strtotime((string) $bk['created_at'])) / 3600;
        $items[] = ['label' => 'Backup disponível', 'status' => 'ok',
            'detail' => 'último há ' . ($ageH < 1 ? round($ageH * 60) . ' min' : round($ageH, 1) . ' h') . ' · um novo será gerado no deploy'];
    } else {
        $items[] = ['label' => 'Backup disponível', 'status' => 'ok', 'detail' => 'o deploy gera um backup automático antes de publicar'];
    }

    // 4. Arquivos a mudar
    $nFiles = null;
    $fileCmp = adm_deploy_compare_files($pdo, $serverId);
    if (!empty($fileCmp['ok'])) {
        $nFiles = (int) ($fileCmp['summary']['total_alteracoes'] ?? 0);
        $items[] = ['label' => 'Arquivos a enviar', 'status' => 'ok', 'detail' => $nFiles . ' arquivo(s) diferente(s)'];
    } else {
        $items[] = ['label' => 'Arquivos a enviar', 'status' => 'warn', 'detail' => (string) ($fileCmp['error'] ?? 'não foi possível comparar')];
    }

    // 5. SQL / migrations
    $nSql = 0;
    $nBlocked = 0;
    $dbCmp = adm_deploy_compare_database($pdo, $serverId);
    if (!empty($dbCmp['ok'])) {
        $val = adm_deploy_validate_sql_commands($dbCmp['detail']['migration_sql'] ?? []);
        $nSql = count($val['safe']);
        $nBlocked = count($val['blocked']);
        $items[] = ['label' => 'Migrações de banco', 'status' => $nBlocked > 0 ? 'warn' : 'ok',
            'detail' => $nSql . ' comando(s) seguro(s)' . ($nBlocked > 0 ? ', ' . $nBlocked . ' bloqueado(s) — revise' : '')];
    } else {
        $items[] = ['label' => 'Migrações de banco', 'status' => 'warn',
            'detail' => (string) ($dbCmp['error'] ?? 'sem comparação de banco (configure o DB remoto)')];
    }

    // 6. 2FA em produção
    if (($server['ambiente'] ?? '') === 'producao') {
        $items[] = ['label' => '2FA em produção', 'status' => 'ok', 'detail' => 'código 2FA será exigido ao publicar'];
    }

    // 7. Health-check inteligente (URL pública OU o próprio servidor — não depende de domínio/SSL)
    $hc = adm_deploy_healthcheck_smart($server);
    if (!empty($hc['checked'])) {
        $via = $hc['via'] ?? '';
        $items[] = ['label' => 'App respondendo (' . $via . ')', 'status' => $hc['ok'] ? 'ok' : 'warn',
            'detail' => $hc['ok'] ? ('HTTP ' . $hc['status'] . (isset($hc['ms']) ? ' em ' . $hc['ms'] . 'ms' : '')) : (string) $hc['error']];
    } else {
        $items[] = ['label' => 'Health-check', 'status' => 'warn',
            'detail' => 'não foi possível verificar (sem URL e sem acesso local)'];
    }

    $hasBad = false;
    foreach ($items as $it) {
        if ($it['status'] === 'bad') {
            $hasBad = true;
            break;
        }
    }
    return [
        'ok' => true,
        'ready' => !$hasBad,
        'items' => $items,
        'resumo' => ['arquivos' => $nFiles, 'sql_seguro' => $nSql, 'sql_bloqueado' => $nBlocked],
    ];
}

function adm_deploy_auto_tests(array $server, ?array $ctx = null): array
{
    $root = adm_deploy_homolog_root($server);
    $errors = [];
    $checks = 0;
    $phpBin = PHP_OS_FAMILY === 'Windows' ? 'C:\\xampp\\php\\php.exe' : 'php';

    $critical = ['index.php', 'login.php', 'includes/config.php', 'includes/db_connect.php'];
    foreach ($critical as $f) {
        $checks++;
        if (!is_file($root . '/' . $f)) {
            $errors[] = 'Arquivo ausente: ' . $f;
        }
    }

    // Lint de TODOS os .php alterados neste deploy (antes validava só o index.php).
    $changed = [];
    if (is_array($ctx) && isset($ctx['file_cmp']['detail'])) {
        $changed = array_merge(
            $ctx['file_cmp']['detail']['novos'] ?? [],
            $ctx['file_cmp']['detail']['alterados'] ?? []
        );
    }
    $phpChanged = array_values(array_filter(
        $changed,
        static fn ($f) => str_ends_with(strtolower((string) $f), '.php')
    ));
    if ($phpChanged === []) {
        $phpChanged = ['index.php']; // fallback: ao menos o index
    }
    $lintErros = 0;
    foreach ($phpChanged as $rel) {
        $abs = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, (string) $rel);
        if (!is_file($abs)) {
            continue;
        }
        $checks++;
        $lint = @shell_exec($phpBin . ' -l ' . escapeshellarg($abs) . ' 2>&1');
        if (!is_string($lint) || !str_contains($lint, 'No syntax errors')) {
            $lintErros++;
            $errors[] = 'Sintaxe PHP inválida: ' . $rel;
            if ($lintErros >= 10) {
                $errors[] = '… (e mais arquivos com erro — corrija e revalide)';
                break;
            }
        }
    }

    if (is_file($root . '/composer.json')) {
        $checks++;
        if (!is_dir($root . '/vendor')) {
            $errors[] = 'vendor/ ausente — rode composer install';
        }
    }

    return ['ok' => $errors === [], 'checks' => $checks, 'errors' => $errors];
}

function adm_deploy_execute_step(PDO $pdo, int $runId, string $stepKey, ?array $opts = null): array
{
    @set_time_limit(300); // passos podem levar tempo (SSH, backup, sync) — evita o PHP morrer no meio
    $run = adm_deploy_run_get($pdo, $runId);
    if (!$run) {
        return ['ok' => false, 'error' => 'Run não encontrado'];
    }
    if (in_array($run['status'], ['success', 'failed', 'cancelled', 'rolled_back'], true)) {
        return ['ok' => false, 'error' => 'Deploy já finalizado', 'run' => $run];
    }

    $server = adm_deploy_server_get($pdo, (int) $run['server_id']);
    if (!$server) {
        return ['ok' => false, 'error' => 'Servidor não encontrado'];
    }

    adm_deploy_run_update($pdo, $runId, ['status' => 'running', 'step_atual' => $stepKey]);
    $t0 = microtime(true);
    $opts = $opts ?? [];
    $ctx = $opts['ctx'] ?? [];

    try {
        switch ($stepKey) {
            case 'validate_server':
                $st = adm_deploy_server_status($pdo, (int) $server['id']);
                if (!$st['online']) {
                    throw new RuntimeException('Servidor offline ou inacessível.');
                }
                $msg = 'Servidor online.';
                break;

            case 'validate_permissions':
                $st = adm_deploy_server_status($pdo, (int) $server['id']);
                if (!$st['writable']) {
                    throw new RuntimeException('Sem permissão de escrita no projeto.');
                }
                $msg = 'Permissões OK.';
                break;

            case 'git_check':
                $g = adm_deploy_git_overview($server);
                $ctx['git'] = $g;
                if ($g['dirty'] && empty($opts['allow_dirty'])) {
                    // Não é fatal: o deploy envia os arquivos por sincronização, não depende do git do servidor.
                    $wmsg = 'Git com alterações locais no servidor (branch ' . ($g['branch'] ?? '?') . ') — seguindo mesmo assim; o envio é por sincronização de arquivos.';
                    adm_deploy_log_step($pdo, $runId, $stepKey, 'warn', $wmsg, (int) ((microtime(true) - $t0) * 1000));
                    adm_deploy_run_update($pdo, $runId, ['progress_pct' => 12]);
                    return ['ok' => true, 'warn' => true, 'message' => $wmsg, 'run' => adm_deploy_run_get($pdo, $runId), 'ctx' => $ctx];
                }
                $msg = 'Git branch: ' . $g['branch'] . ' @ ' . ($g['hash'] ?? '—');
                break;

            case 'git_pull':
                $r = adm_deploy_git_action($server, 'pull');
                if (!$r['ok']) {
                    // Não é fatal: os arquivos são enviados via sincronização (passo 10).
                    // Ex.: repositório sem credencial no servidor, ou deploy que não usa git no destino.
                    $out = trim((string) ($r['output'] ?? ''));
                    $wmsg = 'Git pull ignorado (' . (substr($out, 0, 180) ?: 'sem saída') . '). Os arquivos serão enviados na sincronização.';
                    adm_deploy_log_step($pdo, $runId, $stepKey, 'warn', $wmsg, (int) ((microtime(true) - $t0) * 1000));
                    adm_deploy_run_update($pdo, $runId, ['progress_pct' => 18]);
                    return ['ok' => true, 'warn' => true, 'message' => $wmsg, 'run' => adm_deploy_run_get($pdo, $runId), 'ctx' => $ctx];
                }
                $msg = 'Pull concluído.';
                break;

            case 'compare_files':
                $cmp = adm_deploy_compare_files($pdo, (int) $server['id']);
                if (!$cmp['ok']) {
                    throw new RuntimeException($cmp['error'] ?? 'Comparação falhou');
                }
                $ctx['file_cmp'] = $cmp;
                $msg = $cmp['summary']['total_alteracoes'] . ' diferença(s) de arquivo.';
                break;

            case 'compare_db':
                $cmp = adm_deploy_compare_database($pdo, (int) $server['id']);
                if (!$cmp['ok']) {
                    adm_deploy_log_step($pdo, $runId, $stepKey, 'warn', $cmp['error'] ?? 'Sem comparação DB', (int) ((microtime(true) - $t0) * 1000));
                    adm_deploy_run_update($pdo, $runId, ['progress_pct' => 38]);
                    return ['ok' => true, 'warn' => true, 'message' => $cmp['error'], 'run' => adm_deploy_run_get($pdo, $runId), 'ctx' => $ctx];
                }
                $ctx['db_cmp'] = $cmp;
                $msg = ($cmp['summary']['total_diffs'] ?? 0) . ' diferença(s) no schema.';
                break;

            case 'backup':
                $bk = adm_deploy_create_backup($pdo, (int) $server['id'], $runId, (int) $run['admin_id']);
                if (!$bk['ok']) {
                    throw new RuntimeException($bk['error'] ?? 'Backup falhou');
                }
                adm_deploy_run_update($pdo, $runId, ['backup_id' => $bk['backup_id']]);
                $ctx['backup_id'] = $bk['backup_id'];
                $msg = 'Backup #' . $bk['backup_id'];
                break;

            case 'validate_sql':
                $sql = $ctx['db_cmp']['detail']['migration_sql'] ?? [];
                $val = adm_deploy_validate_sql_commands($sql);
                $ctx['sql_safe'] = $val['safe'];
                $ctx['sql_blocked'] = $val['blocked'];
                if ($val['blocked'] && empty($opts['approve_sql'])) {
                    $msg = count($val['safe']) . ' SQL seguro(s), ' . count($val['blocked']) . ' bloqueado(s).';
                } else {
                    $msg = count($val['safe']) . ' comando(s) validado(s).';
                }
                break;

            case 'migration':
                $toRun = $ctx['sql_safe'] ?? [];
                if ($toRun === [] || empty($opts['approve_sql'])) {
                    adm_deploy_log_step($pdo, $runId, $stepKey, 'skipped', 'Nenhuma migration aprovada', (int) ((microtime(true) - $t0) * 1000));
                    adm_deploy_run_update($pdo, $runId, ['progress_pct' => 65]);
                    return ['ok' => true, 'skipped' => true, 'run' => adm_deploy_run_get($pdo, $runId), 'ctx' => $ctx];
                }
                if (adm_deploy_is_local_server($server)) {
                    // Servidor LOCAL (teste): aplica direto via PDO.
                    $remotePdo = adm_deploy_db_connect_remote($server);
                    if (!$remotePdo) {
                        throw new RuntimeException('Conexão DB local indisponível');
                    }
                    $aplicados = 0;
                    $jaExistiam = 0;
                    $errosSql = [];
                    $ignoraveis = ['Duplicate column name', 'Duplicate key name', 'already exists', 'Duplicate foreign key', 'check that column/key exists', '1050', '1060', '1061', '1091'];
                    foreach ($toRun as $line) {
                        if (trim((string) $line) === '') {
                            continue;
                        }
                        try {
                            $remotePdo->exec($line);
                            $aplicados++;
                        } catch (Throwable $e) {
                            $emsg = $e->getMessage();
                            $idem = false;
                            foreach ($ignoraveis as $ig) {
                                if (stripos($emsg, $ig) !== false) { $idem = true; break; }
                            }
                            if ($idem) {
                                $jaExistiam++;
                            } else {
                                $errosSql[] = substr(trim((string) $line), 0, 70) . ' → ' . $emsg;
                                if (count($errosSql) >= 5) { break; }
                            }
                        }
                    }
                    adm_deploy_run_update($pdo, $runId, ['sql_aplicado' => 1]);
                    if ($errosSql !== []) {
                        throw new RuntimeException($aplicados . ' aplicado(s), ' . $jaExistiam . ' já existiam, ' . count($errosSql) . ' com erro. 1º: ' . $errosSql[0]);
                    }
                    $msg = $aplicados . ' comando(s) aplicado(s)' . ($jaExistiam ? ', ' . $jaExistiam . ' já existiam' : '') . '.';
                    break;
                }
                // Servidor REMOTO: aplica NO BANCO DA PRODUÇÃO via SSH (banco não exposto à internet).
                $mig = adm_deploy_run_migration_via_ssh($server, $toRun);
                adm_deploy_run_update($pdo, $runId, ['sql_aplicado' => 1]);
                if (!$mig['ok']) {
                    $det = $mig['erros'][0] ?? ($mig['error'] ?? 'erro ao aplicar');
                    throw new RuntimeException(
                        $mig['aplicados'] . ' aplicado(s), ' . $mig['ja_existiam'] . ' já existiam, '
                        . count($mig['erros']) . ' com erro. 1º: ' . $det
                    );
                }
                $msg = $mig['aplicados'] . ' comando(s) aplicado(s) NA PRODUÇÃO'
                    . ($mig['ja_existiam'] ? ', ' . $mig['ja_existiam'] . ' já existiam' : '')
                    . (!empty($mig['nao_aplicados']) ? ', ' . $mig['nao_aplicados'] . ' pulado(s) (exigiriam alterar dados existentes)' : '')
                    . (($mig['tabelas_prod'] ?? null) !== null ? ' · produção com ' . $mig['tabelas_prod'] . ' tabelas' : '') . '.';
                break;

            case 'sync_files':
                if (adm_deploy_run_is_cancelled($pdo, $runId)) {
                    return ['ok' => false, 'cancelled' => true, 'error' => 'Deploy cancelado pelo usuário.', 'run' => adm_deploy_run_get($pdo, $runId), 'ctx' => $ctx];
                }
                $cmp = $ctx['file_cmp'] ?? adm_deploy_compare_files($pdo, (int) $server['id']);
                // Lista COMPLETA (sem o corte de 200 da exibição); protegidos já saíram no compare, mas reforçamos aqui.
                $lista = $cmp['detail']['sync_all'] ?? array_merge($cmp['detail']['novos'] ?? [], $cmp['detail']['alterados'] ?? []);
                $protected = adm_deploy_protected_files($server);
                $lista = array_values(array_filter($lista, static fn ($r) => !adm_deploy_is_protected((string) $r, $protected)));
                if ($lista === []) {
                    $msg = 'Nenhum arquivo a enviar (ou todos protegidos).';
                    break;
                }
                $localRoot = adm_deploy_homolog_root($server);
                $up = adm_deploy_sync_upload_batch($pdo, $runId, $server, $localRoot, $lista);
                if (!empty($up['cancelled'])) {
                    $cmsg = 'Cancelado durante o envio — ' . $up['enviados'] . ' de ' . count($lista) . ' arquivo(s).';
                    adm_deploy_log_step($pdo, $runId, $stepKey, 'warn', $cmsg, (int) ((microtime(true) - $t0) * 1000));
                    return ['ok' => false, 'cancelled' => true, 'error' => 'Deploy cancelado pelo usuário.', 'run' => adm_deploy_run_get($pdo, $runId), 'ctx' => $ctx];
                }
                $enviados = (int) $up['enviados'];
                $falhas = $up['falhas'] ?? [];
                $via = $up['metodo'] === 'tar' ? 'pacote tar.gz' : 'arquivo a arquivo';
                adm_deploy_run_update($pdo, $runId, ['arquivos_enviados' => $enviados]);
                if ($falhas !== []) {
                    $amostra = implode(', ', array_slice($falhas, 0, 5)) . (count($falhas) > 5 ? '…' : '');
                    $warn = $enviados . ' arquivo(s) enviados (' . $via . '), ' . count($falhas) . ' falharam (' . $amostra . ').';
                    adm_deploy_log_step($pdo, $runId, $stepKey, 'warn', $warn, (int) ((microtime(true) - $t0) * 1000));
                    adm_deploy_run_update($pdo, $runId, ['progress_pct' => 78]);
                    $ctx['sync_falhas'] = $falhas;
                    return ['ok' => true, 'warn' => true, 'message' => $warn, 'run' => adm_deploy_run_get($pdo, $runId), 'ctx' => $ctx];
                }
                $msg = $enviados . ' arquivo(s) enviados via ' . $via . ' · ' . count($protected) . ' protegido(s) preservado(s).';
                break;

            case 'clear_cache':
                $cacheDirs = ['cache', 'tmp'];
                foreach ($cacheDirs as $d) {
                    adm_deploy_ssh_exec($server, 'rm -rf ' . escapeshellarg(rtrim($server['project_path'], '/') . '/' . $d . '/*') . ' 2>/dev/null; mkdir -p ' . escapeshellarg(rtrim($server['project_path'], '/') . '/' . $d), 30);
                }
                $msg = 'Cache limpo.';
                break;

            case 'composer':
                $r = adm_deploy_ssh_exec($server, 'cd ' . escapeshellarg($server['project_path']) . ' && composer install --no-dev --optimize-autoloader 2>&1 | tail -5', 300);
                $msg = $r['ok'] ? 'Composer OK.' : 'Composer ignorado ou falhou (verifique manualmente).';
                if (!$r['ok']) {
                    adm_deploy_log_step($pdo, $runId, $stepKey, 'warn', $msg, (int) ((microtime(true) - $t0) * 1000));
                    adm_deploy_run_update($pdo, $runId, ['progress_pct' => 88]);
                    return ['ok' => true, 'warn' => true, 'run' => adm_deploy_run_get($pdo, $runId), 'ctx' => $ctx];
                }
                break;

            case 'validate_app':
                $r = adm_deploy_ssh_exec($server, 'php -l ' . escapeshellarg(rtrim($server['project_path'], '/') . '/index.php'), 30);
                if (!$r['ok']) {
                    throw new RuntimeException('index.php inválido no destino');
                }
                $msg = 'Aplicação validada.';
                // Health-check inteligente (público OU no próprio servidor). NÃO derruba o deploy:
                // o site pode estar sem domínio/SSL — só avisa. Rollback fica para falhas reais (sync/migração).
                $hc = adm_deploy_healthcheck_smart($server);
                $ctx['healthcheck'] = $hc;
                if (!empty($hc['checked'])) {
                    $via = $hc['via'] ?? '';
                    if (empty($hc['ok'])) {
                        $wmsg = 'Aplicação validada (php -l OK) · health-check não confirmou o app (' . $via . ': ' . $hc['error'] . '). Verifique o site manualmente — o deploy seguiu.';
                        adm_deploy_log_step($pdo, $runId, $stepKey, 'warn', $wmsg, (int) ((microtime(true) - $t0) * 1000));
                        adm_deploy_run_update($pdo, $runId, ['progress_pct' => 92]);
                        return ['ok' => true, 'warn' => true, 'message' => $wmsg, 'run' => adm_deploy_run_get($pdo, $runId), 'ctx' => $ctx];
                    }
                    $msg = 'Aplicação validada · app respondeu HTTP ' . $hc['status'] . ' (' . $via . ').';
                }
                break;

            case 'auto_tests':
                $t = adm_deploy_auto_tests($server, $ctx);
                if (!$t['ok']) {
                    throw new RuntimeException(implode('; ', $t['errors']));
                }
                $msg = $t['checks'] . ' verificações OK (inclui lint dos arquivos alterados).';
                break;

            case 'git_tag':
                $ver = $run['versao'] ?? ('v' . date('Y.m.d'));
                adm_deploy_git_action($server, 'tag', $ver);
                adm_deploy_run_update($pdo, $runId, ['git_tag' => $ver]);
                adm_deploy_git_action($server, 'push');
                $msg = 'Tag ' . $ver;
                break;

            case 'finalize':
                $dur = (int) (time() - strtotime((string) $run['started_at']));
                $report = [
                    'versao' => $run['versao'],
                    'git_hash' => $run['git_hash'],
                    'arquivos' => $run['arquivos_enviados'],
                    'sql' => (bool) $run['sql_aplicado'],
                    'backup_id' => $run['backup_id'],
                    'recomendacoes' => ['Monitore logs por 15 min', 'Valide login e dashboard do cliente'],
                ];
                adm_deploy_run_update($pdo, $runId, [
                    'status' => 'success',
                    'progress_pct' => 100,
                    'finished_at' => date('Y-m-d H:i:s'),
                    'duracao_seg' => $dur,
                    'report_json' => json_encode($report, JSON_UNESCAPED_UNICODE),
                ]);
                $pdo->prepare('UPDATE adm_deploy_servers SET ultima_sync = NOW() WHERE id = ?')->execute([$server['id']]);
                adm_deploy_audit($pdo, (int) $run['admin_id'], 'deploy_success', 'Deploy concluído run #' . $runId, $report);
                $msg = 'Deploy finalizado com sucesso.';
                adm_deploy_log_step($pdo, $runId, $stepKey, 'ok', $msg, (int) ((microtime(true) - $t0) * 1000));
                return ['ok' => true, 'done' => true, 'run' => adm_deploy_run_get($pdo, $runId), 'report' => $report, 'ctx' => $ctx];

            default:
                throw new RuntimeException('Etapa desconhecida: ' . $stepKey);
        }

        $pct = 0;
        foreach (adm_deploy_run_steps_def() as $s) {
            if ($s['key'] === $stepKey) {
                $pct = $s['pct'];
                break;
            }
        }
        adm_deploy_log_step($pdo, $runId, $stepKey, 'ok', $msg, (int) ((microtime(true) - $t0) * 1000));
        adm_deploy_run_update($pdo, $runId, ['progress_pct' => $pct]);
        return ['ok' => true, 'message' => $msg, 'run' => adm_deploy_run_get($pdo, $runId), 'ctx' => $ctx];
    } catch (Throwable $e) {
        $err = $e->getMessage();
        adm_deploy_log_step($pdo, $runId, $stepKey, 'error', $err, (int) ((microtime(true) - $t0) * 1000));
        adm_deploy_run_update($pdo, $runId, ['status' => 'failed', 'erro_msg' => $err, 'finished_at' => date('Y-m-d H:i:s')]);
        adm_deploy_audit($pdo, (int) $run['admin_id'], 'deploy_failed', $err, ['run_id' => $runId, 'step' => $stepKey]);

        if (!empty($run['backup_id']) && !empty($opts['auto_rollback'])) {
            adm_deploy_rollback_run($pdo, $runId, (int) $run['admin_id']);
        }
        return ['ok' => false, 'error' => $err, 'run' => adm_deploy_run_get($pdo, $runId), 'ctx' => $ctx];
    }
}

function adm_deploy_rollback_run(PDO $pdo, int $runId, int $adminId): array
{
    $run = adm_deploy_run_get($pdo, $runId);
    if (!$run || !$run['backup_id']) {
        return ['ok' => false, 'error' => 'Sem backup para rollback'];
    }
    $st = $pdo->prepare('SELECT * FROM adm_deploy_backups WHERE id = ?');
    $st->execute([$run['backup_id']]);
    $bk = $st->fetch(PDO::FETCH_ASSOC);
    if (!$bk) {
        return ['ok' => false, 'error' => 'Backup não encontrado'];
    }
    $server = adm_deploy_server_get($pdo, (int) $run['server_id']);
    $dump = rtrim($bk['storage_path'], '/\\') . '/database.sql';
    if (is_file($dump) && $server) {
        $remotePdo = adm_deploy_db_connect_remote($server);
        if ($remotePdo) {
            $sql = file_get_contents($dump);
            foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $stmt) {
                if ($stmt !== '' && !str_starts_with($stmt, '--')) {
                    try {
                        $remotePdo->exec($stmt);
                    } catch (Throwable $e) {
                        // continua parcial
                    }
                }
            }
        }
    }
    if ($server && !adm_deploy_is_local_server($server)) {
        $gitPath = $server['git_path'] ?? $server['project_path'];
        adm_deploy_ssh_exec($server, 'cd ' . escapeshellarg($gitPath) . ' && git reset --hard HEAD~1', 60);
    }
    adm_deploy_run_update($pdo, $runId, ['status' => 'rolled_back']);
    adm_deploy_audit($pdo, $adminId, 'rollback', 'Rollback run #' . $runId, ['backup' => $bk['storage_path']]);
    return ['ok' => true, 'message' => 'Rollback executado (DB + Git quando possível).'];
}

function adm_deploy_history(PDO $pdo, int $limit = 30): array
{
    $limit = max(1, min(100, $limit));
    try {
        $st = $pdo->query("SELECT r.*, s.nome AS server_nome, s.ambiente AS server_ambiente
            FROM adm_deploy_runs r JOIN adm_deploy_servers s ON s.id = r.server_id
            ORDER BY r.id DESC LIMIT {$limit}");
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('adm_deploy_history: ' . $e->getMessage());
        return [];
    }
}

function adm_deploy_backups_list(PDO $pdo, int $serverId = 0): array
{
    try {
        if ($serverId > 0) {
            $st = $pdo->prepare('SELECT * FROM adm_deploy_backups WHERE server_id = ? ORDER BY id DESC LIMIT 50');
            $st->execute([$serverId]);
        } else {
            $st = $pdo->query('SELECT b.*, s.nome AS server_nome FROM adm_deploy_backups b JOIN adm_deploy_servers s ON s.id = b.server_id ORDER BY b.id DESC LIMIT 50');
        }
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('adm_deploy_backups_list: ' . $e->getMessage());
        return [];
    }
}

function adm_deploy_backup_get(PDO $pdo, int $backupId): ?array
{
    if ($backupId <= 0) {
        return null;
    }
    $st = $pdo->prepare('SELECT b.*, s.nome AS server_nome FROM adm_deploy_backups b JOIN adm_deploy_servers s ON s.id = b.server_id WHERE b.id = ? LIMIT 1');
    $st->execute([$backupId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Baixa arquivo de backup (local ou VPS via SCP) e envia ao navegador.
 * @param string $file database|files
 */
function adm_deploy_backup_download_send(PDO $pdo, int $backupId, string $file = 'database'): void
{
    @set_time_limit(300);
    $bk = adm_deploy_backup_get($pdo, $backupId);
    if (!$bk || ($bk['status'] ?? '') !== 'ok') {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Backup não encontrado ou incompleto.';
        return;
    }

    $file = $file === 'files' ? 'files' : 'database';
    $remoteName = $file === 'files' ? 'files.tar.gz' : 'database.sql';
    $meta = json_decode((string) ($bk['meta'] ?? ''), true);
    if (!is_array($meta)) {
        $meta = [];
    }

    $basePath = rtrim((string) ($meta['remote_path'] ?? $bk['storage_path']), '/\\');
    $remoteFile = $basePath . '/' . $remoteName;

    $localFile = adm_deploy_backup_dir() . '/dl_' . $backupId . '_' . $remoteName;
    if (is_file($localFile) && filesize($localFile) > 100) {
        adm_deploy_backup_stream_file($localFile, 'backup_' . $backupId . '_' . $remoteName);
        return;
    }

    if (is_file($remoteFile) && filesize($remoteFile) > 100) {
        adm_deploy_backup_stream_file($remoteFile, 'backup_' . $backupId . '_' . $remoteName);
        return;
    }

    $server = adm_deploy_server_get($pdo, (int) $bk['server_id']);
    if (!$server || adm_deploy_is_local_server($server)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Arquivo de backup não disponível localmente.';
        return;
    }

    $dl = adm_deploy_scp_download($server, $remoteFile, $localFile);
    if (!$dl['ok'] || !is_file($localFile) || filesize($localFile) < 100) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Não foi possível baixar o backup do servidor de produção. Tente novamente em instantes.';
        return;
    }

    adm_deploy_backup_stream_file($localFile, 'backup_' . $backupId . '_' . $remoteName);
}

function adm_deploy_backup_stream_file(string $path, string $downloadName): void
{
    if (!is_file($path)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Arquivo não encontrado.';
        return;
    }
    $mime = str_ends_with($downloadName, '.sql') ? 'application/sql' : 'application/gzip';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
}
