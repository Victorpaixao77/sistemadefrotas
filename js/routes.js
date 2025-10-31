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
    
    // Limpa o filtro de mês ao carregar a página
    const filterMonth = document.getElementById('filterMonth');
    if (filterMonth) {
        filterMonth.value = '';
    }
    
    // Carrega os dados iniciais do mês atual
    const currentDate = new Date();
    loadDashboardData(currentDate.getMonth() + 1, currentDate.getFullYear());
});

let currentPage = 1;
let totalPages = 1;

function initializePage() {
    // Get current page from URL or use default
    const urlParams = new URLSearchParams(window.location.search);
    const page = parseInt(urlParams.get('page')) || 1;
    
    // Load route data from API
    loadRouteData(page);
    
    // Setup button events
    document.getElementById('addRouteBtn').addEventListener('click', showAddRouteModal);
    
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
}

function handleSearch(event) {
    // Recarrega os dados com o termo de busca
    loadRouteData(currentPage);
}

function handleFilters() {
    // Recarrega os dados com os filtros atualizados
    loadRouteData(currentPage);
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
    
    const searchTerm = document.getElementById('searchRoute')?.value || '';
    const status = document.getElementById('statusFilter')?.value || '';
    const driver = document.getElementById('driverFilter')?.value || '';
    const date = document.getElementById('dateFilter')?.value || '';
    
    let url = `../api/route_data.php?action=list&page=${page}&limit=5`;
    if (searchTerm) url += `&search=${encodeURIComponent(searchTerm)}`;
    if (status) url += `&status=${encodeURIComponent(status)}`;
    if (driver) url += `&driver=${encodeURIComponent(driver)}`;
    if (date) url += `&date=${encodeURIComponent(date)}`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateRouteTable(data.data);
                if (data.pagination) {
                    totalPages = data.pagination.total_pages;
                    updatePaginationButtons();
                }
            } else {
                throw new Error(data.error || 'Erro ao carregar dados das rotas');
            }
        })
        .catch(error => {
            console.error('Erro ao carregar dados das rotas:', error);
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
                    <button class="btn-icon view-btn" data-id="${route.id}" title="Ver detalhes">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-icon edit-btn" data-id="${route.id}" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-icon expenses-btn" data-id="${route.id}" title="Despesas de Viagem">
                        <i class="fas fa-money-bill"></i>
                    </button>
                    <button class="btn-icon delete-btn" data-id="${route.id}" title="Excluir">
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
    document.querySelectorAll('.pagination-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            if (this.classList.contains('disabled')) return;
            
            const direction = this.getAttribute('data-direction');
            const newPage = direction === 'prev' ? currentPage - 1 : currentPage + 1;
            
            if (newPage >= 1 && newPage <= totalPages) {
                loadRouteData(newPage);
                updateURLParameter('page', newPage);
            }
        });
    });
}

function updatePaginationButtons() {
    const paginationDiv = document.querySelector('.pagination');
    if (!paginationDiv) return;
    
    paginationDiv.innerHTML = `
        <a href="#" class="pagination-btn ${currentPage <= 1 ? 'disabled' : ''}" 
           data-direction="prev">
            <i class="fas fa-chevron-left"></i>
        </a>
        
        <span class="pagination-info">
            Página ${currentPage} de ${totalPages}
        </span>
        
        <a href="#" class="pagination-btn ${currentPage >= totalPages ? 'disabled' : ''}"
           data-direction="next">
            <i class="fas fa-chevron-right"></i>
        </a>
    `;
    
    setupPagination();
}

function updateURLParameter(param, value) {
    const url = new URL(window.location.href);
    url.searchParams.set(param, value);
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
            alert('Erro ao carregar detalhes da rota: ' + error.message);
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
            alert('Erro ao carregar detalhes da rota: ' + error.message);
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
                alert('Rota excluída com sucesso!');
            } else {
                throw new Error(data.error || 'Erro ao excluir rota');
            }
        })
        .catch(error => {
            console.error('Erro ao excluir rota:', error);
            alert('Erro ao excluir rota: ' + error.message);
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
                alert(routeId ? 'Rota atualizada com sucesso!' : 'Rota adicionada com sucesso!');
            } else {
                throw new Error(result.error || 'Erro ao salvar rota');
            }
        })
        .catch(error => {
            console.error('Erro ao salvar rota:', error);
            alert('Erro ao salvar rota: ' + error.message);
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
            alert('Erro ao carregar detalhes da rota: ' + error.message);
        });
}

