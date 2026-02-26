<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';

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

// Função para buscar rotas do banco de dados
function getRotas($page = 1, $per_page = 10) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        $limit = in_array($per_page, [5, 10, 25, 50, 100], true) ? $per_page : 10;
        $offset = ($page - 1) * $limit;
        
        // Primeiro, conta o total de registros
        $sql_count = "SELECT COUNT(*) as total FROM rotas WHERE empresa_id = :empresa_id AND status = 'aprovado'";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_count->execute();
        $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Consulta paginada
        $sql = "SELECT r.*, v.placa as veiculo_placa, m.nome as motorista_nome,
                co.nome as cidade_origem_nome, cd.nome as cidade_destino_nome
                FROM rotas r
                LEFT JOIN veiculos v ON r.veiculo_id = v.id
                LEFT JOIN motoristas m ON r.motorista_id = m.id
                LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                WHERE r.empresa_id = :empresa_id
                AND r.status = 'aprovado'
                ORDER BY r.data_saida DESC, r.id DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'rotas' => $result,
            'total' => $total,
            'pagina_atual' => $page,
            'total_paginas' => ceil($total / $limit)
        ];
    } catch(PDOException $e) {
        error_log("Erro ao buscar rotas: " . $e->getMessage());
        return [
            'rotas' => [],
            'total' => 0,
            'pagina_atual' => 1,
            'total_paginas' => 1
        ];
    }
}

