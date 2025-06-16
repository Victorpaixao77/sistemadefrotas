// Lógica dinâmica para o modal de Abastecimento

document.addEventListener('DOMContentLoaded', function() {
    let filtroData = '';
    let filtroVeiculo = '';
    let filtroMotorista = '';

    const dataRotaFiltroInput = document.getElementById('data_rota_filtro');
    const dataAbastecimentoInput = document.getElementById('data_abastecimento');
    const veiculoSelect = document.getElementById('veiculo_id');
    const motoristaSelect = document.getElementById('motorista_id');
    const rotaSelect = document.getElementById('rota_id');

    // Limpa selects dependentes
    function resetMotoristas() {
        motoristaSelect.innerHTML = '<option value="">Selecione um motorista</option>';
        motoristaSelect.disabled = true;
    }
    function resetRotas() {
        rotaSelect.innerHTML = '<option value="">Selecione a rota</option>';
        rotaSelect.disabled = true;
    }
    function resetVeiculos() {
        veiculoSelect.innerHTML = '<option value="">Selecione um veículo</option>';
        veiculoSelect.disabled = true;
    }

    // Ao alterar a data do filtro, buscar veículos disponíveis
    dataRotaFiltroInput.addEventListener('change', function() {
        filtroData = this.value;
        resetVeiculos();
        resetMotoristas();
        resetRotas();
        if (filtroData) {
            veiculoSelect.disabled = true;
            fetch(`../api/refuel_data.php?action=get_veiculos_by_data&data=${filtroData}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.data.length > 0) {
                        veiculoSelect.innerHTML = '<option value="">Selecione um veículo</option>' +
                            res.data.map(v => `<option value="${v.id}">${v.placa} - ${v.modelo}</option>`).join('');
                        veiculoSelect.disabled = false;
                    } else {
                        veiculoSelect.innerHTML = '<option value="">Nenhum veículo encontrado</option>';
                        veiculoSelect.disabled = true;
                    }
                });
        }
    });

    // 2. Ao selecionar veículo, busca motoristas
    veiculoSelect.addEventListener('change', function() {
        filtroVeiculo = this.value;
        resetMotoristas();
        resetRotas();
        if (filtroData && filtroVeiculo) {
            motoristaSelect.disabled = true;
            fetch(`../api/refuel_data.php?action=get_motoristas_by_veiculo_data&veiculo_id=${filtroVeiculo}&data=${filtroData}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.data.length > 0) {
                        motoristaSelect.innerHTML = '<option value="">Selecione um motorista</option>' +
                            res.data.map(m => `<option value="${m.id}">${m.nome}</option>`).join('');
                        motoristaSelect.disabled = false;
                    } else {
                        motoristaSelect.innerHTML = '<option value="">Nenhum motorista encontrado</option>';
                        motoristaSelect.disabled = true;
                    }
                });
        }
    });

    // 3. Ao selecionar motorista, busca rotas
    motoristaSelect.addEventListener('change', function() {
        filtroMotorista = this.value;
        resetRotas();
        if (filtroData && filtroVeiculo && filtroMotorista) {
            rotaSelect.disabled = true;
            fetch(`../api/refuel_data.php?action=get_rotas_by_veiculo_motorista_data&veiculo_id=${filtroVeiculo}&motorista_id=${filtroMotorista}&data=${filtroData}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.data.length > 0) {
                        rotaSelect.innerHTML = '<option value="">Selecione a rota</option>' +
                            res.data.map(r => `<option value="${r.id}">${r.data_rota} - ${r.cidade_origem_nome} → ${r.cidade_destino_nome}</option>`).join('');
                        rotaSelect.disabled = false;
                    } else {
                        rotaSelect.innerHTML = '<option value="">Nenhuma rota encontrada</option>';
                        rotaSelect.disabled = true;
                    }
                });
        }
    });

    // Configurar eventos do modal
    const modal = document.getElementById('refuelModal');
    const saveBtn = document.getElementById('saveRefuelBtn');
    const cancelBtn = document.getElementById('cancelRefuelBtn');
    const closeBtn = modal.querySelector('.close-modal');

    // Fechar modal
    function closeModal() {
        modal.classList.remove('active');
        document.getElementById('refuelForm').reset();
    }

    // Salvar abastecimento
    saveBtn.addEventListener('click', function() {
        const form = document.getElementById('refuelForm');
        // Corrige campos numéricos para o formato correto
        form.litros.value = form.litros.value.replace(/\./g, '').replace(',', '.');
        form.valor_litro.value = form.valor_litro.value.replace(/\./g, '').replace(',', '.');
        form.valor_total.value = form.valor_total.value.replace(/\./g, '').replace(',', '.');
        const formData = new FormData(form);
        const refuelId = document.getElementById('refuelId').value;
        
        formData.append('action', refuelId ? 'update' : 'create');
        if (refuelId) {
            formData.append('id', refuelId);
        }

        fetch('../api/refuel_actions.php', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                closeModal();
                // Recarrega a página para garantir atualização total
                window.location.href = window.location.pathname + '?page=1';
            } else {
                throw new Error(data.error || 'Erro ao salvar abastecimento');
            }
        })
        .catch(error => {
            console.error('Error saving refuel:', error);
            alert('Erro ao salvar abastecimento: ' + error.message);
        });
    });

    // Cancelar
    cancelBtn.addEventListener('click', closeModal);
    closeBtn.addEventListener('click', closeModal);

    // Inicializa selects dependentes como desabilitados
    resetMotoristas();
    resetRotas();
});

// Função para preencher selects de edição sem disparar filtro dinâmico
async function preencherCamposEdicao(refuel) {
    // Preencher Data da Rota
    const dataRotaInput = document.getElementById('data_rota_filtro');
    dataRotaInput.value = refuel.data_rota;

    // Carregar veículos disponíveis e selecionar o correto
    const veiculoSelect = document.getElementById('veiculo_id');
    const motoristaSelect = document.getElementById('motorista_id');
    const rotaSelect = document.getElementById('rota_id');

    // Buscar veículos
    let res = await fetch(`../api/refuel_data.php?action=get_veiculos_by_data&data=${refuel.data_rota}`);
    let data = await res.json();
    if (data.success && data.data.length > 0) {
        veiculoSelect.innerHTML = '<option value="">Selecione um veículo</option>' +
            data.data.map(v => `<option value="${v.id}">${v.placa} - ${v.modelo}</option>`).join('');
        veiculoSelect.disabled = false;
        veiculoSelect.value = refuel.veiculo_id;
    }

    // Buscar motoristas
    res = await fetch(`../api/refuel_data.php?action=get_motoristas_by_veiculo_data&veiculo_id=${refuel.veiculo_id}&data=${refuel.data_rota}`);
    data = await res.json();
    if (data.success && data.data.length > 0) {
        motoristaSelect.innerHTML = '<option value="">Selecione um motorista</option>' +
            data.data.map(m => `<option value="${m.id}">${m.nome}</option>`).join('');
        motoristaSelect.disabled = false;
        motoristaSelect.value = refuel.motorista_id;
    }

    // Buscar rotas
    res = await fetch(`../api/refuel_data.php?action=get_rotas_by_veiculo_motorista_data&veiculo_id=${refuel.veiculo_id}&motorista_id=${refuel.motorista_id}&data=${refuel.data_rota}`);
    data = await res.json();
    if (data.success && data.data.length > 0) {
        rotaSelect.innerHTML = '<option value="">Selecione a rota</option>' +
            data.data.map(r => `<option value="${r.id}">${r.data_rota} - ${r.cidade_origem_nome} → ${r.cidade_destino_nome}</option>`).join('');
        rotaSelect.disabled = false;
        rotaSelect.value = refuel.rota_id;
    }
}

// Função para abrir o modal de edição de abastecimento
window.openEditRefuelModal = function(refuel) {
    // Preencher campos básicos
    document.getElementById('refuelId').value = refuel.id;
    document.getElementById('data_abastecimento').value = refuel.data_abastecimento.replace(' ', 'T');
    document.getElementById('tipo_combustivel').value = refuel.tipo_combustivel;
    document.getElementById('litros').value = Number(refuel.litros).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('valor_litro').value = Number(refuel.valor_litro).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('valor_total').value = Number(refuel.valor_total).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('km_atual').value = refuel.km_atual;
    document.getElementById('posto').value = refuel.posto;
    document.getElementById('forma_pagamento').value = refuel.forma_pagamento;
    document.getElementById('observacoes').value = refuel.observacoes || '';

    // Preencher select de veículo apenas com o valor do banco
    const veiculoSelect = document.getElementById('veiculo_id');
    veiculoSelect.innerHTML = '';
    if (refuel.veiculo_id) {
        const option = document.createElement('option');
        option.value = refuel.veiculo_id;
        option.textContent = refuel.veiculo_placa ? refuel.veiculo_placa : `Veículo #${refuel.veiculo_id}`;
        veiculoSelect.appendChild(option);
        veiculoSelect.value = refuel.veiculo_id;
    }

    // Preencher select de motorista apenas com o valor do banco
    const motoristaSelect = document.getElementById('motorista_id');
    motoristaSelect.innerHTML = '';
    if (refuel.motorista_id) {
        const option = document.createElement('option');
        option.value = refuel.motorista_id;
        option.textContent = refuel.motorista_nome ? refuel.motorista_nome : `Motorista #${refuel.motorista_id}`;
        motoristaSelect.appendChild(option);
        motoristaSelect.value = refuel.motorista_id;
    }

    // Preencher select de rota apenas com a rota do banco
    const rotaSelect = document.getElementById('rota_id');
    rotaSelect.innerHTML = '';
    if (refuel.rota_id) {
        const option = document.createElement('option');
        option.value = refuel.rota_id;
        if (refuel.data_rota && refuel.cidade_origem_nome && refuel.cidade_destino_nome) {
            option.textContent = `${refuel.data_rota.split('T')[0]} - ${refuel.cidade_origem_nome} → ${refuel.cidade_destino_nome}`;
        } else {
            option.textContent = 'Rota não encontrada';
        }
        rotaSelect.appendChild(option);
        rotaSelect.value = refuel.rota_id;
    }

    // Só buscar opções ao alterar data da rota
    document.getElementById('data_rota_filtro').addEventListener('change', function() {
        const data = this.value;
        if (!data) return;
        // Buscar veículos disponíveis para a data
        fetch(`../api/refuel_data.php?action=get_veiculos_by_data&data=${data}`)
            .then(response => response.json())
            .then(dataV => {
                veiculoSelect.innerHTML = '';
                dataV.data.forEach(v => {
                    const option = document.createElement('option');
                    option.value = v.id;
                    option.textContent = v.placa;
                    veiculoSelect.appendChild(option);
                });
            });
        // Buscar motoristas disponíveis para a data e veículo selecionado
        setTimeout(() => {
            const veiculoId = veiculoSelect.value;
            fetch(`../api/refuel_data.php?action=get_motoristas_by_veiculo_data&veiculo_id=${veiculoId}&data=${data}`)
                .then(response => response.json())
                .then(dataM => {
                    motoristaSelect.innerHTML = '';
                    dataM.data.forEach(m => {
                        const option = document.createElement('option');
                        option.value = m.id;
                        option.textContent = m.nome;
                        motoristaSelect.appendChild(option);
                    });
                });
        }, 200);
        // Buscar rotas disponíveis para a data, veículo e motorista selecionados
        setTimeout(() => {
            const veiculoId = veiculoSelect.value;
            const motoristaId = motoristaSelect.value;
            fetch(`../api/refuel_data.php?action=get_rotas_by_veiculo_motorista_data&veiculo_id=${veiculoId}&motorista_id=${motoristaId}&data=${data}`)
                .then(response => response.json())
                .then(dataR => {
                    rotaSelect.innerHTML = '';
                    dataR.data.forEach(r => {
                        const option = document.createElement('option');
                        option.value = r.id;
                        option.textContent = `${r.data_rota} - ${r.cidade_origem_nome} → ${r.cidade_destino_nome}`;
                        rotaSelect.appendChild(option);
                    });
                });
        }, 400);
    });

    // Exibe o modal
    document.getElementById('refuelModal').classList.add('active');
}

function loadRefuelingData() {
    // Obtém valores dos filtros
    const search = document.getElementById('searchRefueling').value;
    const currentPage = new URLSearchParams(window.location.search).get('page') || 1;
    const vehicleFilter = document.getElementById('vehicleFilter').value;
    const driverFilter = document.getElementById('driverFilter').value;
    const fuelFilter = document.getElementById('fuelFilter').value;
    const paymentFilter = document.getElementById('paymentFilter').value;
    
    // Constrói URL com filtros e paginação
    let url = `../api/refuel_data.php?action=list&page=${currentPage}&limit=5`;
    if (search) url += `&search=${encodeURIComponent(search)}`;
    if (currentFilter) {
        const [year, month] = currentFilter.split('-');
        url += `&year=${year}&month=${month}`;
    }
    if (vehicleFilter) url += `&veiculo=${encodeURIComponent(vehicleFilter)}`;
    if (driverFilter) url += `&motorista=${encodeURIComponent(driverFilter)}`;
    if (fuelFilter) url += `&combustivel=${encodeURIComponent(fuelFilter)}`;
    if (paymentFilter) url += `&pagamento=${encodeURIComponent(paymentFilter)}`;
    
    console.log('Carregando dados dos abastecimentos:', url);
    
    // Carrega dados dos abastecimentos
    fetch(url)
        .then(response => response.json())
        .then(data => {
            console.log('Resposta do backend:', data);
            if (data.success) {
                updateRefuelingsTable(data.data);
                updatePagination(data.pagination);
            } else {
                throw new Error(data.error || 'Erro ao carregar dados dos abastecimentos');
            }
        })
        .catch(error => {
            console.error('Error loading refueling data:', error);
            alert('Erro ao carregar dados dos abastecimentos: ' + error.message);
        });
}

function updateRefuelingsTable(refuelings) {
    console.log('Atualizando tabela com:', refuelings);
    const tbody = document.querySelector('.data-table tbody');
    tbody.innerHTML = '';
    
    if (refuelings && refuelings.length > 0) {
        refuelings.forEach(refuel => {
            console.log('Dados da rota para abastecimento:', {
                id: refuel.id,
                origem: refuel.cidade_origem_nome,
                destino: refuel.cidade_destino_nome,
                rota_id: refuel.rota_id
            });
            
            const row = document.createElement('tr');
            const rotaInfo = (refuel.cidade_origem_nome && refuel.cidade_destino_nome) 
                ? `${refuel.cidade_origem_nome} → ${refuel.cidade_destino_nome}`
                : '-';
                
            row.innerHTML = `
                <td>${formatDate(refuel.data_abastecimento)}</td>
                <td>${refuel.veiculo_placa || '-'}</td>
                <td>${refuel.motorista_nome || '-'}</td>
                <td>${refuel.posto || '-'}</td>
                <td>${formatNumber(refuel.litros, 1)} L</td>
                <td>R$ ${formatNumber(refuel.valor_litro, 2)}</td>
                <td>R$ ${formatNumber(refuel.valor_total, 2)}</td>
                <td>${formatNumber(refuel.km_atual, 0)}</td>
                <td>${refuel.forma_pagamento || '-'}</td>
                <td>${rotaInfo}</td>
                <td class="actions">
                    <button class="btn-icon edit-btn" data-id="${refuel.id}" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    ${refuel.comprovante ? `
                        <button class="btn-icon view-comprovante-btn" data-comprovante="${refuel.comprovante}" title="Ver Comprovante">
                            <i class="fas fa-file-alt"></i>
                        </button>
                    ` : ''}
                    <button class="btn-icon delete-btn" data-id="${refuel.id}" title="Excluir">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
        
        // Configura eventos dos botões
        setupTableButtons();
    } else {
        tbody.innerHTML = '<tr><td colspan="11" class="text-center">Nenhum abastecimento encontrado</td></tr>';
    }
    console.log('Tabela atualizada!');
}

function setupValorTotalCalc() {
    const litrosInput = document.getElementById('litros');
    const valorLitroInput = document.getElementById('valor_litro');
    const valorTotalInput = document.getElementById('valor_total');

    function formatarNumero(valor) {
        // Remove pontos e troca vírgula por ponto para cálculo
        return parseFloat(valor.toString().replace(/\./g, '').replace(',', '.')) || 0;
    }

    function formatarExibicao(valor) {
        // Formata o número para exibição com duas casas decimais
        return valor.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function calcularValorTotal() {
        const litros = formatarNumero(litrosInput.value);
        const valorLitro = formatarNumero(valorLitroInput.value);
        const valorTotal = litros * valorLitro;

        if (!isNaN(valorTotal)) {
            valorTotalInput.value = formatarExibicao(valorTotal);
        }
    }

    function calcularValorLitro() {
        const litros = formatarNumero(litrosInput.value);
        const valorTotal = formatarNumero(valorTotalInput.value);
        
        if (litros > 0) {
            const valorLitro = valorTotal / litros;
            if (!isNaN(valorLitro)) {
                valorLitroInput.value = formatarExibicao(valorLitro);
        }
    }
    }

    // Remove eventos anteriores para evitar duplicação
    litrosInput.removeEventListener('input', calcularValorTotal);
    valorLitroInput.removeEventListener('input', calcularValorTotal);
    litrosInput.removeEventListener('input', calcularValorLitro);
    valorTotalInput.removeEventListener('input', calcularValorLitro);

    // Adiciona eventos para cálculo do valor total
    litrosInput.addEventListener('input', calcularValorTotal);
    valorLitroInput.addEventListener('input', calcularValorTotal);

    // Adiciona eventos para cálculo do valor por litro
    litrosInput.addEventListener('input', calcularValorLitro);
    valorTotalInput.addEventListener('input', calcularValorLitro);

    // Formata os valores iniciais se existirem
    if (litrosInput.value) {
        litrosInput.value = formatarExibicao(formatarNumero(litrosInput.value));
    }
    if (valorLitroInput.value) {
        valorLitroInput.value = formatarExibicao(formatarNumero(valorLitroInput.value));
    }
    if (valorTotalInput.value) {
        valorTotalInput.value = formatarExibicao(formatarNumero(valorTotalInput.value));
    }

    // Dispara os cálculos iniciais se houver valores
    if (litrosInput.value && (valorLitroInput.value || valorTotalInput.value)) {
        if (valorLitroInput.value) {
            calcularValorTotal();
        } else {
            calcularValorLitro();
        }
    }
}

function showEditRefuelModal(id) {
    // Limpa o formulário
    document.getElementById('refuelForm').reset();
    
    // Busca os dados do abastecimento
    fetch(`../api/refuel_data.php?action=get&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const refuel = data.data;
                
                // Preenche o formulário
                document.getElementById('refuelId').value = refuel.id;
                document.getElementById('data_rota_filtro').value = refuel.data_rota ? refuel.data_rota.split('T')[0] : '';
                document.getElementById('data_abastecimento').value = refuel.data_abastecimento.replace(' ', 'T');
                
                // Preencher select de veículo apenas com o valor do banco
                const veiculoSelect = document.getElementById('veiculo_id');
                veiculoSelect.innerHTML = '';
                if (refuel.veiculo_id) {
                    const option = document.createElement('option');
                    option.value = refuel.veiculo_id;
                    option.textContent = refuel.veiculo_placa ? `${refuel.veiculo_placa} - ${refuel.veiculo_modelo || ''}` : `Veículo #${refuel.veiculo_id}`;
                    veiculoSelect.appendChild(option);
                    veiculoSelect.value = refuel.veiculo_id;
                }

                // Preencher select de motorista apenas com o valor do banco
                const motoristaSelect = document.getElementById('motorista_id');
                motoristaSelect.innerHTML = '';
                if (refuel.motorista_id) {
                    const option = document.createElement('option');
                    option.value = refuel.motorista_id;
                    option.textContent = refuel.motorista_nome || `Motorista #${refuel.motorista_id}`;
                    motoristaSelect.appendChild(option);
                    motoristaSelect.value = refuel.motorista_id;
                }

                // Preencher select de rota apenas com o valor do banco
                const rotaSelect = document.getElementById('rota_id');
                rotaSelect.innerHTML = '';
                if (refuel.rota_id) {
                    const option = document.createElement('option');
                    option.value = refuel.rota_id;
                    option.textContent = refuel.cidade_origem_nome && refuel.cidade_destino_nome ? 
                        `${refuel.cidade_origem_nome} → ${refuel.cidade_destino_nome}` : 
                        `Rota #${refuel.rota_id}`;
                    rotaSelect.appendChild(option);
                    rotaSelect.value = refuel.rota_id;
                }
                
                document.getElementById('tipo_combustivel').value = refuel.tipo_combustivel;
                
                // Formata os valores numéricos corretamente
                document.getElementById('litros').value = Number(refuel.litros).toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                document.getElementById('valor_litro').value = Number(refuel.valor_litro).toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                document.getElementById('valor_total').value = Number(refuel.valor_total).toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                
                document.getElementById('km_atual').value = refuel.km_atual;
                document.getElementById('posto').value = refuel.posto;
                document.getElementById('forma_pagamento').value = refuel.forma_pagamento;
                document.getElementById('observacoes').value = refuel.observacoes || '';
                
                // Exibe o comprovante atual se existir
                const comprovanteAtual = document.getElementById('comprovante_atual');
                if (refuel.comprovante) {
                    comprovanteAtual.innerHTML = `
                        <div class="current-file">
                            <i class="fas fa-file-alt"></i>
                            <a href="../${refuel.comprovante}" target="_blank">Ver comprovante atual</a>
                        </div>
                    `;
                } else {
                    comprovanteAtual.innerHTML = '';
                }
                
                // Atualiza o título do modal
                document.getElementById('modalTitle').textContent = 'Editar Abastecimento';
                
                // Configura os cálculos automáticos
                setupValorTotalCalc();
                
                // Exibe o modal
                document.getElementById('refuelModal').classList.add('active');
            } else {
                showError(data.error || 'Erro ao carregar dados do abastecimento');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showError('Erro ao carregar dados do abastecimento');
        });
}

function handleRefuelSubmit(e) {
    e.preventDefault();
    
    const form = document.getElementById('refuelForm');
    const formData = new FormData(form);
    
    // Adiciona o ID se estiver editando
    const refuelId = document.getElementById('refuelId').value;
    if (refuelId) {
        formData.append('id', refuelId);
    }
    
    // Adiciona a ação
    formData.append('action', refuelId ? 'edit' : 'add');
    
    // Envia o formulário
    fetch('../api/refuel_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.message);
            closeAllModals();
            loadRefuelingData();
            loadRefuelingSummary();
            loadConsumptionChart();
            loadEfficiencyChart();
        } else {
            showError(data.error || 'Erro ao salvar abastecimento');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        showError('Erro ao salvar abastecimento');
    });
}

function updateRefuelTable(refuels) {
    const tbody = document.querySelector('#refuelTable tbody');
    tbody.innerHTML = '';
    
    refuels.forEach(refuel => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${formatDate(refuel.data_abastecimento)}</td>
            <td>${refuel.veiculo_placa || '-'}</td>
            <td>${refuel.motorista_nome || '-'}</td>
            <td>${refuel.posto || '-'}</td>
            <td>${refuel.tipo_combustivel || '-'}</td>
            <td>${formatNumber(refuel.litros)}</td>
            <td>R$ ${formatCurrency(refuel.valor_litro)}</td>
            <td>R$ ${formatCurrency(refuel.valor_total)}</td>
            <td>${formatNumber(refuel.km_atual)}</td>
            <td>${refuel.forma_pagamento || '-'}</td>
            <td>${refuel.rota_id || '-'}</td>
            <td class="actions">
                <button class="btn-icon edit-btn" data-id="${refuel.id}" title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
                ${refuel.comprovante ? `
                    <button class="btn-icon view-comprovante-btn" data-comprovante="${refuel.comprovante}" title="Ver Comprovante">
                        <i class="fas fa-file-alt"></i>
                    </button>
                ` : ''}
                <button class="btn-icon delete-btn" data-id="${refuel.id}" title="Excluir">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    
    // Reattach event listeners
    setupTableButtons();
}

function setupTableButtons() {
    // Setup edit buttons
    document.querySelectorAll('.btn-icon.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            showEditRefuelModal(id);
        });
    });
    
    // Setup delete buttons
    document.querySelectorAll('.btn-icon.delete-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            showDeleteConfirmation(id);
        });
    });

    // Setup view comprovante buttons
    document.querySelectorAll('.btn-icon.view-comprovante-btn').forEach(button => {
        button.addEventListener('click', function() {
            const comprovante = this.getAttribute('data-comprovante');
            if (comprovante) {
                window.open('../' + comprovante, '_blank');
            }
        });
    });
}

