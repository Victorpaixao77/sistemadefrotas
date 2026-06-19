// Maintenance management JavaScript

// Escapa texto para inserção segura em HTML (evita XSS via descrição, nome de arquivo, etc.)
function mntEsc(s) {
    if (s == null) return '';
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
// Garante que a URL de um anexo é segura (bloqueia javascript:, data:, etc.)
function mntSafeUrl(u) {
    u = String(u || '').trim();
    if (/^(https?:|\/|\.\.?\/)/i.test(u)) return mntEsc(u);
    return '#';
}

function mntMutationHeaders(extra) {
    if (typeof sfMutationHeaders === 'function') {
        return sfMutationHeaders(extra || {});
    }
    var h = extra ? Object.assign({}, extra) : {};
    if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__) {
        h['X-CSRF-Token'] = window.__SF_CSRF__;
    }
    return h;
}

function mntReloadListPage(preferredPage) {
    var container = document.getElementById('paginationContainer');
    var page = preferredPage || parseInt(container && container.getAttribute('data-page'), 10) || 1;
    if (typeof loadPageViaAjax === 'function') {
        loadPageViaAjax(page);
        return;
    }
    window.location.reload();
}

function enableMaintenanceForm(editable) {
    var form = document.getElementById('maintenanceForm');
    var saveBtn = document.getElementById('saveMaintenanceBtn');
    if (form) {
        Array.from(form.elements).forEach(function (el) {
            if (el.id === 'manutencaoId') return;
            el.disabled = !editable;
        });
    }
    if (saveBtn) saveBtn.style.display = editable ? '' : 'none';
}

function openMaintenanceModalBlank(title) {
    var modal = document.getElementById('maintenanceModal');
    if (!modal) return;
    var form = document.getElementById('maintenanceForm');
    if (form) form.reset();
    document.getElementById('manutencaoId').value = '';
    document.getElementById('modalTitle').textContent = title || 'Nova Manutenção';
    var anexosSec = document.getElementById('anexosSection');
    if (anexosSec) anexosSec.style.display = 'none';
    enableMaintenanceForm(true);
    updateContaPagarLink(null);
    var gerarCp = document.getElementById('gerar_conta_pagar');
    if (gerarCp) gerarCp.checked = true;
    toggleConclusaoFields();
    modal.classList.add('active');
}

function openMaintenanceFromPlano(el) {
    if (!el) return;
    openMaintenanceModalBlank('Nova Manutenção (plano)');
    var vid = el.getAttribute('data-veiculo-id');
    var cid = el.getAttribute('data-componente-id');
    var tid = el.getAttribute('data-tipo-id');
    var km = el.getAttribute('data-km');
    var comp = el.getAttribute('data-componente') || '';
    var tipo = el.getAttribute('data-tipo') || '';
    var placa = el.getAttribute('data-placa') || '';
    if (vid) document.getElementById('veiculo_id').value = vid;
    if (cid) document.getElementById('componente_id').value = cid;
    if (tid) document.getElementById('tipo_manutencao_id').value = tid;
    if (km) document.getElementById('km_atual').value = km;
    var hoje = new Date().toISOString().slice(0, 10);
    document.getElementById('data_manutencao').value = hoje;
    var txt = 'Manutenção ' + tipo + ' — ' + comp + (placa ? ' (' + placa + ')' : '');
    document.getElementById('descricao').value = txt;
    document.getElementById('descricao_servico').value = txt;
}

function openHistoricoVeiculo(veiculoId, placa) {
    if (!veiculoId) return;
    fetch('../api/manutencoes.php?veiculo_id=' + veiculoId, { credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) return;
            var list = data.data || [];
            var total = data.total_custo_veiculo || 0;
            var custo12m = data.custo_12m != null ? data.custo_12m : total;
            var prev = data.total_preventivas != null ? data.total_preventivas : 0;
            var corr = data.total_corretivas != null ? data.total_corretivas : 0;
            document.getElementById('historicoVeiculoTitulo').textContent = 'Relatório - ' + (placa || 'Veículo');
            document.getElementById('historicoVeiculoCusto').textContent = list.length + ' manutenção(ões) no histórico.';
            document.getElementById('historicoVeiculoTotal').textContent = 'R$ ' + Number(total).toFixed(2).replace('.', ',');
            document.getElementById('historicoVeiculoCusto12m').textContent = 'R$ ' + Number(custo12m).toFixed(2).replace('.', ',');
            document.getElementById('historicoVeiculoPreventivas').textContent = prev;
            document.getElementById('historicoVeiculoCorretivas').textContent = corr;
            var tbody = document.getElementById('historicoVeiculoBody');
            tbody.innerHTML = list.map(function (m) {
                return '<tr><td>' + (m.data_manutencao ? m.data_manutencao.split('-').reverse().join('/') : '') + '</td><td>' + mntEsc(m.tipo_nome || '') + '</td><td>' + mntEsc((m.descricao || '').substring(0, 40)) + '</td><td>R$ ' + (parseFloat(m.valor) || 0).toFixed(2).replace('.', ',') + '</td></tr>';
            }).join('');
            document.getElementById('historicoVeiculoModal').classList.add('active');
        });
}

function duplicateMaintenance(id) {
    fetch('../api/manutencoes.php?id=' + id, { credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success || !data.data) {
                mntToast('Não foi possível duplicar.', 'error');
                return;
            }
            openMaintenanceModalBlank('Nova Manutenção (cópia)');
            fillMaintenanceForm(data.data);
            document.getElementById('manutencaoId').value = '';
            document.getElementById('data_manutencao').value = new Date().toISOString().slice(0, 10);
            var dc = document.getElementById('data_conclusao');
            if (dc) dc.value = '';
            mntToast('Registro duplicado — ajuste e salve.', 'info');
        })
        .catch(function () { mntToast('Erro ao duplicar.', 'error'); });
}

