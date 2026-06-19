<?php
/**
 * Provedor de IA de visão (leitura de fichas de abastecimento) por empresa.
 *
 * Suporta qualquer API compatível com a do OpenAI (mesmo formato de
 * /chat/completions com image_url): OpenAI, Google Gemini (camada OpenAI),
 * OpenRouter e endpoints "custom". A chave fica em empresa_vision_ai.
 *
 * Prioridade na leitura: esta configuração por empresa tem precedência; se não
 * houver, o leitor cai na chave OpenAI/DeepSeek legada.
 */

require_once __DIR__ . '/db_connect.php';

if (!function_exists('sf_vision_ai_providers')) {
    /** Mapa provedor => [base_url, modelo_padrão, rótulo]. */
    function sf_vision_ai_providers(): array
    {
        return [
            'openai' => [
                'base' => 'https://api.openai.com/v1',
                'model' => 'gpt-4o',
                'label' => 'OpenAI',
            ],
            'gemini' => [
                'base' => 'https://generativelanguage.googleapis.com/v1beta/openai',
                'model' => 'gemini-2.0-flash',
                'label' => 'Google Gemini',
            ],
            'openrouter' => [
                'base' => 'https://openrouter.ai/api/v1',
                'model' => 'openai/gpt-4o',
                'label' => 'OpenRouter',
            ],
            'custom' => [
                'base' => '',
                'model' => '',
                'label' => 'Personalizado (compatível com OpenAI)',
            ],
        ];
    }
}

if (!function_exists('sf_vision_ai_ensure_schema')) {
    function sf_vision_ai_ensure_schema(PDO $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $conn->exec(
            'CREATE TABLE IF NOT EXISTS empresa_vision_ai (
                empresa_id INT UNSIGNED NOT NULL PRIMARY KEY,
                provider VARCHAR(20) NOT NULL DEFAULT "gemini",
                api_key MEDIUMTEXT NULL,
                model VARCHAR(80) NULL,
                base_url VARCHAR(255) NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }
}

if (!function_exists('sf_vision_ai_get')) {
    /** Linha de config da empresa (ou null). */
    function sf_vision_ai_get(PDO $conn, int $empresa_id): ?array
    {
        if ($empresa_id <= 0) {
            return null;
        }
        sf_vision_ai_ensure_schema($conn);
        $stmt = $conn->prepare('SELECT provider, api_key, model, base_url FROM empresa_vision_ai WHERE empresa_id = :e LIMIT 1');
        $stmt->execute([':e' => $empresa_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('sf_vision_ai_resolve')) {
    /**
     * Config efetiva para chamar a API. Retorna null se não houver chave.
     * @return array{provider:string,label:string,key:string,model:string,url:string}|null
     */
    function sf_vision_ai_resolve(PDO $conn, int $empresa_id): ?array
    {
        $row = sf_vision_ai_get($conn, $empresa_id);
        if (!$row) {
            return null;
        }
        $key = trim((string) ($row['api_key'] ?? ''));
        if ($key === '') {
            return null;
        }
        $providers = sf_vision_ai_providers();
        $provider = (string) ($row['provider'] ?? 'gemini');
        $def = $providers[$provider] ?? $providers['custom'];

        $base = trim((string) ($row['base_url'] ?? ''));
        if ($base === '') {
            $base = $def['base'];
        }
        $base = rtrim($base, '/');
        if ($base === '') {
            return null; // custom sem base_url é inválido
        }
        $url = (substr($base, -16) === '/chat/completions') ? $base : $base . '/chat/completions';

        $model = trim((string) ($row['model'] ?? ''));
        if ($model === '') {
            $model = $def['model'] !== '' ? $def['model'] : 'gpt-4o';
        }

        return [
            'provider' => $provider,
            'label' => $def['label'] ?? $provider,
            'key' => $key,
            'model' => $model,
            'url' => $url,
        ];
    }
}

if (!function_exists('sf_vision_ai_available')) {
    function sf_vision_ai_available(PDO $conn, int $empresa_id): bool
    {
        return sf_vision_ai_resolve($conn, $empresa_id) !== null;
    }
}
