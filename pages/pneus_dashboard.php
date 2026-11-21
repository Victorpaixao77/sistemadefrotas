
﻿<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/../includes/sf_page_help.php';

configure_session();
session_start();

require_authentication();
require_once '../includes/permissions.php';
require_permission('access_tire_management');

require_once '../includes/db_connect.php';
require_once '../includes/pneus_repository.php';

$conn = getConnection();
$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
$dash_data = [];
if ($empresa_id > 0) {
    try {
        $dash_data = pneus_get_executive($conn, $empresa_id);
    } catch (Throwable $e) {
        error_log('pneus_dashboard SSR: ' . $e->getMessage());
    }
}
$dash_summary = $dash_data['summary'] ?? [];
$dash_fin = $dash_data['financeiro'] ?? [];

$page_title = 'Dashboard de Pneus';
$is_modern = !isset($_GET['classic']) || (string) $_GET['classic'] !== '1';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <?php if ($is_modern): ?>
    <link rel="stylesheet" href="../css/fornc-modern-page.css">
    <?php endif; ?>
    <link rel="stylesheet" href="../css/pneus_timeline.css">
    <link rel="icon" type="image/png" href="../logo.png">
    <?php sf_page_help_css('..'); ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
    <script src="../js/sf-charts-premium.js"></script>
    <style>
        .pneus-dash-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin: 1.25rem 0; }
        .pneus-dash-card { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; }
        .pneus-dash-card h3 { margin: 0 0 0.75rem; font-size: 0.95rem; color: var(--text-color); }
        .pneus-dash-card canvas { max-height: 260px; }
        .pneus-dash-table { width: 100%; font-size: 0.85rem; border-collapse: collapse; }
        .pneus-dash-table th, .pneus-dash-table td { padding: 0.5rem; border-bottom: 1px solid var(--border-color); text-align: left; }
        .pneus-dash-loading { padding: 2rem; text-align: center; color: var(--text-secondary); }
        body.pneus-dash-modern .dashboard-header h1 { display: none; }
    </style>
