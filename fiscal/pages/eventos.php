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
$page_title = "Eventos Fiscais";
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
        
        .event-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        
        .event-tab {
            padding: 10px 20px;
            border: none;
            background: none;
            color: var(--text-secondary);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .event-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .event-content {
            display: none;
        }
        
        .event-content.active {
            display: block;
        }
        
        .event-list {
            background: var(--bg-secondary);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 20px;
        }
        
        .event-item {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .event-header h4 {
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
        
        .event-details p {
            margin: 5px 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .event-actions {
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
                        <button id="voltarNFEBtn" class="btn-add-widget" onclick="window.location.href='nfe.php'">
                            <i class="fas fa-file-invoice"></i> Voltar para NF-e
                        </button>
                        <button id="novoEventoBtn" class="btn-add-widget" onclick="abrirModalNovoEvento()">
                            <i class="fas fa-plus"></i> Novo Evento
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
                            <h3>Total de Eventos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="eventosTotal">0</span>
                                <span class="metric-subtitle">Eventos registrados</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Eventos Pendentes</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="eventosPendentes">0</span>
                                <span class="metric-subtitle">Aguardando processamento</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Eventos Processados</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="eventosProcessados">0</span>
                                <span class="metric-subtitle">Eventos concluídos</span>
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
                
                <!-- Event Tabs -->
                <div class="event-tabs">
                    <button class="event-tab active" data-target="cancelamentosContent">Cancelamentos</button>
                    <button class="event-tab" data-target="correcoesContent">Correções</button>
                    <button class="event-tab" data-target="outrosContent">Outros Eventos</button>
                </div>
                
                <!-- Cancelamentos Content -->
                <div id="cancelamentosContent" class="event-content active">
                    <div class="event-list">
                        <h3>Cancelamentos Recentes</h3>
                        <div id="cancelamentosList">
                            <p>Carregando cancelamentos...</p>
                        </div>
                    </div>
                </div>
                
                <!-- Correções Content -->
                <div id="correcoesContent" class="event-content">
                    <div class="event-list">
                        <h3>Correções Recentes</h3>
                        <div id="correcoesList">
                            <p>Carregando correções...</p>
                        </div>
                    </div>
                </div>
                
                <!-- Outros Eventos Content -->
                <div id="outrosContent" class="event-content">
                    <div class="event-list">
                        <h3>Outros Eventos</h3>
                        <div id="outrosList">
                            <p>Carregando outros eventos...</p>
                        </div>
                    </div>
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
        // Configurar abas de eventos
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.event-tab');
            const contents = document.querySelectorAll('.event-content');
            
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
            carregarEventos();
            carregarDadosIniciais();
            verificarStatusSefaz();
        });
        
        // Carregar eventos ao inicializar
        function carregarEventos() {
            const container = document.getElementById('cancelamentosList');
            container.innerHTML = '<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Carregando eventos...</p></div>';
            
            fetch('../api/documentos_fiscais_v2.php?action=listar_eventos')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.eventos && data.eventos.length > 0) {
                        let html = '';
                        data.eventos.forEach(evento => {
                            const dataFormatada = new Date(evento.data_evento).toLocaleString('pt-BR');
                            const statusClass = evento.status === 'aceito' ? 'success' : 
                                              evento.status === 'rejeitado' ? 'danger' : 'warning';
                            const statusText = evento.status === 'aceito' ? 'Aceito' : 
                                             evento.status === 'rejeitado' ? 'Rejeitado' : 'Pendente';
                            const tipoText = {
                                'cancelamento': '🚫 Cancelamento',
                                'cce': '📝 Carta de Correção',
                                'manifestacao': '📋 Manifestação',
                                'inutilizacao': '❌ Inutilização',
                                'encerramento': '🔚 Encerramento'
                            }[evento.tipo_evento] || evento.tipo_evento;
                            
                            html += `
                                <div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; margin-bottom: 15px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                        <div style="font-size: 1.1rem; font-weight: bold; color: var(--primary-color);">${tipoText}</div>
                                        <div style="padding: 6px 12px; border-radius: 20px; font-size: 0.9rem; font-weight: 500; background: ${statusClass === 'success' ? '#d4edda' : statusClass === 'danger' ? '#f8d7da' : '#fff3cd'}; color: ${statusClass === 'success' ? '#155724' : statusClass === 'danger' ? '#721c24' : '#856404'};">${statusText}</div>
                                    </div>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                                        <div>
                                            <h6 style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 5px;">NF-e</h6>
                                            <p style="margin: 0; font-weight: 500;">${evento.numero_nfe || 'N/A'}</p>
                                        </div>
                                        <div>
                                            <h6 style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 5px;">Data do Evento</h6>
                                            <p style="margin: 0; font-weight: 500;">${dataFormatada}</p>
                                        </div>
                                        <div>
                                            <h6 style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 5px;">Protocolo</h6>
                                            <p style="margin: 0; font-weight: 500;">${evento.protocolo_evento || 'Pendente'}</p>
                                        </div>
                                    </div>
                                    ${evento.justificativa ? `
                                        <div style="margin-top: 15px;">
                                            <h6 style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 5px;">Justificativa:</h6>
                                            <p style="margin: 0; color: var(--text-muted);">${evento.justificativa}</p>
                                        </div>
                                    ` : ''}
                                </div>
                            `;
                        });
                        container.innerHTML = html;
                        
                        // Atualizar contadores
                        document.getElementById('eventosTotal').textContent = data.eventos.length;
                        document.getElementById('eventosPendentes').textContent = data.eventos.filter(e => e.status === 'pendente').length;
                        document.getElementById('eventosProcessados').textContent = data.eventos.filter(e => e.status === 'aceito').length;
                    } else {
                        container.innerHTML = `
                            <div class="text-center p-4">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <h6>Nenhum evento encontrado</h6>
                                <p class="text-muted">Não há eventos fiscais registrados no sistema</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    container.innerHTML = `
                        <div class="alert alert-danger">
                            <strong>❌ Erro ao carregar eventos!</strong><br>
                            ${error.message}
                        </div>
                    `;
                });
        }
        
        // Funções reais para os botões
        function abrirModalNovoEvento() {
            // Criar modal dinamicamente se não existir
            let modal = document.getElementById('novoEventoModal');
            if (!modal) {
                const modalHTML = `
                    <div class="modal fade" id="novoEventoModal" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">📝 Novo Evento Fiscal</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="novoEventoForm">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="documentoId" class="form-label">NF-e</label>
                                                    <select class="form-select" id="documentoId" name="documento_id" required>
                                                        <option value="">Selecione a NF-e</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="tipoEvento" class="form-label">Tipo de Evento</label>
                                                    <select class="form-select" id="tipoEvento" name="tipo_evento" required>
                                                        <option value="">Selecione o tipo</option>
                                                        <option value="cancelamento">🚫 Cancelamento</option>
                                                        <option value="cce">📝 Carta de Correção</option>
                                                        <option value="manifestacao">📋 Manifestação</option>
                                                        <option value="inutilizacao">❌ Inutilização</option>
                                                        <option value="encerramento">🔚 Encerramento</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="justificativa" class="form-label">Justificativa</label>
                                            <textarea class="form-control" id="justificativa" name="justificativa" rows="3" required placeholder="Descreva o motivo do evento..."></textarea>
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Cancelar</button>
                                    <button type="button" class="btn btn-primary" onclick="salvarEvento()">📝 Processar Evento</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                document.body.insertAdjacentHTML('beforeend', modalHTML);
                modal = document.getElementById('novoEventoModal');
            }
            
            carregarNFEDisponiveis();
            const bootstrapModal = new bootstrap.Modal(modal);
            bootstrapModal.show();
        }
        
        function carregarNFEDisponiveis() {
            fetch('../api/documentos_fiscais_v2.php?action=list&tipo=nfe&limit=100')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('documentoId');
                    if (select) {
                        select.innerHTML = '<option value="">Selecione a NF-e</option>';
                        
                        if (data.success && data.documentos && data.documentos.length > 0) {
                            data.documentos.forEach(nfe => {
                                const option = document.createElement('option');
                                option.value = nfe.id;
                                option.textContent = `NF-e ${nfe.numero_nfe} - ${nfe.cliente_razao_social || 'Cliente'} - Status: ${nfe.status}`;
                                select.appendChild(option);
                            });
                        }
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar NF-e:', error);
                });
        }
        
        function salvarEvento() {
            const form = document.getElementById('novoEventoForm');
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const formData = new FormData(form);
            formData.append('action', 'processar_evento');
            
            // Mostrar loading
            const btnSalvar = event.target;
            const originalText = btnSalvar.innerHTML;
            btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
            btnSalvar.disabled = true;
            
            fetch('../api/documentos_fiscais_v2.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`✅ Evento processado com sucesso!\n\nTipo: ${data.tipo_evento}\nResultado: ${data.resultado.mensagem}`);
                    
                    // Fechar modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('novoEventoModal'));
                    modal.hide();
                    
                    // Limpar formulário
                    form.reset();
                    
                    // Atualizar lista
                    carregarEventos();
                } else {
                    alert('❌ Erro ao processar evento: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('❌ Erro ao processar evento');
            })
            .finally(() => {
                // Restaurar botão
                btnSalvar.innerHTML = originalText;
                btnSalvar.disabled = false;
            });
        }
        

        
        function sincronizarSefaz() {
            carregarEventos();
            verificarStatusSefaz();
        }
        
        function atualizarDados() {
            carregarEventos();
            carregarDadosIniciais();
        }
        
        function exportarDados() {
            window.open('../api/relatorios_fiscais.php?action=gerar_relatorio&tipo=eventos_fiscais&formato=excel', '_blank');
        }
        
        function carregarDadosIniciais() {
            // Simular carregamento de dados
            document.getElementById('eventosTotal').textContent = '0';
            document.getElementById('eventosPendentes').textContent = '0';
            document.getElementById('eventosProcessados').textContent = '0';
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
