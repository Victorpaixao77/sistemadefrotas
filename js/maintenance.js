// Maintenance management JavaScript

// Escapa texto para inserção segura em HTML (evita XSS via descrição, nome de arquivo, etc.)
function mntEsc(s) {
    if (s == null) return '';
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
// Garante que a URL de um anexo é segura (bloqueia javascript:, data:, etc.)
function mntSafeUrl(u) {
    u = String(u || '').trim();
    if (/^(https?:|\/|\.\.?\/)/i.test(u)) return mntEsc(u);
    return '#';
}

function mntMutationHeaders(extra) {
    if (typeof sfMutationHeaders === 'function') {
        return sfMutationHeaders(extra || {});
    }
    var h = extra ? Object.assign({}, extra) : {};
    if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__) {
        h['X-CSRF-Token'] = window.__SF_CSRF__;
    }
    return h;
}

function mntReloadListPage(preferredPage) {
    var container = document.getElementById('paginationContainer');
    var page = preferredPage || parseInt(container && container.getAttribute('data-page'), 10) || 1;
    if (typeof loadPageViaAjax === 'function') {
        loadPageViaAjax(page);
        return;
    }
    window.location.reload();
}

function enableMaintenanceForm(editable) {
    var form = document.getElementById('maintenanceForm');
    var saveBtn = document.getElementById('saveMaintenanceBtn');
    if (form) {
        Array.from(form.elements).forEach(function (el) {
            if (el.id === 'manutencaoId') return;
            el.disabled = !editable;
        });
    }
    if (saveBtn) saveBtn.style.display = editable ? '' : 'none';
}

function openMaintenanceModalBlank(title) {
    var modal = document.getElementById('maintenanceModal');
    if (!modal) return;
    var form = document.getElementById('maintenanceForm');
    if (form) form.reset();
    document.getElementById('manutencaoId').value = '';
    document.getElementById('modalTitle').textContent = title || 'Nova Manutenção';
    var anexosSec = document.getElementById('anexosSection');
    if (anexosSec) anexosSec.style.display = 'none';
    enableMaintenanceForm(true);
    updateContaPagarLink(null);
    var gerarCp = document.getElementById('gerar_conta_pagar');
    if (gerarCp) gerarCp.checked = true;
    toggleConclusaoFields();
    modal.classList.add('active');
}

function openMaintenanceFromPlano(el) {
    if (!el) return;
    openMaintenanceModalBlank('Nova Manutenção (plano)');
    var vid = el.getAttribute('data-veiculo-id');
    var cid = el.getAttribute('data-componente-id');
    var tid = el.getAttribute('data-tipo-id');
    var km = el.getAttribute('data-km');
    var comp = el.getAttribute('data-componente') || '';
    var tipo = el.getAttribute('data-tipo') || '';
    var placa = el.getAttribute('data-placa') || '';
    if (vid) document.getElementById('veiculo_id').value = vid;
    if (cid) document.getElementById('componente_id').value = cid;
    if (tid) document.getElementById('tipo_manutencao_id').value = tid;
    if (km) document.getElementById('km_atual').value = km;
    var hoje = new Date().toISOString().slice(0, 10);
    document.getElementById('data_manutencao').value = hoje;
    var txt = 'Manutenção ' + tipo + ' — ' + comp + (placa ? ' (' + placa + ')' : '');
    document.getElementById('descricao').value = txt;
    document.getElementById('descricao_servico').value = txt;
}

function openHistoricoVeiculo(veiculoId, placa) {
    if (!veiculoId) return;
    fetch('../api/manutencoes.php?veiculo_id=' + veiculoId, { credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) return;
            var list = data.data || [];
            var total = data.total_custo_veiculo || 0;
            var custo12m = data.custo_12m != null ? data.custo_12m : total;
            var prev = data.total_preventivas != null ? data.total_preventivas : 0;
            var corr = data.total_corretivas != null ? data.total_corretivas : 0;
            document.getElementById('historicoVeiculoTitulo').textContent = 'Relatório - ' + (placa || 'Veículo');
            document.getElementById('historicoVeiculoCusto').textContent = list.length + ' manutenção(ões) no histórico.';
            document.getElementById('historicoVeiculoTotal').textContent = 'R$ ' + Number(total).toFixed(2).replace('.', ',');
            document.getElementById('historicoVeiculoCusto12m').textContent = 'R$ ' + Number(custo12m).toFixed(2).replace('.', ',');
            document.getElementById('historicoVeiculoPreventivas').textContent = prev;
            document.getElementById('historicoVeiculoCorretivas').textContent = corr;
            var tbody = document.getElementById('historicoVeiculoBody');
            tbody.innerHTML = list.map(function (m) {
                return '<tr><td>' + (m.data_manutencao ? m.data_manutencao.split('-').reverse().join('/') : '') + '</td><td>' + mntEsc(m.tipo_nome || '') + '</td><td>' + mntEsc((m.descricao || '').substring(0, 40)) + '</td><td>R$ ' + (parseFloat(m.valor) || 0).toFixed(2).replace('.', ',') + '</td></tr>';
            }).join('');
            document.getElementById('historicoVeiculoModal').classList.add('active');
        });
}

function duplicateMaintenance(id) {
    fetch('../api/manutencoes.php?id=' + id, { credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success || !data.data) {
                mntToast('Não foi possível duplicar.', 'error');
                return;
            }
            openMaintenanceModalBlank('Nova Manutenção (cópia)');
            fillMaintenanceForm(data.data);
            document.getElementById('manutencaoId').value = '';
            document.getElementById('data_manutencao').value = new Date().toISOString().slice(0, 10);
            var dc = document.getElementById('data_conclusao');
            if (dc) dc.value = '';
            mntToast('Registro duplicado — ajuste e salve.', 'info');
        })
        .catch(function () { mntToast('Erro ao duplicar.', 'error'); });
}

