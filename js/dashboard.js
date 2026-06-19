/**
 * Dashboard UI and functionality
 */
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
    g.sfAppBase = g.sfAppBase || function () {
        var b = typeof g.__SF_APP_BASE__ === 'string' ? String(g.__SF_APP_BASE__).replace(/\/+$/, '') : '';
        return b;
    };
})(typeof window !== 'undefined' ? window : this);

// Array global para armazenar requisições AJAX ativas
window.activeAjaxRequests = [];

document.addEventListener('DOMContentLoaded', function() {
    // Initialize dashboard components
    initDashboard();
    
    // Setup modals
    setupModals();
    
    // Setup logout handler
    setupLogoutHandler();
    
    // Página inicial já renderiza KPIs no servidor; evita API legada com dados fictícios.
    if (document.querySelector('.dashboard-content') && !document.body.dataset.dashboardHome) {
        loadDashboardData();
    }
});

/**
 * Wait for Chart.js to be loaded and then initialize charts
 */
function waitForChartJS() {
    if (typeof Chart !== 'undefined') {
        // Chart.js is loaded, initialize only existing charts
        // Only initialize financial chart if canvas exists
        if (document.getElementById('financialChart')) {
            initFinancialChart();
        }
        
        // Only initialize other charts if their canvas elements exist
        if (document.getElementById('fuelConsumptionChart')) {
            initFuelConsumptionChart();
        }
        
        if (document.getElementById('costAnalysisChart')) {
            initCostAnalysisChart();
        }
    } else {
        // Wait a bit and try again
        setTimeout(waitForChartJS, 100);
    }
}

/**
 * Initialize dashboard
 */
function initDashboard() {
    // Setup event listeners
    setupLogoutHandler();
    
    // Página inicial (index.php): gráficos e KPIs vêm do PHP — não chamar API legada.
    const isHomeDashboard = document.body && document.body.dataset.dashboardHome === '1';
    if (!isHomeDashboard) {
        loadDashboardData();
    }
    
    // Setup modals
    setupModals();
    
    // Initialize charts after Chart.js is loaded (exceto financialChart na home — script inline)
    if (!isHomeDashboard) {
        waitForChartJS();
    }
    
    // Drag-and-drop por seção (Operação / Custos / Financeiro). Cada grade é
    // independente: reordenar só acontece dentro da própria seção.
    const dashboardGrids = document.querySelectorAll('.dashboard-grid[data-dash-section]');
    if (dashboardGrids.length) {
        // Restaura a ordem salva (por seção) antes de ligar o Sortable.
        let savedLayout = null;
        try { savedLayout = JSON.parse(localStorage.getItem('dashboardLayoutV2') || 'null'); }
        catch (e) { savedLayout = null; }

        dashboardGrids.forEach(grid => {
            const sec = grid.getAttribute('data-dash-section');
            if (savedLayout && Array.isArray(savedLayout[sec])) {
                savedLayout[sec].forEach(id => {
                    const card = grid.querySelector(`[data-card-id="${id}"]`);
                    if (card) grid.appendChild(card);
                });
            }
            new Sortable(grid, {
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: saveCurrentLayout
            });
        });

        // Modo compacto aplica a todas as seções.
        if (localStorage.getItem('dashboardCompactLayout') === 'true') {
            dashboardGrids.forEach(g => g.classList.add('compact-layout'));
            const btn = document.getElementById('toggleLayoutBtn');
            if (btn) btn.innerHTML = '<i class="fas fa-expand"></i> Expandir';
        }
    }
    
    // Dashboard card hover effects
    const dashboardCards = document.querySelectorAll('.dashboard-card');
    dashboardCards.forEach(card => {
        card.addEventListener('mouseenter', () => {
            card.classList.add('card-hover');
        });
        
        card.addEventListener('mouseleave', () => {
            card.classList.remove('card-hover');
        });

        const href = card.getAttribute('data-href');
        if (href) {
            card.addEventListener('click', (e) => {
                if (e.target.closest('button, a, input, select, textarea, label')) return;
                window.location.href = href;
            });
            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    window.location.href = href;
                }
            });
        }
        
        // Expand card on button click
        const expandBtn = card.querySelector('.btn-card-expand');
        if (expandBtn) {
            expandBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                expandDashboardCard(card);
            });
        }
    });
    
    // Add widget button
    const addWidgetBtn = document.getElementById('addWidgetBtn');
    if (addWidgetBtn) {
        addWidgetBtn.addEventListener('click', showAddWidgetModal);
    }
    
    // Restore layout button
    const restoreLayoutBtn = document.getElementById('restoreLayoutBtn');
    if (restoreLayoutBtn) {
        restoreLayoutBtn.addEventListener('click', restoreDefaultLayout);
    }
    
    // Toggle layout button
    const toggleLayoutBtn = document.getElementById('toggleLayoutBtn');
    if (toggleLayoutBtn) {
        toggleLayoutBtn.addEventListener('click', toggleGridLayout);
    }
}

