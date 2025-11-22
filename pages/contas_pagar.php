<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Set page title
$page_title = "Contas a Pagar";

// Obter conexão com o banco de dados
$conn = getConnection();

// Função para buscar contas com paginação
function getContasPagar($page = 1) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        $limit = 5; // Registros por página
        $offset = ($page - 1) * $limit;
        
        // Primeiro, conta o total de registros
        $sql_count = "SELECT COUNT(*) as total FROM contas_pagar WHERE empresa_id = :empresa_id";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_count->execute();
        $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Consulta paginada
        $sql = "SELECT cp.*, 
            s.nome as status_nome,
            fp.nome as forma_pagamento_nome,
            b.nome as banco_nome
        FROM contas_pagar cp
        LEFT JOIN status_contas_pagar s ON cp.status_id = s.id
        LEFT JOIN formas_pagamento fp ON cp.forma_pagamento_id = fp.id
        LEFT JOIN bancos b ON cp.banco_id = b.id
        WHERE cp.empresa_id = :empresa_id
        ORDER BY cp.data_vencimento DESC, cp.id DESC
        LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'contas' => $result,
            'total' => $total,
            'pagina_atual' => $page,
            'total_paginas' => ceil($total / $limit)
        ];
    } catch(PDOException $e) {
        error_log("Erro ao buscar contas a pagar: " . $e->getMessage());
        return [
            'contas' => [],
            'total' => 0,
            'pagina_atual' => 1,
            'total_paginas' => 1
        ];
    }
}

// Pegar a página atual da URL ou definir como 1
$pagina_atual = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Buscar contas com paginação
$resultado = getContasPagar($pagina_atual);
$contas = $resultado['contas'];
$total_paginas = $resultado['total_paginas'];

