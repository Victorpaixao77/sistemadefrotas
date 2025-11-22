// Vehicles management JavaScript

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
    document.getElementById('saveVehicleBtn').addEventListener('click', saveVehicle);
    
    // Setup pagination
    setupPagination();
    
    // Setup help and export buttons
    setupHelpAndExportButtons();
});

let currentPage = 1;
let totalPages = 1;

function initializePage() {
    // Get current page from URL or use default
    const urlParams = new URLSearchParams(window.location.search);
    const page = parseInt(urlParams.get('page')) || 1;
    
    // Load vehicle data from API
    loadVehicleData(page);
    
    // Setup button events
    document.getElementById('addVehicleBtn').addEventListener('click', showAddVehicleModal);
    
    // Setup table buttons
    setupTableButtons();
    
    // Load select options
    loadSelectOptions();
}

function setupModals() {
    // Close modal when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this);
            }
        });
    });
    
    // Close modal when clicking X button (all close buttons)
    document.querySelectorAll('.close-modal').forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            closeModal(modal);
        });
    });
    
    // Close modal when clicking cancel buttons
    document.getElementById('cancelVehicleBtn')?.addEventListener('click', function() {
        document.getElementById('vehicleModal').style.display = 'none';
    });
    
    document.getElementById('closeVehicleDetailsBtn')?.addEventListener('click', function() {
        closeModal(document.getElementById('viewVehicleModal'));
    });
    
    document.getElementById('cancelDeleteBtn')?.addEventListener('click', function() {
        closeModal(document.getElementById('deleteVehicleModal'));
    });
    
    // Setup tab switching in vehicle details modal
    document.querySelectorAll('.tab-btn').forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Remove active class from all tabs and contents
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Add active class to clicked tab and its content
            this.classList.add('active');
            document.getElementById(tabId).classList.add('active');
            
            // Não há mais abas - tudo é mostrado em cards simples
        });
    });
}

function closeModal(modal) {
    if (!modal) return;
    
    // Hide the modal
    modal.style.display = 'none';
    
    // If it's the vehicle form modal, reset the form
    if (modal.id === 'vehicleModal') {
        document.getElementById('vehicleForm').reset();
        document.getElementById('vehicleId').value = '';
    }
}

function closeAllModals() {
    // Hide all modals
    document.querySelectorAll('.modal').forEach(modal => {
        closeModal(modal);
    });
}

function setupFilters() {
    const searchInput = document.getElementById('searchVehicle');
    const statusFilter = document.getElementById('statusFilter');
    const typeFilter = document.getElementById('typeFilter');
    const applyButton = document.getElementById('applyVehicleFilters');
    const clearButton = document.getElementById('clearVehicleFilters');

    function filterVehicles() {
        const rows = document.querySelectorAll('#vehiclesTable tbody tr');
        const searchTerm = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const selectedStatus = statusFilter ? statusFilter.value.toLowerCase() : '';
        const selectedType = typeFilter ? typeFilter.value.toLowerCase() : '';

        rows.forEach(row => {
            const rowText = row.textContent.toLowerCase();
            const statusCell = row.querySelector('td:nth-child(5)');
            const modelCell = row.querySelector('td:nth-child(2)');
            const brandCell = row.querySelector('td:nth-child(3)');

            const matchesSearch = !searchTerm || rowText.includes(searchTerm);
            const matchesStatus = !selectedStatus || (statusCell && statusCell.textContent.toLowerCase().includes(selectedStatus));
            const matchesType = !selectedType || (
                (modelCell && modelCell.textContent.toLowerCase().includes(selectedType)) ||
                (brandCell && brandCell.textContent.toLowerCase().includes(selectedType))
            );

            row.style.display = matchesSearch && matchesStatus && matchesType ? '' : 'none';
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterVehicles);
    }

    if (statusFilter) {
        statusFilter.addEventListener('change', filterVehicles);
    }

    if (typeFilter) {
        typeFilter.addEventListener('change', filterVehicles);
    }

    if (applyButton) {
        applyButton.addEventListener('click', filterVehicles);
    }

    if (clearButton) {
        clearButton.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (statusFilter) statusFilter.value = '';
            if (typeFilter) typeFilter.value = '';
            filterVehicles();
        });
    }

    // Aplicar filtros inicialmente para garantir consistência
    filterVehicles();
}

