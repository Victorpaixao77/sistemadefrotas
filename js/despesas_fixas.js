(function (g) {
    g.sfApiUrl = g.sfApiUrl || function (rel) {
        rel = String(rel || '').replace(/^\//, '');
        var b = typeof g.__SF_API_BASE__ === 'string' && g.__SF_API_BASE__ !== ''
            ? String(g.__SF_API_BASE__).replace(/\/+$/, '')
            : '';
        if (b) return b + '/' + rel;
        try { return new URL('../api/' + rel, g.location.href).href; }
        catch (e) { return '../api/' + rel; }
    };
})(typeof window !== 'undefined' ? window : this);

var dfSortField = 'vencimento';
var dfSortDir = 'DESC';

var DF_ALLOWED_SORT = ['vencimento', 'veiculo_placa', 'tipo_nome', 'descricao', 'valor', 'status_nome', 'data_pagamento', 'forma_pagamento_nome', 'repetir'];

function dfDefaultSortDir(field) {
    const textLike = ['veiculo_placa', 'tipo_nome', 'descricao', 'status_nome', 'forma_pagamento_nome'];
    if (textLike.indexOf(field) >= 0) return 'ASC';
    return 'DESC';
}

function syncDfSortIndicators() {
    const table = document.getElementById('despesasTable');
    if (!table) return;
    table.querySelectorAll('thead th.sortable').forEach(function (th) {
        const field = th.getAttribute('data-sort');
        const ind = th.querySelector('.sort-ind');
        if (!ind) return;
        const on = field === dfSortField;
        th.classList.toggle('sorted', on);
        ind.textContent = on ? (dfSortDir === 'ASC' ? '▲' : '▼') : '⇅';
    });
}

function wireDespesasFixasSortHeaders() {
    const table = document.getElementById('despesasTable');
    if (!table) return;
    table.querySelectorAll('thead th.sortable').forEach(function (th) {
        th.addEventListener('click', function () {
            const field = th.getAttribute('data-sort');
            if (!field) return;
            if (dfSortField === field) {
                dfSortDir = dfSortDir === 'ASC' ? 'DESC' : 'ASC';
            } else {
                dfSortField = field;
                dfSortDir = dfDefaultSortDir(field);
            }
            syncDfSortIndicators();
            loadDespesasData(1);
        });
    });
}

// Global variables
let currentFilter = null;
let currentPage = 1;
let charts = {
    tipo: null,
    status: null,
    veiculos: null,
    pagamentos: null
};

// Initialize charts
function initializeDespesasTipoChart() {
    const ctx = document.getElementById('despesasTipoChart').getContext('2d');
    if (charts.tipo) {
        charts.tipo.destroy();
    }
    
    charts.tipo = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: [],
            datasets: [{
                data: [],
                backgroundColor: [
                    '#3b82f6', // Azul
                    '#10b981', // Verde
                    '#f59e0b', // Amarelo
                    '#ef4444', // Vermelho
                    '#8b5cf6', // Roxo
                    '#ec4899', // Rosa
                    '#14b8a6', // Turquesa
                    '#6b7280'  // Cinza
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                }
            }
        }
    });
}

function initializeStatusDespesasChart() {
    const ctx = document.getElementById('statusDespesasChart').getContext('2d');
    if (charts.status) {
        charts.status.destroy();
    }
    
    charts.status = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: [],
            datasets: [{
                data: [],
                backgroundColor: [
                    '#10b981', // Verde - Pago
                    '#f59e0b', // Amarelo - Pendente
                    '#ef4444', // Vermelho - Vencido
                    '#6b7280'  // Cinza - Cancelado
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                }
            }
        }
    });
}

function initializeTopVeiculosChart() {
    const ctx = document.getElementById('topVeiculosChart').getContext('2d');
    if (charts.veiculos) {
        charts.veiculos.destroy();
    }
    
    charts.veiculos = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                data: [],
                backgroundColor: '#3b82f6',
                borderColor: '#2563eb',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'R$ ' + value.toLocaleString('pt-BR');
                        }
                    }
                }
            }
        }
    });
}

