/**
 * Fechamento global de .modal com Escape, restauração de foco e scroll do body.
 * Roda em fase de captura para preceder header/sidebar/outros listeners em bolha.
 */
(function () {
    if (typeof window === 'undefined' || window.__sfModalA11yInit) {
        return;
    }
    window.__sfModalA11yInit = true;

    /**
     * Modais que não devem ser fechados pelo handler global (teardown próprio, removem-se do DOM, etc.).
     * Marque no HTML/JS: data-sf-modal-no-global-escape ou data-sf-modal-no-global-escape="true"
     * Para reativar o global num filho específico: data-sf-modal-no-global-escape="false"
     */
    function isExcludedFromGlobalEscape(el) {
        if (!el || typeof el.getAttribute !== 'function') {
            return false;
        }
        var v = el.getAttribute('data-sf-modal-no-global-escape');
        if (v === null) {
            return false;
        }
        var s = String(v).toLowerCase();
        return s !== 'false' && s !== '0';
    }

    function isModalOpen(el) {
        if (!el || el.nodeType !== 1 || !el.classList || !el.classList.contains('modal')) {
            return false;
        }
        if (isExcludedFromGlobalEscape(el)) {
            return false;
        }
        var cs = window.getComputedStyle(el);
        return cs.display !== 'none' && cs.visibility !== 'hidden';
    }

    function getOpenModals() {
        return Array.prototype.slice.call(document.querySelectorAll('.modal')).filter(isModalOpen);
    }

    function maybeRestoreBodyScroll() {
        if (getOpenModals().length === 0) {
            document.body.style.overflow = '';
        }
    }

    function closeModalElement(modal) {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            try {
                var inst = bootstrap.Modal.getInstance(modal);
                if (inst) {
                    inst.hide();
                    maybeRestoreBodyScroll();
                    return;
                }
            } catch (e) { /* ignore */ }
        }

        modal.classList.remove('active', 'show');
        var d = modal.style.display;
        if (d && d !== 'none') {
            modal.style.display = 'none';
        } else {
            modal.style.removeProperty('display');
        }

        try {
            modal.dispatchEvent(new CustomEvent('sfmodalclose', { bubbles: true }));
        } catch (e2) { /* ignore */ }

        maybeRestoreBodyScroll();
    }

    /**
     * Fecha o modal .modal mais “por cima” (último no documento). Retorna true se fechou algum.
     */
    window.sfCloseTopModal = function () {
        var open = getOpenModals();
        if (!open.length) {
            return false;
        }
        var top = open[open.length - 1];
        closeModalElement(top);
        return true;
    };

    document.addEventListener(
        'mousedown',
        function (e) {
            if (e.button !== 0) {
                return;
            }
            var t = e.target;
            if (!t || !t.closest) {
                return;
            }
            var m = t.closest('.modal');
            if (m && isModalOpen(m) && t === m) {
                return;
            }
            window.__sfModalOpenerCandidate = t;
        },
        true
    );

    function restoreFocusAfterClose(closedModal) {
        var cand = window.__sfModalOpenerCandidate;
        if (
            cand &&
            cand.nodeType === 1 &&
            document.contains(cand) &&
            closedModal &&
            !closedModal.contains(cand) &&
            cand !== document.body &&
            cand !== document.documentElement
        ) {
            try {
                if (typeof cand.focus === 'function') {
                    cand.focus();
                    return;
                }
            } catch (e) { /* ignore */ }
        }
        var main = document.getElementById('conteudo-principal');
        if (main && typeof main.focus === 'function') {
            try {
                main.focus();
            } catch (e2) { /* ignore */ }
        }
    }

    document.addEventListener(
        'keydown',
        function (e) {
            if (e.key !== 'Escape') {
                return;
            }
            var open = getOpenModals();
            if (!open.length) {
                return;
            }
            var top = open[open.length - 1];
            e.preventDefault();
            e.stopPropagation();
            closeModalElement(top);
            requestAnimationFrame(function () {
                restoreFocusAfterClose(top);
            });
        },
        true
    );
})();