// Buscar dados para os KPIs
$sql_kpis = "SELECT 
    SUM(CASE WHEN status_id = 1 THEN valor ELSE 0 END) as total_pagar,
    COUNT(CASE WHEN status_id = 4 THEN 1 END) as contas_vencidas,
    SUM(CASE WHEN status_id = 4 THEN valor ELSE 0 END) as valor_vencidas,
    COUNT(CASE WHEN status_id = 1 AND data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as contas_vencer,
    SUM(CASE WHEN status_id = 1 AND data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN valor ELSE 0 END) as valor_vencer,
    COUNT(CASE WHEN status_id = 2 AND MONTH(data_pagamento) = MONTH(CURDATE()) AND YEAR(data_pagamento) = YEAR(CURDATE()) THEN 1 END) as contas_pagas,
    SUM(CASE WHEN status_id = 2 AND MONTH(data_pagamento) = MONTH(CURDATE()) AND YEAR(data_pagamento) = YEAR(CURDATE()) THEN valor ELSE 0 END) as valor_pagas
FROM contas_pagar 
WHERE empresa_id = :empresa_id";

$kpis = fetchOne($conn, $sql_kpis, [':empresa_id' => $_SESSION['empresa_id']]);

// Buscar dados para o gráfico de categorias
$sql_categorias = "SELECT 
    COUNT(*) as quantidade,
    SUM(valor) as total
FROM contas_pagar 
WHERE empresa_id = :empresa_id 
AND MONTH(data_vencimento) = MONTH(CURDATE()) 
AND YEAR(data_vencimento) = YEAR(CURDATE())
GROUP BY status_id";

$stmt = $conn->prepare($sql_categorias);
$stmt->bindValue(':empresa_id', $_SESSION['empresa_id']);
$stmt->execute();
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar dados para o gráfico de evolução
$sql_evolucao = "SELECT 
    MONTH(data_vencimento) as mes,
    YEAR(data_vencimento) as ano,
    SUM(valor) as total
FROM contas_pagar 
WHERE empresa_id = :empresa_id 
AND data_vencimento >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
GROUP BY YEAR(data_vencimento), MONTH(data_vencimento)
ORDER BY ano, mes";

$stmt = $conn->prepare($sql_evolucao);
$stmt->bindValue(':empresa_id', $_SESSION['empresa_id']);
$stmt->execute();
$evolucao = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    
    <style>
        /* Pagination Styles */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .pagination-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: var(--btn-border-radius);
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            transition: all var(--transition-speed) ease;
        }
        
        .pagination-btn:hover:not(.disabled) {
            background-color: var(--accent-primary);
            color: white;
        }
        
        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .pagination-info {
            color: var(--text-secondary);
            font-size: 0.875rem;
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
                    <h1><?php echo $page_title; ?></h1>
                    <div class="dashboard-actions">
                        <button id="addContaBtn" class="btn-add-widget">
                            <i class="fas fa-plus"></i> Nova Conta
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
                            <h3>Total a Pagar</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="totalPagar">R$ <?php echo number_format($kpis['total_pagar'] ?? 0, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Este mês</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Contas Vencidas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="contasVencidas"><?php echo $kpis['contas_vencidas'] ?? 0; ?></span>
                                <span class="metric-subtitle">R$ <?php echo number_format($kpis['valor_vencidas'] ?? 0, 2, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>A Vencer (7 dias)</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="contasVencer"><?php echo $kpis['contas_vencer'] ?? 0; ?></span>
                                <span class="metric-subtitle">R$ <?php echo number_format($kpis['valor_vencer'] ?? 0, 2, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Pagas (Este Mês)</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="contasPagas"><?php echo $kpis['contas_pagas'] ?? 0; ?></span>
                                <span class="metric-subtitle">R$ <?php echo number_format($kpis['valor_pagas'] ?? 0, 2, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Search and Filter -->
                <div class="filter-section">
                    <div class="search-box">
                        <input type="text" id="searchConta" placeholder="Buscar conta...">
                        <i class="fas fa-search"></i>
                    </div>
                    
                    <div class="filter-options">
                        <select id="statusFilter">
                            <option value="">Todos os status</option>
                            <option value="Pendente">Pendente</option>
                            <option value="Vencida">Vencida</option>
                            <option value="Paga">Paga</option>
                            <option value="Cancelada">Cancelada</option>
                        </select>
                        
                        <select id="categoriaFilter">
                            <option value="">Todas as categorias</option>
                            <option value="Fornecedores">Fornecedores</option>
                            <option value="Serviços">Serviços</option>
                            <option value="Manutenção">Manutenção</option>
                            <option value="Combustível">Combustível</option>
                            <option value="Outros">Outros</option>
                        </select>
                        
                        <input type="month" id="mesFilter" placeholder="Filtrar por mês">
                        
                        <button type="button" class="btn-restore-layout" id="applyAccountsFilters" title="Aplicar filtros">
                            <i class="fas fa-filter"></i>
                        </button>
                        <button type="button" class="btn-restore-layout" id="clearAccountsFilters" title="Limpar filtros">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Accounts Table -->
                <div class="data-table-container">
                    <table class="data-table" id="contasTable">
                        <thead>
                            <tr>
                                <th>Vencimento</th>
                                <th>Descrição</th>
                                <th>Valor</th>
                                <th>Status</th>
                                <th>Fornecedor</th>
                                <th>Forma Pagto</th>
                                <th>Banco</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contas as $conta): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($conta['data_vencimento'])); ?></td>
                                <td><?php echo htmlspecialchars($conta['descricao']); ?></td>
                                <td>R$ <?php echo number_format($conta['valor'], 2, ',', '.'); ?></td>
                                <td><span class="status-badge status-<?php echo strtolower($conta['status_nome']); ?>"><?php echo htmlspecialchars($conta['status_nome']); ?></span></td>
                                <td><?php echo htmlspecialchars($conta['fornecedor']); ?></td>
                                <td><?php echo htmlspecialchars($conta['forma_pagamento_nome'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($conta['banco_nome'] ?? '-'); ?></td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-icon view-btn" data-id="<?php echo $conta['id']; ?>" title="Ver detalhes">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if (!empty($conta['recibo_arquivo'])): ?>
                                        <button class="btn-icon view-receipt-btn" data-id="<?php echo $conta['id']; ?>" title="Ver recibo">
                                            <i class="fas fa-file-invoice"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button class="btn-icon edit-btn" data-id="<?php echo $conta['id']; ?>" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-icon delete-btn" data-id="<?php echo $conta['id']; ?>" title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="pagination">
                    <a href="#" class="pagination-btn <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>" id="prevPage">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <span class="pagination-info">Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?></span>
                    <a href="#" class="pagination-btn <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>" id="nextPage">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                
                <!-- Financial Analytics -->
                <div class="analytics-section">
                    <div class="section-header">
                        <h2>Análise Financeira</h2>
                    </div>
                    
                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Contas por Status</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="categoriasChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Evolução Mensal</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="evolucaoChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    
    <!-- Add/Edit Account Modal -->
    <div class="modal" id="contaModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Adicionar Conta</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="contaForm">
                    <input type="hidden" id="id" name="id">
                    <input type="hidden" id="empresa_id" name="empresa_id" value="<?php echo $_SESSION['empresa_id']; ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="fornecedor">Fornecedor*</label>
                            <input type="text" id="fornecedor" name="fornecedor" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="descricao">Descrição*</label>
                            <input type="text" id="descricao" name="descricao" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="valor">Valor*</label>
                            <input type="number" id="valor" name="valor" step="0.01" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="data_vencimento">Data de Vencimento*</label>
                            <input type="date" id="data_vencimento" name="data_vencimento" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="data_pagamento">Data de Pagamento</label>
                            <input type="date" id="data_pagamento" name="data_pagamento">
                        </div>
                        
                        <div class="form-group">
                            <label for="status_id">Status*</label>
                            <select id="status_id" name="status_id" required>
                                <option value="">Selecione</option>
                                <?php
                                $sql = "SELECT id, nome FROM status_contas_pagar ORDER BY nome";
                                $stmt = $conn->prepare($sql);
                                $stmt->execute();
                                $status = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($status as $s) {
                                    echo "<option value='" . $s['id'] . "'>" . htmlspecialchars($s['nome']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="forma_pagamento_id">Forma de Pagamento</label>
                            <select id="forma_pagamento_id" name="forma_pagamento_id">
                                <option value="">Selecione</option>
                                <?php
                                $sql = "SELECT id, nome FROM formas_pagamento ORDER BY nome";
                                $stmt = $conn->prepare($sql);
                                $stmt->execute();
                                $formas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($formas as $forma) {
                                    echo "<option value='" . $forma['id'] . "'>" . htmlspecialchars($forma['nome']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="banco_id">Banco</label>
                            <select id="banco_id" name="banco_id">
                                <option value="">Selecione</option>
                                <?php
                                $sql = "SELECT id, nome FROM bancos ORDER BY nome";
                                $stmt = $conn->prepare($sql);
                                $stmt->execute();
                                $bancos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($bancos as $banco) {
                                    echo "<option value='" . $banco['id'] . "'>" . htmlspecialchars($banco['nome']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="observacoes">Observações</label>
                        <textarea class="form-control" id="observacoes" name="observacoes" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="recibo_arquivo">Recibo</label>
                        <input type="file" class="form-control" id="recibo_arquivo" name="recibo_arquivo" accept=".pdf,.jpg,.jpeg,.png">
                        <small class="form-text text-muted">Formatos aceitos: PDF, JPG, JPEG, PNG</small>
                        <div id="recibo_atual"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button id="cancelContaBtn" class="btn-secondary">Cancelar</button>
                <button id="saveContaBtn" class="btn-primary">Salvar</button>
            </div>
        </div>
    </div>
    
    <!-- Help Modal -->
    <div class="modal" id="helpModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ajuda - Contas a Pagar</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="help-section">
                    <h3>Resumo</h3>
                    <p>O módulo de Contas a Pagar permite gerenciar todas as despesas da empresa, incluindo:</p>
                    <ul>
                        <li>Controle de contas pendentes e pagas</li>
                        <li>Acompanhamento de vencimentos</li>
                        <li>Gestão de fornecedores</li>
                        <li>Análise financeira através de gráficos</li>
                    </ul>
                </div>
                
                <div class="help-section">
                    <h3>Dicas de Uso</h3>
                    <ul>
                        <li>Use os filtros para visualizar contas por período específico</li>
                        <li>Os gráficos são atualizados automaticamente com base nos filtros aplicados</li>
                        <li>Mantenha os status das contas sempre atualizados</li>
                        <li>Utilize o campo de observações para informações importantes</li>
                        <li>Faça upload dos recibos para manter um histórico documental</li>
                    </ul>
                </div>
                
                <div class="help-section">
                    <h3>Filtros</h3>
                    <ul>
                        <li><strong>Mês/Ano:</strong> Filtra contas por período específico</li>
                        <li><strong>Status:</strong> Visualiza contas por situação (Pendente, Paga, Vencida)</li>
                        <li><strong>Fornecedor:</strong> Busca contas de um fornecedor específico</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary close-modal">Fechar</button>
            </div>
        </div>
    </div>
    
    <!-- Filter Modal -->
    <div class="modal" id="filterModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Filtros</h2>
                <span class="close-modal" id="closeFilterBtn">&times;</span>
            </div>
            <div class="modal-body">
                <form id="filterForm">
                    <div class="form-group">
                        <label for="mesFilter">Período</label>
                        <input type="month" id="mesFilter" name="mes" class="form-control">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="clearFiltersBtn">Limpar Filtros</button>
                <button type="button" class="btn-primary" id="applyFiltersBtn">Aplicar Filtros</button>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Files -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initializePage();
        });
        
        function initializePage() {
            // Configurar botão de adicionar
            document.getElementById('addContaBtn').addEventListener('click', showAddContaModal);
            
            // Configurar botões da tabela
            setupTableButtons();
            
            // Configurar modais
            setupModals();
            
            // Configurar filtros
            setupFilters();
            
            // Configurar paginação
            setupPagination();
            
            // Inicializar gráficos
            initializeCharts();
            
            // Configurar botão de ajuda
            document.getElementById('helpBtn').addEventListener('click', function() {
                document.getElementById('helpModal').style.display = 'block';
            });
        }
        
        function setupTableButtons() {
            // Botões de visualizar
            document.querySelectorAll('.view-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const contaId = this.getAttribute('data-id');
                    showContaDetails(contaId);
                });
            });
            
            // Botões de visualizar recibo
            document.querySelectorAll('.view-receipt-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const contaId = this.getAttribute('data-id');
                    fetch(`../api/contas_pagar_actions.php?action=get&id=${contaId}`)
                        .then(response => response.json())
                        .then(result => {
                            if (result.success && result.data.recibo_arquivo) {
                                window.open(`../uploads/recibos/${result.data.recibo_arquivo}`, '_blank');
                            }
                        })
                        .catch(error => {
                            console.error('Erro ao carregar recibo:', error);
                            alert('Erro ao carregar recibo');
                        });
                });
            });
            
            // Botões de editar
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const contaId = this.getAttribute('data-id');
                    showEditContaModal(contaId);
                });
            });
            
            // Botões de excluir
            document.querySelectorAll('.delete-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const contaId = this.getAttribute('data-id');
                    deleteConta(contaId);
                });
            });
        }
        
        function setupModals() {
            // Fechar modal ao clicar no X
            document.querySelectorAll('.close-modal').forEach(button => {
                button.addEventListener('click', closeAllModals);
            });
            
            // Fechar modal ao clicar fora dele
            window.addEventListener('click', (event) => {
                if (event.target.classList.contains('modal')) {
                    closeAllModals();
                }
            });
            
            // Configurar botões do modal
            document.getElementById('saveContaBtn').addEventListener('click', saveConta);
            document.getElementById('cancelContaBtn').addEventListener('click', closeAllModals);
        }
        
        function setupFilters() {
            console.log('Iniciando setupFilters');
            
            const searchInput = document.getElementById('searchConta');
            const statusFilterSelect = document.getElementById('statusFilter');
            const categoriaFilterSelect = document.getElementById('categoriaFilter');
            const mesFilter = document.getElementById('mesFilter');
            const applyFiltersTopBtn = document.getElementById('applyAccountsFilters');
            const clearFiltersTopBtn = document.getElementById('clearAccountsFilters');

            if (searchInput) {
                searchInput.addEventListener('input', filterContas);
            }

            if (statusFilterSelect) {
                statusFilterSelect.addEventListener('change', filterContas);
            }

            if (categoriaFilterSelect) {
                categoriaFilterSelect.addEventListener('change', filterContas);
            }

            if (mesFilter) {
                mesFilter.addEventListener('change', filterContas);
            }

            if (applyFiltersTopBtn) {
                applyFiltersTopBtn.addEventListener('click', filterContas);
            }

            if (clearFiltersTopBtn) {
                clearFiltersTopBtn.addEventListener('click', () => {
                    if (searchInput) searchInput.value = '';
                    if (statusFilterSelect) statusFilterSelect.value = '';
                    if (categoriaFilterSelect) categoriaFilterSelect.value = '';
                    if (mesFilter) mesFilter.value = '';
                    filterContas();
                });
            }

            const filterBtn = document.getElementById('filterBtn');
            const filterModal = document.getElementById('filterModal');
            const closeFilterBtn = document.getElementById('closeFilterBtn');
            const applyFiltersBtn = document.getElementById('applyFiltersBtn');
            const clearFiltersBtn = document.getElementById('clearFiltersBtn');

            if (!filterBtn || !filterModal || !closeFilterBtn || !applyFiltersBtn || !clearFiltersBtn || !mesFilter) {
                console.error('Elementos do modal de filtro não encontrados:', {
                    filterBtn: !!filterBtn,
                    filterModal: !!filterModal,
                    closeFilterBtn: !!closeFilterBtn,
                    applyFiltersBtn: !!applyFiltersBtn,
                    clearFiltersBtn: !!clearFiltersBtn,
                    mesFilter: !!mesFilter
                });
                return;
            }

            // Carregar filtros existentes da URL
            const urlParams = new URLSearchParams(window.location.search);
            const mes = urlParams.get('mes');
            console.log('Filtro atual da URL:', mes);
            
            if (mes) {
                mesFilter.value = mes;
                console.log('Filtro carregado no input:', mesFilter.value);
            }

            // Mostrar modal ao clicar no botão de filtro
            filterBtn.addEventListener('click', () => {
                console.log('Abrindo modal de filtro');
                filterModal.style.display = 'block';
            });

            // Fechar modal ao clicar no X
            closeFilterBtn.addEventListener('click', () => {
                console.log('Fechando modal de filtro');
                filterModal.style.display = 'none';
            });

            // Fechar modal ao clicar fora dele
            window.addEventListener('click', (e) => {
                if (e.target === filterModal) {
                    console.log('Fechando modal ao clicar fora');
                    filterModal.style.display = 'none';
                }
            });

            // Aplicar filtros
            applyFiltersBtn.addEventListener('click', () => {
                const mes = mesFilter.value;
                console.log('Aplicando filtro - mês selecionado:', mes);
                
                let url = window.location.pathname;
                if (mes) {
                    url += `?mes=${mes}`;
                }
                console.log('Nova URL:', url);
                
                // Atualizar URL sem recarregar a página
                window.history.pushState({}, '', url);
                
                // Atualizar KPIs e gráficos com o novo filtro
                console.log('Atualizando KPIs e gráficos com mês:', mes);
                updateKPIs(mes);
                updateCharts(mes);
                
                // Fechar o modal
                filterModal.style.display = 'none';
            });

            // Limpar filtros
            clearFiltersBtn.addEventListener('click', () => {
                console.log('Limpando filtros');
                mesFilter.value = '';
                
                // Atualizar URL sem recarregar a página
                window.history.pushState({}, '', window.location.pathname);
                
                // Atualizar KPIs e gráficos sem filtro
                console.log('Atualizando KPIs e gráficos sem filtro');
                updateKPIs(null);
                updateCharts(null);
                
                // Fechar o modal
                filterModal.style.display = 'none';
            });
        }
        
        function setupPagination() {
            const prevPage = document.getElementById('prevPage');
            const nextPage = document.getElementById('nextPage');
            
            prevPage.addEventListener('click', function() {
                if (!this.disabled) {
                    const currentPage = <?php echo $pagina_atual; ?>;
                    if (currentPage > 1) {
                        window.location.href = `?page=${currentPage - 1}`;
                    }
                }
            });
            
            nextPage.addEventListener('click', function() {
                if (!this.disabled) {
                    const currentPage = <?php echo $pagina_atual; ?>;
                    const totalPages = <?php echo $total_paginas; ?>;
                    if (currentPage < totalPages) {
                        window.location.href = `?page=${currentPage + 1}`;
                    }
                }
            });
        }
        
        function filterContas() {
            const searchInput = document.getElementById('searchConta');
            const statusSelect = document.getElementById('statusFilter');
            const categoriaSelect = document.getElementById('categoriaFilter');
            const mesInput = document.getElementById('mesFilter');

            const searchText = searchInput ? searchInput.value.toLowerCase() : '';
            const statusFilter = statusSelect ? statusSelect.value : '';
            const categoriaFilter = categoriaSelect ? categoriaSelect.value : '';
            const mesFilter = mesInput ? mesInput.value : '';
            
            const tableRows = document.querySelectorAll('#contasTable tbody tr');
            
            tableRows.forEach(row => {
                const descricao = row.cells[1].textContent.toLowerCase();
                const categoria = row.cells[2].textContent;
                const status = row.cells[4].textContent.trim();
                const vencimento = row.cells[0].textContent;
                
                const matchesSearch = descricao.includes(searchText);
                const matchesStatus = statusFilter === '' || status === statusFilter;
                const matchesCategoria = categoriaFilter === '' || categoria === categoriaFilter;
                const matchesMes = mesFilter === '' || vencimento.includes(mesFilter);
                
                if (matchesSearch && matchesStatus && matchesCategoria && matchesMes) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        function initializeCharts() {
            // Obter parâmetros da URL
            const urlParams = new URLSearchParams(window.location.search);
            const mes = urlParams.get('mes');
            console.log('Inicializando gráficos - mês da URL:', mes);
            
            // Atualizar KPIs com base nos filtros
            updateKPIs(mes);
            
            // Atualizar gráficos com base nos filtros
            updateCharts(mes);
        }
        
        function updateKPIs(mes) {
            // Construir URL para buscar KPIs
            let url = '../api/contas_pagar_analytics.php?action=kpis';
            if (mes) {
                url += `&mes=${mes}`;
            }
            
            console.log('Atualizando KPIs - URL:', url);
            
            fetch(url)
                .then(response => {
                    console.log('Resposta recebida dos KPIs:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Dados recebidos dos KPIs:', data);
                    if (data.success) {
                        // Atualizar valores dos KPIs
                        document.getElementById('totalPagar').textContent = `R$ ${formatCurrency(data.data.total_pagar || 0)}`;
                        document.getElementById('contasVencidas').textContent = data.data.contas_vencidas || 0;
                        document.getElementById('contasVencer').textContent = data.data.contas_vencer || 0;
                        document.getElementById('contasPagas').textContent = data.data.contas_pagas || 0;
                    }
                })
                .catch(error => {
                    console.error('Erro ao atualizar KPIs:', error);
                });
        }
        
        function updateCharts(mes) {
            // Construir URL para buscar dados dos gráficos
            let url = '../api/contas_pagar_analytics.php?action=charts';
            if (mes) {
                url += `&mes=${mes}`;
            }
            
            console.log('Atualizando gráficos - URL:', url);
            
            fetch(url)
                .then(response => {
                    console.log('Resposta recebida dos gráficos:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Dados recebidos dos gráficos:', data);
                    if (data.success && data.data) {
                        // Atualizar gráfico de categorias
                        if (data.data.categorias) {
                            updateCategoriasChart(data.data.categorias);
                        } else {
                            console.warn('Dados de categorias não encontrados');
                        }
                        
                        // Atualizar gráfico de evolução
                        if (data.data.evolucao) {
                            updateEvolucaoChart(data.data.evolucao);
                        } else {
                            console.warn('Dados de evolução não encontrados');
                        }
                    } else {
                        console.error('Dados inválidos recebidos:', data);
                    }
                })
                .catch(error => {
                    console.error('Erro ao atualizar gráficos:', error);
                });
        }
        
        function formatCurrency(value) {
            return parseFloat(value || 0).toFixed(2).replace('.', ',');
        }
        
        function showAddContaModal() {
            document.getElementById('modalTitle').textContent = 'Adicionar Conta';
            document.getElementById('contaForm').reset();
            document.getElementById('id').value = '';
            document.getElementById('contaModal').style.display = 'block';
        }
        
        function closeAllModals() {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                modal.style.display = 'none';
            });
        }
        
        function saveConta() {
            const form = document.getElementById('contaForm');
            const formData = new FormData(form);
            
            // Validar campos obrigatórios
            const requiredFields = ['fornecedor', 'descricao', 'valor', 'data_vencimento', 'status_id'];
            for (const field of requiredFields) {
                if (!formData.get(field)) {
                    alert(`Por favor, preencha o campo ${field}`);
                    return;
                }
            }
            
            // Adicionar empresa_id
            formData.append('empresa_id', '<?php echo $_SESSION["empresa_id"]; ?>');
            
            // Determinar a ação (add ou update)
            const action = formData.get('id') ? 'update' : 'add';
            console.log('Salvando conta:', action);
            
            fetch(`../api/contas_pagar_actions.php?action=${action}`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Resposta do servidor:', data);
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Erro ao salvar conta');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao salvar conta');
            });
        }
        
        function showEditContaModal(id) {
            fetch(`../api/contas_pagar_actions.php?action=get&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const conta = data.data;
                        document.getElementById('id').value = conta.id;
                        document.getElementById('fornecedor').value = conta.fornecedor;
                        document.getElementById('descricao').value = conta.descricao;
                        document.getElementById('valor').value = conta.valor;
                        document.getElementById('data_vencimento').value = conta.data_vencimento;
                        document.getElementById('data_pagamento').value = conta.data_pagamento || '';
                        document.getElementById('status_id').value = conta.status_id;
                        document.getElementById('forma_pagamento_id').value = conta.forma_pagamento_id || '';
                        document.getElementById('banco_id').value = conta.banco_id || '';
                        document.getElementById('observacoes').value = conta.observacoes || '';
                        
                        // Limpar o campo de recibo
                        document.getElementById('recibo_arquivo').value = '';
                        
                        // Mostrar recibo atual se existir
                        const reciboContainer = document.getElementById('recibo_atual');
                        if (conta.recibo_arquivo) {
                            reciboContainer.innerHTML = `
                                <div class="alert alert-info">
                                    <i class="fas fa-file-alt"></i> Recibo atual: ${conta.recibo_arquivo}
                                    <button type="button" class="btn btn-sm btn-link" onclick="viewRecibo('${conta.recibo_arquivo}')">
                                        <i class="fas fa-eye"></i> Visualizar
                                    </button>
                                </div>
                            `;
                        } else {
                            reciboContainer.innerHTML = '';
                        }
                        
                        document.getElementById('contaModal').style.display = 'block';
                    } else {
                        alert(data.message || 'Erro ao carregar dados da conta');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao carregar dados da conta');
                });
        }
        
        function viewRecibo(filename) {
            window.open(`../uploads/recibos/${filename}`, '_blank');
        }
        
        function deleteConta(contaId) {
            if (confirm('Tem certeza que deseja excluir esta conta?')) {
                fetch(`../api/contas_pagar_actions.php?action=delete&id=${contaId}`, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        window.location.reload();
                    } else {
                        throw new Error(result.message || 'Erro ao excluir conta');
                    }
                })
                .catch(error => {
                    console.error('Erro ao excluir conta:', error);
                    alert('Erro ao excluir conta: ' + error.message);
                });
            }
        }
        
        function showContaDetails(contaId) {
            fetch(`../api/contas_pagar_actions.php?action=get&id=${contaId}`)
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        const conta = result.data;
                        const modal = document.createElement('div');
                        modal.className = 'modal';
                        modal.innerHTML = `
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h2>Detalhes da Conta</h2>
                                    <span class="close-modal">&times;</span>
                                </div>
                                <div class="modal-body">
                                    <div class="details-grid">
                                        <div class="detail-item">
                                            <label>Fornecedor:</label>
                                            <span>${conta.fornecedor}</span>
                                        </div>
                                        <div class="detail-item">
                                            <label>Descrição:</label>
                                            <span>${conta.descricao}</span>
                                        </div>
                                        <div class="detail-item">
                                            <label>Valor:</label>
                                            <span>R$ ${parseFloat(conta.valor).toFixed(2)}</span>
                                        </div>
                                        <div class="detail-item">
                                            <label>Data de Vencimento:</label>
                                            <span>${new Date(conta.data_vencimento).toLocaleDateString()}</span>
                                        </div>
                                        <div class="detail-item">
                                            <label>Data de Pagamento:</label>
                                            <span>${conta.data_pagamento ? new Date(conta.data_pagamento).toLocaleDateString() : '-'}</span>
                                        </div>
                                        <div class="detail-item">
                                            <label>Status:</label>
                                            <span class="status-badge status-${conta.status_nome.toLowerCase()}">${conta.status_nome}</span>
                                        </div>
                                        <div class="detail-item">
                                            <label>Forma de Pagamento:</label>
                                            <span>${conta.forma_pagamento_nome || '-'}</span>
                                        </div>
                                        <div class="detail-item">
                                            <label>Banco:</label>
                                            <span>${conta.banco_nome || '-'}</span>
                                        </div>
                                        <div class="detail-item full-width">
                                            <label>Observações:</label>
                                            <span>${conta.observacoes || '-'}</span>
                                        </div>
                                        ${conta.recibo_arquivo ? `
                                        <div class="detail-item full-width">
                                            <label>Recibo:</label>
                                            <a href="../uploads/recibos/${conta.recibo_arquivo}" target="_blank" class="btn-link">
                                                <i class="fas fa-file-invoice"></i> Visualizar Recibo
                                            </a>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button class="btn-secondary close-modal">Fechar</button>
                                </div>
                            </div>
                        `;
                        
                        document.body.appendChild(modal);
                        modal.style.display = 'block';
                        
                        const closeButtons = modal.querySelectorAll('.close-modal');
                        closeButtons.forEach(button => {
                            button.addEventListener('click', () => {
                                modal.remove();
                            });
                        });
                    } else {
                        throw new Error(result.message || 'Erro ao carregar detalhes da conta');
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar detalhes da conta:', error);
                    alert('Erro ao carregar detalhes da conta: ' + error.message);
                });
        }
        
        function updateCategoriasChart(data) {
            console.log('Atualizando gráfico de categorias com dados:', data);
            
            const ctx = document.getElementById('categoriasChart').getContext('2d');
            
            // Destruir gráfico existente se houver
            if (window.categoriasChart instanceof Chart) {
                window.categoriasChart.destroy();
            }
            
            // Preparar dados para o gráfico
            const labels = data.map(item => item.status_nome);
            const valores = data.map(item => parseFloat(item.total));
            
            console.log('Labels do gráfico:', labels);
            console.log('Valores do gráfico:', valores);
            
            window.categoriasChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: valores,
                        backgroundColor: [
                            '#4CAF50', // Pago
                            '#F44336', // Pendente
                            '#FFC107', // Atrasado
                            '#2196F3'  // Cancelado
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    return `${label}: R$ ${formatCurrency(value)}`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        function updateEvolucaoChart(data) {
            console.log('Atualizando gráfico de evolução com dados:', data);
            
            const ctx = document.getElementById('evolucaoChart').getContext('2d');
            
            // Destruir gráfico existente se houver
            if (window.evolucaoChart instanceof Chart) {
                window.evolucaoChart.destroy();
            }
            
            // Formatar labels dos meses
            const labels = data.map(item => {
                const date = new Date(item.ano, item.mes - 1);
                return date.toLocaleDateString('pt-BR', { month: 'short', year: 'numeric' });
            });
            
            const valores = data.map(item => parseFloat(item.total));
            
            console.log('Labels do gráfico:', labels);
            console.log('Valores do gráfico:', valores);
            
            window.evolucaoChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Valor Total',
                        data: valores,
                        backgroundColor: '#2196F3'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + formatCurrency(value);
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const value = context.raw || 0;
                                    return `Valor: R$ ${formatCurrency(value)}`;
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html> 