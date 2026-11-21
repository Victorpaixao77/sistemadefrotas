/**
 * SFPeriodo — filtro de período padronizado (botão "Período" + modal único),
 * no mesmo padrão da página de Rotas, reutilizável em qualquer tela.
 *
 * Uso (HTML):
 *   <!-- guarda os valores e submete junto com o form de filtros -->
 *   <input type="hidden" name="data_inicio" data-sf-periodo-from value="...">
 *   <input type="hidden" name="data_fim"    data-sf-periodo-to    value="...">
 *   <button type="button" class="fornc-btn fornc-btn--ghost" data-sf-periodo>
 *       <i class="fas fa-calendar-alt"></i> Período
 *   </button>
 *   <script src="../js/sf-periodo.js"></script>
 *
 * O botão é ligado automaticamente ao DOM carregar. Ao Aplicar, grava os hidden
 * e submete o form do botão (ou de qualquer um dos hidden). Ao Limpar, zera e submete.
 * Para telas com filtro por JS (sem form), use data-sf-periodo-onapply="nomeGlobalFn".
 */
(function (g) {
    'use strict';
    var MODAL_ID = 'sfPeriodoModal';
    var current = null;

    function injectCss() {
        if (document.getElementById('sfPeriodoCss')) return;
        var s = document.createElement('style');
        s.id = 'sfPeriodoCss';
        s.textContent =
            '.sf-periodo-modal{position:fixed;inset:0;background:rgba(2,6,23,.55);backdrop-filter:blur(2px);display:none;align-items:center;justify-content:center;z-index:99999}' +
            '.sf-periodo-modal.open{display:flex}' +
            '.sf-periodo-box{width:min(420px,calc(100% - 32px));background:var(--card-bg,var(--bg-secondary,#fff));color:var(--text-primary,#0f172a);border:1px solid var(--border-color,#e2e8f0);border-radius:14px;box-shadow:0 20px 60px -20px rgba(0,0,0,.6);overflow:hidden;font-family:inherit}' +
            '.sf-periodo-head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border-color,#e2e8f0)}' +
            '.sf-periodo-head h3{margin:0;font-size:1rem;font-weight:700}' +
            '.sf-periodo-x{background:none;border:0;font-size:1.4rem;line-height:1;color:var(--text-muted,#94a3b8);cursor:pointer}' +
            '.sf-periodo-body{padding:16px 18px;display:flex;flex-direction:column;gap:12px}' +
            '.sf-periodo-body label{display:flex;flex-direction:column;gap:5px;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;font-weight:700;color:var(--text-muted,#64748b)}' +
            '.sf-periodo-body input{padding:9px 11px;font-size:.9rem;border:1px solid var(--border-color,#e2e8f0);border-radius:8px;background:var(--bg-tertiary,var(--bg-secondary,#fff));color:var(--text-primary,#0f172a)}' +
            '.sf-periodo-foot{display:flex;justify-content:flex-end;gap:8px;padding:12px 18px;border-top:1px solid var(--border-color,#e2e8f0)}' +
            '.sf-periodo-foot button{padding:8px 14px;border-radius:8px;font-weight:600;font-size:.85rem;cursor:pointer;border:1px solid var(--border-color,#e2e8f0)}' +
            '.sf-periodo-limpar{background:transparent;color:var(--text-secondary,#475569)}' +
            '.sf-periodo-aplicar{background:var(--accent-primary,#3b82f6);border-color:var(--accent-primary,#3b82f6);color:#fff}' +
            '.fornc-btn.is-active{border-color:var(--accent-primary,#3b82f6);color:var(--accent-primary,#3b82f6);background:color-mix(in srgb,var(--accent-primary,#3b82f6) 10%,transparent)}';
        document.head.appendChild(s);
    }

    function ensureModal() {
        var m = document.getElementById(MODAL_ID);
        if (m) return m;
        injectCss();
        m = document.createElement('div');
        m.id = MODAL_ID;
        m.className = 'sf-periodo-modal';
        m.innerHTML =
            '<div class="sf-periodo-box">' +
                '<div class="sf-periodo-head"><h3>Filtrar por Período</h3><button type="button" class="sf-periodo-x" aria-label="Fechar">&times;</button></div>' +
                '<div class="sf-periodo-body">' +
                    '<label class="sf-periodo-l-mes">Mês/Ano<input type="month" class="sf-periodo-mes"></label>' +
                    '<label class="sf-periodo-l-de">Data início<input type="date" class="sf-periodo-de"></label>' +
                    '<label class="sf-periodo-l-ate">Data fim<input type="date" class="sf-periodo-ate"></label>' +
                '</div>' +
                '<div class="sf-periodo-foot"><button type="button" class="sf-periodo-limpar">Limpar filtro</button><button type="button" class="sf-periodo-aplicar">Aplicar</button></div>' +
            '</div>';
        document.body.appendChild(m);
        m.addEventListener('click', function (e) {
            if (e.target === m || e.target.classList.contains('sf-periodo-x')) close();
        });
        m.querySelector('.sf-periodo-aplicar').addEventListener('click', apply);
        m.querySelector('.sf-periodo-limpar').addEventListener('click', clear);
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
        return m;
    }

    function open(cfg) {
        current = cfg || {};
        var m = ensureModal();
        var hasMes = !!cfg.month, hasRange = !!(cfg.from || cfg.to);
        m.querySelector('.sf-periodo-l-mes').style.display = hasMes ? '' : 'none';
        m.querySelector('.sf-periodo-l-de').style.display = hasRange ? '' : 'none';
        m.querySelector('.sf-periodo-l-ate').style.display = hasRange ? '' : 'none';
        m.querySelector('.sf-periodo-mes').value = (cfg.month && cfg.month.value) || '';
        m.querySelector('.sf-periodo-de').value = (cfg.from && cfg.from.value) || '';
        m.querySelector('.sf-periodo-ate').value = (cfg.to && cfg.to.value) || '';
        m.classList.add('open');
        setTimeout(function () { m.querySelector(hasMes ? '.sf-periodo-mes' : '.sf-periodo-de').focus(); }, 40);
    }
    function close() { var m = document.getElementById(MODAL_ID); if (m) m.classList.remove('open'); }

    function apply() {
        if (!current) return;
        var m = document.getElementById(MODAL_ID);
        var mes = m.querySelector('.sf-periodo-mes').value;
        var de = m.querySelector('.sf-periodo-de').value;
        var ate = m.querySelector('.sf-periodo-ate').value;
        if (current.month) current.month.value = mes;
        if (current.from) current.from.value = de;
        if (current.to) current.to.value = ate;
        close();
        if (typeof current.onApply === 'function') current.onApply({ mes: mes, de: de, ate: ate });
        else if (current.form) current.form.submit();
    }
    function clear() {
        if (!current) return;
        if (current.month) current.month.value = '';
        if (current.from) current.from.value = '';
        if (current.to) current.to.value = '';
        var m = document.getElementById(MODAL_ID);
        m.querySelector('.sf-periodo-mes').value = '';
        m.querySelector('.sf-periodo-de').value = '';
        m.querySelector('.sf-periodo-ate').value = '';
        close();
        if (typeof current.onApply === 'function') current.onApply({ mes: '', de: '', ate: '' });
        else if (current.form) current.form.submit();
    }

    function resolveFn(name) {
        if (!name) return null;
        try { return name.split('.').reduce(function (o, k) { return o && o[k]; }, g); } catch (e) { return null; }
    }

    function autoWire() {
        ensureModal();
        document.querySelectorAll('[data-sf-periodo]').forEach(function (btn) {
            if (btn._sfp) return; btn._sfp = true;
            var scope = btn.closest('form') || document;
            var q = function (sel) { return scope.querySelector(sel) || document.querySelector(sel); };
            var from = q('[data-sf-periodo-from]');
            var to = q('[data-sf-periodo-to]');
            var month = q('[data-sf-periodo-month]');
            var form = btn.closest('form') || (from && from.closest('form')) || (month && month.closest('form')) || null;
            var onApply = resolveFn(btn.getAttribute('data-sf-periodo-onapply'));
            var markActive = function () {
                var on = (from && from.value) || (to && to.value) || (month && month.value);
                btn.classList.toggle('is-active', !!on);
            };
            btn.addEventListener('click', function () {
                open({ from: from, to: to, month: month, form: form, onApply: onApply });
            });
            markActive();
        });
    }

    // Helper padrão para telas mês/ano que recarregam com ?mes=MM&ano=YYYY
    // (preserva ?classic=1). Use em data-sf-periodo-onapply="sfPeriodoNavMesAno".
    g.sfPeriodoNavMesAno = function (p) {
        var v = (p && p.mes) || '';
        var keepClassic = /[?&]classic=1/.test(location.search);
        if (v) {
            var pr = v.split('-');
            location.href = '?mes=' + pr[1] + '&ano=' + pr[0] + (keepClassic ? '&classic=1' : '');
        } else {
            location.href = keepClassic ? (location.pathname + '?classic=1') : location.pathname;
        }
    };

    // Helper para telas que filtram via AJAX no modal legado (#filterMonth +
    // #applyFilterBtn/#clearFilterBtn). Escreve o mês e dispara o botão existente.
    g.sfPeriodoClickApply = function (p) {
        var v = (p && p.mes) || '';
        var fm = document.getElementById('filterMonth');
        if (fm) fm.value = v;
        var b = document.getElementById(v ? 'applyFilterBtn' : 'clearFilterBtn');
        if (b) b.click();
    };

    g.SFPeriodo = { open: open, close: close, autoWire: autoWire };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', autoWire);
    else autoWire();
})(window);
