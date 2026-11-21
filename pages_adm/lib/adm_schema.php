<?php
declare(strict_types=1);

/**
 * Assinatura das migrações — muda quando qualquer arquivo SQL de migração ou
 * este próprio arquivo é alterado. Serve para evitar re-aplicar todo o DDL em
 * cada request (perf), re-aplicando apenas quando algo muda de fato.
 */
function adm_schema_signature(): string
{
    $parts = [__FILE__];
    $root = dirname(__DIR__, 2) . '/sql/';
    foreach (['adm_control_center_foundation.sql', 'adm_control_center_phase2.sql', 'adm_control_center_phase3.sql', 'adm_control_center_phase5.sql', 'adm_deploy_producao.sql'] as $f) {
        $path = $root . $f;
        if (is_readable($path)) {
            $parts[] = $f . ':' . filemtime($path) . ':' . filesize($path);
        }
    }
    $parts[] = 'v3'; // bump manual se alterar a lógica inline abaixo
    return substr(hash('sha256', implode('|', $parts)), 0, 32);
}

/** Lê um marcador de configuração; null em qualquer falha (tabela ausente etc.). */
function adm_schema_config_get(PDO $pdo, string $chave): ?string
{
    try {
        $stmt = $pdo->prepare('SELECT valor FROM adm_platform_config WHERE chave = ? LIMIT 1');
        $stmt->execute([$chave]);
        $v = $stmt->fetchColumn();
        return $v === false ? null : (string) $v;
    } catch (Throwable $e) {
        return null;
    }
}

/** Grava/atualiza um marcador de configuração; silencioso em falha. */
function adm_schema_config_set(PDO $pdo, string $chave, string $valor): void
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO adm_platform_config (chave, valor, categoria) VALUES (?, ?, "sistema")
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        );
        $stmt->execute([$chave, $valor]);
    } catch (Throwable $e) {
        error_log('adm_schema_config_set: ' . $e->getMessage());
    }
}

/** Retenção/limpeza — throttled para no máx. 1x/hora, não a cada request. */
function adm_schema_maybe_purge(PDO $pdo): void
{
    if (!is_file(__DIR__ . '/adm_maintenance.php')) {
        return;
    }
    $last = (int) (adm_schema_config_get($pdo, '__last_purge_ts') ?? 0);
    if (time() - $last < 3600) {
        return;
    }
    require_once __DIR__ . '/adm_maintenance.php';
    adm_maintenance_purge($pdo);
    adm_schema_config_set($pdo, '__last_purge_ts', (string) time());
}

/**
 * Garante schema do Control Center ADM (idempotente).
 *
 * Otimização: o DDL pesado (re-exec dos arquivos SQL, seeds) só roda quando a
 * assinatura das migrações muda. Em regime normal cada request faz apenas 1
 * SELECT barato no marcador e (no máximo 1x/hora) a limpeza de retenção.
 */
