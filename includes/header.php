<?php
require_once __DIR__ . '/sf_api_base.php';
// Get the current page name from the URL
$current_page = basename($_SERVER['PHP_SELF'], '.php');
// Only set default page title if not already defined
if (!isset($page_title)) {
    $page_title = ucfirst($current_page);  // Default page title
}

// Dados do usuário logado
$nome_usuario = isset($_SESSION['nome']) ? $_SESSION['nome'] : '';
$foto_perfil = isset($_SESSION['foto_perfil']) && $_SESSION['foto_perfil'] ? $_SESSION['foto_perfil'] : null;
$letra = $nome_usuario ? mb_substr($nome_usuario, 0, 1, 'UTF-8') : 'U';
if (!function_exists('get_user_role_label')) {
    require_once __DIR__ . '/permissions.php';
}
$cargo_usuario = get_user_role_label();

// Carregar sistema de notificações (caminho relativo ao diretório do header)
require_once __DIR__ . '/notifications.php';

$sf_platform_banner = null;
if (!empty($_SESSION['empresa_id']) && is_file(__DIR__ . '/sf_platform_banner.php')) {
    require_once __DIR__ . '/sf_platform_banner.php';
    try {
        $sf_platform_banner = sf_platform_banner_atual(getConnection(), (int) $_SESSION['empresa_id']);
    } catch (Throwable $e) {
        $sf_platform_banner = null;
    }
}

$sf_header_mod_ia = true;
if (!empty($_SESSION['empresa_id'])) {
    if (!function_exists('sf_modulo_ativo_sessao')) {
        require_once __DIR__ . '/sf_saas.php';
    }
    try {
        $sf_header_mod_ia = sf_modulo_ativo_sessao(getConnection(), 'ia');
    } catch (Throwable $e) {
    }
}

// You can override this in specific pages by setting $page_title before including header.php
?>
<?php if (!empty($_SESSION['adm_impersonating'])): ?>
<div class="adm-cc-support-banner" role="alert" style="margin:0;border-radius:0;border-left:0;border-right:0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem">
    <span><i class="fas fa-user-secret"></i> <strong>Modo suporte ativo</strong> — sessão assumida por administrador da plataforma. Todas as ações são auditadas.</span>
    <a href="<?php echo htmlspecialchars(sf_app_url('sair_suporte.php'), ENT_QUOTES, 'UTF-8'); ?>" class="adm-cc-btn adm-cc-btn--sm" style="background:#fff;color:#92400e">Encerrar suporte</a>
</div>
<?php endif; ?>
<?php if (!empty($sf_platform_banner)): ?>
<div id="sfPlatformBannerModal" class="sf-platform-banner" data-banner-id="<?php echo (int) $sf_platform_banner['id'] . '_' . htmlspecialchars($sf_platform_banner['rev'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" role="dialog" aria-modal="true" aria-labelledby="sfPlatformBannerTitle" hidden>
    <div class="sf-platform-banner__backdrop" aria-hidden="true"></div>
    <div class="sf-platform-banner__card">
        <div class="sf-platform-banner__icon" aria-hidden="true"><i class="fas fa-bullhorn"></i></div>
        <h2 id="sfPlatformBannerTitle" class="sf-platform-banner__title"><?php echo htmlspecialchars($sf_platform_banner['titulo'], ENT_QUOTES, 'UTF-8'); ?></h2>
        <?php if ($sf_platform_banner['mensagem'] !== ''): ?>
        <p class="sf-platform-banner__msg"><?php echo nl2br(htmlspecialchars($sf_platform_banner['mensagem'], ENT_QUOTES, 'UTF-8')); ?></p>
        <?php endif; ?>
        <button type="button" class="sf-platform-banner__btn" data-sf-banner-close>Entendi</button>
    </div>
