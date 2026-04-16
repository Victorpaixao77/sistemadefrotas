// Routes management JavaScript
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
})(typeof window !== 'undefined' ? window : this);

function sfErrUserMessage(err, fallback) {
    if (typeof sfSafeErrorMessage === 'function') {
        return sfSafeErrorMessage(err, fallback);
    }
    var m = (err && err.message) ? String(err.message) : (fallback || 'Erro');
    if (/maximum call stack size exceeded|too much recursion/i.test(m)) {
        return 'Erro no navegador. Atualize a página.';
    }
    return m;
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize page
    initializePage();

    wireRoutesKpiClicks();
    wireRoutesSortHeaders();
    wireRouteModalTabs();
    wireRoutesKeyboardShortcuts();
    wireRoutesBatchUi();
    wireColumnsModal();
    applyRoutesColumnVisibility();
    
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
    
    // Carrega os dados iniciais do mês atual (ou do período da URL)
    const currentDate = new Date();
    loadDashboardData(currentDate.getMonth() + 1, currentDate.getFullYear());
});

let currentPage = 1;
let totalPages = 1;
let searchDebounceTimer = null;
let currentRouteDateFrom = null;
let currentRouteDateTo = null;
let currentRouteFilterMonth = null;
let currentSortField = 'data_rota';
let currentSortDir = 'DESC';
const ROUTES_MODERN_LS = 'routes_modern_prefs_v2';

/** Primeira ordenação ao escolher coluna: texto A→Z, datas recentes primeiro, números maior→menor, status 0 antes de 1. */
function routesDefaultSortDir(field) {
    const textLike = ['motorista_nome', 'veiculo_placa', 'rota', 'descricao_carga'];
    if (textLike.indexOf(field) >= 0) return 'ASC';
    if (field === 'data_rota' || field === 'data_saida' || field === 'data_chegada') return 'DESC';
    if (field === 'no_prazo') return 'ASC';
    return 'DESC';
}

function getStoredRoutesColumnVisibility() {
    try {
        const raw = localStorage.getItem(ROUTES_MODERN_LS);
        if (!raw) return {};
        const prefs = JSON.parse(raw);
        return prefs.columnVisibility && typeof prefs.columnVisibility === 'object' ? prefs.columnVisibility : {};
    } catch (e) {
        return {};
    }
}

/** Salva filtros/ordenação sem alterar colunas salvas (último “Salvar preferências” do modal). */
function saveRoutesNonColumnPrefs() {
    const searchInput = document.getElementById('searchRoute');
    const statusEl = document.getElementById('statusFilter');
    const driverEl = document.getElementById('driverFilter');
    const perPageEl = document.getElementById('perPageRoutes');
    const vis = getStoredRoutesColumnVisibility();
    try {
        localStorage.setItem(ROUTES_MODERN_LS, JSON.stringify({
            search: searchInput ? searchInput.value : '',
            delivery: statusEl ? statusEl.value : '',
            driver: driverEl ? driverEl.value : '',
            per_page: perPageEl ? perPageEl.value : '10',
            sort: currentSortField,
            dir: currentSortDir,
            columnVisibility: vis
        }));
    } catch (e) {
        /* ignore */
    }
}

/** Persiste também as colunas marcadas no modal (botão Salvar preferências). */
function saveRoutesModernPrefs() {
    const searchInput = document.getElementById('searchRoute');
    const statusEl = document.getElementById('statusFilter');
    const driverEl = document.getElementById('driverFilter');
    const perPageEl = document.getElementById('perPageRoutes');
    const vis = {};
    document.querySelectorAll('#columnsToggleList input[data-col-toggle]').forEach(cb => {
        const k = cb.getAttribute('data-col-toggle');
        if (k) vis[k] = cb.checked;
    });
    try {
        localStorage.setItem(ROUTES_MODERN_LS, JSON.stringify({
            search: searchInput ? searchInput.value : '',
            delivery: statusEl ? statusEl.value : '',
            driver: driverEl ? driverEl.value : '',
            per_page: perPageEl ? perPageEl.value : '10',
            sort: currentSortField,
            dir: currentSortDir,
            columnVisibility: vis
        }));
    } catch (e) {
        /* ignore */
    }
}

function mergeRoutesModernPrefsFromStorage() {
    const raw = localStorage.getItem(ROUTES_MODERN_LS);
    if (!raw) return;
    let prefs;
    try {
        prefs = JSON.parse(raw);
    } catch (e) {
        return;
    }
    const p = new URLSearchParams(window.location.search);
    const searchInput = document.getElementById('searchRoute');
    if (searchInput && !p.has('search') && typeof prefs.search === 'string') {
        searchInput.value = prefs.search;
    }
    const statusEl = document.getElementById('statusFilter');
    if (statusEl && !p.has('delivery') && !p.has('status') && typeof prefs.delivery === 'string') {
        statusEl.value = prefs.delivery;
    }
    const driverEl = document.getElementById('driverFilter');
    if (driverEl && !p.has('driver') && typeof prefs.driver === 'string') {
        driverEl.value = prefs.driver;
    }
    const perPageEl = document.getElementById('perPageRoutes');
    if (perPageEl && !p.has('per_page') && prefs.per_page) {
        perPageEl.value = String(prefs.per_page);
    }
    if (!p.has('sort') && prefs.sort) currentSortField = prefs.sort;
    if (!p.has('dir') && prefs.dir) currentSortDir = prefs.dir;
    if (prefs.columnVisibility && typeof prefs.columnVisibility === 'object') {
        Object.keys(prefs.columnVisibility).forEach(k => {
            const cb = document.querySelector(`#columnsToggleList input[data-col-toggle="${k}"]`);
            if (cb) cb.checked = !!prefs.columnVisibility[k];
        });
        applyRoutesColumnVisibility();
    }
}