function loadConsumptionChart() {
    let url = '../api/refuel_data.php?action=consumption_chart';
    
    if (currentFilter) {
        const [year, month] = currentFilter.split('-');
        url += `&year=${year}&month=${month}`;
    }

    return fetch(url)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Erro ao carregar dados do gráfico de consumo');
            }

            if (consumptionChart) {
                consumptionChart.destroy();
            }

            const ctx = document.getElementById('fuelConsumptionChart').getContext('2d');
            
            // Cores diferentes para cada mês
            const backgroundColors = [
                'rgba(54, 162, 235, 0.2)',
                'rgba(255, 99, 132, 0.2)',
                'rgba(75, 192, 192, 0.2)',
                'rgba(255, 206, 86, 0.2)',
                'rgba(153, 102, 255, 0.2)',
                'rgba(255, 159, 64, 0.2)'
            ];
            
            const borderColors = [
                'rgba(54, 162, 235, 1)',
                'rgba(255, 99, 132, 1)',
                'rgba(75, 192, 192, 1)',
                'rgba(255, 206, 86, 1)',
                'rgba(153, 102, 255, 1)',
                'rgba(255, 159, 64, 1)'
            ];

            consumptionChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Consumo de Combustível (L)',
                        data: data.values,
                        backgroundColor: backgroundColors,
                        borderColor: borderColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Litros'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Mês'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Consumo: ${context.raw} L`;
                                }
                            }
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error loading consumption chart:', error);
        });
}

function loadEfficiencyChart() {
    let url = '../api/rendimento_veiculo.php';
    
    if (currentFilter) {
        const [year, month] = currentFilter.split('-');
        url += `?year=${year}&month=${month}`;
    } else {
        // Se não houver filtro, usa o mês atual
        const today = new Date();
        const year = today.getFullYear();
        const month = today.getMonth() + 1;
        url += `?year=${year}&month=${month}`;
    }

    return fetch(url)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Erro ao carregar dados do gráfico de eficiência');
            }

            if (efficiencyChart) {
                efficiencyChart.destroy();
            }

            const ctx = document.getElementById('fuelEfficiencyChart').getContext('2d');
            
            // Cores diferentes para cada veículo
            const backgroundColors = data.labels.map((_, index) => {
                const hue = (index * 137.5) % 360; // Usa o número áureo para distribuir as cores
                return `hsla(${hue}, 70%, 50%, 0.2)`;
            });
            
            const borderColors = data.labels.map((_, index) => {
                const hue = (index * 137.5) % 360;
                return `hsla(${hue}, 70%, 50%, 1)`;
            });

            efficiencyChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Eficiência (km/L)',
                        data: data.values,
                        backgroundColor: backgroundColors,
                        borderColor: borderColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'km/L'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Veículo'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Eficiência: ${context.raw} km/L`;
                                }
                            }
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error loading efficiency chart:', error);
        });
}

