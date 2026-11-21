/**
 * Favoritos do menu lateral (localStorage)
 */
(function () {
    'use strict';
    var KEY = 'adm_cc_favorites';
    var MAX = 12;

    function read() {
        try {
            var raw = localStorage.getItem(KEY);
            var list = raw ? JSON.parse(raw) : [];
            return Array.isArray(list) ? list : [];
        } catch (e) {
            return [];
        }
    }

    function write(list) {
        try {
            localStorage.setItem(KEY, JSON.stringify(list.slice(0, MAX)));
        } catch (e) { /* ignore */ }
    }

    function isFav(href, list) {
        return list.some(function (f) { return f.href === href; });
    }

    function toggle(href, label, icon) {
        var list = read();
        var i = list.findIndex(function (f) { return f.href === href; });
        if (i >= 0) {
            list.splice(i, 1);
        } else {
            list.unshift({ href: href, label: label, icon: icon || 'fa-star' });
        }
        write(list);
        render();
        syncStars();
    }

    function render() {
        var nav = document.getElementById('sidebar-nav');
        if (!nav) return;
        var old = document.getElementById('adm-cc-favorites');
        if (old) old.remove();
        var list = read();
        if (!list.length) return;
        var ul = document.createElement('ul');
        ul.id = 'adm-cc-favorites';
        ul.className = 'sidebar-nav-list adm-cc-favorites';
        var html = '<li class="sidebar-nav-item adm-cc-favorites__head"><span class="sidebar-link-text"><i class="fas fa-star"></i> Favoritos</span></li>';
        list.forEach(function (f) {
            var active = location.pathname.indexOf(f.href) >= 0 ? ' active' : '';
            html += '<li><a href="' + f.href + '" class="sidebar-dropdown-link' + active + '">' +
                '<i class="fas ' + (f.icon || 'fa-link') + '"></i> ' + f.label +
                ' <button type="button" class="adm-cc-fav-remove" data-href="' + f.href + '" title="Remover" aria-label="Remover favorito">&times;</button></a></li>';
        });
        ul.innerHTML = html;
        var firstSection = nav.querySelector('.adm-cc-sidebar-nav > .sidebar-nav-item');
        if (firstSection) {
            nav.insertBefore(ul, firstSection);
        } else {
            nav.appendChild(ul);
        }
        ul.querySelectorAll('.adm-cc-fav-remove').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var href = btn.getAttribute('data-href');
                write(read().filter(function (f) { return f.href !== href; }));
                render();
                syncStars();
            });
        });
    }

    function syncStars() {
        var list = read();
        document.querySelectorAll('.adm-cc-fav-star').forEach(function (btn) {
            var href = btn.getAttribute('data-href');
            var on = isFav(href, list);
            btn.classList.toggle('adm-cc-fav-star--on', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            btn.title = on ? 'Remover dos favoritos' : 'Adicionar aos favoritos';
        });
    }

    function attachStars() {
        document.querySelectorAll('.sidebar-dropdown-link').forEach(function (a) {
            if (a.querySelector('.adm-cc-fav-star') || a.classList.contains('adm-cc-favorites__link')) return;
            var href = a.getAttribute('href');
            if (!href || href === '#') return;
            var iconEl = a.querySelector('i.fas');
            var icon = iconEl ? iconEl.className.replace(/^fas\s+/, '') : 'fa-link';
            var label = a.textContent.trim();
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'adm-cc-fav-star';
            btn.setAttribute('data-href', href);
            btn.setAttribute('data-label', label);
            btn.setAttribute('data-icon', icon);
            btn.innerHTML = '<i class="fas fa-star"></i>';
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                toggle(href, label, icon);
            });
            a.appendChild(btn);
        });
        syncStars();
    }

    function init() {
        render();
        attachStars();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.AdmSidebarFavorites = { toggle: toggle, read: read };
})();