function escapeHtmlRoute(s) {
    if (s == null) return '';
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function fmtRouteDateTime(v) {
    if (!v) return '-';
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return '-';
    return d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function fmtRouteKmField(v) {
    if (v === null || v === undefined || v === '') return '-';
    const n = Number(v);
    if (Number.isNaN(n)) return '-';
    return n.toLocaleString('pt-BR', { maximumFractionDigits: 0 });
}

function fmtRoutePctField(v) {
    if (v === null || v === undefined || v === '') return '-';
    const n = Number(v);
    if (Number.isNaN(n)) return '-';
    return n.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%';
}

function fmtRoutePeso(v) {
    if (v === null || v === undefined || v === '') return '-';
    const n = Number(v);
    if (Number.isNaN(n)) return '-';
    return n.toLocaleString('pt-BR', { maximumFractionDigits: 2 });
}

function truncateRouteDesc(s) {
    if (s == null || s === '') return '-';
    const t = String(s);
    return t.length > 48 ? t.slice(0, 48) + '…' : t;
}

function buildRouteOptionalCells(route) {
    return `
                <td data-col="col_id">${route.id != null ? escapeHtmlRoute(String(route.id)) : '-'}</td>
                <td data-col="col_saida">${escapeHtmlRoute(fmtRouteDateTime(route.data_saida))}</td>
                <td data-col="col_chegada">${escapeHtmlRoute(fmtRouteDateTime(route.data_chegada))}</td>
                <td data-col="col_km_saida">${escapeHtmlRoute(fmtRouteKmField(route.km_saida))}</td>
                <td data-col="col_km_chegada">${escapeHtmlRoute(fmtRouteKmField(route.km_chegada))}</td>
                <td data-col="col_km_vazio">${escapeHtmlRoute(fmtRouteKmField(route.km_vazio))}</td>
                <td data-col="col_total_km">${escapeHtmlRoute(fmtRouteKmField(route.total_km))}</td>
                <td data-col="col_comissao">${route.comissao != null && route.comissao !== '' ? formatCurrency(route.comissao) : '-'}</td>
                <td data-col="col_eficiencia">${escapeHtmlRoute(fmtRoutePctField(route.eficiencia_viagem))}</td>
                <td data-col="col_pct_vazio">${escapeHtmlRoute(fmtRoutePctField(route.percentual_vazio))}</td>
                <td data-col="col_peso">${escapeHtmlRoute(fmtRoutePeso(route.peso_carga))}</td>
                <td data-col="col_desc_carga">${escapeHtmlRoute(truncateRouteDesc(route.descricao_carga))}</td>`;
}

function applyRoutesColumnVisibility() {
    document.querySelectorAll('#columnsToggleList input[data-col-toggle]').forEach(cb => {
        const key = cb.getAttribute('data-col-toggle');
        const on = cb.checked;
        document.querySelectorAll(`#routesDataTable [data-col="${key}"]`).forEach(cell => {
            cell.style.display = on ? '' : 'none';
        });
    });
}

function setKpiActiveFromDelivery() {
    const d = document.getElementById('statusFilter')?.value || '';
    document.querySelectorAll('.dashboard-card[data-kpi]').forEach(c => c.classList.remove('kpi-active'));
    if (d === 'no_prazo') {
        document.querySelector('.dashboard-card[data-kpi="no_prazo"]')?.classList.add('kpi-active');
    } else if (d === 'atrasado') {
        document.querySelector('.dashboard-card[data-kpi="atrasadas"]')?.classList.add('kpi-active');
    }
}

function wireRoutesKpiClicks() {
    document.querySelectorAll('.dashboard-card[data-kpi]').forEach(card => {
        card.addEventListener('click', function () {
            const k = this.getAttribute('data-kpi');
            const sel = document.getElementById('statusFilter');
            document.querySelectorAll('.dashboard-card[data-kpi]').forEach(c => c.classList.remove('kpi-active'));
            if (k === 'no_prazo' && sel) {
                sel.value = 'no_prazo';
                this.classList.add('kpi-active');
            } else if (k === 'atrasadas' && sel) {
                sel.value = 'atrasado';
                this.classList.add('kpi-active');
            } else if ((k === 'total' || k === 'concluidas' || k === 'distancia' || k === 'frete' || k === 'eficiencia' || k === 'km_vazio') && sel) {
                sel.value = '';
            } else {
                return;
            }
            currentPage = 1;
            saveRoutesNonColumnPrefs();
            loadRouteData(1);
        });
    });
}

function wireRoutesSortHeaders() {
    document.querySelectorAll('#routesDataTable thead th.sortable').forEach(th => {
        th.addEventListener('click', function () {
            const field = this.getAttribute('data-sort');
            if (!field) return;
            if (currentSortField === field) {
                currentSortDir = currentSortDir === 'ASC' ? 'DESC' : 'ASC';
            } else {
                currentSortField = field;
                currentSortDir = routesDefaultSortDir(field);
            }
            document.querySelectorAll('#routesDataTable thead th.sortable').forEach(h => {
                h.classList.remove('sorted');
                const ind = h.querySelector('.sort-ind');
                if (ind) ind.textContent = '⇅';
            });
            this.classList.add('sorted');
            const ind = this.querySelector('.sort-ind');
            if (ind) ind.textContent = currentSortDir === 'ASC' ? '▲' : '▼';
            currentPage = 1;
            saveRoutesNonColumnPrefs();
            loadRouteData(1);
        });
    });
}

function syncSortIndicators() {
    document.querySelectorAll('#routesDataTable thead th.sortable').forEach(th => {
        const field = th.getAttribute('data-sort');
        const ind = th.querySelector('.sort-ind');
        if (!ind) return;
        th.classList.toggle('sorted', field === currentSortField);
        if (field === currentSortField) {
            ind.textContent = currentSortDir === 'ASC' ? '▲' : '▼';
        } else {
            ind.textContent = '⇅';
        }
    });
}

function wireRouteModalTabs() {
    document.querySelectorAll('.route-tab-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const tab = this.getAttribute('data-route-tab');
            document.querySelectorAll('.route-tab-btn').forEach(b => b.classList.remove('is-active'));
            this.classList.add('is-active');
            document.querySelectorAll('.route-modal-tab-pane').forEach(p => {
                p.classList.toggle('is-active', p.getAttribute('data-route-tab') === tab);
            });
        });
    });
}

