<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/sf_api_base.php';

configure_session();
session_start();
require_authentication();

$page_title = 'Centro de Notificações';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('css/styles.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('css/responsive.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('notificacoes/ia_fabicon.css')); ?>">
    <style>
        .notif-centro { max-width: 960px; margin: 0 auto; padding: 1rem; }
        .notif-toolbar { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem; align-items: center; }
        .notif-toolbar select, .notif-toolbar button { padding: 0.45rem 0.75rem; border-radius: 8px; border: 1px solid var(--border-color, #334155); background: var(--bg-secondary, #1e293b); color: inherit; }
        .notif-prefs { margin-top: 1.5rem; padding: 1rem; border-radius: 12px; background: var(--bg-secondary, #1e293b); border: 1px solid var(--border-color, #334155); }
        .notif-prefs label { display: block; margin: 0.35rem 0; }
        .notif-list-centro .notification-item, .notif-list-centro .ia-notification-item { margin-bottom: 8px; border-radius: 8px; }
        .sev-critico { border-left: 4px solid #ef4444; }
        .sev-aviso { border-left: 4px solid #f59e0b; }
        .sev-info { border-left: 4px solid #3b82f6; }
        .notif-acoes { margin-top: 8px; display: flex; gap: 6px; flex-wrap: wrap; }
        .notif-acoes button { font-size: 0.8rem; padding: 4px 10px; border-radius: 6px; border: none; cursor: pointer; }
        .btn-aprovar { background: #16a34a; color: #fff; }
        .btn-rejeitar { background: #dc2626; color: #fff; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar_pages.php'; ?>
<main class="main-content">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <div class="notif-centro">
        <p style="color: var(--text-secondary, #94a3b8); margin-bottom: 1rem;">Histórico, filtros e preferências dos alertas operacionais e de IA.</p>
        <div class="notif-toolbar">
            <select id="filtroEscopo" aria-label="Escopo">
                <option value="todos">Todos</option>
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
            <label><input type="checkbox" id="verTodas"> Incluir lidas (1 ano)</label>
            <button type="button" id="btnSync" class="btn btn-secondary">Atualizar agora</button>
            <button type="button" id="btnLimparEscopo" class="btn btn-secondary">Limpar escopo</button>
        </div>
        <div id="notifListCentro" class="notif-list-centro" aria-live="polite"></div>
        <div class="notif-prefs">
            <h3 style="margin-top:0;">Preferências</h3>
            <label><input type="checkbox" id="prefDigest"> Receber resumo diário por e-mail</label>
            <label>Hora do resumo: <input type="time" id="prefDigestHora" value="08:00"></label>
            <div id="prefTiposOff" style="margin-top:0.75rem;"></div>
            <button type="button" id="btnSalvarPrefs" class="btn btn-primary" style="margin-top:0.75rem;">Salvar preferências</button>
        </div>
    </div>
</main>
<?php sf_render_api_scripts(); ?>
<script src="<?php echo htmlspecialchars(sf_app_url('js/notificacoes-common.js')); ?>"></script>
<script src="<?php echo htmlspecialchars(sf_app_url('js/notificacoes-centro.js')); ?>"></script>
</body>
</html>
