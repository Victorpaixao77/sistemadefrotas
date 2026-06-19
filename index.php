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

// CNH vencida/vencendo (usado nas "Pendências de hoje" e nos alertas)
$cnh_vencidas = 0;
$cnh_vencendo = 0;
try {
    $stmt = $conn->prepare("SELECT
            SUM(CASE WHEN data_validade_cnh IS NOT NULL AND data_validade_cnh <> '0000-00-00'
                     AND data_validade_cnh < CURRENT_DATE THEN 1 ELSE 0 END) AS vencidas,
            SUM(CASE WHEN data_validade_cnh IS NOT NULL AND data_validade_cnh <> '0000-00-00'
                     AND data_validade_cnh BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS vencendo
        FROM motoristas WHERE empresa_id = :empresa_id");
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    $cnh_row = $stmt->fetch(PDO::FETCH_ASSOC);
    $cnh_vencidas = (int) ($cnh_row['vencidas'] ?? 0);
    $cnh_vencendo = (int) ($cnh_row['vencendo'] ?? 0);
} catch (Exception $e) {
    error_log("Erro ao verificar CNH no dashboard: " . $e->getMessage());
}

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
    <link rel="stylesheet" href="css/dashboard.css?v=1.4">
    <?php sf_page_help_css(''); ?>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="logo.png">
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Sortable.js for drag-and-drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</head>
<body class="index-dashboard-modern" data-dashboard-home="1" data-dash-period="<?php echo htmlspecialchars($home_period, ENT_QUOTES, 'UTF-8'); ?>">
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
                <!-- Saudação + pendências do dia -->
                <div class="index-greeting dashboard-print-hidden">
                    <div class="index-greeting-text">
                        <h1 class="index-greeting-title"><?php echo htmlspecialchars($saudacao . ($primeiro_nome !== '' ? ', ' . $primeiro_nome : '')); ?>!</h1>
                        <p class="index-greeting-sub">
                            <?php echo htmlspecialchars($nome_empresa !== '' ? $nome_empresa . ' · ' : ''); ?><?php echo htmlspecialchars(ucfirst($data_br)); ?><?php
                            $gps_veic = (int) ($gps_veiculos_sinal_recente ?? 0);
                            if ($gps_veic > 0):
                            ?> · <a href="pages/mapa_frota.php" class="index-gps-link" title="Ver no mapa da frota"><i class="fas fa-satellite-dish" aria-hidden="true"></i> <?php echo $gps_veic; ?> veíc. com GPS ativo</a><?php endif; ?>
                        </p>
                    </div>
                    <div class="index-pendencias" aria-label="Pendências que exigem ação">
                        <?php if ($total_pendencias === 0 && $notif_op === 0 && $notif_ia === 0): ?>
                            <span class="index-pend-ok"><i class="fas fa-circle-check"></i> Tudo em dia — nenhuma pendência</span>
                        <?php else: ?>
                            <?php if ($rotas_pend > 0): ?>
                            <a href="pages/routes.php" class="index-pend-pill is-warn"><i class="fas fa-route"></i> <strong><?php echo $rotas_pend; ?></strong> rota(s) a aprovar</a>
                            <?php endif; ?>
                            <?php if ($despesas_pend > 0): ?>
                            <a href="pages/routes.php" class="index-pend-pill is-warn"><i class="fas fa-receipt"></i> <strong><?php echo $despesas_pend; ?></strong> despesa(s) pendente(s)</a>
                            <?php endif; ?>
                            <?php if ($checklists_pend > 0): ?>
                            <a href="pages/checklists.php" class="index-pend-pill is-danger"><i class="fas fa-clipboard-check"></i> <strong><?php echo $checklists_pend; ?></strong> checklist(s) com problema</a>
                            <?php endif; ?>
                            <?php if ($contas_pend > 0): ?>
                            <a href="pages/contas_pagar.php" class="index-pend-pill is-danger"><i class="fas fa-file-invoice-dollar"></i> <strong><?php echo $contas_pend; ?></strong> conta(s) vencida(s)</a>
                            <?php endif; ?>
                            <?php if ($cnh_total_pend > 0): ?>
                            <a href="pages/motorists.php" class="index-pend-pill <?php echo $cnh_vencidas > 0 ? 'is-danger' : 'is-warn'; ?>"><i class="fas fa-id-card"></i> <strong><?php echo $cnh_total_pend; ?></strong> CNH a renovar</a>
                            <?php endif; ?>
                            <?php if ($notif_op > 0): ?>
                            <a href="pages/notificacoes.php" class="index-pend-pill is-info"><i class="fas fa-bell"></i> <strong><?php echo $notif_op; ?></strong> alerta(s) operacional(is)</a>
                            <?php endif; ?>
                            <?php if ($notif_ia > 0): ?>
                            <a href="pages/notificacoes.php" class="index-pend-pill is-info"><i class="fas fa-robot"></i> <strong><?php echo $notif_ia; ?></strong> insight(s) de IA</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

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
                            $diasManut = $manutencao_urgente['dias_sem_manutencao'];
                            if ($diasManut === null || $diasManut === '') {
                                $msgManut = "Veículo {$manutencao_urgente['placa']} nunca teve manutenção registrada.";
                            } else {
                                $msgManut = "Veículo {$manutencao_urgente['placa']} precisa de manutenção preventiva há " . (int) $diasManut . " dias.";
                            }
                            $alertas[] = [
                                'tipo' => 'Manutenção Urgente',
                                'mensagem' => $msgManut,
                                'cor' => '#e74c3c',
                                'link' => 'pages/manutencoes.php',
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
                                'cor' => '#f39c12',
                                'link' => 'pages/pneus.php',
                            ];
                        }
                        
                        // 3. Despesa alta (combustível do mês acima da média mensal dos últimos 3 meses)
                        $sql_despesa_alta = "SELECT
                            (SELECT COALESCE(SUM(valor_total), 0) FROM abastecimentos
                             WHERE empresa_id = :empresa_id
                             AND MONTH(data_abastecimento) = MONTH(CURRENT_DATE)
                             AND YEAR(data_abastecimento) = YEAR(CURRENT_DATE)) AS total_mes,
                            (SELECT COALESCE(AVG(mes_total), 0) FROM (
                                SELECT SUM(valor_total) AS mes_total
                                FROM abastecimentos
                                WHERE empresa_id = :empresa_id2
                                AND data_abastecimento >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)
                                GROUP BY YEAR(data_abastecimento), MONTH(data_abastecimento)
                            ) t) AS media_mensal";
                        
                        $stmt = $conn->prepare($sql_despesa_alta);
                        $stmt->bindParam(':empresa_id', $empresa_id);
                        $stmt->bindParam(':empresa_id2', $empresa_id);
                        $stmt->execute();
                        $despesa_data = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($despesa_data && (float) $despesa_data['media_mensal'] > 0
                            && (float) $despesa_data['total_mes'] > ((float) $despesa_data['media_mensal'] * 1.2)) {
                            $alertas[] = [
                                'tipo' => 'Despesa Alta',
                                'mensagem' => "Despesa de combustível 20% acima da média dos últimos 3 meses.",
                                'cor' => '#2980b9',
                                'link' => 'pages/abastecimentos.php',
                            ];
                        }
                        
                    } catch (Exception $e) {
                        error_log("Erro ao buscar alertas: " . $e->getMessage());
                    }

                    if (!empty($rotas_pendentes_count)) {
                        $alertas[] = [
                            'tipo' => 'Rotas pendentes',
                            'mensagem' => $rotas_pendentes_count . ' rota(s) aguardando análise.',
                            'cor' => '#f39c12',
                            'link' => 'pages/routes.php',
                        ];
                    }
                    if (!empty($contas_vencidas_count)) {
                        $alertas[] = [
                            'tipo' => 'Contas em atraso',
                            'mensagem' => $contas_vencidas_count . ' conta(s) vencidas (R$ ' . number_format($contas_vencidas_valor, 2, ',', '.') . ').',
                            'cor' => '#e74c3c',
                            'link' => 'pages/contas_pagar.php',
                        ];
                    }

                    // CNH vencida / vencendo (risco legal: multa + veículo parado) — contagens já computadas no topo
                    if ($cnh_vencidas > 0) {
                        $alertas[] = [
                            'tipo' => 'CNH vencida',
                            'mensagem' => $cnh_vencidas . ' motorista(s) com CNH vencida — risco de multa e veículo parado.',
                            'cor' => '#e74c3c',
                            'link' => 'pages/motorists.php',
                        ];
                    } elseif ($cnh_vencendo > 0) {
                        $alertas[] = [
                            'tipo' => 'CNH vencendo',
                            'mensagem' => $cnh_vencendo . ' motorista(s) com CNH vencendo nos próximos 30 dias.',
                            'cor' => '#f39c12',
                            'link' => 'pages/motorists.php',
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
                        $alert_link = isset($alerta['link']) ? htmlspecialchars($alerta['link'], ENT_QUOTES, 'UTF-8') : '';
                        $alert_tag = $alert_link !== '' ? 'a' : 'div';
                        ?>
                        <<?php echo $alert_tag; ?> class="alerta-card alerta-card--modern<?php echo $alert_link !== '' ? ' alerta-card--link' : ''; ?>"
                             <?php if ($alert_link !== ''): ?>href="<?php echo $alert_link; ?>"<?php endif; ?>
                             style="--alert-accent: <?php echo $alert_accent; ?>; display: flex; align-items: center; gap: 12px; border-radius: 8px;">
                            <i class="fas fa-<?php echo $icon_alert; ?>"></i>
                            <span><?= htmlspecialchars($alerta['tipo']) ?>: <?= htmlspecialchars($alerta['mensagem']) ?></span>
                            <?php if ($alert_link !== ''): ?><i class="fas fa-chevron-right alerta-card-go" aria-hidden="true"></i><?php endif; ?>
                        </<?php echo $alert_tag; ?>>
                    <?php endforeach; ?>
                </div>

                <?php $home_updated_at = date('d/m/Y \à\s H:i'); ?>
                <div class="index-home-controls no-print">
                    <form method="get" action="" class="index-period-form">
                        <label for="dashPeriod"><i class="fas fa-sliders-h" aria-hidden="true"></i> Período</label>
                        <select name="period" id="dashPeriod" onchange="this.form.submit()" title="Filtra os indicadores abaixo (exceto cadastros).">
                            <option value="year" <?php echo $home_period === 'year' ? 'selected' : ''; ?>>Ano atual</option>
                            <option value="month" <?php echo $home_period === 'month' ? 'selected' : ''; ?>>Mês atual</option>
                            <option value="quarter" <?php echo $home_period === 'quarter' ? 'selected' : ''; ?>>Trimestre atual</option>
                            <option value="all" <?php echo $home_period === 'all' ? 'selected' : ''; ?>>Todo o período</option>
                        </select>
                    </form>
                    <div class="index-home-controls-actions">
                        <span class="index-home-updated" title="Atualizado em <?php echo htmlspecialchars($home_updated_at, ENT_QUOTES, 'UTF-8'); ?>">Atualizado em <?php echo htmlspecialchars($home_updated_at, ENT_QUOTES, 'UTF-8'); ?></span>
                        <a href="?period=<?php echo urlencode($home_period); ?>&amp;refresh=1" class="fornc-btn fornc-btn--ghost" title="Recarregar indicadores (ignora cache)"><i class="fas fa-rotate"></i></a>
                        <button type="button" class="fornc-btn fornc-btn--muted" id="btnDashboardPrint" title="Imprimir resumo"><i class="fas fa-print"></i></button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="btnDashboardCsv" title="Exportar CSV"><i class="fas fa-file-csv"></i></button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>

                <!-- Dashboard KPIs (agrupados em seções para reduzir poluição visual) -->
                <div class="dash-customize-row no-print" style="margin:0.25rem 0 0.5rem;">
                    <button type="button" class="fornc-btn fornc-btn--ghost" id="toggleLayoutBtn" title="Alternar entre cards normais e compactos"><i class="fas fa-compress"></i> Compactar</button>
                    <button type="button" class="fornc-btn fornc-btn--muted" id="restoreLayoutBtn" title="Restaurar a ordem padrão dos cards"><i class="fas fa-rotate-left"></i> Restaurar ordem</button>
                </div>
                <div class="dash-section">
                    <div class="dash-section-title"><i class="fas fa-truck-fast"></i> Operação</div>
                    <div class="dashboard-grid dashboard-print-hidden" id="dashboardGrid" data-dash-section="operacao">
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-veiculos" data-href="pages/vehicles.php" tabindex="0" role="link" aria-label="Ver veículos">
                        <div class="card-header">
                            <i class="fas fa-truck kpi-card-icon" aria-hidden="true"></i>
                            <h3>Total de Veículos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $total_veiculos; ?></span>
                                <span class="metric-subtitle">Veículos cadastrados</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-motoristas" data-href="pages/motorists.php" tabindex="0" role="link" aria-label="Ver motoristas">
                        <div class="card-header">
                            <i class="fas fa-users kpi-card-icon" aria-hidden="true"></i>
                            <h3>Motoristas/Colaboradores</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $total_motoristas; ?></span>
                                <span class="metric-subtitle">Motoristas ativos</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-rotas" data-href="pages/routes.php" tabindex="0" role="link" aria-label="Ver rotas">
                        <div class="card-header">
                            <i class="fas fa-route kpi-card-icon" aria-hidden="true"></i>
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
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-abastecimentos" data-href="pages/abastecimentos.php" tabindex="0" role="link" aria-label="Ver abastecimentos">
                        <div class="card-header">
                            <i class="fas fa-gas-pump kpi-card-icon" aria-hidden="true"></i>
                            <h3>Abastecimentos</h3>
                        </div>
                        <div class="card-body">
                            <div class="kpi-abast-dual">
                                <div class="metric kpi-abast-item">
                                    <span class="metric-value"><?php echo (int) $total_abastecimentos; ?></span>
                                    <span class="metric-subtitle">Registros no período</span>
                                </div>
                                <?php if ($can_view_financial_data): ?>
                                <div class="metric kpi-abast-item kpi-abast-item--valor">
                                    <span class="metric-value">R$ <?php echo number_format($valor_total_abastecimentos, 2, ',', '.'); ?></span>
                                    <span class="metric-subtitle">Total gasto</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($home_compare_available && $delta_abast_valor_pct !== null): ?>
                            <p class="index-kpi-delta" style="margin:0.45rem 0 0;"><?php echo htmlspecialchars(dashboard_home_format_delta($delta_abast_valor_pct), ENT_QUOTES, 'UTF-8'); ?> (valor)</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    </div>
                </div>
                <?php if ($can_view_financial_data): ?>
                <div class="dash-section">
                    <div class="dash-section-title"><i class="fas fa-coins"></i> Custos</div>
                    <div class="dashboard-grid dashboard-print-hidden" data-dash-section="custos">
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-desp-viagem" data-href="pages/routes.php" tabindex="0" role="link">
                        <div class="card-header">
                            <i class="fas fa-suitcase-rolling kpi-card-icon" aria-hidden="true"></i>
                            <h3>Despesas de Viagem</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_desp_viagem, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Custos variáveis</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-desp-fixas" data-href="pages/despesas_fixas.php" tabindex="0" role="link">
                        <div class="card-header">
                            <i class="fas fa-file-invoice-dollar kpi-card-icon" aria-hidden="true"></i>
                            <h3>Despesas Fixas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_desp_fixas, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Pagas no período</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-contas" data-href="pages/contas_pagar.php" tabindex="0" role="link">
                        <div class="card-header">
                            <i class="fas fa-money-check-dollar kpi-card-icon" aria-hidden="true"></i>
                            <h3>Contas Pagas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_contas_pagas, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Outros pagamentos</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-manut-veiculos" data-href="pages/manutencoes.php" tabindex="0" role="link">
                        <div class="card-header">
                            <i class="fas fa-screwdriver-wrench kpi-card-icon" aria-hidden="true"></i>
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
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-manut-pneus" data-href="pages/manutencao_pneus.php" tabindex="0" role="link">
                        <div class="card-header">
                            <i class="fas fa-circle-dot kpi-card-icon" aria-hidden="true"></i>
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
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-financiamento" data-href="pages/financiamento.php" tabindex="0" role="link">
                        <div class="card-header">
                            <i class="fas fa-landmark kpi-card-icon" aria-hidden="true"></i>
                            <h3>Parcelas de Financiamento</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_parcelas_financiamento, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Pagas no período</span>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="dash-section">
                    <div class="dash-section-title"><i class="fas fa-chart-line"></i> Financeiro</div>
                    <div class="dashboard-grid dashboard-print-hidden" data-dash-section="financeiro">
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-fretes" data-href="<?php echo $can_view_financial_data ? 'pages/lucratividade.php' : 'pages/routes.php'; ?>" tabindex="0" role="link">
                        <div class="card-header">
                            <i class="fas <?php echo $can_view_financial_data ? 'fa-sack-dollar' : 'fa-route'; ?> kpi-card-icon" aria-hidden="true"></i>
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
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-comissoes" data-href="pages/comissoes.php" tabindex="0" role="link">
                        <div class="card-header">
                            <i class="fas fa-hand-holding-dollar kpi-card-icon" aria-hidden="true"></i>
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
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-comissoes-pct" data-href="pages/comissoes.php" tabindex="0" role="link">
                        <div class="card-header">
                            <i class="fas fa-percent kpi-card-icon" aria-hidden="true"></i>
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
                    <div class="dashboard-card index-kpi-lucro dashboard-card--link" data-card-id="kpi-lucro" data-href="pages/lucratividade.php" tabindex="0" role="link">
                        <div class="card-header">
                            <i class="fas fa-chart-line kpi-card-icon" aria-hidden="true"></i>
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
                </div>

                <?php if (!empty($proximas_rotas)): ?>
                <div class="card dashboard-print-hidden index-proximas-rotas">
                    <div class="card-header">
                        <h2><i class="fas fa-calendar-week" aria-hidden="true"></i> Próximas rotas (7 dias)</h2>
                    </div>
                    <div class="card-body">
                        <ul class="index-proximas-list">
                            <?php foreach ($proximas_rotas as $pr):
                                $prData = $pr['data_saida'] ?? $pr['data_rota'] ?? '';
                                $prOrigem = trim(($pr['cidade_origem'] ?? '') . ($pr['estado_origem'] ? '/' . $pr['estado_origem'] : ''));
                                $prDestino = trim(($pr['cidade_destino'] ?? '') . ($pr['estado_destino'] ? '/' . $pr['estado_destino'] : ''));
                                if ($prOrigem === '') {
                                    $prOrigem = (string) ($pr['estado_origem'] ?? '—');
                                }
                                if ($prDestino === '') {
                                    $prDestino = (string) ($pr['estado_destino'] ?? '—');
                                }
                            ?>
                            <li>
                                <a href="pages/routes.php" class="index-proximas-item">
                                    <span class="index-proximas-data"><?php echo $prData ? htmlspecialchars(date('d/m', strtotime($prData)), ENT_QUOTES, 'UTF-8') : '—'; ?></span>
                                    <span class="index-proximas-rota"><?php echo htmlspecialchars($prOrigem . ' → ' . $prDestino, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="index-proximas-meta"><?php echo htmlspecialchars(($pr['motorista'] ?? '') . ' · ' . ($pr['placa'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Gráfico Financeiro -->
                <?php if ($can_view_financial_data): ?>
                <div class="analytics-section dashboard-print-hidden" id="dashboardAnalyticsSection">
                    <div class="section-header">
                        <h2>Análise Financeira</h2>
                        <p style="margin:0.25rem 0 0;font-size:0.78rem;color:var(--text-muted);font-weight:normal;">Gráficos no período: <strong><?php echo htmlspecialchars($home_period_label, ENT_QUOTES, 'UTF-8'); ?></strong> (mesmo filtro dos indicadores acima).</p>
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
                                <h3>Distribuição de Despesas (<?php echo htmlspecialchars($home_period_label, ENT_QUOTES, 'UTF-8'); ?>)</h3>
                    </div>
                    <div class="card-body">
                                <canvas id="expensesDistributionChart"></canvas>
                    </div>
                </div>

                        <div class="analytics-card">
                    <div class="card-header">
                                <h3>Comissões Pagas (<?php echo htmlspecialchars($home_period_label, ENT_QUOTES, 'UTF-8'); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="commissionsChart"></canvas>
                    </div>
                </div>

                        <div class="analytics-card">
                    <div class="card-header">
                                <h3>Lucro Líquido (<?php echo htmlspecialchars($home_period_label, ENT_QUOTES, 'UTF-8'); ?>)</h3>
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
                            $sql_rotas = "SELECT r.id, r.estado_origem, r.estado_destino, r.data_saida, r.data_rota, m.nome as motorista, v.placa,
                                         co.nome as cidade_origem, cd.nome as cidade_destino
                                         FROM rotas r 
                                         JOIN motoristas m ON r.motorista_id = m.id 
                                         JOIN veiculos v ON r.veiculo_id = v.id 
                                         LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                                         LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                                         WHERE r.empresa_id = :empresa_id 
                                         ORDER BY COALESCE(r.data_saida, r.data_rota) DESC LIMIT 3";
                            $stmt = $conn->prepare($sql_rotas);
                            $stmt->bindParam(':empresa_id', $empresa_id);
                            $stmt->execute();
                            $rotas_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($rotas_recentes as $rota) {
                                $origem = $rota['cidade_origem'] ? $rota['cidade_origem'] . '/' . $rota['estado_origem'] : $rota['estado_origem'];
                                $destino = $rota['cidade_destino'] ? $rota['cidade_destino'] . '/' . $rota['estado_destino'] : $rota['estado_destino'];
                                $dataRef = $rota['data_saida'] ?: $rota['data_rota'];
                                $atividades[] = [
                                    'ts' => $dataRef ? strtotime($dataRef) : 0,
                                    'data' => $dataRef ? date('d/m/Y', strtotime($dataRef)) : '—',
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
                                    'ts' => strtotime($abastecimento['data_abastecimento']),
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
                                    'ts' => strtotime($manutencao['data_manutencao']),
                                    'data' => date('d/m/Y', strtotime($manutencao['data_manutencao'])),
                                    'descricao' => "Manutenção: {$manutencao['placa']} - " . substr($manutencao['descricao'], 0, 50) . "... (R$ " . number_format($manutencao['valor'], 2, ',', '.') . ")",
                                    'tipo' => 'manutencao',
                                    'cor' => '#f39c12'
                                ];
                            }
                            
                            // Ordenar por data (mais recentes primeiro)
                            usort($atividades, function ($a, $b) {
                                return ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0);
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
                            <ul class="index-atividades-list">
                                <?php foreach ($atividades as $atividade): ?>
                                    <li class="index-atividade-item">
                                        <span class="index-atividade-icon" style="color: <?php echo htmlspecialchars($atividade['cor'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="fas fa-<?php echo $atividade['tipo'] === 'rota' ? 'road' : ($atividade['tipo'] === 'abastecimento' ? 'gas-pump' : ($atividade['tipo'] === 'manutencao' ? 'wrench' : 'money-bill')); ?>"></i>
                                        </span>
                                        <div class="index-atividade-body">
                                            <div class="index-atividade-desc"><?php echo htmlspecialchars($atividade['descricao'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="index-atividade-data"><?php echo htmlspecialchars($atividade['data'], ENT_QUOTES, 'UTF-8'); ?></div>
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
                        $insights_list = [];

                        try {
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
                            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
                            $stmt->execute();
                            $fornecedor_caro = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($fornecedor_caro) {
                                $insights_list[] = 'Reduza custos de manutenção trocando fornecedor '
                                    . $fornecedor_caro['fornecedor'] . ' (média R$ '
                                    . number_format((float) $fornecedor_caro['custo_medio'], 2, ',', '.') . ').';
                            } else {
                                $sql_fornecedor_alt = "SELECT f.nome as fornecedor, AVG(m.valor) as custo_medio
                                    FROM manutencoes m
                                    JOIN fornecedores f ON m.fornecedor_id = f.id
                                    WHERE m.empresa_id = :empresa_id
                                    AND m.data_manutencao >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
                                    GROUP BY f.id
                                    ORDER BY custo_medio DESC
                                    LIMIT 1";
                                $stmt = $conn->prepare($sql_fornecedor_alt);
                                $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
                                $stmt->execute();
                                $fornecedor_alt = $stmt->fetch(PDO::FETCH_ASSOC);
                                if ($fornecedor_alt) {
                                    $insights_list[] = 'Reduza custos de manutenção trocando fornecedor '
                                        . $fornecedor_alt['fornecedor'] . ' (média R$ '
                                        . number_format((float) $fornecedor_alt['custo_medio'], 2, ',', '.') . ').';
                                } else {
                                    $insights_list[] = 'Analise os custos de manutenção por fornecedor para otimizar gastos.';
                                }
                            }
                        } catch (Exception $e) {
                            $insights_list[] = 'Analise os custos de manutenção por fornecedor para otimizar gastos.';
                        }

                        try {
                            $sql_motorista_consumo = "SELECT m.nome as motorista,
                                AVG(a.litros / NULLIF(r.distancia_km, 0)) as consumo_medio,
                                COUNT(DISTINCT r.id) as total_rotas
                                FROM motoristas m
                                JOIN rotas r ON m.id = r.motorista_id
                                JOIN abastecimentos a ON r.id = a.rota_id
                                WHERE m.empresa_id = :empresa_id
                                AND r.data_saida >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)
                                AND r.distancia_km > 0 AND a.litros > 0
                                GROUP BY m.id
                                HAVING total_rotas >= 3
                                ORDER BY consumo_medio ASC
                                LIMIT 1";
                            $stmt = $conn->prepare($sql_motorista_consumo);
                            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
                            $stmt->execute();
                            $melhor_motorista = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($melhor_motorista) {
                                $consumo_l_100km = (float) $melhor_motorista['consumo_medio'] * 100;
                                $insights_list[] = 'Motorista ' . $melhor_motorista['motorista']
                                    . ' tem melhor desempenho de consumo (' . number_format($consumo_l_100km, 1, ',', '.') . ' L/100 km).';
                            } else {
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
                                $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
                                $stmt->execute();
                                $motorista_alt = $stmt->fetch(PDO::FETCH_ASSOC);
                                if ($motorista_alt) {
                                    $insights_list[] = 'Motorista ' . $motorista_alt['motorista']
                                        . ' realizou ' . (int) $motorista_alt['total_rotas'] . ' rotas nos últimos 6 meses.';
                                } else {
                                    $insights_list[] = 'Monitore o desempenho de consumo dos motoristas para otimizar gastos com combustível.';
                                }
                            }
                        } catch (Exception $e) {
                            $insights_list[] = 'Monitore o desempenho de consumo dos motoristas para otimizar gastos com combustível.';
                        }

                        if ($can_view_financial_data) {
                            try {
                                $sql_lucratividade_horario = "SELECT
                                    CASE
                                        WHEN HOUR(r.data_saida) BETWEEN 22 AND 23 OR HOUR(r.data_saida) BETWEEN 0 AND 6 THEN 'Noturno'
                                        WHEN HOUR(r.data_saida) BETWEEN 6 AND 17 THEN 'Diurno'
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
                                $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
                                $stmt->execute();
                                $melhor_horario = $stmt->fetch(PDO::FETCH_ASSOC);
                                if ($melhor_horario) {
                                    $insights_list[] = 'Rotas ' . $melhor_horario['horario']
                                        . ' apresentam maior lucratividade média (R$ '
                                        . number_format((float) $melhor_horario['lucro_medio'], 2, ',', '.') . ').';
                                } else {
                                    $insights_list[] = 'Analise a lucratividade por horário de rotas para otimizar operações.';
                                }
                            } catch (Exception $e) {
                                $insights_list[] = 'Analise a lucratividade por horário de rotas para otimizar operações.';
                            }
                        }
                        ?>

                        <?php if (empty($insights_list)): ?>
                            <p style="color: var(--text-muted, #666); font-style: italic;">Sem insights suficientes no momento.</p>
                        <?php else: ?>
                        <ul class="index-insights-list">
                            <?php foreach ($insights_list as $insight): ?>
                            <li><?php echo htmlspecialchars($insight, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
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
    <script src="js/dashboard.js?v=2.2"></script>
    <script src="js/theme.js"></script>
    <script src="js/sidebar.js"></script>
    <script>
        var sfDashPeriod = <?php echo json_encode($home_period, JSON_UNESCAPED_UNICODE); ?>;
        function sfChartApiUrl(path) {
            var sep = path.indexOf('?') >= 0 ? '&' : '?';
            return sfApiUrl(path + sep + 'period=' + encodeURIComponent(sfDashPeriod));
        }

        // Função para inicializar o gráfico de distribuição de despesas
        async function initExpensesDistributionChart() {
            try {
                // Destroy existing chart if it exists
                const existingChart = Chart.getChart('expensesDistributionChart');
                if (existingChart) {
                    existingChart.destroy();
                }

                const response = await fetch(sfChartApiUrl('expenses_distribution.php'));
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

                const response = await fetch(sfChartApiUrl('commissions_analytics.php'));
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

                const response = await fetch(sfChartApiUrl('net_revenue_analytics.php'));
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

                const response = await fetch(sfChartApiUrl('financial_analytics.php'));
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
    <?php $sf_help_key = 'dashboard'; $sf_help_modern = true; $sf_help_base = ''; include 'includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js(''); ?>
</body>
</html>
