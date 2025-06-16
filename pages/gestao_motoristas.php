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
$page_title = "Gestão de Motoristas";

// Função para buscar motoristas do banco de dados
function getMotoristas($page = 1) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        $limit = 5; // Registros por página
        $offset = ($page - 1) * $limit;
        
        // Primeiro, conta o total de registros
        $sql_count = "SELECT COUNT(*) as total FROM motoristas WHERE empresa_id = :empresa_id";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_count->execute();
        $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Consulta paginada
        $sql = "SELECT m.*, 
                s.nome as status_nome,
                c.nome as categoria_nome
                FROM motoristas m
                LEFT JOIN status_motoristas s ON m.status_id = s.id
                LEFT JOIN categorias_motoristas c ON m.categoria_id = c.id
                WHERE m.empresa_id = :empresa_id
                ORDER BY m.nome ASC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'motoristas' => $result,
            'total' => $total,
            'pagina_atual' => $page,
            'total_paginas' => ceil($total / $limit)
        ];
    } catch(PDOException $e) {
        error_log("Erro ao buscar motoristas: " . $e->getMessage());
        return [
            'motoristas' => [],
            'total' => 0,
            'pagina_atual' => 1,
            'total_paginas' => 1
        ];
    }
}

// Pegar a página atual da URL ou definir como 1
$pagina_atual = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Buscar motoristas com paginação
$resultado = getMotoristas($pagina_atual);
$motoristas = $resultado['motoristas'];
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
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1>Gestão de Motoristas</h1>
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
                
                <!-- DASHBOARD CARDS -->
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <div class="card-header"><h3>Total de Motoristas Ativos</h3></div>
                        <div class="card-body"><div class="metric"><span class="metric-value" id="kpiTotalAtivos">0</span><span class="metric-subtitle">Ativos</span></div></div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header"><h3>Checklists Pendentes</h3></div>
                        <div class="card-body"><div class="metric"><span class="metric-value" id="kpiChecklistsPendentes">0</span><span class="metric-subtitle">Pendentes</span></div></div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header"><h3>Infrações Recentes</h3></div>
                        <div class="card-body"><div class="metric"><span class="metric-value" id="kpiInfracoesRecentes">0</span><span class="metric-subtitle">Últimos 30 dias</span></div></div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header"><h3>Melhor Eficiência</h3></div>
                        <div class="card-body"><div class="metric"><span class="metric-value" id="kpiMelhorEficiencia">-</span><span class="metric-subtitle">Km/L</span></div></div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header"><h3>Mais Viagens no Mês</h3></div>
                        <div class="card-body"><div class="metric"><span class="metric-value" id="kpiMaisViagens">-</span><span class="metric-subtitle">Motorista</span></div></div>
                    </div>
                </div>
                
                <?php echo displayFlashMessage(); ?>
                
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>CNH</th>
                                        <th>Categoria</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($motoristas as $motorista): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($motorista['nome']); ?></td>
                                        <td><?php echo htmlspecialchars($motorista['cnh']); ?></td>
                                        <td><?php echo htmlspecialchars($motorista['categoria_nome']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $motorista['status_id']; ?>">
                                                <?php echo htmlspecialchars($motorista['status_nome']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="view_motorista.php?id=<?php echo $motorista['id']; ?>" class="btn btn-info btn-sm" title="Visualizar">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_motorista.php?id=<?php echo $motorista['id']; ?>" class="btn btn-warning btn-sm" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-danger btn-sm" title="Excluir" onclick="confirmDelete(<?php echo $motorista['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($motoristas)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Nenhum motorista encontrado</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Paginação -->
                        <?php if ($total_paginas > 1): ?>
                        <div class="pagination">
                            <?php if ($pagina_atual > 1): ?>
                            <a href="?page=<?php echo $pagina_atual - 1; ?>" class="pagination-btn">
                                <i class="fas fa-chevron-left"></i> Anterior
                            </a>
                            <?php endif; ?>
                            
                            <span class="pagination-info">
                                Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?>
                            </span>
                            
                            <?php if ($pagina_atual < $total_paginas): ?>
                            <a href="?page=<?php echo $pagina_atual + 1; ?>" class="pagination-btn">
                                Próxima <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ANALYTICS SECTION: GRÁFICOS E TABELAS -->
            <div class="analytics-section">
                <div class="section-header"><h2>Análises Inteligentes de Motoristas</h2></div>
                <div class="analytics-grid">
                    <div class="analytics-card">
                        <div class="card-header"><h3>Ranking de Eficiência de Combustível</h3></div>
                        <div class="card-body"><canvas id="chartEficienciaCombustivel"></canvas></div>
                    </div>
                    <div class="analytics-card">
                        <div class="card-header"><h3>Checklists Pendentes por Motorista</h3></div>
                        <div class="card-body"><canvas id="chartChecklistsPendentes"></canvas></div>
                    </div>
                </div>
                <div class="analytics-grid">
                    <div class="analytics-card">
                        <div class="card-header"><h3>Histórico de Infrações/Advertências</h3></div>
                        <div class="card-body"><canvas id="chartInfracoes"></canvas></div>
                    </div>
                    <div class="analytics-card">
                        <div class="card-header"><h3>Distribuição de Viagens por Motorista</h3></div>
                        <div class="card-body"><canvas id="chartDistribuicaoViagens"></canvas></div>
                    </div>
                </div>
                <div class="analytics-grid">
                    <div class="analytics-card full-width">
                        <div class="card-header"><h3>Motoristas com Documentação Vencida/Próxima do Vencimento</h3></div>
                        <div class="card-body" style="overflow-x:auto; max-height:300px;">
                            <table class="data-table" id="tableDocumentacaoVencida">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>CNH</th>
                                        <th>Categoria</th>
                                        <th>Validade CNH</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Files -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    // DASHBOARDS E GRÁFICOS DE MOTORISTAS
    document.addEventListener('DOMContentLoaded', function() {
        // KPIs
        fetch('../api/gestao_motoristas_dashboard.php?action=kpis')
            .then(r => r.json()).then(res => {
                if (res.success) {
                    document.getElementById('kpiTotalAtivos').textContent = res.data.total_ativos;
                    document.getElementById('kpiChecklistsPendentes').textContent = res.data.checklists_pendentes;
                    document.getElementById('kpiInfracoesRecentes').textContent = res.data.infracoes_recentes;
                    document.getElementById('kpiMelhorEficiencia').textContent = res.data.melhor_eficiencia;
                    document.getElementById('kpiMaisViagens').textContent = res.data.mais_viagens;
                }
            });
        // Gráfico Eficiência Combustível
        fetch('../api/gestao_motoristas_dashboard.php?action=eficiencia_combustivel')
            .then(r => r.json()).then(res => {
                if (res.success) {
                    new Chart(document.getElementById('chartEficienciaCombustivel'), {
                        type: 'bar',
                        data: {labels: res.labels, datasets: [{label: 'Km/L', data: res.data, backgroundColor: 'rgba(54, 162, 235, 0.7)'}]},
                        options: {scales: {y: {beginAtZero: true}}}
                    });
                }
            });
        // Gráfico Checklists Pendentes
        fetch('../api/gestao_motoristas_dashboard.php?action=checklists_pendentes')
            .then(r => r.json()).then(res => {
                if (res.success) {
                    new Chart(document.getElementById('chartChecklistsPendentes'), {
                        type: 'bar',
                        data: {labels: res.labels, datasets: [{label: 'Pendentes', data: res.data, backgroundColor: 'rgba(255, 99, 132, 0.7)'}]},
                        options: {scales: {y: {beginAtZero: true}}}
                    });
                }
            });
        // Gráfico Infrações
        fetch('../api/gestao_motoristas_dashboard.php?action=infracoes')
            .then(r => r.json()).then(res => {
                if (res.success) {
                    new Chart(document.getElementById('chartInfracoes'), {
                        type: 'line',
                        data: {labels: res.labels, datasets: [{label: 'Infrações', data: res.data, borderColor: 'rgba(255, 206, 86, 1)', backgroundColor: 'rgba(255,206,86,0.1)', fill: true}]},
                        options: {scales: {y: {beginAtZero: true}}}
                    });
                }
            });
        // Gráfico Distribuição Viagens
        fetch('../api/gestao_motoristas_dashboard.php?action=distribuicao_viagens')
            .then(r => r.json()).then(res => {
                if (res.success) {
                    new Chart(document.getElementById('chartDistribuicaoViagens'), {
                        type: 'pie',
                        data: {labels: res.labels, datasets: [{label: 'Viagens', data: res.data, backgroundColor: ['#36A2EB','#FF6384','#FFCE56','#4BC0C0','#9966FF','#FF9F40','#C9CBCF']}]},
                        options: {}
                    });
                }
            });
        // Tabela Documentação Vencida
        fetch('../api/gestao_motoristas_dashboard.php?action=documentacao_vencida')
            .then(r => r.json()).then(res => {
                if (res.success) {
                    const tbody = document.querySelector('#tableDocumentacaoVencida tbody');
                    tbody.innerHTML = '';
                    res.data.forEach(row => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `<td>${row.nome}</td><td>${row.cnh}</td><td>${row.categoria_nome}</td><td>${row.validade_cnh}</td><td>${row.status_nome}</td>`;
                        tbody.appendChild(tr);
                    });
                }
            });
    });
    </script>
</body>
</html> 