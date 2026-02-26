// Routes management JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize page
    initializePage();
    
    // Setup modal events
    setupModals();
    
    // Setup filters
    setupFilters();
    
    // Initialize charts
    initializeCharts();
    
    // Setup form submission
    const saveRouteBtn = document.getElementById('saveRouteBtn');
    if (saveRouteBtn) {
        saveRouteBtn.addEventListener('click', saveRoute);
    }
    
    // Setup pagination
    setupPagination();
    
    // Setup expenses modal events
    const saveExpensesBtn = document.getElementById('saveExpensesBtn');
    const cancelExpensesBtn = document.getElementById('cancelExpensesBtn');
    
    if (saveExpensesBtn) {
        saveExpensesBtn.addEventListener('click', saveExpenses);
    }
    if (cancelExpensesBtn) {
        cancelExpensesBtn.addEventListener('click', closeAllModals);
    }

    initializeEventListeners();
    
    // Carrega os dados iniciais do mês atual (ou do período da URL)
    const currentDate = new Date();
    loadDashboardData(currentDate.getMonth() + 1, currentDate.getFullYear());
});

let currentPage = 1;
let totalPages = 1;
let searchDebounceTimer = null;
let currentRouteDateFrom = null;
let currentRouteDateTo = null;
let currentRouteFilterMonth = null;

function showRouteToast(msg, type) {
    if (typeof window.showToast === 'function') {
        try {
            window.showToast(msg, type || 'info');
        } catch (e) {
            console.warn('showToast error:', e);
            alert(msg);
        }
    } else {
        alert(msg);
    }
}

function initializePage() {
    // Restaurar filtros da URL
    const urlParams = new URLSearchParams(window.location.search);
    const searchInput = document.getElementById('searchRoute');
    if (searchInput && urlParams.has('search')) searchInput.value = urlParams.get('search') || '';
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter && urlParams.has('status')) statusFilter.value = urlParams.get('status') || '';
    const driverFilter = document.getElementById('driverFilter');
    if (driverFilter && urlParams.has('driver')) driverFilter.value = urlParams.get('driver') || '';
    const dateFilter = document.getElementById('dateFilter');
    if (dateFilter && urlParams.has('date')) dateFilter.value = urlParams.get('date') || '';
    if (urlParams.has('date_from') && urlParams.has('date_to')) {
        currentRouteDateFrom = urlParams.get('date_from');
        currentRouteDateTo = urlParams.get('date_to');
        currentRouteFilterMonth = null;
        const fdf = document.getElementById('filterDateFrom');
        const fdt = document.getElementById('filterDateTo');
        if (fdf) fdf.value = currentRouteDateFrom;
        if (fdt) fdt.value = currentRouteDateTo;
    } else if (urlParams.has('month')) {
        currentRouteFilterMonth = urlParams.get('month');
        currentRouteDateFrom = null;
        currentRouteDateTo = null;
        const fm = document.getElementById('filterMonth');
        if (fm) fm.value = currentRouteFilterMonth;
    }
    
    // Get current page and per_page from URL or use default
    const page = parseInt(urlParams.get('page')) || 1;
    const perPageFromUrl = parseInt(urlParams.get('per_page'), 10);
    const perPageEl = document.getElementById('perPageRoutes');
    if (perPageEl && !Number.isNaN(perPageFromUrl) && [5, 10, 25, 50, 100].indexOf(perPageFromUrl) >= 0) {
        perPageEl.value = String(perPageFromUrl);
    }
    
    // Load route data from API
    loadRouteData(page);
    
    // Setup button events
    document.getElementById('addRouteBtn').addEventListener('click', showAddRouteModal);
    
    const importNfeXmlBtn = document.getElementById('importNfeXmlBtn');
    if (importNfeXmlBtn) {
        importNfeXmlBtn.addEventListener('click', function() {
            document.getElementById('importNfeXmlForm').reset();
            document.getElementById('importNfeXmlStatus').style.display = 'none';
            document.getElementById('importNfeXmlModal').style.display = 'block';
        });
    }
    
    const importNfeXmlForm = document.getElementById('importNfeXmlForm');
    if (importNfeXmlForm) {
        importNfeXmlForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var fileInput = document.getElementById('nfeXmlFile');
            var file = fileInput && fileInput.files[0];
            if (!file) {
                showRouteToast('Selecione um arquivo XML da NF-e.', 'error');
                return;
            }
            var statusEl = document.getElementById('importNfeXmlStatus');
            var submitBtn = document.getElementById('importNfeXmlSubmit');
            statusEl.style.display = 'block';
            statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importando...';
            statusEl.className = 'mt-2 text-info';
            submitBtn.disabled = true;
            var formData = new FormData();
            formData.append('xml_file', file);
            fetch('../api/route_actions.php?action=import_nfe_xml', {
                method: 'POST',
                body: formData
            })
            .then(function(r) {
                return r.json().then(function(data) {
                    if (!r.ok) {
                        data = data || {};
                        data.success = false;
                        data.message = data.message || data.error || 'Erro na requisição (' + r.status + ')';
                    }
                    return data;
                }, function() {
                    return { success: false, message: 'Resposta inválida do servidor (' + r.status + ').' };
                });
            })
            .then(function(data) {
                if (data.success) {
                    statusEl.innerHTML = '<i class="fas fa-check-circle text-success"></i> Rota criada com sucesso.';
                    statusEl.className = 'mt-2 text-success';
                    showRouteToast(data.message || 'Rota criada a partir da NF-e.', 'success');
                    document.getElementById('importNfeXmlModal').style.display = 'none';
                    loadRouteData(currentPage);
                    loadDashboardData(new Date().getMonth() + 1, new Date().getFullYear());
                } else {
                    statusEl.innerHTML = '<i class="fas fa-exclamation-circle text-danger"></i> ' + (data.message || 'Erro ao importar.');
                    statusEl.className = 'mt-2 text-danger';
                    showRouteToast(data.message || 'Erro ao importar XML.', 'error');
                }
            })
            .catch(function(err) {
                statusEl.innerHTML = '<i class="fas fa-exclamation-circle text-danger"></i> Erro: ' + err.message;
                statusEl.className = 'mt-2 text-danger';
                showRouteToast('Erro ao importar: ' + err.message, 'error');
            })
            .finally(function() {
                submitBtn.disabled = false;
            });
        });
    }
    
    // Setup table buttons
    setupTableButtons();
    
    // Load select options
    loadSelectOptions();

    // Carregar dados do dashboard com o mês atual
    const currentDate = new Date();
    loadDashboardData(currentDate.getMonth() + 1, currentDate.getFullYear());
}