/**
 * Setup logout handler
 */
function setupLogoutHandler() {
    const logoutLink = document.getElementById('logoutLink');
    if (logoutLink) {
        logoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            // Redirecionar diretamente para o logout
            window.location.href = (typeof sfAppBase === 'function' && sfAppBase() !== '' ? sfAppBase() + '/' : '/') + 'logout.php';
        });
    }
}

/**
 * Load dashboard data using AJAX
 */
function loadDashboardData() {
    fetch(sfApiUrl('dashboard_data.php'))
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data && !data.error) {
                updateDashboardCards(data);
                updateMaintenanceTable(data.maintenanceData);
                // Charts are now initialized by waitForChartJS()
            }
        })
        .catch(error => {
            console.error('There was a problem with the fetch operation:', error);
        });
}

/**
 * Update dashboard cards with data
 * @param {Object} data Dashboard data
 */
function updateDashboardCards(data) {
    // Os KPIs do dashboard são renderizados no servidor (PHP) com os valores já
    // consolidados pelo filtro de período. Esta função foi mantida apenas para
    // compatibilidade do fluxo de loadDashboardData(); não há cards client-side
    // para atualizar (os IDs antigos vehicles/motorists/... não existem mais).
    return;
}

/**
 * Update maintenance table with data
 * @param {Array} maintenanceData Maintenance data
 */
function updateMaintenanceTable(maintenanceData) {
    if (!maintenanceData || !maintenanceData.length) return;
    
    const tableBody = document.getElementById('maintenanceTableBody');
    if (!tableBody) return;
    
    // Clear existing rows
    tableBody.innerHTML = '';
    
    // Add new rows
    maintenanceData.forEach(item => {
        const row = document.createElement('tr');
        
        // Format date
        const date = new Date(item.date);
        const formattedDate = `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth() + 1).toString().padStart(2, '0')}/${date.getFullYear()}`;
        
        // Create table cells
        row.innerHTML = `
            <td>${formattedDate}</td>
            <td>${item.vehicle}</td>
            <td>${item.type}</td>
            <td>R$ ${item.value.toFixed(2).replace('.', ',')}</td>
        `;
        
        tableBody.appendChild(row);
    });
}

/**
 * Expand a dashboard card to full-screen view
 * @param {HTMLElement} card Card element to expand
 */
function expandDashboardCard(card) {
    const cardId = card.getAttribute('data-card-id');
    const cardTitle = card.querySelector('.card-header h3').textContent;
    
    // Create expanded view
    const expandedView = document.createElement('div');
    expandedView.classList.add('expanded-card-view');
    expandedView.innerHTML = `
        <div class="expanded-card-header">
            <h2>${cardTitle}</h2>
            <button class="close-expanded-view">&times;</button>
        </div>
        <div class="expanded-card-content" id="expandedContent-${cardId}">
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i>
                <span>Carregando dados detalhados...</span>
            </div>
        </div>
    `;
    
    // Add to document
    document.body.appendChild(expandedView);
    document.body.classList.add('expanded-view-active');
    
    // Setup close button
    expandedView.querySelector('.close-expanded-view').addEventListener('click', () => {
        document.body.removeChild(expandedView);
        document.body.classList.remove('expanded-view-active');
    });
    
    // Load detailed data for the card
    loadDetailedCardData(cardId);
}

