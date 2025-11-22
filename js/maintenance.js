// Maintenance management JavaScript

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded');
    
    // Initialize all components
    initializePage();
    initializeModals();
    
    // Load initial data
    loadMaintenanceData();
    loadVehicles();
    loadSuppliers();

    // Initialize maintenance charts with debouncing
    debouncedInitializeCharts();
});

function initializePage() {
    // Setup event listeners
    setupEventListeners();
    
    // Setup table buttons
    setupTableButtons();
    
    // Setup filters
    setupFilters();
}

function setupEventListeners() {
    // Filter button
    const filterBtn = document.getElementById('filterBtn');
    if (filterBtn) {
        filterBtn.addEventListener('click', function() {
            const filterSection = document.querySelector('.filter-section');
            if (filterSection) {
                filterSection.classList.toggle('active');
            }
        });
    }

    // Help button
    const helpBtn = document.getElementById('helpBtn');
    if (helpBtn) {
        helpBtn.addEventListener('click', function() {
            const helpModal = document.getElementById('helpMaintenanceModal');
            if (helpModal) {
                helpModal.classList.add('active');
            }
        });
    }

    // Add Maintenance button
    const addMaintenanceBtn = document.getElementById('addMaintenanceBtn');
    if (addMaintenanceBtn) {
        addMaintenanceBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const modal = document.getElementById('maintenanceModal');
            if (modal) {
                modal.classList.add('active');
                const form = document.getElementById('maintenanceForm');
                if (form) {
                    form.reset();
                }
                document.getElementById('modalTitle').textContent = 'Nova Manutenção';
            }
        });
    }

    // Close buttons for all modals
    document.querySelectorAll('.close-modal, .btn-secondary').forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.classList.remove('active');
            }
        });
    });

    // Close modals when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(event) {
            if (event.target === this) {
                this.classList.remove('active');
            }
        });
    });

    // Save maintenance button
    const saveMaintenanceBtn = document.getElementById('saveMaintenanceBtn');
    if (saveMaintenanceBtn) {
        saveMaintenanceBtn.addEventListener('click', saveMaintenance);
    }
}

function initializeModals() {
    // Add close functionality to all modals
    document.querySelectorAll('.modal').forEach(modal => {
        // Close when clicking the X button
        const closeBtn = modal.querySelector('.close-modal');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => modal.classList.remove('active'));
        }

        // Close when clicking outside the modal
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });

        // Close when pressing Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                modal.classList.remove('active');
            }
        });
    });
}

function setupTableButtons() {
    // View buttons
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            viewMaintenance(id);
        });
    });

    // Edit buttons
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            editMaintenance(id);
        });
    });

    // Delete buttons
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            showDeleteConfirmation(id);
        });
    });
}

function viewMaintenance(id) {
    fetch(`../api/manutencoes.php?id=${id}`, {
        method: 'GET',
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMaintenanceDetails(data.data);
        } else {
            alert('Erro ao carregar dados da manutenção: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao carregar dados da manutenção');
    });
}

function showMaintenanceDetails(maintenance) {
    const modal = document.getElementById('maintenanceModal');
    if (modal) {
        // Preencher os campos do modal com os dados
        document.getElementById('modalTitle').textContent = 'Detalhes da Manutenção';
        
        // Desabilitar todos os campos para visualização
        const form = document.getElementById('maintenanceForm');
        if (form) {
            Array.from(form.elements).forEach(element => {
                element.disabled = true;
            });
        }
        
        // Preencher os campos
        fillMaintenanceForm(maintenance);
        
        // Esconder botão de salvar
        const saveBtn = document.getElementById('saveMaintenanceBtn');
        if (saveBtn) {
            saveBtn.style.display = 'none';
        }
        
        modal.classList.add('active');
    }
}

