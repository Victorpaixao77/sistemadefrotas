<?php

/**
 * Debug CT-e: grava em fiscal/logs/cte_debug.log para inspecionar consulta, download e importação.
 * Ative com ?debug_cte=1 na URL ou defina CTE_DEBUG=true no config/session.
 */

function logCteDebug($message, array $context = [])
{
    $dir = defined('FISCAL_LOG_DIR') ? FISCAL_LOG_DIR : (__DIR__ . '/../logs');
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . '/cte_debug.log';
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] $message";
    if (!empty($context)) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $line .= "\n";
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

function cteDebugEnabled()
{
    if (defined('CTE_DEBUG') && CTE_DEBUG) {
        return true;
    }
    return (isset($_GET['debug_cte']) && $_GET['debug_cte'] === '1')
        || (isset($_POST['debug_cte']) && $_POST['debug_cte'] === '1');
}