function loadSelectOptions() {
    // Load tipos de combustível
    fetch('../api/combustiveis.php', {
        credentials: 'include'
    })
        .then(response => {
            if (!response.ok) {
                if (response.status === 401) {
                    window.location.href = '../login.php';
                    return;
                }
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            const select = document.getElementById('tipo_combustivel_id');
            if (select) {
                select.innerHTML = '<option value="">Selecione o combustível</option>';
                if (data.success && data.data) {
                    data.data.forEach(combustivel => {
                        const option = document.createElement('option');
                        option.value = combustivel.id;
                        option.textContent = combustivel.nome;
                        select.appendChild(option);
                    });
                }
            }
        })
        .catch(error => {
            console.error('Error loading tipos de combustível:', error);
            alert('Erro ao carregar tipos de combustível. Por favor, recarregue a página.');
        });
    
    // Load carrocerias
    fetch('../api/carrocerias.php', {
        credentials: 'include'
    })
        .then(response => {
            if (!response.ok) {
                if (response.status === 401) {
                    window.location.href = '../login.php';
                    return;
                }
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Error loading carrocerias');
            }
            const select = document.getElementById('carroceria_id');
            if (select) {
                select.innerHTML = '<option value="">Selecione a carroceria</option>';
                data.data.forEach(carroceria => {
                    const option = document.createElement('option');
                    option.value = carroceria.id;
                    option.textContent = carroceria.nome;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading carrocerias:', error);
            alert('Erro ao carregar tipos de carroceria. Por favor, recarregue a página.');
        });
}

function loadVehicleData(page = 1) {
    currentPage = page;
    const limit = 5; // Define o limite de registros por página
    
    // Carregar dados dos veículos da API
    fetch(`../api/vehicle_data.php?action=list&page=${page}&limit=${limit}`)
        .then(response => response.json())
        .then(data => {
            // Atualizar estatísticas dos veículos
            if (data.summary) {
                document.getElementById('totalVehicles').textContent = data.summary.totalVehicles || 0;
                document.getElementById('activeVehicles').textContent = data.summary.statusDistribution?.Ativo || 0;
                document.getElementById('maintenanceVehicles').textContent = data.summary.statusDistribution?.Manutencao || 0;
            }
            
            // Atualizar paginação
            if (data.pagination) {
                document.getElementById('currentPage').textContent = data.pagination.page;
                document.getElementById('totalPages').textContent = data.pagination.totalPages;
                updatePaginationButtons();
            }
            
            // Limpar e preencher a tabela
            const tbody = document.querySelector('#vehiclesTable tbody');
            tbody.innerHTML = '';
            
            if (data.data && data.data.length > 0) {
                data.data.forEach(vehicle => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${vehicle.placa || '-'}</td>
                        <td>${vehicle.modelo || '-'}</td>
                        <td>${vehicle.marca || '-'}</td>
                        <td>${vehicle.ano || '-'}</td>
                        <td><span class="status-badge status-${(vehicle.status_nome || 'ativo').toLowerCase()}">${vehicle.status_nome || 'Ativo'}</span></td>
                        <td>${vehicle.cavalo_nome ? 
                            `${vehicle.cavalo_nome} (${vehicle.cavalo_eixos} eixos, ${vehicle.cavalo_tracao})` : 
                            '-'}</td>
                        <td>${vehicle.carreta_nome ? 
                            `${vehicle.carreta_nome} (${vehicle.carreta_capacidade} ton)` : 
                            '-'}</td>
                        <td>${formatKm(vehicle.km_atual)}</td>
                        <td>
                            <div class="table-actions">
                                <button class="btn-icon view-btn" data-id="${vehicle.id}" title="Ver detalhes">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-icon edit-btn" data-id="${vehicle.id}" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon delete-btn" data-id="${vehicle.id}" title="Excluir">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            } else {
                const row = document.createElement('tr');
                row.innerHTML = '<td colspan="9" class="text-center">Nenhum veículo encontrado</td>';
                tbody.appendChild(row);
            }
            
            // Configurar eventos dos botões
            setupTableButtons();
        })
        .catch(error => {
            console.error('Error loading vehicle data:', error);
            const tbody = document.querySelector('#vehiclesTable tbody');
            tbody.innerHTML = '<tr><td colspan="9" class="text-center">Erro ao carregar dados dos veículos</td></tr>';
            
            // Zerar contadores
            document.getElementById('totalVehicles').textContent = '0';
            document.getElementById('activeVehicles').textContent = '0';
            document.getElementById('maintenanceVehicles').textContent = '0';
        });
}

function setupTableButtons() {
    // View buttons
    document.querySelectorAll('.view-btn').forEach(button => {
        button.addEventListener('click', function() {
            const vehicleId = this.getAttribute('data-id');
            showVehicleDetails(vehicleId);
        });
    });
    
    // Edit buttons
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const vehicleId = this.getAttribute('data-id');
            showEditVehicleModal(vehicleId);
        });
    });
    
    // Delete buttons
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function() {
            const vehicleId = this.getAttribute('data-id');
            showDeleteConfirmation(vehicleId);
        });
    });
}