function buildOsHtml(m, veiculoLabel, anexos) {
    var fmtDate = function (d) { return d ? String(d).slice(0, 10).split('-').reverse().join('/') : '—'; };
    var fmtDateTime = function (d) {
        if (!d) return '';
        var s = String(d).replace(' ', 'T');
        var dt = new Date(s);
        return isNaN(dt) ? fmtDate(d) : dt.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    };
    var fmtMoney = function (v) { return 'R$ ' + (parseFloat(v) || 0).toFixed(2).replace('.', ','); };
    var e = mntEsc;
    var total = (m.custo_total != null && parseFloat(m.custo_total) > 0) ? parseFloat(m.custo_total) : (parseFloat(m.valor) || 0);
    var pecas = parseFloat(m.valor_pecas) || 0;
    var maoObra = parseFloat(m.valor_mao_obra) || 0;
    var outros = parseFloat(m.valor_outros) || 0;
    // Registros antigos (sem detalhamento) mostram o total como mão de obra, pra bater com o TOTAL.
    if ((pecas + maoObra + outros) <= 0 && total > 0) { maoObra = total; }
    if ((pecas + maoObra + outros) > 0) { total = pecas + maoObra + outros; }
    var statusNome = (m.status_nome || '').toString();
    var concluida = /conclu|finaliz|aprov/i.test(statusNome);
    var tipoNome = (m.tipo_nome || '—').toString();
    var now = new Date();
    var emissao = now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

    // Fotos (só anexos de imagem)
    var fotos = (anexos || []).filter(function (a) { return /image|jpg|jpeg|png|webp|gif/i.test((a.tipo || '') + (a.nome_original || '')); }).slice(0, 3);
    var fotosHtml = fotos.length
        ? fotos.map(function (a) { return '<div class="os-foto" style="background-image:url(' + mntSafeUrl(a.url) + ')"></div>'; }).join('')
        : '<div class="os-foto os-foto--empty"><i class="fas fa-image"></i></div>';

    // QR (aponta para o próprio sistema; degrada se offline)
    var qrData = encodeURIComponent((location.origin || '') + '/sistema-frotas/pages/manutencoes.php?os=' + (m.id || ''));
    var qrImg = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&margin=0&data=' + qrData;

    // Timeline
    var tl = [];
    tl.push({ ic: 'fa-file-circle-plus', c: '#2563eb', t: 'Ordem de serviço criada', s: 'por ' + e(m.responsavel_aprovacao || '—'), d: fmtDateTime(m.created_at || m.data_manutencao) });
    tl.push({ ic: 'fa-screwdriver-wrench', c: '#f59e0b', t: 'Serviço em andamento', s: 'Início da execução do serviço', d: fmtDate(m.data_manutencao) });
    if (m.data_conclusao) tl.push({ ic: 'fa-circle-check', c: '#16a34a', t: 'Serviço concluído', s: 'Serviço finalizado com sucesso', d: fmtDate(m.data_conclusao) });
    if (concluida) tl.push({ ic: 'fa-clipboard-check', c: '#2563eb', t: 'Ordem finalizada', s: 'Registro encerrado no sistema', d: fmtDateTime(m.updated_at || m.data_conclusao || m.data_manutencao) });
    var tlHtml = tl.map(function (i, idx) {
        return '<li' + (idx === tl.length - 1 ? ' class="last"' : '') + '><span class="os-tl-dot" style="background:' + i.c + '"><i class="fas ' + i.ic + '"></i></span>' +
            '<div class="os-tl-c"><span class="os-tl-date">' + i.d + '</span><b>' + i.t + '</b><span class="os-tl-sub">' + i.s + '</span></div></li>';
    }).join('');

    var checklist = ['Serviço realizado conforme padrão', 'Peças conferidas e instaladas', 'Testes realizados com sucesso', 'Veículo liberado para operação'];

    var line = function (ic, k, v) {
        return '<div class="os-line"><span class="os-line-k"><i class="fas ' + ic + '"></i> ' + k + '</span><span class="os-line-v">' + v + '</span></div>';
    };
    var statCard = function (ic, cor, label, val) {
        return '<div class="os-stat"><span class="os-stat-ic" style="color:' + cor + ';background:' + cor + '1a"><i class="fas ' + ic + '"></i></span>' +
            '<div><span class="os-stat-l">' + label + '</span><b>' + val + '</b></div></div>';
    };

    return '<style>' + MNT_OS_DOC_CSS + '</style>' +
    '<div class="os-doc">' +
        // Header
        '<div class="os-head">' +
            '<div class="os-head-truck"></div>' +
            '<div class="os-brand"><img src="../logo.png" class="os-logo" onerror="this.style.display=\'none\'"><div class="os-brand-t"><b>FROTEC</b><span>GESTÃO DE FROTAS</span></div></div>' +
            '<div class="os-head-num"><span>ORDEM DE SERVIÇO</span><h1>#' + e(String(m.id || '')) + '</h1></div>' +
            '<div class="os-emissao"><span class="os-emissao-ic"><i class="fas fa-calendar-day"></i></span><div><small>EMISSÃO</small><b>' + emissao.split(' ')[0] + '</b><span>' + (emissao.split(' ')[1] || '') + '</span></div></div>' +
            '<div class="os-qr"><img src="' + qrImg + '" onerror="this.parentNode.style.visibility=\'hidden\'"><span>Escaneie para<br>ver detalhes<br>no sistema</span></div>' +
        '</div>' +
        // Stat cards
        '<div class="os-stats">' +
            statCard('fa-circle-check', concluida ? '#16a34a' : '#f59e0b', 'STATUS', e(statusNome || '—')) +
            statCard('fa-screwdriver-wrench', '#2563eb', 'TIPO', e(tipoNome)) +
            statCard('fa-gauge-high', '#2563eb', 'KM NO MOMENTO', (m.km_atual != null ? Number(m.km_atual).toLocaleString('pt-BR') : '—')) +
            statCard('fa-calendar', '#2563eb', 'DATA DO SERVIÇO', fmtDate(m.data_manutencao)) +
        '</div>' +
        // Veículo + Componente
        '<div class="os-row">' +
            '<div class="os-veic">' +
                '<div class="os-veic-top"><span>VEÍCULO</span><i class="fas fa-truck"></i></div>' +
                '<div class="os-veic-placa">' + e(m.veiculo_placa || veiculoLabel || '—') + '</div>' +
                '<div class="os-veic-tipo">Cavalo Mecânico</div>' +
                '<div class="os-veic-grid">' +
                    '<div class="wide"><small>Marca/Modelo</small><b>' + e(((m.veiculo_marca || '') + ' ' + (m.veiculo_modelo || '')).trim() || '—') + '</b></div>' +
                    '<div><small>Ano</small><b>' + e(m.veiculo_ano || '—') + '</b></div>' +
                    '<div><small>Chassi</small><b>' + e(m.veiculo_chassi || '—') + '</b></div>' +
                '</div>' +
            '</div>' +
            '<div class="os-comp">' +
                '<div class="os-comp-top"><span>COMPONENTE / SERVIÇO</span><i class="fas fa-gears"></i></div>' +
                '<div class="os-comp-name">' + e(m.componente_nome || '—') + '</div>' +
                '<small class="os-comp-lbl">Descrição do serviço realizado</small>' +
                '<div class="os-comp-desc">' + (e(m.descricao_servico || m.descricao || '—')) + '</div>' +
            '</div>' +
        '</div>' +
        // Dados + Financeiro
        '<div class="os-row">' +
            '<div class="os-lines">' +
                line('fa-store', 'FORNECEDOR', e(m.fornecedor || '—')) +
                line('fa-file-invoice', 'NOTA FISCAL', e(m.nota_fiscal || '—')) +
                line('fa-wrench', 'SERVIÇO EXECUTADO', e(m.descricao_servico || '—')) +
                line('fa-comment', 'OBSERVAÇÕES', e(m.observacoes || '—')) +
                line('fa-user', 'RESPONSÁVEL', e(m.responsavel_aprovacao || '—')) +
            '</div>' +
            '<div class="os-fin">' +
                '<div class="os-fin-total"><span class="os-fin-ic"><i class="fas fa-dollar-sign"></i></span><div><small>VALOR TOTAL</small><b>' + fmtMoney(total) + '</b><span>Custo total da ordem de serviço</span></div></div>' +
                '<div class="os-fin-resumo"><span class="os-fin-h">RESUMO FINANCEIRO</span>' +
                    '<div class="os-fin-row"><span>Peças</span><b>' + fmtMoney(pecas) + '</b></div>' +
                    '<div class="os-fin-row"><span>Mão de obra</span><b>' + fmtMoney(maoObra) + '</b></div>' +
                    '<div class="os-fin-row"><span>Outros serviços</span><b>' + fmtMoney(outros) + '</b></div>' +
                    '<div class="os-fin-row total"><span>TOTAL</span><b>' + fmtMoney(total) + '</b></div>' +
                '</div>' +
            '</div>' +
        '</div>' +
        // Timeline + Fotos/Checklist
        '<div class="os-row">' +
            '<div class="os-tl"><div class="os-block-h">LINHA DO TEMPO</div><ul>' + tlHtml + '</ul></div>' +
            '<div class="os-right">' +
                '<div class="os-fotos"><div class="os-block-h">FOTOS DO SERVIÇO</div><div class="os-fotos-grid">' + fotosHtml + '</div></div>' +
                '<div class="os-check"><div class="os-check-list">' +
                    checklist.map(function (c) { return '<div class="os-check-i"><i class="fas fa-circle-check"></i>' + c + '</div>'; }).join('') +
                    '</div><div class="os-check-badge"><i class="fas fa-shield-halved"></i><b>' + (concluida ? 'APROVADO' : 'EM ANÁLISE') + '</b><span>' + (concluida ? 'Todos os itens concluídos' : 'Serviço em execução') + '</span></div>' +
                '</div>' +
            '</div>' +
        '</div>' +
        // Info + Footer
        '<div class="os-info"><span class="os-info-ic"><i class="fas fa-circle-info"></i></span><div><b>INFORMAÇÕES IMPORTANTES</b><span>Manter o veículo em acompanhamento conforme plano de manutenção preventiva.</span></div></div>' +
        '<div class="os-foot">' +
            '<div><span class="os-foot-ic"><i class="fas fa-headset"></i></span><div><small>Precisa de ajuda?</small><b>(44) 99999-0000</b><span>suporte@frotec.com.br</span></div></div>' +
            '<div class="os-foot-mid"><b>Frotec Gestão de Frotas</b><span>Sistema de Gestão Inteligente</span><a>www.frotec.com.br</a></div>' +
            '<div class="os-foot-r"><span>Documento gerado automaticamente</span><span>' + emissao + '</span><span>Página 1 de 1</span></div>' +
        '</div>' +
    '</div>';
}

