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
$page_title = "Manutenção de Pneus";

// Obter conexão com o banco de dados
$conn = getConnection();

// Processar filtros
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : null;
$ano = isset($_GET['ano']) ? intval($_GET['ano']) : null;

// Buscar dados para os KPIs
$sql = "SELECT 
            COUNT(*) as total_manutencoes,
            SUM(custo) as custo_total,
            COUNT(CASE WHEN data_manutencao >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as manutencoes_mes
        FROM pneu_manutencao 
        WHERE empresa_id = :empresa_id";

// Aplicar filtro de mês/ano nos KPIs se existir
if ($mes && $ano) {
    $sql .= " AND MONTH(data_manutencao) = :mes AND YEAR(data_manutencao) = :ano";
    $params = [':empresa_id' => $_SESSION['empresa_id'], ':mes' => $mes, ':ano' => $ano];
} else {
    $params = [':empresa_id' => $_SESSION['empresa_id']];
}

$kpis = fetchOne($conn, $sql, $params);

// Consulta para obter as manutenções (sem filtro de mês/ano)
$sql = "SELECT m.*, p.numero_serie as numero_pneu, v.placa as placa_veiculo, t.nome as tipo_nome
        FROM pneu_manutencao m 
        LEFT JOIN pneus p ON m.pneu_id = p.id 
        LEFT JOIN veiculos v ON m.veiculo_id = v.id 
        LEFT JOIN tipo_manutencao_pneus t ON m.tipo_manutencao_id = t.id
        WHERE m.empresa_id = :empresa_id
        ORDER BY m.data_manutencao DESC";

$stmt = $conn->prepare($sql);
$stmt->bindValue(':empresa_id', $_SESSION['empresa_id']);
$stmt->execute();
$manutencoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Consulta para obter os tipos de manutenção
$sql_tipos = "SELECT id, nome FROM tipo_manutencao_pneus ORDER BY nome";
$stmt_tipos = $conn->query($sql_tipos);
$tipos_manutencao = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);

// Consulta para obter os pneus
$sql_pneus = "SELECT id, numero_serie FROM pneus WHERE empresa_id = :empresa_id ORDER BY numero_serie";
$stmt_pneus = $conn->prepare($sql_pneus);
$stmt_pneus->bindValue(':empresa_id', $_SESSION['empresa_id']);
$stmt_pneus->execute();
$pneus = $stmt_pneus->fetchAll(PDO::FETCH_ASSOC);

// Consulta para obter os veículos
$sql_veiculos = "SELECT id, placa FROM veiculos WHERE empresa_id = :empresa_id AND status_id = 1 ORDER BY placa";
$stmt_veiculos = $conn->prepare($sql_veiculos);
$stmt_veiculos->bindValue(':empresa_id', $_SESSION['empresa_id']);
$stmt_veiculos->execute();
$veiculos = $stmt_veiculos->fetchAll(PDO::FETCH_ASSOC);

// Pegar a página atual da URL ou definir como 1
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 5; // Registros por página
$offset = ($page - 1) * $limit;