function resetRouteModalTabs() {
    document.querySelectorAll('.route-tab-btn').forEach((b, i) => b.classList.toggle('is-active', i === 0));
    document.querySelectorAll('.route-modal-tab-pane').forEach((p, i) => p.classList.toggle('is-active', i === 0));
}

function wireRoutesKeyboardShortcuts() {
    document.addEventListener('keydown', function (e) {
        const t = e.target;
        const tag = t && t.tagName;
        const inField = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || (t && t.isContentEditable);
        // Escape em modais: js/modal_a11y.js (captura, um modal por vez)
        if (inField && e.key !== 'Escape') return;
        if (e.key === 'n' || e.key === 'N') {
            if (!e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                showAddRouteModal();
                resetRouteModalTabs();
            }
            return;
        }
        if (e.key === '/') {
            e.preventDefault();
            const si = document.getElementById('searchRoute');
            if (si) si.focus();
        }
    });
}

function wireRoutesBatchUi() {
    const bar = document.getElementById('routesBatchBar');
    const all = document.getElementById('routesSelectAll');
    const countEl = document.getElementById('routesBatchCount');
    function refreshBar() {
        const n = document.querySelectorAll('.route-row-check:checked').length;
        if (bar) bar.classList.toggle('visible', n > 0);
        if (countEl) countEl.textContent = n + ' selecionada(s)';
    }
    if (all) {
        all.addEventListener('change', function () {
            document.querySelectorAll('.route-row-check').forEach(cb => {
                cb.checked = all.checked;
            });
            refreshBar();
        });
    }
    document.getElementById('routesClearSelectionBtn')?.addEventListener('click', function () {
        document.querySelectorAll('.route-row-check').forEach(cb => {
            cb.checked = false;
        });
        if (all) all.checked = false;
        refreshBar();
    });
    document.getElementById('routesExportSelectedBtn')?.addEventListener('click', function () {
        const ids = [];
        const rows = [];
        document.querySelectorAll('.route-row-check:checked').forEach(cb => {
            ids.push(cb.value);
            rows.push(cb.closest('tr'));
        });
        if (!rows.length) {
            showRouteToast('Selecione ao menos uma rota.', 'warning');
            return;
        }
        const headerRow = [];
        document.querySelectorAll('#routesDataTable thead th').forEach(th => {
            if (th.style.display === 'none') return;
            if (th.querySelector('input[type="checkbox"]')) return;
            headerRow.push(th.textContent.replace(/[⇅▼▲]/g, '').replace(/\s+/g, ' ').trim());
        });
        const lines = [headerRow];
        rows.forEach(tr => {
            const vals = [];
            tr.querySelectorAll('td').forEach(td => {
                if (td.style.display === 'none') return;
                if (td.classList.contains('col-sel') || td.classList.contains('actions')) return;
                vals.push(td.textContent.trim().replace(/\s+/g, ' '));
            });
            lines.push(vals);
        });
        const csv = lines.map(r => r.map(c => '"' + String(c).replace(/"/g, '""') + '"').join(';')).join('\r\n');
        const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'rotas_selecionadas_' + new Date().toISOString().slice(0, 10) + '.csv';
        a.click();
        URL.revokeObjectURL(a.href);
        showRouteToast('CSV das selecionadas gerado.', 'success');
    });
    document.getElementById('routesEmptyCreateBtn')?.addEventListener('click', function () {
        showAddRouteModal();
        resetRouteModalTabs();
    });
    document.getElementById('routeTableContainer')?.addEventListener('change', function (e) {
        if (e.target.classList.contains('route-row-check')) refreshBar();
    });
}

function wireColumnsModal() {
    const btn = document.getElementById('columnsBtn');
    const modal = document.getElementById('columnsModal');
    if (btn && modal) {
        btn.addEventListener('click', function () {
            modal.style.display = 'block';
        });
    }
    document.querySelectorAll('#columnsToggleList input[data-col-toggle]').forEach(cb => {
        cb.addEventListener('change', function () {
            applyRoutesColumnVisibility();
        });
    });
    const saveBtn = document.getElementById('columnsSavePrefsBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            saveRoutesModernPrefs();
            showRouteToast('Preferências de colunas salvas neste navegador.', 'success');
            if (modal) modal.style.display = 'none';
        });
    }
}

function duplicateRouteById(routeId) {
    fetch(sfApiUrl('route_data.php?action=view&id=' + encodeURIComponent(routeId)))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                fillRouteForm(data.data);
                document.getElementById('routeId').value = '';
                document.getElementById('modalTitle').textContent = 'Duplicar Rota';
                const obs = document.getElementById('observacoes');
                if (obs && obs.value) obs.value = obs.value + '\n(Cópia)';
                else if (obs) obs.value = '(Cópia)';
                document.getElementById('routeModal').style.display = 'block';
                setupFormCalculations();
                resetRouteModalTabs();
            } else {
                throw new Error(data.error || 'Erro ao carregar rota');
            }
        })
        .catch(err => {
            console.error(err);
            showRouteToast('Erro ao duplicar: ' + sfErrUserMessage(err), 'error');
        });
}

function showRouteToast(msg, type) {
    if (typeof window.showToast === 'function') {
        try {
            window.showToast(msg, type || 'info');
        } catch (e) {
            console.warn('showToast error:', e);
            alert(msg);
        }
    } else {
        alert(msg);
    }
}

