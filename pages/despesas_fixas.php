
<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once __DIR__ . '/../includes/sf_page_help.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_once '../includes/sf_api_base.php';
require_once __DIR__ . '/../includes/despesas_fixas_list.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Require authentication
require_authentication();

// Create database connection
$conn = getConnection();

// Set page title
$page_title = "Despesas Fixas";

// Layout moderno (fornc-page); ?classic=1 para o layout anterior
$is_modern = !isset($_GET['classic']) || (string) $_GET['classic'] !== '1';

// Por página: 5, 10, 25, 50, 100 — padrão 10
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if (!in_array($per_page, [5, 10, 25, 50, 100], true)) {
    $per_page = 10;
}

// Primeira página + KPIs no HTML (JS só refetch em filtros/paginação).
$df_page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$df_sort = isset($_GET['sort']) ? trim((string) $_GET['sort']) : 'vencimento';
$df_dir = (isset($_GET['dir']) && strtoupper(trim((string) $_GET['dir'])) === 'ASC') ? 'ASC' : 'DESC';
$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);

$df_initial = [
    'success' => true,
    'despesas' => [],
    'metrics' => ['total_despesas' => 0, 'total_pendentes' => 0, 'total_vencidas' => 0, 'valor_total' => 0],
    'charts' => despesas_fixas_get_chart_data($conn, $empresa_id, null, null, false),
    'pagina_atual' => 1,
    'total_paginas' => 1,
    'total_registros' => 0,
    'total_cadastrados' => 0,
];
if ($empresa_id > 0) {
    try {
        $df_initial = despesas_fixas_fetch_list($conn, $empresa_id, [
            'page' => $df_page,
            'per_page' => $per_page,
            'sort' => $df_sort,
            'dir' => $df_dir,
        ]);
    } catch (Throwable $e) {
        error_log('despesas_fixas SSR: ' . $e->getMessage());
    }
}

$df_metrics = $df_initial['metrics'] ?? [];
$df_kpi_qtd = (int) ($df_metrics['total_despesas'] ?? 0);
$df_kpi_valor = despesas_fixas_fmt_money($df_metrics['valor_total'] ?? 0);
$df_kpi_pendentes = (int) ($df_metrics['total_pendentes'] ?? 0);
$df_kpi_vencidas = (int) ($df_metrics['total_vencidas'] ?? 0);
$despesas = $df_initial['despesas'] ?? [];
$pagina_atual = (int) ($df_initial['pagina_atual'] ?? 1);
$total_paginas = max(1, (int) ($df_initial['total_paginas'] ?? 1));
$df_total_registros = (int) ($df_initial['total_registros'] ?? 0);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../logo.png">
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <?php if ($is_modern): ?>
    <link rel="stylesheet" href="../css/fornc-modern-page.css">
    <?php endif; ?>
    <link rel="stylesheet" href="../css/maintenance.css">
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
    <script src="../js/sf-charts-premium.js"></script>
    
    <!-- Sortable.js for drag-and-drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
    <!-- Custom scripts -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/sf-periodo.js"></script>
    <?php sf_render_api_scripts(); ?>
    <script>window.__DESPESAS_FIXAS_INITIAL__=<?php echo json_encode($df_initial, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
    <script src="../js/despesas_fixas.js?v=1.3"></script>
    
    <style>
        /* Modal financeiro: deixa os campos em 2 colunas (desktop) e 1 coluna (mobile) */
        #despesaModal .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 14px;
            margin-bottom: 4px;
        }
        @media (max-width: 640px) {
            #despesaModal .form-grid {
                grid-template-columns: 1fr;
            }
        }
        body.despesas-fixas-modern .dashboard-header { display: none; }
        body.despesas-fixas-modern .dashboard-content.fornc-page { overflow-x: auto; }
        body.despesas-fixas-modern #despesasTable.fornc-table { min-width: 1020px; }
        #despesasTable thead th.sortable { cursor: pointer; user-select: none; }
        #despesasTable thead th.sortable .sort-ind { font-size: 0.75rem; opacity: 0.85; margin-left: 0.15rem; }
        #despesasTable thead th.sortable.sorted { font-weight: 600; }
    </style>
    <?php sf_page_help_css('..'); ?>
