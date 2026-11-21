/**
 * Página dedicada da ficha do pneu (QR / link direto).
 */
(function (global) {
    'use strict';

    function csrfHeaders() {
        var h = { 'Content-Type': 'application/json' };
        if (typeof global.__SF_CSRF__ === 'string' && global.__SF_CSRF__) {
            h['X-CSRF-Token'] = global.__SF_CSRF__;
        }
        return h;
    }

    function apiUrl(path) {
        if (typeof global.sfApiUrl === 'function') {
            return global.sfApiUrl(path);
        }
        return '../api/' + path;
    }

    function reloadPage() {
        global.location.reload();
    }

    function wireQuickActions(pneuId) {
        var btnMedir = document.getElementById('btnMedirSulco');
        var btnSync = document.getElementById('btnSyncKm');
        var btnRecap = document.getElementById('btnRecapagem');
        var btnQr = document.getElementById('btnGerarQr');

        if (btnMedir) {
            btnMedir.addEventListener('click', function () {
                var mm = global.prompt('Profundidade do sulco (mm):');
                if (!mm || isNaN(parseFloat(mm))) return;
                fetch(apiUrl('pneus_acoes.php?action=registrar_sulco'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: csrfHeaders(),
                    body: JSON.stringify({ pneu_id: pneuId, sulco_mm: parseFloat(mm) })
                }).then(function (r) { return r.json(); }).then(function (j) {
                    if (j.success) reloadPage();
                    else global.alert(j.error || 'Erro ao registrar sulco');
                });
            });
        }

        if (btnSync) {
            btnSync.addEventListener('click', function () {
                fetch(apiUrl('pneus_acoes.php?action=sync_km'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: csrfHeaders(),
                    body: JSON.stringify({ pneu_id: pneuId })
                }).then(function (r) { return r.json(); }).then(function (j) {
                    global.alert(j.success ? ('KM sincronizado: ' + (j.km_rodado || 0) + ' km rodados') : (j.error || 'Erro'));
                    if (j.success) reloadPage();
                });
            });
        }

        if (btnRecap) {
            btnRecap.addEventListener('click', function () {
                var custo = global.prompt('Custo da recapagem (R$):');
                if (!custo || isNaN(parseFloat(custo))) return;
                var sulco = global.prompt('Sulco após recapagem (mm, opcional):');
                var payload = { pneu_id: pneuId, custo: parseFloat(custo) };
                if (sulco && !isNaN(parseFloat(sulco))) payload.sulco_pos_recapagem = parseFloat(sulco);
                fetch(apiUrl('pneus_acoes.php?action=registrar_recapagem'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: csrfHeaders(),
                    body: JSON.stringify(payload)
                }).then(function (r) { return r.json(); }).then(function (j) {
                    if (j.success) reloadPage();
                    else global.alert(j.error || 'Erro');
                });
            });
        }

        if (btnQr) {
            btnQr.addEventListener('click', function () {
                fetch(apiUrl('pneus_acoes.php?action=gerar_qr'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: csrfHeaders(),
                    body: JSON.stringify({ pneu_id: pneuId })
                }).then(function (r) { return r.json(); }).then(function (j) {
                    if (!j.success) { global.alert(j.error || 'Erro'); return; }
                    var area = document.getElementById('pneuQrArea');
                    if (!area) return;
                    area.style.display = 'block';
                    var imgUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(j.url);
                    area.innerHTML = '<img src="' + imgUrl + '" alt="QR Code" width="200" height="200">' +
                        '<p style="font-size:0.8rem;word-break:break-all;margin-top:0.5rem">' + String(j.url).replace(/</g, '&lt;') + '</p>';
                });
            });
        }
    }

    async function initFichaPage(cfg) {
        if (!cfg || !cfg.pneuId || !global.PneuTimeline) return;
        var pt = global.PneuTimeline;
        var ficha = cfg.ficha || {};

        pt.renderSulcoChart(document.getElementById('sulcoChart'), ficha.medicoes_sulco || []);
        pt.render(document.getElementById('timelineVis'), ficha.timeline || []);

        var mercado = ficha.mercado || {};
        var p = ficha.pneu || {};
        var catalogos = await pt.loadMercadoCatalogos();
        var built = pt.buildMercadoHtml(mercado, catalogos, p);
        var mercadoHost = document.getElementById('pneuMercadoHost');
        if (mercadoHost && built.html) {
            mercadoHost.innerHTML = built.html;
            pt.renderAnexosGrid(document.getElementById('pneuAnexosGrid'), built.anexos);
            pt.renderGarantiasList(document.getElementById('pneuGarantiasList'), built.garantias);
            pt.wireMercadoActions(cfg.pneuId, { onRefresh: reloadPage });
        }

        wireQuickActions(cfg.pneuId);

        if (cfg.showQr && document.getElementById('btnGerarQr')) {
            document.getElementById('btnGerarQr').click();
        }
    }

    global.PneuFichaPage = { init: initFichaPage };
})(typeof window !== 'undefined' ? window : this);