function initializePage() {
    // Restaurar filtros da URL
    const urlParams = new URLSearchParams(window.location.search);
    const searchInput = document.getElementById('searchRoute');
    if (searchInput && urlParams.has('search')) searchInput.value = urlParams.get('search') || '';
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter) {
        if (urlParams.has('delivery')) statusFilter.value = urlParams.get('delivery') || '';
        else if (urlParams.has('status')) statusFilter.value = urlParams.get('status') || '';
    }
    const driverFilter = document.getElementById('driverFilter');
    if (driverFilter && urlParams.has('driver')) driverFilter.value = urlParams.get('driver') || '';
    const dateFilter = document.getElementById('dateFilter');
    if (dateFilter && urlParams.has('date')) dateFilter.value = urlParams.get('date') || '';
    if (urlParams.has('date_from') && urlParams.has('date_to')) {
        currentRouteDateFrom = urlParams.get('date_from');
        currentRouteDateTo = urlParams.get('date_to');
        currentRouteFilterMonth = null;
        const fdf = document.getElementById('filterDateFrom');
        const fdt = document.getElementById('filterDateTo');
        if (fdf) fdf.value = currentRouteDateFrom;
        if (fdt) fdt.value = currentRouteDateTo;
    } else if (urlParams.has('month')) {
        currentRouteFilterMonth = urlParams.get('month');
        currentRouteDateFrom = null;
        currentRouteDateTo = null;
        const fm = document.getElementById('filterMonth');
        if (fm) fm.value = currentRouteFilterMonth;
    }

    if (urlParams.has('sort')) {
        currentSortField = urlParams.get('sort') || 'data_rota';
    }
    if (urlParams.has('dir')) {
        currentSortDir = (urlParams.get('dir') || 'DESC').toUpperCase() === 'ASC' ? 'ASC' : 'DESC';
    }

    mergeRoutesModernPrefsFromStorage();
    syncSortIndicators();
    
    // Get current page and per_page from URL or use default
    const page = parseInt(urlParams.get('page')) || 1;
    const perPageFromUrl = parseInt(urlParams.get('per_page'), 10);
    const perPageEl = document.getElementById('perPageRoutes');
    if (perPageEl && !Number.isNaN(perPageFromUrl) && [5, 10, 25, 50, 100].indexOf(perPageFromUrl) >= 0) {
        perPageEl.value = String(perPageFromUrl);
    }
    
    // Load route data from API
    loadRouteData(page);
    
    const importNfeXmlBtn = document.getElementById('importNfeXmlBtn');
    if (importNfeXmlBtn) {
        importNfeXmlBtn.addEventListener('click', function() {
            document.getElementById('importNfeXmlForm').reset();
            document.getElementById('importNfeXmlStatus').style.display = 'none';
            document.getElementById('importNfeXmlModal').style.display = 'block';
        });
    }
    
    const importNfeXmlForm = document.getElementById('importNfeXmlForm');
    if (importNfeXmlForm) {
        importNfeXmlForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var fileInput = document.getElementById('nfeXmlFile');
            var file = fileInput && fileInput.files[0];
            if (!file) {
                showRouteToast('Selecione um arquivo XML da NF-e.', 'error');
                return;
            }
            var statusEl = document.getElementById('importNfeXmlStatus');
            var submitBtn = document.getElementById('importNfeXmlSubmit');
            statusEl.style.display = 'block';
            statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importando...';
            statusEl.className = 'mt-2 text-info';
            submitBtn.disabled = true;
            var formData = new FormData();
            formData.append('xml_file', file);
            if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__) {
                formData.append('csrf_token', window.__SF_CSRF__);
            }
            fetch(sfApiUrl('route_actions.php?action=import_nfe_xml'), {
                method: 'POST',
                body: formData,
                credentials: 'include',
                headers: typeof sfMutationHeaders === 'function' ? sfMutationHeaders() : {}
            })
            .then(function(r) {
                return r.json().then(function(data) {
                    if (!r.ok) {
                        data = data || {};
                        data.success = false;
                        data.message = data.message || data.error || 'Erro na requisição (' + r.status + ')';
                    }
                    return data;
                }, function() {
                    return { success: false, message: 'Resposta inválida do servidor (' + r.status + ').' };
                });
            })
            .then(function(data) {
                if (data.success) {
                    var html = '<i class="fas fa-check-circle text-success"></i> Rota criada com sucesso.';
                    if (data.warning) {
                        html += '<div class="mt-2 p-2 rounded" style="background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.4); font-size: 0.9rem;"><i class="fas fa-exclamation-triangle text-warning"></i> ' + (data.warning || '') + '</div>';
                    }
                    statusEl.innerHTML = html;
                    statusEl.className = 'mt-2 text-success';
                    showRouteToast(data.message || 'Rota criada a partir da NF-e.', 'success');
                    if (data.warning) {
                        showRouteToast(data.warning, 'warning');
                    }
                    document.getElementById('importNfeXmlModal').style.display = 'none';
                    loadRouteData(currentPage);
                    loadDashboardData(new Date().getMonth() + 1, new Date().getFullYear());
                } else {
                    statusEl.innerHTML = '<i class="fas fa-exclamation-circle text-danger"></i> ' + (data.message || 'Erro ao importar.');
                    statusEl.className = 'mt-2 text-danger';
                    showRouteToast(data.message || 'Erro ao importar XML.', 'error');
                }
            })
            .catch(function(err) {
                statusEl.innerHTML = '<i class="fas fa-exclamation-circle text-danger"></i> Erro: ' + sfErrUserMessage(err);
                statusEl.className = 'mt-2 text-danger';
                showRouteToast('Erro ao importar: ' + sfErrUserMessage(err), 'error');
            })
            .finally(function() {
                submitBtn.disabled = false;
            });
        });
    }
    
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
        searchInput.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                if (searchDebounceTimer) {
                    clearTimeout(searchDebounceTimer);
                    searchDebounceTimer = null;
                }
                currentPage = 1;
                loadRouteData(1);
            }
        });
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

    const applyFiltersBtn = document.getElementById('applyRouteFilters');
    if (applyFiltersBtn) {
        applyFiltersBtn.addEventListener('click', () => {
            currentPage = 1;
            saveRoutesNonColumnPrefs();
            loadRouteData(1);
        });
    }

    const clearFiltersBtn = document.getElementById('clearRouteFilters');
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';

            if (statusFilter) statusFilter.value = '';
            if (driverFilter) driverFilter.value = '';

            const vehicleFilter = document.getElementById('vehicleFilter');
            if (vehicleFilter) vehicleFilter.value = '';

            const dateFilterInput = document.getElementById('dateFilter');
            if (dateFilterInput) dateFilterInput.value = '';

            document.querySelectorAll('.dashboard-card[data-kpi]').forEach(c => c.classList.remove('kpi-active'));

            currentPage = 1;
            loadRouteData(1);
            saveRoutesNonColumnPrefs();
        });
    }

    const perPageSelect = document.getElementById('perPageRoutes');
    if (perPageSelect) {
        perPageSelect.addEventListener('change', function() {
            currentPage = 1;
            saveRoutesNonColumnPrefs();
            loadRouteData(1);
        });
    }
}