function setupModals() {
    // Close modal when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeAllModals();
            }
        });
    });
    
    // Close modal when clicking X button (all close buttons)
    document.querySelectorAll('.close-modal').forEach(button => {
        button.addEventListener('click', closeAllModals);
    });
    
    // Close modal when clicking cancel buttons
    document.getElementById('cancelRouteBtn')?.addEventListener('click', closeAllModals);
    document.getElementById('closeRouteDetailsBtn')?.addEventListener('click', closeAllModals);
    document.getElementById('cancelDeleteBtn')?.addEventListener('click', closeAllModals);
    
    // Setup tab switching in route details modal
    document.querySelectorAll('.tab-btn').forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Remove active class from all tabs and contents
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Add active class to clicked tab and its content
            this.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        });
    });

    // Configurar eventos de mudança dos estados
    setupEstadoCidadeEvents();
}

function setupEstadoCidadeEvents() {
    // Configura eventos para o estado de origem
    const estadoOrigem = document.getElementById('estado_origem');
    const cidadeOrigem = document.getElementById('cidade_origem_id');
    
    if (estadoOrigem) {
        estadoOrigem.addEventListener('change', function() {
            const uf = this.value;
            if (uf) {
                loadCidades(uf, 'cidade_origem_id');
                cidadeOrigem.disabled = false;
            } else {
                cidadeOrigem.innerHTML = '<option value="">Selecione primeiro o estado</option>';
                cidadeOrigem.disabled = true;
            }
        });
    }

    // Configura eventos para o estado de destino
    const estadoDestino = document.getElementById('estado_destino');
    const cidadeDestino = document.getElementById('cidade_destino_id');
    
    if (estadoDestino) {
        estadoDestino.addEventListener('change', function() {
            const uf = this.value;
            if (uf) {
                loadCidades(uf, 'cidade_destino_id');
                cidadeDestino.disabled = false;
            } else {
                cidadeDestino.innerHTML = '<option value="">Selecione primeiro o estado</option>';
                cidadeDestino.disabled = true;
            }
        });
    }
}

function setupFilters() {
    // Search functionality
    const searchInput = document.getElementById('searchRoute');
    if (searchInput) {
        searchInput.addEventListener('input', handleSearch);
        searchInput.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                if (searchDebounceTimer) {
                    clearTimeout(searchDebounceTimer);
                    searchDebounceTimer = null;
                }
                currentPage = 1;
                loadRouteData(1);
            }
        });
    }
    
    // Status filter
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter) {
        statusFilter.addEventListener('change', handleFilters);
    }
    
    // Driver filter
    const driverFilter = document.getElementById('driverFilter');
    if (driverFilter) {
        driverFilter.addEventListener('change', handleFilters);
    }
    
    // Date filter
    const dateFilter = document.getElementById('dateFilter');
    if (dateFilter) {
        dateFilter.addEventListener('change', handleFilters);
    }

    const applyFiltersBtn = document.getElementById('applyRouteFilters');
    if (applyFiltersBtn) {
        applyFiltersBtn.addEventListener('click', () => {
            currentPage = 1;
            loadRouteData(1);
        });
    }

    const clearFiltersBtn = document.getElementById('clearRouteFilters');
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';

            if (statusFilter) statusFilter.value = '';
            if (driverFilter) driverFilter.value = '';

            const vehicleFilter = document.getElementById('vehicleFilter');
            if (vehicleFilter) vehicleFilter.value = '';

            const dateFilterInput = document.getElementById('dateFilter');
            if (dateFilterInput) dateFilterInput.value = '';

            currentPage = 1;
            loadRouteData(1);
        });
    }

    const perPageSelect = document.getElementById('perPageRoutes');
    if (perPageSelect) {
        perPageSelect.addEventListener('change', function() {
            currentPage = 1;
            loadRouteData(1);
        });
    }
}

function handleSearch(event) {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
    searchDebounceTimer = setTimeout(() => {
        currentPage = 1;
        loadRouteData(1);
    }, 300);
}

function handleFilters() {
    currentPage = 1;
    loadRouteData(1);
}

function loadSelectOptions() {
    // Load estados
    fetch('../api/route_actions.php?action=get_estados')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateEstadosSelects(data.data);
                // Após carregar os estados, configura os eventos
                setupEstadoCidadeEvents();
            }
        })
        .catch(error => console.error('Erro ao carregar estados:', error));
    
    // Load motoristas
    fetch('../api/route_actions.php?action=get_motoristas')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateDriverSelects(data.data);
            }
        })
        .catch(error => console.error('Erro ao carregar motoristas:', error));
    
    // Load veículos
    fetch('../api/route_actions.php?action=get_veiculos')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateVehicleSelects(data.data);
            }
        })
        .catch(error => console.error('Erro ao carregar veículos:', error));
}

function updateDriverSelects(drivers) {
    const driverFilter = document.getElementById('driverFilter');
    const driverSelect = document.getElementById('motorista_id');
    
    const options = drivers.map(driver => 
        `<option value="${driver.id}">${driver.nome}</option>`
    ).join('');
    
    if (driverFilter) {
        driverFilter.innerHTML = '<option value="">Todos os motoristas</option>' + options;
    }
    if (driverSelect) {
        driverSelect.innerHTML = '<option value="">Selecione um motorista</option>' + options;
    }
}

function updateVehicleSelects(vehicles) {
    const vehicleSelect = document.getElementById('veiculo_id');
    if (vehicleSelect) {
        const options = vehicles.map(vehicle => 
            `<option value="${vehicle.id}">${vehicle.placa} (${vehicle.modelo})</option>`
        ).join('');
        
        vehicleSelect.innerHTML = '<option value="">Selecione um veículo</option>' + options;
    }
}

function updateEstadosSelects(estados) {
    const estadoOrigemSelect = document.getElementById('estado_origem');
    const estadoDestinoSelect = document.getElementById('estado_destino');
    const cidadeOrigemSelect = document.getElementById('cidade_origem_id');
    const cidadeDestinoSelect = document.getElementById('cidade_destino_id');
    
    const options = estados.map(estado => 
        `<option value="${estado.uf}">${estado.nome}</option>`
    ).join('');
    
    if (estadoOrigemSelect) {
        estadoOrigemSelect.innerHTML = '<option value="">Selecione o estado</option>' + options;
    }
    if (estadoDestinoSelect) {
        estadoDestinoSelect.innerHTML = '<option value="">Selecione o estado</option>' + options;
    }
    
    // Reseta e desabilita os selects de cidade
    if (cidadeOrigemSelect) {
        cidadeOrigemSelect.innerHTML = '<option value="">Selecione primeiro o estado</option>';
        cidadeOrigemSelect.disabled = true;
    }
    if (cidadeDestinoSelect) {
        cidadeDestinoSelect.innerHTML = '<option value="">Selecione primeiro o estado</option>';
        cidadeDestinoSelect.disabled = true;
    }
}

