<?php
require_once __DIR__ . '/includes/adm_auth.php';
require_once __DIR__ . '/includes/adm_paths.php';
sf_project_include('includes/sf_paths.php');

$page_title = 'Dashboard Executivo';
$adm_extra_scripts = '<script src="' . htmlspecialchars(sf_app_url('pages_adm/js/adm-charts.js?v=' . (function_exists('sf_asset_v') ? sf_asset_v() : '1'))) . '"></script>';

try {
    $stmt = $pdo->query("
        SELECT e.razao_social, e.status_saas, p.nome AS plano_nome,
               COUNT(CASE WHEN v.status_id IN (1, 2) THEN v.id END) AS total_veiculos,
               (COUNT(CASE WHEN v.status_id IN (1, 2) THEN v.id END) * e.valor_por_veiculo) AS valor_total
        FROM empresa_adm e
        LEFT JOIN empresa_clientes ec ON e.id = ec.empresa_adm_id
        LEFT JOIN veiculos v ON ec.id = v.empresa_id
        LEFT JOIN adm_planos p ON e.plano_id = p.id
        GROUP BY e.id, e.razao_social, e.status_saas, e.valor_por_veiculo, p.nome
        ORDER BY e.razao_social ASC LIMIT 50
    ");
    $empresas_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('pages_adm index empresas: ' . $e->getMessage());
    $empresas_recentes = [];
}

require_once __DIR__ . '/includes/layout_start.php';

$cc_title = 'Control Center';
$cc_subtitle = 'Visão executiva da plataforma Frotec em tempo real';
$cc_actions = '<span class="adm-cc-live"><span class="adm-cc-live__dot"></span> Ao vivo</span>';
include __DIR__ . '/components/page_header.php';
?>
<div class="adm-cc-page-stack">
<div class="adm-cc-dashboard adm-cc-skeleton" id="adm-dashboard">

<section class="adm-cc-section">
    <h2 class="adm-cc-section__title">Clientes</h2>
    <div class="adm-cc-kpi-grid">
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-building"></i></span><span class="adm-cc-kpi__label">Empresas ativas</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="kpi-empresas-ativas">—</div></div>
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-flask"></i></span><span class="adm-cc-kpi__label">Trial</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="kpi-trial">—</div></div>
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-ban"></i></span><span class="adm-cc-kpi__label">Bloqueadas</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="kpi-bloqueadas">—</div></div>
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-circle-xmark"></i></span><span class="adm-cc-kpi__label">Canceladas</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="kpi-canceladas">—</div></div>
    </div>
</section>

<section class="adm-cc-section">
    <h2 class="adm-cc-section__title">Financeiro</h2>
    <div class="adm-cc-kpi-grid">
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-coins"></i></span><span class="adm-cc-kpi__label">MRR</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="kpi-mrr">—</div></div>
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-chart-line"></i></span><span class="adm-cc-kpi__label">ARR</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="kpi-arr">—</div></div>
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-sun"></i></span><span class="adm-cc-kpi__label">Receita hoje</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="kpi-hoje">—</div></div>
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-file-invoice-dollar"></i></span><span class="adm-cc-kpi__label">A receber</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="kpi-receber">—</div></div>
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-triangle-exclamation"></i></span><span class="adm-cc-kpi__label">Inadimplentes</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="kpi-inadimplentes">—</div></div>
    </div>
</section>

<section class="adm-cc-section">
    <h2 class="adm-cc-section__title">Uso do sistema</h2>
    <div class="adm-cc-kpi-grid">
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-signal"></i></span><span class="adm-cc-kpi__label">Empresas online</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="kpi-emp-online">—</div></div>
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-user-check"></i></span><span class="adm-cc-kpi__label">Usuários online</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="kpi-user-online">—</div></div>
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-plug"></i></span><span class="adm-cc-kpi__label">Sessões ativas</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="kpi-sessoes">—</div></div>
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-right-to-bracket"></i></span><span class="adm-cc-kpi__label">Logins hoje</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="kpi-logins">—</div></div>
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-user-plus"></i></span><span class="adm-cc-kpi__label">Novos cadastros</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="kpi-novos">—</div></div>
    </div>
</section>

<section class="adm-cc-section">
    <h2 class="adm-cc-section__title">Infraestrutura</h2>
    <div class="adm-cc-kpi-grid">
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-microchip"></i></span><span class="adm-cc-kpi__label">CPU load</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="kpi-cpu">—</div></div>
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-memory"></i></span><span class="adm-cc-kpi__label">RAM</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="kpi-ram">—</div></div>
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-hard-drive"></i></span><span class="adm-cc-kpi__label">Disco</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="kpi-disco">—</div></div>
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-bolt"></i></span><span class="adm-cc-kpi__label">Cache HIT</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="kpi-cache">—</div></div>
        <div class="adm-cc-kpi"><div class="adm-cc-kpi__top"><span class="adm-cc-kpi__icon"><i class="fas fa-stopwatch"></i></span><span class="adm-cc-kpi__label">API média</span></div><div class="adm-cc-kpi__value adm-cc-skeleton-target" id="kpi-api">—</div></div>
    </div>
</section>

<div class="adm-cc-chart-wrap">
    <h2 class="adm-cc-section__title">Receita mensal (12 meses)</h2>
    <div class="adm-cc-chart-wrap__body">
        <canvas id="chart-receita"></canvas>
    </div>
</div>

</div>

<div class="adm-panel">
    <div class="adm-panel__head">
        <h2><i class="fas fa-building" style="color:var(--accent-primary,#3b82f6)"></i> Empresas</h2>
        <a href="empresas.php" class="adm-cc-btn">Gerenciar</a>
    </div>
    <div class="adm-panel__body adm-table-wrap">
        <table class="adm-table" id="tbl-empresas">
            <thead><tr><th>Razão social</th><th>Status</th><th>Plano</th><th>Veículos</th><th>MRR est.</th></tr></thead>
            <tbody>
                <?php if (empty($empresas_recentes)): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--text-secondary)">Nenhuma empresa cadastrada.</td></tr>
                <?php else: foreach ($empresas_recentes as $empresa): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string) $empresa['razao_social'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($empresa['status_saas'] ?? 'ativa'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($empresa['plano_nome'] ?? 'Sem plano'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo (int) ($empresa['total_veiculos'] ?? 0); ?></td>
                    <td><?php echo 'R$ ' . number_format((float) ($empresa['valor_total'] ?? 0), 2, ',', '.'); ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<?php
$adm_extra_scripts .= <<<'JS'
<script>
(function () {
    function apply(d) {
        AdmCC.skeleton(false);
        AdmCC.setText('kpi-empresas-ativas', AdmCC.formatNum(d.empresas_ativas));
        AdmCC.setText('kpi-trial', AdmCC.formatNum(d.empresas_trial));
        AdmCC.setText('kpi-bloqueadas', AdmCC.formatNum(d.empresas_bloqueadas));
        AdmCC.setText('kpi-canceladas', AdmCC.formatNum(d.empresas_canceladas));
        AdmCC.setText('kpi-mrr', AdmCC.formatBRL(d.mrr));
        AdmCC.setText('kpi-arr', AdmCC.formatBRL(d.arr));
        AdmCC.setText('kpi-hoje', AdmCC.formatBRL(d.receita_hoje));
        AdmCC.setText('kpi-receber', AdmCC.formatBRL(d.contas_receber));
        AdmCC.setText('kpi-inadimplentes', AdmCC.formatNum(d.clientes_inadimplentes));
        AdmCC.setText('kpi-emp-online', AdmCC.formatNum(d.empresas_online));
        AdmCC.setText('kpi-user-online', AdmCC.formatNum(d.usuarios_online));
        AdmCC.setText('kpi-sessoes', AdmCC.formatNum(d.sessoes_ativas));
        AdmCC.setText('kpi-logins', AdmCC.formatNum(d.logins_hoje));
        AdmCC.setText('kpi-novos', AdmCC.formatNum(d.novos_cadastros));
        AdmCC.setText('kpi-cpu', d.cpu_load != null ? d.cpu_load + '%' : '—');
        AdmCC.setText('kpi-ram', d.ram_pct != null ? d.ram_pct + '%' : '—');
        AdmCC.setText('kpi-disco', d.disco_pct != null ? d.disco_pct + '%' : '—');
        AdmCC.setText('kpi-cache', d.cache_hit_pct != null ? d.cache_hit_pct + '%' : '—');
        AdmCC.setText('kpi-api', d.api_avg_ms != null ? d.api_avg_ms + ' ms' : '—');
        if (d.receita_meses && window.AdmCharts) {
            var labels = d.receita_meses.map(function (x) { return x.mes; });
            var vals = d.receita_meses.map(function (x) { return x.total; });
            AdmCharts.line('chart-receita', labels, vals, 'Receita');
        }
    }
    AdmCC.poll('dashboard_metrics.php', apply, 45000);
})();
</script>
JS;
require_once __DIR__ . '/includes/layout_end.php';
