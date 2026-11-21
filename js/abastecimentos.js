// Lógica dinâmica para o modal de Abastecimento
(function (g) {
    if (typeof g.sfApiUrl === 'function') return;
    g.sfApiUrl = function (rel) {
        rel = String(rel || '').replace(/^\//, '');
        var b = typeof g.__SF_API_BASE__ === 'string' && g.__SF_API_BASE__ !== ''
            ? String(g.__SF_API_BASE__).replace(/\/+$/, '')
            : '';
        if (b) return b + '/' + rel;
        try { return new URL('../api/' + rel, g.location.href).href; }
        catch (e) { return '../api/' + rel; }
    };
})(typeof window !== 'undefined' ? window : this);

var currentFilter = null;
var refuelingCurrentPage = 1;
var consumptionChart = null;
var efficiencyChart = null;
var pendingDeleteId = null;
var refuelPerPageInUrl = false;
var refuelSortField = 'data_abastecimento';
var refuelSortDir = 'DESC';
var _arlaFieldsWired = false;
var _valorTotalCalcWired = false;
var _numericInputsWired = false;
window.refuelSortField = refuelSortField;
window.refuelSortDir = refuelSortDir;

function showToast(message, type) {
    type = type || 'info';
    var container = document.getElementById('toastContainer');
    if (!container) return;
    var toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.setAttribute('role', 'alert');
    var icon = type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle');
    toast.innerHTML = '<i class="fas ' + icon + '"></i><span></span>';
    toast.querySelector('span').textContent = message || '';
    container.appendChild(toast);
    setTimeout(function () {
        toast.style.opacity = '0';
        setTimeout(function () { toast.remove(); }, 300);
    }, 4000);
}

function formatDate(dateString) {
    if (!dateString) return '-';
    var date = new Date(String(dateString).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleDateString('pt-BR');
}

function formatNumber(value, decimals) {
    decimals = decimals == null ? 0 : decimals;
    if (value == null || value === '') return decimals > 0 ? '0,' + '0'.repeat(decimals) : '0';
    return Number(value).toLocaleString('pt-BR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

function debounce(func, wait) {
    var timeout;
    return function () {
        var args = arguments;
        var later = function () {
            clearTimeout(timeout);
            func.apply(null, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function refuelDefaultSortDir(field) {
    var textLike = ['veiculo_placa', 'motorista_nome', 'posto', 'forma_pagamento', 'rota'];
    return textLike.indexOf(field) >= 0 ? 'ASC' : 'DESC';
}

function syncRefuelSortIndicators() {
    var table = document.getElementById('refuelingTable');
    if (!table) return;
    table.querySelectorAll('thead th.sortable').forEach(function (th) {
        var field = th.getAttribute('data-sort');
        var ind = th.querySelector('.sort-ind');
        if (!ind) return;
        var on = field === refuelSortField;
        th.classList.toggle('sorted', on);
        ind.textContent = on ? (refuelSortDir === 'ASC' ? '▲' : '▼') : '⇅';
    });
}

function wireRefuelSortHeaders() {
    var table = document.getElementById('refuelingTable');
    if (!table || table.dataset.sortWired === '1') return;
    table.dataset.sortWired = '1';
    table.querySelectorAll('thead th.sortable').forEach(function (th) {
        th.addEventListener('click', function () {
            var field = th.getAttribute('data-sort');
            if (!field) return;
            if (refuelSortField === field) {
                refuelSortDir = refuelSortDir === 'ASC' ? 'DESC' : 'ASC';
            } else {
                refuelSortField = field;
                refuelSortDir = refuelDefaultSortDir(field);
            }
            window.refuelSortField = refuelSortField;
            window.refuelSortDir = refuelSortDir;
            syncRefuelSortIndicators();
            loadRefuelingData(1);
        });
    });
}

function restoreRefuelFiltersFromUrl() {
    var p = new URLSearchParams(window.location.search);
    var searchEl = document.getElementById('searchRefueling');
    if (searchEl && p.has('search')) searchEl.value = p.get('search') || '';
    var map = [
        ['veiculo', 'vehicleFilter'],
        ['motorista', 'driverFilter'],
        ['combustivel', 'fuelFilter'],
        ['pagamento', 'paymentFilter']
    ];
    map.forEach(function (pair) {
        if (!p.has(pair[0])) return;
        var el = document.getElementById(pair[1]);
        if (el) el.value = p.get(pair[0]) || '';
    });
    if (p.has('year') && p.has('month')) {
        currentFilter = p.get('year') + '-' + p.get('month');
        var fm = document.getElementById('filterMonth');
        if (fm) fm.value = currentFilter;
    } else if (p.has('month')) {
        currentFilter = p.get('month') || null;
        var fm2 = document.getElementById('filterMonth');
        if (fm2 && currentFilter) fm2.value = currentFilter;
    }
    if (p.has('per_page')) {
        refuelPerPageInUrl = true;
        var pp = parseInt(p.get('per_page'), 10);
        var sel = document.getElementById('perPageRefuel');
        if (sel && !Number.isNaN(pp) && [5, 10, 25, 50, 100].indexOf(pp) >= 0) {
            sel.value = String(pp);
        }
    }
    if (p.has('sort')) refuelSortField = p.get('sort') || refuelSortField;
    if (p.has('dir')) refuelSortDir = (p.get('dir') || '').toUpperCase() === 'ASC' ? 'ASC' : 'DESC';
    window.refuelSortField = refuelSortField;
    window.refuelSortDir = refuelSortDir;
}

function syncRefuelBrowserUrl(state) {
    var isModern = document.body.classList.contains('abastecimentos-modern');
    var hasListFilters = !!(state.search || state.vehicleFilter || state.driverFilter || state.fuelFilter || state.paymentFilter || currentFilter);
    var sortField = state.sortField;
    var sortDir = state.sortDir;
    var hasNonDefaultSort = sortField !== 'data_abastecimento' || sortDir !== 'DESC';
    var hasNonDefaultPage = state.page !== 1;
    var hasPerPageInUrl = refuelPerPageInUrl && state.limit !== 10;
    var params = new URLSearchParams();
    if (hasNonDefaultPage) params.set('page', String(state.page));
    if (hasPerPageInUrl) params.set('per_page', String(state.limit));
    if (state.search) params.set('search', state.search);
    if (state.vehicleFilter) params.set('veiculo', state.vehicleFilter);
    if (state.driverFilter) params.set('motorista', state.driverFilter);
    if (state.fuelFilter) params.set('combustivel', state.fuelFilter);
    if (state.paymentFilter) params.set('pagamento', state.paymentFilter);
    if (currentFilter) {
        var parts = currentFilter.split('-');
        if (parts.length === 2) {
            params.set('year', parts[0]);
            params.set('month', parts[1]);
        }
    }
    if (hasNonDefaultSort) {
        params.set('sort', sortField);
        params.set('dir', sortDir);
    }
    if (!hasListFilters && !hasNonDefaultSort && !hasNonDefaultPage && !hasPerPageInUrl) {
        var desiredEmpty = isModern ? '' : '?classic=1';
        if (window.location.search !== desiredEmpty) {
            window.history.replaceState({}, '', window.location.pathname + desiredEmpty);
        }
        return;
    }
    if (!isModern) params.set('classic', '1');
    var desired = '?' + params.toString();
    if (window.location.search !== desired) {
        window.history.replaceState({}, '', window.location.pathname + desired);
    }
}

// Escapa texto para inserção segura em HTML (evita XSS via posto, nomes, etc.)
function escapeHtmlAbast(s) {
    if (s == null) return '';
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// ---- Painel de insights de abastecimentos ----
var ABAST_INSIGHTS_TITLE = 'Insights de abastecimentos';
var abastInsightsLoaded = false;
var abastInsightsLoading = false;

function abastInsightsUrl() {
    return sfApiUrl('refuel_anomalies.php');
}

function abastInsightsPlaceholder() {
    if (window.SFInsights && SFInsights.placeholderHtml) {
        return SFInsights.placeholderHtml(ABAST_INSIGHTS_TITLE);
    }
    return '<i class="fas fa-clipboard-check"></i> ' + ABAST_INSIGHTS_TITLE +
        ' — <span style="font-weight:500;color:var(--text-muted)">expandir para analisar</span>';
}

function abastInsightsReadCache() {
    if (!window.SFInsights || !SFInsights.readCache) return null;
    return SFInsights.readCache(abastInsightsUrl());
}

function abastInsightsWriteCache(data) {
    if (window.SFInsights && SFInsights.writeCache) {
        SFInsights.writeCache(abastInsightsUrl(), data, SFInsights.CACHE_TTL_MS);
    }
}

function initAbastInsightsPanel() {
    var panel = document.getElementById('abAnomalies');
    var summary = document.getElementById('abSummary');
    if (panel) panel.style.display = '';
    if (summary) summary.innerHTML = abastInsightsPlaceholder();
}

function setupRefuelAnomalies() {
    initAbastInsightsPanel();
    var toggle = document.getElementById('abToggle');
    var list = document.getElementById('abList');
    if (toggle && list) {
        toggle.addEventListener('click', function () {
            var open = toggle.getAttribute('aria-expanded') === 'true';
            var willOpen = !open;
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            list.style.display = willOpen ? '' : 'none';
            if (willOpen && !abastInsightsLoaded && !abastInsightsLoading) {
                loadRefuelAnomalies(false);
            }
        });
    }
    var refresh = document.getElementById('abRefresh');
    if (refresh) {
        refresh.addEventListener('click', function (e) {
            e.stopPropagation();
            abastInsightsLoaded = false;
            if (window.SFInsights && SFInsights.removeCache) SFInsights.removeCache(abastInsightsUrl());
            loadRefuelAnomalies(true);
        });
    }
    if (list) {
        list.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-ab-edit]');
            if (!btn) return;
            var id = btn.getAttribute('data-ab-edit');
            if (typeof showEditRefuelModal === 'function') showEditRefuelModal(id);
        });
    }
}

function renderRefuelAnomalies(json) {
    var panel = document.getElementById('abAnomalies');
    var summary = document.getElementById('abSummary');
    var list = document.getElementById('abList');
    if (!panel || !summary || !list) return;
    abastInsightsLoaded = true;
    panel.classList.remove('has-erros', 'has-avisos');
    var erros = json.erros || 0, avisos = json.avisos || 0, total = json.total_inconsistencias || 0;
    panel.style.display = '';

    if (total === 0) {
        summary.innerHTML = '<i class="fas fa-clipboard-check" style="color:#16a34a"></i> ' + ABAST_INSIGHTS_TITLE +
            ' <span class="ab-pill ok">tudo certo</span>';
        list.innerHTML = '<div class="ab-empty">Nenhuma anomalia encontrada.</div>';
        list.style.display = '';
        return;
    }

    panel.classList.add(erros > 0 ? 'has-erros' : 'has-avisos');
    var color = erros > 0 ? '#dc2626' : '#f59e0b';
    summary.innerHTML = '<i class="fas fa-clipboard-check" style="color:' + color + '"></i> ' + ABAST_INSIGHTS_TITLE +
        '<span class="ab-pill ' + (erros > 0 ? 'erro' : 'aviso') + '">' + total + ' anomalia(s)</span>' +
        (erros > 0 ? '<span class="ab-pill erro">' + erros + ' erro(s)</span>' : '') +
        (avisos > 0 ? '<span class="ab-pill aviso">' + avisos + ' aviso(s)</span>' : '');

    list.innerHTML = (json.itens || []).map(function (it) {
        var dataBr = it.data ? formatDate(it.data) : '';
        var motivos = (it.motivos || []).map(function (m) { return '<li>' + escapeHtmlAbast(m.msg) + '</li>'; }).join('');
        var icon = it.severidade === 'erro' ? 'fa-circle-xmark' : 'fa-triangle-exclamation';
        return '<div class="ab-item">' +
            '<span class="ab-sev ' + it.severidade + '"><i class="fas ' + icon + '"></i></span>' +
            '<div class="ab-info">' +
                '<div class="ab-meta"><strong>' + escapeHtmlAbast(dataBr) + '</strong> · ' +
                escapeHtmlAbast(it.veiculo) + ' · ' + escapeHtmlAbast(it.motorista) + ' · ' + escapeHtmlAbast(it.posto) + '</div>' +
                '<ul class="ab-motivos">' + motivos + '</ul>' +
            '</div>' +
            '<button type="button" class="btn-icon ab-edit" data-ab-edit="' + it.id + '" title="Abrir abastecimento" aria-label="Abrir abastecimento"><i class="fas fa-edit"></i></button>' +
            '</div>';
    }).join('');
}

function loadRefuelAnomalies(forceRefresh) {
    if (abastInsightsLoading) return;
    var panel = document.getElementById('abAnomalies');
    var summary = document.getElementById('abSummary');
    var list = document.getElementById('abList');
    if (!panel) return;
    panel.style.display = '';

    if (!forceRefresh) {
        var hit = abastInsightsReadCache();
        if (hit && hit.success) {
            renderRefuelAnomalies(hit);
            return;
        }
    }

    abastInsightsLoading = true;
    if (summary) summary.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analisando abastecimentos…';
    if (list) list.innerHTML = '<div class="ab-empty">Carregando…</div>';
    var url = abastInsightsUrl();
    if (forceRefresh) url += (url.indexOf('?') >= 0 ? '&' : '?') + 'refresh=1&_=' + Date.now();
    fetch(url, { credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (json) {
            if (json && json.success) {
                abastInsightsWriteCache(json);
                renderRefuelAnomalies(json);
            } else {
                panel.style.display = 'none';
            }
        })
        .catch(function () { panel.style.display = 'none'; })
        .then(function () { abastInsightsLoading = false; });
}


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
            fetch(sfApiUrl(`refuel_data.php?action=get_veiculos_by_data&data=${encodeURIComponent(filtroData)}`))
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.data.length > 0) {
                        veiculoSelect.innerHTML = '<option value="">Selecione um veículo</option>' +
                            res.data.map(v => `<option value="${escapeHtmlAbast(v.id)}">${escapeHtmlAbast(v.placa)} - ${escapeHtmlAbast(v.modelo)}</option>`).join('');
                        veiculoSelect.disabled = false;
                    } else {
                        veiculoSelect.innerHTML = '<option value="">Nenhum veículo encontrado</option>';
                        veiculoSelect.disabled = true;
                    }
                });
        }
    });

    // 2. Ao selecionar veículo, busca motoristas (sempre ler a data do campo — evita filtro vazio após abrir edição)
    veiculoSelect.addEventListener('change', function() {
        filtroVeiculo = this.value;
        resetMotoristas();
        resetRotas();
        const dataFiltro = dataRotaFiltroInput.value;
        if (dataFiltro && filtroVeiculo) {
            motoristaSelect.disabled = true;
            fetch(sfApiUrl(`refuel_data.php?action=get_motoristas_by_veiculo_data&veiculo_id=${encodeURIComponent(filtroVeiculo)}&data=${encodeURIComponent(dataFiltro)}`))
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.data.length > 0) {
                        motoristaSelect.innerHTML = '<option value="">Selecione um motorista</option>' +
                            res.data.map(m => `<option value="${escapeHtmlAbast(m.id)}">${escapeHtmlAbast(m.nome)}</option>`).join('');
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
        const dataFiltro = dataRotaFiltroInput.value;
        const veiculoIdAtual = veiculoSelect.value;
        if (dataFiltro && veiculoIdAtual && filtroMotorista) {
            rotaSelect.disabled = true;
            fetch(sfApiUrl(`refuel_data.php?action=get_rotas_by_veiculo_motorista_data&veiculo_id=${encodeURIComponent(veiculoIdAtual)}&motorista_id=${encodeURIComponent(filtroMotorista)}&data=${encodeURIComponent(dataFiltro)}`))
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.data.length > 0) {
                        rotaSelect.innerHTML = '<option value="">Selecione a rota</option>' +
                            res.data.map(r => `<option value="${escapeHtmlAbast(r.id)}">${escapeHtmlAbast(r.data_rota)} - ${escapeHtmlAbast(r.cidade_origem_nome)} → ${escapeHtmlAbast(r.cidade_destino_nome)}</option>`).join('');
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
        
        // Valida se os campos obrigatórios estão preenchidos
        const motoristaId = form.motorista_id.value;
        const veiculoId = form.veiculo_id.value;
        const rotaId = form.rota_id.value;
        
        if (!motoristaId) {
            if (typeof showToast === 'function') showToast('Por favor, selecione um motorista', 'error');
            else alert('Por favor, selecione um motorista');
            return;
        }
        if (!veiculoId) {
            if (typeof showToast === 'function') showToast('Por favor, selecione um veículo', 'error');
            else alert('Por favor, selecione um veículo');
            return;
        }
        if (!rotaId) {
            if (typeof showToast === 'function') showToast('Por favor, selecione uma rota', 'error');
            else alert('Por favor, selecione uma rota');
            return;
        }
        
        // Corrige campos numéricos para o formato correto
        form.litros.value = form.litros.value.replace(/\./g, '').replace(',', '.');
        form.valor_litro.value = form.valor_litro.value.replace(/\./g, '').replace(',', '.');
        form.valor_total.value = form.valor_total.value.replace(/\./g, '').replace(',', '.');
        
        // Corrige campos ARLA se existirem
        if (form.litros_arla) {
            form.litros_arla.value = form.litros_arla.value.replace(/\./g, '').replace(',', '.');
        }
        if (form.valor_litro_arla) {
            form.valor_litro_arla.value = form.valor_litro_arla.value.replace(/\./g, '').replace(',', '.');
        }
        if (form.valor_total_arla) {
            form.valor_total_arla.value = form.valor_total_arla.value.replace(/\./g, '').replace(',', '.');
        }
        
        const refuelId = document.getElementById('refuelId').value;
        
        // Debug: verificar valores dos campos (apenas em caso de erro)
        // console.log('Valores do formulário:', {
        //     motoristaId: form.motorista_id.value,
        //     veiculoId: form.veiculo_id.value,
        //     rotaId: form.rota_id.value,
        //     refuelId: refuelId
        // });
        
        // Preparar dados para envio
        const formData = new FormData(form);
        
        // Garantir que os campos obrigatórios estejam no FormData
        if (form.rota_id.value) {
            formData.set('rota_id', form.rota_id.value);
        }
        if (form.motorista_id.value) {
            formData.set('motorista_id', form.motorista_id.value);
        }
        if (form.veiculo_id.value) {
            formData.set('veiculo_id', form.veiculo_id.value);
        }
        
        formData.append('action', refuelId ? 'update' : 'create');
        if (refuelId) {
            formData.append('id', refuelId);
        }
        if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__) {
            formData.append('csrf_token', window.__SF_CSRF__);
        }
        
        // Debug: verificar FormData (apenas em caso de erro)
        // console.log('FormData contents:');
        // for (let [key, value] of formData.entries()) {
        //     console.log(key, value);
        // }

        fetch(sfApiUrl('refuel_actions.php'), {
            method: 'POST',
            body: formData,
            credentials: 'include',
            headers: typeof sfMutationHeaders === 'function' ? sfMutationHeaders() : {}
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Abastecimento salvo com sucesso.', 'success');
                } else {
                    alert(data.message);
                }
                closeModal();
                try { sessionStorage.removeItem('abastecimentos_filter_options'); } catch (e) {}
                if (typeof loadRefuelingData === 'function') loadRefuelingData(1);
                if (typeof loadRefuelingSummary === 'function') loadRefuelingSummary();
                if (typeof loadConsumptionChart === 'function') loadConsumptionChart();
                if (typeof loadEfficiencyChart === 'function') loadEfficiencyChart();
                if (typeof loadAnomaliesChart === 'function') {
                    loadAnomaliesChart();
                    loadDriverConsumptionChart();
                    loadVehicleEfficiencyChart();
                    loadMonthlyCostChart();
                }
            } else {
                throw new Error(data.error || 'Erro ao salvar abastecimento');
            }
        })
        .catch(error => {
            console.error('Error saving refuel:', error);
            if (typeof showToast === 'function') {
                showToast('Erro ao salvar abastecimento: ' + (typeof sfSafeErrorMessage === 'function' ? sfSafeErrorMessage(error) : error.message), 'error');
            } else {
                alert('Erro ao salvar abastecimento: ' + (typeof sfSafeErrorMessage === 'function' ? sfSafeErrorMessage(error) : error.message));
            }
        });
    });

    // Cancelar
    cancelBtn.addEventListener('click', closeModal);
    closeBtn.addEventListener('click', closeModal);

    // Inicializa selects dependentes como desabilitados
    resetMotoristas();
    resetRotas();
});


