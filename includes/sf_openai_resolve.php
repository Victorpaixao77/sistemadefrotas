<?php
/**
 * Chave OpenAI: SF_OPENAI_API_KEY (servidor) ou tabela empresa_openai_credentials (por empresa).
 * Coluna legada configuracoes.openai_api_key ainda é lida se existir (migração).
 * Uso estimado em openai_ocr_usage para exibir % do orçamento mensal na tela de configurações.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_connect.php';

/**
 * Garante colunas em configuracoes, tabela de credenciais (chave longa sk-proj-), uso OCR (idempotente).
 */
function sf_openai_ensure_schema(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $alters = [
        'openai_api_key VARCHAR(512) NULL DEFAULT NULL',
        'openai_vision_model VARCHAR(80) NULL DEFAULT NULL',
        'openai_monthly_budget_usd DECIMAL(10,2) NULL DEFAULT NULL',
    ];
    foreach ($alters as $colDef) {
        try {
            $conn->exec('ALTER TABLE configuracoes ADD COLUMN ' . $colDef);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate column') === false && stripos($msg, '1060') === false) {
                // ignora só "coluna já existe"
            }
        }
    }

    $conn->exec(
        'CREATE TABLE IF NOT EXISTS empresa_openai_credentials (
            empresa_id INT UNSIGNED NOT NULL PRIMARY KEY,
            api_key MEDIUMTEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $conn->exec(
        'CREATE TABLE IF NOT EXISTS openai_ocr_usage (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT UNSIGNED NOT NULL,
            prompt_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            completion_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            estimated_cost_usd DECIMAL(14,8) NOT NULL DEFAULT 0,
            model VARCHAR(80) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_empresa_mes (empresa_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    try {
        $conn->exec(
            'INSERT INTO empresa_openai_credentials (empresa_id, api_key)
             SELECT c.empresa_id, c.openai_api_key FROM configuracoes c
             WHERE c.openai_api_key IS NOT NULL AND TRIM(c.openai_api_key) != \'\'
             AND NOT EXISTS (SELECT 1 FROM empresa_openai_credentials x WHERE x.empresa_id = c.empresa_id)'
        );
    } catch (PDOException $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log('openai migrate credentials: ' . $e->getMessage());
        }
    }
}

/**
 * Chave guardada no banco (tabela dedicada, depois coluna legada em configuracoes).
 */
function sf_openai_key_from_database(PDO $conn, int $empresa_id): string
{
    if ($empresa_id <= 0) {
        return '';
    }
    sf_openai_ensure_schema($conn);
    $stmt = $conn->prepare('SELECT api_key FROM empresa_openai_credentials WHERE empresa_id = :e LIMIT 1');
    $stmt->execute([':e' => $empresa_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && isset($row['api_key'])) {
        $k = trim((string) $row['api_key']);
        if ($k !== '') {
            return $k;
        }
    }
    $stmt2 = $conn->prepare('SELECT openai_api_key FROM configuracoes WHERE empresa_id = :e LIMIT 1');
    $stmt2->execute([':e' => $empresa_id]);
    $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
    $k2 = isset($row2['openai_api_key']) ? trim((string) $row2['openai_api_key']) : '';

    return $k2;
}

function sf_openai_has_table_credential(PDO $conn, int $empresa_id): bool
{
    if ($empresa_id <= 0) {
        return false;
    }
    sf_openai_ensure_schema($conn);
    $stmt = $conn->prepare(
        'SELECT 1 FROM empresa_openai_credentials WHERE empresa_id = :e AND api_key IS NOT NULL AND CHAR_LENGTH(TRIM(api_key)) > 0 LIMIT 1'
    );
    $stmt->execute([':e' => $empresa_id]);

    return (bool) $stmt->fetchColumn();
}

/**
 * Chave efetiva: env/constante (global) tem prioridade; senão credencial por empresa no banco.
 */
function sf_openai_key_resolved(PDO $conn, int $empresa_id): string
{
    sf_openai_ensure_schema($conn);
    $env = sf_openai_api_key();
    if ($env !== '') {
        return $env;
    }

    return sf_openai_key_from_database($conn, $empresa_id);
}

function sf_openai_ocr_available(int $empresa_id): bool
{
    if ($empresa_id <= 0) {
        return sf_openai_api_key() !== '';
    }
    try {
        $conn = getConnection();

        return sf_openai_key_resolved($conn, $empresa_id) !== '';
    } catch (Throwable $e) {
        return sf_openai_api_key() !== '';
    }
}

/**
 * Modelo: env > coluna empresa > padrão global.
 */
function sf_openai_vision_model_resolved(PDO $conn, int $empresa_id): string
{
    sf_openai_ensure_schema($conn);
    $envModel = getenv('SF_OPENAI_VISION_MODEL');
    if (is_string($envModel) && trim($envModel) !== '') {
        return trim($envModel);
    }
    if ($empresa_id > 0) {
        $stmt = $conn->prepare('SELECT openai_vision_model FROM configuracoes WHERE empresa_id = :e LIMIT 1');
        $stmt->execute([':e' => $empresa_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $m = isset($row['openai_vision_model']) ? trim((string) $row['openai_vision_model']) : '';
        if ($m !== '') {
            return $m;
        }
    }

    return sf_openai_vision_model();
}

/** Estimativa USD (preços aproximados; conferir fatura OpenAI). */
function sf_openai_estimate_cost_usd(string $model, int $promptTok, int $completionTok): float
{
    $m = strtolower($model);
    if (strpos($m, 'gpt-4o-mini') !== false) {
        $inPer = 0.15 / 1e6;
        $outPer = 0.60 / 1e6;
    } elseif (strpos($m, 'gpt-4o') !== false && strpos($m, 'mini') === false) {
        $inPer = 2.50 / 1e6;
        $outPer = 10.00 / 1e6;
    } else {
        $inPer = 0.50 / 1e6;
        $outPer = 1.50 / 1e6;
    }

    return max(0.0, $promptTok * $inPer + $completionTok * $outPer);
}

function sf_openai_log_usage(PDO $conn, int $empresa_id, string $model, int $promptTok, int $completionTok): void
{
    if ($empresa_id <= 0) {
        return;
    }
    sf_openai_ensure_schema($conn);
    $cost = sf_openai_estimate_cost_usd($model, $promptTok, $completionTok);
    $stmt = $conn->prepare(
        'INSERT INTO openai_ocr_usage (empresa_id, prompt_tokens, completion_tokens, estimated_cost_usd, model) VALUES (:e, :p, :c, :cost, :m)'
    );
    $stmt->execute([
        ':e' => $empresa_id,
        ':p' => max(0, $promptTok),
        ':c' => max(0, $completionTok),
        ':cost' => round($cost, 8),
        ':m' => $model !== '' ? $model : null,
    ]);
}

/**
 * @return array{
 *   has_key: bool,
 *   key_masked: string,
 *   key_source_env: bool,
 *   key_saved_in_database: bool,
 *   key_in_table: bool,
 *   vision_model: string,
 *   monthly_budget_usd: ?float,
 *   month_prompt_tokens: int,
 *   month_completion_tokens: int,
 *   month_estimated_usd: float,
 *   pct_used: ?float
 * }
 */
function sf_openai_settings_payload(PDO $conn, int $empresa_id): array
{
    sf_openai_ensure_schema($conn);
    $fromEnv = sf_openai_api_key() !== '';
    $keyFromDb = sf_openai_key_from_database($conn, $empresa_id);
    $keyResolved = $fromEnv ? sf_openai_api_key() : $keyFromDb;
    $hasKey = $keyResolved !== '';
    $inTable = sf_openai_has_table_credential($conn, $empresa_id);
    $legacyStmt = $conn->prepare('SELECT openai_api_key FROM configuracoes WHERE empresa_id = :e LIMIT 1');
    $legacyStmt->execute([':e' => $empresa_id]);
    $legacyRow = $legacyStmt->fetch(PDO::FETCH_ASSOC);
    $legacyK = isset($legacyRow['openai_api_key']) ? trim((string) $legacyRow['openai_api_key']) : '';
    $savedInDb = $inTable || $legacyK !== '';
    $masked = '';
    if ($hasKey) {
        $tail = strlen($keyResolved) > 6 ? substr($keyResolved, -4) : '****';
        $masked = 'sk-…' . $tail;
    }
    $stmt = $conn->prepare(
        'SELECT openai_vision_model, openai_monthly_budget_usd FROM configuracoes WHERE empresa_id = :e LIMIT 1'
    );
    $stmt->execute([':e' => $empresa_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $vision = isset($row['openai_vision_model']) && trim((string) $row['openai_vision_model']) !== ''
        ? trim((string) $row['openai_vision_model'])
        : sf_openai_vision_model();
    $budget = isset($row['openai_monthly_budget_usd']) && $row['openai_monthly_budget_usd'] !== null && $row['openai_monthly_budget_usd'] !== ''
        ? (float) $row['openai_monthly_budget_usd']
        : null;

    $monthStart = date('Y-m-01 00:00:00');
    $u = $conn->prepare(
        'SELECT COALESCE(SUM(prompt_tokens),0) AS pt, COALESCE(SUM(completion_tokens),0) AS ct, COALESCE(SUM(estimated_cost_usd),0) AS usd
         FROM openai_ocr_usage WHERE empresa_id = :e AND created_at >= :start'
    );
    $u->execute([':e' => $empresa_id, ':start' => $monthStart]);
    $agg = $u->fetch(PDO::FETCH_ASSOC) ?: ['pt' => 0, 'ct' => 0, 'usd' => 0];
    $pt = (int) $agg['pt'];
    $ct = (int) $agg['ct'];
    $usd = (float) $agg['usd'];
    $pct = null;
    if ($budget !== null && $budget > 0) {
        $pct = min(100.0, round(($usd / $budget) * 100.0, 1));
    }

    return [
        'has_key' => $hasKey,
        'key_source_env' => $fromEnv,
        'key_masked' => $masked,
        'key_saved_in_database' => $savedInDb,
        'key_in_table' => $inTable,
        'vision_model' => $vision,
        'monthly_budget_usd' => $budget,
        'month_prompt_tokens' => $pt,
        'month_completion_tokens' => $ct,
        'month_estimated_usd' => round($usd, 4),
        'pct_used' => $pct,
    ];
}
