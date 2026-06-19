<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/pneus_helpers.php';
require_once __DIR__ . '/../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();
require_once '../includes/permissions.php';
require_permission('access_tire_management');

$page_title = 'Inventário de Pneus (QR)';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" type="image/png" href="../logo.png">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php sf_page_help_css('..'); ?>
    <style>
        .inv-wrap { max-width: 720px; margin: 0 auto; padding: 1rem; }
        .inv-scan { display: flex; gap: 8px; margin: 1rem 0; }
        .inv-scan input { flex: 1; padding: 10px; font-size: 1rem; }
        .inv-list { max-height: 400px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px; }
        .inv-item { padding: 10px 12px; border-bottom: 1px solid var(--border-color); }
        .inv-kpi { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin: 1rem 0; }
        .inv-kpi div { background: var(--bg-secondary); padding: 12px; border-radius: 8px; text-align: center; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include '../includes/sidebar_pages.php'; ?>
    <div class="main-content">
        <?php include '../includes/header.php'; ?>
        <div class="dashboard-content inv-wrap">
            <h1><i class="fas fa-barcode"></i> <?php echo htmlspecialchars($page_title); ?></h1>
            <p>Leia o QR Code ou digite o token do pneu para registrar no inventário.</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
                <button type="button" class="btn btn-primary" id="btnNovaSessao"><i class="fas fa-play"></i> Iniciar inventário</button>
                <button type="button" class="btn btn-secondary" id="btnFechar" disabled><i class="fas fa-stop"></i> Fechar sessão</button>
                <a href="pneus_dashboard.php" class="btn btn-link">Dashboard</a>
                <button type="button" class="btn btn-secondary" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
            </div>
            <div class="inv-kpi">
                <div><div class="lbl">Sessão</div><strong id="kpiSessao">—</strong></div>
                <div><div class="lbl">Lidos</div><strong id="kpiLidos">0</strong></div>
                <div><div class="lbl">Status</div><strong id="kpiStatus">Aguardando</strong></div>
            </div>
            <div class="inv-scan">
                <input type="text" id="qrInput" placeholder="QR / token / ID do pneu" autocomplete="off" disabled>
                <button type="button" class="btn btn-primary" id="btnScan" disabled><i class="fas fa-check"></i> Registrar</button>
            </div>
            <div id="lastScan" class="alert alert-success" style="display:none"></div>
            <h2>Itens lidos</h2>
            <div class="inv-list" id="listaItens"><p style="padding:12px">Nenhum item ainda.</p></div>
        </div>
    </div>
</div>
<script src="../js/theme.js"></script>
<script src="../js/sidebar.js"></script>
<script src="../js/pneus_mercado.js"></script>
<script>
PneusMercado.initInventario({
    apiBase: '../api/pneus_mercado.php',
    csrf: <?php echo json_encode(csrf_token_get()); ?>
});
</script>
<?php $sf_help_key = 'pneus_inventario'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
<?php sf_page_help_js('..'); ?>
</body>
</html>