function loadAnomaliesChart() {
    let url = '../api/refuel_data.php?action=anomalies_chart';
    
    if (currentFilter) {
        const [year, month] = currentFilter.split('-');
        url += `&year=${year}&month=${month}`;
    }

    // Destruir o gráfico existente se houver
    const existingChart = Chart.getChart('anomaliesChart');
    if (existingChart) {
        existingChart.destroy();
    }

    return fetch(url)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Erro ao carregar dados do gráfico de anomalias');
            }

            const ctx = document.getElementById('anomaliesChart').getContext('2d');
            
            // Calcula a média de valor por litro
            const valores = data.data.map(item => parseFloat(item.valor_litro));
            const mediaValor = valores.reduce((a, b) => a + b, 0) / valores.length;
            
            // Calcula o desvio padrão
            const desvioPadrao = Math.sqrt(
                valores.reduce((sq, n) => sq + Math.pow(n - mediaValor, 2), 0) / valores.length
            );

            new Chart(ctx, {
                type: 'scatter',
                data: {
                    datasets: [
                        {
                            label: 'Abastecimentos',
                            data: data.data.map(item => ({
                                x: parseFloat(item.litros),
                                y: parseFloat(item.valor_litro)
                            })),
                            backgroundColor: 'rgba(54, 162, 235, 0.5)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1,
                            pointRadius: 6,
                            pointHoverRadius: 8
                        },
                        {
                            label: 'Média',
                            data: [
                                { x: 0, y: mediaValor },
                                { x: Math.max(...data.data.map(item => parseFloat(item.litros))), y: mediaValor }
                            ],
                            type: 'line',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 2,
                            pointRadius: 0,
                            fill: false
                        },
                        {
                            label: 'Limite Superior',
                            data: [
                                { x: 0, y: mediaValor + desvioPadrao },
                                { x: Math.max(...data.data.map(item => parseFloat(item.litros))), y: mediaValor + desvioPadrao }
                            ],
                            type: 'line',
                            borderColor: 'rgba(255, 99, 132, 0.5)',
                            borderWidth: 1,
                            borderDash: [5, 5],
                            pointRadius: 0,
                            fill: false
                        },
                        {
                            label: 'Limite Inferior',
                            data: [
                                { x: 0, y: mediaValor - desvioPadrao },
                                { x: Math.max(...data.data.map(item => parseFloat(item.litros))), y: mediaValor - desvioPadrao }
                            ],
                            type: 'line',
                            borderColor: 'rgba(255, 99, 132, 0.5)',
                            borderWidth: 1,
                            borderDash: [5, 5],
                            pointRadius: 0,
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Litros'
                            },
                            min: 0
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Valor por Litro (R$)'
                            },
                            min: 0
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    if (context.dataset.label === 'Abastecimentos') {
                                        return `Litros: ${context.raw.x}L, Valor: R$ ${context.raw.y}`;
                                    }
                                    return context.dataset.label;
                                }
                            }
                        },
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true
                            }
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error loading anomalies chart:', error);
        });
}

