/**
 * Colunas opcionais em tabelas modernas (mesmo padrão de Rotas).
 * Requer: thead/tbody com células [data-col="chave"] e lista de checkboxes input[data-col-toggle="chave"].
 */
(function () {
    'use strict';

    function readVis(storageKey) {
        try {
            var raw = localStorage.getItem(storageKey);
            if (!raw) return null;
            var o = JSON.parse(raw);
            return o && typeof o === 'object' ? o : null;
        } catch (e) {
            return null;
        }
    }

    function writeVis(storageKey, vis) {
        try {
            localStorage.setItem(storageKey, JSON.stringify(vis));
        } catch (e) { /* ignore */ }
    }

    function apply(table, toggleRoot) {
        if (!table || !toggleRoot) return;
        toggleRoot.querySelectorAll('input[data-col-toggle]').forEach(function (cb) {
            var key = cb.getAttribute('data-col-toggle');
            if (!key) return;
            var on = cb.checked;
            table.querySelectorAll('[data-col="' + key + '"]').forEach(function (cell) {
                cell.style.display = on ? '' : 'none';
            });
        });
    }

    function collectVisibility(toggleRoot) {
        var vis = {};
        toggleRoot.querySelectorAll('input[data-col-toggle]').forEach(function (cb) {
            var k = cb.getAttribute('data-col-toggle');
            if (k) vis[k] = cb.checked;
        });
        return vis;
    }

    /**
     * @param {Object} opts
     * @param {string} opts.tableSelector
     * @param {string} opts.toggleListSelector  — elemento que contém os inputs (ex.: #fooColumnsToggleList)
     * @param {string} opts.storageKey — chave localStorage (só o JSON de visibilidade)
     * @param {string} [opts.columnsBtnId]
     * @param {string} [opts.modalId]
     * @param {string} [opts.saveBtnId]
     * @param {function(string)} [opts.onSaved] — mensagem opcional
     */
    function initForncTableColumns(opts) {
        var table = document.querySelector(opts.tableSelector);
        var toggleRoot = document.querySelector(opts.toggleListSelector);
        if (!table || !toggleRoot) return null;

        var stored = readVis(opts.storageKey);
        if (stored) {
            Object.keys(stored).forEach(function (k) {
                var cb = toggleRoot.querySelector('input[data-col-toggle="' + k + '"]');
                if (cb) cb.checked = !!stored[k];
            });
        }

        function reapply() {
            apply(table, toggleRoot);
        }
        reapply();

        toggleRoot.querySelectorAll('input[data-col-toggle]').forEach(function (cb) {
            cb.addEventListener('change', reapply);
        });

        var modal = opts.modalId ? document.getElementById(opts.modalId) : null;
        var openBtn = opts.columnsBtnId ? document.getElementById(opts.columnsBtnId) : null;
        if (openBtn && modal) {
            openBtn.addEventListener('click', function () {
                modal.classList.add('active');
            });
        }

        var saveBtn = opts.saveBtnId ? document.getElementById(opts.saveBtnId) : null;
        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                writeVis(opts.storageKey, collectVisibility(toggleRoot));
                reapply();
                if (modal) {
                    modal.classList.remove('active');
                    modal.style.removeProperty('display');
                }
                if (typeof opts.onSaved === 'function') {
                    opts.onSaved('Preferências de colunas salvas neste navegador.');
                }
            });
        }

        table._forncColumnVisibilityApply = reapply;
        return { apply: reapply };
    }

    window.initForncTableColumns = initForncTableColumns;
})();