function handleSearch(event) {
    if (searchDebounceTimer) {
        clearTimeout(searchDebounceTimer);
    }
    searchDebounceTimer = setTimeout(() => {
        currentPage = 1;
        saveRoutesNonColumnPrefs();
        loadRouteData(1);
    }, 300);
}

function handleFilters() {
    currentPage = 1;
    saveRoutesNonColumnPrefs();
    loadRouteData(1);
}

function loadSelectOptions() {
    // Load estados
    fetch(sfApiUrl('route_actions.php?action=get_estados'))
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
    fetch(sfApiUrl('route_actions.php?action=get_motoristas'))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateDriverSelects(data.data);
            }
        })
        .catch(error => console.error('Erro ao carregar motoristas:', error));
    
    // Load veículos
    fetch(sfApiUrl('route_actions.php?action=get_veiculos'))
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
        const prev = driverFilter.value;
        driverFilter.innerHTML = '<option value="">Todos os motoristas</option>' + options;
        if (prev) driverFilter.value = prev;
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
    
    const loadingEl = document.getElementById('routeTableLoading');
    const tableEl = document.querySelector('.data-table');
    if (loadingEl) loadingEl.style.display = 'block';
    if (tableEl) tableEl.style.visibility = 'hidden';
    
    const searchTerm = document.getElementById('searchRoute')?.value || '';
    const delivery = document.getElementById('statusFilter')?.value || '';
    const driver = document.getElementById('driverFilter')?.value || '';
    const date = document.getElementById('dateFilter')?.value || '';
    
    const perPageSelect = document.getElementById('perPageRoutes');
    const perPageFromSelect = perPageSelect ? parseInt(perPageSelect.value, 10) : 10;
    const limit = [5, 10, 25, 50, 100].indexOf(perPageFromSelect) >= 0 ? perPageFromSelect : 10;
    
    let url = sfApiUrl(`route_data.php?action=list&page=${page}&limit=${limit}`);
    if (searchTerm) url += `&search=${encodeURIComponent(searchTerm)}`;
    if (delivery) url += `&delivery=${encodeURIComponent(delivery)}`;
    if (driver) url += `&driver=${encodeURIComponent(driver)}`;
    if (date) url += `&date=${encodeURIComponent(date)}`;
    if (currentRouteDateFrom) url += `&date_from=${encodeURIComponent(currentRouteDateFrom)}`;
    if (currentRouteDateTo) url += `&date_to=${encodeURIComponent(currentRouteDateTo)}`;
    url += `&sort=${encodeURIComponent(currentSortField)}&dir=${encodeURIComponent(currentSortDir)}`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (loadingEl) loadingEl.style.display = 'none';
            if (tableEl) tableEl.style.visibility = '';
            if (data.success) {
                updateRouteTable(data.data);
                if (data.pagination) {
                    totalPages = data.pagination.total_pages;
                    updatePaginationButtons(data.pagination);
                    const params = new URLSearchParams();
                    if (currentPage !== 1) params.set('page', currentPage);
                    if (limit !== 10) params.set('per_page', limit);
                    if (searchTerm) params.set('search', searchTerm);
                    if (delivery) params.set('delivery', delivery);
                    if (driver) params.set('driver', driver);
                    if (date) params.set('date', date);
                    if (currentRouteDateFrom) params.set('date_from', currentRouteDateFrom);
                    if (currentRouteDateTo) params.set('date_to', currentRouteDateTo);
                    if (currentRouteFilterMonth && !currentRouteDateFrom) params.set('month', currentRouteFilterMonth);
                    if (currentSortField !== 'data_rota' || currentSortDir !== 'DESC') {
                        params.set('sort', currentSortField);
                        params.set('dir', currentSortDir);
                    }
                    const qs = params.toString();
                    const newUrl = window.location.pathname + (qs ? '?' + qs : '');
                    if (window.location.search !== (qs ? '?' + qs : '')) {
                        window.history.replaceState({}, '', newUrl);
                    }
                }
                saveRoutesNonColumnPrefs();
                setKpiActiveFromDelivery();
            } else {
                throw new Error(data.error || 'Erro ao carregar dados das rotas');
            }
        })
        .catch(error => {
            if (loadingEl) loadingEl.style.display = 'none';
            if (tableEl) tableEl.style.visibility = '';
            console.error('Erro ao carregar dados das rotas:', error);
            showRouteToast('Erro ao carregar dados: ' + sfErrUserMessage(error), 'error');
            const tbody = document.querySelector('.data-table tbody');
            if (tbody) {
                const cc = routesTableColspan();
                tbody.innerHTML = `
                    <tr>
                        <td colspan="${cc}" class="text-center text-danger">
                            Erro ao carregar dados: ${sfErrUserMessage(error)}
                        </td>
                    </tr>
                `;
            }
        });
}

function routesTableColspan() {
    const th = document.querySelectorAll('#routesDataTable thead th');
    return th.length || 21;
}

