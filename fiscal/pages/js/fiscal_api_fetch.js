/**
 * Fetch para fiscal/api/documentos_fiscais_v2.php com CSRF (header X-CSRF-Token).
 * Defina window.FISCAL_CSRF_TOKEN na página antes de carregar este script.
 *
 * Resolve para { ok, status, data } (data sempre objeto; corpo vazio => {}).
 */
function fiscalApiFetch(url, init) {
    init = init || {};
    var method = (init.method || 'GET').toUpperCase();
    var token = (typeof window !== 'undefined' && window.FISCAL_CSRF_TOKEN) ? String(window.FISCAL_CSRF_TOKEN) : '';
    if (token && method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS') {
        if (!init.headers) init.headers = {};
        if (typeof Headers !== 'undefined' && init.headers instanceof Headers) {
            init.headers.set('X-CSRF-Token', token);
        } else {
            init.headers = Object.assign({}, init.headers);
            init.headers['X-CSRF-Token'] = token;
        }
    }
    if (init.credentials === undefined) {
        init.credentials = 'same-origin';
    }
    return fetch(url, init).then(function(response) {
        return response.text().then(function(text) {
            var data = {};
            if (text) {
                try {
                    var parsed = JSON.parse(text);
                    data = (parsed && typeof parsed === 'object') ? parsed : { _nonObject: parsed };
                } catch (e) {
                    data = { parse_error: true, raw: text };
                }
            }
            return { ok: response.ok, status: response.status, data: data };
        });
    });
}

function fiscalApiErrorMessage(data, httpStatus) {
    var st = typeof httpStatus === 'number' ? httpStatus : 0;
    if (st === 429 || (data && String(data.code || '') === 'rate_limited')) {
        if (data && typeof data.error === 'string' && data.error.trim()) return data.error;
        if (data && typeof data.message === 'string' && data.message.trim()) return data.message;
        return 'Muitas requisições em pouco tempo. Aguarde alguns minutos e tente novamente.';
    }
    if (!data || typeof data !== 'object') return 'Não foi possível interpretar a resposta do servidor.';

    var head = (typeof data.error === 'string' && data.error.trim()) ? data.error.trim() : '';
    if (Array.isArray(data.detalhes) && data.detalhes.length > 0) {
        var bodyD = data.detalhes.map(function (x) { return String(x); }).join('\n');
        return head ? (head + '\n\n' + bodyD) : bodyD;
    }
    if (data.erros && Array.isArray(data.erros) && data.erros.length > 0 && typeof data.erros[0] === 'object') {
        var lines = data.erros.map(function (e) {
            if (!e || typeof e !== 'object') return String(e);
            var m = e.mensagem != null ? String(e.mensagem) : '';
            var id = e.id != null ? String(e.id) : (e.codigo != null ? String(e.codigo) : '');
            if (id && m) return id + ': ' + m;
            return m || id || '';
        }).filter(function (s) { return s.length > 0; });
        if (lines.length > 0) {
            var bodyE = lines.join('\n');
            return head ? (head + '\n\n' + bodyE) : bodyE;
        }
    }

    if (data.message && typeof data.message === 'string') return data.message;
    if (typeof data.error === 'string') return data.error;
    if (data.erros && Array.isArray(data.erros)) return data.erros.join(' ');
    return 'Erro desconhecido.';
}

/** Container fixo para toasts (criado sob demanda). */
function fiscalEnsureToastContainer() {
    var id = 'fiscalToastContainer';
    var el = document.getElementById(id);
    if (el) return el;
    el = document.createElement('div');
    el.id = id;
    el.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    el.style.zIndex = '11000';
    el.setAttribute('aria-live', 'polite');
    document.body.appendChild(el);
    return el;
}

/**
 * Toast Bootstrap (sucesso, erro, aviso, info).
 * @param {string} message
 * @param {'success'|'danger'|'warning'|'info'} variant
 */
