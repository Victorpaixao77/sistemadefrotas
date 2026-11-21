<?php
/**
 * Provedor de IA por empresa, com duas finalidades ("purpose"):
 *   - 'vision' → leitura de fichas/notas por imagem (OCR de visão);
 *   - 'text'   → resumos executivos e demais textos do sistema (*_resumo_ia.php).
 *
 * Cada finalidade tem provedor/chave/modelo próprios (tabela empresa_vision_ai,
 * PK composta empresa_id+purpose). Assim a empresa pode, por exemplo, usar
 * Gemini para ler fichas e DeepSeek para os resumos.
 *
 * Suporta qualquer API compatível com a do OpenAI (mesmo formato de
 * /chat/completions): OpenAI, Google Gemini (camada OpenAI), DeepSeek,
 * OpenRouter e endpoints "custom".
 *
 * Compatibilidade: se 'text' não tiver chave própria, cai na config de 'vision'.
 */

require_once __DIR__ . '/db_connect.php';

if (!function_exists('sf_vision_ai_purpose_norm')) {
    /** Normaliza a finalidade para um valor válido ('vision' | 'text' | 'classify' | 'narrate'). */
    function sf_vision_ai_purpose_norm(?string $purpose): string
    {
        $p = strtolower(trim((string) $purpose));
        if (in_array($p, ['text', 'classify', 'narrate'], true)) {
            return $p;
        }
        return 'vision';
    }
}

if (!function_exists('sf_vision_ai_providers')) {
    /**
     * Mapa provedor => [base_url, modelo_padrão, rótulo, suporta visão].
     * 'vision' = false indica que o provedor só serve para texto (ex.: DeepSeek
     * não tem API de imagem).
     */
    function sf_vision_ai_providers(): array
    {
        return [
            'openai' => [
                'base' => 'https://api.openai.com/v1',
                'model' => 'gpt-4o',
                'label' => 'OpenAI (ChatGPT)',
                'vision' => true,
            ],
            'gemini' => [
                'base' => 'https://generativelanguage.googleapis.com/v1beta/openai',
                'model' => 'gemini-2.0-flash',
                'label' => 'Google Gemini',
                'vision' => true,
            ],
            'deepseek' => [
                'base' => 'https://api.deepseek.com/v1',
                'model' => 'deepseek-chat',
                'label' => 'DeepSeek',
                'vision' => false,
            ],
            'openrouter' => [
                'base' => 'https://openrouter.ai/api/v1',
                'model' => 'openai/gpt-4o',
                'label' => 'OpenRouter',
                'vision' => true,
            ],
            'custom' => [
                'base' => '',
                'model' => '',
                'label' => 'Personalizado (compatível com OpenAI)',
                'vision' => true,
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
                empresa_id INT UNSIGNED NOT NULL,
                purpose VARCHAR(20) NOT NULL DEFAULT "vision",
                provider VARCHAR(20) NOT NULL DEFAULT "gemini",
                api_key MEDIUMTEXT NULL,
                model VARCHAR(80) NULL,
                base_url VARCHAR(255) NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (empresa_id, purpose)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        // Migração de instalações antigas (PK só empresa_id, sem coluna purpose).
        try {
            $conn->exec('ALTER TABLE empresa_vision_ai ADD COLUMN purpose VARCHAR(20) NOT NULL DEFAULT "vision" AFTER empresa_id');
        } catch (Throwable $e) {
            // coluna já existe
        }
        try {
            $conn->exec('ALTER TABLE empresa_vision_ai DROP PRIMARY KEY, ADD PRIMARY KEY (empresa_id, purpose)');
        } catch (Throwable $e) {
            // PK já é composta
        }
    }
}

if (!function_exists('sf_vision_ai_get')) {
    /** Linha de config da empresa para a finalidade (ou null). */
    function sf_vision_ai_get(PDO $conn, int $empresa_id, string $purpose = 'vision'): ?array
    {
        if ($empresa_id <= 0) {
            return null;
        }
        sf_vision_ai_ensure_schema($conn);
        $purpose = sf_vision_ai_purpose_norm($purpose);
        $stmt = $conn->prepare('SELECT provider, api_key, model, base_url FROM empresa_vision_ai WHERE empresa_id = :e AND purpose = :pu LIMIT 1');
        $stmt->execute([':e' => $empresa_id, ':pu' => $purpose]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('sf_vision_ai_resolve')) {
    /**
     * Config efetiva para chamar a API, por finalidade. Retorna null se não houver chave.
     * Para 'text' sem chave própria, cai na config de 'vision' (compatibilidade).
     * @return array{provider:string,label:string,key:string,model:string,url:string}|null
     */
    function sf_vision_ai_resolve(PDO $conn, int $empresa_id, string $purpose = 'vision'): ?array
    {
        $purpose = sf_vision_ai_purpose_norm($purpose);
        $row = sf_vision_ai_get($conn, $empresa_id, $purpose);
        if ($purpose === 'text' && (!$row || trim((string) ($row['api_key'] ?? '')) === '')) {
            // Sem config de texto própria: reaproveita a de visão.
            $row = sf_vision_ai_get($conn, $empresa_id, 'vision');
        }
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
    function sf_vision_ai_available(PDO $conn, int $empresa_id, string $purpose = 'vision'): bool
    {
        return sf_vision_ai_resolve($conn, $empresa_id, $purpose) !== null;
    }
}
