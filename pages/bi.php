<?php
/**
 * Indicadores de Desempenho - Página BI (teste)
 * Resumos gráficos editáveis, foco em rotas. Estilo profissional tipo BI.
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_once '../includes/permissions.php';

configure_session();
session_start();
require_authentication();

// Mesma permissão dos relatórios avançados
if (function_exists('require_permission')) {
    require_permission('access_advanced_reports');
}

$page_title = 'BI Frota';
$empresa_id = $_SESSION['empresa_id'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo htmlspecialchars($page_title); ?></title>
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <link rel="icon" type="image/png" href="../logo.png">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Conteúdo BI respeitando a largura da tela (sidebar aberta/fechada) */
        .main-content { min-width: 0; overflow-x: hidden; }
        .dashboard-content { min-width: 0; max-width: 100%; overflow-x: hidden; box-sizing: border-box; }
        .bi-layout { min-width: 0; max-width: 100%; box-sizing: border-box; }
        .bi-content { min-width: 0; max-width: 100%; overflow-x: hidden; box-sizing: border-box; }
        .bi-indicadores-section { min-width: 0; max-width: 100%; box-sizing: border-box; }
        .bi-indicadores-box { width: 100%; max-width: 100%; box-sizing: border-box; }
        #indicatorsTableContainer { min-width: 0; width: 100%; }
        .bi-extra-block { min-width: 0; max-width: 100%; overflow-x: auto; box-sizing: border-box; }
        .bi-table-card { min-width: 0; max-width: 100%; box-sizing: border-box; }

        /* BI usa as variáveis do sistema (--bg-primary, --card-bg, etc.) */
        .bi-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
        .bi-header h1 { font-size: 1.5rem; font-weight: 600; margin: 0; color: var(--text-primary); }
        .bi-filtros { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
        .bi-filtros .bi-filtro-item { display: flex; align-items: center; gap: 0.35rem; }
        .bi-filtros .bi-filtro-item label { font-size: 0.8rem; color: var(--text-muted); margin: 0; white-space: nowrap; }
        .bi-filtros select { padding: 0.45rem 0.6rem; border-radius: var(--btn-border-radius); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); font-size: 0.9rem; min-width: 0; }
        .bi-filtros #filtro-visao { min-width: 180px; }
        .bi-filtros #filtro-ano { width: 4.5rem; }
        .bi-filtros #filtro-mes { width: 5rem; }
        .bi-filtros #btn-aplicar { padding: 0.45rem 0.9rem; border-radius: var(--btn-border-radius); border: none; background: var(--accent-primary); color: #fff; font-weight: 600; cursor: pointer; font-size: 0.9rem; white-space: nowrap; }
        .bi-filtros #btn-aplicar:hover { filter: brightness(1.1); }
        .bi-layout { display: block; }
        .bi-content { max-width: 100%; }
        .bi-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .bi-kpi { background: var(--card-bg); border-radius: var(--card-border-radius); padding: 1rem; border: 1px solid var(--border-color); text-align: center; }
        .bi-kpi .bi-kpi-value { font-size: 1.4rem; font-weight: 700; color: var(--accent-primary); }
        .bi-kpi .bi-kpi-label { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; }
        .bi-kpi .bi-kpi-variacoes { margin-top: 0.2rem; min-height: 1.2em; }
        .bi-kpi i { font-size: 1.2rem; color: var(--text-muted); margin-bottom: 0.25rem; }
        .bi-charts { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
        @media (max-width: 900px) { .bi-charts { grid-template-columns: 1fr; } }
        .bi-chart-card { background: var(--card-bg); border-radius: var(--card-border-radius); padding: 1rem; border: 1px solid var(--border-color); }
        .bi-chart-card h3 { font-size: 0.95rem; margin: 0 0 1rem 0; color: var(--text-primary); }
        .bi-chart-card canvas { max-height: 220px; }
        .bi-chart-full { grid-column: 1 / -1; }
        .bi-table-card { background: var(--card-bg); border-radius: var(--card-border-radius); padding: 1rem; border: 1px solid var(--border-color); overflow-x: auto; }
        .bi-table-card h3 { font-size: 0.95rem; margin: 0 0 1rem 0; color: var(--text-primary); }
        .bi-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .bi-table th, .bi-table td { padding: 0.6rem 0.75rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        .bi-table th { color: var(--text-muted); font-weight: 600; }
        .bi-table tr:hover { background: var(--bg-tertiary); }
        .bi-loading { text-align: center; padding: 2rem; color: var(--text-muted); }
        .bi-error { background: rgba(239,68,68,0.15); color: var(--accent-danger); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }

        /* Indicadores de Desempenho (tela dedicada no BI) */
        .bi-indicadores-section { margin-top: 0; }
        .bi-indicadores-section h2 { font-size: 1.25rem; margin: 0 0 1rem 0; color: var(--text-primary); }
        .bi-indicadores-box { background: var(--bg-secondary); border-radius: 8px; padding: 20px; border: 1px solid var(--border-color); overflow-x: auto; max-width: 100%; box-sizing: border-box; }
        .bi-indicadores-actions { display: flex; justify-content: flex-end; align-items: center; margin-bottom: 20px; gap: 10px; }
        .btn-action-indicators { padding: 8px 15px; background: #007bff; border: none; border-radius: 4px; cursor: pointer; color: white; white-space: nowrap; transition: all 0.2s ease; }
        .btn-action-indicators:hover { transform: translateY(-2px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn-action-indicators:last-of-type { background: #28a745; }
        .indicators-table { font-size: 0.9rem; table-layout: auto; width: 100%; border-collapse: collapse; min-width: 1200px; max-width: none; }
        .indicators-table th { background: var(--bg-tertiary); color: var(--text-primary); font-weight: 600; padding: 12px 8px; text-align: center; border: 1px solid var(--border-color); white-space: nowrap; }
        .indicators-table td { padding: 12px 8px; border: 1px solid var(--border-color); text-align: right; background: var(--bg-secondary); white-space: nowrap; }
        .indicators-table tbody tr:hover { background: var(--bg-tertiary); }
        .indicators-table tbody tr td:first-child { position: sticky; left: 0; background: var(--bg-secondary); z-index: 5; text-align: left; font-weight: 500; min-width: 150px; max-width: 250px; white-space: nowrap; }
        .indicators-table tbody tr:hover td:first-child { background: var(--bg-tertiary); }
        #indicatorsTableContainer { max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .indicator-name { font-weight: 500; color: var(--text-primary); }
        .indicator-help { cursor: help; color: var(--text-secondary); font-size: 0.7rem; opacity: 0.6; transition: opacity 0.2s, color 0.2s; display: inline-flex; align-items: center; justify-content: center; font-weight: normal; position: relative; margin-left: 2px; }
        .indicator-help:hover { opacity: 1; color: var(--accent-primary); }
        .indicator-help-tooltip { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); background: var(--bg-tertiary); color: var(--text-primary); padding: 8px 12px; border-radius: 6px; font-size: 0.85rem; white-space: normal; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.3); border: 1px solid var(--border-color); display: none; max-width: 300px; line-height: 1.4; }
        .indicator-help:hover .indicator-help-tooltip, .indicator-help.active .indicator-help-tooltip { display: block; }
        .cell-value { display: flex; align-items: center; justify-content: flex-end; gap: 5px; flex-wrap: wrap; }
        .value-number { font-weight: 600; color: var(--text-primary); }
        .variation { font-size: 0.85rem; padding: 2px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 3px; }
        .variation.positive { background: rgba(76, 175, 80, 0.1); color: #4caf50; }
        .variation.negative { background: rgba(244, 67, 54, 0.1); color: #f44336; }
        .variation.neutral { background: rgba(158, 158, 158, 0.1); color: #9e9e9e; }

        /* Indicadores de Saúde da Frota (só na Visão Geral) */
        .bi-saude-frota { margin-bottom: 1.5rem; }
        .bi-saude-frota h3 { font-size: 0.9rem; color: var(--text-muted); margin: 0 0 0.75rem 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; }
        .bi-saude-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1rem; }
        @media (max-width: 900px) { .bi-saude-cards { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 500px) { .bi-saude-cards { grid-template-columns: 1fr; } }
        .bi-saude-card { background: var(--card-bg); border-radius: var(--card-border-radius); padding: 1rem; border: 1px solid var(--border-color); text-align: center; }
        .bi-saude-card .bi-saude-value { font-size: 1.35rem; font-weight: 700; color: var(--accent-primary); }
        .bi-saude-card .bi-saude-label { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; line-height: 1.2; }
        .bi-saude-card.negative .bi-saude-value { color: #f44336; }
        .bi-semaforo-wrap { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; background: var(--card-bg); border-radius: var(--card-border-radius); padding: 0.75rem 1rem; border: 1px solid var(--border-color); }
        .bi-semaforo-wrap .bi-semaforo-label { font-size: 0.85rem; color: var(--text-muted); margin-right: 0.5rem; }
        .bi-semaforo { display: flex; align-items: center; gap: 0.5rem; }
        .bi-semaforo-dot { width: 14px; height: 14px; border-radius: 50%; }
        .bi-semaforo-dot.verde { background: #22c55e; box-shadow: 0 0 0 2px rgba(34,197,94,0.3); }
        .bi-semaforo-dot.amarelo { background: #eab308; box-shadow: 0 0 0 2px rgba(234,179,8,0.3); }
        .bi-semaforo-dot.vermelho { background: #ef4444; box-shadow: 0 0 0 2px rgba(239,68,68,0.3); }
        .bi-semaforo-texto { font-size: 0.9rem; font-weight: 600; }
        .bi-semaforo-texto.verde { color: #22c55e; }
        .bi-semaforo-texto.amarelo { color: #eab308; }
        .bi-semaforo-texto.vermelho { color: #ef4444; }
        .bi-score-frota-wrap { background: linear-gradient(135deg, var(--card-bg) 0%, var(--bg-secondary) 100%); border-radius: var(--card-border-radius); padding: 1rem 1.25rem; border: 1px solid var(--border-color); display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .bi-score-frota-num { font-size: 2rem; font-weight: 800; line-height: 1; }
        .bi-score-frota-num.bom { color: #22c55e; }
        .bi-score-frota-num.medio { color: #eab308; }
        .bi-score-frota-num.baixo { color: #ef4444; }
        .bi-score-frota-label { font-size: 0.9rem; color: var(--text-muted); }
        .bi-score-frota-desc { font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.25rem; }
        .bi-extra-block h3 { font-size: 0.95rem; margin: 0 0 0.75rem 0; color: var(--text-primary); }
        .bi-extra-block h4 { font-size: 0.85rem; color: var(--text-muted); margin: 0.5rem 0; }
        .bi-text-muted { color: var(--text-muted); font-size: 0.9rem; margin: 0; }
        .bi-alertas { font-size: 0.9rem; color: var(--text-primary); }
        .bi-aviso-incompletos { background: rgba(245,158,11,0.15); border: 1px solid #f59e0b; border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.9rem; color: var(--text-primary); }
        .bi-aviso-incompletos a { color: var(--accent-primary); }
        .bi-ponto-equilibrio { background: var(--bg-secondary); border-radius: 8px; padding: 1rem; border: 1px solid var(--border-color); margin-top: 1rem; }
        .bi-ponto-equilibrio h4 { font-size: 0.95rem; margin: 0 0 0.5rem 0; color: var(--text-primary); }
        .bi-tendencias { margin-top: 0.5rem; font-size: 0.85rem; color: var(--text-secondary); }
        .bi-simulador-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 0.75rem; }
        @media (max-width: 600px) { .bi-simulador-grid { grid-template-columns: 1fr; } }
        .bi-simulador-field { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
        .bi-simulador-field label { font-size: 0.9rem; color: var(--text-muted); min-width: 140px; }
        .bi-simulador-field input { width: 80px; padding: 0.4rem; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); }
        .bi-simulador-result { margin-top: 1rem; padding: 1rem; background: var(--bg-tertiary); border-radius: 8px; font-size: 0.95rem; }
        .bi-simulador-result .impacto { font-weight: 700; font-size: 1.05rem; }
        .bi-custo-km-veiculos { overflow-x: auto; margin-top: 0.5rem; }
        .bi-table-total { background: var(--bg-tertiary); font-weight: 600; }
        .bi-table-total td { border-top: 2px solid var(--border-color); }
        #btn-mes-atual { padding: 0.45rem 0.9rem; border-radius: var(--btn-border-radius); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); cursor: pointer; font-size: 0.9rem; white-space: nowrap; }
        #btn-mes-atual:hover { filter: brightness(0.95); }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include __DIR__ . '/../includes/sidebar_pages.php'; ?>
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include __DIR__ . '/../includes/header.php'; ?>
            <!-- Page Content -->
            <div class="dashboard-content">
                <div class="bi-header">
                    <h1><i class="fas fa-chart-line"></i> BI Frota</h1>
                    <div class="bi-filtros">
                        <div class="bi-filtro-item">
                            <label for="filtro-visao">Visão</label>
                            <select id="filtro-visao">
                                <option value="geral" selected>Geral</option>
                                <option value="indicadores_desempenho">Indicadores de Desempenho</option>
                                <option value="rotas">Rotas</option>
                                <option value="abastecimento">Abastecimento</option>
                                <option value="manutencao">Manutenção</option>
                                <option value="despesas_viagem">Despesas de viagem</option>
                                <option value="despesas_fixas">Despesas fixas</option>
                            </select>
                        </div>
                        <div class="bi-filtro-item">
                            <label for="filtro-ano">Ano</label>
                            <select id="filtro-ano">
                                <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--) { ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y == date('Y') ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="bi-filtro-item">
                            <label for="filtro-mes">Mês</label>
                            <select id="filtro-mes">
                                <option value="">Todos</option>
                                <?php for ($m = 1; $m <= 12; $m++) { ?>
                                    <option value="<?php echo $m; ?>"><?php echo date('M', mktime(0,0,0,$m,1)); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <button type="button" id="btn-aplicar"><i class="fas fa-sync-alt"></i> Aplicar</button>
                        <button type="button" id="btn-mes-atual" title="Mês atual"><i class="fas fa-calendar-day"></i> Mês atual</button>
                    </div>
                </div>

                <div class="bi-layout">
                    <div class="bi-content">
                        <div id="bi-loading" class="bi-loading"><i class="fas fa-spinner fa-spin"></i> Carregando indicadores...</div>
                        <div id="bi-error" class="bi-error" style="display:none;"></div>
                        <div id="bi-aviso-incompletos" class="bi-aviso-incompletos" style="display:none;"></div>

                        <div id="bi-saude-frota" class="bi-saude-frota" style="display:none;">
                            <h3><i class="fas fa-heartbeat"></i> Indicadores de Saúde da Frota</h3>
                            <div id="bi-score-frota-wrap" class="bi-score-frota-wrap" style="display:none; margin-bottom: 1rem;"></div>
                            <div class="bi-saude-cards" id="bi-saude-cards"></div>
                            <div class="bi-semaforo-wrap" id="bi-semaforo-wrap">
                                <span class="bi-semaforo-label">Desempenho (margem operacional):</span>
                                <div class="bi-semaforo" id="bi-semaforo"></div>
                                <span class="bi-semaforo-texto" id="bi-semaforo-texto"></span>
                            </div>
                        </div>
                        <div id="bi-kpis" class="bi-kpis" style="display:none;"></div>
                        <div id="bi-charts" class="bi-charts" style="display:none;">
                        <div class="bi-chart-card bi-chart-full" id="wrapChartRotasTempo">
                            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.5rem;">
                                <h3 id="tituloChartRotasTempo" style="margin:0;">Rotas e Faturamento ao longo do tempo</h3>
                                <button type="button" id="btn-exportar-grafico-png" class="btn-action-indicators" style="background:#6c757d; font-size:0.85rem;"><i class="fas fa-image"></i> Exportar PNG</button>
                            </div>
                            <canvas id="chartRotasTempo"></canvas>
                        </div>
                        <div class="bi-chart-card" id="wrapChartFreteMensal">
                            <h3 id="tituloChartFreteMensal">Faturamento por mês (Frete)</h3>
                            <canvas id="chartFreteMensal"></canvas>
                        </div>
                        <div class="bi-chart-card" id="wrapChartKmMensal">
                            <h3 id="tituloChartKmMensal">Km rodados por mês</h3>
                            <canvas id="chartKmMensal"></canvas>
                        </div>
                        <div class="bi-chart-card bi-chart-full" id="wrapChartAbastTempo" style="display:none;">
                            <h3>Abastecimentos e Gasto ao longo do tempo</h3>
                            <canvas id="chartAbastTempo"></canvas>
                        </div>
                        <div class="bi-chart-card bi-chart-full" id="wrapChartTopVeiculos">
                            <h3 id="tituloChartTopVeiculos">Top veículos (rotas e km)</h3>
                            <canvas id="chartTopVeiculos"></canvas>
                        </div>
                        </div>
                        <div id="bi-table-wrap" class="bi-table-card" style="display:none; margin-top: 1rem;">
                            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.75rem;">
                                <h3 id="bi-table-titulo" style="margin:0;">Panorama mensal (últimos 12 meses)</h3>
                                <button type="button" id="btn-exportar-tabela-csv" class="btn-action-indicators" style="background:#0d6efd;"><i class="fas fa-file-csv"></i> Exportar CSV</button>
                            </div>
                            <table class="bi-table" id="bi-tabela">
                                <thead><tr><th>Mês</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <!-- Blocos extras por visão (ranking rotas, consumo, manut, desp tipo, fixas, insights IA) -->
                        <div id="bi-ranking-rotas" class="bi-extra-block bi-table-card" style="display:none; margin-top: 1rem;">
                            <h3><i class="fas fa-trophy"></i> Ranking de rotas (mais e menos lucrativas)</h3>
                            <div id="bi-ranking-rotas-content"></div>
                        </div>
                        <div id="bi-abast-extra" class="bi-extra-block bi-table-card" style="display:none; margin-top: 1rem;">
                            <h3><i class="fas fa-tachometer-alt"></i> Consumo e custo</h3>
                            <div id="bi-abast-extra-cards"></div>
                            <div id="bi-abast-alertas" class="bi-alertas" style="margin-top: 1rem;"></div>
                        </div>
                        <div id="bi-manut-extra" class="bi-extra-block bi-table-card" style="display:none; margin-top: 1rem;">
                            <h3><i class="fas fa-wrench"></i> Custo por KM, tipo e estimativa</h3>
                            <div id="bi-manut-extra-content"></div>
                            <div class="bi-chart-card" style="margin-top: 1rem;"><canvas id="chartManutPreventivaCorretiva" height="200"></canvas></div>
                            <div id="bi-veiculos-criticos" style="margin-top: 1rem;"></div>
                        </div>
                        <div id="bi-desp-viagem-extra" class="bi-extra-block bi-table-card" style="display:none; margin-top: 1rem;">
                            <h3><i class="fas fa-road"></i> Despesa por KM e por tipo</h3>
                            <div id="bi-desp-viagem-extra-content"></div>
                            <div class="bi-chart-card" style="margin-top: 1rem;"><canvas id="chartDespViagemTipos" height="200"></canvas></div>
                        </div>
                        <div id="bi-desp-fixas-extra" class="bi-extra-block bi-table-card" style="display:none; margin-top: 1rem;">
                            <h3><i class="fas fa-file-invoice-dollar"></i> Impacto das despesas fixas</h3>
                            <div id="bi-desp-fixas-extra-content"></div>
                        </div>
                        <div id="bi-alertas-periodo" class="bi-extra-block bi-table-card" style="display:none; margin-top: 1rem;">
                            <h3><i class="fas fa-exclamation-triangle"></i> Alertas ativos no período</h3>
                            <div id="bi-alertas-periodo-content" class="bi-alertas-list"></div>
                        </div>
                        <div id="bi-ponto-equilibrio" class="bi-extra-block bi-table-card" style="display:none; margin-top: 1rem;">
                            <h3><i class="fas fa-balance-scale"></i> Ponto de equilíbrio (break-even)</h3>
                            <div id="bi-ponto-equilibrio-content" class="bi-ponto-equilibrio"></div>
                        </div>
                        <div id="bi-tendencias" class="bi-extra-block bi-table-card" style="display:none; margin-top: 1rem;">
                            <h3><i class="fas fa-chart-line"></i> Tendências</h3>
                            <div id="bi-tendencias-content" class="bi-tendencias"></div>
                        </div>
                        <div id="bi-simulador" class="bi-extra-block bi-table-card" style="display:none; margin-top: 1rem;">
                            <h3><i class="fas fa-calculator"></i> Simulador &quot;E se…&quot;</h3>
                            <p class="bi-text-muted" style="margin:0 0 0.5rem 0; font-size:0.9rem;">Simule o impacto no lucro do período alterando combustível ou comissão.</p>
                            <div id="bi-simulador-content">
                                <div class="bi-simulador-grid">
                                    <div class="bi-simulador-field">
                                        <label>Diesel / combustível (%):</label>
                                        <input type="number" id="sim-diesel-pct" value="10" step="1" min="-50" max="100" title="Ex.: 10 = +10%">
                                        <span style="font-size:0.8rem; color: var(--text-muted);">+10% = subida de 10%</span>
                                    </div>
                                    <div class="bi-simulador-field">
                                        <label>Comissão (%):</label>
                                        <input type="number" id="sim-comissao-pct" value="1" step="0.5" min="-50" max="50" title="Ex.: 1 = reduzir 1%">
                                        <span style="font-size:0.8rem; color: var(--text-muted);">-1% = reduzir 1%</span>
                                    </div>
                                </div>
                                <div id="bi-simulador-result" class="bi-simulador-result" style="display:none;"></div>
                            </div>
                        </div>
                        <div id="bi-custo-km-hist" class="bi-extra-block bi-table-card" style="display:none; margin-top: 1rem;">
                            <h3><i class="fas fa-tachometer-alt"></i> Custo por KM – histórico e por veículo</h3>
                            <div class="bi-chart-card" style="margin-bottom: 1rem;">
                                <h4 style="font-size:0.95rem; margin:0 0 0.5rem 0;">Evolução do custo/km (mês a mês)</h4>
                                <canvas id="chartCustoKmHist" height="200"></canvas>
                            </div>
                            <h4 style="font-size:0.95rem; margin:0.5rem 0;">Por veículo (período)</h4>
                            <div id="bi-custo-km-veiculos-content" class="bi-custo-km-veiculos"></div>
                        </div>
                        <div id="bi-insights-ia" class="bi-extra-block bi-table-card" style="display:none; margin-top: 1rem;">
                            <h3><i class="fas fa-robot"></i> Insights automáticos</h3>
                            <div id="bi-insights-ia-content" class="bi-insights-list"></div>
                        </div>

                        <!-- Tela só para Indicadores de Desempenho (visão indicadores_desempenho) -->
                        <div id="bi-indicadores-desempenho" class="bi-indicadores-section" style="display: none;">
                            <h2><i class="fas fa-chart-line"></i> Indicadores de Desempenho</h2>
                            <div class="bi-indicadores-box">
                                <div class="bi-indicadores-actions">
                                    <button type="button" id="btn-indicadores-atualizar" class="btn-action-indicators"><i class="fas fa-sync-alt"></i> Atualizar</button>
                                    <button type="button" id="btn-indicadores-excel" class="btn-action-indicators"><i class="fas fa-file-excel"></i> Excel</button>
                                </div>
                                <div id="indicatorsLoading" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--text-secondary);"></i>
                                    <p style="margin-top: 15px; color: var(--text-secondary);">Carregando indicadores...</p>
                                </div>
                                <div id="indicatorsTableContainer" style="display: none; max-width: 100%; overflow-x: auto; padding: 0;">
                                    <table id="indicatorsTable" class="indicators-table" style="width: 100%; border-collapse: collapse; min-width: 1200px; max-width: none;">
                                        <thead>
                                            <tr>
                                                <th style="position: sticky; left: 0; background: var(--bg-tertiary); z-index: 10; padding: 12px; text-align: left; border: 1px solid var(--border-color); border-bottom: 2px solid var(--border-color); font-weight: 600; min-width: 150px;">Indicador</th>
                                                <th id="indicatorsTableHeader" style="padding: 12px; text-align: center; border-bottom: 2px solid var(--border-color); font-weight: 600; min-width: 150px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="indicatorsTableBody"></tbody>
                                    </table>
                                </div>
                                <div id="bi-indicadores-alertas" class="bi-indicadores-alertas" style="margin-top: 1rem; display: none;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <?php include __DIR__ . '/../includes/footer.php'; ?>
            </div>
        </div>
    </div>

    <!-- JavaScript Files -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
(function() {
    var baseUrl = '../api';
    var charts = {};

    function formatMoney(v) {
        if (v == null) return '—';
        return 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function formatNum(v) {
        if (v == null) return '—';
        return Number(v).toLocaleString('pt-BR', { maximumFractionDigits: 0 });
    }
    function variacaoPct(atual, anterior) {
        if (anterior == null || anterior === 0) return null;
        var pct = ((atual - anterior) / Math.abs(anterior)) * 100;
        return pct;
    }
    function badgeVariacao(pct, label) {
        if (pct == null || isNaN(pct)) return '';
        var cls = pct > 0 ? 'positive' : (pct < 0 ? 'negative' : 'neutral');
        var sinal = pct > 0 ? '▲' : (pct < 0 ? '▼' : '');
        var txt = sinal + (pct >= 0 ? '+' : '') + pct.toFixed(1) + '%';
        return '<span class="variation ' + cls + '" style="font-size:0.7rem;display:block;margin-top:2px;" title="' + (label || '') + '">' + txt + '</span>';
    }

    function showLoading(show) {
        document.getElementById('bi-loading').style.display = show ? 'block' : 'none';
        document.getElementById('bi-error').style.display = 'none';
    }
    function showError(msg) {
        document.getElementById('bi-error').textContent = msg;
        document.getElementById('bi-error').style.display = 'block';
        document.getElementById('bi-kpis').style.display = 'none';
        document.getElementById('bi-charts').style.display = 'none';
        document.getElementById('bi-table-wrap').style.display = 'none';
    }
    function showAvisoDadosIncompletos(data) {
        var el = document.getElementById('bi-aviso-incompletos');
        if (!el) return;
        var d = (data && data.dados_incompletos) ? data.dados_incompletos : null;
        if (!d || !d.tem_incompletos) { el.style.display = 'none'; el.innerHTML = ''; return; }
        var parts = [];
        if (d.rotas_sem_despesas > 0) parts.push(d.rotas_sem_despesas + ' rota(s) sem despesas de viagem');
        if (d.abastecimentos_sem_litros > 0) parts.push(d.abastecimentos_sem_litros + ' abastecimento(s) sem litros');
        if (d.manutencoes_sem_tipo > 0) parts.push(d.manutencoes_sem_tipo + ' manutenção(ões) sem tipo');
        el.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <strong>Existem dados incompletos que podem impactar os resultados:</strong> ' + parts.join('; ') + '.';
        el.style.display = 'block';
    }
    function kpiCard(icon, value, label, attr, ref, comp) {
        var variacoes = '';
        if (comp && comp.mes_anterior && comp.mesmo_mes_ano_anterior && ref) {
            var v1 = variacaoPct(Number(ref[attr]), Number(comp.mes_anterior[attr]));
            var v2 = variacaoPct(Number(ref[attr]), Number(comp.mesmo_mes_ano_anterior[attr]));
            variacoes = '<div class="bi-kpi-variacoes">' + badgeVariacao(v1, 'vs mês ant.') + badgeVariacao(v2, 'vs ano ant.') + '</div>';
        }
        return '<div class="bi-kpi">' + (icon ? '<i class="' + icon + '"></i>' : '') + '<div class="bi-kpi-value">' + value + '</div>' + variacoes + '<div class="bi-kpi-label">' + label + '</div></div>';
    }
    function renderKPIs(data, visao) {
        visao = visao || 'geral';
        var hist = data.historico_mensal || [];
        var comp = data.comparacao || null;
        var ref = hist.length > 0 ? hist[hist.length - 1] : null;
        function sum(attr) {
            return hist.reduce(function (acc, row) { return acc + (Number(row[attr]) || 0); }, 0);
        }
        var totalRotas = sum('total_rotas');
        var totalKm = sum('total_km_rodados');
        var totalFrete = sum('total_frete');
        var totalComissao = sum('total_comissao');
        var totalAbast = sum('total_abastecimentos');
        var gastoAbast = sum('total_gasto_abastecimentos');
        var lucro = sum('lucro_operacional');
        var ticket = totalRotas > 0 ? totalFrete / totalRotas : 0;
        var mediaAbast = totalAbast > 0 ? gastoAbast / totalAbast : 0;
        var veiculosAbast = (data.veiculos_top_abastecimento || []).length;
        var totalDespViagem = sum('total_despesas_viagem');
        var totalManut = sum('total_manutencoes');
        var qtdManut = sum('quantidade_manutencoes');
        var totalDespFixas = sum('total_despesas_fixas');
        var qtdDespFixas = sum('quantidade_despesas_fixas');
        var mediaManut = qtdManut > 0 ? totalManut / qtdManut : 0;
        var mediaDespFixas = qtdDespFixas > 0 ? totalDespFixas / qtdDespFixas : 0;

        var html = '';
        if (visao === 'geral') {
            html = kpiCard('fas fa-route', formatNum(totalRotas), 'Total Rotas', 'total_rotas', ref, comp)
                + kpiCard('fas fa-tachometer-alt', formatNum(totalKm), 'Km rodados', 'total_km_rodados', ref, comp)
                + kpiCard('', formatMoney(totalFrete), 'Faturamento (Frete)', 'total_frete', ref, comp)
                + kpiCard('fas fa-hand-holding-usd', formatMoney(totalComissao), 'Comissão', 'total_comissao', ref, comp)
                + kpiCard('fas fa-gas-pump', formatNum(totalAbast), 'Abastecimentos', 'total_abastecimentos', ref, comp)
                + kpiCard('', formatMoney(gastoAbast), 'Gasto Abast.', 'total_gasto_abastecimentos', ref, comp)
                + kpiCard('fas fa-road', formatMoney(totalDespViagem), 'Desp. viagem', 'total_despesas_viagem', ref, comp)
                + kpiCard('fas fa-tools', formatMoney(totalManut), 'Manutenção', 'total_manutencoes', ref, comp)
                + kpiCard('fas fa-file-invoice-dollar', formatMoney(totalDespFixas), 'Desp. fixas', 'total_despesas_fixas', ref, comp)
                + kpiCard('fas fa-coins', formatMoney(ticket), 'Ticket médio', null, ref, comp)
                + kpiCard('fas fa-chart-line', formatMoney(lucro), 'Lucro oper.', 'lucro_operacional', ref, comp);
        } else if (visao === 'rotas') {
            html = kpiCard('fas fa-route', formatNum(totalRotas), 'Total Rotas', 'total_rotas', ref, comp)
                + kpiCard('fas fa-tachometer-alt', formatNum(totalKm), 'Km rodados', 'total_km_rodados', ref, comp)
                + kpiCard('', formatMoney(totalFrete), 'Faturamento (Frete)', 'total_frete', ref, comp)
                + kpiCard('fas fa-hand-holding-usd', formatMoney(totalComissao), 'Comissão', 'total_comissao', ref, comp)
                + kpiCard('fas fa-coins', formatMoney(ticket), 'Ticket médio', null, ref, comp)
                + kpiCard('fas fa-chart-line', formatMoney(lucro), 'Lucro oper.', 'lucro_operacional', ref, comp);
        } else if (visao === 'manutencao') {
            var custoKmManut = totalKm > 0 ? totalManut / totalKm : 0;
            html = kpiCard('fas fa-tools', formatNum(qtdManut), 'Total Manutenções', 'quantidade_manutencoes', ref, comp)
                + kpiCard('', formatMoney(totalManut), 'Custo Total', 'total_manutencoes', ref, comp)
                + kpiCard('fas fa-coins', formatMoney(mediaManut), 'Média por manut.', null, ref, comp)
                + kpiCard('fas fa-tachometer-alt', formatMoney(custoKmManut), 'Custo/KM', null, ref, comp)
                + kpiCard('fas fa-truck', formatNum((data.veiculos_top_manutencao || []).length), 'Veículos', null, ref, comp);
        } else if (visao === 'despesas_viagem') {
            html = kpiCard('fas fa-road', formatMoney(totalDespViagem), 'Total Desp. Viagem', 'total_despesas_viagem', ref, comp)
                + kpiCard('fas fa-route', formatNum(totalRotas), 'Rotas (período)', 'total_rotas', ref, comp)
                + kpiCard('fas fa-coins', formatMoney(totalRotas > 0 ? totalDespViagem / totalRotas : 0), 'Média por rota', null, ref, comp);
        } else if (visao === 'despesas_fixas') {
            html = kpiCard('fas fa-file-invoice-dollar', formatNum(qtdDespFixas), 'Qtde Desp. Fixas', 'quantidade_despesas_fixas', ref, comp)
                + kpiCard('', formatMoney(totalDespFixas), 'Total Pago', 'total_despesas_fixas', ref, comp)
                + kpiCard('fas fa-coins', formatMoney(mediaDespFixas), 'Média por despesa', null, ref, comp);
        } else {
            html = kpiCard('fas fa-gas-pump', formatNum(totalAbast), 'Total Abastecimentos', 'total_abastecimentos', ref, comp)
                + kpiCard('', formatMoney(gastoAbast), 'Gasto Total', 'total_gasto_abastecimentos', ref, comp)
                + kpiCard('fas fa-coins', formatMoney(mediaAbast), 'Média por abast.', null, ref, comp)
                + kpiCard('fas fa-truck', formatNum(veiculosAbast), 'Veículos', null, ref, comp);
        }
        document.getElementById('bi-kpis').innerHTML = html;
        document.getElementById('bi-kpis').style.display = 'grid';

        if (visao === 'geral') {
            var custoTotal = gastoAbast + totalManut + totalDespViagem + totalDespFixas;
            var custoPorKm = totalKm > 0 ? custoTotal / totalKm : 0;
            var lucroPorKm = totalKm > 0 ? lucro / totalKm : 0;
            var margemPct = totalFrete > 0 ? (lucro / totalFrete) * 100 : 0;
            var custoPorRota = totalRotas > 0 ? custoTotal / totalRotas : 0;
            var semaforoClass = margemPct < 10 ? 'vermelho' : margemPct <= 20 ? 'amarelo' : 'verde';
            var semaforoTexto = margemPct < 10 ? 'Atenção: margem baixa (&lt;10%)' : margemPct <= 20 ? 'Margem moderada (10–20%)' : 'Margem saudável (&gt;20%)';
            var cardsEl = document.getElementById('bi-saude-cards');
            var semaforoEl = document.getElementById('bi-semaforo');
            var textoEl = document.getElementById('bi-semaforo-texto');
            var scoreWrap = document.getElementById('bi-score-frota-wrap');
            if (scoreWrap) {
                var margemScore = Math.min(100, Math.max(0, margemPct * 5));
                var semaforoScore = semaforoClass === 'verde' ? 100 : (semaforoClass === 'amarelo' ? 60 : 25);
                var crescimentoScore = 50;
                if (comp && comp.mesmo_mes_ano_anterior && ref) {
                    var lucroAnt = Number(comp.mesmo_mes_ano_anterior.lucro_operacional) || 0;
                    var lucroRef = Number(ref.lucro_operacional) || 0;
                    if (lucroAnt !== 0) {
                        var growth = ((lucroRef - lucroAnt) / Math.abs(lucroAnt)) * 100;
                        crescimentoScore = Math.min(100, Math.max(0, 50 + growth));
                    }
                }
                var score = Math.round(margemScore * 0.4 + semaforoScore * 0.3 + crescimentoScore * 0.3);
                score = Math.min(100, Math.max(0, score));
                var scoreClass = score >= 70 ? 'bom' : (score >= 40 ? 'medio' : 'baixo');
                var scoreTexto = score >= 70 ? 'Bom' : (score >= 40 ? 'Médio' : 'Atenção');
                scoreWrap.innerHTML = '<div class="bi-score-frota-num ' + scoreClass + '">' + score + '<span style="font-size:0.6em; font-weight:600; opacity:0.8;">/100</span></div><div><div class="bi-score-frota-label">Score da Frota</div><div class="bi-score-frota-desc">' + scoreTexto + ' &middot; Baseado em margem, desempenho e crescimento</div></div>';
                scoreWrap.style.display = 'flex';
            }
            if (cardsEl) {
                cardsEl.innerHTML = '<div class="bi-saude-card"><div class="bi-saude-value">' + formatMoney(custoPorKm) + '</div><div class="bi-saude-label">Custo por KM</div><div class="bi-saude-label" style="font-size:0.7rem;opacity:0.8;">(Abast + Manut + Desp)</div></div>'
                    + '<div class="bi-saude-card' + (lucro < 0 ? ' negative' : '') + '"><div class="bi-saude-value">' + formatMoney(lucroPorKm) + '</div><div class="bi-saude-label">Lucro por KM</div></div>'
                    + '<div class="bi-saude-card' + (margemPct < 0 ? ' negative' : '') + '"><div class="bi-saude-value">' + (margemPct.toFixed(1)) + '%</div><div class="bi-saude-label">Margem operacional</div><div class="bi-saude-label" style="font-size:0.7rem;opacity:0.8;">Lucro ÷ Faturamento</div></div>'
                    + '<div class="bi-saude-card"><div class="bi-saude-value">' + formatMoney(custoPorRota) + '</div><div class="bi-saude-label">Custo por Rota</div></div>';
            }
            if (semaforoEl) {
                semaforoEl.innerHTML = '<span class="bi-semaforo-dot ' + semaforoClass + '" title="Margem ' + margemPct.toFixed(1) + '%"></span>';
            }
            if (textoEl) {
                textoEl.textContent = semaforoTexto.replace(/&lt;/g, '<').replace(/&gt;/g, '>');
                textoEl.className = 'bi-semaforo-texto ' + semaforoClass;
            }
            var saudeWrap = document.getElementById('bi-saude-frota');
            if (saudeWrap) saudeWrap.style.display = 'block';
        } else {
            var saudeWrap = document.getElementById('bi-saude-frota');
            if (saudeWrap) saudeWrap.style.display = 'none';
        }
    }

    function destroyCharts() {
        ['chartRotasTempo','chartFreteMensal','chartKmMensal','chartAbastTempo','chartTopVeiculos','chartDespViagemTipos','chartCustoKmHist','chartManutPreventivaCorretiva'].forEach(function(id) {
            if (charts[id]) { charts[id].destroy(); charts[id] = null; }
        });
    }
    function simuladorUpdate() {
        var wrap = document.getElementById('bi-simulador-content');
        if (!wrap || !wrap.dataset.frete) return;
        var frete = parseFloat(wrap.dataset.frete) || 0, comissao = parseFloat(wrap.dataset.comissao) || 0, gastoAbast = parseFloat(wrap.dataset.gastoAbast) || 0, despViagem = parseFloat(wrap.dataset.despViagem) || 0, lucroAtual = parseFloat(wrap.dataset.lucro) || 0;
        var dieselPct = parseFloat(document.getElementById('sim-diesel-pct') && document.getElementById('sim-diesel-pct').value) || 0;
        var comissaoPct = parseFloat(document.getElementById('sim-comissao-pct') && document.getElementById('sim-comissao-pct').value) || 0;
        var newGastoAbast = gastoAbast * (1 + dieselPct / 100);
        var newComissao = comissao * (1 - comissaoPct / 100);
        var lucroSimulado = frete - newComissao - newGastoAbast - despViagem;
        var impacto = lucroSimulado - lucroAtual;
        var impactoPct = lucroAtual !== 0 ? ((impacto / Math.abs(lucroAtual)) * 100).toFixed(1) : '—';
        var el = document.getElementById('bi-simulador-result');
        if (!el) return;
        el.style.display = 'block';
        var cls = impacto >= 0 ? 'positive' : 'negative';
        el.innerHTML = '<strong>Lucro atual (período):</strong> ' + formatMoney(lucroAtual) + '<br><strong>Lucro simulado:</strong> ' + formatMoney(lucroSimulado) + '<br><span class="impacto variation ' + cls + '">Impacto: ' + (impacto >= 0 ? '+' : '') + formatMoney(impacto) + ' (' + (impacto >= 0 ? '+' : '') + impactoPct + '%)</span>';
    }
    function hideAllExtraBlocks() {
        ['bi-ranking-rotas','bi-abast-extra','bi-manut-extra','bi-desp-viagem-extra','bi-desp-fixas-extra','bi-insights-ia','bi-alertas-periodo','bi-ponto-equilibrio','bi-tendencias','bi-simulador','bi-custo-km-hist'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });
    }
    function renderExtraBlocks(data, visao) {
        hideAllExtraBlocks();
        var hist = data.historico_mensal || [];
        function sum(attr) { return hist.reduce(function (acc, row) { return acc + (Number(row[attr]) || 0); }, 0); }
        var totalKm = sum('total_km_rodados');
        var totalFrete = sum('total_frete');
        var totalComissao = sum('total_comissao');
        var totalDespViagem = sum('total_despesas_viagem');
        var totalDespFixas = sum('total_despesas_fixas');
        var totalRotas = sum('total_rotas');
        var lucro = sum('lucro_operacional');
        var gastoAbast = sum('total_gasto_abastecimentos');
        var totalManut = sum('total_manutencoes');
        var totalLitros = hist.reduce(function(acc, row) { return acc + (Number(row.total_litros) || 0); }, 0);

        if (visao === 'rotas' && data.ranking_rotas) {
            var r = data.ranking_rotas;
            var top5 = (r.top5 || []).map(function(x) { return '<tr><td>' + (x.origem_destino || '-') + '</td><td>' + formatNum(x.km) + '</td><td>' + formatMoney(x.frete) + '</td><td>' + formatMoney(x.custo_total) + '</td><td>' + formatMoney(x.lucro) + '</td><td>' + (x.margem_pct ? x.margem_pct.toFixed(1) : '0') + '%</td></tr>'; }).join('');
            var bot5 = (r.bottom5 || []).map(function(x) { return '<tr><td>' + (x.origem_destino || '-') + '</td><td>' + formatNum(x.km) + '</td><td>' + formatMoney(x.frete) + '</td><td>' + formatMoney(x.custo_total) + '</td><td>' + formatMoney(x.lucro) + '</td><td>' + (x.margem_pct ? x.margem_pct.toFixed(1) : '0') + '%</td></tr>'; }).join('');
            var tbl = '<h4 style="margin-bottom:0.5rem;">Mais lucrativas</h4><table class="bi-table"><thead><tr><th>Rota</th><th>KM</th><th>Frete</th><th>Custo</th><th>Lucro</th><th>Margem %</th></tr></thead><tbody>' + top5 + '</tbody></table><h4 style="margin-top:1rem;">Menos lucrativas</h4><table class="bi-table"><thead><tr><th>Rota</th><th>KM</th><th>Frete</th><th>Custo</th><th>Lucro</th><th>Margem %</th></tr></thead><tbody>' + bot5 + '</tbody></table>';
            var el = document.getElementById('bi-ranking-rotas-content');
            if (el) { el.innerHTML = tbl; document.getElementById('bi-ranking-rotas').style.display = 'block'; }
        }
        if (visao === 'abastecimento') {
            var consumoMedio = totalLitros > 0 && totalKm > 0 ? (totalKm / totalLitros).toFixed(2) : '0';
            var custoCombustivelKm = totalKm > 0 ? formatMoney(gastoAbast / totalKm) : formatMoney(0);
            var cards = '<div class="bi-saude-cards"><div class="bi-saude-card"><div class="bi-saude-value">' + consumoMedio + '</div><div class="bi-saude-label">Consumo médio (KM/L)</div></div><div class="bi-saude-card"><div class="bi-saude-value">' + custoCombustivelKm + '</div><div class="bi-saude-label">Custo combustível por KM</div></div></div>';
            var consumoList = (data.veiculos_consumo || []).filter(function(v) { return (v.total_litros || 0) > 0; }).sort(function(a, b) { return (b.consumo_medio || 0) - (a.consumo_medio || 0); });
            var tabelaConsumo = consumoList.length ? '<h4 style="margin:1rem 0 0.5rem 0;">Consumo por veículo</h4><table class="bi-table"><thead><tr><th>Veículo</th><th>KM</th><th>Litros</th><th>KM/L</th></tr></thead><tbody>' + consumoList.map(function(v) { return '<tr><td>' + (v.placa || v.modelo || '-') + '</td><td>' + formatNum(v.total_km) + '</td><td>' + formatNum(v.total_litros) + '</td><td>' + (v.consumo_medio != null ? v.consumo_medio.toFixed(2) : '-') + '</td></tr>'; }).join('') + '</tbody></table>' : '';
            var alertas = [];
            (data.veiculos_consumo || []).filter(function(v) { return v.fora_media; }).forEach(function(v) { alertas.push('⛽ Veículo ' + (v.placa || v.modelo || '') + ' com consumo abaixo da média da frota (' + v.consumo_medio + ' km/L).'); });
            if (hist.length >= 2) {
                var ult = hist[hist.length - 1].total_gasto_abastecimentos || 0;
                var pen = hist[hist.length - 2].total_gasto_abastecimentos || 0;
                if (pen > 0 && ult > pen * 1.2) alertas.push('⚠️ Aumento brusco de gasto com abastecimento no último mês (+' + (((ult - pen) / pen) * 100).toFixed(0) + '%).');
            }
            var elCards = document.getElementById('bi-abast-extra-cards');
            var elAlertas = document.getElementById('bi-abast-alertas');
            if (elCards) elCards.innerHTML = cards + tabelaConsumo;
            if (elAlertas) elAlertas.innerHTML = alertas.length ? '<ul style="margin:0; padding-left:1.2rem;">' + alertas.map(function(a) { return '<li>' + a + '</li>'; }).join('') + '</ul>' : '<p class="bi-text-muted">Nenhum alerta no período.</p>';
            document.getElementById('bi-abast-extra').style.display = 'block';
        }
        if (visao === 'manutencao') {
            var custoManutKm = totalKm > 0 ? formatMoney(totalManut / totalKm) : formatMoney(0);
            var pc = data.manut_preventiva_corretiva || { preventiva: { qtd: 0, valor: 0 }, corretiva: { qtd: 0, valor: 0 } };
            var prev = pc.preventiva || {}; var corr = pc.corretiva || {};
            var previsao = data.previsao_proximo_mes != null && data.previsao_proximo_mes > 0 ? formatMoney(data.previsao_proximo_mes) : null;
            var bloco = '<div class="bi-saude-cards"><div class="bi-saude-card"><div class="bi-saude-value">' + custoManutKm + '</div><div class="bi-saude-label">Custo manutenção por KM</div></div>' + (previsao ? '<div class="bi-saude-card"><div class="bi-saude-value">' + previsao + '</div><div class="bi-saude-label">Estimativa próximo mês</div><div class="bi-saude-label" style="font-size:0.7rem;">média últimos 6 meses</div></div>' : '') + '</div><p><strong>Preventiva:</strong> ' + (prev.qtd || 0) + ' manutenções, ' + formatMoney(prev.valor || 0) + ' &nbsp;|&nbsp; <strong>Corretiva:</strong> ' + (corr.qtd || 0) + ' manutenções, ' + formatMoney(corr.valor || 0) + '</p>';
            var crit = (data.veiculos_criticos || []).slice(0, 10).map(function(v) { return v.placa + ' – ' + formatMoney(v.total_gasto); }).join('; ');
            var links = '<p style="margin-top:1rem;"><a href="manutencoes.php" class="btn-action-indicators" style="text-decoration:none; margin-right:0.5rem;"><i class="fas fa-tools"></i> Ver página Manutenções</a> <a href="planos_manutencao.php" class="btn-action-indicators" style="text-decoration:none; background:#28a745;"><i class="fas fa-clipboard-list"></i> Planos de manutenção</a></p>';
            var elC = document.getElementById('bi-manut-extra-content');
            var elV = document.getElementById('bi-veiculos-criticos');
            if (elC) elC.innerHTML = bloco + links;
            if (elV) elV.innerHTML = crit ? '<h4>Veículos com custo acima da média</h4><p>' + crit + '</p>' : '';
            document.getElementById('bi-manut-extra').style.display = 'block';
        }
        if (visao === 'despesas_viagem') {
            var despKm = totalKm > 0 ? formatMoney(totalDespViagem / totalKm) : formatMoney(0);
            var elD = document.getElementById('bi-desp-viagem-extra-content');
            if (elD) { elD.innerHTML = '<div class="bi-saude-cards"><div class="bi-saude-card"><div class="bi-saude-value">' + despKm + '</div><div class="bi-saude-label">Despesa média por KM</div></div></div>'; }
            document.getElementById('bi-desp-viagem-extra').style.display = 'block';
        }
        if (visao === 'despesas_fixas') {
            var fixasKm = totalKm > 0 ? formatMoney(totalDespFixas / totalKm) : formatMoney(0);
            var impactoPct = totalFrete > 0 ? ((totalDespFixas / totalFrete) * 100).toFixed(1) : '0';
            var elF = document.getElementById('bi-desp-fixas-extra-content');
            if (elF) elF.innerHTML = '<div class="bi-saude-cards"><div class="bi-saude-card"><div class="bi-saude-value">' + fixasKm + '</div><div class="bi-saude-label">Fixas ÷ KM</div></div><div class="bi-saude-card"><div class="bi-saude-value">' + impactoPct + '%</div><div class="bi-saude-label">Impacto no faturamento</div></div></div>';
            document.getElementById('bi-desp-fixas-extra').style.display = 'block';
        }
        if (visao === 'geral') {
            var anoUrl = (document.getElementById('filtro-ano') && document.getElementById('filtro-ano').value) ? document.getElementById('filtro-ano').value : '';
            var mesUrl = (document.getElementById('filtro-mes') && document.getElementById('filtro-mes').value) ? document.getElementById('filtro-mes').value : '';
            var qs = '?visao=geral' + (anoUrl ? '&ano=' + encodeURIComponent(anoUrl) : '') + (mesUrl ? '&mes=' + encodeURIComponent(mesUrl) : '');
            var qsAbast = '?visao=abastecimento' + (anoUrl ? '&ano=' + encodeURIComponent(anoUrl) : '') + (mesUrl ? '&mes=' + encodeURIComponent(mesUrl) : '');
            var qsManut = '?visao=manutencao' + (anoUrl ? '&ano=' + encodeURIComponent(anoUrl) : '') + (mesUrl ? '&mes=' + encodeURIComponent(mesUrl) : '');
            var qsRotas = '?visao=rotas' + (anoUrl ? '&ano=' + encodeURIComponent(anoUrl) : '') + (mesUrl ? '&mes=' + encodeURIComponent(mesUrl) : '');
            var alertasList = [];
            var refRow = (data.historico_mensal && data.historico_mensal.length) ? data.historico_mensal[data.historico_mensal.length - 1] : null;
            var margemRef = refRow && refRow.total_frete > 0 ? ((refRow.lucro_operacional / refRow.total_frete) * 100) : 0;
            if (margemRef < 0) alertasList.push({ txt: 'Margem operacional negativa no período.', href: qsRotas });
            (data.veiculos_consumo || []).filter(function(v) { return v.fora_media; }).forEach(function(v) { alertasList.push({ txt: 'Veículo ' + (v.placa || v.modelo || '-') + ' com consumo abaixo da média da frota.', href: qsAbast }); });
            (data.veiculos_criticos || []).slice(0, 5).forEach(function(v) { alertasList.push({ txt: 'Custo de manutenção acima da média: ' + (v.placa || v.modelo || '-') + '.', href: qsManut }); });
            (data.ranking_rotas && data.ranking_rotas.bottom5 || []).filter(function(r) { return r.lucro < 0; }).forEach(function(r) { alertasList.push({ txt: 'Rota com margem negativa: ' + (r.origem_destino || 'Rota').substring(0, 50) + '.', href: qsRotas }); });
            var elAlertas = document.getElementById('bi-alertas-periodo-content');
            if (elAlertas) elAlertas.innerHTML = alertasList.length ? '<ul style="margin:0; padding-left:1.2rem;">' + alertasList.map(function(a) { return '<li>' + (a.href ? '<a href="' + a.href + '">' + a.txt + '</a>' : a.txt) + '</li>'; }).join('') + '</ul>' : '<p class="bi-text-muted">Nenhum alerta ativo no período.</p>';
            document.getElementById('bi-alertas-periodo').style.display = 'block';

            if (data.ponto_equilibrio) {
                var pe = data.ponto_equilibrio;
                var elPe = document.getElementById('bi-ponto-equilibrio-content');
                if (elPe) {
                    elPe.innerHTML = '<p class="bi-text-muted" style="margin:0 0 0.5rem 0;">Referência: ' + (pe.mes_ref || 'último mês') + '</p><h4>Faturamento mínimo para empatar</h4><p style="margin:0; font-size:1.1rem; font-weight:600;">' + formatMoney(pe.faturamento_minimo) + '</p><h4 style="margin-top:0.75rem;">Rotas necessárias para empatar</h4><p style="margin:0; font-size:1.1rem;">' + formatNum(pe.rotas_para_empatar) + ' rotas</p><p class="bi-text-muted" style="margin-top:0.25rem; font-size:0.85rem;">Ticket médio de referência: ' + formatMoney(pe.ticket_medio_ref) + '</p>';
                }
                document.getElementById('bi-ponto-equilibrio').style.display = 'block';
            }
            var tendenciasList = [];
            if (data.historico_mensal && data.historico_mensal.length >= 3) {
                var h = data.historico_mensal;
                var m1 = (h[h.length - 3].total_frete > 0) ? (h[h.length - 3].lucro_operacional / h[h.length - 3].total_frete) * 100 : 0;
                var m2 = (h[h.length - 2].total_frete > 0) ? (h[h.length - 2].lucro_operacional / h[h.length - 2].total_frete) * 100 : 0;
                var m3 = (h[h.length - 1].total_frete > 0) ? (h[h.length - 1].lucro_operacional / h[h.length - 1].total_frete) * 100 : 0;
                if (m1 > m2 && m2 > m3) tendenciasList.push('📉 3 meses consecutivos de queda de margem operacional.');
                var g1 = h[h.length - 3].total_gasto_abastecimentos || 0, g2 = h[h.length - 2].total_gasto_abastecimentos || 0, g3 = h[h.length - 1].total_gasto_abastecimentos || 0;
                if (g1 < g2 && g2 < g3) tendenciasList.push('📈 Tendência de alta nos custos de combustível.');
            }
            var elTend = document.getElementById('bi-tendencias-content');
            if (elTend) elTend.innerHTML = tendenciasList.length ? '<ul style="margin:0; padding-left:1.2rem;">' + tendenciasList.map(function(t) { return '<li>' + t + '</li>'; }).join('') + '</ul>' : '<p class="bi-text-muted">Nenhuma tendência identificada nos últimos 3 meses.</p>';
            document.getElementById('bi-tendencias').style.display = 'block';

            var simWrap = document.getElementById('bi-simulador-content');
            if (simWrap) {
                simWrap.dataset.frete = totalFrete;
                simWrap.dataset.comissao = totalComissao;
                simWrap.dataset.gastoAbast = gastoAbast;
                simWrap.dataset.despViagem = totalDespViagem;
                simWrap.dataset.lucro = lucro;
                if (typeof simuladorUpdate === 'function') simuladorUpdate();
            }
            document.getElementById('bi-simulador').style.display = 'block';

            var histCustoKm = (data.historico_mensal || []).map(function(m) {
                var ct = (Number(m.total_gasto_abastecimentos) || 0) + (Number(m.total_manutencoes) || 0) + (Number(m.total_despesas_viagem) || 0) + (Number(m.total_despesas_fixas) || 0);
                var km = Number(m.total_km_rodados) || 0;
                return km > 0 ? ct / km : 0;
            });
            var labelsCustoKm = (data.labels || (data.historico_mensal || []).map(function(x) { return x.mes_nome || x.mes_ano; })) || [];
            var canvasCustoKm = document.getElementById('chartCustoKmHist');
            if (canvasCustoKm && labelsCustoKm.length) {
                if (charts.chartCustoKmHist) { charts.chartCustoKmHist.destroy(); charts.chartCustoKmHist = null; }
                charts.chartCustoKmHist = new Chart(canvasCustoKm, {
                    type: 'line',
                    data: { labels: labelsCustoKm, datasets: [{ label: 'Custo/km (R$)', data: histCustoKm, borderColor: '#0d9488', backgroundColor: 'rgba(13,148,136,0.1)', fill: true }] },
                    options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true } } }
                });
            }
            var topV = data.veiculos_top || [], abastV = data.veiculos_top_abastecimento || [], manutV = data.veiculos_top_manutencao || [];
            var byId = {};
            topV.forEach(function(v) { byId[v.id] = { id: v.id, placa: v.placa || '', modelo: v.modelo || '', total_km: Number(v.total_km) || 0, gasto_abast: 0, gasto_manut: 0 }; });
            abastV.forEach(function(v) { if (byId[v.id]) byId[v.id].gasto_abast = Number(v.total_gasto) || 0; else byId[v.id] = { id: v.id, placa: v.placa || '', modelo: v.modelo || '', total_km: 0, gasto_abast: Number(v.total_gasto) || 0, gasto_manut: 0 }; });
            manutV.forEach(function(v) { if (byId[v.id]) byId[v.id].gasto_manut = Number(v.total_gasto) || 0; else byId[v.id] = { id: v.id, placa: v.placa || '', modelo: v.modelo || '', total_km: 0, gasto_abast: 0, gasto_manut: Number(v.total_gasto) || 0 }; });
            var veiculosCustoKm = Object.keys(byId).map(function(k) { var v = byId[k]; v.custo_km = (v.total_km > 0) ? ((v.gasto_abast + v.gasto_manut) / v.total_km) : 0; return v; }).filter(function(v) { return v.total_km > 0 || (v.gasto_abast + v.gasto_manut) > 0; }).sort(function(a, b) { return b.custo_km - a.custo_km; });
            var elCustoVeic = document.getElementById('bi-custo-km-veiculos-content');
            if (elCustoVeic) elCustoVeic.innerHTML = veiculosCustoKm.length ? '<table class="bi-table"><thead><tr><th>Veículo</th><th>KM</th><th>Gasto abast. + manut.</th><th>Custo/km (R$)</th></tr></thead><tbody>' + veiculosCustoKm.map(function(v) { return '<tr><td>' + (v.placa || v.modelo || '-') + '</td><td>' + formatNum(v.total_km) + '</td><td>' + formatMoney(v.gasto_abast + v.gasto_manut) + '</td><td>' + formatMoney(v.custo_km) + '</td></tr>'; }).join('') + '</tbody></table>' : '<p class="bi-text-muted">Nenhum dado de veículo no período.</p>';
            document.getElementById('bi-custo-km-hist').style.display = 'block';

            if (data.historico_mensal && data.historico_mensal.length >= 2) {
                var insights = [];
                var h = data.historico_mensal;
                var ultimo = h[h.length - 1];
                var anterior = h[h.length - 2];
                var lucroAnt = anterior.lucro_operacional || 0;
                var lucroUlt = ultimo.lucro_operacional || 0;
                if (lucroAnt !== 0 && lucroUlt !== lucroAnt) {
                    var varPct = ((lucroUlt - lucroAnt) / Math.abs(lucroAnt) * 100);
                    var causa = '';
                    var dAbast = (Number(ultimo.total_gasto_abastecimentos) || 0) - (Number(anterior.total_gasto_abastecimentos) || 0);
                    var dComissao = (Number(ultimo.total_comissao) || 0) - (Number(anterior.total_comissao) || 0);
                    var dDesp = (Number(ultimo.total_despesas_viagem) || 0) - (Number(anterior.total_despesas_viagem) || 0);
                    var dFrete = (Number(ultimo.total_frete) || 0) - (Number(anterior.total_frete) || 0);
                    if (lucroUlt < lucroAnt) {
                        if (dAbast > 0 && dAbast >= Math.max(dComissao, dDesp)) {
                            var pctAbast = anterior.total_gasto_abastecimentos ? ((dAbast / anterior.total_gasto_abastecimentos) * 100).toFixed(0) : '';
                            causa = ' principalmente por aumento de combustível' + (pctAbast ? ' (+' + pctAbast + '%)' : '') + '.';
                        } else if (dComissao > 0 && dComissao >= Math.max(dAbast, dDesp)) causa = ' principalmente por aumento de comissão.';
                        else if (dDesp > 0 && dDesp >= Math.max(dAbast, dComissao)) causa = ' principalmente por aumento de despesas de viagem.';
                        else if (dFrete < 0) causa = ' com queda de faturamento.';
                    }
                    insights.push((lucroUlt < lucroAnt ? '⚠️ O lucro operacional caiu ' + Math.abs(varPct).toFixed(0) + '% em relação ao mês anterior' + causa : '✅ O lucro operacional subiu ' + varPct.toFixed(0) + '% em relação ao mês anterior.'));
                }
                var margemUlt = (ultimo.total_frete > 0) ? ((ultimo.lucro_operacional / ultimo.total_frete) * 100) : 0;
                if (margemUlt < 0) insights.push('📉 Margem operacional negativa no último mês.');
                (data.veiculos_consumo || []).filter(function(v) { return v.fora_media; }).slice(0, 2).forEach(function(v) { insights.push('⛽ Consumo abaixo da média no veículo ' + (v.placa || v.modelo || '') + '.'); });
                (data.ranking_rotas && data.ranking_rotas.bottom5 || []).filter(function(r) { return r.lucro < 0; }).slice(0, 1).forEach(function(r) { insights.push('📉 Rota com margem negativa: ' + (r.origem_destino || '') + '.'); });
                var elI = document.getElementById('bi-insights-ia-content');
                if (elI) elI.innerHTML = insights.length ? '<ul style="margin:0; padding-left:1.2rem;">' + insights.map(function(i) { return '<li>' + i + '</li>'; }).join('') + '</ul>' : '<p class="bi-text-muted">Nenhum insight no momento.</p>';
                document.getElementById('bi-insights-ia').style.display = 'block';
            }
        }
    }

    function renderCharts(data, visao) {
        visao = visao || 'geral';
        destroyCharts();
        var hist = data.historico_mensal || [];
        var labels = (data.labels || hist.map(function(x) { return x.mes_nome || x.mes_ano; })) || [];

        var wrapAbast = document.getElementById('wrapChartAbastTempo');
        if (wrapAbast) wrapAbast.style.display = (visao === 'geral') ? 'block' : 'none';

        if (visao === 'manutencao') {
            document.getElementById('tituloChartRotasTempo').textContent = 'Custo de manutenção por mês';
            if (document.getElementById('chartRotasTempo')) {
                charts.chartRotasTempo = new Chart(document.getElementById('chartRotasTempo'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            { label: 'Custo (R$)', data: hist.map(function(x) { return x.total_manutencoes || 0; }), backgroundColor: 'rgba(239,68,68,0.6)', borderColor: '#ef4444', borderWidth: 1 },
                            { label: 'Qtde', data: hist.map(function(x) { return x.quantidade_manutencoes || 0; }), backgroundColor: 'rgba(245,158,11,0.6)', borderColor: '#f59e0b', borderWidth: 1, yAxisID: 'y1' }
                        ]
                    },
                    options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true }, y1: { position: 'right', beginAtZero: true } } }
                });
            }
            document.getElementById('tituloChartFreteMensal').textContent = 'Custo por mês (R$)';
            if (document.getElementById('chartFreteMensal')) {
                charts.chartFreteMensal = new Chart(document.getElementById('chartFreteMensal'), {
                    type: 'line',
                    data: { labels: labels, datasets: [{ label: 'Custo', data: hist.map(function(x) { return x.total_manutencoes || 0; }), borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.1)', fill: true }] },
                    options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true } } }
                });
            }
            document.getElementById('tituloChartKmMensal').textContent = 'Quantidade de manutenções por mês';
            if (document.getElementById('chartKmMensal')) {
                charts.chartKmMensal = new Chart(document.getElementById('chartKmMensal'), {
                    type: 'bar',
                    data: { labels: labels, datasets: [{ label: 'Manutenções', data: hist.map(function(x) { return x.quantidade_manutencoes || 0; }), backgroundColor: 'rgba(245,158,11,0.7)' }] },
                    options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true } } }
                });
            }
            if (charts.chartDespViagemTipos) { charts.chartDespViagemTipos.destroy(); charts.chartDespViagemTipos = null; }
            var veiculosManut = data.veiculos_top_manutencao || [];
            document.getElementById('tituloChartTopVeiculos').textContent = 'Top veículos (custo manutenção)';
            if (document.getElementById('chartTopVeiculos') && veiculosManut.length) {
                charts.chartTopVeiculos = new Chart(document.getElementById('chartTopVeiculos'), {
                    type: 'bar',
                    data: {
                        labels: veiculosManut.map(function(v) { return v.placa || v.modelo || 'N/A'; }),
                        datasets: [
                            { label: 'Custo (R$)', data: veiculosManut.map(function(v) { return v.total_gasto || 0; }), backgroundColor: 'rgba(239,68,68,0.7)' },
                            { label: 'Qtde', data: veiculosManut.map(function(v) { return v.total_manutencoes || 0; }), backgroundColor: 'rgba(245,158,11,0.7)' }
                        ]
                    },
                    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: true, scales: { x: { beginAtZero: true } } }
                });
            }
            var pc = data.manut_preventiva_corretiva || { preventiva: { valor: 0 }, corretiva: { valor: 0 } };
            var vPrev = Number(pc.preventiva && pc.preventiva.valor) || 0;
            var vCorr = Number(pc.corretiva && pc.corretiva.valor) || 0;
            if (document.getElementById('chartManutPreventivaCorretiva') && (vPrev > 0 || vCorr > 0)) {
                if (charts.chartManutPreventivaCorretiva) { charts.chartManutPreventivaCorretiva.destroy(); charts.chartManutPreventivaCorretiva = null; }
                charts.chartManutPreventivaCorretiva = new Chart(document.getElementById('chartManutPreventivaCorretiva'), {
                    type: 'pie',
                    data: {
                        labels: ['Preventiva (R$)', 'Corretiva (R$)'],
                        datasets: [{ data: [vPrev, vCorr], backgroundColor: ['#10b981', '#ef4444'] }]
                    },
                    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'right' } } }
                });
            }
        } else if (visao === 'despesas_viagem') {
            document.getElementById('tituloChartRotasTempo').textContent = 'Despesas de viagem por mês';
            if (document.getElementById('chartRotasTempo')) {
                charts.chartRotasTempo = new Chart(document.getElementById('chartRotasTempo'), {
                    type: 'bar',
                    data: { labels: labels, datasets: [{ label: 'Desp. viagem (R$)', data: hist.map(function(x) { return x.total_despesas_viagem || 0; }), backgroundColor: 'rgba(59,130,246,0.7)' }] },
                    options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true } } }
                });
            }
            document.getElementById('tituloChartFreteMensal').textContent = 'Evolução (R$)';
            if (document.getElementById('chartFreteMensal')) {
                charts.chartFreteMensal = new Chart(document.getElementById('chartFreteMensal'), {
                    type: 'line',
                    data: { labels: labels, datasets: [{ label: 'Desp. viagem', data: hist.map(function(x) { return x.total_despesas_viagem || 0; }), borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)', fill: true }] },
                    options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true } } }
                });
            }
            document.getElementById('tituloChartKmMensal').textContent = 'Por mês';
            if (document.getElementById('chartKmMensal')) {
                charts.chartKmMensal = new Chart(document.getElementById('chartKmMensal'), {
                    type: 'bar',
                    data: { labels: labels, datasets: [{ label: 'R$', data: hist.map(function(x) { return x.total_despesas_viagem || 0; }), backgroundColor: 'rgba(16,185,129,0.7)' }] },
                    options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true } } }
                });
            }
            document.getElementById('tituloChartTopVeiculos').textContent = 'Despesas de viagem';
            if (document.getElementById('chartTopVeiculos')) { charts.chartTopVeiculos = null; }
            var tipos = data.despesas_viagem_tipos || {};
            var pedagio = Number(tipos.pedagios || 0);
            var alimentacao = Number(tipos.caixinha || 0);
            var hospedagem = Number(tipos.estacionamento || 0) + Number(tipos.lavagem || 0);
            var outros = Number(tipos.descarga || 0) + Number(tipos.borracharia || 0) + Number(tipos.eletrica_mecanica || 0) + Number(tipos.adiantamento || 0);
            var labelsPie = ['Pedágio', 'Alimentação', 'Hospedagem', 'Outros'];
            var dadosPie = [pedagio, alimentacao, hospedagem, outros];
            var ctx = document.getElementById('chartDespViagemTipos');
            if (ctx) {
                if (charts.chartDespViagemTipos) charts.chartDespViagemTipos.destroy();
                charts.chartDespViagemTipos = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: labelsPie,
                        datasets: [{ data: dadosPie, backgroundColor: ['#3b82f6','#10b981','#f59e0b','#8b5cf6'] }]
                    },
                    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'right' } } }
                });
            }
        } else if (visao === 'despesas_fixas') {
            document.getElementById('tituloChartRotasTempo').textContent = 'Despesas fixas pagas por mês';
            if (document.getElementById('chartRotasTempo')) {
                charts.chartRotasTempo = new Chart(document.getElementById('chartRotasTempo'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            { label: 'Valor (R$)', data: hist.map(function(x) { return x.total_despesas_fixas || 0; }), backgroundColor: 'rgba(139,92,246,0.7)' },
                            { label: 'Qtde', data: hist.map(function(x) { return x.quantidade_despesas_fixas || 0; }), backgroundColor: 'rgba(236,72,153,0.6)', yAxisID: 'y1' }
                        ]
                    },
                    options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true }, y1: { position: 'right', beginAtZero: true } } }
                });
            }
            document.getElementById('tituloChartFreteMensal').textContent = 'Total pago por mês';
            if (document.getElementById('chartFreteMensal')) {
                charts.chartFreteMensal = new Chart(document.getElementById('chartFreteMensal'), {
                    type: 'line',
                    data: { labels: labels, datasets: [{ label: 'Desp. fixas (R$)', data: hist.map(function(x) { return x.total_despesas_fixas || 0; }), borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,0.1)', fill: true }] },
                    options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true } } }
                });
            }
            document.getElementById('tituloChartKmMensal').textContent = 'Quantidade por mês';
            if (document.getElementById('chartKmMensal')) {
                charts.chartKmMensal = new Chart(document.getElementById('chartKmMensal'), {
                    type: 'bar',
                    data: { labels: labels, datasets: [{ label: 'Qtde', data: hist.map(function(x) { return x.quantidade_despesas_fixas || 0; }), backgroundColor: 'rgba(236,72,153,0.7)' }] },
                    options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true } } }
                });
            }
            document.getElementById('tituloChartTopVeiculos').textContent = 'Despesas fixas';
            if (document.getElementById('chartTopVeiculos')) {
                charts.chartTopVeiculos = null;
            }
        } else if (visao === 'abastecimento') {
            document.getElementById('tituloChartRotasTempo').textContent = 'Abastecimentos e Gasto ao longo do tempo';
            if (document.getElementById('chartRotasTempo')) {
                charts.chartRotasTempo = new Chart(document.getElementById('chartRotasTempo'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            { label: 'Qtde Abast.', data: hist.map(function(x) { return x.total_abastecimentos || 0; }), backgroundColor: 'rgba(79,156,249,0.7)', borderColor: '#4f9cf9', borderWidth: 1 },
                            { label: 'Gasto (R$)', data: hist.map(function(x) { return x.total_gasto_abastecimentos || 0; }), backgroundColor: 'rgba(52,211,153,0.7)', borderColor: '#34d399', borderWidth: 1, yAxisID: 'y1' }
                        ]
                    },
                    options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true }, y1: { position: 'right', beginAtZero: true } } }
                });
            }
            document.getElementById('tituloChartFreteMensal').textContent = 'Gasto com abastecimento por mês';
            if (document.getElementById('chartFreteMensal')) {
                charts.chartFreteMensal = new Chart(document.getElementById('chartFreteMensal'), {
                    type: 'line',
                    data: { labels: labels, datasets: [{ label: 'Gasto (R$)', data: hist.map(function(x) { return x.total_gasto_abastecimentos || 0; }), borderColor: '#4f9cf9', backgroundColor: 'rgba(79,156,249,0.1)', fill: true }] },
                    options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true } } }
                });
            }
            document.getElementById('tituloChartKmMensal').textContent = 'Quantidade de abastecimentos por mês';
            if (document.getElementById('chartKmMensal')) {
                charts.chartKmMensal = new Chart(document.getElementById('chartKmMensal'), {
                    type: 'bar',
                    data: { labels: labels, datasets: [{ label: 'Abastecimentos', data: hist.map(function(x) { return x.total_abastecimentos || 0; }), backgroundColor: 'rgba(245,158,11,0.7)', borderColor: '#f59e0b', borderWidth: 1 }] },
                    options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true } } }
                });
            }
            var veiculosAbast = data.veiculos_top_abastecimento || [];
            document.getElementById('tituloChartTopVeiculos').textContent = 'Top veículos (abastecimento)';
            if (document.getElementById('chartTopVeiculos') && veiculosAbast.length) {
                charts.chartTopVeiculos = new Chart(document.getElementById('chartTopVeiculos'), {
                    type: 'bar',
                    data: {
                        labels: veiculosAbast.map(function(v) { return v.placa || v.modelo || 'N/A'; }),
                        datasets: [
                            { label: 'Abastecimentos', data: veiculosAbast.map(function(v) { return v.total_abastecimentos || 0; }), backgroundColor: 'rgba(79,156,249,0.7)' },
                            { label: 'Gasto (R$)', data: veiculosAbast.map(function(v) { return v.total_gasto || 0; }), backgroundColor: 'rgba(52,211,153,0.7)' }
                        ]
                    },
                    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: true, scales: { x: { beginAtZero: true } } }
                });
            }
        } else {
            document.getElementById('tituloChartRotasTempo').textContent = 'Rotas e Faturamento ao longo do tempo';
            if (document.getElementById('chartRotasTempo')) {
                charts.chartRotasTempo = new Chart(document.getElementById('chartRotasTempo'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            { label: 'Qtde Rotas', data: hist.map(function(x) { return x.total_rotas || 0; }), backgroundColor: 'rgba(79,156,249,0.7)', borderColor: '#4f9cf9', borderWidth: 1 },
                            { label: 'Frete (R$)', data: hist.map(function(x) { return x.total_frete || 0; }), backgroundColor: 'rgba(52,211,153,0.7)', borderColor: '#34d399', borderWidth: 1, yAxisID: 'y1' }
                        ]
                    },
                    options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true }, y1: { position: 'right', beginAtZero: true } } }
                });
            }
            document.getElementById('tituloChartFreteMensal').textContent = 'Faturamento por mês (Frete)';
            if (document.getElementById('chartFreteMensal')) {
                charts.chartFreteMensal = new Chart(document.getElementById('chartFreteMensal'), {
                    type: 'line',
                    data: { labels: labels, datasets: [{ label: 'Frete', data: hist.map(function(x) { return x.total_frete || 0; }), borderColor: '#4f9cf9', backgroundColor: 'rgba(79,156,249,0.1)', fill: true }] },
                    options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true } } }
                });
            }
            if (visao === 'rotas') {
            document.getElementById('tituloChartKmMensal').textContent = 'Lucro por KM por mês';
            if (document.getElementById('chartKmMensal')) {
                charts.chartKmMensal = new Chart(document.getElementById('chartKmMensal'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{ label: 'Lucro/KM (R$)', data: hist.map(function(x) {
                            var km = x.total_km_rodados || 0;
                            var luc = x.lucro_operacional || 0;
                            return km > 0 ? (luc / km) : 0;
                        }), backgroundColor: 'rgba(34,197,94,0.7)', borderColor: '#22c55e', borderWidth: 1 }]
                    },
                    options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true } } }
                });
            }
        } else {
            document.getElementById('tituloChartKmMensal').textContent = 'Km rodados por mês';
            if (document.getElementById('chartKmMensal')) {
                charts.chartKmMensal = new Chart(document.getElementById('chartKmMensal'), {
                    type: 'bar',
                    data: { labels: labels, datasets: [{ label: 'Km', data: hist.map(function(x) { return x.total_km_rodados || 0; }), backgroundColor: 'rgba(245,158,11,0.7)', borderColor: '#f59e0b', borderWidth: 1 }] },
                    options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true } } }
                });
            }
        }
            if (visao === 'geral' && document.getElementById('chartAbastTempo')) {
                charts.chartAbastTempo = new Chart(document.getElementById('chartAbastTempo'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            { label: 'Qtde Abast.', data: hist.map(function(x) { return x.total_abastecimentos || 0; }), backgroundColor: 'rgba(167,139,250,0.7)', borderColor: '#a78bfa', borderWidth: 1 },
                            { label: 'Gasto (R$)', data: hist.map(function(x) { return x.total_gasto_abastecimentos || 0; }), backgroundColor: 'rgba(239,68,68,0.5)', borderColor: '#ef4444', borderWidth: 1, yAxisID: 'y1' }
                        ]
                    },
                    options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true }, y1: { position: 'right', beginAtZero: true } } }
                });
            }
            var veiculos = data.veiculos_top || [];
            document.getElementById('tituloChartTopVeiculos').textContent = 'Top veículos (rotas e km)';
            if (document.getElementById('chartTopVeiculos') && veiculos.length) {
                charts.chartTopVeiculos = new Chart(document.getElementById('chartTopVeiculos'), {
                    type: 'bar',
                    data: {
                        labels: veiculos.map(function(v) { return v.placa || v.modelo || 'N/A'; }),
                        datasets: [
                            { label: 'Rotas', data: veiculos.map(function(v) { return v.total_rotas || 0; }), backgroundColor: 'rgba(79,156,249,0.7)' },
                            { label: 'Km', data: veiculos.map(function(v) { return v.total_km || 0; }), backgroundColor: 'rgba(52,211,153,0.7)' }
                        ]
                    },
                    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: true, scales: { x: { beginAtZero: true } } }
                });
            }
        }
        document.getElementById('bi-charts').style.display = 'grid';
    }

    function formatCurrencyInd(value) {
        if (value == null) return '—';
        return 'R$ ' + Number(value).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function formatNumberInd(value) {
        if (value == null) return '—';
        return Number(value).toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }
    function showTooltipInd(elementId) {
        var element = document.getElementById(elementId);
        if (!element) return;
        var desc = element.getAttribute('data-desc');
        if (!desc) return;
        var existing = element.querySelector('.indicator-help-tooltip');
        if (existing) existing.remove();
        var tooltip = document.createElement('div');
        tooltip.className = 'indicator-help-tooltip';
        tooltip.textContent = desc;
        element.appendChild(tooltip);
        element.classList.add('active');
    }
    function hideTooltipInd(elementId) {
        var element = document.getElementById(elementId);
        if (!element) return;
        var tooltip = element.querySelector('.indicator-help-tooltip');
        if (tooltip && !element.classList.contains('keep-tooltip')) tooltip.remove();
        element.classList.remove('active');
    }
    function toggleTooltipInd(elementId) {
        var element = document.getElementById(elementId);
        if (!element) return;
        var tooltip = element.querySelector('.indicator-help-tooltip');
        if (tooltip) {
            element.classList.remove('keep-tooltip');
            hideTooltipInd(elementId);
        } else {
            element.classList.add('keep-tooltip');
            showTooltipInd(elementId);
        }
    }
    var INDICATORS_DEF = [
        { block: 'financeiro', name: 'Margem operacional (%)', desc: 'Lucro ÷ Faturamento', getValue: function(d) { var f = d.total_frete || 0; var l = d.lucro_operacional || 0; return f > 0 ? (l / f) * 100 : 0; }, format: function(v) { return v.toFixed(1) + '%'; }, type: 'percent', showVariation: true },
        { block: 'financeiro', name: 'Lucro operacional', desc: 'Receitas menos comissão, abast. e despesas de viagem', getValue: function(d) { return d.lucro_operacional; }, format: function(v) { return formatCurrencyInd(v); }, type: 'currency', showVariation: true },
        { block: 'financeiro', name: 'Lucro por KM', desc: 'Lucro operacional ÷ KM rodados', getValue: function(d) { var km = d.total_km_rodados || 0; return km > 0 ? (d.lucro_operacional || 0) / km : 0; }, format: function(v) { return formatCurrencyInd(v); }, type: 'currency', showVariation: true },
        { block: 'financeiro', name: 'Ticket médio por rota', desc: 'Faturamento ÷ quantidade de rotas', getValue: function(d) { var r = d.total_rotas || 0; var f = d.total_frete || 0; return r > 0 ? f / r : 0; }, format: function(v) { return formatCurrencyInd(v); }, type: 'currency', showVariation: true },
        { block: 'financeiro', name: 'Crescimento de receita', desc: 'Variação % do faturamento em relação ao mês anterior', getValue: function(d, index, allData) { if (index === 0) return null; var prev = allData[index - 1].total_frete || 0; var cur = d.total_frete || 0; if (prev === 0) return cur > 0 ? 100 : 0; return ((cur - prev) / prev) * 100; }, format: function(v) { return v === null ? '-' : v.toFixed(2) + '%'; }, type: 'percent', showVariation: false },
        { block: 'operacional', name: 'Veículos ativos', desc: 'Veículos que realizaram rotas no período', getValue: function(d) { return d.quantidade_veiculos_ativos; }, format: function(v) { return formatNumberInd(v); }, type: 'number', showVariation: true },
        { block: 'operacional', name: 'Rotas realizadas', desc: 'Total de rotas no período', getValue: function(d) { return d.total_rotas; }, format: function(v) { return formatNumberInd(v); }, type: 'number', showVariation: true },
        { block: 'operacional', name: 'KM rodados', desc: 'Quilômetros rodados no período', getValue: function(d) { return d.total_km_rodados; }, format: function(v) { return formatNumberInd(v) + ' km'; }, type: 'number', showVariation: true },
        { block: 'operacional', name: 'KM por veículo', desc: 'Média de KM por veículo ativo', getValue: function(d) { var v = d.quantidade_veiculos_ativos || 0; return v > 0 ? (d.total_km_rodados || 0) / v : 0; }, format: function(v) { return formatNumberInd(v) + ' km'; }, type: 'number', showVariation: true },
        { block: 'operacional', name: 'Rotas por veículo', desc: 'Média de rotas por veículo ativo', getValue: function(d) { var v = d.quantidade_veiculos_ativos || 0; return v > 0 ? (d.total_rotas || 0) / v : 0; }, format: function(v) { return formatNumberInd(v); }, type: 'number', showVariation: true },
        { block: 'operacional', name: 'Utilização da frota (%)', desc: 'Aproximação: veículos ativos em relação ao total (baseado em rotas)', getValue: function(d) { return d.quantidade_veiculos_ativos; }, format: function(v) { return formatNumberInd(v) + ' veíc.'; }, type: 'number', showVariation: true },
        { block: 'custos', name: 'Custo por KM', desc: '(Abast + Manut + Desp viagem) ÷ KM', getValue: function(d) { var km = d.total_km_rodados || 0; if (km <= 0) return 0; var c = (d.total_gasto_abastecimentos || 0) + (d.total_manutencoes || 0) + (d.total_despesas_viagem || 0); return c / km; }, format: function(v) { return formatCurrencyInd(v); }, type: 'currency', showVariation: true },
        { block: 'custos', name: 'Combustível por KM', desc: 'Gasto com abastecimento ÷ KM', getValue: function(d) { var km = d.total_km_rodados || 0; return km > 0 ? (d.total_gasto_abastecimentos || 0) / km : 0; }, format: function(v) { return formatCurrencyInd(v); }, type: 'currency', showVariation: true },
        { block: 'custos', name: 'Manutenção por KM', desc: 'Custo de manutenção ÷ KM', getValue: function(d) { var km = d.total_km_rodados || 0; return km > 0 ? (d.total_manutencoes || 0) / km : 0; }, format: function(v) { return formatCurrencyInd(v); }, type: 'currency', showVariation: true },
        { block: 'custos', name: 'Despesas de viagem por KM', desc: 'Despesas de viagem ÷ KM', getValue: function(d) { var km = d.total_km_rodados || 0; return km > 0 ? (d.total_despesas_viagem || 0) / km : 0; }, format: function(v) { return formatCurrencyInd(v); }, type: 'currency', showVariation: true },
        { block: 'custos', name: 'Gasto abastecimentos', desc: 'Total gasto com combustível (incl. ARLA)', getValue: function(d) { return d.total_gasto_abastecimentos; }, format: function(v) { return formatCurrencyInd(v); }, type: 'currency', showVariation: true },
        { block: 'custos', name: 'Despesas de viagem (total)', desc: 'Total de despesas de viagem', getValue: function(d) { return d.total_despesas_viagem; }, format: function(v) { return formatCurrencyInd(v); }, type: 'currency', showVariation: true }
    ];
    var BLOCK_LABELS = { financeiro: '🔹 Financeiro', operacional: '🔹 Operacional', custos: '🔹 Custos' };
    function buildIndicatorsTable(data) {
        if (!data || data.length === 0) return;
        var table = document.getElementById('indicatorsTable');
        var thead = table && table.querySelector('thead');
        var tbody = document.getElementById('indicatorsTableBody');
        if (!table || !thead || !tbody) return;
        tbody.innerHTML = '';
        var headerRow = thead.querySelector('tr');
        if (headerRow) {
            var firstHeader = headerRow.querySelector('th:first-child');
            headerRow.innerHTML = '';
            if (firstHeader) headerRow.appendChild(firstHeader);
            else {
                var th = document.createElement('th');
                th.style.cssText = 'position: sticky; left: 0; background: var(--bg-tertiary); z-index: 10; padding: 12px; text-align: left; border: 1px solid var(--border-color); border-bottom: 2px solid var(--border-color); font-weight: 600; min-width: 150px;';
                th.textContent = 'Indicador';
                headerRow.appendChild(th);
            }
        }
        data.forEach(function(month) {
            var th = document.createElement('th');
            th.style.cssText = 'padding: 12px 8px; text-align: center; border: 1px solid var(--border-color); border-bottom: 2px solid var(--border-color); font-weight: 600; min-width: 120px; background: var(--bg-tertiary);';
            th.textContent = month.mes_nome || month.mes_ano || '';
            if (headerRow) headerRow.appendChild(th);
        });
        var lastBlock = '';
        INDICATORS_DEF.forEach(function(ind) {
            var block = ind.block || '';
            if (block && block !== lastBlock) {
                lastBlock = block;
                var sepRow = document.createElement('tr');
                var sepCell = document.createElement('td');
                sepCell.colSpan = 1 + data.length;
                sepCell.style.cssText = 'background: var(--bg-tertiary); font-weight: 700; padding: 10px 12px; border: 1px solid var(--border-color); color: var(--text-primary);';
                sepCell.textContent = BLOCK_LABELS[block] || block;
                sepRow.appendChild(sepCell);
                tbody.appendChild(sepRow);
            }
            var row = document.createElement('tr');
            var helpId = 'help-' + ind.name.replace(/\s+/g, '-').toLowerCase() + '-' + Math.random().toString(36).substr(2, 9);
            var escapedDesc = (ind.desc || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
            var nameCell = document.createElement('td');
            nameCell.innerHTML = '<div style="display: flex; align-items: center; gap: 2px; flex-wrap: nowrap; position: relative;">' +
                '<span class="indicator-name" style="flex: 1; min-width: 0;">' + ind.name + '</span>' +
                '<span class="indicator-help" id="' + helpId + '" data-desc="' + escapedDesc + '" style="cursor: help; flex-shrink: 0; position: relative;" onmouseenter="window.showTooltipInd(\'' + helpId + '\')" onmouseleave="window.hideTooltipInd(\'' + helpId + '\')" onclick="event.stopPropagation(); window.toggleTooltipInd(\'' + helpId + '\'); return false;">(?)</span></div>';
            row.appendChild(nameCell);
            data.forEach(function(monthData, index) {
                var value = ind.getValue(monthData, index, data);
                var formattedValue = value === null ? '-' : ind.format(value);
                var variationHtml = '';
                if (index > 0 && ind.showVariation && value !== null && typeof value === 'number') {
                    var previousValue = ind.getValue(data[index - 1], index - 1, data);
                    if (previousValue !== null && previousValue !== 0 && typeof previousValue === 'number') {
                        var variation = ((value - previousValue) / Math.abs(previousValue)) * 100;
                        var variationClass = variation > 0 ? 'positive' : variation < 0 ? 'negative' : 'neutral';
                        var arrow = variation > 0 ? '↑' : variation < 0 ? '↓' : '';
                        variationHtml = '<span class="variation ' + variationClass + '">' + Math.abs(variation).toFixed(2) + '%' + arrow + '</span>';
                    } else if (previousValue === 0 && value > 0) { variationHtml = '<span class="variation positive">100%↑</span>'; }
                    else if (previousValue > 0 && value === 0) { variationHtml = '<span class="variation negative">100%↓</span>'; }
                }
                if (!ind.showVariation && ind.type === 'percent' && value !== null && typeof value === 'number') {
                    var vc = value > 0 ? 'positive' : value < 0 ? 'negative' : 'neutral';
                    var ar = value > 0 ? '↑' : value < 0 ? '↓' : '';
                    if (value !== 0) variationHtml = '<span class="variation ' + vc + '">' + ar + '</span>';
                }
                var cell = document.createElement('td');
                cell.innerHTML = '<div class="cell-value"><span class="value-number">' + formattedValue + '</span>' + variationHtml + '</div>';
                if ((ind.name === 'Lucro operacional' || ind.name === 'Lucro Operacional') && value < 0) {
                    var span = cell.querySelector('.value-number');
                    if (span) span.style.color = '#f44336';
                }
                row.appendChild(cell);
            });
            tbody.appendChild(row);
        });
        var alertasEl = document.getElementById('bi-indicadores-alertas');
        if (alertasEl && data.length > 0) {
            var ult = data[data.length - 1];
            var alertas = [];
            var margem = (ult.total_frete > 0) ? ((ult.lucro_operacional || 0) / ult.total_frete) * 100 : 0;
            if (margem < 0) alertas.push('📉 Margem operacional negativa no último mês.');
            if (data.length >= 2) {
                var ant = data[data.length - 2];
                var freteAnt = ant.total_frete || 0;
                var freteUlt = ult.total_frete || 0;
                if (freteAnt > 0 && freteUlt < freteAnt) {
                    var queda = ((freteAnt - freteUlt) / freteAnt * 100).toFixed(0);
                    alertas.push('📉 Queda de faturamento de ' + queda + '% em relação ao mês anterior.');
                }
            }
            var custoKm = (ult.total_km_rodados > 0) ? ((ult.total_gasto_abastecimentos || 0) + (ult.total_manutencoes || 0) + (ult.total_despesas_viagem || 0)) / ult.total_km_rodados : 0;
            var mediaCustoKm = 0;
            if (data.length > 0) {
                var soma = 0, somaKm = 0;
                data.forEach(function(m) {
                    soma += (m.total_gasto_abastecimentos || 0) + (m.total_manutencoes || 0) + (m.total_despesas_viagem || 0);
                    somaKm += m.total_km_rodados || 0;
                });
                mediaCustoKm = somaKm > 0 ? soma / somaKm : 0;
            }
            if (mediaCustoKm > 0 && custoKm > mediaCustoKm * 1.2) alertas.push('⚠️ Custo por KM no último mês acima da média do período.');
            if (alertas.length > 0) {
                alertasEl.innerHTML = '<h4 style="margin:0 0 0.5rem 0;">🔹 Alertas</h4><ul style="margin:0; padding-left:1.2rem;">' + alertas.map(function(a) { return '<li>' + a + '</li>'; }).join('') + '</ul>';
                alertasEl.style.display = 'block';
            } else {
                alertasEl.innerHTML = '<h4 style="margin:0 0 0.5rem 0;">🔹 Alertas</h4><p class="bi-text-muted">Nenhum alerta no período.</p>';
                alertasEl.style.display = 'block';
            }
        }
    }
    function loadPerformanceIndicators() {
        var loadingEl = document.getElementById('indicatorsLoading');
        var containerEl = document.getElementById('indicatorsTableContainer');
        if (loadingEl) loadingEl.style.display = 'block';
        if (containerEl) containerEl.style.display = 'none';
        var a = document.getElementById('filtro-ano');
        var m = document.getElementById('filtro-mes');
        var ano = (a && a.value) ? String(a.value).trim() : '';
        var mes = (m && m.value) ? String(m.value).trim() : '';
        var url = baseUrl + '/performance_indicators.php?visao=geral' + (ano ? '&ano=' + encodeURIComponent(ano) : '') + (mes ? '&mes=' + encodeURIComponent(mes) : '');
        fetch(url, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(json) {
                if (json.success && json.data && json.data.historico_mensal) {
                    buildIndicatorsTable(json.data.historico_mensal);
                    if (loadingEl) loadingEl.style.display = 'none';
                    if (containerEl) containerEl.style.display = 'block';
                } else {
                    if (loadingEl) { loadingEl.innerHTML = '<div style="text-align: center; padding: 40px; color: #dc3545;"><i class="fas fa-exclamation-triangle" style="font-size: 2rem;"></i><p style="margin-top: 15px;">Erro ao carregar indicadores.</p></div>'; loadingEl.style.display = 'block'; }
                    if (containerEl) containerEl.style.display = 'none';
                }
            })
            .catch(function() {
                if (loadingEl) { loadingEl.innerHTML = '<div style="text-align: center; padding: 40px; color: #dc3545;"><i class="fas fa-exclamation-triangle" style="font-size: 2rem;"></i><p style="margin-top: 15px;">Erro ao carregar indicadores.</p></div>'; loadingEl.style.display = 'block'; }
                if (containerEl) containerEl.style.display = 'none';
            });
    }
    function exportIndicatorsToExcel() {
        var table = document.getElementById('indicatorsTable');
        if (!table) { alert('Tabela não encontrada. Aguarde o carregamento dos indicadores.'); return; }
        var data = [];
        var headers = ['Indicador'];
        table.querySelectorAll('thead th').forEach(function(th, index) { if (index > 0) headers.push(th.textContent.trim()); });
        data.push(headers);
        table.querySelectorAll('tbody tr').forEach(function(tr) {
            var row = [];
            tr.querySelectorAll('td').forEach(function(cell, index) {
                if (index === 0) {
                    var name = cell.querySelector('.indicator-name');
                    row.push(name ? name.textContent.trim().replace(/\s*\(\?\)\s*$/, '') : cell.textContent.trim().replace(/\s*\(\?\)\s*$/, ''));
                } else {
                    var cellValue = cell.querySelector('.cell-value');
                    if (cellValue) {
                        var parts = [];
                        var valueNumber = cellValue.querySelector('.value-number');
                        var variation = cellValue.querySelector('.variation');
                        if (valueNumber) parts.push(valueNumber.textContent.trim());
                        if (variation) parts.push(variation.textContent.trim());
                        row.push(parts.join(' ') || cell.textContent.trim());
                    } else row.push(cell.textContent.trim());
                }
            });
            data.push(row);
        });
        var csv = data.map(function(row) {
            return row.map(function(cell) {
                var s = String(cell || '').trim();
                if (s.indexOf(',') !== -1 || s.indexOf(';') !== -1 || s.indexOf('"') !== -1 || s.indexOf('\n') !== -1 || s.indexOf('\r') !== -1) return '"' + s.replace(/"/g, '""') + '"';
                return s;
            }).join(';');
        }).join('\r\n');
        var BOM = '\uFEFF';
        var blob = new Blob([BOM + csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'indicadores_desempenho_' + new Date().toISOString().split('T')[0] + '.csv';
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
    }
    function exportTableToCsv() {
        var table = document.getElementById('bi-tabela');
        if (!table) return;
        var data = [];
        table.querySelectorAll('thead tr').forEach(function(tr) {
            var row = [];
            tr.querySelectorAll('th').forEach(function(th) { row.push(th.textContent.trim()); });
            data.push(row);
        });
        table.querySelectorAll('tbody tr').forEach(function(tr) {
            var row = [];
            tr.querySelectorAll('td').forEach(function(td) { row.push(td.textContent.trim()); });
            data.push(row);
        });
        var csv = data.map(function(row) {
            return row.map(function(cell) {
                var s = String(cell || '').trim();
                if (s.indexOf(',') !== -1 || s.indexOf(';') !== -1 || s.indexOf('"') !== -1 || s.indexOf('\n') !== -1) return '"' + s.replace(/"/g, '""') + '"';
                return s;
            }).join(';');
        }).join('\r\n');
        var BOM = '\uFEFF';
        var blob = new Blob([BOM + csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'bi_panorama_' + new Date().toISOString().split('T')[0] + '.csv';
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
    }
    function exportChartToPng() {
        var canvas = document.querySelector('#bi-charts canvas');
        if (!canvas || !canvas.id) return;
        try {
            var url = canvas.toDataURL('image/png');
            var link = document.createElement('a');
            link.href = url;
            link.download = 'bi_grafico_' + (canvas.id || 'chart') + '_' + new Date().toISOString().split('T')[0] + '.png';
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        } catch (e) { alert('Não foi possível exportar o gráfico.'); }
    }
    window.showTooltipInd = showTooltipInd;
    window.hideTooltipInd = hideTooltipInd;
    window.toggleTooltipInd = toggleTooltipInd;

    function renderTable(data, visao) {
        visao = visao || 'geral';
        var hist = data.historico_mensal || [];
        var thead = document.querySelector('#bi-tabela thead tr');
        var tbody = document.querySelector('#bi-tabela tbody');
        var titulo = document.getElementById('bi-table-titulo');
        function sum(attr) { return hist.reduce(function(acc, row) { return acc + (Number(row[attr]) || 0); }, 0); }
        var semDados = '<tr><td colspan="20" class="bi-text-muted" style="text-align:center; padding:2rem;">Nenhum dado no período selecionado.</td></tr>';

        if (visao === 'abastecimento') {
            titulo.textContent = 'Panorama mensal – Abastecimento';
            thead.innerHTML = '<th>Mês</th><th>Qtde Abast.</th><th>Gasto (R$)</th>';
            if (hist.length === 0) tbody.innerHTML = semDados;
            else tbody.innerHTML = hist.map(function(row) {
                return '<tr><td>' + (row.mes_nome || row.mes_ano) + '</td><td>' + formatNum(row.total_abastecimentos) + '</td><td>' + formatMoney(row.total_gasto_abastecimentos) + '</td></tr>';
            }).join('') + '<tr class="bi-table-total"><td><strong>Total</strong></td><td>' + formatNum(sum('total_abastecimentos')) + '</td><td>' + formatMoney(sum('total_gasto_abastecimentos')) + '</td></tr>';
        } else if (visao === 'rotas') {
            titulo.textContent = 'Panorama mensal – Rotas';
            thead.innerHTML = '<th>Mês</th><th>Rotas</th><th>Km</th><th>Frete (R$)</th><th>Comissão (R$)</th><th>Lucro oper.</th>';
            if (hist.length === 0) tbody.innerHTML = semDados;
            else tbody.innerHTML = hist.map(function(row) {
                return '<tr><td>' + (row.mes_nome || row.mes_ano) + '</td><td>' + formatNum(row.total_rotas) + '</td><td>' + formatNum(row.total_km_rodados) + '</td><td>' + formatMoney(row.total_frete) + '</td><td>' + formatMoney(row.total_comissao) + '</td><td>' + formatMoney(row.lucro_operacional) + '</td></tr>';
            }).join('') + '<tr class="bi-table-total"><td><strong>Total</strong></td><td>' + formatNum(sum('total_rotas')) + '</td><td>' + formatNum(sum('total_km_rodados')) + '</td><td>' + formatMoney(sum('total_frete')) + '</td><td>' + formatMoney(sum('total_comissao')) + '</td><td>' + formatMoney(sum('lucro_operacional')) + '</td></tr>';
        } else if (visao === 'manutencao') {
            titulo.textContent = 'Panorama mensal – Manutenção';
            thead.innerHTML = '<th>Mês</th><th>Qtde</th><th>Custo (R$)</th>';
            if (hist.length === 0) tbody.innerHTML = semDados;
            else tbody.innerHTML = hist.map(function(row) {
                return '<tr><td>' + (row.mes_nome || row.mes_ano) + '</td><td>' + formatNum(row.quantidade_manutencoes) + '</td><td>' + formatMoney(row.total_manutencoes) + '</td></tr>';
            }).join('') + '<tr class="bi-table-total"><td><strong>Total</strong></td><td>' + formatNum(sum('quantidade_manutencoes')) + '</td><td>' + formatMoney(sum('total_manutencoes')) + '</td></tr>';
        } else if (visao === 'despesas_viagem') {
            titulo.textContent = 'Panorama mensal – Despesas de viagem';
            thead.innerHTML = '<th>Mês</th><th>Total (R$)</th>';
            if (hist.length === 0) tbody.innerHTML = semDados;
            else tbody.innerHTML = hist.map(function(row) {
                return '<tr><td>' + (row.mes_nome || row.mes_ano) + '</td><td>' + formatMoney(row.total_despesas_viagem) + '</td></tr>';
            }).join('') + '<tr class="bi-table-total"><td><strong>Total</strong></td><td>' + formatMoney(sum('total_despesas_viagem')) + '</td></tr>';
        } else if (visao === 'despesas_fixas') {
            titulo.textContent = 'Panorama mensal – Despesas fixas';
            thead.innerHTML = '<th>Mês</th><th>Qtde</th><th>Total pago (R$)</th>';
            if (hist.length === 0) tbody.innerHTML = semDados;
            else tbody.innerHTML = hist.map(function(row) {
                return '<tr><td>' + (row.mes_nome || row.mes_ano) + '</td><td>' + formatNum(row.quantidade_despesas_fixas) + '</td><td>' + formatMoney(row.total_despesas_fixas) + '</td></tr>';
            }).join('') + '<tr class="bi-table-total"><td><strong>Total</strong></td><td>' + formatNum(sum('quantidade_despesas_fixas')) + '</td><td>' + formatMoney(sum('total_despesas_fixas')) + '</td></tr>';
        } else {
            titulo.textContent = 'Panorama mensal – Geral';
            thead.innerHTML = '<th>Mês</th><th>Rotas</th><th>Km</th><th>Frete</th><th>Comissão</th><th>Abast.</th><th>Gasto abast.</th><th>Desp. viagem</th><th>Manut.</th><th>Desp. fixas</th><th>Lucro oper.</th>';
            if (hist.length === 0) tbody.innerHTML = semDados;
            else tbody.innerHTML = hist.map(function(row) {
                return '<tr><td>' + (row.mes_nome || row.mes_ano) + '</td><td>' + formatNum(row.total_rotas) + '</td><td>' + formatNum(row.total_km_rodados) + '</td><td>' + formatMoney(row.total_frete) + '</td><td>' + formatMoney(row.total_comissao) + '</td><td>' + formatNum(row.total_abastecimentos) + '</td><td>' + formatMoney(row.total_gasto_abastecimentos) + '</td><td>' + formatMoney(row.total_despesas_viagem) + '</td><td>' + formatMoney(row.total_manutencoes) + '</td><td>' + formatMoney(row.total_despesas_fixas) + '</td><td>' + formatMoney(row.lucro_operacional) + '</td></tr>';
            }).join('') + '<tr class="bi-table-total"><td><strong>Total</strong></td><td>' + formatNum(sum('total_rotas')) + '</td><td>' + formatNum(sum('total_km_rodados')) + '</td><td>' + formatMoney(sum('total_frete')) + '</td><td>' + formatMoney(sum('total_comissao')) + '</td><td>' + formatNum(sum('total_abastecimentos')) + '</td><td>' + formatMoney(sum('total_gasto_abastecimentos')) + '</td><td>' + formatMoney(sum('total_despesas_viagem')) + '</td><td>' + formatMoney(sum('total_manutencoes')) + '</td><td>' + formatMoney(sum('total_despesas_fixas')) + '</td><td>' + formatMoney(sum('lucro_operacional')) + '</td></tr>';
        }
        document.getElementById('bi-table-wrap').style.display = 'block';
    }

    function aplicarFiltroPeriodo(data) {
        var anoEl = document.getElementById('filtro-ano');
        var mesEl = document.getElementById('filtro-mes');
        var ano = (anoEl && anoEl.value) ? String(anoEl.value).trim() : '';
        var mes = (mesEl && mesEl.value) ? String(mesEl.value).trim() : '';
        var hist = (data.historico_mensal || []).slice();
        if (!ano && !mes) return data;
        var filtered = hist;
        if (ano) {
            filtered = hist.filter(function(row) {
                var ma = row.mes_ano || '';
                if (ma.length < 7) return false;
                if (ma.substring(0, 4) !== ano) return false;
                if (mes) {
                    var mesStr = mes.length === 1 ? '0' + mes : mes;
                    return ma === ano + '-' + mesStr;
                }
                return true;
            });
        } else if (mes) {
            var mesStr = mes.length === 1 ? '0' + mes : mes;
            filtered = hist.filter(function(row) {
                var ma = row.mes_ano || '';
                return ma.length >= 7 && ma.substring(5, 7) === mesStr;
            });
        }
        var out = Object.assign({}, data);
        out.historico_mensal = filtered;
        out.labels = filtered.map(function(x) { return x.mes_nome || x.mes_ano; });
        return out;
    }

    function loadData() {
        var visao = (document.getElementById('filtro-visao') && document.getElementById('filtro-visao').value) || 'geral';
        var secIndicadores = document.getElementById('bi-indicadores-desempenho');
        if (secIndicadores) secIndicadores.style.display = 'none';
        if (visao === 'indicadores_desempenho') {
            showLoading(true);
            var anoInd = (document.getElementById('filtro-ano') && document.getElementById('filtro-ano').value) ? String(document.getElementById('filtro-ano').value).trim() : '';
            var mesInd = (document.getElementById('filtro-mes') && document.getElementById('filtro-mes').value) ? String(document.getElementById('filtro-mes').value).trim() : '';
            var urlInd = baseUrl + '/performance_indicators.php?visao=geral' + (anoInd ? '&ano=' + encodeURIComponent(anoInd) : '') + (mesInd ? '&mes=' + encodeURIComponent(mesInd) : '');
            fetch(urlInd, { credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(json) {
                    showLoading(false);
                    if (json.success !== true || !json.data) {
                        showError(json.message || json.error || 'Resposta inválida da API.');
                        return;
                    }
                    document.getElementById('bi-kpis').style.display = 'none';
                    document.getElementById('bi-charts').style.display = 'none';
                    document.getElementById('bi-table-wrap').style.display = 'none';
                    var saudeFrota = document.getElementById('bi-saude-frota');
                    if (saudeFrota) saudeFrota.style.display = 'none';
                    hideAllExtraBlocks();
                    if (secIndicadores) secIndicadores.style.display = 'block';
                    var hist = json.data.historico_mensal || [];
                    var loadingEl = document.getElementById('indicatorsLoading');
                    var containerEl = document.getElementById('indicatorsTableContainer');
                    if (loadingEl) loadingEl.style.display = 'block';
                    if (containerEl) containerEl.style.display = 'none';
                    buildIndicatorsTable(hist);
                    if (loadingEl) loadingEl.style.display = 'none';
                    if (containerEl) containerEl.style.display = 'block';
                    if (json.data && typeof showAvisoDadosIncompletos === 'function') showAvisoDadosIncompletos(json.data);
                    if (typeof updateUrlFromFilters === 'function') updateUrlFromFilters();
                })
                .catch(function(err) {
                    showLoading(false);
                    showError('Erro ao carregar dados: ' + err.message);
                });
            return;
        }
        showLoading(true);
        var ano = (document.getElementById('filtro-ano') && document.getElementById('filtro-ano').value) ? String(document.getElementById('filtro-ano').value).trim() : '';
        var mes = (document.getElementById('filtro-mes') && document.getElementById('filtro-mes').value) ? String(document.getElementById('filtro-mes').value).trim() : '';
        var url = baseUrl + '/performance_indicators.php?visao=' + encodeURIComponent(visao) + (ano ? '&ano=' + encodeURIComponent(ano) : '') + (mes ? '&mes=' + encodeURIComponent(mes) : '');
        fetch(url, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(json) {
                showLoading(false);
                if (json.success !== true || !json.data) {
                    showError(json.message || json.error || 'Resposta inválida da API.');
                    return;
                }
                var dataFiltrada = ano ? json.data : aplicarFiltroPeriodo(json.data);
                var v = json.visao || visao;
                showAvisoDadosIncompletos(json.data);
                renderKPIs(dataFiltrada, v);
                renderCharts(dataFiltrada, v);
                renderTable(dataFiltrada, v);
                renderExtraBlocks(json.data, v);
                if (typeof updateUrlFromFilters === 'function') updateUrlFromFilters();
            })
            .catch(function(err) {
                showLoading(false);
                showError('Erro ao carregar dados: ' + err.message);
            });
    }

    function applyParamsFromUrl() {
        var params = {};
        (window.location.search || '').replace(/^\?/, '').split('&').forEach(function(p) {
            var parts = p.split('=');
            if (parts.length >= 2) params[decodeURIComponent(parts[0])] = decodeURIComponent(parts.slice(1).join('='));
        });
        var v = document.getElementById('filtro-visao');
        var a = document.getElementById('filtro-ano');
        var m = document.getElementById('filtro-mes');
        if (params.visao && v) { for (var i = 0; i < v.options.length; i++) { if (v.options[i].value === params.visao) { v.value = params.visao; break; } } }
        if (params.ano && a) a.value = params.ano;
        if (params.mes !== undefined && m) m.value = params.mes === '' ? '' : params.mes;
    }
    function updateUrlFromFilters() {
        var v = document.getElementById('filtro-visao');
        var a = document.getElementById('filtro-ano');
        var m = document.getElementById('filtro-mes');
        var visao = (v && v.value) ? v.value : 'geral';
        var ano = (a && a.value) ? a.value : '';
        var mes = (m && m.value) ? m.value : '';
        var q = '?visao=' + encodeURIComponent(visao) + (ano ? '&ano=' + encodeURIComponent(ano) : '') + (mes ? '&mes=' + encodeURIComponent(mes) : '');
        if (window.history && window.history.replaceState) window.history.replaceState({}, '', window.location.pathname + q);
    }
    document.getElementById('btn-aplicar').addEventListener('click', function() { loadData(); updateUrlFromFilters(); });
    var filtroVisao = document.getElementById('filtro-visao');
    if (filtroVisao) filtroVisao.addEventListener('change', function() { loadData(); updateUrlFromFilters(); });
    var filtroAno = document.getElementById('filtro-ano');
    var filtroMes = document.getElementById('filtro-mes');
    if (filtroAno) filtroAno.addEventListener('change', function() { loadData(); updateUrlFromFilters(); });
    if (filtroMes) filtroMes.addEventListener('change', function() { loadData(); updateUrlFromFilters(); });
    var btnMesAtual = document.getElementById('btn-mes-atual');
    if (btnMesAtual) btnMesAtual.addEventListener('click', function() {
        var now = new Date();
        var a = document.getElementById('filtro-ano');
        var m = document.getElementById('filtro-mes');
        if (a) a.value = now.getFullYear();
        if (m) m.value = String(now.getMonth() + 1);
        loadData();
        updateUrlFromFilters();
    });
    var btnIndAtualizar = document.getElementById('btn-indicadores-atualizar');
    if (btnIndAtualizar) btnIndAtualizar.addEventListener('click', loadPerformanceIndicators);
    var btnIndExcel = document.getElementById('btn-indicadores-excel');
    if (btnIndExcel) btnIndExcel.addEventListener('click', exportIndicatorsToExcel);
    var btnExportTabela = document.getElementById('btn-exportar-tabela-csv');
    if (btnExportTabela) btnExportTabela.addEventListener('click', exportTableToCsv);
    var btnExportGrafico = document.getElementById('btn-exportar-grafico-png');
    if (btnExportGrafico) btnExportGrafico.addEventListener('click', exportChartToPng);
    var simDiesel = document.getElementById('sim-diesel-pct');
    var simComissao = document.getElementById('sim-comissao-pct');
    if (simDiesel) simDiesel.addEventListener('input', function() { if (typeof simuladorUpdate === 'function') simuladorUpdate(); });
    if (simComissao) simComissao.addEventListener('input', function() { if (typeof simuladorUpdate === 'function') simuladorUpdate(); });
    applyParamsFromUrl();
    loadData();
})();
    </script>
</body>
</html>
