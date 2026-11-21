(function() {
    var baseUrl = (window.__SF_BI__ && window.__SF_BI__.baseUrl) || '../api';
    var charts = {};
    var biLastDrill = null;
    var biLastDrillSerie = null;
    var biLastData = null;  // últimos dados carregados (para o resumo por IA)
    var biLastVisao = 'geral';
    /* Suporte aos KPIs premium (sparkline + cor de acento igual ao "risco" lateral) */
    var biKpiHist = [];
    var biKpiIdx = 0;
    var biKpiPalette = ['#3B82F6', '#22C55E', '#F59E0B', '#8B5CF6', '#06B6D4', '#EC4899', '#14B8A6', '#F97316'];
    var biKpiCompareMode = 'month';
    var biPeriodPreset = 'mes';
    var biVisaoSubtitles = {
        geral: 'Visão consolidada de receita, custos e eficiência operacional',
        rotas: 'Desempenho de rotas, faturamento e lucratividade por viagem',
        abastecimento: 'Consumo, gastos com combustível e eficiência da frota',
        manutencao: 'Custos de manutenção, preventiva vs corretiva e previsões',
        despesas_viagem: 'Despesas variáveis de viagem por tipo e por rota',
        despesas_fixas: 'Compromissos fixos, impacto no resultado e tendências',
        indicadores_desempenho: 'Indicadores mensais com variação e metas operacionais'
    };

    var biAutoRefreshTimer = null;
    var biLoadDataAbort = null;
    var biLoadDataDebounce = null;
    var biMotoristaFetchAbort = null;

    function biChartOptions() { return BiChartsV4.chartOpts.bar(); }
    function biChartPremiumLineOptions() { return BiChartsV4.chartOpts.premiumLine(); }
    function biChartPremiumDonutOptions() { return BiChartsV4.chartOpts.premiumDonut(); }
    function biChartPieOptions() { return BiChartsV4.chartOpts.donut(); }
    function biChartDualOptions() { return BiChartsV4.chartOpts.barDual(); }
    function biChartHorizontalOptions() { return BiChartsV4.chartOpts.barHorizontal(); }

    function biFiltroPeriodoQuery() {
        var a = document.getElementById('filtro-ano');
        var m = document.getElementById('filtro-mes');
        var ano = (a && a.value) ? String(a.value).trim() : '';
        var mes = (m && m.value) ? String(m.value).trim() : '';
        var q = '';
        if (ano) q += '&ano=' + encodeURIComponent(ano);
        if (mes !== '') q += '&mes=' + encodeURIComponent(mes);
        return q;
    }

    function biHtmlEsc(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
    function biDrillFormatCell(key, val) {
        if (val == null || val === '') return '—';
        var k = (key || '').toLowerCase();
        if (k === 'id' || k === 'rota_id') return biHtmlEsc(String(val));
        var n = Number(val);
        if (!isNaN(n) && String(val).trim() !== '') {
            if (/valor|frete|comissao|pedagio|caixinha|descarga|outros|total_despviagem/.test(k)) return biHtmlEsc(formatMoney(n));
            if (k === 'litros' || k.indexOf('litro') === 0) return biHtmlEsc(n.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 3 }));
            if (/distancia|km_atual|_km$/.test(k)) return biHtmlEsc(n.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }));
            return biHtmlEsc(formatNum(n));
        }
        return biHtmlEsc(String(val));
    }
    function biRenderDrillTable(json) {
        var cols = json.columns || [];
        var rows = json.rows || [];
        if (!rows.length) return '<p class="bi-text-muted">Nenhum registro neste mês.</p>';
        
        // LIMITA a 200 registros para evitar stack overflow no navegador
        var maxRows = 200;
        var totalRows = rows.length;
        var displayRows = rows.slice(0, maxRows);
        var hasMore = totalRows > maxRows;
        
        var thead = '<thead><tr>' + cols.map(function(c) { return '<th>' + biHtmlEsc(c.label) + '</th>'; }).join('') + '</tr></thead>';
        var tbody = '<tbody>' + displayRows.map(function(row) {
            return '<tr>' + cols.map(function(c) {
                return '<td>' + biDrillFormatCell(c.key, row[c.key]) + '</td>';
            }).join('') + '</tr>';
        }).join('') + '</tbody>';
        
        var warning = hasMore ? '<p class="bi-drill-hint" style="color:#f59e0b; margin-top:0.5rem;"><i class="fas fa-exclamation-triangle"></i> Exibindo ' + maxRows + ' de ' + totalRows + ' registros. Para ver todos, atualize os filtros ou exporte os dados.</p>' : '';
        
        return '<div style="overflow-x:auto;"><table class="bi-table">' + thead + tbody + '</table></div>' + warning;
    }
    function biDrillThroughUrl(serie) {
        var map = {
            rotas: 'routes.php',
            rotas_veiculo: 'vehicles.php',
            abastecimentos: 'abastecimentos.php',
            manutencoes: 'manutencoes.php',
            manut_pie: 'manutencoes.php',
            despesas_viagem: 'rotas.php',
            desp_viagem_pie: 'rotas.php',
            despesas_fixas: 'despesas_fixas.php'
        };
        return map[serie] || null;
    }
    function biCloseDrillModal() {
        var modal = document.getElementById('bi-drill-modal');
        var footer = document.getElementById('bi-drill-footer');
        if (footer) footer.style.display = 'none';
        if (modal) {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }
    }
    function biFetchDrillIntoModal(pageTitle, url, serie) {
        var modal = document.getElementById('bi-drill-modal');
        var title = document.getElementById('bi-drill-title');
        var body = document.getElementById('bi-drill-body');
        var footer = document.getElementById('bi-drill-footer');
        var through = document.getElementById('bi-drill-through');
        if (!modal || !title || !body) return;
        title.textContent = pageTitle;
        biLastDrill = null;
        biLastDrillSerie = serie || null;
        var expBtn = document.getElementById('bi-drill-export');
        if (expBtn) expBtn.style.display = 'none';
        if (footer && through) {
            var page = biDrillThroughUrl(serie);
            if (page) {
                through.href = page;
                footer.style.display = 'flex';
            } else {
                footer.style.display = 'none';
            }
        }
        body.innerHTML = '<div class="bi-loading" style="padding:1.5rem;"><i class="fas fa-spinner fa-spin"></i> Carregando…</div>';
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        var safe = (typeof sfSafeErrorMessage === 'function')
            ? sfSafeErrorMessage
            : function (e, f) { var m = (e && e.message) ? String(e.message) : (f || ''); return /maximum call stack|stack size exceeded/i.test(m) ? 'Erro no navegador. Atualize a página.' : (m || f || 'Erro'); };
        fetch(url, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(json) {
                if (!json.success) {
                    var em = json.message || json.error || 'Erro ao carregar';
                    body.innerHTML = '<p class="bi-error">' + biHtmlEsc(safe(em, 'Erro ao carregar')) + '</p>';
                    return;
                }
                body.innerHTML = biRenderDrillTable(json);
                // Guarda os dados completos e habilita a exportação CSV (todas as linhas, não só as 200 exibidas)
                var rows = json.rows || [];
                if (rows.length) {
                    biLastDrill = { columns: json.columns || [], rows: rows, title: pageTitle };
                    var eb = document.getElementById('bi-drill-export');
                    if (eb) eb.style.display = '';
                }
            })
            .catch(function(err) {
                body.innerHTML = '<p class="bi-error">' + biHtmlEsc(safe(err, 'Falha ao carregar. Tente novamente.')) + '</p>';
            });
    }

    function biExportDrillCsv() {
        if (!biLastDrill || !biLastDrill.rows.length) return;
        var cols = biLastDrill.columns;
        var rows = biLastDrill.rows;
        function cell(v) {
            if (v == null) return '';
            var s = String(v).replace(/"/g, '""');
            return /[";\n]/.test(s) ? '"' + s + '"' : s;
        }
        var lines = [cols.map(function (c) { return cell(c.label); }).join(';')];
        rows.forEach(function (r) {
            lines.push(cols.map(function (c) { return cell(r[c.key]); }).join(';'));
        });
        var csv = '﻿' + lines.join('\r\n'); // BOM para Excel pt-BR
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var a = document.createElement('a');
        var fname = 'bi_detalhe_' + (biLastDrill.title || 'dados').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '') + '.csv';
        a.href = URL.createObjectURL(blob);
        a.download = fname;
        document.body.appendChild(a);
        a.click();
        setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 100);
    }

    function biShowResumoCard(show) {
        var c = document.getElementById('bi-resumo-ia');
        if (c) c.style.display = show ? '' : 'none';
    }

    function biBuildIaPayload(data) {
        if (!data) return {};
        var hist = (data.historico_mensal || []).slice(-6);
        function sum(attr) {
            return hist.reduce(function (acc, row) {
                var n = biCoerceNumber(row[attr]);
                return acc + (isNaN(n) ? 0 : n);
            }, 0);
        }
        var payload = {
            historico_mensal: hist,
            totais_periodo: {
                frete: sum('total_frete'),
                lucro_operacional: sum('lucro_operacional'),
                km: sum('total_km_rodados'),
                gasto_abast: sum('total_gasto_abastecimentos'),
                manutencoes: sum('total_manutencoes'),
                despesas_viagem: sum('total_despesas_viagem'),
                despesas_fixas: sum('total_despesas_fixas')
            }
        };
        if (data.comparacao) payload.comparacao = data.comparacao;
        if (data.ranking_rotas) {
            payload.ranking_rotas = {
                top5: (data.ranking_rotas.top5 || []).slice(0, 3),
                bottom5: (data.ranking_rotas.bottom5 || []).slice(0, 3)
            };
        }
        if (data.veiculos_top) payload.veiculos_top = (data.veiculos_top || []).slice(0, 5);
        if (data.dados_incompletos) payload.dados_incompletos = data.dados_incompletos;
        return payload;
    }

    function biSanitizeChartLabel(s) {
        return String(s || '').replace(/[<>&"']/g, '').slice(0, 48);
    }

    function biGerarResumoIA(forceRefresh) {
        if (!biLastData) return;
        var btn = document.getElementById('btn-resumo-ia');
        var out = document.getElementById('bi-resumo-ia-texto');
        var ano = (document.getElementById('filtro-ano') || {}).value || '';
        var mes = (document.getElementById('filtro-mes') || {}).value || '';
        var cacheKey = 'bi_resumo_' + (biLastVisao || 'geral') + '_' + ano + '_' + mes;
        if (!forceRefresh) {
            try {
                var skipTs = sessionStorage.getItem(cacheKey + '_skip');
                if (skipTs && (Date.now() - parseInt(skipTs, 10) < 60 * 60 * 1000)) {
                    if (out && !out.textContent) {
                        out.classList.add('bi-text-muted');
                        out.textContent = 'Resumo automático indisponível. Clique em «Gerar resumo» para tentar manualmente.';
                    }
                    return;
                }
                var cached = sessionStorage.getItem(cacheKey);
                if (cached) {
                    var obj = JSON.parse(cached);
                    if (obj && obj.text && obj.ts && (Date.now() - obj.ts < 30 * 60 * 1000)) {
                        if (out) { out.classList.remove('bi-text-muted'); out.textContent = obj.text; }
                        return;
                    }
                }
            } catch (ce) { /* ignore */ }
        }
        if (btn) { btn.disabled = true; btn.dataset.html = btn.innerHTML; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando…'; }
        if (out) { out.classList.add('bi-text-muted'); out.textContent = 'A IA está analisando os números deste período…'; }
        var headers = (typeof sfMutationHeaders === 'function') ? sfMutationHeaders({ 'Content-Type': 'application/json' }) : { 'Content-Type': 'application/json' };
        if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__ && !headers['X-CSRF-Token']) headers['X-CSRF-Token'] = window.__SF_CSRF__;
        fetch(baseUrl + '/bi_resumo_ia.php', {
            method: 'POST', credentials: 'same-origin', headers: headers,
            body: JSON.stringify({ visao: biLastVisao, ano: ano, mes: mes, dados: biBuildIaPayload(biLastData), force: !!forceRefresh })
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, status: r.status, d: d }; }); })
        .then(function (o) {
            var d = o.d || {};
            if (o.ok && d.success) {
                if (out) { out.classList.remove('bi-text-muted'); out.textContent = d.resumo; }
                try {
                    sessionStorage.setItem(cacheKey, JSON.stringify({ text: d.resumo, ts: Date.now() }));
                    sessionStorage.removeItem(cacheKey + '_skip');
                } catch (se) { /* ignore */ }
            } else if (out) {
                out.classList.add('bi-text-muted');
                var msg = d.message || d.error || 'Não foi possível gerar o resumo.';
                if (!forceRefresh && (o.status === 422 || o.status === 401 || o.status === 402 || o.status === 429)) {
                    try { sessionStorage.setItem(cacheKey + '_skip', String(Date.now())); } catch (se2) { /* ignore */ }
                    msg = (o.status === 422)
                        ? 'Configure uma IA em Configurações → IA de Visão (finalidade Texto) e clique em «Gerar resumo».'
                        : 'Resumo automático indisponível no momento. Clique em «Gerar resumo» para tentar manualmente.';
                }
                out.textContent = msg;
            }
        })
        .catch(function () { if (out) out.textContent = 'Erro de comunicação ao gerar o resumo.'; })
        .then(function () { if (btn) { btn.disabled = false; if (btn.dataset.html) btn.innerHTML = btn.dataset.html; } });
    }
    function biGerarResumoIAAuto() {
        if (!biLastData || biLastVisao === 'indicadores_desempenho') return;
        biGerarResumoIA(false);
    }
    function biExportPdf() {
        var root = document.getElementById('biq');
        var btn = document.getElementById('btn-imprimir');
        if (!root) { window.print(); return; }
        if (typeof html2pdf === 'undefined') { window.print(); return; }
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> PDF…'; }
        html2pdf().set({
            margin: [6, 6, 6, 6],
            filename: 'bi_frotec_' + new Date().toISOString().slice(0, 10) + '.pdf',
            image: { type: 'jpeg', quality: 0.92 },
            html2canvas: { scale: 2, useCORS: true, logging: false },
            jsPDF: { unit: 'mm', format: 'a3', orientation: 'landscape' },
            pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
        }).from(root).save().then(function () {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-file-pdf" aria-hidden="true"></i> PDF'; }
        }).catch(function () {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-file-pdf" aria-hidden="true"></i> PDF'; }
            window.print();
        });
    }
    function biToggleAutoRefresh() {
        var btn = document.getElementById('btn-auto-refresh');
        var on = btn && btn.getAttribute('aria-pressed') === 'true';
        if (biAutoRefreshTimer) { clearInterval(biAutoRefreshTimer); biAutoRefreshTimer = null; }
        if (on) {
            if (btn) { btn.setAttribute('aria-pressed', 'false'); btn.classList.remove('is-active'); }
        } else {
            biAutoRefreshTimer = setInterval(function () { loadData({ skipIa: true }); }, 300000);
            if (btn) { btn.setAttribute('aria-pressed', 'true'); btn.classList.add('is-active'); }
        }
    }
    function biOpenDrillModal(serie, mesAno, mesLabel) {
        var sl = { rotas: 'Rotas', abastecimentos: 'Abastecimentos', manutencoes: 'Manutenções', despesas_viagem: 'Despesas de viagem', despesas_fixas: 'Despesas fixas (pagas)' };
        var pageTitle = (sl[serie] || 'Detalhes') + ' — ' + (mesLabel || mesAno);
        var url = baseUrl + '/bi_drill_down.php?serie=' + encodeURIComponent(serie) + '&mes_ano=' + encodeURIComponent(mesAno);
        biFetchDrillIntoModal(pageTitle, url, serie);
    }
    function biBindDrill(chart, ctx) {
        BiChartsV4.attachDrill(chart, ctx, biOpenDrillModal);
    }
    function biBindDrillTopVeiculo(chart, serieApi, ids, placaLabels) {
        BiChartsV4.attachDrillTopVeiculo(chart, serieApi, ids, placaLabels, baseUrl, biFiltroPeriodoQuery(), biFetchDrillIntoModal);
    }
    function biBindDrillPieDesp(chart) {
        BiChartsV4.attachDrillPieDesp(chart, baseUrl, biFiltroPeriodoQuery(), biFetchDrillIntoModal);
    }
    function biBindDrillManutPie(chart) {
        BiChartsV4.attachDrillManutPie(chart, baseUrl, biFiltroPeriodoQuery(), biFetchDrillIntoModal);
    }

    function biVivifyCharts() {
        BiChartsV4.vivify(charts);
    }

    /* Ranking HTML premium (Top veículos), com clique -> drill. */
    function biRenderTopVeiculosRank(veiculos, targetId) {
        var rkId = targetId || 'biTopVeiculosRank';
        var rk = document.getElementById(rkId);
        var cv = document.getElementById('chartTopVeiculos');
        if (!rk) return;
        var top = (veiculos || []).slice().sort(function (a, b) { return (b.total_rotas || 0) - (a.total_rotas || 0); }).slice(0, 10);
        if (!top.length) {
            rk.style.display = '';
            rk.innerHTML = biEmptyHtml('fa-truck', 'Nenhum veículo no período', 'Ajuste os filtros ou cadastre rotas para ver o ranking.');
            if (cv) cv.style.display = 'none';
            return;
        }
        var max = Math.max.apply(null, top.map(function (v) { return v.total_rotas || 0; })) || 1;
        var pq = biFiltroPeriodoQuery();
        rk.innerHTML = top.map(function (v, i) {
            var nome = biHtmlEsc(v.placa || v.modelo || 'N/A');
            var rotas = v.total_rotas || 0, km = v.total_km || 0;
            var pct = Math.max(4, Math.round((rotas / max) * 100));
            return '<div class="bi-rank-item" data-vid="' + biHtmlEsc(String(v.id != null ? v.id : '')) + '" data-nome="' + nome + '" tabindex="0" role="button" aria-label="Ver detalhes de ' + nome + '">'
                + '<span class="bi-rank-pos">' + (i + 1) + '</span>'
                + '<div class="bi-rank-main"><div class="bi-rank-name">' + nome + '</div>'
                + '<div class="bi-rank-bar"><span style="width:' + pct + '%"></span></div></div>'
                + '<div class="bi-rank-vals"><b>' + formatNum(rotas) + '</b> rotas<span>' + formatNum(km) + ' km</span></div>'
                + '</div>';
        }).join('');
        if (cv) cv.style.display = 'none';
        rk.style.display = '';
        rk.querySelectorAll('.bi-rank-item').forEach(function (el) {
            function go() {
                var vid = el.getAttribute('data-vid');
                if (!vid) return;
                var url = baseUrl + '/bi_drill_down.php?serie=rotas_veiculo&veiculo_id=' + encodeURIComponent(vid) + pq;
                biFetchDrillIntoModal((el.getAttribute('data-nome') || 'Veículo') + ' — detalhes', url, 'rotas_veiculo');
            }
            el.addEventListener('click', go);
            el.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
        });
    }

    function biCoerceNumber(v) {
        if (v == null || v === '') return NaN;
        if (typeof v === 'number') return isFinite(v) ? v : NaN;
        if (typeof v === 'string') {
            var t = v.replace(/\s/g, '').replace(',', '.');
            var n = parseFloat(t);
            return isFinite(n) ? n : NaN;
        }
        if (typeof v === 'object') return NaN;
        try {
            var x = Number(v);
            return isFinite(x) ? x : NaN;
        } catch (e) { return NaN; }
    }
    function formatMoney(v) {
        if (v == null || v === '') return '—';
        var n = biCoerceNumber(v);
        if (isNaN(n)) return '—';
        return 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function formatNum(v) {
        if (v == null || v === '') return '—';
        var n = biCoerceNumber(v);
        if (isNaN(n)) return '—';
        return n.toLocaleString('pt-BR', { maximumFractionDigits: 0 });
    }
    function variacaoPct(atual, anterior) {
        var a = biCoerceNumber(atual);
        var ant = biCoerceNumber(anterior);
        if (isNaN(a) || isNaN(ant)) return null;
        if (ant === 0) {
            if (a === 0) return 0;
            return a > 0 ? 100 : -100;
        }
        return ((a - ant) / Math.abs(ant)) * 100;
    }
    function badgeVariacao(pct, label) {
        if (pct == null || isNaN(pct)) return '';
        var cls = pct > 0 ? 'positive' : (pct < 0 ? 'negative' : 'neutral');
        var sinal = pct > 0 ? '▲' : (pct < 0 ? '▼' : '');
        var txt = sinal + (pct >= 0 ? '+' : '') + pct.toFixed(1) + '%';
        return '<span class="variation ' + cls + '" style="font-size:0.7rem;display:block;margin-top:2px;" title="' + (label || '') + '">' + txt + '</span>';
    }

    function showLoading(show) {
        var loadingEl = document.getElementById('bi-loading');
        var skeletonEl = document.getElementById('bi-skeleton');
        var mainEl = document.getElementById('bi-main-content');
        var errorEl = document.getElementById('bi-error');

        if (show) {
            if (skeletonEl) {
                skeletonEl.classList.add('is-visible');
                skeletonEl.setAttribute('aria-hidden', 'false');
            }
            if (loadingEl) {
                loadingEl.style.display = 'none';
                loadingEl.setAttribute('aria-busy', 'true');
            }
            if (mainEl) mainEl.style.opacity = '0.35';
            if (errorEl) errorEl.style.display = 'none';
        } else {
            if (skeletonEl) {
                skeletonEl.classList.remove('is-visible');
                skeletonEl.setAttribute('aria-hidden', 'true');
            }
            if (loadingEl) {
                loadingEl.setAttribute('aria-busy', 'false');
            }
            if (mainEl) {
                mainEl.style.opacity = '1';
                mainEl.style.transition = 'opacity 0.3s ease';
            }
            var ts = document.getElementById('bi-updated-time');
            if (ts) { try { ts.textContent = new Date().toLocaleString('pt-BR'); } catch (e) {} }
        }
    }
    function biToast(msg) {
        var el = document.getElementById('bi-toast');
        if (!el) return;
        el.textContent = msg || '';
        el.classList.add('is-show');
        clearTimeout(biToast._t);
        biToast._t = setTimeout(function () { el.classList.remove('is-show'); }, 2800);
    }
    function biShareDashboard() {
        updateUrlFromFilters();
        var url = window.location.href;
        function ok() { biToast('Link copiado — compartilhe com sua equipe.'); }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(ok).catch(function () {
                window.prompt('Copie o link do painel:', url);
            });
        } else {
            window.prompt('Copie o link do painel:', url);
            ok();
        }
    }
    function biEmptyHtml(icon, title, desc) {
        return '<div class="biq-empty"><i class="fas ' + (icon || 'fa-chart-column') + '" aria-hidden="true"></i>'
            + '<p class="biq-empty__t">' + biHtmlEsc(title || 'Sem dados') + '</p>'
            + (desc ? '<p class="biq-empty__d">' + biHtmlEsc(desc) + '</p>' : '') + '</div>';
    }
    function biInjectChartEmpty(canvasId, icon, title, desc) {
        var cv = document.getElementById(canvasId);
        if (!cv) return;
        var body = cv.closest('.biq-chart-wrap') || cv.closest('.card-body');
        if (!body) return;
        body.querySelectorAll('.biq-empty').forEach(function (n) { n.remove(); });
        cv.style.display = 'none';
        var wrap = document.createElement('div');
        wrap.innerHTML = biEmptyHtml(icon, title, desc);
        body.appendChild(wrap.firstChild);
    }
    function biClearChartEmpties() {
        document.querySelectorAll('.biq-empty').forEach(function (n) { n.remove(); });
        BI_CHART_CANVAS_IDS.forEach(function (id) {
            var cv = document.getElementById(id);
            if (cv) cv.style.display = '';
        });
    }
    function biUpdateExecSubtitle(visao) {
        var sub = document.querySelector('.bi-exec-sub');
        if (sub) sub.textContent = biVisaoSubtitles[visao] || biVisaoSubtitles.geral;
    }
    function showError(msg) {
        var errEl = document.getElementById('bi-error');
        if (errEl) {
            errEl.textContent = msg;
            errEl.style.display = 'block';
            try {
                errEl.focus();
            } catch (e) { /* ignore */ }
        }
        document.getElementById('bi-kpis').style.display = 'none';
        document.getElementById('bi-charts').style.display = 'none';
        var premErr = document.getElementById('bi-premium-geral');
        if (premErr) premErr.style.display = 'none';
        document.getElementById('bi-table-wrap').style.display = 'none';
    }
    function showAvisoDadosIncompletos(data) {
        var el = document.getElementById('bi-aviso-incompletos');
        if (!el) return;
        var d = (data && data.dados_incompletos) ? data.dados_incompletos : null;
        if (!d || !d.tem_incompletos) { el.style.display = 'none'; el.innerHTML = ''; return; }
        var parts = [];
        if (d.rotas_sem_despesas > 0) parts.push(d.rotas_sem_despesas + ' rota(s) sem despesas de viagem');
        if (d.abastecimentos_sem_litros > 0) parts.push(d.abastecimentos_sem_litros + ' abastecimento(s) sem litros');
        if (d.manutencoes_sem_tipo > 0) parts.push(d.manutencoes_sem_tipo + ' manutenção(ões) sem tipo');
        el.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <strong>Existem dados incompletos que podem impactar os resultados:</strong> ' + parts.join('; ') + '.';
        el.style.display = 'block';
    }
    function biKpiSparkSvg(points, color) {
        var pts = (points || []).map(function (p) { var n = biCoerceNumber(p); return isNaN(n) ? 0 : n; });
        if (pts.length === 1) pts = [pts[0], pts[0]];
        if (pts.length < 2) return '<span class="biq-spark" aria-hidden="true"></span>';
        var w = 84, h = 28, pad = 3;
        var min = Math.min.apply(null, pts), max = Math.max.apply(null, pts), rng = (max - min) || 1;
        var step = (w - pad * 2) / (pts.length - 1);
        var coords = pts.map(function (p, i) {
            var x = pad + i * step;
            var y = h - pad - ((p - min) / rng) * (h - pad * 2);
            return x.toFixed(1) + ',' + y.toFixed(1);
        });
        var last = coords[coords.length - 1].split(',');
        return '<svg class="biq-spark" viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="none" aria-hidden="true">'
            + '<polyline fill="none" stroke="' + color + '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="' + coords.join(' ') + '"/>'
            + '<circle cx="' + last[0] + '" cy="' + last[1] + '" r="2.4" fill="' + color + '"/></svg>';
    }
    function biKpiDeltaBadge(pct) {
        if (pct == null || isNaN(pct)) return '<span class="biq-delta biq-delta--flat"><i class="fas fa-minus"></i> ±0,0%</span>';
        var cls = pct > 0 ? 'biq-delta--up' : (pct < 0 ? 'biq-delta--down' : 'biq-delta--flat');
        var arrow = pct > 0 ? 'fa-arrow-trend-up' : (pct < 0 ? 'fa-arrow-trend-down' : 'fa-minus');
        var sign = pct > 0 ? '+' : (pct < 0 ? '−' : '±');
        return '<span class="biq-delta ' + cls + '"><i class="fas ' + arrow + '"></i>' + sign + Math.abs(pct).toFixed(1).replace('.', ',') + '%</span>';
    }
    function biHistRowCustoKm(row) {
        var km = biCoerceNumber(row.total_km_rodados) || 0;
        if (km <= 0) return 0;
        var ct = (biCoerceNumber(row.total_gasto_abastecimentos) || 0) + (biCoerceNumber(row.total_manutencoes) || 0)
            + (biCoerceNumber(row.total_despesas_viagem) || 0) + (biCoerceNumber(row.total_despesas_fixas) || 0)
            + (biCoerceNumber(row.total_comissao) || 0);
        return ct / km;
    }
    function biHistRowTicket(row) {
        var rotas = biCoerceNumber(row.total_rotas) || 0;
        return rotas > 0 ? (biCoerceNumber(row.total_frete) || 0) / rotas : 0;
    }
    function biSerieFromHist(hist, picker) {
        return (hist || []).map(function (row) { return picker(row); });
    }
    function biVariacaoFromSerie(serie, comp, attr, ref) {
        var nums = (serie || []).map(biCoerceNumber);
        var i;
        for (i = nums.length - 1; i >= 1; i--) {
            if (isNaN(nums[i]) || isNaN(nums[i - 1])) continue;
            if (nums[i] === 0 && nums[i - 1] === 0) continue;
            var v = variacaoPct(nums[i], nums[i - 1]);
            if (v != null && !isNaN(v)) return v;
        }
        if (nums.length >= 2) {
            var first = nums[0], last = nums[nums.length - 1];
            if (!isNaN(first) && !isNaN(last) && (first !== 0 || last !== 0)) {
                var vt = variacaoPct(last, first);
                if (vt != null && !isNaN(vt)) return vt;
            }
        }
        if (comp && comp.mes_anterior && ref && attr) {
            var vc = variacaoPct(biCoerceNumber(ref[attr]), biCoerceNumber(comp.mes_anterior[attr]));
            if (vc != null && !isNaN(vc)) return vc;
        }
        if (comp && comp.mesmo_mes_ano_anterior && ref && attr) {
            var vy = variacaoPct(biCoerceNumber(ref[attr]), biCoerceNumber(comp.mesmo_mes_ano_anterior[attr]));
            if (vy != null && !isNaN(vy)) return vy;
        }
        return 0;
    }
    function biVariacaoKpi(spark, comp, attr, ref, label) {
        if (biKpiCompareMode === 'year' && comp && comp.mesmo_mes_ano_anterior && ref && attr) {
            var cur = biCoerceNumber(ref[attr]);
            var ant = biCoerceNumber(comp.mesmo_mes_ano_anterior[attr]);
            if (!isNaN(cur) && !isNaN(ant) && (cur !== 0 || ant !== 0)) {
                var vy = variacaoPct(cur, ant);
                if (vy != null && !isNaN(vy)) return { pct: vy, hint: 'vs mesmo mês ano anterior' };
            }
        }
        var d = biVariacaoFromSerie(spark, comp, attr, ref);
        return { pct: d, hint: biKpiHintForDelta(d, label, null) };
    }
    function biKpiHintForDelta(pct, label, defaults) {
        if (pct != null && !isNaN(pct) && Math.abs(pct) >= 0.05) return 'vs mês anterior';
        if (label === 'Custo / km') return 'queda é boa';
        if (label === 'Ticket médio') return 'por rota';
        if (label === 'Lucro oper.') return 'margem no período';
        return defaults || 'no período';
    }
    function biKpiCardPremium(label, value, icon, iconCls, color, sparkSerie, deltaPct, hint, tip) {
        var spark = biKpiSparkSvg(sparkSerie, color);
        var badge = biKpiDeltaBadge(deltaPct);
        var iconFa = (icon && String(icon).trim()) ? icon : 'fa-chart-column';
        var ic = iconCls || 'ic-blue';
        var tipAttr = tip ? ' title="' + biHtmlEsc(tip) + '"' : '';
        return '<div class="biq-card biq-kpi">'
            + '<div class="biq-kpi__top"><span class="biq-kpi__label biq-kpi-tip"' + tipAttr + '>' + biHtmlEsc(label) + '</span>'
            + '<span class="biq-kpi__icon ' + ic + '"><i class="fas ' + iconFa + '" aria-hidden="true"></i></span></div>'
            + '<div class="biq-kpi__value">' + value + '</div>'
            + '<div class="biq-kpi__foot">' + badge + spark + '</div>'
            + '<div class="biq-kpi__hint" style="margin-top:4px">' + biHtmlEsc(hint) + '</div>'
            + '</div>';
    }
    function biHistRowMedia(attrQtd, attrVal) {
        return function (row) {
            var q = biCoerceNumber(row[attrQtd]) || 0;
            return q > 0 ? (biCoerceNumber(row[attrVal]) || 0) / q : 0;
        };
    }
    function biHistRowDespPorRota(row) {
        var r = biCoerceNumber(row.total_rotas) || 0;
        return r > 0 ? (biCoerceNumber(row.total_despesas_viagem) || 0) / r : 0;
    }
    function biHistRowCustoKmManut(row) {
        var km = biCoerceNumber(row.total_km_rodados) || 0;
        return km > 0 ? (biCoerceNumber(row.total_manutencoes) || 0) / km : 0;
    }
    function renderKpisFromConfig(hist, comp, ref, cards) {
        return cards.map(function (c) {
            var spark = c.sparkFn ? biSerieFromHist(hist, c.sparkFn) : (c.attr ? biSerieFromHist(hist, function (r) { return r[c.attr]; }) : []);
            var vd = biVariacaoKpi(spark, comp, c.attr || null, ref, c.label);
            var hint = c.hint && biKpiCompareMode === 'month' ? c.hint : vd.hint;
            return biKpiCardPremium(c.label, c.value, c.icon, c.cls, c.color, spark, vd.pct, hint, c.tip);
        }).join('');
    }
    function renderKpisGeralPremium(hist, comp, totals) {
        var ref = hist.length ? hist[hist.length - 1] : null;
        var margemPct = totals.totalFrete > 0 ? (totals.lucro / totals.totalFrete) * 100 : 0;
        return renderKpisFromConfig(hist, comp, ref, [
            { label: 'Faturamento', value: formatMoney(totals.totalFrete), icon: 'fa-sack-dollar', cls: 'ic-green', color: '#22C55E', attr: 'total_frete', hint: 'vs mês anterior', tip: 'Soma do frete faturado no período filtrado' },
            { label: 'Lucro oper.', value: formatMoney(totals.lucro), icon: 'fa-chart-line', cls: 'ic-blue', color: '#3B82F6', attr: 'lucro_operacional', hint: 'margem ' + margemPct.toFixed(1).replace('.', ',') + '%', tip: 'Receita menos custos operacionais (comissão, combustível, manutenção, despesas)' },
            { label: 'Custo / km', value: formatMoney(totals.custoKmKpi), icon: 'fa-gas-pump', cls: 'ic-amber', color: '#F59E0B', sparkFn: biHistRowCustoKm, hint: 'queda é boa', tip: 'Custo total operacional dividido pelos km rodados' },
            { label: 'Km rodados', value: formatNum(totals.totalKm), icon: 'fa-route', cls: 'ic-cyan', color: '#06B6D4', attr: 'total_km_rodados', hint: 'no período', tip: 'Quilometragem total das rotas no período' },
            { label: 'Ticket médio', value: formatMoney(totals.ticket), icon: 'fa-truck-fast', cls: 'ic-violet', color: '#8B5CF6', sparkFn: biHistRowTicket, hint: 'por rota', tip: 'Faturamento médio por rota realizada' },
            { label: 'Total rotas', value: formatNum(totals.totalRotas), icon: 'fa-truck', cls: 'ic-blue', color: '#3B82F6', attr: 'total_rotas', hint: 'no período', tip: 'Quantidade de rotas concluídas' },
            { label: 'Gasto abast.', value: formatMoney(totals.gastoAbast), icon: 'fa-wallet', cls: 'ic-amber', color: '#F59E0B', attr: 'total_gasto_abastecimentos', hint: 'combustível', tip: 'Total gasto com abastecimentos' },
            { label: 'Manutenção', value: formatMoney(totals.totalManut), icon: 'fa-tools', cls: 'ic-red', color: '#EF4444', attr: 'total_manutencoes', hint: 'no período', tip: 'Custo total de ordens de manutenção' }
        ]);
    }
    function renderKPIs(data, visao) {
        visao = visao || 'geral';
        var hist = data.historico_mensal || [];
        var comp = data.comparacao || null;
        var ref = hist.length > 0 ? hist[hist.length - 1] : null;
        biKpiHist = hist;   // série usada nos sparklines dos KPIs
        biKpiIdx = 0;       // reinicia a cor de acento cíclica
        function sum(attr) {
            return hist.reduce(function (acc, row) {
                var n = biCoerceNumber(row[attr]);
                return acc + (isNaN(n) ? 0 : n);
            }, 0);
        }
        var totalRotas = sum('total_rotas');
        var totalKm = sum('total_km_rodados');
        var totalFrete = sum('total_frete');
        var totalComissao = sum('total_comissao');
        var totalAbast = sum('total_abastecimentos');
        var gastoAbast = sum('total_gasto_abastecimentos');
        var lucro = sum('lucro_operacional');
        var ticket = totalRotas > 0 ? totalFrete / totalRotas : 0;
        var mediaAbast = totalAbast > 0 ? gastoAbast / totalAbast : 0;
        var veiculosAbast = (data.veiculos_top_abastecimento || []).length;
        var totalDespViagem = sum('total_despesas_viagem');
        var totalManut = sum('total_manutencoes');
        var qtdManut = sum('quantidade_manutencoes');
        var totalDespFixas = sum('total_despesas_fixas');
        var qtdDespFixas = sum('quantidade_despesas_fixas');
        var mediaManut = qtdManut > 0 ? totalManut / qtdManut : 0;
        var mediaDespFixas = qtdDespFixas > 0 ? totalDespFixas / qtdDespFixas : 0;

        var html = '';
        var kpisEl = document.getElementById('bi-kpis');
        if (visao === 'geral') {
            var custoTotalKpi = gastoAbast + totalManut + totalDespViagem + totalDespFixas + totalComissao;
            var custoKmKpi = totalKm > 0 ? custoTotalKpi / totalKm : 0;
            html = renderKpisGeralPremium(hist, comp, {
                totalFrete: totalFrete, lucro: lucro, custoKmKpi: custoKmKpi, totalKm: totalKm,
                ticket: ticket, totalRotas: totalRotas, gastoAbast: gastoAbast, totalManut: totalManut
            });
        } else if (visao === 'rotas') {
            html = renderKpisFromConfig(hist, comp, ref, [
                { label: 'Total rotas', value: formatNum(totalRotas), icon: 'fa-route', cls: 'ic-blue', color: '#3B82F6', attr: 'total_rotas', hint: 'no período' },
                { label: 'Km rodados', value: formatNum(totalKm), icon: 'fa-tachometer-alt', cls: 'ic-cyan', color: '#06B6D4', attr: 'total_km_rodados', hint: 'no período' },
                { label: 'Faturamento', value: formatMoney(totalFrete), icon: 'fa-sack-dollar', cls: 'ic-green', color: '#22C55E', attr: 'total_frete', hint: 'vs mês anterior' },
                { label: 'Comissão', value: formatMoney(totalComissao), icon: 'fa-hand-holding-usd', cls: 'ic-violet', color: '#8B5CF6', attr: 'total_comissao', hint: 'no período' },
                { label: 'Ticket médio', value: formatMoney(ticket), icon: 'fa-truck-fast', cls: 'ic-amber', color: '#F59E0B', sparkFn: biHistRowTicket, hint: 'por rota' },
                { label: 'Lucro oper.', value: formatMoney(lucro), icon: 'fa-chart-line', cls: 'ic-green', color: '#22C55E', attr: 'lucro_operacional', hint: 'margem no período' }
            ]);
        } else if (visao === 'manutencao') {
            var custoKmManut = totalKm > 0 ? totalManut / totalKm : 0;
            html = renderKpisFromConfig(hist, comp, ref, [
                { label: 'Manutenções', value: formatNum(qtdManut), icon: 'fa-tools', cls: 'ic-blue', color: '#3B82F6', attr: 'quantidade_manutencoes', hint: 'no período' },
                { label: 'Custo total', value: formatMoney(totalManut), icon: 'fa-screwdriver-wrench', cls: 'ic-red', color: '#EF4444', attr: 'total_manutencoes', hint: 'vs mês anterior' },
                { label: 'Média / manut.', value: formatMoney(mediaManut), icon: 'fa-coins', cls: 'ic-amber', color: '#F59E0B', sparkFn: biHistRowMedia('quantidade_manutencoes', 'total_manutencoes'), hint: 'por ordem de serviço' },
                { label: 'Custo / km', value: formatMoney(custoKmManut), icon: 'fa-tachometer-alt', cls: 'ic-cyan', color: '#06B6D4', sparkFn: biHistRowCustoKmManut, hint: 'queda é boa' },
                { label: 'Veículos', value: formatNum((data.veiculos_top_manutencao || []).length), icon: 'fa-truck', cls: 'ic-violet', color: '#8B5CF6', attr: 'quantidade_manutencoes', hint: 'com manutenção' }
            ]);
        } else if (visao === 'despesas_viagem') {
            html = renderKpisFromConfig(hist, comp, ref, [
                { label: 'Desp. viagem', value: formatMoney(totalDespViagem), icon: 'fa-road', cls: 'ic-blue', color: '#3B82F6', attr: 'total_despesas_viagem', hint: 'vs mês anterior' },
                { label: 'Rotas', value: formatNum(totalRotas), icon: 'fa-route', cls: 'ic-cyan', color: '#06B6D4', attr: 'total_rotas', hint: 'no período' },
                { label: 'Média / rota', value: formatMoney(totalRotas > 0 ? totalDespViagem / totalRotas : 0), icon: 'fa-coins', cls: 'ic-amber', color: '#F59E0B', sparkFn: biHistRowDespPorRota, hint: 'por rota' }
            ]);
        } else if (visao === 'despesas_fixas') {
            html = renderKpisFromConfig(hist, comp, ref, [
                { label: 'Qtde fixas', value: formatNum(qtdDespFixas), icon: 'fa-file-invoice-dollar', cls: 'ic-violet', color: '#8B5CF6', attr: 'quantidade_despesas_fixas', hint: 'no período' },
                { label: 'Total pago', value: formatMoney(totalDespFixas), icon: 'fa-sack-dollar', cls: 'ic-green', color: '#22C55E', attr: 'total_despesas_fixas', hint: 'vs mês anterior' },
                { label: 'Média / despesa', value: formatMoney(mediaDespFixas), icon: 'fa-coins', cls: 'ic-amber', color: '#F59E0B', sparkFn: biHistRowMedia('quantidade_despesas_fixas', 'total_despesas_fixas'), hint: 'por lançamento' }
            ]);
        } else {
            html = renderKpisFromConfig(hist, comp, ref, [
                { label: 'Abastecimentos', value: formatNum(totalAbast), icon: 'fa-gas-pump', cls: 'ic-blue', color: '#3B82F6', attr: 'total_abastecimentos', hint: 'no período' },
                { label: 'Gasto total', value: formatMoney(gastoAbast), icon: 'fa-wallet', cls: 'ic-amber', color: '#F59E0B', attr: 'total_gasto_abastecimentos', hint: 'combustível' },
                { label: 'Média / abast.', value: formatMoney(mediaAbast), icon: 'fa-coins', cls: 'ic-green', color: '#22C55E', sparkFn: biHistRowMedia('total_abastecimentos', 'total_gasto_abastecimentos'), hint: 'por abastecimento' },
                { label: 'Veículos', value: formatNum(veiculosAbast), icon: 'fa-truck', cls: 'ic-cyan', color: '#06B6D4', attr: 'total_abastecimentos', hint: 'abastecidos' }
            ]);
        }
        document.getElementById('bi-kpis').innerHTML = html;
        document.getElementById('bi-kpis').style.display = 'grid';
        if (kpisEl) kpisEl.classList.add('biq-kpis');

        if (visao === 'geral') {
            var custoTotal = gastoAbast + totalManut + totalDespViagem + totalDespFixas;
            var custoPorKm = totalKm > 0 ? custoTotal / totalKm : 0;
            var lucroPorKm = totalKm > 0 ? lucro / totalKm : 0;
            var margemPct = totalFrete > 0 ? (lucro / totalFrete) * 100 : 0;
            var custoPorRota = totalRotas > 0 ? custoTotal / totalRotas : 0;
            var semaforoClass = margemPct < 10 ? 'vermelho' : margemPct <= 20 ? 'amarelo' : 'verde';
            var semaforoTexto = margemPct < 10 ? 'Atenção: margem baixa (&lt;10%)' : margemPct <= 20 ? 'Margem moderada (10–20%)' : 'Margem saudável (&gt;20%)';
            var cardsEl = document.getElementById('bi-saude-cards');
            var semaforoEl = document.getElementById('bi-semaforo');
            var textoEl = document.getElementById('bi-semaforo-texto');
            var scoreWrap = document.getElementById('bi-score-frota-wrap');
            if (scoreWrap) {
                var margemScore = Math.min(100, Math.max(0, margemPct * 5));
                var semaforoScore = semaforoClass === 'verde' ? 100 : (semaforoClass === 'amarelo' ? 60 : 25);
                var crescimentoScore = 50;
                if (comp && comp.mesmo_mes_ano_anterior && ref) {
                    var lucroAnt = Number(comp.mesmo_mes_ano_anterior.lucro_operacional) || 0;
                    var lucroRef = Number(ref.lucro_operacional) || 0;
                    if (lucroAnt !== 0) {
                        var growth = ((lucroRef - lucroAnt) / Math.abs(lucroAnt)) * 100;
                        crescimentoScore = Math.min(100, Math.max(0, 50 + growth));
                    }
                }
                var score = Math.round(margemScore * 0.4 + semaforoScore * 0.3 + crescimentoScore * 0.3);
                score = Math.min(100, Math.max(0, score));
                var scoreClass = score >= 70 ? 'bom' : (score >= 40 ? 'medio' : 'baixo');
                var scoreTexto = score >= 70 ? 'Bom' : (score >= 40 ? 'Médio' : 'Atenção');
                scoreWrap.innerHTML = '<div class="bi-score-frota-num ' + scoreClass + '">' + score + '<span style="font-size:0.6em; font-weight:600; opacity:0.8;">/100</span></div><div><div class="bi-score-frota-label">Score da Frota</div><div class="bi-score-frota-desc">' + scoreTexto + ' &middot; Baseado em margem, desempenho e crescimento</div></div>';
                scoreWrap.style.display = 'flex';
            }
            if (cardsEl) {
                cardsEl.innerHTML = '<div class="bi-saude-card"><div class="bi-saude-value">' + formatMoney(custoPorKm) + '</div><div class="bi-saude-label">Custo por KM</div><div class="bi-saude-label" style="font-size:0.7rem;opacity:0.8;">(Abast + Manut + Desp)</div></div>'
                    + '<div class="bi-saude-card' + (lucro < 0 ? ' negative' : '') + '"><div class="bi-saude-value">' + formatMoney(lucroPorKm) + '</div><div class="bi-saude-label">Lucro por KM</div></div>'
                    + '<div class="bi-saude-card' + (margemPct < 0 ? ' negative' : '') + '"><div class="bi-saude-value">' + (margemPct.toFixed(1)) + '%</div><div class="bi-saude-label">Margem operacional</div><div class="bi-saude-label" style="font-size:0.7rem;opacity:0.8;">Lucro ÷ Faturamento</div></div>'
                    + '<div class="bi-saude-card"><div class="bi-saude-value">' + formatMoney(custoPorRota) + '</div><div class="bi-saude-label">Custo por Rota</div></div>';
            }
            if (semaforoEl) {
                semaforoEl.innerHTML = '<span class="bi-semaforo-dot ' + semaforoClass + '" title="Margem ' + margemPct.toFixed(1) + '%"></span>';
            }
            if (textoEl) {
                textoEl.textContent = semaforoTexto.replace(/&lt;/g, '<').replace(/&gt;/g, '>');
                textoEl.className = 'bi-semaforo-texto ' + semaforoClass;
            }
            var saudeWrap = document.getElementById('bi-saude-frota');
            if (saudeWrap) saudeWrap.style.display = 'block';
        } else {
            var saudeWrap = document.getElementById('bi-saude-frota');
            if (saudeWrap) saudeWrap.style.display = 'none';
        }
    }

    function biChartCssVar(name, fallback) {
        try {
            var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
            return v || fallback;
        } catch (e) { return fallback; }
    }
    function biChartPalette() {
        return BiChartsV4.palette();
    }

    var BI_CHART_CANVAS_IDS = ['chartRotasTempo','chartFreteMensal','chartKmMensal','chartAbastTempo','chartTopVeiculos','chartDespViagemTipos','chartCustoKmHist','chartManutPreventivaCorretiva','chartReceitaCustoLucro','chartComposicaoCustos','chartMotoristaLucro'];
    function destroyCharts() {
        BiChartsV4.destroyAll(charts, BI_CHART_CANVAS_IDS);
        var dw = document.getElementById('bi-desp-viagem-donut-wrap');
        if (dw) dw.querySelectorAll('.biq-empty').forEach(function (n) { n.remove(); });
        var mw = document.getElementById('bi-manut-donut-wrap');
        if (mw) mw.querySelectorAll('.biq-empty').forEach(function (n) { n.remove(); });
    }

    var biChartsResizeObserver = null;
    var biChartsResizeDebounce = null;
    function biEnsureChartsResizeObserver() {
        if (typeof ResizeObserver === 'undefined') return;
        if (biChartsResizeObserver) {
            try { biChartsResizeObserver.disconnect(); } catch (eRo) {}
            biChartsResizeObserver = null;
        }
        biChartsResizeObserver = new ResizeObserver(function () {
            if (biChartsResizeDebounce) clearTimeout(biChartsResizeDebounce);
            biChartsResizeDebounce = setTimeout(function () {
                biReflowBiCharts();
            }, 60);
        });
        try {
            var grid = document.getElementById('bi-charts');
            if (grid) biChartsResizeObserver.observe(grid);
            var prem = document.getElementById('bi-premium-geral');
            if (prem) biChartsResizeObserver.observe(prem);
            var main = document.querySelector('.main-content');
            if (main) biChartsResizeObserver.observe(main);
        } catch (eOb) {}
    }

    /* Chart.js 2.9 + responsive:false: o bitmap usa canvas.width/height; CSS sozinho não redimensiona o desenho */
    function biElementOrAncestorHidden(el) {
        if (!el) return true;
        try {
            var p = el;
            while (p && p !== document.documentElement) {
                if (p.nodeType !== 1) {
                    p = p.parentElement;
                    continue;
                }
                var st = window.getComputedStyle(p);
                if (st.display === 'none' || st.visibility === 'hidden' || parseFloat(st.opacity || '1') === 0) return true;
                p = p.parentElement;
            }
        } catch (e) { return true; }
        return false;
    }
    /** Largura segura quando o card ainda está em painel display:none (evita bitmap largura total da página). */
    function biFallbackCanvasWidth(canvasEl) {
        var full = false;
        try {
            var card = canvasEl.closest && canvasEl.closest('.bi-chart-card');
            if (card && card.classList && card.classList.contains('bi-chart-full')) full = true;
        } catch (e) {}
        var grid = document.getElementById('bi-charts');
        var gridOk = grid && !biElementOrAncestorHidden(grid) && grid.clientWidth > 80;
        var gw = gridOk ? grid.clientWidth : 0;
        if (gw) {
            var gap = 10;
            var col = Math.max(220, Math.floor((gw - gap) / 2) - 20);
            if (full) return Math.max(280, Math.min(gw - 32, 1200));
            return Math.max(240, Math.min(col, 720));
        }
        var mc = document.querySelector('.dashboard-content') || document.querySelector('.main-content');
        var mw = (mc && mc.clientWidth > 80) ? mc.clientWidth : 640;
        if (full) return Math.max(280, Math.min(mw - 48, 1200));
        return Math.max(240, Math.min(Math.floor((mw - 64) / 2), 720));
    }
    function biCanvasHeightPx(canvasEl, id) {
        if (id === 'chartDespViagemTipos' || id === 'chartComposicaoCustos') return 240;
        if (id === 'chartManutPreventivaCorretiva') return 200;
        if (id === 'chartReceitaCustoLucro') return 280;
        if (id === 'chartComposicaoCustos' || id === 'chartMotoristaLucro') return 230;
        try {
            if (canvasEl && canvasEl.closest && canvasEl.closest('#bi-charts')) {
                var card = canvasEl.closest('.bi-chart-card');
                if (card && card.classList && card.classList.contains('bi-chart-full')) return 268;
            }
        } catch (e) { /* ignore */ }
        return 220;
    }

    function biPrepAllBiChartCanvases() { /* noop v4 */ }
    function biReflowBiCharts() {
        BiChartsV4.reflow(charts, BI_CHART_CANVAS_IDS);
    }
    /** Reflow após sidebar (.main-content transition ~300ms) e segundo frame — evita canvas “vazio” até mover o mouse */
    function biScheduleChartLayoutStabilize() {
        function run() {
            biReflowBiCharts();
        }
        if (typeof requestAnimationFrame === 'function') {
            requestAnimationFrame(function () {
                requestAnimationFrame(run);
            });
        } else {
            setTimeout(run, 0);
        }
        setTimeout(run, 280);
    }

    function simuladorUpdate() {
        var wrap = document.getElementById('bi-simulador-content');
        if (!wrap || !wrap.dataset.frete) return;
        var frete = parseFloat(wrap.dataset.frete) || 0, comissao = parseFloat(wrap.dataset.comissao) || 0, gastoAbast = parseFloat(wrap.dataset.gastoAbast) || 0, despViagem = parseFloat(wrap.dataset.despViagem) || 0, lucroAtual = parseFloat(wrap.dataset.lucro) || 0;
        var dieselPct = parseFloat(document.getElementById('sim-diesel-pct') && document.getElementById('sim-diesel-pct').value) || 0;
        var comissaoPct = parseFloat(document.getElementById('sim-comissao-pct') && document.getElementById('sim-comissao-pct').value) || 0;
        var newGastoAbast = gastoAbast * (1 + dieselPct / 100);
        var newComissao = comissao * (1 - comissaoPct / 100);
        var lucroSimulado = frete - newComissao - newGastoAbast - despViagem;
        var impacto = lucroSimulado - lucroAtual;
        var impactoPct = lucroAtual !== 0 ? ((impacto / Math.abs(lucroAtual)) * 100).toFixed(1) : '—';
        var el = document.getElementById('bi-simulador-result');
        if (!el) return;
        el.style.display = 'block';
        var cls = impacto >= 0 ? 'positive' : 'negative';
        el.innerHTML = '<strong>Lucro atual (período):</strong> ' + formatMoney(lucroAtual) + '<br><strong>Lucro simulado:</strong> ' + formatMoney(lucroSimulado) + '<br><span class="impacto variation ' + cls + '">Impacto: ' + (impacto >= 0 ? '+' : '') + formatMoney(impacto) + ' (' + (impacto >= 0 ? '+' : '') + impactoPct + '%)</span>';
    }
    function hideAllExtraBlocks() {
        ['bi-ranking-rotas','bi-abast-extra','bi-manut-extra','bi-desp-viagem-extra','bi-desp-fixas-extra','bi-insights-ia','bi-alertas-periodo','bi-ponto-equilibrio','bi-tendencias','bi-simulador','bi-custo-km-hist'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });
    }
    function biStatCard(value, label, hint, valueCls) {
        return '<div class="biq-stat">'
            + '<div class="biq-stat__value' + (valueCls ? ' ' + valueCls : '') + '">' + value + '</div>'
            + '<div class="biq-stat__label">' + biHtmlEsc(label) + '</div>'
            + (hint ? '<div class="biq-stat__hint">' + biHtmlEsc(hint) + '</div>' : '')
            + '</div>';
    }
    function biRenderDespViagemPremiumContent(data, totalKm, totalDespViagem, totalRotas, totalFrete) {
        var despKm = totalKm > 0 ? totalDespViagem / totalKm : 0;
        var despRota = totalRotas > 0 ? totalDespViagem / totalRotas : 0;
        var pctFrete = totalFrete > 0 ? (totalDespViagem / totalFrete) * 100 : 0;
        var tipos = data.despesas_viagem_tipos || {};
        var tipoEntries = [
            { k: 'Pedágio', v: Number(tipos.pedagios) || 0 },
            { k: 'Alimentação', v: Number(tipos.caixinha) || 0 },
            { k: 'Estacion./lavagem', v: (Number(tipos.estacionamento) || 0) + (Number(tipos.lavagem) || 0) },
            { k: 'Outros', v: (Number(tipos.descarga) || 0) + (Number(tipos.borracharia) || 0) + (Number(tipos.eletrica_mecanica) || 0) + (Number(tipos.adiantamento) || 0) + (Number(tipos.outros) || 0) }
        ].sort(function (a, b) { return b.v - a.v; });
        var maior = tipoEntries[0] && tipoEntries[0].v > 0 ? tipoEntries[0].k : null;
        var html = biStatCard(formatMoney(despKm), 'Despesa média por KM', 'custo variável ÷ km rodados', 'biq-stat__value--primary')
            + biStatCard(formatMoney(despRota), 'Média por rota', 'total desp. viagem ÷ rotas', 'biq-stat__value--warn')
            + biStatCard(formatMoney(totalDespViagem), 'Total no período', null, 'biq-stat__value--success')
            + biStatCard(pctFrete.toFixed(1).replace('.', ',') + '%', 'Do faturamento', maior ? 'maior tipo: ' + maior : null, '');
        var elD = document.getElementById('bi-desp-viagem-extra-content');
        if (elD) elD.innerHTML = html;
        var metaKm = document.getElementById('bi-desp-km-meta');
        var tagTipo = document.getElementById('bi-desp-tipo-tag');
        var ctxLbl = document.getElementById('bi-context-line') ? (document.getElementById('bi-context-line').textContent || 'Período') : 'Período';
        if (metaKm) metaKm.textContent = ctxLbl;
        if (tagTipo) tagTipo.textContent = (ctxLbl.split('·').pop() || '').trim() || 'Período';
    }
    function renderExtraBlocks(data, visao) {
        hideAllExtraBlocks();
        var hist = data.historico_mensal || [];
        function sum(attr) {
            return hist.reduce(function (acc, row) {
                var n = biCoerceNumber(row[attr]);
                return acc + (isNaN(n) ? 0 : n);
            }, 0);
        }
        var totalKm = sum('total_km_rodados');
        var totalFrete = sum('total_frete');
        var totalComissao = sum('total_comissao');
        var totalDespViagem = sum('total_despesas_viagem');
        var totalDespFixas = sum('total_despesas_fixas');
        var totalRotas = sum('total_rotas');
        var lucro = sum('lucro_operacional');
        var gastoAbast = sum('total_gasto_abastecimentos');
        var totalManut = sum('total_manutencoes');
        var totalLitros = hist.reduce(function(acc, row) {
            var n = biCoerceNumber(row.total_litros);
            return acc + (isNaN(n) ? 0 : n);
        }, 0);

        if (visao === 'rotas' && data.ranking_rotas) {
            var r = data.ranking_rotas;
            var top5 = (r.top5 || []).map(function(x) { return '<tr><td>' + biHtmlEsc(x.origem_destino || '-') + '</td><td>' + formatNum(x.km) + '</td><td>' + formatMoney(x.frete) + '</td><td>' + formatMoney(x.custo_total) + '</td><td>' + formatMoney(x.lucro) + '</td><td>' + (x.margem_pct ? x.margem_pct.toFixed(1) : '0') + '%</td></tr>'; }).join('');
            var bot5 = (r.bottom5 || []).map(function(x) { return '<tr><td>' + biHtmlEsc(x.origem_destino || '-') + '</td><td>' + formatNum(x.km) + '</td><td>' + formatMoney(x.frete) + '</td><td>' + formatMoney(x.custo_total) + '</td><td>' + formatMoney(x.lucro) + '</td><td>' + (x.margem_pct ? x.margem_pct.toFixed(1) : '0') + '%</td></tr>'; }).join('');
            var tbl = '<h4 style="margin-bottom:0.5rem;">Mais lucrativas</h4><table class="bi-table"><thead><tr><th>Rota</th><th>KM</th><th>Frete</th><th>Custo</th><th>Lucro</th><th>Margem %</th></tr></thead><tbody>' + top5 + '</tbody></table><h4 style="margin-top:1rem;">Menos lucrativas</h4><table class="bi-table"><thead><tr><th>Rota</th><th>KM</th><th>Frete</th><th>Custo</th><th>Lucro</th><th>Margem %</th></tr></thead><tbody>' + bot5 + '</tbody></table>';
            var el = document.getElementById('bi-ranking-rotas-content');
            if (el) { el.innerHTML = tbl; document.getElementById('bi-ranking-rotas').style.display = 'block'; }
        }
        if (visao === 'abastecimento') {
            var consumoMedio = totalLitros > 0 && totalKm > 0 ? (totalKm / totalLitros) : 0;
            var custoKmAbast = totalKm > 0 ? gastoAbast / totalKm : 0;
            var totalAbast = sum('total_abastecimentos');
            var elCards = document.getElementById('bi-abast-extra-cards');
            if (elCards) {
                elCards.innerHTML = biStatCard(consumoMedio > 0 ? consumoMedio.toFixed(2).replace('.', ',') + ' km/L' : '—', 'Consumo médio', 'km rodados ÷ litros', 'biq-stat__value--success')
                    + biStatCard(formatMoney(custoKmAbast), 'Custo combustível / KM', null, 'biq-stat__value--primary')
                    + biStatCard(formatMoney(gastoAbast), 'Gasto total', null, 'biq-stat__value--warn')
                    + biStatCard(formatNum(totalAbast), 'Abastecimentos', null, '');
            }
            var consumoList = (data.veiculos_consumo || []).filter(function (v) { return (v.total_litros || 0) > 0; }).sort(function (a, b) { return (b.consumo_medio || 0) - (a.consumo_medio || 0); });
            var elTab = document.getElementById('bi-abast-tabela-wrap');
            if (elTab) {
                elTab.innerHTML = consumoList.length
                    ? '<table class="bi-table"><thead><tr><th>Veículo</th><th>KM</th><th>Litros</th><th>KM/L</th></tr></thead><tbody>'
                    + consumoList.map(function (v) {
                        return '<tr><td>' + biHtmlEsc(v.placa || v.modelo || '-') + '</td><td>' + formatNum(v.total_km) + '</td><td>' + formatNum(v.total_litros) + '</td><td>' + (v.consumo_medio != null ? v.consumo_medio.toFixed(2) : '—') + '</td></tr>';
                    }).join('') + '</tbody></table>'
                    : biEmptyHtml('fa-truck', 'Sem consumo calculado', 'Cadastre litros nos abastecimentos.');
            }
            var alertas = [];
            (data.veiculos_consumo || []).filter(function (v) { return v.fora_media; }).forEach(function (v) {
                alertas.push('⛽ ' + (v.placa || v.modelo || 'Veículo') + ' com consumo abaixo da média (' + (v.consumo_medio || '—') + ' km/L).');
            });
            if (hist.length >= 2) {
                var ult = hist[hist.length - 1].total_gasto_abastecimentos || 0;
                var pen = hist[hist.length - 2].total_gasto_abastecimentos || 0;
                if (pen > 0 && ult > pen * 1.2) alertas.push('⚠️ Gasto com combustível subiu ' + (((ult - pen) / pen) * 100).toFixed(0) + '% no último mês.');
            }
            var elAlertas = document.getElementById('bi-abast-alertas');
            if (elAlertas) biRenderPremiumInsights(alertas.length ? alertas : ['✅ Nenhum alerta de consumo no período.'], 'bi-abast-alertas');
            var metaAb = document.getElementById('bi-abast-meta');
            if (metaAb) metaAb.textContent = document.getElementById('bi-context-line') ? document.getElementById('bi-context-line').textContent : 'Período';
            document.getElementById('bi-abast-extra').style.display = '';
        }
        if (visao === 'manutencao') {
            var custoManutKm = totalKm > 0 ? totalManut / totalKm : 0;
            var pc = data.manut_preventiva_corretiva || { preventiva: { qtd: 0, valor: 0 }, corretiva: { qtd: 0, valor: 0 } };
            var prev = pc.preventiva || {}; var corr = pc.corretiva || {};
            var qtdManut = sum('quantidade_manutencoes');
            var elC = document.getElementById('bi-manut-extra-content');
            if (elC) {
                elC.innerHTML = biStatCard(formatMoney(custoManutKm), 'Custo manutenção / KM', null, 'biq-stat__value--primary')
                    + biStatCard(formatMoney(totalManut), 'Custo total', null, 'biq-stat__value--warn')
                    + biStatCard(formatNum(qtdManut), 'Manutenções', null, '')
                    + (data.previsao_proximo_mes > 0 ? biStatCard(formatMoney(data.previsao_proximo_mes), 'Estimativa próx. mês', 'média 6 meses', 'biq-stat__value--success') : biStatCard(formatNum(prev.qtd || 0) + ' / ' + formatNum(corr.qtd || 0), 'Prev. / Corr. (qtd)', formatMoney(prev.valor || 0) + ' · ' + formatMoney(corr.valor || 0), ''));
            }
            var crit = (data.veiculos_criticos || []).slice(0, 8);
            var elV = document.getElementById('bi-veiculos-criticos');
            if (elV) {
                elV.innerHTML = crit.length
                    ? '<p class="biq-stat__hint" style="margin:0 0 8px"><strong>Veículos acima da média de custo</strong></p><div class="biq-insights">'
                    + crit.map(function (v) { return '<div class="biq-insight biq-insight--warn"><i class="fas fa-triangle-exclamation"></i><div><p class="biq-insight__t">' + biHtmlEsc(v.placa || v.modelo || 'Veículo') + '</p><p class="biq-insight__d">' + formatMoney(v.total_gasto) + '</p></div></div>'; }).join('')
                    + '</div><p style="margin-top:12px;"><a href="manutencoes.php" class="biq-btn" style="text-decoration:none;margin-right:8px;"><i class="fas fa-tools"></i> Manutenções</a></p>'
                    : '';
            }
            var metaM = document.getElementById('bi-manut-meta');
            var tagM = document.getElementById('bi-manut-pie-tag');
            var ctxLbl = document.getElementById('bi-context-line') ? document.getElementById('bi-context-line').textContent : 'Período';
            if (metaM) metaM.textContent = ctxLbl;
            if (tagM) tagM.textContent = (ctxLbl.split('·').pop() || '').trim() || 'Período';
            document.getElementById('bi-manut-extra').style.display = '';
        }
        if (visao === 'despesas_viagem') {
            biRenderDespViagemPremiumContent(data, totalKm, totalDespViagem, totalRotas, totalFrete);
            document.getElementById('bi-desp-viagem-extra').style.display = '';
        }
        if (visao === 'despesas_fixas') {
            var fixasKm = totalKm > 0 ? totalDespFixas / totalKm : 0;
            var impactoPct = totalFrete > 0 ? ((totalDespFixas / totalFrete) * 100) : 0;
            var qtdFix = sum('quantidade_despesas_fixas');
            var elF = document.getElementById('bi-desp-fixas-extra-content');
            if (elF) {
                elF.innerHTML = biStatCard(formatMoney(fixasKm), 'Fixas ÷ KM', null, 'biq-stat__value--primary')
                    + biStatCard(impactoPct.toFixed(1).replace('.', ',') + '%', 'Do faturamento', 'impacto no resultado', 'biq-stat__value--warn')
                    + biStatCard(formatMoney(totalDespFixas), 'Total pago', null, 'biq-stat__value--success')
                    + biStatCard(formatNum(qtdFix), 'Lançamentos', null, '');
            }
            var metaF = document.getElementById('bi-fixas-meta');
            if (metaF) metaF.textContent = document.getElementById('bi-context-line') ? document.getElementById('bi-context-line').textContent : 'Período';
            document.getElementById('bi-desp-fixas-extra').style.display = '';
        }
        if (visao === 'geral') {
            var anoUrl = (document.getElementById('filtro-ano') && document.getElementById('filtro-ano').value) ? document.getElementById('filtro-ano').value : '';
            var mesUrl = (document.getElementById('filtro-mes') && document.getElementById('filtro-mes').value) ? document.getElementById('filtro-mes').value : '';
            var qs = '?visao=geral' + (anoUrl ? '&ano=' + encodeURIComponent(anoUrl) : '') + (mesUrl ? '&mes=' + encodeURIComponent(mesUrl) : '');
            var qsAbast = '?visao=abastecimento' + (anoUrl ? '&ano=' + encodeURIComponent(anoUrl) : '') + (mesUrl ? '&mes=' + encodeURIComponent(mesUrl) : '');
            var qsManut = '?visao=manutencao' + (anoUrl ? '&ano=' + encodeURIComponent(anoUrl) : '') + (mesUrl ? '&mes=' + encodeURIComponent(mesUrl) : '');
            var qsRotas = '?visao=rotas' + (anoUrl ? '&ano=' + encodeURIComponent(anoUrl) : '') + (mesUrl ? '&mes=' + encodeURIComponent(mesUrl) : '');
            var alertasList = [];
            var refRow = (data.historico_mensal && data.historico_mensal.length) ? data.historico_mensal[data.historico_mensal.length - 1] : null;
            var margemRef = refRow && refRow.total_frete > 0 ? ((refRow.lucro_operacional / refRow.total_frete) * 100) : 0;
            if (margemRef < 0) alertasList.push({ txt: 'Margem operacional negativa no período.', href: qsRotas });
            (data.veiculos_consumo || []).filter(function(v) { return v.fora_media; }).forEach(function(v) { alertasList.push({ txt: 'Veículo ' + (v.placa || v.modelo || '-') + ' com consumo abaixo da média da frota.', href: qsAbast }); });
            (data.veiculos_criticos || []).slice(0, 5).forEach(function(v) { alertasList.push({ txt: 'Custo de manutenção acima da média: ' + (v.placa || v.modelo || '-') + '.', href: qsManut }); });
            (data.ranking_rotas && data.ranking_rotas.bottom5 || []).filter(function(r) { return r.lucro < 0; }).forEach(function(r) { alertasList.push({ txt: 'Rota com margem negativa: ' + (r.origem_destino || 'Rota').substring(0, 50) + '.', href: qsRotas }); });
            var elAlertas = document.getElementById('bi-alertas-periodo-content');
            if (elAlertas) elAlertas.innerHTML = alertasList.length ? '<ul style="margin:0; padding-left:1.2rem;">' + alertasList.map(function(a) { return '<li>' + (a.href ? '<a href="' + a.href + '">' + a.txt + '</a>' : a.txt) + '</li>'; }).join('') + '</ul>' : '<p class="bi-text-muted">Nenhum alerta ativo no período.</p>';
            document.getElementById('bi-alertas-periodo').style.display = 'block';

            if (data.ponto_equilibrio) {
                var pe = data.ponto_equilibrio;
                var elPe = document.getElementById('bi-ponto-equilibrio-content');
                if (elPe) {
                    elPe.innerHTML = '<p class="bi-text-muted" style="margin:0 0 0.5rem 0;">Referência: ' + (pe.mes_ref || 'último mês') + '</p><h4>Faturamento mínimo para empatar</h4><p style="margin:0; font-size:1.1rem; font-weight:600;">' + formatMoney(pe.faturamento_minimo) + '</p><h4 style="margin-top:0.75rem;">Rotas necessárias para empatar</h4><p style="margin:0; font-size:1.1rem;">' + formatNum(pe.rotas_para_empatar) + ' rotas</p><p class="bi-text-muted" style="margin-top:0.25rem; font-size:0.85rem;">Ticket médio de referência: ' + formatMoney(pe.ticket_medio_ref) + '</p>';
                }
                document.getElementById('bi-ponto-equilibrio').style.display = 'block';
            }
            var tendenciasList = [];
            if (data.historico_mensal && data.historico_mensal.length >= 3) {
                var h = data.historico_mensal;
                var m1 = (h[h.length - 3].total_frete > 0) ? (h[h.length - 3].lucro_operacional / h[h.length - 3].total_frete) * 100 : 0;
                var m2 = (h[h.length - 2].total_frete > 0) ? (h[h.length - 2].lucro_operacional / h[h.length - 2].total_frete) * 100 : 0;
                var m3 = (h[h.length - 1].total_frete > 0) ? (h[h.length - 1].lucro_operacional / h[h.length - 1].total_frete) * 100 : 0;
                if (m1 > m2 && m2 > m3) tendenciasList.push('📉 3 meses consecutivos de queda de margem operacional.');
                var g1 = h[h.length - 3].total_gasto_abastecimentos || 0, g2 = h[h.length - 2].total_gasto_abastecimentos || 0, g3 = h[h.length - 1].total_gasto_abastecimentos || 0;
                if (g1 < g2 && g2 < g3) tendenciasList.push('📈 Tendência de alta nos custos de combustível.');
            }
            var elTend = document.getElementById('bi-tendencias-content');
            if (elTend) elTend.innerHTML = tendenciasList.length ? '<ul style="margin:0; padding-left:1.2rem;">' + tendenciasList.map(function(t) { return '<li>' + t + '</li>'; }).join('') + '</ul>' : '<p class="bi-text-muted">Nenhuma tendência identificada nos últimos 3 meses.</p>';
            document.getElementById('bi-tendencias').style.display = 'block';

            var simWrap = document.getElementById('bi-simulador-content');
            if (simWrap) {
                simWrap.dataset.frete = totalFrete;
                simWrap.dataset.comissao = totalComissao;
                simWrap.dataset.gastoAbast = gastoAbast;
                simWrap.dataset.despViagem = totalDespViagem;
                simWrap.dataset.lucro = lucro;
                if (typeof simuladorUpdate === 'function') simuladorUpdate();
            }
            document.getElementById('bi-simulador').style.display = 'block';

            var hmCusto = data.historico_mensal || [];
            if (hmCusto.length > 120) hmCusto = hmCusto.slice(-120);
            var histCustoKm = hmCusto.map(function(m) {
                var ct = (biCoerceNumber(m.total_gasto_abastecimentos) || 0) + (biCoerceNumber(m.total_manutencoes) || 0) + (biCoerceNumber(m.total_despesas_viagem) || 0) + (biCoerceNumber(m.total_despesas_fixas) || 0);
                var km = biCoerceNumber(m.total_km_rodados) || 0;
                return km > 0 ? ct / km : 0;
            });
            var labelsCustoKm = hmCusto.map(function(x) { return x.mes_nome || x.mes_ano; });
            var wrapCustoHist = document.getElementById('bi-custo-km-hist');
            if (wrapCustoHist) wrapCustoHist.style.display = 'block';
            if (wrapCustoHist) void wrapCustoHist.offsetWidth;
            var canvasCustoKm = document.getElementById('chartCustoKmHist');
            if (canvasCustoKm && labelsCustoKm.length) {
                if (charts.chartCustoKmHist) { charts.chartCustoKmHist.destroy(); charts.chartCustoKmHist = null; }
                var mesAnosC = hmCusto.map(function(x) { return x.mes_ano || ''; });
                charts.chartCustoKmHist = new Chart(canvasCustoKm, {
                    type: 'line',
                    data: { labels: labelsCustoKm, datasets: [{ label: 'Custo/km (R$)', data: histCustoKm, borderColor: '#0d9488', backgroundColor: 'rgba(13,148,136,0.1)', fill: true }] },
                    options: biChartOptions()
                });
                biBindDrill(charts.chartCustoKmHist, { mesAnos: mesAnosC, labels: labelsCustoKm, serie: 'rotas' });
            }
            var topV = data.veiculos_top || [], abastV = data.veiculos_top_abastecimento || [], manutV = data.veiculos_top_manutencao || [];
            var byId = {};
            topV.forEach(function(v) { byId[v.id] = { id: v.id, placa: v.placa || '', modelo: v.modelo || '', total_km: Number(v.total_km) || 0, gasto_abast: 0, gasto_manut: 0 }; });
            abastV.forEach(function(v) { if (byId[v.id]) byId[v.id].gasto_abast = Number(v.total_gasto) || 0; else byId[v.id] = { id: v.id, placa: v.placa || '', modelo: v.modelo || '', total_km: 0, gasto_abast: Number(v.total_gasto) || 0, gasto_manut: 0 }; });
            manutV.forEach(function(v) { if (byId[v.id]) byId[v.id].gasto_manut = Number(v.total_gasto) || 0; else byId[v.id] = { id: v.id, placa: v.placa || '', modelo: v.modelo || '', total_km: 0, gasto_abast: 0, gasto_manut: Number(v.total_gasto) || 0 }; });
            var veiculosCustoKm = Object.keys(byId).map(function(k) { var v = byId[k]; v.custo_km = (v.total_km > 0) ? ((v.gasto_abast + v.gasto_manut) / v.total_km) : 0; return v; }).filter(function(v) { return v.total_km > 0 || (v.gasto_abast + v.gasto_manut) > 0; }).sort(function(a, b) { return b.custo_km - a.custo_km; });
            var elCustoVeic = document.getElementById('bi-custo-km-veiculos-content');
            if (elCustoVeic) elCustoVeic.innerHTML = veiculosCustoKm.length ? '<table class="bi-table"><thead><tr><th>Veículo</th><th>KM</th><th>Gasto abast. + manut.</th><th>Custo/km (R$)</th></tr></thead><tbody>' + veiculosCustoKm.map(function(v) { return '<tr><td>' + biHtmlEsc(v.placa || v.modelo || '-') + '</td><td>' + formatNum(v.total_km) + '</td><td>' + formatMoney(v.gasto_abast + v.gasto_manut) + '</td><td>' + formatMoney(v.custo_km) + '</td></tr>'; }).join('') + '</tbody></table>' : '<p class="bi-text-muted">Nenhum dado de veículo no período.</p>';

            if (data.historico_mensal && data.historico_mensal.length >= 2) {
                var insights = [];
                var h = data.historico_mensal;
                var ultimo = h[h.length - 1];
                var anterior = h[h.length - 2];
                var lucroAnt = anterior.lucro_operacional || 0;
                var lucroUlt = ultimo.lucro_operacional || 0;
                if (lucroAnt !== 0 && lucroUlt !== lucroAnt) {
                    var varPct = ((lucroUlt - lucroAnt) / Math.abs(lucroAnt) * 100);
                    var causa = '';
                    var dAbast = (Number(ultimo.total_gasto_abastecimentos) || 0) - (Number(anterior.total_gasto_abastecimentos) || 0);
                    var dComissao = (Number(ultimo.total_comissao) || 0) - (Number(anterior.total_comissao) || 0);
                    var dDesp = (Number(ultimo.total_despesas_viagem) || 0) - (Number(anterior.total_despesas_viagem) || 0);
                    var dFrete = (Number(ultimo.total_frete) || 0) - (Number(anterior.total_frete) || 0);
                    if (lucroUlt < lucroAnt) {
                        if (dAbast > 0 && dAbast >= Math.max(dComissao, dDesp)) {
                            var pctAbast = anterior.total_gasto_abastecimentos ? ((dAbast / anterior.total_gasto_abastecimentos) * 100).toFixed(0) : '';
                            causa = ' principalmente por aumento de combustível' + (pctAbast ? ' (+' + pctAbast + '%)' : '') + '.';
                        } else if (dComissao > 0 && dComissao >= Math.max(dAbast, dDesp)) causa = ' principalmente por aumento de comissão.';
                        else if (dDesp > 0 && dDesp >= Math.max(dAbast, dComissao)) causa = ' principalmente por aumento de despesas de viagem.';
                        else if (dFrete < 0) causa = ' com queda de faturamento.';
                    }
                    insights.push((lucroUlt < lucroAnt ? '⚠️ O lucro operacional caiu ' + Math.abs(varPct).toFixed(0) + '% em relação ao mês anterior' + causa : '✅ O lucro operacional subiu ' + varPct.toFixed(0) + '% em relação ao mês anterior.'));
                }
                var margemUlt = (ultimo.total_frete > 0) ? ((ultimo.lucro_operacional / ultimo.total_frete) * 100) : 0;
                if (margemUlt < 0) insights.push('📉 Margem operacional negativa no último mês.');
                (data.veiculos_consumo || []).filter(function(v) { return v.fora_media; }).slice(0, 2).forEach(function(v) { insights.push('⛽ Consumo abaixo da média no veículo ' + (v.placa || v.modelo || '') + '.'); });
                (data.ranking_rotas && data.ranking_rotas.bottom5 || []).filter(function(r) { return r.lucro < 0; }).slice(0, 1).forEach(function(r) { insights.push('📉 Rota com margem negativa: ' + (r.origem_destino || '') + '.'); });
                biRenderPremiumInsights(insights, 'bi-insights-ia-content');
                document.getElementById('bi-insights-ia').style.display = 'block';
            }
        }
    }

    function biTogglePremiumLayout(visao) {
        var isGeral = visao === 'geral';
        var isInd = visao === 'indicadores_desempenho';
        var prem = document.getElementById('bi-premium-geral');
        var visaoExtras = document.getElementById('bi-premium-visao');
        var leg = document.getElementById('bi-charts');
        if (prem) prem.style.display = isGeral ? '' : 'none';
        if (visaoExtras) visaoExtras.style.display = (!isGeral && !isInd) ? '' : 'none';
        if (leg) leg.style.display = isGeral ? 'none' : 'grid';
        document.body.classList.toggle('biq-premium-active', isGeral);
        biUpdateExecSubtitle(visao);
    }

    function biCollectVisaoInsights(data, visao) {
        var insights = [];
        var alertas = [];
        var hist = data.historico_mensal || [];
        if (hist.length < 1) {
            insights.push('ℹ️ Nenhum lançamento no período — ajuste ano, mês ou visão.');
            return { insights: insights, alertas: alertas };
        }
        var ult = hist[hist.length - 1];
        var ant = hist.length > 1 ? hist[hist.length - 2] : null;
        function sum(attr) {
            return hist.reduce(function (acc, row) {
                var n = biCoerceNumber(row[attr]);
                return acc + (isNaN(n) ? 0 : n);
            }, 0);
        }
        if (visao === 'rotas') {
            var ticket = sum('total_rotas') > 0 ? sum('total_frete') / sum('total_rotas') : 0;
            insights.push('✅ Ticket médio de ' + formatMoney(ticket) + ' por rota no período.');
            if (data.ranking_rotas && data.ranking_rotas.top5 && data.ranking_rotas.top5[0]) {
                insights.push('🏆 Rota mais lucrativa: ' + (data.ranking_rotas.top5[0].origem_destino || '—') + '.');
            }
            (data.ranking_rotas && data.ranking_rotas.bottom5 || []).filter(function (r) { return r.lucro < 0; }).slice(0, 2).forEach(function (r) {
                alertas.push('📉 Margem negativa em ' + (r.origem_destino || 'rota') + '.');
            });
        } else if (visao === 'abastecimento') {
            var litros = sum('total_litros') || hist.reduce(function (a, r) { return a + (biCoerceNumber(r.total_litros) || 0); }, 0);
            var km = sum('total_km_rodados');
            if (litros > 0 && km > 0) insights.push('⛽ Consumo médio da frota: ' + (km / litros).toFixed(2) + ' km/L.');
            insights.push('💰 Gasto total com combustível: ' + formatMoney(sum('total_gasto_abastecimentos')) + '.');
            (data.veiculos_consumo || []).filter(function (v) { return v.fora_media; }).slice(0, 2).forEach(function (v) {
                alertas.push('⛽ ' + (v.placa || v.modelo || 'Veículo') + ' com consumo abaixo da média.');
            });
        } else if (visao === 'manutencao') {
            var pc = data.manut_preventiva_corretiva || {};
            var vP = Number(pc.preventiva && pc.preventiva.valor) || 0;
            var vC = Number(pc.corretiva && pc.corretiva.valor) || 0;
            if (vP + vC > 0) {
                var pctP = Math.round((vP / (vP + vC)) * 100);
                insights.push('🔧 ' + pctP + '% do custo é manutenção preventiva.');
            }
            if (data.previsao_proximo_mes > 0) insights.push('📅 Estimativa próximo mês: ' + formatMoney(data.previsao_proximo_mes) + '.');
        } else if (visao === 'despesas_viagem') {
            var porRota = sum('total_rotas') > 0 ? sum('total_despesas_viagem') / sum('total_rotas') : 0;
            insights.push('🛣️ Média de ' + formatMoney(porRota) + ' em despesas por rota.');
            var tipos = data.despesas_viagem_tipos || {};
            var ped = Number(tipos.pedagios) || 0;
            if (ped > 0) insights.push('🚧 Pedágios representam parcela relevante das despesas de viagem.');
        } else if (visao === 'despesas_fixas') {
            insights.push('📋 ' + formatNum(sum('quantidade_despesas_fixas')) + ' lançamentos fixos no período.');
            insights.push('💵 Total pago: ' + formatMoney(sum('total_despesas_fixas')) + '.');
        }
        if (ant && ult) {
            var lucroVar = variacaoPct(ult.lucro_operacional, ant.lucro_operacional);
            if (lucroVar != null && Math.abs(lucroVar) >= 5 && visao !== 'despesas_fixas') {
                insights.push((lucroVar > 0 ? '✅ Lucro ' : '⚠️ Lucro ') + (lucroVar > 0 ? 'subiu ' : 'caiu ') + Math.abs(lucroVar).toFixed(0) + '% vs mês anterior.');
            }
        }
        if (!alertas.length) alertas.push('✅ Nenhum alerta crítico identificado no período.');
        return { insights: insights, alertas: alertas };
    }

    function biRenderVisaoPremiumExtras(data, visao) {
        var wrap = document.getElementById('bi-premium-visao');
        if (!wrap || visao === 'geral' || visao === 'indicadores_desempenho') {
            if (wrap) wrap.style.display = 'none';
            return;
        }
        wrap.style.display = '';
        var pack = biCollectVisaoInsights(data, visao);
        biRenderPremiumInsights(pack.insights, 'bi-visao-insights');
        biRenderPremiumInsights(pack.alertas, 'bi-visao-alertas');
        var meta = document.getElementById('bi-visao-insights-meta');
        if (meta) meta.textContent = document.getElementById('bi-context-line') ? (document.getElementById('bi-context-line').textContent || 'Período') : 'Período';
    }

    function biHandleChartsEmpty(hist, visao) {
        biClearChartEmpties();
        if (hist && hist.length) return;
        if (visao === 'geral') {
            biInjectChartEmpty('chartReceitaCustoLucro', 'fa-chart-line', 'Sem evolução no período', 'Selecione outro ano ou mês nos filtros.');
            biInjectChartEmpty('chartComposicaoCustos', 'fa-chart-pie', 'Sem custos para compor', 'Não há lançamentos no intervalo.');
            var rk = document.getElementById('bi-premium-rank');
            if (rk) rk.innerHTML = biEmptyHtml('fa-trophy', 'Ranking indisponível', 'Cadastre rotas para ver os veículos destaque.');
        } else {
            ['chartRotasTempo', 'chartFreteMensal', 'chartKmMensal'].forEach(function (id) {
                biInjectChartEmpty(id, 'fa-chart-column', 'Sem dados no período', 'Ajuste os filtros para visualizar a evolução.');
            });
        }
    }

    function biInsightTypeFromText(txt) {
        var t = String(txt || '');
        if (/✅|subiu|alta|melhor/i.test(t)) return { cls: 'up', icon: 'fa-arrow-trend-up' };
        if (/⚠️|📉|queda|caiu|negativ/i.test(t)) return { cls: 'risk', icon: 'fa-shield-halved' };
        if (/⛽|combust/i.test(t)) return { cls: 'warn', icon: 'fa-triangle-exclamation' };
        return { cls: 'info', icon: 'fa-circle-info' };
    }

    function biRenderPremiumInsights(insights, targetId) {
        var el = document.getElementById(targetId || 'bi-premium-insights');
        if (!el) return;
        var list = insights || [];
        if (!list.length) {
            el.innerHTML = '<p class="bi-text-muted" style="margin:0;font-size:0.85rem;color:var(--biq-muted)">Nenhum insight no momento.</p>';
            return;
        }
        el.innerHTML = list.map(function (raw) {
            var txt = String(raw).replace(/^[✅⚠️📉⛽]\s*/, '');
            var meta = biInsightTypeFromText(raw);
            var parts = txt.split(/[.:]\s+/, 2);
            var title = parts[0] || txt;
            var desc = parts[1] || '';
            return '<div class="biq-insight biq-insight--' + meta.cls + '"><i class="fas ' + meta.icon + '"></i>'
                + '<div><p class="biq-insight__t">' + biHtmlEsc(title) + '</p>'
                + (desc ? '<p class="biq-insight__d">' + biHtmlEsc(desc) + '</p>' : '') + '</div></div>';
        }).join('');
    }

    function biRenderPremiumMetas(data) {
        var el = document.getElementById('bi-premium-metas');
        if (!el) return;
        var hist = data.historico_mensal || [];
        function sum(attr) {
            return hist.reduce(function (acc, row) {
                var n = biCoerceNumber(row[attr]);
                return acc + (isNaN(n) ? 0 : n);
            }, 0);
        }
        var totalFrete = sum('total_frete');
        var totalKm = sum('total_km_rodados');
        var lucro = sum('lucro_operacional');
        var margem = totalFrete > 0 ? (lucro / totalFrete) * 100 : 0;
        var m = data.metas || null;
        var metaFrete = (m && m.receita > 0) ? m.receita : (totalFrete > 0 ? totalFrete * 1.05 : 1);
        var metaLucro = (m && m.lucro > 0) ? m.lucro : (lucro > 0 ? lucro * 0.95 : Math.max(1, totalFrete * 0.12));
        var metaKm = (m && m.km > 0) ? m.km : (totalKm > 0 ? totalKm * 1.06 : 1);
        var metaMargem = (m && m.margem_pct > 0) ? m.margem_pct : 20;
        var metas = [
            { label: 'Receita', real: totalFrete, meta: metaFrete, fmt: function (v) { return formatMoney(v); } },
            { label: 'Lucro operacional', real: lucro, meta: metaLucro, fmt: function (v) { return formatMoney(v); } },
            { label: 'KM rodados', real: totalKm, meta: metaKm, fmt: function (v) { return formatNum(v); } },
            { label: 'Margem operacional', real: margem, meta: metaMargem, fmt: function (v) { return (biCoerceNumber(v) || 0).toFixed(1) + '%'; } }
        ];
        el.innerHTML = metas.map(function (m) {
            var pct = m.meta > 0 ? Math.min(100, Math.round((m.real / m.meta) * 100)) : 0;
            var ok = m.real >= m.meta;
            return '<div class="biq-meta"><div class="biq-meta__row"><span>' + m.label + '</span>'
                + '<span><b>' + m.fmt(m.real) + '</b> / ' + m.fmt(m.meta) + ' · <span style="color:' + (ok ? 'var(--biq-success)' : 'var(--biq-muted)') + '">' + pct + '%</span></span></div>'
                + '<div class="biq-bar"><span style="width:' + pct + '%' + (ok ? ';background:linear-gradient(90deg,var(--biq-success),#16a34a)' : '') + '"></span></div></div>';
        }).join('');
        var metaLbl = document.getElementById('bi-premium-metas-meta');
        if (metaLbl) metaLbl.textContent = document.getElementById('bi-context-line') ? (document.getElementById('bi-context-line').textContent || 'Período') : 'Período';
    }

    function biCollectGeralInsights(data) {
        var insights = [];
        if (!data.historico_mensal || data.historico_mensal.length < 2) return insights;
        var h = data.historico_mensal;
        var ultimo = h[h.length - 1];
        var anterior = h[h.length - 2];
        var lucroAnt = anterior.lucro_operacional || 0;
        var lucroUlt = ultimo.lucro_operacional || 0;
        if (lucroAnt !== 0 && lucroUlt !== lucroAnt) {
            var varPct = ((lucroUlt - lucroAnt) / Math.abs(lucroAnt) * 100);
            var causa = '';
            var dAbast = (Number(ultimo.total_gasto_abastecimentos) || 0) - (Number(anterior.total_gasto_abastecimentos) || 0);
            var dComissao = (Number(ultimo.total_comissao) || 0) - (Number(anterior.total_comissao) || 0);
            var dDesp = (Number(ultimo.total_despesas_viagem) || 0) - (Number(anterior.total_despesas_viagem) || 0);
            var dFrete = (Number(ultimo.total_frete) || 0) - (Number(anterior.total_frete) || 0);
            if (lucroUlt < lucroAnt) {
                if (dAbast > 0 && dAbast >= Math.max(dComissao, dDesp)) {
                    var pctAbast = anterior.total_gasto_abastecimentos ? ((dAbast / anterior.total_gasto_abastecimentos) * 100).toFixed(0) : '';
                    causa = ': aumento de combustível' + (pctAbast ? ' (+' + pctAbast + '%)' : '');
                } else if (dComissao > 0 && dComissao >= Math.max(dAbast, dDesp)) causa = ': aumento de comissão';
                else if (dDesp > 0 && dDesp >= Math.max(dAbast, dComissao)) causa = ': aumento de despesas de viagem';
                else if (dFrete < 0) causa = ': queda de faturamento';
            }
            insights.push((lucroUlt < lucroAnt ? '⚠️ Lucro operacional caiu ' + Math.abs(varPct).toFixed(0) + '% vs mês anterior' + causa : '✅ Lucro operacional subiu ' + varPct.toFixed(0) + '% vs mês anterior'));
        }
        var margemUlt = (ultimo.total_frete > 0) ? ((ultimo.lucro_operacional / ultimo.total_frete) * 100) : 0;
        if (margemUlt < 0) insights.push('📉 Margem operacional negativa no último mês do período');
        var gastoAbast = Number(ultimo.total_gasto_abastecimentos) || 0;
        var custoOp = gastoAbast + (Number(ultimo.total_manutencoes) || 0) + (Number(ultimo.total_despesas_viagem) || 0) + (Number(ultimo.total_despesas_fixas) || 0) + (Number(ultimo.total_comissao) || 0);
        if (custoOp > 0 && gastoAbast / custoOp > 0.35) {
            insights.push('⛽ Combustível representa ' + Math.round((gastoAbast / custoOp) * 100) + '% dos custos operacionais');
        }
        (data.veiculos_consumo || []).filter(function (v) { return v.fora_media; }).slice(0, 2).forEach(function (v) {
            insights.push('⛽ Consumo abaixo da média: ' + (v.placa || v.modelo || 'veículo'));
        });
        (data.ranking_rotas && data.ranking_rotas.bottom5 || []).filter(function (r) { return r.lucro < 0; }).slice(0, 1).forEach(function (r) {
            insights.push('📉 Rota com margem negativa: ' + (r.origem_destino || 'rota'));
        });
        return insights;
    }

    function biRenderPremiumChartsGeral(data) {
        var hist = data.historico_mensal || [];
        if (hist.length > 120) hist = hist.slice(-120);
        var labels = hist.map(function (x) { return x.mes_nome || x.mes_ano; });
        var mesAnos = hist.map(function (x) { return x.mes_ano || ''; });
        var receitas = hist.map(function (x) { return x.total_frete || 0; });
        var custos = hist.map(function (x) {
            return (Number(x.total_comissao) || 0) + (Number(x.total_gasto_abastecimentos) || 0)
                + (Number(x.total_manutencoes) || 0) + (Number(x.total_despesas_viagem) || 0) + (Number(x.total_despesas_fixas) || 0);
        });
        var lucros = hist.map(function (x) { return x.lucro_operacional || 0; });

        var ctxMain = document.getElementById('chartReceitaCustoLucro');
        if (ctxMain) {
            charts.chartReceitaCustoLucro = new Chart(ctxMain, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        { label: 'Receita', data: receitas, borderColor: '#3B82F6', backgroundColor: 'rgba(59,130,246,0.12)', fill: true, tension: 0.4, borderWidth: 2, pointRadius: 0 },
                        { label: 'Custo', data: custos, borderColor: '#F59E0B', backgroundColor: 'transparent', fill: false, tension: 0.4, borderWidth: 2, pointRadius: 0 },
                        { label: 'Lucro', data: lucros, borderColor: '#22C55E', backgroundColor: 'transparent', fill: false, tension: 0.4, borderWidth: 2, pointRadius: 0 }
                    ]
                },
                options: biChartPremiumLineOptions()
            });
            biBindDrill(charts.chartReceitaCustoLucro, { mesAnos: mesAnos, labels: labels, serie: 'rotas' });
        }

        function sumAttr(attr) {
            return hist.reduce(function (acc, row) {
                var n = biCoerceNumber(row[attr]);
                return acc + (isNaN(n) ? 0 : n);
            }, 0);
        }
        var vAbast = sumAttr('total_gasto_abastecimentos');
        var vManut = sumAttr('total_manutencoes');
        var vDesp = sumAttr('total_despesas_viagem');
        var vFixas = sumAttr('total_despesas_fixas');
        var vCom = sumAttr('total_comissao');
        var donutLabels = ['Combustível', 'Manutenção', 'Desp. viagem', 'Desp. fixas', 'Comissão'];
        var donutData = [vAbast, vManut, vDesp, vFixas, vCom];
        var donutSum = donutData.reduce(function (a, b) { return a + b; }, 0);
        var ctxDonut = document.getElementById('chartComposicaoCustos');
        if (ctxDonut) {
            var donutWrap = ctxDonut.closest('.biq-chart-wrap');
            if (donutWrap) donutWrap.querySelectorAll('.biq-empty').forEach(function (n) { n.remove(); });
            ctxDonut.style.display = '';
            if (donutSum > 0) {
                charts.chartComposicaoCustos = new Chart(ctxDonut, {
                    type: 'doughnut',
                    data: {
                        labels: donutLabels,
                        datasets: [{ data: donutData, backgroundColor: ['#3B82F6', '#8B5CF6', '#F59E0B', '#06B6D4', '#22C55E'], borderWidth: 0, hoverBorderWidth: 6 }]
                    },
                    options: biChartPremiumDonutOptions()
                });
            } else if (donutWrap) {
                ctxDonut.style.display = 'none';
                var de = document.createElement('div');
                de.innerHTML = biEmptyHtml('fa-chart-pie', 'Sem custos no período', 'Lançamentos de combustível, manutenção e despesas aparecerão aqui.');
                donutWrap.appendChild(de.firstChild);
            }
        }

        biRenderTopVeiculosRank(data.veiculos_top || [], 'bi-premium-rank');
        biRenderPremiumInsights(biCollectGeralInsights(data));
        biRenderPremiumMetas(data);

        var metaMain = document.getElementById('bi-premium-main-meta');
        if (metaMain) metaMain.textContent = labels.length ? ('Últimos ' + labels.length + ' meses') : 'Período';
        var tagDonut = document.getElementById('bi-premium-donut-tag');
        if (tagDonut) tagDonut.textContent = document.getElementById('bi-context-line') ? (document.getElementById('bi-context-line').textContent.split('·').pop() || '').trim() || 'Período' : 'Período';

        biFetchMotoristaLucroChart();

        var moreInner = document.getElementById('bi-premium-more-inner');
        var chartsLegacy = document.getElementById('bi-charts');
        if (moreInner && chartsLegacy && chartsLegacy.children.length) {
            while (chartsLegacy.firstChild) {
                var node = chartsLegacy.firstChild;
                if (node.classList && node.classList.contains('bi-drill-hint')) {
                    chartsLegacy.removeChild(node);
                    continue;
                }
                moreInner.appendChild(node);
            }
        }
    }

    function biFetchMotoristaLucroChart() {
        if (biLastVisao && biLastVisao !== 'geral') return;
        var canvas = document.getElementById('chartMotoristaLucro');
        if (!canvas) return;
        if (biMotoristaFetchAbort) {
            try { biMotoristaFetchAbort.abort(); } catch (e) { /* ignore */ }
        }
        biMotoristaFetchAbort = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var fetchOpts = { credentials: 'same-origin' };
        if (biMotoristaFetchAbort) fetchOpts.signal = biMotoristaFetchAbort.signal;
        fetch(baseUrl + '/motorist_efficiency_analytics.php', fetchOpts)
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (json) {
                if (biLastVisao !== 'geral') return;
                if (!json || !json.labels || !json.datasets) return;
                var labels = json.labels.slice(0, 8).map(biSanitizeChartLabel);
                var lucro = (json.datasets.lucro || []).slice(0, 8);
                if (!labels.length) {
                    var motWrap = canvas.closest('.biq-chart-wrap');
                    if (motWrap) {
                        motWrap.querySelectorAll('.biq-empty').forEach(function (n) { n.remove(); });
                        canvas.style.display = 'none';
                        var me = document.createElement('div');
                        me.innerHTML = biEmptyHtml('fa-users', 'Sem dados de motoristas', 'Lucro por motorista nos últimos 30 dias.');
                        motWrap.appendChild(me.firstChild);
                    }
                    return;
                }
                if (canvas) canvas.style.display = '';
                if (charts.chartMotoristaLucro) { try { charts.chartMotoristaLucro.destroy(); } catch (e) {} }
                charts.chartMotoristaLucro = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{ label: 'Lucro (R$)', data: lucro, backgroundColor: '#3B82F6', borderWidth: 0 }]
                    },
                    options: biChartOptions()
                });
                biVivifyCharts();
            })
            .catch(function () { /* silencioso — abort ou rede */ });
    }

    function loadDataDebounced() {
        if (biLoadDataDebounce) clearTimeout(biLoadDataDebounce);
        biLoadDataDebounce = setTimeout(loadData, 350);
    }

    function renderCharts(data, visao) {
        visao = visao || 'geral';
        biTogglePremiumLayout(visao);
        destroyCharts();
        biClearChartEmpties();
        var hist = data.historico_mensal || [];
        biHandleChartsEmpty(hist, visao);
        if (!hist.length && visao !== 'geral') {
            biVivifyCharts();
            biEnsureChartsResizeObserver();
            return;
        }
        if (visao === 'geral') {
            var prem = document.getElementById('bi-premium-geral');
            if (prem) { prem.style.display = ''; void prem.offsetWidth; }
            biPrepAllBiChartCanvases();
            if (!hist.length) {
                biRenderPremiumInsights(['ℹ️ Nenhum lançamento no período — ajuste os filtros ou cadastre rotas.']);
                biRenderPremiumMetas(data);
                biVivifyCharts();
                biEnsureChartsResizeObserver();
                return;
            }
            biRenderPremiumChartsGeral(data);
            biVivifyCharts();
            biEnsureChartsResizeObserver();
            biScheduleChartLayoutStabilize();
            return;
        }
        var moreInner = document.getElementById('bi-premium-more-inner');
        var chartsLegacy = document.getElementById('bi-charts');
        if (moreInner && chartsLegacy && moreInner.children.length && !chartsLegacy.children.length) {
            while (moreInner.firstChild) {
                chartsLegacy.appendChild(moreInner.firstChild);
            }
        }
        var _biChartsWrap = document.getElementById('bi-charts');
        if (_biChartsWrap) {
            _biChartsWrap.style.display = 'grid';
            void _biChartsWrap.offsetWidth;
        }
        biPrepAllBiChartCanvases();
        var hist = data.historico_mensal || [];
        if (hist.length > 120) hist = hist.slice(-120);
        var labels = hist.map(function(x) { return x.mes_nome || x.mes_ano; });

        var wrapAbast = document.getElementById('wrapChartAbastTempo');
        if (wrapAbast) wrapAbast.style.display = (visao === 'geral') ? 'block' : 'none';
        var wrapTopVeiculos = document.getElementById('wrapChartTopVeiculos');
        if (wrapTopVeiculos) {
            wrapTopVeiculos.style.display = (visao === 'despesas_viagem' || visao === 'despesas_fixas') ? 'none' : '';
        }
        // Reset do Top veículos: por padrão mostra o gráfico (canvas) e esconde o ranking HTML.
        (function () {
            var cv = document.getElementById('chartTopVeiculos');
            var rk = document.getElementById('biTopVeiculosRank');
            if (cv) cv.style.display = '';
            if (rk) { rk.style.display = 'none'; rk.innerHTML = ''; }
        })();

        if (visao === 'manutencao') {
            document.getElementById('tituloChartRotasTempo').textContent = 'Custo de manutenção por mês';
            if (document.getElementById('chartRotasTempo')) {
                charts.chartRotasTempo = new Chart(document.getElementById('chartRotasTempo'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            { label: 'Custo (R$)', data: hist.map(function(x) { return x.total_manutencoes || 0; }), backgroundColor: 'rgba(239,68,68,0.6)', borderColor: '#ef4444', borderWidth: 1 },
                            { label: 'Qtde', data: hist.map(function(x) { return x.quantidade_manutencoes || 0; }), backgroundColor: 'rgba(245,158,11,0.6)', borderColor: '#f59e0b', borderWidth: 1, yAxisID: 'y1' }
                        ]
                    },
                    options: biChartDualOptions()
                });
            }
            document.getElementById('tituloChartFreteMensal').textContent = 'Custo por mês (R$)';
            if (document.getElementById('chartFreteMensal')) {
                charts.chartFreteMensal = new Chart(document.getElementById('chartFreteMensal'), {
                    type: 'line',
                    data: { labels: labels, datasets: [{ label: 'Custo', data: hist.map(function(x) { return x.total_manutencoes || 0; }), borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.1)', fill: true }] },
                    options: biChartOptions()
                });
            }
            document.getElementById('tituloChartKmMensal').textContent = 'Quantidade de manutenções por mês';
            if (document.getElementById('chartKmMensal')) {
                charts.chartKmMensal = new Chart(document.getElementById('chartKmMensal'), {
                    type: 'bar',
                    data: { labels: labels, datasets: [{ label: 'Manutenções', data: hist.map(function(x) { return x.quantidade_manutencoes || 0; }), backgroundColor: 'rgba(245,158,11,0.7)' }] },
                    options: biChartOptions()
                });
            }
            if (charts.chartDespViagemTipos) { charts.chartDespViagemTipos.destroy(); charts.chartDespViagemTipos = null; }
            var veiculosManut = data.veiculos_top_manutencao || [];
            document.getElementById('tituloChartTopVeiculos').textContent = 'Top veículos (custo manutenção)';
            if (document.getElementById('chartTopVeiculos') && veiculosManut.length) {
                charts.chartTopVeiculos = new Chart(document.getElementById('chartTopVeiculos'), {
                    type: 'bar',
                    data: {
                        labels: veiculosManut.map(function(v) { return v.placa || v.modelo || 'N/A'; }),
                        datasets: [
                            { label: 'Custo (R$)', data: veiculosManut.map(function(v) { return v.total_gasto || 0; }), backgroundColor: 'rgba(239,68,68,0.7)' },
                            { label: 'Qtde', data: veiculosManut.map(function(v) { return v.total_manutencoes || 0; }), backgroundColor: 'rgba(245,158,11,0.7)' }
                        ]
                    },
                    options: biChartHorizontalOptions()
                });
            }
            var pc = data.manut_preventiva_corretiva || { preventiva: { valor: 0 }, corretiva: { valor: 0 } };
            var vPrev = Number(pc.preventiva && pc.preventiva.valor) || 0;
            var vCorr = Number(pc.corretiva && pc.corretiva.valor) || 0;
            var extraManut = document.getElementById('bi-manut-extra');
            if (extraManut) extraManut.style.display = '';
            var ctxManut = document.getElementById('chartManutPreventivaCorretiva');
            var manutWrap = document.getElementById('bi-manut-donut-wrap');
            if (ctxManut) {
                if (charts.chartManutPreventivaCorretiva) { try { charts.chartManutPreventivaCorretiva.destroy(); } catch (e) {} charts.chartManutPreventivaCorretiva = null; }
                if (manutWrap) manutWrap.querySelectorAll('.biq-empty').forEach(function (n) { n.remove(); });
                ctxManut.style.display = '';
                if (vPrev > 0 || vCorr > 0) {
                    charts.chartManutPreventivaCorretiva = new Chart(ctxManut, {
                        type: 'doughnut',
                        data: {
                            labels: ['Preventiva', 'Corretiva'],
                            datasets: [{ data: [vPrev, vCorr], backgroundColor: ['#22C55E', '#EF4444'], borderWidth: 0, hoverOffset: 6 }]
                        },
                        options: biChartPremiumDonutOptions()
                    });
                } else if (manutWrap) {
                    ctxManut.style.display = 'none';
                    var me = document.createElement('div');
                    me.innerHTML = biEmptyHtml('fa-chart-pie', 'Sem manutenções no período', 'Preventiva e corretiva aparecerão aqui.');
                    manutWrap.appendChild(me.firstChild);
                }
            }
        } else if (visao === 'despesas_viagem') {
            var extraDv = document.getElementById('bi-desp-viagem-extra');
            if (extraDv) extraDv.style.display = '';
            document.getElementById('tituloChartRotasTempo').textContent = 'Despesas de viagem por mês';
            if (document.getElementById('chartRotasTempo')) {
                charts.chartRotasTempo = new Chart(document.getElementById('chartRotasTempo'), {
                    type: 'bar',
                    data: { labels: labels, datasets: [{ label: 'Desp. viagem (R$)', data: hist.map(function(x) { return x.total_despesas_viagem || 0; }), backgroundColor: 'rgba(59,130,246,0.7)' }] },
                    options: biChartOptions()
                });
            }
            document.getElementById('tituloChartFreteMensal').textContent = 'Evolução (R$)';
            if (document.getElementById('chartFreteMensal')) {
                charts.chartFreteMensal = new Chart(document.getElementById('chartFreteMensal'), {
                    type: 'line',
                    data: { labels: labels, datasets: [{ label: 'Desp. viagem', data: hist.map(function(x) { return x.total_despesas_viagem || 0; }), borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)', fill: true }] },
                    options: biChartOptions()
                });
            }
            document.getElementById('tituloChartKmMensal').textContent = 'Por mês';
            if (document.getElementById('chartKmMensal')) {
                charts.chartKmMensal = new Chart(document.getElementById('chartKmMensal'), {
                    type: 'bar',
                    data: { labels: labels, datasets: [{ label: 'R$', data: hist.map(function(x) { return x.total_despesas_viagem || 0; }), backgroundColor: 'rgba(16,185,129,0.7)' }] },
                    options: biChartOptions()
                });
            }
            document.getElementById('tituloChartTopVeiculos').textContent = 'Despesas de viagem';
            if (document.getElementById('chartTopVeiculos')) { charts.chartTopVeiculos = null; }
            var tipos = data.despesas_viagem_tipos || {};
            var pedagio = Number(tipos.pedagios || 0);
            var alimentacao = Number(tipos.caixinha || 0);
            var hospedagem = Number(tipos.estacionamento || 0) + Number(tipos.lavagem || 0);
            var outros = Number(tipos.descarga || 0) + Number(tipos.borracharia || 0) + Number(tipos.eletrica_mecanica || 0) + Number(tipos.adiantamento || 0) + Number(tipos.outros || 0);
            var labelsPie = ['Pedágio', 'Alimentação', 'Estacion./lavagem', 'Outros'];
            var dadosPie = [pedagio, alimentacao, hospedagem, outros];
            var somaPie = dadosPie.reduce(function (a, b) { return a + (Number(b) || 0); }, 0);
            var ctx = document.getElementById('chartDespViagemTipos');
            var donutWrap = document.getElementById('bi-desp-viagem-donut-wrap');
            if (ctx) {
                if (charts.chartDespViagemTipos) {
                    try { charts.chartDespViagemTipos.destroy(); } catch (ePie) {}
                    charts.chartDespViagemTipos = null;
                }
                if (donutWrap) donutWrap.querySelectorAll('.biq-empty').forEach(function (n) { n.remove(); });
                ctx.style.display = '';
                if (somaPie > 0) {
                    charts.chartDespViagemTipos = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: labelsPie,
                            datasets: [{ data: dadosPie, backgroundColor: ['#3B82F6', '#22C55E', '#F59E0B', '#8B5CF6'], borderWidth: 0, hoverBorderWidth: 6 }]
                        },
                        options: biChartPremiumDonutOptions()
                    });
                } else if (donutWrap) {
                    ctx.style.display = 'none';
                    var de = document.createElement('div');
                    de.innerHTML = biEmptyHtml('fa-chart-pie', 'Sem despesas por tipo', 'Lançamentos de pedágio, alimentação e outros aparecerão aqui.');
                    donutWrap.appendChild(de.firstChild);
                }
            }
        } else if (visao === 'despesas_fixas') {
            document.getElementById('tituloChartRotasTempo').textContent = 'Despesas fixas pagas por mês';
            if (document.getElementById('chartRotasTempo')) {
                charts.chartRotasTempo = new Chart(document.getElementById('chartRotasTempo'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            { label: 'Valor (R$)', data: hist.map(function(x) { return x.total_despesas_fixas || 0; }), backgroundColor: 'rgba(139,92,246,0.7)' },
                            { label: 'Qtde', data: hist.map(function(x) { return x.quantidade_despesas_fixas || 0; }), backgroundColor: 'rgba(236,72,153,0.6)', yAxisID: 'y1' }
                        ]
                    },
                    options: biChartDualOptions()
                });
            }
            document.getElementById('tituloChartFreteMensal').textContent = 'Total pago por mês';
            if (document.getElementById('chartFreteMensal')) {
                charts.chartFreteMensal = new Chart(document.getElementById('chartFreteMensal'), {
                    type: 'line',
                    data: { labels: labels, datasets: [{ label: 'Desp. fixas (R$)', data: hist.map(function(x) { return x.total_despesas_fixas || 0; }), borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,0.1)', fill: true }] },
                    options: biChartOptions()
                });
            }
            document.getElementById('tituloChartKmMensal').textContent = 'Quantidade por mês';
            if (document.getElementById('chartKmMensal')) {
                charts.chartKmMensal = new Chart(document.getElementById('chartKmMensal'), {
                    type: 'bar',
                    data: { labels: labels, datasets: [{ label: 'Qtde', data: hist.map(function(x) { return x.quantidade_despesas_fixas || 0; }), backgroundColor: 'rgba(236,72,153,0.7)' }] },
                    options: biChartOptions()
                });
            }
            document.getElementById('tituloChartTopVeiculos').textContent = 'Despesas fixas';
            if (document.getElementById('chartTopVeiculos')) {
                charts.chartTopVeiculos = null;
            }
        } else if (visao === 'abastecimento') {
            document.getElementById('tituloChartRotasTempo').textContent = 'Abastecimentos e Gasto ao longo do tempo';
            if (document.getElementById('chartRotasTempo')) {
                charts.chartRotasTempo = new Chart(document.getElementById('chartRotasTempo'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            { label: 'Qtde Abast.', data: hist.map(function(x) { return x.total_abastecimentos || 0; }), backgroundColor: 'rgba(79,156,249,0.7)', borderColor: '#4f9cf9', borderWidth: 1 },
                            { label: 'Gasto (R$)', data: hist.map(function(x) { return x.total_gasto_abastecimentos || 0; }), backgroundColor: 'rgba(52,211,153,0.7)', borderColor: '#34d399', borderWidth: 1, yAxisID: 'y1' }
                        ]
                    },
                    options: biChartDualOptions()
                });
            }
            document.getElementById('tituloChartFreteMensal').textContent = 'Gasto com abastecimento por mês';
            if (document.getElementById('chartFreteMensal')) {
                charts.chartFreteMensal = new Chart(document.getElementById('chartFreteMensal'), {
                    type: 'line',
                    data: { labels: labels, datasets: [{ label: 'Gasto (R$)', data: hist.map(function(x) { return x.total_gasto_abastecimentos || 0; }), borderColor: '#4f9cf9', backgroundColor: 'rgba(79,156,249,0.1)', fill: true }] },
                    options: biChartOptions()
                });
            }
            document.getElementById('tituloChartKmMensal').textContent = 'Quantidade de abastecimentos por mês';
            if (document.getElementById('chartKmMensal')) {
                charts.chartKmMensal = new Chart(document.getElementById('chartKmMensal'), {
                    type: 'bar',
                    data: { labels: labels, datasets: [{ label: 'Abastecimentos', data: hist.map(function(x) { return x.total_abastecimentos || 0; }), backgroundColor: 'rgba(245,158,11,0.7)', borderColor: '#f59e0b', borderWidth: 1 }] },
                    options: biChartOptions()
                });
            }
            var veiculosAbast = data.veiculos_top_abastecimento || [];
            document.getElementById('tituloChartTopVeiculos').textContent = 'Top veículos (abastecimento)';
            if (document.getElementById('chartTopVeiculos') && veiculosAbast.length) {
                charts.chartTopVeiculos = new Chart(document.getElementById('chartTopVeiculos'), {
                    type: 'bar',
                    data: {
                        labels: veiculosAbast.map(function(v) { return v.placa || v.modelo || 'N/A'; }),
                        datasets: [
                            { label: 'Abastecimentos', data: veiculosAbast.map(function(v) { return v.total_abastecimentos || 0; }), backgroundColor: 'rgba(79,156,249,0.7)' },
                            { label: 'Gasto (R$)', data: veiculosAbast.map(function(v) { return v.total_gasto || 0; }), backgroundColor: 'rgba(52,211,153,0.7)' }
                        ]
                    },
                    options: biChartHorizontalOptions()
                });
            }
        } else {
            document.getElementById('tituloChartRotasTempo').textContent = 'Rotas e Faturamento ao longo do tempo';
            if (document.getElementById('chartRotasTempo')) {
                charts.chartRotasTempo = new Chart(document.getElementById('chartRotasTempo'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            { label: 'Qtde Rotas', data: hist.map(function(x) { return x.total_rotas || 0; }), backgroundColor: 'rgba(79,156,249,0.7)', borderColor: '#4f9cf9', borderWidth: 1 },
                            { label: 'Frete (R$)', data: hist.map(function(x) { return x.total_frete || 0; }), backgroundColor: 'rgba(52,211,153,0.7)', borderColor: '#34d399', borderWidth: 1, yAxisID: 'y1' }
                        ]
                    },
                    options: biChartDualOptions()
                });
            }
            document.getElementById('tituloChartFreteMensal').textContent = 'Faturamento por mês (Frete)';
            if (document.getElementById('chartFreteMensal')) {
                charts.chartFreteMensal = new Chart(document.getElementById('chartFreteMensal'), {
                    type: 'line',
                    data: { labels: labels, datasets: [{ label: 'Frete', data: hist.map(function(x) { return x.total_frete || 0; }), borderColor: '#4f9cf9', backgroundColor: 'rgba(79,156,249,0.1)', fill: true }] },
                    options: biChartOptions()
                });
            }
            if (visao === 'rotas') {
            document.getElementById('tituloChartKmMensal').textContent = 'Lucro por KM por mês';
            if (document.getElementById('chartKmMensal')) {
                charts.chartKmMensal = new Chart(document.getElementById('chartKmMensal'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{ label: 'Lucro/KM (R$)', data: hist.map(function(x) {
                            var km = x.total_km_rodados || 0;
                            var luc = x.lucro_operacional || 0;
                            return km > 0 ? (luc / km) : 0;
                        }), backgroundColor: 'rgba(34,197,94,0.7)', borderColor: '#22c55e', borderWidth: 1 }]
                    },
                    options: biChartOptions()
                });
            }
        } else {
            document.getElementById('tituloChartKmMensal').textContent = 'Km rodados por mês';
            if (document.getElementById('chartKmMensal')) {
                charts.chartKmMensal = new Chart(document.getElementById('chartKmMensal'), {
                    type: 'bar',
                    data: { labels: labels, datasets: [{ label: 'Km', data: hist.map(function(x) { return x.total_km_rodados || 0; }), backgroundColor: 'rgba(245,158,11,0.7)', borderColor: '#f59e0b', borderWidth: 1 }] },
                    options: biChartOptions()
                });
            }
        }
            if (visao === 'geral' && document.getElementById('chartAbastTempo')) {
                charts.chartAbastTempo = new Chart(document.getElementById('chartAbastTempo'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            { label: 'Qtde Abast.', data: hist.map(function(x) { return x.total_abastecimentos || 0; }), backgroundColor: 'rgba(167,139,250,0.7)', borderColor: '#a78bfa', borderWidth: 1 },
                            { label: 'Gasto (R$)', data: hist.map(function(x) { return x.total_gasto_abastecimentos || 0; }), backgroundColor: 'rgba(239,68,68,0.5)', borderColor: '#ef4444', borderWidth: 1, yAxisID: 'y1' }
                        ]
                    },
                    options: biChartDualOptions()
                });
            }
            var veiculos = data.veiculos_top || [];
            document.getElementById('tituloChartTopVeiculos').textContent = 'Top veículos (mais rotas)';
            charts.chartTopVeiculos = null;
            biRenderTopVeiculosRank(veiculos, visao === 'geral' ? 'bi-premium-rank' : 'biTopVeiculosRank');
        }
        (function biAttachDrills() {
            var mesAnos = hist.map(function(x) { return x.mes_ano || ''; });
            function bind(ch, serie) { biBindDrill(ch, { mesAnos: mesAnos, labels: labels, serie: serie }); }
            if (visao === 'manutencao') {
                bind(charts.chartRotasTempo, 'manutencoes');
                bind(charts.chartFreteMensal, 'manutencoes');
                bind(charts.chartKmMensal, 'manutencoes');
            } else if (visao === 'despesas_viagem') {
                bind(charts.chartRotasTempo, 'despesas_viagem');
                bind(charts.chartFreteMensal, 'despesas_viagem');
                bind(charts.chartKmMensal, 'despesas_viagem');
            } else if (visao === 'despesas_fixas') {
                bind(charts.chartRotasTempo, 'despesas_fixas');
                bind(charts.chartFreteMensal, 'despesas_fixas');
                bind(charts.chartKmMensal, 'despesas_fixas');
            } else if (visao === 'abastecimento') {
                bind(charts.chartRotasTempo, 'abastecimentos');
                bind(charts.chartFreteMensal, 'abastecimentos');
                bind(charts.chartKmMensal, 'abastecimentos');
            } else {
                bind(charts.chartRotasTempo, 'rotas');
                bind(charts.chartFreteMensal, 'rotas');
                bind(charts.chartKmMensal, 'rotas');
                if (visao === 'geral' && charts.chartAbastTempo) bind(charts.chartAbastTempo, 'abastecimentos');
            }
        })();
        (function biAttachExtraDrills() {
            if (visao === 'geral' && charts.chartTopVeiculos) {
                var vt = data.veiculos_top || [];
                if (vt.length) {
                    var idsG = vt.map(function(v) { return v.id; });
                    var labsG = vt.map(function(v) { return v.placa || v.modelo || 'N/A'; });
                    biBindDrillTopVeiculo(charts.chartTopVeiculos, 'rotas_veiculo', idsG, labsG);
                }
            } else if (visao === 'abastecimento' && charts.chartTopVeiculos) {
                var va = data.veiculos_top_abastecimento || [];
                if (va.length) {
                    biBindDrillTopVeiculo(charts.chartTopVeiculos, 'abast_veiculo', va.map(function(v) { return v.id; }), va.map(function(v) { return v.placa || v.modelo || 'N/A'; }));
                }
            } else if (visao === 'manutencao' && charts.chartTopVeiculos) {
                var vm = data.veiculos_top_manutencao || [];
                if (vm.length) {
                    biBindDrillTopVeiculo(charts.chartTopVeiculos, 'manut_veiculo', vm.map(function(v) { return v.id; }), vm.map(function(v) { return v.placa || v.modelo || 'N/A'; }));
                }
            }
            // Drill nas pizzas: clicar na fatia abre os lançamentos daquele tipo
            // (vale em qualquer visão em que a pizza apareça, não só na dedicada).
            if (charts.chartDespViagemTipos) biBindDrillPieDesp(charts.chartDespViagemTipos);
            if (charts.chartManutPreventivaCorretiva) biBindDrillManutPie(charts.chartManutPreventivaCorretiva);
            biVivifyCharts();
        })();
        var _biGrid = document.getElementById('bi-charts');
        if (_biGrid) _biGrid.style.display = 'grid';
        biEnsureChartsResizeObserver();
        biScheduleChartLayoutStabilize();
    }

    function formatCurrencyInd(value) {
        if (value == null) return '—';
        return 'R$ ' + Number(value).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function formatNumberInd(value) {
        if (value == null) return '—';
        return Number(value).toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }
    function showTooltipInd(elementId) {
        var element = document.getElementById(elementId);
        if (!element) return;
        var desc = element.getAttribute('data-desc');
        if (!desc) return;
        var existing = element.querySelector('.indicator-help-tooltip');
        if (existing) existing.remove();
        var tooltip = document.createElement('div');
        tooltip.className = 'indicator-help-tooltip';
        tooltip.textContent = desc;
        element.appendChild(tooltip);
        element.classList.add('active');
    }
    function hideTooltipInd(elementId) {
        var element = document.getElementById(elementId);
        if (!element) return;
        var tooltip = element.querySelector('.indicator-help-tooltip');
        if (tooltip && !element.classList.contains('keep-tooltip')) tooltip.remove();
        element.classList.remove('active');
    }
    function toggleTooltipInd(elementId) {
        var element = document.getElementById(elementId);
        if (!element) return;
        var tooltip = element.querySelector('.indicator-help-tooltip');
        if (tooltip) {
            element.classList.remove('keep-tooltip');
            hideTooltipInd(elementId);
        } else {
            element.classList.add('keep-tooltip');
            showTooltipInd(elementId);
        }
    }
    var INDICATORS_DEF = [
        { block: 'financeiro', name: 'Margem operacional (%)', desc: 'Lucro ÷ Faturamento', getValue: function(d) { var f = d.total_frete || 0; var l = d.lucro_operacional || 0; return f > 0 ? (l / f) * 100 : 0; }, format: function(v) { return v.toFixed(1) + '%'; }, type: 'percent', showVariation: true },
        { block: 'financeiro', name: 'Lucro operacional', desc: 'Receitas menos comissão, abast. e despesas de viagem', getValue: function(d) { return d.lucro_operacional; }, format: function(v) { return formatCurrencyInd(v); }, type: 'currency', showVariation: true },
        { block: 'financeiro', name: 'Lucro por KM', desc: 'Lucro operacional ÷ KM rodados', getValue: function(d) { var km = d.total_km_rodados || 0; return km > 0 ? (d.lucro_operacional || 0) / km : 0; }, format: function(v) { return formatCurrencyInd(v); }, type: 'currency', showVariation: true },
        { block: 'financeiro', name: 'Ticket médio por rota', desc: 'Faturamento ÷ quantidade de rotas', getValue: function(d) { var r = d.total_rotas || 0; var f = d.total_frete || 0; return r > 0 ? f / r : 0; }, format: function(v) { return formatCurrencyInd(v); }, type: 'currency', showVariation: true },
        { block: 'financeiro', name: 'Crescimento de receita', desc: 'Variação % do faturamento em relação ao mês anterior', getValue: function(d, index, allData) { if (index === 0) return null; var prev = allData[index - 1].total_frete || 0; var cur = d.total_frete || 0; if (prev === 0) return cur > 0 ? 100 : 0; return ((cur - prev) / prev) * 100; }, format: function(v) { return v === null ? '-' : v.toFixed(2) + '%'; }, type: 'percent', showVariation: false },
        { block: 'operacional', name: 'Veículos ativos', desc: 'Veículos que realizaram rotas no período', getValue: function(d) { return d.quantidade_veiculos_ativos; }, format: function(v) { return formatNumberInd(v); }, type: 'number', showVariation: true },
        { block: 'operacional', name: 'Rotas realizadas', desc: 'Total de rotas no período', getValue: function(d) { return d.total_rotas; }, format: function(v) { return formatNumberInd(v); }, type: 'number', showVariation: true },
        { block: 'operacional', name: 'KM rodados', desc: 'Quilômetros rodados no período', getValue: function(d) { return d.total_km_rodados; }, format: function(v) { return formatNumberInd(v) + ' km'; }, type: 'number', showVariation: true },
        { block: 'operacional', name: 'KM por veículo', desc: 'Média de KM por veículo ativo', getValue: function(d) { var v = d.quantidade_veiculos_ativos || 0; return v > 0 ? (d.total_km_rodados || 0) / v : 0; }, format: function(v) { return formatNumberInd(v) + ' km'; }, type: 'number', showVariation: true },
        { block: 'operacional', name: 'Rotas por veículo', desc: 'Média de rotas por veículo ativo', getValue: function(d) { var v = d.quantidade_veiculos_ativos || 0; return v > 0 ? (d.total_rotas || 0) / v : 0; }, format: function(v) { return formatNumberInd(v); }, type: 'number', showVariation: true },
        { block: 'operacional', name: 'Utilização da frota (%)', desc: 'Aproximação: veículos ativos em relação ao total (baseado em rotas)', getValue: function(d) { return d.quantidade_veiculos_ativos; }, format: function(v) { return formatNumberInd(v) + ' veíc.'; }, type: 'number', showVariation: true },
        { block: 'custos', name: 'Custo por KM', desc: '(Abast + Manut + Desp viagem) ÷ KM', getValue: function(d) { var km = d.total_km_rodados || 0; if (km <= 0) return 0; var c = (d.total_gasto_abastecimentos || 0) + (d.total_manutencoes || 0) + (d.total_despesas_viagem || 0); return c / km; }, format: function(v) { return formatCurrencyInd(v); }, type: 'currency', showVariation: true },
        { block: 'custos', name: 'Combustível por KM', desc: 'Gasto com abastecimento ÷ KM', getValue: function(d) { var km = d.total_km_rodados || 0; return km > 0 ? (d.total_gasto_abastecimentos || 0) / km : 0; }, format: function(v) { return formatCurrencyInd(v); }, type: 'currency', showVariation: true },
        { block: 'custos', name: 'Manutenção por KM', desc: 'Custo de manutenção ÷ KM', getValue: function(d) { var km = d.total_km_rodados || 0; return km > 0 ? (d.total_manutencoes || 0) / km : 0; }, format: function(v) { return formatCurrencyInd(v); }, type: 'currency', showVariation: true },
        { block: 'custos', name: 'Despesas de viagem por KM', desc: 'Despesas de viagem ÷ KM', getValue: function(d) { var km = d.total_km_rodados || 0; return km > 0 ? (d.total_despesas_viagem || 0) / km : 0; }, format: function(v) { return formatCurrencyInd(v); }, type: 'currency', showVariation: true },
        { block: 'custos', name: 'Gasto abastecimentos', desc: 'Total gasto com combustível (incl. ARLA)', getValue: function(d) { return d.total_gasto_abastecimentos; }, format: function(v) { return formatCurrencyInd(v); }, type: 'currency', showVariation: true },
        { block: 'custos', name: 'Despesas de viagem (total)', desc: 'Total de despesas de viagem', getValue: function(d) { return d.total_despesas_viagem; }, format: function(v) { return formatCurrencyInd(v); }, type: 'currency', showVariation: true }
    ];
    var BLOCK_LABELS = { financeiro: 'Financeiro', operacional: 'Operacional', custos: 'Custos' };
    function buildIndicatorsTable(data) {
        if (!data || data.length === 0) return;
        var table = document.getElementById('indicatorsTable');
        var thead = table && table.querySelector('thead');
        var tbody = document.getElementById('indicatorsTableBody');
        if (!table || !thead || !tbody) return;
        tbody.innerHTML = '';
        var headerRow = thead.querySelector('tr');
        if (headerRow) {
            var firstHeader = headerRow.querySelector('th:first-child');
            headerRow.innerHTML = '';
            if (firstHeader) headerRow.appendChild(firstHeader);
            else {
                var th = document.createElement('th');
                th.style.cssText = 'position: sticky; left: 0; background: var(--bg-tertiary); z-index: 10; padding: 12px; text-align: left; border: 1px solid var(--border-color); border-bottom: 2px solid var(--border-color); font-weight: 600; min-width: 150px;';
                th.textContent = 'Indicador';
                headerRow.appendChild(th);
            }
        }
        data.forEach(function(month) {
            var th = document.createElement('th');
            th.style.cssText = 'padding: 12px 8px; text-align: center; border: 1px solid var(--border-color); border-bottom: 2px solid var(--border-color); font-weight: 600; min-width: 120px; background: var(--bg-tertiary);';
            th.textContent = month.mes_nome || month.mes_ano || '';
            if (headerRow) headerRow.appendChild(th);
        });
        var lastBlock = '';
        INDICATORS_DEF.forEach(function(ind) {
            var block = ind.block || '';
            if (block && block !== lastBlock) {
                lastBlock = block;
                var sepRow = document.createElement('tr');
                var sepCell = document.createElement('td');
                sepCell.colSpan = 1 + data.length;
                sepCell.style.cssText = 'background: var(--bg-tertiary); font-weight: 700; padding: 10px 12px; border: 1px solid var(--border-color); color: var(--text-primary);';
                sepCell.textContent = BLOCK_LABELS[block] || block;
                sepRow.appendChild(sepCell);
                tbody.appendChild(sepRow);
            }
            var row = document.createElement('tr');
            var helpId = 'help-' + ind.name.replace(/\s+/g, '-').toLowerCase() + '-' + Math.random().toString(36).substr(2, 9);
            var escapedDesc = (ind.desc || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
            var nameCell = document.createElement('td');
            nameCell.innerHTML = '<div style="display: flex; align-items: center; gap: 2px; flex-wrap: nowrap; position: relative;">' +
                '<span class="indicator-name" style="flex: 1; min-width: 0;">' + ind.name + '</span>' +
                '<span class="indicator-help" id="' + helpId + '" data-desc="' + escapedDesc + '" style="cursor: help; flex-shrink: 0; position: relative;" onmouseenter="window.showTooltipInd(\'' + helpId + '\')" onmouseleave="window.hideTooltipInd(\'' + helpId + '\')" onclick="event.stopPropagation(); window.toggleTooltipInd(\'' + helpId + '\'); return false;">(?)</span></div>';
            row.appendChild(nameCell);
            data.forEach(function(monthData, index) {
                var value = ind.getValue(monthData, index, data);
                var formattedValue = value === null ? '-' : ind.format(value);
                var variationHtml = '';
                if (index > 0 && ind.showVariation && value !== null && typeof value === 'number') {
                    var previousValue = ind.getValue(data[index - 1], index - 1, data);
                    if (previousValue !== null && previousValue !== 0 && typeof previousValue === 'number') {
                        var variation = ((value - previousValue) / Math.abs(previousValue)) * 100;
                        var variationClass = variation > 0 ? 'positive' : variation < 0 ? 'negative' : 'neutral';
                        var arrow = variation > 0 ? '↑' : variation < 0 ? '↓' : '';
                        variationHtml = '<span class="variation ' + variationClass + '">' + Math.abs(variation).toFixed(2) + '%' + arrow + '</span>';
                    } else if (previousValue === 0 && value > 0) { variationHtml = '<span class="variation positive">100%↑</span>'; }
                    else if (previousValue > 0 && value === 0) { variationHtml = '<span class="variation negative">100%↓</span>'; }
                }
                if (!ind.showVariation && ind.type === 'percent' && value !== null && typeof value === 'number') {
                    var vc = value > 0 ? 'positive' : value < 0 ? 'negative' : 'neutral';
                    var ar = value > 0 ? '↑' : value < 0 ? '↓' : '';
                    if (value !== 0) variationHtml = '<span class="variation ' + vc + '">' + ar + '</span>';
                }
                var cell = document.createElement('td');
                cell.innerHTML = '<div class="cell-value"><span class="value-number">' + formattedValue + '</span>' + variationHtml + '</div>';
                if ((ind.name === 'Lucro operacional' || ind.name === 'Lucro Operacional') && value < 0) {
                    var span = cell.querySelector('.value-number');
                    if (span) span.style.color = '#f44336';
                }
                row.appendChild(cell);
            });
            tbody.appendChild(row);
        });
        var alertasEl = document.getElementById('bi-indicadores-alertas');
        if (alertasEl && data.length > 0) {
            var ult = data[data.length - 1];
            var alertas = [];
            var margem = (ult.total_frete > 0) ? ((ult.lucro_operacional || 0) / ult.total_frete) * 100 : 0;
            if (margem < 0) alertas.push('Margem operacional negativa no último mês.');
            if (data.length >= 2) {
                var ant = data[data.length - 2];
                var freteAnt = ant.total_frete || 0;
                var freteUlt = ult.total_frete || 0;
                if (freteAnt > 0 && freteUlt < freteAnt) {
                    var queda = ((freteAnt - freteUlt) / freteAnt * 100).toFixed(0);
                    alertas.push('Queda de faturamento de ' + queda + '% em relação ao mês anterior.');
                }
            }
            var custoKm = (ult.total_km_rodados > 0) ? ((ult.total_gasto_abastecimentos || 0) + (ult.total_manutencoes || 0) + (ult.total_despesas_viagem || 0)) / ult.total_km_rodados : 0;
            var mediaCustoKm = 0;
            if (data.length > 0) {
                var soma = 0, somaKm = 0;
                data.forEach(function(m) {
                    soma += (m.total_gasto_abastecimentos || 0) + (m.total_manutencoes || 0) + (m.total_despesas_viagem || 0);
                    somaKm += m.total_km_rodados || 0;
                });
                mediaCustoKm = somaKm > 0 ? soma / somaKm : 0;
            }
            if (mediaCustoKm > 0 && custoKm > mediaCustoKm * 1.2) alertas.push('Custo por KM no último mês acima da média do período.');
            if (alertas.length > 0) {
                alertasEl.innerHTML = '<h4 style="margin:0 0 0.5rem 0;">Alertas</h4><ul style="margin:0; padding-left:1.2rem;">' + alertas.map(function(a) { return '<li>' + a + '</li>'; }).join('') + '</ul>';
                alertasEl.style.display = 'block';
            } else {
                alertasEl.innerHTML = '<h4 style="margin:0 0 0.5rem 0;">Alertas</h4><p class="bi-text-muted">Nenhum alerta no período.</p>';
                alertasEl.style.display = 'block';
            }
        }
    }
    function loadPerformanceIndicators() {
        var loadingEl = document.getElementById('indicatorsLoading');
        var containerEl = document.getElementById('indicatorsTableContainer');
        if (loadingEl) loadingEl.style.display = 'block';
        if (containerEl) containerEl.style.display = 'none';
        var a = document.getElementById('filtro-ano');
        var m = document.getElementById('filtro-mes');
        var ano = (a && a.value) ? String(a.value).trim() : '';
        var mes = (m && m.value) ? String(m.value).trim() : '';
        var url = baseUrl + '/performance_indicators.php?visao=geral' + (ano ? '&ano=' + encodeURIComponent(ano) : '') + (mes ? '&mes=' + encodeURIComponent(mes) : '');
        fetch(url, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(json) {
                if (json.success && json.data && json.data.historico_mensal) {
                    buildIndicatorsTable(json.data.historico_mensal);
                    if (loadingEl) loadingEl.style.display = 'none';
                    if (containerEl) containerEl.style.display = 'block';
                } else {
                    if (loadingEl) { loadingEl.innerHTML = '<div style="text-align: center; padding: 40px; color: #dc3545;"><i class="fas fa-exclamation-triangle" style="font-size: 2rem;"></i><p style="margin-top: 15px;">Erro ao carregar indicadores.</p></div>'; loadingEl.style.display = 'block'; }
                    if (containerEl) containerEl.style.display = 'none';
                }
            })
            .catch(function() {
                if (loadingEl) { loadingEl.innerHTML = '<div style="text-align: center; padding: 40px; color: #dc3545;"><i class="fas fa-exclamation-triangle" style="font-size: 2rem;"></i><p style="margin-top: 15px;">Erro ao carregar indicadores.</p></div>'; loadingEl.style.display = 'block'; }
                if (containerEl) containerEl.style.display = 'none';
            });
    }
    function exportIndicatorsToExcel() {
        var table = document.getElementById('indicatorsTable');
        if (!table) { alert('Tabela não encontrada. Aguarde o carregamento dos indicadores.'); return; }
        var data = [];
        var headers = ['Indicador'];
        table.querySelectorAll('thead th').forEach(function(th, index) { if (index > 0) headers.push(th.textContent.trim()); });
        data.push(headers);
        table.querySelectorAll('tbody tr').forEach(function(tr) {
            var row = [];
            tr.querySelectorAll('td').forEach(function(cell, index) {
                if (index === 0) {
                    var name = cell.querySelector('.indicator-name');
                    row.push(name ? name.textContent.trim().replace(/\s*\(\?\)\s*$/, '') : cell.textContent.trim().replace(/\s*\(\?\)\s*$/, ''));
                } else {
                    var cellValue = cell.querySelector('.cell-value');
                    if (cellValue) {
                        var parts = [];
                        var valueNumber = cellValue.querySelector('.value-number');
                        var variation = cellValue.querySelector('.variation');
                        if (valueNumber) parts.push(valueNumber.textContent.trim());
                        if (variation) parts.push(variation.textContent.trim());
                        row.push(parts.join(' ') || cell.textContent.trim());
                    } else row.push(cell.textContent.trim());
                }
            });
            data.push(row);
        });
        var csv = data.map(function(row) {
            return row.map(function(cell) {
                var s = String(cell || '').trim();
                if (s.indexOf(',') !== -1 || s.indexOf(';') !== -1 || s.indexOf('"') !== -1 || s.indexOf('\n') !== -1 || s.indexOf('\r') !== -1) return '"' + s.replace(/"/g, '""') + '"';
                return s;
            }).join(';');
        }).join('\r\n');
        var BOM = '\uFEFF';
        var blob = new Blob([BOM + csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'indicadores_desempenho_' + new Date().toISOString().split('T')[0] + '.csv';
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
    }
    function exportTableToCsv() {
        var table = document.getElementById('bi-tabela');
        if (!table) return;
        var data = [];
        table.querySelectorAll('thead tr').forEach(function(tr) {
            var row = [];
            tr.querySelectorAll('th').forEach(function(th) { row.push(th.textContent.trim()); });
            data.push(row);
        });
        table.querySelectorAll('tbody tr').forEach(function(tr) {
            var row = [];
            tr.querySelectorAll('td').forEach(function(td) { row.push(td.textContent.trim()); });
            data.push(row);
        });
        var csv = data.map(function(row) {
            return row.map(function(cell) {
                var s = String(cell || '').trim();
                if (s.indexOf(',') !== -1 || s.indexOf(';') !== -1 || s.indexOf('"') !== -1 || s.indexOf('\n') !== -1) return '"' + s.replace(/"/g, '""') + '"';
                return s;
            }).join(';');
        }).join('\r\n');
        var BOM = '\uFEFF';
        var blob = new Blob([BOM + csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'bi_panorama_' + new Date().toISOString().split('T')[0] + '.csv';
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
    }
    function exportChartToPng() {
        var canvas = document.querySelector('#bi-charts canvas');
        if (!canvas || !canvas.id) return;
        try {
            var url = canvas.toDataURL('image/png');
            var link = document.createElement('a');
            link.href = url;
            link.download = 'bi_grafico_' + (canvas.id || 'chart') + '_' + new Date().toISOString().split('T')[0] + '.png';
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        } catch (e) { alert('Não foi possível exportar o gráfico.'); }
    }
    window.showTooltipInd = showTooltipInd;
    window.hideTooltipInd = hideTooltipInd;
    window.toggleTooltipInd = toggleTooltipInd;

    function renderTable(data, visao) {
        visao = visao || 'geral';
        var hist = data.historico_mensal || [];
        var thead = document.querySelector('#bi-tabela thead tr');
        var tbody = document.querySelector('#bi-tabela tbody');
        var titulo = document.getElementById('bi-table-titulo');
        function sum(attr) {
            return hist.reduce(function(acc, row) {
                var n = biCoerceNumber(row[attr]);
                return acc + (isNaN(n) ? 0 : n);
            }, 0);
        }
        var semDados = '<tr><td colspan="20" style="padding:0;border:none;background:transparent;"><div class="biq-empty" style="min-height:140px;margin:8px 0;"><i class="fas fa-table" aria-hidden="true"></i><p class="biq-empty__t">Nenhum dado no período</p><p class="biq-empty__d">Altere os filtros acima ou registre movimentações nesta visão.</p></div></td></tr>';

        if (visao === 'abastecimento') {
            titulo.textContent = 'Panorama mensal – Abastecimento';
            thead.innerHTML = '<th>Mês</th><th>Qtde Abast.</th><th>Gasto (R$)</th>';
            if (hist.length === 0) tbody.innerHTML = semDados;
            else tbody.innerHTML = hist.map(function(row) {
                return '<tr><td>' + (row.mes_nome || row.mes_ano) + '</td><td>' + formatNum(row.total_abastecimentos) + '</td><td>' + formatMoney(row.total_gasto_abastecimentos) + '</td></tr>';
            }).join('') + '<tr class="bi-table-total"><td><strong>Total</strong></td><td>' + formatNum(sum('total_abastecimentos')) + '</td><td>' + formatMoney(sum('total_gasto_abastecimentos')) + '</td></tr>';
        } else if (visao === 'rotas') {
            titulo.textContent = 'Panorama mensal – Rotas';
            thead.innerHTML = '<th>Mês</th><th>Rotas</th><th>Km</th><th>Frete (R$)</th><th>Comissão (R$)</th><th>Lucro oper.</th>';
            if (hist.length === 0) tbody.innerHTML = semDados;
            else tbody.innerHTML = hist.map(function(row) {
                return '<tr><td>' + (row.mes_nome || row.mes_ano) + '</td><td>' + formatNum(row.total_rotas) + '</td><td>' + formatNum(row.total_km_rodados) + '</td><td>' + formatMoney(row.total_frete) + '</td><td>' + formatMoney(row.total_comissao) + '</td><td>' + formatMoney(row.lucro_operacional) + '</td></tr>';
            }).join('') + '<tr class="bi-table-total"><td><strong>Total</strong></td><td>' + formatNum(sum('total_rotas')) + '</td><td>' + formatNum(sum('total_km_rodados')) + '</td><td>' + formatMoney(sum('total_frete')) + '</td><td>' + formatMoney(sum('total_comissao')) + '</td><td>' + formatMoney(sum('lucro_operacional')) + '</td></tr>';
        } else if (visao === 'manutencao') {
            titulo.textContent = 'Panorama mensal – Manutenção';
            thead.innerHTML = '<th>Mês</th><th>Qtde</th><th>Custo (R$)</th>';
            if (hist.length === 0) tbody.innerHTML = semDados;
            else tbody.innerHTML = hist.map(function(row) {
                return '<tr><td>' + (row.mes_nome || row.mes_ano) + '</td><td>' + formatNum(row.quantidade_manutencoes) + '</td><td>' + formatMoney(row.total_manutencoes) + '</td></tr>';
            }).join('') + '<tr class="bi-table-total"><td><strong>Total</strong></td><td>' + formatNum(sum('quantidade_manutencoes')) + '</td><td>' + formatMoney(sum('total_manutencoes')) + '</td></tr>';
        } else if (visao === 'despesas_viagem') {
            titulo.textContent = 'Panorama mensal – Despesas de viagem';
            thead.innerHTML = '<th>Mês</th><th>Total (R$)</th>';
            if (hist.length === 0) tbody.innerHTML = semDados;
            else tbody.innerHTML = hist.map(function(row) {
                return '<tr><td>' + (row.mes_nome || row.mes_ano) + '</td><td>' + formatMoney(row.total_despesas_viagem) + '</td></tr>';
            }).join('') + '<tr class="bi-table-total"><td><strong>Total</strong></td><td>' + formatMoney(sum('total_despesas_viagem')) + '</td></tr>';
        } else if (visao === 'despesas_fixas') {
            titulo.textContent = 'Panorama mensal – Despesas fixas';
            thead.innerHTML = '<th>Mês</th><th>Qtde</th><th>Total pago (R$)</th>';
            if (hist.length === 0) tbody.innerHTML = semDados;
            else tbody.innerHTML = hist.map(function(row) {
                return '<tr><td>' + (row.mes_nome || row.mes_ano) + '</td><td>' + formatNum(row.quantidade_despesas_fixas) + '</td><td>' + formatMoney(row.total_despesas_fixas) + '</td></tr>';
            }).join('') + '<tr class="bi-table-total"><td><strong>Total</strong></td><td>' + formatNum(sum('quantidade_despesas_fixas')) + '</td><td>' + formatMoney(sum('total_despesas_fixas')) + '</td></tr>';
        } else {
            titulo.textContent = 'Panorama mensal – Geral';
            thead.innerHTML = '<th>Mês</th><th>Rotas</th><th>Km</th><th>Frete</th><th>Comissão</th><th>Abast.</th><th>Gasto abast.</th><th>Desp. viagem</th><th>Manut.</th><th>Desp. fixas</th><th>Lucro oper.</th>';
            if (hist.length === 0) tbody.innerHTML = semDados;
            else tbody.innerHTML = hist.map(function(row) {
                return '<tr><td>' + (row.mes_nome || row.mes_ano) + '</td><td>' + formatNum(row.total_rotas) + '</td><td>' + formatNum(row.total_km_rodados) + '</td><td>' + formatMoney(row.total_frete) + '</td><td>' + formatMoney(row.total_comissao) + '</td><td>' + formatNum(row.total_abastecimentos) + '</td><td>' + formatMoney(row.total_gasto_abastecimentos) + '</td><td>' + formatMoney(row.total_despesas_viagem) + '</td><td>' + formatMoney(row.total_manutencoes) + '</td><td>' + formatMoney(row.total_despesas_fixas) + '</td><td>' + formatMoney(row.lucro_operacional) + '</td></tr>';
            }).join('') + '<tr class="bi-table-total"><td><strong>Total</strong></td><td>' + formatNum(sum('total_rotas')) + '</td><td>' + formatNum(sum('total_km_rodados')) + '</td><td>' + formatMoney(sum('total_frete')) + '</td><td>' + formatMoney(sum('total_comissao')) + '</td><td>' + formatNum(sum('total_abastecimentos')) + '</td><td>' + formatMoney(sum('total_gasto_abastecimentos')) + '</td><td>' + formatMoney(sum('total_despesas_viagem')) + '</td><td>' + formatMoney(sum('total_manutencoes')) + '</td><td>' + formatMoney(sum('total_despesas_fixas')) + '</td><td>' + formatMoney(sum('lucro_operacional')) + '</td></tr>';
        }
        document.getElementById('bi-table-wrap').style.display = 'block';
    }

    function aplicarFiltroPeriodo(data) {
        var anoEl = document.getElementById('filtro-ano');
        var mesEl = document.getElementById('filtro-mes');
        var ano = (anoEl && anoEl.value) ? String(anoEl.value).trim() : '';
        var mes = (mesEl && mesEl.value) ? String(mesEl.value).trim() : '';
        var hist = (data.historico_mensal || []).slice();
        if (!ano && !mes) return data;
        var filtered = hist;
        if (ano) {
            filtered = hist.filter(function(row) {
                var ma = row.mes_ano || '';
                if (ma.length < 7) return false;
                if (ma.substring(0, 4) !== ano) return false;
                if (mes) {
                    var mesStr = mes.length === 1 ? '0' + mes : mes;
                    return ma === ano + '-' + mesStr;
                }
                return true;
            });
        } else if (mes) {
            var mesStr = mes.length === 1 ? '0' + mes : mes;
            filtered = hist.filter(function(row) {
                var ma = row.mes_ano || '';
                return ma.length >= 7 && ma.substring(5, 7) === mesStr;
            });
        }
        var out = Object.assign({}, data);
        out.historico_mensal = filtered;
        out.labels = filtered.map(function(x) { return x.mes_nome || x.mes_ano; });
        if (biPeriodPreset === 'trim' && filtered.length > 3) {
            out.historico_mensal = filtered.slice(-3);
            out.labels = out.historico_mensal.map(function(x) { return x.mes_nome || x.mes_ano; });
        } else if (biPeriodPreset === '12m' && filtered.length > 12) {
            out.historico_mensal = filtered.slice(-12);
            out.labels = out.historico_mensal.map(function(x) { return x.mes_nome || x.mes_ano; });
        }
        return out;
    }

    function biApplyPeriodPreset(preset) {
        biPeriodPreset = preset || 'mes';
        document.querySelectorAll('[data-bi-period]').forEach(function (b) {
            b.classList.toggle('is-active', b.getAttribute('data-bi-period') === biPeriodPreset);
        });
        var now = new Date();
        var a = document.getElementById('filtro-ano');
        var m = document.getElementById('filtro-mes');
        if (!a || !m) return;
        if (preset === 'mes') {
            a.value = String(now.getFullYear());
            m.value = String(now.getMonth() + 1);
        } else if (preset === 'ano') {
            a.value = String(now.getFullYear());
            m.value = '';
        } else if (preset === '12m' || preset === 'trim') {
            a.value = '';
            m.value = '';
        }
        loadData();
        updateUrlFromFilters();
    }

    function biRerenderKpisIfLoaded() {
        if (!biLastData || !biLastVisao || biLastVisao === 'indicadores_desempenho') return;
        renderKPIs(biLastData, biLastVisao);
    }

    function loadData(opts) {
        opts = opts || {};
        var visao = (document.getElementById('filtro-visao') && document.getElementById('filtro-visao').value) || 'geral';
        var secIndicadores = document.getElementById('bi-indicadores-desempenho');
        if (secIndicadores) secIndicadores.style.display = 'none';
        if (visao === 'indicadores_desempenho') {
            showLoading(true);
            biUpdateExecSubtitle('indicadores_desempenho');
            var visaoExtrasInd = document.getElementById('bi-premium-visao');
            if (visaoExtrasInd) visaoExtrasInd.style.display = 'none';
            var anoInd = (document.getElementById('filtro-ano') && document.getElementById('filtro-ano').value) ? String(document.getElementById('filtro-ano').value).trim() : '';
            var mesInd = (document.getElementById('filtro-mes') && document.getElementById('filtro-mes').value) ? String(document.getElementById('filtro-mes').value).trim() : '';
            var urlInd = baseUrl + '/performance_indicators.php?visao=geral' + (anoInd ? '&ano=' + encodeURIComponent(anoInd) : '') + (mesInd ? '&mes=' + encodeURIComponent(mesInd) : '');
            fetch(urlInd, { credentials: 'same-origin' })
                .then(function(r) {
                    if (!r.ok) {
                        return r.text().then(function(t) {
                            var snip = (t || '').replace(/\s+/g, ' ').trim().slice(0, 180);
                            throw new Error(snip ? ('HTTP ' + r.status + ': ' + snip) : ('HTTP ' + r.status));
                        });
                    }
                    return r.json();
                })
                .then(function(json) {
                    showLoading(false);
                    if (json.success !== true || !json.data) {
                        showError(json.message || json.error || 'Resposta inválida da API.');
                        return;
                    }
                    document.getElementById('bi-kpis').style.display = 'none';
                    document.getElementById('bi-charts').style.display = 'none';
                    var premInd = document.getElementById('bi-premium-geral');
                    if (premInd) premInd.style.display = 'none';
                    document.body.classList.remove('biq-premium-active');
                    document.getElementById('bi-table-wrap').style.display = 'none';
                    biShowResumoCard(false);
                    var saudeFrota = document.getElementById('bi-saude-frota');
                    if (saudeFrota) saudeFrota.style.display = 'none';
                    hideAllExtraBlocks();
                    if (secIndicadores) secIndicadores.style.display = 'block';
                    var hist = json.data.historico_mensal || [];
                    var loadingEl = document.getElementById('indicatorsLoading');
                    var containerEl = document.getElementById('indicatorsTableContainer');
                    if (loadingEl) loadingEl.style.display = 'block';
                    if (containerEl) containerEl.style.display = 'none';
                    try {
                        buildIndicatorsTable(hist);
                    } catch (indErr) {
                        if (typeof window !== 'undefined' && window.__SF_DEBUG__) console.error('BI indicadores:', indErr);
                        var im = (typeof sfSafeErrorMessage === 'function') ? sfSafeErrorMessage(indErr, 'Falha ao montar tabela.') : ((indErr && indErr.message) || 'Falha ao montar tabela.');
                        showError('Erro ao montar indicadores: ' + im);
                        if (loadingEl) loadingEl.style.display = 'none';
                        return;
                    }
                    if (loadingEl) loadingEl.style.display = 'none';
                    if (containerEl) containerEl.style.display = 'block';
                    if (json.data && typeof showAvisoDadosIncompletos === 'function') showAvisoDadosIncompletos(json.data);
                    if (typeof updateUrlFromFilters === 'function') updateUrlFromFilters();
                })
                .catch(function(err) {
                    showLoading(false);
                    if (typeof window !== 'undefined' && window.__SF_DEBUG__) console.error('BI indicadores fetch:', err);
                    var em = (typeof sfSafeErrorMessage === 'function') ? sfSafeErrorMessage(err, 'Falha ao carregar.') : (err && err.message) || 'Falha ao carregar.';
                    showError('Erro ao carregar dados: ' + em);
                });
            return;
        }
        showLoading(true);
        var ano = (document.getElementById('filtro-ano') && document.getElementById('filtro-ano').value) ? String(document.getElementById('filtro-ano').value).trim() : '';
        var mes = (document.getElementById('filtro-mes') && document.getElementById('filtro-mes').value) ? String(document.getElementById('filtro-mes').value).trim() : '';
        var url = baseUrl + '/performance_indicators.php?visao=' + encodeURIComponent(visao) + (ano ? '&ano=' + encodeURIComponent(ano) : '') + (mes ? '&mes=' + encodeURIComponent(mes) : '');
        if (biLoadDataAbort) {
            try { biLoadDataAbort.abort(); } catch (e) { /* ignore */ }
        }
        biLoadDataAbort = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var fetchOpts = { credentials: 'same-origin' };
        if (biLoadDataAbort) fetchOpts.signal = biLoadDataAbort.signal;
        fetch(url, fetchOpts)
            .then(function(r) {
                if (!r.ok) {
                    return r.text().then(function(t) {
                        var snip = (t || '').replace(/\s+/g, ' ').trim().slice(0, 180);
                        throw new Error(snip ? ('HTTP ' + r.status + ': ' + snip) : ('HTTP ' + r.status));
                    });
                }
                return r.json();
            })
            .then(function(json) {
                showLoading(false);
                if (json.success !== true || !json.data) {
                    showError(json.message || json.error || 'Resposta inválida da API.');
                    return;
                }
                var dataFiltrada = ano ? json.data : aplicarFiltroPeriodo(json.data);
                var v = json.visao || visao;
                var rawPayload = json.data;
                setTimeout(function () {
                    try {
                        showAvisoDadosIncompletos(rawPayload);
                        renderKPIs(dataFiltrada, v);
                        renderCharts(dataFiltrada, v);
                        renderTable(dataFiltrada, v);
                        renderExtraBlocks(dataFiltrada, v);
                        biRenderVisaoPremiumExtras(dataFiltrada, v);
                        biLastData = dataFiltrada; biLastVisao = v;
                        biShowResumoCard(true);
                        if (!opts.skipIa) biGerarResumoIAAuto();
                        if (typeof requestAnimationFrame === 'function') {
                            requestAnimationFrame(function () {
                                requestAnimationFrame(biReflowBiCharts);
                            });
                        } else {
                            setTimeout(biReflowBiCharts, 50);
                        }
                        if (typeof updateUrlFromFilters === 'function') updateUrlFromFilters();
                    } catch (renderErr) {
                        if (typeof window !== 'undefined' && window.__SF_DEBUG__) {
                            console.error('BI render:', renderErr);
                        }
                        var rm = '';
                        try {
                            rm = (renderErr && renderErr.message) ? String(renderErr.message) : String(renderErr);
                        } catch (e) { rm = 'Erro desconhecido.'; }
                        showError('Erro ao montar o BI: ' + rm.slice(0, 500));
                    }
                }, 0);
            })
            .catch(function(err) {
                if (err && err.name === 'AbortError') return;
                showLoading(false);
                if (typeof window !== 'undefined' && window.__SF_DEBUG__) {
                    console.error('BI fetch/json:', err);
                }
                var em = (typeof sfSafeErrorMessage === 'function') ? sfSafeErrorMessage(err, 'Falha ao carregar.') : (err && err.message) || 'Falha ao carregar.';
                showError('Erro ao carregar dados: ' + em);
            });
    }

    function applyParamsFromUrl() {
        var params = {};
        (window.location.search || '').replace(/^\?/, '').split('&').forEach(function(p) {
            var parts = p.split('=');
            if (parts.length >= 2) params[decodeURIComponent(parts[0])] = decodeURIComponent(parts.slice(1).join('='));
        });
        var v = document.getElementById('filtro-visao');
        var a = document.getElementById('filtro-ano');
        var m = document.getElementById('filtro-mes');
        if (params.visao && v) { for (var i = 0; i < v.options.length; i++) { if (v.options[i].value === params.visao) { v.value = params.visao; break; } } }
        if (params.ano && a) a.value = params.ano;
        if (params.mes !== undefined && m) m.value = params.mes === '' ? '' : params.mes;
    }
    function updateBiContextLine() {
        var v = document.getElementById('filtro-visao');
        var a = document.getElementById('filtro-ano');
        var m = document.getElementById('filtro-mes');
        var el = document.getElementById('bi-context-line');
        if (!el) return;
        var visaoLabel = (v && v.options[v.selectedIndex]) ? v.options[v.selectedIndex].text : 'Geral';
        var anoTxt = (a && a.value) ? String(a.value).trim() : '';
        var mesTxt = 'Todos os meses';
        if (m && m.value !== '' && m.value != null) {
            var mi = parseInt(String(m.value), 10);
            if (!isNaN(mi) && mi >= 1 && mi <= 12) {
                var d = new Date(2000, mi - 1, 1);
                mesTxt = d.toLocaleString('pt-BR', { month: 'long' });
                mesTxt = mesTxt.charAt(0).toUpperCase() + mesTxt.slice(1);
            }
        }
        var parts = [visaoLabel];
        if (anoTxt) parts.push('Ano ' + anoTxt);
        parts.push(mesTxt);
        el.textContent = parts.join(' · ');
    }
    function updateUrlFromFilters() {
        var v = document.getElementById('filtro-visao');
        var a = document.getElementById('filtro-ano');
        var m = document.getElementById('filtro-mes');
        var visao = (v && v.value) ? v.value : 'geral';
        var ano = (a && a.value) ? a.value : '';
        var mes = (m && m.value) ? m.value : '';
        var q = '?visao=' + encodeURIComponent(visao) + (ano ? '&ano=' + encodeURIComponent(ano) : '') + (mes ? '&mes=' + encodeURIComponent(mes) : '');
        if (window.history && window.history.replaceState) window.history.replaceState({}, '', window.location.pathname + q);
        updateBiContextLine();
    }
    document.getElementById('btn-aplicar').addEventListener('click', function() { loadData(); updateUrlFromFilters(); });
    var btnAplicarTop = document.getElementById('btn-aplicar-top');
    if (btnAplicarTop) btnAplicarTop.addEventListener('click', function() { loadData(); updateUrlFromFilters(); });
    var btnExcelHeader = document.getElementById('btn-exportar-excel-header');
    if (btnExcelHeader) btnExcelHeader.addEventListener('click', function() {
        var btnCsv = document.getElementById('btn-exportar-tabela-csv');
        if (btnCsv) btnCsv.click();
    });
    var biSearch = document.getElementById('bi-search');
    var biSearchDebounce = null;
    if (biSearch) biSearch.addEventListener('input', function() {
        if (biSearchDebounce) clearTimeout(biSearchDebounce);
        biSearchDebounce = setTimeout(function () {
        var q = String(biSearch.value || '').toLowerCase().trim();
        document.querySelectorAll('#bi-premium-rank .bi-rank-item, #biTopVeiculosRank .bi-rank-item, #bi-tabela tbody tr, #bi-visao-insights .biq-insight, #bi-visao-alertas .biq-insight').forEach(function (el) {
            var txt = (el.textContent || '').toLowerCase();
            el.style.display = (!q || txt.indexOf(q) >= 0) ? '' : 'none';
        });
        }, 150);
    });
    var filtroVisao = document.getElementById('filtro-visao');
    if (filtroVisao) filtroVisao.addEventListener('change', function() { loadDataDebounced(); updateUrlFromFilters(); });
    var filtroAno = document.getElementById('filtro-ano');
    var filtroMes = document.getElementById('filtro-mes');
    if (filtroAno) filtroAno.addEventListener('change', function() { loadDataDebounced(); updateUrlFromFilters(); });
    if (filtroMes) filtroMes.addEventListener('change', function() { loadDataDebounced(); updateUrlFromFilters(); });
    var btnMesAtual = document.getElementById('btn-mes-atual');
    if (btnMesAtual) btnMesAtual.addEventListener('click', function() {
        var now = new Date();
        var a = document.getElementById('filtro-ano');
        var m = document.getElementById('filtro-mes');
        if (a) a.value = now.getFullYear();
        if (m) m.value = String(now.getMonth() + 1);
        loadData();
        updateUrlFromFilters();
    });
    var btnImprimir = document.getElementById('btn-imprimir');
    if (btnImprimir) btnImprimir.addEventListener('click', biExportPdf);
    var btnAutoRefresh = document.getElementById('btn-auto-refresh');
    if (btnAutoRefresh) btnAutoRefresh.addEventListener('click', biToggleAutoRefresh);
    var btnCompartilhar = document.getElementById('btn-compartilhar');
    if (btnCompartilhar) btnCompartilhar.addEventListener('click', biShareDashboard);
    document.querySelectorAll('[data-bi-period]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            biApplyPeriodPreset(btn.getAttribute('data-bi-period'));
        });
    });
    document.querySelectorAll('[data-bi-compare]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            biKpiCompareMode = btn.getAttribute('data-bi-compare') || 'month';
            document.querySelectorAll('[data-bi-compare]').forEach(function (b) {
                b.classList.toggle('is-active', b.getAttribute('data-bi-compare') === biKpiCompareMode);
            });
            biRerenderKpisIfLoaded();
        });
    });
    var btnResumoIa = document.getElementById('btn-resumo-ia');
    if (btnResumoIa) btnResumoIa.addEventListener('click', function () { biGerarResumoIA(true); });
    var btnIndAtualizar = document.getElementById('btn-indicadores-atualizar');
    if (btnIndAtualizar) btnIndAtualizar.addEventListener('click', loadPerformanceIndicators);
    var btnIndExcel = document.getElementById('btn-indicadores-excel');
    if (btnIndExcel) btnIndExcel.addEventListener('click', exportIndicatorsToExcel);
    var btnExportTabela = document.getElementById('btn-exportar-tabela-csv');
    if (btnExportTabela) btnExportTabela.addEventListener('click', exportTableToCsv);
    var btnExportGrafico = document.getElementById('btn-exportar-grafico-png');
    if (btnExportGrafico) btnExportGrafico.addEventListener('click', exportChartToPng);
    var btnDrillClose = document.getElementById('bi-drill-close');
    var bdBackdrop = document.getElementById('bi-drill-backdrop');
    if (btnDrillClose) btnDrillClose.addEventListener('click', biCloseDrillModal);
    if (bdBackdrop) bdBackdrop.addEventListener('click', biCloseDrillModal);
    var btnDrillExport = document.getElementById('bi-drill-export');
    if (btnDrillExport) btnDrillExport.addEventListener('click', biExportDrillCsv);
    document.addEventListener('keydown', function(ev) {
        if (ev.key === 'Escape') {
            var modal = document.getElementById('bi-drill-modal');
            if (modal && modal.classList.contains('is-open')) biCloseDrillModal();
        } else if (ev.ctrlKey && ev.key === 'r') {
            ev.preventDefault();
            loadData();
        }
    });
    var simDiesel = document.getElementById('sim-diesel-pct');
    var simComissao = document.getElementById('sim-comissao-pct');
    if (simDiesel) simDiesel.addEventListener('input', function() { if (typeof simuladorUpdate === 'function') simuladorUpdate(); });
    if (simComissao) simComissao.addEventListener('input', function() { if (typeof simuladorUpdate === 'function') simuladorUpdate(); });
    window.addEventListener('sf-theme-changed', function () {
        setTimeout(function () {
            biScheduleChartLayoutStabilize();
        }, 50);
    });
    applyParamsFromUrl();
    updateBiContextLine();
    loadData();
})();