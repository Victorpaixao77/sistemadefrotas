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
$page_title = "Manutenções";

// Debug session state
error_log("Session state in manutencoes.php: " . print_r($_SESSION, true));

// Função para buscar métricas do dashboard
function getDashboardMetrics($conn) {
    try {
        $empresa_id = $_SESSION['empresa_id'];
        $primeiro_dia_mes = date('Y-m-01');
        $ultimo_dia_mes = date('Y-m-t');
        
        // Total de manutenções do mês
        $sql_total = "SELECT COUNT(*) as total FROM manutencoes 
                     WHERE empresa_id = :empresa_id 
                     AND data_manutencao BETWEEN :primeiro_dia AND :ultimo_dia";
        $stmt_total = $conn->prepare($sql_total);
        $stmt_total->bindParam(':empresa_id', $empresa_id);
        $stmt_total->bindParam(':primeiro_dia', $primeiro_dia_mes);
        $stmt_total->bindParam(':ultimo_dia', $ultimo_dia_mes);
        $stmt_total->execute();
        $total_manutencoes = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total de preventivas do mês
        $sql_preventivas = "SELECT COUNT(*) as total FROM manutencoes m
                          JOIN tipos_manutencao tm ON m.tipo_manutencao_id = tm.id
                          WHERE m.empresa_id = :empresa_id 
                          AND tm.nome = 'Preventiva'
                          AND m.data_manutencao BETWEEN :primeiro_dia AND :ultimo_dia";
        $stmt_preventivas = $conn->prepare($sql_preventivas);
        $stmt_preventivas->bindParam(':empresa_id', $empresa_id);
        $stmt_preventivas->bindParam(':primeiro_dia', $primeiro_dia_mes);
        $stmt_preventivas->bindParam(':ultimo_dia', $ultimo_dia_mes);
        $stmt_preventivas->execute();
        $total_preventivas = $stmt_preventivas->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total de corretivas do mês
        $sql_corretivas = "SELECT COUNT(*) as total FROM manutencoes m
                         JOIN tipos_manutencao tm ON m.tipo_manutencao_id = tm.id
                         WHERE m.empresa_id = :empresa_id 
                         AND tm.nome = 'Corretiva'
                         AND m.data_manutencao BETWEEN :primeiro_dia AND :ultimo_dia";
        $stmt_corretivas = $conn->prepare($sql_corretivas);
        $stmt_corretivas->bindParam(':empresa_id', $empresa_id);
        $stmt_corretivas->bindParam(':primeiro_dia', $primeiro_dia_mes);
        $stmt_corretivas->bindParam(':ultimo_dia', $ultimo_dia_mes);
        $stmt_corretivas->execute();
        $total_corretivas = $stmt_corretivas->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Total de custos do mês
        $sql_custos = "SELECT COALESCE(SUM(valor), 0) as total FROM manutencoes 
                      WHERE empresa_id = :empresa_id 
                      AND data_manutencao BETWEEN :primeiro_dia AND :ultimo_dia";
        $stmt_custos = $conn->prepare($sql_custos);
        $stmt_custos->bindParam(':empresa_id', $empresa_id);
        $stmt_custos->bindParam(':primeiro_dia', $primeiro_dia_mes);
        $stmt_custos->bindParam(':ultimo_dia', $ultimo_dia_mes);
        $stmt_custos->execute();
        $total_custos = $stmt_custos->fetch(PDO::FETCH_ASSOC)['total'];
        
        return [
            'total_manutencoes' => $total_manutencoes,
            'total_preventivas' => $total_preventivas,
            'total_corretivas' => $total_corretivas,
            'total_custos' => $total_custos
        ];
    } catch(PDOException $e) {
        error_log("Erro ao buscar métricas do dashboard: " . $e->getMessage());
        return [
            'total_manutencoes' => 0,
            'total_preventivas' => 0,
            'total_corretivas' => 0,
            'total_custos' => 0
        ];
    }
}

