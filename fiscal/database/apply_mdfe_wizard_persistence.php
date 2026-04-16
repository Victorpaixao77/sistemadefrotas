<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Use apenas via CLI.\n";
    exit(1);
}

try {
    $conn = getConnection();
    $sqlPath = __DIR__ . '/alter_mdfe_wizard_persistence.sql';
    $sql = file_get_contents($sqlPath);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Não foi possível ler o SQL de migração do wizard MDF-e.');
    }

    // Executa statement por statement removendo comentários SQL.
    $lines = preg_split('/\R/', $sql) ?: [];
    $clean = [];
    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '' || str_starts_with($trim, '--')) {
            continue;
        }
        $clean[] = $line;
    }
    $normalized = implode("\n", $clean);
    $stmts = array_filter(array_map('trim', explode(';', $normalized)));
    foreach ($stmts as $stmt) {
        if ($stmt === '') continue;
        $conn->exec($stmt);
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Tabelas de persistência estruturada do wizard MDF-e criadas/validadas.',
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}

