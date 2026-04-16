<?php
/**
 * Cleanup de logs MDF-e (retencao automatica).
 *
 * Exemplo cron:
 * 0 3 * * * /usr/bin/php /caminho/projeto/fiscal/cleanup_mdfe_logs.php >> /tmp/cleanup_mdfe_logs.log 2>&1
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Use apenas via CLI.\n";
    exit(1);
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$conn = getConnection();

$stmtLog = $conn->prepare("
    DELETE FROM mdfe_validacao_log
    WHERE criado_em < (NOW() - INTERVAL 180 DAY)
");
$stmtLog->execute();
$logsRemovidos = (int) $stmtLog->rowCount();

$stmtEnv = $conn->prepare("
    DELETE FROM mdfe_envios
    WHERE criado_em < (NOW() - INTERVAL 180 DAY)
");
$stmtEnv->execute();
$enviosRemovidos = (int) $stmtEnv->rowCount();

echo json_encode([
    'ok' => true,
    'logs_removidos' => $logsRemovidos,
    'envios_removidos' => $enviosRemovidos,
    'timestamp' => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_UNICODE) . PHP_EOL;

