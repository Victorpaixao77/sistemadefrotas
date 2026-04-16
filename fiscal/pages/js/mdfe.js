/**
 * MDF-e — CSRF, API, UX (toasts, confirmação, busca fornecedor)
 * Carregar após: window.MDFE_CSRF_TOKEN definido em mdfe.php
 */
function mdfeApiFetch(url, init) {
    init = init || {};
    var method = (init.method || 'GET').toUpperCase();
    var token = (typeof window !== 'undefined' && window.MDFE_CSRF_TOKEN) ? String(window.MDFE_CSRF_TOKEN) : '';
    if (token && method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS') {
        if (!init.headers) init.headers = {};
        if (typeof Headers !== 'undefined' && init.headers instanceof Headers) {
            init.headers.set('X-CSRF-Token', token);
        } else {
            init.headers['X-CSRF-Token'] = token;
        }
    }
    return fetch(url, init).then(function(response) {
        return response.text().then(function(text) {
            var data = {};
            if (text) {
                try {
                    var parsed = JSON.parse(text);
                    data = (parsed && typeof parsed === 'object') ? parsed : { _nonObject: parsed };
                } catch (e) {
                    data = { parse_error: true, raw: text };
                }
            }
            return { ok: response.ok, status: response.status, data: data };
        });
    });
}

function mdfeApiErrorMessage(data, httpStatus) {
    if (typeof window.fiscalApiErrorMessage === 'function') {
        return window.fiscalApiErrorMessage(data, httpStatus);
    }
    var st = typeof httpStatus === 'number' ? httpStatus : 0;
    if (st === 429 || (data && String(data.code || '') === 'rate_limited')) {
        if (data && typeof data.error === 'string' && data.error.trim()) return data.error;
        if (data && typeof data.message === 'string' && data.message.trim()) return data.message;
        return 'Muitas requisições em pouco tempo. Aguarde alguns minutos e tente novamente.';
    }
    if (!data || typeof data !== 'object') return 'Não foi possível interpretar a resposta do servidor.';
    var head = (typeof data.error === 'string' && data.error.trim()) ? data.error.trim() : '';
    if (Array.isArray(data.detalhes) && data.detalhes.length > 0) {
        var bodyD = data.detalhes.map(function (x) { return String(x); }).join('\n');
        return head ? (head + '\n\n' + bodyD) : bodyD;
    }
    if (data.erros && Array.isArray(data.erros) && data.erros.length > 0 && typeof data.erros[0] === 'object') {
        var lines = data.erros.map(function (e) {
            if (!e || typeof e !== 'object') return String(e);
            var m = e.mensagem != null ? String(e.mensagem) : '';
            var id = e.id != null ? String(e.id) : (e.codigo != null ? String(e.codigo) : '');
            if (id && m) return id + ': ' + m;
            return m || id || '';
        }).filter(function (s) { return s.length > 0; });
        if (lines.length > 0) {
            var bodyE = lines.join('\n');
            return head ? (head + '\n\n' + bodyE) : bodyE;
        }
    }
    if (data.message && typeof data.message === 'string') return data.message;
    if (typeof data.error === 'string') return data.error;
    if (data.erros && Array.isArray(data.erros)) return data.erros.join(' ');
    return 'Erro desconhecido.';
}

function mdfeNotify(message, variant) {
    variant = variant || 'info';
    var bg = 'primary';
    if (variant === 'success') bg = 'success';
    else if (variant === 'error' || variant === 'danger') bg = 'danger';
    else if (variant === 'warning') bg = 'warning';
    else if (variant === 'info') bg = 'primary';
    var host = document.getElementById('mdfeToastContainer');
    if (!host) {
        if (typeof console !== 'undefined' && console.warn) console.warn(message);
        return;
    }
    var el = document.createElement('div');
    el.className = 'toast align-items-center text-bg-' + bg + ' border-0';
    el.setAttribute('role', (variant === 'error' || variant === 'danger') ? 'alert' : 'status');
    el.setAttribute('aria-live', (variant === 'error' || variant === 'danger') ? 'assertive' : 'polite');
    el.innerHTML = '<div class="d-flex"><div class="toast-body"></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button></div>';
    el.querySelector('.toast-body').textContent = message;
    host.appendChild(el);
    var t = new bootstrap.Toast(el, { delay: 4500 });
    t.show();
    el.addEventListener('hidden.bs.toast', function() { el.remove(); });
}

function mdfeConfirmAsync(title, message) {
    return new Promise(function(resolve) {
        var modalEl = document.getElementById('modalMdfeConfirm');
        if (!modalEl) {
            mdfeNotify('Confirmação indisponível (modal não carregado). Recarregue a página.', 'warning');
            resolve(false);
            return;
        }
        var tEl = modalEl.querySelector('.modal-mdfe-confirm-title');
        var bEl = modalEl.querySelector('.modal-mdfe-confirm-body');
        if (tEl) tEl.textContent = title || 'Confirmar';
        if (bEl) {
            bEl.textContent = '';
            bEl.appendChild(document.createTextNode(message || ''));
        }
        var m = bootstrap.Modal.getOrCreateInstance(modalEl);
        var finished = false;
        var btnSim = document.getElementById('modalMdfeConfirmSim');
        var onSim = function() {
            if (finished) return;
            finished = true;
            m.hide();
            resolve(true);
        };
        var onHidden = function() {
            if (btnSim) btnSim.removeEventListener('click', onSim);
            if (!finished) {
                finished = true;
                resolve(false);
            }
        };
        if (btnSim) btnSim.addEventListener('click', onSim, { once: true });
        modalEl.addEventListener('hidden.bs.modal', onHidden, { once: true });
        m.show();
    });
}

var mdfeBuscaFornecedorCallback = null;

function abrirModalBuscaFornecedorMdfe(titulo, callback) {
    mdfeBuscaFornecedorCallback = callback;
    var t = document.getElementById('modalMdfeBuscaFornecedorTitle');
    var termo = document.getElementById('modalMdfeBuscaFornecedorTermo');
    var lista = document.getElementById('modalMdfeBuscaFornecedorLista');
    if (t) t.textContent = titulo || 'Pesquisar cliente / fornecedor';
    if (termo) termo.value = '';
    if (lista) {
        lista.innerHTML = '<p class="text-muted small mb-0">Digite nome, CNPJ ou CPF e clique em Pesquisar.</p>';
        lista._listaFornecedor = null;
        lista.onclick = null;
    }
    var el = document.getElementById('modalMdfeBuscaFornecedor');
    if (el) bootstrap.Modal.getOrCreateInstance(el).show();
}

