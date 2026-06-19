/* Configurações de Segurança — vários dashboards independentes (salvamento parcial)
   + whitelist de IP. */
(function () {
    'use strict';

    var API = '../api/seguranca.php';
    var forms = Array.prototype.slice.call(document.querySelectorAll('.config-sec-form'));
    var ipForm = document.getElementById('formIpWhitelist');
    if (!forms.length && !ipForm) return;

    function setVal(name, value) {
        var els = document.getElementsByName(name);
        if (!els || !els.length) return;
        var el = els[0];
        if (el.type === 'checkbox') {
            el.checked = (value === 1 || value === '1' || value === true);
        } else {
            el.value = (value === null || value === undefined) ? '' : value;
        }
    }

    function formMsg(form, txt, ok) {
        var box = form.parentNode.querySelector('.config-sec-formmsg');
        if (!box) return;
        box.textContent = txt;
        box.style.color = ok ? '#0a7d2c' : '#c0392b';
        setTimeout(function () { box.textContent = ''; }, 4000);
    }

    var FIELDS = [
        'idle_timeout_min', 'login_max_tentativas', 'login_bloqueio_min', 'senha_forte',
        'two_factor_obrigatorio', 'novo_acesso_email', 'smtp_host', 'smtp_port', 'smtp_user',
        'smtp_pass', 'smtp_secure', 'smtp_from', 'ip_whitelist_enabled', 'geo_block_enabled',
        'geo_paises_permitidos'
    ];

    function loadConfig() {
        fetch(API + '?action=get', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) return;
                var d = res.data || {};
                FIELDS.forEach(function (k) { setVal(k, d[k]); });
            })
            .catch(function () {});
    }

    function setBtnLoading(btn, loading) {
        if (!btn) return;
        if (loading) {
            btn.dataset.html = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando…';
        } else {
            btn.disabled = false;
            if (btn.dataset.html) btn.innerHTML = btn.dataset.html;
        }
    }

    forms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = form.querySelector('button[type="submit"]');
            setBtnLoading(btn, true);
            var fd = new FormData(form);
            fd.append('action', 'save');
            fetch(API, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    formMsg(form, res.success ? 'Salvo.' : (res.message || res.error || 'Erro ao salvar.'), !!res.success);
                })
                .catch(function () { formMsg(form, 'Erro de comunicação.', false); })
                .then(function () { setBtnLoading(btn, false); });
        });
    });

    // ---- Whitelist de IP ----
    var ipTable = document.getElementById('ipWhitelistBody');

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function renderIps(list) {
        if (!ipTable) return;
        if (!list.length) {
            ipTable.innerHTML = '<tr><td colspan="3" style="text-align:center;opacity:.7">Nenhum IP cadastrado (sem restrição).</td></tr>';
            return;
        }
        ipTable.innerHTML = list.map(function (r) {
            return '<tr><td>' + escapeHtml(r.ip) + '</td><td>' + escapeHtml(r.descricao || '') +
                '</td><td style="text-align:center"><button type="button" class="btn btn-sm btn-danger" data-del="' +
                r.id + '"><i class="fas fa-trash"></i></button></td></tr>';
        }).join('');
    }

    function loadIps() {
        if (!ipTable) return;
        fetch(API + '?action=ip_list').then(function (r) { return r.json(); })
            .then(function (res) { if (res.success) renderIps(res.data || []); });
    }

    if (ipForm) {
        ipForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(ipForm);
            fd.append('action', 'ip_add');
            fetch(API, { method: 'POST', body: fd }).then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) { ipForm.reset(); loadIps(); }
                    else { alert(res.message || res.error || 'IP inválido.'); }
                });
        });
    }

    if (ipTable) {
        ipTable.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-del]');
            if (!btn) return;
            if (!confirm('Remover este IP da whitelist?')) return;
            var fd = new FormData();
            fd.append('action', 'ip_delete');
            fd.append('id', btn.getAttribute('data-del'));
            fetch(API, { method: 'POST', body: fd }).then(function (r) { return r.json(); })
                .then(function () { loadIps(); });
        });
    }

    loadConfig();
    loadIps();
})();
