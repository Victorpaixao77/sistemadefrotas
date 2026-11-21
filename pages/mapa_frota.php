<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/sf_api_base.php';
require_once dirname(__DIR__) . '/includes/permissions.php';
require_once dirname(__DIR__) . '/includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();
require_permission('view_fleet_map');

$page_title = 'Mapa da frota (GPS)';

$mapa_kpis = [
    'veiculos_sinal_recente' => 0,
    'veiculos_sem_sinal_30m' => 0,
    'pontos_gps_24h' => 0,
    'alertas_24h' => 0,
    'alertas_por_tipo' => [],
];
$mapa_scores = [];
$eid_mapa = (int) ($_SESSION['empresa_id'] ?? 0);
if ($eid_mapa > 0) {
    try {
        require_once dirname(__DIR__) . '/includes/db_connect.php';
        require_once dirname(__DIR__) . '/includes/gps_inteligencia_resumo.php';
        $conn_mapa = getConnection();
        $mapa_kpis = gps_inteligencia_kpis_mapa($conn_mapa, $eid_mapa);
        $mapa_scores = gps_inteligencia_score_motoristas($conn_mapa, $eid_mapa, 7, 10);
    } catch (Throwable $e) {
        error_log('mapa_frota inteligência: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo htmlspecialchars($page_title); ?></title>
    <?php sf_render_api_scripts(); ?>
    <link rel="icon" type="image/png" href="../logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <link rel="stylesheet" href="../css/fornc-modern-page.css">
    <link rel="stylesheet" href="../css/routes.css?v=1.0.1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
    <?php sf_page_help_css('..'); ?>
    <style>
        /* Mesma barra que Rotas (.fornc-toolbar): ajustes só para datetime e veículo no mapa */
        .mapa-frota-wrap .fornc-filters-inline input[type="datetime-local"] {
            font-size: 0.8125rem;
            padding: 0.3rem 0.4rem;
            border-radius: 5px;
            border: 1px solid var(--border-color);
            min-width: 10rem;
            max-width: 100%;
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        .mapa-frota-wrap .fornc-filters-inline #rtVeiculo,
        .mapa-frota-wrap .fornc-filters-inline #histVeiculo { min-width: 11rem; }
        .mapa-frota-wrap .fornc-filters-inline .fg--rt {
            flex-direction: row;
            align-items: center;
            gap: 0.45rem;
            align-self: flex-end;
            padding-bottom: 0.2rem;
        }
        .mapa-frota-wrap .fornc-filters-inline .fg--rt input { width: auto; margin: 0; }
        .mapa-frota-wrap .fornc-filters-inline .fg--rt label {
            margin: 0;
            font-size: 0.8125rem;
            font-weight: 500;
            text-transform: none;
            letter-spacing: normal;
            color: var(--text-primary);
        }
        .mapa-frota-map-wrap {
            position: relative;
            height: min(62vh, 520px);
            width: 100%;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            z-index: 1;
        }
        .mapa-frota-map-wrap .mapa-frota-canvas {
            height: 100%;
            width: 100%;
            min-height: 280px;
        }
        .mapa-frota-ajuda {
            margin: 0 0 1rem 0;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--card-bg, var(--bg-secondary));
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.45;
        }
        .mapa-frota-ajuda h2 {
            margin: 0 0 0.5rem 0;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .mapa-frota-ajuda ul { margin: 0; padding-left: 1.15rem; }
        .mapa-frota-ajuda li { margin: 0.35rem 0; }
        .mapa-frota-ajuda strong { color: var(--text-primary); font-weight: 600; }
        .mapa-frota-meta { margin-top: 0.75rem; font-size: 0.85rem; color: var(--text-secondary); }
        .mapa-frota-legend { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem; }
        .mapa-frota-legend span { display: inline-flex; align-items: center; gap: 6px; margin-right: 16px; }
        .mapa-frota-legend i { width: 14px; height: 4px; border-radius: 2px; display: inline-block; }
        .mapa-frota-alertas { margin-top: 1rem; font-size: 0.85rem; max-width: 100%; }
        .mapa-frota-alertas h3 { margin: 0 0 0.5rem 0; font-size: 1rem; color: var(--text-primary); }
        .mapa-frota-alertas ul { list-style: none; padding: 0; margin: 0; max-height: 200px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px; }
        .mapa-frota-alertas li { padding: 8px 12px; border-bottom: 1px solid var(--border-color); color: var(--text-secondary); }
        .mapa-frota-alertas li:last-child { border-bottom: none; }
        .mapa-frota-alertas .tag { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; margin-right: 6px; }
        .mapa-frota-alertas .tag.entrou { background: rgba(34, 197, 94, 0.2); color: #16a34a; }
        .mapa-frota-alertas .tag.saiu { background: rgba(239, 68, 68, 0.2); color: #dc2626; }
        .mapa-frota-alertas .tag.permanencia { background: rgba(234, 179, 8, 0.2); color: #a16207; }
        .mapa-frota-alertas .tag.bateria_baixa { background: rgba(245, 158, 11, 0.22); color: #c2410c; }
        .mapa-frota-alertas .tag.velocidade_alta { background: rgba(239, 68, 68, 0.22); color: #b91c1c; }
        .mapa-frota-alertas .tag.gps_mock { background: rgba(147, 51, 234, 0.18); color: #6b21a8; }
        .mapa-frota-alertas .tag.oper_generico { background: rgba(100, 116, 139, 0.2); color: #475569; }
        .mapa-frota-alertas .mapa-frota-alertas__subtit { margin: 0.85rem 0 0.4rem 0; font-size: 0.95rem; color: var(--text-primary); font-weight: 600; }
        .mapa-frota-alertas .mapa-frota-alertas__subtit:first-child { margin-top: 0; }
        .mapa-frota-resumo-frota { margin-top: 1.25rem; font-size: 0.85rem; max-width: 100%; }
        .mapa-frota-resumo-frota h3 { margin: 0 0 0.35rem 0; font-size: 1rem; color: var(--text-primary); }
        .mapa-frota-resumo-frota .mapa-frota-table-wrap {
            overflow-x: auto;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            max-height: 360px;
            overflow-y: auto;
        }
        .mapa-frota-resumo-frota table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
        }
        .mapa-frota-resumo-frota th,
        .mapa-frota-resumo-frota td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        .mapa-frota-resumo-frota th {
            position: sticky;
            top: 0;
            background: var(--card-bg, var(--bg-secondary));
            color: var(--text-secondary);
            font-weight: 600;
            z-index: 1;
        }
        .mapa-frota-resumo-frota tr:last-child td { border-bottom: none; }
        .mapa-frota-resumo-frota tbody tr:hover { background: rgba(0,0,0,0.03); }
        .mapa-frota-resumo-frota .cell-placa { font-weight: 600; white-space: nowrap; }
        .mapa-frota-resumo-frota .badge-sts {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.72rem;
            font-weight: 600;
        }
        .mapa-frota-resumo-frota .badge-sts--mov { background: rgba(34, 197, 94, 0.2); color: #15803d; }
        .mapa-frota-resumo-frota .badge-sts--par { background: rgba(100, 116, 139, 0.2); color: #475569; }
        .mapa-frota-resumo-frota .badge-sts--oci { background: rgba(234, 179, 8, 0.25); color: #a16207; }
        .mapa-frota-resumo-frota .badge-mock { color: #b45309; font-weight: 600; font-size: 0.7rem; }
        .mapa-frota-resumo-frota .lista-empty { margin: 0.5rem 0 0 0; color: var(--text-muted); font-size: 0.85rem; }
        /* Tabela ↔ mapa: clique, destaque de problema e botão seguir */
        .mapa-frota-resumo-frota tbody tr { transition: background 0.12s ease; }
        .mapa-frota-resumo-frota tbody tr:hover { background: var(--bg-tertiary, rgba(127,127,127,0.10)); }
        .mapa-frota-resumo-frota tbody tr.is-stale { background: rgba(239, 68, 68, 0.08); }
        .mapa-frota-resumo-frota tbody tr.is-stale:hover { background: rgba(239, 68, 68, 0.16); }
        .mapa-frota-resumo-frota tbody tr.is-following { box-shadow: inset 3px 0 0 #2563eb; }
        .mapa-frota-resumo-frota .badge-stale { color: #fff; background: #dc2626; font-weight: 700; font-size: 0.62rem; padding: 1px 6px; border-radius: 999px; margin-left: 4px; }
        .mapa-frota-resumo-frota .mapa-follow-btn { border: 1px solid var(--border-color); background: var(--card-bg, var(--bg-secondary)); color: var(--text-muted); border-radius: 6px; padding: 2px 7px; cursor: pointer; font-size: 0.75rem; }
        .mapa-frota-resumo-frota .mapa-follow-btn:hover { color: var(--text-primary); }
        .mapa-frota-resumo-frota .mapa-follow-btn.is-on { background: #2563eb; border-color: #2563eb; color: #fff; }
        .page-mapa-frota .top-header { z-index: 2000; }
        .page-mapa-frota .notification-dropdown,
        .page-mapa-frota .profile-dropdown,
        .page-mapa-frota .empresa-dropdown { z-index: 2100; }
        /* Barra de filtros acima do mapa (evita cliques “mortos” por empilhamento) */
        .page-mapa-frota .mapa-frota-wrap .fornc-toolbar {
            position: relative;
            z-index: 30;
            isolation: isolate;
        }
        .page-mapa-frota .mapa-frota-wrap .fornc-btn-row { position: relative; z-index: 31; }
        .page-mapa-frota .mapa-frota-wrap .mapa-frota-ajuda { position: relative; z-index: 29; }
        .page-mapa-frota .mapa-frota-map-wrap { position: relative; z-index: 1; }
        .sf-mapa-frota-balao { line-height: 1.35; font-size: 0.875rem; }
        .sf-mapa-frota-balao__tit { margin-bottom: 0.2rem; }
        .sf-mapa-frota-balao__meta {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 0.15rem 0.35rem;
            font-size: 0.8125rem;
        }
        .sf-mapa-frota-balao__meta > span:not(.sf-mapa-frota-balao__sep) { white-space: nowrap; }
        .sf-mapa-frota-balao__sep { opacity: 0.55; user-select: none; }
        .sf-mapa-frota-balao__dt { color: var(--text-secondary, #64748b); }
        .sf-mapa-frota-balao__sub { margin-top: 0.2rem; font-size: 0.75rem; color: var(--text-muted, #64748b); line-height: 1.3; }
        .sf-mapa-frota-balao__end { margin-top: 0.25rem; font-size: 0.78rem; color: var(--text-secondary, #475569); line-height: 1.3; }
        .sf-mapa-frota-balao__end i { color: var(--accent-primary, #3b82f6); margin-right: 3px; }
        .sf-mapa-frota-balao__nav { margin-top: 0.3rem; font-size: 0.78rem; }
        .sf-mapa-frota-balao__nav a { color: var(--accent-primary, #3b82f6); text-decoration: none; font-weight: 600; }
        .sf-mapa-frota-balao__nav a:hover { text-decoration: underline; }
        .mapa-frota-legend i.dot { width: 12px; height: 12px; border-radius: 50%; }
        .mapa-frota-updated { font-size: 0.72rem; color: var(--text-muted); margin-top: 0.15rem; }
        .mapa-frota-map-wrap.is-fullscreen { background: var(--bg-primary, #fff); padding: 0; }
        .mapa-frota-map-wrap.is-fullscreen .mapa-frota-canvas { height: 100vh !important; }
        /* Cluster com algum veículo sem sinal: bolha vermelha de alerta */
        .marker-cluster.sf-cluster-alerta { background: rgba(220, 38, 38, 0.30); }
        .marker-cluster.sf-cluster-alerta div { background: rgba(220, 38, 38, 0.85); color: #fff; }
        /* Leaflet: largura para caber Vel · Bat · data na horizontal; quebra só se a tela for estreita */
        .leaflet-tooltip.sf-mapa-frota-tip {
            white-space: normal;
            max-width: min(560px, 94vw);
            line-height: 1.35;
        }
        #mapaOpcoesPanel {
            display: none;
            margin-bottom: 0.5rem;
            padding: 0.65rem 0.75rem;
            background: var(--card-bg, var(--bg-secondary));
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.85rem;
        }
        #mapaOpcoesPanel.is-open { display: block; }
        #mapaOpcoesPanel label { display: flex; align-items: center; gap: 0.5rem; margin: 0.35rem 0; cursor: pointer; }
        .mapa-frota-ajuda.is-hidden { display: none; }
        body.page-mapa-frota .dashboard-header { display: none; }
        body.page-mapa-frota .dashboard-content.routes-modern-page { padding-top: 8px; }
        .mapa-frota-score-wrap {
            margin: 0 0 1rem 0;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--card-bg, var(--bg-secondary));
        }
        .mapa-frota-score-wrap summary {
            cursor: pointer;
            padding: 0.65rem 0.85rem;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-primary);
            list-style: none;
        }
        .mapa-frota-score-wrap summary::-webkit-details-marker { display: none; }
        .mapa-frota-score-wrap .mapa-frota-score-inner { padding: 0 0.85rem 0.85rem; }
        .mapa-frota-tipos-alertas {
            margin: 0.35rem 0 0.75rem 0;
            font-size: 0.78rem;
            color: var(--text-secondary);
        }
        .mapa-frota-kpis {
            margin: 0 0 1.25rem 0;
            padding: 1rem 1.1rem;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: var(--card-bg, var(--bg-secondary));
        }
        .mapa-frota-kpis h2 {
            margin: 0 0 0.75rem 0;
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .mapa-frota-kpis__grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 0.65rem;
        }
        .mapa-frota-kpi-card {
            padding: 0.65rem 0.75rem;
            border-radius: 8px;
            background: rgba(0,0,0,0.04);
            border: 1px solid var(--border-color);
        }
        .mapa-frota-kpi-card .v {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.2;
        }
        .mapa-frota-kpi-card .l {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--text-muted);
            margin-top: 0.2rem;
        }
        .mapa-frota-kpi-card--warn .v { color: #c2410c; }
        .mapa-frota-score {
            margin-top: 1rem;
            padding-top: 0.85rem;
            border-top: 1px solid var(--border-color);
        }
        .mapa-frota-score h3 {
            margin: 0 0 0.5rem 0;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .mapa-frota-score table {
            width: 100%;
            font-size: 0.8125rem;
            border-collapse: collapse;
        }
        .mapa-frota-score th, .mapa-frota-score td {
            padding: 6px 8px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        .mapa-frota-score th { color: var(--text-muted); font-weight: 600; }
        .mapa-frota-score .sc-good { color: #15803d; font-weight: 700; }
        .mapa-frota-score .sc-mid { color: #a16207; font-weight: 700; }
        .mapa-frota-score .sc-bad { color: #b91c1c; font-weight: 700; }
        .mapa-frota-alertas .tag.bateria_critica { background: rgba(220, 38, 38, 0.2); color: #991b1b; }
        .mapa-frota-alertas .tag.perda_sinal_gps { background: rgba(59, 130, 246, 0.18); color: #1d4ed8; }
        .mapa-frota-alertas .tag.velocidade_impossivel { background: rgba(127, 29, 29, 0.25); color: #7f1d1d; }
        .mapa-frota-alertas .tag.salto_suspeito { background: rgba(234, 88, 12, 0.2); color: #c2410c; }
        .mapa-frota-alertas .tag.ignicao_parado { background: rgba(100, 116, 139, 0.25); color: #334155; }
    </style>
</head>
<body class="routes-modern page-mapa-frota">
<div class="app-container">
    <?php include dirname(__DIR__) . '/includes/sidebar_pages.php'; ?>
    <div class="main-content">
        <?php include dirname(__DIR__) . '/includes/header.php'; ?>
        <div class="dashboard-content routes-modern-page mapa-frota-wrap fornc-page">

            <div class="fornc-kpi-strip" aria-label="Indicadores GPS (24 h)">
                <div class="fornc-kpi-cell">
                    <div class="fornc-kpi-head"><i class="fas fa-satellite-dish fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Sinal ≤15 min</span></div>
                    <span class="val" id="kpiSinalRecente"><?php echo (int) ($mapa_kpis['veiculos_sinal_recente'] ?? 0); ?></span>
                </div>
                <div class="fornc-kpi-cell">
                    <div class="fornc-kpi-head"><i class="fas fa-triangle-exclamation fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Sem sinal &gt;30 min</span></div>
                    <span class="val" id="kpiSemSinal"><?php echo (int) ($mapa_kpis['veiculos_sem_sinal_30m'] ?? 0); ?></span>
                </div>
                <div class="fornc-kpi-cell">
                    <div class="fornc-kpi-head"><i class="fas fa-location-dot fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Pontos GPS 24 h</span></div>
                    <span class="val"><?php echo number_format((int) ($mapa_kpis['pontos_gps_24h'] ?? 0), 0, ',', '.'); ?></span>
                </div>
                <div class="fornc-kpi-cell">
                    <div class="fornc-kpi-head"><i class="fas fa-bell fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Alertas 24 h</span></div>
                    <span class="val"><?php echo (int) ($mapa_kpis['alertas_24h'] ?? 0); ?></span>
                </div>
            </div>
            <?php
            $porTipo = $mapa_kpis['alertas_por_tipo'] ?? [];
            if (is_array($porTipo) && count($porTipo) > 0) :
                ksort($porTipo);
                ?>
            <p class="mapa-frota-tipos-alertas">
                <?php foreach ($porTipo as $tipo => $q) : ?>
                    <span style="margin-right:10px;"><strong><?php echo htmlspecialchars((string) $tipo); ?></strong>: <?php echo (int) $q; ?></span>
                <?php endforeach; ?>
            </p>
            <?php endif; ?>

            <details class="mapa-frota-score-wrap">
                <summary><i class="fas fa-chart-line" style="opacity:.85;margin-right:6px;"></i> Score motoristas (7 dias) — <?php echo count($mapa_scores) > 0 ? count($mapa_scores) . ' com alertas' : 'sem alertas recentes'; ?></summary>
                <div class="mapa-frota-score-inner">
                    <p class="form-text" style="margin:0 0 0.5rem 0;font-size:0.78rem;opacity:0.88;">
                        Quanto menor o score, mais eventos de risco nos últimos dias. Inclui veículos sem nunca ter enviado GPS no KPI “sem sinal”.
                    </p>
                    <?php if (count($mapa_scores) === 0) : ?>
                        <p class="lista-empty" style="margin:0;">Nenhum alerta operacional nos últimos 7 dias — frota OK neste critério.</p>
                    <?php else : ?>
                    <table class="mapa-frota-score">
                        <thead>
                            <tr>
                                <th>Motorista</th>
                                <th>Alertas</th>
                                <th>Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mapa_scores as $s) :
                                $sc = (int) ($s['score'] ?? 0);
                                $cls = $sc >= 75 ? 'sc-good' : ($sc >= 50 ? 'sc-mid' : 'sc-bad');
                                ?>
                            <tr>
                                <td><?php echo htmlspecialchars($s['nome'] ?? ''); ?></td>
                                <td><?php echo (int) ($s['alertas'] ?? 0); ?></td>
                                <td class="<?php echo $cls; ?>"><?php echo $sc; ?>/100</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </details>

            <div class="fornc-toolbar">
                <div class="fornc-search-block">
                    <label for="searchRoute">Busca rápida</label>
                    <div class="fornc-search-inner">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchRoute" placeholder="Placa, motorista, modelo..." autocomplete="off">
                    </div>
                </div>
                <div class="fornc-filters-inline">
                    <div class="fg">
                        <label for="driverFilter">Motorista</label>
                        <select id="driverFilter" title="Filtrar por motorista (tempo real)">
                            <option value="">Todos os motoristas</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label for="rtVeiculo">Veículo (tempo real)</label>
                        <select id="rtVeiculo" title="Filtra apenas o mapa e a tabela em tempo real">
                            <option value="">Todos os veículos</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label for="histVeiculo">Veículo (rota)</label>
                        <select id="histVeiculo" title="Veículo da trilha azul — use com Carregar rota">
                            <option value="">Carregando…</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label for="histIni">Início (histórico)</label>
                        <input type="datetime-local" id="histIni" autocomplete="off" title="Período da trilha azul — use com Carregar rota">
                    </div>
                    <div class="fg">
                        <label for="histFim">Fim (histórico)</label>
                        <input type="datetime-local" id="histFim" autocomplete="off" title="Período da trilha azul — use com Carregar rota">
                    </div>
                    <div class="fg fg--rt">
                        <input type="checkbox" id="rtAtivo" checked>
                        <label for="rtAtivo">Atualizar tempo real (30 s)</label>
                    </div>
                </div>
                <div class="fornc-btn-row">
                    <button type="button" class="fornc-btn fornc-btn--ghost preset" id="mapaFrotaPreset6h" data-hours="6">6 h</button>
                    <button type="button" class="fornc-btn fornc-btn--ghost preset" id="mapaFrotaPreset24h" data-hours="24">24 h</button>
                    <button type="button" class="fornc-btn fornc-btn--ghost preset" id="mapaFrotaPreset72h" data-hours="72">3 dias</button>
                    <button type="button" class="fornc-btn fornc-btn--primary" id="histBtnCarregar"><i class="fas fa-route"></i> Carregar rota</button>
                    <button type="button" class="fornc-btn fornc-btn--muted" id="histBtnLimpar">Limpar rota</button>
                    <button type="button" class="fornc-btn fornc-btn--accent" id="applyRouteFilters"><i class="fas fa-search"></i> Pesquisar</button>
                    <button type="button" class="fornc-btn fornc-btn--ghost" id="filterBtn"><i class="fas fa-sliders-h"></i> Opções</button>
                    <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    <button type="button" class="fornc-btn fornc-btn--ghost" id="btnMapaToggle" title="Alternar entre OpenStreetMap e Google Maps">
                        <i class="fas fa-map"></i> <span id="btnMapaToggleLabel">Google Maps</span>
                    </button>
                    <button type="button" class="fornc-btn fornc-btn--ghost" id="btnMapaFullscreen" title="Tela cheia do mapa"><i class="fas fa-expand"></i></button>
                    <button type="button" class="fornc-btn fornc-btn--muted" id="exportBtn"><i class="fas fa-file-export"></i> Exportar</button>
                </div>
            </div>

            <div id="mapaOpcoesPanel" aria-label="Opções do mapa">
                <strong style="display:block;margin-bottom:0.35rem;">Exibição</strong>
                <label><input type="checkbox" id="optMostrarCercas" checked> Mostrar cercas no mapa</label>
                <label><input type="checkbox" id="optMostrarAlertasLista" checked> Mostrar lista de alertas abaixo</label>
            </div>

            <div class="mapa-frota-map-wrap" role="application" aria-label="Mapa de posições dos veículos">
                <div id="mapaFrotaLeaflet" class="mapa-frota-canvas"></div>
                <div id="mapaFrotaGoogle" class="mapa-frota-canvas" style="display:none;"></div>
            </div>
            <div class="mapa-frota-legend" id="mapaFrotaLegend">
                <span><i class="dot" style="background:#16a34a;"></i> Em movimento</span>
                <span><i class="dot" style="background:#f59e0b;"></i> Ocioso</span>
                <span><i class="dot" style="background:#dc2626;"></i> Parado</span>
                <span><i class="dot" style="background:#9ca3af;"></i> Sem sinal &gt;30 min</span>
                <span><i style="background:#3b82f6;"></i> Trilha (histórico)</span>
                <span><i style="background:#16a34a;"></i> Cerca</span>
            </div>
            <p class="mapa-frota-meta" id="mapaFrotaStatus" aria-live="polite">Carregando…</p>
            <p class="mapa-frota-meta mapa-frota-updated" id="mapaFrotaUpdated" aria-live="polite"></p>
            <div class="mapa-frota-alertas" id="mapaAlertasBox" aria-live="polite">
                <h3>Alertas</h3>
                <p class="mapa-frota-alertas__subtit">Cercas eletrônicas</p>
                <ul id="mapaAlertasListCerca"><li>Carregando…</li></ul>
                <p class="mapa-frota-alertas__subtit">Operacionais (GPS)</p>
                <p class="form-text" style="margin:0 0 0.35rem 0;font-size:0.8rem;opacity:0.9;">Bateria, velocidade, mock, perda de sinal, salto suspeito, ignição parado etc.</p>
                <ul id="mapaAlertasListOper"><li>Carregando…</li></ul>
            </div>
            <div class="mapa-frota-resumo-frota" id="mapaResumoFrotaWrap" aria-live="polite">
                <h3>Situação por veículo (tempo real)</h3>
                <p class="form-text" style="margin:0 0 0.5rem 0;font-size:0.8rem;opacity:0.9;">Resumo das últimas posições; respeita busca, motorista e filtro <strong>Veículo (tempo real)</strong>.</p>
                <div class="mapa-frota-table-wrap" id="mapaListaVeiculosTableWrap">
                    <table class="mapa-frota-table" id="mapaListaVeiculosTable">
                        <thead>
                            <tr>
                                <th scope="col">Placa</th>
                                <th scope="col">Modelo</th>
                                <th scope="col">Motorista</th>
                                <th scope="col">Situação</th>
                                <th scope="col">Vel.</th>
                                <th scope="col">Bat.</th>
                                <th scope="col">Prec.</th>
                                <th scope="col">Última posição</th>
                            </tr>
                        </thead>
                        <tbody id="mapaListaVeiculosTbody"></tbody>
                    </table>
                </div>
                <p class="lista-empty" id="mapaListaVeiculosEmpty" hidden>Nenhum veículo com posição em tempo real para os filtros atuais.</p>
            </div>
        </div>
        <?php include dirname(__DIR__) . '/includes/footer.php'; ?>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js" crossorigin="anonymous"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="../google-maps/maps.js"></script>
<script src="../js/mapa-frota.js?v=17"></script>
<script src="../js/theme.js"></script>
<script src="../js/sidebar.js"></script>

<?php include '../includes/scroll_to_top.php'; ?>
<?php $sf_help_key = 'mapa_frota'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
<?php sf_page_help_js('..'); ?>
</body>
</html>
