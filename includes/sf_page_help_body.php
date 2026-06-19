<?php
/**
 * Injeta conteúdo centralizado de ajuda no modal-body existente da página.
 * Uso no topo do PHP: require_once __DIR__ . '/../includes/sf_page_help.php';
 * No modal-body: <?php sf_page_help_body('vehicles'); ?>
 */
declare(strict_types=1);

require_once __DIR__ . '/sf_page_help.php';

if (!function_exists('sf_page_help_body')) {
    function sf_page_help_body(string $key): void
    {
        sf_echo_page_help($key);
    }
}