// CSS do documento — embutido no HTML pra valer na prévia (modal) e na impressão.
var MNT_OS_DOC_CSS =
    '.os-doc{font-family:"Segoe UI",system-ui,-apple-system,sans-serif;color:#1e293b;background:#0b1220;padding:0;max-width:900px;margin:0 auto;border-radius:12px;overflow:hidden;font-size:11px;line-height:1.32}' +
    '.os-doc *{box-sizing:border-box}' +
    '.os-doc small{font-size:9px;letter-spacing:.04em;text-transform:uppercase}' +
    // header
    '.os-head{position:relative;background:linear-gradient(120deg,#0f1e33,#16324f);color:#fff;padding:16px 24px 18px;display:grid;grid-template-columns:auto 1fr auto auto;grid-template-areas:"brand num emis qr";gap:16px;align-items:center;overflow:hidden}' +
    '.os-head-truck{position:absolute;inset:0;background:radial-gradient(600px 200px at 60% -20%,rgba(59,130,246,.25),transparent 70%);pointer-events:none}' +
    '.os-brand{grid-area:brand;display:flex;align-items:center;gap:10px;z-index:1}.os-logo{width:34px;height:34px;object-fit:contain}' +
    '.os-brand-t{display:flex;flex-direction:column;line-height:1}.os-brand-t b{font-size:18px;letter-spacing:.02em}.os-brand-t span{font-size:8px;letter-spacing:.22em;color:#9fc0e8}' +
    '.os-head-num{grid-area:num;z-index:1}.os-head-num span{font-size:10px;letter-spacing:.14em;color:#7db0e6}.os-head-num h1{margin:1px 0 0;font-size:30px;font-weight:800;letter-spacing:-.02em}' +
    '.os-emissao{grid-area:emis;display:flex;gap:10px;align-items:center;z-index:1}.os-emissao-ic{width:38px;height:38px;border-radius:9px;background:#2563eb;display:grid;place-items:center;font-size:15px}' +
    '.os-emissao small{color:#7db0e6}.os-emissao b{display:block;font-size:15px}.os-emissao span{font-size:11px;color:#c7dcf3}' +
    '.os-qr{grid-area:qr;display:flex;flex-direction:column;align-items:center;gap:4px;background:#fff;padding:6px;border-radius:9px;z-index:1}.os-qr img{width:58px;height:58px;display:block}.os-qr span{font-size:7px;color:#334155;text-align:center;line-height:1.2}' +
    // body wrapper
    '.os-doc .os-stats,.os-doc .os-row,.os-doc .os-info,.os-doc .os-foot{background:#f1f5f9}' +
    '.os-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;padding:10px 16px 2px}' +
    '.os-stat{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:9px 12px;display:flex;gap:9px;align-items:center}' +
    '.os-stat-ic{width:34px;height:34px;border-radius:9px;display:grid;place-items:center;font-size:14px;flex:0 0 auto}' +
    '.os-stat-l{display:block;font-size:8.5px;letter-spacing:.05em;text-transform:uppercase;color:#64748b}.os-stat b{font-size:14px;color:#0f172a}' +
    '.os-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:8px 16px}' +
    // veiculo
    '.os-veic{background:linear-gradient(160deg,#132a45,#0f2138);color:#fff;border-radius:12px;padding:12px 15px}' +
    '.os-veic-top{display:flex;justify-content:space-between;align-items:center;color:#7db0e6;font-size:10px;letter-spacing:.1em}.os-veic-top i{font-size:20px;opacity:.5}' +
    '.os-veic-placa{font-size:22px;font-weight:800;margin-top:4px;letter-spacing:.02em}.os-veic-tipo{color:#9fc0e8;font-size:10.5px;margin-bottom:9px}' +
    '.os-veic-grid{display:grid;grid-template-columns:1fr 1fr;gap:7px 14px}.os-veic-grid .wide{grid-column:1/-1}.os-veic-grid small{color:#7db0e6;font-size:8px}.os-veic-grid b{display:block;font-size:12px;margin-top:1px}' +
    // componente
    '.os-comp{background:#eef4fb;border:1px solid #dbe7f5;border-radius:12px;padding:12px 15px}' +
    '.os-comp-top{display:flex;justify-content:space-between;align-items:center;color:#2563eb;font-size:10px;letter-spacing:.1em}.os-comp-top i{font-size:22px;opacity:.55}' +
    '.os-comp-name{font-size:19px;font-weight:800;color:#0f172a;margin:4px 0 9px}.os-comp-lbl{color:#64748b}.os-comp-desc{margin-top:3px;color:#334155;font-size:12px;word-break:break-word}' +
    // lines
    '.os-lines{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:4px 15px}' +
    '.os-line{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:8px 0;border-bottom:1px solid #eef2f7}.os-line:last-child{border-bottom:0}' +
    '.os-line-k{color:#475569;font-size:10px;letter-spacing:.03em;text-transform:uppercase;display:flex;align-items:center;gap:8px}.os-line-k i{color:#94a3b8}' +
    '.os-line-v{color:#0f172a;font-weight:600;text-align:right;word-break:break-word;max-width:60%}' +
    // financeiro
    '.os-fin{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:12px 15px;display:flex;flex-direction:column;gap:10px}' +
    '.os-fin-total{display:flex;gap:11px;align-items:center}.os-fin-ic{width:36px;height:36px;border-radius:10px;background:#dcfce7;color:#16a34a;display:grid;place-items:center;font-size:15px}' +
    '.os-fin-total small{color:#64748b}.os-fin-total b{display:block;font-size:21px;color:#0f172a;letter-spacing:-.02em}.os-fin-total span{font-size:9.5px;color:#94a3b8}' +
    '.os-fin-resumo{border-top:1px solid #eef2f7;padding-top:8px}.os-fin-h{display:block;color:#2563eb;font-size:9.5px;letter-spacing:.08em;margin-bottom:4px}' +
    '.os-fin-row{display:flex;justify-content:space-between;padding:4px 0;color:#475569}.os-fin-row b{color:#0f172a}.os-fin-row.total{border-top:1px solid #e2e8f0;margin-top:3px;padding-top:7px;font-size:13px}.os-fin-row.total span,.os-fin-row.total b{color:#2563eb;font-weight:800}' +
    // timeline
    '.os-block-h{color:#2563eb;font-size:10.5px;letter-spacing:.08em;font-weight:700;margin-bottom:9px}' +
    '.os-tl{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:12px 15px}.os-tl ul{list-style:none;margin:0;padding:0}' +
    '.os-tl li{position:relative;display:flex;gap:11px;padding-bottom:10px}.os-tl li::before{content:"";position:absolute;left:12px;top:25px;bottom:0;width:2px;background:#e2e8f0}.os-tl li.last::before{display:none}' +
    '.os-tl-dot{width:25px;height:25px;border-radius:50%;display:grid;place-items:center;color:#fff;font-size:10px;flex:0 0 auto;z-index:1}' +
    '.os-tl-c{display:flex;flex-direction:column}.os-tl-date{font-size:9px;color:#94a3b8}.os-tl-c b{font-size:12px;color:#0f172a}.os-tl-sub{font-size:10.5px;color:#64748b}' +
    // fotos + checklist
    '.os-right{display:flex;flex-direction:column;gap:12px}' +
    '.os-fotos{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:12px 15px}.os-fotos-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:7px}' +
    '.os-foto{aspect-ratio:4/3;border-radius:8px;background-size:cover;background-position:center;background-color:#e2e8f0}.os-foto--empty{display:grid;place-items:center;color:#94a3b8;font-size:20px;grid-column:1/-1;aspect-ratio:auto;height:56px}' +
    '.os-check{background:#eef4fb;border:1px solid #dbe7f5;border-radius:12px;padding:12px 15px;display:flex;justify-content:space-between;align-items:center;gap:12px}' +
    '.os-check-list{display:flex;flex-direction:column;gap:6px}.os-check-i{display:flex;align-items:center;gap:8px;font-size:11.5px;color:#334155}.os-check-i i{color:#16a34a;font-size:12px}' +
    '.os-check-badge{text-align:center;color:#2563eb}.os-check-badge i{font-size:22px;display:block;margin-bottom:3px}.os-check-badge b{display:block;font-size:13px}.os-check-badge span{font-size:8.5px;color:#64748b}' +
    // info + footer
    '.os-info{margin:6px 16px 0;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:10px 15px;display:flex;gap:11px;align-items:center}' +
    '.os-info-ic{width:30px;height:30px;border-radius:9px;background:#dbeafe;color:#2563eb;display:grid;place-items:center;font-size:14px}.os-info b{display:block;color:#2563eb;font-size:10.5px;letter-spacing:.06em}.os-info span{color:#475569;font-size:11px}' +
    '.os-foot{background:#0b1220;color:#cbd5e1;padding:12px 22px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;align-items:center;margin-top:10px}' +
    '.os-foot>div:first-child{display:flex;gap:10px;align-items:center}.os-foot-ic{width:34px;height:34px;border-radius:9px;background:#16233a;color:#7db0e6;display:grid;place-items:center}.os-foot small{color:#7db0e6}.os-foot b{color:#fff;display:block}.os-foot span{font-size:10.5px}' +
    '.os-foot-mid{text-align:center}.os-foot-mid b{color:#fff}.os-foot-mid span{display:block;font-size:10px}.os-foot-mid a{color:#7db0e6;font-size:10px}' +
    '.os-foot-r{text-align:right}.os-foot-r span{display:block;font-size:10px}' +
    '@media print{.os-doc{max-width:100%;border-radius:0}}';

var MNT_OS_PRINT_CSS = '@page{size:A4;margin:7mm}body{margin:0;background:#fff;-webkit-print-color-adjust:exact;print-color-adjust:exact}.os-doc{max-width:100%!important}';

function mntPrintHtml(html, title) {
    var iframe = document.createElement('iframe');
    iframe.setAttribute('aria-hidden', 'true');
    iframe.style.cssText = 'position:fixed;width:0;height:0;border:0;left:-9999px;top:0';
    document.body.appendChild(iframe);
    var win = iframe.contentWindow;
    var doc = win.document;
    doc.open();
    doc.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + mntEsc(title || 'Ordem de Serviço') + '</title>' +
        '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">' +
        '<style>' + MNT_OS_PRINT_CSS + '</style></head><body>' + html + '</body></html>');
    doc.close();
    setTimeout(function () {
        try {
            win.focus();
            win.print();
        } catch (e) { /* ignore */ }
        setTimeout(function () { iframe.remove(); }, 1500);
    }, 300);
}

function printOrdemServico(id) {
    fetch('../api/manutencoes.php?id=' + id, { credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success || !data.data) {
                mntToast('Não foi possível gerar a OS.', 'error');
                return;
            }
            var m = data.data;
            var veiculoLabel = m.veiculo_placa || '';
            if (!veiculoLabel && m.veiculo_id) {
                var sel = document.getElementById('veiculo_id');
                if (sel) {
                    var opt = sel.querySelector('option[value="' + m.veiculo_id + '"]');
                    if (opt) veiculoLabel = opt.textContent;
                }
            }
            // Busca os anexos (fotos do serviço) e só então monta a OS.
            fetch('../api/manutencao_anexos.php?manutencao_id=' + (m.id || ''), { credentials: 'include' })
                .then(function (r) { return r.json(); })
                .then(function (aj) { return (aj && aj.success && aj.data) ? aj.data : []; })
                .catch(function () { return []; })
                .then(function (anexos) {
                    var html = buildOsHtml(m, veiculoLabel, anexos);
                    document.getElementById('osPrintBody').innerHTML = html;
                    document.getElementById('osPrintModal').classList.add('active');
                });
        })
        .catch(function () { mntToast('Erro ao gerar OS.', 'error'); });
}

function loadFornecedoresDatalist() {
    var dl = document.getElementById('fornecedoresDatalist');
    if (!dl) return;
    fetch('../api/fornecedores.php?action=list&all=1&situacao=A', { credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (json) {
            if (!json.success || !json.fornecedores) return;
            dl.innerHTML = json.fornecedores.map(function (f) {
                var nome = f.nome || f.razao_social || f.nome_fantasia || '';
                return nome ? '<option value="' + mntEsc(nome) + '"></option>' : '';
            }).join('');
        })
        .catch(function () { /* opcional */ });
}

function setupAlertAndScoreClicks() {
    document.querySelectorAll('[data-mnt-alert-plano]').forEach(function (el) {
        el.addEventListener('click', function () { openMaintenanceFromPlano(el); });
        el.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openMaintenanceFromPlano(el); } });
    });
    document.querySelectorAll('[data-mnt-alert-intel]').forEach(function (el) {
        function go() {
            var vid = el.getAttribute('data-veiculo-id');
            if (!vid) return;
            var placa = el.getAttribute('data-placa') || '';
            var cid = el.getAttribute('data-componente-id');
            var tipo = el.getAttribute('data-alert-tipo');
            if (tipo === 'corretivas_componente' && cid) {
                openMaintenanceModalBlank('Nova Manutenção (alerta)');
                document.getElementById('veiculo_id').value = vid;
                document.getElementById('componente_id').value = cid;
                document.getElementById('data_manutencao').value = new Date().toISOString().slice(0, 10);
                document.getElementById('descricao').value = 'Corretiva — componente com recorrência (' + (placa || 'veículo') + ')';
                document.getElementById('descricao_servico').value = document.getElementById('descricao').value;
                return;
            }
            openHistoricoVeiculo(vid, placa);
        }
        el.addEventListener('click', go);
        el.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
    });
    document.querySelectorAll('.score-row.score-clickable').forEach(function (row) {
        row.addEventListener('click', function () {
            openHistoricoVeiculo(row.getAttribute('data-veiculo-id'), row.getAttribute('data-placa'));
        });
    });
    document.querySelectorAll('.mann-ranking-row').forEach(function (row) {
        row.addEventListener('click', function () {
            openHistoricoVeiculo(row.getAttribute('data-veiculo-id'), row.getAttribute('data-placa'));
        });
    });
    var osBtn = document.getElementById('osPrintBtn');
    if (osBtn) osBtn.addEventListener('click', function () {
        var body = document.getElementById('osPrintBody');
        if (body && body.innerHTML.trim()) {
            mntPrintHtml(body.innerHTML, 'Ordem de Serviço');
        }
    });
    setupNfOcr();
    setupMaintenanceImport();
}

// ---- Toast + confirmação (substitui alert()/confirm() nativos) ----
function mntToast(msg, type) {
    var wrap = document.getElementById('mntToastWrap');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.id = 'mntToastWrap';
        document.body.appendChild(wrap);
    }
    var t = document.createElement('div');
    t.className = 'mnt-toast ' + (type || 'info');
    var icon = type === 'success' ? 'fa-circle-check' : (type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-info');
    t.innerHTML = '<i class="fas ' + icon + '"></i><span></span>';
    t.querySelector('span').textContent = msg;
    wrap.appendChild(t);
    requestAnimationFrame(function () { t.classList.add('show'); });
    setTimeout(function () { t.classList.remove('show'); setTimeout(function () { t.remove(); }, 300); }, 3800);
}

