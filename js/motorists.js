// Motorists management JavaScript

document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded, initializing...');
    
    // Initialize page
    initializePage();
    
    // Setup modal events
    setupModals();
    
    // Setup filters
    setupFilters();
    
    // Setup form submission
    setupFormSubmission();
    
    // Setup modal close buttons
    document.querySelectorAll('.close-modal').forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                closeModal(modal.id);
            }
        });
    });

    // Setup help button
    setupHelpButton();

    // Garantir que o botão cancelar sempre fecha o modal, mesmo se renderizado depois
    setTimeout(function() {
        const cancelBtn = document.getElementById('cancelMotoristBtn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function(e) {
                e.preventDefault();
                closeModal('motoristModal');
            });
        }
    }, 500);
});

let currentPage = 1;
let totalPages = 1;
let currentMotoristId = null;
let performanceChart = null;

function initializePage() {
    // Load initial data
    loadMotorists(1);
    
    // Setup button events
    document.getElementById('addMotoristBtn')?.addEventListener('click', showAddMotoristModal);
    
    // Setup table buttons
    setupTableButtons();
    
    // Setup pagination
    setupPagination();
    
    // Setup help button
    setupHelpButton();
}

function setupModals() {
    // Close modal when clicking outside (exclude help modal)
    document.querySelectorAll('.modal:not(#helpMotoristsModal)').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeAllModals();
            }
        });
    });
    
    // Close modal when clicking X button (exclude help modal)
    document.querySelectorAll('.modal:not(#helpMotoristsModal) .close-modal').forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                closeModal(modal.id);
            }
        });
    });
    
    // Close modal when clicking cancel button
    document.getElementById('cancelMotoristBtn')?.addEventListener('click', function() {
        document.getElementById('motoristModal').style.display = 'none';
    });
    
    // Special handling for help modal
    const helpModal = document.getElementById('helpMotoristsModal');
    if (helpModal) {
        // Close help modal when clicking outside
        helpModal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
        
        // Close help modal when clicking X button
        const helpCloseBtn = helpModal.querySelector('.close-modal');
        if (helpCloseBtn) {
            helpCloseBtn.addEventListener('click', function() {
                helpModal.classList.remove('active');
            });
        }
    }
}

function switchTab(tabId) {
    console.log('Switching to tab:', tabId, 'Current motorist ID:', currentMotoristId);
    
    if (!currentMotoristId) {
        console.error('No motorist selected');
        showNotification('Nenhum motorista selecionado', 'error');
        return;
    }

    // Remove active class from all tabs and contents
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    // Add active class to clicked tab and its content
    const tabButton = document.querySelector(`.tab-btn[data-tab="${tabId}"]`);
    const tabContent = document.getElementById(tabId);
    
    if (!tabButton || !tabContent) {
        console.error('Tab elements not found:', { tabId, tabButton, tabContent });
        return;
    }
    
    tabButton.classList.add('active');
    tabContent.classList.add('active');
    
    // Garantir que o container do gráfico existe
    if (tabId === 'performance') {
        const performanceTab = document.getElementById('performance');
        if (!performanceTab.querySelector('#performanceChart')) {
            performanceTab.innerHTML = `
                <div class="metrics-grid">
                    <div class="metric-card">
                        <h3>Avaliação Média</h3>
                        <p id="averageRating">0.0</p>
                    </div>
                    <div class="metric-card">
                        <h3>Total de Viagens</h3>
                        <p id="totalTrips">0</p>
                    </div>
                    <div class="metric-card">
                        <h3>Distância Total</h3>
                        <p id="totalDistance">0 km</p>
                    </div>
                    <div class="metric-card">
                        <h3>Consumo Médio</h3>
                        <p id="averageConsumption">0.0 L/100km</p>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="performanceChart"></canvas>
                </div>
            `;
        }
    }
    
    // Load tab-specific data
    console.log('Loading data for tab:', tabId);
    switch(tabId) {
        case 'routeHistory':
            initializeRouteHistoryTab();
            loadRouteHistory(currentMotoristId);
            break;
        case 'performance':
            loadPerformanceMetrics(currentMotoristId);
            break;
        case 'documents':
            loadDocuments(currentMotoristId);
            break;
    }
}

