<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/pneus_helpers.php';
require_once '../includes/pneus_repository.php';
require_once '../includes/csrf.php';
require_once '../includes/sf_api_base.php';

configure_session();
session_start();
require_authentication();
require_once '../includes/permissions.php';
require_permission('access_tire_management');

if (!function_exists('sf_pneu_dot_css_class')) {
    function sf_pneu_dot_css_class(?array $dot): string
    {
        if (!$dot) {
            return '';
        }
        $alerta = (string) ($dot['alerta'] ?? '');
        if ($alerta === 'vencido') {
            return 'pneu-dot-vencido';
        }
        if ($alerta === 'atencao_5anos') {
            return 'pneu-dot-alerta';
        }
        return '';
    }
}

if (!function_exists('sf_pneu_dot_label')) {
    function sf_pneu_dot_label(?array $dot): string
    {
        if (!$dot) {
            return '—';
        }
        $alerta = (string) ($dot['alerta'] ?? '');
        $idade = isset($dot['idade_anos']) ? round((float) $dot['idade_anos'], 1) : null;
        $base = 'Sem. ' . (int) ($dot['semana'] ?? 0) . '/' . (int) ($dot['ano'] ?? 0);
        if ($idade !== null) {
            $base .= ' (' . number_format($idade, 1, ',', '.') . ' anos)';
        }
        $status = match ($alerta) {
            'vencido' => ' — acima de 7 anos',
            'atencao_5anos' => ' — atenção (+5 anos)',
            'ok' => ' — dentro do prazo',
            default => '',
        };
        return $base . $status;
    }
}

if (!function_exists('sf_pneu_prev_alerta_text')) {
    function sf_pneu_prev_alerta_text(array $prev): ?string
    {
        $alerta = (string) ($prev['alerta'] ?? '');
        return match ($alerta) {
            'troca_imediata' => 'Troca imediata recomendada (sulco no limite).',
            'sem_dados' => 'Sem medições suficientes para previsão.',
            'atencao' => 'Sulco baixo — acompanhar de perto.',
            default => null,
        };
    }
}

$token = trim((string) ($_GET['t'] ?? ''));
$pneu_id_param = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$page_title = 'Ficha do Pneu';
$ficha = null;
$pneu_id = 0;
$erro = null;

try {
    $conn = getConnection();
    $empresa_id = pneus_empresa_id();

    if ($token !== '') {
        if (!pneus_column_exists($conn, 'pneus', 'qr_token')) {
            $erro = 'Execute a migração sql/pneus_profissional_v1.sql para habilitar QR Code.';
        } else {
            $st = $conn->prepare('SELECT id FROM pneus WHERE qr_token = ? AND empresa_id = ? LIMIT 1');
            $st->execute([$token, $empresa_id]);
            $pneu_id = (int) ($st->fetchColumn() ?: 0);
            if ($pneu_id <= 0) {
                $erro = 'Pneu não encontrado ou sem permissão para esta empresa.';
            }
        }
    } elseif ($pneu_id_param > 0) {
        if (!pneus_validar_pneu_empresa($conn, $pneu_id_param, $empresa_id)) {
            $erro = 'Pneu não encontrado ou sem permissão.';
        } else {
            $pneu_id = $pneu_id_param;
        }
    } else {
        $erro = 'Link inválido. Escaneie o QR Code do pneu ou informe o identificador.';
    }

    if ($erro === null && $pneu_id > 0) {
        $ficha = pneus_get_ficha($conn, $pneu_id, $empresa_id);
        if (!$ficha || empty($ficha['pneu'])) {
            $erro = 'Não foi possível carregar os dados do pneu.';
            $ficha = null;
        }
    }
} catch (Throwable $e) {
    error_log('pneu_ficha.php: ' . $e->getMessage());
    $erro = 'Erro ao carregar ficha.';
}

$tipo_rodado_labels = [
    'direcional' => 'Direcional',
    'tracao' => 'Tração',
    'livre' => 'Livre',
    'implemento' => 'Implemento',
    'qualquer' => 'Qualquer',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token_get(), ENT_QUOTES, 'UTF-8'); ?>">
    <title>Sistema de Gestão de Frotas — <?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="icon" type="image/png" href="../logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('css/styles.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('css/theme.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('css/responsive.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('css/fornc-modern-page.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('css/pneus_timeline.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('css/pneu-ficha-page.css?v=1.0')); ?>">
    <?php sf_render_api_scripts(); ?>