function buildOsHtml(m, veiculoLabel) {
    var fmtDate = function (d) { return d ? d.split('-').reverse().join('/') : '—'; };
    var fmtMoney = function (v) { return 'R$ ' + (parseFloat(v) || 0).toFixed(2).replace('.', ','); };
    return '<div class="mann-os-doc">' +
        '<h1>Ordem de Serviço #' + mntEsc(String(m.id || '')) + '</h1>' +
        '<div class="mann-os-grid">' +
        '<div class="mann-os-block"><strong>Veículo</strong>' + mntEsc(veiculoLabel || '') + '</div>' +
        '<div class="mann-os-block"><strong>Data</strong>' + fmtDate(m.data_manutencao) + '</div>' +
        '<div class="mann-os-block"><strong>Componente</strong>' + mntEsc(m.componente_nome || '—') + '</div>' +
        '<div class="mann-os-block"><strong>Tipo</strong>' + mntEsc(m.tipo_nome || '—') + '</div>' +
        '<div class="mann-os-block"><strong>Status</strong>' + mntEsc(m.status_nome || '—') + '</div>' +
        '<div class="mann-os-block"><strong>KM</strong>' + mntEsc(String(m.km_atual != null ? m.km_atual : '—')) + '</div>' +
        '<div class="mann-os-block"><strong>Fornecedor</strong>' + mntEsc(m.fornecedor || '—') + '</div>' +
        '<div class="mann-os-block"><strong>Valor</strong>' + fmtMoney(m.valor) + '</div>' +
        '<div class="mann-os-block"><strong>Nota fiscal</strong>' + mntEsc(m.nota_fiscal || '—') + '</div>' +
        '</div>' +
        '<div class="mann-os-block"><strong>Descrição</strong>' + mntEsc(m.descricao || '') + '</div>' +
        '<div class="mann-os-block"><strong>Serviço executado</strong>' + mntEsc(m.descricao_servico || '') + '</div>' +
        (m.observacoes ? '<div class="mann-os-block"><strong>Observações</strong>' + mntEsc(m.observacoes) + '</div>' : '') +
        '<div class="mann-os-block"><strong>Responsável</strong>' + mntEsc(m.responsavel_aprovacao || '—') + '</div>' +
        '<p class="mann-os-footer">Emitido em ' + new Date().toLocaleString('pt-BR') + ' — Sistema de Gestão de Frotas</p>' +
        '</div>';
}

var MNT_OS_PRINT_CSS =
    'body{font-family:system-ui,-apple-system,sans-serif;margin:20mm;color:#111;font-size:11pt;line-height:1.45}' +
    '.mann-os-doc h1{font-size:16pt;margin:0 0 12px;border-bottom:1px solid #ccc;padding-bottom:8px}' +
    '.mann-os-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px 16px;margin-bottom:12px}' +
    '.mann-os-block{margin-bottom:10px}' +
    '.mann-os-block strong{display:block;font-size:8pt;text-transform:uppercase;color:#555;margin-bottom:2px}' +
    '.mann-os-footer{margin-top:24px;font-size:8pt;color:#666}';

function mntPrintHtml(html, title) {
    var iframe = document.createElement('iframe');
    iframe.setAttribute('aria-hidden', 'true');
    iframe.style.cssText = 'position:fixed;width:0;height:0;border:0;left:-9999px;top:0';
    document.body.appendChild(iframe);
    var win = iframe.contentWindow;
    var doc = win.document;
    doc.open();
    doc.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + mntEsc(title || 'Ordem de Serviço') + '</title><style>' + MNT_OS_PRINT_CSS + '</style></head><body>' + html + '</body></html>');
    doc.close();
    setTimeout(function () {
        try {
            win.focus();
            win.print();
        } catch (e) { /* ignore */ }
        setTimeout(function () { iframe.remove(); }, 1500);
    }, 300);
}

function printOrdemServico(id) {
    fetch('../api/manutencoes.php?id=' + id, { credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success || !data.data) {
                mntToast('Não foi possível gerar a OS.', 'error');
                return;
            }
            var m = data.data;
            var veiculoLabel = m.veiculo_placa || '';
            if (!veiculoLabel && m.veiculo_id) {
                var sel = document.getElementById('veiculo_id');
                if (sel) {
                    var opt = sel.querySelector('option[value="' + m.veiculo_id + '"]');
                    if (opt) veiculoLabel = opt.textContent;
                }
            }
            var html = buildOsHtml(m, veiculoLabel);
            document.getElementById('osPrintBody').innerHTML = html;
            document.getElementById('osPrintModal').classList.add('active');
        })
        .catch(function () { mntToast('Erro ao gerar OS.', 'error'); });
}

