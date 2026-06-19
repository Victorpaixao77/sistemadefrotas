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
    if (modal) modal.classList.remove('active');
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
          fillSelect(document.getElementById('impVeiculo'), res.data || [],
            function (v) { return v.id; },
            function (v) { return (v.placa || '') + (v.modelo ? ' · ' + v.modelo : ''); },
            'Selecione…');
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
    if (shared) shared.hidden = !hasRows;
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
    fetch(importApiUrl(), { method: 'POST', credentials: 'include', headers: headers, body: JSON.stringify(payload) })
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
          if (typeof window.loadRefuelingData === 'function') window.loadRefuelingData(1);
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
        if (tbody) tbody.innerHTML = '';
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
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wire);
  } else {
    wire();
  }

  window.openRefuelImportModal = openRefuelImportModal;
  window.applyRefuelImportRowToForm = applyRowToRefuelForm;
})();