// Função para buscar métricas técnicas (KPIs)
function getKPIMetrics($conn) {
    try {
        $empresa_id = $_SESSION['empresa_id'];
        
        // MTBF e MTTR (últimos 6 meses)
        $sql_mtbf_mttr = "SELECT 
            SUM(v.km_atual) as total_km,
            COUNT(DISTINCT m.id) as total_falhas,
            SUM(TIMESTAMPDIFF(HOUR, m.data_manutencao, m.data_conclusao)) as total_horas_manutencao
            FROM manutencoes m
            JOIN veiculos v ON m.veiculo_id = v.id
            WHERE m.empresa_id = :empresa_id 
            AND m.data_manutencao >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)";
        
        $stmt = $conn->prepare($sql_mtbf_mttr);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        $mtbf_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Custo por km
        $sql_custo_km = "SELECT 
            SUM(m.valor) as custo_total,
            SUM(v.km_atual) as km_total
            FROM manutencoes m
            JOIN veiculos v ON m.veiculo_id = v.id
            WHERE m.empresa_id = :empresa_id";
        
        $stmt = $conn->prepare($sql_custo_km);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        $custo_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Top 5 veículos com mais manutenções
        $sql_top_veiculos = "SELECT 
            v.placa,
            COUNT(*) as total_manutencoes,
            SUM(m.valor) as custo_total
            FROM manutencoes m
            JOIN veiculos v ON m.veiculo_id = v.id
            WHERE m.empresa_id = :empresa_id
            GROUP BY v.id
            ORDER BY total_manutencoes DESC
            LIMIT 5";
        
        $stmt = $conn->prepare($sql_top_veiculos);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        $top_veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Componentes com mais falhas
        $sql_componentes = "SELECT 
            cm.nome as componente,
            COUNT(*) as total_falhas
            FROM manutencoes m
            JOIN componentes_manutencao cm ON m.componente_id = cm.id
            WHERE m.empresa_id = :empresa_id
            GROUP BY cm.id
            ORDER BY total_falhas DESC
            LIMIT 10";
        
        $stmt = $conn->prepare($sql_componentes);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        $componentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Status das manutenções
        $sql_status = "SELECT 
            sm.nome as status,
            COUNT(*) as total
            FROM manutencoes m
            JOIN status_manutencao sm ON m.status_manutencao_id = sm.id
            WHERE m.empresa_id = :empresa_id
            GROUP BY sm.id";
        
        $stmt = $conn->prepare($sql_status);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        $status = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Evolução mensal
        $sql_evolucao = "SELECT 
            DATE_FORMAT(data_manutencao, '%Y-%m') as mes,
            COUNT(*) as total
            FROM manutencoes
            WHERE empresa_id = :empresa_id
            AND data_manutencao >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(data_manutencao, '%Y-%m')
            ORDER BY mes ASC";
        
        $stmt = $conn->prepare($sql_evolucao);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        $evolucao = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'mtbf' => $mtbf_data['total_falhas'] > 0 ? ($mtbf_data['total_km'] / $mtbf_data['total_falhas']) : 0,
            'mttr' => $mtbf_data['total_falhas'] > 0 ? ($mtbf_data['total_horas_manutencao'] / $mtbf_data['total_falhas']) : 0,
            'custo_km' => $custo_data['km_total'] > 0 ? ($custo_data['custo_total'] / $custo_data['km_total']) : 0,
            'top_veiculos' => $top_veiculos,
            'componentes' => $componentes,
            'status' => $status,
            'evolucao' => $evolucao
        ];
    } catch(PDOException $e) {
        error_log("Erro ao buscar KPIs: " . $e->getMessage());
        return [
            'mtbf' => 0,
            'mttr' => 0,
            'custo_km' => 0,
            'top_veiculos' => [],
            'componentes' => [],
            'status' => [],
            'evolucao' => []
        ];
    }
}

// Função para buscar manutenções do banco de dados
function getManutencoes($conn, $page = 1) {
    try {
        $empresa_id = $_SESSION['empresa_id'];
        $limit = 5; // Registros por página
        $offset = ($page - 1) * $limit;
        
        // Primeiro, conta o total de registros
        $sql_count = "SELECT COUNT(*) as total FROM manutencoes WHERE empresa_id = :empresa_id";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_count->execute();
        $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Consulta paginada
        $sql = "SELECT m.*, v.placa as veiculo_placa, tm.nome as tipo_nome, sm.nome as status_nome
                FROM manutencoes m
                LEFT JOIN veiculos v ON m.veiculo_id = v.id
                LEFT JOIN tipos_manutencao tm ON m.tipo_manutencao_id = tm.id
                LEFT JOIN status_manutencao sm ON m.status_manutencao_id = sm.id
                WHERE m.empresa_id = :empresa_id
                ORDER BY m.data_manutencao DESC, m.id DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'manutencoes' => $result,
            'total' => $total,
            'pagina_atual' => $page,
            'total_paginas' => ceil($total / $limit)
        ];
    } catch(PDOException $e) {
        error_log("Erro ao buscar manutenções: " . $e->getMessage());
        return [
            'manutencoes' => [],
            'total' => 0,
            'pagina_atual' => 1,
            'total_paginas' => 1
        ];
    }
}