function fiscalToast(message, variant) {
    variant = variant || 'info';
    var msg = String(message == null ? '' : message);
    if (typeof bootstrap === 'undefined' || !bootstrap.Toast) {
        console.warn('[' + variant + ']', msg);
        return;
    }
    var bgMap = { success: 'success', danger: 'danger', warning: 'warning', info: 'primary' };
    var bg = bgMap[variant] || 'primary';
    var closeClass = variant === 'warning' ? 'btn-close' : 'btn-close btn-close-white';
    var host = fiscalEnsureToastContainer();
    var el = document.createElement('div');
    el.setAttribute('role', variant === 'danger' ? 'alert' : 'status');
    el.className = 'toast align-items-center text-bg-' + bg + ' border-0';
    el.innerHTML = '<div class="d-flex"><div class="toast-body" style="white-space:pre-wrap;max-height:70vh;overflow:auto;font-size:0.9rem"></div><button type="button" class="' + closeClass + ' me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button></div>';
    el.querySelector('.toast-body').textContent = msg;
    host.appendChild(el);
    var delay = Math.min(20000, 4000 + Math.min(msg.length * 20, 14000));
    var t = new bootstrap.Toast(el, { delay: delay, autohide: true });
    el.addEventListener('hidden.bs.toast', function() { el.remove(); });
    t.show();
}

/**
 * Modal de confirmação (substitui window.confirm quando Bootstrap está disponível).
 * @returns {Promise<boolean>}
 */
function fiscalConfirmAsync(title, message) {
    return new Promise(function(resolve) {
        if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            resolve(window.confirm((title ? title + '\n\n' : '') + (message || '')));
            return;
        }
        var modalId = 'fiscalGenericConfirmModal';
        var el = document.getElementById(modalId);
        if (!el) {
            el = document.createElement('div');
            el.className = 'modal fade';
            el.id = modalId;
            el.setAttribute('tabindex', '-1');
            el.setAttribute('aria-labelledby', 'fiscalGenericConfirmTitle');
            el.setAttribute('aria-modal', 'true');
            el.innerHTML = '<div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="fiscalGenericConfirmTitle"></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body" id="fiscalGenericConfirmBody" style="white-space:pre-wrap"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="fiscalGenericConfirmNao">Cancelar</button><button type="button" class="btn btn-primary" id="fiscalGenericConfirmSim">Confirmar</button></div></div></div>';
            document.body.appendChild(el);
        }
        var titleEl = document.getElementById('fiscalGenericConfirmTitle');
        var bodyEl = document.getElementById('fiscalGenericConfirmBody');
        var btnSim = document.getElementById('fiscalGenericConfirmSim');
        if (titleEl) titleEl.textContent = title || 'Confirmar';
        if (bodyEl) bodyEl.textContent = message || '';
        var modal = bootstrap.Modal.getOrCreateInstance(el);
        var finished = false;

        if (el._fiscalConfirmOnSim && btnSim) btnSim.removeEventListener('click', el._fiscalConfirmOnSim);
        if (el._fiscalConfirmOnHidden) el.removeEventListener('hidden.bs.modal', el._fiscalConfirmOnHidden);

        function onSim() {
            if (finished) return;
            finished = true;
            el.removeEventListener('hidden.bs.modal', onHidden);
            if (btnSim) btnSim.removeEventListener('click', onSim);
            delete el._fiscalConfirmOnSim;
            delete el._fiscalConfirmOnHidden;
            modal.hide();
            resolve(true);
        }
        function onHidden() {
            if (finished) return;
            finished = true;
            if (btnSim) btnSim.removeEventListener('click', onSim);
            el.removeEventListener('hidden.bs.modal', onHidden);
            delete el._fiscalConfirmOnSim;
            delete el._fiscalConfirmOnHidden;
            resolve(false);
        }
        el._fiscalConfirmOnSim = onSim;
        el._fiscalConfirmOnHidden = onHidden;
        if (btnSim) btnSim.addEventListener('click', onSim);
        el.addEventListener('hidden.bs.modal', onHidden);
        modal.show();
    });
}

/**
 * Modal com textarea (substitui window.prompt quando Bootstrap está disponível).
 * @param {string} title
 * @param {string} message Texto de ajuda (opcional)
 * @param {{ placeholder?: string, minLength?: number, rows?: number, value?: string }} [opts]
 * @returns {Promise<string|null>} Texto trimado, ou null se cancelado / vazio inválido
 */
