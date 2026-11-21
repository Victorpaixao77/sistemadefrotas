/**
 * Personalização de cores do sistema (tema escuro/claro).
 * Carrega cedo no body (sidebar) para evitar flash; initPalette() no header.
 */
(function (global) {
    'use strict';

    var STORAGE_KEY = 'sf-color-theme';
    var LEGACY_KEYS = [
        '--accent-primary',
        '--accent-secondary',
        '--bg-primary',
        '--bg-sidebar',
        '--card-bg',
        '--bg-secondary',
        '--bg-tertiary'
    ];

    /** Chave do input → variável CSS */
    var FIELD_MAP = {
        primaryColor: '--accent-primary',
        accentSecondary: '--accent-secondary',
        bgColor: '--bg-primary',
        sidebarColor: '--bg-sidebar',
        dashboardBgColor: '--card-bg',
        secondaryBgColor: '--bg-secondary',
        tertiaryBgColor: '--bg-tertiary'
    };

    var ALIASES = {
        '--accent-primary': ['--primary-color'],
        '--accent-secondary': []
    };

    var DEFAULTS_DARK = {
        primaryColor: '#3b82f6',
        accentSecondary: '#0ea5e9',
        bgColor: '#0f1824',
        sidebarColor: '#0f172a',
        dashboardBgColor: '#1e293b',
        secondaryBgColor: '#1a2332',
        tertiaryBgColor: '#243041'
    };

    var DEFAULTS_LIGHT = {
        primaryColor: '#3b82f6',
        accentSecondary: '#2563eb',
        bgColor: '#f3f4f6',
        sidebarColor: '#ffffff',
        dashboardBgColor: '#ffffff',
        secondaryBgColor: '#ffffff',
        tertiaryBgColor: '#e5e7eb'
    };

    var BRANCO_HYBRID = {
        primaryColor: '#3b82f6',
        accentSecondary: '#2563eb',
        bgColor: '#f3f4f6',
        sidebarColor: '#0f172a',
        dashboardBgColor: '#ffffff',
        secondaryBgColor: '#ffffff',
        tertiaryBgColor: '#e5e7eb'
    };

    var PRESETS = {
        frotec: {
            label: 'Frotec',
            dark: DEFAULTS_DARK,
            light: DEFAULTS_LIGHT
        },
        oceano: {
            label: 'Oceano',
            dark: {
                primaryColor: '#0ea5e9',
                accentSecondary: '#06b6d4',
                bgColor: '#0c1222',
                sidebarColor: '#0a1018',
                dashboardBgColor: '#152238',
                secondaryBgColor: '#111b2e',
                tertiaryBgColor: '#1a2740'
            },
            light: {
                primaryColor: '#0284c7',
                accentSecondary: '#0891b2',
                bgColor: '#f0f9ff',
                sidebarColor: '#ffffff',
                dashboardBgColor: '#ffffff',
                secondaryBgColor: '#f8fafc',
                tertiaryBgColor: '#e0f2fe'
            }
        },
        floresta: {
            label: 'Floresta',
            dark: {
                primaryColor: '#10b981',
                accentSecondary: '#34d399',
                bgColor: '#0a1410',
                sidebarColor: '#08120e',
                dashboardBgColor: '#142820',
                secondaryBgColor: '#102018',
                tertiaryBgColor: '#1a3028'
            },
            light: {
                primaryColor: '#059669',
                accentSecondary: '#10b981',
                bgColor: '#f0fdf4',
                sidebarColor: '#ffffff',
                dashboardBgColor: '#ffffff',
                secondaryBgColor: '#f8fafc',
                tertiaryBgColor: '#dcfce7'
            }
        },
        corporativo: {
            label: 'Corporativo',
            dark: {
                primaryColor: '#6366f1',
                accentSecondary: '#818cf8',
                bgColor: '#0f1020',
                sidebarColor: '#0c0d18',
                dashboardBgColor: '#1a1b2e',
                secondaryBgColor: '#141528',
                tertiaryBgColor: '#22233a'
            },
            light: {
                primaryColor: '#4f46e5',
                accentSecondary: '#6366f1',
                bgColor: '#f5f5ff',
                sidebarColor: '#ffffff',
                dashboardBgColor: '#ffffff',
                secondaryBgColor: '#fafafa',
                tertiaryBgColor: '#e8e8ff'
            }
        },
        metalico: {
            label: 'Metálico',
            dark: {
                primaryColor: '#94a3b8',
                accentSecondary: '#cbd5e1',
                bgColor: '#121418',
                sidebarColor: '#0c0e12',
                dashboardBgColor: '#1a1d24',
                secondaryBgColor: '#161920',
                tertiaryBgColor: '#252932'
            },
            light: {
                primaryColor: '#475569',
                accentSecondary: '#64748b',
                bgColor: '#e8eaee',
                sidebarColor: '#ffffff',
                dashboardBgColor: '#f4f5f7',
                secondaryBgColor: '#ffffff',
                tertiaryBgColor: '#d1d5db'
            }
        },
        branco: {
            label: 'Branco Frotec',
            hybrid: true,
            darkOnly: true,
            dark: BRANCO_HYBRID,
            light: BRANCO_HYBRID
        }
    };

    /** Presets de cor só alteram a interface no modo escuro. */
    function shouldApplyColorOverrides() {
        return !isLightTheme();
    }

    function clearColorOverrides() {
        for (var field in FIELD_MAP) {
            clearCssVar(FIELD_MAP[field]);
        }
    }

    function buildPresetColorsForMode(presetId, mode) {
        var preset = PRESETS[presetId];
        if (!preset) {
            return null;
        }
        var base = preset[mode] || preset.dark;
        if (preset.hybrid) {
            return Object.assign({}, base);
        }
        var defaults = mode === 'light' ? DEFAULTS_LIGHT : DEFAULTS_DARK;
        return Object.assign({}, defaults, base);
    }

    function isLightTheme() {
        if (global.document && global.document.documentElement.classList.contains('light-theme')) {
            return true;
        }
        try {
            return global.localStorage.getItem('theme') === 'light';
        } catch (e) {
            return false;
        }
    }

    function getDefaults() {
        return isLightTheme() ? DEFAULTS_LIGHT : DEFAULTS_DARK;
    }

    function getModeKey() {
        return isLightTheme() ? 'light' : 'dark';
    }

    function hexToRgb(hex) {
        var h = String(hex || '').replace('#', '');
        if (h.length === 3) {
            h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
        }
        if (h.length !== 6) return null;
        return {
            r: parseInt(h.slice(0, 2), 16),
            g: parseInt(h.slice(2, 4), 16),
            b: parseInt(h.slice(4, 6), 16)
        };
    }

    function setCssVar(name, value) {
        if (!global.document) return;
        global.document.documentElement.style.setProperty(name, value);
        var list = ALIASES[name];
        if (list) {
            for (var i = 0; i < list.length; i++) {
                global.document.documentElement.style.setProperty(list[i], value);
            }
        }
    }

    function clearCssVar(name) {
        if (!global.document) return;
        global.document.documentElement.style.removeProperty(name);
        var list = ALIASES[name];
        if (list) {
            for (var i = 0; i < list.length; i++) {
                global.document.documentElement.style.removeProperty(list[i]);
            }
        }
    }

    function migrateLegacyStorage() {
        var migrated = {};
        var found = false;
        for (var i = 0; i < LEGACY_KEYS.length; i++) {
            var key = LEGACY_KEYS[i];
            var val = global.localStorage.getItem(key);
            if (val) {
                found = true;
                for (var field in FIELD_MAP) {
                    if (FIELD_MAP[field] === key) {
                        migrated[field] = val;
                    }
                }
                global.localStorage.removeItem(key);
            }
        }
        if (!found) return null;
        var store = { dark: {}, light: {}, activePreset: 'custom' };
        store.dark = Object.assign({}, DEFAULTS_DARK, migrated);
        store.light = Object.assign({}, DEFAULTS_LIGHT, migrated);
        saveStore(store);
        return store;
    }

    function loadStore() {
        try {
            var raw = global.localStorage.getItem(STORAGE_KEY);
            if (raw) {
                var parsed = JSON.parse(raw);
                if (parsed && (parsed.dark || parsed.light)) {
                    return parsed;
                }
            }
        } catch (e) { /* ignore */ }
        return migrateLegacyStorage();
    }

    function saveStore(store) {
        try {
            global.localStorage.setItem(STORAGE_KEY, JSON.stringify(store));
        } catch (e) { /* ignore */ }
    }

    function syncPresetClass(presetId) {
        if (!global.document) {
            return;
        }
        var active = presetId;
        if (active === undefined || active === null) {
            var store = loadStore();
            active = store && store.activePreset ? store.activePreset : '';
        }
        var useBranco = active === 'branco' && shouldApplyColorOverrides();
        global.document.documentElement.classList.toggle('sf-color-preset-branco', useBranco);
        global.document.documentElement.classList.toggle('sf-color-palette-light-mode', !shouldApplyColorOverrides());
    }

    function isPresetDisabledInCurrentMode(presetId) {
        if (!presetId || !PRESETS[presetId]) {
            return false;
        }
        if (!shouldApplyColorOverrides() && PRESETS[presetId].darkOnly) {
            return true;
        }
        return false;
    }

    function syncPresetButtonsState() {
        if (!global.document) {
            return;
        }
        var buttons = global.document.querySelectorAll('[data-color-preset]');
        for (var i = 0; i < buttons.length; i++) {
            var id = buttons[i].getAttribute('data-color-preset');
            var presetDisabled = isPresetDisabledInCurrentMode(id);
            buttons[i].disabled = presetDisabled;
            buttons[i].classList.toggle('is-disabled', presetDisabled);
            if (presetDisabled) {
                buttons[i].setAttribute('title', 'Disponível apenas no modo escuro');
            } else if (PRESETS[id]) {
                buttons[i].setAttribute('title', PRESETS[id].label);
            }
        }
        var inputs = global.document.querySelectorAll('[data-color-key]');
        for (var j = 0; j < inputs.length; j++) {
            inputs[j].disabled = !shouldApplyColorOverrides();
        }
        var restoreBtn = global.document.getElementById('restoreDefaultColors');
        if (restoreBtn) {
            restoreBtn.disabled = !shouldApplyColorOverrides();
        }
    }

    function buildPresetColors(presetId) {
        return buildPresetColorsForMode(presetId, getModeKey());
    }

    function modeStoreHasColors(store, mode) {
        return !!(store && store[mode] && Object.keys(store[mode]).length > 0);
    }

    function getColorsForCurrentMode(store) {
        if (!shouldApplyColorOverrides()) {
            return Object.assign({}, DEFAULTS_LIGHT);
        }
        var mode = 'dark';
        var defaults = DEFAULTS_DARK;
        var presetId = store && store.activePreset ? store.activePreset : null;
        if (presetId && isHybridPreset(presetId)) {
            return buildPresetColorsForMode(presetId, 'dark') || Object.assign({}, defaults);
        }
        if (!modeStoreHasColors(store, mode)) {
            if (presetId && PRESETS[presetId]) {
                return buildPresetColorsForMode(presetId, 'dark') || Object.assign({}, defaults);
            }
            return Object.assign({}, defaults);
        }
        return Object.assign({}, defaults, store[mode]);
    }

    function isHybridPreset(presetId) {
        return !!(presetId && PRESETS[presetId] && PRESETS[presetId].hybrid);
    }

    function applyThemeColors(store, options) {
        options = options || {};
        var presetId = store && store.activePreset ? store.activePreset : null;
        if (!shouldApplyColorOverrides()) {
            clearColorOverrides();
            syncPresetClass(presetId);
            syncPresetButtonsState();
            highlightActivePreset();
            if (!options.silent) {
                notifyChange();
            }
            return;
        }
        applyColors(getColorsForCurrentMode(store), Object.assign({ presetId: presetId, clearMissing: true }, options));
        syncPresetClass(presetId);
        syncPresetButtonsState();
        highlightActivePreset();
    }

    function applyColors(colors, options) {
        options = options || {};
        var root = global.document && global.document.documentElement;
        if (!root) return;

        for (var field in FIELD_MAP) {
            var cssVar = FIELD_MAP[field];
            var value = colors[field];
            if (value) {
                setCssVar(cssVar, value);
            } else if (options.clearMissing) {
                clearCssVar(cssVar);
            }
        }

        updatePreviewStrip(colors);
        syncInputs(colors);

        if (!options.silent) {
            notifyChange();
        }
    }

    function notifyChange() {
        try {
            global.dispatchEvent(new CustomEvent('sf-theme-changed', {
                detail: { theme: isLightTheme() ? 'light' : 'dark', source: 'color-theme' }
            }));
        } catch (e) { /* ignore */ }
    }

    function updatePreviewStrip(colors) {
        var strip = global.document.getElementById('colorPreviewStrip');
        if (!strip) return;
        var order = ['primaryColor', 'accentSecondary', 'bgColor', 'sidebarColor', 'dashboardBgColor'];
        strip.innerHTML = '';
        for (var i = 0; i < order.length; i++) {
            var swatch = global.document.createElement('span');
            swatch.className = 'color-preview-swatch';
            swatch.style.backgroundColor = colors[order[i]] || '#000';
            swatch.title = order[i];
            strip.appendChild(swatch);
        }
    }

    function syncInputs(colors) {
        if (!global.document) return;
        var inputs = global.document.querySelectorAll('[data-color-key]');
        for (var i = 0; i < inputs.length; i++) {
            var key = inputs[i].getAttribute('data-color-key');
            if (key && colors[key]) {
                inputs[i].value = colors[key];
            }
        }
    }

    function persistCurrentColors(colors, presetId) {
        var store = loadStore() || { dark: {}, light: {}, activePreset: 'custom' };
        store.dark = Object.assign({}, colors);
        if (presetId) {
            store.activePreset = presetId;
        } else {
            store.activePreset = 'custom';
        }
        saveStore(store);
        syncPresetClass(store.activePreset);
        syncPresetButtonsState();
        highlightActivePreset();
    }

    function applyPreset(presetId) {
        if (!presetId || !PRESETS[presetId]) {
            return;
        }
        if (isPresetDisabledInCurrentMode(presetId)) {
            return;
        }
        var colors = buildPresetColorsForMode(presetId, 'dark');
        if (!colors) {
            return;
        }
        if (!shouldApplyColorOverrides()) {
            var store = loadStore() || { dark: {}, light: {}, activePreset: 'frotec' };
            store.activePreset = presetId;
            store.dark = Object.assign({}, colors);
            saveStore(store);
            clearColorOverrides();
            syncPresetClass(presetId);
            syncPresetButtonsState();
            highlightActivePreset();
            updatePreviewStrip(colors);
            syncInputs(colors);
            return;
        }
        applyColors(colors, { presetId: presetId, clearMissing: true });
        persistCurrentColors(colors, presetId);
    }

    function restoreDefaults() {
        var store = loadStore() || { dark: {}, light: {}, activePreset: 'frotec' };
        delete store.dark;
        store.activePreset = 'frotec';
        saveStore(store);
        if (shouldApplyColorOverrides()) {
            applyColors(DEFAULTS_DARK, { clearMissing: true, presetId: 'frotec' });
        } else {
            clearColorOverrides();
        }
        syncPresetClass('frotec');
        syncPresetButtonsState();
        highlightActivePreset();
        for (var i = 0; i < LEGACY_KEYS.length; i++) {
            global.localStorage.removeItem(LEGACY_KEYS[i]);
        }
    }

    function highlightActivePreset() {
        var store = loadStore();
        var active = store && store.activePreset ? store.activePreset : 'frotec';
        var buttons = global.document.querySelectorAll('[data-color-preset]');
        for (var i = 0; i < buttons.length; i++) {
            var id = buttons[i].getAttribute('data-color-preset');
            buttons[i].classList.toggle('is-active', id === active);
        }
    }

    function onColorInputChange(input) {
        if (!shouldApplyColorOverrides()) {
            return;
        }
        var key = input.getAttribute('data-color-key');
        if (!key || !FIELD_MAP[key]) return;
        var store = loadStore() || { dark: {}, light: {}, activePreset: 'custom' };
        var colors = getColorsForCurrentMode(store);
        colors[key] = input.value;
        applyColors(colors);
        persistCurrentColors(colors, null);
    }

    function bindPresetClicks() {
        var wrap = global.document && global.document.getElementById('colorPresets');
        if (!wrap || wrap.dataset.sfPresetBound === '1') {
            return !!wrap;
        }
        wrap.dataset.sfPresetBound = '1';
        wrap.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-color-preset]');
            if (!btn) {
                return;
            }
            e.preventDefault();
            applyPreset(btn.getAttribute('data-color-preset'));
        });
        return true;
    }

    function initPalette() {
        bindPresetClicks();

        if (global.__sfColorPaletteInited) {
            var storeEarly = loadStore();
            applyThemeColors(storeEarly, { silent: true });
            return;
        }
        global.__sfColorPaletteInited = true;

        var store = loadStore();
        applyThemeColors(store, { silent: true });

        var inputs = global.document.querySelectorAll('[data-color-key]');
        for (var i = 0; i < inputs.length; i++) {
            inputs[i].addEventListener('input', function () {
                onColorInputChange(this);
            });
        }

        var restoreBtn = global.document.getElementById('restoreDefaultColors');
        if (restoreBtn) {
            restoreBtn.addEventListener('click', function (e) {
                e.preventDefault();
                restoreDefaults();
            });
        }

        highlightActivePreset();
    }

    function onThemeModeChange() {
        var store = loadStore();
        global.requestAnimationFrame(function () {
            applyThemeColors(store, { silent: true });
        });
    }

    function bootstrap() {
        if (!global.document) return;
        var store = loadStore();
        applyThemeColors(store, { silent: true });
    }

    global.SFColorTheme = {
        initPalette: initPalette,
        applyPreset: applyPreset,
        restoreDefaults: restoreDefaults,
        onThemeModeChange: onThemeModeChange,
        getDefaults: getDefaults,
        PRESETS: PRESETS,
        FIELD_MAP: FIELD_MAP
    };

    bootstrap();
})(typeof window !== 'undefined' ? window : this);
