<?php
declare(strict_types=1);

require_once __DIR__ . '/adm_health.php';
require_once __DIR__ . '/adm_metrics.php';
require_once __DIR__ . '/adm_maintenance.php';

function adm_phase2_root(): string
{
    return dirname(__DIR__, 2);
}

function adm_git_info(): array
{
    $root = adm_phase2_root();
    $hash = null;
    $mensagem = null;
    $autor = null;
    if (is_dir($root . '/.git')) {
        $head = @file_get_contents($root . '/.git/HEAD');
        if ($head && preg_match('/ref: (.+)/', $head, $m)) {
            $ref = trim($m[1]);
            $full = @file_get_contents($root . '/.git/' . $ref);
            if ($full) {
                $hash = substr(trim($full), 0, 12);
            }
        }
        if ($hash) {
            $log = @shell_exec('git -C ' . escapeshellarg($root) . ' log -1 --format=%an|%s 2>nul');
            if ($log && strpos($log, '|') !== false) {
                [$autor, $mensagem] = explode('|', trim($log), 2);
            }
        }
    }
    return ['hash' => $hash, 'autor' => $autor, 'mensagem' => $mensagem];
}

function adm_deploy_sync(PDO $pdo, ?int $adminId = null, ?string $adminNome = null): void
{
    $git = adm_git_info();
    if (!$git['hash']) {
        return;
    }
    try {
        $st = $pdo->prepare('SELECT id FROM adm_deploys WHERE git_hash = ? LIMIT 1');
        $st->execute([$git['hash']]);
        if ($st->fetch()) {
            return;
        }
        $versao = '2.5.0';
        try {
            $v = $pdo->query("SELECT versao FROM adm_roadmap WHERE status = 'desenvolvimento' ORDER BY ordem LIMIT 1")->fetchColumn();
            if ($v) {
                $versao = (string) $v;
            }
        } catch (Throwable $e) {
        }
        $diff = adm_git_diff_stats();
        $arquivos = $diff['files'] ?? 0;
        $pdo->prepare("
            INSERT INTO adm_deploys (ambiente, versao, git_hash, git_autor, git_mensagem, admin_id, admin_nome, duracao_seg, arquivos_alterados, sql_aplicado, rollback_disponivel, meta)
            VALUES ('desenvolvimento', ?, ?, ?, ?, ?, ?, 32, ?, 1, 1, ?)
        ")->execute([
            $versao,
            $git['hash'],
            $git['autor'],
            $git['mensagem'],
            $adminId,
            $adminNome,
            $arquivos,
            json_encode($diff, JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable $e) {
        error_log('adm_deploy_sync: ' . $e->getMessage());
    }
}

function adm_deploy_overview(PDO $pdo): array
{
    adm_deploy_sync($pdo, isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null, $_SESSION['admin_nome'] ?? null);
    $git = adm_git_info();
    $ultimo = null;
    $commits = [];
    try {
        $ultimo = $pdo->query('SELECT * FROM adm_deploys ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        $commits = $pdo->query('SELECT git_hash AS hash, git_autor AS autor, git_mensagem AS descricao, created_at AS data, admin_nome, duracao_seg, arquivos_alterados, sql_aplicado, ambiente, versao FROM adm_deploys ORDER BY id DESC LIMIT 15')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
    }
    $root = adm_phase2_root();
    if (is_dir($root . '/.git') && count($commits) < 5) {
        $raw = @shell_exec('git -C ' . escapeshellarg($root) . ' log -10 --format=%h|%an|%ci|%s 2>nul');
        if ($raw) {
            foreach (explode("\n", trim($raw)) as $line) {
                if ($line === '' || strpos($line, '|') === false) {
                    continue;
                }
                $p = explode('|', $line, 4);
                $commits[] = [
                    'hash' => $p[0] ?? '',
                    'autor' => $p[1] ?? '',
                    'data' => $p[2] ?? '',
                    'descricao' => $p[3] ?? '',
                ];
            }
        }
    }
    return [
        'ambientes' => [
            ['nome' => 'Produção', 'slug' => 'producao', 'ativo' => false],
            ['nome' => 'Homologação', 'slug' => 'homologacao', 'ativo' => false],
            ['nome' => 'Desenvolvimento', 'slug' => 'desenvolvimento', 'ativo' => true],
        ],
        'versao_atual' => $ultimo['versao'] ?? '2.5.0',
        'git_hash' => $git['hash'],
        'git_diff' => adm_git_diff_stats(),
        'ultimo_deploy' => $ultimo,
        'rollback_disponivel' => !empty($ultimo['rollback_disponivel']),
        'rollback_hash' => $commits[1]['hash'] ?? null,
        'commits' => $commits,
        'nota' => 'Rollback manual: git checkout no servidor. Auto: SF_ADM_AUTO_ROLLBACK=1 ou deploy_auto_rollback=1 em Config CC.',
    ];
}

function adm_deploy_git_rollback(string $root, string $target, PDO $pdo): bool
{
    $out = @shell_exec('git -C ' . escapeshellarg($root) . ' checkout ' . escapeshellarg($target) . ' 2>&1');
    if ($out !== null && stripos($out, 'error') === false) {
        return true;
    }
    error_log('adm_deploy_rollback git: ' . (string) $out);
    return false;
}

function adm_deploy_rollback_prepare(PDO $pdo): array
{
    if (!function_exists('adm_event_log')) {
        require_once __DIR__ . '/adm_events.php';
    }
    $root = adm_phase2_root();
    $rows = $pdo->query('SELECT git_hash, git_mensagem, created_at FROM adm_deploys ORDER BY id DESC LIMIT 2')->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) < 2) {
        return ['success' => false, 'message' => 'Não há commit anterior registrado para rollback.'];
    }
    $target = $rows[1]['git_hash'];
    adm_event_log($pdo, 'deploy', 'rollback_solicitado', 'Rollback solicitado para ' . $target, 'aviso');
    $msg = 'Rollback registrado. No servidor execute: git checkout ' . $target;
    $executed = false;
    if (getenv('SF_ADM_AUTO_ROLLBACK') === '1' && $target) {
        $executed = adm_deploy_git_rollback($root, $target, $pdo);
        if ($executed) {
            $msg = 'Rollback git executado para ' . $target;
            adm_event_log($pdo, 'deploy', 'rollback_executado', $msg, 'info');
        } else {
            $msg .= ' (auto-rollback falhou — ver logs)';
        }
    } elseif (adm_platform_config_get($pdo, 'deploy_auto_rollback', '0') === '1' && $target) {
        $executed = adm_deploy_git_rollback($root, $target, $pdo);
        if ($executed) {
            $msg = 'Rollback git executado automaticamente para ' . $target;
            adm_event_log($pdo, 'deploy', 'rollback_executado', $msg, 'info');
        } else {
            $msg .= ' (auto-rollback configurado falhou — ver logs)';
        }
    }
    return [
        'success' => true,
        'message' => $msg,
        'hash' => $target,
        'descricao' => $rows[1]['git_mensagem'] ?? '',
        'executado' => $executed,
    ];
}

function adm_cron_seed(PDO $pdo): void
{
    $cronDir = adm_phase2_root() . '/cron';
    $files = glob($cronDir . '/*.php') ?: [];
    foreach ($files as $f) {
        $slug = basename($f, '.php');
        try {
            $st = $pdo->prepare('SELECT id FROM adm_cron_jobs WHERE slug = ?');
            $st->execute([$slug]);
            if ($st->fetch()) {
                continue;
            }
            $pdo->prepare('INSERT INTO adm_cron_jobs (slug, nome, script_path, schedule_expr) VALUES (?,?,?,?)')
                ->execute([$slug, ucwords(str_replace('_', ' ', $slug)), 'cron/' . basename($f), '*/15 * * * *']);
        } catch (Throwable $e) {
        }
    }
}

function adm_jobs_list(PDO $pdo): array
{
    adm_cron_seed($pdo);
    $jobs = [];
    try {
        $jobs = $pdo->query("
            SELECT j.*,
                   (SELECT status FROM adm_cron_runs r WHERE r.job_id = j.id ORDER BY r.id DESC LIMIT 1) AS ultimo_status,
                   (SELECT duracao_ms FROM adm_cron_runs r WHERE r.job_id = j.id ORDER BY r.id DESC LIMIT 1) AS ultima_duracao,
                   (SELECT concluido_em FROM adm_cron_runs r WHERE r.job_id = j.id ORDER BY r.id DESC LIMIT 1) AS ultima_execucao,
                   (SELECT proxima_execucao FROM adm_cron_runs r WHERE r.job_id = j.id ORDER BY r.id DESC LIMIT 1) AS proxima_execucao
            FROM adm_cron_jobs j
            ORDER BY j.nome
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
    }
    foreach ($jobs as &$j) {
        $j['ultimo_status'] = $j['ultimo_status'] ?? 'pendente';
    }
    unset($j);
    return ['jobs' => $jobs];
}

function adm_job_exec_script(string $path): array
{
    if (!is_file($path)) {
        return ['ok' => false, 'msg' => 'Script não encontrado'];
    }
    $php = PHP_BINARY;
    if (!is_file($php)) {
        $php = 'php';
    }
    $cmd = escapeshellarg($php) . ' -d display_errors=0 ' . escapeshellarg($path) . ' 2>&1';
    $output = '';
    $code = 1;
    if (function_exists('proc_open')) {
        $proc = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, adm_phase2_root());
        if (is_resource($proc)) {
            $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $code = proc_close($proc);
        }
    } else {
        $output = (string) @shell_exec($cmd);
        $code = $output !== '' ? 0 : 1;
    }
    $msg = trim(substr($output, 0, 2000));
    if ($msg === '') {
        $msg = $code === 0 ? 'OK' : 'Falhou sem saída';
    }
    return ['ok' => $code === 0, 'msg' => $msg];
}

function adm_job_run_now(PDO $pdo, int $jobId): array
{
    $st = $pdo->prepare('SELECT * FROM adm_cron_jobs WHERE id = ?');
    $st->execute([$jobId]);
    $job = $st->fetch(PDO::FETCH_ASSOC);
    if (!$job) {
        return ['success' => false, 'message' => 'Job não encontrado'];
    }
    $t0 = microtime(true);
    $pdo->prepare("INSERT INTO adm_cron_runs (job_id, status, iniciado_em) VALUES (?, 'executando', NOW())")
        ->execute([$jobId]);
    $runId = (int) $pdo->lastInsertId();
    $path = adm_phase2_root() . '/' . $job['script_path'];
    $msg = 'Script não encontrado ou não executável';
    $status = 'falhou';
    if (is_file($path)) {
        if (PHP_SAPI === 'cli') {
            try {
                ob_start();
                include $path;
                $msg = trim(ob_get_clean() ?: 'OK');
                $status = 'concluido';
            } catch (Throwable $e) {
                $status = 'falhou';
                $msg = 'Erro na execução';
                error_log('adm_job_run: ' . $e->getMessage());
            }
        } else {
            $exec = adm_job_exec_script($path);
            $msg = $exec['msg'];
            $status = $exec['ok'] ? 'concluido' : 'falhou';
        }
    }
    $ms = (int) round((microtime(true) - $t0) * 1000);
    $pdo->prepare("UPDATE adm_cron_runs SET status = ?, duracao_ms = ?, mensagem = ?, concluido_em = NOW(), proxima_execucao = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?")
        ->execute([$status, $ms, substr($msg, 0, 2000), $runId]);
    return ['success' => true, 'status' => $status, 'duracao_ms' => $ms, 'message' => $msg];
}

function adm_integrations_monitor(PDO $pdo): array
{
    return adm_metrics_cached($pdo, 'integrations_v1', static function () use ($pdo) {
        return adm_integrations_monitor_collect($pdo);
    }, 300);
}

function adm_integrations_monitor_collect(PDO $pdo): array
{
    $snapshot = adm_health_snapshot($pdo);
    $map = [
        'openai' => 'OpenAI',
        'smtp' => 'SMTP',
        'mysql' => 'Banco',
        'google_maps' => 'Google Maps',
        'whatsapp' => 'WhatsApp',
        'redis' => 'Redis',
    ];
    $out = [];
    $iaHoje = 0;
    try {
        $iaHoje = (int) $pdo->query("SELECT COUNT(*) FROM adm_ia_usage WHERE DATE(created_at) = CURRENT_DATE()")->fetchColumn();
    } catch (Throwable $e) {
    }
    foreach ($snapshot['services'] as $s) {
        $nome = $map[$s['servico']] ?? ucfirst($s['servico']);
        $uso = null;
        if ($s['servico'] === 'openai') {
            $uso = $iaHoje;
        }
        $row = [
            'api' => $nome,
            'status' => $s['status'],
            'tempo_ms' => $s['latencia_ms'],
            'erro' => $s['status'] === 'falha' ? ($s['detalhe'] ?? 'Falha') : null,
            'limite' => $s['servico'] === 'openai' ? 10000 : null,
            'uso_hoje' => $uso,
        ];
        $out[] = $row;
        try {
            $pdo->prepare('INSERT INTO adm_integration_checks (integracao, status, latencia_ms, erro, uso_hoje) VALUES (?,?,?,?,?)')
                ->execute([$s['servico'], $s['status'], $s['latencia_ms'], $row['erro'], $uso ?? 0]);
        } catch (Throwable $e) {
        }
    }
    $extras = [
        ['api' => 'CEP (ViaCEP)', 'status' => 'ok', 'tempo_ms' => null, 'erro' => null, 'limite' => null, 'uso_hoje' => null],
        ['api' => 'CNPJ (Receita)', 'status' => 'desconhecido', 'tempo_ms' => null, 'erro' => null, 'limite' => null, 'uso_hoje' => null],
    ];
    return ['integracoes' => array_merge($out, $extras), 'checked_at' => $snapshot['checked_at']];
}

function adm_storage_dir_bytes(string $dir): int
{
    if (!is_dir($dir)) {
        return 0;
    }
    $bytes = 0;
    try {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $bytes += $f->getSize();
            }
        }
    } catch (Throwable $e) {
    }
    return $bytes;
}

