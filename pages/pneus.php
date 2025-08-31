<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Set page title
$page_title = "Pneus";
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
                
                <!-- Search and Filter -->
                <div class="filter-section">
                    <div class="search-box">
                        <input type="text" id="searchTire" placeholder="Buscar pneu...">
                        <i class="fas fa-search"></i>
                    </div>
                    
                    <div class="filter-options">
                        <select id="statusFilter">
                            <option value="">Todos os status</option>
                        </select>
                        
                        <select id="vehicleFilter">
                            <option value="">Todos os veículos</option>
                        </select>
                        
                        <button id="applyFilters" class="btn-secondary">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                    </div>
                </div>
                
                <!-- Tires Table -->
                <div class="data-table-container">
                    <table class="data-table" id="tiresTable">
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
                <div class="pagination">
                    <a href="#" class="pagination-btn disabled" id="prevPage">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <span class="pagination-info" id="currentPage">Página 1 de 1</span>
                    <a href="#" class="pagination-btn disabled" id="nextPage">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
            
            <!-- Footer -->
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <!-- Add/Edit Tire Modal -->
    <div class="modal" id="tireModal">
        <div class="modal-content">
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
                        <button type="button" class="btn-secondary" onclick="closeModal()">Cancelar</button>
                    </div>
                </form>
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
            loadTires(1); // Carrega a primeira página
            loadFilters();
            loadDashboardMetrics(); // Carrega as métricas do dashboard
        });
        
        function initializePage() {
            document.getElementById('addTireBtn').addEventListener('click', showAddTireModal);
            document.getElementById('applyFilters').addEventListener('click', applyFilters);
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
                const response = await fetch(`../includes/get_tires.php?page=${page}`);
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
            const prevBtn = document.getElementById('prevPage');
            const nextBtn = document.getElementById('nextPage');
            const currentPageSpan = document.getElementById('currentPage');
            currentPageSpan.textContent = `Página ${currentPage} de ${totalPages}`;
            if (currentPage <= 1) {
                prevBtn.classList.add('disabled');
            } else {
                prevBtn.classList.remove('disabled');
            }
            if (currentPage >= totalPages) {
                nextBtn.classList.add('disabled');
            } else {
                nextBtn.classList.remove('disabled');
            }
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
            document.getElementById('tireModal').classList.add('active');
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
                    document.getElementById('tireModal').classList.add('active');
                }
            } catch (error) {
                console.error('Erro ao carregar dados do pneu:', error);
                alert('Erro ao carregar dados do pneu. Por favor, tente novamente.');
            }
        }

        async function showTireDetails(tireId) {
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
                            input.disabled = true; // Desabilita os campos para visualização
                        }
                    });
                    
                    document.getElementById('modalTitle').textContent = 'Visualizar Pneu';
                    document.getElementById('tireModal').classList.add('active');
                }
            } catch (error) {
                console.error('Erro ao carregar dados do pneu:', error);
                alert('Erro ao carregar dados do pneu. Por favor, tente novamente.');
            }
        }

        function showDeleteConfirmation(tireId) {
            if (confirm('Tem certeza que deseja excluir este pneu?')) {
                deleteTire(tireId);
            }
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
            });
            // Reabilitar campos que podem ter sido desabilitados na visualização
            const form = document.getElementById('tireForm');
            form.querySelectorAll('input, select, textarea').forEach(field => {
                field.disabled = false;
            });
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
            // Total de Pneus
            document.querySelector('.dashboard-card:nth-child(1) .metric-value').textContent = metrics.total_pneus;
            document.querySelector('.dashboard-card:nth-child(1) .metric-subtitle').textContent = 'Total';
            
            // Pneus em Uso
            document.querySelector('.dashboard-card:nth-child(2) .metric-value').textContent = metrics.total_em_uso;
            document.querySelector('.dashboard-card:nth-child(2) .metric-subtitle').textContent = 'Em uso';
            
            // Vida Média
            document.querySelector('.dashboard-card:nth-child(3) .metric-value').textContent = metrics.vida_media + '%';
            document.querySelector('.dashboard-card:nth-child(3) .metric-subtitle').textContent = 'Vida útil média';
            
            // Pneus em Alerta
            document.querySelector('.dashboard-card:nth-child(4) .metric-value').textContent = metrics.pneus_alerta;
            document.querySelector('.dashboard-card:nth-child(4) .metric-subtitle').textContent = 'Em alerta';
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

            // Close modal functionality for help modal
            document.querySelectorAll('.close-modal').forEach(button => {
                button.addEventListener('click', function() {
                    const modal = this.closest('.modal');
                    if (modal) {
                        modal.style.display = 'none';
                    }
                });
            });

            // Close modal when clicking outside
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', function(event) {
                    if (event.target === this) {
                        this.style.display = 'none';
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
    <div class="modal" id="helpPneusModal">
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