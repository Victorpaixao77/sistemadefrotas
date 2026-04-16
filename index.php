<?php
// Include configuration and functions first
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Configure session before starting it
configure_session();

// Start the session
session_start();

// Check if user is logged in and has empresa_id
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    // Clear session and redirect to login
    session_unset();
    session_destroy();
    header("location: login.php");
    exit;
}

$empresa_id = $_SESSION['empresa_id'];

// Verify if empresa is still active
try {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT status FROM empresa_clientes WHERE id = :empresa_id AND status = 'ativo'");
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0 || $stmt->fetch()['status'] !== 'ativo') {
        session_unset();
        session_destroy();
        header("location: login.php?error=empresa_inativa");
        exit;
    }
} catch(PDOException $e) {
    // Log error but don't show to user
    error_log("Erro ao verificar status da empresa: " . $e->getMessage());
}

// Get company data
$empresa = getCompanyData();

// Set page title
$page_title = "Dashboard";

// Verificar permissões do usuário
require_once 'includes/permissions.php';
$can_view_financial_data = can_view_financial_data();
$can_access_fiscal = function_exists('can_access_fiscal_system') && can_access_fiscal_system();
$can_access_pneus = function_exists('can_access_tire_management') && can_access_tire_management();

require_once __DIR__ . '/includes/dashboard_home_metrics.php';
require_once __DIR__ . '/includes/sf_api_base.php';
$home_period = dashboard_home_normalize_period($_GET['period'] ?? 'year');
$dash_home = dashboard_home_load_all($conn, (int) $empresa_id, $home_period, $can_view_financial_data);
extract($dash_home, EXTR_OVERWRITE);