function loadDriverConsumptionChart() {
    let url = '../api/refuel_data.php?action=driver_consumption_chart';
    
    if (currentFilter) {
        const [year, month] = currentFilter.split('-');
        url += `&year=${year}&month=${month}`;
    }

    // Destruir o gráfico existente se houver
    const existingChart = Chart.getChart('driverConsumptionChart');
    if (existingChart) {
        existingChart.destroy();
    }

    return fetch(url)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Erro ao carregar dados do gráfico de consumo por motorista');
            }

            const ctx = document.getElementById('driverConsumptionChart').getContext('2d');
            
            // Gera cores únicas para cada motorista
            const generateColors = (count) => {
                const colors = [];
                for (let i = 0; i < count; i++) {
                    const hue = (i * 137.5) % 360; // Usa o número áureo para distribuir as cores
                    colors.push({
                        background: `hsla(${hue}, 70%, 50%, 0.5)`,
                        border: `hsla(${hue}, 70%, 50%, 1)`
                    });
                }
                return colors;
            };

            const colors = generateColors(data.data.length);
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.data.map(item => item.motorista),
                    datasets: [{
                        label: 'Consumo por Motorista (L)',
                        data: data.data.map(item => parseFloat(item.total_litros)),
                        backgroundColor: colors.map(c => c.background),
                        borderColor: colors.map(c => c.border),
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Litros'
                            },
                            beginAtZero: true
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Motorista'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Consumo: ${context.raw.toLocaleString('pt-BR', {minimumFractionDigits: 1})}L`;
                                }
                            }
                        },
                        legend: {
                            display: false // Remove a legenda pois cada barra já tem sua cor única
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error loading driver consumption chart:', error);
        });
}

