/**
 * Header do painel ADM (perfil, tema, ajuda contextual).
 */
(function () {
    'use strict';

    function initAdmProfileDropdown() {
        var btn = document.getElementById('userProfileBtn');
        var drop = document.getElementById('profileDropdown');
        if (!btn || !drop) return;

        function setOpen(open) {
            drop.classList.toggle('show', open);
            btn.classList.toggle('active', open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            setOpen(!drop.classList.contains('show'));
        });

        document.addEventListener('click', function (e) {
            if (!drop.classList.contains('show')) return;
            if (!drop.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
                setOpen(false);
            }
        });

        drop.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    }

    function initAdmPageHelp() {
        var btn = document.getElementById('helpBtn');
        var modal = document.getElementById('helpModal');
        if (!btn || !modal) return;

        function openModal() {
            modal.classList.add('active');
            modal.style.display = 'flex';
            modal.style.alignItems = 'center';
            modal.style.justifyContent = 'center';
            modal.style.position = 'fixed';
            modal.style.inset = '0';
            modal.style.zIndex = '1200';
            document.body.classList.add('adm-help-open');
        }

        function closeModal() {
            modal.classList.remove('active');
            modal.style.display = 'none';
            document.body.classList.remove('adm-help-open');
        }

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openModal();
        });

        modal.querySelectorAll('.close-modal, .sf-help-close').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                closeModal();
            });
        });

        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeModal();
            }
        });

        window.sfOpenPageHelp = openModal;
        window.sfClosePageHelp = closeModal;
    }

    function init() {
        initAdmProfileDropdown();
        initAdmPageHelp();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
