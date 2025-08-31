<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Iniciar sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o usuário está logado (mas não redirecionar)
$usuario_logado = isLoggedIn();
$empresa_id = $_SESSION['empresa_id'] ?? 1;

// Se não estiver logado, mostrar aviso mas permitir acesso
if (!$usuario_logado) {
    echo "<div class='alert alert-warning' style='margin: 20px;'>";
    echo "<i class='fas fa-exclamation-triangle'></i> ";
    echo "<strong>Atenção:</strong> Você não está logado no sistema. ";
    echo "Algumas funcionalidades podem não funcionar corretamente. ";
    echo "<a href='../index.php' class='alert-link'>Fazer Login</a>";
    echo "</div>";
}

$conn = getConnection();

// Buscar configurações fiscais
$stmt = $conn->prepare("SELECT * FROM fiscal_config_empresa WHERE empresa_id = ?");
$stmt->execute([$empresa_id]);
$config_fiscal = $stmt->fetch(PDO::FETCH_ASSOC);

// Buscar certificado digital
$stmt = $conn->prepare("SELECT * FROM fiscal_certificados_digitais WHERE empresa_id = ? AND ativo = 1 ORDER BY data_vencimento DESC LIMIT 1");
$stmt->execute([$empresa_id]);
$certificado = $stmt->fetch(PDO::FETCH_ASSOC);

$page_title = "Validação SEFAZ";
include '../includes/header.php';
?>

<div class="app-container">
    <?php include '../includes/sidebar_pages.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="page-title-box">
                            <h4 class="page-title">
                                <i class="fas fa-check-circle text-success"></i>
                                Validação da Conexão SEFAZ
                            </h4>
                        </div>
                    </div>
                </div>

                <!-- Status Geral -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="fas fa-info-circle"></i>
                                    Status Geral do Sistema Fiscal
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="alert <?= $config_fiscal ? 'alert-success' : 'alert-warning' ?>">
                                            <strong>Ambiente:</strong> 
                                            <?= $config_fiscal ? ucfirst($config_fiscal['ambiente_sefaz']) : 'Não configurado' ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="alert <?= $certificado ? 'alert-success' : 'alert-danger' ?>">
                                            <strong>Certificado Digital:</strong> 
                                            <?= $certificado ? 'Ativo' : 'Não encontrado' ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Validação do Certificado -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="fas fa-certificate"></i>
                                    Validação do Certificado Digital A1
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if ($certificado): ?>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <table class="table table-bordered">
                                                <tr>
                                                    <th>Nome do Certificado:</th>
                                                    <td><?= htmlspecialchars($certificado['nome_certificado']) ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Tipo:</th>
                                                    <td><?= htmlspecialchars($certificado['tipo_certificado']) ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Emissor:</th>
                                                    <td><?= htmlspecialchars($certificado['emissor']) ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Data de Emissão:</th>
                                                    <td><?= date('d/m/Y', strtotime($certificado['data_emissao'])) ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Data de Vencimento:</th>
                                                    <td>
                                                        <span class="badge <?= strtotime($certificado['data_vencimento']) > time() ? 'bg-success' : 'bg-danger' ?>">
                                                            <?= date('d/m/Y', strtotime($certificado['data_vencimento'])) ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th>CNPJ Proprietário:</th>
                                                    <td><?= htmlspecialchars($certificado['cnpj_proprietario']) ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Status:</th>
                                                    <td>
                                                        <span class="badge <?= $certificado['ativo'] ? 'bg-success' : 'bg-danger' ?>">
                                                            <?= $certificado['ativo'] ? 'Ativo' : 'Inativo' ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="text-center">
                                                <button type="button" class="btn btn-primary btn-lg mb-3" onclick="validarCertificado()">
                                                    <i class="fas fa-check"></i>
                                                    Validar Certificado
                                                </button>
                                                <div id="resultado-certificado"></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Nenhum certificado digital ativo encontrado. 
                                        <a href="../pages/configuracoes.php" class="alert-link">Configure um certificado A1</a>.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Teste de Conexão SEFAZ -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="fas fa-network-wired"></i>
                                    Teste de Conexão com SEFAZ
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Serviços Disponíveis para Teste:</h6>
                                        <ul class="list-group">
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Status dos Serviços
                                                <button class="btn btn-sm btn-outline-primary" onclick="testarStatusServicos()">
                                                    Testar
                                                </button>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Consulta de NF-e
                                                <button class="btn btn-sm btn-outline-primary" onclick="testarConsultaNFe()">
                                                    Testar
                                                </button>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Consulta de CT-e
                                                <button class="btn btn-sm btn-outline-primary" onclick="testarConsultaCTe()">
                                                    Testar
                                                </button>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Consulta de MDF-e
                                                <button class="btn btn-sm btn-outline-primary" onclick="testarConsultaMDFe()">
                                                    Testar
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Resultados dos Testes:</h6>
                                        <div id="resultados-testes" class="border p-3" style="min-height: 200px;">
                                            <p class="text-muted text-center">
                                                Clique em um teste para ver os resultados
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Log de Validações -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">
                                    <i class="fas fa-list-alt"></i>
                                    Log de Validações
                                </h5>
                            </div>
                            <div class="card-body">
                                <div id="log-validacoes" class="border p-3" style="max-height: 300px; overflow-y: auto;">
                                    <p class="text-muted text-center">Nenhuma validação realizada ainda</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Função para validar certificado
