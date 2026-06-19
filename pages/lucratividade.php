<?php
// Include configuration and functions first
$required_files = [
    '../includes/config.php',
    '../includes/functions.php',
    '../includes/db_connect.php'
];

// Verificar se todos os arquivos necessários existem
foreach ($required_files as $file) {
    if (!file_exists($file)) {
        error_log("Arquivo não encontrado: " . $file);
        http_response_code(500);
        die('Erro interno do servidor');
    }
}

require_once '../includes/config.php';
require_once __DIR__ . '/../includes/sf_page_help.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_once '../includes/permissions.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Check authentication - usar o padrão correto do sistema
require_authentication();

// Verificar permissões para acessar lucratividade (apenas admin)
require_permission('access_lucratividade');

// Set page title
$page_title = "Lucratividade";

// Obter empresa_id da sessão (já validada pelo require_authentication)
$empresa_id = $_SESSION['empresa_id'];

// Obter conexão com o banco de dados
try {
    $conn = getConnection();
} catch (Exception $e) {
    error_log("Erro de conexão com banco: " . $e->getMessage());
    if (DEBUG_MODE) {
        throw $e;
    } else {
        http_response_code(500);
        die('Erro interno do servidor');
    }
}

// Obter e validar parâmetros de mês e ano
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('m');
$ano = isset($_GET['ano']) ? intval($_GET['ano']) : date('Y');

// Validar range dos parâmetros
if ($mes < 1 || $mes > 12) {
    $mes = date('m');
}
if ($ano < 2020 || $ano > 2030) {
    $ano = date('Y');
}

// Layout moderno
$is_modern = true;
$lucra_qs_params = ['mes' => (int) $mes, 'ano' => (int) $ano];
$lucra_query_classic = http_build_query(array_merge($lucra_qs_params, ['classic' => '1']));
$lucra_meses_pt = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];
$lucra_periodo_label = ($lucra_meses_pt[(int) $mes] ?? '') . ' de ' . (int) $ano;
$lucra_query_modern_only = http_build_query($lucra_qs_params);
$lucra_prev_mes = (int) $mes <= 1 ? 12 : (int) $mes - 1;
$lucra_prev_ano = (int) $mes <= 1 ? (int) $ano - 1 : (int) $ano;
$lucra_next_mes = (int) $mes >= 12 ? 1 : (int) $mes + 1;
$lucra_next_ano = (int) $mes >= 12 ? (int) $ano + 1 : (int) $ano;
$lucra_nav_params = static function (int $m, int $y) use ($is_modern): array {
    $p = ['mes' => $m, 'ano' => $y];
    if (!$is_modern) {
        $p['classic'] = '1';
    }
    return $p;
};
$lucra_href_prev = 'lucratividade.php?' . http_build_query($lucra_nav_params($lucra_prev_mes, $lucra_prev_ano));
$lucra_href_next = 'lucratividade.php?' . http_build_query($lucra_nav_params($lucra_next_mes, $lucra_next_ano));
require_once '../includes/lucratividade_funcoes.php';

if (!function_exists('lucratividade_debug_log')) {
    function lucratividade_debug_log(string $message): void {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log($message);
        }
    }
}

// Buscar dados otimizados (nova implementação)
try {
    $kpis = getKPIsOptimized($conn, $empresa_id, $mes, $ano);
    $alerts = getIntelligentAlerts($conn, $empresa_id, $mes, $ano);
    
    // Garantir que $alerts sempre seja um array
    if (!is_array($alerts)) {
        $alerts = [];
    }
    
    lucratividade_debug_log("DEBUG - Alertas finais para mês $mes/$ano: " . count($alerts) . " alertas");
    lucratividade_debug_log("DEBUG - Conteúdo dos alertas: " . json_encode($alerts));
    
    // Calcular Lucratividade
    $lucro = isset($kpis) && is_array($kpis) && isset($kpis['lucro_liquido']) ? $kpis['lucro_liquido'] : 0;
    
    // Calcular tendências comparando com mês anterior
    $mes_anterior = $mes == 1 ? 12 : $mes - 1;
    $ano_anterior = $mes == 1 ? $ano - 1 : $ano;
    
    $kpis_anterior = getKPIsOptimized($conn, $empresa_id, $mes_anterior, $ano_anterior);
    
    // Calcular KPIs avançados
    lucratividade_debug_log("DEBUG - KPIs recebidos: " . json_encode($kpis));
    if (!empty($kpis) && is_array($kpis)) {
        $advanced_kpis = getAdvancedKPIs($conn, $empresa_id, $mes, $ano, $kpis);
        lucratividade_debug_log("DEBUG - KPIs avançados calculados: " . json_encode($advanced_kpis));
    } else {
        lucratividade_debug_log("DEBUG - KPIs vazios ou inválidos, usando valores padrão");
        $advanced_kpis = [
            'roi' => 0,
            'ticket_medio' => 0,
            'custo_km' => 0,
            'margem_operacional' => 0,
            'taxa_ocupacao' => 0
        ];
    }
    
    // Calcular tendências dos KPIs avançados comparando com mês anterior
    $advanced_kpis_anterior = [];
    $tendencias_advanced = [];
    if ($kpis_anterior && !empty($kpis_anterior)) {
        $advanced_kpis_anterior = getAdvancedKPIs($conn, $empresa_id, $mes_anterior, $ano_anterior, $kpis_anterior);
        
        // Calcular tendências
        foreach ($advanced_kpis as $key => $valor_atual) {
            $valor_anterior = $advanced_kpis_anterior[$key] ?? 0;
            if ($valor_anterior != 0) {
                $tendencias_advanced[$key] = (($valor_atual - $valor_anterior) / $valor_anterior) * 100;
            } else {
                $tendencias_advanced[$key] = $valor_atual > 0 ? 100 : 0;
            }
        }
    } else {
        $tendencias_advanced = [
            'roi' => 0,
            'ticket_medio' => 0,
            'custo_km' => 0,
            'margem_operacional' => 0,
            'taxa_ocupacao' => 0
        ];
    }
    
    // Buscar rankings
    $rankings = getRankings($conn, $empresa_id, $mes, $ano);
    
    // Calcular tendências
    $crescimento_lucro = 0;
    $crescimento_receita = 0;
    $variacao_combustivel = 0;
    $tendencias = [];
    
    if ($kpis_anterior && $kpis) {
        // Tendência do lucro
        if ($kpis_anterior['lucro_liquido'] != 0) {
            $crescimento_lucro = (($kpis['lucro_liquido'] - $kpis_anterior['lucro_liquido']) / $kpis_anterior['lucro_liquido']) * 100;
        }
        
        // Tendência da receita
        if ($kpis_anterior['total_frete'] != 0) {
            $crescimento_receita = (($kpis['total_frete'] - $kpis_anterior['total_frete']) / $kpis_anterior['total_frete']) * 100;
        }
        
        // Tendência do combustível (quanto menor, melhor)
        if ($kpis_anterior['total_abastecimentos'] != 0) {
            $variacao_combustivel = (($kpis['total_abastecimentos'] - $kpis_anterior['total_abastecimentos']) / $kpis_anterior['total_abastecimentos']) * 100;
        }
        
        // Calcular tendências para cada item do resumo financeiro
        $items = [
            'total_frete' => 'Total de Fretes',
            'total_comissao' => 'Total de Comissões',
            'total_despesas_viagem' => 'Despesas de Viagem',
            'total_abastecimentos' => 'Abastecimentos',
            'total_despesas_fixas' => 'Despesas Fixas',
            'total_parcelas_financiamento' => 'Parcelas de Financiamento',
            'total_contas_pagas' => 'Contas Pagas',
            'total_manutencoes' => 'Manutenções de Veículos',
            'total_pneu_manutencao' => 'Manutenções de Pneus'
        ];
        
        foreach ($items as $key => $label) {
            $valor_atual = isset($kpis[$key]) ? $kpis[$key] : 0;
            $valor_anterior = isset($kpis_anterior[$key]) ? $kpis_anterior[$key] : 0;
            
            if ($valor_anterior != 0) {
                $tendencia = (($valor_atual - $valor_anterior) / $valor_anterior) * 100;
            } else {
                $tendencia = 0;
            }
            
            $tendencias[$key] = $tendencia;
        }
    }
    
} catch (Throwable $e) {
    error_log("Erro ao buscar dados de lucratividade: " . $e->getMessage());
    $kpis = lucra_kpis_vazios((int)$mes, (int)$ano);
    $alerts = [];
    $lucro = 0;
    $crescimento_lucro = 0;
    $crescimento_receita = 0;
    $variacao_combustivel = 0;
    $tendencias = [];
    $advanced_kpis = [
        'roi' => 0,
        'ticket_medio' => 0,
        'custo_km' => 0,
        'margem_operacional' => 0,
        'taxa_ocupacao' => 0
    ];
    $rankings = [
        'veiculos' => [],
        'motoristas' => [],
        'clientes' => [],
        'tipos_frete' => []
    ];
}

// Garantir que $alerts sempre seja um array válido
if (!isset($alerts) || !is_array($alerts)) {
    $alerts = [];
}