function loadRouteData(page = 1) {
    currentPage = page;
    
    const loadingEl = document.getElementById('routeTableLoading');
    const tableEl = document.querySelector('.data-table');
    if (loadingEl) loadingEl.style.display = 'block';
    if (tableEl) tableEl.style.visibility = 'hidden';
    
    const searchTerm = document.getElementById('searchRoute')?.value || '';
    const status = document.getElementById('statusFilter')?.value || '';
    const driver = document.getElementById('driverFilter')?.value || '';
    const date = document.getElementById('dateFilter')?.value || '';
    
    const perPageSelect = document.getElementById('perPageRoutes');
    const perPageFromSelect = perPageSelect ? parseInt(perPageSelect.value, 10) : 10;
    const limit = [5, 10, 25, 50, 100].indexOf(perPageFromSelect) >= 0 ? perPageFromSelect : 10;
    
    let url = `../api/route_data.php?action=list&page=${page}&limit=${limit}`;
    if (searchTerm) url += `&search=${encodeURIComponent(searchTerm)}`;
    if (status) url += `&status=${encodeURIComponent(status)}`;
    if (driver) url += `&driver=${encodeURIComponent(driver)}`;
    if (date) url += `&date=${encodeURIComponent(date)}`;
    if (currentRouteDateFrom) url += `&date_from=${encodeURIComponent(currentRouteDateFrom)}`;
    if (currentRouteDateTo) url += `&date_to=${encodeURIComponent(currentRouteDateTo)}`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (loadingEl) loadingEl.style.display = 'none';
            if (tableEl) tableEl.style.visibility = '';
            if (data.success) {
                updateRouteTable(data.data);
                if (data.pagination) {
                    totalPages = data.pagination.total_pages;
                    updatePaginationButtons(data.pagination);
                    const params = new URLSearchParams();
                    if (currentPage !== 1) params.set('page', currentPage);
                    if (limit !== 10) params.set('per_page', limit);
                    if (searchTerm) params.set('search', searchTerm);
                    if (status) params.set('status', status);
                    if (driver) params.set('driver', driver);
                    if (date) params.set('date', date);
                    if (currentRouteDateFrom) params.set('date_from', currentRouteDateFrom);
                    if (currentRouteDateTo) params.set('date_to', currentRouteDateTo);
                    if (currentRouteFilterMonth && !currentRouteDateFrom) params.set('month', currentRouteFilterMonth);
                    const qs = params.toString();
                    const newUrl = window.location.pathname + (qs ? '?' + qs : '');
                    if (window.location.search !== (qs ? '?' + qs : '')) {
                        window.history.replaceState({}, '', newUrl);
                    }
                }
            } else {
                throw new Error(data.error || 'Erro ao carregar dados das rotas');
            }
        })
        .catch(error => {
            if (loadingEl) loadingEl.style.display = 'none';
            if (tableEl) tableEl.style.visibility = '';
            console.error('Erro ao carregar dados das rotas:', error);
            showRouteToast('Erro ao carregar dados: ' + error.message, 'error');
            const tbody = document.querySelector('.data-table tbody');
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center text-danger">
                            Erro ao carregar dados: ${error.message}
                        </td>
                    </tr>
                `;
            }
        });
}

function updateRouteTable(routes) {
    const tbody = document.querySelector('.data-table tbody');
    if (!tbody) {
        console.error('Elemento tbody não encontrado');
        return;
    }
    
    tbody.innerHTML = '';
    
    if (routes && routes.length > 0) {
        routes.forEach(route => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${formatDate(route.data_rota)}</td>
                <td>${route.motorista_nome || '-'}</td>
                <td>${route.veiculo_placa || '-'}</td>
                <td>${route.cidade_origem_nome || '-'} → ${route.cidade_destino_nome || '-'}</td>
                <td>${formatDistance(route.distancia_km)}</td>
                <td>${formatCurrency(route.frete)}</td>
                <td><span class="status-badge ${route.no_prazo ? 'success' : 'warning'}">${route.no_prazo ? 'No Prazo' : 'Atrasado'}</span></td>
                <td class="actions">
                    <button class="btn-icon view-btn" data-id="${route.id}" title="Ver detalhes" aria-label="Ver detalhes da rota">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-icon edit-btn" data-id="${route.id}" title="Editar" aria-label="Editar rota">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-icon expenses-btn" data-id="${route.id}" title="Despesas de Viagem" aria-label="Despesas da viagem">
                        <i class="fas fa-money-bill"></i>
                    </button>
                    <button class="btn-icon delete-btn" data-id="${route.id}" title="Excluir" aria-label="Excluir rota">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    } else {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center">Nenhuma rota encontrada</td></tr>';
    }
    
    setupTableButtons();
}

function setupTableButtons() {
    // Botões de visualização
    document.querySelectorAll('.view-btn').forEach(button => {
        button.addEventListener('click', function() {
            const routeId = this.getAttribute('data-id');
            showRouteDetails(routeId);
        });
    });
    
    // Botões de edição
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const routeId = this.getAttribute('data-id');
            showEditRouteModal(routeId);
        });
    });

    // Botões de despesas
    document.querySelectorAll('.expenses-btn').forEach(button => {
        button.addEventListener('click', function() {
            const routeId = this.getAttribute('data-id');
            showExpensesModal(routeId);
        });
    });
    
    // Botões de exclusão
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function() {
            const routeId = this.getAttribute('data-id');
            showDeleteConfirmation(routeId);
        });
    });
}

function setupPagination() {
    const paginationDiv = document.querySelector('.pagination');
    if (!paginationDiv) return;
    // Delegação: um único listener no container para que funcione após substituir o HTML
    paginationDiv.addEventListener('click', function(e) {
        const btn = e.target.closest('a.pagination-btn');
        if (!btn || btn.classList.contains('disabled')) return;
        e.preventDefault();
        const direction = btn.getAttribute('data-direction');
        const newPage = direction === 'prev' ? currentPage - 1 : currentPage + 1;
        if (newPage >= 1 && newPage <= totalPages) {
            loadRouteData(newPage);
        }
    });
}

function updatePaginationButtons(pagination) {
    const paginationDiv = document.querySelector('.pagination');
    if (!paginationDiv) return;
    
    const perPageSelect = document.getElementById('perPageRoutes');
    const perPage = perPageSelect ? parseInt(perPageSelect.value, 10) : 10;
    const perPageParam = [5, 10, 25, 50, 100].indexOf(perPage) >= 0 ? perPage : 10;
    const totalRegistros = (pagination && pagination.total) ? pagination.total : (currentPage * perPageParam);
    const paginationText = totalPages > 1
        ? `Página ${currentPage} de ${totalPages} (${totalRegistros} registros)`
        : `${totalRegistros} registros`;
    
    const prevPage = Math.max(1, currentPage - 1);
    const nextPage = Math.min(totalPages, currentPage + 1);
    
    paginationDiv.innerHTML = `
        <a href="#" class="pagination-btn ${currentPage <= 1 ? 'disabled' : ''}" 
           data-direction="prev" data-page="${prevPage}">
            <i class="fas fa-chevron-left"></i>
        </a>
        
        <span class="pagination-info">${paginationText}</span>
        
        <a href="#" class="pagination-btn ${currentPage >= totalPages ? 'disabled' : ''}"
           data-direction="next" data-page="${nextPage}">
            <i class="fas fa-chevron-right"></i>
        </a>
    `;
}

function updateURLParameter(param, value) {
    const url = new URL(window.location.href);
    url.searchParams.set(param, value);
    const perPageSelect = document.getElementById('perPageRoutes');
    const perPage = perPageSelect ? parseInt(perPageSelect.value, 10) : 10;
    if ([5, 10, 25, 50, 100].indexOf(perPage) >= 0) {
        url.searchParams.set('per_page', perPage);
    }
    window.history.pushState({}, '', url);
}

function updateDashboardMetrics() {
    fetch('../api/route_data.php?action=summary')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const metrics = data.data;
                console.log('Métricas recebidas:', metrics);
                
                // Atualiza os elementos se eles existirem
                const elements = {
                    'totalRoutes': metrics.total_routes || 0,
                    'completedRoutes': metrics.rotas_no_prazo || 0,
                    'totalDistance': formatDistance(metrics.total_distance),
                    'totalFrete': formatCurrency(metrics.total_frete),
                    'rotasNoPrazo': metrics.rotas_no_prazo || 0,
                    'rotasAtrasadas': metrics.rotas_atrasadas || 0,
                    'mediaEficiencia': formatPercentage(metrics.media_eficiencia),
                    'percentualVazio': formatPercentage(metrics.media_percentual_vazio)
                };
                
                console.log('Elementos a atualizar:', elements);
                
                Object.entries(elements).forEach(([id, value]) => {
                    const element = document.getElementById(id);
                    if (element) {
                        element.textContent = value;
                        console.log(`Atualizado ${id} com valor ${value}`);
                    } else {
                        console.warn(`Elemento com ID ${id} não encontrado`);
                    }
                });
            }
        })
        .catch(error => console.error('Erro ao atualizar métricas:', error));
}

// Helper functions
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR');
}

function formatDistance(km) {
    if (!km) return '0 km';
    return `${Number(km).toLocaleString('pt-BR')} km`;
}

function formatCurrency(value) {
    if (!value) return 'R$ 0,00';
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

function formatPercentage(value) {
    if (!value) return '0%';
    return `${Number(value).toFixed(1)}%`;
}

function closeAllModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.style.display = 'none';
    });
}

function showAddRouteModal() {
    document.getElementById('routeForm').reset();
    document.getElementById('routeId').value = '';
    document.getElementById('modalTitle').textContent = 'Adicionar Rota';
    document.getElementById('routeModal').style.display = 'block';
    setupFormCalculations();
}

function showEditRouteModal(routeId) {
    fetch(`../api/route_data.php?action=view&id=${routeId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                fillRouteForm(data.data);
                document.getElementById('routeId').value = routeId;
                document.getElementById('modalTitle').textContent = 'Editar Rota';
                document.getElementById('routeModal').style.display = 'block';
                setupFormCalculations();
            } else {
                throw new Error(data.error || 'Erro ao carregar dados da rota');
            }
        })
        .catch(error => {
            console.error('Erro ao carregar detalhes da rota:', error);
            showRouteToast('Erro ao carregar detalhes da rota: ' + error.message, 'error');
        });
}