// Pegar a página atual da URL ou definir como 1
$pagina_atual = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Buscar manutenções com paginação
$resultado = getManutencoes($conn, $pagina_atual);
$manutencoes = $resultado['manutencoes'];
$total_paginas = $resultado['total_paginas'];

// Buscar métricas do dashboard e KPIs
$metricas = getDashboardMetrics($conn);
$kpis = getKPIMetrics($conn);
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
    <script src="../js/maintenance.js"></script>
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
                                <span class="metric-value"><?php echo $metricas['total_manutencoes']; ?></span>
                                <span class="metric-subtitle">Total este mês</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Preventivas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $metricas['total_preventivas']; ?></span>
                                <span class="metric-subtitle">Manutenções preventivas</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Corretivas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $metricas['total_corretivas']; ?></span>
                                <span class="metric-subtitle">Manutenções corretivas</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Custos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($metricas['total_custos'], 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Total este mês</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- New KPI Cards -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>MTBF</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo number_format($kpis['mtbf'], 1); ?> km</span>
                                <span class="metric-subtitle">Tempo Médio entre Falhas</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>MTTR</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo number_format($kpis['mttr'], 1); ?> h</span>
                                <span class="metric-subtitle">Tempo Médio para Reparos</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Custo/KM</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($kpis['custo_km'], 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Custo por KM rodado</span>
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
                        <select id="vehicleFilter">
                            <option value="">Todos os veículos</option>
                            <!-- Será preenchido via JavaScript -->
                        </select>
                        
                        <select id="maintenanceTypeFilter">
                            <option value="">Todos os tipos</option>
                            <option value="Preventiva">Preventiva</option>
                            <option value="Corretiva">Corretiva</option>
                        </select>
                        
                        <select id="statusFilter">
                            <option value="">Todos os status</option>
                            <option value="Agendada">Agendada</option>
                            <option value="Em andamento">Em andamento</option>
                            <option value="Concluída">Concluída</option>
                            <option value="Cancelada">Cancelada</option>
                        </select>
                        
                        <select id="supplierFilter">
                            <option value="">Todos os fornecedores</option>
                            <!-- Será preenchido via JavaScript -->
                        </select>
                    </div>
                </div>
                
                <!-- Maintenance Table -->
                <div class="data-table-container">
                    <table class="data-table" id="maintenanceTable">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Veículo</th>
                                <th>Tipo</th>
                                <th>Descrição</th>
                                <th>Fornecedor</th>
                                <th>Status</th>
                                <th>Valor</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($manutencoes as $manutencao): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($manutencao['data_manutencao'])); ?></td>
                                <td><?php echo htmlspecialchars($manutencao['veiculo_placa']); ?></td>
                                <td><?php echo htmlspecialchars($manutencao['tipo_nome']); ?></td>
                                <td><?php echo htmlspecialchars($manutencao['descricao']); ?></td>
                                <td><?php echo htmlspecialchars($manutencao['fornecedor']); ?></td>
                                <td><?php echo htmlspecialchars($manutencao['status_nome']); ?></td>
                                <td>R$ <?php echo number_format($manutencao['valor'], 2, ',', '.'); ?></td>
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
                        <h2>Análise de Manutenções</h2>
                    </div>
                    
                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Custos de Manutenção (Últimos 6 meses)</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="maintenanceCostsChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Tipos de Manutenção</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="maintenanceTypesChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- New charts -->
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Status das Manutenções</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="maintenanceStatusChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Evolução Mensal de Manutenções</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="maintenanceEvolutionChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Top 5 Veículos - Custos</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="topVehiclesChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Componentes com Mais Falhas</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="componentsHeatmapChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <footer class="footer">
                <p>&copy; <?php echo date('Y'); ?> Sistema de Gestão de Frotas. Todos os direitos reservados.</p>
            </footer>
        </div>
    </div>
    
    <!-- Modal de Manutenção -->
    <div class="modal" id="maintenanceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Nova Manutenção</h2>
                <button class="close-modal" type="button" aria-label="Fechar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="maintenanceForm">
                    <input type="hidden" id="manutencaoId" name="id">
                    <input type="hidden" id="empresaId" name="empresa_id" value="<?php echo $_SESSION['empresa_id']; ?>">
                    
                    <div class="form-section">
                        <h3>Informações Básicas</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="data_manutencao">Data da Manutenção*</label>
                                <input type="date" id="data_manutencao" name="data_manutencao" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="veiculo_id">Veículo*</label>
                                <select id="veiculo_id" name="veiculo_id" required>
                                    <option value="">Selecione um veículo</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="tipo_manutencao_id">Tipo de Manutenção*</label>
                                <select id="tipo_manutencao_id" name="tipo_manutencao_id" required>
                                    <option value="">Selecione o tipo</option>
                                    <?php
                                    $sql = "SELECT id, nome FROM tipos_manutencao ORDER BY nome";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute();
                                    while ($tipo = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='" . $tipo['id'] . "'>" . htmlspecialchars($tipo['nome']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="componente_id">Componente*</label>
                                <select id="componente_id" name="componente_id" required>
                                    <option value="">Selecione o componente</option>
                                    <?php
                                    $sql = "SELECT id, nome FROM componentes_manutencao ORDER BY nome";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute();
                                    while ($componente = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='" . $componente['id'] . "'>" . htmlspecialchars($componente['nome']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="status_manutencao_id">Status*</label>
                                <select id="status_manutencao_id" name="status_manutencao_id" required>
                                    <option value="">Selecione o status</option>
                                    <?php
                                    $sql = "SELECT id, nome FROM status_manutencao ORDER BY nome";
                                    $stmt = $conn->prepare($sql);
                                    $stmt->execute();
                                    while ($status = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='" . $status['id'] . "'>" . htmlspecialchars($status['nome']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="km_atual">Quilometragem Atual*</label>
                                <input type="number" id="km_atual" name="km_atual" step="0.01" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Dados do Serviço</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="fornecedor">Fornecedor</label>
                                <input type="text" id="fornecedor" name="fornecedor" maxlength="255">
                            </div>
                            
                            <div class="form-group">
                                <label for="valor">Valor*</label>
                                <input type="number" id="valor" name="valor" step="0.01" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="custo_total">Custo Total</label>
                                <input type="number" id="custo_total" name="custo_total" step="0.01">
                            </div>
                            
                            <div class="form-group">
                                <label for="nota_fiscal">Nota Fiscal</label>
                                <input type="text" id="nota_fiscal" name="nota_fiscal" maxlength="255">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Detalhes do Serviço</h3>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="descricao">Descrição*</label>
                                <textarea id="descricao" name="descricao" rows="3" required></textarea>
                            </div>

                            <div class="form-group full-width">
                                <label for="descricao_servico">Descrição do Serviço*</label>
                                <textarea id="descricao_servico" name="descricao_servico" rows="3" required></textarea>
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="observacoes">Observações</label>
                                <textarea id="observacoes" name="observacoes" rows="3"></textarea>
                            </div>

                            <div class="form-group">
                                <label for="responsavel_aprovacao">Responsável pela Aprovação*</label>
                                <input type="text" id="responsavel_aprovacao" name="responsavel_aprovacao" required maxlength="255">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelMaintenanceBtn">
                    <i class="fas fa-times"></i>
                    <span>Cancelar</span>
                </button>
                <button type="button" class="btn-primary" id="saveMaintenanceBtn">
                    <i class="fas fa-save"></i>
                    <span>Salvar</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação de Exclusão -->
    <div class="modal" id="deleteMaintenanceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirmar Exclusão</h2>
                <button class="close-modal" type="button" aria-label="Fechar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Tem certeza que deseja excluir esta manutenção?</p>
                    <p class="warning-text">Esta ação não pode ser desfeita.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelDeleteBtn">
                    <i class="fas fa-times"></i>
                    <span>Cancelar</span>
                </button>
                <button type="button" class="btn-danger" id="confirmDeleteBtn">
                    <i class="fas fa-trash"></i>
                    <span>Excluir</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Ajuda -->
    <div class="modal" id="helpMaintenanceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ajuda - Gestão de Manutenções</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="help-section">
                    <h3>Visão Geral</h3>
                    <p>A página de Manutenções permite gerenciar todas as manutenções realizadas nos veículos da frota. Aqui você pode cadastrar, editar, visualizar e excluir registros de manutenções, além de acompanhar métricas importantes.</p>
                </div>

                <div class="help-section">
                    <h3>Funcionalidades Principais</h3>
                    <ul>
                        <li><strong>Nova Manutenção:</strong> Cadastre uma nova manutenção com informações detalhadas do serviço e componentes.</li>
                        <li><strong>Filtros:</strong> Use os filtros para encontrar manutenções específicas por veículo, tipo ou status.</li>
                        <li><strong>Exportar:</strong> Exporte os dados das manutenções para análise externa.</li>
                        <li><strong>Relatórios:</strong> Visualize relatórios e estatísticas de manutenções.</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Indicadores (KPIs)</h3>
                    <ul>
                        <li><strong>Total de Manutenções:</strong> Número total de manutenções no mês atual.</li>
                        <li><strong>Preventivas/Corretivas:</strong> Distribuição entre manutenções preventivas e corretivas.</li>
                        <li><strong>MTBF:</strong> Tempo médio entre falhas (Mean Time Between Failures).</li>
                        <li><strong>MTTR:</strong> Tempo médio para reparo (Mean Time To Repair).</li>
                        <li><strong>Custo por KM:</strong> Valor médio gasto em manutenções por quilômetro rodado.</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Ações Disponíveis</h3>
                    <ul>
                        <li><strong>Visualizar:</strong> Veja detalhes completos da manutenção, incluindo custos e serviços.</li>
                        <li><strong>Editar:</strong> Modifique informações de uma manutenção existente.</li>
                        <li><strong>Excluir:</strong> Remova um registro de manutenção do sistema (ação irreversível).</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Dicas Úteis</h3>
                    <ul>
                        <li>Mantenha um registro detalhado dos serviços realizados para histórico.</li>
                        <li>Acompanhe os custos por veículo para identificar tendências.</li>
                        <li>Programe manutenções preventivas baseadas na quilometragem.</li>
                        <li>Monitore os componentes com mais falhas para ações preventivas.</li>
                        <li>Utilize os relatórios para otimizar o planejamento de manutenções.</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('helpMaintenanceModal')">Fechar</button>
            </div>
        </div>
    </div>

    <!-- Initialize page -->
    <script>
        // Aguarda o DOM estar completamente carregado
        document.addEventListener('DOMContentLoaded', async function() {
            try {
                console.log('Iniciando carregamento dos dados...');
                
                // Busca os dados do servidor
                const response = await fetch('../includes/get_maintenance_data.php');
                if (!response.ok) {
                    throw new Error('Erro ao buscar dados: ' + response.statusText);
                }
                
                const data = await response.json();
                console.log('Dados recebidos:', data);
                
                // Atualiza os cards de KPI
                updateKPICards(data);
                
                // Inicializa os gráficos
                initializeMaintenanceCharts(data);
                
            } catch (error) {
                console.error('Erro ao inicializar página:', error);
            }
        });

        // Função para atualizar os cards de KPI
        function updateKPICards(data) {
            // Total de manutenções
            const totalElement = document.querySelector('.dashboard-card:nth-child(1) .metric-value');
            if (totalElement && data.total_manutencoes !== undefined) {
                totalElement.textContent = data.total_manutencoes;
            }

            // Total de preventivas
            const preventivasElement = document.querySelector('.dashboard-card:nth-child(2) .metric-value');
            if (preventivasElement && data.total_preventivas !== undefined) {
                preventivasElement.textContent = data.total_preventivas;
            }

            // Total de corretivas
            const corretivasElement = document.querySelector('.dashboard-card:nth-child(3) .metric-value');
            if (corretivasElement && data.total_corretivas !== undefined) {
                corretivasElement.textContent = data.total_corretivas;
            }

            // Custos totais
            const custosElement = document.querySelector('.dashboard-card:nth-child(4) .metric-value');
            if (custosElement && data.total_custos !== undefined) {
                custosElement.textContent = `R$ ${data.total_custos.toFixed(2).replace('.', ',')}`;
            }

            // MTBF
            const mtbfElement = document.querySelector('.dashboard-card:nth-child(5) .metric-value');
            if (mtbfElement && data.mtbf !== undefined) {
                mtbfElement.textContent = `${data.mtbf.toFixed(1)} km`;
            }

            // MTTR
            const mttrElement = document.querySelector('.dashboard-card:nth-child(6) .metric-value');
            if (mttrElement && data.mttr !== undefined) {
                mttrElement.textContent = `${data.mttr.toFixed(1)} h`;
            }

            // Custo por KM
            const custoKmElement = document.querySelector('.dashboard-card:nth-child(7) .metric-value');
            if (custoKmElement && data.cost_per_km !== undefined) {
                custoKmElement.textContent = `R$ ${data.cost_per_km.toFixed(2).replace('.', ',')}`;
            }
        }
    </script>
</body>
</html>