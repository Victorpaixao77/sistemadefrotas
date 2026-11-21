<?php
declare(strict_types=1);

function adm_health_redis_ping(): ?bool
{
    $host = getenv('SF_REDIS_HOST') ?: '';
    if ($host === '') {
        return null;
    }
    try {
        if (!class_exists('Redis')) {
            return false;
        }
        $r = new Redis();
        $port = (int) (getenv('SF_REDIS_PORT') ?: 6379);
        $ok = @$r->connect($host, $port, 1.5);
        if ($ok) {
            $r->ping();
            return true;
        }
        return false;
    } catch (Throwable $e) {
        return false;
    }
}

function adm_health_snapshot(PDO $pdo): array
{
    $services = [];
    $t0 = microtime(true);
    try {
        $pdo->query('SELECT 1');
        $services[] = adm_health_row('mysql', 'ok', (int) ((microtime(true) - $t0) * 1000), 'Conexão ativa');
    } catch (Throwable $e) {
        $services[] = adm_health_row('mysql', 'falha', null, 'Falha na conexão');
    }

    $services[] = adm_health_row('apache', 'ok', null, 'PHP ' . PHP_VERSION);

    $redis = adm_health_redis_ping();
    if ($redis === null) {
        $services[] = adm_health_row('redis', 'desconhecido', null, 'Não configurado');
    } else {
        $services[] = adm_health_row('redis', $redis ? 'ok' : 'falha', null, $redis ? 'PING OK' : 'Sem conexão');
    }

    $smtp = adm_health_smtp();
    $services[] = adm_health_row('smtp', $smtp['status'], $smtp['ms'], $smtp['detalhe']);

    $openai = adm_health_openai();
    $services[] = adm_health_row('openai', $openai['status'], $openai['ms'], $openai['detalhe']);

    $maps = trim((string) (getenv('SF_GOOGLE_MAPS_KEY') ?: '')) !== '';
    $services[] = adm_health_row('google_maps', $maps ? 'ok' : 'degradado', null, $maps ? 'Chave configurada' : 'Sem chave env');

    $whatsapp = trim((string) (getenv('SF_WHATSAPP_TOKEN') ?: '')) !== '';
    $services[] = adm_health_row('whatsapp', $whatsapp ? 'ok' : 'desconhecido', null, $whatsapp ? 'Token env' : 'Não configurado');

    $cron = adm_health_cron();
    $services[] = adm_health_row('cron', $cron['status'], null, $cron['detalhe']);

    $services[] = adm_health_row('jobs', 'ok', null, 'Workers GPS opcionais');

    $backup = adm_health_backup();
    $services[] = adm_health_row('backup', $backup['status'], null, $backup['detalhe']);

    $ssl = adm_health_ssl();
    $services[] = adm_health_row('ssl', $ssl['status'], null, $ssl['detalhe']);

    $disk = adm_health_disk();
    $services[] = adm_health_row('disco', $disk['status'], null, $disk['detalhe']);

    $services[] = adm_health_row('cache', function_exists('apcu_enabled') && apcu_enabled() ? 'ok' : 'degradado', null,
        function_exists('apcu_enabled') && apcu_enabled() ? 'APCu ativo' : 'APCu indisponível');

    foreach ($services as $s) {
        try {
            $pdo->prepare('INSERT INTO adm_health_checks (servico, status, latencia_ms, detalhe) VALUES (?,?,?,?)')
                ->execute([$s['servico'], $s['status'], $s['latencia_ms'], substr((string) $s['detalhe'], 0, 500)]);
        } catch (Throwable $e) {
        }
    }

    adm_health_evaluate_alerts($pdo, $services, $disk);

    return ['services' => $services, 'checked_at' => date('c')];
}

function adm_health_row(string $servico, string $status, ?int $ms, ?string $detalhe): array
{
    return ['servico' => $servico, 'status' => $status, 'latencia_ms' => $ms, 'detalhe' => $detalhe];
}

function adm_health_smtp(): array
{
    $host = getenv('SF_SMTP_HOST') ?: '';
    if ($host === '') {
        return ['status' => 'desconhecido', 'ms' => null, 'detalhe' => 'SMTP não configurado (env)'];
    }
    return ['status' => 'ok', 'ms' => null, 'detalhe' => 'Host: ' . $host];
}

