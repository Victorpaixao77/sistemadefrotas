/**
 * UI — inventário QR e utilitários mercado (gestor).
 */
window.PneusMercado = (function () {
    function headers(csrf) {
        const h = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
        if (csrf) h['X-CSRF-Token'] = csrf;
        return h;
    }

    function initInventario(opts) {
        const api = opts.apiBase;
        const csrf = opts.csrf || '';
        let sessaoId = null;

        const elSessao = document.getElementById('kpiSessao');
        const elLidos = document.getElementById('kpiLidos');
        const elStatus = document.getElementById('kpiStatus');
        const elInput = document.getElementById('qrInput');
        const elLista = document.getElementById('listaItens');
        const elLast = document.getElementById('lastScan');
        const btnNova = document.getElementById('btnNovaSessao');
        const btnFechar = document.getElementById('btnFechar');
        const btnScan = document.getElementById('btnScan');

        async function refresh() {
            if (!sessaoId) return;
            const res = await fetch(api + '?action=inventario_resumo&sessao_id=' + sessaoId, { credentials: 'same-origin' });
            const data = await res.json();
            if (!data.success) return;
            elLidos.textContent = data.total_lidos || 0;
            const itens = data.itens || [];
            if (!itens.length) {
                elLista.innerHTML = '<p style="padding:12px">Nenhum item ainda.</p>';
                return;
            }
            elLista.innerHTML = itens.map(function (i) {
                return '<div class="inv-item"><strong>' + escapeHtml(i.numero_serie) + '</strong> — ' +
                    escapeHtml((i.marca || '') + ' ' + (i.medida || '')) +
                    '<br><small>' + escapeHtml(i.lido_em) + '</small></div>';
            }).join('');
        }

        btnNova.addEventListener('click', async function () {
            const res = await fetch(api + '?action=inventario_iniciar', {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers(csrf),
                body: JSON.stringify({ titulo: 'Inventário ' + new Date().toLocaleString('pt-BR') })
            });
            const data = await res.json();
            if (!data.success) {
                alert(data.error || 'Erro ao iniciar');
                return;
            }
            sessaoId = data.sessao_id;
            elSessao.textContent = '#' + sessaoId;
            elStatus.textContent = 'Aberta';
            elInput.disabled = false;
            btnScan.disabled = false;
            btnFechar.disabled = false;
            elInput.focus();
        });

        async function scan() {
            const qr = elInput.value.trim();
            if (!qr || !sessaoId) return;
            const res = await fetch(api + '?action=inventario_scan', {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers(csrf),
                body: JSON.stringify({ sessao_id: sessaoId, qr: qr })
            });
            const data = await res.json();
            if (!data.success) {
                alert(data.error || 'Pneu não encontrado');
                return;
            }
            elLast.style.display = 'block';
            elLast.textContent = 'Registrado: ' + (data.pneu && data.pneu.numero_serie ? data.pneu.numero_serie : qr);
            elInput.value = '';
            elInput.focus();
            refresh();
        }

        btnScan.addEventListener('click', scan);
        elInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') scan(); });

        btnFechar.addEventListener('click', async function () {
            if (!sessaoId) return;
            await fetch(api + '?action=inventario_fechar', {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers(csrf),
                body: JSON.stringify({ sessao_id: sessaoId })
            });
            elStatus.textContent = 'Fechada';
            elInput.disabled = true;
            btnScan.disabled = true;
            btnFechar.disabled = true;
        });
    }

    function escapeHtml(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    return { initInventario: initInventario };
})();
