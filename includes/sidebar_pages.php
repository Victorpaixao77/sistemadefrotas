<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/sf_paths.php';

// $_SESSION permanece legível após session_write_close(); não reabrir sessão aqui.
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

$sf_mod_cte = true;
$sf_mod_mdfe = true;
$sf_mod_fin = true;
$sf_mod_rastreamento = true;
$sf_mod_ia = true;
if ($empresa_id) {
    if (!function_exists('sf_modulo_ativo_sessao')) {
        require_once __DIR__ . '/sf_saas.php';
    }
    try {
        $sf_conn_mod = getConnection();
        $sf_mod_cte = sf_modulo_ativo_sessao($sf_conn_mod, 'cte');
        $sf_mod_mdfe = sf_modulo_ativo_sessao($sf_conn_mod, 'mdfe');
        $sf_mod_fin = sf_modulo_ativo_sessao($sf_conn_mod, 'financeiro');
        $sf_mod_rastreamento = sf_modulo_ativo_sessao($sf_conn_mod, 'rastreamento');
        $sf_mod_ia = sf_modulo_ativo_sessao($sf_conn_mod, 'ia');
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

$sf_open_oper = sf_sidebar_any_active([
    'pages/centro_operacoes.php', 'pages/patio.php', 'pages/digital_twin.php', 'pages/central_alertas.php', 'pages/agenda_inteligente.php', 'pages/scores.php',
    'pages/sla_operacional.php', 'pages/metas.php', 'pages/assistente_ia.php', 'pages/ia_decisao.php', 'pages/otimizacao.php', 'pages/ordens_transporte.php',
    'pages/aprovacoes.php', 'pages/workflow.php', 'pages/planejamento.php', 'pages/routes.php', 'pages/simulador_rota.php', 'pages/planejamento_carga.php',
]);
$sf_open_frota = sf_sidebar_any_active([
    'pages/vehicles.php', 'pages/veiculo_timeline.php', 'pages/qr_codes.php', 'pages/implementos.php', 'pages/abastecimentos.php',
    'pages/gestao_diesel.php', 'pages/manutencoes.php', 'pages/manutencao_preditiva.php',
]);
$sf_open_cad = sf_sidebar_any_active([
    'pages/motorists.php', 'pages/fornecedores_moderno.php', 'pages/fornecedores.php',
    'pages/clientes.php', 'pages/contratos.php',
]);
$sf_open_fiscal = sf_sidebar_any_active(['fiscal/pages/nfe.php', 'fiscal/pages/cte.php', 'fiscal/pages/mdfe.php', 'fiscal/pages/ciot.php', 'fiscal/index.php']);
$sf_open_fin = sf_sidebar_any_active(['pages/painel_executivo.php', 'pages/simulacao_crescimento.php', 'pages/previsao_financeira.php', 'pages/lucratividade.php', 'pages/contas_pagar.php', 'pages/despesas_fixas.php', 'pages/financiamento.php', 'pages/multas.php', 'pages/comissoes.php']);
$sf_open_pneus = sf_sidebar_any_active(['pages/pneus_dashboard.php', 'pages/pneus.php', 'pages/estoque_pneus.php', 'pages/gestao_interativa_pro.php', 'pages/manutencao_pneus.php', 'pages/pneus_inventario.php']);
$sf_open_gm = sf_sidebar_any_active(['pages/gestao_motoristas.php', 'pages/checklists.php', 'pages/rotas_motoristas.php', 'pages/abastecimentos_motoristas.php', 'pages/mapa_frota.php', 'pages/replay_viagem.php']);
$sf_open_rel = sf_sidebar_any_active(['pages/relatorios.php']);
// Categoria atual dos relatórios (só destaca 1 item por vez, igual aos outros menus).
$sf_rel_cat = ($sf_open_rel && isset($_GET['cat'])) ? preg_replace('/[^a-z]/', '', (string) $_GET['cat']) : '';
$sf_rel_active = static function (string $cat) use ($sf_open_rel, $sf_rel_cat): string {
    return ($sf_open_rel && $sf_rel_cat === $cat) ? ' active' : '';
};
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
            // A cada novo acesso ao sistema (login), fecha todos os submenus do
            // menu lateral — eles voltam ao estado recolhido por padrão.
            <?php if (!empty($_SESSION['sf_sidebar_reset'])) { unset($_SESSION['sf_sidebar_reset']); echo "try { localStorage.removeItem('sidebarDropdowns'); localStorage.removeItem('sidebarDropdownsAdm'); } catch (e) {}\n"; } ?>
            // Aplica o estado "recolhido" da barra ANTES da página pintar, evitando o flash
            // (abre expandido e volta a recolher) ao navegar entre páginas.
            if (localStorage.getItem('sidebarCollapsed') === 'true' && document.body) {
                document.body.classList.add('sidebar-collapsed', 'sidebar-no-anim');
            }
        } catch (e) { console.warn('Sidebar init:', e); }
    })();
    </script>
    <script src="<?php echo htmlspecialchars(sf_app_url('js/color-theme.js?v=' . (function_exists('sf_asset_v') ? sf_asset_v() : '1'))); ?>"></script>
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
        <button type="button" class="sidebar-toggle" aria-label="Recolher ou expandir menu lateral" title="Recolher ou expandir menu">
            <span class="sidebar-toggle-dots" aria-hidden="true"></span>
        </button>
    </div>
    
    <div class="sidebar-content">
        <nav class="sidebar-nav" id="sidebar-nav" aria-label="Menu principal do sistema">
            <ul class="sidebar-nav-list">
                <li class="sidebar-nav-item">
                    <a href="<?php echo htmlspecialchars(sf_app_url('index.php')); ?>" class="sidebar-link<?php echo sf_sidebar_active(sf_app_url('index.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('index.php')); ?>>
                        <div class="sidebar-link-icon"><i class="fas fa-tachometer-alt"></i></div>
                        <span class="sidebar-link-text">Dashboard</span>
                    </a>
                </li>

                <li class="sidebar-nav-item<?php echo $sf_open_oper ? ' sidebar-nav-item--section-active' : ''; ?>">
                    <a href="#" class="sidebar-link sidebar-dropdown-toggle<?php echo $sf_open_oper ? ' active' : ''; ?>">
                        <div class="sidebar-link-icon"><i class="fas fa-satellite-dish"></i></div>
                        <span class="sidebar-link-text">Operação</span>
                        <i class="fas fa-chevron-down dropdown-icon<?php echo $sf_open_oper ? ' rotate' : ''; ?>"></i>
                    </a>
                    <ul class="sidebar-dropdown"<?php echo $sf_open_oper ? ' data-dropdown-open="1" style="display:block"' : ''; ?>>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/centro_operacoes.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/centro_operacoes.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/centro_operacoes.php')); ?>>
                                <i class="fas fa-satellite-dish"></i> Centro de Operações
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/patio.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/patio.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/patio.php')); ?>>
                                <i class="fas fa-warehouse"></i> Gestão de Pátio
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/digital_twin.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/digital_twin.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/digital_twin.php')); ?>>
                                <i class="fas fa-diagram-project"></i> Digital Twin
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/central_alertas.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/central_alertas.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/central_alertas.php')); ?>>
                                <i class="fas fa-bell"></i> Central de Alertas
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/agenda_inteligente.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/agenda_inteligente.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/agenda_inteligente.php')); ?>>
                                <i class="fas fa-calendar-check"></i> Agenda Inteligente
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/scores.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/scores.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/scores.php')); ?>>
                                <i class="fas fa-star-half-stroke"></i> Scores
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/sla_operacional.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/sla_operacional.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/sla_operacional.php')); ?>>
                                <i class="fas fa-gauge-high"></i> SLA Operacional
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/metas.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/metas.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/metas.php')); ?>>
                                <i class="fas fa-bullseye"></i> Metas
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/assistente_ia.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/assistente_ia.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/assistente_ia.php')); ?>>
                                <i class="fas fa-brain"></i> Centro de Inteligência
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/ia_decisao.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/ia_decisao.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/ia_decisao.php')); ?>>
                                <i class="fas fa-lightbulb"></i> IA de Decisão
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/otimizacao.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/otimizacao.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/otimizacao.php')); ?>>
                                <i class="fas fa-wand-magic-sparkles"></i> Otimização
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/ordens_transporte.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/ordens_transporte.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/ordens_transporte.php')); ?>>
                                <i class="fas fa-file-signature"></i> Ordens de Transporte
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/aprovacoes.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/aprovacoes.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/aprovacoes.php')); ?>>
                                <i class="fas fa-clipboard-check"></i> Aprovações
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/workflow.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/workflow.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/workflow.php')); ?>>
                                <i class="fas fa-diagram-project"></i> Workflow
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/planejamento.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/planejamento.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/planejamento.php')); ?>>
                                <i class="fas fa-clipboard-list"></i> Planejamento
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/routes.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/routes.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/routes.php')); ?>>
                                <i class="fas fa-route"></i> Rotas
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/simulador_rota.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/simulador_rota.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/simulador_rota.php')); ?>>
                                <i class="fas fa-calculator"></i> Simulador de Rota
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/planejamento_carga.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/planejamento_carga.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/planejamento_carga.php')); ?>>
                                <i class="fas fa-boxes-stacked"></i> Planejamento de Carga
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="sidebar-nav-item<?php echo $sf_open_frota ? ' sidebar-nav-item--section-active' : ''; ?>">
                    <a href="#" class="sidebar-link sidebar-dropdown-toggle<?php echo $sf_open_frota ? ' active' : ''; ?>">
                        <div class="sidebar-link-icon"><i class="fas fa-truck"></i></div>
                        <span class="sidebar-link-text">Frota</span>
                        <i class="fas fa-chevron-down dropdown-icon<?php echo $sf_open_frota ? ' rotate' : ''; ?>"></i>
                    </a>
                    <ul class="sidebar-dropdown"<?php echo $sf_open_frota ? ' data-dropdown-open="1" style="display:block"' : ''; ?>>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/vehicles.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/vehicles.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/vehicles.php')); ?>>
                                <i class="fas fa-truck"></i> Veículos
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/veiculo_timeline.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/veiculo_timeline.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/veiculo_timeline.php')); ?>>
                                <i class="fas fa-timeline"></i> Timeline do Veículo
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/qr_codes.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/qr_codes.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/qr_codes.php')); ?>>
                                <i class="fas fa-qrcode"></i> QR Codes
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/implementos.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/implementos.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/implementos.php')); ?>>
                                <i class="fas fa-trailer"></i> Implementos
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/abastecimentos.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/abastecimentos.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/abastecimentos.php')); ?>>
                                <i class="fas fa-gas-pump"></i> Abastecimento
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/gestao_diesel.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/gestao_diesel.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/gestao_diesel.php')); ?>>
                                <i class="fas fa-ranking-star"></i> Diesel · Postos
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/manutencoes.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/manutencoes.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/manutencoes.php')); ?>>
                                <i class="fas fa-tools"></i> Manutenções
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/manutencao_preditiva.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/manutencao_preditiva.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/manutencao_preditiva.php')); ?>>
                                <i class="fas fa-wand-magic-sparkles"></i> Manut. Preditiva
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="sidebar-nav-item<?php echo $sf_open_cad ? ' sidebar-nav-item--section-active' : ''; ?>">
                    <a href="#" class="sidebar-link sidebar-dropdown-toggle<?php echo $sf_open_cad ? ' active' : ''; ?>">
                        <div class="sidebar-link-icon"><i class="fas fa-address-book"></i></div>
                        <span class="sidebar-link-text">Cadastros</span>
                        <i class="fas fa-chevron-down dropdown-icon<?php echo $sf_open_cad ? ' rotate' : ''; ?>"></i>
                    </a>
                    <ul class="sidebar-dropdown"<?php echo $sf_open_cad ? ' data-dropdown-open="1" style="display:block"' : ''; ?>>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/motorists.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/motorists.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/motorists.php')); ?>>
                                <i class="fas fa-user-tie"></i> Motoristas
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/fornecedores_moderno.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active_rels(['pages/fornecedores_moderno.php', 'pages/fornecedores.php']); ?>"<?php echo sf_sidebar_aria_current_rels(['pages/fornecedores_moderno.php', 'pages/fornecedores.php']); ?>>
                                <i class="fas fa-truck-loading"></i> Fornecedores
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/clientes.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/clientes.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/clientes.php')); ?>>
                                <i class="fas fa-handshake"></i> Clientes
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/contratos.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/contratos.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/contratos.php')); ?>>
                                <i class="fas fa-file-signature"></i> Contratos
                            </a>
                        </li>
                    </ul>
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
                        <?php if ($sf_mod_cte): ?>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('fiscal/pages/cte.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('fiscal/pages/cte.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('fiscal/pages/cte.php')); ?>>
                                <i class="fas fa-truck"></i> Gestão de CT-e
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($sf_mod_mdfe): ?>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('fiscal/pages/mdfe.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('fiscal/pages/mdfe.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('fiscal/pages/mdfe.php')); ?>>
                                <i class="fas fa-file-alt"></i> Gestão de MDF-e
                            </a>
                        </li>
                        <?php endif; ?>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('fiscal/pages/ciot.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('fiscal/pages/ciot.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('fiscal/pages/ciot.php')); ?>>
                                <i class="fas fa-barcode"></i> CIOT (PEF ANTT)
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
                
                <?php if ($sf_mod_fin): ?>
                <!-- Menu Financeiro -->
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
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/painel_executivo.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/painel_executivo.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/painel_executivo.php')); ?>>
                                <i class="fas fa-chart-pie"></i> Painel Executivo
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/simulacao_crescimento.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/simulacao_crescimento.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/simulacao_crescimento.php')); ?>>
                                <i class="fas fa-arrow-trend-up"></i> Simulação de Crescimento
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/previsao_financeira.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/previsao_financeira.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/previsao_financeira.php')); ?>>
                                <i class="fas fa-chart-line"></i> Previsão Financeira
                            </a>
                        </li>
                        <?php if (can_access_lucratividade()): ?>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/lucratividade.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/lucratividade.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/lucratividade.php')); ?>>
                                <i class="fas fa-chart-line"></i> Lucratividade
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                
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
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/pneus_dashboard.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/pneus_dashboard.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/pneus_dashboard.php')); ?>>
                                <i class="fas fa-chart-pie"></i> Dashboard de Pneus
                            </a>
                        </li>
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
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/gestao_interativa_pro.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/gestao_interativa_pro.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/gestao_interativa_pro.php')); ?>>
                                <i class="fas fa-truck-monster"></i> Gestão Interativa
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/manutencao_pneus.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/manutencao_pneus.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/manutencao_pneus.php')); ?>>
                                <i class="fas fa-tools"></i> Manutenção de Pneus
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/pneus_inventario.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/pneus_inventario.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/pneus_inventario.php')); ?>>
                                <i class="fas fa-barcode"></i> Inventário QR
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
                        <?php if ($sf_mod_rastreamento): ?>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/mapa_frota.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/mapa_frota.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/mapa_frota.php')); ?>>
                                <i class="fas fa-map-marker-alt"></i> Mapa GPS
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/replay_viagem.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/replay_viagem.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/replay_viagem.php')); ?>>
                                <i class="fas fa-clapperboard"></i> Replay de Viagem
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                
                <?php if (can_access_advanced_reports()): ?>
                <li class="sidebar-nav-item<?php echo $sf_open_rel ? ' sidebar-nav-item--section-active' : ''; ?>">
                    <a href="#" class="sidebar-link sidebar-dropdown-toggle<?php echo $sf_open_rel ? ' active' : ''; ?>">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <span class="sidebar-link-text">Relatórios</span>
                        <i class="fas fa-chevron-down dropdown-icon<?php echo $sf_open_rel ? ' rotate' : ''; ?>"></i>
                    </a>
                    <ul class="sidebar-dropdown"<?php echo $sf_open_rel ? ' data-dropdown-open="1" style="display:block"' : ''; ?>>
                        <?php $sf_rel_base = htmlspecialchars(sf_app_url('pages/relatorios.php')); ?>
                        <li>
                            <a href="<?php echo $sf_rel_base; ?>" class="sidebar-dropdown-link<?php echo $sf_rel_active(''); ?>"<?php echo $sf_rel_active('') ? ' aria-current="page"' : ''; ?>>
                                <i class="fas fa-border-all"></i> Todos os relatórios
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $sf_rel_base; ?>?cat=operacionais" class="sidebar-dropdown-link<?php echo $sf_rel_active('operacionais'); ?>"<?php echo $sf_rel_active('operacionais') ? ' aria-current="page"' : ''; ?>>
                                <i class="fas fa-route"></i> Operacionais
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $sf_rel_base; ?>?cat=financeiros" class="sidebar-dropdown-link<?php echo $sf_rel_active('financeiros'); ?>"<?php echo $sf_rel_active('financeiros') ? ' aria-current="page"' : ''; ?>>
                                <i class="fas fa-dollar-sign"></i> Financeiros
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $sf_rel_base; ?>?cat=manutencao" class="sidebar-dropdown-link<?php echo $sf_rel_active('manutencao'); ?>"<?php echo $sf_rel_active('manutencao') ? ' aria-current="page"' : ''; ?>>
                                <i class="fas fa-tools"></i> Manutenção
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $sf_rel_base; ?>?cat=motoristas" class="sidebar-dropdown-link<?php echo $sf_rel_active('motoristas'); ?>"<?php echo $sf_rel_active('motoristas') ? ' aria-current="page"' : ''; ?>>
                                <i class="fas fa-id-card"></i> Motoristas &amp; Viagem
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $sf_rel_base; ?>?cat=ia" class="sidebar-dropdown-link<?php echo $sf_rel_active('ia'); ?>"<?php echo $sf_rel_active('ia') ? ' aria-current="page"' : ''; ?>>
                                <i class="fas fa-brain"></i> Inteligentes (IA)
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $sf_rel_base; ?>?cat=fiscais" class="sidebar-dropdown-link<?php echo $sf_rel_active('fiscais'); ?>"<?php echo $sf_rel_active('fiscais') ? ' aria-current="page"' : ''; ?>>
                                <i class="fas fa-file-invoice"></i> Fiscais
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo $sf_rel_base; ?>?cat=avancados" class="sidebar-dropdown-link<?php echo $sf_rel_active('avancados'); ?>"<?php echo $sf_rel_active('avancados') ? ' aria-current="page"' : ''; ?>>
                                <i class="fas fa-chart-bar"></i> Avançados
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>


                <?php if (can_manage_system_settings()):
                    $sf_cfg_url = sf_app_url('pages/configuracoes.php');
                    $sf_on_config = sf_sidebar_any_active(['pages/configuracoes.php', 'pages/empresa.php']);
                    $sf_cfg_aba = (strpos($current_path, 'pages/configuracoes.php') !== false) ? ($_GET['aba'] ?? 'geral') : '';
                    $sf_cfg_itens = [
                        'geral' => ['Geral', 'fas fa-sliders'],
                        'seguranca' => ['Segurança', 'fas fa-shield-halved'],
                    ];
                    if ($sf_mod_ia) {
                        $sf_cfg_itens['ia'] = ['IA & OCR', 'fas fa-robot'];
                    }
                    $sf_cfg_itens += [
                        'fiscal' => ['Fiscal & Multas', 'fas fa-file-invoice'],
                        'gps' => ['GPS', 'fas fa-draw-polygon'],
                        'sistema' => ['Sistema', 'fas fa-microchip'],
                        'gamificacao' => ['Gamificação', 'fas fa-trophy'],
                    ];
                ?>
                <li class="sidebar-nav-item<?php echo $sf_on_config ? ' sidebar-nav-item--section-active' : ''; ?>">
                    <a href="#" class="sidebar-link sidebar-dropdown-toggle<?php echo $sf_on_config ? ' active' : ''; ?>">
                        <div class="sidebar-link-icon">
                            <i class="fas fa-cog"></i>
                        </div>
                        <span class="sidebar-link-text">Configuração</span>
                        <i class="fas fa-chevron-down dropdown-icon<?php echo $sf_on_config ? ' rotate' : ''; ?>"></i>
                    </a>
                    <ul class="sidebar-dropdown"<?php echo $sf_on_config ? ' data-dropdown-open="1" style="display:block"' : ''; ?>>
                        <li>
                            <a href="<?php echo htmlspecialchars(sf_app_url('pages/empresa.php')); ?>" class="sidebar-dropdown-link<?php echo sf_sidebar_active(sf_app_url('pages/empresa.php')); ?>"<?php echo sf_sidebar_aria_current(sf_app_url('pages/empresa.php')); ?>>
                                <i class="fas fa-building"></i> Empresa
                            </a>
                        </li>
                        <?php foreach ($sf_cfg_itens as $aba_key => $aba_def):
                            $aba_active = ($sf_cfg_aba === $aba_key) ? ' active' : '';
                        ?>
                        <li>
                            <a href="<?php echo htmlspecialchars($sf_cfg_url . '?aba=' . $aba_key); ?>" class="sidebar-dropdown-link<?php echo $aba_active; ?>"<?php echo $aba_active ? ' aria-current="page"' : ''; ?>>
                                <i class="<?php echo $aba_def[1]; ?>"></i> <?php echo htmlspecialchars($aba_def[0]); ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php include __DIR__ . '/sidebar_support.php'; ?>
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

/* Rótulos de seção do menu (agrupamento por fluxo operacional) */
.sidebar-section-label {
    padding: 14px 18px 5px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--text-muted, #64748b);
    pointer-events: none;
    user-select: none;
}
.sidebar-section-label span { opacity: .85; }
/* Menu recolhido: esconde o texto e vira um divisor discreto */
.sidebar-collapsed .sidebar-section-label { padding: 8px 14px; }
.sidebar-collapsed .sidebar-section-label span { display: none; }
.sidebar-collapsed .sidebar-section-label::after {
    content: ""; display: block; height: 1px;
    background: var(--border-color, rgba(255,255,255,.12));
}
</style>