<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include configuration and functions first
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/sf_api_base.php';

// Configure session before starting it
configure_session();

// Start session
session_start();

// Check authentication
require_authentication();
require_once __DIR__ . '/../../includes/csrf.php';

// CRT do emitente (1=Simples, 2=Simples excesso, 3=Regime normal) — define impostos no XML do item
$fiscal_crt_emit = 1;
try {
    require_once '../../includes/db_connect.php';
    $eid = (int)($_SESSION['empresa_id'] ?? 0);
    if (!empty($conn) && $eid > 0) {
        $chk = $conn->query("SHOW COLUMNS FROM fiscal_config_empresa LIKE 'crt'");
        if ($chk && $chk->rowCount() > 0) {
            $st = $conn->prepare('SELECT crt FROM fiscal_config_empresa WHERE empresa_id = ? LIMIT 1');
            $st->execute([$eid]);
            $cr = $st->fetchColumn();
            if ($cr !== false && $cr !== null && $cr !== '') {
                $fiscal_crt_emit = max(1, min(3, (int)$cr));
            }
        }
    }
} catch (Throwable $e) {
    $fiscal_crt_emit = 1;
}

// Set page title
$page_title = "Gestão Fiscal de Transporte";
$is_modern = true;

// Layout moderno é padrão: remove ?classic= da URL para manter consistência.
if (array_key_exists('classic', $_GET)) {
    $q = $_GET;
    unset($q['classic']);
    $qs = http_build_query($q);
    header('Location: nfe.php' . ($qs !== '' ? '?' . $qs : ''), true, 301);
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="../../css/theme.css">
    <link rel="stylesheet" href="../../css/responsive.css">
    <link rel="stylesheet" href="../../css/fornc-modern-page.css">
    <link rel="stylesheet" href="../../css/routes.css?v=1.0.1">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../logo.png">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    
    <style>
        .fiscal-dashboard {
            padding: 20px;
        }
        
        .fiscal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .fiscal-card {
            background: var(--bg-secondary);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 20px;
        }
        
        .fiscal-card h3 {
            margin: 0 0 15px 0;
            color: var(--text-primary);
            font-size: 1.1rem;
        }
        
        .fiscal-metric {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .fiscal-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .fiscal-subtitle {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .document-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        
        .document-tab {
            padding: 10px 20px;
            border: none;
            background: none;
            color: var(--text-secondary);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .document-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .document-content {
            display: none;
        }
        
        .document-content.active {
            display: block;
        }
        
        .document-list {
            background: var(--bg-secondary);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 20px;
        }
        
        .document-item {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        /* Tabela simples de NF-e */
        .nfe-table-wrap { overflow-x: auto; }
        .data-table th:first-child, .data-table td:first-child { width: 32px; text-align: center; }
        .data-table .col-num { text-align: right; }
        .data-table .col-mod,
        .data-table .col-serie,
        .data-table .col-numero,
        .data-table .col-recibo {
            text-align: center !important;
            white-space: nowrap;
        }
        .data-table .col-mod { width: 54px; }
        .data-table .col-serie { width: 72px; }
        .data-table .col-numero { width: 112px; }
        .data-table .col-recibo { width: 96px; }
        .data-table .col-vlr { text-align: right; white-space: nowrap; }
        .data-table .col-chave { font-family: monospace; font-size: 0.75rem; max-width: 280px; overflow: hidden; text-overflow: ellipsis; }
        .data-table .col-acoes { white-space: nowrap; }
        .data-table td.actions {
            display: flex;
            align-items: center;
            gap: 0.15rem;
        }
        .data-table .col-acoes a, .data-table .col-acoes button {
            padding: 2px 4px;
            margin: 0;
            border: none;
            background: none;
            cursor: pointer;
            color: var(--text-secondary);
        }
        .data-table .col-acoes a:hover, .data-table .col-acoes button:hover { color: var(--primary-color); }
        .data-table .situacao-autorizada, .data-table .situacao-recebida, .data-table .situacao-consultada_sefaz { color: #155724; font-weight: 500; }
        .data-table .situacao-pendente { color: #856404; }
        .data-table .situacao-cancelada { color: #721c24; }
        
        #modalVisualizarNfeBody dl dt { font-weight: 600; color: #6c757d; }
        #modalVisualizarNfeBody dl dd { margin-bottom: 0.5rem; }
        
        .nfe-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 16px 24px;
            margin-bottom: 8px;
        }
        .nfe-toolbar-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .nfe-toolbar-group .toolbar-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--text-secondary);
            font-weight: 600;
        }
        .nfe-toolbar-group .toolbar-btns {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        .btn-emit-nfe {
            background: #198754 !important;
            color: #fff !important;
        }
        .btn-emit-nfe:hover {
            filter: brightness(1.08);
        }
        
        /* Modal Emitir NF-e — centralizado na viewport, duas colunas (destinatário | item) */
        #modalEmitirNfe.modal .modal-dialog.modal-custom-wide {
            max-width: min(1400px, 98vw);
            width: 98%;
            margin-left: auto;
            margin-right: auto;
        }
        #modalEmitirNfe .emit-nfe-main-row {
            align-items: stretch;
        }
        /* Garante duas colunas lado a lado (evita min-width do conteúdo quebrar o grid) */
        #modalEmitirNfe .emit-nfe-main-row > [class*="col-"] {
            min-width: 0;
        }
        @media (min-width: 768px) {
            #modalEmitirNfe .emit-nfe-section.emit-nfe-dest,
            #modalEmitirNfe .emit-nfe-section.emit-nfe-item {
                min-height: 100%;
            }
        }
        #modalEmitirNfe .emit-nfe-section {
            border: 1px solid var(--border-color, #dee2e6);
            border-radius: 10px;
            padding: 1rem 1.15rem;
            margin-bottom: 1rem;
            background: var(--bg-secondary, #f8f9fa);
        }
        #modalEmitirNfe .emit-nfe-section.emit-nfe-dest {
            border-left: 4px solid #0d6efd;
        }
        #modalEmitirNfe .emit-nfe-section.emit-nfe-item {
            border-left: 4px solid #198754;
        }
        #modalEmitirNfe .emit-nfe-section.emit-nfe-op {
            border-left: 4px solid #6c757d;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
        }
        #modalEmitirNfe .emit-nfe-section-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary, #212529);
            margin: 0 0 0.65rem 0;
            padding-bottom: 0.45rem;
            border-bottom: 1px solid var(--border-color, #dee2e6);
        }
        #modalEmitirNfe .emit-nfe-section-header i {
            width: 1.25rem;
            text-align: center;
            opacity: 0.9;
        }
        #modalEmitirNfe .emit-nfe-hint {
            font-size: 0.8rem;
            color: var(--text-secondary, #6c757d);
            margin: -0.25rem 0 0.75rem 0;
            line-height: 1.35;
        }
        #modalEmitirNfe .emit-nfe-subtitle {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-secondary, #6c757d);
            margin: 1rem 0 0.5rem 0;
        }
        #modalEmitirNfe .emit-nfe-subtitle:first-of-type {
            margin-top: 0;
        }
        
        .document-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .document-header h4 {
            margin: 0;
            color: var(--text-primary);
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-success { background: #d4edda; color: #155724; }
        .status-warning { background: #fff3cd; color: #856404; }
        .status-danger { background: #f8d7da; color: #721c24; }
        .status-info { background: #d1ecf1; color: #0c5460; }
        
        .document-details p {
            margin: 5px 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .document-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        
        .btn-primary { background: #007bff; color: white; border: none; }
        .btn-info { background: #17a2b8; color: white; border: none; }
        .btn-secondary { background: #6c757d; color: white; border: none; }
        .btn-success { background: #28a745; color: white; border: none; }
        .btn-warning { background: #ffc107; color: #212529; border: none; }
        .btn-danger { background: #dc3545; color: white; border: none; }
        
        .btn-sm:hover {
            opacity: 0.8;
            cursor: pointer;
        }
        
        .sefaz-status {
            text-align: center;
            padding: 20px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .status-success { background: #d4edda; color: #155724; }
        .status-danger { background: #f8d7da; color: #721c24; }
        
        /* Estilos para as abas de recebimento */
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 2px solid transparent;
            color: var(--text-secondary);
            font-weight: 500;
            padding: 12px 20px;
            transition: all 0.3s ease;
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--primary-color);
            border-bottom-color: var(--border-color);
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            background: none;
        }
        
        .nav-tabs .nav-link i {
            margin-right: 8px;
        }
        
        /* Estilos para alertas informativos */
        .alert-info {
            background-color: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }
        
        /* Estilos para progress bar */
        .progress {
            height: 8px;
            border-radius: 4px;
            background-color: #e9ecef;
        }
        
        .progress-bar {
            background-color: var(--primary-color);
            border-radius: 4px;
        }
        
        /* Estilos para formulários */
        .form-text {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        /* Largura extra aplicada junto com #modalEmitirNfe ... modal-custom-wide acima */
        body.nfe-modern .dashboard-content.fornc-page { overflow-x: auto; }
        body.nfe-modern .nfe-table-wrap .data-table { min-width: 1140px; }
    </style>
</head>
<body class="<?php echo $is_modern ? 'routes-modern nfe-modern' : ''; ?>">
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include '../../includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include '../../includes/header.php'; ?>
            
            <!-- Page Content -->
            <div class="dashboard-content<?php echo $is_modern ? ' fornc-page routes-modern-page' : ''; ?>">
                <?php if ($is_modern): ?>
                <div class="dashboard-header">
                    <h1><?php echo $page_title; ?></h1>
                </div>
                <div class="fornc-toolbar">
                    <div class="fornc-search-block">
                        <label for="searchNfe">Busca rápida</label>
                        <div class="fornc-search-inner">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input type="text" id="searchNfe" placeholder="Número, chave, cliente, protocolo...">
                        </div>
                    </div>
                    <div class="fornc-filters-inline">
                        <div class="fg">
                            <label for="statusNfeFilter">Status</label>
                            <select id="statusNfeFilter">
                                <option value="">Todos</option>
                                <option value="autorizada">Autorizada</option>
                                <option value="autorizado">Autorizado</option>
                                <option value="recebida">Recebida</option>
                                <option value="consultada_sefaz">Consultada SEFAZ</option>
                                <option value="cancelada">Cancelada</option>
                                <option value="pendente">Pendente</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label for="perPageNfe">Por página</label>
                            <select id="perPageNfe">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                    </div>
                    <div class="fornc-btn-row">
                        <button id="receberNFEBtn" class="fornc-btn fornc-btn--primary" type="button" onclick="receberNFE()">
                            <i class="fas fa-inbox"></i> Receber NF-e
                        </button>
                        <button id="sincronizarCnpjBtn" class="fornc-btn fornc-btn--accent" type="button" onclick="sincronizarNfeCnpj(false)" title="Buscar NF-e novas do meu CNPJ">
                            <i class="fas fa-cloud-download-alt"></i> Buscar NF-e
                        </button>
                        <button id="sincronizarCnpjZeroBtn" class="fornc-btn fornc-btn--ghost" type="button" onclick="sincronizarNfeCnpj(true)" title="Buscar desde o início">
                            <i class="fas fa-history"></i> Desde o início
                        </button>
                        <button type="button" class="fornc-btn fornc-btn--accent" id="applyNfeFilters">
                            <i class="fas fa-search"></i> Pesquisar
                        </button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="clearNfeFilters" title="Limpar filtros">
                            <i class="fas fa-undo"></i>
                        </button>
                        <button type="button" class="fornc-btn fornc-btn--primary" onclick="abrirModalEmitirNfe()" title="Gerar e enviar NF-e modelo 55">
                            <i class="fas fa-file-invoice"></i> Emitir NF-e
                        </button>
                        <button id="refreshBtn" class="fornc-btn fornc-btn--ghost" title="Atualizar listas">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <button id="exportBtn" class="fornc-btn fornc-btn--muted" title="Exportar">
                            <i class="fas fa-file-export"></i> Exportar
                        </button>
                    </div>
                </div>
                <?php else: ?>
                <div class="dashboard-header">
                    <h1><?php echo $page_title; ?></h1>
                    <div class="dashboard-actions nfe-toolbar">
                        <div class="nfe-toolbar-group">
                            <span class="toolbar-label">Receber / consultar (notas de terceiros)</span>
                            <div class="toolbar-btns">
                                <button id="receberNFEBtn" class="btn-add-widget" type="button" onclick="receberNFE()">
                                    <i class="fas fa-inbox"></i> Receber NF-e
                                </button>
                                <button id="sincronizarCnpjBtn" class="btn-add-widget" type="button" onclick="sincronizarNfeCnpj(false)" title="Buscar NF-e novas do meu CNPJ (desde a última sincronização)">
                                    <i class="fas fa-cloud-download-alt"></i> Buscar NF-e do meu CNPJ
                                </button>
                                <button id="sincronizarCnpjZeroBtn" class="btn-add-widget" type="button" onclick="sincronizarNfeCnpj(true)" title="Buscar desde o início (últimos 3 meses na SEFAZ). Use se não trouxe notas que você sabe que existem.">
                                    <i class="fas fa-history"></i> Buscar desde o início
                                </button>
                            </div>
                        </div>
                        <div class="nfe-toolbar-group">
                            <span class="toolbar-label">Emissão própria (sua empresa como emitente)</span>
                            <div class="toolbar-btns">
                                <button type="button" class="btn-add-widget btn-emit-nfe" onclick="abrirModalEmitirNfe()" title="Gerar e enviar NF-e modelo 55 à SEFAZ">
                                    <i class="fas fa-file-invoice"></i> Emitir NF-e
                                </button>
                            </div>
                        </div>
                        <div class="view-controls" style="margin-left: auto;">
                            <button id="refreshBtn" class="btn-restore-layout" title="Atualizar listas">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button id="exportBtn" class="btn-toggle-layout" title="Exportar">
                                <i class="fas fa-file-export"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- NF-e emitidas pela empresa -->
                <div class="table-container routes-table-wrap nfe-table-wrap<?php echo $is_modern ? ' fornc-table-wrap' : ''; ?>" style="margin-top: 8px;">
                    <div id="nfeEmitidasList">
                        <p class="text-muted">Carregando...</p>
                    </div>
                </div>
                <!-- Lista de NF-e Recebidas -->
                <div class="table-container routes-table-wrap nfe-table-wrap<?php echo $is_modern ? ' fornc-table-wrap' : ''; ?>" style="margin-top: 10px;">
                    <div id="nfeList">
                        <p>Carregando NF-e...</p>
                    </div>
                </div>
                <div class="pagination" id="paginationNfeContainer"></div>
            </div>
        </div>
    </div>
    
    <!-- Modal: receber NF-e (XML, manual ou SEFAZ) -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?> fade" id="receberNfeModal" tabindex="-1" aria-labelledby="receberNfeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="receberNfeModalLabel">📥 Receber NF-e</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-secondary mb-3 py-2 small" id="receberNfeModalIntro">
                        <strong>NF-e de entrada</strong> (fornecedor): escolha abaixo <strong>XML</strong> (arquivo), <strong>digitação manual</strong> ou <strong>chave na SEFAZ</strong> (exige certificado A1 e configuração fiscal).
                    </div>
                    <div id="formularioContainer" class="mt-3">
                        <div id="formularioContent"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnReceberNfe" style="display: none;">
                        Continuar
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Emitir NF-e (emissão própria) -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?> fade" id="modalEmitirNfe" tabindex="-1" aria-labelledby="modalEmitirNfeLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-custom-wide mx-auto">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="modalEmitirNfeLabel"><i class="fas fa-file-invoice text-success"></i> Emitir NF-e (modelo 55)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body pt-2">
                    <div class="alert alert-warning small mb-3 py-2 mb-2">
                        <strong>Emitente:</strong> Simples Nacional (CRT 1, CSOSN 102) com um item, salvo se o CRT na configuração for outro. <strong>Certificado A1</strong> e <strong>Configurações fiscais</strong> obrigatórios.
                    </div>
                    <form id="formEmitirNfe">
                        <input type="hidden" id="emit_fiscal_crt" value="<?php echo (int)$fiscal_crt_emit; ?>">
                        
                        <div class="emit-nfe-section emit-nfe-op">
                            <div class="emit-nfe-section-header mb-0 pb-2 border-0">
                                <i class="fas fa-file-signature text-secondary"></i> Operação da nota
                            </div>
                            <div class="row g-2 align-items-end">
                                <div class="col-md-12">
                                    <label class="form-label mb-1" for="emit_natOp">Natureza da operação <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="emit_natOp" value="Venda de mercadoria" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row g-3 emit-nfe-main-row">
                            <!-- Coluna esquerda: destinatário (cliente) — col-md-6 = lado a lado em telas ≥768px -->
                            <div class="col-12 col-md-6">
                                <div class="emit-nfe-section emit-nfe-dest h-100">
                                    <div class="emit-nfe-section-header">
                                        <i class="fas fa-user-tie text-primary"></i> Destinatário (cliente)
                                    </div>
                                    <p class="emit-nfe-hint mb-2">Quem recebe a mercadoria — dados do grupo <code>dest</code> e <code>enderDest</code> no XML.</p>
                                    
                                    <div class="mb-2">
                                        <label class="form-label">Carregar cadastro</label>
                                        <select class="form-select form-select-sm" id="emit_sel_fornecedor">
                                            <option value="">— Digitar manualmente —</option>
                                        </select>
                                        <div class="form-text small">Use <a href="<?php echo htmlspecialchars(sf_app_url('pages/fornecedores_moderno.php')); ?>" target="_blank" rel="noopener">Fornecedores</a> para preencher CPF/CNPJ e endereço.</div>
                                    </div>
                                    
                                    <div class="emit-nfe-subtitle">Documento e identificação</div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-12">
                                            <span class="form-label d-block small">Tipo</span>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <input type="radio" class="btn-check" name="emit_dest_kind" id="emit_dest_kind_j" value="J" autocomplete="off" checked>
                                                <label class="btn btn-outline-secondary" for="emit_dest_kind_j">PJ (CNPJ)</label>
                                                <input type="radio" class="btn-check" name="emit_dest_kind" id="emit_dest_kind_f" value="F" autocomplete="off">
                                                <label class="btn btn-outline-secondary" for="emit_dest_kind_f">PF (CPF)</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6" id="emit_wrap_cnpj">
                                            <label class="form-label">CNPJ</label>
                                            <input type="text" class="form-control form-control-sm" id="emit_dest_cnpj" placeholder="Somente números" maxlength="14">
                                        </div>
                                        <div class="col-md-6" id="emit_wrap_cpf" style="display:none;">
                                            <label class="form-label">CPF</label>
                                            <input type="text" class="form-control form-control-sm" id="emit_dest_cpf" placeholder="Somente números" maxlength="11">
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label" id="emit_label_nome">Razão social / nome <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" id="emit_dest_nome" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">IE</label>
                                            <input type="text" class="form-control form-control-sm" id="emit_dest_ie">
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label">E-mail</label>
                                            <input type="email" class="form-control form-control-sm" id="emit_dest_email">
                                        </div>
                                    </div>
                                    
                                    <div class="emit-nfe-subtitle">Endereço de entrega</div>
                                    <div class="row g-2">
                                        <div class="col-md-8">
                                            <label class="form-label">Logradouro <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" id="emit_ed_xLgr" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Nº <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" id="emit_ed_nro" required>
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label">Complemento</label>
                                            <input type="text" class="form-control form-control-sm" id="emit_ed_xCpl" maxlength="60">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Bairro <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" id="emit_ed_xBairro" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">CEP <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" id="emit_ed_cep" maxlength="9" placeholder="8 dígitos" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">UF <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" id="emit_ed_uf" maxlength="2" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">cMun (IBGE) <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" id="emit_ed_cMun" maxlength="7" placeholder="7 dígitos" required>
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label">Município <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" id="emit_ed_xMun" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Coluna direita: produto / item -->
                            <div class="col-12 col-md-6">
                                <div class="emit-nfe-section emit-nfe-item h-100">
                                    <div class="emit-nfe-section-header">
                                        <i class="fas fa-box text-success"></i> Produto / item da NF-e
                                    </div>
                                    <p class="emit-nfe-hint mb-2">Mercadoria ou serviço — grupo <code>det/prod</code> e impostos conforme CRT do emitente.</p>
                                    <p class="small text-muted mb-2">Com <strong>CRT 3</strong>: <code>ICMS00</code>, <code>PISAliq</code>, <code>COFINSAliq</code>. CRT 1/2: <code>ICMSSN</code> + PIS/COFINS CST 07. <a href="#" class="text-decoration-none" onclick="event.preventDefault(); document.getElementById('emitHelpImposto').classList.toggle('d-none');">Ajuda impostos</a></p>
                                    <div id="emitHelpImposto" class="alert alert-light small py-2 d-none mb-3 border">
                                        Rode <code>fiscal/database/alter_fiscal_config_crt.sql</code> se <code>crt</code> não existir. Opcional no JSON: <code>pICMS_padrao</code>, <code>pPIS_padrao</code>, <code>pCOFINS_padrao</code>, <code>imposto_regime_normal</code> (CRT 2); por item: <code>cProd</code>, <code>cEAN</code>, <code>infAdProd</code>, <code>indTot</code>, <code>icms_*</code>, <code>pis_*</code>, <code>cofins_*</code>.
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <label class="form-label">cProd</label>
                                            <input type="text" class="form-control form-control-sm" id="emit_item_cProd" value="001" maxlength="60">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">cEAN / GTIN</label>
                                            <input type="text" class="form-control form-control-sm" id="emit_item_cEAN" placeholder="SEM GTIN ou 789..." maxlength="14">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Un. (uCom)</label>
                                            <input type="text" class="form-control form-control-sm" id="emit_item_uCom" value="UN" maxlength="6">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Descrição (xProd) <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" id="emit_item_xProd" value="Mercadoria" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">NCM <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" id="emit_item_ncm" maxlength="8" value="84713012" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">CFOP <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" id="emit_item_cfop" value="5102" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Ind. total (indTot)</label>
                                            <select class="form-select form-select-sm" id="emit_item_indTot">
                                                <option value="1" selected>1 — no total da NF</option>
                                                <option value="0">0 — fora do total</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Qtd <span class="text-danger">*</span></label>
                                            <input type="number" step="0.0001" class="form-control form-control-sm" id="emit_item_qtd" value="1" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Vlr. unit. (R$) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" class="form-control form-control-sm" id="emit_item_vun" value="100.00" required>
                                            <div class="form-text">vProd = quantidade × valor unitário (no XML).</div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Informação adicional do item (infAdProd)</label>
                                            <input type="text" class="form-control form-control-sm" id="emit_item_infAdProd" maxlength="500" placeholder="Opcional">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" id="btnEnviarEmitirNfe" onclick="enviarEmitirNfe()">
                        <i class="fas fa-paper-plane"></i> Enviar à SEFAZ
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Visualizar NF-e (dados básicos) -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?> fade" id="modalVisualizarNfe" tabindex="-1" aria-labelledby="modalVisualizarNfeLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalVisualizarNfeLabel"><i class="fas fa-file-invoice"></i> NF-e</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body" id="modalVisualizarNfeBody">
                    <p class="text-muted">Carregando...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Files -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Sistema JavaScript -->
    <script src="../../js/sidebar.js"></script>
    <script src="../../js/theme.js"></script>
    <script>window.FISCAL_CSRF_TOKEN = <?php echo json_encode(csrf_token_get()); ?>;</script>
    <script src="js/fiscal_api_fetch.js?v=<?php echo file_exists(__DIR__ . '/js/fiscal_api_fetch.js') ? (int)filemtime(__DIR__ . '/js/fiscal_api_fetch.js') : 1; ?>"></script>
    
    <script>
        var nfeDocsRecebidasCache = [];
        var nfePaginaAtual = 1;
        var nfePerPage = 10;

        document.addEventListener('DOMContentLoaded', function() {
            carregarNFE();
            carregarNfeEmitidas();
            const urlParams = new URLSearchParams(window.location.search);
            carregarFornecedoresEmitSelect().then(function() {
                if (urlParams.get('fornecedor_id')) {
                    const el = document.getElementById('modalEmitirNfe');
                    if (el && typeof bootstrap !== 'undefined') {
                        bootstrap.Modal.getOrCreateInstance(el).show();
                    }
                }
            });
            initEmitDestKindToggle();
            
            const refreshBtn = document.getElementById('refreshBtn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    carregarNFE();
                    carregarNfeEmitidas();
                });
            }

            var btnReceberNfe = document.getElementById('btnReceberNfe');
            if (btnReceberNfe) {
                btnReceberNfe.addEventListener('click', function(e) {
                    e.preventDefault();
                    processarRecebimentoNFE();
                });
            }
            document.body.addEventListener('submit', function(ev) {
                var t = ev.target;
                if (t && t.id === 'recvNfeSefazForm') {
                    ev.preventDefault();
                    processarConsultaSefaz();
                }
            }, true);
            
            const selForn = document.getElementById('emit_sel_fornecedor');
            if (selForn) {
                selForn.addEventListener('change', function() {
                    const id = this.value;
                    if (!id) return;
                    const f = window._fornecedoresEmitCache && window._fornecedoresEmitCache[id];
                    if (f) aplicarFornecedorEmitir(f);
                });
            }

            var applyBtn = document.getElementById('applyNfeFilters');
            if (applyBtn) {
                applyBtn.addEventListener('click', function() {
                    nfePaginaAtual = 1;
                    aplicarFiltrosNfeRecebidas();
                });
            }

            var clearBtn = document.getElementById('clearNfeFilters');
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    var search = document.getElementById('searchNfe');
                    var status = document.getElementById('statusNfeFilter');
                    if (search) search.value = '';
                    if (status) status.value = '';
                    nfePaginaAtual = 1;
                    aplicarFiltrosNfeRecebidas();
                });
            }

            var perPage = document.getElementById('perPageNfe');
            if (perPage) {
                perPage.addEventListener('change', function() {
                    nfePerPage = Math.max(1, parseInt(this.value, 10) || 10);
                    nfePaginaAtual = 1;
                    aplicarFiltrosNfeRecebidas();
                });
            }

        });
        
        function initEmitDestKindToggle() {
            const j = document.getElementById('emit_dest_kind_j');
            const f = document.getElementById('emit_dest_kind_f');
            const wCnpj = document.getElementById('emit_wrap_cnpj');
            const wCpf = document.getElementById('emit_wrap_cpf');
            const lblNome = document.getElementById('emit_label_nome');
            function sync() {
                const isJ = j && j.checked;
                if (wCnpj) wCnpj.style.display = isJ ? '' : 'none';
                if (wCpf) wCpf.style.display = isJ ? 'none' : '';
                if (lblNome) lblNome.textContent = isJ ? 'Razão social' : 'Nome completo';
                const inpCnpj = document.getElementById('emit_dest_cnpj');
                const inpCpf = document.getElementById('emit_dest_cpf');
                if (inpCnpj) { inpCnpj.required = !!isJ; if (!isJ) inpCnpj.value = ''; }
                if (inpCpf) { inpCpf.required = !isJ; if (isJ) inpCpf.value = ''; }
            }
            if (j) j.addEventListener('change', sync);
            if (f) f.addEventListener('change', sync);
            sync();
        }
        
        function aplicarFornecedorEmitir(f) {
            if (!f) return;
            const isJ = f.tipo === 'J';
            const rj = document.getElementById('emit_dest_kind_j');
            const rf = document.getElementById('emit_dest_kind_f');
            if (isJ && rj) { rj.checked = true; rj.dispatchEvent(new Event('change')); }
            if (!isJ && rf) { rf.checked = true; rf.dispatchEvent(new Event('change')); }
            document.getElementById('emit_dest_cnpj').value = f.cnpj || '';
            document.getElementById('emit_dest_cpf').value = f.cpf || '';
            document.getElementById('emit_dest_nome').value = f.nome || '';
            document.getElementById('emit_dest_ie').value = f.inscricao_estadual || '';
            document.getElementById('emit_dest_email').value = f.email || '';
            document.getElementById('emit_ed_xLgr').value = f.endereco || '';
            document.getElementById('emit_ed_nro').value = f.numero || '';
            document.getElementById('emit_ed_xCpl').value = f.complemento || '';
            document.getElementById('emit_ed_xBairro').value = f.bairro || '';
            document.getElementById('emit_ed_cep').value = f.cep || '';
            document.getElementById('emit_ed_uf').value = (f.uf || '').toUpperCase();
            document.getElementById('emit_ed_cMun').value = f.codigo_municipio_ibge || '';
            document.getElementById('emit_ed_xMun').value = f.cidade || '';
        }
        
        function carregarFornecedoresEmitSelect() {
            const sel = document.getElementById('emit_sel_fornecedor');
            if (!sel) return Promise.resolve();
            return fetch(<?php echo json_encode(sf_app_url('api/fornecedores.php') . '?action=list&situacao=A&all=1', JSON_UNESCAPED_SLASHES); ?>, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    const list = data.fornecedores || [];
                    window._fornecedoresEmitCache = {};
                    list.forEach(function(f) {
                        window._fornecedoresEmitCache[String(f.id)] = f;
                    });
                    let html = '<option value="">— Digitar manualmente —</option>';
                    list.forEach(function(f) {
                        const doc = f.tipo === 'J' ? (f.cnpj || '') : (f.cpf || '');
                        const label = (f.nome || 'Sem nome') + (doc ? ' — ' + doc : '');
                        html += '<option value="' + f.id + '">' + escapeHtmlEmit(label) + '</option>';
                    });
                    sel.innerHTML = html;
                    const params = new URLSearchParams(window.location.search);
                    const prefId = params.get('fornecedor_id');
                    if (prefId && window._fornecedoresEmitCache[prefId]) {
                        sel.value = prefId;
                        aplicarFornecedorEmitir(window._fornecedoresEmitCache[prefId]);
                    }
                })
                .catch(function() { /* tabela pode não existir ainda */ });
        }
        
        function escapeHtmlEmit(s) {
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }
        
        function abrirModalEmitirNfe() {
            carregarFornecedoresEmitSelect().then(function() {
                const el = document.getElementById('modalEmitirNfe');
                if (!el) return;
                const modal = bootstrap.Modal.getOrCreateInstance(el);
                modal.show();
            });
        }
        
        function onlyDigits(s) {
            return (s || '').replace(/\D/g, '');
        }
        
        function enviarEmitirNfe() {
            const form = document.getElementById('formEmitirNfe');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            const isPJ = document.getElementById('emit_dest_kind_j') && document.getElementById('emit_dest_kind_j').checked;
            const cnpj = onlyDigits(document.getElementById('emit_dest_cnpj').value);
            const cpf = onlyDigits(document.getElementById('emit_dest_cpf').value);
            if (isPJ && cnpj.length !== 14) {
                fiscalToast('CNPJ do destinatário deve ter 14 dígitos.', 'warning');
                return;
            }
            if (!isPJ && cpf.length !== 11) {
                fiscalToast('CPF do destinatário deve ter 11 dígitos.', 'warning');
                return;
            }
            const cMun = onlyDigits(document.getElementById('emit_ed_cMun').value);
            if (cMun.length !== 7) {
                fiscalToast('Código do município (IBGE) deve ter 7 dígitos.', 'warning');
                return;
            }
            const q = parseFloat(document.getElementById('emit_item_qtd').value);
            const vun = parseFloat(document.getElementById('emit_item_vun').value);
            const vProd = Math.round(q * vun * 100) / 100;
            const ie = onlyDigits(document.getElementById('emit_dest_ie').value);
            const pedido = {
                natOp: document.getElementById('emit_natOp').value.trim(),
                serie: 1,
                crt: parseInt(document.getElementById('emit_fiscal_crt').value, 10) || 1,
                dest: {
                    xNome: document.getElementById('emit_dest_nome').value.trim(),
                    indIEDest: ie.length > 0 ? 1 : 9,
                    email: document.getElementById('emit_dest_email').value.trim()
                },
                itens: [{
                    cProd: (document.getElementById('emit_item_cProd') && document.getElementById('emit_item_cProd').value.trim()) || String(1),
                    xProd: document.getElementById('emit_item_xProd').value.trim(),
                    NCM: onlyDigits(document.getElementById('emit_item_ncm').value),
                    CFOP: document.getElementById('emit_item_cfop').value.trim(),
                    uCom: (document.getElementById('emit_item_uCom') && document.getElementById('emit_item_uCom').value.trim()) || 'UN',
                    qCom: q,
                    vUnCom: vun,
                    vProd: vProd,
                    indTot: parseInt(document.getElementById('emit_item_indTot').value, 10)
                }]
            };
            const ce = document.getElementById('emit_item_cEAN');
            if (ce && ce.value.trim()) {
                pedido.itens[0].cEAN = ce.value.trim();
            }
            const iad = document.getElementById('emit_item_infAdProd');
            if (iad && iad.value.trim()) {
                pedido.itens[0].infAdProd = iad.value.trim();
            }
            if (isPJ) {
                pedido.dest.CNPJ = cnpj;
            } else {
                pedido.dest.CPF = cpf;
            }
            if (ie.length > 0) {
                pedido.dest.IE = ie;
            }
            const xCpl = document.getElementById('emit_ed_xCpl') ? document.getElementById('emit_ed_xCpl').value.trim() : '';
            pedido.dest.enderDest = {
                xLgr: document.getElementById('emit_ed_xLgr').value.trim(),
                nro: document.getElementById('emit_ed_nro').value.trim(),
                xBairro: document.getElementById('emit_ed_xBairro').value.trim(),
                cMun: cMun,
                xMun: document.getElementById('emit_ed_xMun').value.trim(),
                UF: document.getElementById('emit_ed_uf').value.trim().toUpperCase(),
                CEP: onlyDigits(document.getElementById('emit_ed_cep').value)
            };
            if (xCpl) {
                pedido.dest.enderDest.xCpl = xCpl;
            }
            
            const btn = document.getElementById('btnEnviarEmitirNfe');
            const orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
            
            const fd = new FormData();
            fd.append('action', 'emitir_nfe_sefaz');
            fd.append('pedido_json', JSON.stringify(pedido));
            
            fiscalApiFetch('../api/documentos_fiscais_v2.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(res => {
                    const data = res.data || {};
                    if (res.status === 429 || (data && String(data.code || '') === 'rate_limited')) {
                        fiscalToast(fiscalApiErrorMessage(data, res.status), 'warning');
                        return;
                    }
                    if (data.success) {
                        fiscalToast('NF-e autorizada na SEFAZ.\n\nChave: ' + (data.chave_acesso || '') + '\nProtocolo: ' + (data.protocolo || ''), 'success');
                        bootstrap.Modal.getInstance(document.getElementById('modalEmitirNfe')).hide();
                        carregarNfeEmitidas();
                        carregarNFE();
                    } else {
                        fiscalToast(fiscalApiErrorMessage(data, res.status), 'danger');
                    }
                })
                .catch(err => {
                    console.error(err);
                    fiscalToast('Erro de comunicação ao emitir NF-e.', 'danger');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = orig;
                });
        }
        
        function carregarNfeEmitidas() {
            const box = document.getElementById('nfeEmitidasList');
            if (!box) return;
            box.innerHTML = '<div class="text-muted small">Carregando NF-e emitidas...</div>';
            
            fiscalApiFetch('../api/documentos_fiscais_v2.php?action=list&tipo=nfe_emitida&limit=100', { credentials: 'same-origin' })
                .then(res => {
                    const data = res.data || {};
                    if (res.status === 429 || (data && String(data.code || '') === 'rate_limited')) {
                        box.innerHTML = '<div class="alert alert-warning small mb-0">' + escapeHtml(fiscalApiErrorMessage(data, res.status)) + '</div>';
                        return;
                    }
                    if (!data.success) {
                        throw new Error(data.error || fiscalApiErrorMessage(data, res.status) || 'Lista indisponível');
                    }
                    const docs = data.documentos || [];
                    if (docs.length === 0) {
                        box.innerHTML = '<div class="text-muted">Nenhum registro.</div>';
                        return;
                    }
                    let html = '<table class="data-table"><thead><tr>';
                    html += '<th class="col-serie">Série</th><th class="col-numero">Número</th><th>Chave</th><th class="col-vlr">Valor</th><th class="col-recibo">Protocolo</th><th>Situação</th></tr></thead><tbody>';
                    docs.forEach(doc => {
                        const vlr = parseFloat(doc.valor_total || 0).toFixed(2).replace('.', ',');
                        const st = (doc.status || '').replace(/_/g, ' ');
                        html += '<tr>';
                        html += '<td class="col-serie">' + escapeHtml(String(doc.serie ?? '-')) + '</td>';
                        html += '<td class="col-numero">' + escapeHtml(String(doc.numero_nfe ?? '-')) + '</td>';
                        html += '<td class="col-chave" title="' + escapeHtml(doc.chave_acesso || '') + '">' + escapeHtml(doc.chave_acesso || '-') + '</td>';
                        html += '<td class="col-vlr">R$ ' + vlr + '</td>';
                        html += '<td class="col-recibo">' + escapeHtml(doc.protocolo_autorizacao || '-') + '</td>';
                        html += '<td>' + escapeHtml(st) + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table>';
                    box.innerHTML = html;
                })
                .catch(err => {
                    console.error(err);
                    box.innerHTML = '<div class="alert alert-warning small mb-0">Não foi possível carregar NF-e emitidas. ' +
                        'Crie a tabela <code>fiscal_nfe_emitidas</code> (script em <code>fiscal/database/</code>) ou verifique o log.</div>';
                });
        }
        
        // Funções para receber NF-e do cliente
        function receberNFE() {
            const el = document.getElementById('receberNfeModal');
            const modal = bootstrap.Modal.getOrCreateInstance(el);
            resetarFormulariosRecebimento();
            modal.show();
            setTimeout(function() { mostrarSelecaoMetodoRecebimentoNfe(); }, 150);
        }

        /** Tela inicial: XML, manual ou SEFAZ (antes o modal ia direto na SEFAZ e “sumia” o upload). */
        function mostrarSelecaoMetodoRecebimentoNfe() {
            const formularioContent = document.getElementById('formularioContent');
            const formularioContainer = document.getElementById('formularioContainer');
            const btnReceber = document.getElementById('btnReceberNfe');
            if (!formularioContent || !formularioContainer || !btnReceber) return;

            formularioContent.innerHTML = `
                <p class="text-muted small mb-3 mb-md-2">Como deseja registrar a nota recebida?</p>
                <div class="row g-3">
                    <div class="col-md-4">
                        <button type="button" class="btn btn-outline-primary w-100 h-100 text-start p-3" onclick="abrirMetodoXML()">
                            <i class="fas fa-file-code fa-lg d-block mb-2 text-primary"></i>
                            <strong>Arquivo XML</strong>
                            <span class="d-block small text-muted mt-1">Envie o XML da NF-e (nfeProc) baixado do portal ou do e-mail.</span>
                        </button>
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-outline-secondary w-100 h-100 text-start p-3" onclick="abrirMetodoManual()">
                            <i class="fas fa-keyboard fa-lg d-block mb-2"></i>
                            <strong>Digitar manualmente</strong>
                            <span class="d-block small text-muted mt-1">Informe número, chave, valores e clientes à mão.</span>
                        </button>
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-outline-info w-100 h-100 text-start p-3" onclick="abrirMetodoSefaz()">
                            <i class="fas fa-cloud-download-alt fa-lg d-block mb-2"></i>
                            <strong>Chave na SEFAZ</strong>
                            <span class="d-block small text-muted mt-1">Consulta pela chave (44 dígitos). Requer certificado A1 ativo.</span>
                        </button>
                    </div>
                </div>
            `;
            formularioContainer.style.display = 'block';
            btnReceber.style.display = 'none';
            btnReceber.removeAttribute('data-metodo');
        }
        
        function resetarFormulariosRecebimento() {
            // Ocultar formulário e botão
            document.getElementById('formularioContainer').style.display = 'none';
            document.getElementById('btnReceberNfe').style.display = 'none';
            document.getElementById('formularioContent').innerHTML = '';
        }

        function linkVoltarMetodosNfe() {
            return '<p class="mb-2"><button type="button" class="btn btn-link btn-sm text-secondary p-0" onclick="mostrarSelecaoMetodoRecebimentoNfe()"><i class="fas fa-arrow-left"></i> Voltar às opções</button></p>';
        }
        
        // Funções para abrir cada método de recebimento
        function abrirMetodoXML() {
            const formularioContent = document.getElementById('formularioContent');
            const formularioContainer = document.getElementById('formularioContainer');
            const btnReceber = document.getElementById('btnReceberNfe');
            
            formularioContent.innerHTML = `
                ` + linkVoltarMetodosNfe() + `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Upload XML:</strong> Selecione o arquivo XML da NF-e autorizada pela SEFAZ.
                </div>
                
                <form id="uploadXmlForm">
                    <div class="mb-3">
                        <label for="xmlFile" class="form-label">Arquivo XML da NF-e</label>
                        <input type="file" class="form-control" id="xmlFile" name="xml_file" accept=".xml" required>
                        <div class="form-text">Selecione o arquivo XML da NF-e autorizada pela SEFAZ</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="observacoesXml" class="form-label">Observações (opcional)</label>
                        <textarea class="form-control" id="observacoesXml" name="observacoes" rows="3" placeholder="Informações adicionais sobre a NF-e..."></textarea>
                    </div>
                </form>
            `;
            
            formularioContainer.style.display = 'block';
            btnReceber.style.display = 'inline-block';
            btnReceber.innerHTML = '<i class="fas fa-upload"></i> Processar XML';
            btnReceber.setAttribute('data-metodo', 'xml');
        }
        
        function abrirMetodoManual() {
            const formularioContent = document.getElementById('formularioContent');
            const formularioContainer = document.getElementById('formularioContainer');
            const btnReceber = document.getElementById('btnReceberNfe');
            
            formularioContent.innerHTML = `
                ` + linkVoltarMetodosNfe() + `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Digitação Manual:</strong> Preencha todos os campos obrigatórios com cuidado.
                </div>
                
                <form id="digitarManualForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="numeroNfe" class="form-label">Número da NF-e</label>
                                <input type="text" class="form-control" id="numeroNfe" name="numero_nfe" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="serieNfe" class="form-label">Série</label>
                                <input type="text" class="form-control" id="serieNfe" name="serie_nfe" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="chaveAcesso" class="form-label">Chave de Acesso (44 dígitos)</label>
                        <input type="text" class="form-control" id="chaveAcesso" name="chave_acesso" maxlength="44" pattern="[0-9]{44}" required>
                        <div class="form-text">Digite a chave de acesso completa da NF-e</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="clienteRemetente" class="form-label">Cliente Remetente</label>
                                <input type="text" class="form-control" id="clienteRemetente" name="cliente_remetente" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="clienteDestinatario" class="form-label">Cliente Destinatário</label>
                                <input type="text" class="form-control" id="clienteDestinatario" name="cliente_destinatario" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="valorCarga" class="form-label">Valor da Carga</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="valorCarga" name="valor_carga" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="pesoCarga" class="form-label">Peso (kg)</label>
                                <input type="number" class="form-control" id="pesoCarga" name="peso_carga" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="volumes" class="form-label">Volumes</label>
                                <input type="number" class="form-control" id="volumes" name="volumes" min="1" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="observacoesManual" class="form-label">Observações (opcional)</label>
                        <textarea class="form-control" id="observacoesManual" name="observacoes" rows="3" placeholder="Informações adicionais sobre a NF-e..."></textarea>
                    </div>
                </form>
            `;
            
            formularioContainer.style.display = 'block';
            btnReceber.style.display = 'inline-block';
            btnReceber.innerHTML = '<i class="fas fa-save"></i> Salvar NF-e';
            btnReceber.setAttribute('data-metodo', 'manual');
        }
        
        function abrirMetodoSefaz() {
            const formularioContent = document.getElementById('formularioContent');
            const formularioContainer = document.getElementById('formularioContainer');
            const btnReceber = document.getElementById('btnReceberNfe');
            
            formularioContent.innerHTML = `
                ` + linkVoltarMetodosNfe() + `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Consulta SEFAZ:</strong> informe a chave (44 dígitos). O sistema consulta a situação e tenta obter o XML quando permitido.
                </div>
                
                <form id="recvNfeSefazForm" class="recv-nfe-sefaz-form" novalidate>
                    <div class="mb-3">
                        <label for="recvNfeChaveSefaz" class="form-label">Chave de Acesso da NF-e</label>
                        <input type="text" class="form-control" id="recvNfeChaveSefaz" name="chave_acesso" maxlength="55" inputmode="numeric" autocomplete="off" placeholder="44 dígitos">
                        <div class="form-text">Cole a chave (44 números). Espaços são ignorados ao consultar.</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="recvNfeValidarCert" name="validar_certificado" checked>
                            <label class="form-check-label" for="recvNfeValidarCert">
                                Validar certificado digital antes da consulta
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="recvNfeObsSefaz" class="form-label">Observações (opcional)</label>
                        <textarea class="form-control" id="recvNfeObsSefaz" name="observacoes" rows="3" placeholder="Informações adicionais sobre a consulta..."></textarea>
                    </div>
                    
                    <div id="recvNfeStatusConsulta" style="display: none;">
                        <div class="alert alert-info">
                            <i class="fas fa-spinner fa-spin"></i>
                            <strong>Consultando SEFAZ...</strong>
                            <div class="text-muted small mt-1">Aguarde — pode levar até 2 minutos.</div>
                            <div id="recvNfeProgressConsulta" class="mt-2">
                                <div class="progress">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="recvNfeSefazResult" class="mt-2" style="display:none" role="status" aria-live="polite"></div>
                </form>
            `;
            
            formularioContainer.style.display = 'block';
            btnReceber.style.display = 'inline-block';
            btnReceber.innerHTML = '<i class="fas fa-search"></i> Consultar SEFAZ';
            btnReceber.setAttribute('data-metodo', 'sefaz');
        }
        
        function processarRecebimentoNFE() {
            const btnReceber = document.getElementById('btnReceberNfe');
            if (!btnReceber) {
                fiscalToast('Botão de ação não encontrado. Recarregue a página.', 'warning');
                return;
            }
            const metodo = btnReceber.getAttribute('data-metodo');
            if (metodo === 'xml') {
                processarUploadXML();
            } else if (metodo === 'manual') {
                processarDigitarManual();
            } else if (metodo === 'sefaz') {
                processarConsultaSefaz();
            } else {
                fiscalToast('Escolha uma opção (XML, manual ou chave na SEFAZ) e use o botão azul do rodapé.', 'warning');
            }
        }
        
        function processarUploadXML() {
            const form = document.getElementById('uploadXmlForm');
            const xmlFile = document.getElementById('xmlFile').files[0];
            
            if (!xmlFile) {
                fiscalToast('Selecione um arquivo XML para continuar.', 'warning');
                return;
            }
            
            if (!xmlFile.name.toLowerCase().endsWith('.xml')) {
                fiscalToast('O arquivo deve ser um XML válido.', 'warning');
                return;
            }
            
            // Validar tamanho do arquivo (máximo 5MB)
            if (xmlFile.size > 5 * 1024 * 1024) {
                fiscalToast('O arquivo XML é muito grande. Tamanho máximo: 5MB.', 'warning');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'receber_nfe_xml');
            formData.append('xml_file', xmlFile);
            formData.append('observacoes', document.getElementById('observacoesXml').value);
            
            // Mostrar loading
            const btnReceber = document.getElementById('btnReceberNfe');
            const originalText = btnReceber.innerHTML;
            btnReceber.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando XML...';
            btnReceber.disabled = true;
            
            fiscalApiFetch('../api/documentos_fiscais_v2.php', {
                method: 'POST',
                body: formData
            })
            .then(res => {
                const data = res.data || {};
                if (res.status === 429 || (data && String(data.code || '') === 'rate_limited')) {
                    fiscalToast(fiscalApiErrorMessage(data, res.status), 'warning');
                    return;
                }
                if (data.success) {
                    fiscalToast(`NF-e recebida com sucesso.\n\nChave: ${data.chave_acesso}\nNúmero: ${data.numero_nfe}\nEmitente: ${data.emitente || 'N/A'}\nValor: R$ ${parseFloat(data.valor_total || 0).toFixed(2)}`, 'success');
                    
                    // Fechar modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('receberNfeModal'));
                    modal.hide();
                    
                    // Atualizar dados
                    carregarNFE();
                } else {
                    fiscalToast(fiscalApiErrorMessage(data, res.status), 'danger');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                fiscalToast('Erro ao processar arquivo XML.', 'danger');
            })
            .finally(() => {
                // Restaurar botão
                btnReceber.innerHTML = originalText;
                btnReceber.disabled = false;
            });
        }
        
        function processarDigitarManual() {
            const form = document.getElementById('digitarManualForm');
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const formData = new FormData(form);
            formData.append('action', 'receber_nfe_manual');
            
            // Mostrar loading
            const btnReceber = document.getElementById('btnReceberNfe');
            const originalText = btnReceber.innerHTML;
            btnReceber.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
            btnReceber.disabled = true;
            
            fiscalApiFetch('../api/documentos_fiscais_v2.php', {
                method: 'POST',
                body: formData
            })
            .then(res => {
                const data = res.data || {};
                if (res.status === 429 || (data && String(data.code || '') === 'rate_limited')) {
                    fiscalToast(fiscalApiErrorMessage(data, res.status), 'warning');
                    return;
                }
                if (data.success) {
                    fiscalToast(`NF-e recebida com sucesso.\n\nNúmero: ${data.numero_nfe}\nChave: ${data.chave_acesso}\nStatus: ${data.status}`, 'success');
                    
                    // Fechar modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('receberNfeModal'));
                    modal.hide();
                    
                    // Atualizar dados
                    carregarNFE();
                } else {
                    fiscalToast(fiscalApiErrorMessage(data, res.status), 'danger');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                fiscalToast('Erro ao salvar NF-e.', 'danger');
            })
            .finally(() => {
                // Restaurar botão
                btnReceber.innerHTML = originalText;
                btnReceber.disabled = false;
            });
        }
        
        function recvNfeSefazClearResultBox() {
            const box = document.getElementById('recvNfeSefazResult');
            if (!box) return;
            box.style.display = 'none';
            box.innerHTML = '';
        }

        /** Mensagem visível no modal (toast às vezes não aparece por z-index / Bootstrap). */
        function recvNfeSefazShowResult(alertClass, title, bodyText) {
            const box = document.getElementById('recvNfeSefazResult');
            if (!box) return;
            var t = String(title || '');
            var b = String(bodyText || '');
            if (b.length > 1200) b = b.slice(0, 1200) + '…';
            box.style.display = 'block';
            box.innerHTML = '<div class="alert ' + alertClass + ' mb-0 shadow-sm">' +
                '<strong>' + escapeHtml(t) + '</strong>' +
                '<div class="small mt-2 mb-0" style="white-space:pre-wrap;word-break:break-word">' + escapeHtml(b) + '</div></div>';
        }

        function fiscalTryToast(message, variant) {
            try {
                if (typeof fiscalToast === 'function') {
                    fiscalToast(message, variant);
                }
            } catch (e) {
                console.warn('fiscalToast:', e, message);
            }
        }

        function processarConsultaSefaz() {
            const form = document.getElementById('recvNfeSefazForm');
            if (!form) {
                fiscalTryToast('Abra de novo o recebimento de NF-e e escolha «Chave na SEFAZ».', 'warning');
                return;
            }
            const inputChave = document.getElementById('recvNfeChaveSefaz');
            if (!inputChave) {
                fiscalTryToast('Formulário de consulta incompleto. Feche e abra o modal.', 'warning');
                return;
            }
            const chaveAcesso = String(inputChave.value || '').replace(/\D/g, '');
            if (chaveAcesso.length !== 44) {
                fiscalTryToast('A chave deve ter 44 dígitos. Cole a chave completa; espaços e traços são ignorados.', 'warning');
                inputChave.focus();
                return;
            }
            inputChave.value = chaveAcesso;

            const chkCert = document.getElementById('recvNfeValidarCert');
            const validarCertificado = chkCert ? chkCert.checked : true;

            recvNfeSefazClearResultBox();

            const statusEl = document.getElementById('recvNfeStatusConsulta');
            if (statusEl) statusEl.style.display = 'block';

            const btnReceber = document.getElementById('btnReceberNfe');
            if (!btnReceber) {
                fiscalTryToast('Erro interno: botão do modal não encontrado.', 'danger');
                if (statusEl) statusEl.style.display = 'none';
                return;
            }
            const originalText = btnReceber.innerHTML;
            btnReceber.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Consultando SEFAZ...';
            btnReceber.disabled = true;

            const progressBar = document.querySelector('#recvNfeProgressConsulta .progress-bar');
            if (progressBar) progressBar.style.width = '40%';

            const formData = new FormData();
            formData.append('action', 'receber_nfe_sefaz');
            formData.append('chave_acesso', chaveAcesso);
            formData.append('validar_certificado', validarCertificado ? '1' : '0');
            const obsSef = document.getElementById('recvNfeObsSefaz');
            formData.append('observacoes', obsSef ? obsSef.value : '');

            const abortCtl = typeof AbortController !== 'undefined' ? new AbortController() : null;
            const abortTimer = abortCtl ? setTimeout(function() { abortCtl.abort(); }, 120000) : null;

            fiscalApiFetch('../api/documentos_fiscais_v2.php', {
                method: 'POST',
                body: formData,
                signal: abortCtl ? abortCtl.signal : undefined
            })
            .then(res => {
                const data = res.data || {};
                if (progressBar) progressBar.style.width = '100%';
                const st = document.getElementById('recvNfeStatusConsulta');
                if (st) st.style.display = 'none';

                if (data.parse_error) {
                    var raw = (typeof data.raw === 'string') ? data.raw.replace(/<[^>]+>/g, ' ').trim() : '';
                    var snippet = raw ? raw.slice(0, 500) : '(corpo vazio)';
                    var peMsg = 'O servidor respondeu com algo que não é JSON (HTTP ' + res.status + '). Pode ser erro PHP, timeout ou página de erro do hosting.\n\nTrecho: ' + snippet;
                    recvNfeSefazShowResult('alert-warning', 'Resposta inválida', peMsg);
                    fiscalTryToast(peMsg, 'danger');
                    return;
                }

                if (res.status === 429 || (data && String(data.code || '') === 'rate_limited')) {
                    var limMsg = fiscalApiErrorMessage(data, res.status);
                    recvNfeSefazShowResult('alert-warning', 'Muitas tentativas', limMsg);
                    fiscalTryToast(limMsg, 'warning');
                    return;
                }

                if (data.success) {
                    var okMsg = 'Chave: ' + (data.chave_acesso || chaveAcesso) +
                        '\nNúmero: ' + (data.numero_nfe != null ? data.numero_nfe : '-') +
                        '\nEmitente: ' + (data.emitente || 'N/A') +
                        '\nValor: R$ ' + parseFloat(data.valor_total || 0).toFixed(2) +
                        '\nProtocolo: ' + (data.protocolo || 'N/A');
                    recvNfeSefazShowResult('alert-success', 'Consulta concluída', okMsg);
                    fiscalTryToast('NF-e consultada na SEFAZ com sucesso.\n\n' + okMsg, 'success');
                    setTimeout(function() {
                        const elModal = document.getElementById('receberNfeModal');
                        if (elModal && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                            const inst = bootstrap.Modal.getInstance(elModal) || bootstrap.Modal.getOrCreateInstance(elModal);
                            inst.hide();
                        }
                        carregarNFE();
                    }, 900);
                    return;
                }

                var errMsg = fiscalApiErrorMessage(data, res.status);
                recvNfeSefazShowResult('alert-danger', 'Não foi possível concluir', errMsg);
                fiscalTryToast(errMsg, 'danger');
            })
            .catch(error => {
                console.error('Erro:', error);
                const st = document.getElementById('recvNfeStatusConsulta');
                if (st) st.style.display = 'none';
                if (error && error.name === 'AbortError') {
                    var abMsg = 'A consulta ultrapassou 2 minutos (cancelada no navegador). O servidor ou a SEFAZ podem estar lentos — tente de novo em instantes.';
                    recvNfeSefazShowResult('alert-warning', 'Tempo esgotado', abMsg);
                    fiscalTryToast(abMsg, 'warning');
                } else {
                    var netMsg = 'Falha de rede ou servidor ao chamar a API. Verifique a conexão e o console (F12).';
                    recvNfeSefazShowResult('alert-danger', 'Erro de comunicação', netMsg + (error && error.message ? '\n' + error.message : ''));
                    fiscalTryToast(netMsg, 'danger');
                }
            })
            .finally(() => {
                if (abortTimer) clearTimeout(abortTimer);
                if (btnReceber) {
                    btnReceber.innerHTML = originalText;
                    btnReceber.disabled = false;
                }
            });
        }
        
        function sincronizarNfeCnpj(desdeInicio) {
            const btn = document.getElementById('sincronizarCnpjBtn');
            const btnZero = document.getElementById('sincronizarCnpjZeroBtn');
            const activeBtn = desdeInicio ? btnZero : btn;
            if (!activeBtn) return;
            const originalText = activeBtn.innerHTML;
            activeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + (desdeInicio ? 'Buscando desde o início...' : 'Buscando NF-e...');
            activeBtn.disabled = true;
            if (btn) btn.disabled = true;
            if (btnZero) btnZero.disabled = true;
            const formData = new FormData();
            formData.append('action', 'sincronizar_nfe_cnpj');
            if (desdeInicio) formData.append('forcar_zero', '1');
            fiscalApiFetch('../api/documentos_fiscais_v2.php', { method: 'POST', body: formData })
                .then(res => {
                    const data = res.data || {};
                    if (res.status === 429 || (data && String(data.code || '') === 'rate_limited')) {
                        fiscalToast(fiscalApiErrorMessage(data, res.status), 'warning');
                        return;
                    }
                    if (data.success) {
                        let texto = data.message || 'Sincronização concluída.';
                        if (data.sefaz && (data.sefaz.cStat != null || data.sefaz.xMotivo)) {
                            texto += '\n\nSEFAZ: cStat=' + (data.sefaz.cStat ?? '-') + ' - ' + (data.sefaz.xMotivo || '');
                            if (data.sefaz.numDocZip != null) texto += '\nDocumentos no lote: ' + data.sefaz.numDocZip;
                            if (data.sefaz.numResNFe != null) texto += ' (resumos NF-e: ' + data.sefaz.numResNFe + ')';
                            if (data.sefaz.cStat === '656') {
                                texto += '\n\n' + (data.sefaz.dica || 'Aguarde cerca de 1 hora. Depois use apenas "Buscar NF-e do meu CNPJ" (sem "desde o início") para buscar só notas novas.');
                                if (data.sefaz.ult_nsu_gravado != null && data.sefaz.ult_nsu_gravado !== '') {
                                    texto += '\n\nO sistema já gravou o NSU da SEFAZ para a próxima consulta. Após 1 hora, clique em "Buscar NF-e do meu CNPJ" (o botão azul, sem "desde o início").';
                                }
                            }
                        }
                        fiscalToast(texto, 'success');
                        carregarNFE();
                    } else {
                        fiscalToast(fiscalApiErrorMessage(data, res.status), 'danger');
                    }
                })
                .catch(err => {
                    console.error(err);
                    fiscalToast('Erro ao sincronizar NF-e.', 'danger');
                })
                .finally(() => {
                    if (btn) { btn.innerHTML = '<i class="fas fa-cloud-download-alt"></i> Buscar NF-e do meu CNPJ'; btn.disabled = false; }
                    if (btnZero) { btnZero.innerHTML = '<i class="fas fa-history"></i> Buscar desde o início'; btnZero.disabled = false; }
                });
        }
        
        // Funções de carregamento de dados
        function carregarNFE() {
            const msg = document.getElementById('nfeList');
            msg.innerHTML = '<div style="color: #17a2b8; background: #d1ecf1; padding: 15px; border-radius: 5px;">' +
                '<strong>🔄 Carregando NF-e...</strong></div>';
            
            fiscalApiFetch('../api/documentos_fiscais_v2.php?action=list&tipo=nfe&limit=100')
                .then(res => {
                    const data = res.data || {};
                    if (res.status === 429 || (data && String(data.code || '') === 'rate_limited')) {
                        nfeDocsRecebidasCache = [];
                        msg.innerHTML = '<div style="color: #856404; background: #fff3cd; padding: 15px; border-radius: 5px;">' +
                            '<strong>Limite de requisições</strong><br>' + escapeHtml(fiscalApiErrorMessage(data, res.status)) + '</div>';
                        atualizarPaginacaoNfe(0);
                        return;
                    }
                    if (data.success) {
                        if (data.documentos && data.documentos.length > 0) {
                            nfeDocsRecebidasCache = data.documentos || [];
                            aplicarFiltrosNfeRecebidas();
                        } else {
                            nfeDocsRecebidasCache = [];
                            msg.innerHTML = '<div style="color: #6c757d; background: #f8f9fa; padding: 15px; border-radius: 5px;">' +
                                '<strong>📭 Nenhuma NF-e recebida</strong><br>Use "Buscar NF-e do meu CNPJ" ou <strong>Receber NF-e</strong> (XML, manual ou chave na SEFAZ).</div>';
                            atualizarPaginacaoNfe(0);
                        }
                    } else {
                        throw new Error(fiscalApiErrorMessage(data, res.status));
                    }
                })
                .catch(error => {
                    console.error('❌ Erro ao carregar NF-e:', error);
                    nfeDocsRecebidasCache = [];
                    msg.innerHTML = '<div style="color: #dc3545; background: #f8d7da; padding: 15px; border-radius: 5px;">' +
                        '<strong>❌ Erro ao carregar dados!</strong><br>' + error.message + '</div>';
                    atualizarPaginacaoNfe(0);
                });
        }

        function filtrarNfeRecebidas(docs) {
            var search = (document.getElementById('searchNfe') || {}).value || '';
            var status = (document.getElementById('statusNfeFilter') || {}).value || '';
            var term = String(search).trim().toLowerCase();
            return (docs || []).filter(function(doc) {
                var st = String(doc.status || '').toLowerCase();
                if (status && st !== status.toLowerCase()) return false;
                if (!term) return true;
                var base = [
                    doc.numero_nfe, doc.serie_nfe, doc.chave_acesso, doc.cliente_razao_social, doc.protocolo_autorizacao, doc.status
                ].join(' ').toLowerCase();
                return base.indexOf(term) !== -1;
            });
        }

        function aplicarFiltrosNfeRecebidas() {
            renderizarNfeRecebidas(filtrarNfeRecebidas(nfeDocsRecebidasCache));
        }

        function renderizarNfeRecebidas(docsFiltrados) {
            var msg = document.getElementById('nfeList');
            if (!msg) return;
            if (!docsFiltrados.length) {
                msg.innerHTML = '<div style="color: #6c757d; background: #f8f9fa; padding: 15px; border-radius: 5px;"><strong>Nenhuma NF-e encontrada</strong><br>Ajuste os filtros para continuar.</div>';
                atualizarPaginacaoNfe(0);
                return;
            }

            var totalPaginas = Math.max(1, Math.ceil(docsFiltrados.length / nfePerPage));
            if (nfePaginaAtual > totalPaginas) nfePaginaAtual = totalPaginas;
            var inicio = (nfePaginaAtual - 1) * nfePerPage;
            var fim = inicio + nfePerPage;
            var docs = docsFiltrados.slice(inicio, fim);

            var html = '<table class="data-table"><thead><tr>';
            html += '<th></th>';
            html += '<th class="col-mod">Mod</th><th class="col-serie">Série</th><th class="col-numero">Número</th><th>Chave de Acesso</th><th>Emissão</th>';
            html += '<th>Cliente/Fornecedor</th><th>Vlr Nota</th><th class="col-recibo">Recibo</th><th>Situação</th>';
            html += '<th class="col-acoes">Ações</th></tr></thead><tbody>';

            docs.forEach(function(doc) {
                var dataEmissao = doc.data_emissao ? new Date(doc.data_emissao).toLocaleDateString('pt-BR') : '-';
                var numero = String(doc.numero_nfe || '').padStart(9, '0');
                var serie = doc.serie_nfe || '-';
                var vlr = parseFloat(doc.valor_total || 0).toFixed(2).replace('.', ',');
                var recibo = doc.protocolo_autorizacao || '0';
                var situacao = doc.status === 'consultada_sefaz' ? 'Consultada SEFAZ' :
                    doc.status === 'entregue' ? 'Entregue' : doc.status === 'em_transporte' ? 'Em Transporte' :
                    doc.status === 'cancelada' ? 'Cancelada' : doc.status === 'autorizado' || doc.status === 'autorizada' ? 'Autorizada' :
                    (doc.status ? doc.status.replace(/_/g, ' ') : 'Recebida');
                var situacaoClass = (doc.status || 'recebida').replace(/_/g, '-');
                html += '<tr>';
                html += '<td><input type="checkbox" class="nfe-check" data-id="' + doc.id + '"></td>';
                html += '<td class="col-mod">55</td>';
                html += '<td class="col-serie">' + escapeHtml(serie) + '</td>';
                html += '<td class="col-numero">' + escapeHtml(numero) + '</td>';
                html += '<td class="col-chave" title="' + escapeHtml(doc.chave_acesso || '') + '">' + escapeHtml(doc.chave_acesso || '-') + '</td>';
                html += '<td>' + dataEmissao + '</td>';
                html += '<td>' + escapeHtml(doc.cliente_razao_social || 'Cliente') + '</td>';
                html += '<td class="col-vlr">R$ ' + vlr + '</td>';
                html += '<td class="col-recibo">' + escapeHtml(recibo) + '</td>';
                html += '<td class="situacao-' + situacaoClass + '">' + escapeHtml(situacao) + '</td>';
                html += '<td class="actions col-acoes">';
                html += '<a class="btn-icon" href="#" onclick="abrirModalNfe(' + doc.id + '); return false;" title="Visualizar"><i class="fas fa-search"></i></a>';
                html += '<a class="btn-icon" href="#" onclick="downloadNfeXml(' + doc.id + '); return false;" title="Download XML"><i class="fas fa-file-code"></i></a>';
                html += '<a class="btn-icon" href="#" onclick="downloadNfePdf(' + doc.id + '); return false;" title="Download PDF"><i class="fas fa-file-pdf"></i></a>';
                html += '</td></tr>';
            });
            html += '</tbody></table>';
            msg.innerHTML = html;
            atualizarPaginacaoNfe(docsFiltrados.length);
        }

        function atualizarPaginacaoNfe(totalItens) {
            var container = document.getElementById('paginationNfeContainer');
            if (!container) return;
            if (!totalItens) {
                container.innerHTML = '';
                return;
            }
            var totalPaginas = Math.max(1, Math.ceil(totalItens / nfePerPage));
            var inicio = ((nfePaginaAtual - 1) * nfePerPage) + 1;
            var fim = Math.min(totalItens, nfePaginaAtual * nfePerPage);
            var prevDisabled = nfePaginaAtual <= 1;
            var nextDisabled = nfePaginaAtual >= totalPaginas;
            container.innerHTML =
                '<button type="button" class="pagination-btn' + (prevDisabled ? ' disabled' : '') + '" id="nfePagePrevBtn">Anterior</button>' +
                '<span class="pagination-info">' + inicio + '-' + fim + ' de ' + totalItens + ' • Página ' + nfePaginaAtual + '/' + totalPaginas + '</span>' +
                '<button type="button" class="pagination-btn' + (nextDisabled ? ' disabled' : '') + '" id="nfePageNextBtn">Próxima</button>';
            var prevBtn = document.getElementById('nfePagePrevBtn');
            var nextBtn = document.getElementById('nfePageNextBtn');
            if (prevBtn && !prevDisabled) {
                prevBtn.addEventListener('click', function() {
                    nfePaginaAtual -= 1;
                    aplicarFiltrosNfeRecebidas();
                });
            }
            if (nextBtn && !nextDisabled) {
                nextBtn.addEventListener('click', function() {
                    nfePaginaAtual += 1;
                    aplicarFiltrosNfeRecebidas();
                });
            }
        }

        function escapeHtml(s) {
            if (s == null) return '';
            const div = document.createElement('div');
            div.textContent = s;
            return div.innerHTML;
        }
        
        function downloadNfeXml(id) {
            // GET evita bloqueio de WAF/mod_security comum em POST em produção; cookie de sessão segue na mesma origem
            var u = '../api/download_nfe_xml.php?id=' + encodeURIComponent(id) + '&_=' + Date.now();
            window.open(u, '_blank', 'noopener,noreferrer');
        }
        
        function downloadNfePdf(id) {
            var u = '../api/download_nfe_pdf.php?id=' + encodeURIComponent(id) + '&_=' + Date.now();
            window.open(u, '_blank', 'noopener,noreferrer');
        }
        
        function abrirModalNfe(id) {
            const modal = new bootstrap.Modal(document.getElementById('modalVisualizarNfe'));
            const body = document.getElementById('modalVisualizarNfeBody');
            const title = document.getElementById('modalVisualizarNfeLabel');
            body.innerHTML = '<p class="text-muted">Carregando...</p>';
            modal.show();
            
            fiscalApiFetch('../api/documentos_fiscais_v2.php?action=get&tipo=nfe&id=' + id)
                .then(res => {
                    const data = res.data || {};
                    if (res.status === 429 || (data && String(data.code || '') === 'rate_limited')) {
                        body.innerHTML = '<p class="text-warning">' + escapeHtml(fiscalApiErrorMessage(data, res.status)) + '</p>';
                        return;
                    }
                    if (!data.success || !data.documento) {
                        body.innerHTML = '<p class="text-danger">NF-e não encontrada.</p>';
                        return;
                    }
                    const d = data.documento;
                    const dataEmissao = d.data_emissao ? new Date(d.data_emissao).toLocaleDateString('pt-BR') : '-';
                    const vlr = parseFloat(d.valor_total || 0).toFixed(2).replace('.', ',');
                    const situacao = d.status === 'consultada_sefaz' ? 'Consultada SEFAZ' : 
                        d.status === 'entregue' ? 'Entregue' : d.status === 'em_transporte' ? 'Em Transporte' : 
                        d.status === 'cancelada' ? 'Cancelada' : d.status === 'autorizado' || d.status === 'autorizada' ? 'Autorizada' :
                        (d.status ? d.status.replace(/_/g, ' ') : 'Recebida');
                    title.textContent = 'NF-e ' + (d.numero_nfe || id);
                    body.innerHTML = '<dl class="row mb-0">' +
                        '<dt class="col-sm-4">Número</dt><dd class="col-sm-8">' + escapeHtml(d.numero_nfe || '-') + '</dd>' +
                        '<dt class="col-sm-4">Série</dt><dd class="col-sm-8">' + escapeHtml(d.serie_nfe || '-') + '</dd>' +
                        '<dt class="col-sm-4">Chave de acesso</dt><dd class="col-sm-8"><code class="small">' + escapeHtml(d.chave_acesso || '-') + '</code></dd>' +
                        '<dt class="col-sm-4">Data de emissão</dt><dd class="col-sm-8">' + dataEmissao + '</dd>' +
                        '<dt class="col-sm-4">Emitente / Cliente</dt><dd class="col-sm-8">' + escapeHtml(d.cliente_razao_social || 'Cliente') + '</dd>' +
                        '<dt class="col-sm-4">Valor total</dt><dd class="col-sm-8">R$ ' + vlr + '</dd>' +
                        '<dt class="col-sm-4">Status</dt><dd class="col-sm-8">' + escapeHtml(situacao) + '</dd>' +
                        '<dt class="col-sm-4">Protocolo</dt><dd class="col-sm-8">' + escapeHtml(d.protocolo_autorizacao || '-') + '</dd>' +
                        '</dl>';
                })
                .catch(err => {
                    body.innerHTML = '<p class="text-danger">Erro ao carregar dados.</p>';
                });
        }
        
        // Funções auxiliares
        function visualizarNFE(id) {
            abrirModalNfe(id);
        }
        
    </script>
    
    <!-- Footer -->
    <?php include '../../includes/footer.php'; ?>
</body>
</html>