function loadFornecedoresDatalist() {
    var dl = document.getElementById('fornecedoresDatalist');
    if (!dl) return;
    fetch('../api/fornecedores.php?action=list&all=1&situacao=A', { credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (json) {
            if (!json.success || !json.fornecedores) return;
            dl.innerHTML = json.fornecedores.map(function (f) {
                var nome = f.nome || f.razao_social || f.nome_fantasia || '';
                return nome ? '<option value="' + mntEsc(nome) + '"></option>' : '';
            }).join('');
        })
        .catch(function () { /* opcional */ });
}

function setupAlertAndScoreClicks() {
    document.querySelectorAll('[data-mnt-alert-plano]').forEach(function (el) {
        el.addEventListener('click', function () { openMaintenanceFromPlano(el); });
        el.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openMaintenanceFromPlano(el); } });
    });
    document.querySelectorAll('[data-mnt-alert-intel]').forEach(function (el) {
        function go() {
            var vid = el.getAttribute('data-veiculo-id');
            if (!vid) return;
            var placa = el.getAttribute('data-placa') || '';
            var cid = el.getAttribute('data-componente-id');
            var tipo = el.getAttribute('data-alert-tipo');
            if (tipo === 'corretivas_componente' && cid) {
                openMaintenanceModalBlank('Nova Manutenção (alerta)');
                document.getElementById('veiculo_id').value = vid;
                document.getElementById('componente_id').value = cid;
                document.getElementById('data_manutencao').value = new Date().toISOString().slice(0, 10);
                document.getElementById('descricao').value = 'Corretiva — componente com recorrência (' + (placa || 'veículo') + ')';
                document.getElementById('descricao_servico').value = document.getElementById('descricao').value;
                return;
            }
            openHistoricoVeiculo(vid, placa);
        }
        el.addEventListener('click', go);
        el.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
    });
    document.querySelectorAll('.score-row.score-clickable').forEach(function (row) {
        row.addEventListener('click', function () {
            openHistoricoVeiculo(row.getAttribute('data-veiculo-id'), row.getAttribute('data-placa'));
        });
    });
    document.querySelectorAll('.mann-ranking-row').forEach(function (row) {
        row.addEventListener('click', function () {
            openHistoricoVeiculo(row.getAttribute('data-veiculo-id'), row.getAttribute('data-placa'));
        });
    });
    var osBtn = document.getElementById('osPrintBtn');
    if (osBtn) osBtn.addEventListener('click', function () {
        var body = document.getElementById('osPrintBody');
        if (body && body.innerHTML.trim()) {
            mntPrintHtml(body.innerHTML, 'Ordem de Serviço');
        }
    });
    setupNfOcr();
    setupMaintenanceImport();
}

// ---- Toast + confirmação (substitui alert()/confirm() nativos) ----
function mntToast(msg, type) {
    var wrap = document.getElementById('mntToastWrap');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.id = 'mntToastWrap';
        document.body.appendChild(wrap);
    }
    var t = document.createElement('div');
    t.className = 'mnt-toast ' + (type || 'info');
    var icon = type === 'success' ? 'fa-circle-check' : (type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-info');
    t.innerHTML = '<i class="fas ' + icon + '"></i><span></span>';
    t.querySelector('span').textContent = msg;
    wrap.appendChild(t);
    requestAnimationFrame(function () { t.classList.add('show'); });
    setTimeout(function () { t.classList.remove('show'); setTimeout(function () { t.remove(); }, 300); }, 3800);
}

function mntConfirm(message, opts) {
    opts = opts || {};
    return new Promise(function (resolve) {
        var modal = document.getElementById('mntConfirmModal');
        var msgEl = document.getElementById('mntConfirmMsg');
        var ok = document.getElementById('mntConfirmOk');
        var cancel = document.getElementById('mntConfirmCancel');
        if (!modal || !ok || !cancel) { resolve(window.confirm(message)); return; }
        if (msgEl) msgEl.textContent = message || 'Tem certeza?';
        if (opts.okLabel) ok.innerHTML = '<i class="fas fa-check"></i> ' + opts.okLabel;
        modal.style.display = 'flex';
        function cleanup(v) {
            modal.style.display = 'none';
            ok.removeEventListener('click', onOk);
            cancel.removeEventListener('click', onCancel);
            modal.removeEventListener('click', onBg);
            resolve(v);
        }
        function onOk() { cleanup(true); }
        function onCancel() { cleanup(false); }
        function onBg(e) { if (e.target === modal) cleanup(false); }
        ok.addEventListener('click', onOk);
        cancel.addEventListener('click', onCancel);
        modal.addEventListener('click', onBg);
    });
}

// ---- Painéis colapsáveis (alertas, score, ranking, insights) ----
function setupMntCollapsiblePanels() {
    document.querySelectorAll('.mnt-insights .mnt-toggle').forEach(function (toggle) {
        if (toggle.id === 'mntToggle') return;
        if (toggle.dataset.mntToggleWired) return;
        toggle.dataset.mntToggleWired = '1';
        var bodyId = toggle.getAttribute('aria-controls');
        var body = bodyId ? document.getElementById(bodyId) : null;
        if (!body) {
            var panel = toggle.closest('.mnt-insights');
            body = panel ? panel.querySelector('.mnt-body') : null;
        }
        if (!body) return;
        toggle.addEventListener('click', function () {
            var open = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
            body.style.display = open ? 'none' : '';
        });
    });
}

// ---- Insights de manutenção (preventiva + anomalias + custo + IA) ----
var mntLastData = null;
var mntInsightsLoaded = false;
var mntInsightsLoading = false;
var MNT_INSIGHTS_URL = '../api/maintenance_insights.php';
var MNT_CACHE_TTL_MS = 5 * 60 * 1000;

function mntInsightsCacheKey() {
    return 'sf_insights_v1_' + MNT_INSIGHTS_URL.replace(/[^a-z0-9]/gi, '_');
}

function mntReadInsightsCache() {
    try {
        var raw = sessionStorage.getItem(mntInsightsCacheKey());
        if (!raw) return null;
        var o = JSON.parse(raw);
        if (!o || !o.ts || !o.data) return null;
        if (Date.now() - o.ts > (o.ttl || MNT_CACHE_TTL_MS)) {
            sessionStorage.removeItem(mntInsightsCacheKey());
            return null;
        }
        return o.data;
    } catch (e) { return null; }
}

function mntWriteInsightsCache(data) {
    try {
        sessionStorage.setItem(mntInsightsCacheKey(), JSON.stringify({ ts: Date.now(), ttl: MNT_CACHE_TTL_MS, data: data }));
    } catch (e) { /* quota */ }
}

