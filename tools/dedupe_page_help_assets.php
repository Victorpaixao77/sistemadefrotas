<?php
/**
 * Remove includes duplicados de sf_page_help_css / sf_page_help_js.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$pages = glob($root . '/pages/*.php') ?: [];

foreach ($pages as $path) {
    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }
    $original = $content;
    $content = preg_replace(
        '/(\s*<\?php sf_page_help_css\(\'\.\.\'\); \?>)\s*(<\?php sf_page_help_css\(\'\.\.\'\); \?>)/',
        '$1',
        $content
    );
    $content = preg_replace(
        '/(\s*<\?php sf_page_help_js\(\'\.\.\'\); \?>)\s*(<\?php sf_page_help_js\(\'\.\.\'\); \?>)/',
        '$1',
        $content
    );
    if ($content !== $original) {
        file_put_contents($path, $content);
        echo 'Deduped: ' . basename($path) . "\n";
    }
}

echo "Done.\n";
