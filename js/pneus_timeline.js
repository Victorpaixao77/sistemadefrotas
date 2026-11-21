/**
 * Timeline visual do pneu + ficha completa (modal).
 */
(function (global) {
    'use strict';

    const TIPO_LABELS = {
        entrada_estoque: 'Comprado / Entrada estoque',
        instalacao: 'Instalado no veículo',
        remocao: 'Removido do veículo',
        deslocamento: 'Rodízio / Deslocamento',
        recapagem: 'Recapagem',
        manutencao: 'Manutenção / Medição',
        descarte: 'Sucateado'
    };

    function esc(s) {
        if (s == null) return '';
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fmtDate(d) {
        if (!d) return '—';
        try {
            const dt = new Date(d);
            if (isNaN(dt)) return d;
            return dt.toLocaleDateString('pt-BR') + ' ' + dt.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        } catch (e) {
            return d;
        }
    }

    function fmtBrl(v) {
        if (v == null || v === '' || Number(v) === 0) return '';
        return 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
    }

    function csrfHeaders() {
        const h = { 'Content-Type': 'application/json' };
        if (typeof global.__SF_CSRF__ === 'string' && global.__SF_CSRF__) {
            h['X-CSRF-Token'] = global.__SF_CSRF__;
        }
        return h;
    }

    function renderTimeline(container, items) {
        if (!container) return;
        if (!items || !items.length) {
            container.innerHTML = '<p class="detail-text">Nenhum evento no histórico.</p>';
            return;
        }
        const sorted = items.slice().sort(function (a, b) {
            return String(b.data_movimentacao || '').localeCompare(String(a.data_movimentacao || ''));
        });
        container.innerHTML = '<div class="pneu-timeline">' + sorted.map(function (it) {
            const tipo = it.tipo || 'manutencao';
            const title = TIPO_LABELS[tipo] || tipo;
            const parts = [];
            if (it.veiculo_placa) parts.push('Veículo ' + esc(it.veiculo_placa));
            if (it.posicao_nome) parts.push(esc(it.posicao_nome));
            if (it.km_odometro) parts.push('KM ' + esc(it.km_odometro));
            if (it.sulco_mm != null) parts.push('Sulco ' + esc(it.sulco_mm) + ' mm');
            if (it.custo > 0) parts.push(fmtBrl(it.custo));
            if (it.observacoes) parts.push(esc(it.observacoes));
            return '<div class="pneu-timeline-item tipo-' + esc(tipo) + '">' +
                '<div class="pneu-timeline-date">' + esc(fmtDate(it.data_movimentacao)) + '</div>' +
                '<div class="pneu-timeline-title">' + esc(title) + '</div>' +
                (parts.length ? '<div class="pneu-timeline-meta">' + parts.join(' · ') + '</div>' : '') +
                '</div>';
        }).join('') + '</div>';
    }

    function renderSulcoChart(container, medicoes) {
        if (!container || !medicoes || !medicoes.length) {
            if (container) container.innerHTML = '<p class="detail-text">Sem medições de sulco registradas.</p>';
            return;
        }
        const rows = medicoes.slice().reverse();
        let html = '<table class="data-table" style="width:100%;font-size:0.85rem"><thead><tr><th>Data</th><th>Sulco (mm)</th><th>KM</th><th>Veículo</th></tr></thead><tbody>';
        rows.forEach(function (m) {
            html += '<tr><td>' + esc(fmtDate(m.data_medicao)) + '</td><td><strong>' + esc(m.sulco_mm) + '</strong></td><td>' + esc(m.km_veiculo || '—') + '</td><td>' + esc(m.veiculo_placa || '—') + '</td></tr>';
        });
        html += '</tbody></table>';
        container.innerHTML = html;
    }

    async function fetchFicha(pneuId) {
        const res = await fetch('../api/pneus.php?action=ficha&pneu_id=' + encodeURIComponent(pneuId), { credentials: 'same-origin' });
        return res.json();
    }

    const TIPO_FOTO_LABELS = {
        compra: 'Compra', instalacao: 'Instalação', remocao: 'Remoção',
        manutencao: 'Manutenção', sucata: 'Sucata', inspecao: 'Inspeção', outro: 'Outro'
    };

    const TIPO_RODADO_LABELS = {
        direcional: 'Direcional', tracao: 'Tração', livre: 'Livre',
        implemento: 'Implemento', qualquer: 'Qualquer'
    };

    function renderAnexosGrid(container, anexos) {
        if (!container) return;
        if (!anexos || !anexos.length) {
            container.innerHTML = '<p class="detail-text">Nenhuma foto anexada.</p>';
            return;
        }
        container.innerHTML = '<div class="pneu-fotos-grid">' + anexos.map(function (a) {
            const url = '../' + esc(a.arquivo).replace(/^\.\.\//, '');
            const isImg = /\.(jpe?g|png|webp|gif)$/i.test(a.arquivo || '');
            const tipo = TIPO_FOTO_LABELS[a.tipo] || a.tipo;
            if (isImg) {
                return '<a class="pneu-foto-thumb" href="' + url + '" target="_blank" rel="noopener">' +
                    '<img src="' + url + '" alt="">' +
                    '<span>' + esc(tipo) + '</span></a>';
            }
            return '<a class="pneu-foto-thumb" href="' + url + '" target="_blank"><span>' + esc(tipo) + ' (arquivo)</span></a>';
        }).join('') + '</div>';
    }

    function renderGarantiasList(container, garantias) {
        if (!container) return;
        if (!garantias || !garantias.length) {
            container.innerHTML = '<p class="detail-text">Nenhuma garantia cadastrada.</p>';
            return;
        }
        let html = '<table class="data-table" style="width:100%;font-size:0.85rem"><thead><tr><th>Tipo</th><th>Fornecedor</th><th>Validade</th></tr></thead><tbody>';
        garantias.forEach(function (g) {
            html += '<tr><td>' + esc(g.tipo) + '</td><td>' + esc(g.fornecedor_nome || '—') + '</td><td>' + esc(g.validade_ate || '—') + '</td></tr>';
        });
        html += '</tbody></table>';
        container.innerHTML = html;
    }

    function renderCarcacaBlock(car, dot) {
        if (!car) return '';
        let dotHtml = '';
        if (dot) {
            const cls = dot.alerta === 'vencido' ? 'pneu-dot-vencido' : (dot.alerta === 'atencao_5anos' ? 'pneu-dot-alerta' : '');
            dotHtml = '<div class="pneu-ficha-kpi"><div class="lbl">DOT</div><div class="val ' + cls + '">Sem. ' +
                esc(dot.semana) + '/' + esc(dot.ano) + ' (' + esc(dot.idade_anos) + ' anos)</div></div>';
        }
        return '<div class="pneu-mercado-section"><h3><i class="fas fa-ring"></i> Carcaça</h3>' +
            '<div class="pneu-ficha-grid">' +
            '<div class="pneu-ficha-kpi"><div class="lbl">Código</div><div class="val">' + esc(car.codigo) + '</div></div>' +
            '<div class="pneu-ficha-kpi"><div class="lbl">Recapagens</div><div class="val">' + esc(car.recapagens) + '</div></div>' +
            '<div class="pneu-ficha-kpi"><div class="lbl">KM acumulados</div><div class="val">' + Number(car.km_acumulados || 0).toLocaleString('pt-BR') + '</div></div>' +
            '<div class="pneu-ficha-kpi"><div class="lbl">Investido</div><div class="val">R$ ' + Number(car.valor_investido || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) + '</div></div>' +
            '<div class="pneu-ficha-kpi"><div class="lbl">Status</div><div class="val">' + esc(car.status_carcaca) + '</div></div>' +
            dotHtml +
            '</div></div>';
    }

    function wireMercadoActions(pneuId, opts) {
        opts = opts || {};
        var refresh = typeof opts.onRefresh === 'function'
            ? opts.onRefresh
            : function () { openFichaModal(pneuId, opts); };
        const uploadBtn = document.getElementById('btnUploadFoto');
        const fotoInput = document.getElementById('pneuFotoInput');
        const fotoTipo = document.getElementById('pneuFotoTipo');
        if (uploadBtn && fotoInput) {
            uploadBtn.onclick = function () {
                if (!fotoInput.files || !fotoInput.files[0]) {
                    alert('Selecione uma foto');
                    return;
                }
                const fd = new FormData();
                fd.append('pneu_id', pneuId);
                fd.append('foto', fotoInput.files[0]);
                fd.append('tipo', fotoTipo ? fotoTipo.value : 'outro');
                const h = {};
                if (typeof global.__SF_CSRF__ === 'string') h['X-CSRF-Token'] = global.__SF_CSRF__;
                fetch('../api/pneus_mercado.php?action=upload_foto', {
                    method: 'POST', credentials: 'same-origin', headers: h, body: fd
                }).then(function (r) { return r.json(); }).then(function (j) {
                    if (j.success) refresh();
                    else alert(j.error || 'Erro no upload');
                });
            };
        }
        const btnGar = document.getElementById('btnAddGarantia');
        if (btnGar) {
            btnGar.onclick = function () {
                const payload = {
                    pneu_id: pneuId,
                    tipo: document.getElementById('garTipo').value,
                    fornecedor_nome: document.getElementById('garFornecedor').value,
                    validade_ate: document.getElementById('garValidade').value || null
                };
                fetch('../api/pneus_mercado.php?action=garantia', {
                    method: 'POST', credentials: 'same-origin', headers: csrfHeaders(),
                    body: JSON.stringify(payload)
                }).then(function (r) { return r.json(); }).then(function (j) {
                    if (j.success) refresh();
                    else alert(j.error || 'Erro');
                });
            };
        }
        const btnSuc = document.getElementById('btnRegistrarSucata');
        if (btnSuc) {
            btnSuc.onclick = function () {
                if (!confirm('Registrar sucata? O pneu será marcado como descartado.')) return;
                const payload = {
                    pneu_id: pneuId,
                    motivo_remocao_id: parseInt(document.getElementById('sucMotivo').value, 10) || null,
                    valor_recuperado: parseFloat(document.getElementById('sucValor').value) || null,
                    destino: document.getElementById('sucDestino').value,
                    observacoes: document.getElementById('sucObs').value
                };
                fetch('../api/pneus_mercado.php?action=sucata', {
                    method: 'POST', credentials: 'same-origin', headers: csrfHeaders(),
                    body: JSON.stringify(payload)
                }).then(function (r) { return r.json(); }).then(function (j) {
                    if (j.success) {
                        alert('Sucata registrada.');
                        refresh();
                    } else alert(j.error || 'Erro');
                });
            };
        }
        const btnTrans = document.getElementById('btnTransferirFilial');
        if (btnTrans) {
            btnTrans.onclick = function () {
                const fid = document.getElementById('transFilial').value;
                if (!fid) { alert('Selecione a filial'); return; }
                fetch('../api/pneus_mercado.php?action=transferir_filial', {
                    method: 'POST', credentials: 'same-origin', headers: csrfHeaders(),
                    body: JSON.stringify({ pneu_id: pneuId, filial_id: parseInt(fid, 10) })
                }).then(function (r) { return r.json(); }).then(function (j) {
                    if (j.success) refresh();
                    else alert(j.error || 'Erro');
                });
            };
        }
    }

    async function loadMercadoCatalogos() {
        try {
            const res = await fetch('../api/pneus_mercado.php?action=catalogos', { credentials: 'same-origin' });
            return await res.json();
        } catch (e) {
            return { motivos_remocao: [], filiais: [] };
        }
    }

    function buildMercadoHtml(mercado, catalogos, p) {
        const car = mercado.carcaca || null;
        const dot = mercado.dot || null;
        const anexos = mercado.anexos || [];
        const garantias = mercado.garantias || [];
        const motivos = (catalogos && catalogos.motivos_remocao) ? catalogos.motivos_remocao : [];
        const filiais = (catalogos && catalogos.filiais) ? catalogos.filiais : [];
        const tipoRodado = TIPO_RODADO_LABELS[p.tipo_rodado_ideal] || p.tipo_rodado_ideal || '—';

        let fotoTipoOpts = '';
        Object.keys(TIPO_FOTO_LABELS).forEach(function (k) {
            fotoTipoOpts += '<option value="' + k + '">' + TIPO_FOTO_LABELS[k] + '</option>';
        });
        let motOpts = '<option value="">—</option>';
        motivos.forEach(function (m) {
            motOpts += '<option value="' + m.id + '">' + esc(m.nome) + '</option>';
        });
        let filOpts = '<option value="">—</option>';
        filiais.forEach(function (f) {
            filOpts += '<option value="' + f.id + '">' + esc(f.nome) + '</option>';
        });

        const html = renderCarcacaBlock(car, dot) +
            (p.tipo_rodado_ideal && p.tipo_rodado_ideal !== 'qualquer'
                ? '<p><strong>Posição ideal:</strong> ' + esc(tipoRodado) + '</p>' : '') +
            '<div class="pneu-mercado-section"><h3><i class="fas fa-camera"></i> Fotos e laudos</h3>' +
            '<div id="pneuAnexosGrid"></div>' +
            '<div class="pneu-mercado-form">' +
            '<div><label>Tipo</label><select id="pneuFotoTipo">' + fotoTipoOpts + '</select></div>' +
            '<div><label>Arquivo</label><input type="file" id="pneuFotoInput" accept="image/*,.pdf"></div>' +
            '<div><button type="button" class="btn btn-secondary btn-sm" id="btnUploadFoto">Enviar foto</button></div>' +
            '</div></div>' +
            '<div class="pneu-mercado-section"><h3><i class="fas fa-shield-alt"></i> Garantias</h3>' +
            '<div id="pneuGarantiasList"></div>' +
            '<div class="pneu-mercado-form">' +
            '<div><label>Tipo</label><select id="garTipo"><option value="fabricante">Fabricante</option><option value="revenda">Revenda</option><option value="recapadora">Recapadora</option></select></div>' +
            '<div><label>Fornecedor</label><input type="text" id="garFornecedor" placeholder="Nome"></div>' +
            '<div><label>Validade</label><input type="date" id="garValidade"></div>' +
            '<div><button type="button" class="btn btn-secondary btn-sm" id="btnAddGarantia">Adicionar</button></div>' +
            '</div></div>' +
            '<div class="pneu-mercado-section"><h3><i class="fas fa-trash-alt"></i> Sucata</h3>' +
            '<p class="detail-text">Registre quando o pneu sair de operação definitivamente.</p>' +
            '<div class="pneu-mercado-form">' +
            '<div><label>Motivo</label><select id="sucMotivo">' + motOpts + '</select></div>' +
            '<div><label>Valor recuperado</label><input type="number" step="0.01" id="sucValor" min="0"></div>' +
            '<div><label>Destino</label><input type="text" id="sucDestino" placeholder="Ex.: Reciclador"></div>' +
            '<div style="grid-column:1/-1"><label>Obs.</label><input type="text" id="sucObs"></div>' +
            '<div><button type="button" class="btn btn-danger btn-sm" id="btnRegistrarSucata">Registrar sucata</button></div>' +
            '</div></div>' +
            (filiais.length ? '<div class="pneu-mercado-section"><h3><i class="fas fa-building"></i> Transferir filial</h3>' +
            '<div class="pneu-mercado-form">' +
            '<div><label>Filial destino</label><select id="transFilial">' + filOpts + '</select></div>' +
            '<div><button type="button" class="btn btn-secondary btn-sm" id="btnTransferirFilial">Transferir</button></div>' +
            '</div></div>' : '');

        return { html: html, anexos: anexos, garantias: garantias };
    }

    async function openFichaModal(pneuId, opts) {
        opts = opts || {};
        const modalId = opts.modalId || 'pneuFichaModal';
        let modal = document.getElementById(modalId);
        if (!modal) {
            modal = document.createElement('div');
            modal.id = modalId;
            modal.className = 'modal';
            modal.innerHTML = '<div class="modal-content" style="max-width:780px;max-height:90vh;overflow:auto">' +
                '<div class="modal-header"><h2 id="pneuFichaTitle">Ficha do pneu</h2>' +
                '<button type="button" class="close-modal" aria-label="Fechar">&times;</button></div>' +
                '<div class="modal-body" id="pneuFichaBody"><p>Carregando…</p></div></div>';
            document.body.appendChild(modal);
            modal.querySelector('.close-modal').onclick = function () { modal.style.display = 'none'; };
            modal.addEventListener('click', function (e) { if (e.target === modal) modal.style.display = 'none'; });
        }
        modal.style.display = 'flex';
        const body = document.getElementById('pneuFichaBody');
        body.innerHTML = '<p><i class="fas fa-spinner fa-spin"></i> Carregando ficha…</p>';

        try {
            const json = await fetchFicha(pneuId);
            if (!json.success || !json.data) {
                body.innerHTML = '<p>Erro ao carregar ficha.</p>';
                return;
            }
            const d = json.data;
            const p = d.pneu || {};
            document.getElementById('pneuFichaTitle').textContent = 'Pneu ' + (p.numero_serie || pneuId);

            const ck = d.custo_km || {};
            const prev = d.previsao_sulco || {};
            const qrUrl = json.data.qr_url || '';
            const mercado = d.mercado || {};
            const catalogos = await loadMercadoCatalogos();
            const mercadoBuilt = buildMercadoHtml(mercado, catalogos, p);

            body.innerHTML =
                '<div class="pneu-ficha-grid">' +
                '<div class="pneu-ficha-kpi"><div class="lbl">Marca / Modelo</div><div class="val">' + esc(p.marca) + ' ' + esc(p.modelo) + '</div></div>' +
                '<div class="pneu-ficha-kpi"><div class="lbl">Medida</div><div class="val">' + esc(p.medida || '—') + '</div></div>' +
                '<div class="pneu-ficha-kpi"><div class="lbl">Sulco atual</div><div class="val">' + (d.sulco_atual_mm != null ? d.sulco_atual_mm + ' mm' : '—') + '</div></div>' +
                '<div class="pneu-ficha-kpi"><div class="lbl">Custo/km</div><div class="val">' + (ck.custo_km != null ? ('R$ ' + Number(ck.custo_km).toFixed(4)) : '—') + '</div></div>' +
                '<div class="pneu-ficha-kpi"><div class="lbl">KM rodado</div><div class="val">' + esc(ck.km_rodado || p.km_rodado_total || '—') + '</div></div>' +
                '<div class="pneu-ficha-kpi"><div class="lbl">Recapagens</div><div class="val">' + esc(p.numero_recapagens || 0) + '</div></div>' +
                '</div>' +
                (d.veiculo_placa ? '<p><strong>Veículo atual:</strong> ' + esc(d.veiculo_placa) + '</p>' : '') +
                (prev.dias_restantes != null ? '<p><strong>Previsão troca (sulco):</strong> ~' + prev.dias_restantes + ' dias / ' + (prev.km_restantes || '?') + ' km</p>' : '') +
                mercadoBuilt.html +
                '<div style="display:flex;flex-wrap:wrap;gap:8px;margin:12px 0">' +
                '<button type="button" class="btn btn-secondary btn-sm" id="btnMedirSulco">Medir sulco</button>' +
                '<button type="button" class="btn btn-secondary btn-sm" id="btnSyncKm">Sync KM veículo</button>' +
                '<button type="button" class="btn btn-secondary btn-sm" id="btnRecapagem">Registrar recapagem</button>' +
                '<button type="button" class="btn btn-primary btn-sm" id="btnGerarQr">QR Code</button>' +
                '</div>' +
                '<div id="pneuQrArea" class="pneu-qr-wrap" style="display:none"></div>' +
                '<h3>Medições de sulco (TWI)</h3><div id="pneuSulcoTable"></div>' +
                '<h3>Histórico visual</h3><div id="pneuTimelineVis"></div>';

            renderSulcoChart(document.getElementById('pneuSulcoTable'), d.medicoes_sulco);
            renderTimeline(document.getElementById('pneuTimelineVis'), d.timeline);
            renderAnexosGrid(document.getElementById('pneuAnexosGrid'), mercadoBuilt.anexos);
            renderGarantiasList(document.getElementById('pneuGarantiasList'), mercadoBuilt.garantias);
            wireMercadoActions(pneuId, opts);

            document.getElementById('btnMedirSulco').onclick = function () {
                const mm = prompt('Profundidade do sulco (mm):');
                if (!mm || isNaN(parseFloat(mm))) return;
                fetch('../api/pneus_acoes.php?action=registrar_sulco', {
                    method: 'POST', credentials: 'same-origin', headers: csrfHeaders(),
                    body: JSON.stringify({ pneu_id: pneuId, sulco_mm: parseFloat(mm) })
                }).then(function (r) { return r.json(); }).then(function (j) {
                    if (j.success) openFichaModal(pneuId, opts);
                    else alert(j.error || 'Erro ao registrar sulco');
                });
            };
            document.getElementById('btnSyncKm').onclick = function () {
                fetch('../api/pneus_acoes.php?action=sync_km', {
                    method: 'POST', credentials: 'same-origin', headers: csrfHeaders(),
                    body: JSON.stringify({ pneu_id: pneuId })
                }).then(function (r) { return r.json(); }).then(function (j) {
                    alert(j.success ? ('KM sincronizado: ' + (j.km_rodado || 0) + ' km rodados') : (j.error || 'Erro'));
                    if (j.success) openFichaModal(pneuId, opts);
                });
            };
            document.getElementById('btnRecapagem').onclick = function () {
                const custo = prompt('Custo da recapagem (R$):');
                if (!custo || isNaN(parseFloat(custo))) return;
                const sulco = prompt('Sulco após recapagem (mm, opcional):');
                const payload = { pneu_id: pneuId, custo: parseFloat(custo) };
                if (sulco && !isNaN(parseFloat(sulco))) payload.sulco_pos_recapagem = parseFloat(sulco);
                fetch('../api/pneus_acoes.php?action=registrar_recapagem', {
                    method: 'POST', credentials: 'same-origin', headers: csrfHeaders(),
                    body: JSON.stringify(payload)
                }).then(function (r) { return r.json(); }).then(function (j) {
                    if (j.success) openFichaModal(pneuId, opts);
                    else alert(j.error || 'Erro');
                });
            };
            document.getElementById('btnGerarQr').onclick = function () {
                fetch('../api/pneus_acoes.php?action=gerar_qr', {
                    method: 'POST', credentials: 'same-origin', headers: csrfHeaders(),
                    body: JSON.stringify({ pneu_id: pneuId })
                }).then(function (r) { return r.json(); }).then(function (j) {
                    if (!j.success) { alert(j.error || 'Erro'); return; }
                    const area = document.getElementById('pneuQrArea');
                    area.style.display = 'block';
                    const imgUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(j.url);
                    area.innerHTML = '<img src="' + imgUrl + '" alt="QR Code"><p style="font-size:0.8rem;word-break:break-all">' + esc(j.url) + '</p>';
                });
            };
            if (qrUrl && opts.showQr) {
                document.getElementById('btnGerarQr').click();
            }
        } catch (e) {
            body.innerHTML = '<p>Erro ao carregar.</p>';
            console.error(e);
        }
    }

    global.PneuTimeline = {
        render: renderTimeline,
        renderSulcoChart: renderSulcoChart,
        renderAnexosGrid: renderAnexosGrid,
        renderGarantiasList: renderGarantiasList,
        buildMercadoHtml: buildMercadoHtml,
        loadMercadoCatalogos: loadMercadoCatalogos,
        wireMercadoActions: wireMercadoActions,
        openFichaModal: openFichaModal,
        TIPO_LABELS: TIPO_LABELS
    };
})(typeof window !== 'undefined' ? window : this);
