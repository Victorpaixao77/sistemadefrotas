<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';
require_once '../includes/notifications.php';
require_once '../includes/competitions.php';

configure_session();
session_start();

// Verificar permissão para acessar configurações do sistema
require_permission('manage_system_settings');

$page_title = 'Configurações do Sistema';
$empresa_id = $_SESSION['empresa_id'];

// Busca as configurações atuais
$conn = getConnection();
$stmt = $conn->prepare('SELECT nome_personalizado, logo_empresa, certificado_a1_id FROM configuracoes WHERE empresa_id = :empresa_id LIMIT 1');
$stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$nome_personalizado = $row ? $row['nome_personalizado'] : 'Frotec Online';
$logo_path = $row && $row['logo_empresa'] ? $row['logo_empresa'] : 'logo.png';
$certificado_atual = null;
if ($row && !empty($row['certificado_a1_id'])) {
    try {
        $stmtCert = $conn->prepare('SELECT nome_certificado, data_vencimento, arquivo_certificado FROM fiscal_certificados_digitais WHERE id = :id AND empresa_id = :empresa_id AND ativo = 1 LIMIT 1');
        $stmtCert->bindParam(':id', $row['certificado_a1_id'], PDO::PARAM_INT);
        $stmtCert->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmtCert->execute();
        $cert = $stmtCert->fetch(PDO::FETCH_ASSOC);
        if ($cert) {
            $certificado_atual = [
                'nome' => $cert['nome_certificado'],
                'data_vencimento' => $cert['data_vencimento'],
                'arquivo' => $cert['arquivo_certificado']
            ];
        }
    } catch (Exception $e) {
        // Evitar que erro de consulta do certificado quebre a tela de configurações
    }
}
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
    <link rel="stylesheet" href="../css/fornc-modern-page.css">
    <link rel="stylesheet" href="../css/configuracoes.css?v=5">
    <?php require_once '../includes/sf_api_base.php'; sf_render_api_scripts(); ?>
