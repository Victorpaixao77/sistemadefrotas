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

    // Charts: only init here if page did NOT already call initializeMaintenanceCharts(data).
    // manutencoes.php fetches once and calls initializeMaintenanceCharts(data), so we do NOT
    // call debouncedInitializeCharts() here to avoid double init and page "tremida".
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
                var anexosSec = document.getElementById('anexosSection');
                if (anexosSec) anexosSec.style.display = 'none';
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
    // Mostrar/ocultar data conclusão e checklist quando status mudar
    const statusSel = document.getElementById('status_manutencao_id');
    if (statusSel) {
        statusSel.addEventListener('change', toggleConclusaoFields);
    }
    // Upload anexo
    const uploadAnexoBtn = document.getElementById('uploadAnexoBtn');
    if (uploadAnexoBtn) {
        uploadAnexoBtn.addEventListener('click', uploadAnexo);
    }
    // Deletar anexo (delegado no modal)
    const maintenanceModal = document.getElementById('maintenanceModal');
    if (maintenanceModal) {
        maintenanceModal.addEventListener('click', function(e) {
            var del = e.target.closest('.delete-anexo-btn');
            if (!del) return;
            e.preventDefault();
            var id = del.getAttribute('data-id');
            if (!id || !confirm('Excluir este anexo?')) return;
            var fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            fetch('../api/manutencao_anexos.php', { method: 'POST', credentials: 'include', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        var mid = document.getElementById('manutencaoId') && document.getElementById('manutencaoId').value;
                        if (mid) loadAnexos(parseInt(mid, 10));
                    } else {
                        alert(data.error || 'Erro ao excluir.');
                    }
                });
        });
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

// Delegação: botões da tabela funcionam mesmo após trocar de página (AJAX)
function setupTableButtons() {
    const table = document.getElementById('maintenanceTable');
    if (!table) return;
    table.addEventListener('click', function(e) {
        const btn = e.target.closest('.view-btn');
        if (btn) { e.preventDefault(); viewMaintenance(btn.dataset.id); return; }
        const editBtn = e.target.closest('.edit-btn');
        if (editBtn) { e.preventDefault(); editMaintenance(editBtn.dataset.id); return; }
        const delBtn = e.target.closest('.delete-btn');
        if (delBtn) { e.preventDefault(); showDeleteConfirmation(delBtn.dataset.id); return; }
        const histBtn = e.target.closest('.historico-veiculo-btn');
        if (histBtn) {
            e.preventDefault();
            const veiculoId = histBtn.dataset.veiculoId;
            const placa = histBtn.dataset.placa || '';
            if (!veiculoId) return;
            fetch('../api/manutencoes.php?veiculo_id=' + veiculoId, { credentials: 'include' })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    const list = data.data || [];
                    const total = data.total_custo_veiculo || 0;
                    const custo12m = data.custo_12m != null ? data.custo_12m : total;
                    const prev = data.total_preventivas != null ? data.total_preventivas : 0;
                    const corr = data.total_corretivas != null ? data.total_corretivas : 0;
                    document.getElementById('historicoVeiculoTitulo').textContent = 'Relatório - ' + (placa || 'Veículo');
                    document.getElementById('historicoVeiculoCusto').textContent = list.length + ' manutenção(ões) no histórico.';
                    document.getElementById('historicoVeiculoTotal').textContent = 'R$ ' + (total).toFixed(2).replace('.', ',');
                    document.getElementById('historicoVeiculoCusto12m').textContent = 'R$ ' + (custo12m).toFixed(2).replace('.', ',');
                    document.getElementById('historicoVeiculoPreventivas').textContent = prev;
                    document.getElementById('historicoVeiculoCorretivas').textContent = corr;
                    const tbody = document.getElementById('historicoVeiculoBody');
                    tbody.innerHTML = list.map(m => '<tr><td>' + (m.data_manutencao ? m.data_manutencao.split('-').reverse().join('/') : '') + '</td><td>' + (m.tipo_nome || '') + '</td><td>' + (m.descricao || '').substring(0, 40) + '</td><td>R$ ' + (parseFloat(m.valor) || 0).toFixed(2).replace('.', ',') + '</td></tr>').join('');
                    document.getElementById('historicoVeiculoModal').classList.add('active');
                });
        }
    });
    setupPaginationAjax();
}

