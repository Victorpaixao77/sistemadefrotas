<?php
declare(strict_types=1);

require_once __DIR__ . '/adm_phase2.php';

function adm_storage_sync_all(PDO $pdo, int $limit = 200): array
{
    $synced = 0;
    try {
        $rows = $pdo->query('SELECT id FROM empresa_adm ORDER BY id LIMIT ' . (int) $limit)->fetchAll(PDO::FETCH_COLUMN);
        $ins = $pdo->prepare('INSERT INTO adm_storage_usage (empresa_adm_id, total_mb, fotos_mb, documentos_mb, outros_mb, calculado_em)
            VALUES (?,?,?,?,?,NOW())
            ON DUPLICATE KEY UPDATE total_mb = VALUES(total_mb), fotos_mb = VALUES(fotos_mb),
            documentos_mb = VALUES(documentos_mb), outros_mb = VALUES(outros_mb), calculado_em = NOW()');
        foreach ($rows as $eaId) {
            $eaId = (int) $eaId;
            $b = adm_storage_empresa_breakdown($eaId);
            $total = adm_storage_empresa_mb($eaId);
            $outros = max(0, $total - $b['fotos'] - $b['documentos'] - $b['os'] - $b['comprovantes']);
            $ins->execute([$eaId, $total, $b['fotos'], $b['documentos'], $outros]);
            $synced++;
        }
    } catch (Throwable $e) {
        error_log('adm_storage_sync_all: ' . $e->getMessage());
    }
    return ['synced' => $synced];
}

function adm_storage_cached_mb(PDO $pdo, int $empresaAdmId): ?int
{
    try {
        $st = $pdo->prepare('SELECT total_mb FROM adm_storage_usage WHERE empresa_adm_id = ? LIMIT 1');
        $st->execute([$empresaAdmId]);
        $v = $st->fetchColumn();
        return $v !== false ? (int) $v : null;
    } catch (Throwable $e) {
        return null;
    }
}
