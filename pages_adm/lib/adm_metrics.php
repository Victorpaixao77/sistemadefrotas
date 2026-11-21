<?php
declare(strict_types=1);

require_once __DIR__ . '/adm_schema.php';

function adm_metrics_cached(PDO $pdo, string $key, callable $fn, int $ttl = 45)
{
    if (!function_exists('sf_cache_remember')) {
        $path = dirname(__DIR__, 2) . '/includes/sf_cache.php';
        if (is_readable($path)) {
            require_once $path;
        }
    }
    $cacheKey = 'adm:metrics:' . $key;
    if (function_exists('sf_cache_remember')) {
        return sf_cache_remember($cacheKey, $ttl, static fn () => $fn());
    }
    return $fn();
}

function adm_metrics_dashboard(PDO $pdo): array
{
    return adm_metrics_cached($pdo, 'dashboard_v1', static function () use ($pdo) {
        $clientes = adm_metrics_clientes($pdo);
        $financeiro = adm_metrics_financeiro($pdo);
        $uso = adm_metrics_uso($pdo);
        $infra = adm_metrics_infra($pdo);
        $receita_meses = adm_metrics_receita_meses($pdo, 12);

        return array_merge($clientes, $financeiro, $uso, $infra, [
            'receita_meses' => $receita_meses,
            'updated_at' => date('c'),
        ]);
    }, 30);
}

function adm_metrics_clientes(PDO $pdo): array
{
    $total = (int) $pdo->query('SELECT COUNT(*) FROM empresa_adm')->fetchColumn();

    $byStatus = ['ativa' => 0, 'trial' => 0, 'bloqueada' => 0, 'cancelada' => 0, 'suspensa' => 0];
    try {
        $rows = $pdo->query("SELECT status_saas, COUNT(*) AS c FROM empresa_adm GROUP BY status_saas")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $s = (string) ($r['status_saas'] ?? 'ativa');
            if (isset($byStatus[$s])) {
                $byStatus[$s] = (int) $r['c'];
            }
        }
    } catch (Throwable $e) {
        $byStatus['ativa'] = $total;
    }

    return [
        'empresas_total' => $total,
        'empresas_ativas' => $byStatus['ativa'],
        'empresas_trial' => $byStatus['trial'],
        'empresas_bloqueadas' => $byStatus['bloqueada'],
        'empresas_canceladas' => $byStatus['cancelada'],
        'empresas_suspensas' => $byStatus['suspensa'],
    ];
}

