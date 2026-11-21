<?php
$index = dirname(__DIR__) . '/index.php';
$lines = file($index);
// Not used after patch - extract from backup logic
$detail = dirname(__DIR__) . '/includes/dashboard_home_kpis_detail.php';
// Write minimal placeholder; full KPI grids copied manually
$content = "<?php\n/** KPIs detalhados (colapsável) — variáveis do index.php */\n?>\n";
$content .= implode('', array_slice($lines, 390, 225));
file_put_contents($detail, $content);
echo 'Wrote ' . $detail . PHP_EOL;
