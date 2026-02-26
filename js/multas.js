// Multas.js - Gerenciamento de Multas
document.addEventListener('DOMContentLoaded', function() {
    initializeMultas();
    initializeMultasCharts();
    initializeDenatranConsulta();
});

function initializeMultas() {
    // Botões principais
    const addMultaBtn = document.getElementById('addMultaBtn');
    const filterBtn = document.getElementById('filterBtn');
    const exportBtn = document.getElementById('exportBtn');
    const helpBtn = document.getElementById('helpBtn');

    // Event listeners para botões principais
    if (addMultaBtn) {
        addMultaBtn.addEventListener('click', openNewMultaModal);
    }

    if (filterBtn) {
        filterBtn.addEventListener('click', showFilters);
    }

    if (exportBtn) {
        exportBtn.addEventListener('click', exportMultas);
    }

    if (helpBtn) {
        helpBtn.addEventListener('click', showHelp);
    }

    // Event listeners para botões de ação na tabela
    document.addEventListener('click', function(e) {
        if (e.target.closest('.edit-btn')) {
            const multaId = e.target.closest('.edit-btn').getAttribute('data-id');
            editMulta(multaId);
        }

        if (e.target.closest('.delete-btn')) {
            const multaId = e.target.closest('.delete-btn').getAttribute('data-id');
            deleteMulta(multaId);
        }

        if (e.target.closest('.view-comprovante-btn')) {
            const comprovante = e.target.closest('.view-comprovante-btn').getAttribute('data-comprovante');
            viewComprovante(comprovante);
        }
    });

    // Event listeners para modais
    setupModalEventListeners();

    setupTableFilters();
}

// --- Consulta DETRAN (WSDenatran) ---
function formatarCpfDisplay(cpf) {
    var n = (cpf || '').replace(/\D/g, '');
    if (n.length !== 11) return cpf || '';
    return n.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
}

function initializeDenatranConsulta() {
    const form = document.getElementById('denatranForm');
    const tipoSelect = document.getElementById('denatran_tipo');
    const cpfGroup = document.getElementById('denatran_cpf_group');
    const placaGroup = document.getElementById('denatran_placa_group');
    const exigibilidadeGroup = document.getElementById('denatran_exigibilidade_group');
    const cnpjGroup = document.getElementById('denatran_cnpj_group');
    const cpfUsuarioInput = document.getElementById('denatran_cpf_usuario');

    function toggleTipoFields() {
        const tipo = tipoSelect ? tipoSelect.value : 'cpf';
        if (cpfGroup) cpfGroup.style.display = tipo === 'cpf' ? '' : 'none';
        if (placaGroup) placaGroup.style.display = tipo === 'placa' ? '' : 'none';
        if (exigibilidadeGroup) exigibilidadeGroup.style.display = tipo === 'placa' ? '' : 'none';
        if (cnpjGroup) cnpjGroup.style.display = tipo === 'cnpj' ? '' : 'none';
    }

    if (tipoSelect) tipoSelect.addEventListener('change', toggleTipoFields);
    toggleTipoFields();

    // Pré-preencher CPF do usuário (quem consulta) com o valor das Configurações
    if (cpfUsuarioInput) {
        fetch('../api/configuracoes.php?action=get_config_denatran', { credentials: 'include' })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success && res.data && res.data.cpf_usuario && res.data.cpf_usuario.length === 11) {
                    cpfUsuarioInput.value = formatarCpfDisplay(res.data.cpf_usuario);
                    if (cpfUsuarioInput.placeholder) cpfUsuarioInput.placeholder = 'Preenchido pelas configurações';
                }
            })
            .catch(function() {});
    }

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            consultarDenatran();
        });
    }
}