function showDeleteConfirmation(routeId) {
    fetch(`../api/route_data.php?action=view&id=${routeId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const route = data.data;
                document.getElementById('deleteRouteInfo').textContent = 
                    `${route.cidade_origem_nome} → ${route.cidade_destino_nome} (${formatDate(route.data_rota)})`;
                
                document.getElementById('confirmDeleteBtn').onclick = () => deleteRoute(routeId);
                document.getElementById('deleteRouteModal').style.display = 'block';
            } else {
                throw new Error(data.error || 'Erro ao carregar dados da rota');
            }
        })
        .catch(error => {
            console.error('Erro ao carregar detalhes da rota:', error);
            showRouteToast('Erro ao carregar detalhes da rota: ' + error.message, 'error');
        });
}

function deleteRoute(routeId) {
    if (!confirm('Tem certeza que deseja excluir esta rota?')) return;
    
    fetch(`../api/route_actions.php?action=delete&id=${routeId}`, {
        method: 'POST'
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeAllModals();
                loadRouteData(currentPage);
                showRouteToast('Rota excluída com sucesso!', 'success');
            } else {
                throw new Error(data.error || 'Erro ao excluir rota');
            }
        })
        .catch(error => {
            console.error('Erro ao excluir rota:', error);
            showRouteToast('Erro ao excluir rota: ' + error.message, 'error');
        });
}

function saveRoute() {
    const form = document.getElementById('routeForm');
    const formData = new FormData(form);
    const data = {};
    
    formData.forEach((value, key) => {
        data[key] = value;
    });
    
    const routeId = document.getElementById('routeId').value;
    const method = routeId ? 'update' : 'add';
    
    fetch(`../api/route_actions.php?action=${method}${routeId ? '&id=' + routeId : ''}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                closeAllModals();
                loadRouteData(currentPage);
                showRouteToast(result.message || (routeId ? 'Rota atualizada com sucesso!' : 'Rota adicionada com sucesso!'), 'success');
            } else {
                throw new Error(result.error || 'Erro ao salvar rota');
            }
        })
        .catch(error => {
            console.error('Erro ao salvar rota:', error);
            showRouteToast('Erro ao salvar rota: ' + error.message, 'error');
        });
}

function initializeCharts() {
    // Implementar inicialização dos gráficos aqui
    // Esta função será implementada posteriormente quando os gráficos forem necessários
}

function fillRouteForm(data) {
    // Preenche o formulário com os dados da rota
    const form = document.getElementById('routeForm');
    
    // Campos básicos
    form.querySelector('#data_rota').value = data.data_rota || '';
    form.querySelector('#motorista_id').value = data.motorista_id || '';
    form.querySelector('#veiculo_id').value = data.veiculo_id || '';
    
    // Estado e Cidade de Origem
    const estadoOrigem = form.querySelector('#estado_origem');
    if (estadoOrigem) {
        estadoOrigem.value = data.estado_origem || '';
        if (data.estado_origem) {
            loadCidades(data.estado_origem, 'cidade_origem_id')
                .then(() => {
                    const cidadeOrigem = form.querySelector('#cidade_origem_id');
                    if (cidadeOrigem) {
                        cidadeOrigem.value = data.cidade_origem_id || '';
                        cidadeOrigem.disabled = false;
                    }
                })
                .catch(error => console.error('Erro ao carregar cidade de origem:', error));
        }
    }
    
    // Estado e Cidade de Destino
    const estadoDestino = form.querySelector('#estado_destino');
    if (estadoDestino) {
        estadoDestino.value = data.estado_destino || '';
        if (data.estado_destino) {
            loadCidades(data.estado_destino, 'cidade_destino_id')
                .then(() => {
                    const cidadeDestino = form.querySelector('#cidade_destino_id');
                    if (cidadeDestino) {
                        cidadeDestino.value = data.cidade_destino_id || '';
                        cidadeDestino.disabled = false;
                    }
                })
                .catch(error => console.error('Erro ao carregar cidade de destino:', error));
        }
    }
    
    // Dados da Viagem
    form.querySelector('#data_saida').value = data.data_saida?.replace(' ', 'T') || '';
    form.querySelector('#data_chegada').value = data.data_chegada?.replace(' ', 'T') || '';
    form.querySelector('#km_saida').value = data.km_saida || '';
    form.querySelector('#km_chegada').value = data.km_chegada || '';
    form.querySelector('#distancia_km').value = data.distancia_km || '';
    form.querySelector('#km_vazio').value = data.km_vazio || '';
    form.querySelector('#total_km').value = data.total_km || '';
    
    // Dados Financeiros e Eficiência
    form.querySelector('#frete').value = data.frete || '';
    form.querySelector('#comissao').value = data.comissao || '';
    form.querySelector('#percentual_vazio').value = data.percentual_vazio || '';
    form.querySelector('#eficiencia_viagem').value = data.eficiencia_viagem || '';
    form.querySelector('#no_prazo').value = data.no_prazo || '0';
    
    // Dados da Carga
    form.querySelector('#peso_carga').value = data.peso_carga || '';
    form.querySelector('#descricao_carga').value = data.descricao_carga || '';
    
    // Observações
    form.querySelector('#observacoes').value = data.observacoes || '';
}

