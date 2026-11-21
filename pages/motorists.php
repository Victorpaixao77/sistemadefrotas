
<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once __DIR__ . '/../includes/sf_page_help.php';
require_once '../includes/functions.php';
require_once '../includes/progress_bars.php';
require_once '../includes/sf_api_base.php';
require_once __DIR__ . '/../includes/motorists_list.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Check if user is logged in, if not redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header('location: ' . sf_app_url('login.php'));
    exit;
}
if (function_exists('csrf_token_get')) {
    require_once __DIR__ . '/../includes/csrf.php';
    csrf_token_get();
}
if (function_exists('sf_release_session_lock')) {
    sf_release_session_lock();
}

// Set page title
$page_title = "Motoristas";

// Por página: 5, 10, 25, 50, 100 — padrão 10 (usado pelo JS/API)
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if (!in_array($per_page, [5, 10, 25, 50, 100], true)) {
    $per_page = 10;
}

// Layout moderno por padrão (como rotas / fornecedores_moderno); ?classic=1 para o anterior
$is_modern = !isset($_GET['classic']) || (string)$_GET['classic'] !== '1';

// Load motorists for listing and metrics
$conn = getConnection();
$empresa_id = $_SESSION['empresa_id'];

// Sincronizar alertas de CNH vencendo para o sino de notificações (máx. 1 por dia)
require_once __DIR__ . '/../includes/sync_cnh_notificacoes.php';

$mnt_page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$mnt_search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$mnt_status = isset($_GET['status']) && $_GET['status'] !== '' ? (string) $_GET['status'] : null;
$mnt_categoria_cnh = isset($_GET['categoria_cnh']) && $_GET['categoria_cnh'] !== '' ? (string) $_GET['categoria_cnh'] : null;
$mnt_tipo_contrato = isset($_GET['tipo_contrato']) && $_GET['tipo_contrato'] !== '' ? (string) $_GET['tipo_contrato'] : null;
$mnt_sort = isset($_GET['sort']) ? trim((string) $_GET['sort']) : 'nome';
$mnt_order = (isset($_GET['order']) && strtoupper((string) $_GET['order']) === 'DESC') ? 'DESC' : 'ASC';
$mnt_only_fav = isset($_GET['only_favoritos']) && (string) $_GET['only_favoritos'] === '1';
$mnt_uid = $_SESSION['usuario_id'] ?? $_SESSION['id'] ?? $_SESSION['user_id'] ?? null;

$mnt_initial = ['success' => true, 'motorists' => [], 'pagination' => ['page' => 1, 'limit' => $per_page, 'total' => 0, 'total_pages' => 1], 'summary' => []];
if ($empresa_id) {
    try {
        $mnt_initial = motorists_fetch_list($conn, (int) $empresa_id, $mnt_uid ? (int) $mnt_uid : null, [
            'limit' => $per_page,
            'page' => $mnt_page,
            'status' => $mnt_status,
            'search' => $mnt_search !== '' ? $mnt_search : null,
            'categoria_cnh' => $mnt_categoria_cnh,
            'tipo_contrato' => $mnt_tipo_contrato,
            'sort' => $mnt_sort,
            'order' => $mnt_order,
            'only_favoritos' => $mnt_only_fav,
        ]);
    } catch (Throwable $e) {
        error_log('motorists SSR: ' . $e->getMessage());
    }
}

$mnt_summary = $mnt_initial['summary'] ?? [];
$mnt_rows = $mnt_initial['motorists'] ?? [];
$mnt_pag = $mnt_initial['pagination'] ?? ['page' => 1, 'limit' => $per_page, 'total' => 0, 'total_pages' => 1];
$mnt_total = (int) ($mnt_pag['total'] ?? 0);
$mnt_cur_page = (int) ($mnt_pag['page'] ?? 1);
$mnt_total_pages = max(1, (int) ($mnt_pag['total_pages'] ?? 1));

$total_motoristas = (int) ($mnt_summary['total_motorists'] ?? 0);
$motoristas_disponiveis = (int) ($mnt_summary['motorists_ativos'] ?? 0);
$motoristas_indisponiveis = (int) ($mnt_summary['motorists_ferias'] ?? 0)
    + (int) ($mnt_summary['motorists_licenca'] ?? 0)
    + (int) ($mnt_summary['motorists_inativos'] ?? 0)
    + (int) ($mnt_summary['motorists_afastados'] ?? 0);
$media_comissao = (float) ($mnt_summary['media_comissao_pct'] ?? 0);

// Banner CPF ausente (query leve, independente dos filtros da lista)
$stmt_sem_cpf = $conn->prepare("
    SELECT COUNT(*) AS sem_cpf FROM motoristas m
    WHERE m.empresa_id = :empresa_id
    AND (TRIM(COALESCE(m.cpf, '')) = '' OR REPLACE(REPLACE(REPLACE(m.cpf, '.', ''), '-', ''), ' ', '') = '')
");
$stmt_sem_cpf->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
$stmt_sem_cpf->execute();
$motoristas_sem_cpf = (int) ($stmt_sem_cpf->fetchColumn() ?: 0);
$motoristas = $mnt_rows;

// Helper functions
if (!function_exists('normalize_status_class')) {
    function normalize_status_class(?string $value): string
    {
        if (!$value) {
            return '';
        }

        $slug = iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }
}

if (!function_exists('format_phone_br')) {
    function format_phone_br(?string $phone): string
    {
        if (!$phone) {
            return '-';
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) === 11) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7));
        }
        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6));
        }

        return $phone;
    }
}

if (!function_exists('format_cpf_br')) {
    function format_cpf_br(?string $cpf): string
    {
        if (!$cpf) {
            return '-';
        }

        $digits = preg_replace('/\D+/', '', $cpf);
        if (strlen($digits) !== 11) {
            return $cpf;
        }

        return sprintf('%s.%s.%s-%s',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 3),
            substr($digits, 9, 2)
        );
    }
}
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
    <?php if ($is_modern): ?>
    <link rel="stylesheet" href="../css/fornc-modern-page.css">
    <?php endif; ?>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../logo.png">
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
    <script src="../js/sf-charts-premium.js"></script>
    
    <link rel="stylesheet" href="../css/motorists-page.css">
    <?php sf_page_help_css('..'); ?>
