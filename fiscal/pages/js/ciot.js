(function () {
    'use strict';

    var API = typeof sfApiUrl === 'function' ? sfApiUrl('pef_ciot.php') : '/api/pef_ciot.php';
    var CONFIG_URL = typeof sfAppUrl === 'function'
        ? sfAppUrl('pages/configuracoes.php?aba=fiscal')
        : '/pages/configuracoes.php?aba=fiscal';
    var csrf = window.__SF_CSRF__ || '';
    var pefStatus = { ativo: false, empresa_cnpj: '' };
    var contratacaoAtual = { permitido: false, nivel: '', mensagem: '' };

    function atualizarAlertaContratacao(data) {
        var el = document.getElementById('ciotContratacaoAlert');
        var btn = document.getElementById('btnEnviarDeclaracao');
        if (!el) return;
        contratacaoAtual = data || { permitido: false, nivel: 'bloqueio', mensagem: '' };
        el.className = 'ciot-alert';
        el.textContent = '';
        if (!data || !data.mensagem) {
            el.style.display = 'none';
            if (btn) btn.disabled = false;
            return;
        }
        el.style.display = 'block';
        el.textContent = data.mensagem;
        if (data.nivel === 'bloqueio') {
            el.classList.add('ciot-alert--bloqueio');
        } else if (data.nivel === 'aviso') {
            el.classList.add('ciot-alert--aviso');
        } else {
            el.classList.add('ciot-alert--ok');
        }
        if (btn) btn.disabled = !data.permitido;
    }

    function validarContratacao(tipo) {
        if (!tipo) {
            atualizarAlertaContratacao({ permitido: false, nivel: 'bloqueio', mensagem: 'Selecione o tipo de contratação do transporte.' });
            return Promise.resolve();
        }
        return fetchApi(API + '?action=validar_contratacao&tipo_contratacao=' + encodeURIComponent(tipo))
            .then(function (j) {
                if (j.success && j.data) {
                    atualizarAlertaContratacao(j.data);
                    if (j.data.permitido && (tipo === 'tac' || tipo === 'agregado')) {
                        var tipoOp = document.getElementById('ciot_tipo_operacao');
                        if (tipoOp && tipoOp.value === '1') tipoOp.value = '3';
                    }
                }
            });
    }

    function fetchApi(url, opts) {
        opts = opts || {};
        opts.credentials = 'same-origin';
        opts.headers = opts.headers || {};
        if (opts.method === 'POST') {
            opts.headers['X-CSRF-Token'] = csrf;
        }
        return fetch(url, opts).then(function (r) { return r.json(); });
    }

    function notify(msg, ok) {
        if (typeof window.showToast === 'function') {
            window.showToast(msg, ok ? 'success' : 'error');
        } else {
            alert(msg);
        }
    }

    function badgeClass(st) {
        return 'ciot-badge ciot-badge--' + (st || 'rascunho');
    }

    function loadStatus() {
        return fetchApi(API + '?action=config').then(function (j) {
            if (!j.success || !j.data) return;
            pefStatus.ativo = !!Number(j.data.ativo);
            pefStatus.empresa_cnpj = j.data.empresa_cnpj || j.data.cpf_cnpj_interessado || '';
            var banner = document.getElementById('ciotInactiveBanner');
            if (banner && !pefStatus.ativo) {
                banner.style.display = 'block';
                banner.innerHTML = 'Integração CIOT/PEF não está ativa. Configure em <a href="' + CONFIG_URL + '">Configurações → Fiscal &amp; Multas</a>.';
            } else if (banner) {
                banner.style.display = 'none';
            }
        });
    }

    function loadList() {
        var st = document.getElementById('ciotStatusFilter').value;
        var url = API + '?action=list' + (st ? '&status=' + encodeURIComponent(st) : '');
        return fetchApi(url).then(function (j) {
            var wrap = document.getElementById('ciotListWrap');
            if (!j.success) {
                wrap.innerHTML = '<p class="text-danger">Erro ao carregar lista.</p>';
                return;
            }
            var rows = j.data || [];
            if (!rows.length) {
                wrap.innerHTML = '<p>Nenhuma operação CIOT registrada.</p>';
                return;
            }
            var html = '<table class="ciot-table"><thead><tr>' +
                '<th>CIOT</th><th>Id operação</th><th>Status</th><th>Frete</th><th>Declaração</th><th>Ações</th>' +
                '</tr></thead><tbody>';
            rows.forEach(function (r) {
                html += '<tr>' +
                    '<td><strong>' + (r.codigo_identificacao || '—') + '</strong>' +
                    (r.codigo_verificador ? '<br><small>Verif: ' + r.codigo_verificador + '</small>' : '') + '</td>' +
                    '<td>' + r.id_operacao_transporte + '</td>' +
                    '<td><span class="' + badgeClass(r.status) + '">' + r.status + '</span></td>' +
                    '<td>' + (r.valor_frete != null ? 'R$ ' + Number(r.valor_frete).toFixed(2) : '—') + '</td>' +
                    '<td>' + (r.data_declaracao || r.criado_em || '—') + '</td>' +
                    '<td>' + actionButtons(r) + '</td></tr>';
            });
            html += '</tbody></table>';
            wrap.innerHTML = html;
            bindRowActions();
        });
    }

    function actionButtons(r) {
        var b = '';
        if (r.status === 'declarado') {
            b += '<button type="button" class="fornc-btn fornc-btn--ghost btn-sm" data-act="encerrar" data-id="' + r.id + '">Encerrar</button> ';
            b += '<button type="button" class="fornc-btn fornc-btn--ghost btn-sm" data-act="cancelar" data-id="' + r.id + '">Cancelar</button>';
        }
        if (r.codigo_identificacao) {
            b += ' <button type="button" class="fornc-btn fornc-btn--ghost btn-sm" data-act="copy" data-ciot="' + r.codigo_identificacao + '">Copiar CIOT</button>';
        }
        return b || '—';
    }

    function bindRowActions() {
        document.querySelectorAll('[data-act="encerrar"]').forEach(function (btn) {
            btn.onclick = function () {
                if (!confirm('Encerrar esta operação na ANTT?')) return;
                postJson('encerrar', { id: btn.getAttribute('data-id') }).then(function (j) {
                    notify(j.message || (j.success ? 'Encerrado.' : 'Falha.'), j.success);
                    if (j.success) loadList();
                });
            };
        });
        document.querySelectorAll('[data-act="cancelar"]').forEach(function (btn) {
            btn.onclick = function () {
                var motivo = prompt('Motivo do cancelamento:');
                if (!motivo) return;
                postJson('cancelar', { id: btn.getAttribute('data-id'), motivo: motivo }).then(function (j) {
                    notify(j.message || (j.success ? 'Cancelado.' : 'Falha.'), j.success);
                    if (j.success) loadList();
                });
            };
        });
        document.querySelectorAll('[data-act="copy"]').forEach(function (btn) {
            btn.onclick = function () {
                var c = btn.getAttribute('data-ciot');
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(c).then(function () { notify('CIOT copiado para o MDF-e.', true); });
                } else {
                    prompt('CIOT:', c);
                }
            };
        });
    }

    function postJson(action, body) {
        return fetchApi(API + '?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(body || {})
        });
    }

    function openModal() {
        if (!pefStatus.ativo) {
            notify('Ative a integração CIOT/PEF em Configurações → Fiscal.', false);
            return;
        }
        document.getElementById('ciotDeclararModal').classList.add('is-open');
        var today = new Date().toISOString().slice(0, 10);
        var f = document.getElementById('ciotDeclararForm');
        if (f.data_inicio_viagem && !f.data_inicio_viagem.value) f.data_inicio_viagem.value = today;
        if (f.data_fim_viagem && !f.data_fim_viagem.value) f.data_fim_viagem.value = today;
        var tipoC = document.getElementById('ciot_tipo_contratacao');
        if (tipoC && tipoC.value) {
            validarContratacao(tipoC.value);
        } else {
            atualizarAlertaContratacao(null);
        }
    }

    function closeModal() {
        document.getElementById('ciotDeclararModal').classList.remove('is-open');
    }

    function enviarDeclaracao() {
        var tipoC = document.getElementById('ciot_tipo_contratacao');
        var tipo = tipoC ? tipoC.value : '';
        if (!tipo) {
            notify('Selecione o tipo de contratação do transporte.', false);
            return;
        }
        if (!contratacaoAtual.permitido) {
            notify(contratacaoAtual.mensagem || 'CIOT não aplicável para este tipo de transporte.', false);
            return;
        }
        var f = document.getElementById('ciotDeclararForm');
        var fd = new FormData(f);
        var payload = {};
        fd.forEach(function (v, k) { payload[k] = v; });
        payload.veiculos = [{
            placa: payload.veiculo_placa,
            rntrc_veiculo: payload.veiculo_rntrc,
            numero_eixos: '2'
        }];
        delete payload.veiculo_placa;
        delete payload.veiculo_rntrc;

        postJson('declarar', payload).then(function (j) {
            if (j.success) {
                notify('CIOT: ' + (j.data && j.data.ciot ? j.data.ciot : 'gerado'), true);
                closeModal();
                loadList();
            } else {
                notify(j.message || j.error || 'Erro na declaração.', false);
            }
        });
    }

    function consultaAntt(action) {
        if (!pefStatus.ativo) {
            notify('Ative a integração em Configurações → Fiscal.', false);
            return;
        }
        var cnpj = pefStatus.empresa_cnpj || '';
        if (!cnpj) {
            cnpj = (prompt('CPF/CNPJ do transportador:', '') || '').replace(/\D/g, '');
        }
        var rntrc = prompt('RNTRC do transportador (opcional):', '') || '';
        postJson(action, { cpf_cnpj: cnpj, rntrc: rntrc }).then(function (j) {
            var el = document.getElementById('pefConsultaResult');
            el.style.display = 'block';
            el.textContent = j.success ? JSON.stringify(j.data, null, 2) : (j.error || j.message || 'Erro');
        });
    }

    document.getElementById('btnAtualizarLista').addEventListener('click', loadList);
    document.getElementById('ciotStatusFilter').addEventListener('change', loadList);
    document.getElementById('btnNovaDeclaracao').addEventListener('click', openModal);
    document.getElementById('ciotModalClose').addEventListener('click', closeModal);
    document.getElementById('btnCancelarModal').addEventListener('click', closeModal);
    document.getElementById('btnEnviarDeclaracao').addEventListener('click', enviarDeclaracao);
    document.getElementById('btnConsultarSituacao').addEventListener('click', function () { consultaAntt('consultar_situacao'); });
    document.getElementById('btnConsultarFrota').addEventListener('click', function () { consultaAntt('consultar_frota'); });

    var tipoContratEl = document.getElementById('ciot_tipo_contratacao');
    if (tipoContratEl) {
        tipoContratEl.addEventListener('change', function () {
            validarContratacao(this.value);
        });
    }

    loadStatus().then(loadList);
})();