function loadMotorists(page = 1) {
    currentPage = page;
    const limit = 5;

    const searchInput = document.getElementById('searchMotorist');
    const statusFilter = document.getElementById('statusFilter');

    const params = new URLSearchParams();
    params.append('action', 'list');
    params.append('page', page);
    params.append('limit', limit);

    const searchTerm = searchInput ? searchInput.value.trim() : '';
    if (searchTerm) {
        params.append('search', searchTerm);
    }

    const statusValue = statusFilter ? statusFilter.value : '';
    if (statusValue) {
        params.append('status', statusValue);
    }

    fetch(`../api/motorist_data.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                showNotification(data.error || 'Erro ao carregar motoristas', 'error');
                return;
            }
            
            // Update statistics
            if (data.summary) {
                document.getElementById('totalMotorists').textContent = data.summary.total_motorists || 0;
                document.getElementById('activeMotorists').textContent = data.summary.motorists_ativos || 0;
                
                // Update Status KPI - show distribution with better formatting
                const statusItems = [];
                if (data.summary.motorists_ativos > 0) statusItems.push(`${data.summary.motorists_ativos} Ativos`);
                if (data.summary.motorists_ferias > 0) statusItems.push(`${data.summary.motorists_ferias} Férias`);
                if (data.summary.motorists_licenca > 0) statusItems.push(`${data.summary.motorists_licenca} Licença`);
                if (data.summary.motorists_inativos > 0) statusItems.push(`${data.summary.motorists_inativos} Inativos`);
                if (data.summary.motorists_afastados > 0) statusItems.push(`${data.summary.motorists_afastados} Afastados`);
                
                if (statusItems.length > 0) {
                    document.getElementById('totalTrips').textContent = statusItems.join(', ');
                } else {
                    document.getElementById('totalTrips').textContent = 'N/A';
                }
                
                // Update Commission KPI - show total paid this month
                if (data.summary.total_comissao_mes > 0 && data.summary.motorists_com_comissao_mes > 0) {
                    const totalComissao = parseFloat(data.summary.total_comissao_mes) || 0;
                    document.getElementById('averageRating').textContent = `R$ ${totalComissao.toFixed(2)}`;
                    
                    const subtitle = document.querySelector('#averageRating + .metric-subtitle');
                    if (subtitle) {
                        subtitle.textContent = `${data.summary.motorists_com_comissao_mes} motoristas`;
                    }
                } else {
                    document.getElementById('averageRating').textContent = 'R$ 0,00';
                    const subtitle = document.querySelector('#averageRating + .metric-subtitle');
                    if (subtitle) {
                        subtitle.textContent = 'Sem comissão no mês';
                    }
                }
                
                // Log para debug
                console.log('📊 KPIs atualizados:', data.summary);
            }
            
            // Update pagination
            if (data.pagination) {
                totalPages = data.pagination.total_pages;

                const currentPageElement = document.getElementById('currentPage');
                if (currentPageElement) {
                    currentPageElement.textContent = page;
                }

                const totalPagesElement = document.getElementById('totalPages');
                if (totalPagesElement) {
                    totalPagesElement.textContent = totalPages;
                }

                updatePaginationButtons();
            }
            
            // Update table
            const tbody = document.querySelector('#motoristsTable tbody');
            if (!tbody) {
                console.error('Tabela de motoristas não encontrada');
                return;
            }

            tbody.innerHTML = '';
            
            data.motorists.forEach(motorist => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${motorist.nome}</td>
                    <td>${motorist.cpf || '-'}</td>
                    <td>${motorist.cnh || '-'}</td>
                    <td>${motorist.categoria_cnh_nome || '-'}</td>
                    <td>${motorist.telefone || '-'}</td>
                    <td>${motorist.email || '-'}</td>
                    <td><span class="status-badge ${motorist.disponibilidade_nome?.toLowerCase()}">${motorist.disponibilidade_nome || 'N/A'}</span></td>
                    <td>${motorist.porcentagem_comissao ? motorist.porcentagem_comissao + '%' : '-'}</td>
                    <td class="actions">
                        <button class="btn-icon view-btn" data-id="${motorist.id}" title="Ver detalhes">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn-icon edit-btn" data-id="${motorist.id}" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon delete-btn" data-id="${motorist.id}" title="Excluir">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
            
            // Setup new buttons
            setupTableButtons();
        })
        .catch(error => {
            console.error('Error loading motorists:', error);
            showNotification('Erro ao carregar motoristas', 'error');
        });
}

function loadMotoristDetails(id) {
    console.log('Loading motorist details for ID:', id);
    
    if (!id) {
        console.error('Invalid motorist ID');
        return;
    }

    // Carregar dados básicos do motorista
    fetch(`../api/motorist_data.php?action=view&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const motorist = data.data;
                
                // Atualizar informações básicas
                document.getElementById('viewMotoristName').textContent = motorist.nome;
                document.getElementById('viewMotoristCPF').textContent = motorist.cpf;
                document.getElementById('viewMotoristCNH').textContent = motorist.cnh;
                document.getElementById('viewMotoristCNHCategory').textContent = motorist.categoria_cnh_nome || '-';
                document.getElementById('viewMotoristCNHExpiry').textContent = motorist.data_validade_cnh ? formatDate(motorist.data_validade_cnh) : '-';
                document.getElementById('viewMotoristPhone').textContent = motorist.telefone || '-';
                document.getElementById('viewMotoristEmergencyPhone').textContent = motorist.telefone_emergencia || '-';
                document.getElementById('viewMotoristEmail').textContent = motorist.email || '-';
                document.getElementById('viewMotoristAddress').textContent = motorist.endereco || '-';
                document.getElementById('viewMotoristHireDate').textContent = motorist.data_contratacao ? formatDate(motorist.data_contratacao) : '-';
                document.getElementById('viewMotoristContract').textContent = motorist.tipo_contrato_nome || '-';
                document.getElementById('viewMotoristAvailability').textContent = motorist.disponibilidade_nome || '-';
                document.getElementById('viewMotoristCommission').textContent = motorist.porcentagem_comissao ? `${motorist.porcentagem_comissao}%` : '-';
                document.getElementById('viewMotoristNotes').textContent = motorist.observacoes || '-';

                // Atualizar documentos
                if (motorist.cnh_arquivo) {
                    document.getElementById('cnhLink').href = `../uploads/motoristas/cnh/${motorist.cnh_arquivo.split('/').pop()}`;
                    document.getElementById('cnhLink').style.display = 'block';
                } else {
                    document.getElementById('cnhLink').style.display = 'none';
                }

                // Atualizar status e data da CNH
                const cnhStatus = document.querySelector('#cnhDocumentStatus .status-badge');
                const cnhExpiry = document.querySelector('#cnhExpiryDate span');
                if (motorist.data_validade_cnh) {
                    const expiryDate = new Date(motorist.data_validade_cnh);
                    const today = new Date();
                    const daysUntilExpiry = Math.ceil((expiryDate - today) / (1000 * 60 * 60 * 24));
                    
                    if (expiryDate < today) {
                        cnhStatus.textContent = 'Vencido';
                        cnhStatus.className = 'status-badge vencido';
                    } else if (daysUntilExpiry <= 30) {
                        cnhStatus.textContent = 'Próximo ao vencimento';
                        cnhStatus.className = 'status-badge proximo';
                    } else {
                        cnhStatus.textContent = 'Válido';
                        cnhStatus.className = 'status-badge valido';
                    }
                    cnhExpiry.textContent = formatDate(motorist.data_validade_cnh);
                } else {
                    cnhStatus.textContent = 'Não informado';
                    cnhStatus.className = 'status-badge';
                    cnhExpiry.textContent = '-';
                }

                if (motorist.contrato_arquivo) {
                    document.getElementById('contractLink').href = `../uploads/motoristas/contrato/${motorist.contrato_arquivo.split('/').pop()}`;
                    document.getElementById('contractLink').style.display = 'block';
                } else {
                    document.getElementById('contractLink').style.display = 'none';
                }

                // Atualizar status e data do contrato
                const contractStatus = document.querySelector('#contractDocumentStatus .status-badge');
                const contractDate = document.querySelector('#contractDate span');
                if (motorist.data_contratacao) {
                    contractStatus.textContent = 'Ativo';
                    contractStatus.className = 'status-badge ativo';
                    contractDate.textContent = formatDate(motorist.data_contratacao);
                } else {
                    contractStatus.textContent = 'Não informado';
                    contractStatus.className = 'status-badge';
                    contractDate.textContent = '-';
                }

                // Atualizar foto do motorista
                const fotoImg = document.getElementById('motoristPhoto');
                const noFotoMsg = document.getElementById('noPhotoMessage');

                if (motorist.foto_motorista) {
                    fotoImg.src = `../uploads/motoristas/foto/${motorist.foto_motorista.split('/').pop()}`;
                    fotoImg.style.display = 'block';
                    noFotoMsg.style.display = 'none';
                } else {
                    fotoImg.style.display = 'none';
                    noFotoMsg.style.display = 'block';
                }

                // Carregar e atualizar documentos (incluindo data do contrato)
                fetch(`../api/motorist_data.php?action=documents&id=${id}`)
                    .then(response => response.json())
                    .then(data => {
                        // Atualizar status e data do contrato
                        const contractStatus = document.querySelector('#contractDocumentStatus .status-badge');
                        const contractDate = document.querySelector('#contractDate span');
                        if (data.contract && data.contract.date) {
                            contractStatus.textContent = data.contract.status || 'Ativo';
                            contractStatus.className = 'status-badge ativo';
                            contractDate.textContent = formatDate(data.contract.date);
                        } else {
                            contractStatus.textContent = 'Não informado';
                            contractStatus.className = 'status-badge';
                            contractDate.textContent = '-';
                        }
                    });

                // Abrir o modal
                openModal('viewMotoristModal');
            } else {
                console.error('Error loading motorist data:', data.error);
                showNotification('Erro ao carregar dados do motorista', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading motorist details:', error);
            showNotification('Erro ao carregar detalhes do motorista', 'error');
        });

    // Carregar histórico de rotas
    fetch(`../api/motorist_data.php?action=routes&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const tbody = document.querySelector('#routeHistoryTable tbody');
                tbody.innerHTML = '';
                
                if (data.routes && data.routes.length > 0) {
                    data.routes.forEach(route => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${route.data}</td>
                            <td>${route.origem}</td>
                            <td>${route.destino}</td>
                            <td>${route.veiculo}</td>
                            <td>${route.km_percorrido}</td>
                            <td><span class="status-badge ${route.status?.toLowerCase()}">${route.status}</span></td>
                        `;
                        tbody.appendChild(row);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center">Nenhuma rota encontrada</td></tr>';
                }
            }
        })
        .then(data => {
            console.log('Route history data:', data);
        })
        .catch(error => {
            console.error('Error loading route history:', error);
            showNotification('Erro ao carregar histórico de rotas', 'error');
        });

    // Carregar métricas de desempenho
    fetch(`../api/motorist_performance_chart.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            console.log('Performance data received:', data);
            
            if (data.success) {
                const metrics = data.data;
                
                // Atualizar métricas
                document.getElementById('view-average-rating').textContent = metrics.average_rating.toFixed(1);
                document.getElementById('view-total-trips').textContent = metrics.total_trips;
                document.getElementById('view-total-distance').textContent = `${metrics.total_distance.toFixed(0)} km`;
                document.getElementById('view-average-consumption').textContent = `${metrics.average_consumption.toFixed(1)} L/100km`;
                
                // Atualizar gráfico
                updatePerformanceChart(metrics.monthly_metrics);
            } else {
                console.error('Error in performance data:', data.error);
                showNotification('Erro ao carregar métricas de desempenho', 'error');
                
                // Definir valores padrão
                document.getElementById('view-average-rating').textContent = '0.0';
                document.getElementById('view-total-trips').textContent = '0';
                document.getElementById('view-total-distance').textContent = '0 km';
                document.getElementById('view-average-consumption').textContent = '0.0 L/100km';
            }
        })
        .catch(error => {
            console.error('Error loading performance metrics:', error);
            showNotification('Erro ao carregar métricas de desempenho', 'error');
            
            // Definir valores padrão em caso de erro
            document.getElementById('view-average-rating').textContent = '0.0';
            document.getElementById('view-total-trips').textContent = '0';
            document.getElementById('view-total-distance').textContent = '0 km';
            document.getElementById('view-average-consumption').textContent = '0.0 L/100km';
        });
}

function loadRouteHistory(motoristId) {
    fetch(`../api/motorist_data.php?action=routes&id=${motoristId}`)
        .then(response => response.json())
        .then(data => {
            const tbody = document.querySelector('#routeHistoryTable tbody');
            const noDataMessage = document.getElementById('noRouteHistoryMessage');
            
            if (!tbody || !noDataMessage) {
                console.error('Route history elements not found');
                return;
            }
            
            tbody.innerHTML = '';
            
            if (data.success && data.routes && data.routes.length > 0) {
                data.routes.forEach(route => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${formatDate(route.data)}</td>
                        <td>${route.origem || '-'}</td>
                        <td>${route.destino || '-'}</td>
                        <td>${route.veiculo || '-'}</td>
                        <td>${formatKm(route.km_percorrido)}</td>
                        <td><span class="status-badge ${route.status?.toLowerCase() || ''}">${route.status || 'N/A'}</span></td>
                    `;
                    tbody.appendChild(row);
                });
                noDataMessage.style.display = 'none';
                tbody.closest('.route-history-container').style.display = 'block';
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center">Nenhuma rota encontrada</td></tr>';
                noDataMessage.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error loading route history:', error);
            showNotification('Erro ao carregar histórico de rotas', 'error');
        });
}

