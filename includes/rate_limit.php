<?php
/**
 * Rate limit simples por sessão + chave (sliding window em arquivo).
 */

if (!function_exists('sf_rate_limit_allow')) {
    /**
     * @return bool true se permitido, false se excedeu
     */
    function sf_rate_limit_allow(string $bucket, int $maxAttempts, int $windowSeconds): bool
    {
        if ($maxAttempts <= 0 || $windowSeconds <= 0) {
            return true;
        }
        $sid = session_status() === PHP_SESSION_ACTIVE ? session_id() : 'nosess';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sf_rl_' . md5($bucket . '|' . $sid . '|' . $ip) . '.json';
        $now = time();
        $data = ['hits' => []];
        if (is_readable($file)) {
            $raw = @file_get_contents($file);
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['hits']) && is_array($decoded['hits'])) {
                    $data['hits'] = $decoded['hits'];
                }
            }
        }
        $cutoff = $now - $windowSeconds;
        $data['hits'] = array_values(array_filter(array_map('intval', $data['hits']), function ($t) use ($cutoff) {
            return $t >= $cutoff;
        }));
        if (count($data['hits']) >= $maxAttempts) {
            return false;
        }
        $data['hits'][] = $now;
        @file_put_contents($file, json_encode($data));
        return true;
    }
}

if (!function_exists('sf_rate_limit_allow_custom')) {
    /**
     * Rate limit por chave fixa (API sem sessão), ex.: motorista_id + IP.
     */
    function sf_rate_limit_allow_custom(string $bucketKey, int $maxAttempts, int $windowSeconds): bool
    {
        if ($maxAttempts <= 0 || $windowSeconds <= 0) {
            return true;
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sf_rl_' . md5('custom|' . $bucketKey . '|' . $ip) . '.json';
        $now = time();
        $data = ['hits' => []];
        if (is_readable($file)) {
            $raw = @file_get_contents($file);
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['hits']) && is_array($decoded['hits'])) {
                    $data['hits'] = $decoded['hits'];
                }
            }
        }
        $cutoff = $now - $windowSeconds;
        $data['hits'] = array_values(array_filter(array_map('intval', $data['hits']), function ($t) use ($cutoff) {
            return $t >= $cutoff;
        }));
        if (count($data['hits']) >= $maxAttempts) {
            return false;
        }
        $data['hits'][] = $now;
        @file_put_contents($file, json_encode($data));
        return true;
    }
}