function editMaintenance(id) {
    fetch(`../api/manutencoes.php?id=${id}`, {
        method: 'GET',
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const modal = document.getElementById('maintenanceModal');
            if (modal) {
                document.getElementById('modalTitle').textContent = 'Editar Manutenção';
                
                // Habilitar todos os campos para edição
                const form = document.getElementById('maintenanceForm');
                if (form) {
                    Array.from(form.elements).forEach(element => {
                        element.disabled = false;
                    });
                }
                
                // Preencher os campos
                fillMaintenanceForm(data.data);
                
                // Mostrar botão de salvar
                const saveBtn = document.getElementById('saveMaintenanceBtn');
                if (saveBtn) {
                    saveBtn.style.display = 'block';
                }
                
                modal.classList.add('active');
            }
        } else {
            alert('Erro ao carregar dados da manutenção: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao carregar dados da manutenção');
    });
}

function fillMaintenanceForm(data) {
    // Preencher campos do formulário
    document.getElementById('manutencaoId').value = data.id;
    document.getElementById('data_manutencao').value = data.data_manutencao;
    document.getElementById('veiculo_id').value = data.veiculo_id;
    document.getElementById('tipo_manutencao_id').value = data.tipo_manutencao_id;
    document.getElementById('componente_id').value = data.componente_id;
    document.getElementById('status_manutencao_id').value = data.status_manutencao_id;
    document.getElementById('km_atual').value = data.km_atual;
    document.getElementById('fornecedor').value = data.fornecedor || '';
    document.getElementById('valor').value = data.valor;
    document.getElementById('custo_total').value = data.custo_total || '';
    document.getElementById('nota_fiscal').value = data.nota_fiscal || '';
    document.getElementById('descricao').value = data.descricao;
    document.getElementById('descricao_servico').value = data.descricao_servico;
    document.getElementById('observacoes').value = data.observacoes || '';
    document.getElementById('responsavel_aprovacao').value = data.responsavel_aprovacao;
}

function saveMaintenance() {
    const form = document.getElementById('maintenanceForm');
    if (!form) return;

    // Validação básica
    const requiredFields = [
        'data_manutencao',
        'veiculo_id',
        'tipo_manutencao_id',
        'componente_id',
        'status_manutencao_id',
        'km_atual',
        'valor',
        'descricao',
        'descricao_servico',
        'responsavel_aprovacao'
    ];

    let isValid = true;
    requiredFields.forEach(field => {
        const input = document.getElementById(field);
        if (!input.value.trim()) {
            isValid = false;
            input.classList.add('error');
        } else {
            input.classList.remove('error');
        }
    });

    if (!isValid) {
        alert('Por favor, preencha todos os campos obrigatórios.');
        return;
    }

    // Preparar dados para envio
    const formData = {
        id: document.getElementById('manutencaoId').value,
        data_manutencao: document.getElementById('data_manutencao').value,
        veiculo_id: document.getElementById('veiculo_id').value,
        tipo_manutencao_id: document.getElementById('tipo_manutencao_id').value,
        componente_id: document.getElementById('componente_id').value,
        status_manutencao_id: document.getElementById('status_manutencao_id').value,
        km_atual: document.getElementById('km_atual').value,
        fornecedor: document.getElementById('fornecedor').value,
        valor: document.getElementById('valor').value,
        custo_total: document.getElementById('custo_total').value,
        nota_fiscal: document.getElementById('nota_fiscal').value,
        descricao: document.getElementById('descricao').value,
        descricao_servico: document.getElementById('descricao_servico').value,
        observacoes: document.getElementById('observacoes').value,
        responsavel_aprovacao: document.getElementById('responsavel_aprovacao').value
    };

    // Determinar se é uma nova manutenção ou atualização
    const method = formData.id ? 'PUT' : 'POST';

    // Enviar dados
    fetch('../api/manutencoes.php', {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        },
        credentials: 'include',
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            // Fechar modal e recarregar dados
            const modal = document.getElementById('maintenanceModal');
            if (modal) {
                modal.classList.remove('active');
            }
            window.location.reload();
        } else {
            alert('Erro ao salvar manutenção: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar manutenção');
    });
}

function showDeleteConfirmation(id) {
    const modal = document.getElementById('deleteMaintenanceModal');
    if (modal) {
        modal.classList.add('active');
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        if (confirmBtn) {
            confirmBtn.onclick = () => deleteMaintenance(id);
        }
    }
}