function setupMaintenanceInsights() {
    setupMntCollapsiblePanels();
    var panel = document.getElementById('mntInsights');
    var summary = document.getElementById('mntSummary');
    var toggle = document.getElementById('mntToggle');
    var body = document.getElementById('mntBody');

    if (panel && summary) {
        panel.style.display = '';
        summary.innerHTML = '<i class="fas fa-clipboard-check"></i> Insights de manutenção — <span style="font-weight:500;color:var(--text-muted)">expandir para analisar</span>';
    }

    if (toggle && body) {
        toggle.addEventListener('click', function () {
            var open = toggle.getAttribute('aria-expanded') === 'true';
            var willOpen = !open;
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            body.style.display = willOpen ? '' : 'none';
            if (willOpen && !mntInsightsLoaded && !mntInsightsLoading) {
                loadMaintenanceInsights(false);
            }
        });
    }

    var refresh = document.getElementById('mntRefresh');
    if (refresh) {
        refresh.addEventListener('click', function (e) {
            e.stopPropagation();
            mntInsightsLoaded = false;
            try { sessionStorage.removeItem(mntInsightsCacheKey()); } catch (err) {}
            loadMaintenanceInsights(true);
        });
    }
    var anomList = document.getElementById('mntAnomList');
    if (anomList) {
        anomList.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-mnt-edit]');
            if (btn && typeof editMaintenance === 'function') editMaintenance(btn.getAttribute('data-mnt-edit'));
        });
    }
    var resumoBtn = document.getElementById('mntResumoBtn');
    if (resumoBtn) resumoBtn.addEventListener('click', maintenanceGerarResumoIA);
}

function renderMaintenanceInsights(json) {
    mntLastData = json;
    var panel = document.getElementById('mntInsights');
    var summary = document.getElementById('mntSummary');
    if (!panel || !summary) return;
    panel.style.display = '';
    document.getElementById('mntResumoIa').style.display = '';

    var venc = json.prev_vencidos || 0, aVenc = json.prev_a_vencer || 0;
    var anErr = json.anom_erros || 0, anAvi = json.anom_avisos || 0;
    var totalErros = venc + anErr;
    panel.classList.remove('has-erros', 'has-avisos');
    if (totalErros > 0) panel.classList.add('has-erros');
    else if (aVenc + anAvi > 0) panel.classList.add('has-avisos');

    summary.innerHTML = '<i class="fas fa-clipboard-check" style="color:' + (totalErros > 0 ? '#dc2626' : (aVenc + anAvi > 0 ? '#f59e0b' : '#16a34a')) + '"></i> Insights de manutenção' +
        ((venc + aVenc) > 0 ? '<span class="mnt-pill ' + (venc > 0 ? 'erro' : 'aviso') + '">' + (venc + aVenc) + ' preventiva(s)</span>' : '') +
        ((anErr + anAvi) > 0 ? '<span class="mnt-pill ' + (anErr > 0 ? 'erro' : 'aviso') + '">' + (anErr + anAvi) + ' anomalia(s)</span>' : '') +
        ((venc + aVenc + anErr + anAvi) === 0 ? '<span class="mnt-pill ok">tudo certo</span>' : '');

    // Preventivos
    var prev = json.preventivos || [];
    document.getElementById('mntPrevList').innerHTML = prev.length ? prev.map(function (p) {
        var icon = p.severidade === 'erro' ? 'fa-circle-xmark' : 'fa-clock';
        return '<div class="mnt-item"><span class="mnt-sev ' + p.severidade + '"><i class="fas ' + icon + '"></i></span>' +
            '<div class="mnt-txt"><strong>' + mntEsc(p.placa) + '</strong> — ' + mntEsc(p.motivo) + '</div></div>';
    }).join('') : '<div class="mnt-empty">Nenhuma manutenção preventiva pendente.</div>';

    // Anomalias
    var anom = json.anomalias || [];
    document.getElementById('mntAnomList').innerHTML = anom.length ? anom.map(function (a) {
        var dataBr = a.data ? a.data.split('-').reverse().join('/') : '';
        var icon = a.severidade === 'erro' ? 'fa-circle-xmark' : 'fa-triangle-exclamation';
        var motivos = (a.motivos || []).map(function (m) { return mntEsc(m.msg); }).join(' ');
        return '<div class="mnt-item"><span class="mnt-sev ' + a.severidade + '"><i class="fas ' + icon + '"></i></span>' +
            '<div class="mnt-txt"><strong>' + mntEsc(dataBr) + '</strong> · ' + mntEsc(a.veiculo) + ' · ' + mntEsc(a.tipo) + ' — ' + motivos + '</div>' +
            '<button type="button" class="btn-icon mnt-edit" data-mnt-edit="' + mntEsc(a.id) + '" title="Abrir manutenção"><i class="fas fa-edit"></i></button></div>';
    }).join('') : '<div class="mnt-empty">Nenhuma anomalia encontrada.</div>';

    // Custo por veículo
    var custo = json.custo_veiculos || [];
    document.getElementById('mntCustoList').innerHTML = custo.length ? custo.map(function (c) {
        var porKm = c.por_km != null ? ('R$ ' + Number(c.por_km).toFixed(2).replace('.', ',') + '/km') : '—';
        var total = 'R$ ' + Number(c.total || 0).toFixed(2).replace('.', ',');
        return '<div class="mnt-custo-row"><span><strong>' + mntEsc(c.placa) + '</strong></span><span>' + total + ' · ' + porKm + '</span></div>';
    }).join('') : '<div class="mnt-empty">Sem dados de custo.</div>';
}