function loadVehicleEfficiencyChart() {
    let url = '../api/refuel_data.php?action=vehicle_efficiency_chart';
    
    if (currentFilter) {
        const [year, month] = currentFilter.split('-');
        url += `&year=${year}&month=${month}`;
    }

    // Destruir o gráfico existente se houver
    const existingChart = Chart.getChart('vehicleEfficiencyChart');
    if (existingChart) {
        existingChart.destroy();
    }

    return fetch(url)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Erro ao carregar dados do gráfico de eficiência por veículo');
            }

            const ctx = document.getElementById('vehicleEfficiencyChart').getContext('2d');
            
            // Gera cores únicas para cada veículo
            const generateColors = (count) => {
                const colors = [];
                for (let i = 0; i < count; i++) {
                    const hue = (i * 137.5) % 360; // Usa o número áureo para distribuir as cores
                    colors.push({
                        background: `hsla(${hue}, 70%, 50%, 0.5)`,
                        border: `hsla(${hue}, 70%, 50%, 1)`
                    });
                }
                return colors;
            };

            const colors = generateColors(data.data.length);
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.data.map(item => item.veiculo),
                    datasets: [{
                        label: 'Custo por KM (R$/km)',
                        data: data.data.map(item => parseFloat(item.custo_por_km)),
                        backgroundColor: colors.map(c => c.background),
                        borderColor: colors.map(c => c.border),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'R$/km'
                            },
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                                }
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Veículo'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Custo: R$ ${context.raw.toLocaleString('pt-BR', {minimumFractionDigits: 2})}/km`;
                                }
                            }
                        },
                        legend: {
                            display: false // Remove a legenda pois cada barra já tem sua cor única
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error loading vehicle efficiency chart:', error);
        });
}

