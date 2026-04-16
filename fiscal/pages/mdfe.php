<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
configure_session();
session_start();
require_authentication();
require_once __DIR__ . '/../../includes/csrf.php';

$page_title = "Gestão de MDF-e";
$is_modern = true;

// Layout moderno é padrão: remove ?classic= da URL para manter consistência.
if (array_key_exists('classic', $_GET)) {
    $q = $_GET;
    unset($q['classic']);
    $qs = http_build_query($q);
    header('Location: mdfe.php' . ($qs !== '' ? '?' . $qs : ''), true, 301);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="../../css/theme.css">
    <link rel="stylesheet" href="../../css/responsive.css">
    <link rel="stylesheet" href="../../css/fornc-modern-page.css">
    <link rel="stylesheet" href="../../css/routes.css?v=1.0.1">
    <link rel="icon" type="image/png" href="../../logo.png">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="css/mdfe-page.css?v=<?php echo file_exists(__DIR__ . '/css/mdfe-page.css') ? (int)filemtime(__DIR__ . '/css/mdfe-page.css') : 1; ?>">
</head>
<body class="<?php echo $is_modern ? 'routes-modern mdfe-modern' : ''; ?>">
    <div class="app-container">
        <?php include '../../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../../includes/header.php'; ?>
            <div class="dashboard-content<?php echo $is_modern ? ' fornc-page routes-modern-page' : ''; ?>">
                <?php if ($is_modern): ?>
                <div class="dashboard-header">
                    <h1><?php echo $page_title; ?></h1>
                </div>
                <div class="fornc-toolbar">
                    <div class="fornc-search-block">
                        <label for="searchMdfe">Busca rápida</label>
                        <div class="fornc-search-inner">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input type="text" id="searchMdfe" placeholder="Número, chave, UF, origem documental...">
                        </div>
                    </div>
                    <div class="fornc-filters-inline">
                        <div class="fg">
                            <label for="statusMdfeFilter">Status</label>
                            <select id="statusMdfeFilter">
                                <option value="">Todos</option>
                                <option value="autorizado">Autorizado</option>
                                <option value="pendente">Pendente</option>
                                <option value="rascunho">Rascunho</option>
                                <option value="encerrado">Encerrado</option>
                                <option value="cancelado">Cancelado</option>
                                <option value="denegado">Denegado</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label for="perPageMdfe">Por página</label>
                            <select id="perPageMdfe">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                    </div>
                    <div class="fornc-btn-row">
                        <button type="button" class="fornc-btn fornc-btn--primary" onclick="abrirFluxoNovoMDFE()">
                            <i class="fas fa-plus"></i> Novo MDF-e
                        </button>
                        <button type="button" class="fornc-btn fornc-btn--accent" id="applyMdfeFilters">
                            <i class="fas fa-search"></i> Pesquisar
                        </button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="clearMdfeFilters" title="Limpar filtros">
                            <i class="fas fa-undo"></i> Limpar
                        </button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" title="Atualizar lista" onclick="carregarMDFE()">
                            <i class="fas fa-sync-alt"></i> Atualizar
                        </button>
                        <button type="button" class="fornc-btn fornc-btn--muted" title="Exportar" onclick="exportarDados()">
                            <i class="fas fa-file-export"></i> Exportar
                        </button>
                    </div>
                </div>
                <?php else: ?>
                <div class="dashboard-header">
                    <h1><?php echo $page_title; ?></h1>
                    <div class="dashboard-actions">
                        <button type="button" class="btn-add-widget" onclick="abrirFluxoNovoMDFE()">
                            <i class="fas fa-plus"></i> Novo MDF-e
                        </button>
                        <div class="view-controls">
                            <button type="button" class="btn-restore-layout" title="Atualizar lista" onclick="carregarMDFE()">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button type="button" class="btn-toggle-layout" title="Exportar" onclick="exportarDados()">
                                <i class="fas fa-file-export"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="table-container routes-table-wrap mdfe-table-wrap<?php echo $is_modern ? ' fornc-table-wrap' : ''; ?>" style="margin-top: 8px;">
                    <div id="mdfeList">
                        <p>Carregando MDF-e...</p>
                    </div>
                </div>
                <div class="pagination" id="paginationMdfeContainer"></div>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/partials/mdfe_modals_stack.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/sidebar.js"></script>
    <script src="../../js/theme.js"></script>
    <?php include __DIR__ . '/partials/mdfe_ui_modals.php'; ?>
    <script>window.MDFE_CSRF_TOKEN = <?php echo json_encode(csrf_token_get()); ?>;</script>
    <script src="../../js/doc_validators.js"></script>
    <script src="js/mdfe.js?v=<?php echo filemtime(__DIR__ . '/js/mdfe.js'); ?>"></script>
    <?php include '../../includes/footer.php'; ?>
</body>
</html>
