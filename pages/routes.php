<?php
// Include configuration and functions first (display_errors / error_reporting em config.php)
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/sf_api_base.php';

// Configure session before starting it
configure_session();

// Start session
session_start();

// Check authentication
require_authentication();

// Set page title
$page_title = "Rotas";

// Por página: 5, 10, 25, 50, 100 — padrão 10
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if (!in_array($per_page, [5, 10, 25, 50, 100], true)) {
    $per_page = 10;
}
// Remove ?modern= da URL (links antigos) — layout moderno é padrão
if (array_key_exists('modern', $_GET)) {
    $q = $_GET;
    unset($q['modern']);
    $qs = http_build_query($q);
    header('Location: routes.php' . ($qs !== '' ? '?' . $qs : ''), true, 301);
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    <?php sf_render_api_scripts(); ?>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <link rel="stylesheet" href="../css/fornc-modern-page.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../logo.png">
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Route Profitability Analysis -->
    <script src="../js/route-profitability.js"></script>
    <link rel="stylesheet" href="../css/routes.css?v=1.0.2">
</head>
<body class="routes-modern">
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include '../includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include '../includes/header.php'; ?>
            
            <!-- Page Content -->
            <div class="dashboard-content routes-modern-page">
                <div class="dashboard-header">
                    <h1>Rotas</h1>
                </div>
                
                <!-- KPI Cards Row -->
                <div class="dashboard-grid">
                    <div class="dashboard-card" data-kpi="total" title="Clique para ver todas as rotas do período">
                        <div class="card-header">
                            <i class="fas fa-route kpi-card-icon" aria-hidden="true"></i>
                            <h3>Total de Rotas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="totalRoutes">0</span>
                                <span class="metric-subtitle">Rotas cadastradas</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card" data-kpi="concluidas" title="Rotas concluídas no período">
                        <div class="card-header">
                            <i class="fas fa-circle-check kpi-card-icon" aria-hidden="true"></i>
                            <h3>Rotas Concluídas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="completedRoutes">0</span>
                                <span class="metric-subtitle">Neste mês</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card" data-kpi="distancia" title="Distância total no período">
                        <div class="card-header">
                            <i class="fas fa-road kpi-card-icon" aria-hidden="true"></i>
                            <h3>Distância Total</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="totalDistance">0 km</span>
                                <span class="metric-subtitle">Percorridos</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card" data-kpi="frete" title="Frete total no período">
                        <div class="card-header">
                            <i class="fas fa-sack-dollar kpi-card-icon" aria-hidden="true"></i>
                            <h3>Frete Total</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="totalFrete">R$ 0,00</span>
                                <span class="metric-subtitle">Em fretes</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Efficiency Metrics -->
                <div class="dashboard-grid">
                    <div class="dashboard-card" data-kpi="no_prazo" title="Filtrar entregas no prazo">
                        <div class="card-header">
                            <i class="fas fa-calendar-check kpi-card-icon" aria-hidden="true"></i>
                            <h3>Rotas no Prazo</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="rotasNoPrazo">0</span>
                                <span class="metric-subtitle">Entregas no prazo</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card" data-kpi="atrasadas" title="Filtrar entregas atrasadas">
                        <div class="card-header">
                            <i class="fas fa-triangle-exclamation kpi-card-icon" aria-hidden="true"></i>
                            <h3>Rotas Atrasadas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="rotasAtrasadas">0</span>
                                <span class="metric-subtitle">Entregas atrasadas</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card" data-kpi="eficiencia" title="Eficiência média no período">
                        <div class="card-header">
                            <i class="fas fa-chart-line kpi-card-icon" aria-hidden="true"></i>
                            <h3>Eficiência Média</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="mediaEficiencia">0%</span>
                                <span class="metric-subtitle">Taxa de eficiência</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card" data-kpi="km_vazio" title="Média de quilometragem vazia">
                        <div class="card-header">
                            <i class="fas fa-box-open kpi-card-icon" aria-hidden="true"></i>
                            <h3>Média KM Vazio</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="percentualVazio">0%</span>
                                <span class="metric-subtitle">Quilometragem sem carga</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Active Routes Section -->
                <div class="section-container" id="activeRoutesSection">
                    <div class="section-header">
                        <h2>Rotas Ativas</h2>
                    </div>
                    
                    <div class="active-routes-container" id="activeRoutesContainer">
                        <!-- Será preenchido via JavaScript -->
                    </div>
                </div>
                
                <!-- Search and Filter -->
                <div class="fornc-toolbar">
                    <div class="fornc-search-block">
                        <label for="searchRoute">Busca rápida</label>
                        <div class="fornc-search-inner">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchRoute" placeholder="Motorista, placa, cidades da rota...">
                        </div>
                    </div>
                    <div class="fornc-filters-inline">
                        <div class="fg">
                            <label for="statusFilter">Entrega</label>
                            <select id="statusFilter" title="Filtrar por prazo de entrega">
                                <option value="">Todas</option>
                                <option value="no_prazo">No prazo</option>
                                <option value="atrasado">Atrasado</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label for="driverFilter">Motorista</label>
                            <select id="driverFilter">
                                <option value="">Todos</option>
                                <!-- Será preenchido via JavaScript -->
                            </select>
                        </div>
                        <div class="fg">
                            <label for="perPageRoutes">Por página</label>
                            <form method="get" action="" id="formPerPageRoutes">
                                <input type="hidden" name="page" value="1">
                                <select id="perPageRoutes" name="per_page" class="filter-per-page" title="Registros por página">
                                    <option value="5"  <?php echo $per_page == 5  ? 'selected' : ''; ?>>5</option>
                                    <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                                    <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                                    <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                                </select>
                            </form>
                        </div>
                        <select id="vehicleFilter" style="display:none;">
                            <option value="">Todos os veículos</option>
                        </select>
                    </div>
                    <div class="fornc-btn-row">
                            <button id="addRouteBtn" class="fornc-btn fornc-btn--primary"><i class="fas fa-plus"></i> Novo</button>
                            <button id="importNfeXmlBtn" type="button" class="fornc-btn fornc-btn--accent" title="Importar XML da NF-e e criar rota"><i class="fas fa-file-import"></i> Importar NF-e</button>
                            <button id="applyRouteFilters" type="button" class="fornc-btn fornc-btn--accent"><i class="fas fa-search"></i> Pesquisar</button>
                            <button id="filterBtn" type="button" class="fornc-btn fornc-btn--ghost"><i class="fas fa-sliders-h"></i> Opções</button>
                            <button id="columnsBtn" type="button" class="fornc-btn fornc-btn--ghost" title="Colunas da tabela"><i class="fas fa-columns"></i></button>
                            <button id="exportBtn" type="button" class="fornc-btn fornc-btn--muted"><i class="fas fa-file-export"></i> Exportar</button>
                            <button id="simulateRouteBtn" type="button" class="fornc-btn fornc-btn--ghost"><i class="fas fa-route"></i> Simular Rota</button>
                            <button id="helpBtn" type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" title="Ajuda"><i class="fas fa-question-circle"></i></button>
                            <button type="button" class="fornc-btn fornc-btn--ghost" id="clearRouteFilters" title="Limpar filtros"><i class="fas fa-undo"></i></button>
                    </div>
                </div>
                
                <!-- Route Table -->
                <div class="routes-batch-bar" id="routesBatchBar">
                    <span id="routesBatchCount">0 selecionada(s)</span>
                    <button type="button" class="fornc-btn fornc-btn--muted" id="routesExportSelectedBtn"><i class="fas fa-file-csv"></i> CSV selecionadas</button>
                    <button type="button" class="fornc-btn fornc-btn--ghost" id="routesClearSelectionBtn">Limpar seleção</button>
                </div>
                <div class="routes-empty-state" id="routesEmptyState">
                    <p style="margin:0 0 8px 0; font-weight:600; color:var(--text-primary);">Nenhuma rota encontrada</p>
                    <p style="margin:0 0 14px 0; font-size:0.85rem; color:var(--text-secondary);">Ajuste os filtros ou cadastre uma nova rota.</p>
                    <button type="button" class="fornc-btn fornc-btn--primary" id="routesEmptyCreateBtn"><i class="fas fa-plus"></i> Criar primeira rota</button>
                </div>
                <div class="table-container routes-table-wrap" id="routeTableContainer">
                    <div class="table-loading" id="routeTableLoading" style="display:none; padding: 2rem; text-align: center;">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Carregando rotas...</span>
                    </div>
                    <table class="data-table" id="routesDataTable">
                        <thead>
                            <tr>
                                <th class="col-sel" data-col="sel"><input type="checkbox" id="routesSelectAll" title="Selecionar página" aria-label="Selecionar todas desta página"></th>
                                <th class="sortable sorted" data-sort="data_rota" data-col="data">Data <span class="sort-ind">▼</span></th>
                                <th class="sortable" data-sort="motorista_nome" data-col="motorista">Motorista <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="veiculo_placa" data-col="veiculo">Veículo <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="rota" data-col="rota">Rota <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="distancia_km" data-col="dist">Distância <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="frete" data-col="frete">Frete <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="id" data-col="col_id">ID <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="data_saida" data-col="col_saida">Saída <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="data_chegada" data-col="col_chegada">Chegada <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="km_saida" data-col="col_km_saida">KM saída <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="km_chegada" data-col="col_km_chegada">KM chegada <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="km_vazio" data-col="col_km_vazio">KM vazio <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="total_km" data-col="col_total_km">Total KM <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="comissao" data-col="col_comissao">Comissão <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="eficiencia_viagem" data-col="col_eficiencia">Eficiência <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="percentual_vazio" data-col="col_pct_vazio">% vazio <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="peso_carga" data-col="col_peso">Peso (kg) <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="descricao_carga" data-col="col_desc_carga">Carga <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="no_prazo" data-col="status">Status <span class="sort-ind">⇅</span></th>
                                <th data-col="acoes">Ações</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="pagination" id="paginationRoutesContainer" data-per-page="<?php echo (int)$per_page; ?>"></div>
                
                <!-- Analytics Section -->
                <div class="analytics-section">
                    <div class="section-header">
                        <h2>Análise de Desempenho</h2>
                    </div>
                    
                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Distância Percorrida por Motorista</h3>
                                <span class="card-subtitle">Mês Atual</span>
                            </div>
                            <div class="card-body">
                                <canvas id="distanciaMotoristaChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Média de Eficiência por Motorista</h3>
                                <span class="card-subtitle">Percentual de Eficiência</span>
                            </div>
                            <div class="card-body">
                                <canvas id="eficienciaMotoristaChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Rotas Concluídas no Prazo</h3>
                                <span class="card-subtitle">Por Motorista</span>
                            </div>
                            <div class="card-body">
                                <canvas id="rotasPrazoChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Valor de Frete por Motorista</h3>
                                <span class="card-subtitle">Mês Atual</span>
                            </div>
                            <div class="card-body">
                                <canvas id="freteMotoristaChart"></canvas>
                            </div>
                        </div>

                        <div class="analytics-card half-width">
                            <div class="card-header">
                                <h3>Evolução de KM Rodados</h3>
                                <span class="card-subtitle">Últimos 6 Meses</span>
                            </div>
                            <div class="card-body">
                                <canvas id="evolucaoKmChart"></canvas>
                            </div>
                        </div>

                        <div class="analytics-card half-width">
                            <div class="card-header">
                                <h3>Indicadores por Motorista</h3>
                                <span class="card-subtitle">Análise Multidimensional</span>
                            </div>
                            <div class="card-body">
                                <canvas id="indicadoresMotoristaChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    
    <!-- Modal Importar XML NF-e -->
    <div class="modal" id="importNfeXmlModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Importar XML da NF-e</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <p class="text-muted">Selecione o arquivo XML da NF-e. A rota será criada com origem (emitente), destino (destinatário), data, descrição da carga e, quando disponível nas informações complementares, motorista, veículo e km.</p>
                <form id="importNfeXmlForm">
                    <div class="form-group">
                        <label for="nfeXmlFile">Arquivo XML da NF-e</label>
                        <input type="file" id="nfeXmlFile" name="xml_file" accept=".xml,application/xml,text/xml" required>
                    </div>
                    <div id="importNfeXmlStatus" class="mt-2" style="display:none;"></div>
                    <div class="form-actions" style="margin-top:1rem;">
                        <button type="button" class="btn-secondary close-modal">Cancelar</button>
                        <button type="submit" id="importNfeXmlSubmit" class="btn-primary">
                            <i class="fas fa-file-import"></i> Importar e criar rota
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Route Modal -->
    <div class="modal" id="routeModal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h2 id="modalTitle">Adicionar Rota</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="routeForm">
                    <input type="hidden" id="routeId" name="id">

                    <div class="route-modal-tab-bar" role="tablist" aria-label="Seções do formulário">
                        <button type="button" class="route-tab-btn is-active" data-route-tab="1">Geral</button>
                        <button type="button" class="route-tab-btn" data-route-tab="2">Viagem</button>
                        <button type="button" class="route-tab-btn" data-route-tab="3">Financeiro</button>
                        <button type="button" class="route-tab-btn" data-route-tab="4">Carga</button>
                    </div>

                    <div class="route-modal-tab-pane is-active" data-route-tab="1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="data_rota">Data da Rota*</label>
                            <input type="date" id="data_rota" name="data_rota" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="motorista_id">Motorista*</label>
                            <select id="motorista_id" name="motorista_id" required>
                                <option value="">Selecione um motorista</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="veiculo_id">Veículo*</label>
                            <select id="veiculo_id" name="veiculo_id" required>
                                <option value="">Selecione um veículo</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Origem e Destino</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="estado_origem">Estado de Origem*</label>
                                <select id="estado_origem" name="estado_origem" required>
                                    <option value="">Selecione o estado</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="cidade_origem_id">Cidade de Origem*</label>
                                <select id="cidade_origem_id" name="cidade_origem_id" required>
                                    <option value="">Selecione primeiro o estado</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="estado_destino">Estado de Destino*</label>
                                <select id="estado_destino" name="estado_destino" required>
                                    <option value="">Selecione o estado</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="cidade_destino_id">Cidade de Destino*</label>
                                <select id="cidade_destino_id" name="cidade_destino_id" required>
                                    <option value="">Selecione primeiro o estado</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    </div>
                    
                    <div class="route-modal-tab-pane" data-route-tab="2">
                    <div class="form-section">
                        <h3>Dados da Viagem</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="data_saida">Data/Hora Saída*</label>
                                <input type="datetime-local" id="data_saida" name="data_saida" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="data_chegada">Data/Hora Chegada</label>
                                <input type="datetime-local" id="data_chegada" name="data_chegada">
                            </div>
                            
                            <div class="form-group">
                                <label for="km_saida" class="km-saida-label-row">
                                    <span>KM Saída</span>
                                    <span class="km-saida-meta">
                                        <span id="km_saida_help">Selecione veículo para valida KM</span>
                                    </span>
                                </label>
                                <input type="number" id="km_saida" name="km_saida" step="0.01" placeholder="Ex: 150000">
                                <div id="km_saida_validation" style="margin-top: 5px; font-size: 0.875rem;"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="km_chegada">KM Chegada</label>
                                <input type="number" id="km_chegada" name="km_chegada" step="0.01">
                            </div>
                            
                            <div class="form-group">
                                <label for="distancia_km">Distância (km)</label>
                                <input type="number" id="distancia_km" name="distancia_km" step="0.01">
                            </div>
                            
                            <div class="form-group">
                                <label for="km_vazio">KM Vazio</label>
                                <input type="number" id="km_vazio" name="km_vazio" step="0.01">
                            </div>
                            
                            <div class="form-group">
                                <label for="total_km">Total KM</label>
                                <input type="number" id="total_km" name="total_km" step="0.01">
                            </div>
                        </div>
                    </div>
                    </div>
                    
                    <div class="route-modal-tab-pane" data-route-tab="3">
                    <div class="form-section">
                        <h3>Dados Financeiros e Eficiência</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="frete">Valor do Frete (R$)</label>
                                <input type="number" id="frete" name="frete" step="0.01">
                            </div>
                            
                            <div class="form-group">
                                <label for="comissao">Comissão (R$)</label>
                                <input type="number" id="comissao" name="comissao" step="0.01">
                            </div>
                            
                            <div class="form-group">
                                <label for="percentual_vazio">Percentual Vazio (%)</label>
                                <input type="number" id="percentual_vazio" name="percentual_vazio" step="0.01">
                            </div>
                            
                            <div class="form-group">
                                <label for="eficiencia_viagem">Eficiência da Viagem (%)</label>
                                <input type="number" id="eficiencia_viagem" name="eficiencia_viagem" step="0.01">
                            </div>
                            
                            <div class="form-group">
                                <label for="no_prazo">Entrega no Prazo</label>
                                <select id="no_prazo" name="no_prazo">
                                    <option value="1">Sim</option>
                                    <option value="0">Não</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    </div>
                    
                    <div class="route-modal-tab-pane" data-route-tab="4">
                    <div class="form-section">
                        <h3>Dados da Carga</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="peso_carga">Peso da Carga (kg)</label>
                                <input type="number" id="peso_carga" name="peso_carga" step="0.01">
                            </div>
                            
                            <div class="form-group">
                                <label for="descricao_carga">Descrição da Carga</label>
                                <textarea id="descricao_carga" name="descricao_carga" rows="2"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="observacoes">Observações</label>
                                <textarea id="observacoes" name="observacoes" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button id="cancelRouteBtn" class="btn-secondary">Cancelar</button>
                <button id="saveRouteBtn" class="btn-primary">Salvar</button>
            </div>
        </div>
    </div>
    
    <!-- View Route Details Modal -->
    <div class="modal route-view-modal" id="viewRouteModal" data-route-id="">
        <div class="modal-content modal-lg route-view-modal__shell">
            <div class="modal-header route-view-modal__header">
                <div class="route-view-modal__header-text">
                    <h2 id="viewModalTitle" class="route-view-modal__title">Detalhes da Rota</h2>
                    <p id="routeOriginDestination" class="route-view-modal__route-line">—</p>
                </div>
                <span class="close-modal close-view-modal" aria-label="Fechar">&times;</span>
            </div>
            <div class="modal-body route-view-modal__body">
                <div class="details-container">
                    <div class="rd-summary">
                        <span id="routeStatus" class="rd-badge rd-badge--neutral">—</span>
                        <div class="rd-summary__date">
                            <span class="rd-kv-label">Data da rota</span>
                            <span id="routeDate" class="rd-summary__date-value">—</span>
                        </div>
                    </div>

                    <div class="rd-panels">
                        <article class="rd-panel rd-panel--accent">
                            <h3 class="rd-panel__title"><i class="fas fa-route" aria-hidden="true"></i> Viagem</h3>
                            <div class="rd-kv-grid">
                                <div class="rd-kv">
                                    <span class="rd-kv-label">Motorista</span>
                                    <span class="rd-kv-value" id="detailDriver">—</span>
                                </div>
                                <div class="rd-kv">
                                    <span class="rd-kv-label">Veículo</span>
                                    <span class="rd-kv-value" id="detailVehicle">—</span>
                                </div>
                                <div class="rd-kv">
                                    <span class="rd-kv-label">Distância</span>
                                    <span class="rd-kv-value" id="detailDistance">—</span>
                                </div>
                                <div class="rd-kv">
                                    <span class="rd-kv-label">Consumo</span>
                                    <span class="rd-kv-value" id="detailFuelConsumption">—</span>
                                </div>
                            </div>
                        </article>
                        <article class="rd-panel rd-panel--time">
                            <h3 class="rd-panel__title"><i class="fas fa-clock" aria-hidden="true"></i> Horários</h3>
                            <div class="rd-kv-grid">
                                <div class="rd-kv">
                                    <span class="rd-kv-label">Saída</span>
                                    <span class="rd-kv-value" id="detailStartTime">—</span>
                                </div>
                                <div class="rd-kv">
                                    <span class="rd-kv-label">Chegada</span>
                                    <span class="rd-kv-value" id="detailEndTime">—</span>
                                </div>
                                <div class="rd-kv rd-kv--span rd-kv--duration">
                                    <span class="rd-kv-label">Duração total</span>
                                    <span class="rd-kv-value" id="detailDuration">—</span>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div class="rd-panels">
                        <article class="rd-panel rd-panel--route">
                            <h3 class="rd-panel__title"><i class="fas fa-map-marker-alt" aria-hidden="true"></i> Origem e destino</h3>
                            <div class="rd-stack">
                                <div>
                                    <span class="rd-kv-label">Origem</span>
                                    <span class="rd-kv-value" id="detailOriginAddress">—</span>
                                </div>
                                <div>
                                    <span class="rd-kv-label">Destino</span>
                                    <span class="rd-kv-value" id="detailDestinationAddress">—</span>
                                </div>
                            </div>
                        </article>
                        <article class="rd-panel rd-panel--cargo">
                            <h3 class="rd-panel__title"><i class="fas fa-box" aria-hidden="true"></i> Carga</h3>
                            <div class="rd-kv-grid">
                                <div class="rd-kv rd-kv--span">
                                    <span class="rd-kv-label">Descrição</span>
                                    <span class="rd-kv-value" id="detailCargoDescription">—</span>
                                </div>
                                <div class="rd-kv">
                                    <span class="rd-kv-label">Peso</span>
                                    <span class="rd-kv-value" id="detailCargoWeight">—</span>
                                </div>
                                <div class="rd-kv">
                                    <span class="rd-kv-label">Cliente</span>
                                    <span class="rd-kv-value" id="detailCustomer">—</span>
                                </div>
                                <div class="rd-kv rd-kv--span">
                                    <span class="rd-kv-label">Contato</span>
                                    <span class="rd-kv-value" id="detailCustomerContact">—</span>
                                </div>
                            </div>
                        </article>
                    </div>

                    <section class="cost-summary rd-profit">
                        <h3 class="rd-section-title"><i class="fas fa-chart-line" aria-hidden="true"></i> Lucratividade</h3>
                        <div class="cost-cards rd-profit-grid">
                            <div class="cost-card rd-stat-card rd-stat-card--revenue">
                                <h4 class="rd-stat-card__head"><i class="fas fa-dollar-sign" aria-hidden="true"></i> Receita bruta</h4>
                                <div class="cost-value rd-stat-card__value" id="profitReceitaBruta">R$ 0,00</div>
                                <p class="rd-stat-card__hint">Valor do frete</p>
                            </div>
                            <div class="cost-card rd-stat-card rd-stat-card--expense">
                                <h4 class="rd-stat-card__head"><i class="fas fa-receipt" aria-hidden="true"></i> Despesas totais</h4>
                                <div class="cost-value rd-stat-card__value" id="profitDespesasTotais">R$ 0,00</div>
                                <p class="rd-stat-card__hint">Comissão + despesas + combustível</p>
                            </div>
                            <div class="cost-card rd-stat-card rd-stat-card--profit">
                                <h4 class="rd-stat-card__head"><i class="fas fa-chart-pie" aria-hidden="true"></i> Lucro líquido</h4>
                                <div class="cost-value rd-stat-card__value" id="profitLucroLiquido">R$ 0,00</div>
                                <p class="rd-stat-card__hint">Resultado final da rota</p>
                            </div>
                            <div class="cost-card rd-stat-card rd-stat-card--margin">
                                <h4 class="rd-stat-card__head"><i class="fas fa-chart-area" aria-hidden="true"></i> Margem líquida</h4>
                                <div class="cost-value rd-stat-card__value" id="profitMargem">0%</div>
                                <p class="rd-stat-card__hint">Percentual sobre a receita</p>
                            </div>
                        </div>
                        <div class="cost-breakdown">
                            <h4 class="rd-subsection-title"><i class="fas fa-list-alt" aria-hidden="true"></i> Composição do resultado</h4>
                            <div class="cost-breakdown-table rd-table-wrap">
                                <table class="info-table rd-table">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th class="rd-num">Valor</th>
                                            <th class="rd-num">% Receita</th>
                                        </tr>
                                    </thead>
                                    <tbody id="profitabilityTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="rd-meter-wrap">
                            <h4 class="rd-subsection-title"><i class="fas fa-tachometer-alt" aria-hidden="true"></i> Indicador de rentabilidade</h4>
                            <div class="rd-meter-track">
                                <div id="profitabilityIndicator" class="rd-meter-fill"></div>
                            </div>
                            <div class="rd-meter-legend">
                                <span class="rd-meter-legend__item">Prejuízo</span>
                                <span class="rd-meter-legend__item">Baixa</span>
                                <span class="rd-meter-legend__item">Boa</span>
                                <span class="rd-meter-legend__item">Excelente</span>
                            </div>
                        </div>
                    </section>

                    <section class="rd-notes">
                        <h3 class="rd-section-title">Observações</h3>
                        <p id="detailNotes" class="rd-notes__body"></p>
                    </section>
                </div>
            </div>
            <div class="modal-footer route-view-modal__footer">
                <button type="button" id="closeRouteDetailsBtn" class="btn-secondary">Fechar</button>
            </div>
        </div>
    </div>
    
    <script>
    // Observer para calcular lucratividade quando o modal for aberto
    (function() {
        const modal = document.getElementById('viewRouteModal');
        if (modal) {
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.attributeName === 'style') {
                        const displayStyle = window.getComputedStyle(modal).display;
                        if (displayStyle === 'block' || displayStyle === 'flex') {
                            const rotaId = modal.getAttribute('data-route-id');
                            if (rotaId && window.calcularLucratividade) {
                                window.__SF_DEBUG__ && console.log('Calculando lucratividade para rota:', rotaId);
                                window.calcularLucratividade(rotaId);
                            }
                        }
                    }
                });
            });
            
            observer.observe(modal, {
                attributes: true,
                attributeFilter: ['style', 'data-route-id']
            });
            
            // Também observar quando o atributo data-route-id mudar
            const titleObserver = new MutationObserver(function() {
                const rotaId = modal.getAttribute('data-route-id');
                if (rotaId && modal.style.display === 'block') {
                    requestAnimationFrame(function() {
                        requestAnimationFrame(function() {
                            if (window.calcularLucratividade) {
                                window.calcularLucratividade(rotaId);
                            }
                        });
                    });
                }
            });
            
            titleObserver.observe(modal, {
                attributes: true,
                attributeFilter: ['data-route-id']
            });
        }
    })();
    </script>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteRouteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirmar Exclusão</h2>
                <span class="close-modal close-delete-modal">&times;</span>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir a rota <strong id="deleteRouteInfo"></strong>?</p>
                <p class="warning-text">Esta ação não pode ser desfeita.</p>
            </div>
            <div class="modal-footer">
                <button id="cancelDeleteBtn" class="btn-secondary">Cancelar</button>
                <button id="confirmDeleteBtn" class="btn-danger">Excluir</button>
            </div>
        </div>
    </div>

    <!-- Filter Modal -->
    <div class="modal" id="filterModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Filtrar por Período</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="filterMonth">Mês/Ano</label>
                    <input type="month" id="filterMonth" name="filterMonth" title="Ou use o período abaixo">
                </div>
                <div class="form-group">
                    <label for="filterDateFrom">Data início</label>
                    <input type="date" id="filterDateFrom" class="form-control">
                </div>
                <div class="form-group">
                    <label for="filterDateTo">Data fim</label>
                    <input type="date" id="filterDateTo" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button id="clearFilterBtn" class="btn-secondary" aria-label="Limpar filtro de período">Limpar Filtro</button>
                <button id="applyFilterBtn" class="btn-primary" aria-label="Aplicar filtro de período">Aplicar</button>
            </div>
        </div>
    </div>

    <div class="modal" id="columnsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Colunas da tabela</h2>
                <span class="close-modal" title="Fechar">&times;</span>
            </div>
            <div class="modal-body">
                <p class="text-muted" style="font-size:0.8rem; margin-top:0;">Marque as colunas e clique em <strong>Salvar preferências</strong>. As opções extras vêm do cadastro da rota (banco).</p>
                <div id="columnsToggleList">
                    <div class="col-toggle-row col-toggle-section">Principais</div>
                    <label class="col-toggle-row"><span>Data</span><input type="checkbox" data-col-toggle="data" checked></label>
                    <label class="col-toggle-row"><span>Motorista</span><input type="checkbox" data-col-toggle="motorista" checked></label>
                    <label class="col-toggle-row"><span>Veículo</span><input type="checkbox" data-col-toggle="veiculo" checked></label>
                    <label class="col-toggle-row"><span>Rota</span><input type="checkbox" data-col-toggle="rota" checked></label>
                    <label class="col-toggle-row"><span>Distância</span><input type="checkbox" data-col-toggle="dist" checked></label>
                    <label class="col-toggle-row"><span>Frete</span><input type="checkbox" data-col-toggle="frete" checked></label>
                    <div class="col-toggle-row col-toggle-section">Extras (opcional)</div>
                    <label class="col-toggle-row"><span>ID</span><input type="checkbox" data-col-toggle="col_id"></label>
                    <label class="col-toggle-row"><span>Saída (data/hora)</span><input type="checkbox" data-col-toggle="col_saida"></label>
                    <label class="col-toggle-row"><span>Chegada (data/hora)</span><input type="checkbox" data-col-toggle="col_chegada"></label>
                    <label class="col-toggle-row"><span>KM saída</span><input type="checkbox" data-col-toggle="col_km_saida"></label>
                    <label class="col-toggle-row"><span>KM chegada</span><input type="checkbox" data-col-toggle="col_km_chegada"></label>
                    <label class="col-toggle-row"><span>KM vazio</span><input type="checkbox" data-col-toggle="col_km_vazio"></label>
                    <label class="col-toggle-row"><span>Total KM</span><input type="checkbox" data-col-toggle="col_total_km"></label>
                    <label class="col-toggle-row"><span>Comissão</span><input type="checkbox" data-col-toggle="col_comissao"></label>
                    <label class="col-toggle-row"><span>Eficiência %</span><input type="checkbox" data-col-toggle="col_eficiencia"></label>
                    <label class="col-toggle-row"><span>% vazio</span><input type="checkbox" data-col-toggle="col_pct_vazio"></label>
                    <label class="col-toggle-row"><span>Peso carga (kg)</span><input type="checkbox" data-col-toggle="col_peso"></label>
                    <label class="col-toggle-row"><span>Descrição carga</span><input type="checkbox" data-col-toggle="col_desc_carga"></label>
                    <div class="col-toggle-row col-toggle-section">Sempre úteis</div>
                    <label class="col-toggle-row"><span>Status</span><input type="checkbox" data-col-toggle="status" checked></label>
                    <label class="col-toggle-row"><span>Ações</span><input type="checkbox" data-col-toggle="acoes" checked></label>
                </div>
            </div>
            <div class="modal-footer columns-modal-footer">
                <button type="button" class="btn-secondary btn-columns-close close-modal">Fechar</button>
                <button type="button" class="btn-primary btn-columns-save" id="columnsSavePrefsBtn">Salvar preferências</button>
            </div>
        </div>
    </div>

    <!-- Route Simulation Modal -->
    <div class="modal" id="routeSimulationModal">
        <div class="modal-content route-sim-modal-content">
            <div class="modal-header">
                <h2>🚛 Simulador de Rota</h2>
                <span class="close-modal" onclick="closeSimulationModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="route-sim-grid-main">
                    <div class="simulation-form">
                        <h3 class="route-sim-section-title">📍 Configuração da Rota</h3>
                        
                        <div class="form-group">
                            <label for="simOrigin">Origem:</label>
                            <input type="text" id="simOrigin" class="route-sim-input" placeholder="Ex: São Paulo, SP">
                        </div>
                        
                        <div class="form-group">
                            <label for="simDestination">Destino:</label>
                            <input type="text" id="simDestination" class="route-sim-input" placeholder="Ex: Rio de Janeiro, RJ">
                        </div>
                        
                        <div class="form-group">
                            <label for="simVehicle">Veículo:</label>
                            <select id="simVehicle" class="route-sim-input">
                                <option value="">Selecione um veículo</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="simFuelPrice">Preço do Combustível (R$/L):</label>
                            <input type="number" id="simFuelPrice" class="route-sim-input" step="0.01" value="5.50">
                        </div>
                        
                        <button type="button" id="simulateRouteBtnModal" class="btn-route-sim-calc">
                            <i class="fas fa-calculator"></i> Simular Rota
                        </button>
                        
                        <div class="route-sim-external-box">
                            <h4 class="route-sim-external-title">
                                <i class="fas fa-external-link-alt"></i> Simuladores de Rotas Online
                            </h4>
                            <div class="route-sim-external-grid">
                                <a href="https://rotasbrasil.com.br/" target="_blank" rel="noopener noreferrer" class="route-sim-external-link">
                                    <i class="fas fa-route"></i>
                                    <span>Rotas Brasil</span>
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                                <a href="https://www.webrouter.com.br/way/#/calcularRota" target="_blank" rel="noopener noreferrer" class="route-sim-external-link">
                                    <i class="fas fa-map-marked-alt"></i>
                                    <span>WebRouter</span>
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                                <a href="https://qualp.com.br/#/" target="_blank" rel="noopener noreferrer" class="route-sim-external-link">
                                    <i class="fas fa-route"></i>
                                    <span>Qualp</span>
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                                <a href="https://www.semparar.com.br/trace-sua-rota" target="_blank" rel="noopener noreferrer" class="route-sim-external-link">
                                    <i class="fas fa-tag"></i>
                                    <span>Sem Parar</span>
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="simulation-map">
                        <h3 class="route-sim-section-title">🗺️ Mapa da Rota</h3>
                        <div id="simulationMap" class="route-sim-map-el"></div>
                        <div id="simulationInfo" class="route-sim-alert--info" role="status"></div>
                        <div id="simulationError" class="route-sim-alert--err" role="alert"></div>
                    </div>
                </div>
                
                <div id="simulationResults" class="route-sim-results">
                    <h3 class="route-sim-results-title">📊 Resultados da Simulação</h3>
                    
                    <div class="route-sim-result-cards">
                        <div class="result-card result-card--dist">
                            <h4 class="rc-title rc-title--green">📏 Distância</h4>
                            <p class="rc-val rc-val--green" id="simDistance">-</p>
                            <p class="rc-sub rc-sub--green" id="simDuration">-</p>
                        </div>
                        <div class="result-card result-card--fuel">
                            <h4 class="rc-title rc-title--orange">⛽ Combustível</h4>
                            <p class="rc-val rc-val--orange" id="simFuelCost">-</p>
                            <p class="rc-sub rc-sub--orange" id="simFuelLiters">-</p>
                        </div>
                        <div class="result-card result-card--toll">
                            <h4 class="rc-title rc-title--orange">🛣️ Pedágios</h4>
                            <p class="rc-val rc-val--orange" id="simTolls">-</p>
                            <p class="rc-sub rc-sub--orange" id="simTollCount">-</p>
                        </div>
                        <div class="result-card result-card--total">
                            <h4 class="rc-title rc-title--white">💰 Custo Total</h4>
                            <p class="rc-val rc-val--total" id="simTotalCost">-</p>
                            <p class="rc-sub rc-sub--total" id="simCostPerKm">-</p>
                        </div>
                    </div>
                    
                    <div class="route-sim-details-panel">
                        <h4>🛣️ Detalhes da Rota</h4>
                        <div id="routeDetails" class="route-sim-details-scroll"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Help Modal -->
    <div class="modal" id="helpRouteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ajuda - Rotas</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="help-section">
                    <h3>Visão Geral</h3>
                    <p>A página de Rotas permite gerenciar todas as rotas de transporte da sua frota. Aqui você pode:</p>
                    <ul>
                        <li>Visualizar todas as rotas ativas e concluídas</li>
                        <li>Adicionar novas rotas</li>
                        <li>Editar rotas existentes</li>
                        <li>Excluir rotas</li>
                        <li>Gerenciar despesas de viagem</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Dashboard</h3>
                    <p>O dashboard mostra os principais indicadores de desempenho:</p>
                    <ul>
                        <li><strong>Total de Rotas:</strong> Número total de rotas cadastradas</li>
                        <li><strong>Rotas Concluídas:</strong> Rotas finalizadas no período</li>
                        <li><strong>Distância Total:</strong> Quilômetros percorridos</li>
                        <li><strong>Frete Total:</strong> Valor total dos fretes</li>
                        <li><strong>Eficiência:</strong> Taxa de eficiência das rotas</li>
                        <li><strong>KM Vazio:</strong> Percentual de quilômetros sem carga</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Gráficos</h3>
                    <p>Os gráficos fornecem uma visão detalhada do desempenho:</p>
                    <ul>
                        <li><strong>Distância por Motorista:</strong> KM percorridos por motorista</li>
                        <li><strong>Eficiência por Motorista:</strong> Taxa de eficiência individual</li>
                        <li><strong>Rotas no Prazo:</strong> Distribuição de entregas no prazo</li>
                        <li><strong>Frete por Motorista:</strong> Valor dos fretes por motorista</li>
                        <li><strong>Evolução de KM:</strong> Histórico de quilometragem</li>
                        <li><strong>Indicadores:</strong> Análise multidimensional do desempenho</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Filtros</h3>
                    <p>Use os filtros para:</p>
                    <ul>
                        <li>Buscar rotas específicas</li>
                        <li>Filtrar por status</li>
                        <li>Filtrar por motorista</li>
                        <li>Filtrar por veículo</li>
                        <li>Filtrar por período</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-primary close-modal">Fechar</button>
            </div>
        </div>
    </div>

    <!-- Expenses Modal -->
    <div class="modal" id="expensesModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Despesas de Viagem</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="expensesForm">
                    <input type="hidden" id="expenseRouteId" name="rota_id">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="descarga">Descarga</label>
                            <input type="number" id="descarga" name="descarga" step="0.01" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="pedagios">Pedágios</label>
                            <input type="number" id="pedagios" name="pedagios" step="0.01" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="caixinha">Caixinha</label>
                            <input type="number" id="caixinha" name="caixinha" step="0.01" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="estacionamento">Estacionamento</label>
                            <input type="number" id="estacionamento" name="estacionamento" step="0.01" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="lavagem">Lavagem</label>
                            <input type="number" id="lavagem" name="lavagem" step="0.01" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="borracharia">Borracharia</label>
                            <input type="number" id="borracharia" name="borracharia" step="0.01" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="eletrica_mecanica">Elétrica/Mecânica</label>
                            <input type="number" id="eletrica_mecanica" name="eletrica_mecanica" step="0.01" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="adiantamento">Adiantamento</label>
                            <input type="number" id="adiantamento" name="adiantamento" step="0.01" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="total_despviagem">Total</label>
                            <input type="number" id="total_despviagem" name="total_despviagem" step="0.01" readonly>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button id="cancelExpensesBtn" class="btn-secondary">Cancelar</button>
                <button id="clearExpensesBtn" class="btn-danger">Limpar</button>
                <button id="saveExpensesBtn" class="btn-primary">Salvar</button>
            </div>
        </div>
    </div>

    <!-- Botão Flutuante do Mapa de Rotas -->
    <button type="button" id="btnMapaRotas" class="routes-map-fab" aria-label="Abrir mapa de rotas">
      <i class="fas fa-map-marked-alt"></i>
    </button>

    <!-- Modal do Mapa de Rotas -->
    <div id="modalMapaRotas" class="routes-map-overlay">
      <div class="routes-map-modal-panel">
        <button type="button" onclick="fecharModalMapa()" class="routes-map-modal-close" aria-label="Fechar mapa">&times;</button>
        <div class="routes-map-toolbar-wrap">
          <div class="routes-map-toolbar">
            <input type="month" id="filtroMesMapa" class="routes-map-month" placeholder="Selecione o mês/ano" title="Mês/ano">
            <button type="button" onclick="desenhaMapaComRotas()" class="btn-mapa-filtro">
              <i class="fas fa-filter"></i> Filtrar
            </button>
            <button type="button" id="btnAlternarMapa" class="btn-mapa-gmaps">
              <i class="fas fa-map-marked-alt"></i> Google Maps
            </button>
            <button type="button" id="btnLimparMapa" class="btn-mapa-clear">
              <i class="fas fa-eraser"></i> Limpar
            </button>
          </div>
        </div>
        <div class="routes-map-stats-row">
          <span id="coordenadaInfo" class="routes-map-coord-hint"></span>
          <div id="mapStats" class="routes-map-stats">
            <div class="routes-map-stats-inner">
              <span class="routes-map-stat">
                <i class="fas fa-route"></i>
                <span id="totalRotasMapa">0</span> rotas
              </span>
              <span class="routes-map-sep">|</span>
              <span class="routes-map-stat">
                <i class="fas fa-road"></i>
                <span id="totalKmMapa">0</span> km
              </span>
              <span class="routes-map-sep">|</span>
              <span class="routes-map-stat">
                <i class="fas fa-dollar-sign"></i>
                <span id="totalFreteMapa">R$ 0,00</span>
              </span>
            </div>
          </div>
        </div>
        
        <div class="map-container routes-map-canvas-wrap">
        <canvas id="mapCanvas" class="routes-map-canvas" width="800" height="700"></canvas>
        <div id="googleMap" class="routes-map-gmap"></div>
        </div>
      </div>
    </div>

    <div id="mapTooltip" class="routes-map-tooltip" role="tooltip"></div>

    <div id="toastContainer" class="toast-container routes-page-toast" aria-live="polite"></div>
    <script>
    function showToast(message, type) {
        type = type || 'info';
        var container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container routes-page-toast';
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }
        if (container.parentNode !== document.body) {
            document.body.appendChild(container);
        }
        var toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.setAttribute('role', 'alert');
        var icon = type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle');
        toast.innerHTML = '<i class="fas ' + icon + '"></i><span>' + (message || '') + '</span>';
        container.appendChild(toast);
        setTimeout(function() { toast.style.opacity = '0'; setTimeout(function() { toast.remove(); }, 300); }, 4000);
    }
    window.showToast = showToast;
    </script>

    <!-- JavaScript Files (header.js já é carregado pelo includes/header.php) -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/routes.js"></script>
    <script>
    // Garantir dropdown do perfil no menu superior (igual às outras páginas)
    (function() {
        function initProfileDropdownOnce() {
            if (window.__profileDropdownInited) return;
            var btn = document.getElementById('userProfileBtn');
            var dropdown = document.getElementById('profileDropdown');
            if (!btn || !dropdown) return;
            window.__profileDropdownInited = true;
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var open = !dropdown.classList.contains('show');
                dropdown.classList.toggle('show', open);
                btn.classList.toggle('active', open);
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
            document.addEventListener('click', function(e) {
                if (dropdown.classList.contains('show') && !dropdown.contains(e.target) && !btn.contains(e.target)) {
                    dropdown.classList.remove('show');
                    btn.classList.remove('active');
                    btn.setAttribute('aria-expanded', 'false');
                }
            });
            dropdown.addEventListener('click', function(e) { e.stopPropagation(); });
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initProfileDropdownOnce);
        } else {
            initProfileDropdownOnce();
        }
    })();
    </script>
    
    <!-- Google Maps Scripts -->
    <script src="../google-maps/maps.js"></script>
    <script src="../google-maps/route-manager.js"></script>
    <script src="../google-maps/geolocation.js"></script>
    

    <!-- Mapa + simulação: URLs via window.__SF_ROUTES_MAP_IMG__ -->
    <script>window.__SF_ROUTES_MAP_IMG__=<?php echo json_encode(sf_app_url('uploads/mapa/mapa-brasil.png'), JSON_UNESCAPED_SLASHES); ?>;</script>
    <script src="../js/routes-simulation.js?v=1.0.1"></script>
    <script src="../js/routes-map.js?v=1.0.1"></script>

    <?php include '../includes/scroll_to_top.php'; ?>
</body>
</html>

