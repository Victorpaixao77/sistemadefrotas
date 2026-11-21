<?php
require_once __DIR__ . '/includes/adm_auth.php';
require_once __DIR__ . '/lib/adm_health.php';
require_once __DIR__ . '/lib/adm_deploy_prod.php';

adm_deploy_schema_ensure($pdo);

$page_title = 'Central de backup';
$backup = adm_health_backup();
$root = dirname(__DIR__, 2);
$files = array_merge(glob($root . '/sql/*backup*') ?: [], glob($root . '/logs/*backup*') ?: []);
$deployBackups = adm_deploy_backups_list($pdo, 0);
$dlApi = 'api/deploy_producao.php?action=backup_download';

require_once __DIR__ . '/includes/layout_start.php';
$cc_title = 'Central de backup';
$cc_subtitle = 'Visão geral — backups locais e de produção';
include __DIR__ . '/components/page_header.php';
?>
<p class="adm-dp-meta" style="margin-bottom:1rem;max-width:720px">
    Esta página mostra <strong>backups legados</strong> salvos em <code>/sql</code> e <code>/logs</code> do Control Center (XAMPP).
    Os backups de <strong>produção</strong> (antes do deploy) são gerados em
    <a href="deploy_producao.php">Deploy Produção</a> e ficam no VPS — use os botões <strong>SQL</strong> / <strong>Arquivos</strong> para baixar cópia no seu PC.
</p>
<div class="adm-cc-kpi-grid">
    <div class="adm-cc-kpi"><div class="adm-cc-kpi__label">Status (local)</div><div class="adm-cc-kpi__value"><?php echo htmlspecialchars($backup['detalhe'], ENT_QUOTES, 'UTF-8'); ?></div></div>
    <div class="adm-cc-kpi"><div class="adm-cc-kpi__label">Arquivos em /sql e /logs</div><div class="adm-cc-kpi__value"><?php echo count($files); ?></div></div>
    <div class="adm-cc-kpi"><div class="adm-cc-kpi__label">Backups de produção (deploy)</div><div class="adm-cc-kpi__value"><?php echo count($deployBackups); ?></div></div>
</div>

<div class="adm-panel" style="margin-top:1rem">
    <div class="adm-panel__head"><h2>Backups de produção (Deploy)</h2></div>
    <div class="adm-panel__body adm-table-wrap">
        <table class="adm-table">
            <thead><tr><th>#</th><th>Servidor</th><th>Data</th><th>Tamanho</th><th>Status</th><th>Download</th></tr></thead>
            <tbody>
            <?php if (!$deployBackups): ?>
                <tr><td colspan="6">Nenhum backup de deploy. Gere em <a href="deploy_producao.php">Deploy Produção</a> → card Backup.</td></tr>
            <?php else: foreach ($deployBackups as $b):
                $mb = $b['size_bytes'] ? number_format($b['size_bytes'] / 1024 / 1024, 2) . ' MB' : '—';
            ?>
                <tr>
                    <td><?php echo (int) $b['id']; ?></td>
                    <td><?php echo htmlspecialchars((string) ($b['server_nome'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) $b['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo $mb; ?></td>
                    <td><?php echo htmlspecialchars((string) $b['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <a class="adm-cc-btn adm-cc-btn--ghost adm-cc-btn--sm" href="<?php echo htmlspecialchars($dlApi . '&backup_id=' . (int) $b['id'] . '&file=database', ENT_QUOTES, 'UTF-8'); ?>">SQL</a>
                        <a class="adm-cc-btn adm-cc-btn--ghost adm-cc-btn--sm" href="<?php echo htmlspecialchars($dlApi . '&backup_id=' . (int) $b['id'] . '&file=files', ENT_QUOTES, 'UTF-8'); ?>">Arquivos</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="adm-panel" style="margin-top:1rem">
    <div class="adm-panel__head"><h2>Arquivos locais (/sql, /logs)</h2></div>
    <div class="adm-panel__body adm-table-wrap">
        <table class="adm-table"><thead><tr><th>Arquivo</th><th>Tamanho</th><th>Modificado</th></tr></thead><tbody>
        <?php foreach ($files as $f): ?>
        <tr><td><?php echo htmlspecialchars(basename($f), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo number_format(filesize($f) / 1024 / 1024, 2); ?> MB</td><td><?php echo date('d/m/Y H:i', filemtime($f)); ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$files): ?><tr><td colspan="3">Nenhum arquivo de backup encontrado em /sql ou /logs.</td></tr><?php endif; ?>
        </tbody></table>
    </div>
</div>
<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
