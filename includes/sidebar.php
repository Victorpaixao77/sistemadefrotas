<?php
require_once __DIR__ . '/sf_paths.php';
// Incluir arquivo de permissões se ainda não foi incluído
if (!function_exists('can_access_fiscal_system')) {
    require_once __DIR__ . '/permissions.php';
}
?>
<!-- Sidebar Navigation -->
<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="logo-icon">
                <span>V</span>
            </div>
            <div class="logo-text">
                <span class="app-name">Frotec</span>
            </div>
        </div>
        <button class="sidebar-toggle">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>
    
    <div class="sidebar-content">
        <nav class="sidebar-nav">
            <ul class="sidebar-nav-list">
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('index.php')); ?>" class="sidebar-link active">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                        <span class="sidebar-link-text">Dashboard</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/empresa.php')); ?>" class="sidebar-link">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <span class="sidebar-link-text">Empresa</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/vehicles.php')); ?>" class="sidebar-link">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <span class="sidebar-link-text">Veículos</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/motorists.php')); ?>" class="sidebar-link">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <span class="sidebar-link-text">Motoristas</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/fornecedores_moderno.php')); ?>" class="sidebar-link">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-truck-loading"></i>
                        </div>
                        <span class="sidebar-link-text">Fornecedores</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/routes.php')); ?>" class="sidebar-link">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-route"></i>
                        </div>
                        <span class="sidebar-link-text">Rotas</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/abastecimentos.php')); ?>" class="sidebar-link">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-gas-pump"></i>
                        </div>
                        <span class="sidebar-link-text">Abastecimento</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/manutencoes.php')); ?>" class="sidebar-link">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-tools"></i>
                        </div>
                        <span class="sidebar-link-text">Manutenções</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="#" class="sidebar-link sidebar-dropdown-toggle">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <span class="sidebar-link-text">Financeiro</span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </a>
                    <ul class="sidebar-dropdown">
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/contas_pagar.php')); ?>">Contas a Pagar</a>
                        </li>
                        <li>

                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/financiamento.php')); ?>">Financiamento</a>
                        </li>
                    </ul>
                </li>
                <?php if (can_access_tire_management()): ?>
                <li class="sidebar-nav-item">
                    <a href="#" class="sidebar-link sidebar-dropdown-toggle">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-tire"></i>
                        </div>
                        <span class="sidebar-link-text">Gestão de Pneus</span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </a>
                    <ul class="sidebar-dropdown">
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/pneus.php')); ?>">Pneus</a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/manutencao_pneus.php')); ?>">Manutenção de Pneus</a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/lucratividade.php')); ?>" class="sidebar-link">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <span class="sidebar-link-text">Lucratividade</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/reports.php')); ?>" class="sidebar-link">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <span class="sidebar-link-text">Relatório</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/configuracoes.php')); ?>" class="sidebar-link">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-cog"></i>
                        </div>
                        <span class="sidebar-link-text">Configuração</span>
                    </a>
                </li>
                <?php if (function_exists('can_manage_system_settings') && can_manage_system_settings()): ?>
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/debug_apis.php')); ?>" class="sidebar-link">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-bug"></i>
                        </div>
                        <span class="sidebar-link-text">Debug APIs</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/log_acessos.php')); ?>" class="sidebar-link">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <span class="sidebar-link-text">Log de Acessos</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</div>

<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay"></div>
