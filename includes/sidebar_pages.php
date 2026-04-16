<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/sf_paths.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$empresa_id = $_SESSION['empresa_id'] ?? null;
$nome_personalizado = 'Frotec Online';
$logo_path = 'logo.png';

if ($empresa_id) {
    try {
        $conn = getConnection();
        $stmt = $conn->prepare('SELECT nome_personalizado, logo_empresa FROM configuracoes WHERE empresa_id = :empresa_id LIMIT 1');
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            if (!empty($row['nome_personalizado'])) {
                $nome_personalizado = $row['nome_personalizado'];
            }
            if (!empty($row['logo_empresa'])) {
                // Se for o logo padrão do sistema, usar direto
                if ($row['logo_empresa'] === 'logo.png') {
                    $logo_path = 'logo.png';
                } else {
                    // Construir o caminho completo da logo personalizada
                    if (strpos($row['logo_empresa'], 'uploads/') !== 0) {
                        // Se não começar com 'uploads/', adicionar o caminho
                        $logo_path = 'uploads/logos/' . $row['logo_empresa'];
                    } else {
                        $logo_path = $row['logo_empresa'];
                    }
                }
            }
        }
    } catch (Exception $e) {}
}

// Determinar o caminho base para a logo
$base_path = '';
$current_path = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');

// Debug para verificar o caminho atual
// error_log("Sidebar - Caminho atual: " . $current_path);

if (strpos($current_path, '/fiscal/pages/') !== false) {
    // Para páginas dentro de fiscal/pages/ precisa subir 2 níveis
    $base_path = '../../';
} elseif (strpos($current_path, '/fiscal/') !== false) {
    // Para outras páginas dentro de fiscal/ precisa subir 1 nível
    $base_path = '../';
} elseif (strpos($current_path, '/pages/') !== false) {
    // Para páginas dentro de pages/ precisa subir 1 nível
    $base_path = '../';
} elseif (strpos($current_path, '/calendario/') !== false) {
    // Para páginas dentro de calendario/ precisa subir 1 nível
    $base_path = '../';
} else {
    // Para páginas na raiz
    $base_path = '';
}

// Navegação ativa: destaca o item da página atual no menu lateral
if (!function_exists('sf_sidebar_script_path')) {
    function sf_sidebar_script_path(): string {
        return str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    }
}
if (!function_exists('sf_sidebar_url_path')) {
    function sf_sidebar_url_path(string $href): string {
        $p = parse_url($href, PHP_URL_PATH);
        if (is_string($p) && $p !== '') {
            return str_replace('\\', '/', $p);
        }
        return str_replace('\\', '/', $href);
    }
}
if (!function_exists('sf_sidebar_is_active')) {
    function sf_sidebar_is_active(string $href): bool {
        $cur = sf_sidebar_script_path();
        $target = sf_sidebar_url_path($href);
        if ($cur === '' || $target === '') {
            return false;
        }
        if ($cur === $target) {
            return true;
        }
        if (strlen($target) >= 2 && substr($cur, -strlen($target)) === $target) {
            return true;
        }
        return false;
    }
}
if (!function_exists('sf_sidebar_active')) {
    function sf_sidebar_active(string $href): string {
        return sf_sidebar_is_active($href) ? ' active' : '';
    }
}
if (!function_exists('sf_sidebar_any_active')) {
    function sf_sidebar_any_active(array $rels): bool {
        foreach ($rels as $rel) {
            if (sf_sidebar_is_active(sf_app_url($rel))) {
                return true;
            }
        }
        return false;
    }
}
if (!function_exists('sf_sidebar_aria_current')) {
    function sf_sidebar_aria_current(string $href): string {
        return sf_sidebar_is_active($href) ? ' aria-current="page"' : '';
    }
}
if (!function_exists('sf_sidebar_active_rels')) {
    /** Marca ativo se SCRIPT_NAME coincidir com qualquer rota em $rels (ex.: página legada + moderna). */
    function sf_sidebar_active_rels(array $rels): string {
        return sf_sidebar_any_active($rels) ? ' active' : '';
    }
}
if (!function_exists('sf_sidebar_aria_current_rels')) {
    function sf_sidebar_aria_current_rels(array $rels): string {
        return sf_sidebar_any_active($rels) ? ' aria-current="page"' : '';
    }
}

