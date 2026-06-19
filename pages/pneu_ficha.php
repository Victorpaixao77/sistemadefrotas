<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/pneus_helpers.php';
require_once '../includes/pneus_repository.php';

configure_session();
session_start();
require_authentication();
require_once '../includes/permissions.php';
require_permission('access_tire_management');

$token = trim((string) ($_GET['t'] ?? ''));
$page_title = 'Ficha do Pneu';
$ficha = null;
$erro = null;

if ($token === '') {
    $erro = 'Link inválido. Escaneie o QR Code do pneu.';
} else {
    try {
        $conn = getConnection();
        $empresa_id = pneus_empresa_id();
        if (!pneus_column_exists($conn, 'pneus', 'qr_token')) {
            $erro = 'Execute a migração sql/pneus_profissional_v1.sql para habilitar QR Code.';
        } else {
            $st = $conn->prepare('SELECT id FROM pneus WHERE qr_token = ? AND empresa_id = ? LIMIT 1');
            $st->execute([$token, $empresa_id]);
            $pneu_id = (int) ($st->fetchColumn() ?: 0);
            if ($pneu_id <= 0) {
                $erro = 'Pneu não encontrado ou sem permissão.';
            } else {
                $ficha = pneus_get_ficha($conn, $pneu_id, $empresa_id);
            }
        }
    } catch (Throwable $e) {
        error_log('pneu_ficha.php: ' . $e->getMessage());
        $erro = 'Erro ao carregar ficha.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/pneus_timeline.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="app-container">
    <?php include '../includes/sidebar_pages.php'; ?>
    <div class="main-content">
        <?php include '../includes/header.php'; ?>
        <div class="dashboard-content" style="padding:1.5rem">
            <?php if ($erro): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
            <?php elseif ($ficha): ?>
                <?php $p = $ficha['pneu']; $ck = $ficha['custo_km'] ?? []; ?>
                <h1><i class="fas fa-qrcode"></i> Pneu <?php echo htmlspecialchars($p['numero_serie'] ?? ''); ?></h1>
                <p><?php echo htmlspecialchars(($p['marca'] ?? '') . ' ' . ($p['modelo'] ?? '') . ' — ' . ($p['medida'] ?? '')); ?></p>
                <div class="pneu-ficha-grid">
                    <div class="pneu-ficha-kpi"><div class="lbl">Status</div><div class="val"><?php echo htmlspecialchars($p['status_nome'] ?? '—'); ?></div></div>
                    <div class="pneu-ficha-kpi"><div class="lbl">Sulco atual</div><div class="val"><?php echo $ficha['sulco_atual_mm'] !== null ? htmlspecialchars($ficha['sulco_atual_mm']) . ' mm' : '—'; ?></div></div>
                    <div class="pneu-ficha-kpi"><div class="lbl">Veículo</div><div class="val"><?php echo htmlspecialchars($ficha['veiculo_placa'] ?? 'Estoque'); ?></div></div>
                    <div class="pneu-ficha-kpi"><div class="lbl">Custo/km</div><div class="val"><?php echo isset($ck['custo_km']) && $ck['custo_km'] !== null ? 'R$ ' . number_format($ck['custo_km'], 4, ',', '.') : '—'; ?></div></div>
                    <div class="pneu-ficha-kpi"><div class="lbl">Recapagens</div><div class="val"><?php echo (int) ($p['numero_recapagens'] ?? 0); ?></div></div>
                    <div class="pneu-ficha-kpi"><div class="lbl">Valor compra</div><div class="val"><?php echo !empty($p['valor_compra']) ? 'R$ ' . number_format((float) $p['valor_compra'], 2, ',', '.') : '—'; ?></div></div>
                </div>
                <?php $merc = $ficha['mercado'] ?? []; $car = $merc['carcaca'] ?? null; ?>
                <?php if ($car): ?>
                <h2>Carcaça</h2>
                <div class="pneu-ficha-grid">
                    <div class="pneu-ficha-kpi"><div class="lbl">Código</div><div class="val"><?php echo htmlspecialchars($car['codigo'] ?? '—'); ?></div></div>
                    <div class="pneu-ficha-kpi"><div class="lbl">Recapagens</div><div class="val"><?php echo (int) ($car['recapagens'] ?? 0); ?></div></div>
                    <div class="pneu-ficha-kpi"><div class="lbl">KM acumulados</div><div class="val"><?php echo number_format((int) ($car['km_acumulados'] ?? 0), 0, '', '.'); ?></div></div>
                    <div class="pneu-ficha-kpi"><div class="lbl">Investido</div><div class="val">R$ <?php echo number_format((float) ($car['valor_investido'] ?? 0), 2, ',', '.'); ?></div></div>
                    <div class="pneu-ficha-kpi"><div class="lbl">Status carcaça</div><div class="val"><?php echo htmlspecialchars($car['status_carcaca'] ?? '—'); ?></div></div>
                    <?php if (!empty($merc['dot'])): $di = $merc['dot']; ?>
                    <div class="pneu-ficha-kpi"><div class="lbl">DOT</div><div class="val">Sem. <?php echo (int) $di['semana']; ?>/<?php echo (int) $di['ano']; ?> (<?php echo htmlspecialchars($di['alerta']); ?>)</div></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <h2>Medições de sulco</h2>
                <div id="sulcoChart"></div>
                <h2>Histórico</h2>
                <div id="timelineVis"></div>
                <p style="margin-top:1.5rem">
                    <a href="pneus.php" class="btn btn-primary">Abrir cadastro de pneus</a>
                    <a href="gestao_interativa.php" class="btn btn-secondary">Gestão interativa</a>
                </p>
                <script src="../js/pneus_timeline.js"></script>
                <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (window.PneuTimeline) {
                        PneuTimeline.renderSulcoChart(document.getElementById('sulcoChart'), <?php echo json_encode($ficha['medicoes_sulco'] ?? [], JSON_UNESCAPED_UNICODE); ?>);
                        PneuTimeline.render(document.getElementById('timelineVis'), <?php echo json_encode($ficha['timeline'] ?? [], JSON_UNESCAPED_UNICODE); ?>);
                    }
                });
                </script>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="../js/theme.js"></script>
<script src="../js/sidebar.js"></script>
</body>
</html>
