<?php
declare(strict_types=1);

/**
 * Manutenção do Control Center: retenção de logs e paginação.
 */
function adm_pagination_from_request(int $defaultLimit = 20, int $maxLimit = 100): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min($maxLimit, max(5, (int) ($_GET['limit'] ?? $defaultLimit)));
    return [
        'page' => $page,
        'limit' => $limit,
        'offset' => ($page - 1) * $limit,
    ];
}

function adm_pagination_meta(int $total, int $page, int $limit): array
{
    $pages = max(1, (int) ceil($total / max(1, $limit)));
    return [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'pages' => $pages,
        'has_prev' => $page > 1,
        'has_next' => $page < $pages,
    ];
}

function adm_maintenance_purge(PDO $pdo): void
{
    if (!function_exists('sf_cache_remember')) {
        $path = dirname(__DIR__, 2) . '/includes/sf_cache.php';
        if (is_readable($path)) {
            require_once $path;
        }
    }

    $run = static function () use ($pdo): void {
        $specs = [
            ['adm_sql_log', 'created_at', 30],
            ['adm_integration_checks', 'checked_at', 30],
            ['adm_health_checks', 'checked_at', 30],
            ['adm_empresa_scores', 'calculado_em', 90],
            ['adm_request_metrics', 'created_at', 60],
        ];
        foreach ($specs as [$table, $col, $days]) {
            try {
                $pdo->exec("DELETE FROM {$table} WHERE {$col} < DATE_SUB(NOW(), INTERVAL " . (int) $days . ' DAY)');
            } catch (Throwable $e) {
                error_log('adm_maintenance_purge ' . $table . ': ' . $e->getMessage());
            }
        }
    };

    if (function_exists('sf_cache_remember')) {
        sf_cache_remember('adm:maintenance:purge', 86400, static function () use ($run) {
            $run();
            return 1;
        });
    } else {
        $run();
    }
}

function adm_storage_breakdown_for_dir(string $dir): array
{
    $out = ['fotos' => 0, 'documentos' => 0, 'os' => 0, 'comprovantes' => 0, 'outros' => 0];
    if (!is_dir($dir)) {
        return $out;
    }
    $img = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
    $doc = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'];
    $os = ['os', 'ordem', 'manutencao', 'maintenance'];
    $comp = ['comprovante', 'recibo', 'nota', 'nf', 'boleto'];

    try {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile()) {
                continue;
            }
            $sz = $f->getSize();
            $path = strtolower($f->getPathname());
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $base = strtolower(basename($path));

            if (in_array($ext, $img, true)) {
                $out['fotos'] += $sz;
            } elseif (in_array($ext, $doc, true)) {
                $out['documentos'] += $sz;
            } elseif (preg_match('/(' . implode('|', $os) . ')/', $path)) {
                $out['os'] += $sz;
            } elseif (preg_match('/(' . implode('|', $comp) . ')/', $path)) {
                $out['comprovantes'] += $sz;
            } else {
                $out['outros'] += $sz;
            }
        }
    } catch (Throwable $e) {
    }

    foreach ($out as $k => $v) {
        $out[$k] = round($v / 1024 / 1024, 2);
    }
    return $out;
}

function adm_git_diff_stats(): array
{
    $root = dirname(__DIR__, 2);
    $files = 0;
    $insertions = 0;
    $deletions = 0;
    if (!is_dir($root . '/.git')) {
        return compact('files', 'insertions', 'deletions');
    }
    $raw = @shell_exec('git -C ' . escapeshellarg($root) . ' diff --numstat HEAD~1 HEAD 2>nul');
    if (!$raw) {
        $raw = @shell_exec('git -C ' . escapeshellarg($root) . ' show --numstat --format= HEAD 2>nul');
    }
    if ($raw) {
        foreach (explode("\n", trim($raw)) as $line) {
            if (!preg_match('/^(\d+|-)\s+(\d+|-)\s+/', $line, $m)) {
                continue;
            }
            $files++;
            if (is_numeric($m[1])) {
                $insertions += (int) $m[1];
            }
            if (is_numeric($m[2])) {
                $deletions += (int) $m[2];
            }
        }
    }
    return compact('files', 'insertions', 'deletions');
}

function adm_slow_query_log_entries(PDO $pdo, int $limit = 20): array
{
    $entries = [];
    try {
        $logFile = $pdo->query("SHOW VARIABLES LIKE 'slow_query_log_file'")->fetch(PDO::FETCH_ASSOC);
        $path = $logFile['Value'] ?? '';
        if ($path && is_readable($path) && filesize($path) > 0) {
            $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines)) {
                $lines = array_slice($lines, -200);
                $current = null;
                foreach ($lines as $line) {
                    if (preg_match('/Query_time:\s+([\d.]+)/', $line, $m)) {
                        $current = ['tempo_ms' => (int) round((float) $m[1] * 1000), 'query' => ''];
                    } elseif ($current && preg_match('/^\s*(SELECT|INSERT|UPDATE|DELETE)/i', $line)) {
                        $current['query'] = substr(trim($line), 0, 200);
                        $current['tipo'] = strtoupper(substr(trim($line), 0, 6));
                        $entries[] = $current;
                        $current = null;
                        if (count($entries) >= $limit) {
                            break;
                        }
                    }
                }
            }
        }
    } catch (Throwable $e) {
    }

    if (empty($entries)) {
        try {
            $rows = $pdo->query("
                SELECT DIGEST_TEXT AS query, COUNT_STAR AS execs, ROUND(AVG_TIMER_WAIT/1000000000) AS avg_ms
                FROM performance_schema.events_statements_summary_by_digest
                WHERE SCHEMA_NAME = DATABASE()
                ORDER BY AVG_TIMER_WAIT DESC LIMIT " . (int) $limit
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $entries[] = [
                    'tipo' => 'SQL',
                    'tempo_ms' => (int) ($r['avg_ms'] ?? 0),
                    'query' => substr((string) ($r['query'] ?? ''), 0, 200),
                    'origem' => 'performance_schema',
                ];
            }
        } catch (Throwable $e) {
        }
    }
    return $entries;
}
