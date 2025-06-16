/**
 * Chart generation and management
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize any charts on the dashboard
    initializeCharts();
});

/**
 * Initialize charts on dashboard
 */
function initializeCharts() {
    // Setup chart defaults
    setupChartDefaults();
    
    // Initialize specific charts if their containers exist
    // These would be generated dynamically in real app based on loaded data
}

/**
 * Setup global Chart.js defaults
 */
function setupChartDefaults() {
    if (typeof Chart === 'undefined') return;
    
    // Get current theme
    const currentTheme = document.body.classList.contains('light-theme') ? 'light' : 'dark';
    
    // Define colors based on theme
    const textColor = currentTheme === 'light' ? '#1f2937' : '#f3f4f6';
    const gridColor = currentTheme === 'light' ? 'rgba(0, 0, 0, 0.1)' : 'rgba(255, 255, 255, 0.1)';
    
    // Set global defaults
    Chart.defaults.color = textColor;
    Chart.defaults.borderColor = gridColor;
    Chart.defaults.font.family = "'Inter', 'Segoe UI', Roboto, sans-serif";
    
    // Scale defaults
    Chart.defaults.scale.grid.color = gridColor;
    Chart.defaults.scale.ticks.color = textColor;
    
    // Plugin defaults
    Chart.defaults.plugins.tooltip.backgroundColor = currentTheme === 'light' ? 'rgba(0, 0, 0, 0.7)' : 'rgba(0, 0, 0, 0.9)';
    Chart.defaults.plugins.tooltip.titleColor = '#ffffff';
    Chart.defaults.plugins.tooltip.bodyColor = '#ffffff';
    Chart.defaults.plugins.tooltip.borderColor = gridColor;
    Chart.defaults.plugins.tooltip.borderWidth = 1;
    Chart.defaults.plugins.legend.labels.color = textColor;
}

/**
 * Create a fuel efficiency chart
 * @param {string} canvasId ID of the canvas element
 * @param {Object} data Chart data
 */
function createFuelEfficiencyChart(canvasId, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    
    // Default data if not provided
    if (!data) {
        data = {
            labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai'],
            datasets: [
                {
                    label: 'Caminhão XYZ-123',
                    data: [9.2, 8.7, 9.0, 9.5, 9.8],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.2
                },
                {
                    label: 'Caminhão ABC-456',
                    data: [8.3, 8.8, 8.5, 9.0, 9.3],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.2
                }
            ]
        };
    }
    
    return new Chart(ctx, {
        type: 'line',
        data: data,
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.dataset.label}: ${context.raw} km/l`;
                        }
                    }
                },
                title: {
                    display: true,
                    text: 'Eficiência de Combustível (km/l)'
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    title: {
                        display: true,
                        text: 'km/l'
                    },
                    min: 6
                }
            }
        }
    });
}

/**
 * Create a maintenance cost chart
 * @param {string} canvasId ID of the canvas element
 * @param {Object} data Chart data
 */
function createMaintenanceCostChart(canvasId, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    
    // Default data if not provided
    if (!data) {
        data = {
            labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai'],
            datasets: [
                {
                    label: 'Manutenção Preventiva',
                    data: [1200, 800, 1500, 900, 1100],
                    backgroundColor: '#3b82f6'
                },
                {
                    label: 'Manutenção Corretiva',
                    data: [500, 1200, 600, 1600, 400],
                    backgroundColor: '#ef4444'
                }
            ]
        };
    }
    
    return new Chart(ctx, {
        type: 'bar',
        data: data,
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
                    text: 'Custos de Manutenção'
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

/**
 * Create a vehicle status distribution chart
 * @param {string} canvasId ID of the canvas element
 * @param {Object} data Chart data
 */
function createVehicleStatusChart(canvasId, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    
    // Default data if not provided
    if (!data) {
        data = {
            labels: ['Em Trânsito', 'Disponível', 'Manutenção', 'Inativo'],
            data: [3, 2, 1, 0],
            colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444']
        };
    }
    
    return new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.data,
                backgroundColor: data.colors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                title: {
                    display: true,
                    text: 'Status da Frota'
                }
            },
            cutout: '60%'
        }
    });
}

/**
 * Create a cost breakdown chart
 * @param {string} canvasId ID of the canvas element
 * @param {Object} data Chart data
 */
function createCostBreakdownChart(canvasId, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    
    // Default data if not provided
    if (!data) {
        data = {
            labels: ['Combustível', 'Manutenção', 'Pessoal', 'Impostos', 'Outros'],
            data: [40, 25, 20, 10, 5],
            colors: ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6']
        };
    }
    
    return new Chart(ctx, {
        type: 'pie',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.data,
                backgroundColor: data.colors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                title: {
                    display: true,
                    text: 'Distribuição de Custos'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.raw;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${context.label}: ${percentage}%`;
                        }
                    }
                }
            }
        }
    });
}

/**
 * Create a driver performance comparison chart
 * @param {string} canvasId ID of the canvas element
 * @param {Object} data Chart data
 */
function createDriverPerformanceChart(canvasId, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    
    // Default data if not provided
    if (!data) {
        data = {
            labels: ['João', 'Maria', 'Carlos', 'Ana', 'Pedro'],
            datasets: [
                {
                    label: 'Eficiência de Combustível',
                    data: [85, 92, 78, 90, 82],
                    backgroundColor: '#3b82f6'
                },
                {
                    label: 'Pontuação de Segurança',
                    data: [90, 88, 82, 95, 79],
                    backgroundColor: '#10b981'
                },
                {
                    label: 'Pontualidade',
                    data: [75, 95, 85, 88, 92],
                    backgroundColor: '#f59e0b'
                }
            ]
        };
    }
    
    return new Chart(ctx, {
        type: 'radar',
        data: data,
        options: {
            responsive: true,
            scales: {
                r: {
                    beginAtZero: true,
                    min: 0,
                    max: 100,
                    ticks: {
                        stepSize: 20
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Desempenho dos Motoristas'
                }
            }
        }
    });
}
