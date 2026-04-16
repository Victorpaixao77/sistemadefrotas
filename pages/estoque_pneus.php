<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Check if user is logged in, if not redirect to login page
require_authentication();

// Set page title
$page_title = "Estoque de Pneus";
$is_modern = !isset($_GET['classic']) || (string) $_GET['classic'] !== '1';
$classic_param = $is_modern ? '' : '&classic=1';

// Função para buscar pneus do estoque
function getEstoquePneus($page = 1, $opts = []) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        $per_page = isset($opts['per_page']) ? (int) $opts['per_page'] : 10;
        $allowed_per = [5, 10, 25, 50, 100];
        if (!in_array($per_page, $allowed_per)) {
            $per_page = 10;
        }
        $offset = ($page - 1) * $per_page;
        
        $where = ["p.empresa_id = :empresa_id"];
        $params = [':empresa_id' => $empresa_id];
        
        if (!empty($opts['status'])) {
            $where[] = "p.status_id = :status";
            $params[':status'] = $opts['status'];
        }
        if (isset($opts['disponivel']) && $opts['disponivel'] !== '') {
            $where[] = "COALESCE(ep.disponivel, 0) = :disponivel";
            $params[':disponivel'] = (int) $opts['disponivel'];
        }
        if (!empty($opts['search'])) {
            $term = '%' . $opts['search'] . '%';
            $where[] = "(p.numero_serie LIKE :search1 OR p.marca LIKE :search2 OR p.modelo LIKE :search3 OR p.medida LIKE :search4)";
            $params[':search1'] = $params[':search2'] = $params[':search3'] = $params[':search4'] = $term;
        }
        
        $where_sql = implode(' AND ', $where);
        
        $sql_count = "SELECT COUNT(*) as total FROM pneus p LEFT JOIN estoque_pneus ep ON ep.pneu_id = p.id WHERE $where_sql";
        $stmt_count = $conn->prepare($sql_count);
        foreach ($params as $k => $v) {
            $stmt_count->bindValue($k, $v);
        }
        $stmt_count->execute();
        $total = (int) $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
        
        $sql = "SELECT 
                    p.id as pneu_id,
                    p.numero_serie,
                    p.marca,
                    p.modelo,
                    p.medida,
                    p.sulco_inicial,
                    p.numero_recapagens,
                    p.data_ultima_recapagem,
                    p.lote,
                    s.nome as status_nome,
                    p.status_id,
                    p.created_at,
                    p.updated_at,
                    ep.disponivel
                FROM pneus p
                LEFT JOIN status_pneus s ON p.status_id = s.id
                LEFT JOIN estoque_pneus ep ON ep.pneu_id = p.id
                WHERE $where_sql
                ORDER BY p.id DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $conn->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'pneus' => $result,
            'total' => $total,
            'pagina_atual' => $page,
            'per_page' => $per_page,
            'total_paginas' => $total > 0 ? (int) ceil($total / $per_page) : 1
        ];
    } catch (PDOException $e) {
        if (function_exists('error_log')) {
            error_log('getEstoquePneus: ' . $e->getMessage());
        }
        return [
            'pneus' => [],
            'total' => 0,
            'pagina_atual' => 1,
            'per_page' => 10,
            'total_paginas' => 1
        ];
    }
}

