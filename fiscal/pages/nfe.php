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
        
        /* Tabela simples de NF-e */
        .nfe-table-wrap { overflow-x: auto; }
        .nfe-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .nfe-table th, .nfe-table td {
            border: 1px solid var(--border-color);
            padding: 8px 10px;
            text-align: left;
        }
        .nfe-table th:first-child, .nfe-table td:first-child { width: 32px; text-align: center; }
        .nfe-table th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-secondary);
        }
        .nfe-table tbody tr:hover { background: #dee2e6; }
        .nfe-table .col-num { text-align: right; }
        .nfe-table .col-vlr { text-align: right; white-space: nowrap; }
        .nfe-table .col-chave { font-family: monospace; font-size: 0.75rem; max-width: 280px; overflow: hidden; text-overflow: ellipsis; }
        .nfe-table .col-acoes { white-space: nowrap; }
        .nfe-table .col-acoes a, .nfe-table .col-acoes button {
            padding: 4px 8px;
            margin: 0 2px;
            border: none;
            background: none;
            cursor: pointer;
            color: var(--text-secondary);
        }
        .nfe-table .col-acoes a:hover, .nfe-table .col-acoes button:hover { color: var(--primary-color); }
        .nfe-table .situacao-autorizada, .nfe-table .situacao-recebida, .nfe-table .situacao-consultada_sefaz { color: #155724; font-weight: 500; }
        .nfe-table .situacao-pendente { color: #856404; }
        .nfe-table .situacao-cancelada { color: #721c24; }
        
        #modalVisualizarNfeBody dl dt { font-weight: 600; color: #6c757d; }
        #modalVisualizarNfeBody dl dd { margin-bottom: 0.5rem; }
        
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
                            <i class="fas fa-search"></i> Consultar NF-e
                        </button>
                        <button id="sincronizarCnpjBtn" class="btn-add-widget" type="button" onclick="sincronizarNfeCnpj(false)" title="Buscar NF-e novas do meu CNPJ (desde a última sincronização)">
                            <i class="fas fa-cloud-download-alt"></i> Buscar NF-e do meu CNPJ
                        </button>
                        <button id="sincronizarCnpjZeroBtn" class="btn-add-widget" type="button" onclick="sincronizarNfeCnpj(true)" title="Buscar desde o início (últimos 3 meses na SEFAZ). Use se não trouxe notas que você sabe que existem." style="margin-left: 4px;">
                            <i class="fas fa-history"></i> Buscar desde o início
                        </button>
                        <div class="view-controls">
                            <button id="refreshBtn" class="btn-restore-layout" title="Atualizar lista">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button id="exportBtn" class="btn-toggle-layout" title="Exportar">
                                <i class="fas fa-file-export"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Lista de NF-e Recebidas -->
                <div class="document-list nfe-table-wrap" style="margin-top: 20px;">
                    <h3>NF-e Recebidas do Cliente</h3>
                    <div id="nfeList">
                        <p>Carregando NF-e...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para Consultar NF-e -->
    <div class="modal fade" id="receberNfeModal" tabindex="-1" aria-labelledby="receberNfeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="receberNfeModalLabel">🔍 Consultar NF-e</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle"></i>
                        <strong>Consulta SEFAZ:</strong> Informe a chave de acesso da NF-e para buscar e receber a nota automaticamente (requer certificado digital).
                    </div>
                    <div id="formularioContainer" class="mt-3">
                        <div id="formularioContent"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnReceberNfe" onclick="processarRecebimentoNFE()" style="display: none;">
                        🔍 Consultar NF-e
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Visualizar NF-e (dados básicos) -->
    <div class="modal fade" id="modalVisualizarNfe" tabindex="-1" aria-labelledby="modalVisualizarNfeLabel" aria-hidden="true">
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
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Carregar lista de NF-e ao abrir a página
            carregarNFE();
            
            // Botão de atualizar lista
            const refreshBtn = document.getElementById('refreshBtn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    carregarNFE();
                });
            }
        });
        
        // Funções para receber NF-e do cliente
        function receberNFE() {
            const modal = new bootstrap.Modal(document.getElementById('receberNfeModal'));
            resetarFormulariosRecebimento();
            modal.show();
            // Abrir direto o formulário de Consulta SEFAZ
            setTimeout(function() { abrirMetodoSefaz(); }, 300);
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
alert(`✅ NF-e recebida com sucesso!\n\nChave: ${data.chave_acesso}\nNúmero: ${data.numero_nfe}\nEmitente: ${data.emitente || 'N/A'}\nValor: R$ ${parseFloat(data.valor_total || 0).toFixed(2)}`);
                    
                    // Fechar modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('receberNfeModal'));
                    modal.hide();
                    
                    // Atualizar dados
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
alert(`✅ NF-e consultada na SEFAZ com sucesso!\n\nChave: ${data.chave_acesso}\nNúmero: ${data.numero_nfe}\nEmitente: ${data.emitente || 'N/A'}\nValor: R$ ${parseFloat(data.valor_total || 0).toFixed(2)}\nProtocolo: ${data.protocolo || 'N/A'}`);
                    
                    // Fechar modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('receberNfeModal'));
                    modal.hide();
                    
                    // Atualizar dados
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
            fetch('../api/documentos_fiscais_v2.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
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
                        alert(texto);
                        carregarNFE();
                    } else {
                        alert('Erro: ' + (data.error || data.message || 'Erro desconhecido'));
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Erro ao sincronizar NF-e.');
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
            
            fetch('../api/documentos_fiscais_v2.php?action=list&tipo=nfe&limit=100')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.documentos && data.documentos.length > 0) {
                            const docs = data.documentos;
                            let html = '<table class="nfe-table"><thead><tr>';
                            html += '<th></th>';
                            html += '<th>Mod</th><th>Série</th><th>Número</th><th>Chave de Acesso</th><th>Emissão</th>';
                            html += '<th>Cliente/Fornecedor</th><th>Vlr Nota</th><th>Recibo</th><th>Situação</th>';
                            html += '<th class="col-acoes">Ações</th></tr></thead><tbody>';
                            
                            docs.forEach(doc => {
                                const dataEmissao = doc.data_emissao ? new Date(doc.data_emissao).toLocaleDateString('pt-BR') : '-';
                                const numero = String(doc.numero_nfe || '').padStart(9, '0');
                                const serie = doc.serie_nfe || '-';
                                const vlr = parseFloat(doc.valor_total || 0).toFixed(2).replace('.', ',');
                                const recibo = doc.protocolo_autorizacao || '0';
                                const situacao = doc.status === 'consultada_sefaz' ? 'Consultada SEFAZ' : 
                                    doc.status === 'entregue' ? 'Entregue' : doc.status === 'em_transporte' ? 'Em Transporte' : 
                                    doc.status === 'cancelada' ? 'Cancelada' : doc.status === 'autorizado' || doc.status === 'autorizada' ? 'Autorizada' :
                                    (doc.status ? doc.status.replace(/_/g, ' ') : 'Recebida');
                                const situacaoClass = (doc.status || 'recebida').replace(/_/g, '-');
                                html += '<tr>';
                                html += '<td><input type="checkbox" class="nfe-check" data-id="' + doc.id + '"></td>';
                                html += '<td class="col-num">55</td>';
                                html += '<td>' + escapeHtml(serie) + '</td>';
                                html += '<td class="col-num">' + escapeHtml(numero) + '</td>';
                                html += '<td class="col-chave" title="' + escapeHtml(doc.chave_acesso || '') + '">' + escapeHtml(doc.chave_acesso || '-') + '</td>';
                                html += '<td>' + dataEmissao + '</td>';
                                html += '<td>' + escapeHtml(doc.cliente_razao_social || 'Cliente') + '</td>';
                                html += '<td class="col-vlr">R$ ' + vlr + '</td>';
                                html += '<td class="col-num">' + escapeHtml(recibo) + '</td>';
                                html += '<td class="situacao-' + situacaoClass + '">' + escapeHtml(situacao) + '</td>';
                                html += '<td class="col-acoes">';
                                html += '<a href="#" onclick="abrirModalNfe(' + doc.id + '); return false;" title="Visualizar"><i class="fas fa-search"></i></a>';
                                html += '<a href="#" onclick="downloadNfeXml(' + doc.id + '); return false;" title="Download XML"><i class="fas fa-file-code"></i></a>';
                                html += '<a href="#" onclick="downloadNfePdf(' + doc.id + '); return false;" title="Download PDF"><i class="fas fa-file-pdf"></i></a>';
                                html += '</td></tr>';
                            });
                            html += '</tbody></table>';
                            msg.innerHTML = html;
                        } else {
                            msg.innerHTML = '<div style="color: #6c757d; background: #f8f9fa; padding: 15px; border-radius: 5px;">' +
                                '<strong>📭 Nenhuma NF-e recebida</strong><br>Use "Buscar NF-e do meu CNPJ" para puxar automaticamente ou "Consultar NF-e" para buscar por chave</div>';
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
        
        function escapeHtml(s) {
            if (s == null) return '';
            const div = document.createElement('div');
            div.textContent = s;
            return div.innerHTML;
        }
        
        function downloadNfeXml(id) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../api/download_nfe_xml.php';
            form.target = '_blank';
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'id'; input.value = id;
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
        function downloadNfePdf(id) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../api/download_nfe_pdf.php';
            form.target = '_blank';
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'id'; input.value = id;
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
        function abrirModalNfe(id) {
            const modal = new bootstrap.Modal(document.getElementById('modalVisualizarNfe'));
            const body = document.getElementById('modalVisualizarNfeBody');
            const title = document.getElementById('modalVisualizarNfeLabel');
            body.innerHTML = '<p class="text-muted">Carregando...</p>';
            modal.show();
            
            fetch('../api/documentos_fiscais_v2.php?action=get&tipo=nfe&id=' + id)
                .then(r => r.json())
                .then(data => {
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