</head>
<body class="configuracoes-modern-page">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content configuracoes-page fornc-page" id="configuracoes-top">
                <div class="config-grid">
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
                            <div id="configMsg"></div>
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
                            <div id="logoMsg"></div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Google Maps</h3>
                        </div>
                        <div class="card-body">
                            <form id="googleMapsForm">
                                <div class="form-group">
                                    <label for="google_maps_api_key">Chave da API do Google Maps</label>
                                    <input type="text" id="google_maps_api_key" name="google_maps_api_key" 
                                           placeholder="AIzaSyBxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" 
                                           maxlength="500" required>
                                    <small class="form-text">
                                        <strong>Como obter:</strong> Acesse o <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a>, 
                                        ative a API do Google Maps e gere uma chave de API.<br>
                                        <strong>Permissões necessárias:</strong> Geocoding API, Maps JavaScript API, Places API
                                    </small>
                                </div>
                                <div class="config-actions">
                                    <button type="button" id="testApiKeyBtn" class="btn-secondary">
                                        <i class="fas fa-check-circle"></i> Testar Chave
                                    </button>
                                    <button type="submit" class="btn-primary">
                                        <i class="fas fa-save"></i> Salvar Configuração
                                    </button>
                                </div>
                            </form>
                            <div id="googleMapsMsg"></div>
                        </div>
                    </div>

                    <div class="dashboard-card config-card--full config-gps-cercas" id="config-gps-cercas">
                        <div class="card-header">
                            <h3><i class="fas fa-draw-polygon"></i> Cercas GPS (geofence)</h3>
                        </div>
                        <div class="card-body">
                            <p class="form-text config-card-lead">Zonas circulares no mapa: quando o app envia a posição, o sistema pode registrar <strong>entrou</strong> / <strong>saiu</strong> nos alertas. As cercas ativas aparecem no <a href="<?php echo htmlspecialchars(sf_app_url('pages/mapa_frota.php')); ?>">Mapa da frota</a>.</p>
                            <div id="gpsCercasMsg" class="config-inline-msg" role="status" aria-live="polite"></div>
                            <form id="gpsCercasCreateForm" autocomplete="off">
                                <div class="config-row-2 config-row-2--mb">
                                    <div class="form-group">
                                        <label for="gpsCercaNome">Nome</label>
                                        <input type="text" id="gpsCercaNome" name="nome" maxlength="120" placeholder="Ex.: Pátio matriz" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="gpsCercaRaio">Raio (metros)</label>
                                        <input type="number" id="gpsCercaRaio" name="raio_metros" value="500" min="50" max="50000" step="10" required>
                                    </div>
                                </div>
                                <div class="config-row-2 config-row-2--mb-lg">
                                    <div class="form-group">
                                        <label for="gpsCercaLat">Latitude</label>
                                        <input type="text" id="gpsCercaLat" name="latitude" inputmode="decimal" placeholder="-23.550520" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="gpsCercaLng">Longitude</label>
                                        <input type="text" id="gpsCercaLng" name="longitude" inputmode="decimal" placeholder="-46.633308" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn-primary" id="gpsCercasSubmitBtn">
                                    <i class="fas fa-plus"></i> Cadastrar cerca
                                </button>
                            </form>
                            <h4 class="config-gps-cercas-sub">Cercas cadastradas</h4>
                            <div class="config-gps-cercas-table-wrap">
                                <table class="config-gps-cercas-table">
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>Lat / Lng</th>
                                            <th>Raio (m)</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="gpsCercasTableBody">
                                        <tr><td colspan="4" class="form-text">Carregando…</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Certificado Digital A1</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($certificado_atual)): ?>
                                <div class="form-text config-cert-banner">
                                    <strong>Certificado atual:</strong>
                                    <?php echo htmlspecialchars($certificado_atual['nome']); ?>
                                    <?php if (!empty($certificado_atual['data_vencimento'])): ?>
                                        - válido até <?php echo date('d/m/Y', strtotime($certificado_atual['data_vencimento'])); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($certificado_atual['arquivo'])): ?>
                                        <br><strong>Arquivo:</strong> <?php echo htmlspecialchars($certificado_atual['arquivo']); ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <form id="certificadoForm" enctype="multipart/form-data">
                                <div class="config-row-2">
                                    <div class="form-group">
                                        <label for="nome_certificado">Nome do Certificado</label>
                                        <input type="text" id="nome_certificado" name="nome_certificado" placeholder="Ex: Certificado Empresa 2025" value="<?php echo !empty($certificado_atual['nome']) ? htmlspecialchars($certificado_atual['nome']) : ''; ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="data_validade">Data de Validade</label>
                                        <input type="date" id="data_validade" name="data_validade" value="<?php echo !empty($certificado_atual['data_vencimento']) ? htmlspecialchars($certificado_atual['data_vencimento']) : ''; ?>">
                                        <small class="form-text">Será preenchida automaticamente a partir do certificado enviado.</small>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="arquivo_certificado">Arquivo do Certificado (.pfx, .p12)</label>
                                    <input type="file" id="arquivo_certificado" name="arquivo_certificado" accept=".pfx,.p12" required>
                                    <small class="form-text">Formatos aceitos: .pfx, .p12 (máx. 10MB)</small>
                                </div>
                                
                                <div class="config-row-2">
                                    <div class="form-group">
                                        <label for="senha_certificado">Senha do Certificado</label>
                                        <input type="password" id="senha_certificado" name="senha_certificado" placeholder="Digite a senha do certificado" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="tipo_certificado">Tipo de Certificado</label>
                                        <select id="tipo_certificado" name="tipo_certificado" required>
                                            <option value="A1">A1 - Arquivo</option>
                                            <option value="A3">A3 - Token/Cartão</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="observacoes">Observações</label>
                                    <textarea id="observacoes" name="observacoes" rows="3" placeholder="Informações adicionais sobre o certificado"></textarea>
                                </div>
                                <button type="submit" class="btn-primary" id="uploadCertificadoBtn">Enviar Certificado</button>
                            </form>
                            <div id="certificadoMsg"></div>
                        </div>
                    </div>

                    <div class="dashboard-card config-card--span-2">
                        <div class="card-header">
                            <h3><i class="fas fa-search"></i> Consulta de Multas (DETRAN)</h3>
                        </div>
                        <div class="card-body">
                            <p class="form-text config-card-lead">Configure a integração com o WSDenatran para consultar infrações na base do Denatran. Cadastre o certificado abaixo (igual ao Certificado Digital A1).</p>
                            <form id="denatranConfigForm">
                                <div class="config-row-2 config-row-2--mb">
                                    <div class="form-group">
                                        <label for="denatran_habilitado">Habilitar consulta DETRAN</label>
                                        <select id="denatran_habilitado" name="habilitado">
                                            <option value="0">Não</option>
                                            <option value="1">Sim</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="denatran_base_url">URL do serviço (ambiente)</label>
                                        <select id="denatran_base_url" name="base_url">
                                            <option value="https://wsdenatrandes-des07116.apps.dev.serpro">Desenvolvimento</option>
                                            <option value="https://wsrenavam.hom.denatran.serpro.gov.br">Homologação</option>
                                            <option value="https://renavam.denatran.serpro.gov.br">Produção</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="denatran_cpf_usuario">CPF do usuário autorizado (11 dígitos)</label>
                                    <input type="text" id="denatran_cpf_usuario" name="cpf_usuario" placeholder="000.000.000-00" maxlength="14">
                                    <small class="form-text">Obrigatório no header da API WSDenatran.</small>
                                </div>
                                <button type="submit" class="btn-primary" id="saveDenatranConfigBtn"><i class="fas fa-save"></i> Salvar configuração DETRAN</button>
                            </form>
                            <div id="denatranConfigMsg"></div>

                            <div class="config-subsection">
                                <h4><i class="fas fa-certificate"></i> Certificado Digital (DETRAN)</h4>
                                <p id="denatranCertInfo" class="form-text config-text-muted">Nenhum certificado enviado. Envie um certificado .pfx ou .p12 (igual ao A1).</p>
                                <form id="denatranCertForm" enctype="multipart/form-data">
                                    <div class="config-row-2 config-row-2--mb">
                                        <div class="form-group">
                                            <label for="denatran_nome_certificado">Nome do certificado</label>
                                            <input type="text" id="denatran_nome_certificado" name="nome_certificado" placeholder="Ex: Certificado DETRAN 2025" value="Certificado DETRAN">
                                        </div>
                                        <div class="form-group">
                                            <label for="denatran_data_validade">Data de validade</label>
                                            <input type="date" id="denatran_data_validade" name="data_validade">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="denatran_arquivo_certificado">Arquivo do certificado (.pfx, .p12)</label>
                                        <input type="file" id="denatran_arquivo_certificado" name="arquivo_certificado" accept=".pfx,.p12" required>
                                        <small class="form-text">Formatos aceitos: .pfx, .p12 (máx. 10MB) — igual ao Certificado A1.</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="denatran_senha_certificado">Senha do certificado</label>
                                        <input type="password" id="denatran_senha_certificado" name="senha_certificado" placeholder="Senha do arquivo .pfx/.p12" required>
                                    </div>
                                    <button type="submit" class="btn-primary" id="uploadDenatranCertBtn"><i class="fas fa-upload"></i> Enviar certificado DETRAN</button>
                                </form>
                                <div id="denatranCertMsg"></div>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-card config-card--full">
                        <div class="card-header">
                            <h3>Ambiente do Sistema Fiscal</h3>
                        </div>
                        <div class="card-body">
                            <form id="ambienteFiscalForm" class="config-form-fiscal">
                                <div class="form-group">
                                    <label for="ambiente_sefaz">Ambiente SEFAZ</label>
                                    <select id="ambiente_sefaz" name="ambiente_sefaz" required>
                                        <option value="homologacao">🟡 Homologação (Teste)</option>
                                        <option value="producao">🟢 Produção (Real)</option>
                                    </select>
                                    <small class="form-text">
                                        <strong>Homologação:</strong> Ambiente de testes da SEFAZ<br>
                                        <strong>Produção:</strong> Ambiente real para emissão de documentos<br>
                                        <strong>Esta opção vale para os 3 tipos de documento:</strong> NF-e, CT-e e MDF-e.
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
                                    <label for="rntrc">RNTRC (ANTT)</label>
                                    <input type="text" id="rntrc" name="rntrc" placeholder="Ex: 1234567890123" maxlength="20">
                                    <small class="form-text">
                                        Necessário para emitir MDF-e rodoviário (e validações antes de envio para SEFAZ).
                                    </small>
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
                            <div id="ambienteFiscalMsg"></div>
                            
                            <div class="config-subsection">
                                <h4>🧪 Testes SEFAZ</h4>
                                <div class="config-row-2 config-row-2--gap-sm config-row-2--mb">
                                    <button type="button" class="btn-secondary" id="testeSefazBtn" onclick="testarSefaz()">
                                        <i class="fas fa-wifi"></i> Testar Conexão SEFAZ
                                    </button>
                                    <button type="button" class="btn-secondary" id="testeCertificadoBtn" onclick="testarCertificado()">
                                        <i class="fas fa-certificate"></i> Testar Certificado
                                    </button>
                                </div>
                                <div class="config-row-2 config-row-2--gap-sm">
                                    <button type="button" class="btn-secondary" id="converterCertificadoBtn" onclick="converterCertificado()">
                                        <i class="fas fa-exchange-alt"></i> Converter Certificado
                                    </button>
                                    <button type="button" class="btn-secondary" id="diagnosticoBtn" onclick="diagnosticoSefaz()">
                                        <i class="fas fa-stethoscope"></i> Diagnóstico Completo
                                    </button>
                                </div>
                                <div id="testeSefazMsg"></div>
                            </div>
                        </div>
                    </div>

                    <!-- ======================================== -->
                    <!-- CONFIGURAÇÕES DE FUNCIONALIDADES AVANÇADAS -->
                    <!-- ======================================== -->
                    
                    <div class="dashboard-card config-card--full">
                        <div class="card-header">
                            <h3>⚙️ Configurações de Funcionalidades Avançadas</h3>
                        </div>
                        <div class="card-body">
                            <!-- Sistema de Notificações -->
                            <div class="mb-4">
                                <h5 class="mb-3" style="color: #2c3e50; border-bottom: 2px solid #2c3e50; padding-bottom: 5px;">
                                    <i class="fas fa-bell me-2"></i>Sistema de Notificações
                                </h5>
                                <form id="configNotificacoesForm">
                                    <div class="config-row-2 config-row-2--mb-lg">
                                        <div class="form-group">
                                            <label>
                                                <input type="checkbox" id="notificacoes_badges" name="notificacoes_badges" checked> 
                                                Notificar sobre novos badges conquistados
                                            </label>
                                        </div>
                                        <div class="form-group">
                                            <label>
                                                <input type="checkbox" id="notificacoes_niveis" name="notificacoes_niveis" checked> 
                                                Notificar sobre subida de nível
                                            </label>
                                        </div>
                                        <div class="form-group">
                                            <label>
                                                <input type="checkbox" id="notificacoes_ranking" name="notificacoes_ranking" checked> 
                                                Notificar sobre mudanças no ranking
                                            </label>
                                        </div>
                                        <div class="form-group">
                                            <label>
                                                <input type="checkbox" id="notificacoes_desafios" name="notificacoes_desafios" checked> 
                                                Notificar sobre desafios completados
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" id="sistema_notificacoes_ativo" name="sistema_notificacoes_ativo" checked> 
                                            Sistema de Notificações Ativo
                                        </label>
                                    </div>
                                    
                                    <button type="submit" class="btn-primary">Salvar Configurações de Notificações</button>
                                </form>
                                <div id="configNotificacoesMsg"></div>
                            </div>

                            <!-- Sistema de Cache -->
                            <div class="mb-4">
                                <h5 class="mb-3" style="color: #2c3e50; border-bottom: 2px solid #2c3e50; padding-bottom: 5px;">
                                    <i class="fas fa-database me-2"></i>Sistema de Cache
                                </h5>
                                <form id="configCacheForm">
                                    <div class="config-row-2 config-row-2--mb-lg">
                                        <div class="form-group">
                                            <label for="cache_ttl">TTL do Cache (minutos)</label>
                                            <input type="number" id="cache_ttl" name="cache_ttl" value="5" min="1" max="60">
                                            <small class="form-text">Tempo de vida do cache em minutos</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="cache_max_size">Tamanho Máximo do Cache (MB)</label>
                                            <input type="number" id="cache_max_size" name="cache_max_size" value="100" min="10" max="1000">
                                            <small class="form-text">Tamanho máximo em megabytes</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="background_intervalo">Intervalo de Processamento (min)</label>
                                            <input type="number" id="background_intervalo" name="background_intervalo" value="30" min="5" max="1440">
                                            <small class="form-text">Intervalo entre execuções automáticas</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="background_timeout">Timeout de Execução (min)</label>
                                            <input type="number" id="background_timeout" name="background_timeout" value="10" min="1" max="60">
                                            <small class="form-text">Tempo máximo para cada execução</small>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" id="cache_ativo" name="cache_ativo" checked> 
                                            Sistema de Cache Ativo
                                        </label>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" id="background_ativo" name="background_ativo" checked> 
                                            Processamento em Background Ativo
                                        </label>
                                    </div>
                                    
                                    <button type="submit" class="btn-primary">Salvar Configurações de Cache</button>
                                    <button type="button" class="btn-secondary ms-2" onclick="limparCache()">
                                        <i class="fas fa-trash me-1"></i>Limpar Cache Agora
                                    </button>
                                </form>
                                <div id="configCacheMsg"></div>
                            </div>
                        </div>
                    </div>

                    <!-- ======================================== -->
                    <!-- CONFIGURAÇÕES DE GAMIFICAÇÃO -->
                    <!-- ======================================== -->
                    
                    <div class="dashboard-card config-card--full">
                        <div class="card-header">
                            <h3>🎮 Configurações de Gamificação</h3>
                        </div>
                        <div class="card-body">
                            <!-- Ações Principais -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-start mb-4">
                                <button type="button" class="btn-secondary" onclick="calcularGamificacao()">
                                    <i class="fas fa-calculator me-2"></i>Calcular Pontos
                                </button>
                                <button type="button" class="btn-secondary" onclick="window.open('../pages/motorists.php', '_blank')">
                                    <i class="fas fa-eye me-2"></i>Ver Gamificação
                                </button>
                            </div>
                            <div id="gamificacaoStatus" style="margin-bottom: 20px;"></div>

                            <!-- Sistema de Badges e Conquistas -->
                            <div class="mb-4">
                                <h5 class="mb-3" style="color: #ff6b35; border-bottom: 2px solid #ff6b35; padding-bottom: 5px;">
                                    <i class="fas fa-medal me-2"></i>Sistema de Badges e Conquistas
                                </h5>
                                <form id="configBadgesForm">
                                    <div class="config-row-2 config-row-2--mb-lg">
                                        <div class="form-group">
                                            <label for="badge_motorista_economico">Badge "Motorista Econômico"</label>
                                            <input type="number" id="badge_motorista_economico" name="badge_motorista_economico" value="3" min="1" max="12">
                                            <small class="form-text">Meses com consumo abaixo da média</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="badge_sem_multas">Badge "Sem Multas"</label>
                                            <input type="number" id="badge_sem_multas" name="badge_sem_multas" value="12" min="1" max="24">
                                            <small class="form-text">Meses sem infrações</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="badge_checklists_perfeitos">Badge "Checklists Perfeitos"</label>
                                            <input type="number" id="badge_checklists_perfeitos" name="badge_checklists_perfeitos" value="50" min="10" max="200">
                                            <small class="form-text">Checklists completos sem falhas</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="pontos_por_badge">Pontos por Badge</label>
                                            <input type="number" id="pontos_por_badge" name="pontos_por_badge" value="50" min="10" max="500">
                                            <small class="form-text">Pontos extras ao conquistar uma badge</small>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" id="sistema_badges_ativo" name="sistema_badges_ativo" checked> 
                                            Sistema de Badges Ativo
                                        </label>
                                    </div>
                                    
                                    <button type="submit" class="btn-primary">Salvar Configurações de Badges</button>
                                </form>
                                <div id="configBadgesMsg"></div>
                            </div>

                            <!-- Sistema de Níveis Avançado -->
                            <div class="mb-4">
                                <h5 class="mb-3" style="color: #ff6b35; border-bottom: 2px solid #ff6b35; padding-bottom: 5px;">
                                    <i class="fas fa-star me-2"></i>Sistema de Níveis Avançado
                                </h5>
                                <form id="configNiveisForm">
                                    <div class="config-row-2 config-row-2--mb-lg">
                                        <div class="form-group">
                                            <label for="nivel_bronze_min">Bronze - Pontos Mínimos</label>
                                            <input type="number" id="nivel_bronze_min" name="nivel_bronze_min" value="0" min="0" max="1000">
                                        </div>
                                        <div class="form-group">
                                            <label for="nivel_bronze_max">Bronze - Pontos Máximos</label>
                                            <input type="number" id="nivel_bronze_max" name="nivel_bronze_max" value="99" min="0" max="1000">
                                        </div>
                                        <div class="form-group">
                                            <label for="nivel_prata_min">Prata - Pontos Mínimos</label>
                                            <input type="number" id="nivel_prata_min" name="nivel_prata_min" value="100" min="0" max="1000">
                                        </div>
                                        <div class="form-group">
                                            <label for="nivel_prata_max">Prata - Pontos Máximos</label>
                                            <input type="number" id="nivel_prata_max" name="nivel_prata_max" value="299" min="0" max="1000">
                                        </div>
                                        <div class="form-group">
                                            <label for="nivel_ouro_min">Ouro - Pontos Mínimos</label>
                                            <input type="number" id="nivel_ouro_min" name="nivel_ouro_min" value="300" min="0" max="1000">
                                        </div>
                                        <div class="form-group">
                                            <label for="nivel_ouro_max">Ouro - Pontos Máximos</label>
                                            <input type="number" id="nivel_ouro_max" name="nivel_ouro_max" value="599" min="0" max="1000">
                                        </div>
                                        <div class="form-group">
                                            <label for="nivel_platina_min">Platina - Pontos Mínimos</label>
                                            <input type="number" id="nivel_platina_min" name="nivel_platina_min" value="600" min="0" max="1000">
                                        </div>
                                        <div class="form-group">
                                            <label for="nivel_platina_max">Platina - Pontos Máximos</label>
                                            <input type="number" id="nivel_platina_max" name="nivel_platina_max" value="899" min="0" max="1000">
                                        </div>
                                        <div class="form-group">
                                            <label for="nivel_diamante_min">Diamante - Pontos Mínimos</label>
                                            <input type="number" id="nivel_diamante_min" name="nivel_diamante_min" value="900" min="0" max="1000">
                                        </div>
                                        <div class="form-group">
                                            <label for="nivel_diamante_max">Diamante - Pontos Máximos</label>
                                            <input type="number" id="nivel_diamante_max" name="nivel_diamante_max" value="999" min="0" max="1000">
                                        </div>
                                        <div class="form-group">
                                            <label for="nivel_lenda_min">Lenda - Pontos Mínimos</label>
                                            <input type="number" id="nivel_lenda_min" name="nivel_lenda_min" value="1000" min="0" max="10000">
                                        </div>
                                        <div class="form-group">
                                            <label for="nivel_lenda_max">Lenda - Pontos Máximos</label>
                                            <input type="number" id="nivel_lenda_max" name="nivel_lenda_max" value="9999" min="0" max="10000">
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" id="niveis_avancados_ativos" name="niveis_avancados_ativos" checked> 
                                            Sistema de Níveis Avançado Ativo
                                        </label>
                                    </div>
                                    
                                    <button type="submit" class="btn-primary">Salvar Configurações de Níveis</button>
                                </form>
                                <div id="configNiveisMsg"></div>
                            </div>

                            <!-- Sistema de Desafios -->
                            <div class="mb-4">
                                <h5 class="mb-3" style="color: #ff6b35; border-bottom: 2px solid #ff6b35; padding-bottom: 5px;">
                                    <i class="fas fa-target me-2"></i>Sistema de Desafios
                                </h5>
                                <form id="configDesafiosForm">
                                    <div class="config-row-2 config-row-2--mb-lg">
                                        <div class="form-group">
                                            <label for="desafio_km_sem_infracoes">Desafio: KM sem Infrações</label>
                                            <input type="number" id="desafio_km_sem_infracoes" name="desafio_km_sem_infracoes" value="5000" min="1000" max="50000">
                                            <small class="form-text">Quilômetros para completar o desafio</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="desafio_rotas_sem_atrasos">Desafio: Rotas sem Atrasos</label>
                                            <input type="number" id="desafio_rotas_sem_atrasos" name="desafio_rotas_sem_atrasos" value="10" min="5" max="100">
                                            <small class="form-text">Número de rotas consecutivas</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="desafio_economia_combustivel">Desafio: Economia de Combustível</label>
                                            <input type="number" id="desafio_economia_combustivel" name="desafio_economia_combustivel" value="15" min="5" max="50" step="0.1">
                                            <small class="form-text">Percentual de economia (%)</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="pontos_desafio_completo">Pontos por Desafio Completo</label>
                                            <input type="number" id="pontos_desafio_completo" name="pontos_desafio_completo" value="100" min="10" max="1000">
                                            <small class="form-text">Pontos extras ao completar um desafio</small>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" id="desafios_ativos" name="desafios_ativos" checked> 
                                            Sistema de Desafios Ativo
                                        </label>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" id="feedback_imediato" name="feedback_imediato" checked> 
                                            Feedback Imediato Ativo
                                        </label>
                                        <small class="form-text">Mostrar notificações quando motorista completa uma ação eficiente</small>
                                    </div>
                                    
                                    <button type="submit" class="btn-primary">Salvar Configurações de Desafios</button>
                                </form>
                                <div id="configDesafiosMsg"></div>
                            </div>
                        </div>
                    </div>

                    <!-- ======================================== -->
                    <!-- CONFIGURAÇÕES DE RANKING -->
                    <!-- ======================================== -->
                    
                    <div class="dashboard-card config-card--full">
                        <div class="card-header">
                            <h3>🏆 Configurações de Ranking</h3>
                        </div>
                        <div class="card-body">
                            <!-- Ações Principais -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-start mb-4">
                                <button type="button" class="btn-secondary" onclick="calcularRanking()">
                                    <i class="fas fa-chart-line me-2"></i>Calcular Ranking
                                </button>
                                <button type="button" class="btn-secondary" onclick="window.open('../pages/motorists.php', '_blank')">
                                    <i class="fas fa-eye me-2"></i>Ver Ranking
                                </button>
                            </div>
                            <div id="rankingStatus" style="margin-bottom: 20px;"></div>

                            <!-- Configurações de Performance Básicas -->
                            <div class="mb-4">
                                <h5 class="mb-3" style="color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 5px;">
                                    <i class="fas fa-cogs me-2"></i>Configurações de Performance Básicas
                                </h5>
                                <form id="configPerformanceForm">
                                    <div class="config-row-2 config-row-2--mb-lg">
                                        <div class="form-group">
                                            <label for="peso_pontualidade">Peso da Pontualidade (%)</label>
                                            <input type="number" id="peso_pontualidade" name="peso_pontualidade" value="25" min="0" max="100" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="peso_consumo">Peso do Consumo (%)</label>
                                            <input type="number" id="peso_consumo" name="peso_consumo" value="30" min="0" max="100" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="peso_multas">Peso das Multas (%)</label>
                                            <input type="number" id="peso_multas" name="peso_multas" value="20" min="0" max="100" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="peso_checklist">Peso do Checklist (%)</label>
                                            <input type="number" id="peso_checklist" name="peso_checklist" value="15" min="0" max="100" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="peso_ocorrencias">Peso das Ocorrências (%)</label>
                                            <input type="number" id="peso_ocorrencias" name="peso_ocorrencias" value="10" min="0" max="100" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="pontos_maximos">Pontos Máximos para Gamificação</label>
                                            <input type="number" id="pontos_maximos" name="pontos_maximos" value="1000" min="100" max="10000" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" id="gamificacao_ativa" name="gamificacao_ativa" checked> 
                                            Sistema de Gamificação Ativo
                                        </label>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" id="ranking_automatico" name="ranking_automatico" checked> 
                                            Cálculo Automático de Ranking
                                        </label>
                                    </div>
                                    
                                    <button type="submit" class="btn-primary">Salvar Configurações de Performance</button>
                                </form>
                                <div id="configPerformanceMsg"></div>
                            </div>

                            <!-- Métricas Avançadas de Avaliação -->
                            <div class="mb-4">
                                <h5 class="mb-3" style="color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 5px;">
                                    <i class="fas fa-chart-bar me-2"></i>Métricas Avançadas de Avaliação
                                </h5>
                                <form id="configMetricasForm">
                                    <div class="config-row-2 config-row-2--mb-lg">
                                        <div class="form-group">
                                            <label for="peso_ocorrencias_sinistros">Peso de Ocorrências/Sinistros (%)</label>
                                            <input type="number" id="peso_ocorrencias_sinistros" name="peso_ocorrencias_sinistros" value="15" min="0" max="100">
                                            <small class="form-text">Impacta diretamente a segurança</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="peso_custos_km">Peso de Custos por KM (%)</label>
                                            <input type="number" id="peso_custos_km" name="peso_custos_km" value="10" min="0" max="100">
                                            <small class="form-text">Visão financeira do desempenho</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="peso_feedback_cliente">Peso de Feedback do Cliente (%)</label>
                                            <input type="number" id="peso_feedback_cliente" name="peso_feedback_cliente" value="10" min="0" max="100">
                                            <small class="form-text">Entrega dentro do padrão esperado</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="peso_manutencao_preventiva">Peso de Manutenção Preventiva (%)</label>
                                            <input type="number" id="peso_manutencao_preventiva" name="peso_manutencao_preventiva" value="5" min="0" max="100">
                                            <small class="form-text">Ajuda a manter o veículo em ordem</small>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" id="metricas_avancadas_ativas" name="metricas_avancadas_ativas"> 
                                            Ativar Métricas Avançadas
                                        </label>
                                    </div>
                                    
                                    <button type="submit" class="btn-primary">Salvar Métricas Avançadas</button>
                                </form>
                                <div id="configMetricasMsg"></div>
                            </div>

                            <!-- Filtros Dinâmicos de Ranking -->
                            <div class="mb-4">
                                <h5 class="mb-3" style="color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 5px;">
                                    <i class="fas fa-filter me-2"></i>Filtros Dinâmicos de Ranking
                                </h5>
                                <form id="configFiltrosForm">
                                    <div class="config-row-2 config-row-2--mb-lg">
                                        <div class="form-group">
                                            <label for="periodo_padrao">Período Padrão do Ranking</label>
                                            <select id="periodo_padrao" name="periodo_padrao">
                                                <option value="semanal">Semanal</option>
                                                <option value="mensal" selected>Mensal</option>
                                                <option value="trimestral">Trimestral</option>
                                                <option value="anual">Anual</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="filtro_por_veiculo">Filtrar por Tipo de Veículo</label>
                                            <select id="filtro_por_veiculo" name="filtro_por_veiculo">
                                                <option value="todos">Todos os Veículos</option>
                                                <option value="caminhao">Caminhões</option>
                                                <option value="van">Vans</option>
                                                <option value="carreta">Carretas</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="filtro_por_rota">Filtrar por Tipo de Rota</label>
                                            <select id="filtro_por_rota" name="filtro_por_rota">
                                                <option value="todas">Todas as Rotas</option>
                                                <option value="urbana">Urbana</option>
                                                <option value="interurbana">Interurbana</option>
                                                <option value="longa_distancia">Longa Distância</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="comparacao_filiais">Comparação entre Filiais</label>
                                            <select id="comparacao_filiais" name="comparacao_filiais">
                                                <option value="desabilitada">Desabilitada</option>
                                                <option value="habilitada">Habilitada</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" id="filtros_ativos" name="filtros_ativos" checked> 
                                            Filtros Dinâmicos Ativos
                                        </label>
                                    </div>
                                    
                                    <button type="submit" class="btn-primary">Salvar Configurações de Filtros</button>
                                </form>
                                <div id="configFiltrosMsg"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" class="config-scroll-top" id="configScrollTopBtn" aria-label="Voltar ao topo" title="Voltar ao topo">
                    <i class="fas fa-arrow-up" aria-hidden="true"></i>
                </button>
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
        const form = this;
        const nomeCert = document.getElementById('nome_certificado') ? document.getElementById('nome_certificado').value.trim() : '';
        const formData = new FormData(form);
        formData.append('action', 'upload_certificado');
        
        const msg = document.getElementById('certificadoMsg');
        msg.textContent = '';
        
        fetch('../api/configuracoes.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                msg.textContent = 'Certificado enviado com sucesso!';
                msg.style.color = 'green';
                // Limpar apenas senha e arquivo, manter nome e data preenchidos
                const senha = document.getElementById('senha_certificado');
                const arquivo = document.getElementById('arquivo_certificado');
                if (senha) senha.value = '';
                if (arquivo) arquivo.value = '';

                // Atualizar nome e data de validade no formulário
                const nomeInput = document.getElementById('nome_certificado');
                if (nomeInput && nomeCert) {
                    nomeInput.value = nomeCert;
                }
                if (res.data_validade) {
                    const dt = document.getElementById('data_validade');
                    if (dt) dt.value = res.data_validade;
                }

                // Atualizar texto do certificado atual sem precisar recarregar a página
                const infoDiv = document.querySelector('.dashboard-card .card-body .form-text strong');
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
                        var rntrcEl = document.getElementById('rntrc');
                        if (rntrcEl) rntrcEl.value = data.data.rntrc || '';
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
            rntrc: (formData.get('rntrc') || '').trim(),
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


    // Funções de Teste SEFAZ (usam APIs existentes e exibem resultado na própria página)
    function testarSefaz() {
        const msg = document.getElementById('testeSefazMsg');
        msg.innerHTML = '<div style="color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px;"><strong>🔄 Testando conexão SEFAZ...</strong></div>';
        fetch('../fiscal/api/sefaz_status.php?action=status&force=true', { credentials: 'include' })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    let html = '<div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 5px; margin-top: 8px;">';
                    html += '<strong>✅ ' + (data.status_texto || 'Conexão SEFAZ') + '</strong><br>';
                    html += '<small>Status: ' + (data.status_geral || '-') + ' | ' + (data.timestamp || '') + '</small>';
                    if (data.detalhes && data.detalhes.conexao_basica) {
                        html += '<br><small>Conexão básica: ' + (data.detalhes.conexao_basica.sucesso ? 'OK' : 'Falha') + ' (' + (data.detalhes.conexao_basica.tempo || '') + ' ms)</small>';
                    }
                    if (data.detalhes && data.detalhes.requisicao_soap) {
                        html += ' | SOAP: ' + (data.detalhes.requisicao_soap.sucesso ? 'OK' : 'Falha');
                    }
                    html += '</div>';
                    msg.innerHTML = html;
                } else {
                    msg.innerHTML = '<div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 5px;"><strong>❌ Erro:</strong> ' + (data.error || 'Falha na conexão') + '</div>';
                }
            })
            .catch(err => {
                msg.innerHTML = '<div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 5px;"><strong>❌ Erro ao testar:</strong> ' + err.message + '</div>';
            });
    }

    function testarCertificado() {
        const msg = document.getElementById('testeSefazMsg');
        msg.innerHTML = '<div style="color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px;"><strong>🔐 Testando certificado e conexão SEFAZ...</strong></div>';
        fetch('../fiscal/api/validar_sefaz.php', { credentials: 'include' })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    let html = '<div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 5px; margin-top: 8px;">';
                    html += '<strong>✅ Validação concluída</strong><br>';
                    if (data.certificado) {
                        html += '<strong>Certificado:</strong> ' + (data.certificado.valido ? 'Válido' : 'Inválido') + '<br>';
                        if (data.certificado.info) {
                            html += '<small>Vencimento: ' + (data.certificado.info.data_vencimento || '-') + '</small><br>';
                        }
                        if (data.certificado.detalhes && data.certificado.detalhes.avisos && data.certificado.detalhes.avisos.length) {
                            html += '<small class="text-warning">' + data.certificado.detalhes.avisos.join('; ') + '</small><br>';
                        }
                    }
                    if (data.conexao_sefaz) {
                        html += '<strong>SEFAZ:</strong> ' + (data.conexao_sefaz.status_geral || '-') + ' (ambiente: ' + (data.conexao_sefaz.ambiente || '-') + ')</small>';
                    }
                    html += '</div>';
                    msg.innerHTML = html;
                } else {
                    msg.innerHTML = '<div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 5px;"><strong>❌ Erro:</strong> ' + (data.error || 'Certificado não encontrado ou falha na validação') + '</div>';
                }
            })
            .catch(err => {
                msg.innerHTML = '<div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 5px;"><strong>❌ Erro ao testar:</strong> ' + err.message + '</div>';
            });
    }

    function converterCertificado() {
        const msg = document.getElementById('testeSefazMsg');
        msg.innerHTML = '<div style="background: #e7f3ff; color: #004085; padding: 12px; border-radius: 5px;">' +
            '<strong>📄 Converter certificado</strong><br>' +
            'O certificado .pfx/.p12 é processado automaticamente ao ser enviado no bloco <strong>Certificado Digital A1</strong> (acima nesta página). ' +
            'Não é necessário converter manualmente — faça o upload do arquivo .pfx ou .p12 e informe a senha.</div>';
    }

    function diagnosticoSefaz() {
        const msg = document.getElementById('testeSefazMsg');
        msg.innerHTML = '<div style="color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px;"><strong>🔍 Diagnóstico completo (certificado + SEFAZ)...</strong></div>';
        Promise.all([
            fetch('../fiscal/api/validar_sefaz.php', { credentials: 'include' }).then(r => r.json()),
            fetch('../fiscal/api/sefaz_status.php?action=status&force=true', { credentials: 'include' }).then(r => r.json())
        ]).then(([dataValidar, dataStatus]) => {
            let html = '<div style="margin-top: 8px;">';
            if (dataValidar.success) {
                html += '<div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 8px;">';
                html += '<strong>Certificado:</strong> ' + (dataValidar.certificado && dataValidar.certificado.valido ? 'Válido' : 'Verificar') + '<br>';
                if (dataValidar.conexao_sefaz) {
                    html += '<strong>SEFAZ (validar_sefaz):</strong> ' + (dataValidar.conexao_sefaz.status_geral || '-') + '<br>';
                    if (dataValidar.conexao_sefaz.servicos) {
                        Object.keys(dataValidar.conexao_sefaz.servicos).forEach(s => {
                            const r = dataValidar.conexao_sefaz.servicos[s];
                            html += '<small>' + s.toUpperCase() + ': ' + (r.status || r.mensagem) + '</small><br>';
                        });
                    }
                }
                html += '</div>';
            } else {
                html += '<div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 8px;"><strong>Certificado/Validação:</strong> ' + (dataValidar.error || 'Erro') + '</div>';
            }
            if (dataStatus.success) {
                html += '<div style="background: #e7f3ff; color: #004085; padding: 10px; border-radius: 5px;">';
                html += '<strong>Status SEFAZ (API status):</strong> ' + (dataStatus.status_geral || '-') + ' — ' + (dataStatus.status_texto || '') + '</div>';
            }
            html += '</div>';
            msg.innerHTML = html;
        }).catch(err => {
            msg.innerHTML = '<div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 5px;"><strong>❌ Erro no diagnóstico:</strong> ' + err.message + '</div>';
        });
    }

    // Funções do Google Maps
    function loadGoogleMapsConfig() {
        fetch('../google-maps/api.php?action=get_config', {
            credentials: 'include'
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('google_maps_api_key').value = data.data.google_maps_api_key || '';
                }
            })
            .catch(error => {
                console.error('Erro ao carregar configurações do Google Maps:', error);
            });
    }

    // Testar chave da API
    document.getElementById('testApiKeyBtn').addEventListener('click', function() {
        const apiKey = document.getElementById('google_maps_api_key').value.trim();
        
        if (!apiKey) {
            showGoogleMapsMessage('Digite uma chave da API primeiro', 'error');
            return;
        }

        const btn = this;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testando...';
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'test_api_key');
        formData.append('api_key', apiKey);

        fetch('../google-maps/api.php', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showGoogleMapsMessage('✅ ' + data.message, 'success');
            } else {
                showGoogleMapsMessage('❌ ' + data.message, 'error');
            }
        })
        .catch(error => {
            showGoogleMapsMessage('❌ Erro ao testar chave: ' + error.message, 'error');
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    });

    // Salvar configuração do Google Maps
    document.getElementById('googleMapsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const apiKey = document.getElementById('google_maps_api_key').value.trim();
        
        if (!apiKey) {
            showGoogleMapsMessage('Digite uma chave da API', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'save_config');
        formData.append('google_maps_api_key', apiKey);

        const btn = this.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
        btn.disabled = true;

        fetch('../google-maps/api.php', {
            method: 'POST',
            body: formData,
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showGoogleMapsMessage('✅ ' + data.message, 'success');
            } else {
                showGoogleMapsMessage('❌ ' + data.message, 'error');
            }
        })
        .catch(error => {
            showGoogleMapsMessage('❌ Erro ao salvar: ' + error.message, 'error');
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    });

    function showGoogleMapsMessage(message, type) {
        const msgDiv = document.getElementById('googleMapsMsg');
        const color = type === 'success' ? '#28a745' : '#dc3545';
        const bgColor = type === 'success' ? '#d4edda' : '#f8d7da';
        
        msgDiv.innerHTML = `<div style="color: ${color}; background: ${bgColor}; padding: 10px; border-radius: 5px; border: 1px solid ${color};">${message}</div>`;
        
        // Limpar mensagem após 5 segundos
        setTimeout(() => {
            msgDiv.innerHTML = '';
        }, 5000);
    }

    // Consulta de Multas (DETRAN) - carregar configuração e info do certificado
    function loadDenatranConfig() {
        const form = document.getElementById('denatranConfigForm');
        const certInfo = document.getElementById('denatranCertInfo');
        if (!form) return;
        fetch('../api/configuracoes.php?action=get_config_denatran', { credentials: 'include' })
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data) {
                    const d = res.data;
                    const hab = document.getElementById('denatran_habilitado');
                    const url = document.getElementById('denatran_base_url');
                    if (hab) hab.value = d.habilitado ? '1' : '0';
                    if (url) url.value = d.base_url || url.options[0]?.value || '';
                    const cpf = document.getElementById('denatran_cpf_usuario');
                    if (cpf) cpf.value = d.cpf_usuario || '';
                    if (certInfo) {
                        if (d.certificado && d.certificado.nome_certificado) {
                            const val = d.certificado.data_validade ? new Date(d.certificado.data_validade).toLocaleDateString('pt-BR') : '-';
                            certInfo.innerHTML = 'Certificado atual: <strong>' + (d.certificado.nome_certificado || '') + '</strong>. Validade: ' + val + '.';
                        } else {
                            certInfo.textContent = 'Nenhum certificado enviado. Envie um certificado .pfx ou .p12 (igual ao A1).';
                        }
                    }
                }
            })
            .catch(() => {});
    }
    loadDenatranConfig();

    document.getElementById('denatranConfigForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const msg = document.getElementById('denatranConfigMsg');
        const btn = document.getElementById('saveDenatranConfigBtn');
        msg.textContent = '';
        msg.style.color = '';
        const payload = {
            action: 'save_config_denatran',
            habilitado: document.getElementById('denatran_habilitado').value === '1' ? 1 : 0,
            base_url: document.getElementById('denatran_base_url').value.trim(),
            cpf_usuario: document.getElementById('denatran_cpf_usuario').value.trim()
        };
        const origText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
        btn.disabled = true;
        fetch('../api/configuracoes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            credentials: 'include'
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                msg.textContent = res.message || 'Configuração salva com sucesso!';
                msg.style.color = 'green';
            } else {
                msg.textContent = res.error || 'Erro ao salvar.';
                msg.style.color = 'red';
            }
        })
        .catch(() => {
            msg.textContent = 'Erro ao salvar.';
            msg.style.color = 'red';
        })
        .finally(() => {
            btn.innerHTML = origText;
            btn.disabled = false;
        });
    });

    document.getElementById('denatranCertForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const msg = document.getElementById('denatranCertMsg');
        const btn = document.getElementById('uploadDenatranCertBtn');
        const fd = new FormData(this);
        fd.append('action', 'upload_certificado_denatran');
        fd.append('nome_certificado', document.getElementById('denatran_nome_certificado').value.trim() || 'Certificado DETRAN');
        fd.append('data_validade', document.getElementById('denatran_data_validade').value || '');
        fd.append('senha_certificado', document.getElementById('denatran_senha_certificado').value);
        const origText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
        btn.disabled = true;
        msg.textContent = '';
        msg.style.color = '';
        fetch('../api/configuracoes.php', {
            method: 'POST',
            body: fd,
            credentials: 'include'
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                msg.textContent = res.message || 'Certificado enviado com sucesso!';
                msg.style.color = 'green';
                document.getElementById('denatran_senha_certificado').value = '';
                document.getElementById('denatran_arquivo_certificado').value = '';
                loadDenatranConfig();
            } else {
                msg.textContent = res.error || 'Erro ao enviar certificado.';
                msg.style.color = 'red';
            }
        })
        .catch(() => {
            msg.textContent = 'Erro ao enviar certificado.';
            msg.style.color = 'red';
        })
        .finally(() => {
            btn.innerHTML = origText;
            btn.disabled = false;
        });
    });

    // Funções de Gamificação e Ranking (configurações apenas)
    function calcularGamificacao() {
        const status = document.getElementById('gamificacaoStatus');
        status.innerHTML = '<div style="color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px;">Calculando gamificação...</div>';
        
        fetch('../api/gamificacao_motoristas.php?action=calcular_gamificacao', {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                status.innerHTML = '<div style="color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px;">✅ ' + res.message + '</div>';
            } else {
                status.innerHTML = '<div style="color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px;">❌ ' + (res.error || 'Erro ao calcular gamificação') + '</div>';
            }
        })
        .catch(() => {
            status.innerHTML = '<div style="color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px;">❌ Erro ao calcular gamificação</div>';
        });
    }

    function calcularRanking() {
        const status = document.getElementById('rankingStatus');
        status.innerHTML = '<div style="color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px;">Calculando ranking...</div>';
        
        fetch('../api/ranking_motoristas.php?action=calcular_ranking', {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                status.innerHTML = '<div style="color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px;">✅ ' + res.message + '</div>';
            } else {
                status.innerHTML = '<div style="color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px;">❌ ' + (res.error || 'Erro ao calcular ranking') + '</div>';
            }
        })
        .catch(() => {
            status.innerHTML = '<div style="color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px;">❌ Erro ao calcular ranking</div>';
        });
    }

    // Configurações de Performance
    document.getElementById('configPerformanceForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const msg = document.getElementById('configPerformanceMsg');
        msg.textContent = '';
        
        // Validar se a soma dos pesos é 100%
        const peso_pontualidade = parseInt(formData.get('peso_pontualidade'));
        const peso_consumo = parseInt(formData.get('peso_consumo'));
        const peso_multas = parseInt(formData.get('peso_multas'));
        const peso_checklist = parseInt(formData.get('peso_checklist'));
        const peso_ocorrencias = parseInt(formData.get('peso_ocorrencias'));
        
        const total_pesos = peso_pontualidade + peso_consumo + peso_multas + peso_checklist + peso_ocorrencias;
        
        if (total_pesos !== 100) {
            msg.textContent = 'A soma dos pesos deve ser igual a 100%. Atual: ' + total_pesos + '%';
            msg.style.color = 'red';
            return;
        }
        
        const dados = {
            action: 'save_config_performance',
            peso_pontualidade: peso_pontualidade,
            peso_consumo: peso_consumo,
            peso_multas: peso_multas,
            peso_checklist: peso_checklist,
            peso_ocorrencias: peso_ocorrencias,
            pontos_maximos: parseInt(formData.get('pontos_maximos')),
            gamificacao_ativa: formData.get('gamificacao_ativa') === 'on',
            ranking_automatico: formData.get('ranking_automatico') === 'on'
        };
        
        fetch('../api/configuracoes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                msg.textContent = 'Configurações de performance salvas com sucesso!';
                msg.style.color = 'green';
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

    // Carregar configurações de performance existentes
    async function loadConfiguracoesPerformance() {
        try {
            console.log('Carregando configurações de performance...');
            const response = await fetch('../api/configuracoes.php?action=get_config_performance', {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });
            
            console.log('Response status:', response.status);
            
            if (response.ok) {
                const data = await response.json();
                console.log('Data received:', data);
                
                if (data.success && data.data) {
                    // Preencher formulário com dados existentes
                    const peso_pontualidade = document.getElementById('peso_pontualidade');
                    const peso_consumo = document.getElementById('peso_consumo');
                    const peso_multas = document.getElementById('peso_multas');
                    const peso_checklist = document.getElementById('peso_checklist');
                    const peso_ocorrencias = document.getElementById('peso_ocorrencias');
                    const pontos_maximos = document.getElementById('pontos_maximos');
                    const gamificacao_ativa = document.getElementById('gamificacao_ativa');
                    const ranking_automatico = document.getElementById('ranking_automatico');
                    
                    if (peso_pontualidade) peso_pontualidade.value = data.data.peso_pontualidade || 25;
                    if (peso_consumo) peso_consumo.value = data.data.peso_consumo || 30;
                    if (peso_multas) peso_multas.value = data.data.peso_multas || 20;
                    if (peso_checklist) peso_checklist.value = data.data.peso_checklist || 15;
                    if (peso_ocorrencias) peso_ocorrencias.value = data.data.peso_ocorrencias || 10;
                    if (pontos_maximos) pontos_maximos.value = data.data.pontos_maximos || 1000;
                    if (gamificacao_ativa) gamificacao_ativa.checked = data.data.gamificacao_ativa == 1;
                    if (ranking_automatico) ranking_automatico.checked = data.data.ranking_automatico == 1;
                    
                    console.log('Formulário preenchido com sucesso');
                } else {
                    console.error('Erro na resposta da API:', data);
                }
            } else {
                console.error('Erro HTTP:', response.status, response.statusText);
            }
        } catch (error) {
            console.error('Erro ao carregar configurações de performance:', error);
        }
    }

    // Event listeners para os novos formulários de gamificação
    document.getElementById('configBadgesForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const msg = document.getElementById('configBadgesMsg');
        msg.textContent = '';
        
        const dados = {
            action: 'save_config_badges',
            badge_motorista_economico: parseInt(formData.get('badge_motorista_economico')),
            badge_sem_multas: parseInt(formData.get('badge_sem_multas')),
            badge_checklists_perfeitos: parseInt(formData.get('badge_checklists_perfeitos')),
            pontos_por_badge: parseInt(formData.get('pontos_por_badge')),
            sistema_badges_ativo: formData.get('sistema_badges_ativo') === 'on'
        };
        
        fetch('../api/configuracoes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                msg.textContent = 'Configurações de badges salvas com sucesso!';
                msg.style.color = 'green';
            } else {
                msg.textContent = res.error || 'Erro ao salvar configurações de badges.';
                msg.style.color = 'red';
            }
        })
        .catch(() => {
            msg.textContent = 'Erro ao salvar configurações de badges.';
            msg.style.color = 'red';
        });
    });

    document.getElementById('configNiveisForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const msg = document.getElementById('configNiveisMsg');
        msg.textContent = '';
        
        const dados = {
            action: 'save_config_niveis',
            nivel_bronze_min: parseInt(formData.get('nivel_bronze_min')),
            nivel_bronze_max: parseInt(formData.get('nivel_bronze_max')),
            nivel_prata_min: parseInt(formData.get('nivel_prata_min')),
            nivel_prata_max: parseInt(formData.get('nivel_prata_max')),
            nivel_ouro_min: parseInt(formData.get('nivel_ouro_min')),
            nivel_ouro_max: parseInt(formData.get('nivel_ouro_max')),
            nivel_platina_min: parseInt(formData.get('nivel_platina_min')),
            nivel_platina_max: parseInt(formData.get('nivel_platina_max')),
            nivel_diamante_min: parseInt(formData.get('nivel_diamante_min')),
            nivel_diamante_max: parseInt(formData.get('nivel_diamante_max')),
            nivel_lenda_min: parseInt(formData.get('nivel_lenda_min')),
            nivel_lenda_max: parseInt(formData.get('nivel_lenda_max')),
            niveis_avancados_ativos: formData.get('niveis_avancados_ativos') === 'on'
        };
        
        fetch('../api/configuracoes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                msg.textContent = 'Configurações de níveis salvas com sucesso!';
                msg.style.color = 'green';
            } else {
                msg.textContent = res.error || 'Erro ao salvar configurações de níveis.';
                msg.style.color = 'red';
            }
        })
        .catch(() => {
            msg.textContent = 'Erro ao salvar configurações de níveis.';
            msg.style.color = 'red';
        });
    });

    document.getElementById('configDesafiosForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const msg = document.getElementById('configDesafiosMsg');
        msg.textContent = '';
        
        const dados = {
            action: 'save_config_desafios',
            desafio_km_sem_infracoes: parseInt(formData.get('desafio_km_sem_infracoes')),
            desafio_rotas_sem_atrasos: parseInt(formData.get('desafio_rotas_sem_atrasos')),
            desafio_economia_combustivel: parseFloat(formData.get('desafio_economia_combustivel')),
            pontos_desafio_completo: parseInt(formData.get('pontos_desafio_completo')),
            desafios_ativos: formData.get('desafios_ativos') === 'on',
            feedback_imediato: formData.get('feedback_imediato') === 'on'
        };
        
        fetch('../api/configuracoes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                msg.textContent = 'Configurações de desafios salvas com sucesso!';
                msg.style.color = 'green';
            } else {
                msg.textContent = res.error || 'Erro ao salvar configurações de desafios.';
                msg.style.color = 'red';
            }
        })
        .catch(() => {
            msg.textContent = 'Erro ao salvar configurações de desafios.';
            msg.style.color = 'red';
        });
    });

    // Event listeners para os formulários de ranking
    document.getElementById('configMetricasForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const msg = document.getElementById('configMetricasMsg');
        msg.textContent = '';
        
        const dados = {
            action: 'save_config_metricas',
            peso_ocorrencias_sinistros: parseInt(formData.get('peso_ocorrencias_sinistros')),
            peso_custos_km: parseInt(formData.get('peso_custos_km')),
            peso_feedback_cliente: parseInt(formData.get('peso_feedback_cliente')),
            peso_manutencao_preventiva: parseInt(formData.get('peso_manutencao_preventiva')),
            metricas_avancadas_ativas: formData.get('metricas_avancadas_ativas') === 'on'
        };
        
        fetch('../api/configuracoes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                msg.textContent = 'Métricas avançadas salvas com sucesso!';
                msg.style.color = 'green';
            } else {
                msg.textContent = res.error || 'Erro ao salvar métricas avançadas.';
                msg.style.color = 'red';
            }
        })
        .catch(() => {
            msg.textContent = 'Erro ao salvar métricas avançadas.';
            msg.style.color = 'red';
        });
    });

    document.getElementById('configFiltrosForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const msg = document.getElementById('configFiltrosMsg');
        msg.textContent = '';
        
        const dados = {
            action: 'save_config_filtros',
            periodo_padrao: formData.get('periodo_padrao'),
            filtro_por_veiculo: formData.get('filtro_por_veiculo'),
            filtro_por_rota: formData.get('filtro_por_rota'),
            comparacao_filiais: formData.get('comparacao_filiais'),
            filtros_ativos: formData.get('filtros_ativos') === 'on'
        };
        
        fetch('../api/configuracoes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                msg.textContent = 'Configurações de filtros salvas com sucesso!';
                msg.style.color = 'green';
            } else {
                msg.textContent = res.error || 'Erro ao salvar configurações de filtros.';
                msg.style.color = 'red';
            }
        })
        .catch(() => {
            msg.textContent = 'Erro ao salvar configurações de filtros.';
            msg.style.color = 'red';
        });
    });

    // Carregar configurações ao inicializar
    document.addEventListener('DOMContentLoaded', function() {
        loadConfiguracoesFiscais();
        loadGoogleMapsConfig();
        
        // Aguardar um pouco para garantir que o DOM esteja totalmente carregado
        setTimeout(() => {
            loadConfiguracoesPerformance();
        }, 500);
    });
    
    // Event listeners para as configurações de funcionalidades avançadas
    document.getElementById('configNotificacoesForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'save_notifications_config');
        const msg = document.getElementById('configNotificacoesMsg');
        msg.textContent = '';
        
        fetch('../api/configuracoes.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                msg.textContent = 'Configurações de notificações salvas com sucesso!';
                msg.style.color = 'green';
            } else {
                msg.textContent = res.error || 'Erro ao salvar configurações de notificações.';
                msg.style.color = 'red';
            }
        })
        .catch(() => {
            msg.textContent = 'Erro ao salvar configurações de notificações.';
            msg.style.color = 'red';
        });
    });

    document.getElementById('configCacheForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'save_cache_config');
        const msg = document.getElementById('configCacheMsg');
        msg.textContent = '';
        
        fetch('../api/configuracoes.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                msg.textContent = 'Configurações de cache salvas com sucesso!';
                msg.style.color = 'green';
            } else {
                msg.textContent = res.error || 'Erro ao salvar configurações de cache.';
                msg.style.color = 'red';
            }
        })
        .catch(() => {
            msg.textContent = 'Erro ao salvar configurações de cache.';
            msg.style.color = 'red';
        });
    });
    
    function limparCache() {
        if (confirm('Tem certeza que deseja limpar todo o cache? Esta ação não pode ser desfeita.')) {
            fetch('../api/configuracoes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'clear_cache' })
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    alert('Cache limpo com sucesso!');
                } else {
                    alert('Erro ao limpar cache: ' + (res.error || 'Erro desconhecido'));
                }
            })
            .catch(() => alert('Erro ao limpar cache.'));
        }
    }
    
    function salvarConfiguracoesBackground() {
        const dados = {
            background_ativo: document.getElementById('background_ativo').checked,
            background_intervalo: document.getElementById('background_intervalo').value,
            background_relatorios: document.getElementById('background_relatorios').checked
        };
        
        fetch('../api/configuracoes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save_background_config', ...dados })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                alert('Configurações de background processing salvas com sucesso!');
            } else {
                alert('Erro ao salvar configurações: ' + (res.error || 'Erro desconhecido'));
            }
        })
        .catch(() => alert('Erro ao salvar configurações de background processing.'));
    }

    (function () {
        var apiUrl = function () {
            return (typeof sfApiUrl === 'function') ? sfApiUrl('gps_cercas.php') : '../api/gps_cercas.php';
        };
        var jsonHeaders = function () {
            var h = { 'Content-Type': 'application/json' };
            if (typeof window.__SF_CSRF__ === 'string' && window.__SF_CSRF__) {
                h['X-CSRF-Token'] = window.__SF_CSRF__;
            }
            return h;
        };
        var msgEl = document.getElementById('gpsCercasMsg');
        var tbody = document.getElementById('gpsCercasTableBody');
        var form = document.getElementById('gpsCercasCreateForm');
        function showGpsMsg(text, ok) {
            if (!msgEl) return;
            msgEl.textContent = text || '';
            msgEl.style.color = ok ? 'var(--success-color, #16a34a)' : 'var(--danger-color, #dc2626)';
        }
        function loadGpsCercas() {
            if (!tbody) return;
            fetch(apiUrl(), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    tbody.innerHTML = '';
                    if (!j.success || !j.data || !Array.isArray(j.data.cercas)) {
                        tbody.innerHTML = '<tr><td colspan="4" class="form-text">Não foi possível carregar as cercas.</td></tr>';
                        return;
                    }
                    var rows = j.data.cercas;
                    if (rows.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" class="form-text">Nenhuma cerca cadastrada. Use coordenadas de um mapa externo (ex.: clique com o botão direito no Google Maps).</td></tr>';
                        return;
                    }
                    rows.forEach(function (c) {
                        var tr = document.createElement('tr');
                        var td1 = document.createElement('td');
                        td1.textContent = c.nome || '';
                        var td2 = document.createElement('td');
                        td2.textContent = String(c.latitude) + ', ' + String(c.longitude);
                        var td3 = document.createElement('td');
                        td3.textContent = String(c.raio_metros);
                        var td4 = document.createElement('td');
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'btn-gps-cerca-del';
                        btn.textContent = 'Excluir';
                        btn.setAttribute('data-id', String(c.id));
                        btn.addEventListener('click', function () {
                            var id = parseInt(btn.getAttribute('data-id'), 10);
                            if (!id || !window.confirm('Excluir esta cerca?')) return;
                            fetch(apiUrl(), {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: jsonHeaders(),
                                body: JSON.stringify({ action: 'delete', id: id })
                            })
                                .then(function (r) { return r.json(); })
                                .then(function (res) {
                                    if (res.success) {
                                        showGpsMsg(res.message || 'Cerca removida.', true);
                                        loadGpsCercas();
                                    } else {
                                        showGpsMsg(res.message || 'Erro ao excluir.', false);
                                    }
                                })
                                .catch(function () { showGpsMsg('Erro de rede.', false); });
                        });
                        td4.appendChild(btn);
                        tr.appendChild(td1);
                        tr.appendChild(td2);
                        tr.appendChild(td3);
                        tr.appendChild(td4);
                        tbody.appendChild(tr);
                    });
                })
                .catch(function () {
                    tbody.innerHTML = '<tr><td colspan="4" class="form-text">Erro ao carregar cercas.</td></tr>';
                });
        }
        if (form && tbody) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var nome = (document.getElementById('gpsCercaNome') && document.getElementById('gpsCercaNome').value || '').trim();
                var lat = parseFloat(document.getElementById('gpsCercaLat').value.replace(',', '.'));
                var lng = parseFloat(document.getElementById('gpsCercaLng').value.replace(',', '.'));
                var raio = parseInt(document.getElementById('gpsCercaRaio').value, 10) || 500;
                if (!nome) {
                    showGpsMsg('Informe o nome da cerca.', false);
                    return;
                }
                if (isNaN(lat) || isNaN(lng)) {
                    showGpsMsg('Latitude e longitude inválidas.', false);
                    return;
                }
                fetch(apiUrl(), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: jsonHeaders(),
                    body: JSON.stringify({ nome: nome, latitude: lat, longitude: lng, raio_metros: raio })
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res.success) {
                            showGpsMsg(res.message || 'Cerca cadastrada.', true);
                            form.reset();
                            var rIn = document.getElementById('gpsCercaRaio');
                            if (rIn) rIn.value = '500';
                            loadGpsCercas();
                        } else {
                            showGpsMsg(res.message || 'Erro ao salvar.', false);
                        }
                    })
                    .catch(function () { showGpsMsg('Erro de rede.', false); });
            });
            loadGpsCercas();
        }
    })();

    (function () {
        var btn = document.getElementById('configScrollTopBtn');
        var topEl = document.getElementById('configuracoes-top');
        if (!btn || !topEl) return;
        function toggle() {
            var y = window.scrollY || document.documentElement.scrollTop;
            btn.classList.toggle('is-visible', y > 180);
        }
        window.addEventListener('scroll', toggle, { passive: true });
        toggle();
        btn.addEventListener('click', function () {
            topEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    })();

    </script>
    <?php include '../includes/scroll_to_top.php'; ?>
</body>
</html> 