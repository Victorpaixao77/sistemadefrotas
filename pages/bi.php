<?php
/**
 * Indicadores de Desempenho - Página BI (teste)
 * Resumos gráficos editáveis, foco em rotas. Estilo profissional tipo BI.
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_once '../includes/permissions.php';
require_once __DIR__ . '/../includes/sf_page_help.php';

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
    <link rel="stylesheet" href="../css/bi-premium.css?v=<?php echo sf_asset_v(); ?>">
    <link rel="icon" type="image/png" href="../logo.png">
    <?php sf_page_help_css('..'); ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
    <script src="../js/bi-charts-v4.js?v=<?php echo sf_asset_v(); ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2pdf.js@0.10.1/dist/html2pdf.bundle.min.js"></script>
    <script>
    (function () {
        if (typeof Chart === 'undefined') return;
        Chart.defaults.font.family = "'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif";
        Chart.defaults.responsive = true;
        Chart.defaults.maintainAspectRatio = false;
    })();
    </script>
    <style>
        /* Conteúdo BI — um único scroll na página (evitar overflow-x em cascata → scrollbar duplicada) */
        body.page-bi { overflow-x: hidden; }
        body.page-bi .main-content,
        body.page-bi .dashboard-content,
        body.page-bi .bi-layout,
        body.page-bi .bi-content {
            min-width: 0;
            max-width: 100%;
            box-sizing: border-box;
            overflow: visible;
        }
        .bi-indicadores-section { min-width: 0; max-width: 100%; box-sizing: border-box; }
        .bi-indicadores-box { width: 100%; max-width: 100%; box-sizing: border-box; }
        #indicatorsTableContainer { min-width: 0; width: 100%; }
        .bi-extra-block { min-width: 0; max-width: 100%; box-sizing: border-box; }
        .bi-table-card { min-width: 0; max-width: 100%; box-sizing: border-box; }

        /* BI usa as variáveis do sistema (--bg-primary, --card-bg, etc.) */
        /* Sem título duplicado: o nome da página vem do header superior (.header-title) */
        .bi-header { display: flex; align-items: center; justify-content: flex-start; flex-wrap: wrap; gap: 0.75rem; margin-bottom: var(--gutter); }
        .bi-filtros { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
        .bi-filtros .bi-filtro-item { display: flex; align-items: center; gap: 0.35rem; }
        .bi-filtros .bi-filtro-item label { font-size: 0.8rem; color: var(--text-muted); margin: 0; white-space: nowrap; }
        .bi-filtros select { padding: 0.45rem 0.6rem; border-radius: var(--btn-border-radius); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); font-size: 0.9rem; min-width: 0; }
        .bi-filtros #filtro-visao { min-width: 180px; }
        .bi-filtros #filtro-ano { width: 4.5rem; }
        .bi-filtros #filtro-mes { width: 5rem; }
        .bi-filtros #btn-aplicar { padding: 0.45rem 0.9rem; border-radius: var(--btn-border-radius); border: none; background: var(--accent-primary); color: #fff; font-weight: 600; cursor: pointer; font-size: 0.9rem; white-space: nowrap; transition: filter 0.2s ease, transform 0.2s ease; }
        .bi-filtros #btn-aplicar:hover { filter: brightness(1.08); }
        .bi-filtros #btn-aplicar:active { transform: scale(0.98); }
        .bi-filtros #helpBtn {
            padding: 0.45rem 0.65rem;
            border-radius: var(--btn-border-radius);
            border: 1px solid var(--border-color);
            background: var(--bg-secondary);
            color: var(--accent-primary);
            cursor: pointer;
            font-size: 0.95rem;
            line-height: 1;
        }
        .bi-toolbar {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            background: var(--card-bg, var(--bg-secondary));
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            box-shadow: var(--card-shadow, 0 1px 3px rgba(0, 0, 0, 0.06));
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }
        .bi-context-line {
            margin: 0;
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.4;
            padding-top: 0.45rem;
            border-top: 1px solid var(--border-color);
        }
        .bi-filtros select:focus-visible,
        .bi-filtros #btn-aplicar:focus-visible,
        .bi-filtros #btn-mes-atual:focus-visible {
            outline: 2px solid var(--accent-primary);
            outline-offset: 2px;
        }
        .bi-filtros select { transition: border-color 0.2s ease, box-shadow 0.2s ease; }
        .bi-filtros select:hover { border-color: color-mix(in srgb, var(--accent-primary) 45%, var(--border-color)); }
        @media (prefers-reduced-motion: reduce) {
            .bi-filtros select,
            .bi-filtros #btn-aplicar,
            #btn-mes-atual,
            body.page-bi #bi-kpis .dashboard-card,
            body.page-bi #bi-charts .analytics-card,
            body.page-bi .bi-chart-embed.analytics-card,
            .btn-action-indicators {
                transition: none !important;
            }
            .bi-filtros #btn-aplicar:active { transform: none; }
            .btn-action-indicators:hover { transform: none; }
            .bi-loading .fa-spinner { animation: none !important; opacity: 0.9; }
        }
        .bi-layout { display: block; }
        .bi-content { max-width: 100%; }

        /* Mesmo ritmo visual de pages/routes.php (body.routes-modern + routes-modern-page), sem importar routes.css */
        body.page-bi .dashboard-content.routes-modern-page {
            --forn-table-stripe: rgba(0, 0, 0, 0.035);
            padding-top: 8px;
        }
        /* (Estilo dos KPIs definido no bloco premium <style id="bi-premium"> abaixo) */

        body.page-bi #bi-charts.analytics-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }
        @media (max-width: 768px) {
            body.page-bi #bi-charts.analytics-grid { grid-template-columns: 1fr; }
        }
        body.page-bi #bi-charts > .bi-drill-hint {
            grid-column: 1 / -1;
            margin: 0 0 8px 0;
            padding: 0.55rem 0.75rem;
            font-size: 0.8rem;
            line-height: 1.45;
            color: var(--text-secondary);
            background: color-mix(in srgb, var(--accent-primary) 8%, var(--card-bg, var(--bg-secondary)));
            border: 1px solid color-mix(in srgb, var(--accent-primary) 22%, var(--border-color));
            border-radius: 8px;
        }
        body.page-bi #bi-charts .analytics-card {
            background: var(--card-bg, var(--bg-secondary));
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: none;
            overflow: hidden;
            max-width: 100%;
            box-sizing: border-box;
            padding: 0;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        body.page-bi #bi-charts .analytics-card:hover {
            border-color: color-mix(in srgb, var(--accent-primary) 35%, var(--border-color));
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.07);
        }
        body.page-bi #bi-charts .analytics-card .card-header { padding: 8px 10px; border-bottom: 1px solid var(--border-color); }
        body.page-bi #bi-charts .analytics-card .card-header h3 {
            font-size: 0.78rem;
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
        }
        body.page-bi #bi-charts .analytics-card .card-header.card-header--actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
        }
        body.page-bi #bi-charts .analytics-card .card-header.card-header--actions h3 { flex: 1 1 auto; min-width: 0; }
        body.page-bi #bi-charts .analytics-card .card-body.bi-chart-body {
            height: 240px;
            padding: 10px;
            box-sizing: border-box;
            position: relative;
        }
        body.page-bi #bi-charts .analytics-card .card-body.bi-chart-body canvas {
            display: block;
            width: 100% !important;
            height: 220px !important;
            max-width: 100%;
            max-height: 220px;
            box-sizing: border-box;
            transform: translateZ(0);
            -webkit-backface-visibility: hidden;
            backface-visibility: hidden;
        }
        body.page-bi #bi-charts .bi-chart-full { grid-column: 1 / -1; }
        body.page-bi #bi-charts .bi-chart-full .card-body.bi-chart-body {
            height: 292px;
        }
        body.page-bi #bi-charts .bi-chart-full .card-body.bi-chart-body canvas {
            height: 268px !important;
            max-height: 268px;
        }

        /* Gráficos pizza / blocos embutidos (fora do grid principal): mesmo cartão Rotas */
        body.page-bi .bi-chart-embed.analytics-card {
            background: var(--card-bg, var(--bg-secondary));
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: none;
            overflow: hidden;
            padding: 0;
            max-width: 100%;
            box-sizing: border-box;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        body.page-bi .bi-chart-embed.analytics-card:hover {
            border-color: color-mix(in srgb, var(--accent-primary) 35%, var(--border-color));
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.07);
        }
        body.page-bi .bi-chart-embed.analytics-card .card-body--pie {
            min-height: 200px;
            padding: 10px;
            box-sizing: border-box;
        }
        body.page-bi .bi-chart-embed.analytics-card .card-body--pie canvas.bi-canvas-pie {
            height: 200px !important;
            max-height: 200px;
        }
        body.page-bi .bi-chart-embed.analytics-card .card-header {
            padding: 8px 10px;
            border-bottom: 1px solid var(--border-color);
        }
        body.page-bi .bi-chart-embed.analytics-card .card-header h3 {
            font-size: 0.78rem;
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
        }
        body.page-bi .bi-chart-embed.analytics-card .card-body.bi-chart-body {
            height: 240px;
            padding: 10px;
            box-sizing: border-box;
            position: relative;
        }
        body.page-bi .bi-chart-embed.analytics-card .card-body.bi-chart-body canvas {
            display: block;
            width: 100% !important;
            height: 220px !important;
            max-width: 100%;
            max-height: 220px;
            box-sizing: border-box;
            transform: translateZ(0);
            -webkit-backface-visibility: hidden;
            backface-visibility: hidden;
        }
        .bi-table-card { background: var(--card-bg); border-radius: var(--card-border-radius); padding: 1rem; border: 1px solid var(--border-color); overflow-x: auto; }
        .bi-table-card h3 { font-size: 0.95rem; margin: 0 0 1rem 0; color: var(--text-primary); }
        .bi-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .bi-table th, .bi-table td { padding: 0.6rem 0.75rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        .bi-table th { color: var(--text-muted); font-weight: 600; }
        .bi-table tr:hover { background: var(--bg-tertiary); }
        .bi-loading {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            min-height: 140px;
            margin-bottom: 1rem;
            padding: 1.25rem 1.5rem;
            text-align: center;
            color: var(--text-muted);
            background: var(--card-bg, var(--bg-secondary));
            border: 1px dashed color-mix(in srgb, var(--accent-primary) 38%, var(--border-color));
            border-radius: 12px;
            box-shadow: var(--card-shadow, 0 1px 3px rgba(0, 0, 0, 0.06));
        }
        .bi-loading .fa-spinner { color: var(--accent-primary); font-size: 1.35rem; }
        .bi-error { background: rgba(239,68,68,0.15); color: var(--accent-danger); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }

        /* Indicadores de Desempenho (tela dedicada no BI) */
        .bi-indicadores-section { margin-top: 0; }
        .bi-indicadores-section h2 { font-size: 1.25rem; margin: 0 0 1rem 0; color: var(--text-primary); }
        .bi-indicadores-box { background: var(--bg-secondary); border-radius: 8px; padding: var(--gutter); border: 1px solid var(--border-color); overflow-x: auto; max-width: 100%; box-sizing: border-box; }
        .bi-indicadores-actions { display: flex; justify-content: flex-end; align-items: center; margin-bottom: var(--gutter); gap: 10px; }
        .btn-action-indicators { padding: 8px 15px; background: var(--accent-primary, #3b82f6); border: none; border-radius: 6px; cursor: pointer; color: #fff; white-space: nowrap; transition: filter 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease; }
        .btn-action-indicators:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12); filter: brightness(1.05); }
        .btn-action-indicators:focus-visible { outline: 2px solid var(--accent-primary, #2563eb); outline-offset: 2px; }
        .btn-action-indicators:last-of-type { background: var(--accent-success, #10b981); }
        .btn-action-indicators.bi-btn-export-png {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        .btn-action-indicators.bi-btn-export-png:hover {
            filter: brightness(1.06);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
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
        .bi-saude-frota { margin-bottom: var(--gutter); }
        .bi-saude-frota h3 { font-size: 0.9rem; color: var(--text-muted); margin: 0 0 0.75rem 0; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; }
        .bi-saude-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1rem; }
        body.page-bi .bi-saude-cards { gap: 10px; margin-bottom: 10px; }
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
        .bi-ponto-equilibrio { background: var(--bg-secondary); border-radius: 8px; padding: 1rem; border: 1px solid var(--border-color); margin-top: var(--gutter); }
        .bi-ponto-equilibrio h4 { font-size: 0.95rem; margin: 0 0 0.5rem 0; color: var(--text-primary); }
        .bi-tendencias { margin-top: 0.5rem; font-size: 0.85rem; color: var(--text-secondary); }
        .bi-simulador-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 0.75rem; }
        @media (max-width: 600px) { .bi-simulador-grid { grid-template-columns: 1fr; } }
        .bi-simulador-field { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
        .bi-simulador-field label { font-size: 0.9rem; color: var(--text-muted); min-width: 140px; }
        .bi-simulador-field input { width: 80px; padding: 0.4rem; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); }
        .bi-simulador-result { margin-top: var(--gutter); padding: 1rem; background: var(--bg-tertiary); border-radius: 8px; font-size: 0.95rem; }
        .bi-simulador-result .impacto { font-weight: 700; font-size: 1.05rem; }
        .bi-custo-km-veiculos { overflow-x: auto; margin-top: 0.5rem; }
        .bi-table-total { background: var(--bg-tertiary); font-weight: 600; }
        .bi-table-total td { border-top: 2px solid var(--border-color); }
        #btn-mes-atual { padding: 0.45rem 0.9rem; border-radius: var(--btn-border-radius); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); cursor: pointer; font-size: 0.9rem; white-space: nowrap; transition: background 0.2s ease, border-color 0.2s ease, filter 0.2s ease; }
        #btn-mes-atual:hover { filter: brightness(0.95); }

        .bi-drill-hint { font-size: 0.8rem; color: var(--text-muted); }
        .bi-drill-modal { display: none; position: fixed; inset: 0; z-index: 10050; align-items: center; justify-content: center; padding: 1rem; box-sizing: border-box; }
        .bi-drill-modal.is-open { display: flex; }
        .bi-drill-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,0.45); }
        .bi-drill-dialog { position: relative; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: var(--card-border-radius); max-width: min(960px, 100%); width: 100%; max-height: 85vh; display: flex; flex-direction: column; box-shadow: 0 12px 40px rgba(0,0,0,0.25); }
        .bi-drill-header { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.85rem 1rem; border-bottom: 1px solid var(--border-color); }
        .bi-drill-header h3 { margin: 0; font-size: 1rem; color: var(--text-primary); }
        .bi-drill-close { border: none; background: transparent; color: var(--text-muted); font-size: 1.5rem; line-height: 1; cursor: pointer; padding: 0 0.25rem; border-radius: 6px; transition: color 0.2s ease, background 0.2s ease; }
        .bi-drill-close:hover { color: var(--text-primary); background: var(--bg-tertiary); }
        .bi-drill-close:focus-visible { outline: 2px solid var(--accent-primary); outline-offset: 2px; }
        .bi-drill-body { padding: 0.75rem 1rem 1rem; overflow: auto; flex: 1; min-height: 0; }
        .bi-drill-body .bi-table { font-size: 0.8rem; }

        /* Resumo executivo por IA */
        .bi-resumo-ia { border: 1px solid var(--border-color); border-radius: 12px; background: var(--card-bg, var(--bg-secondary)); padding: 0.85rem 1rem; margin-bottom: var(--gutter); }
        .bi-resumo-ia-head { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.5rem; }
        .bi-resumo-ia-head h3 { margin: 0; font-size: 1rem; display: flex; align-items: center; gap: 0.45rem; }
        .bi-resumo-ia-head h3 i { color: var(--accent-primary, #3b82f6); }
        .bi-resumo-ia-texto { font-size: 0.92rem; line-height: 1.55; color: var(--text-primary); }
        .bi-resumo-ia-texto.bi-text-muted { color: var(--text-muted); }

        /* Impressão / salvar em PDF: mostra só o conteúdo do BI */
        @media print {
            .sidebar, .main-header, header.main-header, .bi-toolbar, .footer,
            #bi-drill-modal, .scroll-to-top, .bi-drill-hint, #btn-imprimir,
            .btn-action-indicators, .bi-btn-export-png, #btn-exportar-tabela-csv {
                display: none !important;
            }
            .main-content { margin-left: 0 !important; width: 100% !important; }
            #bi-main, .dashboard-content { padding: 0 !important; }
            .bi-chart-card, .analytics-card, .bi-table-card, .dashboard-card,
            .bi-saude-frota, #bi-kpis > * {
                break-inside: avoid;
                page-break-inside: avoid;
            }
            body { background: #fff !important; }
        }
    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style id="bi-premium">
    /* ============================================================
       CAMADA VISUAL PREMIUM (enterprise) — só estética, sem tocar
       na lógica/drill-down. Paleta adapta ao tema claro/escuro.
       ============================================================ */
    body.page-bi {
        --pbg: #0F172A; --psurface: #1E293B; --psurface2: #243349;
        --ptext: #F8FAFC; --pmuted: #94A3B8; --pborder: rgba(148,163,184,0.16);
        --pprimary: #3B82F6; --pprimary-soft: rgba(59,130,246,0.14);
        --psuccess: #22C55E; --pwarn: #F59E0B; --perror: #EF4444;
        --pshadow: 0 1px 2px rgba(0,0,0,0.25), 0 14px 36px -20px rgba(0,0,0,0.65);
        --pradius: 16px;
    }
    html.light-theme body.page-bi {
        --pbg: #F1F5F9; --psurface: #FFFFFF; --psurface2: #F8FAFC;
        --ptext: #0F172A; --pmuted: #64748B; --pborder: #E5E7EB;
        --pprimary: #2563EB; --pprimary-soft: rgba(37,99,235,0.08);
        --pshadow: 0 1px 2px rgba(16,24,40,0.05), 0 12px 30px -18px rgba(16,24,40,0.22);
    }
    body.page-bi .dashboard-content.routes-modern-page {
        background: radial-gradient(1100px 560px at 82% -12%, var(--pprimary-soft), transparent 60%), var(--pbg);
        color: var(--ptext);
        font-family: 'Inter', system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    }

    /* ---- Cabeçalho / toolbar executivo ---- */
    body.page-bi .bi-toolbar {
        background: var(--psurface); border: 1px solid var(--pborder);
        border-radius: var(--pradius); box-shadow: var(--pshadow);
        padding: 12px 14px;
    }
    body.page-bi .bi-filtros label { color: var(--pmuted); }
    body.page-bi .bi-filtros select {
        background: var(--psurface2); border: 1px solid var(--pborder); color: var(--ptext);
        border-radius: 10px; padding: 8px 10px; font-weight: 500;
    }
    body.page-bi .bi-filtros select:hover { border-color: color-mix(in srgb, var(--pprimary) 45%, var(--pborder)); }
    body.page-bi #btn-aplicar {
        background: var(--pprimary); border: 1px solid var(--pprimary); border-radius: 10px;
        box-shadow: 0 8px 20px -10px var(--pprimary); font-weight: 600;
    }
    body.page-bi #btn-mes-atual,
    body.page-bi #btn-imprimir,
    body.page-bi #helpBtn {
        background: var(--psurface2); border: 1px solid var(--pborder); color: var(--ptext);
        border-radius: 10px; font-weight: 600; transition: transform .12s ease, border-color .15s;
    }
    body.page-bi #btn-mes-atual:hover,
    body.page-bi #btn-imprimir:hover,
    body.page-bi #helpBtn:hover { transform: translateY(-1px); border-color: color-mix(in srgb, var(--pprimary) 45%, var(--pborder)); }
    body.page-bi .bi-context-line { color: var(--pmuted); }

    /* ---- Cabeçalho executivo (igual à demo) ---- */
    body.page-bi .bi-exec-head {
        display: flex; align-items: flex-start; justify-content: space-between;
        gap: 16px; flex-wrap: wrap; margin-bottom: 14px;
    }
    body.page-bi .bi-exec-title { display: flex; flex-direction: column; gap: 4px; }
    body.page-bi .bi-eyebrow {
        display: inline-flex; align-items: center; gap: 7px;
        font-size: 0.72rem; font-weight: 600; letter-spacing: 0.4px;
        text-transform: uppercase; color: var(--pprimary);
    }
    body.page-bi .bi-eyebrow .bi-dot {
        width: 7px; height: 7px; border-radius: 50%; background: var(--psuccess);
        box-shadow: 0 0 0 3px rgba(34,197,94,0.18);
    }
    body.page-bi .bi-exec-h1 { margin: 0; font-size: 1.6rem; font-weight: 800; letter-spacing: -0.02em; color: var(--ptext); }
    body.page-bi .bi-exec-sub { margin: 0; color: var(--pmuted); font-size: 0.86rem; }
    body.page-bi .bi-updated { color: var(--pmuted); font-size: 0.78rem; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }

    /* ---- KPIs premium ---- */
    body.page-bi #bi-kpis.dashboard-grid {
        display: grid; gap: 16px;
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    @media (max-width: 1100px) { body.page-bi #bi-kpis.dashboard-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 560px) { body.page-bi #bi-kpis.dashboard-grid { grid-template-columns: 1fr; } }
    body.page-bi #bi-kpis .dashboard-card {
        position: relative; overflow: hidden;
        background: var(--psurface); border: 1px solid var(--pborder);
        border-radius: var(--pradius); box-shadow: var(--pshadow);
        padding: 16px 18px; transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease;
    }
    /* "Risco" colorido na lateral (cor cíclica por posição) */
    body.page-bi #bi-kpis .dashboard-card::before {
        content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
        background: var(--accent, var(--pprimary)); opacity: 0.9;
    }
    body.page-bi #bi-kpis .dashboard-card:nth-child(8n+1) { --accent: #3B82F6; }
    body.page-bi #bi-kpis .dashboard-card:nth-child(8n+2) { --accent: #22C55E; }
    body.page-bi #bi-kpis .dashboard-card:nth-child(8n+3) { --accent: #F59E0B; }
    body.page-bi #bi-kpis .dashboard-card:nth-child(8n+4) { --accent: #8B5CF6; }
    body.page-bi #bi-kpis .dashboard-card:nth-child(8n+5) { --accent: #06B6D4; }
    body.page-bi #bi-kpis .dashboard-card:nth-child(8n+6) { --accent: #EC4899; }
    body.page-bi #bi-kpis .dashboard-card:nth-child(8n+7) { --accent: #14B8A6; }
    body.page-bi #bi-kpis .dashboard-card:nth-child(8n)   { --accent: #F97316; }
    body.page-bi #bi-kpis .dashboard-card:hover {
        transform: translateY(-3px);
        border-color: color-mix(in srgb, var(--accent, var(--pprimary)) 45%, var(--pborder));
        box-shadow: 0 1px 2px rgba(0,0,0,0.25), 0 20px 44px -22px rgba(0,0,0,0.7);
    }
    body.page-bi #bi-kpis .dashboard-card .card-header { border: none; padding: 0; margin-bottom: 8px; display: flex; align-items: center; justify-content: flex-start; gap: 10px; }
    body.page-bi #bi-kpis .dashboard-card .card-header h3 {
        font-size: 0.74rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; color: var(--pmuted);
    }
    body.page-bi #bi-kpis .dashboard-card .kpi-card-icon {
        width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center;
        background: color-mix(in srgb, var(--accent, var(--pprimary)) 15%, transparent);
        color: var(--accent, var(--pprimary)); font-size: 0.9rem;
    }
    body.page-bi #bi-kpis .metric-value { font-size: 1.55rem; font-weight: 800; letter-spacing: -0.02em; color: var(--ptext); }
    body.page-bi #bi-kpis .bi-kpi-variacoes { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }

    /* ===== KPI no formato EXATO da demo (chip + valor + badge + sparkline) ===== */
    body.page-bi #bi-kpis .biq-kpi__top { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; }
    body.page-bi #bi-kpis .biq-kpi__label { font-size: 0.74rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; color: var(--biq-muted, var(--pmuted)); }
    body.page-bi #bi-kpis .biq-kpi__value { line-height: 1.1; }
    body.page-bi #bi-kpis .biq-kpi__foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 10px; }
    body.page-bi #bi-kpis .biq-delta { display: inline-flex; align-items: center; gap: 5px; font-size: 0.78rem; font-weight: 700; padding: 3px 8px; border-radius: 999px; white-space: nowrap; }
    body.page-bi #bi-kpis .biq-delta--up { color: #16a34a; background: rgba(34,197,94,0.14); }
    body.page-bi #bi-kpis .biq-delta--down { color: #dc2626; background: rgba(239,68,68,0.14); }
    body.page-bi #bi-kpis .biq-delta--flat { color: var(--pmuted); background: rgba(148,163,184,0.14); }
    body.page-bi #bi-kpis .biq-spark { width: 84px; height: 28px; display: block; flex: 0 0 auto; }
    body.page-bi #bi-kpis .biq-kpi__hint { font-size: 0.72rem; color: var(--biq-muted, var(--pmuted)); margin-top: 4px; }

    /* ---- Cartões de gráfico (NÃO altera tamanho dos canvases) ---- */
    body.page-bi #bi-charts .analytics-card,
    body.page-bi .bi-chart-embed.analytics-card {
        background: var(--psurface); border: 1px solid var(--pborder);
        border-radius: var(--pradius); box-shadow: var(--pshadow);
    }
    body.page-bi #bi-charts .analytics-card:hover,
    body.page-bi .bi-chart-embed.analytics-card:hover {
        border-color: color-mix(in srgb, var(--pprimary) 32%, var(--pborder));
        box-shadow: 0 1px 2px rgba(0,0,0,0.25), 0 18px 40px -24px rgba(0,0,0,0.6);
    }
    body.page-bi #bi-charts .analytics-card .card-header,
    body.page-bi .bi-chart-embed.analytics-card .card-header { padding: 12px 16px; border-bottom: 1px solid var(--pborder); }
    body.page-bi #bi-charts .analytics-card .card-header h3,
    body.page-bi .bi-chart-embed.analytics-card .card-header h3 { font-size: 0.92rem; font-weight: 700; color: var(--ptext); }
    body.page-bi #bi-charts > .bi-drill-hint {
        background: var(--pprimary-soft);
        border: 1px solid color-mix(in srgb, var(--pprimary) 28%, var(--pborder));
        color: var(--ptext); border-radius: 12px;
    }

    /* ---- Resumo IA ---- */
    body.page-bi .bi-resumo-ia {
        background: linear-gradient(135deg, color-mix(in srgb, var(--pprimary) 7%, var(--psurface)), var(--psurface));
        border: 1px solid var(--pborder); border-radius: var(--pradius); box-shadow: var(--pshadow); padding: 16px 18px;
    }
    body.page-bi .bi-resumo-ia-head h3 i { color: #8B5CF6; }
    body.page-bi .bi-resumo-ia-texto { color: var(--ptext); }
    body.page-bi .bi-resumo-ia-texto.bi-text-muted { color: var(--pmuted); }

    /* ---- Saúde da frota / score / semáforo ---- */
    body.page-bi .bi-saude-frota h3 { color: var(--pmuted); }
    body.page-bi .bi-saude-card,
    body.page-bi .bi-semaforo-wrap,
    body.page-bi .bi-score-frota-wrap {
        background: var(--psurface); border: 1px solid var(--pborder);
        border-radius: var(--pradius); box-shadow: var(--pshadow);
    }
    body.page-bi .bi-saude-card { padding: 14px 16px; }
    body.page-bi .bi-saude-card .bi-saude-value { color: var(--pprimary); font-weight: 800; }
    body.page-bi .bi-saude-card .bi-saude-label { color: var(--pmuted); }

    /* ---- Botões de ação ---- */
    body.page-bi .btn-action-indicators { border-radius: 10px; font-weight: 600; }
    body.page-bi .btn-action-indicators.bi-btn-export-png { background: var(--psurface2); color: var(--ptext); border: 1px solid var(--pborder); }

    /* ---- Tabelas / indicadores ---- */
    body.page-bi .bi-table-card,
    body.page-bi .bi-indicadores-box { background: var(--psurface); border: 1px solid var(--pborder); border-radius: var(--pradius); box-shadow: var(--pshadow); }
    body.page-bi .bi-table th { color: var(--pmuted); }
    body.page-bi .bi-table tr:hover { background: var(--psurface2); }

    /* ---- Modal de drill-down (mantém o comportamento, muda só o visual) ---- */
    body.page-bi .bi-drill-dialog {
        background: var(--psurface); border: 1px solid var(--pborder); border-radius: var(--pradius);
        box-shadow: 0 24px 70px -20px rgba(0,0,0,0.6);
    }
    body.page-bi .bi-drill-header { border-bottom: 1px solid var(--pborder); }
    body.page-bi .bi-drill-header h3 { color: var(--ptext); font-weight: 700; }

    /* ---- Loading ---- */
    body.page-bi .bi-loading {
        background: var(--psurface); border: 1px dashed color-mix(in srgb, var(--pprimary) 40%, var(--pborder));
        border-radius: var(--pradius); color: var(--pmuted);
    }
    body.page-bi .bi-loading .fa-spinner { color: var(--pprimary); }

    /* Ranking HTML premium (Top veículos) */
    body.page-bi .bi-rank { display: flex; flex-direction: column; gap: 8px; padding: 2px; }
    body.page-bi .bi-rank-item {
        display: grid; grid-template-columns: 28px 1fr auto; align-items: center; gap: 12px;
        padding: 9px 11px; border-radius: 12px; cursor: pointer;
        transition: background .15s ease, transform .12s ease;
    }
    body.page-bi .bi-rank-item:hover { background: var(--psurface2); transform: translateX(2px); }
    body.page-bi .bi-rank-item:focus-visible { outline: 2px solid var(--pprimary); outline-offset: 1px; }
    body.page-bi .bi-rank-pos { width: 28px; height: 28px; border-radius: 8px; display: grid; place-items: center; font-weight: 800; font-size: 0.8rem; background: var(--psurface2); color: var(--pmuted); }
    body.page-bi .bi-rank-item:nth-child(1) .bi-rank-pos { background: rgba(245,158,11,0.18); color: #F59E0B; }
    body.page-bi .bi-rank-item:nth-child(2) .bi-rank-pos { background: rgba(148,163,184,0.20); color: #cbd5e1; }
    body.page-bi .bi-rank-item:nth-child(3) .bi-rank-pos { background: rgba(180,120,60,0.20); color: #d2925a; }
    body.page-bi .bi-rank-name { font-size: 0.88rem; font-weight: 600; color: var(--ptext); margin-bottom: 5px; }
    body.page-bi .bi-rank-bar { height: 7px; border-radius: 999px; background: var(--psurface2); overflow: hidden; }
    body.page-bi .bi-rank-bar > span { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--pprimary), #06B6D4); }
    body.page-bi .bi-rank-vals { text-align: right; font-size: 0.78rem; color: var(--pmuted); white-space: nowrap; line-height: 1.3; }
    body.page-bi .bi-rank-vals b { color: var(--ptext); font-size: 0.95rem; }
    body.page-bi .bi-rank-vals span { display: block; }

    /* Sinaliza que os gráficos são clicáveis (drill-down) */
    body.page-bi #bi-charts canvas,
    body.page-bi .bi-chart-embed canvas,
    body.page-bi .bi-canvas-pie { cursor: pointer; }

    @media (prefers-reduced-motion: reduce) {
        body.page-bi #bi-kpis .dashboard-card,
        body.page-bi #bi-charts .analytics-card { transition: none; }
    }
    </style>
</head>
<body class="page-bi">
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include __DIR__ . '/../includes/sidebar_pages.php'; ?>
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include __DIR__ . '/../includes/header.php'; ?>
            <!-- Page Content -->
            <main id="bi-main" class="dashboard-content routes-modern-page" role="main" aria-label="Painel BI Frota">
                <div class="biq" id="biq">
                <header class="biq-head bi-header">
                    <div class="biq-head__title bi-exec-title">
                        <span class="biq-eyebrow bi-eyebrow"><span class="dot bi-dot"></span> Business Intelligence · Frota</span>
                        <h1 class="bi-exec-h1">Dashboard Executivo</h1>
                        <p class="biq-sub bi-exec-sub">Visão consolidada de receita, custos e eficiência operacional</p>
                    </div>
                    <div class="biq-actions">
                        <div class="biq-search"><i class="fas fa-search" aria-hidden="true"></i><input type="search" id="bi-search" placeholder="Pesquisar veículo, rota…" aria-label="Pesquisar no painel"></div>
                        <button type="button" id="btn-auto-refresh" class="biq-btn" title="Atualização automática a cada 5 minutos" aria-pressed="false"><i class="fas fa-clock-rotate-left" aria-hidden="true"></i> Auto 5m</button>
                        <button type="button" id="btn-compartilhar" class="biq-btn" title="Copiar link deste painel com filtros" aria-label="Compartilhar link do painel"><i class="fas fa-share-nodes" aria-hidden="true"></i> Compartilhar</button>
                        <button type="button" id="btn-imprimir" class="biq-btn" title="Imprimir / salvar em PDF" aria-label="Imprimir ou salvar em PDF"><i class="fas fa-file-pdf" aria-hidden="true"></i> PDF</button>
                        <button type="button" id="btn-exportar-excel-header" class="biq-btn" title="Exportar tabela em CSV" aria-label="Exportar dados em Excel/CSV"><i class="fas fa-file-excel" aria-hidden="true"></i> Excel</button>
                        <button type="button" id="btn-aplicar-top" class="biq-btn biq-btn--primary" aria-label="Atualizar painel"><i class="fas fa-arrows-rotate" aria-hidden="true"></i> Atualizar</button>
                    </div>
                </header>
                    <div class="biq-toolbar bi-toolbar" role="region" aria-label="Filtros do relatório BI">
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
                        <button type="button" id="btn-aplicar" aria-label="Aplicar filtros e atualizar o painel"><i class="fas fa-sync-alt" aria-hidden="true"></i> Aplicar</button>
                        <button type="button" id="btn-mes-atual" title="Mês atual" aria-label="Usar mês e ano atuais nos filtros"><i class="fas fa-calendar-day" aria-hidden="true"></i> Mês atual</button>
                        <button type="button" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                        <div class="biq-seg biq-period" role="group" aria-label="Atalho de período">
                            <button type="button" data-bi-period="mes" class="is-active" title="Mês atual">Mês</button>
                            <button type="button" data-bi-period="trim" title="Últimos 3 meses">Trim.</button>
                            <button type="button" data-bi-period="ano" title="Ano inteiro">Ano</button>
                            <button type="button" data-bi-period="12m" title="Últimos 12 meses">12m</button>
                        </div>
                        <div class="biq-seg biq-compare" role="group" aria-label="Base do comparativo nos KPIs">
                            <button type="button" data-bi-compare="month" class="is-active" title="Variação vs mês anterior">vs Mês</button>
                            <button type="button" data-bi-compare="year" title="Variação vs mesmo mês do ano anterior">vs Ano</button>
                        </div>
                        <span class="biq-updated bi-updated"><i class="fas fa-clock" aria-hidden="true"></i> Atualizado em <span id="bi-updated-time">—</span></span>
                    </div>
                    <p class="bi-context-line" id="bi-context-line" aria-live="polite"></p>
                    </div>
                    <div id="bi-toast" class="biq-toast" role="status" aria-live="polite"></div>

                <div class="bi-layout" id="bi-main-content">
                    <div class="bi-content">
                        <div id="bi-skeleton" class="biq-skeleton" aria-hidden="true">
                            <div class="biq-skeleton__kpis">
                                <div class="biq-skeleton__kpi"></div><div class="biq-skeleton__kpi"></div>
                                <div class="biq-skeleton__kpi"></div><div class="biq-skeleton__kpi"></div>
                                <div class="biq-skeleton__kpi"></div><div class="biq-skeleton__kpi"></div>
                                <div class="biq-skeleton__kpi"></div><div class="biq-skeleton__kpi"></div>
                            </div>
                            <div class="biq-skeleton__charts">
                                <div class="biq-skeleton__chart"></div>
                                <div class="biq-skeleton__chart" style="height:230px;"></div>
                            </div>
                        </div>
                        <div id="bi-loading" class="bi-loading" role="status" aria-live="polite" aria-busy="false" aria-label="Carregando dados do BI" style="display:none;">
                            <i class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                            <span>Carregando indicadores…</span>
                        </div>
                        <div id="bi-error" class="bi-error" style="display:none;" role="alert" aria-live="assertive" tabindex="-1"></div>
                        <div id="bi-aviso-incompletos" class="bi-aviso-incompletos" style="display:none;"></div>

                        <div id="bi-resumo-ia" class="biq-card bi-resumo-ia" style="display:none;">
                            <div class="bi-resumo-ia-head">
                                <h3><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i> Resumo executivo (IA)</h3>
                                <button type="button" id="btn-resumo-ia" class="btn-action-indicators" aria-label="Gerar resumo executivo com IA"><i class="fas fa-bolt" aria-hidden="true"></i> Gerar resumo</button>
                            </div>
                            <div id="bi-resumo-ia-texto" class="bi-resumo-ia-texto bi-text-muted" aria-live="polite">Clique em “Gerar resumo” para uma análise dos números deste período pela IA.</div>
                        </div>

                        <div id="bi-saude-frota" class="bi-saude-frota" style="display:none;">
                            <h3><i class="fas fa-heartbeat"></i> Indicadores de Saúde da Frota</h3>
                            <div id="bi-score-frota-wrap" class="bi-score-frota-wrap" style="display:none; margin-bottom: var(--gutter);"></div>
                            <div class="bi-saude-cards" id="bi-saude-cards"></div>
                            <div class="bi-semaforo-wrap" id="bi-semaforo-wrap">
                                <span class="bi-semaforo-label">Desempenho (margem operacional):</span>
                                <div class="bi-semaforo" id="bi-semaforo"></div>
                                <span class="bi-semaforo-texto" id="bi-semaforo-texto"></span>
                            </div>
                        </div>
                        <div id="bi-kpis" class="dashboard-grid biq-kpis" style="display:none;"></div>

                        <!-- Insights premium (demais visões) -->
                        <div id="bi-premium-visao" class="biq-visao-extras" style="display:none;" aria-label="Insights da visão">
                            <div class="biq-grid biq-grid--2-1">
                                <div class="biq-card">
                                    <div class="biq-card__head">
                                        <h3><i class="fas fa-wand-magic-sparkles" style="color:var(--biq-violet)"></i> Insights automáticos</h3>
                                        <span class="biq-card__meta" id="bi-visao-insights-meta">Período</span>
                                    </div>
                                    <div class="biq-insights" id="bi-visao-insights"></div>
                                </div>
                                <div class="biq-card">
                                    <div class="biq-card__head">
                                        <h3><i class="fas fa-bell" style="color:var(--biq-warn)"></i> Alertas</h3>
                                        <span class="biq-card__meta">Requer atenção</span>
                                    </div>
                                    <div class="biq-insights" id="bi-visao-alertas"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Layout premium (visão Geral — igual à demo) -->
                        <div id="bi-premium-geral" style="display:none;" aria-label="Dashboard executivo premium">
                            <p class="biq-drill-hint"><i class="fas fa-mouse-pointer" aria-hidden="true"></i> Clique em um mês nos gráficos de evolução — ou em uma fatia do donut — para ver o detalhamento.</p>
                            <div class="biq-grid biq-grid--2-1">
                                <div class="biq-card">
                                    <div class="biq-card__head">
                                        <h3>Receita × Custo × Lucro</h3>
                                        <span class="biq-card__meta" id="bi-premium-main-meta">Últimos 12 meses</span>
                                    </div>
                                    <div class="biq-chart-wrap"><canvas id="chartReceitaCustoLucro"></canvas></div>
                                </div>
                                <div class="biq-card">
                                    <div class="biq-card__head">
                                        <h3>Composição de custos</h3>
                                        <span class="biq-tag" id="bi-premium-donut-tag">Período</span>
                                    </div>
                                    <div class="biq-chart-wrap biq-chart-wrap--sm"><canvas id="chartComposicaoCustos"></canvas></div>
                                </div>
                            </div>
                            <div class="biq-grid biq-grid--2-1">
                                <div class="biq-card">
                                    <div class="biq-card__head">
                                        <h3><i class="fas fa-wand-magic-sparkles" style="color:var(--biq-violet)"></i> Insights automáticos</h3>
                                        <span class="biq-card__meta">Baseado nos dados do período</span>
                                    </div>
                                    <div class="biq-insights" id="bi-premium-insights"></div>
                                </div>
                                <div class="biq-card">
                                    <div class="biq-card__head">
                                        <h3>Metas × Realizado</h3>
                                        <span class="biq-card__meta" id="bi-premium-metas-meta">Período</span>
                                    </div>
                                    <div id="bi-premium-metas"></div>
                                </div>
                            </div>
                            <div class="biq-grid biq-grid--1-1">
                                <div class="biq-card">
                                    <div class="biq-card__head">
                                        <h3><i class="fas fa-trophy" style="color:var(--biq-warn)"></i> Top veículos</h3>
                                        <span class="biq-card__meta">Por rotas no período</span>
                                    </div>
                                    <div id="bi-premium-rank" class="biq-rank bi-rank"></div>
                                </div>
                                <div class="biq-card">
                                    <div class="biq-card__head">
                                        <h3>Lucro por motorista</h3>
                                        <span class="biq-card__meta">Últimos 30 dias</span>
                                    </div>
                                    <div class="biq-chart-wrap biq-chart-wrap--sm"><canvas id="chartMotoristaLucro"></canvas></div>
                                </div>
                            </div>
                            <details class="biq-more-charts" id="bi-premium-more">
                                <summary><i class="fas fa-chart-bar"></i> Mais gráficos detalhados</summary>
                                <div class="biq-more-inner" id="bi-premium-more-inner"></div>
                            </details>
                        </div>

                        <div id="bi-charts" class="analytics-grid biq-charts" style="display:none;" role="region" aria-label="Gráficos do painel">
                        <p class="bi-drill-hint"><i class="fas fa-mouse-pointer" aria-hidden="true"></i> <span>Clique em um mês nos gráficos de evolução — ou em uma fatia das pizzas — para ver o detalhamento.</span></p>
                        <div class="analytics-card bi-chart-card bi-chart-full" id="wrapChartRotasTempo">
                            <div class="card-header card-header--actions">
                                <h3 id="tituloChartRotasTempo">Rotas e Faturamento ao longo do tempo</h3>
                                <button type="button" id="btn-exportar-grafico-png" class="btn-action-indicators bi-btn-export-png" style="font-size:0.85rem;" aria-label="Exportar gráfico principal em imagem PNG"><i class="fas fa-image" aria-hidden="true"></i> Exportar PNG</button>
                            </div>
                            <div class="card-body bi-chart-body">
                                <canvas id="chartRotasTempo"></canvas>
                            </div>
                        </div>
                        <div class="analytics-card bi-chart-card" id="wrapChartFreteMensal">
                            <div class="card-header">
                                <h3 id="tituloChartFreteMensal">Faturamento por mês (Frete)</h3>
                            </div>
                            <div class="card-body bi-chart-body">
                                <canvas id="chartFreteMensal"></canvas>
                            </div>
                        </div>
                        <div class="analytics-card bi-chart-card" id="wrapChartKmMensal">
                            <div class="card-header">
                                <h3 id="tituloChartKmMensal">Km rodados por mês</h3>
                            </div>
                            <div class="card-body bi-chart-body">
                                <canvas id="chartKmMensal"></canvas>
                            </div>
                        </div>
                        <div class="analytics-card bi-chart-card bi-chart-full" id="wrapChartAbastTempo" style="display:none;">
                            <div class="card-header">
                                <h3>Abastecimentos e Gasto ao longo do tempo</h3>
                            </div>
                            <div class="card-body bi-chart-body">
                                <canvas id="chartAbastTempo"></canvas>
                            </div>
                        </div>
                        <div class="analytics-card bi-chart-card bi-chart-full" id="wrapChartTopVeiculos">
                            <div class="card-header">
                                <h3 id="tituloChartTopVeiculos">Top veículos (rotas e km)</h3>
                            </div>
                            <div class="card-body bi-chart-body">
                                <canvas id="chartTopVeiculos"></canvas>
                                <div id="biTopVeiculosRank" class="bi-rank" style="display:none;"></div>
                            </div>
                        </div>
                        </div>
                        <div id="bi-table-wrap" class="biq-card bi-table-card" style="display:none;">
                            <div class="biq-card__head" style="margin-bottom:12px;">
                                <h3 id="bi-table-titulo" style="margin:0;">Panorama mensal (últimos 12 meses)</h3>
                                <button type="button" id="btn-exportar-tabela-csv" class="btn-action-indicators" aria-label="Exportar tabela de panorama mensal em CSV"><i class="fas fa-file-csv" aria-hidden="true"></i> Exportar CSV</button>
                            </div>
                            <table class="bi-table" id="bi-tabela">
                                <thead><tr><th>Mês</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <!-- Blocos extras por visão (ranking rotas, consumo, manut, desp tipo, fixas, insights IA) -->
                        <div id="bi-ranking-rotas" class="biq-card bi-extra-block bi-table-card" style="display:none;">
                            <h3><i class="fas fa-trophy"></i> Ranking de rotas (mais e menos lucrativas)</h3>
                            <div id="bi-ranking-rotas-content"></div>
                        </div>
                        <div id="bi-abast-extra" class="biq-visao-analytics" style="display:none;" aria-label="Abastecimento — análise detalhada">
                            <div class="biq-grid biq-grid--2-1">
                                <div class="biq-card">
                                    <div class="biq-card__head">
                                        <h3><i class="fas fa-gas-pump" style="color:var(--biq-primary)"></i> Consumo e custo</h3>
                                        <span class="biq-card__meta" id="bi-abast-meta">Período</span>
                                    </div>
                                    <div id="bi-abast-extra-cards" class="biq-stat-grid"></div>
                                    <div id="bi-abast-alertas" class="biq-insights" style="margin-top:14px;"></div>
                                </div>
                                <div class="biq-card">
                                    <div class="biq-card__head">
                                        <h3>Consumo por veículo</h3>
                                        <span class="biq-tag">KM/L</span>
                                    </div>
                                    <div id="bi-abast-tabela-wrap"></div>
                                </div>
                            </div>
                        </div>
                        <div id="bi-manut-extra" class="biq-visao-analytics" style="display:none;" aria-label="Manutenção — análise detalhada">
                            <div class="biq-grid biq-grid--2-1">
                                <div class="biq-card">
                                    <div class="biq-card__head">
                                        <h3><i class="fas fa-wrench" style="color:var(--biq-warn)"></i> Indicadores de manutenção</h3>
                                        <span class="biq-card__meta" id="bi-manut-meta">Período</span>
                                    </div>
                                    <div id="bi-manut-extra-content" class="biq-stat-grid"></div>
                                    <div id="bi-veiculos-criticos" style="margin-top:14px;"></div>
                                </div>
                                <div class="biq-card">
                                    <div class="biq-card__head">
                                        <h3>Preventiva vs corretiva</h3>
                                        <span class="biq-tag" id="bi-manut-pie-tag">Período</span>
                                    </div>
                                    <div class="biq-chart-wrap biq-chart-wrap--sm biq-chart-wrap--donut" id="bi-manut-donut-wrap">
                                        <canvas id="chartManutPreventivaCorretiva"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="bi-desp-viagem-extra" class="biq-visao-analytics" style="display:none;" aria-label="Despesas de viagem — análise detalhada">
                            <div class="biq-grid biq-grid--2-1">
                                <div class="biq-card">
                                    <div class="biq-card__head">
                                        <h3><i class="fas fa-road" style="color:var(--biq-primary)"></i> Indicadores de despesa</h3>
                                        <span class="biq-card__meta" id="bi-desp-km-meta">Período</span>
                                    </div>
                                    <div id="bi-desp-viagem-extra-content" class="biq-stat-grid"></div>
                                </div>
                                <div class="biq-card">
                                    <div class="biq-card__head">
                                        <h3>Despesas por tipo</h3>
                                        <span class="biq-tag" id="bi-desp-tipo-tag">Período</span>
                                    </div>
                                    <div class="biq-chart-wrap biq-chart-wrap--sm biq-chart-wrap--donut" id="bi-desp-viagem-donut-wrap">
                                        <canvas id="chartDespViagemTipos"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="bi-desp-fixas-extra" class="biq-visao-analytics" style="display:none;" aria-label="Despesas fixas — análise">
                            <div class="biq-card">
                                <div class="biq-card__head">
                                    <h3><i class="fas fa-file-invoice-dollar" style="color:var(--biq-violet)"></i> Impacto das despesas fixas</h3>
                                    <span class="biq-card__meta" id="bi-fixas-meta">Período</span>
                                </div>
                                <div id="bi-desp-fixas-extra-content" class="biq-stat-grid"></div>
                            </div>
                        </div>
                        <div id="bi-alertas-periodo" class="biq-card bi-extra-block bi-table-card" style="display:none;">
                            <h3><i class="fas fa-exclamation-triangle"></i> Alertas ativos no período</h3>
                            <div id="bi-alertas-periodo-content" class="bi-alertas-list"></div>
                        </div>
                        <div id="bi-ponto-equilibrio" class="biq-card bi-extra-block bi-table-card" style="display:none;">
                            <h3><i class="fas fa-balance-scale"></i> Ponto de equilíbrio (break-even)</h3>
                            <div id="bi-ponto-equilibrio-content" class="bi-ponto-equilibrio"></div>
                        </div>
                        <div id="bi-tendencias" class="biq-card bi-extra-block bi-table-card" style="display:none;">
                            <h3><i class="fas fa-chart-line"></i> Tendências</h3>
                            <div id="bi-tendencias-content" class="bi-tendencias"></div>
                        </div>
                        <div id="bi-simulador" class="biq-card bi-extra-block bi-table-card" style="display:none;">
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
                        <div id="bi-custo-km-hist" class="biq-card bi-extra-block bi-table-card" style="display:none;">
                            <h3><i class="fas fa-tachometer-alt"></i> Custo por KM – histórico e por veículo</h3>
                            <div class="bi-chart-embed analytics-card bi-chart-card" style="margin-bottom: var(--gutter);">
                                <div class="card-header"><h3>Evolução do custo/km (mês a mês)</h3></div>
                                <div class="card-body bi-chart-body">
                                    <canvas id="chartCustoKmHist"></canvas>
                                </div>
                            </div>
                            <h4 style="font-size:0.95rem; margin:0.5rem 0;">Por veículo (período)</h4>
                            <div id="bi-custo-km-veiculos-content" class="bi-custo-km-veiculos"></div>
                        </div>
                        <div id="bi-insights-ia" class="biq-card bi-extra-block bi-table-card" style="display:none;">
                            <h3><i class="fas fa-robot"></i> Insights automáticos</h3>
                            <div id="bi-insights-ia-content" class="biq-insights bi-insights-list"></div>
                        </div>

                        <!-- Tela só para Indicadores de Desempenho (visão indicadores_desempenho) -->
                        <div id="bi-indicadores-desempenho" class="biq-visao-analytics" style="display: none;" aria-label="Indicadores de desempenho">
                            <div class="biq-card bi-indicadores-box">
                                <div class="biq-card__head">
                                    <h3><i class="fas fa-chart-line" style="color:var(--biq-primary)"></i> Indicadores de Desempenho</h3>
                                    <div class="biq-actions" style="margin:0;">
                                        <button type="button" id="btn-indicadores-atualizar" class="biq-btn" aria-label="Atualizar indicadores"><i class="fas fa-sync-alt" aria-hidden="true"></i> Atualizar</button>
                                        <button type="button" id="btn-indicadores-excel" class="biq-btn" aria-label="Exportar Excel"><i class="fas fa-file-excel" aria-hidden="true"></i> Excel</button>
                                    </div>
                                </div>
                                <div id="indicatorsLoading" class="biq-skeleton is-visible" style="margin:0;" aria-hidden="false">
                                    <div class="biq-skeleton__chart" style="height:120px;"></div>
                                </div>
                                <div id="indicatorsTableContainer" class="bi-indicadores-table-wrap" style="display: none;">
                                    <table id="indicatorsTable" class="bi-table bi-indicadores-table">
                                        <thead>
                                            <tr>
                                                <th class="bi-ind-sticky">Indicador</th>
                                                <th id="indicatorsTableHeader"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="indicatorsTableBody"></tbody>
                                    </table>
                                </div>
                                <div id="bi-indicadores-alertas" class="biq-insights" style="margin-top:14px; display: none;"></div>
                            </div>
                        </div>

                        <div id="bi-drill-modal" class="bi-drill-modal" role="dialog" aria-modal="true" aria-labelledby="bi-drill-title" aria-hidden="true">
                            <div class="bi-drill-backdrop" id="bi-drill-backdrop"></div>
                            <div class="bi-drill-dialog">
                                <div class="bi-drill-header">
                                    <h3 id="bi-drill-title">Detalhes</h3>
                                    <div style="display:flex; align-items:center; gap:0.5rem;">
                                        <button type="button" class="btn-action-indicators" id="bi-drill-export" style="display:none; font-size:0.85rem;" aria-label="Exportar detalhamento em CSV"><i class="fas fa-file-csv" aria-hidden="true"></i> Exportar CSV</button>
                                        <button type="button" class="bi-drill-close" id="bi-drill-close" aria-label="Fechar">&times;</button>
                                    </div>
                                </div>
                                <div id="bi-drill-body" class="bi-drill-body"></div>
                                <div class="bi-drill-footer" id="bi-drill-footer" style="display:none; padding:12px 16px; border-top:1px solid var(--biq-border, #334155); gap:8px; flex-wrap:wrap;">
                                    <a id="bi-drill-through" class="biq-btn biq-btn--primary" href="#" style="text-decoration:none;"><i class="fas fa-arrow-up-right-from-square"></i> Abrir no módulo</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </div><!-- /.biq -->
            </main>

            <!-- Footer: irmão de .dashboard-content (igual rotas/outras páginas) para colar na base do main-content -->
            <?php include __DIR__ . '/../includes/footer.php'; ?>
        </div>
    </div>

    <!-- JavaScript Files -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>window.__SF_BI__ = { baseUrl: '../api' };</script>
    <script src="../js/bi-app.js?v=<?php echo sf_asset_v(); ?>"></script>

    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'bi'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