function getPaginationParams() {
    const form = document.getElementById('formFiltroPeriodo');
    if (!form) return {};
    const d = new FormData(form);
    return {
        page: 1,
        per_page: d.get('per_page') || '10',
        data_inicio: d.get('data_inicio') || '',
        data_fim: d.get('data_fim') || '',
        order: d.get('order') || 'data_manutencao',
        dir: d.get('dir') || 'DESC'
    };
}

function buildListUrl(page) {
    const p = getPaginationParams();
    const params = new URLSearchParams();
    params.set('list', '1');
    params.set('page', String(page));
    params.set('per_page', p.per_page);
    if (p.data_inicio) params.set('data_inicio', p.data_inicio);
    if (p.data_fim) params.set('data_fim', p.data_fim);
    params.set('order', p.order);
    params.set('dir', p.dir);
    return '../api/manutencoes.php?' + params.toString();
}

function buildPageUrl(page) {
    const form = document.getElementById('formFiltroPeriodo');
    if (!form) return '?page=' + page;
    const d = new FormData(form);
    const params = new URLSearchParams();
    params.set('page', String(page));
    params.set('per_page', d.get('per_page') || '10');
    if (d.get('data_inicio')) params.set('data_inicio', d.get('data_inicio'));
    if (d.get('data_fim')) params.set('data_fim', d.get('data_fim'));
    params.set('order', d.get('order') || 'data_manutencao');
    params.set('dir', d.get('dir') || 'DESC');
    return '?' + params.toString();
}

