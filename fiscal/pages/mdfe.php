<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
configure_session();
session_start();
require_authentication();

$page_title = "Gestão de MDF-e";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="../../css/theme.css">
    <link rel="stylesheet" href="../../css/responsive.css">
    <link rel="icon" type="image/png" href="../../logo.png">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <style>
        .mdfe-table-wrap { overflow-x: auto; }
        .mdfe-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .mdfe-table th, .mdfe-table td { border: 1px solid var(--border-color); padding: 8px 10px; text-align: left; }
        .mdfe-table th { background: var(--bg-secondary); font-weight: 600; color: var(--text-secondary); }
        .mdfe-table tbody tr:hover { background: #dee2e6; }
        .mdfe-table .col-num { text-align: right; }
        .mdfe-table .col-vlr { text-align: right; white-space: nowrap; }
        .mdfe-table .col-chave { font-family: monospace; font-size: 0.75rem; max-width: 220px; overflow: hidden; text-overflow: ellipsis; }
        .mdfe-table .col-acoes { white-space: nowrap; }
        .mdfe-table .col-acoes a, .mdfe-table .col-acoes button { padding: 4px 8px; margin: 0 2px; border: none; background: none; cursor: pointer; color: var(--text-secondary); text-decoration: none; }
        .mdfe-table .col-acoes a:hover, .mdfe-table .col-acoes button:hover { color: var(--primary-color); }
        .mdfe-table .situacao-autorizado, .mdfe-table .situacao-encerrado { color: #155724; font-weight: 500; }
        .mdfe-table .situacao-pendente, .mdfe-table .situacao-rascunho { color: #856404; }
        .mdfe-table .situacao-cancelado, .mdfe-table .situacao-denegado { color: #721c24; }
        .document-list { background: var(--bg-secondary); border-radius: 8px; border: 1px solid var(--border-color); padding: 20px; }
        #modalVisualizarMdfeBody dl dt { font-weight: 600; color: #6c757d; }
        #modalVisualizarMdfeBody dl dd { margin-bottom: 0.5rem; }
        .cte-selector { max-height: 320px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; background: var(--bg-secondary); }
        .cte-item { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; margin-bottom: 8px; cursor: pointer; }
        .cte-item:hover { border-color: var(--primary-color); }
        .cte-item.selected { border-color: var(--primary-color); background: rgba(0,123,255,0.05); }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../../includes/header.php'; ?>
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1><?php echo $page_title; ?></h1>
                    <div class="dashboard-actions">
                        <button type="button" class="btn-add-widget" onclick="abrirModalCriarMDFE()">
                            <i class="fas fa-plus"></i> Criar MDF-e
                        </button>
                        <div class="view-controls">
                            <button type="button" class="btn-restore-layout" title="Atualizar lista" onclick="carregarMDFE()">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button type="button" class="btn-toggle-layout" title="Exportar" onclick="exportarDados()">
                                <i class="fas fa-file-export"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="document-list mdfe-table-wrap" style="margin-top: 20px;">
                    <h3>MDF-e</h3>
                    <div id="mdfeList">
                        <p>Carregando MDF-e...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Visualizar MDF-e -->
    <div class="modal fade" id="modalVisualizarMdfe" tabindex="-1" aria-labelledby="modalVisualizarMdfeLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalVisualizarMdfeLabel"><i class="fas fa-route"></i> MDF-e</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body" id="modalVisualizarMdfeBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Criar MDF-e -->
    <div class="modal fade" id="criarMDFEModal" tabindex="-1" aria-labelledby="criarMDFEModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="criarMDFEModalLabel"><i class="fas fa-plus"></i> Criar MDF-e</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Selecione os CT-e autorizados que farão parte desta viagem. O MDF-e será emitido no seu CNPJ (transportadora executora); basta informar a chave dos CT-e.</p>
                    <form id="criarMDFEForm">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="veiculoMDFE" class="form-label">Veículo</label>
                                <select class="form-select" id="veiculoMDFE" name="veiculo_id" required>
                                    <option value="">Selecione</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="motoristaMDFE" class="form-label">Motorista</label>
                                <select class="form-select" id="motoristaMDFE" name="motorista_id" required>
                                    <option value="">Selecione</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="ufInicio" class="form-label">UF Início</label>
                                <select class="form-select" id="ufInicio" name="uf_inicio" required>
                                    <option value="">Selecione o estado</option>
                                    <?php
                                    $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                                    foreach ($ufs as $uf) { echo "<option value=\"$uf\">$uf</option>"; }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="cidadeCarregamento" class="form-label">Cidade carregamento</label>
                                <select class="form-select" id="cidadeCarregamento" name="municipio_carregamento" required disabled>
                                    <option value="">Selecione primeiro o estado</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="tipoViagem" class="form-label">Tipo</label>
                                <select class="form-select" id="tipoViagem" name="tipo_viagem">
                                    <option value="1">Com CT-e</option>
                                    <option value="2">Sem CT-e</option>
                                    <option value="3">Misto</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="ufFim" class="form-label">UF Fim</label>
                                <select class="form-select" id="ufFim" name="uf_fim" required>
                                    <option value="">Selecione o estado</option>
                                    <?php foreach ($ufs as $uf) { echo "<option value=\"$uf\">$uf</option>"; } ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="cidadeDescarregamento" class="form-label">Cidade descarregamento</label>
                                <select class="form-select" id="cidadeDescarregamento" name="municipio_descarregamento" required disabled>
                                    <option value="">Selecione primeiro o estado</option>
                                </select>
                            </div>
                            <div class="col-md-4"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">CT-e autorizados</label>
                            <div id="cteSelector" class="cte-selector">Carregando CT-e...</div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Peso total (kg)</label>
                                <input type="number" class="form-control" id="totalPesoMDFE" name="peso_total" step="0.01" min="0" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Volumes</label>
                                <input type="number" class="form-control" id="totalVolumesMDFE" name="volumes_total" min="0" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Qtd CT-e</label>
                                <input type="number" class="form-control" id="totalCTe" name="total_cte" min="0" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Valor total</label>
                                <input type="number" class="form-control" id="valorTotalMDFE" name="valor_total" step="0.01" min="0" readonly>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="observacoesMDFE" class="form-label">Observações</label>
                            <textarea class="form-control" id="observacoesMDFE" name="observacoes" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnSalvarMDFE" onclick="salvarMDFE()"><i class="fas fa-save"></i> Criar MDF-e</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Erro de Validação (com botão Editar) -->
    <div class="modal fade" id="modalErroValidacaoMdfe" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle"></i> Validação antes de emitir</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="modalErroValidacaoMdfeTexto" class="mb-0"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" id="btnEditarAposErro"><i class="fas fa-edit"></i> Editar MDF-e</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar MDF-e -->
    <div class="modal fade" id="editarMDFEModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Editar MDF-e</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editarMdfeId" value="">
                    <p class="text-muted small">Corrija os dados e salve. Depois tente enviar para a SEFAZ novamente.</p>
                    <form id="editarMDFEForm">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="veiculoMDFEEdit" class="form-label">Veículo</label>
                                <select class="form-select" id="veiculoMDFEEdit" name="veiculo_id" required></select>
                            </div>
                            <div class="col-md-6">
                                <label for="motoristaMDFEEdit" class="form-label">Motorista</label>
                                <select class="form-select" id="motoristaMDFEEdit" name="motorista_id" required></select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="ufInicioEdit" class="form-label">UF Início</label>
                                <select class="form-select" id="ufInicioEdit" name="uf_inicio" required>
                                    <option value="">Selecione o estado</option>
                                    <?php $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO']; foreach ($ufs as $uf) { echo "<option value=\"$uf\">$uf</option>"; } ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="cidadeCarregamentoEdit" class="form-label">Cidade carregamento</label>
                                <select class="form-select" id="cidadeCarregamentoEdit" name="municipio_carregamento" required disabled></select>
                            </div>
                            <div class="col-md-4">
                                <label for="tipoViagemEdit" class="form-label">Tipo</label>
                                <select class="form-select" id="tipoViagemEdit" name="tipo_viagem">
                                    <option value="1">Com CT-e</option>
                                    <option value="2">Sem CT-e</option>
                                    <option value="3">Misto</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="ufFimEdit" class="form-label">UF Fim</label>
                                <select class="form-select" id="ufFimEdit" name="uf_fim" required>
                                    <option value="">Selecione o estado</option>
                                    <?php foreach ($ufs as $uf) { echo "<option value=\"$uf\">$uf</option>"; } ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="cidadeDescarregamentoEdit" class="form-label">Cidade descarregamento</label>
                                <select class="form-select" id="cidadeDescarregamentoEdit" name="municipio_descarregamento" required disabled></select>
                            </div>
                            <div class="col-md-4"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">CT-e autorizados</label>
                            <div id="cteSelectorEdit" class="cte-selector">Carregando...</div>
                        </div>
                        <div class="mb-3">
                            <label for="observacoesMDFEEdit" class="form-label">Observações</label>
                            <textarea class="form-control" id="observacoesMDFEEdit" name="observacoes" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnSalvarEditarMDFE"><i class="fas fa-save"></i> Salvar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Incluir Condutor (troca de motorista durante a viagem) -->
    <div class="modal fade" id="modalIncluirCondutor" tabindex="-1" aria-labelledby="modalIncluirCondutorLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalIncluirCondutorLabel"><i class="fas fa-user-edit"></i> Incluir / Trocar condutor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Use quando houver troca de motorista durante a viagem (revezamento, etc.).</p>
                    <div class="mb-3">
                        <label for="condutorMotoristaSelect" class="form-label">Novo condutor</label>
                        <select class="form-select" id="condutorMotoristaSelect">
                            <option value="">Carregando...</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarIncluirCondutor()"><i class="fas fa-check"></i> Incluir condutor</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/sidebar.js"></script>
    <script src="../../js/theme.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            carregarMDFE();
            carregarVeiculosMotoristas();
            setupEstadoCidadeMdfe();
        });

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
            return fetch('../api/documentos_fiscais_v2.php?action=list&tipo=cte&status=autorizado&limit=100')
                .then(function(r) { return r.json(); })
                .then(function(data) {
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
                        html += '<input type="checkbox" name="cte_ids[]" value="' + id + '" style="margin-right:8px" data-numero="' + (cte.numero_cte||'') + '" data-valor="' + (cte.valor_total||0) + '" data-peso="' + (cte.peso_carga||0) + '" data-volumes="' + (cte.volumes_carga||0) + '">';
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
            cbs.forEach(function(cb) {
                peso += parseFloat(cb.getAttribute('data-peso') || 0);
                volumes += parseInt(cb.getAttribute('data-volumes') || 0, 10);
                valor += parseFloat(cb.getAttribute('data-valor') || 0);
            });
            document.getElementById('totalPesoMDFE').value = peso.toFixed(2);
            document.getElementById('totalVolumesMDFE').value = volumes;
            document.getElementById('totalCTe').value = cbs.length;
            document.getElementById('valorTotalMDFE').value = valor.toFixed(2);
        }

        function salvarMDFE() {
            var form = document.getElementById('criarMDFEForm');
            if (!form.checkValidity()) { form.reportValidity(); return; }
            var cbs = document.querySelectorAll('input[name="cte_ids[]"]:checked');
            if (cbs.length === 0) {
                alert('Selecione pelo menos um CT-e.');
                return;
            }
            var fd = new FormData(form);
            fd.append('action', 'criar_mdfe');
            cbs.forEach(function(cb) { fd.append('cte_ids[]', cb.value); });
            var btn = document.getElementById('btnSalvarMDFE');
            var orig = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Criando...';
            btn.disabled = true;
            fetch('../api/documentos_fiscais_v2.php', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        alert('MDF-e criado: #' + data.numero_mdfe + '. Status: ' + data.status);
                        bootstrap.Modal.getInstance(document.getElementById('criarMDFEModal')).hide();
                        form.reset();
                        carregarMDFE();
                    } else {
                        alert('Erro: ' + (data.error || 'Erro desconhecido'));
                    }
                })
                .catch(function() { alert('Erro ao criar MDF-e.'); })
                .finally(function() { btn.innerHTML = orig; btn.disabled = false; });
        }

        function carregarMDFE() {
            var el = document.getElementById('mdfeList');
            el.innerHTML = '<div style="color:#17a2b8;background:#d1ecf1;padding:15px;border-radius:5px;"><strong>Carregando MDF-e...</strong></div>';
            fetch('../api/documentos_fiscais_v2.php?action=list&tipo=mdfe&limit=100')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.documentos && data.documentos.length > 0) {
                        var docs = data.documentos;
                        var html = '<table class="mdfe-table"><thead><tr>';
                        html += '<th>Mod</th><th>Série</th><th>Número</th><th>Chave</th><th>Emissão</th>';
                        html += '<th>UF Início</th><th>UF Fim</th><th>Qtd CT-e</th><th>Peso/Vol.</th><th>Situação</th><th class="col-acoes">Ações</th></tr></thead><tbody>';
                        docs.forEach(function(doc) {
                            var dataEmissao = doc.data_emissao ? new Date(doc.data_emissao).toLocaleDateString('pt-BR') : '-';
                            var numero = String(doc.numero_mdfe || '').padStart(9, '0');
                            var serie = doc.serie_mdfe || '1';
                            var ufIni = doc.uf_inicio || '-';
                            var ufFim = doc.uf_fim || '-';
                            var qtdCte = doc.total_cte != null ? doc.total_cte : (doc.qtd_cte != null ? doc.qtd_cte : '-');
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
                            html += '<td class="col-num">58</td>';
                            html += '<td>' + escapeHtml(serie) + '</td>';
                            html += '<td class="col-num">' + escapeHtml(numero) + '</td>';
                            html += '<td class="col-chave" title="' + escapeHtml(doc.chave_acesso || '') + '">' + escapeHtml(doc.chave_acesso || '-') + '</td>';
                            html += '<td>' + dataEmissao + '</td>';
                            html += '<td>' + escapeHtml(ufIni) + '</td>';
                            html += '<td>' + escapeHtml(ufFim) + '</td>';
                            html += '<td class="col-num">' + qtdCte + '</td>';
                            html += '<td>' + escapeHtml(pesoVol) + '</td>';
                            html += '<td class="situacao-' + stClass + '">' + escapeHtml(stTexto) + '</td>';
                            html += '<td class="col-acoes">';
                            html += '<a href="#" onclick="abrirModalMdfe(' + doc.id + '); return false;" title="Visualizar"><i class="fas fa-eye"></i></a>';
                            if (st === 'rascunho' || st === 'pendente') {
                                html += ' <a href="#" onclick="abrirModalEditarMdfe(' + doc.id + '); return false;" title="Editar"><i class="fas fa-edit"></i></a>';
                                html += ' <a href="#" onclick="enviarMDFESefaz(' + doc.id + '); return false;" title="Enviar SEFAZ"><i class="fas fa-paper-plane"></i></a>';
                            }
                            if (st === 'autorizado' && !encerrado) {
                                if (dentro24h) {
                                    html += ' <a href="#" onclick="cancelarMDFE(' + doc.id + '); return false;" title="Cancelar MDF-e (até 24h)"><i class="fas fa-times-circle"></i></a>';
                                }
                                html += ' <a href="#" onclick="abrirModalIncluirCondutor(' + doc.id + '); return false;" title="Incluir condutor"><i class="fas fa-user-edit"></i></a>';
                                html += ' <a href="#" onclick="encerrarMDFE(' + doc.id + '); return false;" title="Encerrar MDF-e"><i class="fas fa-stop-circle"></i></a>';
                            }
                            html += '</td></tr>';
                        });
                        html += '</tbody></table>';
                        el.innerHTML = html;
                    } else {
                        el.innerHTML = '<div style="color:#6c757d;background:#f8f9fa;padding:15px;border-radius:5px;"><strong>Nenhum MDF-e</strong><br>Clique em "Criar MDF-e" e selecione CT-e autorizados para gerar o manifesto.</div>';
                    }
                })
                .catch(function(err) {
                    console.error(err);
                    el.innerHTML = '<div style="color:#dc3545;background:#f8d7da;padding:15px;border-radius:5px;"><strong>Erro ao carregar dados.</strong></div>';
                });
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
            fetch('../api/documentos_fiscais_v2.php?action=get&tipo=mdfe&id=' + id)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success || !data.documento) {
                        body.innerHTML = '<p class="text-danger">MDF-e não encontrado.</p>';
                        return;
                    }
                    var d = data.documento;
                    var dataEmissao = d.data_emissao ? new Date(d.data_emissao).toLocaleDateString('pt-BR') : '-';
                    var st = d.status === 'autorizado' ? 'Autorizado' : d.status === 'rascunho' ? 'Rascunho' : d.status === 'pendente' ? 'Pendente' : d.status === 'encerrado' ? 'Encerrado' : d.status || '-';
                    title.innerHTML = '<i class="fas fa-route"></i> MDF-e ' + (d.numero_mdfe || id);
                    body.innerHTML = '<dl class="row mb-0">' +
                        '<dt class="col-sm-4">Número</dt><dd class="col-sm-8">' + escapeHtml(d.numero_mdfe || '-') + '</dd>' +
                        '<dt class="col-sm-4">Série</dt><dd class="col-sm-8">' + escapeHtml(d.serie_mdfe || '-') + '</dd>' +
                        '<dt class="col-sm-4">Chave</dt><dd class="col-sm-8"><code class="small">' + escapeHtml(d.chave_acesso || '-') + '</code></dd>' +
                        '<dt class="col-sm-4">Data emissão</dt><dd class="col-sm-8">' + dataEmissao + '</dd>' +
                        '<dt class="col-sm-4">UF Início / Fim</dt><dd class="col-sm-8">' + escapeHtml(d.uf_inicio || '-') + ' → ' + escapeHtml(d.uf_fim || '-') + '</dd>' +
                        '<dt class="col-sm-4">Município carregamento</dt><dd class="col-sm-8">' + escapeHtml(d.municipio_carregamento || '-') + '</dd>' +
                        '<dt class="col-sm-4">Município descarregamento</dt><dd class="col-sm-8">' + escapeHtml(d.municipio_descarregamento || '-') + '</dd>' +
                        '<dt class="col-sm-4">CT-e vinculados</dt><dd class="col-sm-8">' + (d.total_cte != null ? d.total_cte : '-') + '</dd>' +
                        '<dt class="col-sm-4">Status</dt><dd class="col-sm-8">' + escapeHtml(st) + '</dd>' +
                        '</dl>';
                })
                .catch(function() { body.innerHTML = '<p class="text-danger">Erro ao carregar dados.</p>'; });
        }

        function enviarMDFESefaz(id) {
            if (!confirm('Validar e enviar este MDF-e para a SEFAZ?')) return;
            fetch('../api/documentos_fiscais_v2.php?action=validar_mdfe&id=' + id)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success) {
                        alert('Erro: ' + (data.error || data.message || 'Erro ao validar'));
                        return;
                    }
                    if (!data.valid) {
                        mostrarErroValidacaoEOferecerEditar(id, data.message || 'MDF-e não está válido para envio.');
                        return;
                    }
                    var fd = new FormData();
                    fd.append('action', 'enviar_sefaz');
                    fd.append('id', id);
                    fd.append('tipo_documento', 'mdfe');
                    return fetch('../api/documentos_fiscais_v2.php', { method: 'POST', body: fd });
                })
                .then(function(r) { return r && r.json ? r.json() : null; })
                .then(function(data) {
                    if (!data) return;
                    if (data.success) {
                        alert('MDF-e enviado. Status: ' + data.status + (data.protocolo ? ' | Protocolo: ' + data.protocolo : ''));
                        carregarMDFE();
                    } else {
                        mostrarErroValidacaoEOferecerEditar(id, data.error || 'Erro desconhecido');
                    }
                })
                .catch(function() { alert('Erro ao enviar.'); });
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
            fetch('../api/documentos_fiscais_v2.php?action=get&tipo=mdfe&id=' + id)
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (!res.success || !res.documento) { alert('MDF-e não encontrado.'); return; }
                    var d = res.documento;
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
                    return fetch('../api/documentos_fiscais_v2.php?action=list&tipo=cte&status=autorizado&limit=100').then(function(r) { return r.json(); }).then(function(cteRes) {
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
            if (cbs.length === 0) { alert('Selecione pelo menos um CT-e.'); return; }
            var fd = new FormData(form);
            fd.append('action', 'update');
            fd.append('id', id);
            fd.append('tipo_documento', 'mdfe');
            cbs.forEach(function(cb) { fd.append('cte_ids[]', cb.value); });
            var btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
            fetch('../api/documentos_fiscais_v2.php', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('editarMDFEModal')).hide();
                        carregarMDFE();
                    } else {
                        alert('Erro: ' + (data.error || data.message || ''));
                    }
                })
                .catch(function() { alert('Erro ao salvar.'); })
                .finally(function() { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Salvar'; });
        };

        function encerrarMDFE(id) {
            if (!confirm('Encerrar este MDF-e? Após encerrado, a viagem fica finalizada e não é possível cancelar.')) return;
            var fd = new FormData();
            fd.append('action', 'encerrar_mdfe');
            fd.append('id', id);
            fetch('../api/documentos_fiscais_v2.php', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        alert(data.message || 'MDF-e encerrado.');
                        carregarMDFE();
                    } else {
                        alert('Erro: ' + (data.error || data.message || ''));
                    }
                })
                .catch(function() { alert('Erro ao encerrar.'); });
        }

        function cancelarMDFE(id) {
            if (!confirm('Cancelar este MDF-e? Só é permitido antes da viagem e dentro de 24h da autorização. Esta ação não pode ser desfeita.')) return;
            var fd = new FormData();
            fd.append('action', 'cancelar_mdfe');
            fd.append('id', id);
            fetch('../api/documentos_fiscais_v2.php', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        alert(data.message || 'MDF-e cancelado.');
                        carregarMDFE();
                    } else {
                        alert('Erro: ' + (data.error || data.message || ''));
                    }
                })
                .catch(function() { alert('Erro ao cancelar.'); });
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
            if (!id || !motoristaId) { alert('Selecione o novo condutor.'); return; }
            var fd = new FormData();
            fd.append('action', 'incluir_condutor_mdfe');
            fd.append('id', id);
            fd.append('motorista_id', motoristaId);
            fetch('../api/documentos_fiscais_v2.php', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('modalIncluirCondutor')).hide();
                        alert(data.message || 'Condutor incluído.');
                        carregarMDFE();
                    } else {
                        alert('Erro: ' + (data.error || data.message || ''));
                    }
                })
                .catch(function() { alert('Erro ao incluir condutor.'); });
        }

        function exportarDados() {
            alert('Exportação em desenvolvimento.');
        }
    </script>
    <?php include '../../includes/footer.php'; ?>
</body>
</html>
