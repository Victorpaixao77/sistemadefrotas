<?php
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
$page_title = "Veículos";

// Inicializar variáveis de estatísticas
$total_veiculos = 0;
$veiculos_ativos = 0;
$veiculos_manutencao = 0;
$quilometragem_total = 0;

// Função para formatar quilometragem
function formatKm($km) {
    if ($km === null) return '0 km';
    return number_format($km, 0, ',', '.') . ' km';
}

// Função para buscar veículos e estatísticas do banco de dados
function getVehicles($page = 1, $limit = 5) {
    global $total_veiculos, $veiculos_ativos, $veiculos_manutencao, $quilometragem_total;
    
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        
        // Primeiro busca as estatísticas totais
        $sqlStats = "SELECT 
            COUNT(*) as total_veiculos,
            SUM(CASE WHEN status_id = 1 THEN 1 ELSE 0 END) as veiculos_ativos,
            SUM(CASE WHEN status_id = 2 THEN 1 ELSE 0 END) as veiculos_manutencao,
            SUM(COALESCE(km_atual, 0)) as quilometragem_total
            FROM veiculos 
            WHERE empresa_id = :empresa_id";
            
        $stmtStats = $conn->prepare($sqlStats);
        $stmtStats->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmtStats->execute();
        $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
        
        // Atualiza as variáveis globais
        $total_veiculos = $stats['total_veiculos'] ?? 0;
        $veiculos_ativos = $stats['veiculos_ativos'] ?? 0;
        $veiculos_manutencao = $stats['veiculos_manutencao'] ?? 0;
        $quilometragem_total = $stats['quilometragem_total'] ?? 0;
        
        // Calcula o offset para a paginação
        $offset = ($page - 1) * $limit;
        
        // Depois busca os veículos com paginação
        $sql = "SELECT v.*, 
                s.nome as status_nome,
                tc.nome as tipo_combustivel_nome,
                t.nome as tipo_nome,
                c.nome as categoria_nome,
                cr.nome as carroceria_nome,
                cavalo.nome as cavalo_nome,
                cavalo.eixos as cavalo_eixos,
                cavalo.tracao as cavalo_tracao,
                carreta.nome as carreta_nome,
                carreta.capacidade_media as carreta_capacidade
                FROM veiculos v
                LEFT JOIN status_veiculos s ON v.status_id = s.id
                LEFT JOIN tipos_combustivel tc ON v.tipo_combustivel_id = tc.id
                LEFT JOIN tipos t ON v.tipo_id = t.id
                LEFT JOIN categorias c ON v.categoria_id = c.id
                LEFT JOIN carrocerias cr ON v.carroceria_id = cr.id
                LEFT JOIN tipos_cavalos cavalo ON v.id_cavalo = cavalo.id
                LEFT JOIN tipos_carretas carreta ON v.id_carreta = carreta.id
                WHERE v.empresa_id = :empresa_id 
                ORDER BY v.id DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcula o total de páginas
        $total_pages = ceil($total_veiculos / $limit);
        
        return [
            'veiculos' => $veiculos,
            'total_pages' => $total_pages,
            'current_page' => $page
        ];
    } catch(PDOException $e) {
        error_log("Erro ao buscar veículos: " . $e->getMessage());
        return [
            'veiculos' => [],
            'total_pages' => 0,
            'current_page' => 1
        ];
    }
}

// Pegar a página atual da URL ou definir como 1
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