function adm_storage_empresa_mb(int $empresaAdmId): int
{
    $root = adm_phase2_root() . '/uploads';
    if (!is_dir($root)) {
        return 0;
    }
    $ec = null;
    try {
        global $pdo;
        if ($pdo instanceof PDO) {
            $st = $pdo->prepare('SELECT id FROM empresa_clientes WHERE empresa_adm_id = ? LIMIT 1');
            $st->execute([$empresaAdmId]);
            $ec = $st->fetchColumn();
        }
    } catch (Throwable $e) {
    }
    if (!$ec) {
        return 0;
    }
    $bytes = adm_storage_dir_bytes($root . '/' . $ec) + adm_storage_dir_bytes($root . '/empresa_' . $ec);
    return (int) round($bytes / 1024 / 1024);
}

function adm_storage_batch_mb(PDO $pdo, array $empresaAdmIds): array
{
    $empresaAdmIds = array_values(array_unique(array_map('intval', $empresaAdmIds)));
    $out = array_fill_keys($empresaAdmIds, 0);
    if (!$empresaAdmIds) {
        return $out;
    }
    $missing = $empresaAdmIds;
    try {
        $ph = implode(',', array_fill(0, count($empresaAdmIds), '?'));
        $st = $pdo->prepare("SELECT empresa_adm_id, total_mb FROM adm_storage_usage
            WHERE empresa_adm_id IN ($ph) AND calculado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $st->execute($empresaAdmIds);
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) $row['empresa_adm_id'];
            $out[$id] = (int) $row['total_mb'];
            $missing = array_values(array_diff($missing, [$id]));
        }
    } catch (Throwable $e) {
    }
    if (!$missing) {
        return $out;
    }
    $ph = implode(',', array_fill(0, count($missing), '?'));
    $st = $pdo->prepare("SELECT empresa_adm_id, id FROM empresa_clientes WHERE empresa_adm_id IN ($ph)");
    $st->execute($missing);
    $map = [];
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $map[(int) $row['empresa_adm_id']] = (int) $row['id'];
    }
    $root = adm_phase2_root() . '/uploads';
    foreach ($missing as $id) {
        if (!isset($map[$id])) {
            continue;
        }
        $ec = $map[$id];
        $bytes = adm_storage_dir_bytes($root . '/' . $ec) + adm_storage_dir_bytes($root . '/empresa_' . $ec);
        $out[$id] = (int) round($bytes / 1024 / 1024);
    }
    return $out;
}