// Primeiro, conta o total de registros
$sql_count = "SELECT COUNT(*) as total FROM pneu_manutencao WHERE empresa_id = :empresa_id";
$total = fetchOne($conn, $sql_count, [':empresa_id' => $_SESSION['empresa_id']])['total'];
$total_pages = ceil($total / $limit);
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
    <link rel="stylesheet" href="../css/maintenance.css">
    
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
                    <h1><?php echo $page_title; ?></h1>
                    <div class="dashboard-actions">
                        <button id="addMaintenanceBtn" class="btn-add-widget">
                            <i class="fas fa-plus"></i> Nova Manutenção
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
                            <h3>Manutenções</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $kpis['manutencoes_mes']; ?></span>
                                <span class="metric-subtitle">Este mês</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Custo Total</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($kpis['custo_total'] ?? 0, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Este mês</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Total Geral</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $kpis['total_manutencoes']; ?></span>
                                <span class="metric-subtitle">Manutenções</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Search and Filter -->
                <div class="filter-section">
                    <div class="search-box">
                        <input type="text" id="searchMaintenance" placeholder="Buscar manutenção...">
                        <i class="fas fa-search"></i>
                    </div>
                    
                    <div class="filter-options">
                        <select id="tipoFilter">
                            <option value="">Todos os tipos</option>
                            <?php
                            $sql = "SELECT id, nome FROM tipo_manutencao_pneus ORDER BY nome";
                            $tipos = executeQuery($conn, $sql);
                            foreach ($tipos as $tipo) {
                                echo "<option value='" . $tipo['id'] . "'>" . htmlspecialchars($tipo['nome']) . "</option>";
                            }
                            ?>
                        </select>
                        
                        <button id="applyFilters" class="btn-secondary">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                    </div>
                </div>
                
                <!-- Maintenance Table -->
                <div class="data-table-container">
                    <table class="data-table" id="maintenanceTable">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Pneu</th>
                                <th>Veículo</th>
                                <th>Tipo</th>
                                <th>KM</th>
                                <th>Custo</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($manutencoes as $manutencao): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($manutencao['data_manutencao'])); ?></td>
                                <td><?php echo htmlspecialchars($manutencao['numero_pneu']); ?></td>
                                <td><?php echo htmlspecialchars($manutencao['placa_veiculo'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($manutencao['tipo_nome']); ?></td>
                                <td><?php echo number_format($manutencao['km_veiculo'] ?? 0, 0, ',', '.'); ?></td>
                                <td>R$ <?php echo number_format($manutencao['custo'] ?? 0, 2, ',', '.'); ?></td>
                                <td class="actions">
                                    <button class="btn-icon view-btn" data-id="<?php echo $manutencao['id']; ?>" title="Ver detalhes">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon edit-btn" data-id="<?php echo $manutencao['id']; ?>" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon delete-btn" data-id="<?php echo $manutencao['id']; ?>" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Pagination -->
                    <div class="pagination">
                        <?php if ($total_pages > 1): ?>
                            <a href="#" class="pagination-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" 
                               onclick="return changePage(<?php echo $page - 1; ?>)">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            
                            <span class="pagination-info">
                                Página <?php echo $page; ?> de <?php echo $total_pages; ?>
                            </span>
                            
                            <a href="#" class="pagination-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>"
                               onclick="return changePage(<?php echo $page + 1; ?>)">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Analytics Section -->
                <div class="analytics-section">
                    <div class="section-header">
                        <h2>Análise de Manutenção de Pneus</h2>
                    </div>
                    
                    <div class="analytics-grid">
                        <!-- Custo Total por Mês -->
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Custo Total por Mês</h3>
                                <span class="card-subtitle">Últimos 12 meses</span>
                            </div>
                            <div class="card-body">
                                <canvas id="custoMensalChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- Quantidade por Tipo -->
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Quantidade por Tipo</h3>
                                <span class="card-subtitle">Distribuição por tipo de manutenção</span>
                            </div>
                            <div class="card-body">
                                <canvas id="quantidadeTipoChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- Top Pneus -->
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Top Pneus com Maior Custo</h3>
                                <span class="card-subtitle">Top 5 pneus</span>
                            </div>
                            <div class="card-body">
                                <canvas id="topPneusChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- Média por Tipo -->
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Média de Custo por Tipo</h3>
                                <span class="card-subtitle">Valor médio por tipo de manutenção</span>
                            </div>
                            <div class="card-body">
                                <canvas id="mediaTipoChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <!-- Add/Edit Maintenance Modal -->
    <div class="modal" id="maintenanceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Adicionar Manutenção</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="maintenanceForm">
                    <input type="hidden" id="id" name="id">
                    <input type="hidden" id="empresa_id" name="empresa_id" value="<?php echo $_SESSION['empresa_id']; ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="data_manutencao">Data da Manutenção*</label>
                            <input type="date" id="data_manutencao" name="data_manutencao" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="pneu_id">Pneu*</label>
                            <select id="pneu_id" name="pneu_id" required>
                                <option value="">Selecione um pneu</option>
                                <?php
                                foreach ($pneus as $pneu) {
                                    echo "<option value='" . $pneu['id'] . "'>" . htmlspecialchars($pneu['numero_serie']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="veiculo_id">Veículo</label>
                            <select id="veiculo_id" name="veiculo_id">
                                <option value="">Selecione um veículo</option>
                                <?php
                                foreach ($veiculos as $veiculo) {
                                    echo "<option value='" . $veiculo['id'] . "'>" . htmlspecialchars($veiculo['placa']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="tipo_manutencao_id">Tipo de Manutenção*</label>
                            <select id="tipo_manutencao_id" name="tipo_manutencao_id" required>
                                <option value="">Selecione o tipo</option>
                                <?php
                                foreach ($tipos_manutencao as $tipo) {
                                    echo "<option value='" . $tipo['id'] . "'>" . htmlspecialchars($tipo['nome']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="km_veiculo">KM do Veículo*</label>
                            <input type="number" id="km_veiculo" name="km_veiculo" step="0.01" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="custo">Custo*</label>
                            <input type="number" id="custo" name="custo" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="observacoes">Observações</label>
                        <textarea id="observacoes" name="observacoes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button id="cancelMaintenanceBtn" class="btn-secondary">Cancelar</button>
                <button id="saveMaintenanceBtn" class="btn-primary">Salvar</button>
            </div>
        </div>
    </div>
    
    <!-- View Maintenance Modal -->
    <div class="modal" id="viewMaintenanceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Detalhes da Manutenção</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="details-grid">
                    <div class="detail-item">
                        <label>Data da Manutenção:</label>
                        <span id="view_data_manutencao"></span>
                    </div>
                    <div class="detail-item">
                        <label>Pneu:</label>
                        <span id="view_pneu_numero"></span>
                    </div>
                    <div class="detail-item">
                        <label>Veículo:</label>
                        <span id="view_veiculo_placa"></span>
                    </div>
                    <div class="detail-item">
                        <label>Tipo de Manutenção:</label>
                        <span id="view_tipo_nome"></span>
                    </div>
                    <div class="detail-item">
                        <label>KM do Veículo:</label>
                        <span id="view_km_veiculo"></span>
                    </div>
                    <div class="detail-item">
                        <label>Custo:</label>
                        <span id="view_custo"></span>
                    </div>
                    <div class="detail-item full-width">
                        <label>Observações:</label>
                        <span id="view_observacoes"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary close-modal">Fechar</button>
            </div>
        </div>
    </div>
    
    <!-- Help Modal -->
    <div class="modal" id="helpModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ajuda - Manutenção de Pneus</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="help-section">
                    <h3>Visão Geral</h3>
                    <p>O módulo de Manutenção de Pneus permite gerenciar todas as manutenções realizadas nos pneus da sua frota, oferecendo análises detalhadas de custos e tipos de manutenção.</p>
                </div>

                <div class="help-section">
                    <h3>Funcionalidades Principais</h3>
                    <ul>
                        <li><strong>Nova Manutenção:</strong> Registre novas manutenções com informações como:
                            <ul>
                                <li>Data da manutenção</li>
                                <li>Pneu e veículo</li>
                                <li>Tipo de manutenção</li>
                                <li>Quilometragem</li>
                                <li>Custo</li>
                                <li>Observações</li>
                            </ul>
                        </li>
                        <li><strong>Filtros:</strong> Filtre os dados por:
                            <ul>
                                <li>Tipo de manutenção</li>
                                <li>Pneu</li>
                                <li>Veículo</li>
                                <li>Período</li>
                            </ul>
                        </li>
                        <li><strong>Análises:</strong> Visualize:
                            <ul>
                                <li>Custo total por mês</li>
                                <li>Quantidade por tipo de manutenção</li>
                                <li>Top pneus com maior custo</li>
                                <li>Média de custo por tipo</li>
                                <li>Gráficos de distribuição e evolução</li>
                            </ul>
                        </li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Como Usar</h3>
                    <ol>
                        <li><strong>Registrar Manutenção:</strong>
                            <ul>
                                <li>Clique no botão "Nova Manutenção"</li>
                                <li>Preencha todos os campos obrigatórios (*)</li>
                                <li>Clique em "Salvar"</li>
                            </ul>
                        </li>
                        <li><strong>Filtrar Dados:</strong>
                            <ul>
                                <li>Use a barra de busca para encontrar manutenções específicas</li>
                                <li>Selecione o tipo de manutenção no filtro</li>
                                <li>Clique em "Filtrar" para aplicar os filtros</li>
                            </ul>
                        </li>
                        <li><strong>Gerenciar Registros:</strong>
                            <ul>
                                <li>Use o botão de visualizar (olho) para ver detalhes</li>
                                <li>Use o botão de editar (lápis) para modificar um registro</li>
                                <li>Use o botão de excluir (lixeira) para remover um registro</li>
                            </ul>
                        </li>
                    </ol>
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
    
    <!-- JavaScript Files -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initializePage();
            setupModals();
            setupFilters();
            loadAnalytics();
        });
        
        function initializePage() {
            document.getElementById('addMaintenanceBtn').addEventListener('click', showAddMaintenanceModal);
            document.getElementById('helpBtn').addEventListener('click', showHelpModal);
            document.getElementById('filterBtn').addEventListener('click', showFilterModal);
            setupTableButtons();
        }
        
        function setupTableButtons() {
            const viewButtons = document.querySelectorAll('.btn-icon.view-btn');
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const maintenanceId = this.getAttribute('data-id');
                    showMaintenanceDetails(maintenanceId);
                });
            });
            
            const editButtons = document.querySelectorAll('.btn-icon.edit-btn');
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const maintenanceId = this.getAttribute('data-id');
                    showEditMaintenanceModal(maintenanceId);
                });
            });
            
            const deleteButtons = document.querySelectorAll('.btn-icon.delete-btn');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const maintenanceId = this.getAttribute('data-id');
                    showDeleteConfirmation(maintenanceId);
                });
            });
        }
        
        function setupModals() {
            const closeButtons = document.querySelectorAll('.close-modal');
            closeButtons.forEach(button => {
                button.addEventListener('click', closeAllModals);
            });
            
            document.getElementById('cancelMaintenanceBtn').addEventListener('click', closeAllModals);
            document.getElementById('saveMaintenanceBtn').addEventListener('click', saveMaintenance);
        }
        
        function setupFilters() {
            // Add clear filter button click handler
            document.getElementById('clearFilterBtn').addEventListener('click', function() {
                document.getElementById('filterMonth').value = '';
                window.location.href = window.location.pathname;
            });
            
            // Add apply filter button click handler
            document.getElementById('applyFilterBtn').addEventListener('click', function() {
                const monthYear = document.getElementById('filterMonth').value;
                if (monthYear) {
                    const [year, month] = monthYear.split('-');
                    window.location.href = `?mes=${month}&ano=${year}`;
                } else {
                    window.location.href = window.location.pathname;
                }
            });
            
            // Set the current month/year in the filter if it exists
            const urlParams = new URLSearchParams(window.location.search);
            const mes = urlParams.get('mes');
            const ano = urlParams.get('ano');
            if (mes && ano) {
                document.getElementById('filterMonth').value = `${ano}-${mes.padStart(2, '0')}`;
            }
        }
        
        function showAddMaintenanceModal() {
            document.getElementById('maintenanceForm').reset();
            document.getElementById('id').value = '';
            document.getElementById('empresa_id').value = '<?php echo $_SESSION['empresa_id']; ?>';
            document.getElementById('modalTitle').textContent = 'Adicionar Manutenção';
            document.getElementById('maintenanceModal').style.display = 'block';
        }
        
        function showEditMaintenanceModal(maintenanceId) {
            fetch(`../api/pneu_manutencao_data.php?action=view&id=${maintenanceId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        fillMaintenanceForm(data.data);
                        document.getElementById('modalTitle').textContent = 'Editar Manutenção';
                        document.getElementById('maintenanceModal').style.display = 'block';
                    } else {
                        throw new Error(data.error || 'Erro ao carregar dados da manutenção');
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar dados da manutenção:', error);
                    alert('Erro ao carregar dados da manutenção: ' + error.message);
                });
        }
        
        function fillMaintenanceForm(data) {
            const form = document.getElementById('maintenanceForm');
            form.reset();
            
            // Preenche os campos com os dados recebidos
            document.getElementById('id').value = data.id;
            document.getElementById('empresa_id').value = data.empresa_id;
            document.getElementById('data_manutencao').value = data.data_manutencao;
            document.getElementById('pneu_id').value = data.pneu_id;
            document.getElementById('veiculo_id').value = data.veiculo_id || '';
            document.getElementById('tipo_manutencao_id').value = data.tipo_manutencao_id;
            document.getElementById('km_veiculo').value = data.km_veiculo;
            document.getElementById('custo').value = data.custo;
            document.getElementById('observacoes').value = data.observacoes || '';
        }
        
        function closeAllModals() {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.style.display = 'none';
            });
        }
        
        function saveMaintenance() {
            const form = document.getElementById('maintenanceForm');
            const formData = new FormData(form);
            const data = {};
            
            formData.forEach((value, key) => {
                data[key] = value;
            });
            
            const maintenanceId = document.getElementById('id').value;
            const method = maintenanceId ? 'update' : 'add';
            
            fetch(`../api/pneu_manutencao_actions.php?action=${method}${maintenanceId ? '&id=' + maintenanceId : ''}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        closeAllModals();
                        window.location.reload();
                    } else {
                        throw new Error(result.error || 'Erro ao salvar manutenção');
                    }
                })
                .catch(error => {
                    console.error('Erro ao salvar manutenção:', error);
                    alert('Erro ao salvar manutenção: ' + error.message);
                });
        }
        
        function showDeleteConfirmation(maintenanceId) {
            if (confirm('Tem certeza que deseja excluir esta manutenção?')) {
                fetch(`../api/pneu_manutencao_actions.php?action=delete&id=${maintenanceId}`, {
                    method: 'POST'
                })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            window.location.reload();
                        } else {
                            throw new Error(result.error || 'Erro ao excluir manutenção');
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao excluir manutenção:', error);
                        alert('Erro ao excluir manutenção: ' + error.message);
                    });
            }
        }
        
        function showMaintenanceDetails(maintenanceId) {
            fetch(`../api/pneu_manutencao_data.php?action=view&id=${maintenanceId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Preencher os campos do modal de visualização
                        document.getElementById('view_data_manutencao').textContent = formatDate(data.data.data_manutencao);
                        document.getElementById('view_pneu_numero').textContent = data.data.numero_pneu;
                        document.getElementById('view_veiculo_placa').textContent = data.data.placa_veiculo || '-';
                        document.getElementById('view_tipo_nome').textContent = data.data.tipo_nome;
                        document.getElementById('view_km_veiculo').textContent = formatNumber(data.data.km_veiculo);
                        document.getElementById('view_custo').textContent = formatCurrency(data.data.custo);
                        document.getElementById('view_observacoes').textContent = data.data.observacoes || '-';
                        
                        // Mostrar o modal
                        document.getElementById('viewMaintenanceModal').style.display = 'block';
                    } else {
                        throw new Error(data.error || 'Erro ao carregar dados da manutenção');
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar dados da manutenção:', error);
                    alert('Erro ao carregar dados da manutenção: ' + error.message);
                });
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('pt-BR');
        }
        
        function formatNumber(value) {
            return new Intl.NumberFormat('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(value);
        }
        
        function formatCurrency(value) {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(value);
        }
        
        function showHelpModal() {
            document.getElementById('helpModal').style.display = 'block';
        }
        
        function showFilterModal() {
            document.getElementById('filterModal').style.display = 'block';
        }
        
        function loadAnalytics() {
            console.log('Iniciando carregamento dos gráficos...');
            
            // Obtém o mês e ano dos parâmetros da URL
            const urlParams = new URLSearchParams(window.location.search);
            const mes = urlParams.get('mes');
            const ano = urlParams.get('ano');
            
            console.log('Parâmetros de filtro:', { mes, ano });
            
            // Constrói a URL com os parâmetros de filtro
            let url = '../api/pneu_manutencao_analytics.php';
            if (mes && ano) {
                url += `?mes=${mes}&ano=${ano}`;
            }
            
            console.log('URL da API:', url);
            
            // Carrega dados para os gráficos
            fetch(url)
                .then(response => {
                    console.log('Resposta da API:', response.status, response.statusText);
                    return response.json();
                })
                .then(data => {
                    console.log('Dados recebidos da API:', data);
                    if (data.success) {
                        console.log('Renderizando gráficos com os dados:', {
                            custo_mensal: data.custo_mensal,
                            quantidade_tipo: data.quantidade_tipo,
                            top_pneus: data.top_pneus,
                            media_tipo: data.media_tipo
                        });
                        renderCustoMensalChart(data.custo_mensal);
                        renderQuantidadeTipoChart(data.quantidade_tipo);
                        renderTopPneusChart(data.top_pneus);
                        renderMediaTipoChart(data.media_tipo);
                    } else {
                        console.error('API retornou erro:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar analytics:', error);
                    console.error('Stack trace:', error.stack);
                });
        }
        
        function renderCustoMensalChart(data) {
            console.log('Renderizando gráfico de custo mensal:', data);
            const ctx = document.getElementById('custoMensalChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(item => {
                        const [year, month] = item.mes.split('-');
                        return `${month}/${year}`;
                    }),
                    datasets: [{
                        label: 'Custo Total',
                        data: data.map(item => item.total_custo),
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1,
                        fill: true,
                        backgroundColor: 'rgba(75, 192, 192, 0.1)'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `R$ ${formatNumber(context.raw)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return `R$ ${formatNumber(value)}`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        function renderQuantidadeTipoChart(data) {
            console.log('Renderizando gráfico de quantidade por tipo:', data);
            const ctx = document.getElementById('quantidadeTipoChart').getContext('2d');
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.map(item => item.tipo),
                    datasets: [{
                        data: data.map(item => item.quantidade),
                        backgroundColor: [
                            'rgb(255, 99, 132)',
                            'rgb(54, 162, 235)',
                            'rgb(255, 205, 86)',
                            'rgb(75, 192, 192)',
                            'rgb(153, 102, 255)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.raw / total) * 100).toFixed(1);
                                    return `${context.label}: ${context.raw} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        function renderTopPneusChart(data) {
            console.log('Renderizando gráfico de top pneus:', data);
            const ctx = document.getElementById('topPneusChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(item => item.pneu),
                    datasets: [{
                        label: 'Custo Total',
                        data: data.map(item => item.total_custo),
                        backgroundColor: 'rgba(75, 192, 192, 0.8)'
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `R$ ${formatNumber(context.raw)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return `R$ ${formatNumber(value)}`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        function renderMediaTipoChart(data) {
            console.log('Renderizando gráfico de média por tipo:', data);
            const ctx = document.getElementById('mediaTipoChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(item => item.tipo),
                    datasets: [{
                        label: 'Média de Custo',
                        data: data.map(item => item.media_custo),
                        backgroundColor: 'rgba(153, 102, 255, 0.8)'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `R$ ${formatNumber(context.raw)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return `R$ ${formatNumber(value)}`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Função para mudar de página
        function changePage(newPage) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('page', newPage);
            window.location.href = window.location.pathname + '?' + urlParams.toString();
            return false;
        }
    </script>
</body>
</html> 