/**
 * Gráficos do dashboard principal (index.php) — Chart.js 4 + sf-charts-premium.
 */
(function (g) {
    'use strict';

    var PALETTE = ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#64748b'];
    var charts = { financial: null, expenses: null, commissions: null, netRevenue: null };
    var financialLoading = false;
    var primaryLoaded = false;
    var secondaryLoaded = false;

    function sfChartApiUrl(path) {
        var period = g.sfDashPeriod || (g.document && g.document.body && g.document.body.getAttribute('data-dash-period')) || 'year';
        var sep = path.indexOf('?') >= 0 ? '&' : '?';
        if (typeof g.sfApiUrl === 'function') {
            return g.sfApiUrl(path + sep + 'period=' + encodeURIComponent(period));
        }
        return path + sep + 'period=' + encodeURIComponent(period);
    }

    function theme() {
        if (g.SFCharts && typeof g.SFCharts.theme === 'function') {
            return g.SFCharts.theme();
        }
        try {
            var cs = getComputedStyle(g.document.body);
            return {
                tick: (cs.getPropertyValue('--text-secondary') || '#94a3b8').trim() || '#94a3b8',
                grid: (cs.getPropertyValue('--border-color') || 'rgba(148,163,184,0.18)').trim()
            };
        } catch (e) {
            return { tick: '#94a3b8', grid: 'rgba(148,163,184,0.18)' };
        }
    }

    function gradient(ctx, hex) {
        if (g.SFCharts && typeof g.SFCharts.gradient === 'function') {
            return g.SFCharts.gradient(ctx, hex);
        }
        try {
            var h = (ctx.canvas && ctx.canvas.height) ? ctx.canvas.height : 240;
            var grd = ctx.createLinearGradient(0, 0, 0, h);
            grd.addColorStop(0, hex + '55');
            grd.addColorStop(1, hex + '00');
            return grd;
        } catch (e) {
            return hex + '22';
        }
    }

    function scalesBrl(extraY) {
        var th = theme();
        extraY = extraY || {};
        var yTicks = Object.assign({
            color: th.tick,
            font: { size: 11 },
            callback: function (value) {
                return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 }).format(value);
            }
        }, extraY.ticks || {});
        var y = Object.assign({ beginAtZero: true, grid: { color: th.grid, drawBorder: false } }, extraY);
        y.ticks = yTicks;
        return {
            x: { grid: { display: false, drawBorder: false }, ticks: { color: th.tick, font: { size: 11 }, maxRotation: 0 } },
            y: y
        };
    }

    function legend(pos) {
        var th = theme();
        return {
            position: pos || 'top',
            labels: { usePointStyle: true, boxWidth: 8, padding: 14, color: th.tick, font: { size: 11, weight: '500' } }
        };
    }

    function brlTooltip() {
        return {
            callbacks: {
                label: function (ctx) {
                    var v = ctx.raw;
                    if (v == null) return ctx.label || '';
                    return (ctx.dataset.label ? ctx.dataset.label + ': ' : '') +
                        new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v);
                }
            }
        };
    }

    function destroyChart(id) {
        if (typeof Chart === 'undefined' || typeof Chart.getChart !== 'function') return;
        var existing = Chart.getChart(id);
        if (existing) existing.destroy();
    }

    function applyDonutPalette(data) {
        if (!data || !data.datasets || !data.datasets[0]) return data;
        var ds = data.datasets[0];
        var n = (ds.data && ds.data.length) || 0;
        ds.backgroundColor = PALETTE.slice(0, n);
        ds.borderWidth = 0;
        ds.hoverOffset = 8;
        return data;
    }

    async function initFinancialChart() {
        if (financialLoading) return;
        financialLoading = true;
        destroyChart('financialChart');
        if (charts.financial) {
            charts.financial.destroy();
            charts.financial = null;
        }

        var canvas = g.document.getElementById('financialChart');
        if (!canvas) {
            financialLoading = false;
            return;
        }

        var labels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        var faturamento = [];
        var despesas = [];
        var fb = g.__SF_DASH_CHART_FALLBACK__ || {};

        try {
            var res = await fetch(sfChartApiUrl('financial_analytics.php'), { credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            var data = await res.json();
            labels = data.labels || labels;
            faturamento = data.faturamento || [];
            despesas = data.despesas || [];
        } catch (e) {
            console.error('dashboard financial chart:', e);
            faturamento = [fb.faturamento || 0];
            despesas = [fb.despesas || 0];
            labels = labels.slice(0, 1);
        }

        var ctx = canvas.getContext('2d');
        charts.financial = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Receita',
                        data: faturamento,
                        borderColor: '#22c55e',
                        backgroundColor: gradient(ctx, '#22c55e'),
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHitRadius: 12
                    },
                    {
                        label: 'Custos',
                        data: despesas,
                        borderColor: '#ef4444',
                        backgroundColor: gradient(ctx, '#ef4444'),
                        fill: true,
                        tension: 0.35,
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHitRadius: 12
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: legend('top'),
                    tooltip: brlTooltip()
                },
                scales: scalesBrl()
            }
        });
        financialLoading = false;
    }

    async function initExpensesDistributionChart() {
        destroyChart('expensesDistributionChart');
        if (charts.expenses) {
            charts.expenses.destroy();
            charts.expenses = null;
        }
        var canvas = g.document.getElementById('expensesDistributionChart');
        if (!canvas) return;

        var data = { labels: [], datasets: [{ data: [] }] };
        try {
            var res = await fetch(sfChartApiUrl('expenses_distribution.php'), { credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            data = applyDonutPalette(await res.json());
        } catch (e) {
            console.error('dashboard expenses donut:', e);
        }

        var ctx = canvas.getContext('2d');
        charts.expenses = new Chart(ctx, {
            type: 'doughnut',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '64%',
                plugins: {
                    legend: legend('bottom'),
                    tooltip: brlTooltip()
                }
            }
        });
    }

    async function initCommissionsChart() {
        destroyChart('commissionsChart');
        if (charts.commissions) {
            charts.commissions.destroy();
            charts.commissions = null;
        }
        var canvas = g.document.getElementById('commissionsChart');
        if (!canvas) return;

        var data = { labels: [], datasets: [{ label: 'Comissões', data: [], borderColor: '#8b5cf6' }] };
        try {
            var res = await fetch(sfChartApiUrl('commissions_analytics.php'), { credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            data = await res.json();
        } catch (e) {
            console.error('dashboard commissions chart:', e);
        }

        var ctx = canvas.getContext('2d');
        var ds = (data.datasets && data.datasets[0]) ? data.datasets[0] : { data: [] };
        charts.commissions = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: ds.label || 'Comissões',
                    data: ds.data || [],
                    borderColor: '#8b5cf6',
                    backgroundColor: gradient(ctx, '#8b5cf6'),
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: brlTooltip() },
                scales: scalesBrl()
            }
        });
    }

    async function initNetRevenueChart() {
        destroyChart('netRevenueChart');
        if (charts.netRevenue) {
            charts.netRevenue.destroy();
            charts.netRevenue = null;
        }
        var canvas = g.document.getElementById('netRevenueChart');
        if (!canvas) return;

        var data = { labels: [], datasets: [{ label: 'Lucro líquido', data: [] }] };
        try {
            var res = await fetch(sfChartApiUrl('net_revenue_analytics.php'), { credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            data = await res.json();
        } catch (e) {
            console.error('dashboard net revenue chart:', e);
        }

        var ctx = canvas.getContext('2d');
        var ds = (data.datasets && data.datasets[0]) ? data.datasets[0] : { data: [], label: 'Lucro líquido' };
        var values = ds.data || [];
        charts.netRevenue = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: ds.label || 'Lucro líquido',
                    data: values,
                    borderColor: '#3b82f6',
                    backgroundColor: gradient(ctx, '#3b82f6'),
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    segment: {
                        borderColor: function (c) {
                            var v = c.p1.parsed.y;
                            return (v != null && v < 0) ? '#ef4444' : '#22c55e';
                        }
                    }
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: brlTooltip() },
                scales: scalesBrl()
            }
        });
    }

    function runPrimaryCharts() {
        if (primaryLoaded || typeof Chart === 'undefined') return;
        primaryLoaded = true;
        initFinancialChart();
        initExpensesDistributionChart();
    }

    function runSecondaryCharts() {
        if (secondaryLoaded || typeof Chart === 'undefined') return;
        secondaryLoaded = true;
        initCommissionsChart();
        initNetRevenueChart();
    }

    function runAllCharts() {
        runPrimaryCharts();
        runSecondaryCharts();
    }

    function wireDetailsLazyCharts() {
        var details = g.document.querySelector('details.dh-more');
        if (!details) return;
        if (details.open) runSecondaryCharts();
        details.addEventListener('toggle', function () {
            if (details.open) runSecondaryCharts();
        });
    }

    function scheduleCharts() {
        var done = false;
        function run() {
            if (done) return;
            done = true;
            runPrimaryCharts();
            wireDetailsLazyCharts();
        }
        var anchor = g.document.getElementById('financialChart')
            || g.document.getElementById('expensesDistributionChart');
        if (!anchor) {
            wireDetailsLazyCharts();
            return;
        }
        if ('IntersectionObserver' in g) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    if (en.isIntersecting) {
                        run();
                        io.disconnect();
                    }
                });
            }, { root: null, rootMargin: '120px 0px', threshold: 0 });
            io.observe(anchor);
        } else {
            g.requestAnimationFrame(run);
        }
    }

    g.addEventListener('sf-theme-changed', function () {
        if (primaryLoaded) {
            initFinancialChart();
            initExpensesDistributionChart();
        }
        if (secondaryLoaded) {
            initCommissionsChart();
            initNetRevenueChart();
        }
    });

    g.DashboardHomeCharts = {
        init: runAllCharts,
        refresh: runAllCharts
    };

    if (g.document.readyState === 'loading') {
        g.document.addEventListener('DOMContentLoaded', scheduleCharts);
    } else {
        scheduleCharts();
    }
})(typeof window !== 'undefined' ? window : this);