function destroyChart() {
    if (performanceChart) {
        try {
            performanceChart.destroy();
        } catch (error) {
            console.warn('Error destroying chart:', error);
        } finally {
            performanceChart = null;
        }
    }
}

function updatePerformanceChart(monthlyData) {
    console.log('Updating performance chart with data:', monthlyData);
    
    // Primeiro, destruir o gráfico anterior
    destroyChart();

    // Garantir que o container do gráfico existe
    const chartContainer = document.getElementById('performanceChart')?.parentElement;
    if (!chartContainer) {
        console.error('Chart container not found');
        return;
    }

    // Limpar o container e criar um novo canvas
    chartContainer.innerHTML = '<canvas id="performanceChart"></canvas>';
    
    // Pequeno delay para garantir que o canvas esteja pronto
    setTimeout(() => {
        const ctx = document.getElementById('performanceChart');
        if (!ctx) {
            console.error('Canvas element not found after creation');
            return;
        }

        try {
            // Preparar dados para o gráfico
            const labels = monthlyData.map(item => {
                const [year, month] = item.month.split('-');
                return `${month}/${year}`;
            }).reverse();

            const tripsData = monthlyData.map(item => item.trips).reverse();
            const ratingData = monthlyData.map(item => (item.rating * 10)).reverse();

            // Configuração do gráfico
            const config = {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Viagens',
                            data: tripsData,
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Avaliação (x10)',
                            data: ratingData,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            };

            // Criar novo gráfico
            performanceChart = new Chart(ctx, config);
            console.log('Performance chart updated successfully');

        } catch (error) {
            console.error('Error creating performance chart:', error);
            chartContainer.innerHTML = '<div class="error">Erro ao criar gráfico de desempenho</div>';
        }
    }, 100);
}