function adm_schema_ensure(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $sig = adm_schema_signature();
    if (adm_schema_config_get($pdo, '__schema_sig') === $sig) {
        adm_schema_maybe_purge($pdo);
        return;
    }

    try {
        $col = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa_adm' AND COLUMN_NAME = 'status_saas'")->fetchColumn();
        if ((int) $col === 0) {
            $pdo->exec("ALTER TABLE empresa_adm
                ADD COLUMN status_saas ENUM('trial','ativa','bloqueada','cancelada','suspensa') NOT NULL DEFAULT 'ativa' AFTER email,
                ADD COLUMN trial_ate DATE NULL AFTER status_saas,
                ADD COLUMN bloqueado_motivo TEXT NULL AFTER trial_ate,
                ADD COLUMN cancelada_em DATE NULL AFTER bloqueado_motivo");
        }
    } catch (Throwable $e) {
        error_log('adm_schema empresa_adm: ' . $e->getMessage());
    }

    $sqlFile = dirname(__DIR__, 2) . '/sql/adm_control_center_foundation.sql';
    if (is_readable($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        $parts = preg_split('/;\s*\n/', $sql);
        foreach ($parts as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || str_starts_with($stmt, '--')) {
                continue;
            }
            try {
                $pdo->exec($stmt);
            } catch (Throwable $e) {
                if (strpos($e->getMessage(), 'Duplicate') === false) {
                    error_log('adm_schema: ' . $e->getMessage());
                }
            }
        }
    }

    try {
        $col = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'adm_sessoes_ativas' AND COLUMN_NAME = 'admin_id'")->fetchColumn();
        if ((int) $col === 0) {
            $pdo->exec('ALTER TABLE adm_sessoes_ativas ADD COLUMN admin_id INT NULL AFTER usuario_id, ADD INDEX idx_admin (admin_id)');
        }
    } catch (Throwable $e) {
        error_log('adm_schema adm_sessoes_ativas: ' . $e->getMessage());
    }

    try {
        foreach (['two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0', 'two_factor_secret VARCHAR(64) NULL', 'two_factor_confirmed_at DATETIME NULL'] as $colDef) {
            $colName = explode(' ', $colDef, 2)[0];
            $col = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios_adm' AND COLUMN_NAME = " . $pdo->quote($colName))->fetchColumn();
            if ((int) $col === 0) {
                $pdo->exec('ALTER TABLE usuarios_adm ADD COLUMN ' . $colDef);
            }
        }
    } catch (Throwable $e) {
        error_log('adm_schema usuarios_adm 2FA: ' . $e->getMessage());
    }

    $count = 0;
    try {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM adm_roadmap')->fetchColumn();
    } catch (Throwable $e) {
        error_log('adm_schema adm_roadmap: ' . $e->getMessage());
    }
    $sqlFile2 = dirname(__DIR__, 2) . '/sql/adm_control_center_phase2.sql';
    if (is_readable($sqlFile2)) {
        $sql2 = file_get_contents($sqlFile2);
        $parts2 = preg_split('/;\s*\n/', $sql2);
        foreach ($parts2 as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || str_starts_with($stmt, '--')) {
                continue;
            }
            try {
                $pdo->exec($stmt);
            } catch (Throwable $e) {
                if (strpos($e->getMessage(), 'Duplicate') === false) {
                    error_log('adm_schema phase2: ' . $e->getMessage());
                }
            }
        }
    }

    if (is_file(__DIR__ . '/adm_phase2.php')) {
        require_once __DIR__ . '/adm_phase2.php';
        adm_cron_seed($pdo);
        adm_marketplace_seed($pdo);
    }

    try {
        foreach ([
            'cidade VARCHAR(120) NULL',
            'estado CHAR(2) NULL',
            'created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP',
        ] as $colDef) {
            $colName = explode(' ', $colDef, 2)[0];
            $col = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa_adm' AND COLUMN_NAME = " . $pdo->quote($colName))->fetchColumn();
            if ((int) $col === 0) {
                $pdo->exec('ALTER TABLE empresa_adm ADD COLUMN ' . $colDef);
            }
        }
    } catch (Throwable $e) {
        error_log('adm_schema empresa_adm geo: ' . $e->getMessage());
    }

    $sqlFile3 = dirname(__DIR__, 2) . '/sql/adm_control_center_phase3.sql';
    if (is_readable($sqlFile3)) {
        $sql3 = file_get_contents($sqlFile3);
        $parts3 = preg_split('/;\s*\n/', $sql3);
        foreach ($parts3 as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || str_starts_with($stmt, '--')) {
                continue;
            }
            try {
                $pdo->exec($stmt);
            } catch (Throwable $e) {
                if (strpos($e->getMessage(), 'Duplicate') === false) {
                    error_log('adm_schema phase3: ' . $e->getMessage());
                }
            }
        }
    }

    $sqlFile5 = dirname(__DIR__, 2) . '/sql/adm_control_center_phase5.sql';
    if (is_readable($sqlFile5)) {
        $sql5 = file_get_contents($sqlFile5);
        foreach (preg_split('/;\s*\n/', $sql5) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || str_starts_with($stmt, '--')) {
                continue;
            }
            try {
                $pdo->exec($stmt);
            } catch (Throwable $e) {
                if (strpos($e->getMessage(), 'Duplicate') === false) {
                    error_log('adm_schema phase5: ' . $e->getMessage());
                }
            }
        }
    }

    if ($count === 0) {
        $seed = [
            ['2.5.0', 'Control Center ADM', 'Dashboard executivo, NOC, monitor e alertas', 'desenvolvimento', 45, 1],
            ['2.4.0', 'Segurança enterprise', '2FA, políticas por empresa, geo-bloqueio', 'concluido', 100, 2],
            ['2.6.0', 'IA administrativa plataforma', 'Análise cross-tenant e IA comercial', 'planejado', 10, 3],
            ['2.7.0', 'Modo suporte auditado', 'Impersonation com trilha completa', 'planejado', 0, 4],
        ];
        $ins = $pdo->prepare('INSERT INTO adm_roadmap (versao, titulo, descricao, status, progresso, ordem) VALUES (?,?,?,?,?,?)');
        foreach ($seed as $row) {
            $ins->execute($row);
        }
    }

    // Marca a assinatura aplicada e faz a limpeza de retenção (throttled).
    adm_schema_config_set($pdo, '__schema_sig', $sig);
    adm_schema_maybe_purge($pdo);
}
