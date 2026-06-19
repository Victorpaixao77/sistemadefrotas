<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once __DIR__ . '/../includes/sf_page_help.php';
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
    <?php sf_page_help_css('..'); ?>
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
                            <button type="button" id="refuelImportBtn" class="btn-toggle-layout" title="Importar linhas de abastecimento (CSV/JSON)" aria-label="Importar abastecimentos de arquivo">
                                <i class="fas fa-file-import"></i>
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
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-gas-pump fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Abastecimentos</span></div><span class="val" id="refuelKpiAbastecimentos">0</span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-droplet fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Litros (mês)</span></div><span class="val" id="refuelKpiLitros">0</span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-money-bill-wave fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Valor (mês)</span></div><span class="val" id="refuelKpiValor">R$ 0,00</span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-chart-simple fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Média R$/L</span></div><span class="val" id="refuelKpiMediaLitro">R$ 0,00/L</span></div>
                </div>
                <?php else: ?>
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <i class="fas fa-gas-pump kpi-card-icon" aria-hidden="true"></i>
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
                            <i class="fas fa-droplet kpi-card-icon" aria-hidden="true"></i>
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
                            <i class="fas fa-sack-dollar kpi-card-icon" aria-hidden="true"></i>
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
                            <i class="fas fa-calculator kpi-card-icon" aria-hidden="true"></i>
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
                        <button type="button" id="refuelImportBtn" class="fornc-btn fornc-btn--ghost" title="Importar CSV/JSON (planilha de frete)"><i class="fas fa-file-import"></i> Importar</button>
                        <button type="button" class="fornc-btn fornc-btn--accent" id="applyRefuelFilters" title="Aplicar filtros"><i class="fas fa-search"></i> Pesquisar</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="filterBtn" title="Filtro por mês/ano"><i class="fas fa-sliders-h"></i> Opções</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="clearRefuelFilters" title="Limpar filtros"><i class="fas fa-undo"></i></button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="refuelColumnsBtn" title="Colunas da tabela" aria-label="Colunas da tabela"><i class="fas fa-columns"></i></button>
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
                <?php
                $refuel_modern_empty_initial = !empty($is_modern) && empty($abastecimentos);
                ?>
                <?php if (!empty($is_modern)): ?>
                <div class="fornc-empty-state<?php echo $refuel_modern_empty_initial ? ' visible' : ''; ?>" id="refuelEmptyState" role="status" aria-live="polite">
                    <p class="fornc-empty-state-title">Nenhum abastecimento encontrado</p>
                    <p class="fornc-empty-state-hint">Ajuste o período ou os filtros, ou registre um novo abastecimento.</p>
                    <button type="button" class="fornc-btn fornc-btn--primary" id="refuelEmptyCreateBtn"><i class="fas fa-plus"></i> Registrar primeiro abastecimento</button>
                </div>
                <?php endif; ?>

                <!-- Painel de anomalias/fraude de abastecimento -->
                <div class="ab-anomalies" id="abAnomalies" style="display:none;">
                    <div class="ab-head">
                        <button type="button" class="ab-toggle" id="abToggle" aria-expanded="false" aria-controls="abList">
                            <span class="ab-summary" id="abSummary"></span>
                            <i class="fas fa-chevron-down ab-caret" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon ab-refresh" id="abRefresh" title="Reanalisar abastecimentos" aria-label="Reanalisar abastecimentos"><i class="fas fa-sync-alt"></i></button>
                    </div>
                    <div class="ab-list" id="abList" style="display:none;"></div>
                </div>

                <!-- Refueling Table -->
                <div class="<?php echo $is_modern ? 'fornc-table-wrap refuel-table-wrap' : 'data-table-container'; ?>" id="refuelTableContainer"<?php echo $refuel_modern_empty_initial ? ' style="display:none"' : ''; ?>>
                    <div class="table-loading" id="refuelTableLoading" aria-live="polite">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Carregando abastecimentos...</span>
                    </div>
                    <table class="<?php echo $is_modern ? 'fornc-table' : 'data-table'; ?>" id="refuelingTable">
                        <thead>
                            <tr>
                                <?php if ($is_modern): ?>
                                <th class="sortable sorted" data-sort="data_abastecimento" data-col="data">Data <span class="sort-ind">▼</span></th>
                                <th class="sortable" data-sort="veiculo_placa" data-col="veiculo">Veículo <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="motorista_nome" data-col="motorista">Motorista <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="posto" data-col="posto">Posto <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="litros" data-col="litros">Litros <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="valor_litro" data-col="valor_litro">Valor/L <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="valor_total" data-col="valor_total">Valor Total <span class="sort-ind">⇅</span></th>
                                <th class="col-arla sortable" data-sort="litros_arla" data-col="arla">ARLA <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="km_atual" data-col="km">Km <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="forma_pagamento" data-col="forma_pagamento">Forma Pgto <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="rota" data-col="rota">Rota <span class="sort-ind">⇅</span></th>
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
                                <th<?php echo $is_modern ? ' data-col="acoes"' : ''; ?>>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="refuelingTableBody">
                            <?php if (!empty($abastecimentos)): ?>
                                <?php foreach ($abastecimentos as $abastecimento): ?>
                                <tr>
                                    <td<?php echo $is_modern ? ' data-col="data"' : ''; ?>><?php echo date('d/m/Y', strtotime($abastecimento['data_abastecimento'])); ?></td>
                                    <td<?php echo $is_modern ? ' data-col="veiculo"' : ''; ?>><?php echo htmlspecialchars($abastecimento['veiculo_placa']); ?></td>
                                    <td<?php echo $is_modern ? ' data-col="motorista"' : ''; ?>><?php echo htmlspecialchars($abastecimento['motorista_nome']); ?></td>
                                    <td<?php echo $is_modern ? ' data-col="posto"' : ''; ?>><?php echo htmlspecialchars($abastecimento['posto']); ?></td>
                                    <td<?php echo $is_modern ? ' data-col="litros"' : ''; ?>><?php echo number_format($abastecimento['litros'], 3, ',', '.'); ?></td>
                                    <td<?php echo $is_modern ? ' data-col="valor_litro"' : ''; ?>>R$ <?php echo number_format($abastecimento['valor_litro'], 2, ',', '.'); ?></td>
                                    <?php
                                        $valor_total_abastecimento = (float)$abastecimento['valor_total'];
                                        if (!empty($abastecimento['inclui_arla']) && (int)$abastecimento['inclui_arla'] === 1) {
                                            $valor_total_abastecimento += (float)($abastecimento['valor_total_arla'] ?? 0);
                                        }
                                    ?>
                                    <td<?php echo $is_modern ? ' data-col="valor_total"' : ''; ?>>R$ <?php echo number_format($valor_total_abastecimento, 2, ',', '.'); ?></td>
                                    <td class="col-arla"<?php echo $is_modern ? ' data-col="arla"' : ''; ?>>
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
                                    <td<?php echo $is_modern ? ' data-col="km"' : ''; ?>><?php echo number_format($abastecimento['km_atual'], 0, ',', '.'); ?></td>
                                    <td<?php echo $is_modern ? ' data-col="forma_pagamento"' : ''; ?>><?php echo htmlspecialchars($abastecimento['forma_pagamento']); ?></td>
                                    <td<?php echo $is_modern ? ' data-col="rota"' : ''; ?>><?php 
                                        if (!empty($abastecimento['cidade_origem_nome']) && !empty($abastecimento['cidade_destino_nome'])) {
                                            echo htmlspecialchars($abastecimento['cidade_origem_nome'] . ' → ' . $abastecimento['cidade_destino_nome']);
                                        } else {
                                            echo '-';
                                        }
                                    ?></td>
                                    <td class="actions"<?php echo $is_modern ? ' data-col="acoes"' : ''; ?>>
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
                            <?php elseif (empty($is_modern)): ?>
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

    <!-- Importar abastecimentos (CSV/JSON — mesmo layout da coluna DATA/KM/POSTO/L/R$ do papel de frete) -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="refuelImportModal">
        <div class="modal-content<?php echo $is_modern ? ' modal-lg fornc-modal--wide' : ''; ?>">
            <div class="modal-header">
                <h2>Importar abastecimentos</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <p class="refuel-import-intro text-muted">
                    <strong>CSV / JSON:</strong> colunas <strong>data</strong>, <strong>km</strong>, <strong>posto</strong>, <strong>litros</strong>, <strong>valor_total</strong>
                    (<code>;</code> ou <code>,</code>; datas <code>19/03/2025</code> ou <code>2025-03-19</code>).
                </p>
                <p class="refuel-import-intro text-muted">
                    <strong>Foto ou PDF:</strong>
                    com chave do <strong>Google Vision</strong> (em Configurações) ou <code>SF_GOOGLE_VISION_API_KEY</code> no servidor, a leitura usa OCR em nuvem no backend; sem chave, cai para <strong>OCR no navegador</strong> (Tesseract). Revise sempre antes de salvar.
                </p>
                <p class="refuel-import-intro text-muted">
                    <a href="../examples/abastecimento_import_modelo.csv" download class="refuel-import-model-link"><i class="fas fa-download" aria-hidden="true"></i> Modelo CSV</a>
                    ·
                    <a href="../examples/abastecimento_import_modelo.json" download class="refuel-import-model-link"><i class="fas fa-download" aria-hidden="true"></i> Modelo JSON</a>
                </p>
                <div class="form-group">
                    <label for="refuelImportFile">Arquivo</label>
                    <input type="file" id="refuelImportFile" accept=".csv,.json,.pdf,image/*,text/csv,application/json,application/pdf">
                </div>
                <p id="refuelImportProgress" class="refuel-import-progress" role="status" aria-live="polite"></p>
                <p id="refuelImportError" class="refuel-import-error" role="alert"></p>
                <details class="refuel-import-ocr-details" id="refuelImportOcrWrap" hidden>
                    <summary>Texto extraído (OCR / PDF)</summary>
                    <pre id="refuelImportOcrRaw" class="refuel-import-ocr-pre"></pre>
                </details>
                <div class="refuel-import-shared" id="refuelImportShared" hidden>
                    <p class="refuel-import-intro text-muted" style="margin-bottom:.5rem">
                        <strong>Aplicar a todas as linhas:</strong> o comprovante não traz estes campos — selecione uma vez.
                    </p>
                    <div class="refuel-import-shared-grid">
                        <div class="form-group">
                            <label for="impVeiculo">Veículo</label>
                            <select id="impVeiculo"><option value="">Selecione…</option></select>
                        </div>
                        <div class="form-group">
                            <label for="impMotorista">Motorista</label>
                            <select id="impMotorista"><option value="">Selecione…</option></select>
                        </div>
                        <div class="form-group">
                            <label for="impRota">Rota</label>
                            <select id="impRota"><option value="">Selecione veículo e motorista…</option></select>
                        </div>
                        <div class="form-group">
                            <label for="impCombustivel">Combustível</label>
                            <select id="impCombustivel"><option value="">Selecione…</option></select>
                        </div>
                        <div class="form-group">
                            <label for="impFormaPgto">Forma de pagamento</label>
                            <select id="impFormaPgto">
                                <option value="Dinheiro">Dinheiro</option>
                                <option value="PIX">PIX</option>
                                <option value="Cartão">Cartão</option>
                                <option value="Boleto">Boleto</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="refuel-import-preview-wrap">
                    <table class="data-table refuel-import-preview" id="refuelImportPreviewTable">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>KM</th>
                                <th>Posto</th>
                                <th>Litros</th>
                                <th>Valor R$</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="refuelImportPreviewBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <span id="refuelImportResult" class="refuel-import-result" aria-live="polite" style="margin-right:auto"></span>
                <button type="button" class="btn-secondary close-modal">Fechar</button>
                <button type="button" class="btn-primary" id="refuelImportSaveBtn" disabled><i class="fas fa-file-import"></i> Importar</button>
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
    <?php $sf_help_key = 'abastecimentos'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>


    <?php if (!empty($is_modern)): ?>
    <div class="modal fornc-modal fornc-columns-modal" id="refuelColumnsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Colunas da tabela</h2>
                <span class="close-modal" title="Fechar">&times;</span>
            </div>
            <div class="modal-body">
                <p class="text-muted" style="font-size:0.8rem; margin-top:0;">Marque as colunas desejadas e clique em <strong>Salvar preferências</strong>.</p>
                <div id="refuelColumnsToggleList">
                    <div class="col-toggle-row col-toggle-section">Colunas</div>
                    <label class="col-toggle-row"><span>Data</span><input type="checkbox" data-col-toggle="data" checked></label>
                    <label class="col-toggle-row"><span>Veículo</span><input type="checkbox" data-col-toggle="veiculo" checked></label>
                    <label class="col-toggle-row"><span>Motorista</span><input type="checkbox" data-col-toggle="motorista" checked></label>
                    <label class="col-toggle-row"><span>Posto</span><input type="checkbox" data-col-toggle="posto" checked></label>
                    <label class="col-toggle-row"><span>Litros</span><input type="checkbox" data-col-toggle="litros" checked></label>
                    <label class="col-toggle-row"><span>Valor/L</span><input type="checkbox" data-col-toggle="valor_litro" checked></label>
                    <label class="col-toggle-row"><span>Valor total</span><input type="checkbox" data-col-toggle="valor_total" checked></label>
                    <label class="col-toggle-row"><span>ARLA</span><input type="checkbox" data-col-toggle="arla" checked></label>
                    <label class="col-toggle-row"><span>Km</span><input type="checkbox" data-col-toggle="km" checked></label>
                    <label class="col-toggle-row"><span>Forma pgto.</span><input type="checkbox" data-col-toggle="forma_pagamento" checked></label>
                    <label class="col-toggle-row"><span>Rota</span><input type="checkbox" data-col-toggle="rota" checked></label>
                    <label class="col-toggle-row"><span>Ações</span><input type="checkbox" data-col-toggle="acoes" checked></label>
                </div>
            </div>
            <div class="modal-footer columns-modal-footer">
                <button type="button" class="btn-secondary btn-columns-close close-modal">Fechar</button>
                <button type="button" class="btn-primary btn-columns-save" id="refuelColumnsSaveBtn">Salvar preferências</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- JavaScript Files -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <?php if (!empty($is_modern)): ?><script src="../js/fornc-table-columns.js"></script><?php endif; ?>
    <script src="../js/sf_insights.js?v=1.3"></script>
    <script src="../js/abastecimentos.js?v=1.1.1"></script>
    <script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/legacy/build/pdf.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
    <script src="../js/refuel-import.js?v=<?php echo htmlspecialchars(sf_asset_v(), ENT_QUOTES, 'UTF-8'); ?>"></script>

    <?php include '../includes/scroll_to_top.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>