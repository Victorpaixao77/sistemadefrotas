<!-- Botão flutuante: voltar ao topo -->
<button type="button" class="scroll-to-top" id="scrollToTopBtn" aria-label="Voltar ao topo" title="Voltar ao topo">
    <i class="fas fa-arrow-up" aria-hidden="true"></i>
</button>

<script>
(function () {
    function scrollAllToTop() {
        var selectors = ['.main-content', '.dashboard-content', '.app-container'];
        for (var i = 0; i < selectors.length; i++) {
            var el = document.querySelector(selectors[i]);
            if (el && el.scrollTop > 0) {
                try {
                    el.scrollTo({ top: 0, behavior: 'smooth' });
                } catch (e) {
                    el.scrollTop = 0;
                }
            }
        }
        try {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch (e) {
            window.scrollTo(0, 0);
        }
        document.documentElement.scrollTop = 0;
        document.body.scrollTop = 0;
    }

    function initSfScrollTop() {
        var btn = document.getElementById('scrollToTopBtn');
        if (!btn) return;

        var anchor = document.querySelector('.dashboard-content')
            || document.querySelector('.main-content')
            || document.body;
        if (!anchor) return;

        if (document.getElementById('sfScrollTopSentinel')) {
            return;
        }

        var sen = document.createElement('div');
        sen.id = 'sfScrollTopSentinel';
        sen.setAttribute('aria-hidden', 'true');
        sen.style.cssText = 'width:1px;height:1px;margin:0;padding:0;border:0;pointer-events:none;position:absolute;top:0;left:0;overflow:hidden;opacity:0;';
        var pos = window.getComputedStyle(anchor).position;
        if (pos === 'static') {
            anchor.style.position = 'relative';
        }
        anchor.insertBefore(sen, anchor.firstChild);

        function setVisible(show) {
            btn.classList.toggle('is-visible', show);
        }

        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    setVisible(!e.isIntersecting);
                });
            }, { root: null, threshold: 0, rootMargin: '0px 0px 0px 0px' });
            io.observe(sen);
        } else {
            function scrollRootY() {
                var w = window.scrollY || document.documentElement.scrollTop || document.body.scrollTop || 0;
                var mc = document.querySelector('.main-content');
                var dc = document.querySelector('.dashboard-content');
                var m = mc ? (mc.scrollTop || 0) : 0;
                var d = dc ? (dc.scrollTop || 0) : 0;
                return Math.max(w, m, d);
            }
            function toggle() {
                setVisible(scrollRootY() > 180);
            }
            window.addEventListener('scroll', toggle, { passive: true });
            var mc = document.querySelector('.main-content');
            if (mc) mc.addEventListener('scroll', toggle, { passive: true });
            var dc = document.querySelector('.dashboard-content');
            if (dc) dc.addEventListener('scroll', toggle, { passive: true });
            toggle();
        }

        btn.addEventListener('click', scrollAllToTop);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSfScrollTop);
    } else {
        initSfScrollTop();
    }
})();
</script>
