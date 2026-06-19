/**
 * Eventos CT-e: CC-e, desacordo, comprovante, inutilização
 */
(function () {
    function showAlert(elId, msg, type) {
        var el = document.getElementById(elId);
        if (!el) return;
        el.className = 'alert alert-' + (type || 'info') + ' mb-0';
        el.textContent = msg;
        el.classList.remove('d-none');
    }

    function postAction(action, fields) {
        var fd = new FormData();
        fd.append('action', action);
        Object.keys(fields).forEach(function (k) {
            if (fields[k] != null) fd.append(k, String(fields[k]));
        });
        return fiscalApiFetch('../api/documentos_fiscais_v2.php', { method: 'POST', body: fd });
    }

    window.abrirModalCteCce = function (cteId) {
        document.getElementById('cceCteId').value = String(cteId);
        document.getElementById('cceTexto').value = '';
        document.getElementById('cceStatus').classList.add('d-none');
        new bootstrap.Modal(document.getElementById('modalCteCce')).show();
    };

    window.abrirModalCteDesacordo = function (cteId) {
        document.getElementById('desacordoCteId').value = String(cteId);
        document.getElementById('desacordoObs').value = '';
        document.getElementById('desacordoStatus').classList.add('d-none');
        new bootstrap.Modal(document.getElementById('modalCteDesacordo')).show();
    };

    window.abrirModalCteComprovante = function (cteId) {
        document.getElementById('ceCteId').value = String(cteId);
        document.getElementById('ceNome').value = '';
        document.getElementById('ceDoc').value = '';
        document.getElementById('ceDh').value = '';
        document.getElementById('ceStatus').classList.add('d-none');
        new bootstrap.Modal(document.getElementById('modalCteComprovante')).show();
    };

    window.abrirModalCteInutilizar = function () {
        document.getElementById('inutStatus').classList.add('d-none');
        new bootstrap.Modal(document.getElementById('modalCteInutilizar')).show();
    };

    document.addEventListener('DOMContentLoaded', function () {
        var btnCce = document.getElementById('btnEnviarCce');
        if (btnCce) {
            btnCce.addEventListener('click', function () {
                var id = parseInt(document.getElementById('cceCteId').value, 10);
                var texto = document.getElementById('cceTexto').value.trim();
                if (!texto) {
                    showAlert('cceStatus', 'Informe o texto da correção.', 'warning');
                    return;
                }
                btnCce.disabled = true;
                postAction('cte_cce', {
                    cte_id: id,
                    correcao: texto,
                    grupo_alterado: document.getElementById('cceGrupo').value,
                    campo_alterado: document.getElementById('cceCampo').value
                }).then(function (res) {
                    var d = res.data || {};
                    if (d.success) {
                        showAlert('cceStatus', 'CC-e homologada. Protocolo: ' + (d.protocolo_evento || '-'), 'success');
                        if (typeof carregarCTE === 'function') carregarCTE();
                    } else {
                        showAlert('cceStatus', fiscalApiErrorMessage(d, res.status), 'danger');
                    }
                }).catch(function () {
                    showAlert('cceStatus', 'Erro de comunicação.', 'danger');
                }).finally(function () {
                    btnCce.disabled = false;
                });
            });
        }

        var btnDes = document.getElementById('btnEnviarDesacordo');
        if (btnDes) {
            btnDes.addEventListener('click', function () {
                var id = parseInt(document.getElementById('desacordoCteId').value, 10);
                postAction('cte_desacordo', {
                    cte_id: id,
                    observacao: document.getElementById('desacordoObs').value.trim(),
                    uf_evento: document.getElementById('desacordoUf').value.trim()
                }).then(function (res) {
                    var d = res.data || {};
                    if (d.success) {
                        showAlert('desacordoStatus', d.message || 'Desacordo registrado.', 'success');
                    } else {
                        showAlert('desacordoStatus', fiscalApiErrorMessage(d, res.status), 'danger');
                    }
                });
            });
        }

        var btnCe = document.getElementById('btnEnviarComprovante');
        if (btnCe) {
            btnCe.addEventListener('click', function () {
                var id = parseInt(document.getElementById('ceCteId').value, 10);
                var dh = document.getElementById('ceDh').value;
                postAction('cte_comprovante_entrega', {
                    cte_id: id,
                    nome_recebedor: document.getElementById('ceNome').value.trim(),
                    doc_recebedor: document.getElementById('ceDoc').value.trim(),
                    dh_entrega: dh ? (dh.replace('T', 'T') + ':00') : ''
                }).then(function (res) {
                    var d = res.data || {};
                    if (d.success) {
                        showAlert('ceStatus', d.message || 'Comprovante registrado.', 'success');
                    } else {
                        showAlert('ceStatus', fiscalApiErrorMessage(d, res.status), 'danger');
                    }
                });
            });
        }

        var btnInut = document.getElementById('btnInutilizarCte');
        if (btnInut) {
            btnInut.addEventListener('click', function () {
                postAction('cte_inutilizar', {
                    serie: document.getElementById('inutSerie').value,
                    numero_inicio: document.getElementById('inutIni').value,
                    numero_fim: document.getElementById('inutFim').value,
                    justificativa: document.getElementById('inutJust').value.trim()
                }).then(function (res) {
                    var d = res.data || {};
                    if (d.success) {
                        showAlert('inutStatus', d.message || 'Inutilização homologada.', 'success');
                    } else {
                        showAlert('inutStatus', fiscalApiErrorMessage(d, res.status), 'danger');
                    }
                });
            });
        }
    });
})();