function validarCertificado() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validando...';
    btn.disabled = true;
    
    fetch('../fiscal/api/validar_sefaz.php')
        .then(response => response.json())
        .then(data => {
            const resultado = document.getElementById('resultado-certificado');
            
            if (data.success) {
                const certificado = data.certificado;
                const conexao = data.conexao_sefaz;
                
                let alertClass = 'alert-success';
                let icon = 'check-circle';
                let titulo = 'Certificado Válido!';
                
                if (!certificado.valido) {
                    alertClass = 'alert-danger';
                    icon = 'times-circle';
                    titulo = 'Certificado com Problemas!';
                } elseif (certificado.detalhes.avisos.length > 0) {
                    alertClass = 'alert-warning';
                    icon = 'exclamation-triangle';
                    titulo = 'Certificado Válido com Avisos!';
                }
                
                let avisosHtml = '';
                if (certificado.detalhes.avisos.length > 0) {
                    avisosHtml = '<br><strong>Avisos:</strong><ul>' + 
                        certificado.detalhes.avisos.map(aviso => '<li>' + aviso + '</li>').join('') + 
                        '</ul>';
                }
                
                let errosHtml = '';
                if (certificado.detalhes.erros.length > 0) {
                    errosHtml = '<br><strong>Erros:</strong><ul>' + 
                        certificado.detalhes.erros.map(erro => '<li>' + erro + '</li>').join('') + 
                        '</ul>';
                }
                
                resultado.innerHTML = 
                    '<div class="alert ' + alertClass + '">' +
                        '<i class="fas fa-' + icon + '"></i>' +
                        '<strong>' + titulo + '</strong><br>' +
                        '<strong>Status:</strong> ' + certificado.detalhes.status + '<br>' +
                        '<strong>Ambiente SEFAZ:</strong> ' + conexao.ambiente + '<br>' +
                        '<strong>Status SEFAZ:</strong> ' + conexao.status_geral.replace('_', ' ') +
                        avisosHtml + errosHtml +
                    '</div>';
                
                adicionarLog('Certificado digital validado: ' + certificado.detalhes.status, certificado.valido ? 'success' : 'error');
                atualizarStatusGeral(data);
                
            } else {
                resultado.innerHTML = 
                    '<div class="alert alert-danger">' +
                        '<i class="fas fa-times-circle"></i>' +
                        '<strong>Erro na Validação!</strong><br>' +
                        data.error +
                    '</div>';
                
                adicionarLog('Erro na validação: ' + data.error, 'error');
            }
        })
        .catch(error => {
            const resultado = document.getElementById('resultado-certificado');
            resultado.innerHTML = 
                '<div class="alert alert-danger">' +
                    '<i class="fas fa-times-circle"></i>' +
                    '<strong>Erro de Conexão!</strong><br>' +
                    'Não foi possível conectar com o servidor de validação.' +
                '</div>';
            
            adicionarLog('Erro de conexão: ' + error.message, 'error');
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
}

// Função para testar status dos serviços
function testarStatusServicos() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testando...';
    btn.disabled = true;
    
    fetch('../fiscal/api/validar_sefaz.php')
        .then(response => response.json())
        .then(data => {
            const resultados = document.getElementById('resultados-testes');
            
            if (data.success) {
                const conexao = data.conexao_sefaz;
                let statusClass = 'alert-success';
                let statusText = 'Online';
                
                if (conexao.status_geral === 'todos_offline') {
                    statusClass = 'alert-danger';
                    statusText = 'Offline';
                } else if (conexao.status_geral === 'parcial') {
                    statusClass = 'alert-warning';
                    statusText = 'Parcial';
                }
                
                let servicosHtml = '';
                Object.keys(conexao.servicos).forEach(function(servico) {
                    const resultado = conexao.servicos[servico];
                    const servicoNome = servico.toUpperCase();
                    const status = resultado.status === 'online' ? '✅' : '❌';
                    servicosHtml += '<p><strong>' + servicoNome + ':</strong> ' + status + ' ' + resultado.mensagem + ' (' + resultado.tempo + 'ms)</p>';
                });
                
                resultados.innerHTML = 
                    '<div class="alert ' + statusClass + '">' +
                        '<h6><i class="fas fa-info-circle"></i> Status dos Serviços SEFAZ</h6>' +
                        '<hr>' +
                        '<p><strong>Ambiente:</strong> ' + conexao.ambiente + '</p>' +
                        '<p><strong>Status Geral:</strong> <span class="badge bg-' + (statusClass === 'alert-success' ? 'success' : statusClass === 'alert-warning' ? 'warning' : 'danger') + '">' + statusText + '</span></p>' +
                        '<p><strong>Última Verificação:</strong> ' + new Date().toLocaleString('pt-BR') + '</p>' +
                        '<hr>' +
                        '<h6>Detalhes dos Serviços:</h6>' +
                        servicosHtml +
                    '</div>';
                
                adicionarLog('Status SEFAZ verificado: ' + conexao.status_geral, statusClass === 'alert-success' ? 'success' : 'warning');
            } else {
                resultados.innerHTML = 
                    '<div class="alert alert-danger">' +
                        '<h6><i class="fas fa-times-circle"></i> Erro ao verificar SEFAZ</h6>' +
                        '<hr>' +
                        '<p>' + data.error + '</p>' +
                    '</div>';
                
                adicionarLog('Erro ao verificar SEFAZ: ' + data.error, 'error');
            }
        })
        .catch(error => {
            const resultados = document.getElementById('resultados-testes');
            resultados.innerHTML = 
                '<div class="alert alert-danger">' +
                    '<h6><i class="fas fa-times-circle"></i> Erro de Conexão</h6>' +
                    '<hr>' +
                    '<p>Não foi possível conectar com o servidor de validação.</p>' +
                '</div>';
            
            adicionarLog('Erro de conexão: ' + error.message, 'error');
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
}

// Função para testar consulta de NF-e
function testarConsultaNFe() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testando...';
    btn.disabled = true;
    
    fetch('../fiscal/api/validar_sefaz.php')
        .then(response => response.json())
        .then(data => {
            const resultados = document.getElementById('resultados-testes');
            
            if (data.success) {
                const nfe = data.conexao_sefaz.servicos.nfe;
                const statusClass = nfe.status === 'online' ? 'alert-success' : 'alert-danger';
                const statusIcon = nfe.status === 'online' ? 'check-circle' : 'times-circle';
                const statusText = nfe.status === 'online' ? 'Funcionando' : 'Erro';
                
                resultados.innerHTML = 
                    '<div class="alert ' + statusClass + '">' +
                        '<h6><i class="fas fa-' + statusIcon + '"></i> Consulta NF-e</h6>' +
                        '<hr>' +
                        '<p><strong>Status:</strong> <span class="badge bg-' + (nfe.status === 'online' ? 'success' : 'danger') + '">' + statusText + '</span></p>' +
                        '<p><strong>Serviço:</strong> NFeConsultaProtocolo4</p>' +
                        '<p><strong>Resposta:</strong> ' + nfe.mensagem + '</p>' +
                        '<p><strong>Tempo:</strong> ' + nfe.tempo + 'ms</p>' +
                        '<p><strong>HTTP Code:</strong> ' + (nfe.http_code || 'N/A') + '</p>' +
                    '</div>';
                
                adicionarLog('Consulta NF-e: ' + nfe.status + ' - ' + nfe.mensagem, nfe.status === 'online' ? 'success' : 'error');
            } else {
                resultados.innerHTML = 
                    '<div class="alert alert-danger">' +
                        '<h6><i class="fas fa-times-circle"></i> Erro ao testar NF-e</h6>' +
                        '<hr>' +
                        '<p>' + data.error + '</p>' +
                    '</div>';
                
                adicionarLog('Erro ao testar NF-e: ' + data.error, 'error');
            }
        })
        .catch(error => {
            const resultados = document.getElementById('resultados-testes');
            resultados.innerHTML = 
                '<div class="alert alert-danger">' +
                    '<h6><i class="fas fa-times-circle"></i> Erro de Conexão</h6>' +
                    '<hr>' +
                    '<p>Não foi possível conectar com o servidor.</p>' +
                '</div>';
            
            adicionarLog('Erro de conexão NF-e: ' + error.message, 'error');
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
}

// Função para testar consulta de CT-e
function testarConsultaCTe() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testando...';
    btn.disabled = true;
    
    fetch('../fiscal/api/validar_sefaz.php')
        .then(response => response.json())
        .then(data => {
            const resultados = document.getElementById('resultados-testes');
            
            if (data.success) {
                const cte = data.conexao_sefaz.servicos.cte;
                const statusClass = cte.status === 'online' ? 'alert-success' : 'alert-danger';
                const statusIcon = cte.status === 'online' ? 'check-circle' : 'times-circle';
                const statusText = cte.status === 'online' ? 'Funcionando' : 'Erro';
                
                resultados.innerHTML = 
                    '<div class="alert ' + statusClass + '">' +
                        '<h6><i class="fas fa-' + statusIcon + '"></i> Consulta CT-e</h6>' +
                        '<hr>' +
                        '<p><strong>Status:</strong> <span class="badge bg-' + (cte.status === 'online' ? 'success' : 'danger') + '">' + statusText + '</span></p>' +
                        '<p><strong>Serviço:</strong> CTeConsultaProtocolo</p>' +
                        '<p><strong>Resposta:</strong> ' + cte.mensagem + '</p>' +
                        '<p><strong>Tempo:</strong> ' + cte.tempo + 'ms</p>' +
                        '<p><strong>HTTP Code:</strong> ' + (cte.http_code || 'N/A') + '</p>' +
                    '</div>';
                
                adicionarLog('Consulta CT-e: ' + cte.status + ' - ' + cte.mensagem, cte.status === 'online' ? 'success' : 'error');
            } else {
                resultados.innerHTML = 
                    '<div class="alert alert-danger">' +
                        '<h6><i class="fas fa-times-circle"></i> Erro ao testar CT-e</h6>' +
                        '<hr>' +
                        '<p>' + data.error + '</p>' +
                    '</div>';
                
                adicionarLog('Erro ao testar CT-e: ' + data.error, 'error');
            }
        })
        .catch(error => {
            const resultados = document.getElementById('resultados-testes');
            resultados.innerHTML = 
                '<div class="alert alert-danger">' +
                    '<h6><i class="fas fa-times-circle"></i> Erro de Conexão</h6>' +
                    '<hr>' +
                    '<p>Não foi possível conectar com o servidor.</p>' +
                '</div>';
            
            adicionarLog('Erro de conexão CT-e: ' + error.message, 'error');
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
}

// Função para testar consulta de MDF-e
function testarConsultaMDFe() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testando...';
    btn.disabled = true;
    
    fetch('../fiscal/api/validar_sefaz.php')
        .then(response => response.json())
        .then(data => {
            const resultados = document.getElementById('resultados-testes');
            
            if (data.success) {
                const mdfe = data.conexao_sefaz.servicos.mdfe;
                const statusClass = mdfe.status === 'online' ? 'alert-success' : 'alert-danger';
                const statusIcon = mdfe.status === 'online' ? 'check-circle' : 'times-circle';
                const statusText = mdfe.status === 'online' ? 'Funcionando' : 'Erro';
                
                resultados.innerHTML = 
                    '<div class="alert ' + statusClass + '">' +
                        '<h6><i class="fas fa-' + statusIcon + '"></i> Consulta MDF-e</h6>' +
                        '<hr>' +
                        '<p><strong>Status:</strong> <span class="badge bg-' + (mdfe.status === 'online' ? 'success' : 'danger') + '">' + statusText + '</span></p>' +
                        '<p><strong>Serviço:</strong> MDFeConsulta</p>' +
                        '<p><strong>Resposta:</strong> ' + mdfe.mensagem + '</p>' +
                        '<p><strong>Tempo:</strong> ' + mdfe.tempo + 'ms</p>' +
                        '<p><strong>HTTP Code:</strong> ' + (mdfe.http_code || 'N/A') + '</p>' +
                    '</div>';
                
                adicionarLog('Consulta MDF-e: ' + mdfe.status + ' - ' + mdfe.mensagem, mdfe.status === 'online' ? 'success' : 'error');
            } else {
                resultados.innerHTML = 
                    '<div class="alert alert-danger">' +
                        '<h6><i class="fas fa-times-circle"></i> Erro ao testar MDF-e</h6>' +
                        '<hr>' +
                        '<p>' + data.error + '</p>' +
                    '</div>';
                
                adicionarLog('Erro ao testar MDF-e: ' + data.error, 'error');
            }
        })
        .catch(error => {
            const resultados = document.getElementById('resultados-testes');
            resultados.innerHTML = 
                '<div class="alert alert-danger">' +
                    '<h6><i class="fas fa-times-circle"></i> Erro de Conexão</h6>' +
                    '<hr>' +
                    '<p>Não foi possível conectar com o servidor.</p>' +
                '</div>';
            
            adicionarLog('Erro de conexão MDF-e: ' + error.message, 'error');
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
}

// Função para atualizar status geral
function atualizarStatusGeral(data) {
    const certificado = data.certificado;
    const conexao = data.conexao_sefaz;
    
    // Atualizar status do certificado
    const statusCertificado = document.querySelector('.alert:has(strong:contains("Certificado Digital"))');
    if (statusCertificado) {
        if (certificado.valido) {
            statusCertificado.className = 'alert alert-success';
            statusCertificado.innerHTML = '<strong>Certificado Digital:</strong> Ativo e Válido';
        } else {
            statusCertificado.className = 'alert alert-danger';
            statusCertificado.innerHTML = '<strong>Certificado Digital:</strong> Com Problemas';
        }
    }
    
    // Atualizar status do ambiente
    const statusAmbiente = document.querySelector('.alert:has(strong:contains("Ambiente"))');
    if (statusAmbiente) {
        statusAmbiente.innerHTML = '<strong>Ambiente:</strong> ' + conexao.ambiente.charAt(0).toUpperCase() + conexao.ambiente.slice(1);
    }
}

// Função para adicionar log
function adicionarLog(mensagem, tipo) {
    if (!tipo) tipo = 'info';
    
    const log = document.getElementById('log-validacoes');
    const timestamp = new Date().toLocaleString('pt-BR');
    
    let icon = 'info-circle';
    let alertClass = 'alert-info';
    
    switch(tipo) {
        case 'success':
            icon = 'check-circle';
            alertClass = 'alert-success';
            break;
        case 'warning':
            icon = 'exclamation-triangle';
            alertClass = 'alert-warning';
            break;
        case 'error':
            icon = 'times-circle';
            alertClass = 'alert-danger';
            break;
    }
    
    const logEntry = document.createElement('div');
    logEntry.className = 'alert ' + alertClass + ' alert-sm mb-2';
    logEntry.innerHTML = 
        '<i class="fas fa-' + icon + '"></i>' +
        '<strong>' + timestamp + ':</strong> ' + mensagem;
    
    if (log.children.length === 1 && log.children[0].classList.contains('text-muted')) {
        log.innerHTML = '';
    }
    
    log.insertBefore(logEntry, log.firstChild);
    
    // Manter apenas os últimos 10 logs
    const logs = log.querySelectorAll('.alert');
    if (logs.length > 10) {
        logs[logs.length - 1].remove();
    }
}

// Auto-validação ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    adicionarLog('Página de validação SEFAZ carregada', 'info');
    
    // Se há certificado, fazer validação automática
    <?php if ($certificado): ?>
    setTimeout(function() {
        validarCertificado();
    }, 1000);
    <?php endif; ?>
});
</script>

<?php include '../includes/footer.php'; ?>