function loadPerformanceMetrics(motoristId) {
    console.log('Loading performance metrics for motorist ID:', motoristId);
    
    if (!motoristId) {
        console.error('Motorist ID is required');
        showNotification('ID do motorista é obrigatório', 'error');
        return;
    }

    // Primeiro, destruir qualquer gráfico existente
    destroyChart();

    // Garantir que o container do gráfico existe
    const chartContainer = document.getElementById('performanceChart')?.parentElement;
    if (!chartContainer) {
        console.error('Chart container not found, creating it...');
        const performanceTab = document.getElementById('performance');
        if (!performanceTab) {
            console.error('Performance tab not found');
            return;
        }
        performanceTab.innerHTML = `
            <div class="metrics-grid">
                <div class="metric-card">
                    <h3>Avaliação Média</h3>
                    <p id="averageRating">0.0</p>
                </div>
                <div class="metric-card">
                    <h3>Total de Viagens</h3>
                    <p id="totalTrips">0</p>
                </div>
                <div class="metric-card">
                    <h3>Distância Total</h3>
                    <p id="totalDistance">0 km</p>
                </div>
                <div class="metric-card">
                    <h3>Consumo Médio</h3>
                    <p id="averageConsumption">0.0 L/100km</p>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="performanceChart"></canvas>
            </div>
        `;
    }

    // Mostrar indicador de carregamento
    const newChartContainer = document.getElementById('performanceChart')?.parentElement;
    if (!newChartContainer) {
        console.error('Failed to create chart container');
        return;
    }
    
    newChartContainer.innerHTML = '<div class="loading">Carregando dados...</div>';

    // Atualizar os elementos de métricas antes de fazer a requisição
    const updateMetricElements = (metrics) => {
        console.log('Updating metric elements with data:', metrics);
        
        const averageRating = document.getElementById('averageRating');
        const totalTrips = document.getElementById('totalTrips');
        const totalDistance = document.getElementById('totalDistance');
        const averageConsumption = document.getElementById('averageConsumption');

        if (averageRating) {
            averageRating.textContent = metrics.average_rating?.toFixed(1) || '0.0';
            console.log('Updated average rating:', averageRating.textContent);
        }

        if (totalTrips) {
            totalTrips.textContent = metrics.total_trips || '0';
            console.log('Updated total trips:', totalTrips.textContent);
        }

        if (totalDistance) {
            totalDistance.textContent = formatKm(metrics.total_distance);
            console.log('Updated total distance:', totalDistance.textContent);
        }

        if (averageConsumption) {
            averageConsumption.textContent = `${metrics.average_consumption?.toFixed(1) || '0.0'} L/100km`;
            console.log('Updated average consumption:', averageConsumption.textContent);
        }
    };

    fetch(`../api/motorist_performance_chart.php?id=${motoristId}`)
        .then(response => {
            console.log('Performance API response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Performance metrics response:', data);
            
            if (!data.success) {
                throw new Error(data.error || 'Erro ao carregar métricas');
            }
            
            const metrics = data.data;
            console.log('Performance metrics data:', metrics);
            
            // Update metrics
            updateMetricElements(metrics);
            
            // Restaurar o canvas
            newChartContainer.innerHTML = '<canvas id="performanceChart"></canvas>';
            
            // Update chart if exists and has data
            if (metrics.monthly_metrics && metrics.monthly_metrics.length > 0) {
                console.log('Updating performance chart with data:', metrics.monthly_metrics);
                // Pequeno delay para garantir que o canvas esteja pronto
                setTimeout(() => {
                    updatePerformanceChart(metrics.monthly_metrics);
                }, 100);
            } else {
                console.log('No monthly metrics data available');
                newChartContainer.innerHTML = '<div class="no-data">Nenhum dado disponível</div>';
            }
        })
        .catch(error => {
            console.error('Error loading performance metrics:', error);
            showNotification('Erro ao carregar métricas de desempenho', 'error');
            newChartContainer.innerHTML = '<div class="error">Erro ao carregar dados</div>';
            
            // Set default values
            updateMetricElements({
                average_rating: 0,
                total_trips: 0,
                total_distance: 0,
                average_consumption: 0
            });
        });
}

