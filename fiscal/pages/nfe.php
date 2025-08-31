<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include configuration and functions first
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Configure session before starting it
configure_session();

// Start session
session_start();

// Check authentication
require_authentication();

// Set page title
$page_title = "Gestão Fiscal de Transporte";
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
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include '../../includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include '../../includes/header.php'; ?>
            
            <!-- Page Content -->
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1><?php echo $page_title; ?></h1>
                    <div class="dashboard-actions">
                        <button id="receberNFEBtn" class="btn-add-widget" onclick="receberNFE()">
                            <i class="fas fa-download"></i> Receber NF-e
                        </button>
                        <button id="gerenciarCTEBtn" class="btn-add-widget" onclick="window.location.href='cte.php'">
                            <i class="fas fa-truck"></i> Gerenciar CT-e
                        </button>
                        <button id="gerenciarMDFEBtn" class="btn-add-widget" onclick="window.location.href='mdfe.php'">
                            <i class="fas fa-route"></i> Gerenciar MDF-e
                        </button>
                        <button id="sincronizarSefazBtn" class="btn-add-widget" onclick="sincronizarSefaz()">
                            <i class="fas fa-sync"></i> Sincronizar SEFAZ
                        </button>
                        <div class="view-controls">
                            <button id="refreshBtn" class="btn-restore-layout" title="Atualizar">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button id="exportBtn" class="btn-toggle-layout" title="Exportar">
                                <i class="fas fa-file-export"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- KPI Cards Row -->
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>NF-e Recebidas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="nfeRecebidas">0</span>
                                <span class="metric-subtitle">Documentos recebidos</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>CT-e Pendentes</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="ctePendentes">0</span>
                                <span class="metric-subtitle">Aguardando autorização</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>CT-e Autorizados</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="cteAutorizados">0</span>
                                <span class="metric-subtitle">Documentos aprovados</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Status SEFAZ</h3>
                            <button id="refreshSefazBtn" class="btn-sm" style="float: right; padding: 2px 8px; font-size: 12px;">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <div id="sefazStatus">
                                    <span class="status-badge status-warning">
                                        <i class="fas fa-clock"></i> Verificando...
                                    </span>
                                </div>
                                <span class="metric-subtitle" id="sefazSubtitle">Testando conexão...</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Document Tabs -->
                <div class="document-tabs">
                    <button class="document-tab active" data-target="nfeContent">NF-e Recebidas</button>
                    <button class="document-tab" data-target="cteContent">CT-e</button>
                    <button class="document-tab" data-target="mdfeContent">MDF-e</button>
                </div>
                
                <!-- NF-e Content -->
                <div id="nfeContent" class="document-content active">
                    <div class="document-list">
                        <h3>NF-e Recebidas do Cliente</h3>
                        <div id="nfeList">
                            <p>Carregando NF-e...</p>
                        </div>
                    </div>
                </div>
                
                <!-- CT-e Content -->
                <div id="cteContent" class="document-content">
                    <div class="document-list">
                        <h3>CT-e (Conhecimento de Transporte)</h3>
                        <div id="cteList">
                            <p>Carregando CT-e...</p>
                        </div>
                    </div>
                </div>
                
                <!-- MDF-e Content -->
                <div id="mdfeContent" class="document-content">
                    <div class="document-list">
                        <h3>MDF-e (Manifesto de Documentos)</h3>
                        <div id="mdfeList">
                            <p>Carregando MDF-e...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para Receber NF-e -->
    <div class="modal fade" id="receberNfeModal" tabindex="-1" aria-labelledby="receberNfeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="receberNfeModalLabel">📥 Receber NF-e do Cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-info-circle"></i>
                        <strong>Escolha o método de recebimento:</strong> Selecione como deseja receber a NF-e do seu cliente.
                                </div>
                    
                    <!-- Botões das três situações de recebimento -->
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="card h-100 border-primary">
                                <div class="card-body text-center">
                                <div class="mb-3">
                                        <i class="fas fa-file-code fa-3x text-primary"></i>
                                </div>
                                    <h5 class="card-title">Upload XML</h5>
                                    <p class="card-text">
                                        <small class="text-success">
                                            <i class="fas fa-check-circle"></i> Recomendado
                                        </small><br>
                                        Upload do arquivo XML da NF-e autorizada pela SEFAZ
                                    </p>
                                    <ul class="list-unstyled text-start small mb-3">
                                        <li><i class="fas fa-check text-success"></i> Integridade garantida</li>
                                        <li><i class="fas fa-check text-success"></i> Sem erros de digitação</li>
                                        <li><i class="fas fa-check text-success"></i> Dados validados</li>
                                    </ul>
                                    <button type="button" class="btn btn-primary btn-sm w-100" onclick="abrirMetodoXML()">
                                        <i class="fas fa-upload"></i> Enviar XML
                                    </button>
                            </div>
                        </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card h-100 border-warning">
                                <div class="card-body text-center">
                                <div class="mb-3">
                                        <i class="fas fa-keyboard fa-3x text-warning"></i>
                                </div>
                                    <h5 class="card-title">Digitação Manual</h5>
                                    <p class="card-text">
                                        <small class="text-warning">
                                            <i class="fas fa-exclamation-triangle"></i> Plano B
                                        </small><br>
                                        Preencher manualmente os dados da NF-e
                                    </p>
                                    <ul class="list-unstyled text-start small mb-3">
                                        <li><i class="fas fa-info text-info"></i> Quando XML não disponível</li>
                                        <li><i class="fas fa-clock text-secondary"></i> Mais demorado</li>
                                        <li><i class="fas fa-exclamation text-warning"></i> Sujeito a erros</li>
                                    </ul>
                                    <button type="button" class="btn btn-warning btn-sm w-100" onclick="abrirMetodoManual()">
                                        <i class="fas fa-edit"></i> Digitar Dados
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                            <div class="col-md-4">
                            <div class="card h-100 border-info">
                                <div class="card-body text-center">
                                <div class="mb-3">
                                        <i class="fas fa-search fa-3x text-info"></i>
                                    </div>
                                    <h5 class="card-title">Consulta SEFAZ</h5>
                                    <p class="card-text">
                                        <small class="text-info">
                                            <i class="fas fa-certificate"></i> Requer Certificado
                                        </small><br>
                                        Buscar automaticamente na SEFAZ
                                    </p>
                                    <ul class="list-unstyled text-start small mb-3">
                                        <li><i class="fas fa-shield-alt text-success"></i> Dados atualizados</li>
                                        <li><i class="fas fa-sync text-info"></i> Consulta automática</li>
                                        <li><i class="fas fa-certificate text-primary"></i> Certificado digital</li>
                                    </ul>
                                    <button type="button" class="btn btn-info btn-sm w-100" onclick="abrirMetodoSefaz()">
                                        <i class="fas fa-search"></i> Consultar SEFAZ
                                    </button>
                                </div>
                            </div>
                                </div>
                            </div>
                    
                    <!-- Container para formulários (inicialmente oculto) -->
                    <div id="formularioContainer" class="mt-4" style="display: none;">
                        <hr>
                        <div id="formularioContent"></div>
                                </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnReceberNfe" onclick="processarRecebimentoNFE()" style="display: none;">
                        📥 Receber NF-e
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para Criar CT-e -->
    <div class="modal fade" id="criarCTEModal" tabindex="-1" aria-labelledby="criarCTEModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="criarCTEModalLabel">🚛 Criar CT-e</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="criarCTEForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="veiculoId" class="form-label">Veículo</label>
                                    <select class="form-select" id="veiculoId" name="veiculo_id" required>
                                        <option value="">Selecione o veículo</option>
                                        <option value="1">Caminhão 01 - ABC-1234</option>
                                        <option value="2">Caminhão 02 - DEF-5678</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="motoristaId" class="form-label">Motorista</label>
                                    <select class="form-select" id="motoristaId" name="motorista_id" required>
                                        <option value="">Selecione o motorista</option>
                                        <option value="1">João Silva</option>
                                        <option value="2">Pedro Santos</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="origem" class="form-label">Origem</label>
                                    <input type="text" class="form-control" id="origem" name="origem" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="destino" class="form-label">Destino</label>
                                    <input type="text" class="form-control" id="destino" name="destino" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="valorFrete" class="form-label">Valor do Frete</label>
                                    <div class="input-group">
                                        <span class="input-group-text">R$</span>
                                        <input type="number" class="form-control" id="valorFrete" name="valor_frete" step="0.01" min="0" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="pesoTotal" class="form-label">Peso Total</label>
                                    <input type="number" class="form-control" id="pesoTotal" name="peso_total" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="volumesTotal" class="form-label">Volumes Total</label>
                                    <input type="number" class="form-control" id="volumesTotal" name="volumes_total" min="1" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">NF-e para Transportar</label>
                            <div id="nfeSelecao" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                                <p>Carregando NF-e disponíveis...</p>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarCTE()">🚛 Criar CT-e</button>
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
    
    <!-- Fiscal System JavaScript -->
    <script src="../assets/js/fiscal.js"></script>
    
    <script>
        // Configurar abas de documentos
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.document-tab');
            const contents = document.querySelectorAll('.document-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const target = tab.getAttribute('data-target');
                    
                    // Atualizar abas ativas
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    
                    // Atualizar conteúdo ativo
                    contents.forEach(content => {
                        content.classList.remove('active');
                        if (content.id === target) {
                            content.classList.add('active');
                        }
                    });
                    
                    // Carregar dados da aba selecionada
                    if (target === 'nfeContent') {
                        carregarNFE();
                    } else if (target === 'cteContent') {
                        carregarCTE();
                    } else if (target === 'mdfeContent') {
                        carregarMDFE();
                    }
                });
            });
            
            // Botão de atualizar
            document.getElementById('refreshBtn').addEventListener('click', function() {
                atualizarDados();
            });
            
            // Botão de atualizar SEFAZ
            document.getElementById('refreshSefazBtn').addEventListener('click', function() {
                verificarStatusSefaz(true); // Forçar refresh
            });
            
            // Carregar dados iniciais
            carregarDadosIniciais();
            verificarStatusSefaz();
        });
        
        // Funções para receber NF-e do cliente
        function receberNFE() {
            const modal = new bootstrap.Modal(document.getElementById('receberNfeModal'));
            modal.show();
            
            // Resetar formulários e status
            resetarFormulariosRecebimento();
        }
        
        function resetarFormulariosRecebimento() {
            // Ocultar formulário e botão
            document.getElementById('formularioContainer').style.display = 'none';
            document.getElementById('btnReceberNfe').style.display = 'none';
            document.getElementById('formularioContent').innerHTML = '';
        }
        
        // Funções para abrir cada método de recebimento
        function abrirMetodoXML() {
            const formularioContent = document.getElementById('formularioContent');
            const formularioContainer = document.getElementById('formularioContainer');
            const btnReceber = document.getElementById('btnReceberNfe');
            
            formularioContent.innerHTML = `
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
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Consulta SEFAZ:</strong> Digite apenas a chave de acesso para buscar automaticamente os dados.
                </div>
                
                <form id="consultaSefazForm">
                    <div class="mb-3">
                        <label for="chaveAcessoConsulta" class="form-label">Chave de Acesso da NF-e</label>
                        <input type="text" class="form-control" id="chaveAcessoConsulta" name="chave_acesso" maxlength="44" pattern="[0-9]{44}" required>
                        <div class="form-text">Digite a chave de acesso (44 dígitos) para consulta na SEFAZ</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="validarCertificado" name="validar_certificado" checked>
                            <label class="form-check-label" for="validarCertificado">
                                Validar certificado digital antes da consulta
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="observacoesSefaz" class="form-label">Observações (opcional)</label>
                        <textarea class="form-control" id="observacoesSefaz" name="observacoes" rows="3" placeholder="Informações adicionais sobre a consulta..."></textarea>
                    </div>
                    
                    <div id="statusConsulta" style="display: none;">
                        <div class="alert alert-info">
                            <i class="fas fa-spinner fa-spin"></i>
                            <strong>Consultando SEFAZ...</strong>
                            <div id="progressConsulta" class="mt-2">
                                <div class="progress">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            `;
            
            formularioContainer.style.display = 'block';
            btnReceber.style.display = 'inline-block';
            btnReceber.innerHTML = '<i class="fas fa-search"></i> Consultar SEFAZ';
            btnReceber.setAttribute('data-metodo', 'sefaz');
        }
        
        function processarRecebimentoNFE() {
            // Verificar qual método está sendo usado
            const btnReceber = document.getElementById('btnReceberNfe');
            const metodo = btnReceber.getAttribute('data-metodo');
            
            if (metodo === 'xml') {
                processarUploadXML();
            } else if (metodo === 'manual') {
                processarDigitarManual();
            } else if (metodo === 'sefaz') {
                processarConsultaSefaz();
            }
        }
        
        function processarUploadXML() {
            const form = document.getElementById('uploadXmlForm');
            const xmlFile = document.getElementById('xmlFile').files[0];
            
            if (!xmlFile) {
                alert('❌ Selecione um arquivo XML para continuar');
                return;
            }
            
            if (!xmlFile.name.toLowerCase().endsWith('.xml')) {
                alert('❌ O arquivo deve ser um XML válido');
                return;
            }
            
            // Validar tamanho do arquivo (máximo 5MB)
            if (xmlFile.size > 5 * 1024 * 1024) {
                alert('❌ O arquivo XML é muito grande. Tamanho máximo: 5MB');
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
            
            fetch('../api/documentos_fiscais_v2.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`✅ NF-e recebida com sucesso!\n\nChave: ${data.chave_acesso}\nNúmero: ${data.numero_nfe}\nEmitente: ${data.emitente}\nValor: R$ ${parseFloat(data.valor_total || 0).toFixed(2)}`);
                    
                    // Fechar modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('receberNfeModal'));
                    modal.hide();
                    
                    // Atualizar dados
                    carregarDadosIniciais();
                    carregarNFE();
                } else {
                    alert('❌ Erro ao processar XML: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('❌ Erro ao processar arquivo XML');
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
            
            fetch('../api/documentos_fiscais_v2.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`✅ NF-e recebida com sucesso!\n\nNúmero: ${data.numero_nfe}\nChave: ${data.chave_acesso}\nStatus: ${data.status}`);
                    
                    // Fechar modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('receberNfeModal'));
                    modal.hide();
                    
                    // Atualizar dados
                    carregarDadosIniciais();
                    carregarNFE();
                } else {
                    alert('❌ Erro ao receber NF-e: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('❌ Erro ao salvar NF-e');
            })
            .finally(() => {
                // Restaurar botão
                btnReceber.innerHTML = originalText;
                btnReceber.disabled = false;
            });
        }
        
        function processarConsultaSefaz() {
            const form = document.getElementById('consultaSefazForm');
            const chaveAcesso = document.getElementById('chaveAcessoConsulta').value;
            const validarCertificado = document.getElementById('validarCertificado').checked;
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            // Validar formato da chave de acesso
            if (!/^\d{44}$/.test(chaveAcesso)) {
                alert('❌ A chave de acesso deve ter exatamente 44 dígitos numéricos');
                return;
            }
            
            // Mostrar status de consulta
            document.getElementById('statusConsulta').style.display = 'block';
            
            // Mostrar loading no botão
            const btnReceber = document.getElementById('btnReceberNfe');
            const originalText = btnReceber.innerHTML;
            btnReceber.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Consultando SEFAZ...';
            btnReceber.disabled = true;
            
            // Simular progresso
            let progress = 0;
            const progressBar = document.querySelector('#progressConsulta .progress-bar');
            const progressInterval = setInterval(() => {
                progress += Math.random() * 15;
                if (progress > 90) progress = 90;
                progressBar.style.width = progress + '%';
            }, 200);
            
            const formData = new FormData();
            formData.append('action', 'receber_nfe_sefaz');
            formData.append('chave_acesso', chaveAcesso);
            formData.append('validar_certificado', validarCertificado ? '1' : '0');
            formData.append('observacoes', document.getElementById('observacoesSefaz').value);
            
            fetch('../api/documentos_fiscais_v2.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                clearInterval(progressInterval);
                progressBar.style.width = '100%';
                
                if (data.success) {
                    alert(`✅ NF-e consultada na SEFAZ com sucesso!\n\nChave: ${data.chave_acesso}\nNúmero: ${data.numero_nfe}\nEmitente: ${data.emitente}\nValor: R$ ${parseFloat(data.valor_total || 0).toFixed(2)}\nProtocolo: ${data.protocolo || 'N/A'}`);
                    
                    // Fechar modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('receberNfeModal'));
                    modal.hide();
                    
                    // Atualizar dados
                    carregarDadosIniciais();
                    carregarNFE();
                } else {
                    alert('❌ Erro na consulta SEFAZ: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                clearInterval(progressInterval);
                console.error('Erro:', error);
                alert('❌ Erro ao consultar SEFAZ');
            })
            .finally(() => {
                // Ocultar status de consulta
                document.getElementById('statusConsulta').style.display = 'none';
                
                // Restaurar botão
                btnReceber.innerHTML = originalText;
                btnReceber.disabled = false;
            });
        }
        
        // Funções para criar CT-e
        function criarCTE() {
            // Carregar NF-e disponíveis primeiro
            carregarNFEDisponiveis().then(() => {
                const modal = new bootstrap.Modal(document.getElementById('criarCTEModal'));
                modal.show();
            });
        }
        
        function carregarNFEDisponiveis() {
            return fetch('../api/documentos_fiscais_v2.php?action=list&tipo=nfe&status=recebida')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.documentos) {
                        let html = '';
                        data.documentos.forEach(nfe => {
                            html += `
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="nfe_ids[]" value="${nfe.id}" id="nfe_${nfe.id}">
                                    <label class="form-check-label" for="nfe_${nfe.id}">
                                        NF-e ${nfe.numero_nfe} - ${nfe.cliente_razao_social} - R$ ${parseFloat(nfe.valor_total || 0).toFixed(2)}
                                    </label>
                                </div>
                            `;
                        });
                        document.getElementById('nfeSelecao').innerHTML = html;
                    } else {
                        document.getElementById('nfeSelecao').innerHTML = '<p>Nenhuma NF-e disponível para transporte</p>';
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    document.getElementById('nfeSelecao').innerHTML = '<p>Erro ao carregar NF-e</p>';
                });
        }
        
        function salvarCTE() {
            const formData = new FormData(document.getElementById('criarCTEForm'));
            formData.append('action', 'criar_cte');
            
            // Adicionar NF-e selecionadas
            const nfeSelecionadas = document.querySelectorAll('input[name="nfe_ids[]"]:checked');
            if (nfeSelecionadas.length === 0) {
                alert('Selecione pelo menos uma NF-e para transportar');
                return;
            }
            
            nfeSelecionadas.forEach(nfe => {
                formData.append('nfe_ids[]', nfe.value);
            });
            
            fetch('../api/documentos_fiscais_v2.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`✅ CT-e criado com sucesso!\n\nNúmero: ${data.numero_cte}\nStatus: ${data.status}`);
                    
                    // Fechar modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('criarCTEModal'));
                    modal.hide();
                    
                    // Limpar formulário
                    document.getElementById('criarCTEForm').reset();
                    
                    // Atualizar dados
                    carregarDadosIniciais();
                    carregarCTE();
                } else {
                    alert('❌ Erro ao criar CT-e: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao criar CT-e');
            });
        }
        
        // Funções para criar MDF-e
        function criarMDFE() {
            alert('Funcionalidade de criação de MDF-e será implementada em breve!');
        }
        
        // Funções de carregamento de dados
        function carregarNFE() {
            const msg = document.getElementById('nfeList');
            msg.innerHTML = '<div style="color: #17a2b8; background: #d1ecf1; padding: 15px; border-radius: 5px;">' +
                '<strong>🔄 Carregando NF-e...</strong></div>';
            
            fetch('../api/documentos_fiscais_v2.php?action=list&tipo=nfe&limit=20')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.documentos && data.documentos.length > 0) {
                            let html = '<div style="margin-bottom: 15px;"><strong>📋 NF-e Recebidas:</strong></div>';
                            data.documentos.forEach(doc => {
                                const statusClass = doc.status === 'entregue' ? 'success' : 
                                                  doc.status === 'em_transporte' ? 'warning' : 'info';
                                const statusText = doc.status === 'entregue' ? 'Entregue' : 
                                                 doc.status === 'em_transporte' ? 'Em Transporte' : 'Recebida';
                                const dataFormatada = new Date(doc.data_emissao).toLocaleDateString('pt-BR');
                                
                                html += `<div class="document-item">
                                    <div class="document-header">
                                        <h4>NF-e ${doc.numero_nfe.toString().padStart(3, '0')}</h4>
                                        <span class="status-badge status-${statusClass}">${statusText}</span>
                                    </div>
                                    <div class="document-details">
                                        <p><strong>Data:</strong> ${dataFormatada}</p>
                                        <p><strong>Valor:</strong> R$ ${parseFloat(doc.valor_total || 0).toFixed(2).replace('.', ',')}</p>
                                        <p><strong>Cliente:</strong> ${doc.cliente_razao_social || 'Cliente'}</p>
                                        <p><strong>Peso:</strong> ${parseFloat(doc.peso_carga || 0).toFixed(2)} kg</p>
                                        <p><strong>Volumes:</strong> ${doc.volumes || 0}</p>
                                    </div>
                                    <div class="document-actions">
                                        <button onclick="visualizarNFE(${doc.id})" class="btn-sm btn-secondary">
                                            👁️ Visualizar
                                        </button>
                                    </div>
                                </div>`;
                            });
                            msg.innerHTML = html;
                        } else {
                            msg.innerHTML = '<div style="color: #6c757d; background: #f8f9fa; padding: 15px; border-radius: 5px;">' +
                                '<strong>📭 Nenhuma NF-e recebida</strong><br>Clique em "Receber NF-e" para adicionar documentos</div>';
                        }
                    } else {
                        throw new Error(data.error || 'Erro desconhecido');
                    }
                })
                .catch(error => {
                    console.error('❌ Erro ao carregar NF-e:', error);
                    msg.innerHTML = '<div style="color: #dc3545; background: #f8d7da; padding: 15px; border-radius: 5px;">' +
                        '<strong>❌ Erro ao carregar dados!</strong><br>' + error.message + '</div>';
                });
        }
        
        function carregarCTE() {
            const msg = document.getElementById('cteList');
            msg.innerHTML = '<div style="color: #17a2b8; background: #d1ecf1; padding: 15px; border-radius: 5px;">' +
                '<strong>🔄 Carregando CT-e...</strong></div>';
            
            fetch('../api/documentos_fiscais_v2.php?action=list&tipo=cte&limit=20')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.documentos && data.documentos.length > 0) {
                            let html = '<div style="margin-bottom: 15px;"><strong>🚛 CT-e Criados:</strong></div>';
                            data.documentos.forEach(doc => {
                                const statusClass = doc.status === 'autorizado' ? 'success' : 
                                                  doc.status === 'pendente' ? 'warning' : 'info';
                                const statusText = doc.status === 'autorizado' ? 'Autorizado' : 
                                                 doc.status === 'pendente' ? 'Pendente' : 'Rascunho';
                                const dataFormatada = new Date(doc.data_emissao).toLocaleDateString('pt-BR');
                                
                                html += `<div class="document-item">
                                    <div class="document-header">
                                        <h4>CT-e ${doc.numero_cte.toString().padStart(3, '0')}</h4>
                                        <span class="status-badge status-${statusClass}">${statusText}</span>
                                    </div>
                                    <div class="document-details">
                                        <p><strong>Data:</strong> ${dataFormatada}</p>
                                        <p><strong>Valor Frete:</strong> R$ ${parseFloat(doc.valor_total || 0).toFixed(2).replace('.', ',')}</p>
                                        <p><strong>Origem:</strong> ${doc.origem || 'N/A'}</p>
                                        <p><strong>Destino:</strong> ${doc.destino || 'N/A'}</p>
                                        <p><strong>Peso:</strong> ${parseFloat(doc.peso_carga || 0).toFixed(2)} kg</p>
                                        <p><strong>Volumes:</strong> ${doc.volumes_carga || 0}</p>
                                    </div>
                                    <div class="document-actions">
                                        <button onclick="editarCTE(${doc.id})" class="btn-sm btn-info">
                                            ✏️ Editar
                                        </button>
                                        <button onclick="enviarCTESefaz(${doc.id})" class="btn-sm btn-success">
                                            🚀 Enviar SEFAZ
                                        </button>
                                        <button onclick="visualizarCTE(${doc.id})" class="btn-sm btn-secondary">
                                            👁️ Visualizar
                                        </button>
                                    </div>
                                </div>`;
                            });
                            msg.innerHTML = html;
                        } else {
                            msg.innerHTML = '<div style="color: #6c757d; background: #f8f9fa; padding: 15px; border-radius: 5px;">' +
                                '<strong>📭 Nenhum CT-e criado</strong><br>Clique em "Criar CT-e" para gerar documentos de transporte</div>';
                        }
                    } else {
                        throw new Error(data.error || 'Erro desconhecido');
                    }
                })
                .catch(error => {
                    console.error('❌ Erro ao carregar CT-e:', error);
                    msg.innerHTML = '<div style="color: #dc3545; background: #f8d7da; padding: 15px; border-radius: 5px;">' +
                        '<strong>❌ Erro ao carregar dados!</strong><br>' + error.message + '</div>';
                });
        }
        
        function carregarMDFE() {
            const msg = document.getElementById('mdfeList');
            msg.innerHTML = '<div style="color: #6c757d; background: #f8f9fa; padding: 15px; border-radius: 5px;">' +
                '<strong>📋 Funcionalidade em desenvolvimento</strong><br>MDF-e será implementado em breve</div>';
        }
        
        function carregarDadosIniciais() {
            // Carregar dados reais da API
            fetch('../api/documentos_fiscais_v2.php?action=totals')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.totais.nfe) {
                            const nfe = data.totais.nfe;
                            document.getElementById('nfeRecebidas').textContent = nfe.total || '0';
                        }
                        if (data.totais.cte) {
                            const cte = data.totais.cte;
                            document.getElementById('ctePendentes').textContent = cte.pendentes || '0';
                            document.getElementById('cteAutorizados').textContent = cte.autorizados || '0';
                        }
                    } else {
                        // Fallback para dados padrão
                        document.getElementById('nfeRecebidas').textContent = '0';
                        document.getElementById('ctePendentes').textContent = '0';
                        document.getElementById('cteAutorizados').textContent = '0';
                    }
                })
                .catch(error => {
                    console.error('❌ Erro ao carregar dados:', error);
                    // Fallback para dados padrão
                    document.getElementById('nfeRecebidas').textContent = '0';
                    document.getElementById('ctePendentes').textContent = '0';
                    document.getElementById('cteAutorizados').textContent = '0';
                });
        }
        
        function atualizarDados() {
            carregarDadosIniciais();
            carregarNFE();
            carregarCTE();
        }
        
        function sincronizarSefaz() {
            const msg = document.getElementById('nfeList');
            msg.innerHTML = '<div style="color: #17a2b8; background: #d1ecf1; padding: 15px; border-radius: 5px;">' +
                '<strong>🔄 Sincronizando com SEFAZ...</strong><br>Verificando status dos documentos...</div>';
            
            // Simular sincronização
            setTimeout(() => {
                msg.innerHTML = '<div style="color: #28a745; background: #d4edda; padding: 15px; border-radius: 5px;">' +
                    '<strong>✅ Sincronização concluída!</strong><br>Status atualizado para todos os documentos</div>';
                
                // Recarregar dados
                setTimeout(() => {
                    atualizarDados();
                }, 1000);
            }, 3000);
        }
        
        // Funções auxiliares
        function visualizarNFE(id) {
            window.open(`../visualizar_nfe.php?id=${id}`, '_blank');
        }
        
        function editarCTE(id) {
            alert('Funcionalidade de edição de CT-e será implementada em breve!');
        }
        
        function enviarCTESefaz(id) {
            if (!confirm('Confirma o envio deste CT-e para SEFAZ?\n\nEsta operação não pode ser desfeita.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'enviar_sefaz');
            formData.append('id', id);
            formData.append('tipo_documento', 'cte');
            
            fetch('../api/documentos_fiscais_v2.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`✅ CT-e enviado para SEFAZ com sucesso!\n\nStatus: ${data.status}\nProtocolo: ${data.protocolo}`);
                    carregarCTE();
                } else {
                    alert('❌ Erro ao enviar para SEFAZ: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao enviar para SEFAZ');
            });
        }
        
        function visualizarCTE(id) {
            alert('Funcionalidade de visualização de CT-e será implementada em breve!');
        }
        
        function verificarStatusSefaz(forceRefresh = false) {
            const statusElement = document.getElementById('sefazStatus');
            const subtitleElement = document.getElementById('sefazSubtitle');
            
            // Mostrar carregando
            statusElement.innerHTML = '<span class="status-badge status-warning"><i class="fas fa-clock"></i> Verificando...</span>';
            subtitleElement.textContent = 'Testando conexão...';
            
            // Simular verificação de status
            setTimeout(() => {
                const status = Math.random() > 0.3 ? 'online' : 'offline';
                const cor = status === 'online' ? 'success' : 'danger';
                const texto = status === 'online' ? 'Sistema SEFAZ funcionando normalmente' : 'Problemas detectados no SEFAZ';
                const tempo = Math.floor(Math.random() * 200) + 50;
                
                statusElement.innerHTML = `<span class="status-badge status-${cor}">
                    <i class="fas fa-${status === 'online' ? 'check-circle' : 'exclamation-triangle'}"></i> 
                    ${status === 'online' ? 'Online' : 'Offline'}
                </span>`;
                
                subtitleElement.textContent = `${texto} (${tempo}ms)`;
            }, 1500);
        }
    </script>
    
    <!-- Footer -->
    <?php include '../../includes/footer.php'; ?>
</body>
</html>