function loadMaintenanceInsights(forceRefresh) {
    if (mntInsightsLoading) return;
    var panel = document.getElementById('mntInsights');
    var summary = document.getElementById('mntSummary');
    if (!panel) return;
    panel.style.display = '';

    if (!forceRefresh) {
        var hit = mntReadInsightsCache();
        if (hit && hit.success) {
            renderMaintenanceInsights(hit);
            mntInsightsLoaded = true;
            return;
        }
    }

    mntInsightsLoading = true;
    if (summary) summary.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analisando manutenções…';
    var url = MNT_INSIGHTS_URL;
    if (forceRefresh) url += (url.indexOf('?') >= 0 ? '&' : '?') + 'refresh=1&_=' + Date.now();
    fetch(url, { credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (json) {
            if (json && json.success) {
                mntWriteInsightsCache(json);
                renderMaintenanceInsights(json);
                mntInsightsLoaded = true;
            } else {
                panel.style.display = 'none';
            }
        })
        .catch(function () { panel.style.display = 'none'; })
        .then(function () { mntInsightsLoading = false; });
}

function maintenanceGerarResumoIA() {
    if (!mntLastData) return;
    var btn = document.getElementById('mntResumoBtn');
    var out = document.getElementById('mntResumoTexto');
    if (btn) { btn.disabled = true; btn.dataset.html = btn.innerHTML; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando…'; }
    if (out) { out.classList.add('text-muted'); out.textContent = 'A IA está analisando as manutenções…'; }
    var headers = (typeof sfMutationHeaders === 'function') ? sfMutationHeaders({ 'Content-Type': 'application/json' }) : { 'Content-Type': 'application/json' };
    if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__ && !headers['X-CSRF-Token']) headers['X-CSRF-Token'] = window.__SF_CSRF__;
    fetch('../api/maintenance_resumo_ia.php', { method: 'POST', credentials: 'include', headers: headers, body: JSON.stringify({ dados: mntLastData }) })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (o) {
            var d = o.d || {};
            if (o.ok && d.success) { if (out) { out.classList.remove('text-muted'); out.textContent = d.resumo; } }
            else if (out) { out.classList.add('text-muted'); out.textContent = d.message || d.error || 'Não foi possível gerar o resumo.'; }
        })
        .catch(function () { if (out) out.textContent = 'Erro de comunicação ao gerar o resumo.'; })
        .then(function () { if (btn) { btn.disabled = false; if (btn.dataset.html) btn.innerHTML = btn.dataset.html; } });
}

