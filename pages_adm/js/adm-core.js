/**
 * Control Center ADM — core utilities (poll, fetch, format, skeleton)
 */
(function (global) {
    'use strict';

    function admApiBase() {
        var b = global.__ADM_API_BASE__;
        return typeof b === 'string' ? b.replace(/\/+$/, '') : '';
    }

    function admCsrf() {
        return typeof global.__ADM_CSRF__ === 'string' ? global.__ADM_CSRF__ : '';
    }

    function admFetch(path, opts) {
        opts = opts || {};
        var url = admApiBase() + '/' + String(path || '').replace(/^\//, '');
        var headers = Object.assign({ 'Accept': 'application/json' }, opts.headers || {});
        if (opts.method && opts.method !== 'GET') {
            headers['X-CSRF-Token'] = admCsrf();
        }
        return fetch(url, Object.assign({}, opts, { headers: headers, credentials: 'same-origin' }))
            .then(function (r) {
                return r.json().then(function (j) {
                    if (!r.ok) throw new Error(j.message || j.error || 'Erro na requisição');
                    return j;
                });
            });
    }

    function formatBRL(n) {
        var v = Number(n);
        if (isNaN(v)) return '—';
        return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function formatNum(n) {
        var v = Number(n);
        if (isNaN(v)) return '—';
        return v.toLocaleString('pt-BR');
    }

    function setText(id, text) {
        var el = document.getElementById(id);
        if (el) el.textContent = text;
    }

    function skeleton(on) {
        document.querySelectorAll('.adm-cc-kpi-grid, .adm-cc-dashboard').forEach(function (g) {
            g.classList.toggle('adm-cc-skeleton', !!on);
        });
    }

    function poll(path, onData, intervalMs, onError) {
        intervalMs = intervalMs || 30000;
        function tick() {
            admFetch(path).then(function (res) {
                if (res.success && res.data !== undefined) {
                    onData(res.data);
                } else if (onError) {
                    onError(new Error('Resposta inválida'));
                }
            }).catch(function (err) {
                if (onError) onError(err);
            });
        }
        tick();
        return setInterval(tick, intervalMs);
    }

    function nocEmoji(status) {
        return { ok: '🟢', degradado: '🟡', falha: '🔴', desconhecido: '⚪' }[status] || '⚪';
    }

    function renderNocGrid(container, services) {
        if (!container || !services) return;
        container.innerHTML = services.map(function (s) {
            var cls = 'adm-cc-noc adm-cc-noc--' + (s.status || 'desconhecido');
            return '<div class="' + cls + '">' +
                '<div class="adm-cc-noc__status">' + nocEmoji(s.status) + '</div>' +
                '<div class="adm-cc-noc__name">' + escapeHtml(s.servico) + '</div>' +
                '<div class="adm-cc-noc__meta">' + escapeHtml(s.detalhe || '') +
                (s.latencia_ms != null ? ' · ' + s.latencia_ms + 'ms' : '') + '</div></div>';
        }).join('');
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function renderTerminal(container, lines) {
        if (!container) return;
        if (!lines || !lines.length) {
            container.innerHTML = '<div class="adm-cc-empty"><i class="fas fa-terminal"></i><p>Nenhum evento recente.</p></div>';
            return;
        }
        container.innerHTML = lines.map(function (l) {
            var sev = l.severidade || 'info';
            return '<div class="adm-cc-terminal__line">' +
                '<span class="adm-cc-terminal__time">' + escapeHtml(l.hora || '') + '</span>' +
                '<span class="adm-cc-terminal__msg adm-cc-terminal__msg--' + sev + '">' +
                escapeHtml(l.empresa || '') + ' · ' + escapeHtml(l.mensagem || '') + '</span></div>';
        }).join('');
        container.scrollTop = container.scrollHeight;
    }

    global.AdmCC = {
        fetch: admFetch,
        poll: poll,
        formatBRL: formatBRL,
        formatNum: formatNum,
        setText: setText,
        skeleton: skeleton,
        renderNocGrid: renderNocGrid,
        renderTerminal: renderTerminal,
        escapeHtml: escapeHtml
    };
})(typeof window !== 'undefined' ? window : this);