function adm_metrics_financeiro(PDO $pdo): array
{
    $mrr = 0.0;
    $arr = 0.0;
    $receita_hoje = 0.0;
    $receita_prevista = 0.0;
    $contas_receber = 0.0;
    $inadimplentes = 0;

    try {
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(valor_pago), 0) AS total
            FROM adm_pagamentos
            WHERE MONTH(data_pagamento) = MONTH(CURRENT_DATE())
              AND YEAR(data_pagamento) = YEAR(CURRENT_DATE())
        ");
        $mrr = (float) $stmt->fetchColumn();
        $arr = $mrr * 12;

        $stmt = $pdo->query("
            SELECT COALESCE(SUM(valor_pago), 0) FROM adm_pagamentos
            WHERE DATE(data_pagamento) = CURRENT_DATE()
        ");
        $receita_hoje = (float) $stmt->fetchColumn();

        $stmt = $pdo->query("
            SELECT COALESCE(SUM(valor), 0) FROM adm_boletos
            WHERE status IN ('pendente','vencido')
        ");
        $contas_receber = (float) $stmt->fetchColumn();

        $stmt = $pdo->query("
            SELECT COALESCE(SUM(valor), 0) FROM adm_boletos WHERE status = 'vencido'
        ");
        $vencido = (float) $stmt->fetchColumn();

        $inadimplentes = (int) $pdo->query("
            SELECT COUNT(DISTINCT empresa_adm_id) FROM adm_boletos WHERE status = 'vencido'
        ")->fetchColumn();

        $stmt = $pdo->query("
            SELECT COALESCE(SUM(valor), 0) FROM adm_boletos
            WHERE status = 'pendente' AND MONTH(data_vencimento) = MONTH(CURRENT_DATE())
        ");
        $receita_prevista = (float) $stmt->fetchColumn() + $mrr;
    } catch (Throwable $e) {
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(
                (SELECT COUNT(*) FROM veiculos v
                 JOIN empresa_clientes ec ON ec.id = v.empresa_id
                 WHERE ec.empresa_adm_id = e.id AND v.status_id IN (1,2)) * e.valor_por_veiculo
            ), 0) AS mrr_est
            FROM empresa_adm e
        ");
        $mrr = (float) $stmt->fetchColumn();
        $arr = $mrr * 12;
        $receita_prevista = $mrr;
    }

    return [
        'mrr' => round($mrr, 2),
        'arr' => round($arr, 2),
        'receita_hoje' => round($receita_hoje, 2),
        'receita_prevista' => round($receita_prevista, 2),
        'contas_receber' => round($contas_receber, 2),
        'clientes_inadimplentes' => $inadimplentes,
    ];
}

function adm_metrics_uso(PDO $pdo): array
{
    $logins_hoje = 0;
    $novos_cadastros = 0;
    $usuarios_online = 0;
    $empresas_online = 0;
    $sessoes_ativas = 0;

    try {
        $logins_hoje = (int) $pdo->query("
            SELECT COUNT(*) FROM log_acessos
            WHERE tipo_acesso = 'login' AND status = 'sucesso' AND DATE(data_acesso) = CURRENT_DATE()
        ")->fetchColumn();
    } catch (Throwable $e) {
    }

    try {
        $novos_cadastros = (int) $pdo->query("
            SELECT COUNT(*) FROM empresa_adm WHERE DATE(data_cadastro) = CURRENT_DATE()
        ")->fetchColumn();
    } catch (Throwable $e) {
    }

    try {
        $cutoff = date('Y-m-d H:i:s', time() - 120);
        $st = $pdo->prepare('SELECT COUNT(*) FROM adm_sessoes_ativas WHERE ultimo_ping >= ?');
        $st->execute([$cutoff]);
        $sessoes_ativas = (int) $st->fetchColumn();

        $st = $pdo->prepare('SELECT COUNT(DISTINCT usuario_id) FROM adm_sessoes_ativas WHERE ultimo_ping >= ? AND usuario_id IS NOT NULL');
        $st->execute([$cutoff]);
        $usuarios_online = (int) $st->fetchColumn();

        $st = $pdo->prepare('SELECT COUNT(DISTINCT empresa_cliente_id) FROM adm_sessoes_ativas WHERE ultimo_ping >= ? AND empresa_cliente_id IS NOT NULL');
        $st->execute([$cutoff]);
        $empresas_online = (int) $st->fetchColumn();
    } catch (Throwable $e) {
    }

    $total_usuarios = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'ativo'")->fetchColumn();
    $total_motoristas = (int) $pdo->query('SELECT COUNT(*) FROM motoristas')->fetchColumn();
    $total_veiculos = (int) $pdo->query('SELECT COUNT(*) FROM veiculos')->fetchColumn();

    return [
        'empresas_online' => $empresas_online,
        'usuarios_online' => $usuarios_online,
        'sessoes_ativas' => $sessoes_ativas,
        'logins_hoje' => $logins_hoje,
        'novos_cadastros' => $novos_cadastros,
        'total_usuarios' => $total_usuarios,
        'total_motoristas' => $total_motoristas,
        'total_veiculos' => $total_veiculos,
    ];
}

function adm_metrics_infra(PDO $pdo): array
{
    require_once __DIR__ . '/adm_health.php';
    $health = adm_health_snapshot($pdo);

    $cpu = null;
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        $cpu = $load ? round($load[0] * 100 / max(1, (int) shell_exec('echo 1') ?: 1), 1) : null;
    }

    $ram_pct = null;
    if (function_exists('memory_get_usage')) {
        $limit = ini_get('memory_limit');
        $bytes = adm_parse_bytes((string) $limit);
        if ($bytes > 0) {
            $ram_pct = round(memory_get_usage(true) / $bytes * 100, 1);
        }
    }

    $disk = null;
    $root = dirname(__DIR__, 2);
    $free = @disk_free_space($root);
    $total = @disk_total_space($root);
    if ($free !== false && $total && $total > 0) {
        $disk = round((1 - $free / $total) * 100, 1);
    }

    $cache_hit = null;
    if (function_exists('apcu_cache_info')) {
        $info = @apcu_cache_info(true);
        if (is_array($info) && isset($info['num_hits'], $info['num_misses'])) {
            $h = (int) $info['num_hits'];
            $m = (int) $info['num_misses'];
            $cache_hit = ($h + $m) > 0 ? round($h / ($h + $m) * 100, 1) : null;
        }
    }

    $avg_api = null;
    try {
        $avg_api = $pdo->query("
            SELECT ROUND(AVG(duracao_ms)) FROM adm_request_metrics
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ")->fetchColumn();
        $avg_api = $avg_api !== null ? (int) $avg_api : null;
    } catch (Throwable $e) {
    }

    $mysql_ok = false;
    foreach ($health['services'] as $svc) {
        if ($svc['servico'] === 'mysql') {
            $mysql_ok = $svc['status'] === 'ok';
        }
    }

    return [
        'cpu_load' => $cpu,
        'ram_pct' => $ram_pct,
        'disco_pct' => $disk,
        'mysql_ok' => $mysql_ok,
        'cache_hit_pct' => $cache_hit,
        'api_avg_ms' => $avg_api,
        'redis_ok' => adm_health_redis_ping(),
    ];
}

function adm_parse_bytes(string $val): int
{
    $val = trim($val);
    $n = (int) $val;
    $u = strtolower(substr($val, -1));
    return match ($u) {
        'g' => $n * 1024 ** 3,
        'm' => $n * 1024 ** 2,
        'k' => $n * 1024,
        default => $n,
    };
}

function adm_metrics_receita_meses(PDO $pdo, int $meses = 12): array
{
    $out = [];
    try {
        $stmt = $pdo->query("
            SELECT DATE_FORMAT(data_pagamento, '%Y-%m') AS mes,
                   COALESCE(SUM(valor_pago), 0) AS total
            FROM adm_pagamentos
            WHERE data_pagamento >= DATE_SUB(CURRENT_DATE(), INTERVAL {$meses} MONTH)
            GROUP BY mes ORDER BY mes ASC
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[] = ['mes' => $row['mes'], 'total' => round((float) $row['total'], 2)];
        }
    } catch (Throwable $e) {
        for ($i = $meses - 1; $i >= 0; $i--) {
            $m = date('Y-m', strtotime("-{$i} months"));
            $out[] = ['mes' => $m, 'total' => 0];
        }
    }
    return $out;
}

function adm_metrics_financeiro_saas(PDO $pdo): array
{
    return adm_metrics_cached($pdo, 'finance_saas_v1', static function () use ($pdo) {
        $base = adm_metrics_financeiro($pdo);
        $clientes = adm_metrics_clientes($pdo);

        $churn = 0.0;
        $canceladas_mes = 0;
        try {
            $canceladas_mes = (int) $pdo->query("
                SELECT COUNT(*) FROM empresa_adm
                WHERE status_saas = 'cancelada' AND cancelada_em >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')
            ")->fetchColumn();
            $ativas_inicio = max(1, $clientes['empresas_ativas'] + $canceladas_mes);
            $churn = round($canceladas_mes / $ativas_inicio * 100, 2);
        } catch (Throwable $e) {
        }

        $ticket = 0.0;
        if ($clientes['empresas_ativas'] > 0) {
            $ticket = round($base['mrr'] / max(1, $clientes['empresas_ativas']), 2);
        }

        $ltv = round($ticket * 24, 2);

        $por_plano = [];
        try {
            $stmt = $pdo->query("
                SELECT COALESCE(p.nome, 'Sem plano') AS plano, COUNT(*) AS qtd,
                       COALESCE(SUM(
                           (SELECT COUNT(*) FROM veiculos v
                            JOIN empresa_clientes ec ON ec.id = v.empresa_id
                            WHERE ec.empresa_adm_id = e.id AND v.status_id IN (1,2)) * e.valor_por_veiculo
                       ), 0) AS receita
                FROM empresa_adm e
                LEFT JOIN adm_planos p ON p.id = e.plano_id
                GROUP BY p.id, p.nome
            ");
            $por_plano = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
        }

        $receita_dia = [];
        try {
            $stmt = $pdo->query("
                SELECT DATE(data_pagamento) AS dia, SUM(valor_pago) AS total
                FROM adm_pagamentos
                WHERE data_pagamento >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
                GROUP BY dia ORDER BY dia
            ");
            $receita_dia = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
        }

        return array_merge($base, [
            'churn_pct' => $churn,
            'ltv' => $ltv,
            'ticket_medio' => $ticket,
            'receita_por_plano' => $por_plano,
            'receita_diaria' => $receita_dia,
            'receita_perdida' => round((float) ($pdo->query("
                SELECT COALESCE(SUM(valor),0) FROM adm_boletos WHERE status = 'cancelado'
            ")->fetchColumn() ?: 0), 2),
        ]);
    }, 60);
}

function adm_metrics_licencas(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT e.id, e.razao_social, e.status_saas, e.trial_ate,
               p.nome AS plano_nome, p.limite_veiculos,
               (SELECT COUNT(*) FROM veiculos v
                JOIN empresa_clientes ec ON ec.id = v.empresa_id
                WHERE ec.empresa_adm_id = e.id AND v.status_id IN (1,2)) AS veiculos_uso,
               e.valor_por_veiculo
        FROM empresa_adm e
        LEFT JOIN adm_planos p ON p.id = e.plano_id
        ORDER BY e.razao_social
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $lim = $r['limite_veiculos'] !== null ? (int) $r['limite_veiculos'] : null;
        $uso = (int) $r['veiculos_uso'];
        $r['consumo_pct'] = $lim ? min(100, round($uso / $lim * 100, 1)) : null;
        $r['upgrade_sugerido'] = $lim && $uso >= $lim * 0.9;
    }
    unset($r);
    return $rows;
}

function adm_metrics_performance(PDO $pdo): array
{
    $modulos = ['dashboard', 'veiculos', 'motoristas', 'rotas', 'financeiro', 'manutencoes', 'pneus', 'ia', 'relatorios', 'api', 'adm', 'sistema'];
    $out = [];
    foreach ($modulos as $m) {
        $st = $pdo->prepare("
            SELECT COUNT(*) AS qtd, ROUND(AVG(duracao_ms)) AS avg_ms, MAX(duracao_ms) AS max_ms
            FROM adm_request_metrics
            WHERE modulo = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $st->execute([$m]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: ['qtd' => 0, 'avg_ms' => null, 'max_ms' => null];
        $out[] = [
            'modulo' => $m,
            'queries' => (int) $row['qtd'],
            'avg_ms' => $row['avg_ms'] !== null ? (int) $row['avg_ms'] : null,
            'max_ms' => $row['max_ms'] !== null ? (int) $row['max_ms'] : null,
        ];
    }

    $mysqlMs = null;
    try {
        $t0 = microtime(true);
        $pdo->query('SELECT 1');
        $mysqlMs = (int) round((microtime(true) - $t0) * 1000);
        $out[] = ['modulo' => 'banco', 'queries' => 1, 'avg_ms' => $mysqlMs, 'max_ms' => $mysqlMs];
    } catch (Throwable $e) {
        $out[] = ['modulo' => 'banco', 'queries' => 0, 'avg_ms' => null, 'max_ms' => null];
    }

    $slow = [];
    try {
        $slow = $pdo->query("
            SELECT rota, modulo, duracao_ms, created_at FROM adm_request_metrics
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY duracao_ms DESC LIMIT 25
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
    }

    $total24 = 0;
    try {
        $total24 = (int) $pdo->query("
            SELECT COUNT(*) FROM adm_request_metrics WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ")->fetchColumn();
    } catch (Throwable $e) {
    }

    $avgGlobal = null;
    try {
        $avgGlobal = $pdo->query("
            SELECT ROUND(AVG(duracao_ms)) FROM adm_request_metrics
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ")->fetchColumn();
        $avgGlobal = $avgGlobal !== null ? (int) $avgGlobal : null;
    } catch (Throwable $e) {
    }

    $cache_hit = null;
    $cache_miss = null;
    if (function_exists('apcu_cache_info')) {
        $info = @apcu_cache_info(true);
        if (is_array($info)) {
            $cache_hit = (int) ($info['num_hits'] ?? 0);
            $cache_miss = (int) ($info['num_misses'] ?? 0);
        }
    }

    return [
        'modulos' => $out,
        'slow_queries' => $slow,
        'cache_hit' => $cache_hit,
        'cache_miss' => $cache_miss,
        'total_queries_24h' => $total24,
        'avg_global_ms' => $avgGlobal,
        'instrumented' => $total24 > 0,
    ];
}

function adm_metrics_ia(PDO $pdo): array
{
    try {
        $totais = $pdo->query("
            SELECT COUNT(*) AS consultas,
                   COALESCE(SUM(tokens_in),0) AS tokens_in,
                   COALESCE(SUM(tokens_out),0) AS tokens_out,
                   ROUND(AVG(duracao_ms)) AS avg_ms,
                   COALESCE(SUM(custo_estimado),0) AS custo,
                   SUM(CASE WHEN sucesso = 0 THEN 1 ELSE 0 END) AS erros
            FROM adm_ia_usage
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ")->fetch(PDO::FETCH_ASSOC);

        $modelos = $pdo->query("
            SELECT modelo, COUNT(*) AS qtd FROM adm_ia_usage
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY modelo ORDER BY qtd DESC LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);

        return ['totais' => $totais, 'modelos' => $modelos];
    } catch (Throwable $e) {
        return ['totais' => [], 'modelos' => []];
    }
}