function loadDocuments(motoristId) {
    fetch(`../api/motorist_data.php?action=documents&id=${motoristId}`)
        .then(response => response.json())
        .then(data => {
            // Update CNH status
            const cnhStatus = document.querySelector('#cnhDocumentStatus .status-badge');
            const cnhExpiry = document.querySelector('#cnhExpiryDate span');
            
            if (data.cnh) {
                cnhStatus.textContent = data.cnh.status;
                cnhStatus.className = `status-badge ${data.cnh.status.toLowerCase()}`;
                cnhExpiry.textContent = formatDate(data.cnh.expiry_date);
            }
            
            // Update contract status
            const contractStatus = document.querySelector('#contractDocumentStatus .status-badge');
            const contractDate = document.querySelector('#contractDate span');
            
            if (data.contract) {
                contractStatus.textContent = data.contract.status;
                contractStatus.className = `status-badge ${data.contract.status.toLowerCase()}`;
                contractDate.textContent = formatDate(data.contract.date);
            }
        })
        .catch(error => {
            console.error('Error loading documents:', error);
            showNotification('Erro ao carregar documentos', 'error');
        });
}

function setupPagination() {
    const prevBtn = document.getElementById('prevPageBtn');
    const nextBtn = document.getElementById('nextPageBtn');
    
    if (prevBtn) {
        prevBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (currentPage > 1) {
                loadMotorists(currentPage - 1);
            }
        });
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (currentPage < totalPages) {
                loadMotorists(currentPage + 1);
            }
        });
    }
}