function mntConfirm(message, opts) {
    opts = opts || {};
    return new Promise(function (resolve) {
        var modal = document.getElementById('mntConfirmModal');
        var msgEl = document.getElementById('mntConfirmMsg');
        var ok = document.getElementById('mntConfirmOk');
        var cancel = document.getElementById('mntConfirmCancel');
        if (!modal || !ok || !cancel) { resolve(window.confirm(message)); return; }
        if (msgEl) msgEl.textContent = message || 'Tem certeza?';
        if (opts.okLabel) ok.innerHTML = '<i class="fas fa-check"></i> ' + opts.okLabel;
        modal.style.display = 'flex';
        function cleanup(v) {
            modal.style.display = 'none';
            ok.removeEventListener('click', onOk);
            cancel.removeEventListener('click', onCancel);
            modal.removeEventListener('click', onBg);
            resolve(v);
        }
        function onOk() { cleanup(true); }
        function onCancel() { cleanup(false); }
        function onBg(e) { if (e.target === modal) cleanup(false); }
        ok.addEventListener('click', onOk);
        cancel.addEventListener('click', onCancel);
        modal.addEventListener('click', onBg);
    });
}

// ---- Painéis colapsáveis (alertas, score, ranking, insights) ----
function setupMntCollapsiblePanels() {
    document.querySelectorAll('.mnt-insights .mnt-toggle').forEach(function (toggle) {
        if (toggle.id === 'mntToggle') return;
        if (toggle.dataset.mntToggleWired) return;
        toggle.dataset.mntToggleWired = '1';
        var bodyId = toggle.getAttribute('aria-controls');
        var body = bodyId ? document.getElementById(bodyId) : null;
        if (!body) {
            var panel = toggle.closest('.mnt-insights');
            body = panel ? panel.querySelector('.mnt-body') : null;
        }
        if (!body) return;
        toggle.addEventListener('click', function () {
            var open = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
            body.style.display = open ? 'none' : '';
        });
    });
}

// ---- Insights de manutenção (preventiva + anomalias + custo + IA) ----
var mntLastData = null;
var mntInsightsLoaded = false;
var mntInsightsLoading = false;
var MNT_INSIGHTS_URL = '../api/maintenance_insights.php';
var MNT_CACHE_TTL_MS = 5 * 60 * 1000;

function mntInsightsCacheKey() {
    return 'sf_insights_v1_' + MNT_INSIGHTS_URL.replace(/[^a-z0-9]/gi, '_');
}

function mntReadInsightsCache() {
    try {
        var raw = sessionStorage.getItem(mntInsightsCacheKey());
        if (!raw) return null;
        var o = JSON.parse(raw);
        if (!o || !o.ts || !o.data) return null;
        if (Date.now() - o.ts > (o.ttl || MNT_CACHE_TTL_MS)) {
            sessionStorage.removeItem(mntInsightsCacheKey());
            return null;
        }
        return o.data;
    } catch (e) { return null; }
}

function mntWriteInsightsCache(data) {
    try {
        sessionStorage.setItem(mntInsightsCacheKey(), JSON.stringify({ ts: Date.now(), ttl: MNT_CACHE_TTL_MS, data: data }));
    } catch (e) { /* quota */ }
}

function setupMaintenanceInsights() {
    setupMntCollapsiblePanels();
    var panel = document.getElementById('mntInsights');
    var summary = document.getElementById('mntSummary');
    var toggle = document.getElementById('mntToggle');
    var body = document.getElementById('mntBody');

    if (panel && summary) {
        panel.style.display = '';
        summary.innerHTML = '<i class="fas fa-clipboard-check"></i> Insights de manutenção — <span style="font-weight:500;color:var(--text-muted)">expandir para analisar</span>';
    }

    if (toggle && body) {
        toggle.addEventListener('click', function () {
            var open = toggle.getAttribute('aria-expanded') === 'true';
            var willOpen = !open;
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            body.style.display = willOpen ? '' : 'none';
            if (willOpen && !mntInsightsLoaded && !mntInsightsLoading) {
                loadMaintenanceInsights(false);
            }
        });
    }

    var refresh = document.getElementById('mntRefresh');
    if (refresh) {
        refresh.addEventListener('click', function (e) {
            e.stopPropagation();
            mntInsightsLoaded = false;
            try { sessionStorage.removeItem(mntInsightsCacheKey()); } catch (err) {}
            loadMaintenanceInsights(true);
        });
    }
    var anomList = document.getElementById('mntAnomList');
    if (anomList) {
        anomList.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-mnt-edit]');
            if (btn && typeof editMaintenance === 'function') editMaintenance(btn.getAttribute('data-mnt-edit'));
        });
    }
    var resumoBtn = document.getElementById('mntResumoBtn');
    if (resumoBtn) resumoBtn.addEventListener('click', maintenanceGerarResumoIA);
}

function renderMaintenanceInsights(json) {
    mntLastData = json;
    var panel = document.getElementById('mntInsights');
    var summary = document.getElementById('mntSummary');
    if (!panel || !summary) return;
    panel.style.display = '';
    document.getElementById('mntResumoIa').style.display = '';

    var venc = json.prev_vencidos || 0, aVenc = json.prev_a_vencer || 0;
    var anErr = json.anom_erros || 0, anAvi = json.anom_avisos || 0;
    var totalErros = venc + anErr;
    panel.classList.remove('has-erros', 'has-avisos');
    if (totalErros > 0) panel.classList.add('has-erros');
    else if (aVenc + anAvi > 0) panel.classList.add('has-avisos');

    summary.innerHTML = '<i class="fas fa-clipboard-check" style="color:' + (totalErros > 0 ? '#dc2626' : (aVenc + anAvi > 0 ? '#f59e0b' : '#16a34a')) + '"></i> Insights de manutenção' +
        ((venc + aVenc) > 0 ? '<span class="mnt-pill ' + (venc > 0 ? 'erro' : 'aviso') + '">' + (venc + aVenc) + ' preventiva(s)</span>' : '') +
        ((anErr + anAvi) > 0 ? '<span class="mnt-pill ' + (anErr > 0 ? 'erro' : 'aviso') + '">' + (anErr + anAvi) + ' anomalia(s)</span>' : '') +
        ((venc + aVenc + anErr + anAvi) === 0 ? '<span class="mnt-pill ok">tudo certo</span>' : '');

    // Preventivos
    var prev = json.preventivos || [];
    document.getElementById('mntPrevList').innerHTML = prev.length ? prev.map(function (p) {
        var icon = p.severidade === 'erro' ? 'fa-circle-xmark' : 'fa-clock';
        return '<div class="mnt-item"><span class="mnt-sev ' + p.severidade + '"><i class="fas ' + icon + '"></i></span>' +
            '<div class="mnt-txt"><strong>' + mntEsc(p.placa) + '</strong> — ' + mntEsc(p.motivo) + '</div></div>';
    }).join('') : '<div class="mnt-empty">Nenhuma manutenção preventiva pendente.</div>';

    // Anomalias
    var anom = json.anomalias || [];
    document.getElementById('mntAnomList').innerHTML = anom.length ? anom.map(function (a) {
        var dataBr = a.data ? a.data.split('-').reverse().join('/') : '';
        var icon = a.severidade === 'erro' ? 'fa-circle-xmark' : 'fa-triangle-exclamation';
        var motivos = (a.motivos || []).map(function (m) { return mntEsc(m.msg); }).join(' ');
        return '<div class="mnt-item"><span class="mnt-sev ' + a.severidade + '"><i class="fas ' + icon + '"></i></span>' +
            '<div class="mnt-txt"><strong>' + mntEsc(dataBr) + '</strong> · ' + mntEsc(a.veiculo) + ' · ' + mntEsc(a.tipo) + ' — ' + motivos + '</div>' +
            '<button type="button" class="btn-icon mnt-edit" data-mnt-edit="' + mntEsc(a.id) + '" title="Abrir manutenção"><i class="fas fa-edit"></i></button></div>';
    }).join('') : '<div class="mnt-empty">Nenhuma anomalia encontrada.</div>';

    // Custo por veículo
    var custo = json.custo_veiculos || [];
    document.getElementById('mntCustoList').innerHTML = custo.length ? custo.map(function (c) {
        var porKm = c.por_km != null ? ('R$ ' + Number(c.por_km).toFixed(2).replace('.', ',') + '/km') : '—';
        var total = 'R$ ' + Number(c.total || 0).toFixed(2).replace('.', ',');
        return '<div class="mnt-custo-row"><span><strong>' + mntEsc(c.placa) + '</strong></span><span>' + total + ' · ' + porKm + '</span></div>';
    }).join('') : '<div class="mnt-empty">Sem dados de custo.</div>';
}

function loadMaintenanceInsights(forceRefresh) {
    if (mntInsightsLoading) return;
    var panel = document.getElementById('mntInsights');
    var summary = document.getElementById('mntSummary');
    if (!panel) return;
    panel.style.display = '';

    if (!forceRefresh) {
        var hit = mntReadInsightsCache();
        if (hit && hit.success) {
            renderMaintenanceInsights(hit);
            mntInsightsLoaded = true;
            return;
        }
    }

    mntInsightsLoading = true;
    if (summary) summary.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analisando manutenções…';
    var url = MNT_INSIGHTS_URL;
    if (forceRefresh) url += (url.indexOf('?') >= 0 ? '&' : '?') + 'refresh=1&_=' + Date.now();
    fetch(url, { credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (json) {
            if (json && json.success) {
                mntWriteInsightsCache(json);
                renderMaintenanceInsights(json);
                mntInsightsLoaded = true;
            } else {
                panel.style.display = 'none';
            }
        })
        .catch(function () { panel.style.display = 'none'; })
        .then(function () { mntInsightsLoading = false; });
}

function maintenanceGerarResumoIA() {
    if (!mntLastData) return;
    var btn = document.getElementById('mntResumoBtn');
    var out = document.getElementById('mntResumoTexto');
    if (btn) { btn.disabled = true; btn.dataset.html = btn.innerHTML; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando…'; }
    if (out) { out.classList.add('text-muted'); out.textContent = 'A IA está analisando as manutenções…'; }
    var headers = (typeof sfMutationHeaders === 'function') ? sfMutationHeaders({ 'Content-Type': 'application/json' }) : { 'Content-Type': 'application/json' };
    if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__ && !headers['X-CSRF-Token']) headers['X-CSRF-Token'] = window.__SF_CSRF__;
    fetch('../api/maintenance_resumo_ia.php', { method: 'POST', credentials: 'include', headers: headers, body: JSON.stringify({ dados: mntLastData }) })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (o) {
            var d = o.d || {};
            if (o.ok && d.success) { if (out) { out.classList.remove('text-muted'); out.textContent = d.resumo; } }
            else if (out) { out.classList.add('text-muted'); out.textContent = d.message || d.error || 'Não foi possível gerar o resumo.'; }
        })
        .catch(function () { if (out) out.textContent = 'Erro de comunicação ao gerar o resumo.'; })
        .then(function () { if (btn) { btn.disabled = false; if (btn.dataset.html) btn.innerHTML = btn.dataset.html; } });
}