function loadMonthlyCostChart() {
    let url = '../api/refuel_data.php?action=monthly_cost_chart';
    
    if (currentFilter) {
        const [year, month] = currentFilter.split('-');
        url += `&year=${year}&month=${month}`;
    }

    return fetch(url)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Erro ao carregar dados do gráfico de custo mensal');
            }

            const ctx = document.getElementById('monthlyCostChart').getContext('2d');
            
            // Calcula a média móvel de 3 meses
            const valores = data.data.map(item => parseFloat(item.total_gasto));
            const mediaMovel = [];
            for (let i = 0; i < valores.length; i++) {
                const inicio = Math.max(0, i - 2);
                const media = valores.slice(inicio, i + 1).reduce((a, b) => a + b, 0) / (i - inicio + 1);
                mediaMovel.push(media);
            }

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.data.map(item => {
                        const [year, month] = item.mes.split('-');
                        return `${month}/${year}`;
                    }),
                    datasets: [
                        {
                            label: 'Custo Mensal (R$)',
                            data: data.data.map(item => parseFloat(item.total_gasto)),
                            backgroundColor: 'rgba(255, 159, 64, 0.5)',
                            borderColor: 'rgba(255, 159, 64, 1)',
                            borderWidth: 1,
                            type: 'bar'
                        },
                        {
                            label: 'Média Móvel (3 meses)',
                            data: mediaMovel,
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 2,
                            fill: false,
                            type: 'line',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Valor (R$)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                }
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Mês'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const value = context.raw;
                                    if (context.dataset.label === 'Custo Mensal (R$)') {
                                        return `Custo: R$ ${value.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
                                    } else {
                                        return `Média: R$ ${value.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
                                    }
                                }
                            }
                        },
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true
                            }
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error loading monthly cost chart:', error);
        });
}

