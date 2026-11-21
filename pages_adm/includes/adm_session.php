<?php
declare(strict_types=1);

/**
 * Inicia sessão ADM com o mesmo nome/cookie do sistema principal (config.php).
 */
require_once __DIR__ . '/adm_paths.php';

if (!function_exists('configure_session')) {
    require_once SF_PROJECT_ROOT . '/includes/config.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