function consultarDenatran() {
    const btn = document.getElementById('denatranConsultarBtn');
    const container = document.getElementById('denatranResultContainer');
    const msgEl = document.getElementById('denatranResultMessage');
    const tbody = document.getElementById('denatranResultBody');
    const tipo = document.getElementById('denatran_tipo').value;
    const cpfUsuario = (document.getElementById('denatran_cpf_usuario').value || '').replace(/\D/g, '');
    const cpf = (document.getElementById('denatran_cpf').value || '').replace(/\D/g, '');
    const placa = (document.getElementById('denatran_placa').value || '').replace(/[^A-Za-z0-9]/g, '').toUpperCase();
    const exigibilidade = document.getElementById('denatran_exigibilidade').value;
    const cnpj = (document.getElementById('denatran_cnpj').value || '').replace(/\D/g, '');
    const dataInicio = document.getElementById('denatran_data_inicio').value || '';
    const dataFim = document.getElementById('denatran_data_fim').value || '';

    if (cpfUsuario.length !== 11) {
        showAlert('Informe o CPF do usuário (quem consulta) com 11 dígitos.', 'error');
        return;
    }
    if (tipo === 'cpf' && cpf.length !== 11) {
        showAlert('Informe o CPF do condutor/proprietário.', 'error');
        return;
    }
    if (tipo === 'placa' && placa.length < 7) {
        showAlert('Informe a placa do veículo (7 caracteres).', 'error');
        return;
    }
    if (tipo === 'cnpj' && cnpj.length !== 14) {
        showAlert('Informe o CNPJ com 14 dígitos.', 'error');
        return;
    }

    const params = new URLSearchParams();
    params.set('action', 'consultar');
    params.set('tipo', tipo);
    params.set('cpf_usuario', cpfUsuario);
    if (dataInicio) params.set('dataInicio', dataInicio);
    if (dataFim) params.set('dataFim', dataFim);
    if (tipo === 'cpf') params.set('cpf', cpf);
    if (tipo === 'placa') {
        params.set('placa', placa);
        params.set('exigibilidade', exigibilidade);
    }
    if (tipo === 'cnpj') params.set('cnpj', cnpj);

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Consultando...';
    }
    if (container) container.style.display = 'block';
    if (msgEl) msgEl.textContent = 'Carregando...';
    if (tbody) tbody.innerHTML = '';

    fetch('../api/denatran_infracoes.php?' + params.toString(), { method: 'GET' })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (msgEl) msgEl.textContent = data.message || (data.success ? 'Consulta concluída.' : 'Erro na consulta.');
            if (data.success && data.infracoes && data.infracoes.length > 0) {
                tbody.innerHTML = data.infracoes.map(function(inf) {
                    var dataInfracao = inf.dataInfracao ? formatarDataISO(inf.dataInfracao) : '-';
                    var valor = inf.valorIntegralInfracao != null ? 'R$ ' + Number(inf.valorIntegralInfracao).toFixed(2).replace('.', ',') : '-';
                    var exig = inf.descricaoIndicadorExigibilidade || '-';
                    var orgao = inf.descricaoOrgaoAutuador || inf.codigoOrgaoAutuador || '-';
                    return '<tr><td>' + escapeHtml(inf.numeroAutoInfracao || '-') + '</td><td>' + escapeHtml(inf.placa || '-') + '</td><td>' + escapeHtml(inf.descricaoInfracao || '-') + '</td><td>' + dataInfracao + '</td><td>' + valor + '</td><td>' + escapeHtml(exig) + '</td><td>' + escapeHtml(orgao) + '</td></tr>';
                }).join('');
            } else if (tbody) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center">' + (data.success ? 'Nenhuma infração encontrada.' : '') + '</td></tr>';
            }
        })
        .catch(function(err) {
            if (msgEl) msgEl.textContent = 'Erro ao consultar: ' + (err.message || 'verifique a conexão.');
            if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center">Erro na requisição.</td></tr>';
        })
        .finally(function() {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-search"></i> Consultar no DETRAN';
            }
        });
}

function formatarDataISO(s) {
    if (!s) return '-';
    var d = new Date(s);
    if (isNaN(d.getTime())) return s;
    return ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear();
}