/**
 * Load detailed data for expanded card view
 * @param {string} cardId ID of the card to load data for
 */
function loadDetailedCardData(cardId) {
    const contentContainer = document.getElementById(`expandedContent-${cardId}`);
    if (!contentContainer) return;
    
    // Different data endpoint based on card ID
    let endpoint = '';
    
    switch(cardId) {
        case 'vehicles':
            endpoint = 'api/vehicle_data.php';
            break;
        case 'motorists':
            endpoint = 'api/motorist_data.php';
            break;
        case 'supply':
            endpoint = 'api/supply_data.php';
            break;
        case 'routes':
            endpoint = 'api/route_data.php';
            break;
        default:
            endpoint = `api/dashboard_data.php?detail=${cardId}`;
    }
    
    // Fetch detailed data
    fetch(endpoint)
        .then(response => response.json())
        .then(data => {
            // Clear loading indicator
            contentContainer.innerHTML = '';
            
            // Render detailed content based on card type
            switch(cardId) {
                case 'vehicles':
                    renderVehicleDetails(contentContainer, data);
                    break;
                case 'motorists':
                    renderMotoristDetails(contentContainer, data);
                    break;
                case 'routes':
                    renderRouteDetails(contentContainer, data);
                    break;
                case 'supply':
                    renderSupplyDetails(contentContainer, data);
                    break;
                case 'productivity':
                    renderProductivityDetails(contentContainer, data);
                    break;
                case 'liquid-value':
                    renderFinancialDetails(contentContainer, data);
                    break;
            }
        })
        .catch(error => {
            const msg = (typeof sfSafeErrorMessage === 'function')
                ? sfSafeErrorMessage(error, 'Falha ao carregar.')
                : ((error && error.message) || 'Falha ao carregar.');
            contentContainer.innerHTML = `
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Erro ao carregar dados: ${msg}</p>
                </div>
            `;
        });
}

/**
 * Render vehicle details in expanded view
 * @param {HTMLElement} container Container element
 * @param {Object} data Vehicle data
 */
function renderVehicleDetails(container, data) {
    if (!data || !data.vehicles || !data.vehicles.length) {
        container.innerHTML = '<p>Nenhum veículo encontrado.</p>';
        return;
    }
    
    // Create table for vehicles
    const table = document.createElement('table');
    table.classList.add('data-table');
    
    table.innerHTML = `
        <thead>
            <tr>
                <th>Placa</th>
                <th>Modelo</th>
                <th>Ano</th>
                <th>Status</th>
                <th>Quilometragem</th>
                <th>Última Manutenção</th>
            </tr>
        </thead>
        <tbody>
            ${data.vehicles.map(vehicle => `
                <tr>
                    <td>${vehicle.plate}</td>
                    <td>${vehicle.model}</td>
                    <td>${vehicle.year}</td>
                    <td><span class="status-badge status-${vehicle.status.toLowerCase()}">${vehicle.status}</span></td>
                    <td>${vehicle.mileage} km</td>
                    <td>${vehicle.lastMaintenance}</td>
                </tr>
            `).join('')}
        </tbody>
    `;
    
    container.appendChild(table);
    
    // Add chart if available
    if (data.chart) {
        const chartContainer = document.createElement('div');
        chartContainer.classList.add('chart-container');
        chartContainer.innerHTML = '<canvas id="vehicleChart"></canvas>';
        
        container.appendChild(chartContainer);
        
        // Initialize chart with data
        const ctx = document.getElementById('vehicleChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.chart.labels,
                datasets: [{
                    label: data.chart.title,
                    data: data.chart.data,
                    backgroundColor: data.chart.colors || ['#3b82f6', '#0ea5e9', '#10b981']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: data.chart.title
                    }
                }
            }
        });
    }
}

/**
 * Setup modal dialogs
 */