document.addEventListener('DOMContentLoaded', function() {
    initializePage();
    initializeModals();
    loadVehicles();
    loadFornecedoresDatalist();
    setupMntCollapsiblePanels();
    setupMaintenanceInsights();
    setupAlertAndScoreClicks();
    setupFinanceiroAutoSum();
    // Filtro "Período" agora é o widget compartilhado sf-periodo.js (auto-wire por data-*).
});

// Soma peças + mão de obra + outros no Custo Total (e no Valor, se vazio).
function setupFinanceiroAutoSum() {
    var ids = ['valor_pecas', 'valor_mao_obra', 'valor_outros'];
    var recalc = function () {
        var soma = ids.reduce(function (s, id) { var el = document.getElementById(id); return s + (el ? (parseFloat(el.value) || 0) : 0); }, 0);
        if (soma > 0) {
            var ct = document.getElementById('custo_total'); if (ct) ct.value = soma.toFixed(2);
            var v = document.getElementById('valor'); if (v && !v.value) v.value = soma.toFixed(2);
        }
    };
    ids.forEach(function (id) { var el = document.getElementById(id); if (el) el.addEventListener('input', recalc); });
}

function initializePage() {
    // Setup event listeners
    setupEventListeners();
    
    // Setup table buttons
    setupTableButtons();
    
    // Setup filters
    setupFilters();
}

function setupEventListeners() {
    // Filter button
    const filterBtn = document.getElementById('filterBtn');
    if (filterBtn) {
        filterBtn.addEventListener('click', function() {
            const filterSection = document.querySelector('.filter-section');
            if (filterSection) {
                filterSection.classList.toggle('active');
            }
        });
    }

    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    if (cancelDeleteBtn) {
        cancelDeleteBtn.addEventListener('click', function () {
            const delModal = document.getElementById('deleteMaintenanceModal');
            if (delModal) delModal.classList.remove('active');
        });
    }

    // Add Maintenance button
    const mannEmptyCreate = document.getElementById('mannEmptyCreateBtn');
    if (mannEmptyCreate) {
        mannEmptyCreate.addEventListener('click', function () {
            const addBtn = document.getElementById('addMaintenanceBtn');
            if (addBtn) addBtn.click();
        });
    }

    const addMaintenanceBtn = document.getElementById('addMaintenanceBtn');
    if (addMaintenanceBtn) {
        addMaintenanceBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openMaintenanceModalBlank('Nova Manutenção');
        });
    }

    const cancelMaintenanceBtn = document.getElementById('cancelMaintenanceBtn');
    if (cancelMaintenanceBtn) {
        cancelMaintenanceBtn.addEventListener('click', function () {
            document.getElementById('maintenanceModal').classList.remove('active');
        });
    }

    // Close buttons for modals (apenas .close-modal e botões com data-close-modal)
    document.querySelectorAll('.close-modal, [data-close-modal]').forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.classList.remove('active');
            }
        });
    });

    // Close modals when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(event) {
            if (event.target === this) {
                this.classList.remove('active');
            }
        });
    });

    // Save maintenance button
    const saveMaintenanceBtn = document.getElementById('saveMaintenanceBtn');
    if (saveMaintenanceBtn) {
        saveMaintenanceBtn.addEventListener('click', saveMaintenance);
    }
    // Mostrar/ocultar data conclusão e checklist quando status mudar
    const statusSel = document.getElementById('status_manutencao_id');
    if (statusSel) {
        statusSel.addEventListener('change', toggleConclusaoFields);
    }
    var dcEl = document.getElementById('data_conclusao');
    if (dcEl) {
        dcEl.addEventListener('change', function () {
            var cv = document.getElementById('conta_vencimento');
            if (!cv || cv.value) return;
            if (!dcEl.value) return;
            var d = new Date(dcEl.value + 'T12:00:00');
            d.setDate(d.getDate() + 30);
            cv.value = d.toISOString().slice(0, 10);
        });
    }
    // Upload anexo
    const uploadAnexoBtn = document.getElementById('uploadAnexoBtn');
    if (uploadAnexoBtn) {
        uploadAnexoBtn.addEventListener('click', uploadAnexo);
    }
    // Deletar anexo (delegado no modal)
    const maintenanceModal = document.getElementById('maintenanceModal');
    if (maintenanceModal) {
        maintenanceModal.addEventListener('click', function(e) {
            var del = e.target.closest('.delete-anexo-btn');
            if (!del) return;
            e.preventDefault();
            var id = del.getAttribute('data-id');
            if (!id) return;
            mntConfirm('Excluir este anexo?', { okLabel: 'Excluir' }).then(function (ok) {
                if (!ok) return;
                var fd = new FormData();
                fd.append('action', 'delete');
                fd.append('id', id);
                fetch('../api/manutencao_anexos.php', { method: 'POST', credentials: 'include', headers: mntMutationHeaders(), body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            var mid = document.getElementById('manutencaoId') && document.getElementById('manutencaoId').value;
                            if (mid) loadAnexos(parseInt(mid, 10));
                            mntToast('Anexo excluído.', 'success');
                        } else {
                            mntToast(data.error || 'Erro ao excluir.', 'error');
                        }
                    });
            });
        });
    }
}

function initializeModals() {
    // Add close functionality to all modals
    document.querySelectorAll('.modal').forEach(modal => {
        // Close when clicking the X button
        const closeBtn = modal.querySelector('.close-modal');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => modal.classList.remove('active'));
        }

        // Close when clicking outside the modal
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });
        // Escape: js/modal_a11y.js (global)
    });
}

// Delegação: botões da tabela funcionam mesmo após trocar de página (AJAX)
function setupTableButtons() {
    const table = document.getElementById('maintenanceTable');
    if (!table) return;
    table.addEventListener('click', function(e) {
        const btn = e.target.closest('.view-btn');
        if (btn) { e.preventDefault(); viewMaintenance(btn.dataset.id); return; }
        const editBtn = e.target.closest('.edit-btn');
        if (editBtn) { e.preventDefault(); editMaintenance(editBtn.dataset.id); return; }
        const delBtn = e.target.closest('.delete-btn');
        if (delBtn) { e.preventDefault(); showDeleteConfirmation(delBtn.dataset.id); return; }
        const histBtn = e.target.closest('.historico-veiculo-btn');
        if (histBtn) {
            e.preventDefault();
            openHistoricoVeiculo(histBtn.dataset.veiculoId, histBtn.dataset.placa || '');
            return;
        }
        const dupBtn = e.target.closest('.duplicate-btn');
        if (dupBtn) { e.preventDefault(); duplicateMaintenance(dupBtn.dataset.id); return; }
        const osBtn = e.target.closest('.os-btn');
        if (osBtn) { e.preventDefault(); printOrdemServico(osBtn.dataset.id); return; }
    });
    setupPaginationAjax();
}

function getPaginationParams() {
    const form = document.getElementById('formFiltroPeriodo');
    if (!form) return {};
    const d = new FormData(form);
    return {
        page: 1,
        per_page: d.get('per_page') || '10',
        data_inicio: d.get('data_inicio') || '',
        data_fim: d.get('data_fim') || '',
        order: d.get('order') || 'data_manutencao',
        dir: d.get('dir') || 'DESC',
        q: d.get('q') || '',
        veiculo_id: d.get('veiculo_id') || '',
        tipo_classe: d.get('tipo_classe') || '',
        status_id: d.get('status_id') || '',
        fornecedor: d.get('fornecedor') || ''
    };
}

function appendListParams(params, p) {
    params.set('list', '1');
    params.set('page', String(p.page || 1));
    params.set('per_page', p.per_page || '10');
    if (p.data_inicio) params.set('data_inicio', p.data_inicio);
    if (p.data_fim) params.set('data_fim', p.data_fim);
    if (p.order) params.set('order', p.order);
    if (p.dir) params.set('dir', p.dir);
    if (p.q) params.set('q', p.q);
    if (p.veiculo_id) params.set('veiculo_id', p.veiculo_id);
    if (p.tipo_classe) params.set('tipo_classe', p.tipo_classe);
    if (p.status_id) params.set('status_id', p.status_id);
    if (p.fornecedor) params.set('fornecedor', p.fornecedor);
}

function buildListUrl(page) {
    const p = getPaginationParams();
    p.page = page;
    const params = new URLSearchParams();
    appendListParams(params, p);
    return '../api/manutencoes.php?' + params.toString();
}

function buildPageUrl(page) {
    const form = document.getElementById('formFiltroPeriodo');
    if (!form) return '?page=' + page;
    const d = new FormData(form);
    const params = new URLSearchParams();
    params.set('page', String(page));
    params.set('per_page', d.get('per_page') || '10');
    if (d.get('data_inicio')) params.set('data_inicio', d.get('data_inicio'));
    if (d.get('data_fim')) params.set('data_fim', d.get('data_fim'));
    if (d.get('order')) params.set('order', d.get('order'));
    if (d.get('dir')) params.set('dir', d.get('dir'));
    if (d.get('q')) params.set('q', d.get('q'));
    if (d.get('veiculo_id')) params.set('veiculo_id', d.get('veiculo_id'));
    if (d.get('tipo_classe')) params.set('tipo_classe', d.get('tipo_classe'));
    if (d.get('status_id')) params.set('status_id', d.get('status_id'));
    if (d.get('fornecedor')) params.set('fornecedor', d.get('fornecedor'));
    if (!document.body.classList.contains('manutencoes-modern')) {
        params.set('classic', '1');
    }
    return '?' + params.toString();
}

