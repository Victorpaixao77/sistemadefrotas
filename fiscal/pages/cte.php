<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include configuration and functions first
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Configure session before starting it
configure_session();

// Start session
session_start();

// Check authentication
require_authentication();

// Set page title
$page_title = "Gestão de CT-e";
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="../../css/theme.css">
    <link rel="stylesheet" href="../../css/responsive.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../logo.png">
    
    <!-- Bootstrap 5 CSS (para modais) - mesmo que DataTables usa -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    
    <style>
        .cte-table-wrap { overflow-x: auto; }
        .cte-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .cte-table th, .cte-table td {
            border: 1px solid var(--border-color);
            padding: 8px 10px;
            text-align: left;
        }
        .cte-table th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-secondary);
        }
        .cte-table tbody tr:hover { background: #dee2e6; }
        .cte-table .col-num { text-align: right; }
        .cte-table .col-vlr { text-align: right; white-space: nowrap; }
        .cte-table .col-chave { font-family: monospace; font-size: 0.75rem; max-width: 280px; overflow: hidden; text-overflow: ellipsis; }
        .cte-table .col-acoes { white-space: nowrap; }
        .cte-table .col-acoes a {
            padding: 4px 8px;
            margin: 0 2px;
            color: var(--text-secondary);
            text-decoration: none;
        }
        .cte-table .col-acoes a:hover { color: var(--primary-color); }
        .cte-table .situacao-autorizado { color: #155724; font-weight: 500; }
        .cte-table .situacao-pendente { color: #856404; }
        .cte-table .situacao-cancelada, .cte-table .situacao-denegado { color: #721c24; }
        .document-list {
            background: var(--bg-secondary);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 20px;
        }
        #modalVisualizarCTeBody dl dt { font-weight: 600; color: #6c757d; }
        #modalVisualizarCTeBody dl dd { margin-bottom: 0.5rem; }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include '../../includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include '../../includes/header.php'; ?>
            
            <!-- Page Content -->
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1><?php echo $page_title; ?></h1>
                    <div class="dashboard-actions">
                        <button id="consultarCTeBtn" class="btn-add-widget" onclick="abrirModalConsultarCTe()">
                            <i class="fas fa-search"></i> Consultar CT-e
                        </button>
                        <button id="importarXmlCteBtn" class="btn-add-widget" type="button" onclick="abrirModalImportarXmlCte()" title="Colar o XML do CT-e obtido na SEFAZ para preencher dados corretos (valor, tomador, origem/destino)">
                            <i class="fas fa-file-upload"></i> Importar XML
                        </button>
                        <div class="view-controls">
                            <button id="refreshBtn" class="btn-restore-layout" title="Atualizar lista" onclick="carregarCTE()">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button id="exportBtn" class="btn-toggle-layout" title="Exportar" onclick="exportarDados()">
                                <i class="fas fa-file-export"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Lista de CT-e -->
                <div class="document-list cte-table-wrap" style="margin-top: 20px;">
                    <h3>CT-e</h3>
                    <div id="cteList">
                        <p>Carregando CT-e...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Visualizar CT-e -->
    <div class="modal fade" id="modalVisualizarCTe" tabindex="-1" aria-labelledby="modalVisualizarCTeLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalVisualizarCTeLabel"><i class="fas fa-file-alt"></i> CT-e</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body" id="modalVisualizarCTeBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Consultar CT-e -->
    <div class="modal fade" id="consultarCTeModal" tabindex="-1" aria-labelledby="consultarCTeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="consultarCTeModalLabel">Consultar CT-e</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Informe a chave de acesso do CT-e (44 dígitos). O sistema consulta a SEFAZ e tenta baixar o XML completo (como na NF-e); quando possível, preenche valor, tomador, origem/destino e grava em <strong>fiscal_cte</strong> e <strong>fiscal_cte_itens</strong>.</p>
                    <div class="mb-3">
                        <label for="cteChaveAcesso" class="form-label">Chave de acesso</label>
                        <input type="text" class="form-control font-monospace" id="cteChaveAcesso" placeholder="44 dígitos" maxlength="44" pattern="[0-9]{44}" title="Apenas números, 44 dígitos">
                    </div>
                    <div id="consultarCTeStatus" style="display: none;" class="alert mb-0"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnConsultarCTe" onclick="consultarCTeSefaz()">
                        <i class="fas fa-search"></i> Consultar
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Importar XML do CT-e -->
    <div class="modal fade" id="modalImportarXmlCte" tabindex="-1" aria-labelledby="modalImportarXmlCteLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalImportarXmlCteLabel"><i class="fas fa-file-upload"></i> Importar XML do CT-e</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Cole aqui o XML do CT-e (cteProc) obtido no portal da SEFAZ ou na consulta por chave. O sistema irá preencher valor do frete, tomador, origem/destino e gravar em <strong>fiscal_cte</strong> e <strong>fiscal_cte_itens</strong>. Depois o download de XML e PDF usará os dados corretos.</p>
                    <p class="text-muted small"><strong>Debug:</strong> Consulte o arquivo <code>fiscal/logs/cte_debug.log</code> para ver o que a consulta SEFAZ, o download (Distribuição DFe) e a importação registraram (cStat, valor, tomador, origem/destino).</p>
                    <div class="mb-3">
                        <label for="cteXmlContent" class="form-label">Conteúdo do XML (cteProc)</label>
                        <textarea class="form-control font-monospace" id="cteXmlContent" rows="12" placeholder="&lt;?xml version=&quot;1.0&quot;?>&#10;&lt;cteProc xmlns=&quot;http://www.portalfiscal.inf.br/cte&quot; versao=&quot;3.00&quot;>..."></textarea>
                    </div>
                    <div id="importarXmlCteStatus" style="display: none;" class="alert mb-0"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnImportarXmlCte" onclick="importarXmlCte()">
                        <i class="fas fa-upload"></i> Importar
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Files -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Sistema JavaScript -->
    <script src="../../js/sidebar.js"></script>
    <script src="../../js/theme.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            carregarCTE();
        });

        function abrirModalConsultarCTe() {
            document.getElementById('cteChaveAcesso').value = '';
            document.getElementById('consultarCTeStatus').style.display = 'none';
            new bootstrap.Modal(document.getElementById('consultarCTeModal')).show();
        }

        function abrirModalImportarXmlCte() {
            document.getElementById('cteXmlContent').value = '';
            document.getElementById('importarXmlCteStatus').style.display = 'none';
            new bootstrap.Modal(document.getElementById('modalImportarXmlCte')).show();
        }

        function importarXmlCte() {
            var xml = document.getElementById('cteXmlContent').value.trim();
            if (!xml) {
                alert('Cole o conteúdo do XML do CT-e.');
                return;
            }
            var btn = document.getElementById('btnImportarXmlCte');
            var statusEl = document.getElementById('importarXmlCteStatus');
            var origText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importando...';
            btn.disabled = true;
            statusEl.style.display = 'none';
            var formData = new FormData();
            formData.append('action', 'importar_xml_cte');
            formData.append('xml_content', xml);
            fetch('../api/documentos_fiscais_v2.php', { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        statusEl.className = 'alert alert-success mb-0';
                        statusEl.textContent = data.message || 'XML importado com sucesso.';
                        statusEl.style.display = 'block';
                        var modal = bootstrap.Modal.getInstance(document.getElementById('modalImportarXmlCte'));
                        setTimeout(function() {
                            if (modal) modal.hide();
                            carregarCTE();
                        }, 1200);
                    } else {
                        statusEl.className = 'alert alert-danger mb-0';
                        statusEl.textContent = data.error || data.message || 'Erro ao importar.';
                        statusEl.style.display = 'block';
                    }
                })
                .catch(function() {
                    statusEl.className = 'alert alert-danger mb-0';
                    statusEl.textContent = 'Erro de conexão.';
                    statusEl.style.display = 'block';
                })
                .finally(function() {
                    btn.innerHTML = origText;
                    btn.disabled = false;
                });
        }

        function consultarCTeSefaz() {
            var chave = document.getElementById('cteChaveAcesso').value.replace(/\D/g, '');
            if (chave.length !== 44) {
                alert('Informe a chave de acesso com 44 dígitos.');
                return;
            }
            var btn = document.getElementById('btnConsultarCTe');
            var statusEl = document.getElementById('consultarCTeStatus');
            var origText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Consultando...';
            btn.disabled = true;
            statusEl.style.display = 'none';
            var formData = new FormData();
            formData.append('action', 'consultar_cte_sefaz');
            formData.append('chave_acesso', chave);
            fetch('../api/documentos_fiscais_v2.php', { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        statusEl.className = 'alert alert-success mb-0';
                        statusEl.textContent = data.message || 'CT-e registrado.';
                        statusEl.style.display = 'block';
                        var modal = bootstrap.Modal.getInstance(document.getElementById('consultarCTeModal'));
                        setTimeout(function() {
                            modal.hide();
                            carregarCTE();
                        }, 1500);
                    } else {
                        statusEl.className = 'alert alert-danger mb-0';
                        statusEl.textContent = data.error || data.message || 'Erro ao consultar.';
                        statusEl.style.display = 'block';
                    }
                })
                .catch(function() {
                    statusEl.className = 'alert alert-danger mb-0';
                    statusEl.textContent = 'Erro de conexão.';
                    statusEl.style.display = 'block';
                })
                .finally(function() {
                    btn.innerHTML = origText;
                    btn.disabled = false;
                });
        }

        function carregarCTE() {
            var msg = document.getElementById('cteList');
            msg.innerHTML = '<div style="color: #17a2b8; background: #d1ecf1; padding: 15px; border-radius: 5px;"><strong>Carregando CT-e...</strong></div>';
            fetch('../api/documentos_fiscais_v2.php?action=list&tipo=cte&limit=100')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.documentos && data.documentos.length > 0) {
                        var docs = data.documentos;
                        var html = '<table class="cte-table"><thead><tr>';
                        html += '<th>Mod</th><th>Série</th><th>Número</th><th>Chave de Acesso</th><th>Emissão</th>';
                        html += '<th>Origem / Destino</th><th>Vlr Frete</th><th>Situação</th><th class="col-acoes">Ações</th></tr></thead><tbody>';
                        docs.forEach(function(doc) {
                            var dataEmissao = doc.data_emissao ? new Date(doc.data_emissao).toLocaleDateString('pt-BR') : '-';
                            var numero = String(doc.numero_cte || '').padStart(9, '0');
                            var serie = doc.serie_cte || '-';
                            var vlr = parseFloat(doc.valor_total || 0).toFixed(2).replace('.', ',');
                            var origem = doc.origem || doc.origem_cidade || '';
                            var destino = doc.destino || doc.destino_cidade || '';
                            var origemDestino = (origem && destino) ? origem + ' → ' + destino : (origem || destino || 'N/A');
                            var status = doc.status || 'pendente';
                            var situacaoTexto = status === 'autorizado' ? 'Autorizado' : status === 'pendente' ? 'Pendente' : status === 'cancelado' ? 'Cancelado' : status === 'denegado' ? 'Denegado' : status === 'inutilizado' ? 'Inutilizado' : status;
                            var situacaoClass = (status || 'pendente').replace(/_/g, '-');
                            html += '<tr>';
                            html += '<td class="col-num">57</td>';
                            html += '<td>' + escapeHtml(serie) + '</td>';
                            html += '<td class="col-num">' + escapeHtml(numero) + '</td>';
                            html += '<td class="col-chave" title="' + escapeHtml(doc.chave_acesso || '') + '">' + escapeHtml(doc.chave_acesso || '-') + '</td>';
                            html += '<td>' + dataEmissao + '</td>';
                            html += '<td>' + escapeHtml(origemDestino) + '</td>';
                            html += '<td class="col-vlr">R$ ' + vlr + '</td>';
                            html += '<td class="situacao-' + situacaoClass + '">' + escapeHtml(situacaoTexto) + '</td>';
                            html += '<td class="col-acoes">';
                            html += '<a href="#" onclick="abrirModalCTe(' + doc.id + '); return false;" title="Visualizar"><i class="fas fa-eye"></i></a>';
                            html += ' <a href="#" onclick="downloadCteXml(' + doc.id + '); return false;" title="Gerar / Download XML"><i class="fas fa-file-code"></i></a>';
                            html += ' <a href="#" onclick="downloadCtePdf(' + doc.id + '); return false;" title="Gerar / Download PDF"><i class="fas fa-file-pdf"></i></a>';
                            html += '</td></tr>';
                        });
                        html += '</tbody></table>';
                        msg.innerHTML = html;
                    } else {
                        msg.innerHTML = '<div style="color: #6c757d; background: #f8f9fa; padding: 15px; border-radius: 5px;"><strong>Nenhum CT-e</strong><br>Clique em "Consultar CT-e" para registrar pela chave de acesso.</div>';
                    }
                })
                .catch(function(err) {
                    console.error('Erro ao carregar CT-e:', err);
                    msg.innerHTML = '<div style="color: #dc3545; background: #f8d7da; padding: 15px; border-radius: 5px;"><strong>Erro ao carregar dados.</strong></div>';
                });
        }

        function escapeHtml(s) {
            if (s == null) return '';
            var div = document.createElement('div');
            div.textContent = s;
            return div.innerHTML;
        }

        function abrirModalCTe(id) {
            var modal = new bootstrap.Modal(document.getElementById('modalVisualizarCTe'));
            var body = document.getElementById('modalVisualizarCTeBody');
            var title = document.getElementById('modalVisualizarCTeLabel');
            body.innerHTML = '<p class="text-muted">Carregando...</p>';
            modal.show();
            fetch('../api/documentos_fiscais_v2.php?action=get&tipo=cte&id=' + id)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success || !data.documento) {
                        body.innerHTML = '<p class="text-danger">CT-e não encontrado.</p>';
                        return;
                    }
                    var d = data.documento;
                    var dataEmissao = d.data_emissao ? new Date(d.data_emissao).toLocaleDateString('pt-BR') : '-';
                    var vlr = parseFloat(d.valor_total || 0).toFixed(2).replace('.', ',');
                    var origem = d.origem || d.origem_cidade || '-';
                    var destino = d.destino || d.destino_cidade || '-';
                    var situacao = (d.status === 'autorizado' ? 'Autorizado' : d.status === 'pendente' ? 'Pendente' : d.status === 'cancelado' ? 'Cancelado' : d.status === 'denegado' ? 'Denegado' : d.status || '-');
                    title.innerHTML = '<i class="fas fa-file-alt"></i> CT-e ' + (d.numero_cte || id);
                    body.innerHTML = '<dl class="row mb-0">' +
                        '<dt class="col-sm-4">Número</dt><dd class="col-sm-8">' + escapeHtml(d.numero_cte || '-') + '</dd>' +
                        '<dt class="col-sm-4">Série</dt><dd class="col-sm-8">' + escapeHtml(d.serie_cte || '-') + '</dd>' +
                        '<dt class="col-sm-4">Chave de acesso</dt><dd class="col-sm-8"><code class="small">' + escapeHtml(d.chave_acesso || '-') + '</code></dd>' +
                        '<dt class="col-sm-4">Data de emissão</dt><dd class="col-sm-8">' + dataEmissao + '</dd>' +
                        '<dt class="col-sm-4">Origem</dt><dd class="col-sm-8">' + escapeHtml(origem) + '</dd>' +
                        '<dt class="col-sm-4">Destino</dt><dd class="col-sm-8">' + escapeHtml(destino) + '</dd>' +
                        '<dt class="col-sm-4">Valor do frete</dt><dd class="col-sm-8">R$ ' + vlr + '</dd>' +
                        '<dt class="col-sm-4">Status</dt><dd class="col-sm-8">' + escapeHtml(situacao) + '</dd>' +
                        '</dl>';
                })
                .catch(function() {
                    body.innerHTML = '<p class="text-danger">Erro ao carregar dados.</p>';
                });
        }

        function visualizarCTE(id) {
            abrirModalCTe(id);
        }

        function exportarDados() {
            alert('Exportação em desenvolvimento.');
        }

        function downloadCteXml(id) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '../api/download_cte_xml.php';
            form.target = '_blank';
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'id';
            input.value = id;
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        function downloadCtePdf(id) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '../api/download_cte_pdf.php';
            form.target = '_blank';
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'id';
            input.value = id;
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
    </script>
    
    <!-- Footer -->
    <?php include '../../includes/footer.php'; ?>
</body>
</html>