function initializeFormasPagamentoChart() {
    const ctx = document.getElementById('formasPagamentoChart').getContext('2d');
    if (charts.pagamentos) {
        charts.pagamentos.destroy();
    }
    
    charts.pagamentos = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: [],
            datasets: [{
                data: [],
                backgroundColor: [
                    '#3b82f6', // Azul
                    '#10b981', // Verde
                    '#f59e0b', // Amarelo
                    '#ef4444', // Vermelho
                    '#8b5cf6'  // Roxo
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                }
            }
        }
    });
}

// Load data functions
async function loadVehicles() {
    try {
        const response = await fetch(sfApiUrl('veiculos.php?action=list'));
        if (!response.ok) throw new Error('Erro ao carregar veículos');
        
        const result = await response.json();
        const data = result.veiculos || [];
        
        // Get both select elements
        const modalSelect = document.getElementById('veiculo_id');
        const filterSelect = document.getElementById('vehicleFilter');
        
        // Clear and populate modal select
        if (modalSelect) {
            modalSelect.innerHTML = '<option value="">Selecione um veículo</option>';
            data.forEach(veiculo => {
                const option = document.createElement('option');
                option.value = veiculo.id;
                option.textContent = `${veiculo.placa} - ${veiculo.modelo}`;
                modalSelect.appendChild(option);
            });
        }
        
        // Clear and populate filter select
        if (filterSelect) {
            filterSelect.innerHTML = '<option value="">Todos os veículos</option>';
            data.forEach(veiculo => {
                const option = document.createElement('option');
                option.value = veiculo.id;
                option.textContent = `${veiculo.placa} - ${veiculo.modelo}`;
                filterSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar veículos:', error);
    }
}

async function loadPaymentMethods() {
    try {
        const response = await fetch(sfApiUrl('formas_pagamento.php?action=list'));
        if (!response.ok) throw new Error('Erro ao carregar formas de pagamento');
        
        const data = await response.json();
        
        // Get both select elements
        const modalSelect = document.getElementById('forma_pagamento_id');
        const filterSelect = document.getElementById('paymentFilter');
        
        // Clear and populate modal select
        if (modalSelect) {
            modalSelect.innerHTML = '<option value="">Selecione a forma de pagamento</option>';
            data.forEach(forma => {
                const option = document.createElement('option');
                option.value = forma.id;
                option.textContent = forma.nome;
                modalSelect.appendChild(option);
            });
        }
        
        // Clear and populate filter select
        if (filterSelect) {
            filterSelect.innerHTML = '<option value="">Todas as formas de pagamento</option>';
            data.forEach(forma => {
                const option = document.createElement('option');
                option.value = forma.id;
                option.textContent = forma.nome;
                filterSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar formas de pagamento:', error);
    }
}

async function loadDespesasData(page = null) {
    try {
        // Update current page if provided; senão usar página da URL para ficar igual ao PHP
        if (page !== null) {
            currentPage = page;
        } else {
            const params = new URLSearchParams(window.location.search);
            const pageFromUrl = parseInt(params.get('page'), 10);
            if (!isNaN(pageFromUrl) && pageFromUrl >= 1) {
                currentPage = pageFromUrl;
            }
        }
        
        // Get filter values
        const search = document.getElementById('searchDespesa').value;
        const vehicleFilter = document.getElementById('vehicleFilter').value;
        const tipoFilter = document.getElementById('tipoFilter').value;
        const statusFilter = document.getElementById('statusFilter').value;
        const paymentFilter = document.getElementById('paymentFilter').value;
        
        // Build URL with filters (per_page igual ao select da página)
        const perPageSelect = document.querySelector('select.filter-per-page');
        const perPage = perPageSelect ? perPageSelect.value : '10';
        let url = sfApiUrl('despesas_fixas.php?action=list');
        url += `&page=${currentPage}&per_page=${perPage}`;
        if (search) url += `&search=${encodeURIComponent(search)}`;
        if (vehicleFilter) url += `&veiculo=${encodeURIComponent(vehicleFilter)}`;
        if (tipoFilter) url += `&tipo=${encodeURIComponent(tipoFilter)}`;
        if (statusFilter) url += `&status=${encodeURIComponent(statusFilter)}`;
        if (paymentFilter) url += `&pagamento=${encodeURIComponent(paymentFilter)}`;
        if (currentFilter) {
            const [year, month] = currentFilter.split('-');
            url += `&year=${year}&month=${month}`;
        }
        url += `&sort=${encodeURIComponent(dfSortField)}&dir=${encodeURIComponent(dfSortDir)}`;

        const isModern = document.body.classList.contains('despesas-fixas-modern');
        const hasListFilters = !!(search || vehicleFilter || tipoFilter || statusFilter || paymentFilter || currentFilter);
        const isDefaultSort = dfSortField === 'vencimento' && dfSortDir === 'DESC';
        const urlParams = new URLSearchParams();
        urlParams.set('page', String(currentPage));
        urlParams.set('per_page', String(perPage));
        if (!isModern) urlParams.set('classic', '1');
        if (!isDefaultSort) {
            urlParams.set('sort', dfSortField);
            urlParams.set('dir', dfSortDir);
        }
        const isDefaultPage = currentPage === 1 && parseInt(perPage, 10) === 10 && !hasListFilters && isDefaultSort;
        const desiredSearch = isDefaultPage && isModern ? '' : ('?' + urlParams.toString());
        if (window.location.search !== desiredSearch) {
            window.history.replaceState({}, '', window.location.pathname + desiredSearch);
        }

        const response = await fetch(url);
        if (!response.ok) throw new Error('Erro ao carregar dados');
        
        const data = await response.json();
        
        // Update table and pagination (padrão: "Página X de Y (N registros)")
        updateDespesasTable(data.despesas);
        updatePagination(data.pagina_atual, data.total_paginas, data.total_registros || 0);
        syncDfSortIndicators();
        updateKPICards(data.metrics);
        updateCharts(data.charts);
        
    } catch (error) {
        console.error('Erro ao carregar dados:', error);
    }
}

// Update UI functions
function updateDespesasTable(despesas) {
    const tbody = document.querySelector('#despesasTable tbody');
    tbody.innerHTML = '';
    
    despesas.forEach(despesa => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${formatDate(despesa.vencimento)}</td>
            <td>${despesa.veiculo_placa || '-'}</td>
            <td>${despesa.tipo_nome || '-'}</td>
            <td>${despesa.descricao || '-'}</td>
            <td>R$ ${formatNumber(despesa.valor, 2)}</td>
            <td>${despesa.status_nome || '-'}</td>
            <td>${despesa.data_pagamento ? formatDate(despesa.data_pagamento) : '-'}</td>
            <td>${despesa.forma_pagamento_nome || '-'}</td>
            <td>${despesa.repetir_automaticamente ? 'Sim' : 'Não'}</td>
            <td class="actions">
                <button class="btn-icon edit-btn" data-id="${despesa.id}" title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
                ${despesa.comprovante ? `
                    <button class="btn-icon view-comprovante-btn" data-comprovante="${despesa.comprovante}" title="Ver Comprovante">
                        <i class="fas fa-file-alt"></i>
                    </button>
                ` : ''}
                <button class="btn-icon delete-btn" data-id="${despesa.id}" title="Excluir">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    
    // Reattach event listeners
    setupTableButtons();
}

function updateKPICards(metrics) {
    if (!metrics) return;
    function setKpi(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
    }
    setKpi('dfKpiQtd', String(metrics.total_despesas || '0'));
    setKpi('dfKpiValor', `R$ ${formatNumber(metrics.valor_total || 0, 2)}`);
    setKpi('dfKpiPendentes', String(metrics.total_pendentes || '0'));
    setKpi('dfKpiVencidas', String(metrics.total_vencidas || '0'));
}

function updateCharts(data) {
    if (!data) return;
    
    // Initialize charts if they don't exist
    if (!charts.tipo) initializeDespesasTipoChart();
    if (!charts.status) initializeStatusDespesasChart();
    if (!charts.veiculos) initializeTopVeiculosChart();
    if (!charts.pagamentos) initializeFormasPagamentoChart();
    
    // Update tipos chart
    if (data.tipos) {
        charts.tipo.data.labels = data.tipos.labels;
        charts.tipo.data.datasets[0].data = data.tipos.valores;
        charts.tipo.update();
    }
    
    // Update status chart
    if (data.status) {
        charts.status.data.labels = data.status.labels;
        charts.status.data.datasets[0].data = data.status.valores;
        charts.status.update();
    }
    
    // Update veículos chart
    if (data.veiculos) {
        charts.veiculos.data.labels = data.veiculos.labels;
        charts.veiculos.data.datasets[0].data = data.veiculos.valores;
        charts.veiculos.update();
    }
    
    // Update formas de pagamento chart
    if (data.pagamentos) {
        charts.pagamentos.data.labels = data.pagamentos.labels;
        charts.pagamentos.data.datasets[0].data = data.pagamentos.valores;
        charts.pagamentos.update();
    }
}

// Add pagination update function (padrão igual outras telas: "Página X de Y (N registros)")
function updatePagination(currentPage, totalPages, totalRegistros) {
    const paginationDiv = document.querySelector('.pagination');
    if (!paginationDiv) return;
    
    const prevBtn = paginationDiv.querySelector('a:first-child');
    const nextBtn = paginationDiv.querySelector('a:last-child');
    const pageInfo = paginationDiv.querySelector('.pagination-info');
    
    const total = typeof totalRegistros === 'number' ? totalRegistros : 0;
    const totalPagesNum = Math.max(1, parseInt(totalPages, 10) || 1);
    
    // Update page info: "Página X de Y (N registros)"
    pageInfo.textContent = `Página ${currentPage} de ${totalPagesNum} (${total} registros)`;
    
    // Update buttons state and href
    prevBtn.classList.toggle('disabled', currentPage <= 1);
    nextBtn.classList.toggle('disabled', currentPage >= totalPagesNum);
    
    // Atualizar href dos links para manter per_page na URL
    const perPageSelect = document.querySelector('select.filter-per-page');
    const perPage = perPageSelect ? perPageSelect.value : '10';
    const classic = document.body.classList.contains('despesas-fixas-modern') ? '' : '&classic=1';
    const sortQs = (dfSortField !== 'vencimento' || dfSortDir !== 'DESC')
        ? `&sort=${encodeURIComponent(dfSortField)}&dir=${encodeURIComponent(dfSortDir)}`
        : '';
    prevBtn.href = currentPage > 1 ? `?page=${currentPage - 1}&per_page=${perPage}${classic}${sortQs}` : '#';
    nextBtn.href = currentPage < totalPagesNum ? `?page=${currentPage + 1}&per_page=${perPage}${classic}${sortQs}` : '#';
    
    // Remove old event listeners
    prevBtn.replaceWith(prevBtn.cloneNode(true));
    nextBtn.replaceWith(nextBtn.cloneNode(true));
    
    // Add new event listeners
    const newPrevBtn = paginationDiv.querySelector('a:first-child');
    const newNextBtn = paginationDiv.querySelector('a:last-child');
    
    newPrevBtn.addEventListener('click', (e) => {
        e.preventDefault();
        if (currentPage > 1) {
            loadDespesasData(currentPage - 1);
        }
    });
    
    newNextBtn.addEventListener('click', (e) => {
        e.preventDefault();
        if (currentPage < totalPagesNum) {
            loadDespesasData(currentPage + 1);
        }
    });
}

// Modal functions
function showAddDespesaModal() {
    document.getElementById('despesaForm').reset();
    document.getElementById('despesaId').value = '';
    document.getElementById('modalTitle').textContent = 'Nova Despesa Fixa';
    document.getElementById('despesaModal').classList.add('active');
    
    // Reload vehicles when opening the modal
    loadVehicles().catch(error => console.error('Erro ao recarregar veículos:', error));
}

async function showEditDespesaModal(id) {
    await loadVehicles();
    fetch(sfApiUrl(`despesas_fixas.php?action=get&id=${id}`))
        .then(response => response.json())
        .then(data => {
            document.getElementById('despesaId').value = data.id;
            document.getElementById('veiculo_id').value = data.veiculo_id;
            document.getElementById('tipo_despesa_id').value = data.tipo_despesa_id;
            document.getElementById('valor').value = data.valor;
            document.getElementById('vencimento').value = data.vencimento;
            document.getElementById('status_pagamento_id').value = data.status_pagamento_id;
            document.getElementById('forma_pagamento_id').value = data.forma_pagamento_id;
            document.getElementById('data_pagamento').value = data.data_pagamento || '';
            document.getElementById('repetir_automaticamente').value = data.repetir_automaticamente;
            document.getElementById('notificar_vencimento').value = data.notificar_vencimento;
            document.getElementById('descricao').value = data.descricao || '';
            document.getElementById('modalTitle').textContent = 'Editar Despesa Fixa';
            document.getElementById('despesaModal').classList.add('active');
        })
        .catch(error => console.error('Erro ao carregar despesa:', error));
}

function showFilterModal() {
    document.getElementById('filterModal').classList.add('active');
}

function showHelpModal() {
    document.getElementById('helpModal').classList.add('active');
}

// Form handling
async function handleDespesaSubmit(event) {
    if (event && event.preventDefault) {
        event.preventDefault();
    }
    
    try {
        // Get form data
        const form = document.getElementById('despesaForm');
        const formData = new FormData(form);
        if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__) {
            formData.append('csrf_token', window.__SF_CSRF__);
        }
        
        // Adiciona o ID da despesa se estiver editando
        const despesaId = document.getElementById('despesaId').value;
        if (despesaId) {
            formData.append('id', despesaId);
        }
        
        // Send request to API
        const response = await fetch(sfApiUrl('despesas_fixas.php'), {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Close modal and reload data
            closeModal('despesaModal');
            loadDespesasData();
            
            // Show success message
            alert('Despesa salva com sucesso!');
        } else {
            throw new Error(result.message || 'Erro ao salvar despesa');
        }
    } catch (error) {
        console.error('Erro ao salvar despesa:', error);
        alert('Erro ao salvar despesa: ' + (error.message || 'Erro desconhecido'));
    }
}

// Utility functions
function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('pt-BR');
}

function formatNumber(value, decimals = 0) {
    return Number(value).toLocaleString('pt-BR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

function formatCurrency(value) {
    return Number(value).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    });
}

function closeModal(modalId) {
    console.log('Fechando modal:', modalId);
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        
        // If it's the despesa modal, reset the form
        if (modalId === 'despesaModal') {
            const form = document.getElementById('despesaForm');
            if (form) {
                form.reset();
            }
        }
    }
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Setup functions
function setupTableButtons() {
    // Setup edit buttons
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-id');
            showEditDespesaModal(id);
        });
    });
    
    // Setup delete buttons
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-id');
            if (confirm('Tem certeza que deseja excluir esta despesa fixa?')) {
                deleteDespesa(id);
            }
        });
    });

    // Setup view comprovante buttons
    document.querySelectorAll('.view-comprovante-btn').forEach(button => {
        button.addEventListener('click', () => {
            const comprovante = button.getAttribute('data-comprovante');
            if (comprovante) {
                const rel = String(comprovante).replace(/^\//, '');
                try {
                    window.open(new URL('../' + rel, window.location.href).href, '_blank');
                } catch (e) {
                    window.open('../' + rel, '_blank');
                }
            }
        });
    });
}

