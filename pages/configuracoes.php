<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

configure_session();
session_start();

$page_title = 'Configurações do Sistema';
$empresa_id = $_SESSION['empresa_id'];

// Busca as configurações atuais
$conn = getConnection();
$stmt = $conn->prepare('SELECT nome_personalizado, logo_empresa FROM configuracoes WHERE empresa_id = :empresa_id LIMIT 1');
$stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$nome_personalizado = $row ? $row['nome_personalizado'] : 'Desenvolvimento';
$logo_path = $row ? $row['logo_empresa'] : null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    <link rel="icon" type="image/png" href="../logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <style>
        .logo-preview {
            max-width: 200px;
            max-height: 200px;
            margin: 10px 0;
            display: none;
        }
        .logo-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .logo-upload {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn-secondary i {
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1><?php echo $page_title; ?></h1>
                </div>
                <div class="dashboard-grid" style="max-width: 600px; margin: 0 auto;">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Personalização do Menu Lateral</h3>
                        </div>
                        <div class="card-body">
                            <form id="configForm">
                                <div class="form-group">
                                    <label for="nome_personalizado">Título do Menu Lateral</label>
                                    <input type="text" id="nome_personalizado" name="nome_personalizado" value="<?php echo htmlspecialchars($nome_personalizado); ?>" maxlength="255" required>
                                </div>
                                <button type="submit" class="btn-primary" id="saveConfigBtn">Salvar</button>
                            </form>
                            <div id="configMsg" style="margin-top:10px;"></div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Logo da Empresa</h3>
                        </div>
                        <div class="card-body">
                            <form id="logoForm" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="logo">Selecione uma imagem (JPG, PNG ou GIF, máx. 5MB)</label>
                                    <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/gif" required>
                                </div>
                                <div class="logo-preview" id="logoPreview">
                                    <img src="" alt="Preview do logo">
                                </div>
                                <button type="submit" class="btn-primary" id="uploadLogoBtn">Enviar Logo</button>
                            </form>
                            <div id="logoMsg" style="margin-top:10px;"></div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Certificado Digital A1</h3>
                        </div>
                        <div class="card-body">
                            <form id="certificadoForm" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="nome_certificado">Nome do Certificado</label>
                                    <input type="text" id="nome_certificado" name="nome_certificado" placeholder="Ex: Certificado Empresa 2025" required>
                                </div>
                                <div class="form-group">
                                    <label for="arquivo_certificado">Arquivo do Certificado (.pfx, .p12)</label>
                                    <input type="file" id="arquivo_certificado" name="arquivo_certificado" accept=".pfx,.p12" required>
                                    <small class="form-text">Formatos aceitos: .pfx, .p12 (máx. 10MB)</small>
                                </div>
                                <div class="form-group">
                                    <label for="senha_certificado">Senha do Certificado</label>
                                    <input type="password" id="senha_certificado" name="senha_certificado" placeholder="Digite a senha do certificado" required>
                                </div>
                                <div class="form-group">
                                    <label for="data_validade">Data de Validade</label>
                                    <input type="date" id="data_validade" name="data_validade" required>
                                </div>
                                <div class="form-group">
                                    <label for="tipo_certificado">Tipo de Certificado</label>
                                    <select id="tipo_certificado" name="tipo_certificado" required>
                                        <option value="A1">A1 - Arquivo</option>
                                        <option value="A3">A3 - Token/Cartão</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="observacoes">Observações</label>
                                    <textarea id="observacoes" name="observacoes" rows="3" placeholder="Informações adicionais sobre o certificado"></textarea>
                                </div>
                                <button type="submit" class="btn-primary" id="uploadCertificadoBtn">Enviar Certificado</button>
                            </form>
                            <div id="certificadoMsg" style="margin-top:10px;"></div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Ambiente do Sistema Fiscal</h3>
                        </div>
                        <div class="card-body">
                            <form id="ambienteFiscalForm">
                                <div class="form-group">
                                    <label for="ambiente_sefaz">Ambiente SEFAZ</label>
                                    <select id="ambiente_sefaz" name="ambiente_sefaz" required>
                                        <option value="homologacao">🟡 Homologação (Teste)</option>
                                        <option value="producao">🟢 Produção (Real)</option>
                                    </select>
                                    <small class="form-text">
                                        <strong>Homologação:</strong> Ambiente de testes da SEFAZ<br>
                                        <strong>Produção:</strong> Ambiente real para emissão de documentos
                                    </small>
                                </div>
                                <div class="form-group">
                                    <label for="cnpj_empresa">CNPJ da Empresa</label>
                                    <input type="text" id="cnpj_empresa" name="cnpj_empresa" placeholder="00.000.000/0000-00" maxlength="18" required>
                                </div>
                                <div class="form-group">
                                    <label for="razao_social">Razão Social</label>
                                    <input type="text" id="razao_social" name="razao_social" placeholder="Nome completo da empresa" maxlength="255" required>
                                </div>
                                <div class="form-group">
                                    <label for="nome_fantasia">Nome Fantasia</label>
                                    <input type="text" id="nome_fantasia" name="nome_fantasia" placeholder="Nome comercial da empresa" maxlength="255">
                                </div>
                                <div class="form-group">
                                    <label for="inscricao_estadual">Inscrição Estadual</label>
                                    <input type="text" id="inscricao_estadual" name="inscricao_estadual" placeholder="Inscrição estadual" maxlength="20">
                                </div>
                                <div class="form-group">
                                    <label for="codigo_municipio">Código do Município</label>
                                    <input type="text" id="codigo_municipio" name="codigo_municipio" placeholder="Código IBGE do município" maxlength="7">
                                </div>
                                <div class="form-group">
                                    <label for="cep">CEP</label>
                                    <input type="text" id="cep" name="cep" placeholder="00000-000" maxlength="9">
                                </div>
                                <div class="form-group">
                                    <label for="endereco">Endereço Completo</label>
                                    <textarea id="endereco" name="endereco" rows="3" placeholder="Endereço completo da empresa"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="telefone">Telefone</label>
                                    <input type="text" id="telefone" name="telefone" placeholder="(00) 00000-0000" maxlength="20">
                                </div>
                                <div class="form-group">
                                    <label for="email">E-mail</label>
                                    <input type="email" id="email" name="email" placeholder="email@empresa.com" maxlength="255">
                                </div>
                                <button type="submit" class="btn-primary" id="saveAmbienteFiscalBtn">Salvar Configurações Fiscais</button>
                            </form>
                            <div id="ambienteFiscalMsg" style="margin-top:10px;"></div>
                            
                            <!-- Botões de Teste SEFAZ -->
                            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                                <h4 style="margin-bottom: 15px; color: #333;">🧪 Testes SEFAZ</h4>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                                    <button type="button" class="btn-secondary" id="testeSefazBtn" onclick="testarSefaz()">
                                        <i class="fas fa-wifi"></i> Testar Conexão SEFAZ
                                    </button>
                                    <button type="button" class="btn-secondary" id="testeCertificadoBtn" onclick="testarCertificado()">
                                        <i class="fas fa-certificate"></i> Testar Certificado
                                    </button>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                    <button type="button" class="btn-secondary" id="converterCertificadoBtn" onclick="converterCertificado()">
                                        <i class="fas fa-exchange-alt"></i> Converter Certificado
                                    </button>
                                    <button type="button" class="btn-secondary" id="diagnosticoBtn" onclick="diagnosticoSefaz()">
                                        <i class="fas fa-stethoscope"></i> Diagnóstico Completo
                                    </button>
                                </div>
                                <div id="testeSefazMsg" style="margin-top: 15px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
    document.getElementById('configForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const nome = document.getElementById('nome_personalizado').value.trim();
        const msg = document.getElementById('configMsg');
        msg.textContent = '';
        fetch('../api/configuracoes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_nome', nome_personalizado: nome })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                msg.textContent = 'Título atualizado com sucesso!';
                msg.style.color = 'green';
                // Atualiza o menu lateral dinamicamente
                const menuTitle = document.querySelector('.sidebar .app-name');
                if (menuTitle) menuTitle.textContent = nome;
            } else {
                msg.textContent = res.error || 'Erro ao salvar.';
                msg.style.color = 'red';
            }
        })
        .catch(() => {
            msg.textContent = 'Erro ao salvar.';
            msg.style.color = 'red';
        });
    });

    // Preview da imagem antes do upload
    document.getElementById('logo').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('logoPreview');
                preview.style.display = 'block';
                preview.querySelector('img').src = e.target.result;
            }
            reader.readAsDataURL(file);
        }
    });

    // Upload do logo
    document.getElementById('logoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'upload_logo');
        
        const msg = document.getElementById('logoMsg');
        msg.textContent = '';
        
        fetch('../api/configuracoes.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                msg.textContent = 'Logo atualizado com sucesso!';
                msg.style.color = 'green';
                // Atualiza o logo no menu lateral
                const menuLogo = document.querySelector('.sidebar .logo img');
                if (menuLogo) {
                    menuLogo.src = '../' + res.logo_path;
                }
            } else {
                msg.textContent = res.error || 'Erro ao fazer upload do logo.';
                msg.style.color = 'red';
            }
        })
        .catch(() => {
            msg.textContent = 'Erro ao fazer upload do logo.';
            msg.style.color = 'red';
        });
    });

    // Upload do certificado A1
    document.getElementById('certificadoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'upload_certificado');
        
        const msg = document.getElementById('certificadoMsg');
        msg.textContent = '';
        
        // Validar data de validade
        const dataValidade = new Date(document.getElementById('data_validade').value);
        const hoje = new Date();
        if (dataValidade <= hoje) {
            msg.textContent = 'A data de validade deve ser futura.';
            msg.style.color = 'red';
            return;
        }
        
        fetch('../api/configuracoes.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                msg.textContent = 'Certificado enviado com sucesso!';
                msg.style.color = 'green';
                // Limpar formulário
                document.getElementById('certificadoForm').reset();
            } else {
                msg.textContent = res.error || 'Erro ao fazer upload do certificado.';
                msg.style.color = 'red';
            }
        })
        .catch(() => {
            msg.textContent = 'Erro ao fazer upload do certificado.';
            msg.style.color = 'red';
        });
    });

    // Carregar configurações fiscais existentes
    async function loadConfiguracoesFiscais() {
        try {
            const response = await fetch('../api/configuracoes.php?action=get_config_fiscal', {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.data) {
                    // Preencher formulário com dados existentes
                    document.getElementById('ambiente_sefaz').value = data.data.ambiente_sefaz || 'homologacao';
                    document.getElementById('cnpj_empresa').value = data.data.cnpj || '';
                    document.getElementById('razao_social').value = data.data.razao_social || '';
                    document.getElementById('nome_fantasia').value = data.data.nome_fantasia || '';
                    document.getElementById('inscricao_estadual').value = data.data.inscricao_estadual || '';
                    document.getElementById('codigo_municipio').value = data.data.codigo_municipio || '';
                    document.getElementById('cep').value = data.data.cep || '';
                    document.getElementById('endereco').value = data.data.endereco || '';
                    document.getElementById('telefone').value = data.data.telefone || '';
                    document.getElementById('email').value = data.data.email || '';
                    
                    // Mostrar mensagem informativa
                    const msg = document.getElementById('ambienteFiscalMsg');
                    if (data.empresa_existe) {
                        msg.innerHTML = '<div style="color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0;">' +
                            '<strong>✅ Dados carregados da empresa!</strong><br>' +
                            'Os campos foram preenchidos automaticamente com as informações da sua empresa. ' +
                            'Você pode editar e salvar as alterações.' +
                            '</div>';
                    } else if (data.config_fiscal_existe) {
                        msg.innerHTML = '<div style="color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0;">' +
                            '<strong>ℹ️ Configurações fiscais encontradas!</strong><br>' +
                            'Carregamos suas configurações fiscais existentes.' +
                            '</div>';
                    } else {
                        msg.innerHTML = '<div style="color: #ffc107; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0;">' +
                            '<strong>⚠️ Primeira configuração!</strong><br>' +
                            'Esta é a primeira vez que você configura o sistema fiscal. ' +
                            'Preencha os dados da sua empresa e escolha o ambiente.' +
                            '</div>';
                    }
                }
            }
        } catch (error) {
            console.error('Erro ao carregar configurações fiscais:', error);
            const msg = document.getElementById('ambienteFiscalMsg');
            msg.innerHTML = '<div style="color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0;">' +
                '<strong>❌ Erro ao carregar dados!</strong><br>' +
                'Não foi possível carregar as configurações. Verifique sua conexão.' +
                '</div>';
        }
    }

    // Salvar configurações fiscais
    document.getElementById('ambienteFiscalForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const msg = document.getElementById('ambienteFiscalMsg');
        msg.textContent = '';
        
        // Validar CNPJ
        const cnpj = formData.get('cnpj_empresa').replace(/\D/g, '');
        if (cnpj.length !== 14) {
            msg.textContent = 'CNPJ inválido. Deve ter 14 dígitos.';
            msg.style.color = 'red';
            return;
        }
        
        // Preparar dados para envio
        const dados = {
            action: 'save_config_fiscal',
            ambiente_sefaz: formData.get('ambiente_sefaz'),
            cnpj: cnpj,
            razao_social: formData.get('razao_social').trim(),
            nome_fantasia: formData.get('nome_fantasia').trim(),
            inscricao_estadual: formData.get('inscricao_estadual').trim(),
            codigo_municipio: formData.get('codigo_municipio').trim(),
            cep: formData.get('cep').trim(),
            endereco: formData.get('endereco').trim(),
            telefone: formData.get('telefone').trim(),
            email: formData.get('email').trim()
        };
        
        // Validar campos obrigatórios
        if (!dados.razao_social) {
            msg.textContent = 'Razão Social é obrigatória.';
            msg.style.color = 'red';
            return;
        }
        
        fetch('../api/configuracoes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                msg.textContent = 'Configurações fiscais salvas com sucesso!';
                msg.style.color = 'green';
                
                // Mostrar alerta de ambiente
                const ambiente = dados.ambiente_sefaz === 'producao' ? 'PRODUÇÃO' : 'HOMOLOGAÇÃO';
                const icon = dados.ambiente_sefaz === 'producao' ? '🟢' : '🟡';
                alert(`${icon} AMBIENTE ALTERADO PARA: ${ambiente}\n\n` +
                      `⚠️ ATENÇÃO: ${dados.ambiente_sefaz === 'producao' ? 
                      'Este ambiente é REAL e emitirá documentos válidos!' : 
                      'Este ambiente é de TESTE e emitirá documentos inválidos!'}`);
            } else {
                msg.textContent = res.error || 'Erro ao salvar configurações.';
                msg.style.color = 'red';
            }
        })
        .catch(() => {
            msg.textContent = 'Erro ao salvar configurações.';
            msg.style.color = 'red';
        });
    });

    // Máscara para CNPJ
    document.getElementById('cnpj_empresa').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length <= 14) {
            value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
            e.target.value = value;
        }
    });

    // Máscara para CEP
    document.getElementById('cep').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length <= 8) {
            value = value.replace(/^(\d{5})(\d{3})$/, '$1-$2');
            e.target.value = value;
        }
    });

    // Máscara para telefone
    document.getElementById('telefone').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length <= 11) {
            if (value.length <= 10) {
                value = value.replace(/^(\d{2})(\d{4})(\d{4})$/, '($1) $2-$3');
            } else {
                value = value.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
            }
            e.target.value = value;
        }
    });

    // Carregar configurações fiscais ao carregar a página
    document.addEventListener('DOMContentLoaded', function() {
        loadConfiguracoesFiscais();
    });

    // Funções de Teste SEFAZ
    function testarSefaz() {
        const msg = document.getElementById('testeSefazMsg');
        msg.innerHTML = '<div style="color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px;">' +
            '<strong>🔄 Testando conexão SEFAZ...</strong><br>Redirecionando para o teste...</div>';
        
        // Abrir em nova aba
        window.open('../fiscal/teste_sefaz_curl.php', '_blank');
    }

    function testarCertificado() {
        const msg = document.getElementById('testeSefazMsg');
        msg.innerHTML = '<div style="color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px;">' +
            '<strong>🔐 Testando certificado ICP-Brasil...</strong><br>Redirecionando para o teste...</div>';
        
        // Abrir em nova aba
        window.open('../fiscal/teste_sefaz_icp.php', '_blank');
    }

    function converterCertificado() {
        const msg = document.getElementById('testeSefazMsg');
        msg.innerHTML = '<div style="color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px;">' +
            '<strong>🔄 Convertendo certificado...</strong><br>Redirecionando para o conversor...</div>';
        
        // Abrir em nova aba
        window.open('../fiscal/converter_certificado_icp.php', '_blank');
    }

    function diagnosticoSefaz() {
        const msg = document.getElementById('testeSefazMsg');
        msg.innerHTML = '<div style="color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px;">' +
            '<strong>🔍 Executando diagnóstico completo...</strong><br>Redirecionando para o diagnóstico...</div>';
        
        // Abrir em nova aba
        window.open('../fiscal/diagnostico_dns_php.php', '_blank');
    }
    </script>
</body>
</html> 