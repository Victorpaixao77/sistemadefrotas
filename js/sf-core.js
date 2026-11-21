/**
 * Utilitários compartilhados do front (debounce, fetch com timeout).
 * Depende de sfApiUrl / sfMutationHeaders / sfSafeErrorMessage em sf_api_base.php.
 */
(function (g) {
    'use strict';

    if (g.sfDebounce) return;

    g.sfDebounce = function sfDebounce(fn, wait) {
        var t;
        wait = wait || 300;
        return function () {
            var ctx = this, args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(ctx, args); }, wait);
        };
    };

    g.sfThrottle = function sfThrottle(fn, wait) {
        var last = 0, timer;
        wait = wait || 300;
        return function () {
            var ctx = this, args = arguments, now = Date.now();
            if (now - last >= wait) {
                last = now;
                fn.apply(ctx, args);
            } else {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    last = Date.now();
                    fn.apply(ctx, args);
                }, wait - (now - last));
            }
        };
    };

    /**
     * fetch com timeout e credentials para API interna.
     * @param {string} url
     * @param {RequestInit} [opts]
     * @param {number} [timeoutMs]
     */
    g.sfFetchJson = function sfFetchJson(url, opts, timeoutMs) {
        opts = opts || {};
        timeoutMs = timeoutMs || 30000;
        var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timer = ctrl ? setTimeout(function () { ctrl.abort(); }, timeoutMs) : null;
        if (ctrl) opts.signal = ctrl.signal;
        if (typeof opts.credentials === 'undefined') opts.credentials = 'include';
        return g.fetch(url, opts).then(function (r) {
            return r.text().then(function (text) {
                var data;
                try { data = text ? JSON.parse(text) : null; }
                catch (e) { throw new Error('Resposta inválida do servidor.'); }
                if (!r.ok && !(data && data.success)) {
                    throw new Error((data && (data.error || data.message)) || ('HTTP ' + r.status));
                }
                return data;
            });
        }).finally(function () { if (timer) clearTimeout(timer); });
    };
})(typeof window !== 'undefined' ? window : globalThis);