</div>
<link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('css/sf-platform-banner.css?v=' . (function_exists('sf_asset_v') ? sf_asset_v() : '1'))); ?>">
<script src="<?php echo htmlspecialchars(sf_app_url('js/sf-platform-banner.js?v=' . (function_exists('sf_asset_v') ? sf_asset_v() : '1'))); ?>"></script>
<?php endif; ?>
<header class="top-header" role="banner">
    <div class="header-left">
        <button type="button" class="mobile-menu-toggle" id="mobileMenuToggleBtn" aria-label="Abrir menu de navegação" aria-expanded="false" aria-controls="sidebar-nav">
            <i class="fas fa-bars" aria-hidden="true"></i>
        </button>
        <h1 class="header-title"><?php echo $page_title; ?></h1>
    </div>
    
    <div class="header-controls">
        <!-- Botão BI Frota -->
        <?php if (function_exists('can_access_advanced_reports') && can_access_advanced_reports() && $sf_header_mod_ia): ?>
        <a href="<?php echo htmlspecialchars(sf_app_url('pages/bi.php')); ?>" class="header-icon-btn header-bi-btn" title="BI Frota" aria-label="Abrir BI Frota" style="background: var(--bg-tertiary); width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 50%; padding: 0; text-decoration: none; flex-shrink: 0;">
            <img src="<?php echo htmlspecialchars(sf_app_url('bi/bi-icon.png')); ?>" alt="" width="48" height="48" style="width:100%; height:100%; display:block; object-fit:contain; margin:0; padding:0;" />
        </a>
        <?php endif; ?>
        <?php if ($sf_header_mod_ia): ?>
        <!-- Botão IA Flutuante -->
        <button type="button" class="header-icon-btn" title="IA Notificações" aria-label="Assistente de notificações IA" id="iaFabBtn" style="background: var(--bg-tertiary); color: #2563eb; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 50%; border: none; cursor: pointer; padding: 0; position: relative;">
            <img src="<?php echo htmlspecialchars(sf_app_url('IA/ia-icon.png')); ?>" alt="" width="48" height="48" style="width:100%; height:100%; display:block; object-fit:contain; margin:0; padding:0; pointer-events: none;" />
            <span class="notification-badge" id="iaNotificationBadge" style="display: none;">0</span>
        </button>
        <?php endif; ?>
        <!-- Calendário -->
        <a href="<?php echo htmlspecialchars(sf_app_url('calendario/')); ?>" class="header-icon-btn" title="Calendário" aria-label="Abrir calendário" id="calendarBtn" style="text-decoration: none; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-calendar-alt" aria-hidden="true"></i>
        </a>
        
        <!-- Notificações -->
        <div class="header-icon-wrapper">
            <button type="button" class="header-icon-btn" title="Notificações" aria-label="Notificações" aria-expanded="false" aria-haspopup="true" id="notificationBtn">
                <i class="fas fa-bell" aria-hidden="true"></i>
                <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
            </button>
            
            <!-- Dropdown de Notificações -->
            <div class="notification-dropdown" id="notificationDropdown">
                <div class="notification-header">
                    <h3>Operacionais</h3>
                    <button type="button" class="notification-clear-btn">Limpar</button>
                </div>
                <div class="notification-list">
                    <!-- Notificações serão carregadas via JavaScript -->
                </div>
                <div class="notification-footer">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/notificacoes.php')); ?>" class="view-all-link">Ver todas</a>
                </div>
            </div>
        </div>
        
        <?php if (!empty($_SESSION['acesso_todas_empresas']) && $_SESSION['acesso_todas_empresas'] === true): ?>
        <!-- Seletor de Empresa (apenas para usuários com acesso global) -->
        <div class="header-icon-wrapper" style="position: relative;">
            <button type="button" class="header-icon-btn" title="Trocar Empresa" aria-label="Trocar empresa" aria-expanded="false" aria-haspopup="true" id="empresaSelectorBtn">
                <i class="fas fa-building" aria-hidden="true"></i>
            </button>
            
            <!-- Dropdown de Empresas -->
            <div class="empresa-dropdown" id="empresaDropdown">
                <div class="empresa-dropdown-header">
                    <h3>Trocar de Empresa</h3>
                </div>
                <div class="empresa-dropdown-list" id="empresaDropdownList">
                    <div style="padding: 12px; text-align: center; color: #999;">Carregando...</div>
                </div>
                <div class="empresa-dropdown-footer">
                    <a href="selecionar_empresa.php" class="view-all-link">Ver todas as empresas</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Alternador de tema -->
        <button type="button" class="theme-toggle" id="themeToggle" role="switch" aria-checked="false" title="Alternar tema" aria-label="Alternar entre tema escuro e tema claro">
            <span class="theme-toggle-thumb" aria-hidden="true"></span>
            <span class="theme-toggle-icon sun" aria-hidden="true">
                <i class="fas fa-sun"></i>
            </span>
            <span class="theme-toggle-icon moon" aria-hidden="true">
                <i class="fas fa-moon"></i>
            </span>
        </button>
        
        <div class="header-profile-wrapper">
            <button type="button" class="user-profile" id="userProfileBtn" aria-expanded="false" aria-haspopup="true" aria-controls="profileDropdown" aria-label="<?php echo htmlspecialchars($nome_usuario !== '' ? 'Menu da conta: ' . $nome_usuario : 'Menu da conta', ENT_QUOTES, 'UTF-8'); ?>">
            <div class="user-avatar">
                <?php if ($foto_perfil): ?>
                    <img src="<?php echo htmlspecialchars(sf_app_url('uploads/perfil/' . $foto_perfil)); ?>" alt="" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid #ccc;">
                <?php else: ?>
                    <span aria-hidden="true"><?php echo htmlspecialchars($letra); ?></span>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($nome_usuario); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($cargo_usuario); ?></div>
            </div>
            <i class="fas fa-chevron-down profile-dropdown-icon" aria-hidden="true"></i>
            </button>
            
            <!-- Dropdown do Perfil -->
            <div class="profile-dropdown" id="profileDropdown" aria-label="Opções da conta">
                <nav class="profile-dropdown-nav" aria-label="Atalhos da conta">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/perfil.php')); ?>" class="profile-dropdown-item">
                        <i class="fas fa-user" aria-hidden="true"></i>
                        <span>Meu Perfil</span>
                    </a>
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/usuarios.php')); ?>" class="profile-dropdown-item">
                        <i class="fas fa-users" aria-hidden="true"></i>
                        <span>Usuários</span>
                    </a>
                    <?php if ($sf_header_mod_ia): ?>
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/ia_painel.php')); ?>" class="profile-dropdown-item">
                        <i class="fas fa-robot" aria-hidden="true"></i>
                        <span>Painel Inteligente (IA)</span>
                    </a>
                    <?php endif; ?>
                </nav>

                <details class="profile-dropdown-colors" id="profileColorPanel">
                    <summary class="profile-dropdown-colors__summary">
                        <span class="profile-dropdown-colors__label">
                            <i class="fas fa-palette" aria-hidden="true"></i>
                            Personalizar cores
                        </span>
                    </summary>
                    <div class="color-palette-section">
                        <div class="color-preview-strip" id="colorPreviewStrip" aria-hidden="true"></div>

                        <div class="color-palette-presets" id="colorPresets" role="group" aria-label="Temas prontos">
                            <button type="button" class="color-preset-btn" data-color-preset="frotec" title="Azul Frotec">Frotec</button>
                            <button type="button" class="color-preset-btn" data-color-preset="oceano" title="Tons de azul oceano">Oceano</button>
                            <button type="button" class="color-preset-btn" data-color-preset="floresta" title="Tons de verde">Floresta</button>
                            <button type="button" class="color-preset-btn" data-color-preset="corporativo" title="Roxo corporativo">Corp.</button>
                            <button type="button" class="color-preset-btn" data-color-preset="metalico" title="Cinza prata metálico">Metálico</button>
                            <button type="button" class="color-preset-btn" data-color-preset="branco" title="Menu escuro + conteúdo claro">Branco</button>
                        </div>

                        <div class="color-palette-grid">
                            <div class="color-option">
                                <label for="primaryColor">Cor principal</label>
                                <input type="color" id="primaryColor" value="#3b82f6" data-color-key="primaryColor" aria-label="Cor principal">
                            </div>
                            <div class="color-option">
                                <label for="accentSecondaryColor">Cor de destaque</label>
                                <input type="color" id="accentSecondaryColor" value="#0ea5e9" data-color-key="accentSecondary" aria-label="Cor de destaque">
                            </div>
                            <div class="color-option">
                                <label for="bgColor">Fundo da página</label>
                                <input type="color" id="bgColor" value="#0f1824" data-color-key="bgColor" aria-label="Fundo da página">
                            </div>
                            <div class="color-option">
                                <label for="sidebarColor">Menu lateral</label>
                                <input type="color" id="sidebarColor" value="#0f172a" data-color-key="sidebarColor" aria-label="Menu lateral">
                            </div>
                            <div class="color-option">
                                <label for="dashboardBgColor">Cards / dashboards</label>
                                <input type="color" id="dashboardBgColor" value="#1e293b" data-color-key="dashboardBgColor" aria-label="Fundo dos cards">
                            </div>
                            <div class="color-option">
                                <label for="secondaryBgColor">Fundo secundário</label>
                                <input type="color" id="secondaryBgColor" value="#1a2332" data-color-key="secondaryBgColor" aria-label="Fundo secundário">
                            </div>
                            <div class="color-option">
                                <label for="tertiaryBgColor">Colunas de histórico</label>
                                <input type="color" id="tertiaryBgColor" value="#243041" data-color-key="tertiaryBgColor" aria-label="Colunas de histórico">
                            </div>
                        </div>

                        <button type="button" id="restoreDefaultColors" class="btn-restore-colors">
                            <i class="fas fa-undo" aria-hidden="true"></i> Restaurar padrão
                        </button>
                    </div>
                </details>

                <div class="profile-dropdown-footer">
                    <a href="<?php echo htmlspecialchars(sf_app_url('logout.php')); ?>" class="profile-dropdown-item text-danger" id="logoutLink">
                        <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                        <span>Sair</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<style>