function escapeHtml(str) {
    if (str == null) return '';
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function initializeMultasCharts() {
    // Carregar dados dos gráficos
    loadMultasPorMesChart();
    loadValorPorMesChart();
    loadMultasPorMotoristaChart();
    loadPontosPorMotoristaChart();
}

function loadMultasPorMesChart() {
    fetch('../api/multas_analytics.php?action=multas_por_mes')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                createMultasPorMesChart(data.data);
            }
        })
        .catch(error => {
            console.error('Erro ao carregar dados de multas por mês:', error);
        });
}

function loadValorPorMesChart() {
    fetch('../api/multas_analytics.php?action=valor_por_mes')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                createValorPorMesChart(data.data);
            }
        })
        .catch(error => {
            console.error('Erro ao carregar dados de valor por mês:', error);
        });
}

function loadMultasPorMotoristaChart() {
    fetch('../api/multas_analytics.php?action=multas_por_motorista')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                createMultasPorMotoristaChart(data.data);
            }
        })
        .catch(error => {
            console.error('Erro ao carregar dados de multas por motorista:', error);
        });
}

function loadPontosPorMotoristaChart() {
    fetch('../api/multas_analytics.php?action=pontos_por_motorista')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                createPontosPorMotoristaChart(data.data);
            }
        })
        .catch(error => {
            console.error('Erro ao carregar dados de pontos por motorista:', error);
        });
}

function setupTableFilters() {
    const tableBody = document.querySelector('#multasTable tbody');
    if (!tableBody) return;

    const searchInput = document.getElementById('searchMulta');
    const vehicleFilter = document.getElementById('vehicleFilter');
    const driverFilter = document.getElementById('driverFilter');
    const statusFilter = document.getElementById('statusFilter');
    const applyBtn = document.getElementById('applyFinesFilters');
    const clearBtn = document.getElementById('clearFinesFilters');

    const applyFilters = () => {
        const searchTerm = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const vehicleValue = vehicleFilter ? vehicleFilter.value.trim().toLowerCase() : '';
        const driverValue = driverFilter ? driverFilter.value.trim().toLowerCase() : '';
        const statusValue = statusFilter ? statusFilter.value.trim().toLowerCase() : '';

        const rows = tableBody.querySelectorAll('tr');
        rows.forEach(row => {
            const rowText = row.textContent.toLowerCase();
            const vehicleCell = row.querySelector('td:nth-child(2)');
            const driverCell = row.querySelector('td:nth-child(3)');
            const statusCell = row.querySelector('td:nth-child(8)');

            const matchesSearch = !searchTerm || rowText.includes(searchTerm);
            const matchesVehicle = !vehicleValue || (vehicleCell && vehicleCell.textContent.toLowerCase().includes(vehicleValue));
            const matchesDriver = !driverValue || (driverCell && driverCell.textContent.toLowerCase().includes(driverValue));
            const matchesStatus = !statusValue || (statusCell && statusCell.textContent.toLowerCase().includes(statusValue));

            row.style.display = (matchesSearch && matchesVehicle && matchesDriver && matchesStatus) ? '' : 'none';
        });
    };

    if (searchInput) {
        searchInput.addEventListener('input', debounce(applyFilters, 200));
    }

    if (vehicleFilter) vehicleFilter.addEventListener('change', applyFilters);
    if (driverFilter) driverFilter.addEventListener('change', applyFilters);
    if (statusFilter) statusFilter.addEventListener('change', applyFilters);

    if (applyBtn) {
        applyBtn.addEventListener('click', applyFilters);
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (vehicleFilter) vehicleFilter.value = '';
            if (driverFilter) driverFilter.value = '';
            if (statusFilter) statusFilter.value = '';
            applyFilters();
        });
    }

    applyFilters();
}

function debounce(fn, wait = 200) {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn.apply(null, args), wait);
    };
}

