
<?php
require_once '../includes/config.php';
require_once __DIR__ . '/../includes/sf_page_help.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';
require_once '../includes/sf_api_base.php';

configure_session();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_authentication();

$page_title = "Comissões";
// Layout moderno (fornc-page); ?classic=1 para o layout anterior
$is_modern = !isset($_GET['classic']) || (string) $_GET['classic'] !== '1';
$empresa_id = (int) $_SESSION['empresa_id'];

require_once '../includes/comissoes_helpers.php';

if (!can_view_financial_data()) {
    http_response_code(403);
    die('Sem permissão para visualizar dados financeiros.');
}

$conn = getConnection();
ensureCommissionPaymentsTable($conn);

$pagina_atual = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;
if (!in_array($per_page, [5, 10, 25, 50, 100], true)) {
    $per_page = 10;
}

$filters = [
    'mes' => '',
    'motorista' => '',
    'veiculo' => '',
    'search' => '',
    'status_pagamento' => '',
];

if (!empty($_GET['mes']) && preg_match('/^\d{4}-\d{2}$/', $_GET['mes'])) {
    $filters['mes'] = $_GET['mes'];
}

if (!empty($_GET['motorista'])) {
    $filters['motorista'] = $_GET['motorista'];
}

if (!empty($_GET['veiculo'])) {
    $filters['veiculo'] = $_GET['veiculo'];
}

$filters['search'] = trim($_GET['search'] ?? '');

if (!empty($_GET['status_pagamento']) && in_array($_GET['status_pagamento'], ['pago', 'pendente'], true)) {
    $filters['status_pagamento'] = $_GET['status_pagamento'];
}

$com_sort = isset($_GET['sort']) ? preg_replace('/[^a-z_]/', '', (string) $_GET['sort']) : 'data_rota';
$com_dir = (isset($_GET['dir']) && strtoupper((string) $_GET['dir']) === 'ASC') ? 'ASC' : 'DESC';

function comissoes_sort_link_url($field, $com_sort, $com_dir, $per_page, $is_modern, array $filters) {
    $next_dir = 'DESC';
    if ($com_sort === $field) {
        $next_dir = $com_dir === 'ASC' ? 'DESC' : 'ASC';
    } else {
        $text_like = ['motorista_nome', 'veiculo_placa', 'status_pagamento'];
        $next_dir = in_array($field, $text_like, true) ? 'ASC' : 'DESC';
    }
    $q = array_merge($filters, [
        'page' => 1,
        'per_page' => $per_page,
        'sort' => $field,
        'dir' => $next_dir,
    ]);
    if (!$is_modern) {
        $q['classic'] = '1';
    }
    return '?' . http_build_query($q);
}

function comissoes_sort_indicator($field, $com_sort, $com_dir) {
    if ($com_sort !== $field) {
        return '⇅';
    }
    return $com_dir === 'ASC' ? '▲' : '▼';
}

$resultado = getComissoes($filters, $pagina_atual, $empresa_id, $conn, $per_page, $com_sort, $com_dir);
$comissoes = $resultado['comissoes'];
$resumo = $resultado['resumo'];
$total_paginas = $resultado['total_paginas'];
$charts = $resultado['charts'] ?? [];

$chartStatusData = $charts['status'] ?? [
    'labels' => ['Pagas', 'Pendentes'],
    'values' => [0, 0]
];

$chartMonthlyData = $charts['monthly'] ?? [
    'labels' => [],
    'total' => [],
    'pagas' => [],
    'pendentes' => []
];

$chartMotoristaData = $charts['motoristas'] ?? [
    'labels' => [],
    'totais' => [],
    'pagas' => [],
    'pendentes' => [],
    'top_list' => []
];

$topMotoristas = $chartMotoristaData['top_list'] ?? [];

$filterData = getComissoesFilterData($empresa_id);
$motoristas = $filterData['motoristas'];
$veiculos = $filterData['veiculos'];
$periodos = $filterData['periodos'];

