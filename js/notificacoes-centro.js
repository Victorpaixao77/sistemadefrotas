(function () {
    'use strict';
    var sf = window.sfNotif;
    if (!sf) return;

    var listEl = document.getElementById('notifListCentro');
    sf.bindItemClicks(listEl);

    listEl.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-notif-acao]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        var acao = btn.getAttribute('data-notif-acao');
        var ref = btn.getAttribute('data-notif-ref');
        var nid = btn.getAttribute('data-notif-id');
        sf.runAction(acao, ref, nid).then(function (data) {
            if (data && data.success) {
                carregarLista();
                sf.refreshBadges();
            }
        });
    });

    function buildUrl() {
        var escopo = document.getElementById('filtroEscopo').value;
        var sev = document.getElementById('filtroSeveridade').value;
        var tipo = document.getElementById('filtroTipo').value;
        var todas = document.getElementById('verTodas').checked;
        var url = sfAppUrl('notificacoes/notificacoes.php');
        var q = [];
        if (escopo && escopo !== 'todos') q.push('escopo=' + encodeURIComponent(escopo));
        if (sev) q.push('severidade=' + encodeURIComponent(sev));
        if (tipo) q.push('tipo=' + encodeURIComponent(tipo));
        if (todas) q.push('todas=1');
        if (q.length) url += '?' + q.join('&');
        return url;
    }

    function carregarLista() {
        fetch(buildUrl(), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    listEl.innerHTML = '<p>Erro ao carregar.</p>';
                    return;
                }
                if (!data.notificacoes || !data.notificacoes.length) {
                    listEl.innerHTML = '<p style="color:#94a3b8;padding:1rem;">Nenhuma notificação encontrada.</p>';
                    return;
                }
                listEl.innerHTML = data.notificacoes.map(function (n) {
                    return sf.renderItem(n, { showIaTip: true, extraClass: 'sev-' + (n.severidade || 'info') });
                }).join('');
            });
    }

    function carregarPrefs() {
        fetch(sfAppUrl('notificacoes/preferencias.php'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) return;
                var p = data.preferencias || {};
                document.getElementById('prefDigest').checked = !!p.email_digest;
                var h = (p.email_digest_hora || '08:00:00').substring(0, 5);
                document.getElementById('prefDigestHora').value = h;
                var tipos = [].concat(data.tipos_operacional || [], data.tipos_ia || []);
                var off = p.tipos_desativados || [];
                var lbl = data.labels || {};
                var sel = document.getElementById('filtroTipo');
                var box = document.getElementById('prefTiposOff');
                sel.innerHTML = '<option value="">Todos os tipos</option>';
                box.innerHTML = '<strong>Silenciar tipos:</strong>';
                tipos.forEach(function (t) {
                    var nome = lbl[t] || t;
                    sel.innerHTML += '<option value="' + t + '">' + nome + '</option>';
                    var id = 'off_' + t;
                    box.innerHTML += '<label><input type="checkbox" id="' + id + '" data-tipo="' + t + '"' + (off.indexOf(t) >= 0 ? ' checked' : '') + '> ' + nome + '</label>';
                });
            });
    }

    document.getElementById('btnSync').addEventListener('click', function () {
        fetch(sfAppUrl('notificacoes/sync_all.php'), { credentials: 'same-origin' })
            .then(function () { carregarLista(); sf.refreshBadges(); });
    });

    document.getElementById('btnLimparEscopo').addEventListener('click', function () {
        var escopo = document.getElementById('filtroEscopo').value;
        var url = escopo === 'ia'
            ? sfAppUrl('notificacoes/limpar_notificacoes_ia.php')
            : sfAppUrl('notificacoes/limpar_notificacoes.php');
        fetch(url, { method: 'POST', credentials: 'same-origin', headers: sf.mutationHeaders() })
            .then(function () { carregarLista(); sf.refreshBadges(); });
    });

    document.getElementById('btnSalvarPrefs').addEventListener('click', function () {
        var off = [];
        document.querySelectorAll('#prefTiposOff input[data-tipo]:checked').forEach(function (cb) {
            off.push(cb.getAttribute('data-tipo'));
        });
        var body = new URLSearchParams();
        body.append('tipos_desativados', JSON.stringify(off));
        body.append('email_digest', document.getElementById('prefDigest').checked ? '1' : '0');
        body.append('email_digest_hora', document.getElementById('prefDigestHora').value + ':00');
        if (window.__SF_CSRF__) body.append('csrf_token', window.__SF_CSRF__);
        fetch(sfAppUrl('notificacoes/preferencias.php'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: sf.mutationHeaders({ 'Content-Type': 'application/x-www-form-urlencoded' }),
            body: body.toString()
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (d.success) alert('Preferências salvas.');
        });
    });

    ['filtroEscopo', 'filtroSeveridade', 'filtroTipo', 'verTodas'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', carregarLista);
    });

    carregarPrefs();
    carregarLista();
})();