function fillRouteDetails(data) {
    // Preenche os detalhes da rota no modal de visualização
    document.getElementById('routeOriginDestination').textContent = 
        `${data.cidade_origem_nome || '-'}, ${data.estado_origem || '-'} → ${data.cidade_destino_nome || '-'}, ${data.estado_destino || '-'}`;
    
    // Status da rota baseado no no_prazo
    const statusText = data.no_prazo === '1' ? 'No Prazo' : data.no_prazo === '0' ? 'Atrasado' : '-';
    document.getElementById('routeStatus').textContent = statusText;
    document.getElementById('routeDate').textContent = data.data_rota ? formatDate(data.data_rota) : '-';
    
    // Informações gerais
    document.getElementById('detailDriver').textContent = data.motorista_nome || '-';
    document.getElementById('detailVehicle').textContent = data.veiculo_placa && data.veiculo_modelo ? 
        `${data.veiculo_placa} (${data.veiculo_modelo})` : data.veiculo_placa || '-';
    document.getElementById('detailDistance').textContent = data.distancia_km ? 
        `${parseFloat(data.distancia_km).toFixed(2)} km` : '-';
    
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
    document.getElementById('detailStartTime').textContent = data.data_saida ? 
        formatDateTime(data.data_saida) : '-';
    document.getElementById('detailEndTime').textContent = data.data_chegada ? 
        formatDateTime(data.data_chegada) : '-';
    
    // Calcula duração se houver data de início e fim
    if (data.data_saida && data.data_chegada) {
        const inicio = new Date(data.data_saida);
        const fim = new Date(data.data_chegada);
        const duracao = Math.abs(fim - inicio);
        const horas = Math.floor(duracao / (1000 * 60 * 60));
        const minutos = Math.floor((duracao % (1000 * 60 * 60)) / (1000 * 60));
        document.getElementById('detailDuration').textContent = `${horas}h ${minutos}min`;
    } else {
        document.getElementById('detailDuration').textContent = '-';
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
    
    document.getElementById('detailOriginAddress').textContent = enderecoOrigem;
    document.getElementById('detailDestinationAddress').textContent = enderecoDestino;
    
    // Informações da Carga
    document.getElementById('detailCargoDescription').textContent = data.descricao_carga || '-';
    document.getElementById('detailCargoWeight').textContent = data.peso_carga ? 
        `${parseFloat(data.peso_carga).toFixed(2)} kg` : '-';
    document.getElementById('detailCustomer').textContent = data.cliente || '-';
    document.getElementById('detailCustomerContact').textContent = data.cliente_contato || '-';
    
    // Informações Financeiras (cards antigos removidos - agora usa Análise de Lucratividade)
    
    // Eficiência
    if (document.getElementById('detailEfficiency')) {
        document.getElementById('detailEfficiency').textContent = data.eficiencia_viagem ? 
            `${parseFloat(data.eficiencia_viagem).toFixed(2)}%` : '-';
    }
    
    // Observações
    document.getElementById('detailNotes').textContent = data.observacoes || 'Nenhuma observação registrada';
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
            alert('Erro ao carregar despesas: ' + error.message);
        });
}

function fillExpensesForm(expenses) {
    // Preenche cada campo com o valor existente ou deixa vazio
    const fields = [
        'arla', 'pedagios', 'caixinha', 'estacionamento',
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
        'arla', 'pedagios', 'caixinha', 'estacionamento',
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
        'arla', 'pedagios', 'caixinha', 'estacionamento',
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
                alert('Despesas salvas com sucesso!');
            } else {
                throw new Error(result.error || 'Erro ao salvar despesas');
            }
        })
        .catch(error => {
            console.error('Erro ao salvar despesas:', error);
            alert('Erro ao salvar despesas: ' + error.message);
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
                    alert('Despesas excluídas com sucesso!');
                } else {
                    throw new Error(result.error || 'Erro ao excluir despesas');
                }
            })
            .catch(error => {
                alert('Erro ao excluir despesas: ' + error.message);
            });
        });
    }
}

// Função para limpar o filtro
function clearFilter() {
    const filterMonth = document.getElementById('filterMonth');
    if (filterMonth) {
        filterMonth.value = '';
        const currentDate = new Date();
        loadDashboardData(currentDate.getMonth() + 1, currentDate.getFullYear());
        closeModal('filterModal');
    }
}

// Função para aplicar o filtro
function applyFilter() {
    const filterMonth = document.getElementById('filterMonth');
    if (filterMonth && filterMonth.value) {
        const [year, month] = filterMonth.value.split('-');
        loadDashboardData(parseInt(month), parseInt(year));
        closeModal('filterModal');
    }
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
            alert('Erro ao carregar dados do dashboard: ' + error.message);
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