function rowHtml(m) {
    const data = m.data_manutencao ? m.data_manutencao.split('-').reverse().join('/') : '';
    const placa = (m.veiculo_placa || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const tipo = (m.tipo_nome || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const desc = (m.descricao || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').substring(0, 200);
    const forn = (m.fornecedor || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const status = (m.status_nome || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const valor = (parseFloat(m.valor) || 0).toFixed(2).replace('.', ',');
    const custo12m = (parseFloat(m.custo_veiculo_12m) || 0).toFixed(2).replace('.', ',');
    const vid = parseInt(m.veiculo_id, 10) || 0;
    return '<tr>' +
        '<td>' + data + '</td>' +
        '<td>' + placa + '</td>' +
        '<td>' + tipo + '</td>' +
        '<td>' + desc + '</td>' +
        '<td>' + forn + '</td>' +
        '<td>' + status + '</td>' +
        '<td>R$ ' + valor + '</td>' +
        '<td title="Custo do veículo nos últimos 12 meses">R$ ' + custo12m + '</td>' +
        '<td class="actions">' +
        '<button class="btn-icon historico-veiculo-btn" data-veiculo-id="' + vid + '" data-placa="' + placa + '" title="Histórico do veículo"><i class="fas fa-history"></i></button> ' +
        '<button class="btn-icon view-btn" data-id="' + m.id + '" title="Ver detalhes"><i class="fas fa-eye"></i></button> ' +
        '<button class="btn-icon edit-btn" data-id="' + m.id + '" title="Editar"><i class="fas fa-edit"></i></button> ' +
        '<button class="btn-icon delete-btn" data-id="' + m.id + '" title="Excluir"><i class="fas fa-trash"></i></button>' +
        '</td></tr>';
}

function updatePaginationUI(data) {
    const total = data.total || 0;
    const page = data.pagina_atual || 1;
    const totalPag = data.total_paginas || 1;
    const prev = document.querySelector('#paginationContainer .pagination-prev');
    const next = document.querySelector('#paginationContainer .pagination-next');
    const info = document.getElementById('paginationInfo');
    if (info) {
        info.textContent = totalPag > 1 ? 'Página ' + page + ' de ' + totalPag + ' (' + total + ' registros)' : total + ' registros';
    }
    if (prev) {
        prev.classList.toggle('disabled', page <= 1);
        prev.setAttribute('data-page', page - 1);
        prev.href = buildPageUrl(page - 1);
    }
    if (next) {
        next.classList.toggle('disabled', page >= totalPag);
        next.setAttribute('data-page', page + 1);
        next.href = buildPageUrl(page + 1);
    }
    const container = document.getElementById('paginationContainer');
    if (container) {
        container.setAttribute('data-page', page);
        container.setAttribute('data-total-paginas', totalPag);
        container.setAttribute('data-total', total);
    }
}

function loadPageViaAjax(page) {
    const tbody = document.getElementById('maintenanceTableBody');
    if (!tbody) return Promise.resolve();
    const url = buildListUrl(page);
    const container = tbody.closest('.data-table-container');
    if (container) container.classList.add('loading');
    return fetch(url, { credentials: 'include' })
        .then(r => r.json())
        .then(data => {
            if (container) container.classList.remove('loading');
            if (!data.success || !data.data) return;
            tbody.innerHTML = data.data.map(rowHtml).join('');
            updatePaginationUI(data);
        })
        .catch(function() {
            if (container) container.classList.remove('loading');
        });
}

function setupPaginationAjax() {
    const container = document.getElementById('paginationContainer');
    if (!container) return;
    container.addEventListener('click', function(e) {
        const link = e.target.closest('a.pagination-btn');
        if (!link || link.classList.contains('disabled')) {
            if (link) e.preventDefault();
            return;
        }
        e.preventDefault();
        const page = parseInt(link.getAttribute('data-page'), 10);
        if (!page || page < 1) return;
        loadPageViaAjax(page).then(function() {
            if (typeof history !== 'undefined' && history.pushState) {
                history.pushState({ page: page }, '', buildPageUrl(page));
            }
        });
    });
    window.addEventListener('popstate', function(e) {
        const page = e.state && e.state.page;
        if (page && page >= 1) loadPageViaAjax(page);
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
    document.getElementById('manutencaoId').value = data.id;
    document.getElementById('data_manutencao').value = data.data_manutencao;
    const dc = document.getElementById('data_conclusao');
    if (dc) dc.value = data.data_conclusao || '';
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
    let obs = data.observacoes || '';
    const checklistMatch = obs.match(/\n?Checklist:\s*(.+?)(?=\n|$)/i);
    if (checklistMatch) {
        obs = obs.replace(/\n?Checklist:\s*.+?(?=\n|$)/i, '').trim();
        const c = checklistMatch[1].toLowerCase();
        const o = document.getElementById('checklist_oleo');
        const f = document.getElementById('checklist_filtro');
        const t = document.getElementById('checklist_teste');
        if (o) o.checked = c.indexOf('óleo') !== -1;
        if (f) f.checked = c.indexOf('filtro') !== -1;
        if (t) t.checked = c.indexOf('teste') !== -1;
    } else {
        document.querySelectorAll('#checklist_oleo, #checklist_filtro, #checklist_teste').forEach(function(cb) { if (cb) cb.checked = false; });
    }
    document.getElementById('observacoes').value = obs;
    document.getElementById('responsavel_aprovacao').value = data.responsavel_aprovacao;
    toggleConclusaoFields();
    var anexosSec = document.getElementById('anexosSection');
    if (anexosSec) {
        anexosSec.style.display = 'block';
        loadAnexos(data.id);
    }
}

function toggleConclusaoFields() {
    const sel = document.getElementById('status_manutencao_id');
    const wrapDc = document.getElementById('wrap_data_conclusao');
    const wrapCk = document.getElementById('wrap_checklist');
    if (!sel || !wrapDc) return;
    const text = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : '';
    const show = text.toLowerCase().indexOf('conclu') !== -1;
    wrapDc.style.display = show ? 'block' : 'none';
    if (wrapCk) wrapCk.style.display = show ? 'block' : 'none';
    if (show) document.getElementById('data_conclusao').setAttribute('required', 'required');
    else document.getElementById('data_conclusao').removeAttribute('required');
}

function loadAnexos(manutencaoId) {
    if (!manutencaoId) return;
    fetch('../api/manutencao_anexos.php?manutencao_id=' + manutencaoId, { credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.data) {
                renderAnexos(data.data);
            } else {
                renderAnexos([]);
            }
        })
        .catch(function() { renderAnexos([]); });
}

function renderAnexos(list) {
    var el = document.getElementById('anexosList');
    if (!el) return;
    if (!list.length) {
        el.innerHTML = '<p class="text-muted">Nenhum anexo. Envie NF ou foto acima.</p>';
        return;
    }
    el.innerHTML = list.map(function(a) {
        return '<div class="anexo-item"><a href="' + (a.url || '') + '" target="_blank" rel="noopener">' + (a.nome_original || 'Anexo') + '</a> ' +
            '<button type="button" class="btn-icon delete-anexo-btn" data-id="' + a.id + '" title="Excluir"><i class="fas fa-trash"></i></button></div>';
    }).join('');
}

function uploadAnexo() {
    var mid = document.getElementById('manutencaoId') && document.getElementById('manutencaoId').value;
    var fileInput = document.getElementById('anexoFile');
    if (!mid || !fileInput || !fileInput.files || !fileInput.files.length) {
        alert('Salve a manutenção primeiro ou selecione um arquivo.');
        return;
    }
    var fd = new FormData();
    fd.append('manutencao_id', mid);
    fd.append('file', fileInput.files[0]);
    var btn = document.getElementById('uploadAnexoBtn');
    if (btn) btn.disabled = true;
    fetch('../api/manutencao_anexos.php', { method: 'POST', credentials: 'include', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (btn) btn.disabled = false;
            if (data.success) {
                fileInput.value = '';
                loadAnexos(parseInt(mid, 10));
            } else {
                alert(data.error || 'Erro ao enviar anexo.');
            }
        })
        .catch(function() {
            if (btn) btn.disabled = false;
            alert('Erro ao enviar anexo.');
        });
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

    const statusSel = document.getElementById('status_manutencao_id');
    const statusTexto = statusSel.options[statusSel.selectedIndex] ? statusSel.options[statusSel.selectedIndex].text : '';
    const isConcluida = statusTexto.toLowerCase().indexOf('conclu') !== -1;
    if (isConcluida && !document.getElementById('data_conclusao').value.trim()) {
        alert('Quando o status é Concluída, a Data de Conclusão é obrigatória.');
        document.getElementById('data_conclusao').focus();
        return;
    }
    let observacoes = document.getElementById('observacoes').value || '';
    const oleo = document.getElementById('checklist_oleo');
    const filtro = document.getElementById('checklist_filtro');
    const teste = document.getElementById('checklist_teste');
    if (isConcluida && (oleo || filtro || teste)) {
        const parts = [];
        if (oleo && oleo.checked) parts.push('Óleo trocado/verificado');
        if (filtro && filtro.checked) parts.push('Filtro trocado');
        if (teste && teste.checked) parts.push('Teste realizado');
        if (parts.length) observacoes = (observacoes ? observacoes + '\n' : '') + 'Checklist: ' + parts.join(', ');
    }
    const formData = {
        id: document.getElementById('manutencaoId').value,
        data_manutencao: document.getElementById('data_manutencao').value,
        data_conclusao: document.getElementById('data_conclusao').value || null,
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
        observacoes: observacoes,
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
async function initializeMaintenanceCharts(preloadedData) {
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

        const canvasIds = ['maintenanceCostsChart', 'maintenanceTypesChart', 'maintenanceStatusChart', 
                          'maintenanceEvolutionChart', 'topVehiclesChart', 'componentsHeatmapChart'];
        const anyChartExists = Object.values(charts).some(c => c instanceof Chart);
        // Só limpa/redimensiona canvas se já existiam gráficos (reinit); na 1ª carga evita reflow/tremida
        if (anyChartExists) {
            canvasIds.forEach(id => {
                const canvas = document.getElementById(id);
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    canvas.width = canvas.offsetWidth;
                    canvas.height = canvas.offsetHeight;
                }
            });
            await new Promise(resolve => setTimeout(resolve, 50));
        }

        // Use preloaded data when provided (e.g. from manutencoes.php) to avoid double fetch and double init
        let data = preloadedData;
        if (!data || !data.success) {
            const response = await fetch('../includes/get_maintenance_data.php');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            data = await response.json();
        }
        
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

        // Mostra a seção de gráficos após tudo desenhado (evita tremida)
        const section = document.getElementById('analyticsSection');
        if (section && section.classList.contains('charts-pending')) {
            section.classList.remove('charts-pending');
            section.classList.add('charts-ready');
        }

    } catch (error) {
        console.error('Erro ao carregar dados dos gráficos:', error);
        console.error('Stack trace:', error.stack);
        const errorMessage = `Erro ao carregar dados dos gráficos: ${error.message}`;
        alert(errorMessage);
        const section = document.getElementById('analyticsSection');
        if (section) {
            section.classList.remove('charts-pending');
            section.classList.add('charts-ready');
        }
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