function showAddVehicleModal() {
    // Clear form
    document.getElementById('vehicleForm').reset();
    document.getElementById('vehicleId').value = '';
    
    // Set modal title
    document.getElementById('modalTitle').textContent = 'Adicionar Veículo';
    
    // Show modal
    const modal = document.getElementById('vehicleModal');
    if (modal) {
        modal.style.display = 'block';
    }
}

function showVehicleDetails(vehicleId) {
    // Verifica se o modal existe
    const modal = document.getElementById('viewVehicleModal');
    if (!modal) {
        console.error('Modal de detalhes do veículo não encontrado');
        alert('Erro: Modal não encontrado');
        return;
    }

    fetch(`../api/vehicle_data.php?action=view&id=${vehicleId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const vehicle = data.data.veiculo;
                window.selectedVehicle = vehicle;
                const historicoKm = data.data.historico_km;

                // Debug: log dos dados do veículo
                console.log('Dados do veículo:', vehicle);

                // Preenche os dados básicos do veículo
                const vehicleModelYear = document.getElementById('vehicleModelYear');
                const vehiclePlate = document.getElementById('vehiclePlate');
                const vehicleStatus = document.getElementById('vehicleStatus');
                const detailChassisNumber = document.getElementById('detailChassisNumber');
                const detailRenavam = document.getElementById('detailRenavam');
                const detailMileage = document.getElementById('detailMileage');
                const detailFuelType = document.getElementById('detailFuelType');
                const detailCavalo = document.getElementById('detailCavalo');
                const detailCarreta = document.getElementById('detailCarreta');
                const detailNotes = document.getElementById('detailNotes');
                const detailColor = document.getElementById('detailColor');
                const detailYear = document.getElementById('detailYear');
                const detailCapacidadeCarga = document.getElementById('detailCapacidadeCarga');
                const detailCapacidadePassageiros = document.getElementById('detailCapacidadePassageiros');
                const detailProprietario = document.getElementById('detailProprietario');
                const detailAcquisition = document.getElementById('detailAcquisition');

                if (vehicleModelYear) vehicleModelYear.textContent = `${vehicle.modelo} ${vehicle.ano}`;
                if (vehiclePlate) vehiclePlate.textContent = vehicle.placa;
                if (vehicleStatus) vehicleStatus.textContent = vehicle.status_nome;
                if (detailChassisNumber) detailChassisNumber.textContent = vehicle.chassi || '-';
                if (detailRenavam) detailRenavam.textContent = vehicle.renavam || '-';
                if (detailMileage) detailMileage.textContent = formatKm(vehicle.km_atual);
                if (detailFuelType) detailFuelType.textContent = vehicle.tipo_combustivel_nome || '-';
                if (detailCavalo) detailCavalo.textContent = vehicle.cavalo_nome ? 
                    `${vehicle.cavalo_nome} (${vehicle.cavalo_eixos} eixos, ${vehicle.cavalo_tracao})` : '-';
                if (detailCarreta) detailCarreta.textContent = vehicle.carreta_nome ? 
                    `${vehicle.carreta_nome} (${vehicle.carreta_capacidade} ton)` : '-';
                if (detailNotes) detailNotes.textContent = vehicle.observacoes || 'Sem observações';

                // Preenche os campos adicionais que estavam faltando
                if (detailColor) detailColor.textContent = vehicle.cor || '-';
                if (detailYear) detailYear.textContent = vehicle.ano || '-';
                
                // Verifica se capacidade_carga existe e é maior que 0
                const capacidadeCarga = vehicle.capacidade_carga;
                if (detailCapacidadeCarga) {
                    detailCapacidadeCarga.textContent = 
                        capacidadeCarga && parseFloat(capacidadeCarga) > 0 ? 
                        `${parseFloat(capacidadeCarga).toLocaleString('pt-BR')} kg` : '-';
                }
                
                // Verifica se capacidade_passageiros existe e é maior que 0
                const capacidadePassageiros = vehicle.capacidade_passageiros;
                if (detailCapacidadePassageiros) {
                    detailCapacidadePassageiros.textContent = 
                        capacidadePassageiros && parseInt(capacidadePassageiros) > 0 ? 
                        capacidadePassageiros : '-';
                }
                
                if (detailProprietario) detailProprietario.textContent = vehicle.proprietario || '-';
                
                // Formata a data de cadastro
                const dataCadastro = vehicle.data_cadastro;
                if (detailAcquisition) {
                    detailAcquisition.textContent = 
                        dataCadastro ? new Date(dataCadastro).toLocaleDateString('pt-BR') : '-';
                }

                // Popular os cards com os totais reais vindos da API
                if (data.data && data.data.totals) {
                    const totals = data.data.totals;
                    
                    // Atualizar card de atividade recente
                    const lastTripDate = document.getElementById('lastTripDate');
                    const lastRefuelDate = document.getElementById('lastRefuelDate');
                    const lastMaintenanceDate = document.getElementById('lastMaintenanceDate');
                    
                    if (lastTripDate) {
                        lastTripDate.textContent = totals.ultima_viagem ? formatDate(totals.ultima_viagem) : '-';
                    }
                    if (lastRefuelDate) {
                        lastRefuelDate.textContent = totals.ultimo_abastecimento ? formatDate(totals.ultimo_abastecimento) : '-';
                    }
                    if (lastMaintenanceDate) {
                        lastMaintenanceDate.textContent = totals.ultima_manutencao ? formatDate(totals.ultima_manutencao) : '-';
                    }
                    
                    // Atualizar card de status geral com totais reais
                    const totalTripsCount = document.getElementById('totalTripsCount');
                    const totalRefuelsCount = document.getElementById('totalRefuelsCount');
                    const totalMaintenanceCount = document.getElementById('totalMaintenanceCount');
                    
                    if (totalTripsCount) totalTripsCount.textContent = totals.total_viagens;
                    if (totalRefuelsCount) totalRefuelsCount.textContent = totals.total_abastecimentos;
                    if (totalMaintenanceCount) totalMaintenanceCount.textContent = totals.total_manutencoes;
                    
                    console.log('Totais atualizados:', totals);
                } else {
                    console.log('Totais não encontrados na resposta da API');
                }

                // Armazena o ID do veículo no modal para uso nas abas
                modal.setAttribute('data-vehicle-id', vehicleId);
                
                // Carregar dados de custos automaticamente
                loadVehicleCosts(vehicleId);
                
                // Mostra o modal
                modal.style.display = 'block';
            } else {
                alert('Erro ao carregar dados do veículo');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao carregar dados do veículo');
        });
}

function loadMaintenanceHistory(vehicleId) {
    fetch(`../api/vehicle_data.php?action=maintenance&id=${vehicleId}`, {
        credentials: 'include'
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.error || 'Erro ao carregar histórico de manutenção');
            });
        }
        return response.json();
    })
    .then(data => {
        const maintenanceBody = document.getElementById('maintenanceHistoryBody');
        if (!maintenanceBody) return;
        
        maintenanceBody.innerHTML = '';
        
        if (data.maintenanceRecords && data.maintenanceRecords.length > 0) {
            data.maintenanceRecords.forEach(record => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${record.date}</td>
                    <td>${record.type}</td>
                    <td>${record.description}</td>
                    <td>${formatKm(record.mileage)}</td>
                    <td>R$ ${parseFloat(record.cost).toFixed(2)}</td>
                    <td>${record.mechanic}</td>
                    <td>${record.notes || '-'}</td>
                `;
                maintenanceBody.appendChild(row);
            });
        } else {
            const row = document.createElement('tr');
            row.innerHTML = '<td colspan="7" class="text-center">Nenhum registro de manutenção encontrado</td>';
            maintenanceBody.appendChild(row);
        }
    })
    .catch(error => {
        console.error('Error loading maintenance history:', error);
        const maintenanceBody = document.getElementById('maintenanceHistoryBody');
        if (maintenanceBody) {
            maintenanceBody.innerHTML = '<tr><td colspan="7" class="text-center">Erro ao carregar histórico de manutenção</td></tr>';
        }
    });
}

