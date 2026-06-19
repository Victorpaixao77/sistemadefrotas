(function () {
    'use strict';

    var form = document.getElementById('configNotifPrefsForm');
    if (!form) return;

    var boxOp = document.getElementById('notifPrefsOperacional');
    var boxIa = document.getElementById('notifPrefsIa');
    var msgEl = document.getElementById('configNotifPrefsMsg');
    var labels = {};

    function showMsg(text, ok) {
        if (!msgEl) return;
        msgEl.textContent = text;
        msgEl.style.color = ok ? 'var(--success-color, #16a34a)' : 'var(--danger-color, #dc2626)';
    }

    function labelFor(tipo) {
        return labels[tipo] || tipo;
    }

    function renderTipos(container, tipos, off) {
        if (!container) return;
        if (!tipos.length) {
            container.innerHTML = '<p class="notif-prefs-empty">Nenhum tipo disponível para o seu perfil.</p>';
            return;
        }
        container.innerHTML = tipos.map(function (t) {
            var checked = off.indexOf(t) < 0 ? ' checked' : '';
            return '<label class="notif-pref-check"><input type="checkbox" data-tipo="' + t + '" data-grupo="' +
                (container.id === 'notifPrefsOperacional' ? 'operacional' : 'ia') + '"' + checked + '> ' +
                labelFor(t) + '</label>';
        }).join('');
    }

    function coletarDesativados() {
        var off = [];
        form.querySelectorAll('input[data-tipo]:not(:checked)').forEach(function (cb) {
            off.push(cb.getAttribute('data-tipo'));
        });
        return off;
    }

    function setGrupo(grupo, acao) {
        form.querySelectorAll('input[data-tipo][data-grupo="' + grupo + '"]').forEach(function (cb) {
            cb.checked = acao === 'all';
        });
    }

    function carregar() {
        fetch(typeof sfAppUrl === 'function' ? sfAppUrl('notificacoes/preferencias.php') : '../notificacoes/preferencias.php', {
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    showMsg('Não foi possível carregar as preferências.', false);
                    return;
                }
                labels = data.labels || {};
                var p = data.preferencias || {};
                var off = p.tipos_desativados || [];
                renderTipos(boxOp, data.tipos_operacional || [], off);
                renderTipos(boxIa, data.tipos_ia || [], off);
                var digest = document.getElementById('notifPrefDigest');
                var hora = document.getElementById('notifPrefDigestHora');
                if (digest) digest.checked = !!p.email_digest;
                if (hora) hora.value = (p.email_digest_hora || '08:00:00').substring(0, 5);
            })
            .catch(function () {
                showMsg('Erro ao carregar preferências.', false);
            });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var body = new URLSearchParams();
        body.append('tipos_desativados', JSON.stringify(coletarDesativados()));
        body.append('email_digest', document.getElementById('notifPrefDigest').checked ? '1' : '0');
        body.append('email_digest_hora', document.getElementById('notifPrefDigestHora').value + ':00');
        if (window.__SF_CSRF__) body.append('csrf_token', window.__SF_CSRF__);

        var headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
        if (window.__SF_CSRF__) headers['X-CSRF-Token'] = window.__SF_CSRF__;

        fetch(typeof sfAppUrl === 'function' ? sfAppUrl('notificacoes/preferencias.php') : '../notificacoes/preferencias.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: headers,
            body: body.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    showMsg('Preferências salvas. Os alertas passam a respeitar sua escolha.', true);
                    if (window.sfNotif && typeof window.sfNotif.refreshBadges === 'function') {
                        window.sfNotif.refreshBadges();
                    }
                } else {
                    showMsg(d.error || 'Erro ao salvar preferências.', false);
                }
            })
            .catch(function () {
                showMsg('Erro ao salvar preferências.', false);
            });
    });

    document.querySelectorAll('[data-prefs-grupo][data-prefs-acao]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setGrupo(btn.getAttribute('data-prefs-grupo'), btn.getAttribute('data-prefs-acao'));
        });
    });

    carregar();
})();

(function () {
    'use strict';

    var formEmp = document.getElementById('configNotificacoesEmpresaForm');
    var formGamif = document.getElementById('configNotificacoesGamifForm');
    if (!formEmp && !formGamif) return;

    function loadEmpresaConfig() {
        fetch('../api/configuracoes.php?action=get_notifications_config', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success || !res.data) return;
                var d = res.data;
                var ativo = document.getElementById('sistema_notificacoes_ativo');
                if (ativo) ativo.checked = !!d.sistema_notificacoes_ativo;
                ['notificacoes_badges', 'notificacoes_niveis', 'notificacoes_ranking', 'notificacoes_desafios'].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) el.checked = !!d[id];
                });
            })
            .catch(function () { /* ignora */ });
    }

    if (formEmp) {
        formEmp.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(this);
            fd.append('action', 'save_notifications_empresa');
            var msg = document.getElementById('configNotificacoesEmpresaMsg');
            if (msg) msg.textContent = '';
            fetch('../api/configuracoes.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!msg) return;
                    if (res.success) {
                        msg.textContent = res.message || 'Salvo.';
                        msg.style.color = 'var(--success-color, #16a34a)';
                    } else {
                        msg.textContent = res.error || 'Erro ao salvar.';
                        msg.style.color = 'var(--danger-color, #dc2626)';
                    }
                })
                .catch(function () {
                    if (msg) {
                        msg.textContent = 'Erro ao salvar.';
                        msg.style.color = 'var(--danger-color, #dc2626)';
                    }
                });
        });
    }

    if (formGamif) {
        formGamif.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(this);
            fd.append('action', 'save_notifications_config');
            var msg = document.getElementById('configNotificacoesGamifMsg');
            if (msg) msg.textContent = '';
            fetch('../api/configuracoes.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!msg) return;
                    if (res.success) {
                        msg.textContent = res.message || 'Salvo.';
                        msg.style.color = 'var(--success-color, #16a34a)';
                    } else {
                        msg.textContent = res.error || 'Erro ao salvar.';
                        msg.style.color = 'var(--danger-color, #dc2626)';
                    }
                })
                .catch(function () {
                    if (msg) {
                        msg.textContent = 'Erro ao salvar.';
                        msg.style.color = 'var(--danger-color, #dc2626)';
                    }
                });
        });
    }

    loadEmpresaConfig();
})();
