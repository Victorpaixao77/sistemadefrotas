/**
 * 🚀 SISTEMA FISCAL - JAVASCRIPT PRINCIPAL
 * 📋 SISTEMA DE FROTAS - MÓDULO FISCAL
 * 
 * Este arquivo gerencia toda a lógica do frontend do sistema fiscal:
 * - Dashboard com KPIs
 * - Gestão de documentos (NF-e, CT-e, MDF-e)
 * - Eventos fiscais
 * - Interface responsiva
 * 
 * 📅 Data: Agosto 2025
 * 🔧 Versão: 2.0.0
 * 🏷️  Prefixo: fiscal_ (para organização do banco)
 */

class FiscalSystem {
    constructor() {
        this.empresaId = 1; // Será obtido dinamicamente
        this.currentTab = 'nfe';
        this.documents = {
            nfe: [],
            cte: [],
            mdfe: []
        };
        this.init();
    }

    /**
     * 🚀 Inicializar o sistema fiscal
     */
    init() {
        this.initializeFiscalSystem();
        this.loadFiscalDashboard();
        this.setupDocumentTabs();
        this.setupModals();
        this.checkSefazStatus();
    }

    /**
     * 🔧 Inicializar sistema fiscal
     */
    initializeFiscalSystem() {
        console.log('🚀 Sistema Fiscal inicializando...');
        
        // Carregar dados iniciais
        this.loadRecentDocuments();
        
        // Configurar atualizações automáticas
        setInterval(() => {
            this.updateDashboardKPIs();
        }, 30000); // Atualizar a cada 30 segundos
    }

    /**
     * 📊 Carregar dashboard fiscal
     */
    async loadFiscalDashboard() {
        try {
            const response = await fetch('/sistema-frotas/fiscal/api/fiscal_dashboard.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    empresa_id: this.empresaId
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success) {
                this.updateDashboardKPIs(data.data);
            } else {
                console.error('❌ Erro ao carregar dashboard:', data.message);
            }
        } catch (error) {
            console.error('❌ Erro ao carregar dashboard fiscal:', error);
            this.showMessage('Erro ao carregar dashboard', 'error');
        }
    }

