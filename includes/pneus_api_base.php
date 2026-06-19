<?php
/**
 * Bootstrap padrão para APIs de Gestão de Pneus.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/api_json.php';
require_once __DIR__ . '/pneus_helpers.php';

pneus_require_api_access();
