<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/adm_api.php';

try {
    $pdo = adm_api_boot();

    $logFile = dirname(__DIR__, 3) . '/logs/php_error.log';
    $errors = [];

    if (is_readable($logFile)) {
        $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $lines = array_slice(array_reverse($lines), 0, 200);
        foreach ($lines as $line) {
            $tipo = 'notice';
            if (stripos($line, 'Fatal') !== false) {
                $tipo = 'fatal';
            } elseif (stripos($line, 'Warning') !== false) {
                $tipo = 'warning';
            } elseif (stripos($line, 'Exception') !== false) {
                $tipo = 'exception';
            }
            $errors[] = ['linha' => $line, 'tipo' => $tipo];
        }
    }

    $dbErr = [];
    try {
        $dbErr = $pdo->query("
            SELECT mensagem, COUNT(*) AS freq, MAX(created_at) AS ultimo
            FROM adm_eventos WHERE severidade IN ('erro','critico')
            GROUP BY mensagem ORDER BY freq DESC LIMIT 30
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('errors.php db: ' . $e->getMessage());
    }

    adm_api_ok(['file_errors' => $errors, 'aggregated' => $dbErr]);
} catch (Throwable $e) {
    error_log('errors.php: ' . $e->getMessage());
    if (function_exists('api_json_error')) {
        api_json_error('Erro ao carregar central de erros.', 500);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno']);
}
