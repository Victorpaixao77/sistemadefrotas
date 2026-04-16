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

    $conn->exec("
        ALTER TABLE fiscal_mdfe
            MODIFY COLUMN status ENUM('rascunho', 'pendente', 'em_envio', 'emitido', 'em_viagem', 'autorizado', 'cancelado', 'encerrado', 'denegado')
            NOT NULL DEFAULT 'rascunho'
    ");

    $conn->exec("
        ALTER TABLE fiscal_mdfe
            ADD COLUMN IF NOT EXISTS data_autorizacao DATETIME NULL AFTER protocolo_autorizacao
    ");

    $conn->exec("
        ALTER TABLE fiscal_mdfe
            ADD COLUMN IF NOT EXISTS data_encerramento DATETIME NULL AFTER status
    ");

    echo json_encode(['ok' => true, 'message' => 'Schema MDF-e crítico atualizado com sucesso.'], JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}

