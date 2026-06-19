<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/../includes/sf_page_help.php';

// Configure session before starting it
configure_session();

// Start session
session_start();

// Check authentication
require_authentication();

// Set page title
$page_title = "Gestão de Motoristas";
$is_modern = !isset($_GET['classic']) || (string) $_GET['classic'] !== '1';

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
    <?php if ($is_modern): ?>
    <link rel="stylesheet" href="../css/fornc-modern-page.css">
    <?php endif; ?>
    
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
        body.gestao-motoristas-modern .dashboard-header { display: none; }
        body.gestao-motoristas-modern .dashboard-content.fornc-page { overflow-x: auto; }
        body.gestao-motoristas-modern #motoristTable.fornc-table { min-width: 860px; }
    </style>
    
    <!-- Custom scripts -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/motoristas.js"></script>
    <?php sf_page_help_css('..'); ?>
</head>
<body class="<?php echo $is_modern ? 'gestao-motoristas-modern' : ''; ?>">
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include '../includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include '../includes/header.php'; ?>
            
            <!-- Page Content -->
            <div class="dashboard-content<?php echo $is_modern ? ' fornc-page' : ''; ?>">
                <?php if ($is_modern): ?>
                <div class="fornc-kpi-strip fornc-kpi-strip--5">
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-user-check fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Motoristas ativos</span></div><span class="val" id="kpiTotalAtivos">0</span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-clipboard-list fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Checklists pendentes</span></div><span class="val" id="kpiChecklistsPendentes">0</span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-triangle-exclamation fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Infrações (30 dias)</span></div><span class="val" id="kpiInfracoesRecentes">0</span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-chart-line fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Melhor eficiência</span></div><span class="val" id="kpiMelhorEficiencia">-</span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-road fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Mais viagens</span></div><span class="val" id="kpiMaisViagens">-</span></div>
                </div>
                <div class="fornc-toolbar">
                    <div class="fornc-search-block">
                        <label for="searchMotorist">Busca rápida</label>
                        <div class="fornc-search-inner">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input type="text" id="searchMotorist" placeholder="Buscar motorista..." autocomplete="off">
                        </div>
                    </div>
                    <div class="fornc-filters-inline">
                        <div class="fg">
                            <label for="statusFilter">Status</label>
                            <select id="statusFilter">
                                <option value="">Todos os status</option>
                                <option value="Ativo">Ativo</option>
                                <option value="Inativo">Inativo</option>
                                <option value="Férias">Férias</option>
                                <option value="Afastado">Afastado</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label for="categoriaFilter">Categoria</label>
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
                    <div class="fornc-btn-row">
                        <button id="applyMotoristFilters" class="fornc-btn fornc-btn--accent" title="Aplicar filtros"><i class="fas fa-search"></i> Pesquisar</button>
                        <button id="clearMotoristFilters" class="fornc-btn fornc-btn--ghost" title="Limpar filtros"><i class="fas fa-undo"></i></button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="gestaoMotorColumnsBtn" title="Colunas da tabela" aria-label="Colunas da tabela"><i class="fas fa-columns"></i></button>
                        <button id="filterBtn" class="fornc-btn fornc-btn--ghost" title="Filtros"><i class="fas fa-sliders-h"></i> Opções</button>
                        <button id="exportBtn" class="fornc-btn fornc-btn--muted" title="Exportar"><i class="fas fa-file-export"></i> Exportar</button>
                        <button id="helpBtn" class="fornc-btn fornc-btn--ghost fornc-btn--icon" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>
                <?php else: ?>
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
                <?php endif; ?>
                
                <!-- KPI Cards Row -->
                <?php if (!$is_modern): ?>
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <i class="fas fa-user-check kpi-card-icon" aria-hidden="true"></i>
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
                            <i class="fas fa-clipboard-list kpi-card-icon" aria-hidden="true"></i>
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
                            <i class="fas fa-triangle-exclamation kpi-card-icon" aria-hidden="true"></i>
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
                            <i class="fas fa-gauge-high kpi-card-icon" aria-hidden="true"></i>
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
                            <i class="fas fa-route kpi-card-icon" aria-hidden="true"></i>
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
                <?php endif; ?>
                
                <!-- Search and Filter -->
                <?php if (!$is_modern): ?>
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

                        <button type="button" class="btn-restore-layout" id="applyMotoristFilters" title="Aplicar filtros">
                            <i class="fas fa-filter"></i>
                        </button>
                        <button type="button" class="btn-restore-layout" id="clearMotoristFilters" title="Limpar filtros">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
                <?php
                $gestao_motor_modern_empty = !empty($is_modern) && empty($motoristas);
                ?>
                <?php if (!empty($is_modern)): ?>
                <div class="fornc-empty-state<?php echo $gestao_motor_modern_empty ? ' visible' : ''; ?>" id="gestaoMotorEmptyState" role="status" aria-live="polite">
                    <p class="fornc-empty-state-title">Nenhum motorista encontrado</p>
                    <p class="fornc-empty-state-hint">Ajuste os filtros ou cadastre motoristas no cadastro principal.</p>
                    <a href="motorists.php" class="fornc-btn fornc-btn--primary"><i class="fas fa-user-plus"></i> Ir para cadastro de motoristas</a>
                </div>
                <?php endif; ?>
                
                <!-- Motorists Table -->
                <div class="<?php echo $is_modern ? 'fornc-table-wrap' : 'data-table-container'; ?>" id="gestaoMotorTableWrap"<?php echo $gestao_motor_modern_empty ? ' style="display:none"' : ''; ?>>
                    <table class="<?php echo $is_modern ? 'fornc-table' : 'data-table'; ?>" id="motoristTable">
                        <thead>
                            <tr>
                                <th<?php echo $is_modern ? ' data-col="nome"' : ''; ?>>Nome</th>
                                <th<?php echo $is_modern ? ' data-col="cnh"' : ''; ?>>CNH</th>
                                <th<?php echo $is_modern ? ' data-col="categoria"' : ''; ?>>Categoria</th>
                                <th<?php echo $is_modern ? ' data-col="status"' : ''; ?>>Status</th>
                                <th<?php echo $is_modern ? ' data-col="telefone"' : ''; ?>>Telefone</th>
                                <th<?php echo $is_modern ? ' data-col="validade_cnh"' : ''; ?>>Validade CNH</th>
                                <th<?php echo $is_modern ? ' data-col="acoes"' : ''; ?>>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($motoristas as $motorista): ?>
                            <tr>
                                <td<?php echo $is_modern ? ' data-col="nome"' : ''; ?>><?php echo htmlspecialchars($motorista['nome']); ?></td>
                                <td<?php echo $is_modern ? ' data-col="cnh"' : ''; ?>><?php echo htmlspecialchars($motorista['cnh']); ?></td>
                                <td<?php echo $is_modern ? ' data-col="categoria"' : ''; ?>><?php echo htmlspecialchars($motorista['categoria_cnh_nome'] ?? '-'); ?></td>
                                <td<?php echo $is_modern ? ' data-col="status"' : ''; ?>>
                                    <span class="status-badge status-<?php echo $motorista['disponibilidade_id'] ?? '1'; ?>">
                                        <?php echo htmlspecialchars($motorista['disponibilidade_nome'] ?? 'Ativo'); ?>
                                    </span>
                                </td>
                                <td<?php echo $is_modern ? ' data-col="telefone"' : ''; ?>><?php echo htmlspecialchars($motorista['telefone'] ?? '-'); ?></td>
                                <td<?php echo $is_modern ? ' data-col="validade_cnh"' : ''; ?>><?php echo $motorista['data_validade_cnh'] ? date('d/m/Y', strtotime($motorista['data_validade_cnh'])) : '-'; ?></td>
                                <td class="actions"<?php echo $is_modern ? ' data-col="acoes"' : ''; ?>>
                                    <button class="btn-icon view-btn" data-id="<?php echo $motorista['id']; ?>" title="Ver detalhes">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($motoristas) && empty($is_modern)): ?>
                            <tr>
                                <td colspan="7" class="text-center">Nenhum motorista encontrado</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($is_modern): ?><div class="fornc-pagination-bar"><?php endif; ?>
                <div class="pagination<?php echo $is_modern ? ' fornc-modern-pagination' : ''; ?>">
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
                <?php if ($is_modern): ?></div><?php endif; ?>

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
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="motoristModal">
        <div class="modal-content<?php echo $is_modern ? ' modal-lg fornc-modal--wide' : ''; ?>">
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
    <?php $sf_help_key = 'gestao_motoristas'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>


    <?php if (!empty($is_modern)): ?>
    <div class="modal fornc-modal fornc-columns-modal" id="gestaoMotorColumnsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Colunas da tabela</h2>
                <span class="close-modal" title="Fechar">&times;</span>
            </div>
            <div class="modal-body">
                <p class="text-muted" style="font-size:0.8rem; margin-top:0;">Marque as colunas desejadas e clique em <strong>Salvar preferências</strong>.</p>
                <div id="gestaoMotorColumnsToggleList">
                    <div class="col-toggle-row col-toggle-section">Colunas</div>
                    <label class="col-toggle-row"><span>Nome</span><input type="checkbox" data-col-toggle="nome" checked></label>
                    <label class="col-toggle-row"><span>CNH</span><input type="checkbox" data-col-toggle="cnh" checked></label>
                    <label class="col-toggle-row"><span>Categoria</span><input type="checkbox" data-col-toggle="categoria" checked></label>
                    <label class="col-toggle-row"><span>Status</span><input type="checkbox" data-col-toggle="status" checked></label>
                    <label class="col-toggle-row"><span>Telefone</span><input type="checkbox" data-col-toggle="telefone" checked></label>
                    <label class="col-toggle-row"><span>Validade CNH</span><input type="checkbox" data-col-toggle="validade_cnh" checked></label>
                    <label class="col-toggle-row"><span>Ações</span><input type="checkbox" data-col-toggle="acoes" checked></label>
                </div>
            </div>
            <div class="modal-footer columns-modal-footer">
                <button type="button" class="btn-secondary btn-columns-close close-modal">Fechar</button>
                <button type="button" class="btn-primary btn-columns-save" id="gestaoMotorColumnsSaveBtn">Salvar preferências</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- JavaScript Files -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php if (!empty($is_modern)): ?><script src="../js/fornc-table-columns.js"></script><?php endif; ?>
    <script>
    // DASHBOARDS E GRÁFICOS DE MOTORISTAS
    document.addEventListener('DOMContentLoaded', function() {
        if (document.body.classList.contains('gestao-motoristas-modern') && typeof window.initForncTableColumns === 'function') {
            window.initForncTableColumns({
                tableSelector: '#motoristTable',
                toggleListSelector: '#gestaoMotorColumnsToggleList',
                storageKey: 'sf_gestao_motoristas_cols_v1',
                columnsBtnId: 'gestaoMotorColumnsBtn',
                modalId: 'gestaoMotorColumnsModal',
                saveBtnId: 'gestaoMotorColumnsSaveBtn'
            });
        }
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
        const classic = <?php echo $is_modern ? 'false' : 'true'; ?>;
        window.location.href = '?page=' + page + (classic ? '&classic=1' : '');
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
    <?php sf_page_help_js('..'); ?>
</body>
</html> 