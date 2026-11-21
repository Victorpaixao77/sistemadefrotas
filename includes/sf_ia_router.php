<?php
/**
 * Roteador de modelos por tarefa — classificar (barato), narrar (texto), visão (OCR).
 */
declare(strict_types=1);

require_once __DIR__ . '/sf_vision_ai.php';

/** Tarefas suportadas pelo roteador. */
function sf_ia_router_tasks(): array
{
    return ['classify', 'narrate', 'vision', 'benchmark'];
}

/**
 * Resolve config efetiva por tarefa. Cadeia de fallback:
 * classify → purpose classify | text | vision
 * narrate  → text | vision
 * vision   → vision | text
 *
 * @return array{provider:string,label:string,key:string,model:string,url:string,task:string,purpose:string}|null
 */
function sf_ia_router_resolve(PDO $conn, int $empresaId, string $task): ?array
{
    $task = in_array($task, sf_ia_router_tasks(), true) ? $task : 'narrate';
    $purposes = match ($task) {
        'classify' => ['classify', 'text', 'vision'],
        'narrate' => ['narrate', 'text', 'vision'],
        'vision' => ['vision', 'text'],
        default => ['text', 'vision'],
    };

    $cfg = null;
    $usedPurpose = 'text';
    foreach ($purposes as $p) {
        $cfg = sf_ia_router_resolve_purpose($conn, $empresaId, $p);
        if ($cfg !== null) {
            $usedPurpose = $p;
            break;
        }
    }
    if ($cfg === null) {
        return null;
    }

    if ($task === 'classify') {
        $cfg['model'] = sf_ia_router_cheap_model($cfg['model'], $cfg['provider']);
    }

    $cfg['task'] = $task;
    $cfg['purpose'] = $usedPurpose;
    return $cfg;
}

/** Busca purpose estendido (classify além de vision/text). */
function sf_ia_router_resolve_purpose(PDO $conn, int $empresaId, string $purpose): ?array
{
    if ($purpose === 'classify' || $purpose === 'narrate') {
        sf_vision_ai_ensure_schema($conn);
        try {
            $st = $conn->prepare('SELECT provider, api_key, model, base_url FROM empresa_vision_ai WHERE empresa_id = :e AND purpose = :pu LIMIT 1');
            $st->execute([':e' => $empresaId, ':pu' => $purpose]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row && trim((string) ($row['api_key'] ?? '')) !== '') {
                return sf_ia_router_build_cfg($row);
            }
        } catch (Throwable $e) {
            // ignora
        }
        return null;
    }
    return sf_vision_ai_resolve($conn, $empresaId, $purpose === 'vision' ? 'vision' : 'text');
}

function sf_ia_router_build_cfg(array $row): ?array
{
    $providers = sf_vision_ai_providers();
    $provider = (string) ($row['provider'] ?? 'gemini');
    $def = $providers[$provider] ?? $providers['custom'];
    $key = trim((string) ($row['api_key'] ?? ''));
    if ($key === '') {
        return null;
    }
    $base = trim((string) ($row['base_url'] ?? ''));
    if ($base === '') {
        $base = $def['base'];
    }
    $base = rtrim($base, '/');
    if ($base === '') {
        return null;
    }
    $url = (substr($base, -16) === '/chat/completions') ? $base : $base . '/chat/completions';
    $model = trim((string) ($row['model'] ?? ''));
    if ($model === '') {
        $model = $def['model'] !== '' ? $def['model'] : 'gpt-4o-mini';
    }
    return [
        'provider' => $provider,
        'label' => $def['label'] ?? $provider,
        'key' => $key,
        'model' => $model,
        'url' => $url,
    ];
}

/** Modelo econômico para classificação quando não há purpose classify dedicado. */
function sf_ia_router_cheap_model(string $model, string $provider): string
{
    $env = getenv('SF_IA_MODEL_CLASSIFY');
    if (is_string($env) && $env !== '') {
        return $env;
    }
    static $cheap = [
        'openai' => 'gpt-4o-mini',
        'gemini' => 'gemini-2.0-flash',
        'deepseek' => 'deepseek-chat',
        'openrouter' => 'openai/gpt-4o-mini',
    ];
    if (isset($cheap[$provider])) {
        return $cheap[$provider];
    }
    if (str_contains($model, 'gpt-4o') && !str_contains($model, 'mini')) {
        return 'gpt-4o-mini';
    }
    return $model;
}