function loadCidades(uf, targetSelectId) {
    console.log(`Carregando cidades para UF: ${uf}, target: ${targetSelectId}`);
    
    return fetch(`../api/route_actions.php?action=get_cidades&uf=${uf}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const cidadeSelect = document.getElementById(targetSelectId);
                if (cidadeSelect) {
                    cidadeSelect.disabled = false;
                    const options = data.data.map(cidade => 
                        `<option value="${cidade.id}">${cidade.nome}</option>`
                    ).join('');
                    cidadeSelect.innerHTML = '<option value="">Selecione a cidade</option>' + options;
                    console.log(`Cidades carregadas com sucesso para ${targetSelectId}`);
                } else {
                    console.error(`Elemento select não encontrado: ${targetSelectId}`);
                }
                return data.data;
            } else {
                throw new Error(data.error || 'Erro ao carregar cidades');
            }
        })
        .catch(error => {
            console.error('Erro ao carregar cidades:', error);
            const cidadeSelect = document.getElementById(targetSelectId);
            if (cidadeSelect) {
                cidadeSelect.innerHTML = '<option value="">Erro ao carregar cidades</option>';
                cidadeSelect.disabled = true;
            }
            throw error;
        });
}

function showRouteDetails(routeId) {
    fetch(`../api/route_data.php?action=view&id=${routeId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                fillRouteDetails(data.data);
                const modal = document.getElementById('viewRouteModal');
                // Definir o ID da rota no modal para calcular lucratividade
                modal.setAttribute('data-route-id', routeId);
                modal.style.display = 'block';
            } else {
                throw new Error(data.error || 'Erro ao carregar detalhes da rota');
            }
        })
        .catch(error => {
            console.error('Erro ao carregar detalhes da rota:', error);
            showRouteToast('Erro ao carregar detalhes da rota: ' + error.message, 'error');
        });
}

function fillRouteDetails(data) {
    // Função auxiliar para preencher elemento com segurança
    const setElementText = (id, text) => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = text;
        } else {
            console.warn(`Elemento ${id} não encontrado no DOM`);
        }
    };
    
    // Preenche os detalhes da rota no modal de visualização
    setElementText('routeOriginDestination', 
        `${data.cidade_origem_nome || '-'}, ${data.estado_origem || '-'} → ${data.cidade_destino_nome || '-'}, ${data.estado_destino || '-'}`);
    
    // Status da rota baseado no no_prazo
    const statusText = data.no_prazo === '1' ? 'No Prazo' : data.no_prazo === '0' ? 'Atrasado' : '-';
    setElementText('routeStatus', statusText);
    setElementText('routeDate', data.data_rota ? formatDate(data.data_rota) : '-');
    
    // Informações gerais
    setElementText('detailDriver', data.motorista_nome || '-');
    setElementText('detailVehicle', data.veiculo_placa && data.veiculo_modelo ? 
        `${data.veiculo_placa} (${data.veiculo_modelo})` : data.veiculo_placa || '-');
    setElementText('detailDistance', data.distancia_km ? 
        `${parseFloat(data.distancia_km).toFixed(2)} km` : '-');
    
    // Consumo de Combustível
    const fuelElement = document.getElementById('detailFuelConsumption');
    if (fuelElement) {
        fuelElement.textContent = data.consumo_medio ? 
            `${parseFloat(data.consumo_medio).toFixed(2)} km/l` : '-';
    }
    
    // Status (opcional - só se existir no layout)
    const statusElement = document.getElementById('detailStatus');
    if (statusElement) {
        statusElement.textContent = statusText;
    }
    
    // Formatação de datas
    setElementText('detailStartTime', data.data_saida ? formatDateTime(data.data_saida) : '-');
    setElementText('detailEndTime', data.data_chegada ? formatDateTime(data.data_chegada) : '-');
    
    // Calcula duração se houver data de início e fim
    if (data.data_saida && data.data_chegada) {
        const inicio = new Date(data.data_saida);
        const fim = new Date(data.data_chegada);
        const duracao = Math.abs(fim - inicio);
        const horas = Math.floor(duracao / (1000 * 60 * 60));
        const minutos = Math.floor((duracao % (1000 * 60 * 60)) / (1000 * 60));
        setElementText('detailDuration', `${horas}h ${minutos}min`);
    } else {
        setElementText('detailDuration', '-');
    }
    
    // Endereços
    const enderecoOrigem = [
        data.cidade_origem_nome,
        data.estado_origem
    ].filter(Boolean).join(', ') || '-';
    
    const enderecoDestino = [
        data.cidade_destino_nome,
        data.estado_destino
    ].filter(Boolean).join(', ') || '-';
    
    setElementText('detailOriginAddress', enderecoOrigem);
    setElementText('detailDestinationAddress', enderecoDestino);
    
    // Informações da Carga
    setElementText('detailCargoDescription', data.descricao_carga || '-');
    setElementText('detailCargoWeight', data.peso_carga ? `${parseFloat(data.peso_carga).toFixed(2)} kg` : '-');
    setElementText('detailCustomer', data.cliente || '-');
    setElementText('detailCustomerContact', data.cliente_contato || '-');
    
    // Informações Financeiras (cards antigos removidos - agora usa Análise de Lucratividade)
    
    // Eficiência
    if (document.getElementById('detailEfficiency')) {
        document.getElementById('detailEfficiency').textContent = data.eficiencia_viagem ? 
            `${parseFloat(data.eficiencia_viagem).toFixed(2)}%` : '-';
    }
    
    // Observações
    setElementText('detailNotes', data.observacoes || 'Nenhuma observação registrada');
}