// Pegar parâmetros da URL
$pagina_atual = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;
$opts = [
    'per_page' => $per_page,
    'status'   => isset($_GET['status']) ? $_GET['status'] : '',
    'disponivel' => isset($_GET['disponivel']) ? $_GET['disponivel'] : '',
    'search'   => isset($_GET['search']) ? trim($_GET['search']) : ''
];
$resultado = getEstoquePneus($pagina_atual, $opts);
$pneus = $resultado['pneus'];
$total_paginas = $resultado['total_paginas'];
$per_page = $resultado['per_page'];
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
</head>
<body class="<?php echo $is_modern ? 'estoque-pneus-modern' : ''; ?>">
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include '../includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include '../includes/header.php'; ?>
            
            <!-- Page Content -->
            <div class="dashboard-content<?php echo $is_modern ? ' fornc-page' : ''; ?>">
                <style>
                    body.estoque-pneus-modern .dashboard-content.fornc-page { overflow-x: auto; }
                    body.estoque-pneus-modern .dashboard-header { display: none; }
                    body.estoque-pneus-modern .dashboard-grid { display: none; }
                    body.estoque-pneus-modern .filter-section { display: none; }
                </style>

                <?php if ($is_modern): ?>
                <div class="fornc-kpi-strip">
                    <div class="fornc-kpi-cell"><span class="lbl">Total de pneus</span><span class="val"><?php echo (int) $resultado['total']; ?></span></div>
                </div>
                <?php endif; ?>
                <?php if ($is_modern): ?>
                <div class="fornc-toolbar">
                    <form method="get" action="estoque_pneus.php" id="estoquePneusSearchForm" class="fornc-search-block">
                        <label for="searchEstoquePneus">Busca rápida</label>
                        <div class="fornc-search-inner">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input
                                type="text"
                                id="searchEstoquePneus"
                                name="search"
                                value="<?php echo htmlspecialchars($opts['search'] ?? ''); ?>"
                                placeholder="Buscar (série, marca, modelo...)"
                                autocomplete="off"
                            >
                        </div>
                        <input type="hidden" name="page" value="1">
                        <?php if (!empty($opts['status'])): ?>
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($opts['status']); ?>">
                        <?php endif; ?>
                        <?php if (isset($opts['disponivel']) && $opts['disponivel'] !== ''): ?>
                            <input type="hidden" name="disponivel" value="<?php echo htmlspecialchars($opts['disponivel']); ?>">
                        <?php endif; ?>
                        <?php if (!empty($opts['per_page'])): ?>
                            <input type="hidden" name="per_page" value="<?php echo (int)$opts['per_page']; ?>">
                        <?php endif; ?>
                    </form>

                    <div class="fornc-btn-row">
                        <button
                            type="submit"
                            form="estoquePneusSearchForm"
                            class="fornc-btn fornc-btn--accent"
                            title="Pesquisar"
                        >
                            <i class="fas fa-search"></i> Pesquisar
                        </button>
                        <button id="filterBtn" class="fornc-btn fornc-btn--ghost" title="Filtros" type="button">
                            <i class="fas fa-filter"></i>
                        </button>
                        <button id="exportBtn" class="fornc-btn fornc-btn--muted" title="Exportar" type="button">
                            <i class="fas fa-file-export"></i>
                        </button>
                        <button id="helpBtn" class="fornc-btn fornc-btn--ghost fornc-btn--icon" title="Ajuda" aria-label="Ajuda" type="button">
                            <i class="fas fa-question-circle"></i>
                        </button>
                    </div>
                </div>
                <?php else: ?>
                <div class="dashboard-header">
                    <h1><?php echo $page_title; ?></h1>
                    <div class="dashboard-actions">
                        <form method="get" action="estoque_pneus.php" class="search-form" style="display:flex; gap:8px; align-items:center;">
                            <input type="hidden" name="page" value="1">
                            <?php if (!empty($opts['status'])): ?><input type="hidden" name="status" value="<?php echo htmlspecialchars($opts['status']); ?>"><?php endif; ?>
                            <?php if (isset($opts['disponivel']) && $opts['disponivel'] !== ''): ?><input type="hidden" name="disponivel" value="<?php echo htmlspecialchars($opts['disponivel']); ?>"><?php endif; ?>
                            <?php if (!empty($opts['per_page'])): ?><input type="hidden" name="per_page" value="<?php echo (int)$opts['per_page']; ?>"><?php endif; ?>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($opts['search'] ?? ''); ?>" placeholder="Buscar (série, marca, modelo...)">
                            <button type="submit" class="btn-primary"><i class="fas fa-search"></i></button>
                        </form>
                        <div class="view-controls">
                            <button id="filterBtn" class="btn-restore-layout" title="Filtros">
                                <i class="fas fa-filter"></i>
                            </button>
                            <button id="exportBtn" class="btn-toggle-layout" title="Exportar">
                                <i class="fas fa-file-export"></i>
                            </button>
                            <button id="helpBtn" class="btn-help" title="Ajuda" aria-label="Ajuda">
                                <i class="fas fa-question-circle"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- KPI Cards Row -->
                <div class="dashboard-grid mb-4">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Total de Pneus</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $resultado['total']; ?></span>
                                <span class="metric-subtitle">Pneus em estoque</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtro Por página (igual abastecimentos) -->
                <div class="filter-section" style="margin-bottom:1rem;">
                    <form method="get" action="estoque_pneus.php" id="formPerPageEstoque" style="display:inline-flex; align-items:center; gap:0.5rem;">
                        <input type="hidden" name="page" value="1">
                        <?php if (!empty($opts['status'])): ?><input type="hidden" name="status" value="<?php echo htmlspecialchars($opts['status']); ?>"><?php endif; ?>
                        <?php if (isset($opts['disponivel']) && $opts['disponivel'] !== ''): ?><input type="hidden" name="disponivel" value="<?php echo htmlspecialchars($opts['disponivel']); ?>"><?php endif; ?>
                        <?php if (!empty($opts['search'])): ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($opts['search']); ?>"><?php endif; ?>
                        <span class="filter-label">Por página</span>
                        <select name="per_page" class="filter-per-page" title="Registros por página" onchange="this.form.submit()">
                            <option value="5"  <?php echo $per_page == 5   ? 'selected' : ''; ?>>5</option>
                            <option value="10" <?php echo $per_page == 10  ? 'selected' : ''; ?>>10</option>
                            <option value="25" <?php echo $per_page == 25  ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $per_page == 50  ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                    </form>
                </div>
                
                <!-- Table Section -->
                <div class="<?php echo $is_modern ? 'fornc-table-wrap' : 'table-container'; ?>">
                    <table class="<?php echo $is_modern ? 'fornc-table' : 'data-table'; ?>">
                        <thead>
                            <tr>
                                <th>Número de Série</th>
                                <th>Marca</th>
                                <th>Modelo</th>
                                <th>Medida</th>
                                <th>Status</th>
                                <th>Disponível</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pneus as $pneu): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pneu['numero_serie']); ?></td>
                                <td><?php echo htmlspecialchars($pneu['marca']); ?></td>
                                <td><?php echo htmlspecialchars($pneu['modelo']); ?></td>
                                <td><?php echo htmlspecialchars($pneu['medida']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($pneu['status_nome']); ?>">
                                        <?php echo htmlspecialchars($pneu['status_nome']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (isset($pneu['disponivel']) && $pneu['disponivel'] == 1): ?>
                                        <span class="status-badge status-success">Sim</span>
                                    <?php else: ?>
                                        <span class="status-badge status-danger">Não</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <button class="btn-icon view-btn" data-id="<?php echo $pneu['pneu_id']; ?>" title="Ver detalhes">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon edit-btn" data-id="<?php echo $pneu['pneu_id']; ?>" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginação (igual abastecimentos.php) -->
                <?php
                $base_params = ['page' => 1, 'per_page' => $per_page];
                if (!empty($opts['status'])) $base_params['status'] = $opts['status'];
                if (isset($opts['disponivel']) && $opts['disponivel'] !== '') $base_params['disponivel'] = $opts['disponivel'];
                if (!empty($opts['search'])) $base_params['search'] = $opts['search'];
                $prev_params = array_merge($base_params, ['page' => max(1, $pagina_atual - 1)]);
                $next_params = array_merge($base_params, ['page' => min($total_paginas, $pagina_atual + 1)]);
                ?>
                <?php if ($is_modern): ?>
                <div class="fornc-pagination-bar">
                <div class="pagination fornc-modern-pagination">
                    <a href="estoque_pneus.php?<?php echo htmlspecialchars(http_build_query($prev_params)); ?>"
                       class="pagination-btn <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <span class="pagination-info">
                        <?php if ($total_paginas > 1): ?>Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?> (<?php echo (int)$resultado['total']; ?> registros)
                        <?php else: ?><?php echo (int)$resultado['total']; ?> registros<?php endif; ?>
                    </span>
                    <a href="estoque_pneus.php?<?php echo htmlspecialchars(http_build_query($next_params)); ?>"
                       class="pagination-btn <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                </div>
                <?php else: ?>
                <div class="pagination">
                    <a href="estoque_pneus.php?<?php echo htmlspecialchars(http_build_query($prev_params)); ?><?php echo $classic_param; ?>"
                       class="pagination-btn <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <span class="pagination-info">
                        <?php if ($total_paginas > 1): ?>Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?> (<?php echo (int)$resultado['total']; ?> registros)
                        <?php else: ?><?php echo (int)$resultado['total']; ?> registros<?php endif; ?>
                    </span>
                    <a href="estoque_pneus.php?<?php echo htmlspecialchars(http_build_query($next_params)); ?><?php echo $classic_param; ?>"
                       class="pagination-btn <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Footer -->
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    
    <!-- Filter Modal -->
    <div id="filterModal" class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Filtrar Estoque</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="filterForm" method="get" action="estoque_pneus.php">
                    <input type="hidden" name="page" value="1">
                    <input type="hidden" name="per_page" value="<?php echo (int)($resultado['per_page'] ?? 10); ?>">
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select id="status" name="status">
                            <option value="">Todos</option>
                            <?php
                            try {
                                $conn = getConnection();
                                $st = $conn->query("SELECT id, nome FROM status_pneus ORDER BY nome");
                                while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                                    $sel = (isset($opts['status']) && $opts['status'] === $row['id']) ? ' selected' : '';
                                    echo '<option value="' . (int)$row['id'] . '"' . $sel . '>' . htmlspecialchars($row['nome']) . '</option>';
                                }
                            } catch (Exception $e) { /* ignore */ }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="disponivel">Disponibilidade:</label>
                        <select id="disponivel" name="disponivel">
                            <option value="">Todos</option>
                            <option value="1" <?php echo (isset($opts['disponivel']) && $opts['disponivel'] === '1') ? 'selected' : ''; ?>>Disponível</option>
                            <option value="0" <?php echo (isset($opts['disponivel']) && $opts['disponivel'] === '0') ? 'selected' : ''; ?>>Indisponível</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="filter_search">Buscar:</label>
                        <input type="text" id="filter_search" name="search" value="<?php echo htmlspecialchars($opts['search'] ?? ''); ?>" placeholder="Série, marca, modelo...">
                    </div>
                    <button type="submit" class="btn-primary">Aplicar Filtros</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Help Modal -->
    <div id="helpModal" class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ajuda - Estoque de Pneus</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="help-section">
                    <h3>Visão Geral</h3>
                    <p>A página de Estoque de Pneus permite gerenciar todos os pneus disponíveis na sua frota. Aqui você pode:</p>
                    <ul>
                        <li>Visualizar todos os pneus em estoque</li>
                        <li>Filtrar pneus por status e disponibilidade</li>
                        <li>Ver detalhes de cada pneu</li>
                        <li>Editar informações dos pneus</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="../js/main.js"></script>
    <script>
        function buildQueryString(overrides) {
            const params = new URLSearchParams(window.location.search);
            if (overrides) {
                Object.keys(overrides).forEach(function(k) { params.set(k, overrides[k]); });
            }
            return params.toString();
        }
        function changePage(page) {
            const qs = buildQueryString({ page: page });
            window.location.href = 'estoque_pneus.php' + (qs ? '?' + qs : '');
            return false;
        }
        
        // Fechar modal ao clicar fora
        document.getElementById('filterModal').addEventListener('click', function(e) {
            if (e.target === this) this.style.display = 'none';
        });
        document.querySelector('#filterModal .close-modal').addEventListener('click', function() {
            document.getElementById('filterModal').style.display = 'none';
        });
    </script>
    
    <!-- JavaScript Files -->
    <script src="../js/dashboard.js"></script>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/sortable.js"></script>
    <script src="../js/charts.js"></script>
</body>
</html> 