// Buscar os veículos com paginação
$result = getVehicles($current_page);
$veiculos = $result['veiculos'];
$total_pages = $result['total_pages'];
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
    /* Estilos para a seção de análise */
    .analytics-section {
        margin-top: 20px;
    }
    
    .analytics-section .section-header {
        margin-bottom: 20px;
    }
    
    .analytics-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }
    
    .analytics-card {
        background: var(--bg-secondary);
        border-radius: 8px;
        border: 1px solid var(--border-color);
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
        height: 250px;
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
                    <h1>Veículos</h1>
                    <div class="dashboard-actions">
                        <button id="addVehicleBtn" class="btn-add-widget">
                            <i class="fas fa-plus"></i> Novo Veículo
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
                            <h3>Total de Veículos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="totalVehicles"><?php echo $total_veiculos; ?></span>
                                <span class="metric-subtitle">Veículos cadastrados</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Veículos Ativos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="activeVehicles"><?php echo $veiculos_ativos; ?></span>
                                <span class="metric-subtitle">Em operação</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Em Manutenção</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="maintenanceVehicles"><?php echo $veiculos_manutencao; ?></span>
                                <span class="metric-subtitle">Neste mês</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Quilometragem Total</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="totalMileage"><?php echo formatKm($quilometragem_total); ?></span>
                                <span class="metric-subtitle">Percorridos</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Search and Filter -->
                <div class="filter-section">
                    <div class="search-box">
                        <input type="text" id="searchVehicle" placeholder="Buscar veículo...">
                        <i class="fas fa-search"></i>
                    </div>
                    
                    <div class="filter-options">
                        <select id="statusFilter">
                            <option value="">Todos os status</option>
                            <option value="Ativo">Ativo</option>
                            <option value="Manutenção">Em Manutenção</option>
                            <option value="Inativo">Inativo</option>
                        </select>
                        
                        <select id="typeFilter">
                            <option value="">Todos os tipos</option>
                            <option value="Mercedes">Mercedes-Benz</option>
                            <option value="Volvo">Volvo</option>
                            <option value="Scania">Scania</option>
                        </select>
                    </div>
                </div>
                
                <!-- Vehicles List Table -->
                <div class="data-table-container">
                    <table class="data-table" id="vehiclesTable">
                        <thead>
                            <tr>
                                <th>Placa</th>
                                <th>Modelo</th>
                                <th>Marca</th>
                                <th>Ano</th>
                                <th>Status</th>
                                <th>Cavalo</th>
                                <th>Carreta</th>
                                <th>Quilometragem</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($veiculos)): ?>
                                <?php foreach ($veiculos as $veiculo): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($veiculo['placa']); ?></td>
                                    <td><?php echo htmlspecialchars($veiculo['modelo']); ?></td>
                                    <td><?php echo htmlspecialchars($veiculo['marca']); ?></td>
                                    <td><?php echo htmlspecialchars($veiculo['ano']); ?></td>
                                    <td><span class="status-badge"><?php echo htmlspecialchars($veiculo['status_nome']); ?></span></td>
                                    <td>
                                        <?php if ($veiculo['cavalo_nome']): ?>
                                            <?php echo htmlspecialchars($veiculo['cavalo_nome']); ?>
                                            <small>(<?php echo $veiculo['cavalo_eixos']; ?> eixos, <?php echo $veiculo['cavalo_tracao']; ?>)</small>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($veiculo['carreta_nome']): ?>
                                            <?php echo htmlspecialchars($veiculo['carreta_nome']); ?>
                                            <small>(<?php echo $veiculo['carreta_capacidade']; ?> ton)</small>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatKm($veiculo['km_atual']); ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn-icon view-btn" data-id="<?php echo $veiculo['id']; ?>" title="Ver detalhes">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-icon edit-btn" data-id="<?php echo $veiculo['id']; ?>" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon delete-btn" data-id="<?php echo $veiculo['id']; ?>" title="Excluir">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center">Nenhum veículo encontrado</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination" id="vehiclesPagination">
                    <a href="#" class="pagination-btn disabled" id="prevPageBtn">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    
                    <span class="pagination-info" id="paginationInfo">
                        Página <span id="currentPage"><?php echo $current_page; ?></span> de <span id="totalPages"><?php echo $total_pages; ?></span>
                    </span>
                    
                    <a href="#" class="pagination-btn" id="nextPageBtn">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                
                <!-- Vehicle Analytics -->
                <div class="analytics-section">
                    <div class="section-header">
                        <h2>Análise de Frota</h2>
                    </div>
                    
                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Consumo de Combustível</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="fuelEfficiencyChart" height="200"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Custos de Manutenção</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="maintenanceCostChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    
    <!-- Add/Edit Vehicle Modal -->
    <div class="modal" id="vehicleModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Adicionar Veículo</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="vehicleForm">
                    <input type="hidden" id="vehicleId" name="id">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="placa">Placa*</label>
                            <input type="text" id="placa" name="placa" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="modelo">Modelo*</label>
                            <input type="text" id="modelo" name="modelo" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="marca">Marca</label>
                            <input type="text" id="marca" name="marca">
                        </div>
                        
                        <div class="form-group">
                            <label for="ano">Ano*</label>
                            <input type="number" id="ano" name="ano" min="1990" max="2050" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="cor">Cor</label>
                            <input type="text" id="cor" name="cor">
                        </div>
                        
                        <div class="form-group">
                            <label for="status_id">Status</label>
                            <select id="status_id" name="status_id">
                                <option value="1">Ativo</option>
                                <option value="2">Em Manutenção</option>
                                <option value="3">Inativo</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="id_cavalo">Tipo de Cavalo*</label>
                            <select id="id_cavalo" name="id_cavalo" required>
                                <option value="">Selecione um tipo de cavalo</option>
                                <?php
                                try {
                                    $conn = getConnection();
                                    $sql = "SELECT id, nome, eixos, tracao FROM tipos_cavalos ORDER BY nome";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute();
                                    while ($row = $stmt->fetch()) {
                                        echo "<option value='" . $row['id'] . "'>" . $row['nome'] . " (" . $row['eixos'] . " eixos, " . $row['tracao'] . ")</option>";
                                    }
                                } catch(PDOException $e) {
                                    error_log("Erro ao buscar tipos de cavalos: " . $e->getMessage());
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="id_carreta">Tipo de Carreta</label>
                            <select id="id_carreta" name="id_carreta">
                                <option value="">Selecione uma carreta</option>
                                <?php
                                try {
                                    $conn = getConnection();
                                    $sql = "SELECT id, nome, capacidade_media FROM tipos_carretas ORDER BY nome";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute();
                                    while ($row = $stmt->fetch()) {
                                        echo "<option value='" . $row['id'] . "'>" . $row['nome'] . " (" . $row['capacidade_media'] . " ton)</option>";
                                    }
                                } catch(PDOException $e) {
                                    error_log("Erro ao buscar tipos de carretas: " . $e->getMessage());
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="km_atual">Quilometragem</label>
                            <input type="number" id="km_atual" name="km_atual" min="0" step="0.01">
                        </div>
                        
                        <div class="form-group">
                            <label for="tipo_combustivel_id">Tipo de Combustível</label>
                            <select id="tipo_combustivel_id" name="tipo_combustivel_id">
                                <!-- Será preenchido via JavaScript -->
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="chassi">Número do Chassi</label>
                            <input type="text" id="chassi" name="chassi">
                        </div>
                        
                        <div class="form-group">
                            <label for="renavam">RENAVAM</label>
                            <input type="text" id="renavam" name="renavam">
                        </div>
                        
                        <div class="form-group">
                            <label for="capacidade_carga">Capacidade de Carga (kg)</label>
                            <input type="number" id="capacidade_carga" name="capacidade_carga" min="0" step="0.01">
                        </div>
                        
                        <div class="form-group">
                            <label for="capacidade_passageiros">Capacidade de Passageiros</label>
                            <input type="number" id="capacidade_passageiros" name="capacidade_passageiros" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="numero_motor">Número do Motor</label>
                            <input type="text" id="numero_motor" name="numero_motor">
                        </div>
                        
                        <div class="form-group">
                            <label for="proprietario">Proprietário</label>
                            <input type="text" id="proprietario" name="proprietario">
                        </div>
                        
                        <div class="form-group">
                            <label for="potencia_motor">Potência do Motor</label>
                            <input type="text" id="potencia_motor" name="potencia_motor">
                        </div>
                        
                        <div class="form-group">
                            <label for="numero_eixos">Número de Eixos</label>
                            <input type="number" id="numero_eixos" name="numero_eixos" min="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="carroceria_id">Carroceria</label>
                            <select id="carroceria_id" name="carroceria_id">
                                <!-- Será preenchido via JavaScript -->
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="documento">Documento</label>
                            <input type="file" id="documento" name="documento">
                        </div>
                        
                        <div class="form-group">
                            <label for="foto_veiculo">Foto do Veículo</label>
                            <input type="file" id="foto_veiculo" name="foto_veiculo">
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="observacoes">Observações</label>
                        <textarea id="observacoes" name="observacoes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button id="cancelVehicleBtn" class="btn-secondary">Cancelar</button>
                <button id="saveVehicleBtn" class="btn-primary">Salvar</button>
            </div>
        </div>
    </div>
    
    <!-- View Vehicle Details Modal -->
    <div class="modal" id="viewVehicleModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="viewModalTitle">Detalhes do Veículo</h2>
                <span class="close-modal close-view-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="details-container">
                    <div class="vehicle-header">
                        <div class="vehicle-primary-info">
                            <h3 id="vehicleModelYear"></h3>
                            <div class="vehicle-plate" id="vehiclePlate"></div>
                            <div class="vehicle-status" id="vehicleStatus"></div>
                        </div>
                    </div>
                    
                    <div class="tabs">
                        <button class="tab-btn active" data-tab="generalInfo">Informações Gerais</button>
                        <button class="tab-btn" data-tab="maintenance">Manutenções</button>
                        <button class="tab-btn" data-tab="documents">Documentos</button>
                        <button class="tab-btn" data-tab="costs">Custos</button>
                    </div>
                    
                    <div class="tab-content active" id="generalInfo">
                        <div class="info-grid">
                            <div class="info-group">
                                <label>Chassi</label>
                                <div id="detailChassisNumber"></div>
                            </div>
                            <div class="info-group">
                                <label>RENAVAM</label>
                                <div id="detailRenavam"></div>
                            </div>
                            <div class="info-group">
                                <label>Quilometragem</label>
                                <div id="detailMileage"></div>
                            </div>
                            <div class="info-group">
                                <label>Combustível</label>
                                <div id="detailFuelType"></div>
                            </div>
                            <div class="info-group">
                                <label>Tipo de Cavalo</label>
                                <div id="detailCavalo"></div>
                            </div>
                            <div class="info-group">
                                <label>Tipo de Carreta</label>
                                <div id="detailCarreta"></div>
                            </div>
                            <div class="info-group">
                                <label>Aquisição</label>
                                <div id="detailAcquisition"></div>
                            </div>
                            <div class="info-group">
                                <label>Motorista Atual</label>
                                <div id="detailDriver"></div>
                            </div>
                        </div>
                        
                        <div class="info-section">
                            <h4>Observações</h4>
                            <p id="detailNotes"></p>
                        </div>
                    </div>
                    
                    <div class="tab-content" id="maintenance">
                        <div class="maintenance-schedule">
                            <h4>Programação de Manutenção</h4>
                            <table class="info-table">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Última Data</th>
                                        <th>Próxima Data</th>
                                        <th>Intervalo (km)</th>
                                        <th>Próxima Km</th>
                                    </tr>
                                </thead>
                                <tbody id="maintenanceScheduleBody">
                                    <!-- Populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="maintenance-history">
                            <h4>Histórico de Manutenção</h4>
                            <table class="info-table">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Descrição</th>
                                        <th>Quilometragem</th>
                                        <th>Custo</th>
                                        <th>Mecânico</th>
                                        <th>Observações</th>
                                    </tr>
                                </thead>
                                <tbody id="maintenanceHistoryBody">
                                    <!-- Populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>

                        <div class="carretas-history">
                            <h4>Histórico de Carretas</h4>
                            <table class="info-table">
                                <thead>
                                    <tr>
                                        <th>Data da Troca</th>
                                        <th>Carreta</th>
                                        <th>Capacidade</th>
                                        <th>Motivo</th>
                                        <th>Observações</th>
                                    </tr>
                                </thead>
                                <tbody id="carretasHistoryBody">
                                    <!-- Populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>

                        <div class="km-history">
                            <h4>Histórico de Quilometragem</h4>
                            <table class="info-table">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Quilometragem</th>
                                        <th>Tipo</th>
                                        <th>Observações</th>
                                    </tr>
                                </thead>
                                <tbody id="kmHistoryBody">
                                    <!-- Populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="tab-content" id="documents">
                        <div class="documents-info">
                            <h4>Documentação</h4>
                            <div class="info-grid">
                                <div class="info-group">
                                    <label>Vencimento da Licença</label>
                                    <div id="detailLicenseExpiry"></div>
                                </div>
                                <div class="info-group">
                                    <label>Vencimento do Seguro</label>
                                    <div id="detailInsuranceExpiry"></div>
                                </div>
                                <div class="info-group">
                                    <label>Apólice de Seguro</label>
                                    <div id="detailInsurancePolicy"></div>
                                </div>
                                <div class="info-group">
                                    <label>Valor do Seguro</label>
                                    <div id="detailInsuranceValue"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-content" id="costs">
                        <div class="cost-summary">
                            <h4>Resumo de Custos</h4>
                            <div class="info-grid">
                                <div class="cost-card">
                                    <h5>Manutenção (Último Ano)</h5>
                                    <div class="cost-value" id="maintenanceCostValue">R$ 0,00</div>
                                </div>
                                <div class="cost-card">
                                    <h5>Combustível (Último Ano)</h5>
                                    <div class="cost-value" id="fuelCostValue">R$ 0,00</div>
                                </div>
                                <div class="cost-card">
                                    <h5>Custo por Km</h5>
                                    <div class="cost-value" id="costPerKm">R$ 0,00</div>
                                </div>
                                <div class="cost-card">
                                    <h5>Total de Custos</h5>
                                    <div class="cost-value" id="totalCostValue">R$ 0,00</div>
                                </div>
                            </div>
                            
                            <div class="chart-container">
                                <canvas id="vehicleCostChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="closeVehicleDetailsBtn" class="btn-secondary">Fechar</button>
                <button id="editFromDetailsBtn" class="btn-primary">Editar</button>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteVehicleModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirmar Exclusão</h2>
                <span class="close-modal close-delete-modal">&times;</span>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir o veículo <strong id="deleteVehiclePlate"></strong>?</p>
                <p class="warning-text">Esta ação não pode ser desfeita.</p>
            </div>
            <div class="modal-footer">
                <button id="cancelDeleteBtn" class="btn-secondary">Cancelar</button>
                <button id="confirmDeleteBtn" class="btn-danger">Excluir</button>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Files -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/vehicles.js"></script>
</body>
</html>