document.addEventListener('DOMContentLoaded', function() {
    initializePage();
    initializeModals();
    loadVehicles();
    loadFornecedoresDatalist();
    setupMntCollapsiblePanels();
    setupMaintenanceInsights();
    setupAlertAndScoreClicks();
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

    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    if (cancelDeleteBtn) {
        cancelDeleteBtn.addEventListener('click', function () {
            const delModal = document.getElementById('deleteMaintenanceModal');
            if (delModal) delModal.classList.remove('active');
        });
    }

    // Add Maintenance button
    const mannEmptyCreate = document.getElementById('mannEmptyCreateBtn');
    if (mannEmptyCreate) {
        mannEmptyCreate.addEventListener('click', function () {
            const addBtn = document.getElementById('addMaintenanceBtn');
            if (addBtn) addBtn.click();
        });
    }

    const addMaintenanceBtn = document.getElementById('addMaintenanceBtn');
    if (addMaintenanceBtn) {
        addMaintenanceBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openMaintenanceModalBlank('Nova Manutenção');
        });
    }

    const cancelMaintenanceBtn = document.getElementById('cancelMaintenanceBtn');
    if (cancelMaintenanceBtn) {
        cancelMaintenanceBtn.addEventListener('click', function () {
            document.getElementById('maintenanceModal').classList.remove('active');
        });
    }

    // Close buttons for modals (apenas .close-modal e botões com data-close-modal)
    document.querySelectorAll('.close-modal, [data-close-modal]').forEach(button => {
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
    var dcEl = document.getElementById('data_conclusao');
    if (dcEl) {
        dcEl.addEventListener('change', function () {
            var cv = document.getElementById('conta_vencimento');
            if (!cv || cv.value) return;
            if (!dcEl.value) return;
            var d = new Date(dcEl.value + 'T12:00:00');
            d.setDate(d.getDate() + 30);
            cv.value = d.toISOString().slice(0, 10);
        });
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
            if (!id) return;
            mntConfirm('Excluir este anexo?', { okLabel: 'Excluir' }).then(function (ok) {
                if (!ok) return;
                var fd = new FormData();
                fd.append('action', 'delete');
                fd.append('id', id);
                fetch('../api/manutencao_anexos.php', { method: 'POST', credentials: 'include', headers: mntMutationHeaders(), body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            var mid = document.getElementById('manutencaoId') && document.getElementById('manutencaoId').value;
                            if (mid) loadAnexos(parseInt(mid, 10));
                            mntToast('Anexo excluído.', 'success');
                        } else {
                            mntToast(data.error || 'Erro ao excluir.', 'error');
                        }
                    });
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
        // Escape: js/modal_a11y.js (global)
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
            openHistoricoVeiculo(histBtn.dataset.veiculoId, histBtn.dataset.placa || '');
            return;
        }
        const dupBtn = e.target.closest('.duplicate-btn');
        if (dupBtn) { e.preventDefault(); duplicateMaintenance(dupBtn.dataset.id); return; }
        const osBtn = e.target.closest('.os-btn');
        if (osBtn) { e.preventDefault(); printOrdemServico(osBtn.dataset.id); return; }
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
        dir: d.get('dir') || 'DESC',
        q: d.get('q') || '',
        veiculo_id: d.get('veiculo_id') || '',
        tipo_classe: d.get('tipo_classe') || '',
        status_id: d.get('status_id') || '',
        fornecedor: d.get('fornecedor') || ''
    };
}

function appendListParams(params, p) {
    params.set('list', '1');
    params.set('page', String(p.page || 1));
    params.set('per_page', p.per_page || '10');
    if (p.data_inicio) params.set('data_inicio', p.data_inicio);
    if (p.data_fim) params.set('data_fim', p.data_fim);
    if (p.order) params.set('order', p.order);
    if (p.dir) params.set('dir', p.dir);
    if (p.q) params.set('q', p.q);
    if (p.veiculo_id) params.set('veiculo_id', p.veiculo_id);
    if (p.tipo_classe) params.set('tipo_classe', p.tipo_classe);
    if (p.status_id) params.set('status_id', p.status_id);
    if (p.fornecedor) params.set('fornecedor', p.fornecedor);
}

function buildListUrl(page) {
    const p = getPaginationParams();
    p.page = page;
    const params = new URLSearchParams();
    appendListParams(params, p);
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
    if (d.get('order')) params.set('order', d.get('order'));
    if (d.get('dir')) params.set('dir', d.get('dir'));
    if (d.get('q')) params.set('q', d.get('q'));
    if (d.get('veiculo_id')) params.set('veiculo_id', d.get('veiculo_id'));
    if (d.get('tipo_classe')) params.set('tipo_classe', d.get('tipo_classe'));
    if (d.get('status_id')) params.set('status_id', d.get('status_id'));
    if (d.get('fornecedor')) params.set('fornecedor', d.get('fornecedor'));
    if (!document.body.classList.contains('manutencoes-modern')) {
        params.set('classic', '1');
    }
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
        '<button class="btn-icon os-btn" data-id="' + m.id + '" title="Imprimir ordem de serviço"><i class="fas fa-print"></i></button> ' +
        '<button class="btn-icon duplicate-btn" data-id="' + m.id + '" title="Duplicar manutenção"><i class="fas fa-copy"></i></button> ' +
        '<button class="btn-icon historico-veiculo-btn" data-veiculo-id="' + vid + '" data-placa="' + placa + '" title="Histórico do veículo"><i class="fas fa-history"></i></button> ' +
        '<button class="btn-icon view-btn" data-id="' + m.id + '" title="Ver detalhes"><i class="fas fa-eye"></i></button> ' +
        '<button class="btn-icon edit-btn" data-id="' + m.id + '" title="Editar"><i class="fas fa-edit"></i></button> ' +
        '<button class="btn-icon delete-btn" data-id="' + m.id + '" title="Excluir"><i class="fas fa-trash"></i></button>' +
        '</td></tr>';
}

function syncMannEmptyState(isEmpty) {
    const el = document.getElementById('mannEmptyState');
    const wrap = document.getElementById('mannTableWrap');
    if (!el || !wrap || !document.body.classList.contains('manutencoes-modern')) return;
    el.classList.toggle('visible', !!isEmpty);
    wrap.style.display = isEmpty ? 'none' : '';
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
    const container = tbody.closest('.data-table-container') || tbody.closest('.fornc-table-wrap') || tbody.closest('.mann-table-wrap');
    if (container) container.classList.add('loading');
    return fetch(url, { credentials: 'include' })
        .then(r => r.json())
        .then(data => {
            if (container) container.classList.remove('loading');
            if (!data.success || !data.data) return;
            const rows = data.data;
            const isEmpty = !rows.length;
            tbody.innerHTML = isEmpty ? '' : rows.map(rowHtml).join('');
            syncMannEmptyState(isEmpty);
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
            mntToast('Erro ao carregar dados da manutenção: ' + data.error, 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        mntToast('Erro ao carregar dados da manutenção', 'error');
    });
}

function showMaintenanceDetails(maintenance) {
    const modal = document.getElementById('maintenanceModal');
    if (modal) {
        // Preencher os campos do modal com os dados
        document.getElementById('modalTitle').textContent = 'Detalhes da Manutenção';
        enableMaintenanceForm(false);
        
        // Preencher os campos
        fillMaintenanceForm(maintenance);
        
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
                enableMaintenanceForm(true);
                
                // Preencher os campos
                fillMaintenanceForm(data.data);
                
                modal.classList.add('active');
            }
        } else {
            mntToast('Erro ao carregar dados da manutenção: ' + data.error, 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        mntToast('Erro ao carregar dados da manutenção', 'error');
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
    updateContaPagarLink(data.conta_pagar_id || null);
    var gerarCp = document.getElementById('gerar_conta_pagar');
    if (gerarCp) gerarCp.checked = !data.conta_pagar_id;
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
    const wrapConta = document.getElementById('wrap_gerar_conta');
    if (!sel || !wrapDc) return;
    const text = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : '';
    const show = text.toLowerCase().indexOf('conclu') !== -1;
    wrapDc.style.display = show ? 'block' : 'none';
    if (wrapCk) wrapCk.style.display = show ? 'block' : 'none';
    if (wrapConta) wrapConta.style.display = show ? 'block' : 'none';
    if (show) {
        document.getElementById('data_conclusao').setAttribute('required', 'required');
        var dc = document.getElementById('data_conclusao');
        var cv = document.getElementById('conta_vencimento');
        if (dc && dc.value && cv && !cv.value) {
            var d = new Date(dc.value + 'T12:00:00');
            d.setDate(d.getDate() + 30);
            cv.value = d.toISOString().slice(0, 10);
        }
    } else {
        document.getElementById('data_conclusao').removeAttribute('required');
    }
}

function updateContaPagarLink(contaId) {
    var wrap = document.getElementById('contaPagarLinkWrap');
    var link = document.getElementById('contaPagarLink');
    if (!wrap || !link) return;
    if (contaId) {
        wrap.style.display = 'block';
        link.href = 'contas_pagar.php?highlight=' + encodeURIComponent(contaId);
        link.textContent = 'Ver conta a pagar #' + contaId;
    } else {
        wrap.style.display = 'none';
    }
}

function setupNfOcr() {
    var btn = document.getElementById('nfOcrBtn');
    var file = document.getElementById('nfOcrFile');
    if (!btn || !file) return;
    btn.addEventListener('click', function () { file.click(); });
    file.addEventListener('change', function () {
        if (!file.files || !file.files[0]) return;
        var fd = new FormData();
        fd.append('image', file.files[0]);
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        fetch('../api/maintenance_ocr_nf.php', { method: 'POST', credentials: 'include', headers: mntMutationHeaders(), body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success || !data.data) {
                    mntToast(data.error || data.message || 'Não foi possível ler a NF.', 'error');
                    return;
                }
                var d = data.data;
                if (d.nota_fiscal) document.getElementById('nota_fiscal').value = d.nota_fiscal;
                if (d.fornecedor) document.getElementById('fornecedor').value = d.fornecedor;
                if (d.valor != null && d.valor > 0) {
                    document.getElementById('valor').value = d.valor;
                    var ct = document.getElementById('custo_total');
                    if (ct && !ct.value) ct.value = d.valor;
                }
                if (d.data_manutencao) {
                    var dm = document.getElementById('data_manutencao');
                    if (dm && !dm.value) dm.value = d.data_manutencao;
                }
                if (d.descricao) {
                    var desc = document.getElementById('descricao');
                    if (desc && !desc.value.trim()) desc.value = d.descricao;
                    var ds = document.getElementById('descricao_servico');
                    if (ds && !ds.value.trim()) ds.value = d.descricao;
                }
                mntToast('NF lida com ' + (data.provider || 'IA') + '. Revise os campos.', 'success');
            })
            .catch(function () { mntToast('Erro ao ler NF.', 'error'); })
            .then(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-camera"></i>';
                file.value = '';
            });
    });
}

var _mntImportRows = [];

function mntSplitCsvLine(line, delim) {
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

function mntParseImportCsv(text) {
    var lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n').filter(function (l) { return l.trim() !== ''; });
    if (!lines.length) return [];
    var delim = (lines[0].match(/;/g) || []).length >= (lines[0].match(/,/g) || []).length ? ';' : ',';
    var head = mntSplitCsvLine(lines[0], delim).map(function (h) { return h.toLowerCase().replace(/^\uFEFF/, '').trim(); });
    var idx = {};
    ['data', 'placa', 'tipo', 'componente', 'fornecedor', 'valor', 'km', 'descricao', 'descricao_servico', 'nota_fiscal', 'status'].forEach(function (k) {
        idx[k] = head.indexOf(k);
    });
    var rows = [];
    for (var i = 1; i < lines.length; i++) {
        var c = mntSplitCsvLine(lines[i], delim);
        var get = function (k) { return idx[k] >= 0 ? (c[idx[k]] || '') : ''; };
        if (!get('placa')) continue;
        rows.push({
            data: get('data'),
            placa: get('placa'),
            tipo: get('tipo'),
            componente: get('componente'),
            fornecedor: get('fornecedor'),
            valor: get('valor'),
            km: get('km'),
            descricao: get('descricao'),
            descricao_servico: get('descricao_servico'),
            nota_fiscal: get('nota_fiscal'),
            status: get('status')
        });
    }
    return rows;
}

function mntRenderImportPreview() {
    var tb = document.getElementById('mntImportPreview');
    var info = document.getElementById('mntImportInfo');
    var btn = document.getElementById('mntImportSaveBtn');
    if (!tb) return;
    tb.innerHTML = _mntImportRows.slice(0, 30).map(function (r) {
        return '<tr><td>' + mntEsc(r.data) + '</td><td>' + mntEsc(r.placa) + '</td><td>' + mntEsc(r.tipo || '-') +
            '</td><td>' + mntEsc(r.componente || '-') + '</td><td>' + mntEsc(r.fornecedor || '-') + '</td><td>' + mntEsc(r.valor) + '</td></tr>';
    }).join('');
    if (info) info.textContent = _mntImportRows.length + ' linha(s).' + (_mntImportRows.length > 30 ? ' Exibindo as 30 primeiras.' : '');
    if (btn) btn.disabled = _mntImportRows.length === 0;
}

function setupMaintenanceImport() {
    var openBtn = document.getElementById('mntImportBtn');
    var modal = document.getElementById('mntImportModal');
    var file = document.getElementById('mntImportFile');
    var saveBtn = document.getElementById('mntImportSaveBtn');
    if (!openBtn || !modal) return;
    openBtn.addEventListener('click', function () {
        _mntImportRows = [];
        mntRenderImportPreview();
        var res = document.getElementById('mntImportResult');
        if (res) res.textContent = '';
        modal.classList.add('active');
    });
    document.querySelectorAll('[data-close-mnt-import]').forEach(function (el) {
        el.addEventListener('click', function () { modal.classList.remove('active'); });
    });
    if (file) {
        file.addEventListener('change', function () {
            if (!file.files || !file.files[0]) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                _mntImportRows = mntParseImportCsv(String(e.target.result || ''));
                mntRenderImportPreview();
            };
            reader.readAsText(file.files[0], 'UTF-8');
        });
    }
    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            if (!_mntImportRows.length) return;
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importando…';
            var res = document.getElementById('mntImportResult');
            fetch('../api/maintenance_import.php', {
                method: 'POST',
                credentials: 'include',
                headers: mntMutationHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ rows: _mntImportRows })
            })
                .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
                .then(function (o) {
                    var d = o.d || {};
                    if (o.ok && d.success) {
                        var msg = d.message || (d.inserted + ' importada(s).');
                        if (d.errors && d.errors.length) msg += ' Avisos: ' + d.errors.slice(0, 3).join(' ');
                        if (res) { res.style.color = '#16a34a'; res.textContent = msg; }
                        mntToast(msg, 'success');
                        mntReloadListPage(1);
                    } else {
                        var err = d.error || d.message || 'Erro ao importar.';
                        if (res) { res.style.color = '#dc2626'; res.textContent = err; }
                        mntToast(err, 'error');
                    }
                })
                .catch(function () {
                    if (res) { res.style.color = '#dc2626'; res.textContent = 'Erro de comunicação.'; }
                })
                .then(function () {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-file-import"></i> Importar';
                });
        });
    }
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
        return '<div class="anexo-item"><a href="' + mntSafeUrl(a.url) + '" target="_blank" rel="noopener">' + mntEsc(a.nome_original || 'Anexo') + '</a> ' +
            '<button type="button" class="btn-icon delete-anexo-btn" data-id="' + mntEsc(a.id) + '" title="Excluir"><i class="fas fa-trash"></i></button></div>';
    }).join('');
}