/** Data Y-m-d a partir de campo da API (DATE ou datetime). */
function sfRefuelDateToYmd(v) {
    if (v == null || v === '') {
        return '';
    }
    const s = String(v).trim();
    const m = s.match(/^(\d{4}-\d{2}-\d{2})/);
    return m ? m[1] : '';
}

/**
 * Preenche data da rota + selects veículo/motorista/rota com listas da API (para poder trocar na edição).
 * Reabilita os selects (o init do modal deixa motorista/rota disabled).
 */
async function preencherCamposEdicao(refuel) {
    const dataRotaInput = document.getElementById('data_rota_filtro');
    const veiculoSelect = document.getElementById('veiculo_id');
    const motoristaSelect = document.getElementById('motorista_id');
    const rotaSelect = document.getElementById('rota_id');

    const dataYmd = sfRefuelDateToYmd(refuel.data_rota) || sfRefuelDateToYmd(refuel.data_abastecimento);
    if (!dataYmd) {
        return;
    }
    dataRotaInput.value = dataYmd;

    const enc = encodeURIComponent(dataYmd);
    let res = await fetch(sfApiUrl(`refuel_data.php?action=get_veiculos_by_data&data=${enc}`));
    let json = await res.json();
    let vehList = (json.success && Array.isArray(json.data)) ? json.data : [];
    if (vehList.length === 0 && refuel.veiculo_id) {
        vehList = [{
            id: refuel.veiculo_id,
            placa: refuel.veiculo_placa || '',
            modelo: refuel.veiculo_modelo || '',
        }];
    }
    veiculoSelect.innerHTML = '<option value="">Selecione um veículo</option>' +
        vehList.map(function (v) {
            return '<option value="' + escapeHtmlAbast(v.id) + '">' + escapeHtmlAbast(v.placa || '') + ' - ' + escapeHtmlAbast(v.modelo || '') + '</option>';
        }).join('');
    veiculoSelect.value = String(refuel.veiculo_id || '');

    const vid = refuel.veiculo_id;
    let motList = [];
    if (vid) {
        res = await fetch(sfApiUrl(`refuel_data.php?action=get_motoristas_by_veiculo_data&veiculo_id=${encodeURIComponent(vid)}&data=${enc}`));
        json = await res.json();
        motList = (json.success && Array.isArray(json.data)) ? json.data : [];
    }
    const mid = refuel.motorista_id;
    if (mid && !motList.some(function (m) { return String(m.id) === String(mid); })) {
        motList = motList.concat([{ id: mid, nome: refuel.motorista_nome || ('#' + mid) }]);
    }
    motoristaSelect.innerHTML = '<option value="">Selecione um motorista</option>' +
        motList.map(function (m) {
            return '<option value="' + escapeHtmlAbast(m.id) + '">' + escapeHtmlAbast(m.nome || '') + '</option>';
        }).join('');
    motoristaSelect.value = String(mid || '');

    let rList = [];
    if (vid && mid) {
        res = await fetch(sfApiUrl(`refuel_data.php?action=get_rotas_by_veiculo_motorista_data&veiculo_id=${encodeURIComponent(vid)}&motorista_id=${encodeURIComponent(mid)}&data=${enc}`));
        json = await res.json();
        rList = (json.success && Array.isArray(json.data)) ? json.data : [];
    }
    const rid = refuel.rota_id;
    if (rid && !rList.some(function (r) { return String(r.id) === String(rid); })) {
        const dr = sfRefuelDateToYmd(refuel.data_rota) || dataYmd;
        rList = rList.concat([{
            id: rid,
            data_rota: dr,
            cidade_origem_nome: refuel.cidade_origem_nome || 'Origem',
            cidade_destino_nome: refuel.cidade_destino_nome || 'Destino',
        }]);
    }
    rotaSelect.innerHTML = '<option value="">Selecione a rota</option>' +
        rList.map(function (r) {
            return '<option value="' + escapeHtmlAbast(r.id) + '">' + escapeHtmlAbast(r.data_rota) + ' - ' + escapeHtmlAbast(r.cidade_origem_nome || '') + ' → ' + escapeHtmlAbast(r.cidade_destino_nome || '') + '</option>';
        }).join('');
    rotaSelect.value = String(rid || '');

    veiculoSelect.disabled = veiculoSelect.querySelectorAll('option').length <= 1;
    motoristaSelect.disabled = motoristaSelect.querySelectorAll('option').length <= 1;
    rotaSelect.disabled = rotaSelect.querySelectorAll('option').length <= 1;
}