function updatePaginationButtons() {
    const prevBtn = document.getElementById('prevPageBtn');
    const nextBtn = document.getElementById('nextPageBtn');
    
    if (prevBtn) {
        prevBtn.classList.toggle('disabled', currentPage <= 1);
    }
    
    if (nextBtn) {
        nextBtn.classList.toggle('disabled', currentPage >= totalPages);
    }
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR');
}

function formatKm(km) {
    if (!km) return '0 km';
    return `${Number(km).toLocaleString('pt-BR')} km`;
}

function closeAllModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.classList.remove('active');
    });
}

function showNotification(message, type = 'info') {
    console.log(`${type.toUpperCase()}: ${message}`);
    // You can implement a more visual notification system here
    alert(message);
}

function setupTableButtons() {
    console.log('Setting up table buttons...');
    
    // View buttons
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const motoristId = btn.getAttribute('data-id');
            console.log('View button clicked for ID:', motoristId);
            if (!motoristId) {
                console.error('No motorist ID found in button data');
                showNotification('Erro: ID do motorista não encontrado', 'error');
                return;
            }
            loadMotoristDetails(motoristId);
        });
    });
    
    // Edit buttons
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const motoristId = btn.getAttribute('data-id');
            console.log('Edit button clicked for ID:', motoristId);
            if (!motoristId) {
                console.error('No motorist ID found in button data');
                showNotification('Erro: ID do motorista não encontrado', 'error');
                return;
            }
            loadMotoristForEdit(motoristId);
        });
    });
    
    // Delete buttons
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const motoristId = btn.getAttribute('data-id');
            console.log('Delete button clicked for ID:', motoristId);
            if (!motoristId) {
                console.error('No motorist ID found in button data');
                showNotification('Erro: ID do motorista não encontrado', 'error');
                return;
            }
            showDeleteConfirmation(motoristId);
        });
    });
}

