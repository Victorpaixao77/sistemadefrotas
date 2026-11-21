<?php
/**
 * Métricas, saúde da IA e custos por empresa (SaaS).
 */
declare(strict_types=1);

function sf_ia_metrics_ensure_schema(PDO $conn): void
{
    $files = [
        dirname(__DIR__) . '/sql/ia_copilot_phase2.sql',
        dirname(__DIR__) . '/sql/ia_copilot_phase3.sql',
    ];
    foreach ($files as $file) {
        if (!is_file($file)) {
            continue;
        }
        $sql = file_get_contents($file);
        if ($sql === false) {
            continue;
        }
        foreach (preg_split('/;\s*(?:\r?\n)/', $sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || stripos($stmt, 'CREATE TABLE') === false) {
                continue;
            }
            if (substr($stmt, -1) !== ';') {
                $stmt .= ';';
            }
            try {
                $conn->exec($stmt);
            } catch (PDOException $e) {
                // ignora
            }
        }
    }
}

/** Registra uso diário (consulta, tokens, cache, LLM, falha). */
function sf_ia_metrics_track(PDO $conn, int $empresaId, array $opts = []): void
{
    if ($empresaId < 1 || !sf_ia_table_exists($conn, 'ia_uso_empresa')) {
        return;
    }
    sf_ia_metrics_ensure_schema($conn);
    $dia = date('Y-m-d');
    $tokensIn = (int) ($opts['tokens_in'] ?? 0);
    $tokensOut = (int) ($opts['tokens_out'] ?? 0);
    $cacheHit = !empty($opts['cache_hit']) ? 1 : 0;
    $llm = !empty($opts['llm']) ? 1 : 0;
    $falha = !empty($opts['falha']) ? 1 : 0;
    $custo = sf_ia_metrics_estimate_cost($tokensIn + $tokensOut);

    try {
        $st = $conn->prepare("INSERT INTO ia_uso_empresa (empresa_id, dia, consultas, tokens_in, tokens_out, cache_hits, llm_calls, falhas, custo_estimado_usd)
            VALUES (:e, :d, 1, :ti, :to, :ch, :llm, :f, :c)
            ON DUPLICATE KEY UPDATE
                consultas = consultas + 1,
                tokens_in = tokens_in + VALUES(tokens_in),
                tokens_out = tokens_out + VALUES(tokens_out),
                cache_hits = cache_hits + VALUES(cache_hits),
                llm_calls = llm_calls + VALUES(llm_calls),
                falhas = falhas + VALUES(falhas),
                custo_estimado_usd = custo_estimado_usd + VALUES(custo_estimado_usd)");
        $st->execute([
            ':e' => $empresaId, ':d' => $dia,
            ':ti' => $tokensIn, ':to' => $tokensOut,
            ':ch' => $cacheHit, ':llm' => $llm, ':f' => $falha, ':c' => $custo,
        ]);
    } catch (Throwable $e) {
        error_log('sf_ia_metrics_track: ' . $e->getMessage());
    }
}

function sf_ia_metrics_estimate_cost(int $tokens, string $modelo = 'gpt-4o-mini'): float
{
    // USD por 1M tokens (estimativa conservadora)
    static $rates = [
        'gpt-4o-mini' => 0.00015,
        'gpt-4o' => 0.005,
        'default' => 0.001,
    ];
    $rate = $rates[$modelo] ?? $rates['default'];
    return round(($tokens / 1_000_000) * $rate, 4);
}

/** Painel de saúde da IA (empresa ou global admin). */
function sf_ia_metrics_saude(PDO $conn, ?int $empresaId = null): array
{
    sf_ia_metrics_ensure_schema($conn);
    $hoje = date('Y-m-d');
    $out = [
        'tempo_medio_ms' => 0,
        'consultas_hoje' => 0,
        'cache_hit_pct' => 0,
        'llm_pct' => 0,
        'banco_pct' => 0,
        'tokens_hoje' => 0,
        'economia_cache_usd' => 0,
        'falhas_hoje' => 0,
        'intent_top' => [],
        'intent_erros' => [],
        'uso_mes' => [],
    ];

    $where = $empresaId ? 'WHERE empresa_id = :e' : '';
    $bind = $empresaId ? [':e' => $empresaId] : [];

    if (sf_ia_table_exists($conn, 'ia_auditoria')) {
        try {
            $sql = "SELECT COUNT(*) q, AVG(tempo_ms) tm FROM ia_auditoria WHERE DATE(created_at) = :h";
            if ($empresaId) {
                $sql .= ' AND empresa_id = :e';
            }
            $st = $conn->prepare($sql);
            $st->execute(array_merge([':h' => $hoje], $bind));
            $r = $st->fetch(PDO::FETCH_ASSOC);
            $out['consultas_hoje'] = (int) ($r['q'] ?? 0);
            $out['tempo_medio_ms'] = (int) round((float) ($r['tm'] ?? 0));
        } catch (Throwable $e) {
            // ignora
        }
    }

    if (sf_ia_table_exists($conn, 'ia_uso_empresa')) {
        try {
            $sql = "SELECT consultas, tokens_in, tokens_out, cache_hits, llm_calls, falhas, custo_estimado_usd
                FROM ia_uso_empresa WHERE dia = :h";
            if ($empresaId) {
                $sql .= ' AND empresa_id = :e';
            }
            $st = $conn->prepare($sql);
            $st->execute(array_merge([':h' => $hoje], $bind));
            $agg = ['consultas' => 0, 'tokens_in' => 0, 'tokens_out' => 0, 'cache_hits' => 0, 'llm_calls' => 0, 'falhas' => 0, 'custo' => 0];
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $agg['consultas'] += (int) $row['consultas'];
                $agg['tokens_in'] += (int) $row['tokens_in'];
                $agg['tokens_out'] += (int) $row['tokens_out'];
                $agg['cache_hits'] += (int) $row['cache_hits'];
                $agg['llm_calls'] += (int) $row['llm_calls'];
                $agg['falhas'] += (int) $row['falhas'];
                $agg['custo'] += (float) $row['custo_estimado_usd'];
            }
            $tot = max(1, $agg['consultas']);
            $out['consultas_hoje'] = max($out['consultas_hoje'], $agg['consultas']);
            $out['tokens_hoje'] = $agg['tokens_in'] + $agg['tokens_out'];
            $out['cache_hit_pct'] = (int) round($agg['cache_hits'] / $tot * 100);
            $out['llm_pct'] = (int) round($agg['llm_calls'] / $tot * 100);
            $out['banco_pct'] = max(0, 100 - $out['llm_pct']);
            $out['falhas_hoje'] = $agg['falhas'];
            $out['economia_cache_usd'] = round($agg['cache_hits'] * 0.002, 2);
            $out['custo_estimado_usd'] = round($agg['custo'], 4);
        } catch (Throwable $e) {
            // ignora
        }
    }

    if (sf_ia_table_exists($conn, 'ia_auditoria')) {
        try {
            $sql = "SELECT intent, COUNT(*) c FROM ia_auditoria WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            if ($empresaId) {
                $sql .= ' AND empresa_id = :e';
            }
            $sql .= ' GROUP BY intent ORDER BY c DESC LIMIT 8';
            $st = $conn->prepare($sql);
            $st->execute($bind);
            $out['intent_top'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            // ignora
        }
    }

    if (sf_ia_table_exists($conn, 'ia_metricas_diarias')) {
        try {
            $sql = "SELECT intent, SUM(falhas) f, SUM(consultas) c FROM ia_metricas_diarias
                WHERE dia >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            if ($empresaId) {
                $sql .= ' AND empresa_id = :e';
            }
            $sql .= ' GROUP BY intent HAVING f > 0 ORDER BY f DESC LIMIT 8';
            $st = $conn->prepare($sql);
            $st->execute($bind);
            $out['intent_erros'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            // ignora
        }
    }

    if (sf_ia_table_exists($conn, 'ia_auditoria')) {
        try {
            $sql = "SELECT usuario_id, COUNT(*) c FROM ia_auditoria
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND usuario_id IS NOT NULL";
            if ($empresaId) {
                $sql .= ' AND empresa_id = :e';
            }
            $sql .= ' GROUP BY usuario_id ORDER BY c DESC LIMIT 5';
            $st = $conn->prepare($sql);
            $st->execute($bind);
            $out['top_usuarios'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            // ignora
        }
        try {
            $sql = "SELECT JSON_UNQUOTE(JSON_EXTRACT(fontes, '$.modelo')) AS modelo, COUNT(*) c
                FROM ia_auditoria WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND fontes IS NOT NULL AND JSON_EXTRACT(fontes, '$.modelo') IS NOT NULL";
            if ($empresaId) {
                $sql .= ' AND empresa_id = :e';
            }
            $sql .= " GROUP BY modelo ORDER BY c DESC LIMIT 6";
            $st = $conn->prepare($sql);
            $st->execute($bind);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $out['modelos_top'] = array_values(array_filter($rows, static fn($r) => ($r['modelo'] ?? '') !== '' && ($r['modelo'] ?? '') !== 'null'));
        } catch (Throwable $e) {
            // ignora
        }
    }

    if (sf_ia_table_exists($conn, 'ia_uso_empresa') && $empresaId) {
        try {
            $st = $conn->prepare("SELECT dia, consultas, tokens_in + tokens_out AS tokens, custo_estimado_usd
                FROM ia_uso_empresa WHERE empresa_id = :e AND dia >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) ORDER BY dia");
            $st->execute([':e' => $empresaId]);
            $out['uso_mes'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            // ignora
        }
    }

    return $out;
}

/** Uso resumido para card Configurações / admin. */
function sf_ia_metrics_uso_empresa(PDO $conn, int $empresaId): array
{
    sf_ia_metrics_ensure_schema($conn);
    $mesIni = date('Y-m-01');
    $out = ['consultas' => 0, 'tokens' => 0, 'custo_usd' => 0, 'ultimo_uso' => null];
    if (!sf_ia_table_exists($conn, 'ia_uso_empresa')) {
        return $out;
    }
    try {
        $st = $conn->prepare("SELECT SUM(consultas) c, SUM(tokens_in+tokens_out) t, SUM(custo_estimado_usd) u, MAX(updated_at) lu
            FROM ia_uso_empresa WHERE empresa_id = :e AND dia >= :m");
        $st->execute([':e' => $empresaId, ':m' => $mesIni]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        $out['consultas'] = (int) ($r['c'] ?? 0);
        $out['tokens'] = (int) ($r['t'] ?? 0);
        $out['custo_usd'] = round((float) ($r['u'] ?? 0), 4);
        $out['ultimo_uso'] = $r['lu'] ?? null;
    } catch (Throwable $e) {
        // ignora
    }
    return $out;
}

/** Benchmark: roda classificador em múltiplos modelos (playground). */
function sf_ia_metrics_benchmark(PDO $conn, int $empresaId, string $pergunta, array $modelos): array
{
    $results = [];
    if (!function_exists('sf_vision_ai_resolve')) {
        return $results;
    }
    $cfg = sf_vision_ai_resolve($conn, $empresaId, 'text');
    if (!$cfg) {
        return $results;
    }
    foreach ($modelos as $modelo) {
        $t0 = microtime(true);
        $intent = 'ajuda';
        $tokens = 0;
        try {
            $payload = [
                'model' => $modelo,
                'messages' => [
                    ['role' => 'system', 'content' => 'Classifique em uma palavra: faturamento, rotas_ativas, manutencao, ajuda. JSON: {"intent":"..."}'],
                    ['role' => 'user', 'content' => $pergunta],
                ],
                'max_tokens' => 30,
                'temperature' => 0,
            ];
            $ch = curl_init($cfg['url']);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $cfg['key']],
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
            ]);
            $raw = curl_exec($ch);
            curl_close($ch);
            $ms = (int) round((microtime(true) - $t0) * 1000);
            if ($raw) {
                $j = json_decode($raw, true);
                $content = (string) ($j['choices'][0]['message']['content'] ?? '');
                $tokens = (int) ($j['usage']['total_tokens'] ?? 60);
                if (preg_match('/"intent"\s*:\s*"([a-z_]+)"/', $content, $m)) {
                    $intent = $m[1];
                }
            } else {
                $ms = 0;
            }
            $results[] = [
                'modelo' => $modelo,
                'intent' => $intent,
                'tempo_ms' => $ms,
                'tokens' => $tokens,
                'custo_usd' => sf_ia_metrics_estimate_cost($tokens, $modelo),
            ];
            if (sf_ia_table_exists($conn, 'ia_benchmark_runs')) {
                $st = $conn->prepare('INSERT INTO ia_benchmark_runs (empresa_id, pergunta, modelo, intent_detectada, tempo_ms, tokens) VALUES (:e,:p,:m,:i,:t,:tk)');
                $st->execute([':e' => $empresaId, ':p' => mb_substr($pergunta, 0, 500), ':m' => $modelo, ':i' => $intent, ':t' => $ms, ':tk' => $tokens]);
            }
        } catch (Throwable $e) {
            $results[] = ['modelo' => $modelo, 'erro' => true];
        }
    }
    return $results;
}

/** Agrega consultas por intent/dia (métricas avançadas). */
function sf_ia_metrics_track_intent(PDO $conn, int $empresaId, string $intent, int $tempoMs, bool $falha = false): void
{
    if ($empresaId < 1 || !sf_ia_table_exists($conn, 'ia_metricas_diarias')) {
        sf_ia_metrics_ensure_schema($conn);
    }
    if (!sf_ia_table_exists($conn, 'ia_metricas_diarias')) {
        return;
    }
    $dia = date('Y-m-d');
    $intent = substr(preg_replace('/[^a-z_]/', '', $intent) ?: 'ajuda', 0, 64);
    try {
        $st = $conn->prepare("INSERT INTO ia_metricas_diarias (empresa_id, dia, intent, consultas, falhas, tempo_ms_total)
            VALUES (:e, :d, :i, 1, :f, :t)
            ON DUPLICATE KEY UPDATE
                consultas = consultas + 1,
                falhas = falhas + VALUES(falhas),
                tempo_ms_total = tempo_ms_total + VALUES(tempo_ms_total)");
        $st->execute([':e' => $empresaId, ':d' => $dia, ':i' => $intent, ':f' => $falha ? 1 : 0, ':t' => max(0, $tempoMs)]);
    } catch (Throwable $e) {
        error_log('sf_ia_metrics_track_intent: ' . $e->getMessage());
    }
}

/** Trace completo para suporte L2 / modo dev. */
function sf_ia_support_trace(PDO $conn, int $empresaId, ?int $usuarioId, string $pergunta, string $intent, array $payload): void
{
    sf_ia_metrics_ensure_schema($conn);
    if (!sf_ia_table_exists($conn, 'ia_suporte_traces')) {
        return;
    }
    try {
        $st = $conn->prepare('INSERT INTO ia_suporte_traces (empresa_id, usuario_id, pergunta, intent, payload) VALUES (:e,:u,:p,:i,:pl)');
        $st->execute([
            ':e' => $empresaId,
            ':u' => $usuarioId,
            ':p' => mb_substr($pergunta, 0, 500),
            ':i' => substr($intent, 0, 64),
            ':pl' => json_encode([
                'tools' => $payload['tools'] ?? [],
                'timeline' => $payload['timeline'] ?? [],
                'raciocinio' => $payload['raciocinio'] ?? [],
                'via' => $payload['via'] ?? null,
                'tokens' => $payload['tokens_estimados'] ?? 0,
            ], JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable $e) {
        error_log('sf_ia_support_trace: ' . $e->getMessage());
    }
}

/** Lista traces para admin suporte. */
function sf_ia_support_traces_list(PDO $conn, ?int $empresaId = null, int $limit = 50): array
{
    sf_ia_metrics_ensure_schema($conn);
    if (!sf_ia_table_exists($conn, 'ia_suporte_traces')) {
        return [];
    }
    $limit = max(1, min(200, $limit));
    $sql = "SELECT id, empresa_id, usuario_id, pergunta, intent, payload, created_at FROM ia_suporte_traces";
    $bind = [];
    if ($empresaId) {
        $sql .= ' WHERE empresa_id = :e';
        $bind[':e'] = $empresaId;
    }
    $sql .= " ORDER BY id DESC LIMIT {$limit}";
    try {
        $st = $conn->prepare($sql);
        $st->execute($bind);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}
