/**
 * Botão Ajuda (#helpBtn) — abre modal central (#sfHelpModal ou legado #helpModal).
 */
(function () {
    'use strict';

        function findHelpModal() {
        return document.getElementById('helpModal')
            || document.getElementById('sfHelpModal')
            || document.getElementById('helpVehiclesModal')
            || document.getElementById('helpMotoristsModal')
            || document.getElementById('helpMaintenanceModal')
            || document.getElementById('helpRouteModal')
            || document.getElementById('helpFornecedorModal')
            || document.getElementById('helpPneusModal')
            || document.getElementById('helpMultaModal')
            || document.getElementById('helpMotoristModal')
            || document.querySelector('[data-sf-help-key]');
    }

    function openHelpModal(modal) {
        if (!modal) return;
        modal.classList.add('active');
        modal.style.display = 'flex';
    }

    function closeHelpModal(modal) {
        if (!modal) return;
        modal.classList.remove('active');
        modal.style.display = 'none';
    }

    function bindHelpModal(modal) {
        if (!modal || modal.dataset.sfHelpBound === '1') return;
        modal.dataset.sfHelpBound = '1';

        modal.querySelectorAll('.close-modal, .sf-help-close').forEach(function (btn) {
            btn.addEventListener('click', function () { closeHelpModal(modal); });
        });
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeHelpModal(modal);
        });
    }

    function initHelpButton() {
        var btn = document.getElementById('helpBtn');
        var modal = findHelpModal();
        if (!btn || !modal) return;

        bindHelpModal(modal);

        var fresh = btn.cloneNode(true);
        btn.parentNode.replaceChild(fresh, btn);
        fresh.addEventListener('click', function (e) {
            e.preventDefault();
            openHelpModal(modal);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHelpButton);
    } else {
        initHelpButton();
    }

    window.sfOpenPageHelp = function () {
        openHelpModal(findHelpModal());
    };
    window.sfClosePageHelp = function () {
        closeHelpModal(findHelpModal());
    };
})();