function loadMotoristForEdit(id) {
    console.log('Loading motorist for edit:', id);
    
    // Load dropdown options first
    loadDropdownOptions().then(() => {
        // After dropdowns are loaded, fetch motorist data
        fetch(`../api/motorist_data.php?action=view&id=${id}`)
            .then(response => {
                console.log('Edit load response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Edit load data:', data);
                if (!data.success) {
                    showNotification(data.error || 'Erro ao carregar dados do motorista', 'error');
                    return;
                }
                
                const motorist = data.data;
                
                // Update modal title
                document.getElementById('modalTitle').textContent = 'Editar Motorista';
                
                // Fill form fields
                document.getElementById('motoristId').value = motorist.id;
                document.getElementById('nome').value = motorist.nome || '';
                document.getElementById('cpf').value = motorist.cpf || '';
                document.getElementById('cnh').value = motorist.cnh || '';
                document.getElementById('categoria_cnh_id').value = motorist.categoria_cnh_id || '';
                document.getElementById('data_validade_cnh').value = motorist.data_validade_cnh ? motorist.data_validade_cnh.split(' ')[0] : '';
                document.getElementById('telefone').value = motorist.telefone || '';
                document.getElementById('telefone_emergencia').value = motorist.telefone_emergencia || '';
                document.getElementById('email').value = motorist.email || '';
                document.getElementById('endereco').value = motorist.endereco || '';
                document.getElementById('data_contratacao').value = motorist.data_contratacao ? motorist.data_contratacao.split(' ')[0] : '';
                document.getElementById('tipo_contrato_id').value = motorist.tipo_contrato_id || '';
                document.getElementById('disponibilidade_id').value = motorist.disponibilidade_id || '';
                document.getElementById('porcentagem_comissao').value = motorist.porcentagem_comissao || '';
                document.getElementById('observacoes').value = motorist.observacoes || '';
                
                // Limpar os campos de arquivo
                document.getElementById('foto_motorista').value = '';
                document.getElementById('cnh_arquivo').value = '';
                document.getElementById('contrato_arquivo').value = '';
                
                // Show modal
                openModal('motoristModal');
            })
            .catch(error => {
                console.error('Error loading motorist for edit:', error);
                showNotification('Erro ao carregar dados do motorista', 'error');
            });
    });
}

function loadDropdownOptions() {
    console.log('Loading dropdown options...');
    
    // Return a promise that resolves when all dropdowns are loaded
    return Promise.all([
        // Load contract types
        fetch('../api/motorist_data.php?action=get_contract_types')
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('tipo_contrato_id');
                if (!select) {
                    console.error('Contract type select element not found');
                    return;
                }
                
                if (data.success && data.types) {
                    select.innerHTML = '<option value="">Selecione...</option>';
                    data.types.forEach(type => {
                        select.innerHTML += `<option value="${type.id}">${type.nome}</option>`;
                    });
                    console.log('Contract types loaded:', data.types.length);
                } else {
                    console.error('Error loading contract types:', data.error);
                }
            }),
        
        // Load availability status
        fetch('../api/motorist_data.php?action=get_availability_status')
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('disponibilidade_id');
                if (!select) {
                    console.error('Availability select element not found');
                    return;
                }
                
                if (data.success && data.status) {
                    select.innerHTML = '<option value="">Selecione...</option>';
                    data.status.forEach(status => {
                        select.innerHTML += `<option value="${status.id}">${status.nome}</option>`;
                    });
                    console.log('Availability options loaded:', data.status.length);
                } else {
                    console.error('Error loading availability status:', data.error);
                }
            }),
        
        // Load CNH categories
        fetch('../api/motorist_data.php?action=get_cnh_categories')
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('categoria_cnh_id');
                if (!select) {
                    console.error('CNH category select element not found');
                    return;
                }
                
                if (data.success && data.categories) {
                    select.innerHTML = '<option value="">Selecione...</option>';
                    data.categories.forEach(category => {
                        select.innerHTML += `<option value="${category.id}">${category.nome}</option>`;
                    });
                    console.log('CNH categories loaded:', data.categories.length);
                } else {
                    console.error('Error loading CNH categories:', data.error);
                }
            })
    ]).catch(error => {
        console.error('Error loading dropdown options:', error);
        showNotification('Erro ao carregar opções dos campos', 'error');
    });
}

function setupFilters() {
    const searchInput = document.getElementById('searchMotorist');
    const statusFilter = document.getElementById('statusFilter');
    const applyFiltersBtn = document.getElementById('applyMotoristFilters');
    const clearFiltersBtn = document.getElementById('clearMotoristFilters');

    let debounceTimer = null;
    const debounceDelay = 300;

    const triggerFilter = () => {
        loadMotorists(1);
    };

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }
            debounceTimer = setTimeout(triggerFilter, debounceDelay);
        });

        searchInput.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                if (debounceTimer) {
                    clearTimeout(debounceTimer);
                }
                triggerFilter();
            }
        });
    }

    if (statusFilter) {
        statusFilter.addEventListener('change', () => {
            triggerFilter();
        });
    }

    if (applyFiltersBtn) {
        applyFiltersBtn.addEventListener('click', event => {
            event.preventDefault();
            triggerFilter();
        });
    }

    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', event => {
            event.preventDefault();
            if (searchInput) {
                searchInput.value = '';
            }
            if (statusFilter) {
                statusFilter.value = '';
            }
            triggerFilter();
        });
    }
}

function loadDashboardData() {
    // Implementation of loadDashboardData function
}

