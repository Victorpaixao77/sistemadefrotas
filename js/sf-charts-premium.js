/**
 * sf-charts-premium.js — padrão visual premium para TODOS os gráficos (Chart.js 4).
 *
 * Aplica defaults globais (mesmo visual dos gráficos do routes.php): tipografia e
 * cores temáticas, barras arredondadas, linhas suaves, pontos discretos, legenda
 * em "point style", grade suave no eixo Y (sem grade vertical) e donut com furo.
 *
 * Carregue este arquivo LOGO APÓS o <script> do Chart.js (no <head>), antes de
 * qualquer gráfico ser criado. Configs explícitas de cada gráfico continuam tendo
 * prioridade sobre estes defaults.
 *
 * Também expõe helpers opcionais (window.SFCharts) para quem quiser gradiente/eixos
 * premium manualmente, no mesmo formato usado no routes.js.
 */
(function () {
    if (typeof window === 'undefined' || typeof window.Chart === 'undefined') return;
    var Chart = window.Chart;
    var D = Chart.defaults;
    if (!D) return;
    // Só aplica no Chart.js v4 (evita quebrar páginas legadas em 2.x).
    if (!/^4/.test(String(Chart.version || '4'))) return;

    function themeVar(name, fallback) {
        try {
            var v = getComputedStyle(document.body).getPropertyValue(name);
            v = (v || '').trim();
            return v || fallback;
        } catch (e) { return fallback; }
    }

    function applyTheme() {
        var tick = themeVar('--text-secondary', '#94a3b8');
        var grid = themeVar('--border-color', 'rgba(148,163,184,0.18)');
        var surface = themeVar('--bg-secondary', '#0f172a');
        var textPrimary = themeVar('--text-primary', '#e2e8f0');

        // Tipografia / cor base
        if (D.font) { D.font.family = "'Segoe UI', system-ui, -apple-system, 'Inter', sans-serif"; D.font.size = 11; }
        D.color = tick;

        // Barras arredondadas (igual routes)
        if (D.elements && D.elements.bar) {
            D.elements.bar.borderRadius = 6;
            D.elements.bar.borderSkipped = false;
            D.elements.bar.maxBarThickness = 46;
        }
        // Linhas suaves
        if (D.elements && D.elements.line) {
            D.elements.line.tension = 0.35;
            D.elements.line.borderWidth = 2;
            D.elements.line.fill = false;
        }
        // Pontos discretos (aparecem no hover)
        if (D.elements && D.elements.point) {
            D.elements.point.radius = 0;
            D.elements.point.hoverRadius = 5;
            D.elements.point.hitRadius = 10;
        }
        // Donut/pizza sem borda, com leve destaque no hover
        if (D.elements && D.elements.arc) {
            D.elements.arc.borderWidth = 0;
            D.elements.arc.hoverOffset = 8;
        }

        // Legenda em point-style
        if (D.plugins && D.plugins.legend) {
            D.plugins.legend.labels = Object.assign({}, D.plugins.legend.labels, {
                usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 14, color: tick
            });
        }
        // Tooltip premium
        if (D.plugins && D.plugins.tooltip) {
            Object.assign(D.plugins.tooltip, {
                backgroundColor: surface,
                titleColor: textPrimary,
                bodyColor: tick,
                borderColor: grid,
                borderWidth: 1,
                padding: 10,
                cornerRadius: 8,
                usePointStyle: true,
                boxPadding: 5
            });
        }

        // Eixos: grade suave no Y, sem grade vertical no X (igual routes)
        if (D.scales) {
            ['linear', 'logarithmic', 'time', 'timeseries', 'radialLinear'].forEach(function (k) {
                if (D.scales[k]) {
                    D.scales[k].grid = Object.assign({}, D.scales[k].grid, { color: grid, drawBorder: false });
                    D.scales[k].ticks = Object.assign({}, D.scales[k].ticks, { color: tick });
                }
            });
            if (D.scales.category) {
                D.scales.category.grid = Object.assign({}, D.scales.category.grid, { display: false, drawBorder: false });
                D.scales.category.ticks = Object.assign({}, D.scales.category.ticks, { color: tick });
            }
        }

        // Donut com furo elegante por padrão
        if (D.datasets && D.datasets.doughnut) {
            D.datasets.doughnut.cutout = D.datasets.doughnut.cutout || '64%';
        }
    }

    applyTheme();

    // Reaplica se o tema (claro/escuro) mudar, para os próximos gráficos.
    try {
        var obs = new MutationObserver(function () { applyTheme(); });
        obs.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        obs.observe(document.body, { attributes: true, attributeFilter: ['class'] });
    } catch (e) { /* ignore */ }

    /** Helpers opcionais (mesmo formato do routes.js) para uso manual em gráficos específicos. */
    window.SFCharts = {
        theme: function () {
            return { tick: themeVar('--text-secondary', '#94a3b8'), grid: themeVar('--border-color', 'rgba(148,163,184,0.18)') };
        },
        gradient: function (ctx, hex) {
            try {
                var h = (ctx.canvas && ctx.canvas.height) ? ctx.canvas.height : 240;
                var g = ctx.createLinearGradient(0, 0, 0, h);
                g.addColorStop(0, hex + '59');
                g.addColorStop(1, hex + '00');
                return g;
            } catch (e) { return hex + '22'; }
        }
    };
})();
