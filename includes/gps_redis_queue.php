<?php
/**
 * Fila opcional Redis para fan-out / microserviços (pontos GPS).
 * Ative com variáveis de ambiente:
 *   SF_REDIS_HOST (obrigatório para usar), SF_REDIS_PORT (default 6379), SF_REDIS_KEY (default gps:stream)
 * Requer extensão PHP redis.
 */

if (!function_exists('gps_redis_queue_try_push')) {
    function gps_redis_queue_try_push(array $payload): bool
    {
        $host = getenv('SF_REDIS_HOST');
        if (!is_string($host) || $host === '') {
            return false;
        }
        if (!class_exists('Redis')) {
            return false;
        }
        $port = (int) (getenv('SF_REDIS_PORT') ?: 6379);
        $key = getenv('SF_REDIS_KEY') ?: 'gps:stream';
        $payload['queued_at'] = date('c');

        try {
            $redis = new Redis();
            if (!$redis->connect($host, $port, 1.5)) {
                return false;
            }
            $pass = getenv('SF_REDIS_PASSWORD');
            if (is_string($pass) && $pass !== '') {
                $redis->auth($pass);
            }
            $redis->rPush($key, json_encode($payload, JSON_UNESCAPED_UNICODE));
            $redis->close();
            return true;
        } catch (Throwable $e) {
            error_log('gps_redis_queue: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('gps_redis_cache_key_ultima')) {
    function gps_redis_cache_key_ultima(int $empresa_id, int $veiculo_id): string
    {
        return 'gps:ultima:' . $empresa_id . ':' . $veiculo_id;
    }
}

if (!function_exists('gps_redis_cache_set_ultima')) {
    /**
     * Cache da última posição (painel: SF_GPS_REDIS_READ=1 em api/gps_posicoes.php).
     * Opt-in: SF_GPS_REDIS_CACHE=1 e SF_REDIS_HOST.
     */
    function gps_redis_cache_set_ultima(int $empresa_id, int $veiculo_id, array $fields, int $ttlSeconds = 86400): bool
    {
        if (getenv('SF_GPS_REDIS_CACHE') !== '1') {
            return false;
        }
        $host = getenv('SF_REDIS_HOST');
        if (!is_string($host) || $host === '') {
            return false;
        }
        if (!class_exists('Redis')) {
            return false;
        }
        $port = (int) (getenv('SF_REDIS_PORT') ?: 6379);
        $key = gps_redis_cache_key_ultima($empresa_id, $veiculo_id);
        $fields['updated_at'] = date('c');
        try {
            $redis = new Redis();
            if (!$redis->connect($host, $port, 1.5)) {
                return false;
            }
            $pass = getenv('SF_REDIS_PASSWORD');
            if (is_string($pass) && $pass !== '') {
                $redis->auth($pass);
            }
            $redis->setex($key, max(60, $ttlSeconds), json_encode($fields, JSON_UNESCAPED_UNICODE));
            $redis->close();
            return true;
        } catch (Throwable $e) {
            error_log('gps_redis_cache_set_ultima: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('gps_redis_cache_mget_ultimas')) {
    /**
     * @param int[] $veiculo_ids
     * @return array<int, array<string, mixed>|null> veiculo_id => decoded ou null
     */
    function gps_redis_cache_mget_ultimas(int $empresa_id, array $veiculo_ids): array
    {
        $out = [];
        foreach ($veiculo_ids as $id) {
            $out[(int) $id] = null;
        }
        $host = getenv('SF_REDIS_HOST');
        if (!is_string($host) || $host === '' || !class_exists('Redis')) {
            return $out;
        }
        if (getenv('SF_GPS_REDIS_READ') !== '1') {
            return $out;
        }
        $ids = array_values(array_unique(array_map('intval', $veiculo_ids)));
        if ($ids === []) {
            return $out;
        }
        $keys = array_map(function ($vid) use ($empresa_id) {
            return gps_redis_cache_key_ultima($empresa_id, $vid);
        }, $ids);
        try {
            $redis = new Redis();
            if (!$redis->connect($host, (int) (getenv('SF_REDIS_PORT') ?: 6379), 1.5)) {
                return $out;
            }
            $pass = getenv('SF_REDIS_PASSWORD');
            if (is_string($pass) && $pass !== '') {
                $redis->auth($pass);
            }
            $raw = $redis->mGet($keys);
            $redis->close();
            if (!is_array($raw)) {
                return $out;
            }
            foreach ($ids as $i => $vid) {
                $s = $raw[$i] ?? null;
                if (!is_string($s) || $s === '') {
                    continue;
                }
                $dec = json_decode($s, true);
                if (is_array($dec)) {
                    $out[$vid] = $dec;
                }
            }
        } catch (Throwable $e) {
            error_log('gps_redis_cache_mget_ultimas: ' . $e->getMessage());
        }
        return $out;
    }
}
