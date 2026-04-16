<?php
/**
 * Worker de exemplo: consome fila Redis (LPUSH/BRPOP).
 * Uso: php cron/gps_redis_worker.php
 * Configure SF_REDIS_HOST e opcionalmente SF_REDIS_KEY, SF_REDIS_WORKER_KEY (fila de saída).
 * Personalize o processamento no bloco abaixo (webhook, segundo banco, etc.).
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$host = getenv('SF_REDIS_HOST') ?: '';
if ($host === '') {
    fwrite(STDERR, "Defina SF_REDIS_HOST.\n");
    exit(1);
}

if (!class_exists('Redis')) {
    fwrite(STDERR, "Extensão PHP redis não carregada.\n");
    exit(1);
}

$port = (int) (getenv('SF_REDIS_PORT') ?: 6379);
$srcKey = getenv('SF_REDIS_KEY') ?: 'gps:stream';
$timeout = (int) (getenv('SF_REDIS_BRPOP_TIMEOUT') ?: 5);

$redis = new Redis();
$redis->connect($host, $port, 2.0);
$pass = getenv('SF_REDIS_PASSWORD');
if (is_string($pass) && $pass !== '') {
    $redis->auth($pass);
}

fwrite(STDOUT, "Worker escutando {$srcKey} (BRPOP {$timeout}s)… Ctrl+C para sair.\n");

while (true) {
    $item = $redis->blPop([$srcKey], $timeout);
    if ($item === null || $item === false || !isset($item[1])) {
        continue;
    }
    $json = $item[1];
    $data = json_decode($json, true);
    if (!is_array($data)) {
        fwrite(STDERR, "JSON inválido: {$json}\n");
        continue;
    }
    // TODO: integrar (webhook, analytics, segundo serviço).
    fwrite(STDOUT, date('c') . ' gps event ' . ($data['veiculo_id'] ?? '?') . "\n");
}
