<?php
declare(strict_types=1);

/**
 * Métricas e observabilidade do banco MySQL (Control Center).
 */
function adm_db_overview(PDO $pdo): array
{
    require_once __DIR__ . '/adm_maintenance.php';
    $pg = adm_pagination_from_request(15, 50);
    $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();

    $tables = [];
    $totalBytes = 0;
    try {
        $stmt = $pdo->prepare("
            SELECT TABLE_NAME AS nome,
                   TABLE_ROWS AS linhas_est,
                   ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS mb,
                   ROUND(DATA_FREE / 1024 / 1024, 2) AS fragmentacao_mb,
                   AUTO_INCREMENT AS auto_inc
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = ?
            ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
            LIMIT " . (int) $pg['limit'] . ' OFFSET ' . (int) $pg['offset'] . '
        ');
        $stmt->execute([$dbName]);
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $sumStmt = $pdo->prepare("
            SELECT ROUND(SUM(DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2)
            FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?
        ");
        $sumStmt->execute([$dbName]);
        $totalBytes = (float) $sumStmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('adm_db_overview tables: ' . $e->getMessage());
    }

    $qtdTabelas = count($tables);
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?");
        $st->execute([$dbName]);
        $qtdTabelas = (int) $st->fetchColumn();
    } catch (Throwable $e) {
    }

    $status = [];
    try {
        $rows = $pdo->query("SHOW GLOBAL STATUS WHERE Variable_name IN (
            'Threads_connected','Threads_running','Slow_queries','Open_tables',
            'Table_locks_waited','Innodb_buffer_pool_read_requests','Innodb_buffer_pool_reads'
        )")->fetchAll(PDO::FETCH_KEY_PAIR);
        $status = $rows;
    } catch (Throwable $e) {
    }

    $cacheHit = null;
    $reads = (int) ($status['Innodb_buffer_pool_read_requests'] ?? 0);
    $diskReads = (int) ($status['Innodb_buffer_pool_reads'] ?? 0);
    if ($reads > 0) {
        $cacheHit = round((1 - $diskReads / max(1, $reads)) * 100, 1);
    }

    $processlist = [];
    try {
        $processlist = $pdo->query('SHOW FULL PROCESSLIST')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
    }

    $slowFromMetrics = [];
    try {
        $slowFromMetrics = $pdo->query("
            SELECT rota, modulo, duracao_ms, created_at
            FROM adm_request_metrics
            WHERE duracao_ms >= 200
            ORDER BY duracao_ms DESC LIMIT 15
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
    }

    $indexSuggestions = adm_db_index_suggestions($pdo, $dbName);
    $slowLog = adm_slow_query_log_entries($pdo, 15);

    return [
        'database' => $dbName,
        'version' => $version,
        'qtd_tabelas' => $qtdTabelas,
        'tamanho_mb' => round($totalBytes, 2),
        'maior_tabela' => $tables[0] ?? null,
        'tabelas' => $tables,
        'pagination' => adm_pagination_meta($qtdTabelas, $pg['page'], $pg['limit']),
        'conexoes' => (int) ($status['Threads_connected'] ?? 0),
        'executando' => (int) ($status['Threads_running'] ?? 0),
        'slow_queries' => (int) ($status['Slow_queries'] ?? 0),
        'locks_espera' => (int) ($status['Table_locks_waited'] ?? 0),
        'cache_hit_pct' => $cacheHit,
        'processlist' => array_slice($processlist, 0, 20),
        'rotas_lentas' => $slowFromMetrics,
        'slow_log' => $slowLog,
        'index_suggestions' => $indexSuggestions,
    ];
}

function adm_db_index_suggestions(PDO $pdo, string $dbName): array
{
    $out = [];
    try {
        $stmt = $pdo->prepare("
            SELECT TABLE_NAME, COLUMN_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND COLUMN_NAME IN ('empresa_id','empresa_cliente_id','empresa_adm_id','created_at','data_criacao','status_id')
            ORDER BY TABLE_NAME
        ");
        $stmt->execute([$dbName]);
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            $table = $c['TABLE_NAME'];
            $col = $c['COLUMN_NAME'];
            $idx = $pdo->prepare("
                SELECT COUNT(*) FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
            ");
            $idx->execute([$dbName, $table, $col]);
            if ((int) $idx->fetchColumn() === 0) {
                $out[] = [
                    'tabela' => $table,
                    'coluna' => $col,
                    'sugestao' => "CREATE INDEX idx_{$table}_{$col} ON `{$table}` (`{$col}`)",
                ];
            }
            if (count($out) >= 12) {
                break;
            }
        }
    } catch (Throwable $e) {
        error_log('adm_db_index_suggestions: ' . $e->getMessage());
    }
    return $out;
}

function adm_sql_stream(PDO $pdo): array
{
    require_once __DIR__ . '/adm_maintenance.php'; // define adm_slow_query_log_entries()
    $lines = [];
    try {
        $rows = $pdo->query("
            SELECT tipo, rota, modulo, duracao_ms, detalhe, created_at
            FROM adm_sql_log
            ORDER BY id DESC LIMIT 40
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $lines[] = $r;
        }
    } catch (Throwable $e) {
    }

    if (count($lines) < 5) {
        try {
            $rows = $pdo->query("
                SELECT 'OTHER' AS tipo, rota, modulo, duracao_ms,
                       CONCAT('HTTP ', rota) AS detalhe, created_at
                FROM adm_request_metrics
                ORDER BY id DESC LIMIT 30
            ")->fetchAll(PDO::FETCH_ASSOC);
            $lines = array_merge($lines, $rows);
        } catch (Throwable $e) {
        }
    }

    $live = [];
    try {
        foreach ($pdo->query('SHOW FULL PROCESSLIST')->fetchAll(PDO::FETCH_ASSOC) as $p) {
            if (($p['Command'] ?? '') === 'Sleep' || empty($p['Info'])) {
                continue;
            }
            $info = (string) $p['Info'];
            $tipo = 'OTHER';
            if (preg_match('/^\s*(SELECT|INSERT|UPDATE|DELETE)/i', $info, $m)) {
                $tipo = strtoupper($m[1]);
            }
            $live[] = [
                'tipo' => $tipo,
                'tempo_ms' => (int) ($p['Time'] ?? 0) * 1000,
                'origem' => 'MySQL #' . ($p['Id'] ?? ''),
                'query' => substr($info, 0, 200),
            ];
            if (count($live) >= 10) {
                break;
            }
        }
    } catch (Throwable $e) {
    }

    $slowEntries = adm_slow_query_log_entries($pdo, 15);
    foreach ($slowEntries as $e) {
        $lines[] = [
            'tipo' => $e['tipo'] ?? 'SQL',
            'rota' => $e['origem'] ?? 'slow_log',
            'modulo' => 'mysql',
            'duracao_ms' => $e['tempo_ms'] ?? 0,
            'detalhe' => $e['query'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    return ['stream' => array_slice($lines, 0, 50), 'live' => $live, 'fonte_slow' => count($slowEntries) > 0 ? 'slow_query_log' : 'request_metrics'];
}

function adm_sql_log_from_metrics(PDO $pdo): void
{
    try {
        $rows = $pdo->query("
            SELECT rota, modulo, duracao_ms, empresa_cliente_id
            FROM adm_request_metrics
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
              AND duracao_ms >= 100
            ORDER BY id DESC LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);
        $exists = $pdo->prepare('SELECT id FROM adm_sql_log WHERE rota = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND) LIMIT 1');
        $ins = $pdo->prepare('INSERT INTO adm_sql_log (tipo, rota, modulo, duracao_ms, empresa_cliente_id, detalhe) VALUES (?,?,?,?,?,?)');
        foreach ($rows as $r) {
            $rota = substr((string) ($r['rota'] ?? ''), 0, 255);
            $exists->execute([$rota]);
            if ($exists->fetch()) {
                continue;
            }
            $tipo = 'OTHER';
            if (stripos($rota, 'select') !== false) {
                $tipo = 'SELECT';
            }
            $ins->execute([
                $tipo,
                $rota,
                $r['modulo'] ?? null,
                (int) $r['duracao_ms'],
                $r['empresa_cliente_id'] ?? null,
                'Rota HTTP lenta',
            ]);
        }
    } catch (Throwable $e) {
    }
}
