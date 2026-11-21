
<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once __DIR__ . '/../includes/sf_page_help.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Check authentication
require_authentication();
require_once '../includes/permissions.php';
require_permission('access_tire_management');

require_once '../includes/db_connect.php';
require_once '../includes/pneus_list.php';
require_once '../includes/pneus_repository.php';

$conn = getConnection();
$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
$pn_per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;
if (!in_array($pn_per_page, [5, 10, 25, 50, 100], true)) {
    $pn_per_page = 10;
}
$pn_page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$pn_status = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
$pn_veiculo = isset($_GET['veiculo']) ? trim((string) $_GET['veiculo']) : '';
$pn_search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';

$pn_initial = ['success' => true, 'tires' => [], 'pagination' => ['total' => 0, 'current_page' => 1, 'per_page' => $pn_per_page, 'total_pages' => 1], 'metrics' => []];
if ($empresa_id > 0) {
    try {
        $pn_initial = pneus_fetch_list($conn, $empresa_id, [
            'page' => $pn_page,
            'per_page' => $pn_per_page,
            'status' => $pn_status,
            'veiculo' => $pn_veiculo,
            'search' => $pn_search,
        ]);
        $pn_initial['metrics'] = pneus_fetch_page_metrics($conn, $empresa_id);
        $pn_initial['success'] = true;
    } catch (Throwable $e) {
        error_log('pneus SSR: ' . $e->getMessage());
    }
}
$pn_metrics = $pn_initial['metrics'] ?? [];
$pn_rows = $pn_initial['tires'] ?? [];
$pn_pag = $pn_initial['pagination'] ?? ['total' => 0, 'current_page' => 1, 'per_page' => $pn_per_page, 'total_pages' => 1];
$pn_cur_page = (int) ($pn_pag['current_page'] ?? 1);
$pn_total_pages = max(1, (int) ($pn_pag['total_pages'] ?? 1));
$pn_total = (int) ($pn_pag['total'] ?? 0);

