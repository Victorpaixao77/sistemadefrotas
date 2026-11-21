/**
 * Cadastro de fornecedores — lista + modal CRUD (api/fornecedores.php)
 */
(function () {
    'use strict';

    function apiBase(rel) {
        rel = String(rel || '').replace(/^\//, '');
        var b = typeof window.__SF_API_BASE__ === 'string' && window.__SF_API_BASE__ !== ''
            ? String(window.__SF_API_BASE__).replace(/\/+$/, '')
            : '';
        if (b) return b + '/' + rel;
        try { return new URL('../api/' + rel, window.location.href).href; }
        catch (e) { return '../api/' + rel; }
    }
    var API = function () { return apiBase('fornecedores.php'); };
    var API_POS = function () { return apiBase('fornecedor_posicao.php'); };
    var API_CNPJ = function () { return apiBase('cnpj_brasilapi.php'); };

    /** @type {Array<Object>} */
    let _fornecedoresCache = [];

    let _fornPage = 1;
    let _fornPerPage = 10;
    let _fornTotalPages = 1;
    let _fornTotal = 0;
    let _fornSortField = 'nome';
    let _fornSortDir = 'ASC';

    /** Primeira ordenação ao escolher coluna: ordem alfabética (A→Z). */
    function fornDefaultSortDir() {
        return 'ASC';
    }

    function syncFornSortIndicators() {
        document.querySelectorAll('.fornc-table thead th.sortable').forEach(function (th) {
            const field = th.getAttribute('data-sort');
            const ind = th.querySelector('.sort-ind');
            if (!ind) return;
            const on = field === _fornSortField;
            th.classList.toggle('sorted', on);
            if (on) {
                ind.textContent = _fornSortDir === 'ASC' ? '▲' : '▼';
            } else {
                ind.textContent = '⇅';
            }
        });
    }

    function wireFornecedoresSortHeaders() {
        document.querySelectorAll('.fornc-table thead th.sortable').forEach(function (th) {
            th.addEventListener('click', function () {
                const field = this.getAttribute('data-sort');
                if (!field) return;
                if (_fornSortField === field) {
                    _fornSortDir = _fornSortDir === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    _fornSortField = field;
                    _fornSortDir = fornDefaultSortDir();
                }
                _fornPage = 1;
                syncFornSortIndicators();
                loadList(1);
            });
        });
    }

    /** Debounce BrasilAPI ao digitar CNPJ */
    let _cnpjDebounceTimer = null;
    /** Último CNPJ (14 dígitos) que já recebeu preenchimento automático neste modal */
    let _lastCnpjAutoFetched = '';

    const FETCH_TIMEOUT_MS = 35000;

    function fornFiscalNfeUrl(fornecedorId) {
        var q = 'fiscal/pages/nfe.php?fornecedor_id=' + encodeURIComponent(fornecedorId);
        if (typeof sfAppUrl === 'function') return sfAppUrl(q);
        var b = typeof window.__SF_APP_BASE__ === 'string' ? String(window.__SF_APP_BASE__).replace(/\/+$/, '') : '';
        return (b ? b + '/' : '../') + q.replace(/^\//, '');
    }

    function onlyDigits(s) {
        return String(s || '').replace(/\D/g, '');
    }

    function setGlobalLoading(on) {
        const el = document.getElementById('fornGlobalLoading');
        if (!el) return;
        el.classList.toggle('active', !!on);
        el.setAttribute('aria-hidden', on ? 'false' : 'true');
    }

    function showToast(msg, kind) {
        const el = document.getElementById('fornToast');
        if (!el) {
            if (kind === 'err') {
                alert(msg);
            }
            return;
        }
        el.textContent = msg;
        el.className = 'show ' + (kind === 'ok' ? 'ok' : 'err');
        clearTimeout(showToast._t);
        showToast._t = setTimeout(function () {
            el.classList.remove('show');
        }, 4500);
    }

    function getCsrfToken() {
        const m = document.querySelector('meta[name="csrf-token"]');
        if (m && m.getAttribute('content')) {
            return m.getAttribute('content');
        }
        const h = document.getElementById('forn_csrf_token');
        return h ? h.value : '';
    }

    function appendCsrf(fd) {
        const t = getCsrfToken();
        if (t) {
            fd.append('csrf_token', t);
        }
    }

    function fetchWithTimeout(url, options, ms) {
        const c = new AbortController();
        const timeoutMs = ms || FETCH_TIMEOUT_MS;
        const tid = setTimeout(function () {
            c.abort();
        }, timeoutMs);
        const opts = Object.assign({}, options || {}, { signal: c.signal, credentials: 'same-origin' });
        return fetch(url, opts).finally(function () {
            clearTimeout(tid);
        });
    }

    function clearBrasilapiHint() {
        const hint = document.getElementById('fornBrasilapiHint');
        if (hint) hint.textContent = '';
    }

    function clearCnpjBrasilapiTimers() {
        if (_cnpjDebounceTimer) {
            clearTimeout(_cnpjDebounceTimer);
            _cnpjDebounceTimer = null;
        }
    }

    /**
     * Preenche campos do modal a partir do JSON normalizado em api/cnpj_brasilapi.php
     * @param {Object} d
     */
    function applyBrasilapiData(d) {
        const set = (id, v) => {
            const el = document.getElementById(id);
            if (el && v != null && String(v).length) el.value = String(v);
        };
        set('forn_nome', d.nome);
        set('forn_endereco', d.endereco);
        set('forn_numero', d.numero);
        set('forn_complemento', d.complemento);
        set('forn_bairro', d.bairro);
        set('forn_cep', d.cep);
        set('forn_cidade', d.cidade);
        set('forn_uf', d.uf);
        set('forn_cMun', d.codigo_municipio_ibge);
        set('forn_ie', d.inscricao_estadual);
        set('forn_im', d.inscricao_municipal);
        set('forn_regime', d.regime);
        set('forn_telefone', d.telefone);
        set('forn_email', d.email);
        const pais = document.getElementById('forn_pais');
        if (pais && (!pais.value || String(pais.value).trim() === '')) {
            pais.value = 'Brasil';
        }
    }

    /**
     * @param {boolean} isManual - se true, usa alert em erro; se false, só mensagem no hint
     */
    function fetchCnpjBrasilapi(isManual) {
        const tipoEl = document.getElementById('forn_tipo');
        if (!tipoEl || tipoEl.value !== 'J') return;

        const cnpj = onlyDigits(document.getElementById('forn_cnpj')?.value);
        const hintEl = document.getElementById('fornBrasilapiHint');
        const btn = document.getElementById('btnConsultarCnpj');

        if (cnpj.length !== 14) {
            if (isManual) {
                showToast('Informe o CNPJ com 14 dígitos (pode usar máscara).', 'err');
            }
            return;
        }
        if (window.DocValidators && !window.DocValidators.validarCnpj(cnpj)) {
            if (isManual) {
                showToast('CNPJ inválido (dígitos verificadores).', 'err');
            }
            return;
        }

        if (isManual && hintEl) {
            hintEl.textContent = 'Consultando BrasilAPI...';
        }
        if (btn) btn.disabled = true;

        fetchWithTimeout(API_CNPJ() + '?cnpj=' + encodeURIComponent(cnpj), { method: 'GET' }, FETCH_TIMEOUT_MS)
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    if (isManual) {
                        showToast(res.message || 'Não foi possível consultar o CNPJ.', 'err');
                    } else if (hintEl) {
                        hintEl.textContent = res.message || 'Consulta automática indisponível.';
                    }
                    return;
                }
                const d = res.data || {};
                applyBrasilapiData(d);
                _lastCnpjAutoFetched = cnpj;

                if (hintEl) {
                    hintEl.textContent = (d.hint != null ? String(d.hint) : '').trim();
                }
            })
            .catch(function (err) {
                if (isManual) {
                    showToast(err && err.name === 'AbortError' ? 'Tempo esgotado ao consultar CNPJ.' : 'Erro de comunicação ao consultar o CNPJ.', 'err');
                } else if (hintEl) {
                    hintEl.textContent = 'Falha de rede ao consultar CNPJ.';
                }
            })
            .finally(() => {
                if (btn) btn.disabled = false;
            });
    }

    function scheduleAutoFetchCnpj() {
        const tipoEl = document.getElementById('forn_tipo');
        if (!tipoEl || tipoEl.value !== 'J') return;

        clearCnpjBrasilapiTimers();
        const cnpj = onlyDigits(document.getElementById('forn_cnpj')?.value);

        if (cnpj.length !== 14) {
            return;
        }
        if (cnpj === _lastCnpjAutoFetched) {
            return;
        }

        _cnpjDebounceTimer = setTimeout(function () {
            _cnpjDebounceTimer = null;
            const again = onlyDigits(document.getElementById('forn_cnpj')?.value);
            if (again.length === 14 && again !== _lastCnpjAutoFetched && document.getElementById('forn_tipo')?.value === 'J') {
                fetchCnpjBrasilapi(false);
            }
        }, 1200);
    }

    function wireCnpjBrasilapiOnce() {
        const inp = document.getElementById('forn_cnpj');
        const btn = document.getElementById('btnConsultarCnpj');
        if (inp && !inp.dataset.brasilapiWired) {
            inp.dataset.brasilapiWired = '1';
            inp.addEventListener('input', function () {
                const d = onlyDigits(inp.value);
                if (d.length !== 14) {
                    _lastCnpjAutoFetched = '';
                }
                scheduleAutoFetchCnpj();
            });
        }
        if (btn && !btn.dataset.brasilapiWired) {
            btn.dataset.brasilapiWired = '1';
            btn.addEventListener('click', function () {
                fetchCnpjBrasilapi(true);
            });
        }
    }

    function fmtDoc(tipo, cpf, cnpj) {
        if (tipo === 'F' && cpf) {
            const d = onlyDigits(cpf);
            if (d.length === 11) {
                return d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            }
        }
        if (tipo === 'J' && cnpj) {
            const d = onlyDigits(cnpj);
            if (d.length === 14) {
                return d.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
            }
        }
        return cpf || cnpj || '—';
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function hasIbge7(f) {
        const d = onlyDigits(f.codigo_municipio_ibge || '');
        return d.length === 7;
    }

    function isModernFornecedoresPage() {
        return !!document.querySelector('.fornc-page');
    }

    function pctLabel(value, total) {
        const t = Number(total) || 0;
        if (t <= 0) return '0%';
        const p = Math.round((Number(value) || 0) * 100 / t);
        return String(p) + '%';
    }

    function setKpiText(id, value, total, showPct) {
        const el = document.getElementById(id);
        if (!el) return;
        if (showPct) {
            el.textContent = String(value) + ' · ' + pctLabel(value, total);
            return;
        }
        el.textContent = String(value);
    }

    function setKpiCellState(id, state) {
        const el = document.getElementById(id);
        const cell = el ? el.closest('.fornc-kpi-cell') : null;
        if (!cell) return;
        cell.classList.remove('is-ok', 'is-warn');
        if (state === 'ok' || state === 'warn') {
            cell.classList.add('is-' + state);
        }
    }

    function updateKpiSummary(total, ativos, inativos, ibgeFalta, emailOk) {
        const el = document.getElementById('forncKpiSummary');
        if (!el) return;
        if (total <= 0) {
            el.textContent = 'Nenhum fornecedor com os filtros atuais.';
            return;
        }
        el.textContent =
            'Base: ' + total + ' registros | Ativos: ' + pctLabel(ativos, total) +
            ' | Sem IBGE: ' + ibgeFalta + ' | Com e-mail: ' + pctLabel(emailOk, total);
    }

    function applyKpiQuickFilter(filter) {
        const sit = document.getElementById('filtroSituacaoFornecedor');
        const tipo = document.getElementById('filterTipoFornecedor');
        const search = document.getElementById('searchFornecedor');

        if (!sit || !tipo) return;

        if (filter === 'ativos') sit.value = 'A';
        if (filter === 'inativos') sit.value = 'I';
        if (filter === 'pj') tipo.value = 'J';
        if (filter === 'pf') tipo.value = 'F';
        if (filter === 'todos') {
            sit.value = 'all';
            tipo.value = '';
            if (search) search.value = '';
        }
        _fornPage = 1;
        loadList(1);
    }

    function wireKpiQuickFilters() {
        const binds = [
            { id: 'fornKpiTotal', filter: 'todos', title: 'Clique para ver todos os registros' },
            { id: 'fornKpiAtivos', filter: 'ativos', title: 'Clique para filtrar ativos' },
            { id: 'fornKpiInativos', filter: 'inativos', title: 'Clique para filtrar inativos' },
            { id: 'fornKpiPJ', filter: 'pj', title: 'Clique para filtrar pessoa jurídica' },
            { id: 'fornKpiPF', filter: 'pf', title: 'Clique para filtrar pessoa física' }
        ];
        binds.forEach(function (b) {
            const el = document.getElementById(b.id);
            const cell = el ? el.closest('.fornc-kpi-cell') : null;
            if (!cell || cell.dataset.kpiWired) return;
            cell.dataset.kpiWired = '1';
            cell.classList.add('is-clickable');
            cell.title = b.title;
            cell.addEventListener('click', function () {
                applyKpiQuickFilter(b.filter);
            });
        });
    }

    function updateKpisFromApi(k) {
        if (!k || typeof k !== 'object') {
            updateKpisFromRows([]);
            return;
        }
        const total = k.total != null ? Number(k.total) : 0;
        const ativos = k.ativos != null ? Number(k.ativos) : 0;
        const inativos = k.inativos != null ? Number(k.inativos) : 0;
        const pj = k.pj != null ? Number(k.pj) : 0;
        const pf = k.pf != null ? Number(k.pf) : 0;
        const ibgeOk = k.ibge_ok != null ? Number(k.ibge_ok) : 0;
        const ibgeFalta = Math.max(0, total - ibgeOk);
        const emailOk = k.email_ok != null ? Number(k.email_ok) : 0;
        const modern = isModernFornecedoresPage();

        setKpiText('fornKpiTotal', total, total, false);
        setKpiText('fornKpiAtivos', ativos, total, modern);
        setKpiText('fornKpiInativos', inativos, total, modern);
        setKpiText('fornKpiPJ', pj, total, modern);
        setKpiText('fornKpiPF', pf, total, modern);
        setKpiText('fornKpiIbgeOk', ibgeOk, total, modern);
        setKpiText('fornKpiIbgeFalta', ibgeFalta, total, modern);
        setKpiText('fornKpiEmail', emailOk, total, modern);

        setKpiCellState('fornKpiInativos', inativos > 0 ? 'warn' : 'ok');
        setKpiCellState('fornKpiIbgeFalta', ibgeFalta > 0 ? 'warn' : 'ok');
        setKpiCellState('fornKpiIbgeOk', total > 0 && ibgeOk === total ? 'ok' : (total > 0 ? 'warn' : ''));
        setKpiCellState('fornKpiEmail', total > 0 && emailOk === total ? 'ok' : (total > 0 ? 'warn' : ''));
        updateKpiSummary(total, ativos, inativos, ibgeFalta, emailOk);
    }

    /** Fallback se API antiga não enviar kpi */
    function updateKpisFromRows(rows) {
        const total = rows.length;
        let ativos = 0;
        let inativos = 0;
        let pj = 0;
        let pf = 0;
        let ibgeOk = 0;
        let emailOk = 0;
        rows.forEach(f => {
            if (f.situacao === 'A') ativos++;
            if (f.situacao === 'I') inativos++;
            if (f.tipo === 'J') pj++;
            if (f.tipo === 'F') pf++;
            if (hasIbge7(f)) ibgeOk++;
            const em = (f.email || '').trim();
            if (em.length > 0) emailOk++;
        });
        updateKpisFromApi({
            total,
            ativos,
            inativos,
            pj,
            pf,
            ibge_ok: ibgeOk,
            email_ok: emailOk
        });
    }

    function buildListQueryParams(forExportAll) {
        const qs = new URLSearchParams();
        qs.append('action', 'list');
        const sit = document.getElementById('filtroSituacaoFornecedor');
        if (sit && sit.value) {
            qs.append('situacao', sit.value);
        }
        const tipo = document.getElementById('filterTipoFornecedor');
        if (tipo && tipo.value) {
            qs.append('tipo', tipo.value);
        }
        const search = document.getElementById('searchFornecedor');
        const q = (search && search.value) ? search.value.trim() : '';
        if (q) {
            qs.append('q', q);
        }
        if (forExportAll) {
            qs.append('all', '1');
        } else {
            const perEl = document.getElementById('perPageFornecedores');
            const pp = perEl ? parseInt(perEl.value, 10) : 10;
            _fornPerPage = [5, 10, 25, 50, 100].indexOf(pp) >= 0 ? pp : 10;
            qs.append('per_page', String(_fornPerPage));
            qs.append('page', String(_fornPage));
        }
        qs.append('sort', _fornSortField);
        qs.append('dir', _fornSortDir);
        return qs;
    }

    function updatePagination(p) {
        if (!p) return;
        _fornPage = Math.max(1, parseInt(p.page, 10) || 1);
        _fornTotalPages = Math.max(1, parseInt(p.total_pages, 10) || 1);
        _fornTotal = parseInt(p.total, 10);
        if (Number.isNaN(_fornTotal)) _fornTotal = 0;

        const prev = document.getElementById('fornPrevPage');
        const next = document.getElementById('fornNextPage');
        const info = document.getElementById('fornPaginationInfo');
        if (info) {
            if (_fornTotalPages > 1) {
                info.textContent = 'Página ' + _fornPage + ' de ' + _fornTotalPages + ' (' + _fornTotal + ' registros)';
            } else {
                info.textContent = _fornTotal === 1 ? '1 registro' : _fornTotal + ' registros';
            }
        }
        if (prev) {
            prev.classList.toggle('disabled', _fornPage <= 1);
            prev.style.pointerEvents = _fornPage <= 1 ? 'none' : '';
        }
        if (next) {
            next.classList.toggle('disabled', _fornPage >= _fornTotalPages);
            next.style.pointerEvents = _fornPage >= _fornTotalPages ? 'none' : '';
        }
    }

    function formatMoneyBr(v) {
        const n = parseFloat(v);
        if (Number.isNaN(n)) return '0,00';
        return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function bindTableActions(tbody) {
        tbody.querySelectorAll('.btn-edit-forn').forEach(btn => {
            btn.addEventListener('click', () => openModal(parseInt(btn.getAttribute('data-id'), 10)));
        });
        tbody.querySelectorAll('.btn-del-forn').forEach(btn => {
            btn.addEventListener('click', () => inativar(parseInt(btn.getAttribute('data-id'), 10)));
        });
        tbody.querySelectorAll('.btn-pos-fin').forEach(btn => {
            btn.addEventListener('click', () => openPosicaoFinanceira(parseInt(btn.getAttribute('data-id'), 10)));
        });
        tbody.querySelectorAll('.btn-pos-fisc').forEach(btn => {
            btn.addEventListener('click', () => openPosicaoFiscal(parseInt(btn.getAttribute('data-id'), 10)));
        });
    }

    function syncFornEmptyState(isEmpty) {
        const el = document.getElementById('fornEmptyState');
        const wrap = document.getElementById('fornTableWrap');
        if (!el || !wrap) return;
        el.classList.toggle('visible', !!isEmpty);
        wrap.style.display = isEmpty ? 'none' : '';
    }

    function renderTable() {
        const tbody = document.getElementById('fornecedoresTableBody');
        if (!tbody) return;

        const rows = _fornecedoresCache;
        if (rows.length === 0) {
            tbody.innerHTML = '';
            syncFornEmptyState(true);
            syncFornSortIndicators();
            return;
        }

        syncFornEmptyState(false);
        tbody.innerHTML = rows.map(f => {
            const doc = f.tipo === 'J' ? fmtDoc('J', '', f.cnpj) : fmtDoc('F', f.cpf, '');
            const sit = f.situacao === 'A'
                ? '<span class="status-badge success">Ativo</span>'
                : '<span class="status-badge warning">Inativo</span>';
            return '<tr data-id="' + f.id + '">' +
                '<td>' + escapeHtml(f.nome) + '</td>' +
                '<td>' + (f.tipo === 'J' ? 'PJ' : 'PF') + '</td>' +
                '<td>' + escapeHtml(doc) + '</td>' +
                '<td>' + escapeHtml(f.cidade || '') + (f.uf ? ' / ' + escapeHtml(f.uf) : '') + '</td>' +
                '<td>' + sit + '</td>' +
                '<td class="actions">' +
                '<a href="' + escapeHtml(fornFiscalNfeUrl(f.id)) + '" class="btn-icon" title="Emitir NF-e com este cadastro" target="_blank" rel="noopener"><i class="fas fa-paper-plane"></i></a> ' +
                '<button type="button" class="btn-icon btn-pos-fin" data-id="' + f.id + '" title="Posição financeira"><i class="fas fa-wallet"></i></button> ' +
                '<button type="button" class="btn-icon btn-pos-fisc" data-id="' + f.id + '" title="Posição fiscal"><i class="fas fa-file-invoice"></i></button> ' +
                '<button type="button" class="btn-icon edit-btn btn-edit-forn" data-id="' + f.id + '" title="Editar"><i class="fas fa-edit"></i></button> ' +
                '<button type="button" class="btn-icon delete-btn btn-del-forn" data-id="' + f.id + '" title="Inativar"><i class="fas fa-ban"></i></button>' +
                '</td></tr>';
        }).join('');

        bindTableActions(tbody);
        syncFornSortIndicators();
    }

    function applyFornPayload(data, bindActions) {
        if (!data || !data.success) return;
        _fornecedoresCache = data.fornecedores || [];
        if (data.kpi) {
            updateKpisFromApi(data.kpi);
        } else {
            updateKpisFromRows(_fornecedoresCache);
        }
        if (data.pagination) {
            updatePagination(data.pagination);
        }
        if (_fornecedoresCache.length === 0) {
            var tbody = document.getElementById('fornecedoresTableBody');
            if (tbody) tbody.innerHTML = '';
            syncFornEmptyState(true);
            syncFornSortIndicators();
            return;
        }
        if (bindActions !== false) {
            renderTable();
        }
    }

    function setTableLoading(on) {
        var wrap = document.getElementById('fornTableWrap');
        if (wrap) {
            wrap.style.opacity = on ? '0.55' : '';
            wrap.style.pointerEvents = on ? 'none' : '';
        }
    }

    function loadList(goPage, opts) {
        opts = opts || {};
        const tbody = document.getElementById('fornecedoresTableBody');
        if (!tbody) return;

        if (typeof goPage === 'number' && goPage >= 1) {
            _fornPage = goPage;
        }

        var hadRows = _fornecedoresCache && _fornecedoresCache.length > 0;
        if (!hadRows && !opts.keepTable) {
            tbody.innerHTML = '<tr><td colspan="6">Carregando...</td></tr>';
        }
        syncFornEmptyState(false);
        if (opts.overlay) setGlobalLoading(true);
        else setTableLoading(true);

        const qs = buildListQueryParams(false);

        fetchWithTimeout(API() + '?' + qs.toString(), { method: 'GET' }, FETCH_TIMEOUT_MS)
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    tbody.innerHTML = '<tr><td colspan="6">' + (data.message || 'Erro ao listar') + '</td></tr>';
                    _fornecedoresCache = [];
                    syncFornEmptyState(false);
                    updateKpisFromRows([]);
                    updatePagination({ page: 1, total_pages: 1, total: 0 });
                    return;
                }
                applyFornPayload(data, true);
            })
            .catch(function () {
                if (!hadRows) {
                    tbody.innerHTML = '<tr><td colspan="6">Falha de rede ou tempo esgotado. Tente novamente.</td></tr>';
                }
                if (!_fornecedoresCache.length) {
                    syncFornEmptyState(false);
                    updateKpisFromRows([]);
                    updatePagination({ page: 1, total_pages: 1, total: 0 });
                }
                showToast('Não foi possível carregar a lista.', 'err');
            })
            .finally(function () {
                if (opts.overlay) setGlobalLoading(false);
                else setTableLoading(false);
            });
    }

    function clearLocalFilters() {
        const s = document.getElementById('searchFornecedor');
        if (s) s.value = '';
        const t = document.getElementById('filterTipoFornecedor');
        if (t) t.value = '';
        _fornPage = 1;
        loadList(1);
    }

    function exportCsv() {
        const qs = buildListQueryParams(true);
        fetch(API() + '?' + qs.toString(), { method: 'GET', credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.fornecedores || data.fornecedores.length === 0) {
                    alert('Não há dados para exportar.');
                    return;
                }
                const rows = data.fornecedores;
                const headers = ['nome', 'tipo', 'cpf', 'cnpj', 'cidade', 'uf', 'situacao', 'email', 'codigo_municipio_ibge'];
                const lines = [headers.join(';')];
                rows.forEach(f => {
                    const line = headers.map(h => {
                        let v = f[h] != null ? String(f[h]) : '';
                        v = v.replace(/"/g, '""');
                        if (v.indexOf(';') >= 0 || v.indexOf('\n') >= 0) {
                            v = '"' + v + '"';
                        }
                        return v;
                    });
                    lines.push(line.join(';'));
                });
                const blob = new Blob(['\ufeff' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'fornecedores_' + new Date().toISOString().slice(0, 10) + '.csv';
                a.click();
                URL.revokeObjectURL(a.href);
            })
            .catch(() => alert('Falha ao exportar.'));
    }

    function syncModalFilterFromPage() {
        const sit = document.getElementById('filtroSituacaoFornecedor');
        const tipo = document.getElementById('filterTipoFornecedor');
        const search = document.getElementById('searchFornecedor');
        const ms = document.getElementById('modalFilterSit');
        const mt = document.getElementById('modalFilterTipo');
        const mq = document.getElementById('modalFilterSearch');
        if (ms && sit) ms.value = sit.value;
        if (mt && tipo) mt.value = tipo.value;
        if (mq && search) mq.value = search.value;
    }

    function applyModalFilter() {
        const ms = document.getElementById('modalFilterSit');
        const mt = document.getElementById('modalFilterTipo');
        const mq = document.getElementById('modalFilterSearch');
        const sit = document.getElementById('filtroSituacaoFornecedor');
        const tipo = document.getElementById('filterTipoFornecedor');
        const search = document.getElementById('searchFornecedor');
        if (sit && ms) sit.value = ms.value;
        if (tipo && mt) tipo.value = mt.value;
        if (search && mq) search.value = mq.value;
        closeModal('filterFornecedorModal');
        _fornPage = 1;
        loadList(1);
    }

    function clearModalFilter() {
        const ms = document.getElementById('modalFilterSit');
        const mt = document.getElementById('modalFilterTipo');
        const mq = document.getElementById('modalFilterSearch');
        if (ms) ms.value = 'A';
        if (mt) mt.value = '';
        if (mq) mq.value = '';
    }

    function openModal(id) {
        const form = document.getElementById('formFornecedor');
        if (!form) return;

        clearBrasilapiHint();
        clearCnpjBrasilapiTimers();
        _lastCnpjAutoFetched = '';

        form.reset();
        document.getElementById('fornecedor_id').value = id ? String(id) : '';
        document.getElementById('modalFornecedorTitle').textContent = id ? 'Editar fornecedor' : 'Novo fornecedor';

        wireCnpjBrasilapiOnce();

        const setTipoFields = () => {
            const tipo = document.getElementById('forn_tipo').value;
            document.getElementById('forn_cpf_wrap').style.display = tipo === 'F' ? '' : 'none';
            document.getElementById('forn_cnpj_wrap').style.display = tipo === 'J' ? '' : 'none';
            if (tipo === 'F') {
                clearBrasilapiHint();
                clearCnpjBrasilapiTimers();
            }
        };
        document.getElementById('forn_tipo').onchange = setTipoFields;

        if (id) {
            setGlobalLoading(true);
            fetch(API() + '?action=get&id=' + encodeURIComponent(id), { credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    setGlobalLoading(false);
                    if (!data.success || !data.fornecedor) {
                        showToast(data.message || 'Não foi possível carregar.', 'err');
                        return;
                    }
                    const f = data.fornecedor;
                    document.getElementById('forn_tipo').value = f.tipo || 'J';
                    document.getElementById('forn_nome').value = f.nome || '';
                    document.getElementById('forn_cpf').value = f.cpf || '';
                    document.getElementById('forn_cnpj').value = f.cnpj || '';
                    document.getElementById('forn_ie').value = f.inscricao_estadual || '';
                    document.getElementById('forn_im').value = f.inscricao_municipal || '';
                    document.getElementById('forn_regime').value = f.regime_tributario || '';
                    document.getElementById('forn_telefone').value = f.telefone || '';
                    document.getElementById('forn_email').value = f.email || '';
                    document.getElementById('forn_site').value = f.site || '';
                    document.getElementById('forn_endereco').value = f.endereco || '';
                    document.getElementById('forn_numero').value = f.numero || '';
                    document.getElementById('forn_complemento').value = f.complemento || '';
                    document.getElementById('forn_bairro').value = f.bairro || '';
                    document.getElementById('forn_cidade').value = f.cidade || '';
                    document.getElementById('forn_uf').value = f.uf || '';
                    document.getElementById('forn_cep').value = f.cep || '';
                    document.getElementById('forn_cMun').value = f.codigo_municipio_ibge || '';
                    document.getElementById('forn_pais').value = f.pais || 'Brasil';
                    document.getElementById('forn_tipo_forn').value = f.tipo_fornecedor || '';
                    document.getElementById('forn_limite').value = f.limite_credito ?? '0';
                    document.getElementById('forn_prazo').value = f.prazo_pagamento ?? '0';
                    document.getElementById('forn_multa').value = f.taxa_multa ?? '0';
                    document.getElementById('forn_juros').value = f.taxa_juros ?? '0';
                    document.getElementById('forn_situacao').value = f.situacao || 'A';
                    document.getElementById('forn_obs').value = f.observacoes || '';
                    setTipoFields();
                    const cj = onlyDigits(f.cnpj || '');
                    _lastCnpjAutoFetched = cj.length === 14 ? cj : '';
                    showModal('modalFornecedor');
                })
                .catch(function () {
                    setGlobalLoading(false);
                    showToast('Erro ao carregar fornecedor.', 'err');
                });
        } else {
            document.getElementById('forn_tipo').value = 'J';
            setTipoFields();
            showModal('modalFornecedor');
        }
    }

    function saveFornecedor() {
        const tipoDoc = document.getElementById('forn_tipo').value;
        if (window.DocValidators) {
            if (tipoDoc === 'F') {
                if (!window.DocValidators.validarCpf(document.getElementById('forn_cpf').value)) {
                    showToast('CPF inválido (verifique os dígitos).', 'err');
                    return;
                }
            } else {
                if (!window.DocValidators.validarCnpj(document.getElementById('forn_cnpj').value)) {
                    showToast('CNPJ inválido (verifique os dígitos).', 'err');
                    return;
                }
            }
        }
        const id = document.getElementById('fornecedor_id').value;
        const fd = new FormData();
        fd.append('action', id ? 'update' : 'create');
        if (id) fd.append('id', id);
        appendCsrf(fd);
        fd.append('tipo', tipoDoc);
        fd.append('nome', document.getElementById('forn_nome').value.trim());
        fd.append('cpf', onlyDigits(document.getElementById('forn_cpf').value));
        fd.append('cnpj', onlyDigits(document.getElementById('forn_cnpj').value));
        fd.append('inscricao_estadual', document.getElementById('forn_ie').value.trim());
        fd.append('inscricao_municipal', document.getElementById('forn_im').value.trim());
        fd.append('regime_tributario', document.getElementById('forn_regime').value.trim());
        fd.append('telefone', document.getElementById('forn_telefone').value.trim());
        fd.append('email', document.getElementById('forn_email').value.trim());
        fd.append('site', document.getElementById('forn_site').value.trim());
        fd.append('endereco', document.getElementById('forn_endereco').value.trim());
        fd.append('numero', document.getElementById('forn_numero').value.trim());
        fd.append('complemento', document.getElementById('forn_complemento').value.trim());
        fd.append('bairro', document.getElementById('forn_bairro').value.trim());
        fd.append('cidade', document.getElementById('forn_cidade').value.trim());
        fd.append('uf', document.getElementById('forn_uf').value.trim());
        fd.append('cep', document.getElementById('forn_cep').value.trim());
        fd.append('codigo_municipio_ibge', onlyDigits(document.getElementById('forn_cMun').value));
        fd.append('pais', document.getElementById('forn_pais').value.trim());
        fd.append('tipo_fornecedor', document.getElementById('forn_tipo_forn').value.trim());
        fd.append('limite_credito', document.getElementById('forn_limite').value);
        fd.append('prazo_pagamento', document.getElementById('forn_prazo').value);
        fd.append('taxa_multa', document.getElementById('forn_multa').value);
        fd.append('taxa_juros', document.getElementById('forn_juros').value);
        fd.append('situacao', document.getElementById('forn_situacao').value);
        fd.append('observacoes', document.getElementById('forn_obs').value.trim());

        const btn = document.getElementById('btnSalvarFornecedor');
        const orig = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }

        fetchWithTimeout(API(), { method: 'POST', body: fd }, FETCH_TIMEOUT_MS)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    closeModal('modalFornecedor');
                    showToast('Fornecedor salvo.', 'ok');
                    loadList(_fornPage);
                } else {
                    showToast(data.message || 'Erro ao salvar', 'err');
                }
            })
            .catch(function () {
                showToast('Erro de comunicação ao salvar.', 'err');
            })
            .finally(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = orig;
                }
            });
    }

    function openPosicaoFinanceira(id) {
        const body = document.getElementById('posFinBody');
        const title = document.getElementById('posFinTitle');
        if (!body) return;
        body.innerHTML = '<p style="color:var(--text-secondary);"><i class="fas fa-spinner fa-spin"></i> Carregando...</p>';
        if (title) title.textContent = 'Posição financeira';
        showModal('modalPosicaoFinanceira');

        fetch(API_POS() + '?action=financeiro&fornecedor_id=' + encodeURIComponent(id), { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    body.innerHTML = '<p>' + escapeHtml(data.message || 'Erro ao carregar') + '</p>';
                    return;
                }
                const f = data.fornecedor || {};
                if (title) title.textContent = 'Posição financeira — ' + (f.nome || '');
                const r = data.resumo || {};
                let html = '<div class="dashboard-grid" style="margin-bottom:1rem;">';
                html += '<div class="dashboard-card"><div class="card-header"><h3>Em aberto</h3></div><div class="card-body"><div class="metric"><span class="metric-value">R$ ' + formatMoneyBr(r.total_em_aberto) + '</span></div></div></div>';
                html += '<div class="dashboard-card"><div class="card-header"><h3>Pago</h3></div><div class="card-body"><div class="metric"><span class="metric-value">R$ ' + formatMoneyBr(r.total_pago) + '</span></div></div></div>';
                html += '<div class="dashboard-card"><div class="card-header"><h3>Registros</h3></div><div class="card-body"><div class="metric"><span class="metric-value">' + (r.qtd_contas || 0) + '</span><span class="metric-subtitle">Contas encontradas</span></div></div></div>';
                html += '</div>';
                html += '<p style="font-size:.85rem;color:var(--text-secondary);margin-bottom:.75rem;">Vínculo: <strong>fornecedor_id</strong> na conta ou nome igual ao cadastro (legado). Execute o SQL <code>sql/alter_contas_pagar_fornecedor_id.sql</code> para usar FK.</p>';
                const contas = data.contas || [];
                if (contas.length === 0) {
                    html += '<p>Nenhuma conta a pagar encontrada para este fornecedor.</p>';
                    body.innerHTML = html;
                    return;
                }
                html += '<div class="table-container"><table class="data-table"><thead><tr><th>Vencimento</th><th>Descrição</th><th>Valor</th><th>Status</th><th>Pagamento</th></tr></thead><tbody>';
                contas.forEach(c => {
                    html += '<tr><td>' + escapeHtml(String(c.data_vencimento || '')) + '</td><td>' + escapeHtml(String(c.descricao || '')) + '</td><td>R$ ' + formatMoneyBr(c.valor) + '</td><td>' + escapeHtml(String(c.status_nome || '')) + '</td><td>' + escapeHtml(String(c.data_pagamento || '—')) + '</td></tr>';
                });
                html += '</tbody></table></div>';
                body.innerHTML = html;
            })
            .catch(() => {
                body.innerHTML = '<p>Erro de comunicação.</p>';
            });
    }

    function openPosicaoFiscal(id) {
        const body = document.getElementById('posFiscBody');
        const title = document.getElementById('posFiscTitle');
        if (!body) return;
        body.innerHTML = '<p style="color:var(--text-secondary);"><i class="fas fa-spinner fa-spin"></i> Carregando...</p>';
        if (title) title.textContent = 'Posição fiscal';
        showModal('modalPosicaoFiscal');

        fetch(API_POS() + '?action=fiscal&fornecedor_id=' + encodeURIComponent(id), { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    body.innerHTML = '<p>' + escapeHtml(data.message || 'Erro ao carregar') + '</p>';
                    return;
                }
                const f = data.fornecedor || {};
                if (title) title.textContent = 'Posição fiscal — ' + (f.nome || '');
                const rs = data.resumo || {};
                let html = '<div class="dashboard-grid" style="margin-bottom:1rem;">';
                html += '<div class="dashboard-card"><div class="card-header"><h3>NF-e emitidas (venda)</h3></div><div class="card-body"><div class="metric"><span class="metric-value">' + (rs.qtd_nfe_emitidas || 0) + '</span><span class="metric-subtitle">R$ ' + formatMoneyBr(rs.valor_total_emitidas) + '</span></div></div></div>';
                html += '<div class="dashboard-card"><div class="card-header"><h3>NF-e recebidas (entrada)</h3></div><div class="card-body"><div class="metric"><span class="metric-value">' + (rs.qtd_nfe_recebidas || 0) + '</span><span class="metric-subtitle">R$ ' + formatMoneyBr(rs.valor_total_recebidas) + '</span></div></div></div>';
                html += '<div class="dashboard-card"><div class="card-header"><h3>CT-e (tomador)</h3></div><div class="card-body"><div class="metric"><span class="metric-value">' + (rs.qtd_cte_tomador || 0) + '</span><span class="metric-subtitle">R$ ' + formatMoneyBr(rs.valor_total_cte_tomador) + '</span></div></div></div>';
                html += '</div>';
                html += '<p style="font-size:.85rem;color:var(--text-secondary);margin-bottom:.75rem;">' + escapeHtml(data.nota || '') + '</p>';

                const emit = data.nfe_emitidas || [];
                html += '<h4 style="margin:1rem 0 .5rem;">NF-e emitidas para este destinatário</h4>';
                if (emit.length === 0) {
                    html += '<p style="margin-bottom:1rem;">Nenhuma NF-e emitida encontrada (casamento por CPF/CNPJ do cadastro).</p>';
                } else {
                    html += '<div class="table-container"><table class="data-table"><thead><tr><th>Nº</th><th>Data</th><th>Valor</th><th>Status</th><th>Chave</th></tr></thead><tbody>';
                    emit.forEach(n => {
                        html += '<tr><td>' + escapeHtml(String(n.numero_nfe ?? '')) + '</td><td>' + escapeHtml(String(n.data_emissao || '')) + '</td><td>R$ ' + formatMoneyBr(n.valor_total) + '</td><td>' + escapeHtml(String(n.status || '')) + '</td><td style="max-width:220px;word-break:break-all;font-size:.75rem;">' + escapeHtml(String(n.chave_acesso || '')) + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                }

                const rec = data.nfe_recebidas || [];
                html += '<h4 style="margin:1rem 0 .5rem;">NF-e recebidas (fornecedor como emitente)</h4>';
                if (rec.length === 0) {
                    html += '<p>Nenhuma NF-e recebida encontrada pelo CPF/CNPJ.</p>';
                } else {
                    html += '<div class="table-container"><table class="data-table"><thead><tr><th>Nº</th><th>Emissão</th><th>Emitente (XML)</th><th>Valor</th><th>Status</th></tr></thead><tbody>';
                    rec.forEach(n => {
                        html += '<tr><td>' + escapeHtml(String(n.numero_nfe ?? '')) + '</td><td>' + escapeHtml(String(n.data_emissao || '')) + '</td><td>' + escapeHtml(String(n.cliente_razao_social || '')) + '</td><td>R$ ' + formatMoneyBr(n.valor_total) + '</td><td>' + escapeHtml(String(n.status || '')) + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                }

                body.innerHTML = html;
            })
            .catch(() => {
                body.innerHTML = '<p>Erro de comunicação.</p>';
            });
    }

    function inativar(id) {
        if (!confirm('Inativar este fornecedor?\n\nEle deixará de aparecer como ativo nas listas padrão. Você pode reativar editando o cadastro.')) return;
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', String(id));
        appendCsrf(fd);
        const delBtns = document.querySelectorAll('.btn-del-forn');
        delBtns.forEach(function (b) { b.disabled = true; });
        fetchWithTimeout(API(), { method: 'POST', body: fd }, FETCH_TIMEOUT_MS)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast('Fornecedor inativado.', 'ok');
                    loadList(_fornPage);
                } else {
                    showToast(data.message || 'Erro ao inativar', 'err');
                }
            })
            .catch(function () {
                showToast('Erro de comunicação.', 'err');
            })
            .finally(function () {
                delBtns.forEach(function (b) { b.disabled = false; });
            });
    }

    function showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
        }
    }

    function wireModalClose(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.querySelectorAll('.close-modal').forEach(btn => {
            btn.addEventListener('click', () => closeModal(modalId));
        });
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeModal(modalId);
            }
        });
    }

    // ---- Insights (qualidade + ranking) + resumo por IA ----
    var _fnLastData = null;
    var _fnInsightsLoaded = false;
    var _fnInsightsLoading = false;
    var FN_CACHE_TTL_MS = 5 * 60 * 1000;

    function fnInsightsUrl() {
        return apiBase('fornecedor_insights.php');
    }

    function fnInsightsCacheKey() {
        return 'sf_insights_v1_' + fnInsightsUrl().replace(/[^a-z0-9]/gi, '_');
    }

    function fnReadInsightsCache() {
        try {
            var raw = sessionStorage.getItem(fnInsightsCacheKey());
            if (!raw) return null;
            var o = JSON.parse(raw);
            if (!o || !o.ts || !o.data) return null;
            if (Date.now() - o.ts > (o.ttl || FN_CACHE_TTL_MS)) {
                sessionStorage.removeItem(fnInsightsCacheKey());
                return null;
            }
            return o.data;
        } catch (e) { return null; }
    }

    function fnWriteInsightsCache(data) {
        try {
            sessionStorage.setItem(fnInsightsCacheKey(), JSON.stringify({ ts: Date.now(), ttl: FN_CACHE_TTL_MS, data: data }));
        } catch (e) { /* quota */ }
    }

    function renderFornInsights(json) {
        _fnLastData = json;
        var panel = document.getElementById('fnInsights');
        var summary = document.getElementById('fnSummary');
        if (!panel || !summary) return;
        panel.style.display = '';
        var resumo = document.getElementById('fnResumoIa');
        var fnBody = document.getElementById('fnBody');
        if (resumo) resumo.style.display = (fnBody && fnBody.style.display !== 'none') ? '' : 'none';

        var er = json.q_erros || 0, av = json.q_avisos || 0;
        panel.classList.remove('has-erros', 'has-avisos');
        if (er > 0) panel.classList.add('has-erros');
        else if (av > 0) panel.classList.add('has-avisos');

        var totalQ = er + av;
        summary.innerHTML = '<i class="fas fa-clipboard-check" style="color:' + (er > 0 ? '#dc2626' : (av > 0 ? '#f59e0b' : '#16a34a')) + '"></i> Insights de fornecedores' +
            (totalQ > 0 ? '<span class="fn-pill ' + (er > 0 ? 'erro' : 'aviso') + '">' + totalQ + ' pendência(s)</span>' : '<span class="fn-pill ok">tudo certo</span>');

        var qual = json.qualidade || [];
        document.getElementById('fnQualList').innerHTML = qual.length ? qual.map(function (q) {
            var icon = q.severidade === 'erro' ? 'fa-circle-xmark' : 'fa-triangle-exclamation';
            var motivos = (q.motivos || []).map(function (m) { return escapeHtml(m.msg); }).join(' ');
            return '<div class="fn-item"><span class="fn-sev ' + q.severidade + '"><i class="fas ' + icon + '"></i></span>' +
                '<div class="fn-txt"><strong>' + escapeHtml(q.nome) + '</strong> <span style="opacity:.7">(' + escapeHtml(q.tipo) + ')</span> — ' + motivos + '</div>' +
                '<button type="button" class="btn-icon fn-edit" data-fn-edit="' + escapeHtml(String(q.id)) + '" title="Abrir cadastro"><i class="fas fa-edit"></i></button></div>';
        }).join('') : '<div class="fn-empty">Nenhum problema de cadastro encontrado.</div>';

        var rank = json.ranking || [];
        document.getElementById('fnRankList').innerHTML = rank.length ? rank.map(function (r) {
            return '<div class="fn-rank-row"><span><strong>' + escapeHtml(r.nome) + '</strong></span>' +
                '<span>R$ ' + formatMoneyBr(r.total) + ' <span style="opacity:.6">· ' + (r.n || 0) + ' conta(s)</span></span></div>';
        }).join('') : '<div class="fn-empty">Sem contas a pagar vinculadas a fornecedores.</div>';
    }

    function loadFornInsights(forceRefresh) {
        if (_fnInsightsLoading) return;
        var panel = document.getElementById('fnInsights');
        var summary = document.getElementById('fnSummary');
        if (!panel) return;
        panel.style.display = '';

        if (!forceRefresh) {
            var hit = fnReadInsightsCache();
            if (hit && hit.success) {
                renderFornInsights(hit);
                _fnInsightsLoaded = true;
                return;
            }
        }

        _fnInsightsLoading = true;
        if (summary) summary.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analisando fornecedores…';
        var url = fnInsightsUrl();
        if (forceRefresh) url += (url.indexOf('?') >= 0 ? '&' : '?') + 'refresh=1&_=' + Date.now();
        fetch(url, { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json && json.success) {
                    fnWriteInsightsCache(json);
                    renderFornInsights(json);
                    _fnInsightsLoaded = true;
                } else {
                    panel.style.display = 'none';
                }
            })
            .catch(function () { panel.style.display = 'none'; })
            .then(function () { _fnInsightsLoading = false; });
    }

    function fornGerarResumoIA() {
        if (!_fnLastData) {
            showToast('Aguarde o painel de insights carregar ou recarregue a página.', 'err');
            return;
        }
        var btn = document.getElementById('fnResumoBtn');
        var out = document.getElementById('fnResumoTexto');
        if (btn) { btn.disabled = true; btn.dataset.html = btn.innerHTML; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando…'; }
        if (out) { out.classList.add('text-muted'); out.textContent = 'A IA está analisando os fornecedores…'; }
        var headers = (typeof sfMutationHeaders === 'function') ? sfMutationHeaders({ 'Content-Type': 'application/json' }) : { 'Content-Type': 'application/json' };
        if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__ && !headers['X-CSRF-Token']) headers['X-CSRF-Token'] = window.__SF_CSRF__;
        fetch(apiBase('fornecedor_resumo_ia.php'), { method: 'POST', credentials: 'include', headers: headers, body: JSON.stringify({ dados: _fnLastData }) })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (o) {
                var d = o.d || {};
                if (o.ok && d.success) { if (out) { out.classList.remove('text-muted'); out.textContent = d.resumo; } }
                else if (out) { out.classList.add('text-muted'); out.textContent = d.message || d.error || 'Não foi possível gerar o resumo.'; }
            })
            .catch(function () { if (out) out.textContent = 'Erro de comunicação ao gerar o resumo.'; })
            .then(function () { if (btn) { btn.disabled = false; if (btn.dataset.html) btn.innerHTML = btn.dataset.html; } });
    }

    // ---- Importação CSV ----
    var _fnImportRows = [];

    function fnSplitCsvLine(line, delim) {
        var out = [], cur = '', q = false;
        for (var i = 0; i < line.length; i++) {
            var c = line[i];
            if (c === '"') { q = !q; continue; }
            if (!q && c === delim) { out.push(cur.trim()); cur = ''; continue; }
            cur += c;
        }
        out.push(cur.trim());
        return out;
    }

    function fnParseImportCsv(text) {
        var lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n').filter(function (l) { return l.trim() !== ''; });
        if (!lines.length) return [];
        var delim = (lines[0].match(/;/g) || []).length >= (lines[0].match(/,/g) || []).length ? ';' : ',';
        var head = fnSplitCsvLine(lines[0], delim).map(function (h) { return h.toLowerCase().replace(/^﻿/, '').trim(); });
        var idx = {};
        ['nome', 'tipo', 'documento', 'cnpj', 'cpf', 'email', 'cidade', 'uf'].forEach(function (k) { idx[k] = head.indexOf(k); });
        var rows = [];
        for (var i = 1; i < lines.length; i++) {
            var c = fnSplitCsvLine(lines[i], delim);
            var get = function (k) { return idx[k] >= 0 ? (c[idx[k]] || '') : ''; };
            var nome = get('nome');
            if (!nome) continue;
            rows.push({
                nome: nome,
                tipo: get('tipo'),
                documento: get('documento') || get('cnpj') || get('cpf'),
                email: get('email'),
                cidade: get('cidade'),
                uf: get('uf')
            });
        }
        return rows;
    }

    function fnRenderImportPreview() {
        var tb = document.getElementById('fornImportPreview');
        var info = document.getElementById('fornImportInfo');
        var btn = document.getElementById('fornImportSaveBtn');
        if (!tb) return;
        tb.innerHTML = _fnImportRows.slice(0, 50).map(function (r) {
            return '<tr><td>' + escapeHtml(r.nome) + '</td><td>' + escapeHtml(r.tipo || '-') + '</td><td>' + escapeHtml(r.documento || '-') +
                '</td><td>' + escapeHtml(r.email || '-') + '</td><td>' + escapeHtml((r.cidade || '') + (r.uf ? '/' + r.uf : '')) + '</td></tr>';
        }).join('');
        if (info) info.textContent = _fnImportRows.length + ' linha(s) lida(s).' + (_fnImportRows.length > 50 ? ' Exibindo as 50 primeiras.' : '');
        if (btn) btn.disabled = _fnImportRows.length === 0;
    }

    function fnDoImport() {
        if (!_fnImportRows.length) return;
        var btn = document.getElementById('fornImportSaveBtn');
        var res = document.getElementById('fornImportResult');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importando…'; }
        if (res) { res.textContent = ''; res.style.color = ''; }
        var headers = (typeof sfMutationHeaders === 'function') ? sfMutationHeaders({ 'Content-Type': 'application/json' }) : { 'Content-Type': 'application/json' };
        if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__ && !headers['X-CSRF-Token']) headers['X-CSRF-Token'] = window.__SF_CSRF__;
        fetch(apiBase('fornecedor_import.php'), { method: 'POST', credentials: 'include', headers: headers, body: JSON.stringify({ rows: _fnImportRows }) })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (o) {
                var d = o.d || {};
                if (o.ok && d.success) {
                    var msg = d.inserted + ' fornecedor(es) importado(s).' + (d.skipped ? ' ' + d.skipped + ' ignorado(s).' : '');
                    if (d.errors && d.errors.length) msg += ' Detalhes: ' + d.errors.slice(0, 3).join(' ');
                    if (res) { res.style.color = '#16a34a'; res.textContent = msg; }
                    showToast(msg, 'ok');
                    loadList(1);
                    if (typeof loadFornInsights === 'function') loadFornInsights(true);
                } else {
                    if (res) { res.style.color = '#dc2626'; res.textContent = d.message || d.error || 'Erro ao importar.'; }
                }
            })
            .catch(function () { if (res) { res.style.color = '#dc2626'; res.textContent = 'Erro de comunicação.'; } })
            .then(function () { if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-file-import"></i> Importar'; } });
    }

    function fnPreloadInsightsSummaryFromCache() {
        var hit = fnReadInsightsCache();
        if (hit && hit.success) {
            renderFornInsights(hit);
            var fnBody = document.getElementById('fnBody');
            var resumo = document.getElementById('fnResumoIa');
            if (fnBody) fnBody.style.display = 'none';
            if (resumo) resumo.style.display = 'none';
            var fnToggle = document.getElementById('fnToggle');
            if (fnToggle) fnToggle.setAttribute('aria-expanded', 'false');
            return;
        }
        var panel = document.getElementById('fnInsights');
        var summary = document.getElementById('fnSummary');
        if (panel && summary) {
            panel.style.display = '';
            summary.innerHTML = '<i class="fas fa-clipboard-check"></i> Insights de fornecedores — <span style="font-weight:500;color:var(--text-muted)">expandir para analisar</span>';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        wireKpiQuickFilters();
        wireFornecedoresSortHeaders();
        syncFornSortIndicators();

        var initial = (typeof window.__FORN_INITIAL__ === 'object' && window.__FORN_INITIAL__) ? window.__FORN_INITIAL__ : null;
        if (initial && initial.success) {
            applyFornPayload(initial, false);
            var tbody = document.getElementById('fornecedoresTableBody');
            if (tbody) bindTableActions(tbody);
            if (initial.pagination) {
                _fornPage = initial.pagination.page || 1;
                _fornTotalPages = initial.pagination.total_pages || 1;
                _fornTotal = initial.pagination.total || 0;
                _fornPerPage = initial.pagination.per_page || 10;
            }
        } else {
            loadList(1, { keepTable: false });
        }

        fnPreloadInsightsSummaryFromCache();
        wireCnpjBrasilapiOnce();

        const filtro = document.getElementById('filtroSituacaoFornecedor');
        if (filtro) {
            filtro.addEventListener('change', function () {
                _fornPage = 1;
                loadList(1);
            });
        }

        const search = document.getElementById('searchFornecedor');
        if (search) {
            let t;
            search.addEventListener('input', function () {
                clearTimeout(t);
                t = setTimeout(function () {
                    _fornPage = 1;
                    loadList(1);
                }, 300);
            });
        }

        const tipo = document.getElementById('filterTipoFornecedor');
        if (tipo) {
            tipo.addEventListener('change', function () {
                _fornPage = 1;
                loadList(1);
            });
        }

        const perPage = document.getElementById('perPageFornecedores');
        if (perPage) {
            perPage.addEventListener('change', function () {
                _fornPage = 1;
                loadList(1);
            });
        }

        const prev = document.getElementById('fornPrevPage');
        if (prev) {
            prev.addEventListener('click', function (e) {
                e.preventDefault();
                if (_fornPage > 1) loadList(_fornPage - 1);
            });
        }
        const next = document.getElementById('fornNextPage');
        if (next) {
            next.addEventListener('click', function (e) {
                e.preventDefault();
                if (_fornPage < _fornTotalPages) loadList(_fornPage + 1);
            });
        }

        const apply = document.getElementById('applyFornecedorFilters');
        if (apply) {
            apply.addEventListener('click', function () {
                _fornPage = 1;
                loadList(1);
            });
        }

        const clear = document.getElementById('clearFornecedorFilters');
        if (clear) clear.addEventListener('click', clearLocalFilters);

        const btnNovo = document.getElementById('btnNovoFornecedor');
        if (btnNovo) btnNovo.addEventListener('click', () => openModal(null));
        const fornEmptyCreate = document.getElementById('fornEmptyCreateBtn');
        if (fornEmptyCreate) fornEmptyCreate.addEventListener('click', () => openModal(null));

        const btnSalvar = document.getElementById('btnSalvarFornecedor');
        if (btnSalvar) btnSalvar.addEventListener('click', saveFornecedor);

        wireModalClose('modalFornecedor');
        wireModalClose('filterFornecedorModal');
        wireModalClose('modalPosicaoFinanceira');
        wireModalClose('modalPosicaoFiscal');
        wireModalClose('modalFornImport');

        const filterBtn = document.getElementById('filterBtn');
        if (filterBtn) {
            filterBtn.addEventListener('click', () => {
                syncModalFilterFromPage();
                showModal('filterFornecedorModal');
            });
        }

        const exportBtn = document.getElementById('exportBtn');
        if (exportBtn) exportBtn.addEventListener('click', exportCsv);

        // Insights + resumo IA (lazy: só busca ao expandir o painel)
        var fnToggle = document.getElementById('fnToggle');
        var fnBody = document.getElementById('fnBody');
        if (fnToggle && fnBody) {
            fnToggle.addEventListener('click', function () {
                var open = fnToggle.getAttribute('aria-expanded') === 'true';
                var willOpen = !open;
                fnToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                fnBody.style.display = willOpen ? '' : 'none';
                var resumo = document.getElementById('fnResumoIa');
                if (resumo) resumo.style.display = willOpen ? '' : 'none';
                if (willOpen && !_fnInsightsLoaded && !_fnInsightsLoading) {
                    loadFornInsights(false);
                }
            });
        }
        var fnRefresh = document.getElementById('fnRefresh');
        if (fnRefresh) {
            fnRefresh.addEventListener('click', function (e) {
                e.stopPropagation();
                _fnInsightsLoaded = false;
                try { sessionStorage.removeItem(fnInsightsCacheKey()); } catch (err) {}
                loadFornInsights(true);
            });
        }
        var fnQual = document.getElementById('fnQualList');
        if (fnQual) fnQual.addEventListener('click', function (e) {
            var b = e.target.closest('[data-fn-edit]');
            if (b && typeof openModal === 'function') openModal(parseInt(b.getAttribute('data-fn-edit'), 10));
        });
        var fnResumoBtn = document.getElementById('fnResumoBtn');
        if (fnResumoBtn) fnResumoBtn.addEventListener('click', fornGerarResumoIA);

        // Importação CSV
        var importBtn = document.getElementById('importBtn');
        if (importBtn) importBtn.addEventListener('click', function () {
            _fnImportRows = [];
            var fi = document.getElementById('fornImportFile'); if (fi) fi.value = '';
            var tb = document.getElementById('fornImportPreview'); if (tb) tb.innerHTML = '';
            var info = document.getElementById('fornImportInfo'); if (info) info.textContent = '';
            var res = document.getElementById('fornImportResult'); if (res) res.textContent = '';
            var sv = document.getElementById('fornImportSaveBtn'); if (sv) sv.disabled = true;
            showModal('modalFornImport');
        });
        var fornImportFile = document.getElementById('fornImportFile');
        if (fornImportFile) fornImportFile.addEventListener('change', function () {
            var f = this.files && this.files[0];
            if (!f) return;
            var reader = new FileReader();
            reader.onload = function () { _fnImportRows = fnParseImportCsv(String(reader.result || '')); fnRenderImportPreview(); };
            reader.readAsText(f, 'UTF-8');
        });
        var fornImportSaveBtn = document.getElementById('fornImportSaveBtn');
        if (fornImportSaveBtn) fornImportSaveBtn.addEventListener('click', fnDoImport);
        document.querySelectorAll('[data-close-import]').forEach(function (el) {
            el.addEventListener('click', function () { closeModal('modalFornImport'); });
        });

        const applyModal = document.getElementById('modalFornecedorApplyFilter');
        if (applyModal) applyModal.addEventListener('click', applyModalFilter);

        const clearModal = document.getElementById('modalFornecedorClearFilter');
        if (clearModal) clearModal.addEventListener('click', clearModalFilter);
    });
})();
