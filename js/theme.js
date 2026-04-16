/**
 * Theme management functionality
 */

(function() {
    const savedTheme = localStorage.getItem('theme');
    const isLight = savedTheme === 'light';
    if (isLight) {
        document.documentElement.classList.add('light-theme');
    } else {
        document.documentElement.classList.remove('light-theme');
    }
})();

document.addEventListener('DOMContentLoaded', function() {
    // Initialize theme from localStorage
    initializeTheme();
    
    // Setup theme toggle
    setupThemeToggle();
});

function applyThemeClass(isLight) {
    const classAction = isLight ? 'add' : 'remove';
    document.documentElement.classList[classAction]('light-theme');
    if (document.body) {
        document.body.classList.toggle('light-theme', isLight);
    }
    syncThemeToggleAria(isLight);
}

/**
 * Atualiza role="switch" / aria-checked no botão de tema
 */
function syncThemeToggleAria(isLight) {
    const themeToggle = document.getElementById('themeToggle');
    if (!themeToggle) {
        return;
    }
    themeToggle.setAttribute('aria-checked', isLight ? 'true' : 'false');
    themeToggle.setAttribute(
        'aria-label',
        isLight ? 'Tema claro ativo. Alternar para tema escuro.' : 'Tema escuro ativo. Alternar para tema claro.'
    );
}

/**
 * Initialize theme from saved preference
 */
function initializeTheme() {
    // Check if theme preference exists in localStorage
    const savedTheme = localStorage.getItem('theme');
    const isLight = savedTheme === 'light';
    
    if (!savedTheme) {
        localStorage.setItem('theme', 'dark');
    }
    
    applyThemeClass(isLight);

    // Add transition class after initial load (for smooth transitions later)
    setTimeout(() => {
        document.body.classList.add('theme-transition');
    }, 100);
}

/**
 * Setup theme toggle button
 */
function setupThemeToggle() {
    const themeToggle = document.getElementById('themeToggle');
    
    if (themeToggle) {
        themeToggle.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent event from bubbling up
            toggleTheme();
        });
    }
}

/**
 * Toggle between light and dark themes
 */
function toggleTheme() {
    const isLight = !document.documentElement.classList.contains('light-theme');
    applyThemeClass(isLight);
    
    // Save preference to localStorage
    const currentTheme = isLight ? 'light' : 'dark';
    localStorage.setItem('theme', currentTheme);
    
    // Update any theme-specific elements
    updateThemeSpecificElements(currentTheme);
}

/**
 * Update theme-specific elements when theme changes
 * @param {string} theme Current theme name
 */
function updateThemeSpecificElements(theme) {
    updateChartThemes(theme);
    try {
        window.dispatchEvent(new CustomEvent('sf-theme-changed', { detail: { theme: theme } }));
    } catch (e) {
        /* ignore */
    }
}

/**
 * Update chart themes when theme changes
 * @param {string} theme Current theme name
 */
function sfChartFromCanvas(el) {
    if (!el || typeof Chart === 'undefined') return null;
    if (typeof Chart.getChart === 'function') {
        try {
            const g = Chart.getChart(el);
            if (g) return g;
        } catch (e) { /* ignore */ }
    }
    if (el.chart) return el.chart;
    try {
        const c = el.getContext && el.getContext('2d');
        if (c && c.chart) return c.chart;
    } catch (e2) { /* ignore */ }
    return null;
}

function sfReadCssVar(name, fallback) {
    try {
        var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return v || fallback;
    } catch (e) {
        return fallback;
    }
}

/** Paleta alinhada a pages/bi.php (biChartPalette) após troca de tema */
function sfBiChartPalette(isLight) {
    return {
        tick: sfReadCssVar('--text-secondary', isLight ? '#64748b' : '#94a3b8'),
        grid: sfReadCssVar('--border-color', isLight ? 'rgba(15, 23, 42, 0.12)' : 'rgba(148, 163, 184, 0.22)'),
        legend: sfReadCssVar('--text-secondary', isLight ? '#64748b' : '#94a3b8'),
        tooltipBg: isLight ? 'rgba(30, 41, 59, 0.96)' : 'rgba(15, 24, 36, 0.94)',
        tooltipTitle: '#f8fafc',
        tooltipBody: '#e2e8f0'
    };
}

