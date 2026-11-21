/**
 * Chart.js 4 helpers for ADM Control Center (aligned with sf-charts-premium)
 */
(function (global) {
    'use strict';

    var chartJsLoaded = false;
    var premiumLoaded = false;
    var PALETTE = ['#3b82f6', '#22c55e', '#eab308', '#f97316', '#a855f7', '#06b6d4', '#94a3b8'];

    function chartJsUrl() {
        return global.__SF_CHARTJS_URL__ || 'https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js';
    }

    function premiumUrl() {
        var base = global.__SF_APP_BASE__ || '';
        return (base.replace(/\/+$/, '') || '') + '/js/sf-charts-premium.js';
    }

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            if (!src) {
                resolve();
                return;
            }
            var existing = document.querySelector('script[src="' + src + '"]');
            if (existing) {
                if (existing.getAttribute('data-loaded') === '1') {
                    resolve();
                    return;
                }
                existing.addEventListener('load', function () { resolve(); });
                existing.addEventListener('error', reject);
                return;
            }
            var s = document.createElement('script');
            s.src = src;
            s.onload = function () {
                s.setAttribute('data-loaded', '1');
                resolve();
            };
            s.onerror = reject;
            document.head.appendChild(s);
        });
    }

    function loadPremium() {
        if (premiumLoaded) return Promise.resolve();
        return loadScript(premiumUrl()).then(function () {
            premiumLoaded = true;
        }).catch(function () {
            premiumLoaded = true;
        });
    }

    function loadChartJs() {
        if (chartJsLoaded && global.Chart) {
            return loadPremium();
        }
        if (global.Chart) {
            chartJsLoaded = true;
            return loadPremium();
        }
        return loadScript(chartJsUrl()).then(function () {
            chartJsLoaded = true;
            return loadPremium();
        });
    }

    function themeVar(name, fallback) {
        try {
            var v = getComputedStyle(document.body).getPropertyValue(name);
            v = (v || '').trim();
            return v || fallback;
        } catch (e) {
            return fallback;
        }
    }

    function theme() {
        return {
            tick: themeVar('--text-secondary', '#94a3b8'),
            grid: themeVar('--border-color', 'rgba(148,163,184,0.18)'),
            primary: themeVar('--accent-primary', '#3b82f6')
        };
    }

    function brlScales() {
        var th = theme();
        return {
            x: {
                grid: { display: false, drawBorder: false },
                ticks: { color: th.tick, font: { size: 11 }, maxRotation: 0 }
            },
            y: {
                beginAtZero: true,
                grid: { color: th.grid, drawBorder: false },
                ticks: {
                    color: th.tick,
                    font: { size: 11 },
                    callback: function (value) {
                        return Number(value).toLocaleString('pt-BR', {
                            style: 'currency',
                            currency: 'BRL',
                            maximumFractionDigits: 0
                        });
                    }
                }
            }
        };
    }

    function destroyChart(canvasId) {
        if (!global.Chart) return;
        var el = document.getElementById(canvasId);
        if (!el) return;
        var existing = global.Chart.getChart(el);
        if (existing) existing.destroy();
    }

    function lineChart(canvasId, labels, data, label) {
        return loadChartJs().then(function () {
            var ctx = document.getElementById(canvasId);
            if (!ctx) return null;
            destroyChart(canvasId);
            var th = theme();
            return new global.Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: label || 'Valor',
                        data: data,
                        borderColor: th.primary,
                        backgroundColor: th.primary + '22',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 0,
                        pointHoverRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: brlScales()
                }
            });
        });
    }

    function barChart(canvasId, labels, data, label) {
        return loadChartJs().then(function () {
            var ctx = document.getElementById(canvasId);
            if (!ctx) return null;
            destroyChart(canvasId);
            var th = theme();
            return new global.Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: label || '',
                        data: data,
                        backgroundColor: th.primary + 'a6',
                        borderRadius: 6,
                        maxBarThickness: 46
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: brlScales()
                }
            });
        });
    }

    function doughnutChart(canvasId, labels, data) {
        return loadChartJs().then(function () {
            var ctx = document.getElementById(canvasId);
            if (!ctx) return null;
            destroyChart(canvasId);
            var th = theme();
            return new global.Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: PALETTE.slice(0, data.length),
                        borderWidth: 0,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                boxWidth: 8,
                                padding: 12,
                                color: th.tick,
                                font: { size: 11 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (item) {
                                    var v = item.parsed || 0;
                                    return (item.label || '') + ': ' + Number(v).toLocaleString('pt-BR', {
                                        style: 'currency',
                                        currency: 'BRL'
                                    });
                                }
                            }
                        }
                    }
                }
            });
        });
    }

    global.AdmCharts = {
        line: lineChart,
        bar: barChart,
        doughnut: doughnutChart,
        destroy: destroyChart,
        load: loadChartJs
    };
})(typeof window !== 'undefined' ? window : this);
