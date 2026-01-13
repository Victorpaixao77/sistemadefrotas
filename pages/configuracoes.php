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
$stmt = $conn->prepare('SELECT nome_personalizado, logo_empresa FROM configuracoes WHERE empresa_id = :empresa_id LIMIT 1');
$stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$nome_personalizado = $row ? $row['nome_personalizado'] : 'Frotec Online';
$logo_path = $row && $row['logo_empresa'] ? $row['logo_empresa'] : 'logo.png';
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
        
        /* Responsividade para o grid */
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr !important;
            }
            
            .form-group[style*="grid-template-columns"] {
                grid-template-columns: 1fr !important;
            }
        }
        
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr !important;
            }
        }
        
        /* Garantir que o grid não afete o layout principal */
        .dashboard-grid {
            max-width: 1200px !important;
            margin: 0 auto !important;
        }
        
        /* Layout do sidebar - usar o padrão do sistema */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition-speed) ease;
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
        }
        
        .sidebar-collapsed .main-content {
            margin-left: var(--sidebar-collapsed-width);
            width: calc(100% - var(--sidebar-collapsed-width));
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }
        
        
        /* Melhorar contraste dos textos de ajuda */
        .form-text {
            color: #333 !important;
            font-size: 0.875rem !important;
            line-height: 1.4 !important;
            margin-top: 0.5rem !important;
        }
        
        .form-text strong {
            color: #000 !important;
            font-weight: 600 !important;
        }
        
        /* Forçar contraste para elementos strong em form-text */
        .form-text strong,
        .form-text b {
            color: #000 !important;
            font-weight: 600 !important;
        }
        
        /* Garantir que textos específicos sejam escuros no modo claro */
        .form-text strong:not(.dark-theme),
        .form-text b:not(.dark-theme) {
            color: #000 !important;
        }
        
        /* Sobrescrever qualquer estilo que possa estar interferindo */
        .dashboard-card .form-text strong,
        .dashboard-card .form-text b {
            color: #000 !important;
        }
        
        .form-text a {
            color: #007bff !important;
            text-decoration: underline !important;
        }
        
        .form-text a:hover {
            color: #0056b3 !important;
        }
        
        /* Modo escuro */
        @media (prefers-color-scheme: dark) {
            .form-text {
                color: #ccc !important;
            }
            
            .form-text strong {
                color: #fff !important;
            }
            
            .form-text a {
                color: #4dabf7 !important;
            }
            
            .form-text a:hover {
                color: #74c0fc !important;
            }
        }
        
        /* Tema escuro ativo */
        .dark-theme .form-text {
            color: #ccc !important;
        }
        
        .dark-theme .form-text strong {
            color: #fff !important;
        }
        
        .dark-theme .form-text a {
            color: #4dabf7 !important;
        }
        
        .dark-theme .form-text a:hover {
            color: #74c0fc !important;
        }
        
        /* Forçar alinhamento correto do sidebar APENAS quando expandido */
        .sidebar-link {
            justify-content: flex-start !important;
            text-align: left !important;
        }
        
        .sidebar-link-icon {
            justify-content: center !important;
            text-align: center !important;
            margin-right: 12px !important;
            position: static !important;
            left: auto !important;
            transform: none !important;
        }
        
        .sidebar-link-text {
            text-align: left !important;
        }
        
        /* MANTER comportamento responsivo correto quando colapsado */
        .sidebar-collapsed .sidebar-link {
            justify-content: center !important;
            text-align: center !important;
        }
        
        .sidebar-collapsed .sidebar-link-icon {
            justify-content: center !important;
            text-align: center !important;
            margin-right: 0 !important;
            position: absolute !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
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
                <div class="dashboard-grid" style="max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 20px;">
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
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <div class="form-group">
                                        <label for="nome_certificado">Nome do Certificado</label>
                                        <input type="text" id="nome_certificado" name="nome_certificado" placeholder="Ex: Certificado Empresa 2025" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="data_validade">Data de Validade</label>
                                        <input type="date" id="data_validade" name="data_validade" required>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="arquivo_certificado">Arquivo do Certificado (.pfx, .p12)</label>
                                    <input type="file" id="arquivo_certificado" name="arquivo_certificado" accept=".pfx,.p12" required>
                                    <small class="form-text">Formatos aceitos: .pfx, .p12 (máx. 10MB)</small>
                                </div>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
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
                            <div id="certificadoMsg" style="margin-top:10px;"></div>
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
                                <div class="form-group" style="display: flex; gap: 10px; align-items: center;">
                                    <button type="button" id="testApiKeyBtn" class="btn-secondary">
                                        <i class="fas fa-check-circle"></i> Testar Chave
                                    </button>
                                    <button type="submit" class="btn-primary">
                                        <i class="fas fa-save"></i> Salvar Configuração
                                    </button>
                                </div>
                            </form>
                            <div id="googleMapsMsg" style="margin-top:10px;"></div>
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

                    <!-- ======================================== -->
                    <!-- CONFIGURAÇÕES DE FUNCIONALIDADES AVANÇADAS -->
                    <!-- ======================================== -->
                    
                    <div class="dashboard-card">
                        <div class="card-header" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); color: white;">
                            <h3>⚙️ Configurações de Funcionalidades Avançadas</h3>
                        </div>
                        <div class="card-body">
                            <!-- Sistema de Notificações -->
                            <div class="mb-4">
                                <h5 class="mb-3" style="color: #2c3e50; border-bottom: 2px solid #2c3e50; padding-bottom: 5px;">
                                    <i class="fas fa-bell me-2"></i>Sistema de Notificações
                                </h5>
                                <form id="configNotificacoesForm">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
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
                                <div id="configNotificacoesMsg" style="margin-top:10px;"></div>
                            </div>

                            <!-- Sistema de Cache -->
                            <div class="mb-4">
                                <h5 class="mb-3" style="color: #2c3e50; border-bottom: 2px solid #2c3e50; padding-bottom: 5px;">
                                    <i class="fas fa-database me-2"></i>Sistema de Cache
                                </h5>
                                <form id="configCacheForm">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
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
                                <div id="configCacheMsg" style="margin-top:10px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- ======================================== -->
                    <!-- CONFIGURAÇÕES DE GAMIFICAÇÃO -->
                    <!-- ======================================== -->
                    
                    <div class="dashboard-card">
                        <div class="card-header" style="background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%); color: white;">
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
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
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
                                <div id="configBadgesMsg" style="margin-top:10px;"></div>
                            </div>

                            <!-- Sistema de Níveis Avançado -->
                            <div class="mb-4">
                                <h5 class="mb-3" style="color: #ff6b35; border-bottom: 2px solid #ff6b35; padding-bottom: 5px;">
                                    <i class="fas fa-star me-2"></i>Sistema de Níveis Avançado
                                </h5>
                                <form id="configNiveisForm">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
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
                                <div id="configNiveisMsg" style="margin-top:10px;"></div>
                            </div>

                            <!-- Sistema de Desafios -->
                            <div class="mb-4">
                                <h5 class="mb-3" style="color: #ff6b35; border-bottom: 2px solid #ff6b35; padding-bottom: 5px;">
                                    <i class="fas fa-target me-2"></i>Sistema de Desafios
                                </h5>
                                <form id="configDesafiosForm">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
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
                                <div id="configDesafiosMsg" style="margin-top:10px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- ======================================== -->
                    <!-- CONFIGURAÇÕES DE RANKING -->
                    <!-- ======================================== -->
                    
                    <div class="dashboard-card">
                        <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
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
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
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
                                <div id="configPerformanceMsg" style="margin-top:10px;"></div>
                            </div>

                            <!-- Métricas Avançadas de Avaliação -->
                            <div class="mb-4">
                                <h5 class="mb-3" style="color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 5px;">
                                    <i class="fas fa-chart-bar me-2"></i>Métricas Avançadas de Avaliação
                                </h5>
                                <form id="configMetricasForm">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
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
                                <div id="configMetricasMsg" style="margin-top:10px;"></div>
                            </div>

                            <!-- Filtros Dinâmicos de Ranking -->
                            <div class="mb-4">
                                <h5 class="mb-3" style="color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 5px;">
                                    <i class="fas fa-filter me-2"></i>Filtros Dinâmicos de Ranking
                                </h5>
                                <form id="configFiltrosForm">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
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
                                <div id="configFiltrosMsg" style="margin-top:10px;"></div>
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
    </script>
</body>
</html> 