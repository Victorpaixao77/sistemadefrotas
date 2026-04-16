<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/sf_api_base.php';
require_once '../includes/abastecimentos_repository.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

require_authentication();

// Set page title
$page_title = "Abastecimentos";

// Por página: 5, 10, 25, 50, 100 — padrão 10
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if (!in_array($per_page, [5, 10, 25, 50, 100], true)) {
    $per_page = 10;
}

$is_modern = !isset($_GET['classic']) || (string)$_GET['classic'] !== '1';

// Pegar a página atual da URL ou definir como 1
$pagina_atual = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Buscar abastecimentos com paginação
$resultado = getAbastecimentos($pagina_atual, $per_page);
$abastecimentos = $resultado['abastecimentos'];
$total_paginas = $resultado['total_paginas'];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    <?php sf_render_api_scripts(); ?>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../logo.png">
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <link rel="stylesheet" href="../css/abastecimentos.css?v=<?php echo htmlspecialchars(sf_asset_v(), ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($is_modern): ?>
    <link rel="stylesheet" href="../css/fornc-modern-page.css">
    <?php endif; ?>
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Sortable.js for drag-and-drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</head>
<body class="<?php echo $is_modern ? 'abastecimentos-modern' : ''; ?>">
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
                        <button id="addRefuelBtn" class="btn-add-widget">
                            <i class="fas fa-plus"></i> Novo Abastecimento
                        </button>
                        <div class="view-controls">
                            <button id="filterBtn" class="btn-restore-layout" title="Filtros" aria-label="Abrir filtros por mês e ano">
                                <i class="fas fa-filter"></i>
                            </button>
                            <button id="exportBtn" class="btn-toggle-layout" title="Exportar lista em CSV/Excel" aria-label="Exportar lista de abastecimentos em CSV">
                                <i class="fas fa-file-export"></i>
                            </button>
                            <button id="helpBtn" class="btn-help" title="Ajuda" aria-label="Ajuda sobre abastecimentos">
                                <i class="fas fa-question-circle"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($is_modern): ?>
                <div class="fornc-kpi-strip">
                    <div class="fornc-kpi-cell"><span class="lbl">Abastecimentos</span><span class="val" id="refuelKpiAbastecimentos">0</span></div>
                    <div class="fornc-kpi-cell"><span class="lbl">Litros (mês)</span><span class="val" id="refuelKpiLitros">0</span></div>
                    <div class="fornc-kpi-cell"><span class="lbl">Valor (mês)</span><span class="val" id="refuelKpiValor">R$ 0,00</span></div>
                    <div class="fornc-kpi-cell"><span class="lbl">Média R$/L</span><span class="val" id="refuelKpiMediaLitro">R$ 0,00/L</span></div>
                </div>
                <?php else: ?>
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Abastecimentos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="refuelKpiAbastecimentos">0</span>
                                <span class="metric-subtitle">Total este mês</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Litros</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="refuelKpiLitros">0</span>
                                <span class="metric-subtitle">Total este mês</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Valor</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="refuelKpiValor">R$ 0,00</span>
                                <span class="metric-subtitle">Total este mês</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Médias</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="refuelKpiMediaLitro">R$ 0,00/L</span>
                                <span class="metric-subtitle">Média no mês</span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($is_modern): ?>
                <div class="fornc-toolbar">
                    <div class="fornc-search-block">
                        <label for="searchRefueling">Busca rápida</label>
                        <div class="fornc-search-inner">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input type="text" id="searchRefueling" placeholder="Posto, placa, motorista..." autocomplete="off">
                        </div>
                    </div>
                    <div class="fornc-filters-inline">
                        <div class="fg">
                            <label for="vehicleFilter">Veículo</label>
                            <select id="vehicleFilter" title="Filtrar por veículo">
                                <option value="">Todos</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label for="driverFilter">Motorista</label>
                            <select id="driverFilter" title="Filtrar por motorista">
                                <option value="">Todos</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label for="fuelFilter">Combustível</label>
                            <select id="fuelFilter" title="Tipo de combustível">
                                <option value="">Todos</option>
                                <option value="Diesel S10">Diesel S10</option>
                                <option value="Diesel Comum">Diesel Comum</option>
                                <option value="Gasolina">Gasolina</option>
                                <option value="Etanol">Etanol</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label for="paymentFilter">Pagamento</label>
                            <select id="paymentFilter" title="Forma de pagamento">
                                <option value="">Todas</option>
                                <option value="Dinheiro">Dinheiro</option>
                                <option value="Cartão">Cartão</option>
                                <option value="Boleto">Boleto</option>
                                <option value="PIX">PIX</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label for="perPageRefuel">Por página</label>
                            <form method="get" action="" id="formPerPageRefuel" style="margin:0;">
                                <select id="perPageRefuel" name="per_page" class="filter-per-page" title="Registros por página">
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
                        <button type="button" id="addRefuelBtn" class="fornc-btn fornc-btn--primary"><i class="fas fa-plus"></i> Novo</button>
                        <button type="button" class="fornc-btn fornc-btn--accent" id="applyRefuelFilters" title="Aplicar filtros"><i class="fas fa-search"></i> Pesquisar</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="filterBtn" title="Filtro por mês/ano"><i class="fas fa-sliders-h"></i> Opções</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="clearRefuelFilters" title="Limpar filtros"><i class="fas fa-undo"></i></button>
                        <button type="button" class="fornc-btn fornc-btn--muted" id="exportBtn" title="Exportar CSV"><i class="fas fa-file-export"></i> Exportar</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>
                <?php else: ?>
                <div class="filter-section">
                    <div class="search-box">
                        <input type="text" id="searchRefueling" placeholder="Buscar abastecimento...">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="filter-options">
                        <form method="get" action="" id="formPerPageRefuel" style="display:inline-flex; align-items:center; gap:0.5rem;">
                            <span class="filter-label">Por página</span>
                            <input type="hidden" name="page" value="1">
                            <input type="hidden" name="classic" value="1">
                            <select id="perPageRefuel" name="per_page" class="filter-per-page" title="Registros por página" onchange="this.form.submit()">
                                <option value="5"  <?php echo $per_page == 5  ? 'selected' : ''; ?>>5</option>
                                <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </form>
                        <select id="vehicleFilter">
                            <option value="">Todos os veículos</option>
                        </select>
                        <select id="driverFilter">
                            <option value="">Todos os motoristas</option>
                        </select>
                        <select id="fuelFilter">
                            <option value="">Todos os combustíveis</option>
                            <option value="Diesel S10">Diesel S10</option>
                            <option value="Diesel Comum">Diesel Comum</option>
                            <option value="Gasolina">Gasolina</option>
                            <option value="Etanol">Etanol</option>
                        </select>
                        <select id="paymentFilter">
                            <option value="">Todas as formas de pagamento</option>
                            <option value="Dinheiro">Dinheiro</option>
                            <option value="Cartão">Cartão</option>
                            <option value="Boleto">Boleto</option>
                            <option value="PIX">PIX</option>
                        </select>
                        <button type="button" class="btn-restore-layout" id="applyRefuelFilters" title="Aplicar filtros" aria-label="Aplicar filtros">
                            <i class="fas fa-filter"></i>
                        </button>
                        <button type="button" class="btn-restore-layout" id="clearRefuelFilters" title="Limpar filtros" aria-label="Limpar filtros">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Refueling Table -->
                <div class="<?php echo $is_modern ? 'fornc-table-wrap refuel-table-wrap' : 'data-table-container'; ?>" id="refuelTableContainer">
                    <div class="table-loading" id="refuelTableLoading" aria-live="polite">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Carregando abastecimentos...</span>
                    </div>
                    <table class="<?php echo $is_modern ? 'fornc-table' : 'data-table'; ?>" id="refuelingTable">
                        <thead>
                            <tr>
                                <?php if ($is_modern): ?>
                                <th class="sortable sorted" data-sort="data_abastecimento">Data <span class="sort-ind">▼</span></th>
                                <th class="sortable" data-sort="veiculo_placa">Veículo <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="motorista_nome">Motorista <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="posto">Posto <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="litros">Litros <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="valor_litro">Valor/L <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="valor_total">Valor Total <span class="sort-ind">⇅</span></th>
                                <th class="col-arla sortable" data-sort="litros_arla">ARLA <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="km_atual">Km <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="forma_pagamento">Forma Pgto <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="rota">Rota <span class="sort-ind">⇅</span></th>
                                <?php else: ?>
                                <th>Data</th>
                                <th>Veículo</th>
                                <th>Motorista</th>
                                <th>Posto</th>
                                <th>Litros</th>
                                <th>Valor/L</th>
                                <th>Valor Total</th>
                                <th class="col-arla">ARLA</th>
                                <th>Km</th>
                                <th>Forma Pgto</th>
                                <th>Rota</th>
                                <?php endif; ?>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="refuelingTableBody">
                            <?php if (!empty($abastecimentos)): ?>
                                <?php foreach ($abastecimentos as $abastecimento): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($abastecimento['data_abastecimento'])); ?></td>
                                    <td><?php echo htmlspecialchars($abastecimento['veiculo_placa']); ?></td>
                                    <td><?php echo htmlspecialchars($abastecimento['motorista_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($abastecimento['posto']); ?></td>
                                    <td><?php echo number_format($abastecimento['litros'], 3, ',', '.'); ?></td>
                                    <td>R$ <?php echo number_format($abastecimento['valor_litro'], 2, ',', '.'); ?></td>
                                    <?php
                                        $valor_total_abastecimento = (float)$abastecimento['valor_total'];
                                        if (!empty($abastecimento['inclui_arla']) && (int)$abastecimento['inclui_arla'] === 1) {
                                            $valor_total_abastecimento += (float)($abastecimento['valor_total_arla'] ?? 0);
                                        }
                                    ?>
                                    <td>R$ <?php echo number_format($valor_total_abastecimento, 2, ',', '.'); ?></td>
                                    <td class="col-arla">
                                        <?php if (!empty($abastecimento['inclui_arla']) && $abastecimento['inclui_arla'] == 1): ?>
                                            <?php 
                                            $percentual_arla = 0;
                                            if ($abastecimento['litros'] > 0) {
                                                $percentual_arla = ($abastecimento['litros_arla'] / $abastecimento['litros']) * 100;
                                            }
                                            $classe_percentual = '';
                                            if ($percentual_arla >= 3 && $percentual_arla <= 5) {
                                                $classe_percentual = 'percentual-ok';
                                            } elseif ($percentual_arla > 5) {
                                                $classe_percentual = 'percentual-alto';
                                            } else {
                                                $classe_percentual = 'percentual-baixo';
                                            }
                                            $arla_pct_txt = abs($percentual_arla) >= 100
                                                ? number_format(round($percentual_arla), 0, ',', '')
                                                : number_format($percentual_arla, 1, ',', '');
                                            $arla_pct_title = number_format($percentual_arla, 2, ',', '');
                                            ?>
                                            <span class="percentual-arla <?php echo $classe_percentual; ?>" title="Percentual ARLA (litros ARLA ÷ diesel): <?php echo htmlspecialchars($arla_pct_title, ENT_QUOTES, 'UTF-8'); ?>%">
                                                <?php echo htmlspecialchars($arla_pct_txt, ENT_QUOTES, 'UTF-8'); ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($abastecimento['km_atual'], 0, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($abastecimento['forma_pagamento']); ?></td>
                                    <td><?php 
                                        if (!empty($abastecimento['cidade_origem_nome']) && !empty($abastecimento['cidade_destino_nome'])) {
                                            echo htmlspecialchars($abastecimento['cidade_origem_nome'] . ' → ' . $abastecimento['cidade_destino_nome']);
                                        } else {
                                            echo '-';
                                        }
                                    ?></td>
                                    <td class="actions">
                                        <button class="btn-icon edit-btn" data-id="<?php echo $abastecimento['id']; ?>" title="Editar" aria-label="Editar abastecimento">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if (!empty($abastecimento['comprovante'])): ?>
                                            <button class="btn-icon view-comprovante-btn" data-comprovante="<?php echo htmlspecialchars($abastecimento['comprovante']); ?>" title="Ver Comprovante" aria-label="Ver comprovante">
                                                <i class="fas fa-file-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn-icon delete-btn" data-id="<?php echo $abastecimento['id']; ?>" title="Excluir" aria-label="Excluir abastecimento">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="12" class="text-center">Nenhum abastecimento encontrado</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginação -->
                <?php
                $base_params = ['page' => 1, 'per_page' => $per_page];
                if (!$is_modern) {
                    $base_params['classic'] = '1';
                }
                $prev_params = array_merge($base_params, ['page' => max(1, $pagina_atual - 1)]);
                $next_params = array_merge($base_params, ['page' => min($total_paginas, $pagina_atual + 1)]);
                ?>
                <?php if ($is_modern): ?><div class="fornc-pagination-bar"><?php endif; ?>
                <div class="pagination<?php echo $is_modern ? ' fornc-modern-pagination' : ''; ?>" id="paginationRefuelContainer" data-per-page="<?php echo (int)$per_page; ?>">
                    <a href="?<?php echo htmlspecialchars(http_build_query($prev_params)); ?>" 
                       class="pagination-btn pagination-prev <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>"
                       data-page="<?php echo max(1, $pagina_atual - 1); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <span class="pagination-info" id="paginationRefuelInfo">
                        <?php if ($total_paginas > 1): ?>Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?> (<?php echo (int)$resultado['total']; ?> registros)
                        <?php else: ?><?php echo (int)$resultado['total']; ?> registros<?php endif; ?>
                    </span>
                    <a href="?<?php echo htmlspecialchars(http_build_query($next_params)); ?>" 
                       class="pagination-btn pagination-next <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>"
                       data-page="<?php echo min($total_paginas, $pagina_atual + 1); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                <?php if ($is_modern): ?></div><?php endif; ?>

                <!-- Analytics Section (gráficos) -->
                <div class="analytics-section">
                    <div class="section-header">
                        <h2>Análise de Consumo</h2>
                    </div>
                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Consumo de Combustível (Últimos 6 meses)</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="fuelConsumptionChart"></canvas>
                            </div>
                        </div>
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Rendimento por Veículo (km/L)</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="fuelEfficiencyChart"></canvas>
                            </div>
                        </div>
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Anomalias de Consumo</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="anomaliesChart"></canvas>
                            </div>
                        </div>
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Consumo por Motorista</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="driverConsumptionChart"></canvas>
                            </div>
                        </div>
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Eficiência por Veículo (R$/km)</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="vehicleEfficiencyChart"></canvas>
                            </div>
                        </div>
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Evolução do Custo Mensal</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="monthlyCostChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>
    
    <!-- Add/Edit Refueling Modal -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="refuelModal">
        <div class="modal-content<?php echo $is_modern ? ' modal-lg fornc-modal--wide' : ''; ?>">
            <div class="modal-header">
                <h2 id="modalTitle">Adicionar Abastecimento</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="refuelForm">
                    <input type="hidden" id="refuelId">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="data_rota_filtro">Data da Rota*</label>
                            <input type="date" id="data_rota_filtro" name="data_rota_filtro" required>
                        </div>
                        <div class="form-group">
                            <label for="data_abastecimento">Data Abastecimento*</label>
                            <input type="datetime-local" id="data_abastecimento" name="data_abastecimento" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="veiculo_id">Veículo*</label>
                            <select id="veiculo_id" name="veiculo_id" required>
                                <option value="">Selecione um veículo</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="motorista_id">Motorista*</label>
                            <select id="motorista_id" name="motorista_id" required>
                                <option value="">Selecione um motorista</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="rota_id">Rota*</label>
                            <select id="rota_id" name="rota_id" required>
                                <option value="">Selecione a rota</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="tipo_combustivel">Combustível*</label>
                            <select id="tipo_combustivel" name="tipo_combustivel" required>
                                <option value="">Selecione o combustível</option>
                                <option value="Diesel S10">Diesel S10</option>
                                <option value="Diesel Comum">Diesel Comum</option>
                                <option value="Gasolina">Gasolina</option>
                                <option value="Etanol">Etanol</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="litros">Litros*</label>
                            <input type="text" id="litros" name="litros" class="numeric-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="valor_litro">Valor por Litro*</label>
                            <input type="text" id="valor_litro" name="valor_litro" class="numeric-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="valor_total">Valor Total*</label>
                            <input type="text" id="valor_total" name="valor_total" class="numeric-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="km_atual">Quilometragem*</label>
                            <input type="number" id="km_atual" name="km_atual" required placeholder="Ex: 150000">
                            <small class="form-text" style="color: #6c757d; font-size: 0.875rem; margin-top: 4px;">
                                <i class="fas fa-info-circle"></i> <span id="km_atual_help">Selecione uma rota para validar a quilometragem</span>
                            </small>
                            <div id="km_atual_validation" style="margin-top: 5px; font-size: 0.875rem;"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="posto">Posto*</label>
                            <input type="text" id="posto" name="posto" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="forma_pagamento">Forma de Pagamento*</label>
                            <select id="forma_pagamento" name="forma_pagamento" required>
                                <option value="">Selecione a forma de pagamento</option>
                                <option value="Dinheiro">Dinheiro</option>
                                <option value="Cartão">Cartão</option>
                                <option value="Boleto">Boleto</option>
                                <option value="PIX">PIX</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Seção ARLA -->
                    <div class="form-group full-width">
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="inclui_arla" name="inclui_arla" value="1">
                                <span class="checkmark"></span>
                                Este abastecimento incluiu ARLA?
                            </label>
                        </div>
                        <small class="form-text text-muted">
                            <i class="fas fa-info-circle"></i>
                            A porcentagem ideal de ARLA é de 3% a 5% em relação ao volume de diesel.
                        </small>
                    </div>
                    
                    <!-- Campos ARLA (inicialmente ocultos) -->
                    <div id="campos_arla" class="form-grid" style="display: none;">
                        <div class="form-group">
                            <label for="litros_arla">Litros ARLA</label>
                            <input type="text" id="litros_arla" name="litros_arla" class="numeric-input">
                        </div>
                        
                        <div class="form-group">
                            <label for="valor_litro_arla">Valor/Litro ARLA</label>
                            <input type="text" id="valor_litro_arla" name="valor_litro_arla" class="numeric-input">
                        </div>
                        
                        <div class="form-group">
                            <label for="valor_total_arla">Valor Total ARLA</label>
                            <input type="text" id="valor_total_arla" name="valor_total_arla" class="numeric-input" readonly>
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="observacoes">Observações</label>
                        <textarea id="observacoes" name="observacoes" rows="3"></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label for="comprovante">Comprovante</label>
                        <input type="file" id="comprovante" name="comprovante" accept=".pdf,.jpg,.jpeg,.png">
                        <small class="form-text text-muted">Formatos aceitos: PDF, JPG, JPEG, PNG</small>
                        <div id="comprovante_atual"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button id="cancelRefuelBtn" class="btn-secondary">Cancelar</button>
                <button id="saveRefuelBtn" class="btn-primary">Salvar</button>
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
                        <input type="month" id="filterMonth" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="clearFilterBtn" class="btn-secondary">Limpar</button>
                <button id="applyFilterBtn" class="btn-primary">Aplicar</button>
            </div>
        </div>
    </div>

    <!-- Help Modal -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="helpModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ajuda - Abastecimentos</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="help-section">
                    <h3>Visão Geral</h3>
                    <p>O módulo de Abastecimentos permite gerenciar todos os abastecimentos da sua frota, oferecendo análises detalhadas de consumo e custos.</p>
                </div>

                <div class="help-section">
                    <h3>Funcionalidades Principais</h3>
                    <ul>
                        <li><strong>Novo Abastecimento:</strong> Registre novos abastecimentos com informações como:
                            <ul>
                                <li>Data e hora</li>
                                <li>Veículo e motorista</li>
                                <li>Tipo de combustível e quantidade</li>
                                <li>Valor por litro e total</li>
                                <li>Quilometragem atual</li>
                                <li>Posto e forma de pagamento</li>
                            </ul>
                        </li>
                        <li><strong>Filtros:</strong> Filtre os dados por:
                            <ul>
                                <li>Mês/Ano</li>
                                <li>Veículo</li>
                                <li>Motorista</li>
                                <li>Tipo de combustível</li>
                                <li>Forma de pagamento</li>
                            </ul>
                        </li>
                        <li><strong>Análises:</strong> Visualize:
                            <ul>
                                <li>Total de abastecimentos no período</li>
                                <li>Consumo total em litros</li>
                                <li>Valor total gasto</li>
                                <li>Média de consumo (km/L)</li>
                                <li>Gráficos de consumo e eficiência</li>
                            </ul>
                        </li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Como Usar</h3>
                    <ol>
                        <li><strong>Registrar Abastecimento:</strong>
                            <ul>
                                <li>Clique no botão "Novo Abastecimento"</li>
                                <li>Preencha todos os campos obrigatórios (*)</li>
                                <li>Clique em "Salvar"</li>
                            </ul>
                        </li>
                        <li><strong>Filtrar Dados:</strong>
                            <ul>
                                <li>Clique no botão de filtro (ícone de funil)</li>
                                <li>Selecione o mês/ano desejado</li>
                                <li>Clique em "Aplicar" para ver os dados filtrados</li>
                            </ul>
                        </li>
                        <li><strong>Gerenciar Registros:</strong>
                            <ul>
                                <li>Use os botões de editar (lápis) para modificar um registro</li>
                                <li>Use os botões de excluir (lixeira) para remover um registro</li>
                            </ul>
                        </li>
                    </ol>
                </div>

                <div class="help-section">
                    <h3>Dicas</h3>
                    <ul>
                        <li>Mantenha os registros de quilometragem atualizados para obter análises precisas de consumo</li>
                        <li>Use os filtros para comparar o consumo entre diferentes períodos</li>
                        <li>Acompanhe regularmente os gráficos para identificar variações no consumo dos veículos</li>
                        <li>Exporte os dados quando precisar fazer análises mais detalhadas em planilhas</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast container (notificações) -->
    <div id="toastContainer" class="toast-container" aria-live="polite" aria-atomic="true"></div>

    <!-- Modal de confirmação de exclusão -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="deleteConfirmModal">
        <div class="modal-content modal-content-sm">
            <div class="modal-header">
                <h2>Excluir abastecimento</h2>
                <span class="close-modal" id="closeDeleteModal">&times;</span>
            </div>
            <div class="modal-body">
                <p id="deleteConfirmMessage">Deseja realmente excluir este abastecimento?</p>
            </div>
            <div class="modal-footer">
                <button type="button" id="cancelDeleteBtn" class="btn-secondary">Cancelar</button>
                <button type="button" id="confirmDeleteBtn" class="btn-primary btn-danger"><i class="fas fa-trash"></i> Excluir</button>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Files -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
        var consumptionChart = null;
        var efficiencyChart = null;
        var currentFilter = null;
        var refuelingCurrentPage = 1;
        var pendingDeleteId = null;
        var refuelSortField = 'data_abastecimento';
        var refuelSortDir = 'DESC';

        function refuelDefaultSortDir(field) {
            var textLike = ['veiculo_placa', 'motorista_nome', 'posto', 'forma_pagamento', 'rota'];
            if (textLike.indexOf(field) >= 0) return 'ASC';
            return 'DESC';
        }

        function syncRefuelSortIndicators() {
            var table = document.getElementById('refuelingTable');
            if (!table) return;
            table.querySelectorAll('thead th.sortable').forEach(function (th) {
                var field = th.getAttribute('data-sort');
                var ind = th.querySelector('.sort-ind');
                if (!ind) return;
                var on = field === refuelSortField;
                th.classList.toggle('sorted', on);
                ind.textContent = on ? (refuelSortDir === 'ASC' ? '▲' : '▼') : '⇅';
            });
        }

        function wireRefuelSortHeaders() {
            var table = document.getElementById('refuelingTable');
            if (!table) return;
            table.querySelectorAll('thead th.sortable').forEach(function (th) {
                th.addEventListener('click', function () {
                    var field = th.getAttribute('data-sort');
                    if (!field) return;
                    if (refuelSortField === field) {
                        refuelSortDir = refuelSortDir === 'ASC' ? 'DESC' : 'ASC';
                    } else {
                        refuelSortField = field;
                        refuelSortDir = refuelDefaultSortDir(field);
                    }
                    syncRefuelSortIndicators();
                    loadRefuelingData(1);
                });
            });
        }

        function showToast(message, type) {
            type = type || 'info';
            var container = document.getElementById('toastContainer');
            if (!container) return;
            var toast = document.createElement('div');
            toast.className = 'toast toast-' + type;
            toast.setAttribute('role', 'alert');
            var icon = type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle');
            toast.innerHTML = '<i class="fas ' + icon + '"></i><span>' + (message || '') + '</span>';
            container.appendChild(toast);
            setTimeout(function() {
                toast.style.opacity = '0';
                setTimeout(function() { toast.remove(); }, 300);
            }, 4000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Exportar CSV/Excel
            var exportBtn = document.getElementById('exportBtn');
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    var params = new URLSearchParams();
                    var search = document.getElementById('searchRefueling');
                    if (search && search.value.trim()) params.set('search', search.value.trim());
                    var v = document.getElementById('vehicleFilter'); if (v && v.value) params.set('veiculo', v.value);
                    var d = document.getElementById('driverFilter'); if (d && d.value) params.set('motorista', d.value);
                    var f = document.getElementById('fuelFilter'); if (f && f.value) params.set('combustivel', f.value);
                    var p = document.getElementById('paymentFilter'); if (p && p.value) params.set('pagamento', p.value);
                    if (currentFilter) {
                        var parts = currentFilter.split('-');
                        if (parts.length === 2) { params.set('year', parts[0]); params.set('month', parts[1]); }
                    }
                    window.open(sfApiUrl('refuel_export.php?' + params.toString()), '_blank');
                    showToast('Exportação iniciada. O download deve abrir em instantes.', 'info');
                });
            }

            // Modal de exclusão
            var deleteModal = document.getElementById('deleteConfirmModal');
            function closeDeleteModal() {
                if (deleteModal) deleteModal.classList.remove('active');
                pendingDeleteId = null;
            }
            document.getElementById('closeDeleteModal').addEventListener('click', closeDeleteModal);
            document.getElementById('cancelDeleteBtn').addEventListener('click', closeDeleteModal);
            document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
                if (pendingDeleteId) {
                    deleteRefuel(pendingDeleteId);
                    closeDeleteModal();
                }
            });

            wireRefuelSortHeaders();
            initializePage();
            
            // Configura eventos dos modais
            setupModals();
            
            // Configura filtros
            setupFilters();

            // Carrega o resumo (API utiliza o mês atual como padrão)
            loadRefuelingSummary();
        });
        
        function initializePage() {
            var upInit = new URLSearchParams(window.location.search);
            if (upInit.has('sort')) refuelSortField = upInit.get('sort') || refuelSortField;
            if (upInit.has('dir')) refuelSortDir = (upInit.get('dir') || '').toUpperCase() === 'ASC' ? 'ASC' : 'DESC';
            syncRefuelSortIndicators();
            // Load refuel data from API
            loadRefuelingData();
            
            // Load summary data
            loadRefuelingSummary();

            // Load filter options
            loadFilterOptions();
            
            // Load chart data
            loadConsumptionChart().then(() => {
                loadEfficiencyChart();
                // Gráficos definidos em abastecimentos.js (carregado após este script)
                if (typeof loadAnomaliesChart === 'function') {
                    loadAnomaliesChart();
                    loadDriverConsumptionChart();
                    loadVehicleEfficiencyChart();
                    loadMonthlyCostChart();
                }
            });
            
            // Setup button events
            document.getElementById('addRefuelBtn').addEventListener('click', showAddRefuelModal);
            document.getElementById('filterBtn').addEventListener('click', showFilterModal);
            document.getElementById('helpBtn').addEventListener('click', showHelpModal);
            
            // Setup search
            const searchInput = document.getElementById('searchRefueling');
            if (searchInput) {
            searchInput.addEventListener('input', debounce(() => {
                    loadRefuelingData(1);
            }, 300));

                searchInput.addEventListener('keydown', event => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        loadRefuelingData(1);
                    }
                });
            }
            
            // Sincronizar select "Por página" com a URL (ex.: link com ?per_page=25)
            const perPageRefuelEl = document.getElementById('perPageRefuel');
            const urlParamsInit = new URLSearchParams(window.location.search);
            const perPageFromUrl = parseInt(urlParamsInit.get('per_page'), 10);
            if (perPageRefuelEl && !Number.isNaN(perPageFromUrl) && [5, 10, 25, 50, 100].indexOf(perPageFromUrl) >= 0) {
                perPageRefuelEl.value = String(perPageFromUrl);
            }
            
            // Setup table buttons
            setupTableButtons();
        }
        
        function loadRefuelingData(page = null) {
            if (page !== null) {
                const parsedPage = parseInt(page, 10);
                refuelingCurrentPage = Number.isNaN(parsedPage) || parsedPage < 1 ? 1 : parsedPage;
            } else {
                const urlParamsCheck = new URLSearchParams(window.location.search);
                const urlPage = parseInt(urlParamsCheck.get('page'), 10);
                if (!Number.isNaN(urlPage) && urlPage > 0) {
                    refuelingCurrentPage = urlPage;
                }
            }

            const perPageSelect = document.getElementById('perPageRefuel');
            const perPageFromSelect = perPageSelect ? parseInt(perPageSelect.value, 10) : 10;
            const perPageValid = [5, 10, 25, 50, 100].indexOf(perPageFromSelect) >= 0 ? perPageFromSelect : 10;
            
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('page', refuelingCurrentPage);
            urlParams.set('per_page', perPageValid);
            if (refuelSortField !== 'data_abastecimento' || refuelSortDir !== 'DESC') {
                urlParams.set('sort', refuelSortField);
                urlParams.set('dir', refuelSortDir);
            } else {
                urlParams.delete('sort');
                urlParams.delete('dir');
            }
            const isDefault = refuelingCurrentPage === 1 && perPageValid === 10 && refuelSortField === 'data_abastecimento' && refuelSortDir === 'DESC';
            const desiredSearch = isDefault ? '' : '?' + urlParams.toString();
            if (window.location.search !== desiredSearch) {
                window.history.replaceState({}, '', window.location.pathname + desiredSearch);
            }

            const searchInput = document.getElementById('searchRefueling');
            const vehicleSelect = document.getElementById('vehicleFilter');
            const driverSelect = document.getElementById('driverFilter');
            const fuelSelect = document.getElementById('fuelFilter');
            const paymentSelect = document.getElementById('paymentFilter');

            const search = searchInput ? searchInput.value.trim() : '';
            const vehicleFilter = vehicleSelect ? vehicleSelect.value : '';
            const driverFilter = driverSelect ? driverSelect.value : '';
            const fuelFilter = fuelSelect ? fuelSelect.value : '';
            const paymentFilter = paymentSelect ? paymentSelect.value : '';
            
            let url = sfApiUrl(`refuel_data.php?action=list&page=${refuelingCurrentPage}&limit=${perPageValid}`);
            if (search) url += `&search=${encodeURIComponent(search)}`;
            if (currentFilter) {
                const [year, month] = currentFilter.split('-');
                url += `&year=${year}&month=${month}`;
            }
            if (vehicleFilter) url += `&veiculo=${encodeURIComponent(vehicleFilter)}`;
            if (driverFilter) url += `&motorista=${encodeURIComponent(driverFilter)}`;
            if (fuelFilter) url += `&combustivel=${encodeURIComponent(fuelFilter)}`;
            if (paymentFilter) url += `&pagamento=${encodeURIComponent(paymentFilter)}`;
            url += `&sort=${encodeURIComponent(refuelSortField)}&dir=${encodeURIComponent(refuelSortDir)}`;

            fetch(url, { credentials: 'include' })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        updateRefuelingsTable(data.data);
                        if (data.pagination) {
                            refuelingCurrentPage = data.pagination.page || refuelingCurrentPage;
                            updatePagination(data.pagination);
                            const updatedParams = new URLSearchParams(window.location.search);
                            updatedParams.set('page', refuelingCurrentPage);
                            updatedParams.set('per_page', perPageValid);
                            if (refuelSortField !== 'data_abastecimento' || refuelSortDir !== 'DESC') {
                                updatedParams.set('sort', refuelSortField);
                                updatedParams.set('dir', refuelSortDir);
                            } else {
                                updatedParams.delete('sort');
                                updatedParams.delete('dir');
                            }
                            const isDefaultResp = refuelingCurrentPage === 1 && perPageValid === 10 && refuelSortField === 'data_abastecimento' && refuelSortDir === 'DESC';
                            const desiredSearchResp = isDefaultResp ? '' : '?' + updatedParams.toString();
                            if (window.location.search !== desiredSearchResp) {
                                window.history.replaceState({}, '', window.location.pathname + desiredSearchResp);
                            }
                        }
                    } else {
                        throw new Error(data.error || 'Erro ao carregar dados dos abastecimentos');
                    }
                })
                .catch(error => {
                    if (loadingEl) loadingEl.classList.remove('is-visible');
                    if (containerEl) containerEl.classList.remove('table-loading-visible');
                    window.__SF_DEBUG__ && console.error('Erro ao carregar dados dos abastecimentos:', error);
                });
        }
        
        function updateRefuelingsTable(refuelings) {
            var tbody = document.getElementById('refuelingTableBody') ||
                document.querySelector('#refuelingTable tbody') ||
                document.querySelector('table.fornc-table tbody') ||
                document.querySelector('table.data-table tbody');
            if (!tbody) return;
            tbody.innerHTML = '';
            
            if (refuelings && refuelings.length > 0) {
                refuelings.forEach(refuel => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${formatDate(refuel.data_abastecimento)}</td>
                        <td>${refuel.veiculo_placa || '-'}</td>
                        <td>${refuel.motorista_nome || '-'}</td>
                        <td>${refuel.posto || '-'}</td>
                        <td>${formatNumber(refuel.litros, 1)} L</td>
                        <td>R$ ${formatNumber(refuel.valor_litro, 2)}</td>
                        <td>R$ ${formatNumber((parseFloat(refuel.valor_total || 0) + (refuel.inclui_arla == 1 ? parseFloat(refuel.valor_total_arla || 0) : 0)), 2)}</td>
                        <td class="col-arla">
                            ${refuel.inclui_arla == 1 ? 
                                (() => {
                                    const percentual = refuel.litros > 0 ? (refuel.litros_arla / refuel.litros) * 100 : 0;
                                    let classePercentual = '';
                                    if (percentual >= 3 && percentual <= 5) {
                                        classePercentual = 'percentual-ok';
                                    } else if (percentual > 5) {
                                        classePercentual = 'percentual-alto';
                                    } else {
                                        classePercentual = 'percentual-baixo';
                                    }
                                    const titleAttr = formatArlaPercentTitle(percentual).replace(/"/g, '&quot;');
                                    return `<span class="percentual-arla ${classePercentual}" title="${titleAttr}">${formatArlaPercentDisplay(percentual)}</span>`;
                                })() : 
                                '<span class="text-muted">-</span>'
                            }
                        </td>
                        <td>${formatNumber(refuel.km_atual, 0)}</td>
                        <td>${refuel.forma_pagamento || '-'}</td>
                        <td>${refuel.cidade_origem_nome || '-'} → ${refuel.cidade_destino_nome || '-'}</td>
                        <td class="actions">
                            <button class="btn-icon edit-btn" data-id="${refuel.id}" title="Editar" aria-label="Editar abastecimento">
                                <i class="fas fa-edit"></i>
                            </button>
                            ${refuel.comprovante ? `
                                <button class="btn-icon view-comprovante-btn" data-comprovante="${refuel.comprovante}" title="Ver Comprovante" aria-label="Ver comprovante">
                                    <i class="fas fa-file-alt"></i>
                                </button>
                            ` : ''}
                            <button class="btn-icon delete-btn" data-id="${refuel.id}" title="Excluir" aria-label="Excluir abastecimento">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
                
                // Configura eventos dos botões
                setupTableButtons();
            } else {
                tbody.innerHTML = '<tr><td colspan="12" class="text-center">Nenhum abastecimento encontrado</td></tr>';
            }
        }
        
        function updatePagination(pagination) {
            if (!pagination) {
                return;
            }

            const totalPages = Math.max(1, pagination.totalPages || 1);
            const current = Math.min(Math.max(1, pagination.page || 1), totalPages);
            refuelingCurrentPage = current;

            const paginationContainer = document.querySelector('.pagination');
            if (!paginationContainer) return;
            
            const prevBtn = paginationContainer.querySelector('a:first-child');
            const nextBtn = paginationContainer.querySelector('a:last-child');
            const paginationInfo = paginationContainer.querySelector('.pagination-info');
            
            const perPageSelect = document.getElementById('perPageRefuel');
            const perPage = perPageSelect ? parseInt(perPageSelect.value, 10) : 10;
            const perPageParam = [5, 10, 25, 50, 100].indexOf(perPage) >= 0 ? perPage : 10;
            
            if (paginationInfo) {
                const total = pagination.total || (current * perPageParam);
                paginationInfo.textContent = totalPages > 1 
                    ? `Página ${current} de ${totalPages} (${total} registros)` 
                    : `${total} registros`;
            }
            
            const prevPage = Math.max(1, current - 1);
            const nextPageValue = Math.min(totalPages, current + 1);
            
            if (prevBtn) {
                const isDisabled = current <= 1;
                prevBtn.classList.toggle('disabled', isDisabled);
                prevBtn.href = `?page=${prevPage}&per_page=${perPageParam}`;
                prevBtn.setAttribute('data-page', prevPage);
                prevBtn.onclick = function(event) {
                    event.preventDefault();
                    if (isDisabled) return;
                    loadRefuelingData(prevPage);
                };
            }
            
            if (nextBtn) {
                const isDisabled = current >= totalPages;
                nextBtn.classList.toggle('disabled', isDisabled);
                nextBtn.href = `?page=${nextPageValue}&per_page=${perPageParam}`;
                nextBtn.setAttribute('data-page', nextPageValue);
                nextBtn.onclick = function(event) {
                    event.preventDefault();
                    if (isDisabled) return;
                    loadRefuelingData(nextPageValue);
                };
            }
        }
        
        function loadRefuelingSummary() {
            let url = sfApiUrl('refuel_data.php?action=summary');
            
            if (currentFilter) {
                const [year, month] = currentFilter.split('-');
                url += `&year=${year}&month=${month}`;
            }

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Erro ao carregar resumo dos abastecimentos');
                    }

                    // Atualiza os cards de métricas com os dados filtrados
                    updateMetricCards(data.data);
                })
                .catch(error => {
                    window.__SF_DEBUG__ && console.error('Error loading refueling summary:', error);
                });
        }

        function loadFilterOptions() {
            var cacheKey = 'abastecimentos_filter_options';
            var cacheTtl = 5 * 60 * 1000; // 5 minutos
            try {
                var cached = sessionStorage.getItem(cacheKey);
                if (cached) {
                    var parsed = JSON.parse(cached);
                    if (parsed && parsed.ts && (Date.now() - parsed.ts < cacheTtl) && parsed.data) {
                        var data = { success: true, data: parsed.data };
                        applyFilterOptionsData(data);
                        return;
                    }
                }
            } catch (e) {}
            fetch(sfApiUrl('refuel_data.php?action=filter_options'), { credentials: 'include' })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.data) {
                        try {
                            sessionStorage.setItem(cacheKey, JSON.stringify({ data: data.data, ts: Date.now() }));
                        } catch (e) {}
                        applyFilterOptionsData(data);
                    }
                })
                .catch(error => {
                    window.__SF_DEBUG__ && console.error('Erro ao carregar opções de filtro:', error);
                });
        }
        
        function applyFilterOptionsData(data) {
                    if (!data.success || !data.data) return;
                    const { vehicles = [], drivers = [] } = data.data;

                    const vehicleFilter = document.getElementById('vehicleFilter');
                    if (vehicleFilter) {
                        const currentValue = vehicleFilter.value;
                        vehicleFilter.innerHTML = '<option value=\"\">Todos os veículos</option>';
                        vehicles.forEach(vehicle => {
                            const option = document.createElement('option');
                            option.value = vehicle.id;
                            option.textContent = vehicle.placa + (vehicle.modelo ? ` - ${vehicle.modelo}` : '');
                            vehicleFilter.appendChild(option);
                        });
                        if (currentValue) {
                            vehicleFilter.value = currentValue;
                        }
                    }

                    const driverFilter = document.getElementById('driverFilter');
                    if (driverFilter) {
                        const currentValue = driverFilter.value;
                        driverFilter.innerHTML = '<option value=\"\">Todos os motoristas</option>';
                        drivers.forEach(driver => {
                            const option = document.createElement('option');
                            option.value = driver.id;
                            option.textContent = driver.nome;
                            driverFilter.appendChild(option);
                        });
                        if (currentValue) {
                            driverFilter.value = currentValue;
                        }
                    }
        }

        function updateMetricCards(data) {
            function setText(id, text) {
                var el = document.getElementById(id);
                if (el) el.textContent = text;
            }
            setText('refuelKpiAbastecimentos', formatNumber(data.total_abastecimentos, 0));
            setText('refuelKpiLitros', formatNumber(data.total_litros, 2) + ' L');
            setText('refuelKpiValor', 'R$ ' + formatNumber(data.total_gasto, 2));
            setText('refuelKpiMediaLitro', 'R$ ' + formatNumber(data.media_valor_litro, 2) + '/L');
        }
        
        function loadConsumptionChart() {
            let url = sfApiUrl('refuel_data.php?action=consumption_chart');
            
            if (currentFilter) {
                const [year, month] = currentFilter.split('-');
                url += `&year=${year}&month=${month}`;
            }

            return fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Erro ao carregar dados do gráfico de consumo');
                    }

                    // Atualiza o gráfico de consumo com os dados filtrados
                    if (consumptionChart) {
                        consumptionChart.destroy();
                    }

                    const ctx = document.getElementById('fuelConsumptionChart').getContext('2d');
                    consumptionChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Consumo de Combustível (L)',
                                data: data.values,
                                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                borderColor: 'rgba(54, 162, 235, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    window.__SF_DEBUG__ && console.error('Error loading consumption chart:', error);
                });
        }
        
        function loadEfficiencyChart() {
            let url = sfApiUrl('refuel_data.php?action=efficiency_chart');
            
            if (currentFilter) {
                const [year, month] = currentFilter.split('-');
                url += `&year=${year}&month=${month}`;
            }

            return fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Erro ao carregar dados do gráfico de eficiência');
                    }

                    // Atualiza o gráfico de eficiência com os dados filtrados
                    if (efficiencyChart) {
                        efficiencyChart.destroy();
                    }

                    const ctx = document.getElementById('fuelEfficiencyChart').getContext('2d');
                    efficiencyChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Eficiência (km/L)',
                                data: data.values,
                                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                borderColor: 'rgba(75, 192, 192, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    window.__SF_DEBUG__ && console.error('Error loading efficiency chart:', error);
                });
        }
        
        function setupTableButtons() {
            // Setup edit buttons
            document.querySelectorAll('.btn-icon.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    showEditRefuelModal(id);
                });
            });
            
            // Setup delete buttons
            document.querySelectorAll('.btn-icon.delete-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    showDeleteConfirmation(id);
                });
            });
        }
        
        function showDeleteConfirmation(refuelId) {
            fetch(sfApiUrl(`refuel_data.php?action=get&id=${refuelId}`))
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(data => {
                            throw new Error(data.error || 'Erro ao carregar dados do abastecimento');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Erro ao carregar dados do abastecimento');
                    }
                    const refuel = data.data;
                    var msgEl = document.getElementById('deleteConfirmMessage');
                    if (msgEl) {
                        msgEl.textContent = 'Deseja realmente excluir o abastecimento do veículo ' + (refuel.veiculo_placa || '') + ' realizado em ' + formatDate(refuel.data_abastecimento) + '?';
                    }
                    pendingDeleteId = refuelId;
                    var modal = document.getElementById('deleteConfirmModal');
                    if (modal) modal.classList.add('active');
                })
                .catch(error => {
                    window.__SF_DEBUG__ && console.error('Error loading refuel data:', error);
                    showToast('Erro ao carregar dados do abastecimento: ' + error.message, 'error');
                });
        }
        
        function deleteRefuel(refuelId) {
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', refuelId);
            if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__) {
                fd.append('csrf_token', window.__SF_CSRF__);
            }
            fetch(sfApiUrl('refuel_actions.php'), {
                method: 'POST',
                body: fd,
                credentials: 'include',
                headers: typeof sfMutationHeaders === 'function' ? sfMutationHeaders() : {}
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.error || 'Erro ao excluir abastecimento');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    throw new Error(data.error || 'Erro ao excluir abastecimento');
                }
                showToast(data.message || 'Abastecimento excluído.', 'success');
                loadRefuelingData();
                loadRefuelingSummary();
                loadConsumptionChart();
                loadEfficiencyChart();
                if (typeof loadAnomaliesChart === 'function') {
                    loadAnomaliesChart();
                    loadDriverConsumptionChart();
                    loadVehicleEfficiencyChart();
                    loadMonthlyCostChart();
                }
            })
            .catch(error => {
                window.__SF_DEBUG__ && console.error('Error deleting refuel:', error);
                showToast('Erro ao excluir abastecimento: ' + error.message, 'error');
            });
        }
        
        // Funções auxiliares
        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString.replace(' ', 'T'));
            return date.toLocaleDateString('pt-BR');
        }
        
        function formatNumber(value, decimals = 0) {
            if (!value) return '0';
            return Number(value).toLocaleString('pt-BR', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        }

        function formatArlaPercentDisplay(percentual) {
            var p = Number(percentual);
            if (!isFinite(p)) return '0%';
            if (Math.abs(p) >= 100) {
                return Math.round(p).toLocaleString('pt-BR', { maximumFractionDigits: 0, useGrouping: false }) + '%';
            }
            return p.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1, useGrouping: false }) + '%';
        }

        function formatArlaPercentTitle(percentual) {
            var p = Number(percentual);
            if (!isFinite(p)) return '';
            return 'Percentual ARLA (litros ARLA ÷ diesel): ' +
                p.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 4, useGrouping: false }) + '%';
        }
        
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        function setupModals() {
            const closeButtons = document.querySelectorAll('.close-modal');
            closeButtons.forEach(button => {
                button.addEventListener('click', closeAllModals);
            });
            
            document.getElementById('cancelRefuelBtn').addEventListener('click', closeAllModals);
            
            // Fechar qualquer modal ao clicar no overlay (fora do .modal-content)
            document.querySelectorAll('.modal').forEach(function(modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeAllModals();
                    }
                });
            });
        }
        
        function showAddRefuelModal() {
            document.getElementById('refuelForm').reset();
            document.getElementById('refuelId').value = '';
            document.getElementById('modalTitle').textContent = 'Adicionar Abastecimento';
            document.getElementById('refuelModal').classList.add('active');
            
            // Set current date and time
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            const dateInput = document.getElementById('data_abastecimento');
            dateInput.value = now.toISOString().slice(0, 16);
            
            // Reset and disable fields initially
            const veiculo = document.getElementById('veiculo_id');
            const motorista = document.getElementById('motorista_id');
            const rota = document.getElementById('rota_id');
            
            veiculo.value = '';
            motorista.value = '';
            rota.value = '';
            
            // Setup automatic total calculation
            setupValorTotalCalc();
            
            // Setup ARLA fields
            setupArlaFields();
            
            // Setup numeric inputs
            setupNumericInputs();
        }
        
        function showEditRefuelModal(refuelId) {
            document.getElementById('refuelForm').reset();
            // Carrega os dados completos do abastecimento
            fetch(sfApiUrl(`refuel_data.php?action=get&id=${refuelId}`))
                .then(response => response.json())
                .then(data => {
                    if (!data.success) throw new Error(data.error || 'Erro ao carregar dados do abastecimento');
                    // Chama a função global do JS externo para preencher e abrir o modal corretamente
                    window.openEditRefuelModal(data.data);
                })
                .catch(error => {
                    showToast('Erro ao carregar dados do abastecimento: ' + error.message, 'error');
                });
        }
        
        function setupValorTotalCalc() {
            const litrosInput = document.getElementById('litros');
            const valorLitroInput = document.getElementById('valor_litro');
            const valorTotalInput = document.getElementById('valor_total');
            
            function calcularValorTotal() {
                const litros = parseFloat(litrosInput.value.replace(/\./g, '').replace(',', '.')) || 0;
                const valorLitro = parseFloat(valorLitroInput.value.replace(/\./g, '').replace(',', '.')) || 0;
                const valorTotal = litros * valorLitro;
                
                if (!isNaN(valorTotal)) {
                    valorTotalInput.value = valorTotal.toLocaleString('pt-BR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            }
            
            litrosInput.addEventListener('input', calcularValorTotal);
            valorLitroInput.addEventListener('input', calcularValorTotal);
        }
        
        function setupArlaFields() {
            const incluiArlaCheckbox = document.getElementById('inclui_arla');
            const camposArla = document.getElementById('campos_arla');
            const litrosArlaInput = document.getElementById('litros_arla');
            const valorLitroArlaInput = document.getElementById('valor_litro_arla');
            const valorTotalArlaInput = document.getElementById('valor_total_arla');
            
            // Controla a exibição dos campos ARLA
            incluiArlaCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    camposArla.style.display = 'grid';
                    // Torna os campos obrigatórios quando ARLA está marcado
                    litrosArlaInput.required = true;
                    valorLitroArlaInput.required = true;
                } else {
                    camposArla.style.display = 'none';
                    // Remove obrigatoriedade e limpa os campos
                    litrosArlaInput.required = false;
                    valorLitroArlaInput.required = false;
                    valorTotalArlaInput.required = false;
                    litrosArlaInput.value = '';
                    valorLitroArlaInput.value = '';
                    valorTotalArlaInput.value = '';
                }
            });
            
            // Cálculo automático do valor total ARLA
            function calcularValorTotalArla() {
                const litros = parseFloat(litrosArlaInput.value.replace(/\./g, '').replace(',', '.')) || 0;
                const valorLitro = parseFloat(valorLitroArlaInput.value.replace(/\./g, '').replace(',', '.')) || 0;
                const valorTotal = litros * valorLitro;
                
                if (!isNaN(valorTotal)) {
                    valorTotalArlaInput.value = valorTotal.toLocaleString('pt-BR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                }
            }
            
            litrosArlaInput.addEventListener('input', calcularValorTotalArla);
            valorLitroArlaInput.addEventListener('input', calcularValorTotalArla);
        }
        
        function closeAllModals() {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.classList.remove('active');
            });
        }
        
        function setupNumericInputs() {
            const numericInputs = document.querySelectorAll('.numeric-input');
            
            numericInputs.forEach(input => {
                // Formata o valor inicial se houver
                if (input.value) {
                    const numericValue = parseFloat(input.value.replace(',', '.'));
                    if (!isNaN(numericValue)) {
                        input.value = numericValue.toLocaleString('pt-BR', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                }
                
                input.addEventListener('input', function(e) {
                    let value = e.target.value;
                    
                    // Remove tudo exceto números, vírgula e ponto
                    value = value.replace(/[^\d,.]/g, '');
                    
                    // Remove todos os pontos e substitui vírgula por ponto temporariamente
                    value = value.replace(/\./g, '');
                    
                    // Garante apenas uma vírgula
                    const parts = value.split(',');
                    if (parts.length > 2) {
                        value = parts[0] + ',' + parts.slice(1).join('');
                    }
                    
                    // Limita a 2 casas decimais
                    if (parts[1] && parts[1].length > 2) {
                        value = parts[0] + ',' + parts[1].slice(0, 2);
                    }
                    
                    // Atualiza o valor do campo
                    e.target.value = value;
                });
                
                input.addEventListener('blur', function(e) {
                    let value = e.target.value;
                    
                    if (value) {
                        // Converte para número (troca vírgula por ponto)
                        const numericValue = parseFloat(value.replace(/\./g, '').replace(',', '.'));
                        if (!isNaN(numericValue)) {
                            // Formata o número de volta para exibição
                            e.target.value = numericValue.toLocaleString('pt-BR', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }
                    }
                });
            });
        }

        function showHelpModal() {
            document.getElementById('helpModal').classList.add('active');
        }

        function setupFilters() {
            const filterMonthInput = document.getElementById('filterMonth');
            if (filterMonthInput) {
                filterMonthInput.value = '';
            }
            currentFilter = null;
            updateFilterButtonState();

            // Setup filter modal buttons
            document.getElementById('applyFilterBtn').addEventListener('click', () => {
                const filterMonth = document.getElementById('filterMonth').value;
                currentFilter = filterMonth;
                refuelingCurrentPage = 1;
                loadRefuelingData(1);
                loadRefuelingSummary();
                loadConsumptionChart();
                loadEfficiencyChart();
                if (typeof loadAnomaliesChart === 'function') {
                    loadAnomaliesChart();
                    loadDriverConsumptionChart();
                    loadVehicleEfficiencyChart();
                    loadMonthlyCostChart();
                }
                closeAllModals();
                updateFilterButtonState();
            });

            document.getElementById('clearFilterBtn').addEventListener('click', () => {
                document.getElementById('filterMonth').value = '';
                currentFilter = null;
                refuelingCurrentPage = 1;
                loadRefuelingData(1);
                loadRefuelingSummary();
                loadConsumptionChart();
                loadEfficiencyChart();
                if (typeof loadAnomaliesChart === 'function') {
                    loadAnomaliesChart();
                    loadDriverConsumptionChart();
                    loadVehicleEfficiencyChart();
                    loadMonthlyCostChart();
                }
                closeAllModals();
                updateFilterButtonState();
            });
        }
        
        function updateFilterButtonState() {
            const filterBtn = document.getElementById('filterBtn');
            if (currentFilter) {
                filterBtn.classList.add('active');
                filterBtn.title = `Filtro: ${formatMonthYear(currentFilter)}`;
            } else {
                filterBtn.classList.remove('active');
                filterBtn.title = 'Filtros';
            }
        }

        function formatMonthYear(dateString) {
            const [year, month] = dateString.split('-');
            const date = new Date(year, month - 1);
            return date.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
        }

        function showFilterModal() {
            document.getElementById('filterModal').classList.add('active');
        }

        // Torna as funções globais para o JS externo
        window.loadRefuelingData = loadRefuelingData;
        window.loadRefuelingSummary = loadRefuelingSummary;
        window.loadConsumptionChart = loadConsumptionChart;
        window.loadEfficiencyChart = loadEfficiencyChart;

        // ===== VALIDAÇÃO DE QUILOMETRAGEM PARA ABASTECIMENTOS =====
        
        // Função para validar quilometragem do abastecimento
        async function validarKmAbastecimento(rotaId, kmAbastecimento) {
            if (!rotaId || !kmAbastecimento) {
                return { valido: false, mensagem: 'Dados insuficientes para validação' };
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'validar_km_abastecimento');
                formData.append('rota_id', rotaId);
                formData.append('km_abastecimento', kmAbastecimento);
                
                const response = await fetch(sfApiUrl('validar_quilometragem.php'), {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                return data;
            } catch (error) {
                window.__SF_DEBUG__ && console.error('Erro na validação de quilometragem:', error);
                return { valido: false, mensagem: 'Erro na validação' };
            }
        }
        
        // Função para obter informações de abastecimentos da rota
        async function obterAbastecimentosRota(rotaId) {
            if (!rotaId) return null;
            
            try {
                const response = await fetch(sfApiUrl(`validar_quilometragem.php?action=obter_abastecimentos_rota&rota_id=${rotaId}`));
                const data = await response.json();
                return data.success ? data : null;
            } catch (error) {
                window.__SF_DEBUG__ && console.error('Erro ao obter abastecimentos da rota:', error);
                return null;
            }
        }
        
        // Configurar validação quando rota for selecionada
        function configurarValidacaoKmAbastecimento() {
            const rotaSelect = document.getElementById('rota_id');
            const kmAtualInput = document.getElementById('km_atual');
            const kmAtualHelp = document.getElementById('km_atual_help');
            const kmAtualValidation = document.getElementById('km_atual_validation');
            
            if (!rotaSelect || !kmAtualInput) return;
            
            // Quando rota for selecionada
            rotaSelect.addEventListener('change', async function() {
                const rotaId = this.value;
                
                if (rotaId) {
                    const dadosRota = await obterAbastecimentosRota(rotaId);
                    if (dadosRota) {
                        let helpText = `<i class="fas fa-info-circle"></i> KM Saída da rota: ${dadosRota.km_saida_rota.toLocaleString('pt-BR')} km`;
                        let minValue = dadosRota.km_saida_rota + 1;
                        
                        if (dadosRota.total_abastecimentos > 0) {
                            helpText += `<br><i class="fas fa-gas-pump"></i> Último abastecimento: ${dadosRota.km_ultimo_abastecimento.toLocaleString('pt-BR')} km`;
                            helpText += `<br><i class="fas fa-list"></i> Total de abastecimentos: ${dadosRota.total_abastecimentos}`;
                            minValue = dadosRota.km_ultimo_abastecimento + 1;
                        } else {
                            helpText += `<br><i class="fas fa-plus"></i> Primeiro abastecimento da rota`;
                        }
                        
                        helpText += `<br><i class="fas fa-exclamation-triangle"></i> Quilometragem deve ser maior que ${minValue.toLocaleString('pt-BR')} km`;
                        
                        kmAtualHelp.innerHTML = helpText;
                        kmAtualInput.placeholder = `Mín: ${minValue.toLocaleString('pt-BR')}`;
                        kmAtualInput.min = minValue;
                    }
                } else {
                    kmAtualHelp.innerHTML = '<i class="fas fa-info-circle"></i> Selecione uma rota para validar a quilometragem';
                    kmAtualInput.placeholder = 'Ex: 150000';
                    kmAtualInput.min = '';
                }
                
                // Limpar validação anterior
                kmAtualValidation.innerHTML = '';
            });
            
            // Quando quilometragem for digitada
            kmAtualInput.addEventListener('blur', async function() {
                const rotaId = rotaSelect.value;
                const kmAbastecimento = this.value;
                
                if (rotaId && kmAbastecimento) {
                    const validacao = await validarKmAbastecimento(rotaId, kmAbastecimento);
                    
                    if (validacao.valido) {
                        kmAtualValidation.innerHTML = `<div style="color: #28a745;"><i class="fas fa-check-circle"></i> ${validacao.mensagem}</div>`;
                        kmAtualInput.style.borderColor = '#28a745';
                    } else {
                        kmAtualValidation.innerHTML = `<div style="color: #dc3545;"><i class="fas fa-exclamation-triangle"></i> ${validacao.mensagem}</div>`;
                        kmAtualInput.style.borderColor = '#dc3545';
                    }
                } else {
                    kmAtualValidation.innerHTML = '';
                    kmAtualInput.style.borderColor = '';
                }
            });
        }
        
        // Inicializar validação quando DOM estiver carregado
        document.addEventListener('DOMContentLoaded', function() {
            // Evita "race condition" sem depender de setTimeout fixo
            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    configurarValidacaoKmAbastecimento();
                });
            });
        });
    </script>
    <script src="../js/abastecimentos.js"></script>

    <?php include '../includes/scroll_to_top.php'; ?>
</body>
</html>