function executarBuscaFornecedorMdfe() {
    var termoEl = document.getElementById('modalMdfeBuscaFornecedorTermo');
    var box = document.getElementById('modalMdfeBuscaFornecedorLista');
    if (!termoEl || !box) return;
    var termo = termoEl.value;
    box.innerHTML = '<p class="text-muted small mb-0">Buscando...</p>';
    buscarClientesFornecedorNovoMdfe(termo).then(function(lista) {
        if (!lista.length) {
            box.innerHTML = '<p class="text-muted mb-0">Nenhum cliente/fornecedor encontrado.</p>';
            return;
        }
        box._listaFornecedor = lista;
        box.innerHTML = lista.slice(0, 50).map(function(item, idx) {
            var nome = escapeHtmlMdfe(item.nome || item.razao_social || item.nome_fantasia || '-');
            var doc = escapeHtmlMdfe(item.cnpj || item.cpf || '');
            return '<button type="button" class="list-group-item list-group-item-action text-start" data-idx="' + idx + '">' + nome + (doc ? '<br><small class="text-muted">' + doc + '</small>' : '') + '</button>';
        }).join('');
        box.onclick = function(e) {
            var btn = e.target.closest('button[data-idx]');
            if (!btn) return;
            var idx = parseInt(btn.getAttribute('data-idx'), 10);
            var item = box._listaFornecedor && box._listaFornecedor[idx];
            if (item && mdfeBuscaFornecedorCallback) {
                mdfeBuscaFornecedorCallback(item);
                var modalEl = document.getElementById('modalMdfeBuscaFornecedor');
                if (modalEl) bootstrap.Modal.getInstance(modalEl).hide();
            }
        };
    });
}

        document.addEventListener('DOMContentLoaded', function() {
            carregarMDFE();
            carregarVeiculosMotoristas();
            setupEstadoCidadeMdfe();
            setupEstadoCidadeNovoMdfe();
            setupTabsNovoMdfe();
            setupSubtabsRodoviarioNovoMdfe();
            setupVeiculoEmitenteToggleNovoMdfe();
            setupTipoPessoaRodoviarioNovoMdfe();
            setupDocumentosNovoMdfe();
            setupProdutoPredominanteNovoMdfe();
            setupTotalizadoresNovoMdfe();

            var applyBtn = document.getElementById('applyMdfeFilters');
            if (applyBtn) {
                applyBtn.addEventListener('click', function() {
                    mdfePaginaAtual = 1;
                    aplicarFiltrosMDFE();
                });
            }

            var clearBtn = document.getElementById('clearMdfeFilters');
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    var search = document.getElementById('searchMdfe');
                    var status = document.getElementById('statusMdfeFilter');
                    if (search) search.value = '';
                    if (status) status.value = '';
                    mdfePaginaAtual = 1;
                    aplicarFiltrosMDFE();
                });
            }

            var perPage = document.getElementById('perPageMdfe');
            if (perPage) {
                perPage.addEventListener('change', function() {
                    mdfePerPage = Math.max(1, parseInt(this.value, 10) || 10);
                    mdfePaginaAtual = 1;
                    aplicarFiltrosMDFE();
                });
            }

            var termoBuscaForn = document.getElementById('modalMdfeBuscaFornecedorTermo');
            if (termoBuscaForn) {
                termoBuscaForn.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        executarBuscaFornecedorMdfe();
                    }
                });
            }

        });

        var novoMdfeUfsPercurso = [];
        var rodCondutoresNovoMdfe = [];
        var rodValesPedagioNovoMdfe = [];
        var rodContratantesNovoMdfe = [];
        var rodPagamentosFreteNovoMdfe = [];
        var rodPagamentoComponentesDraft = [];
        var rodPagamentoCompPaginaAtual = 1;
        var rodCiotNovoMdfe = [];
        var rodValePedagioEditIndex = -1;
        var rodCiotEditIndex = -1;
        var rodContratanteEditIndex = -1;
        var rodPagamentoFreteEditIndex = -1;
        var docDocumentosNovoMdfe = [];
        var docEditIndexNovoMdfe = -1;
        var segSegurosNovoMdfe = [];
        var segAverbacoesDraftNovoMdfe = [];
        var segEditIndexNovoMdfe = -1;
        var prodPredominantesNovoMdfe = [];
        var prodEditIndexNovoMdfe = -1;
        var totLacresNovoMdfe = [];
        var totAutorizadosNovoMdfe = [];
        var novoMdfeOrigem = '';
        var novoMdfeOrigemCteIds = [];
        var novoMdfeOrigemNfeIds = [];
        var mdfeDocsCache = [];
        var mdfePaginaAtual = 1;
        var mdfePerPage = 10;
        var mdfeSortField = 'data_emissao';
        var mdfeSortDir = 'desc';

        function setupEstadoCidadeMdfe() {
            var ufInicio = document.getElementById('ufInicio');
            var ufFim = document.getElementById('ufFim');
            var cidadeCarregamento = document.getElementById('cidadeCarregamento');
            var cidadeDescarregamento = document.getElementById('cidadeDescarregamento');
            if (ufInicio) {
                ufInicio.addEventListener('change', function() {
                    var uf = this.value;
                    if (uf) {
                        loadCidadesMdfe(uf, 'cidadeCarregamento');
                        cidadeCarregamento.disabled = false;
                    } else {
                        cidadeCarregamento.innerHTML = '<option value="">Selecione primeiro o estado</option>';
                        cidadeCarregamento.disabled = true;
                    }
                });
            }
            if (ufFim) {
                ufFim.addEventListener('change', function() {
                    var uf = this.value;
                    if (uf) {
                        loadCidadesMdfe(uf, 'cidadeDescarregamento');
                        cidadeDescarregamento.disabled = false;
                    } else {
                        cidadeDescarregamento.innerHTML = '<option value="">Selecione primeiro o estado</option>';
                        cidadeDescarregamento.disabled = true;
                    }
                });
            }
        }

        function loadCidadesMdfe(uf, selectId) {
            var sel = document.getElementById(selectId);
            if (!sel) return;
            sel.innerHTML = '<option value="">Carregando...</option>';
            fetch('../../api/route_actions.php?action=get_cidades&uf=' + encodeURIComponent(uf))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.data && data.data.length) {
                        var opts = '<option value="">Selecione a cidade</option>';
                        data.data.forEach(function(c) {
                            var nome = (c.nome || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                            opts += '<option value="' + nome + '">' + (c.nome || '') + '</option>';
                        });
                        sel.innerHTML = opts;
                    } else {
                        sel.innerHTML = '<option value="">Nenhuma cidade encontrada</option>';
                    }
                })
                .catch(function() {
                    sel.innerHTML = '<option value="">Erro ao carregar cidades</option>';
                });
        }

        function setupEstadoCidadeNovoMdfe() {
            var ufCarga = document.getElementById('novo_mdfe_uf_carga');
            var ufDescarga = document.getElementById('novo_mdfe_uf_descarga');
            var munCarga = document.getElementById('novo_mdfe_municipio_carga');
            var munDescarga = document.getElementById('novo_mdfe_municipio_descarga');

            if (ufCarga) {
                ufCarga.addEventListener('change', function() {
                    var uf = this.value;
                    if (uf) {
                        loadCidadesMdfe(uf, 'novo_mdfe_municipio_carga');
                        munCarga.disabled = false;
                    } else {
                        munCarga.innerHTML = '<option value="">Selecione primeiro a UF</option>';
                        munCarga.disabled = true;
                    }
                });
            }
            if (ufDescarga) {
                ufDescarga.addEventListener('change', function() {
                    var uf = this.value;
                    if (uf) {
                        loadCidadesMdfe(uf, 'novo_mdfe_municipio_descarga');
                        munDescarga.disabled = false;
                    } else {
                        munDescarga.innerHTML = '<option value="">Selecione primeiro a UF</option>';
                        munDescarga.disabled = true;
                    }
                });
            }
        }

        function abrirModalNovoMDFE() {
            var form = document.getElementById('novoMDFEWizardForm');
            if (form) form.reset();

            var dataEmissao = document.getElementById('novo_mdfe_data_emissao');
            if (dataEmissao) dataEmissao.value = new Date().toISOString().slice(0, 10);

            var munCarga = document.getElementById('novo_mdfe_municipio_carga');
            var munDescarga = document.getElementById('novo_mdfe_municipio_descarga');
            if (munCarga) {
                munCarga.innerHTML = '<option value="">Selecione primeiro a UF</option>';
                munCarga.disabled = true;
            }
            if (munDescarga) {
                munDescarga.innerHTML = '<option value="">Selecione primeiro a UF</option>';
                munDescarga.disabled = true;
            }

            novoMdfeUfsPercurso = [];
            renderUfsPercursoNovoMDFE();
            rodCondutoresNovoMdfe = [];
            renderCondutoresNovoMDFE();
            rodCiotNovoMdfe = [];
            renderCiotNovoMDFE();
            rodValesPedagioNovoMdfe = [];
            renderValesPedagioNovoMDFE();
            rodContratantesNovoMdfe = [];
            renderContratantesNovoMDFE();
            rodPagamentosFreteNovoMdfe = [];
            renderPagamentosFreteNovoMDFE();
            limparFormValePedagioNovoMDFE();
            limparFormCiotNovoMDFE();
            limparFormContratanteNovoMDFE();
            limparFormPagamentoFreteNovoMDFE();
            docDocumentosNovoMdfe = [];
            renderDocumentosNovoMDFE();
            limparFormDocumentoNovoMDFE();
            segSegurosNovoMdfe = [];
            renderSegurosNovoMDFE();
            limparFormSeguroNovoMDFE();
            prodPredominantesNovoMdfe = [];
            renderProdutosPredNovoMDFE();
            limparFormProdutoPredNovoMDFE();
            totLacresNovoMdfe = [];
            totAutorizadosNovoMdfe = [];
            novoMdfeOrigem = '';
            novoMdfeOrigemCteIds = [];
            novoMdfeOrigemNfeIds = [];
            atualizarOrigemMdfeHiddenFields();
            limparTotalizadoresNovoMDFE();
            renderTotalizadoresNovoMDFE();
            updateTipoPessoaContratanteNovoMDFE();
            updateTipoPessoaPagamentoNovoMDFE();
            aplicarRegrasPorTipoEmitenteNovoMDFE();
            setRodoviarioSubtabNovoMdfe('1');
            updateVeiculoEmitenteVisibilityNovoMdfe();

            setNovoMdfeTab('1');

            var modalEl = document.getElementById('novoMDFEWizardModal');
            if (modalEl) {
                new bootstrap.Modal(modalEl).show();
            }
        }

        function atualizarOrigemMdfeHiddenFields() {
            var origem = document.getElementById('origem_mdfe');
            var ctes = document.getElementById('origem_cte_ids');
            var nfes = document.getElementById('origem_nfe_ids');
            if (origem) origem.value = novoMdfeOrigem || '';
            if (ctes) ctes.value = JSON.stringify(novoMdfeOrigemCteIds || []);
            if (nfes) nfes.value = JSON.stringify(novoMdfeOrigemNfeIds || []);
        }

        var tipoImportacaoSelecionadoMDFE = '';

        function abrirFluxoNovoMDFE() {
            var modal = document.getElementById('modalEscolhaOrigemMdfe');
            if (!modal) {
                abrirModalNovoMDFE();
                return;
            }
            new bootstrap.Modal(modal).show();
        }

        function iniciarNovoMDFEManual() {
            var escolha = document.getElementById('modalEscolhaOrigemMdfe');
            if (escolha) {
                var inst = bootstrap.Modal.getInstance(escolha);
                if (inst) inst.hide();
            }
            abrirModalNovoMDFE();
        }

        function abrirEscolhaImportacaoMDFE(tipo) {
            tipoImportacaoSelecionadoMDFE = String(tipo || '').toLowerCase();
            var texto = document.getElementById('modalImportacaoMdfeTexto');
            if (texto) {
                texto.textContent = tipoImportacaoSelecionadoMDFE === 'nfe'
                    ? 'Você escolheu partir de NF-e. Agora selecione a origem dos dados.'
                    : 'Você escolheu partir de CT-e. Agora selecione a origem dos dados.';
            }
            var escolha = document.getElementById('modalEscolhaOrigemMdfe');
            if (escolha) {
                var instEscolha = bootstrap.Modal.getInstance(escolha);
                if (instEscolha) instEscolha.hide();
            }
            var modalImport = document.getElementById('modalImportacaoMdfe');
            if (modalImport) new bootstrap.Modal(modalImport).show();
        }

        function confirmarImportacaoMDFE(origem) {
            var tipo = tipoImportacaoSelecionadoMDFE || 'cte';
            var origemNorm = String(origem || '').toLowerCase();
            var modalImport = document.getElementById('modalImportacaoMdfe');
            if (modalImport) {
                var inst = bootstrap.Modal.getInstance(modalImport);
                if (inst) inst.hide();
            }

            if (tipo === 'cte' && origemNorm === 'sistema') {
                abrirModalSelecionarCTEParaWizard();
                return;
            }

            if (tipo === 'cte' && origemNorm === 'xml') {
                mdfeNotify('Você será direcionado para CT-e para importar XML e depois vincular no MDF-e.');
                window.location.href = 'cte.php';
                return;
            }

            if (tipo === 'nfe' && origemNorm === 'sistema') {
                abrirModalSelecionarNFEParaMDFE();
                return;
            }

            if (tipo === 'nfe' && origemNorm === 'xml') {
                mdfeNotify('Você será direcionado para NF-e para importar XML e iniciar o MDF-e.');
                window.location.href = 'nfe.php';
                return;
            }

            abrirModalNovoMDFE();
        }

        function abrirModalSelecionarCTEParaWizard() {
            carregarCtesSistemaParaWizardMdfe().then(function() {
                var modal = document.getElementById('selecionarCTEWizardModal');
                if (modal) new bootstrap.Modal(modal).show();
            });
        }

        function carregarCtesSistemaParaWizardMdfe() {
            return mdfeApiFetch('../api/documentos_fiscais_v2.php?action=list&tipo=cte&status=autorizado&limit=200')
                .then(function(res) {
                    var data = res.data || {};
                    var sel = document.getElementById('cteSelectorWizardMdfe');
                    if (!sel) return;
                    if (!data.success || !Array.isArray(data.documentos) || !data.documentos.length) {
                        sel.innerHTML = '<p class="text-muted mb-0">Nenhum CT-e autorizado disponível.</p>';
                        atualizarTotaisCteWizardSelecionados();
                        return;
                    }
                    var html = '';
                    data.documentos.forEach(function(cte) {
                        var id = cte.id != null && cte.id !== '' ? Number(cte.id) : (cte.cte_id != null ? Number(cte.cte_id) : null);
                        if (id == null || isNaN(id) || id <= 0) return;
                        var dataF = cte.data_emissao ? new Date(cte.data_emissao).toLocaleDateString('pt-BR') : '-';
                        var valor = parseFloat(cte.valor_total || 0).toFixed(2).replace('.', ',');
                        var origem = String(cte.origem_cidade || cte.origem || 'N/A');
                        var destino = String(cte.destino_cidade || cte.destino || 'N/A');
                        html += '<div class="cte-item" onclick="toggleCteWizardMdfeItem(this)">';
                        html += '<input type="checkbox" name="cte_ids_wizard_mdfe[]" value="' + id + '" style="margin-right:8px"';
                        html += ' onclick="event.stopPropagation();" onchange="atualizarTotaisCteWizardSelecionados();"';
                        html += ' data-numero="' + escapeHtmlMdfe(String(cte.numero_cte || '').padStart(6, '0')) + '"';
                        html += ' data-serie="' + escapeHtmlMdfe(String(cte.serie_cte || '')) + '"';
                        html += ' data-chave="' + escapeHtmlMdfe(String(cte.chave_acesso || '')) + '"';
                        html += ' data-valor="' + (cte.valor_total || 0) + '"';
                        html += ' data-peso="' + (cte.peso_total || cte.peso_carga || 0) + '"';
                        html += ' data-origem-uf="' + escapeHtmlMdfe(String(cte.origem_estado || '')) + '"';
                        html += ' data-origem-cidade="' + escapeHtmlMdfe(origem) + '"';
                        html += ' data-destino-uf="' + escapeHtmlMdfe(String(cte.destino_estado || '')) + '"';
                        html += ' data-destino-cidade="' + escapeHtmlMdfe(destino) + '"';
                        html += ' data-nfe-ids="' + escapeHtmlMdfe(String(cte.nfe_ids || '[]')) + '">';
                        html += '<strong>CT-e ' + escapeHtmlMdfe(String(cte.numero_cte || '').padStart(6, '0')) + '</strong> ';
                        html += dataF + ' | ' + escapeHtmlMdfe(origem) + ' → ' + escapeHtmlMdfe(destino) + ' | R$ ' + valor;
                        html += '</div>';
                    });
                    sel.innerHTML = html || '<p class="text-muted mb-0">Nenhum CT-e autorizado disponível.</p>';
                    atualizarTotaisCteWizardSelecionados();
                })
                .catch(function() {
                    var sel = document.getElementById('cteSelectorWizardMdfe');
                    if (sel) sel.innerHTML = '<p class="text-danger mb-0">Erro ao carregar CT-e.</p>';
                    atualizarTotaisCteWizardSelecionados();
                });
        }

        function toggleCteWizardMdfeItem(el) {
            var cb = el ? el.querySelector('input[type="checkbox"]') : null;
            if (!cb) return;
            cb.checked = !cb.checked;
            el.classList.toggle('selected', cb.checked);
            atualizarTotaisCteWizardSelecionados();
        }

        function atualizarTotaisCteWizardSelecionados() {
            var cbs = document.querySelectorAll('#selecionarCTEWizardModal input[name="cte_ids_wizard_mdfe[]"]:checked');
            var totalValor = 0;
            var totalPeso = 0;
            cbs.forEach(function(cb) {
                totalValor += parseFloat(cb.getAttribute('data-valor') || 0);
                totalPeso += parseFloat(cb.getAttribute('data-peso') || 0);
            });
            var qtd = document.getElementById('totalCteWizardSelecionados');
            var peso = document.getElementById('totalPesoCteWizardSelecionados');
            var valor = document.getElementById('totalValorCteWizardSelecionados');
            if (qtd) qtd.value = cbs.length;
            if (peso) peso.value = totalPeso.toFixed(3);
            if (valor) valor.value = totalValor.toFixed(2);
        }

        function importarCteSelecionadosParaWizardMDFE() {
            var cbs = document.querySelectorAll('#selecionarCTEWizardModal input[name="cte_ids_wizard_mdfe[]"]:checked');
            if (!cbs.length) {
                mdfeNotify('Selecione pelo menos um CT-e.');
                return;
            }

            abrirModalNovoMDFE();

            var origemUfs = [];
            var origemCidades = [];
            var destinoUfs = [];
            var destinoCidades = [];
            var valorTotal = 0;
            var pesoTotal = 0;
            var docs = [];
            var cteOrigemIds = [];
            var nfeOrigemIds = [];

            cbs.forEach(function(cb) {
                var cteId = Number(cb.value || 0);
                if (cteId > 0 && cteOrigemIds.indexOf(cteId) === -1) cteOrigemIds.push(cteId);
                var chaveCte = String(cb.getAttribute('data-chave') || '').trim();
                var numeroCte = String(cb.getAttribute('data-numero') || '').trim();
                var valor = Number(cb.getAttribute('data-valor') || 0);
                var peso = Number(cb.getAttribute('data-peso') || 0);
                var ouf = String(cb.getAttribute('data-origem-uf') || '').trim().toUpperCase();
                var ocid = String(cb.getAttribute('data-origem-cidade') || '').trim();
                var duf = String(cb.getAttribute('data-destino-uf') || '').trim().toUpperCase();
                var dcid = String(cb.getAttribute('data-destino-cidade') || '').trim();
                var rawNfeIds = String(cb.getAttribute('data-nfe-ids') || '[]').trim();
                try {
                    var idsParse = JSON.parse(rawNfeIds);
                    if (Array.isArray(idsParse)) {
                        idsParse.map(function(x) { return Number(x || 0); }).forEach(function(idNfe) {
                            if (idNfe > 0 && nfeOrigemIds.indexOf(idNfe) === -1) nfeOrigemIds.push(idNfe);
                        });
                    }
                } catch (_) {}

                if (ouf && origemUfs.indexOf(ouf) === -1) origemUfs.push(ouf);
                if (ocid && origemCidades.indexOf(ocid) === -1) origemCidades.push(ocid);
                if (duf && destinoUfs.indexOf(duf) === -1) destinoUfs.push(duf);
                if (dcid && destinoCidades.indexOf(dcid) === -1) destinoCidades.push(dcid);

                valorTotal += valor;
                pesoTotal += peso;

                if (chaveCte) {
                    docs.push({
                        municipioDescarregamento: dcid,
                        tipoAcao: 'adicionar',
                        chaveNfe: '',
                        numeroNfe: '',
                        serieNfe: '',
                        valorNfe: 0,
                        chaveCte: chaveCte,
                        numeroCte: numeroCte
                    });
                }
            });

            var finalizarImportacaoCte = function() {
                docDocumentosNovoMdfe = docs;
                renderDocumentosNovoMDFE();

                var totalCteLegacy = document.getElementById('totalCTe');
                if (totalCteLegacy) totalCteLegacy.value = String(cbs.length);
                var pesoLegacy = document.getElementById('totalPesoMDFE');
                if (pesoLegacy) pesoLegacy.value = pesoTotal.toFixed(2);
                var valorLegacy = document.getElementById('valorTotalMDFE');
                if (valorLegacy) valorLegacy.value = valorTotal.toFixed(2);

                var totalNfeField = document.getElementById('tot_total_nfe');
                var valorCargaField = document.getElementById('tot_valor_total_carga');
                var pesoField = document.getElementById('tot_peso_total');
                if (totalNfeField) {
                    var totalNfe = docs.filter(function(x) { return !!String(x.chaveNfe || '').trim(); }).length;
                    totalNfeField.value = String(totalNfe);
                }
                if (valorCargaField) valorCargaField.value = valorTotal.toFixed(2);
                if (pesoField && pesoTotal > 0) pesoField.value = pesoTotal.toFixed(3);
                if (typeof atualizarJsonTotalizadoresNovoMDFE === 'function') {
                    atualizarJsonTotalizadoresNovoMDFE();
                }
            };

            var buscarNfesVinculadas = function() {
                if (!nfeOrigemIds.length) {
                    finalizarImportacaoCte();
                    return;
                }
                mdfeApiFetch('../api/documentos_fiscais_v2.php?action=list&tipo=nfe&limit=500')
                    .then(function(r) {
                        var resp = r.data || {};
                        if (!resp || !resp.success || !Array.isArray(resp.documentos)) {
                            finalizarImportacaoCte();
                            return;
                        }
                        var mapa = {};
                        resp.documentos.forEach(function(n) {
                            var id = Number(n.id || 0);
                            if (id > 0) mapa[id] = n;
                        });
                        nfeOrigemIds.forEach(function(idNfe) {
                            var nfe = mapa[idNfe];
                            if (!nfe) return;
                            var chaveNfe = String(nfe.chave_acesso || '').trim();
                            if (!chaveNfe) return;
                            var existe = docs.some(function(d) { return String(d.chaveNfe || '').trim() === chaveNfe; });
                            if (existe) return;
                            docs.push({
                                municipioDescarregamento: String(nfe.municipio_descarregamento || nfe.destino_cidade || ''),
                                tipoAcao: 'adicionar',
                                chaveNfe: chaveNfe,
                                numeroNfe: String(nfe.numero_nfe || '').trim(),
                                serieNfe: String(nfe.serie_nfe || '').trim(),
                                valorNfe: Number(nfe.valor_total || 0),
                                chaveCte: '',
                                numeroCte: ''
                            });
                        });
                        finalizarImportacaoCte();
                    })
                    .catch(function() {
                        finalizarImportacaoCte();
                    });
            };

            if (origemUfs.length === 1) {
                var ufCarga = document.getElementById('novo_mdfe_uf_carga');
                if (ufCarga) {
                    ufCarga.value = origemUfs[0];
                    ufCarga.dispatchEvent(new Event('change'));
                    if (origemCidades.length === 1) {
                        setTimeout(function() {
                            var munCarga = document.getElementById('novo_mdfe_municipio_carga');
                            if (munCarga) munCarga.value = origemCidades[0];
                        }, 300);
                    }
                }
            }
            if (destinoUfs.length === 1) {
                var ufDesc = document.getElementById('novo_mdfe_uf_descarga');
                if (ufDesc) {
                    ufDesc.value = destinoUfs[0];
                    ufDesc.dispatchEvent(new Event('change'));
                    if (destinoCidades.length === 1) {
                        setTimeout(function() {
                            var munDesc = document.getElementById('novo_mdfe_municipio_descarga');
                            if (munDesc) munDesc.value = destinoCidades[0];
                            atualizarMunicipioDescargaDocumentosNovoMDFE();
                        }, 300);
                    }
                }
            }

            var tipoEmitente = document.getElementById('novo_mdfe_tipo_emitente');
            if (tipoEmitente && !tipoEmitente.value) {
                tipoEmitente.value = '1';
                tipoEmitente.dispatchEvent(new Event('change'));
            }

            novoMdfeOrigem = 'cte';
            novoMdfeOrigemCteIds = cteOrigemIds.slice();
            novoMdfeOrigemNfeIds = nfeOrigemIds.slice();
            atualizarOrigemMdfeHiddenFields();

            setNovoMdfeTab('3');

            var modal = document.getElementById('selecionarCTEWizardModal');
            if (modal) {
                var inst = bootstrap.Modal.getInstance(modal);
                if (inst) inst.hide();
            }

            buscarNfesVinculadas();
        }

        function abrirModalSelecionarNFEParaMDFE() {
            carregarNfesSistemaParaMdfe().then(function() {
                var modal = document.getElementById('selecionarNFEModal');
                if (modal) new bootstrap.Modal(modal).show();
            });
        }

        function carregarNfesSistemaParaMdfe() {
            return mdfeApiFetch('../api/documentos_fiscais_v2.php?action=list&tipo=nfe&limit=200')
                .then(function(res) {
                    var data = res.data || {};
                    var sel = document.getElementById('nfeSelectorMdfe');
                    if (!sel) return;
                    if (!data.success || !Array.isArray(data.documentos) || !data.documentos.length) {
                        sel.innerHTML = '<p class="text-muted mb-0">Nenhuma NF-e disponível no sistema.</p>';
                        atualizarTotaisNfeSelecionadasMdfe();
                        return;
                    }
                    var html = '';
                    data.documentos.forEach(function(nfe) {
                        var id = nfe.id != null && nfe.id !== '' ? Number(nfe.id) : null;
                        if (id == null || isNaN(id) || id <= 0) return;
                        var dataF = nfe.data_emissao ? new Date(nfe.data_emissao).toLocaleDateString('pt-BR') : '-';
                        var valor = parseFloat(nfe.valor_total || 0).toFixed(2).replace('.', ',');
                        var numero = String(nfe.numero_nfe || '').padStart(9, '0');
                        var chave = String(nfe.chave_acesso || '');
                        html += '<div class="cte-item" onclick="toggleNfeMdfeItem(this)">';
                        html += '<input type="checkbox" name="nfe_ids_mdfe[]" value="' + id + '" style="margin-right:8px"';
                        html += ' onclick="event.stopPropagation();" onchange="atualizarTotaisNfeSelecionadasMdfe();"';
                        html += ' data-numero="' + escapeHtmlMdfe(numero) + '"';
                        html += ' data-serie="' + escapeHtmlMdfe(String(nfe.serie_nfe || '')) + '"';
                        html += ' data-chave="' + escapeHtmlMdfe(chave) + '"';
                        html += ' data-valor="' + (nfe.valor_total || 0) + '"';
                        html += ' data-peso="' + (nfe.peso_carga || 0) + '">';
                        html += '<strong>NF-e ' + escapeHtmlMdfe(numero || '-') + '</strong> ';
                        html += dataF + ' | Chave: ' + escapeHtmlMdfe(chave || '-') + ' | R$ ' + valor;
                        html += '</div>';
                    });
                    sel.innerHTML = html || '<p class="text-muted mb-0">Nenhuma NF-e disponível no sistema.</p>';
                    atualizarTotaisNfeSelecionadasMdfe();
                })
                .catch(function() {
                    var sel = document.getElementById('nfeSelectorMdfe');
                    if (sel) sel.innerHTML = '<p class="text-danger mb-0">Erro ao carregar NF-e.</p>';
                    atualizarTotaisNfeSelecionadasMdfe();
                });
        }

        function toggleNfeMdfeItem(el) {
            var cb = el ? el.querySelector('input[type="checkbox"]') : null;
            if (!cb) return;
            cb.checked = !cb.checked;
            el.classList.toggle('selected', cb.checked);
            atualizarTotaisNfeSelecionadasMdfe();
        }

        function atualizarTotaisNfeSelecionadasMdfe() {
            var cbs = document.querySelectorAll('#selecionarNFEModal input[name="nfe_ids_mdfe[]"]:checked');
            var total = 0;
            cbs.forEach(function(cb) {
                total += parseFloat(cb.getAttribute('data-valor') || 0);
            });
            var qtd = document.getElementById('totalNfeSelecionadasMdfe');
            var valor = document.getElementById('totalValorNfeSelecionadasMdfe');
            if (qtd) qtd.value = cbs.length;
            if (valor) valor.value = total.toFixed(2);
        }

        function importarNfeSelecionadasParaMDFE() {
            var cbs = document.querySelectorAll('#selecionarNFEModal input[name="nfe_ids_mdfe[]"]:checked');
            if (!cbs.length) {
                mdfeNotify('Selecione pelo menos uma NF-e.');
                return;
            }

            abrirModalNovoMDFE();

            var munDesc = ((document.getElementById('novo_mdfe_municipio_descarga') || {}).value || '').trim();
            var adicionados = 0;
            var valorTotal = 0;
            var pesoTotal = 0;
            cbs.forEach(function(cb) {
                var chave = String(cb.getAttribute('data-chave') || '').trim();
                if (!chave) return;
                var existe = docDocumentosNovoMdfe.some(function(item) {
                    return String(item.chaveNfe || '').trim() === chave;
                });
                if (existe) return;
                docDocumentosNovoMdfe.push({
                    municipioDescarregamento: munDesc,
                    tipoAcao: 'adicionar',
                    chaveNfe: chave,
                    numeroNfe: String(cb.getAttribute('data-numero') || '').trim(),
                    serieNfe: String(cb.getAttribute('data-serie') || '').trim(),
                    valorNfe: Number(cb.getAttribute('data-valor') || 0),
                    chaveCte: '',
                    numeroCte: ''
                });
                valorTotal += Number(cb.getAttribute('data-valor') || 0);
                pesoTotal += Number(cb.getAttribute('data-peso') || 0);
                adicionados++;
            });
            renderDocumentosNovoMDFE();
            var totalNfeField = document.getElementById('tot_total_nfe');
            var valorCargaField = document.getElementById('tot_valor_total_carga');
            var pesoField = document.getElementById('tot_peso_total');
            if (totalNfeField) totalNfeField.value = String(docDocumentosNovoMdfe.length);
            if (valorCargaField && valorTotal > 0) valorCargaField.value = valorTotal.toFixed(2);
            if (pesoField && pesoTotal > 0) pesoField.value = pesoTotal.toFixed(3);
            if (typeof atualizarJsonTotalizadoresNovoMDFE === 'function') {
                atualizarJsonTotalizadoresNovoMDFE();
            }
            var tipoEmitente = document.getElementById('novo_mdfe_tipo_emitente');
            if (tipoEmitente) {
                tipoEmitente.value = '2';
                tipoEmitente.dispatchEvent(new Event('change'));
            }
            var tipoTransportador = document.getElementById('novo_mdfe_tipo_transportador');
            if (tipoTransportador && !tipoTransportador.value) {
                tipoTransportador.value = '1';
                tipoTransportador.dispatchEvent(new Event('change'));
            }
            novoMdfeOrigem = 'nfe';
            novoMdfeOrigemCteIds = [];
            novoMdfeOrigemNfeIds = Array.from(cbs).map(function(cb){ return Number(cb.value || 0); }).filter(function(v){ return v > 0; });
            atualizarOrigemMdfeHiddenFields();
            setNovoMdfeTab('3');

            var modal = document.getElementById('selecionarNFEModal');
            if (modal) {
                var inst = bootstrap.Modal.getInstance(modal);
                if (inst) inst.hide();
            }

            if (!munDesc) {
                mdfeNotify('NF-e importadas para o MDF-e. Agora selecione o município de descarga na aba "Dados do MDF-e" para concluir as validações.');
            } else {
                mdfeNotify(adicionados + ' NF-e importada(s) para o MDF-e.');
            }
        }

        function setupTabsNovoMdfe() {
            var botoes = document.querySelectorAll('#novoMDFEWizardModal .route-tab-btn');
            botoes.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var tab = this.getAttribute('data-route-tab');
                    setNovoMdfeTab(tab);
                });
            });
        }

        function setupSubtabsRodoviarioNovoMdfe() {
            var botoes = document.querySelectorAll('#novoMDFEWizardModal .mdfe-subtab-btn[data-mdfe-rodo-tab]');
            botoes.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var tab = this.getAttribute('data-mdfe-rodo-tab');
                    setRodoviarioSubtabNovoMdfe(tab);
                });
            });
        }

        function setRodoviarioSubtabNovoMdfe(tab) {
            var botoes = document.querySelectorAll('#novoMDFEWizardModal .mdfe-subtab-btn[data-mdfe-rodo-tab]');
            botoes.forEach(function(btn) {
                btn.classList.toggle('is-active', btn.getAttribute('data-mdfe-rodo-tab') === String(tab));
            });
            var panes = document.querySelectorAll('#novoMDFEWizardModal .mdfe-subtab-pane[data-mdfe-rodo-tab]');
            panes.forEach(function(pane) {
                pane.classList.toggle('is-active', pane.getAttribute('data-mdfe-rodo-tab') === String(tab));
            });
        }

        function setNovoMdfeTab(tab) {
            var botoes = document.querySelectorAll('#novoMDFEWizardModal .route-tab-btn');
            botoes.forEach(function(btn) {
                btn.classList.toggle('is-active', btn.getAttribute('data-route-tab') === String(tab));
            });
            var panes = document.querySelectorAll('#novoMDFEWizardModal .route-modal-tab-pane');
            panes.forEach(function(pane) {
                pane.classList.toggle('is-active', pane.getAttribute('data-route-tab') === String(tab));
            });
            if (String(tab) === '3') {
                atualizarMunicipioDescargaDocumentosNovoMDFE();
                updateCteFieldsVisibilityDocumentosNovoMDFE();
            }
        }

        function adicionarUfPercursoNovoMDFE() {
            var sel = document.getElementById('novo_mdfe_uf_percurso');
            if (!sel) return;
            var uf = sel.value || '';
            if (!uf) return;
            if (novoMdfeUfsPercurso.indexOf(uf) === -1) {
                novoMdfeUfsPercurso.push(uf);
                renderUfsPercursoNovoMDFE();
            }
            sel.value = '';
        }

        function removerUfPercursoNovoMDFE(uf) {
            novoMdfeUfsPercurso = novoMdfeUfsPercurso.filter(function(item) { return item !== uf; });
            renderUfsPercursoNovoMDFE();
        }

        function renderUfsPercursoNovoMDFE() {
            var wrap = document.getElementById('novo_mdfe_ufs_percurso_lista');
            var hidden = document.getElementById('novo_mdfe_ufs_percurso_hidden');
            if (!wrap || !hidden) return;

            hidden.value = novoMdfeUfsPercurso.join(',');
            if (novoMdfeUfsPercurso.length === 0) {
                wrap.innerHTML = '<span class="text-muted small">Nenhuma UF de percurso adicionada.</span>';
                return;
            }
            wrap.innerHTML = novoMdfeUfsPercurso.map(function(uf) {
                return '<span class="badge rounded-pill text-bg-secondary">' + uf +
                    ' <button type="button" class="btn-close btn-close-white btn-sm ms-1" aria-label="Remover" onclick="removerUfPercursoNovoMDFE(\'' + uf + '\')"></button></span>';
            }).join('');
        }

        function setupVeiculoEmitenteToggleNovoMdfe() {
            var radios = document.querySelectorAll('input[name="rod_veiculo_empresa_emitente"]');
            radios.forEach(function(radio) {
                radio.addEventListener('change', updateVeiculoEmitenteVisibilityNovoMdfe);
            });
        }

        function updateVeiculoEmitenteVisibilityNovoMdfe() {
            var wrap = document.getElementById('mdfeProprietarioWrap');
            if (!wrap) return;
            var selecionado = document.querySelector('input[name="rod_veiculo_empresa_emitente"]:checked');
            var mostrar = selecionado && selecionado.value === 'nao';
            wrap.classList.toggle('is-visible', !!mostrar);
        }

        function adicionarCondutorNovoMDFE() {
            var inputNome = document.getElementById('rod_condutor_nome');
            var inputCpf = document.getElementById('rod_condutor_cpf');
            if (!inputNome || !inputCpf) return;

            var nome = (inputNome.value || '').trim();
            var cpf = (inputCpf.value || '').trim();
            var cpfNumerico = cpf.replace(/\D/g, '');
            if (!nome) {
                mdfeNotify('Informe o nome do condutor.');
                inputNome.focus();
                return;
            }
            if (cpfNumerico.length !== 11) {
                mdfeNotify('Informe um CPF válido com 11 dígitos.');
                inputCpf.focus();
                return;
            }

            rodCondutoresNovoMdfe.push({ nome: nome, cpf: cpf });
            inputNome.value = '';
            inputCpf.value = '';
            renderCondutoresNovoMDFE();
        }

        function removerCondutorNovoMDFE(index) {
            rodCondutoresNovoMdfe = rodCondutoresNovoMdfe.filter(function(_, i) { return i !== Number(index); });
            renderCondutoresNovoMDFE();
        }

        function renderCondutoresNovoMDFE() {
            var tbody = document.getElementById('rodCondutoresTabelaBody');
            var hidden = document.getElementById('rod_condutores_json');
            if (!tbody || !hidden) return;

            hidden.value = JSON.stringify(rodCondutoresNovoMdfe);
            if (rodCondutoresNovoMdfe.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-muted">Nenhum condutor adicionado.</td></tr>';
                return;
            }

            tbody.innerHTML = rodCondutoresNovoMdfe.map(function(item, idx) {
                var nome = (item.nome || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                var cpf = (item.cpf || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                return '<tr>' +
                    '<td>' + nome + '</td>' +
                    '<td>' + cpf + '</td>' +
                    '<td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removerCondutorNovoMDFE(' + idx + ')"><i class="fas fa-trash"></i></button></td>' +
                    '</tr>';
            }).join('');
        }

        function obterTipoEmitenteAtualNovoMDFE() {
            return ((document.getElementById('novo_mdfe_tipo_emitente') || {}).value || '').trim();
        }

        function obterTipoTransportadorAtualNovoMDFE() {
            return ((document.getElementById('novo_mdfe_tipo_transportador') || {}).value || '').trim();
        }

        function mostrarOuOcultarSubAbaRodoviarioNovoMDFE(tabId, mostrar) {
            var btn = document.querySelector('#novoMDFEWizardModal .mdfe-subtab-btn[data-mdfe-rodo-tab="' + tabId + '"]');
            var pane = document.querySelector('#novoMDFEWizardModal .mdfe-subtab-pane[data-mdfe-rodo-tab="' + tabId + '"]');
            if (btn) btn.classList.toggle('is-hidden', !mostrar);
            if (pane) pane.classList.toggle('is-hidden', !mostrar);
        }

        function aplicarRegrasPorTipoEmitenteNovoMDFE() {
            var tipoEmitente = obterTipoEmitenteAtualNovoMDFE();
            var tipoTransportador = obterTipoTransportadorAtualNovoMDFE();
            var ehCargaPropria = tipoEmitente === '2';
            var ehPrestador = tipoEmitente === '1' || tipoEmitente === '3';

            // Tipo 2 (carga própria): bloquear CIOT, vale pedágio, contratante e pagamento frete.
            mostrarOuOcultarSubAbaRodoviarioNovoMDFE('2', !ehCargaPropria);
            mostrarOuOcultarSubAbaRodoviarioNovoMDFE('4', !ehCargaPropria);
            mostrarOuOcultarSubAbaRodoviarioNovoMDFE('5', !ehCargaPropria);
            mostrarOuOcultarSubAbaRodoviarioNovoMDFE('6', !ehCargaPropria);

            // RNTRC obrigatório para prestador (1/3), opcional para carga própria.
            var rntrc = document.getElementById('rod_rntrc');
            if (rntrc) rntrc.required = ehPrestador;

            // CT-e no bloco documentos só faz sentido para tipo 1 e 3.
            updateCteFieldsVisibilityDocumentosNovoMDFE();

            // CIOT obrigatório se transportador for TAC (2), exceto carga própria.
            var ciotNumero = document.getElementById('rod_ciot_numero');
            if (ciotNumero) ciotNumero.required = !ehCargaPropria && tipoTransportador === '2';

            // Se aba atual ficou oculta, volta para veículo de tração.
            var ativa = document.querySelector('#novoMDFEWizardModal .mdfe-subtab-btn.is-active');
            if (ativa && ativa.classList.contains('is-hidden')) {
                setRodoviarioSubtabNovoMdfe('1');
            }
        }

        function mostrarFormCiotNovoMDFE() {
            toggleFormMdfeById('rodCiotFormWrap', true);
        }

        function limparFormCiotNovoMDFE() {
            rodCiotEditIndex = -1;
            ['rod_ciot_numero','rod_ciot_valor_frete','rod_ciot_cpf_cnpj_tac','rod_ciot_ipef'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.value = '';
            });
            toggleFormMdfeById('rodCiotFormWrap', false);
        }

        function cancelarCiotNovoMDFE() {
            limparFormCiotNovoMDFE();
        }

        function gravarCiotNovoMDFE() {
            var numero = document.getElementById('rod_ciot_numero');
            if (!numero) return;
            if (!numero.value.trim()) {
                mdfeNotify('Informe o número do CIOT.');
                numero.focus();
                return;
            }
            var tacEl = document.getElementById('rod_ciot_cpf_cnpj_tac');
            var tacVal = tacEl ? tacEl.value.trim() : '';
            if (tacVal && !mdfeExigirCpfCnpjOpcional(tacVal, 'CPF/CNPJ TAC')) {
                if (tacEl) tacEl.focus();
                return;
            }
            var item = {
                numeroCiot: numero.value.trim(),
                valorFrete: Number(((document.getElementById('rod_ciot_valor_frete') || {}).value || 0)),
                cpfCnpjTac: tacVal,
                ipef: ((document.getElementById('rod_ciot_ipef') || {}).value || '').trim()
            };
            if (rodCiotEditIndex >= 0) rodCiotNovoMdfe[rodCiotEditIndex] = item;
            else rodCiotNovoMdfe.push(item);
            renderCiotNovoMDFE();
            limparFormCiotNovoMDFE();
        }

        function editarCiotNovoMDFE(index) {
            var item = rodCiotNovoMdfe[index];
            if (!item) return;
            rodCiotEditIndex = Number(index);
            document.getElementById('rod_ciot_numero').value = item.numeroCiot || '';
            document.getElementById('rod_ciot_valor_frete').value = item.valorFrete || '';
            document.getElementById('rod_ciot_cpf_cnpj_tac').value = item.cpfCnpjTac || '';
            document.getElementById('rod_ciot_ipef').value = item.ipef || '';
            toggleFormMdfeById('rodCiotFormWrap', true);
        }

        function excluirCiotNovoMDFE(index) {
            rodCiotNovoMdfe = rodCiotNovoMdfe.filter(function(_, i) { return i !== Number(index); });
            renderCiotNovoMDFE();
        }

        function renderCiotNovoMDFE() {
            var tbody = document.getElementById('rodCiotTabelaBody');
            var hidden = document.getElementById('rod_ciot_json');
            if (!tbody || !hidden) return;
            hidden.value = JSON.stringify(rodCiotNovoMdfe);
            if (!rodCiotNovoMdfe.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-muted">Nenhum CIOT adicionado.</td></tr>';
                return;
            }
            tbody.innerHTML = rodCiotNovoMdfe.map(function(item, idx) {
                return '<tr>' +
                    '<td>' + escapeHtmlMdfe(item.numeroCiot || '-') + '</td>' +
                    '<td>' + (item.valorFrete ? ('R$ ' + formatarValorMoedaMdfe(item.valorFrete)) : '-') + '</td>' +
                    '<td>' + escapeHtmlMdfe(item.cpfCnpjTac || '-') + '</td>' +
                    '<td>' + escapeHtmlMdfe(item.ipef || '-') + '</td>' +
                    '<td><button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="editarCiotNovoMDFE(' + idx + ')"><i class="fas fa-edit"></i></button>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger" onclick="excluirCiotNovoMDFE(' + idx + ')"><i class="fas fa-trash"></i></button></td>' +
                    '</tr>';
            }).join('');
        }

        function escapeHtmlMdfe(texto) {
            return String(texto == null ? '' : texto)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatarValorMoedaMdfe(valor) {
            var num = Number(valor || 0);
            return num.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function somenteDigitosMdfe(valor) {
            return String(valor || '').replace(/\D/g, '');
        }

        /** CPF/CNPJ — alinhado a js/doc_validators.js (DocValidators). */
        function mdfeExigirCpfCnpjOpcional(valor, label) {
            var t = (valor || '').trim();
            if (!t) return true;
            return mdfeExigirCpfCnpjObrigatorio(t, label);
        }
        function mdfeExigirCpfCnpjObrigatorio(valor, label) {
            var d = somenteDigitosMdfe(valor);
            var L = label || 'Documento';
            if (d.length !== 11 && d.length !== 14) {
                mdfeNotify(L + ': informe CPF (11 dígitos) ou CNPJ (14 dígitos).', 'warning');
                return false;
            }
            if (typeof DocValidators === 'undefined') {
                mdfeNotify(L + ': validação de documento indisponível. Verifique se doc_validators.js está carregado.', 'error');
                return false;
            }
            if (d.length === 11 && !DocValidators.validarCpf(d)) {
                mdfeNotify(L + ': CPF inválido (dígitos verificadores).', 'error');
                return false;
            }
            if (d.length === 14 && !DocValidators.validarCnpj(d)) {
                mdfeNotify(L + ': CNPJ inválido (dígitos verificadores).', 'error');
                return false;
            }
            return true;
        }
        function mdfeExigirCnpjObrigatorio(valor, label) {
            var d = somenteDigitosMdfe(valor);
            if (d.length !== 14) {
                mdfeNotify((label || 'CNPJ') + ': informe 14 dígitos.', 'warning');
                return false;
            }
            if (typeof DocValidators === 'undefined') {
                mdfeNotify((label || 'CNPJ') + ': validação indisponível. Recarregue a página.', 'error');
                return false;
            }
            if (!DocValidators.validarCnpj(d)) {
                mdfeNotify((label || 'CNPJ') + ' inválido (dígitos verificadores).', 'error');
                return false;
            }
            return true;
        }
        function mdfeExigirCpfObrigatorio(valor, label) {
            var d = somenteDigitosMdfe(valor);
            if (d.length !== 11) {
                mdfeNotify((label || 'CPF') + ': informe 11 dígitos.', 'warning');
                return false;
            }
            if (typeof DocValidators === 'undefined') {
                mdfeNotify((label || 'CPF') + ': validação indisponível. Recarregue a página.', 'error');
                return false;
            }
            if (!DocValidators.validarCpf(d)) {
                mdfeNotify((label || 'CPF') + ' inválido (dígitos verificadores).', 'error');
                return false;
            }
            return true;
        }

        function toggleFormMdfeById(id, visivel) {
            var wrap = document.getElementById(id);
            if (!wrap) return;
            wrap.classList.toggle('is-hidden', !visivel);
        }

        function setupTipoPessoaRodoviarioNovoMdfe() {
            var tiposContratante = document.querySelectorAll('input[name="rod_contratante_tipo_pessoa"]');
            tiposContratante.forEach(function(el) { el.addEventListener('change', updateTipoPessoaContratanteNovoMDFE); });
            var tiposPagamento = document.querySelectorAll('input[name="rod_pag_tipo_pessoa"]');
            tiposPagamento.forEach(function(el) { el.addEventListener('change', updateTipoPessoaPagamentoNovoMDFE); });
        }

        function setupDocumentosNovoMdfe() {
            var tipoEmitente = document.getElementById('novo_mdfe_tipo_emitente');
            if (tipoEmitente) {
                tipoEmitente.addEventListener('change', function() {
                    updateCteFieldsVisibilityDocumentosNovoMDFE();
                    aplicarRegrasPorTipoEmitenteNovoMDFE();
                });
            }
            var municipioDesc = document.getElementById('novo_mdfe_municipio_descarga');
            if (municipioDesc) {
                municipioDesc.addEventListener('change', atualizarMunicipioDescargaDocumentosNovoMDFE);
            }
            var tipoTransportador = document.getElementById('novo_mdfe_tipo_transportador');
            if (tipoTransportador) {
                tipoTransportador.addEventListener('change', aplicarRegrasPorTipoEmitenteNovoMDFE);
            }
        }

        function setupProdutoPredominanteNovoMdfe() {
            var cargaLotacao = document.getElementById('prod_carga_lotacao');
            if (cargaLotacao) {
                cargaLotacao.addEventListener('change', updateCamposCargaLotacaoProdutoNovoMDFE);
            }
        }

        function setupTotalizadoresNovoMdfe() {
            var botoes = document.querySelectorAll('#novoMDFEWizardModal [data-mdfe-total-tab]');
            botoes.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var tab = this.getAttribute('data-mdfe-total-tab');
                    setTotalizadoresTabNovoMDFE(tab);
                });
            });
            ['tot_total_nfe','tot_valor_total_carga','tot_unidade_medida_carga','tot_peso_total'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) {
                    el.addEventListener('input', atualizarJsonTotalizadoresNovoMDFE);
                    el.addEventListener('change', atualizarJsonTotalizadoresNovoMDFE);
                }
            });
        }

        function getTipoPessoaSelecionadoMdfe(groupName) {
            var selecionado = document.querySelector('input[name="' + groupName + '"]:checked');
            return selecionado ? selecionado.value : '';
        }

        function updateTipoPessoaContratanteNovoMDFE() {
            var tipo = getTipoPessoaSelecionadoMdfe('rod_contratante_tipo_pessoa');
            var label = document.getElementById('rodContratanteDocLabel');
            if (!label) return;
            if (tipo === 'fisica') label.textContent = 'CPF *';
            else if (tipo === 'estrangeiro') label.textContent = 'Documento estrangeiro *';
            else label.textContent = 'CNPJ *';
        }

        function updateTipoPessoaPagamentoNovoMDFE() {
            var tipo = getTipoPessoaSelecionadoMdfe('rod_pag_tipo_pessoa');
            var label = document.getElementById('rodPagDocLabel');
            if (!label) return;
            if (tipo === 'fisica') label.textContent = 'CPF *';
            else if (tipo === 'estrangeiro') label.textContent = 'Documento estrangeiro *';
            else label.textContent = 'CNPJ *';
        }

        function mostrarFormValePedagioNovoMDFE() {
            toggleFormMdfeById('rodValePedagioFormWrap', true);
        }

        function focarValorValePedagioNovoMDFE() {
            var input = document.getElementById('rod_vp_valor');
            if (input) input.focus();
        }

        function pesquisarValePedagioNovoMDFE() {
            mdfeNotify('Pesquisa de vale pedágio preparada para integração. Você pode preencher manualmente e gravar.');
        }

        function limparFormValePedagioNovoMDFE() {
            rodValePedagioEditIndex = -1;
            ['rod_vp_eixos','rod_vp_valor','rod_vp_tipo','rod_vp_cnpj_fornecedor','rod_vp_num_comprovante','rod_vp_resp_pagamento'].forEach(function(id){
                var el = document.getElementById(id);
                if (el) el.value = '';
            });
            toggleFormMdfeById('rodValePedagioFormWrap', false);
        }

        function gravarValePedagioNovoMDFE() {
            var eixos = document.getElementById('rod_vp_eixos');
            var valor = document.getElementById('rod_vp_valor');
            var cnpjForn = document.getElementById('rod_vp_cnpj_fornecedor');
            var resp = document.getElementById('rod_vp_resp_pagamento');
            if (!eixos || !valor || !cnpjForn || !resp) return;
            if (!eixos.value) { mdfeNotify('Selecione os eixos do veículo.'); eixos.focus(); return; }
            if (!cnpjForn.value.trim()) { mdfeNotify('Informe o CNPJ da empresa fornecedora.'); cnpjForn.focus(); return; }
            if (!mdfeExigirCnpjObrigatorio(cnpjForn.value, 'CNPJ da empresa fornecedora')) { cnpjForn.focus(); return; }
            if (!valor.value || Number(valor.value) <= 0) { mdfeNotify('Informe um valor válido para o vale pedágio.'); valor.focus(); return; }
            if (!resp.value.trim()) { mdfeNotify('Informe CPF/CNPJ do responsável pelo pagamento.'); resp.focus(); return; }
            if (!mdfeExigirCpfCnpjObrigatorio(resp.value, 'Responsável pelo pagamento')) { resp.focus(); return; }

            var item = {
                eixos: eixos.value,
                valor: Number(valor.value),
                tipo: (document.getElementById('rod_vp_tipo') || {}).value || '',
                cnpjFornecedor: cnpjForn.value.trim(),
                numeroComprovante: ((document.getElementById('rod_vp_num_comprovante') || {}).value || '').trim(),
                responsavelPagamento: resp.value.trim()
            };
            if (rodValePedagioEditIndex >= 0) rodValesPedagioNovoMdfe[rodValePedagioEditIndex] = item;
            else rodValesPedagioNovoMdfe.push(item);

            renderValesPedagioNovoMDFE();
            limparFormValePedagioNovoMDFE();
        }

        function editarValePedagioNovoMDFE(index) {
            var item = rodValesPedagioNovoMdfe[index];
            if (!item) return;
            rodValePedagioEditIndex = Number(index);
            document.getElementById('rod_vp_eixos').value = item.eixos || '';
            document.getElementById('rod_vp_valor').value = item.valor || '';
            document.getElementById('rod_vp_tipo').value = item.tipo || '';
            document.getElementById('rod_vp_cnpj_fornecedor').value = item.cnpjFornecedor || '';
            document.getElementById('rod_vp_num_comprovante').value = item.numeroComprovante || '';
            document.getElementById('rod_vp_resp_pagamento').value = item.responsavelPagamento || '';
            toggleFormMdfeById('rodValePedagioFormWrap', true);
        }

        function excluirValePedagioNovoMDFE(index) {
            rodValesPedagioNovoMdfe = rodValesPedagioNovoMdfe.filter(function(_, i) { return i !== Number(index); });
            renderValesPedagioNovoMDFE();
        }

        function cancelarValePedagioNovoMDFE() {
            limparFormValePedagioNovoMDFE();
        }

        function renderValesPedagioNovoMDFE() {
            var tbody = document.getElementById('rodValePedagioTabelaBody');
            var hidden = document.getElementById('rod_vales_pedagio_json');
            if (!tbody || !hidden) return;
            hidden.value = JSON.stringify(rodValesPedagioNovoMdfe);
            if (!rodValesPedagioNovoMdfe.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-muted">Nenhum vale pedágio adicionado.</td></tr>';
                return;
            }
            tbody.innerHTML = rodValesPedagioNovoMdfe.map(function(item, idx) {
                return '<tr>' +
                    '<td>' + escapeHtmlMdfe(item.eixos + ' eixos') + '</td>' +
                    '<td>' + escapeHtmlMdfe(item.cnpjFornecedor) + '</td>' +
                    '<td>' + escapeHtmlMdfe(item.numeroComprovante || '-') + '</td>' +
                    '<td>' + escapeHtmlMdfe(item.tipo || '-') + '</td>' +
                    '<td>R$ ' + formatarValorMoedaMdfe(item.valor) + '</td>' +
                    '<td>' + escapeHtmlMdfe(item.responsavelPagamento) + '</td>' +
                    '<td><button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="editarValePedagioNovoMDFE(' + idx + ')"><i class="fas fa-edit"></i></button>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger" onclick="excluirValePedagioNovoMDFE(' + idx + ')"><i class="fas fa-trash"></i></button></td>' +
                    '</tr>';
            }).join('');
        }

        function mostrarFormContratanteNovoMDFE() {
            toggleFormMdfeById('rodContratanteFormWrap', true);
        }

        function limparFormContratanteNovoMDFE() {
            rodContratanteEditIndex = -1;
            ['rod_contratante_doc','rod_contratante_razao_social','rod_contratante_numero_contrato','rod_contratante_valor'].forEach(function(id){
                var el = document.getElementById(id);
                if (el) el.value = '';
            });
            var tipoJ = document.getElementById('rod_contratante_tipo_juridica');
            if (tipoJ) tipoJ.checked = true;
            updateTipoPessoaContratanteNovoMDFE();
            toggleFormMdfeById('rodContratanteFormWrap', false);
        }

        function cancelarContratanteNovoMDFE() {
            limparFormContratanteNovoMDFE();
        }

        function gravarContratanteNovoMDFE() {
            var tipoPessoa = getTipoPessoaSelecionadoMdfe('rod_contratante_tipo_pessoa') || 'juridica';
            var doc = document.getElementById('rod_contratante_doc');
            if (!doc || !doc.value.trim()) { mdfeNotify('Informe o documento do contratante.'); if (doc) doc.focus(); return; }
            if (tipoPessoa === 'juridica' && !mdfeExigirCnpjObrigatorio(doc.value, 'CNPJ do contratante')) { doc.focus(); return; }
            if (tipoPessoa === 'fisica' && !mdfeExigirCpfObrigatorio(doc.value, 'CPF do contratante')) { doc.focus(); return; }

            var item = {
                tipoPessoa: tipoPessoa,
                documento: doc.value.trim(),
                razaoSocial: ((document.getElementById('rod_contratante_razao_social') || {}).value || '').trim(),
                numeroContrato: ((document.getElementById('rod_contratante_numero_contrato') || {}).value || '').trim(),
                valor: Number(((document.getElementById('rod_contratante_valor') || {}).value || 0))
            };
            if (rodContratanteEditIndex >= 0) rodContratantesNovoMdfe[rodContratanteEditIndex] = item;
            else rodContratantesNovoMdfe.push(item);
            renderContratantesNovoMDFE();
            limparFormContratanteNovoMDFE();
        }

        function editarContratanteNovoMDFE(index) {
            var item = rodContratantesNovoMdfe[index];
            if (!item) return;
            rodContratanteEditIndex = Number(index);
            var radio = document.getElementById('rod_contratante_tipo_' + item.tipoPessoa);
            if (radio) radio.checked = true;
            updateTipoPessoaContratanteNovoMDFE();
            document.getElementById('rod_contratante_doc').value = item.documento || '';
            document.getElementById('rod_contratante_razao_social').value = item.razaoSocial || '';
            document.getElementById('rod_contratante_numero_contrato').value = item.numeroContrato || '';
            document.getElementById('rod_contratante_valor').value = item.valor || '';
            toggleFormMdfeById('rodContratanteFormWrap', true);
        }

        function excluirContratanteNovoMDFE(index) {
            rodContratantesNovoMdfe = rodContratantesNovoMdfe.filter(function(_, i) { return i !== Number(index); });
            renderContratantesNovoMDFE();
        }

        function renderContratantesNovoMDFE() {
            var tbody = document.getElementById('rodContratantesTabelaBody');
            var hidden = document.getElementById('rod_contratantes_json');
            if (!tbody || !hidden) return;
            hidden.value = JSON.stringify(rodContratantesNovoMdfe);
            if (!rodContratantesNovoMdfe.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-muted">Nenhum contratante adicionado.</td></tr>';
                return;
            }
            tbody.innerHTML = rodContratantesNovoMdfe.map(function(item, idx) {
                return '<tr>' +
                    '<td>' + escapeHtmlMdfe(item.tipoPessoa) + '</td>' +
                    '<td>' + escapeHtmlMdfe(item.documento) + '</td>' +
                    '<td>' + escapeHtmlMdfe(item.razaoSocial || '-') + '</td>' +
                    '<td>' + escapeHtmlMdfe(item.numeroContrato || '-') + '</td>' +
                    '<td>' + (item.valor ? ('R$ ' + formatarValorMoedaMdfe(item.valor)) : '-') + '</td>' +
                    '<td><button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="editarContratanteNovoMDFE(' + idx + ')"><i class="fas fa-edit"></i></button>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger" onclick="excluirContratanteNovoMDFE(' + idx + ')"><i class="fas fa-trash"></i></button></td>' +
                    '</tr>';
            }).join('');
        }

        function mostrarFormPagamentoFreteNovoMDFE() {
            toggleFormMdfeById('rodPagamentoFreteFormWrap', true);
        }

        function limparFormPagamentoFreteNovoMDFE() {
            rodPagamentoFreteEditIndex = -1;
            rodPagamentoComponentesDraft = [];
            rodPagamentoCompPaginaAtual = 1;
            ['rod_pag_doc','rod_pag_razao_social','rod_pag_comp_tipo','rod_pag_comp_valor','rod_pag_valor_total_contrato','rod_pag_forma_financiamento','rod_pag_tipo_pagamento','rod_pag_indicador_status'].forEach(function(id){
                var el = document.getElementById(id);
                if (el) el.value = '';
            });
            var cbox = document.getElementById('rod_pag_considerar_componentes');
            if (cbox) cbox.checked = true;
            var tipoJ = document.getElementById('rod_pag_tipo_juridica');
            if (tipoJ) tipoJ.checked = true;
            var forma = document.getElementById('rod_pag_ind_forma_pagamento');
            if (forma) forma.value = '';
            var alto = document.getElementById('rod_pag_alto_desempenho');
            if (alto) alto.value = '';
            updateTipoPessoaPagamentoNovoMDFE();
            renderComponentesPagamentoNovoMDFE(1);
            toggleFormMdfeById('rodPagamentoFreteFormWrap', false);
        }

        function cancelarPagamentoFreteNovoMDFE() {
            limparFormPagamentoFreteNovoMDFE();
        }

        function adicionarComponentePagamentoNovoMDFE() {
            var tipo = document.getElementById('rod_pag_comp_tipo');
            var valor = document.getElementById('rod_pag_comp_valor');
            if (!tipo || !valor) return;
            if (!tipo.value) { mdfeNotify('Selecione o tipo do componente.'); tipo.focus(); return; }
            if (!valor.value || Number(valor.value) <= 0) { mdfeNotify('Informe um valor válido para o componente.'); valor.focus(); return; }
            var label = (tipo.options[tipo.selectedIndex] || {}).text || tipo.value;
            rodPagamentoComponentesDraft.push({ codigo: tipo.value, tipo: label, valor: Number(valor.value) });
            tipo.value = '';
            valor.value = '';
            renderComponentesPagamentoNovoMDFE(rodPagamentoCompPaginaAtual);
        }

        function removerComponentePagamentoNovoMDFE(index) {
            rodPagamentoComponentesDraft = rodPagamentoComponentesDraft.filter(function(_, i) { return i !== Number(index); });
            renderComponentesPagamentoNovoMDFE(rodPagamentoCompPaginaAtual);
        }

        function renderComponentesPagamentoNovoMDFE(pagina) {
            var tbody = document.getElementById('rodPagamentoCompTabelaBody');
            var info = document.getElementById('rodPagCompPageInfo');
            var perPageEl = document.getElementById('rod_pag_comp_per_page');
            if (!tbody || !info || !perPageEl) return;
            var perPage = Number(perPageEl.value || 5);
            var total = rodPagamentoComponentesDraft.length;
            var totalPages = Math.max(1, Math.ceil(total / perPage));
            rodPagamentoCompPaginaAtual = Math.min(Math.max(1, Number(pagina || 1)), totalPages);
            var inicio = (rodPagamentoCompPaginaAtual - 1) * perPage;
            var fim = inicio + perPage;
            var pageItems = rodPagamentoComponentesDraft.slice(inicio, fim);
            info.textContent = rodPagamentoCompPaginaAtual + ' de ' + totalPages;
            if (!pageItems.length) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-muted">Nenhum componente adicionado.</td></tr>';
                return;
            }
            tbody.innerHTML = pageItems.map(function(item, idx) {
                var realIdx = inicio + idx;
                return '<tr>' +
                    '<td>' + escapeHtmlMdfe(item.tipo) + '</td>' +
                    '<td>R$ ' + formatarValorMoedaMdfe(item.valor) + '</td>' +
                    '<td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removerComponentePagamentoNovoMDFE(' + realIdx + ')"><i class="fas fa-trash"></i></button></td>' +
                    '</tr>';
            }).join('');
        }

        function gravarPagamentoFreteNovoMDFE() {
            var tipoPessoa = getTipoPessoaSelecionadoMdfe('rod_pag_tipo_pessoa') || 'juridica';
            var doc = document.getElementById('rod_pag_doc');
            var valorTotal = document.getElementById('rod_pag_valor_total_contrato');
            if (!doc || !doc.value.trim()) { mdfeNotify('Informe o documento do pagador.'); if (doc) doc.focus(); return; }
            if (tipoPessoa === 'juridica' && !mdfeExigirCnpjObrigatorio(doc.value, 'CNPJ do pagador')) { doc.focus(); return; }
            if (tipoPessoa === 'fisica' && !mdfeExigirCpfObrigatorio(doc.value, 'CPF do pagador')) { doc.focus(); return; }
            if (!valorTotal || !valorTotal.value || Number(valorTotal.value) <= 0) { mdfeNotify('Valor total do contrato é obrigatório.'); if (valorTotal) valorTotal.focus(); return; }
            var temComponenteFrete = rodPagamentoComponentesDraft.some(function(c) { return String(c.codigo || '') === '04'; });
            if (!temComponenteFrete) {
                mdfeNotify('Inclua ao menos um componente do tipo 04 - Frete.');
                return;
            }

            var item = {
                tipoPessoa: tipoPessoa,
                documento: doc.value.trim(),
                razaoSocial: ((document.getElementById('rod_pag_razao_social') || {}).value || '').trim(),
                componentes: rodPagamentoComponentesDraft.slice(),
                considerarComponentes: !!((document.getElementById('rod_pag_considerar_componentes') || {}).checked),
                indicadorFormaPagamento: ((document.getElementById('rod_pag_ind_forma_pagamento') || {}).value || '').trim(),
                formaFinanciamento: ((document.getElementById('rod_pag_forma_financiamento') || {}).value || '').trim(),
                altoDesempenho: ((document.getElementById('rod_pag_alto_desempenho') || {}).value || '').trim(),
                tipoPagamento: ((document.getElementById('rod_pag_tipo_pagamento') || {}).value || '').trim(),
                indicadorStatusPagamento: ((document.getElementById('rod_pag_indicador_status') || {}).value || '').trim(),
                valorTotalContrato: Number(valorTotal.value || 0)
            };
            if (rodPagamentoFreteEditIndex >= 0) rodPagamentosFreteNovoMdfe[rodPagamentoFreteEditIndex] = item;
            else rodPagamentosFreteNovoMdfe.push(item);
            renderPagamentosFreteNovoMDFE();
            limparFormPagamentoFreteNovoMDFE();
        }

        function editarPagamentoFreteNovoMDFE(index) {
            var item = rodPagamentosFreteNovoMdfe[index];
            if (!item) return;
            rodPagamentoFreteEditIndex = Number(index);
            var radio = document.getElementById('rod_pag_tipo_' + item.tipoPessoa);
            if (radio) radio.checked = true;
            updateTipoPessoaPagamentoNovoMDFE();
            document.getElementById('rod_pag_doc').value = item.documento || '';
            document.getElementById('rod_pag_razao_social').value = item.razaoSocial || '';
            document.getElementById('rod_pag_valor_total_contrato').value = item.valorTotalContrato || '';
            document.getElementById('rod_pag_considerar_componentes').checked = !!item.considerarComponentes;
            document.getElementById('rod_pag_ind_forma_pagamento').value = item.indicadorFormaPagamento || '';
            document.getElementById('rod_pag_forma_financiamento').value = item.formaFinanciamento || '';
            document.getElementById('rod_pag_alto_desempenho').value = item.altoDesempenho || '';
            document.getElementById('rod_pag_tipo_pagamento').value = item.tipoPagamento || '';
            document.getElementById('rod_pag_indicador_status').value = item.indicadorStatusPagamento || '';
            rodPagamentoComponentesDraft = Array.isArray(item.componentes) ? item.componentes.slice() : [];
            renderComponentesPagamentoNovoMDFE(1);
            toggleFormMdfeById('rodPagamentoFreteFormWrap', true);
        }

        function excluirPagamentoFreteNovoMDFE(index) {
            rodPagamentosFreteNovoMdfe = rodPagamentosFreteNovoMdfe.filter(function(_, i) { return i !== Number(index); });
            renderPagamentosFreteNovoMDFE();
        }

        function renderPagamentosFreteNovoMDFE() {
            var tbody = document.getElementById('rodPagamentosFreteTabelaBody');
            var hidden = document.getElementById('rod_pagamentos_frete_json');
            if (!tbody || !hidden) return;
            hidden.value = JSON.stringify(rodPagamentosFreteNovoMdfe);
            if (!rodPagamentosFreteNovoMdfe.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-muted">Nenhum pagamento de frete adicionado.</td></tr>';
                return;
            }
            tbody.innerHTML = rodPagamentosFreteNovoMdfe.map(function(item, idx) {
                return '<tr>' +
                    '<td>' + escapeHtmlMdfe(item.razaoSocial || '-') + '</td>' +
                    '<td>' + escapeHtmlMdfe(item.documento || '-') + '</td>' +
                    '<td>' + String((item.componentes || []).length) + '</td>' +
                    '<td>R$ ' + formatarValorMoedaMdfe(item.valorTotalContrato) +
                    (item.indicadorStatusPagamento ? ('<br><small>' + escapeHtmlMdfe(item.indicadorStatusPagamento) + '</small>') : '') + '</td>' +
                    '<td><button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="editarPagamentoFreteNovoMDFE(' + idx + ')"><i class="fas fa-edit"></i></button>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger" onclick="excluirPagamentoFreteNovoMDFE(' + idx + ')"><i class="fas fa-trash"></i></button></td>' +
                    '</tr>';
            }).join('');
        }

        function buscarClientesFornecedorNovoMdfe(termoBusca) {
            var q = encodeURIComponent(termoBusca || '');
            var url = '../../api/fornecedores.php?action=list&limit=100&search=' + q;
            return fetch(url)
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d && d.success && Array.isArray(d.data)) return d.data;
                    if (Array.isArray(d)) return d;
                    return [];
                })
                .catch(function() { return []; });
        }

        function preencherContratanteComClienteNovoMdfe(item) {
            if (!item) return;
            var doc = item.cnpj || item.cpf || item.documento || '';
            var nome = item.razao_social || item.nome || item.nome_fantasia || '';
            var tipo = item.cnpj ? 'juridica' : (item.cpf ? 'fisica' : 'estrangeiro');
            var radio = document.getElementById('rod_contratante_tipo_' + tipo);
            if (radio) radio.checked = true;
            updateTipoPessoaContratanteNovoMDFE();
            var docEl = document.getElementById('rod_contratante_doc');
            var nomeEl = document.getElementById('rod_contratante_razao_social');
            if (docEl) docEl.value = doc;
            if (nomeEl) nomeEl.value = nome;
        }

        function preencherPagamentoComClienteNovoMdfe(item) {
            if (!item) return;
            var doc = item.cnpj || item.cpf || item.documento || '';
            var nome = item.razao_social || item.nome || item.nome_fantasia || '';
            var tipo = item.cnpj ? 'juridica' : (item.cpf ? 'fisica' : 'estrangeiro');
            var radio = document.getElementById('rod_pag_tipo_' + tipo);
            if (radio) radio.checked = true;
            updateTipoPessoaPagamentoNovoMDFE();
            var docEl = document.getElementById('rod_pag_doc');
            var nomeEl = document.getElementById('rod_pag_razao_social');
            if (docEl) docEl.value = doc;
            if (nomeEl) nomeEl.value = nome;
        }

        function pesquisarClienteContratanteNovoMDFE() {
            abrirModalBuscaFornecedorMdfe('Contratante', function(item) {
                preencherContratanteComClienteNovoMdfe(item);
            });
        }

        function pesquisarClientePagamentoNovoMDFE() {
            abrirModalBuscaFornecedorMdfe('Pagador do frete', function(item) {
                preencherPagamentoComClienteNovoMdfe(item);
            });
        }

        function copiarDadosEmitentePagamentoNovoMDFE() {
            var tipoEmitente = document.getElementById('novo_mdfe_tipo_emitente');
            var infoEmitente = document.getElementById('novo_mdfe_info_contribuinte');
            var razao = document.getElementById('rod_pag_razao_social');
            if (!razao) return;
            var sufixo = tipoEmitente && tipoEmitente.value ? ('Emitente tipo ' + tipoEmitente.value) : 'Emitente';
            razao.value = 'Emitente MDF-e (' + sufixo + ')';
            var docEl = document.getElementById('rod_pag_doc');
            if (docEl && !docEl.value) {
                var sugestao = somenteDigitosMdfe((infoEmitente && infoEmitente.value) || '').slice(0, 14);
                docEl.value = sugestao;
            }
        }

        function atualizarMunicipioDescargaDocumentosNovoMDFE() {
            var sel = document.getElementById('doc_municipio_descarregamento');
            var munDesc = document.getElementById('novo_mdfe_municipio_descarga');
            if (!sel) return;
            var atual = sel.value || '';
            var val = munDesc ? (munDesc.value || '') : '';
            var html = '<option value="">Selecione um município</option>';
            if (val) {
                html += '<option value="' + escapeHtmlMdfe(val) + '">' + escapeHtmlMdfe(val) + '</option>';
            }
            sel.innerHTML = html;
            if (val) sel.value = val;
            else if (atual) sel.value = atual;
        }

        function updateCteFieldsVisibilityDocumentosNovoMDFE() {
            var emitente = document.getElementById('novo_mdfe_tipo_emitente');
            var wrap = document.getElementById('docCteWrapNovoMDFE');
            if (!emitente || !wrap) return;
            var mostrar = emitente.value === '1' || emitente.value === '3';
            wrap.classList.toggle('is-hidden', !mostrar);
        }

        function mostrarFormDocumentoNovoMDFE(tipoAcao) {
            atualizarMunicipioDescargaDocumentosNovoMDFE();
            updateCteFieldsVisibilityDocumentosNovoMDFE();
            toggleFormMdfeById('docFormWrapNovoMDFE', true);
            var acao = document.getElementById('doc_tipo_acao');
            if (acao && tipoAcao) acao.value = tipoAcao;
        }

        function limparFormDocumentoNovoMDFE() {
            docEditIndexNovoMdfe = -1;
            ['doc_tipo_acao','doc_chave_nfe','doc_numero_nfe','doc_serie_nfe','doc_valor_nfe','doc_chave_cte','doc_numero_cte'].forEach(function(id) {
                var el = document.getElementById(id);
                if (!el) return;
                if (id === 'doc_tipo_acao') el.value = 'adicionar';
                else el.value = '';
            });
            atualizarMunicipioDescargaDocumentosNovoMDFE();
            updateCteFieldsVisibilityDocumentosNovoMDFE();
            toggleFormMdfeById('docFormWrapNovoMDFE', false);
        }

        function cancelarDocumentoNovoMDFE() {
            limparFormDocumentoNovoMDFE();
        }

        function gravarDocumentoNovoMDFE() {
            var municipio = document.getElementById('doc_municipio_descarregamento');
            var acao = document.getElementById('doc_tipo_acao');
            var chaveNfe = document.getElementById('doc_chave_nfe');
            if (!municipio || !acao || !chaveNfe) return;
            if (!municipio.value) { mdfeNotify('Selecione o município de descarregamento.'); municipio.focus(); return; }
            if (!chaveNfe.value.trim()) { mdfeNotify('Informe a chave da NF-e.'); chaveNfe.focus(); return; }
            var tipoEmitente = obterTipoEmitenteAtualNovoMDFE();
            var chaveCteEl = document.getElementById('doc_chave_cte');
            var chaveCte = ((chaveCteEl || {}).value || '').trim();
            if ((tipoEmitente === '1' || tipoEmitente === '3') && !chaveCte) {
                mdfeNotify('Para tipo de emitente 1 ou 3, o CT-e é obrigatório no documento.');
                if (chaveCteEl) chaveCteEl.focus();
                return;
            }
            if (tipoEmitente === '2' && chaveCte) {
                mdfeNotify('Tipo de emitente 2 (carga própria) não permite CT-e.');
                if (chaveCteEl) chaveCteEl.focus();
                return;
            }

            var item = {
                municipioDescarregamento: municipio.value,
                tipoAcao: acao.value || 'adicionar',
                chaveNfe: chaveNfe.value.trim(),
                numeroNfe: ((document.getElementById('doc_numero_nfe') || {}).value || '').trim(),
                serieNfe: ((document.getElementById('doc_serie_nfe') || {}).value || '').trim(),
                valorNfe: Number(((document.getElementById('doc_valor_nfe') || {}).value || 0)),
                chaveCte: chaveCte,
                numeroCte: ((document.getElementById('doc_numero_cte') || {}).value || '').trim()
            };

            if (docEditIndexNovoMdfe >= 0) docDocumentosNovoMdfe[docEditIndexNovoMdfe] = item;
            else docDocumentosNovoMdfe.push(item);

            renderDocumentosNovoMDFE();
            limparFormDocumentoNovoMDFE();
        }

        function editarDocumentoNovoMDFE(index) {
            var item = docDocumentosNovoMdfe[index];
            if (!item) return;
            docEditIndexNovoMdfe = Number(index);
            mostrarFormDocumentoNovoMDFE(item.tipoAcao || 'adicionar');
            document.getElementById('doc_municipio_descarregamento').value = item.municipioDescarregamento || '';
            document.getElementById('doc_chave_nfe').value = item.chaveNfe || '';
            document.getElementById('doc_numero_nfe').value = item.numeroNfe || '';
            document.getElementById('doc_serie_nfe').value = item.serieNfe || '';
            document.getElementById('doc_valor_nfe').value = item.valorNfe || '';
            document.getElementById('doc_chave_cte').value = item.chaveCte || '';
            document.getElementById('doc_numero_cte').value = item.numeroCte || '';
        }

        function excluirDocumentoNovoMDFE(index) {
            docDocumentosNovoMdfe = docDocumentosNovoMdfe.filter(function(_, i) { return i !== Number(index); });
            renderDocumentosNovoMDFE();
        }

        function renderDocumentosNovoMDFE() {
            var tbody = document.getElementById('docTabelaBodyNovoMDFE');
            var hidden = document.getElementById('doc_documentos_json');
            if (!tbody || !hidden) return;
            hidden.value = JSON.stringify(docDocumentosNovoMdfe);
            if (!docDocumentosNovoMdfe.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-muted">Nenhum documento fiscal adicionado.</td></tr>';
                return;
            }
            tbody.innerHTML = docDocumentosNovoMdfe.map(function(item, idx) {
                var nfeTexto = (item.numeroNfe ? ('Nº ' + item.numeroNfe + ' ') : '') + (item.chaveNfe || '-');
                var cteTexto = item.chaveCte ? ((item.numeroCte ? ('Nº ' + item.numeroCte + ' ') : '') + item.chaveCte) : '-';
                return '<tr>' +
                    '<td>' + escapeHtmlMdfe(item.municipioDescarregamento || '-') + '</td>' +
                    '<td>' + escapeHtmlMdfe(item.tipoAcao || '-') + '</td>' +
                    '<td>' + escapeHtmlMdfe(nfeTexto) + '</td>' +
                    '<td>' + (item.valorNfe ? ('R$ ' + formatarValorMoedaMdfe(item.valorNfe)) : '-') + '</td>' +
                    '<td>' + escapeHtmlMdfe(cteTexto) + '</td>' +
                    '<td><button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="editarDocumentoNovoMDFE(' + idx + ')"><i class="fas fa-edit"></i></button>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger" onclick="excluirDocumentoNovoMDFE(' + idx + ')"><i class="fas fa-trash"></i></button></td>' +
                    '</tr>';
            }).join('');
        }

        function mostrarFormSeguroNovoMDFE() {
            toggleFormMdfeById('seguroFormWrapNovoMDFE', true);
        }

        function pesquisarSeguroNovoMDFE() {
            mdfeNotify('Pesquisa de seguro pronta para integração. Você pode preencher e gravar manualmente.');
        }

        function limparFormSeguroNovoMDFE() {
            segEditIndexNovoMdfe = -1;
            segAverbacoesDraftNovoMdfe = [];
            ['seg_responsavel','seg_cpf_cnpj_responsavel','seg_emitente','seg_cnpj_seguradora','seg_nome_seguradora','seg_tomador_contratante','seg_numero_apolice','seg_numero_averbacao'].forEach(function(id){
                var el = document.getElementById(id);
                if (el) el.value = '';
            });
            renderAverbacoesSeguroNovoMDFE();
            toggleFormMdfeById('seguroFormWrapNovoMDFE', false);
        }

        function cancelarSeguroNovoMDFE() {
            limparFormSeguroNovoMDFE();
        }

        function adicionarAverbacaoSeguroNovoMDFE() {
            var input = document.getElementById('seg_numero_averbacao');
            if (!input) return;
            var numero = (input.value || '').trim();
            if (!numero) { mdfeNotify('Informe o número da averbação.'); input.focus(); return; }
            segAverbacoesDraftNovoMdfe.push(numero);
            input.value = '';
            renderAverbacoesSeguroNovoMDFE();
        }

        function removerAverbacaoSeguroNovoMDFE(index) {
            segAverbacoesDraftNovoMdfe = segAverbacoesDraftNovoMdfe.filter(function(_, i) { return i !== Number(index); });
            renderAverbacoesSeguroNovoMDFE();
        }

        function renderAverbacoesSeguroNovoMDFE() {
            var tbody = document.getElementById('segAverbacoesTabelaBodyNovoMDFE');
            if (!tbody) return;
            if (!segAverbacoesDraftNovoMdfe.length) {
                tbody.innerHTML = '<tr><td colspan="2" class="text-muted">Nenhuma averbação adicionada.</td></tr>';
                return;
            }
            tbody.innerHTML = segAverbacoesDraftNovoMdfe.map(function(av, idx) {
                return '<tr><td>' + escapeHtmlMdfe(av) + '</td>' +
                    '<td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removerAverbacaoSeguroNovoMDFE(' + idx + ')"><i class="fas fa-trash"></i></button></td></tr>';
            }).join('');
        }

        function gravarSeguroNovoMDFE() {
            var resp = document.getElementById('seg_responsavel');
            var seguradora = document.getElementById('seg_nome_seguradora');
            var cnpjSeg = document.getElementById('seg_cnpj_seguradora');
            var cpfResp = document.getElementById('seg_cpf_cnpj_responsavel');
            var apolice = document.getElementById('seg_numero_apolice');
            if (!resp || !seguradora || !cnpjSeg || !cpfResp || !apolice) return;
            if (!resp.value.trim()) { mdfeNotify('Informe o responsável pelo seguro.'); resp.focus(); return; }
            if (!cpfResp.value.trim()) { mdfeNotify('Informe CPF/CNPJ do responsável pelo seguro.'); cpfResp.focus(); return; }
            if (!mdfeExigirCpfCnpjObrigatorio(cpfResp.value, 'CPF/CNPJ do responsável pelo seguro')) { cpfResp.focus(); return; }
            if (!cnpjSeg.value.trim()) { mdfeNotify('Informe o CNPJ da seguradora.'); cnpjSeg.focus(); return; }
            if (!mdfeExigirCnpjObrigatorio(cnpjSeg.value, 'CNPJ da seguradora')) { cnpjSeg.focus(); return; }
            if (!seguradora.value.trim()) { mdfeNotify('Informe o nome da seguradora.'); seguradora.focus(); return; }
            if (!apolice.value.trim()) { mdfeNotify('Informe o número da apólice.'); apolice.focus(); return; }

            var item = {
                responsavel: resp.value.trim(),
                cpfCnpjResponsavel: ((document.getElementById('seg_cpf_cnpj_responsavel') || {}).value || '').trim(),
                emitente: ((document.getElementById('seg_emitente') || {}).value || '').trim(),
                cnpjSeguradora: ((document.getElementById('seg_cnpj_seguradora') || {}).value || '').trim(),
                nomeSeguradora: seguradora.value.trim(),
                tomadorContratante: ((document.getElementById('seg_tomador_contratante') || {}).value || '').trim(),
                numeroApolice: ((document.getElementById('seg_numero_apolice') || {}).value || '').trim(),
                averbacoes: segAverbacoesDraftNovoMdfe.slice()
            };
            if (segEditIndexNovoMdfe >= 0) segSegurosNovoMdfe[segEditIndexNovoMdfe] = item;
            else segSegurosNovoMdfe.push(item);
            renderSegurosNovoMDFE();
            limparFormSeguroNovoMDFE();
        }

        function editarSeguroNovoMDFE(index) {
            var item = segSegurosNovoMdfe[index];
            if (!item) return;
            segEditIndexNovoMdfe = Number(index);
            document.getElementById('seg_responsavel').value = item.responsavel || '';
            document.getElementById('seg_cpf_cnpj_responsavel').value = item.cpfCnpjResponsavel || '';
            document.getElementById('seg_emitente').value = item.emitente || '';
            document.getElementById('seg_cnpj_seguradora').value = item.cnpjSeguradora || '';
            document.getElementById('seg_nome_seguradora').value = item.nomeSeguradora || '';
            document.getElementById('seg_tomador_contratante').value = item.tomadorContratante || '';
            document.getElementById('seg_numero_apolice').value = item.numeroApolice || '';
            segAverbacoesDraftNovoMdfe = Array.isArray(item.averbacoes) ? item.averbacoes.slice() : [];
            renderAverbacoesSeguroNovoMDFE();
            toggleFormMdfeById('seguroFormWrapNovoMDFE', true);
        }

        function excluirSeguroNovoMDFE(index) {
            segSegurosNovoMdfe = segSegurosNovoMdfe.filter(function(_, i) { return i !== Number(index); });
            renderSegurosNovoMDFE();
        }

        function renderSegurosNovoMDFE() {
            var tbody = document.getElementById('segTabelaBodyNovoMDFE');
            var hidden = document.getElementById('seg_seguros_json');
            if (!tbody || !hidden) return;
            hidden.value = JSON.stringify(segSegurosNovoMdfe);
            if (!segSegurosNovoMdfe.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-muted">Nenhum seguro adicionado.</td></tr>';
                return;
            }
            tbody.innerHTML = segSegurosNovoMdfe.map(function(item, idx) {
                return '<tr>' +
                    '<td>' + escapeHtmlMdfe(item.responsavel || '-') + '</td>' +
                    '<td>' + escapeHtmlMdfe(item.nomeSeguradora || '-') + '</td>' +
                    '<td>' + escapeHtmlMdfe(item.numeroApolice || '-') + '</td>' +
                    '<td>' + String((item.averbacoes || []).length) + '</td>' +
                    '<td><button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="editarSeguroNovoMDFE(' + idx + ')"><i class="fas fa-edit"></i></button>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger" onclick="excluirSeguroNovoMDFE(' + idx + ')"><i class="fas fa-trash"></i></button></td>' +
                    '</tr>';
            }).join('');
        }

        function mostrarFormProdutoPredNovoMDFE() {
            toggleFormMdfeById('prodFormWrapNovoMDFE', true);
            updateCamposCargaLotacaoProdutoNovoMDFE();
        }

        function limparFormProdutoPredNovoMDFE() {
            prodEditIndexNovoMdfe = -1;
            ['prod_tipo_carga','prod_descricao','prod_gtin','prod_ncm','prod_carga_lotacao','prod_local_carregamento_cep','prod_local_descarregamento_cep','prod_cep_descarregamento'].forEach(function(id){
                var el = document.getElementById(id);
                if (el) el.value = '';
            });
            updateCamposCargaLotacaoProdutoNovoMDFE();
            toggleFormMdfeById('prodFormWrapNovoMDFE', false);
        }

        function cancelarProdutoPredNovoMDFE() {
            limparFormProdutoPredNovoMDFE();
        }

        function gravarProdutoPredNovoMDFE() {
            var tipo = document.getElementById('prod_tipo_carga');
            var desc = document.getElementById('prod_descricao');
            var ncm = document.getElementById('prod_ncm');
            if (!tipo || !desc || !ncm) return;
            if (!tipo.value.trim()) { mdfeNotify('Informe o tipo de carga.'); tipo.focus(); return; }
            if (!desc.value.trim()) { mdfeNotify('Informe a descrição do produto.'); desc.focus(); return; }

            var item = {
                tipoCarga: tipo.value.trim(),
                descricao: desc.value.trim(),
                gtin: ((document.getElementById('prod_gtin') || {}).value || '').trim(),
                ncm: ncm.value.trim(),
                cargaLotacao: ((document.getElementById('prod_carga_lotacao') || {}).value || '').trim(),
                cepCarregamento: ((document.getElementById('prod_local_carregamento_cep') || {}).value || '').trim(),
                cepDescarregamento: ((document.getElementById('prod_local_descarregamento_cep') || {}).value || '').trim(),
                cepDescarregamentoExtra: ((document.getElementById('prod_cep_descarregamento') || {}).value || '').trim()
            };
            if (item.cargaLotacao !== 'sim') {
                item.cepCarregamento = '';
                item.cepDescarregamento = '';
                item.cepDescarregamentoExtra = '';
            }

            if (prodEditIndexNovoMdfe >= 0) prodPredominantesNovoMdfe[prodEditIndexNovoMdfe] = item;
            else prodPredominantesNovoMdfe.push(item);

            renderProdutosPredNovoMDFE();
            limparFormProdutoPredNovoMDFE();
        }

        function editarProdutoPredNovoMDFE(index) {
            var item = prodPredominantesNovoMdfe[index];
            if (!item) return;
            prodEditIndexNovoMdfe = Number(index);
            document.getElementById('prod_tipo_carga').value = item.tipoCarga || '';
            document.getElementById('prod_descricao').value = item.descricao || '';
            document.getElementById('prod_gtin').value = item.gtin || '';
            document.getElementById('prod_ncm').value = item.ncm || '';
            document.getElementById('prod_carga_lotacao').value = item.cargaLotacao || '';
            document.getElementById('prod_local_carregamento_cep').value = item.cepCarregamento || '';
            document.getElementById('prod_local_descarregamento_cep').value = item.cepDescarregamento || '';
            document.getElementById('prod_cep_descarregamento').value = item.cepDescarregamentoExtra || '';
            toggleFormMdfeById('prodFormWrapNovoMDFE', true);
            updateCamposCargaLotacaoProdutoNovoMDFE();
        }

        function excluirProdutoPredNovoMDFE(index) {
            prodPredominantesNovoMdfe = prodPredominantesNovoMdfe.filter(function(_, i) { return i !== Number(index); });
            renderProdutosPredNovoMDFE();
        }

        function renderProdutosPredNovoMDFE() {
            var tbody = document.getElementById('prodTabelaBodyNovoMDFE');
            var hidden = document.getElementById('prod_predominantes_json');
            if (!tbody || !hidden) return;
            hidden.value = JSON.stringify(prodPredominantesNovoMdfe);
            if (!prodPredominantesNovoMdfe.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-muted">Nenhum produto predominante adicionado.</td></tr>';
                return;
            }
            tbody.innerHTML = prodPredominantesNovoMdfe.map(function(item, idx) {
                return '<tr>' +
                    '<td>' + escapeHtmlMdfe(item.tipoCarga || '-') + '</td>' +
                    '<td>' + escapeHtmlMdfe(item.descricao || '-') + '</td>' +
                    '<td>' + escapeHtmlMdfe(item.ncm || '-') + '</td>' +
                    '<td>' + escapeHtmlMdfe(item.cargaLotacao || '-') + '</td>' +
                    '<td>' + escapeHtmlMdfe(item.cepCarregamento || '-') + '</td>' +
                    '<td>' + escapeHtmlMdfe(item.cepDescarregamento || '-') + '</td>' +
                    '<td>' + escapeHtmlMdfe(item.cepDescarregamentoExtra || '-') + '</td>' +
                    '<td><button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="editarProdutoPredNovoMDFE(' + idx + ')"><i class="fas fa-edit"></i></button>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger" onclick="excluirProdutoPredNovoMDFE(' + idx + ')"><i class="fas fa-trash"></i></button></td>' +
                    '</tr>';
            }).join('');
        }

        function updateCamposCargaLotacaoProdutoNovoMDFE() {
            var carga = document.getElementById('prod_carga_lotacao');
            var wrap = document.getElementById('prodLocalizacoesLotacaoWrap');
            if (!carga || !wrap) return;
            var mostrar = carga.value === 'sim';
            wrap.classList.toggle('is-hidden', !mostrar);
            if (!mostrar) {
                ['prod_local_carregamento_cep','prod_local_descarregamento_cep','prod_cep_descarregamento'].forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) el.value = '';
                });
            }
        }

        function setTotalizadoresTabNovoMDFE(tab) {
            var botoes = document.querySelectorAll('#novoMDFEWizardModal [data-mdfe-total-tab]');
            botoes.forEach(function(btn) {
                btn.classList.toggle('is-active', btn.getAttribute('data-mdfe-total-tab') === String(tab));
            });
            var panes = document.querySelectorAll('#novoMDFEWizardModal .route-modal-tab-pane[data-route-tab="6"] .mdfe-subtab-pane');
            panes.forEach(function(pane) {
                pane.classList.toggle('is-active', pane.getAttribute('data-mdfe-total-tab') === String(tab));
            });
        }

        function limparTotalizadoresNovoMDFE() {
            ['tot_total_nfe','tot_valor_total_carga','tot_unidade_medida_carga','tot_peso_total','tot_numero_lacre','tot_autorizado_doc','tot_autorizado_motorista'].forEach(function(id){
                var el = document.getElementById(id);
                if (el) el.value = '';
            });
            setTotalizadoresTabNovoMDFE('1');
        }

        function renderTotalizadoresNovoMDFE() {
            renderLacresTotalizadoresNovoMDFE();
            renderAutorizadosDownloadNovoMDFE();
            atualizarJsonTotalizadoresNovoMDFE();
        }

        function atualizarJsonTotalizadoresNovoMDFE() {
            var hidden = document.getElementById('tot_totalizadores_json');
            if (!hidden) return;
            hidden.value = JSON.stringify({
                totalNfeInformadas: Number((document.getElementById('tot_total_nfe') || {}).value || 0),
                valorTotalCarga: Number((document.getElementById('tot_valor_total_carga') || {}).value || 0),
                unidadeMedidaCarga: ((document.getElementById('tot_unidade_medida_carga') || {}).value || ''),
                pesoTotal: Number((document.getElementById('tot_peso_total') || {}).value || 0),
                lacres: totLacresNovoMdfe,
                autorizadosDownload: totAutorizadosNovoMdfe
            });
        }

        function adicionarLacreTotalizadoresNovoMDFE() {
            var input = document.getElementById('tot_numero_lacre');
            if (!input) return;
            var numero = (input.value || '').trim();
            if (!numero) { mdfeNotify('Informe o número do lacre.'); input.focus(); return; }
            totLacresNovoMdfe.push(numero);
            input.value = '';
            renderLacresTotalizadoresNovoMDFE();
            atualizarJsonTotalizadoresNovoMDFE();
        }

        function removerLacreTotalizadoresNovoMDFE(index) {
            totLacresNovoMdfe = totLacresNovoMdfe.filter(function(_, i) { return i !== Number(index); });
            renderLacresTotalizadoresNovoMDFE();
            atualizarJsonTotalizadoresNovoMDFE();
        }

        function renderLacresTotalizadoresNovoMDFE() {
            var tbody = document.getElementById('totLacresTabelaBodyNovoMDFE');
            if (!tbody) return;
            if (!totLacresNovoMdfe.length) {
                tbody.innerHTML = '<tr><td colspan="2" class="text-muted">Nenhum lacre adicionado.</td></tr>';
                return;
            }
            tbody.innerHTML = totLacresNovoMdfe.map(function(numero, idx) {
                return '<tr><td>' + escapeHtmlMdfe(numero) + '</td>' +
                    '<td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removerLacreTotalizadoresNovoMDFE(' + idx + ')"><i class="fas fa-trash"></i></button></td></tr>';
            }).join('');
        }

        function adicionarAutorizadoDownloadNovoMDFE() {
            var doc = document.getElementById('tot_autorizado_doc');
            var mot = document.getElementById('tot_autorizado_motorista');
            if (!doc || !mot) return;
            if (!doc.value.trim()) { mdfeNotify('Informe CPF/CNPJ do autorizado.'); doc.focus(); return; }
            if (!mdfeExigirCpfCnpjObrigatorio(doc.value, 'CPF/CNPJ do autorizado')) { doc.focus(); return; }
            totAutorizadosNovoMdfe.push({
                documento: doc.value.trim(),
                motorista: (mot.value || '').trim()
            });
            doc.value = '';
            mot.value = '';
            renderAutorizadosDownloadNovoMDFE();
            atualizarJsonTotalizadoresNovoMDFE();
        }

        function removerAutorizadoDownloadNovoMDFE(index) {
            totAutorizadosNovoMdfe = totAutorizadosNovoMdfe.filter(function(_, i) { return i !== Number(index); });
            renderAutorizadosDownloadNovoMDFE();
            atualizarJsonTotalizadoresNovoMDFE();
        }

        function renderAutorizadosDownloadNovoMDFE() {
            var tbody = document.getElementById('totAutorizadosTabelaBodyNovoMDFE');
            if (!tbody) return;
            if (!totAutorizadosNovoMdfe.length) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-muted">Nenhum autorizado cadastrado.</td></tr>';
                return;
            }
            tbody.innerHTML = totAutorizadosNovoMdfe.map(function(item, idx) {
                return '<tr><td>' + escapeHtmlMdfe(item.documento || '-') + '</td>' +
                    '<td>' + escapeHtmlMdfe(item.motorista || '-') + '</td>' +
                    '<td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removerAutorizadoDownloadNovoMDFE(' + idx + ')"><i class="fas fa-trash"></i></button></td></tr>';
            }).join('');
        }

        function registrarLogValidacaoNovoMDFE(resultado) {
            try {
                var chave = 'mdfe_validacoes_log';
                var atual = [];
                try { atual = JSON.parse(localStorage.getItem(chave) || '[]'); } catch (e) { atual = []; }
                atual.push({
                    data: new Date().toISOString(),
                    erros: resultado.erros || [],
                    warnings: resultado.warnings || []
                });
                if (atual.length > 50) atual = atual.slice(atual.length - 50);
                localStorage.setItem(chave, JSON.stringify(atual));
            } catch (e) {}
        }

        function validarRegrasFiscaisNovoMDFE() {
            var tipoEmitente = obterTipoEmitenteAtualNovoMDFE();
            var tipoTransportador = obterTipoTransportadorAtualNovoMDFE();
            var temDocumentoNfe = docDocumentosNovoMdfe.length > 0;
            var temDocumentoComCte = docDocumentosNovoMdfe.some(function(d) { return !!(d.chaveCte || '').trim(); });
            var totalCte = docDocumentosNovoMdfe.filter(function(d) { return !!(d.chaveCte || '').trim(); }).length;
            var temPagamento = rodPagamentosFreteNovoMdfe.length > 0;
            var temContratante = rodContratantesNovoMdfe.length > 0;
            var temValePedagio = rodValesPedagioNovoMdfe.length > 0;
            var temCiot = rodCiotNovoMdfe.length > 0;
            var rntrc = ((document.getElementById('rod_rntrc') || {}).value || '').trim();
            var placa = ((document.getElementById('rod_placa') || {}).value || '').trim();
            var tipoRodado = ((document.getElementById('rod_tipo_rodado') || {}).value || '').trim();
            var tipoCarroceria = ((document.getElementById('rod_tipo_carroceria') || {}).value || '').trim();
            var pesoTotal = Number(((document.getElementById('tot_peso_total') || {}).value || 0));
            var produtoComNcm = prodPredominantesNovoMdfe.some(function(p) { return !!(p.ncm || '').trim(); });
            var temCargaLotacao = prodPredominantesNovoMdfe.some(function(p) { return (p.cargaLotacao || '') === 'sim'; });
            var documentosSemMunicipio = docDocumentosNovoMdfe.filter(function(d) { return !String(d.municipioDescarregamento || '').trim(); }).length;
            var pagamentosSemFreteComponente = rodPagamentosFreteNovoMdfe.filter(function(pg) {
                var comps = Array.isArray(pg.componentes) ? pg.componentes : [];
                return !comps.some(function(c) { return String(c.codigo || '') === '04'; });
            }).length;
            var erros = [];
            var warnings = [];

            if (!placa) erros.push('Placa do veículo obrigatória.');
            if (!tipoRodado) erros.push('Tipo de rodado obrigatório.');
            if (!tipoCarroceria) erros.push('Tipo de carroceria obrigatório.');
            if (!(pesoTotal > 0)) erros.push('Peso total inválido (deve ser maior que zero).');
            if (documentosSemMunicipio > 0) erros.push('Existem documentos sem município de descarregamento vinculado.');

            if (tipoEmitente === '1') {
                if (!temDocumentoComCte || totalCte === 0) erros.push('Tipo emitente 1 exige pelo menos 1 CT-e vinculado.');
                if (!temPagamento) erros.push('Tipo emitente 1 exige pagamento de frete.');
                if (!temContratante) erros.push('Tipo emitente 1 exige contratante.');
                if (!rntrc) erros.push('Tipo emitente 1 exige RNTRC.');
                if (!temValePedagio) warnings.push('Tipo emitente 1 normalmente exige vale pedágio em rota com pedágio.');
            }

            if (tipoEmitente === '2') {
                if (!temDocumentoNfe) erros.push('Tipo emitente 2 exige pelo menos 1 NF-e.');
                if (temDocumentoComCte) erros.push('Tipo emitente 2 não permite CT-e.');
                if (temPagamento) erros.push('Tipo emitente 2 não permite pagamento de frete.');
                if (temContratante) erros.push('Tipo emitente 2 não permite contratante.');
                if (temCiot) erros.push('Tipo emitente 2 não permite CIOT.');
                if (temValePedagio) erros.push('Tipo emitente 2 não permite vale pedágio.');
                if (!rntrc) warnings.push('RNTRC é opcional para tipo emitente 2.');
            }

            if (tipoEmitente === '3') {
                if (!temDocumentoComCte || totalCte === 0) erros.push('Tipo emitente 3 exige CT-e (globalizado).');
                if (!temDocumentoNfe) erros.push('Tipo emitente 3 exige NF-e vinculada.');
                if (!temPagamento) erros.push('Tipo emitente 3 exige pagamento de frete.');
                if (!temContratante) erros.push('Tipo emitente 3 exige contratante.');
                if (!rntrc) erros.push('Tipo emitente 3 exige RNTRC.');
                if (!temValePedagio) warnings.push('Tipo emitente 3 normalmente exige vale pedágio em rota com pedágio.');
            }

            if (tipoTransportador === '2' && tipoEmitente !== '2' && temPagamento && !temCiot) {
                erros.push('CIOT é obrigatório para TAC quando houver pagamento de frete.');
            }

            if (temPagamento && pagamentosSemFreteComponente > 0) {
                erros.push('Cada pagamento de frete deve conter ao menos 1 componente do tipo 04 - Frete.');
            }

            // Regra 2025: carga lotação com pagamento -> exigir NCM no produto predominante.
            if (temCargaLotacao && temPagamento && !produtoComNcm) {
                erros.push('Para carga lotação com pagamento de frete, informe o NCM no produto predominante.');
            }

            return { erros: erros, warnings: warnings };
        }

        function irParaAbaRodoviarioNovoMDFE() {
            var form = document.getElementById('novoMDFEWizardForm');
            if (!form) return;

            var obrigatorios = [
                'novo_mdfe_data_emissao',
                'novo_mdfe_tipo_emitente',
                'novo_mdfe_tipo_transportador',
                'novo_mdfe_uf_carga',
                'novo_mdfe_municipio_carga',
                'novo_mdfe_uf_descarga',
                'novo_mdfe_municipio_descarga'
            ];
            for (var i = 0; i < obrigatorios.length; i++) {
                var el = document.getElementById(obrigatorios[i]);
                if (el && !el.value) {
                    mdfeNotify('Preencha os campos obrigatórios da aba "Dados do MDF-e" antes de avançar.');
                    el.focus();
                    return;
                }
            }

            setNovoMdfeTab('2');
        }

        function avancarNovoMdfeWizard() {
            var ativa = document.querySelector('#novoMDFEWizardModal .route-tab-btn.is-active');
            var tabAtual = ativa ? Number(ativa.getAttribute('data-route-tab') || '1') : 1;
            if (tabAtual === 1) {
                irParaAbaRodoviarioNovoMDFE();
                return;
            }
            if (tabAtual < 6) {
                setNovoMdfeTab(String(tabAtual + 1));
                return;
            }
            var resultado = validarRegrasFiscaisNovoMDFE();
            registrarLogValidacaoNovoMDFE(resultado);
            if (resultado.erros && resultado.erros.length) {
                mdfeNotify('Validação fiscal (erros): ' + resultado.erros.join('; '), 'error');
                return;
            }
            if (resultado.warnings && resultado.warnings.length) {
                mdfeConfirmAsync('Avisos de validação', resultado.warnings.join('\n') + '\n\nDeseja continuar mesmo assim?').then(function(cont) {
                    if (!cont) return;
                    mdfeNotify('Validação fiscal concluída com sucesso. Regras por tipo de emitente atendidas.', 'success');
                });
                return;
            }
            mdfeNotify('Validação fiscal concluída com sucesso. Regras por tipo de emitente atendidas.', 'success');
        }

        function abrirModalCriarMDFE() {
            document.getElementById('cidadeCarregamento').innerHTML = '<option value="">Selecione primeiro o estado</option>';
            document.getElementById('cidadeCarregamento').disabled = true;
            document.getElementById('cidadeDescarregamento').innerHTML = '<option value="">Selecione primeiro o estado</option>';
            document.getElementById('cidadeDescarregamento').disabled = true;
            carregarCTEAutorizados().then(function() {
                new bootstrap.Modal(document.getElementById('criarMDFEModal')).show();
            });
        }

        function carregarVeiculosMotoristas() {
            var v = document.getElementById('veiculoMDFE');
            var m = document.getElementById('motoristaMDFE');
            if (!v || !m) return;
            // Usar mesma API das rotas (get_veiculos) para formato consistente { success, data }
            fetch('../../api/route_actions.php?action=get_veiculos')
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    var list = (d && d.success && d.data) ? d.data : (Array.isArray(d) ? d : []);
                    if (list.length) {
                        list.forEach(function(o) {
                            var opt = document.createElement('option');
                            opt.value = o.id;
                            opt.textContent = (o.placa || 'Veículo') + (o.modelo ? ' - ' + o.modelo : '') + (o.marca ? ' ' + o.marca : '');
                            v.appendChild(opt);
                        });
                    } else {
                        v.innerHTML = '<option value="">Nenhum veículo cadastrado</option>';
                    }
                })
                .catch(function(err) {
                    if (v) v.innerHTML = '<option value="">Erro ao carregar veículos</option>';
                });
            fetch('../../api/motoristas.php?action=list&limit=500')
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    var list = (d && d.success && d.data) ? d.data : [];
                    if (list.length) {
                        list.forEach(function(o) {
                            var opt = document.createElement('option');
                            opt.value = o.id;
                            opt.textContent = (o.nome || 'Motorista') + (o.cpf ? ' - ' + o.cpf : '');
                            m.appendChild(opt);
                        });
                    } else {
                        m.innerHTML = '<option value="">Nenhum motorista cadastrado</option>';
                    }
                })
                .catch(function() {
                    if (m) m.innerHTML = '<option value="">Erro ao carregar motoristas</option>';
                });
        }

        function carregarCTEAutorizados() {
            return mdfeApiFetch('../api/documentos_fiscais_v2.php?action=list&tipo=cte&status=autorizado&limit=100')
                .then(function(res) {
                    var data = res.data || {};
                    var sel = document.getElementById('cteSelector');
                    if (!data.success || !data.documentos || data.documentos.length === 0) {
                        sel.innerHTML = '<p class="text-muted mb-0">Nenhum CT-e autorizado. Consulte e autorize CT-e em <a href="cte.php">CT-e</a> primeiro.</p>';
                        return;
                    }
                    var html = '';
                    data.documentos.forEach(function(cte) {
                        var id = cte.id != null && cte.id !== '' ? Number(cte.id) : (cte.cte_id != null ? Number(cte.cte_id) : null);
                        if (id == null || isNaN(id) || id <= 0) return;
                        var dataF = cte.data_emissao ? new Date(cte.data_emissao).toLocaleDateString('pt-BR') : '-';
                        var valor = parseFloat(cte.valor_total || 0).toFixed(2).replace('.', ',');
                        var origem = (cte.origem_cidade || cte.origem || 'N/A');
                        var destino = (cte.destino_cidade || cte.destino || 'N/A');
                        html += '<div class="cte-item" onclick="toggleCTE(this)">';
                        html += '<input type="checkbox" name="cte_ids[]" value="' + id + '" style="margin-right:8px" onclick="event.stopPropagation();" onchange="atualizarTotaisMDFE();"';
                        html += ' data-numero="' + (cte.numero_cte||'') + '" data-valor="' + (cte.valor_total||0) + '"';
                        html += ' data-peso="' + (cte.peso_total||cte.peso_carga||0) + '" data-volumes="' + (cte.volumes_carga||0) + '"';
                        html += ' data-origem-uf="' + (cte.origem_estado||'') + '" data-origem-cidade="' + (cte.origem_cidade||'') + '"';
                        html += ' data-destino-uf="' + (cte.destino_estado||'') + '" data-destino-cidade="' + (cte.destino_cidade||'') + '">';
                        html += '<strong>CT-e ' + String(cte.numero_cte||'').padStart(6,'0') + '</strong> ' + dataF + ' | ' + origem + ' → ' + destino + ' | R$ ' + valor;
                        html += '</div>';
                    });
                    sel.innerHTML = html;
                })
                .catch(function() {
                    document.getElementById('cteSelector').innerHTML = '<p class="text-danger">Erro ao carregar CT-e.</p>';
                });
        }

        function toggleCTE(el) {
            var cb = el.querySelector('input[type="checkbox"]');
            cb.checked = !cb.checked;
            el.classList.toggle('selected', cb.checked);
            atualizarTotaisMDFE();
        }

        function atualizarTotaisMDFE() {
            var cbs = document.querySelectorAll('#criarMDFEForm input[name="cte_ids[]"]:checked');
            var peso = 0, volumes = 0, valor = 0;
            var origemUfs = [];
            var origemCidades = [];
            var destinoUfs = [];
            var destinoCidades = [];
            cbs.forEach(function(cb) {
                peso += parseFloat(cb.getAttribute('data-peso') || 0);
                volumes += parseInt(cb.getAttribute('data-volumes') || 0, 10);
                valor += parseFloat(cb.getAttribute('data-valor') || 0);
                var ouf = String(cb.getAttribute('data-origem-uf') || '').trim().toUpperCase();
                var ocid = String(cb.getAttribute('data-origem-cidade') || '').trim();
                var duf = String(cb.getAttribute('data-destino-uf') || '').trim().toUpperCase();
                var dcid = String(cb.getAttribute('data-destino-cidade') || '').trim();
                if (ouf && origemUfs.indexOf(ouf) === -1) origemUfs.push(ouf);
                if (ocid && origemCidades.indexOf(ocid) === -1) origemCidades.push(ocid);
                if (duf && destinoUfs.indexOf(duf) === -1) destinoUfs.push(duf);
                if (dcid && destinoCidades.indexOf(dcid) === -1) destinoCidades.push(dcid);
            });
            document.getElementById('totalPesoMDFE').value = peso.toFixed(2);
            document.getElementById('totalVolumesMDFE').value = volumes;
            document.getElementById('totalCTe').value = cbs.length;
            document.getElementById('valorTotalMDFE').value = valor.toFixed(2);

            // Preenche automaticamente origem/destino quando todos os CT-e selecionados convergem.
            if (origemUfs.length === 1) {
                var ufInicio = document.getElementById('ufInicio');
                if (ufInicio) {
                    ufInicio.value = origemUfs[0];
                    ufInicio.dispatchEvent(new Event('change'));
                    if (origemCidades.length === 1) {
                        setTimeout(function() {
                            var cidadeC = document.getElementById('cidadeCarregamento');
                            if (cidadeC) cidadeC.value = origemCidades[0];
                        }, 250);
                    }
                }
            }
            if (destinoUfs.length === 1) {
                var ufFim = document.getElementById('ufFim');
                if (ufFim) {
                    ufFim.value = destinoUfs[0];
                    ufFim.dispatchEvent(new Event('change'));
                    if (destinoCidades.length === 1) {
                        setTimeout(function() {
                            var cidadeD = document.getElementById('cidadeDescarregamento');
                            if (cidadeD) cidadeD.value = destinoCidades[0];
                        }, 250);
                    }
                }
            }
        }

        function salvarMDFE() {
            var form = document.getElementById('criarMDFEForm');
            if (!form.checkValidity()) { form.reportValidity(); return; }
            var cbs = document.querySelectorAll('input[name="cte_ids[]"]:checked');
            if (cbs.length === 0) {
                mdfeNotify('Selecione pelo menos um CT-e.');
                return;
            }
            var fd = new FormData(form);
            fd.append('action', 'criar_mdfe');
            cbs.forEach(function(cb) { fd.append('cte_ids[]', cb.value); });
            var btn = document.getElementById('btnSalvarMDFE');
            var orig = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Criando...';
            btn.disabled = true;
            mdfeApiFetch('../api/documentos_fiscais_v2.php', { method: 'POST', body: fd })
                .then(function(res) {
                    var data = res.data || {};
                    if (data.success) {
                        mdfeNotify('MDF-e criado: #' + data.numero_mdfe + '. Status: ' + data.status, 'success');
                        bootstrap.Modal.getInstance(document.getElementById('criarMDFEModal')).hide();
                        form.reset();
                        carregarMDFE();
                    } else {
                        mdfeNotify(mdfeApiErrorMessage(data, res.status), 'error');
                    }
                })
                .catch(function() { mdfeNotify('Erro ao criar MDF-e. Verifique a conexão.', 'error'); })
                .finally(function() { btn.innerHTML = orig; btn.disabled = false; });
        }

        function carregarMDFE() {
            var el = document.getElementById('mdfeList');
            el.innerHTML = '<div style="color:#17a2b8;background:#d1ecf1;padding:15px;border-radius:5px;"><strong>Carregando MDF-e...</strong></div>';
            mdfeApiFetch('../api/documentos_fiscais_v2.php?action=list&tipo=mdfe&limit=100')
                .then(function(res) {
                    var data = res.data || {};
                    if (data.success && data.documentos && data.documentos.length > 0) {
                        mdfeDocsCache = data.documentos || [];
                        aplicarFiltrosMDFE();
                    } else {
                        mdfeDocsCache = [];
                        el.innerHTML = '<div style="color:#6c757d;background:#f8f9fa;padding:15px;border-radius:5px;"><strong>Nenhum MDF-e</strong><br>Clique em "Criar MDF-e" e selecione CT-e autorizados para gerar o manifesto.</div>';
                        atualizarPaginacaoMdfe(0);
                    }
                })
                .catch(function(err) {
                    console.error(err);
                    mdfeDocsCache = [];
                    el.innerHTML = '<div style="color:#dc3545;background:#f8d7da;padding:15px;border-radius:5px;"><strong>Erro ao carregar dados.</strong></div>';
                    atualizarPaginacaoMdfe(0);
                });
        }

        function filtrarMdfeDocs(docs) {
            var search = (document.getElementById('searchMdfe') || {}).value || '';
            var status = (document.getElementById('statusMdfeFilter') || {}).value || '';
            var term = String(search).trim().toLowerCase();
            return (docs || []).filter(function(doc) {
                if (status && String(doc.status || '').toLowerCase() !== status.toLowerCase()) return false;
                if (!term) return true;
                var base = [
                    doc.numero_mdfe, doc.serie_mdfe, doc.chave_acesso, doc.uf_inicio, doc.uf_fim, doc.origem_documental, doc.status
                ].join(' ').toLowerCase();
                return base.indexOf(term) !== -1;
            });
        }

        function aplicarFiltrosMDFE() {
            var filtrados = filtrarMdfeDocs(mdfeDocsCache);
            renderizarTabelaMdfe(ordenarMdfeDocs(filtrados));
        }

        function renderizarTabelaMdfe(docsFiltrados) {
            var el = document.getElementById('mdfeList');
            if (!docsFiltrados.length) {
                el.innerHTML = '<div style="color:#6c757d;background:#f8f9fa;padding:15px;border-radius:5px;"><strong>Nenhum MDF-e encontrado</strong><br>Ajuste os filtros para continuar.</div>';
                atualizarPaginacaoMdfe(0);
                return;
            }

            var totalPaginas = Math.max(1, Math.ceil(docsFiltrados.length / mdfePerPage));
            if (mdfePaginaAtual > totalPaginas) mdfePaginaAtual = totalPaginas;
            var inicio = (mdfePaginaAtual - 1) * mdfePerPage;
            var fim = inicio + mdfePerPage;
            var docs = docsFiltrados.slice(inicio, fim);

            var html = '<table class="data-table" id="mdfeDataTable"><thead><tr>';
            html += buildMdfeSortableTh('mod', 'Mod', 'col-mod');
            html += buildMdfeSortableTh('serie_mdfe', 'Série', 'col-serie');
            html += buildMdfeSortableTh('numero_mdfe', 'Número', 'col-numero');
            html += buildMdfeSortableTh('chave_acesso', 'Chave', '');
            html += buildMdfeSortableTh('data_emissao', 'Emissão', '');
            html += buildMdfeSortableTh('uf_inicio', 'UF Início', '');
            html += buildMdfeSortableTh('uf_fim', 'UF Fim', '');
            html += buildMdfeSortableTh('origem_documental', 'Origem', '');
            html += buildMdfeSortableTh('total_cte', 'Qtd CT-e', 'col-qtd');
            html += buildMdfeSortableTh('peso_vol', 'Peso/Vol.', '');
            html += buildMdfeSortableTh('status', 'Situação', '');
            html += '<th class="col-acoes">Ações</th></tr></thead><tbody>';
            docs.forEach(function(doc) {
                var dataEmissao = doc.data_emissao ? new Date(doc.data_emissao).toLocaleDateString('pt-BR') : '-';
                var numero = String(doc.numero_mdfe || '').padStart(9, '0');
                var serie = doc.serie_mdfe || '1';
                var ufIni = doc.uf_inicio || '-';
                var ufFim = doc.uf_fim || '-';
                var qtdCte = doc.total_cte != null ? doc.total_cte : (doc.qtd_cte != null ? doc.qtd_cte : '-');
                var origemDoc = String(doc.origem_documental || 'manual');
                var qtdNfeOrigem = parseInt(doc.qtd_nfe_origem || 0, 10);
                var origemLabel = origemDoc === 'cte' ? 'CT-e' : (origemDoc === 'nfe' ? 'NF-e' : (origemDoc === 'misto' ? 'Misto' : 'Manual'));
                var origemTitle = origemDoc === 'misto'
                    ? ('CT-e: ' + (qtdCte || 0) + ' | NF-e: ' + qtdNfeOrigem)
                    : (origemDoc === 'nfe' ? ('NF-e: ' + qtdNfeOrigem) : (origemDoc === 'cte' ? ('CT-e: ' + (qtdCte || 0)) : 'Sem vínculo automático'));
                var peso = doc.peso_total != null ? parseFloat(doc.peso_total).toFixed(1) : (doc.peso_total_carga != null ? parseFloat(doc.peso_total_carga).toFixed(1) : '-');
                var vol = doc.volumes_total != null ? doc.volumes_total : (doc.qtd_total_volumes != null ? doc.qtd_total_volumes : '-');
                var pesoVol = (peso !== '-' ? peso + ' kg' : '') + (vol !== '-' ? ' / ' + vol + ' vol.' : '');
                if (!pesoVol) pesoVol = '-';
                var st = doc.status || 'pendente';
                var stTexto = st === 'autorizado' ? 'Autorizado' : st === 'rascunho' ? 'Rascunho' : st === 'pendente' ? 'Pendente' : st === 'encerrado' ? 'Encerrado' : st === 'cancelado' ? 'Cancelado' : st === 'denegado' ? 'Denegado' : st;
                var stClass = st.replace(/_/g, '-');
                var encerrado = !!(doc.data_encerramento);
                var dataRef = doc.data_autorizacao || doc.data_emissao;
                var dentro24h = false;
                if (dataRef && (st === 'autorizado' && !encerrado)) {
                    var t = new Date(dataRef).getTime();
                    dentro24h = (Date.now() - t) < (24 * 60 * 60 * 1000);
                }
                html += '<tr>';
                html += '<td class="col-mod">58</td>';
                html += '<td class="col-serie">' + escapeHtml(serie) + '</td>';
                html += '<td class="col-numero">' + escapeHtml(numero) + '</td>';
                html += '<td class="col-chave" title="' + escapeHtml(doc.chave_acesso || '') + '">' + escapeHtml(doc.chave_acesso || '-') + '</td>';
                html += '<td>' + dataEmissao + '</td>';
                html += '<td>' + escapeHtml(ufIni) + '</td>';
                html += '<td>' + escapeHtml(ufFim) + '</td>';
                html += '<td><span class="mdfe-origem-badge origem-' + escapeHtml(origemDoc) + '" title="' + escapeHtml(origemTitle) + '">' + escapeHtml(origemLabel) + '</span></td>';
                html += '<td class="col-qtd">' + qtdCte + '</td>';
                html += '<td>' + escapeHtml(pesoVol) + '</td>';
                html += '<td class="situacao-' + stClass + '">' + escapeHtml(stTexto) + '</td>';
                html += '<td class="actions col-acoes">';
                html += '<a class="btn-icon" href="#" onclick="abrirModalMdfe(' + doc.id + '); return false;" title="Visualizar"><i class="fas fa-eye"></i></a>';
                html += ' <a class="btn-icon" href="#" onclick="abrirAuditoriaMdfe(' + doc.id + '); return false;" title="Auditoria técnica"><i class="fas fa-clipboard-check"></i></a>';
                if (st === 'rascunho' || st === 'pendente') {
                    html += ' <a class="btn-icon" href="#" onclick="abrirModalEditarMdfe(' + doc.id + '); return false;" title="Editar"><i class="fas fa-edit"></i></a>';
                    html += ' <a class="btn-icon" href="#" onclick="enviarMDFESefaz(' + doc.id + '); return false;" title="Enviar SEFAZ"><i class="fas fa-paper-plane"></i></a>';
                }
                if (st === 'autorizado' && !encerrado) {
                    if (dentro24h) {
                        html += ' <a class="btn-icon" href="#" onclick="cancelarMDFE(' + doc.id + '); return false;" title="Cancelar MDF-e (até 24h)"><i class="fas fa-times-circle"></i></a>';
                    }
                    html += ' <a class="btn-icon" href="#" onclick="abrirModalIncluirCondutor(' + doc.id + '); return false;" title="Incluir condutor"><i class="fas fa-user-edit"></i></a>';
                    html += ' <a class="btn-icon" href="#" onclick="encerrarMDFE(' + doc.id + '); return false;" title="Encerrar MDF-e"><i class="fas fa-stop-circle"></i></a>';
                }
                html += '</td></tr>';
            });
            html += '</tbody></table>';
            el.innerHTML = html;
            bindMdfeSortEvents();
            atualizarPaginacaoMdfe(docsFiltrados.length);
        }

        function buildMdfeSortableTh(field, label, extraClass) {
            var isActive = mdfeSortField === field;
            var indicator = isActive ? (mdfeSortDir === 'asc' ? '▲' : '▼') : '⇅';
            var cls = 'sortable' + (isActive ? ' sorted' : '') + (extraClass ? (' ' + extraClass) : '');
            return '<th class="' + cls + '" data-sort="' + field + '">' + label + ' <span class="sort-ind">' + indicator + '</span></th>';
        }

        function bindMdfeSortEvents() {
            var table = document.getElementById('mdfeDataTable');
            if (!table) return;
            var headers = table.querySelectorAll('th.sortable');
            headers.forEach(function(th) {
                th.addEventListener('click', function() {
                    var field = this.getAttribute('data-sort');
                    if (!field) return;
                    if (mdfeSortField === field) {
                        mdfeSortDir = mdfeSortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        mdfeSortField = field;
                        mdfeSortDir = (field === 'data_emissao' || field === 'numero_mdfe' || field === 'total_cte') ? 'desc' : 'asc';
                    }
                    mdfePaginaAtual = 1;
                    aplicarFiltrosMDFE();
                });
            });
        }

        function ordenarMdfeDocs(docs) {
            var arr = (docs || []).slice();
            var field = mdfeSortField;
            var dir = mdfeSortDir === 'asc' ? 1 : -1;
            arr.sort(function(a, b) {
                var av;
                var bv;
                if (field === 'mod') {
                    av = 58; bv = 58;
                } else if (field === 'peso_vol') {
                    av = parseFloat(a.peso_total != null ? a.peso_total : (a.peso_total_carga != null ? a.peso_total_carga : 0)) || 0;
                    bv = parseFloat(b.peso_total != null ? b.peso_total : (b.peso_total_carga != null ? b.peso_total_carga : 0)) || 0;
                } else if (field === 'data_emissao') {
                    av = new Date(a.data_emissao || 0).getTime();
                    bv = new Date(b.data_emissao || 0).getTime();
                } else if (field === 'numero_mdfe' || field === 'serie_mdfe' || field === 'total_cte') {
                    av = parseInt(a[field] != null ? a[field] : 0, 10) || 0;
                    bv = parseInt(b[field] != null ? b[field] : 0, 10) || 0;
                } else {
                    av = String(a[field] != null ? a[field] : '').toLowerCase();
                    bv = String(b[field] != null ? b[field] : '').toLowerCase();
                }
                if (av < bv) return -1 * dir;
                if (av > bv) return 1 * dir;
                return 0;
            });
            return arr;
        }

        function atualizarPaginacaoMdfe(totalItens) {
            var container = document.getElementById('paginationMdfeContainer');
            if (!container) return;
            if (!totalItens) {
                container.innerHTML = '';
                return;
            }
            var totalPaginas = Math.max(1, Math.ceil(totalItens / mdfePerPage));
            var inicio = ((mdfePaginaAtual - 1) * mdfePerPage) + 1;
            var fim = Math.min(totalItens, mdfePaginaAtual * mdfePerPage);
            var prevDisabled = mdfePaginaAtual <= 1;
            var nextDisabled = mdfePaginaAtual >= totalPaginas;
            container.innerHTML =
                '<button type="button" class="pagination-btn' + (prevDisabled ? ' disabled' : '') + '" id="mdfePagePrevBtn">Anterior</button>' +
                '<span class="pagination-info">' + inicio + '-' + fim + ' de ' + totalItens + ' • Página ' + mdfePaginaAtual + '/' + totalPaginas + '</span>' +
                '<button type="button" class="pagination-btn' + (nextDisabled ? ' disabled' : '') + '" id="mdfePageNextBtn">Próxima</button>';
            var prevBtn = document.getElementById('mdfePagePrevBtn');
            var nextBtn = document.getElementById('mdfePageNextBtn');
            if (prevBtn && !prevDisabled) {
                prevBtn.addEventListener('click', function() {
                    mdfePaginaAtual -= 1;
                    aplicarFiltrosMDFE();
                });
            }
            if (nextBtn && !nextDisabled) {
                nextBtn.addEventListener('click', function() {
                    mdfePaginaAtual += 1;
                    aplicarFiltrosMDFE();
                });
            }
        }

        function escapeHtml(s) {
            if (s == null) return '';
            var div = document.createElement('div');
            div.textContent = s;
            return div.innerHTML;
        }

        function abrirModalMdfe(id) {
            var modal = new bootstrap.Modal(document.getElementById('modalVisualizarMdfe'));
            var body = document.getElementById('modalVisualizarMdfeBody');
            var title = document.getElementById('modalVisualizarMdfeLabel');
            body.innerHTML = '<p class="text-muted">Carregando...</p>';
            modal.show();
            mdfeApiFetch('../api/documentos_fiscais_v2.php?action=get&tipo=mdfe&id=' + id)
                .then(function(res) {
                    var data = res.data || {};
                    if (!data.success || !data.documento) {
                        body.innerHTML = '<p class="text-danger">MDF-e não encontrado.</p>';
                        return;
                    }
                    var d = data.documento;
                    var dataEmissao = d.data_emissao ? new Date(d.data_emissao).toLocaleDateString('pt-BR') : '-';
                    var st = d.status === 'autorizado' ? 'Autorizado' : d.status === 'rascunho' ? 'Rascunho' : d.status === 'pendente' ? 'Pendente' : d.status === 'encerrado' ? 'Encerrado' : d.status || '-';
                    var origemDoc = String(d.origem_documental || 'manual');
                    var origemLabel = origemDoc === 'cte' ? 'CT-e' : (origemDoc === 'nfe' ? 'NF-e' : (origemDoc === 'misto' ? 'Misto' : 'Manual'));
                    var qtdCteOrigem = Array.isArray(d.cte_ids) ? d.cte_ids.length : Number(d.total_cte || 0);
                    var qtdNfeOrigem = Number(d.qtd_nfe_origem || (Array.isArray(d.nfe_ids) ? d.nfe_ids.length : 0));
                    title.innerHTML = '<i class="fas fa-route"></i> MDF-e ' + (d.numero_mdfe || id);
                    body.innerHTML = '<dl class="row mb-0">' +
                        '<dt class="col-sm-4">Número</dt><dd class="col-sm-8">' + escapeHtml(d.numero_mdfe || '-') + '</dd>' +
                        '<dt class="col-sm-4">Série</dt><dd class="col-sm-8">' + escapeHtml(d.serie_mdfe || '-') + '</dd>' +
                        '<dt class="col-sm-4">Chave</dt><dd class="col-sm-8"><code class="small">' + escapeHtml(d.chave_acesso || '-') + '</code></dd>' +
                        '<dt class="col-sm-4">Data emissão</dt><dd class="col-sm-8">' + dataEmissao + '</dd>' +
                        '<dt class="col-sm-4">UF Início / Fim</dt><dd class="col-sm-8">' + escapeHtml(d.uf_inicio || '-') + ' → ' + escapeHtml(d.uf_fim || '-') + '</dd>' +
                        '<dt class="col-sm-4">Município carregamento</dt><dd class="col-sm-8">' + escapeHtml(d.municipio_carregamento || '-') + '</dd>' +
                        '<dt class="col-sm-4">Município descarregamento</dt><dd class="col-sm-8">' + escapeHtml(d.municipio_descarregamento || '-') + '</dd>' +
                        '<dt class="col-sm-4">Origem</dt><dd class="col-sm-8"><span class="mdfe-origem-badge origem-' + escapeHtml(origemDoc) + '">' + escapeHtml(origemLabel) + '</span> <small class="text-muted ms-2">CT-e: ' + escapeHtml(String(qtdCteOrigem)) + ' | NF-e: ' + escapeHtml(String(qtdNfeOrigem)) + '</small></dd>' +
                        '<dt class="col-sm-4">CT-e vinculados</dt><dd class="col-sm-8">' + (d.total_cte != null ? d.total_cte : '-') + '</dd>' +
                        '<dt class="col-sm-4">Status</dt><dd class="col-sm-8">' + escapeHtml(st) + '</dd>' +
                        '</dl>';
                })
                .catch(function() { body.innerHTML = '<p class="text-danger">Erro ao carregar dados.</p>'; });
        }

        function abrirAuditoriaMdfe(id) {
            var modal = new bootstrap.Modal(document.getElementById('modalAuditoriaMdfe'));
            var body = document.getElementById('modalAuditoriaMdfeBody');
            var title = document.getElementById('modalAuditoriaMdfeLabel');
            body.innerHTML = '<p class="text-muted">Carregando auditoria estruturada...</p>';
            modal.show();

            mdfeApiFetch('../api/documentos_fiscais_v2.php?action=get&tipo=mdfe&id=' + id)
                .then(function(r) {
                    var resp = r.data || {};
                    if (!resp.success || !resp.documento) {
                        body.innerHTML = '<p class="text-danger">MDF-e não encontrado para auditoria.</p>';
                        return;
                    }
                    var d = resp.documento;
                    var w = d.wizard_estruturado || {};
                    title.innerHTML = '<i class="fas fa-clipboard-check"></i> Auditoria MDF-e ' + escapeHtml(String(d.numero_mdfe || id));

                    var c = {
                        ciot: (w.ciots || []).length,
                        vale: (w.vales_pedagio || []).length,
                        contratantes: (w.contratantes || []).length,
                        pagamentos: (w.pagamentos || []).length,
                        componentes: (w.pagamento_componentes || []).length,
                        seguros: (w.seguros || []).length,
                        averbacoes: (w.seguros_averbacoes || []).length,
                        produtos: (w.produtos || []).length,
                        lacres: (w.lacres || []).length,
                        autorizados: (w.autorizados_download || []).length
                    };

                    var html = '';
                    html += '<div class="mdfe-audit-grid">';
                    html += '<div class="mdfe-audit-card"><div class="k">CIOT</div><div class="v">' + c.ciot + '</div></div>';
                    html += '<div class="mdfe-audit-card"><div class="k">Vale pedágio</div><div class="v">' + c.vale + '</div></div>';
                    html += '<div class="mdfe-audit-card"><div class="k">Contratantes</div><div class="v">' + c.contratantes + '</div></div>';
                    html += '<div class="mdfe-audit-card"><div class="k">Pagamentos</div><div class="v">' + c.pagamentos + '</div></div>';
                    html += '<div class="mdfe-audit-card"><div class="k">Componentes pagamento</div><div class="v">' + c.componentes + '</div></div>';
                    html += '<div class="mdfe-audit-card"><div class="k">Seguros / Averbações</div><div class="v">' + c.seguros + ' / ' + c.averbacoes + '</div></div>';
                    html += '<div class="mdfe-audit-card"><div class="k">Produtos predominantes</div><div class="v">' + c.produtos + '</div></div>';
                    html += '<div class="mdfe-audit-card"><div class="k">Lacres</div><div class="v">' + c.lacres + '</div></div>';
                    html += '<div class="mdfe-audit-card"><div class="k">Autorizados download</div><div class="v">' + c.autorizados + '</div></div>';
                    html += '</div>';

                    html += '<div class="mdfe-audit-section"><h6>Origem e vínculo</h6>';
                    html += '<div><strong>Origem:</strong> ' + escapeHtml(String(d.origem_documental || 'manual')) + '</div>';
                    html += '<div><strong>CT-e:</strong> ' + escapeHtml(String((d.cte_ids || []).length || d.total_cte || 0)) +
                        ' | <strong>NF-e:</strong> ' + escapeHtml(String((d.nfe_ids || []).length || d.qtd_nfe_origem || 0)) + '</div>';
                    html += '</div>';

                    html += '<div class="mdfe-audit-section"><h6>Amostra técnica</h6>';
                    html += '<pre style="white-space:pre-wrap; font-size:.74rem; max-height:280px; overflow:auto;">' +
                        escapeHtml(JSON.stringify({
                            ciots: (w.ciots || []).slice(0, 2),
                            vales_pedagio: (w.vales_pedagio || []).slice(0, 2),
                            contratantes: (w.contratantes || []).slice(0, 2),
                            pagamentos: (w.pagamentos || []).slice(0, 2),
                            produtos: (w.produtos || []).slice(0, 2),
                            lacres: (w.lacres || []).slice(0, 5),
                            autorizados_download: (w.autorizados_download || []).slice(0, 5)
                        }, null, 2)) +
                        '</pre></div>';

                    body.innerHTML = html;
                })
                .catch(function() {
                    body.innerHTML = '<p class="text-danger">Erro ao carregar auditoria.</p>';
                });
        }

        function enviarMDFESefaz(id) {
            mdfeConfirmAsync('Enviar para SEFAZ', 'Validar e enviar este MDF-e para a SEFAZ?').then(function(ok) {
                if (!ok) return;
                mdfeApiFetch('../api/documentos_fiscais_v2.php?action=validar_mdfe&id=' + id)
                    .then(function(resVal) {
                        var data = resVal.data || {};
                        if (!data.success) {
                            mdfeNotify(mdfeApiErrorMessage(data, resVal.status), 'error');
                            return null;
                        }
                        if (!data.valid) {
                            mostrarErroValidacaoEOferecerEditar(id, data.message || 'MDF-e não está válido para envio.');
                            return null;
                        }
                        var fd = new FormData();
                        fd.append('action', 'enviar_sefaz');
                        fd.append('id', id);
                        fd.append('tipo_documento', 'mdfe');
                        return mdfeApiFetch('../api/documentos_fiscais_v2.php', { method: 'POST', body: fd });
                    })
                    .then(function(resEnv) {
                        if (!resEnv) return;
                        var data = resEnv.data || {};
                        if (data.success) {
                            mdfeNotify('MDF-e enviado. Status: ' + data.status + (data.protocolo ? ' | Protocolo: ' + data.protocolo : ''), 'success');
                            carregarMDFE();
                        } else {
                            mostrarErroValidacaoEOferecerEditar(id, mdfeApiErrorMessage(data, resEnv.status));
                        }
                    })
                    .catch(function() { mdfeNotify('Erro ao enviar. Verifique a conexão e tente novamente.', 'error'); });
            });
        }

        function mostrarErroValidacaoEOferecerEditar(mdfeId, mensagem) {
            document.getElementById('modalErroValidacaoMdfeTexto').textContent = mensagem;
            var btn = document.getElementById('btnEditarAposErro');
            btn.onclick = function() {
                bootstrap.Modal.getInstance(document.getElementById('modalErroValidacaoMdfe')).hide();
                abrirModalEditarMdfe(mdfeId);
            };
            new bootstrap.Modal(document.getElementById('modalErroValidacaoMdfe')).show();
        }

        function abrirModalEditarMdfe(id) {
            document.getElementById('editarMdfeId').value = id;
            var modal = document.getElementById('editarMDFEModal');
            document.getElementById('cteSelectorEdit').innerHTML = 'Carregando...';
            if (!document.getElementById('veiculoMDFEEdit').options.length) carregarVeiculosMotoristasEdit();
            mdfeApiFetch('../api/documentos_fiscais_v2.php?action=get&tipo=mdfe&id=' + id)
                .then(function(r0) {
                    var res = r0.data || {};
                    if (!res.success || !res.documento) { mdfeNotify('MDF-e não encontrado.'); return; }
                    var d = res.documento;
                    var origemDoc = String(d.origem_documental || 'manual');
                    var origemLabel = origemDoc === 'cte' ? 'CT-e' : (origemDoc === 'nfe' ? 'NF-e' : (origemDoc === 'misto' ? 'Misto' : 'Manual'));
                    var qtdCteOrigem = Array.isArray(d.cte_ids) ? d.cte_ids.length : Number(d.total_cte || 0);
                    var qtdNfeOrigem = Number(d.qtd_nfe_origem || (Array.isArray(d.nfe_ids) ? d.nfe_ids.length : 0));
                    var origemWrap = document.getElementById('origemMdfeEditInfo');
                    if (origemWrap) {
                        origemWrap.innerHTML = '<span class="mdfe-origem-badge origem-' + escapeHtml(origemDoc) + '">' + escapeHtml(origemLabel) + '</span>' +
                            ' <small class="text-muted ms-2">CT-e: ' + escapeHtml(String(qtdCteOrigem)) + ' | NF-e: ' + escapeHtml(String(qtdNfeOrigem)) + '</small>';
                    }
                    document.getElementById('veiculoMDFEEdit').value = d.veiculo_id || '';
                    document.getElementById('motoristaMDFEEdit').value = d.motorista_id || '';
                    document.getElementById('ufInicioEdit').value = d.uf_inicio || '';
                    document.getElementById('ufFimEdit').value = d.uf_fim || '';
                    document.getElementById('tipoViagemEdit').value = d.tipo_viagem || '1';
                    document.getElementById('observacoesMDFEEdit').value = d.observacoes || '';
                    if (d.uf_inicio) loadCidadesMdfe(d.uf_inicio, 'cidadeCarregamentoEdit');
                    if (d.uf_fim) loadCidadesMdfe(d.uf_fim, 'cidadeDescarregamentoEdit');
                    setTimeout(function() {
                        document.getElementById('cidadeCarregamentoEdit').value = d.municipio_carregamento || '';
                        document.getElementById('cidadeDescarregamentoEdit').value = d.municipio_descarregamento || '';
                        document.getElementById('cidadeCarregamentoEdit').disabled = false;
                        document.getElementById('cidadeDescarregamentoEdit').disabled = false;
                    }, 300);
                    var linkedIds = (d.cte_ids || []).map(function(x) { return parseInt(x, 10); }).filter(function(x) { return x > 0; });
                    return mdfeApiFetch('../api/documentos_fiscais_v2.php?action=list&tipo=cte&status=autorizado&limit=100').then(function(r1) {
                        var cteRes = r1.data || {};
                        var sel = document.getElementById('cteSelectorEdit');
                        if (!cteRes || !cteRes.success || !cteRes.documentos) { sel.innerHTML = 'Nenhum CT-e autorizado.'; new bootstrap.Modal(modal).show(); return; }
                        var html = '';
                        cteRes.documentos.forEach(function(cte) {
                            var idCte = cte.id != null ? Number(cte.id) : null;
                            if (idCte == null || idCte <= 0) return;
                            var dataF = cte.data_emissao ? new Date(cte.data_emissao).toLocaleDateString('pt-BR') : '-';
                            var valor = parseFloat(cte.valor_total || 0).toFixed(2).replace('.', ',');
                            var origem = (cte.origem_cidade || cte.origem || 'N/A');
                            var destino = (cte.destino_cidade || cte.destino || 'N/A');
                            var checked = linkedIds.indexOf(idCte) >= 0 ? ' checked' : '';
                            html += '<div class="cte-item" onclick="toggleCTEEdit(this)">';
                            html += '<input type="checkbox" name="cte_ids[]" value="' + idCte + '"' + checked + ' style="margin-right:8px" data-numero="' + (cte.numero_cte||'') + '" data-valor="' + (cte.valor_total||0) + '" data-peso="' + (cte.peso_carga||0) + '" data-volumes="' + (cte.volumes_carga||0) + '">';
                            html += '<strong>CT-e ' + String(cte.numero_cte||'').padStart(6,'0') + '</strong> ' + dataF + ' | ' + origem + ' → ' + destino + ' | R$ ' + valor;
                            html += '</div>';
                        });
                        sel.innerHTML = html || '<p class="text-muted mb-0">Nenhum CT-e autorizado.</p>';
                        new bootstrap.Modal(modal).show();
                    });
                })
                .catch(function() {
                    document.getElementById('cteSelectorEdit').innerHTML = 'Erro ao carregar.';
                    new bootstrap.Modal(modal).show();
                });
            var ufIni = document.getElementById('ufInicioEdit');
            var ufFim = document.getElementById('ufFimEdit');
            if (!ufIni._editListener) {
                ufIni._editListener = true;
                ufIni.addEventListener('change', function() {
                    var uf = this.value;
                    if (uf) { loadCidadesMdfe(uf, 'cidadeCarregamentoEdit'); document.getElementById('cidadeCarregamentoEdit').disabled = false; }
                    else { document.getElementById('cidadeCarregamentoEdit').innerHTML = '<option value="">Selecione primeiro o estado</option>'; document.getElementById('cidadeCarregamentoEdit').disabled = true; }
                });
            }
            if (!ufFim._editListener) {
                ufFim._editListener = true;
                ufFim.addEventListener('change', function() {
                    var uf = this.value;
                    if (uf) { loadCidadesMdfe(uf, 'cidadeDescarregamentoEdit'); document.getElementById('cidadeDescarregamentoEdit').disabled = false; }
                    else { document.getElementById('cidadeDescarregamentoEdit').innerHTML = '<option value="">Selecione primeiro o estado</option>'; document.getElementById('cidadeDescarregamentoEdit').disabled = true; }
                });
            }
        }

        function carregarVeiculosMotoristasEdit() {
            fetch('../../api/route_actions.php?action=get_veiculos').then(function(r) { return r.json(); }).then(function(d) {
                var list = (d && d.success && d.data) ? d.data : [];
                var v = document.getElementById('veiculoMDFEEdit');
                v.innerHTML = '<option value="">Selecione</option>';
                list.forEach(function(o) {
                    var opt = document.createElement('option');
                    opt.value = o.id;
                    opt.textContent = (o.placa || 'Veículo') + (o.modelo ? ' - ' + o.modelo : '');
                    v.appendChild(opt);
                });
            });
            fetch('../../api/motoristas.php?action=list&limit=500').then(function(r) { return r.json(); }).then(function(d) {
                var list = (d && d.success && d.data) ? d.data : [];
                var m = document.getElementById('motoristaMDFEEdit');
                m.innerHTML = '<option value="">Selecione</option>';
                list.forEach(function(o) {
                    var opt = document.createElement('option');
                    opt.value = o.id;
                    opt.textContent = (o.nome || 'Motorista') + (o.cpf ? ' - ' + o.cpf : '');
                    m.appendChild(opt);
                });
            });
        }

        function toggleCTEEdit(el) {
            var cb = el.querySelector('input[type="checkbox"]');
            if (cb) { cb.checked = !cb.checked; el.classList.toggle('selected', cb.checked); }
        }

        document.getElementById('btnSalvarEditarMDFE').onclick = function() {
            var id = document.getElementById('editarMdfeId').value;
            if (!id) return;
            var form = document.getElementById('editarMDFEForm');
            if (!form.checkValidity()) { form.reportValidity(); return; }
            var cbs = document.querySelectorAll('#cteSelectorEdit input[name="cte_ids[]"]:checked');
            if (cbs.length === 0) { mdfeNotify('Selecione pelo menos um CT-e.'); return; }
            var fd = new FormData(form);
            fd.append('action', 'update');
            fd.append('id', id);
            fd.append('tipo_documento', 'mdfe');
            cbs.forEach(function(cb) { fd.append('cte_ids[]', cb.value); });
            var btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
            mdfeApiFetch('../api/documentos_fiscais_v2.php', { method: 'POST', body: fd })
                .then(function(res) {
                    var data = res.data || {};
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('editarMDFEModal')).hide();
                        carregarMDFE();
                    } else {
                        mdfeNotify(mdfeApiErrorMessage(data, res.status), 'error');
                    }
                })
                .catch(function() { mdfeNotify('Erro ao salvar. Tente novamente.', 'error'); })
                .finally(function() { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Salvar'; });
        };

        function encerrarMDFE(id) {
            mdfeConfirmAsync('Encerrar MDF-e', 'Após encerrado, a viagem fica finalizada e não é possível cancelar. Deseja continuar?').then(function(ok) {
                if (!ok) return;
                var fd = new FormData();
                fd.append('action', 'encerrar_mdfe');
                fd.append('id', id);
                mdfeApiFetch('../api/documentos_fiscais_v2.php', { method: 'POST', body: fd })
                    .then(function(res) {
                        var data = res.data || {};
                        if (data.success) {
                            mdfeNotify(data.message || 'MDF-e encerrado.', 'success');
                            carregarMDFE();
                        } else {
                            mdfeNotify(mdfeApiErrorMessage(data, res.status), 'error');
                        }
                    })
                    .catch(function() { mdfeNotify('Erro ao encerrar. Tente novamente.', 'error'); });
            });
        }

        function cancelarMDFE(id) {
            mdfeConfirmAsync('Cancelar MDF-e', 'Só é permitido antes da viagem e dentro de 24h da autorização. Esta ação não pode ser desfeita. Deseja cancelar?').then(function(ok) {
                if (!ok) return;
                var fd = new FormData();
                fd.append('action', 'cancelar_mdfe');
                fd.append('id', id);
                mdfeApiFetch('../api/documentos_fiscais_v2.php', { method: 'POST', body: fd })
                    .then(function(res) {
                        var data = res.data || {};
                        if (data.success) {
                            mdfeNotify(data.message || 'MDF-e cancelado.', 'success');
                            carregarMDFE();
                        } else {
                            mdfeNotify(mdfeApiErrorMessage(data, res.status), 'error');
                        }
                    })
                    .catch(function() { mdfeNotify('Erro ao cancelar. Tente novamente.', 'error'); });
            });
        }

        var mdfeIncluirCondutorId = null;
        function abrirModalIncluirCondutor(id) {
            mdfeIncluirCondutorId = id;
            var modal = document.getElementById('modalIncluirCondutor');
            var sel = document.getElementById('condutorMotoristaSelect');
            if (!sel.options || sel.options.length <= 1) {
                fetch('../../api/motoristas.php?action=list&limit=500').then(function(r) { return r.json(); }).then(function(d) {
                    var list = (d && d.success && d.data) ? d.data : [];
                    sel.innerHTML = '<option value="">Selecione o novo condutor</option>';
                    list.forEach(function(o) {
                        var opt = document.createElement('option');
                        opt.value = o.id;
                        opt.textContent = (o.nome || 'Motorista') + (o.cpf ? ' - ' + o.cpf : '');
                        sel.appendChild(opt);
                    });
                });
            }
            new bootstrap.Modal(modal).show();
        }
        function salvarIncluirCondutor() {
            var id = mdfeIncluirCondutorId;
            var motoristaId = document.getElementById('condutorMotoristaSelect').value;
            if (!id || !motoristaId) { mdfeNotify('Selecione o novo condutor.'); return; }
            var fd = new FormData();
            fd.append('action', 'incluir_condutor_mdfe');
            fd.append('id', id);
            fd.append('motorista_id', motoristaId);
            mdfeApiFetch('../api/documentos_fiscais_v2.php', { method: 'POST', body: fd })
                .then(function(res) {
                    var data = res.data || {};
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('modalIncluirCondutor')).hide();
                        mdfeNotify(data.message || 'Condutor incluído.', 'success');
                        carregarMDFE();
                    } else {
                        mdfeNotify(mdfeApiErrorMessage(data, res.status), 'error');
                    }
                })
                .catch(function() { mdfeNotify('Erro ao incluir condutor.'); });
        }

        function exportarDados() {
            mdfeNotify('Exportação em desenvolvimento.');
        }