$dashboard_export_rows = [
    ['Indicador', 'Valor'],
    ['Período', $home_period_label],
    ['Veículos (cadastro)', (string) $total_veiculos],
    ['Motoristas (cadastro)', (string) $total_motoristas],
    ['Rotas concluídas', (string) $total_rotas_concluidas],
    ['Rotas (total no período)', (string) $total_rotas],
    ['Abastecimentos (qtd)', (string) $total_abastecimentos],
    ['Abastecimentos (R$)', number_format($valor_total_abastecimentos, 2, ',', '.')],
    ['Despesas viagem (R$)', number_format($total_desp_viagem, 2, ',', '.')],
    ['Despesas fixas pagas (R$)', number_format($total_desp_fixas, 2, ',', '.')],
    ['Contas pagas (R$)', number_format($total_contas_pagas, 2, ',', '.')],
    ['Manutenções veículos (R$)', number_format($total_manutencoes, 2, ',', '.')],
    ['Manutenções pneus (R$)', number_format($total_pneu_manutencao, 2, ',', '.')],
    ['Parcelas financ. pagas (R$)', number_format($total_parcelas_financiamento, 2, ',', '.')],
    ['Rotas pendentes', (string) $rotas_pendentes_count],
    ['Contas vencidas (qtd)', (string) $contas_vencidas_count],
    ['Contas vencidas (R$)', number_format($contas_vencidas_valor, 2, ',', '.')],
];
if ($can_view_financial_data) {
    $dashboard_export_rows[] = ['Fretes aprovados (R$)', number_format($total_fretes, 2, ',', '.')];
    $dashboard_export_rows[] = ['Comissões (R$)', number_format($total_comissoes, 2, ',', '.')];
    $dashboard_export_rows[] = ['Lucro líquido (R$)', number_format($lucro_liquido, 2, ',', '.')];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Principal - <?php echo APP_NAME; ?></title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="stylesheet" href="css/fornc-modern-page.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="logo.png">
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Sortable.js for drag-and-drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
    <style>
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition-speed) ease;
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            background: var(--bg-primary);
        }
        
        .sidebar-collapsed .main-content {
            margin-left: var(--sidebar-collapsed-width);
            width: calc(100% - var(--sidebar-collapsed-width));
        }
        
        .dashboard-content {
            padding: 20px;
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }

        /* Dashboard principal — modo moderno (alinhado ao fornc-modern-page) */
        body.index-dashboard-modern .dashboard-content.fornc-page {
            padding-top: 8px;
        }
        body.index-dashboard-modern .index-alerts-modern {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem !important;
        }
        body.index-dashboard-modern .alerta-card--modern {
            flex: 1 1 240px;
            min-width: 200px;
            background: var(--card-bg, var(--bg-secondary)) !important;
            color: var(--text-primary) !important;
            border: 1px solid var(--border-color) !important;
            border-left: 4px solid var(--alert-accent, var(--accent-primary)) !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06) !important;
            padding: 0.75rem 1rem !important;
            font-weight: 500 !important;
        }
        body.index-dashboard-modern .alerta-card--modern i {
            color: var(--alert-accent, var(--accent-primary)) !important;
        }
        body.index-dashboard-modern .dashboard-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)) !important;
            gap: 0.65rem !important;
            max-width: 100% !important;
        }
        body.index-dashboard-modern .dashboard-card {
            background: var(--card-bg, var(--bg-secondary));
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
            overflow: hidden;
        }
        body.index-dashboard-modern .dashboard-card .card-header {
            padding: 0.55rem 0.7rem;
        }
        body.index-dashboard-modern .dashboard-card .card-body {
            padding: 0.65rem 0.75rem 0.85rem;
        }
        body.index-dashboard-modern .analytics-section .section-header h2 {
            font-size: 1rem;
            margin: 0 0 0.5rem 0;
        }
        body.index-dashboard-modern .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 0.65rem;
        }
        body.index-dashboard-modern .analytics-card {
            background: var(--card-bg, var(--bg-secondary));
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
        }
        body.index-dashboard-modern .analytics-card .card-header {
            padding: 0.55rem 0.7rem;
        }
        body.index-dashboard-modern .analytics-card .card-body {
            padding: 0.5rem 0.65rem 0.75rem;
            min-height: 220px;
        }
        body.index-dashboard-modern .card {
            background: var(--card-bg, var(--bg-secondary));
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
            margin-bottom: 1rem;
            overflow: hidden;
        }
        body.index-dashboard-modern .card .card-header {
            padding: 0.55rem 0.75rem;
        }
        body.index-dashboard-modern .card .card-body {
            padding: 0.65rem 0.85rem;
        }
        body.index-dashboard-modern .index-home-subbar {
            margin-bottom: 0.5rem;
        }
        body.index-dashboard-modern .index-home-toolbar.fornc-toolbar {
            display: block;
            padding-bottom: 0.45rem;
        }
        body.index-dashboard-modern .index-home-toolbar .index-home-toolbar-title {
            font-size: 0.58rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            font-weight: 700;
            margin-bottom: 0.35rem;
        }
        body.index-dashboard-modern .index-home-updated {
            margin: 0.35rem 0 0;
            font-size: 0.72rem;
            color: var(--text-muted);
        }
        body.index-dashboard-modern .index-home-kpi-strip.fornc-kpi-strip {
            grid-template-columns: repeat(auto-fit, minmax(128px, 1fr)) !important;
        }
        body.index-dashboard-modern .index-kpi-delta {
            display: block;
            font-size: 0.58rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-top: 0.15rem;
            line-height: 1.2;
        }
        body.index-dashboard-modern .index-proximas-rotas .fornc-table {
            font-size: 0.72rem;
        }
        body.index-dashboard-modern .index-proximas-rotas .fornc-table td {
            text-transform: none;
            letter-spacing: normal;
        }
        @media print {
            body.index-dashboard-modern .sidebar,
            body.index-dashboard-modern .sidebar-toggle,
            body.index-dashboard-modern .no-print,
            body.index-dashboard-modern .top-header,
            body.index-dashboard-modern footer {
                display: none !important;
            }
            body.index-dashboard-modern .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
            body.index-dashboard-modern .dashboard-content > *:not(#dashboardPrintArea) {
                display: none !important;
            }
            body.index-dashboard-modern #dashboardPrintArea {
                display: block !important;
            }
        }
        #dashboardPrintArea {
            display: none;
            padding: 1rem;
            font-size: 12px;
        }
        #dashboardPrintArea table {
            width: 100%;
            border-collapse: collapse;
        }
        #dashboardPrintArea th, #dashboardPrintArea td {
            border: 1px solid #ccc;
            padding: 6px 8px;
            text-align: left;
        }
        body.index-dashboard-modern .sortable-ghost {
            opacity: 0.45;
        }
        body.index-dashboard-modern .index-kpi-lucro {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.45);
        }
        body.index-dashboard-modern .index-kpi-lucro .card-header h3 {
            color: var(--accent-success, #059669);
            margin: 0;
            font-size: 0.95rem;
        }
        body.index-dashboard-modern .index-kpi-lucro__value {
            color: var(--accent-success, #059669);
            font-size: 1.65rem;
            font-weight: 700;
        }

        /* KPI Abastecimentos: qtd + valor na mesma altura (evita card “duplo”) */
        body.index-dashboard-modern .kpi-abast-dual {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr);
            gap: 0.5rem 0.75rem;
            align-items: start;
        }
        body.index-dashboard-modern .kpi-abast-dual .kpi-abast-item {
            min-width: 0;
        }
        body.index-dashboard-modern .kpi-abast-dual .kpi-abast-item:first-child {
            padding-right: 0.65rem;
            border-right: 1px solid var(--border-color, #e5e7eb);
        }
        body.index-dashboard-modern .kpi-abast-dual .metric-value {
            font-size: 1.35rem;
            line-height: 1.15;
        }
        body.index-dashboard-modern .kpi-abast-dual .kpi-abast-item--valor .metric-value {
            font-size: 1.15rem;
            font-weight: 700;
            word-break: break-word;
        }
        body.index-dashboard-modern .kpi-abast-dual .metric-subtitle {
            font-size: 0.68rem;
            margin-top: 0.15rem;
        }
        @media (max-width: 380px) {
            body.index-dashboard-modern .kpi-abast-dual {
                grid-template-columns: 1fr;
            }
            body.index-dashboard-modern .kpi-abast-dual .kpi-abast-item:first-child {
                padding-right: 0;
                border-right: none;
                padding-bottom: 0.5rem;
                border-bottom: 1px solid var(--border-color, #e5e7eb);
            }
        }
        body.index-dashboard-modern .fornc-kpi-cell .val .kpi-strip-sep {
            opacity: 0.45;
            font-weight: 600;
            margin: 0 0.15rem;
        }
    </style>
</head>
<body class="index-dashboard-modern">
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include __DIR__ . '/includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include 'includes/header.php'; ?>
            
            <!-- Dashboard Content -->
            <div class="dashboard-content fornc-page">
                <!-- Alertas Inteligentes -->
                <div class="alertas-inteligentes mb-4 index-alerts-modern">
                    <?php
                    // Buscar alertas reais do banco de dados
                    $alertas = [];
                    
                    try {
                        // 1. Manutenção urgente (veículos sem manutenção há mais de 90 dias)
                        $sql_manutencao = "SELECT v.placa, DATEDIFF(CURRENT_DATE, ultima_manutencao.data_manutencao) as dias_sem_manutencao
                                           FROM veiculos v 
                                           LEFT JOIN (
                                               SELECT veiculo_id, MAX(data_manutencao) as data_manutencao
                                               FROM manutencoes 
                                               WHERE empresa_id = :empresa_id
                                               GROUP BY veiculo_id
                                           ) ultima_manutencao ON v.id = ultima_manutencao.veiculo_id
                                           WHERE v.empresa_id = :empresa_id2 
                                           AND (ultima_manutencao.data_manutencao IS NULL OR DATEDIFF(CURRENT_DATE, ultima_manutencao.data_manutencao) > 90)
                                           LIMIT 1";
                        
                        $stmt = $conn->prepare($sql_manutencao);
                        $stmt->bindParam(':empresa_id', $empresa_id);
                        $stmt->bindParam(':empresa_id2', $empresa_id);
                        $stmt->execute();
                        $manutencao_urgente = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($manutencao_urgente) {
                            $alertas[] = [
                                'tipo' => 'Manutenção Urgente',
                                'mensagem' => "Veículo {$manutencao_urgente['placa']} precisa de manutenção preventiva há " . $manutencao_urgente['dias_sem_manutencao'] . " dias.",
                                'cor' => '#e74c3c'
                            ];
                        }
                        
                        // 2. Pneus antigos (baseado na idade do DOT - data de fabricação)
                        $sql_pneu_antigo = "SELECT p.id, p.marca, p.modelo, p.dot,
                                           DATEDIFF(CURRENT_DATE, STR_TO_DATE(p.dot, '%m/%y')) as dias_fabricacao
                                           FROM pneus p 
                                           WHERE p.empresa_id = :empresa_id 
                                           AND p.dot IS NOT NULL 
                                           AND p.dot != ''
                                           AND p.dot REGEXP '^[0-9]{2}/[0-9]{2}$'
                                           AND DATEDIFF(CURRENT_DATE, STR_TO_DATE(p.dot, '%m/%y')) > 1460
                                           AND p.status_id IN (SELECT id FROM status_pneus WHERE nome = 'em_uso')
                                           LIMIT 1";
                        
                        $stmt = $conn->prepare($sql_pneu_antigo);
                        $stmt->bindParam(':empresa_id', $empresa_id);
                        $stmt->execute();
                        $pneu_antigo = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($pneu_antigo) {
                            $alertas[] = [
                                'tipo' => 'Pneu Antigo',
                                'mensagem' => "Pneu {$pneu_antigo['marca']} {$pneu_antigo['modelo']} com DOT {$pneu_antigo['dot']} tem mais de 4 anos.",
                                'cor' => '#f39c12'
                            ];
                        }
                        
                        // 3. Despesa alta (combustível acima da média do mês)
                        $sql_despesa_alta = "SELECT AVG(a.valor_total) as media_mensal, 
                                           (SELECT SUM(valor_total) FROM abastecimentos 
                                            WHERE empresa_id = :empresa_id 
                                            AND MONTH(data_abastecimento) = MONTH(CURRENT_DATE)
                                            AND YEAR(data_abastecimento) = YEAR(CURRENT_DATE)) as total_mes
                                           FROM abastecimentos a 
                                           WHERE a.empresa_id = :empresa_id2 
                                           AND a.data_abastecimento >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)";
                        
                        $stmt = $conn->prepare($sql_despesa_alta);
                        $stmt->bindParam(':empresa_id', $empresa_id);
                        $stmt->bindParam(':empresa_id2', $empresa_id);
                        $stmt->execute();
                        $despesa_data = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($despesa_data && $despesa_data['total_mes'] > ($despesa_data['media_mensal'] * 1.2)) {
                            $alertas[] = [
                                'tipo' => 'Despesa Alta',
                                'mensagem' => "Despesa de combustível 20% acima da média dos últimos 3 meses.",
                                'cor' => '#2980b9'
                            ];
                        }
                        
                    } catch (Exception $e) {
                        error_log("Erro ao buscar alertas: " . $e->getMessage());
                    }

                    if (!empty($rotas_pendentes_count)) {
                        $alertas[] = [
                            'tipo' => 'Rotas pendentes',
                            'mensagem' => $rotas_pendentes_count . ' rota(s) aguardando análise. Acesse Rotas no menu.',
                            'cor' => '#f39c12',
                        ];
                    }
                    if (!empty($contas_vencidas_count)) {
                        $alertas[] = [
                            'tipo' => 'Contas em atraso',
                            'mensagem' => $contas_vencidas_count . ' conta(s) vencidas (R$ ' . number_format($contas_vencidas_valor, 2, ',', '.') . '). Veja Contas a pagar.',
                            'cor' => '#e74c3c',
                        ];
                    }
                    
                    // Se não houver alertas reais, mostrar alertas padrão
                    if (empty($alertas)) {
                        $alertas = [
                            ['tipo' => 'Sistema OK', 'mensagem' => 'Todos os sistemas funcionando normalmente.', 'cor' => '#27ae60'],
                        ];
                    }
                    ?>
                    
                    <?php foreach ($alertas as $alerta): ?>
                        <?php
                        $alert_accent = htmlspecialchars($alerta['cor'], ENT_QUOTES, 'UTF-8');
                        $icon_alert = ($alerta['cor'] == '#27ae60') ? 'check-circle' : 'exclamation-triangle';
                        ?>
                        <div class="alerta-card alerta-card--modern"
                             style="--alert-accent: <?php echo $alert_accent; ?>; display: flex; align-items: center; gap: 12px; border-radius: 8px;">
                            <i class="fas fa-<?php echo $icon_alert; ?>"></i>
                            <span><?= htmlspecialchars($alerta['tipo']) ?>: <?= htmlspecialchars($alerta['mensagem']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php $home_updated_at = date('d/m/Y \à\s H:i'); ?>
                <div class="index-home-subbar">
                    <div class="fornc-toolbar index-home-toolbar">
                        <div class="index-home-toolbar-title">Período: <?php echo htmlspecialchars($home_period_label, ENT_QUOTES, 'UTF-8'); ?></div>
                        <form method="get" action="" class="fornc-filters-inline" style="align-items: flex-end;">
                            <div class="fg">
                                <label for="dashPeriod">Consolidar indicadores</label>
                                <select name="period" id="dashPeriod" onchange="this.form.submit()" title="Filtra valores abaixo (exceto cadastros). Padrão: ano calendário atual.">
                                    <option value="year" <?php echo $home_period === 'year' ? 'selected' : ''; ?>>Ano atual (padrão)</option>
                                    <option value="month" <?php echo $home_period === 'month' ? 'selected' : ''; ?>>Mês atual</option>
                                    <option value="quarter" <?php echo $home_period === 'quarter' ? 'selected' : ''; ?>>Trimestre atual</option>
                                    <option value="all" <?php echo $home_period === 'all' ? 'selected' : ''; ?>>Todo o período</option>
                                </select>
                            </div>
                        </form>
                        <div class="fornc-btn-row no-print">
                            <button type="button" class="fornc-btn fornc-btn--muted" id="btnDashboardPrint" title="Imprimir resumo"><i class="fas fa-print"></i> Imprimir resumo</button>
                            <button type="button" class="fornc-btn fornc-btn--ghost" id="btnDashboardCsv" title="Exportar CSV"><i class="fas fa-file-csv"></i> Exportar CSV</button>
                        </div>
                    </div>
                    <div class="fornc-toolbar index-home-toolbar">
                        <div class="index-home-toolbar-title">Acesso rápido</div>
                        <div class="fornc-btn-row">
                            <a href="pages/routes.php" class="fornc-btn fornc-btn--ghost"><i class="fas fa-route"></i> Rotas</a>
                            <a href="pages/abastecimentos.php" class="fornc-btn fornc-btn--ghost"><i class="fas fa-gas-pump"></i> Abastecimento</a>
                            <a href="pages/manutencoes.php" class="fornc-btn fornc-btn--ghost"><i class="fas fa-tools"></i> Manutenções</a>
                            <a href="pages/vehicles.php" class="fornc-btn fornc-btn--ghost"><i class="fas fa-truck"></i> Veículos</a>
                            <a href="pages/motorists.php" class="fornc-btn fornc-btn--ghost"><i class="fas fa-user-tie"></i> Motoristas</a>
                            <a href="pages/multas.php" class="fornc-btn fornc-btn--ghost"><i class="fas fa-exclamation-triangle"></i> Multas</a>
                            <a href="pages/contas_pagar.php" class="fornc-btn fornc-btn--ghost"><i class="fas fa-file-invoice-dollar"></i> Contas a pagar</a>
                            <a href="pages/fornecedores.php" class="fornc-btn fornc-btn--ghost"><i class="fas fa-truck-loading"></i> Fornecedores</a>
                            <?php if ($can_access_pneus): ?>
                            <a href="pages/pneus.php" class="fornc-btn fornc-btn--ghost"><i class="fas fa-circle"></i> Pneus</a>
                            <?php endif; ?>
                            <?php if ($can_access_fiscal): ?>
                            <a href="fiscal/pages/nfe.php" class="fornc-btn fornc-btn--ghost"><i class="fas fa-receipt"></i> NF-e</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <p class="index-home-updated">Indicadores consolidados · Atualizado em <?php echo htmlspecialchars($home_updated_at, ENT_QUOTES, 'UTF-8'); ?><?php echo $home_compare_available ? ' · Comparação com período anterior' : ''; ?></p>
                </div>

                <div class="fornc-kpi-strip index-home-kpi-strip mb-4 dashboard-print-hidden" aria-label="Resumo rápido">
                    <div class="fornc-kpi-cell"><span class="lbl">Veículos</span><span class="val"><?php echo (int) $total_veiculos; ?></span></div>
                    <div class="fornc-kpi-cell"><span class="lbl">Motoristas</span><span class="val"><?php echo (int) $total_motoristas; ?></span></div>
                    <div class="fornc-kpi-cell">
                        <span class="lbl">Rotas (concl. / total)</span>
                        <span class="val"><?php echo (int) $total_rotas_concluidas; ?> / <?php echo (int) $total_rotas; ?></span>
                        <?php if ($home_compare_available && $delta_rotas_concl_pct !== null): ?>
                        <span class="index-kpi-delta"><?php echo htmlspecialchars(dashboard_home_format_delta($delta_rotas_concl_pct), ENT_QUOTES, 'UTF-8'); ?> (concluídas)</span>
                        <?php endif; ?>
                    </div>
                    <div class="fornc-kpi-cell">
                        <span class="lbl">Abastecimentos</span>
                        <span class="val"><?php echo (int) $total_abastecimentos; ?><span class="kpi-strip-sep">·</span>R$ <?php echo number_format($valor_total_abastecimentos, 0, ',', '.'); ?></span>
                    </div>
                    <?php if ($rotas_pendentes_count > 0): ?>
                    <div class="fornc-kpi-cell is-warn"><span class="lbl">Rotas pendentes</span><span class="val"><?php echo (int) $rotas_pendentes_count; ?></span></div>
                    <?php endif; ?>
                    <?php if ($contas_vencidas_count > 0): ?>
                    <div class="fornc-kpi-cell is-warn"><span class="lbl">Contas vencidas</span><span class="val"><?php echo (int) $contas_vencidas_count; ?></span></div>
                    <?php endif; ?>
                    <?php if (((int) ($gps_veiculos_sinal_recente ?? 0)) > 0 || ((int) ($gps_pontos_hoje ?? 0)) > 0): ?>
                    <div class="fornc-kpi-cell" title="Rastreamento GPS (últimos 45 min / pontos hoje)">
                        <span class="lbl">GPS ativo</span>
                        <span class="val"><?php echo (int) ($gps_veiculos_sinal_recente ?? 0); ?> veíc.<span class="kpi-strip-sep">·</span><?php echo (int) ($gps_pontos_hoje ?? 0); ?> pts/hoje</span>
                    </div>
                    <?php endif; ?>
                    <?php if ($can_view_financial_data): ?>
                    <div class="fornc-kpi-cell">
                        <span class="lbl">Fretes (aprov.)</span>
                        <span class="val">R$ <?php echo number_format($total_fretes, 0, ',', '.'); ?></span>
                        <?php if ($home_compare_available && $delta_fretes_pct !== null): ?>
                        <span class="index-kpi-delta"><?php echo htmlspecialchars(dashboard_home_format_delta($delta_fretes_pct), ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="fornc-kpi-cell<?php echo $lucro_liquido >= 0 ? ' is-ok' : ' is-warn'; ?>">
                        <span class="lbl">Lucro líquido</span>
                        <span class="val">R$ <?php echo number_format($lucro_liquido, 0, ',', '.'); ?></span>
                        <?php if ($home_compare_available && $delta_lucro_pct !== null): ?>
                        <span class="index-kpi-delta"><?php echo htmlspecialchars(dashboard_home_format_delta($delta_lucro_pct), ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="card index-proximas-rotas mb-4 dashboard-print-hidden">
                    <div class="card-header"><h2>Próximas rotas (7 dias)</h2></div>
                    <div class="card-body" style="padding:0;">
                        <?php if (empty($proximas_rotas)): ?>
                        <p style="padding:0.75rem 1rem;margin:0;font-size:0.85rem;color:var(--text-secondary);">Nenhuma rota prevista neste intervalo.</p>
                        <?php else: ?>
                        <div class="fornc-table-wrap">
                            <table class="fornc-table">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Motorista</th>
                                        <th>Veículo</th>
                                        <th>Trajeto</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($proximas_rotas as $pr): ?>
                                    <?php
                                    $dref = !empty($pr['data_saida']) ? $pr['data_saida'] : $pr['data_rota'];
                                    $dshow = $dref ? date('d/m/Y', strtotime($dref)) : '—';
                                    $orig = $pr['cidade_origem'] ? $pr['cidade_origem'] . '/' . $pr['estado_origem'] : $pr['estado_origem'];
                                    $dst = $pr['cidade_destino'] ? $pr['cidade_destino'] . '/' . $pr['estado_destino'] : $pr['estado_destino'];
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dshow, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($pr['motorista'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($pr['placa'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($orig . ' → ' . $dst, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($pr['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Dashboard KPIs -->
                <div class="dashboard-grid mb-4 dashboard-print-hidden" id="dashboardGrid">
                    <div class="dashboard-card" data-card-id="kpi-veiculos">
                        <div class="card-header">
                            <h3>Total de Veículos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $total_veiculos; ?></span>
                                <span class="metric-subtitle">Veículos cadastrados</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card" data-card-id="kpi-motoristas">
                        <div class="card-header">
                            <h3>Motoristas/Colaboradores</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $total_motoristas; ?></span>
                                <span class="metric-subtitle">Motoristas ativos</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card" data-card-id="kpi-rotas">
                        <div class="card-header">
                            <h3>Rotas Realizadas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $total_rotas_concluidas; ?></span>
                                <span class="metric-subtitle">De <?php echo $total_rotas; ?> rotas<?php echo $home_period !== 'all' ? ' no período' : ''; ?></span>
                            </div>
                            <?php if ($home_compare_available && $delta_rotas_concl_pct !== null): ?>
                            <p class="index-kpi-delta" style="margin:0.5rem 0 0;"><?php echo htmlspecialchars(dashboard_home_format_delta($delta_rotas_concl_pct), ENT_QUOTES, 'UTF-8'); ?> (concluídas)</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="dashboard-card" data-card-id="kpi-abastecimentos">
                        <div class="card-header">
                            <h3>Abastecimentos</h3>
                        </div>
                        <div class="card-body">
                            <div class="kpi-abast-dual">
                                <div class="metric kpi-abast-item">
                                    <span class="metric-value"><?php echo (int) $total_abastecimentos; ?></span>
                                    <span class="metric-subtitle">Registros no período</span>
                                </div>
                                <div class="metric kpi-abast-item kpi-abast-item--valor">
                                    <span class="metric-value">R$ <?php echo number_format($valor_total_abastecimentos, 2, ',', '.'); ?></span>
                                    <span class="metric-subtitle">Total gasto</span>
                                </div>
                            </div>
                            <?php if ($home_compare_available && $delta_abast_valor_pct !== null): ?>
                            <p class="index-kpi-delta" style="margin:0.45rem 0 0;"><?php echo htmlspecialchars(dashboard_home_format_delta($delta_abast_valor_pct), ENT_QUOTES, 'UTF-8'); ?> (valor)</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="dashboard-card" data-card-id="kpi-desp-viagem">
                        <div class="card-header">
                            <h3>Despesas de Viagem</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_desp_viagem, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Custos variáveis</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card" data-card-id="kpi-desp-fixas">
                        <div class="card-header">
                            <h3>Despesas Fixas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_desp_fixas, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Pagas no período</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card" data-card-id="kpi-contas">
                        <div class="card-header">
                            <h3>Contas Pagas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_contas_pagas, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Outros pagamentos</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card" data-card-id="kpi-manut-veiculos">
                        <div class="card-header">
                            <h3>Manutenções de Veículos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_manutencoes, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Veículos</span>
                            </div>
                        </div>
                    </div>
                    <?php if ($can_access_pneus): ?>
                    <div class="dashboard-card" data-card-id="kpi-manut-pneus">
                        <div class="card-header">
                            <h3>Manutenções de Pneus</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_pneu_manutencao, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Pneus</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="dashboard-card" data-card-id="kpi-financiamento">
                        <div class="card-header">
                            <h3>Parcelas de Financiamento</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_parcelas_financiamento, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Pagas no período</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card" data-card-id="kpi-fretes">
                        <div class="card-header">
                            <h3><?php echo $can_view_financial_data ? 'Faturamento (Fretes)' : 'Rotas no período'; ?></h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <?php if ($can_view_financial_data): ?>
                                    <span class="metric-value">R$ <?php echo number_format($total_fretes, 2, ',', '.'); ?></span>
                                    <span class="metric-subtitle">Receita bruta</span>
                                    <?php if ($home_compare_available && $delta_fretes_pct !== null): ?>
                                    <p class="index-kpi-delta" style="margin:0.5rem 0 0;"><?php echo htmlspecialchars(dashboard_home_format_delta($delta_fretes_pct), ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="metric-value"><?php echo number_format($total_rotas, 0, ',', '.'); ?></span>
                                    <span class="metric-subtitle">Total de rotas (período selecionado)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($can_view_financial_data): ?>
                    <div class="dashboard-card" data-card-id="kpi-comissoes">
                        <div class="card-header">
                            <h3>Comissões</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_comissoes, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Pagas a motoristas</span>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="dashboard-card" data-card-id="kpi-comissoes-pct">
                        <div class="card-header">
                            <h3>Comissões</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <?php
                                $percentual_comissao = $total_fretes > 0 ? ($total_comissoes / $total_fretes) * 100 : 0;
                                ?>
                                <span class="metric-value"><?php echo number_format($percentual_comissao, 1, ',', '.'); ?>%</span>
                                <span class="metric-subtitle">Percentual médio (rotas aprovadas no período)</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($can_view_financial_data): ?>
                    <div class="dashboard-card index-kpi-lucro" data-card-id="kpi-lucro">
                        <div class="card-header">
                            <h3>Lucro líquido geral</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value index-kpi-lucro__value">R$ <?php echo number_format($lucro_liquido, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Lucro no período selecionado</span>
                            </div>
                            <?php if ($home_compare_available && $delta_lucro_pct !== null): ?>
                            <p class="index-kpi-delta" style="margin:0.5rem 0 0;"><?php echo htmlspecialchars(dashboard_home_format_delta($delta_lucro_pct), ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Gráfico Financeiro -->
                <?php if ($can_view_financial_data): ?>
                <div class="analytics-section dashboard-print-hidden" id="dashboardAnalyticsSection">
                    <div class="section-header">
                        <h2>Análise Financeira</h2>
                        <p style="margin:0.25rem 0 0;font-size:0.78rem;color:var(--text-muted);font-weight:normal;">Gráficos consolidados por <strong>ano calendário atual</strong> (<?php echo (int) date('Y'); ?>), independente do filtro de período acima.</p>
                    </div>
                    <div class="analytics-grid">
                        <div class="analytics-card">
                    <div class="card-header">
                                <h3>Análise Financeira Geral</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="financialChart"></canvas>
                    </div>
                </div>

                        <div class="analytics-card">
                    <div class="card-header">
                                <h3>Distribuição de Despesas (<?= date('Y') ?>)</h3>
                    </div>
                    <div class="card-body">
                                <canvas id="expensesDistributionChart"></canvas>
                    </div>
                </div>

                        <div class="analytics-card">
                    <div class="card-header">
                                <h3>Comissões Pagas (<?= date('Y') ?>)</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="commissionsChart"></canvas>
                    </div>
                </div>

                        <div class="analytics-card">
                    <div class="card-header">
                                <h3>Faturamento Líquido (<?= date('Y') ?>)</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="netRevenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Recent Activities -->
                <div class="card dashboard-print-hidden">
                    <div class="card-header">
                        <h2>Atividades Recentes</h2>
                    </div>
                    <div class="card-body">
                        <?php
                        // Buscar atividades reais do banco de dados
                        try {
                            $atividades = [];
                            
                            // Últimas rotas
                            $sql_rotas = "SELECT r.id, r.estado_origem, r.estado_destino, r.data_saida, m.nome as motorista, v.placa,
                                         co.nome as cidade_origem, cd.nome as cidade_destino
                                         FROM rotas r 
                                         JOIN motoristas m ON r.motorista_id = m.id 
                                         JOIN veiculos v ON r.veiculo_id = v.id 
                                         LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                                         LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                                         WHERE r.empresa_id = :empresa_id 
                                         ORDER BY r.data_saida DESC LIMIT 3";
                            $stmt = $conn->prepare($sql_rotas);
                            $stmt->bindParam(':empresa_id', $empresa_id);
                            $stmt->execute();
                            $rotas_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($rotas_recentes as $rota) {
                                $origem = $rota['cidade_origem'] ? $rota['cidade_origem'] . '/' . $rota['estado_origem'] : $rota['estado_origem'];
                                $destino = $rota['cidade_destino'] ? $rota['cidade_destino'] . '/' . $rota['estado_destino'] : $rota['estado_destino'];
                                $atividades[] = [
                                    'data' => date('d/m/Y', strtotime($rota['data_saida'])),
                                    'descricao' => "Rota: {$origem} → {$destino} ({$rota['motorista']})",
                                    'tipo' => 'rota',
                                    'cor' => '#3498db'
                                ];
                            }
                            
                            // Últimos abastecimentos
                            $sql_abastecimentos = "SELECT a.id, a.data_abastecimento, a.litros, a.valor_total, m.nome as motorista, v.placa 
                                                  FROM abastecimentos a 
                                                  JOIN motoristas m ON a.motorista_id = m.id 
                                                  JOIN veiculos v ON a.veiculo_id = v.id 
                                                  WHERE a.empresa_id = :empresa_id 
                                                  ORDER BY a.data_abastecimento DESC LIMIT 3";
                            $stmt = $conn->prepare($sql_abastecimentos);
                            $stmt->bindParam(':empresa_id', $empresa_id);
                            $stmt->execute();
                            $abastecimentos_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($abastecimentos_recentes as $abastecimento) {
                                $atividades[] = [
                                    'data' => date('d/m/Y', strtotime($abastecimento['data_abastecimento'])),
                                    'descricao' => "Abastecimento: {$abastecimento['placa']} - {$abastecimento['litros']}L (R$ " . number_format($abastecimento['valor_total'], 2, ',', '.') . ")",
                                    'tipo' => 'abastecimento',
                                    'cor' => '#e67e22'
                                ];
                            }
                            
                            // Últimas manutenções
                            $sql_manutencoes = "SELECT m.id, m.data_manutencao, m.descricao, m.valor, v.placa 
                                               FROM manutencoes m 
                                               JOIN veiculos v ON m.veiculo_id = v.id 
                                               WHERE m.empresa_id = :empresa_id 
                                               ORDER BY m.data_manutencao DESC LIMIT 3";
                            $stmt = $conn->prepare($sql_manutencoes);
                            $stmt->bindParam(':empresa_id', $empresa_id);
                            $stmt->execute();
                            $manutencoes_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($manutencoes_recentes as $manutencao) {
                                $atividades[] = [
                                    'data' => date('d/m/Y', strtotime($manutencao['data_manutencao'])),
                                    'descricao' => "Manutenção: {$manutencao['placa']} - " . substr($manutencao['descricao'], 0, 50) . "... (R$ " . number_format($manutencao['valor'], 2, ',', '.') . ")",
                                    'tipo' => 'manutencao',
                                    'cor' => '#f39c12'
                                ];
                            }
                            
                            // Ordenar por data (mais recentes primeiro)
                            usort($atividades, function($a, $b) {
                                return strtotime($b['data']) - strtotime($a['data']);
                            });
                            
                            // Pegar apenas as 4 mais recentes
                            $atividades = array_slice($atividades, 0, 4);
                            
                        } catch (Exception $e) {
                            error_log("Erro ao buscar atividades recentes: " . $e->getMessage());
                            $atividades = [];
                        }
                        ?>
                        
                        <?php if (empty($atividades)): ?>
                            <p style="color: #666; font-style: italic;">Nenhuma atividade recente encontrada.</p>
                        <?php else: ?>
                            <ul style="list-style: none; padding: 0; margin: 0;">
                                <?php foreach ($atividades as $atividade): ?>
                                    <li style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; margin-bottom: 12px; padding: 16px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; transition: all 0.2s ease;">
                                        <span style="font-size: 1.3rem; color: <?= $atividade['cor'] ?>; min-width: 24px;">
                                            <i class="fas fa-<?= $atividade['tipo']=='rota'?'road':($atividade['tipo']=='abastecimento'?'gas-pump':($atividade['tipo']=='manutencao'?'wrench':'money-bill')) ?>"></i>
                                        </span>
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600; color: #2c3e50; margin-bottom: 4px;"><?= $atividade['descricao'] ?></div>
                                            <div style="font-size: 0.9rem; color: #7f8c8d;"><?= $atividade['data'] ?></div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Insights Inteligentes -->
                    <div class="card dashboard-print-hidden">
                        <div class="card-header">
                        <h2>Insights Inteligentes</h2>
                        </div>
                        <div class="card-body">
                        <?php
                        // Buscar insights reais do banco de dados
                        $insights = [];
                        
                        try {
                            // 1. Análise de custos de manutenção por fornecedor
                            $sql_custos_fornecedor = "SELECT f.nome as fornecedor, AVG(m.valor) as custo_medio, COUNT(*) as total_manutencoes
                                                     FROM manutencoes m 
                                                     JOIN fornecedores f ON m.fornecedor_id = f.id 
                                                     WHERE m.empresa_id = :empresa_id 
                                                     AND m.data_manutencao >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
                                                     GROUP BY f.id 
                                                     HAVING total_manutencoes >= 3
                                                     ORDER BY custo_medio DESC 
                                                     LIMIT 1";
                            
                            $stmt = $conn->prepare($sql_custos_fornecedor);
                            $stmt->bindParam(':empresa_id', $empresa_id);
                            $stmt->execute();
                            $fornecedor_caro = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($fornecedor_caro) {
                                echo "Reduza custos de manutenção trocando fornecedor {$fornecedor_caro['fornecedor']} (média R$ " . number_format($fornecedor_caro['custo_medio'], 2, ',', '.') . ").";
                            } else {
                                // Se não há dados suficientes, buscar fornecedor com maior custo médio
                                $sql_fornecedor_alt = "SELECT f.nome as fornecedor, AVG(m.valor) as custo_medio
                                                      FROM manutencoes m 
                                                      JOIN fornecedores f ON m.fornecedor_id = f.id 
                                                      WHERE m.empresa_id = :empresa_id 
                                                      AND m.data_manutencao >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
                                                      GROUP BY f.id 
                                                      ORDER BY custo_medio DESC 
                                                      LIMIT 1";
                                $stmt = $conn->prepare($sql_fornecedor_alt);
                                $stmt->bindParam(':empresa_id', $empresa_id);
                                $stmt->execute();
                                $fornecedor_alt = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($fornecedor_alt) {
                                    echo "Reduza custos de manutenção trocando fornecedor {$fornecedor_alt['fornecedor']} (média R$ " . number_format($fornecedor_alt['custo_medio'], 2, ',', '.') . ").";
                                } else {
                                    echo "Analise os custos de manutenção por fornecedor para otimizar gastos.";
                                }
                            }
                            
                        } catch (Exception $e) {
                            echo "Analise os custos de manutenção por fornecedor para otimizar gastos.";
                        }
                        ?>
                        
                        <ul style="list-style: disc inside; color: #2d3436; font-size: 1.1rem;">
                            <li><?php
                                try {
                                    // 2. Motorista com melhor desempenho de consumo
                                    $sql_motorista_consumo = "SELECT m.nome as motorista, 
                                                             AVG(a.litros / NULLIF(r.distancia_km, 0)) as consumo_medio,
                                                             COUNT(DISTINCT r.id) as total_rotas
                                                             FROM motoristas m 
                                                             JOIN rotas r ON m.id = r.motorista_id 
                                                             JOIN abastecimentos a ON r.id = a.rota_id 
                                                             WHERE m.empresa_id = :empresa_id 
                                                             AND r.data_saida >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)
                                                             AND r.distancia_km > 0
                                                             AND a.litros > 0
                                                             GROUP BY m.id 
                                                             HAVING total_rotas >= 3
                                                             ORDER BY consumo_medio ASC 
                                                             LIMIT 1";
                                    
                                    $stmt = $conn->prepare($sql_motorista_consumo);
                                    $stmt->bindParam(':empresa_id', $empresa_id);
                                    $stmt->execute();
                                    $melhor_motorista = $stmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($melhor_motorista) {
                                        $consumo_l_100km = $melhor_motorista['consumo_medio'] * 100;
                                        echo "Motorista {$melhor_motorista['motorista']} tem melhor desempenho de consumo (" . number_format($consumo_l_100km, 1) . "L/100km).";
                                    } else {
                                        // Se não há dados suficientes, buscar motorista com mais rotas
                                        $sql_motorista_alt = "SELECT m.nome as motorista, COUNT(r.id) as total_rotas
                                                             FROM motoristas m 
                                                             LEFT JOIN rotas r ON m.id = r.motorista_id 
                                                             WHERE m.empresa_id = :empresa_id 
                                                             AND r.data_saida >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
                                                             GROUP BY m.id 
                                                             HAVING total_rotas > 0
                                                             ORDER BY total_rotas DESC 
                                                             LIMIT 1";
                                        $stmt = $conn->prepare($sql_motorista_alt);
                                        $stmt->bindParam(':empresa_id', $empresa_id);
                                        $stmt->execute();
                                        $motorista_alt = $stmt->fetch(PDO::FETCH_ASSOC);
                                        
                                        if ($motorista_alt) {
                                            echo "Motorista {$motorista_alt['motorista']} realizou {$motorista_alt['total_rotas']} rotas nos últimos 6 meses.";
                                        } else {
                                            echo "Monitore o desempenho de consumo dos motoristas para otimizar gastos com combustível.";
                                        }
                                    }
                                    
                                } catch (Exception $e) {
                                    echo "Monitore o desempenho de consumo dos motoristas para otimizar gastos com combustível.";
                                }
                            ?></li>
                            <li><?php
                                try {
                                    // 3. Análise de lucratividade por horário de rota
                                    $sql_lucratividade_horario = "SELECT 
                                                                 CASE 
                                                                     WHEN HOUR(r.data_saida) BETWEEN 22 AND 6 THEN 'Noturno'
                                                                     WHEN HOUR(r.data_saida) BETWEEN 6 AND 18 THEN 'Diurno'
                                                                     ELSE 'Vespertino'
                                                                 END as horario,
                                                                 AVG(r.frete - r.comissao) as lucro_medio,
                                                                 COUNT(*) as total_rotas
                                                                 FROM rotas r 
                                                                 WHERE r.empresa_id = :empresa_id 
                                                                 AND r.data_saida >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
                                                                 GROUP BY horario 
                                                                 HAVING total_rotas >= 3
                                                                 ORDER BY lucro_medio DESC 
                                                                 LIMIT 1";
                                    
                                    $stmt = $conn->prepare($sql_lucratividade_horario);
                                    $stmt->bindParam(':empresa_id', $empresa_id);
                                    $stmt->execute();
                                    $melhor_horario = $stmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($melhor_horario) {
                                        echo "Rotas {$melhor_horario['horario']} apresentam maior lucratividade média (R$ " . number_format($melhor_horario['lucro_medio'], 2, ',', '.') . ").";
                                    } else {
                                        echo "Analise a lucratividade por horário de rotas para otimizar operações.";
                                    }
                                    
                                } catch (Exception $e) {
                                    echo "Analise a lucratividade por horário de rotas para otimizar operações.";
                                }
                            ?></li>
                        </ul>
                    </div>
                </div>

                <div id="dashboardPrintArea">
                    <h1 style="font-size:16px;margin:0 0 8px;">Resumo do dashboard</h1>
                    <p style="margin:0 0 12px;font-size:11px;">
                        <?php
                        $emp_nome = '';
                        if (is_array($empresa)) {
                            $emp_nome = $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? '';
                        }
                        echo htmlspecialchars($emp_nome !== '' ? $emp_nome : APP_NAME, ENT_QUOTES, 'UTF-8');
                        ?>
                        · Período: <?php echo htmlspecialchars($home_period_label, ENT_QUOTES, 'UTF-8'); ?>
                        · <?php echo htmlspecialchars($home_updated_at, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <table>
                        <thead><tr><th>Indicador</th><th>Valor</th></tr></thead>
                        <tbody>
                        <?php foreach ($dashboard_export_rows as $er): ?>
                            <?php if (count($er) >= 2): ?>
                            <tr><td><?php echo htmlspecialchars($er[0], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($er[1], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <!-- JavaScript Files (sfApiUrl/sfAppUrl vêm de includes/header.php) -->
    <script src="js/dashboard.js"></script>
    <script src="js/theme.js"></script>
    <script src="js/sidebar.js"></script>
    <script>
        // Função para inicializar o gráfico de distribuição de despesas
        async function initExpensesDistributionChart() {
            try {
                // Destroy existing chart if it exists
                const existingChart = Chart.getChart('expensesDistributionChart');
                if (existingChart) {
                    existingChart.destroy();
                }

                const response = await fetch(sfApiUrl('expenses_distribution.php'));
                if (!response.ok) {
                    throw new Error('Erro ao carregar dados de distribuição de despesas');
                }
                
                const data = await response.json();
                
                // Criar o gráfico
                const ctx = document.getElementById('expensesDistributionChart').getContext('2d');
                new Chart(ctx, {
                    type: 'doughnut',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const value = context.raw;
                                        return `${context.label}: ${new Intl.NumberFormat('pt-BR', {
                                            style: 'currency',
                                            currency: 'BRL'
                                        }).format(value)}`;
                                    }
                                }
                            }
                        }
                    }
                });
                
            } catch (error) {
                console.error('Erro ao carregar gráfico de distribuição de despesas:', error);
            }
        }

        // Função para inicializar o gráfico de comissões
        async function initCommissionsChart() {
            try {
                // Destroy existing chart if it exists
                const existingChart = Chart.getChart('commissionsChart');
                if (existingChart) {
                    existingChart.destroy();
                }

                const response = await fetch(sfApiUrl('commissions_analytics.php'));
                if (!response.ok) {
                    throw new Error('Erro ao carregar dados de comissões');
                }
                
                const data = await response.json();
                
                // Criar o gráfico
                const ctx = document.getElementById('commissionsChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const value = context.raw;
                                        return `${context.dataset.label}: ${new Intl.NumberFormat('pt-BR', {
                                            style: 'currency',
                                            currency: 'BRL'
                                        }).format(value)}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return new Intl.NumberFormat('pt-BR', {
                                            style: 'currency',
                                            currency: 'BRL',
                                            maximumFractionDigits: 0
                                        }).format(value);
                                    }
                                }
                            }
                        }
                    }
                });
                
            } catch (error) {
                console.error('Erro ao carregar gráfico de comissões:', error);
            }
        }

        // Função para inicializar o gráfico de faturamento líquido
        async function initNetRevenueChart() {
            try {
                // Destroy existing chart if it exists
                const existingChart = Chart.getChart('netRevenueChart');
                if (existingChart) {
                    existingChart.destroy();
                }

                const response = await fetch(sfApiUrl('net_revenue_analytics.php'));
                if (!response.ok) {
                    throw new Error('Erro ao carregar dados de faturamento líquido');
                }
                
                const data = await response.json();
                
                // Criar o gráfico
                const ctx = document.getElementById('netRevenueChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const value = context.raw;
                                        const formattedValue = new Intl.NumberFormat('pt-BR', {
                                            style: 'currency',
                                            currency: 'BRL'
                                        }).format(value);
                                        
                                        // Adicionar cor ao valor baseado se é positivo ou negativo
                                        const color = value >= 0 ? '#2ecc40' : '#e74c3c';
                                        return `${context.dataset.label}: <span style="color: ${color}">${formattedValue}</span>`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return new Intl.NumberFormat('pt-BR', {
                                            style: 'currency',
                                            currency: 'BRL',
                                            maximumFractionDigits: 0
                                        }).format(value);
                                    }
                                }
                            }
                        }
                    }
                });
                
            } catch (error) {
                console.error('Erro ao carregar gráfico de faturamento líquido:', error);
            }
        }

        // Função para inicializar o gráfico financeiro (Faturamento x Despesas)
        // Variáveis globais para controlar o gráfico financeiro
        let financialChart = null;
        let financialChartLoading = false;
        
        async function initFinancialChart() {
            // Evitar múltiplas inicializações simultâneas
            if (financialChartLoading) {
                return;
            }
            
            financialChartLoading = true;
            try {
                // Destroy existing chart if it exists
                if (financialChart) {
                    financialChart.destroy();
                    financialChart = null;
                }
                
                // Double check with Chart.js registry
                const existingChart = Chart.getChart('financialChart');
                if (existingChart) {
                    existingChart.destroy();
                }

                // Verificar se o canvas existe
                const canvas = document.getElementById('financialChart');
                if (!canvas) {
                    console.error('Canvas financialChart não encontrado');
                    return;
                }

                const response = await fetch(sfApiUrl('financial_analytics.php'));
                if (!response.ok) {
                    throw new Error('Erro ao carregar dados financeiros');
                }
                
                const data = await response.json();
                
                // Criar o gráfico
                const ctx = document.getElementById('financialChart').getContext('2d');
                financialChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.labels || ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                        datasets: [
                            {
                                label: 'Faturamento',
                                data: data.faturamento || [],
                                backgroundColor: 'rgba(46, 204, 64, 0.8)',
                                borderColor: 'rgba(46, 204, 64, 1)',
                                borderWidth: 2,
                                borderRadius: 4,
                                borderSkipped: false,
                            },
                            {
                                label: 'Despesas',
                                data: data.despesas || [],
                                backgroundColor: 'rgba(231, 76, 60, 0.8)',
                                borderColor: 'rgba(231, 76, 60, 1)',
                                borderWidth: 2,
                                borderRadius: 4,
                                borderSkipped: false,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20,
                                    font: {
                                        size: 12,
                                        weight: '500'
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: 'rgba(255, 255, 255, 0.1)',
                                borderWidth: 1,
                                cornerRadius: 6,
                                displayColors: true,
                                callbacks: {
                                    label: function(context) {
                                        const value = context.raw;
                                        const formattedValue = new Intl.NumberFormat('pt-BR', {
                                            style: 'currency',
                                            currency: 'BRL'
                                        }).format(value);
                                        return `${context.dataset.label}: ${formattedValue}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)',
                                    lineWidth: 1
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    },
                                    callback: function(value) {
                                        return new Intl.NumberFormat('pt-BR', {
                                            style: 'currency',
                                            currency: 'BRL',
                                            maximumFractionDigits: 0
                                        }).format(value);
                                    }
                                }
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        }
                    }
                });
                
            } catch (error) {
                console.error('Erro ao carregar gráfico financeiro:', error);
                
                // Criar gráfico com dados padrão em caso de erro
                const ctx = document.getElementById('financialChart').getContext('2d');
                financialChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                        datasets: [
                            {
                                label: 'Faturamento',
                                data: [<?php echo $total_fretes; ?>, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                                backgroundColor: 'rgba(46, 204, 64, 0.8)',
                                borderColor: 'rgba(46, 204, 64, 1)',
                                borderWidth: 2,
                                borderRadius: 4,
                            },
                            {
                                label: 'Despesas',
                                data: [<?php echo ($total_desp_viagem + $total_desp_fixas + $total_contas_pagas + $total_manutencoes + $total_pneu_manutencao + $total_parcelas_financiamento); ?>, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                                backgroundColor: 'rgba(231, 76, 60, 0.8)',
                                borderColor: 'rgba(231, 76, 60, 1)',
                                borderWidth: 2,
                                borderRadius: 4,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            } finally {
                financialChartLoading = false;
            }
        }

        // Gráficos: carregar quando a seção estiver próxima da viewport (menos trabalho no primeiro paint)
        document.addEventListener('DOMContentLoaded', function() {
            var analyticsEl = document.getElementById('dashboardAnalyticsSection');
            var chartsDone = false;
            function runCharts() {
                if (chartsDone) return;
                chartsDone = true;
                initFinancialChart();
                initExpensesDistributionChart();
                initCommissionsChart();
                initNetRevenueChart();
            }
            if (!analyticsEl) {
                runCharts();
                return;
            }
            if ('IntersectionObserver' in window) {
                var io = new IntersectionObserver(function (entries) {
                    entries.forEach(function (en) {
                        if (en.isIntersecting) {
                            runCharts();
                            io.disconnect();
                        }
                    });
                }, { root: null, rootMargin: '180px 0px', threshold: 0 });
                io.observe(analyticsEl);
            } else {
                requestAnimationFrame(runCharts);
            }
        });
    </script>
    <script>
    (function () {
        var rows = <?php echo json_encode($dashboard_export_rows, JSON_UNESCAPED_UNICODE); ?>;
        document.getElementById('btnDashboardPrint')?.addEventListener('click', function () { window.print(); });
        document.getElementById('btnDashboardCsv')?.addEventListener('click', function () {
            var lines = rows.map(function (r) {
                return r.map(function (c) { return '"' + String(c).replace(/"/g, '""') + '"'; }).join(';');
            });
            var blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'dashboard-resumo-<?php echo date('Y-m-d'); ?>.csv';
            a.click();
            URL.revokeObjectURL(a.href);
        });
    })();
    </script>

    <?php include 'includes/scroll_to_top.php'; ?>
</body>
</html>
