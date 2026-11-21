/**
 * GipStagePremium — planta técnica ortográfica (vista superior).
 * Proporções de engenharia; camadas independentes; pneus como elemento principal.
 */
(function (global) {
    'use strict';

    function f(n, d) { return Number(n).toFixed(d == null ? 1 : d); }

    function pathRoundRect(x, y, w, h, r) {
        r = Math.min(r || 5, w / 2, h / 2);
        return 'M' + f(x + r) + ' ' + f(y) +
            ' h' + f(w - 2 * r) +
            ' q' + f(r) + ' 0 ' + f(r) + ' ' + f(r) +
            ' v' + f(h - 2 * r) +
            ' q0 ' + f(r) + ' ' + f(-r) + ' ' + f(r) +
            ' h' + f(-(w - 2 * r)) +
            ' q' + f(-r) + ' 0 ' + f(-r) + ' ' + f(-r) +
            ' v' + f(-(h - 2 * r)) +
            ' q0 ' + f(-r) + ' ' + f(r) + ' ' + f(-r) + ' Z';
    }

    function pathEllipse(cx, cy, rx, ry) {
        return 'M' + f(cx - rx) + ' ' + f(cy) +
            ' a' + f(rx) + ' ' + f(ry) + ' 0 1 1 ' + f(rx * 2) + ' 0' +
            ' a' + f(rx) + ' ' + f(ry) + ' 0 1 1 ' + f(-rx * 2) + ' 0';
    }

    /* ═══════════════════ DEFS ═══════════════════ */
    function drawDefs() {
        var s = ['<defs>'];

        s.push('<pattern id="gspBrushed" width="4" height="4" patternUnits="userSpaceOnUse" patternTransform="rotate(6)">');
        s.push('<line x1="0" y1="0" x2="0" y2="4" stroke="rgba(255,255,255,.07)" stroke-width=".6"/>');
        s.push('<line x1="2" y1="0" x2="2" y2="4" stroke="rgba(0,0,0,.06)" stroke-width=".4"/>');
        s.push('</pattern>');
        s.push('<pattern id="gspRubberGrain" width="5" height="5" patternUnits="userSpaceOnUse">');
        s.push('<circle cx="2.5" cy="2.5" r=".35" fill="rgba(0,0,0,.12)"/>');
        s.push('</pattern>');
        s.push('<pattern id="gspTreadBlock" width="8" height="6" patternUnits="userSpaceOnUse">');
        s.push('<rect x="0" y="1" width="8" height="4" fill="rgba(0,0,0,.22)"/>');
        s.push('<rect x="1" y="2" width="6" height="2" fill="rgba(255,255,255,.03)"/>');
        s.push('</pattern>');
        s.push('<pattern id="gspFloorL" width="40" height="40" patternUnits="userSpaceOnUse">');
        s.push('<path d="M0 40 L40 0 M-10 10 L10 -10 M30 50 L50 30" stroke="#b8c4d0" stroke-width=".4" fill="#e4e9ef"/>');
        s.push('</pattern>');
        s.push('<pattern id="gspFloorD" width="40" height="40" patternUnits="userSpaceOnUse">');
        s.push('<path d="M0 40 L40 0 M-10 10 L10 -10 M30 50 L50 30" stroke="#1a2430" stroke-width=".4" fill="#0f141c"/>');
        s.push('</pattern>');
        s.push('<pattern id="gspPerforated" width="6" height="6" patternUnits="userSpaceOnUse">');
        s.push('<circle cx="3" cy="3" r=".8" fill="rgba(0,0,0,.2)"/>');
        s.push('</pattern>');

        var grads = [
            ['gspRail', 'linear', '0,0,0,1', '0:#4a5561,.35:#2f363e,.65:#252b31,1:#1a1f24'],
            ['gspSteelH', 'linear', '0,0,1,0', '0:#5c6670,.4:#3d4650,.7:#6b7580,1:#2a3139'],
            ['gspSteelR', 'radial', '.45,.4,.65', '0:#8d97a1,.35:#5c6670,.7:#3d4650,1:#1c2228'],
            ['gspAlu', 'linear', '0,0,1,1', '0:#f2f6fa,.3:#c8d4e0,.55:#9aadc0,.8:#d0dae4,1:#6a7d90'],
            ['gspAluTank', 'linear', '0,0,1,0', '0:#eef3f8,.25:#b0c0d0,.5:#7a90a4,.75:#c5d0dc,1:#5a6d7d'],
            ['gspIron', 'linear', '0,0,1,0', '0:#4a5560,.5:#2a3139,1:#1a1f24'],
            ['gspRubSide', 'linear', '0,0,0,1', '0:#2a3138,.5:#14181d,.8:#1e242a,1:#353c44'],
            ['gspRubTread', 'linear', '0,0,0,1', '0:#1a1f24,.45:#0a0c0f,.55:#0a0c0f,.8:#1a1f24,1:#252b31'],
            ['gspGlass', 'linear', '0,0,1,1', '0:#b8d4ec,.5:#5a8ab0,1:#2e5070'],
            ['gspGlassHi', 'linear', '0,0,0,1', '0:#ffffff60,1:#ffffff00'],
            ['gspCab', 'linear', '0,0,1,1', '0:#f0f3f7,.35:#d4dce6,.65:#b0bcc8,1:#8894a0'],
            ['gspCabTrans', 'linear', '0,0,1,1', '0:#e8edf2,.5:#b8c4d0,1:#98a8b6'],
            ['gspCabRoof', 'linear', '0,0,1,0', '0:#e8edf3,.5:#c5ced8,1:#9aa8b6'],
            ['gspBlack', 'linear', '0,0,0,1', '0:#2a3139,.5:#14181d,1:#080a0c'],
            ['gspDiff', 'radial', '.5,.45,.75', '0:#3d4650,.4:#252b31,.75:#1a1f24,1:#0a0c0f'],
            ['gspExhaust', 'linear', '0,0,1,0', '0:#5c6670,.5:#3d4650,1:#252b31']
        ];
        grads.forEach(function (g) {
            var id = g[0], type = g[1], coords = g[2].split(','), stops = g[3].split(',');
            if (type === 'radial') {
                s.push('<radialGradient id="' + id + '" cx="' + coords[0] + '" cy="' + coords[1] + '" r="' + coords[2] + '">');
            } else {
                s.push('<linearGradient id="' + id + '" x1="' + coords[0] + '" y1="' + coords[1] + '" x2="' + coords[2] + '" y2="' + coords[3] + '">');
            }
            stops.forEach(function (st) {
                var p = st.split(':');
                s.push('<stop offset="' + p[0] + '" stop-color="' + p[1] + '"/>');
            });
            s.push('</' + (type === 'radial' ? 'radial' : 'linear') + 'Gradient>');
        });

        s.push('<linearGradient id="gspTireFaceV" x1="0" y1="0" x2="0" y2="1">');
        s.push('<stop offset="0" stop-color="#2a3138"/><stop offset="0.1" stop-color="#1a1f24"/>');
        s.push('<stop offset="0.35" stop-color="#0a0c0f"/><stop offset="0.5" stop-color="#14181d"/>');
        s.push('<stop offset="0.65" stop-color="#0a0c0f"/><stop offset="0.9" stop-color="#1a1f24"/><stop offset="1" stop-color="#2a3138"/>');
        s.push('</linearGradient>');
        s.push('<linearGradient id="gspTireFaceHi" x1="0" y1="0" x2="0" y2="1">');
        s.push('<stop offset="0" stop-color="#ffffff10"/><stop offset="0.5" stop-color="#ffffff04"/><stop offset="1" stop-color="#00000012"/>');
        s.push('</linearGradient>');

        s.push('<filter id="gipChipSh" x="-30%" y="-40%" width="160%" height="180%">');
        s.push('<feDropShadow dx="0" dy="1.5" stdDeviation="2" flood-color="#000" flood-opacity=".35"/>');
        s.push('</filter>');
        s.push('<filter id="gspAO" x="-12%" y="-12%" width="124%" height="124%">');
        s.push('<feGaussianBlur in="SourceAlpha" stdDeviation="1.2" result="b"/>');
        s.push('<feOffset dx="0" dy=".8" result="o"/>');
        s.push('<feFlood flood-color="#000" flood-opacity=".18"/>');
        s.push('<feComposite in2="o" operator="in" result="s"/>');
        s.push('<feComposite in="SourceGraphic" in2="s" operator="arithmetic" k1="0" k2="1" k3=".28" k4="0"/>');
        s.push('</filter>');
        s.push('<filter id="gspDrop" x="-40%" y="-40%" width="180%" height="180%">');
        s.push('<feDropShadow dx="0" dy="1" stdDeviation="1.5" flood-color="#000" flood-opacity=".22"/>');
        s.push('</filter>');
        s.push('<filter id="gspInner" x="-40%" y="-40%" width="180%" height="180%">');
        s.push('<feDropShadow dx="0" dy=".8" stdDeviation="1" flood-color="#000" flood-opacity=".3"/>');
        s.push('</filter>');
        s.push('<filter id="gspTireSh" x="-50%" y="-50%" width="200%" height="200%">');
        s.push('<feDropShadow dx="0" dy="2" stdDeviation="2.5" flood-color="#000" flood-opacity=".42"/>');
        s.push('</filter>');
        s.push('<filter id="gspMatte" x="-15%" y="-15%" width="130%" height="130%">');
        s.push('<feGaussianBlur in="SourceAlpha" stdDeviation=".6" result="b"/>');
        s.push('<feSpecularLighting in="b" surfaceScale="1" specularConstant=".18" specularExponent="10" lighting-color="#fff" result="s">');
        s.push('<feDistantLight azimuth="235" elevation="42"/>');
        s.push('</feSpecularLighting>');
        s.push('<feComposite in="s" in2="SourceGraphic" operator="in" result="hl"/>');
        s.push('<feComposite in="SourceGraphic" in2="hl" operator="arithmetic" k1="0" k2="1" k3=".12" k4="0"/>');
        s.push('</filter>');
        s.push('</defs>');
        return s.join('');
    }

    /* ═══════════════════ DETALHES (escala reduzida) ═══════════════════ */
    function drawBolts(cx, cy, count, radius, size) {
        var out = '';
        var hs = size || 0.9;
        for (var i = 0; i < count; i++) {
            var a = (Math.PI * 2 / count) * i - Math.PI / 2;
            var bx = cx + Math.cos(a) * radius;
            var by = cy + Math.sin(a) * radius;
            out += '<circle cx="' + f(bx) + '" cy="' + f(by) + '" r="' + f(hs) + '" fill="#1a1f24" stroke="#080a0c" stroke-width=".2"/>';
        }
        return out;
    }

    function drawFrameRivets(x1, x2, y, step) {
        var s = '', st = step || 28;
        for (var x = x1 + 14; x < x2 - 8; x += st) {
            s += '<circle cx="' + f(x) + '" cy="' + f(y) + '" r=".55" fill="#6b7580" stroke="#3d4650" stroke-width=".2"/>';
        }
        return s;
    }

    function drawShadow(w, h) {
        return '<g class="gsp-shadow" opacity=".22"><ellipse cx="' + f(w / 2) + '" cy="' + f(h - 10) + '" rx="' + f(w * .42) + '" ry="10" fill="#000"/></g>';
    }

    function drawFloor(w, h, dark) {
        return '<path d="M0 0 h' + w + ' v' + h + ' h-' + w + ' Z" fill="url(#' + (dark ? 'gspFloorD' : 'gspFloorL') + ')"/>';
    }

    /* ═══════════════════ LONGARINAS (largas, grafite) ═══════════════════ */
    function drawRail(x1, x2, y, t) {
        t = t || 18;
        var h = t / 2;
        return [
            '<g class="gsp-rail">',
            '<path d="M' + f(x1) + ' ' + f(y - h) + ' L' + f(x2) + ' ' + f(y - h) +
            ' L' + f(x2) + ' ' + f(y + h) + ' L' + f(x1) + ' ' + f(y + h) + ' Z" fill="url(#gspRail)" stroke="#1a1f24" stroke-width=".9"/>',
            '<path d="M' + f(x1 + 6) + ' ' + f(y - h + 4) + ' L' + f(x2 - 6) + ' ' + f(y - h + 4) +
            ' L' + f(x2 - 6) + ' ' + f(y + h - 4) + ' L' + f(x1 + 6) + ' ' + f(y + h - 4) + ' Z" fill="url(#gspSteelH)" opacity=".45"/>',
            '<path fill="url(#gspBrushed)" d="M' + f(x1 + 8) + ' ' + f(y - h + 5) + ' L' + f(x2 - 8) + ' ' + f(y - h + 5) +
            ' L' + f(x2 - 8) + ' ' + f(y + h - 5) + ' L' + f(x1 + 8) + ' ' + f(y + h - 5) + ' Z" opacity=".2"/>',
            drawFrameRivets(x1, x2, y - h + 2, 32),
            drawFrameRivets(x1, x2, y + h - 2, 32),
            '</g>'
        ].join('');
    }

    function drawFrame(x1, x2, yT, yB, t) {
        return drawRail(x1, x2, yT, t) + drawRail(x1, x2, yB, t);
    }

    function drawRearFrame(x1, x2, cy, t) {
        var yT = cy - 14, yB = cy + 14;
        return drawRail(x1, x2, yT, t * .7) + drawRail(x1, x2, yB, t * .7) +
            drawCrossMembers([x1, (x1 + x2) / 2, x2], yT, yB);
    }

    function drawCrossMembers(xs, yT, yB) {
        var s = '<g class="gsp-cross" opacity=".9">';
        for (var i = 0; i < xs.length - 1; i++) {
            var x0 = xs[i], x1 = xs[i + 1];
            var mx = (x0 + x1) / 2;
            var w = Math.min(14, (x1 - x0) * 0.12);
            s += '<path d="M' + f(mx - w / 2) + ' ' + f(yT + 3) + ' L' + f(mx + w / 2) + ' ' + f(yT + 3) +
                ' L' + f(mx + w / 2 - 2) + ' ' + f(yB - 3) + ' L' + f(mx - w / 2 + 2) + ' ' + f(yB - 3) + ' Z" fill="url(#gspSteelH)" stroke="#2a3139" stroke-width=".6"/>';
            s += '<path d="M' + f(x0 + 16) + ' ' + f(yT + 6) + ' L' + f(x1 - 16) + ' ' + f(yB - 6) +
                '" stroke="#4a5560" stroke-width="1.2" fill="none" opacity=".4"/>';
            s += '<path d="M' + f(x0 + 16) + ' ' + f(yB - 6) + ' L' + f(x1 - 16) + ' ' + f(yT + 6) +
                '" stroke="#4a5560" stroke-width="1.2" fill="none" opacity=".4"/>';
        }
        return s + '</g>';
    }

    function drawBracket(x, y, flip) {
        var d = flip ? -1 : 1;
        return '<path d="M' + f(x) + ' ' + f(y) + ' l' + f(7 * d) + ' -5 l1.5 1.5 l' + f(-3 * d) + ' 4 l' + f(4 * d) + ' 0 l' + f(-2 * d) + ' 4 l' + f(-8 * d) + ' -4 Z" fill="url(#gspSteelR)" stroke="#2a3139" stroke-width=".45"/>';
    }

    function drawBrackets(cx, yT, yB) {
        return drawBracket(cx - 11, yT - 1, false) + drawBracket(cx - 11, yB + 1, true) +
            drawBracket(cx + 11, yT - 1, false) + drawBracket(cx + 11, yB + 1, true);
    }

    /* Cabine Volvo FH / Scania — estreita, longa, sobre o 1º eixo */
    function drawCabSteps(x, y, h, cy, steerX, tireAlong) {
        var sx = steerX - tireAlong * 0.15;
        var parts = '<g class="gsp-steps" opacity=".75">';
        for (var s = 0; s < 3; s++) {
            var sy = cy - 14 + s * 11;
            parts += '<path d="M' + f(x + 4) + ' ' + f(sy) + ' l-7 0 l-2 4 l7 0 Z" fill="url(#gspSteelH)" stroke="#3d4650" stroke-width=".4"/>';
            parts += '<path d="M' + f(x + 4) + ' ' + f(cy + 14 - s * 11) + ' l-7 0 l-2 -4 l7 0 Z" fill="url(#gspSteelH)" stroke="#3d4650" stroke-width=".4"/>';
        }
        parts += '<path d="M' + f(sx) + ' ' + f(y + h - 18) + ' l-5 0 l0 14 l5 -3 Z M' + f(sx) + ' ' + f(y + 18) + ' l-5 0 l0 -14 l5 3 Z" fill="url(#gspIron)" stroke="#2a3139" stroke-width=".4"/>';
        return parts + '</g>';
    }

    function cabBoundsFromSteer(slots, steerX, tireAlong, cabWMin) {
        var x0 = Infinity, x1 = -Infinity, y0 = Infinity, y1 = -Infinity;
        slots.forEach(function (s) {
            x0 = Math.min(x0, s.x);
            x1 = Math.max(x1, s.x + s.w);
            y0 = Math.min(y0, s.y);
            y1 = Math.max(y1, s.y + s.h);
        });
        var mirrorPad = 14, padY = 10 + mirrorPad, padFront = 56, padRear = 6;
        var x = x0 - padFront;
        var backX = Math.max(x1 + padRear, steerX + tireAlong * 0.30);
        return {
            x: x,
            w: Math.max(cabWMin || 88, backX - x),
            y: y0 - padY,
            h: (y1 - y0) + padY * 2,
            cy: (y0 + y1) / 2
        };
    }

    /* Cabine do cavalo — vista superior, desenho técnico premium (frente à esquerda). */
    function drawCab(x, cy, w, opts) {
        opts = opts || {};
        var overhang = opts.overhang || 0;
        var bounds;
        if (opts.steerSlots && opts.steerSlots.length && opts.steerX != null) {
            bounds = cabBoundsFromSteer(opts.steerSlots, opts.steerX, opts.tireAlong || 58, w);
            x = bounds.x;
            w = bounds.w;
            cy = bounds.cy;
            overhang = 0;
        }
        w = w + overhang;
        var h = bounds ? bounds.h : Math.round(w * 0.66);
        var y = bounds ? bounds.y : cy - h / 2, yB = bounds ? bounds.y + h : cy + h / 2;
        var op = opts.bodyOpacity != null ? opts.bodyOpacity : 0.95;
        var overWheels = !!(opts.steerSlots && opts.steerSlots.length);
        var noseR = h * 0.44, backX = x + w;
        var hoodEnd = x + w * 0.36, windEnd = x + w * 0.54;
        var P = ['<g class="gsp-cab" pointer-events="none">'];

        /* Faixa translúcida sobre os pneus direcionais (cabine por cima das rodas). */
        if (overWheels) {
            var fx = Infinity, fy = Infinity, fx1 = -Infinity, fy1 = -Infinity;
            opts.steerSlots.forEach(function (s) {
                fx = Math.min(fx, s.x);
                fy = Math.min(fy, s.y);
                fx1 = Math.max(fx1, s.x + s.w);
                fy1 = Math.max(fy1, s.y + s.h);
            });
            var fw = fx1 - fx, fh = fy1 - fy;
            P.push('<path d="' + pathRoundRect(fx - 2, fy - 2, fw + 4, fh + 4, Math.min(6, fw * 0.08)) +
                '" fill="url(#gspCabTrans)" fill-opacity=".12" stroke="#9aa8b6" stroke-width=".55" stroke-opacity=".45"/>');
            if (bounds) {
                P.push('<path d="' + pathRoundRect(x + 1, y + 1, w - 2, h - 2, Math.min(10, h * 0.07)) +
                    '" fill="url(#gspCabTrans)" fill-opacity=".08" stroke="none"/>');
            }
        }

        /* ── Carroceria (silhueta) com sombra suave — nariz arredondado à frente ── */
        var shell = 'M' + f(x + noseR) + ' ' + f(y)
            + ' L' + f(backX - 5) + ' ' + f(y + 2)
            + ' Q' + f(backX) + ' ' + f(y + 5) + ' ' + f(backX) + ' ' + f(y + 12)
            + ' L' + f(backX) + ' ' + f(yB - 12)
            + ' Q' + f(backX) + ' ' + f(yB - 5) + ' ' + f(backX - 5) + ' ' + f(yB)
            + ' L' + f(x + noseR) + ' ' + f(yB)
            + ' Q' + f(x) + ' ' + f(cy) + ' ' + f(x + noseR) + ' ' + f(y) + ' Z';
        P.push('<path class="gsp-cab-body" d="' + shell + '" fill="url(#' + (overWheels ? 'gspCabTrans' : 'gspCab') + ')" fill-opacity="' + op + '" stroke="#6b7580" stroke-width="' + (overWheels ? '.85' : '1.2') + '" stroke-opacity="' + (overWheels ? '.55' : '1') + '"' + (overWheels ? '' : ' filter="url(#gspDrop)"') + '/>');
        P.push('<path class="gsp-cab-outline" d="' + shell + '" fill="none" stroke="rgba(255,255,255,' + (overWheels ? '.14' : '.22') + ')" stroke-width=".65"/>');

        /* ── Capô frontal com vincos + buzina ── */
        var hood = 'M' + f(x + noseR * 0.55) + ' ' + f(y + 6)
            + ' L' + f(hoodEnd) + ' ' + f(y + 5) + ' L' + f(hoodEnd) + ' ' + f(yB - 5)
            + ' L' + f(x + noseR * 0.55) + ' ' + f(yB - 6)
            + ' Q' + f(x + 5) + ' ' + f(cy) + ' ' + f(x + noseR * 0.55) + ' ' + f(y + 6) + ' Z';
        P.push('<path d="' + hood + '" fill="url(#gspCabRoof)" fill-opacity="' + (overWheels ? '.14' : '.3') + '" stroke="#6b7580" stroke-width=".45" stroke-opacity=".5"/>');
        P.push('<line x1="' + f(x + noseR * 0.5) + '" y1="' + f(cy) + '" x2="' + f(hoodEnd) + '" y2="' + f(cy) + '" stroke="rgba(0,0,0,.22)" stroke-width=".9"/>');
        P.push('<line x1="' + f(x + noseR * 0.6) + '" y1="' + f(cy - h * 0.2) + '" x2="' + f(hoodEnd) + '" y2="' + f(cy - h * 0.2) + '" stroke="rgba(255,255,255,.16)" stroke-width=".7"/>');
        P.push('<line x1="' + f(x + noseR * 0.6) + '" y1="' + f(cy + h * 0.2) + '" x2="' + f(hoodEnd) + '" y2="' + f(cy + h * 0.2) + '" stroke="rgba(255,255,255,.16)" stroke-width=".7"/>');
        P.push('<circle cx="' + f(x + noseR * 0.72) + '" cy="' + f(cy - h * 0.1) + '" r="1.6" fill="url(#gspSteelR)" stroke="#3d4650" stroke-width=".35"/>');
        P.push('<circle cx="' + f(x + noseR * 0.72) + '" cy="' + f(cy + h * 0.1) + '" r="1.6" fill="url(#gspSteelR)" stroke="#3d4650" stroke-width=".35"/>');

        /* ── Quebra-sol (lip frontal do teto) ── */
        P.push('<rect x="' + f(hoodEnd - 3) + '" y="' + f(y + 3) + '" width="6" height="' + f(h - 6) + '" rx="2.5" fill="url(#gspSteelH)" stroke="#3d4650" stroke-width=".5"/>');
        P.push('<line x1="' + f(hoodEnd) + '" y1="' + f(y + 6) + '" x2="' + f(hoodEnd) + '" y2="' + f(yB - 6) + '" stroke="rgba(255,255,255,.25)" stroke-width=".5"/>');

        /* ── Para-brisa (vidro dividido) ── */
        var wsX0 = hoodEnd + 3, wsX1 = windEnd, wsMid = (wsX0 + wsX1) / 2;
        P.push('<path d="M' + f(wsX0) + ' ' + f(y + 7) + ' L' + f(wsX1) + ' ' + f(y + 8) + ' L' + f(wsX1) + ' ' + f(yB - 8) + ' L' + f(wsX0) + ' ' + f(yB - 7) + ' Z" fill="url(#gspGlass)" fill-opacity="' + (overWheels ? '.28' : '.42') + '" stroke="#3d556c" stroke-width=".5" stroke-opacity=".55"/>');
        P.push('<line x1="' + f(wsMid) + '" y1="' + f(y + 8) + '" x2="' + f(wsMid) + '" y2="' + f(yB - 8) + '" stroke="rgba(0,0,0,.18)" stroke-width=".7"/>');
        P.push('<path fill="url(#gspGlassHi)" opacity=".5" d="M' + f(wsX0 + 1) + ' ' + f(y + 9) + ' L' + f(wsMid - 1) + ' ' + f(y + 10) + ' L' + f(wsMid - 1) + ' ' + f(cy - 2) + ' L' + f(wsX0 + 1) + ' ' + f(cy - 4) + ' Z"/>');

        /* ── Teto traseiro: relevo central + entradas de ar + costura ── */
        var rfX0 = windEnd + 2;
        P.push('<path d="M' + f(rfX0) + ' ' + f(y + 6) + ' L' + f(backX - 8) + ' ' + f(y + 7) + ' L' + f(backX - 8) + ' ' + f(yB - 7) + ' L' + f(rfX0) + ' ' + f(yB - 6) + ' Z" fill="url(#gspCabRoof)" fill-opacity="' + (overWheels ? '.16' : '.34') + '" stroke="#6b7580" stroke-width=".45" stroke-opacity=".5"/>');
        P.push('<line x1="' + f(rfX0 + 3) + '" y1="' + f(cy) + '" x2="' + f(backX - 10) + '" y2="' + f(cy) + '" stroke="rgba(255,255,255,.18)" stroke-width="1.1"/>');
        var aiX = rfX0 + (backX - rfX0) * 0.18, aiW = (backX - rfX0) * 0.5;
        P.push('<rect x="' + f(aiX) + '" y="' + f(cy - h * 0.17) + '" width="' + f(aiW) + '" height="4.5" rx="2" fill="url(#gspPerforated)" stroke="#5a6570" stroke-width=".4"/>');
        P.push('<rect x="' + f(aiX) + '" y="' + f(cy + h * 0.17 - 4.5) + '" width="' + f(aiW) + '" height="4.5" rx="2" fill="url(#gspPerforated)" stroke="#5a6570" stroke-width=".4"/>');
        P.push('<line x1="' + f(backX - 12) + '" y1="' + f(y + 9) + '" x2="' + f(backX - 12) + '" y2="' + f(yB - 9) + '" stroke="rgba(0,0,0,.16)" stroke-width=".6"/>');

        /* ── Costuras das laterais ── */
        P.push('<path d="M' + f(hoodEnd) + ' ' + f(y + 4) + ' L' + f(backX - 6) + ' ' + f(y + 5) + '" stroke="rgba(0,0,0,.14)" stroke-width=".5"/>');
        P.push('<path d="M' + f(hoodEnd) + ' ' + f(yB - 4) + ' L' + f(backX - 6) + ' ' + f(yB - 5) + '" stroke="rgba(0,0,0,.14)" stroke-width=".5"/>');

        /* ── Retrovisores (dentro da silhueta da cabine) ── */
        var mx = hoodEnd + 2;
        [-1, 1].forEach(function (sd) {
            var inset = 5;
            var ey = sd < 0 ? y + inset : yB - inset;
            var tipY = ey + sd * 9;
            P.push('<path d="M' + f(mx - 1) + ' ' + f(ey) + ' L' + f(mx - 4) + ' ' + f(tipY) + ' L' + f(mx + 9) + ' ' + f(tipY) + ' L' + f(mx + 11) + ' ' + f(ey) + ' Z" fill="url(#gspAlu)" fill-opacity="' + (overWheels ? '.55' : '1') + '" stroke="#4a5561" stroke-width=".45" stroke-opacity=".6"/>');
            P.push('<rect x="' + f(mx - 2) + '" y="' + f(sd < 0 ? tipY - 1 : tipY - 4) + '" width="11" height="4.5" rx="1" fill="url(#gspGlass)" fill-opacity="' + (overWheels ? '.5' : '.82') + '" stroke="#3d556c" stroke-width=".35" stroke-opacity=".55"/>');
        });

        /* ── Escadas laterais (contorno superior), perto da traseira ── */
        var sx = x + w * 0.66;
        [-1, 1].forEach(function (sd) {
            var ey = sd < 0 ? y : yB;
            P.push('<path d="M' + f(sx) + ' ' + f(ey + sd * 1) + ' l16 0 l-2 ' + f(sd * 5) + ' l-12 0 Z" fill="url(#gspSteelH)" fill-opacity="' + (overWheels ? '.38' : '.72') + '" stroke="#3d4650" stroke-width=".4" stroke-opacity=".55"/>');
            P.push('<line x1="' + f(sx + 4) + '" y1="' + f(ey + sd * 3) + '" x2="' + f(sx + 12) + '" y2="' + f(ey + sd * 3) + '" stroke="rgba(255,255,255,.18)" stroke-width=".4"/>');
        });

        P.push('</g>');
        return P.join('');
    }

    function drawBattery(x, y) {
        return [
            '<g class="gsp-battery" opacity=".9">',
            '<rect x="' + f(x) + '" y="' + f(y) + '" width="24" height="14" rx="1.5" fill="url(#gspSteelH)" stroke="#2a3139" stroke-width=".55"/>',
            '<rect x="' + f(x + 2) + '" y="' + f(y + 2) + '" width="20" height="10" rx="1" fill="url(#gspBlack)" opacity=".75"/>',
            '<path d="M' + f(x + 3) + ' ' + f(y - 2) + ' h5 v2.5 M' + f(x + 16) + ' ' + f(y - 2) + ' h5 v2.5" stroke="#252b31" stroke-width=".7"/>',
            drawBolts(x + 12, y + 7, 4, 6, .55),
            '</g>'
        ].join('');
    }

    /** Tanque lateral — alongado no chassi, entre longarina e pneu (nunca sobre o eixo). */
    function drawFuelTank(cx, tankCy, len, bodyH) {
        bodyH = bodyH || 18;
        var capR = bodyH * 0.5;
        var L = len;
        var x = cx - L / 2;
        var ty = tankCy - bodyH / 2;
        var s = [
            '<g class="gsp-fuel" filter="url(#gspDrop)" pointer-events="none">',
            '<path d="M' + f(x + capR) + ' ' + f(ty) +
            ' L' + f(x + L - capR) + ' ' + f(ty) +
            ' a' + f(capR) + ' ' + f(bodyH / 2) + ' 0 0 1 0 ' + f(bodyH) +
            ' L' + f(x + capR) + ' ' + f(ty + bodyH) +
            ' a' + f(capR) + ' ' + f(bodyH / 2) + ' 0 0 1 0 ' + f(-bodyH) + ' Z" fill="url(#gspAluTank)" stroke="#5a6d7d" stroke-width=".7"/>',
            '<path d="M' + f(x + capR + 3) + ' ' + f(ty + 1.5) + ' L' + f(x + L - capR - 3) + ' ' + f(ty + 1.5) +
            ' L' + f(x + L - capR - 4) + ' ' + f(ty + bodyH - 1.5) + ' L' + f(x + capR + 4) + ' ' + f(ty + bodyH - 1.5) + ' Z" fill="url(#gspBrushed)" opacity=".28"/>',
            '<path d="M' + f(x + L * .32) + ' ' + f(ty + 1) + ' L' + f(x + L * .35) + ' ' + f(ty + bodyH - 1) +
            '" stroke="rgba(255,255,255,.4)" stroke-width="1.1" stroke-linecap="round"/>',
            '<path d="M' + f(cx - 7) + ' ' + f(ty - 1) + ' h14 v3.5 h-14 Z" fill="url(#gspSteelH)" stroke="#3d4650" stroke-width=".4"/>'
        ];
        [0.22, 0.5, 0.78].forEach(function (pct) {
            var sx = x + L * pct;
            s.push('<path d="M' + f(sx) + ' ' + f(ty - 1.5) + ' v' + f(bodyH + 3) +
                '" stroke="#3d4650" stroke-width="1.3" opacity=".65"/>');
        });
        s.push('</g>');
        return s.join('');
    }

    function fuelTankLayout(cavXs, frameStart, railHalf, railThick, axleOffset, tireWide, tireAlong) {
        var tankX, tankLen;
        /* Tanque vai no MAIOR vão entre eixos do cavalo — assim, ao arrastar um eixo
           para antes do tanque, ele se reacomoda sozinho (não trava a montagem). */
        var best = null;
        for (var i = 0; i < cavXs.length - 1; i++) {
            var g = cavXs[i + 1] - cavXs[i];
            if (!best || g > best.gap) best = { x: (cavXs[i] + cavXs[i + 1]) / 2, gap: g };
        }
        /* Comprimento FIXO (não estica com o vão); só encolhe se o vão for pequeno. */
        var TANK_LEN = 80;
        if (best && best.gap > tireAlong + 24) {
            tankX = best.x;
            tankLen = Math.min(TANK_LEN, Math.max(40, best.gap - tireAlong - 14));
        } else if (cavXs.length >= 1) {
            tankX = (frameStart + cavXs[0]) / 2;
            tankLen = Math.min(TANK_LEN, Math.max(34, cavXs[0] - frameStart - tireAlong));
        } else {
            tankX = frameStart + 50;
            tankLen = Math.min(TANK_LEN, 60);
        }
        var railOuter = railHalf + railThick * 0.42;
        var tireInner = axleOffset - tireWide * 0.25;
        var strip = (railOuter + tireInner) * 0.5;
        /* Mais grosso: ocupa quase todo o vão entre longarina e pneu. */
        var bodyH = Math.min(24, Math.max(18, (tireInner - railOuter) - 1));
        return { x: tankX, len: tankLen, bodyH: bodyH, topCy: -strip, botCy: strip };
    }

    function drawEngine(x, cy) {
        return [
            '<g class="gsp-engine" opacity=".88">',
            '<rect x="' + f(x) + '" y="' + f(cy - 16) + '" width="42" height="32" rx="2" fill="url(#gspIron)" stroke="#1a1f24" stroke-width=".7"/>',
            '<rect x="' + f(x + 4) + '" y="' + f(cy - 12) + '" width="12" height="24" rx="1" fill="#252b31" opacity=".6"/>',
            '<rect x="' + f(x + 18) + '" y="' + f(cy - 12) + '" width="12" height="24" rx="1" fill="#252b31" opacity=".6"/>',
            '<rect x="' + f(x + 32) + '" y="' + f(cy - 8) + '" width="8" height="16" rx="1" fill="url(#gspAlu)" stroke="#3d4650" stroke-width=".4"/>',
            '</g>'
        ].join('');
    }

    function drawTransmission(cx, cy) {
        return [
            '<g class="gsp-transmission">',
            '<rect x="' + f(cx - 14) + '" y="' + f(cy - 10) + '" width="28" height="20" rx="2" fill="url(#gspSteelR)" stroke="#2a3139" stroke-width=".65"/>',
            '<rect x="' + f(cx - 8) + '" y="' + f(cy - 5) + '" width="16" height="10" rx="1" fill="#1a1f24"/>',
            drawBolts(cx, cy, 6, 8, .7),
            '</g>'
        ].join('');
    }

    function drawCardan(pts, cy) {
        if (!pts || pts.length < 2) return '';
        var d = 'M' + f(pts[0]) + ' ' + f(cy);
        for (var i = 1; i < pts.length; i++) d += ' L' + f(pts[i]) + ' ' + f(cy);
        var joints = '';
        for (var k = 1; k < pts.length - 1; k++) {
            var jx = pts[k];
            joints += '<path d="' + pathEllipse(jx, cy, 4.5, 3.5) + '" fill="url(#gspSteelR)" stroke="#1a1f24" stroke-width=".65"/>';
            joints += '<path d="M' + f(jx - 3) + ' ' + f(cy) + ' h6 M' + f(jx) + ' ' + f(cy - 3) + ' v6" stroke="#252b31" stroke-width=".5"/>';
        }
        return [
            '<g class="gsp-cardan" opacity=".92">',
            '<path d="' + d + '" fill="none" stroke="#0a0c0f" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>',
            '<path d="' + d + '" fill="none" stroke="url(#gspSteelH)" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>',
            '<path d="' + d + '" fill="none" stroke="#ffffff0a" stroke-width="1.5" stroke-linecap="round"/>',
            joints,
            '</g>'
        ].join('');
    }

    function drawMudguard(cx, cy, span, tireAlong) {
        var hw = tireAlong * 0.55, hh = span * 0.92;
        return '<path d="M' + f(cx - hw / 2) + ' ' + f(cy - hh / 2) + ' h' + f(hw) + ' q' + f(hw * .15) + ' ' + f(hh * .5) +
            ' 0 ' + f(hh) + ' h' + f(-hw) + ' q' + f(-hw * .15) + ' ' + f(-hh * .5) + ' 0 ' + f(-hh) + ' Z" fill="#94a3b8" fill-opacity=".18" stroke="#6b7580" stroke-width=".45" pointer-events="none"/>';
    }

    function drawDifferential(cx, cy, drive, railGap) {
        railGap = railGap || 28;
        var maxW = railGap * 0.52;
        var rx = drive ? maxW * 0.44 : maxW * 0.34;
        var ry = rx * 0.72;
        var hx = rx + 6;
        return [
            '<g class="gsp-diff" opacity=".95">',
            '<path d="' + pathEllipse(cx, cy, rx + 1, ry + 1) + '" fill="#00000012"/>',
            '<path d="' + pathEllipse(cx, cy, rx, ry) + '" fill="url(#gspDiff)" stroke="#14181d" stroke-width=".75"/>',
            '<path d="' + pathEllipse(cx, cy, rx - 3, ry - 2) + '" fill="none" stroke="#ffffff0c" stroke-width=".6"/>',
            '<path d="M' + f(cx - hx) + ' ' + f(cy) + ' L' + f(cx - rx + 1) + ' ' + f(cy) + '" stroke="url(#gspSteelH)" stroke-width="2.2" stroke-linecap="round" opacity=".7"/>',
            '<path d="M' + f(cx + rx - 1) + ' ' + f(cy) + ' L' + f(cx + hx) + ' ' + f(cy) + '" stroke="url(#gspSteelH)" stroke-width="2.2" stroke-linecap="round" opacity=".7"/>',
            drawBolts(cx, cy, drive ? 8 : 6, rx - 2.5, .65),
            '<circle cx="' + f(cx) + '" cy="' + f(cy) + '" r="2.2" fill="#080a0c"/>',
            '</g>'
        ].join('');
    }

    function drawLeafSpring(cx, y, span) {
        span = span || 40;
        var s = '<g class="gsp-spring" opacity=".85">';
        for (var i = 0; i < 4; i++) {
            var off = i * 1;
            var len = span - i * 3;
            s += '<path d="M' + f(cx - len / 2) + ' ' + f(y + off) + ' L' + f(cx + len / 2) + ' ' + f(y + off) + '"' +
                ' stroke="#5c6670" stroke-width="' + f(2 - i * .3) + '" stroke-linecap="round"/>';
        }
        s += '<rect x="' + f(cx - 4) + '" y="' + f(y - 2) + '" width="8" height="3" rx=".5" fill="url(#gspSteelH)" stroke="#3d4650" stroke-width=".35"/>';
        return s + '</g>';
    }

    function drawSuspension(cx, yT, yB) {
        var mid = (yT + yB) / 2;
        return [
            '<path d="M' + f(cx) + ' ' + f(yT) + ' L' + f(cx) + ' ' + f(yT + (yB - yT) * .25) + '" stroke="#3d4650" stroke-width="1.6"/>',
            '<rect x="' + f(cx - 3) + '" y="' + f(yT + (yB - yT) * .25) + '" width="6" height="12" rx="1" fill="url(#gspAlu)" stroke="#3d4650" stroke-width=".45"/>',
            '<path d="M' + f(cx - 14) + ' ' + f(mid) + ' h28" stroke="#4a5560" stroke-width="1.4" opacity=".45"/>',
            '<rect x="' + f(cx - 2) + '" y="' + f(mid - 2) + '" width="4" height="4" rx=".5" fill="url(#gspSteelH)" stroke="#3d4650" stroke-width=".35"/>'
        ].join('');
    }

    function drawAxleBeam(cx, y1, y2) {
        return '<path d="M' + f(cx - 4) + ' ' + f(y1) + ' L' + f(cx + 4) + ' ' + f(y1) +
            ' L' + f(cx + 3.5) + ' ' + f(y2) + ' L' + f(cx - 3.5) + ' ' + f(y2) + ' Z" fill="url(#gspSteelH)" stroke="#252b31" stroke-width=".65"/>';
    }

    function drawWheelHub(cx, cy, r) {
        r = r || 4;
        return [
            '<g class="gsp-hub" opacity=".8">',
            '<circle cx="' + f(cx) + '" cy="' + f(cy) + '" r="' + f(r) + '" fill="url(#gspSteelR)" stroke="#252b31" stroke-width=".5"/>',
            '<circle cx="' + f(cx) + '" cy="' + f(cy) + '" r="' + f(r - 1.5) + '" fill="none" stroke="#ffffff10" stroke-width=".4"/>',
            '<circle cx="' + f(cx) + '" cy="' + f(cy) + '" r="1.2" fill="#14181d"/>',
            '</g>'
        ].join('');
    }

    function drawAxle(ax, cy, yMin, yMax, railT, railB, isDrive, railGap) {
        return [
            drawAxleBeam(ax, yMin + 2, yMax - 2),
            drawLeafSpring(ax, railT - 6, 38),
            drawLeafSpring(ax, railB + 6, 38),
            drawSuspension(ax, railT, railB),
            drawBrackets(ax, railT, railB),
            drawDifferential(ax, cy, isDrive, railGap),
            drawWheelHub(ax, cy, 3.5)
        ].join('');
    }

    function drawFifthWheel(cx, cy) {
        var r = 14;
        return [
            '<g class="gsp-fifth" opacity=".94">',
            '<ellipse cx="' + f(cx) + '" cy="' + f(cy) + '" rx="' + f(r) + '" ry="' + f(r * .72) + '" fill="url(#gspSteelR)" stroke="#252b31" stroke-width=".75"/>',
            '<ellipse cx="' + f(cx) + '" cy="' + f(cy) + '" rx="' + f(r - 4) + '" ry="' + f((r - 4) * .72) + '" fill="none" stroke="#ffffff10" stroke-width=".6"/>',
            '<path d="M' + f(cx - 6) + ' ' + f(cy - 4) + ' l4 -5 l12 0 l4 5 l-4 7 l-12 0 Z" fill="#14181d" stroke="#0a0c0f" stroke-width=".5"/>',
            '<circle cx="' + f(cx) + '" cy="' + f(cy) + '" r="2.6" fill="#080a0c"/>',
            drawBolts(cx, cy, 8, r - 3.5, .45),
            '</g>'
        ].join('');
    }

    function drawExhaust(x, cy) {
        return [
            '<g class="gsp-exhaust" opacity=".85">',
            '<rect x="' + f(x) + '" y="' + f(cy - 5) + '" width="32" height="10" rx="2" fill="url(#gspExhaust)" stroke="#252b31" stroke-width=".5"/>',
            '<rect x="' + f(x + 32) + '" y="' + f(cy - 4) + '" width="12" height="8" rx="1.5" fill="url(#gspIron)" stroke="#252b31" stroke-width=".45"/>',
            '</g>'
        ].join('');
    }

    function drawSpare(cx, cy, along, wide) {
        return '<g class="gsp-spare">' + drawTireRubber(cx - along / 2, cy - wide / 2, along, wide, { hub: false }) + '</g>';
    }

    function drawAirLines(x1, x2, yT, yB) {
        return [
            '<g class="gsp-airlines" opacity=".45">',
            '<path d="M' + f(x1 + 50) + ' ' + f(yT - 8) + ' Q' + f((x1 + x2) / 2) + ' ' + f(yT - 14) + ' ' + f(x2 - 40) + ' ' + f(yT - 8) +
            '" fill="none" stroke="#3a4249" stroke-width="1.6" stroke-linecap="round"/>',
            '<path d="M' + f(x1 + 50) + ' ' + f(yB + 8) + ' Q' + f((x1 + x2) / 2) + ' ' + f(yB + 14) + ' ' + f(x2 - 40) + ' ' + f(yB + 8) +
            '" fill="none" stroke="#3a4249" stroke-width="1.6" stroke-linecap="round"/>',
            '</g>'
        ].join('');
    }

    function drawLandingGear(cx, cy) {
        return [
            '<g class="gsp-landing" opacity=".8">',
            '<path d="M' + f(cx - 3) + ' ' + f(cy - 36) + ' l6 0 l-3 28 Z" fill="url(#gspSteelH)" stroke="#3d4650" stroke-width=".5"/>',
            '<rect x="' + f(cx - 10) + '" y="' + f(cy - 8) + '" width="20" height="5" rx="1" fill="url(#gspSteelR)" stroke="#2a3139" stroke-width=".5"/>',
            drawWheelHub(cx, cy - 5, 4),
            '</g>'
        ].join('');
    }

    function drawSteeringGear(cx, cy) {
        return [
            '<g class="gsp-steer" opacity=".7">',
            '<path d="M' + f(cx) + ' ' + f(cy - 14) + ' l0 -12 M' + f(cx - 6) + ' ' + f(cy - 24) + ' l12 0" stroke="url(#gspSteelH)" stroke-width="1.8" stroke-linecap="round"/>',
            '<circle cx="' + f(cx) + '" cy="' + f(cy - 26) + '" r="3" fill="url(#gspSteelR)" stroke="#2a3139" stroke-width=".45"/>',
            '</g>'
        ].join('');
    }

    /* ═══════════════════ PNEU — elemento principal da planta ═══════════════════ */
    function drawTireRubber(x, y, w, h, opts) {
        opts = opts || {};
        var cx = x + w / 2, cy = y + h / 2;
        var r = Math.min(4, w * 0.08, h * 0.08);
        var body = pathRoundRect(x, y, w, h, r);
        var narrow = Math.min(w, h);
        var sw = narrow * 0.2;
        var tw = Math.max(2, narrow * 0.6);
        var ty0 = y + sw;
        var parts = ['<g class="gsp-tire-rubber">'];

        parts.push('<path d="' + body + '" fill="url(#gspTireFaceV)" stroke="#080a0c" stroke-width="1.2" filter="url(#gspTireSh)"/>');

        parts.push('<path d="M' + f(x + 1) + ' ' + f(y + 1) + ' L' + f(x + w - 1) + ' ' + f(y + 1) +
            ' L' + f(x + w - 2) + ' ' + f(y + sw) + ' L' + f(x + 2) + ' ' + f(y + sw) + ' Z" fill="url(#gspRubSide)" opacity=".85"/>');
        parts.push('<path d="M' + f(x + 1) + ' ' + f(y + h - 1) + ' L' + f(x + w - 1) + ' ' + f(y + h - 1) +
            ' L' + f(x + w - 2) + ' ' + f(y + h - sw) + ' L' + f(x + 2) + ' ' + f(y + h - sw) + ' Z" fill="url(#gspRubSide)" opacity=".85"/>');
        parts.push('<rect x="' + f(x + 2) + '" y="' + f(ty0) + '" width="' + f(Math.max(2, w - 4)) + '" height="' + f(tw) + '" fill="url(#gspRubTread)" opacity=".95"/>');
        parts.push('<rect x="' + f(x + 2) + '" y="' + f(ty0) + '" width="' + f(Math.max(2, w - 4)) + '" height="' + f(tw) + '" fill="url(#gspTreadBlock)" opacity=".55"/>');
        var step = Math.max(2.5, w / 10);
        for (var tx = x + step; tx < x + w - step * .5; tx += step) {
            parts.push('<path d="M' + f(tx) + ' ' + f(ty0 + 1) + ' L' + f(tx) + ' ' + f(ty0 + tw - 1) +
                '" stroke="rgba(0,0,0,.35)" stroke-width=".45"/>');
        }

        parts.push('<path d="' + body + '" fill="url(#gspRubberGrain)" opacity=".12" pointer-events="none"/>');
        parts.push('<path d="' + body + '" fill="url(#gspTireFaceHi)" stroke="none" pointer-events="none"/>');

        if (opts.hub !== false) {
            var hubR = Math.min(5.5, Math.min(w, h) * 0.11);
            parts.push(drawTireHub(cx, cy, hubR));
        }

        parts.push('</g>');
        return parts.join('');
    }

    function drawTireHub(cx, cy, r) {
        r = r || 5;
        return [
            '<g class="gsp-tire-hub" pointer-events="none" opacity=".92">',
            '<circle cx="' + f(cx) + '" cy="' + f(cy) + '" r="' + f(r) + '" fill="url(#gspSteelR)" stroke="#252b31" stroke-width=".55"/>',
            '<circle cx="' + f(cx) + '" cy="' + f(cy) + '" r="' + f(r - 1.5) + '" fill="none" stroke="#ffffff14" stroke-width=".45"/>',
            drawBolts(cx, cy, 8, r - 1.8, .42),
            '<circle cx="' + f(cx) + '" cy="' + f(cy) + '" r="' + f(r * 0.2) + '" fill="#14181d"/>',
            '</g>'
        ].join('');
    }

    function dualStackSpan(slots, tireWide) {
        if (!slots || slots.length < 2) return tireWide;
        return Math.abs(slots[slots.length - 1].y - slots[0].y) + tireWide;
    }

    function drawDualTireHub(cx, cy, span) {
        var hh = Math.max(4, span * 0.68), hw = 4;
        return [
            '<g class="gsp-dual-hub" pointer-events="none" opacity=".75">',
            '<rect x="' + f(cx - hw) + '" y="' + f(cy - hh / 2) + '" width="' + f(hw * 2) + '" height="' + f(hh) + '" rx="1" fill="url(#gspSteelH)" stroke="#3d4650" stroke-width=".5"/>',
            '</g>'
        ].join('');
    }

    function drawTireRubberLegacy(cx, cy, rx, ry, opts) {
        return drawTireRubber(cx - rx, cy - ry, rx * 2, ry * 2, opts);
    }

    function tireSlots(count, ax, cy, side, tireAlong, tireWide, axleOffset, dualGap) {
        var slots = [], sign = side < 0 ? -1 : 1;
        var x0 = ax - tireAlong / 2;
        if (count <= 0) return slots;
        for (var i = 0; i < count; i++) {
            var rowY = cy + sign * (axleOffset + i * (tireWide + dualGap)) - tireWide / 2;
            slots.push({ x: x0, y: rowY, w: tireAlong, h: tireWide, single: count === 1 });
        }
        return slots;
    }

    function vehicleFrame(innerW, innerH, body, pad) {
        pad = pad == null ? 24 : pad;
        return {
            width: innerW + pad * 2,
            height: innerH + pad * 2,
            markup: '<g class="gsp-vehicle" transform="translate(' + f(pad) + ',' + f(pad) + ')">' + body + '</g>'
        };
    }

    function drawCarretaRails(x1, x2, yT, yB, t) {
        return '<g class="gsp-carreta-rails">' + drawFrame(x1, x2, yT, yB, t) + '</g>';
    }

    /** Posiciona eixos: 1º do cavalo sob a traseira da cabine; demais com colW; carreta após unitGap. */
    function placeAxleXs(axles, padL, cabW, colW, unitGap) {
        var xs = [];
        /* Sem cavalo na visão (ex.: só carreta) não reservamos o espaço da cabine. */
        var hasCav = axles.length && axles[0].unit === 'cavalo';
        var steerX = hasCav ? (padL + cabW - 10) : (padL + 54);
        for (var i = 0; i < axles.length; i++) {
            if (i === 0) {
                xs.push(steerX);
            } else if (axles[i].unit !== axles[i - 1].unit) {
                xs.push(xs[i - 1] + unitGap);
            } else {
                xs.push(xs[i - 1] + colW);
            }
        }
        return xs;
    }

    /* ═══════════════════ BUILD — planta técnica horizontal (cavalo ← → carreta) ═══════════════════ */
    function build(opts) {
        var padL = 44, padR = 52, padLabel = 26;
        var cabW = 96, colW = opts.colW || 104, unitGap = opts.unitGap || 172;
        var tireAlong = 58, tireWide = 32, dualGap = 0, axleOffset = 44;
        var railHalf = 13, railThick = 13, railGap = railHalf * 2;

        var axles = opts.axles || [];
        var cavCount = opts.cavCount || 0;
        var carCount = opts.carCount || 0;

        var tireReach = axleOffset + tireWide + dualGap + tireWide + 18;
        var H = tireReach * 2 + padLabel + 24;
        var cy = padLabel + tireReach;

        /* Posições custom (modo montagem) têm prioridade; senão, espaçamento automático. */
        var xs = (opts.axleXs && opts.axleXs.length === axles.length)
            ? opts.axleXs.map(Number)
            : placeAxleXs(axles, padL, cabW, colW, unitGap);

        var W = Math.max(padL + cabW + 200, (xs.length ? Math.max.apply(null, xs) + colW : padL + cabW) + padR);
        var cavXs = xs.slice(0, cavCount);
        var carXs = xs.slice(cavCount);
        var frameStart = padL + 20;
        var frameEnd = xs.length ? xs[xs.length - 1] + 40 : padL + cabW + 200;
        var cavFrameEnd = cavXs.length ? cavXs[cavXs.length - 1] + 20 : frameEnd;
        var carFrameStart = carXs.length ? carXs[0] - 48 : frameEnd;
        var railT = cy - railHalf, railB = cy + railHalf;

        var L = { under: [], power: [], hitch: [], mech: [], tires: [], steerTires: [], cab: [], labels: [] };
        var vehicleTankX = null;

        L.under.push(drawShadow(W, H));

        if (cavXs.length && carXs.length) {
            L.under.push(drawFrame(frameStart, cavFrameEnd, railT, railB, railThick));
            L.under.push(drawCrossMembers([frameStart].concat(cavXs, [cavFrameEnd]), railT, railB));
            L.under.push(drawAirLines(frameStart, cavFrameEnd, railT, railB));
            L.under.push(drawFrame(carFrameStart, frameEnd, railT, railB, railThick));
            L.under.push(drawCrossMembers([carFrameStart].concat(carXs, [frameEnd]), railT, railB));
            L.under.push(drawAirLines(carFrameStart, frameEnd, railT, railB));
        } else if (cavXs.length) {
            L.under.push(drawFrame(frameStart, cavFrameEnd, railT, railB, railThick));
            L.under.push(drawCrossMembers([frameStart].concat(cavXs, [cavFrameEnd]), railT, railB));
            L.under.push(drawAirLines(frameStart, cavFrameEnd, railT, railB));
        } else if (carXs.length) {
            L.under.push(drawFrame(carFrameStart, frameEnd, railT, railB, railThick));
            L.under.push(drawCrossMembers([carFrameStart].concat(carXs, [frameEnd]), railT, railB));
            L.under.push(drawAirLines(carFrameStart, frameEnd, railT, railB));
        } else {
            L.under.push(drawFrame(frameStart, frameEnd, railT, railB, railThick));
            L.under.push(drawCrossMembers([frameStart].concat(xs, [frameEnd]), railT, railB));
            L.under.push(drawAirLines(frameStart, frameEnd, railT, railB));
        }

        if (cavXs.length) {
            var ft = fuelTankLayout(cavXs, frameStart, railHalf, railThick, axleOffset, tireWide, tireAlong);
            /* Posição custom do tanque (modo montagem) tem prioridade. */
            vehicleTankX = (opts.tankX != null && !isNaN(opts.tankX)) ? Number(opts.tankX) : ft.x;
            L.power.push('<g class="gsp-fuel-group" data-part="tank">');
            L.power.push(drawFuelTank(vehicleTankX, cy + ft.topCy, ft.len, ft.bodyH));
            L.power.push(drawFuelTank(vehicleTankX, cy + ft.botCy, ft.len, ft.bodyH));
            var thx = vehicleTankX - ft.len / 2 - 4, thw = ft.len + 8;
            var thy = cy + ft.topCy - ft.bodyH / 2 - 2, thh = (ft.botCy - ft.topCy) + ft.bodyH + 4;
            L.power.push('<rect class="gsp-tank-hit" x="' + f(thx) + '" y="' + f(thy) + '" width="' + f(thw) + '" height="' + f(thh) + '" fill="transparent"/>');
            L.power.push('</g>');
            L.power.push(drawBattery(padL + cabW - 8, railT - 20));
            L.power.push(drawEngine(padL + cabW + 4, cy));
            if (cavXs.length > 1) L.power.push(drawTransmission((cavXs[0] + cavXs[1]) / 2, cy));
        }

        var cardanPts = [];
        cavXs.forEach(function (ax, i) { if (i > 0) cardanPts.push(ax); });
        if (cardanPts.length) {
            cardanPts.unshift(frameStart + 28);
            L.under.push(drawCardan(cardanPts, cy));
        }

        if (carXs.length) {
            if (cavXs.length) {
                L.hitch.push(drawFifthWheel(cavXs[cavXs.length - 1] + 28, cy));
            }
            L.hitch.push(drawLandingGear(carXs[0] - 6, cy));
            var carLbl = 'CARRETA · ' + carXs.length + ' eixo' + (carXs.length > 1 ? 's' : '');
            L.labels.push('<text class="gip-unit-label gip-unit-carreta" x="' + f((carXs[0] + carXs[carXs.length - 1]) / 2) + '" y="' + f(16) + '" text-anchor="middle">' + carLbl + '</text>');
        }

        if (cavXs.length) {
            var cavLbl = 'CAVALO MECÂNICO · ' + cavXs.length + ' eixo' + (cavXs.length > 1 ? 's' : '');
            L.labels.push('<text class="gip-unit-label gip-unit-cavalo" x="' + f(padL + (cavXs[0] + cavXs[cavXs.length - 1]) / 2) + '" y="' + f(16) + '" text-anchor="middle">' + cavLbl + '</text>');
        }

        var steerSlots = null;
        axles.forEach(function (a, ai) {
            var ax = xs[ai], n = a.pneus || 2;
            var topCount = Math.ceil(n / 2), botCount = n - topCount;
            var topSlots = tireSlots(topCount, ax, cy, -1, tireAlong, tireWide, axleOffset, dualGap);
            var botSlots = tireSlots(botCount, ax, cy, 1, tireAlong, tireWide, axleOffset, dualGap);
            var all = topSlots.concat(botSlots);
            var yMin = Math.min.apply(null, all.map(function (s) { return s.y; }));
            var yMax = Math.max.apply(null, all.map(function (s) { return s.y + s.h; }));
            var isDrive = (a.unit === 'cavalo' && a.eixoIdx > 0) || (a.unit === 'carreta');
            var isSteer = a.unit === 'cavalo' && a.eixoIdx === 0;
            if (isSteer) steerSlots = topSlots.concat(botSlots);

            L.mech.push('<g class="gip-axle" data-axle="' + a.prefix + '-' + a.eixoIdx + '">');
            L.mech.push(drawAxle(ax, cy, yMin, yMax, railT, railB, isDrive, railGap));
            if (isSteer) L.mech.push(drawSteeringGear(ax, cy));
            if (topCount >= 2) {
                var tCy = (topSlots[0].y + topSlots[topCount - 1].y + tireWide) / 2;
                var tSpan = dualStackSpan(topSlots, tireWide);
                L.mech.push(drawDualTireHub(ax, tCy, tSpan));
                if (isDrive || a.unit === 'carreta') L.mech.push(drawMudguard(ax, tCy, tSpan, tireAlong));
            }
            if (botCount >= 2) {
                var bCy = (botSlots[0].y + botSlots[botCount - 1].y + tireWide) / 2;
                var bSpan = dualStackSpan(botSlots, tireWide);
                L.mech.push(drawDualTireHub(ax, bCy, bSpan));
                if (isDrive || a.unit === 'carreta') L.mech.push(drawMudguard(ax, bCy, bSpan, tireAlong));
            }
            L.mech.push('</g>');

            var tireLayer = isSteer ? L.steerTires : L.tires;
            tireLayer.push('<g class="gip-axle-tires' + (isSteer ? ' gip-axle-tires-steer' : '') + '" data-axle="' + a.prefix + '-' + a.eixoIdx + '">');
            topSlots.forEach(function (s, k) {
                tireLayer.push(opts.renderTire(a.prefix + '-' + a.eixoIdx + '-' + k, s.x, s.y, s.w, s.h, { hub: !!s.single }));
            });
            botSlots.forEach(function (s, j) {
                tireLayer.push(opts.renderTire(a.prefix + '-' + a.eixoIdx + '-' + (topCount + j), s.x, s.y, s.w, s.h, { hub: !!s.single }));
            });
            tireLayer.push('</g>');
        });

        /* Cabine por cima do 1º eixo — só quando há cavalo na visão. */
        if (cavXs.length) {
            var steerX = cavXs[0];
            if (steerSlots) {
                L.cab.push(drawCab(padL, cy, cabW, {
                    steerSlots: steerSlots,
                    steerX: steerX,
                    tireAlong: tireAlong,
                    bodyOpacity: 0.22
                }));
            } else {
                var cabOverhang = 6;
                var cabDrawW = cabW + cabOverhang;
                var cabX = Math.max(padL - 2, steerX - cabDrawW + tireAlong * 0.5);
                L.cab.push(drawCab(cabX, cy, cabW, {
                    overhang: cabOverhang,
                    steerX: steerX,
                    tireAlong: tireAlong,
                    bodyOpacity: 0.4
                }));
            }
        }

        var body = L.under.concat(L.power, L.hitch, L.mech, L.tires, L.cab, L.steerTires, L.labels).join('');
        var framed = vehicleFrame(W, H, body, 20);
        var markup = drawDefs() + drawFloor(framed.width, framed.height, opts.darkTheme) + framed.markup;

        return { width: framed.width, height: framed.height, markup: markup, axleXs: xs, tankX: vehicleTankX, pad: 20 };
    }

    global.GipStagePremium = {
        build: build,
        tireSlots: tireSlots,
        drawTireRubber: drawTireRubber,
        drawTireRubberLegacy: drawTireRubberLegacy,
        pathRoundRect: pathRoundRect,
        drawDefs: drawDefs,
        drawCab: drawCab,
        drawFrame: drawFrame,
        drawDifferential: drawDifferential,
        drawFifthWheel: drawFifthWheel,
        drawTire: drawTireRubber
    };
})(typeof window !== 'undefined' ? window : global);