function formatarDataRota(?string $data): string
{
    if (empty($data)) {
        return '-';
    }
    return date('d/m/Y', strtotime($data));
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>

    <link rel="icon" type="image/png" href="../logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <?php if ($is_modern): ?>
    <link rel="stylesheet" href="../css/fornc-modern-page.css">
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
    <script src="../js/sf-charts-premium.js"></script>
    <style>
        .commission-charts {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 20px;
        }

        .charts-row {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr;
            gap: 20px;
        }

        .chart-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .chart-card h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }

        .chart-card canvas {
            width: 100% !important;
            height: 260px !important;
        }

        .chart-card-wide canvas {
            height: 280px;
        }

        .top-motoristas-card {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            padding: 20px;
        }

        .top-motoristas-card .card-header {
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 16px;
        }

        .top-motoristas-card .card-body {
            padding: 0;
        }

        .top-motoristas-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .top-motoristas-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .top-motoristas-list li:last-child {
            border-bottom: none;
        }

        .top-motoristas-list .values {
            text-align: right;
            font-weight: 600;
        }

        @media (max-width: 1024px) {
            .charts-row {
                grid-template-columns: 1fr;
            }

            .chart-card canvas,
            .chart-card-wide canvas {
                height: 260px;
            }
        }

        .filter-section.highlight-filter,
        .comissoes-fornc-filter.highlight-filter {
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.4);
        }

        body.comissoes-modern .dashboard-header { display: none; }
        body.comissoes-modern .dashboard-content.fornc-page { overflow-x: auto; }
        body.comissoes-modern table.data-table,
        body.comissoes-modern table.fornc-table { min-width: 960px; }

        body.comissoes-modern table.fornc-table th.sortable .th-sort-link {
            color: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        body.comissoes-modern table.fornc-table th.sortable .th-sort-link:hover {
            color: var(--accent-primary, #3b82f6);
        }
        body.comissoes-modern table.fornc-table th.sortable.sorted .th-sort-link {
            font-weight: 600;
        }
        table.fornc-table .sort-ind { font-size: 0.75rem; opacity: 0.85; }

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.4);
            overflow-y: auto;
        }

        .modal-content {
            background-color: var(--bg-secondary);
            margin: 5% auto;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            width: min(600px, 90%);
            overflow: hidden;
            color: var(--text-primary);
        }

        .modal-header, .modal-footer {
            padding: 16px 24px;
            background-color: var(--bg-secondary);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
        }

        .modal-body {
            padding: 24px;
            line-height: 1.6;
        }

        .modal-body ul {
            margin: 12px 0 0 18px;
            padding: 0;
        }

        .close-modal {
            cursor: pointer;
            font-size: 1.5rem;
            color: var(--text-secondary);
        }

        .close-modal:hover {
            color: var(--text-primary);
        }

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background: var(--accent-primary);
            color: #fff;
        }

        .filter-section {
            margin: 20px 0;
        }

        .filter-section .search-box {
            position: relative;
            max-width: 320px;
        }

        .filter-section .search-box input {
            width: 100%;
            padding: 10px 36px 10px 12px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background-color: var(--bg-secondary);
            color: var(--text-primary);
        }

        .filter-section .search-box i {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .filter-options {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .filter-options select {
            min-width: 180px;
            padding: 8px 12px;
            border-radius: 10px;
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            transition: border-color 0.2s ease;
        }

        .filter-options select:focus {
            border-color: var(--accent-primary);
            outline: none;
        }

        .filter-options .filter-label {
            font-size: 0.9rem;
            color: var(--text-primary);
            margin-right: 0.25rem;
        }
        .filter-options .filter-per-page {
            padding: 6px 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .filter-options .btn-restore-layout {
            border: none;
            background: none;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s ease;
        }

        .filter-options .btn-restore-layout i {
            font-size: 1rem;
        }

        .filter-options .btn-restore-layout:hover {
            color: var(--accent-primary);
        }

        .light-theme .filter-options .btn-restore-layout {
            color: var(--text-secondary);
        }

        .light-theme .filter-options .btn-restore-layout:hover {
            color: var(--accent-primary);
        }

    </style>
    <?php sf_page_help_css('..'); ?>
</head>
<body class="<?php echo $is_modern ? 'comissoes-modern' : ''; ?>">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>

        <div class="main-content">
            <?php include '../includes/header.php'; ?>

            <div class="dashboard-content<?php echo $is_modern ? ' fornc-page' : ''; ?>">
                <?php if ($is_modern): ?>
                <p class="fornc-modern-hint">
                    Comissões de viagens aprovadas. Use <code>?classic=1</code> para o layout anterior.
                </p>
                <div class="fornc-kpi-strip">
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-percent fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Total comissões</span></div><span class="val">R$ <?php echo number_format($resumo['total_comissao'] ?? 0, 2, ',', '.'); ?></span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-circle-check fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Pagas</span></div><span class="val">R$ <?php echo number_format($resumo['total_pago'] ?? 0, 2, ',', '.'); ?></span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-hourglass-half fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Pendentes</span></div><span class="val">R$ <?php echo number_format($resumo['total_pendente'] ?? 0, 2, ',', '.'); ?></span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-chart-pie fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">% pago</span></div><span class="val"><?php echo number_format($resumo['percentual_pago'] ?? 0, 2, ',', '.'); ?>%</span></div>
                </div>
                <p class="fornc-kpi-summary"><?php echo (int) ($resumo['total_viagens'] ?? 0); ?> viagens nos filtros atuais.</p>

                <div id="sfComInsights"></div>

                <form method="get" id="comissoesFilterForm" class="comissoes-fornc-filter">
                    <input type="hidden" name="page" id="comissoesFormPage" value="<?php echo $pagina_atual; ?>">
                    <div class="fornc-toolbar">
                        <div class="fornc-search-block">
                            <label for="searchCommission">Busca rápida</label>
                            <div class="fornc-search-inner">
                                <i class="fas fa-search" aria-hidden="true"></i>
                                <input type="text" id="searchCommission" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Motorista, veículo ou data..." autocomplete="off">
                            </div>
                        </div>
                        <input type="hidden" name="mes" id="mes" data-sf-periodo-month value="<?php echo htmlspecialchars($filters['mes'] ?? ''); ?>">
                        <div class="fornc-filters-inline">
                            <div class="fg">
                                <label for="motorista">Motorista</label>
                                <select id="motorista" name="motorista" title="Motorista">
                            <option value="">Todos os motoristas</option>
                            <?php foreach ($motoristas as $motorista): ?>
                                <option value="<?php echo (int) $motorista['id']; ?>" <?php echo $filters['motorista'] == $motorista['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($motorista['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                            </div>
                            <div class="fg">
                                <label for="veiculo">Veículo</label>
                                <select id="veiculo" name="veiculo" title="Veículo">
                            <option value="">Todos os veículos</option>
                            <?php foreach ($veiculos as $veiculo): ?>
                                <option value="<?php echo (int) $veiculo['id']; ?>" <?php echo $filters['veiculo'] == $veiculo['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($veiculo['placa'] . ' - ' . $veiculo['modelo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                            </div>
                            <div class="fg">
                                <label for="status_pagamento">Status pagamento</label>
                                <select id="status_pagamento" name="status_pagamento" title="Status pagamento">
                                    <option value="">Todos</option>
                                    <option value="pago" <?php echo $filters['status_pagamento'] === 'pago' ? 'selected' : ''; ?>>Pagas</option>
                                    <option value="pendente" <?php echo $filters['status_pagamento'] === 'pendente' ? 'selected' : ''; ?>>Pendentes</option>
                                </select>
                            </div>
                            <div class="fg">
                                <label for="per_page_com">Por página</label>
                                <select name="per_page" id="per_page_com" class="filter-per-page" onchange="document.getElementById('comissoesFormPage').value=1; this.form.submit();">
                            <option value="5"  <?php echo $per_page == 5  ? 'selected' : ''; ?>>5</option>
                            <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                            <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                            </div>
                        </div>
                        <div class="fornc-btn-row">
                            <button type="button" data-sf-periodo class="fornc-btn fornc-btn--ghost" title="Filtrar por período"><i class="fas fa-calendar-alt"></i> Período</button>
                            <button type="submit" class="fornc-btn fornc-btn--accent" title="Aplicar filtros"><i class="fas fa-search"></i> Pesquisar</button>
                            <a href="comissoes.php" class="fornc-btn fornc-btn--ghost" title="Limpar filtros"><i class="fas fa-undo"></i></a>
                            <button type="button" id="filterBtn" class="fornc-btn fornc-btn--ghost" title="Destacar filtros"><i class="fas fa-sliders-h"></i> Opções</button>
                            <button type="button" id="exportCsvBtn" class="fornc-btn fornc-btn--muted" title="Exportar CSV"><i class="fas fa-file-export"></i> Exportar</button>
                            <button type="button" id="helpBtn" class="fornc-btn fornc-btn--ghost fornc-btn--icon" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                        </div>
                    </div>
                </form>
                <?php else: ?>
                <div class="dashboard-header">
                    <h1><?php echo htmlspecialchars($page_title); ?></h1>
                    <div class="dashboard-actions">
                        <div class="view-controls">
                            <button id="filterBtn" class="btn-restore-layout" title="Filtros">
                                <i class="fas fa-filter"></i>
                            </button>
                            <button id="exportCsvBtn" class="btn-toggle-layout" title="Exportar">
                                <i class="fas fa-file-export"></i>
                            </button>
                            <button id="helpBtn" class="btn-help" title="Ajuda">
                                <i class="fas fa-question-circle"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <i class="fas fa-hand-holding-dollar kpi-card-icon" aria-hidden="true"></i>
                            <h3>Total de Comissões</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($resumo['total_comissao'] ?? 0, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle"><?php echo (int) ($resumo['total_viagens'] ?? 0); ?> viagens</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <i class="fas fa-circle-check kpi-card-icon" aria-hidden="true"></i>
                            <h3>Comissões Pagas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($resumo['total_pago'] ?? 0, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle"><?php echo (int) ($resumo['viagens_pagas'] ?? 0); ?> viagens pagas</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <i class="fas fa-hourglass-half kpi-card-icon" aria-hidden="true"></i>
                            <h3>Comissões Pendentes</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($resumo['total_pendente'] ?? 0, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle"><?php echo (int) ($resumo['viagens_pendentes'] ?? 0); ?> viagens pendentes</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <i class="fas fa-chart-pie kpi-card-icon" aria-hidden="true"></i>
                            <h3>Percentual Pago</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo number_format($resumo['percentual_pago'] ?? 0, 2, ',', '.'); ?>%</span>
                                <span class="metric-subtitle">Do total de comissões</span>
                            </div>
                        </div>
                    </div>
                </div>

                <form class="filter-section" method="GET" id="comissoesFilterForm">
                    <input type="hidden" name="page" id="comissoesFormPage" value="<?php echo $pagina_atual; ?>">
                    <input type="hidden" name="classic" value="1">
                    <div class="search-box">
                        <input type="text" id="searchCommission" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Buscar por motorista, veículo ou data...">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="filter-options">
                        <span class="filter-label">Por página</span>
                        <select name="per_page" class="filter-per-page" onchange="document.getElementById('comissoesFormPage').value=1; this.form.submit();">
                            <option value="5"  <?php echo $per_page == 5  ? 'selected' : ''; ?>>5</option>
                            <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                            <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                        <select id="mes" name="mes" title="Período">
                            <option value="">Todos os períodos</option>
                            <?php foreach ($periodos as $periodo): ?>
                                <option value="<?php echo htmlspecialchars($periodo); ?>" <?php echo $filters['mes'] === $periodo ? 'selected' : ''; ?>>
                                    <?php echo date('m/Y', strtotime($periodo . '-01')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select id="motorista" name="motorista" title="Motorista">
                            <option value="">Todos os motoristas</option>
                            <?php foreach ($motoristas as $motorista): ?>
                                <option value="<?php echo (int) $motorista['id']; ?>" <?php echo $filters['motorista'] == $motorista['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($motorista['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select id="veiculo" name="veiculo" title="Veículo">
                            <option value="">Todos os veículos</option>
                            <?php foreach ($veiculos as $veiculo): ?>
                                <option value="<?php echo (int) $veiculo['id']; ?>" <?php echo $filters['veiculo'] == $veiculo['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($veiculo['placa'] . ' - ' . $veiculo['modelo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select id="status_pagamento" name="status_pagamento" title="Status pagamento">
                            <option value="">Todos os status</option>
                            <option value="pago" <?php echo $filters['status_pagamento'] === 'pago' ? 'selected' : ''; ?>>Pagas</option>
                            <option value="pendente" <?php echo $filters['status_pagamento'] === 'pendente' ? 'selected' : ''; ?>>Pendentes</option>
                        </select>
                        <button type="submit" class="btn-restore-layout" title="Aplicar filtros">
                            <i class="fas fa-filter"></i>
                        </button>
                        <a href="comissoes.php?classic=1" class="btn-restore-layout" title="Limpar filtros">
                            <i class="fas fa-undo"></i>
                        </a>
                    </div>
                </form>
                <?php endif; ?>

                <div class="com-bulk-bar" id="comBulkBar" style="display:none;margin-bottom:0.75rem;">
                    <span id="comBulkCount">0 selecionada(s)</span>
                    <button type="button" id="comBulkPaidBtn" class="fornc-btn fornc-btn--accent">
                        <i class="fas fa-check-double"></i> Marcar selecionadas como pagas
                    </button>
                </div>

                <?php
                $comissoes_modern_empty = !empty($is_modern) && empty($comissoes);
                ?>
                <?php if (!empty($is_modern)): ?>
                <div class="fornc-empty-state<?php echo $comissoes_modern_empty ? ' visible' : ''; ?>" id="comissoesEmptyState" role="status" aria-live="polite">
                    <p class="fornc-empty-state-title">Nenhuma comissão encontrada</p>
                    <p class="fornc-empty-state-hint">Ajuste o período, o motorista ou o veículo nos filtros. As comissões vêm dos fretes registrados em rotas.</p>
                    <a href="routes.php" class="fornc-btn fornc-btn--primary"><i class="fas fa-route"></i> Ir para rotas</a>
                </div>
                <?php endif; ?>

                <div class="<?php echo $is_modern ? 'fornc-table-wrap' : 'data-table-container'; ?>" id="comissoesTableWrap"<?php echo $comissoes_modern_empty ? ' style="display:none"' : ''; ?>>
                    <table class="<?php echo $is_modern ? 'fornc-table' : 'data-table'; ?>">
                        <thead>
                            <tr>
                                <th class="com-check-col" style="width:36px;" title="Selecionar pendentes">
                                    <input type="checkbox" id="comSelectAll" aria-label="Selecionar todas pendentes">
                                </th>
                                <?php if ($is_modern): ?>
                                <th class="sortable<?php echo $com_sort === 'data_rota' ? ' sorted' : ''; ?>"><a href="<?php echo htmlspecialchars(comissoes_sort_link_url('data_rota', $com_sort, $com_dir, $per_page, $is_modern, $filters)); ?>" class="th-sort-link">Data <span class="sort-ind"><?php echo htmlspecialchars(comissoes_sort_indicator('data_rota', $com_sort, $com_dir)); ?></span></a></th>
                                <th class="sortable<?php echo $com_sort === 'motorista_nome' ? ' sorted' : ''; ?>"><a href="<?php echo htmlspecialchars(comissoes_sort_link_url('motorista_nome', $com_sort, $com_dir, $per_page, $is_modern, $filters)); ?>" class="th-sort-link">Motorista <span class="sort-ind"><?php echo htmlspecialchars(comissoes_sort_indicator('motorista_nome', $com_sort, $com_dir)); ?></span></a></th>
                                <th class="sortable<?php echo $com_sort === 'veiculo_placa' ? ' sorted' : ''; ?>"><a href="<?php echo htmlspecialchars(comissoes_sort_link_url('veiculo_placa', $com_sort, $com_dir, $per_page, $is_modern, $filters)); ?>" class="th-sort-link">Veículo <span class="sort-ind"><?php echo htmlspecialchars(comissoes_sort_indicator('veiculo_placa', $com_sort, $com_dir)); ?></span></a></th>
                                <th class="sortable<?php echo $com_sort === 'frete' ? ' sorted' : ''; ?>"><a href="<?php echo htmlspecialchars(comissoes_sort_link_url('frete', $com_sort, $com_dir, $per_page, $is_modern, $filters)); ?>" class="th-sort-link">Frete <span class="sort-ind"><?php echo htmlspecialchars(comissoes_sort_indicator('frete', $com_sort, $com_dir)); ?></span></a></th>
                                <th class="sortable<?php echo $com_sort === 'comissao' ? ' sorted' : ''; ?>"><a href="<?php echo htmlspecialchars(comissoes_sort_link_url('comissao', $com_sort, $com_dir, $per_page, $is_modern, $filters)); ?>" class="th-sort-link">Comissão <span class="sort-ind"><?php echo htmlspecialchars(comissoes_sort_indicator('comissao', $com_sort, $com_dir)); ?></span></a></th>
                                <th class="sortable<?php echo $com_sort === 'pct_comissao' ? ' sorted' : ''; ?>"><a href="<?php echo htmlspecialchars(comissoes_sort_link_url('pct_comissao', $com_sort, $com_dir, $per_page, $is_modern, $filters)); ?>" class="th-sort-link">% Comissão <span class="sort-ind"><?php echo htmlspecialchars(comissoes_sort_indicator('pct_comissao', $com_sort, $com_dir)); ?></span></a></th>
                                <th class="sortable<?php echo $com_sort === 'status_pagamento' ? ' sorted' : ''; ?>"><a href="<?php echo htmlspecialchars(comissoes_sort_link_url('status_pagamento', $com_sort, $com_dir, $per_page, $is_modern, $filters)); ?>" class="th-sort-link">Status Pagamento <span class="sort-ind"><?php echo htmlspecialchars(comissoes_sort_indicator('status_pagamento', $com_sort, $com_dir)); ?></span></a></th>
                                <th class="sortable<?php echo $com_sort === 'no_prazo' ? ' sorted' : ''; ?>"><a href="<?php echo htmlspecialchars(comissoes_sort_link_url('no_prazo', $com_sort, $com_dir, $per_page, $is_modern, $filters)); ?>" class="th-sort-link">Prazo <span class="sort-ind"><?php echo htmlspecialchars(comissoes_sort_indicator('no_prazo', $com_sort, $com_dir)); ?></span></a></th>
                                <?php else: ?>
                                <th>Data</th>
                                <th>Motorista</th>
                                <th>Veículo</th>
                                <th>Frete</th>
                                <th>Comissão</th>
                                <th>% Comissão</th>
                                <th>Status Pagamento</th>
                                <th>Prazo</th>
                                <?php endif; ?>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($comissoes)): ?>
                                <?php foreach ($comissoes as $comissao): ?>
                                    <?php
                                        $percentual = ($comissao['frete'] > 0)
                                            ? ($comissao['comissao'] / $comissao['frete']) * 100
                                            : 0;
                                    ?>
                                    <tr>
                                        <td class="com-check-col">
                                            <?php if (($comissao['status_pagamento'] ?? 'pendente') !== 'pago'): ?>
                                                <input type="checkbox" class="com-row-check" data-rota-id="<?php echo (int) $comissao['id']; ?>" aria-label="Selecionar comissão">
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo formatarDataRota($comissao['data_rota'] ?? $comissao['data_saida']); ?></td>
                                        <td><?php echo htmlspecialchars($comissao['motorista_nome'] ?? 'Motorista não informado'); ?></td>
                                        <td><?php echo htmlspecialchars(trim(($comissao['veiculo_placa'] ?? '-') . ' ' . ($comissao['veiculo_modelo'] ?? ''))); ?></td>
                                        <td>R$ <?php echo number_format($comissao['frete'], 2, ',', '.'); ?></td>
                                        <td>R$ <?php echo number_format($comissao['comissao'], 2, ',', '.'); ?></td>
                                        <td><?php echo number_format($percentual, 2, ',', '.'); ?>%</td>
                                        <td>
                                            <?php
                                                $statusPagamento = $comissao['status_pagamento'] ?? 'pendente';
                                                $badgeClass = $statusPagamento === 'pago' ? 'status-success' : 'status-warning';
                                                $label = $statusPagamento === 'pago' ? 'Pago' : 'Pendente';
                                            ?>
                                            <span class="status-badge <?php echo $badgeClass; ?>">
                                                <?php echo $label; ?>
                                            </span>
                                            <?php if (!empty($comissao['data_pagamento'])): ?>
                                                <div class="small text-muted">
                                                    Pago em <?php echo date('d/m/Y', strtotime($comissao['data_pagamento'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo !empty($comissao['no_prazo']) ? 'status-success' : 'status-warning'; ?>">
                                                <?php echo !empty($comissao['no_prazo']) ? 'No prazo' : 'Fora do prazo'; ?>
                                            </span>
                                        </td>
                                        <td class="actions">
                                            <?php if (($comissao['status_pagamento'] ?? 'pendente') === 'pago'): ?>
                                                <button 
                                                    class="btn-icon mark-pending-btn" 
                                                    data-rota-id="<?php echo (int) $comissao['id']; ?>"
                                                    title="Marcar como pendente">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            <?php else: ?>
                                                <button 
                                                    class="btn-icon mark-paid-btn" 
                                                    data-rota-id="<?php echo (int) $comissao['id']; ?>"
                                                    title="Marcar como pago">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php elseif (empty($is_modern)): ?>
                                <tr>
                                    <td colspan="10" class="text-center">Nenhuma comissão encontrada para os filtros selecionados.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php
                $total_reg_com = (int)($resultado['total'] ?? 0);
                $params_prev = array_filter(array_merge($filters, ['per_page' => $per_page, 'page' => max(1, $pagina_atual - 1)]));
                $params_next = array_filter(array_merge($filters, ['per_page' => $per_page, 'page' => min($total_paginas, $pagina_atual + 1)]));
                if ($com_sort !== 'data_rota' || $com_dir !== 'DESC') {
                    $params_prev['sort'] = $com_sort;
                    $params_prev['dir'] = $com_dir;
                    $params_next['sort'] = $com_sort;
                    $params_next['dir'] = $com_dir;
                }
                if (!$is_modern) {
                    $params_prev['classic'] = '1';
                    $params_next['classic'] = '1';
                }
                ?>
                <?php if ($is_modern): ?><div class="fornc-pagination-bar"><?php endif; ?>
                <div class="pagination<?php echo $is_modern ? ' fornc-modern-pagination' : ''; ?>">
                    <a href="<?php echo $pagina_atual <= 1 ? '#' : '?' . http_build_query($params_prev); ?>" 
                       class="pagination-btn <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>" 
                       id="prevPageBtn"><i class="fas fa-chevron-left"></i></a>
                    <span class="pagination-info">Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?> (<?php echo $total_reg_com; ?> registros)</span>
                    <a href="<?php echo $pagina_atual >= $total_paginas ? '#' : '?' . http_build_query($params_next); ?>" 
                       class="pagination-btn <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>" 
                       id="nextPageBtn"><i class="fas fa-chevron-right"></i></a>
                </div>
                <?php if ($is_modern): ?></div><?php endif; ?>

                <div class="commission-charts">
                    <div class="charts-row">
                        <div class="chart-card">
                            <h3>Situação de Pagamento</h3>
                            <canvas id="statusChart"></canvas>
                        </div>
                        <div class="chart-card chart-card-wide">
                            <h3>Evolução Mensal de Comissões</h3>
                            <canvas id="monthlyChart"></canvas>
                        </div>
                        <div class="chart-card">
                            <h3>Motoristas x Comissão</h3>
                            <canvas id="motoristaChart"></canvas>
                        </div>
                    </div>
                    <div class="top-motoristas-card">
                        <div class="card-header">
                            <h3>Top Motoristas por Comissão</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($topMotoristas)): ?>
                                <ul class="top-motoristas-list">
                                    <?php foreach ($topMotoristas as $motorista): ?>
                                        <li>
                                            <div>
                                                <strong><?php echo htmlspecialchars($motorista['nome']); ?></strong>
                                                <div class="text-muted small">
                                                    Pago: R$ <?php echo number_format($motorista['pago'], 2, ',', '.'); ?> •
                                                    Pendente: R$ <?php echo number_format($motorista['pendente'], 2, ',', '.'); ?>
                                                </div>
                                            </div>
                                            <div class="values">
                                                <span class="total">R$ <?php echo number_format($motorista['total'], 2, ',', '.'); ?></span>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted">Nenhum motorista com comissão registrada para os filtros selecionados.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
    <?php $sf_help_key = 'comissoes'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>


                <!-- Modal data de pagamento -->
                <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="comPaymentModal" style="display:none;">
                    <div class="modal-content" style="max-width:420px;">
                        <div class="modal-header">
                            <h2>Confirmar pagamento</h2>
                            <span class="close-modal" id="comPaymentClose">&times;</span>
                        </div>
                        <div class="modal-body">
                            <label for="comPaymentDate">Data do pagamento</label>
                            <input type="date" id="comPaymentDate" name="comPaymentDate" value="<?php echo date('Y-m-d'); ?>" class="form-control" style="width:100%;margin-top:0.35rem;">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-secondary" id="comPaymentCancel">Cancelar</button>
                            <button type="button" class="fornc-btn fornc-btn--accent" id="comPaymentConfirm">Confirmar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/sf-periodo.js"></script>
    <?php sf_render_api_scripts(); ?>
    <script>
        window.__COMISSOES_CHARTS__ = <?php echo json_encode([
            'status' => $chartStatusData,
            'monthly' => $chartMonthlyData,
            'motoristas' => $chartMotoristaData,
        ], JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="../js/comissoes.js?v=1"></script>
    <script src="../js/sf_insights.js?v=1.3"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.SFInsights) {
            SFInsights.init({
                mountId: 'sfComInsights',
                insightsUrl: '../api/comissoes_insights.php',
                resumoUrl: '../api/comissoes_resumo_ia.php',
                title: 'Insights de comissões',
                sectionTitle: 'Comissões pendentes prioritárias',
                emptyOk: 'Nenhuma comissão pendente com alerta relevante.',
                stats: function (j) {
                    return [
                        { label: 'pendentes', value: j.total_pendentes || 0, tone: 'aviso' },
                        { label: 'em aberto', value: 'R$ ' + Number(j.total_pendente || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 }), tone: 'aviso' }
                    ];
                }
            });
        }
    });
    </script>

    <?php include '../includes/scroll_to_top.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>

