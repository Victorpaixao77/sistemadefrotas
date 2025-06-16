// Funções de utilidade
function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

function formatDate(date) {
    return new Date(date).toLocaleDateString('pt-BR');
}

function showMessage(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    document.querySelector('.container').insertBefore(alertDiv, document.querySelector('.container').firstChild);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Funções de API
async function fetchAPI(endpoint, method = 'GET', data = null) {
    const options = {
        method,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json'
        }
    };
    
    if (data) {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(`/sistema-frotas/pages_motorista/api/motorista_api.php?action=${endpoint}`, options);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'Erro na requisição');
        }
        
        return result;
    } catch (error) {
        showMessage(error.message, 'danger');
        throw error;
    }
}

// Funções de Rotas
function carregarVeiculos() {
    fetchAPI('get_veiculos')
        .then(data => {
            const select = document.getElementById('veiculo_id');
            select.innerHTML = '<option value="">Selecione um veículo</option>';
            data.forEach(veiculo => {
                select.innerHTML += `
                    <option value="${veiculo.id}">
                        ${veiculo.placa} - ${veiculo.modelo}
                    </option>
                `;
            });
        })
        .catch(error => {
            showMessage('Erro ao carregar veículos', 'error');
        });
}

function registrarRota(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'registrar_rota');

    // Validações
    const kmSaida = parseFloat(formData.get('km_saida'));
    const kmChegada = parseFloat(formData.get('km_chegada'));
    
    if (kmChegada <= kmSaida) {
        showMessage('A quilometragem de chegada deve ser maior que a de saída', 'error');
        return;
    }

    fetchAPI('registrar_rota', formData)
        .then(data => {
            showMessage('Rota registrada com sucesso', 'success');
            form.reset();
            setTimeout(() => location.reload(), 1500);
        })
        .catch(error => {
            showMessage(error.message || 'Erro ao registrar rota', 'error');
        });
}

async function carregarRotasPendentes() {
    try {
        const result = await fetchAPI('get_rotas_pendentes');
        const tbody = document.querySelector('#tabela-rotas tbody');
        
        tbody.innerHTML = '';
        result.data.forEach(rota => {
            tbody.innerHTML += `
                <tr>
                    <td>${rota.placa}</td>
                    <td>${rota.modelo}</td>
                    <td>${formatDate(rota.data_rota)}</td>
                    <td>${rota.origem}</td>
                    <td>${rota.destino}</td>
                    <td>${rota.km_rodado}</td>
                    <td>${rota.observacoes || '-'}</td>
                </tr>
            `;
        });
    } catch (error) {
        console.error('Erro ao carregar rotas:', error);
    }
}

// Funções de Abastecimento
async function salvarAbastecimento(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    try {
        await fetchAPI('salvar_abastecimento', 'POST', data);
        showMessage('Abastecimento registrado com sucesso! Aguardando aprovação.');
        form.reset();
    } catch (error) {
        console.error('Erro ao salvar abastecimento:', error);
    }
}

async function carregarAbastecimentosPendentes() {
    try {
        const result = await fetchAPI('get_abastecimentos_pendentes');
        const tbody = document.querySelector('#tabela-abastecimentos tbody');
        
        tbody.innerHTML = '';
        result.data.forEach(abastecimento => {
            tbody.innerHTML += `
                <tr>
                    <td>${abastecimento.placa}</td>
                    <td>${abastecimento.modelo}</td>
                    <td>${formatDate(abastecimento.data_abastecimento)}</td>
                    <td>${abastecimento.tipo_combustivel}</td>
                    <td>${abastecimento.quantidade}L</td>
                    <td>${formatCurrency(abastecimento.valor_litro)}</td>
                    <td>${formatCurrency(abastecimento.valor_total)}</td>
                    <td>${abastecimento.km_atual}</td>
                    <td>${abastecimento.posto || '-'}</td>
                    <td>${abastecimento.observacoes || '-'}</td>
                </tr>
            `;
        });
    } catch (error) {
        console.error('Erro ao carregar abastecimentos:', error);
    }
}

// Funções de Checklist
async function salvarChecklist(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    try {
        await fetchAPI('salvar_checklist', 'POST', data);
        showMessage('Checklist registrado com sucesso! Aguardando aprovação.');
        form.reset();
    } catch (error) {
        console.error('Erro ao salvar checklist:', error);
    }
}

async function carregarChecklistsPendentes() {
    try {
        const result = await fetchAPI('get_checklists_pendentes');
        const tbody = document.querySelector('#tabela-checklists tbody');
        
        tbody.innerHTML = '';
        result.data.forEach(checklist => {
            tbody.innerHTML += `
                <tr>
                    <td>${checklist.placa}</td>
                    <td>${checklist.modelo}</td>
                    <td>${formatDate(checklist.data_checklist)}</td>
                    <td>${checklist.tipo_checklist}</td>
                    <td>${checklist.km_atual}</td>
                    <td>${checklist.observacoes || '-'}</td>
                </tr>
            `;
        });
    } catch (error) {
        console.error('Erro ao carregar checklists:', error);
    }
}

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    // Carrega veículos no select
    carregarVeiculos();

    // Adiciona listener para o formulário de rota
    const rotaForm = document.getElementById('rotaForm');
    if (rotaForm) {
        rotaForm.addEventListener('submit', registrarRota);
    }

    // Adiciona máscara para estados
    const estados = document.querySelectorAll('input[name="estado_origem"], input[name="estado_destino"]');
    estados.forEach(input => {
        input.addEventListener('input', function(e) {
            this.value = this.value.toUpperCase().substr(0, 2);
        });
    });

    // Calcula distância automaticamente
    const kmChegada = document.getElementById('km_chegada');
    if (kmChegada) {
        kmChegada.addEventListener('change', function() {
            const kmSaida = parseFloat(document.getElementById('km_saida').value) || 0;
            const kmChegada = parseFloat(this.value) || 0;
            if (kmSaida > 0 && kmChegada > 0) {
                const distancia = kmChegada - kmSaida;
                if (distancia >= 0) {
                    const distanciaInput = document.createElement('input');
                    distanciaInput.type = 'hidden';
                    distanciaInput.name = 'distancia_km';
                    distanciaInput.value = distancia;
                    this.form.appendChild(distanciaInput);
                }
            }
        });
    }

    // Configurar formulários
    const formAbastecimento = document.getElementById('form-abastecimento');
    if (formAbastecimento) {
        formAbastecimento.addEventListener('submit', salvarAbastecimento);
        carregarAbastecimentosPendentes();
    }
    
    const formChecklist = document.getElementById('form-checklist');
    if (formChecklist) {
        formChecklist.addEventListener('submit', salvarChecklist);
        carregarChecklistsPendentes();
    }
    
    // Configurar cálculo automático do valor total no formulário de abastecimento
    const quantidadeInput = document.getElementById('quantidade');
    const valorLitroInput = document.getElementById('valor_litro');
    const valorTotalInput = document.getElementById('valor_total');
    
    if (quantidadeInput && valorLitroInput && valorTotalInput) {
        const calcularTotal = () => {
            const quantidade = parseFloat(quantidadeInput.value) || 0;
            const valorLitro = parseFloat(valorLitroInput.value) || 0;
            valorTotalInput.value = (quantidade * valorLitro).toFixed(2);
        };
        
        quantidadeInput.addEventListener('input', calcularTotal);
        valorLitroInput.addEventListener('input', calcularTotal);
    }
}); 