/* =====================================================================
   Gestão Interativa Pro — interação, estágio SVG e ações de pneu.
   Reaproveita: gestao_interativa/api/eixos_veiculos.php (layout/mover/remover/
   manutenção) e api/gestao_pneu_pro.php (kpis/detalhe/saúde/histórico/alertas/
   sulco/calibragem).
   ===================================================================== */
(function () {
    'use strict';

    var EIXOS_API = '../gestao_interativa/api/eixos_veiculos.php';
    var LAYOUT_JSON_API = '../gestao_interativa/api/layout_flexivel.php';
    function proApi(action) {
        var base = (typeof sfApiUrl === 'function') ? sfApiUrl('gestao_pneu_pro.php') : '../api/gestao_pneu_pro.php';
        return base + '?action=' + action;
    }

    // ---- Estado ----
    var vehId = null;
    var layout = { eixosCaminhao: [], eixosCarreta: [] };
    var allocated = {};   // slotId -> tire {id, numero_serie, marca, modelo, medida, status_id, status_nome}
    var healthMap = {};   // pneuId -> { sulco_atual, vida, health{cor,nivel,rotulo} }
    var curPneuId = null;
    var curSlot = null;
    var viewMode = 'all';      // 'all' | 'cavalo' | 'carreta'
    // ---- Modo montagem (arrastar eixos) ----
    var assemblyMode = false;
    var customAxleXs = null;   // posições custom dos eixos (null = automático)
    var customTankX = null;    // posição custom do tanque (null = automático)
    var pendingMontagem = null; // montagem salva a aplicar no 1º render (evita flash)
    var stageZoom = 1;          // zoom do estágio (1 = ajustado à largura)
    var compareMode = false;    // aguardando 2º pneu para comparar
    var compareA = null;        // pneu_id do 1º selecionado para comparar
    var axleOrder = [];        // chave data-axle por índice (alinha com customAxleXs)
    var axleIds = [];          // id do eixo_veiculo por índice (para salvar/carregar)
    var lastBuilt = null;      // { width, height, pad, tankX } do último build
    var filterSomenteVazios = false; // legenda "Sem pneu" — destaca só posições vazias

    // ---- Helpers ----
    function el(id) { return document.getElementById(id); }
    function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    function money(n) { var v = Number(n); return isNaN(v) ? '—' : 'R$ ' + v.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function num(n, suf) { if (n == null || n === '') return '—'; var v = Number(n); return (isNaN(v) ? n : v.toLocaleString('pt-BR')) + (suf || ''); }
    function dateBr(s) { if (!s) return '—'; var d = new Date(String(s).replace(' ', 'T')); return isNaN(d) ? '—' : d.toLocaleDateString('pt-BR'); }
    function mutHeaders() {
        var h = (typeof sfMutationHeaders === 'function') ? sfMutationHeaders({ 'Content-Type': 'application/json' }) : { 'Content-Type': 'application/json' };
        if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__ && !h['X-CSRF-Token']) h['X-CSRF-Token'] = window.__SF_CSRF__;
        return h;
    }
    function gipResolveUrl(rel) {
        rel = String(rel || '').replace(/^\//, '');
        try { return new URL(rel, window.location.href).href; }
        catch (e) { return rel; }
    }
    function getJson(url) {
        return fetch(url, { credentials: 'include' }).then(function (r) {
            return r.text().then(function (text) {
                var data;
                try { data = text ? JSON.parse(text) : null; }
                catch (e) { throw new Error('Resposta inválida do servidor.'); }
                if (!r.ok && !(data && data.success)) {
                    throw new Error((data && (data.error || data.message)) || ('HTTP ' + r.status));
                }
                return data;
            });
        });
    }
    function postJson(url, body) {
        return fetch(url, { method: 'POST', credentials: 'include', headers: mutHeaders(), body: JSON.stringify(body) })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); });
    }
    function toast(msg, type) {
        var t = el('gipToast'); if (!t) return;
        t.textContent = msg; t.className = 'gip-toast show ' + (type || '');
        clearTimeout(t._t); t._t = setTimeout(function () { t.className = 'gip-toast'; }, 2600);
    }

    // ---- Carregamento inicial ----
    function lsGet(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
    function lsSet(k, v) { try { localStorage.setItem(k, v == null ? '' : v); } catch (e) {} }

    function loadVehicles() {
        getJson(proApi('vehicles')).then(function (res) {
            var sel = el('gipVeiculo'); if (!sel || !res || !res.success) { loadFleetKpis(); return; }
            sel.innerHTML = '<option value="">Selecione um veículo…</option>' +
                (res.data || []).map(function (v) {
                    var label = (v.placa || ('Veículo ' + v.id)) + (v.modelo ? ' · ' + v.modelo : '');
                    if (v.tem_montagem) label = '★ ' + label + ' (montado)';
                    return '<option value="' + v.id + '">' + esc(label) + '</option>';
                }).join('');
            // Restaura o último veículo selecionado (se ainda existir na lista).
            var saved = lsGet('gip_veh');
            if (saved && sel.querySelector('option[value="' + saved + '"]')) {
                sel.value = saved;
                selectVehicle(saved);     // carrega os KPIs já no escopo do veículo (uma vez)
            } else {
                loadFleetKpis();          // sem veículo selecionado → KPIs da frota
            }
        }).catch(function () { toast('Falha ao carregar a lista de veículos.', 'error'); loadFleetKpis(); });
    }

    function setKpiScope() {
        var sc = el('gipKpiScope'); if (!sc) return;
        if (vehId) sc.innerHTML = '<i class="fas fa-truck"></i> Indicadores do veículo: <b>' + esc(vehName() || ('#' + vehId)) + '</b>';
        else sc.innerHTML = '<i class="fas fa-layer-group"></i> Indicadores da <b>frota toda</b>';
        var hint = el('kpiTotalHint');
        if (hint) hint.textContent = vehId ? 'No veículo' : 'Na frota';
    }
    // Carrega KPIs no escopo certo: veículo selecionado ou frota.
    function loadFleetKpis() {
        setKpiScope();
        var url = vehId ? (proApi('vehicle_kpis') + '&veiculo_id=' + vehId) : proApi('fleet_kpis');
        getJson(url).then(function (res) {
            if (!res || !res.success) return; var k = res.data;
            el('kpiTotal').textContent = num(k.total);
            el('kpiUso').textContent = num(k.em_uso);
            el('kpiAlerta').textContent = num(k.em_alerta);
            el('kpiCritico').textContent = num(k.criticos);
            el('kpiVida').textContent = (k.vida_media != null ? k.vida_media : '—');
            el('kpiCusto').textContent = k.custo_total ? money(k.custo_total) : '—';
            el('kpiCustoKm').textContent = k.custo_km ? money(k.custo_km) : '—';
            el('kpiSemMed').textContent = num(k.sem_medicao);
        }).catch(function () { toast('Falha ao carregar os indicadores.', 'error'); });
    }

    var layoutLoadError = '';

    function parseLayoutFromApi(data) {
        if (!data || !data.success || !data.layout) return null;
        var cav = data.layout.eixosCaminhao || [];
        var car = data.layout.eixosCarreta || [];
        if (!cav.length && !car.length) return null;
        return {
            eixosCaminhao: cav,
            eixosCarreta: car,
            allocated: data.layout.pneusFlexAlocados || {}
        };
    }

    function fetchLayoutJsonFallback(vehId) {
        return getJson(gipResolveUrl(LAYOUT_JSON_API + '?veiculo_id=' + encodeURIComponent(vehId)));
    }

    function fetchLayoutFromDb(vehId) {
        return getJson(gipResolveUrl(EIXOS_API + '?action=layout_completo&veiculo_id=' + encodeURIComponent(vehId)));
    }

    /** Banco (eixos_veiculos) + fallback JSON legado (layout_flexivel.php), igual à versão clássica. */
    function loadVehicleLayout(vehId) {
        return fetchLayoutFromDb(vehId).then(function (data) {
            var parsed = parseLayoutFromApi(data);
            if (parsed) return { parsed: parsed, error: '' };
            var apiErr = (data && data.error) ? String(data.error) : '';
            return fetchLayoutJsonFallback(vehId).then(function (fb) {
                var parsedFb = parseLayoutFromApi(fb);
                if (parsedFb) return { parsed: parsedFb, error: '' };
                return { parsed: null, error: apiErr || (fb && fb.error ? String(fb.error) : '') };
            });
        }).catch(function (err) {
            return fetchLayoutJsonFallback(vehId).then(function (fb) {
                var parsedFb = parseLayoutFromApi(fb);
                if (parsedFb) return { parsed: parsedFb, error: '' };
                var msg = (typeof sfSafeErrorMessage === 'function')
                    ? sfSafeErrorMessage(err, 'Falha ao carregar layout do veículo')
                    : (err && err.message ? err.message : 'Falha ao carregar layout do veículo');
                return { parsed: null, error: msg };
            }).catch(function () {
                var msg = (typeof sfSafeErrorMessage === 'function')
                    ? sfSafeErrorMessage(err, 'Falha ao carregar layout do veículo')
                    : 'Falha ao carregar layout do veículo';
                return { parsed: null, error: msg };
            });
        });
    }

    // ---- Seleção de veículo ----
    function selectVehicle(id) {
        vehId = id ? parseInt(id, 10) : null;
        lsSet('gip_veh', vehId || '');
        curPneuId = null; curSlot = null;
        customAxleXs = null; customTankX = null;   // novo veículo: volta ao automático
        pendingMontagem = null;
        layoutLoadError = '';
        renderPanelEmpty();
        renderTimelineEmpty();
        setStageTitle();
        loadFleetKpis();   // KPIs no escopo do veículo (ou frota, se deselecionar)
        if (!vehId) { el('gipStage').innerHTML = ''; el('gipStageMeta').textContent = ''; renderAlerts([]); return; }
        el('gipStage').innerHTML = '<div class="gip-tl-empty" style="padding:40px">Carregando estágio…</div>';

        // Carrega layout e montagem em paralelo e só desenha uma vez (sem piscar).
        Promise.all([
            loadVehicleLayout(vehId),
            getJson(proApi('get_montagem') + '&veiculo_id=' + vehId).catch(function () { return null; })
        ]).then(function (arr) {
            var res = arr[0], m = arr[1];
            if (res.parsed) {
                layout = { eixosCaminhao: res.parsed.eixosCaminhao, eixosCarreta: res.parsed.eixosCarreta };
                allocated = res.parsed.allocated;
            } else {
                layout = { eixosCaminhao: [], eixosCarreta: [] };
                allocated = {};
                layoutLoadError = res.error || '';
            }
            // Guarda a montagem para o renderStage aplicar já no primeiro desenho.
            pendingMontagem = (m && m.success && m.data) ? m.data : null;
            renderStage();
            loadHealthAndColor();
            loadAlertas();
        });
    }
    function setStageTitle() {
        var t = el('gipStageTitle'); if (!t) return;
        t.textContent = vehId ? ('Estágio · ' + (vehName() || ('Veículo #' + vehId))) : 'Estágio do veículo';
    }

    function loadHealthAndColor() {
        getJson(proApi('tires_health') + '&veiculo_id=' + vehId).then(function (res) {
            healthMap = (res && res.success) ? (res.data || {}) : {};
            applyTireColors();
            applyFilters();
        }).catch(function () { applyTireColors(); });
    }

    // ---- Render do estágio (motor SVG premium industrial) ----
    function gipEllipsePath(cx, cy, rx, ry) {
        return 'M' + (cx - rx).toFixed(2) + ',' + cy.toFixed(2) +
            ' a' + rx.toFixed(2) + ',' + ry.toFixed(2) + ' 0 1,1 ' + (rx * 2).toFixed(2) + ',0' +
            ' a' + rx.toFixed(2) + ',' + ry.toFixed(2) + ' 0 1,1 ' + (-rx * 2).toFixed(2) + ',0';
    }

    function gipRoundRectPath(x, y, w, h, r) {
        if (window.GipStagePremium && window.GipStagePremium.pathRoundRect) {
            return window.GipStagePremium.pathRoundRect(x, y, w, h, r);
        }
        r = Math.min(r || 4, w / 2, h / 2);
        return 'M' + (x + r).toFixed(2) + ',' + y.toFixed(2) +
            ' h' + (w - 2 * r).toFixed(2) +
            ' q' + r + ' 0 ' + r + ' ' + r +
            ' v' + (h - 2 * r).toFixed(2) +
            ' q0 ' + r + ' ' + (-r) + ' ' + r +
            ' h' + (-(w - 2 * r)).toFixed(2) +
            ' q' + (-r) + ' 0 ' + (-r) + ' ' + (-r) +
            ' v' + (-(h - 2 * r)).toFixed(2) +
            ' q0 ' + (-r) + ' ' + r + ' ' + (-r) + ' Z';
    }

    function renderStage() {
        var stage = el('gipStage');
        var allCav = layout.eixosCaminhao || [], allCar = layout.eixosCarreta || [];
        var cav = (viewMode === 'carreta') ? [] : allCav;
        var car = (viewMode === 'cavalo') ? [] : allCar;
        var totalEixos = cav.length + car.length;
        if (!totalEixos && (allCav.length + allCar.length)) {
            // o conjunto existe, mas a visão atual ficou vazia
            stage.innerHTML = '<div class="gip-tl-empty" style="padding:46px 16px">' +
                '<i class="fas fa-truck-ramp-box" style="font-size:2rem;opacity:.5;display:block;margin-bottom:10px"></i>' +
                'Este veículo não tem eixos no <b>' + (viewMode === 'cavalo' ? 'cavalo' : 'carreta') + '</b>.' +
                '</div>';
            el('gipStageMeta').textContent = '';
            return;
        }
        if (!totalEixos) {
            var errBlock = layoutLoadError
                ? '<p style="margin-top:10px;font-size:.84rem;color:var(--text-secondary,#94a3b8)"><i class="fas fa-circle-info"></i> ' + esc(layoutLoadError) + '</p>'
                : '';
            stage.innerHTML = '<div class="gip-tl-empty" style="padding:46px 16px">' +
                '<i class="fas fa-traffic-cone" style="font-size:2rem;opacity:.5;display:block;margin-bottom:10px"></i>' +
                'Este veículo ainda não tem eixos configurados.<br>Use o menu <b>Eixos → Adicionar eixo</b> para começar.' +
                '<div style="margin-top:14px;display:flex;gap:8px;justify-content:center;flex-wrap:wrap">' +
                '<button type="button" class="gip-btn gip-btn--sm gip-btn--primary" id="gipEmptyAddCav"><i class="fas fa-plus"></i> Adicionar eixo</button>' +
                '</div>' + errBlock + '</div>';
            el('gipStageMeta').textContent = '';
            var addBtn = el('gipEmptyAddCav'); if (addBtn) addBtn.addEventListener('click', function () { adicionarEixo('caminhao'); });
            return;
        }

        if (typeof window.GipStagePremium === 'undefined') {
            stage.innerHTML = '<div class="gip-tl-empty" style="padding:40px">Motor visual premium não carregado. Verifique <code>gip-stage-premium.js</code>.</div>';
            return;
        }

        var axles = [];
        cav.forEach(function (e, i) { axles.push({ prefix: 'cavalo', unit: 'cavalo', eixoIdx: i, pneus: e.pneus || 2, id: e.id }); });
        car.forEach(function (e, i) { axles.push({ prefix: 'carreta', unit: 'carreta', eixoIdx: i, pneus: e.pneus || 4, id: e.id }); });
        axleOrder = axles.map(function (a) { return a.prefix + '-' + a.eixoIdx; });
        axleIds = axles.map(function (a) { return a.id; });

        // Aplica a montagem salva já no primeiro desenho (evita o "flash" de posição automática).
        if (pendingMontagem && viewMode === 'all' && customAxleXs == null && customTankX == null) {
            applyMontagem(pendingMontagem);
        }
        pendingMontagem = null;

        var dark = !document.documentElement.classList.contains('light-theme');
        var buildOpts = {
            axles: axles,
            cavCount: cav.length,
            carCount: car.length,
            darkTheme: dark,
            renderTire: tireSvg
        };
        if (viewMode === 'all' && customAxleXs && customAxleXs.length === axles.length) buildOpts.axleXs = customAxleXs;
        if (viewMode === 'all' && customTankX != null) buildOpts.tankX = customTankX;
        var built = window.GipStagePremium.build(buildOpts);
        if (viewMode === 'all' && built.axleXs) customAxleXs = built.axleXs.slice();
        lastBuilt = { width: built.width, height: built.height, pad: built.pad || 20, tankX: built.tankX };

        stage.innerHTML = '<svg class="gip-stage-svg gsp-stage' + (assemblyMode ? ' gsp-assembly' : '') + '" viewBox="0 0 ' + built.width + ' ' + built.height + '" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Vista superior do veículo">' +
            built.markup + '</svg>';
        var totalPos = axles.reduce(function (s, a) { return s + (a.pneus || 0); }, 0);
        var nPneus = axles.reduce(function (s, a) {
            for (var i = 0; i < (a.pneus || 0); i++) { if (allocated[a.prefix + '-' + a.eixoIdx + '-' + i]) s++; }
            return s;
        }, 0);
        var vagas = Math.max(0, totalPos - nPneus);
        el('gipStageMeta').textContent = totalEixos + ' eixo(s) · cavalo ' + cav.length + ' · carreta ' + car.length +
            ' · ' + nPneus + ' pneu(s)' + (vagas ? ' · ' + vagas + ' vaga(s)' : '');
        applyTireColors();
        applyFilters();   // mantém o filtro ativo (KPI/seletor) após qualquer re-render
        applyZoom();      // mantém o nível de zoom após qualquer re-render
    }
    function applyZoom() {
        var svg = el('gipStage') && el('gipStage').querySelector('svg'); if (!svg) return;
        svg.style.minWidth = '0';
        svg.style.width = Math.round(stageZoom * 100) + '%';
    }
    function setZoom(z) {
        stageZoom = Math.max(0.5, Math.min(2.5, z));
        applyZoom();
    }

    function tireSvg(slotId, x, y, w, h, meta) {
        meta = meta || {};
        var cx = x + w / 2, cy = y + h / 2;
        var tireAtSlot = allocated[slotId];
        var hitLabel = tireAtSlot ? ('Pneu ' + esc(tireAtSlot.numero_serie || ('#' + tireAtSlot.id)) + ' na posição ' + esc(slotId)) : ('Posição vazia ' + esc(slotId));
        var hit = '<path class="gip-tire-hit" tabindex="0" role="button" aria-label="' + hitLabel + '" d="' + gipRoundRectPath(x, y, w, h, 4) + '" fill="transparent"/>';
        var rubber = window.GipStagePremium.drawTireRubber(x, y, w, h, {
            hub: meta.hub !== false
        });
        var tire = allocated[slotId];
        if (!tire) {
            return '<g class="gip-tire gip-slot-empty" data-slot="' + esc(slotId) + '">' + rubber + hit +
                '<text x="' + cx.toFixed(1) + '" y="' + (cy + 5).toFixed(1) + '" text-anchor="middle" class="gip-slot-plus">+</text></g>';
        }
        var cardW = Math.min(46, Math.max(34, w + 12));
        var cardH = 24;
        var cardX = cx - cardW / 2, cardY = cy - cardH / 2;
        var serial = (tire.numero_serie || String(tire.id || '')).toString().replace(/\D/g, '').slice(-3) ||
            String(tire.id || '').slice(-3);
        var chipLbl = 'PNEU ' + serial;
        var ring = '<path class="gip-tire-ring" d="' + gipRoundRectPath(x - 1.5, y - 1.5, w + 3, h + 3, 5) + '" fill="none" stroke="#22c55e" stroke-width="2.4" stroke-opacity="0" pointer-events="none"/>';
        return '<g class="gip-tire" data-slot="' + esc(slotId) + '" data-pneu="' + esc(String(tire.id || '')) + '">' + rubber + ring + hit +
            '<g class="gip-chip-wrap" pointer-events="none">' +
            '<path class="gip-chip" d="' + gipRoundRectPath(cardX, cardY, cardW, cardH, 4) + '" fill="#0f172ae6" stroke="#22c55e" stroke-width="1.6" filter="url(#gipChipSh)"/>' +
            '<rect class="gip-chip-bar" x="' + cardX.toFixed(1) + '" y="' + (cardY + 2).toFixed(1) + '" width="' + cardW.toFixed(1) + '" height="2.2" rx="1" fill="#22c55e" opacity=".85" data-full-w="' + cardW.toFixed(1) + '"/>' +
            '<text class="gip-chip-lbl" x="' + cx.toFixed(1) + '" y="' + (cy - 2).toFixed(1) + '" text-anchor="middle" fill="#94a3b8" font-size="6" font-weight="700" letter-spacing=".06em">' + esc(chipLbl) + '</text>' +
            '<text class="gip-chip-txt" x="' + cx.toFixed(1) + '" y="' + (cy + 8).toFixed(1) + '" text-anchor="middle" fill="#f8fafc" font-size="8.5" font-weight="800">—</text>' +
            '</g></g>';
    }

    function statusFallbackColor(statusId) {
        switch (parseInt(statusId, 10)) {
            case 4: return '#3b82f6'; // recapagem
            case 3: return '#64748b'; // descartado
            case 2: return '#94a3b8'; // reserva
            case 5: return '#22c55e'; // novo
            default: return '#22c55e';
        }
    }

    function applyTireColors() {
        var stage = el('gipStage');
        if (!stage) return;
        stage.querySelectorAll('.gip-tire[data-pneu]').forEach(function (g) {
            var pid = g.getAttribute('data-pneu');
            var slot = g.getAttribute('data-slot');
            var tire = allocated[slot] || {};
            var hm = pid && healthMap[pid] ? healthMap[pid] : null;
            var color = hm && hm.health ? hm.health.cor : statusFallbackColor(tire.status_id);
            var nivel = hm && hm.health ? hm.health.nivel : 'bom';
            var chip = g.querySelector('.gip-chip');
            var bar = g.querySelector('.gip-chip-bar');
            var txt = g.querySelector('.gip-chip-txt');
            var ring = g.querySelector('.gip-tire-ring');
            if (ring) {
                ring.setAttribute('stroke', color);
                ring.setAttribute('stroke-opacity', '0.95');
                // Calibragem vencida → anel tracejado (sinaliza sem perder a cor de saúde).
                if (hm && hm.sem_calibragem) ring.setAttribute('stroke-dasharray', '3.5,2.5');
                else ring.removeAttribute('stroke-dasharray');
            }
            if (chip) {
                chip.setAttribute('stroke', color);
                chip.setAttribute('fill', '#0f172ae6');
            }
            if (bar) {
                bar.setAttribute('fill', color);
                var fullW = parseFloat(bar.getAttribute('data-full-w') || bar.getAttribute('width') || '0');
                if (fullW > 0 && hm && hm.pct != null) {
                    bar.setAttribute('width', Math.max(2, fullW * Math.min(100, hm.pct) / 100).toFixed(1));
                    bar.setAttribute('opacity', '.9');
                } else {
                    bar.setAttribute('width', fullW.toFixed(1));
                    bar.setAttribute('opacity', '.45');
                }
            }
            if (txt) {
                var disp = (hm && hm.pct != null) ? (hm.pct + '%') : (hm && hm.vida ? hm.vida + 'ª' : '—');
                txt.textContent = disp;
                txt.setAttribute('fill', '#f1f5f9');
            }
            g.setAttribute('data-nivel', nivel);
            g.setAttribute('data-vida', hm && hm.vida ? hm.vida : (tire.numero_recapagens != null ? tire.numero_recapagens + 1 : ''));
            g.setAttribute('data-semcalib', hm && hm.sem_calibragem ? '1' : '0');
            g.setAttribute('data-semmed', hm && hm.sem_medicao ? '1' : '0');
            if (curSlot && slot === curSlot) g.classList.add('is-selected');
        });
    }

    // ---- Filtros (esmaece pneus que não batem) ----
    function applyFilters() {
        var stage = el('gipStage');
        if (!stage) return;
        if (filterSomenteVazios) {
            stage.querySelectorAll('.gip-tire[data-pneu]').forEach(function (g) {
                g.style.opacity = '0.12';
                g.style.pointerEvents = 'none';
            });
            stage.querySelectorAll('.gip-tire.gip-slot-empty').forEach(function (g) {
                g.style.opacity = '1';
                g.style.pointerEvents = '';
            });
            return;
        }
        var fStatus = el('gipStatus').value;
        var fVida = el('gipVida').value;
        var fExtra = el('gipExtra') ? el('gipExtra').value : '';
        var soAlerta = el('gipSoAlerta').checked;
        stage.querySelectorAll('.gip-tire[data-pneu]').forEach(function (g) {
            var nivel = g.getAttribute('data-nivel') || 'bom';
            var vida = g.getAttribute('data-vida') || '';
            var show = true;
            if (fStatus && nivel !== fStatus) show = false;
            if (fVida) { if (fVida === '4') { if (!(parseInt(vida, 10) >= 4)) show = false; } else if (String(vida) !== fVida) show = false; }
            if (soAlerta && !(nivel === 'critico' || nivel === 'atencao')) show = false;
            if (fExtra === 'sem_calibragem' && g.getAttribute('data-semcalib') !== '1') show = false;
            if (fExtra === 'sem_medicao' && g.getAttribute('data-semmed') !== '1') show = false;
            if (fExtra === 'proximo_descarte' && !(nivel === 'critico' || parseInt(vida, 10) >= 4)) show = false;
            g.style.opacity = show ? '1' : '0.16';
            g.style.pointerEvents = show ? '' : 'none';
        });
        // Com qualquer filtro ativo, esmaece também as posições vazias (não poluem a leitura).
        var anyFilter = !!(fStatus || fVida || fExtra || soAlerta);
        stage.querySelectorAll('.gip-tire.gip-slot-empty').forEach(function (g) {
            g.style.opacity = anyFilter ? '0.12' : '1';
            g.style.pointerEvents = anyFilter ? 'none' : '';
        });
    }

    // ---- KPIs clicáveis (filtram os pneus no estágio) ----
    function clearKpiActive() {
        var box = el('gipKpis'); if (box) box.querySelectorAll('.kpi-active').forEach(function (c) { c.classList.remove('kpi-active'); });
        var lg = el('gipLegend'); if (lg) lg.querySelectorAll('.leg-active').forEach(function (c) { c.classList.remove('leg-active'); });
    }
    function onManualFilter() { filterSomenteVazios = false; clearKpiActive(); applyFilters(); }
    function setFiltersTo(opts) {
        opts = opts || {};
        filterSomenteVazios = !!opts.somenteVazios;
        el('gipStatus').value = opts.status || '';
        el('gipVida').value = opts.vida || '';
        if (el('gipExtra')) el('gipExtra').value = opts.extra || '';
        el('gipSoAlerta').checked = !!opts.soAlerta;
        applyFilters();
    }
    function wireKpis() {
        var box = el('gipKpis'); if (!box) return;
        // Só estes KPIs filtram; os demais apenas limpam o filtro (sem ficar "ativos").
        var map = {
            alerta:  { soAlerta: true },
            critico: { status: 'critico' },
            semmed:  { extra: 'sem_medicao' }
        };
        box.querySelectorAll('.biq-kpi[data-k]').forEach(function (card) {
            var k = card.getAttribute('data-k');
            var filterable = !!map[k];
            card.style.cursor = 'pointer';
            card.setAttribute('title', filterable
                ? 'Clique para filtrar os pneus por este indicador'
                : 'Clique para limpar os filtros');
            card.addEventListener('click', function () {
                var wasActive = card.classList.contains('kpi-active');
                clearKpiActive();
                if (!filterable || wasActive) { setFiltersTo({}); return; }
                card.classList.add('kpi-active');
                setFiltersTo(map[k]);
            });
        });
    }

    // ---- Tooltip ----
    function showTooltip(g, evt) {
        var tip = el('gipTooltip');
        var slot = g.getAttribute('data-slot');
        var pid = g.getAttribute('data-pneu');
        var tire = allocated[slot];
        if (!tire) {
            tip.innerHTML = '<div class="tt-title">Posição vazia</div><div class="tt-row"><span>Slot</span><span>' + esc(slot) + '</span></div><div class="tt-row"><span></span><span>Clique para alocar um pneu</span></div>';
        } else {
            var hm = pid && healthMap[pid] ? healthMap[pid] : null;
            var cor = hm && hm.health ? hm.health.cor : statusFallbackColor(tire.status_id);
            var rot = hm && hm.health ? hm.health.rotulo : (tire.status_nome || '—');
            tip.innerHTML =
                '<div class="tt-title"><span class="tt-dot" style="background:' + cor + '"></span>' + esc(tire.numero_serie || ('Pneu #' + tire.id)) + '</div>' +
                '<div class="tt-row"><span>Marca/Modelo</span><span>' + esc((tire.marca || '—') + ' ' + (tire.modelo || '')) + '</span></div>' +
                '<div class="tt-row"><span>Medida</span><span>' + esc(tire.medida || '—') + '</span></div>' +
                '<div class="tt-row"><span>Status</span><span>' + esc(rot) + '</span></div>' +
                '<div class="tt-row"><span>Sulco atual</span><span>' + (hm && hm.sulco_atual != null ? hm.sulco_atual + ' mm' : '—') + '</span></div>' +
                '<div class="tt-row"><span>Vida</span><span>' + (hm && hm.vida ? hm.vida + 'ª' : '—') + '</span></div>' +
                '<div class="tt-row"><span>Posição</span><span>' + esc(tire.posicao_nome || slot) + '</span></div>';
        }
        tip.classList.add('show');
        moveTooltip(evt);
    }
    function moveTooltip(evt) {
        var tip = el('gipTooltip');
        var x = evt.clientX + 16, y = evt.clientY + 16;
        if (x + 270 > window.innerWidth) x = evt.clientX - 270;
        if (y + 180 > window.innerHeight) y = evt.clientY - 180;
        tip.style.left = x + 'px'; tip.style.top = y + 'px';
    }
    function hideTooltip() { el('gipTooltip').classList.remove('show'); }

    // ---- Painel do pneu ----
    function renderPanelEmpty() {
        el('gipPanel').innerHTML = '<div class="gip-empty"><i class="fas fa-hand-pointer"></i>Clique em um pneu no estágio para ver os detalhes e ações.</div>';
    }
    function renderTimelineEmpty() {
        el('gipTimeline').innerHTML = '<li class="gip-tl-empty">Selecione um pneu para ver o histórico.</li>';
    }

    function onTireClick(g) {
        var slot = g.getAttribute('data-slot');
        var pid = g.getAttribute('data-pneu');
        // Modo comparar: o próximo pneu clicado é o "B".
        if (compareMode) {
            if (pid && parseInt(pid, 10) !== compareA) { doCompare(compareA, parseInt(pid, 10)); return; }
            if (!pid) { toast('Selecione um pneu (posição vazia não compara).', 'error'); return; }
            cancelCompare();
        }
        curSlot = slot;
        el('gipStage').querySelectorAll('.gip-tire').forEach(function (x) { x.classList.remove('is-selected'); });
        g.classList.add('is-selected');
        if (!pid) {
            el('gipPanel').innerHTML = '<div class="gip-empty"><i class="fas fa-plus-circle"></i>Posição <b>' + esc(slot) + '</b> sem pneu.</div>' +
                '<div class="gip-pnl-actions"><button class="gip-btn gip-btn--primary gip-btn--wide" id="actAlocar"><i class="fas fa-plus"></i> Alocar pneu aqui</button></div>';
            var ba = el('actAlocar'); if (ba) ba.addEventListener('click', function () { openAlocarPicker(slot); });
            renderTimelineEmpty();
            return;
        }
        curPneuId = parseInt(pid, 10);
        el('gipPanel').innerHTML = '<div class="gip-empty"><i class="fas fa-spinner fa-spin"></i>Carregando…</div>';
        getJson(proApi('tire_detail') + '&pneu_id=' + curPneuId).then(function (res) {
            if (!res || !res.success) { el('gipPanel').innerHTML = '<div class="gip-empty">Não foi possível carregar o pneu.</div>'; return; }
            renderPanel(res.data);
        });
        loadHistory(curPneuId);
    }

    function escAttr(s) { return esc(s).replace(/'/g, '&#39;'); }

    // ---- Alocar pneu numa posição vazia (do estoque) ----
    function slotEixoId(slot) {
        var parts = String(slot).split('-');
        var key = parts[0] + '-' + parts[1];
        var idx = axleOrder.indexOf(key);
        return idx >= 0 ? axleIds[idx] : null;
    }
    // Deriva lado/interno-externo a partir do slot (topo = esquerdo, por convenção da planta).
    function slotSideInfo(slot) {
        var parts = String(slot).split('-');
        var prefix = parts[0], eIdx = parseInt(parts[1], 10), pIdx = parseInt(parts[2], 10);
        var arr = (prefix === 'cavalo') ? (layout.eixosCaminhao || []) : (layout.eixosCarreta || []);
        var n = (arr[eIdx] && arr[eIdx].pneus) || 2;
        var topCount = Math.ceil(n / 2);
        var isTop = pIdx < topCount;
        var groupSize = isTop ? topCount : (n - topCount);
        var groupIdx = isTop ? pIdx : (pIdx - topCount);
        var side = isTop ? 'Esquerdo' : 'Direito';
        var innerOuter = (groupSize >= 2) ? ((groupIdx === groupSize - 1) ? 'Interno' : 'Externo') : null;
        return { side: side, innerOuter: innerOuter, isDual: groupSize >= 2 };
    }
    function suggestPosicaoId(slot, posicoes) {
        var info = slotSideInfo(slot);
        var sideL = info.side.toLowerCase(), ioL = info.innerOuter ? info.innerOuter.toLowerCase() : null;
        var withIO = null, sideOnly = null;
        posicoes.forEach(function (p) {
            var n = (p.nome || '').toLowerCase();
            if (n.indexOf(sideL) < 0) return;
            var hasIO = n.indexOf('interno') >= 0 || n.indexOf('externo') >= 0;
            if (ioL && n.indexOf(ioL) >= 0 && withIO == null) withIO = p.id;
            if (!ioL && !hasIO && sideOnly == null) sideOnly = p.id;
            if (sideOnly == null) sideOnly = p.id;
        });
        return (ioL && withIO != null) ? withIO : sideOnly;
    }

    function openAlocarPicker(slot) {
        var eixoId = slotEixoId(slot);
        var posSlot = parseInt(String(slot).split('-')[2] || '0', 10);
        if (eixoId == null) { toast('Não identifiquei o eixo desta posição.', 'error'); return; }
        var m = el('gipAlocarModal');
        if (!m) {
            m = document.createElement('div');
            m.id = 'gipAlocarModal'; m.className = 'gip-modal';
            m.innerHTML = '<div class="gip-modal-box"><div class="gip-modal-head"><h4 id="gipAlocarTitle">Alocar pneu</h4>' +
                '<button type="button" class="x" data-close>&times;</button></div>' +
                '<div class="gip-modal-body" style="grid-template-columns:1fr">' +
                '<div class="gip-aloc-step" id="gipAlocStep"></div>' +
                '<div id="gipAlocarList" style="max-height:46vh;overflow:auto"></div></div>' +
                '<div class="gip-modal-foot"><span class="msg" id="gipAlocarMsg"></span><span style="flex:1"></span>' +
                '<button class="gip-btn gip-btn--sm" id="gipAlocBack" hidden><i class="fas fa-arrow-left"></i> Voltar</button>' +
                '<button class="gip-btn gip-btn--sm" data-close>Fechar</button></div></div>';
            document.body.appendChild(m);
            m.addEventListener('click', function (e) { if (e.target === m || e.target.hasAttribute('data-close')) m.classList.remove('open'); });
            m.querySelector('#gipAlocBack').addEventListener('click', function () { alocarStepTires(m); });
        }
        m._slot = slot; m._eixoId = eixoId; m._posSlot = posSlot; m._pneu = null;
        m.querySelector('#gipAlocarMsg').textContent = '';
        m.classList.add('open');
        alocarStepTires(m);
    }
    function alocarStepTires(m) {
        var info = slotSideInfo(m._slot);
        el('gipAlocarTitle').textContent = 'Alocar pneu — passo 1 de 2';
        m.querySelector('#gipAlocBack').hidden = true;
        m.querySelector('#gipAlocarMsg').textContent = '';
        m.querySelector('#gipAlocStep').innerHTML = '<div class="gip-aloc-hint"><i class="fas fa-location-dot"></i> Posição <b>' + esc(m._slot) +
            '</b> · lado <b>' + info.side + '</b>' + (info.innerOuter ? ' · <b>' + info.innerOuter + '</b>' : '') + '<br>Escolha o pneu do estoque:</div>';
        var list = el('gipAlocarList');
        list.innerHTML = '<div class="gip-empty"><i class="fas fa-spinner fa-spin"></i> Carregando pneus disponíveis…</div>';
        getJson('../gestao_interativa/api/pneus_disponiveis.php').then(function (res) {
            var pneus = (res && res.success) ? (res.pneus || []) : [];
            if (!pneus.length) { list.innerHTML = '<div class="gip-empty">Nenhum pneu disponível no estoque.</div>'; return; }
            list.innerHTML = pneus.map(function (p) {
                return '<button type="button" class="gip-alocar-item" data-id="' + p.id + '">' +
                    '<b>' + esc(p.numero_serie || ('Pneu #' + p.id)) + '</b>' +
                    '<span>' + esc((p.marca || '') + ' ' + (p.modelo || '') + ' · ' + (p.medida || '—') + (p.status_nome ? ' · ' + p.status_nome : '')) + '</span></button>';
            }).join('');
            list.querySelectorAll('.gip-alocar-item').forEach(function (b) {
                b.addEventListener('click', function () {
                    var p = pneus.find(function (x) { return String(x.id) === b.getAttribute('data-id'); });
                    m._pneu = p || { id: parseInt(b.getAttribute('data-id'), 10) };
                    alocarStepPosicao(m);
                });
            });
        }).catch(function () { list.innerHTML = '<div class="gip-empty">Erro ao carregar pneus.</div>'; });
    }
    function alocarStepPosicao(m) {
        var p = m._pneu;
        el('gipAlocarTitle').textContent = 'Alocar pneu — passo 2 de 2';
        m.querySelector('#gipAlocBack').hidden = false;
        m.querySelector('#gipAlocarMsg').textContent = '';
        m.querySelector('#gipAlocStep').innerHTML = '<div class="gip-aloc-hint"><i class="fas fa-circle-check" style="color:#22c55e"></i> Pneu <b>' +
            esc(p.numero_serie || ('#' + p.id)) + '</b>' + (p.medida ? ' · ' + esc(p.medida) : '') + '<br>Selecione a <b>posição de montagem</b> (lado/eixo):</div>';
        var list = el('gipAlocarList');
        list.innerHTML = '<div class="gip-empty"><i class="fas fa-spinner fa-spin"></i> Carregando posições…</div>';
        getJson('../gestao_interativa/api/posicoes_pneus.php').then(function (res) {
            var posicoes = (res && res.success) ? (res.posicoes || []) : [];
            var sugId = posicoes.length ? suggestPosicaoId(m._slot, posicoes) : null;
            var html = '';
            if (sugId != null) {
                var sug = posicoes.find(function (x) { return String(x.id) === String(sugId); });
                if (sug) html += '<button type="button" class="gip-alocar-item gip-aloc-sug" data-pos="' + sug.id + '"><b>' + esc(sug.nome) +
                    ' <span class="gip-aloc-badge">sugerida</span></b><span>Com base no lado da posição selecionada</span></button>';
            }
            html += posicoes.filter(function (x) { return String(x.id) !== String(sugId); }).map(function (pos) {
                return '<button type="button" class="gip-alocar-item" data-pos="' + pos.id + '"><b>' + esc(pos.nome) + '</b></button>';
            }).join('');
            html += '<button type="button" class="gip-alocar-item gip-aloc-skip" data-pos=""><b><i class="fas fa-forward"></i> Sem posição específica</b><span>Aloca sem registrar lado/eixo</span></button>';
            list.innerHTML = html;
            list.querySelectorAll('.gip-alocar-item').forEach(function (b) {
                b.addEventListener('click', function () {
                    var pv = b.getAttribute('data-pos');
                    doAlocar(m, pv ? parseInt(pv, 10) : null);
                });
            });
        }).catch(function () { list.innerHTML = '<div class="gip-empty">Erro ao carregar posições.</div>'; });
    }
    function doAlocar(m, posicaoId) {
        var msg = m.querySelector('#gipAlocarMsg'); if (msg) { msg.style.color = ''; msg.textContent = 'Alocando…'; }
        var body = { eixo_id: m._eixoId, pneu_id: m._pneu.id, slot_id: m._slot, posicao_slot: m._posSlot };
        if (posicaoId != null) body.posicao_id = posicaoId;
        postJson(EIXOS_API + '?action=alocar_pneu', body)
            .then(function (o) {
                if (!o.ok || !o.d || !o.d.success) { if (msg) { msg.style.color = '#ef4444'; msg.textContent = (o.d && (o.d.error || o.d.message)) || 'Falha ao alocar.'; } return; }
                m.classList.remove('open');
                toast('Pneu alocado na posição.', 'success');
                reloadVehicle();
            }).catch(function () { if (msg) { msg.style.color = '#ef4444'; msg.textContent = 'Erro de comunicação.'; } });
    }

    function renderPanel(d) {
        var cor = d.health ? d.health.cor : '#22c55e';
        var rot = d.health ? d.health.rotulo : (d.status_nome || '—');
        var sulcoPct = '';
        if (d.sulco_inicial && d.sulco_atual != null) {
            var pct = Math.max(0, Math.min(100, (d.sulco_atual / d.sulco_inicial) * 100));
            sulcoPct = '<div class="gip-meter"><i style="width:' + pct.toFixed(0) + '%;background:' + cor + '"></i></div>';
        }
        var item = function (k, v) { return '<div class="gip-pnl-item"><span class="k">' + k + '</span><span class="v">' + v + '</span></div>'; };
        el('gipPanel').innerHTML =
            '<span class="gip-pnl-status" style="background:' + hexA(cor, .15) + ';color:' + cor + '"><i class="fas fa-circle" style="font-size:.6rem"></i>' + esc(rot) + '</span>' +
            '<div class="gip-pnl-title">' + esc(d.numero_serie || ('Pneu #' + d.id)) + '</div>' +
            '<div class="gip-pnl-sub">' + esc((d.marca || '—') + ' ' + (d.modelo || '') + ' · ' + (d.medida || '—')) + (d.posicao_nome ? ' · ' + esc(d.posicao_nome) : '') + '</div>' +
            '<div class="gip-pnl-grid">' +
                item('DOT', esc(d.dot || '—') + (d.dot_idade_anos != null ? ' <small style="color:var(--text-muted,var(--text-secondary))">(' + d.dot_idade_anos + ' anos)</small>' : '')) +
                item('Vida', (d.vida || 1) + 'ª (' + (d.numero_recapagens || 0) + ' recap.)') +
                item('Sulco inicial', d.sulco_inicial != null ? d.sulco_inicial + ' mm' : '—') +
                item('Sulco atual', d.sulco_atual != null ? d.sulco_atual + ' mm' : '—') +
                item('Pressão rec.', d.pressao_recomendada != null ? d.pressao_recomendada + ' psi' : '—') +
                item('Pressão aferida', d.pressao_aferida != null ? d.pressao_aferida + ' psi' : '—') +
                item('KM acumulado', num(d.km_acumulado, ' km')) +
                item('Custo/km', d.custo_km != null ? money(d.custo_km) : '—') +
                item('Custo total', money(d.custo_total)) +
                item('Instalação', dateBr(d.data_instalacao)) +
                item('Última inspeção', dateBr(d.sulco_data)) +
                item('Próxima sugerida', dateBr(d.proxima_inspecao)) +
            '</div>' + sulcoPct +
            '<div class="gip-pnl-actions">' +
                '<button class="gip-btn gip-btn--primary gip-btn--wide" id="actSulco"><i class="fas fa-ruler-vertical"></i> Nova medição de sulco</button>' +
                '<button class="gip-btn" id="actCalib"><i class="fas fa-gauge"></i> Calibragem</button>' +
                '<button class="gip-btn" id="actMover"><i class="fas fa-arrows-up-down-left-right"></i> Trocar posição</button>' +
                '<button class="gip-btn" id="actComparar"><i class="fas fa-code-compare"></i> Comparar</button>' +
                '<button class="gip-btn" id="actRecap"><i class="fas fa-recycle"></i> Recapagem</button>' +
                '<button class="gip-btn" id="actRemover"><i class="fas fa-circle-minus"></i> Remover</button>' +
                '<button class="gip-btn gip-btn--wide" id="actDescartar" style="border-color:#ef4444;color:#ef4444"><i class="fas fa-trash"></i> Descartar pneu</button>' +
                (d.qr_token ? '<a class="gip-btn gip-btn--wide gip-btn--ghost" id="actFicha" href="' + esc(gipResolveUrl('pneu_ficha.php?t=' + encodeURIComponent(d.qr_token))) + '" target="_blank" rel="noopener"><i class="fas fa-id-card"></i> Ficha completa</a>' : '') +
            '</div>';
        el('actSulco').addEventListener('click', function () { openModal('gipSulcoModal'); });
        el('actCalib').addEventListener('click', function () {
            if (d.pressao_recomendada != null) el('calibReco').value = d.pressao_recomendada;
            openModal('gipCalibModal');
        });
        el('actMover').addEventListener('click', moverPneu);
        el('actComparar').addEventListener('click', startCompare);
        el('actRecap').addEventListener('click', recapagem);
        el('actRemover').addEventListener('click', removerPneu);
        el('actDescartar').addEventListener('click', descartarPneu);
    }

    // ---- Comparar dois pneus ----
    function startCompare() {
        if (!curPneuId) return;
        compareMode = true; compareA = curPneuId;
        el('gipStage').classList.add('gip-compare-pick');
        toast('Clique em outro pneu para comparar.', 'success');
    }
    function cancelCompare() {
        compareMode = false; compareA = null;
        var s = el('gipStage'); if (s) s.classList.remove('gip-compare-pick');
    }
    function doCompare(idA, idB) {
        cancelCompare();
        var m = el('gipCompareModal');
        if (!m) {
            m = document.createElement('div');
            m.id = 'gipCompareModal'; m.className = 'gip-modal';
            m.innerHTML = '<div class="gip-modal-box"><div class="gip-modal-head"><h4><i class="fas fa-code-compare" style="color:#3b82f6"></i> Comparar pneus</h4>' +
                '<button type="button" class="x" data-close>&times;</button></div>' +
                '<div class="gip-modal-body" style="grid-template-columns:1fr"><div id="gipCompareBody" style="max-height:62vh;overflow:auto"></div></div>' +
                '<div class="gip-modal-foot"><span class="msg" style="flex:1"></span><button class="gip-btn gip-btn--sm" data-close>Fechar</button></div></div>';
            document.body.appendChild(m);
            m.addEventListener('click', function (e) { if (e.target === m || e.target.hasAttribute('data-close')) m.classList.remove('open'); });
        }
        var body = el('gipCompareBody');
        body.innerHTML = '<div class="gip-empty"><i class="fas fa-spinner fa-spin"></i> Carregando…</div>';
        m.classList.add('open');
        Promise.all([
            getJson(proApi('tire_detail') + '&pneu_id=' + idA),
            getJson(proApi('tire_detail') + '&pneu_id=' + idB)
        ]).then(function (arr) {
            var a = arr[0] && arr[0].success ? arr[0].data : null;
            var b = arr[1] && arr[1].success ? arr[1].data : null;
            if (!a || !b) { body.innerHTML = '<div class="gip-empty">Não foi possível carregar os pneus.</div>'; return; }
            body.innerHTML = renderCompare(a, b);
        }).catch(function () { body.innerHTML = '<div class="gip-empty">Erro ao comparar.</div>'; });
    }
    function renderCompare(a, b) {
        function head(d) {
            var cor = d.health ? d.health.cor : '#22c55e';
            return '<div class="gip-cmp-head"><span class="gip-pnl-status" style="background:' + hexA(cor, .15) + ';color:' + cor + '"><i class="fas fa-circle" style="font-size:.55rem"></i>' +
                esc(d.health ? d.health.rotulo : (d.status_nome || '—')) + '</span><b>' + esc(d.numero_serie || ('#' + d.id)) + '</b><span>' + esc((d.marca || '') + ' ' + (d.modelo || '')) + '</span></div>';
        }
        var rows = [
            ['Medida', a.medida || '—', b.medida || '—'],
            ['DOT', (a.dot || '—') + (a.dot_idade_anos != null ? ' (' + a.dot_idade_anos + 'a)' : ''), (b.dot || '—') + (b.dot_idade_anos != null ? ' (' + b.dot_idade_anos + 'a)' : '')],
            ['Vida', (a.vida || 1) + 'ª', (b.vida || 1) + 'ª'],
            ['Sulco atual', a.sulco_atual != null ? a.sulco_atual + ' mm' : '—', b.sulco_atual != null ? b.sulco_atual + ' mm' : '—'],
            ['Sulco inicial', a.sulco_inicial != null ? a.sulco_inicial + ' mm' : '—', b.sulco_inicial != null ? b.sulco_inicial + ' mm' : '—'],
            ['KM acumulado', num(a.km_acumulado, ' km'), num(b.km_acumulado, ' km')],
            ['Custo/km', a.custo_km != null ? money(a.custo_km) : '—', b.custo_km != null ? money(b.custo_km) : '—'],
            ['Custo total', money(a.custo_total), money(b.custo_total)],
            ['Posição', a.posicao_nome || '—', b.posicao_nome || '—']
        ];
        var html = '<div class="gip-cmp-heads">' + head(a) + head(b) + '</div><div class="gip-cmp-rows">';
        rows.forEach(function (r) {
            html += '<div class="gip-cmp-row"><span class="k">' + r[0] + '</span><span class="v">' + esc(String(r[1])) + '</span><span class="v">' + esc(String(r[2])) + '</span></div>';
        });
        html += '</div>';
        return html;
    }

    function descartarPneu() {
        if (!curPneuId) return;
        gipConfirm({
            title: 'Descartar pneu', icon: 'trash', danger: true, confirmText: 'Descartar',
            message: 'Descartar este pneu? Ele sai do veículo e fica marcado como DESCARTADO (não volta ao estoque).'
        }).then(function (ok) {
            if (!ok) return;
            postJson(proApi('descartar_pneu'), { pneu_id: curPneuId }).then(function (o) {
                if (!o.ok || !o.d.success) { toast((o.d && (o.d.message || o.d.error)) || 'Falha ao descartar.', 'error'); return; }
                toast('Pneu descartado.', 'success');
                reloadVehicle();
            }).catch(function () { toast('Erro de comunicação.', 'error'); });
        });
    }

    function hexA(hex, a) {
        if (!/^#?[0-9a-f]{6}$/i.test(hex.replace('#', ''))) return hex;
        var h = hex.replace('#', ''); var r = parseInt(h.substr(0, 2), 16), g = parseInt(h.substr(2, 2), 16), b = parseInt(h.substr(4, 2), 16);
        return 'rgba(' + r + ',' + g + ',' + b + ',' + a + ')';
    }

    // ---- Histórico ----
    function loadHistory(pneuId) {
        var ul = el('gipTimeline');
        ul.innerHTML = '<li class="gip-tl-empty">Carregando…</li>';
        getJson(proApi('history') + '&pneu_id=' + pneuId).then(function (res) {
            var ev = (res && res.success) ? (res.data || []) : [];
            if (!ev.length) { ul.innerHTML = '<li class="gip-tl-empty">Sem eventos registrados.</li>'; return; }
            var corGrupo = { movimentacao: '#3b82f6', sulco: '#22c55e', calibragem: '#06b6d4' };
            ul.innerHTML = ev.map(function (e) {
                var extra = [];
                if (e.km != null) extra.push(num(e.km, ' km'));
                if (e.sulco != null) extra.push(e.sulco + ' mm');
                if (e.custo != null && Number(e.custo) > 0) extra.push(money(e.custo));
                return '<li class="gip-tl-item" style="--gip-tl:' + (corGrupo[e.grupo] || '#3b82f6') + '">' +
                    '<div class="tl-when">' + dateBr(e.data) + '</div>' +
                    '<div class="tl-what">' + esc(String(e.tipo || '').replace(/_/g, ' ')) + '</div>' +
                    '<div class="tl-desc">' + (extra.length ? extra.join(' · ') + (e.obs ? ' — ' : '') : '') + esc(e.obs || '') + '</div></li>';
            }).join('');
        });
    }

    // ---- Alertas ----
    function loadAlertas() {
        getJson(proApi('alertas') + '&veiculo_id=' + vehId).then(function (res) {
            renderAlerts((res && res.success) ? (res.data || []) : []);
        }).catch(function () { renderAlerts([]); });
    }
    function renderAlerts(list) {
        var ul = el('gipAlerts');
        el('gipAlertCount').textContent = list.length ? '(' + list.length + ')' : '';
        if (!list.length) { ul.innerHTML = '<li class="gip-alerts-empty">Nenhum alerta para este veículo. 👍</li>'; return; }
        var ico = { critico: 'fa-circle-exclamation', atencao: 'fa-triangle-exclamation', info: 'fa-circle-info' };
        ul.innerHTML = list.map(function (a) {
            return '<li class="gip-alert ' + a.nivel + '" data-pneu="' + (a.pneu_id || '') + '">' +
                '<span class="ico"><i class="fas ' + (ico[a.nivel] || 'fa-bell') + '"></i></span>' +
                '<span><span class="tit">' + esc(a.titulo) + '</span><br><span class="dsc">' + esc(a.desc) + '</span></span></li>';
        }).join('');
        ul.querySelectorAll('.gip-alert[data-pneu]').forEach(function (li) {
            li.addEventListener('click', function () {
                var pid = li.getAttribute('data-pneu'); if (!pid) return;
                var g = el('gipStage').querySelector('.gip-tire[data-pneu="' + pid + '"]');
                if (g) { g.scrollIntoView({ block: 'nearest' }); onTireClick(g); }
            });
        });
    }

    // ---- Ações ----
    function openModal(id) { el(id).classList.add('open'); }
    function closeModal(id) { el(id).classList.remove('open'); }

    function submitSulco() {
        if (!curPneuId) return;
        var btn = el('sulcoSalvar'); btn.disabled = true;
        el('sulcoMsg').textContent = '';
        postJson(proApi('registrar_sulco'), {
            pneu_id: curPneuId, veiculo_id: vehId,
            sulco_externo: el('sulcoExt').value, sulco_central: el('sulcoCen').value, sulco_interno: el('sulcoInt').value,
            km_veiculo: el('sulcoKm').value, responsavel: el('sulcoResp').value, observacoes: el('sulcoObs').value
        }).then(function (o) {
            btn.disabled = false;
            if (!o.ok || !o.d.success) { el('sulcoMsg').style.color = '#ef4444'; el('sulcoMsg').textContent = (o.d && (o.d.message || o.d.error)) || 'Erro.'; return; }
            closeModal('gipSulcoModal');
            ['sulcoExt', 'sulcoCen', 'sulcoInt', 'sulcoKm', 'sulcoResp', 'sulcoObs'].forEach(function (i) { el(i).value = ''; });
            toast('Sulco registrado: ' + o.d.sulco_media + ' mm', 'success');
            refreshAfterAction();
        }).catch(function () { btn.disabled = false; el('sulcoMsg').textContent = 'Erro de comunicação.'; });
    }

    function submitCalib() {
        if (!curPneuId) return;
        var btn = el('calibSalvar'); btn.disabled = true; el('calibMsg').textContent = '';
        postJson(proApi('registrar_calibragem'), {
            pneu_id: curPneuId, veiculo_id: vehId,
            pressao_recomendada: el('calibReco').value, pressao_aferida: el('calibAfer').value,
            km_veiculo: el('calibKm').value, responsavel: el('calibResp').value, observacoes: el('calibObs').value
        }).then(function (o) {
            btn.disabled = false;
            if (!o.ok || !o.d.success) { el('calibMsg').style.color = '#ef4444'; el('calibMsg').textContent = (o.d && (o.d.message || o.d.error)) || 'Erro.'; return; }
            closeModal('gipCalibModal');
            ['calibAfer', 'calibKm', 'calibResp', 'calibObs'].forEach(function (i) { el(i).value = ''; });
            toast('Calibragem registrada.' + (o.d.aviso ? ' ' + o.d.aviso : ''), o.d.aviso ? 'error' : 'success');
            refreshAfterAction();
        }).catch(function () { btn.disabled = false; el('calibMsg').textContent = 'Erro de comunicação.'; });
    }

    function removerPneu() {
        if (!curSlot) return;
        gipConfirm({
            title: 'Remover pneu', icon: 'box-archive', confirmText: 'Remover',
            message: 'Remover este pneu do veículo? Ele volta ao estoque/manutenção.'
        }).then(function (ok) {
            if (!ok) return;
            postJson(EIXOS_API + '?action=remover_pneu', { slot_id: curSlot }).then(function (o) {
                if (!o.ok || !o.d.success) { toast((o.d && o.d.error) || 'Falha ao remover.', 'error'); return; }
                toast('Pneu removido.', 'success'); reloadVehicle();
            }).catch(function () { toast('Erro de comunicação.', 'error'); });
        });
    }

    function recapagem() {
        if (!curSlot) return;
        gipConfirm({
            title: 'Enviar para recapagem', icon: 'recycle', confirmText: 'Enviar',
            message: 'Enviar este pneu para recapagem? Ele será removido da posição.'
        }).then(function (ok) {
            if (!ok) return;
            postJson(EIXOS_API + '?action=enviar_manutencao', { slot_id: curSlot, observacoes: 'Enviado para recapagem (Gestão Interativa Pro).' }).then(function (o) {
                if (!o.ok || !o.d.success) { toast((o.d && o.d.error) || 'Falha ao enviar.', 'error'); return; }
                toast('Enviado para recapagem.', 'success'); reloadVehicle();
            }).catch(function () { toast('Erro de comunicação.', 'error'); });
        });
    }

    function moverPneu() {
        if (!curSlot) return;
        // Lista de posições vazias do veículo atual
        var vazios = [];
        el('gipStage').querySelectorAll('.gip-tire.gip-slot-empty').forEach(function (g) { vazios.push(g.getAttribute('data-slot')); });
        if (!vazios.length) { toast('Não há posição vazia neste veículo para mover.', 'error'); return; }
        gipChoose({
            title: 'Mover pneu', message: 'Para qual posição vazia deseja mover este pneu?',
            options: vazios.map(function (v) { return { label: v, value: v }; })
        }).then(function (destino) {
            if (!destino) return;
            postJson(EIXOS_API + '?action=mover_pneu', { from_slot_id: curSlot, to_slot_id: destino }).then(function (o) {
                if (!o.ok || !o.d.success) { toast((o.d && o.d.error) || 'Falha ao mover.', 'error'); return; }
                toast('Pneu movido para ' + destino + '.', 'success'); reloadVehicle();
            }).catch(function () { toast('Erro de comunicação.', 'error'); });
        });
    }

    function reloadVehicle() { var id = vehId; curSlot = null; curPneuId = null; renderPanelEmpty(); renderTimelineEmpty(); selectVehicle(id); loadFleetKpis(); }
    function refreshAfterAction() {
        loadHealthAndColor(); loadAlertas(); loadFleetKpis();
        if (curPneuId) getJson(proApi('tire_detail') + '&pneu_id=' + curPneuId).then(function (res) { if (res && res.success) renderPanel(res.data); });
        if (curPneuId) loadHistory(curPneuId);
    }

    // ---- Modo montagem: arrastar eixos ----
    function toggleAssembly() {
        if (!assemblyMode && viewMode !== 'all') { toast('Volte para a visão "Tudo" para montar os eixos.', 'error'); return; }
        var wasOn = assemblyMode;
        assemblyMode = !assemblyMode;
        var b = el('gipMontar');
        if (b) {
            b.classList.toggle('gip-btn--primary', assemblyMode);
            b.innerHTML = assemblyMode
                ? '<i class="fas fa-check"></i> Concluir montagem'
                : '<i class="fas fa-up-down-left-right"></i> Montar eixos';
        }
        var auto = el('gipAutoMontagem');
        if (auto) auto.style.display = assemblyMode ? '' : 'none';
        if (vehId) renderStage();
        if (assemblyMode) toast('Arraste os eixos e o tanque para posicionar. Ao concluir, salva.', 'success');
        else if (wasOn) saveMontagem();   // Concluir → persiste a montagem
    }
    function resetAssembly() {
        customAxleXs = null; customTankX = null;
        if (vehId) renderStage();
        toast('Espaçamento automático restaurado.', 'success');
    }
    function saveMontagem() {
        if (!vehId) return;
        var eixos = {};
        if (customAxleXs && customAxleXs.length === axleIds.length) {
            axleIds.forEach(function (id, i) { if (id != null) eixos[id] = Math.round(customAxleXs[i]); });
        }
        var payload = { veiculo_id: vehId, eixos: eixos, tank_x: (customTankX != null ? Math.round(customTankX) : null) };
        postJson(proApi('salvar_montagem'), payload).then(function (o) {
            if (o.ok && o.d && o.d.success) toast(o.d.cleared ? 'Montagem automática salva.' : 'Montagem salva no veículo. ✓', 'success');
            else toast((o.d && (o.d.message || o.d.error)) || 'Falha ao salvar montagem.', 'error');
        }).catch(function () { toast('Erro de comunicação ao salvar.', 'error'); });
    }
    function applyMontagem(cfg) {
        // cfg = { eixos: {id:x}, tankX } — aplica nas posições atuais (na ordem dos eixos).
        customAxleXs = null; customTankX = null;
        if (!cfg) return;
        if (cfg.eixos && axleIds.length) {
            var xs = axleIds.map(function (id) { var v = cfg.eixos[id]; return (v != null) ? Number(v) : null; });
            if (xs.every(function (v) { return v != null && !isNaN(v); })) customAxleXs = xs;
        }
        if (cfg.tankX != null && !isNaN(cfg.tankX)) customTankX = Number(cfg.tankX);
    }

    var _axleDrag = null, _tireSwap = null, _didSwapDrag = false;
    function onStageMouseDown(e) {
        var stage = el('gipStage'), svg = stage.querySelector('svg');
        if (!svg) return;
        if (!assemblyMode) {
            // Modo normal: arrastar um pneu para trocar de posição.
            var tireG = e.target.closest('.gip-tire[data-pneu]');
            if (!tireG) return;
            _tireSwap = { fromSlot: tireG.getAttribute('data-slot'), el: tireG, sx: e.clientX, sy: e.clientY, moved: false };
            document.addEventListener('mousemove', onTireSwapMove);
            document.addEventListener('mouseup', onTireSwapEnd);
            return;
        }
        var vbW = (lastBuilt && lastBuilt.width) ? lastBuilt.width : (svg.viewBox.baseVal.width || 1);
        var scale = (svg.clientWidth || vbW) / vbW;
        var tankG = e.target.closest('[data-part="tank"]');
        if (tankG) {
            e.preventDefault();
            var tx0 = (customTankX != null) ? customTankX : (lastBuilt ? lastBuilt.tankX : 0);
            _axleDrag = { type: 'tank', startX: e.clientX, cur: 0, scale: scale || 1, x0: tx0, groups: [tankG] };
        } else {
            var g = e.target.closest('[data-axle]');
            if (!g || !customAxleXs) return;
            var key = g.getAttribute('data-axle');
            var idx = axleOrder.indexOf(key);
            if (idx < 0) return;
            e.preventDefault();
            _axleDrag = { type: 'axle', idx: idx, startX: e.clientX, cur: 0, scale: scale || 1, x0: customAxleXs[idx], groups: stage.querySelectorAll('[data-axle="' + key + '"]') };
        }
        document.addEventListener('mousemove', onAxleDragMove);
        document.addEventListener('mouseup', onAxleDragEnd);
    }
    function onAxleDragMove(e) {
        if (!_axleDrag) return;
        var dx = (e.clientX - _axleDrag.startX) / _axleDrag.scale;
        _axleDrag.cur = dx;
        _axleDrag.groups.forEach(function (gr) { gr.setAttribute('transform', 'translate(' + dx.toFixed(1) + ',0)'); });
    }
    function onAxleDragEnd() {
        document.removeEventListener('mousemove', onAxleDragMove);
        document.removeEventListener('mouseup', onAxleDragEnd);
        if (!_axleDrag) return;
        var nx = Math.max(40, _axleDrag.x0 + (_axleDrag.cur || 0));
        if (_axleDrag.type === 'tank') customTankX = nx;
        else customAxleXs[_axleDrag.idx] = nx;
        _axleDrag = null;
        renderStage();
    }

    // ---- Modal de confirmação estilizado (substitui o confirm() nativo) ----
    function gipConfirm(opts) {
        return new Promise(function (resolve) {
            var m = el('gipConfirmModal');
            if (!m) {
                m = document.createElement('div');
                m.id = 'gipConfirmModal'; m.className = 'gip-modal';
                m.innerHTML = '<div class="gip-modal-box gip-modal-sm"><div class="gip-modal-head"><h4></h4>' +
                    '<button type="button" class="x" data-no>&times;</button></div>' +
                    '<div class="gip-modal-body" style="grid-template-columns:1fr"><p class="gip-confirm-msg"></p></div>' +
                    '<div class="gip-modal-foot"><button class="gip-btn" data-no>Cancelar</button>' +
                    '<button class="gip-btn gip-btn--primary" data-yes></button></div></div>';
                document.body.appendChild(m);
            }
            m.querySelector('h4').textContent = opts.title || 'Confirmar';
            m.querySelector('.gip-confirm-msg').textContent = opts.message || '';
            var yes = m.querySelector('[data-yes]');
            yes.innerHTML = (opts.icon ? '<i class="fas fa-' + opts.icon + '"></i> ' : '') + (opts.confirmText || 'Confirmar');
            yes.classList.toggle('gip-btn--danger', !!opts.danger);
            yes.classList.toggle('gip-btn--primary', !opts.danger);
            function close(val) { m.classList.remove('open'); m.onclick = null; resolve(val); }
            m.onclick = function (e) {
                if (e.target.closest('[data-yes]')) close(true);
                else if (e.target.closest('[data-no]') || e.target === m) close(false);
            };
            m.classList.add('open');
        });
    }

    // ---- Modal de escolha (lista) estilizado (substitui o prompt() nativo) ----
    function gipChoose(opts) {
        return new Promise(function (resolve) {
            var m = el('gipChooseModal');
            if (!m) {
                m = document.createElement('div');
                m.id = 'gipChooseModal'; m.className = 'gip-modal';
                m.innerHTML = '<div class="gip-modal-box gip-modal-sm"><div class="gip-modal-head"><h4></h4>' +
                    '<button type="button" class="x" data-no>&times;</button></div>' +
                    '<div class="gip-modal-body" style="grid-template-columns:1fr"><p class="gip-confirm-msg" style="margin:0 0 8px"></p>' +
                    '<div class="gip-choose-list" style="max-height:46vh;overflow:auto"></div></div>' +
                    '<div class="gip-modal-foot"><button class="gip-btn" data-no>Cancelar</button></div></div>';
                document.body.appendChild(m);
            }
            m.querySelector('h4').textContent = opts.title || 'Selecione';
            m.querySelector('.gip-confirm-msg').textContent = opts.message || '';
            var list = m.querySelector('.gip-choose-list');
            list.innerHTML = (opts.options || []).map(function (o) {
                return '<button type="button" class="gip-alocar-item" data-val="' + escAttr(String(o.value)) + '"><b>' + esc(o.label) + '</b></button>';
            }).join('');
            function close(val) { m.classList.remove('open'); m.onclick = null; resolve(val); }
            m.onclick = function (e) {
                var b = e.target.closest('[data-val]');
                if (b) close(b.getAttribute('data-val'));
                else if (e.target.closest('[data-no]') || e.target === m) close(null);
            };
            m.classList.add('open');
        });
    }

    // ---- Sugestão de rodízio ----
    function openRodizio() {
        if (!vehId) { toast('Selecione um veículo primeiro.', 'error'); return; }
        var m = el('gipRodizioModal');
        if (!m) {
            m = document.createElement('div');
            m.id = 'gipRodizioModal'; m.className = 'gip-modal';
            m.innerHTML = '<div class="gip-modal-box"><div class="gip-modal-head"><h4><i class="fas fa-rotate" style="color:#3b82f6"></i> Sugestão de rodízio</h4>' +
                '<button type="button" class="x" data-close>&times;</button></div>' +
                '<div class="gip-modal-body" style="grid-template-columns:1fr"><div id="gipRodizioBody" style="max-height:60vh;overflow:auto"></div></div>' +
                '<div class="gip-modal-foot"><span class="msg" style="flex:1"></span><button class="gip-btn gip-btn--sm" data-close>Fechar</button></div></div>';
            document.body.appendChild(m);
            m.addEventListener('click', function (e) { if (e.target === m || e.target.hasAttribute('data-close')) m.classList.remove('open'); });
        }
        var body = el('gipRodizioBody');
        body.innerHTML = '<div class="gip-empty"><i class="fas fa-spinner fa-spin"></i> Analisando desgaste…</div>';
        m.classList.add('open');
        getJson(proApi('rodizio') + '&veiculo_id=' + vehId).then(function (res) {
            if (!res || !res.success) { body.innerHTML = '<div class="gip-empty">Não foi possível analisar.</div>'; return; }
            var d = res.data, html = '';
            html += '<div class="gip-aloc-hint"><i class="fas fa-ruler-vertical"></i> Sulco médio do veículo: <b>' + (d.media != null ? d.media + ' mm' : '—') + '</b></div>';
            if (d.suggestions && d.suggestions.length) {
                html += '<h5 class="gip-rod-h">Trocas sugeridas</h5>';
                html += d.suggestions.map(function (s) {
                    return '<div class="gip-rod-sug">' +
                        '<div class="gip-rod-leg"><span class="gip-rod-from"><b>' + esc(s.de_serie) + '</b> <small>' + esc(s.de_pos) + '</small><span class="gip-rod-mm">' + s.de_sulco + ' mm</span></span>' +
                        '<i class="fas fa-right-left"></i>' +
                        '<span class="gip-rod-to"><b>' + esc(s.para_serie) + '</b> <small>' + esc(s.para_pos) + '</small><span class="gip-rod-mm">' + s.para_sulco + ' mm</span></span></div>' +
                        '<div class="gip-rod-why">Diferença de <b>' + s.diff + ' mm</b> — trocar o mais gasto pelo mais novo equilibra o desgaste.</div>' +
                        '<div class="gip-rod-btns">' +
                        '<button type="button" class="gip-btn gip-btn--sm gip-rod-go" data-a="' + escAttr(s.de_slot) + '" data-b="' + escAttr(s.para_slot) + '"><i class="fas fa-crosshairs"></i> Destacar</button>' +
                        '<button type="button" class="gip-btn gip-btn--sm gip-btn--primary gip-rod-apply" data-a="' + escAttr(s.de_slot) + '" data-b="' + escAttr(s.para_slot) + '"><i class="fas fa-right-left"></i> Aplicar troca</button>' +
                        '</div></div>';
                }).join('');
            } else {
                html += '<div class="gip-empty" style="padding:18px">' + esc(d.message || 'Sem sugestões.') + '</div>';
            }
            if (d.ranking && d.ranking.length) {
                html += '<h5 class="gip-rod-h">Ranking por desgaste (mais gasto primeiro)</h5><div class="gip-rod-rank">';
                html += d.ranking.map(function (t, idx) {
                    return '<div class="gip-rod-row"><span class="n">' + (idx + 1) + '</span><b>' + esc(t.serie) + '</b><small>' + esc(t.pos) + '</small><span class="mm">' + t.sulco + ' mm</span></div>';
                }).join('') + '</div>';
            }
            body.innerHTML = html;
            body.querySelectorAll('.gip-rod-go').forEach(function (b) {
                b.addEventListener('click', function () {
                    m.classList.remove('open');
                    highlightSlots([b.getAttribute('data-a'), b.getAttribute('data-b')]);
                });
            });
            body.querySelectorAll('.gip-rod-apply').forEach(function (b) {
                b.addEventListener('click', function () {
                    var sa = b.getAttribute('data-a'), sb = b.getAttribute('data-b');
                    gipConfirm({
                        title: 'Aplicar rodízio', icon: 'right-left', confirmText: 'Trocar pneus',
                        message: 'Trocar os pneus entre as duas posições destacadas? As posições e o histórico serão atualizados.'
                    }).then(function (ok) {
                        if (!ok) return;
                        b.disabled = true; b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Aplicando…';
                        postJson(proApi('aplicar_rodizio'), { veiculo_id: vehId, slot_a: sa, slot_b: sb }).then(function (o) {
                            if (!o.ok || !o.d || !o.d.success) { toast((o.d && (o.d.error || o.d.message)) || 'Falha ao aplicar.', 'error'); b.disabled = false; b.innerHTML = '<i class="fas fa-right-left"></i> Aplicar troca'; return; }
                            m.classList.remove('open');
                            toast('Rodízio aplicado.', 'success');
                            reloadVehicle();
                        }).catch(function () { toast('Erro de comunicação.', 'error'); b.disabled = false; b.innerHTML = '<i class="fas fa-right-left"></i> Aplicar troca'; });
                    });
                });
            });
        }).catch(function () { body.innerHTML = '<div class="gip-empty">Erro ao analisar rodízio.</div>'; });
    }
    function highlightSlots(slots) {
        var stage = el('gipStage'); if (!stage) return;
        slots.forEach(function (s) {
            var g = stage.querySelector('.gip-tire[data-slot="' + s + '"]');
            if (g) {
                g.classList.add('gip-rod-flash');
                if (g.scrollIntoView) g.scrollIntoView({ block: 'nearest', inline: 'center' });
                setTimeout(function () { g.classList.remove('gip-rod-flash'); }, 2600);
            }
        });
    }

    // ---- Nome do veículo selecionado (limpo) ----
    function vehName() {
        var sel = el('gipVeiculo'); if (!sel || !sel.selectedOptions || !sel.selectedOptions.length) return '';
        return sel.selectedOptions[0].textContent.replace(/^★\s*/, '').replace(/\s*\(montado\)\s*$/, '').trim();
    }
    function currentTireRows() {
        var rows = [];
        Object.keys(allocated).forEach(function (slot) {
            var t = allocated[slot]; if (!t) return;
            var hm = (t.id && healthMap[t.id]) ? healthMap[t.id] : {};
            rows.push({
                pos: slot,
                serie: t.numero_serie || ('#' + t.id),
                marca: ((t.marca || '') + ' ' + (t.modelo || '')).trim() || '—',
                medida: t.medida || '—',
                sulco: (hm.sulco_atual != null) ? (hm.sulco_atual + ' mm') : '—',
                pct: (hm.pct != null) ? (hm.pct + '%') : '—',
                vida: hm.vida ? (hm.vida + 'ª') : '—',
                status: (hm.health && hm.health.rotulo) || t.status_nome || '—'
            });
        });
        rows.sort(function (a, b) { return a.pos < b.pos ? -1 : (a.pos > b.pos ? 1 : 0); });
        return rows;
    }

    // ---- Exportar / imprimir ----
    function openExport() {
        if (!vehId) { toast('Selecione um veículo primeiro.', 'error'); return; }
        var rows = currentTireRows();
        if (!rows.length) { toast('Este veículo não tem pneus para exportar.', 'error'); return; }
        gipChoose({
            title: 'Exportar pneus', message: 'Como deseja exportar os pneus de ' + vehName() + '?',
            options: [{ label: '🖨️  Imprimir / PDF', value: 'print' }, { label: '⬇️  Baixar CSV', value: 'csv' }]
        }).then(function (v) {
            if (v === 'print') printReport(rows);
            else if (v === 'csv') csvReport(rows);
        });
    }
    function printReport(rows) {
        var head = ['Posição', 'Série', 'Marca/Modelo', 'Medida', 'Sulco', '% Vida', 'Vida', 'Status'];
        var body = rows.map(function (r) {
            return '<tr><td>' + esc(r.pos) + '</td><td>' + esc(r.serie) + '</td><td>' + esc(r.marca) + '</td><td>' + esc(r.medida) +
                '</td><td>' + esc(r.sulco) + '</td><td>' + esc(r.pct) + '</td><td>' + esc(r.vida) + '</td><td>' + esc(r.status) + '</td></tr>';
        }).join('');
        var html = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Pneus — ' + esc(vehName()) + '</title>' +
            '<style>body{font-family:Arial,Helvetica,sans-serif;color:#0f172a;margin:24px}h1{font-size:18px;margin:0 0 4px}.sub{color:#64748b;font-size:12px;margin:0 0 16px}' +
            'table{width:100%;border-collapse:collapse;font-size:12px}th,td{border:1px solid #cbd5e1;padding:6px 8px;text-align:left}th{background:#f1f5f9}tr:nth-child(even) td{background:#f8fafc}' +
            '@media print{button{display:none}}</style></head><body>' +
            '<h1>Relatório de pneus — ' + esc(vehName()) + '</h1>' +
            '<p class="sub">' + rows.length + ' pneu(s) instalado(s) · gerado em ' + new Date().toLocaleString('pt-BR') + '</p>' +
            '<table><thead><tr>' + head.map(function (h) { return '<th>' + h + '</th>'; }).join('') + '</tr></thead><tbody>' + body + '</tbody></table>' +
            '<p style="margin-top:18px"><button onclick="window.print()">Imprimir</button></p>' +
            '<script>setTimeout(function(){window.print();},350);<\/script></body></html>';
        var w = window.open('', '_blank');
        if (!w) { toast('Permita pop-ups para imprimir.', 'error'); return; }
        w.document.open(); w.document.write(html); w.document.close();
    }
    function csvReport(rows) {
        var head = ['Posicao', 'Serie', 'Marca/Modelo', 'Medida', 'Sulco', '% Vida', 'Vida', 'Status'];
        var lines = [head.join(';')].concat(rows.map(function (r) {
            return [r.pos, r.serie, r.marca, r.medida, r.sulco, r.pct, r.vida, r.status].map(function (c) {
                return '"' + String(c).replace(/"/g, '""') + '"';
            }).join(';');
        }));
        var csv = '﻿' + lines.join('\r\n');
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url; a.download = 'pneus_' + (vehName() || 'veiculo').replace(/[^\w-]+/g, '_') + '.csv';
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        setTimeout(function () { URL.revokeObjectURL(url); }, 1500);
        toast('CSV gerado.', 'success');
    }

    // ---- Resumo IA do veículo ----
    function openResumoIA() {
        if (!vehId) { toast('Selecione um veículo primeiro.', 'error'); return; }
        var m = el('gipResumoModal');
        if (!m) {
            m = document.createElement('div');
            m.id = 'gipResumoModal'; m.className = 'gip-modal';
            m.innerHTML = '<div class="gip-modal-box"><div class="gip-modal-head"><h4><i class="fas fa-wand-magic-sparkles" style="color:#8b5cf6"></i> Resumo IA do veículo</h4>' +
                '<button type="button" class="x" data-close>&times;</button></div>' +
                '<div class="gip-modal-body" style="grid-template-columns:1fr"><div id="gipResumoBody" style="max-height:60vh;overflow:auto"></div></div>' +
                '<div class="gip-modal-foot"><span class="msg" style="flex:1"></span><button class="gip-btn gip-btn--sm" data-close>Fechar</button></div></div>';
            document.body.appendChild(m);
            m.addEventListener('click', function (e) { if (e.target === m || e.target.hasAttribute('data-close')) m.classList.remove('open'); });
        }
        var body = el('gipResumoBody');
        body.innerHTML = '<div class="gip-empty"><i class="fas fa-spinner fa-spin"></i> Gerando análise com IA…</div>';
        m.classList.add('open');
        var url = (typeof sfApiUrl === 'function') ? sfApiUrl('gestao_pneu_resumo_ia.php') : '../api/gestao_pneu_resumo_ia.php';
        postJson(url, { veiculo_id: vehId }).then(function (o) {
            if (!o.ok || !o.d || !o.d.success) {
                body.innerHTML = '<div class="gip-empty">' + esc((o.d && (o.d.error || o.d.message)) || 'Não foi possível gerar o resumo.') + '</div>';
                return;
            }
            var meta = o.d.meta || {};
            var chips = '<div class="gip-resumo-chips">' +
                '<span><b>' + (meta.instalados != null ? meta.instalados : '—') + '</b> instalados</span>' +
                '<span class="cr"><b>' + (meta.criticos != null ? meta.criticos : '—') + '</b> críticos</span>' +
                '<span class="at"><b>' + (meta.em_atencao != null ? meta.em_atencao : '—') + '</b> em atenção</span>' +
                '<span><b>' + (meta.sem_medicao != null ? meta.sem_medicao : '—') + '</b> sem medição</span>' +
                '<span><b>' + (meta.sulco_medio_mm != null ? meta.sulco_medio_mm + ' mm' : '—') + '</b> sulco médio</span></div>';
            var acoes = (o.d.acoes && o.d.acoes.length)
                ? '<div class="gip-acoes-title"><i class="fas fa-list-check"></i> Recomendações</div><ul class="gip-acoes">' +
                    o.d.acoes.map(function (a) { return '<li>' + esc(a) + '</li>'; }).join('') + '</ul>'
                : '';
            body.innerHTML = chips +
                (o.d.resumo ? '<div class="gip-resumo-text">' + esc(o.d.resumo).replace(/\n/g, '<br>') + '</div>' : '') +
                acoes;
        }).catch(function () { body.innerHTML = '<div class="gip-empty">Erro de comunicação ao gerar o resumo.</div>'; });
    }

    // ---- Timeline do veículo ----
    function openTimeline() {
        if (!vehId) { toast('Selecione um veículo primeiro.', 'error'); return; }
        var m = el('gipTimelineModal');
        if (!m) {
            m = document.createElement('div');
            m.id = 'gipTimelineModal'; m.className = 'gip-modal';
            m.innerHTML = '<div class="gip-modal-box"><div class="gip-modal-head"><h4><i class="fas fa-timeline" style="color:#3b82f6"></i> Linha do tempo — <span id="gipTlVeh"></span></h4>' +
                '<button type="button" class="x" data-close>&times;</button></div>' +
                '<div class="gip-modal-body" style="grid-template-columns:1fr"><div id="gipTlBody" style="max-height:64vh;overflow:auto"></div></div>' +
                '<div class="gip-modal-foot"><span class="msg" style="flex:1"></span><button class="gip-btn gip-btn--sm" data-close>Fechar</button></div></div>';
            document.body.appendChild(m);
            m.addEventListener('click', function (e) { if (e.target === m || e.target.hasAttribute('data-close')) m.classList.remove('open'); });
        }
        el('gipTlVeh').textContent = vehName() || ('#' + vehId);
        var body = el('gipTlBody');
        body.innerHTML = '<div class="gip-empty"><i class="fas fa-spinner fa-spin"></i> Carregando…</div>';
        m.classList.add('open');
        getJson(proApi('vehicle_timeline') + '&veiculo_id=' + vehId).then(function (res) {
            var list = (res && res.success) ? (res.data || []) : [];
            if (!list.length) { body.innerHTML = '<div class="gip-empty">Sem eventos registrados para este veículo.</div>'; return; }
            body.innerHTML = '<ul class="gip-tl-vert">' + list.map(function (it) {
                return '<li><span class="gip-tl-dot" style="background:' + esc(it.cor || '#94a3b8') + '"><i class="fas ' + esc(it.icon || 'fa-circle') + '"></i></span>' +
                    '<div class="gip-tl-content"><div class="gip-tl-top"><b>' + esc(it.titulo) + '</b><span class="gip-tl-date">' + esc(dateBr(it.data)) + '</span></div>' +
                    (it.desc ? '<div class="gip-tl-desc">' + esc(it.desc) + '</div>' : '') + '</div></li>';
            }).join('') + '</ul>';
        }).catch(function () { body.innerHTML = '<div class="gip-empty">Erro ao carregar a timeline.</div>'; });
    }

    // ---- Saúde geral + heatmap ----
    function vehicleHealth() {
        var scoreByNivel = { bom: 100, novo: 100, atencao: 60, critico: 25, recapagem: 55, descartado: 0 };
        var sum = 0, n = 0;
        Object.keys(allocated).forEach(function (slot) {
            var t = allocated[slot]; if (!t) return;
            var hm = (t.id && healthMap[t.id]) ? healthMap[t.id] : null;
            var nivel = (hm && hm.health) ? hm.health.nivel : 'bom';
            sum += (scoreByNivel[nivel] != null ? scoreByNivel[nivel] : 70); n++;
        });
        return n ? Math.round(sum / n) : null;
    }
    function healthLabel(p) {
        if (p == null) return { t: '—', c: '#94a3b8' };
        if (p >= 85) return { t: 'Excelente', c: '#22c55e' };
        if (p >= 65) return { t: 'Bom', c: '#84cc16' };
        if (p >= 45) return { t: 'Atenção', c: '#f59e0b' };
        return { t: 'Crítico', c: '#ef4444' };
    }
    function openSaude() {
        if (!vehId) { toast('Selecione um veículo primeiro.', 'error'); return; }
        var m = el('gipSaudeModal');
        if (!m) {
            m = document.createElement('div');
            m.id = 'gipSaudeModal'; m.className = 'gip-modal';
            m.innerHTML = '<div class="gip-modal-box"><div class="gip-modal-head"><h4><i class="fas fa-heart-pulse" style="color:#ef4444"></i> Saúde do veículo</h4>' +
                '<button type="button" class="x" data-close>&times;</button></div>' +
                '<div class="gip-modal-body" style="grid-template-columns:1fr"><div id="gipSaudeBody"></div></div>' +
                '<div class="gip-modal-foot"><span class="msg" style="flex:1"></span><button class="gip-btn gip-btn--sm" data-close>Fechar</button></div></div>';
            document.body.appendChild(m);
            m.addEventListener('click', function (e) { if (e.target === m || e.target.hasAttribute('data-close')) m.classList.remove('open'); });
        }
        var p = vehicleHealth(), lbl = healthLabel(p);
        var bar = '<div class="gip-saude-top"><div class="gip-saude-num" style="color:' + lbl.c + '">' + (p != null ? p + '%' : '—') +
            '</div><div class="gip-saude-lbl">' + lbl.t + '</div></div>' +
            '<div class="gip-saude-bar"><i style="width:' + (p != null ? p : 0) + '%;background:' + lbl.c + '"></i></div>';
        // Heatmap: uma linha por eixo, células coloridas por saúde.
        var cav = layout.eixosCaminhao || [], car = layout.eixosCarreta || [];
        function rowsFor(arr, prefix, nome) {
            if (!arr.length) return '';
            var h = '<div class="gip-hm-unit">' + nome + '</div>';
            arr.forEach(function (e, i) {
                var cells = '';
                for (var pos = 0; pos < (e.pneus || 0); pos++) {
                    var slot = prefix + '-' + i + '-' + pos;
                    var t = allocated[slot];
                    var col = '#26303f';
                    if (t) { var hm = (t.id && healthMap[t.id]) ? healthMap[t.id] : null; col = (hm && hm.health) ? hm.health.cor : '#22c55e'; }
                    cells += '<span class="gip-hm-cell" title="' + esc(slot) + '" style="background:' + col + (t ? '' : ';opacity:.35') + '"></span>';
                }
                h += '<div class="gip-hm-row"><span class="gip-hm-eixo">E' + (i + 1) + '</span><div class="gip-hm-cells">' + cells + '</div></div>';
            });
            return h;
        }
        el('gipSaudeBody').innerHTML = bar +
            '<div class="gip-hm-title"><i class="fas fa-grip"></i> Heatmap de desgaste</div>' +
            '<div class="gip-heatmap">' + rowsFor(cav, 'cavalo', 'Cavalo') + rowsFor(car, 'carreta', 'Carreta') + '</div>' +
            '<div class="gip-hm-legend"><span><i style="background:#22c55e"></i> bom</span><span><i style="background:#f59e0b"></i> atenção</span><span><i style="background:#ef4444"></i> crítico</span><span><i style="background:#3b82f6"></i> recapagem</span></div>';
        m.classList.add('open');
    }

    // ---- Ranking + comparar veículos ----
    function openFrota() {
        var m = el('gipFrotaModal');
        if (!m) {
            m = document.createElement('div');
            m.id = 'gipFrotaModal'; m.className = 'gip-modal';
            m.innerHTML = '<div class="gip-modal-box"><div class="gip-modal-head"><h4><i class="fas fa-ranking-star" style="color:#f59e0b"></i> Frota — ranking e comparação</h4>' +
                '<button type="button" class="x" data-close>&times;</button></div>' +
                '<div class="gip-modal-body" style="grid-template-columns:1fr"><div id="gipFrotaBody" style="max-height:64vh;overflow:auto"></div></div>' +
                '<div class="gip-modal-foot"><span class="msg" id="gipFrotaMsg" style="flex:1"></span>' +
                '<button class="gip-btn gip-btn--sm gip-btn--primary" id="gipFrotaCmp"><i class="fas fa-code-compare"></i> Comparar selecionados</button>' +
                '<button class="gip-btn gip-btn--sm" data-close>Fechar</button></div></div>';
            document.body.appendChild(m);
            m.addEventListener('click', function (e) { if (e.target === m || e.target.hasAttribute('data-close')) m.classList.remove('open'); });
            m.querySelector('#gipFrotaCmp').addEventListener('click', function () {
                var sel = m.querySelectorAll('.gip-frota-row input:checked');
                if (sel.length !== 2) { el('gipFrotaMsg').textContent = 'Selecione exatamente 2 veículos.'; return; }
                doCompareVehicles(parseInt(sel[0].value, 10), parseInt(sel[1].value, 10));
            });
        }
        el('gipFrotaMsg').textContent = '';
        var body = el('gipFrotaBody');
        body.innerHTML = '<div class="gip-empty"><i class="fas fa-spinner fa-spin"></i> Carregando frota…</div>';
        m.classList.add('open');
        getJson(proApi('fleet_ranking')).then(function (res) {
            var rows = (res && res.success) ? (res.data || []) : [];
            if (!rows.length) { body.innerHTML = '<div class="gip-empty">Nenhum veículo com pneus instalados.</div>'; return; }
            var byCusto = rows.slice().sort(function (a, b) { return b.custo_total - a.custo_total; });
            var byRecap = rows.slice().sort(function (a, b) { return b.recapagens - a.recapagens; });
            var comKm = rows.filter(function (r) { return r.custo_km > 0; }).sort(function (a, b) { return a.custo_km - b.custo_km; });
            function plate(r) { return r ? (r.placa || ('#' + r.id)) : '—'; }
            var dest = '<div class="gip-frota-dest">' +
                '<div class="gip-frota-card"><span>🏆 Melhor saúde</span><b>' + esc(plate(rows[0])) + '</b><small>' + (rows[0] ? rows[0].saude + '%' : '') + '</small></div>' +
                '<div class="gip-frota-card"><span>💰 Maior custo</span><b>' + esc(plate(byCusto[0])) + '</b><small>' + (byCusto[0] ? money(byCusto[0].custo_total) : '') + '</small></div>' +
                '<div class="gip-frota-card"><span>📉 Menor custo/km</span><b>' + esc(plate(comKm[0])) + '</b><small>' + (comKm[0] ? money(comKm[0].custo_km) : '—') + '</small></div>' +
                '<div class="gip-frota-card"><span>♻️ Mais recapagens</span><b>' + esc(plate(byRecap[0])) + '</b><small>' + (byRecap[0] ? byRecap[0].recapagens : '') + '</small></div>' +
                '</div>';
            var tbl = '<div class="gip-frota-list">' + rows.map(function (r) {
                var lbl = healthLabel(r.saude);
                return '<label class="gip-frota-row"><input type="checkbox" value="' + r.id + '">' +
                    '<span class="pl"><b>' + esc(r.placa || ('#' + r.id)) + '</b><small>' + esc(r.modelo || '') + '</small></span>' +
                    '<span class="hb"><i style="width:' + r.saude + '%;background:' + lbl.c + '"></i></span>' +
                    '<span class="sa" style="color:' + lbl.c + '">' + r.saude + '%</span>' +
                    '<span class="cu">' + (r.custo_total ? money(r.custo_total) : '—') + '</span>' +
                    '<span class="re">' + r.recapagens + ' recap.</span></label>';
            }).join('') + '</div>';
            body.innerHTML = dest + '<div class="gip-frota-hint">Marque 2 veículos e clique em “Comparar selecionados”.</div>' + tbl;
        }).catch(function () { body.innerHTML = '<div class="gip-empty">Erro ao carregar a frota.</div>'; });
    }
    function doCompareVehicles(idA, idB) {
        var m = el('gipFrotaModal'); var body = el('gipFrotaBody');
        body.innerHTML = '<div class="gip-empty"><i class="fas fa-spinner fa-spin"></i> Comparando…</div>';
        Promise.all([
            getJson(proApi('vehicle_kpis') + '&veiculo_id=' + idA),
            getJson(proApi('vehicle_kpis') + '&veiculo_id=' + idB)
        ]).then(function (arr) {
            var a = arr[0] && arr[0].success ? arr[0].data : null;
            var b = arr[1] && arr[1].success ? arr[1].data : null;
            if (!a || !b) { body.innerHTML = '<div class="gip-empty">Não foi possível comparar.</div>'; return; }
            function nm(id) { var o = el('gipVeiculo').querySelector('option[value="' + id + '"]'); return o ? o.textContent.replace(/^★\s*/, '').replace(/\s*\(montado\)\s*$/, '') : ('#' + id); }
            var rows = [
                ['Total de pneus', a.total, b.total],
                ['Em uso', a.em_uso, b.em_uso],
                ['Em alerta', a.em_alerta, b.em_alerta],
                ['Críticos', a.criticos, b.criticos],
                ['Vida média', a.vida_media + 'ª', b.vida_media + 'ª'],
                ['Sem medição', a.sem_medicao, b.sem_medicao],
                ['Custo total', a.custo_total ? money(a.custo_total) : '—', b.custo_total ? money(b.custo_total) : '—'],
                ['Custo / km', a.custo_km ? money(a.custo_km) : '—', b.custo_km ? money(b.custo_km) : '—']
            ];
            var html = '<button class="gip-btn gip-btn--sm" id="gipFrotaBack" style="margin-bottom:12px"><i class="fas fa-arrow-left"></i> Voltar ao ranking</button>' +
                '<div class="gip-cmp-heads"><div class="gip-cmp-head"><b>' + esc(nm(idA)) + '</b></div><div class="gip-cmp-head"><b>' + esc(nm(idB)) + '</b></div></div><div class="gip-cmp-rows">' +
                rows.map(function (r) { return '<div class="gip-cmp-row"><span class="k">' + r[0] + '</span><span class="v">' + esc(String(r[1])) + '</span><span class="v">' + esc(String(r[2])) + '</span></div>'; }).join('') + '</div>';
            body.innerHTML = html;
            el('gipFrotaBack').addEventListener('click', openFrota);
        }).catch(function () { body.innerHTML = '<div class="gip-empty">Erro ao comparar.</div>'; });
    }

    // ---- Atualizar odômetro ----
    function openKm() {
        if (!vehId) { toast('Selecione um veículo primeiro.', 'error'); return; }
        var m = el('gipKmModal');
        if (!m) {
            m = document.createElement('div');
            m.id = 'gipKmModal'; m.className = 'gip-modal';
            m.innerHTML = '<div class="gip-modal-box gip-modal-sm"><div class="gip-modal-head"><h4><i class="fas fa-gauge-high" style="color:#3b82f6"></i> Atualizar odômetro</h4>' +
                '<button type="button" class="x" data-close>&times;</button></div>' +
                '<div class="gip-modal-body" style="grid-template-columns:1fr"><p class="gip-confirm-msg" style="margin:0 0 8px">Informe o KM atual do veículo. Alimenta o cálculo de custo/km dos pneus.</p>' +
                '<input type="number" id="gipKmInput" min="0" step="1" placeholder="Ex.: 152340" style="width:100%;padding:9px 11px;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-tertiary,var(--bg-secondary));color:var(--text-primary)">' +
                '<span class="msg" id="gipKmMsg" style="display:block;margin-top:6px"></span></div>' +
                '<div class="gip-modal-foot"><button class="gip-btn gip-btn--sm" data-close>Cancelar</button><button class="gip-btn gip-btn--sm gip-btn--primary" id="gipKmSave"><i class="fas fa-floppy-disk"></i> Salvar</button></div></div>';
            document.body.appendChild(m);
            m.addEventListener('click', function (e) { if (e.target === m || e.target.hasAttribute('data-close')) m.classList.remove('open'); });
            m.querySelector('#gipKmSave').addEventListener('click', function () {
                var km = parseInt(el('gipKmInput').value, 10);
                var msg = el('gipKmMsg'); msg.style.color = '';
                if (isNaN(km) || km < 0) { msg.style.color = '#ef4444'; msg.textContent = 'Informe um KM válido.'; return; }
                msg.textContent = 'Salvando…';
                postJson(proApi('set_odometro'), { veiculo_id: vehId, km: km }).then(function (o) {
                    if (!o.ok || !o.d || !o.d.success) { msg.style.color = '#ef4444'; msg.textContent = (o.d && (o.d.error || o.d.message)) || 'Falha ao salvar.'; return; }
                    m.classList.remove('open');
                    toast('Odômetro atualizado para ' + km.toLocaleString('pt-BR') + ' km.', 'success');
                    loadFleetKpis();
                    if (curPneuId) refreshAfterAction();
                }).catch(function () { msg.style.color = '#ef4444'; msg.textContent = 'Erro de comunicação.'; });
            });
        }
        el('gipKmInput').value = '';
        el('gipKmMsg').textContent = '';
        m.classList.add('open');
        setTimeout(function () { el('gipKmInput').focus(); }, 60);
    }

    // ---- Adicionar / excluir eixos ----
    function adicionarEixo(unit) {
        if (!vehId) { toast('Selecione um veículo primeiro.', 'error'); return; }
        var nome = (unit === 'caminhao') ? 'Caminhão' : 'Carreta';
        var m = el('gipEixoAddModal');
        if (!m) {
            m = document.createElement('div');
            m.id = 'gipEixoAddModal'; m.className = 'gip-modal';
            m.innerHTML = '<div class="gip-modal-box gip-modal-sm"><div class="gip-modal-head"><h4 id="gipEixoAddTitle">Adicionar eixo</h4>' +
                '<button type="button" class="x" data-close>&times;</button></div>' +
                '<div class="gip-modal-body" style="grid-template-columns:1fr"><p class="gip-confirm-msg" style="margin:0 0 4px">Escolha o tipo de eixo:</p>' +
                '<div class="gip-eixo-opts">' +
                '<button type="button" class="gip-eixo-opt" data-qtd="2"><span class="gip-eixo-opt-ico"><i class="fas fa-circle"></i><i class="fas fa-circle"></i></span><b>Simples</b><span>2 pneus (1 por lado)</span></button>' +
                '<button type="button" class="gip-eixo-opt" data-qtd="4"><span class="gip-eixo-opt-ico"><i class="fas fa-circle"></i><i class="fas fa-circle"></i><span class="gap"></span><i class="fas fa-circle"></i><i class="fas fa-circle"></i></span><b>Duplo</b><span>4 pneus (2 por lado)</span></button>' +
                '</div></div></div>';
            document.body.appendChild(m);
            m.addEventListener('click', function (e) {
                if (e.target === m || e.target.hasAttribute('data-close')) { m.classList.remove('open'); return; }
                var opt = e.target.closest('.gip-eixo-opt'); if (!opt) return;
                m.classList.remove('open');
                doAddEixo(m._unit, m._nome, parseInt(opt.getAttribute('data-qtd'), 10));
            });
        }
        m._unit = unit; m._nome = nome;
        el('gipEixoAddTitle').textContent = 'Adicionar eixo — ' + nome;
        m.classList.add('open');
    }
    function doAddEixo(unit, nome, qtd) {
        postJson(EIXOS_API + '?action=adicionar_eixo', { veiculo_id: vehId, tipo_veiculo: unit, quantidade_pneus: qtd }).then(function (o) {
            if (!o.ok || !o.d.success) { toast((o.d && o.d.error) || 'Falha ao adicionar eixo.', 'error'); return; }
            toast('Eixo ' + (qtd === 4 ? 'duplo' : 'simples') + ' adicionado ao ' + nome + '.', 'success');
            customAxleXs = null; customTankX = null;   // mudou a contagem de eixos
            reloadVehicle();
        }).catch(function () { toast('Erro de comunicação.', 'error'); });
    }
    function excluirEixo(unit) {
        if (!vehId) { toast('Selecione um veículo primeiro.', 'error'); return; }
        var prefix = (unit === 'caminhao') ? 'cavalo' : 'carreta';
        var arr = (unit === 'caminhao') ? (layout.eixosCaminhao || []) : (layout.eixosCarreta || []);
        var nome = (unit === 'caminhao') ? 'Caminhão' : 'Carreta';
        if (!arr.length) { toast('Não há eixos no ' + nome + ' para excluir.', 'error'); return; }
        // do último para o primeiro, acha um eixo sem pneus alocados
        var alvo = null, alvoIdx = -1;
        for (var i = arr.length - 1; i >= 0; i--) {
            var ocupado = Object.keys(allocated).some(function (k) { return k.indexOf(prefix + '-' + i + '-') === 0; });
            if (!ocupado) { alvo = arr[i]; alvoIdx = i; break; }
        }
        if (!alvo) { toast('Remova os pneus de um eixo do ' + nome + ' antes de excluí-lo.', 'error'); return; }
        gipConfirm({
            title: 'Excluir eixo', icon: 'trash', danger: true, confirmText: 'Excluir',
            message: 'Deseja excluir o eixo ' + (alvoIdx + 1) + ' do ' + nome + '? Essa ação não pode ser desfeita.'
        }).then(function (ok) {
            if (!ok) return;
            postJson(EIXOS_API + '?action=excluir_eixo', { eixo_id: alvo.id }).then(function (o) {
                if (!o.ok || !o.d.success) { toast((o.d && o.d.error) || 'Falha ao excluir eixo.', 'error'); return; }
                toast('Eixo excluído do ' + nome + '.', 'success');
                customAxleXs = null; customTankX = null;
                reloadVehicle();
            }).catch(function () { toast('Erro de comunicação.', 'error'); });
        });
    }

    // ---- Arrastar pneu para trocar de posição (modo normal) ----
    function onTireSwapMove(e) {
        if (!_tireSwap) return;
        if (!_tireSwap.moved) {
            if (Math.abs(e.clientX - _tireSwap.sx) + Math.abs(e.clientY - _tireSwap.sy) < 6) return;
            _tireSwap.moved = true;
            document.body.style.cursor = 'grabbing';
            if (_tireSwap.el) { _tireSwap.el.style.opacity = '.45'; _tireSwap.el.style.pointerEvents = 'none'; }
            el('gipStage').querySelectorAll('.gip-tire.gip-slot-empty').forEach(function (g) { g.classList.add('gip-drop-target'); });
        }
    }
    function onTireSwapEnd(e) {
        document.removeEventListener('mousemove', onTireSwapMove);
        document.removeEventListener('mouseup', onTireSwapEnd);
        if (!_tireSwap) return;
        var moved = _tireSwap.moved, fromSlot = _tireSwap.fromSlot, dragEl = _tireSwap.el;
        document.body.style.cursor = '';
        if (dragEl) { dragEl.style.opacity = ''; dragEl.style.pointerEvents = ''; }
        el('gipStage').querySelectorAll('.gip-drop-target').forEach(function (g) { g.classList.remove('gip-drop-target'); });
        _tireSwap = null;
        if (!moved) return;               // foi clique → abre o painel normalmente
        _didSwapDrag = true;              // suprime o click que vem em seguida
        var under = document.elementFromPoint(e.clientX, e.clientY);
        var target = under ? under.closest('.gip-tire.gip-slot-empty') : null;
        if (!target) { toast('Solte sobre uma posição vazia para trocar.', 'error'); return; }
        var toSlot = target.getAttribute('data-slot');
        if (!toSlot || toSlot === fromSlot) return;
        postJson(EIXOS_API + '?action=mover_pneu', { from_slot_id: fromSlot, to_slot_id: toSlot }).then(function (o) {
            if (!o.ok || !o.d.success) { toast((o.d && o.d.error) || 'Falha ao mover.', 'error'); return; }
            toast('Pneu movido para a nova posição.', 'success'); reloadVehicle();
        }).catch(function () { toast('Erro de comunicação.', 'error'); });
    }

    // ---- Eventos ----
    function wire() {
        loadVehicles();   // popula o dropdown primeiro e, ao final, carrega os KPIs no escopo certo

        el('gipVeiculo').addEventListener('change', function () { selectVehicle(this.value); });
        el('gipRefresh').addEventListener('click', function () { loadFleetKpis(); if (vehId) selectVehicle(vehId); });
        var bRes = el('gipResumoIA'); if (bRes) bRes.addEventListener('click', openResumoIA);
        var bKm = el('gipKm'); if (bKm) bKm.addEventListener('click', openKm);
        var bExp = el('gipExport'); if (bExp) bExp.addEventListener('click', openExport);
        var bFrota = el('gipFrota'); if (bFrota) bFrota.addEventListener('click', openFrota);
        var bSaude = el('gipSaude'); if (bSaude) bSaude.addEventListener('click', openSaude);
        var bTl = el('gipTimelineBtn'); if (bTl) bTl.addEventListener('click', openTimeline);
        ['gipStatus', 'gipVida', 'gipExtra'].forEach(function (id) { var x = el(id); if (x) x.addEventListener('change', onManualFilter); });
        el('gipSoAlerta').addEventListener('change', onManualFilter);
        wireKpis();

        // Clicar num item da legenda filtra por aquele estado (clique de novo limpa).
        var legend = el('gipLegend');
        if (legend) {
            legend.querySelectorAll('span[data-leg]').forEach(function (sp) { sp.style.cursor = 'pointer'; });
            legend.addEventListener('click', function (e) {
                var sp = e.target.closest('span[data-leg]'); if (!sp) return;
                var leg = sp.getAttribute('data-leg');
                var active = sp.classList.contains('leg-active');
                legend.querySelectorAll('.leg-active').forEach(function (x) { x.classList.remove('leg-active'); });
                clearKpiActive();
                if (active) { setFiltersTo({}); return; }
                sp.classList.add('leg-active');
                if (leg === 'reset') setFiltersTo({ somenteVazios: true });
                else if (leg === 'sem_calibragem') setFiltersTo({ extra: 'sem_calibragem' });
                else setFiltersTo({ status: leg });
            });
        }

        // Restaura a visão (Tudo/Cavalo/Carreta) salva.
        var sv = lsGet('gip_view');
        if (sv === 'cavalo' || sv === 'carreta' || sv === 'all') { viewMode = sv; }

        var stage = el('gipStage');
        stage.addEventListener('mousedown', onStageMouseDown);
        stage.addEventListener('click', function (e) { if (assemblyMode) return; if (_didSwapDrag) { _didSwapDrag = false; return; } var g = e.target.closest('.gip-tire'); if (g) onTireClick(g); });
        // Duplo-clique num pneu → abre direto a medição de sulco.
        stage.addEventListener('dblclick', function (e) {
            if (assemblyMode) return;
            var g = e.target.closest('.gip-tire[data-pneu]'); if (!g) return;
            onTireClick(g);
            if (curPneuId) openModal('gipSulcoModal');
        });
        stage.addEventListener('keydown', function (e) {
            if (assemblyMode) return;
            if (e.key !== 'Enter' && e.key !== ' ' && e.key !== 'Spacebar') return;
            var g = e.target.closest && e.target.closest('.gip-tire'); if (!g) return;
            e.preventDefault(); onTireClick(g);
        });
        stage.addEventListener('mouseover', function (e) { if (assemblyMode) return; var g = e.target.closest('.gip-tire'); if (g) showTooltip(g, e); });
        stage.addEventListener('mousemove', function (e) { if (assemblyMode) return; if (el('gipTooltip').classList.contains('show')) moveTooltip(e); });
        stage.addEventListener('mouseout', function (e) { if (assemblyMode) return; var g = e.target.closest('.gip-tire'); if (g && !e.relatedTarget) hideTooltip(); else if (g && !g.contains(e.relatedTarget)) hideTooltip(); });

        var bMontar = el('gipMontar'); if (bMontar) bMontar.addEventListener('click', toggleAssembly);
        var bAuto = el('gipAutoMontagem'); if (bAuto) bAuto.addEventListener('click', resetAssembly);
        var bRod = el('gipRodizio'); if (bRod) bRod.addEventListener('click', openRodizio);

        var zoom = el('gipZoom');
        if (zoom) zoom.addEventListener('click', function (e) {
            var b = e.target.closest('button[data-z]'); if (!b) return;
            var z = b.getAttribute('data-z');
            if (z === 'in') setZoom(stageZoom * 1.25);
            else if (z === 'out') setZoom(stageZoom / 1.25);
            else setZoom(1);
        });

        // Seletor de visão: Tudo / Cavalo / Carreta
        var viewToggle = el('gipViewToggle');
        if (viewToggle) {
            // Reflete a visão salva nos botões.
            viewToggle.querySelectorAll('button').forEach(function (x) { x.classList.toggle('active', x.getAttribute('data-view') === viewMode); });
            viewToggle.addEventListener('click', function (e) {
                var b = e.target.closest('button[data-view]'); if (!b) return;
                var v = b.getAttribute('data-view');
                if (v === viewMode) return;
                viewMode = v;
                lsSet('gip_view', viewMode);
                viewToggle.querySelectorAll('button').forEach(function (x) { x.classList.toggle('active', x === b); });
                if (assemblyMode) toggleAssembly();   // sai do modo montagem ao trocar de visão
                renderStage();
            });
        }

        // Menu Eixos (adicionar / excluir / salvar layout)
        var eixosBtn = el('gipEixosBtn'), eixosMenu = el('gipEixosMenu');
        if (eixosBtn && eixosMenu) {
            eixosBtn.addEventListener('click', function (e) { e.stopPropagation(); eixosMenu.hidden = !eixosMenu.hidden; });
            document.addEventListener('click', function () { eixosMenu.hidden = true; });
            eixosMenu.addEventListener('click', function (e) {
                var b = e.target.closest('button[data-act]'); if (!b) return;
                e.stopPropagation(); eixosMenu.hidden = true;
                var a = b.getAttribute('data-act');
                if (a === 'add-cav') adicionarEixo('caminhao');
                else if (a === 'add-car') adicionarEixo('carreta');
                else if (a === 'del-cav') excluirEixo('caminhao');
                else if (a === 'del-car') excluirEixo('carreta');
                else if (a === 'save') saveMontagem();
            });
        }

        document.querySelectorAll('.gip-modal [data-close]').forEach(function (b) {
            b.addEventListener('click', function () { b.closest('.gip-modal').classList.remove('open'); });
        });
        document.querySelectorAll('.gip-modal').forEach(function (m) {
            m.addEventListener('click', function (e) { if (e.target === m) m.classList.remove('open'); });
        });
        el('sulcoSalvar').addEventListener('click', submitSulco);
        el('calibSalvar').addEventListener('click', submitCalib);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', wire);
    else wire();
})();