.notification-dropdown.show {
    display: flex;
    flex-direction: column;
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.profile-dropdown.show {
    display: flex;
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.notification-dropdown,
.profile-dropdown,
.empresa-dropdown {
    display: none;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
}

.empresa-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    width: 300px;
    background: var(--bg-secondary);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 1000;
    margin-top: 8px;
}

.empresa-dropdown-header {
    padding: 16px;
    border-bottom: 1px solid var(--bg-tertiary);
}

.empresa-dropdown-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
}

.empresa-dropdown-list {
    max-height: 300px;
    overflow-y: auto;
}

.empresa-dropdown-item {
    padding: 12px 16px;
    cursor: pointer;
    transition: background-color 0.2s;
    border-bottom: 1px solid var(--bg-tertiary);
}

.empresa-dropdown-item:hover {
    background: var(--bg-tertiary);
}

.empresa-dropdown-item.active {
    background: var(--accent-primary);
    color: white;
}

.empresa-dropdown-item h4 {
    margin: 0 0 4px 0;
    font-size: 14px;
    font-weight: 600;
}

.empresa-dropdown-item p {
    margin: 0;
    font-size: 12px;
    opacity: 0.8;
}

.empresa-dropdown-footer {
    padding: 12px 16px;
    border-top: 1px solid var(--bg-tertiary);
    text-align: center;
}

