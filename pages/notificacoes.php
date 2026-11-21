<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sf_api_base.php';
require_once __DIR__ . '/../includes/notificacoes_helpers.php';

configure_session();
session_start();
require_authentication();

$page_title = 'Centro de Notificações';
$csrf_token = csrf_token_get();
$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
$usuario_id = sf_notif_usuario_id();

$notif_counts = [
    'operacional' => 0,
    'ia' => 0,
    'sistema_ativo' => true,
];

if ($empresa_id > 0) {
    try {
        $conn = getConnection();
        sf_notif_ensure_schema($conn);
        $notif_counts['sistema_ativo'] = sf_notificacoes_sistema_ativo($conn, $empresa_id);
        if ($notif_counts['sistema_ativo']) {
            $notif_counts['operacional'] = sf_notif_contar_nao_lidas_usuario($conn, $empresa_id, $usuario_id, 'operacional');
            $notif_counts['ia'] = sf_notif_contar_nao_lidas_usuario($conn, $empresa_id, $usuario_id, 'ia');
        }
    } catch (Throwable $e) {
        error_log('pages/notificacoes.php counts: ' . $e->getMessage());
    }
}
$notif_total = (int) $notif_counts['operacional'] + (int) $notif_counts['ia'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Sistema de Gestão de Frotas — <?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="icon" type="image/png" href="../logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('css/styles.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('css/theme.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('css/responsive.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('css/fornc-modern-page.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('css/notif-preferencias.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('css/notificacoes-centro.css?v=1.0')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('notificacoes/ia_fabicon.css')); ?>">
    <?php sf_render_api_scripts(); ?>
</head>
<body class="notif-centro-body">
    <div class="app-container">
        <?php include __DIR__ . '/../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include __DIR__ . '/../includes/header.php'; ?>
            <div class="dashboard-content fornc-page notif-centro-page" id="conteudo-principal">
                <div class="notif-page-head">
                    <div>
                        <h2><i class="fas fa-bell" aria-hidden="true"></i> <?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p class="notif-page-lead">Histórico completo, filtros e preferências dos alertas operacionais e de inteligência artificial.</p>
                    </div>
                    <button type="button" id="btnSync" class="fornc-btn fornc-btn--primary" title="Sincronizar e atualizar lista">
                        <i class="fas fa-sync-alt" aria-hidden="true"></i> Atualizar
                    </button>
                </div>

                <?php if (!$notif_counts['sistema_ativo']): ?>
                <div class="notif-centro-inactive" role="status">
                    <i class="fas fa-bell-slash" aria-hidden="true"></i>
                    O sistema de notificações está desativado para esta empresa.
                    Ative em <a href="<?php echo htmlspecialchars(sf_app_url('pages/configuracoes.php?aba=sistema'), ENT_QUOTES, 'UTF-8'); ?>">Configurações → Sistema</a>.
                </div>
                <?php endif; ?>

                <div class="notif-centro-kpis" role="group" aria-label="Resumo de não lidas">
                    <button type="button" class="notif-centro-kpi notif-centro-kpi--total is-active" data-filtro-escopo="todos" aria-pressed="true">
                        <div class="notif-centro-kpi__label">Total não lidas</div>
                        <div class="notif-centro-kpi__value" id="kpiTotal"><?php echo (int) $notif_total; ?></div>
                    </button>
                    <button type="button" class="notif-centro-kpi notif-centro-kpi--op" data-filtro-escopo="operacional" aria-pressed="false">
                        <div class="notif-centro-kpi__label">Operacionais</div>
                        <div class="notif-centro-kpi__value" id="kpiOperacional"><?php echo (int) $notif_counts['operacional']; ?></div>
                    </button>
                    <button type="button" class="notif-centro-kpi notif-centro-kpi--ia" data-filtro-escopo="ia" aria-pressed="false">
                        <div class="notif-centro-kpi__label">Inteligência artificial</div>
                        <div class="notif-centro-kpi__value" id="kpiIa"><?php echo (int) $notif_counts['ia']; ?></div>
                    </button>
                </div>

                <div class="notif-centro-panel">
                    <div class="notif-centro-panel__head">
                        <h3><i class="fas fa-inbox" aria-hidden="true"></i> Alertas</h3>
                        <div class="notif-centro-toolbar">
                            <input type="search" id="filtroBusca" placeholder="Buscar título ou mensagem…" aria-label="Buscar notificações" autocomplete="off">
                            <select id="filtroEscopo" aria-label="Escopo" hidden>
                                <option value="todos" selected>Todos</option>
                                <option value="operacional">Operacionais</option>
                                <option value="ia">IA</option>
                            </select>
                            <select id="filtroSeveridade" aria-label="Severidade">
                                <option value="">Todas severidades</option>
                                <option value="critico">Crítico</option>
                                <option value="aviso">Aviso</option>
                                <option value="info">Info</option>
                            </select>
                            <select id="filtroTipo" aria-label="Tipo">
                                <option value="">Todos os tipos</option>
                            </select>
                            <label class="notif-centro-check">
                                <input type="checkbox" id="verTodas"> Incluir lidas (1 ano)
                            </label>
                            <button type="button" id="btnLimparEscopo" class="fornc-btn fornc-btn--muted">
                                <i class="fas fa-check-double" aria-hidden="true"></i> Marcar escopo como lido
                            </button>
                        </div>
                    </div>
                    <div class="notif-centro-list-wrap">
                        <div id="notifListCentro" class="notif-list-centro" aria-live="polite" aria-busy="true">
                            <div class="notif-centro-loading" id="notifListLoading">
                                <div class="notif-spinner" aria-hidden="true"></div>
                                <span>Carregando notificações…</span>
                            </div>
                        </div>
                    </div>
                </div>

                <details class="notif-centro-panel notif-prefs-centro">
                    <summary><i class="fas fa-sliders-h" aria-hidden="true"></i> Preferências de notificação</summary>
                    <p class="notif-prefs-lead">Escolha quais tipos deseja silenciar e configure o resumo diário por e-mail.</p>
                    <div class="notif-prefs-digest">
                        <label class="notif-pref-check">
                            <input type="checkbox" id="prefDigest"> Receber resumo diário por e-mail
                        </label>
                        <label class="notif-pref-time">Hora do resumo:
                            <input type="time" id="prefDigestHora" value="08:00">
                        </label>
                    </div>
                    <div class="notif-prefs-grid">
                        <div class="notif-prefs-col">
                            <div class="notif-prefs-col-head">
                                <h6><i class="fas fa-truck" aria-hidden="true"></i> Operacionais</h6>
                                <span class="notif-prefs-actions">
                                    <button type="button" class="btn-link-sm" data-pref-grupo="operacional" data-pref-acao="none">Silenciar todos</button>
                                    ·
                                    <button type="button" class="btn-link-sm" data-pref-grupo="operacional" data-pref-acao="all">Ativar todos</button>
                                </span>
                            </div>
                            <div id="prefTiposOffOp" class="notif-prefs-tipos notif-prefs-loading">Carregando…</div>
                        </div>
                        <div class="notif-prefs-col">
                            <div class="notif-prefs-col-head">
                                <h6><i class="fas fa-brain" aria-hidden="true"></i> Inteligência artificial</h6>
                                <span class="notif-prefs-actions">
                                    <button type="button" class="btn-link-sm" data-pref-grupo="ia" data-pref-acao="none">Silenciar todos</button>
                                    ·
                                    <button type="button" class="btn-link-sm" data-pref-grupo="ia" data-pref-acao="all">Ativar todos</button>
                                </span>
                            </div>
                            <div id="prefTiposOffIa" class="notif-prefs-tipos notif-prefs-loading">Carregando…</div>
                        </div>
                    </div>
                    <button type="button" id="btnSalvarPrefs" class="fornc-btn fornc-btn--primary" style="margin-top:0.85rem;">
                        <i class="fas fa-save" aria-hidden="true"></i> Salvar preferências
                    </button>
                    <div id="notifPrefsMsg" class="notif-centro-msg" role="status" aria-live="polite"></div>
                </details>
            </div>
            <?php include __DIR__ . '/../includes/footer.php'; ?>
        </div>
    </div>

    <script src="<?php echo htmlspecialchars(sf_app_url('js/theme.js')); ?>"></script>
    <script src="<?php echo htmlspecialchars(sf_app_url('js/sidebar.js')); ?>"></script>
    <script src="<?php echo htmlspecialchars(sf_app_url('js/notificacoes-common.js')); ?>"></script>
    <script src="<?php echo htmlspecialchars(sf_app_url('js/notificacoes-centro.js?v=1.1')); ?>"></script>
</body>
</html>