function uploadAnexo() {
    var mid = document.getElementById('manutencaoId') && document.getElementById('manutencaoId').value;
    var fileInput = document.getElementById('anexoFile');
    if (!mid || !fileInput || !fileInput.files || !fileInput.files.length) {
        mntToast('Salve a manutenção primeiro ou selecione um arquivo.', 'error');
        return;
    }
    var fd = new FormData();
    fd.append('manutencao_id', mid);
    fd.append('file', fileInput.files[0]);
    var btn = document.getElementById('uploadAnexoBtn');
    if (btn) btn.disabled = true;
    fetch('../api/manutencao_anexos.php', { method: 'POST', credentials: 'include', headers: mntMutationHeaders(), body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (btn) btn.disabled = false;
            if (data.success) {
                fileInput.value = '';
                loadAnexos(parseInt(mid, 10));
            } else {
                mntToast(data.error || 'Erro ao enviar anexo.', 'error');
            }
        })
        .catch(function() {
            if (btn) btn.disabled = false;
            mntToast('Erro ao enviar anexo.', 'error');
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
        mntToast('Por favor, preencha todos os campos obrigatórios.', 'error');
        return;
    }

    const statusSel = document.getElementById('status_manutencao_id');
    const statusTexto = statusSel.options[statusSel.selectedIndex] ? statusSel.options[statusSel.selectedIndex].text : '';
    const isConcluida = statusTexto.toLowerCase().indexOf('conclu') !== -1;
    if (isConcluida && !document.getElementById('data_conclusao').value.trim()) {
        mntToast('Quando o status é Concluída, a Data de Conclusão é obrigatória.', 'error');
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
        responsavel_aprovacao: document.getElementById('responsavel_aprovacao').value,
        gerar_conta_pagar: !!(document.getElementById('gerar_conta_pagar') && document.getElementById('gerar_conta_pagar').checked),
        conta_vencimento: (document.getElementById('conta_vencimento') && document.getElementById('conta_vencimento').value) || null
    };

    // Determinar se é uma nova manutenção ou atualização
    const method = formData.id ? 'PUT' : 'POST';

    // Enviar dados
    fetch('../api/manutencoes.php', {
        method: method,
        headers: mntMutationHeaders({ 'Content-Type': 'application/json' }),
        credentials: 'include',
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            var msg = data.message;
            if (data.conta_message) msg += ' ' + data.conta_message;
            mntToast(msg, 'success');
            if (data.conta_pagar_id) updateContaPagarLink(data.conta_pagar_id);
            const modal = document.getElementById('maintenanceModal');
            if (modal) {
                modal.classList.remove('active');
            }
            mntReloadListPage(formData.id ? null : 1);
        } else {
            mntToast('Erro ao salvar manutenção: ' + data.error, 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        mntToast('Erro ao salvar manutenção', 'error');
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
    mntConfirm('Tem certeza que deseja excluir esta manutenção? Esta ação não pode ser desfeita.', { okLabel: 'Excluir' }).then(function (ok) {
        if (!ok) return;
        fetch(`../api/manutencoes.php?id=${id}`, {
            method: 'DELETE',
            credentials: 'include',
            headers: mntMutationHeaders()
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mntToast(data.message || 'Manutenção excluída.', 'success');
                const delModal = document.getElementById('deleteMaintenanceModal');
                if (delModal) delModal.classList.remove('active');
                setTimeout(function () { mntReloadListPage(); }, 400);
            } else {
                mntToast('Erro ao excluir manutenção: ' + data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mntToast('Erro ao excluir manutenção', 'error');
        });
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
            const current = select.value;
            while (select.options.length > 1) select.remove(1);
            data.data.forEach(vehicle => {
                const option = document.createElement('option');
                option.value = vehicle.id;
                option.textContent = `${vehicle.placa} - ${vehicle.modelo}`;
                select.appendChild(option);
            });
            if (current) select.value = current;
        }
    })
    .catch(error => console.error('Erro ao carregar veículos:', error));
}

function setupFilters() {
    const clearBtn = document.getElementById('clearMaintenanceFilters');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            const base = document.body.classList.contains('manutencoes-modern') ? 'manutencoes.php' : 'manutencoes.php?classic=1';
            window.location.href = base;
        });
    }
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
        return;
    }

    try {
        isInitializingCharts = true;
        
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
        
        const periodLabel = data.period_label || 'período selecionado';

        // Initialize cost chart
        const costCtx = document.getElementById('maintenanceCostsChart');
        if (costCtx && data.costs && data.costs.labels) {
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
                            text: 'Custos de Manutenção (' + periodLabel + ')'
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
        const msg = (typeof sfSafeErrorMessage === 'function')
            ? sfSafeErrorMessage(error, 'Falha ao carregar os gráficos.')
            : (error && error.message) || 'Falha ao carregar os gráficos.';
        const errorMessage = `Erro ao carregar dados dos gráficos: ${msg}`;
        mntToast(errorMessage, 'error');
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