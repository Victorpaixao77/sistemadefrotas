<?php
/**
 * Cache leve em arquivo (JSON) para listagens / agregações.
 */

if (!function_exists('sf_file_cache_path')) {
    function sf_file_cache_path(string $key): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sf_' . md5($key) . '.json';
    }

    /**
     * @return mixed|null Valor decodificado ou null se miss/expirado
     */
    function sf_file_cache_get(string $key, int $ttlSeconds)
    {
        $file = sf_file_cache_path($key);
        if (!is_readable($file) || (time() - (int) @filemtime($file)) >= $ttlSeconds) {
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) && array_key_exists('_v', $data) ? $data['payload'] : null;
    }

    function sf_file_cache_set(string $key, $payload): void
    {
        $file = sf_file_cache_path($key);
        @file_put_contents($file, json_encode(['_v' => 1, 'payload' => $payload], JSON_UNESCAPED_UNICODE));
    }
}

/**
 * Cache em memória compartilhada (APCu) para dados quentes, com fallback no cache
 * em arquivo acima. Seguro: vira no-op se nada estiver disponível.
 * NÃO use TTL longo para dados sensíveis de segurança (permissões revogadas etc.).
 */
if (!function_exists('sf_cache_remember')) {

    function sf_cache_available(): bool
    {
        static $a = null;
        if ($a === null) $a = function_exists('apcu_enabled') && @apcu_enabled();
        return $a;
    }

    /** @return mixed */
    function sf_cache_get(string $key, $default = null)
    {
        if (sf_cache_available()) {
            $ok = false;
            $v = apcu_fetch($key, $ok);
            return $ok ? $v : $default;
        }
        $v = sf_file_cache_get($key, 3600);
        return $v === null ? $default : $v;
    }

    function sf_cache_set(string $key, $value, int $ttl = 300): void
    {
        if (sf_cache_available()) { apcu_store($key, $value, $ttl); return; }
        sf_file_cache_set($key, $value);
    }

    /** @return mixed */
    function sf_cache_remember(string $key, int $ttl, callable $cb)
    {
        $miss = "\0__sf_miss__\0";
        $hit = sf_cache_get($key, $miss);
        if ($hit !== $miss) return $hit;
        $val = $cb();
        sf_cache_set($key, $val, $ttl);
        return $val;
    }

    function sf_cache_forget(string $key): void
    {
        if (sf_cache_available()) { apcu_delete($key); return; }
        $f = sf_file_cache_path($key);
        if (is_file($f)) @unlink($f);
    }

    /** Limpa todas as chaves com um prefixo (apenas backend APCu). */
    function sf_cache_forget_prefix(string $prefix): void
    {
        if (!sf_cache_available() || !class_exists('APCUIterator')) return;
        foreach (new APCUIterator('/^' . preg_quote($prefix, '/') . '/', APC_ITER_KEY) as $item) {
            apcu_delete($item['key']);
        }
    }
}