function rowHtml(m) {
    const data = m.data_manutencao ? m.data_manutencao.split('-').reverse().join('/') : '';
    const placa = (m.veiculo_placa || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const tipo = (m.tipo_nome || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const desc = (m.descricao || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').substring(0, 200);
    const forn = (m.fornecedor || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const status = (m.status_nome || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const valor = (parseFloat(m.valor) || 0).toFixed(2).replace('.', ',');
    const custo12m = (parseFloat(m.custo_veiculo_12m) || 0).toFixed(2).replace('.', ',');
    const vid = parseInt(m.veiculo_id, 10) || 0;
    return '<tr>' +
        '<td>' + data + '</td>' +
        '<td>' + placa + '</td>' +
        '<td>' + tipo + '</td>' +
        '<td>' + desc + '</td>' +
        '<td>' + forn + '</td>' +
        '<td>' + status + '</td>' +
        '<td>R$ ' + valor + '</td>' +
        '<td title="Custo do veículo nos últimos 12 meses">R$ ' + custo12m + '</td>' +
        '<td class="actions">' +
        '<button class="btn-icon os-btn" data-id="' + m.id + '" title="Imprimir ordem de serviço"><i class="fas fa-print"></i></button> ' +
        '<button class="btn-icon duplicate-btn" data-id="' + m.id + '" title="Duplicar manutenção"><i class="fas fa-copy"></i></button> ' +
        '<button class="btn-icon historico-veiculo-btn" data-veiculo-id="' + vid + '" data-placa="' + placa + '" title="Histórico do veículo"><i class="fas fa-history"></i></button> ' +
        '<button class="btn-icon view-btn" data-id="' + m.id + '" title="Ver detalhes"><i class="fas fa-eye"></i></button> ' +
        '<button class="btn-icon edit-btn" data-id="' + m.id + '" title="Editar"><i class="fas fa-edit"></i></button> ' +
        '<button class="btn-icon delete-btn" data-id="' + m.id + '" title="Excluir"><i class="fas fa-trash"></i></button>' +
        '</td></tr>';
}

function syncMannEmptyState(isEmpty) {
    const el = document.getElementById('mannEmptyState');
    const wrap = document.getElementById('mannTableWrap');
    if (!el || !wrap || !document.body.classList.contains('manutencoes-modern')) return;
    el.classList.toggle('visible', !!isEmpty);
    wrap.style.display = isEmpty ? 'none' : '';
}

function updatePaginationUI(data) {
    const total = data.total || 0;
    const page = data.pagina_atual || 1;
    const totalPag = data.total_paginas || 1;
    const prev = document.querySelector('#paginationContainer .pagination-prev');
    const next = document.querySelector('#paginationContainer .pagination-next');
    const info = document.getElementById('paginationInfo');
    if (info) {
        info.textContent = totalPag > 1 ? 'Página ' + page + ' de ' + totalPag + ' (' + total + ' registros)' : total + ' registros';
    }
    if (prev) {
        prev.classList.toggle('disabled', page <= 1);
        prev.setAttribute('data-page', page - 1);
        prev.href = buildPageUrl(page - 1);
    }
    if (next) {
        next.classList.toggle('disabled', page >= totalPag);
        next.setAttribute('data-page', page + 1);
        next.href = buildPageUrl(page + 1);
    }
    const container = document.getElementById('paginationContainer');
    if (container) {
        container.setAttribute('data-page', page);
        container.setAttribute('data-total-paginas', totalPag);
        container.setAttribute('data-total', total);
    }
}

function loadPageViaAjax(page) {
    const tbody = document.getElementById('maintenanceTableBody');
    if (!tbody) return Promise.resolve();
    const url = buildListUrl(page);
    const container = tbody.closest('.data-table-container') || tbody.closest('.fornc-table-wrap') || tbody.closest('.mann-table-wrap');
    // Anti-flash: só marca "loading" se a resposta demorar (>180ms). Paginação rápida não pisca.
    const _mannLoadTimer = setTimeout(function () { if (container) container.classList.add('loading'); }, 180);
    return fetch(url, { credentials: 'include' })
        .then(r => r.json())
        .then(data => {
            clearTimeout(_mannLoadTimer);
            if (container) container.classList.remove('loading');
            if (!data.success || !data.data) return;
            const rows = data.data;
            const isEmpty = !rows.length;
            tbody.innerHTML = isEmpty ? '' : rows.map(rowHtml).join('');
            syncMannEmptyState(isEmpty);
            updatePaginationUI(data);
        })
        .catch(function() {
            clearTimeout(_mannLoadTimer);
            if (container) container.classList.remove('loading');
        });
}

function setupPaginationAjax() {
    const container = document.getElementById('paginationContainer');
    if (!container) return;
    container.addEventListener('click', function(e) {
        const link = e.target.closest('a.pagination-btn');
        if (!link || link.classList.contains('disabled')) {
            if (link) e.preventDefault();
            return;
        }
        e.preventDefault();
        const page = parseInt(link.getAttribute('data-page'), 10);
        if (!page || page < 1) return;
        loadPageViaAjax(page).then(function() {
            if (typeof history !== 'undefined' && history.pushState) {
                history.pushState({ page: page }, '', buildPageUrl(page));
            }
        });
    });
    window.addEventListener('popstate', function(e) {
        const page = e.state && e.state.page;
        if (page && page >= 1) loadPageViaAjax(page);
    });
}

function viewMaintenance(id) {
    fetch(`../api/manutencoes.php?id=${id}`, {
        method: 'GET',
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMaintenanceDetails(data.data);
        } else {
            mntToast('Erro ao carregar dados da manutenção: ' + data.error, 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        mntToast('Erro ao carregar dados da manutenção', 'error');
    });
}

function showMaintenanceDetails(maintenance) {
    const modal = document.getElementById('maintenanceModal');
    if (modal) {
        // Preencher os campos do modal com os dados
        document.getElementById('modalTitle').textContent = 'Detalhes da Manutenção';
        enableMaintenanceForm(false);
        
        // Preencher os campos
        fillMaintenanceForm(maintenance);
        
        modal.classList.add('active');
    }
}

function editMaintenance(id) {
    fetch(`../api/manutencoes.php?id=${id}`, {
        method: 'GET',
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const modal = document.getElementById('maintenanceModal');
            if (modal) {
                document.getElementById('modalTitle').textContent = 'Editar Manutenção';
                enableMaintenanceForm(true);
                
                // Preencher os campos
                fillMaintenanceForm(data.data);
                
                modal.classList.add('active');
            }
        } else {
            mntToast('Erro ao carregar dados da manutenção: ' + data.error, 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        mntToast('Erro ao carregar dados da manutenção', 'error');
    });
}

function fillMaintenanceForm(data) {
    document.getElementById('manutencaoId').value = data.id;
    document.getElementById('data_manutencao').value = data.data_manutencao;
    const dc = document.getElementById('data_conclusao');
    if (dc) dc.value = data.data_conclusao || '';
    document.getElementById('veiculo_id').value = data.veiculo_id;
    document.getElementById('tipo_manutencao_id').value = data.tipo_manutencao_id;
    document.getElementById('componente_id').value = data.componente_id;
    document.getElementById('status_manutencao_id').value = data.status_manutencao_id;
    document.getElementById('km_atual').value = data.km_atual;
    document.getElementById('fornecedor').value = data.fornecedor || '';
    document.getElementById('valor').value = data.valor;
    var _fp = function (id, v) { var el = document.getElementById(id); if (el) el.value = (v != null && parseFloat(v) > 0) ? v : ''; };
    _fp('valor_pecas', data.valor_pecas);
    _fp('valor_mao_obra', data.valor_mao_obra);
    _fp('valor_outros', data.valor_outros);
    document.getElementById('custo_total').value = data.custo_total || '';
    document.getElementById('nota_fiscal').value = data.nota_fiscal || '';
    updateContaPagarLink(data.conta_pagar_id || null);
    var gerarCp = document.getElementById('gerar_conta_pagar');
    if (gerarCp) gerarCp.checked = !data.conta_pagar_id;
    document.getElementById('descricao').value = data.descricao;
    document.getElementById('descricao_servico').value = data.descricao_servico;
    let obs = data.observacoes || '';
    const checklistMatch = obs.match(/\n?Checklist:\s*(.+?)(?=\n|$)/i);
    if (checklistMatch) {
        obs = obs.replace(/\n?Checklist:\s*.+?(?=\n|$)/i, '').trim();
        const c = checklistMatch[1].toLowerCase();
        const o = document.getElementById('checklist_oleo');
        const f = document.getElementById('checklist_filtro');
        const t = document.getElementById('checklist_teste');
        if (o) o.checked = c.indexOf('óleo') !== -1;
        if (f) f.checked = c.indexOf('filtro') !== -1;
        if (t) t.checked = c.indexOf('teste') !== -1;
    } else {
        document.querySelectorAll('#checklist_oleo, #checklist_filtro, #checklist_teste').forEach(function(cb) { if (cb) cb.checked = false; });
    }
    document.getElementById('observacoes').value = obs;
    document.getElementById('responsavel_aprovacao').value = data.responsavel_aprovacao;
    toggleConclusaoFields();
    var anexosSec = document.getElementById('anexosSection');
    if (anexosSec) {
        anexosSec.style.display = 'block';
        loadAnexos(data.id);
    }
}

function toggleConclusaoFields() {
    const sel = document.getElementById('status_manutencao_id');
    const wrapDc = document.getElementById('wrap_data_conclusao');
    const wrapCk = document.getElementById('wrap_checklist');
    const wrapConta = document.getElementById('wrap_gerar_conta');
    if (!sel || !wrapDc) return;
    const text = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : '';
    const show = text.toLowerCase().indexOf('conclu') !== -1;
    wrapDc.style.display = show ? 'block' : 'none';
    if (wrapCk) wrapCk.style.display = show ? 'block' : 'none';
    if (wrapConta) wrapConta.style.display = show ? 'block' : 'none';
    if (show) {
        document.getElementById('data_conclusao').setAttribute('required', 'required');
        var dc = document.getElementById('data_conclusao');
        var cv = document.getElementById('conta_vencimento');
        if (dc && dc.value && cv && !cv.value) {
            var d = new Date(dc.value + 'T12:00:00');
            d.setDate(d.getDate() + 30);
            cv.value = d.toISOString().slice(0, 10);
        }
    } else {
        document.getElementById('data_conclusao').removeAttribute('required');
    }
}

function updateContaPagarLink(contaId) {
    var wrap = document.getElementById('contaPagarLinkWrap');
    var link = document.getElementById('contaPagarLink');
    if (!wrap || !link) return;
    if (contaId) {
        wrap.style.display = 'block';
        link.href = 'contas_pagar.php?highlight=' + encodeURIComponent(contaId);
        link.textContent = 'Ver conta a pagar #' + contaId;
    } else {
        wrap.style.display = 'none';
    }
}

function setupNfOcr() {
    var btn = document.getElementById('nfOcrBtn');
    var file = document.getElementById('nfOcrFile');
    if (!btn || !file) return;
    btn.addEventListener('click', function () { file.click(); });
    file.addEventListener('change', function () {
        if (!file.files || !file.files[0]) return;
        var fd = new FormData();
        fd.append('image', file.files[0]);
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        fetch('../api/maintenance_ocr_nf.php', { method: 'POST', credentials: 'include', headers: mntMutationHeaders(), body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success || !data.data) {
                    mntToast(data.error || data.message || 'Não foi possível ler a NF.', 'error');
                    return;
                }
                var d = data.data;
                if (d.nota_fiscal) document.getElementById('nota_fiscal').value = d.nota_fiscal;
                if (d.fornecedor) document.getElementById('fornecedor').value = d.fornecedor;
                if (d.valor != null && d.valor > 0) {
                    document.getElementById('valor').value = d.valor;
                    var ct = document.getElementById('custo_total');
                    if (ct && !ct.value) ct.value = d.valor;
                }
                if (d.data_manutencao) {
                    var dm = document.getElementById('data_manutencao');
                    if (dm && !dm.value) dm.value = d.data_manutencao;
                }
                if (d.descricao) {
                    var desc = document.getElementById('descricao');
                    if (desc && !desc.value.trim()) desc.value = d.descricao;
                    var ds = document.getElementById('descricao_servico');
                    if (ds && !ds.value.trim()) ds.value = d.descricao;
                }
                mntToast('NF lida com ' + (data.provider || 'IA') + '. Revise os campos.', 'success');
            })
            .catch(function () { mntToast('Erro ao ler NF.', 'error'); })
            .then(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-camera"></i>';
                file.value = '';
            });
    });
}

var _mntImportRows = [];

function mntSplitCsvLine(line, delim) {
    var out = [], cur = '', q = false;
    for (var i = 0; i < line.length; i++) {
        var c = line[i];
        if (c === '"') { q = !q; continue; }
        if (!q && c === delim) { out.push(cur.trim()); cur = ''; continue; }
        cur += c;
    }
    out.push(cur.trim());
    return out;
}

function mntParseImportCsv(text) {
    var lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n').filter(function (l) { return l.trim() !== ''; });
    if (!lines.length) return [];
    var delim = (lines[0].match(/;/g) || []).length >= (lines[0].match(/,/g) || []).length ? ';' : ',';
    var head = mntSplitCsvLine(lines[0], delim).map(function (h) { return h.toLowerCase().replace(/^\uFEFF/, '').trim(); });
    var idx = {};
    ['data', 'placa', 'tipo', 'componente', 'fornecedor', 'valor', 'km', 'descricao', 'descricao_servico', 'nota_fiscal', 'status'].forEach(function (k) {
        idx[k] = head.indexOf(k);
    });
    var rows = [];
    for (var i = 1; i < lines.length; i++) {
        var c = mntSplitCsvLine(lines[i], delim);
        var get = function (k) { return idx[k] >= 0 ? (c[idx[k]] || '') : ''; };
        if (!get('placa')) continue;
        rows.push({
            data: get('data'),
            placa: get('placa'),
            tipo: get('tipo'),
            componente: get('componente'),
            fornecedor: get('fornecedor'),
            valor: get('valor'),
            km: get('km'),
            descricao: get('descricao'),
            descricao_servico: get('descricao_servico'),
            nota_fiscal: get('nota_fiscal'),
            status: get('status')
        });
    }
    return rows;
}

function mntRenderImportPreview() {
    var tb = document.getElementById('mntImportPreview');
    var info = document.getElementById('mntImportInfo');
    var btn = document.getElementById('mntImportSaveBtn');
    if (!tb) return;
    tb.innerHTML = _mntImportRows.slice(0, 30).map(function (r) {
        return '<tr><td>' + mntEsc(r.data) + '</td><td>' + mntEsc(r.placa) + '</td><td>' + mntEsc(r.tipo || '-') +
            '</td><td>' + mntEsc(r.componente || '-') + '</td><td>' + mntEsc(r.fornecedor || '-') + '</td><td>' + mntEsc(r.valor) + '</td></tr>';
    }).join('');
    if (info) info.textContent = _mntImportRows.length + ' linha(s).' + (_mntImportRows.length > 30 ? ' Exibindo as 30 primeiras.' : '');
    if (btn) btn.disabled = _mntImportRows.length === 0;
}

function setupMaintenanceImport() {
    var openBtn = document.getElementById('mntImportBtn');
    var modal = document.getElementById('mntImportModal');
    var file = document.getElementById('mntImportFile');
    var saveBtn = document.getElementById('mntImportSaveBtn');
    if (!openBtn || !modal) return;
    openBtn.addEventListener('click', function () {
        _mntImportRows = [];
        mntRenderImportPreview();
        var res = document.getElementById('mntImportResult');
        if (res) res.textContent = '';
        modal.classList.add('active');
    });
    document.querySelectorAll('[data-close-mnt-import]').forEach(function (el) {
        el.addEventListener('click', function () { modal.classList.remove('active'); });
    });
    if (file) {
        file.addEventListener('change', function () {
            if (!file.files || !file.files[0]) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                _mntImportRows = mntParseImportCsv(String(e.target.result || ''));
                mntRenderImportPreview();
            };
            reader.readAsText(file.files[0], 'UTF-8');
        });
    }
    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            if (!_mntImportRows.length) return;
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importando…';
            var res = document.getElementById('mntImportResult');
            fetch('../api/maintenance_import.php', {
                method: 'POST',
                credentials: 'include',
                headers: mntMutationHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ rows: _mntImportRows })
            })
                .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
                .then(function (o) {
                    var d = o.d || {};
                    if (o.ok && d.success) {
                        var msg = d.message || (d.inserted + ' importada(s).');
                        if (d.errors && d.errors.length) msg += ' Avisos: ' + d.errors.slice(0, 3).join(' ');
                        if (res) { res.style.color = '#16a34a'; res.textContent = msg; }
                        mntToast(msg, 'success');
                        mntReloadListPage(1);
                    } else {
                        var err = d.error || d.message || 'Erro ao importar.';
                        if (res) { res.style.color = '#dc2626'; res.textContent = err; }
                        mntToast(err, 'error');
                    }
                })
                .catch(function () {
                    if (res) { res.style.color = '#dc2626'; res.textContent = 'Erro de comunicação.'; }
                })
                .then(function () {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-file-import"></i> Importar';
                });
        });
    }
}