function adm_health_openai(): array
{
    $key = getenv('SF_OPENAI_API_KEY') ?: '';
    if ($key === '' && function_exists('sf_openai_api_key')) {
        require_once dirname(__DIR__, 2) . '/includes/sf_openai_resolve.php';
        $key = sf_openai_api_key();
    }
    if ($key === '') {
        return ['status' => 'degradado', 'ms' => null, 'detalhe' => 'Sem chave OpenAI'];
    }
    return ['status' => 'ok', 'ms' => null, 'detalhe' => 'Chave configurada'];
}

function adm_health_cron(): array
{
    $cronDir = dirname(__DIR__, 2) . '/cron';
    $files = glob($cronDir . '/*.php') ?: [];
    return ['status' => count($files) > 0 ? 'ok' : 'degradado', 'detalhe' => count($files) . ' scripts em /cron'];
}

function adm_health_backup(): array
{
    $logDir = dirname(__DIR__, 2) . '/logs';
    $sqlDir = dirname(__DIR__, 2) . '/sql';
    $recent = false;
    foreach (array_merge(glob($logDir . '/*backup*') ?: [], glob($sqlDir . '/*backup*') ?: []) as $f) {
        if (filemtime($f) > time() - 86400 * 7) {
            $recent = true;
            break;
        }
    }
    return [
        'status' => $recent ? 'ok' : 'aviso',
        'detalhe' => $recent ? 'Backup recente detectado' : 'Nenhum backup recente nos últimos 7 dias',
    ];
}

function adm_health_ssl(): array
{
    if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
        return ['status' => 'degradado', 'detalhe' => 'Ambiente local / sem HTTPS'];
    }
    return ['status' => 'ok', 'detalhe' => 'HTTPS ativo'];
}

function adm_health_disk(): array
{
    $root = dirname(__DIR__, 2);
    $free = @disk_free_space($root);
    $total = @disk_total_space($root);
    if ($free === false || !$total) {
        return ['status' => 'desconhecido', 'detalhe' => 'Não foi possível ler disco'];
    }
    $pct = round((1 - $free / $total) * 100, 1);
    $status = $pct >= 90 ? 'falha' : ($pct >= 80 ? 'degradado' : 'ok');
    return ['status' => $status, 'detalhe' => "Uso {$pct}%", 'pct' => $pct];
}

function adm_health_evaluate_alerts(PDO $pdo, array $services, array $disk): void
{
    if (($disk['pct'] ?? 0) >= 80) {
        adm_alert_upsert($pdo, 'disco_alto', 'critico', 'Disco acima de 80%', (string) ($disk['detalhe'] ?? ''));
    }
    foreach ($services as $s) {
        if ($s['status'] === 'falha') {
            adm_alert_upsert($pdo, 'servico_' . $s['servico'], 'emergencia', 'Serviço indisponível: ' . $s['servico'], (string) $s['detalhe']);
        }
    }
}

function adm_alert_upsert(PDO $pdo, string $regra, string $prioridade, string $titulo, string $mensagem): void
{
    try {
        $st = $pdo->prepare("SELECT id FROM adm_alertas WHERE regra = ? AND status = 'aberto' LIMIT 1");
        $st->execute([$regra]);
        if ($st->fetch()) {
            return;
        }
        $pdo->prepare('INSERT INTO adm_alertas (regra, prioridade, titulo, mensagem) VALUES (?,?,?,?)')
            ->execute([$regra, $prioridade, $titulo, $mensagem]);
    } catch (Throwable $e) {
        error_log('adm_alert_upsert: ' . $e->getMessage());
    }
}

function adm_system_info(): array
{
    $root = dirname(__DIR__, 2);
    $gitCommit = null;
    if (is_dir($root . '/.git')) {
        $head = @file_get_contents($root . '/.git/HEAD');
        if ($head && preg_match('/ref: (.+)/', $head, $m)) {
            $ref = trim($m[1]);
            $hash = @file_get_contents($root . '/.git/' . $ref);
            $gitCommit = $hash ? substr(trim($hash), 0, 8) : null;
        }
    }

    return [
        'php' => PHP_VERSION,
        'apache' => $_SERVER['SERVER_SOFTWARE'] ?? 'Apache',
        'mysql' => null,
        'redis' => adm_health_redis_ping(),
        'composer' => is_file($root . '/vendor/autoload.php'),
        'git_commit' => $gitCommit,
        'opcache' => function_exists('opcache_get_status') && @opcache_get_status(false),
        'apcu' => function_exists('apcu_enabled') && apcu_enabled(),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'document_root' => $root,
        'deploy' => [
            'ultimo_commit' => $gitCommit,
            'ultimo_backup' => null,
        ],
    ];
}