function setupFilters() {
    // Set default value to current month/year
    const today = new Date();
    const defaultDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;
    document.getElementById('filterMonth').value = defaultDate;
    currentFilter = defaultDate;
    updateFilterButtonState();

    // Setup filter modal buttons
    document.getElementById('applyFilterBtn').addEventListener('click', () => {
        const filterMonth = document.getElementById('filterMonth').value;
        currentFilter = filterMonth;
        
        // Atualiza todos os dados e gráficos
        Promise.all([
            loadRefuelingData(),
            loadRefuelingSummary(),
            loadConsumptionChart(),
            loadEfficiencyChart(),
            loadAnomaliesChart(),
            loadDriverConsumptionChart(),
            loadVehicleEfficiencyChart(),
            loadMonthlyCostChart()
        ]).catch(error => {
            console.error('Erro ao atualizar dados com filtro:', error);
        });
        
        closeAllModals();
        updateFilterButtonState();
    });

    document.getElementById('clearFilterBtn').addEventListener('click', () => {
        document.getElementById('filterMonth').value = '';
        currentFilter = null;
        
        // Atualiza todos os dados e gráficos
        Promise.all([
            loadRefuelingData(),
            loadRefuelingSummary(),
            loadConsumptionChart(),
            loadEfficiencyChart(),
            loadAnomaliesChart(),
            loadDriverConsumptionChart(),
            loadVehicleEfficiencyChart(),
            loadMonthlyCostChart()
        ]).catch(error => {
            console.error('Erro ao atualizar dados sem filtro:', error);
        });
        
        closeAllModals();
        updateFilterButtonState();
    });
}

