/**
 * Mapa da frota — Leaflet (OSM) + Google Maps com alternância.
 * Chave Google: Configurações > Google Maps (mesmo fluxo de rotas).
 */
(function () {
    'use strict';

    var MAP_STORAGE_KEY = 'mapaFrotaMapProvider';

    var statusEl = document.getElementById('mapaFrotaStatus');
    var mapLeafletEl = document.getElementById('mapaFrotaLeaflet');
    var mapGoogleEl = document.getElementById('mapaFrotaGoogle');
    var selHistVeiculo = document.getElementById('histVeiculo');
    var selRtVeiculo = document.getElementById('rtVeiculo');
    var inpIni = document.getElementById('histIni');
    var inpFim = document.getElementById('histFim');
    var chkRt = document.getElementById('rtAtivo');
    var searchEl = document.getElementById('searchRoute');
    var driverFilter = document.getElementById('driverFilter');

    var mapaPosicoesRaw = [];
    var optCercas = document.getElementById('optMostrarCercas');
    var optAlertas = document.getElementById('optMostrarAlertasLista');
    var mapaOpcoes = document.getElementById('mapaOpcoesPanel');

    var savedGooglePreference = localStorage.getItem(MAP_STORAGE_KEY) === 'google';
    var mapProvider = 'leaflet';

    var map = null;
    var layerCercas = null;
    var layerHistorico = null;
    var layerRealtime = null;
    var rtTimer = null;
    var cercasTimer = null;
    var alertasTimer = null;
    /** Markers de tempo real (Leaflet) por id de veículo — reaproveitados entre atualizações. */
    var rtMarkers = {};
    /** Assinatura do conjunto de veículos exibido — evita re-enquadrar o mapa a cada atualização. */
    var rtFitSignature = '';
    /** Id do veículo sendo "seguido" (mapa centraliza nele a cada atualização). */
    var followId = null;
    /** Momento da última atualização de posições bem-sucedida (para o "atualizado há Xs"). */
    var lastRtUpdate = null;

    var gMap = null;
    /** @type {{ ref: object, cleanup?: function(): void }[]} */
    var gMarkers = [];
    var gCircles = [];
    var gHistPolyline = null;
    /** @type {{ ref: object, cleanup?: function(): void }[]} */
    var gHistMarkers = [];

    /** InfoWindow compartilhada (tempo real / histórico Google) — hover como no Leaflet */
    var gRtInfoWindow = null;
    var gRtInfoCloseTimer = null;

    /** Última rota histórica carregada (para redesenhar ao trocar de mapa) */
    var histCache = null;

    var MAP_MARKER_CAP = 300;
    var mapaCapAviso = false;

    function fillVeiculoSelect(selectEl, list, placeholderTodos) {
        if (!selectEl) return;
        var prev = selectEl.value;
        selectEl.innerHTML = '';
        var opt0 = document.createElement('option');
        opt0.value = '';
        opt0.textContent = placeholderTodos;
        selectEl.appendChild(opt0);
        list.forEach(function (v) {
            var o = document.createElement('option');
            o.value = v.id;
            o.textContent = (v.placa || '') + (v.modelo ? ' — ' + v.modelo : '');
            selectEl.appendChild(o);
        });
        if (prev) selectEl.value = prev;
    }

    function getRtVeiculoId() {
        return selRtVeiculo ? String(selRtVeiculo.value || '').trim() : '';
    }

    function getHistVeiculoId() {
        return selHistVeiculo ? String(selHistVeiculo.value || '').trim() : '';
    }

    function apiPosicoesUrl() {
        if (typeof sfApiUrl === 'function') return sfApiUrl('gps_posicoes.php');
        return '../api/gps_posicoes.php';
    }
    function apiHistoricoUrl(vid, ini, fim) {
        var q = 'veiculo_id=' + encodeURIComponent(vid);
        if (ini) q += '&data_inicio=' + encodeURIComponent(ini.replace('T', ' '));
        if (fim) q += '&data_fim=' + encodeURIComponent(fim.replace('T', ' '));
        if (typeof sfApiUrl === 'function') return sfApiUrl('gps_historico.php?' + q);
        return '../api/gps_historico.php?' + q;
    }
    function apiVeiculosUrl() {
        if (typeof sfApiUrl === 'function') return sfApiUrl('veiculos.php?action=list');
        return '../api/veiculos.php?action=list';
    }
    function apiCercasUrl() {
        if (typeof sfApiUrl === 'function') return sfApiUrl('gps_cercas.php');
        return '../api/gps_cercas.php';
    }
    function apiAlertasUrl() {
        if (typeof sfApiUrl === 'function') return sfApiUrl('gps_cerca_alertas.php?limite=200');
        return '../api/gps_cerca_alertas.php?limite=200';
    }
    function apiAlertasOperacionaisUrl() {
        var q = 'limite=120';
        var vid = getRtVeiculoId();
        if (vid) q += '&veiculo_id=' + encodeURIComponent(vid);
        if (typeof sfApiUrl === 'function') return sfApiUrl('gps_alertas_operacionais.php?' + q);
        return '../api/gps_alertas_operacionais.php?' + q;
    }
    function googleMapsConfigUrl() {
        if (typeof sfAppUrl === 'function') return sfAppUrl('google-maps/api.php?action=get_config');
        return '../google-maps/api.php?action=get_config';
    }

    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function toLocalDt(d) {
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }
    function escapeHtml(s) {
        if (s == null) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
    /** Sempre exibe unidade km/h quando há número (evita NaN e strings vazias). */
    function formatVelocidadeKmH(v) {
        if (v === null || v === undefined) return '—';
        if (typeof v === 'string' && v.trim() === '') return '—';
        var n = Number(v);
        if (!isFinite(n)) return '—';
        return Math.round(n) + ' km/h';
    }
    function formatBateriaPct(p) {
        if (p === null || p === undefined || p === '') return '—';
        var n = Number(p);
        if (!isFinite(n)) return '—';
        n = Math.round(n);
        if (n < 0 || n > 100) return '—';
        return n + '%';
    }
    function formatGpsStatus(s) {
        if (s == null || s === '') return '—';
        var k = String(s).toLowerCase();
        var map = { parado: 'Parado', movimento: 'Movimento', ocioso: 'Ocioso' };
        return map[k] || escapeHtml(String(s));
    }
    function formatAccuracyM(a) {
        if (a === null || a === undefined || a === '') return null;
        var n = Number(a);
        if (!isFinite(n) || n < 0) return null;
        return '±' + Math.round(n) + ' m';
    }
    /** Idade da última posição em minutos (null se não der pra calcular). */
    function posIdadeMin(p) {
        if (!p) return null;
        // Preferir a idade calculada no servidor (NOW() do MySQL) — imune a fuso.
        if (p.idade_seg != null && isFinite(Number(p.idade_seg))) {
            return Number(p.idade_seg) / 60;
        }
        // Fallback: parse de data_hora contra o relógio do navegador (sujeito a fuso).
        if (p.data_hora == null || p.data_hora === '') return null;
        var d = new Date(String(p.data_hora).replace(' ', 'T'));
        if (isNaN(d.getTime())) return null;
        return (Date.now() - d.getTime()) / 60000;
    }
    var STALE_MIN = 30;
    function posSemSinal(p) {
        var m = posIdadeMin(p);
        return m != null && m > STALE_MIN;
    }
    /** Cor do veículo no mapa conforme situação: sem sinal, movimento, ocioso, parado. */
    function posStatusColor(p) {
        if (posSemSinal(p)) return '#9ca3af'; // cinza — sem sinal recente
        var s = String((p && p.status) || '').toLowerCase();
        if (s === 'movimento') return '#16a34a'; // verde
        if (s === 'ocioso') return '#f59e0b';     // amarelo
        return '#dc2626';                          // vermelho — parado/desconhecido
    }
    /** divIcon do marker de tempo real com a cor da situação. */
    function rtDivIcon(cor) {
        return L.divIcon({
            className: 'sf-map-marker-rt',
            html: '<div style="width:12px;height:12px;background:' + cor + ';border:2px solid #fff;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,.4);"></div>',
            iconSize: [16, 16], iconAnchor: [8, 8]
        });
    }
    function buildPosicaoPopupHtml(p) {
        var title = (p.placa || 'Veículo') + ' · ' + (p.motorista_nome || 'Motorista');
        var vel = formatVelocidadeKmH(p.velocidade);
        var bat = formatBateriaPct(p.bateria_pct);
        var when = p.data_hora != null ? String(p.data_hora) : '—';
        var sts = formatGpsStatus(p.status);
        var acc = formatAccuracyM(p.accuracy_metros);
        var endereco = (p.endereco != null && String(p.endereco).trim() !== '') ? String(p.endereco) : '';
        var sub = [];
        if (acc) sub.push('Prec.: ' + acc);
        if (p.provider) sub.push(escapeHtml(String(p.provider)));
        if (p.location_mock === 1 || p.location_mock === '1') sub.push('<span style="color:#b45309">mock</span>');
        var subHtml = sub.length
            ? '<div class="sf-mapa-frota-balao__sub">' + sub.join(' · ') + '</div>'
            : '';
        var endHtml = endereco
            ? '<div class="sf-mapa-frota-balao__end"><i class="fas fa-location-dot" aria-hidden="true"></i> ' + escapeHtml(endereco) + '</div>'
            : '';
        var navHtml = (p.latitude != null && p.longitude != null)
            ? '<div class="sf-mapa-frota-balao__nav"><a href="https://www.google.com/maps/dir/?api=1&destination=' +
                encodeURIComponent(p.latitude + ',' + p.longitude) + '" target="_blank" rel="noopener">Abrir no Google Maps →</a></div>'
            : '';
        return (
            '<div class="sf-mapa-frota-balao">' +
            '<div class="sf-mapa-frota-balao__tit"><strong>' + escapeHtml(title) + ' (última)</strong></div>' +
            '<div class="sf-mapa-frota-balao__meta" role="text">' +
            '<span>Vel.: ' + vel + '</span>' +
            '<span class="sf-mapa-frota-balao__sep" aria-hidden="true">·</span>' +
            '<span>Sts.: ' + sts + '</span>' +
            '<span class="sf-mapa-frota-balao__sep" aria-hidden="true">·</span>' +
            '<span>Bat.: ' + bat + '</span>' +
            '<span class="sf-mapa-frota-balao__sep" aria-hidden="true">·</span>' +
            '<span class="sf-mapa-frota-balao__dt">' + escapeHtml(when) + '</span>' +
            '</div>' + endHtml + subHtml + navHtml + '</div>'
        );
    }
    function setPresetHours(h) {
        var fim = new Date();
        var ini = new Date(fim.getTime() - h * 3600000);
        inpIni.value = toLocalDt(ini);
        inpFim.value = toLocalDt(fim);
    }

    function filterPosicoes(list) {
        var q = (searchEl && searchEl.value ? searchEl.value : '').trim().toLowerCase();
        var mid = driverFilter ? String(driverFilter.value || '') : '';
        var vidRt = getRtVeiculoId();

        var out = list.slice();
        if (vidRt) {
            out = out.filter(function (p) { return String(p.veiculo_id || '') === vidRt; });
        }
        if (mid) {
            out = out.filter(function (p) { return String(p.motorista_id || '') === mid; });
        }
        if (q) {
            out = out.filter(function (p) {
                var placa = (p.placa || '').toLowerCase();
                var mot = (p.motorista_nome || '').toLowerCase();
                var mod = (p.modelo || '').toLowerCase();
                var end = (p.endereco || '').toLowerCase();
                return placa.indexOf(q) !== -1 || mot.indexOf(q) !== -1 || mod.indexOf(q) !== -1 || end.indexOf(q) !== -1;
            });
        }
        mapaCapAviso = false;
        if (!vidRt && out.length > MAP_MARKER_CAP) {
            mapaCapAviso = true;
            out = out.slice(0, MAP_MARKER_CAP);
        }
        return out;
    }

    /** Tabela resumo abaixo dos alertas (mesmos filtros do mapa). */
    function renderListaVeiculosResumo(list) {
        var tbody = document.getElementById('mapaListaVeiculosTbody');
        var emptyEl = document.getElementById('mapaListaVeiculosEmpty');
        var wrap = document.getElementById('mapaListaVeiculosTableWrap');
        if (!tbody) return;
        tbody.innerHTML = '';
        if (!list || !list.length) {
            if (emptyEl) emptyEl.hidden = false;
            if (wrap) wrap.style.display = 'none';
            return;
        }
        if (emptyEl) emptyEl.hidden = true;
        if (wrap) wrap.style.display = '';
        var sorted = list.slice().sort(function (a, b) {
            return String(a.placa || '').localeCompare(String(b.placa || ''), 'pt', { sensitivity: 'base' });
        });
        sorted.forEach(function (p) {
            var tr = document.createElement('tr');
            var sts = String(p.status || '').toLowerCase();
            var badgeCls = 'badge-sts ';
            if (sts === 'movimento') {
                badgeCls += 'badge-sts--mov';
            } else if (sts === 'ocioso') {
                badgeCls += 'badge-sts--oci';
            } else {
                badgeCls += 'badge-sts--par';
            }
            var stsLabel = formatGpsStatus(p.status);
            var vel = formatVelocidadeKmH(p.velocidade);
            var bat = formatBateriaPct(p.bateria_pct);
            var acc = formatAccuracyM(p.accuracy_metros);
            var mock = (p.location_mock === 1 || p.location_mock === '1')
                ? ' <span class="badge-mock" title="Localização fictícia">MOCK</span>'
                : '';
            var semSinal = posSemSinal(p);
            var seguindo = followId != null && String(p.veiculo_id) === String(followId);
            if (semSinal) tr.classList.add('is-stale');
            if (seguindo) tr.classList.add('is-following');
            if (p.veiculo_id != null) {
                tr.dataset.veiculoId = String(p.veiculo_id);
                tr.style.cursor = 'pointer';
                tr.title = 'Clique para centralizar no mapa';
                tr.tabIndex = 0;
                tr.setAttribute('role', 'button');
                tr.setAttribute('aria-label', 'Centralizar ' + (p.placa || 'veículo') + ' no mapa');
            }
            var seguirBtn = (p.veiculo_id != null)
                ? '<button type="button" class="mapa-follow-btn' + (seguindo ? ' is-on' : '') + '" data-follow-id="' + escapeHtml(String(p.veiculo_id)) +
                    '" title="' + (seguindo ? 'Parar de seguir' : 'Seguir este veículo') + '"><i class="fas fa-' + (seguindo ? 'crosshairs' : 'location-arrow') + '"></i></button>'
                : '';
            tr.innerHTML =
                '<td class="cell-placa">' + escapeHtml(p.placa || '—') + mock + (semSinal ? ' <span class="badge-stale" title="Sem sinal há mais de 30 min">SEM SINAL</span>' : '') + '</td>' +
                '<td>' + escapeHtml(p.modelo || '—') + '</td>' +
                '<td>' + escapeHtml(p.motorista_nome || '—') + '</td>' +
                '<td><span class="' + badgeCls + '">' + stsLabel + '</span></td>' +
                '<td>' + vel + '</td>' +
                '<td>' + bat + '</td>' +
                '<td>' + (acc || '—') + '</td>' +
                '<td>' + escapeHtml(p.data_hora != null ? String(p.data_hora) : '—') + ' ' + seguirBtn + '</td>';
            tbody.appendChild(tr);
        });
    }

    /** Atualiza os KPIs do topo que dependem do tempo real (frota inteira, sem filtro). */
    function atualizarKpisTempoReal() {
        var recente = 0, semSinal = 0;
        mapaPosicoesRaw.forEach(function (p) {
            var m = posIdadeMin(p);
            if (m == null) return;
            if (m <= 15) recente++;
            else if (m > 30) semSinal++;
        });
        var elR = document.getElementById('kpiSinalRecente');
        var elS = document.getElementById('kpiSemSinal');
        if (elR) elR.textContent = recente;
        if (elS) elS.textContent = semSinal;
    }

    /** Centraliza o mapa no veículo e abre o popup (chamado ao clicar na linha da tabela). */
    function focusVeiculoNoMapa(vid) {
        if (vid == null) return;
        var svid = String(vid);
        if (mapProvider === 'leaflet') {
            var mk = rtMarkers[svid];
            if (mk) {
                // Se estiver dentro de um cluster, expande até o marker antes de abrir.
                if (typeof layerRealtime.zoomToShowLayer === 'function') {
                    layerRealtime.zoomToShowLayer(mk, function () { mk.openPopup(); });
                } else {
                    map.setView(mk.getLatLng(), Math.max(map.getZoom(), 15), { animate: true });
                    mk.openPopup();
                }
                return;
            }
        }
        // Fallback (Google ou marker ainda não criado): usa a posição crua.
        var pos = null;
        for (var i = 0; i < mapaPosicoesRaw.length; i++) {
            if (String(mapaPosicoesRaw[i].veiculo_id) === svid) { pos = mapaPosicoesRaw[i]; break; }
        }
        if (pos && pos.latitude != null && pos.longitude != null) {
            if (mapProvider === 'leaflet' && map) {
                map.setView([pos.latitude, pos.longitude], Math.max(map.getZoom(), 15), { animate: true });
            } else if (gMap) {
                gMap.setCenter({ lat: Number(pos.latitude), lng: Number(pos.longitude) });
                gMap.setZoom(15);
            }
        }
    }

    function sfWaitForGoogleMapsReady(maxMs) {
        maxMs = maxMs || 8000;
        var start = Date.now();
        return new Promise(function (resolve, reject) {
            function tick() {
                if (window.google && window.google.maps && window.google.maps.Map) {
                    resolve();
                    return;
                }
                if (Date.now() - start > maxMs) {
                    reject(new Error('Google Maps API não carregou'));
                    return;
                }
                requestAnimationFrame(tick);
            }
            tick();
        });
    }

    function disposeGoogleMapObject(obj) {
        if (!obj) return;
        if (typeof obj.setMap === 'function') {
            obj.setMap(null);
            return;
        }
        try {
            obj.map = null;
        } catch (e) { /* ignore */ }
    }

    function getRtInfoWindow() {
        if (!gRtInfoWindow && gMap && window.google && google.maps) {
            gRtInfoWindow = new google.maps.InfoWindow({
                disableAutoPan: true,
                maxWidth: 560
            });
        }
        return gRtInfoWindow;
    }

    function addGoogleHistoricoMarker(lat, lng, popupHtml, glyphText, titleHint) {
        var pos = { lat: Number(lat), lng: Number(lng) };
        var entry = { ref: null, cleanup: null };
        var Adv = window.google && google.maps.marker && google.maps.marker.AdvancedMarkerElement;
        var Pin = window.google && google.maps.marker && google.maps.marker.PinElement;

        if (Adv && Pin) {
            try {
                var pinOpts = {
                    background: '#2563eb',
                    borderColor: '#ffffff',
                    glyphColor: '#ffffff',
                    scale: 1.05
                };
                if (glyphText) {
                    pinOpts.glyphText = glyphText;
                }
                var pinH = new Pin(pinOpts);
                var advH = new Adv({
                    map: gMap,
                    position: pos,
                    content: pinH,
                    title: titleHint || ''
                });
                entry.ref = advH;
                entry.cleanup = attachGoogleFleetHover(advH, popupHtml, true, pos, pinH);
                gHistMarkers.push(entry);
                return;
            } catch (e) {
                console.warn('mapa-frota: AdvancedMarker (histórico) falhou.', e);
            }
        }
        var leg = new google.maps.Marker({
            position: pos,
            map: gMap,
            label: glyphText || ''
        });
        entry.ref = leg;
        entry.cleanup = attachGoogleFleetHover(leg, popupHtml, false, pos);
        gHistMarkers.push(entry);
    }

    /**
     * InfoWindow com o mesmo HTML do Leaflet.
     * AdvancedMarker + PinElement: eventos em marker.element costumam falhar (shadow DOM);
     * usamos PinElement, Maps API (mouseover/click) e fallback por posição.
     */
    function attachGoogleFleetHover(markerRef, html, isAdvanced, fallbackPosition, contentEl) {
        var iw = getRtInfoWindow();
        if (!iw || !gMap) return function () {};

        var cleanups = [];

        function openIw() {
            if (gRtInfoCloseTimer) {
                clearTimeout(gRtInfoCloseTimer);
                gRtInfoCloseTimer = null;
            }
            iw.setContent(html);
            try {
                iw.open({ shouldFocus: false, map: gMap, anchor: markerRef });
            } catch (e1) {
                try {
                    if (fallbackPosition && typeof iw.setPosition === 'function') {
                        iw.setPosition(fallbackPosition);
                        iw.open({ map: gMap });
                    }
                } catch (e2) {
                    console.warn('mapa-frota: InfoWindow', e2);
                }
            }
        }

        function addMapsListener(evtName) {
            try {
                var h = google.maps.event.addListener(markerRef, evtName, openIw);
                cleanups.push(function () {
                    google.maps.event.removeListener(h);
                });
            } catch (ignored) { /* evento não suportado nesta versão */ }
        }

        if (contentEl && typeof contentEl.addEventListener === 'function') {
            var onPtr = function () { openIw(); };
            contentEl.addEventListener('pointerenter', onPtr);
            contentEl.addEventListener('click', onPtr);
            cleanups.push(function () {
                contentEl.removeEventListener('pointerenter', onPtr);
                contentEl.removeEventListener('click', onPtr);
            });
        }

        if (isAdvanced && markerRef.element) {
            markerRef.element.style.cursor = 'pointer';
            var onWrap = function () { openIw(); };
            markerRef.element.addEventListener('pointerenter', onWrap);
            markerRef.element.addEventListener('click', onWrap);
            cleanups.push(function () {
                markerRef.element.removeEventListener('pointerenter', onWrap);
                markerRef.element.removeEventListener('click', onWrap);
            });
        }

        if (isAdvanced) {
            addMapsListener('mouseover');
            addMapsListener('click');
            addMapsListener('gmp-click');
        } else {
            addMapsListener('mouseover');
            addMapsListener('click');
            var hOut = google.maps.event.addListener(markerRef, 'mouseout', function () {
                gRtInfoCloseTimer = setTimeout(function () {
                    iw.close();
                    gRtInfoCloseTimer = null;
                }, 1200);
            });
            cleanups.push(function () {
                google.maps.event.removeListener(hOut);
            });
        }

        return function () {
            cleanups.forEach(function (fn) {
                try { fn(); } catch (e) { /* ignore */ }
            });
        };
    }

    function clearGoogleRealtime() {
        if (gRtInfoCloseTimer) {
            clearTimeout(gRtInfoCloseTimer);
            gRtInfoCloseTimer = null;
        }
        if (gRtInfoWindow) {
            gRtInfoWindow.close();
        }
        gMarkers.forEach(function (entry) {
            if (!entry) return;
            if (typeof entry === 'object' && entry.ref !== undefined) {
                if (entry.cleanup) entry.cleanup();
                disposeGoogleMapObject(entry.ref);
            } else if (typeof entry.setMap === 'function') {
                entry.setMap(null);
            }
        });
        gMarkers = [];
    }
    function clearGoogleCercas() {
        gCircles.forEach(function (c) { c.setMap(null); });
        gCircles = [];
    }
    function clearGoogleHistorico() {
        if (gRtInfoCloseTimer) {
            clearTimeout(gRtInfoCloseTimer);
            gRtInfoCloseTimer = null;
        }
        if (gRtInfoWindow) {
            gRtInfoWindow.close();
        }
        if (gHistPolyline) {
            gHistPolyline.setMap(null);
            gHistPolyline = null;
        }
        gHistMarkers.forEach(function (entry) {
            if (!entry) return;
            if (typeof entry === 'object' && entry.ref !== undefined) {
                if (entry.cleanup) entry.cleanup();
                disposeGoogleMapObject(entry.ref);
            } else if (typeof entry.setMap === 'function') {
                entry.setMap(null);
            }
        });
        gHistMarkers = [];
    }

    function updateToggleButtonLabel() {
        var span = document.getElementById('btnMapaToggleLabel');
        var btn = document.getElementById('btnMapaToggle');
        if (!span || !btn) return;
        if (mapProvider === 'google') {
            span.textContent = 'OpenStreetMap';
            btn.setAttribute('title', 'Voltar para mapa OpenStreetMap (Leaflet)');
        } else {
            span.textContent = 'Google Maps';
            btn.setAttribute('title', 'Usar Google Maps (requer chave em Configurações)');
        }
    }

    function applyProviderLayout() {
        if (!mapLeafletEl || !mapGoogleEl) return;
        if (mapProvider === 'google') {
            mapLeafletEl.style.display = 'none';
            mapGoogleEl.style.display = 'block';
        } else {
            mapLeafletEl.style.display = 'block';
            mapGoogleEl.style.display = 'none';
        }
        updateToggleButtonLabel();
    }

    function ensureLeafletVisible() {
        if (map && mapProvider === 'leaflet') {
            setTimeout(function () {
                map.invalidateSize();
            }, 100);
        }
    }

    async function ensureGoogleMap() {
        if (gMap) return gMap;
        if (!window.googleMapsManager) {
            throw new Error('GoogleMapsManager não carregado');
        }
        var response = await fetch(googleMapsConfigUrl(), { credentials: 'same-origin' });
        var data = await response.json();
        if (!data.success || !data.data || !data.data.google_maps_api_key) {
            throw new Error('Chave da API do Google Maps não configurada (Configurações > Google Maps).');
        }
        await window.googleMapsManager.init(data.data.google_maps_api_key);
        await sfWaitForGoogleMapsReady(8000);
        gMap = await window.googleMapsManager.createMap('mapaFrotaGoogle', {
            zoom: 5,
            center: { lat: -14.235, lng: -51.9253 },
            mapTypeId: 'roadmap'
        });
        return gMap;
    }

    function redrawEverything() {
        renderRealtimeFromCache();
        loadCercas();
        drawHistoricoFromCache();
    }

    async function switchMapProvider() {
        var next = mapProvider === 'leaflet' ? 'google' : 'leaflet';
        if (next === 'google') {
            try {
                statusEl.textContent = 'Carregando Google Maps…';
                await ensureGoogleMap();
                mapProvider = 'google';
                localStorage.setItem(MAP_STORAGE_KEY, 'google');
                applyProviderLayout();
                if (window.google && gMap) {
                    google.maps.event.trigger(gMap, 'resize');
                }
                redrawEverything();
                if (window.google && gMap) {
                    google.maps.event.trigger(gMap, 'resize');
                }
            } catch (e) {
                mapProvider = 'leaflet';
                localStorage.removeItem(MAP_STORAGE_KEY);
                applyProviderLayout();
                statusEl.textContent = 'Google Maps: ' + (e && e.message ? e.message : 'erro ao carregar.');
                alert(statusEl.textContent);
            }
        } else {
            mapProvider = 'leaflet';
            localStorage.setItem(MAP_STORAGE_KEY, 'leaflet');
            applyProviderLayout();
            clearGoogleRealtime();
            clearGoogleCercas();
            clearGoogleHistorico();
            ensureLeafletVisible();
            redrawEverything();
        }
    }

    map = L.map(mapLeafletEl, { scrollWheelZoom: true }).setView([-14.235, -51.9253], 4);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);
    layerCercas = L.layerGroup().addTo(map);
    layerHistorico = L.layerGroup().addTo(map);
    // Agrupa markers próximos quando há muitos veículos sobrepostos. Se a lib de
    // cluster não carregar (CDN offline), usa layerGroup comum como fallback.
    layerRealtime = (typeof L.markerClusterGroup === 'function')
        ? L.markerClusterGroup({
            showCoverageOnHover: false,
            maxClusterRadius: 50,
            spiderfyOnMaxZoom: true,
            // Bolha do cluster fica vermelha se algum veículo dentro estiver sem sinal.
            iconCreateFunction: function (cluster) {
                var filhos = cluster.getAllChildMarkers();
                var temSemSinal = filhos.some(function (m) { return m._sfSemSinal; });
                var n = cluster.getChildCount();
                var tam = n < 10 ? 'small' : (n < 100 ? 'medium' : 'large');
                var extra = temSemSinal ? ' sf-cluster-alerta' : '';
                return L.divIcon({
                    html: '<div><span>' + n + '</span></div>',
                    className: 'marker-cluster marker-cluster-' + tam + extra,
                    iconSize: L.point(40, 40)
                });
            }
        })
        : L.layerGroup();
    layerRealtime.addTo(map);

    function loadCercas() {
        if (optCercas && !optCercas.checked) {
            layerCercas.clearLayers();
            clearGoogleCercas();
            return;
        }
        fetch(apiCercasUrl(), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                layerCercas.clearLayers();
                clearGoogleCercas();
                if (!j.success || !j.data || !j.data.cercas) return;
                j.data.cercas.forEach(function (c) {
                    if (Number(c.ativo) === 0) return;
                    if (mapProvider === 'leaflet') {
                        L.circle([c.latitude, c.longitude], {
                            radius: c.raio_metros,
                            color: '#16a34a',
                            weight: 2,
                            fillColor: '#22c55e',
                            fillOpacity: 0.12
                        }).bindPopup('Cerca: <strong>' + escapeHtml(c.nome || '') + '</strong><br>Raio: ' + escapeHtml(c.raio_metros) + ' m').addTo(layerCercas);
                    } else if (gMap) {
                        var circle = new google.maps.Circle({
                            center: { lat: Number(c.latitude), lng: Number(c.longitude) },
                            radius: Number(c.raio_metros),
                            strokeColor: '#16a34a',
                            strokeWeight: 2,
                            fillColor: '#22c55e',
                            fillOpacity: 0.12,
                            map: gMap
                        });
                        circle.addListener('click', function () {
                            var inf = new google.maps.InfoWindow({
                                content: 'Cerca: <strong>' + escapeHtml(c.nome || '') + '</strong><br>Raio: ' + escapeHtml(c.raio_metros) + ' m'
                            });
                            inf.setPosition(circle.getCenter());
                            inf.open(gMap);
                        });
                        gCircles.push(circle);
                    }
                });
            })
            .catch(function () {});
    }

    function loadAlertasCercas() {
        var ul = document.getElementById('mapaAlertasListCerca');
        var box = document.getElementById('mapaAlertasBox');
        if (optAlertas && !optAlertas.checked) {
            if (box) box.style.display = 'none';
            return;
        }
        if (box) box.style.display = '';
        if (!ul) return;
        fetch(apiAlertasUrl(), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                ul.innerHTML = '';
                if (!j.success || !j.data || !j.data.alertas || !j.data.alertas.length) {
                    ul.innerHTML = '<li>Nenhum alerta de cerca recente.</li>';
                    return;
                }
                var arr = j.data.alertas.slice();
                var vidSel = getRtVeiculoId();
                if (vidSel) {
                    arr = arr.filter(function (a) { return String(a.veiculo_id || '') === vidSel; });
                }
                if (arr.length === 0) {
                    ul.innerHTML = '<li>Nenhum alerta de cerca' + (vidSel ? ' para este veículo.' : '.') + '</li>';
                    return;
                }
                arr = arr.slice(0, 25);
                arr.forEach(function (a) {
                    var li = document.createElement('li');
                    var tc = String(a.tipo || '').toLowerCase();
                    var tag = tc === 'entrou' ? 'entrou' : (tc === 'saiu' ? 'saiu' : (tc === 'permanencia' ? 'permanencia' : 'entrou'));
                    var tipoLabel = String(a.tipo || '').toUpperCase();
                    li.innerHTML = '<span class="tag ' + tag + '">' + escapeHtml(tipoLabel) + '</span> ' +
                        escapeHtml(a.cerca_nome || '') + ' · ' + escapeHtml(a.placa || ('Veículo ' + a.veiculo_id)) + ' · ' + escapeHtml(a.motorista_nome || '') +
                        '<br><small style="opacity:.85">' + escapeHtml(a.data_hora || '') + '</small>';
                    ul.appendChild(li);
                });
            })
            .catch(function () { ul.innerHTML = '<li>Erro ao carregar alertas de cerca.</li>'; });
    }

    function operAlertTagClass(tipo) {
        var t = String(tipo || '').replace(/[^a-z0-9_]/gi, '');
        var known = 'bateria_baixa bateria_critica velocidade_alta velocidade_impossivel gps_mock perda_sinal_gps salto_suspeito ignicao_parado';
        if (known.indexOf(t) >= 0) return t;
        return 'oper_generico';
    }
    function operAlertLabel(tipo) {
        var map = {
            bateria_baixa: 'BATERIA',
            bateria_critica: 'BAT. CRÍT.',
            velocidade_alta: 'VELOCIDADE',
            velocidade_impossivel: 'VEL. IMPOSS.',
            gps_mock: 'MOCK GPS',
            perda_sinal_gps: 'SEM SINAL',
            salto_suspeito: 'SALTO GPS',
            ignicao_parado: 'IGNIÇÃO',
        };
        var k = String(tipo || '');
        return map[k] || k.replace(/_/g, ' ').toUpperCase() || 'ALERTA';
    }

    function loadAlertasOperacionais() {
        var ul = document.getElementById('mapaAlertasListOper');
        var box = document.getElementById('mapaAlertasBox');
        if (optAlertas && !optAlertas.checked) {
            return;
        }
        if (box) box.style.display = '';
        if (!ul) return;
        fetch(apiAlertasOperacionaisUrl(), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                ul.innerHTML = '';
                if (!j.success || !j.data || !j.data.alertas || !j.data.alertas.length) {
                    ul.innerHTML = '<li>Nenhum alerta operacional recente (ou tabela / env SF_GPS_ALERTAS_OPERACIONAIS não ativos).</li>';
                    return;
                }
                var arr = j.data.alertas.slice(0, 25);
                arr.forEach(function (a) {
                    var li = document.createElement('li');
                    var cls = operAlertTagClass(a.tipo);
                    var placa = a.placa || ('Veículo ' + a.veiculo_id);
                    var mot = a.motorista_nome || '';
                    var msg = escapeHtml(a.mensagem || '');
                    li.innerHTML = '<span class="tag ' + cls + '">' + escapeHtml(operAlertLabel(a.tipo)) + '</span> ' +
                        escapeHtml(placa) + (mot ? ' · ' + escapeHtml(mot) : '') +
                        '<br>' + msg +
                        '<br><small style="opacity:.85">' + escapeHtml(a.data_hora || '') + '</small>';
                    ul.appendChild(li);
                });
            })
            .catch(function () { ul.innerHTML = '<li>Erro ao carregar alertas operacionais.</li>'; });
    }

    function loadAlertas() {
        loadAlertasCercas();
        loadAlertasOperacionais();
    }

    function renderRealtimeFromCache() {
        var list = filterPosicoes(mapaPosicoesRaw);
        renderListaVeiculosResumo(list);
        clearGoogleRealtime();
        // No Leaflet usamos diff (markers reaproveitados); só limpamos a camada
        // quando o provedor ativo é o Google, para não deixar markers órfãos.
        if (mapProvider !== 'leaflet') {
            layerRealtime.clearLayers();
            rtMarkers = {};
        }
        var rtSeen = {};

        if (list.length === 0) {
            if (mapProvider === 'leaflet') { layerRealtime.clearLayers(); rtMarkers = {}; rtFitSignature = ''; }
            if (mapaPosicoesRaw.length === 0) {
                statusEl.textContent = 'Tempo real: nenhuma posição. Histórico: escolha veículo e período.';
            } else {
                var vSel = getRtVeiculoId();
                if (vSel) {
                    statusEl.textContent = 'Nenhuma posição em tempo real para o veículo filtrado (ou combine com motorista/busca).';
                } else {
                    statusEl.textContent = 'Nenhum veículo com os filtros atuais. Ajuste busca ou motorista.';
                }
            }
            return;
        }

        var bounds = [];
        var boundsG = (window.google && google.maps && google.maps.LatLngBounds)
            ? new google.maps.LatLngBounds()
            : null;

        list.forEach(function (p) {
            var lat = p.latitude, lng = p.longitude;
            if (lat == null || lng == null) return;
            var titlePlain = (p.placa || 'Veículo') + ' · ' + (p.motorista_nome || 'Motorista');
            var html = buildPosicaoPopupHtml(p);
            var cor = posStatusColor(p);
            var tipEl = document.createElement('div');
            tipEl.innerHTML = html;

            if (mapProvider === 'leaflet') {
                var rtId = String(p.veiculo_id != null ? p.veiculo_id : (p.placa || (lat + ',' + lng)));
                rtSeen[rtId] = true;
                var semSinal = posSemSinal(p);
                var existente = rtMarkers[rtId];
                if (existente) {
                    // Reaproveita o marker: só move e atualiza o conteúdo (popup aberto permanece, sem piscar).
                    existente.setLatLng([lat, lng]);
                    existente.setPopupContent(html);
                    existente.setTooltipContent(tipEl);
                    existente._sfSemSinal = semSinal;
                    if (existente._sfCor !== cor) {
                        existente.setIcon(rtDivIcon(cor));
                        existente._sfCor = cor;
                    }
                } else {
                    var mk = L.marker([lat, lng], { icon: rtDivIcon(cor) });
                    mk._sfCor = cor;
                    mk._sfSemSinal = semSinal;
                    mk.bindPopup(html);
                    /* className: tooltip Leaflet usa nowrap por padrão e cortava bateria/data */
                    mk.bindTooltip(tipEl, {
                        sticky: true,
                        direction: 'top',
                        opacity: 0.98,
                        interactive: true,
                        className: 'sf-mapa-frota-tip'
                    });
                    mk.addTo(layerRealtime);
                    rtMarkers[rtId] = mk;
                }
                bounds.push([lat, lng]);
            } else if (gMap) {
                var pos = { lat: Number(lat), lng: Number(lng) };
                var entry = { ref: null, cleanup: null };
                var Adv = window.google && google.maps.marker && google.maps.marker.AdvancedMarkerElement;
                var Pin = window.google && google.maps.marker && google.maps.marker.PinElement;

                if (Adv && Pin) {
                    try {
                        var pinEl = new Pin({
                            background: cor,
                            borderColor: '#ffffff',
                            glyphColor: '#ffffff',
                            scale: 1.05
                        });
                        var adv = new Adv({
                            map: gMap,
                            position: pos,
                            content: pinEl,
                            title: titlePlain + ' (última)'
                        });
                        entry.ref = adv;
                        entry.cleanup = attachGoogleFleetHover(adv, html, true, pos, pinEl);
                        gMarkers.push(entry);
                        if (boundsG) boundsG.extend(pos);
                    } catch (advErr) {
                        console.warn('mapa-frota: AdvancedMarker indisponível, usando Marker legado.', advErr);
                        var m = new google.maps.Marker({
                            position: pos,
                            map: gMap,
                            title: titlePlain + ' (última)',
                            icon: {
                                path: google.maps.SymbolPath.CIRCLE,
                                scale: 7,
                                fillColor: cor,
                                fillOpacity: 1,
                                strokeColor: '#ffffff',
                                strokeWeight: 2
                            }
                        });
                        entry.ref = m;
                        entry.cleanup = attachGoogleFleetHover(m, html, false, pos);
                        gMarkers.push(entry);
                        if (boundsG) boundsG.extend(pos);
                    }
                } else {
                    var m2 = new google.maps.Marker({
                        position: pos,
                        map: gMap,
                        title: titlePlain + ' (última)',
                        icon: {
                            path: google.maps.SymbolPath.CIRCLE,
                            scale: 7,
                            fillColor: cor,
                            fillOpacity: 1,
                            strokeColor: '#ffffff',
                            strokeWeight: 2
                        }
                    });
                    entry.ref = m2;
                    entry.cleanup = attachGoogleFleetHover(m2, html, false, pos);
                    gMarkers.push(entry);
                    if (boundsG) boundsG.extend(pos);
                }
            }
        });

        // Remove markers Leaflet de veículos que saíram do filtro/da lista.
        if (mapProvider === 'leaflet') {
            Object.keys(rtMarkers).forEach(function (id) {
                if (!rtSeen[id]) {
                    layerRealtime.removeLayer(rtMarkers[id]);
                    delete rtMarkers[id];
                }
            });
            // Markers que se moveram precisam ser re-agrupados no cluster.
            if (typeof layerRealtime.refreshClusters === 'function') {
                layerRealtime.refreshClusters();
            }
        }

        // Modo "seguir": mantém o mapa centralizado no veículo selecionado.
        var seguindoAgora = false;
        if (followId != null) {
            if (mapProvider === 'leaflet' && rtMarkers[String(followId)]) {
                map.panTo(rtMarkers[String(followId)].getLatLng(), { animate: true });
                seguindoAgora = true;
            } else if (mapProvider !== 'leaflet' && gMap) {
                for (var fi = 0; fi < list.length; fi++) {
                    if (String(list[fi].veiculo_id) === String(followId) && list[fi].latitude != null) {
                        gMap.panTo({ lat: Number(list[fi].latitude), lng: Number(list[fi].longitude) });
                        seguindoAgora = true;
                        break;
                    }
                }
            }
        }

        if (mapProvider === 'leaflet') {
            // Só reenquadra quando o conjunto de veículos muda (entrou/saiu veículo
            // ou filtro), não a cada atualização de posição — evita "pulos" no mapa.
            // Em modo "seguir", não reenquadra (o panTo acima já cuida).
            var rtSig = Object.keys(rtMarkers).sort().join(',');
            if (!seguindoAgora && bounds.length && layerHistorico.getLayers().length === 0 && rtSig !== rtFitSignature) {
                map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
            }
            rtFitSignature = rtSig;
        } else if (!seguindoAgora && gMap && boundsG && !boundsG.isEmpty()) {
            if (!histCache) {
                gMap.fitBounds(boundsG);
            }
        }

        var hasHist = mapProvider === 'leaflet' ? layerHistorico.getLayers().length : (gHistPolyline ? 1 : 0);
        var histNote = hasHist ? ' Rota histórica visível.' : '';
        var onlyNote = '';
        var vSel = getRtVeiculoId();
        if (vSel && selRtVeiculo && selRtVeiculo.options[selRtVeiculo.selectedIndex]) {
            onlyNote = ' Filtro tempo real: ' + selRtVeiculo.options[selRtVeiculo.selectedIndex].text + '.';
        }
        var capNote = mapaCapAviso ? (' Limite de ' + MAP_MARKER_CAP + ' marcadores — refine busca ou filtre por veículo.') : '';
        var mapLabel = mapProvider === 'google' ? ' [Google Maps]' : ' [OpenStreetMap]';
        statusEl.textContent = 'Tempo real: ' + list.length + ' veículo(s) exibido(s).' + onlyNote + capNote + histNote + (chkRt.checked ? ' Atualiza a cada 30 s.' : '') + mapLabel;
    }

    function drawHistoricoFromCache() {
        if (mapProvider === 'leaflet') {
            layerHistorico.clearLayers();
        } else {
            clearGoogleHistorico();
        }
        if (!histCache || !histCache.latlngs || histCache.latlngs.length === 0) return;

        var latlngs = histCache.latlngs;
        var placa = histCache.placa || '';
        var pts = histCache.pts || [];

        var placaEsc = escapeHtml(placa);
        var dhIni = escapeHtml(pts[0] && pts[0].data_hora ? pts[0].data_hora : '');
        var dhFim = escapeHtml(pts[pts.length - 1] && pts[pts.length - 1].data_hora ? pts[pts.length - 1].data_hora : '');
        if (mapProvider === 'leaflet') {
            if (latlngs.length >= 2) {
                L.polyline(latlngs, { color: '#3b82f6', weight: 4, opacity: 0.88 }).addTo(layerHistorico);
                L.marker(latlngs[0]).bindPopup('<strong>Início</strong><br>' + dhIni + '<br>' + placaEsc).addTo(layerHistorico);
                L.marker(latlngs[latlngs.length - 1]).bindPopup('<strong>Fim</strong><br>' + dhFim + '<br>' + placaEsc).addTo(layerHistorico);
                map.fitBounds(L.latLngBounds(latlngs), { padding: [48, 48], maxZoom: 15 });
            } else {
                L.marker(latlngs[0]).bindPopup('<strong>Único ponto</strong><br>' + dhIni + '<br>' + placaEsc).addTo(layerHistorico);
                map.setView(latlngs[0], 14);
            }
        } else if (gMap) {
            var path = latlngs.map(function (ll) { return { lat: ll[0], lng: ll[1] }; });
            gHistPolyline = new google.maps.Polyline({
                path: path,
                strokeColor: '#3b82f6',
                strokeOpacity: 0.88,
                strokeWeight: 4,
                map: gMap
            });
            if (latlngs.length >= 2) {
                var htmlIni = '<strong>Início</strong><br>' + dhIni + '<br>' + placaEsc;
                var htmlFim = '<strong>Fim</strong><br>' + dhFim + '<br>' + placaEsc;
                addGoogleHistoricoMarker(latlngs[0][0], latlngs[0][1], htmlIni, 'A', 'Início — ' + placa);
                addGoogleHistoricoMarker(latlngs[latlngs.length - 1][0], latlngs[latlngs.length - 1][1], htmlFim, 'B', 'Fim — ' + placa);
            } else if (latlngs.length === 1) {
                var htmlUnico = '<strong>Único ponto</strong><br>' + dhIni + '<br>' + placaEsc;
                addGoogleHistoricoMarker(latlngs[0][0], latlngs[0][1], htmlUnico, '', placa);
            }
            var b = new google.maps.LatLngBounds();
            path.forEach(function (pt) { b.extend(pt); });
            gMap.fitBounds(b, 48);
        }
    }

    function loadRealtime(force) {
        if (!chkRt.checked && !force) return;
        fetch(apiPosicoesUrl(), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.success || !j.data || !j.data.posicoes) {
                    mapaPosicoesRaw = [];
                    statusEl.textContent = 'Não foi possível carregar as posições em tempo real.';
                    renderRealtimeFromCache();
                    return;
                }
                mapaPosicoesRaw = j.data.posicoes;
                lastRtUpdate = Date.now();
                atualizarKpisTempoReal();
                renderRealtimeFromCache();
            })
            .catch(function () {
                statusEl.textContent = 'Erro de rede (tempo real).';
            });
    }

    function loadVeiculos() {
        fetch(apiVeiculosUrl(), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                var list = (j.veiculos || j.data || []);
                if (j.success === false || !Array.isArray(list)) {
                    if (selHistVeiculo) selHistVeiculo.innerHTML = '<option value="">Erro ao listar veículos</option>';
                    if (selRtVeiculo) selRtVeiculo.innerHTML = '<option value="">Erro ao listar veículos</option>';
                    return;
                }
                if (list.length === 0) {
                    if (selHistVeiculo) selHistVeiculo.innerHTML = '<option value="">Nenhum veículo</option>';
                    if (selRtVeiculo) selRtVeiculo.innerHTML = '<option value="">Nenhum veículo</option>';
                    return;
                }
                fillVeiculoSelect(selRtVeiculo, list, 'Todos os veículos');
                fillVeiculoSelect(selHistVeiculo, list, 'Selecione…');
            })
            .catch(function () {
                if (selHistVeiculo) selHistVeiculo.innerHTML = '<option value="">Falha ao carregar veículos</option>';
                if (selRtVeiculo) selRtVeiculo.innerHTML = '<option value="">Falha ao carregar veículos</option>';
            });
    }

    function loadMotoristas() {
        var url = (typeof sfApiUrl === 'function') ? sfApiUrl('route_actions.php?action=get_motoristas') : '../api/route_actions.php?action=get_motoristas';
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success || !data.data || !driverFilter) return;
                var prev = driverFilter.value;
                driverFilter.innerHTML = '';
                var o0 = document.createElement('option');
                o0.value = '';
                o0.textContent = 'Todos os motoristas';
                driverFilter.appendChild(o0);
                data.data.forEach(function (driver) {
                    var o = document.createElement('option');
                    o.value = String(driver.id);
                    o.textContent = driver.nome || '';
                    driverFilter.appendChild(o);
                });
                if (prev) driverFilter.value = prev;
            })
            .catch(function () {});
    }

    function exportCsv() {
        var list = filterPosicoes(mapaPosicoesRaw);
        if (!list.length) {
            alert('Não há posições para exportar. Clique em Pesquisar após carregar o mapa.');
            return;
        }
        var rows = [['placa', 'motorista', 'latitude', 'longitude', 'velocidade', 'status', 'bateria_pct', 'accuracy_metros', 'provider', 'data_hora', 'endereco']];
        list.forEach(function (p) {
            rows.push([
                p.placa || '',
                (p.motorista_nome || '').replace(/"/g, '""'),
                p.latitude != null ? p.latitude : '',
                p.longitude != null ? p.longitude : '',
                p.velocidade != null ? p.velocidade : '',
                p.status || '',
                p.bateria_pct != null && p.bateria_pct !== '' ? p.bateria_pct : '',
                p.accuracy_metros != null && p.accuracy_metros !== '' ? p.accuracy_metros : '',
                (p.provider || '').replace(/"/g, '""'),
                p.data_hora || '',
                (p.endereco || '').replace(/"/g, '""')
            ]);
        });
        var csv = rows.map(function (r) {
            return r.map(function (c) { return '"' + String(c) + '"'; }).join(';');
        }).join('\r\n');
        var blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'mapa_frota_posicoes.csv';
        a.click();
        URL.revokeObjectURL(a.href);
    }

    function carregarHistorico() {
        var vid = getHistVeiculoId();
        if (!vid) {
            statusEl.textContent = 'Selecione um veículo para o histórico.';
            return;
        }
        var ini = inpIni.value;
        var fim = inpFim.value;
        if (!ini || !fim) {
            statusEl.textContent = 'Informe início e fim do período.';
            return;
        }
        statusEl.textContent = 'Carregando histórico…';
        fetch(apiHistoricoUrl(vid, ini, fim), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                histCache = null;
                layerHistorico.clearLayers();
                clearGoogleHistorico();
                if (!j.success || !j.data || !j.data.pontos) {
                    statusEl.textContent = 'Histórico indisponível.';
                    return;
                }
                var pts = j.data.pontos;
                var placa = j.data.placa || '';
                if (pts.length === 0) {
                    statusEl.textContent = 'Nenhum ponto GPS no período para ' + placa + '.';
                    return;
                }
                var latlngs = [];
                pts.forEach(function (p) {
                    if (p.latitude != null && p.longitude != null) {
                        latlngs.push([p.latitude, p.longitude]);
                    }
                });
                if (latlngs.length === 0) {
                    statusEl.textContent = 'Coordenadas inválidas nos registros.';
                    return;
                }
                histCache = { latlngs: latlngs, placa: placa, pts: pts };
                drawHistoricoFromCache();
                statusEl.textContent = 'Histórico: ' + latlngs.length + ' ponto(s) — ' + placa + '. Máx. 7 dias / 2000 pontos por consulta.';
            })
            .catch(function () {
                statusEl.textContent = 'Erro de rede ao carregar histórico.';
            });
    }

    document.getElementById('histBtnCarregar').addEventListener('click', carregarHistorico);
    document.getElementById('histBtnLimpar').addEventListener('click', function () {
        histCache = null;
        layerHistorico.clearLayers();
        clearGoogleHistorico();
        statusEl.textContent = 'Rota histórica removida.';
        loadRealtime();
    });
    function applyPresetHistorico(h, autoLoad) {
        if (!inpIni || !inpFim) return;
        setPresetHours(h);
        if (statusEl) {
            statusEl.textContent = 'Período do histórico: últimas ' + h + ' horas (Início/Fim atualizados).';
        }
        if (autoLoad !== false && getHistVeiculoId()) {
            carregarHistorico();
        }
    }
    [['mapaFrotaPreset6h', 6], ['mapaFrotaPreset24h', 24], ['mapaFrotaPreset72h', 72]].forEach(function (pair) {
        var el = document.getElementById(pair[0]);
        if (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                applyPresetHistorico(pair[1]);
            });
        }
    });
    chkRt.addEventListener('change', function () {
        if (rtTimer) clearInterval(rtTimer);
        rtTimer = null;
        if (chkRt.checked) {
            loadRealtime();
            rtTimer = setInterval(loadRealtime, 30000);
        } else {
            statusEl.textContent = 'Atualização em tempo real pausada. Tabela abaixo mantém a última leitura.';
            renderListaVeiculosResumo(filterPosicoes(mapaPosicoesRaw));
        }
    });

    document.getElementById('applyRouteFilters').addEventListener('click', function () {
        loadRealtime(true);
        loadAlertas();
        loadCercas();
    });
    if (searchEl) searchEl.addEventListener('input', function () { renderRealtimeFromCache(); });
    if (driverFilter) driverFilter.addEventListener('change', function () { renderRealtimeFromCache(); });
    if (selRtVeiculo) selRtVeiculo.addEventListener('change', function () { renderRealtimeFromCache(); loadAlertas(); });

    // Tabela "Situação por veículo": clicar na linha centraliza no mapa; botão "seguir" fixa o veículo.
    var rtTbody = document.getElementById('mapaListaVeiculosTbody');
    if (rtTbody) {
        rtTbody.addEventListener('click', function (e) {
            var followBtn = e.target.closest('[data-follow-id]');
            if (followBtn) {
                e.stopPropagation();
                var fid = followBtn.getAttribute('data-follow-id');
                followId = (String(followId) === String(fid)) ? null : fid;
                if (followId != null) focusVeiculoNoMapa(followId);
                renderRealtimeFromCache();
                return;
            }
            var row = e.target.closest('tr[data-veiculo-id]');
            if (row) focusVeiculoNoMapa(row.dataset.veiculoId);
        });
        rtTbody.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var row = e.target.closest('tr[data-veiculo-id]');
            if (row) {
                e.preventDefault();
                focusVeiculoNoMapa(row.dataset.veiculoId);
            }
        });
    }

    document.getElementById('filterBtn').addEventListener('click', function () {
        mapaOpcoes.classList.toggle('is-open');
    });
    document.getElementById('exportBtn').addEventListener('click', exportCsv);
    if (optCercas) optCercas.addEventListener('change', loadCercas);
    if (optAlertas) optAlertas.addEventListener('change', loadAlertas);

    var btnMapaToggle = document.getElementById('btnMapaToggle');
    if (btnMapaToggle) {
        btnMapaToggle.addEventListener('click', function () {
            switchMapProvider();
        });
    }

    // Tela cheia do mapa
    var btnFs = document.getElementById('btnMapaFullscreen');
    var mapWrap = document.querySelector('.mapa-frota-map-wrap');
    if (btnFs && mapWrap) {
        btnFs.addEventListener('click', function () {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else if (mapWrap.requestFullscreen) {
                mapWrap.requestFullscreen();
            }
        });
        document.addEventListener('fullscreenchange', function () {
            var on = document.fullscreenElement === mapWrap;
            btnFs.innerHTML = on ? '<i class="fas fa-compress"></i>' : '<i class="fas fa-expand"></i>';
            mapWrap.classList.toggle('is-fullscreen', on);
            // O mapa precisa recalcular o tamanho ao mudar de dimensão.
            setTimeout(function () {
                if (mapProvider === 'leaflet' && map) map.invalidateSize();
                else if (gMap && window.google) google.maps.event.trigger(gMap, 'resize');
            }, 120);
        });
    }

    // "Atualizado há X s" — atualiza a cada segundo.
    var updatedEl = document.getElementById('mapaFrotaUpdated');
    if (updatedEl) {
        setInterval(function () {
            if (lastRtUpdate == null) { updatedEl.textContent = ''; return; }
            var s = Math.round((Date.now() - lastRtUpdate) / 1000);
            updatedEl.textContent = s < 5 ? 'Atualizado agora' : ('Atualizado há ' + (s < 60 ? s + ' s' : Math.floor(s / 60) + ' min') + '.');
        }, 1000);
    }

    setPresetHours(24);
    applyProviderLayout();
    renderListaVeiculosResumo(filterPosicoes(mapaPosicoesRaw));
    loadMotoristas();
    loadVeiculos();
    loadCercas();
    loadAlertas();
    // Polling gerenciado: religa só o necessário e pausa quando a aba sai de foco
    // (economiza bateria/dados do celular e carga no servidor).
    function startTimers() {
        stopTimers();
        if (chkRt.checked) rtTimer = setInterval(loadRealtime, 30000);
        cercasTimer = setInterval(loadCercas, 120000);
        alertasTimer = setInterval(loadAlertas, 60000);
    }
    function stopTimers() {
        if (rtTimer) { clearInterval(rtTimer); rtTimer = null; }
        if (cercasTimer) { clearInterval(cercasTimer); cercasTimer = null; }
        if (alertasTimer) { clearInterval(alertasTimer); alertasTimer = null; }
    }

    loadRealtime();
    startTimers();

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stopTimers();
        } else {
            // Voltou ao primeiro plano: atualiza na hora e religa os timers.
            if (chkRt.checked) loadRealtime();
            loadCercas();
            loadAlertas();
            startTimers();
        }
    });

    if (savedGooglePreference) {
        fetch(googleMapsConfigUrl(), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && data.data && data.data.google_maps_api_key) {
                    return switchMapProvider();
                }
                localStorage.removeItem(MAP_STORAGE_KEY);
            })
            .catch(function () {
                localStorage.removeItem(MAP_STORAGE_KEY);
            });
    }
})();