</head>
<body>
<div class="app-container">
    <?php include '../includes/sidebar_pages.php'; ?>
    <div class="main-content">
        <?php include '../includes/header.php'; ?>
        <div class="dashboard-content fornc-page pneu-ficha-page" id="conteudo-principal">
            <?php if ($erro): ?>
                <div class="pneu-ficha-alert" role="alert">
                    <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                    <?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <p>
                    <a href="<?php echo htmlspecialchars(sf_app_url('pages/pneus.php'), ENT_QUOTES, 'UTF-8'); ?>" class="fornc-btn fornc-btn--primary">
                        <i class="fas fa-circle" aria-hidden="true"></i> Voltar aos pneus
                    </a>
                </p>
            <?php elseif ($ficha): ?>
                <?php
                $p = $ficha['pneu'];
                $ck = $ficha['custo_km'] ?? [];
                $prev = $ficha['previsao_sulco'] ?? [];
                $merc = $ficha['mercado'] ?? [];
                $car = $merc['carcaca'] ?? null;
                $dot = $merc['dot'] ?? null;
                $km_rodado = (int) ($ck['km_rodado'] ?? $p['km_rodado_total'] ?? 0);
                $tipo_rodado = (string) ($p['tipo_rodado_ideal'] ?? '');
                $prev_alerta = sf_pneu_prev_alerta_text($prev);
                $ficha_url = sf_app_url('pages/pneu_ficha.php?t=' . urlencode((string) ($ficha['qr_token'] ?? $token)));
                ?>
                <div class="pf-head">
                    <div>
                        <h1><i class="fas fa-qrcode" aria-hidden="true"></i> Pneu <?php echo htmlspecialchars((string) ($p['numero_serie'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
                        <p class="pf-sub"><?php echo htmlspecialchars(trim(($p['marca'] ?? '') . ' ' . ($p['modelo'] ?? '') . ' — ' . ($p['medida'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div class="pf-actions">
                        <button type="button" class="fornc-btn fornc-btn--ghost" onclick="window.print()">
                            <i class="fas fa-print" aria-hidden="true"></i> Imprimir
                        </button>
                        <a href="<?php echo htmlspecialchars(sf_app_url('pages/pneus.php'), ENT_QUOTES, 'UTF-8'); ?>" class="fornc-btn fornc-btn--muted">
                            <i class="fas fa-list" aria-hidden="true"></i> Cadastro
                        </a>
                        <a href="<?php echo htmlspecialchars(sf_app_url('pages/gestao_interativa_pro.php'), ENT_QUOTES, 'UTF-8'); ?>" class="fornc-btn fornc-btn--muted">
                            <i class="fas fa-th" aria-hidden="true"></i> Gestão interativa
                        </a>
                    </div>
                </div>

                <?php if ($prev_alerta): ?>
                <div class="pneu-ficha-alert pneu-ficha-alert--warn" role="status">
                    <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                    <?php echo htmlspecialchars($prev_alerta, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>

                <div class="pneu-ficha-panel">
                    <div class="pneu-ficha-panel__head"><i class="fas fa-chart-bar" aria-hidden="true"></i> Resumo</div>
                    <div class="pneu-ficha-panel__body">
                        <div class="pneu-ficha-grid">
                            <div class="pneu-ficha-kpi"><div class="lbl">Status</div><div class="val"><?php echo htmlspecialchars((string) ($p['status_nome'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div></div>
                            <div class="pneu-ficha-kpi"><div class="lbl">Sulco atual</div><div class="val"><?php echo $ficha['sulco_atual_mm'] !== null ? htmlspecialchars((string) $ficha['sulco_atual_mm'], ENT_QUOTES, 'UTF-8') . ' mm' : '—'; ?></div></div>
                            <div class="pneu-ficha-kpi"><div class="lbl">Veículo</div><div class="val"><?php echo htmlspecialchars((string) ($ficha['veiculo_placa'] ?? 'Estoque'), ENT_QUOTES, 'UTF-8'); ?></div></div>
                            <div class="pneu-ficha-kpi"><div class="lbl">KM rodado</div><div class="val"><?php echo $km_rodado > 0 ? number_format($km_rodado, 0, '', '.') : '—'; ?></div></div>
                            <div class="pneu-ficha-kpi"><div class="lbl">Custo/km</div><div class="val"><?php echo isset($ck['custo_km']) && $ck['custo_km'] !== null ? 'R$ ' . number_format((float) $ck['custo_km'], 4, ',', '.') : '—'; ?></div></div>
                            <div class="pneu-ficha-kpi"><div class="lbl">Recapagens</div><div class="val"><?php echo (int) ($p['numero_recapagens'] ?? 0); ?></div></div>
                            <div class="pneu-ficha-kpi"><div class="lbl">Valor compra</div><div class="val"><?php echo !empty($p['valor_compra']) ? 'R$ ' . number_format((float) $p['valor_compra'], 2, ',', '.') : '—'; ?></div></div>
                            <div class="pneu-ficha-kpi"><div class="lbl">Custo total</div><div class="val"><?php echo !empty($ck['custo_total']) ? 'R$ ' . number_format((float) $ck['custo_total'], 2, ',', '.') : '—'; ?></div></div>
                        </div>
                        <?php if (!empty($prev['dias_restantes']) || !empty($prev['km_restantes'])): ?>
                        <p class="pneu-ficha-meta">
                            <strong>Previsão de troca (sulco):</strong>
                            ~<?php echo (int) ($prev['dias_restantes'] ?? 0); ?> dias /
                            <?php echo $prev['km_restantes'] !== null ? number_format((int) $prev['km_restantes'], 0, '', '.') . ' km' : '?'; ?>
                        </p>
                        <?php endif; ?>
                        <?php if ($tipo_rodado !== '' && $tipo_rodado !== 'qualquer'): ?>
                        <p class="pneu-ficha-meta"><strong>Posição ideal:</strong> <?php echo htmlspecialchars($tipo_rodado_labels[$tipo_rodado] ?? $tipo_rodado, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($car): ?>
                <div class="pneu-ficha-panel">
                    <div class="pneu-ficha-panel__head"><i class="fas fa-ring" aria-hidden="true"></i> Carcaça</div>
                    <div class="pneu-ficha-panel__body">
                        <div class="pneu-ficha-grid">
                            <div class="pneu-ficha-kpi"><div class="lbl">Código</div><div class="val"><?php echo htmlspecialchars((string) ($car['codigo'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div></div>
                            <div class="pneu-ficha-kpi"><div class="lbl">Recapagens</div><div class="val"><?php echo (int) ($car['recapagens'] ?? 0); ?></div></div>
                            <div class="pneu-ficha-kpi"><div class="lbl">KM acumulados</div><div class="val"><?php echo number_format((int) ($car['km_acumulados'] ?? 0), 0, '', '.'); ?></div></div>
                            <div class="pneu-ficha-kpi"><div class="lbl">Investido</div><div class="val">R$ <?php echo number_format((float) ($car['valor_investido'] ?? 0), 2, ',', '.'); ?></div></div>
                            <div class="pneu-ficha-kpi"><div class="lbl">Status carcaça</div><div class="val"><?php echo htmlspecialchars((string) ($car['status_carcaca'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div></div>
                            <?php if ($dot): ?>
                            <div class="pneu-ficha-kpi"><div class="lbl">DOT</div><div class="val <?php echo htmlspecialchars(sf_pneu_dot_css_class($dot), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(sf_pneu_dot_label($dot), ENT_QUOTES, 'UTF-8'); ?></div></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="pneu-ficha-panel">
                    <div class="pneu-ficha-panel__head"><i class="fas fa-tools" aria-hidden="true"></i> Ações rápidas</div>
                    <div class="pneu-ficha-panel__body">
                        <div class="pf-actions">
                            <button type="button" class="fornc-btn fornc-btn--accent" id="btnMedirSulco"><i class="fas fa-ruler" aria-hidden="true"></i> Medir sulco</button>
                            <button type="button" class="fornc-btn fornc-btn--muted" id="btnSyncKm"><i class="fas fa-tachometer-alt" aria-hidden="true"></i> Sync KM veículo</button>
                            <button type="button" class="fornc-btn fornc-btn--muted" id="btnRecapagem"><i class="fas fa-redo" aria-hidden="true"></i> Registrar recapagem</button>
                            <button type="button" class="fornc-btn fornc-btn--primary" id="btnGerarQr"><i class="fas fa-qrcode" aria-hidden="true"></i> Ver QR Code</button>
                        </div>
                        <div id="pneuQrArea" class="pneu-qr-wrap" style="display:none" aria-live="polite"></div>
                        <p class="pneu-ficha-meta" style="margin-top:0.75rem;margin-bottom:0">
                            <strong>Link da ficha:</strong>
                            <a href="<?php echo htmlspecialchars($ficha_url, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ficha_url, ENT_QUOTES, 'UTF-8'); ?></a>
                        </p>
                    </div>
                </div>

                <div id="pneuMercadoHost"></div>

                <div class="pneu-ficha-panel">
                    <div class="pneu-ficha-panel__head"><i class="fas fa-ruler-combined" aria-hidden="true"></i> Medições de sulco (TWI)</div>
                    <div class="pneu-ficha-panel__body" id="sulcoChart"></div>
                </div>

                <div class="pneu-ficha-panel">
                    <div class="pneu-ficha-panel__head"><i class="fas fa-history" aria-hidden="true"></i> Histórico visual</div>
                    <div class="pneu-ficha-panel__body" id="timelineVis"></div>
                </div>

                <script src="<?php echo htmlspecialchars(sf_app_url('js/pneus_timeline.js')); ?>"></script>
                <script src="<?php echo htmlspecialchars(sf_app_url('js/pneu-ficha-page.js?v=1.0')); ?>"></script>
                <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (window.PneuFichaPage) {
                        PneuFichaPage.init({
                            pneuId: <?php echo (int) $pneu_id; ?>,
                            showQr: <?php echo $token !== '' ? 'false' : 'true'; ?>,
                            ficha: <?php echo json_encode($ficha, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>
                        });
                    }
                });
                </script>
            <?php endif; ?>
        </div>
        <?php include '../includes/footer.php'; ?>
    </div>
</div>
<script src="<?php echo htmlspecialchars(sf_app_url('js/theme.js')); ?>"></script>
<script src="<?php echo htmlspecialchars(sf_app_url('js/sidebar.js')); ?>"></script>
</body>
</html>
