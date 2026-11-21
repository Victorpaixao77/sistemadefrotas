<?php
require_once __DIR__ . '/adm_paths.php';
sf_project_include('includes/sf_paths.php');

$adm_current = basename($_SERVER['PHP_SELF'] ?? '');

if (!function_exists('adm_sidebar_active')) {
    function adm_sidebar_active(string $file): string
    {
        global $adm_current;
        return $adm_current === $file ? ' active' : '';
    }
    function adm_sidebar_aria(string $file): string
    {
        global $adm_current;
        return $adm_current === $file ? ' aria-current="page"' : '';
    }
    function adm_nav_section_open(array $items): bool
    {
        global $adm_current;
        foreach ($items as $item) {
            if (($item[0] ?? '') === $adm_current) {
                return true;
            }
        }
        return false;
    }
}

$adm_asset_v = function_exists('sf_asset_v') ? sf_asset_v() : '1';

/** [file, icon, label] */
$adm_nav = [
    [
        'label' => 'Visão',
        'icon' => 'fa-compass',
        'items' => [
            ['index.php', 'fa-gauge-high', 'Dashboard'],
            ['monitor.php', 'fa-broadcast-tower', 'Monitor ao vivo'],
            ['saude.php', 'fa-heart-pulse', 'Saúde (NOC)'],
            ['sla.php', 'fa-chart-line', 'SLA'],
            ['heatmap.php', 'fa-fire', 'Heatmap'],
            ['timeline.php', 'fa-stream', 'Timeline'],
            ['mapa_clientes.php', 'fa-earth-americas', 'Mapa clientes'],
        ],
    ],
    [
        'label' => 'Operações',
        'icon' => 'fa-building',
        'items' => [
            ['empresas.php', 'fa-building', 'Empresas'],
            ['planos.php', 'fa-tags', 'Planos'],
            ['usuarios.php', 'fa-users', 'Usuários'],
            ['licenciamento.php', 'fa-key', 'Licenciamento'],
            ['marketplace.php', 'fa-store', 'Marketplace'],
            ['health_score.php', 'fa-heart', 'Health Score'],
            ['suporte.php', 'fa-user-secret', 'Modo suporte'],
        ],
    ],
    [
        'label' => 'Financeiro',
        'icon' => 'fa-coins',
        'items' => [
            ['financeiro_executivo.php', 'fa-chart-line', 'Financeiro SaaS'],
            ['comercial.php', 'fa-handshake', 'Painel comercial'],
            ['custos.php', 'fa-file-invoice-dollar', 'Custos plataforma'],
            ['posicao_financeira.php', 'fa-wallet', 'Posição financeira'],
        ],
    ],
    [
        'label' => 'Observabilidade',
        'icon' => 'fa-eye',
        'items' => [
            ['performance.php', 'fa-gauge', 'Performance'],
            ['banco.php', 'fa-database', 'Banco de dados'],
            ['sql_monitor.php', 'fa-code', 'SQL ao vivo'],
            ['apis.php', 'fa-plug', 'APIs externas'],
            ['logs.php', 'fa-terminal', 'Central de logs'],
            ['alertas.php', 'fa-bell', 'Alertas'],
            ['erros.php', 'fa-bug', 'Central de erros'],
            ['benchmark.php', 'fa-ranking-star', 'Benchmark'],
        ],
    ],
    [
        'label' => 'Inteligência',
        'icon' => 'fa-brain',
        'items' => [
            ['ia_admin.php', 'fa-brain', 'IA administrativa'],
            ['ia_dev.php', 'fa-code-branch', 'IA desenvolvedor'],
            ['ia_estatisticas.php', 'fa-robot', 'Estatísticas IA'],
            ['auditoria.php', 'fa-shield-halved', 'Auditoria'],
            ['ia_comercial.php', 'fa-lightbulb', 'IA comercial'],
            ['analytics.php', 'fa-chart-pie', 'Analytics'],
        ],
    ],
    [
        'label' => 'Plataforma',
        'icon' => 'fa-server',
        'items' => [
            ['deploy.php', 'fa-rocket', 'Deploy'],
            ['deploy_producao.php', 'fa-cloud-upload-alt', 'Deploy Produção'],
            ['jobs.php', 'fa-clock', 'Jobs / Cron'],
            ['storage.php', 'fa-hard-drive', 'Storage'],
            ['dependencias.php', 'fa-box', 'Dependências'],
            ['configuracoes_cc.php', 'fa-sliders', 'Configurações'],
            ['comunicacao.php', 'fa-paper-plane', 'Comunicação'],
            ['backup.php', 'fa-database', 'Backup'],
            ['sistema.php', 'fa-server', 'Sistema'],
            ['roadmap.php', 'fa-road', 'Roadmap'],
            ['seguranca_premium.php', 'fa-shield-virus', 'Segurança NOC'],
            ['conta_adm.php', 'fa-user-shield', 'Minha conta / 2FA'],
            ['log_acessos.php', 'fa-clock-rotate-left', 'Log de acessos'],
            ['seguranca_login.php', 'fa-lock', 'Login / reCAPTCHA'],
        ],
    ],
];
?>
<a href="#conteudo-principal" class="skip-to-main">Pular para o conteúdo principal</a>
<div class="sidebar">
    <script>
    (function() {
        try {
            var savedTheme = localStorage.getItem('theme');
            var isLight = savedTheme === 'light';
            var root = document.documentElement;
            if (isLight) {
                root.classList.add('light-theme');
                if (document.body) document.body.classList.add('light-theme');
            } else {
                root.classList.remove('light-theme');
                if (document.body) document.body.classList.remove('light-theme');
            }
            if (localStorage.getItem('sidebarCollapsed') === 'true' && document.body) {
                document.body.classList.add('sidebar-collapsed', 'sidebar-no-anim');
            }
        } catch (e) { /* ignore */ }
    })();
    </script>
    <script src="<?php echo htmlspecialchars(sf_app_url('js/color-theme.js?v=' . $adm_asset_v)); ?>"></script>
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="logo-image">
                <img src="<?php echo htmlspecialchars(sf_app_url('logo.png')); ?>" alt="Frotec">
            </div>
            <div class="logo-text">
                <span class="app-name">Control Center</span>
            </div>
        </div>
        <button type="button" class="sidebar-toggle" aria-label="Recolher ou expandir menu lateral" title="Recolher ou expandir menu">
            <span class="sidebar-toggle-dots" aria-hidden="true"></span>
        </button>
    </div>

    <div class="sidebar-content">
        <div class="adm-sidebar-search-wrap">
            <input type="search" id="adm-sidebar-search" class="adm-sidebar-search" placeholder="Buscar no menu…" autocomplete="off" aria-label="Buscar no menu">
        </div>
        <nav class="sidebar-nav" id="sidebar-nav" aria-label="Menu Control Center">
            <ul class="sidebar-nav-list adm-cc-sidebar-nav">
                <?php foreach ($adm_nav as $section):
                    $open = adm_nav_section_open($section['items']);
                ?>
                <li class="sidebar-nav-item<?php echo $open ? ' sidebar-nav-item--section-active' : ''; ?>">
                    <a href="#" class="sidebar-link sidebar-dropdown-toggle<?php echo $open ? ' active' : ''; ?>" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>">
                        <div class="sidebar-link-icon"><i class="fas <?php echo htmlspecialchars($section['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i></div>
                        <span class="sidebar-link-text"><?php echo htmlspecialchars($section['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <i class="fas fa-chevron-down dropdown-icon<?php echo $open ? ' rotate' : ''; ?>" aria-hidden="true"></i>
                    </a>
                    <ul class="sidebar-dropdown"<?php echo $open ? ' data-dropdown-open="1" style="display:block"' : ''; ?>>
                        <?php foreach ($section['items'] as $item): ?>
                        <li>
                            <a href="<?php echo htmlspecialchars($item[0], ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-dropdown-link<?php echo adm_sidebar_active($item[0]); ?>"<?php echo adm_sidebar_aria($item[0]); ?>>
                                <i class="fas <?php echo htmlspecialchars($item[1], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                                <?php echo htmlspecialchars($item[2], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <?php endforeach; ?>

                <li class="sidebar-nav-item adm-cc-sidebar-external">
                    <a href="acessar_frotas.php" class="sidebar-link" title="Escolher empresa e abrir o sistema de frotas">
                        <div class="sidebar-link-icon"><i class="fas fa-truck-fast" aria-hidden="true"></i></div>
                        <span class="sidebar-link-text">Sistema de frotas</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php include SF_PROJECT_ROOT . '/includes/sidebar_support.php'; ?>
</div>

<div class="sidebar-overlay" aria-hidden="true"></div>
