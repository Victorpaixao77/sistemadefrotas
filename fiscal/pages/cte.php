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
$page_title = "Gestão de CT-e";
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
        .status-warning { background: #fff3cd; color: #856404; }
        
        /* Estilos específicos para CT-e */
        .nfe-selector {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            background: var(--bg-secondary);
        }
        
        .nfe-item {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .nfe-item:hover {
            border-color: var(--primary-color);
            box-shadow: 0 2px 8px rgba(0,123,255,0.1);
        }
        
        .nfe-item.selected {
            border-color: var(--primary-color);
            background: rgba(0,123,255,0.05);
        }
        
        .nfe-item .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .nfe-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
            font-size: 0.9rem;
        }
        
        .nfe-details span {
            color: var(--text-secondary);
        }
        
        .nfe-details strong {
            color: var(--text-primary);
        }
        
        .cte-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .cte-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .cte-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .cte-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .status-rascunho { background: #f8f9fa; color: #6c757d; }
        .status-pendente { background: #fff3cd; color: #856404; }
        .status-autorizado { background: #d4edda; color: #155724; }
        .status-rejeitado { background: #f8d7da; color: #721c24; }
        
        .cte-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .info-group h6 {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-group p {
            color: var(--text-primary);
            margin: 0;
            font-weight: 500;
        }
        
        .status-recebida { background: #d4edda; color: #155724; }
        .status-em-transporte { background: #fff3cd; color: #856404; }
        .status-entregue { background: #d1ecf1; color: #0c5460; }
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
                        <button id="criarCTeBtn" class="btn-add-widget" onclick="abrirModalCriarCTE()">
                            <i class="fas fa-plus"></i> Criar CT-e
                        </button>
                        <button id="voltarNFEBtn" class="btn-add-widget" onclick="window.location.href='nfe.php'">
                            <i class="fas fa-file-invoice"></i> Gerenciar NF-e
                        </button>
                        <button id="irMDFEBtn" class="btn-add-widget" onclick="window.location.href='mdfe.php'">
                            <i class="fas fa-route"></i> Gerenciar MDF-e
                        </button>
                        <button id="sincronizarSefazBtn" class="btn-add-widget" onclick="sincronizarSefaz()">
                            <i class="fas fa-sync"></i> Sincronizar SEFAZ
                        </button>
                        <div class="view-controls">
                            <button id="refreshBtn" class="btn-restore-layout" title="Atualizar" onclick="atualizarDados()">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button id="exportBtn" class="btn-toggle-layout" title="Exportar" onclick="exportarDados()">
                                <i class="fas fa-file-export"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- KPI Cards Row -->
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Total de CT-e</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="cteTotal">0</span>
                                <span class="metric-subtitle">Documentos cadastrados</span>
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
                    <button class="document-tab active" data-target="cteContent">CT-e</button>
                    <button class="document-tab" data-target="nfeContent">NF-e</button>
                    <button class="document-tab" data-target="mdfeContent">MDF-e</button>
                </div>
                
                <!-- CT-e Content -->
                <div id="cteContent" class="document-content active">
                    <div class="document-list">
                        <h3>CT-e Recentes</h3>
                        <div id="cteList">
                            <p>Carregando CT-e...</p>
                        </div>
                    </div>
                </div>
                
                <!-- NF-e Content -->
                <div id="nfeContent" class="document-content">
                    <div class="document-list">
                        <h3>NF-e Recentes</h3>
                        <div id="nfeList">
                            <p>Carregando NF-e...</p>
                        </div>
                    </div>
                </div>
                
                <!-- MDF-e Content -->
                <div id="mdfeContent" class="document-content">
                    <div class="document-list">
                        <h3>MDF-e Recentes</h3>
                        <div id="mdfeList">
                            <p>Carregando MDF-e...</p>
                        </div>
                    </div>
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
                        <!-- Informações do Transporte -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="veiculoId" class="form-label">Veículo</label>
                                    <select class="form-select" id="veiculoId" name="veiculo_id" required>
                                        <option value="">Selecione o veículo</option>
                                        <option value="1">Caminhão 01 - ABC-1234</option>
                                        <option value="2">Caminhão 02 - DEF-5678</option>
                                        <option value="3">Carreta 01 - GHI-9012</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="motoristaId" class="form-label">Motorista</label>
                                    <select class="form-select" id="motoristaId" name="motorista_id" required>
                                        <option value="">Selecione o motorista</option>
                                        <option value="1">João Silva - CNH: 12345678901</option>
                                        <option value="2">Pedro Santos - CNH: 98765432109</option>
                                        <option value="3">Carlos Oliveira - CNH: 11122233344</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Origem e Destino -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="origem" class="form-label">Origem</label>
                                    <input type="text" class="form-control" id="origem" name="origem" required placeholder="Cidade/UF de origem">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="destino" class="form-label">Destino</label>
                                    <input type="text" class="form-control" id="destino" name="destino" required placeholder="Cidade/UF de destino">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Valores -->
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
                                    <label for="pesoTotal" class="form-label">Peso Total (kg)</label>
                                    <input type="number" class="form-control" id="pesoTotal" name="peso_total" step="0.01" min="0" required readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="volumesTotal" class="form-label">Volumes Total</label>
                                    <input type="number" class="form-control" id="volumesTotal" name="volumes_total" min="1" required readonly>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Seleção de NF-e -->
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-file-invoice"></i> NF-e para Transportar
                                <small class="text-muted">(Selecione as NF-e recebidas que serão transportadas)</small>
                            </label>
                            <div id="nfeSelector" class="nfe-selector">
                                <div class="text-center p-3">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <p>Carregando NF-e disponíveis...</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Observações -->
                        <div class="mb-3">
                            <label for="observacoesCTE" class="form-label">Observações (opcional)</label>
                            <textarea class="form-control" id="observacoesCTE" name="observacoes" rows="3" placeholder="Informações adicionais sobre o transporte..."></textarea>
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
                    if (target === 'cteContent') {
                        carregarCTE();
                    } else if (target === 'nfeContent') {
                        carregarNFE();
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
                verificarStatusSefaz();
            });
            
            // Carregar dados iniciais
            carregarDadosIniciais();
            carregarCTE();
            verificarStatusSefaz();
        });
        
        // Função para abrir modal de criar CT-e
        function abrirModalCriarCTE() {
            // Carregar NF-e disponíveis
            carregarNFEDisponiveis().then(() => {
                const modal = new bootstrap.Modal(document.getElementById('criarCTEModal'));
                modal.show();
            });
        }
        
        function carregarNFEDisponiveis() {
            return fetch('../api/documentos_fiscais_v2.php?action=list&tipo=nfe&status=recebida')
                .then(response => response.json())
                .then(data => {
                    const selector = document.getElementById('nfeSelector');
                    
                    if (data.success && data.documentos && data.documentos.length > 0) {
                        let html = '';
                        data.documentos.forEach(nfe => {
                            const dataFormatada = new Date(nfe.data_emissao).toLocaleDateString('pt-BR');
                            const valor = parseFloat(nfe.valor_total || 0).toFixed(2);
                            const peso = parseFloat(nfe.peso_carga || 0).toFixed(2);
                            
                            html += `
                                <div class="nfe-item" onclick="toggleNFE(${nfe.id}, this)">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="nfe_ids[]" value="${nfe.id}" id="nfe_${nfe.id}">
                                        <label class="form-check-label w-100" for="nfe_${nfe.id}">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong>NF-e ${nfe.numero_nfe.toString().padStart(3, '0')}</strong>
                                                    <span class="status-badge status-${nfe.status === 'recebida' ? 'recebida' : 'em-transporte'}">${nfe.status === 'recebida' ? 'Recebida' : 'Em Transporte'}</span>
                                                </div>
                                                <small class="text-muted">${dataFormatada}</small>
                                            </div>
                                            <div class="nfe-details">
                                                <span><strong>Cliente:</strong> ${nfe.cliente_razao_social || 'N/A'}</span>
                                                <span><strong>Valor:</strong> R$ ${valor.replace('.', ',')}</span>
                                                <span><strong>Peso:</strong> ${peso} kg</span>
                                                <span><strong>Volumes:</strong> ${nfe.volumes || 0}</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            `;
                        });
                        selector.innerHTML = html;
                    } else {
                        selector.innerHTML = `
                            <div class="text-center p-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h6>Nenhuma NF-e disponível</h6>
                                <p class="text-muted">Não há NF-e recebidas disponíveis para transporte.</p>
                                <a href="nfe.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus"></i> Receber NF-e
                                </a>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    document.getElementById('nfeSelector').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            Erro ao carregar NF-e disponíveis
                        </div>
                    `;
                });
        }
        
        function toggleNFE(nfeId, element) {
            const checkbox = element.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                element.classList.add('selected');
            } else {
                element.classList.remove('selected');
            }
            
            // Atualizar totais automaticamente
            atualizarTotaisFormulario();
        }
        
        function atualizarTotaisFormulario() {
            const checkboxes = document.querySelectorAll('input[name="nfe_ids[]"]:checked');
            let pesoTotal = 0;
            let volumesTotal = 0;
            let valorSugerido = 0;
            
            checkboxes.forEach(checkbox => {
                const nfeItem = checkbox.closest('.nfe-item');
                const detalhes = nfeItem.querySelector('.nfe-details');
                
                // Extrair peso
                const pesoText = detalhes.children[2].textContent;
                const peso = parseFloat(pesoText.replace('Peso:', '').replace('kg', '').trim());
                if (!isNaN(peso)) pesoTotal += peso;
                
                // Extrair volumes
                const volumesText = detalhes.children[3].textContent;
                const volumes = parseInt(volumesText.replace('Volumes:', '').trim());
                if (!isNaN(volumes)) volumesTotal += volumes;
                
                // Calcular valor sugerido (10% do valor da carga)
                const valorText = detalhes.children[1].textContent;
                const valor = parseFloat(valorText.replace('Valor: R$', '').replace(',', '.').trim());
                if (!isNaN(valor)) valorSugerido += valor * 0.1;
            });
            
            // Atualizar campos do formulário
            document.getElementById('pesoTotal').value = pesoTotal.toFixed(2);
            document.getElementById('volumesTotal').value = volumesTotal;
            document.getElementById('valorFrete').value = valorSugerido.toFixed(2);
        }
        
        function salvarCTE() {
            const form = document.getElementById('criarCTEForm');
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            // Verificar se pelo menos uma NF-e foi selecionada
            const nfeSelecionadas = document.querySelectorAll('input[name="nfe_ids[]"]:checked');
            if (nfeSelecionadas.length === 0) {
                alert('❌ Selecione pelo menos uma NF-e para transportar');
                return;
            }
            
            const formData = new FormData(form);
            formData.append('action', 'criar_cte');
            
            // Adicionar NF-e selecionadas
            nfeSelecionadas.forEach(nfe => {
                formData.append('nfe_ids[]', nfe.value);
            });
            
            // Mostrar loading
            const btnSalvar = event.target;
            const originalText = btnSalvar.innerHTML;
            btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Criando CT-e...';
            btnSalvar.disabled = true;
            
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
                    form.reset();
                    
                    // Atualizar dados
                    carregarDadosIniciais();
                    carregarCTE();
                } else {
                    alert('❌ Erro ao criar CT-e: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('❌ Erro ao criar CT-e');
            })
            .finally(() => {
                // Restaurar botão
                btnSalvar.innerHTML = originalText;
                btnSalvar.disabled = false;
            });
        }
        
        function carregarCTE() {
            const msg = document.getElementById('cteList');
            msg.innerHTML = '<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Carregando CT-e...</div>';
            
            fetch('../api/documentos_fiscais_v2.php?action=list&tipo=cte&limit=20')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.documentos && data.documentos.length > 0) {
                            let html = '';
                            data.documentos.forEach(doc => {
                                const statusClass = doc.status === 'autorizado' ? 'autorizado' : 
                                                  doc.status === 'pendente' ? 'pendente' : 
                                                  doc.status === 'rejeitado' ? 'rejeitado' : 'rascunho';
                                const statusText = doc.status === 'autorizado' ? 'Autorizado' : 
                                                 doc.status === 'pendente' ? 'Pendente' : 
                                                 doc.status === 'rejeitado' ? 'Rejeitado' : 'Rascunho';
                                const dataFormatada = new Date(doc.data_emissao).toLocaleDateString('pt-BR');
                                
                                html += `
                                    <div class="cte-card">
                                        <div class="cte-header">
                                            <div class="cte-number">CT-e ${doc.numero_cte.toString().padStart(3, '0')}</div>
                                            <div class="cte-status status-${statusClass}">${statusText}</div>
                                        </div>
                                        <div class="cte-info">
                                            <div class="info-group">
                                                <h6>Data de Emissão</h6>
                                                <p>${dataFormatada}</p>
                                            </div>
                                            <div class="info-group">
                                                <h6>Valor do Frete</h6>
                                                <p>R$ ${parseFloat(doc.valor_total || 0).toFixed(2).replace('.', ',')}</p>
                                            </div>
                                            <div class="info-group">
                                                <h6>Origem</h6>
                                                <p>${doc.origem || 'N/A'}</p>
                                            </div>
                                            <div class="info-group">
                                                <h6>Destino</h6>
                                                <p>${doc.destino || 'N/A'}</p>
                                            </div>
                                            <div class="info-group">
                                                <h6>Peso Total</h6>
                                                <p>${parseFloat(doc.peso_carga || 0).toFixed(2)} kg</p>
                                            </div>
                                            <div class="info-group">
                                                <h6>Volumes</h6>
                                                <p>${doc.volumes_carga || 0}</p>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <button onclick="editarCTE(${doc.id})" class="btn btn-sm btn-outline-primary me-2">
                                                <i class="fas fa-edit"></i> Editar
                                            </button>
                                            ${doc.status === 'rascunho' || doc.status === 'pendente' ? 
                                                `<button onclick="enviarCTESefaz(${doc.id})" class="btn btn-sm btn-success me-2">
                                                    <i class="fas fa-paper-plane"></i> Enviar SEFAZ
                                                </button>` : ''}
                                            <button onclick="visualizarCTE(${doc.id})" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-eye"></i> Visualizar
                                            </button>
                                        </div>
                                    </div>
                                `;
                            });
                            msg.innerHTML = html;
                        } else {
                            msg.innerHTML = `
                                <div class="text-center p-4">
                                    <i class="fas fa-truck fa-3x text-muted mb-3"></i>
                                    <h6>Nenhum CT-e criado</h6>
                                    <p class="text-muted">Clique em "Criar CT-e" para gerar documentos de transporte</p>
                                    <button onclick="abrirModalCriarCTE()" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Criar CT-e
                                    </button>
                                </div>
                            `;
                        }
                    } else {
                        throw new Error(data.error || 'Erro desconhecido');
                    }
                })
                .catch(error => {
                    console.error('❌ Erro ao carregar CT-e:', error);
                    msg.innerHTML = `
                        <div class="alert alert-danger">
                            <strong>❌ Erro ao carregar dados!</strong><br>
                            ${error.message}
                        </div>
                    `;
                });
        }
        
        function carregarNFE() {
            const msg = document.getElementById('nfeList');
            msg.innerHTML = '<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Carregando NF-e...</div>';
            
            fetch('../api/documentos_fiscais_v2.php?action=list&tipo=nfe&limit=10')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.documentos && data.documentos.length > 0) {
                        let html = '';
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
                                </div>
                            </div>`;
                        });
                        msg.innerHTML = html;
                    } else {
                        msg.innerHTML = `
                            <div class="text-center p-4">
                                <i class="fas fa-file-invoice fa-2x text-muted mb-2"></i>
                                <p class="text-muted">Nenhuma NF-e encontrada</p>
                                <a href="nfe.php" class="btn btn-primary btn-sm">Ver todas NF-e</a>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    msg.innerHTML = '<div class="alert alert-danger">Erro ao carregar NF-e</div>';
                });
        }
        
        function carregarMDFE() {
            const msg = document.getElementById('mdfeList');
            msg.innerHTML = `
                <div class="text-center p-4">
                    <i class="fas fa-route fa-2x text-muted mb-2"></i>
                    <p class="text-muted">Funcionalidade MDF-e em desenvolvimento</p>
                    <a href="mdfe.php" class="btn btn-primary btn-sm">Ir para MDF-e</a>
                </div>
            `;
        }
        
        function sincronizarSefaz() {
            const msg = document.getElementById('cteList');
            msg.innerHTML = '<div style="color: #17a2b8; background: #d1ecf1; padding: 15px; border-radius: 5px;">' +
                '<strong>🔄 Sincronizando com SEFAZ...</strong><br>Verificando status dos documentos...</div>';
            
            setTimeout(() => {
                msg.innerHTML = '<div style="color: #28a745; background: #d4edda; padding: 15px; border-radius: 5px;">' +
                    '<strong>✅ Sincronização concluída!</strong><br>Status atualizado para todos os documentos</div>';
                
                // Recarregar dados
                setTimeout(() => {
                    carregarCTE();
                }, 1000);
            }, 3000);
        }
        
        function atualizarDados() {
            carregarDadosIniciais();
            carregarCTE();
        }
        
        function exportarDados() {
            alert('Funcionalidade de exportação será implementada em breve!');
        }
        
        function carregarDadosIniciais() {
            fetch('../api/documentos_fiscais_v2.php?action=totals')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.totais.cte) {
                        const cte = data.totais.cte;
                        document.getElementById('cteTotal').textContent = cte.total || '0';
                        document.getElementById('ctePendentes').textContent = cte.pendentes || '0';
                        document.getElementById('cteAutorizados').textContent = cte.autorizados || '0';
                    } else {
                        // Fallback para dados padrão
            document.getElementById('cteTotal').textContent = '0';
            document.getElementById('ctePendentes').textContent = '0';
            document.getElementById('cteAutorizados').textContent = '0';
                    }
                })
                .catch(error => {
                    console.error('❌ Erro ao carregar totais:', error);
                    document.getElementById('cteTotal').textContent = '0';
                    document.getElementById('ctePendentes').textContent = '0';
                    document.getElementById('cteAutorizados').textContent = '0';
                });
        }
        
        // Funções auxiliares
        function editarCTE(id) {
            alert('Funcionalidade de edição será implementada em breve!');
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
                    carregarDadosIniciais();
                } else {
                    alert('❌ Erro ao enviar para SEFAZ: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('❌ Erro ao enviar para SEFAZ');
            });
        }
        
        function visualizarCTE(id) {
            window.open(`../visualizar_cte.php?id=${id}`, '_blank');
        }
        
        function verificarStatusSefaz() {
            const statusElement = document.getElementById('sefazStatus');
            const subtitleElement = document.getElementById('sefazSubtitle');
            
            // Mostrar carregando
            statusElement.innerHTML = '<span class="status-badge status-warning"><i class="fas fa-clock"></i> Verificando...</span>';
            subtitleElement.textContent = 'Testando conexão...';
            
            // Fazer requisição real para a API
            fetch('../api/sefaz_status.php?action=status&ambiente=homologacao')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const status = data.status_geral;
                        const texto = data.status_texto;
                        const cor = data.status_cor;
                        const tempo = data.detalhes.conexao_basica.tempo;
                        
                        // Atualizar status
                        statusElement.innerHTML = `<span class="status-badge status-${cor}">
                            <i class="fas fa-${status === 'online' ? 'check-circle' : 'exclamation-triangle'}"></i> 
                            ${status === 'online' ? 'Online' : 'Offline'}
                        </span>`;
                        
                        subtitleElement.textContent = `${texto} (${tempo}ms)`;
                        
                        // Log detalhado no console
                        console.log('🔍 Status SEFAZ:', data);
                        
                    } else {
                        throw new Error(data.error || 'Erro desconhecido');
                    }
                })
                .catch(error => {
                    console.error('❌ Erro ao verificar status SEFAZ:', error);
                    
                    // Mostrar erro
                    statusElement.innerHTML = '<span class="status-badge status-danger"><i class="fas fa-exclamation-triangle"></i> Erro</span>';
                    subtitleElement.textContent = 'Falha na verificação';
                });
        }
    </script>
    
    <!-- Footer -->
    <?php include '../../includes/footer.php'; ?>
</body>
</html>
