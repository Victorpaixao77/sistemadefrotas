<?php
/** CLI: php tools/inspect_import_xlsx.php "path\to\file.xlsx" */
require __DIR__ . '/../vendor/autoload.php';

$path = $argv[1] ?? '';
if ($path === '' || !is_readable($path)) {
    fwrite(STDERR, "Usage: php inspect_import_xlsx.php <file.xlsx>\n");
    exit(1);
}

$ss = PhpOffice\PhpSpreadsheet\IOFactory::load($path);

foreach ($ss->getSheetNames() as $name) {
    echo "=== SHEET: {$name} ===\n";
    $sh = $ss->getSheetByName($name);
    $rows = $sh->toArray(null, true, true, true);
    $clean = [];
    foreach ($rows as $r) {
        $allEmpty = true;
        foreach ($r as $c) {
            if ($c !== null && trim((string) $c) !== '') {
                $allEmpty = false;
                break;
            }
        }
        if (!$allEmpty) {
            $clean[] = $r;
        }
    }
    echo "clean rows: " . count($clean) . "\n";
    $maxPreview = min(5, count($clean));
    for ($i = 0; $i < $maxPreview; $i++) {
        echo "--- clean index {$i} ---\n";
        $row = $clean[$i];
        ksort($row);
        foreach ($row as $col => $val) {
            $j = json_encode($val, JSON_UNESCAPED_UNICODE);
            if (strlen($j) > 80) {
                $j = substr($j, 0, 77) . '...';
            }
            echo "  {$col}: {$j}\n";
        }
    }
    echo "\n";
}