function updateRouteTable(routes) {
    const tbody = document.querySelector('.data-table tbody');
    if (!tbody) {
        console.error('Elemento tbody não encontrado');
        return;
    }
    const cc = routesTableColspan();
    const emptyEl = document.getElementById('routesEmptyState');
    const tableEl = document.getElementById('routesDataTable') || document.querySelector('.data-table');
    if (emptyEl && tableEl) {
        const empty = !routes || routes.length === 0;
        emptyEl.classList.toggle('visible', empty);
        tableEl.style.display = empty ? 'none' : '';
    }
    
    tbody.innerHTML = '';
    
    if (routes && routes.length > 0) {
        routes.forEach(route => {
            const row = document.createElement('tr');
            const badgeClass = route.no_prazo ? 'status-ok' : 'status-late';
            const selCell = `<td class="col-sel" data-col="sel"><input type="checkbox" class="route-row-check" value="${route.id}" aria-label="Selecionar rota"></td>`;
            const optCells = buildRouteOptionalCells(route);
            row.innerHTML = `
                ${selCell}
                <td data-col="data">${formatDate(route.data_rota)}</td>
                <td data-col="motorista">${route.motorista_nome || '-'}</td>
                <td data-col="veiculo">${route.veiculo_placa || '-'}</td>
                <td data-col="rota">${route.cidade_origem_nome || '-'} → ${route.cidade_destino_nome || '-'}</td>
                <td data-col="dist">${formatDistance(route.distancia_km)}</td>
                <td data-col="frete">${formatCurrency(route.frete)}</td>
                ${optCells}
                <td data-col="status"><span class="status-badge ${badgeClass}">${route.no_prazo ? 'No Prazo' : 'Atrasado'}</span></td>
                <td class="actions" data-col="acoes">
                    <button class="btn-icon view-btn" data-id="${route.id}" title="Ver" aria-label="Ver detalhes da rota">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-icon edit-btn" data-id="${route.id}" title="Editar" aria-label="Editar rota">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-icon expenses-btn" data-id="${route.id}" title="Despesas da viagem" aria-label="Despesas da viagem">
                        <i class="fas fa-money-bill"></i>
                    </button>
                    <button class="btn-icon duplicate-btn" data-id="${route.id}" title="Duplicar" aria-label="Duplicar rota">
                        <i class="fas fa-copy"></i>
                    </button>
                    <button class="btn-icon delete-btn" data-id="${route.id}" title="Excluir" aria-label="Excluir rota">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    } else {
        tbody.innerHTML = '<tr><td colspan="' + cc + '" class="text-center">Nenhuma rota encontrada</td></tr>';
    }
    
    setupTableButtons();
    applyRoutesColumnVisibility();
    const all = document.getElementById('routesSelectAll');
    if (all) all.checked = false;
    document.getElementById('routesBatchBar')?.classList.remove('visible');
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

    document.querySelectorAll('.duplicate-btn').forEach(button => {
        button.addEventListener('click', function() {
            const routeId = this.getAttribute('data-id');
            duplicateRouteById(routeId);
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
    const paginationDiv = document.querySelector('.pagination');
    if (!paginationDiv) return;
    // Delegação: um único listener no container para que funcione após substituir o HTML
    paginationDiv.addEventListener('click', function(e) {
        const btn = e.target.closest('a.pagination-btn');
        if (!btn || btn.classList.contains('disabled')) return;
        e.preventDefault();
        const direction = btn.getAttribute('data-direction');
        const newPage = direction === 'prev' ? currentPage - 1 : currentPage + 1;
        if (newPage >= 1 && newPage <= totalPages) {
            loadRouteData(newPage);
        }
    });
}

function updatePaginationButtons(pagination) {
    const paginationDiv = document.querySelector('.pagination');
    if (!paginationDiv) return;
    
    const perPageSelect = document.getElementById('perPageRoutes');
    const perPage = perPageSelect ? parseInt(perPageSelect.value, 10) : 10;
    const perPageParam = [5, 10, 25, 50, 100].indexOf(perPage) >= 0 ? perPage : 10;
    const totalRegistros = (pagination && pagination.total) ? pagination.total : (currentPage * perPageParam);
    const paginationText = totalPages > 1
        ? `Página ${currentPage} de ${totalPages} (${totalRegistros} registros)`
        : `${totalRegistros} registros`;
    
    const prevPage = Math.max(1, currentPage - 1);
    const nextPage = Math.min(totalPages, currentPage + 1);
    
    paginationDiv.innerHTML = `
        <a href="#" class="pagination-btn ${currentPage <= 1 ? 'disabled' : ''}" 
           data-direction="prev" data-page="${prevPage}">
            <i class="fas fa-chevron-left"></i>
        </a>
        
        <span class="pagination-info">${paginationText}</span>
        
        <a href="#" class="pagination-btn ${currentPage >= totalPages ? 'disabled' : ''}"
           data-direction="next" data-page="${nextPage}">
            <i class="fas fa-chevron-right"></i>
        </a>
    `;
}

function updateURLParameter(param, value) {
    const url = new URL(window.location.href);
    url.searchParams.set(param, value);
    const perPageSelect = document.getElementById('perPageRoutes');
    const perPage = perPageSelect ? parseInt(perPageSelect.value, 10) : 10;
    if ([5, 10, 25, 50, 100].indexOf(perPage) >= 0) {
        url.searchParams.set('per_page', perPage);
    }
    window.history.pushState({}, '', url);
}

function updateDashboardMetrics() {
    fetch(sfApiUrl('route_data.php?action=summary'))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const metrics = data.data;
                window.__SF_DEBUG__ && console.log('Métricas recebidas:', metrics);
                
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
                
                window.__SF_DEBUG__ && console.log('Elementos a atualizar:', elements);
                
                Object.entries(elements).forEach(([id, value]) => {
                    const element = document.getElementById(id);
                    if (element) {
                        element.textContent = value;
                        window.__SF_DEBUG__ && console.log(`Atualizado ${id} com valor ${value}`);
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
    // Evita off-by-one em `YYYY-MM-DD` (JS interpreta como UTC)
    if (/^\d{4}-\d{2}-\d{2}$/.test(dateString)) {
        const [y, m, d] = dateString.split('-').map(Number);
        const dt = new Date(y, m - 1, d); // horário local
        return dt.toLocaleDateString('pt-BR');
    }
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
    resetRouteModalTabs();
}

function showEditRouteModal(routeId) {
    fetch(sfApiUrl(`route_data.php?action=view&id=${routeId}`))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                fillRouteForm(data.data);
                document.getElementById('routeId').value = routeId;
                document.getElementById('modalTitle').textContent = 'Editar Rota';
                document.getElementById('routeModal').style.display = 'block';
                setupFormCalculations();
                resetRouteModalTabs();
            } else {
                throw new Error(data.error || 'Erro ao carregar dados da rota');
            }
        })
        .catch(error => {
            console.error('Erro ao carregar detalhes da rota:', error);
            showRouteToast('Erro ao carregar detalhes da rota: ' + sfErrUserMessage(error), 'error');
        });
}

function showDeleteConfirmation(routeId) {
    fetch(sfApiUrl(`route_data.php?action=view&id=${routeId}`))
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
            showRouteToast('Erro ao carregar detalhes da rota: ' + sfErrUserMessage(error), 'error');
        });
}

function deleteRoute(routeId) {
    if (!confirm('Tem certeza que deseja excluir esta rota?')) return;
    
    fetch(sfApiUrl(`route_actions.php?action=delete&id=${routeId}`), {
        method: 'POST',
        credentials: 'include',
        headers: typeof sfMutationHeaders === 'function' ? sfMutationHeaders() : {}
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeAllModals();
                loadRouteData(currentPage);
                showRouteToast('Rota excluída com sucesso!', 'success');
            } else {
                throw new Error(data.error || 'Erro ao excluir rota');
            }
        })
        .catch(error => {
            console.error('Erro ao excluir rota:', error);
            showRouteToast('Erro ao excluir rota: ' + sfErrUserMessage(error), 'error');
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
    
    fetch(sfApiUrl(`route_actions.php?action=${method}${routeId ? '&id=' + routeId : ''}`), {
        method: 'POST',
        credentials: 'include',
        headers: typeof sfMutationHeaders === 'function'
            ? sfMutationHeaders({ 'Content-Type': 'application/json' })
            : { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                closeAllModals();
                loadRouteData(currentPage);
                showRouteToast(result.message || (routeId ? 'Rota atualizada com sucesso!' : 'Rota adicionada com sucesso!'), 'success');
            } else {
                throw new Error(result.error || 'Erro ao salvar rota');
            }
        })
        .catch(error => {
            console.error('Erro ao salvar rota:', error);
            showRouteToast('Erro ao salvar rota: ' + sfErrUserMessage(error), 'error');
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
    window.__SF_DEBUG__ && console.log(`Carregando cidades para UF: ${uf}, target: ${targetSelectId}`);
    
    return fetch(sfApiUrl(`route_actions.php?action=get_cidades&uf=${uf}`))
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
                    window.__SF_DEBUG__ && console.log(`Cidades carregadas com sucesso para ${targetSelectId}`);
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
    fetch(sfApiUrl(`route_data.php?action=view&id=${routeId}`))
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
            showRouteToast('Erro ao carregar detalhes da rota: ' + sfErrUserMessage(error), 'error');
        });
}

function fillRouteDetails(data) {
    // Função auxiliar para preencher elemento com segurança
    const setElementText = (id, text) => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = text;
        } else {
            console.warn(`Elemento ${id} não encontrado no DOM`);
        }
    };
    
    // Preenche os detalhes da rota no modal de visualização
    setElementText('routeOriginDestination', 
        `${data.cidade_origem_nome || '-'}, ${data.estado_origem || '-'} → ${data.cidade_destino_nome || '-'}, ${data.estado_destino || '-'}`);
    
    // Status da rota baseado no no_prazo (badge no modal de detalhes)
    const statusText = data.no_prazo === '1' ? 'No prazo' : data.no_prazo === '0' ? 'Atrasado' : '—';
    const statusEl = document.getElementById('routeStatus');
    if (statusEl) {
        statusEl.textContent = statusText;
        statusEl.className = 'rd-badge';
        if (data.no_prazo === '1') statusEl.classList.add('rd-badge--success');
        else if (data.no_prazo === '0') statusEl.classList.add('rd-badge--danger');
        else statusEl.classList.add('rd-badge--neutral');
    }
    setElementText('routeDate', data.data_rota ? formatDate(data.data_rota) : '-');
    
    // Informações gerais
    setElementText('detailDriver', data.motorista_nome || '-');
    setElementText('detailVehicle', data.veiculo_placa && data.veiculo_modelo ? 
        `${data.veiculo_placa} (${data.veiculo_modelo})` : data.veiculo_placa || '-');
    setElementText('detailDistance', data.distancia_km ? 
        `${parseFloat(data.distancia_km).toFixed(2)} km` : '-');
    
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
    setElementText('detailStartTime', data.data_saida ? formatDateTime(data.data_saida) : '-');
    setElementText('detailEndTime', data.data_chegada ? formatDateTime(data.data_chegada) : '-');
    
    // Calcula duração se houver data de início e fim
    if (data.data_saida && data.data_chegada) {
        const inicio = new Date(data.data_saida);
        const fim = new Date(data.data_chegada);
        const duracao = Math.abs(fim - inicio);
        const horas = Math.floor(duracao / (1000 * 60 * 60));
        const minutos = Math.floor((duracao % (1000 * 60 * 60)) / (1000 * 60));
        setElementText('detailDuration', `${horas}h ${minutos}min`);
    } else {
        setElementText('detailDuration', '-');
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
    
    setElementText('detailOriginAddress', enderecoOrigem);
    setElementText('detailDestinationAddress', enderecoDestino);
    
    // Informações da Carga
    setElementText('detailCargoDescription', data.descricao_carga || '-');
    setElementText('detailCargoWeight', data.peso_carga ? `${parseFloat(data.peso_carga).toFixed(2)} kg` : '-');
    setElementText('detailCustomer', data.cliente || '-');
    setElementText('detailCustomerContact', data.cliente_contato || '-');
    
    // Informações Financeiras (cards antigos removidos - agora usa Análise de Lucratividade)
    
    // Eficiência
    if (document.getElementById('detailEfficiency')) {
        document.getElementById('detailEfficiency').textContent = data.eficiencia_viagem ? 
            `${parseFloat(data.eficiencia_viagem).toFixed(2)}%` : '-';
    }
    
    // Observações
    setElementText('detailNotes', data.observacoes || 'Nenhuma observação registrada');
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
    window.__SF_DEBUG__ && console.log('Configurando cálculos do formulário...');
    
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
            fetch(sfApiUrl(`route_actions.php?action=get_motorista_comissao&id=${motorista.value}`))
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
    fetch(sfApiUrl(`route_data.php?action=get_expenses&id=${routeId}`))
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
            showRouteToast('Erro ao carregar despesas: ' + sfErrUserMessage(error), 'error');
        });
}

function fillExpensesForm(expenses) {
    // Preenche cada campo com o valor existente ou deixa vazio
    const fields = [
        'descarga', 'pedagios', 'caixinha', 'estacionamento',
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
        'descarga', 'pedagios', 'caixinha', 'estacionamento',
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
        'descarga', 'pedagios', 'caixinha', 'estacionamento',
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
    
    fetch(sfApiUrl('route_actions.php?action=save_expenses'), {
        method: 'POST',
        credentials: 'include',
        headers: typeof sfMutationHeaders === 'function'
            ? sfMutationHeaders({ 'Content-Type': 'application/json' })
            : { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                closeAllModals();
                showRouteToast('Despesas salvas com sucesso!', 'success');
            } else {
                throw new Error(result.error || 'Erro ao salvar despesas');
            }
        })
        .catch(error => {
            console.error('Erro ao salvar despesas:', error);
            showRouteToast('Erro ao salvar despesas: ' + sfErrUserMessage(error), 'error');
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
        addRouteBtn.addEventListener('click', () => showAddRouteModal());
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

    // Botão de exportar
    const exportBtn = document.getElementById('exportBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            const search = document.getElementById('searchRoute')?.value?.trim() || '';
            const delivery = document.getElementById('statusFilter')?.value || '';
            const driver = document.getElementById('driverFilter')?.value || '';
            const date = document.getElementById('dateFilter')?.value || '';
            let url = sfApiUrl('route_export.php?');
            const p = [];
            if (search) p.push('search=' + encodeURIComponent(search));
            if (delivery) p.push('delivery=' + encodeURIComponent(delivery));
            if (driver) p.push('driver=' + encodeURIComponent(driver));
            if (date) p.push('date=' + encodeURIComponent(date));
            if (currentRouteDateFrom) p.push('date_from=' + encodeURIComponent(currentRouteDateFrom));
            if (currentRouteDateTo) p.push('date_to=' + encodeURIComponent(currentRouteDateTo));
            if (currentRouteFilterMonth && !currentRouteDateFrom) {
                const [y, m] = currentRouteFilterMonth.split('-');
                p.push('year=' + y + '&month=' + m);
            }
            window.open(url + p.join('&'), '_blank');
            showRouteToast('Exportação iniciada. O download deve abrir em instantes.', 'info');
        });
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
            var delBody = 'rota_id=' + encodeURIComponent(expenseRouteId);
            if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__) {
                delBody += '&csrf_token=' + encodeURIComponent(window.__SF_CSRF__);
            }
            fetch(sfApiUrl('route_actions.php?action=delete_expenses'), {
                method: 'POST',
                credentials: 'include',
                headers: typeof sfMutationHeaders === 'function'
                    ? sfMutationHeaders({ 'Content-Type': 'application/x-www-form-urlencoded' })
                    : { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: delBody
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    closeAllModals();
                    showRouteToast('Despesas excluídas com sucesso!', 'success');
                } else {
                    throw new Error(result.error || 'Erro ao excluir despesas');
                }
            })
            .catch(error => {
                showRouteToast('Erro ao excluir despesas: ' + sfErrUserMessage(error), 'error');
            });
        });
    }
}

// Função para limpar o filtro
function clearFilter() {
    const filterMonth = document.getElementById('filterMonth');
    const filterDateFrom = document.getElementById('filterDateFrom');
    const filterDateTo = document.getElementById('filterDateTo');
    if (filterMonth) filterMonth.value = '';
    if (filterDateFrom) filterDateFrom.value = '';
    if (filterDateTo) filterDateTo.value = '';
    currentRouteDateFrom = null;
    currentRouteDateTo = null;
    currentRouteFilterMonth = null;
    currentPage = 1;
    saveRoutesNonColumnPrefs();
    loadRouteData(1);
    const currentDate = new Date();
    loadDashboardData(currentDate.getMonth() + 1, currentDate.getFullYear());
    closeModal('filterModal');
}

// Função para aplicar o filtro
function applyFilter() {
    const filterMonth = document.getElementById('filterMonth');
    const filterDateFrom = document.getElementById('filterDateFrom');
    const filterDateTo = document.getElementById('filterDateTo');
    if (filterDateFrom && filterDateTo && filterDateFrom.value && filterDateTo.value) {
        currentRouteDateFrom = filterDateFrom.value;
        currentRouteDateTo = filterDateTo.value;
        currentRouteFilterMonth = null;
        if (filterMonth) filterMonth.value = '';
    } else if (filterMonth && filterMonth.value) {
        currentRouteFilterMonth = filterMonth.value;
        currentRouteDateFrom = null;
        currentRouteDateTo = null;
        if (filterDateFrom) filterDateFrom.value = '';
        if (filterDateTo) filterDateTo.value = '';
    }
    currentPage = 1;
    saveRoutesNonColumnPrefs();
    loadRouteData(1);
    if (currentRouteFilterMonth) {
        const [y, m] = currentRouteFilterMonth.split('-');
        loadDashboardData(parseInt(m), parseInt(y));
    } else {
        const currentDate = new Date();
        loadDashboardData(currentDate.getMonth() + 1, currentDate.getFullYear());
    }
    closeModal('filterModal');
}

// Função para carregar os dados do dashboard
function loadDashboardData(month, year) {
    // Mostrar indicador de carregamento
    showLoading();

    // Fazer requisição AJAX para buscar os dados
    fetch(sfApiUrl(`routes_data.php?action=dashboard&month=${month}&year=${year}`))
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
            showRouteToast('Erro ao carregar dados do dashboard: ' + sfErrUserMessage(error), 'error');
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