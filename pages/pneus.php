<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Check authentication
require_authentication();

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
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../logo.png">
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
                    <div class="fornc-kpi-cell"><span class="lbl">Total</span><span class="val" id="pneusKpiTotal">0</span></div>
                    <div class="fornc-kpi-cell"><span class="lbl">Em uso</span><span class="val" id="pneusKpiEmUso">0</span></div>
                    <div class="fornc-kpi-cell"><span class="lbl">Vida útil média</span><span class="val" id="pneusKpiVidaMedia">0%</span></div>
                    <div class="fornc-kpi-cell"><span class="lbl">Em alerta</span><span class="val" id="pneusKpiAlerta">0</span></div>
                </div>
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
                            <h3>Total de Pneus</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">0</span>
                                <span class="metric-subtitle">Total</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Pneus em Uso</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">0</span>
                                <span class="metric-subtitle">Em uso</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Vida Útil</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">0%</span>
                                <span class="metric-subtitle">Vida útil média</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Pneus em Alerta</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">0</span>
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
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($is_modern): ?>
                <div class="fornc-pagination-bar">
                    <div class="pagination fornc-modern-pagination">
                        <a href="#" class="pagination-btn disabled" id="prevPage" title="Página anterior" aria-label="Página anterior">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <span class="pagination-info" id="currentPage">0 registros</span>
                        <a href="#" class="pagination-btn disabled" id="nextPage" title="Próxima página" aria-label="Próxima página">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <div class="pagination">
                    <a href="#" class="pagination-btn disabled" id="prevPage" title="Página anterior">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <span class="pagination-info" id="currentPage">0 registros</span>
                    <a href="#" class="pagination-btn disabled" id="nextPage" title="Próxima página">
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
                        <h3><i class="fas fa-retweet"></i> Recapagens</h3>
                        <div class="detail-grid" id="detailRecapagens"></div>
                    </section>
                    <section class="detail-section">
                        <h3><i class="fas fa-wrench"></i> Histórico de manutenções</h3>
                        <div id="detailManutencoes"></div>
                    </section>
                    <section class="detail-section" id="sectionMovimentacoes">
                        <h3><i class="fas fa-history"></i> Histórico de movimentações</h3>
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
                <button type="button" class="btn-primary" id="btnEditFromDetails"><i class="fas fa-edit"></i> Editar pneu</button>
            </div>
        </div>
    </div>

    <!-- JavaScript Files -->
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
            loadTires(1); // Carrega a primeira página
            loadFilters();
            loadDashboardMetrics(); // Carrega as métricas do dashboard
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
                            <button class="btn-action view" onclick="showTireDetails(${tire.id})"><i class="fas fa-eye"></i></button>
                            <button class="btn-action edit" onclick="showEditTireModal(${tire.id})"><i class="fas fa-edit"></i></button>
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
                const [tireRes, manutRes, movRes] = await Promise.all([
                    fetch(`../includes/get_tires.php?id=${tireId}`),
                    fetch(`../api/pneu_manutencao_data.php?action=list_by_pneu&pneu_id=${tireId}`),
                    fetch(`../api/pneu_movimentacoes_debug.php?action=by_pneu&pneu_id=${tireId}`)
                ]);
                const tireData = await tireRes.json();
                const manutData = await manutRes.json();
                const movData = await movRes.json();
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
                const sectionMov = document.getElementById('sectionMovimentacoes');
                const msgMov = document.getElementById('detailMovimentacoesMsg');
                const tableMov = document.getElementById('detailMovimentacoesTable');
                if (movData.table_exists === false) {
                    msgMov.textContent = 'Tabela de movimentações não criada. Execute sql/create_pneu_movimentacoes.sql para ativar o histórico único.';
                    msgMov.style.display = 'block';
                    tableMov.innerHTML = '';
                } else if (!movData.movimentacoes || movData.movimentacoes.length === 0) {
                    msgMov.textContent = 'Nenhuma movimentação registrada para este pneu. As movimentações são geradas ao cadastrar, alocar, remover ou registrar manutenção.';
                    msgMov.style.display = 'block';
                    tableMov.innerHTML = '';
                } else {
                    msgMov.style.display = 'none';
                    const labels = { entrada_estoque: 'Entrada estoque', instalacao: 'Instalação', remocao: 'Remoção', deslocamento: 'Deslocamento', recapagem: 'Recapagem', manutencao: 'Manutenção', descarte: 'Descarte' };
                    tableMov.innerHTML = '<table class="data-table"><thead><tr><th>Tipo</th><th>Data</th><th>Veículo</th><th>KM odômetro</th><th>KM rodado</th><th>Sulco (mm)</th><th>Custo</th><th>Obs.</th></tr></thead><tbody>' +
                        movData.movimentacoes.map(m => {
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
                        document.getElementById('debugMovPneuJson').textContent = JSON.stringify(movData, null, 2);
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

        async function deleteTire(tireId) {
            try {
                const response = await fetch(`../includes/delete_tire.php?id=${tireId}`, {
                    method: 'DELETE'
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
                    headers: {
                        'Content-Type': 'application/json'
                    },
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
                const response = await fetch('../includes/get_tire_metrics.php');
                const data = await response.json();
                if (data.success) {
                    updateDashboardMetrics(data.data);
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

        // Função para configurar botão de ajuda
        function setupHelpButton() {
            const helpBtn = document.getElementById('helpBtn');
            if (helpBtn) {
                helpBtn.addEventListener('click', function() {
                    const helpModal = document.getElementById('helpPneusModal');
                    if (helpModal) {
                        helpModal.style.display = 'block';
                    }
                });
            }

            // Close modal: tire modal usa closeAllModals para reabilitar campos e permitir reabrir; help modal só esconde
            document.querySelectorAll('.close-modal').forEach(button => {
                button.addEventListener('click', function() {
                    const modal = this.closest('.modal');
                    if (modal) {
                        if (modal.id === 'helpPneusModal') {
                            modal.style.display = 'none';
                        } else {
                            closeAllModals();
                        }
                    }
                });
            });

            // Close modal when clicking outside (usar closeAllModals para reabilitar campos e permitir reabrir)
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', function(event) {
                    if (event.target === this) {
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
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="helpPneusModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ajuda - Gestão de Pneus</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="help-section">
                    <h3>Visão Geral</h3>
                    <p>A página de Pneus permite gerenciar todo o estoque de pneus da empresa. Aqui você pode cadastrar, editar, visualizar e excluir pneus, além de acompanhar métricas importantes de vida útil e status.</p>
                </div>

                <div class="help-section">
                    <h3>Funcionalidades Principais</h3>
                    <ul>
                        <li><strong>Novo Pneu:</strong> Cadastre um novo pneu com informações completas como marca, modelo, dimensões e DOT.</li>
                        <li><strong>Filtros:</strong> Use os filtros para encontrar pneus específicos por status, marca ou através da busca por texto.</li>
                        <li><strong>Relatórios:</strong> Visualize relatórios e estatísticas de vida útil dos pneus.</li>
                        <li><strong>Alocação:</strong> Gerencie a alocação de pneus para veículos específicos.</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Indicadores (KPIs)</h3>
                    <ul>
                        <li><strong>Total de Pneus:</strong> Número total de pneus no estoque.</li>
                        <li><strong>Pneus em Uso:</strong> Quantidade de pneus atualmente alocados em veículos.</li>
                        <li><strong>Vida Útil Média:</strong> Percentual médio de vida útil dos pneus.</li>
                        <li><strong>Pneus em Alerta:</strong> Quantidade de pneus que precisam de atenção.</li>
                        <li><strong>Distribuição por Status:</strong> Gráfico mostrando a distribuição por status dos pneus.</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Ações Disponíveis</h3>
                    <ul>
                        <li><strong>Visualizar:</strong> Veja detalhes completos do pneu, incluindo histórico de uso e especificações.</li>
                        <li><strong>Editar:</strong> Modifique informações de um pneu existente.</li>
                        <li><strong>Excluir:</strong> Remova um pneu do sistema (ação irreversível).</li>
                        <li><strong>Alocar:</strong> Atribua pneus a veículos específicos.</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Dicas Úteis</h3>
                    <ul>
                        <li>Mantenha o DOT (Data de Fabricação) sempre atualizado para controle de vida útil.</li>
                        <li>Monitore a vida útil dos pneus para planejar substituições preventivas.</li>
                        <li>Configure alertas para pneus que estão próximos do fim da vida útil.</li>
                        <li>Utilize os filtros para encontrar pneus específicos rapidamente.</li>
                        <li>Acompanhe as métricas para otimizar o uso dos pneus.</li>
                        <li>Mantenha um registro detalhado da alocação de pneus por veículo.</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('helpPneusModal')">Fechar</button>
            </div>
        </div>
    </div>
</body>
</html> 