function formatDateTime(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString.replace(' ', 'T'));
    if (isNaN(date.getTime())) return '-';
    return date.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function setupFormCalculations() {
    console.log('Configurando cálculos do formulário...');
    
    // Elementos do formulário
    const kmSaida = document.getElementById('km_saida');
    const kmChegada = document.getElementById('km_chegada');
    const distanciaKm = document.getElementById('distancia_km');
    const kmVazio = document.getElementById('km_vazio');
    const totalKm = document.getElementById('total_km');
    const percentualVazio = document.getElementById('percentual_vazio');
    const eficienciaViagem = document.getElementById('eficiencia_viagem');
    const frete = document.getElementById('frete');
    const comissao = document.getElementById('comissao');
    const motorista = document.getElementById('motorista_id');

    // Desabilita campos calculados automaticamente
    if (distanciaKm) distanciaKm.readOnly = true;
    if (totalKm) totalKm.readOnly = true;
    if (percentualVazio) percentualVazio.readOnly = true;
    if (eficienciaViagem) eficienciaViagem.readOnly = true;
    if (comissao) comissao.readOnly = true;

    // Função para calcular distância
    function calcularDistancia() {
        if (kmSaida && kmChegada && kmSaida.value && kmChegada.value) {
            const distancia = Math.abs(parseFloat(kmChegada.value) - parseFloat(kmSaida.value));
            if (distanciaKm) distanciaKm.value = distancia.toFixed(2);
            
            calcularTotais();
        }
    }

    // Função para calcular totais
    function calcularTotais() {
        if (distanciaKm && kmVazio) {
            const distancia = parseFloat(distanciaKm.value) || 0;
            const vazio = parseFloat(kmVazio.value) || 0;
            const total = distancia + vazio;
            
            if (totalKm) totalKm.value = total.toFixed(2);
            
            // Calcula percentual vazio
            if (percentualVazio && total > 0) {
                const percVazio = (vazio / total) * 100;
                percentualVazio.value = percVazio.toFixed(2);
                
                // Calcula eficiência
                if (eficienciaViagem) {
                    const eficiencia = 100 - percVazio;
                    eficienciaViagem.value = eficiencia.toFixed(2);
                }
            }
        }
    }

    // Função para calcular comissão
    function calcularComissao() {
        if (frete && motorista && frete.value && motorista.value) {
            fetch(`../api/route_actions.php?action=get_motorista_comissao&id=${motorista.value}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.porcentagem_comissao) {
                        const valorFrete = parseFloat(frete.value);
                        const porcentagem = parseFloat(data.porcentagem_comissao);
                        const valorComissao = (valorFrete * porcentagem) / 100;
                        if (comissao) comissao.value = valorComissao.toFixed(2);
                    }
                })
                .catch(error => console.error('Erro ao calcular comissão:', error));
        }
    }

    // Configura eventos para KM saída e chegada
    if (kmSaida) kmSaida.addEventListener('input', calcularDistancia);
    if (kmChegada) kmChegada.addEventListener('input', calcularDistancia);
    
    // Configura evento para KM vazio
    if (kmVazio) kmVazio.addEventListener('input', calcularTotais);
    
    // Configura eventos para frete e motorista
    if (frete) frete.addEventListener('input', calcularComissao);
    if (motorista) motorista.addEventListener('change', calcularComissao);
}

function showExpensesModal(routeId) {
    const modal = document.getElementById('expensesModal');
    if (!modal) {
        console.error('Modal de despesas não encontrado');
        return;
    }

    // Reset form
    const form = document.getElementById('expensesForm');
    if (form) {
        form.reset();
    }

    // Set route ID
    const expenseRouteId = document.getElementById('expenseRouteId');
    if (expenseRouteId) {
        expenseRouteId.value = routeId;
    }
    
    // Load existing expenses
    fetch(`../api/route_data.php?action=get_expenses&id=${routeId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.expenses) {
                fillExpensesForm(data.expenses);
            }
            // Setup calculations after loading data
            setupExpensesCalculations();
            // Show modal
            modal.style.display = 'block';
        })
        .catch(error => {
            console.error('Erro ao carregar despesas:', error);
            showRouteToast('Erro ao carregar despesas: ' + error.message, 'error');
        });
}

function fillExpensesForm(expenses) {
    // Preenche cada campo com o valor existente ou deixa vazio
    const fields = [
        'descarga', 'pedagios', 'caixinha', 'estacionamento',
        'lavagem', 'borracharia', 'eletrica_mecanica', 'adiantamento'
    ];
    
    fields.forEach(field => {
        const value = expenses[field] || '';
        document.getElementById(field).value = value;
    });
    
    // Calcula o total após preencher os campos
    calculateTotalExpenses();
}

function setupExpensesCalculations() {
    const expenseInputs = [
        'descarga', 'pedagios', 'caixinha', 'estacionamento',
        'lavagem', 'borracharia', 'eletrica_mecanica', 'adiantamento'
    ];
    
    expenseInputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            // Remove evento anterior se existir
            input.removeEventListener('input', calculateTotalExpenses);
            // Adiciona novo evento
            input.addEventListener('input', calculateTotalExpenses);
        }
    });
}

function calculateTotalExpenses() {
    const expenseInputs = [
        'descarga', 'pedagios', 'caixinha', 'estacionamento',
        'lavagem', 'borracharia', 'eletrica_mecanica', 'adiantamento'
    ];
    
    const total = expenseInputs.reduce((sum, inputId) => {
        const input = document.getElementById(inputId);
        const value = input ? (parseFloat(input.value) || 0) : 0;
        return sum + value;
    }, 0);
    
    document.getElementById('total_despviagem').value = total.toFixed(2);
}

function saveExpenses() {
    const form = document.getElementById('expensesForm');
    if (!form) return;

    const formData = new FormData(form);
    const data = {};
    
    formData.forEach((value, key) => {
        // Converte strings vazias para null
        data[key] = value === '' ? null : value;
    });
    
    fetch('../api/route_actions.php?action=save_expenses', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                closeAllModals();
                showRouteToast('Despesas salvas com sucesso!', 'success');
            } else {
                throw new Error(result.error || 'Erro ao salvar despesas');
            }
        })
        .catch(error => {
            console.error('Erro ao salvar despesas:', error);
            showRouteToast('Erro ao salvar despesas: ' + error.message, 'error');
        });
}

// Função para mostrar o modal
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
    }
}

// Função para fechar o modal
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

