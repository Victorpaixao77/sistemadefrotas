<?php
/**
 * Cache curto (arquivo) para endpoints *\_insights.php — reduz carga no primeiro paint.
 */
declare(strict_types=1);

function sf_insights_cache_key(int $empresa_id, string $scope, array $extra = []): string
{
    $suffix = $extra !== [] ? '_' . substr(md5(json_encode($extra, JSON_UNESCAPED_UNICODE)), 0, 12) : '';
    $scope = preg_replace('/[^a-z0-9_]/', '', strtolower($scope)) ?: 'default';
    return 'sf_insights_' . $scope . '_' . $empresa_id . $suffix;
}

/**
 * @param callable(): array $build Deve retornar array serializável (payload JSON).
 */
function sf_insights_json_cached(int $empresa_id, string $scope, int $ttlSeconds, callable $build, array $keyExtra = []): void
{
    require_once __DIR__ . '/cache.php';
    $key = sf_insights_cache_key($empresa_id, $scope, $keyExtra);
    if (!empty($_GET['refresh']) && (string) $_GET['refresh'] === '1') {
        invalidateCache($key);
    }
    $data = getCachedData($key, static function () use ($build) {
        return $build();
    }, max(60, $ttlSeconds));
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}