function showDeleteConfirmation(vehicleId) {
    // Load vehicle data to show plate in confirmation
    fetch(`../api/vehicle_data.php?action=view&id=${vehicleId}`, {
        credentials: 'include'
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.error || 'Erro ao carregar dados do veículo');
            });
        }
        return response.json();
    })
    .then(data => {
        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar dados do veículo');
        }
        
        const vehicle = data.data;
        
        // Set vehicle plate in confirmation message
        document.getElementById('deleteVehiclePlate').textContent = vehicle.placa;
        
        // Store vehicle ID in modal for delete action
        document.getElementById('deleteVehicleModal').setAttribute('data-id', vehicleId);
        
        // Show modal
        document.getElementById('deleteVehicleModal').classList.add('active');
        
        // Setup delete confirmation button
        document.getElementById('confirmDeleteBtn').onclick = function() {
            deleteVehicle(vehicleId);
        };
    })
    .catch(error => {
        console.error('Error loading vehicle data:', error);
        alert('Erro ao carregar dados do veículo: ' + error.message);
    });
}

function showEditVehicleModal(vehicleId) {
    // Set modal title
    document.getElementById('modalTitle').textContent = 'Editar Veículo';
    
    // Load vehicle data
    fetch(`../api/vehicle_data.php?action=view&id=${vehicleId}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Erro ao carregar dados do veículo');
            }
            
            const vehicle = data.data.veiculo;
            
            // Populate form
            document.getElementById('vehicleId').value = vehicle.id;
            document.getElementById('placa').value = vehicle.placa || '';
            document.getElementById('modelo').value = vehicle.modelo || '';
            document.getElementById('marca').value = vehicle.marca || '';
            document.getElementById('ano').value = vehicle.ano || '';
            document.getElementById('cor').value = vehicle.cor || '';
            document.getElementById('status_id').value = vehicle.status_id || '1';
            document.getElementById('id_cavalo').value = vehicle.id_cavalo || '';
            
            // Se tiver um cavalo selecionado, carrega as carretas compatíveis
            if (vehicle.id_cavalo) {
                loadCompatibleCarretas(vehicle.id_cavalo).then(() => {
                    document.getElementById('id_carreta').value = vehicle.id_carreta || '';
                });
            }
            
            document.getElementById('km_atual').value = vehicle.km_atual || '';
            document.getElementById('tipo_combustivel_id').value = vehicle.tipo_combustivel_id || '';
            document.getElementById('chassi').value = vehicle.chassi || '';
            document.getElementById('renavam').value = vehicle.renavam || '';
            document.getElementById('capacidade_carga').value = vehicle.capacidade_carga || '';
            document.getElementById('capacidade_passageiros').value = vehicle.capacidade_passageiros || '';
            document.getElementById('numero_motor').value = vehicle.numero_motor || '';
            document.getElementById('proprietario').value = vehicle.proprietario || '';
            document.getElementById('potencia_motor').value = vehicle.potencia_motor || '';
            document.getElementById('numero_eixos').value = vehicle.numero_eixos || '';
            document.getElementById('carroceria_id').value = vehicle.carroceria_id || '';
            document.getElementById('observacoes').value = vehicle.observacoes || '';
            
            // Show modal
            document.getElementById('vehicleModal').style.display = 'block';
        })
        .catch(error => {
            console.error('Error loading vehicle data:', error);
            alert('Erro ao carregar dados do veículo: ' + error.message);
        });
}

function saveVehicle() {
    // Get form data
    const vehicleId = document.getElementById('vehicleId').value;
    const action = vehicleId ? 'update' : 'create';
    
    // Create FormData object for file uploads
    const formData = new FormData(document.getElementById('vehicleForm'));
    formData.append('action', action);
    if (vehicleId) {
        formData.append('id', vehicleId);
    }
    
    // Debug: Log form data
    console.log('Form data being sent:', {
        action: action,
        id: vehicleId
    });
    for (let [key, value] of formData.entries()) {
        console.log(key + ': ' + value);
    }
    
    // API URL
    const url = '../actions/vehicle_actions.php';
    
    // Send data to API
    fetch(url, {
        method: 'POST',
        body: formData,
        credentials: 'include'
    })
    .then(response => {
        // Debug: Log raw response
        console.log('Raw response:', response);
        
        // If response is not ok, try to parse it as JSON first
        if (!response.ok) {
            return response.text().then(text => {
                try {
                    // Try to parse as JSON
                    const data = JSON.parse(text);
                    if (data.redirect) {
                        window.location.href = data.redirect;
                        throw new Error('Redirecionado: ' + data.message);
                    }
                    throw new Error(data.message || 'Erro desconhecido');
                } catch (e) {
                    // If not JSON, throw the raw text
                    throw new Error(text || 'Erro desconhecido');
                }
            });
        }
        
        // Try to parse successful response as JSON
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Erro ao processar resposta do servidor: ' + text);
            }
        });
    })
    .then(data => {
        if (data.success) {
            // Close modal
            closeAllModals();
            
            // Show success message
            alert(data.message);
            
            // Reload page to show updated data
            window.location.reload();
        } else {
            if (data.redirect) {
                window.location.href = data.redirect;
                throw new Error('Redirecionado: ' + data.message);
            }
            throw new Error(data.message || 'Falha ao salvar veículo.');
        }
    })
    .catch(error => {
        console.error('Error saving vehicle:', error);
        if (!error.message.includes('Redirecionado')) {
            alert('Erro ao salvar veículo: ' + error.message);
        }
    });
}

function deleteVehicle() {
    const vehicleId = document.getElementById('deleteVehicleModal').getAttribute('data-id');
    
    // Send delete request to API
    fetch(`../api/vehicle_data.php?action=delete&id=${vehicleId}`, {
        method: 'DELETE',
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close modal
            closeAllModals();
            
            // Remove row from table
            const row = document.querySelector(`.delete-btn[data-id="${vehicleId}"]`).closest('tr');
            row.remove();
            
            // Update vehicle count
            const totalVehicles = parseInt(document.getElementById('totalVehicles').textContent) - 1;
            document.getElementById('totalVehicles').textContent = totalVehicles;
            
            // Update active vehicles count if applicable
            const activeVehicles = parseInt(document.getElementById('activeVehicles').textContent);
            if (row.cells[3].textContent.trim() === 'Ativo') {
                document.getElementById('activeVehicles').textContent = activeVehicles - 1;
            }
        } else {
            alert('Erro: ' + (data.error || 'Falha ao excluir veículo.'));
        }
    })
    .catch(error => {
        console.error('Error deleting vehicle:', error);
        alert('Erro ao excluir veículo. Tente novamente.');
    });
}

// Helper function to format kilometers
function formatKm(km) {
    if (!km || km === 0) return '0 km';
    return `${parseFloat(km).toLocaleString('pt-BR')} km`;
}

function formatDate(dateString) {
    if (!dateString) return '-';
    try {
        return new Date(dateString).toLocaleDateString('pt-BR');
    } catch (error) {
        return dateString;
    }
}

function initializeCharts() {
    // Initialize Fuel Efficiency Chart
    const fuelEfficiencyCtx = document.getElementById('fuelEfficiencyChart');
    if (fuelEfficiencyCtx) {
        new Chart(fuelEfficiencyCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                datasets: [{
                    label: 'Consumo Médio (km/l)',
                    data: [8.5, 8.7, 8.3, 8.9, 8.6, 8.8, 9.1, 8.4, 8.7, 9.0, 8.6, 8.9],
                    borderColor: 'rgba(46, 204, 64, 1)',
                    backgroundColor: 'rgba(46, 204, 64, 0.2)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: 'rgba(46, 204, 64, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                size: 12,
                                weight: '500'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(46, 204, 64, 0.3)',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return `${context.parsed.y.toFixed(1)} km/l`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    },
                    y: {
                        beginAtZero: false,
                        min: 7,
                        max: 10,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            font: {
                                size: 11
                            },
                            callback: function(value) {
                                return value.toFixed(1) + ' km/l';
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    }

    // Initialize Maintenance Cost Chart
    const maintenanceCostCtx = document.getElementById('maintenanceCostChart');
    if (maintenanceCostCtx) {
        new Chart(maintenanceCostCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                datasets: [{
                    label: 'Custo de Manutenção',
                    data: [1200, 800, 1500, 600, 2000, 900, 1100, 1800, 750, 1300, 950, 1600],
                    backgroundColor: 'rgba(231, 76, 60, 0.8)',
                    borderColor: 'rgba(231, 76, 60, 1)',
                    borderWidth: 2,
                    borderRadius: 6,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                size: 12,
                                weight: '500'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(231, 76, 60, 0.3)',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                return `R$ ${context.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            font: {
                                size: 11
                            },
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    }

    // Initialize Vehicle Cost Chart in details modal
    const vehicleCostCtx = document.getElementById('vehicleCostChart');
    if (vehicleCostCtx) {
        new Chart(vehicleCostCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
                datasets: [{
                    label: 'Manutenção',
                    data: [1200, 800, 1500, 600, 2000, 900],
                    borderColor: '#4CAF50',
                    tension: 0.4
                }, {
                    label: 'Combustível',
                    data: [2000, 2200, 1800, 2100, 1900, 2300],
                    borderColor: '#2196F3',
                    tension: 0.4
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
                        text: 'Custos por Categoria'
                    }
                }
            }
        });
    }
}

