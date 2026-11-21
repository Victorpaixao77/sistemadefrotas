/**
 * Importação de abastecimentos: CSV/JSON, foto (OCR) ou PDF (texto ou 1ª página + OCR).
 * Formato alinhado à coluna do papel de frete: data, km, posto, litros, valor R$.
 */
(function () {
  'use strict';

  var COL = {
    data: ['data', 'dt', 'date', 'data_abastecimento'],
    km: ['km', 'hodometro', 'odometro', 'quilometragem', 'km_atual'],
    posto: ['posto', 'bomba', 'estabelecimento', 'local'],
    litros: ['l', 'litros', 'litro', 'volume', 'qtd_litros', 'qtd'],
    valor_total: ['valor_total', 'valor', 'total', 'r$', 'rs', 'preco', 'preço', 'custo', 'valor_rs']
  };

  var PDF_WORKER =
    'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/legacy/build/pdf.worker.min.js';

  function normKey(s) {
    return String(s || '')
      .replace(/^\uFEFF/, '')
      .trim()
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/\s+/g, '_');
  }

  function detectDelimiter(headerLine) {
    var sc = (headerLine.match(/;/g) || []).length;
    var cm = (headerLine.match(/,/g) || []).length;
    return sc >= cm ? ';' : ',';
  }

  function splitCsvLine(line, delim) {
    var out = [];
    var cur = '';
    var q = false;
    for (var i = 0; i < line.length; i++) {
      var c = line[i];
      if (c === '"') {
        q = !q;
        continue;
      }
      if (!q && c === delim) {
        out.push(cur.trim());
        cur = '';
        continue;
      }
      cur += c;
    }
    out.push(cur.trim());
    return out;
  }

  function matchColKey(h) {
    var k = normKey(h);
    var name;
    for (name in COL) {
      if (!Object.prototype.hasOwnProperty.call(COL, name)) continue;
      var syns = COL[name];
      for (var i = 0; i < syns.length; i++) {
        if (k === syns[i] || k === syns[i].replace(/\$/g, '')) return name;
      }
    }
    return null;
  }

  function parseBrNumber(str, mode) {
    if (str == null || str === '') return NaN;
    var s = String(str).trim();
    if (!s) return NaN;
    var hasComma = s.indexOf(',') >= 0;
    var hasDot = s.indexOf('.') >= 0;
    if (hasComma && hasDot) {
      s = s.replace(/\./g, '').replace(',', '.');
    } else if (hasComma && !hasDot) {
      s = s.replace(',', '.');
    } else if (!hasComma && hasDot) {
      if (mode === 'money' && /^\d{1,3}(\.\d{3})+$/.test(s)) {
        s = s.replace(/\./g, '');
      } else if (mode === 'km') {
        s = s.replace(/\./g, '');
      }
    }
    var n = parseFloat(s);
    return Number.isFinite(n) ? n : NaN;
  }

  function fixOcrNumericToken(tok) {
    return String(tok || '')
      .replace(/[Oo]/g, '0')
      .replace(/,/g, ',')
      .replace(/[Il]/g, '1');
  }

  function parseDateParts(s) {
    if (!s) return null;
    s = String(s).trim();
    var m = s.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2}|\d{4})/);
    if (m) {
      var d = parseInt(m[1], 10);
      var mo = parseInt(m[2], 10);
      var y = parseInt(m[3], 10);
      if (y < 100) y += 2000;
      if (mo < 1 || mo > 12 || d < 1 || d > 31) return null;
      return { y: y, m: mo, d: d };
    }
    var iso = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (iso) {
      return { y: parseInt(iso[1], 10), m: parseInt(iso[2], 10), d: parseInt(iso[3], 10) };
    }
    return null;
  }

  function ymdFromParts(p) {
    if (!p) return '';
    var mm = String(p.m).padStart(2, '0');
    var dd = String(p.d).padStart(2, '0');
    return p.y + '-' + mm + '-' + dd;
  }

  function datetimeLocalFromParts(p, hour, min) {
    hour = hour == null ? 8 : hour;
    min = min == null ? 0 : min;
    if (!p) return '';
    var mm = String(p.m).padStart(2, '0');
    var dd = String(p.d).padStart(2, '0');
    var hh = String(hour).padStart(2, '0');
    var mi = String(min).padStart(2, '0');
    return p.y + '-' + mm + '-' + dd + 'T' + hh + ':' + mi;
  }

  function parseCsv(text) {
    var lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n').filter(function (ln) {
      return ln.trim().length > 0;
    });
    if (!lines.length) return { error: 'Arquivo vazio.' };
    var delim = detectDelimiter(lines[0]);
    var headerCells = splitCsvLine(lines[0], delim);
    var idx = {};
    headerCells.forEach(function (raw, i) {
      var key = matchColKey(raw);
      if (key) idx[key] = i;
    });
    if (idx.data == null || idx.km == null || idx.posto == null || idx.litros == null || idx.valor_total == null) {
      return {
        error:
          'Cabeçalho incompleto. Use: data; km; posto; litros; valor_total (nomes alternativos: dt, odometro, r$, etc.).'
      };
    }
    var rows = [];
    for (var r = 1; r < lines.length; r++) {
      var cells = splitCsvLine(lines[r], delim);
      if (!cells.some(function (c) { return c; })) continue;
      var row = rowFromCells(cells, idx);
      if (row.error) return { error: 'Linha ' + (r + 1) + ': ' + row.error };
      rows.push(row);
    }
    if (!rows.length) return { error: 'Nenhuma linha de dados após o cabeçalho.' };
    return { rows: rows };
  }

  function rowFromCells(cells, idx) {
    var dataRaw = cells[idx.data] || '';
    var kmRaw = cells[idx.km] || '';
    var posto = (cells[idx.posto] || '').trim();
    var litrosRaw = cells[idx.litros] || '';
    var valRaw = cells[idx.valor_total] || '';
    var parts = parseDateParts(dataRaw);
    if (!parts) return { error: 'Data inválida: "' + dataRaw + '"' };
    var km = parseBrNumber(fixOcrNumericToken(kmRaw), 'km');
    if (!Number.isFinite(km) || km < 0) return { error: 'KM inválido: "' + kmRaw + '"' };
    var litros = parseBrNumber(fixOcrNumericToken(litrosRaw), 'litros');
    if (!Number.isFinite(litros) || litros <= 0) return { error: 'Litros inválidos: "' + litrosRaw + '"' };
    var valorTotal = parseBrNumber(fixOcrNumericToken(valRaw), 'money');
    if (!Number.isFinite(valorTotal) || valorTotal < 0) return { error: 'Valor total inválido: "' + valRaw + '"' };
    if (!posto) return { error: 'Posto vazio.' };
    var valorLitro = litros > 0 ? valorTotal / litros : 0;
    return {
      ymd: ymdFromParts(parts),
      datetimeLocal: datetimeLocalFromParts(parts, 8, 0),
      km: Math.round(km),
      posto: posto,
      litros: litros,
      valorTotal: valorTotal,
      valorLitro: valorLitro,
      litrosBr: litros.toLocaleString('pt-BR', { minimumFractionDigits: 3, maximumFractionDigits: 3 }),
      valorTotalBr: valorTotal.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
      valorLitroBr: valorLitro.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
      _label: dataRaw + ' · ' + posto
    };
  }

  function pickObjField(o, keys) {
    var map = {};
    for (var ok in o) {
      if (!Object.prototype.hasOwnProperty.call(o, ok)) continue;
      map[normKey(ok)] = o[ok];
    }
    for (var j = 0; j < keys.length; j++) {
      var nk = normKey(keys[j]);
      if (map[nk] != null && map[nk] !== '') return map[nk];
    }
    return '';
  }

  function parseJson(text) {
    var data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      return { error: 'JSON inválido: ' + (e && e.message ? e.message : '') };
    }
    if (!Array.isArray(data)) {
      if (data && Array.isArray(data.abastecimentos)) data = data.abastecimentos;
      else if (data && Array.isArray(data.rows)) data = data.rows;
      else return { error: 'JSON deve ser um array ou { "abastecimentos": [...] }.' };
    }
    var idx = { data: 0, km: 1, posto: 2, litros: 3, valor_total: 4 };
    var rows = [];
    for (var i = 0; i < data.length; i++) {
      var o = data[i] || {};
      var cells = [
        pickObjField(o, COL.data),
        pickObjField(o, COL.km),
        pickObjField(o, COL.posto),
        pickObjField(o, COL.litros),
        pickObjField(o, COL.valor_total)
      ];
      var row = rowFromCells(cells, idx);
      if (row.error) return { error: 'Item ' + (i + 1) + ': ' + row.error };
      rows.push(row);
    }
    if (!rows.length) return { error: 'Array vazio.' };
    return { rows: rows };
  }

  function parseFileContent(text, name) {
    var lower = (name || '').toLowerCase();
    if (lower.endsWith('.json')) return parseJson(text);
    return parseCsv(text);
  }

  /** Linha livre pós-OCR: data no início, depois km, posto (texto), litros, valor R$ */
  function tryParseFreteOcrLine(rawLine) {
    var line = String(rawLine || '')
      .replace(/\u00a0/g, ' ')
      .replace(/\|/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
    if (line.length < 14) return null;
    if (/^data\s+km\s/i.test(line) && !/\d{1,2}\/\d{1,2}\/\d{2}/.test(line)) return null;
    if (/^abastecimento/i.test(line) && partsCountWords(line) < 4) return null;

    var dm = line.match(/^(\d{1,2}\/\d{1,2}\/\d{2,4})[\s.,:|/_-]+(.+)$/);
    if (!dm) return null;
    var tail = dm[2].trim();
    var parts = tail.split(/\s+/);
    if (parts.length < 4) return null;

    var valorStr = fixOcrNumericToken(parts[parts.length - 1]);
    var litrosStr = fixOcrNumericToken(parts[parts.length - 2]);
    var kmStr = fixOcrNumericToken(parts[0]);
    var posto = parts.slice(1, parts.length - 2).join(' ').trim();
    if (!posto || posto.length > 80) return null;

    var dataRaw = dm[1];
    var cells = [dataRaw, kmStr, posto, litrosStr, valorStr];
    var idx = { data: 0, km: 1, posto: 2, litros: 3, valor_total: 4 };
    var row = rowFromCells(cells, idx);
    if (row.error) return null;
    if (row.km < 500 || row.km > 9999999) return null;
    if (row.litros < 1 || row.litros > 80000) return null;
    return row;
  }

  function partsCountWords(line) {
    return line.trim().split(/\s+/).length;
  }

  function parseOcrFreteLines(text) {
    var lines = String(text || '').split(/\r?\n/);
    var rows = [];
    var seen = {};
    for (var i = 0; i < lines.length; i++) {
      var r = tryParseFreteOcrLine(lines[i]);
      if (r && !r.error) {
        var k = r.ymd + '|' + r.km + '|' + r.posto + '|' + r.litros + '|' + r.valorTotal;
        if (seen[k]) continue;
        seen[k] = 1;
        rows.push(r);
      }
    }
    return rows;
  }

  function extractPdfPage1Text(arrayBuffer) {
    var pdfjsLib = typeof window !== 'undefined' ? window.pdfjsLib || window['pdfjs-dist/build/pdf'] : null;
    if (!pdfjsLib || !pdfjsLib.getDocument) return Promise.resolve('');
    if (pdfjsLib.GlobalWorkerOptions) {
      pdfjsLib.GlobalWorkerOptions.workerSrc = PDF_WORKER;
    }
    return pdfjsLib
      .getDocument({ data: arrayBuffer })
      .promise.then(function (pdf) {
        return pdf.getPage(1);
      })
      .then(function (page) {
        return page.getTextContent();
      })
      .then(function (tc) {
        var items = tc.items || [];
        var out = '';
        for (var i = 0; i < items.length; i++) {
          var s = items[i].str || '';
          if (!s) continue;
          if (i > 0 && /^\d{1,2}\/\d{1,2}\/\d{2,4}/.test(s)) out += '\n';
          else if (out.length > 0) out += ' ';
          out += s;
        }
        return out;
      })
      .catch(function () {
        return '';
      });
  }

  function renderPdfFirstPageToDataUrl(arrayBuffer) {
    var pdfjsLib = typeof window !== 'undefined' ? window.pdfjsLib || window['pdfjs-dist/build/pdf'] : null;
    if (!pdfjsLib || !pdfjsLib.getDocument) {
      return Promise.reject(new Error('PDF.js não está disponível.'));
    }
    if (pdfjsLib.GlobalWorkerOptions) {
      pdfjsLib.GlobalWorkerOptions.workerSrc = PDF_WORKER;
    }
    return pdfjsLib
      .getDocument({ data: arrayBuffer })
      .promise.then(function (pdf) {
        return pdf.getPage(1);
      })
      .then(function (page) {
        var scale = 2.25;
        var viewport = page.getViewport({ scale: scale });
        var canvas = document.createElement('canvas');
        var ctx = canvas.getContext('2d');
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        var renderContext = { canvasContext: ctx, viewport: viewport };
        return page.render(renderContext).promise.then(function () {
          return canvas.toDataURL('image/png');
        });
      });
  }

  function runTesseract(imageSrc, onProgress) {
    var T = typeof window !== 'undefined' ? window.Tesseract : null;
    if (!T || typeof T.recognize !== 'function') {
      return Promise.reject(new Error('Tesseract.js não está disponível.'));
    }
    return T.recognize(imageSrc, 'por', {
      logger: function (m) {
        if (typeof onProgress === 'function') onProgress(m);
      }
    }).then(function (res) {
      return (res && res.data && res.data.text) ? res.data.text : '';
    });
  }

  function renderPreview(tbody, rows) {
    tbody.innerHTML = '';
    rows.forEach(function (row, i) {
      var dateBr = '';
      if (row && row.ymd && /^\d{4}-\d{2}-\d{2}$/.test(String(row.ymd))) {
        var p = String(row.ymd).split('-');
        dateBr = p[2] + '/' + p[1] + '/' + p[0];
      } else {
        dateBr = String(row._label || '');
      }
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td><input type="text" class="refuel-edit" data-f="date" data-i="' + i + '" value="' + escapeHtml(dateBr) + '" placeholder="dd/mm/aaaa"></td><td>' +
        '<input type="text" class="refuel-edit" data-f="km" data-i="' + i + '" value="' + escapeHtml(String(row.km || '')) + '" placeholder="KM"></td><td>' +
        '<input type="text" class="refuel-edit" data-f="posto" data-i="' + i + '" value="' + escapeHtml(String(row.posto || '')) + '" placeholder="Posto"></td><td>' +
        '<input type="text" class="refuel-edit" data-f="litros" data-i="' + i + '" value="' + escapeHtml(String(row.litrosBr || '')) + '" placeholder="Litros"></td><td>' +
        '<input type="text" class="refuel-edit" data-f="valor" data-i="' + i + '" value="' + escapeHtml(String(row.valorTotalBr || '')) + '" placeholder="Valor total">' +
        '</td><td><button type="button" class="refuel-import-apply" data-i="' +
        i +
        '">Usar</button></td>';
      tbody.appendChild(tr);
    });
  }

  /**
   * Alterna o modo do rodapé/cabeçalho do modal.
   * hasRota=true  → ficha de frete: mostra "Criar rota…", esconde "Importar", o texto de instruções
   *                 e o select "Rota" (a rota será criada agora, não há o que vincular).
   * hasRota=false → CSV/foto simples: mostra "Importar", esconde "Criar rota…", restaura instruções
   *                 e o select "Rota" (abastecimento avulso precisa vincular a uma rota existente).
   */
  function irrSetFooterMode(hasRota) {
    var saveBtn = document.getElementById('refuelImportSaveBtn');
    var createBtn = document.getElementById('irrCreateBtn');
    var intro = document.getElementById('refuelImportIntro');
    // Página só de rotas (Importar ficha): nunca há import avulso → "Importar" do rodapé fica sempre oculto.
    var routesOnly = typeof window.loadRouteData === 'function' && typeof window.loadRefuelingData !== 'function';
    if (saveBtn) saveBtn.style.display = (hasRota || routesOnly) ? 'none' : '';
    if (createBtn) createBtn.style.display = hasRota ? '' : 'none';
    if (intro) intro.style.display = hasRota ? 'none' : '';
    var rotaSel = document.getElementById('impRota');
    var rotaGroup = rotaSel ? rotaSel.closest('.form-group') : null;
    if (rotaGroup) rotaGroup.style.display = hasRota ? 'none' : '';
  }

  /** Recarrega a lista da página atual após importar (rotas em routes.php, abastecimentos em abastecimentos.php). */
  function irrRefreshList() {
    if (typeof window.loadRouteData === 'function') { window.loadRouteData(1); return; }
    if (typeof window.loadRefuelingData === 'function') { window.loadRefuelingData(1); }
  }

  function irrEnsureStyle() {
    if (document.getElementById('imp-rota-resumo-style')) return;
    var st = document.createElement('style');
    st.id = 'imp-rota-resumo-style';
    st.textContent =
      '.imp-rota-resumo{margin:0 0 14px;border:1px solid var(--border-color,#e5e7eb);border-radius:12px;background:var(--bg-secondary,#fff);padding:16px 18px;}' +
      '.imp-rota-resumo .irr-head{display:flex;align-items:center;gap:8px;font-weight:700;font-size:.92rem;margin-bottom:14px;color:var(--text-primary);}' +
      '.imp-rota-resumo .irr-head i{color:var(--accent-primary,#3b82f6);}' +
      '.imp-rota-resumo .irr-tag{margin-left:auto;font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:999px;background:rgba(245,158,11,.16);color:#b45309;text-transform:uppercase;}' +
      '.imp-rota-resumo .irr-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px 18px;}' +
      '.imp-rota-resumo .irr-item{display:flex;flex-direction:column;gap:3px;}' +
      '.imp-rota-resumo .irr-l{font-size:.68rem;text-transform:uppercase;letter-spacing:.3px;color:var(--text-muted,#64748b);}' +
      '.imp-rota-resumo .irr-v{font-size:.9rem;font-weight:600;color:var(--text-primary);}' +
      '.imp-rota-resumo .irr-notes{margin-top:8px;font-size:.82rem;color:var(--text-secondary,#475569);}' +
      '.imp-rota-resumo .irr-foot{margin-top:10px;font-size:.78rem;color:var(--text-muted,#64748b);display:flex;gap:6px;align-items:flex-start;}' +
      '.imp-rota-resumo .irr-actions{margin-top:12px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;}' +
      '.imp-rota-resumo #irrCreateBtn{background:#3b82f6;color:#fff;border:none;border-radius:8px;padding:9px 14px;font-weight:600;cursor:pointer;}' +
      '.imp-rota-resumo #irrCreateBtn:hover{filter:brightness(1.07);}' +
      '.imp-rota-resumo #irrCreateBtn:disabled{opacity:.6;cursor:default;}' +
      '.imp-rota-resumo .irr-status{font-size:.82rem;font-weight:600;}' +
      '.imp-rota-resumo .irr-inp{width:100%;box-sizing:border-box;padding:6px 8px;font-size:.85rem;border:1px solid var(--border-color,#d0d5dd);border-radius:6px;background:var(--bg-tertiary,var(--bg-secondary,#fff));color:var(--text-primary);}' +
      '.imp-rota-resumo .irr-inp:focus{outline:none;border-color:var(--accent-primary,#3b82f6);box-shadow:0 0 0 2px rgba(59,130,246,.15);}' +
      '.imp-rota-resumo .irr-item.irr-full{grid-column:1/-1;margin-top:8px;}' +
      '.imp-rota-resumo .irr-item label.irr-l{margin-bottom:3px;display:block;}' +
      '.imp-rota-resumo .irr-subhead{display:flex;align-items:center;gap:7px;font-weight:700;font-size:.82rem;margin:14px 0 8px;padding-top:10px;border-top:1px dashed var(--border-color,#e5e7eb);color:var(--text-primary);}' +
      '.imp-rota-resumo .irr-subhead i{color:#16a34a;}' +
      '.imp-rota-resumo .irr-subhead .irr-hint{margin-left:auto;font-weight:500;text-transform:none;letter-spacing:0;color:var(--text-muted,#64748b);}' +
      '.imp-rota-resumo .irr-km-warn{margin:10px 0 0;font-size:.78rem;font-weight:600;padding:7px 11px;border-radius:8px;line-height:1.35;}' +
      '.imp-rota-resumo .irr-km-warn.err{background:rgba(220,38,38,.12);color:#b91c1c;border:1px solid rgba(220,38,38,.25);}' +
      '.imp-rota-resumo .irr-km-warn.ok{background:rgba(22,163,74,.12);color:#15803d;border:1px solid rgba(22,163,74,.25);}' +
      /* alarga o modal de importação quando há resumo de viagem */
      '#refuelImportModal .modal-content,.refuel-import-modal .modal-content{max-width:min(1280px,97vw)!important;width:97%!important;}' +
      '#refuelImportModal .modal-body{max-height:78vh!important;overflow-y:auto;}' +
      '#refuelImportModal .refuel-import-preview-wrap{max-height:min(46vh,420px)!important;}';
    document.head.appendChild(st);
  }

  /** Mostra o cabeçalho da viagem lido da ficha (para o usuário validar). */
  function renderRotaResumo(tbody, rota) {
    var table = tbody ? tbody.closest('table') : null;
    var host = table ? table.parentNode : null;
    var box = document.getElementById('imp-rota-resumo');
    _irrCreatedRotaId = 0; // nova ficha/limpeza → nenhuma rota criada ainda nesta sessão
    if (!rota) { _currentRota = null; if (box && box.parentNode) box.parentNode.removeChild(box); irrSetFooterMode(false); return; }
    irrEnsureStyle();
    // Com ficha de rota: botão "Criar rota…" no rodapé, esconde "Importar" e o texto de instruções.
    irrSetFooterMode(true);
    if (!box) {
      box = document.createElement('div');
      box.id = 'imp-rota-resumo';
      box.className = 'imp-rota-resumo';
      if (host && table) host.insertBefore(box, table);
      else if (host) host.appendChild(box);
      else if (tbody && tbody.parentNode) tbody.parentNode.insertBefore(box, tbody);
    }
    var fmtMoney = function (v) { if (v == null || v === '') return ''; var n = Number(v); return isNaN(n) ? '' : n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    var fmtInt = function (v) { if (v == null || v === '') return ''; var n = Number(v); return isNaN(n) ? '' : String(Math.round(n)); };
    var inp = function (id, val, ph, type) { type = type || 'text'; return '<input id="' + id + '" class="irr-inp" type="' + type + '" value="' + escapeHtml(String(val == null ? '' : val)) + '"' + (ph ? ' placeholder="' + ph + '"' : '') + '>'; };
    var fld = function (id, label, val, ph, type) { return '<div class="irr-item"><label class="irr-l" for="' + id + '">' + label + '</label>' + inp(id, val, ph, type) + '</div>'; };
    var dz = rota.despesas || {};
    var dfld = function (id, label, val) { return fld(id, label, fmtMoney(val), '0,00'); };
    var despHtml =
      '<div class="irr-subhead"><i class="fas fa-receipt"></i> Despesas de Viagem <span class="irr-hint">vão para a aba Despesas da rota</span></div>' +
      '<div class="irr-grid">' +
      dfld('irr-desp-pedagios', 'Pedágio (R$)', rota.pedagio) +
      dfld('irr-desp-adiantamento', 'Adiantamento (R$)', rota.adiantamento) +
      dfld('irr-desp-descarga', 'Descarga/Chapa (R$)', dz.descarga) +
      dfld('irr-desp-caixinha', 'Caixinha (R$)', dz.caixinha) +
      dfld('irr-desp-estacionamento', 'Estacionamento (R$)', dz.estacionamento) +
      dfld('irr-desp-lavagem', 'Lavagem (R$)', dz.lavagem) +
      dfld('irr-desp-borracharia', 'Borracharia (R$)', dz.borracharia) +
      dfld('irr-desp-eletrica', 'Elétrica/Mecânica (R$)', dz.eletrica_mecanica) +
      dfld('irr-desp-outros', 'Outros (R$)', dz.outros) +
      '</div>';
    box.innerHTML =
      '<div class="irr-head"><i class="fas fa-route"></i> Viagem lida da ficha <span class="irr-tag">revise / edite</span></div>' +
      '<div class="irr-grid">' +
      '<div class="irr-item"><span class="irr-l">Motorista (sugerido)</span><span class="irr-v">' + escapeHtml(rota.motorista || '—') + '</span></div>' +
      '<div class="irr-item irr-loc"><label class="irr-l">Origem <small class="irr-hint">(' + escapeHtml(rota.origem || '?') + ')</small></label><div class="irr-loc-row"><select id="irr-uf-origem" class="irr-inp irr-uf"><option value="">UF</option></select><select id="irr-cid-origem" class="irr-inp"><option value="">Selecione a UF…</option></select></div></div>' +
      '<div class="irr-item irr-loc"><label class="irr-l">Destino <small class="irr-hint">(' + escapeHtml(rota.destino || '?') + ')</small></label><div class="irr-loc-row"><select id="irr-uf-destino" class="irr-inp irr-uf"><option value="">UF</option></select><select id="irr-cid-destino" class="irr-inp"><option value="">Selecione a UF…</option></select></div></div>' +
      fld('irr-dsaida', 'Saída', rota.data_saida, '', 'date') +
      fld('irr-dchegada', 'Chegada', rota.data_chegada, '', 'date') +
      fld('irr-kmsaida', 'KM saída', fmtInt(rota.km_saida), '') +
      fld('irr-kmchegada', 'KM chegada', fmtInt(rota.km_chegada), '') +
      fld('irr-kmrodado', 'KM rodado', fmtInt(rota.km_rodado), '') +
      fld('irr-peso', 'Peso (kg)', fmtInt(rota.peso_saida), '') +
      fld('irr-frete', 'Total frete (R$)', fmtMoney(rota.total_frete), '') +
      fld('irr-comissao', 'Comissão (R$)', fmtMoney(rota.comissao), '') +
      fld('irr-descricao', 'Descrição da carga', rota.descricao || '', 'opcional') +
      '</div>' +
      '<div id="irr-km-warn" class="irr-km-warn" hidden></div>' +
      '<div id="irr-comissao-warn" class="irr-km-warn" hidden></div>' +
      '<div id="irr-peso-warn" class="irr-km-warn" hidden></div>' +
      despHtml +
      '<div class="irr-item irr-full"><label class="irr-l" for="irr-anotacoes">Anotações</label>' + inp('irr-anotacoes', rota.anotacoes || '', '') + '</div>' +
      '<div class="irr-foot"><i class="fas fa-info-circle"></i><span>O veículo não vem na ficha — selecione <b>Veículo</b>, <b>Combustível</b> e <b>Forma de pagamento</b> abaixo. O motorista foi sugerido pelo nome (confira no campo Motorista acima). Para gravar, use <b>Criar rota e importar abastecimentos</b> no rodapé.</span></div>';

    _currentRota = rota;
    // Rótulo do botão conforme tenha ou não abastecimentos a vincular.
    var createBtn = document.getElementById('irrCreateBtn');
    if (createBtn) createBtn.innerHTML = '<i class="fas fa-route"></i> ' + (_cachedRows.length ? 'Criar rota e importar abastecimentos' : 'Criar rota');
    // Pré-seleciona o motorista pelo nome (se houver correspondência no select).
    irrPreselectMotorista(rota.motorista);
    irrSetupLocais(rota);
    // KM rodado: recalcula quando vazio e avisa quando o valor informado não confere.
    ['irr-kmsaida', 'irr-kmchegada', 'irr-kmrodado'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('input', irrRecalcKm);
    });
    irrRecalcKm();
    // Comissão: ao mudar frete/comissão/motorista, valida com o % do motorista (frete × % / 100).
    ['irr-frete', 'irr-comissao'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('input', irrValidateComissao);
    });
    var motSel = document.getElementById('impMotorista');
    if (motSel && !motSel._irrComWired) { motSel._irrComWired = true; motSel.addEventListener('change', irrValidateComissao); }
    irrValidateComissao();
    // Peso: valida faixa e capacidade do veículo selecionado.
    var pesoEl = document.getElementById('irr-peso');
    if (pesoEl) pesoEl.addEventListener('input', irrValidatePeso);
    var vehSel = document.getElementById('impVeiculo');
    if (vehSel && !vehSel._irrPesoWired) { vehSel._irrPesoWired = true; vehSel.addEventListener('change', irrValidatePeso); }
    irrValidatePeso();
  }

  /**
   * Valida o peso da carga lido da ficha:
   * - peso muito baixo para um caminhão (< 100 kg) → provável erro de leitura (ex.: 48 em vez de 48000);
   * - acima da capacidade do veículo selecionado → aviso (mesma regra do simulador, em kg);
   * - dentro da capacidade → mostra a ocupação (%).
   */
  function irrValidatePeso() {
    var warn = document.getElementById('irr-peso-warn');
    var pesoEl = document.getElementById('irr-peso');
    if (!warn || !pesoEl) return;
    var setWarn = function (msg, type) {
      if (!msg) { warn.hidden = true; warn.textContent = ''; warn.className = 'irr-km-warn'; return; }
      warn.hidden = false; warn.textContent = msg; warn.className = 'irr-km-warn ' + (type || 'err');
    };
    var peso = irrParseNum(pesoEl.value);
    if (peso == null || peso <= 0) { setWarn(''); return; }
    var kg = function (n) { return Math.round(n).toLocaleString('pt-BR') + ' kg'; };
    if (peso < 100) {
      setWarn('⚠ Peso muito baixo para um caminhão (' + kg(peso) + '). Confira — talvez seja ' + kg(peso * 1000) + '.', 'err');
      return;
    }
    var vehSel = document.getElementById('impVeiculo');
    var opt = (vehSel && vehSel.value) ? vehSel.options[vehSel.selectedIndex] : null;
    var cap = opt ? parseFloat(opt.getAttribute('data-cap')) : NaN;
    if (!isFinite(cap) || cap <= 0) { setWarn(''); return; }
    if (peso > cap) {
      setWarn('⚠ Carga (' + kg(peso) + ') acima da capacidade do veículo (' + kg(cap) + ').', 'err');
    } else {
      setWarn('Ocupação: ' + Math.round((peso / cap) * 100) + '% da capacidade (' + kg(cap) + ').', 'ok');
    }
  }

  var _irrComissaoPctCache = {};
  /**
   * Valida a comissão da ficha contra o % cadastrado do motorista (mesma conta da rota: frete × % / 100).
   * - comissão vazia → preenche com o valor calculado;
   * - comissão informada diferente → mostra aviso embaixo, igual ao do KM rodado.
   */
  function irrValidateComissao() {
    var warn = document.getElementById('irr-comissao-warn');
    var freteEl = document.getElementById('irr-frete');
    var comEl = document.getElementById('irr-comissao');
    if (!warn || !freteEl || !comEl) return;
    var setWarn = function (msg, type) {
      if (!msg) { warn.hidden = true; warn.textContent = ''; warn.className = 'irr-km-warn'; return; }
      warn.hidden = false; warn.textContent = msg; warn.className = 'irr-km-warn ' + (type || 'err');
    };
    var motSel = document.getElementById('impMotorista');
    var motId = motSel ? String(motSel.value || '') : '';
    var frete = irrParseNum(freteEl.value);
    if (!motId || frete == null) { setWarn(''); return; }
    var apply = function (pct) {
      if (pct == null || isNaN(pct)) { setWarn(''); return; }
      var expected = frete * pct / 100;
      var brl = function (n) { return 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
      var com = irrParseNum(comEl.value);
      if (com == null || String(comEl.value).trim() === '') {
        comEl.value = expected.toFixed(2);
        setWarn('Comissão calculada automaticamente: ' + pct + '% de ' + brl(frete) + ' = ' + brl(expected) + '.', 'ok');
        return;
      }
      if (Math.abs(com - expected) > 0.5) {
        setWarn('⚠ Comissão informada (' + brl(com) + ') não confere com ' + pct + '% do frete (' + brl(expected) + '). Corrija se necessário.', 'err');
      } else {
        setWarn('');
      }
    };
    if (Object.prototype.hasOwnProperty.call(_irrComissaoPctCache, motId)) {
      apply(_irrComissaoPctCache[motId]);
      return;
    }
    var url = irrRouteActionsUrl() + '?action=get_motorista_comissao&id=' + encodeURIComponent(motId);
    fetch(url, { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (d) {
      var pct = (d && d.success && d.porcentagem_comissao != null) ? parseFloat(d.porcentagem_comissao) : null;
      _irrComissaoPctCache[motId] = pct;
      apply(pct);
    }).catch(function () { setWarn(''); });
  }

  /**
   * Confere/recalcula o KM rodado a partir de KM saída e chegada.
   * - rodado vazio → preenche com (chegada − saída);
   * - rodado informado mas diferente → mostra aviso de cálculo incorreto;
   * - chegada menor que saída → aviso de inconsistência.
   */
  function irrRecalcKm(ev) {
    var sEl = document.getElementById('irr-kmsaida');
    var cEl = document.getElementById('irr-kmchegada');
    var rEl = document.getElementById('irr-kmrodado');
    var warn = document.getElementById('irr-km-warn');
    if (!sEl || !cEl || !rEl) return;
    var setWarn = function (msg, type) {
      if (!warn) return;
      if (!msg) { warn.hidden = true; warn.textContent = ''; warn.className = 'irr-km-warn'; return; }
      warn.hidden = false; warn.textContent = msg; warn.className = 'irr-km-warn ' + (type || 'err');
    };
    var s = irrParseNum(sEl.value), c = irrParseNum(cEl.value), r = irrParseNum(rEl.value);
    var fromRodado = ev && ev.target && ev.target.id === 'irr-kmrodado';
    if (s == null || c == null) { setWarn(''); return; }
    var expected = Math.round(c - s);
    if (expected < 0) {
      setWarn('⚠ KM chegada (' + Math.round(c) + ') é menor que KM saída (' + Math.round(s) + '). Verifique os valores.', 'err');
      return;
    }
    if (r == null || String(rEl.value).trim() === '') {
      // Vazio: o sistema mesmo calcula (a menos que o usuário esteja editando o próprio campo).
      if (!fromRodado) {
        rEl.value = String(expected);
        setWarn('KM rodado calculado automaticamente: ' + expected + ' km.', 'ok');
      } else {
        setWarn('');
      }
      return;
    }
    if (Math.abs(Math.round(r) - expected) > 1) {
      setWarn('⚠ Cálculo do KM rodado parece errado: informado ' + Math.round(r) + ' km, mas chegada − saída = ' + expected + ' km. Corrija se necessário.', 'err');
    } else {
      setWarn('');
    }
  }

  function irrRouteActionsUrl() {
    return typeof sfApiUrl === 'function' ? sfApiUrl('route_actions.php') : '../api/route_actions.php';
  }
  var _irrEstadosCache = null;
  function irrLoadEstados() {
    if (_irrEstadosCache) return Promise.resolve(_irrEstadosCache);
    return fetch(irrRouteActionsUrl() + '?action=get_estados', { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (d) { _irrEstadosCache = (d && d.success && Array.isArray(d.data)) ? d.data : []; return _irrEstadosCache; })
      .catch(function () { return []; });
  }
  function irrFillCidades(uf, cidSel, selectedId) {
    if (!cidSel) return Promise.resolve();
    if (!uf) { cidSel.innerHTML = '<option value="">Selecione a UF…</option>'; return Promise.resolve(); }
    cidSel.innerHTML = '<option value="">Carregando…</option>';
    return fetch(irrRouteActionsUrl() + '?action=get_cidades&uf=' + encodeURIComponent(uf), { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        var cids = (d && d.success && Array.isArray(d.data)) ? d.data : [];
        cidSel.innerHTML = '<option value="">Selecione a cidade…</option>' +
          cids.map(function (c) { return '<option value="' + c.id + '">' + escapeHtml(c.nome) + '</option>'; }).join('');
        if (selectedId) cidSel.value = String(selectedId);
      })
      .catch(function () { cidSel.innerHTML = '<option value="">Erro ao carregar</option>'; });
  }
  /** Monta os selects UF/cidade de origem e destino e pré-seleciona pelo nome lido. */
  function irrSetupLocais(rota) {
    var ufO = document.getElementById('irr-uf-origem'), cidO = document.getElementById('irr-cid-origem');
    var ufD = document.getElementById('irr-uf-destino'), cidD = document.getElementById('irr-cid-destino');
    if (!ufO || !ufD) return;
    irrLoadEstados().then(function (estados) {
      var opts = '<option value="">UF</option>' + estados.map(function (e) { return '<option value="' + e.uf + '">' + e.uf + '</option>'; }).join('');
      ufO.innerHTML = opts; ufD.innerHTML = opts;
      ufO.addEventListener('change', function () { irrFillCidades(ufO.value, cidO); });
      ufD.addEventListener('change', function () { irrFillCidades(ufD.value, cidD); });
      // Pré-seleciona resolvendo o nome lido (UF + cidade), sem travar se falhar.
      irrResolveCidade(rota.origem).then(function (c) { if (c && c.uf) { ufO.value = c.uf; irrFillCidades(c.uf, cidO, c.id); } });
      irrResolveCidade(rota.destino).then(function (c) { if (c && c.uf) { ufD.value = c.uf; irrFillCidades(c.uf, cidD, c.id); } });
    });
  }

  function irrSetStatus(msg, color) {
    var s = document.getElementById('irrStatus');
    if (s) { s.textContent = msg || ''; s.style.color = color || ''; }
  }
  function irrNorm(s) {
    s = String(s || '').toLowerCase();
    try { s = s.normalize('NFD').replace(/[̀-ͯ]/g, ''); } catch (e) {}
    return s.replace(/[^a-z0-9 ]/g, ' ').replace(/\s+/g, ' ').trim();
  }
  function irrPreselectMotorista(nome) {
    var sel = document.getElementById('impMotorista');
    if (!sel || sel.value) return; // não sobrescreve escolha manual
    var alvo = irrNorm(nome);
    if (!alvo) return;
    var tokens = alvo.split(' ').filter(function (t) { return t.length >= 3; });
    var best = null, bestScore = 0;
    for (var i = 0; i < sel.options.length; i++) {
      var opt = sel.options[i];
      if (!opt.value) continue;
      var on = irrNorm(opt.textContent);
      var score = 0;
      tokens.forEach(function (t) { if (on.indexOf(t) >= 0) score++; });
      if (on === alvo) score += 5;
      if (score > bestScore) { bestScore = score; best = opt; }
    }
    if (best && bestScore >= 1) sel.value = best.value;
  }

  function irrParseNum(s) {
    s = String(s == null ? '' : s).trim();
    if (!s) return null;
    s = s.replace(/[^\d.,-]/g, '');
    if (s.indexOf(',') >= 0) { s = s.replace(/\./g, '').replace(',', '.'); }
    else if ((s.match(/\./g) || []).length > 1) { s = s.replace(/\./g, ''); }
    else if (/\.\d{3}$/.test(s)) { s = s.replace(/\./g, ''); }
    var n = parseFloat(s);
    return isNaN(n) ? null : n;
  }
  function irrVal(id) { var e = document.getElementById(id); return e ? String(e.value).trim() : ''; }
  function irrNum(id, asInt) { var n = irrParseNum(irrVal(id)); if (n == null) return null; return asInt ? Math.round(n) : n; }

  function irrPostJson(url, body) {
    var headers = typeof sfMutationHeaders === 'function' ? sfMutationHeaders({ 'Content-Type': 'application/json' }) : { 'Content-Type': 'application/json' };
    if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__ && !headers['X-CSRF-Token']) headers['X-CSRF-Token'] = window.__SF_CSRF__;
    return fetch(url, { method: 'POST', credentials: 'include', headers: headers, body: JSON.stringify(body) })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); });
  }
  function irrResolveCidade(nome) {
    if (!nome) return Promise.resolve(null);
    var url = (typeof sfApiUrl === 'function' ? sfApiUrl('route_actions.php') : '../api/route_actions.php') + '?action=resolve_cidade&nome=' + encodeURIComponent(nome);
    return fetch(url, { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (d) { return (d && d.success) ? d : null; }).catch(function () { return null; });
  }

  /** Etapa 3: cria a rota a partir da ficha e (se houver) importa os abastecimentos vinculados. */
  function createRouteFromFicha() {
    var rota = _currentRota;
    if (!rota) return;
    var temAbastecimentos = _cachedRows.length > 0;
    var v = importSharedValues();
    if (!v.veiculo_id) { irrSetStatus('Selecione o veículo.', '#c0392b'); return; }
    // Combustível e forma de pagamento só são necessários quando há abastecimentos a importar.
    if (temAbastecimentos && !v.tipo_combustivel) { irrSetStatus('Selecione o combustível.', '#c0392b'); return; }
    if (temAbastecimentos && !v.forma_pagamento) { irrSetStatus('Selecione a forma de pagamento.', '#c0392b'); return; }
    var motoristaId = v.motorista_id;
    if (!motoristaId) { irrSetStatus('Selecione o motorista (não consegui sugerir).', '#c0392b'); return; }

    var btn = document.getElementById('irrCreateBtn');
    var anotacoes = irrVal('irr-anotacoes');
    var descricao = irrVal('irr-descricao');
    var ufO = irrVal('irr-uf-origem'), cidOId = irrVal('irr-cid-origem');
    var ufD = irrVal('irr-uf-destino'), cidDId = irrVal('irr-cid-destino');
    if (!ufO || !cidOId) { irrSetStatus('Selecione UF e cidade de Origem.', '#c0392b'); return; }
    if (!ufD || !cidDId) { irrSetStatus('Selecione UF e cidade de Destino.', '#c0392b'); return; }

    var btnLabel = btn ? btn.innerHTML : '';
    var setBusy = function (busy, txt) {
      if (!btn) return;
      btn.disabled = busy;
      btn.innerHTML = busy ? '<i class="fas fa-spinner fa-spin"></i> ' + (txt || 'Processando…') : btnLabel;
    };

    // Despesas + importação dos abastecimentos para uma rota já existente.
    var finishWithRoute = function (rotaId) {
      _irrCreatedRotaId = rotaId; // guarda para não recriar a rota numa retentativa
      var despUrl = irrRouteActionsUrl() + '?action=save_expenses';
      var desp = {
        rota_id: rotaId,
        pedagios: irrNum('irr-desp-pedagios') || 0,
        adiantamento: irrNum('irr-desp-adiantamento') || 0,
        descarga: irrNum('irr-desp-descarga') || 0,
        caixinha: irrNum('irr-desp-caixinha') || 0,
        estacionamento: irrNum('irr-desp-estacionamento') || 0,
        lavagem: irrNum('irr-desp-lavagem') || 0,
        borracharia: irrNum('irr-desp-borracharia') || 0,
        eletrica_mecanica: irrNum('irr-desp-eletrica') || 0,
        outros: irrNum('irr-desp-outros') || 0
      };
      var despTotal = desp.pedagios + desp.adiantamento + desp.descarga + desp.caixinha + desp.estacionamento + desp.lavagem + desp.borracharia + desp.eletrica_mecanica + desp.outros;
      var despPromise = despTotal > 0 ? irrPostJson(despUrl, desp).catch(function () { return null; }) : Promise.resolve(null);

      // Sem abastecimentos legíveis: cria só a rota (com as despesas) e encerra.
      if (!temAbastecimentos) {
        irrSetStatus('Salvando despesas…', '');
        despPromise.then(function () {
          var msg = 'Rota #' + rotaId + ' criada (sem abastecimentos).';
          irrSetStatus(msg, '#0a7d2c');
          if (typeof showToast === 'function') showToast(msg, 'success');
          setTimeout(function () {
            if (typeof closeRefuelImportModal === 'function') closeRefuelImportModal();
            irrRefreshList();
          }, 1400);
        });
        return;
      }

      irrSetStatus('Importando abastecimentos…', '');
      despPromise.then(function () {
        var impPayload = {
          action: 'import',
          veiculo_id: v.veiculo_id, motorista_id: motoristaId, rota_id: rotaId,
          tipo_combustivel: v.tipo_combustivel, forma_pagamento: v.forma_pagamento,
          rows: _cachedRows.map(function (r) { return { ymd: r.ymd, datetimeLocal: r.datetimeLocal, km: r.km, posto: r.posto, litros: r.litros, valorTotal: r.valorTotal }; })
        };
        irrPostJson(importApiUrl() + '?action=import', impPayload).then(function (o2) {
          var d2 = o2.d || {};
          if (!o2.ok || !d2.success) { irrSetStatus('Rota #' + rotaId + ' criada, mas falhou ao importar abastecimentos: ' + (d2.message || d2.error || '') + ' — clique de novo para tentar só a importação.', '#c0392b'); setBusy(false); return; }
          var msg = 'Rota #' + rotaId + ' criada com ' + (d2.inserted || 0) + ' abastecimento(s) vinculado(s).';
          irrSetStatus(msg, '#0a7d2c');
          if (typeof showToast === 'function') showToast(msg, 'success');
          setTimeout(function () {
            if (typeof closeRefuelImportModal === 'function') closeRefuelImportModal();
            irrRefreshList();
          }, 1600);
        }).catch(function () { irrSetStatus('Rota #' + rotaId + ' criada, mas erro de comunicação ao importar — clique de novo para tentar só a importação.', '#c0392b'); setBusy(false); });
      });
    };

    // Já criada numa tentativa anterior? Não recria — só refaz despesas + importação.
    if (_irrCreatedRotaId) {
      setBusy(true, 'Reimportando…');
      irrSetStatus('Reusando rota #' + _irrCreatedRotaId + '…', '');
      finishWithRoute(_irrCreatedRotaId);
      return;
    }

    setBusy(true, 'Criando rota…');
    var hoje = new Date().toISOString().slice(0, 10);
    var dataSaida = irrVal('irr-dsaida') || rota.data_saida || rota.data_chegada || hoje;
    var dataChegada = irrVal('irr-dchegada') || rota.data_chegada || '';
    var routePayload = {
      motorista_id: motoristaId,
      veiculo_id: v.veiculo_id,
      estado_origem: ufO, cidade_origem_id: cidOId,
      estado_destino: ufD, cidade_destino_id: cidDId,
      data_saida: dataSaida + ' 00:00:00',
      data_chegada: dataChegada ? (dataChegada + ' 00:00:00') : null,
      data_rota: dataSaida,
      km_saida: irrNum('irr-kmsaida', true),
      km_chegada: irrNum('irr-kmchegada', true),
      distancia_km: irrNum('irr-kmrodado', true),
      frete: irrNum('irr-frete'),
      comissao: irrNum('irr-comissao'),
      peso_carga: irrNum('irr-peso'),
      descricao_carga: descricao || null,
      no_prazo: 1,
      observacoes: 'Importado da ficha de frete.' + (anotacoes ? ' Anotações: ' + anotacoes : '')
    };
    irrSetStatus('Criando rota (' + ufO + ' → ' + ufD + ')…', '');
    irrPostJson(irrRouteActionsUrl() + '?action=add', routePayload).then(function (o) {
      var d = o.d || {};
      var rotaId = parseInt(d.id || d.rota_id || (d.data && d.data.id) || 0, 10) || 0;
      if (!o.ok || !d.success || !rotaId) {
        irrSetStatus('Falha ao criar a rota: ' + (d.message || d.error || 'erro'), '#c0392b');
        setBusy(false); return;
      }
      finishWithRoute(rotaId);
    }).catch(function () { irrSetStatus('Erro de comunicação ao criar a rota.', '#c0392b'); setBusy(false); });
  }

  function normalizeEditedRow(base, field, value) {
    var row = Object.assign({}, base || {});
    var v = String(value || '').trim();
    if (field === 'date') {
      var m = v.match(/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})$/);
      if (m) {
        var d = parseInt(m[1], 10);
        var mo = parseInt(m[2], 10);
        var y = parseInt(m[3], 10);
        if (y < 100) y += 2000;
        if (d >= 1 && d <= 31 && mo >= 1 && mo <= 12) {
          row.ymd = String(y).padStart(4, '0') + '-' + String(mo).padStart(2, '0') + '-' + String(d).padStart(2, '0');
          row.datetimeLocal = row.ymd + 'T08:00';
          row._label = v;
        }
      }
      return row;
    }
    if (field === 'posto') {
      row.posto = v;
      return row;
    }
    if (field === 'km') {
      var onlyDigits = v.replace(/[^\d]/g, '');
      if (onlyDigits) row.km = parseInt(onlyDigits, 10);
      return row;
    }
    if (field === 'litros' || field === 'valor') {
      var n = parseBrNumber(v);
      if (Number.isFinite(n)) {
        if (field === 'litros') {
          row.litros = n;
          row.litrosBr = n.toLocaleString('pt-BR', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
        } else {
          row.valorTotal = n;
          row.valorTotalBr = n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        var litros = Number(row.litros || 0);
        var total = Number(row.valorTotal || 0);
        var vl = litros > 0 ? total / litros : 0;
        row.valorLitro = vl;
        row.valorLitroBr = vl.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }
      return row;
    }
    return row;
  }

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function applyRowToRefuelForm(row) {
    if (typeof window.showAddRefuelModal !== 'function') {
      alert('Formulário de abastecimento não está disponível nesta página.');
      return;
    }
    window.showAddRefuelModal();
    var df = document.getElementById('data_rota_filtro');
    var da = document.getElementById('data_abastecimento');
    if (df) df.value = row.ymd;
    if (da) da.value = row.datetimeLocal;
    var kmEl = document.getElementById('km_atual');
    if (kmEl) kmEl.value = String(row.km);
    var postoEl = document.getElementById('posto');
    if (postoEl) postoEl.value = row.posto;
    var litrosEl = document.getElementById('litros');
    if (litrosEl) litrosEl.value = row.litrosBr;
    var vtEl = document.getElementById('valor_total');
    if (vtEl) vtEl.value = row.valorTotalBr;
    var vlEl = document.getElementById('valor_litro');
    if (vlEl) vlEl.value = row.valorLitroBr;
    if (df) df.dispatchEvent(new Event('change', { bubbles: true }));
    if (typeof showToast === 'function') {
      showToast('Dados da linha aplicados. Selecione veículo, motorista, rota e combustível.', 'info');
    }
  }

  var _cachedRows = [];
  var _currentRota = null;
  var _irrCreatedRotaId = 0; // evita criar rota duplicada se a importação falhar e o usuário tentar de novo

  function setProgress(el, msg) {
    if (el) el.textContent = msg || '';
  }

  function showOcrRaw(text) {
    var pre = document.getElementById('refuelImportOcrRaw');
    var wrap = document.getElementById('refuelImportOcrWrap');
    if (pre) pre.textContent = text || '';
    if (wrap) wrap.hidden = !String(text || '').trim();
  }

  function openRefuelImportModal() {
    var modal = document.getElementById('refuelImportModal');
    if (!modal) return;
    modal.classList.add('active');
    // Em routes.php os modais são fechados com display:none inline; limpa para o .active voltar a valer.
    modal.style.display = '';
    // Página só de rotas: o bloco "Só abastecimentos" não se aplica (não há import avulso aqui).
    var routesOnly = typeof window.loadRouteData === 'function' && typeof window.loadRefuelingData !== 'function';
    var avulso = document.getElementById('refuelImportIntroAvulso');
    if (avulso) avulso.style.display = routesOnly ? 'none' : '';
    var err = document.getElementById('refuelImportError');
    if (err) err.textContent = '';
    var tbody = document.getElementById('refuelImportPreviewBody');
    if (tbody) tbody.innerHTML = '';
    var fi = document.getElementById('refuelImportFile');
    if (fi) fi.value = '';
    setProgress(document.getElementById('refuelImportProgress'), '');
    showOcrRaw('');
    var wrap = document.getElementById('refuelImportOcrWrap');
    if (wrap) wrap.hidden = true;
    _cachedRows = [];
    // Limpa ficha de rota anterior e volta o rodapé/instruções ao modo padrão.
    renderRotaResumo(tbody, null);
    var st = document.getElementById('irrStatus');
    if (st) { st.textContent = ''; st.style.color = ''; }

    // Reset / carrega selects de import em massa
    var resultEl = document.getElementById('refuelImportResult');
    if (resultEl) { resultEl.textContent = ''; resultEl.style.color = ''; }
    var rota = document.getElementById('impRota');
    if (rota) fillSelect(rota, [], function () {}, function () {}, 'Selecione veículo e motorista…');
    loadImportSelects();
    refreshImportUI();
  }

  function closeRefuelImportModal() {
    var modal = document.getElementById('refuelImportModal');
    if (modal) { modal.classList.remove('active'); modal.style.display = 'none'; }
  }

  function refuelAiOcrEnabled() {
    return typeof window !== 'undefined' && window.__SF_REFUEL_AI_OCR__ === true;
  }

  function dataUrlToBlob(dataUrl) {
    var parts = dataUrl.split(',');
    if (parts.length < 2) throw new Error('Imagem inválida.');
    var mimeMatch = parts[0].match(/:(.*?);/);
    var mime = mimeMatch ? mimeMatch[1] : 'image/png';
    var bin = atob(parts[1]);
    var len = bin.length;
    var arr = new Uint8Array(len);
    for (var i = 0; i < len; i++) arr[i] = bin.charCodeAt(i);
    return new Blob([arr], { type: mime });
  }

  function sleepRefuelImport(ms) {
    return new Promise(function (resolve) {
      setTimeout(resolve, ms);
    });
  }

  /** attemptNo: 0 = primeira chamada; em HTTP 429 faz nova tentativa após espera (limite da API). */
  function uploadVisionBackend(blob, filename, progressEl, attemptNo) {
    attemptNo = attemptNo || 0;
    if (attemptNo === 0) {
      setProgress(progressEl, 'Enviando para leitura por IA de visão…');
    }
    var fd = new FormData();
    if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__) {
      fd.append('csrf_token', window.__SF_CSRF__);
    }
    fd.append('image', blob, filename || 'ficha.png');
    var headers = typeof sfMutationHeaders === 'function' ? sfMutationHeaders({}) : {};
    return fetch(typeof sfApiUrl === 'function' ? sfApiUrl('refuel_ocr_openai.php') : '../api/refuel_ocr_openai.php', {
      method: 'POST',
      body: fd,
      credentials: 'include',
      headers: headers
    }).then(function (res) {
      return res.json().then(function (data) {
        if (res.status === 429 && attemptNo < 2) {
          var sec = attemptNo === 0 ? 50 : 90;
          if (data && typeof data.retry_after_seconds === 'number' && data.retry_after_seconds >= 5) {
            sec = Math.min(180, Math.round(data.retry_after_seconds));
          }
          setProgress(
            progressEl,
            'Serviço em limite (429): aguardando ' + sec + ' s antes de tentar de novo (' + (attemptNo + 1) + '/2 nova(s) tentativa(s))…'
          );
          return sleepRefuelImport(sec * 1000).then(function () {
            return uploadVisionBackend(blob, filename, progressEl, attemptNo + 1);
          });
        }
        if (!res.ok || !data || data.success !== true) {
          var msg =
            (data && (data.message || data.error)) ||
            'Erro ' + res.status + ' ao ler a imagem.';
          if (data && Array.isArray(data.google_errors) && data.google_errors.length) {
            msg += ' Google: ' + data.google_errors.join(' | ');
          }
          if (data && Array.isArray(data.warnings) && data.warnings.length) {
            msg += ' ' + data.warnings.join(' ');
          }
          var err = new Error(msg);
          err.ocrText = data && data.ocr_text ? String(data.ocr_text) : '';
          err.rows = data && Array.isArray(data.rows) ? data.rows : [];
          err.warnings = data && Array.isArray(data.warnings) ? data.warnings : [];
          throw err;
        }
        var rows = data.rows || [];
        var summary = JSON.stringify(rows, null, 2);
        return {
          text: data.ocr_text || summary,
          rows: rows,
          rota: data.rota || null,
          source: data.source || 'deepseek-vision',
          model: data.model,
          warnings: Array.isArray(data.warnings) ? data.warnings : []
        };
      });
    });
  }

  /** Com IA no backend: imagem direta ou 1ª página do PDF como PNG. */
  function processBinaryFileOpenAi(f, progressEl) {
    var name = (f.name || '').toLowerCase();
    var isPdf = name.endsWith('.pdf') || f.type === 'application/pdf';

    return f.arrayBuffer().then(function (abuf) {
      if (isPdf) {
        setProgress(progressEl, 'PDF: preparando imagem da 1ª página…');
        return renderPdfFirstPageToDataUrl(abuf).then(function (dataUrl) {
          var blob = dataUrlToBlob(dataUrl);
          return uploadVisionBackend(blob, 'frete-pagina1.png', progressEl);
        });
      }
      return uploadVisionBackend(f, f.name || 'foto.jpg', progressEl);
    });
  }

  /** OCR local (Tesseract) + heurísticas — usado se IA não estiver configurada. */
  function processBinaryFileLocal(f, errEl, tbody, progressEl) {
    var name = (f.name || '').toLowerCase();
    var isPdf = name.endsWith('.pdf') || f.type === 'application/pdf';

    return f.arrayBuffer().then(function (abuf) {
      if (isPdf) {
        setProgress(progressEl, 'PDF: extraindo texto…');
        return extractPdfPage1Text(abuf).then(function (embedded) {
          var rowsFromText = parseOcrFreteLines(embedded);
          if (rowsFromText.length) {
            setProgress(progressEl, '');
            showOcrRaw(embedded);
            return { text: embedded, rows: rowsFromText, source: 'pdf-text' };
          }
          setProgress(progressEl, 'PDF: renderizando 1ª página para OCR…');
          return renderPdfFirstPageToDataUrl(abuf).then(function (dataUrl) {
            setProgress(progressEl, 'OCR local na imagem (30–120 s possível)…');
            return runTesseract(dataUrl, function (m) {
              if (m.status === 'recognizing text' && progressEl) {
                progressEl.textContent = 'OCR: ' + Math.round((m.progress || 0) * 100) + '%';
              }
            }).then(function (text) {
              return { text: text, rows: parseOcrFreteLines(text), source: 'pdf-ocr' };
            });
          });
        });
      }
      setProgress(progressEl, 'OCR local na imagem (30–120 s possível)…');
      var url = URL.createObjectURL(f);
      return runTesseract(url, function (m) {
        if (m.status === 'recognizing text' && progressEl) {
          progressEl.textContent = 'OCR: ' + Math.round((m.progress || 0) * 100) + '%';
        }
      })
        .then(function (text) {
          try {
            URL.revokeObjectURL(url);
          } catch (e) {}
          return { text: text, rows: parseOcrFreteLines(text), source: 'image' };
        })
        .catch(function (e) {
          try {
            URL.revokeObjectURL(url);
          } catch (e2) {}
          throw e;
        });
    });
  }

  function processBinaryFile(f, errEl, tbody, progressEl) {
    if (refuelAiOcrEnabled()) {
      return processBinaryFileOpenAi(f, progressEl);
    }
    return processBinaryFileLocal(f, errEl, tbody, progressEl);
  }

  // ---------------- Importação em massa (salvar no banco) ----------------

  function importApiUrl() {
    return typeof sfApiUrl === 'function'
      ? sfApiUrl('refuel_import_bulk.php')
      : '../api/refuel_import_bulk.php';
  }

  function fillSelect(sel, items, getVal, getLabel, placeholder) {
    if (!sel) return;
    var html = '<option value="">' + escapeHtml(placeholder || 'Selecione…') + '</option>';
    items.forEach(function (it) {
      html += '<option value="' + escapeHtml(String(getVal(it))) + '">' + escapeHtml(String(getLabel(it))) + '</option>';
    });
    sel.innerHTML = html;
  }

  function loadImportSelects() {
    var url = importApiUrl();
    fetch(url + '?action=veiculos', { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.success) {
          var vsel = document.getElementById('impVeiculo');
          if (vsel) {
            var html = '<option value="">Selecione…</option>';
            (res.data || []).forEach(function (v) {
              var cap = (v.capacidade_carga != null && v.capacidade_carga !== '') ? v.capacidade_carga : '';
              var label = (v.placa || '') + (v.modelo ? ' · ' + v.modelo : '');
              html += '<option value="' + escapeHtml(String(v.id)) + '" data-cap="' + escapeHtml(String(cap)) + '">' + escapeHtml(label) + '</option>';
            });
            vsel.innerHTML = html;
          }
        }
      }).catch(function () {});
    fetch(url + '?action=motoristas', { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.success) {
          fillSelect(document.getElementById('impMotorista'), res.data || [],
            function (m) { return m.id; }, function (m) { return m.nome; }, 'Selecione…');
        }
      }).catch(function () {});
    fetch(url + '?action=combustiveis', { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.success) {
          fillSelect(document.getElementById('impCombustivel'), (res.data || []).map(function (n) { return { n: n }; }),
            function (o) { return o.n; }, function (o) { return o.n; }, 'Selecione…');
        }
      }).catch(function () {});
  }

  function loadImportRotas() {
    var v = document.getElementById('impVeiculo');
    var m = document.getElementById('impMotorista');
    var rota = document.getElementById('impRota');
    if (!v || !m || !rota) return;
    if (!v.value || !m.value) {
      fillSelect(rota, [], function () {}, function () {}, 'Selecione veículo e motorista…');
      refreshImportUI();
      return;
    }
    fillSelect(rota, [], function () {}, function () {}, 'Carregando…');
    fetch(importApiUrl() + '?action=rotas&veiculo_id=' + encodeURIComponent(v.value) + '&motorista_id=' + encodeURIComponent(m.value), { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        var items = (res && res.success) ? (res.data || []) : [];
        fillSelect(rota, items,
          function (r2) { return r2.id; },
          function (r2) {
            var dt = r2.data_saida ? String(r2.data_saida).substring(0, 10).split('-').reverse().join('/') : '';
            var trecho = [r2.origem, r2.destino].filter(Boolean).join(' → ');
            return '#' + r2.id + (dt ? ' · ' + dt : '') + (trecho ? ' · ' + trecho : '');
          },
          items.length ? 'Selecione a rota…' : 'Nenhuma rota para este par');
        refreshImportUI();
      }).catch(function () {
        fillSelect(rota, [], function () {}, function () {}, 'Erro ao carregar rotas');
      });
  }

  function importSharedValues() {
    return {
      veiculo_id: (document.getElementById('impVeiculo') || {}).value || '',
      motorista_id: (document.getElementById('impMotorista') || {}).value || '',
      rota_id: (document.getElementById('impRota') || {}).value || '',
      tipo_combustivel: (document.getElementById('impCombustivel') || {}).value || '',
      forma_pagamento: (document.getElementById('impFormaPgto') || {}).value || ''
    };
  }

  function refreshImportUI() {
    var shared = document.getElementById('refuelImportShared');
    var saveBtn = document.getElementById('refuelImportSaveBtn');
    var hasRows = _cachedRows.length > 0;
    // Mostra os campos compartilhados quando há abastecimentos OU quando há ficha de rota
    // (na ficha sem abastecimentos ainda é preciso escolher o veículo para criar a rota).
    if (shared) shared.hidden = !(hasRows || _currentRota);
    // Tabela de abastecimentos só aparece com linhas; o wrapper só quando há linhas ou ficha de rota (o card fica nele).
    var previewTable = document.getElementById('refuelImportPreviewTable');
    var previewWrap = document.getElementById('refuelImportPreviewWrap');
    if (previewTable) previewTable.style.display = hasRows ? '' : 'none';
    if (previewWrap) previewWrap.hidden = !(hasRows || _currentRota);
    if (saveBtn) {
      var v = importSharedValues();
      var ready = hasRows && v.veiculo_id && v.motorista_id && v.rota_id && v.tipo_combustivel && v.forma_pagamento;
      saveBtn.disabled = !ready;
      saveBtn.innerHTML = '<i class="fas fa-file-import"></i> Importar' + (hasRows ? ' (' + _cachedRows.length + ')' : '');
    }
  }

  function doBulkImport() {
    if (!_cachedRows.length) return;
    var v = importSharedValues();
    var resultEl = document.getElementById('refuelImportResult');
    var saveBtn = document.getElementById('refuelImportSaveBtn');
    var payload = {
      action: 'import',
      veiculo_id: v.veiculo_id,
      motorista_id: v.motorista_id,
      rota_id: v.rota_id,
      tipo_combustivel: v.tipo_combustivel,
      forma_pagamento: v.forma_pagamento,
      rows: _cachedRows.map(function (r) {
        return { ymd: r.ymd, datetimeLocal: r.datetimeLocal, km: r.km, posto: r.posto, litros: r.litros, valorTotal: r.valorTotal };
      })
    };
    if (saveBtn) { saveBtn.disabled = true; saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importando…'; }
    if (resultEl) { resultEl.textContent = ''; resultEl.style.color = ''; }

    var headers = typeof sfMutationHeaders === 'function' ? sfMutationHeaders({ 'Content-Type': 'application/json' }) : { 'Content-Type': 'application/json' };
    if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__ && !headers['X-CSRF-Token']) {
      headers['X-CSRF-Token'] = window.__SF_CSRF__;
    }
    fetch(importApiUrl() + '?action=import', { method: 'POST', credentials: 'include', headers: headers, body: JSON.stringify(payload) })
      .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
      .then(function (o) {
        var d = o.d || {};
        if (!o.ok || !d.success) {
          if (resultEl) { resultEl.style.color = '#c0392b'; resultEl.textContent = d.message || d.error || 'Erro ao importar.'; }
          refreshImportUI();
          return;
        }
        var msg = d.inserted + ' abastecimento(s) importado(s)' + (d.status ? ' (' + d.status + ')' : '') + '.';
        if (d.skipped) msg += ' ' + d.skipped + ' ignorado(s).';
        if (typeof showToast === 'function') showToast(msg, 'success');
        if (resultEl) { resultEl.style.color = '#0a7d2c'; resultEl.textContent = msg; }
        setTimeout(function () {
          closeRefuelImportModal();
          if (typeof window.loadRouteData === 'function' || typeof window.loadRefuelingData === 'function') irrRefreshList();
          else location.reload();
        }, 1200);
      })
      .catch(function () {
        if (resultEl) { resultEl.style.color = '#c0392b'; resultEl.textContent = 'Erro de comunicação.'; }
        refreshImportUI();
      });
  }

  function wire() {
    var btn = document.getElementById('refuelImportBtn');
    if (btn) btn.addEventListener('click', openRefuelImportModal);
    var modal = document.getElementById('refuelImportModal');
    if (!modal) return;
    var file = document.getElementById('refuelImportFile');
    if (file) {
      file.addEventListener('change', function () {
        var f = file.files && file.files[0];
        if (!f) return;
        // Novo arquivo: volta ao modo padrão (botão "Importar" + instruções) até saber se há ficha de rota.
        irrSetFooterMode(false);
        var errEl = document.getElementById('refuelImportError');
        var tbody = document.getElementById('refuelImportPreviewBody');
        var progressEl = document.getElementById('refuelImportProgress');
        var name = (f.name || '').toLowerCase();
        var mime = f.type || '';

        var isStructured =
          name.endsWith('.csv') ||
          name.endsWith('.json') ||
          mime.indexOf('json') >= 0 ||
          mime === 'text/csv' ||
          mime === 'text/plain';

        if (isStructured && !name.endsWith('.pdf')) {
          var reader = new FileReader();
          reader.onload = function () {
            var text = String(reader.result || '');
            var res = parseFileContent(text, f.name);
            setProgress(progressEl, '');
            showOcrRaw('');
            if (res.error) {
              _cachedRows = [];
              if (tbody) tbody.innerHTML = '';
              if (errEl) errEl.textContent = res.error;
              refreshImportUI();
              return;
            }
            _cachedRows = res.rows;
            if (errEl) errEl.textContent = '';
            if (tbody) renderPreview(tbody, res.rows);
            refreshImportUI();
          };
          reader.readAsText(f, 'UTF-8');
          return;
        }

        if (errEl) errEl.textContent = '';
        if (tbody) { tbody.innerHTML = ''; renderRotaResumo(tbody, null); }
        _cachedRows = [];

        processBinaryFile(f, errEl, tbody, progressEl)
          .then(function (pack) {
            setProgress(progressEl, '');
            showOcrRaw(pack.text || '');
            var rows = pack.rows || [];
            if (!rows.length) {
              if (errEl) {
                errEl.textContent =
                  /-vision$/.test(pack.source || '')
                    ? 'A IA não encontrou linhas de abastecimento na imagem. Tente foto mais nítida, enquadramento na tabela ou use CSV.'
                    : 'Nenhuma linha no formato data + km + posto + litros + valor. Confira o texto extraído (abra “Texto extraído”) ou use um CSV.';
              }
              refreshImportUI();
              return;
            }
            _cachedRows = rows;
            if (errEl) {
              if (/-vision$/.test(pack.source || '')) {
                var aiMsg =
                  'Dados extraídos por IA na nuvem. Revise antes de salvar — confira KM, litros e valores.' +
                  (pack.model ? ' Modelo: ' + pack.model + '.' : '');
                if (Array.isArray(pack.warnings) && pack.warnings.length) {
                  aiMsg += ' Atenção: ' + pack.warnings.join(' ');
                }
                errEl.textContent = aiMsg;
              } else {
                errEl.textContent =
                  'Revise os valores — OCR local pode errar, principalmente em manuscrito. Origem: ' +
                  (pack.source === 'pdf-text' ? 'texto do PDF' : 'OCR') +
                  '.';
              }
            }
            if (tbody) renderPreview(tbody, rows);
            if (tbody) renderRotaResumo(tbody, pack.rota || null);
            refreshImportUI();
          })
          .catch(function (e) {
            setProgress(progressEl, '');
            console.error(e);
            if (e && e.ocrText) {
              showOcrRaw(e.ocrText);
            }
            if (tbody && e && Array.isArray(e.rows) && e.rows.length) {
              _cachedRows = e.rows;
              renderPreview(tbody, e.rows);
            }
            if (errEl) errEl.textContent = (e && e.message) ? e.message : 'Erro ao processar arquivo.';
            refreshImportUI();
          });
      });
    }
    var tbody = document.getElementById('refuelImportPreviewBody');
    if (tbody) {
      tbody.addEventListener('click', function (e) {
        var b = e.target.closest('.refuel-import-apply');
        if (!b) return;
        var i = parseInt(b.getAttribute('data-i'), 10);
        if (!Number.isFinite(i) || !_cachedRows[i]) return;
        closeRefuelImportModal();
        applyRowToRefuelForm(_cachedRows[i]);
      });
      tbody.addEventListener('input', function (e) {
        var inp = e.target.closest('.refuel-edit');
        if (!inp) return;
        var i = parseInt(inp.getAttribute('data-i'), 10);
        var f = String(inp.getAttribute('data-f') || '');
        if (!Number.isFinite(i) || !_cachedRows[i] || !f) return;
        _cachedRows[i] = normalizeEditedRow(_cachedRows[i], f, inp.value);
      });
    }

    // Import em massa: selects compartilhados + botão Importar
    var impV = document.getElementById('impVeiculo');
    var impM = document.getElementById('impMotorista');
    if (impV) impV.addEventListener('change', loadImportRotas);
    if (impM) impM.addEventListener('change', loadImportRotas);
    ['impRota', 'impCombustivel', 'impFormaPgto'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('change', refreshImportUI);
    });
    var saveBtn = document.getElementById('refuelImportSaveBtn');
    if (saveBtn) saveBtn.addEventListener('click', doBulkImport);
    var createBtn = document.getElementById('irrCreateBtn');
    if (createBtn) createBtn.addEventListener('click', createRouteFromFicha);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wire);
  } else {
    wire();
  }

  window.openRefuelImportModal = openRefuelImportModal;
  window.applyRefuelImportRowToForm = applyRowToRefuelForm;
})();
