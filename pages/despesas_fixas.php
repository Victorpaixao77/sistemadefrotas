<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';

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

// Função para buscar despesas fixas do banco de dados
function getDespesasFixas($page = 1) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        $limit = 5; // Registros por página
        $offset = ($page - 1) * $limit;
        
        // Primeiro, conta o total de registros
        $sql_count = "SELECT COUNT(*) as total FROM despesas_fixas WHERE empresa_id = :empresa_id";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_count->execute();
        $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Consulta paginada
        $sql = "SELECT df.*, v.placa as veiculo_placa, td.nome as tipo_nome, sp.nome as status_nome,
                       fp.nome as forma_pagamento_nome, df.comprovante
                FROM despesas_fixas df
                LEFT JOIN veiculos v ON df.veiculo_id = v.id
                LEFT JOIN tipos_despesa_fixa td ON df.tipo_despesa_id = td.id
                LEFT JOIN status_pagamento sp ON df.status_pagamento_id = sp.id
                LEFT JOIN formas_pagamento fp ON df.forma_pagamento_id = fp.id
                WHERE df.empresa_id = :empresa_id
                ORDER BY df.vencimento DESC, df.id DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'despesas' => $result,
            'total' => $total,
            'pagina_atual' => $page,
            'total_paginas' => ceil($total / $limit)
        ];
    } catch(PDOException $e) {
        error_log("Erro ao buscar despesas fixas: " . $e->getMessage());
        return [
            'despesas' => [],
            'total' => 0,
            'pagina_atual' => 1,
            'total_paginas' => 1
        ];
    }
}

