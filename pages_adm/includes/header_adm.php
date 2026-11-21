<?php
require_once __DIR__ . '/adm_paths.php';
sf_project_include('includes/sf_paths.php');
$adm_nome = (string) ($_SESSION['admin_nome'] ?? 'Administrador');
$adm_email = (string) ($_SESSION['admin_email'] ?? '');
$adm_letra = $adm_nome !== '' ? mb_substr($adm_nome, 0, 1, 'UTF-8') : 'A';
if (!isset($page_title)) {
    $page_title = 'Painel ADM';
}
?>
<header class="top-header" role="banner">
    <div class="header-left">
        <button type="button" class="mobile-menu-toggle" id="mobileMenuToggleBtn" aria-label="Abrir menu de navegação" aria-expanded="false" aria-controls="sidebar-nav">
            <i class="fas fa-bars" aria-hidden="true"></i>
        </button>
        <h1 class="header-title"><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>

    <div class="header-controls">
<?php if (!empty($GLOBALS['adm_help_key_resolved']) || (!empty($adm_help_key_resolved))): ?>
        <button type="button" class="adm-header-help-btn" id="helpBtn" title="Ajuda desta tela" aria-label="Ajuda desta tela">
            <i class="fas fa-question-circle" aria-hidden="true"></i>
        </button>
<?php endif; ?>
        <button type="button" class="theme-toggle" id="themeToggle" role="switch" aria-checked="false" title="Alternar tema" aria-label="Alternar entre tema escuro e tema claro">
            <span class="theme-toggle-thumb" aria-hidden="true"></span>
            <span class="theme-toggle-icon sun" aria-hidden="true"><i class="fas fa-sun"></i></span>
            <span class="theme-toggle-icon moon" aria-hidden="true"><i class="fas fa-moon"></i></span>
        </button>

        <div class="header-profile-wrapper">
            <button type="button" class="user-profile" id="userProfileBtn" aria-expanded="false" aria-haspopup="true" aria-controls="profileDropdown" aria-label="<?php echo htmlspecialchars('Menu da conta: ' . $adm_nome, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="user-avatar"><span aria-hidden="true"><?php echo htmlspecialchars($adm_letra, ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($adm_nome, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="user-role">Administrador</div>
                </div>
                <i class="fas fa-chevron-down profile-dropdown-icon" aria-hidden="true"></i>
            </button>

            <div class="profile-dropdown" id="profileDropdown" aria-label="Opções da conta">
                <nav class="profile-dropdown-nav" aria-label="Atalhos da conta">
                    <?php if ($adm_email !== ''): ?>
                    <div class="profile-dropdown-item" style="pointer-events:none;opacity:0.85;font-size:0.82rem;">
                        <i class="fas fa-envelope" aria-hidden="true"></i>
                        <span><?php echo htmlspecialchars($adm_email, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php endif; ?>
                    <a href="conta_adm.php" class="profile-dropdown-item">
                        <i class="fas fa-shield-halved" aria-hidden="true"></i>
                        <span>Segurança / 2FA</span>
                    </a>
                    <a href="acessar_frotas.php" class="profile-dropdown-item">
                        <i class="fas fa-truck-fast" aria-hidden="true"></i>
                        <span>Sistema de frotas</span>
                    </a>
                </nav>
                <div class="profile-dropdown-footer">
                    <a href="logout.php" class="profile-dropdown-item text-danger">
                        <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                        <span>Sair</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>
