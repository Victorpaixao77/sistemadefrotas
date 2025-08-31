<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Check if user is logged in, if not redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: /sistema-frotas/login.php");
    exit;
}

// Set page title
$page_title = "Motoristas";
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Motoristas - Sistema de Frotas</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
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
            cursor: pointer;
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
            padding: 0 10px;
        }
        
        /* Estilos para a seção de análise */
        .analytics-section {
            margin-top: 20px;
            padding: 0 20px;
        }
        
        .analytics-section .section-header {
            margin-bottom: 20px;
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
            height: 100%;
        }
        
        .analytics-card .card-header {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .analytics-card .card-header h3 {
            margin: 0;
            font-size: 1rem;
        }
        
        .analytics-card .card-body {
            padding: 15px;
            height: 400px;
            position: relative;
        }

        .chart-container {
            position: relative;
            height: 100%;
            width: 100%;
        }

        /* Garantir que os botões do modal de motoristas tenham o mesmo tamanho dos botões do modal de veículos */
        #motoristModal .modal-footer .btn-secondary,
        #motoristModal .modal-footer .btn-primary {
            min-width: 100px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 500;
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
                    <h1>Motoristas</h1>
                    <div class="dashboard-actions">
                        <button id="addMotoristBtn" class="btn-add-widget">
                            <i class="fas fa-plus"></i> Novo Motorista
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
                            <h3>Total de Motoristas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="totalMotorists">0</span>
                                <span class="metric-subtitle">Motoristas cadastrados</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Motoristas Ativos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="activeMotorists">0</span>
                                <span class="metric-subtitle">Em serviço</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Status</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="totalTrips">-</span>
                                <span class="metric-subtitle">Distribuição por disponibilidade</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Comissão</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="averageRating">-</span>
                                <span class="metric-subtitle">Total pago no mês</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Search and Filter -->
                <div class="filter-section">
                    <div class="search-box">
                        <input type="text" id="searchMotorist" placeholder="Buscar motorista...">
                        <i class="fas fa-search"></i>
                    </div>
                    
                    <div class="filter-options">
                        <select id="statusFilter">
                            <option value="">Todos os status</option>
                            <option value="Ativo">Ativo</option>
                            <option value="Férias">Férias</option>
                            <option value="Licença">Licença</option>
                            <option value="Inativo">Inativo</option>
                        </select>
                    </div>
                </div>
                
                <!-- Motorists List Table -->
                <div class="data-table-container">
                    <table class="data-table" id="motoristsTable">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>CPF</th>
                                <th>CNH</th>
                                <th>Categoria</th>
                                <th>Telefone</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Comissão</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dados serão carregados via JavaScript -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="pagination" id="motoristsPagination">
                    <a href="#" class="pagination-btn" id="prevPageBtn">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    
                    <span class="pagination-info">
                        Página <span id="currentPage">1</span> de <span id="totalPages">1</span>
                    </span>
                    
                    <a href="#" class="pagination-btn" id="nextPageBtn">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                
                <!-- Motorist Analytics -->
                <div class="analytics-section">
                    <div class="section-header">
                        <h2>Desempenho dos Motoristas</h2>
                    </div>
                    
                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Eficiência por Motorista</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="position: relative; height:400px;">
                                    <canvas id="motoristEfficiencyChart"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Avaliação de Desempenho</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="position: relative; height:400px;">
                                    <canvas id="motoristPerformanceChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    
    <!-- Add/Edit Motorist Modal -->
    <div class="modal" id="motoristModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Adicionar Motorista</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="motoristForm">
                    <input type="hidden" id="motoristId" name="id">
                    <input type="hidden" id="empresaId" name="empresa_id">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nome">Nome*</label>
                            <input type="text" id="nome" name="nome" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="foto_motorista">Foto do Motorista</label>
                            <input type="file" id="foto_motorista" name="foto_motorista" class="form-control" accept=".jpg,.jpeg,.png">
                            <small class="form-text text-muted">Formatos aceitos: JPG, JPEG, PNG</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="cpf">CPF*</label>
                            <input type="text" id="cpf" name="cpf" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="cnh">CNH</label>
                            <input type="text" id="cnh" name="cnh" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="cnh_arquivo">Arquivo da CNH</label>
                            <input type="file" id="cnh_arquivo" name="cnh_arquivo" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <small class="form-text text-muted">Formatos aceitos: PDF, DOC, DOCX, JPG, PNG</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="categoria_cnh_id">Categoria CNH</label>
                            <select id="categoria_cnh_id" name="categoria_cnh_id" class="form-control">
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="data_validade_cnh">Data de Validade da CNH</label>
                            <input type="date" id="data_validade_cnh" name="data_validade_cnh" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="telefone">Telefone</label>
                            <input type="tel" id="telefone" name="telefone" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="telefone_emergencia">Telefone de Emergência</label>
                            <input type="tel" id="telefone_emergencia" name="telefone_emergencia" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">E-mail</label>
                            <input type="email" id="email" name="email" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="data_contratacao">Data de Contratação</label>
                            <input type="date" id="data_contratacao" name="data_contratacao" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="tipo_contrato_id">Tipo de Contrato</label>
                            <select id="tipo_contrato_id" name="tipo_contrato_id" class="form-control">
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="contrato_arquivo">Arquivo do Contrato</label>
                            <input type="file" id="contrato_arquivo" name="contrato_arquivo" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <small class="form-text text-muted">Formatos aceitos: PDF, DOC, DOCX, JPG, PNG</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="disponibilidade_id">Disponibilidade</label>
                            <select id="disponibilidade_id" name="disponibilidade_id" class="form-control">
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="porcentagem_comissao">Porcentagem de Comissão</label>
                            <input type="number" id="porcentagem_comissao" name="porcentagem_comissao" class="form-control" step="0.01" min="0" max="100">
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="endereco">Endereço</label>
                        <input type="text" id="endereco" name="endereco" class="form-control">
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="observacoes">Observações</label>
                        <textarea id="observacoes" name="observacoes" class="form-control" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button id="cancelMotoristBtn" class="btn-secondary close-modal">Cancelar</button>
                <button id="saveMotoristBtn" class="btn-primary">Salvar</button>
            </div>
        </div>
    </div>
    
    <!-- Modal de Visualização -->
    <div class="modal" id="viewMotoristModal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h2>Detalhes do Motorista</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="details-grid">
                    <div class="details-group">
                        <label>Nome:</label>
                        <span id="viewMotoristName"></span>
                    </div>
                    <div class="details-group">
                        <label>CPF:</label>
                        <span id="viewMotoristCPF"></span>
                    </div>
                    <div class="details-group">
                        <label>CNH:</label>
                        <span id="viewMotoristCNH"></span>
                    </div>
                    <div class="details-group">
                        <label>Categoria CNH:</label>
                        <span id="viewMotoristCNHCategory"></span>
                    </div>
                    <div class="details-group">
                        <label>Validade CNH:</label>
                        <span id="viewMotoristCNHExpiry"></span>
                    </div>
                    <div class="details-group">
                        <label>Telefone:</label>
                        <span id="viewMotoristPhone"></span>
                    </div>
                    <div class="details-group">
                        <label>Telefone de Emergência:</label>
                        <span id="viewMotoristEmergencyPhone"></span>
                    </div>
                    <div class="details-group">
                        <label>E-mail:</label>
                        <span id="viewMotoristEmail"></span>
                    </div>
                    <div class="details-group">
                        <label>Endereço:</label>
                        <span id="viewMotoristAddress"></span>
                    </div>
                    <div class="details-group">
                        <label>Data de Contratação:</label>
                        <span id="viewMotoristHireDate"></span>
                    </div>
                    <div class="details-group">
                        <label>Tipo de Contrato:</label>
                        <span id="viewMotoristContract"></span>
                    </div>
                    <div class="details-group">
                        <label>Disponibilidade:</label>
                        <span id="viewMotoristAvailability"></span>
                    </div>
                    <div class="details-group">
                        <label>Comissão:</label>
                        <span id="viewMotoristCommission"></span>
                    </div>
                </div>

                <div class="details-group full-width">
                    <label>Observações:</label>
                    <span id="viewMotoristNotes"></span>
                </div>

                <!-- Documentos -->
                <div class="documents-section">
                    <h3>Documentos</h3>
                    <div class="documents-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                        <div class="document-card">
                            <div class="document-icon">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <div class="document-info">
                                <h4>CNH</h4>
                                <p id="cnhDocumentStatus">Status: <span class="status-badge">Válido</span></p>
                                <p id="cnhExpiryDate">Validade: <span></span></p>
                                <div class="document-preview" id="cnhPreview">
                                    <a href="#" id="cnhLink" target="_blank" class="btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> Visualizar CNH
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="document-card">
                            <div class="document-icon">
                                <i class="fas fa-file-contract"></i>
                            </div>
                            <div class="document-info">
                                <h4>Contrato</h4>
                                <p id="contractDocumentStatus">Status: <span class="status-badge">Ativo</span></p>
                                <p id="contractDate">Data: <span></span></p>
                                <div class="document-preview" id="contractPreview">
                                    <a href="#" id="contractLink" target="_blank" class="btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> Visualizar Contrato
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="document-card">
                            <div class="document-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="document-info">
                                <h4>Foto do Motorista</h4>
                                <div class="document-preview" id="photoPreview">
                                    <img id="motoristPhoto" src="" alt="Foto do Motorista" style="max-width: 150px; max-height: 150px; display: none;">
                                    <p id="noPhotoMessage" style="display: none;">Nenhuma foto disponível</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Histórico de Rotas -->
                <div class="route-history-section">
                    <h3>Histórico de Rotas</h3>
                    <div class="route-history-container">
                        <table class="data-table" id="routeHistoryTable">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Origem</th>
                                    <th>Destino</th>
                                    <th>Veículo</th>
                                    <th>Km Percorrido</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Preenchido via JavaScript -->
                            </tbody>
                        </table>
                        <div class="no-data-message" id="noRouteHistoryMessage" style="display: none;">
                            Nenhuma rota encontrada para este motorista.
                        </div>
                    </div>
                </div>

                <!-- Métricas de Desempenho -->
                <div class="performance-section">
                    <h3>Métricas de Desempenho</h3>
                    <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px;">
                        <div class="metric-card">
                            <div class="metric-icon">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="metric-info">
                                <h4>Avaliação Média</h4>
                                <p id="view-average-rating">0.0</p>
                            </div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-icon">
                                <i class="fas fa-route"></i>
                            </div>
                            <div class="metric-info">
                                <h4>Total de Viagens</h4>
                                <p id="view-total-trips">0</p>
                            </div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-icon">
                                <i class="fas fa-road"></i>
                            </div>
                            <div class="metric-info">
                                <h4>Distância Total</h4>
                                <p id="view-total-distance">0 km</p>
                            </div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-icon">
                                <i class="fas fa-gas-pump"></i>
                            </div>
                            <div class="metric-info">
                                <h4>Consumo Médio</h4>
                                <p id="view-average-consumption">0.0 L/100km</p>
                            </div>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('viewMotoristModal')">Fechar</button>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteMotoristModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirmar Exclusão</h2>
                <span class="close-modal close-delete-modal">&times;</span>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir o motorista <strong id="deleteMotoristName"></strong>?</p>
                <p class="warning-text">Esta ação não pode ser desfeita.</p>
            </div>
            <div class="modal-footer">
                <button id="cancelDeleteBtn" class="btn-secondary">Cancelar</button>
                <button id="confirmDeleteBtn" class="btn-danger">Excluir</button>
            </div>
        </div>
    </div>
    
    <!-- Modal de Ajuda -->
    <div class="modal" id="helpMotoristsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ajuda - Gestão de Motoristas</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="help-section">
                    <h3>Visão Geral</h3>
                    <p>A página de Motoristas permite gerenciar todos os motoristas da empresa. Aqui você pode cadastrar, editar, visualizar e excluir motoristas, além de acompanhar métricas importantes de performance e eficiência.</p>
                </div>

                <div class="help-section">
                    <h3>Funcionalidades Principais</h3>
                    <ul>
                        <li><strong>Novo Motorista:</strong> Cadastre um novo motorista com informações completas como dados pessoais, CNH e documentos.</li>
                        <li><strong>Filtros:</strong> Use os filtros para encontrar motoristas específicos por status, tipo de CNH ou através da busca por texto.</li>
                        <li><strong>Relatórios:</strong> Visualize relatórios e estatísticas de performance dos motoristas.</li>
                        <li><strong>Análise de Eficiência:</strong> Acompanhe a eficiência financeira de cada motorista.</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Indicadores (KPIs)</h3>
                    <ul>
                        <li><strong>Total de Motoristas:</strong> Número total de motoristas ativos.</li>
                        <li><strong>Motoristas Ativos:</strong> Quantidade de motoristas em operação.</li>
                        <li><strong>Eficiência Financeira:</strong> Análise de faturamento vs despesas por motorista.</li>
                        <li><strong>Avaliação de Desempenho:</strong> Métricas de performance em diferentes aspectos.</li>
                        <li><strong>Distribuição por CNH:</strong> Gráfico mostrando a distribuição por tipo de CNH.</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Ações Disponíveis</h3>
                    <ul>
                        <li><strong>Visualizar:</strong> Veja detalhes completos do motorista, incluindo histórico de viagens e documentos.</li>
                        <li><strong>Editar:</strong> Modifique informações de um motorista existente.</li>
                        <li><strong>Excluir:</strong> Remova um motorista do sistema (ação irreversível).</li>
                        <li><strong>Histórico:</strong> Acesse o histórico completo de viagens e atividades do motorista.</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Dicas Úteis</h3>
                    <ul>
                        <li>Mantenha os documentos dos motoristas sempre atualizados, especialmente a CNH.</li>
                        <li>Monitore a eficiência financeira para identificar oportunidades de melhoria.</li>
                        <li>Acompanhe o desempenho dos motoristas para treinamentos específicos.</li>
                        <li>Utilize os filtros para encontrar motoristas específicos rapidamente.</li>
                        <li>Analise os relatórios para otimizar a alocação de motoristas.</li>
                        <li>Configure alertas para vencimento de documentos importantes.</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('helpMotoristsModal')">Fechar</button>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Files -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/motorists.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize page first (includes setupHelpButton)
        initializePage();
        
        // Setup modal events after page initialization
        setupModals();
        
        // Setup filters
        setupFilters();
        
        // Initialize charts
        initializeCharts();
        
        // Setup pagination
        setupPagination();

        // Função para formatar valores em reais
        const formatCurrency = (value) => {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(value);
        };
        
        // Carregar dados do gráfico de eficiência por motorista
        fetch('../api/motorist_efficiency_analytics.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro ao carregar dados de eficiência');
                }
                return response.json();
            })
            .then(data => {
                const ctx = document.getElementById('motoristEfficiencyChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [
                            {
                                label: 'Faturamento',
                                data: data.datasets.faturamento,
                                backgroundColor: 'rgba(46, 204, 64, 0.7)',
                                borderColor: 'rgba(46, 204, 64, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Despesas',
                                data: data.datasets.despesas,
                                backgroundColor: 'rgba(231, 76, 60, 0.7)',
                                borderColor: 'rgba(231, 76, 60, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Lucro',
                                data: data.datasets.lucro,
                                backgroundColor: 'rgba(52, 152, 219, 0.7)',
                                borderColor: 'rgba(52, 152, 219, 1)',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed && context.parsed.y !== null && context.parsed.y !== undefined) {
                                            label += formatCurrency(context.parsed.y);
                                        } else {
                                            label += formatCurrency(0);
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return formatCurrency(value);
                                    }
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Erro ao carregar dados de eficiência:', error);
                document.getElementById('motoristEfficiencyChart').parentNode.innerHTML = 
                    '<div class="alert alert-danger">Erro ao carregar dados do gráfico de eficiência</div>';
            });

        // Carregar dados do gráfico de avaliação de desempenho
        fetch('../api/motorist_performance_analytics.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro ao carregar dados de desempenho');
                }
                return response.json();
            })
            .then(data => {
                const ctx = document.getElementById('motoristPerformanceChart').getContext('2d');
                new Chart(ctx, {
                    type: 'radar',
                    data: {
                        labels: data.labels,
                        datasets: data.datasets.map(dataset => ({
                            label: dataset.label,
                            data: dataset.data,
                            backgroundColor: dataset.backgroundColor,
                            borderColor: dataset.borderColor,
                            borderWidth: 1,
                            fill: true
                        }))
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed && context.parsed.value !== null && context.parsed.value !== undefined) {
                                            label += context.parsed.value.toFixed(1) + '%';
                                        } else {
                                            label += '0.0%';
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            r: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    stepSize: 20
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Erro ao carregar dados de desempenho:', error);
                document.getElementById('motoristPerformanceChart').parentNode.innerHTML = 
                    '<div class="alert alert-danger">Erro ao carregar dados do gráfico de desempenho</div>';
            });
    });
    </script>
</body>
</html>