$lucra_receita_kpi = is_array($kpis) ? floatval($kpis['total_frete'] ?? 0) : 0;
$lucra_margem_kpi = isset($advanced_kpis['margem_operacional']) ? floatval($advanced_kpis['margem_operacional']) : 0;
$lucra_ticket_kpi = isset($advanced_kpis['ticket_medio']) ? floatval($advanced_kpis['ticket_medio']) : 0;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <?php if ($is_modern): ?>
    <link rel="stylesheet" href="../css/fornc-modern-page.css">
    <?php endif; ?>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../logo.png">

    <style>
        /* Estilos específicos para os gráficos de lucratividade */
        .analytics-card canvas {
            max-height: 280px !important;
            width: 100% !important;
        }
        
        /* Ajuste para o gráfico gauge */
        #profitPerKmGauge {
            max-height: 250px !important;
        }

        .lucra-chart-msg {
            padding: 0.85rem 1rem;
            text-align: center;
            font-size: 0.875rem;
            line-height: 1.45;
            border-radius: 8px;
            margin: 0.5rem 0;
        }
        .lucra-chart-msg--load {
            color: var(--text-muted);
            background: var(--bg-tertiary, rgba(0, 0, 0, 0.04));
        }
        .lucra-chart-msg--err {
            color: #b91c1c;
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.25);
        }
        
        /* Responsividade para telas menores */
        @media (max-width: 768px) {
            .analytics-grid {
                grid-template-columns: 1fr !important;
            }
            
            .analytics-card canvas {
                max-height: 250px !important;
            }
        }
        
        /* Alertas */
        .alerts-section {
            margin-bottom: 2rem;
        }
        
        .alerts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .alert-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-left: 4px solid;
            transition: all 0.3s ease;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .alert-card.alert-success {
            border-left-color: #28a745;
            background: linear-gradient(135deg, #f8fff9, #e8f5e8);
        }
        
        .alert-card.alert-warning {
            border-left-color: #ffc107;
            background: linear-gradient(135deg, #fffdf8, #fef9e7);
        }
        
        .alert-card.alert-danger {
            border-left-color: #dc3545;
            background: linear-gradient(135deg, #fff8f8, #f8e8e8);
        }
        
        .alert-card.alert-info {
            border-left-color: #17a2b8;
            background: linear-gradient(135deg, #f8fdff, #e8f4f8);
        }
        
        .alert-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .alert-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            color: white;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .alert-success .alert-icon {
            background: #28a745;
        }
        
        .alert-warning .alert-icon {
            background: #ffc107;
            color: #000;
        }
        
        .alert-danger .alert-icon {
            background: #dc3545;
        }
        
        .alert-info .alert-icon {
            background: #17a2b8;
        }
        
        .alert-content h4 {
            margin: 0 0 0.5rem 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: #212529;
        }
        
        .alert-content p {
            margin: 0;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        /* Estilos para badges na tabela */
        .badge {
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.375rem;
        }
        
        .bg-success {
            background-color: #198754 !important;
            color: white;
        }
        
        .bg-warning {
            background-color: #ffc107 !important;
            color: #000;
        }
        
        .bg-danger {
            background-color: #dc3545 !important;
            color: white;
        }
        
        .bg-info {
            background-color: #0dcaf0 !important;
            color: #000;
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .alerts-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Estilos para KPIs Avançados */
        .advanced-kpis {
            margin-bottom: 2rem;
        }
        
        .kpis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .kpi-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #007bff, #6610f2);
        }
        
        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .kpi-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #007bff, #6610f2);
            color: white;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .kpi-content h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            color: #212529;
        }
        
        .kpi-content p {
            margin: 0 0 0.5rem 0;
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .kpi-trend {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .kpi-trend.positive {
            background: #d4edda;
            color: #155724;
        }
        
        .kpi-trend.negative {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .alerts-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Espaçamento adicional para tabelas */
        .table-responsive .table th,
        .table-responsive .table td {
            padding: 0.75rem 1rem !important;
        }
        
        .table-responsive .table th {
            padding: 1rem 1.25rem !important;
        }
        
        /* Estilos para tendências */
        .trend-up {
            color: #28a745;
            font-weight: 600;
        }
        
        .trend-down {
            color: #dc3545;
            font-weight: 600;
        }
        
        .trend-neutral {
            color: #6c757d;
            font-weight: 600;
        }
        
        .trend-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .trend-badge.trend-up {
            background-color: #d4edda;
            color: #155724;
        }
        
        .trend-badge.trend-down {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .trend-badge.trend-neutral {
            background-color: #e2e3e5;
            color: #495057;
        }
        
        /* Estilos para exportação PDF */
        @media print {
            .modal {
                display: none !important;
            }
            
            .sidebar {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
            
            .dashboard-actions {
                display: none !important;
            }
            
            .btn-restore-layout,
            .btn-toggle-layout,
            .btn-help {
                display: none !important;
            }
        }
        
        /* Estilos para melhorar a captura de tela */
        .exporting {
            overflow: visible !important;
        }
        
        .exporting .modal {
            display: none !important;
        }
        
        .exporting .sidebar {
            display: none !important;
        }
        
        .exporting .main-content {
            margin-left: 0 !important;
            width: 100% !important;
        }
        
        .exporting .dashboard-actions {
            display: none !important;
        }
        .modal .close-modal {
            background: transparent;
            border: none;
            padding: 0;
            margin: 0;
            font: inherit;
            line-height: 1;
            cursor: pointer;
            color: inherit;
        }

        /* Estilos para Modais */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-content {
            background: var(--bg-primary);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            color: var(--text-primary);
            font-size: 1.5em;
        }
        
        .close-modal {
            font-size: 28px;
            font-weight: bold;
            color: var(--text-secondary);
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .close-modal:hover {
            color: var(--text-primary);
        }
        
        .modal-body {
            padding: 20px;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .help-section {
            margin-bottom: 25px;
        }
        
        .help-section h3 {
            color: var(--primary-color);
            margin-bottom: 10px;
            font-size: 1.2em;
        }
        
        .help-section ul, .help-section ol {
            margin-left: 20px;
            line-height: 1.8;
        }
        
        .help-section li {
            margin-bottom: 8px;
            color: var(--text-secondary);
        }
        
        .help-section strong {
            color: var(--text-primary);
        }
        
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 20px;
            }
        }
        /* Estilos para Análises Detalhadas */
        .analysis-section {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .analysis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .analysis-card {
            background: var(--bg-primary);
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid var(--accent-primary);
        }
        
        .analysis-card h4 {
            margin: 0 0 10px 0;
            color: var(--text-primary);
            font-size: 1rem;
        }
        
        .ranking-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .ranking-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin-bottom: 8px;
            background: var(--bg-secondary);
            border-radius: 6px;
        }
        
        .ranking-position {
            font-weight: 700;
            color: var(--accent-primary);
            margin-right: 10px;
        }
        
        .ranking-item {
            padding: 15px !important;
        }
        
        .ranking-item small {
            line-height: 1.6;
        }
        
        /* Melhorar legibilidade do metric-trend */
        .metric-trend {
            font-weight: 600;
            font-size: 0.9rem;
            margin-top: 8px;
        }
        
        .metric-trend.positive {
            color: #218838 !important;
        }
        
        .metric-trend.negative {
            color: #dc3545 !important;
        }
        
        .metric-trend.info {
            color: #6c757d !important;
        }
        
        /* Específico para o card de Lucro Líquido com fundo verde claro */
        .dashboard-card[style*="background: #e6f9ed"] .metric-trend {
            color: #155724 !important;
            font-weight: 700;
        }

        /* Reordenar blocos da página (sempre ativo — moderno e clássico) */
        .lucra-dashboard-stack {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }
        .lucra-dash-block {
            position: relative;
        }
        .lucra-dash-block__body {
            position: relative;
        }
        /* Sortable.js — mesmo padrão do index.php (dashboard) */
        .sortable-ghost {
            opacity: 0.45;
        }
        #lucraKpiGrid .dashboard-card {
            cursor: grab;
        }
        #lucraKpiGrid .dashboard-card:active {
            cursor: grabbing;
        }
        .lucra-dash-block {
            cursor: grab;
        }
        .lucra-dash-block:active {
            cursor: grabbing;
        }

        <?php if ($is_modern): ?>
        body.lucratividade-modern .dashboard-content.fornc-page {
            padding-top: 8px;
        }
        body.lucratividade-modern .dashboard-header {
            display: none;
        }
        body.lucratividade-modern .dashboard-grid {
            gap: 0.65rem;
        }
        body.lucratividade-modern .sortable-ghost {
            opacity: 0.45;
        }
        body.lucratividade-modern .dashboard-card {
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--border-color);
        }
        body.lucratividade-modern .analytics-section {
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }
        body.lucratividade-modern .analytics-card {
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        body.lucratividade-modern .card.mb-4.fornc-resumo-shell {
            border: 1px solid var(--border-color);
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
            background: var(--card-bg, var(--bg-secondary));
        }
        body.lucratividade-modern .fornc-table tbody td {
            text-transform: none;
            letter-spacing: normal;
        }
        body.lucratividade-modern .fornc-table tr.table-success td {
            background: rgba(25, 135, 84, 0.1);
            font-weight: 600;
        }
        body.lucratividade-modern .fornc-table tr.table-primary td {
            background: rgba(13, 110, 253, 0.08);
            font-weight: 600;
        }
        body.lucratividade-modern .exporting .fornc-toolbar,
        body.lucratividade-modern .exporting .fornc-kpi-strip,
        body.lucratividade-modern .exporting .lucra-period-nav {
            display: none !important;
        }
        @media print {
            body.lucratividade-modern .fornc-toolbar,
            body.lucratividade-modern .fornc-kpi-strip,
            body.lucratividade-modern .lucra-period-nav {
                display: none !important;
            }
        }
        body.lucratividade-modern .lucra-period-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            align-items: center;
        }
        body.lucratividade-modern .dashboard-card--highlight {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.12), rgba(16, 185, 129, 0.04)) !important;
            border: 1px solid rgba(16, 185, 129, 0.45) !important;
        }
        body.lucratividade-modern .dashboard-card--highlight .card-header h3 {
            color: #15803d;
        }
        body.lucratividade-modern #lucraKpiGrid .dashboard-card .card-header {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            min-width: 0;
        }
        body.lucratividade-modern #lucraKpiGrid .dashboard-card .card-header .kpi-card-icon {
            flex-shrink: 0;
            font-size: 0.85rem;
            color: var(--accent-primary);
            opacity: 0.92;
        }
        body.lucratividade-modern .dashboard-card--highlight .card-header .kpi-card-icon {
            color: #15803d;
        }
        <?php endif; ?>
    </style>
    <?php sf_page_help_css('..'); ?>
</head>
<body class="<?php echo $is_modern ? 'lucratividade-modern' : ''; ?>">
    <?php include '../includes/sidebar_pages.php'; ?>
    <div class="main-content">
        <?php include '../includes/header.php'; ?>
        <div class="dashboard-content<?php echo $is_modern ? ' fornc-page' : ''; ?>">
            <?php if ($is_modern): ?>
            <div class="fornc-kpi-strip" aria-label="Resumo do período">
                <div class="fornc-kpi-cell<?php echo $lucro >= 0 ? ' is-ok' : ' is-warn'; ?>"><div class="fornc-kpi-head"><i class="fas fa-coins fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Lucro líquido</span></div><span class="val">R$ <?php echo number_format($lucro, 2, ',', '.'); ?></span></div>
                <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-truck-moving fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Receita (fretes)</span></div><span class="val">R$ <?php echo number_format($lucra_receita_kpi, 2, ',', '.'); ?></span></div>
                <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-chart-line fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Margem</span></div><span class="val"><?php echo number_format($lucra_margem_kpi, 1, ',', '.'); ?>%</span></div>
                <div class="fornc-kpi-cell"><div class="fornc-kpi-head"><i class="fas fa-ticket fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Ticket médio / rota</span></div><span class="val">R$ <?php echo number_format($lucra_ticket_kpi, 2, ',', '.'); ?></span></div>
            </div>

            <div class="fornc-toolbar">
                <div class="fornc-search-block" style="flex: 1 1 200px;">
                    <label for="lucraMesAnoDisplay">Período analisado</label>
                    <div class="fornc-search-inner">
                        <i class="fas fa-calendar-alt" aria-hidden="true"></i>
                        <input type="text" id="lucraMesAnoDisplay" readonly value="<?php echo htmlspecialchars($lucra_periodo_label); ?>" style="cursor: pointer;" title="Clique para alterar o período" role="button" tabindex="0" aria-label="Abrir filtros de período. Período atual: <?php echo htmlspecialchars($lucra_periodo_label); ?>">
                    </div>
                </div>
                <div class="fornc-btn-row lucra-period-nav" aria-label="Navegar período">
                    <a href="<?php echo htmlspecialchars($lucra_href_prev); ?>" class="fornc-btn fornc-btn--ghost" title="Mês anterior"><i class="fas fa-chevron-left" aria-hidden="true"></i> Anterior</a>
                    <a href="<?php echo htmlspecialchars($lucra_href_next); ?>" class="fornc-btn fornc-btn--ghost" title="Próximo mês">Próximo <i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                    <button type="button" id="filterBtn" class="fornc-btn fornc-btn--accent" title="Filtros">
                        <i class="fas fa-filter" aria-hidden="true"></i> Filtros
                    </button>
                    <button type="button" id="exportBtn" class="fornc-btn fornc-btn--muted" title="Exportar">
                        <i class="fas fa-file-export" aria-hidden="true"></i> Exportar
                    </button>
                    <button type="button" id="helpBtn" class="fornc-btn fornc-btn--ghost" title="Ajuda">
                        <i class="fas fa-question-circle" aria-hidden="true"></i> Ajuda
                    </button>
                </div>
            </div>
            <?php else: ?>
            <div class="dashboard-header">
                <h1 class="mb-0"><i class="fas fa-chart-line"></i> <?php echo $page_title; ?>
                    <a href="lucratividade.php?<?php echo htmlspecialchars($lucra_query_modern_only); ?>" class="btn btn-sm btn-outline-secondary ms-2" style="font-size:0.75rem;vertical-align:middle;">Layout moderno</a>
                </h1>
                <div class="dashboard-actions">
                    <div class="view-controls">
                        <button id="filterBtn" class="btn-restore-layout" title="Filtros">
                            <i class="fas fa-filter"></i>
                        </button>
                        <button id="exportBtn" class="btn-toggle-layout" title="Exportar">
                            <i class="fas fa-file-export"></i>
                        </button>
                        <button id="helpBtn" class="btn-help" title="Ajuda">
                            <i class="fas fa-question-circle"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div id="sfLucraInsights" class="lucra-dash-block" data-lucra-block="insights"></div>

            <div id="lucraDashboardStack" class="lucra-dashboard-stack">
            <?php if (!empty($alerts)): ?>
            <div class="lucra-dash-block" data-lucra-block="alerts">
                <div class="lucra-dash-block__body">
                    <div class="alerts-section mb-4">
                        <div class="alerts-grid">
                            <?php foreach ($alerts as $alert): ?>
                            <div class="alert-card alert-<?php echo $alert['type']; ?>">
                                <div class="alert-icon">
                                    <i class="<?php echo $alert['icon']; ?>"></i>
                                </div>
                                <div class="alert-content">
                                    <h4><?php echo htmlspecialchars($alert['title']); ?></h4>
                                    <p><?php echo htmlspecialchars($alert['message']); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Dashboard KPIs -->
            <div class="lucra-dash-block" data-lucra-block="kpis">
                <div class="lucra-dash-block__body">
            <div class="dashboard-grid mb-4" id="lucraKpiGrid">
                <div class="dashboard-card<?php echo $is_modern ? ' dashboard-card--highlight' : ''; ?>" data-card-id="lucra-kpi-lucro"<?php echo $is_modern ? '' : ' style="background: #e6f9ed; border: 2px solid #2ecc40;"'; ?>>
                    <div class="card-header">
                        <i class="fas fa-chart-line kpi-card-icon" aria-hidden="true"></i>
                        <h3<?php echo $is_modern ? '' : ' style="color: #218838;"'; ?>>Lucro Líquido</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value" style="<?php echo $is_modern ? 'color: #15803d; font-size: 2rem; font-weight: bold;' : 'color: #218838; font-size: 2rem; font-weight: bold;'; ?>">R$ <?= number_format($lucro, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Lucro do período</span>
                            <?php if (isset($crescimento_lucro)): ?>
                            <div class="metric-trend <?= $crescimento_lucro >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $crescimento_lucro >= 0 ? 'up' : 'down' ?>"></i>
                                <?= $crescimento_lucro >= 0 ? '+' : '' ?><?= number_format($crescimento_lucro, 1) ?>% vs mês anterior
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card" data-card-id="lucra-kpi-frete">
                    <div class="card-header">
                        <i class="fas fa-sack-dollar kpi-card-icon" aria-hidden="true"></i>
                        <h3>Total de Fretes</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value">R$ <?= number_format(isset($kpis['total_frete']) && $kpis['total_frete'] !== null ? $kpis['total_frete'] : 0, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Receita bruta</span>
                            <?php if (isset($crescimento_receita)): ?>
                            <div class="metric-trend <?= $crescimento_receita >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $crescimento_receita >= 0 ? 'up' : 'down' ?>"></i>
                                <?= $crescimento_receita >= 0 ? '+' : '' ?><?= number_format($crescimento_receita, 1) ?>% vs mês anterior
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card" data-card-id="lucra-kpi-comissao">
                    <div class="card-header">
                        <i class="fas fa-hand-holding-dollar kpi-card-icon" aria-hidden="true"></i>
                        <h3>Total de Comissões</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value">R$ <?= number_format(isset($kpis['total_comissao']) && $kpis['total_comissao'] !== null ? $kpis['total_comissao'] : 0, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Pagas a motoristas</span>
                            <?php if (isset($tendencias['total_comissao'])): ?>
                            <div class="metric-trend <?= $tendencias['total_comissao'] >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias['total_comissao'] >= 0 ? 'up' : 'down' ?>"></i>
                                <?= $tendencias['total_comissao'] >= 0 ? '+' : '' ?><?= number_format($tendencias['total_comissao'], 1) ?>% vs mês anterior
                        </div>
                            <?php else: ?>
                            <div class="metric-trend info">
                                <i class="fas fa-minus"></i> N/A
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card" data-card-id="lucra-kpi-desp-viagem">
                    <div class="card-header">
                        <i class="fas fa-suitcase-rolling kpi-card-icon" aria-hidden="true"></i>
                        <h3>Despesas de Viagem</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value">R$ <?= number_format(isset($kpis['total_despesas_viagem']) && $kpis['total_despesas_viagem'] !== null ? $kpis['total_despesas_viagem'] : 0, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Custos variáveis</span>
                            <?php if (isset($tendencias['total_despesas_viagem'])): ?>
                            <div class="metric-trend <?= $tendencias['total_despesas_viagem'] <= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias['total_despesas_viagem'] <= 0 ? 'down' : 'up' ?>"></i>
                                <?= $tendencias['total_despesas_viagem'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_despesas_viagem'], 1) ?>% vs mês anterior
                        </div>
                            <?php else: ?>
                            <div class="metric-trend info">
                                <i class="fas fa-minus"></i> N/A
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card" data-card-id="lucra-kpi-abast">
                    <div class="card-header">
                        <i class="fas fa-gas-pump kpi-card-icon" aria-hidden="true"></i>
                        <h3>Abastecimentos</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value">R$ <?= number_format(isset($kpis['total_abastecimentos']) && $kpis['total_abastecimentos'] !== null ? $kpis['total_abastecimentos'] : 0, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Combustível</span>
                            <?php if (isset($variacao_combustivel)): ?>
                            <div class="metric-trend <?= $variacao_combustivel <= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $variacao_combustivel <= 0 ? 'down' : 'up' ?>"></i>
                                <?= $variacao_combustivel <= 0 ? '+' : '' ?><?= number_format($variacao_combustivel, 1) ?>% vs mês anterior
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card" data-card-id="lucra-kpi-fixas">
                    <div class="card-header">
                        <i class="fas fa-file-invoice-dollar kpi-card-icon" aria-hidden="true"></i>
                        <h3>Despesas Fixas</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value">R$ <?= number_format(isset($kpis['total_despesas_fixas']) && $kpis['total_despesas_fixas'] !== null ? $kpis['total_despesas_fixas'] : 0, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Pagas no mês</span>
                            <?php if (isset($tendencias['total_despesas_fixas'])): ?>
                            <div class="metric-trend <?= $tendencias['total_despesas_fixas'] <= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias['total_despesas_fixas'] <= 0 ? 'down' : 'up' ?>"></i>
                                <?= $tendencias['total_despesas_fixas'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_despesas_fixas'], 1) ?>% vs mês anterior
                        </div>
                            <?php else: ?>
                            <div class="metric-trend info">
                                <i class="fas fa-minus"></i> N/A
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card" data-card-id="lucra-kpi-fin">
                    <div class="card-header">
                        <i class="fas fa-landmark kpi-card-icon" aria-hidden="true"></i>
                        <h3>Parcelas de Financiamento</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value">R$ <?= number_format(isset($kpis['total_parcelas_financiamento']) && $kpis['total_parcelas_financiamento'] !== null ? $kpis['total_parcelas_financiamento'] : 0, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Pagas no mês</span>
                            <?php if (isset($tendencias['total_parcelas_financiamento'])): ?>
                            <div class="metric-trend <?= $tendencias['total_parcelas_financiamento'] <= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias['total_parcelas_financiamento'] <= 0 ? 'down' : 'up' ?>"></i>
                                <?= $tendencias['total_parcelas_financiamento'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_parcelas_financiamento'], 1) ?>% vs mês anterior
                        </div>
                            <?php else: ?>
                            <div class="metric-trend info">
                                <i class="fas fa-minus"></i> N/A
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card" data-card-id="lucra-kpi-contas">
                    <div class="card-header">
                        <i class="fas fa-money-check-dollar kpi-card-icon" aria-hidden="true"></i>
                        <h3>Contas Pagas</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value">R$ <?= number_format(isset($kpis['total_contas_pagas']) && $kpis['total_contas_pagas'] !== null ? $kpis['total_contas_pagas'] : 0, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Outros pagamentos</span>
                            <?php if (isset($tendencias['total_contas_pagas'])): ?>
                            <div class="metric-trend <?= $tendencias['total_contas_pagas'] <= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias['total_contas_pagas'] <= 0 ? 'down' : 'up' ?>"></i>
                                <?= $tendencias['total_contas_pagas'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_contas_pagas'], 1) ?>% vs mês anterior
                        </div>
                            <?php else: ?>
                            <div class="metric-trend info">
                                <i class="fas fa-minus"></i> N/A
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card" data-card-id="lucra-kpi-manut">
                    <div class="card-header">
                        <i class="fas fa-screwdriver-wrench kpi-card-icon" aria-hidden="true"></i>
                        <h3>Manutenções de Veículos</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value">R$ <?= number_format(isset($kpis['total_manutencoes']) && $kpis['total_manutencoes'] !== null ? $kpis['total_manutencoes'] : 0, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Veículos</span>
                            <?php if (isset($tendencias['total_manutencoes'])): ?>
                            <div class="metric-trend <?= $tendencias['total_manutencoes'] <= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias['total_manutencoes'] <= 0 ? 'down' : 'up' ?>"></i>
                                <?= $tendencias['total_manutencoes'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_manutencoes'], 1) ?>% vs mês anterior
                        </div>
                            <?php else: ?>
                            <div class="metric-trend info">
                                <i class="fas fa-minus"></i> N/A
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card" data-card-id="lucra-kpi-pneu">
                    <div class="card-header">
                        <i class="fas fa-circle-dot kpi-card-icon" aria-hidden="true"></i>
                        <h3>Manutenções de Pneus</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value">R$ <?= number_format(isset($kpis['total_pneu_manutencao']) && $kpis['total_pneu_manutencao'] !== null ? $kpis['total_pneu_manutencao'] : 0, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Pneus</span>
                            <?php if (isset($tendencias['total_pneu_manutencao'])): ?>
                            <div class="metric-trend <?= $tendencias['total_pneu_manutencao'] <= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias['total_pneu_manutencao'] <= 0 ? 'down' : 'up' ?>"></i>
                                <?= $tendencias['total_pneu_manutencao'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_pneu_manutencao'], 1) ?>% vs mês anterior
                        </div>
                            <?php else: ?>
                            <div class="metric-trend info">
                                <i class="fas fa-minus"></i> N/A
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Novos KPIs Avançados -->
                <div class="dashboard-card" data-card-id="lucra-kpi-roi" style="border-left: 4px solid var(--accent-secondary);">
                    <div class="card-header">
                        <i class="fas fa-chart-pie kpi-card-icon" aria-hidden="true"></i>
                        <h3>ROI (Retorno sobre Investimento)</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value" style="color: var(--accent-secondary); font-size: 2rem; font-weight: bold;"><?= number_format($advanced_kpis['roi'] ?? 0, 2, ',', '.') ?>%</span>
                            <span class="metric-subtitle">Taxa de retorno</span>
                            <?php if (isset($tendencias_advanced['roi'])): ?>
                            <div class="metric-trend <?= $tendencias_advanced['roi'] >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias_advanced['roi'] >= 0 ? 'up' : 'down' ?>"></i>
                                <?= $tendencias_advanced['roi'] >= 0 ? '+' : '' ?><?= number_format($tendencias_advanced['roi'], 1) ?>% vs mês anterior
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card" data-card-id="lucra-kpi-ticket" style="border-left: 4px solid var(--accent-primary);">
                    <div class="card-header">
                        <i class="fas fa-receipt kpi-card-icon" aria-hidden="true"></i>
                        <h3>Ticket Médio por Rota</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value" style="font-size: 2rem; font-weight: bold;">R$ <?= number_format($advanced_kpis['ticket_medio'] ?? 0, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Média por rota</span>
                            <?php if (isset($tendencias_advanced['ticket_medio'])): ?>
                            <div class="metric-trend <?= $tendencias_advanced['ticket_medio'] >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias_advanced['ticket_medio'] >= 0 ? 'up' : 'down' ?>"></i>
                                <?= $tendencias_advanced['ticket_medio'] >= 0 ? '+' : '' ?><?= number_format($tendencias_advanced['ticket_medio'], 1) ?>% vs mês anterior
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card" data-card-id="lucra-kpi-custo-km" style="border-left: 4px solid var(--accent-warning);">
                    <div class="card-header">
                        <i class="fas fa-road kpi-card-icon" aria-hidden="true"></i>
                        <h3>Custo por Quilômetro</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value" style="color: var(--accent-warning); font-size: 2rem; font-weight: bold;">R$ <?= number_format($advanced_kpis['custo_km'] ?? 0, 2, ',', '.') ?></span>
                            <span class="metric-subtitle">Por km rodado</span>
                            <?php if (isset($tendencias_advanced['custo_km'])): ?>
                            <div class="metric-trend <?= $tendencias_advanced['custo_km'] <= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias_advanced['custo_km'] <= 0 ? 'down' : 'up' ?>"></i>
                                <?= $tendencias_advanced['custo_km'] <= 0 ? '+' : '' ?><?= number_format($tendencias_advanced['custo_km'], 1) ?>% vs mês anterior
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card" data-card-id="lucra-kpi-margem" style="border-left: 4px solid var(--accent-success);">
                    <div class="card-header">
                        <i class="fas fa-percent kpi-card-icon" aria-hidden="true"></i>
                        <h3>Margem Operacional</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value" style="color: var(--accent-success); font-size: 2rem; font-weight: bold;"><?= number_format($advanced_kpis['margem_operacional'] ?? 0, 2, ',', '.') ?>%</span>
                            <span class="metric-subtitle">Margem líquida</span>
                            <?php if (isset($tendencias_advanced['margem_operacional'])): ?>
                            <div class="metric-trend <?= $tendencias_advanced['margem_operacional'] >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias_advanced['margem_operacional'] >= 0 ? 'up' : 'down' ?>"></i>
                                <?= $tendencias_advanced['margem_operacional'] >= 0 ? '+' : '' ?><?= number_format($tendencias_advanced['margem_operacional'], 1) ?>% vs mês anterior
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card" data-card-id="lucra-kpi-ocupacao" style="border-left: 4px solid var(--accent-secondary);">
                    <div class="card-header">
                        <i class="fas fa-box kpi-card-icon" aria-hidden="true"></i>
                        <h3>Taxa de Ocupação</h3>
                    </div>
                    <div class="card-body">
                        <div class="metric">
                            <span class="metric-value" style="color: var(--accent-secondary); font-size: 2rem; font-weight: bold;"><?= number_format($advanced_kpis['taxa_ocupacao'] ?? 0, 2, ',', '.') ?>%</span>
                            <span class="metric-subtitle">Rotas com carga</span>
                            <?php if (isset($tendencias_advanced['taxa_ocupacao'])): ?>
                            <div class="metric-trend <?= $tendencias_advanced['taxa_ocupacao'] >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-arrow-<?= $tendencias_advanced['taxa_ocupacao'] >= 0 ? 'up' : 'down' ?>"></i>
                                <?= $tendencias_advanced['taxa_ocupacao'] >= 0 ? '+' : '' ?><?= number_format($tendencias_advanced['taxa_ocupacao'], 1) ?>% vs mês anterior
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
                </div>
            </div>
            
            <!-- Análises Detalhadas -->
            <div class="lucra-dash-block" data-lucra-block="analysis">
                <div class="lucra-dash-block__body">
            <div class="analysis-section">
                <h2 style="margin-bottom: 20px; color: var(--text-primary);">
                    <i class="fas fa-chart-bar"></i> Análises detalhadas
                </h2>
                
                <div class="analysis-grid">
                    <!-- Top 5 Veículos -->
                    <?php if (!empty($rankings['veiculos'])): ?>
                    <div class="analysis-card">
                        <h4><i class="fas fa-truck"></i> Top 5 Veículos Mais Rentáveis</h4>
                        <ul class="ranking-list">
                            <?php foreach ($rankings['veiculos'] as $index => $veiculo): 
                                $receita = floatval($veiculo['receita'] ?? 0);
                                $comissao = floatval($veiculo['comissao'] ?? 0);
                                $custo_abastecimento = floatval($veiculo['custo_abastecimento'] ?? 0);
                                $despesas_viagem = floatval($veiculo['despesas_viagem'] ?? 0);
                                $custo_manutencao = floatval($veiculo['custo_manutencao'] ?? 0);
                                $lucro = floatval($veiculo['lucro'] ?? 0);
                                $total_custos = $comissao + $custo_abastecimento + $despesas_viagem + $custo_manutencao;
                            ?>
                            <li class="ranking-item">
                                <div style="flex: 1;">
                                    <div>
                                        <span class="ranking-position"><?= $index + 1 ?>º</span>
                                        <span><strong><?= htmlspecialchars($veiculo['placa']) ?> - <?= htmlspecialchars($veiculo['modelo']) ?></strong></span>
                                    </div>
                                    <div style="margin-top: 8px; font-size: 0.85rem; color: var(--text-secondary);">
                                        <div>Receita: <strong style="color: var(--accent-primary);">R$ <?= number_format($receita, 2, ',', '.') ?></strong></div>
                                        <div style="margin-top: 4px;">Custos: 
                                            <span style="color: var(--accent-danger);">R$ <?= number_format($total_custos, 2, ',', '.') ?></span>
                                            <small style="display: block; margin-left: 20px; margin-top: 2px;">
                                                • Comissões: R$ <?= number_format($comissao, 2, ',', '.') ?><br>
                                                • Abastecimentos: R$ <?= number_format($custo_abastecimento, 2, ',', '.') ?><br>
                                                • Despesas Viagem: R$ <?= number_format($despesas_viagem, 2, ',', '.') ?><br>
                                                • Manutenções: R$ <?= number_format($custo_manutencao, 2, ',', '.') ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div style="text-align: right; margin-left: 15px;">
                                    <strong style="color: <?= $lucro >= 0 ? 'var(--accent-success)' : 'var(--accent-danger)' ?>; font-size: 1.1rem;">R$ <?= number_format($lucro, 2, ',', '.') ?></strong>
                                    <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px;">
                                        Lucro Líquido
                                    </div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Top 5 Motoristas -->
                    <?php if (!empty($rankings['motoristas'])): ?>
                    <div class="analysis-card">
                        <h4><i class="fas fa-user"></i> Top 5 Motoristas Mais Rentáveis</h4>
                        <ul class="ranking-list">
                            <?php foreach ($rankings['motoristas'] as $index => $motorista): 
                                $receita = floatval($motorista['receita'] ?? 0);
                                $comissao = floatval($motorista['comissao'] ?? 0);
                                $despesas_viagem = floatval($motorista['despesas_viagem'] ?? 0);
                                $lucro = floatval($motorista['lucro'] ?? 0);
                                $total_custos = $comissao + $despesas_viagem;
                            ?>
                            <li class="ranking-item">
                                <div style="flex: 1;">
                                    <div>
                                        <span class="ranking-position"><?= $index + 1 ?>º</span>
                                        <span><strong><?= htmlspecialchars($motorista['nome']) ?></strong></span>
                                    </div>
                                    <div style="margin-top: 8px; font-size: 0.85rem; color: var(--text-secondary);">
                                        <div>Receita: <strong style="color: var(--accent-primary);">R$ <?= number_format($receita, 2, ',', '.') ?></strong></div>
                                        <div style="margin-top: 4px;">Custos: 
                                            <span style="color: var(--accent-danger);">R$ <?= number_format($total_custos, 2, ',', '.') ?></span>
                                            <small style="display: block; margin-left: 20px; margin-top: 2px;">
                                                • Comissões: R$ <?= number_format($comissao, 2, ',', '.') ?><br>
                                                • Despesas Viagem: R$ <?= number_format($despesas_viagem, 2, ',', '.') ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div style="text-align: right; margin-left: 15px;">
                                    <strong style="color: <?= $lucro >= 0 ? 'var(--accent-success)' : 'var(--accent-danger)' ?>; font-size: 1.1rem;">R$ <?= number_format($lucro, 2, ',', '.') ?></strong>
                                    <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px;">
                                        Lucro Líquido
                                    </div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Lucratividade por Tipo de Frete -->
                    <?php if (!empty($rankings['tipos_frete'])): ?>
                    <div class="analysis-card">
                        <h4><i class="fas fa-route"></i> Lucratividade por Tipo de Frete</h4>
                        <ul class="ranking-list">
                            <?php foreach ($rankings['tipos_frete'] as $tipo): ?>
                            <li class="ranking-item">
                                <div>
                                    <span><?= htmlspecialchars($tipo['tipo_frete']) ?></span>
                                    <br>
                                    <small style="color: var(--text-secondary); font-size: 0.85rem;">Receita: R$ <?= number_format($tipo['receita'] ?? 0, 2, ',', '.') ?></small>
                                </div>
                                <div>
                                    <strong style="color: var(--accent-success);">R$ <?= number_format($tipo['lucro'] ?? 0, 2, ',', '.') ?></strong>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (empty($rankings['veiculos']) && empty($rankings['motoristas']) && empty($rankings['tipos_frete'])): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: var(--text-secondary);">
                        <i class="fas fa-info-circle" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                        <p>Não há dados suficientes para exibir análises detalhadas no período selecionado.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
                </div>
            </div>
            
            <!-- Resumo Financeiro -->
            <div class="lucra-dash-block" data-lucra-block="resumo">
                <div class="lucra-dash-block__body">
            <div class="card mb-4<?php echo $is_modern ? ' fornc-resumo-shell' : ''; ?>">
                <div class="card-body">
                    <h4 class="card-title mb-3"><i class="fas fa-table"></i> Resumo Financeiro</h4>
                    <div class="<?php echo $is_modern ? 'fornc-table-wrap' : 'table-responsive'; ?>">
                        <table class="<?php echo $is_modern ? 'fornc-table' : 'table table-bordered'; ?>">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-end">Valor</th>
                                    <th class="text-center">% da Receita</th>
                                    <th class="text-center">Tendência</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $receita_total = isset($kpis['total_frete']) ? $kpis['total_frete'] : 0;
                                $percentual_frete = $receita_total > 0 ? 100 : 0;
                                $percentual_comissao = $receita_total > 0 ? ($kpis['total_comissao'] / $receita_total) * 100 : 0;
                                $percentual_abastecimento = $receita_total > 0 ? ($kpis['total_abastecimentos'] / $receita_total) * 100 : 0;
                                $percentual_despesas_viagem = $receita_total > 0 ? ($kpis['total_despesas_viagem'] / $receita_total) * 100 : 0;
                                $percentual_despesas_fixas = $receita_total > 0 ? ($kpis['total_despesas_fixas'] / $receita_total) * 100 : 0;
                                $percentual_manutencoes = $receita_total > 0 ? ($kpis['total_manutencoes'] / $receita_total) * 100 : 0;
                                $percentual_lucro = $receita_total > 0 ? ($lucro / $receita_total) * 100 : 0;
                                ?>
                                <tr class="table-success">
                                    <td><strong>Total de Fretes</strong></td>
                                    <td class="text-end"><strong>R$ <?= number_format(isset($kpis['total_frete']) && $kpis['total_frete'] !== null ? $kpis['total_frete'] : 0, 2, ',', '.') ?></strong></td>
                                    <td class="text-center"><strong><?= number_format($percentual_frete, 1) ?>%</strong></td>
                                    <td class="text-center">
                                        <?php if (isset($tendencias['total_frete'])): ?>
                                            <span class="trend-badge <?= $tendencias['total_frete'] >= 0 ? 'trend-up' : 'trend-down' ?>">
                                                <i class="fas fa-arrow-<?= $tendencias['total_frete'] >= 0 ? 'up' : 'down' ?>"></i>
                                                <?= $tendencias['total_frete'] >= 0 ? '+' : '' ?><?= number_format($tendencias['total_frete'], 1) ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="trend-badge trend-neutral">
                                                <i class="fas fa-minus"></i> N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Total de Comissões</td>
                                    <td class="text-end">R$ <?= number_format(isset($kpis['total_comissao']) && $kpis['total_comissao'] !== null ? $kpis['total_comissao'] : 0, 2, ',', '.') ?></td>
                                    <td class="text-center"><?= number_format($percentual_comissao, 1) ?>%</td>
                                    <td class="text-center">
                                        <?php if (isset($tendencias['total_comissao'])): ?>
                                            <span class="trend-badge <?= $tendencias['total_comissao'] >= 0 ? 'trend-up' : 'trend-down' ?>">
                                                <i class="fas fa-arrow-<?= $tendencias['total_comissao'] >= 0 ? 'up' : 'down' ?>"></i>
                                                <?= $tendencias['total_comissao'] >= 0 ? '+' : '' ?><?= number_format($tendencias['total_comissao'], 1) ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="trend-badge trend-neutral">
                                                <i class="fas fa-minus"></i> N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Despesas de Viagem</td>
                                    <td class="text-end">R$ <?= number_format(isset($kpis['total_despesas_viagem']) && $kpis['total_despesas_viagem'] !== null ? $kpis['total_despesas_viagem'] : 0, 2, ',', '.') ?></td>
                                    <td class="text-center"><?= number_format($percentual_despesas_viagem, 1) ?>%</td>
                                    <td class="text-center">
                                        <?php if (isset($tendencias['total_despesas_viagem'])): ?>
                                            <span class="trend-badge <?= $tendencias['total_despesas_viagem'] <= 0 ? 'trend-up' : 'trend-down' ?>">
                                                <i class="fas fa-arrow-<?= $tendencias['total_despesas_viagem'] <= 0 ? 'down' : 'up' ?>"></i>
                                                <?= $tendencias['total_despesas_viagem'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_despesas_viagem'], 1) ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="trend-badge trend-neutral">
                                                <i class="fas fa-minus"></i> N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Abastecimentos</td>
                                    <td class="text-end">R$ <?= number_format(isset($kpis['total_abastecimentos']) && $kpis['total_abastecimentos'] !== null ? $kpis['total_abastecimentos'] : 0, 2, ',', '.') ?></td>
                                    <td class="text-center"><?= number_format($percentual_abastecimento, 1) ?>%</td>
                                    <td class="text-center">
                                        <?php if (isset($tendencias['total_abastecimentos'])): ?>
                                            <span class="trend-badge <?= $tendencias['total_abastecimentos'] <= 0 ? 'trend-up' : 'trend-down' ?>">
                                                <i class="fas fa-arrow-<?= $tendencias['total_abastecimentos'] <= 0 ? 'down' : 'up' ?>"></i>
                                                <?= $tendencias['total_abastecimentos'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_abastecimentos'], 1) ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="trend-badge trend-neutral">
                                                <i class="fas fa-minus"></i> N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Despesas Fixas</td>
                                    <td class="text-end">R$ <?= number_format(isset($kpis['total_despesas_fixas']) && $kpis['total_despesas_fixas'] !== null ? $kpis['total_despesas_fixas'] : 0, 2, ',', '.') ?></td>
                                    <td class="text-center"><?= number_format($percentual_despesas_fixas, 1) ?>%</td>
                                    <td class="text-center">
                                        <?php if (isset($tendencias['total_despesas_fixas'])): ?>
                                            <span class="trend-badge <?= $tendencias['total_despesas_fixas'] <= 0 ? 'trend-up' : 'trend-down' ?>">
                                                <i class="fas fa-arrow-<?= $tendencias['total_despesas_fixas'] <= 0 ? 'down' : 'up' ?>"></i>
                                                <?= $tendencias['total_despesas_fixas'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_despesas_fixas'], 1) ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="trend-badge trend-neutral">
                                                <i class="fas fa-minus"></i> N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Parcelas de Financiamento</td>
                                    <td class="text-end">R$ <?= number_format(isset($kpis['total_parcelas_financiamento']) && $kpis['total_parcelas_financiamento'] !== null ? $kpis['total_parcelas_financiamento'] : 0, 2, ',', '.') ?></td>
                                    <td class="text-center"><?= $receita_total > 0 ? number_format(($kpis['total_parcelas_financiamento'] / $receita_total) * 100, 1) : 0 ?>%</td>
                                    <td class="text-center">
                                        <?php if (isset($tendencias['total_parcelas_financiamento'])): ?>
                                            <span class="trend-badge <?= $tendencias['total_parcelas_financiamento'] <= 0 ? 'trend-up' : 'trend-down' ?>">
                                                <i class="fas fa-arrow-<?= $tendencias['total_parcelas_financiamento'] <= 0 ? 'down' : 'up' ?>"></i>
                                                <?= $tendencias['total_parcelas_financiamento'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_parcelas_financiamento'], 1) ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="trend-badge trend-neutral">
                                                <i class="fas fa-minus"></i> N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Contas Pagas</td>
                                    <td class="text-end">R$ <?= number_format(isset($kpis['total_contas_pagas']) && $kpis['total_contas_pagas'] !== null ? $kpis['total_contas_pagas'] : 0, 2, ',', '.') ?></td>
                                    <td class="text-center"><?= $receita_total > 0 ? number_format(($kpis['total_contas_pagas'] / $receita_total) * 100, 1) : 0 ?>%</td>
                                    <td class="text-center">
                                        <?php if (isset($tendencias['total_contas_pagas'])): ?>
                                            <span class="trend-badge <?= $tendencias['total_contas_pagas'] <= 0 ? 'trend-up' : 'trend-down' ?>">
                                                <i class="fas fa-arrow-<?= $tendencias['total_contas_pagas'] <= 0 ? 'down' : 'up' ?>"></i>
                                                <?= $tendencias['total_contas_pagas'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_contas_pagas'], 1) ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="trend-badge trend-neutral">
                                                <i class="fas fa-minus"></i> N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Manutenções de Veículos</td>
                                    <td class="text-end">R$ <?= number_format(isset($kpis['total_manutencoes']) && $kpis['total_manutencoes'] !== null ? $kpis['total_manutencoes'] : 0, 2, ',', '.') ?></td>
                                    <td class="text-center"><?= number_format($percentual_manutencoes, 1) ?>%</td>
                                    <td class="text-center">
                                        <?php if (isset($tendencias['total_manutencoes'])): ?>
                                            <span class="trend-badge <?= $tendencias['total_manutencoes'] <= 0 ? 'trend-up' : 'trend-down' ?>">
                                                <i class="fas fa-arrow-<?= $tendencias['total_manutencoes'] <= 0 ? 'down' : 'up' ?>"></i>
                                                <?= $tendencias['total_manutencoes'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_manutencoes'], 1) ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="trend-badge trend-neutral">
                                                <i class="fas fa-minus"></i> N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Manutenções de Pneus</td>
                                    <td class="text-end">R$ <?= number_format(isset($kpis['total_pneu_manutencao']) && $kpis['total_pneu_manutencao'] !== null ? $kpis['total_pneu_manutencao'] : 0, 2, ',', '.') ?></td>
                                    <td class="text-center"><?= $receita_total > 0 ? number_format(($kpis['total_pneu_manutencao'] / $receita_total) * 100, 1) : 0 ?>%</td>
                                    <td class="text-center">
                                        <?php if (isset($tendencias['total_pneu_manutencao'])): ?>
                                            <span class="trend-badge <?= $tendencias['total_pneu_manutencao'] <= 0 ? 'trend-up' : 'trend-down' ?>">
                                                <i class="fas fa-arrow-<?= $tendencias['total_pneu_manutencao'] <= 0 ? 'down' : 'up' ?>"></i>
                                                <?= $tendencias['total_pneu_manutencao'] <= 0 ? '+' : '' ?><?= number_format($tendencias['total_pneu_manutencao'], 1) ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="trend-badge trend-neutral">
                                                <i class="fas fa-minus"></i> N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr class="table-primary">
                                    <td><strong>Lucro Líquido</strong></td>
                                    <td class="text-end"><strong>R$ <?= number_format($lucro, 2, ',', '.') ?></strong></td>
                                    <td class="text-center"><strong><?= number_format($percentual_lucro, 1) ?>%</strong></td>
                                    <td class="text-center">
                                        <?php if (isset($crescimento_lucro)): ?>
                                            <span class="trend-badge <?= $crescimento_lucro >= 0 ? 'trend-up' : 'trend-down' ?>">
                                                <i class="fas fa-arrow-<?= $crescimento_lucro >= 0 ? 'up' : 'down' ?>"></i>
                                                <?= $crescimento_lucro >= 0 ? '+' : '' ?><?= number_format($crescimento_lucro, 1) ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="trend-badge trend-neutral">
                                                <i class="fas fa-minus"></i> N/A
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
                </div>
            </div>

            <!-- Gráficos de Análise -->
            <div class="lucra-dash-block" data-lucra-block="charts_main">
                <div class="lucra-dash-block__body">
            <div class="analytics-section">
                <div class="section-header">
                    <h2>Análise de Lucratividade</h2>
                </div>
                <div class="analytics-grid">
                    <div class="analytics-card">
                <div class="card-header">
                            <h3>Custo Médio por KM Rodado</h3>
                </div>
                <div class="card-body">
                        <canvas id="costPerKmChart"></canvas>
                </div>
            </div>

                    <div class="analytics-card">
                <div class="card-header">
                            <h3>Eficiência Operacional - Gauge</h3>
                </div>
                <div class="card-body">
                                <canvas id="profitPerKmGauge"></canvas>
                            </div>
                        </div>
                    
                    <div class="analytics-card">
                        <div class="card-header">
                            <h3>Eficiência Operacional - Linha</h3>
                        </div>
                        <div class="card-body">
                                <canvas id="profitPerKmLine"></canvas>
                            </div>
                        </div>

                    <div class="analytics-card">
                <div class="card-header">
                            <h3>Projeção de Lucro</h3>
                </div>
                <div class="card-body">
                        <canvas id="profitForecastChart"></canvas>
                    </div>
                    </div>
                </div>
            </div>
                </div>
            </div>

            <!-- Gráficos de Análise Avançada -->
            <div class="lucra-dash-block" data-lucra-block="charts_advanced">
                <div class="lucra-dash-block__body">
            <div class="analytics-section">
                <div class="section-header">
                    <h2>Análise de Lucratividade Avançada</h2>
                </div>
                <div class="analytics-grid">
                    <div class="analytics-card">
                <div class="card-header">
                            <h3>Evolução da Lucratividade</h3>
                </div>
                <div class="card-body">
                            <canvas id="profitEvolutionChart"></canvas>
                    </div>
                </div>
                    
                    <div class="analytics-card">
                        <div class="card-header">
                            <h3>Distribuição de Custos</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="costDistributionChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="analytics-card">
                        <div class="card-header">
                            <h3>Margem por Tipo de Frete</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="marginByFreightChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="analytics-card">
                        <div class="card-header">
                            <h3>Projeção de Lucro (Próximos 3 meses)</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="profitForecastAdvancedChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
                </div>
            </div>
            
            <!-- Novos Gráficos Avançados -->
            <div class="lucra-dash-block" data-lucra-block="charts_flow">
                <div class="lucra-dash-block__body">
            <div class="analytics-section">
                <div class="section-header">
                    <h2>Análise de Fluxo Financeiro</h2>
                </div>
                <div class="analytics-grid">
                    <!-- Gráfico de Cascata (Waterfall) -->
                    <div class="analytics-card" style="grid-column: 1 / -1;">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-bar"></i> Análise de Fluxo Financeiro (Waterfall)</h3>
                        </div>
                        <div class="card-body" style="position: relative; height: 400px;">
                            <canvas id="waterfallChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Gráfico de Tendência Anual -->
                    <div class="analytics-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-line"></i> Tendência Anual</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Gráfico de Pareto -->
                    <div class="analytics-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-pie"></i> Análise de Pareto (80/20)</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="paretoChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Gráfico de Distribuição Detalhada de Custos -->
                    <div class="analytics-card" style="grid-column: 1 / -1;">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-pie"></i> Distribuição Detalhada de Custos</h3>
                        </div>
                        <div class="card-body" style="position: relative; height: 500px; display: flex; align-items: center; justify-content: center;">
                            <canvas id="costBreakdownChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
                </div>
            </div>
            </div><!-- /#lucraDashboardStack -->

            <!-- Resumo Financeiro Detalhado -->
            <div class="financial-summary mb-4">
                <!-- Seção removida conforme solicitado -->
            </div>
        </div>
        <?php include '../includes/footer.php'; ?>
    </div>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
    window.LUCRA_EMPRESA_ID = <?php echo (int) $empresa_id; ?>;
    (function () {
        document.addEventListener('DOMContentLoaded', function () {
            var emp = window.LUCRA_EMPRESA_ID;

            var stack = document.getElementById('lucraDashboardStack');
            if (stack) {
                var stackKey = 'lucratividade_dash_order_' + emp;
                function getStackBlocks() {
                    var out = [];
                    for (var i = 0; i < stack.children.length; i++) {
                        var el = stack.children[i];
                        if (el.classList && el.classList.contains('lucra-dash-block')) {
                            out.push(el);
                        }
                    }
                    return out;
                }
                function restoreStackOrder() {
                    var raw = localStorage.getItem(stackKey);
                    if (!raw) return;
                    var order;
                    try { order = JSON.parse(raw); } catch (e) { return; }
                    if (!Array.isArray(order)) return;
                    var blocks = getStackBlocks();
                    var map = {};
                    blocks.forEach(function (b) {
                        var id = b.getAttribute('data-lucra-block');
                        if (id) map[id] = b;
                    });
                    var extras = [].slice.call(stack.children).filter(function (el) {
                        return !el.classList.contains('lucra-dash-block');
                    });
                    blocks.forEach(function (b) {
                        if (b.parentNode === stack) stack.removeChild(b);
                    });
                    order.forEach(function (id) {
                        if (map[id]) stack.appendChild(map[id]);
                    });
                    Object.keys(map).forEach(function (id) {
                        if (order.indexOf(id) === -1 && map[id]) stack.appendChild(map[id]);
                    });
                    extras.forEach(function (n) { stack.appendChild(n); });
                }
                function saveStackOrder() {
                    var ids = getStackBlocks().map(function (b) { return b.getAttribute('data-lucra-block'); }).filter(Boolean);
                    try { localStorage.setItem(stackKey, JSON.stringify(ids)); } catch (e) { /* ignore */ }
                }
                restoreStackOrder();
                if (typeof Sortable !== 'undefined') {
                    new Sortable(stack, {
                        animation: 150,
                        ghostClass: 'sortable-ghost',
                        draggable: '.lucra-dash-block',
                        filter: 'input, textarea, button, select, option, a, canvas, label',
                        preventOnFilter: true,
                        onEnd: saveStackOrder
                    });
                }
            }

            var kpiGrid = document.getElementById('lucraKpiGrid');
            if (kpiGrid && typeof Sortable !== 'undefined') {
                var kpiKey = 'lucratividade_kpi_layout_' + emp;
                var savedKpi = localStorage.getItem(kpiKey);
                if (savedKpi) {
                    try {
                        var layout = JSON.parse(savedKpi);
                        if (Array.isArray(layout)) {
                            layout.forEach(function (item) {
                                if (!item || !item.id) return;
                                var card = kpiGrid.querySelector('[data-card-id="' + item.id + '"]');
                                if (card) kpiGrid.appendChild(card);
                            });
                        }
                    } catch (e) { /* ignore */ }
                }
                new Sortable(kpiGrid, {
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    filter: 'input, textarea, button, select, option, a, label',
                    preventOnFilter: true,
                    onEnd: function () {
                        var cards = kpiGrid.querySelectorAll('.dashboard-card');
                        var layout = [];
                        for (var i = 0; i < cards.length; i++) {
                            var id = cards[i].getAttribute('data-card-id');
                            if (id) layout.push({ id: id, order: i });
                        }
                        try { localStorage.setItem(kpiKey, JSON.stringify(layout)); } catch (e) { /* ignore */ }
                    }
                });
            }
        });
    })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    
    <!-- Bibliotecas para exportar PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <!-- Filter Modal -->
    <div class="modal<?php echo $is_modern ? ' fornc-modal' : ''; ?>" id="filterModal" role="dialog" aria-modal="true" aria-labelledby="filterModalTitle" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="filterModalTitle">Filtros</h2>
                <button type="button" class="close-modal" aria-label="Fechar">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="filterMonth">Mês/Ano</label>
                        <input type="month" id="filterMonth" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="clearFilterBtn" class="btn-secondary">Limpar</button>
                <button id="applyFilterBtn" class="btn-primary">Aplicar</button>
            </div>
        </div>
    </div>

    <script>
    const lucraClassic = <?php echo $is_modern ? 'false' : 'true'; ?>;
    const lucraClassicQs = lucraClassic ? '&classic=1' : '';

    function lucraFetchJson(response, chartName) {
        if (!response.ok) {
            throw new Error(chartName + ': HTTP ' + response.status);
        }
        return response.json().then(function(data) {
            if (data && data.success === false) {
                throw new Error(data.message || chartName);
            }
            return data;
        });
    }

    function lucraChartEmpty(el, msg) {
        if (el && el.parentNode) {
            el.parentNode.innerHTML = '<p class="lucra-chart-msg lucra-chart-msg--load" role="status">' + msg + '</p>';
        }
    }

    function lucraOpenModal(el) {
        if (!el) return;
        el.style.display = 'flex';
        el.setAttribute('aria-hidden', 'false');
        el.classList.add('active');
        const focusMonth = el.querySelector('#filterMonth');
        const toFocus = focusMonth || el.querySelector('button, [href], input, select, textarea');
        if (toFocus) {
            try { toFocus.focus(); } catch (e) { /* ignore */ }
        }
    }
    function lucraCloseModal(el) {
        if (!el) return;
        el.style.display = 'none';
        el.setAttribute('aria-hidden', 'true');
        el.classList.remove('active');
    }

    document.addEventListener('DOMContentLoaded', function() {
        const filterBtn = document.getElementById('filterBtn');
        const filterModal = document.getElementById('filterModal');
        const filterMonth = document.getElementById('filterMonth');
        const clearFilterBtn = document.getElementById('clearFilterBtn');
        const applyFilterBtn = document.getElementById('applyFilterBtn');
        const helpBtn = document.getElementById('helpBtn');
        const helpModal = document.getElementById('helpModal');
        void helpBtn;
        void helpModal;
        const lucraPeriodField = document.getElementById('lucraMesAnoDisplay');

        if (filterMonth) {
            const urlParamsInit = new URLSearchParams(window.location.search);
            const mesInit = urlParamsInit.get('mes');
            const anoInit = urlParamsInit.get('ano');
            if (mesInit && anoInit) {
                filterMonth.value = `${anoInit}-${mesInit.padStart(2, '0')}`;
            } else {
                const currentDate = new Date();
                filterMonth.value = `${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}`;
            }
        }

        if (filterBtn && filterModal) {
            filterBtn.addEventListener('click', function() { lucraOpenModal(filterModal); });
        }
        if (lucraPeriodField && filterModal) {
            lucraPeriodField.addEventListener('click', function() { lucraOpenModal(filterModal); });
            lucraPeriodField.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    lucraOpenModal(filterModal);
                }
            });
        }

        document.querySelectorAll('.modal .close-modal').forEach(function(btn) {
            btn.addEventListener('click', function() {
                lucraCloseModal(btn.closest('.modal'));
            });
        });
        document.querySelectorAll('.modal').forEach(function(m) {
            m.addEventListener('click', function(e) {
                if (e.target === m) lucraCloseModal(m);
            });
        });
        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Escape') return;
            document.querySelectorAll('.modal').forEach(function(m) {
                var d = window.getComputedStyle(m).display;
                if (d === 'flex' || d === 'block') lucraCloseModal(m);
            });
        });

        if (clearFilterBtn && filterMonth) {
            clearFilterBtn.addEventListener('click', function() {
                filterMonth.value = '';
                window.location.href = lucraClassic ? (window.location.pathname + '?classic=1') : window.location.pathname;
            });
        }
        if (applyFilterBtn && filterMonth) {
            applyFilterBtn.addEventListener('click', function() {
                var monthYear = filterMonth.value;
                if (monthYear) {
                    var parts = monthYear.split('-');
                    var year = parts[0];
                    var month = parts[1];
                    window.location.href = '?mes=' + month + '&ano=' + year + lucraClassicQs;
                } else {
                    window.location.href = lucraClassic ? (window.location.pathname + '?classic=1') : window.location.pathname;
                }
            });
        }
    });

    // Configuração do gráfico de custo por KM
    document.addEventListener('DOMContentLoaded', function() {
        const costPerKmEl = document.getElementById('costPerKmChart');
        if (!costPerKmEl) return;
        const costPerKmCtx = costPerKmEl.getContext('2d');
        
        // Função para formatar valores em reais
        const formatCurrency = (value) => {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(value);
        };
        
        // Get month and year from URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const mes = urlParams.get('mes');
        const ano = urlParams.get('ano');
        
        // Build API URL with parameters
        let costPerKmUrl = '../api/cost_per_km_analytics.php';
        if (mes && ano) {
            costPerKmUrl += `?mes=${mes}&ano=${ano}`;
        }
        
        // Carregar dados do gráfico de custo por KM
        fetch(costPerKmUrl)
            .then(function(response) { return lucraFetchJson(response, 'Custo por KM'); })
            .then(data => {
                new Chart(costPerKmCtx, {
                    type: 'line',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed.y !== null) {
                                            label += formatCurrency(context.parsed.y);
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return formatCurrency(value);
                                    }
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Erro ao carregar dados do gráfico de custo por KM:', error);
                costPerKmCtx.canvas.parentNode.innerHTML = '<p class="lucra-chart-msg lucra-chart-msg--err" role="alert">Não foi possível carregar o gráfico de custo por KM. Verifique a conexão ou tente outro período.</p>';
            });
    });

    // Configuração do gráfico de eficiência operacional
    document.addEventListener('DOMContentLoaded', function() {
        if (!document.getElementById('profitPerKmGauge') || !document.getElementById('profitPerKmLine')) return;
        // Registrar o plugin datalabels
        Chart.register(ChartDataLabels);
        
        // Função para formatar valores em reais
        const formatCurrency = (value) => {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(value);
        };
        
        // Get month and year from URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const mes = urlParams.get('mes');
        const ano = urlParams.get('ano');
        
        // Build API URL with parameters
        let profitPerKmUrl = '../api/profit_per_km_analytics.php';
        if (mes && ano) {
            profitPerKmUrl += `?mes=${mes}&ano=${ano}`;
        }
        
        // Carregar dados do gráfico de eficiência operacional
        fetch(profitPerKmUrl)
            .then(function(response) { return lucraFetchJson(response, 'Eficiência operacional'); })
            .then(data => {
                // Mostrar aviso se há dados de exemplo
                if (data.warning) {
                    const warningDiv = document.createElement('div');
                    warningDiv.className = 'alert alert-warning';
                    const suggestions = Array.isArray(data.suggestions) ? data.suggestions : [];
                    warningDiv.innerHTML = '<h5><i class="fas fa-exclamation-triangle"></i> ' + data.warning + '</h5>' +
                        (suggestions.length ? '<ul>' + suggestions.map(function(s) { return '<li>' + s + '</li>'; }).join('') + '</ul>' : '');
                    document.getElementById('profitPerKmGauge').parentNode.parentNode.insertBefore(warningDiv, document.getElementById('profitPerKmGauge').parentNode);
                }
                if (data.empty || !data.gauge || !data.line) {
                    lucraChartEmpty(document.getElementById('profitPerKmGauge'), data.warning || 'Sem dados de lucro por km no período.');
                    return;
                }
                
                // Configurar gráfico gauge (usando doughnut como base)
                const gaugeCtx = document.getElementById('profitPerKmGauge').getContext('2d');
                new Chart(gaugeCtx, {
                    type: 'doughnut',
                    data: {
                        datasets: [{
                            data: [
                                data.gauge.value - data.gauge.min,
                                data.gauge.max - data.gauge.value
                            ],
                            backgroundColor: [
                                data.gauge.value < data.gauge.thresholds.red ? '#e74c3c' :
                                data.gauge.value < data.gauge.thresholds.yellow ? '#f1c40f' : '#2ecc40',
                                '#f8f9fa'
                            ],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        circumference: 180,
                        rotation: -90,
                        cutout: '80%',
                        plugins: {
                            title: {
                                display: true,
                                text: 'Lucro por KM (Atual)',
                                fontSize: 16,
                                padding: {
                                    bottom: 30
                                }
                            },
                            datalabels: {
                                display: true,
                                formatter: function(value, context) {
                                    if (context.dataIndex === 0) {
                                        return formatCurrency(data.gauge.value);
                                    }
                                    return '';
                                },
                                color: '#000',
                                font: {
                                    weight: 'bold',
                                    size: 16
                                }
                            },
                            legend: {
                                display: false
                            }
                        }
                    }
                });
                
                // Configurar gráfico de linha
                const lineCtx = document.getElementById('profitPerKmLine').getContext('2d');
                new Chart(lineCtx, {
                    type: 'line',
                    data: data.line,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed.y !== null) {
                                            label += formatCurrency(context.parsed.y);
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return formatCurrency(value);
                                    }
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Erro ao carregar dados de eficiência operacional:', error);
                var g = document.getElementById('profitPerKmGauge');
                var l = document.getElementById('profitPerKmLine');
                var msg = '<p class="lucra-chart-msg lucra-chart-msg--err" role="alert">Não foi possível carregar os gráficos de eficiência operacional.</p>';
                if (g && g.parentNode) g.parentNode.innerHTML = msg;
                if (l && l.parentNode) l.parentNode.innerHTML = msg;
            });
    });

    // Configuração do gráfico de projeção de lucro
    document.addEventListener('DOMContentLoaded', function() {
        const profitForecastEl = document.getElementById('profitForecastChart');
        if (!profitForecastEl) return;
        const urlParamsPf = new URLSearchParams(window.location.search);
        const mes = urlParamsPf.get('mes');
        const ano = urlParamsPf.get('ano');
        // Função para formatar valores em reais
        const formatCurrency = (value) => {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(value);
        };
        
        // Carregar dados do gráfico de projeção
        fetch('../api/profit_forecast_analytics.php' + (mes && ano ? ('?mes=' + mes + '&ano=' + ano) : ''))
            .then(function(response) { return lucraFetchJson(response, 'Projeção de lucro'); })
            .then(data => {
                if (data.empty) {
                    lucraChartEmpty(profitForecastEl, data.warning || 'Dados insuficientes para projeção no período.');
                    return;
                }
                const ctx = profitForecastEl.getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.labels || [],
                        datasets: data.datasets || []
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed.y !== null) {
                                            label += formatCurrency(context.parsed.y);
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return formatCurrency(value);
                                    }
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Erro ao carregar dados de projeção:', error);
                if (profitForecastEl.parentNode) {
                    profitForecastEl.parentNode.innerHTML = '<p class="lucra-chart-msg lucra-chart-msg--err" role="alert">Não foi possível carregar a projeção de lucro.</p>';
                }
            });
    });

    // Configuração dos novos gráficos avançados
    document.addEventListener('DOMContentLoaded', function() {
        // Função para formatar valores em reais
        const formatCurrency = (value) => {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(value);
        };
        
        // Get month and year from URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const mes = urlParams.get('mes');
        const ano = urlParams.get('ano');
        
        // Parâmetros para as APIs
        const apiParams = mes && ano ? `?mes=${mes}&ano=${ano}` : '';
        
        // 1. Gráfico de Evolução da Lucratividade
        const evolucaoCtx = document.getElementById('evolucaoLucratividadeChart');
        if (evolucaoCtx) {
            fetch(`../api/evolucao_lucratividade.php${apiParams}`)
                .then(function(response) { return lucraFetchJson(response, 'Evolução da lucratividade'); })
                .then(data => {
                    new Chart(evolucaoCtx, {
                        type: 'line',
                        data: {
                            labels: data.labels || ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
                            datasets: [{
                                label: 'Lucro Líquido',
                                data: data.lucro || [0, 0, 0, 0, 0, 0],
                                borderColor: '#28a745',
                                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                tension: 0.4,
                                fill: true
                            }, {
                                label: 'Receita Bruta',
                                data: data.receita || [0, 0, 0, 0, 0, 0],
                                borderColor: '#007bff',
                                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                },
                                title: {
                                    display: true,
                                    text: 'Evolução da Lucratividade'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return formatCurrency(value);
                                        }
                                    }
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Erro ao carregar dados de evolução:', error);
                    evolucaoCtx.parentNode.innerHTML = '<p class="lucra-chart-msg lucra-chart-msg--err" role="alert">Não foi possível carregar a evolução da lucratividade.</p>';
                });
        }
        
        // 2. Gráfico de Distribuição de Custos
        const distribuicaoCtx = document.getElementById('distribuicaoCustosChart');
        if (distribuicaoCtx) {
            fetch(`../api/distribuicao_custos.php${apiParams}`)
                .then(function(response) { return lucraFetchJson(response, 'Distribuição de custos'); })
                .then(data => {
                    new Chart(distribuicaoCtx, {
                        type: 'doughnut',
                        data: {
                            labels: data.labels || ['Combustível', 'Manutenções', 'Despesas Fixas', 'Comissões'],
                            datasets: [{
                                data: data.values || [0, 0, 0, 0],
                                backgroundColor: [
                                    '#ff6384',
                                    '#36a2eb',
                                    '#ffce56',
                                    '#4bc0c0'
                                ],
                                borderWidth: 2,
                                borderColor: '#fff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                },
                                title: {
                                    display: true,
                                    text: 'Distribuição de Custos'
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.parsed;
                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            const percentage = ((value / total) * 100).toFixed(1);
                                            return `${label}: ${formatCurrency(value)} (${percentage}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Erro ao carregar dados de distribuição:', error);
                    distribuicaoCtx.parentNode.innerHTML = '<p class="lucra-chart-msg lucra-chart-msg--err" role="alert">Não foi possível carregar a distribuição de custos.</p>';
                });
        }
        
        // 3. Gráfico de Margem por Tipo de Frete
        const margemCtx = document.getElementById('margemTipoFreteChart');
        if (margemCtx) {
            fetch(`../api/margem_tipo_frete.php${apiParams}`)
                .then(function(response) { return lucraFetchJson(response, 'Margem por tipo de frete'); })
                .then(data => {
                    new Chart(margemCtx, {
                        type: 'bar',
                        data: {
                            labels: data.labels || ['Frete Local', 'Frete Regional', 'Frete Interestadual'],
                            datasets: [{
                                label: 'Margem de Lucro (%)',
                                data: data.margens || [0, 0, 0],
                                backgroundColor: [
                                    'rgba(255, 99, 132, 0.8)',
                                    'rgba(54, 162, 235, 0.8)',
                                    'rgba(255, 205, 86, 0.8)'
                                ],
                                borderColor: [
                                    'rgba(255, 99, 132, 1)',
                                    'rgba(54, 162, 235, 1)',
                                    'rgba(255, 205, 86, 1)'
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                },
                                title: {
                                    display: true,
                                    text: 'Margem por Tipo de Frete'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: 100,
                                    ticks: {
                                        callback: function(value) {
                                            return value + '%';
                                        }
                                    }
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Erro ao carregar dados de margem:', error);
                    margemCtx.parentNode.innerHTML = '<p class="lucra-chart-msg lucra-chart-msg--err" role="alert">Não foi possível carregar a margem por tipo de frete.</p>';
                });
        }
        
        // 4. Gráfico de Projeção de Lucro
        const projecaoCtx = document.getElementById('projecaoLucroChart');
        if (projecaoCtx) {
            fetch(`../api/projecao_lucro.php${apiParams}`)
                .then(function(response) { return lucraFetchJson(response, 'Projeção de lucro (avançado)'); })
                .then(data => {
                    new Chart(projecaoCtx, {
                        type: 'line',
                        data: {
                            labels: data.labels || ['Mês Atual', 'Próximo Mês', '2º Mês', '3º Mês'],
                            datasets: [{
                                label: 'Projeção de Lucro',
                                data: data.projecao || [0, 0, 0, 0],
                                borderColor: '#28a745',
                                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                tension: 0.4,
                                fill: true,
                                pointBackgroundColor: '#28a745',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                                pointRadius: 6
                            }, {
                                label: 'Meta de Lucro',
                                data: data.meta || [0, 0, 0, 0],
                                borderColor: '#ffc107',
                                backgroundColor: 'rgba(255, 193, 7, 0.1)',
                                borderDash: [5, 5],
                                tension: 0.4,
                                fill: false,
                                pointBackgroundColor: '#ffc107',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                                pointRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                },
                                title: {
                                    display: true,
                                    text: 'Projeção de Lucro (Próximos 3 meses)'
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return formatCurrency(value);
                                        }
                                    }
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Erro ao carregar dados de projeção:', error);
                    projecaoCtx.parentNode.innerHTML = '<p class="lucra-chart-msg lucra-chart-msg--err" role="alert">Não foi possível carregar a projeção de lucro (avançado).</p>';
                });
        }
        
        // 5. Evolução da lucratividade (dados reais)
        const profitEvolutionCtx = document.getElementById('profitEvolutionChart');
        if (profitEvolutionCtx) {
            fetch(`../api/evolucao_lucratividade.php${apiParams}`)
                .then(function(response) { return lucraFetchJson(response, 'Evolução da lucratividade'); })
                .then(data => {
                    if (data.empty) {
                        lucraChartEmpty(profitEvolutionCtx, 'Sem dados de lucratividade nos últimos meses.');
                        return;
                    }
                    new Chart(profitEvolutionCtx, {
                        type: 'line',
                        data: {
                            labels: data.labels || [],
                            datasets: [{
                                label: 'Lucro Líquido',
                                data: data.lucro || [],
                                borderColor: '#28a745',
                                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                fill: true,
                                tension: 0.4
                            }, {
                                label: 'Receita Bruta',
                                data: data.receita || [],
                                borderColor: '#007bff',
                                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                                fill: true,
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'top' } },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { callback: function(value) { return formatCurrency(value); } }
                                }
                            }
                        }
                    });
                })
                .catch(function(error) {
                    console.error('Erro ao carregar evolução:', error);
                    profitEvolutionCtx.parentNode.innerHTML = '<p class="lucra-chart-msg lucra-chart-msg--err" role="alert">Não foi possível carregar a evolução.</p>';
                });
        }
        
        // 6. Gráfico de Distribuição de Custos
        const costDistributionCtx = document.getElementById('costDistributionChart');
        if (costDistributionCtx) {
            new Chart(costDistributionCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Combustível', 'Manutenções', 'Despesas Fixas', 'Comissões', 'Outros'],
                    datasets: [{
                        data: [
                            <?= isset($kpis['total_abastecimentos']) ? $kpis['total_abastecimentos'] : 0 ?>,
                            <?= isset($kpis['total_manutencoes']) ? $kpis['total_manutencoes'] : 0 ?>,
                            <?= isset($kpis['total_despesas_fixas']) ? $kpis['total_despesas_fixas'] : 0 ?>,
                            <?= isset($kpis['total_comissao']) ? $kpis['total_comissao'] : 0 ?>,
                            <?= isset($kpis['total_contas_pagas']) ? $kpis['total_contas_pagas'] : 0 ?>
                        ],
                        backgroundColor: [
                            '#ff6384',
                            '#36a2eb',
                            '#ffce56',
                            '#4bc0c0',
                            '#9966ff'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${formatCurrency(value)} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // 7. Margem por tipo de frete (API)
        const marginByFreightCtx = document.getElementById('marginByFreightChart');
        if (marginByFreightCtx) {
            fetch(`../api/margem_tipo_frete.php${apiParams}`)
                .then(function(response) { return lucraFetchJson(response, 'Margem por tipo de frete'); })
                .then(data => {
                    if (data.empty) {
                        lucraChartEmpty(marginByFreightCtx, 'Sem rotas classificáveis no período.');
                        return;
                    }
                    new Chart(marginByFreightCtx, {
                        type: 'bar',
                        data: {
                            labels: data.labels || [],
                            datasets: [{
                                label: 'Margem de Lucro (%)',
                                data: data.margens || [],
                                backgroundColor: 'rgba(59, 130, 246, 0.75)',
                                borderColor: 'rgba(59, 130, 246, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { callback: function(value) { return value + '%'; } }
                                }
                            }
                        }
                    });
                })
                .catch(function(error) {
                    console.error('Erro margem frete:', error);
                    marginByFreightCtx.parentNode.innerHTML = '<p class="lucra-chart-msg lucra-chart-msg--err" role="alert">Não foi possível carregar margens.</p>';
                });
        }
        
        // 8. Projeção de lucro (API)
        const profitForecastAdvancedCtx = document.getElementById('profitForecastAdvancedChart');
        if (profitForecastAdvancedCtx) {
            fetch(`../api/projecao_lucro.php${apiParams}`)
                .then(function(response) { return lucraFetchJson(response, 'Projeção de lucro'); })
                .then(data => {
                    if (data.empty) {
                        lucraChartEmpty(profitForecastAdvancedCtx, 'Sem histórico para projetar lucro.');
                        return;
                    }
                    new Chart(profitForecastAdvancedCtx, {
                        type: 'line',
                        data: {
                            labels: data.labels || [],
                            datasets: [{
                                label: 'Projeção de Lucro',
                                data: data.projecao || [],
                                borderColor: '#28a745',
                                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                tension: 0.4,
                                fill: true
                            }, {
                                label: 'Meta',
                                data: data.meta || [],
                                borderColor: '#ffc107',
                                backgroundColor: 'rgba(255, 193, 7, 0.1)',
                                borderDash: [5, 5],
                                tension: 0.4,
                                fill: false
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'top' } },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { callback: function(value) { return formatCurrency(value); } }
                                }
                            }
                        }
                    });
                })
                .catch(function(error) {
                    console.error('Erro projeção:', error);
                    profitForecastAdvancedCtx.parentNode.innerHTML = '<p class="lucra-chart-msg lucra-chart-msg--err" role="alert">Não foi possível carregar a projeção.</p>';
                });
        }
    });
    </script>
    
    <script>
        // Função para exportar PDF da tela
        function exportToPDF() {
            // Mostrar loading
            const exportBtn = document.getElementById('exportBtn');
            const originalHTML = exportBtn.innerHTML;
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            exportBtn.disabled = true;

            // Adicionar classe para esconder elementos durante exportação
            document.body.classList.add('exporting');

            // Aguardar um pouco para aplicar os estilos
            setTimeout(() => {
                // Configurações do html2canvas
                const options = {
                    scale: 1.5,
                    useCORS: true,
                    allowTaint: true,
                    backgroundColor: '#ffffff',
                    width: window.innerWidth,
                    height: document.documentElement.scrollHeight,
                    scrollX: 0,
                    scrollY: 0,
                    ignoreElements: function(element) {
                        return element.classList.contains('modal') ||
                               element.classList.contains('sidebar') ||
                               element.classList.contains('dashboard-actions') ||
                               element.classList.contains('fornc-toolbar') ||
                               element.classList.contains('fornc-kpi-strip') ||
                               element.classList.contains('lucra-period-nav');
                    }
                };

                // Capturar a tela
                html2canvas(document.body, options).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                
                // Criar PDF
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF({
                    orientation: 'landscape',
                    unit: 'mm',
                    format: 'a4'
                });

                // Calcular dimensões para caber na página
                const pdfWidth = pdf.internal.pageSize.getWidth();
                const pdfHeight = pdf.internal.pageSize.getHeight();
                const imgWidth = canvas.width;
                const imgHeight = canvas.height;
                
                // Calcular escala para caber na página
                const ratio = Math.min(pdfWidth / imgWidth, pdfHeight / imgHeight);
                const finalWidth = imgWidth * ratio;
                const finalHeight = imgHeight * ratio;
                
                // Centralizar na página
                const x = (pdfWidth - finalWidth) / 2;
                const y = (pdfHeight - finalHeight) / 2;

                // Adicionar imagem ao PDF
                pdf.addImage(imgData, 'PNG', x, y, finalWidth, finalHeight);
                
                // Gerar nome do arquivo com data e hora
                const now = new Date();
                const dateStr = now.toLocaleDateString('pt-BR').replace(/\//g, '-');
                const timeStr = now.toLocaleTimeString('pt-BR').replace(/:/g, '-');
                const fileName = `Lucratividade_${dateStr}_${timeStr}.pdf`;
                
                // Baixar o PDF
                pdf.save(fileName);
                
                // Remover classe de exportação
                document.body.classList.remove('exporting');
                
                // Restaurar botão
                exportBtn.innerHTML = originalHTML;
                exportBtn.disabled = false;
                
                // Mostrar sucesso
                alert('PDF exportado com sucesso!');
                
            }).catch(error => {
                console.error('Erro ao exportar PDF:', error);
                
                // Remover classe de exportação
                document.body.classList.remove('exporting');
                
                // Restaurar botão
                exportBtn.innerHTML = originalHTML;
                exportBtn.disabled = false;
                
                // Mostrar erro
                alert('Erro ao exportar PDF. Tente novamente.');
            });
            }, 500); // Aguardar 500ms para aplicar estilos
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Setup export button
            const exportBtn = document.getElementById('exportBtn');
            if (exportBtn) {
                exportBtn.addEventListener('click', exportToPDF);
            }
            
            // Novos Gráficos Avançados
            // Gráfico de Cascata (Waterfall)
            const waterfallCtx = document.getElementById('waterfallChart');
            if (waterfallCtx) {
                const receita = <?= isset($kpis['total_frete']) ? (float)$kpis['total_frete'] : 0 ?>;
                const comissao = <?= isset($kpis['total_comissao']) ? -(float)$kpis['total_comissao'] : 0 ?>;
                const combustivel = <?= isset($kpis['total_abastecimentos']) ? -(float)$kpis['total_abastecimentos'] : 0 ?>;
                const manutencao = <?= isset($kpis['total_manutencoes']) ? -(float)$kpis['total_manutencoes'] : 0 ?>;
                const pneus = <?= isset($kpis['total_pneu_manutencao']) ? -(float)$kpis['total_pneu_manutencao'] : 0 ?>;
                const despesas_fixas = <?= isset($kpis['total_despesas_fixas']) ? -(float)$kpis['total_despesas_fixas'] : 0 ?>;
                const despesas_viagem = <?= isset($kpis['total_despesas_viagem']) ? -(float)$kpis['total_despesas_viagem'] : 0 ?>;
                const financiamento = <?= isset($kpis['total_parcelas_financiamento']) ? -(float)$kpis['total_parcelas_financiamento'] : 0 ?>;
                const contas = <?= isset($kpis['total_contas_pagas']) ? -(float)$kpis['total_contas_pagas'] : 0 ?>;
                const lucro = <?= (float)$lucro ?>;
                
                new Chart(waterfallCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: ['Receita', 'Comissões', 'Combustível', 'Viagem', 'Fixas', 'Financ.', 'Contas', 'Manutenção', 'Pneus', 'Lucro'],
                        datasets: [{
                            label: 'Valor (R$)',
                            data: [receita, comissao, combustivel, despesas_viagem, despesas_fixas, financiamento, contas, manutencao, pneus, lucro],
                            backgroundColor: [
                                'rgba(16, 185, 129, 0.8)',
                                'rgba(239, 68, 68, 0.8)', 'rgba(239, 68, 68, 0.8)', 'rgba(239, 68, 68, 0.8)',
                                'rgba(239, 68, 68, 0.8)', 'rgba(239, 68, 68, 0.8)', 'rgba(239, 68, 68, 0.8)',
                                'rgba(239, 68, 68, 0.8)', 'rgba(239, 68, 68, 0.8)',
                                'rgba(16, 185, 129, 0.8)'
                            ],
                            borderColor: [
                                'rgba(16, 185, 129, 1)',
                                'rgba(239, 68, 68, 1)', 'rgba(239, 68, 68, 1)', 'rgba(239, 68, 68, 1)',
                                'rgba(239, 68, 68, 1)', 'rgba(239, 68, 68, 1)', 'rgba(239, 68, 68, 1)',
                                'rgba(239, 68, 68, 1)', 'rgba(239, 68, 68, 1)',
                                'rgba(16, 185, 129, 1)'
                            ],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                top: 10,
                                bottom: 10,
                                left: 10,
                                right: 10
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'R$ ' + Math.abs(context.parsed.y).toLocaleString('pt-BR', {minimumFractionDigits: 2});
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 45
                                }
                            },
                            y: {
                                beginAtZero: false,
                                ticks: {
                                    callback: function(value) {
                                        return 'R$ ' + Math.abs(value).toLocaleString('pt-BR');
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Gráfico de Distribuição Detalhada de Custos
            const costBreakdownCtx = document.getElementById('costBreakdownChart');
            if (costBreakdownCtx) {
                const combustivel = <?= isset($kpis['total_abastecimentos']) ? $kpis['total_abastecimentos'] : 0 ?>;
                const comissao = <?= isset($kpis['total_comissao']) ? $kpis['total_comissao'] : 0 ?>;
                const manutencao = <?= isset($kpis['total_manutencoes']) ? $kpis['total_manutencoes'] : 0 ?>;
                const despesas_fixas = <?= isset($kpis['total_despesas_fixas']) ? $kpis['total_despesas_fixas'] : 0 ?>;
                const despesas_viagem = <?= isset($kpis['total_despesas_viagem']) ? $kpis['total_despesas_viagem'] : 0 ?>;
                const financiamento = <?= isset($kpis['total_parcelas_financiamento']) ? $kpis['total_parcelas_financiamento'] : 0 ?>;
                const pneu_manutencao = <?= isset($kpis['total_pneu_manutencao']) ? $kpis['total_pneu_manutencao'] : 0 ?>;
                const contas = <?= isset($kpis['total_contas_pagas']) ? $kpis['total_contas_pagas'] : 0 ?>;
                
                new Chart(costBreakdownCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Combustível', 'Comissões', 'Manutenção', 'Despesas Fixas', 'Despesas Viagem', 'Financiamento', 'Contas Pagas', 'Pneus'],
                        datasets: [{
                            data: [combustivel, comissao, manutencao, despesas_fixas, despesas_viagem, financiamento, contas, pneu_manutencao],
                            backgroundColor: [
                                'rgba(239, 68, 68, 0.8)',
                                'rgba(245, 158, 11, 0.8)',
                                'rgba(59, 130, 246, 0.8)',
                                'rgba(139, 92, 246, 0.8)',
                                'rgba(236, 72, 153, 0.8)',
                                'rgba(107, 114, 128, 0.8)'
                            ],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                top: 20,
                                bottom: 20,
                                left: 20,
                                right: 20
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    font: {
                                        size: 12
                                    },
                                    usePointStyle: true,
                                    pointStyle: 'circle'
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                        return label + ': R$ ' + value.toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ' (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Tendência anual (12 meses — API)
            const trendCtx = document.getElementById('trendChart');
            if (trendCtx) {
                const urlTrend = new URLSearchParams(window.location.search);
                const mesT = urlTrend.get('mes');
                const anoT = urlTrend.get('ano');
                const trendQs = mesT && anoT ? ('?mes=' + mesT + '&ano=' + anoT + '&meses=12') : '?meses=12';
                fetch('../api/evolucao_lucratividade.php' + trendQs)
                    .then(function(r) { return lucraFetchJson(r, 'Tendência anual'); })
                    .then(function(data) {
                        if (data.empty) {
                            lucraChartEmpty(trendCtx, 'Sem dados para tendência anual.');
                            return;
                        }
                        new Chart(trendCtx.getContext('2d'), {
                            type: 'line',
                            data: {
                                labels: data.labels || [],
                                datasets: [{
                                    label: 'Lucro líquido',
                                    data: data.lucro || [],
                                    borderColor: 'rgba(16, 185, 129, 1)',
                                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                    tension: 0.4,
                                    fill: true
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: { legend: { display: true, position: 'top' } },
                                scales: {
                                    y: {
                                        ticks: {
                                            callback: function(value) {
                                                return 'R$ ' + Number(value).toLocaleString('pt-BR');
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    })
                    .catch(function() {
                        trendCtx.parentNode.innerHTML = '<p class="lucra-chart-msg lucra-chart-msg--err" role="alert">Não foi possível carregar a tendência.</p>';
                    });
            }
            
            // Gráfico de Pareto
            const paretoCtx = document.getElementById('paretoChart');
            if (paretoCtx) {
                const combustivel = <?= isset($kpis['total_abastecimentos']) ? $kpis['total_abastecimentos'] : 0 ?>;
                const manutencao = <?= isset($kpis['total_manutencoes']) ? $kpis['total_manutencoes'] : 0 ?>;
                const despesas_fixas = <?= isset($kpis['total_despesas_fixas']) ? $kpis['total_despesas_fixas'] : 0 ?>;
                const despesas_viagem = <?= isset($kpis['total_despesas_viagem']) ? $kpis['total_despesas_viagem'] : 0 ?>;
                const total = combustivel + manutencao + despesas_fixas + despesas_viagem;
                
                const pct_combustivel = total > 0 ? (combustivel / total) * 100 : 0;
                const pct_manutencao = total > 0 ? (manutencao / total) * 100 : 0;
                const pct_fixas = total > 0 ? (despesas_fixas / total) * 100 : 0;
                const pct_viagem = total > 0 ? (despesas_viagem / total) * 100 : 0;
                
                new Chart(paretoCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: ['Combustível', 'Manutenção', 'Despesas Fixas', 'Despesas Viagem'],
                        datasets: [{
                            label: 'Valor (R$)',
                            data: [combustivel, manutencao, despesas_fixas, despesas_viagem],
                            backgroundColor: [
                                'rgba(239, 68, 68, 0.8)',
                                'rgba(245, 158, 11, 0.8)',
                                'rgba(59, 130, 246, 0.8)',
                                'rgba(139, 92, 246, 0.8)'
                            ]
                        }, {
                            label: 'Acumulado (%)',
                            type: 'line',
                            data: [pct_combustivel, pct_combustivel + pct_manutencao, pct_combustivel + pct_manutencao + pct_fixas, 100],
                            borderColor: 'rgba(16, 185, 129, 1)',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            yAxisID: 'y1',
                            fill: false
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: true },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        if (context.datasetIndex === 0) {
                                            return 'Valor: R$ ' + context.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                                        } else {
                                            return 'Acumulado: ' + context.parsed.y.toFixed(1) + '%';
                                        }
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                position: 'left',
                                ticks: {
                                    callback: function(value) {
                                        return 'R$ ' + value.toLocaleString('pt-BR');
                                    }
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                max: 100,
                                ticks: {
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                },
                                grid: {
                                    drawOnChartArea: false
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
    <?php $sf_help_key = 'lucratividade'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>


    <?php include '../includes/scroll_to_top.php'; ?>

    <script src="../js/sf_insights.js?v=1.3"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.SFInsights) return;
        var urlI = new URLSearchParams(window.location.search);
        var insightsUrl = '../api/lucratividade_insights.php';
        if (urlI.get('mes') && urlI.get('ano')) {
            insightsUrl += '?mes=' + urlI.get('mes') + '&ano=' + urlI.get('ano');
        }
        SFInsights.init({
            mountId: 'sfLucraInsights',
            insightsUrl: insightsUrl,
            resumoUrl: '../api/lucratividade_resumo_ia.php',
            title: 'Insights de lucratividade',
            sectionTitle: 'Alertas do período',
            emptyOk: 'Nenhum alerta relevante para o mês.',
            stats: function (j) {
                return [
                    { label: 'lucro', value: 'R$ ' + Number(j.lucro_liquido || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 }), tone: (j.lucro_liquido || 0) >= 0 ? 'ok' : 'erro' },
                    { label: 'margem', value: Number(j.margem || 0).toFixed(1) + '%', tone: (j.margem || 0) >= 10 ? 'ok' : 'aviso' }
                ];
            }
        });
    });
    </script>
    <?php sf_page_help_js('..'); ?>
</body>
</html> 