function setupModals() {
    // Add Widget Modal
    const addWidgetModal = document.getElementById('addWidgetModal');
    const closeModal = document.querySelector('.close-modal');
    const cancelWidgetBtn = document.getElementById('cancelWidgetBtn');
    
    if (closeModal) {
        closeModal.addEventListener('click', () => {
            addWidgetModal.classList.remove('active');
        });
    }
    
    if (cancelWidgetBtn) {
        cancelWidgetBtn.addEventListener('click', () => {
            addWidgetModal.classList.remove('active');
        });
    }
    
    // Widget selection in modal
    const widgetOptions = document.querySelectorAll('.widget-option');
    let selectedWidget = null;
    
    widgetOptions.forEach(option => {
        option.addEventListener('click', () => {
            // Clear previous selection
            widgetOptions.forEach(opt => opt.classList.remove('selected'));
            
            // Select clicked widget
            option.classList.add('selected');
            selectedWidget = option.getAttribute('data-widget');
        });
    });
    
    // Add selected widget
    const addSelectedWidgetBtn = document.getElementById('addSelectedWidgetBtn');
    if (addSelectedWidgetBtn) {
        addSelectedWidgetBtn.addEventListener('click', () => {
            if (selectedWidget) {
                addWidgetToDashboard(selectedWidget);
                addWidgetModal.classList.remove('active');
            } else {
                alert('Por favor, selecione um widget para adicionar.');
            }
        });
    }
}

/**
 * Show Add Widget modal
 */
function showAddWidgetModal() {
    const modal = document.getElementById('addWidgetModal');
    if (modal) {
        modal.classList.add('active');
    }
}

/**
 * Add selected widget to dashboard
 * @param {string} widgetType Type of widget to add
 */