// Pegar a página atual da URL ou definir como 1
$pagina_atual = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Buscar despesas com paginação
$resultado = getDespesasFixas($pagina_atual);
$despesas = $resultado['despesas'];
$total_paginas = $resultado['total_paginas'];
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
    <link rel="stylesheet" href="../css/maintenance.css">
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Sortable.js for drag-and-drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
    <!-- Custom scripts -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/despesas_fixas.js"></script>
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
                            <h3>Total de Despesas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">0</span>
                                <span class="metric-subtitle">Total este mês</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Valor Total</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ 0,00</span>
                                <span class="metric-subtitle">Total este mês</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Pendentes</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">0</span>
                                <span class="metric-subtitle">Despesas pendentes</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Vencidas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">0</span>
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
                
                <!-- Despesas Table -->
                <div class="data-table-container">
                    <table class="data-table" id="despesasTable">
                        <thead>
                            <tr>
                                <th>Vencimento</th>
                                <th>Veículo</th>
                                <th>Tipo</th>
                                <th>Descrição</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Data Pagamento</th>
                                <th>Forma Pgto</th>
                                <th>Repetir</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($despesas)): ?>
                                <?php foreach ($despesas as $despesa): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($despesa['vencimento'])); ?></td>
                                    <td><?php echo htmlspecialchars($despesa['veiculo_placa']); ?></td>
                                    <td><?php echo htmlspecialchars($despesa['tipo_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($despesa['descricao']); ?></td>
                                    <td>R$ <?php echo number_format($despesa['valor'], 2, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($despesa['status_nome']); ?></td>
                                    <td><?php echo $despesa['data_pagamento'] ? date('d/m/Y', strtotime($despesa['data_pagamento'])) : '-'; ?></td>
                                    <td><?php echo htmlspecialchars($despesa['forma_pagamento_nome']); ?></td>
                                    <td><?php echo $despesa['repetir_automaticamente'] ? 'Sim' : 'Não'; ?></td>
                                    <td class="actions">
                                        <button class="btn-icon edit-btn" data-id="<?php echo $despesa['id']; ?>" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if (!empty($despesa['comprovante'])): ?>
                                            <button class="btn-icon view-comprovante-btn" data-comprovante="<?php echo htmlspecialchars($despesa['comprovante']); ?>" title="Ver Comprovante">
                                                <i class="fas fa-file-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn-icon delete-btn" data-id="<?php echo $despesa['id']; ?>" title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center">Nenhuma despesa fixa encontrada</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginação -->
                <div class="pagination">
                    <a href="?page=<?php echo max(1, $pagina_atual - 1); ?>" 
                       class="pagination-btn <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    
                    <span class="pagination-info">
                        Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?>
                    </span>
                    
                    <a href="?page=<?php echo min($total_paginas, $pagina_atual + 1); ?>" 
                       class="pagination-btn <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>

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
    <div class="modal" id="despesaModal">
        <div class="modal-content">
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
    <div class="modal" id="filterModal">
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
    <div class="modal" id="helpModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ajuda - Despesas Fixas</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="help-section">
                    <h3>Visão Geral</h3>
                    <p>O módulo de Despesas Fixas permite gerenciar todas as despesas recorrentes da sua frota, como IPVA, seguro, licenciamento e outras despesas fixas.</p>
                </div>

                <div class="help-section">
                    <h3>Funcionalidades Principais</h3>
                    <ul>
                        <li><strong>Nova Despesa Fixa:</strong> Registre novas despesas com informações como:
                            <ul>
                                <li>Tipo de despesa</li>
                                <li>Veículo associado</li>
                                <li>Valor e vencimento</li>
                                <li>Status de pagamento</li>
                                <li>Forma de pagamento</li>
                                <li>Opção de repetição automática</li>
                            </ul>
                        </li>
                        <li><strong>Filtros:</strong> Filtre os dados por:
                            <ul>
                                <li>Mês/Ano</li>
                                <li>Veículo</li>
                                <li>Tipo de despesa</li>
                                <li>Status de pagamento</li>
                                <li>Forma de pagamento</li>
                            </ul>
                        </li>
                        <li><strong>Análises:</strong> Visualize:
                            <ul>
                                <li>Total de despesas no período</li>
                                <li>Valor total das despesas</li>
                                <li>Despesas pendentes e vencidas</li>
                                <li>Gráficos de distribuição e evolução</li>
                            </ul>
                        </li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Como Usar</h3>
                    <ol>
                        <li><strong>Registrar Despesa:</strong>
                            <ul>
                                <li>Clique no botão "Nova Despesa Fixa"</li>
                                <li>Preencha todos os campos obrigatórios (*)</li>
                                <li>Configure as opções de repetição e notificação</li>
                                <li>Clique em "Salvar"</li>
                            </ul>
                        </li>
                        <li><strong>Filtrar Dados:</strong>
                            <ul>
                                <li>Use os filtros rápidos na página</li>
                                <li>Ou clique no botão de filtro para opções avançadas</li>
                                <li>Selecione o período e outros critérios</li>
                                <li>Clique em "Aplicar" para ver os dados filtrados</li>
                            </ul>
                        </li>
                        <li><strong>Gerenciar Registros:</strong>
                            <ul>
                                <li>Use os botões de editar para modificar uma despesa</li>
                                <li>Use os botões de excluir para remover uma despesa</li>
                                <li>Acompanhe os status e vencimentos</li>
                            </ul>
                        </li>
                    </ol>
                </div>

                <div class="help-section">
                    <h3>Dicas Úteis</h3>
                    <ul>
                        <li>Configure notificações para não perder vencimentos</li>
                        <li>Use a repetição automática para despesas recorrentes</li>
                        <li>Mantenha os comprovantes de pagamento organizados</li>
                        <li>Acompanhe a evolução das despesas nos gráficos</li>
                        <li>Exporte os dados para análises detalhadas</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('helpModal')">Fechar</button>
            </div>
        </div>
    </div>

    <!-- Initialize page -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize components
            initializeFilters();
            initializeCharts();
            setupEventListeners();
            loadDespesasData();
        });

        function initializeFilters() {
            // Load vehicles for filter
            loadVehicles();
            
            // Load payment methods for filter
            loadPaymentMethods();
        }
        
        function initializeCharts() {
            // Initialize charts with empty data
            initializeDespesasTipoChart();
            initializeStatusDespesasChart();
            initializeTopVeiculosChart();
            initializeFormasPagamentoChart();
        }

        function setupEventListeners() {
            // Add event listeners for buttons and forms
            document.getElementById('addDespesaBtn').addEventListener('click', showAddDespesaModal);
            document.getElementById('filterBtn').addEventListener('click', showFilterModal);
            document.getElementById('helpBtn').addEventListener('click', showHelpModal);
            
            // Setup form submission
            document.getElementById('despesaForm').addEventListener('submit', handleDespesaSubmit);
            
            // Setup search and filter events
            document.getElementById('searchDespesa').addEventListener('input', debounce(loadDespesasData, 500));
            document.querySelectorAll('.filter-options select').forEach(select => {
                select.addEventListener('change', loadDespesasData);
            });
            
            // Adicionar evento para visualizar comprovante
            const viewComprovanteButtons = document.querySelectorAll('.view-comprovante-btn');
            viewComprovanteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const comprovante = this.getAttribute('data-comprovante');
                    window.open('../' + comprovante, '_blank');
                });
            });
        }
    </script>
</body>
</html> 