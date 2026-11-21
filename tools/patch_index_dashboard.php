<?php
$f = dirname(__DIR__) . '/index.php';
$lines = file($f);
$insert = "                <?php \$home_updated_at = date('d/m/Y \\às H:i'); include __DIR__ . '/includes/dashboard_home_v2.php'; ?>\n";
$out = array_merge(
    array_slice($lines, 0, 169),
    [$insert],
    array_slice($lines, 954)
);
file_put_contents($f, implode('', $out));
echo 'OK lines=' . count($out) . PHP_EOL;
