/**
 * Chart.js 4.x — helpers do painel BI (bi.php)
 */
(function (global) {
    'use strict';

    function palette() {
        var light = document.documentElement.classList.contains('light-theme');
        var biq = document.getElementById('biq');
        function cssVar(name, fb) {
            try {
                if (biq) {
                    var v = getComputedStyle(biq).getPropertyValue(name).trim();
                    if (v) return v;
                }
            } catch (e) { /* ignore */ }
            return fb;
        }
        return {
            tick: cssVar('--biq-muted', light ? '#64748b' : '#94a3b8'),
            grid: cssVar('--biq-border', light ? 'rgba(15, 23, 42, 0.12)' : 'rgba(148, 163, 184, 0.22)'),
            legend: cssVar('--biq-muted', light ? '#64748b' : '#94a3b8'),
            tooltipBg: light ? 'rgba(30, 41, 59, 0.96)' : 'rgba(15, 24, 36, 0.94)'
        };
    }

    function moneyTick(v) {
        var n = Number(v);
        if (isNaN(n)) return v;
        if (Math.abs(n) >= 1000000) return 'R$ ' + (n / 1000000).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + 'M';
        if (Math.abs(n) >= 1000) return 'R$ ' + (n / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 0 }) + 'k';
        return 'R$ ' + n.toLocaleString('pt-BR', { maximumFractionDigits: 0 });
    }

    function basePlugins(pal, legendPos, legendDisplay) {
        return {
            legend: {
                display: legendDisplay !== false,
                position: legendPos || 'bottom',
                labels: {
                    color: pal.legend,
                    usePointStyle: true,
                    boxWidth: 8,
                    padding: 12
                }
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                backgroundColor: pal.tooltipBg,
                titleColor: '#f8fafc',
                bodyColor: '#e2e8f0',
                borderColor: pal.grid,
                borderWidth: 1,
                cornerRadius: 8,
                padding: 10
            }
        };
    }

    function scalesXY(pal, opts) {
        opts = opts || {};
        var s = {
            x: {
                grid: { display: false },
                ticks: { color: pal.tick, maxRotation: 48, minRotation: 0, autoSkip: true }
            },
            y: {
                beginAtZero: opts.beginAtZero !== false,
                grid: { color: pal.grid },
                ticks: { color: pal.tick }
            }
        };
        if (opts.moneyY) {
            s.y.ticks.callback = moneyTick;
        }
        return s;
    }

    function scalesDual(pal) {
        return {
            x: { grid: { display: false }, ticks: { color: pal.tick } },
            y: {
                beginAtZero: true,
                position: 'left',
                grid: { color: pal.grid },
                ticks: { color: pal.tick }
            },
            y1: {
                beginAtZero: true,
                position: 'right',
                grid: { drawOnChartArea: false },
                ticks: { color: pal.tick }
            }
        };
    }

    var chartOpts = {
        base: function (extra) {
            var pal = palette();
            return Object.assign({
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 350 },
                plugins: basePlugins(pal, 'bottom')
            }, extra || {});
        },
        bar: function () {
            var pal = palette();
            return {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 350 },
                plugins: Object.assign(basePlugins(pal, 'bottom'), { legend: { display: true, position: 'bottom', labels: { color: pal.legend, boxWidth: 8, padding: 12 } } }),
                scales: scalesXY(pal, { beginAtZero: true })
            };
        },
        barDual: function () {
            var pal = palette();
            return {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 350 },
                plugins: basePlugins(pal, 'bottom'),
                scales: scalesDual(pal)
            };
        },
        barHorizontal: function () {
            var pal = palette();
            return {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                animation: { duration: 350 },
                plugins: Object.assign(basePlugins(pal, 'bottom'), { legend: { display: true, position: 'bottom', labels: { color: pal.legend } } }),
                scales: {
                    x: { beginAtZero: true, grid: { color: pal.grid }, ticks: { color: pal.tick } },
                    y: { grid: { display: false }, ticks: { color: pal.tick } }
                }
            };
        },
        line: function () {
            return chartOpts.base({ scales: scalesXY(palette(), { beginAtZero: true }) });
        },
        premiumLine: function () {
            var pal = palette();
            return {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                animation: { duration: 400 },
                plugins: Object.assign(basePlugins(pal, 'top'), {
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: pal.tooltipBg,
                        titleColor: '#f8fafc',
                        bodyColor: '#e2e8f0',
                        borderColor: pal.grid,
                        borderWidth: 1,
                        callbacks: {
                            label: function (ctx) {
                                var label = ctx.dataset.label || '';
                                var n = Number(ctx.parsed.y);
                                var s = isNaN(n) ? String(ctx.parsed.y) : n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                return label + ': R$ ' + s;
                            }
                        }
                    }
                }),
                scales: scalesXY(pal, { beginAtZero: true, moneyY: true })
            };
        },
        donut: function () {
            var pal = palette();
            return {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                animation: { duration: 350 },
                plugins: Object.assign(basePlugins(pal, 'bottom'), {
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var n = Number(ctx.parsed);
                                var s = isNaN(n) ? ctx.parsed : n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                return (ctx.label || '') + ': R$ ' + s;
                            }
                        }
                    }
                })
            };
        },
        premiumDonut: function () {
            var pal = palette();
            return {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '64%',
                animation: { duration: 400 },
                plugins: Object.assign(basePlugins(pal, 'right'), {
                    legend: { display: true, position: 'right', labels: { color: pal.legend, usePointStyle: true, boxWidth: 8, padding: 10 } },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var n = Number(ctx.parsed);
                                var s = isNaN(n) ? ctx.parsed : n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                var sum = ctx.dataset.data.reduce(function (a, b) { return a + (Number(b) || 0); }, 0);
                                var pct = sum > 0 ? Math.round((n / sum) * 100) : 0;
                                return (ctx.label || '') + ': R$ ' + s + ' (' + pct + '%)';
                            }
                        }
                    }
                })
            };
        }
    };

    function hexToRgba(hex, a) {
        var m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(String(hex || ''));
        if (!m) return null;
        return 'rgba(' + parseInt(m[1], 16) + ',' + parseInt(m[2], 16) + ',' + parseInt(m[3], 16) + ',' + a + ')';
    }

    function vivify(chartsMap) {
        if (!chartsMap) return;
        try {
            Object.keys(chartsMap).forEach(function (k) {
                var ch = chartsMap[k];
                if (!ch || !ch.data || !ch.data.datasets) return;
                var ctx = ch.ctx;
                if (!ctx) return;
                var areaH = (ch.chartArea && ch.chartArea.bottom) ? ch.chartArea.bottom : 260;
                var changed = false;
                ch.data.datasets.forEach(function (ds) {
                    var dtype = ds.type || ch.config.type;
                    if (dtype === 'line') {
                        var hex = (typeof ds.borderColor === 'string' && ds.borderColor.indexOf('#') === 0) ? ds.borderColor : null;
                        if (hex && ds.fill !== false) {
                            try {
                                var g = ctx.createLinearGradient(0, 0, 0, areaH);
                                g.addColorStop(0, hexToRgba(hex, 0.35) || 'rgba(59,130,246,0.35)');
                                g.addColorStop(1, hexToRgba(hex, 0) || 'rgba(59,130,246,0)');
                                ds.backgroundColor = g;
                                ds.fill = true;
                            } catch (eg) { /* ignore */ }
                        }
                        if (ds.tension == null) ds.tension = 0.4;
                        if (!ds.borderWidth || ds.borderWidth < 2) ds.borderWidth = 2;
                        if (ds.pointRadius == null) ds.pointRadius = 0;
                        ds.pointHoverRadius = 4;
                        changed = true;
                    } else if (dtype === 'bar') {
                        if (typeof ds.borderColor === 'string' && /^#/.test(ds.borderColor)) {
                            ds.backgroundColor = ds.borderColor;
                        }
                        if (ds.borderRadius == null) ds.borderRadius = 6;
                        if (ds.maxBarThickness == null) ds.maxBarThickness = 34;
                        ds.borderWidth = 0;
                        changed = true;
                    } else if (dtype === 'doughnut' || dtype === 'pie') {
                        ds.borderWidth = 0;
                        ds.hoverOffset = 6;
                        changed = true;
                    }
                });
                if (changed) { try { ch.update(); } catch (eu) { /* ignore */ } }
            });
        } catch (e) { /* ignore */ }
    }

    function attachDrill(chart, ctx, openDrillFn) {
        if (!chart || !ctx || !openDrillFn) return;
        chart.options.onClick = function (evt, elements) {
            if (!elements || !elements.length) return;
            var i = elements[0].index;
            if (i == null) return;
            var mes = ctx.mesAnos[i];
            if (!mes) return;
            var lab = (ctx.labels && ctx.labels[i]) ? ctx.labels[i] : mes;
            openDrillFn(ctx.serie, mes, lab);
        };
        chart.update();
    }

    function attachDrillTopVeiculo(chart, serieApi, ids, placaLabels, baseUrl, pq, openModalFn) {
        if (!chart || !ids || !ids.length || !openModalFn) return;
        chart.options.onClick = function (evt, elements) {
            if (!elements || !elements.length) return;
            var idx = elements[0].index;
            if (idx == null) return;
            var vid = ids[idx];
            if (vid == null || vid === '') return;
            var lab = (placaLabels && placaLabels[idx]) ? placaLabels[idx] : ('Veículo ' + vid);
            var url = baseUrl + '/bi_drill_down.php?serie=' + encodeURIComponent(serieApi) + '&veiculo_id=' + encodeURIComponent(vid) + pq;
            openModalFn(lab + ' — detalhes', url, 'rotas_veiculo');
        };
        chart.update();
    }

    function attachDrillPieDesp(chart, baseUrl, pq, openModalFn) {
        if (!chart || !openModalFn) return;
        var labelsPie = ['Pedágio', 'Alimentação', 'Estacion./lavagem', 'Outros'];
        chart.options.onClick = function (evt, elements) {
            if (!elements || !elements.length) return;
            var ix = elements[0].index;
            if (ix == null) return;
            var url = baseUrl + '/bi_drill_down.php?serie=desp_viagem_pie&slice=' + encodeURIComponent(ix) + pq;
            openModalFn('Despesas de viagem — ' + (labelsPie[ix] != null ? labelsPie[ix] : ('Fatia ' + ix)), url, 'desp_viagem_pie');
        };
        chart.update();
    }

    function attachDrillManutPie(chart, baseUrl, pq, openModalFn) {
        if (!chart || !openModalFn) return;
        var labs = ['Preventiva', 'Corretiva'];
        var tipos = ['preventiva', 'corretiva'];
        chart.options.onClick = function (evt, elements) {
            if (!elements || !elements.length) return;
            var ix = elements[0].index;
            if (ix == null) return;
            var tp = tipos[ix] || 'preventiva';
            var url = baseUrl + '/bi_drill_down.php?serie=manut_pie&tipo_pie=' + encodeURIComponent(tp) + pq;
            openModalFn('Manutenções — ' + (labs[ix] != null ? labs[ix] : tp), url, 'manut_pie');
        };
        chart.update();
    }

    function destroyAll(chartsMap, canvasIds) {
        (canvasIds || []).forEach(function (id) {
            if (chartsMap[id]) {
                try { chartsMap[id].destroy(); } catch (e) {}
                chartsMap[id] = null;
            }
            var el = document.getElementById(id);
            if (!el || typeof Chart === 'undefined') return;
            var oc = null;
            if (typeof Chart.getChart === 'function') {
                try { oc = Chart.getChart(el); } catch (e0) {}
            }
            if (oc) {
                try { oc.destroy(); } catch (e2) {}
            }
        });
    }

    function reflow(chartsMap, canvasIds) {
        (canvasIds || []).forEach(function (id) {
            var ch = chartsMap[id];
            if (ch && typeof ch.resize === 'function') {
                try { ch.resize(); ch.update(); } catch (e) { /* ignore */ }
            }
        });
    }

    global.BiChartsV4 = {
        palette: palette,
        chartOpts: chartOpts,
        vivify: vivify,
        attachDrill: attachDrill,
        attachDrillTopVeiculo: attachDrillTopVeiculo,
        attachDrillPieDesp: attachDrillPieDesp,
        attachDrillManutPie: attachDrillManutPie,
        destroyAll: destroyAll,
        reflow: reflow,
        moneyTick: moneyTick
    };
})(typeof window !== 'undefined' ? window : this);