function loadAnexos(manutencaoId) {
    if (!manutencaoId) return;
    fetch('../api/manutencao_anexos.php?manutencao_id=' + manutencaoId, { credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.data) {
                renderAnexos(data.data);
            } else {
                renderAnexos([]);
            }
        })
        .catch(function() { renderAnexos([]); });
}

function renderAnexos(list) {
    var el = document.getElementById('anexosList');
    if (!el) return;
    if (!list.length) {
        el.innerHTML = '<p class="text-muted">Nenhum anexo. Envie NF ou foto acima.</p>';
        return;
    }
    el.innerHTML = list.map(function(a) {
        return '<div class="anexo-item"><a href="' + mntSafeUrl(a.url) + '" target="_blank" rel="noopener">' + mntEsc(a.nome_original || 'Anexo') + '</a> ' +
            '<button type="button" class="btn-icon delete-anexo-btn" data-id="' + mntEsc(a.id) + '" title="Excluir"><i class="fas fa-trash"></i></button></div>';
    }).join('');
}

function uploadAnexo() {
    var mid = document.getElementById('manutencaoId') && document.getElementById('manutencaoId').value;
    var fileInput = document.getElementById('anexoFile');
    if (!mid || !fileInput || !fileInput.files || !fileInput.files.length) {
        mntToast('Salve a manutenção primeiro ou selecione um arquivo.', 'error');
        return;
    }
    var fd = new FormData();
    fd.append('manutencao_id', mid);
    fd.append('file', fileInput.files[0]);
    var btn = document.getElementById('uploadAnexoBtn');
    if (btn) btn.disabled = true;
    fetch('../api/manutencao_anexos.php', { method: 'POST', credentials: 'include', headers: mntMutationHeaders(), body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (btn) btn.disabled = false;
            if (data.success) {
                fileInput.value = '';
                loadAnexos(parseInt(mid, 10));
            } else {
                mntToast(data.error || 'Erro ao enviar anexo.', 'error');
            }
        })
        .catch(function() {
            if (btn) btn.disabled = false;
            mntToast('Erro ao enviar anexo.', 'error');
        });
}

function saveMaintenance() {
    const form = document.getElementById('maintenanceForm');
    if (!form) return;

    // Validação básica
    const requiredFields = [
        'data_manutencao',
        'veiculo_id',
        'tipo_manutencao_id',
        'componente_id',
        'status_manutencao_id',
        'km_atual',
        'valor',
        'descricao',
        'descricao_servico',
        'responsavel_aprovacao'
    ];

    let isValid = true;
    requiredFields.forEach(field => {
        const input = document.getElementById(field);
        if (!input.value.trim()) {
            isValid = false;
            input.classList.add('error');
        } else {
            input.classList.remove('error');
        }
    });

    if (!isValid) {
        mntToast('Por favor, preencha todos os campos obrigatórios.', 'error');
        return;
    }

    const statusSel = document.getElementById('status_manutencao_id');
    const statusTexto = statusSel.options[statusSel.selectedIndex] ? statusSel.options[statusSel.selectedIndex].text : '';
    const isConcluida = statusTexto.toLowerCase().indexOf('conclu') !== -1;
    if (isConcluida && !document.getElementById('data_conclusao').value.trim()) {
        mntToast('Quando o status é Concluída, a Data de Conclusão é obrigatória.', 'error');
        document.getElementById('data_conclusao').focus();
        return;
    }
    let observacoes = document.getElementById('observacoes').value || '';
    const oleo = document.getElementById('checklist_oleo');
    const filtro = document.getElementById('checklist_filtro');
    const teste = document.getElementById('checklist_teste');
    if (isConcluida && (oleo || filtro || teste)) {
        const parts = [];
        if (oleo && oleo.checked) parts.push('Óleo trocado/verificado');
        if (filtro && filtro.checked) parts.push('Filtro trocado');
        if (teste && teste.checked) parts.push('Teste realizado');
        if (parts.length) observacoes = (observacoes ? observacoes + '\n' : '') + 'Checklist: ' + parts.join(', ');
    }
    const formData = {
        id: document.getElementById('manutencaoId').value,
        data_manutencao: document.getElementById('data_manutencao').value,
        data_conclusao: document.getElementById('data_conclusao').value || null,
        veiculo_id: document.getElementById('veiculo_id').value,
        tipo_manutencao_id: document.getElementById('tipo_manutencao_id').value,
        componente_id: document.getElementById('componente_id').value,
        status_manutencao_id: document.getElementById('status_manutencao_id').value,
        km_atual: document.getElementById('km_atual').value,
        fornecedor: document.getElementById('fornecedor').value,
        valor: document.getElementById('valor').value,
        custo_total: document.getElementById('custo_total').value,
        nota_fiscal: document.getElementById('nota_fiscal').value,
        descricao: document.getElementById('descricao').value,
        descricao_servico: document.getElementById('descricao_servico').value,
        observacoes: observacoes,
        responsavel_aprovacao: document.getElementById('responsavel_aprovacao').value,
        gerar_conta_pagar: !!(document.getElementById('gerar_conta_pagar') && document.getElementById('gerar_conta_pagar').checked),
        conta_vencimento: (document.getElementById('conta_vencimento') && document.getElementById('conta_vencimento').value) || null
    };

    // Determinar se é uma nova manutenção ou atualização
    const method = formData.id ? 'PUT' : 'POST';

    // Enviar dados
    fetch('../api/manutencoes.php', {
        method: method,
        headers: mntMutationHeaders({ 'Content-Type': 'application/json' }),
        credentials: 'include',
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            var msg = data.message;
            if (data.conta_message) msg += ' ' + data.conta_message;
            mntToast(msg, 'success');
            if (data.conta_pagar_id) updateContaPagarLink(data.conta_pagar_id);
            const modal = document.getElementById('maintenanceModal');
            if (modal) {
                modal.classList.remove('active');
            }
            mntReloadListPage(formData.id ? null : 1);
        } else {
            mntToast('Erro ao salvar manutenção: ' + data.error, 'error');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        mntToast('Erro ao salvar manutenção', 'error');
    });
}