function createMultasPorMesChart(data) {
    const ctx = document.getElementById('multasPorMesChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Quantidade de Multas',
                data: data.values,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Evolução de Multas por Mês'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

function createValorPorMesChart(data) {
    const ctx = document.getElementById('valorPorMesChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Valor Total (R$)',
                data: data.values,
                backgroundColor: 'rgba(54, 162, 235, 0.8)',
                borderColor: 'rgb(54, 162, 235)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Valor Total de Multas por Mês'
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

function createMultasPorMotoristaChart(data) {
    const ctx = document.getElementById('multasPorMotoristaChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.values,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 205, 86, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(153, 102, 255, 0.8)',
                    'rgba(255, 159, 64, 0.8)'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                },
                title: {
                    display: true,
                    text: 'Distribuição de Multas por Motorista'
                }
            }
        }
    });
}

function createPontosPorMotoristaChart(data) {
    const ctx = document.getElementById('pontosPorMotoristaChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Pontos na CNH',
                data: data.values,
                backgroundColor: 'rgba(255, 99, 132, 0.8)',
                borderColor: 'rgb(255, 99, 132)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Pontos na CNH por Motorista'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

function setupModalEventListeners() {
    // Modal de multa
    const multaModal = document.getElementById('multaModal');
    const saveMultaBtn = document.getElementById('saveMultaBtn');
    const cancelMultaBtn = document.getElementById('cancelMultaBtn');

    if (saveMultaBtn) {
        saveMultaBtn.addEventListener('click', saveMulta);
    }

    if (cancelMultaBtn) {
        cancelMultaBtn.addEventListener('click', closeMultaModal);
    }

    // Modal de exclusão
    const deleteMultaModal = document.getElementById('deleteMultaModal');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', confirmDeleteMulta);
    }

    if (cancelDeleteBtn) {
        cancelDeleteBtn.addEventListener('click', closeDeleteModal);
    }

    // Fechar modais com X
    const closeButtons = document.querySelectorAll('.close-modal');
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });

    // Fechar modais clicando fora
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            e.target.style.display = 'none';
        }
    });
}

function openNewMultaModal() {
    const modal = document.getElementById('multaModal');
    const modalTitle = document.getElementById('modalTitle');
    const form = document.getElementById('multaForm');

    // Limpar formulário
    form.reset();
    document.getElementById('multaId').value = '';
    modalTitle.textContent = 'Nova Multa';

    // Definir data atual como padrão
    document.getElementById('data_infracao').value = new Date().toISOString().split('T')[0];

    modal.style.display = 'block';
}