function deleteMaintenance(id) {
    if (!confirm('Tem certeza que deseja excluir esta manutenção?')) {
        return;
    }

    fetch(`../api/manutencoes.php?id=${id}`, {
        method: 'DELETE',
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.reload();
        } else {
            alert('Erro ao excluir manutenção: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao excluir manutenção');
    });
}

// Funções auxiliares
function loadVehicles() {
    fetch('../api/vehicle_data.php', {
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        const select = document.getElementById('veiculo_id');
        if (select && data.data) {
            data.data.forEach(vehicle => {
                const option = document.createElement('option');
                option.value = vehicle.id;
                option.textContent = `${vehicle.placa} - ${vehicle.modelo}`;
                select.appendChild(option);
            });
        }
    })
    .catch(error => console.error('Erro ao carregar veículos:', error));
}

function loadMaintenanceData() {
    // Implementar carregamento de dados para os gráficos e métricas
    console.log('Loading maintenance data...');
}

function initializeCharts() {
    // Implementar inicialização dos gráficos
    console.log('Initializing charts...');
}

function setupFilters() {
    const tableBody = document.querySelector('#maintenanceTable tbody');
    if (!tableBody) {
        return;
    }

    const searchInput = document.getElementById('searchMaintenance');
    const vehicleFilter = document.getElementById('vehicleFilter');
    const maintenanceTypeFilter = document.getElementById('maintenanceTypeFilter');
    const statusFilter = document.getElementById('statusFilter');
    const supplierFilter = document.getElementById('supplierFilter');
    const applyBtn = document.getElementById('applyMaintenanceFilters');
    const clearBtn = document.getElementById('clearMaintenanceFilters');

    const applyFilters = () => {
        const rows = tableBody.querySelectorAll('tr');
        const searchTerm = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const selectedVehicle = vehicleFilter ? vehicleFilter.value.toLowerCase() : '';
        const selectedType = maintenanceTypeFilter ? maintenanceTypeFilter.value.toLowerCase() : '';
        const selectedStatus = statusFilter ? statusFilter.value.toLowerCase() : '';
        const selectedSupplier = supplierFilter ? supplierFilter.value.toLowerCase() : '';

        rows.forEach(row => {
            const rowText = row.textContent.toLowerCase();
            const vehicleCell = row.querySelector('td:nth-child(2)');
            const typeCell = row.querySelector('td:nth-child(3)');
            const supplierCell = row.querySelector('td:nth-child(5)');
            const statusCell = row.querySelector('td:nth-child(6)');

            const matchesSearch = !searchTerm || rowText.includes(searchTerm);
            const matchesVehicle = !selectedVehicle || (vehicleCell && vehicleCell.textContent.toLowerCase().includes(selectedVehicle));
            const matchesType = !selectedType || (typeCell && typeCell.textContent.toLowerCase().includes(selectedType));
            const matchesSupplier = !selectedSupplier || (supplierCell && supplierCell.textContent.toLowerCase().includes(selectedSupplier));
            const matchesStatus = !selectedStatus || (statusCell && statusCell.textContent.toLowerCase().includes(selectedStatus));

            row.style.display = (matchesSearch && matchesVehicle && matchesType && matchesSupplier && matchesStatus) ? '' : 'none';
        });
    };

    if (searchInput) {
        searchInput.addEventListener('input', debounce(applyFilters, 200));
    }

    if (vehicleFilter) vehicleFilter.addEventListener('change', applyFilters);
    if (maintenanceTypeFilter) maintenanceTypeFilter.addEventListener('change', applyFilters);
    if (statusFilter) statusFilter.addEventListener('change', applyFilters);
    if (supplierFilter) supplierFilter.addEventListener('change', applyFilters);

    if (applyBtn) {
        applyBtn.addEventListener('click', () => {
            applyFilters();
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (vehicleFilter) vehicleFilter.value = '';
            if (maintenanceTypeFilter) maintenanceTypeFilter.value = '';
            if (statusFilter) statusFilter.value = '';
            if (supplierFilter) supplierFilter.value = '';
            applyFilters();
        });
    }

    applyFilters();
}

function loadSuppliers() {
    // Implementation of loading suppliers
    // This will be implemented when we have the API endpoint ready
}