function setupPagination() {
    const prevBtn = document.getElementById('prevPageBtn');
    const nextBtn = document.getElementById('nextPageBtn');
    
    if (prevBtn && nextBtn) {
        prevBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const currentPage = parseInt(document.getElementById('currentPage').textContent);
            if (currentPage > 1) {
                loadVehicleData(currentPage - 1);
            }
        });
        
        nextBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const currentPage = parseInt(document.getElementById('currentPage').textContent);
            const totalPages = parseInt(document.getElementById('totalPages').textContent);
            if (currentPage < totalPages) {
                loadVehicleData(currentPage + 1);
            }
        });
        
        // Atualiza estado dos botões
        updatePaginationButtons();
    }
}

function updatePaginationButtons() {
    const currentPage = parseInt(document.getElementById('currentPage').textContent);
    const totalPages = parseInt(document.getElementById('totalPages').textContent);
    const prevBtn = document.getElementById('prevPageBtn');
    const nextBtn = document.getElementById('nextPageBtn');
    
    if (prevBtn) {
        prevBtn.classList.toggle('disabled', currentPage <= 1);
    }
    
    if (nextBtn) {
        nextBtn.classList.toggle('disabled', currentPage >= totalPages);
    }
}

function updateURLParameter(param, value) {
    const url = new URL(window.location.href);
    url.searchParams.set(param, value);
    return url.toString();
}

