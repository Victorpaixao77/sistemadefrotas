<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$empresa_id = $_SESSION['empresa_id'] ?? null;
$nome_personalizado = 'Desenvolvimento';
$logo_path = null;

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
                // Construir o caminho completo da logo
                if (strpos($row['logo_empresa'], 'uploads/') !== 0) {
                    // Se não começar com 'uploads/', adicionar o caminho
                    $logo_path = 'uploads/logos/' . $row['logo_empresa'];
                } else {
                    $logo_path = $row['logo_empresa'];
                }
            }
        }
    } catch (Exception $e) {}
}

// Determinar o caminho base para a logo
$base_path = '';
$current_path = $_SERVER['PHP_SELF'];

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
?>
<!-- Sidebar Navigation -->
<div class="sidebar">
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
        <button class="sidebar-toggle">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>
    
    <div class="sidebar-content">
        <nav class="sidebar-nav">
            <ul class="sidebar-nav-list">
                <li class="sidebar-nav-item">
                    <a href="/sistema-frotas/index.php" class="sidebar-link">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                        <span class="sidebar-link-text">Dashboard</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="/sistema-frotas/pages/empresa.php" class="sidebar-link">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <span class="sidebar-link-text">Empresa</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="/sistema-frotas/pages/vehicles.php" class="sidebar-link">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <span class="sidebar-link-text">Veículos</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="/sistema-frotas/pages/motorists.php" class="sidebar-link">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <span class="sidebar-link-text">Motoristas</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="/sistema-frotas/pages/routes.php" class="sidebar-link">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-route"></i>
                        </div>
                        <span class="sidebar-link-text">Rotas</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="/sistema-frotas/pages/abastecimentos.php" class="sidebar-link">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-gas-pump"></i>
                        </div>
                        <span class="sidebar-link-text">Abastecimento</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="/sistema-frotas/pages/manutencoes.php" class="sidebar-link">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-tools"></i>
                        </div>
                        <span class="sidebar-link-text">Manutenções</span>
                    </a>
                </li>
                <?php if (can_access_fiscal_system()): ?>
                <li class="sidebar-nav-item">
                    <a href="#" class="sidebar-link sidebar-dropdown-toggle">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <span class="sidebar-link-text">Sistema Fiscal</span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </a>
                    <ul class="sidebar-dropdown">
                        <li>
                            <a href="/sistema-frotas/fiscal/pages/nfe.php">
                                <i class="fas fa-receipt"></i> Gestão de NF-e
                            </a>
                        </li>
                        <li>
                            <a href="/sistema-frotas/fiscal/pages/cte.php">
                                <i class="fas fa-truck"></i> Gestão de CT-e
                            </a>
                        </li>
                        <li>
                            <a href="/sistema-frotas/fiscal/pages/mdfe.php">
                                <i class="fas fa-file-alt"></i> Gestão de MDF-e
                            </a>
                        </li>
                        <li>
                            <a href="/sistema-frotas/fiscal/pages/eventos.php">
                                <i class="fas fa-calendar-alt"></i> Eventos Fiscais
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Menu Financeiro - sempre visível -->
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
                            <a href="/sistema-frotas/pages/contas_pagar.php">
                                <i class="fas fa-file-invoice-dollar"></i> Contas a Pagar
                            </a>
                        </li>
                        <li>
                            <a href="/sistema-frotas/pages/despesas_fixas.php">
                                <i class="fas fa-receipt"></i> Despesas Fixas
                            </a>
                        </li>
                        <li>
                            <a href="/sistema-frotas/pages/financiamento.php">
                                <i class="fas fa-hand-holding-usd"></i> Financiamento
                            </a>
                        </li>
                        <li>
                            <a href="/sistema-frotas/pages/multas.php">
                                <i class="fas fa-ticket-alt"></i> Multas
                            </a>
                        </li>
                    </ul>
                </li>
                
                <?php if (can_access_tire_management()): ?>
                <li class="sidebar-nav-item">
                    <a href="#" class="sidebar-link sidebar-dropdown-toggle">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-circle"></i>
                        </div>
                        <span class="sidebar-link-text">Gestão de Pneus</span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </a>
                    <ul class="sidebar-dropdown">
                        <li>
                            <a href="/sistema-frotas/pages/pneus.php" class="sidebar-link">
                                <i class="fas fa-truck"></i> Pneus
                            </a>
                        </li>
                        <li>
                            <a href="/sistema-frotas/pages/estoque_pneus.php" class="sidebar-link">
                                <i class="fas fa-warehouse"></i> Estoque de Pneus
                            </a>
                        </li>
                        <li>
                            <a href="/sistema-frotas/pages/gestao_interativa.php" class="sidebar-link">
                                <i class="fas fa-truck"></i> Gestão Interativa
                            </a>
                        </li>
                        <li>
                            <a href="/sistema-frotas/pages/manutencao_pneus.php" class="sidebar-link">
                                <i class="fas fa-tools"></i> Manutenção de Pneus
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if (can_approve_refuels()): ?>
                <li class="sidebar-nav-item">
                    <a href="#" class="sidebar-link sidebar-dropdown-toggle">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <span class="sidebar-link-text">Gestão de Motoristas</span>
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </a>
                    <ul class="sidebar-dropdown">
                        <li>
                            <a href="/sistema-frotas/pages/gestao_motoristas.php" class="sidebar-link">
                                <i class="fas fa-clipboard-check"></i> Aprovações
                            </a>
                        </li>
                        <li>
                            <a href="/sistema-frotas/pages/checklists.php" class="sidebar-link">
                                <i class="fas fa-clipboard-list"></i> Checklists
                            </a>
                        </li>
                        <li>
                            <a href="/sistema-frotas/pages/rotas_motoristas.php" class="sidebar-link">
                                <i class="fas fa-route"></i> Rotas
                            </a>
                        </li>
                        <li>
                            <a href="/sistema-frotas/pages/abastecimentos_motoristas.php" class="sidebar-link">
                                <i class="fas fa-gas-pump"></i> Abastecimentos
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if (can_access_lucratividade()): ?>
                <li class="sidebar-nav-item">
                    <a href="/sistema-frotas/pages/lucratividade.php" class="sidebar-link">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <span class="sidebar-link-text">Lucratividade</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (can_access_advanced_reports()): ?>
                <li class="sidebar-nav-item">
                    <a href="/sistema-frotas/pages/relatorios.php" class="sidebar-link">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <span class="sidebar-link-text">Relatórios</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (can_manage_system_settings()): ?>
                <li class="sidebar-nav-item">
                    <a href="/sistema-frotas/pages/configuracoes.php" class="sidebar-link">
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