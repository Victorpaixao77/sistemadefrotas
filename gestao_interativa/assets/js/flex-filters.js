/**
 * Sistema de Filtros - Modo Flexível
 * Gerencia filtros avançados para pneus
 */

class FlexFilters {
    constructor() {
        this.filters = {
            status: [],
            marca: '',
            modelo: '',
            tamanho: '',
            precoMin: '',
            precoMax: '',
            dataInicio: '',
            dataFim: '',
            veiculo: '',
            posicao: ''
        };
        this.activeFilters = new Set();
        this.isInitialized = false;
        this.init();
    }

    init() {
        this.createFilters();
        this.loadFilterOptions();
        this.bindEvents();
        this.isInitialized = true;
        console.log('FlexFilters inicializado');
    }

    createFilters() {
        const container = document.querySelector('.flex-container');
        if (!container) {
            console.error('Container flex não encontrado');
            return;
        }

        // Criar filtros se não existir
        if (!document.querySelector('.flex-filters')) {
            const filtersHTML = `
                <div class="flex-filters">
                    <div class="filters-header">
                        <div class="filters-title">
                            <i class="fas fa-filter"></i>
                            Filtros Avançados
                        </div>
                        <div class="filters-actions">
                            <button class="filter-btn" data-action="expand">
                                <i class="fas fa-expand"></i>
                                Expandir
                            </button>
                            <button class="filter-btn" data-action="collapse">
                                <i class="fas fa-compress"></i>
                                Recolher
                            </button>
                        </div>
                    </div>
                    
                    <div class="filters-content">
                        <div class="filters-grid">
                            <div class="filter-group">
                                <label class="filter-label">
                                    <i class="fas fa-search"></i>
                                    Buscar Pneu
                                </label>
                                <input type="text" class="filter-input" id="filter-search" placeholder="Código, marca, modelo...">
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">
                                    <i class="fas fa-tag"></i>
                                    Status
                                </label>
                                <div class="status-filters">
                                    <span class="status-filter disponivel" data-status="disponivel">
                                        <i class="fas fa-circle"></i> Disponível
                                    </span>
                                    <span class="status-filter em-uso" data-status="em-uso">
                                        <i class="fas fa-circle"></i> Em Uso
                                    </span>
                                    <span class="status-filter manutencao" data-status="manutencao">
                                        <i class="fas fa-circle"></i> Manutenção
                                    </span>
                                    <span class="status-filter critico" data-status="critico">
                                        <i class="fas fa-circle"></i> Crítico
                                    </span>
                                    <span class="status-filter alerta" data-status="alerta">
                                        <i class="fas fa-circle"></i> Alerta
                                    </span>
                                </div>
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">
                                    <i class="fas fa-tag"></i>
                                    Marca
                                </label>
                                <select class="filter-select" id="filter-marca">
                                    <option value="">Todas as marcas</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">
                                    <i class="fas fa-ruler"></i>
                                    Tamanho
                                </label>
                                <select class="filter-select" id="filter-tamanho">
                                    <option value="">Todos os tamanhos</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">
                                    <i class="fas fa-dollar-sign"></i>
                                    Faixa de Preço
                                </label>
                                <div class="price-range">
                                    <input type="number" class="filter-input" id="filter-preco-min" placeholder="Mín">
                                    <span class="separator">-</span>
                                    <input type="number" class="filter-input" id="filter-preco-max" placeholder="Máx">
                                </div>
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">
                                    <i class="fas fa-calendar"></i>
                                    Data de Instalação
                                </label>
                                <div class="date-filters">
                                    <input type="date" class="filter-input" id="filter-data-inicio">
                                    <span class="separator">até</span>
                                    <input type="date" class="filter-input" id="filter-data-fim">
                                </div>
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">
                                    <i class="fas fa-truck"></i>
                                    Veículo
                                </label>
                                <select class="filter-select" id="filter-veiculo">
                                    <option value="">Todos os veículos</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">
                                    <i class="fas fa-map-marker-alt"></i>
                                    Posição
                                </label>
                                <select class="filter-select" id="filter-posicao">
                                    <option value="">Todas as posições</option>
                                    <option value="dianteira-esquerda">Dianteira Esquerda</option>
                                    <option value="dianteira-direita">Dianteira Direita</option>
                                    <option value="traseira-esquerda">Traseira Esquerda</option>
                                    <option value="traseira-direita">Traseira Direita</option>
                                    <option value="eixo-auxiliar">Eixo Auxiliar</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="filters-actions-bottom">
                            <div class="filters-left">
                                <div class="results-counter">
                                    <i class="fas fa-list"></i>
                                    <span>Resultados: <span class="count" id="filter-count">0</span></span>
                                </div>
                            </div>
                            <div class="filters-right">
                                <button class="btn-clear-filters" onclick="flexFilters.clearFilters()">
                                    <i class="fas fa-times"></i>
                                    Limpar
                                </button>
                                <button class="btn-apply-filters" onclick="flexFilters.applyFilters()">
                                    <i class="fas fa-check"></i>
                                    Aplicar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Inserir após o dashboard
            const dashboard = document.querySelector('.flex-dashboard');
            if (dashboard) {
                dashboard.insertAdjacentHTML('afterend', filtersHTML);
            } else {
                container.insertAdjacentHTML('afterbegin', filtersHTML);
            }
        }
    }

    async loadFilterOptions() {
        try {
            const response = await fetch('api/flex-filters.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_options'
                })
            });

            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    this.populateFilterOptions(data.options);
                }
            }
        } catch (error) {
            console.error('Erro ao carregar opções de filtro:', error);
            this.loadMockOptions();
        }
    }

    loadMockOptions() {
        // Dados mock para desenvolvimento
        const mockOptions = {
            marcas: ['Michelin', 'Bridgestone', 'Goodyear', 'Pirelli', 'Continental'],
            tamanhos: ['205/55R16', '215/55R17', '225/45R17', '235/45R18', '245/40R18'],
            veiculos: ['Caminhão 1', 'Caminhão 2', 'Caminhão 3', 'Van 1', 'Van 2']
        };

        this.populateFilterOptions(mockOptions);
    }

    populateFilterOptions(options) {
        // Popular marcas
        const marcaSelect = document.getElementById('filter-marca');
        if (marcaSelect && options.marcas) {
            options.marcas.forEach(marca => {
                const option = document.createElement('option');
                option.value = marca;
                option.textContent = marca;
                marcaSelect.appendChild(option);
            });
        }

        // Popular tamanhos
        const tamanhoSelect = document.getElementById('filter-tamanho');
        if (tamanhoSelect && options.tamanhos) {
            options.tamanhos.forEach(tamanho => {
                const option = document.createElement('option');
                option.value = tamanho;
                option.textContent = tamanho;
                tamanhoSelect.appendChild(option);
            });
        }

        // Popular veículos
        const veiculoSelect = document.getElementById('filter-veiculo');
        if (veiculoSelect && options.veiculos) {
            options.veiculos.forEach(veiculo => {
                const option = document.createElement('option');
                option.value = veiculo;
                option.textContent = veiculo;
                veiculoSelect.appendChild(option);
            });
        }
    }

    bindEvents() {
        // Eventos dos botões de filtro
        document.addEventListener('click', (e) => {
            if (e.target.closest('.filter-btn')) {
                const action = e.target.closest('.filter-btn').dataset.action;
                this.handleFilterAction(action);
            }

            if (e.target.closest('.status-filter')) {
                const statusFilter = e.target.closest('.status-filter');
                const status = statusFilter.dataset.status;
                this.toggleStatusFilter(status, statusFilter);
            }
        });

        // Eventos de input
        document.addEventListener('input', (e) => {
            if (e.target.matches('.filter-input, .filter-select')) {
                this.handleInputChange(e.target);
            }
        });

        // Eventos de teclas
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && e.target.matches('.filter-input')) {
                this.applyFilters();
            }
        });
    }

    handleFilterAction(action) {
        const filtersContent = document.querySelector('.filters-content');
        const expandBtn = document.querySelector('[data-action="expand"]');
        const collapseBtn = document.querySelector('[data-action="collapse"]');

        switch (action) {
            case 'expand':
                filtersContent.style.display = 'block';
                expandBtn.style.display = 'none';
                collapseBtn.style.display = 'inline-flex';
                break;
            case 'collapse':
                filtersContent.style.display = 'none';
                expandBtn.style.display = 'inline-flex';
                collapseBtn.style.display = 'none';
                break;
        }
    }

    toggleStatusFilter(status, element) {
        if (element.classList.contains('active')) {
            element.classList.remove('active');
            this.filters.status = this.filters.status.filter(s => s !== status);
        } else {
            element.classList.add('active');
            this.filters.status.push(status);
        }
        this.activeFilters.add('status');
    }

    handleInputChange(input) {
        const field = input.id.replace('filter-', '');
        this.filters[field] = input.value;
        
        if (input.value) {
            this.activeFilters.add(field);
        } else {
            this.activeFilters.delete(field);
        }
    }

    async applyFilters() {
        try {
            const filtersContainer = document.querySelector('.flex-filters');
            filtersContainer.classList.add('loading');

            // Coletar valores dos filtros
            this.collectFilterValues();

            // Aplicar filtros na interface
            this.applyFiltersToUI();

            // Atualizar contador
            this.updateResultsCount();

            // Notificar outros componentes
            this.notifyFiltersApplied();

        } catch (error) {
            console.error('Erro ao aplicar filtros:', error);
            this.showError('Erro ao aplicar filtros');
        } finally {
            const filtersContainer = document.querySelector('.flex-filters');
            filtersContainer.classList.remove('loading');
        }
    }

    collectFilterValues() {
        this.filters = {
            search: document.getElementById('filter-search')?.value || '',
            status: this.filters.status,
            marca: document.getElementById('filter-marca')?.value || '',
            tamanho: document.getElementById('filter-tamanho')?.value || '',
            precoMin: document.getElementById('filter-preco-min')?.value || '',
            precoMax: document.getElementById('filter-preco-max')?.value || '',
            dataInicio: document.getElementById('filter-data-inicio')?.value || '',
            dataFim: document.getElementById('filter-data-fim')?.value || '',
            veiculo: document.getElementById('filter-veiculo')?.value || '',
            posicao: document.getElementById('filter-posicao')?.value || ''
        };
    }

    applyFiltersToUI() {
        // Aplicar filtros nos elementos da interface
        const pneus = document.querySelectorAll('.pneu-slot');
        
        pneus.forEach(pneu => {
            const shouldShow = this.shouldShowPneu(pneu);
            pneu.style.display = shouldShow ? 'block' : 'none';
        });
    }

    shouldShowPneu(pneuElement) {
        const pneuData = this.getPneuData(pneuElement);
        
        // Filtro de busca
        if (this.filters.search) {
            const searchTerm = this.filters.search.toLowerCase();
            const matches = pneuData.codigo?.toLowerCase().includes(searchTerm) ||
                           pneuData.marca?.toLowerCase().includes(searchTerm) ||
                           pneuData.modelo?.toLowerCase().includes(searchTerm);
            if (!matches) return false;
        }

        // Filtro de status
        if (this.filters.status.length > 0) {
            if (!this.filters.status.includes(pneuData.status)) {
                return false;
            }
        }

        // Filtro de marca
        if (this.filters.marca && pneuData.marca !== this.filters.marca) {
            return false;
        }

        // Filtro de tamanho
        if (this.filters.tamanho && pneuData.tamanho !== this.filters.tamanho) {
            return false;
        }

        // Filtro de preço
        if (this.filters.precoMin && pneuData.preco < parseFloat(this.filters.precoMin)) {
            return false;
        }
        if (this.filters.precoMax && pneuData.preco > parseFloat(this.filters.precoMax)) {
            return false;
        }

        // Filtro de veículo
        if (this.filters.veiculo && pneuData.veiculo !== this.filters.veiculo) {
            return false;
        }

        // Filtro de posição
        if (this.filters.posicao && pneuData.posicao !== this.filters.posicao) {
            return false;
        }

        return true;
    }

    getPneuData(pneuElement) {
        // Extrair dados do elemento pneu (implementar conforme estrutura HTML)
        return {
            codigo: pneuElement.dataset.codigo,
            marca: pneuElement.dataset.marca,
            modelo: pneuElement.dataset.modelo,
            status: pneuElement.dataset.status,
            tamanho: pneuElement.dataset.tamanho,
            preco: parseFloat(pneuElement.dataset.preco) || 0,
            veiculo: pneuElement.dataset.veiculo,
            posicao: pneuElement.dataset.posicao
        };
    }

    updateResultsCount() {
        const visiblePneus = document.querySelectorAll('.pneu-slot[style*="display: block"], .pneu-slot:not([style*="display: none"])');
        const countElement = document.getElementById('filter-count');
        if (countElement) {
            countElement.textContent = visiblePneus.length;
        }
    }

    notifyFiltersApplied() {
        // Disparar evento customizado
        const event = new CustomEvent('filtersApplied', {
            detail: { filters: this.filters }
        });
        document.dispatchEvent(event);
    }

    clearFilters() {
        // Limpar todos os filtros
        this.filters = {
            search: '',
            status: [],
            marca: '',
            tamanho: '',
            precoMin: '',
            precoMax: '',
            dataInicio: '',
            dataFim: '',
            veiculo: '',
            posicao: ''
        };

        // Limpar interface
        document.querySelectorAll('.filter-input').forEach(input => {
            input.value = '';
        });

        document.querySelectorAll('.filter-select').forEach(select => {
            select.selectedIndex = 0;
        });

        document.querySelectorAll('.status-filter').forEach(filter => {
            filter.classList.remove('active');
        });

        // Mostrar todos os pneus
        document.querySelectorAll('.pneu-slot').forEach(pneu => {
            pneu.style.display = 'block';
        });

        // Atualizar contador
        this.updateResultsCount();

        // Limpar filtros ativos
        this.activeFilters.clear();

        console.log('Filtros limpos');
    }

    showError(message) {
        const filtersContainer = document.querySelector('.flex-filters');
        if (filtersContainer) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'filters-error';
            errorDiv.innerHTML = `
                <i class="fas fa-exclamation-circle"></i>
                <span>${message}</span>
            `;
            filtersContainer.appendChild(errorDiv);

            setTimeout(() => {
                errorDiv.remove();
            }, 5000);
        }
    }

    // Métodos públicos
    getActiveFilters() {
        return this.filters;
    }

    hasActiveFilters() {
        return this.activeFilters.size > 0;
    }

    destroy() {
        this.isInitialized = false;
        console.log('FlexFilters destruído');
    }
}

// Inicializar filtros quando o DOM estiver pronto
let flexFilters;

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        if (document.querySelector('.flex-container')) {
            flexFilters = new FlexFilters();
        }
    }, 1500);
});

// Exportar para uso global
window.flexFilters = flexFilters; 