</head>
<body class="<?php echo $is_modern ? 'motorists-modern' : ''; ?>">
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include '../includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include '../includes/header.php'; ?>
            
            <!-- Page Content -->
            <div class="dashboard-content<?php echo $is_modern ? ' motoristas-modern-page fornc-page' : ''; ?>">
                <?php if (!$is_modern): ?>
                <div class="dashboard-header">
                    <h1>Motoristas</h1>
                    <div class="dashboard-actions">
                        <button id="addMotoristBtn" class="btn-add-widget">
                            <i class="fas fa-plus"></i> Novo Motorista
                        </button>
                        <button id="rankingBtn" class="btn-add-widget" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin-left: 10px;">
                            <i class="fas fa-trophy"></i> Ranking de Performance
                        </button>
                        <button id="gamificacaoBtn" class="btn-add-widget" style="background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%); margin-left: 10px;">
                            <i class="fas fa-gamepad"></i> Gamificação
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
                <?php endif; ?>
                
                <?php if ($is_modern): ?>
                <div class="fornc-kpi-strip">
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-users fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Total</span></div><span class="val" id="totalMotorists"><?php echo (int) $total_motoristas; ?></span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-user-check fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Ativos</span></div><span class="val" id="activeMotorists"><?php echo (int) $motoristas_disponiveis; ?></span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-user-slash fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Indisponíveis</span></div><span class="val" id="kpiIndisponiveis"><?php echo (int) $motoristas_indisponiveis; ?></span></div>
                    <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-percent fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Média comissão</span></div><span class="val" id="kpiComissaoMedia"><?php echo number_format($media_comissao, 2, ',', '.'); ?>%</span></div>
                </div>
                <?php else: ?>
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <i class="fas fa-users kpi-card-icon" aria-hidden="true"></i>
                            <h3>Total de Motoristas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="totalMotorists"><?php echo $total_motoristas; ?></span>
                                <span class="metric-subtitle">Motoristas cadastrados</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <i class="fas fa-user-check kpi-card-icon" aria-hidden="true"></i>
                            <h3>Motoristas Ativos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="activeMotorists"><?php echo $motoristas_disponiveis; ?></span>
                                <span class="metric-subtitle">Em serviço</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <i class="fas fa-user-clock kpi-card-icon" aria-hidden="true"></i>
                            <h3>Status</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="kpiIndisponiveis"><?php echo $motoristas_indisponiveis; ?></span>
                                <span class="metric-subtitle">Motoristas indisponíveis</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <i class="fas fa-percent kpi-card-icon" aria-hidden="true"></i>
                            <h3>Comissão</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="kpiComissaoMedia"><?php echo number_format($media_comissao, 2, ',', '.'); ?>%</span>
                                <span class="metric-subtitle">Média de comissão configurada</span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div id="cnhAlertasWidget" class="cnh-alertas-widget" style="display:none; margin-bottom: 1rem; padding: 0.75rem 1rem; border-radius: 8px; background: var(--bg-secondary); border: 1px solid var(--border-color);">
                    <h4 style="margin: 0 0 0.5rem 0; font-size: 0.95rem;"><i class="fas fa-exclamation-triangle"></i> CNH vencendo</h4>
                    <div id="cnhAlertasContent" style="font-size: 0.9rem;"></div>
                </div>
                
                <?php if ($motoristas_sem_cpf > 0): ?>
                <div class="alert alert-info" style="margin-bottom: 1rem; padding: 0.6rem 1rem; font-size: 0.9rem;">
                    <i class="fas fa-info-circle"></i> <strong>Atenção:</strong> <?php echo $motoristas_sem_cpf; ?> motorista(s) cadastrado(s) sem CPF. O CPF é necessário para emissão de MDF-e e para vincular corretamente o motorista na importação de XML (NF-e/CT-e). Motoristas sem CPF não poderão ser usados em MDF-e.
                </div>
                <?php endif; ?>
                
                <?php if ($is_modern): ?>
                <div class="fornc-toolbar">
                    <div class="fornc-search-block">
                        <label for="searchMotorist">Busca rápida</label>
                        <div class="fornc-search-inner">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input type="text" id="searchMotorist" placeholder="Nome, CPF, CNH..." autocomplete="off">
                        </div>
                    </div>
                    <div class="fornc-filters-inline">
                        <div class="fg">
                            <label for="perPageMotorists">Por página</label>
                            <form method="get" action="" id="formPerPageMotorists" style="margin:0;">
                                <input type="hidden" name="page" value="1">
                                <select id="perPageMotorists" name="per_page" class="filter-per-page" title="Registros por página">
                                    <option value="5"  <?php echo $per_page == 5  ? 'selected' : ''; ?>>5</option>
                                    <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                                    <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                                    <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                                </select>
                            </form>
                        </div>
                        <div class="fg">
                            <label for="statusFilter">Status</label>
                            <select id="statusFilter">
                                <option value="">Todos</option>
                                <option value="1">Ativo</option>
                                <option value="2">Férias</option>
                                <option value="3">Licença</option>
                                <option value="4">Inativo</option>
                                <option value="5">Afastado</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label for="categoriaCnhFilter">CNH</label>
                            <select id="categoriaCnhFilter">
                                <option value="">Todas</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label for="tipoContratoFilter">Contrato</label>
                            <select id="tipoContratoFilter">
                                <option value="">Todos</option>
                            </select>
                        </div>
                        <div class="fg fg--check">
                            <label><input type="checkbox" id="filterOnlyFavoritos" value="1"> Só favoritos</label>
                        </div>
                    </div>
                    <div class="fornc-btn-row">
                        <button type="button" id="addMotoristBtn" class="fornc-btn fornc-btn--primary"><i class="fas fa-plus"></i> Novo</button>
                        <button type="button" id="rankingBtn" class="fornc-btn fornc-btn--accent" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border:none;"><i class="fas fa-trophy"></i> Ranking</button>
                        <button type="button" id="gamificacaoBtn" class="fornc-btn fornc-btn--accent" style="background:linear-gradient(135deg,#ff6b35 0%,#f7931e 100%);border:none;"><i class="fas fa-gamepad"></i> Gamificação</button>
                        <button type="button" class="fornc-btn fornc-btn--accent" id="applyMotoristFilters" title="Aplicar filtros"><i class="fas fa-search"></i> Pesquisar</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost view-mode-btn active" data-mode="table" title="Tabela"><i class="fas fa-table"></i></button>
                        <button type="button" class="fornc-btn fornc-btn--ghost view-mode-btn" data-mode="cards" title="Cards"><i class="fas fa-th-large"></i></button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="clearMotoristFilters" title="Limpar filtros"><i class="fas fa-undo"></i></button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="filterBtn" title="Filtros"><i class="fas fa-sliders-h"></i> Opções</button>
                        <button type="button" class="fornc-btn fornc-btn--muted" id="exportBtn" title="Exportar"><i class="fas fa-file-export"></i> Exportar</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>
                <?php else: ?>
                <div class="filter-section">
                    <div class="search-box">
                        <input type="text" id="searchMotorist" placeholder="Buscar motorista...">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="filter-options">
                        <form method="get" action="" id="formPerPageMotorists" style="display:inline-flex; align-items:center; gap:0.5rem;">
                            <span class="filter-label">Por página</span>
                            <input type="hidden" name="page" value="1">
                            <input type="hidden" name="classic" value="1">
                            <select id="perPageMotorists" name="per_page" class="filter-per-page" title="Registros por página" onchange="this.form.submit()">
                                <option value="5"  <?php echo $per_page == 5  ? 'selected' : ''; ?>>5</option>
                                <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </form>
                        <select id="statusFilter">
                            <option value="">Todos os status</option>
                            <option value="1">Ativo</option>
                            <option value="2">Férias</option>
                            <option value="3">Licença</option>
                            <option value="4">Inativo</option>
                            <option value="5">Afastado</option>
                        </select>
                        <select id="categoriaCnhFilter">
                            <option value="">Todas categorias CNH</option>
                        </select>
                        <select id="tipoContratoFilter">
                            <option value="">Todos tipos de contrato</option>
                        </select>
                        <label class="filter-label" style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;">
                            <input type="checkbox" id="filterOnlyFavoritos" value="1"> Só favoritos
                        </label>
                        <span class="filter-label" style="margin-left:8px;">Ver:</span>
                        <button type="button" class="btn-restore-layout view-mode-btn active" data-mode="table" title="Visualização em tabela"><i class="fas fa-table"></i></button>
                        <button type="button" class="btn-restore-layout view-mode-btn" data-mode="cards" title="Visualização em cards"><i class="fas fa-th-large"></i></button>
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
                $motoristas_modern_empty = false;
                ?>
                <!-- Painel de insights: CNH + qualidade de cadastro + resumo IA -->
                <div id="sfMotInsights"></div>

                <?php if (!empty($is_modern)): ?>
                <div class="fornc-empty-state<?php echo $motoristas_modern_empty ? ' visible' : ''; ?>" id="motoristsEmptyState" role="status" aria-live="polite">
                    <p class="fornc-empty-state-title">Nenhum motorista encontrado</p>
                    <p class="fornc-empty-state-hint">Ajuste os filtros ou cadastre um novo motorista.</p>
                    <button type="button" class="fornc-btn fornc-btn--primary" id="motoristsEmptyCreateBtn"><i class="fas fa-plus"></i> Cadastrar primeiro motorista</button>
                </div>
                <?php endif; ?>

                <!-- Motorists List Table -->
                <div class="<?php echo $is_modern ? 'motoristas-table-wrap fornc-table-wrap' : 'data-table-container'; ?>" id="motoristsTableContainer"<?php echo $motoristas_modern_empty ? ' style="display:none"' : ''; ?>>
                    <table class="<?php echo $is_modern ? 'fornc-table' : 'data-table'; ?>" id="motoristsTable">
                        <thead>
                            <tr>
                                <th style="width:50px;"></th>
                                <th class="sortable sorted" data-sort="nome">Nome <span class="sort-ind">▲</span></th>
                                <th class="sortable" data-sort="cpf">CPF <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="cnh">CNH <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="categoria_cnh_nome">Categoria <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="telefone">Telefone <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="email">Email <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="disponibilidade_nome">Status <span class="sort-ind">⇅</span></th>
                                <th class="sortable" data-sort="porcentagem_comissao">Comissão <span class="sort-ind">⇅</span></th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($motoristas)): ?>
                            <tr><td colspan="10" class="text-center text-muted">Nenhum motorista encontrado.</td></tr>
                            <?php else: foreach ($motoristas as $m): echo motorists_render_table_row($m); endforeach; endif; ?>
                        </tbody>
                    </table>
                    <div id="motoristsCardsContainer" class="motorists-cards-grid" style="display:none;"></div>
                </div>
                
                <!-- Pagination -->
                <?php if ($is_modern): ?><div class="fornc-pagination-bar"><?php endif; ?>
                <div class="pagination" id="motoristsPagination" data-per-page="<?php echo (int)$per_page; ?>">
                    <a href="?page=<?php echo max(1, $mnt_cur_page - 1); ?>&per_page=<?php echo (int)$per_page; ?>" class="pagination-btn<?php echo ($mnt_cur_page <= 1) ? ' disabled' : ''; ?>" id="prevPageBtn">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <span class="pagination-info" id="paginationMotoristsInfo">
                        Página <span id="currentPage"><?php echo (int) $mnt_cur_page; ?></span> de <span id="totalPages"><?php echo (int) $mnt_total_pages; ?></span> (<?php echo (int) $mnt_total; ?> registros)
                    </span>
                    <a href="?page=<?php echo min($mnt_total_pages, $mnt_cur_page + 1); ?>&per_page=<?php echo (int)$per_page; ?>" class="pagination-btn<?php echo ($mnt_cur_page >= $mnt_total_pages) ? ' disabled' : ''; ?>" id="nextPageBtn">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                <?php if ($is_modern): ?></div><?php endif; ?>
                
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
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="motoristModal">
        <div class="modal-content<?php echo $is_modern ? ' modal-lg fornc-modal--wide' : ''; ?>">
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
                            <label for="cpf">CPF</label>
                            <input type="text" id="cpf" name="cpf" placeholder="000.000.000-00">
                            <small class="form-text text-muted" style="margin-top: 0.35rem; display: block;">
                                <i class="fas fa-info-circle"></i> O CPF é necessário para emissão de MDF-e e para vincular corretamente o motorista na importação de XML (NF-e/CT-e).
                            </small>
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
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="viewMotoristModal">
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
                    <div id="documentChecklist" class="document-checklist" style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;padding:0.5rem;background:var(--bg-tertiary);border-radius:6px;">
                        <span id="checkCnh"><i class="fas fa-id-card"></i> CNH: <strong>—</strong></span>
                        <span id="checkContrato"><i class="fas fa-file-contract"></i> Contrato: <strong>—</strong></span>
                        <span id="checkFoto"><i class="fas fa-user"></i> Foto: <strong>—</strong></span>
                    </div>
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
                        <div class="metric-card">
                            <div class="metric-icon">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                            <div class="metric-info">
                                <h4>Total de multas</h4>
                                <p id="view-total-multas">0</p>
                                <a href="#" id="viewLinkMultas" target="_blank" class="btn-sm btn-secondary" style="margin-top:4px;">Ver multas</a>
                                <a href="multas.php" target="_blank" class="btn-sm btn-secondary" style="margin-top:4px;margin-left:4px;">Consultar DETRAN</a>
                            </div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-icon">
                                <i class="fas fa-wrench"></i>
                            </div>
                            <div class="metric-info">
                                <h4>Manutenções (veículos que dirigiu)</h4>
                                <p id="view-total-manutencoes">0</p>
                                <a href="manutencoes.php" target="_blank" class="btn-sm btn-secondary" style="margin-top:4px;">Ver manutenções</a>
                            </div>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-primary" id="editFromViewBtn" onclick="editarMotoristaDoModalVisualizacao()" style="margin-right: 8px;">
                    <i class="fas fa-edit"></i> Editar
                </button>
                <button class="btn-secondary" id="historyFromViewBtn" onclick="abrirHistoricoDoModalVisualizacao()" style="margin-right: 8px;">
                    <i class="fas fa-history"></i> Histórico
                </button>
                <button class="btn-secondary" id="shareMotoristBtn" onclick="compartilharMotorista()" style="margin-right: 8px;" title="Copiar link para visualização">
                    <i class="fas fa-share-alt"></i> Compartilhar
                </button>
                <button class="btn-secondary" onclick="closeModal('viewMotoristModal')">Fechar</button>
            </div>
        </div>
    </div>
    
    <!-- Modal Histórico de Alterações -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="motoristLogModal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h2>Histórico de Alterações</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <p class="text-muted" id="motoristLogName"></p>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Ação</th>
                                <th>Alterado por</th>
                                <th>Descrição</th>
                                <th>Detalhes</th>
                            </tr>
                        </thead>
                        <tbody id="motoristLogTbody">
                        </tbody>
                    </table>
                </div>
                <p id="motoristLogEmpty" class="text-muted" style="display:none;">Nenhuma alteração registrada.</p>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('motoristLogModal')">Fechar</button>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="deleteMotoristModal">
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
    
    <!-- Modal de Gamificação -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="gamificacaoModal">
        <div class="modal-content modal-xl">
            <div class="modal-header">
                <h2><i class="fas fa-gamepad me-2"></i>Sistema de Gamificação</h2>
                <span class="close-modal" onclick="fecharModalGamificacao()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="gamificacaoContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="fecharModalGamificacao()">Fechar</button>
            </div>
        </div>
    </div>

    <!-- Modal de Ranking -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="rankingModal">
        <div class="modal-content modal-xl">
            <div class="modal-header">
                <h2><i class="fas fa-trophy me-2"></i>Sistema de Ranking</h2>
                <span class="close-modal" onclick="fecharModalRanking()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="rankingContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="fecharModalRanking()">Fechar</button>
            </div>
        </div>
    </div>
    <?php $sf_help_key = 'motorists'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>

    
    <!-- JavaScript Files -->
    <?php sf_render_api_scripts(); ?>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>window.__MOTORISTS_INITIAL__=<?php echo json_encode($mnt_initial, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
    <script src="../js/motorists.js?v=1.3"></script>
    <script src="../js/sf_insights.js?v=1.3"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.SFInsights) {
            SFInsights.init({
                mountId: 'sfMotInsights',
                insightsUrl: '../api/motorist_insights.php',
                resumoUrl: '../api/motorist_resumo_ia.php',
                title: 'Insights de motoristas',
                sectionTitle: 'Pendências de cadastro',
                emptyOk: 'Nenhuma pendência de CNH ou cadastro encontrada.',
                stats: function (j) {
                    var c = j.cnh || {};
                    return [
                        { label: 'motoristas', value: j.total_motoristas || 0, tone: 'ok' },
                        { label: 'CNH vencida', value: c.vencidas || 0, tone: (c.vencidas > 0 ? 'erro' : 'ok') },
                        { label: 'vence ≤30 dias', value: c.vence_30 || 0, tone: (c.vence_30 > 0 ? 'aviso' : 'ok') },
                        { label: 'vence ≤60 dias', value: c.vence_60 || 0, tone: (c.vence_60 > 0 ? 'aviso' : 'ok') }
                    ];
                },
                onEdit: function (id) {
                    // loadMotoristForEdit já abre o modal (openModal) ao terminar de carregar.
                    if (typeof loadMotoristForEdit === 'function') loadMotoristForEdit(id);
                }
            });
        }
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

    <script>
    function mntEsc(v) {
        return (typeof escapeHtml === 'function') ? escapeHtml(v) : String(v ?? '');
    }

    document.addEventListener('DOMContentLoaded', function() {
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

        // Event listeners para os novos botões
        document.getElementById('rankingBtn').addEventListener('click', function() {
            abrirModalRanking();
        });

        document.getElementById('gamificacaoBtn').addEventListener('click', function() {
            abrirModalGamificacao();
        });

        // Event listeners para fechar modais ao clicar fora
        document.getElementById('gamificacaoModal').addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModalGamificacao();
            }
        });

        document.getElementById('rankingModal').addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModalRanking();
            }
        });
        // Escape: js/modal_a11y.js fecha .modal e restaura scroll do body
    });

    // Funções para abrir modais de gamificação e ranking
    function abrirModalGamificacao() {
        const modal = document.getElementById('gamificacaoModal');
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden'; // Previne scroll da página
        carregarConteudoGamificacao();
    }

    function abrirModalRanking() {
        const modal = document.getElementById('rankingModal');
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden'; // Previne scroll da página
        carregarConteudoRanking();
    }

    function fecharModalGamificacao() {
        const modal = document.getElementById('gamificacaoModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto'; // Restaura scroll da página
    }

    function fecharModalRanking() {
        const modal = document.getElementById('rankingModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto'; // Restaura scroll da página
    }

    function carregarConteudoGamificacao() {
        const content = document.getElementById('gamificacaoContent');
        
        // Primeiro, tentar carregar dados reais de gamificação
        fetch('../api/gamificacao_motoristas.php?action=get_leaderboard&empresa_id=<?php echo $_SESSION['empresa_id']; ?>')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.leaderboard && data.data.leaderboard.length > 0) {
                    // Usar dados reais de gamificação
                    content.innerHTML = gerarConteudoGamificacao(data.data);
                } else {
                    // Se não há dados reais, mostrar mensagem de "sem dados"
                    console.log('Nenhum dado de gamificação encontrado.');
                    content.innerHTML = gerarConteudoGamificacaoVazio();
                }
            })
            .catch(error => {
                console.error('Erro ao carregar dados de gamificação:', error);
                // Mostrar mensagem de erro
                content.innerHTML = gerarConteudoGamificacaoVazio();
            });
    }

    function calcularGamificacaoReal() {
        const content = document.getElementById('gamificacaoContent');
        
        // Mostrar loading
        content.innerHTML = `
            <div class="text-center p-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Calculando...</span>
                </div>
                <p class="mt-2">Calculando dados de gamificação...</p>
            </div>
        `;
        
        // Timeout de 5 segundos para evitar loading infinito
        const timeout = setTimeout(() => {
            console.log('Timeout no cálculo de gamificação, usando simulação');
            carregarDadosSimuladosGamificacao();
        }, 5000);
        
        // Executar cálculo de gamificação
        fetch('../api/gamificacao_motoristas.php?action=calcular_gamificacao', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            clearTimeout(timeout);
            if (data.success) {
                // Após calcular, carregar os dados reais
                carregarConteudoGamificacao();
            } else {
                // Se cálculo falhou, usar simulação
                console.log('Cálculo de gamificação falhou:', data.error);
                carregarDadosSimuladosGamificacao();
            }
        })
        .catch(error => {
            clearTimeout(timeout);
            console.error('Erro ao calcular gamificação:', error);
            carregarDadosSimuladosGamificacao();
        });
    }

    function carregarDadosSimuladosGamificacao() {
        const content = document.getElementById('gamificacaoContent');
        
        // Fallback para simulação baseada em motoristas reais
        fetch('../api/motoristas.php?action=get_all')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    const gamificacaoData = simularDadosGamificacao(data.data);
                    content.innerHTML = gerarConteudoGamificacao(gamificacaoData);
                } else {
                    content.innerHTML = gerarConteudoGamificacaoEstatico();
                }
            })
            .catch(error => {
                console.error('Erro ao carregar motoristas:', error);
                content.innerHTML = gerarConteudoGamificacaoEstatico();
            });
    }

    function carregarConteudoRanking(filtros) {
        const content = document.getElementById('rankingContent');

        // Filtros opcionais encaminhados à API (período → datas reais que a API entende)
        let qs = '';
        if (filtros && filtros.periodo) {
            const fim = new Date();
            const inicio = new Date();
            switch (filtros.periodo) {
                case 'semanal': inicio.setDate(inicio.getDate() - 7); break;
                case 'trimestral': inicio.setMonth(inicio.getMonth() - 3); break;
                case 'anual': inicio.setFullYear(inicio.getFullYear() - 1); break;
                case 'mensal': default: inicio.setDate(1); break;
            }
            const fmt = (d) => d.toISOString().slice(0, 10);
            qs = '&periodo_inicio=' + fmt(inicio) + '&periodo_fim=' + fmt(fim);
        }

        // Primeiro, tentar carregar dados reais de ranking
        fetch('../api/ranking_motoristas.php?action=get_ranking&empresa_id=<?php echo $_SESSION['empresa_id']; ?>' + qs)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.ranking && data.data.ranking.length > 0) {
                    // Usar dados reais de ranking
                    content.innerHTML = gerarConteudoRanking(data.data);
                } else {
                    // Se não há dados reais, mostrar mensagem de "sem dados"
                    console.log('Nenhum dado de ranking encontrado.');
                    content.innerHTML = gerarConteudoRankingVazio();
                }
            })
            .catch(error => {
                console.error('Erro ao carregar dados de ranking:', error);
                // Mostrar mensagem de erro
                content.innerHTML = gerarConteudoRankingVazio();
            });
    }

    function calcularRankingReal() {
        const content = document.getElementById('rankingContent');
        
        // Mostrar loading
        content.innerHTML = `
            <div class="text-center p-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Calculando...</span>
                </div>
                <p class="mt-2">Calculando dados de ranking...</p>
            </div>
        `;
        
        // Timeout de 5 segundos para evitar loading infinito
        const timeout = setTimeout(() => {
            console.log('Timeout no cálculo de ranking, usando simulação');
            carregarDadosSimuladosRanking();
        }, 5000);
        
        // Executar cálculo de ranking
        fetch('../api/ranking_motoristas.php?action=calcular_ranking', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            clearTimeout(timeout);
            if (data.success) {
                // Após calcular, carregar os dados reais
                carregarConteudoRanking();
            } else {
                // Se cálculo falhou, usar simulação
                console.log('Cálculo de ranking falhou:', data.error);
                carregarDadosSimuladosRanking();
            }
        })
        .catch(error => {
            clearTimeout(timeout);
            console.error('Erro ao calcular ranking:', error);
            carregarDadosSimuladosRanking();
        });
    }

    function carregarDadosSimuladosRanking() {
        const content = document.getElementById('rankingContent');
        
        // Fallback para simulação baseada em motoristas reais
        fetch('../api/motoristas.php?action=get_all')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    const rankingData = simularDadosRanking(data.data);
                    content.innerHTML = gerarConteudoRanking(rankingData);
                } else {
                    content.innerHTML = gerarConteudoRankingEstatico();
                }
            })
            .catch(error => {
                console.error('Erro ao carregar motoristas:', error);
                content.innerHTML = gerarConteudoRankingEstatico();
            });
    }

    function gerarConteudoGamificacao(data) {
        const leaderboard = data.leaderboard || [];
        const estatisticas = data.estatisticas || {};
        
        return `
            <!-- Header -->
            <div class="gamification-header mb-4">
                <div>
                    <h3 class="mb-1 text-primary"><i class="fas fa-gamepad me-2"></i>Sistema de Gamificação</h3>
                    <p class="mb-0 text-muted">Ranking de performance dos motoristas em tempo real</p>
                </div>
            </div>

            <!-- Conteúdo principal -->
            <div class="row g-4">
                <!-- Sistema de Níveis -->
                <div class="col-lg-6">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-gradient-primary text-white border-0">
                            <h5 class="mb-0"><i class="fas fa-star me-2"></i>Sistema de Níveis</h5>
                        </div>
                        <div class="card-body p-3">
                            <div class="levels-container">
                                <div class="level-card bronze">
                                    <div class="level-icon">🥉</div>
                                    <div class="level-name">Bronze</div>
                                    <div class="level-points">0-99 pts</div>
                                </div>
                                <div class="level-card silver">
                                    <div class="level-icon">🥈</div>
                                    <div class="level-name">Prata</div>
                                    <div class="level-points">100-299 pts</div>
                                </div>
                                <div class="level-card gold">
                                    <div class="level-icon">🥇</div>
                                    <div class="level-name">Ouro</div>
                                    <div class="level-points">300-599 pts</div>
                                </div>
                                <div class="level-card platinum">
                                    <div class="level-icon">🏆</div>
                                    <div class="level-name">Platina</div>
                                    <div class="level-points">600-899 pts</div>
                                </div>
                                <div class="level-card diamond">
                                    <div class="level-icon">💎</div>
                                    <div class="level-name">Diamante</div>
                                    <div class="level-points">900-999 pts</div>
                                </div>
                                <div class="level-card legend">
                                    <div class="level-icon">👑</div>
                                    <div class="level-name">Lenda</div>
                                    <div class="level-points">1000+ pts</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sistema de Badges -->
                <div class="col-lg-6">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-gradient-success text-white border-0">
                            <h5 class="mb-0"><i class="fas fa-medal me-2"></i>Sistema de Badges</h5>
                        </div>
                        <div class="card-body p-3">
                            <div class="badges-container">
                                <div class="badge-card">
                                    <div class="badge-icon">⛽</div>
                                    <div class="badge-name">Motorista Econômico</div>
                                    <div class="badge-desc">3 meses com consumo abaixo da média</div>
                                </div>
                                <div class="badge-card">
                                    <div class="badge-icon">🚫</div>
                                    <div class="badge-name">Sem Multas</div>
                                    <div class="badge-desc">1 ano sem infrações</div>
                                </div>
                                <div class="badge-card">
                                    <div class="badge-icon">✅</div>
                                    <div class="badge-name">Checklists Perfeitos</div>
                                    <div class="badge-desc">50 checklists sem falhas</div>
                                </div>
                                <div class="badge-card">
                                    <div class="badge-icon">🔥</div>
                                    <div class="badge-name">Streak de Fogo</div>
                                    <div class="badge-desc">7 dias consecutivos de excelência</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leaderboard -->
            <div class="row mt-4">
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-gradient-success text-white border-0">
                            <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Leaderboard Atual</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="leaderboard-container">
                                ${leaderboard.length > 0 ? 
                                    leaderboard.slice(0, 15).map((motorista, index) => `
                                        <div class="leaderboard-item ${index < 3 ? 'top-three' : ''}">
                                            <div class="position">
                                                <span class="position-badge ${index < 3 ? ['gold', 'silver', 'bronze'][index] : ''}">
                                                    ${index < 3 ? ['🥇', '🥈', '🥉'][index] : (index + 1) + 'º'}
                                                </span>
                                            </div>
                                            <div class="driver-info">
                                                <div class="driver-name">${mntEsc(motorista.motorista_nome) || 'N/A'}</div>
                                                <div class="driver-stats">
                                                    <span class="points"><i class="fas fa-star"></i> ${motorista.pontos_totais || 0} pontos</span>
                                                    <span class="routes"><i class="fas fa-route"></i> ${motorista.total_rotas_reais || motorista.rotas_concluidas || 0} rotas</span>
                                                </div>
                                                <div class="driver-progress">
                                                    ${generateProgressBar(motorista.pontos_totais || 0, motorista.nivel_calculado || 'Bronze')}
                                                </div>
                                            </div>
                                            <div class="level-badge">
                                                <span class="badge ${getNivelClass(motorista.nivel_calculado)}">
                                                    ${getNivelEmoji(motorista.nivel_calculado)} ${mntEsc(motorista.nivel_calculado)}
                                                </span>
                                            </div>
                                        </div>
                                    `).join('') :
                                    '<div class="no-data"><i class="fas fa-users fa-4x mb-3"></i><h5>Nenhum dado disponível</h5><p>Configure o sistema para ver os dados dos motoristas</p></div>'
                                }
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estatísticas Gerais -->
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-gradient-info text-white border-0">
                            <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Estatísticas Gerais</h5>
                        </div>
                        <div class="card-body p-3">
                            <div class="stats-container">
                                <div class="stat-card">
                                    <div class="stat-icon bg-primary">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number">${estatisticas.total_motoristas || leaderboard.length || 0}</div>
                                        <div class="stat-label">Total de Motoristas</div>
                                    </div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-icon bg-success">
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number">${Math.round(estatisticas.media_pontos || 0)}</div>
                                        <div class="stat-label">Pontos Médios</div>
                                    </div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-icon bg-warning">
                                        <i class="fas fa-trophy"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number">${estatisticas.max_pontos || 0}</div>
                                        <div class="stat-label">Pontos Máximos</div>
                                    </div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-icon bg-info">
                                        <i class="fas fa-medal"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number">${estatisticas.total_desafios_completos || 0}</div>
                                        <div class="stat-label">Desafios Completos</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        `;
    }

    function gerarConteudoRanking(data) {
        const ranking = data.ranking || [];
        const estatisticas = data.estatisticas || {};
        
        return `
            <!-- Header -->
            <div class="ranking-header mb-4">
                <div>
                    <h3 class="mb-1 text-primary"><i class="fas fa-trophy me-2"></i>Sistema de Ranking</h3>
                    <p class="mb-0 text-muted">Avaliação completa dos motoristas baseada em múltiplas métricas</p>
                </div>
            </div>

            <!-- Conteúdo principal -->
            <div class="row g-4">
                <!-- Ranking de Performance -->
                <div class="col-12">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-gradient-primary text-white border-0">
                            <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Ranking de Performance</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="ranking-container">
                                ${ranking.length > 0 ? 
                                    ranking.slice(0, 25).map((motorista, index) => `
                                        <div class="ranking-item ${index < 3 ? 'top-three' : ''}">
                                            <div class="position">
                                                <span class="position-badge ${index < 3 ? ['gold', 'silver', 'bronze'][index] : ''}">
                                                    ${index < 3 ? ['🥇', '🥈', '🥉'][index] : (index + 1) + 'º'}
                                                </span>
                                            </div>
                                            <div class="driver-info">
                                                <div class="driver-name">${mntEsc(motorista.motorista_nome) || 'N/A'}</div>
                                                <div class="driver-id">ID: ${motorista.motorista_id || 'N/A'}</div>
                                            </div>
                                            <div class="score-section">
                                                <div class="score-bar">
                                                    <div class="score-fill" style="width: ${motorista.nota_total || 0}%"></div>
                                                    <span class="score-text">${motorista.nota_total || 0}/100</span>
                                                </div>
                                            </div>
                                            <div class="status-section">
                                                <span class="status-badge ${getStatusClass(motorista.status || 'Ativo')}">
                                                    ${mntEsc(motorista.status) || 'Ativo'}
                                                </span>
                                                <button class="btn btn-sm btn-outline-primary" onclick="verDetalhesMotorista(${motorista.motorista_id})" title="Ver Detalhes">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    `).join('') :
                                    '<div class="no-data"><i class="fas fa-users fa-4x mb-3"></i><h5>Nenhum dado disponível</h5><p>Configure o sistema para ver os dados dos motoristas</p></div>'
                                }
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Distribuição por Faixas e Critérios de Avaliação -->
            <div class="row mt-4">
                <!-- Distribuição por Faixas -->
                <div class="col-lg-6">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-gradient-success text-white border-0">
                            <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Distribuição por Faixas</h5>
                        </div>
                        <div class="card-body p-3">
                            <div class="distribution-grid">
                                <div class="distribution-item">
                                    <div class="distribution-header">
                                        <i class="fas fa-star text-success"></i>
                                        <span>Excelente</span>
                                        <span class="badge bg-success">${data.excelente || 0}</span>
                                    </div>
                                    <div class="distribution-bar">
                                        <div class="bar-fill bg-success" style="width: ${((data.excelente || 0) / Math.max(estatisticas.total_motoristas || ranking.length || 1, 1)) * 100}%"></div>
                                    </div>
                                    <small class="text-muted">90-100 pontos</small>
                                </div>

                                <div class="distribution-item">
                                    <div class="distribution-header">
                                        <i class="fas fa-thumbs-up text-primary"></i>
                                        <span>Bom</span>
                                        <span class="badge bg-primary">${data.bom || 0}</span>
                                    </div>
                                    <div class="distribution-bar">
                                        <div class="bar-fill bg-primary" style="width: ${((data.bom || 0) / Math.max(estatisticas.total_motoristas || ranking.length || 1, 1)) * 100}%"></div>
                                    </div>
                                    <small class="text-muted">70-89 pontos</small>
                                </div>

                                <div class="distribution-item">
                                    <div class="distribution-header">
                                        <i class="fas fa-exclamation text-warning"></i>
                                        <span>Regular</span>
                                        <span class="badge bg-warning">${data.regular || 0}</span>
                                    </div>
                                    <div class="distribution-bar">
                                        <div class="bar-fill bg-warning" style="width: ${((data.regular || 0) / Math.max(estatisticas.total_motoristas || ranking.length || 1, 1)) * 100}%"></div>
                                    </div>
                                    <small class="text-muted">50-69 pontos</small>
                                </div>

                                <div class="distribution-item">
                                    <div class="distribution-header">
                                        <i class="fas fa-exclamation-triangle text-danger"></i>
                                        <span>Necessita Melhoria</span>
                                        <span class="badge bg-danger">${data.necessita_melhoria || 0}</span>
                                    </div>
                                    <div class="distribution-bar">
                                        <div class="bar-fill bg-danger" style="width: ${((data.necessita_melhoria || 0) / Math.max(estatisticas.total_motoristas || ranking.length || 1, 1)) * 100}%"></div>
                                    </div>
                                    <small class="text-muted">< 50 pontos</small>
                                </div>
                            </div>

                            <hr class="my-3">
                            <div class="total-summary">
                                <div class="total-number">${estatisticas.total_motoristas || ranking.length || 0}</div>
                                <div class="total-label">Total de Motoristas</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Critérios de Avaliação Básicos -->
                <div class="col-lg-6">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-gradient-warning text-white border-0">
                            <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Critérios de Avaliação Básicos</h5>
                        </div>
                        <div class="card-body p-3">
                            <div class="criteria-container">
                                <div class="criteria-card">
                                    <div class="criteria-icon bg-primary">
                                        <i class="fas fa-gas-pump"></i>
                                    </div>
                                    <div class="criteria-content">
                                        <h6>Consumo de Combustível</h6>
                                        <div class="criteria-percentage">30%</div>
                                        <p class="small text-muted">Eficiência no uso de combustível</p>
                                    </div>
                                </div>
                                <div class="criteria-card">
                                    <div class="criteria-icon bg-success">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="criteria-content">
                                        <h6>Pontualidade</h6>
                                        <div class="criteria-percentage">25%</div>
                                        <p class="small text-muted">Cumprimento de prazos</p>
                                    </div>
                                </div>
                                <div class="criteria-card">
                                    <div class="criteria-icon bg-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                    <div class="criteria-content">
                                        <h6>Multas</h6>
                                        <div class="criteria-percentage">20%</div>
                                        <p class="small text-muted">Histórico de infrações</p>
                                    </div>
                                </div>
                                <div class="criteria-card">
                                    <div class="criteria-icon bg-info">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="criteria-content">
                                        <h6>Checklists</h6>
                                        <div class="criteria-percentage">15%</div>
                                        <p class="small text-muted">Qualidade dos checklists</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros Dinâmicos e Métricas Avançadas -->
            <div class="row mt-4">
                <!-- Filtros Dinâmicos -->
                <div class="col-lg-6">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-gradient-secondary text-white border-0">
                            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros Dinâmicos</h5>
                        </div>
                        <div class="card-body p-3">
                            <div class="filters-container">
                                <div class="filter-item">
                                    <label class="form-label">Período</label>
                                    <select class="form-select" id="filtroPeriodo">
                                        <option value="semanal">Semanal</option>
                                        <option value="mensal" selected>Mensal</option>
                                        <option value="trimestral">Trimestral</option>
                                        <option value="anual">Anual</option>
                                    </select>
                                </div>
                                <div class="filter-item">
                                    <label class="form-label">Tipo de Veículo</label>
                                    <select class="form-select" id="filtroVeiculo">
                                        <option value="todos">Todos os Veículos</option>
                                        <option value="caminhao">Caminhões</option>
                                        <option value="van">Vans</option>
                                        <option value="carreta">Carretas</option>
                                    </select>
                                </div>
                                <div class="filter-item">
                                    <label class="form-label">Tipo de Rota</label>
                                    <select class="form-select" id="filtroRota">
                                        <option value="todas">Todas as Rotas</option>
                                        <option value="urbana">Urbana</option>
                                        <option value="interurbana">Interurbana</option>
                                        <option value="longa_distancia">Longa Distância</option>
                                    </select>
                                </div>
                                <div class="filter-item">
                                    <label class="form-label">Ação</label>
                                    <button class="btn btn-primary w-100" onclick="aplicarFiltros()">
                                        <i class="fas fa-search me-2"></i>Aplicar Filtros
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Métricas Avançadas -->
                <div class="col-lg-6">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-gradient-info text-white border-0">
                            <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Métricas Avançadas</h5>
                        </div>
                        <div class="card-body p-3">
                            <div class="metrics-container">
                                <div class="metric-card">
                                    <div class="metric-icon bg-danger">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                    <div class="metric-content">
                                        <h6>Ocorrências/Sinistros</h6>
                                        <p>15% do total</p>
                                        <small>Impacta diretamente a segurança</small>
                                    </div>
                                </div>
                                <div class="metric-card">
                                    <div class="metric-icon bg-success">
                                        <i class="fas fa-dollar-sign"></i>
                                    </div>
                                    <div class="metric-content">
                                        <h6>Custos por KM</h6>
                                        <p>10% do total</p>
                                        <small>Visão financeira do desempenho</small>
                                    </div>
                                </div>
                                <div class="metric-card">
                                    <div class="metric-icon bg-warning">
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <div class="metric-content">
                                        <h6>Feedback do Cliente</h6>
                                        <p>10% do total</p>
                                        <small>Entrega dentro do padrão esperado</small>
                                    </div>
                                </div>
                                <div class="metric-card">
                                    <div class="metric-icon bg-info">
                                        <i class="fas fa-wrench"></i>
                                    </div>
                                    <div class="metric-content">
                                        <h6>Manutenção Preventiva</h6>
                                        <p>5% do total</p>
                                        <small>Ajuda a manter o veículo em ordem</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        `;
    }

    function gerarConteudoGamificacaoVazio() {
        return `
            <div class="row">
                <div class="col-md-12">
                    <div class="alert alert-warning">
                        <h4><i class="fas fa-exclamation-triangle me-2"></i>Sistema de Gamificação</h4>
                        <p class="mb-3">Nenhum dado de gamificação disponível. Configure o sistema para começar a acompanhar o desempenho dos motoristas.</p>
                        <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                            <button class="btn btn-primary" onclick="window.open('../pages/configuracoes.php', '_blank')">
                                <i class="fas fa-cog me-2"></i>Configurar Sistema
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function gerarConteudoGamificacaoEstatico() {
        return gerarConteudoGamificacaoVazio();
    }

    function gerarConteudoRankingVazio() {
        return `
            <div class="row">
                <div class="col-md-12">
                    <div class="alert alert-warning">
                        <h4><i class="fas fa-exclamation-triangle me-2"></i>Sistema de Ranking</h4>
                        <p class="mb-3">Nenhum dado de ranking disponível. Configure o sistema para começar a acompanhar o desempenho dos motoristas.</p>
                        <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                            <button class="btn btn-primary" onclick="window.open('../pages/configuracoes.php', '_blank')">
                                <i class="fas fa-cog me-2"></i>Configurar Sistema
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function gerarConteudoRankingEstatico() {
        return gerarConteudoRankingVazio();
    }

    function getNivelCor(nivel) {
        switch(nivel) {
            case 'Diamante': return 'primary';
            case 'Ouro': return 'warning';
            case 'Prata': return 'secondary';
            case 'Bronze': return 'dark';
            default: return 'light';
        }
    }

    function getNivelClass(nivel) {
        switch(nivel) {
            case 'Lenda': return 'bg-warning';
            case 'Diamante': return 'bg-primary';
            case 'Platina': return 'bg-secondary';
            case 'Ouro': return 'bg-warning';
            case 'Prata': return 'bg-secondary';
            case 'Bronze': return 'bg-dark';
            default: return 'bg-light text-dark';
        }
    }

    function getNivelEmoji(nivel) {
        switch(nivel) {
            case 'Lenda': return '👑';
            case 'Diamante': return '💎';
            case 'Platina': return '🏆';
            case 'Ouro': return '🥇';
            case 'Prata': return '🥈';
            case 'Bronze': return '🥉';
            default: return '🥉';
        }
    }
    
    function generateProgressBar(pontos, nivel) {
        const niveis = {
            'Bronze': { min: 0, max: 99 },
            'Prata': { min: 100, max: 299 },
            'Ouro': { min: 300, max: 599 },
            'Platina': { min: 600, max: 899 },
            'Diamante': { min: 900, max: 999 },
            'Lenda': { min: 1000, max: 9999 }
        };
        
        const currentLevel = niveis[nivel] || niveis['Bronze'];
        const nextLevel = getNextLevel(nivel);
        const nextLevelData = niveis[nextLevel];
        
        if (!nextLevelData) {
            // Nível máximo
            return `
                <div class="level-progress-container">
                    <div class="progress level-progress-bar">
                        <div class="progress-bar bg-danger" style="width: 100%">
                            <span class="progress-text">100%</span>
                        </div>
                    </div>
                    <div class="level-progress-footer">
                        <small class="text-success">🏆 Nível máximo alcançado!</small>
                    </div>
                </div>
            `;
        }
        
        const pointsInCurrentLevel = pontos - currentLevel.min;
        const pointsNeededForNext = nextLevelData.min - currentLevel.min;
        const progress = Math.min(100, (pointsInCurrentLevel / pointsNeededForNext) * 100);
        
        const progressColor = getProgressColor(nivel);
        const nextIcon = getNivelEmoji(nextLevel);
        
        return `
            <div class="level-progress-container">
                <div class="progress level-progress-bar">
                    <div class="progress-bar ${progressColor}" style="width: ${progress}%">
                        <span class="progress-text">${Math.round(progress)}%</span>
                    </div>
                </div>
                <div class="level-progress-footer">
                    <small class="text-muted">Próximo: ${nextIcon} ${nextLevel} (${nextLevelData.min} pts)</small>
                </div>
            </div>
        `;
    }
    
    function getNextLevel(currentLevel) {
        const levelOrder = ['Bronze', 'Prata', 'Ouro', 'Platina', 'Diamante', 'Lenda'];
        const currentIndex = levelOrder.indexOf(currentLevel);
        return currentIndex >= 0 && currentIndex < levelOrder.length - 1 ? levelOrder[currentIndex + 1] : null;
    }
    
    function getProgressColor(nivel) {
        const colors = {
            'Bronze': 'bg-warning',
            'Prata': 'bg-secondary',
            'Ouro': 'bg-warning',
            'Platina': 'bg-info',
            'Diamante': 'bg-primary',
            'Lenda': 'bg-danger'
        };
        return colors[nivel] || 'bg-secondary';
    }

    function getStatusCor(status) {
        switch(status) {
            case 'Ativo': return 'success';
            case 'Férias': return 'warning';
            case 'Licença': return 'info';
            case 'Inativo': return 'danger';
            default: return 'light';
        }
    }

    function getStatusClass(status) {
        switch(status) {
            case 'Ativo': return 'bg-success';
            case 'Férias': return 'bg-warning';
            case 'Licença': return 'bg-info';
            case 'Inativo': return 'bg-danger';
            default: return 'bg-light text-dark';
        }
    }

    function calcularGamificacao() {
        // Usar API real de gamificação
        fetch('../api/gamificacao_motoristas.php?action=calcular_gamificacao', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message || 'Gamificação calculada com sucesso.', 'success');
                carregarConteudoGamificacao(); // Recarregar dados
            } else {
                showNotification(data.error || 'Falha ao calcular gamificação', 'error');
            }
        })
        .catch(() => {
            showNotification('Erro ao calcular gamificação', 'error');
        });
    }

    function calcularRanking() {
        // Usar API real de ranking
        fetch('../api/ranking_motoristas.php?action=calcular_ranking', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message || 'Ranking calculado com sucesso.', 'success');
                carregarConteudoRanking(); // Recarregar dados
            } else {
                showNotification(data.error || 'Falha ao calcular ranking', 'error');
            }
        })
        .catch(() => {
            showNotification('Erro ao calcular ranking', 'error');
        });
    }

    function verDetalhesMotorista(motoristaId) {
        // Buscar dados do motorista
        fetch(`../api/ranking_motoristas.php?action=get_detalhes&motorista_id=${motoristaId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.motorista) {
                    mostrarDetalhesMotorista(data.data.motorista, data.data.historico, data.data.gamificacao);
                } else {
                    showNotification('Erro ao carregar detalhes do motorista', 'error');
                }
            })
            .catch(() => {
                showNotification('Erro ao carregar detalhes do motorista', 'error');
            });
    }
    
    function mostrarDetalhesMotorista(motorista, historico = [], gamificacao = null) {
        // Criar modal de detalhes
        const modalHtml = `
            <div class="modal fade" id="detalhesMotoristaModal" tabindex="-1" aria-labelledby="detalhesMotoristaModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="detalhesMotoristaModalLabel">
                                <i class="fas fa-user me-2"></i>Detalhes do Motorista
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Informações Pessoais</h6>
                                    <p><strong>Nome:</strong> ${mntEsc(motorista.nome) || 'N/A'}</p>
                                    <p><strong>ID:</strong> ${motorista.id || 'N/A'}</p>
                                    <p><strong>CPF:</strong> ${motorista.cpf || 'N/A'}</p>
                                    <p><strong>CNH:</strong> ${motorista.cnh || 'N/A'}</p>
                                    <p><strong>Telefone:</strong> ${motorista.telefone || 'N/A'}</p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Performance no Ranking</h6>
                                    <p><strong>Posição:</strong> ${motorista.posicao_ranking || 'N/A'}</p>
                                    <p><strong>Nota Total:</strong> ${motorista.nota_total || '0'}/100</p>
                                    <p><strong>Classificação:</strong> ${mntEsc(motorista.classificacao) || 'N/A'}</p>
                                    <p><strong>Total de Rotas:</strong> ${motorista.total_rotas || '0'}</p>
                                    <p><strong>Rotas Pontuais:</strong> ${motorista.rotas_pontuais || '0'}</p>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Métricas de Desempenho</h6>
                                    <div class="metric-item mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Consumo de Combustível:</span>
                                            <span class="fw-bold">${motorista.nota_consumo || '0.00'}/100</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-success" style="width: ${motorista.nota_consumo || 0}%"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="metric-item mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Pontualidade:</span>
                                            <span class="fw-bold">${motorista.nota_pontualidade || '0.00'}/100</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-primary" style="width: ${motorista.nota_pontualidade || 0}%"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="metric-item mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Multas:</span>
                                            <span class="fw-bold">${motorista.nota_multas || '0.00'}/100</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-warning" style="width: ${motorista.nota_multas || 0}%"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Estatísticas Adicionais</h6>
                                    <div class="metric-item mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Ocorrências:</span>
                                            <span class="fw-bold">${motorista.nota_ocorrencias || '0.00'}/100</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-info" style="width: ${motorista.nota_ocorrencias || 0}%"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="metric-item mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Checklist:</span>
                                            <span class="fw-bold">${motorista.nota_checklist || '0.00'}/100</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-secondary" style="width: ${motorista.nota_checklist || 0}%"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="metric-item mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Eficiência:</span>
                                            <span class="fw-bold">${motorista.nota_eficiencia || '0.00'}/100</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-dark" style="width: ${motorista.nota_eficiencia || 0}%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            ${historico && historico.length > 0 ? `
                                <hr class="my-4">
                                <h6 class="text-primary mb-3">Histórico de Performance</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Período</th>
                                                <th>Nota Total</th>
                                                <th>Posição</th>
                                                <th>Rotas</th>
                                                <th>Data</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${historico.map(h => `
                                                <tr>
                                                    <td>${h.periodo_inicio} a ${h.periodo_fim}</td>
                                                    <td>${h.nota_total}</td>
                                                    <td>${h.posicao_ranking}</td>
                                                    <td>${h.total_rotas}</td>
                                                    <td>${new Date(h.data_atualizacao).toLocaleDateString('pt-BR')}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            ` : ''}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remover modal existente se houver
        const existingModal = document.getElementById('detalhesMotoristaModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Adicionar modal ao body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Mostrar modal
        const modalElement = document.getElementById('detalhesMotoristaModal');
        
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            // Usar Bootstrap 5
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        } else if (typeof $ !== 'undefined' && $.fn.modal) {
            // Fallback para jQuery modal
            $(modalElement).modal('show');
        } else {
            // Fallback para JavaScript puro
            modalElement.style.display = 'block';
            modalElement.classList.add('show');
            document.body.classList.add('modal-open');
            
            // Adicionar backdrop
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.id = 'modalBackdrop';
            document.body.appendChild(backdrop);
        }
        
        // Função para fechar modal
        function fecharModal() {
            const modalElement = document.getElementById('detalhesMotoristaModal');
            const backdrop = document.getElementById('modalBackdrop');
            
            if (modalElement) {
                modalElement.style.display = 'none';
                modalElement.classList.remove('show');
                modalElement.remove();
            }
            
            if (backdrop) {
                backdrop.remove();
            }
            
            document.body.classList.remove('modal-open');
        }
        
        // Adicionar evento de fechamento para o botão X
        const closeButton = modalElement.querySelector('.btn-close');
        if (closeButton) {
            closeButton.addEventListener('click', fecharModal);
        }
        
        // Adicionar evento de fechamento para o botão Fechar
        const closeButtonFooter = modalElement.querySelector('.btn-secondary');
        if (closeButtonFooter) {
            closeButtonFooter.addEventListener('click', fecharModal);
        }
        
        // Adicionar evento de fechamento para clicar no backdrop
        modalElement.addEventListener('click', function(e) {
            if (e.target === modalElement) {
                fecharModal();
            }
        });
        
        // Remover modal do DOM quando fechado (Bootstrap)
        modalElement.addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }

    function aplicarFiltros() {
        const periodo = document.getElementById('filtroPeriodo').value;
        const veiculo = document.getElementById('filtroVeiculo').value;
        const rota = document.getElementById('filtroRota').value;

        showNotification('Atualizando ranking...', 'info');

        // Recarregar conteúdo do ranking encaminhando os filtros à API
        carregarConteudoRanking({ periodo, veiculo, rota });
    }

    // Função para simular dados de gamificação baseados nos motoristas reais
    function simularDadosGamificacao(motoristas) {
        const leaderboard = motoristas.map((motorista, index) => {
            // Usar ID do motorista como base para gerar valores consistentes
            const seed = motorista.id * 7 + 13; // Fórmula para gerar seed único
            const pontos = (seed % 1000) + 100; // Pontos entre 100-1099
            const nivel = pontos >= 1000 ? 'Lenda' : 
                         pontos >= 900 ? 'Diamante' : 
                         pontos >= 600 ? 'Platina' : 
                         pontos >= 300 ? 'Ouro' : 
                         pontos >= 100 ? 'Prata' : 'Bronze';
            const emoji = pontos >= 1000 ? '👑' : 
                         pontos >= 900 ? '💎' : 
                         pontos >= 600 ? '🏆' : 
                         pontos >= 300 ? '🥇' : 
                         pontos >= 100 ? '🥈' : '🥉';
            
            return {
                motorista_id: motorista.id,
                motorista_nome: motorista.nome || 'Motorista ' + motorista.id,
                pontos_totais: pontos,
                nivel_calculado: nivel,
                nivel_emoji: emoji,
                rotas_concluidas: (seed % 50) + 10, // Rotas entre 10-59
                telefone: motorista.telefone || 'N/A'
            };
        }).sort((a, b) => b.pontos_totais - a.pontos_totais);

        return {
            leaderboard: leaderboard,
            total_motoristas: motoristas.length,
            media_pontos: Math.round(leaderboard.reduce((sum, m) => sum + m.pontos_totais, 0) / leaderboard.length),
            max_pontos: Math.max(...leaderboard.map(m => m.pontos_totais)),
            total_desafios_completos: Math.floor(motoristas.length * 0.3) + 5 // Baseado no número de motoristas
        };
    }

    // Função para simular dados de ranking baseados nos motoristas reais
    function simularDadosRanking(motoristas) {
        const ranking = motoristas.map((motorista, index) => {
            // Usar ID do motorista como base para gerar valores consistentes
            const seed = motorista.id * 11 + 17; // Fórmula diferente para ranking
            const nota_final = (seed % 40) + 60; // Nota entre 60-99
            const status = 'Ativo'; // Sempre mostrar como Ativo
            
            return {
                motorista_id: motorista.id,
                motorista_nome: motorista.nome || 'Motorista ' + motorista.id,
                nota_final: nota_final,
                status: status,
                telefone: motorista.telefone || 'N/A',
                cpf: motorista.cpf || 'N/A'
            };
        }).sort((a, b) => b.nota_final - a.nota_final);

        // Calcular distribuição por faixas
        const excelente = ranking.filter(m => m.nota_final >= 90).length;
        const bom = ranking.filter(m => m.nota_final >= 70 && m.nota_final < 90).length;
        const regular = ranking.filter(m => m.nota_final >= 50 && m.nota_final < 70).length;
        const necessita_melhoria = ranking.filter(m => m.nota_final < 50).length;

        return {
            ranking: ranking,
            total_motoristas: motoristas.length,
            excelente: excelente,
            bom: bom,
            regular: regular,
            necessita_melhoria: necessita_melhoria
        };
    }
    </script>
    

    <?php include '../includes/scroll_to_top.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
