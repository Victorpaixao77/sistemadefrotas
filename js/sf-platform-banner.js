/**
 * Modal central de comunicação da plataforma (Control Center).
 */
(function () {
    'use strict';

    function init() {
        var el = document.getElementById('sfPlatformBannerModal');
        if (!el) return;
        var id = el.getAttribute('data-banner-id');
        if (!id) return;
        var key = 'sf_banner_dismiss_' + id;
        try {
            if (localStorage.getItem(key) === '1') return;
        } catch (e) { /* ignore */ }

        el.hidden = false;
        el.setAttribute('aria-hidden', 'false');
        document.body.classList.add('sf-platform-banner-open');

        function close() {
            el.hidden = true;
            el.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('sf-platform-banner-open');
            try { localStorage.setItem(key, '1'); } catch (e) { /* ignore */ }
        }

        el.querySelectorAll('[data-sf-banner-close]').forEach(function (btn) {
            btn.addEventListener('click', close);
        });
        el.addEventListener('click', function (e) {
            if (e.target === el.querySelector('.sf-platform-banner__backdrop')) {
                close();
            }
        });
        document.addEventListener('keydown', function onKey(e) {
            if (e.key === 'Escape' && !el.hidden) {
                close();
                document.removeEventListener('keydown', onKey);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