// Atualiza a função initializePage para garantir que os gráficos sejam carregados corretamente
function initializePage() {
    // Load refuel data from API
    loadRefuelingData();
    
    // Load summary data
    loadRefuelingSummary();
    
    // Load all charts
    Promise.all([
        loadConsumptionChart(),
        loadEfficiencyChart(),
        loadAnomaliesChart(),
        loadDriverConsumptionChart(),
        loadVehicleEfficiencyChart(),
        loadMonthlyCostChart()
    ]).catch(error => {
        console.error('Erro ao carregar gráficos iniciais:', error);
    });
    
    // Setup button events
    document.getElementById('addRefuelBtn').addEventListener('click', showAddRefuelModal);
    document.getElementById('filterBtn').addEventListener('click', showFilterModal);
    document.getElementById('helpBtn').addEventListener('click', showHelpModal);
    
    // Setup search
    const searchInput = document.getElementById('searchRefueling');
    searchInput.addEventListener('input', debounce(() => {
        loadRefuelingData();
    }, 300));
    
    // Setup table buttons
    setupTableButtons();
}

function carregarMotoristas(veiculoId, data) {
    return fetch(`../api/refuel_data.php?action=get_motoristas_by_veiculo_data&veiculo_id=${veiculoId}&data=${data}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                return data.data;
            } else {
                throw new Error(data.error || 'Erro ao carregar motoristas');
            }
        });
}

function carregarFormasPagamento() {
    fetch('../api/formas_pagamento.php?action=list')
        .then(response => response.json())
        .then(data => {
            // data é um array simples
            const select = document.getElementById('editFormaPagamento');
            select.innerHTML = '<option value="">Selecione a Forma de Pagamento</option>';
            data.forEach(forma => {
                const option = document.createElement('option');
                option.value = forma.nome;
                option.textContent = forma.nome;
                select.appendChild(option);
            });
        });
}

function carregarRotasPorAbastecimento(refuel) {
    return fetch(`../api/refuel_data.php?action=get_rotas_by_veiculo_motorista_data&veiculo_id=${refuel.veiculo_id}&motorista_id=${refuel.motorista_id}&data=${refuel.data_rota}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                return data.data;
            } else {
                throw new Error(data.error || 'Erro ao carregar rotas');
            }
        });
} 