<?php
// Include configuration and functions first
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once __DIR__ . '/includes/sf_page_help.php';

// Configure session before starting it
configure_session();

// Start the session
session_start();

require_authentication();

// Check empresa context
if (!isset($_SESSION['empresa_id'])) {
    header('location: selecionar_empresa.php');
    exit;
}

$empresa_id = $_SESSION['empresa_id'];

// Verify if empresa is still active
try {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT status FROM empresa_clientes WHERE id = :empresa_id LIMIT 1");
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->execute();
    $empresa_row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$empresa_row || ($empresa_row['status'] ?? '') !== 'ativo') {
        session_unset();
        session_destroy();
        header('location: login.php?error=empresa_inativa');
        exit;
    }
} catch (PDOException $e) {
    error_log('Erro ao verificar status da empresa: ' . $e->getMessage());
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
if (!empty($_GET['refresh'])) {
    dashboard_home_cache_clear_empresa((int) $empresa_id);
}
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
    ['Rotas pendentes', (string) $rotas_pendentes_count],
];
if ($can_view_financial_data) {
    $dashboard_export_rows[] = ['Abastecimentos (R$)', number_format($valor_total_abastecimentos, 2, ',', '.')];
    $dashboard_export_rows[] = ['Despesas viagem (R$)', number_format($total_desp_viagem, 2, ',', '.')];
    $dashboard_export_rows[] = ['Despesas fixas pagas (R$)', number_format($total_desp_fixas, 2, ',', '.')];
    $dashboard_export_rows[] = ['Contas pagas (R$)', number_format($total_contas_pagas, 2, ',', '.')];
    $dashboard_export_rows[] = ['Manutenções veículos (R$)', number_format($total_manutencoes, 2, ',', '.')];
    $dashboard_export_rows[] = ['Manutenções pneus (R$)', number_format($total_pneu_manutencao, 2, ',', '.')];
    $dashboard_export_rows[] = ['Parcelas financ. pagas (R$)', number_format($total_parcelas_financiamento, 2, ',', '.')];
    $dashboard_export_rows[] = ['Contas vencidas (qtd)', (string) $contas_vencidas_count];
    $dashboard_export_rows[] = ['Contas vencidas (R$)', number_format($contas_vencidas_valor, 2, ',', '.')];
    $dashboard_export_rows[] = ['Fretes aprovados (R$)', number_format($total_fretes, 2, ',', '.')];
    $dashboard_export_rows[] = ['Comissões (R$)', number_format($total_comissoes, 2, ',', '.')];
    $dashboard_export_rows[] = ['Lucro líquido (R$)', number_format($lucro_liquido, 2, ',', '.')];
} else {
    $dashboard_export_rows[] = ['Contas vencidas (qtd)', (string) $contas_vencidas_count];
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
    <link rel="stylesheet" href="css/dashboard.css?v=1.5">
    <link rel="stylesheet" href="css/dashboard-home-v2.css?v=1.1">
    <?php sf_page_help_css(''); ?>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="logo.png">
</head>
<body class="index-dashboard-modern" data-dashboard-home="1" data-dash-period="<?php echo htmlspecialchars($home_period, ENT_QUOTES, 'UTF-8'); ?>" data-notif-op="<?php echo (int) ($notif_nao_lidas_operacional ?? 0); ?>" data-notif-ia="<?php echo (int) ($notif_nao_lidas_ia ?? 0); ?>">
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include __DIR__ . '/includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include 'includes/header.php'; ?>
            
            <!-- Dashboard Content -->
            <div class="dashboard-content fornc-page">
                <?php
                $hora = (int) date('H');
                $saudacao = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');
                $dias_semana = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
                $meses_ano = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
                $data_br = $dias_semana[(int) date('w')] . ', ' . (int) date('d') . ' de ' . $meses_ano[(int) date('n')] . ' de ' . date('Y');
                $primeiro_nome = trim(explode(' ', (string) ($_SESSION['nome'] ?? ''))[0]);
                $nome_empresa = '';
                if (is_array($empresa)) {
                    $nome_empresa = (string) ($empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? $empresa['nome_empresa'] ?? $empresa['nome'] ?? '');
                }
                $cnh_total_pend = $cnh_vencidas + $cnh_vencendo;
                $rotas_pend = (int) ($rotas_pendentes_count ?? 0);
                $contas_pend = (int) ($contas_vencidas_count ?? 0);
                $despesas_pend = (int) ($despesas_pendentes_count ?? 0);
                $checklists_pend = (int) ($checklists_problema_count ?? 0);
                $notif_op = (int) ($notif_nao_lidas_operacional ?? 0);
                $notif_ia = (int) ($notif_nao_lidas_ia ?? 0);
                $total_pendencias = $rotas_pend + $contas_pend + $cnh_total_pend + $despesas_pend + $checklists_pend;
                ?>
                <?php if (isset($_GET['error']) && $_GET['error'] === 'access_denied'): ?>
                <div class="index-flash index-flash--warn dashboard-print-hidden" role="alert">
                    <i class="fas fa-lock" aria-hidden="true"></i> Você não tem permissão para acessar a página solicitada.
                </div>
                <?php endif; ?>
                <?php $home_updated_at = date('d/m/Y \às H:i'); include __DIR__ . '/includes/dashboard_home_v2.php'; ?>
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

            <?php include 'includes/footer.php'; ?>
        </div>
    </div>
    
    <!-- JavaScript Files (sfApiUrl/sfAppUrl vêm de includes/header.php) -->
    <script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
    <script defer src="<?php echo htmlspecialchars(sf_app_url('js/sf-charts-premium.js')); ?>"></script>
    <script defer src="js/dashboard.js?v=2.3"></script>
    <script defer src="js/theme.js"></script>
    <script defer src="js/sidebar.js"></script>
    <script>
        window.sfDashPeriod = <?php echo json_encode($home_period, JSON_UNESCAPED_UNICODE); ?>;
        window.__SF_DASH_CHART_FALLBACK__ = {
            faturamento: <?php echo json_encode((float) ($total_fretes ?? 0)); ?>,
            despesas: <?php echo json_encode((float) (($valor_total_abastecimentos ?? 0) + ($total_desp_viagem ?? 0) + ($total_desp_fixas ?? 0) + ($total_contas_pagas ?? 0) + ($total_manutencoes ?? 0) + ($total_pneu_manutencao ?? 0) + ($total_parcelas_financiamento ?? 0))); ?>
        };
    </script>
    <script defer src="<?php echo htmlspecialchars(sf_app_url('js/dashboard-home-charts.js?v=1.1')); ?>"></script>
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
    <?php $sf_help_key = 'dashboard'; $sf_help_modern = true; $sf_help_base = ''; include 'includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js(''); ?>
</body>
</html>
