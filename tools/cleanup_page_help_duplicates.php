<?php
/**
 * Remove blocos help-section legados deixados fora do modal-body após sf_echo_page_help.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$files = glob($root . '/pages/*.php') ?: [];

foreach ($files as $path) {
    $content = file_get_contents($path);
    if ($content === false || strpos($content, 'sf_echo_page_help') === false) {
        continue;
    }

    $original = $content;

    // Remove help-section órfãos entre </div> do modal-body e modal-footer / fechamento do modal
    $content = preg_replace(
        '/(<\?php sf_echo_page_help\([^)]+\); \?>\s*<\/div>)\s*(?:\s*<div class="help-section">.*?)(?=\s*<div class="modal-footer">)/s',
        "$1\n",
        $content
    );

    // Caso sem modal-footer: até fechar modal-content
    $content = preg_replace(
        '/(<\?php sf_echo_page_help\([^)]+\); \?>\s*<\/div>)\s*(?:\s*<div class="help-section">.*?)(?=\s*<\/div>\s*<\/div>\s*<\/div>\s*(?:<\?php|<!-- |<script))/s',
        "$1\n            ",
        $content
    );

    // Fechar modal-body órfão extra (</div> a mais antes do footer)
    $content = preg_replace(
        '/(<\?php sf_echo_page_help\([^)]+\); \?>\s*<\/div>)\s*<\/div>\s*(<div class="modal-footer">)/s',
        "$1\n            $2",
        $content
    );

    if ($content !== $original) {
        file_put_contents($path, $content);
        echo 'Fixed: ' . basename($path) . "\n";
    }
}

echo "Done.\n";