</head>
<body class="<?php echo $is_modern ? 'pneus-dash-modern' : ''; ?>">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content<?php echo $is_modern ? ' fornc-page' : ''; ?>">
                <?php if ($is_modern): ?>
                <div class="fornc-kpi-strip" id="pneusDashKpis">
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-circle-dot fornc-kpi-icon"></i><span class="lbl">Total</span></div><span class="val" id="kpiTotal"><?php echo (int) ($dash_summary['total_pneus'] ?? 0); ?></span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-truck-moving fornc-kpi-icon"></i><span class="lbl">Em uso</span></div><span class="val" id="kpiEmUso"><?php echo (int) ($dash_summary['em_uso'] ?? 0); ?></span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-warehouse fornc-kpi-icon"></i><span class="lbl">Estoque</span></div><span class="val" id="kpiEstoque"><?php echo (int) ($dash_summary['estoque_disponivel'] ?? 0); ?></span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-triangle-exclamation fornc-kpi-icon"></i><span class="lbl">Alerta sulco</span></div><span class="val" id="kpiAlerta"><?php echo (int) ($dash_summary['alerta_sulco'] ?? 0); ?></span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-coins fornc-kpi-icon"></i><span class="lbl">Custo/km médio</span></div><span class="val" id="kpiCustoKm"><?php echo isset($dash_summary['custo_km_medio_frota']) && $dash_summary['custo_km_medio_frota'] !== null ? 'R$ ' . number_format((float) $dash_summary['custo_km_medio_frota'], 4, ',', '.') . '/km' : '—'; ?></span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-wallet fornc-kpi-icon"></i><span class="lbl">Investimento</span></div><span class="val" id="kpiInvest"><?php echo isset($dash_fin['custo_vida_total']) ? 'R$ ' . number_format((float) $dash_fin['custo_vida_total'], 2, ',', '.') : '—'; ?></span></div>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin:12px 0">
                    <a class="btn btn-secondary btn-sm" href="../api/pneus_export.php?tipo=estoque">Exportar estoque</a>
                    <a class="btn btn-secondary btn-sm" href="../api/pneus_export.php?tipo=movimentacoes">Movimentações</a>
                    <a class="btn btn-secondary btn-sm" href="../api/pneus_export.php?tipo=custos">Custos</a>
                    <a class="btn btn-secondary btn-sm" href="../api/pneus_export.php?tipo=sulcos">Sulcos</a>
                    <a class="btn btn-secondary btn-sm" href="../api/pneus_export.php?tipo=marcas">Ranking marcas</a>
                    <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                </div>
                <div id="pneusIaDashMount"></div>
                <div id="sfPneusDashInsights"></div>
                <?php else: ?>
                <div class="dashboard-header">
                    <h1><?php echo htmlspecialchars($page_title); ?></h1>
                    <div class="dashboard-actions">
                        <button type="button" class="btn-help" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>
                <?php endif; ?>

                <div id="pneusDashLoading" class="pneus-dash-loading" style="display:none;"><i class="fas fa-spinner fa-spin"></i> Carregando indicadores…</div>
                <div id="pneusDashContent" style="display:block;">
                    <div class="pneus-dash-grid">
                        <div class="pneus-dash-card"><h3><i class="fas fa-chart-line"></i> Custo de manutenção (12 meses)</h3><canvas id="chartCustoMensal"></canvas></div>
                        <div class="pneus-dash-card"><h3><i class="fas fa-chart-pie"></i> Pneus por status</h3><canvas id="chartPorStatus"></canvas></div>
                    </div>
                    <div class="pneus-dash-grid">
                        <div class="pneus-dash-card">
                            <h3><i class="fas fa-truck"></i> Top veículos — custo manutenção (6 meses)</h3>
                            <div id="tableTopVeiculos"></div>
                        </div>
                        <div class="pneus-dash-card">
                            <h3><i class="fas fa-shopping-cart"></i> Previsão de compras (90 dias)</h3>
                            <div id="tablePrevisao"></div>
                        </div>
                    </div>
                    <div class="pneus-dash-card">
                        <h3><i class="fas fa-recycle"></i> Ranking recapagem por fornecedor</h3>
                        <div id="tableRecapagem"></div>
                    </div>
                    <div class="pneus-dash-card">
                        <h3><i class="fas fa-industry"></i> Ranking de marcas (KM médio)</h3>
                        <div id="tableMarcas"></div>
                    </div>
                    <div class="pneus-dash-grid">
                        <div class="pneus-dash-card">
                            <h3><i class="fas fa-calendar-day"></i> Previsão de compra (60 dias — IA)</h3>
                            <div id="previsaoCompra60"></div>
                        </div>
                        <div class="pneus-dash-card">
                            <h3><i class="fas fa-certificate"></i> Alertas DOT</h3>
                            <div id="dotAlertas"></div>
                        </div>
                        <div class="pneus-dash-card">
                            <h3><i class="fas fa-gas-pump"></i> Consumo × desgaste (abastecimento)</h3>
                            <div id="abastInsights"></div>
                        </div>
                        <div class="pneus-dash-card">
                            <h3><i class="fas fa-chart-bar"></i> Motivos de remoção</h3>
                            <div id="statsRemocao"></div>
                        </div>
                    </div>
                    <div class="pneus-dash-card">
                        <h3><i class="fas fa-chart-pie"></i> Resumo financeiro</h3>
                        <div id="financeiroResumo"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/sf_insights.js?v=1.3"></script>
    <script src="../js/ia_pneus.js"></script>
    <script src="../js/ia_pneus_avancado.js?v=1.1"></script>
    <script>window.__PNEUS_DASH_INITIAL__=<?php echo json_encode(['success' => true, 'data' => $dash_data], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
    <script>
    (function () {
        let chartCusto = null;
        let chartStatus = null;
        let _dashChartsDone = false;

        function fmtBrl(v) {
            return 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
        }

        function renderTable(elId, cols, rows, emptyMsg) {
            const el = document.getElementById(elId);
            if (!rows || !rows.length) {
                el.innerHTML = '<p class="detail-text">' + emptyMsg + '</p>';
                return;
            }
            let html = '<table class="pneus-dash-table"><thead><tr>';
            cols.forEach(function (c) { html += '<th>' + c.label + '</th>'; });
            html += '</tr></thead><tbody>';
            rows.forEach(function (r) {
                html += '<tr>';
                cols.forEach(function (c) {
                    html += '<td>' + (c.render ? c.render(r) : (r[c.key] ?? '—')) + '</td>';
                });
                html += '</tr>';
            });
            html += '</tbody></table>';
            el.innerHTML = html;
        }

        function applyPneusDashData(data) {
            if (!data) return;
            const s = data.summary || {};
            const elTotal = document.getElementById('kpiTotal');
            if (elTotal) elTotal.textContent = s.total_pneus ?? 0;
            const elUso = document.getElementById('kpiEmUso');
            if (elUso) elUso.textContent = s.em_uso ?? 0;
            const elEst = document.getElementById('kpiEstoque');
            if (elEst) elEst.textContent = s.estoque_disponivel ?? 0;
            const elAlerta = document.getElementById('kpiAlerta');
            if (elAlerta) elAlerta.textContent = s.alerta_sulco ?? 0;
            const elCustoKm = document.getElementById('kpiCustoKm');
            if (elCustoKm) elCustoKm.textContent = s.custo_km_medio_frota != null
                ? ('R$ ' + Number(s.custo_km_medio_frota).toFixed(4) + '/km') : '—';
            const fin = data.financeiro || {};
            const kpiInv = document.getElementById('kpiInvest');
            if (kpiInv) kpiInv.textContent = fin.custo_vida_total != null ? fmtBrl(fin.custo_vida_total) : '—';

            const finEl = document.getElementById('financeiroResumo');
            if (finEl) {
                finEl.innerHTML = '<div class="pneu-ficha-grid">' +
                    '<div class="pneu-ficha-kpi"><div class="lbl">Compra (cadastro)</div><div class="val">' + fmtBrl(fin.investimento_compra) + '</div></div>' +
                    '<div class="pneu-ficha-kpi"><div class="lbl">Manutenções</div><div class="val">' + fmtBrl(fin.custo_manutencao_total) + '</div></div>' +
                    '<div class="pneu-ficha-kpi"><div class="lbl">Recapagens</div><div class="val">' + fmtBrl(fin.custo_recapagens) + '</div></div>' +
                    '<div class="pneu-ficha-kpi"><div class="lbl">Custo vida total</div><div class="val">' + fmtBrl(fin.custo_vida_total) + '</div></div></div>';
            }

            const custo = data.custo_mensal || [];
            const ctx1 = document.getElementById('chartCustoMensal');
            if (ctx1) {
                if (chartCusto) chartCusto.destroy();
                chartCusto = new Chart(ctx1.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: custo.map(function (x) { return x.mes; }),
                        datasets: [{ label: 'Custo (R$)', data: custo.map(function (x) { return x.custo; }), backgroundColor: 'rgba(54, 162, 235, 0.6)' }]
                    },
                    options: { responsive: true, plugins: { legend: { display: false } } }
                });
            }

            const st = data.por_status || [];
            const ctx2 = document.getElementById('chartPorStatus');
            if (ctx2) {
                if (chartStatus) chartStatus.destroy();
                chartStatus = new Chart(ctx2.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: st.map(function (x) { return x.status || 'Sem status'; }),
                        datasets: [{ data: st.map(function (x) { return x.qtd; }) }]
                    },
                    options: { responsive: true }
                });
            }

            renderTable('tableTopVeiculos', [
                { key: 'placa', label: 'Placa' },
                { key: 'pneus_manut', label: 'Pneus' },
                { key: 'custo', label: 'Custo', render: function (r) { return fmtBrl(r.custo); } }
            ], data.top_veiculos_custo, 'Sem manutenções nos últimos 6 meses.');

            renderTable('tablePrevisao', [
                { key: 'medida', label: 'Medida' },
                { key: 'disponiveis', label: 'Disp.' },
                { key: 'criticos_ou_descarte', label: 'Críticos' },
                { key: 'sugestao_compra', label: 'Sugestão compra' }
            ], data.previsao_compras, 'Nenhuma reposição sugerida no horizonte de 90 dias.');

            renderTable('tableRecapagem', [
                { key: 'fornecedor', label: 'Fornecedor' },
                { key: 'qtd', label: 'Qtd.' },
                { key: 'custo_medio', label: 'Custo médio', render: function (r) { return fmtBrl(r.custo_medio); } },
                { key: 'custo_total', label: 'Total', render: function (r) { return fmtBrl(r.custo_total); } }
            ], data.ranking_recapagem, 'Sem recapagens registradas.');

            renderTable('tableMarcas', [
                { key: 'marca', label: 'Marca' },
                { key: 'total_pneus', label: 'Pneus' },
                { key: 'km_medio', label: 'KM médio', render: function (r) { return Number(r.km_medio || 0).toLocaleString('pt-BR'); } },
                { key: 'valor_medio_compra', label: 'Valor médio', render: function (r) { return r.valor_medio_compra ? fmtBrl(r.valor_medio_compra) : '—'; } }
            ], data.ranking_marcas, 'Sem dados de marcas.');
        }

        function schedulePneusDashCharts(data) {
            if (_dashChartsDone) return;
            const section = document.getElementById('pneusDashContent');
            const load = function () {
                _dashChartsDone = true;
                applyPneusDashData(data);
            };
            if (!section || !('IntersectionObserver' in window)) {
                load();
                return;
            }
            const io = new IntersectionObserver(function (entries) {
                if (entries.some(function (e) { return e.isIntersecting; })) {
                    load();
                    io.disconnect();
                }
            }, { rootMargin: '120px' });
            io.observe(section);
        }

        function escapeHtml(s) {
            return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
        }

        async function loadMercadoExtras() {
            try {
                const rPrev = await fetch('../api/pneus_mercado.php?action=previsao_compra&dias=60');
                const jPrev = await rPrev.json();
                const elP = document.getElementById('previsaoCompra60');
                if (elP && jPrev.success && jPrev.previsao) {
                    elP.innerHTML = '<p><strong>' + escapeHtml(jPrev.previsao.mensagem_ia) + '</strong></p>';
                    if (jPrev.previsao.detalhes_medida && jPrev.previsao.detalhes_medida.length) {
                        elP.innerHTML += '<ul>' + jPrev.previsao.detalhes_medida.map(function (d) {
                            return '<li>' + escapeHtml(d) + '</li>';
                        }).join('') + '</ul>';
                    }
                }
                const rDot = await fetch('../api/pneus_mercado.php?action=dot_alertas');
                const jDot = await rDot.json();
                if (jDot.success) {
                    const a = jDot.alertas || {};
                    const rows = [].concat(a.vencido || [], a.atencao_5anos || []);
                    renderTable('dotAlertas', [
                        { key: 'numero_serie', label: 'Série' },
                        { key: 'dot', label: 'DOT' },
                        { key: 'idade_anos', label: 'Anos' }
                    ], rows, 'Nenhum alerta DOT no momento.');
                }
                const rAb = await fetch('../api/pneus_mercado.php?action=abastecimento_insights');
                const jAb = await rAb.json();
                if (jAb.success) {
                    renderTable('abastInsights', [
                        { key: 'placa', label: 'Placa' },
                        { key: 'consumo_km_l', label: 'km/L' },
                        { key: 'sulco_medio_mm', label: 'Sulco médio' },
                        { key: 'alertas', label: 'Alerta', render: function (r) {
                            return (r.alertas || []).join('; ');
                        }}
                    ], jAb.insights || [], 'Sem correlações detectadas.');
                }
                const rSt = await fetch('../api/pneus_mercado.php?action=stats_remocao');
                const jSt = await rSt.json();
                if (jSt.success) {
                    renderTable('statsRemocao', [
                        { key: 'nome', label: 'Motivo' },
                        { key: 'total', label: 'Qtd.' }
                    ], jSt.estatisticas || [], 'Sem remoções com motivo registrado.');
                }
            } catch (mercErr) {
                console.warn('Mercado dashboard', mercErr);
            }
        }

        document.addEventListener('DOMContentLoaded', async function () {
            if (typeof mountPneusIAPanel === 'function') {
                mountPneusIAPanel('pneusIaDashMount');
            }
            if (window.SFInsights) {
                SFInsights.init({
                    mountId: 'sfPneusDashInsights',
                    insightsUrl: '../api/pneus_insights.php',
                    resumoUrl: '../api/pneus_resumo_ia.php',
                    title: 'Insights de pneus',
                    sectionTitle: 'Alertas da frota',
                    emptyOk: 'Nenhum alerta relevante no momento.',
                    stats: function (j) {
                        var s = j.summary || {};
                        return [
                            { label: 'em uso', value: s.em_uso || 0, tone: 'info' },
                            { label: 'estoque', value: s.estoque_disponivel || 0, tone: 'ok' },
                            { label: 'alerta sulco', value: s.alerta_sulco || 0, tone: s.alerta_sulco > 0 ? 'erro' : 'ok' }
                        ];
                    }
                });
            }

            const initial = (typeof window.__PNEUS_DASH_INITIAL__ === 'object') ? window.__PNEUS_DASH_INITIAL__ : null;
            if (initial && initial.success && initial.data && Object.keys(initial.data).length) {
                applyPneusDashData(initial.data);
            } else {
                try {
                    const res = await fetch('../api/pneus.php?action=executive');
                    const json = await res.json();
                    if (json.success && json.data) {
                        applyPneusDashData(json.data);
                    }
                } catch (e) {
                    console.error(e);
                    const loading = document.getElementById('pneusDashLoading');
                    const content = document.getElementById('pneusDashContent');
                    if (loading) {
                        loading.style.display = 'block';
                        loading.textContent = 'Erro ao carregar dashboard. Tente recarregar a página.';
                    }
                    if (content) content.style.display = 'none';
                }
            }

            loadMercadoExtras();
        });
    })();
    </script>
    <?php $sf_help_key = 'pneus_dashboard'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