// Função para carregar carretas compatíveis com o cavalo selecionado
async function loadCompatibleCarretas(cavaloId) {
    try {
        const response = await fetch(`../api/veiculos.php?action=get_compatible_carretas&cavalo_id=${cavaloId}`);
        if (!response.ok) throw new Error('Erro ao carregar carretas compatíveis');
        
        const data = await response.json();
        const carretaSelect = document.getElementById('id_carreta');
        
        // Limpa as opções atuais
        carretaSelect.innerHTML = '<option value="">Selecione uma carreta</option>';
        
        // Adiciona as novas opções
        data.forEach(carreta => {
            const option = document.createElement('option');
            option.value = carreta.id;
            option.textContent = `${carreta.nome} (${carreta.capacidade_media} ton)`;
            carretaSelect.appendChild(option);
        });
    } catch (error) {
        console.error('Erro ao carregar carretas compatíveis:', error);
    }
}

// Adiciona event listener para o select de cavalos
document.addEventListener('DOMContentLoaded', function() {
    const cavaloSelect = document.getElementById('id_cavalo');
    if (cavaloSelect) {
        cavaloSelect.addEventListener('change', function() {
            const cavaloId = this.value;
            if (cavaloId) {
                loadCompatibleCarretas(cavaloId);
            } else {
                // Se nenhum cavalo selecionado, limpa o select de carretas
                const carretaSelect = document.getElementById('id_carreta');
                carretaSelect.innerHTML = '<option value="">Selecione uma carreta</option>';
            }
        });
    }
});

