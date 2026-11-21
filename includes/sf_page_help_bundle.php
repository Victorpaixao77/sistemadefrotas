<?php
/**
 * Incluir antes de </body> quando a página define $sf_help_key.
 * Opcional: $sf_help_modal_id (default helpModal), $sf_help_title (override)
 */
declare(strict_types=1);

if (empty($sf_help_key)) {
    return;
}

require_once __DIR__ . '/sf_page_help.php';

$modalId = $sf_help_modal_id ?? 'helpModal';
$opts = ['modal_id' => $modalId];
if (!empty($sf_help_title)) {
    $opts['title'] = (string) $sf_help_title;
}
if (!empty($sf_help_modal_extra_class)) {
    $opts['extra_class'] = (string) $sf_help_modal_extra_class;
}
sf_render_page_help_modal((string) $sf_help_key, $opts);