// Função para inicializar os event listeners
function initializeEventListeners() {
    // Botão de adicionar rota
    const addRouteBtn = document.getElementById('addRouteBtn');
    if (addRouteBtn) {
        addRouteBtn.addEventListener('click', () => showModal('routeModal'));
    }

    // Botão de filtro
    const filterBtn = document.getElementById('filterBtn');
    if (filterBtn) {
        filterBtn.addEventListener('click', () => showModal('filterModal'));
    }

    // Botão de ajuda
    const helpBtn = document.getElementById('helpBtn');
    if (helpBtn) {
        helpBtn.addEventListener('click', () => showModal('helpRouteModal'));
    }

    // Botão de exportar
    const exportBtn = document.getElementById('exportBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            const search = document.getElementById('searchRoute')?.value?.trim() || '';
            const status = document.getElementById('statusFilter')?.value || '';
            const driver = document.getElementById('driverFilter')?.value || '';
            const date = document.getElementById('dateFilter')?.value || '';
            let url = '../api/route_export.php?';
            const p = [];
            if (search) p.push('search=' + encodeURIComponent(search));
            if (status) p.push('status=' + encodeURIComponent(status));
            if (driver) p.push('driver=' + encodeURIComponent(driver));
            if (date) p.push('date=' + encodeURIComponent(date));
            if (currentRouteDateFrom) p.push('date_from=' + encodeURIComponent(currentRouteDateFrom));
            if (currentRouteDateTo) p.push('date_to=' + encodeURIComponent(currentRouteDateTo));
            if (currentRouteFilterMonth && !currentRouteDateFrom) {
                const [y, m] = currentRouteFilterMonth.split('-');
                p.push('year=' + y + '&month=' + m);
            }
            window.open(url + p.join('&'), '_blank');
            showRouteToast('Exportação iniciada. O download deve abrir em instantes.', 'info');
        });
    }

    // Botões de fechar modal
    const closeButtons = document.querySelectorAll('.close-modal');
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });

    // Fechar modal ao clicar fora
    window.addEventListener('click', function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });

    // Botão de cancelar rota
    const cancelRouteBtn = document.getElementById('cancelRouteBtn');
    if (cancelRouteBtn) {
        cancelRouteBtn.addEventListener('click', () => closeModal('routeModal'));
    }

    // Botão de salvar rota
    const saveRouteBtn = document.getElementById('saveRouteBtn');
    if (saveRouteBtn) {
        saveRouteBtn.addEventListener('click', saveRoute);
    }

    // Botão de limpar filtro
    const clearFilterBtn = document.getElementById('clearFilterBtn');
    if (clearFilterBtn) {
        clearFilterBtn.addEventListener('click', clearFilter);
    }

    // Botão de aplicar filtro
    const applyFilterBtn = document.getElementById('applyFilterBtn');
    if (applyFilterBtn) {
        applyFilterBtn.addEventListener('click', applyFilter);
    }

    // Botão de limpar despesas
    const clearExpensesBtn = document.getElementById('clearExpensesBtn');
    if (clearExpensesBtn) {
        clearExpensesBtn.addEventListener('click', function() {
            const expenseRouteId = document.getElementById('expenseRouteId').value;
            if (!expenseRouteId) return;
            if (!confirm('Tem certeza que deseja excluir as despesas desta viagem?')) return;
            fetch('../api/route_actions.php?action=delete_expenses', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'rota_id=' + encodeURIComponent(expenseRouteId)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    closeAllModals();
                    showRouteToast('Despesas excluídas com sucesso!', 'success');
                } else {
                    throw new Error(result.error || 'Erro ao excluir despesas');
                }
            })
            .catch(error => {
                showRouteToast('Erro ao excluir despesas: ' + error.message, 'error');
            });
        });
    }
}

// Função para limpar o filtro
function clearFilter() {
    const filterMonth = document.getElementById('filterMonth');
    const filterDateFrom = document.getElementById('filterDateFrom');
    const filterDateTo = document.getElementById('filterDateTo');
    if (filterMonth) filterMonth.value = '';
    if (filterDateFrom) filterDateFrom.value = '';
    if (filterDateTo) filterDateTo.value = '';
    currentRouteDateFrom = null;
    currentRouteDateTo = null;
    currentRouteFilterMonth = null;
    currentPage = 1;
    loadRouteData(1);
    const currentDate = new Date();
    loadDashboardData(currentDate.getMonth() + 1, currentDate.getFullYear());
    closeModal('filterModal');
}

// Função para aplicar o filtro
function applyFilter() {
    const filterMonth = document.getElementById('filterMonth');
    const filterDateFrom = document.getElementById('filterDateFrom');
    const filterDateTo = document.getElementById('filterDateTo');
    if (filterDateFrom && filterDateTo && filterDateFrom.value && filterDateTo.value) {
        currentRouteDateFrom = filterDateFrom.value;
        currentRouteDateTo = filterDateTo.value;
        currentRouteFilterMonth = null;
        if (filterMonth) filterMonth.value = '';
    } else if (filterMonth && filterMonth.value) {
        currentRouteFilterMonth = filterMonth.value;
        currentRouteDateFrom = null;
        currentRouteDateTo = null;
        if (filterDateFrom) filterDateFrom.value = '';
        if (filterDateTo) filterDateTo.value = '';
    }
    currentPage = 1;
    loadRouteData(1);
    if (currentRouteFilterMonth) {
        const [y, m] = currentRouteFilterMonth.split('-');
        loadDashboardData(parseInt(m), parseInt(y));
    } else {
        const currentDate = new Date();
        loadDashboardData(currentDate.getMonth() + 1, currentDate.getFullYear());
    }
    closeModal('filterModal');
}

// Função para carregar os dados do dashboard
function loadDashboardData(month, year) {
    // Mostrar indicador de carregamento
    showLoading();

    // Fazer requisição AJAX para buscar os dados
    fetch(`../api/routes_data.php?action=dashboard&month=${month}&year=${year}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Erro na resposta da API: ${response.status} ${response.statusText}`);
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Resposta da API:', text);
                    throw new Error('Resposta inválida da API');
                }
            });
        })
        .then(data => {
            if (data.error) {
                throw new Error(data.message || 'Erro ao carregar dados');
            }
            
            // Atualiza os KPIs
            updateKPIs(data);
            
            // Atualiza os gráficos
            updateCharts(data);
            
            // Esconde indicador de carregamento
            hideLoading();
        })
        .catch(error => {
            console.error('Erro ao carregar dados:', error);
            hideLoading();
            // Mostrar mensagem de erro para o usuário
            showRouteToast('Erro ao carregar dados do dashboard: ' + error.message, 'error');
        });
}

// Função para atualizar os KPIs
function updateKPIs(data) {
    // Atualiza os valores dos cards
    document.getElementById('totalRoutes').textContent = data.total_rotas || '0';
    document.getElementById('completedRoutes').textContent = data.rotas_concluidas || '0';
    document.getElementById('totalDistance').textContent = formatDistance(data.distancia_total);
    document.getElementById('totalFrete').textContent = formatCurrency(data.frete_total);
    
    // Atualiza os valores de eficiência
    document.getElementById('rotasNoPrazo').textContent = data.rotas_no_prazo || '0';
    document.getElementById('rotasAtrasadas').textContent = data.rotas_atrasadas || '0';
    document.getElementById('mediaEficiencia').textContent = formatPercentage(data.media_eficiencia);
    document.getElementById('percentualVazio').textContent = formatPercentage(data.percentual_vazio);
}