.empresa-dropdown.show {
    display: block;
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.header-icon-wrapper {
    position: relative;
}

.notification-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    width: 320px;
    background: var(--bg-secondary);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 1000;
    margin-top: 8px;
}

.profile-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    width: 280px;
    background: var(--bg-secondary);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 1000;
    margin-top: 8px;
}

.header-profile-wrapper {
    position: relative;
}

.user-profile {
    position: relative;
    cursor: pointer;
    padding: 8px;
    border-radius: 8px;
    transition: background-color 0.3s ease;
}

button.user-profile {
    border: none;
    background: none;
    font: inherit;
    color: inherit;
    text-align: left;
    -webkit-appearance: none;
    appearance: none;
}

.user-profile:hover {
    background: var(--bg-tertiary);
}

.user-profile.active {
    background: var(--bg-tertiary);
}

.header-icon-btn {
    position: relative;
    cursor: pointer;
    padding: 8px;
    border-radius: 8px;
    transition: background-color 0.3s ease;
}

.header-icon-btn:hover {
    background: var(--bg-tertiary);
}
</style>

<!-- Inclusão do JS para Personalizar Cores e funcionalidades do header -->
<?php sf_render_api_scripts(); ?>
<script src="<?php echo htmlspecialchars(sf_app_url('js/modal_a11y.js')); ?>"></script>
<script src="<?php echo htmlspecialchars(sf_app_url('js/notificacoes-common.js')); ?>"></script>
<script src="<?php echo htmlspecialchars(sf_app_url('js/header.js')); ?>"></script>

<link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('notificacoes/ia_fabicon.css')); ?>">
<script src="<?php echo htmlspecialchars(sf_app_url('notificacoes/ia_fabicon.js')); ?>"></script>
