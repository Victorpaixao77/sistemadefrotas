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
        $limit = 10; // Registros por página
        $offset = ($page - 1) * $limit;
        
        // Primeiro, conta o total de registros
        $sql_count = "SELECT COUNT(*) as total FROM motoristas WHERE empresa_id = :empresa_id";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_count->execute();
        $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Consulta paginada
        $sql = "SELECT m.*, 
                d.nome as disponibilidade_nome,
                c.nome as categoria_cnh_nome
                FROM motoristas m
                LEFT JOIN disponibilidades d ON m.disponibilidade_id = d.id
                LEFT JOIN categorias_cnh c ON m.categoria_cnh_id = c.id
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
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../logo.png">

    <style>
        .motorist-details {
            padding: 20px 0;
        }
        
        .detail-section {
            margin-bottom: 30px;
        }
        
        .detail-section h3 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .detail-item label {
            font-weight: bold;
            color: #666;
            font-size: 0.9em;
        }
        
        .detail-item span {
            color: #333;
            font-size: 1em;
            padding: 8px 12px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border-left: 3px solid #007bff;
        }
    </style>
    
    <!-- Custom scripts -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/motoristas.js"></script>
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
                            <h3>Motoristas Ativos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="kpiTotalAtivos">0</span>
                                <span class="metric-subtitle">Total ativos</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Checklists Pendentes</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="kpiChecklistsPendentes">0</span>
                                <span class="metric-subtitle">Pendentes</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Infrações Recentes</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="kpiInfracoesRecentes">0</span>
                                <span class="metric-subtitle">Últimos 30 dias</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Melhor Eficiência</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="kpiMelhorEficiencia">-</span>
                                <span class="metric-subtitle">Km/L</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Mais Viagens</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="kpiMaisViagens">-</span>
                                <span class="metric-subtitle">Motorista</span>
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
                            <option value="Inativo">Inativo</option>
                            <option value="Férias">Férias</option>
                            <option value="Afastado">Afastado</option>
                        </select>
                        
                        <select id="categoriaFilter">
                            <option value="">Todas as categorias</option>
                            <option value="A">Categoria A</option>
                            <option value="B">Categoria B</option>
                            <option value="C">Categoria C</option>
                            <option value="D">Categoria D</option>
                            <option value="E">Categoria E</option>
                        </select>
                    </div>
                </div>
                
                <!-- Motorists Table -->
                <div class="data-table-container">
                    <table class="data-table" id="motoristTable">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>CNH</th>
                                <th>Categoria</th>
                                <th>Status</th>
                                <th>Telefone</th>
                                <th>Validade CNH</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($motoristas as $motorista): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($motorista['nome']); ?></td>
                                <td><?php echo htmlspecialchars($motorista['cnh']); ?></td>
                                <td><?php echo htmlspecialchars($motorista['categoria_cnh_nome'] ?? '-'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $motorista['disponibilidade_id'] ?? '1'; ?>">
                                        <?php echo htmlspecialchars($motorista['disponibilidade_nome'] ?? 'Ativo'); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($motorista['telefone'] ?? '-'); ?></td>
                                <td><?php echo $motorista['data_validade_cnh'] ? date('d/m/Y', strtotime($motorista['data_validade_cnh'])) : '-'; ?></td>
                                <td class="actions">
                                    <button class="btn-icon view-btn" data-id="<?php echo $motorista['id']; ?>" title="Ver detalhes">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($motoristas)): ?>
                            <tr>
                                <td colspan="7" class="text-center">Nenhum motorista encontrado</td>
                            </tr>
                            <?php endif; ?>
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
                        <h2>Análise de Motoristas</h2>
                    </div>
                    
                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Ranking de Eficiência de Combustível</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="chartEficienciaCombustivel"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Checklists Pendentes por Motorista</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="chartChecklistsPendentes"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Histórico de Infrações</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="chartInfracoes"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Distribuição de Viagens</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="chartDistribuicaoViagens"></canvas>
                            </div>
                        </div>
                        
                        <div class="analytics-card full-width">
                            <div class="card-header">
                                <h3>Documentação Vencida/Próxima do Vencimento</h3>
                            </div>
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
            
            <!-- Footer -->
            <footer class="footer">
                <p>&copy; <?php echo date('Y'); ?> Sistema de Gestão de Frotas. Todos os direitos reservados.</p>
            </footer>
        </div>
    </div>
    
    <!-- Modal de Visualização de Motorista -->
    <div class="modal" id="motoristModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Detalhes do Motorista</h2>
                <button class="close-modal" type="button" aria-label="Fechar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="motoristDetails">
                    <!-- Detalhes do motorista serão carregados aqui -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="closeMotoristBtn">
                    <i class="fas fa-times"></i>
                    <span>Fechar</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Ajuda -->
    <div class="modal" id="helpMotoristModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ajuda - Gestão de Motoristas</h2>
                <button class="close-modal" type="button" aria-label="Fechar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="help-section">
                    <h3>Visão Geral</h3>
                    <p>A página de Gestão de Motoristas oferece uma visão completa da performance e status de todos os motoristas da empresa. Aqui você pode acompanhar eficiência, pendências e análises inteligentes.</p>
                </div>

                <div class="help-section">
                    <h3>Funcionalidades Principais</h3>
                    <ul>
                        <li><strong>Visualização:</strong> Visualize detalhes completos dos motoristas cadastrados.</li>
                        <li><strong>Filtros:</strong> Use os filtros para encontrar motoristas específicos por status ou categoria.</li>
                        <li><strong>Exportar:</strong> Exporte os dados dos motoristas para análise externa.</li>
                        <li><strong>Análises:</strong> Visualize relatórios e estatísticas de performance.</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Indicadores (KPIs)</h3>
                    <ul>
                        <li><strong>Motoristas Ativos:</strong> Número de motoristas atualmente ativos.</li>
                        <li><strong>Checklists Pendentes:</strong> Quantidade de checklists não realizados.</li>
                        <li><strong>Infrações Recentes:</strong> Número de infrações nos últimos 30 dias.</li>
                        <li><strong>Melhor Eficiência:</strong> Motorista com melhor km/l.</li>
                        <li><strong>Mais Viagens:</strong> Motorista com maior número de viagens.</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Ações Disponíveis</h3>
                    <ul>
                        <li><strong>Visualizar:</strong> Veja detalhes completos do motorista, incluindo informações pessoais e da CNH.</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Dicas Úteis</h3>
                    <ul>
                        <li>Monitore regularmente a eficiência de combustível para identificar oportunidades de melhoria.</li>
                        <li>Acompanhe os checklists pendentes para garantir a segurança da frota.</li>
                        <li>Analise o histórico de infrações para identificar padrões e necessidades de treinamento.</li>
                        <li>Configure alertas para vencimento de documentação importante.</li>
                        <li>Use os rankings para reconhecer motoristas com melhor desempenho.</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('helpMotoristModal')">Fechar</button>
            </div>
        </div>
    </div>

    <!-- JavaScript Files -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    // DASHBOARDS E GRÁFICOS DE MOTORISTAS
    document.addEventListener('DOMContentLoaded', function() {
        // KPIs
        fetch('../api/gestao_motoristas_dashboard.php?action=kpis')
            .then(r => r.json()).then(res => {
                if (res.success) {
                    document.getElementById('kpiTotalAtivos').textContent = res.data.total_ativos;
                    document.getElementById('kpiChecklistsPendentes').textContent = res.data.checklists_recentes;
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
                        tr.innerHTML = `<td>${row.nome}</td><td>${row.cnh}</td><td>${row.categoria_cnh_nome}</td><td>${row.validade_cnh}</td><td>${row.status_nome}</td>`;
                        tbody.appendChild(tr);
                    });
                }
            });
    });
    
    // Função para mudar página
    function changePage(page) {
        window.location.href = '?page=' + page;
        return false;
    }
    
    // Função para fechar modal específico
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
        }
    }
    </script>
</body>
</html> 