async function deleteDespesa(id) {
    try {
        const response = await fetch(sfApiUrl(`despesas_fixas.php?action=delete&id=${id}`), {
            method: 'DELETE'
        });
        
        if (!response.ok) throw new Error('Erro ao excluir despesa');
        
        loadDespesasData();
    } catch (error) {
        console.error('Erro ao excluir despesa:', error);
    }
}

// Initialize page
document.addEventListener('DOMContentLoaded', async function() {
    try {
        const urlParamsInit = new URLSearchParams(window.location.search);
        if (urlParamsInit.has('sort')) {
            const s = urlParamsInit.get('sort');
            if (s && DF_ALLOWED_SORT.indexOf(s) >= 0) {
                dfSortField = s;
            }
        }
        if (urlParamsInit.has('dir')) {
            dfSortDir = (urlParamsInit.get('dir') || '').toUpperCase() === 'ASC' ? 'ASC' : 'DESC';
        }

        // Initialize components
        initializeDespesasTipoChart();
        initializeStatusDespesasChart();
        initializeTopVeiculosChart();
        initializeFormasPagamentoChart();
        
        // Load data for selects
        await Promise.all([
            loadVehicles(),
            loadPaymentMethods()
        ]);
        
        // Setup event listeners
        setupModalEventListeners();
        setupTableButtons();

        wireDespesasFixasSortHeaders();
        syncDfSortIndicators();

        document.querySelectorAll('form.df-per-page-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                loadDespesasData(1);
            });
        });
        document.querySelectorAll('select.filter-per-page').forEach(function (sel) {
            sel.addEventListener('change', function () {
                loadDespesasData(1);
            });
        });
        
        // Load initial data
        await loadDespesasData();

        // Setup filter event listeners
        document.getElementById('filterBtn').addEventListener('click', showFilterModal);
        document.getElementById('helpBtn').addEventListener('click', showHelpModal);
        document.getElementById('addDespesaBtn').addEventListener('click', showAddDespesaModal);
        
        // Setup search and filter events
        const searchDespesaEl = document.getElementById('searchDespesa');
        if (searchDespesaEl) {
            searchDespesaEl.addEventListener('input', debounce(loadDespesasData, 500));
        }
        document.querySelectorAll('.filter-options select, .fornc-filters-inline select:not(.filter-per-page)').forEach(function (select) {
            select.addEventListener('change', loadDespesasData);
        });
        const applyFiltersBtn = document.getElementById('applyFixedExpenseFilters');
        if (applyFiltersBtn) {
            applyFiltersBtn.addEventListener('click', () => loadDespesasData());
        }
        const clearFiltersBtn = document.getElementById('clearFixedExpenseFilters');
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', () => {
                const searchInput = document.getElementById('searchDespesa');
                if (searchInput) searchInput.value = '';
                document.querySelectorAll('.filter-options select, .fornc-filters-inline select:not(.filter-per-page)').forEach(function (select) {
                    select.value = '';
                });
                loadDespesasData();
            });
        }
        
        // Setup modal close buttons
        document.querySelectorAll('.close-modal').forEach(button => {
            button.addEventListener('click', () => {
                const modal = button.closest('.modal');
                if (modal) {
                    closeModal(modal.id);
                }
            });
        });
        
        // Setup filter modal buttons
        document.getElementById('applyFilterBtn').addEventListener('click', () => {
            const filterMonth = document.getElementById('filterMonth').value;
            currentFilter = filterMonth;
            loadDespesasData();
            closeModal('filterModal');
        });
        
        document.getElementById('clearFilterBtn').addEventListener('click', () => {
            document.getElementById('filterMonth').value = '';
            currentFilter = null;
            loadDespesasData();
            closeModal('filterModal');
        });
        
    } catch (error) {
        console.error('Erro ao inicializar a página:', error);
    }
});

// Setup modal event listeners
function setupModalEventListeners() {
    // Get modal elements
    const despesaModal = document.getElementById('despesaModal');
    const closeModalBtn = despesaModal.querySelector('.close-modal');
    const cancelBtn = document.getElementById('cancelDespesaBtn');
    const saveBtn = document.getElementById('saveDespesaBtn');
    
    // Close button (X)
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', () => closeModal('despesaModal'));
    }
    
    // Cancel button
    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => closeModal('despesaModal'));
    }

    const despesaForm = document.getElementById('despesaForm');
    if (despesaForm) {
        despesaForm.addEventListener('submit', handleDespesaSubmit);
    }
    
    // Save button
    if (saveBtn) {
        saveBtn.addEventListener('click', handleDespesaSubmit);
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', (event) => {
        if (event.target === despesaModal) {
            closeModal('despesaModal');
        }
    });
} 