// Set page title
$page_title = "Pneus";
$is_modern = !isset($_GET['classic']) || (string) $_GET['classic'] !== '1';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <?php if ($is_modern): ?>
    <link rel="stylesheet" href="../css/fornc-modern-page.css">
    <?php endif; ?>
    <link rel="stylesheet" href="../css/pneus_timeline.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../logo.png">
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
    <script src="../js/sf-charts-premium.js"></script>

    <style>
        /* Estilos para paginação */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
            gap: 15px;
        }

        .pagination-btn {
            padding: 8px 16px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: var(--bg-secondary);
            color: var(--text-color);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .pagination-btn:hover:not(.disabled) {
            background: var(--bg-tertiary);
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .pagination-info {
            font-size: 0.9rem;
            color: var(--text-color);
        }

        /* Modal Detalhes do Pneu */
        .tire-details-loading { padding: 1.5rem; text-align: center; color: var(--text-secondary); }
        .tire-details-content { padding: 0.5rem 0; }
        .detail-section { margin-bottom: 1.5rem; }
        .detail-section h3 { font-size: 0.95rem; margin-bottom: 0.75rem; color: var(--text-color); display: flex; align-items: center; gap: 0.5rem; }
        .detail-section h3 i { color: var(--primary-color); }
        .detail-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.75rem 1.5rem; }
        .detail-item { display: flex; flex-direction: column; gap: 0.25rem; }
        .detail-label { font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.02em; }
        .detail-value { font-size: 0.9rem; font-weight: 500; color: var(--text-color); }
        .detail-text { margin: 0; font-size: 0.9rem; color: var(--text-secondary); line-height: 1.5; }
        #tireDetailsModal .modal-footer { display: flex; justify-content: flex-end; gap: 0.5rem; }
        #tireDetailsModal .data-table { font-size: 0.85rem; margin-top: 0.5rem; }
        .tire-details-debug .detail-debug-box { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; margin-top: 8px; max-height: 280px; overflow: auto; }
        .tire-details-debug pre { margin: 0; font-size: 11px; white-space: pre-wrap; word-break: break-all; }

        body.pneus-modern .dashboard-content.fornc-page { overflow-x: auto; }
        body.pneus-modern .dashboard-header h1 { display: none; }
        body.pneus-modern .dashboard-grid { display: none; }
        body.pneus-modern .filter-section { display: none; }
    </style>
    <?php sf_page_help_css('..'); ?>
</head>
<body class="<?php echo $is_modern ? 'pneus-modern' : ''; ?>">
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include '../includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include '../includes/header.php'; ?>
            
            <!-- Page Content -->
            <div class="dashboard-content<?php echo $is_modern ? ' fornc-page' : ''; ?>">
                <?php if (!$is_modern): ?>
                <div class="dashboard-header">
                    <h1><?php echo $page_title; ?></h1>
                    <div class="dashboard-actions">
                        <button id="addTireBtn" class="btn-add-widget">
                            <i class="fas fa-plus"></i> Novo Pneu
                        </button>
                        <div class="view-controls">
                            <button id="filterBtn" class="btn-restore-layout" title="Filtros">
                                <i class="fas fa-filter"></i>
                            </button>
                            <button id="exportBtn" class="btn-toggle-layout" title="Exportar">
                                <i class="fas fa-file-export"></i>
                            </button>
                            <button id="helpBtn" class="btn-help" title="Ajuda" aria-label="Ajuda">
                                <i class="fas fa-question-circle"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($is_modern): ?>
                <div class="fornc-kpi-strip">
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-circle-dot fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Total</span></div><span class="val" id="pneusKpiTotal"><?php echo (int) ($pn_metrics['total_pneus'] ?? 0); ?></span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-truck-moving fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Em uso</span></div><span class="val" id="pneusKpiEmUso"><?php echo (int) ($pn_metrics['total_em_uso'] ?? 0); ?></span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-percent fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Vida útil média</span></div><span class="val" id="pneusKpiVidaMedia"><?php echo (int) ($pn_metrics['vida_media'] ?? 0); ?>%</span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-triangle-exclamation fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Em alerta</span></div><span class="val" id="pneusKpiAlerta"><?php echo (int) ($pn_metrics['pneus_alerta'] ?? 0); ?></span></div>
                </div>
                <div id="sfPneusInsights"></div>
                <div class="fornc-toolbar">
                    <div class="fornc-search-block">
                        <label for="searchTire">Busca rápida</label>
                        <div class="fornc-search-inner">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input type="text" id="searchTire" placeholder="Buscar pneu..." autocomplete="off">
                        </div>
                    </div>
                    <div class="fornc-filters-inline">
                        <div class="fg">
                            <label for="perPagePneus">Por página</label>
                            <select id="perPagePneus" class="filter-per-page" title="Registros por página">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label for="statusFilter">Status</label>
                            <select id="statusFilter">
                                <option value="">Todos os status</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label for="vehicleFilter">Veículo</label>
                            <select id="vehicleFilter">
                                <option value="">Todos os veículos</option>
                            </select>
                        </div>
                    </div>
                    <div class="fornc-btn-row">
                        <button type="button" id="addTireBtn" class="fornc-btn fornc-btn--primary" title="Novo Pneu">
                            <i class="fas fa-plus"></i> Novo Pneu
                        </button>
                        <button type="button" class="fornc-btn fornc-btn--accent" id="applyTireFilters" title="Aplicar filtros">
                            <i class="fas fa-search"></i> Pesquisar
                        </button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="clearTireFilters" title="Limpar filtros">
                            <i class="fas fa-undo"></i>
                        </button>
                        <button type="button" id="exportBtn" class="fornc-btn fornc-btn--muted" title="Exportar">
                            <i class="fas fa-file-export"></i> Exportar
                        </button>
                        <button type="button" id="helpBtn" class="fornc-btn fornc-btn--ghost fornc-btn--icon" title="Ajuda" aria-label="Ajuda">
                            <i class="fas fa-question-circle"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- KPI Cards Row -->
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <i class="fas fa-circle-dot kpi-card-icon" aria-hidden="true"></i>
                            <h3>Total de Pneus</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo (int) ($pn_metrics['total_pneus'] ?? 0); ?></span>
                                <span class="metric-subtitle">Total</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <i class="fas fa-truck kpi-card-icon" aria-hidden="true"></i>
                            <h3>Pneus em Uso</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo (int) ($pn_metrics['total_em_uso'] ?? 0); ?></span>
                                <span class="metric-subtitle">Em uso</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <i class="fas fa-battery-three-quarters kpi-card-icon" aria-hidden="true"></i>
                            <h3>Vida Útil</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo (int) ($pn_metrics['vida_media'] ?? 0); ?>%</span>
                                <span class="metric-subtitle">Vida útil média</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <i class="fas fa-bell kpi-card-icon" aria-hidden="true"></i>
                            <h3>Pneus em Alerta</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo (int) ($pn_metrics['pneus_alerta'] ?? 0); ?></span>
                                <span class="metric-subtitle">Em alerta</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Debug: status pneu_movimentacoes (visível com ?debug=1) -->
                <div class="detail-section" id="debugBannerMovimentacoes" style="display:none; margin-bottom:1rem; padding:0.75rem 1rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius:6px;">
                    <strong><i class="fas fa-bug"></i> Debug — pneu_movimentacoes:</strong> <span id="debugBannerText">verificando...</span>
                </div>
                
                <!-- Search and Filter -->
                <?php if (!$is_modern): ?>
                <div class="filter-section">
                    <div class="search-box">
                        <input type="text" id="searchTire" placeholder="Buscar pneu...">
                        <i class="fas fa-search"></i>
                    </div>
                    
                    <div class="filter-options">
                        <span class="filter-label">Por página</span>
                        <select id="perPagePneus" class="filter-per-page" title="Registros por página">
                            <option value="5">5</option>
                            <option value="10" selected>10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <select id="statusFilter">
                            <option value="">Todos os status</option>
                        </select>
                        
                        <select id="vehicleFilter">
                            <option value="">Todos os veículos</option>
                        </select>
                        
                        <button type="button" class="btn-restore-layout" id="applyTireFilters" title="Aplicar filtros">
                            <i class="fas fa-filter"></i>
                        </button>
                        <button type="button" class="btn-restore-layout" id="clearTireFilters" title="Limpar filtros">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Tires Table -->
                <div class="<?php echo $is_modern ? 'fornc-table-wrap' : 'data-table-container'; ?>">
                    <table class="<?php echo $is_modern ? 'fornc-table' : 'data-table'; ?>" id="tiresTable">
                        <thead>
                            <tr>
                                <th>Número de Série</th>
                                <th>Marca/Modelo</th>
                                <th>DOT</th>
                                <th>Veículo</th>
                                <th>Posição</th>
                                <th>Data Instalação</th>
                                <th>KM Instalação</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pn_rows)): ?>
                            <tr><td colspan="9" class="text-center text-muted">Nenhum pneu encontrado.</td></tr>
                            <?php else: foreach ($pn_rows as $t): echo pneus_render_table_row($t); endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($is_modern): ?>
                <div class="fornc-pagination-bar">
                    <div class="pagination fornc-modern-pagination">
                        <a href="?page=<?php echo max(1, $pn_cur_page - 1); ?>&per_page=<?php echo (int) $pn_per_page; ?>" class="pagination-btn<?php echo ($pn_cur_page <= 1) ? ' disabled' : ''; ?>" id="prevPage" title="Página anterior" aria-label="Página anterior">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <span class="pagination-info" id="currentPage"><?php echo $pn_total_pages > 1 ? 'Página ' . (int) $pn_cur_page . ' de ' . (int) $pn_total_pages . ' (' . (int) $pn_total . ' registros)' : (int) $pn_total . ' registros'; ?></span>
                        <a href="?page=<?php echo min($pn_total_pages, $pn_cur_page + 1); ?>&per_page=<?php echo (int) $pn_per_page; ?>" class="pagination-btn<?php echo ($pn_cur_page >= $pn_total_pages) ? ' disabled' : ''; ?>" id="nextPage" title="Próxima página" aria-label="Próxima página">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <div class="pagination">
                    <a href="?page=<?php echo max(1, $pn_cur_page - 1); ?>&per_page=<?php echo (int) $pn_per_page; ?>" class="pagination-btn<?php echo ($pn_cur_page <= 1) ? ' disabled' : ''; ?>" id="prevPage" title="Página anterior">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <span class="pagination-info" id="currentPage"><?php echo $pn_total_pages > 1 ? 'Página ' . (int) $pn_cur_page . ' de ' . (int) $pn_total_pages . ' (' . (int) $pn_total . ' registros)' : (int) $pn_total . ' registros'; ?></span>
                    <a href="?page=<?php echo min($pn_total_pages, $pn_cur_page + 1); ?>&per_page=<?php echo (int) $pn_per_page; ?>" class="pagination-btn<?php echo ($pn_cur_page >= $pn_total_pages) ? ' disabled' : ''; ?>" id="nextPage" title="Próxima página">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Footer -->
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <!-- Add/Edit Tire Modal -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="tireModal">
        <div class="modal-content<?php echo $is_modern ? ' modal-lg fornc-modal--wide' : ''; ?>">
            <div class="modal-header">
                <h2 id="modalTitle">Adicionar Pneu</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="tireForm">
                    <input type="hidden" id="id" name="id">
                    <input type="hidden" id="empresa_id" name="empresa_id">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="numero_serie">Número de Série*</label>
                            <input type="text" id="numero_serie" name="numero_serie" required maxlength="50">
                        </div>
                        
                        <div class="form-group">
                            <label for="marca">Marca*</label>
                            <input type="text" id="marca" name="marca" required maxlength="50">
                        </div>
                        
                        <div class="form-group">
                            <label for="modelo">Modelo*</label>
                            <input type="text" id="modelo" name="modelo" required maxlength="50">
                        </div>
                        
                        <div class="form-group">
                            <label for="dot">DOT*</label>
                            <input type="text" id="dot" name="dot" required maxlength="20">
                        </div>
                        
                        <div class="form-group">
                            <label for="km_instalacao">KM Instalação*</label>
                            <input type="number" step="0.01" id="km_instalacao" name="km_instalacao" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="data_instalacao">Data de Instalação*</label>
                            <input type="date" id="data_instalacao" name="data_instalacao" required>
                        </div>

                        <div class="form-group">
                            <label for="vida_util_km">Vida Útil (KM)*</label>
                            <input type="number" id="vida_util_km" name="vida_util_km" required>
                        </div>

                        <div class="form-group">
                            <label for="status_id">Status*</label>
                            <select id="status_id" name="status_id" required>
                                <option value="">Selecione o status</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="medida">Medida*</label>
                            <input type="text" id="medida" name="medida" required maxlength="20">
                        </div>
                        
                        <div class="form-group">
                            <label for="sulco_inicial">Sulco Inicial (mm)*</label>
                            <input type="number" step="0.01" id="sulco_inicial" name="sulco_inicial" required>
                        </div>

                        <div class="form-group">
                            <label for="valor_compra">Valor de compra (R$)</label>
                            <input type="number" step="0.01" id="valor_compra" name="valor_compra" min="0">
                        </div>

                        <div class="form-group">
                            <label for="nota_fiscal">Nota fiscal</label>
                            <input type="text" id="nota_fiscal" name="nota_fiscal" maxlength="60">
                        </div>

                        <div class="form-group">
                            <label for="garantia_ate">Garantia até</label>
                            <input type="date" id="garantia_ate" name="garantia_ate">
                        </div>

                        <div class="form-group">
                            <label for="carcaca_codigo">Código da carcaça</label>
                            <input type="text" id="carcaca_codigo" name="carcaca_codigo" maxlength="60" placeholder="Opcional — padrão: nº série">
                        </div>

                        <div class="form-group">
                            <label for="tipo_rodado_ideal">Posição ideal</label>
                            <select id="tipo_rodado_ideal" name="tipo_rodado_ideal">
                                <option value="qualquer">Qualquer</option>
                                <option value="direcional">Direcional</option>
                                <option value="tracao">Tração</option>
                                <option value="livre">Livre</option>
                                <option value="implemento">Implemento</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="filial_id">Filial / unidade</label>
                            <select id="filial_id" name="filial_id">
                                <option value="">— Matriz / não informado —</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="numero_recapagens">Número de Recapagens</label>
                            <input type="number" id="numero_recapagens" name="numero_recapagens" value="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="data_ultima_recapagem">Data Última Recapagem</label>
                            <input type="date" id="data_ultima_recapagem" name="data_ultima_recapagem">
                        </div>
                        
                        <div class="form-group">
                            <label for="lote">Lote</label>
                            <input type="text" id="lote" name="lote" maxlength="50">
                        </div>
                        
                        <div class="form-group">
                            <label for="data_entrada">Data de Entrada</label>
                            <input type="date" id="data_entrada" name="data_entrada">
                        </div>

                        <div class="form-group full-width">
                            <label for="observacoes">Observações</label>
                            <textarea id="observacoes" name="observacoes" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Salvar</button>
                        <button type="button" class="btn-secondary" onclick="closeAllModals()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Detalhes do Pneu (relatório completo) -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="tireDetailsModal">
        <div class="modal-content modal-lg<?php echo $is_modern ? ' fornc-modal--wide' : ''; ?>">
            <div class="modal-header">
                <h2 id="tireDetailsTitle">Detalhes do Pneu</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body" id="tireDetailsBody">
                <div class="tire-details-loading">Carregando...</div>
                <div class="tire-details-content" id="tireDetailsContent" style="display:none;">
                    <section class="detail-section">
                        <h3><i class="fas fa-info-circle"></i> Dados cadastrais</h3>
                        <div class="detail-grid" id="detailCadastro"></div>
                    </section>
                    <section class="detail-section">
                        <h3><i class="fas fa-car"></i> Localização atual</h3>
                        <div id="detailLocalizacao"></div>
                    </section>
                    <section class="detail-section">
                        <h3><i class="fas fa-tachometer-alt"></i> Uso e vida útil</h3>
                        <div class="detail-grid" id="detailUso"></div>
                    </section>
                    <section class="detail-section">
                        <h3><i class="fas fa-coins"></i> Custo por km</h3>
                        <div class="detail-grid" id="detailCustoKm"></div>
                    </section>
                    <section class="detail-section">
                        <h3><i class="fas fa-retweet"></i> Recapagens</h3>
                        <div class="detail-grid" id="detailRecapagens"></div>
                    </section>
                    <section class="detail-section">
                        <h3><i class="fas fa-wrench"></i> Histórico de manutenções</h3>
                        <div id="detailManutencoes"></div>
                    </section>
                    <section class="detail-section" id="sectionMovimentacoes">
                        <h3><i class="fas fa-history"></i> Histórico visual (timeline)</h3>
                        <div id="detailTimelineVis"></div>
                        <h3 style="margin-top:1rem"><i class="fas fa-list"></i> Movimentações (tabela)</h3>
                        <p class="detail-text" id="detailMovimentacoesMsg">Carregando...</p>
                        <div id="detailMovimentacoesTable"></div>
                    </section>
                    <section class="detail-section" id="sectionObservacoes">
                        <h3><i class="fas fa-sticky-note"></i> Observações</h3>
                        <p id="detailObservacoes" class="detail-text"></p>
                    </section>
                    <!-- Debug: exibir com ?debug=1 na URL -->
                    <section class="detail-section tire-details-debug" id="sectionTireDebug" style="display:none;">
                        <h3><i class="fas fa-bug"></i> Debug — dados brutos das APIs</h3>
                        <p class="detail-text">Adicione <code>?debug=1</code> na URL da página Pneus (ex.: pneus.php?debug=1) e abra os detalhes de um pneu para ver abaixo as respostas brutas de <strong>get_tires.php</strong> e <strong>pneu_manutencao_data.php</strong>.</p>
                        <div class="detail-debug-box">
                            <strong>get_tires.php?id=…</strong>
                            <pre id="debugTireJson"></pre>
                        </div>
                        <div class="detail-debug-box">
                            <strong>pneu_manutencao_data.php?action=list_by_pneu&pneu_id=…</strong>
                            <pre id="debugManutJson"></pre>
                        </div>
                        <div class="detail-debug-box">
                            <strong>pneu_movimentacoes_debug.php?action=status</strong>
                            <pre id="debugMovStatusJson"></pre>
                        </div>
                        <div class="detail-debug-box">
                            <strong>pneu_movimentacoes_debug.php?action=by_pneu&pneu_id=…</strong>
                            <pre id="debugMovPneuJson"></pre>
                        </div>
                    </section>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeAllModals()">Fechar</button>
                <button type="button" class="btn-secondary" id="btnFichaFromDetails"><i class="fas fa-id-card"></i> Ficha completa</button>
                <button type="button" class="btn-primary" id="btnEditFromDetails"><i class="fas fa-edit"></i> Editar pneu</button>
            </div>
        </div>
    </div>

    <!-- JavaScript Files -->
    <script>window.__PNEUS_INITIAL__=<?php echo json_encode($pn_initial, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
    <script>
        // Variáveis globais
        const EMPRESA_ID = '<?php echo $_SESSION['empresa_id']; ?>';
        let currentPage = 1;
        let totalPages = 1;
    </script>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initializePage();
            setupModals();
            setupTireDetailsModal();
            const hasInitial = !!(window.__PNEUS_INITIAL__ && window.__PNEUS_INITIAL__.success);
            if (hasInitial) {
                applyPneusInitialPayload(window.__PNEUS_INITIAL__, false);
            } else {
                loadTires(1);
                loadDashboardMetrics();
            }
            loadFilters();
            if (/[?&]debug=1/.test(window.location.search)) {
                document.getElementById('debugBannerMovimentacoes').style.display = 'block';
                fetch('../api/pneu_movimentacoes_debug.php?action=status').then(r => r.json()).then(d => {
                    const el = document.getElementById('debugBannerText');
                    if (d.table_exists) {
                        el.textContent = 'Tabela existe. Total de registros (sua empresa): ' + (d.total ?? 0);
                    } else {
                        el.textContent = 'Tabela não existe. Execute sql/create_pneu_movimentacoes.sql';
                    }
                }).catch(() => {
                    document.getElementById('debugBannerText').textContent = 'Erro ao verificar API.';
                });
            }
            const qs = new URLSearchParams(window.location.search);
            const pneuId = qs.get('pneu_id');
            if (pneuId) {
                const id = parseInt(pneuId, 10);
                if (qs.get('edit') === '1') {
                    showEditTireModal(id);
                } else {
                    showTireDetails(id);
                }
            }
        });
        
        function initializePage() {
            document.getElementById('addTireBtn').addEventListener('click', showAddTireModal);
            const applyFiltersBtn = document.getElementById('applyTireFilters');
            if (applyFiltersBtn) {
                applyFiltersBtn.addEventListener('click', applyFilters);
            }

            const clearFiltersBtn = document.getElementById('clearTireFilters');
            if (clearFiltersBtn) {
                clearFiltersBtn.addEventListener('click', () => {
                    const searchInput = document.getElementById('searchTire');
                    const statusSelect = document.getElementById('statusFilter');
                    const vehicleSelect = document.getElementById('vehicleFilter');
                    if (searchInput) searchInput.value = '';
                    if (statusSelect) statusSelect.value = '';
                    if (vehicleSelect) vehicleSelect.value = '';
                    loadTires(1);
                });
            }

            const perPagePneus = document.getElementById('perPagePneus');
            if (perPagePneus) {
                perPagePneus.addEventListener('change', () => loadTires(1));
            }

            const searchInput = document.getElementById('searchTire');
            if (searchInput) {
                searchInput.addEventListener('input', debounce(() => loadTires(1), 300));
            }
            document.getElementById('prevPage').addEventListener('click', function(e) {
                e.preventDefault();
                if (currentPage > 1) {
                    loadTires(currentPage - 1);
                }
            });
            document.getElementById('nextPage').addEventListener('click', function(e) {
                e.preventDefault();
                if (currentPage < totalPages) {
                    loadTires(currentPage + 1);
                }
            });
        }

        async function loadFilters() {
            try {
                // Carregar status para o filtro
                const statusResponse = await fetch('../includes/get_tire_data.php?type=status');
                const statusData = await statusResponse.json();
                if (statusData.success) {
                    const statusFilter = document.getElementById('statusFilter');
                    statusFilter.innerHTML = '<option value="">Todos os status</option>';
                    statusData.data.forEach(status => {
                        statusFilter.innerHTML += `<option value="${status.id}">${status.nome}</option>`;
                    });
                }

                // Carregar veículos para o filtro
                const veiculosResponse = await fetch('../includes/get_tire_data.php?type=veiculos');
                const veiculosData = await veiculosResponse.json();
                if (veiculosData.success) {
                    const veiculoFilter = document.getElementById('vehicleFilter');
                    veiculoFilter.innerHTML = '<option value="">Todos os veículos</option>';
                    veiculosData.data.forEach(veiculo => {
                        veiculoFilter.innerHTML += `<option value="${veiculo.id}">${veiculo.nome}</option>`;
                    });
                }
            } catch (error) {
                console.error('Erro ao carregar filtros:', error);
            }
        }

        function applyFilters() {
            loadTires(1); // Reinicia na primeira página ao aplicar filtros
        }

        function applyPneusInitialPayload(initial, refreshTable) {
            if (!initial || !initial.success) return;
            if (initial.pagination) {
                currentPage = initial.pagination.current_page || 1;
                totalPages = initial.pagination.total_pages || 1;
                updatePagination(initial.pagination);
            }
            if (initial.metrics) {
                updateDashboardMetrics(initial.metrics);
            }
            if (refreshTable !== false) {
                const tbody = document.querySelector('#tiresTable tbody');
                const alreadyRendered = tbody && tbody.querySelector('tr') && tbody.querySelector('td') && !tbody.querySelector('td[colspan="9"]');
                if (!alreadyRendered) {
                    updateTiresTable(initial.tires || []);
                }
            }
        }

        async function loadTires(page = 1) {
            try {
                const statusFilter = document.getElementById('statusFilter')?.value || '';
                const vehicleFilter = document.getElementById('vehicleFilter')?.value || '';
                const searchTerm = document.getElementById('searchTire')?.value || '';

                let url = `../includes/get_tires.php?page=${page}`;
                const perPageEl = document.getElementById('perPagePneus');
                const perPage = perPageEl ? Math.min(100, Math.max(5, parseInt(perPageEl.value, 10) || 10)) : 10;
                if ([5, 10, 25, 50, 100].indexOf(perPage) >= 0) {
                    url += `&per_page=${perPage}`;
                }
                if (statusFilter) url += `&status=${encodeURIComponent(statusFilter)}`;
                if (vehicleFilter) url += `&veiculo=${encodeURIComponent(vehicleFilter)}`;
                if (searchTerm) url += `&search=${encodeURIComponent(searchTerm)}`;

                const response = await fetch(url);
                const data = await response.json();
                if (data.success) {
                    if (data.metrics) {
                        updateDashboardMetrics(data.metrics);
                    }
                    updateTiresTable(data.data);
                    updatePagination(data.pagination);
                }
            } catch (error) {
                console.error('Erro ao carregar pneus:', error);
            }
        }

        function updateTiresTable(tires) {
            const tbody = document.querySelector('#tiresTable tbody');
            tbody.innerHTML = '';
            
            tires.forEach(tire => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${tire.numero_serie}</td>
                    <td>${tire.marca} ${tire.modelo}</td>
                    <td>${tire.dot}</td>
                    <td>${tire.veiculo_placa || '-'}</td>
                    <td>${tire.posicao_nome}</td>
                    <td>${formatDate(tire.data_instalacao)}</td>
                    <td>${formatNumber(tire.km_instalacao)}</td>
                    <td><span class="status-badge status-${tire.status_id}">${tire.status_nome}</span></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-action view" onclick="showTireDetails(${tire.id})" title="Detalhes"><i class="fas fa-eye"></i></button>
                            <button class="btn-action" onclick="PneuTimeline.openFichaModal(${tire.id})" title="Ficha completa"><i class="fas fa-id-card"></i></button>
                            <button class="btn-action edit" onclick="showEditTireModal(${tire.id})" title="Editar"><i class="fas fa-edit"></i></button>
                            <button class="btn-action delete" onclick="showDeleteConfirmation(${tire.id})"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('pt-BR');
        }

        function formatNumber(number) {
            if (!number) return '-';
            return Number(number).toLocaleString('pt-BR');
        }

        function updatePagination(pagination) {
            currentPage = pagination.current_page;
            totalPages = pagination.total_pages;
            const total = pagination.total != null ? pagination.total : 0;
            const prevBtn = document.getElementById('prevPage');
            const nextBtn = document.getElementById('nextPage');
            const currentPageSpan = document.getElementById('currentPage');
            currentPageSpan.textContent = totalPages > 1
                ? `Página ${currentPage} de ${totalPages} (${total} registros)`
                : `${total} registros`;
            prevBtn.classList.toggle('disabled', currentPage <= 1);
            nextBtn.classList.toggle('disabled', currentPage >= totalPages);
        }

        async function loadSelectData() {
            try {
                // Carregar status
                const statusResponse = await fetch('../includes/get_tire_data.php?type=status');
                const statusData = await statusResponse.json();
                if (statusData.success) {
                    const statusSelect = document.getElementById('status_id');
                    statusSelect.innerHTML = '<option value="">Selecione o status</option>';
                    statusData.data.forEach(status => {
                        statusSelect.innerHTML += `<option value="${status.id}">${status.nome}</option>`;
                    });
                }
                const filialRes = await fetch('../includes/get_tire_data.php?type=filiais');
                const filialData = await filialRes.json();
                if (filialData.success) {
                    const filialSelect = document.getElementById('filial_id');
                    if (filialSelect) {
                        const cur = filialSelect.value;
                        filialSelect.innerHTML = '<option value="">— Matriz / não informado —</option>';
                        filialData.data.forEach(function (f) {
                            filialSelect.innerHTML += `<option value="${f.id}">${f.nome}</option>`;
                        });
                        if (cur) filialSelect.value = cur;
                    }
                }
            } catch (error) {
                console.error('Erro ao carregar dados:', error);
                alert('Erro ao carregar dados dos selects. Por favor, recarregue a página.');
            }
        }

        function setupModals() {
            const closeButtons = document.querySelectorAll('.close-modal');
            closeButtons.forEach(button => {
                button.addEventListener('click', closeAllModals);
            });
            
            document.getElementById('tireForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                await saveTire();
            });
        }
        
        function showAddTireModal() {
            document.getElementById('tireForm').reset();
            document.getElementById('id').value = '';
            document.getElementById('empresa_id').value = EMPRESA_ID;
            document.getElementById('modalTitle').textContent = 'Adicionar Pneu';
            const tireModal = document.getElementById('tireModal');
            tireModal.style.display = 'flex';
            tireModal.classList.add('active');
            loadSelectData();
        }
        
        async function showEditTireModal(tireId) {
            try {
                const response = await fetch(`../includes/get_tires.php?id=${tireId}`);
                const data = await response.json();
                if (data.success && data.data) {
                    const tire = data.data;
                    
                    // Preencher o formulário
                    const form = document.getElementById('tireForm');
                    form.reset();
                    
                    // Primeiro carrega os selects
                    await loadSelectData();
                    
                    // Depois preenche todos os campos, exceto veiculo_id e posicao_id
                    Object.keys(tire).forEach(key => {
                        if (key === 'veiculo_id' || key === 'posicao_id') return;
                        const input = form.querySelector(`[name="${key}"]`);
                        if (input) {
                            input.value = tire[key];
                        }
                    });
                    
                    document.getElementById('modalTitle').textContent = 'Editar Pneu';
                    const tireModal = document.getElementById('tireModal');
                    tireModal.style.display = 'flex';
                    tireModal.classList.add('active');
                }
            } catch (error) {
                console.error('Erro ao carregar dados do pneu:', error);
                alert('Erro ao carregar dados do pneu. Por favor, tente novamente.');
            }
        }

        async function showTireDetails(tireId) {
            const modal = document.getElementById('tireDetailsModal');
            const content = document.getElementById('tireDetailsContent');
            const loading = document.querySelector('#tireDetailsBody .tire-details-loading');
            content.style.display = 'none';
            loading.style.display = 'block';
            loading.textContent = 'Carregando...';
            modal.style.display = 'flex';
            modal.classList.add('active');
            window._tireDetailsId = tireId;

            try {
                const [tireRes, manutRes, timelineRes] = await Promise.all([
                    fetch(`../includes/get_tires.php?id=${tireId}`),
                    fetch(`../api/pneu_manutencao_data.php?action=list_by_pneu&pneu_id=${tireId}`),
                    fetch(`../api/pneus.php?action=timeline&pneu_id=${tireId}`)
                ]);
                const tireData = await tireRes.json();
                const manutData = await manutRes.json();
                const timelineData = await timelineRes.json();
                const tire = tireData.success && tireData.data ? tireData.data : null;
                const manutencoes = manutData.success && manutData.data ? manutData.data : [];

                if (!tire) {
                    loading.textContent = 'Pneu não encontrado.';
                    return;
                }

                document.getElementById('tireDetailsTitle').textContent = 'Detalhes do Pneu — ' + (tire.numero_serie || 'S/N');

                // Dados cadastrais
                const cadastro = [
                    ['Número de série', tire.numero_serie],
                    ['Marca', tire.marca],
                    ['Modelo', tire.modelo],
                    ['Medida', tire.medida],
                    ['DOT', tire.dot],
                    ['Status', tire.status_nome],
                    ['Lote', tire.lote],
                    ['Data de entrada', formatDate(tire.data_entrada)]
                ];
                document.getElementById('detailCadastro').innerHTML = cadastro.map(([l, v]) => '<div class="detail-item"><span class="detail-label">' + l + '</span><span class="detail-value">' + (v || '-') + '</span></div>').join('');

                // Localização atual (veículo/posição já vêm do get_tires quando listagem; no get por id não vêm — podemos deixar do cache ou não)
                const loc = (tire.veiculo_placa || tire.posicao_nome) ? (tire.veiculo_placa + (tire.posicao_nome ? ' — ' + tire.posicao_nome : '')) : 'Não alocado';
                document.getElementById('detailLocalizacao').innerHTML = '<p class="detail-text">' + loc + '</p>';

                // Uso e vida útil
                const uso = [
                    ['KM na instalação', formatNumber(tire.km_instalacao)],
                    ['Data de instalação', formatDate(tire.data_instalacao)],
                    ['Vida útil (km)', formatNumber(tire.vida_util_km)],
                    ['Sulco inicial (mm)', tire.sulco_inicial != null ? tire.sulco_inicial : '-']
                ];
                document.getElementById('detailUso').innerHTML = uso.map(([l, v]) => '<div class="detail-item"><span class="detail-label">' + l + '</span><span class="detail-value">' + (v || '-') + '</span></div>').join('');

                const ck = timelineData.custo_km || {};
                const custoKmHtml = [
                    ['Custo total', ck.custo_total != null ? 'R$ ' + Number(ck.custo_total).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) : '-'],
                    ['KM rodado', formatNumber(ck.km_rodado)],
                    ['Custo/km', ck.custo_km != null ? ('R$ ' + Number(ck.custo_km).toFixed(4) + '/km') : '-'],
                    ['Sulco atual (mm)', ck.sulco_atual_mm != null ? ck.sulco_atual_mm : '-']
                ];
                document.getElementById('detailCustoKm').innerHTML = custoKmHtml.map(([l, v]) => '<div class="detail-item"><span class="detail-label">' + l + '</span><span class="detail-value">' + (v || '-') + '</span></div>').join('');

                // Recapagens
                const recap = [
                    ['Número de recapagens', tire.numero_recapagens != null ? tire.numero_recapagens : '0'],
                    ['Data da última recapagem', formatDate(tire.data_ultima_recapagem)]
                ];
                document.getElementById('detailRecapagens').innerHTML = recap.map(([l, v]) => '<div class="detail-item"><span class="detail-label">' + l + '</span><span class="detail-value">' + (v || '-') + '</span></div>').join('');

                // Histórico de manutenções
                let manutHtml = '';
                if (manutencoes.length === 0) {
                    manutHtml = '<p class="detail-text">Nenhuma manutenção registrada para este pneu.</p>';
                } else {
                    manutHtml = '<table class="data-table"><thead><tr><th>Data</th><th>Tipo</th><th>Veículo</th><th>KM</th><th>Custo</th><th>Obs.</th></tr></thead><tbody>' +
                        manutencoes.map(m => {
                            const data = m.data_manutencao ? formatDate(m.data_manutencao) : '-';
                            const custo = m.custo != null ? 'R$ ' + Number(m.custo).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) : '-';
                            return '<tr><td>' + data + '</td><td>' + (m.tipo_nome || '-') + '</td><td>' + (m.veiculo_placa || '-') + '</td><td>' + formatNumber(m.km_veiculo) + '</td><td>' + custo + '</td><td>' + (m.observacoes || '-') + '</td></tr>';
                        }).join('') + '</tbody></table>';
                }
                document.getElementById('detailManutencoes').innerHTML = manutHtml;

                // Histórico de movimentações (pneu_movimentacoes)
                const movimentacoes = (timelineData.success && timelineData.timeline) ? timelineData.timeline : [];
                const sectionMov = document.getElementById('sectionMovimentacoes');
                const msgMov = document.getElementById('detailMovimentacoesMsg');
                const tableMov = document.getElementById('detailMovimentacoesTable');
                if (!timelineData.success) {
                    msgMov.textContent = 'Não foi possível carregar o histórico de movimentações.';
                    msgMov.style.display = 'block';
                    tableMov.innerHTML = '';
                } else if (movimentacoes.length === 0) {
                    msgMov.textContent = 'Nenhuma movimentação registrada para este pneu. As movimentações são geradas ao cadastrar, alocar, remover ou registrar manutenção.';
                    msgMov.style.display = 'block';
                    tableMov.innerHTML = '';
                } else {
                    msgMov.style.display = 'none';
                    if (window.PneuTimeline) {
                        PneuTimeline.render(document.getElementById('detailTimelineVis'), movimentacoes);
                    }
                    const labels = { entrada_estoque: 'Entrada estoque', instalacao: 'Instalação', remocao: 'Remoção', deslocamento: 'Deslocamento', recapagem: 'Recapagem', manutencao: 'Manutenção', descarte: 'Descarte' };
                    tableMov.innerHTML = '<table class="data-table"><thead><tr><th>Tipo</th><th>Data</th><th>Veículo</th><th>KM odômetro</th><th>KM rodado</th><th>Sulco (mm)</th><th>Custo</th><th>Obs.</th></tr></thead><tbody>' +
                        movimentacoes.map(m => {
                            const tipo = labels[m.tipo] || m.tipo;
                            const data = m.data_movimentacao ? formatDate(m.data_movimentacao) : '-';
                            const custo = m.custo != null && m.custo > 0 ? 'R$ ' + Number(m.custo).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) : '-';
                            return '<tr><td>' + tipo + '</td><td>' + data + '</td><td>' + (m.veiculo_placa || '-') + '</td><td>' + formatNumber(m.km_odometro) + '</td><td>' + formatNumber(m.km_rodado) + '</td><td>' + (m.sulco_mm != null ? m.sulco_mm : '-') + '</td><td>' + custo + '</td><td>' + (m.observacoes || '-') + '</td></tr>';
                        }).join('') + '</tbody></table>';
                }
                sectionMov.style.display = 'block';

                const obs = tire.observacoes && tire.observacoes.trim() ? tire.observacoes.trim() : '';
                document.getElementById('detailObservacoes').textContent = obs || 'Nenhuma observação.';
                document.getElementById('sectionObservacoes').style.display = obs ? 'block' : 'none';

                // Debug: mostrar dados brutos se ?debug=1
                const showDebug = /[?&]debug=1/.test(window.location.search);
                const sectionDebug = document.getElementById('sectionTireDebug');
                if (sectionDebug) {
                    sectionDebug.style.display = showDebug ? 'block' : 'none';
                    if (showDebug) {
                        document.getElementById('debugTireJson').textContent = JSON.stringify(tireData, null, 2);
                        document.getElementById('debugManutJson').textContent = JSON.stringify(manutData, null, 2);
                        document.getElementById('debugMovPneuJson').textContent = JSON.stringify(timelineData, null, 2);
                        try {
                            const statusRes = await fetch('../api/pneu_movimentacoes_debug.php?action=status');
                            const statusData = await statusRes.json();
                            document.getElementById('debugMovStatusJson').textContent = JSON.stringify(statusData, null, 2);
                        } catch (e) {
                            document.getElementById('debugMovStatusJson').textContent = 'Erro: ' + (e.message || e);
                        }
                    }
                }

                loading.style.display = 'none';
                content.style.display = 'block';
            } catch (err) {
                console.error(err);
                loading.textContent = 'Erro ao carregar detalhes. Tente novamente.';
            }
        }

        function setupTireDetailsModal() {
            document.getElementById('btnFichaFromDetails').addEventListener('click', function() {
                const id = window._tireDetailsId;
                if (id && window.PneuTimeline) {
                    PneuTimeline.openFichaModal(id);
                }
            });
            document.getElementById('btnEditFromDetails').addEventListener('click', function() {
                const id = window._tireDetailsId;
                if (id) {
                    closeAllModals();
                    showEditTireModal(id);
                }
            });
        }

        function showDeleteConfirmation(tireId) {
            if (confirm('Tem certeza que deseja excluir este pneu?')) {
                deleteTire(tireId);
            }
        }

        function debounce(fn, delay = 200) {
            let timeout;
            return (...args) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => fn.apply(null, args), delay);
            };
        }

        function pneusCsrfHeaders(extra) {
            const h = Object.assign({}, extra || {});
            if (!h['Content-Type'] && !h['content-type']) {
                h['Content-Type'] = 'application/json';
            }
            if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__) {
                h['X-CSRF-Token'] = window.__SF_CSRF__;
            }
            return h;
        }

        async function deleteTire(tireId) {
            try {
                const response = await fetch('../includes/delete_tire.php', {
                    method: 'POST',
                    headers: pneusCsrfHeaders(),
                    body: JSON.stringify({ id: tireId })
                });
                const result = await response.json();
                
                if (result.success) {
                    loadTires(); // Recarrega a tabela
                    alert('Pneu excluído com sucesso!');
                } else {
                    throw new Error(result.error || 'Erro ao excluir pneu');
                }
            } catch (error) {
                console.error('Erro ao excluir pneu:', error);
                alert('Erro ao excluir pneu. Por favor, tente novamente.');
            }
        }
        
        function closeAllModals() {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.classList.remove('active');
                modal.style.display = ''; // Remove inline display para permitir reabrir pelo .active
            });
            // Reabilitar campos que podem ter sido desabilitados na visualização
            const form = document.getElementById('tireForm');
            if (form) {
                form.querySelectorAll('input, select, textarea').forEach(field => {
                    field.disabled = false;
                });
            }
        }
        
        async function saveTire() {
            try {
                const form = document.getElementById('tireForm');
                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());
                // Remover veiculo_id e posicao_id antes de enviar
                delete data.veiculo_id;
                delete data.posicao_id;
                
                const response = await fetch('../includes/save_tire.php', {
                    method: 'POST',
                    headers: pneusCsrfHeaders(),
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    closeAllModals();
                    loadTires(); // Recarrega a tabela
                    alert('Pneu salvo com sucesso!');
                } else {
                    throw new Error(result.error || 'Erro ao salvar pneu');
                }
            } catch (error) {
                console.error('Erro ao salvar pneu:', error);
                alert('Erro ao salvar pneu. Por favor, tente novamente.');
            }
        }

        async function loadDashboardMetrics() {
            try {
                const [metricsRes, summaryRes] = await Promise.all([
                    fetch('../includes/get_tire_metrics.php'),
                    fetch('../api/pneus.php?action=summary')
                ]);
                const data = await metricsRes.json();
                const summary = await summaryRes.json();
                if (data.success) {
                    const m = data.data;
                    if (summary.success && summary.data) {
                        m.total_pneus = summary.data.total_pneus ?? m.total_pneus;
                        m.total_em_uso = summary.data.em_uso ?? m.total_em_uso;
                        m.pneus_alerta = summary.data.alerta_sulco ?? m.pneus_alerta;
                    }
                    updateDashboardMetrics(m);
                }
            } catch (error) {
                console.error('Erro ao carregar métricas:', error);
            }
        }

        function updateDashboardMetrics(metrics) {
            // Cards clássicos (mantidos no DOM mesmo no modo moderno)
            const card1 = document.querySelector('.dashboard-card:nth-child(1) .metric-value');
            if (card1) {
                document.querySelector('.dashboard-card:nth-child(1) .metric-value').textContent = metrics.total_pneus;
                document.querySelector('.dashboard-card:nth-child(1) .metric-subtitle').textContent = 'Total';
            }
            const card2 = document.querySelector('.dashboard-card:nth-child(2) .metric-value');
            if (card2) {
                document.querySelector('.dashboard-card:nth-child(2) .metric-value').textContent = metrics.total_em_uso;
                document.querySelector('.dashboard-card:nth-child(2) .metric-subtitle').textContent = 'Em uso';
            }
            const card3 = document.querySelector('.dashboard-card:nth-child(3) .metric-value');
            if (card3) {
                document.querySelector('.dashboard-card:nth-child(3) .metric-value').textContent = metrics.vida_media + '%';
                document.querySelector('.dashboard-card:nth-child(3) .metric-subtitle').textContent = 'Vida útil média';
            }
            const card4 = document.querySelector('.dashboard-card:nth-child(4) .metric-value');
            if (card4) {
                document.querySelector('.dashboard-card:nth-child(4) .metric-value').textContent = metrics.pneus_alerta;
                document.querySelector('.dashboard-card:nth-child(4) .metric-subtitle').textContent = 'Em alerta';
            }

            // KPI strip moderno
            const kpiTotal = document.getElementById('pneusKpiTotal');
            if (kpiTotal) kpiTotal.textContent = metrics.total_pneus;
            const kpiUso = document.getElementById('pneusKpiEmUso');
            if (kpiUso) kpiUso.textContent = metrics.total_em_uso;
            const kpiVida = document.getElementById('pneusKpiVidaMedia');
            if (kpiVida) kpiVida.textContent = (metrics.vida_media ?? 0) + '%';
            const kpiAlerta = document.getElementById('pneusKpiAlerta');
            if (kpiAlerta) kpiAlerta.textContent = metrics.pneus_alerta;
        }

        // Função para configurar botão de ajuda — aberto por js/sf_page_help.js
        function setupHelpButton() {
            document.querySelectorAll('.close-modal').forEach(button => {
                button.addEventListener('click', function() {
                    const modal = this.closest('.modal');
                    if (modal) {
                        if (modal.id === 'helpModal') {
                            modal.classList.remove('active');
                            modal.style.display = 'none';
                        } else {
                            closeAllModals();
                        }
                    }
                });
            });

            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', function(event) {
                    if (event.target === this && this.id !== 'helpModal') {
                        closeAllModals();
                    }
                });
            });
        }

        // Função para fechar modal específico
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // Inicializar botão de ajuda quando o DOM estiver carregado
        document.addEventListener('DOMContentLoaded', function() {
            // Setup help button
            setupHelpButton();
        });
    </script>
    
    <!-- Modal de Ajuda -->
    <?php $sf_help_key = 'pneus'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <script src="../js/pneus_timeline.js?v=1.1"></script>
    <script src="../js/sf_insights.js?v=1.3"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.SFInsights && document.getElementById('sfPneusInsights')) {
            SFInsights.init({
                mountId: 'sfPneusInsights',
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
                        { label: 'alerta sulco', value: s.alerta_sulco || 0, tone: (s.alerta_sulco || 0) > 0 ? 'erro' : 'ok' }
                    ];
                }
            });
        }
    });
    </script>
    <?php sf_page_help_js('..'); ?>
</body>
</html> 