// Função para configurar botões de ajuda e exportação
function setupHelpAndExportButtons() {
    // Help button
    const helpBtn = document.getElementById('helpBtn');
    if (helpBtn) {
        helpBtn.addEventListener('click', function() {
            const helpModal = document.getElementById('helpVehiclesModal');
            if (helpModal) {
                helpModal.style.display = 'block';
            }
        });
    }

    // Close modal functionality for help modal
    document.querySelectorAll('.close-modal').forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });

    // Close modal when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(event) {
            if (event.target === this) {
                this.style.display = 'none';
            }
        });
    });
}

// Função para fechar modal específico
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

function loadVehicleCosts(vehicleId) {
    console.log('Carregando custos para veículo:', vehicleId);
    
    fetch(`/sistema-frotas/api/vehicle_data.php?action=costs&id=${vehicleId}`, {
        credentials: 'include'
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.error || 'Erro ao carregar custos do veículo');
            });
        }
        return response.json();
    })
    .then(data => {
        console.log('Dados de custos recebidos:', data);
        if (data.success && data.costs) {
            // Atualizar valores de custo
            const maintenanceCost = document.getElementById('maintenanceCostValue');
            const fuelCost = document.getElementById('fuelCostValue');
            const totalCost = document.getElementById('totalCostValue');
            const costPerKm = document.getElementById('costPerKm');
            
            if (maintenanceCost) {
                maintenanceCost.textContent = `R$ ${parseFloat(data.costs.maintenance).toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
            }
            if (fuelCost) {
                fuelCost.textContent = `R$ ${parseFloat(data.costs.fuel).toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
            }
            if (totalCost) {
                totalCost.textContent = `R$ ${parseFloat(data.costs.total).toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
            }
            if (costPerKm) {
                costPerKm.textContent = `R$ ${parseFloat(data.costs.cost_per_km).toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
            }
            
            // Inicializar gráfico de custos se houver dados
            if (data.chart_data && data.chart_data.length > 0) {
                initVehicleCostChart(data.chart_data);
            } else {
                // Dados de exemplo para demonstração
                const exampleData = [
                    { mes: '2025-01', combustivel: 1200, manutencao: 300 },
                    { mes: '2025-02', combustivel: 1100, manutencao: 0 },
                    { mes: '2025-03', combustivel: 1350, manutencao: 850 },
                    { mes: '2025-04', combustivel: 1250, manutencao: 200 },
                    { mes: '2025-05', combustivel: 1400, manutencao: 0 },
                    { mes: '2025-06', combustivel: 1180, manutencao: 450 }
                ];
                initVehicleCostChart(exampleData);
            }
        }
    })
    .catch(error => {
        console.error('Erro ao carregar custos:', error);
        
        // Valores padrão em caso de erro
        const maintenanceCost = document.getElementById('maintenanceCostValue');
        const fuelCost = document.getElementById('fuelCostValue');
        const totalCost = document.getElementById('totalCostValue');
        const costPerKm = document.getElementById('costPerKm');
        
        if (maintenanceCost) maintenanceCost.textContent = 'R$ 4.900,00';
        if (fuelCost) fuelCost.textContent = 'R$ 16.417,00';
        if (totalCost) totalCost.textContent = 'R$ 21.317,00';
        if (costPerKm) costPerKm.textContent = 'R$ 0,77';
    });
}

function initVehicleCostChart(data) {
    const canvas = document.getElementById('vehicleCostChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    // Destruir gráfico existente se houver
    if (window.vehicleCostChartInstance) {
        window.vehicleCostChartInstance.destroy();
    }
    
    const labels = data.map(item => {
        const [year, month] = item.mes.split('-');
        return new Date(year, month - 1).toLocaleDateString('pt-BR', { month: 'short', year: '2-digit' });
    });
    
    const fuelData = data.map(item => parseFloat(item.combustivel || 0));
    const maintenanceData = data.map(item => parseFloat(item.manutencao || 0));
    
    window.vehicleCostChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Combustível',
                    data: fuelData,
                    backgroundColor: 'rgba(54, 162, 235, 0.8)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Manutenção',
                    data: maintenanceData,
                    backgroundColor: 'rgba(255, 99, 132, 0.8)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Custos por Mês (Últimos 6 Meses)'
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
                            return 'R$ ' + value.toLocaleString('pt-BR');
                        }
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': R$ ' + 
                                   context.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                        }
                    }
                }
            }
        }
    });
}

 