function initializeRouteHistoryTab() {
    const routeHistoryTab = document.getElementById('routeHistory');
    if (!routeHistoryTab) {
        console.error('Route history tab not found');
        return;
    }
    
    // Make sure the table exists
    if (!routeHistoryTab.querySelector('#routeHistoryTable')) {
        const tableHTML = `
            <div class="route-history-container">
                <table class="data-table" id="routeHistoryTable">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Origem</th>
                            <th>Destino</th>
                            <th>Veículo</th>
                            <th>Km Percorrido</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <div class="no-data-message" id="noRouteHistoryMessage" style="display: none;">
                    Nenhuma rota encontrada para este motorista.
                </div>
            </div>
        `;
        routeHistoryTab.innerHTML = tableHTML;
    }
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
    } else {
        console.error('Modal not found:', modalId);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
        }
        // Destruir o gráfico quando o modal for fechado
        if (modalId === 'viewMotoristModal') {
            destroyChart();
        }
    } else {
        console.error('Modal not found:', modalId);
    }
}

function setupFormSubmission() {
    const saveBtn = document.getElementById('saveMotoristBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Save button clicked');
            saveMotorist();
        });
    } else {
        console.error('Save button not found');
    }
}

function saveMotorist() {
    console.log('Saving motorist...');
    const form = document.getElementById('motoristForm');
    const formData = new FormData(form);
    const motoristId = document.getElementById('motoristId').value;
    
    // Debug: Log form data
    console.log('Form data values:');
    for (let pair of formData.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
    }
    
    // Validate tipo_contrato_id
    const tipoContratoId = formData.get('tipo_contrato_id');
    if (!tipoContratoId) {
        showNotification('Por favor, selecione um tipo de contrato', 'error');
        return;
    }
    
    // Add empresa_id from session if needed
    if (!formData.get('empresa_id')) {
        formData.append('empresa_id', document.getElementById('empresaId').value);
    }
    
    const action = motoristId ? 'update' : 'add';
    console.log('Action:', action, 'ID:', motoristId);
    
    fetch(`../api/motorist_data.php?action=${action}${motoristId ? '&id=' + motoristId : ''}`, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Save response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Save response data:', data);
        if (!data.success) {
            showNotification(data.error || 'Erro ao salvar motorista', 'error');
            return;
        }
        
        // Close modal and reload data
        closeModal('motoristModal');
        loadMotorists(currentPage);
        showNotification(motoristId ? 'Motorista atualizado com sucesso' : 'Motorista adicionado com sucesso', 'success');
    })
    .catch(error => {
        console.error('Error saving motorist:', error);
        showNotification('Erro ao salvar motorista', 'error');
    });
}

function showDeleteConfirmation(motoristId) {
    if (confirm('Tem certeza que deseja excluir este motorista?')) {
        console.log('Attempting to delete motorist:', motoristId);
        
        fetch(`../api/motorist_data.php?action=delete&id=${motoristId}`, {
            method: 'POST'
        })
        .then(response => {
            console.log('Delete response status:', response.status);
            return response.json().catch(error => {
                console.error('Error parsing JSON:', error);
                throw new Error('Failed to parse server response');
            });
        })
        .then(data => {
            console.log('Delete response data:', data);
            if (!data.success) {
                showNotification(data.error || 'Erro ao excluir motorista', 'error');
                return;
            }
            
            showNotification(data.message || 'Motorista excluído com sucesso', 'success');
            loadMotorists(currentPage);
        })
        .catch(error => {
            console.error('Error in delete operation:', error);
            showNotification('Erro ao excluir motorista: ' + error.message, 'error');
        });
    }
}

function showAddMotoristModal() {
    const modal = document.getElementById('motoristModal');
    document.getElementById('modalTitle').textContent = 'Adicionar Motorista';
    document.getElementById('motoristForm').reset();
    document.getElementById('motoristId').value = '';
    
    // Limpar os campos de arquivo
    document.getElementById('foto_motorista').value = '';
    document.getElementById('cnh_arquivo').value = '';
    document.getElementById('contrato_arquivo').value = '';
    
    // Load dropdown options and then show modal
    loadDropdownOptions().then(() => {
        modal.classList.add('active');
    }).catch(error => {
        console.error('Error preparing add motorist modal:', error);
        showNotification('Erro ao preparar formulário', 'error');
    });
}

function initializeCharts() {
    // Initialize any charts if needed
    console.log('Charts initialized');
}

// Função para configurar botão de ajuda
function setupHelpButton() {
    const helpBtn = document.getElementById('helpBtn');
    if (helpBtn) {
        helpBtn.addEventListener('click', function() {
            const helpModal = document.getElementById('helpMotoristsModal');
            if (helpModal) {
                helpModal.classList.add('active');
            }
        });
    }
}

// Função específica para fechar modal de ajuda
function closeHelpModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
} 