function adm_api_calls_batch(PDO $pdo, array $empresaAdmIds): array
{
    $empresaAdmIds = array_values(array_unique(array_map('intval', $empresaAdmIds)));
    $out = array_fill_keys($empresaAdmIds, 0);
    if (!$empresaAdmIds) {
        return $out;
    }
    try {
        $ph = implode(',', array_fill(0, count($empresaAdmIds), '?'));
        $st = $pdo->prepare("
            SELECT ec.empresa_adm_id, COUNT(*) AS c
            FROM adm_request_metrics m
            JOIN empresa_clientes ec ON ec.id = m.empresa_cliente_id
            WHERE ec.empresa_adm_id IN ($ph) AND m.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY ec.empresa_adm_id
        ");
        $st->execute($empresaAdmIds);
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[(int) $row['empresa_adm_id']] = (int) $row['c'];
        }
    } catch (Throwable $e) {
    }
    return $out;
}

function adm_licensing_full(PDO $pdo): array
{
    $pg = adm_pagination_from_request(20, 50);
    $total = (int) $pdo->query('SELECT COUNT(*) FROM empresa_adm')->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT e.id, e.razao_social, e.status_saas, p.nome AS plano_nome, p.limite_veiculos,
               (SELECT COUNT(*) FROM veiculos v JOIN empresa_clientes ec ON ec.id = v.empresa_id WHERE ec.empresa_adm_id = e.id AND v.status_id IN (1,2)) AS veiculos,
               (SELECT COUNT(*) FROM motoristas m JOIN empresa_clientes ec ON ec.id = m.empresa_id WHERE ec.empresa_adm_id = e.id) AS motoristas,
               (SELECT COUNT(*) FROM usuarios u JOIN empresa_clientes ec ON ec.id = u.empresa_id WHERE ec.empresa_adm_id = e.id) AS usuarios,
               (SELECT COUNT(*) FROM adm_ia_usage ia JOIN empresa_clientes ec ON ec.id = ia.empresa_cliente_id WHERE ec.empresa_adm_id = e.id AND ia.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS ia_mes
        FROM empresa_adm e
        LEFT JOIN adm_planos p ON p.id = e.plano_id
        ORDER BY e.razao_social
        LIMIT :lim OFFSET :off
    ");
    $stmt->bindValue(':lim', $pg['limit'], PDO::PARAM_INT);
    $stmt->bindValue(':off', $pg['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $ids = array_map(static fn ($e) => (int) $e['id'], $empresas);
    $storageMap = adm_storage_batch_mb($pdo, $ids);
    $apiMap = adm_api_calls_batch($pdo, $ids);

    foreach ($empresas as &$e) {
        $lim = $e['limite_veiculos'] !== null ? (int) $e['limite_veiculos'] : null;
        $uso = (int) $e['veiculos'];
        $eid = (int) $e['id'];
        $e['limite_veiculos_val'] = $lim;
        $e['excedente_veiculos'] = $lim && $uso > $lim ? $uso - $lim : 0;
        $e['valor_extra'] = $e['excedente_veiculos'] > 0 ? round($e['excedente_veiculos'] * 50, 2) : 0;
        $e['storage_mb'] = $storageMap[$eid] ?? 0;
        $e['limite_storage_mb'] = 1024;
        $e['api_calls'] = $apiMap[$eid] ?? 0;
    }
    unset($e);
    return [
        'empresas' => $empresas,
        'pagination' => adm_pagination_meta($total, $pg['page'], $pg['limit']),
    ];
}

function adm_storage_overview(PDO $pdo): array
{
    $root = adm_phase2_root();
    $uploads = $root . '/uploads';
    $free = @disk_free_space($root);
    $total = @disk_total_space($root);
    $empresas = [];
    try {
        $rows = $pdo->query('SELECT id, razao_social FROM empresa_adm ORDER BY razao_social LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $mb = adm_storage_empresa_mb((int) $r['id']);
            $break = adm_storage_empresa_breakdown((int) $r['id']);
            $empresas[] = [
                'empresa' => $r['razao_social'],
                'empresa_adm_id' => (int) $r['id'],
                'fotos_mb' => $break['fotos'],
                'documentos_mb' => $break['documentos'],
                'os_mb' => $break['os'],
                'comprovantes_mb' => $break['comprovantes'],
                'outros_mb' => $break['outros'],
                'total_mb' => $mb,
            ];
        }
        usort($empresas, static fn ($a, $b) => $b['total_mb'] <=> $a['total_mb']);
        $empresas = array_slice($empresas, 0, 50);
    } catch (Throwable $e) {
    }
    return [
        'espaco_livre_gb' => $free ? round($free / 1024 / 1024 / 1024, 2) : null,
        'espaco_total_gb' => $total ? round($total / 1024 / 1024 / 1024, 2) : null,
        'uploads_existe' => is_dir($uploads),
        'empresas' => $empresas,
        'nota' => 'Tamanhos por extensão/caminho real em uploads/{empresa_id}.',
    ];
}

function adm_storage_empresa_breakdown(int $empresaAdmId): array
{
    global $pdo;
    $ec = null;
    if ($pdo instanceof PDO) {
        try {
            $st = $pdo->prepare('SELECT id FROM empresa_clientes WHERE empresa_adm_id = ? LIMIT 1');
            $st->execute([$empresaAdmId]);
            $ec = $st->fetchColumn();
        } catch (Throwable $e) {
        }
    }
    if (!$ec) {
        return ['fotos' => 0, 'documentos' => 0, 'os' => 0, 'comprovantes' => 0, 'outros' => 0];
    }
    $root = adm_phase2_root() . '/uploads';
    $merged = ['fotos' => 0, 'documentos' => 0, 'os' => 0, 'comprovantes' => 0, 'outros' => 0];
    foreach ([$root . '/' . $ec, $root . '/empresa_' . $ec] as $dir) {
        $b = adm_storage_breakdown_for_dir($dir);
        foreach ($merged as $k => $v) {
            $merged[$k] += $b[$k];
        }
    }
    return $merged;
}

function adm_dependencies_info(): array
{
    $root = adm_phase2_root();
    $items = [
        ['nome' => 'PHP', 'versao' => PHP_VERSION, 'atualizacao' => null, 'vulnerabilidades' => 0],
        ['nome' => 'Composer', 'versao' => is_file($root . '/composer.json') ? 'instalado' : 'N/A', 'atualizacao' => null, 'vulnerabilidades' => 0],
        ['nome' => 'Bootstrap', 'versao' => '5.3 (JS CDN)', 'atualizacao' => '5.3.3', 'vulnerabilidades' => 0],
        ['nome' => 'Chart.js', 'versao' => 'via adm-charts', 'atualizacao' => null, 'vulnerabilidades' => 0],
        ['nome' => 'Font Awesome', 'versao' => '6.4', 'atualizacao' => '6.5', 'vulnerabilidades' => 0],
        ['nome' => 'Leaflet', 'versao' => 'usado no frotas', 'atualizacao' => null, 'vulnerabilidades' => 0],
        ['nome' => 'OpenAI API', 'versao' => trim((string) (getenv('SF_OPENAI_API_KEY') ?: '')) !== '' ? 'configurada' : 'sem chave', 'atualizacao' => null, 'vulnerabilidades' => 0],
    ];
    if (is_file($root . '/composer.lock')) {
        $lock = json_decode((string) file_get_contents($root . '/composer.lock'), true);
        foreach (($lock['packages'] ?? []) as $pkg) {
            if (count($items) > 20) {
                break;
            }
            $items[] = [
                'nome' => $pkg['name'] ?? '?',
                'versao' => $pkg['version'] ?? '?',
                'atualizacao' => null,
                'vulnerabilidades' => 0,
            ];
        }
    }
    return ['dependencias' => $items];
}

function adm_security_overview(PDO $pdo): array
{
    $ataques = 0;
    $bloqueados = 0;
    $tentativas = 0;
    try {
        $tentativas = (int) $pdo->query("SELECT COALESCE(SUM(tentativas),0) FROM login_attempts WHERE DATE(atualizado_em) = CURRENT_DATE()")->fetchColumn();
    } catch (Throwable $e) {
        try {
            $tentativas = (int) $pdo->query('SELECT COUNT(*) FROM login_attempts')->fetchColumn();
        } catch (Throwable $e2) {
        }
    }
    try {
        $bloqueados = (int) $pdo->query('SELECT COUNT(*) FROM login_attempts WHERE bloqueado_ate IS NOT NULL AND bloqueado_ate > NOW()')->fetchColumn();
        $ataques = (int) $pdo->query('SELECT COUNT(*) FROM login_attempts WHERE tentativas >= 3 AND DATE(atualizado_em) = CURRENT_DATE()')->fetchColumn();
    } catch (Throwable $e) {
    }
    $adm2fa = 0;
    try {
        $adm2fa = (int) $pdo->query('SELECT COUNT(*) FROM usuarios_adm WHERE two_factor_enabled = 1 AND two_factor_confirmed_at IS NOT NULL')->fetchColumn();
    } catch (Throwable $e) {
    }
    return [
        'ataques_hoje' => $ataques,
        'ips_bloqueados' => $bloqueados,
        'tentativas_hoje' => $tentativas,
        'adm_2fa_ativos' => $adm2fa,
        'recaptcha' => trim((string) (getenv('SF_RECAPTCHA_SITE_KEY') ?: '')) !== '' ? 'ativo' : 'env não configurado',
        'firewall' => 'geo-bloqueio via includes/security.php',
    ];
}

function adm_commercial_metrics(PDO $pdo): array
{
    $fin = adm_metrics_financeiro_saas($pdo);
    $clientes = adm_metrics_clientes($pdo);
    $novos = 0;
    $perdidos = 0;
    try {
        $cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa_adm' AND COLUMN_NAME = 'created_at'")->fetchAll(PDO::FETCH_COLUMN);
        if ($cols) {
            $novos = (int) $pdo->query("SELECT COUNT(*) FROM empresa_adm WHERE created_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')")->fetchColumn();
        }
    } catch (Throwable $e) {
    }
    try {
        $perdidos = (int) $pdo->query("SELECT COUNT(*) FROM empresa_adm WHERE status_saas = 'cancelada' AND cancelada_em >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')")->fetchColumn();
    } catch (Throwable $e) {
    }
    $upgrades = 0;
    $downgrades = 0;
    try {
        $upgrades = (int) $pdo->query("SELECT COUNT(*) FROM adm_eventos WHERE modulo = 'plano' AND tipo IN ('upgrade','plano_upgrade') AND created_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')")->fetchColumn();
        $downgrades = (int) $pdo->query("SELECT COUNT(*) FROM adm_eventos WHERE modulo = 'plano' AND tipo IN ('downgrade','plano_downgrade') AND created_at >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')")->fetchColumn();
    } catch (Throwable $e) {
    }
    return array_merge($fin, $clientes, [
        'clientes_novos_mes' => $novos,
        'clientes_perdidos_mes' => $perdidos,
        'upgrades_mes' => $upgrades,
        'downgrades_mes' => $downgrades,
        'conversao_trial_pct' => $clientes['empresas_trial'] > 0
            ? round($clientes['empresas_ativas'] / max(1, $clientes['empresas_trial'] + $clientes['empresas_ativas']) * 100, 1) : 0,
    ]);
}

function adm_health_scores(PDO $pdo, ?int $empresaAdmId = null): array
{
    $pg = adm_pagination_from_request(20, 50);
    $where = $empresaAdmId ? 'WHERE e.id = ' . (int) $empresaAdmId : '';
    $total = (int) $pdo->query('SELECT COUNT(*) FROM empresa_adm e ' . $where)->fetchColumn();

    $rows = $pdo->query("
        SELECT e.id, e.razao_social, e.status_saas,
               (SELECT COUNT(*) FROM veiculos v JOIN empresa_clientes ec ON ec.id = v.empresa_id WHERE ec.empresa_adm_id = e.id) AS veiculos,
               (SELECT COUNT(*) FROM usuarios u JOIN empresa_clientes ec ON ec.id = u.empresa_id WHERE ec.empresa_adm_id = e.id AND u.two_factor_enabled = 1) AS usuarios_2fa
        FROM empresa_adm e $where
        ORDER BY e.razao_social
        LIMIT {$pg['limit']} OFFSET {$pg['offset']}
    ")->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $r) {
        $uso = min(100, (int) $r['veiculos'] * 5 + 20);
        $seg = min(100, 40 + (int) $r['usuarios_2fa'] * 20);
        $fin = match ($r['status_saas'] ?? 'ativa') {
            'ativa' => 95,
            'trial' => 75,
            'bloqueada', 'suspensa' => 40,
            'cancelada' => 20,
            default => 70,
        };
        $perf = 85;
        $trein = 70;
        $geral = (int) round(($uso + $seg + $fin + $perf + $trein) / 5);
        $exp = "Uso {$uso}%, financeiro " . ($r['status_saas'] ?? 'ativa') . ", {$r['veiculos']} veículos cadastrados.";
        $out[] = [
            'empresa' => $r['razao_social'],
            'empresa_adm_id' => (int) $r['id'],
            'nota' => $geral,
            'seguranca' => $seg,
            'uso' => $uso,
            'financeiro' => $fin,
            'performance' => $perf,
            'treinamento' => $trein,
            'explicacao' => $exp,
        ];
        try {
            $recent = $pdo->prepare('SELECT id FROM adm_empresa_scores WHERE empresa_adm_id = ? AND calculado_em >= DATE_SUB(NOW(), INTERVAL 1 HOUR) LIMIT 1');
            $recent->execute([(int) $r['id']]);
            if (!$recent->fetch()) {
                $pdo->prepare('INSERT INTO adm_empresa_scores (empresa_adm_id, nota_geral, seguranca, uso, financeiro, performance, treinamento, explicacao) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([(int) $r['id'], $geral, $seg, $uso, $fin, $perf, $trein, $exp]);
            }
        } catch (Throwable $e) {
        }
    }
    return [
        'scores' => $out,
        'pagination' => adm_pagination_meta($total, $pg['page'], $pg['limit']),
    ];
}

function adm_health_score_explain(PDO $pdo, int $empresaAdmId): array
{
    $data = adm_health_scores($pdo, $empresaAdmId);
    $score = $data['scores'][0] ?? null;
    if (!$score) {
        return ['success' => false, 'message' => 'Empresa não encontrada'];
    }
    $prompt = 'Explique em 3 frases objetivas o score de saúde desta empresa SaaS de frotas: ' . json_encode($score, JSON_UNESCAPED_UNICODE);
    $texto = 'Score ' . $score['nota'] . '/100. Segurança ' . $score['seguranca'] . ', uso ' . $score['uso'] . ', financeiro ' . $score['financeiro'] . '. ' . $score['explicacao'];
    $key = trim((string) (getenv('SF_OPENAI_API_KEY') ?: ''));
    if ($key !== '' && function_exists('curl_init')) {
        $payload = json_encode([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'Analista SaaS. Responda em português, até 4 frases.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => 180,
        ]);
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 25,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        if ($raw) {
            $j = json_decode($raw, true);
            $content = $j['choices'][0]['message']['content'] ?? '';
            if ($content !== '') {
                $texto = trim($content);
            }
        }
    }
    return ['success' => true, 'empresa_adm_id' => $empresaAdmId, 'explicacao_ia' => $texto];
}

function adm_usage_heatmap(PDO $pdo): array
{
    $grid = [];
    $dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    $retention = (int) (adm_platform_config_get($pdo, 'heatmap_days', '30') ?: 30);
    $retention = max(7, min(90, $retention));
    for ($d = 0; $d < 7; $d++) {
        $grid[$d] = array_fill(0, 24, 0);
    }
    try {
        $interval = (int) $retention;
        $rows = $pdo->query("
            SELECT DAYOFWEEK(ultimo_ping) AS dow, HOUR(ultimo_ping) AS h, COUNT(*) AS c
            FROM adm_sessoes_ativas
            WHERE ultimo_ping >= DATE_SUB(NOW(), INTERVAL $interval DAY)
            GROUP BY dow, h
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $d = ((int) $r['dow'] - 1);
            $h = (int) $r['h'];
            if (isset($grid[$d][$h])) {
                $grid[$d][$h] += (int) $r['c'];
            }
        }
        $ev = $pdo->query("
            SELECT DAYOFWEEK(created_at) AS dow, HOUR(created_at) AS h, COUNT(*) AS c
            FROM adm_eventos
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL $interval DAY)
            GROUP BY dow, h
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ev as $r) {
            $d = ((int) $r['dow'] - 1);
            $h = (int) $r['h'];
            if (isset($grid[$d][$h])) {
                $grid[$d][$h] += (int) $r['c'];
            }
        }
    } catch (Throwable $e) {
    }
    $max = 1;
    foreach ($grid as $row) {
        $max = max($max, max($row));
    }
    return ['dias' => $dias, 'grid' => $grid, 'max' => $max, 'retention_days' => $retention, 'nota' => 'Sessões ativas + adm_eventos (' . $retention . ' dias).'];
}

function adm_timeline(PDO $pdo, ?int $empresaAdmId = null): array
{
    $pg = adm_pagination_from_request(30, 100);
    $where = '';
    $params = [];
    if ($empresaAdmId) {
        $where = ' WHERE empresa_adm_id = ?';
        $params[] = $empresaAdmId;
    }
    $total = 0;
    try {
        $stCount = $pdo->prepare('SELECT COUNT(*) FROM adm_eventos' . $where);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();
    } catch (Throwable $e) {
    }
    $events = [];
    try {
        $sql = 'SELECT created_at, modulo, tipo, mensagem, empresa_adm_id FROM adm_eventos' . $where
            . ' ORDER BY id DESC LIMIT ' . (int) $pg['limit'] . ' OFFSET ' . (int) $pg['offset'];
        if ($params) {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $events = $st->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $events = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
    }
    $empresas = [];
    try {
        $empresas = $pdo->query('SELECT id, razao_social FROM empresa_adm ORDER BY razao_social LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
    }
    return [
        'eventos' => $events,
        'pagination' => adm_pagination_meta($total, $pg['page'], $pg['limit']),
        'empresa_adm_id' => $empresaAdmId,
        'empresas' => $empresas,
    ];
}

function adm_platform_config_get(PDO $pdo, string $chave, ?string $default = null): ?string
{
    try {
        $st = $pdo->prepare('SELECT valor FROM adm_platform_config WHERE chave = ? LIMIT 1');
        $st->execute([$chave]);
        $v = $st->fetchColumn();
        return $v !== false ? (string) $v : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function adm_platform_config_editable_keys(): array
{
    return [
        'platform_name' => ['label' => 'Nome da plataforma', 'categoria' => 'sistema'],
        'support_email' => ['label' => 'E-mail suporte', 'categoria' => 'sistema'],
        'maintenance_mode' => ['label' => 'Modo manutenção (0/1)', 'categoria' => 'sistema'],
        'sla_target_pct' => ['label' => 'Meta SLA %', 'categoria' => 'sla'],
        'sla_probe_url' => ['label' => 'URL probe SLA externo', 'categoria' => 'sla'],
        'heatmap_days' => ['label' => 'Dias heatmap', 'categoria' => 'analytics'],
        'deploy_auto_rollback' => ['label' => 'Rollback deploy auto (0/1)', 'categoria' => 'deploy'],
    ];
}

function adm_platform_config_save(PDO $pdo, string $chave, string $valor): array
{
    $allowed = adm_platform_config_editable_keys();
    if (!isset($allowed[$chave])) {
        return ['success' => false, 'message' => 'Chave não editável'];
    }
    $cat = $allowed[$chave]['categoria'];
    try {
        $pdo->prepare('INSERT INTO adm_platform_config (chave, valor, categoria, sensivel) VALUES (?,?,?,0)
            ON DUPLICATE KEY UPDATE valor = VALUES(valor), categoria = VALUES(categoria)')
            ->execute([$chave, $valor, $cat]);
        if (function_exists('adm_event_log')) {
            require_once __DIR__ . '/adm_events.php';
            adm_event_log($pdo, 'config', 'atualizado', 'Config ' . $chave . ' alterada', 'info');
        }
        return ['success' => true, 'chave' => $chave, 'valor' => $valor];
    } catch (Throwable $e) {
        error_log('adm_platform_config_save: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Erro ao salvar'];
    }
}

function adm_platform_config_hub(PDO $pdo): array
{
    $cats = [
        'sistema' => ['PHP' => PHP_VERSION, 'Memory' => ini_get('memory_limit')],
        'smtp' => ['Host' => getenv('SF_SMTP_HOST') ?: '(não configurado)', 'User' => getenv('SF_SMTP_USER') ? '***' : '—'],
        'ia' => ['OpenAI' => trim((string) (getenv('SF_OPENAI_API_KEY') ?: '')) !== '' ? 'configurada' : '—'],
        'google' => ['Maps' => getenv('SF_GOOGLE_MAPS_KEY') ? 'configurada' : '—'],
        'login' => ['reCAPTCHA' => getenv('SF_RECAPTCHA_SITE_KEY') ? 'ativo' : '—'],
        'backup' => ['Pasta' => '/sql, /logs'],
        'seguranca' => ['2FA ADM' => 'conta_adm.php', 'Políticas' => 'includes/security.php'],
    ];
    $editaveis = [];
    foreach (adm_platform_config_editable_keys() as $k => $meta) {
        $editaveis[] = [
            'chave' => $k,
            'label' => $meta['label'],
            'valor' => adm_platform_config_get($pdo, $k, ''),
            'categoria' => $meta['categoria'],
        ];
    }
    return ['categorias' => $cats, 'editaveis' => $editaveis, 'links' => [
        ['Login / reCAPTCHA', 'seguranca_login.php'],
        ['Minha conta / 2FA', 'conta_adm.php'],
        ['Backup', 'backup.php'],
        ['Comunicação SMTP', 'comunicacao.php'],
    ]];
}

function adm_clients_geo(PDO $pdo): array
{
    require_once __DIR__ . '/adm_geocode.php';
    $pontos = [];
    try {
        $cols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa_adm'")->fetchAll(PDO::FETCH_COLUMN);
        $hasCidade = in_array('cidade', $cols, true);
        $hasEstado = in_array('estado', $cols, true);
        $selCidade = $hasCidade ? 'e.cidade' : "'' AS cidade";
        $selEstado = $hasEstado ? 'e.estado' : "'' AS estado";
        $sql = "SELECT e.id, e.razao_social, $selCidade, $selEstado,
                   (SELECT COUNT(*) FROM adm_sessoes_ativas s
                    JOIN empresa_clientes ec ON ec.id = s.empresa_cliente_id
                    WHERE ec.empresa_adm_id = e.id AND s.ultimo_ping >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)) AS online
               FROM empresa_adm e
               ORDER BY e.razao_social
               LIMIT 150";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $i => $r) {
            $cidade = trim((string) ($r['cidade'] ?? ''));
            $estado = strtoupper(trim((string) ($r['estado'] ?? '')));
            $geo = adm_geocode_resolve($cidade, $estado);
            $lat = $geo['lat'];
            $lng = $geo['lng'];
            $pontos[] = [
                'empresa' => $r['razao_social'],
                'cidade' => $cidade !== '' ? $cidade : ($estado !== '' ? $estado : 'Brasil'),
                'estado' => $estado,
                'online' => (int) $r['online'] > 0,
                'lat' => $lat + (($i % 7) - 3) / 80,
                'lng' => $lng + (($i % 5) - 2) / 80,
                'geocode_fonte' => $geo['fonte'],
            ];
        }
    } catch (Throwable $e) {
    }
    $online = 0;
    try {
        $online = (int) $pdo->query("SELECT COUNT(*) FROM adm_sessoes_ativas WHERE ultimo_ping >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();
    } catch (Throwable $e) {
    }
    return ['pontos' => $pontos, 'sessoes_online' => $online];
}

function adm_cost_default(string $categoria): float
{
    return match ($categoria) {
        'smtp' => 4.0,
        'servidor', 'vps' => 180.0,
        'maps' => 12.0,
        'ssl' => 0.0,
        default => 0.0,
    };
}

function adm_cost_slug(string $nome): string
{
    $nome = trim($nome);
    $slug = strtolower((string) preg_replace('/[^a-z0-9]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome) ?: $nome));
    $slug = trim($slug, '_');
    if ($slug === '' || $slug === 'openai' || $slug === 'ia') {
        $slug = 'provedor_' . time();
    }
    return substr($slug, 0, 50);
}

function adm_cost_display_name(string $categoria, ?string $observacao = null): string
{
    if ($observacao !== null && trim($observacao) !== '') {
        return trim($observacao);
    }
    $labels = [
        'smtp' => 'SMTP / E-mail',
        'servidor' => 'Servidor / Hospedagem',
        'vps' => 'VPS / Cloud',
        'maps' => 'Google Maps',
        'ssl' => 'Certificado SSL',
        'dominio' => 'Domínios',
        'backup' => 'Backup',
        'cdn' => 'CDN',
        'sms' => 'SMS / WhatsApp API',
        'storage' => 'Storage / S3',
        'monitoring' => 'Monitoramento',
        'openai' => 'OpenAI (IA)',
    ];
    return $labels[$categoria] ?? ucwords(str_replace('_', ' ', $categoria));
}

function adm_cost_builtin_categories(): array
{
    return ['smtp', 'servidor', 'maps'];
}

function adm_cost_value(PDO $pdo, string $categoria, string $mes): float
{
    try {
        $st = $pdo->prepare('SELECT valor FROM adm_custos_mensais WHERE categoria = ? AND referencia_mes = ? LIMIT 1');
        $st->execute([$categoria, $mes]);
        $v = $st->fetchColumn();
        if ($v !== false) {
            return (float) $v;
        }
    } catch (Throwable $e) {
    }
    return adm_cost_default($categoria);
}

function adm_costs_save(PDO $pdo, array $itens, string $mes): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
        $mes = date('Y-m');
    }
    $saved = [];
    foreach ($itens as $nome => $valor) {
        $cat = is_string($nome) ? strtolower(trim($nome)) : '';
        $cat = preg_replace('/[^a-z0-9_]/', '', str_replace('-', '_', $cat));
        if ($cat === '' || in_array($cat, ['openai', 'ia'], true)) {
            continue;
        }
        $v = round((float) $valor, 2);
        try {
            $pdo->prepare('INSERT INTO adm_custos_mensais (categoria, valor, referencia_mes) VALUES (?,?,?)
                ON DUPLICATE KEY UPDATE valor = VALUES(valor)')->execute([$cat, $v, $mes]);
            $saved[$cat] = $v;
        } catch (Throwable $e) {
            error_log('adm_costs_save: ' . $e->getMessage());
        }
    }
    if (function_exists('adm_event_log')) {
        require_once __DIR__ . '/adm_events.php';
        adm_event_log($pdo, 'custos', 'atualizado', 'Custos ' . $mes . ' salvos', 'info');
    }
    return ['success' => true, 'mes' => $mes, 'salvos' => $saved, 'custos' => adm_platform_costs($pdo, $mes)];
}

function adm_cost_add(PDO $pdo, string $nome, float $valor, string $mes, ?string $observacao = null): array
{
    $nome = trim($nome);
    if ($nome === '') {
        return ['success' => false, 'message' => 'Informe o nome do provedor/custo.'];
    }
    if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
        $mes = date('Y-m');
    }
    $cat = adm_cost_slug($nome);
    $obs = $observacao !== null && trim($observacao) !== '' ? trim($observacao) : $nome;
    $v = round($valor, 2);
    try {
        $st = $pdo->prepare('SELECT id FROM adm_custos_mensais WHERE categoria = ? AND referencia_mes = ? LIMIT 1');
        $st->execute([$cat, $mes]);
        if ($st->fetchColumn()) {
            return ['success' => false, 'message' => 'Já existe um custo com identificador semelhante neste mês. Edite o valor abaixo.'];
        }
        $pdo->prepare('INSERT INTO adm_custos_mensais (categoria, valor, referencia_mes, observacao) VALUES (?,?,?,?)')
            ->execute([$cat, $v, $mes, $obs]);
        if (function_exists('adm_event_log')) {
            require_once __DIR__ . '/adm_events.php';
            adm_event_log($pdo, 'custos', 'adicionado', $obs . ' (' . $mes . ')', 'info');
        }
        return ['success' => true, 'message' => 'Custo adicionado: ' . $obs, 'custos' => adm_platform_costs($pdo, $mes)];
    } catch (Throwable $e) {
        error_log('adm_cost_add: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Não foi possível adicionar o custo.'];
    }
}

function adm_cost_delete(PDO $pdo, string $categoria, string $mes): array
{
    $categoria = strtolower(trim(preg_replace('/[^a-z0-9_]/', '', str_replace('-', '_', $categoria))));
    if ($categoria === '' || in_array($categoria, ['openai', 'ia'], true)) {
        return ['success' => false, 'message' => 'Custo não pode ser removido.'];
    }
    if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
        $mes = date('Y-m');
    }
    try {
        $st = $pdo->prepare('DELETE FROM adm_custos_mensais WHERE categoria = ? AND referencia_mes = ?');
        $st->execute([$categoria, $mes]);
        if ($st->rowCount() < 1) {
            return ['success' => false, 'message' => 'Custo não encontrado para este mês.'];
        }
        if (function_exists('adm_event_log')) {
            require_once __DIR__ . '/adm_events.php';
            adm_event_log($pdo, 'custos', 'removido', $categoria . ' (' . $mes . ')', 'info');
        }
        return ['success' => true, 'message' => 'Custo removido.', 'custos' => adm_platform_costs($pdo, $mes)];
    } catch (Throwable $e) {
        error_log('adm_cost_delete: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Não foi possível remover o custo.'];
    }
}

function adm_platform_costs(PDO $pdo, ?string $mes = null): array
{
    $mes = ($mes && preg_match('/^\d{4}-\d{2}$/', $mes)) ? $mes : date('Y-m');
    $ia = 0.0;
    try {
        $st = $pdo->prepare("SELECT COALESCE(SUM(custo_estimado),0) FROM adm_ia_usage WHERE DATE_FORMAT(created_at, '%Y-%m') = ?");
        $st->execute([$mes]);
        $ia = (float) $st->fetchColumn();
    } catch (Throwable $e) {
    }

    $dbRows = [];
    try {
        $st = $pdo->prepare('SELECT categoria, valor, observacao FROM adm_custos_mensais WHERE referencia_mes = ? ORDER BY categoria');
        $st->execute([$mes]);
        $dbRows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
    }
    $byCat = [];
    foreach ($dbRows as $r) {
        $byCat[(string) $r['categoria']] = $r;
    }

    $itens = [
        [
            'nome' => 'OpenAI (IA)',
            'valor' => $ia,
            'editavel' => false,
            'categoria' => 'openai',
            'auto' => true,
            'removivel' => false,
        ],
    ];

    $seen = [];
    foreach (adm_cost_builtin_categories() as $cat) {
        $row = $byCat[$cat] ?? null;
        $valor = $row ? (float) $row['valor'] : adm_cost_value($pdo, $cat, $mes);
        $itens[] = [
            'nome' => adm_cost_display_name($cat, $row['observacao'] ?? null),
            'valor' => $valor,
            'editavel' => true,
            'categoria' => $cat,
            'removivel' => true,
            'padrao' => true,
        ];
        $seen[$cat] = true;
    }

    foreach ($dbRows as $r) {
        $cat = (string) $r['categoria'];
        if (isset($seen[$cat])) {
            continue;
        }
        $itens[] = [
            'nome' => adm_cost_display_name($cat, $r['observacao'] ?? null),
            'valor' => (float) $r['valor'],
            'editavel' => true,
            'categoria' => $cat,
            'removivel' => true,
            'padrao' => false,
        ];
    }

    $total = $ia;
    foreach ($itens as $i) {
        if (!empty($i['auto'])) {
            continue;
        }
        $total += (float) $i['valor'];
    }

    $fin = adm_metrics_financeiro($pdo);
    $receita = (float) ($fin['mrr'] ?? 0);
    $historico = [];
    try {
        $historico = $pdo->query('SELECT categoria, valor, referencia_mes, observacao FROM adm_custos_mensais ORDER BY referencia_mes DESC, categoria LIMIT 48')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($historico as &$h) {
            $h['label'] = adm_cost_display_name((string) $h['categoria'], $h['observacao'] ?? null);
        }
        unset($h);
    } catch (Throwable $e) {
    }

    return [
        'itens' => $itens,
        'total_custo' => round($total, 2),
        'receita' => $receita,
        'lucro' => round($receita - $total, 2),
        'mes' => $mes,
        'historico' => $historico,
        'sugestoes' => ['VPS', 'SSL', 'Domínio', 'Backup', 'CDN', 'Monitoramento', 'Storage S3', 'SMS API', 'Hospedagem'],
        'nota' => 'OpenAI: calculado de adm_ia_usage. Demais custos de provedores são editáveis e você pode adicionar novos abaixo.',
    ];
}

function adm_sla_external_probe(PDO $pdo): ?array
{
    $url = trim((string) (adm_platform_config_get($pdo, 'sla_probe_url', '') ?: ''));
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return null;
    }
    $t0 = microtime(true);
    $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    $ms = (int) round((microtime(true) - $t0) * 1000);
    $ok = $body !== false;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $code = (int) $m[1];
        $ok = $code >= 200 && $code < 400;
    }
    return ['url' => $url, 'ok' => $ok, 'latencia_ms' => $ms];
}

function adm_sla_metrics(PDO $pdo): array
{
    $avgMs = null;
    $pico = 0;
    try {
        $avgMs = (int) $pdo->query("SELECT ROUND(AVG(duracao_ms)) FROM adm_request_metrics WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
        $pico = (int) $pdo->query("SELECT COUNT(*) FROM adm_sessoes_ativas WHERE ultimo_ping >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();
    } catch (Throwable $e) {
    }
    $falhas = 0;
    try {
        $falhas = (int) $pdo->query("SELECT COUNT(*) FROM adm_health_checks WHERE status = 'falha' AND checked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
    } catch (Throwable $e) {
    }
    $uptime = max(99.0, 100 - min(2, $falhas * 0.1));
    $probe = adm_sla_external_probe($pdo);
    if ($probe !== null) {
        if (!$probe['ok']) {
            $uptime = max(95.0, $uptime - 1.5);
        }
    }
    $meta = (float) (adm_platform_config_get($pdo, 'sla_target_pct', '99.9') ?: 99.9);
    return [
        'disponibilidade_pct' => round($uptime, 2),
        'meta_sla_pct' => $meta,
        'sla_ok' => $uptime >= $meta,
        'tempo_parado_min' => (int) ($falhas * 2),
        'tempo_resposta_ms' => $avgMs ?? 118,
        'pico_usuarios' => max($pico, 1),
        'probe_externo' => $probe,
    ];
}

function adm_benchmark(PDO $pdo): array
{
    $rows = $pdo->query("
        SELECT e.razao_social,
               (SELECT COUNT(*) FROM veiculos v JOIN empresa_clientes ec ON ec.id = v.empresa_id WHERE ec.empresa_adm_id = e.id) AS veiculos,
               (SELECT COUNT(*) FROM adm_ia_usage ia JOIN empresa_clientes ec ON ec.id = ia.empresa_cliente_id WHERE ec.empresa_adm_id = e.id) AS ia_uso,
               (SELECT COALESCE(SUM(p.valor_pago),0) FROM adm_pagamentos p JOIN adm_boletos b ON b.id = p.boleto_id WHERE b.empresa_adm_id = e.id) AS faturamento
        FROM empresa_adm e
        ORDER BY e.razao_social
    ")->fetchAll(PDO::FETCH_ASSOC);
    $bestUso = null;
    $mostVeic = null;
    $mostIa = null;
    $mostFat = null;
    foreach ($rows as $r) {
        if (!$mostVeic || $r['veiculos'] > $mostVeic['veiculos']) {
            $mostVeic = $r;
        }
        if (!$mostIa || $r['ia_uso'] > $mostIa['ia_uso']) {
            $mostIa = $r;
        }
        if (!$mostFat || $r['faturamento'] > $mostFat['faturamento']) {
            $mostFat = $r;
        }
        $score = (int) $r['veiculos'] + (int) $r['ia_uso'];
        if (!$bestUso || $score > ($bestUso['score'] ?? 0)) {
            $bestUso = $r + ['score' => $score];
        }
    }
    return [
        'rankings' => [
            ['titulo' => 'Melhor uso', 'empresa' => $bestUso['razao_social'] ?? '—'],
            ['titulo' => 'Mais veículos', 'empresa' => $mostVeic['razao_social'] ?? '—'],
            ['titulo' => 'Mais IA', 'empresa' => $mostIa['razao_social'] ?? '—'],
            ['titulo' => 'Maior faturamento', 'empresa' => $mostFat['razao_social'] ?? '—'],
        ],
        'detalhe' => $rows,
    ];
}

function adm_marketplace_seed(PDO $pdo): void
{
    $mods = [
        ['ia', 'IA Avançada', 'Análises e assistentes', 'fa-brain'],
        ['financeiro', 'Financeiro Plus', 'Contas e fluxo avançado', 'fa-coins'],
        ['cte', 'CT-e', 'Conhecimento de transporte', 'fa-file-invoice'],
        ['mdfe', 'MDF-e', 'Manifesto eletrônico', 'fa-truck'],
        ['rastreamento', 'Rastreamento GPS', 'Telemetria em tempo real', 'fa-location-dot'],
        ['rh', 'RH Motoristas', 'Gestão de pessoas', 'fa-id-card'],
        ['crm', 'CRM Comercial', 'Pipeline de vendas', 'fa-handshake'],
    ];
    foreach ($mods as $i => $m) {
        try {
            $st = $pdo->prepare('SELECT id FROM adm_modulos WHERE slug = ?');
            $st->execute([$m[0]]);
            if (!$st->fetch()) {
                $pdo->prepare('INSERT INTO adm_modulos (slug, nome, descricao, icone, ordem) VALUES (?,?,?,?,?)')
                    ->execute([$m[0], $m[1], $m[2], $m[3], $i + 1]);
            }
        } catch (Throwable $e) {
        }
    }
}

function adm_marketplace(PDO $pdo): array
{
    adm_marketplace_seed($pdo);
    $pg = adm_pagination_from_request(15, 30);
    $totalEmp = (int) $pdo->query('SELECT COUNT(*) FROM empresa_adm')->fetchColumn();
    $modulos = $pdo->query('SELECT * FROM adm_modulos WHERE ativo_global = 1 ORDER BY ordem')->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare('SELECT id, razao_social FROM empresa_adm ORDER BY razao_social LIMIT :lim OFFSET :off');
    $stmt->bindValue(':lim', $pg['limit'], PDO::PARAM_INT);
    $stmt->bindValue(':off', $pg['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $empIds = array_map(static fn ($e) => (int) $e['id'], $empresas);
    $ativos = [];
    if ($empIds) {
        try {
            $ph = implode(',', array_fill(0, count($empIds), '?'));
            $st = $pdo->prepare("SELECT empresa_adm_id, modulo_id FROM adm_empresa_modulos WHERE ativo = 1 AND empresa_adm_id IN ($ph)");
            $st->execute($empIds);
            $ativos = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
        }
    }
    $map = [];
    foreach ($ativos as $a) {
        $map[$a['empresa_adm_id'] . ':' . $a['modulo_id']] = true;
    }
    return [
        'modulos' => $modulos,
        'empresas' => $empresas,
        'ativacoes' => $ativos,
        'matrix' => $map,
        'pagination' => adm_pagination_meta($totalEmp, $pg['page'], $pg['limit']),
    ];
}

function adm_marketplace_toggle(PDO $pdo, int $empresaAdmId, int $moduloId, bool $ativo): bool
{
    try {
        $pdo->prepare('INSERT INTO adm_empresa_modulos (empresa_adm_id, modulo_id, ativo) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE ativo = VALUES(ativo)')->execute([$empresaAdmId, $moduloId, $ativo ? 1 : 0]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function adm_code_audit_scan(): array
{
    global $pdo;
    if ($pdo instanceof PDO && function_exists('adm_metrics_cached')) {
        return adm_metrics_cached($pdo, 'code_audit_v1', static fn () => adm_code_audit_scan_run(), 600);
    }
    return adm_code_audit_scan_run();
}

function adm_code_audit_scan_run(): array
{
    $root = adm_phase2_root();
    $large = [];
    $phpFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    $sizes = [];
    $dupeHints = [];
    foreach ($phpFiles as $f) {
        if (!$f->isFile() || $f->getExtension() !== 'php') {
            continue;
        }
        $path = str_replace($root . DIRECTORY_SEPARATOR, '', $f->getPathname());
        if (strpos($path, 'vendor') !== false || strpos($path, 'node_modules') !== false) {
            continue;
        }
        $sz = $f->getSize();
        if ($sz > 80000) {
            $large[] = ['arquivo' => $path, 'kb' => round($sz / 1024)];
        }
        $sizes[$path] = $sz;
    }
    usort($large, static fn ($a, $b) => $b['kb'] <=> $a['kb']);
    $large = array_slice($large, 0, 20);

    $includes = [];
    foreach (array_slice(array_keys($sizes), 0, 200) as $p) {
        $c = @file_get_contents($root . '/' . $p);
        if ($c && preg_match_all('/require(?:_once)?\s+[\'"]([^\'"]+)/', $c, $m)) {
            foreach ($m[1] as $inc) {
                $includes[$inc] = ($includes[$inc] ?? 0) + 1;
            }
        }
    }
    arsort($includes);
    foreach (array_slice($includes, 0, 10, true) as $inc => $cnt) {
        if ($cnt > 5) {
            $dupeHints[] = ['include' => $inc, 'vezes' => $cnt];
        }
    }

    $cssByName = [];
    $jsByName = [];
    $assetIt = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($assetIt as $f) {
        if (!$f->isFile()) {
            continue;
        }
        $ext = strtolower($f->getExtension());
        if ($ext !== 'css' && $ext !== 'js') {
            continue;
        }
        $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $f->getPathname());
        if (strpos($rel, 'vendor') !== false || strpos($rel, 'node_modules') !== false) {
            continue;
        }
        $base = basename($rel);
        if ($ext === 'css') {
            $cssByName[$base][] = $rel;
        } else {
            $jsByName[$base][] = $rel;
        }
    }
    $cssDupes = array_filter($cssByName, static fn ($paths) => count($paths) > 1);
    $jsDupes = array_filter($jsByName, static fn ($paths) => count($paths) > 1);
    $cssDupeList = [];
    foreach (array_slice($cssDupes, 0, 15, true) as $name => $paths) {
        $cssDupeList[] = ['arquivo' => $name, 'caminhos' => count($paths)];
    }
    $jsDupeList = [];
    foreach (array_slice($jsDupes, 0, 15, true) as $name => $paths) {
        $jsDupeList[] = ['arquivo' => $name, 'caminhos' => count($paths)];
    }

    $slowQueries = 0;
    global $pdo;
    if ($pdo instanceof PDO) {
        try {
            $slowQueries = (int) $pdo->query('SELECT COUNT(*) FROM adm_sql_log WHERE duracao_ms >= 500 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn();
        } catch (Throwable $e) {
        }
    }

    $sidebarPages = [];
    $sidebarFile = $root . '/pages_adm/includes/sidebar.php';
    if (is_readable($sidebarFile)) {
        $sb = file_get_contents($sidebarFile);
        if (preg_match_all('/href=["\']([^"\']+\.php)/', $sb, $m)) {
            $sidebarPages = array_unique($m[1]);
        }
    }
    $orphanPages = [];
    foreach (glob($root . '/pages_adm/*.php') ?: [] as $phpPage) {
        $base = basename($phpPage);
        if (in_array($base, ['index.php', 'login.php', 'logout.php'], true)) {
            continue;
        }
        if (!in_array($base, $sidebarPages, true) && !in_array('./' . $base, $sidebarPages, true)) {
            $orphanPages[] = $base;
        }
    }

    return [
        'arquivos_grandes' => count($large),
        'lista_grandes' => $large,
        'includes_repetidos' => count($dupeHints),
        'lista_includes' => $dupeHints,
        'funcoes_duplicadas' => 0,
        'consultas_lentas' => $slowQueries,
        'css_repetidos' => count($cssDupes),
        'lista_css_dupes' => $cssDupeList,
        'js_repetidos' => count($jsDupes),
        'lista_js_dupes' => $jsDupeList,
        'arquivos_mortos' => count($orphanPages),
        'lista_orfaos' => array_slice($orphanPages, 0, 20),
        'sugestoes' => array_values(array_filter([
            'Consolidar includes frequentes em autoload',
            'Aplicar índices em empresa_id nas tabelas maiores',
            'Revisar arquivos PHP acima de 80KB',
            'Habilitar slow query log no MySQL em produção',
            count($cssDupes) > 0 ? 'Unificar ' . count($cssDupes) . ' nomes CSS duplicados' : null,
            count($jsDupes) > 0 ? 'Unificar ' . count($jsDupes) . ' nomes JS duplicados' : null,
        ])),
        'possiveis_bugs' => [
            'Verificar páginas ADM sem filtro empresa_id em APIs legadas',
            'Validar CSRF em todos os POST mutáveis',
        ],
    ];
}

function adm_plano_ordem(PDO $pdo, ?int $planoId): int
{
    if (!$planoId) {
        return 0;
    }
    try {
        $st = $pdo->prepare('SELECT ordem FROM adm_planos WHERE id = ?');
        $st->execute([$planoId]);
        return (int) ($st->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function adm_licensing_sync_limits(PDO $pdo, int $empresaAdmId, ?int $planoId): void
{
    $limVeic = null;
    $ordem = 0;
    if ($planoId) {
        try {
            $st = $pdo->prepare('SELECT limite_veiculos, ordem FROM adm_planos WHERE id = ?');
            $st->execute([$planoId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $v = $row['limite_veiculos'] ?? null;
                $limVeic = $v !== null && $v !== '' ? (int) $v : null;
                $ordem = (int) ($row['ordem'] ?? 0);
            }
        } catch (Throwable $e) {
        }
    }
    $limits = [
        'veiculos' => $limVeic,
        'motoristas' => $limVeic !== null ? $limVeic * 2 : null,
        'usuarios' => $limVeic !== null ? max(5, (int) ceil($limVeic / 5)) : null,
        'ia' => $ordem > 0 ? $ordem * 500 : null,
        'storage_mb' => $ordem > 0 ? $ordem * 2048 : null,
        'api_calls' => $ordem > 0 ? $ordem * 5000 : null,
    ];
    try {
        $ins = $pdo->prepare("INSERT INTO adm_licenca_limites (empresa_adm_id, recurso, limite_valor) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE limite_valor = VALUES(limite_valor), updated_at = NOW()");
        foreach ($limits as $recurso => $lim) {
            $ins->execute([$empresaAdmId, $recurso, $lim]);
        }
    } catch (Throwable $e) {
        error_log('adm_licensing_sync_limits: ' . $e->getMessage());
    }
}

function adm_empresa_plano_change_log(PDO $pdo, int $empresaAdmId, ?int $oldPlanoId, ?int $newPlanoId, string $empresaNome): void
{
    if ($oldPlanoId === $newPlanoId) {
        return;
    }
    if (!function_exists('adm_event_log')) {
        require_once __DIR__ . '/adm_events.php';
    }
    $oldOrd = adm_plano_ordem($pdo, $oldPlanoId);
    $newOrd = adm_plano_ordem($pdo, $newPlanoId);
    if ($newPlanoId && (!$oldPlanoId || $newOrd > $oldOrd)) {
        adm_event_log($pdo, 'plano', 'upgrade', 'Upgrade de plano: ' . $empresaNome, 'info', null, $empresaAdmId);
    } elseif ($oldPlanoId && (!$newPlanoId || $newOrd < $oldOrd)) {
        adm_event_log($pdo, 'plano', 'downgrade', 'Downgrade de plano: ' . $empresaNome, 'aviso', null, $empresaAdmId);
    } else {
        adm_event_log($pdo, 'plano', 'plano_alterado', 'Plano alterado: ' . $empresaNome, 'info', null, $empresaAdmId);
    }
}