</head>
<body class="<?php echo $is_modern ? 'despesas-fixas-modern' : ''; ?>">
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include '../includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include '../includes/header.php'; ?>
            
            <!-- Page Content -->
            <div class="dashboard-content<?php echo $is_modern ? ' fornc-page' : ''; ?>">
                <?php if ($is_modern): ?>
                <p class="fornc-modern-hint">
                    Despesas fixas por veículo e tipo. Use <code>?classic=1</code> para o layout anterior.
                </p>
                <div class="fornc-kpi-strip">
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-list fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Qtd. despesas</span></div><span class="val" id="dfKpiQtd"><?php echo (int) $df_kpi_qtd; ?></span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-coins fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Valor total</span></div><span class="val" id="dfKpiValor"><?php echo htmlspecialchars($df_kpi_valor, ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-clock fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Pendentes</span></div><span class="val" id="dfKpiPendentes"><?php echo (int) $df_kpi_pendentes; ?></span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-calendar-xmark fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Vencidas</span></div><span class="val" id="dfKpiVencidas"><?php echo (int) $df_kpi_vencidas; ?></span></div>
                </div>
                <p class="fornc-kpi-summary" id="dfKpiSummary">Indicadores da frota (atualizados ao filtrar).</p>

                <div class="fornc-toolbar">
                    <div class="fornc-search-block">
                        <label for="searchDespesa">Busca rápida</label>
                        <div class="fornc-search-inner">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input type="text" id="searchDespesa" placeholder="Buscar despesa..." autocomplete="off">
                        </div>
                    </div>
                    <div class="fornc-filters-inline">
                        <div class="fg">
                            <label for="vehicleFilter">Veículo</label>
                            <select id="vehicleFilter">
                            <option value="">Todos os veículos</option>
                        </select>
                        </div>
                        <div class="fg">
                            <label for="tipoFilter">Tipo</label>
                            <select id="tipoFilter">
                            <option value="">Todos os tipos</option>
                            <?php
                            $sql = "SELECT id, nome FROM tipos_despesa_fixa ORDER BY nome";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute();
                            while ($tipo = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='" . $tipo['id'] . "'>" . htmlspecialchars($tipo['nome']) . "</option>";
                            }
                            ?>
                        </select>
                        </div>
                        <div class="fg">
                            <label for="statusFilter">Status</label>
                            <select id="statusFilter">
                            <option value="">Todos os status</option>
                            <option value="1">Pendente</option>
                            <option value="2">Pago</option>
                            <option value="3">Vencido</option>
                            <option value="4">Cancelado</option>
                        </select>
                        </div>
                        <div class="fg">
                            <label for="paymentFilter">Forma pgto</label>
                            <select id="paymentFilter">
                            <option value="">Todas as formas de pagamento</option>
                        </select>
                        </div>
                        <div class="fg">
                            <label for="per_page_df">Por página</label>
                            <form method="get" action="" style="display:inline-flex; align-items:center; gap:0.35rem;" class="df-per-page-form">
                                <input type="hidden" name="page" value="1">
                                <select name="per_page" id="per_page_df" class="filter-per-page">
                                <option value="5"  <?php echo $per_page == 5  ? 'selected' : ''; ?>>5</option>
                                <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                            </form>
                        </div>
                    </div>
                    <div class="fornc-btn-row">
                        <button type="button" id="addDespesaBtn" class="fornc-btn fornc-btn--primary"><i class="fas fa-plus"></i> Nova despesa</button>
                        <button type="button" class="fornc-btn fornc-btn--accent" id="applyFixedExpenseFilters" title="Aplicar filtros"><i class="fas fa-search"></i> Pesquisar</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" data-sf-periodo data-sf-periodo-onapply="sfPeriodoClickApply" title="Filtrar por período"><i class="fas fa-calendar-alt"></i> Período</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="clearFixedExpenseFilters" title="Limpar filtros"><i class="fas fa-undo"></i></button>
                        <button type="button" class="fornc-btn fornc-btn--muted" id="exportBtn" title="Exportar"><i class="fas fa-file-export"></i> Exportar</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>
                <?php else: ?>
                <div class="dashboard-header">
                    <h1><?php echo $page_title; ?></h1>
                    <div class="dashboard-actions">
                        <button id="addDespesaBtn" class="btn-add-widget">
                            <i class="fas fa-plus"></i> Nova Despesa Fixa
                        </button>
                        <div class="view-controls">
                            <button id="filterBtn" class="btn-restore-layout" title="Filtros">
                                <i class="fas fa-filter"></i>
                            </button>
                            <button id="exportBtn" class="btn-toggle-layout" title="Exportar">
                                <i class="fas fa-file-export"></i>
                            </button>
                            <button id="helpBtn" class="btn-help" title="Ajuda">
                                <i class="fas fa-question-circle"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- KPI Cards Row -->
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <i class="fas fa-list-check kpi-card-icon" aria-hidden="true"></i>
                            <h3>Total de Despesas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="dfKpiQtd"><?php echo (int) $df_kpi_qtd; ?></span>
                                <span class="metric-subtitle">Total este mês</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <i class="fas fa-sack-dollar kpi-card-icon" aria-hidden="true"></i>
                            <h3>Valor Total</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="dfKpiValor"><?php echo htmlspecialchars($df_kpi_valor, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="metric-subtitle">Total este mês</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <i class="fas fa-hourglass-half kpi-card-icon" aria-hidden="true"></i>
                            <h3>Pendentes</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="dfKpiPendentes"><?php echo (int) $df_kpi_pendentes; ?></span>
                                <span class="metric-subtitle">Despesas pendentes</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <i class="fas fa-calendar-xmark kpi-card-icon" aria-hidden="true"></i>
                            <h3>Vencidas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="dfKpiVencidas"><?php echo (int) $df_kpi_vencidas; ?></span>
                                <span class="metric-subtitle">Despesas vencidas</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Search and Filter -->
                <div class="filter-section">
                    <div class="search-box">
                        <input type="text" id="searchDespesa" placeholder="Buscar despesa...">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="filter-options">
                        <form method="get" action="" style="display:inline-flex; align-items:center; gap:0.5rem;" class="df-per-page-form">
                            <span class="filter-label">Por página</span>
                            <input type="hidden" name="page" value="1">
                            <input type="hidden" name="classic" value="1">
                            <select name="per_page" class="filter-per-page">
                                <option value="5"  <?php echo $per_page == 5  ? 'selected' : ''; ?>>5</option>
                                <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </form>
                        <select id="vehicleFilter">
                            <option value="">Todos os veículos</option>
                            <!-- Será preenchido via JavaScript -->
                        </select>
                        
                        <select id="tipoFilter">
                            <option value="">Todos os tipos</option>
                            <?php
                            $sql = "SELECT id, nome FROM tipos_despesa_fixa ORDER BY nome";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute();
                            while ($tipo = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='" . $tipo['id'] . "'>" . htmlspecialchars($tipo['nome']) . "</option>";
                            }
                            ?>
                        </select>
                        
                        <select id="statusFilter">
                            <option value="">Todos os status</option>
                            <option value="1">Pendente</option>
                            <option value="2">Pago</option>
                            <option value="3">Vencido</option>
                            <option value="4">Cancelado</option>
                        </select>
                        
                        <select id="paymentFilter">
                            <option value="">Todas as formas de pagamento</option>
                            <!-- Será preenchido via JavaScript -->
                        </select>
                        <button type="button" class="btn-restore-layout" id="applyFixedExpenseFilters" title="Aplicar filtros">
                            <i class="fas fa-filter"></i>
                        </button>
                        <button type="button" class="btn-restore-layout" id="clearFixedExpenseFilters" title="Limpar filtros">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($is_modern)): ?>
                <div class="fornc-empty-state" id="dfEmptyState" role="status" aria-live="polite">
                    <p class="fornc-empty-state-title" id="dfEmptyTitle">Nenhuma despesa fixa cadastrada</p>
                    <p class="fornc-empty-state-hint" id="dfEmptyHint">Cadastre a primeira despesa recorrente da frota.</p>
                    <button type="button" class="fornc-btn fornc-btn--primary" id="dfEmptyCreateBtn"><i class="fas fa-plus"></i> Cadastrar primeira despesa</button>
                </div>
                <?php endif; ?>
                
                <!-- Despesas Table -->
                <div class="<?php echo $is_modern ? 'fornc-table-wrap' : 'data-table-container'; ?>" id="dfTableWrap">
                    <table class="<?php echo $is_modern ? 'fornc-table' : 'data-table'; ?>" id="despesasTable">
                        <thead>
                            <tr>
                                <?php if ($is_modern): ?>
                                <th class="sortable sorted" data-sort="vencimento">Vencimento <span class="sort-ind">▼</span></th>
                                <th class="sortable" data-sort="veiculo_placa">Veículo <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="tipo_nome">Tipo <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="descricao">Descrição <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="valor">Valor <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="status_nome">Status <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="data_pagamento">Data Pagamento <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="forma_pagamento_nome">Forma Pgto <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="repetir">Repetir <span class="sort-ind">⇅</span></th>
                                <?php else: ?>
                                <th>Vencimento</th>
                                <th>Veículo</th>
                                <th>Tipo</th>
                                <th>Descrição</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Data Pagamento</th>
                                <th>Forma Pgto</th>
                                <th>Repetir</th>
                                <?php endif; ?>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($despesas)): ?>
                            <tr><td colspan="10" class="text-center">Nenhuma despesa fixa encontrada</td></tr>
                            <?php else: foreach ($despesas as $d): echo despesas_fixas_render_table_row($d); endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginação -->
                <?php if ($is_modern): ?><div class="fornc-pagination-bar"><?php endif; ?>
                <div class="pagination<?php echo $is_modern ? ' fornc-modern-pagination' : ''; ?>">
                    <a href="?page=<?php echo max(1, $pagina_atual - 1); ?>&per_page=<?php echo (int) $per_page; ?>" class="pagination-btn<?php echo ($pagina_atual <= 1) ? ' disabled' : ''; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <span class="pagination-info">Página <?php echo (int) $pagina_atual; ?> de <?php echo (int) $total_paginas; ?> (<?php echo (int) $df_total_registros; ?> registros)</span>
                    <a href="?page=<?php echo min($total_paginas, $pagina_atual + 1); ?>&per_page=<?php echo (int) $per_page; ?>" class="pagination-btn<?php echo ($pagina_atual >= $total_paginas) ? ' disabled' : ''; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                <?php if ($is_modern): ?></div><?php endif; ?>

                <!-- Analytics Section -->
                <div class="analytics-section">
                    <div class="section-header">
                        <h2>Análise de Despesas</h2>
                    </div>
                    
                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Custos por Tipo de Despesa</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="despesasTipoChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Status das Despesas</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="statusDespesasChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Top 5 Veículos - Custos</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="topVeiculosChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Formas de Pagamento</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="formasPagamentoChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Despesa Modal -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="despesaModal">
        <div class="modal-content<?php echo $is_modern ? ' modal-lg fornc-modal--wide' : ''; ?>">
            <div class="modal-header">
                <h2 id="modalTitle">Nova Despesa Fixa</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="despesaForm" enctype="multipart/form-data">
                    <input type="hidden" id="despesaId">
                    <input type="hidden" id="empresaId" value="<?php echo $_SESSION['empresa_id']; ?>">
                    
                    <div class="form-section">
                        <h3>Informações Básicas</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="veiculo_id">Veículo*</label>
                                <select id="veiculo_id" name="veiculo_id" required>
                                    <option value="">Selecione um veículo</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="tipo_despesa_id">Tipo de Despesa*</label>
                                <select id="tipo_despesa_id" name="tipo_despesa_id" required>
                                    <option value="">Selecione o tipo</option>
                                    <?php
                                    $sql = "SELECT id, nome FROM tipos_despesa_fixa ORDER BY nome";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute();
                                    while ($tipo = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='" . $tipo['id'] . "'>" . htmlspecialchars($tipo['nome']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="valor">Valor*</label>
                                <input type="number" id="valor" name="valor" step="0.01" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="vencimento">Vencimento*</label>
                                <input type="date" id="vencimento" name="vencimento" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="status_pagamento_id">Status*</label>
                                <select id="status_pagamento_id" name="status_pagamento_id" required>
                                    <option value="">Selecione o status</option>
                                    <option value="1">Pendente</option>
                                    <option value="2">Pago</option>
                                    <option value="3">Vencido</option>
                                    <option value="4">Cancelado</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="forma_pagamento_id">Forma de Pagamento*</label>
                                <select id="forma_pagamento_id" name="forma_pagamento_id" required>
                                    <option value="">Selecione a forma de pagamento</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Detalhes do Pagamento</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="data_pagamento">Data do Pagamento</label>
                                <input type="date" id="data_pagamento" name="data_pagamento">
                            </div>
                            
                            <div class="form-group">
                                <label for="comprovante">Comprovante</label>
                                <input type="file" id="comprovante" name="comprovante" accept=".pdf,.jpg,.jpeg,.png">
                            </div>
                            
                            <div class="form-group">
                                <label for="repetir_automaticamente">Repetir Automaticamente</label>
                                <select id="repetir_automaticamente" name="repetir_automaticamente">
                                    <option value="0">Não</option>
                                    <option value="1">Sim</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="notificar_vencimento">Notificar Vencimento</label>
                                <select id="notificar_vencimento" name="notificar_vencimento">
                                    <option value="0">Não</option>
                                    <option value="1">Sim</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Informações Adicionais</h3>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="descricao">Descrição</label>
                                <textarea id="descricao" name="descricao" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelDespesaBtn">
                    <i class="fas fa-times"></i>
                    <span>Cancelar</span>
                </button>
                <button type="button" class="btn-primary" id="saveDespesaBtn">
                    <i class="fas fa-save"></i>
                    <span>Salvar</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Filter Modal -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="filterModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Filtros</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="filterMonth">Mês/Ano</label>
                        <input type="month" id="filterMonth" class="form-control" data-sf-periodo-month>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="clearFilterBtn" class="btn-secondary">Limpar</button>
                <button id="applyFilterBtn" class="btn-primary">Aplicar</button>
            </div>
        </div>
    </div>
    <?php $sf_help_key = 'despesas_fixas'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>


    <?php include '../includes/scroll_to_top.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html> 