function showDeleteConfirmation(id) {
    const modal = document.getElementById('deleteMaintenanceModal');
    if (modal) {
        modal.classList.add('active');
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        if (confirmBtn) {
            confirmBtn.onclick = () => deleteMaintenance(id);
        }
    }
}

function deleteMaintenance(id) {
    mntConfirm('Tem certeza que deseja excluir esta manutenção? Esta ação não pode ser desfeita.', { okLabel: 'Excluir' }).then(function (ok) {
        if (!ok) return;
        fetch(`../api/manutencoes.php?id=${id}`, {
            method: 'DELETE',
            credentials: 'include',
            headers: mntMutationHeaders()
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mntToast(data.message || 'Manutenção excluída.', 'success');
                const delModal = document.getElementById('deleteMaintenanceModal');
                if (delModal) delModal.classList.remove('active');
                setTimeout(function () { mntReloadListPage(); }, 400);
            } else {
                mntToast('Erro ao excluir manutenção: ' + data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mntToast('Erro ao excluir manutenção', 'error');
        });
    });
}

// Funções auxiliares
function loadVehicles() {
    fetch('../api/vehicle_data.php', {
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        const select = document.getElementById('veiculo_id');
        if (select && data.data) {
            const current = select.value;
            while (select.options.length > 1) select.remove(1);
            data.data.forEach(vehicle => {
                const option = document.createElement('option');
                option.value = vehicle.id;
                option.textContent = `${vehicle.placa} - ${vehicle.modelo}`;
                select.appendChild(option);
            });
            if (current) select.value = current;
        }
    })
    .catch(error => console.error('Erro ao carregar veículos:', error));
}

function setupFilters() {
    const clearBtn = document.getElementById('clearMaintenanceFilters');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            const base = document.body.classList.contains('manutencoes-modern') ? 'manutencoes.php' : 'manutencoes.php?classic=1';
            window.location.href = base;
        });
    }
}

// Store chart instances globally
let charts = {
    costs: null,
    types: null,
    status: null,
    evolution: null,
    topVehicles: null,
    components: null
};

// Add debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Add loading state
let isInitializingCharts = false;

/**
 * Initialize maintenance charts
 */
async function initializeMaintenanceCharts(preloadedData) {
    // Prevent multiple simultaneous initializations
    if (isInitializingCharts) {
        return;
    }

    try {
        isInitializingCharts = true;
        
        // Properly destroy existing charts
        for (const chartKey in charts) {
            if (charts[chartKey] instanceof Chart) {
                try {
                    charts[chartKey].destroy();
                } catch (e) {
                    console.warn(`Error destroying chart ${chartKey}:`, e);
                }
                charts[chartKey] = null;
            }
        }

        const canvasIds = ['maintenanceCostsChart', 'maintenanceTypesChart', 'maintenanceStatusChart', 
                          'maintenanceEvolutionChart', 'topVehiclesChart', 'componentsHeatmapChart'];
        const anyChartExists = Object.values(charts).some(c => c instanceof Chart);
        // Só limpa/redimensiona canvas se já existiam gráficos (reinit); na 1ª carga evita reflow/tremida
        if (anyChartExists) {
            canvasIds.forEach(id => {
                const canvas = document.getElementById(id);
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    canvas.width = canvas.offsetWidth;
                    canvas.height = canvas.offsetHeight;
                }
            });
            await new Promise(resolve => setTimeout(resolve, 50));
        }

        // Use preloaded data when provided (e.g. from manutencoes.php) to avoid double fetch and double init
        let data = preloadedData;
        if (!data || !data.success) {
            const response = await fetch('../includes/get_maintenance_data.php');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            data = await response.json();
        }
        
        // Check if the response indicates an error
        if (!data.success) {
            throw new Error(data.error || 'Erro desconhecido ao carregar dados');
        }
        
        const periodLabel = data.period_label || 'período selecionado';

        // Initialize cost chart
        const costCtx = document.getElementById('maintenanceCostsChart');
        if (costCtx && data.costs && data.costs.labels) {
            // Ensure we're working with a fresh context
            const ctx = costCtx.getContext('2d');
            
            // Create new chart instance
            charts.costs = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.costs.labels,
                    datasets: [
                        {
                            label: 'Manutenção Preventiva',
                            data: data.costs.preventiva,
                            backgroundColor: '#3b82f6'
                        },
                        {
                            label: 'Manutenção Corretiva',
                            data: data.costs.corretiva,
                            backgroundColor: '#ef4444'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: R$ ${context.raw.toFixed(2).replace('.', ',')}`;
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: 'Custos de Manutenção (' + periodLabel + ')'
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                        },
                        y: {
                            stacked: true,
                            title: {
                                display: true,
                                text: 'Custo (R$)'
                            }
                        }
                    }
                }
            });
        }

        // Initialize types chart
        const typesCtx = document.getElementById('maintenanceTypesChart');
        if (typesCtx && data.types && data.types.labels) {
            charts.types = new Chart(typesCtx, {
                type: 'pie',
                data: {
                    labels: data.types.labels,
                    datasets: [{
                        data: data.types.data,
                        backgroundColor: [
                            '#3b82f6',  // Azul
                            '#ef4444',  // Vermelho
                            '#10b981',  // Verde
                            '#f59e0b',  // Amarelo
                            '#6366f1'   // Índigo
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value * 100) / total).toFixed(1);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Initialize status chart
        const statusCtx = document.getElementById('maintenanceStatusChart');
        if (statusCtx && data.status && Array.isArray(data.status)) {
            charts.status = new Chart(statusCtx, {
                type: 'pie',
                data: {
                    labels: data.status.map(item => item.status),
                    datasets: [{
                        data: data.status.map(item => item.total),
                        backgroundColor: [
                            '#10b981',  // Verde - Concluída
                            '#f59e0b',  // Amarelo - Em andamento
                            '#3b82f6',  // Azul - Agendada
                            '#ef4444'   // Vermelho - Cancelada
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value * 100) / total).toFixed(1);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Initialize evolution chart
        const evolutionCtx = document.getElementById('maintenanceEvolutionChart');
        if (evolutionCtx && data.evolution && Array.isArray(data.evolution)) {
            charts.evolution = new Chart(evolutionCtx, {
                type: 'line',
                data: {
                    labels: data.evolution.map(item => {
                        const [year, month] = item.mes.split('-');
                        return `${month}/${year}`;
                    }),
                    datasets: [{
                        label: 'Total de Manutenções',
                        data: data.evolution.map(item => item.total),
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Total: ${context.raw}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Quantidade'
                            }
                        }
                    }
                }
            });
        }

        // Initialize top vehicles chart
        const vehiclesCtx = document.getElementById('topVehiclesChart');
        if (vehiclesCtx && data.top_vehicles && Array.isArray(data.top_vehicles)) {
            charts.topVehicles = new Chart(vehiclesCtx, {
                type: 'bar',
                data: {
                    labels: data.top_vehicles.map(item => item.placa),
                    datasets: [{
                        label: 'Custo Total',
                        data: data.top_vehicles.map(item => item.custo_total),
                        backgroundColor: '#3b82f6'
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let raw = context.raw;
                                    if (typeof raw === 'number') {
                                        return `R$ ${raw.toFixed(2).replace('.', ',')}`;
                                    } else if (!isNaN(Number(raw))) {
                                        return `R$ ${Number(raw).toFixed(2).replace('.', ',')}`;
                                    } else {
                                        return `R$ -`;
                                    }
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Custo Total (R$)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return `R$ ${value.toFixed(2).replace('.', ',')}`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Initialize components chart
        const componentsCtx = document.getElementById('componentsHeatmapChart');
        if (componentsCtx && data.components && Array.isArray(data.components)) {
            charts.components = new Chart(componentsCtx, {
                type: 'bar',
                data: {
                    labels: data.components.map(item => item.componente),
                    datasets: [{
                        label: 'Número de Falhas',
                        data: data.components.map(item => item.total_falhas),
                        backgroundColor: data.components.map(item => {
                            const maxFalhas = Math.max(...data.components.map(i => i.total_falhas));
                            const intensity = Math.min(item.total_falhas / maxFalhas, 1);
                            return `rgba(239, 68, 68, ${intensity})`; // Vermelho com opacidade variável
                        })
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Falhas: ${context.raw}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Número de Falhas'
                            },
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Mostra a seção de gráficos após tudo desenhado (evita tremida)
        const section = document.getElementById('analyticsSection');
        if (section && section.classList.contains('charts-pending')) {
            section.classList.remove('charts-pending');
            section.classList.add('charts-ready');
        }

    } catch (error) {
        console.error('Erro ao carregar dados dos gráficos:', error);
        console.error('Stack trace:', error.stack);
        const msg = (typeof sfSafeErrorMessage === 'function')
            ? sfSafeErrorMessage(error, 'Falha ao carregar os gráficos.')
            : (error && error.message) || 'Falha ao carregar os gráficos.';
        const errorMessage = `Erro ao carregar dados dos gráficos: ${msg}`;
        mntToast(errorMessage, 'error');
        const section = document.getElementById('analyticsSection');
        if (section) {
            section.classList.remove('charts-pending');
            section.classList.add('charts-ready');
        }
    } finally {
        isInitializingCharts = false;
    }
}

// Create debounced version of the initialization function
const debouncedInitializeCharts = debounce(initializeMaintenanceCharts, 250);

function changePage(page) {
    if (page < 1) return false;
    
    // Update URL with new page number
    const url = new URL(window.location.href);
    url.searchParams.set('page', page);
    window.location.href = url.toString();
    
    return false; // Prevent default anchor behavior
} 