$sf_open_fiscal = sf_sidebar_any_active(['fiscal/pages/nfe.php', 'fiscal/pages/cte.php', 'fiscal/pages/mdfe.php', 'fiscal/index.php']);
$sf_open_fin = sf_sidebar_any_active(['pages/contas_pagar.php', 'pages/despesas_fixas.php', 'pages/financiamento.php', 'pages/multas.php', 'pages/comissoes.php']);
$sf_open_pneus = sf_sidebar_any_active(['pages/pneus.php', 'pages/estoque_pneus.php', 'pages/gestao_interativa.php', 'pages/manutencao_pneus.php']);
$sf_open_gm = sf_sidebar_any_active(['pages/gestao_motoristas.php', 'pages/checklists.php', 'pages/rotas_motoristas.php', 'pages/abastecimentos_motoristas.php', 'pages/mapa_frota.php']);
?>
<!-- Sidebar Navigation -->
<a href="#conteudo-principal" class="skip-to-main"><?php echo htmlspecialchars('Pular para o conteúdo principal'); ?></a>
<div class="sidebar">
    <script>
    (function() {
        try {
            const savedTheme = localStorage.getItem('theme');
            const isLight = savedTheme === 'light';
            const root = document.documentElement;
            if (isLight) {
                root.classList.add('light-theme');
                if (document.body) document.body.classList.add('light-theme');
            } else {
                root.classList.remove('light-theme');
                if (document.body) document.body.classList.remove('light-theme');
            }
        } catch (e) { console.warn('Theme:', e); }
    })();
    </script>
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <?php 
            if ($logo_path): 
            ?>
            <div class="logo-image">
                <?php 
                $logo_full_path = $base_path . htmlspecialchars($logo_path);
                ?>
                <img src="<?php echo $logo_full_path; ?>" alt="Logo da Empresa">
            </div>
            <?php else: ?>
            <div class="logo-icon">
                <span>V</span>
            </div>
            <?php endif; ?>
            <div class="logo-text">
                <span class="app-name"><?php echo htmlspecialchars($nome_personalizado); ?></span>
            </div>
        </div>
        <button type="button" class="sidebar-toggle" aria-label="Recolher ou expandir menu lateral">
            <i class="fas fa-chevron-left" aria-hidden="true"></i>
        </button>
    </div>
    
    <div class="sidebar-content">
        <nav class="sidebar-nav" id="sidebar-nav" aria-label="Menu principal do sistema">
            <ul class="sidebar-nav-list">
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('index.php')); ?>" class="sidebar-link<?php echo sf_sidebar_active(sf_app_url('index.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('index.php')); ?>>
                        <div class="sidebar-link-icon">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                        <span class="sidebar-link-text">Dashboard</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/empresa.php')); ?>" class="sidebar-link<?php echo sf_sidebar_active(sf_app_url('pages/empresa.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/empresa.php')); ?>>
                        <div class="sidebar-link-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <span class="sidebar-link-text">Empresa</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/vehicles.php')); ?>" class="sidebar-link<?php echo sf_sidebar_active(sf_app_url('pages/vehicles.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/vehicles.php')); ?>>
                        <div class="sidebar-link-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <span class="sidebar-link-text">Veículos</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/motorists.php')); ?>" class="sidebar-link<?php echo sf_sidebar_active(sf_app_url('pages/motorists.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/motorists.php')); ?>>
                        <div class="sidebar-link-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <span class="sidebar-link-text">Motoristas</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/fornecedores_moderno.php')); ?>" class="sidebar-link<?php echo sf_sidebar_active_rels(['pages/fornecedores_moderno.php', 'pages/fornecedores.php']); ?>"<?php echo sf_sidebar_aria_current_rels(['pages/fornecedores_moderno.php', 'pages/fornecedores.php']); ?>>
                        <div class="sidebar-link-icon">
                            <i class="fas fa-truck-loading"></i>
                        </div>
                        <span class="sidebar-link-text">Fornecedores</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/routes.php')); ?>" class="sidebar-link<?php echo sf_sidebar_active(sf_app_url('pages/routes.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/routes.php')); ?>>
                        <div class="sidebar-link-icon">
                            <i class="fas fa-route"></i>
                        </div>
                        <span class="sidebar-link-text">Rotas</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/abastecimentos.php')); ?>" class="sidebar-link<?php echo sf_sidebar_active(sf_app_url('pages/abastecimentos.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/abastecimentos.php')); ?>>
                        <div class="sidebar-link-icon">
                            <i class="fas fa-gas-pump"></i>
                        </div>
                        <span class="sidebar-link-text">Abastecimento</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/manutencoes.php')); ?>" class="sidebar-link<?php echo sf_sidebar_active(sf_app_url('pages/manutencoes.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/manutencoes.php')); ?>>
                        <div class="sidebar-link-icon">
                            <i class="fas fa-tools"></i>
                        </div>
                        <span class="sidebar-link-text">Manutenções</span>
                    </a>
                </li>
                <?php if (can_access_fiscal_system()): ?>
                <li class="sidebar-nav-item<?php echo $sf_open_fiscal ? ' sidebar-nav-item--section-active' : ''; ?>">
                    <a href="#" class="sidebar-link sidebar-dropdown-toggle<?php echo $sf_open_fiscal ? ' active' : ''; ?>">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <span class="sidebar-link-text">Sistema Fiscal</span>
                        <i class="fas fa-chevron-down dropdown-icon<?php echo $sf_open_fiscal ? ' rotate' : ''; ?>"></i>
                    </a>
                    <ul class="sidebar-dropdown"<?php echo $sf_open_fiscal ? ' data-dropdown-open="1" style="display:block"' : ''; ?>>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('fiscal/pages/nfe.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('fiscal/pages/nfe.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('fiscal/pages/nfe.php')); ?>>
                                <i class="fas fa-receipt"></i> Gestão de NF-e
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('fiscal/pages/cte.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('fiscal/pages/cte.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('fiscal/pages/cte.php')); ?>>
                                <i class="fas fa-truck"></i> Gestão de CT-e
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('fiscal/pages/mdfe.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('fiscal/pages/mdfe.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('fiscal/pages/mdfe.php')); ?>>
                                <i class="fas fa-file-alt"></i> Gestão de MDF-e
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Menu Financeiro - sempre visível -->
                <li class="sidebar-nav-item<?php echo $sf_open_fin ? ' sidebar-nav-item--section-active' : ''; ?>">
                    <a href="#" class="sidebar-link sidebar-dropdown-toggle<?php echo $sf_open_fin ? ' active' : ''; ?>">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <span class="sidebar-link-text">Financeiro</span>
                        <i class="fas fa-chevron-down dropdown-icon<?php echo $sf_open_fin ? ' rotate' : ''; ?>"></i>
                    </a>
                    <ul class="sidebar-dropdown"<?php echo $sf_open_fin ? ' data-dropdown-open="1" style="display:block"' : ''; ?>>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/contas_pagar.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/contas_pagar.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/contas_pagar.php')); ?>>
                                <i class="fas fa-file-invoice-dollar"></i> Contas a Pagar
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/despesas_fixas.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/despesas_fixas.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/despesas_fixas.php')); ?>>
                                <i class="fas fa-receipt"></i> Despesas Fixas
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/financiamento.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/financiamento.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/financiamento.php')); ?>>
                                <i class="fas fa-hand-holding-usd"></i> Financiamento
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/multas.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/multas.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/multas.php')); ?>>
                                <i class="fas fa-ticket-alt"></i> Multas
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/comissoes.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/comissoes.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/comissoes.php')); ?>>
                                <i class="fas fa-wallet"></i> Comissão
                            </a>
                        </li>
                    </ul>
                </li>
                
                <?php if (can_access_tire_management()): ?>
                <li class="sidebar-nav-item<?php echo $sf_open_pneus ? ' sidebar-nav-item--section-active' : ''; ?>">
                    <a href="#" class="sidebar-link sidebar-dropdown-toggle<?php echo $sf_open_pneus ? ' active' : ''; ?>">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-circle"></i>
                        </div>
                        <span class="sidebar-link-text">Gestão de Pneus</span>
                        <i class="fas fa-chevron-down dropdown-icon<?php echo $sf_open_pneus ? ' rotate' : ''; ?>"></i>
                    </a>
                    <ul class="sidebar-dropdown"<?php echo $sf_open_pneus ? ' data-dropdown-open="1" style="display:block"' : ''; ?>>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/pneus.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/pneus.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/pneus.php')); ?>>
                                <i class="fas fa-truck"></i> Pneus
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/estoque_pneus.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/estoque_pneus.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/estoque_pneus.php')); ?>>
                                <i class="fas fa-warehouse"></i> Estoque de Pneus
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/gestao_interativa.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/gestao_interativa.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/gestao_interativa.php')); ?>>
                                <i class="fas fa-truck"></i> Gestão Interativa
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/manutencao_pneus.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/manutencao_pneus.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/manutencao_pneus.php')); ?>>
                                <i class="fas fa-tools"></i> Manutenção de Pneus
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if (can_approve_refuels()): ?>
                <li class="sidebar-nav-item<?php echo $sf_open_gm ? ' sidebar-nav-item--section-active' : ''; ?>">
                    <a href="#" class="sidebar-link sidebar-dropdown-toggle<?php echo $sf_open_gm ? ' active' : ''; ?>">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <span class="sidebar-link-text">Gestão de Motoristas</span>
                        <i class="fas fa-chevron-down dropdown-icon<?php echo $sf_open_gm ? ' rotate' : ''; ?>"></i>
                    </a>
                    <ul class="sidebar-dropdown"<?php echo $sf_open_gm ? ' data-dropdown-open="1" style="display:block"' : ''; ?>>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/gestao_motoristas.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/gestao_motoristas.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/gestao_motoristas.php')); ?>>
                                <i class="fas fa-clipboard-check"></i> Aprovações
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/checklists.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/checklists.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/checklists.php')); ?>>
                                <i class="fas fa-clipboard-list"></i> Checklists
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/rotas_motoristas.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/rotas_motoristas.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/rotas_motoristas.php')); ?>>
                                <i class="fas fa-route"></i> Rotas
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/abastecimentos_motoristas.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/abastecimentos_motoristas.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/abastecimentos_motoristas.php')); ?>>
                                <i class="fas fa-gas-pump"></i> Abastecimentos
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/mapa_frota.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/mapa_frota.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/mapa_frota.php')); ?>>
                                <i class="fas fa-map-marker-alt"></i> Mapa GPS
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if (can_access_lucratividade()): ?>
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/lucratividade.php')); ?>" class="sidebar-link<?php echo sf_sidebar_active(sf_app_url('pages/lucratividade.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/lucratividade.php')); ?>>
                        <div class="sidebar-link-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <span class="sidebar-link-text">Lucratividade</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (can_access_advanced_reports()): ?>
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/relatorios.php')); ?>" class="sidebar-link<?php echo sf_sidebar_active(sf_app_url('pages/relatorios.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/relatorios.php')); ?>>
                        <div class="sidebar-link-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <span class="sidebar-link-text">Relatórios</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (can_manage_system_settings()): ?>
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/configuracoes.php')); ?>" class="sidebar-link<?php echo sf_sidebar_active(sf_app_url('pages/configuracoes.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/configuracoes.php')); ?>>
                        <div class="sidebar-link-icon">
                            <i class="fas fa-cog"></i>
                        </div>
                        <span class="sidebar-link-text">Configuração</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</div>

<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay"></div>

<style>
.sidebar-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
}

.logo-image {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: 5px;
    flex-shrink: 0;
}

.logo-image img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.logo-icon {
    width: 40px;
    height: 40px;
    background: #3498db;
    border-radius: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 1.2em;
    flex-shrink: 0;
}

.logo-text {
    flex: 1;
    min-width: 0;
}

.app-name {
    font-size: 1.1em;
    font-weight: bold;
    color: white;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Estilos para o menu minimizado */
.sidebar-collapsed .sidebar-logo {
    justify-content: center;
    padding: 10px 5px;
}

.sidebar-collapsed .logo-image {
    width: 35px;
    height: 35px;
}

.sidebar-collapsed .logo-icon {
    width: 35px;
    height: 35px;
}

.sidebar-collapsed .logo-text {
    display: none;
}

/* Estilos para hover no menu minimizado */
.sidebar-hovered .sidebar-logo {
    justify-content: flex-start;
    padding: 10px;
}

.sidebar-hovered .logo-image {
    width: 40px;
    height: 40px;
}

.sidebar-hovered .logo-icon {
    width: 40px;
    height: 40px;
}

.sidebar-hovered .logo-text {
    display: block;
}
</style>