/**
 * Chamada LLM unificada com roteador + tokens estimados.
 * @return array{content:string,tokens:int,model:string,task:string}|null
 */
function sf_ia_router_chat(PDO $conn, int $empresaId, string $task, array $messages, int $maxTokens = 120, float $temperature = 0): ?array
{
    $cfg = sf_ia_router_resolve($conn, $empresaId, $task);
    if (!$cfg) {
        return null;
    }
    $payload = [
        'model' => $cfg['model'],
        'messages' => $messages,
        'max_tokens' => $maxTokens,
        'temperature' => $temperature,
    ];
    $ch = curl_init($cfg['url']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $cfg['key']],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$raw || $http >= 400) {
        return null;
    }
    $j = json_decode($raw, true);
    $content = trim((string) ($j['choices'][0]['message']['content'] ?? ''));
    $tokens = (int) ($j['usage']['total_tokens'] ?? ($maxTokens / 2));
    return [
        'content' => $content,
        'tokens' => $tokens,
        'model' => $cfg['model'],
        'task' => $task,
        'purpose' => $cfg['purpose'] ?? $task,
    ];
}

/** Suite fixa de perguntas para benchmark. */
function sf_ia_router_benchmark_suite(): array
{
    return [
        ['pergunta' => 'Qual o faturamento do mês passado?', 'esperado' => 'faturamento'],
        ['pergunta' => 'Quantas rotas ativas tenho agora?', 'esperado' => 'rotas_ativas'],
        ['pergunta' => 'Quanto gastei de manutenção este mês?', 'esperado' => 'manutencao'],
        ['pergunta' => 'Alguma CNH vencendo?', 'esperado' => 'cnh'],
        ['pergunta' => 'Mapa da frota agora', 'esperado' => 'mapa_frota'],
        ['pergunta' => 'Como cadastrar motorista?', 'esperado' => 'ajuda_sistema'],
        ['pergunta' => 'Análise completa da operação', 'esperado' => 'analise_completa'],
        ['pergunta' => 'Comparar com mês passado', 'esperado' => 'faturamento'],
    ];
}

/** Roda benchmark na suite fixa. */
function sf_ia_router_benchmark_suite_run(PDO $conn, int $empresaId, array $modelos): array
{
    $suite = sf_ia_router_benchmark_suite();
    $out = [];
    foreach ($suite as $item) {
        $pergunta = $item['pergunta'];
        $esperado = $item['esperado'];
        foreach ($modelos as $modelo) {
            $t0 = microtime(true);
            $intent = 'ajuda';
            $tokens = 0;
            try {
                $cfg = sf_ia_router_resolve($conn, $empresaId, 'classify');
                if (!$cfg) {
                    continue;
                }
                $payload = [
                    'model' => $modelo,
                    'messages' => [
                        ['role' => 'system', 'content' => 'Classifique em JSON: {"intent":"..."} usando ids: faturamento, rotas_ativas, manutencao, cnh, mapa_frota, ajuda_sistema, analise_completa, ajuda'],
                        ['role' => 'user', 'content' => $pergunta],
                    ],
                    'max_tokens' => 40,
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
                    $tokens = (int) ($j['usage']['total_tokens'] ?? 50);
                    if (preg_match('/"intent"\s*:\s*"([a-z_]+)"/', $content, $m)) {
                        $intent = $m[1];
                    }
                }
                $acertou = $intent === $esperado;
                $out[] = [
                    'pergunta' => $pergunta,
                    'esperado' => $esperado,
                    'modelo' => $modelo,
                    'intent' => $intent,
                    'acertou' => $acertou,
                    'tempo_ms' => $ms,
                    'tokens' => $tokens,
                ];
            } catch (Throwable $e) {
                $out[] = ['pergunta' => $pergunta, 'modelo' => $modelo, 'erro' => true];
            }
        }
    }
    return $out;
}
