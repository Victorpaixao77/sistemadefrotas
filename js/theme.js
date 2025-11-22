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
    // Update charts if they exist
    updateChartThemes(theme);
    
    // Update other theme-specific elements if needed
    // ...
}

/**
 * Update chart themes when theme changes
 * @param {string} theme Current theme name
 */
function updateChartThemes(theme) {
    if (typeof Chart === 'undefined') return;
    
    // Define colors based on theme
    const textColor = theme === 'light' ? '#1f2937' : '#f3f4f6';
    const gridColor = theme === 'light' ? 'rgba(0, 0, 0, 0.1)' : 'rgba(255, 255, 255, 0.1)';
    
    // Update Chart.js defaults
    Chart.defaults.color = textColor;
    Chart.defaults.scale.grid.color = gridColor;
    
    // Update existing charts
    if (Chart.instances) {
        Object.values(Chart.instances).forEach(chart => {
            // Update chart options
            if (chart.options.scales && chart.options.scales.x) {
                chart.options.scales.x.ticks.color = textColor;
                chart.options.scales.x.grid.color = gridColor;
            }
            
            if (chart.options.scales && chart.options.scales.y) {
                chart.options.scales.y.ticks.color = textColor;
                chart.options.scales.y.grid.color = gridColor;
            }
            
            // Update the chart
            chart.update();
        });
    }
}
