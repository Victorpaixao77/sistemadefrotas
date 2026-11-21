/**
 * Control Center — flyout de submenus quando a sidebar está recolhida.
 */
(function () {
    'use strict';

    if (!document.body || !document.body.classList.contains('adm-app')) {
        return;
    }

    var flyout = null;
    var hideTimer = null;

    function closeFlyout() {
        if (flyout) {
            flyout.remove();
            flyout = null;
        }
    }

    function openFlyout(navItem, toggle, dropdown) {
        closeFlyout();
        if (!document.body.classList.contains('sidebar-collapsed')) {
            return;
        }

        var label = toggle.querySelector('.sidebar-link-text');
        var title = label ? label.textContent.trim() : 'Menu';

        flyout = document.createElement('div');
        flyout.className = 'adm-cc-sidebar-flyout';
        flyout.setAttribute('role', 'menu');
        flyout.innerHTML = '<div class="adm-cc-sidebar-flyout__title">' + escapeHtml(title) + '</div>';

        dropdown.querySelectorAll('.sidebar-dropdown-link').forEach(function (link) {
            var a = link.cloneNode(true);
            a.setAttribute('role', 'menuitem');
            flyout.appendChild(a);
        });

        document.body.appendChild(flyout);

        var rect = navItem.getBoundingClientRect();
        var top = Math.max(8, rect.top);
        flyout.style.top = top + 'px';
        flyout.style.left = (rect.right + 6) + 'px';

        var fh = flyout.offsetHeight;
        if (top + fh > window.innerHeight - 8) {
            flyout.style.top = Math.max(8, window.innerHeight - fh - 8) + 'px';
        }

        flyout.addEventListener('mouseenter', function () {
            clearTimeout(hideTimer);
        });
        flyout.addEventListener('mouseleave', function () {
            hideTimer = setTimeout(closeFlyout, 200);
        });
        flyout.addEventListener('click', function (e) {
            var a = e.target.closest('a[href]');
            if (a && a.getAttribute('href') !== '#') {
                closeFlyout();
            }
        });
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function bind() {
        document.querySelectorAll('.adm-cc-sidebar-nav .sidebar-nav-item').forEach(function (item) {
            var toggle = item.querySelector('.sidebar-dropdown-toggle');
            var dropdown = item.querySelector('.sidebar-dropdown');
            if (!toggle || !dropdown) {
                return;
            }

            toggle.addEventListener('mouseenter', function () {
                if (!document.body.classList.contains('sidebar-collapsed')) {
                    return;
                }
                clearTimeout(hideTimer);
                openFlyout(item, toggle, dropdown);
            });

            item.addEventListener('mouseleave', function () {
                hideTimer = setTimeout(closeFlyout, 250);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', bind);
    window.addEventListener('resize', closeFlyout);
    document.addEventListener('click', function (e) {
        if (flyout && !e.target.closest('.adm-cc-sidebar-flyout') && !e.target.closest('.sidebar-dropdown-toggle')) {
            closeFlyout();
        }
    });
})();