// Store chart instances globally
let charts = {
    costs: null,
    types: null,
    status: null,
    evolution: null,
    topVehicles: null,
    components: null
};

// Add debounce function
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

// Add loading state
let isInitializingCharts = false;

/**
 * Initialize maintenance charts
 */
async function initializeMaintenanceCharts() {
    // Prevent multiple simultaneous initializations
    if (isInitializingCharts) {
        console.log('Chart initialization already in progress...');
        return;
    }

    try {
        isInitializingCharts = true;
        console.log('Iniciando carregamento dos gráficos...');
        
        // Properly destroy existing charts
        for (const chartKey in charts) {
            if (charts[chartKey] instanceof Chart) {
                try {
                    charts[chartKey].destroy();
                } catch (e) {
                    console.warn(`Error destroying chart ${chartKey}:`, e);
                }
                charts[chartKey] = null;
            }
        }

        // Clear any existing canvas contexts
        const canvasIds = ['maintenanceCostsChart', 'maintenanceTypesChart', 'maintenanceStatusChart', 
                          'maintenanceEvolutionChart', 'topVehiclesChart', 'componentsHeatmapChart'];
        
        canvasIds.forEach(id => {
            const canvas = document.getElementById(id);
            if (canvas) {
                // Get a fresh context
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                // Reset the canvas dimensions
                canvas.width = canvas.offsetWidth;
                canvas.height = canvas.offsetHeight;
            }
        });

        // Add small delay to ensure cleanup is complete
        await new Promise(resolve => setTimeout(resolve, 100));
        
        // Fetch data from server
        const response = await fetch('../includes/get_maintenance_data.php');
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        
        // Check if the response indicates an error
        if (!data.success) {
            throw new Error(data.error || 'Erro desconhecido ao carregar dados');
        }
        
        console.log('Dados recebidos:', data);

        // Initialize cost chart
        const costCtx = document.getElementById('maintenanceCostsChart');
        if (costCtx && data.costs && data.costs.labels) {
            console.log('Inicializando gráfico de custos:', data.costs);
            // Ensure we're working with a fresh context
            const ctx = costCtx.getContext('2d');
            
            // Create new chart instance
            charts.costs = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.costs.labels,
                    datasets: [
                        {
                            label: 'Manutenção Preventiva',
                            data: data.costs.preventiva,
                            backgroundColor: '#3b82f6'
                        },
                        {
                            label: 'Manutenção Corretiva',
                            data: data.costs.corretiva,
                            backgroundColor: '#ef4444'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: R$ ${context.raw.toFixed(2).replace('.', ',')}`;
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: 'Custos de Manutenção (Últimos 6 meses)'
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                        },
                        y: {
                            stacked: true,
                            title: {
                                display: true,
                                text: 'Custo (R$)'
                            }
                        }
                    }
                }
            });
        }

        // Initialize types chart
        const typesCtx = document.getElementById('maintenanceTypesChart');
        if (typesCtx && data.types && data.types.labels) {
            console.log('Inicializando gráfico de tipos:', data.types);
            charts.types = new Chart(typesCtx, {
                type: 'pie',
                data: {
                    labels: data.types.labels,
                    datasets: [{
                        data: data.types.data,
                        backgroundColor: [
                            '#3b82f6',  // Azul
                            '#ef4444',  // Vermelho
                            '#10b981',  // Verde
                            '#f59e0b',  // Amarelo
                            '#6366f1'   // Índigo
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value * 100) / total).toFixed(1);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Initialize status chart
        const statusCtx = document.getElementById('maintenanceStatusChart');
        if (statusCtx && data.status && Array.isArray(data.status)) {
            console.log('Inicializando gráfico de status:', data.status);
            charts.status = new Chart(statusCtx, {
                type: 'pie',
                data: {
                    labels: data.status.map(item => item.status),
                    datasets: [{
                        data: data.status.map(item => item.total),
                        backgroundColor: [
                            '#10b981',  // Verde - Concluída
                            '#f59e0b',  // Amarelo - Em andamento
                            '#3b82f6',  // Azul - Agendada
                            '#ef4444'   // Vermelho - Cancelada
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value * 100) / total).toFixed(1);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Initialize evolution chart
        const evolutionCtx = document.getElementById('maintenanceEvolutionChart');
        if (evolutionCtx && data.evolution && Array.isArray(data.evolution)) {
            console.log('Inicializando gráfico de evolução:', data.evolution);
            charts.evolution = new Chart(evolutionCtx, {
                type: 'line',
                data: {
                    labels: data.evolution.map(item => {
                        const [year, month] = item.mes.split('-');
                        return `${month}/${year}`;
                    }),
                    datasets: [{
                        label: 'Total de Manutenções',
                        data: data.evolution.map(item => item.total),
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Total: ${context.raw}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Quantidade'
                            }
                        }
                    }
                }
            });
        }

        // Initialize top vehicles chart
        const vehiclesCtx = document.getElementById('topVehiclesChart');
        if (vehiclesCtx && data.top_vehicles && Array.isArray(data.top_vehicles)) {
            console.log('Inicializando gráfico de top veículos:', data.top_vehicles);
            charts.topVehicles = new Chart(vehiclesCtx, {
                type: 'bar',
                data: {
                    labels: data.top_vehicles.map(item => item.placa),
                    datasets: [{
                        label: 'Custo Total',
                        data: data.top_vehicles.map(item => item.custo_total),
                        backgroundColor: '#3b82f6'
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let raw = context.raw;
                                    if (typeof raw === 'number') {
                                        return `R$ ${raw.toFixed(2).replace('.', ',')}`;
                                    } else if (!isNaN(Number(raw))) {
                                        return `R$ ${Number(raw).toFixed(2).replace('.', ',')}`;
                                    } else {
                                        return `R$ -`;
                                    }
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Custo Total (R$)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return `R$ ${value.toFixed(2).replace('.', ',')}`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Initialize components chart
        const componentsCtx = document.getElementById('componentsHeatmapChart');
        if (componentsCtx && data.components && Array.isArray(data.components)) {
            console.log('Inicializando gráfico de componentes:', data.components);
            charts.components = new Chart(componentsCtx, {
                type: 'bar',
                data: {
                    labels: data.components.map(item => item.componente),
                    datasets: [{
                        label: 'Número de Falhas',
                        data: data.components.map(item => item.total_falhas),
                        backgroundColor: data.components.map(item => {
                            const maxFalhas = Math.max(...data.components.map(i => i.total_falhas));
                            const intensity = Math.min(item.total_falhas / maxFalhas, 1);
                            return `rgba(239, 68, 68, ${intensity})`; // Vermelho com opacidade variável
                        })
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Falhas: ${context.raw}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Número de Falhas'
                            },
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Update KPI values if they exist in the data
        if (data.mtbf !== undefined) {
            const mtbfElement = document.querySelector('.dashboard-card:nth-child(5) .metric-value');
            if (mtbfElement) {
                mtbfElement.textContent = `${data.mtbf.toFixed(1)} km`;
            }
        }

        if (data.mttr !== undefined) {
            const mttrElement = document.querySelector('.dashboard-card:nth-child(6) .metric-value');
            if (mttrElement) {
                mttrElement.textContent = `${data.mttr.toFixed(1)} h`;
            }
        }

        if (data.cost_per_km !== undefined) {
            const costKmElement = document.querySelector('.dashboard-card:nth-child(7) .metric-value');
            if (costKmElement) {
                costKmElement.textContent = `R$ ${data.cost_per_km.toFixed(2).replace('.', ',')}`;
            }
        }

    } catch (error) {
        console.error('Erro ao carregar dados dos gráficos:', error);
        console.error('Stack trace:', error.stack);
        const errorMessage = `Erro ao carregar dados dos gráficos: ${error.message}`;
        alert(errorMessage);
    } finally {
        isInitializingCharts = false;
    }
}

// Create debounced version of the initialization function
const debouncedInitializeCharts = debounce(initializeMaintenanceCharts, 250);

function changePage(page) {
    if (page < 1) return false;
    
    // Update URL with new page number
    const url = new URL(window.location.href);
    url.searchParams.set('page', page);
    window.location.href = url.toString();
    
    return false; // Prevent default anchor behavior
} 