function fiscalPromptAsync(title, message, opts) {
    opts = opts || {};
    var placeholder = opts.placeholder || '';
    var minLen = typeof opts.minLength === 'number' ? opts.minLength : 0;
    var rows = typeof opts.rows === 'number' ? opts.rows : 4;
    var initial = opts.value != null ? String(opts.value) : '';

    return new Promise(function(resolve) {
        if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            var p = window.prompt((title ? title + '\n\n' : '') + (message || ''), initial);
            resolve(p == null ? null : String(p).trim());
            return;
        }

        var modalId = 'fiscalGenericPromptModal';
        var el = document.getElementById(modalId);
        if (!el) {
            el = document.createElement('div');
            el.className = 'modal fade';
            el.id = modalId;
            el.setAttribute('tabindex', '-1');
            el.setAttribute('aria-labelledby', 'fiscalGenericPromptTitle');
            el.setAttribute('aria-modal', 'true');
            el.innerHTML =
                '<div class="modal-dialog modal-dialog-centered">' +
                '<div class="modal-content">' +
                '<div class="modal-header"><h5 class="modal-title" id="fiscalGenericPromptTitle"></h5>' +
                '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>' +
                '<div class="modal-body">' +
                '<p class="text-muted small mb-2" id="fiscalGenericPromptHelp" style="white-space:pre-wrap"></p>' +
                '<label for="fiscalGenericPromptInput" class="visually-hidden">Texto</label>' +
                '<textarea class="form-control" id="fiscalGenericPromptInput" rows="4" autocomplete="off"></textarea>' +
                '<div class="invalid-feedback d-block" id="fiscalGenericPromptErr" style="min-height:1.25rem"></div>' +
                '</div>' +
                '<div class="modal-footer">' +
                '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="fiscalGenericPromptCancel">Cancelar</button>' +
                '<button type="button" class="btn btn-primary" id="fiscalGenericPromptOk">Confirmar</button>' +
                '</div></div></div>';
            document.body.appendChild(el);
        }

        var titleEl = document.getElementById('fiscalGenericPromptTitle');
        var helpEl = document.getElementById('fiscalGenericPromptHelp');
        var ta = document.getElementById('fiscalGenericPromptInput');
        var errEl = document.getElementById('fiscalGenericPromptErr');
        var btnOk = document.getElementById('fiscalGenericPromptOk');

        if (titleEl) titleEl.textContent = title || 'Informar';
        if (helpEl) {
            helpEl.textContent = message || '';
            helpEl.style.display = message ? 'block' : 'none';
        }
        if (ta) {
            ta.value = initial;
            ta.placeholder = placeholder;
            ta.rows = rows;
        }
        if (errEl) errEl.textContent = '';

        var modal = bootstrap.Modal.getOrCreateInstance(el);
        var finished = false;

        if (el._fiscalPromptOnOk && btnOk) btnOk.removeEventListener('click', el._fiscalPromptOnOk);
        if (el._fiscalPromptOnHidden) el.removeEventListener('hidden.bs.modal', el._fiscalPromptOnHidden);

        function clearErr() {
            if (errEl) errEl.textContent = '';
            if (ta) ta.classList.remove('is-invalid');
        }

        function onOk() {
            if (finished || !ta) return;
            var v = String(ta.value || '').trim();
            if (minLen > 0 && v.length < minLen) {
                if (errEl) errEl.textContent = 'Informe pelo menos ' + minLen + ' caracteres (exigência usual da SEFAZ).';
                ta.classList.add('is-invalid');
                return;
            }
            if (v.length === 0) {
                if (errEl) errEl.textContent = 'Este campo é obrigatório.';
                ta.classList.add('is-invalid');
                return;
            }
            finished = true;
            el.removeEventListener('hidden.bs.modal', onHidden);
            if (btnOk) btnOk.removeEventListener('click', onOk);
            delete el._fiscalPromptOnOk;
            delete el._fiscalPromptOnHidden;
            clearErr();
            modal.hide();
            resolve(v);
        }

        function onHidden() {
            if (finished) return;
            finished = true;
            if (btnOk) btnOk.removeEventListener('click', onOk);
            el.removeEventListener('hidden.bs.modal', onHidden);
            delete el._fiscalPromptOnOk;
            delete el._fiscalPromptOnHidden;
            resolve(null);
        }

        el._fiscalPromptOnOk = onOk;
        el._fiscalPromptOnHidden = onHidden;
        if (btnOk) btnOk.addEventListener('click', onOk);
        el.addEventListener('hidden.bs.modal', onHidden);
        el.addEventListener('shown.bs.modal', function onShown() {
            el.removeEventListener('shown.bs.modal', onShown);
            if (ta) {
                ta.focus();
                try { ta.setSelectionRange(ta.value.length, ta.value.length); } catch (e) {}
            }
        }, { once: true });
        modal.show();
    });
}

if (typeof window !== 'undefined') {
    window.fiscalToast = fiscalToast;
    window.fiscalConfirmAsync = fiscalConfirmAsync;
    window.fiscalPromptAsync = fiscalPromptAsync;
}
