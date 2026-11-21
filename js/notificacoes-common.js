/**

 * Utilitários compartilhados — Notificações (sininho + painel IA).

 */

(function (global) {

    'use strict';



    function escapeHtml(value) {

        if (value == null) return '';

        return String(value)

            .replace(/&/g, '&amp;')

            .replace(/</g, '&lt;')

            .replace(/>/g, '&gt;')

            .replace(/"/g, '&quot;')

            .replace(/'/g, '&#39;');

    }



    function mutationHeaders(extra) {

        if (typeof global.sfMutationHeaders === 'function') {

            return global.sfMutationHeaders(extra);

        }

        var h = extra ? Object.assign({}, extra) : {};

        if (typeof global.__SF_CSRF__ === 'string' && global.__SF_CSRF__) {

            h['X-CSRF-Token'] = global.__SF_CSRF__;

        }

        return h;

    }



    function iconForTipo(tipo) {

        var map = {

            manutencao: { icon: 'fa-tools', cls: 'warning' },

            alerta: { icon: 'fa-exclamation-triangle', cls: 'warning' },

            pneu: { icon: 'fa-circle', cls: 'success' },

            documento: { icon: 'fa-file-alt', cls: 'info' },

            cnh: { icon: 'fa-id-card', cls: 'warning' },

            financeiro: { icon: 'fa-dollar-sign', cls: 'danger' },

            despesa: { icon: 'fa-wallet', cls: 'warning' },

            multa: { icon: 'fa-gavel', cls: 'danger' },

            comissao: { icon: 'fa-hand-holding-usd', cls: 'info' },

            fiscal: { icon: 'fa-file-invoice', cls: 'info' },

            rota: { icon: 'fa-route', cls: 'info' },

            abastecimento: { icon: 'fa-gas-pump', cls: 'warning' },

            consumo: { icon: 'fa-tachometer-alt', cls: 'info' },

            custo: { icon: 'fa-chart-line', cls: 'info' },

            seguranca: { icon: 'fa-shield-alt', cls: 'danger' },

            insights: { icon: 'fa-brain', cls: 'info' },

            recomendacao: { icon: 'fa-lightbulb', cls: 'info' },

            gps: { icon: 'fa-map-marker-alt', cls: 'info' },

            fraudes: { icon: 'fa-user-secret', cls: 'danger' },

            fornecedor: { icon: 'fa-truck-loading', cls: 'warning' },

            checklist: { icon: 'fa-clipboard-check', cls: 'warning' },

            plataforma: { icon: 'fa-bullhorn', cls: 'info' }

        };

        return map[tipo] || { icon: 'fa-bell', cls: 'info' };

    }



    function severidadeLabel(sev) {

        if (sev === 'critico') return 'Crítico';

        if (sev === 'aviso') return 'Aviso';

        return 'Info';

    }



    function formatTempo(dataStr) {

        var dataNotif = new Date(dataStr);

        if (isNaN(dataNotif.getTime())) return '';

        var agora = new Date();

        var diffDias = Math.floor((agora - dataNotif) / (1000 * 60 * 60 * 24));

        var hora = dataNotif.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

        if (diffDias === 0) return 'Hoje às ' + hora;

        if (diffDias === 1) return 'Ontem às ' + hora;

        if (diffDias < 7) return diffDias + ' dias atrás às ' + hora;

        return dataNotif.toLocaleDateString('pt-BR') + ' às ' + hora;

    }



    function buildListUrl(escopo, verTodas) {

        var url = typeof global.sfAppUrl === 'function'

            ? global.sfAppUrl('notificacoes/notificacoes.php')

            : '../notificacoes/notificacoes.php';

        var q = [];

        if (escopo && escopo !== 'todos') q.push('escopo=' + encodeURIComponent(escopo));

        if (verTodas) q.push('todas=1');

        if (q.length) url += (url.indexOf('?') >= 0 ? '&' : '?') + q.join('&');

        return url;

    }



    function renderAcoes(n) {

        var acoes = (n.meta && n.meta.acoes) ? n.meta.acoes : [];

        if (!acoes.length) return '';

        var html = '<div class="notif-acoes">';

        acoes.forEach(function (a) {

            var cls = (a.acao || '').indexOf('rejeitar') >= 0 ? 'btn-rejeitar' : 'btn-aprovar';

            html += '<button type="button" class="' + cls + '" data-notif-acao="' + escapeHtml(a.acao) + '" data-notif-ref="' + escapeHtml(a.ref) + '" data-notif-id="' + escapeHtml(n.id) + '">' + escapeHtml(a.label || a.acao) + '</button>';

        });

        html += '</div>';

        return html;

    }



    function renderItem(n, opts) {

        opts = opts || {};

        var ic = iconForTipo(n.tipo);

        var unread = n.lida == 0 ? 'unread' : 'lida';

        var showIa = opts.showIaTip !== false;

        var itemClass = opts.itemClass || 'notification-item';

        var iconWrapClass = opts.iconWrapClass || 'notification-icon';

        var contentClass = opts.contentClass || 'notification-content';

        var titleClass = opts.titleClass || 'notification-title';

        var textClass = opts.textClass || 'notification-text';

        var timeClass = opts.timeClass || 'notification-time';

        var sev = n.severidade || 'info';

        var extra = opts.extraClass || ('sev-' + sev);



        var url = n.url ? escapeHtml(n.url) : '';

        var clickAttr = url

            ? ' data-notif-id="' + escapeHtml(n.id) + '" data-notif-url="' + url + '" style="cursor:pointer" role="link" tabindex="0"'

            : ' data-notif-id="' + escapeHtml(n.id) + '"';



        var sevBadge = '<span class="notif-sev-badge" style="font-size:0.7rem;opacity:0.85;margin-left:6px;">' + escapeHtml(severidadeLabel(sev)) + '</span>';

        var iaBlock = (showIa && n.ia_mensagem)

            ? '<div class="ia-dica"><i class="fas fa-lightbulb"></i> <b>Dica IA:</b> ' + escapeHtml(n.ia_mensagem) + '</div>'

            : '';



        return (

            '<div class="' + itemClass + ' ' + unread + ' ' + extra + '"' + clickAttr + '>' +

                '<div class="' + iconWrapClass + ' ' + ic.cls + '">' +

                    '<i class="fas ' + ic.icon + '"></i>' +

                '</div>' +

                '<div class="' + contentClass + '">' +

                    '<div class="' + titleClass + '">' + escapeHtml(n.titulo) + sevBadge + '</div>' +

                    '<div class="' + textClass + '">' + escapeHtml(n.mensagem) + '</div>' +

                    iaBlock +

                    renderAcoes(n) +

                    '<div class="' + timeClass + '">' + escapeHtml(formatTempo(n.data_criacao)) + '</div>' +

                '</div>' +

            '</div>'

        );

    }



    function updateBadge(badgeEl, count) {

        if (!badgeEl) return;

        var n = parseInt(count, 10) || 0;

        badgeEl.textContent = String(n);

        badgeEl.style.display = n > 0 ? 'flex' : 'none';

        badgeEl.style.backgroundColor = '';

    }



    function refreshBadges() {

        var url = typeof global.sfAppUrl === 'function'

            ? global.sfAppUrl('notificacoes/contagens.php')

            : '../notificacoes/contagens.php';

        return fetch(url, { credentials: 'same-origin' })

            .then(function (res) {

                if (!res.ok) throw new Error('HTTP ' + res.status);

                return res.json();

            })

            .then(function (data) {

                if (!data.success) return data;

                updateBadge(document.getElementById('notificationBadge'), data.operacional);

                updateBadge(document.getElementById('iaNotificationBadge'), data.ia);

                return data;

            })

            .catch(function (err) {

                // Best-effort: fetch abortado (navegação) ou falha de rede — o próximo
                // ciclo re-sincroniza. Não é erro de aplicação, log discreto.
                console.debug('atualizar badges adiado:', err && err.message);

            });

    }



    function markRead(id) {

        var url = typeof global.sfAppUrl === 'function'

            ? global.sfAppUrl('notificacoes/marcar_lida.php')

            : '../notificacoes/marcar_lida.php';

        var body = new URLSearchParams();

        body.append('id', String(id));

        if (typeof global.__SF_CSRF__ === 'string' && global.__SF_CSRF__) {

            body.append('csrf_token', global.__SF_CSRF__);

        }

        return fetch(url, {

            method: 'POST',

            credentials: 'same-origin',

            headers: mutationHeaders({ 'Content-Type': 'application/x-www-form-urlencoded' }),

            body: body.toString()

        }).then(function (res) { return res.json(); });

    }



    function runAction(acao, ref, notifId) {

        var url = typeof global.sfAppUrl === 'function'

            ? global.sfAppUrl('notificacoes/acoes.php')

            : '../notificacoes/acoes.php';

        var body = new URLSearchParams();

        body.append('acao', String(acao));

        body.append('ref', String(ref));

        if (notifId) body.append('notif_id', String(notifId));

        if (typeof global.__SF_CSRF__ === 'string' && global.__SF_CSRF__) {

            body.append('csrf_token', global.__SF_CSRF__);

        }

        return fetch(url, {

            method: 'POST',

            credentials: 'same-origin',

            headers: mutationHeaders({ 'Content-Type': 'application/x-www-form-urlencoded' }),

            body: body.toString()

        }).then(function (res) { return res.json(); });

    }



    function bindItemClicks(container, onAfterMark) {

        if (!container) return;

        container.addEventListener('click', function (e) {

            if (e.target.closest('[data-notif-acao]')) return;

            var item = e.target.closest('[data-notif-id]');

            if (!item) return;

            var id = parseInt(item.getAttribute('data-notif-id'), 10);

            var url = item.getAttribute('data-notif-url');

            if (id > 0) {

                markRead(id).then(function () {

                    item.classList.remove('unread');

                    item.classList.add('lida');

                    if (typeof onAfterMark === 'function') onAfterMark();

                    refreshBadges();

                });

            }

            if (url) {

                global.location.href = url;

            }

        });

        container.addEventListener('keydown', function (e) {

            if (e.key !== 'Enter' && e.key !== ' ') return;

            var item = e.target.closest('[data-notif-id]');

            if (item && !e.target.closest('[data-notif-acao]')) item.click();

        });

    }



    global.sfNotif = {

        escapeHtml: escapeHtml,

        iconForTipo: iconForTipo,

        formatTempo: formatTempo,

        buildListUrl: buildListUrl,

        renderItem: renderItem,

        updateBadge: updateBadge,

        refreshBadges: refreshBadges,

        markRead: markRead,

        runAction: runAction,

        bindItemClicks: bindItemClicks,

        mutationHeaders: mutationHeaders

    };



    global.atualizarContadores = refreshBadges;

})(typeof window !== 'undefined' ? window : this);


