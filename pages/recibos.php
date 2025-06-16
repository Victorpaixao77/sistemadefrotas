<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Set page title
$page_title = "Recibos";
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
                        <button id="addReciboBtn" class="btn-add-widget">
                            <i class="fas fa-plus"></i> Novo Recibo
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
                            <h3>Total de Recibos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="totalRecibos">45</span>
                                <span class="metric-subtitle">Este mês</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Valor Total</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="valorTotal">R$ 25.450,00</span>
                                <span class="metric-subtitle">Em recibos</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Recibos Pendentes</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="recibosPendentes">3</span>
                                <span class="metric-subtitle">Para aprovação</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Média por Recibo</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="mediaRecibo">R$ 565,55</span>
                                <span class="metric-subtitle">Valor médio</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Search and Filter -->
                <div class="filter-section">
                    <div class="search-box">
                        <input type="text" id="searchRecibo" placeholder="Buscar recibo...">
                        <i class="fas fa-search"></i>
                    </div>
                    
                    <div class="filter-options">
                        <select id="tipoFilter">
                            <option value="">Todos os tipos</option>
                            <option value="Serviço">Serviço</option>
                            <option value="Produto">Produto</option>
                            <option value="Diária">Diária</option>
                            <option value="Outros">Outros</option>
                        </select>
                        
                        <select id="statusFilter">
                            <option value="">Todos os status</option>
                            <option value="Aprovado">Aprovado</option>
                            <option value="Pendente">Pendente</option>
                            <option value="Rejeitado">Rejeitado</option>
                        </select>
                        
                        <input type="month" id="mesFilter" placeholder="Filtrar por mês">
                        
                        <button id="applyFilters" class="btn-secondary">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                    </div>
                </div>
                
                <!-- Receipts Table -->
                <div class="data-table-container">
                    <table class="data-table" id="recibosTable">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Número</th>
                                <th>Descrição</th>
                                <th>Tipo</th>
                                <th>Valor</th>
                                <th>Emissor</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>10/05/2025</td>
                                <td>REC-2025-001</td>
                                <td>Serviço de Manutenção</td>
                                <td>Serviço</td>
                                <td>R$ 850,00</td>
                                <td>Auto Mecânica Silva</td>
                                <td><span class="status-badge status-approved">Aprovado</span></td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-icon view-btn" data-id="1" title="Ver detalhes">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn-icon edit-btn" data-id="1" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-icon print-btn" data-id="1" title="Imprimir">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <button class="btn-icon delete-btn" data-id="1" title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="pagination">
                    <button class="pagination-btn" id="prevPage" disabled>
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <span id="currentPage">Página 1 de 1</span>
                    <button class="pagination-btn" id="nextPage" disabled>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                
                <!-- Receipt Analytics -->
                <div class="analytics-section">
                    <div class="section-header">
                        <h2>Análise de Recibos</h2>
                    </div>
                    
                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Recibos por Tipo</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="tiposChart"></canvas>
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
    
    <!-- Add/Edit Receipt Modal -->
    <div class="modal" id="reciboModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Adicionar Recibo</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="reciboForm">
                    <input type="hidden" id="reciboId">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="data">Data*</label>
                            <input type="date" id="data" name="data" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="numero">Número do Recibo*</label>
                            <input type="text" id="numero" name="numero" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="descricao">Descrição*</label>
                            <input type="text" id="descricao" name="descricao" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="tipo">Tipo*</label>
                            <select id="tipo" name="tipo" required>
                                <option value="">Selecione o tipo</option>
                                <option value="Serviço">Serviço</option>
                                <option value="Produto">Produto</option>
                                <option value="Diária">Diária</option>
                                <option value="Outros">Outros</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="valor">Valor*</label>
                            <input type="number" id="valor" name="valor" step="0.01" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="emissor">Emissor*</label>
                            <input type="text" id="emissor" name="emissor" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status*</label>
                            <select id="status" name="status" required>
                                <option value="Pendente">Pendente</option>
                                <option value="Aprovado">Aprovado</option>
                                <option value="Rejeitado">Rejeitado</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="observacoes">Observações</label>
                        <textarea id="observacoes" name="observacoes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button id="cancelReciboBtn" class="btn-secondary">Cancelar</button>
                <button id="saveReciboBtn" class="btn-primary">Salvar</button>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Files -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initializePage();
            setupModals();
            setupFilters();
            initializeCharts();
        });
        
        function initializePage() {
            document.getElementById('addReciboBtn').addEventListener('click', showAddReciboModal);
            setupTableButtons();
        }
        
        function setupTableButtons() {
            const viewButtons = document.querySelectorAll('.view-btn');
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const reciboId = this.getAttribute('data-id');
                    showReciboDetails(reciboId);
                });
            });
            
            const editButtons = document.querySelectorAll('.edit-btn');
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const reciboId = this.getAttribute('data-id');
                    showEditReciboModal(reciboId);
                });
            });
            
            const printButtons = document.querySelectorAll('.print-btn');
            printButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const reciboId = this.getAttribute('data-id');
                    printRecibo(reciboId);
                });
            });
            
            const deleteButtons = document.querySelectorAll('.delete-btn');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const reciboId = this.getAttribute('data-id');
                    showDeleteConfirmation(reciboId);
                });
            });
        }
        
        function setupModals() {
            const closeButtons = document.querySelectorAll('.close-modal');
            closeButtons.forEach(button => {
                button.addEventListener('click', closeAllModals);
            });
            
            document.getElementById('cancelReciboBtn').addEventListener('click', closeAllModals);
            document.getElementById('saveReciboBtn').addEventListener('click', saveRecibo);
        }
        
        function setupFilters() {
            const searchBox = document.getElementById('searchRecibo');
            searchBox.addEventListener('input', filterRecibos);
            
            document.getElementById('applyFilters').addEventListener('click', filterRecibos);
        }
        
        function filterRecibos() {
            const searchText = document.getElementById('searchRecibo').value.toLowerCase();
            const tipoFilter = document.getElementById('tipoFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const mesFilter = document.getElementById('mesFilter').value;
            
            const tableRows = document.querySelectorAll('#recibosTable tbody tr');
            
            tableRows.forEach(row => {
                const descricao = row.cells[2].textContent.toLowerCase();
                const tipo = row.cells[3].textContent;
                const status = row.cells[6].textContent.trim();
                const data = row.cells[0].textContent;
                
                const matchesSearch = descricao.includes(searchText);
                const matchesTipo = tipoFilter === '' || tipo === tipoFilter;
                const matchesStatus = statusFilter === '' || status === statusFilter;
                const matchesMes = mesFilter === '' || data.includes(mesFilter);
                
                if (matchesSearch && matchesTipo && matchesStatus && matchesMes) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        function initializeCharts() {
            // Tipos Chart
            const tiposCtx = document.getElementById('tiposChart').getContext('2d');
            new Chart(tiposCtx, {
                type: 'pie',
                data: {
                    labels: ['Serviço', 'Produto', 'Diária', 'Outros'],
                    datasets: [{
                        data: [40, 30, 20, 10],
                        backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Evolução Chart
            const evolucaoCtx = document.getElementById('evolucaoChart').getContext('2d');
            new Chart(evolucaoCtx, {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai'],
                    datasets: [{
                        label: 'Quantidade de Recibos',
                        data: [35, 42, 38, 48, 45],
                        backgroundColor: '#3b82f6'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 10
                            }
                        }
                    }
                }
            });
        }
        
        function showAddReciboModal() {
            document.getElementById('reciboForm').reset();
            document.getElementById('reciboId').value = '';
            document.getElementById('modalTitle').textContent = 'Adicionar Recibo';
            document.getElementById('reciboModal').classList.add('active');
        }
        
        function closeAllModals() {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.classList.remove('active');
            });
        }
        
        function saveRecibo() {
            // Implementar a lógica de salvamento
            closeAllModals();
            // Recarregar dados
            window.location.reload();
        }
        
        function printRecibo(reciboId) {
            // Implementar a lógica de impressão
            window.print();
        }
    </script>
</body>
</html> 