/**
 * Abre o modal de edição (chamado pelo PHP ou após fetch). Preenche listas para motorista/rota editáveis.
 */
window.openEditRefuelModal = async function(refuel) {
    document.getElementById('refuelId').value = refuel.id;
    const dab = (refuel.data_abastecimento || '').toString().replace(' ', 'T');
    document.getElementById('data_abastecimento').value = dab.length >= 16 ? dab.slice(0, 16) : dab;
    document.getElementById('tipo_combustivel').value = refuel.tipo_combustivel || '';
    document.getElementById('litros').value = Number(refuel.litros).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('valor_litro').value = Number(refuel.valor_litro).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('valor_total').value = Number(refuel.valor_total).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('km_atual').value = refuel.km_atual;
    document.getElementById('posto').value = refuel.posto || '';
    document.getElementById('forma_pagamento').value = refuel.forma_pagamento || '';
    document.getElementById('observacoes').value = refuel.observacoes || '';

    await preencherCamposEdicao(refuel);

    const incluiArlaCheckbox = document.getElementById('inclui_arla');
    const camposArla = document.getElementById('campos_arla');
    if (incluiArlaCheckbox && camposArla) {
        if (Number(refuel.inclui_arla) === 1) {
            incluiArlaCheckbox.checked = true;
            camposArla.style.display = 'grid';
            var la = document.getElementById('litros_arla');
            var vla = document.getElementById('valor_litro_arla');
            var vta = document.getElementById('valor_total_arla');
            if (la) {
                la.value = Number(refuel.litros_arla || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            if (vla) {
                vla.value = Number(refuel.valor_litro_arla || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            if (vta) {
                vta.value = Number(refuel.valor_total_arla || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        } else {
            incluiArlaCheckbox.checked = false;
            camposArla.style.display = 'none';
        }
    }

    var comprovanteAtual = document.getElementById('comprovante_atual');
    if (comprovanteAtual) {
        if (refuel.comprovante) {
            var compPath = String(refuel.comprovante);
            var compHref = /^(https?:|\/)/i.test(compPath) ? compPath : '../' + compPath.replace(/^\.\.\//, '');
            comprovanteAtual.innerHTML =
                '<div class="current-file"><i class="fas fa-file-alt"></i> ' +
                '<a href="' + escapeHtmlAbast(compHref) + '" target="_blank" rel="noopener">Ver comprovante atual</a></div>';
        } else {
            comprovanteAtual.innerHTML = '';
        }
    }

    document.getElementById('modalTitle').textContent = 'Editar Abastecimento';
    if (typeof setupValorTotalCalc === 'function') {
        setupValorTotalCalc();
    }
    if (typeof setupArlaFields === 'function') {
        setupArlaFields();
    }
    if (typeof setupNumericInputs === 'function') {
        setupNumericInputs();
    }

    document.getElementById('refuelModal').classList.add('active');
};

/** Percentual ARLA na listagem: sem agrupador de milhares (evita "1.785,7%"). */
function formatArlaPercentDisplay(percentual) {
    const p = Number(percentual);
    if (!Number.isFinite(p)) return '0%';
    if (Math.abs(p) >= 100) {
        return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0, useGrouping: false }).format(Math.round(p)) + '%';
    }
    return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1, useGrouping: false }).format(p) + '%';
}

function formatArlaPercentTitle(percentual) {
    const p = Number(percentual);
    if (!Number.isFinite(p)) return '';
    const s = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 4, useGrouping: false }).format(p);
    return 'Percentual ARLA (litros ARLA ÷ diesel): ' + s + '%';
}

function loadRefuelingData(page = null) {
    var emptyElStart = document.getElementById('refuelEmptyState');
    var wrapStart = document.getElementById('refuelTableContainer');
    var loadingEl = document.getElementById('refuelTableLoading');
    if (emptyElStart && wrapStart) {
        emptyElStart.classList.remove('visible');
        wrapStart.style.display = '';
    }
    // Anti-flash: só mostra o loading se a resposta demorar (>180ms). Paginação rápida não pisca.
    var _refuelLoadTimer = setTimeout(function () { if (loadingEl) loadingEl.classList.add('is-visible'); }, 180);

    if (page !== null) {
        const parsedPage = parseInt(page, 10);
        refuelingCurrentPage = Number.isNaN(parsedPage) || parsedPage < 1 ? 1 : parsedPage;
    } else {
        const urlParams = new URLSearchParams(window.location.search);
        const urlPage = parseInt(urlParams.get('page'), 10);
        if (!Number.isNaN(urlPage) && urlPage > 0) {
            refuelingCurrentPage = urlPage;
        }
    }

    const perPageSelect = document.getElementById('perPageRefuel');
    const perPageFromSelect = perPageSelect ? parseInt(perPageSelect.value, 10) : 10;
    const limit = [5, 10, 25, 50, 100].indexOf(perPageFromSelect) >= 0 ? perPageFromSelect : 10;

    const searchInput = document.getElementById('searchRefueling');
    const vehicleSelect = document.getElementById('vehicleFilter');
    const driverSelect = document.getElementById('driverFilter');
    const fuelSelect = document.getElementById('fuelFilter');
    const paymentSelect = document.getElementById('paymentFilter');

    const search = searchInput ? searchInput.value.trim() : '';
    const vehicleFilter = vehicleSelect ? vehicleSelect.value : '';
    const driverFilter = driverSelect ? driverSelect.value : '';
    const fuelFilter = fuelSelect ? fuelSelect.value : '';
    const paymentFilter = paymentSelect ? paymentSelect.value : '';

    const sortField = refuelSortField || 'data_abastecimento';
    const sortDir = String(refuelSortDir || 'DESC').toUpperCase() === 'ASC' ? 'ASC' : 'DESC';

    syncRefuelBrowserUrl({
        page: refuelingCurrentPage,
        limit: limit,
        search: search,
        vehicleFilter: vehicleFilter,
        driverFilter: driverFilter,
        fuelFilter: fuelFilter,
        paymentFilter: paymentFilter,
        sortField: sortField,
        sortDir: sortDir
    });

    let url = sfApiUrl(`refuel_data.php?action=list&page=${refuelingCurrentPage}&limit=${limit}`);
    if (search) url += `&search=${encodeURIComponent(search)}`;
    if (currentFilter) {
        const [year, month] = currentFilter.split('-');
        url += `&year=${year}&month=${month}`;
    }
    if (vehicleFilter) url += `&veiculo=${encodeURIComponent(vehicleFilter)}`;
    if (driverFilter) url += `&motorista=${encodeURIComponent(driverFilter)}`;
    if (fuelFilter) url += `&combustivel=${encodeURIComponent(fuelFilter)}`;
    if (paymentFilter) url += `&pagamento=${encodeURIComponent(paymentFilter)}`;
    url += `&sort=${encodeURIComponent(sortField)}&dir=${encodeURIComponent(sortDir)}`;

    fetch(url, { credentials: 'include' })
        .then(response => {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(data => {
            clearTimeout(_refuelLoadTimer);
            if (loadingEl) loadingEl.classList.remove('is-visible');
            if (data.success) {
                updateRefuelingsTable(data.data);
                if (data.pagination) {
                    refuelingCurrentPage = data.pagination.page || refuelingCurrentPage;
                    updatePagination(data.pagination);
                    syncRefuelBrowserUrl({
                        page: refuelingCurrentPage,
                        limit: limit,
                        search: search,
                        vehicleFilter: vehicleFilter,
                        driverFilter: driverFilter,
                        fuelFilter: fuelFilter,
                        paymentFilter: paymentFilter,
                        sortField: sortField,
                        sortDir: sortDir
                    });
                }
            } else {
                throw new Error(data.error || 'Erro ao carregar dados dos abastecimentos');
            }
        })
        .catch(error => {
            clearTimeout(_refuelLoadTimer);
            if (loadingEl) loadingEl.classList.remove('is-visible');
            if (window.__SF_DEBUG__) console.error('Erro ao carregar abastecimentos:', error);
            var msg = typeof sfSafeErrorMessage === 'function' ? sfSafeErrorMessage(error) : (error.message || 'Erro');
            showToast('Erro ao carregar dados: ' + msg, 'error');
        });
}

function updatePagination(pagination) {
    if (!pagination) {
        return;
    }

    const totalPages = Math.max(1, pagination.totalPages || 1);
    const current = Math.min(Math.max(1, pagination.page || 1), totalPages);
    refuelingCurrentPage = current;

    const paginationContainer = document.getElementById('paginationRefuelContainer') || document.querySelector('.pagination');
    if (!paginationContainer) return;
    
    const prevBtn = paginationContainer.querySelector('a:first-child');
    const nextBtn = paginationContainer.querySelector('a:last-child');
    const perPageSelect = document.getElementById('perPageRefuel');
    const perPage = perPageSelect ? parseInt(perPageSelect.value, 10) : 10;
    const perPageParam = [5, 10, 25, 50, 100].indexOf(perPage) >= 0 ? perPage : 10;
    
    const paginationInfo = paginationContainer.querySelector('.pagination-info');
    const totalRegistros = pagination.total || (current * perPageParam);
    if (paginationInfo) {
        paginationInfo.textContent = totalPages > 1
            ? `Página ${current} de ${totalPages} (${totalRegistros} registros)`
            : `${totalRegistros} registros`;
    }
    
    const prevPage = Math.max(1, current - 1);
    const nextPageValue = Math.min(totalPages, current + 1);
    
    const abastModern = document.body.classList.contains('abastecimentos-modern');
    const sortFieldPg = (typeof refuelSortField !== 'undefined' && refuelSortField)
        ? refuelSortField
        : 'data_abastecimento';
    const sortDirPg = (typeof refuelSortDir !== 'undefined' && String(refuelSortDir).toUpperCase() === 'ASC')
        ? 'ASC'
        : 'DESC';
    function refuelPaginationHref(pageNum) {
        const p = new URLSearchParams();
        p.set('page', String(pageNum));
        p.set('per_page', String(perPageParam));
        if (!abastModern) {
            p.set('classic', '1');
        }
        if (sortFieldPg !== 'data_abastecimento' || sortDirPg !== 'DESC') {
            p.set('sort', sortFieldPg);
            p.set('dir', sortDirPg);
        }
        return '?' + p.toString();
    }

    if (prevBtn) {
        const isDisabled = current <= 1;
        prevBtn.classList.toggle('disabled', isDisabled);
        prevBtn.href = refuelPaginationHref(prevPage);
        prevBtn.onclick = function(event) {
            event.preventDefault();
            if (isDisabled) return;
            loadRefuelingData(prevPage);
        };
    }
    
    if (nextBtn) {
        const isDisabled = current >= totalPages;
        nextBtn.classList.toggle('disabled', isDisabled);
        nextBtn.href = refuelPaginationHref(nextPageValue);
        nextBtn.onclick = function(event) {
            event.preventDefault();
            if (isDisabled) return;
            loadRefuelingData(nextPageValue);
        };
    }
}

function updateRefuelingsTable(refuelings) {
    const tbody =
        document.getElementById('refuelingTableBody') ||
        document.querySelector('#refuelingTable tbody') ||
        document.querySelector('table.fornc-table tbody') ||
        document.querySelector('table.data-table tbody');
    if (!tbody) return;
    tbody.innerHTML = '';

    var refuelModern = document.body.classList.contains('abastecimentos-modern');
    function dc(name) {
        return refuelModern ? ' data-col="' + name + '"' : '';
    }
    var emptyEl = document.getElementById('refuelEmptyState');
    var refuelWrap = document.getElementById('refuelTableContainer');
    var isEmpty = !refuelings || refuelings.length === 0;
    if (emptyEl && refuelWrap) {
        emptyEl.classList.toggle('visible', isEmpty);
        refuelWrap.style.display = isEmpty ? 'none' : '';
    }

    if (isEmpty) {
        if (!emptyEl) {
            tbody.innerHTML = '<tr><td colspan="12" class="text-center">Nenhum abastecimento encontrado</td></tr>';
        }
        return;
    }

    refuelings.forEach(refuel => {
        const row = document.createElement('tr');
        const valorTotalAbastecimento = (() => {
            const valorBase = parseFloat(refuel.valor_total || 0);
            if (refuel.inclui_arla == 1) {
                return valorBase + parseFloat(refuel.valor_total_arla || 0);
            }
            return valorBase;
        })();
        const rotaInfo = (refuel.cidade_origem_nome && refuel.cidade_destino_nome)
            ? escapeHtmlAbast(refuel.cidade_origem_nome) + ' → ' + escapeHtmlAbast(refuel.cidade_destino_nome)
            : '-';

        row.innerHTML = `
            <td${dc('data')}>${formatDate(refuel.data_abastecimento)}</td>
            <td${dc('veiculo')}>${escapeHtmlAbast(refuel.veiculo_placa) || '-'}</td>
            <td${dc('motorista')}>${escapeHtmlAbast(refuel.motorista_nome) || '-'}</td>
            <td${dc('posto')}>${escapeHtmlAbast(refuel.posto) || '-'}</td>
            <td${dc('litros')}>${formatNumber(refuel.litros, 3)} L</td>
            <td${dc('valor_litro')}>R$ ${formatNumber(refuel.valor_litro, 2)}</td>
            <td${dc('valor_total')}>R$ ${formatNumber(valorTotalAbastecimento, 2)}</td>
            <td class="col-arla"${dc('arla')}>
                ${refuel.inclui_arla == 1 ?
                    (() => {
                        const percentual = refuel.litros > 0 ? (refuel.litros_arla / refuel.litros) * 100 : 0;
                        let classePercentual = percentual >= 3 && percentual <= 5 ? 'percentual-ok' : (percentual > 5 ? 'percentual-alto' : 'percentual-baixo');
                        const titleAttr = formatArlaPercentTitle(percentual).replace(/"/g, '&quot;');
                        return '<span class="percentual-arla ' + classePercentual + '" title="' + titleAttr + '">' + formatArlaPercentDisplay(percentual) + '</span>';
                    })() :
                    '<span class="text-muted">-</span>'
                }
            </td>
            <td${dc('km')}>${formatNumber(refuel.km_atual, 0)}</td>
            <td${dc('pagamento')}>${escapeHtmlAbast(refuel.forma_pagamento) || '-'}</td>
            <td${dc('rota')}>${rotaInfo}</td>
            <td class="actions">
                <button class="btn-icon edit-btn" data-id="${refuel.id}" title="Editar"><i class="fas fa-edit"></i></button>
                ${refuel.comprovante ? '<button class="btn-icon view-comprovante-btn" data-comprovante="' + escapeHtmlAbast(refuel.comprovante) + '" title="Ver Comprovante"><i class="fas fa-file-alt"></i></button>' : ''}
                <button class="btn-icon delete-btn" data-id="${refuel.id}" title="Excluir"><i class="fas fa-trash"></i></button>
            </td>`;
        tbody.appendChild(row);
    });
}

function setupArlaFields() {
    const incluiArlaCheckbox = document.getElementById('inclui_arla');
    const camposArla = document.getElementById('campos_arla');
    const litrosArlaInput = document.getElementById('litros_arla');
    const valorLitroArlaInput = document.getElementById('valor_litro_arla');
    const valorTotalArlaInput = document.getElementById('valor_total_arla');
    
    if (!incluiArlaCheckbox || !camposArla) return;
    if (_arlaFieldsWired) return;
    _arlaFieldsWired = true;
    incluiArlaCheckbox.addEventListener('change', function() {
        if (this.checked) {
            camposArla.style.display = 'grid';
            // Torna os campos obrigatórios quando ARLA está marcado
            if (litrosArlaInput) litrosArlaInput.required = true;
            if (valorLitroArlaInput) valorLitroArlaInput.required = true;
        } else {
            camposArla.style.display = 'none';
            // Remove obrigatoriedade e limpa os campos
            if (litrosArlaInput) {
                litrosArlaInput.required = false;
                litrosArlaInput.value = '';
            }
            if (valorLitroArlaInput) {
                valorLitroArlaInput.required = false;
                valorLitroArlaInput.value = '';
            }
            if (valorTotalArlaInput) {
                valorTotalArlaInput.required = false;
                valorTotalArlaInput.value = '';
            }
        }
    });
    
    // Cálculo automático do valor total ARLA
    function calcularValorTotalArla() {
        if (!litrosArlaInput || !valorLitroArlaInput || !valorTotalArlaInput) return;
        
        const litros = parseFloat(litrosArlaInput.value.replace(/\./g, '').replace(',', '.')) || 0;
        const valorLitro = parseFloat(valorLitroArlaInput.value.replace(/\./g, '').replace(',', '.')) || 0;
        const valorTotal = litros * valorLitro;
        
        if (!isNaN(valorTotal)) {
            valorTotalArlaInput.value = valorTotal.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        
        // Validação da porcentagem ARLA
        validarPercentualArla();
    }
    
    // Validação da porcentagem ARLA
    function validarPercentualArla() {
        if (!litrosArlaInput) return;
        
        const litrosDiesel = parseFloat(document.getElementById('litros').value.replace(/\./g, '').replace(',', '.')) || 0;
        const litrosArla = parseFloat(litrosArlaInput.value.replace(/\./g, '').replace(',', '.')) || 0;
        
        if (litrosDiesel > 0 && litrosArla > 0) {
            const percentual = (litrosArla / litrosDiesel) * 100;
            
            // Remove classes anteriores
            litrosArlaInput.classList.remove('percentual-ok', 'percentual-alto', 'percentual-baixo');
            
            if (percentual >= 3 && percentual <= 5) {
                litrosArlaInput.classList.add('percentual-ok');
                litrosArlaInput.title = `Percentual ARLA: ${percentual.toFixed(1)}% - Dentro da faixa recomendada (3-5%)`;
            } else if (percentual > 5) {
                litrosArlaInput.classList.add('percentual-alto');
                litrosArlaInput.title = `Percentual ARLA: ${percentual.toFixed(1)}% - ACIMA da faixa recomendada (3-5%)`;
            } else {
                litrosArlaInput.classList.add('percentual-baixo');
                litrosArlaInput.title = `Percentual ARLA: ${percentual.toFixed(1)}% - ABAIXO da faixa recomendada (3-5%)`;
            }
        } else {
            litrosArlaInput.classList.remove('percentual-ok', 'percentual-alto', 'percentual-baixo');
            litrosArlaInput.title = '';
        }
    }
    
    if (litrosArlaInput) litrosArlaInput.addEventListener('input', calcularValorTotalArla);
    if (valorLitroArlaInput) valorLitroArlaInput.addEventListener('input', calcularValorTotalArla);
    
    // Adiciona listener para o campo de litros de diesel para validar porcentagem ARLA
    const litrosDieselInput = document.getElementById('litros');
    if (litrosDieselInput) {
        litrosDieselInput.addEventListener('input', validarPercentualArla);
    }
}

function setupValorTotalCalc() {
    if (_valorTotalCalcWired) return;
    const litrosInput = document.getElementById('litros');
    const valorLitroInput = document.getElementById('valor_litro');
    const valorTotalInput = document.getElementById('valor_total');
    if (!litrosInput || !valorLitroInput || !valorTotalInput) return;
    _valorTotalCalcWired = true;

    function formatarNumero(valor) {
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
    document.getElementById('refuelForm').reset();
    fetch(sfApiUrl(`refuel_data.php?action=get&id=${encodeURIComponent(id)}`))
        .then(function (response) { return response.json(); })
        .then(async function (data) {
            if (!data.success) {
                throw new Error(data.error || 'Erro ao carregar dados do abastecimento');
            }
            await window.openEditRefuelModal(data.data);
        })
        .catch(function (error) {
            console.error('Erro:', error);
            var msg = error && error.message ? error.message : String(error);
            if (typeof showToast === 'function') {
                showToast(msg, 'error');
            } else if (typeof showError === 'function') {
                showError(msg);
            }
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
    if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__) {
        formData.append('csrf_token', window.__SF_CSRF__);
    }
    
    // Envia o formulário
    fetch(sfApiUrl('refuel_actions.php'), {
        method: 'POST',
        body: formData,
        credentials: 'include',
        headers: typeof sfMutationHeaders === 'function' ? sfMutationHeaders() : {}
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
            <td>${escapeHtmlAbast(refuel.veiculo_placa) || '-'}</td>
            <td>${escapeHtmlAbast(refuel.motorista_nome) || '-'}</td>
            <td>${escapeHtmlAbast(refuel.posto) || '-'}</td>
            <td>${escapeHtmlAbast(refuel.tipo_combustivel) || '-'}</td>
            <td>${formatNumber(refuel.litros)}</td>
            <td>R$ ${formatCurrency(refuel.valor_litro)}</td>
            <td>R$ ${formatCurrency(refuel.valor_total)}</td>
            <td>${formatNumber(refuel.km_atual)}</td>
            <td>${escapeHtmlAbast(refuel.forma_pagamento) || '-'}</td>
            <td>${escapeHtmlAbast(refuel.rota_id) || '-'}</td>
            <td class="actions">
                <button class="btn-icon edit-btn" data-id="${refuel.id}" title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
                ${refuel.comprovante ? `
                    <button class="btn-icon view-comprovante-btn" data-comprovante="${escapeHtmlAbast(refuel.comprovante)}" title="Ver Comprovante">
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

let _abastTableButtonsWired = false;

function setupTableButtons() {
    const table = document.getElementById('refuelingTable');
    if (!table || _abastTableButtonsWired) return;
    _abastTableButtonsWired = true;
    table.addEventListener('click', function (e) {
        const editBtn = e.target.closest('.edit-btn');
        if (editBtn) {
            e.preventDefault();
            showEditRefuelModal(editBtn.getAttribute('data-id'));
            return;
        }
        const delBtn = e.target.closest('.delete-btn');
        if (delBtn) {
            e.preventDefault();
            showDeleteConfirmation(delBtn.getAttribute('data-id'));
            return;
        }
        const compBtn = e.target.closest('.view-comprovante-btn');
        if (compBtn) {
            e.preventDefault();
            const comprovante = compBtn.getAttribute('data-comprovante') || '';
            if (comprovante && !/^\s*javascript:/i.test(comprovante)) {
                const href = /^(https?:|\/)/i.test(comprovante) ? comprovante : '../' + comprovante.replace(/^\.\.\//, '');
                window.open(href, '_blank', 'noopener');
            }
        }
    });
}

function loadConsumptionChart() {
    let url = sfApiUrl('refuel_data.php?action=consumption_chart');
    
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
    let url = sfApiUrl('rendimento_veiculo.php');
    
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
    let url = sfApiUrl('refuel_data.php?action=anomalies_chart');
    
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

            if (!data.data || !data.data.length) {
                return;
            }

            const ctx = document.getElementById('anomaliesChart').getContext('2d');
            
            const valores = data.data.map(item => parseFloat(item.valor_litro)).filter(v => !Number.isNaN(v));
            if (!valores.length) return;
            const mediaValor = valores.reduce((a, b) => a + b, 0) / valores.length;
            const maxLitros = Math.max.apply(null, data.data.map(item => parseFloat(item.litros) || 0));
            
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
                                { x: Math.max(maxLitros, 1), y: mediaValor }
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
                                { x: Math.max(maxLitros, 1), y: mediaValor + desvioPadrao }
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
                                { x: Math.max(maxLitros, 1), y: mediaValor - desvioPadrao }
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
    let url = sfApiUrl('refuel_data.php?action=driver_consumption_chart');
    
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
    let url = sfApiUrl('refuel_data.php?action=vehicle_efficiency_chart');
    
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
    let url = sfApiUrl('refuel_data.php?action=monthly_cost_chart');
    
    if (currentFilter) {
        const [year, month] = currentFilter.split('-');
        url += `&year=${year}&month=${month}`;
    }

    const existingChart = Chart.getChart('monthlyCostChart');
    if (existingChart) {
        existingChart.destroy();
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
    const filterMonthInput = document.getElementById('filterMonth');
    if (filterMonthInput && !currentFilter) {
        filterMonthInput.value = '';
    } else if (filterMonthInput && currentFilter) {
        filterMonthInput.value = currentFilter;
    }
    updateFilterButtonState();

    // Setup filter modal buttons
    document.getElementById('applyFilterBtn').addEventListener('click', () => {
        const filterMonth = document.getElementById('filterMonth').value;
        currentFilter = filterMonth;
        refuelingCurrentPage = 1;
        
        // Atualiza todos os dados e gráficos
        Promise.all([
            loadRefuelingData(1),
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
        refuelingCurrentPage = 1;
        
        // Atualiza todos os dados e gráficos
        Promise.all([
            loadRefuelingData(1),
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

function applyRefuelInitialPayload(data, rerenderTable) {
    if (!data || !data.success) return;
    if (data.pagination) {
        refuelingCurrentPage = data.pagination.page || 1;
        updatePagination(data.pagination);
    }
    if (rerenderTable !== false && data.data) {
        updateRefuelingsTable(data.data);
    }
}

let refuelChartsLoaded = false;
function scheduleRefuelChartsLoad() {
    const section = document.querySelector('.analytics-section');
    if (!section) return;
    const load = function () {
        if (refuelChartsLoaded) return;
        refuelChartsLoaded = true;
        Promise.all([
            loadConsumptionChart(),
            loadEfficiencyChart(),
            loadAnomaliesChart(),
            loadDriverConsumptionChart(),
            loadVehicleEfficiencyChart(),
            loadMonthlyCostChart()
        ]).catch(function (error) {
            if (window.__SF_DEBUG__) console.error('Erro ao carregar gráficos iniciais:', error);
        });
    };
    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver(function (entries) {
            if (entries.some(function (e) { return e.isIntersecting; })) {
                load();
                io.disconnect();
            }
        }, { rootMargin: '160px' });
        io.observe(section);
    } else {
        setTimeout(load, 600);
    }
}

function initializePage() {
    restoreRefuelFiltersFromUrl();
    syncRefuelSortIndicators();
    wireRefuelSortHeaders();

    const hasInitial = !!(window.__REFUEL_INITIAL__ && window.__REFUEL_INITIAL__.success);
    if (hasInitial) {
        applyRefuelInitialPayload(window.__REFUEL_INITIAL__, false);
        if (window.__REFUEL_INITIAL__.summary) {
            updateMetricCards(window.__REFUEL_INITIAL__.summary);
        }
    } else {
        loadRefuelingData();
        loadRefuelingSummary();
    }

    loadFilterOptions();
    setupRefuelAnomalies();
    scheduleRefuelChartsLoad();

    var addBtn = document.getElementById('addRefuelBtn');
    if (addBtn) addBtn.addEventListener('click', showAddRefuelModal);
    var emptyCreate = document.getElementById('refuelEmptyCreateBtn');
    if (emptyCreate) emptyCreate.addEventListener('click', showAddRefuelModal);
    var filterBtn = document.getElementById('filterBtn');
    if (filterBtn) filterBtn.addEventListener('click', showFilterModal);

    const searchInput = document.getElementById('searchRefueling');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function () { loadRefuelingData(1); }, 300));
        searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                loadRefuelingData(1);
            }
        });
    }

    const applyFiltersBtn = document.getElementById('applyRefuelFilters');
    if (applyFiltersBtn) {
        applyFiltersBtn.addEventListener('click', function () { loadRefuelingData(1); });
    }

    const clearFiltersBtn = document.getElementById('clearRefuelFilters');
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            ['vehicleFilter', 'driverFilter', 'fuelFilter', 'paymentFilter'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.value = '';
            });
            var filterMonthInput = document.getElementById('filterMonth');
            if (filterMonthInput) filterMonthInput.value = '';
            currentFilter = null;
            refuelingCurrentPage = 1;
            updateFilterButtonState();
            loadRefuelingData(1);
        });
    }

    const perPageRefuelEl = document.getElementById('perPageRefuel');
    if (perPageRefuelEl) {
        perPageRefuelEl.addEventListener('change', function () {
            refuelPerPageInUrl = true;
            loadRefuelingData(1);
        });
    }

    setupTableButtons();
}

function loadFilterOptions() {
    var cacheKey = 'abastecimentos_filter_options';
    var cacheTtl = 5 * 60 * 1000;
    try {
        var cached = sessionStorage.getItem(cacheKey);
        if (cached) {
            var parsed = JSON.parse(cached);
            if (parsed && parsed.ts && (Date.now() - parsed.ts < cacheTtl) && parsed.data) {
                applyFilterOptionsData({ success: true, data: parsed.data });
                return;
            }
        }
    } catch (e) { /* ignore */ }
    fetch(sfApiUrl('refuel_data.php?action=filter_options'), { credentials: 'include' })
        .then(response => {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(data => {
            if (data.success && data.data) {
                try {
                    sessionStorage.setItem(cacheKey, JSON.stringify({ data: data.data, ts: Date.now() }));
                } catch (e) { /* ignore */ }
                applyFilterOptionsData(data);
            }
        })
        .catch(error => {
            if (window.__SF_DEBUG__) console.error('Erro ao carregar opções de filtro:', error);
        });
}

function applyFilterOptionsData(data) {
    if (!data.success || !data.data) return;
    const { vehicles = [], drivers = [] } = data.data;

    const vehicleFilter = document.getElementById('vehicleFilter');
    if (vehicleFilter) {
        const currentValue = vehicleFilter.value;
        vehicleFilter.innerHTML = '<option value="">Todos os veículos</option>';
        vehicles.forEach(vehicle => {
            const option = document.createElement('option');
            option.value = vehicle.id;
            option.textContent = vehicle.placa + (vehicle.modelo ? ' - ' + vehicle.modelo : '');
            vehicleFilter.appendChild(option);
        });
        if (currentValue) vehicleFilter.value = currentValue;
    }

    const driverFilter = document.getElementById('driverFilter');
    if (driverFilter) {
        const currentValue = driverFilter.value;
        driverFilter.innerHTML = '<option value="">Todos os motoristas</option>';
        drivers.forEach(driver => {
            const option = document.createElement('option');
            option.value = driver.id;
            option.textContent = driver.nome;
            driverFilter.appendChild(option);
        });
        if (currentValue) driverFilter.value = currentValue;
    }
}

function carregarMotoristas(veiculoId, data) {
    return fetch(sfApiUrl(`refuel_data.php?action=get_motoristas_by_veiculo_data&veiculo_id=${veiculoId}&data=${data}`))
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
    fetch(sfApiUrl('formas_pagamento.php?action=list'))
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
    return fetch(sfApiUrl(`refuel_data.php?action=get_rotas_by_veiculo_motorista_data&veiculo_id=${refuel.veiculo_id}&motorista_id=${refuel.motorista_id}&data=${refuel.data_rota}`))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                return data.data;
            } else {
                throw new Error(data.error || 'Erro ao carregar rotas');
            }
        });
}

function loadRefuelingSummary() {
    let url = sfApiUrl('refuel_data.php?action=summary');
    if (currentFilter) {
        const parts = currentFilter.split('-');
        url += '&year=' + parts[0] + '&month=' + parts[1];
    }
    return fetch(url, { credentials: 'include' })
        .then(response => response.json())
        .then(data => {
            if (!data.success) throw new Error(data.error || 'Erro ao carregar resumo');
            updateMetricCards(data.data);
        })
        .catch(function (error) {
            if (window.__SF_DEBUG__) console.error('Error loading refueling summary:', error);
        });
}

function updateMetricCards(data) {
    function setText(id, text) {
        var el = document.getElementById(id);
        if (el) el.textContent = text;
    }
    if (!data) return;
    setText('refuelKpiAbastecimentos', formatNumber(data.total_abastecimentos, 0));
    setText('refuelKpiLitros', formatNumber(data.total_litros, 2) + ' L');
    setText('refuelKpiValor', 'R$ ' + formatNumber(data.total_gasto, 2));
    setText('refuelKpiMediaLitro', 'R$ ' + formatNumber(data.media_valor_litro, 2) + '/L');
}

function showDeleteConfirmation(refuelId) {
    fetch(sfApiUrl('refuel_data.php?action=get&id=' + encodeURIComponent(refuelId)), { credentials: 'include' })
        .then(function (response) {
            if (!response.ok) {
                return response.json().then(function (data) {
                    throw new Error(data.error || 'Erro ao carregar dados');
                });
            }
            return response.json();
        })
        .then(function (data) {
            if (!data.success) throw new Error(data.error || 'Erro ao carregar dados');
            var refuel = data.data;
            var msgEl = document.getElementById('deleteConfirmMessage');
            if (msgEl) {
                msgEl.textContent = 'Deseja realmente excluir o abastecimento do veículo ' + (refuel.veiculo_placa || '') + ' realizado em ' + formatDate(refuel.data_abastecimento) + '?';
            }
            pendingDeleteId = refuelId;
            var modal = document.getElementById('deleteConfirmModal');
            if (modal) modal.classList.add('active');
        })
        .catch(function (error) {
            showToast('Erro ao carregar dados: ' + (error.message || 'Erro'), 'error');
        });
}

function deleteRefuel(refuelId) {
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', refuelId);
    if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__) {
        fd.append('csrf_token', window.__SF_CSRF__);
    }
    fetch(sfApiUrl('refuel_actions.php'), {
        method: 'POST',
        body: fd,
        credentials: 'include',
        headers: typeof sfMutationHeaders === 'function' ? sfMutationHeaders() : {}
    })
        .then(function (response) {
            if (!response.ok) {
                return response.json().then(function (data) { throw new Error(data.error || 'Erro ao excluir'); });
            }
            return response.json();
        })
        .then(function (data) {
            if (!data.success) throw new Error(data.error || 'Erro ao excluir');
            showToast(data.message || 'Abastecimento excluído.', 'success');
            loadRefuelingData();
            loadRefuelingSummary();
            loadConsumptionChart();
            loadEfficiencyChart();
            loadAnomaliesChart();
            loadDriverConsumptionChart();
            loadVehicleEfficiencyChart();
            loadMonthlyCostChart();
        })
        .catch(function (error) {
            showToast('Erro ao excluir: ' + (error.message || 'Erro'), 'error');
        });
}

function closeAllModals() {
    document.querySelectorAll('.modal').forEach(function (modal) { modal.classList.remove('active'); });
}

function setupModals() {
    document.querySelectorAll('.close-modal').forEach(function (button) {
        button.addEventListener('click', closeAllModals);
    });
    var cancelBtn = document.getElementById('cancelRefuelBtn');
    if (cancelBtn) cancelBtn.addEventListener('click', closeAllModals);
    document.querySelectorAll('.modal').forEach(function (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeAllModals();
        });
    });
}

function showAddRefuelModal() {
    document.getElementById('refuelForm').reset();
    document.getElementById('refuelId').value = '';
    document.getElementById('modalTitle').textContent = 'Adicionar Abastecimento';
    document.getElementById('refuelModal').classList.add('active');
    var now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('data_abastecimento').value = now.toISOString().slice(0, 16);
    var veiculo = document.getElementById('veiculo_id');
    var motorista = document.getElementById('motorista_id');
    var rota = document.getElementById('rota_id');
    if (veiculo) { veiculo.innerHTML = '<option value="">Selecione um veículo</option>'; veiculo.disabled = true; }
    if (motorista) { motorista.innerHTML = '<option value="">Selecione um motorista</option>'; motorista.disabled = true; }
    if (rota) { rota.innerHTML = '<option value="">Selecione a rota</option>'; rota.disabled = true; }
    setupValorTotalCalc();
    setupArlaFields();
    setupNumericInputs();
}

function setupNumericInputs() {
    if (_numericInputsWired) return;
    _numericInputsWired = true;
    document.querySelectorAll('.numeric-input').forEach(function (input) {
        if (input.value) {
            var numericValue = parseFloat(input.value.replace(',', '.'));
            if (!isNaN(numericValue)) {
                input.value = numericValue.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }
        input.addEventListener('input', function (e) {
            var value = e.target.value.replace(/[^\d,.]/g, '').replace(/\./g, '');
            var parts = value.split(',');
            if (parts.length > 2) value = parts[0] + ',' + parts.slice(1).join('');
            e.target.value = value;
        });
    });
}

function showFilterModal() {
    var m = document.getElementById('filterModal');
    if (m) m.classList.add('active');
}

function formatMonthYear(dateString) {
    var parts = dateString.split('-');
    var date = new Date(parts[0], parts[1] - 1);
    return date.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
}

function updateFilterButtonState() {
    var filterBtn = document.getElementById('filterBtn');
    if (!filterBtn) return;
    if (currentFilter) {
        filterBtn.classList.add('active');
        filterBtn.title = 'Filtro: ' + formatMonthYear(currentFilter);
    } else {
        filterBtn.classList.remove('active');
        filterBtn.title = 'Filtros';
    }
}

function showFilterModal() {
    var m = document.getElementById('filterModal');
    if (m) m.classList.add('active');
}

async function validarKmAbastecimento(rotaId, kmAbastecimento) {
    if (!rotaId || !kmAbastecimento) return { valido: false, mensagem: 'Dados insuficientes para validação' };
    try {
        var formData = new FormData();
        formData.append('action', 'validar_km_abastecimento');
        formData.append('rota_id', rotaId);
        formData.append('km_abastecimento', kmAbastecimento);
        var response = await fetch(sfApiUrl('validar_quilometragem.php'), { method: 'POST', body: formData });
        return await response.json();
    } catch (e) {
        return { valido: false, mensagem: 'Erro na validação' };
    }
}

async function obterAbastecimentosRota(rotaId) {
    if (!rotaId) return null;
    try {
        var response = await fetch(sfApiUrl('validar_quilometragem.php?action=obter_abastecimentos_rota&rota_id=' + encodeURIComponent(rotaId)));
        var data = await response.json();
        return data.success ? data : null;
    } catch (e) {
        return null;
    }
}

function configurarValidacaoKmAbastecimento() {
    var rotaSelect = document.getElementById('rota_id');
    var kmAtualInput = document.getElementById('km_atual');
    var kmAtualHelp = document.getElementById('km_atual_help');
    var kmAtualValidation = document.getElementById('km_atual_validation');
    if (!rotaSelect || !kmAtualInput || rotaSelect.dataset.kmValWired === '1') return;
    rotaSelect.dataset.kmValWired = '1';

    rotaSelect.addEventListener('change', async function () {
        var rotaId = this.value;
        if (rotaId && kmAtualHelp) {
            var dadosRota = await obterAbastecimentosRota(rotaId);
            if (dadosRota) {
                var minValue = dadosRota.total_abastecimentos > 0 ? dadosRota.km_ultimo_abastecimento + 1 : dadosRota.km_saida_rota + 1;
                kmAtualHelp.textContent = 'KM saída: ' + dadosRota.km_saida_rota.toLocaleString('pt-BR') + ' km. Mínimo: ' + minValue.toLocaleString('pt-BR') + ' km';
                kmAtualInput.placeholder = 'Mín: ' + minValue.toLocaleString('pt-BR');
                kmAtualInput.min = minValue;
            }
        } else if (kmAtualHelp) {
            kmAtualHelp.textContent = 'Selecione uma rota para validar a quilometragem';
            kmAtualInput.placeholder = 'Ex: 150000';
            kmAtualInput.min = '';
        }
        if (kmAtualValidation) kmAtualValidation.textContent = '';
    });

    kmAtualInput.addEventListener('blur', async function () {
        var rotaId = rotaSelect.value;
        var kmAbastecimento = this.value;
        if (!kmAtualValidation) return;
        if (rotaId && kmAbastecimento) {
            var validacao = await validarKmAbastecimento(rotaId, kmAbastecimento);
            var msg = validacao.mensagem || '';
            if (validacao.valido) {
                kmAtualValidation.textContent = msg;
                kmAtualValidation.style.color = '#28a745';
                kmAtualInput.style.borderColor = '#28a745';
            } else {
                kmAtualValidation.textContent = msg;
                kmAtualValidation.style.color = '#dc3545';
                kmAtualInput.style.borderColor = '#dc3545';
            }
        } else {
            kmAtualValidation.textContent = '';
            kmAtualInput.style.borderColor = '';
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    if (window.__abastPageInitDone) return;
    window.__abastPageInitDone = true;

    var exportBtn = document.getElementById('exportBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            var params = new URLSearchParams();
            var search = document.getElementById('searchRefueling');
            if (search && search.value.trim()) params.set('search', search.value.trim());
            var v = document.getElementById('vehicleFilter'); if (v && v.value) params.set('veiculo', v.value);
            var d = document.getElementById('driverFilter'); if (d && d.value) params.set('motorista', d.value);
            var f = document.getElementById('fuelFilter'); if (f && f.value) params.set('combustivel', f.value);
            var p = document.getElementById('paymentFilter'); if (p && p.value) params.set('pagamento', p.value);
            if (currentFilter) {
                var parts = currentFilter.split('-');
                if (parts.length === 2) { params.set('year', parts[0]); params.set('month', parts[1]); }
            }
            window.open(sfApiUrl('refuel_export.php?' + params.toString()), '_blank');
            showToast('Exportação iniciada.', 'info');
        });
    }

    var deleteModal = document.getElementById('deleteConfirmModal');
    function closeDeleteModal() {
        if (deleteModal) deleteModal.classList.remove('active');
        pendingDeleteId = null;
    }
    var closeDel = document.getElementById('closeDeleteModal');
    var cancelDel = document.getElementById('cancelDeleteBtn');
    var confirmDel = document.getElementById('confirmDeleteBtn');
    if (closeDel) closeDel.addEventListener('click', closeDeleteModal);
    if (cancelDel) cancelDel.addEventListener('click', closeDeleteModal);
    if (confirmDel) {
        confirmDel.addEventListener('click', function () {
            if (pendingDeleteId) {
                deleteRefuel(pendingDeleteId);
                closeDeleteModal();
            }
        });
    }

    if (document.body.classList.contains('abastecimentos-modern') && typeof window.initForncTableColumns === 'function') {
        window.initForncTableColumns({
            tableSelector: '#refuelingTable',
            toggleListSelector: '#refuelColumnsToggleList',
            storageKey: 'sf_abastecimentos_table_cols_v1',
            columnsBtnId: 'refuelColumnsBtn',
            modalId: 'refuelColumnsModal',
            saveBtnId: 'refuelColumnsSaveBtn'
        });
    }

    initializePage();
    setupModals();
    setupFilters();
    requestAnimationFrame(function () {
        requestAnimationFrame(configurarValidacaoKmAbastecimento);
    });
}); 