function applyBiChartColors(chart, isLight) {
    if (!chart || !chart.options) return;
    var pal = sfBiChartPalette(!!isLight);
    var opt = chart.options;
    function paintAxis(sc) {
        if (!sc || typeof sc !== 'object') return;
        sc.ticks = sc.ticks || {};
        sc.ticks.fontColor = pal.tick;
        sc.gridLines = sc.gridLines || {};
        if (sc.gridLines.drawOnChartArea !== false) {
            sc.gridLines.color = pal.grid;
            sc.gridLines.zeroLineColor = pal.grid;
        }
    }
    if (opt.scales && typeof opt.scales === 'object') {
        if (Array.isArray(opt.scales.xAxes)) {
            opt.scales.xAxes.forEach(paintAxis);
        }
        if (Array.isArray(opt.scales.yAxes)) {
            opt.scales.yAxes.forEach(paintAxis);
        }
    }
    if (opt.legend && opt.legend.labels) {
        opt.legend.labels.fontColor = pal.legend;
    }
    if (opt.tooltips && typeof opt.tooltips === 'object') {
        opt.tooltips.backgroundColor = pal.tooltipBg;
        opt.tooltips.titleFontColor = pal.tooltipTitle;
        opt.tooltips.bodyFontColor = pal.tooltipBody;
        opt.tooltips.borderColor = pal.grid;
    }
    try {
        chart.update();
    } catch (e3) {
        try {
            chart.update();
        } catch (e4) { /* ignore */ }
    }
}

function updateChartThemes(theme) {
    if (typeof Chart === 'undefined') return;

    const textColor = theme === 'light' ? '#1f2937' : '#f3f4f6';
    const gridColor = theme === 'light' ? 'rgba(0, 0, 0, 0.1)' : 'rgba(255, 255, 255, 0.1)';

    try {
        if (Chart.defaults.global) {
            Chart.defaults.global.defaultFontColor = textColor;
        }
        if (Chart.defaults.scale && Chart.defaults.scale.gridLines) {
            Chart.defaults.scale.gridLines.color = gridColor;
        }
        if (Chart.defaults.color !== undefined) {
            Chart.defaults.color = textColor;
        }
        if (Chart.defaults.scale && Chart.defaults.scale.grid) {
            Chart.defaults.scale.grid.color = gridColor;
        }
    } catch (e) {
        /* Chart.js version may omit some defaults */
    }

    function applyColorsToChart(chart) {
        if (!chart || !chart.options) return;
        const opt = chart.options;
        function paintAxis(sc) {
            if (!sc || typeof sc !== 'object') return;
            sc.ticks = sc.ticks || {};
            sc.ticks.fontColor = textColor;
            sc.ticks.color = textColor;
            sc.gridLines = sc.gridLines || {};
            if (sc.gridLines.drawOnChartArea !== false) {
                sc.gridLines.color = gridColor;
                sc.gridLines.zeroLineColor = gridColor;
            }
            sc.grid = sc.grid || {};
            if (sc.grid && sc.grid.display !== false) {
                sc.grid.color = gridColor;
            }
        }
        if (opt.scales && typeof opt.scales === 'object') {
            if (Array.isArray(opt.scales.yAxes)) {
                opt.scales.yAxes.forEach(paintAxis);
            }
            if (Array.isArray(opt.scales.xAxes)) {
                opt.scales.xAxes.forEach(paintAxis);
            }
            if (!Array.isArray(opt.scales.yAxes) && !Array.isArray(opt.scales.xAxes)) {
                Object.keys(opt.scales).forEach(function (key) {
                    const sc = opt.scales[key];
                    if (!sc || typeof sc !== 'object') return;
                    sc.ticks = sc.ticks || {};
                    sc.ticks.fontColor = textColor;
                    sc.ticks.color = textColor;
                    sc.grid = sc.grid || {};
                    sc.grid.color = gridColor;
                });
            }
        }
        if (opt.legend && opt.legend.labels) {
            opt.legend.labels.fontColor = textColor;
        }
        if (opt.plugins && opt.plugins.legend && opt.plugins.legend.labels) {
            opt.plugins.legend.labels.color = textColor;
        }
        try {
            /* Chart.js 2.x: update('none') vira duration inválida e pode corromper o desenho */
            if (Chart.defaults && Chart.defaults.global) {
                chart.update();
            } else {
                chart.update('none');
            }
        } catch (e3) {
            try { chart.update(); } catch (e4) { /* ignore */ }
        }
    }

    const isLightTheme = theme === 'light';

    /* BI: mesmas variáveis CSS que biChartOptions + tooltips */
    if (document.body && document.body.classList.contains('page-bi')) {
        const biIds = [
            'chartRotasTempo', 'chartFreteMensal', 'chartKmMensal', 'chartAbastTempo',
            'chartTopVeiculos', 'chartDespViagemTipos', 'chartCustoKmHist', 'chartManutPreventivaCorretiva'
        ];
        biIds.forEach(function (cid, i) {
            setTimeout(function () {
                try {
                    const el = document.getElementById(cid);
                    const ch = sfChartFromCanvas(el);
                    if (ch) applyBiChartColors(ch, isLightTheme);
                } catch (e2) { /* ignora */ }
            }, i * 72);
        });
        return;
    }

    document.querySelectorAll('canvas').forEach(function (canvas) {
        try {
            const chart = sfChartFromCanvas(canvas);
            if (chart) applyColorsToChart(chart);
        } catch (e2) {
            /* ignora canvas sem gráfico ou opções inesperadas */
        }
    });
}
