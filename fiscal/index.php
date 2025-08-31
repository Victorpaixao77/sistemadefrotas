<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Check if user is logged in, if not redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: /sistema-frotas/login.php");
    exit;
}

// Set page title
$page_title = "Sistema Fiscal";
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Fiscal - Sistema de Frotas</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <link rel="stylesheet" href="assets/css/fiscal.css">
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1><i class="fas fa-file-invoice-dollar"></i> Sistema Fiscal</h1>
                    <p>Gestão completa de documentos fiscais eletrônicos</p>
                </div>
                <div class="header-right">
                    <button id="configBtn" class="btn-primary" title="Configurações">
                        <i class="fas fa-cog"></i> Configurações
                    </button>
                    <button id="helpBtn" class="btn-help" title="Ajuda">
                        <i class="fas fa-question-circle"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- KPI Cards Row -->
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>NF-e Clientes</h3>
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value" id="totalNFe">0</span>
                            <span class="metric-subtitle">Total processadas</span>
                        </div>
                        <div class="metric-details">
                            <span class="detail-item">
                                <i class="fas fa-clock"></i>
                                <span id="nfePendentes">0</span> pendentes
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>CT-e</h3>
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value" id="totalCTe">0</span>
                            <span class="metric-subtitle">Total emitidos</span>
                        </div>
                        <div class="metric-details">
                            <span class="detail-item">
                                <i class="fas fa-check-circle"></i>
                                <span id="cteAutorizados">0</span> autorizados
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>MDF-e</h3>
                        <i class="fas fa-route"></i>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value" id="totalMDFe">0</span>
                            <span class="metric-subtitle">Total manifestos</span>
                        </div>
                        <div class="metric-details">
                            <span class="detail-item">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span id="mdfePendentes">0</span> pendentes
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Status SEFAZ</h3>
                        <i class="fas fa-server"></i>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value" id="statusSefaz">-</span>
                            <span class="metric-subtitle">Conectividade</span>
                        </div>
                        <div class="metric-details">
                            <span class="detail-item">
                                <i class="fas fa-sync-alt"></i>
                                <span id="ultimaSincronizacao">-</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <h3>Ações Rápidas</h3>
                <div class="actions-grid">
                    <button class="action-btn" onclick="showImportarNFEModal()">
                        <i class="fas fa-upload"></i>
                        <span>Importar NF-e</span>
                    </button>
                    <button class="action-btn" onclick="showEmitirCTeModal()">
                        <i class="fas fa-plus"></i>
                        <span>Emitir CT-e</span>
                    </button>
                    <button class="action-btn" onclick="showEmitirMDFeModal()">
                        <i class="fas fa-file-alt"></i>
                        <span>Emitir MDF-e</span>
                    </button>
                    <button class="action-btn" onclick="showConsultarStatusModal()">
                        <i class="fas fa-search"></i>
                        <span>Consultar Status</span>
                    </button>
                </div>
            </div>
            
            <!-- Recent Documents -->
            <div class="recent-documents">
                <h3>Documentos Recentes</h3>
                <div class="documents-tabs">
                    <button class="tab-btn active" data-tab="nfe">NF-e</button>
                    <button class="tab-btn" data-tab="cte">CT-e</button>
                    <button class="tab-btn" data-tab="mdfe">MDF-e</button>
                </div>
                <div class="documents-content">
                    <div id="tab-nfe" class="tab-content active">
                        <div class="documents-list" id="nfeList">
                            <!-- NF-e serão carregadas via JavaScript -->
                        </div>
                    </div>
                    <div id="tab-cte" class="tab-content">
                        <div class="documents-list" id="cteList">
                            <!-- CT-e serão carregados via JavaScript -->
                        </div>
                    </div>
                    <div id="tab-mdfe" class="tab-content">
                        <div class="documents-list" id="mdfeList">
                            <!-- MDF-e serão carregados via JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modals -->
    <?php include 'components/importar_nfe_modal.php'; ?>
    <?php include 'components/emitir_cte_modal.php'; ?>
    <?php include 'components/emitir_mdfe_modal.php'; ?>
    <?php include 'components/consultar_status_modal.php'; ?>
    <?php include 'components/config_fiscal_modal.php'; ?>
    <?php include 'components/help_fiscal_modal.php'; ?>
    
    <!-- JavaScript Files -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="assets/js/fiscal.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize fiscal system
        initializeFiscalSystem();
        
        // Setup tabs
        setupDocumentTabs();
        
        // Setup modals
        setupModals();
    });
    </script>
</body>
</html>