function addWidgetToDashboard(widgetType) {
    // Create new widget based on type
    const dashboardGrid = document.getElementById('dashboardGrid');
    
    if (!dashboardGrid) return;
    
    // Define widget content based on type
    let widgetTitle = '';
    let widgetContent = '';
    
    switch(widgetType) {
        case 'fuel-consumption':
            widgetTitle = 'Consumo de Combustível';
            widgetContent = `
                <div class="metric">
                    <span class="metric-value">9.8</span>
                    <span class="metric-subtitle">km/litro (média)</span>
                </div>
                <div class="chart-container">
                    <canvas id="fuelConsumptionChart"></canvas>
                </div>
            `;
            break;
        case 'maintenance-schedule':
            widgetTitle = 'Agenda de Manutenção';
            widgetContent = `
                <div class="upcoming-maintenance">
                    <div class="upcoming-item">
                        <div class="upcoming-date">15/05</div>
                        <div class="upcoming-details">
                            <strong>Troca de Óleo</strong>
                            <span>Veículo: ABC-1234</span>
                        </div>
                    </div>
                    <div class="upcoming-item">
                        <div class="upcoming-date">22/05</div>
                        <div class="upcoming-details">
                            <strong>Revisão dos Freios</strong>
                            <span>Veículo: DEF-5678</span>
                        </div>
                    </div>
                </div>
            `;
            break;
        case 'route-map':
            widgetTitle = 'Mapa de Rotas';
            widgetContent = `
                <div class="map-container">
                    <div class="map-placeholder">
                        <i class="fas fa-map-marked-alt"></i>
                        <span>Mapa de rotas ativas</span>
                    </div>
                </div>
            `;
            break;
        case 'cost-analysis':
            widgetTitle = 'Análise de Custos';
            widgetContent = `
                <div class="chart-container">
                    <canvas id="costAnalysisChart"></canvas>
                </div>
            `;
            break;
        case 'driver-performance':
            widgetTitle = 'Desempenho dos Motoristas';
            widgetContent = `
                <div class="driver-ranking">
                    <div class="driver-item">
                        <div class="driver-rank">1</div>
                        <div class="driver-avatar">JD</div>
                        <div class="driver-details">
                            <strong>João da Silva</strong>
                            <div class="driver-stats">
                                <span>Rotas: 28</span>
                                <span>Pontuação: 98</span>
                            </div>
                        </div>
                    </div>
                    <div class="driver-item">
                        <div class="driver-rank">2</div>
                        <div class="driver-avatar">MS</div>
                        <div class="driver-details">
                            <strong>Maria Santos</strong>
                            <div class="driver-stats">
                                <span>Rotas: 25</span>
                                <span>Pontuação: 95</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            break;
        case 'vehicle-status':
            widgetTitle = 'Status dos Veículos';
            widgetContent = `
                <div class="status-overview">
                    <div class="status-item">
                        <div class="status-icon active">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="status-count">3</div>
                        <div class="status-label">Ativos</div>
                    </div>
                    <div class="status-item">
                        <div class="status-icon maintenance">
                            <i class="fas fa-tools"></i>
                        </div>
                        <div class="status-count">1</div>
                        <div class="status-label">Manutenção</div>
                    </div>
                    <div class="status-item">
                        <div class="status-icon inactive">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="status-count">0</div>
                        <div class="status-label">Inativos</div>
                    </div>
                </div>
            `;
            break;
    }
    
    // Create card element
    const card = document.createElement('div');
    card.classList.add('dashboard-card');
    card.setAttribute('data-card-id', widgetType);
    
    card.innerHTML = `
        <div class="card-header">
            <h3>${widgetTitle}</h3>
            <div class="card-actions">
                <button class="btn-card-expand"><i class="fas fa-expand"></i></button>
                <button class="btn-card-remove"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div class="card-body">
            ${widgetContent}
        </div>
    `;
    
    // Add to dashboard
    dashboardGrid.appendChild(card);
    
    // Setup remove button
    const removeBtn = card.querySelector('.btn-card-remove');
    if (removeBtn) {
        removeBtn.addEventListener('click', () => {
            dashboardGrid.removeChild(card);
            saveCurrentLayout();
        });
    }
    
    // Setup expand button
    const expandBtn = card.querySelector('.btn-card-expand');
    if (expandBtn) {
        expandBtn.addEventListener('click', () => {
            expandDashboardCard(card);
        });
    }
    
    // Initialize charts if needed
    if (widgetType === 'fuel-consumption') {
        initFuelConsumptionChart();
    } else if (widgetType === 'cost-analysis') {
        initCostAnalysisChart();
    }
    
    // Save current layout
    saveCurrentLayout();
}

/**
 * Initialize fuel consumption chart
 */
function initFuelConsumptionChart() {
    // Check if Chart.js is available
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not loaded yet, retrying in 1 second...');
        setTimeout(initFuelConsumptionChart, 1000);
        return;
    }
    
    const ctx = document.getElementById('fuelConsumptionChart');
    if (!ctx) {
        // Canvas doesn't exist, this is expected for some pages
        return;
    }
    
    try {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
                datasets: [{
                    label: 'Consumo Médio (km/l)',
                    data: [8.5, 8.2, 8.8, 8.1, 8.9, 8.6],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Consumo de Combustível (Últimos 6 meses)',
                        font: {
                            size: 16
                        }
                    },
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return `${value} km/l`;
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.raw} km/l`;
                            }
                        }
                    }
                }
            }
        });
    } catch (error) {
        console.error('Error creating fuel consumption chart:', error);
    }
}

/**
 * Initialize cost analysis chart
 */
