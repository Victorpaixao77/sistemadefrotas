(function () {
    'use strict';

    var sf = window.sfNotif;
    if (!sf) {
        console.error('notificacoes-centro: sfNotif não carregado');
        return;
    }

    var esc = sf.escapeHtml;
    var listEl = document.getElementById('notifListCentro');
    var msgEl = document.getElementById('notifPrefsMsg');
    var cacheItems = [];
    var listLoading = false;

    if (!listEl) return;

    sf.bindItemClicks(listEl);

    listEl.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-notif-acao]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        var acao = btn.getAttribute('data-notif-acao');
        var ref = btn.getAttribute('data-notif-ref');
        var nid = btn.getAttribute('data-notif-id');
        btn.disabled = true;
        sf.runAction(acao, ref, nid).then(function (data) {
            if (data && data.success) {
                showPrefsMsg('Ação concluída.', true);
                carregarLista();
                atualizarKpis();
            } else {
                showPrefsMsg((data && data.error) ? data.error : 'Não foi possível executar a ação.', false);
            }
        }).catch(function () {
            showPrefsMsg('Erro de rede ao executar ação.', false);
        }).finally(function () {
            btn.disabled = false;
        });
    });

    function showPrefsMsg(text, ok) {
        if (!msgEl) return;
        msgEl.textContent = text || '';
        msgEl.className = 'notif-centro-msg' + (text ? (ok ? ' is-ok' : ' is-err') : '');
    }

    function setListLoading(on) {
        listLoading = on;
        listEl.setAttribute('aria-busy', on ? 'true' : 'false');
        if (on) {
            listEl.innerHTML = '<div class="notif-centro-loading"><div class="notif-spinner" aria-hidden="true"></div><span>Carregando notificações…</span></div>';
        }
    }

    function getEscopo() {
        var sel = document.getElementById('filtroEscopo');
        return sel ? sel.value : 'todos';
    }

    function setEscopo(val) {
        var sel = document.getElementById('filtroEscopo');
        if (sel) sel.value = val;
        document.querySelectorAll('[data-filtro-escopo]').forEach(function (btn) {
            var active = btn.getAttribute('data-filtro-escopo') === val;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function buildUrl() {
        var escopo = getEscopo();
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

    function filtroBuscaTexto() {
        var q = (document.getElementById('filtroBusca').value || '').trim().toLowerCase();
        if (!q) return cacheItems;
        return cacheItems.filter(function (n) {
            var t = ((n.titulo || '') + ' ' + (n.mensagem || '') + ' ' + (n.ia_mensagem || '')).toLowerCase();
            return t.indexOf(q) >= 0;
        });
    }

    function renderLista(items) {
        if (!items.length) {
            var escopo = getEscopo();
            var hint = escopo === 'ia' ? ' de IA' : (escopo === 'operacional' ? ' operacionais' : '');
            listEl.innerHTML =
                '<div class="notif-centro-empty">' +
                '<i class="fas fa-bell-slash" aria-hidden="true"></i>' +
                '<strong>Nenhuma notificação' + esc(hint) + '</strong>' +
                '<p>Tente incluir lidas, alterar filtros ou clicar em Atualizar.</p>' +
                '</div>';
            return;
        }
        listEl.innerHTML = items.map(function (n) {
            return sf.renderItem(n, { showIaTip: true, extraClass: 'sev-' + (n.severidade || 'info') });
        }).join('');
    }

    function carregarLista() {
        if (listLoading) return;
        setListLoading(true);
        fetch(buildUrl(), { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) {
                if (!data.success) {
                    listEl.innerHTML = '<div class="notif-centro-error"><i class="fas fa-exclamation-circle"></i><strong>Erro ao carregar</strong><p>' + esc(data.error || 'Tente novamente.') + '</p></div>';
                    cacheItems = [];
                    return;
                }
                if (data.sistema_ativo === false) {
                    listEl.innerHTML = '<div class="notif-centro-empty"><i class="fas fa-bell-slash"></i><strong>Notificações desativadas</strong><p>Ative o sistema em Configurações.</p></div>';
                    cacheItems = [];
                    return;
                }
                cacheItems = data.notificacoes || [];
                renderLista(filtroBuscaTexto());
            })
            .catch(function (err) {
                console.error('notificacoes-centro lista:', err);
                listEl.innerHTML = '<div class="notif-centro-error"><i class="fas fa-wifi"></i><strong>Falha de conexão</strong><p>Não foi possível carregar as notificações.</p></div>';
                cacheItems = [];
            })
            .finally(function () {
                listLoading = false;
                listEl.setAttribute('aria-busy', 'false');
            });
    }

    function atualizarKpis() {
        fetch(sfAppUrl('notificacoes/contagens.php'), { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.success) return;
                var op = parseInt(data.operacional, 10) || 0;
                var ia = parseInt(data.ia, 10) || 0;
                var elOp = document.getElementById('kpiOperacional');
                var elIa = document.getElementById('kpiIa');
                var elTotal = document.getElementById('kpiTotal');
                if (elOp) elOp.textContent = String(op);
                if (elIa) elIa.textContent = String(ia);
                if (elTotal) elTotal.textContent = String(op + ia);
                sf.refreshBadges();
            })
            .catch(function () { /* silencioso */ });
    }

    function renderPrefTipos(container, tipos, off, grupo) {
        if (!container) return;
        if (!tipos.length) {
            container.innerHTML = '<p class="notif-prefs-empty">Nenhum tipo disponível.</p>';
            return;
        }
        var lbl = window.__notifLabels || {};
        container.innerHTML = tipos.map(function (t) {
            var nome = lbl[t] || t;
            var silenciado = off.indexOf(t) >= 0;
            return '<label class="notif-pref-check"><input type="checkbox" data-tipo="' + esc(t) + '" data-grupo="' + esc(grupo) + '"' +
                (silenciado ? ' checked' : '') + '> Silenciar: ' + esc(nome) + '</label>';
        }).join('');
    }

    function carregarPrefs() {
        fetch(sfAppUrl('notificacoes/preferencias.php'), { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.success) {
                    showPrefsMsg('Não foi possível carregar preferências.', false);
                    return;
                }
                var p = data.preferencias || {};
                document.getElementById('prefDigest').checked = !!p.email_digest;
                document.getElementById('prefDigestHora').value = (p.email_digest_hora || '08:00:00').substring(0, 5);

                window.__notifLabels = data.labels || {};
                var off = p.tipos_desativados || [];
                var tiposOp = data.tipos_operacional || [];
                var tiposIa = data.tipos_ia || [];

                renderPrefTipos(document.getElementById('prefTiposOffOp'), tiposOp, off, 'operacional');
                renderPrefTipos(document.getElementById('prefTiposOffIa'), tiposIa, off, 'ia');

                var sel = document.getElementById('filtroTipo');
                sel.innerHTML = '<option value="">Todos os tipos</option>';
                tiposOp.concat(tiposIa).forEach(function (t) {
                    var opt = document.createElement('option');
                    opt.value = t;
                    opt.textContent = window.__notifLabels[t] || t;
                    sel.appendChild(opt);
                });
            })
            .catch(function () {
                showPrefsMsg('Erro ao carregar preferências.', false);
            });
    }

    function coletarTiposSilenciados() {
        var off = [];
        document.querySelectorAll('.notif-prefs-tipos input[data-tipo]:checked').forEach(function (cb) {
            off.push(cb.getAttribute('data-tipo'));
        });
        return off;
    }

    function setPrefGrupo(grupo, acao) {
        document.querySelectorAll('input[data-tipo][data-grupo="' + grupo + '"]').forEach(function (cb) {
            cb.checked = acao === 'none';
        });
    }

    document.querySelectorAll('[data-pref-grupo]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setPrefGrupo(btn.getAttribute('data-pref-grupo'), btn.getAttribute('data-pref-acao'));
        });
    });

    document.querySelectorAll('[data-filtro-escopo]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var val = btn.getAttribute('data-filtro-escopo');
            setEscopo(val);
            carregarLista();
        });
    });

    var btnSync = document.getElementById('btnSync');
    btnSync.addEventListener('click', function () {
        btnSync.disabled = true;
        btnSync.classList.add('is-loading');
        fetch(sfAppUrl('notificacoes/sync_all.php'), { credentials: 'same-origin' })
            .then(function () {
                carregarLista();
                atualizarKpis();
            })
            .catch(function () {
                showPrefsMsg('Erro ao sincronizar.', false);
            })
            .finally(function () {
                btnSync.disabled = false;
                btnSync.classList.remove('is-loading');
            });
    });

    document.getElementById('btnLimparEscopo').addEventListener('click', function () {
        var escopo = getEscopo();
        var msg = escopo === 'ia'
            ? 'Marcar todas as notificações de IA como lidas?'
            : (escopo === 'operacional'
                ? 'Marcar todas as notificações operacionais como lidas?'
                : 'Marcar todas as notificações (operacionais e IA) como lidas?');
        if (!window.confirm(msg)) return;

        var urls = [];
        if (escopo === 'ia') {
            urls.push(sfAppUrl('notificacoes/limpar_notificacoes_ia.php'));
        } else if (escopo === 'operacional') {
            urls.push(sfAppUrl('notificacoes/limpar_notificacoes.php'));
        } else {
            urls.push(sfAppUrl('notificacoes/limpar_notificacoes.php'));
            urls.push(sfAppUrl('notificacoes/limpar_notificacoes_ia.php'));
        }

        var chain = Promise.resolve();
        urls.forEach(function (url) {
            chain = chain.then(function () {
                return fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: sf.mutationHeaders()
                });
            });
        });

        chain.then(function () {
            carregarLista();
            atualizarKpis();
            showPrefsMsg('Notificações marcadas como lidas.', true);
        }).catch(function () {
            showPrefsMsg('Erro ao marcar como lidas.', false);
        });
    });

    document.getElementById('btnSalvarPrefs').addEventListener('click', function () {
        var btn = document.getElementById('btnSalvarPrefs');
        btn.disabled = true;
        var off = coletarTiposSilenciados();
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
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                showPrefsMsg(d.success ? 'Preferências salvas com sucesso.' : (d.error || 'Erro ao salvar.'), !!d.success);
            })
            .catch(function () {
                showPrefsMsg('Erro de rede ao salvar.', false);
            })
            .finally(function () {
                btn.disabled = false;
            });
    });

    ['filtroSeveridade', 'filtroTipo', 'verTodas'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', carregarLista);
    });

    var buscaEl = document.getElementById('filtroBusca');
    var buscaTimer;
    buscaEl.addEventListener('input', function () {
        clearTimeout(buscaTimer);
        buscaTimer = setTimeout(function () {
            if (!listLoading) renderLista(filtroBuscaTexto());
        }, 180);
    });

    carregarPrefs();
    carregarLista();
})();