// Pegar a página atual da URL ou definir como 1
$pagina_atual = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Buscar rotas com paginação
$resultado = getRotas($pagina_atual, $per_page);
$rotas = $resultado['rotas'];
$total_paginas = $resultado['total_paginas'];
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
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../logo.png">
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Route Profitability Analysis -->
    <script src="../js/route-profitability.js"></script>
    <style>
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

        .filter-options .filter-label {
            font-size: 0.9rem;
            color: var(--text-color);
            margin-right: 0.25rem;
        }
        .filter-options .filter-per-page {
            padding: 6px 10px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: var(--bg-secondary);
            color: var(--text-color);
            font-size: 0.9rem;
        }

        /* Estilos específicos para o modal de ajuda */
        #helpRouteModal .modal-content {
            width: 80%;
            max-width: 900px;
            max-height: 85vh;
            overflow-y: auto;
        }

        #helpRouteModal .help-section {
            margin-bottom: 30px;
            padding: 0 20px;
        }

        #helpRouteModal h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 1.3em;
        }

        #helpRouteModal p {
            line-height: 1.6;
            margin-bottom: 15px;
            font-size: 1.1em;
        }

        #helpRouteModal ul {
            padding-left: 20px;
            margin-bottom: 15px;
        }

        #helpRouteModal li {
            margin-bottom: 12px;
            line-height: 1.5;
            font-size: 1.1em;
        }

        #helpRouteModal strong {
            color: var(--primary-color);
        }

        #helpRouteModal .modal-body {
            padding: 25px;
        }

        #helpRouteModal .modal-header {
            padding: 20px 25px;
        }

        #helpRouteModal .modal-footer {
            padding: 20px 25px;
        }

        /* Estilos para a seção de análise */
        .analytics-section {
            margin-top: 20px;
        }
        
        .analytics-section .section-header {
            margin-bottom: 20px;
        }
        
        .analytics-section .section-header h2 {
            font-size: 1.25rem;
            color: var(--text-primary);
            margin: 0;
        }
        
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .analytics-card {
            background: var(--bg-secondary);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        
        .analytics-card .card-header {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .analytics-card .card-header h3 {
            margin: 0;
            font-size: 1rem;
            color: var(--text-primary);
        }
        
        .analytics-card .card-body {
            padding: 15px;
            height: 300px;
            position: relative;
        }

        .analytics-card.full-width {
            grid-column: 1 / -1;
        }

        .analytics-card.half-width {
            grid-column: span 1;
        }

        @media (max-width: 768px) {
            .analytics-grid {
                grid-template-columns: 1fr;
            }
            
            .analytics-card .card-body {
                height: 250px;
            }
        }



        /* Estilos para botões do modal do mapa */
        #filtroMesMapa:focus {
            outline: none;
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
        }

        /* Efeitos hover para botões do modal do mapa */
        button[onclick="desenhaMapaComRotas()"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
        }

        #btnAlternarMapa:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(25, 118, 210, 0.4);
        }

        #btnLimparMapa:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.4);
        }

        /* Efeito de brilho para botões do modal */
        button[onclick="desenhaMapaComRotas()"]:hover::before,
        #btnAlternarMapa:hover::before,
        #btnLimparMapa:hover::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        button[onclick="desenhaMapaComRotas()"]:hover::before,
        #btnAlternarMapa:hover::before,
        #btnLimparMapa:hover::before {
            left: 100%;
        }

        /* Posicionamento relativo para efeito de brilho */
        button[onclick="desenhaMapaComRotas()"],
        #btnAlternarMapa,
        #btnLimparMapa {
            position: relative;
            overflow: hidden;
        }
        
        /* Estilos responsivos para mobile */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition-speed) ease;
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            background: var(--bg-primary);
        }
        
        .sidebar-collapsed .main-content {
            margin-left: var(--sidebar-collapsed-width);
            width: calc(100% - var(--sidebar-collapsed-width));
        }
        
        .dashboard-content {
            padding: 20px;
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }
        
        /* Estilos para links de simuladores externos */
        .simulation-form a[target="_blank"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(63, 166, 255, 0.3) !important;
            border-color: #1e7ecb !important;
            background: linear-gradient(135deg, #f0f8ff 0%, #e6f3ff 100%) !important;
        }
        
        .simulation-form a[target="_blank"]:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include '../includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include '../includes/header.php'; ?>
            
            <!-- Page Content -->
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1>Rotas</h1>
                    <div class="dashboard-actions">
                        <button id="addRouteBtn" class="btn-add-widget">
                            <i class="fas fa-plus"></i> Nova Rota
                        </button>
                        <button id="importNfeXmlBtn" class="btn-add-widget" type="button" title="Importar XML da NF-e e criar rota" style="background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);">
                            <i class="fas fa-file-import"></i> Importar XML NF-e
                        </button>
                        <button id="simulateRouteBtn" class="btn-add-widget" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);">
                            <i class="fas fa-route"></i> Simular Rota
                        </button>
                        <div class="view-controls">
                            <button id="filterBtn" class="btn-restore-layout" title="Filtros" aria-label="Abrir filtros por período">
                                <i class="fas fa-filter"></i>
                            </button>
                            <button id="exportBtn" class="btn-toggle-layout" title="Exportar" aria-label="Exportar rotas em CSV">
                                <i class="fas fa-file-export"></i>
                            </button>
                            <button id="helpBtn" class="btn-help" title="Ajuda" aria-label="Ajuda sobre rotas">
                                <i class="fas fa-question-circle"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- KPI Cards Row -->
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Total de Rotas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="totalRoutes">0</span>
                                <span class="metric-subtitle">Rotas cadastradas</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Rotas Concluídas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="completedRoutes">0</span>
                                <span class="metric-subtitle">Neste mês</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Distância Total</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="totalDistance">0 km</span>
                                <span class="metric-subtitle">Percorridos</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
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
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Rotas no Prazo</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="rotasNoPrazo">0</span>
                                <span class="metric-subtitle">Entregas no prazo</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Rotas Atrasadas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="rotasAtrasadas">0</span>
                                <span class="metric-subtitle">Entregas atrasadas</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Eficiência Média</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="mediaEficiencia">0%</span>
                                <span class="metric-subtitle">Taxa de eficiência</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
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
                <div class="section-container">
                    <div class="section-header">
                        <h2>Rotas Ativas</h2>
                    </div>
                    
                    <div class="active-routes-container" id="activeRoutesContainer">
                        <!-- Será preenchido via JavaScript -->
                    </div>
                </div>
                
                <!-- Search and Filter -->
                <div class="filter-section">
                    <div class="search-box">
                        <input type="text" id="searchRoute" placeholder="Buscar rota...">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="filter-options">
                        <form method="get" action="" id="formPerPageRoutes" style="display:inline-flex; align-items:center; gap:0.5rem;">
                            <span class="filter-label">Por página</span>
                            <input type="hidden" name="page" value="1">
                            <select id="perPageRoutes" name="per_page" class="filter-per-page" title="Registros por página">
                                <option value="5"  <?php echo $per_page == 5  ? 'selected' : ''; ?>>5</option>
                                <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </form>
                        <select id="statusFilter">
                            <option value="">Todos os status</option>
                            <option value="Concluída">Concluídas</option>
                            <option value="Em andamento">Em andamento</option>
                            <option value="Programada">Programadas</option>
                            <option value="Cancelada">Canceladas</option>
                        </select>
                        
                        <select id="driverFilter">
                            <option value="">Todos os motoristas</option>
                            <!-- Será preenchido via JavaScript -->
                        </select>
                        
                        <select id="vehicleFilter">
                            <option value="">Todos os veículos</option>
                            <!-- Será preenchido via JavaScript -->
                        </select>
                        <button type="button" class="btn-restore-layout" id="applyRouteFilters" title="Aplicar filtros">
                            <i class="fas fa-filter"></i>
                        </button>
                        <button type="button" class="btn-restore-layout" id="clearRouteFilters" title="Limpar filtros">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Route Table -->
                <div class="table-container" id="routeTableContainer">
                    <div class="table-loading" id="routeTableLoading" style="display:none; padding: 2rem; text-align: center;">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>Carregando rotas...</span>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Motorista</th>
                                <th>Veículo</th>
                                <th>Rota</th>
                                <th>Distância</th>
                                <th>Frete</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rotas as $rota): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($rota['data_rota']); ?>
                                    (<?php echo date('d/m/Y', strtotime($rota['data_rota'])); ?>)
                                </td>
                                <td><?php echo htmlspecialchars($rota['motorista_nome'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($rota['veiculo_placa'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($rota['cidade_origem_nome'] ?? '-') . ' → ' . htmlspecialchars($rota['cidade_destino_nome'] ?? '-'); ?></td>
                                <td><?php echo number_format($rota['distancia_km'], 0, ',', '.') . ' km'; ?></td>
                                <td>R$ <?php echo number_format($rota['frete'], 2, ',', '.'); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $rota['no_prazo'] ? 'success' : 'warning'; ?>">
                                        <?php echo $rota['no_prazo'] ? 'No Prazo' : 'Atrasado'; ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <button class="btn-icon view-btn" data-id="<?php echo $rota['id']; ?>" title="Ver detalhes" aria-label="Ver detalhes da rota">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon edit-btn" data-id="<?php echo $rota['id']; ?>" title="Editar" aria-label="Editar rota">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon expenses-btn" data-id="<?php echo $rota['id']; ?>" title="Despesas de Viagem" aria-label="Despesas da viagem">
                                        <i class="fas fa-money-bill"></i>
                                    </button>
                                    <button class="btn-icon delete-btn" data-id="<?php echo $rota['id']; ?>" title="Excluir" aria-label="Excluir rota">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php
                $base_params = ['page' => 1, 'per_page' => $per_page];
                $prev_params = array_merge($base_params, ['page' => max(1, $pagina_atual - 1)]);
                $next_params = array_merge($base_params, ['page' => min($total_paginas, $pagina_atual + 1)]);
                ?>
                <div class="pagination" id="paginationRoutesContainer" data-per-page="<?php echo (int)$per_page; ?>">
                    <a href="?<?php echo htmlspecialchars(http_build_query($prev_params)); ?>" class="pagination-btn pagination-prev <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>" data-page="<?php echo max(1, $pagina_atual - 1); ?>" data-direction="prev">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <span class="pagination-info" id="paginationRoutesInfo">
                        <?php if ($total_paginas > 1): ?>Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?> (<?php echo (int)$resultado['total']; ?> registros)
                        <?php else: ?><?php echo (int)$resultado['total']; ?> registros<?php endif; ?>
                    </span>
                    <a href="?<?php echo htmlspecialchars(http_build_query($next_params)); ?>" class="pagination-btn pagination-next <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>" data-page="<?php echo min($total_paginas, $pagina_atual + 1); ?>" data-direction="next">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                
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
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Adicionar Rota</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="routeForm">
                    <input type="hidden" id="routeId" name="id">
                    
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
                                <label for="km_saida">KM Saída</label>
                                <input type="number" id="km_saida" name="km_saida" step="0.01" placeholder="Ex: 150000">
                                <small class="form-text" style="color: #6c757d; font-size: 0.875rem; margin-top: 4px;">
                                    <i class="fas fa-info-circle"></i> <span id="km_saida_help">Selecione um veículo para validar a quilometragem</span>
                                </small>
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
                    
                    <div class="form-section">
                        <h3>Dados da Carga</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="peso_carga">Peso da Carga (kg)</label>
                                <input type="number" id="peso_carga" name="peso_carga" step="0.01">
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="descricao_carga">Descrição da Carga</label>
                                <textarea id="descricao_carga" name="descricao_carga" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="observacoes">Observações</label>
                        <textarea id="observacoes" name="observacoes" rows="3"></textarea>
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
    <div class="modal" id="viewRouteModal" data-route-id="">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h2 id="viewModalTitle">Detalhes da Rota</h2>
                <span class="close-modal close-view-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="details-container">
                    <!-- Cabeçalho Principal -->
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 10px; margin-bottom: 25px; color: white; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h3 style="margin: 0 0 8px 0; font-size: 1.6rem; text-shadow: 0 2px 4px rgba(0,0,0,0.2);" id="routeOriginDestination">São Paulo, SP → Rio de Janeiro, RJ</h3>
                                <div style="display: inline-block; background: rgba(255,255,255,0.25); padding: 4px 12px; border-radius: 15px; font-size: 0.9rem; font-weight: 500;" id="routeStatus">Concluída</div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 0.85rem; opacity: 0.9; margin-bottom: 4px;">Data da Rota</div>
                                <div style="font-size: 1.3rem; font-weight: 700;" id="routeDate">07/05/2025</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Grid de Informações Principais -->
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 25px;">
                        <!-- Card Viagem -->
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; border-left: 4px solid #007bff;">
                            <h4 style="color: #007bff; margin: 0 0 15px 0; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-route"></i> Informações da Viagem
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                                <div>
                                    <label style="font-size: 0.75rem; color: #6c757d; text-transform: uppercase; font-weight: 600; display: block; margin-bottom: 4px;">Motorista</label>
                                    <div style="font-size: 1rem; font-weight: 500; color: #333;" id="detailDriver">-</div>
                                </div>
                                <div>
                                    <label style="font-size: 0.75rem; color: #6c757d; text-transform: uppercase; font-weight: 600; display: block; margin-bottom: 4px;">Veículo</label>
                                    <div style="font-size: 1rem; font-weight: 500; color: #333;" id="detailVehicle">-</div>
                                </div>
                                <div>
                                    <label style="font-size: 0.75rem; color: #6c757d; text-transform: uppercase; font-weight: 600; display: block; margin-bottom: 4px;">Distância</label>
                                    <div style="font-size: 1rem; font-weight: 500; color: #333;" id="detailDistance">-</div>
                                </div>
                                <div>
                                    <label style="font-size: 0.75rem; color: #6c757d; text-transform: uppercase; font-weight: 600; display: block; margin-bottom: 4px;">Consumo</label>
                                    <div style="font-size: 1rem; font-weight: 500; color: #333;" id="detailFuelConsumption">-</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Card Horários -->
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; border-left: 4px solid #28a745;">
                            <h4 style="color: #28a745; margin: 0 0 15px 0; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-clock"></i> Horários e Duração
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                                <div>
                                    <label style="font-size: 0.75rem; color: #6c757d; text-transform: uppercase; font-weight: 600; display: block; margin-bottom: 4px;">Saída</label>
                                    <div style="font-size: 1rem; font-weight: 500; color: #333;" id="detailStartTime">-</div>
                                </div>
                                <div>
                                    <label style="font-size: 0.75rem; color: #6c757d; text-transform: uppercase; font-weight: 600; display: block; margin-bottom: 4px;">Chegada</label>
                                    <div style="font-size: 1rem; font-weight: 500; color: #333;" id="detailEndTime">-</div>
                                </div>
                                <div style="grid-column: 1 / -1;">
                                    <label style="font-size: 0.75rem; color: #6c757d; text-transform: uppercase; font-weight: 600; display: block; margin-bottom: 4px;">Duração Total</label>
                                    <div style="font-size: 1.2rem; font-weight: 700; color: #28a745;" id="detailDuration">-</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Grid de Endereços e Carga -->
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 25px;">
                        <!-- Endereços -->
                        <div style="background: #fff3cd; padding: 20px; border-radius: 10px; border-left: 4px solid #ffc107;">
                            <h4 style="color: #856404; margin: 0 0 15px 0; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-map-marker-alt"></i> Origem e Destino
                            </h4>
                            <div style="margin-bottom: 15px;">
                                <label style="font-size: 0.75rem; color: #856404; text-transform: uppercase; font-weight: 600; display: block; margin-bottom: 4px;">📍 Origem</label>
                                <div style="font-size: 0.95rem; font-weight: 500; color: #333;" id="detailOriginAddress">-</div>
                            </div>
                            <div>
                                <label style="font-size: 0.75rem; color: #856404; text-transform: uppercase; font-weight: 600; display: block; margin-bottom: 4px;">🏁 Destino</label>
                                <div style="font-size: 0.95rem; font-weight: 500; color: #333;" id="detailDestinationAddress">-</div>
                            </div>
                        </div>
                        
                        <!-- Informações da Carga -->
                        <div style="background: #e7f3ff; padding: 20px; border-radius: 10px; border-left: 4px solid #17a2b8;">
                            <h4 style="color: #117a8b; margin: 0 0 15px 0; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-box"></i> Informações da Carga
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                                <div style="grid-column: 1 / -1;">
                                    <label style="font-size: 0.75rem; color: #117a8b; text-transform: uppercase; font-weight: 600; display: block; margin-bottom: 4px;">Descrição</label>
                                    <div style="font-size: 0.95rem; font-weight: 500; color: #333;" id="detailCargoDescription">-</div>
                                </div>
                                <div>
                                    <label style="font-size: 0.75rem; color: #117a8b; text-transform: uppercase; font-weight: 600; display: block; margin-bottom: 4px;">Peso</label>
                                    <div style="font-size: 0.95rem; font-weight: 500; color: #333;" id="detailCargoWeight">-</div>
                                </div>
                                <div>
                                    <label style="font-size: 0.75rem; color: #117a8b; text-transform: uppercase; font-weight: 600; display: block; margin-bottom: 4px;">Cliente</label>
                                    <div style="font-size: 0.95rem; font-weight: 500; color: #333;" id="detailCustomer">-</div>
                                </div>
                                <div style="grid-column: 1 / -1;">
                                    <label style="font-size: 0.75rem; color: #117a8b; text-transform: uppercase; font-weight: 600; display: block; margin-bottom: 4px;">Contato</label>
                                    <div style="font-size: 0.95rem; font-weight: 500; color: #333;" id="detailCustomerContact">-</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Análise de Lucratividade -->
                    <div class="cost-summary" style="margin-top: 25px;">
                        <h4><i class="fas fa-chart-line"></i> Análise de Lucratividade da Rota</h4>
                        
                        <div class="cost-cards" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px;">
                            <!-- Receita Bruta -->
                            <div class="cost-card" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-left: 4px solid #2196f3;">
                                <h5 style="color: #1976d2; display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                                    <i class="fas fa-dollar-sign"></i> Receita Bruta
                                </h5>
                                <div class="cost-value" style="font-size: 1.8rem; color: #1565c0; font-weight: 700;" id="profitReceitaBruta">R$ 0,00</div>
                                <p style="font-size: 0.85rem; color: #666; margin: 5px 0 0;">Valor do Frete</p>
                            </div>
                            
                            <!-- Despesas Totais -->
                            <div class="cost-card" style="background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%); border-left: 4px solid #f44336;">
                                <h5 style="color: #c62828; display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                                    <i class="fas fa-receipt"></i> Despesas Totais
                                </h5>
                                <div class="cost-value" style="font-size: 1.8rem; color: #b71c1c; font-weight: 700;" id="profitDespesasTotais">R$ 0,00</div>
                                <p style="font-size: 0.85rem; color: #666; margin: 5px 0 0;">Comissão + Despesas + Combustível</p>
                            </div>
                            
                            <!-- Lucro Líquido -->
                            <div class="cost-card" style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-left: 4px solid #4caf50;">
                                <h5 style="color: #388e3c; display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                                    <i class="fas fa-chart-pie"></i> Lucro Líquido
                                </h5>
                                <div class="cost-value" style="font-size: 2.2rem; color: #2e7d32; font-weight: 700;" id="profitLucroLiquido">R$ 0,00</div>
                                <p style="font-size: 0.85rem; color: #666; margin: 5px 0 0;">Resultado Final da Rota</p>
                            </div>
                            
                            <!-- Margem -->
                            <div class="cost-card" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%); border-left: 4px solid #9c27b0;">
                                <h5 style="color: #7b1fa2; display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                                    <i class="fas fa-chart-area"></i> Margem Líquida
                                </h5>
                                <div class="cost-value" style="font-size: 2.2rem; color: #6a1b9a; font-weight: 700;" id="profitMargem">0%</div>
                                <p style="font-size: 0.85rem; color: #666; margin: 5px 0 0;">% sobre Receita</p>
                            </div>
                        </div>
                        
                        <!-- Tabela Detalhada -->
                        <div class="cost-breakdown" style="margin-top: 20px;">
                            <h4><i class="fas fa-list-alt"></i> Composição Detalhada do Resultado</h4>
                            <div class="cost-breakdown-table">
                                <table class="info-table">
                                    <thead>
                                        <tr>
                                            <th style="text-align: left;">Item</th>
                                            <th style="text-align: right;">Valor</th>
                                            <th style="text-align: right;">% Receita</th>
                                        </tr>
                                    </thead>
                                    <tbody id="profitabilityTableBody">
                                        <!-- Preenchido via JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Indicador Visual de Lucro -->
                        <div style="margin-top: 20px; padding: 20px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 8px; border: 1px solid #dee2e6;">
                            <h5 style="color: #495057; margin-bottom: 12px; font-weight: 600;">
                                <i class="fas fa-tachometer-alt"></i> Indicador de Rentabilidade
                            </h5>
                            <div style="position: relative; height: 35px; background: #fff; border: 2px solid #dee2e6; border-radius: 8px; overflow: hidden; box-shadow: inset 0 2px 4px rgba(0,0,0,0.06);">
                                <div id="profitabilityIndicator" style="height: 100%; background: linear-gradient(90deg, #dc3545 0%, #ffc107 40%, #17a2b8 70%, #28a745 100%); width: 0%; transition: all 0.5s ease;"></div>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-top: 10px; font-size: 0.8rem; color: #6c757d; font-weight: 500;">
                                <span style="color: #dc3545;">❌ Prejuízo</span>
                                <span style="color: #ffc107;">⚠️ Baixa</span>
                                <span style="color: #17a2b8;">✅ Boa</span>
                                <span style="color: #28a745;">🎯 Excelente</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <h4>Observações</h4>
                        <p id="detailNotes"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="closeRouteDetailsBtn" class="btn-secondary">Fechar</button>
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
                                console.log('Calculando lucratividade para rota:', rotaId);
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
                    setTimeout(function() {
                        if (window.calcularLucratividade) {
                            window.calcularLucratividade(rotaId);
                        }
                    }, 500);
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

    <!-- Route Simulation Modal -->
    <div class="modal" id="routeSimulationModal">
        <div class="modal-content" style="width: 90%; max-width: 1200px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <h2>🚛 Simulador de Rota</h2>
                <span class="close-modal" onclick="closeSimulationModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <!-- Formulário de Simulação -->
                    <div class="simulation-form">
                        <h3 style="color: #1976d2; margin-bottom: 15px;">📍 Configuração da Rota</h3>
                        
                        <div class="form-group">
                            <label for="simOrigin">Origem:</label>
                            <input type="text" id="simOrigin" placeholder="Ex: São Paulo, SP" style="width: 100%; padding: 10px; border: 2px solid #e1e5e9; border-radius: 8px;">
                        </div>
                        
                        <div class="form-group">
                            <label for="simDestination">Destino:</label>
                            <input type="text" id="simDestination" placeholder="Ex: Rio de Janeiro, RJ" style="width: 100%; padding: 10px; border: 2px solid #e1e5e9; border-radius: 8px;">
                        </div>
                        
                        <div class="form-group">
                            <label for="simVehicle">Veículo:</label>
                            <select id="simVehicle" style="width: 100%; padding: 10px; border: 2px solid #e1e5e9; border-radius: 8px;">
                                <option value="">Selecione um veículo</option>
                                <!-- Opções serão preenchidas via JavaScript -->
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="simFuelPrice">Preço do Combustível (R$/L):</label>
                            <input type="number" id="simFuelPrice" step="0.01" value="5.50" style="width: 100%; padding: 10px; border: 2px solid #e1e5e9; border-radius: 8px;">
                        </div>
                        
                        <button id="simulateRouteBtnModal" style="width: 100%; padding: 12px; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 10px;">
                            <i class="fas fa-calculator"></i> Simular Rota
                        </button>
                        
                        <!-- Links para Simuladores Externos -->
                        <div style="margin-top: 20px; padding: 15px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 8px; border: 1px solid #dee2e6;">
                            <h4 style="color: #1976d2; margin-bottom: 12px; font-weight: 600; font-size: 1em;">
                                <i class="fas fa-external-link-alt"></i> Simuladores de Rotas Online
                            </h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                                <a href="https://rotasbrasil.com.br/" target="_blank" rel="noopener noreferrer" 
                                   style="display: flex; align-items: center; gap: 8px; padding: 10px; background: white; border: 2px solid #3fa6ff; border-radius: 6px; text-decoration: none; color: #1976d2; font-weight: 500; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                    <i class="fas fa-route" style="color: #3fa6ff;"></i>
                                    <span>Rotas Brasil</span>
                                    <i class="fas fa-external-link-alt" style="margin-left: auto; font-size: 0.8em; opacity: 0.7;"></i>
                                </a>
                                <a href="https://www.webrouter.com.br/way/#/calcularRota" target="_blank" rel="noopener noreferrer" 
                                   style="display: flex; align-items: center; gap: 8px; padding: 10px; background: white; border: 2px solid #3fa6ff; border-radius: 6px; text-decoration: none; color: #1976d2; font-weight: 500; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                    <i class="fas fa-map-marked-alt" style="color: #3fa6ff;"></i>
                                    <span>WebRouter</span>
                                    <i class="fas fa-external-link-alt" style="margin-left: auto; font-size: 0.8em; opacity: 0.7;"></i>
                                </a>
                                <a href="https://qualp.com.br/#/" target="_blank" rel="noopener noreferrer" 
                                   style="display: flex; align-items: center; gap: 8px; padding: 10px; background: white; border: 2px solid #3fa6ff; border-radius: 6px; text-decoration: none; color: #1976d2; font-weight: 500; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                    <i class="fas fa-route" style="color: #3fa6ff;"></i>
                                    <span>Qualp</span>
                                    <i class="fas fa-external-link-alt" style="margin-left: auto; font-size: 0.8em; opacity: 0.7;"></i>
                                </a>
                                <a href="https://www.semparar.com.br/trace-sua-rota" target="_blank" rel="noopener noreferrer" 
                                   style="display: flex; align-items: center; gap: 8px; padding: 10px; background: white; border: 2px solid #3fa6ff; border-radius: 6px; text-decoration: none; color: #1976d2; font-weight: 500; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                    <i class="fas fa-tag" style="color: #3fa6ff;"></i>
                                    <span>Sem Parar</span>
                                    <i class="fas fa-external-link-alt" style="margin-left: auto; font-size: 0.8em; opacity: 0.7;"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Mapa de Simulação -->
                    <div class="simulation-map">
                        <h3 style="color: #1976d2; margin-bottom: 15px;">🗺️ Mapa da Rota</h3>
                        <div id="simulationMap" style="width: 100%; height: 400px; border: 2px solid #e1e5e9; border-radius: 8px;"></div>
                        
                        <!-- Mensagens de status (baseadas no example.html) -->
                        <div id="simulationInfo" style="display: none; background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%); border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 8px; margin: 10px 0; font-weight: 500; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>
                        <div id="simulationError" style="display: none; background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 8px; margin: 10px 0; font-weight: 500; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>
                    </div>
                </div>
                
                <!-- Resultados da Simulação -->
                <div id="simulationResults" style="display: none;">
                    <h3 style="color: #1976d2; margin-bottom: 15px;">📊 Resultados da Simulação</h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px;">
                        <!-- Informações Básicas -->
                        <div class="result-card" style="background: linear-gradient(135deg, #e8f5e8 0%, #f1f8e9 100%); padding: 15px; border-radius: 8px; border-left: 4px solid #28a745; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            <h4 style="margin: 0 0 10px 0; color: #2e7d32; font-weight: 600;">📏 Distância</h4>
                            <p style="margin: 5px 0; font-size: 18px; font-weight: bold; color: #1b5e20; text-shadow: 0 1px 2px rgba(0,0,0,0.1);" id="simDistance">-</p>
                            <p style="margin: 5px 0; color: #2e7d32; font-weight: 500;" id="simDuration">-</p>
                        </div>
                        
                        <!-- Custos de Combustível -->
                        <div class="result-card" style="background: linear-gradient(135deg, #fff3e0 0%, #fce4ec 100%); padding: 15px; border-radius: 8px; border-left: 4px solid #ff6b6b; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            <h4 style="margin: 0 0 10px 0; color: #e65100; font-weight: 600;">⛽ Combustível</h4>
                            <p style="margin: 5px 0; font-size: 18px; font-weight: bold; color: #bf360c; text-shadow: 0 1px 2px rgba(0,0,0,0.1);" id="simFuelCost">-</p>
                            <p style="margin: 5px 0; color: #d84315; font-weight: 500;" id="simFuelLiters">-</p>
                        </div>
                        
                        <!-- Pedágios -->
                        <div class="result-card" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1f5fe 100%); padding: 15px; border-radius: 8px; border-left: 4px solid #ffa726; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            <h4 style="margin: 0 0 10px 0; color: #e65100; font-weight: 600;">🛣️ Pedágios</h4>
                            <p style="margin: 5px 0; font-size: 18px; font-weight: bold; color: #bf360c; text-shadow: 0 1px 2px rgba(0,0,0,0.1);" id="simTolls">-</p>
                            <p style="margin: 5px 0; color: #d84315; font-weight: 500;" id="simTollCount">-</p>
                        </div>
                        
                        <!-- Custo Total -->
                        <div class="result-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
                            <h4 style="margin: 0 0 10px 0; font-weight: 600; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">💰 Custo Total</h4>
                            <p style="margin: 5px 0; font-size: 24px; font-weight: bold; text-shadow: 0 2px 4px rgba(0,0,0,0.3);" id="simTotalCost">-</p>
                            <p style="margin: 5px 0; opacity: 0.9; font-weight: 500; text-shadow: 0 1px 2px rgba(0,0,0,0.3);" id="simCostPerKm">-</p>
                        </div>
                    </div>
                    
                    <!-- Detalhes da Rota -->
                    <div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 20px; border-radius: 8px; margin-top: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 1px solid #dee2e6;">
                        <h4 style="color: #1976d2; margin-bottom: 15px; font-weight: 600; text-shadow: 0 1px 2px rgba(0,0,0,0.1);">🛣️ Detalhes da Rota</h4>
                        <div id="routeDetails" style="max-height: 200px; overflow-y: auto; color: #333; line-height: 1.6;">
                            <!-- Detalhes serão preenchidos via JavaScript -->
                        </div>
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
    <button id="btnMapaRotas" style="
      position: fixed; bottom: 30px; right: 30px; z-index: 1000;
      background: #1976d2; color: #fff; border: none; border-radius: 50%; width: 60px; height: 60px; font-size: 2rem; box-shadow: 0 2px 8px #0003; cursor: pointer;">
      <i class="fas fa-map-marked-alt"></i>
    </button>

    <!-- Modal do Mapa de Rotas -->
    <div id="modalMapaRotas" style="
      display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
      background: rgba(0,0,0,0.5); z-index: 2000; justify-content: center; align-items: center;">
      <div style="
        background: rgba(255,255,255,0.05); /* Quase transparente */
        border: 2px solid #1976d2;          /* Moldura azul simples */
        border-radius: 16px;
        box-shadow: 0 2px 16px #0005;
        padding: 24px;
        position: relative;">
        <button onclick="fecharModalMapa()" style="position: absolute; top: 8px; right: 8px; background: none; border: none; font-size: 1.5rem; color: #1976d2;">&times;</button>
        <div style="text-align:center; margin-bottom: 15px;">
          <div style="display: flex; justify-content: center; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 15px;">
            <input type="month" id="filtroMesMapa" style="font-size:1rem; padding:8px 12px; border:2px solid #e1e5e9; border-radius:8px; background:white; color:#333; transition: border-color 0.3s ease;" placeholder="Selecione o mês/ano">
            <button onclick="desenhaMapaComRotas()" style="font-size:1rem; padding:8px 16px; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);">
              <i class="fas fa-filter"></i> Filtrar
            </button>
            <button id="btnAlternarMapa" style="font-size:1rem; padding:8px 16px; background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(25, 118, 210, 0.3);">
              <i class="fas fa-map-marked-alt"></i> Google Maps
            </button>
            <button id="btnLimparMapa" style="font-size:1rem; padding:8px 16px; background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(108, 117, 125, 0.3);">
              <i class="fas fa-eraser"></i> Limpar
            </button>
        </div>
        </div>
        <div style="margin: 15px 0; display: flex; justify-content: center; align-items: center; flex-direction: column; gap: 8px;">
          <span id="coordenadaInfo" style="color:#1976d2; font-weight:bold; font-size: 0.9rem;"></span>
          <div id="mapStats" style="background: linear-gradient(135deg, rgba(25, 118, 210, 0.1) 0%, rgba(25, 118, 210, 0.05) 100%); padding: 12px 20px; border-radius: 12px; color: #1976d2; font-weight: bold; border: 2px solid rgba(25, 118, 210, 0.2); box-shadow: 0 2px 8px rgba(25, 118, 210, 0.1); text-align: center; min-width: 300px;">
            <div style="display: flex; justify-content: center; align-items: center; gap: 15px; flex-wrap: wrap;">
              <span style="display: flex; align-items: center; gap: 5px;">
                <i class="fas fa-route" style="color: #1976d2;"></i>
                <span id="totalRotasMapa">0</span> rotas
              </span>
              <span style="color: #666;">|</span>
              <span style="display: flex; align-items: center; gap: 5px;">
                <i class="fas fa-road" style="color: #1976d2;"></i>
                <span id="totalKmMapa">0</span> km
              </span>
              <span style="color: #666;">|</span>
              <span style="display: flex; align-items: center; gap: 5px;">
                <i class="fas fa-dollar-sign" style="color: #1976d2;"></i>
                <span id="totalFreteMapa">R$ 0,00</span>
              </span>
            </div>
          </div>
        </div>
        
        <!-- Container responsivo para os mapas -->
        <div class="map-container" style="width: 100%; max-width: 800px; margin: 0 auto; padding: 10px;">
        <!-- Mapa Canvas (atual) -->
        <canvas id="mapCanvas" width="800" height="700" style="display: block; margin: 0 auto; max-width: 100%; height: auto;"></canvas>
        
        <!-- Google Maps -->
        <div id="googleMap" style="width: 100%; max-width: 800px; height: 700px; margin: 0 auto; display: none;"></div>
        </div>
      </div>
    </div>

    <!-- Tooltip para o mapa -->
    <div id="mapTooltip" style="
      display:none;
      position:fixed;
      pointer-events:none;
      background:rgba(30,30,30,0.95);
      color:#fff;
      padding:8px 12px;
      border-radius:6px;
      font-size:0.95rem;
      z-index:3000;
      box-shadow:0 2px 8px #0007;
    ></div>

    <!-- Toast container: topo direito como em abastecimentos; z-index abaixo do dropdown do header (100) para não bloquear o menu -->
    <div id="toastContainer" class="toast-container" aria-live="polite" style="position:fixed;top:80px;right:20px;left:auto;bottom:auto;z-index:50;display:flex;flex-direction:column;gap:8px;max-width:360px;pointer-events:none;"></div>
    <script>
    function showToast(message, type) {
        type = type || 'info';
        var container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            container.setAttribute('aria-live', 'polite');
            container.style.cssText = 'position:fixed;top:80px;right:20px;left:auto;bottom:auto;z-index:50;display:flex;flex-direction:column;gap:8px;max-width:360px;pointer-events:none;';
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
        toast.style.cssText = 'padding:12px 16px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.2);display:flex;align-items:center;gap:10px;transition:opacity 0.3s ease;pointer-events:auto;opacity:1;';
        if (type === 'success') { toast.style.background = '#d4edda'; toast.style.color = '#155724'; }
        else if (type === 'error') { toast.style.background = '#f8d7da'; toast.style.color = '#721c24'; }
        else { toast.style.background = '#d1ecf1'; toast.style.color = '#0c5460'; }
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
                dropdown.classList.toggle('show');
                btn.classList.toggle('active');
            });
            document.addEventListener('click', function(e) {
                if (dropdown.classList.contains('show') && !dropdown.contains(e.target) && !btn.contains(e.target)) {
                    dropdown.classList.remove('show');
                    btn.classList.remove('active');
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
    
    <!-- Script do Mapa - Carregado por último -->
    <script>
        // Função para redimensionar o canvas responsivamente
        function resizeCanvas() {
            const canvas = document.getElementById('mapCanvas');
            const container = canvas.parentElement;
            const containerWidth = container.clientWidth;
            
            // Manter proporção 800x700
            const aspectRatio = 800 / 700;
            let newWidth = Math.min(containerWidth - 20, 800); // 20px de margem
            let newHeight = newWidth / aspectRatio;
            
            // Limitar altura máxima
            if (newHeight > 700) {
                newHeight = 700;
                newWidth = newHeight * aspectRatio;
            }
            
            // Aplicar novos tamanhos
            canvas.style.width = newWidth + 'px';
            canvas.style.height = newHeight + 'px';
            
            // Redesenhar o mapa se necessário
            if (typeof desenhaMapaComRotas === 'function') {
                desenhaMapaComRotas();
            }
        }
        
        // Redimensionar quando a janela mudar de tamanho
        window.addEventListener('resize', resizeCanvas);
        
        // Redimensionar quando a página carregar
        window.addEventListener('load', resizeCanvas);
        
        // Namespace para evitar conflitos
        window.MapRoutes = window.MapRoutes || {};
        
        // Verificar se as variáveis já foram declaradas
        if (typeof window.MapRoutes.googleMap === 'undefined') {
            window.MapRoutes.googleMap = null;
        }
        if (typeof window.MapRoutes.googleMapManager === 'undefined') {
            window.MapRoutes.googleMapManager = null;
        }
        if (typeof window.MapRoutes.routeManager === 'undefined') {
            window.MapRoutes.routeManager = null;
        }
        if (typeof window.MapRoutes.isGoogleMapActive === 'undefined') {
            window.MapRoutes.isGoogleMapActive = false;
        }
        if (typeof window.MapRoutes.routesData === 'undefined') {
            window.MapRoutes.routesData = [];
        }
        
        // Usar as variáveis do namespace
        let googleMap = window.MapRoutes.googleMap;
        let googleMapManager = window.MapRoutes.googleMapManager;
        let routeManager = window.MapRoutes.routeManager;
        let isGoogleMapActive = window.MapRoutes.isGoogleMapActive;
        let routesData = window.MapRoutes.routesData;

        // Função para mostrar o modal de ajuda
        function showHelpModal() {
            const modal = document.getElementById('helpRouteModal');
            if (modal) {
                modal.style.display = 'block';
            }
        }

        // Função para fechar o modal
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
            }
        }


        function fecharModalMapa() {
            document.getElementById('modalMapaRotas').style.display = 'none';
        }

        function getColor(index) {
            const colors = [
                '#e6194b', '#3cb44b', '#ffe119', '#4363d8', '#f58231',
                '#911eb4', '#46f0f0', '#f032e6', '#bcf60c', '#fabebe',
                '#008080', '#e6beff', '#9a6324', '#fffac8', '#800000',
                '#aaffc3', '#808000', '#ffd8b1', '#000075', '#808080'
            ];
            return colors[index % colors.length];
        }

        let pointCount = {};
        function getOffset(x, y) {
            const key = `${x}_${y}`;
            if (!pointCount[key]) pointCount[key] = 0;
            const offset = pointCount[key] * 10; // 10px de deslocamento por ponto sobreposto
            pointCount[key]++;
            return offset;
        }

        let pontosRotas = [];
        function desenhaMapaComRotas() {
            const canvas = document.getElementById("mapCanvas");
            const ctx = canvas.getContext("2d");
            const img = new Image();
            img.src = '/sistema-frotas/uploads/mapa/mapa-brasil.png';

            img.onload = () => {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                // Pega o mês/ano do filtro, ou usa o atual
                let mes, ano;
                const filtro = document.getElementById('filtroMesMapa');
                if (filtro && filtro.value) {
                    [ano, mes] = filtro.value.split('-');
                } else {
                    const data = new Date();
                    mes = data.getMonth() + 1;
                    ano = data.getFullYear();
                }

                fetch('../api/rotas_mapa.php?mes=' + mes + '&ano=' + ano)
                    .then(res => res.json())
                    .then(rotas => {
                        pontosRotas = [];
                        pointCount = {};
                        if (!Array.isArray(rotas)) {
                            console.error('Resposta inesperada da API:', rotas);
                            return;
                        }
                        rotas.forEach((r, idx) => {
                            const color = getColor(idx);

                            // Origem
                            let offsetO = getOffset(r.origem_x, r.origem_y);
                            ctx.beginPath();
                            ctx.arc(r.origem_x + offsetO, r.origem_y + offsetO, 8, 0, 2 * Math.PI);
                            ctx.fillStyle = color;
                            ctx.globalAlpha = 0.85;
                            ctx.fill();
                            ctx.globalAlpha = 1.0;

                            // Destino
                            let offsetD = getOffset(r.destino_x, r.destino_y);
                            ctx.beginPath();
                            ctx.arc(r.destino_x + offsetD, r.destino_y + offsetD, 8, 0, 2 * Math.PI);
                            ctx.fillStyle = color;
                            ctx.globalAlpha = 0.85;
                            ctx.fill();
                            ctx.globalAlpha = 1.0;

                            // Linha curva tracejada
                            ctx.save();
                            ctx.beginPath();
                            const mx = (r.origem_x + r.destino_x) / 2;
                            const my = (r.origem_y + r.destino_y) / 2 - 40;
                            ctx.setLineDash([8, 8]);
                            ctx.moveTo(r.origem_x + offsetO, r.origem_y + offsetO);
                            ctx.quadraticCurveTo(mx, my, r.destino_x + offsetD, r.destino_y + offsetD);
                            ctx.strokeStyle = color;
                            ctx.lineWidth = 2;
                            ctx.stroke();
                            ctx.setLineDash([]);
                            ctx.restore();

                            // Salva os pontos de origem e destino para hover
                            pontosRotas.push({
                                x: r.origem_x + offsetO,
                                y: r.origem_y + offsetO,
                                tipo: 'origem',
                                estado: r.estado_origem,
                                cidade: r.cidade_origem_nome,
                                estado_destino: r.estado_destino,
                                cidade_destino: r.cidade_destino_nome,
                                color: color
                            });
                            pontosRotas.push({
                                x: r.destino_x + offsetD,
                                y: r.destino_y + offsetD,
                                tipo: 'destino',
                                estado: r.estado_destino,
                                cidade: r.cidade_destino_nome,
                                estado_origem: r.estado_origem,
                                cidade_origem: r.cidade_origem_nome,
                                color: color
                            });
                        });
                    });
            };
        }

        // Evento de mousemove para mostrar o tooltip
        const canvasEl = document.getElementById('mapCanvas');
        canvasEl.addEventListener('mousemove', function(e) {
            const rect = this.getBoundingClientRect();
            const mouseX = e.clientX - rect.left;
            const mouseY = e.clientY - rect.top;
            let found = false;
            for (const p of pontosRotas) {
                if (Math.sqrt((mouseX - p.x) ** 2 + (mouseY - p.y) ** 2) < 12) {
                    found = true;
                    let html = '';
                    if (p.tipo === 'origem') {
                        html = `<strong>Origem</strong><br>Estado: ${p.estado}<br>Cidade: ${p.cidade}<br>` +
                               `<strong>Destino</strong><br>Estado: ${p.estado_destino}<br>Cidade: ${p.cidade_destino}`;
                    } else {
                        html = `<strong>Destino</strong><br>Estado: ${p.estado}<br>Cidade: ${p.cidade}<br>` +
                               `<strong>Origem</strong><br>Estado: ${p.estado_origem}<br>Cidade: ${p.cidade_origem}`;
                    }
                    const tooltip = document.getElementById('mapTooltip');
                    tooltip.innerHTML = html;
                    tooltip.style.display = 'block';
                    tooltip.style.left = (e.clientX + 12) + 'px';
                    tooltip.style.top = (e.clientY + 12) + 'px';
                    tooltip.style.borderColor = p.color;
                    break;
                }
            }
            if (!found) {
                const tooltip = document.getElementById('mapTooltip');
                tooltip.style.display = 'none';
                tooltip.style.borderColor = '';
            }
        });
        canvasEl.addEventListener('mouseleave', function() {
            document.getElementById('mapTooltip').style.display = 'none';
        });

        let modoCoordenadas = false;

        document.getElementById('mapCanvas').addEventListener('click', function(e) {
            if (!modoCoordenadas) return;
            const rect = this.getBoundingClientRect();
            const x = Math.round(e.clientX - rect.left);
            const y = Math.round(e.clientY - rect.top);
            document.getElementById('coordenadaInfo').textContent = `Coordenada: X=${x} Y=${y} (copie e preencha na tabela)`;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(`${x},${y}`);
            }
        });

        // ===== GOOGLE MAPS INTEGRATION =====
        
        // Simulador de Rotas (baseado no example.html)
        let simulationMap = null;
        let simulationRouteManager = null;

        // Função para inicializar o simulador
        function initRouteSimulator() {
            console.log('Inicializando simulador de rotas...');
            
            // Verificar se os scripts estão carregados
            if (typeof GoogleMapsManager === 'undefined') {
                console.error('GoogleMapsManager não está disponível');
                showSimulationError('GoogleMapsManager não está disponível');
                return;
            }
            
            if (typeof RouteManager === 'undefined') {
                console.error('RouteManager não está disponível');
                showSimulationError('RouteManager não está disponível');
                return;
            }
            
            // Event listeners do simulador
            const simulateBtn = document.getElementById('simulateRouteBtnModal');
            if (simulateBtn) {
                console.log('Botão de simular encontrado no modal');
                simulateBtn.addEventListener('click', simulateRoute);
            } else {
                console.error('Botão de simular não encontrado no modal');
            }
            
            // Preencher opções de veículos padrão
            loadDefaultVehicles();
            console.log('Simulador inicializado');
        }

        // Carregar veículos padrão para simulação
        function loadDefaultVehicles() {
            const select = document.getElementById('simVehicle');
            select.innerHTML = `
                <option value="">Selecione um tipo de veículo</option>
                <option value="caminhao_pequeno">Caminhão Pequeno (8-10 km/L)</option>
                <option value="caminhao_medio">Caminhão Médio (6-8 km/L)</option>
                <option value="caminhao_grande">Caminhão Grande (4-6 km/L)</option>
                <option value="carreta">Carreta (3-5 km/L)</option>
                <option value="van">Van (10-12 km/L)</option>
                <option value="pickup">Pickup (8-10 km/L)</option>
            `;
        }

        // Obter consumo baseado no tipo de veículo
        function getVehicleConsumption(vehicleType) {
            const consumptions = {
                'caminhao_pequeno': 9.0,
                'caminhao_medio': 7.0,
                'caminhao_grande': 5.0,
                'carreta': 4.0,
                'van': 11.0,
                'pickup': 9.0
            };
            
            return consumptions[vehicleType] || 8.0; // Padrão se não selecionado
        }

        // Simular rota (baseado no example.html)
        async function simulateRoute() {
            console.log('Função simulateRoute chamada');
            
            const origin = document.getElementById('simOrigin').value;
            const destination = document.getElementById('simDestination').value;
            const fuelPrice = parseFloat(document.getElementById('simFuelPrice').value);
            const vehicleType = document.getElementById('simVehicle').value;

            console.log('Valores:', { origin, destination, fuelPrice, vehicleType });

            if (!origin || !destination) {
                showSimulationError('Por favor, preencha origem e destino');
                return;
            }

            try {
                showSimulationInfo('Inicializando simulador...');
                console.log('Iniciando simulação...');
                
                // Obter chave da API do Google Maps
                showSimulationInfo('Obtendo chave da API...');
                const response = await fetch('../google-maps/api.php?action=get_config', {
                    credentials: 'include'
                });
                const data = await response.json();
                
                if (!data.success || !data.data.google_maps_api_key) {
                    showSimulationError('Chave da API do Google Maps não configurada');
                    return;
                }

                console.log('Chave da API obtida:', data.data.google_maps_api_key);

                // Inicializar Google Maps para simulação
                showSimulationInfo('Carregando Google Maps...');
                await initSimulationMap(data.data.google_maps_api_key);
                
                showSimulationInfo('Calculando rota...');
                
                // Calcular rota usando RouteManager
                console.log('Iniciando cálculo da rota...');
                
                // Adicionar timeout para evitar travamento
                const routePromise = simulationRouteManager.calculateRoute(origin, destination);
                const timeoutPromise = new Promise((_, reject) => 
                    setTimeout(() => reject(new Error('Timeout: Cálculo da rota demorou muito')), 30000)
                );
                
                const result = await Promise.race([routePromise, timeoutPromise]);
                console.log('Resultado do cálculo:', result);
                
                if (result) {
                    console.log('Rota calculada com sucesso, obtendo informações...');
                    // Obter informações da rota
                    const routeInfo = simulationRouteManager.getRouteInfo();
                    console.log('Informações da rota:', routeInfo);
                    
                    if (routeInfo) {
                        // Calcular custos
                        const fuelConsumption = getVehicleConsumption(vehicleType);
                        const distance = parseFloat(routeInfo.distance.text.replace(/[^\d.]/g, ''));
                        const fuelLiters = distance / fuelConsumption;
                        const fuelCost = fuelLiters * fuelPrice;
                        const tollCost = calculateTollCost(distance);
                        const totalCost = fuelCost + tollCost;
                        
                        console.log('Custos calculados:', { distance, fuelLiters, fuelCost, tollCost, totalCost });
                        
                        // Exibir resultados
                        displaySimulationResults(routeInfo, distance, fuelLiters, fuelCost, tollCost, totalCost, fuelConsumption);
                        showSimulationInfo('Rota calculada com sucesso!');
                    } else {
                        console.error('Informações da rota não disponíveis');
                        showSimulationError('Informações da rota não disponíveis');
                    }
                } else {
                    console.error('Resultado do cálculo é null');
                    showSimulationError('Não foi possível calcular a rota');
                }
                
            } catch (error) {
                console.error('Erro na simulação:', error);
                showSimulationError('Erro ao simular rota: ' + error.message);
            }
        }

        // Inicializar mapa de simulação (baseado no example.html)
        async function initSimulationMap(apiKey) {
            if (simulationMap) return;

            console.log('Inicializando mapa de simulação...');

            // Verificar se Google Maps API está carregada
            if (!window.google || !window.google.maps) {
                console.log('Google Maps API não carregada, carregando...');
                await loadGoogleMapsAPI(apiKey);
            }

            // Aguardar um pouco mais para garantir que a API esteja totalmente carregada
            await new Promise(resolve => setTimeout(resolve, 1000));

            // Inicializar GoogleMapsManager
            if (!window.googleMapsManager) {
                window.googleMapsManager = new GoogleMapsManager();
            }

            // Inicializar com a chave da API
            console.log('Inicializando GoogleMapsManager...');
            await window.googleMapsManager.init(apiKey);

            // Verificar se o elemento do mapa existe
            const mapElement = document.getElementById('simulationMap');
            if (!mapElement) {
                throw new Error('Elemento simulationMap não encontrado');
            }
            
            console.log('Elemento do mapa encontrado, criando mapa...');
            
            // Criar mapa
            simulationMap = await window.googleMapsManager.createMap('simulationMap', {
                zoom: 6,
                center: { lat: -23.5505, lng: -46.6333 }
            });

            // Inicializar RouteManager
            console.log('Inicializando RouteManager...');
            
            // Verificar se RouteManager está disponível
            if (typeof RouteManager === 'undefined') {
                console.log('RouteManager não encontrado, aguardando carregamento...');
                await new Promise(resolve => setTimeout(resolve, 1000));
                
                if (typeof RouteManager === 'undefined') {
                    throw new Error('RouteManager não está disponível. Verifique se route-manager.js está carregado.');
                }
            }
            
            simulationRouteManager = new RouteManager();
            console.log('RouteManager criado:', simulationRouteManager);
            
            simulationRouteManager.init(simulationMap);
            console.log('RouteManager inicializado com mapa');
            
            console.log('Mapa de simulação inicializado com sucesso');
        }

        // Função para carregar Google Maps API
        function loadGoogleMapsAPI(apiKey) {
            return new Promise((resolve, reject) => {
                // Verificar se já está carregando
                if (window.googleMapsLoading) {
                    window.googleMapsLoading.then(resolve).catch(reject);
                    return;
                }

                // Verificar se já está carregada
                if (window.google && window.google.maps && window.google.maps.Map) {
                    console.log('Google Maps API já está carregada');
                    resolve();
                    return;
                }

                console.log('Carregando Google Maps API...');
                window.googleMapsLoading = new Promise((resolveLoading, rejectLoading) => {
                    const script = document.createElement('script');
                    script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places,geometry&loading=async`;
                    script.async = true;
                    script.defer = true;
                    
                    script.onload = () => {
                        console.log('Script Google Maps carregado, aguardando API...');
                        
                        // Aguardar um pouco para garantir que a API esteja totalmente carregada
                        const checkAPI = () => {
                            if (window.google && window.google.maps && window.google.maps.Map) {
                                console.log('Google Maps API carregada com sucesso');
                                resolveLoading();
                            } else {
                                console.log('Aguardando Google Maps API...');
                                setTimeout(checkAPI, 100);
                            }
                        };
                        
                        setTimeout(checkAPI, 500);
                    };
                    
                    script.onerror = () => {
                        console.error('Erro ao carregar Google Maps API');
                        rejectLoading(new Error('Erro ao carregar Google Maps API'));
                    };
                    
                    document.head.appendChild(script);
                });

                window.googleMapsLoading.then(resolve).catch(reject);
            });
        }

        // Exibir resultados da simulação
        function displaySimulationResults(routeInfo, distance, fuelLiters, fuelCost, tollCost, totalCost, fuelConsumption) {
            // Atualizar cards de resultados
            document.getElementById('simDistance').textContent = `${distance.toFixed(1)} km`;
            document.getElementById('simDuration').textContent = routeInfo.duration.text;
            document.getElementById('simFuelCost').textContent = `R$ ${fuelCost.toFixed(2)}`;
            document.getElementById('simFuelLiters').textContent = `${fuelLiters.toFixed(1)} litros`;
            document.getElementById('simTolls').textContent = `R$ ${tollCost.toFixed(2)}`;
            document.getElementById('simTollCount').textContent = `${Math.floor(distance / 100)} pedágios`;
            document.getElementById('simTotalCost').textContent = `R$ ${totalCost.toFixed(2)}`;
            document.getElementById('simCostPerKm').textContent = `R$ ${(totalCost / distance).toFixed(2)}/km`;
            
            // Detalhes da rota
            displayRouteDetails(routeInfo, distance, fuelLiters, fuelConsumption);
            
            // Mostrar resultados
            document.getElementById('simulationResults').style.display = 'block';
        }

        // Calcular custo de pedágios (simulação)
        function calculateTollCost(distance) {
            let tollCost = 0;
            
            if (distance > 50) tollCost += 5.00;
            if (distance > 150) tollCost += 8.50;
            if (distance > 300) tollCost += 12.00;
            if (distance > 500) tollCost += 15.00;
            if (distance > 800) tollCost += 20.00;
            
            return tollCost;
        }

        // Exibir detalhes da rota
        function displayRouteDetails(routeInfo, distance, fuelLiters, fuelConsumption) {
            const detailsDiv = document.getElementById('routeDetails');
            detailsDiv.innerHTML = '';
            
            let html = '<div style="font-size: 14px; color: #333;">';
            
            // Informações de abastecimento
            html += `
                <div style="margin-bottom: 15px; padding: 12px; background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%); border-radius: 8px; border-left: 4px solid #2196f3; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h4 style="margin: 0 0 10px 0; color: #1976d2; font-weight: 600;">⛽ Informações de Abastecimento</h4>
                    <p style="margin: 5px 0; color: #333;"><strong style="color: #1976d2;">Consumo estimado:</strong> ${fuelConsumption} km/L</p>
                    <p style="margin: 5px 0; color: #333;"><strong style="color: #1976d2;">Combustível necessário:</strong> ${fuelLiters.toFixed(1)} litros</p>
                    <p style="margin: 5px 0; color: #333;"><strong style="color: #1976d2;">Autonomia:</strong> ${(fuelConsumption * 50).toFixed(0)} km (tanque de 50L)</p>
                    <p style="margin: 5px 0; color: #333;"><strong style="color: #1976d2;">Recomendação:</strong> ${distance > (fuelConsumption * 50) ? 'Abastecer antes da viagem' : 'Tanque suficiente para a viagem'}</p>
                </div>
            `;
            
            // Informações da rota
            html += `
                <div style="margin-bottom: 15px; padding: 12px; background: linear-gradient(135deg, #e8f5e8 0%, #f1f8e9 100%); border-radius: 8px; border-left: 4px solid #4caf50; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h4 style="margin: 0 0 10px 0; color: #2e7d32; font-weight: 600;">🛣️ Informações da Rota</h4>
                    <p style="margin: 5px 0; color: #333;"><strong style="color: #2e7d32;">Origem:</strong> ${routeInfo.start_address}</p>
                    <p style="margin: 5px 0; color: #333;"><strong style="color: #2e7d32;">Destino:</strong> ${routeInfo.end_address}</p>
                    <p style="margin: 5px 0; color: #333;"><strong style="color: #2e7d32;">Distância:</strong> ${routeInfo.distance.text}</p>
                    <p style="margin: 5px 0; color: #333;"><strong style="color: #2e7d32;">Duração:</strong> ${routeInfo.duration.text}</p>
                </div>
            `;
            
            html += '</div>';
            detailsDiv.innerHTML = html;
        }

        // Funções de exibição de mensagens (baseadas no example.html)
        function showSimulationInfo(message) {
            const infoDiv = document.getElementById('simulationInfo');
            const errorDiv = document.getElementById('simulationError');
            
            if (infoDiv) {
                infoDiv.innerHTML = message;
                infoDiv.style.display = 'block';
            }
            if (errorDiv) {
                errorDiv.style.display = 'none';
            }
        }

        function showSimulationError(message) {
            const infoDiv = document.getElementById('simulationInfo');
            const errorDiv = document.getElementById('simulationError');
            
            if (errorDiv) {
                errorDiv.innerHTML = message;
                errorDiv.style.display = 'block';
            }
            if (infoDiv) {
                infoDiv.style.display = 'none';
            }
        }

        // Função para fechar o modal de simulação
        function closeSimulationModal() {
            const modal = document.getElementById('routeSimulationModal');
            if (modal) {
                modal.style.display = 'none';
                console.log('Modal de simulação fechado');
            }
        }


        // Aguardar o DOM estar carregado
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM carregado - Configurando event listeners...');
            
            // Aguardar um pouco mais para garantir que todos os scripts foram carregados
            setTimeout(function() {
                const btnMapaRotas = document.getElementById('btnMapaRotas');
                if (btnMapaRotas) {
                    console.log('Botão do mapa encontrado, configurando click...');
                    btnMapaRotas.onclick = function() {
                        console.log('Botão do mapa clicado!');
                        document.getElementById('modalMapaRotas').style.display = 'flex';
                        desenhaMapaComRotas();
                    };
                } else {
                    console.error('Botão do mapa não encontrado!');
                }

        // Função para alternar entre mapas
                const btnAlternarMapa = document.getElementById('btnAlternarMapa');
                if (btnAlternarMapa) {
                    console.log('Botão alternar mapa encontrado');
                    btnAlternarMapa.addEventListener('click', function() {
                        if (window.MapRoutes.isGoogleMapActive) {
                // Voltar para mapa Canvas
                document.getElementById('mapCanvas').style.display = 'block';
                document.getElementById('googleMap').style.display = 'none';
                            this.innerHTML = '<i class="fas fa-map-marked-alt"></i> Google Maps';
                this.style.background = '#1976d2';
                            window.MapRoutes.isGoogleMapActive = false;
                isGoogleMapActive = false;
            } else {
                // Usar Google Maps
                document.getElementById('mapCanvas').style.display = 'none';
                document.getElementById('googleMap').style.display = 'block';
                            this.innerHTML = '<i class="fas fa-map"></i> Mapa Canvas';
                this.style.background = '#4caf50';
                            window.MapRoutes.isGoogleMapActive = true;
                isGoogleMapActive = true;
                
                // Inicializar Google Maps se ainda não foi inicializado
                            if (!window.MapRoutes.googleMap) {
                    initGoogleMapsForRoutes();
                } else {
                    // Atualizar dados se já foi inicializado
                    updateGoogleMapsWithRoutes();
                }
            }
        });
                }

                // Função para limpar o mapa
                const btnLimparMapa = document.getElementById('btnLimparMapa');
                if (btnLimparMapa) {
                    btnLimparMapa.addEventListener('click', function() {
                        if (window.MapRoutes.isGoogleMapActive) {
                            // Limpar Google Maps
                            if (window.googleMapsManager) {
                                window.googleMapsManager.clearMarkers();
                            }
                            if (window.MapRoutes.routeManager) {
                                window.MapRoutes.routeManager.clearRoute();
                            }
                        } else {
                            // Limpar Canvas
                            const canvas = document.getElementById('mapCanvas');
                            const ctx = canvas.getContext('2d');
                            ctx.clearRect(0, 0, canvas.width, canvas.height);
                            
                            // Redesenhar apenas o mapa base
                            const img = new Image();
                            img.src = '/sistema-frotas/uploads/mapa/mapa-brasil.png';
                            img.onload = () => {
                                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                            };
                        }
                    });
                }

                // Modo coordenadas
                const btnModoCoordenadas = document.getElementById('btnModoCoordenadas');
                if (btnModoCoordenadas) {
                    btnModoCoordenadas.addEventListener('click', function() {
                        modoCoordenadas = !modoCoordenadas;
                        this.style.background = modoCoordenadas ? '#1976d2' : '';
                        this.style.color = modoCoordenadas ? '#fff' : '';
                        document.getElementById('coordenadaInfo').textContent = modoCoordenadas ? 'Clique no mapa para capturar X/Y' : '';
                    });
                }

                // Configurar botão de simular rota
                const btnSimularRota = document.getElementById('simulateRouteBtn');
                if (btnSimularRota) {
                    console.log('Botão simular rota encontrado');
                    btnSimularRota.addEventListener('click', function() {
                        console.log('Abrindo simulador de rotas...');
                        const modal = document.getElementById('routeSimulationModal');
                        if (modal) {
                            modal.style.display = 'block';
                            console.log('Modal aberto');
                            initRouteSimulator();
                        } else {
                            console.error('Modal não encontrado');
                        }
                    });
                } else {
                    console.error('Botão simular rota não encontrado');
                }
            }, 100); // Fim do setTimeout
        }); // Fim do DOMContentLoaded

        // Função para inicializar Google Maps
        async function initGoogleMapsForRoutes() {
            try {
                // Obter chave da API
                const response = await fetch('../google-maps/api.php?action=get_config', {
                    credentials: 'include'
                });
                const data = await response.json();
                
                if (!data.success || !data.data.google_maps_api_key) {
                    alert('Chave da API do Google Maps não configurada. Configure em Configurações > Google Maps');
                    return;
                }

                // Inicializar Google Maps
                await window.googleMapsManager.init(data.data.google_maps_api_key);
                
                // Aguardar um pouco mais para garantir que a API está totalmente carregada
                await new Promise(resolve => setTimeout(resolve, 500));
                
                // Verificar se o Google Maps está realmente disponível
                if (!window.google || !window.google.maps || !window.google.maps.Map) {
                    throw new Error('Google Maps API não carregou completamente');
                }
                
                window.MapRoutes.googleMap = await window.googleMapsManager.createMap('googleMap', {
                    zoom: 5,
                    center: { lat: -14.2350, lng: -51.9253 }, // Centro do Brasil
                    mapTypeId: 'roadmap' // Usar string em vez de google.maps.MapTypeId
                });
                googleMap = window.MapRoutes.googleMap;

                // Inicializar gerenciador de rotas
                window.MapRoutes.routeManager = new RouteManager();
                window.MapRoutes.routeManager.init(googleMap);
                routeManager = window.MapRoutes.routeManager;

                // Carregar dados das rotas
                await loadRoutesData();
                updateGoogleMapsWithRoutes();

            } catch (error) {
                console.error('Erro ao inicializar Google Maps:', error);
                alert('Erro ao carregar Google Maps: ' + error.message);
            }
        }

        // Função para carregar dados das rotas
        async function loadRoutesData() {
            try {
                // Pega o mês/ano do filtro, ou usa o atual
                let mes, ano;
                const filtro = document.getElementById('filtroMesMapa');
                if (filtro && filtro.value) {
                    [ano, mes] = filtro.value.split('-');
                } else {
                    const data = new Date();
                    mes = data.getMonth() + 1;
                    ano = data.getFullYear();
                }

                const response = await fetch(`../api/rotas_google_maps.php?mes=${mes}&ano=${ano}`, {
                    credentials: 'include'
                });
                const data = await response.json();
                
                if (data.success) {
                    window.MapRoutes.routesData = data.data;
                    routesData = window.MapRoutes.routesData;
                } else {
                    console.error('Erro ao carregar dados das rotas:', data.error);
                    window.MapRoutes.routesData = [];
                    routesData = window.MapRoutes.routesData;
                }
            } catch (error) {
                console.error('Erro ao carregar dados das rotas:', error);
                routesData = [];
            }
        }

        // Função para atualizar Google Maps com as rotas
        function updateGoogleMapsWithRoutes() {
            if (!window.MapRoutes.googleMap || !window.MapRoutes.routesData.length) {
                console.log('Google Maps não inicializado ou sem dados de rotas');
                return;
            }

            // Limpar marcadores existentes
            window.googleMapsManager.clearMarkers();

            // Adicionar marcadores para cada rota
            console.log('Dados das rotas:', window.MapRoutes.routesData);
            window.MapRoutes.routesData.forEach((route, index) => {
                const color = getColor(index);
                console.log(`Processando rota ${index + 1}:`, route);
                
                // Marcador de origem
                if (route.origem.latitude && route.origem.longitude) {
                    const originMarker = window.googleMapsManager.addMarker(
                        { lat: route.origem.latitude, lng: route.origem.longitude },
                        {
                            title: `Origem: ${route.origem.cidade}, ${route.origem.estado}`,
                            icon: {
                                url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                                    <svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                                        <circle cx="16" cy="16" r="12" fill="${color}" stroke="#fff" stroke-width="2"/>
                                        <text x="16" y="20" text-anchor="middle" fill="white" font-family="Arial" font-size="12" font-weight="bold">O</text>
                                    </svg>
                                `),
                                scaledSize: new google.maps.Size(32, 32),
                                anchor: new google.maps.Point(16, 16)
                            }
                        }
                    );

                    // Info window para origem
                    const originInfoWindow = new google.maps.InfoWindow({
                        content: `
                            <div style="padding: 12px; min-width: 250px; background: #f8f9fa; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                                <h4 style="margin: 0 0 12px 0; color: ${color}; font-size: 16px; font-weight: bold; border-bottom: 2px solid ${color}; padding-bottom: 8px;">📍 Origem</h4>
                                <div style="color: #333; line-height: 1.6;">
                                    <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Cidade:</strong> ${route.origem.cidade || 'N/A'}, ${route.origem.estado || 'N/A'}</p>
                                    <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Motorista:</strong> ${route.motorista.nome || 'N/A'}</p>
                                    <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Veículo:</strong> ${route.veiculo.placa || 'N/A'}</p>
                                    <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Data:</strong> ${route.data_rota ? new Date(route.data_rota).toLocaleDateString('pt-BR') : 'N/A'}</p>
                                    <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Distância:</strong> ${route.distancia_km || 0} km</p>
                                    <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Frete:</strong> R$ ${route.frete ? route.frete.toFixed(2) : '0.00'}</p>
                                    <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Status:</strong> <span style="color: ${route.no_prazo ? '#28a745' : '#dc3545'}; font-weight: bold;">${route.no_prazo ? 'No Prazo' : 'Atrasado'}</span></p>
                                </div>
                            </div>
                        `
                    });

                    originMarker.addListener('click', () => {
                        originInfoWindow.open(window.MapRoutes.googleMap, originMarker);
                    });
                }

                // Marcador de destino
                if (route.destino.latitude && route.destino.longitude) {
                    const destinationMarker = window.googleMapsManager.addMarker(
                        { lat: route.destino.latitude, lng: route.destino.longitude },
                        {
                            title: `Destino: ${route.destino.cidade}, ${route.destino.estado}`,
                            icon: {
                                url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                                    <svg width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                                        <circle cx="16" cy="16" r="12" fill="${color}" stroke="#fff" stroke-width="2"/>
                                        <text x="16" y="20" text-anchor="middle" fill="white" font-family="Arial" font-size="12" font-weight="bold">D</text>
                                    </svg>
                                `),
                                scaledSize: new google.maps.Size(32, 32),
                                anchor: new google.maps.Point(16, 16)
                            }
                        }
                    );

                    // Info window para destino
                    const destinationInfoWindow = new google.maps.InfoWindow({
                        content: `
                            <div style="padding: 12px; min-width: 250px; background: #f8f9fa; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                                <h4 style="margin: 0 0 12px 0; color: ${color}; font-size: 16px; font-weight: bold; border-bottom: 2px solid ${color}; padding-bottom: 8px;">🎯 Destino</h4>
                                <div style="color: #333; line-height: 1.6;">
                                    <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Cidade:</strong> ${route.destino.cidade || 'N/A'}, ${route.destino.estado || 'N/A'}</p>
                                    <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Motorista:</strong> ${route.motorista.nome || 'N/A'}</p>
                                    <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Veículo:</strong> ${route.veiculo.placa || 'N/A'}</p>
                                    <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Data:</strong> ${route.data_rota ? new Date(route.data_rota).toLocaleDateString('pt-BR') : 'N/A'}</p>
                                    <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Distância:</strong> ${route.distancia_km || 0} km</p>
                                    <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Frete:</strong> R$ ${route.frete ? route.frete.toFixed(2) : '0.00'}</p>
                                    <p style="margin: 6px 0; font-size: 14px;"><strong style="color: #555;">Status:</strong> <span style="color: ${route.no_prazo ? '#28a745' : '#dc3545'}; font-weight: bold;">${route.no_prazo ? 'No Prazo' : 'Atrasado'}</span></p>
                                </div>
                            </div>
                        `
                    });

                    destinationMarker.addListener('click', () => {
                        destinationInfoWindow.open(window.MapRoutes.googleMap, destinationMarker);
                    });
                }

                // Desenhar rota se ambos os pontos existirem
                if (route.origem.latitude && route.origem.longitude && route.destino.latitude && route.destino.longitude) {
                    const origin = `${route.origem.latitude},${route.origem.longitude}`;
                    const destination = `${route.destino.latitude},${route.destino.longitude}`;
                    
                    // Usar o RouteManager para desenhar a rota
                    if (window.MapRoutes.routeManager) {
                        window.MapRoutes.routeManager.calculateRoute(origin, destination, {
                            polylineOptions: {
                                strokeColor: color,
                                strokeWeight: 4,
                                strokeOpacity: 0.8
                            }
                        })
                        .then(result => {
                            if (result) {
                            console.log('Rota calculada:', result);
                                // Desenhar polilinha customizada
                                window.MapRoutes.routeManager.drawRoutePolyline(result, color);
                            } else {
                                console.warn(`Nenhuma rota encontrada entre ${origin} e ${destination}`);
                            }
                        })
                        .catch(error => {
                            console.warn('Erro ao calcular rota:', error.message);
                        });
                    }
                }
            });

            // Ajustar zoom para mostrar todas as rotas
            if (window.MapRoutes.routesData.length > 0) {
                const bounds = new google.maps.LatLngBounds();
                window.MapRoutes.routesData.forEach(route => {
                    if (route.origem.latitude && route.origem.longitude) {
                        bounds.extend(new google.maps.LatLng(route.origem.latitude, route.origem.longitude));
                    }
                    if (route.destino.latitude && route.destino.longitude) {
                        bounds.extend(new google.maps.LatLng(route.destino.latitude, route.destino.longitude));
                    }
                });
                window.MapRoutes.googleMap.fitBounds(bounds);
            }
        }

        // Função para atualizar estatísticas do mapa
        function updateMapStats() {
            if (!window.MapRoutes.routesData || window.MapRoutes.routesData.length === 0) {
                document.getElementById('totalRotasMapa').textContent = '0';
                document.getElementById('totalKmMapa').textContent = '0';
                document.getElementById('totalFreteMapa').textContent = 'R$ 0,00';
                return;
            }

            const totalRotas = window.MapRoutes.routesData.length;
            const totalKm = window.MapRoutes.routesData.reduce((sum, route) => sum + (route.distancia_km || 0), 0);
            const totalFrete = window.MapRoutes.routesData.reduce((sum, route) => sum + (route.frete || 0), 0);

            document.getElementById('totalRotasMapa').textContent = totalRotas;
            document.getElementById('totalKmMapa').textContent = totalKm.toFixed(0);
            document.getElementById('totalFreteMapa').textContent = `R$ ${totalFrete.toFixed(2)}`;
        }

        // Modificar a função de filtrar para funcionar com ambos os mapas
        const originalDesenhaMapaComRotas = desenhaMapaComRotas;
        desenhaMapaComRotas = function() {
            if (window.MapRoutes.isGoogleMapActive) {
                // Se estiver usando Google Maps, recarregar dados
                loadRoutesData().then(() => {
                    updateGoogleMapsWithRoutes();
                    updateMapStats();
                });
            } else {
                // Usar função original do Canvas
                originalDesenhaMapaComRotas();
                // Atualizar estatísticas para o mapa Canvas também
                loadRoutesData().then(() => {
                    updateMapStats();
                });
            }
        };

        // ===== VALIDAÇÃO DE QUILOMETRAGEM =====
        
        // Função para validar KM Saída da rota
        async function validarKmSaidaRota(veiculoId, kmSaida) {
            if (!veiculoId || !kmSaida) {
                return { valido: false, mensagem: 'Dados insuficientes para validação' };
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'validar_km_saida_rota');
                formData.append('veiculo_id', veiculoId);
                formData.append('km_saida', kmSaida);
                
                const response = await fetch('../api/validar_quilometragem.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                return data;
            } catch (error) {
                console.error('Erro na validação de quilometragem:', error);
                return { valido: false, mensagem: 'Erro na validação' };
            }
        }
        
        // Função para obter quilometragem atual do veículo
        async function obterKmAtualVeiculo(veiculoId) {
            if (!veiculoId) return null;
            
            try {
                const response = await fetch(`../api/validar_quilometragem.php?action=obter_km_atual_veiculo&veiculo_id=${veiculoId}`);
                const data = await response.json();
                return data.success ? data.km_atual : null;
            } catch (error) {
                console.error('Erro ao obter quilometragem do veículo:', error);
                return null;
            }
        }
        
        // Configurar validação quando veículo for selecionado
        function configurarValidacaoKmSaida() {
            const veiculoSelect = document.getElementById('veiculo_id');
            const kmSaidaInput = document.getElementById('km_saida');
            const kmSaidaHelp = document.getElementById('km_saida_help');
            const kmSaidaValidation = document.getElementById('km_saida_validation');
            
            if (!veiculoSelect || !kmSaidaInput) return;
            
            // Quando veículo for selecionado
            veiculoSelect.addEventListener('change', async function() {
                const veiculoId = this.value;
                
                if (veiculoId) {
                    const kmAtual = await obterKmAtualVeiculo(veiculoId);
                    if (kmAtual !== null) {
                        kmSaidaHelp.innerHTML = `<i class="fas fa-info-circle"></i> Quilometragem atual do veículo: ${kmAtual.toLocaleString('pt-BR')} km`;
                        kmSaidaInput.placeholder = `Mín: ${kmAtual.toLocaleString('pt-BR')}`;
                        kmSaidaInput.min = kmAtual;
                    }
                } else {
                    kmSaidaHelp.innerHTML = '<i class="fas fa-info-circle"></i> Selecione um veículo para validar a quilometragem';
                    kmSaidaInput.placeholder = 'Ex: 150000';
                    kmSaidaInput.min = '';
                }
                
                // Limpar validação anterior
                kmSaidaValidation.innerHTML = '';
            });
            
            // Quando KM Saída for digitado
            kmSaidaInput.addEventListener('blur', async function() {
                const veiculoId = veiculoSelect.value;
                const kmSaida = this.value;
                
                if (veiculoId && kmSaida) {
                    const validacao = await validarKmSaidaRota(veiculoId, kmSaida);
                    
                    if (validacao.valido) {
                        kmSaidaValidation.innerHTML = `<div style="color: #28a745;"><i class="fas fa-check-circle"></i> ${validacao.mensagem}</div>`;
                        kmSaidaInput.style.borderColor = '#28a745';
                    } else {
                        kmSaidaValidation.innerHTML = `<div style="color: #dc3545;"><i class="fas fa-exclamation-triangle"></i> ${validacao.mensagem}</div>`;
                        kmSaidaInput.style.borderColor = '#dc3545';
                    }
                } else {
                    kmSaidaValidation.innerHTML = '';
                    kmSaidaInput.style.borderColor = '';
                }
            });
        }
        
        // Inicializar validação quando DOM estiver carregado
        document.addEventListener('DOMContentLoaded', function() {
            // Aguardar um pouco para garantir que todos os elementos estejam carregados
            setTimeout(() => {
                configurarValidacaoKmSaida();
            }, 500);
        });
        
    </script>
</body>
</html>

