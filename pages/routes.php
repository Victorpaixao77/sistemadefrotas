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

// Função para buscar rotas do banco de dados
function getRotas($page = 1) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        $limit = 5; // Registros por página
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
$resultado = getRotas($pagina_atual);
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
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    </div>
                </div>
                
                <!-- Route Table -->
                <div class="table-container">
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
                                    <button class="btn-icon view-btn" data-id="<?php echo $rota['id']; ?>" title="Ver detalhes">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon edit-btn" data-id="<?php echo $rota['id']; ?>" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon expenses-btn" data-id="<?php echo $rota['id']; ?>" title="Despesas de Viagem">
                                        <i class="fas fa-money-bill"></i>
                                    </button>
                                    <button class="btn-icon delete-btn" data-id="<?php echo $rota['id']; ?>" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="pagination">
                    <?php if ($total_paginas > 1): ?>
                        <a href="#" class="pagination-btn <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>" 
                           onclick="return changePage(<?php echo $pagina_atual - 1; ?>)">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        
                        <span class="pagination-info">
                            Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?>
                        </span>
                        
                        <a href="#" class="pagination-btn <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>"
                           onclick="return changePage(<?php echo $pagina_atual + 1; ?>)">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
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
                                <input type="number" id="km_saida" name="km_saida" step="0.01">
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
    <div class="modal" id="viewRouteModal">
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h2 id="viewModalTitle">Detalhes da Rota</h2>
                <span class="close-modal close-view-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="details-container">
                    <div class="route-header">
                        <div class="route-primary-info">
                            <h3 id="routeOriginDestination">São Paulo, SP → Rio de Janeiro, RJ</h3>
                            <div class="route-status" id="routeStatus">Concluída</div>
                        </div>
                        <div class="route-date" id="routeDate">07/05/2025</div>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-group">
                            <label>Motorista</label>
                            <div id="detailDriver"></div>
                        </div>
                        <div class="info-group">
                            <label>Veículo</label>
                            <div id="detailVehicle"></div>
                        </div>
                        <div class="info-group">
                            <label>Distância</label>
                            <div id="detailDistance"></div>
                        </div>
                        <div class="info-group">
                            <label>Status</label>
                            <div id="detailStatus"></div>
                        </div>
                        <div class="info-group">
                            <label>Horário de Saída</label>
                            <div id="detailStartTime"></div>
                        </div>
                        <div class="info-group">
                            <label>Horário de Chegada</label>
                            <div id="detailEndTime"></div>
                        </div>
                        <div class="info-group">
                            <label>Duração</label>
                            <div id="detailDuration"></div>
                        </div>
                        <div class="info-group">
                            <label>Consumo de Combustível</label>
                            <div id="detailFuelConsumption"></div>
                        </div>
                    </div>
                    
                    <div class="address-info">
                        <div class="address-card">
                            <h4>Endereço de Origem</h4>
                            <p id="detailOriginAddress"></p>
                        </div>
                        <div class="address-card">
                            <h4>Endereço de Destino</h4>
                            <p id="detailDestinationAddress"></p>
                        </div>
                    </div>
                    
                    <div class="cargo-info">
                        <h4>Informações da Carga</h4>
                        <div class="info-grid">
                            <div class="info-group">
                                <label>Descrição</label>
                                <div id="detailCargoDescription"></div>
                            </div>
                            <div class="info-group">
                                <label>Peso</label>
                                <div id="detailCargoWeight"></div>
                            </div>
                            <div class="info-group">
                                <label>Cliente</label>
                                <div id="detailCustomer"></div>
                            </div>
                            <div class="info-group">
                                <label>Contato</label>
                                <div id="detailCustomerContact"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="cost-summary">
                        <h4>Resumo de Custos</h4>
                        <div class="cost-cards">
                            <div class="cost-card">
                                <h5>Custo Estimado</h5>
                                <div class="cost-value" id="estimatedCostValue">R$ 0,00</div>
                            </div>
                            <div class="cost-card">
                                <h5>Custo Real</h5>
                                <div class="cost-value" id="actualCostValue">R$ 0,00</div>
                            </div>
                            <div class="cost-card">
                                <h5>Diferença</h5>
                                <div class="cost-value" id="costDifference">R$ 0,00</div>
                            </div>
                        </div>
                        
                        <div class="cost-breakdown">
                            <h4>Detalhamento de Custos</h4>
                            <div class="cost-breakdown-table">
                                <table class="info-table">
                                    <thead>
                                        <tr>
                                            <th>Categoria</th>
                                            <th>Valor (R$)</th>
                                            <th>Percentual</th>
                                        </tr>
                                    </thead>
                                    <tbody id="costBreakdownBody">
                                        <!-- Populated by JavaScript -->
                                    </tbody>
                                </table>
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
                <button id="editFromDetailsBtn" class="btn-primary">Editar</button>
            </div>
        </div>
    </div>
    
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
                    <label for="filterMonth">Selecione o Mês/Ano</label>
                    <input type="month" id="filterMonth" name="filterMonth">
                </div>
            </div>
            <div class="modal-footer">
                <button id="clearFilterBtn" class="btn-secondary">Limpar Filtro</button>
                <button id="applyFilterBtn" class="btn-primary">Aplicar</button>
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
                            <label for="arla">ARLA</label>
                            <input type="number" id="arla" name="arla" step="0.01" min="0">
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
        <div style="text-align:center; margin-bottom: 10px;">
          <input type="month" id="filtroMesMapa" style="font-size:1rem; padding:4px;">
          <button onclick="desenhaMapaComRotas()" style="font-size:1rem; padding:4px 12px;">Filtrar</button>
        </div>
        <button id="btnModoCoordenadas" style="font-size:1rem; padding:4px 12px; margin-left:10px;">Modo Coordenadas</button>
        <span id="coordenadaInfo" style="margin-left:10px; color:#1976d2; font-weight:bold;"></span>
        <canvas id="mapCanvas" width="800" height="700" style="display: block; margin: 0 auto;"></canvas>
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

    <!-- JavaScript Files -->
    <script src="../js/header.js"></script>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/routes.js"></script>
    
    <script>
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

        document.getElementById('btnMapaRotas').onclick = function() {
            document.getElementById('modalMapaRotas').style.display = 'flex';
            desenhaMapaComRotas();
        };

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
        document.getElementById('btnModoCoordenadas').onclick = function() {
            modoCoordenadas = !modoCoordenadas;
            this.style.background = modoCoordenadas ? '#1976d2' : '';
            this.style.color = modoCoordenadas ? '#fff' : '';
            document.getElementById('coordenadaInfo').textContent = modoCoordenadas ? 'Clique no mapa para capturar X/Y' : '';
        };

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
    </script>
</body>
</html>