function editMulta(multaId) {
    // Buscar dados da multa
    fetch(`../api/multas.php?action=get&id=${multaId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                fillMultaForm(data.multa);
                openEditMultaModal();
            } else {
                showAlert('Erro ao carregar dados da multa', 'error');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showAlert('Erro ao carregar dados da multa', 'error');
        });
}

function fillMultaForm(multa) {
    document.getElementById('multaId').value = multa.id;
    document.getElementById('data_infracao').value = multa.data_infracao;
    document.getElementById('veiculo_id').value = multa.veiculo_id;
    document.getElementById('motorista_id').value = multa.motorista_id;
    document.getElementById('rota_id').value = multa.rota_id || '';
    document.getElementById('tipo_infracao').value = multa.tipo_infracao;
    document.getElementById('pontos').value = multa.pontos;
    document.getElementById('valor').value = multa.valor;
    document.getElementById('vencimento').value = multa.vencimento || '';
    document.getElementById('status_pagamento').value = multa.status_pagamento;
    document.getElementById('data_pagamento').value = multa.data_pagamento || '';
    document.getElementById('descricao').value = multa.descricao || '';

    document.getElementById('modalTitle').textContent = 'Editar Multa';
}

function openEditMultaModal() {
    const modal = document.getElementById('multaModal');
    modal.style.display = 'block';
}

function saveMulta() {
    console.log('=== INICIANDO SALVAMENTO DE MULTA ===');
    
    const form = document.getElementById('multaForm');
    const formData = new FormData(form);

    // Debug: mostrar dados do formulário
    console.log('Dados do formulário:');
    for (let [key, value] of formData.entries()) {
        console.log(`${key}: ${value}`);
    }

    // Validações básicas
    if (!validateMultaForm(formData)) {
        console.log('❌ Validação falhou');
        return;
    }

    const multaId = document.getElementById('multaId').value;
    const action = multaId ? 'update' : 'create';
    
    console.log('ID da multa:', multaId);
    console.log('Ação:', action);

    // Adicionar action ao FormData
    formData.append('action', action);

    console.log('Enviando requisição para:', '../api/multas.php');
    
    fetch('../api/multas.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response ok:', response.ok);
        return response.json();
    })
    .then(data => {
        console.log('Resposta da API:', data);
        
        if (data.success) {
            console.log('✅ Multa salva com sucesso');
            showAlert(data.message, 'success');
            closeMultaModal();
            // Recarregar página para atualizar dados
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            console.log('❌ Erro ao salvar multa:', data.message);
            showAlert(data.message || 'Erro ao salvar multa', 'error');
        }
    })
    .catch(error => {
        console.error('❌ Erro na requisição:', error);
        showAlert('Erro ao salvar multa', 'error');
    });
}

function validateMultaForm(formData) {
    const requiredFields = ['data_infracao', 'veiculo_id', 'motorista_id', 'tipo_infracao', 'valor', 'status_pagamento'];
    
    for (let field of requiredFields) {
        if (!formData.get(field)) {
            showAlert(`Campo ${field.replace('_', ' ')} é obrigatório`, 'error');
            return false;
        }
    }

    const valor = parseFloat(formData.get('valor'));
    if (valor <= 0) {
        showAlert('Valor da multa deve ser maior que zero', 'error');
        return false;
    }

    const pontos = parseInt(formData.get('pontos'));
    if (pontos < 0 || pontos > 20) {
        showAlert('Pontos devem estar entre 0 e 20', 'error');
        return false;
    }

    return true;
}

function deleteMulta(multaId) {
    // Armazenar ID para confirmação
    window.currentMultaId = multaId;
    
    // Abrir modal de confirmação
    const modal = document.getElementById('deleteMultaModal');
    modal.style.display = 'block';
}

function confirmDeleteMulta() {
    const multaId = window.currentMultaId;
    
    if (!multaId) {
        showAlert('ID da multa não encontrado', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', multaId);

    fetch('../api/multas.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            closeDeleteModal();
            // Recarregar página para atualizar dados
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert(data.message || 'Erro ao excluir multa', 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showAlert('Erro ao excluir multa', 'error');
    });
}

function closeMultaModal() {
    const modal = document.getElementById('multaModal');
    modal.style.display = 'none';
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteMultaModal');
    modal.style.display = 'none';
    window.currentMultaId = null;
}

function showFilters() {
    // Implementar filtros
    showAlert('Funcionalidade de filtros em desenvolvimento', 'info');
}

function exportMultas() {
    // Implementar exportação
    showAlert('Funcionalidade de exportação em desenvolvimento', 'info');
}

function showHelp() {
    const modal = document.getElementById('helpMultaModal');
    modal.style.display = 'block';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

function viewComprovante(comprovante) {
    if (comprovante) {
        const url = `../uploads/comprovantes/${comprovante}`;
        window.open(url, '_blank');
    } else {
        showAlert('Comprovante não encontrado', 'warning');
    }
}

function showAlert(message, type = 'info') {
    // Criar elemento de alerta
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
        <span>${message}</span>
        <button class="alert-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;

    // Adicionar ao body
    document.body.appendChild(alertDiv);

    // Remover automaticamente após 5 segundos
    setTimeout(() => {
        if (alertDiv.parentElement) {
            alertDiv.remove();
        }
    }, 5000);
}

function changePage(page) {
    window.location.href = `?page=${page}`;
} 