    /**
     * 🔧 Atualizar elemento se existir
     */
    updateElementIfExists(elementId, value) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = value;
        } else {
            console.log(`⚠️ Elemento não encontrado: ${elementId}`);
        }
    }

    /**
     * 📈 Atualizar KPIs do dashboard
     */
    updateDashboardKPIs(data = null) {
        if (!data) {
            // Buscar dados atualizados
            this.loadFiscalDashboard();
            return;
        }

        // Detectar qual página está sendo carregada
        const currentPage = this.getCurrentPage();
        
        // Atualizar apenas os KPIs da página atual
        switch (currentPage) {
            case 'nfe':
                if (data.nfe) {
                    this.updateElementIfExists('nfeTotal', data.nfe.total || 0);
                    this.updateElementIfExists('nfePendentes', data.nfe.pendentes || 0);
                    this.updateElementIfExists('nfeAutorizadas', data.nfe.autorizadas || 0);
                }
                break;
                
            case 'cte':
                if (data.cte) {
                    this.updateElementIfExists('cteTotal', data.cte.total || 0);
                    this.updateElementIfExists('ctePendentes', data.cte.pendentes || 0);
                    this.updateElementIfExists('cteAutorizados', data.cte.autorizadas || 0);
                }
                break;
                
            case 'mdfe':
                if (data.mdfe) {
                    this.updateElementIfExists('mdfeTotal', data.mdfe.total || 0);
                    this.updateElementIfExists('mdfePendentes', data.mdfe.pendentes || 0);
                    this.updateElementIfExists('mdfeAutorizados', data.mdfe.autorizadas || 0);
                }
                break;
                
            case 'eventos':
                if (data.eventos) {
                    this.updateElementIfExists('totalEventos', data.eventos.total || 0);
                    this.updateElementIfExists('eventosPendentes', data.eventos.pendentes || 0);
                    this.updateElementIfExists('eventosProcessados', data.eventos.processados || 0);
                }
                break;
        }

        // Atualizar status SEFAZ (presente em todas as páginas)
        if (data.sefaz_status) {
            this.updateSefazStatus(data.sefaz_status);
        }
    }

    /**
     * 🔍 Detectar página atual
     */
    getCurrentPage() {
        const path = window.location.pathname;
        if (path.includes('/nfe.php')) return 'nfe';
        if (path.includes('/cte.php')) return 'cte';
        if (path.includes('/mdfe.php')) return 'mdfe';
        if (path.includes('/eventos.php')) return 'eventos';
        return 'nfe'; // padrão
    }

    /**
     * 📋 Carregar documentos recentes
     */
    async loadRecentDocuments() {
        try {
            const response = await fetch('/sistema-frotas/fiscal/api/fiscal_documents.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    empresa_id: this.empresaId,
                    action: 'get_recent'
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success) {
                this.documents = data.data;
                this.updateDocumentsList();
            } else {
                console.error('❌ Erro ao carregar documentos:', data.message);
            }
        } catch (error) {
            console.error('❌ Erro ao carregar documentos fiscais:', error);
        }
    }

    /**
     * 🔄 Atualizar lista de documentos
     */
    updateDocumentsList() {
        const currentPage = this.getCurrentPage();
        
        // Atualizar apenas a lista relevante para a página atual
        switch (currentPage) {
            case 'nfe':
                this.updateNFEList();
                break;
            case 'cte':
                this.updateCTeList();
                break;
            case 'mdfe':
                this.updateMDFeList();
                break;
            case 'eventos':
                // Para eventos, não há lista de documentos específica
                break;
        }
    }

    /**
     * 📄 Atualizar lista de NF-e
     */
    updateNFEList() {
        const container = document.getElementById('nfeList');
        if (!container) return;

        container.innerHTML = '';
        
        if (this.documents.nfe && this.documents.nfe.length > 0) {
            this.documents.nfe.forEach(nfe => {
                const item = this.createDocumentItem(nfe, 'nfe');
                container.appendChild(item);
            });
        } else {
            container.innerHTML = '<div class="no-documents">Nenhuma NF-e encontrada</div>';
        }
    }

    /**
     * 🚛 Atualizar lista de CT-e
     */
    updateCTeList() {
        const container = document.getElementById('cteList');
        if (!container) return;

        container.innerHTML = '';
        
        if (this.documents.cte && this.documents.cte.length > 0) {
            this.documents.cte.forEach(cte => {
                const item = this.createDocumentItem(cte, 'cte');
                container.appendChild(item);
            });
        } else {
            container.innerHTML = '<div class="no-documents">Nenhum CT-e encontrado</div>';
        }
    }

    /**
     * 📋 Atualizar lista de MDF-e
     */
    updateMDFeList() {
        const container = document.getElementById('mdfeList');
        if (!container) return;

        container.innerHTML = '';
        
        if (this.documents.mdfe && this.documents.mdfe.length > 0) {
            this.documents.mdfe.forEach(mdfe => {
                const item = this.createDocumentItem(mdfe, 'mdfe');
                container.appendChild(item);
            });
        } else {
            container.innerHTML = '<div class="no-documents">Nenhum MDF-e encontrado</div>';
        }
    }

    /**
     * 🎯 Criar item de documento
     */
    createDocumentItem(doc, type) {
        const item = document.createElement('div');
        item.className = 'document-item';
        
        const statusClass = this.getStatusClass(doc.status);
        const statusText = this.getStatusText(doc.status);
        
        item.innerHTML = `
            <div class="document-header">
                <h4>${this.getDocumentTitle(doc, type)}</h4>
                <span class="status-badge ${statusClass}">${statusText}</span>
            </div>
            <div class="document-details">
                <p><strong>Data:</strong> ${this.formatDate(doc.data_emissao)}</p>
                <p><strong>Valor:</strong> ${this.formatCurrency(doc.valor_total || 0)}</p>
                ${this.getDocumentSpecificInfo(doc, type)}
            </div>
            <div class="document-actions">
                <button onclick="fiscalSystem.viewDocument('${type}', ${doc.id})" class="btn btn-sm btn-primary">
                    <i class="fas fa-eye"></i> Visualizar
                </button>
                <button onclick="fiscalSystem.downloadDocument('${type}', ${doc.id})" class="btn btn-sm btn-secondary">
                    <i class="fas fa-download"></i> Download
                </button>
                ${this.getActionButtons(doc, type)}
            </div>
        `;
        
        return item;
    }

    /**
     * 🏷️ Obter título do documento
     */
    getDocumentTitle(doc, type) {
        switch (type) {
            case 'nfe':
                return `NF-e ${doc.numero_nfe || doc.id}`;
            case 'cte':
                return `CT-e ${doc.numero_cte || doc.id}`;
            case 'mdfe':
                return `MDF-e ${doc.numero_mdfe || doc.id}`;
            default:
                return `Documento ${doc.id}`;
        }
    }

    /**
     * 📊 Obter informações específicas do documento
     */
    getDocumentSpecificInfo(doc, type) {
        switch (type) {
            case 'nfe':
                return `<p><strong>Cliente:</strong> ${doc.cliente_razao_social || 'N/A'}</p>`;
            case 'cte':
                return `
                    <p><strong>Origem:</strong> ${doc.origem_cidade || 'N/A'}</p>
                    <p><strong>Destino:</strong> ${doc.destino_cidade || 'N/A'}</p>
                `;
            case 'mdfe':
                return `
                    <p><strong>Tipo:</strong> ${doc.tipo_transporte || 'N/A'}</p>
                    <p><strong>Peso:</strong> ${doc.peso_total_carga || 0} kg</p>
                `;
            default:
                return '';
        }
    }

    /**
     * 🔘 Obter botões de ação específicos
     */
    getActionButtons(doc, type) {
        let buttons = '';
        
        if (doc.status === 'autorizado') {
            if (type === 'mdfe') {
                buttons += `
                    <button onclick="fiscalSystem.encerrarMDFe(${doc.id})" class="btn btn-sm btn-warning">
                        <i class="fas fa-lock"></i> Encerrar
                    </button>
                `;
            } else {
                buttons += `
                    <button onclick="fiscalSystem.cancelarDocumento('${type}', ${doc.id})" class="btn btn-sm btn-danger">
                        <i class="fas fa-ban"></i> Cancelar
                    </button>
                `;
            }
        }
        
        return buttons;
    }

    /**
     * 🎨 Obter classe CSS do status
     */
    getStatusClass(status) {
        switch (status) {
            case 'autorizado':
                return 'status-success';
            case 'pendente':
                return 'status-warning';
            case 'cancelado':
            case 'denegado':
                return 'status-danger';
            case 'encerrado':
                return 'status-info';
            default:
                return 'status-default';
        }
    }

    /**
     * 📝 Obter texto do status
     */
    getStatusText(status) {
        switch (status) {
            case 'autorizado':
                return 'Autorizado';
            case 'pendente':
                return 'Pendente';
            case 'cancelado':
                return 'Cancelado';
            case 'denegado':
                return 'Denegado';
            case 'encerrado':
                return 'Encerrado';
            default:
                return status;
        }
    }

    /**
     * 📋 Configurar abas de documentos
     */
    setupDocumentTabs() {
        const tabs = document.querySelectorAll('.document-tab');
        const contents = document.querySelectorAll('.document-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                
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
                
                this.currentTab = target;
            });
        });
    }

    /**
     * 🔧 Configurar modais
     */
    setupModals() {
        // Modal de importar NF-e
        const importModal = document.getElementById('importNFEModal');
        if (importModal) {
            importModal.addEventListener('show.bs.modal', () => {
                this.setupFileUpload();
            });
        }

        // Modal de emitir CT-e
        const cteModal = document.getElementById('emitirCTeModal');
        if (cteModal) {
            cteModal.addEventListener('show.bs.modal', () => {
                this.loadMotoristasVeiculos();
            });
        }

        // Modal de emitir MDF-e
        const mdfeModal = document.getElementById('emitirMDFeModal');
        if (mdfeModal) {
            mdfeModal.addEventListener('show.bs.modal', () => {
                this.loadMotoristasVeiculos();
            });
        }
    }

    /**
     * 🌐 Verificar status SEFAZ
     */
    async checkSefazStatus() {
        try {
            const response = await fetch('/sistema-frotas/fiscal/api/fiscal_sefaz_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    empresa_id: this.empresaId
                })
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.updateSefazStatus(data.data);
                }
            }
        } catch (error) {
            console.error('❌ Erro ao verificar status SEFAZ:', error);
        }
    }

    /**
     * 🔄 Atualizar status SEFAZ
     */
    updateSefazStatus(status) {
        const statusElement = document.getElementById('sefazStatus');
        if (!statusElement) return;

        const statusClass = status === 'online' ? 'status-success' : 'status-danger';
        const statusText = status === 'online' ? 'Online' : 'Offline';
        const statusIcon = status === 'online' ? 'fa-check-circle' : 'fa-times-circle';

        statusElement.innerHTML = `
            <span class="status-badge ${statusClass}">
                <i class="fas ${statusIcon}"></i> ${statusText}
            </span>
        `;
    }

    /**
     * 📁 Configurar upload de arquivos
     */
    setupFileUpload() {
        const fileInput = document.getElementById('xmlFile');
        const uploadArea = document.getElementById('uploadArea');
        
        if (fileInput && uploadArea) {
            fileInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    this.handleFileSelect(file, uploadArea);
                }
            });
        }
    }

    /**
     * 📄 Manipular seleção de arquivo
     */
    handleFileSelect(file, uploadArea) {
        if (file.type === 'application/xml' || file.name.endsWith('.xml')) {
            uploadArea.innerHTML = `
                <div class="file-selected">
                    <i class="fas fa-file-code"></i>
                    <span>${file.name}</span>
                    <small>${this.formatFileSize(file.size)}</small>
                </div>
            `;
        } else {
            uploadArea.innerHTML = `
                <div class="file-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Arquivo inválido. Selecione um arquivo XML.</span>
                </div>
            `;
        }
    }

    /**
     * 👥 Carregar motoristas e veículos
     */
    async loadMotoristasVeiculos() {
        try {
            const response = await fetch('/sistema-frotas/fiscal/api/fiscal_motoristas_veiculos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    empresa_id: this.empresaId
                })
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.populateMotoristasVeiculos(data.data);
                }
            }
        } catch (error) {
            console.error('❌ Erro ao carregar motoristas e veículos:', error);
        }
    }

    /**
     * 🔄 Preencher selects de motoristas e veículos
     */
    populateMotoristasVeiculos(data) {
        // Preencher motoristas
        const motoristaSelect = document.getElementById('motorista_id');
        if (motoristaSelect && data.motoristas) {
            motoristaSelect.innerHTML = '<option value="">Selecione um motorista</option>';
            data.motoristas.forEach(motorista => {
                const option = document.createElement('option');
                option.value = motorista.id;
                option.textContent = motorista.nome;
                motoristaSelect.appendChild(option);
            });
        }

        // Preencher veículos
        const veiculoSelect = document.getElementById('veiculo_id');
        if (veiculoSelect && data.veiculos) {
            veiculoSelect.innerHTML = '<option value="">Selecione um veículo</option>';
            data.veiculos.forEach(veiculo => {
                const option = document.createElement('option');
                option.value = veiculo.id;
                option.textContent = `${veiculo.placa} - ${veiculo.modelo}`;
                veiculoSelect.appendChild(option);
            });
        }
    }

    /**
     * 🔍 Visualizar documento
     */
    viewDocument(type, id) {
        // Implementar visualização do documento
        console.log(`Visualizando ${type} ID: ${id}`);
        this.showMessage(`Visualizando ${type.toUpperCase()} ID: ${id}`, 'info');
    }

    /**
     * 📥 Download de documento
     */
    downloadDocument(type, id) {
        // Implementar download do documento
        console.log(`Download ${type} ID: ${id}`);
        this.showMessage(`Download ${type.toUpperCase()} ID: ${id}`, 'info');
    }

    /**
     * ❌ Cancelar documento
     */
    async cancelarDocumento(type, id) {
        const justificativa = prompt('Digite a justificativa para o cancelamento:');
        if (!justificativa) return;

        try {
            const response = await fetch('/sistema-frotas/fiscal/api/fiscal_events.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'cancelar',
                    empresa_id: this.empresaId,
                    documento_tipo: type,
                    documento_id: id,
                    justificativa: justificativa
                })
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.showMessage('Documento cancelado com sucesso!', 'success');
                    this.loadRecentDocuments(); // Recarregar lista
                } else {
                    this.showMessage(`Erro ao cancelar: ${data.message}`, 'error');
                }
            }
        } catch (error) {
            console.error('❌ Erro ao cancelar documento:', error);
            this.showMessage('Erro ao cancelar documento', 'error');
        }
    }

    /**
     * 🔒 Encerrar MDF-e
     */
    async encerrarMDFe(id) {
        if (!confirm('Tem certeza que deseja encerrar este MDF-e?')) return;

        try {
            const response = await fetch('/sistema-frotas/fiscal/api/fiscal_events.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'encerrar',
                    empresa_id: this.empresaId,
                    documento_tipo: 'mdfe',
                    documento_id: id
                })
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.showMessage('MDF-e encerrado com sucesso!', 'success');
                    this.loadRecentDocuments(); // Recarregar lista
                } else {
                    this.showMessage(`Erro ao encerrar: ${data.message}`, 'error');
                }
            }
        } catch (error) {
            console.error('❌ Erro ao encerrar MDF-e:', error);
            this.showMessage('Erro ao encerrar MDF-e', 'error');
        }
    }

    /**
     * 📤 Importar NF-e XML
     */
    async importarNFEXML() {
        const fileInput = document.getElementById('xmlFile');
        const file = fileInput.files[0];
        
        if (!file) {
            this.showMessage('Selecione um arquivo XML', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('xml_file', file);
        formData.append('empresa_id', this.empresaId);
        formData.append('action', 'importar_xml');

        try {
            const response = await fetch('/sistema-frotas/fiscal/api/fiscal_nfe.php', {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.showMessage('NF-e importada com sucesso!', 'success');
                    this.loadRecentDocuments(); // Recarregar lista
                    this.closeModal('importNFEModal');
                } else {
                    this.showMessage(`Erro ao importar: ${data.message}`, 'error');
                }
            }
        } catch (error) {
            console.error('❌ Erro ao importar NF-e:', error);
            this.showMessage('Erro ao importar NF-e', 'error');
        }
    }

    /**
     * 🚛 Emitir CT-e
     */
    async emitirCTe() {
        const form = document.getElementById('cteForm');
        const formData = new FormData(form);
        formData.append('empresa_id', this.empresaId);
        formData.append('action', 'emitir');

        try {
            const response = await fetch('/sistema-frotas/fiscal/api/fiscal_cte.php', {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.showMessage('CT-e emitido com sucesso!', 'success');
                    this.loadRecentDocuments(); // Recarregar lista
                    this.closeModal('emitirCTeModal');
                } else {
                    this.showMessage(`Erro ao emitir: ${data.message}`, 'error');
                }
            }
        } catch (error) {
            console.error('❌ Erro ao emitir CT-e:', error);
            this.showMessage('Erro ao emitir CT-e', 'error');
        }
    }

    /**
     * 📋 Emitir MDF-e
     */
    async emitirMDFe() {
        const form = document.getElementById('mdfeForm');
        const formData = new FormData(form);
        formData.append('empresa_id', this.empresaId);
        formData.append('action', 'emitir');

        try {
            const response = await fetch('/sistema-frotas/fiscal/api/fiscal_mdfe.php', {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.showMessage('MDF-e emitido com sucesso!', 'success');
                    this.loadRecentDocuments(); // Recarregar lista
                    this.closeModal('emitirMDFeModal');
                } else {
                    this.showMessage(`Erro ao emitir: ${data.message}`, 'error');
                }
            }
        } catch (error) {
            console.error('❌ Erro ao emitir MDF-e:', error);
            this.showMessage('Erro ao emitir MDF-e', 'error');
        }
    }

    /**
     * 🔒 Fechar modal
     */
    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) {
                bsModal.hide();
            }
        }
    }

    /**
     * 📅 Formatar data
     */
    formatDate(dateString) {
        if (!dateString) return 'N/A';
        
        const date = new Date(dateString);
        return date.toLocaleDateString('pt-BR');
    }

    /**
     * 💰 Formatar moeda
     */
    formatCurrency(value) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(value);
    }

    /**
     * 📏 Formatar tamanho de arquivo
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    /**
     * 💬 Mostrar mensagem
     */
    showMessage(message, type = 'info') {
        // Implementar sistema de notificações
        console.log(`${type.toUpperCase()}: ${message}`);
        
        // Usar SweetAlert2 se disponível
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: type === 'success' ? '✅ Sucesso!' : 
                       type === 'error' ? '❌ Erro!' : 
                       type === 'warning' ? '⚠️ Atenção!' : 'ℹ️ Informação',
                text: message,
                icon: type,
                timer: type === 'success' ? 3000 : undefined,
                timerProgressBar: type === 'success'
            });
        } else {
            // Fallback para alert simples
            alert(`${type.toUpperCase()}: ${message}`);
        }
    }
}

// 🚀 Inicializar sistema fiscal quando a página carregar
document.addEventListener('DOMContentLoaded', () => {
    window.fiscalSystem = new FiscalSystem();
});

// 📋 Funções globais para uso nos modais
function importarNFEXML() {
    if (window.fiscalSystem) {
        window.fiscalSystem.importarNFEXML();
    }
}

function emitirCTe() {
    if (window.fiscalSystem) {
        window.fiscalSystem.emitirCTe();
    }
}

function emitirMDFe() {
    if (window.fiscalSystem) {
        window.fiscalSystem.emitirMDFe();
    }
}
