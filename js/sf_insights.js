/**
 * Widget reutilizável de Insights + Resumo por IA.
 * Carregamento lazy (expandir) + cache sessionStorage para não travar o paint da página.
 */
(function (g) {
    'use strict';

    var DEFAULT_CACHE_TTL_MS = 5 * 60 * 1000;

    function esc(v) {
        if (v === null || v === undefined) return '';
        return String(v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function cacheKey(url) {
        return 'sf_insights_v1_' + String(url || '').replace(/[^a-z0-9]/gi, '_').slice(0, 120);
    }

    function readCache(url) {
        try {
            var raw = sessionStorage.getItem(cacheKey(url));
            if (!raw) return null;
            var o = JSON.parse(raw);
            if (!o || !o.ts || !o.data) return null;
            if (Date.now() - o.ts > (o.ttl || DEFAULT_CACHE_TTL_MS)) {
                sessionStorage.removeItem(cacheKey(url));
                return null;
            }
            return o.data;
        } catch (e) {
            return null;
        }
    }

    function writeCache(url, data, ttlMs) {
        try {
            sessionStorage.setItem(cacheKey(url), JSON.stringify({
                ts: Date.now(),
                ttl: ttlMs || DEFAULT_CACHE_TTL_MS,
                data: data
            }));
        } catch (e) { /* quota */ }
    }

    function scheduleIdle(fn, timeoutMs) {
        timeoutMs = timeoutMs || 2500;
        if (typeof g.requestIdleCallback === 'function') {
            var done = false;
            var t = setTimeout(function () {
                if (!done) { done = true; fn(); }
            }, timeoutMs);
            g.requestIdleCallback(function () {
                if (!done) { done = true; clearTimeout(t); fn(); }
            }, { timeout: timeoutMs });
            return;
        }
        setTimeout(fn, Math.min(timeoutMs, 1500));
    }

    function injectCss() {
        if (document.getElementById('sfInsightsCss')) return;
        var css = '' +
            '.fn-insights{border:1px solid var(--border-color);border-radius:10px;margin-bottom:1rem;overflow:hidden;background:var(--card-bg,var(--bg-secondary))}' +
            '.fn-insights.has-erros{border-color:#dc2626}.fn-insights.has-avisos{border-color:#f59e0b}' +
            '.fn-head{display:flex;align-items:center;gap:.5rem;padding:.5rem .6rem .5rem .85rem}' +
            '.fn-toggle{flex:1;display:flex;align-items:center;justify-content:space-between;gap:.5rem;background:none;border:none;cursor:pointer;color:var(--text-primary);font-weight:600;font-size:.9rem;text-align:left;padding:.2rem 0}' +
            '.fn-caret{transition:transform .2s ease;color:var(--text-muted)}' +
            '.fn-toggle[aria-expanded="true"] .fn-caret{transform:rotate(180deg)}' +
            '.fn-summary .fn-pill{display:inline-block;padding:1px 8px;border-radius:999px;font-size:.78rem;font-weight:700;margin-left:6px}' +
            '.fn-pill.erro{background:rgba(220,38,38,.12);color:#dc2626}' +
            '.fn-pill.aviso{background:rgba(245,158,11,.14);color:#b45309}' +
            '.fn-pill.ok{background:rgba(22,163,74,.12);color:#16a34a}' +
            '.fn-body{padding:.25rem .85rem .7rem;max-height:420px;overflow:auto}' +
            '.fn-section{padding:.5rem 0;border-top:1px solid var(--border-color)}' +
            '.fn-section:first-child{border-top:none}' +
            '.fn-section h4{margin:0 0 .4rem;font-size:.85rem;display:flex;align-items:center;gap:.4rem;color:var(--text-primary)}' +
            '.fn-section h4 i{color:var(--accent-primary,#3b82f6)}' +
            '.fn-item{display:flex;gap:.55rem;padding:.4rem 0;border-top:1px dashed var(--border-color);font-size:.85rem;align-items:flex-start}' +
            '.fn-item:first-child{border-top:none}' +
            '.fn-item .fn-sev{flex:0 0 auto;margin-top:.1rem}' +
            '.fn-item .fn-sev.erro{color:#dc2626}.fn-item .fn-sev.aviso{color:#f59e0b}' +
            '.fn-item .fn-txt{flex:1;min-width:0}.fn-item .fn-txt strong{color:var(--text-primary)}' +
            '.fn-item .fn-edit{flex:0 0 auto}' +
            '.fn-empty{font-size:.83rem;color:var(--text-muted);padding:.2rem 0}' +
            '.fn-more{margin-top:.4rem;font-size:.82rem;color:var(--text-muted)}' +
            '.fn-resumo-ia{border:1px solid var(--border-color);border-radius:10px;margin-bottom:1rem;padding:.75rem .85rem;background:var(--card-bg,var(--bg-secondary))}' +
            '.fn-resumo-head{display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap;margin-bottom:.4rem}' +
            '.fn-resumo-head h4{margin:0;font-size:.95rem;display:flex;align-items:center;gap:.4rem;color:var(--text-primary)}' +
            '.fn-resumo-head h4 i{color:var(--accent-primary,#3b82f6)}' +
            '.fn-resumo-texto{font-size:.9rem;line-height:1.5;color:var(--text-primary);white-space:pre-wrap}' +
            '.fn-resumo-texto.text-muted{color:var(--text-muted)}' +
            '.fn-head .fornc-btn.fornc-btn--ghost.fornc-btn--icon{background:none;border:none;color:var(--text-muted);cursor:pointer;padding:4px 8px;border-radius:6px;min-width:auto}' +
            '.fn-head .fornc-btn.fornc-btn--ghost.fornc-btn--icon:hover{background:var(--bg-tertiary)}' +
            '.fn-resumo-head .fornc-btn.fornc-btn--ghost{border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-primary);border-radius:8px;padding:5px 12px;font-size:.82rem;cursor:pointer;font-weight:600;display:inline-flex;align-items:center;gap:.35rem}' +
            '.fn-resumo-head .fornc-btn.fornc-btn--ghost:hover{background:var(--bg-tertiary)}' +
            '.fn-resumo-head .fornc-btn.fornc-btn--ghost:disabled{opacity:.6;cursor:default}';
        var el = document.createElement('style');
        el.id = 'sfInsightsCss';
        el.textContent = css;
        document.head.appendChild(el);
    }

    function placeholderHtml(title, iconClass) {
        iconClass = iconClass || 'fa-clipboard-check';
        return '<i class="fas ' + iconClass + '"></i> ' + esc(title) +
            ' — <span style="font-weight:500;color:var(--text-muted)">expandir para analisar</span>';
    }

    function summaryHtml(title, json, pillBuilder) {
        var er = json.q_erros || json.erros || 0;
        var av = json.q_avisos || json.avisos || 0;
        var tot = er + av;
        if (typeof pillBuilder === 'function') {
            return pillBuilder(title, json, er, av, tot);
        }
        var color = er > 0 ? '#dc2626' : (av > 0 ? '#f59e0b' : '#16a34a');
        var pills = (tot > 0)
            ? '<span class="fn-pill ' + (er > 0 ? 'erro' : 'aviso') + '">' + tot + ' pendência(s)</span>'
            : '<span class="fn-pill ok">tudo certo</span>';
        return '<i class="fas fa-clipboard-check" style="color:' + color + '"></i> ' + esc(title) + pills;
    }

    function init(opts) {
        opts = opts || {};
        var mount = document.getElementById(opts.mountId);
        if (!mount) return;
        injectCss();

        var loadOnExpand = opts.loadOnExpand !== false;
        var cacheTtlMs = opts.cacheTtlMs || DEFAULT_CACHE_TTL_MS;
        var deferIdleMs = opts.deferIdleMs != null ? opts.deferIdleMs : 0;
        var eagerIfCached = opts.eagerIfCached === true;

        var pid = opts.mountId;
        mount.innerHTML =
            '<div class="fn-insights" id="' + pid + '_panel">' +
                '<div class="fn-head">' +
                    '<button type="button" class="fn-toggle" id="' + pid + '_toggle" aria-expanded="false" aria-controls="' + pid + '_body">' +
                        '<span class="fn-summary" id="' + pid + '_summary">' + placeholderHtml(opts.title || 'Insights') + '</span>' +
                        '<i class="fas fa-chevron-down fn-caret" aria-hidden="true"></i>' +
                    '</button>' +
                    '<button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="' + pid + '_refresh" title="Reanalisar" aria-label="Reanalisar"><i class="fas fa-sync-alt"></i></button>' +
                '</div>' +
                '<div class="fn-body" id="' + pid + '_body" style="display:none;">' +
                    '<div class="fn-section">' +
                        '<h4><i class="fas fa-clipboard-check"></i> ' + esc(opts.sectionTitle || 'Pendências de cadastro') + '</h4>' +
                        '<div id="' + pid + '_list"><div class="fn-empty">Expanda o painel para carregar a análise.</div></div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="fn-resumo-ia" id="' + pid + '_resumo" style="display:none">' +
                '<div class="fn-resumo-head">' +
                    '<h4><i class="fas fa-wand-magic-sparkles"></i> Resumo executivo (IA)</h4>' +
                    '<button type="button" class="fornc-btn fornc-btn--ghost" id="' + pid + '_iaBtn"><i class="fas fa-bolt"></i> Gerar resumo</button>' +
                '</div>' +
                '<div id="' + pid + '_iaText" class="fn-resumo-texto text-muted">Clique em “Gerar resumo” para uma análise pela IA.</div>' +
            '</div>';

        var panel = document.getElementById(pid + '_panel');
        var toggle = document.getElementById(pid + '_toggle');
        var body = document.getElementById(pid + '_body');
        var summary = document.getElementById(pid + '_summary');
        var listEl = document.getElementById(pid + '_list');
        var refreshBtn = document.getElementById(pid + '_refresh');
        var resumoBlock = document.getElementById(pid + '_resumo');
        var iaBtn = document.getElementById(pid + '_iaBtn');
        var iaText = document.getElementById(pid + '_iaText');
        var lastData = null;
        var loaded = false;
        var loading = false;
        var maxItems = opts.maxItems || 20;
        var insightsUrl = opts.insightsUrl || '';

        function render(json) {
            lastData = json;
            loaded = true;
            panel.style.display = '';
            if (resumoBlock) resumoBlock.style.display = '';

            var er = json.q_erros || 0;
            var av = json.q_avisos || 0;
            var tot = er + av;
            panel.classList.remove('has-erros', 'has-avisos');
            if (er > 0) panel.classList.add('has-erros');
            else if (av > 0) panel.classList.add('has-avisos');

            var color = er > 0 ? '#dc2626' : (av > 0 ? '#f59e0b' : '#16a34a');
            var pills = (tot > 0)
                ? '<span class="fn-pill ' + (er > 0 ? 'erro' : 'aviso') + '">' + tot + ' pendência(s)</span>'
                : '<span class="fn-pill ok">tudo certo</span>';

            var stats = (typeof opts.stats === 'function') ? (opts.stats(json) || []) : [];
            stats.forEach(function (c) {
                pills += '<span class="fn-pill ' + esc(c.tone || 'ok') + '">' + esc(c.value) + ' ' + esc(c.label) + '</span>';
            });

            summary.innerHTML = '<i class="fas fa-clipboard-check" style="color:' + color + '"></i> ' +
                esc(opts.title || 'Insights') + pills;

            var qual = json.qualidade || [];
            var shown = qual.slice(0, maxItems);
            listEl.innerHTML = shown.length ? shown.map(function (q) {
                var icon = q.severidade === 'erro' ? 'fa-circle-xmark' : 'fa-triangle-exclamation';
                var reasons = (q.motivos || []).map(function (m) { return esc(m.msg); }).join(' · ');
                return '<div class="fn-item"><span class="fn-sev ' + esc(q.severidade) + '"><i class="fas ' + icon + '"></i></span>' +
                    '<div class="fn-txt"><strong>' + esc(q.nome) + '</strong>' +
                    (reasons ? '<br><span style="color:var(--text-muted)">' + reasons + '</span>' : '') +
                    '</div>' +
                    (opts.onEdit ? '<button type="button" class="btn-icon fn-edit" data-sfi-edit="' + esc(String(q.id)) + '" title="Abrir cadastro"><i class="fas fa-edit"></i></button>' : '') +
                    '</div>';
            }).join('') + (qual.length > maxItems ? '<div class="fn-more">+ ' + (qual.length - maxItems) + ' outro(s) item(ns) não exibido(s).</div>' : '')
              : '<div class="fn-empty">' + esc(opts.emptyOk || 'Nenhuma pendência encontrada.') + '</div>';

            if (typeof opts.onEdit === 'function') {
                listEl.querySelectorAll('[data-sfi-edit]').forEach(function (b) {
                    b.addEventListener('click', function () { opts.onEdit(this.getAttribute('data-sfi-edit')); });
                });
            }
        }

        function load(forceRefresh) {
            if (loading) return;
            if (!insightsUrl) return;

            var url = insightsUrl;
            if (forceRefresh) {
                url += (url.indexOf('?') >= 0 ? '&' : '?') + 'refresh=1&_=' + Date.now();
            }

            if (!forceRefresh) {
                var cached = readCache(insightsUrl);
                if (cached && cached.success) {
                    render(cached);
                    return;
                }
            }

            loading = true;
            summary.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analisando…';
            listEl.innerHTML = '<div class="fn-empty">Carregando…</div>';

            fetch(url, { credentials: 'include' })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    if (json && json.success) {
                        writeCache(insightsUrl, json, cacheTtlMs);
                        render(json);
                    } else {
                        mount.innerHTML = '';
                    }
                })
                .catch(function () {
                    if (!loaded) {
                        summary.innerHTML = '<i class="fas fa-clipboard-check"></i> ' + esc(opts.title || 'Insights') +
                            ' <span class="fn-pill aviso">falha ao carregar</span>';
                    }
                })
                .then(function () { loading = false; });
        }

        toggle.addEventListener('click', function () {
            var open = toggle.getAttribute('aria-expanded') === 'true';
            var willOpen = !open;
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            body.style.display = willOpen ? '' : 'none';
            if (willOpen && (!loaded || loadOnExpand)) {
                load(false);
            }
        });

        refreshBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            loaded = false;
            try { sessionStorage.removeItem(cacheKey(insightsUrl)); } catch (err) {}
            load(true);
        });

        function gerarIA() {
            if (!lastData) return;
            iaBtn.disabled = true;
            var oldHtml = iaBtn.innerHTML;
            iaBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando…';
            iaText.classList.add('text-muted');
            iaText.textContent = 'A IA está analisando os dados…';
            var headers = (typeof sfMutationHeaders === 'function') ? sfMutationHeaders({ 'Content-Type': 'application/json' }) : { 'Content-Type': 'application/json' };
            if (typeof g.__SF_CSRF__ === 'string' && g.__SF_CSRF__ && !headers['X-CSRF-Token']) headers['X-CSRF-Token'] = g.__SF_CSRF__;
            fetch(opts.resumoUrl, { method: 'POST', credentials: 'include', headers: headers, body: JSON.stringify({ dados: lastData }) })
                .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
                .then(function (o) {
                    var d = o.d || {};
                    if (o.ok && d.success) {
                        iaText.classList.remove('text-muted');
                        iaText.textContent = d.resumo;
                    } else {
                        iaText.classList.add('text-muted');
                        iaText.textContent = d.message || d.error || 'Não foi possível gerar o resumo.';
                    }
                })
                .catch(function () {
                    iaText.classList.add('text-muted');
                    iaText.textContent = 'Erro de comunicação ao gerar o resumo.';
                })
                .then(function () {
                    iaBtn.disabled = false;
                    iaBtn.innerHTML = oldHtml;
                });
        }

        iaBtn.addEventListener('click', gerarIA);

        if (eagerIfCached) {
            var hit = readCache(insightsUrl);
            if (hit && hit.success) {
                render(hit);
                return;
            }
        }

        if (!loadOnExpand) {
            if (deferIdleMs > 0) {
                scheduleIdle(function () { load(false); }, deferIdleMs);
            } else {
                load(false);
            }
        }
    }

    g.SFInsights = {
        init: init,
        placeholderHtml: placeholderHtml,
        summaryHtml: summaryHtml,
        readCache: readCache,
        writeCache: writeCache,
        removeCache: function (url) {
            try { sessionStorage.removeItem(cacheKey(url)); } catch (e) {}
        },
        cacheKey: cacheKey,
        CACHE_TTL_MS: DEFAULT_CACHE_TTL_MS
    };
})(typeof window !== 'undefined' ? window : this);