// Função para atualizar os gráficos
function updateCharts(data) {
    // Atualiza o gráfico de distância por motorista
    updateDistanciaChart(data.distancia_motorista);
    
    // Atualiza o gráfico de eficiência
    updateEficienciaChart(data.eficiencia_motorista);
    
    // Atualiza o gráfico de rotas no prazo
    updateRotasPrazoChart(data.rotas_prazo);
    
    // Atualiza o gráfico de frete
    updateFreteChart(data.frete_motorista);
    
    // Atualiza o gráfico de evolução de KM
    updateEvolucaoKmChart(data.evolucao_km);
    
    // Atualiza o gráfico de indicadores
    updateIndicadoresChart(data.indicadores_motorista);
}

// Funções de atualização dos gráficos
function updateDistanciaChart(data) {
    const ctx = document.getElementById('distanciaMotoristaChart').getContext('2d');
    if (window.distanciaChart && typeof window.distanciaChart.destroy === 'function') {
        window.distanciaChart.destroy();
    }
    const labels = Array.isArray(data?.labels) ? data.labels : [];
    const values = Array.isArray(data?.values) ? data.values : [];
    window.distanciaChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'KM Percorridos',
                data: values,
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
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Quilômetros'
                    }
                }
            }
        }
    });
}

function updateEficienciaChart(data) {
    const ctx = document.getElementById('eficienciaMotoristaChart').getContext('2d');
    if (window.eficienciaChart && typeof window.eficienciaChart.destroy === 'function') {
        window.eficienciaChart.destroy();
    }
    const labels = Array.isArray(data?.labels) ? data.labels : [];
    const values = Array.isArray(data?.values) ? data.values : [];
    window.eficienciaChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Eficiência (%)',
                data: values,
                backgroundColor: '#10b981',
                borderColor: '#059669',
                borderWidth: 1
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
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Percentual'
                    }
                }
            }
        }
    });
}

function updateRotasPrazoChart(data) {
    const ctx = document.getElementById('rotasPrazoChart').getContext('2d');
    if (window.rotasPrazoChart && typeof window.rotasPrazoChart.destroy === 'function') {
        window.rotasPrazoChart.destroy();
    }
    const labels = Array.isArray(data?.labels) ? data.labels : [];
    const values = Array.isArray(data?.values) ? data.values : [];
    window.rotasPrazoChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: [
                    '#3b82f6',
                    '#10b981',
                    '#f59e0b'
                ],
                borderWidth: 2
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

function updateFreteChart(data) {
    const ctx = document.getElementById('freteMotoristaChart').getContext('2d');
    if (window.freteChart && typeof window.freteChart.destroy === 'function') {
        window.freteChart.destroy();
    }
    const labels = Array.isArray(data?.labels) ? data.labels : [];
    const values = Array.isArray(data?.values) ? data.values : [];
    window.freteChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Valor em R$',
                data: values,
                backgroundColor: '#8b5cf6',
                borderColor: '#7c3aed',
                borderWidth: 1
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
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Valor (R$)'
                    }
                }
            }
        }
    });
}

function updateEvolucaoKmChart(data) {
    const ctx = document.getElementById('evolucaoKmChart').getContext('2d');
    if (window.evolucaoKmChart && typeof window.evolucaoKmChart.destroy === 'function') {
        window.evolucaoKmChart.destroy();
    }
    // Garante que labels e values são arrays
    const labels = Array.isArray(data?.labels) ? data.labels : [];
    const values = Array.isArray(data?.values) ? data.values : [];
    window.evolucaoKmChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'KM Rodados',
                data: values,
                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                borderColor: '#3b82f6',
                borderWidth: 2,
                fill: true,
                tension: 0.3
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
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Quilômetros'
                    }
                }
            }
        }
    });
}

function updateIndicadoresChart(data) {
    const ctx = document.getElementById('indicadoresMotoristaChart').getContext('2d');
    if (window.indicadoresChart && typeof window.indicadoresChart.destroy === 'function') {
        window.indicadoresChart.destroy();
    }
    // Normaliza os dados para que fiquem entre 0 e 100
    const normalizedDatasets = Array.isArray(data?.datasets) ? data.datasets.map(dataset => {
        const normalizedData = Array.isArray(dataset.data) ? dataset.data.map((value, index) => {
            switch (index) {
                case 0: // Eficiência
                    return Math.min(Number(value) || 0, 100);
                case 1: // KM Vazio
                    return Math.min(Number(value) || 0, 100);
                case 2: // Pontualidade
                    return Math.min(Number(value) || 0, 100);
                case 3: // Total Rotas
                    const maxRotas = Math.max(...data.datasets.map(d => Number(d.data[3]) || 0), 1);
                    return ((Number(value) || 0) / maxRotas) * 100;
                case 4: // Frete
                    const maxFrete = Math.max(...data.datasets.map(d => Number(d.data[4]) || 0), 1);
                    return ((Number(value) || 0) / maxFrete) * 100;
                default:
                    return Number(value) || 0;
            }
        }) : [];
        return {
            ...dataset,
            data: normalizedData
        };
    }) : [];

    const labels = Array.isArray(data?.labels) ? data.labels : [];

    window.indicadoresChart = new Chart(ctx, {
        type: 'radar',
        data: {
            labels: labels,
            datasets: normalizedDatasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.dataset.label || '';
                            const value = context.raw;
                            // Busca o valor original, se possível
                            let originalValue = null;
                            if (Array.isArray(data?.datasets) && data.datasets[context.datasetIndex] && Array.isArray(data.datasets[context.datasetIndex].data)) {
                                originalValue = data.datasets[context.datasetIndex].data[context.dataIndex];
                            }
                            let suffix = '%';
                            switch (context.dataIndex) {
                                case 3:
                                    suffix = ' rotas';
                                    break;
                                case 4:
                                    suffix = ' R$';
                                    break;
                            }
                            // Garante que originalValue é número antes de chamar toFixed
                            if (typeof originalValue === 'number') {
                                return `${label}: ${originalValue.toFixed(2)}${suffix}`;
                            } else if (!isNaN(Number(originalValue))) {
                                return `${label}: ${Number(originalValue).toFixed(2)}${suffix}`;
                            } else {
                                return `${label}: -${suffix}`;
                            }
                        }
                    }
                }
            },
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        stepSize: 20,
                        backdropColor: 'rgba(0, 0, 0, 0.1)'
                    },
                    pointLabels: {
                        font: {
                            size: 12,
                            weight: 'bold'
                        }
                    }
                }
            }
        }
    });
}

// Funções auxiliares de formatação
function formatDistance(value) {
    return `${Number(value || 0).toLocaleString('pt-BR')} km`;
}

function formatCurrency(value) {
    return `R$ ${Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
}

function formatPercentage(value) {
    return `${Number(value || 0).toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`;
}

// Funções para mostrar/esconder indicador de carregamento
function showLoading() {
    document.querySelectorAll('.dashboard-card').forEach(card => {
        card.classList.add('loading');
    });
}

function hideLoading() {
    document.querySelectorAll('.dashboard-card').forEach(card => {
        card.classList.remove('loading');
    });
}