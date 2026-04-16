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
