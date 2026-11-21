/**
 * Busca no menu lateral do Control Center ADM
 */
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var input = document.getElementById('adm-sidebar-search');
        if (!input) return;

        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            document.querySelectorAll('.adm-cc-sidebar-nav .sidebar-nav-item').forEach(function (item) {
                var links = item.querySelectorAll('.sidebar-dropdown-link');
                var any = false;
                links.forEach(function (a) {
                    var text = (a.textContent || '').toLowerCase();
                    var show = !q || text.indexOf(q) !== -1;
                    a.closest('li').style.display = show ? '' : 'none';
                    if (show) any = true;
                });
                item.style.display = any || !q ? '' : 'none';
                if (q && any) {
                    var dd = item.querySelector('.sidebar-dropdown');
                    if (dd) dd.style.display = 'block';
                }
            });
        });
    });
})();