function initCostAnalysisChart() {
    // Check if Chart.js is available
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not loaded yet, retrying in 1 second...');
        setTimeout(initCostAnalysisChart, 1000);
        return;
    }
    
    const ctx = document.getElementById('costAnalysisChart');
    if (!ctx) {
        // Canvas doesn't exist, this is expected for some pages
        return;
    }
    
    try {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: [
                    'Combustível',
                    'Manutenção',
                    'Impostos',
                    'Pessoal',
                    'Outros'
                ],
                datasets: [{
                    data: [45, 25, 10, 15, 5],
                    backgroundColor: [
                        '#3b82f6',
                        '#ef4444',
                        '#f59e0b',
                        '#10b981',
                        '#8b5cf6'
                    ]
                }]
            },
            options: {
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.raw;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${percentage}% (R$ ${(value * 100).toFixed(2).replace('.', ',')})`;
                            }
                        }
                    }
                }
            }
        });
    } catch (error) {
        console.error('Error creating cost analysis chart:', error);
    }
}

/**
 * Save current dashboard layout
 */
function saveCurrentLayout() {
    // Salva a ordem dos cards por seção: { operacao:[ids], custos:[ids], financeiro:[ids] }
    const layout = {};
    document.querySelectorAll('.dashboard-grid[data-dash-section]').forEach(grid => {
        const sec = grid.getAttribute('data-dash-section');
        layout[sec] = Array.from(grid.querySelectorAll('.dashboard-card'))
            .map(card => card.getAttribute('data-card-id'))
            .filter(Boolean);
    });
    localStorage.setItem('dashboardLayoutV2', JSON.stringify(layout));
}

/**
 * Restore default dashboard layout
 */
function restoreDefaultLayout() {
    // Limpa ordem e modo compacto salvos e recarrega para voltar ao padrão.
    localStorage.removeItem('dashboardLayoutV2');
    localStorage.removeItem('dashboardLayout');
    localStorage.removeItem('dashboardCompactLayout');
    window.location.reload();
}

/**
 * Toggle grid layout (compact/expanded) — aplica a todas as seções.
 */
function toggleGridLayout() {
    const grids = document.querySelectorAll('.dashboard-grid[data-dash-section]');
    if (!grids.length) return;

    const willCompact = !grids[0].classList.contains('compact-layout');
    grids.forEach(g => g.classList.toggle('compact-layout', willCompact));
    localStorage.setItem('dashboardCompactLayout', willCompact);

    // Atualiza o rótulo do botão, se existir.
    const btn = document.getElementById('toggleLayoutBtn');
    if (btn) {
        btn.innerHTML = willCompact
            ? '<i class="fas fa-expand"></i> Expandir'
            : '<i class="fas fa-compress"></i> Compactar';
    }
}

/**
 * Utility function to safely destroy a chart if it exists
 * @param {string} canvasId The canvas element ID
 */
function destroyChartIfExists(canvasId) {
    // Check if Chart.js is available
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not loaded yet');
        return;
    }
    
    try {
        const existingChart = Chart.getChart(canvasId);
        if (existingChart) {
            existingChart.destroy();
        }
    } catch (error) {
        console.warn('Error destroying chart:', error);
    }
}

/**
 * Initialize financial chart
 */
function initFinancialChart() {
    // Check if Chart.js is available
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not loaded yet, retrying in 1 second...');
        setTimeout(initFinancialChart, 1000);
        return;
    }
    
    // Check if canvas exists
    const ctx = document.getElementById('financialChart');
    if (!ctx) {
        console.warn('Financial chart canvas not found');
        return;
    }
    
    // Destroy existing chart if it exists
    destroyChartIfExists('financialChart');

    fetch(sfApiUrl('financial_analytics.php'))
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            // Check again if Chart.js is available
            if (typeof Chart === 'undefined') {
                console.error('Chart.js not available after data fetch');
                return;
            }
            
            // Check if canvas still exists
            const ctx = document.getElementById('financialChart');
            if (!ctx) {
                console.warn('Financial chart canvas not found after data fetch');
                return;
            }

            try {
                new Chart(ctx, {
                    type: 'bar',
                    data: data,
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Faturamento x Despesas (Mensal)',
                                font: {
                                    size: 16
                                }
                            },
                            legend: {
                                position: 'top'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed.y !== null) {
                                            label += new Intl.NumberFormat('pt-BR', {
                                                style: 'currency',
                                                currency: 'BRL'
                                            }).format(context.parsed.y);
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return new Intl.NumberFormat('pt-BR', {
                                            style: 'currency',
                                            currency: 'BRL',
                                            maximumFractionDigits: 0
                                        }).format(value);
                                    }
                                }
                            }
                        }
                    }
                });
            } catch (error) {
                console.error('Error creating financial chart:', error);
            }
        })
        .catch(error => {
            console.error('Error loading financial data:', error);
        });
}
