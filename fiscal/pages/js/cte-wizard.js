/**
 * Wizard CT-e — criar rascunho + emissão SEFAZ (layout 4.00)
 */
(function () {
    var wizardData = { nfes: [], veiculos: [], motoristas: [] };

    function esc(s) {
        if (s == null) return '';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function showStatus(msg, type) {
        var el = document.getElementById('cteWizardStatus');
        if (!el) return;
        el.className = 'alert alert-' + (type || 'info') + ' mb-0';
        el.textContent = msg;
        el.classList.remove('d-none');
    }

    function clearStatus() {
        var el = document.getElementById('cteWizardStatus');
        if (el) {
            el.classList.add('d-none');
            el.textContent = '';
        }
    }

    function fillSelect(id, items, valueKey, labelFn) {
        var sel = document.getElementById(id);
        if (!sel) return;
        var cur = sel.value;
        sel.innerHTML = '<option value="">Selecione...</option>';
        (items || []).forEach(function (it) {
            var opt = document.createElement('option');
            opt.value = String(it[valueKey]);
            opt.textContent = labelFn(it);
            sel.appendChild(opt);
        });
        if (cur) sel.value = cur;
    }

    function renderNfeList() {
        var host = document.getElementById('cteWizardNfeList');
        if (!host) return;
        if (!wizardData.nfes.length) {
            host.innerHTML = '<p class="text-muted small mb-0">Nenhuma NF-e com status &quot;recebida&quot; disponível. Importe ou sincronize NF-e primeiro.</p>';
            return;
        }
        var html = '<div class="list-group list-group-flush">';
        wizardData.nfes.forEach(function (n) {
            var label = 'NF-e ' + esc(n.numero_nfe || n.id) + ' — R$ ' + parseFloat(n.valor_total || 0).toFixed(2);
            var sub = [];
            if (n.cliente_remetente) sub.push('Rem: ' + n.cliente_remetente);
            if (n.cliente_destinatario) sub.push('Dest: ' + n.cliente_destinatario);
            html += '<label class="list-group-item list-group-item-action d-flex gap-2 align-items-start">';
            html += '<input class="form-check-input flex-shrink-0 mt-1 cte-wizard-nfe-cb" type="checkbox" value="' + esc(String(n.id)) + '" data-peso="' + esc(String(n.peso_carga || 0)) + '" data-valor="' + esc(String(n.valor_total || 0)) + '">';
            html += '<span><strong>' + label + '</strong>';
            if (sub.length) html += '<br><small class="text-muted">' + esc(sub.join(' | ')) + '</small>';
            html += '</span></label>';
        });
        html += '</div>';
        host.innerHTML = html;
        host.querySelectorAll('.cte-wizard-nfe-cb').forEach(function (cb) {
            cb.addEventListener('change', atualizarTotaisNfe);
        });
    }

    function getNfeIdsSelecionados() {
        return Array.prototype.map.call(
            document.querySelectorAll('.cte-wizard-nfe-cb:checked'),
            function (cb) { return parseInt(cb.value, 10); }
        ).filter(function (v) { return v > 0; });
    }

    function atualizarTotaisNfe() {
        var peso = 0;
        var volEl = document.getElementById('cteWizardVolumes');
        var checked = document.querySelectorAll('.cte-wizard-nfe-cb:checked');
        checked.forEach(function (cb) {
            peso += parseFloat(cb.getAttribute('data-peso') || '0') || 0;
        });
        var pesoEl = document.getElementById('cteWizardPeso');
        if (pesoEl && peso > 0) pesoEl.value = peso.toFixed(3);
        if (volEl && checked.length > 0 && (!volEl.value || parseInt(volEl.value, 10) === 0)) {
            volEl.value = String(checked.length);
        }
    }

    function carregarDadosWizard(preselOt) {
        var host = document.getElementById('cteWizardNfeList');
        if (host) host.innerHTML = '<p class="text-muted small mb-0">Carregando...</p>';
        return fiscalApiFetch('../api/documentos_fiscais_v2.php?action=documentos_para_cte')
            .then(function (res) {
                var data = res.data || {};
                if (!data.success) {
                    throw new Error((typeof fiscalApiErrorMessage === 'function') ? fiscalApiErrorMessage(data, res.status) : 'Erro ao carregar dados');
                }
                wizardData.nfes = data.nfes || [];
                wizardData.veiculos = data.veiculos || [];
                wizardData.motoristas = data.motoristas || [];
                renderNfeList();
                fillSelect('cteWizardVeiculo', wizardData.veiculos, 'id', function (v) {
                    return (v.placa || '') + (v.modelo ? ' — ' + v.modelo : '');
                });
                fillSelect('cteWizardMotorista', wizardData.motoristas, 'id', function (m) {
                    return (m.nome || '') + (m.cpf ? ' — ' + m.cpf : '');
                });
                carregarOtsWizard(preselOt);
            });
    }

    // Integração: lista Ordens de Transporte (aprovadas/em viagem) para pré-preencher o CT-e.
    function carregarOtsWizard(preselId) {
        var sel = document.getElementById('cteWizardOtSel');
        if (!sel) { return; }
        fetch('../../api/ordens_transporte.php?action=list', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.success || !d.ordens) { return; }
                var elig = d.ordens.filter(function (o) { return o.status === 'aprovada' || o.status === 'em_viagem'; });
                sel.innerHTML = '<option value="">— não usar OT —</option>';
                elig.forEach(function (o) {
                    var opt = document.createElement('option');
                    opt.value = String(o.id);
                    opt.textContent = o.numero + (o.cliente_nome ? ' · ' + o.cliente_nome : '') + ' · ' +
                        ((o.origem || o.estado_origem || '') + '→' + (o.destino || o.estado_destino || ''));
                    sel.appendChild(opt);
                });
                if (preselId) { sel.value = String(preselId); if (sel.value) { prefillDeOt(preselId); } }
            }).catch(function () {});
        if (!sel.dataset.bound) {
            sel.dataset.bound = '1';
            sel.addEventListener('change', function () { if (sel.value) { prefillDeOt(sel.value); } });
        }
    }

    function prefillDeOt(otId) {
        fetch('../../api/ordens_transporte.php?action=get&id=' + encodeURIComponent(otId), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.success || !d.ordem) { return; }
                var o = d.ordem;
                function setVal(id, v) { var el = document.getElementById(id); if (el && v != null && v !== '') { el.value = v; } }
                setVal('cteWizardVeiculo', o.veiculo_id);
                setVal('cteWizardMotorista', o.motorista_id);
                setVal('cteWizardOrigem', (o.origem || '') + (o.estado_origem ? '/' + o.estado_origem : ''));
                setVal('cteWizardDestino', (o.destino || '') + (o.estado_destino ? '/' + o.estado_destino : ''));
                if (parseFloat(o.valor_frete) > 0) { setVal('cteWizardValorFrete', o.valor_frete); }
                if (parseFloat(o.peso_carga) > 0) { setVal('cteWizardPeso', o.peso_carga); }
            }).catch(function () {});
    }
    window.cteWizardCarregarOts = carregarOtsWizard;

    window.abrirModalCteWizard = function (otId) {
        clearStatus();
        ['cteWizardOrigem', 'cteWizardDestino'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        var vf = document.getElementById('cteWizardValorFrete');
        if (vf) vf.value = '0';
        var modal = new bootstrap.Modal(document.getElementById('modalCteWizard'));
        modal.show();
        carregarDadosWizard(typeof otId === 'number' || typeof otId === 'string' ? otId : null).catch(function (err) {
            showStatus(err.message || 'Erro ao carregar wizard.', 'danger');
        });
    };

    // Abertura automática pré-preenchida quando vem da OT (fiscal/pages/cte.php?ot=ID).
    document.addEventListener('DOMContentLoaded', function () {
        try {
            var ot = new URLSearchParams(window.location.search).get('ot');
            if (ot && /^\d+$/.test(ot)) {
                setTimeout(function () { window.abrirModalCteWizard(ot); }, 400);
            }
        } catch (e) { /* ignora */ }
    });

    function salvarCteWizard() {
        clearStatus();
        var nfeIds = getNfeIdsSelecionados();
        var veiculoId = parseInt((document.getElementById('cteWizardVeiculo') || {}).value || '0', 10);
        var motoristaId = parseInt((document.getElementById('cteWizardMotorista') || {}).value || '0', 10);
        var origem = ((document.getElementById('cteWizardOrigem') || {}).value || '').trim();
        var destino = ((document.getElementById('cteWizardDestino') || {}).value || '').trim();
        var valorFrete = parseFloat((document.getElementById('cteWizardValorFrete') || {}).value || '0') || 0;
        var peso = parseFloat((document.getElementById('cteWizardPeso') || {}).value || '0') || 0;
        var volumes = parseInt((document.getElementById('cteWizardVolumes') || {}).value || '0', 10) || 0;
        var emitir = !!(document.getElementById('cteWizardEmitirApos') || {}).checked;

        if (!nfeIds.length) {
            showStatus('Selecione pelo menos uma NF-e.', 'warning');
            return;
        }
        if (veiculoId <= 0) {
            showStatus('Selecione o veículo.', 'warning');
            return;
        }
        if (motoristaId <= 0) {
            showStatus('Selecione o motorista.', 'warning');
            return;
        }

        var btn = document.getElementById('btnCteWizardSalvar');
        var origHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
        }

        var fd = new FormData();
        fd.append('action', 'criar_cte');
        nfeIds.forEach(function (id) { fd.append('nfe_ids[]', String(id)); });
        fd.append('veiculo_id', String(veiculoId));
        fd.append('motorista_id', String(motoristaId));
        fd.append('origem', origem);
        fd.append('destino', destino);
        fd.append('valor_frete', String(valorFrete));
        fd.append('peso_total', String(peso));
        fd.append('volumes_total', String(volumes));
        var otSel = document.getElementById('cteWizardOtSel');
        if (otSel && otSel.value) { fd.append('ordem_transporte_id', otSel.value); }

        fiscalApiFetch('../api/documentos_fiscais_v2.php', { method: 'POST', body: fd })
            .then(function (res) {
                var data = res.data || {};
                if (!data.success) {
                    var msg = (typeof fiscalApiErrorMessage === 'function') ? fiscalApiErrorMessage(data, res.status) : (data.error || 'Erro ao criar CT-e');
                    showStatus(msg, 'danger');
                    return null;
                }
                var cteId = data.cte_id;
                if (Array.isArray(data.warnings) && data.warnings.length) {
                    showStatus('CT-e #' + (data.numero_cte || cteId) + ' salvo com avisos. Revise antes de enviar à SEFAZ.', 'warning');
                } else {
                    showStatus('CT-e #' + (data.numero_cte || cteId) + ' salvo como rascunho.', 'success');
                }
                if (typeof carregarCTE === 'function') carregarCTE();
                if (!emitir || !cteId) {
                    return { cteId: cteId, skipEmit: true };
                }
                var fd2 = new FormData();
                fd2.append('action', 'emitir_cte_sefaz');
                fd2.append('cte_id', String(cteId));
                return fiscalApiFetch('../api/documentos_fiscais_v2.php', { method: 'POST', body: fd2 })
                    .then(function (res2) {
                        return { cteId: cteId, emit: res2 };
                    });
            })
            .then(function (result) {
                if (!result || result.skipEmit) return;
                var emitData = (result.emit && result.emit.data) ? result.emit.data : {};
                if (emitData.success) {
                    showStatus('CT-e enviado. Status: ' + (emitData.status || 'pendente') + (emitData.protocolo ? ' | Protocolo: ' + emitData.protocolo : ''), 'success');
                    if (typeof carregarCTE === 'function') carregarCTE();
                } else {
                    var msgE = (typeof fiscalApiErrorMessage === 'function') ? fiscalApiErrorMessage(emitData, result.emit.status) : 'Falha na emissão';
                    showStatus('Rascunho salvo, mas emissão falhou: ' + msgE, 'warning');
                }
            })
            .catch(function () {
                showStatus('Erro de comunicação ao salvar CT-e.', 'danger');
            })
            .finally(function () {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                }
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('btnCteWizardSalvar');
        if (btn) btn.addEventListener('click', salvarCteWizard);
    });
})();
