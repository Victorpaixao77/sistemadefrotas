<?php
// Get the current page name from the URL
$current_page = basename($_SERVER['PHP_SELF'], '.php');
// Only set default page title if not already defined
if (!isset($page_title)) {
    $page_title = ucfirst($current_page);  // Default page title
}

// Dados do usuário logado
$nome_usuario = isset($_SESSION['nome']) ? $_SESSION['nome'] : '';
$email_usuario = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$foto_perfil = isset($_SESSION['foto_perfil']) && $_SESSION['foto_perfil'] ? $_SESSION['foto_perfil'] : null;
$letra = $nome_usuario ? mb_substr($nome_usuario, 0, 1, 'UTF-8') : 'U';

// Carregar sistema de notificações
require_once 'notifications.php';

// You can override this in specific pages by setting $page_title before including header.php
?>
<header class="top-header">
    <div class="header-left">
        <button class="mobile-menu-toggle">
            <i class="fas fa-bars"></i>
        </button>
        <h1 class="header-title"><?php echo $page_title; ?></h1>
    </div>
    
    <div class="header-controls">
        <!-- Botão IA Flutuante -->
        <button class="header-icon-btn" title="IA Notificações" id="iaFabBtn" style="background: var(--bg-tertiary); color: #2563eb; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 50%; border: none; cursor: pointer; padding: 0; position: relative;">
            <img src="/sistema-frotas/IA/ia-icon.png" alt="IA" style="width:100%; height:100%; display:block; object-fit:contain; margin:0; padding:0; pointer-events: none;" />
            <span class="notification-badge" id="iaNotificationBadge" style="display: none;">0</span>
        </button>
        <!-- Calendário -->
        <button class="header-icon-btn" title="Calendário" id="calendarBtn" onclick="window.location.href='/sistema-frotas/calendario/'">
            <i class="fas fa-calendar-alt"></i>
        </button>
        
        <!-- Notificações -->
        <div class="header-icon-wrapper">
            <button class="header-icon-btn" title="Notificações" id="notificationBtn" onclick="event.preventDefault(); event.stopPropagation();">
                <i class="fas fa-bell"></i>
                <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
            </button>
            
            <!-- Dropdown de Notificações -->
            <div class="notification-dropdown" id="notificationDropdown">
                <div class="notification-header">
                    <h3>Notificações</h3>
                    <button class="notification-clear-btn">Limpar</button>
                </div>
                <div class="notification-list">
                    <!-- Notificações serão carregadas via JavaScript -->
                </div>
                <div class="notification-footer">
                    <a href="#" class="view-all-link">Ver todas</a>
                </div>
            </div>
        </div>
        
        <?php if (!empty($_SESSION['acesso_todas_empresas']) && $_SESSION['acesso_todas_empresas'] === true): ?>
        <!-- Seletor de Empresa (apenas para usuários com acesso global) -->
        <div class="header-icon-wrapper" style="position: relative;">
            <button class="header-icon-btn" title="Trocar Empresa" id="empresaSelectorBtn" onclick="event.preventDefault(); event.stopPropagation();">
                <i class="fas fa-building"></i>
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
        <div class="theme-toggle" id="themeToggle" title="Alternar tema">
            <div class="theme-toggle-thumb"></div>
            <div class="theme-toggle-icon sun">
                <i class="fas fa-sun"></i>
            </div>
            <div class="theme-toggle-icon moon">
                <i class="fas fa-moon"></i>
            </div>
        </div>
        
        <div class="user-profile" id="userProfileBtn" onclick="event.preventDefault(); event.stopPropagation();">
            <div class="user-avatar">
                <?php if ($foto_perfil): ?>
                    <img src="/sistema-frotas/uploads/perfil/<?php echo htmlspecialchars($foto_perfil); ?>" alt="Foto de Perfil" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid #ccc;">
                <?php else: ?>
                    <span><?php echo htmlspecialchars($letra); ?></span>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($nome_usuario); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($email_usuario); ?></div>
            </div>
            <i class="fas fa-chevron-down profile-dropdown-icon"></i>
            
            <!-- Dropdown do Perfil -->
            <div class="profile-dropdown" id="profileDropdown">
                <div class="profile-dropdown-header">
                    <div class="profile-dropdown-avatar">
                        <?php if ($foto_perfil): ?>
                            <img src="/sistema-frotas/uploads/perfil/<?php echo htmlspecialchars($foto_perfil); ?>" alt="Foto de Perfil" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid #ccc;">
                        <?php else: ?>
                            <span><?php echo htmlspecialchars($letra); ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="profile-dropdown-name"><?php echo htmlspecialchars($nome_usuario); ?></div>
                        <div class="profile-dropdown-email"><?php echo htmlspecialchars($email_usuario); ?></div>
                    </div>
                </div>
                
                <div class="profile-dropdown-menu">
                    <a href="/sistema-frotas/pages/perfil.php" class="profile-dropdown-item">
                        <i class="fas fa-user"></i>
                        <span>Meu Perfil</span>
                    </a>
                    <a href="/sistema-frotas/pages/usuarios.php" class="profile-dropdown-item">
                        <i class="fas fa-users"></i>
                        <span>Usuários</span>
                    </a>
                    <a href="/sistema-frotas/pages/ia_painel.php" class="profile-dropdown-item">
                        <i class="fas fa-robot"></i>
                        <span>Painel Inteligente (IA)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    
                    <!-- Seletor de Cores -->
                    <div class="color-palette-section">
                        <div class="color-palette-header">Personalizar Cores</div>
                        
                        <!-- Cor Principal -->
                        <div class="color-option">
                            <label for="primaryColor">Cor Principal:</label>
                            <input type="color" id="primaryColor" value="#3b82f6" data-color-var="--accent-primary">
                        </div>
                        
                        <!-- Cor Secundária -->
                        <div class="color-option">
                            <label for="secondaryColor">Cor Secundária:</label>
                            <input type="color" id="secondaryColor" value="#1a2332" data-color-var="--bg-secondary">
                        </div>
                        
                        <!-- Cor do Fundo -->
                        <div class="color-option">
                            <label for="bgColor">Cor de Fundo:</label>
                            <input type="color" id="bgColor" value="#0f1824" data-color-var="--bg-primary">
                        </div>
                        
                        <!-- Cor do Menu Lateral -->
                        <div class="color-option">
                            <label for="sidebarColor">Menu Lateral:</label>
                            <input type="color" id="sidebarColor" value="#121a29" data-color-var="--bg-sidebar">
                        </div>
                        
                        <!-- Cor do Fundo das Dashboards -->
                        <div class="color-option">
                            <label for="dashboardBgColor">Fundo das Dashboards:</label>
                            <input type="color" id="dashboardBgColor" value="#1e293b" data-color-var="--card-bg">
                        </div>
                        
                        <!-- Cor do Fundo Secundário -->
                        <div class="color-option">
                            <label for="secondaryBgColor">Fundo Secundário:</label>
                            <input type="color" id="secondaryBgColor" value="#1a2332" data-color-var="--bg-secondary">
                        </div>
                        
                        <!-- Cor do Fundo Terciário (Colunas de Histórico) -->
                        <div class="color-option">
                            <label for="tertiaryBgColor">Colunas de Histórico:</label>
                            <input type="color" id="tertiaryBgColor" value="#243041" data-color-var="--bg-tertiary">
                        </div>
                        
                        <!-- Botão para Restaurar Cores Padrão -->
                        <button id="restoreDefaultColors" class="btn-restore-colors">
                            Restaurar Padrão
                        </button>
                    </div>
                    
                    <div class="dropdown-divider"></div>
                    
                    <a href="/sistema-frotas/logout.php" class="profile-dropdown-item text-danger" id="logoutLink">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Sair</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<style>
.notification-dropdown.show,
.profile-dropdown.show {
    display: block;
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

.user-profile {
    position: relative;
    cursor: pointer;
    padding: 8px;
    border-radius: 8px;
    transition: background-color 0.3s ease;
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
<script src="/sistema-frotas/js/header.js"></script>

<link rel="stylesheet" href="/sistema-frotas/notificacoes/ia_fabicon.css">
<script src="/sistema-frotas/notificacoes/ia_fabicon.js"></script>
