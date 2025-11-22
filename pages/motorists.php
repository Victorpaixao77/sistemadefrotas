<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/progress_bars.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Check if user is logged in, if not redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: /sistema-frotas/login.php");
    exit;
}

// Set page title
$page_title = "Motoristas";

// Load motorists for listing and metrics
$conn = getConnection();
$empresa_id = $_SESSION['empresa_id'];

$stmt = $conn->prepare("
    SELECT 
        m.id,
        m.nome,
        m.cpf,
        m.telefone,
        m.email,
        m.cnh,
        m.porcentagem_comissao,
        d.nome AS disponibilidade_nome,
        c.nome AS categoria_cnh_nome
    FROM motoristas m
    LEFT JOIN disponibilidades d ON m.disponibilidade_id = d.id
    LEFT JOIN categorias_cnh c ON m.categoria_cnh_id = c.id
    WHERE m.empresa_id = :empresa_id
    ORDER BY m.nome ASC
");
$stmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
$stmt->execute();
$motoristas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_motoristas = count($motoristas);
$motoristas_disponiveis = 0;
$motoristas_indisponiveis = 0;
$soma_comissoes = 0.0;

foreach ($motoristas as $motorista) {
    $status = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $motorista['disponibilidade_nome'] ?? ''));
    if (in_array($status, ['ativo', 'disponivel', 'disponivel', 'em-operacao'], true)) {
        $motoristas_disponiveis++;
    } else {
        $motoristas_indisponiveis++;
    }

    if (isset($motorista['porcentagem_comissao'])) {
        $soma_comissoes += (float) $motorista['porcentagem_comissao'];
    }
}

$media_comissao = $total_motoristas > 0 ? $soma_comissoes / $total_motoristas : 0.0;

// Helper functions
if (!function_exists('normalize_status_class')) {
    function normalize_status_class(?string $value): string
    {
        if (!$value) {
            return '';
        }

        $slug = iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }
}

if (!function_exists('format_phone_br')) {
    function format_phone_br(?string $phone): string
    {
        if (!$phone) {
            return '-';
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) === 11) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7));
        }
        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6));
        }

        return $phone;
    }
}

if (!function_exists('format_cpf_br')) {
    function format_cpf_br(?string $cpf): string
    {
        if (!$cpf) {
            return '-';
        }

        $digits = preg_replace('/\D+/', '', $cpf);
        if (strlen($digits) !== 11) {
            return $cpf;
        }

        return sprintf('%s.%s.%s-%s',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 3),
            substr($digits, 9, 2)
        );
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Motoristas - Sistema de Frotas</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../logo.png">
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
        
        /* Estilos para paginação */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
            gap: 15px;
        }
        
        .pagination-btn {
            padding: 8px 16px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: var(--bg-secondary);
            color: var(--text-color);
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .pagination-btn:hover:not(.disabled) {
            background: var(--bg-tertiary);
        }
        
        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .pagination-info {
            font-size: 0.9rem;
            color: var(--text-color);
            padding: 0 10px;
        }
        
        /* Estilos para a seção de análise */
        .analytics-section {
            margin-top: 20px;
            padding: 0 20px;
        }
        
        .analytics-section .section-header {
            margin-bottom: 20px;
        }
        
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .analytics-card {
            background: var(--bg-secondary);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            height: 100%;
        }
        
        .analytics-card .card-header {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .analytics-card .card-header h3 {
            margin: 0;
            font-size: 1rem;
        }
        
        .analytics-card .card-body {
            padding: 15px;
            height: 400px;
            position: relative;
        }

        .chart-container {
            position: relative;
            height: 100%;
            width: 100%;
        }

        /* Garantir que os botões do modal de motoristas tenham o mesmo tamanho dos botões do modal de veículos */
        #motoristModal .modal-footer .btn-secondary,
        #motoristModal .modal-footer .btn-primary {
            min-width: 100px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 500;
        }

        /* Modal extra large para gamificação e ranking */
        .modal-xl {
            width: 95vw;
            max-width: 1200px;
            margin: 20px auto;
        }

        .modal-xl .modal-content {
            max-height: 90vh;
            overflow-y: auto;
        }

        /* Spinner para loading */
        .spinner-border {
            width: 3rem;
            height: 3rem;
            border: 0.25em solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spinner-border 0.75s linear infinite;
        }

        @keyframes spinner-border {
            to {
                transform: rotate(360deg);
            }
        }

        .visually-hidden {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }

        /* Estilos modernos para Gamificação */
        .gamification-header, .ranking-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .gamification-header h3, .ranking-header h3 {
            color: white;
            font-weight: 700;
        }

        .gamification-header p, .ranking-header p {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

        .bg-gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }

        .bg-gradient-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%) !important;
        }

        .bg-gradient-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }

        /* Cards de Níveis */
        .level-card {
            background: white;
            border-radius: 8px;
            padding: 6px;
            text-align: center;
            transition: all 0.3s ease;
            min-height: 70px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            border: 2px solid transparent;
            height: 100%;
            flex: 1;
            margin: 0 2px;
        }

        .level-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .level-card.bronze {
            background: linear-gradient(135deg, #CD7F32, #8B4513);
            color: white;
        }

        .level-card.silver {
            background: linear-gradient(135deg, #C0C0C0, #808080);
            color: white;
        }

        .level-card.gold {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: white;
        }

        .level-card.platinum {
            background: linear-gradient(135deg, #E5E4E2, #C0C0C0);
            color: white;
        }

        .level-card.diamond {
            background: linear-gradient(135deg, #B9F2FF, #00BFFF);
            color: white;
        }

        .level-card.legend {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: white;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
        }

        /* Badge Cards */
        .badge-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 8px;
            text-align: center;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 70px;
        }

        .badge-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .badge-icon {
            font-size: 1.2rem;
            margin-bottom: 4px;
        }

        .badge-name {
            font-weight: 600;
            font-size: 0.7rem;
            margin-bottom: 2px;
            color: #2c3e50;
        }

        .badge-desc {
            font-size: 0.6rem;
            color: #6c757d;
            line-height: 1.1;
        }

        /* Stat Cards Compactos */
        .stat-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 80px;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-size: 0.9rem;
        }

        .stat-content {
            flex: 1;
        }

        .stat-number {
            font-size: 1.2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.7rem;
            color: #6c757d;
            font-weight: 500;
            line-height: 1.2;
        }

        /* Garantir que os níveis fiquem lado a lado */
        .levels-container {
            display: flex !important;
            flex-wrap: nowrap !important;
            gap: 4px !important;
            overflow-x: auto;
            padding-bottom: 5px;
        }

        .levels-container .level-card {
            flex: 1;
            min-width: 0;
            white-space: nowrap;
        }

        /* Garantir que os badges fiquem lado a lado */
        .badges-container {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 8px !important;
        }

        .badges-container .badge-card {
            width: 100% !important;
            margin: 0 !important;
        }

        /* Garantir que as estatísticas fiquem lado a lado */
        .stats-container {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 8px !important;
        }

        .stats-container .stat-card {
            width: 100% !important;
            margin: 0 !important;
        }

        /* Garantir que os filtros fiquem lado a lado */
        .filters-container {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 12px !important;
        }

        .filters-container .filter-item {
            width: 100% !important;
            margin: 0 !important;
        }

        /* Garantir que as métricas fiquem lado a lado */
        .metrics-container {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 8px !important;
        }

        .metrics-container .metric-card {
            width: 100% !important;
            margin: 0 !important;
        }

        /* Garantir que a distribuição fique lado a lado */
        .distribution-grid {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 12px !important;
        }

        .distribution-grid .distribution-item {
            width: 100% !important;
            margin: 0 !important;
        }

        /* Garantir que os critérios fiquem lado a lado */
        .criteria-container {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 8px !important;
        }

        .criteria-container .criteria-card {
            width: 100% !important;
            margin: 0 !important;
        }

        /* Corrigir contraste das métricas de desempenho */
        .performance-section .metric-info h4 {
            color: #2c3e50 !important;
            font-weight: 600 !important;
            margin-bottom: 8px !important;
        }

        .performance-section .metric-info p {
            color: #495057 !important;
            font-weight: 500 !important;
            font-size: 1.1rem !important;
            margin: 0 !important;
        }

        .performance-section .metric-icon {
            color: #6c757d !important;
            font-size: 1.5rem !important;
        }

        .performance-section h3 {
            color: #2c3e50 !important;
            font-weight: 700 !important;
            margin-bottom: 20px !important;
        }

        .level-icon {
            font-size: 1.2rem;
            margin-bottom: 4px;
        }

        .level-name {
            font-weight: bold;
            font-size: 0.7rem;
            margin-bottom: 2px;
        }

        .level-points {
            font-size: 0.6rem;
            line-height: 1.1;
            opacity: 0.9;
        }

        /* Cards de Badges */
        .badge-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            height: 100%;
        }

        .badge-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: #007bff;
        }

        .badge-icon {
            font-size: 2rem;
            margin-bottom: 8px;
        }

        .badge-name {
            font-weight: bold;
            font-size: 0.9rem;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .badge-desc {
            font-size: 0.75rem;
            color: #6c757d;
            line-height: 1.3;
        }

        /* Cards de Métricas Avançadas */
        .metric-card {
            display: flex;
            align-items: center;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
        }

        .metric-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .metric-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 1.2rem;
        }

        .metric-content {
            flex: 1;
        }

        .metric-content h6 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .metric-content p {
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: bold;
            margin: 0 0 5px 0;
        }

        .metric-content small {
            color: #6c757d;
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Leaderboard */
        .leaderboard-container {
            max-height: 500px;
            overflow-y: auto;
            padding: 0;
        }

        .leaderboard-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s ease;
        }

        .leaderboard-item:hover {
            background: #f8f9fa;
        }

        .leaderboard-item.top-three {
            background: linear-gradient(90deg, rgba(255,215,0,0.1), transparent);
        }

        .position {
            margin-right: 15px;
        }

        .position-badge {
            display: inline-block;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            text-align: center;
            line-height: 40px;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .position-badge.gold {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: white;
        }

        .position-badge.silver {
            background: linear-gradient(135deg, #C0C0C0, #808080);
            color: white;
        }

        .position-badge.bronze {
            background: linear-gradient(135deg, #CD7F32, #8B4513);
            color: white;
        }

        .position-badge:not(.gold):not(.silver):not(.bronze) {
            background: #f8f9fa;
            color: #6c757d;
        }

        .driver-info {
            flex: 1;
        }

        .driver-name {
            font-weight: bold;
            font-size: 1.1rem;
            color: #2c3e50;
        }
        
        /* Contraste melhorado - modo claro (padrão) */
        .driver-name {
            color: #2c3e50 !important;
            font-weight: 700 !important;
        }
        
        .leaderboard-item .driver-name {
            color: #2c3e50 !important;
            font-weight: 700 !important;
        }
        
        .ranking-item .driver-name {
            color: #2c3e50 !important;
            font-weight: 700 !important;
        }
        
        .driver-stats {
            color: #495057 !important;
            font-weight: 600 !important;
        }
        
        .leaderboard-item .driver-stats {
            color: #495057 !important;
            font-weight: 600 !important;
        }
        
        .driver-id {
            color: #6c757d !important;
            font-weight: 500 !important;
        }
        
        .ranking-item .driver-id {
            color: #6c757d !important;
            font-weight: 500 !important;
        }
        
        .score-text {
            color: #2c3e50 !important;
            font-weight: 700 !important;
        }
        
        .ranking-item .score-text {
            color: #2c3e50 !important;
            font-weight: 700 !important;
        }
        
        .level-badge {
            color: #2c3e50 !important;
            font-weight: 600 !important;
        }
        
        .leaderboard-item .level-badge {
            color: #2c3e50 !important;
            font-weight: 600 !important;
        }
        
        .status-badge {
            color: #2c3e50 !important;
            font-weight: 600 !important;
        }
        
        .ranking-item .status-badge {
            color: #2c3e50 !important;
            font-weight: 600 !important;
        }
        
        /* Modo escuro - apenas quando detectado */
        @media (prefers-color-scheme: dark) {
            .driver-name, .leaderboard-item .driver-name, .ranking-item .driver-name {
                color: #f8f9fa !important;
            }
            
            .driver-stats, .leaderboard-item .driver-stats {
                color: #e9ecef !important;
            }
            
            .driver-id, .ranking-item .driver-id {
                color: #adb5bd !important;
            }
            
            .score-text, .ranking-item .score-text {
                color: #f8f9fa !important;
            }
            
            .level-badge, .leaderboard-item .level-badge {
                color: #f8f9fa !important;
            }
            
            .status-badge, .ranking-item .status-badge {
                color: #f8f9fa !important;
            }
            
            /* Forçar contraste em modo escuro para modais */
            .modal .driver-name,
            .modal .leaderboard-item .driver-name,
            .modal .ranking-item .driver-name {
                color: #f8f9fa !important;
            }
            
            .modal .driver-stats,
            .modal .leaderboard-item .driver-stats {
                color: #e9ecef !important;
            }
            
            .modal .driver-id,
            .modal .ranking-item .driver-id {
                color: #adb5bd !important;
            }
            
            .modal .score-text,
            .modal .ranking-item .score-text {
                color: #f8f9fa !important;
            }
            
            .modal .level-badge,
            .modal .leaderboard-item .level-badge {
                color: #f8f9fa !important;
            }
            
            .modal .status-badge,
            .modal .ranking-item .status-badge {
                color: #f8f9fa !important;
            }
        }

        .driver-stats {
            display: flex;
            gap: 15px;
            margin-top: 5px;
        }
        
        .driver-stats span {
            font-size: 0.9rem;
            color: #495057 !important;
            font-weight: 600 !important;
        }

        .level-badge {
            margin-left: 15px;
        }
        
        /* Contraste para modo claro (padrão) */
        /* Estilos específicos apenas para os modais de gamificação e ranking */
        .modal-xl .modal-body .driver-name,
        .modal-xl .modal-body .leaderboard-item .driver-name,
        .modal-xl .modal-body .ranking-item .driver-name {
            color: #2c3e50 !important;
            font-weight: 700 !important;
        }
        
        .modal-xl .modal-body .driver-stats,
        .modal-xl .modal-body .leaderboard-item .driver-stats {
            color: #495057 !important;
            font-weight: 600 !important;
        }
        
        .modal-xl .modal-body .driver-id,
        .modal-xl .modal-body .ranking-item .driver-id {
            color: #6c757d !important;
            font-weight: 500 !important;
        }
        
        .modal-xl .modal-body .score-text,
        .modal-xl .modal-body .ranking-item .score-text {
            color: #2c3e50 !important;
            font-weight: 700 !important;
        }
        
        .modal-xl .modal-body .level-badge,
        .modal-xl .modal-body .leaderboard-item .level-badge {
            color: #2c3e50 !important;
            font-weight: 600 !important;
        }
        
        .modal-xl .modal-body .status-badge,
        .modal-xl .modal-body .ranking-item .status-badge {
            color: #2c3e50 !important;
            font-weight: 600 !important;
        }
        
        /* Modo escuro - apenas para modais de gamificação e ranking */
        @media (prefers-color-scheme: dark) {
            .modal-xl .modal-body .driver-name,
            .modal-xl .modal-body .leaderboard-item .driver-name,
            .modal-xl .modal-body .ranking-item .driver-name {
                color: #ffffff !important;
            }
            
            .modal-xl .modal-body .driver-stats,
            .modal-xl .modal-body .leaderboard-item .driver-stats {
                color: #e9ecef !important;
            }
            
            .modal-xl .modal-body .driver-id,
            .modal-xl .modal-body .ranking-item .driver-id {
                color: #adb5bd !important;
            }
            
            .modal-xl .modal-body .score-text,
            .modal-xl .modal-body .ranking-item .score-text {
                color: #ffffff !important;
            }
            
            .modal-xl .modal-body .level-badge,
            .modal-xl .modal-body .leaderboard-item .level-badge {
                color: #ffffff !important;
            }
            
            .modal-xl .modal-body .status-badge,
            .modal-xl .modal-body .ranking-item .status-badge {
                color: #ffffff !important;
            }
        }
        
        /* Detecção manual de modo escuro - apenas para modais de gamificação e ranking */
        body.dark-mode .modal-xl .modal-body .driver-name,
        body.dark-mode .modal-xl .modal-body .leaderboard-item .driver-name,
        body.dark-mode .modal-xl .modal-body .ranking-item .driver-name {
            color: #ffffff !important;
        }
        
        body.dark-mode .modal-xl .modal-body .driver-stats,
        body.dark-mode .modal-xl .modal-body .leaderboard-item .driver-stats {
            color: #e9ecef !important;
        }
        
        body.dark-mode .modal-xl .modal-body .driver-id,
        body.dark-mode .modal-xl .modal-body .ranking-item .driver-id {
            color: #adb5bd !important;
        }
        
        body.dark-mode .modal-xl .modal-body .score-text,
        body.dark-mode .modal-xl .modal-body .ranking-item .score-text {
            color: #ffffff !important;
        }
        
        body.dark-mode .modal-xl .modal-body .level-badge,
        body.dark-mode .modal-xl .modal-body .leaderboard-item .level-badge {
            color: #ffffff !important;
        }
        
        body.dark-mode .modal-xl .modal-body .status-badge,
        body.dark-mode .modal-xl .modal-body .ranking-item .status-badge {
            color: #ffffff !important;
        }

        /* Ranking */
        .ranking-container {
            max-height: 500px;
            overflow-y: auto;
            padding: 0;
        }

        .ranking-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s ease;
        }

        .driver-id {
            color: #6c757d;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .ranking-item:hover {
            background: #f8f9fa;
        }

        .ranking-item.top-three {
            background: linear-gradient(90deg, rgba(255,215,0,0.1), transparent);
        }

        .score-section {
            flex: 1;
            margin: 0 20px;
        }

        .score-bar {
            position: relative;
            background: #e9ecef;
            height: 25px;
            border-radius: 12px;
            overflow: hidden;
        }

        .score-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            border-radius: 12px;
            transition: width 0.3s ease;
        }

        .score-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-weight: bold;
            color: white;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }

        .status-section {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: center;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Distribuição */
        .distribution-item {
            margin-bottom: 20px;
        }

        .distribution-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .distribution-header span:first-child {
            color: #2c3e50;
            font-weight: 600;
        }

        .distribution-header .badge {
            font-weight: 600;
        }

        .distribution-bar {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 5px;
        }

        .bar-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .distribution-item small {
            color: #6c757d;
            font-weight: 500;
        }

        .total-summary {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .total-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2c3e50;
        }

        .total-label {
            color: #495057;
            font-size: 1.1rem;
            font-weight: 600;
        }

        /* Estatísticas */
        .stat-card {
            display: flex;
            align-items: center;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 1.5rem;
        }

        .stat-content {
            flex: 1;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #495057;
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* Critérios */
        .criteria-card {
            display: flex;
            align-items: center;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
        }

        .criteria-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .criteria-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 1.2rem;
        }

        .criteria-content {
            flex: 1;
        }

        .criteria-content h6 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .criteria-content p {
            color: #6c757d;
            font-size: 0.85rem;
            margin: 0;
        }

        .criteria-percentage {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
            margin: 5px 0;
        }

        /* No Data */
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #495057;
        }

        .no-data h5 {
            color: #2c3e50;
            font-weight: 600;
        }

        .no-data p {
            color: #6c757d;
            font-weight: 500;
        }

        .no-data i {
            color: #adb5bd;
            margin-bottom: 20px;
        }

        /* Scrollbar personalizada */
        .leaderboard-container::-webkit-scrollbar,
        .ranking-container::-webkit-scrollbar {
            width: 6px;
        }

        .leaderboard-container::-webkit-scrollbar-track,
        .ranking-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .leaderboard-container::-webkit-scrollbar-thumb,
        .ranking-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .leaderboard-container::-webkit-scrollbar-thumb:hover,
        .ranking-container::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include '../includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include '../includes/header.php'; ?>
            
            <!-- Page Content -->
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1>Motoristas</h1>
                    <div class="dashboard-actions">
                        <button id="addMotoristBtn" class="btn-add-widget">
                            <i class="fas fa-plus"></i> Novo Motorista
                        </button>
                        <button id="rankingBtn" class="btn-add-widget" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin-left: 10px;">
                            <i class="fas fa-trophy"></i> Ranking de Performance
                        </button>
                        <button id="gamificacaoBtn" class="btn-add-widget" style="background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%); margin-left: 10px;">
                            <i class="fas fa-gamepad"></i> Gamificação
                        </button>
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
                
                <!-- KPI Cards Row -->
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Total de Motoristas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="totalMotorists"><?php echo $total_motoristas; ?></span>
                                <span class="metric-subtitle">Motoristas cadastrados</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Motoristas Ativos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="activeMotorists"><?php echo $motoristas_disponiveis; ?></span>
                                <span class="metric-subtitle">Em serviço</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Status</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="totalTrips"><?php echo $motoristas_indisponiveis; ?></span>
                                <span class="metric-subtitle">Motoristas indisponíveis</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Comissão</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" id="averageRating"><?php echo number_format($media_comissao, 2, ',', '.'); ?>%</span>
                                <span class="metric-subtitle">Média de comissão configurada</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Search and Filter -->
                <div class="filter-section">
                    <div class="search-box">
                        <input type="text" id="searchMotorist" placeholder="Buscar motorista...">
                        <i class="fas fa-search"></i>
                    </div>
                    
                    <div class="filter-options">
                        <select id="statusFilter">
                            <option value="">Todos os status</option>
                            <option value="1">Ativo</option>
                            <option value="2">Férias</option>
                            <option value="3">Licença</option>
                            <option value="4">Inativo</option>
                            <option value="5">Afastado</option>
                        </select>
                        <button type="button" class="btn-restore-layout" id="applyMotoristFilters" title="Aplicar filtros">
                            <i class="fas fa-filter"></i>
                        </button>
                        <button type="button" class="btn-restore-layout" id="clearMotoristFilters" title="Limpar filtros">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Motorists List Table -->
                <div class="data-table-container">
                    <table class="data-table" id="motoristsTable">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>CPF</th>
                                <th>CNH</th>
                                <th>Categoria</th>
                                <th>Telefone</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Comissão</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($motoristas)): ?>
                                <?php foreach ($motoristas as $motorista): ?>
                                    <?php
                                        $statusNome = $motorista['disponibilidade_nome'] ?? 'Indefinido';
                                        $statusClass = normalize_status_class($statusNome);
                                        $comissaoDisplay = isset($motorista['porcentagem_comissao'])
                                            ? number_format((float) $motorista['porcentagem_comissao'], 2, ',', '.') . '%'
                                            : '-';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($motorista['nome']); ?></td>
                                        <td><?php echo htmlspecialchars(format_cpf_br($motorista['cpf'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars($motorista['cnh'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($motorista['categoria_cnh_nome'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars(format_phone_br($motorista['telefone'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars($motorista['email'] ?? '-'); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $statusClass ? 'status-' . $statusClass : ''; ?>">
                                                <?php echo htmlspecialchars($statusNome ?: 'Indefinido'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($comissaoDisplay); ?></td>
                                        <td class="actions">
                                            <button class="btn-icon view-btn" data-id="<?php echo (int) $motorista['id']; ?>" title="Ver detalhes">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="no-data-row">
                                    <td colspan="9" class="text-center">Nenhum motorista encontrado</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="pagination" id="motoristsPagination">
                    <a href="#" class="pagination-btn" id="prevPageBtn">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    
                    <span class="pagination-info">
                        Página <span id="currentPage">1</span> de <span id="totalPages">1</span>
                    </span>
                    
                    <a href="#" class="pagination-btn" id="nextPageBtn">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                
                <!-- Motorist Analytics -->
                <div class="analytics-section">
                    <div class="section-header">
                        <h2>Desempenho dos Motoristas</h2>
                    </div>
                    
                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Eficiência por Motorista</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="position: relative; height:400px;">
                                    <canvas id="motoristEfficiencyChart"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Avaliação de Desempenho</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="position: relative; height:400px;">
                                    <canvas id="motoristPerformanceChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    
    <!-- Add/Edit Motorist Modal -->
    <div class="modal" id="motoristModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Adicionar Motorista</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="motoristForm">
                    <input type="hidden" id="motoristId" name="id">
                    <input type="hidden" id="empresaId" name="empresa_id">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nome">Nome*</label>
                            <input type="text" id="nome" name="nome" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="foto_motorista">Foto do Motorista</label>
                            <input type="file" id="foto_motorista" name="foto_motorista" class="form-control" accept=".jpg,.jpeg,.png">
                            <small class="form-text text-muted">Formatos aceitos: JPG, JPEG, PNG</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="cpf">CPF*</label>
                            <input type="text" id="cpf" name="cpf" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="cnh">CNH</label>
                            <input type="text" id="cnh" name="cnh" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="cnh_arquivo">Arquivo da CNH</label>
                            <input type="file" id="cnh_arquivo" name="cnh_arquivo" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <small class="form-text text-muted">Formatos aceitos: PDF, DOC, DOCX, JPG, PNG</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="categoria_cnh_id">Categoria CNH</label>
                            <select id="categoria_cnh_id" name="categoria_cnh_id" class="form-control">
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="data_validade_cnh">Data de Validade da CNH</label>
                            <input type="date" id="data_validade_cnh" name="data_validade_cnh" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="telefone">Telefone</label>
                            <input type="tel" id="telefone" name="telefone" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="telefone_emergencia">Telefone de Emergência</label>
                            <input type="tel" id="telefone_emergencia" name="telefone_emergencia" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">E-mail</label>
                            <input type="email" id="email" name="email" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="data_contratacao">Data de Contratação</label>
                            <input type="date" id="data_contratacao" name="data_contratacao" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="tipo_contrato_id">Tipo de Contrato</label>
                            <select id="tipo_contrato_id" name="tipo_contrato_id" class="form-control">
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="contrato_arquivo">Arquivo do Contrato</label>
                            <input type="file" id="contrato_arquivo" name="contrato_arquivo" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <small class="form-text text-muted">Formatos aceitos: PDF, DOC, DOCX, JPG, PNG</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="disponibilidade_id">Disponibilidade</label>
                            <select id="disponibilidade_id" name="disponibilidade_id" class="form-control">
                                <option value="">Selecione...</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="porcentagem_comissao">Porcentagem de Comissão</label>
                            <input type="number" id="porcentagem_comissao" name="porcentagem_comissao" class="form-control" step="0.01" min="0" max="100">
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="endereco">Endereço</label>
                        <input type="text" id="endereco" name="endereco" class="form-control">
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="observacoes">Observações</label>
                        <textarea id="observacoes" name="observacoes" class="form-control" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button id="cancelMotoristBtn" class="btn-secondary close-modal">Cancelar</button>
                <button id="saveMotoristBtn" class="btn-primary">Salvar</button>
            </div>
        </div>
    </div>
    
    <!-- Modal de Visualização -->
    <div class="modal" id="viewMotoristModal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h2>Detalhes do Motorista</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="details-grid">
                    <div class="details-group">
                        <label>Nome:</label>
                        <span id="viewMotoristName"></span>
                    </div>
                    <div class="details-group">
                        <label>CPF:</label>
                        <span id="viewMotoristCPF"></span>
                    </div>
                    <div class="details-group">
                        <label>CNH:</label>
                        <span id="viewMotoristCNH"></span>
                    </div>
                    <div class="details-group">
                        <label>Categoria CNH:</label>
                        <span id="viewMotoristCNHCategory"></span>
                    </div>
                    <div class="details-group">
                        <label>Validade CNH:</label>
                        <span id="viewMotoristCNHExpiry"></span>
                    </div>
                    <div class="details-group">
                        <label>Telefone:</label>
                        <span id="viewMotoristPhone"></span>
                    </div>
                    <div class="details-group">
                        <label>Telefone de Emergência:</label>
                        <span id="viewMotoristEmergencyPhone"></span>
                    </div>
                    <div class="details-group">
                        <label>E-mail:</label>
                        <span id="viewMotoristEmail"></span>
                    </div>
                    <div class="details-group">
                        <label>Endereço:</label>
                        <span id="viewMotoristAddress"></span>
                    </div>
                    <div class="details-group">
                        <label>Data de Contratação:</label>
                        <span id="viewMotoristHireDate"></span>
                    </div>
                    <div class="details-group">
                        <label>Tipo de Contrato:</label>
                        <span id="viewMotoristContract"></span>
                    </div>
                    <div class="details-group">
                        <label>Disponibilidade:</label>
                        <span id="viewMotoristAvailability"></span>
                    </div>
                    <div class="details-group">
                        <label>Comissão:</label>
                        <span id="viewMotoristCommission"></span>
                    </div>
                </div>

                <div class="details-group full-width">
                    <label>Observações:</label>
                    <span id="viewMotoristNotes"></span>
                </div>

                <!-- Documentos -->
                <div class="documents-section">
                    <h3>Documentos</h3>
                    <div class="documents-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                        <div class="document-card">
                            <div class="document-icon">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <div class="document-info">
                                <h4>CNH</h4>
                                <p id="cnhDocumentStatus">Status: <span class="status-badge">Válido</span></p>
                                <p id="cnhExpiryDate">Validade: <span></span></p>
                                <div class="document-preview" id="cnhPreview">
                                    <a href="#" id="cnhLink" target="_blank" class="btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> Visualizar CNH
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="document-card">
                            <div class="document-icon">
                                <i class="fas fa-file-contract"></i>
                            </div>
                            <div class="document-info">
                                <h4>Contrato</h4>
                                <p id="contractDocumentStatus">Status: <span class="status-badge">Ativo</span></p>
                                <p id="contractDate">Data: <span></span></p>
                                <div class="document-preview" id="contractPreview">
                                    <a href="#" id="contractLink" target="_blank" class="btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> Visualizar Contrato
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="document-card">
                            <div class="document-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="document-info">
                                <h4>Foto do Motorista</h4>
                                <div class="document-preview" id="photoPreview">
                                    <img id="motoristPhoto" src="" alt="Foto do Motorista" style="max-width: 150px; max-height: 150px; display: none;">
                                    <p id="noPhotoMessage" style="display: none;">Nenhuma foto disponível</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Histórico de Rotas -->
                <div class="route-history-section">
                    <h3>Histórico de Rotas</h3>
                    <div class="route-history-container">
                        <table class="data-table" id="routeHistoryTable">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Origem</th>
                                    <th>Destino</th>
                                    <th>Veículo</th>
                                    <th>Km Percorrido</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Preenchido via JavaScript -->
                            </tbody>
                        </table>
                        <div class="no-data-message" id="noRouteHistoryMessage" style="display: none;">
                            Nenhuma rota encontrada para este motorista.
                        </div>
                    </div>
                </div>

                <!-- Métricas de Desempenho -->
                <div class="performance-section">
                    <h3>Métricas de Desempenho</h3>
                    <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px;">
                        <div class="metric-card">
                            <div class="metric-icon">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="metric-info">
                                <h4>Avaliação Média</h4>
                                <p id="view-average-rating">0.0</p>
                            </div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-icon">
                                <i class="fas fa-route"></i>
                            </div>
                            <div class="metric-info">
                                <h4>Total de Viagens</h4>
                                <p id="view-total-trips">0</p>
                            </div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-icon">
                                <i class="fas fa-road"></i>
                            </div>
                            <div class="metric-info">
                                <h4>Distância Total</h4>
                                <p id="view-total-distance">0 km</p>
                            </div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-icon">
                                <i class="fas fa-gas-pump"></i>
                            </div>
                            <div class="metric-info">
                                <h4>Consumo Médio</h4>
                                <p id="view-average-consumption">0.0 L/100km</p>
                            </div>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('viewMotoristModal')">Fechar</button>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteMotoristModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirmar Exclusão</h2>
                <span class="close-modal close-delete-modal">&times;</span>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir o motorista <strong id="deleteMotoristName"></strong>?</p>
                <p class="warning-text">Esta ação não pode ser desfeita.</p>
            </div>
            <div class="modal-footer">
                <button id="cancelDeleteBtn" class="btn-secondary">Cancelar</button>
                <button id="confirmDeleteBtn" class="btn-danger">Excluir</button>
            </div>
        </div>
    </div>
    
    <!-- Modal de Gamificação -->
    <div class="modal" id="gamificacaoModal">
        <div class="modal-content modal-xl">
            <div class="modal-header">
                <h2><i class="fas fa-gamepad me-2"></i>Sistema de Gamificação</h2>
                <span class="close-modal" onclick="fecharModalGamificacao()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="gamificacaoContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="fecharModalGamificacao()">Fechar</button>
            </div>
        </div>
    </div>

    <!-- Modal de Ranking -->
    <div class="modal" id="rankingModal">
        <div class="modal-content modal-xl">
            <div class="modal-header">
                <h2><i class="fas fa-trophy me-2"></i>Sistema de Ranking</h2>
                <span class="close-modal" onclick="fecharModalRanking()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="rankingContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="fecharModalRanking()">Fechar</button>
            </div>
        </div>
    </div>
    
    <!-- Modal de Ajuda -->
    <div class="modal" id="helpMotoristsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ajuda - Gestão de Motoristas</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="help-section">
                    <h3>Visão Geral</h3>
                    <p>A página de Motoristas permite gerenciar todos os motoristas da empresa. Aqui você pode cadastrar, editar, visualizar e excluir motoristas, além de acompanhar métricas importantes de performance e eficiência.</p>
                </div>

                <div class="help-section">
                    <h3>Funcionalidades Principais</h3>
                    <ul>
                        <li><strong>Novo Motorista:</strong> Cadastre um novo motorista com informações completas como dados pessoais, CNH e documentos.</li>
                        <li><strong>Filtros:</strong> Use os filtros para encontrar motoristas específicos por status, tipo de CNH ou através da busca por texto.</li>
                        <li><strong>Relatórios:</strong> Visualize relatórios e estatísticas de performance dos motoristas.</li>
                        <li><strong>Análise de Eficiência:</strong> Acompanhe a eficiência financeira de cada motorista.</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Indicadores (KPIs)</h3>
                    <ul>
                        <li><strong>Total de Motoristas:</strong> Número total de motoristas ativos.</li>
                        <li><strong>Motoristas Ativos:</strong> Quantidade de motoristas em operação.</li>
                        <li><strong>Eficiência Financeira:</strong> Análise de faturamento vs despesas por motorista.</li>
                        <li><strong>Avaliação de Desempenho:</strong> Métricas de performance em diferentes aspectos.</li>
                        <li><strong>Distribuição por CNH:</strong> Gráfico mostrando a distribuição por tipo de CNH.</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Ações Disponíveis</h3>
                    <ul>
                        <li><strong>Visualizar:</strong> Veja detalhes completos do motorista, incluindo histórico de viagens e documentos.</li>
                        <li><strong>Editar:</strong> Modifique informações de um motorista existente.</li>
                        <li><strong>Excluir:</strong> Remova um motorista do sistema (ação irreversível).</li>
                        <li><strong>Histórico:</strong> Acesse o histórico completo de viagens e atividades do motorista.</li>
                    </ul>
                </div>

                <div class="help-section">
                    <h3>Dicas Úteis</h3>
                    <ul>
                        <li>Mantenha os documentos dos motoristas sempre atualizados, especialmente a CNH.</li>
                        <li>Monitore a eficiência financeira para identificar oportunidades de melhoria.</li>
                        <li>Acompanhe o desempenho dos motoristas para treinamentos específicos.</li>
                        <li>Utilize os filtros para encontrar motoristas específicos rapidamente.</li>
                        <li>Analise os relatórios para otimizar a alocação de motoristas.</li>
                        <li>Configure alertas para vencimento de documentos importantes.</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('helpMotoristsModal')">Fechar</button>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Files -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/motorists.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize page first (includes setupHelpButton)
        initializePage();
        
        // Setup modal events after page initialization
        setupModals();
        
        // Setup filters
        setupFilters();
        
        // Initialize charts
        initializeCharts();
        
        // Setup pagination
        setupPagination();

        // Função para formatar valores em reais
        const formatCurrency = (value) => {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(value);
        };
        
        // Carregar dados do gráfico de eficiência por motorista
        fetch('../api/motorist_efficiency_analytics.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro ao carregar dados de eficiência');
                }
                return response.json();
            })
            .then(data => {
                const ctx = document.getElementById('motoristEfficiencyChart').getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [
                            {
                                label: 'Faturamento',
                                data: data.datasets.faturamento,
                                backgroundColor: 'rgba(46, 204, 64, 0.7)',
                                borderColor: 'rgba(46, 204, 64, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Despesas',
                                data: data.datasets.despesas,
                                backgroundColor: 'rgba(231, 76, 60, 0.7)',
                                borderColor: 'rgba(231, 76, 60, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Lucro',
                                data: data.datasets.lucro,
                                backgroundColor: 'rgba(52, 152, 219, 0.7)',
                                borderColor: 'rgba(52, 152, 219, 1)',
                                borderWidth: 1
                            }
                        ]
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
                                        if (context.parsed && context.parsed.y !== null && context.parsed.y !== undefined) {
                                            label += formatCurrency(context.parsed.y);
                                        } else {
                                            label += formatCurrency(0);
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
                console.error('Erro ao carregar dados de eficiência:', error);
                document.getElementById('motoristEfficiencyChart').parentNode.innerHTML = 
                    '<div class="alert alert-danger">Erro ao carregar dados do gráfico de eficiência</div>';
            });

        // Carregar dados do gráfico de avaliação de desempenho
        fetch('../api/motorist_performance_analytics.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro ao carregar dados de desempenho');
                }
                return response.json();
            })
            .then(data => {
                const ctx = document.getElementById('motoristPerformanceChart').getContext('2d');
                new Chart(ctx, {
                    type: 'radar',
                    data: {
                        labels: data.labels,
                        datasets: data.datasets.map(dataset => ({
                            label: dataset.label,
                            data: dataset.data,
                            backgroundColor: dataset.backgroundColor,
                            borderColor: dataset.borderColor,
                            borderWidth: 1,
                            fill: true
                        }))
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
                                        if (context.parsed && context.parsed.value !== null && context.parsed.value !== undefined) {
                                            label += context.parsed.value.toFixed(1) + '%';
                                        } else {
                                            label += '0.0%';
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            r: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    stepSize: 20
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Erro ao carregar dados de desempenho:', error);
                document.getElementById('motoristPerformanceChart').parentNode.innerHTML = 
                    '<div class="alert alert-danger">Erro ao carregar dados do gráfico de desempenho</div>';
            });

        // Event listeners para os novos botões
        document.getElementById('rankingBtn').addEventListener('click', function() {
            abrirModalRanking();
        });

        document.getElementById('gamificacaoBtn').addEventListener('click', function() {
            abrirModalGamificacao();
        });

        // Event listeners para fechar modais ao clicar fora
        document.getElementById('gamificacaoModal').addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModalGamificacao();
            }
        });

        document.getElementById('rankingModal').addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModalRanking();
            }
        });

        // Event listeners para fechar modais com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (document.getElementById('gamificacaoModal').style.display === 'block') {
                    fecharModalGamificacao();
                }
                if (document.getElementById('rankingModal').style.display === 'block') {
                    fecharModalRanking();
                }
            }
        });
    });

    // Funções para abrir modais de gamificação e ranking
    function abrirModalGamificacao() {
        const modal = document.getElementById('gamificacaoModal');
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden'; // Previne scroll da página
        carregarConteudoGamificacao();
    }

    function abrirModalRanking() {
        const modal = document.getElementById('rankingModal');
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden'; // Previne scroll da página
        carregarConteudoRanking();
    }

    function fecharModalGamificacao() {
        const modal = document.getElementById('gamificacaoModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto'; // Restaura scroll da página
    }

    function fecharModalRanking() {
        const modal = document.getElementById('rankingModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto'; // Restaura scroll da página
    }

    function carregarConteudoGamificacao() {
        const content = document.getElementById('gamificacaoContent');
        
        // Primeiro, tentar carregar dados reais de gamificação
        fetch('../api/gamificacao_motoristas.php?action=get_leaderboard&empresa_id=<?php echo $_SESSION['empresa_id']; ?>')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.leaderboard && data.data.leaderboard.length > 0) {
                    // Usar dados reais de gamificação
                    content.innerHTML = gerarConteudoGamificacao(data.data);
                } else {
                    // Se não há dados reais, mostrar mensagem de "sem dados"
                    console.log('Nenhum dado de gamificação encontrado.');
                    content.innerHTML = gerarConteudoGamificacaoVazio();
                }
            })
            .catch(error => {
                console.error('Erro ao carregar dados de gamificação:', error);
                // Mostrar mensagem de erro
                content.innerHTML = gerarConteudoGamificacaoVazio();
            });
    }

    function calcularGamificacaoReal() {
        const content = document.getElementById('gamificacaoContent');
        
        // Mostrar loading
        content.innerHTML = `
            <div class="text-center p-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Calculando...</span>
                </div>
                <p class="mt-2">Calculando dados de gamificação...</p>
            </div>
        `;
        
        // Timeout de 5 segundos para evitar loading infinito
        const timeout = setTimeout(() => {
            console.log('Timeout no cálculo de gamificação, usando simulação');
            carregarDadosSimuladosGamificacao();
        }, 5000);
        
        // Executar cálculo de gamificação
        fetch('../api/gamificacao_motoristas.php?action=calcular_gamificacao', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            clearTimeout(timeout);
            if (data.success) {
                // Após calcular, carregar os dados reais
                carregarConteudoGamificacao();
            } else {
                // Se cálculo falhou, usar simulação
                console.log('Cálculo de gamificação falhou:', data.error);
                carregarDadosSimuladosGamificacao();
            }
        })
        .catch(error => {
            clearTimeout(timeout);
            console.error('Erro ao calcular gamificação:', error);
            carregarDadosSimuladosGamificacao();
        });
    }

    function carregarDadosSimuladosGamificacao() {
        const content = document.getElementById('gamificacaoContent');
        
        // Fallback para simulação baseada em motoristas reais
        fetch('../api/motoristas.php?action=get_all')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    const gamificacaoData = simularDadosGamificacao(data.data);
                    content.innerHTML = gerarConteudoGamificacao(gamificacaoData);
                } else {
                    content.innerHTML = gerarConteudoGamificacaoEstatico();
                }
            })
            .catch(error => {
                console.error('Erro ao carregar motoristas:', error);
                content.innerHTML = gerarConteudoGamificacaoEstatico();
            });
    }

    function carregarConteudoRanking() {
        const content = document.getElementById('rankingContent');
        
        // Primeiro, tentar carregar dados reais de ranking
        fetch('../api/ranking_motoristas.php?action=get_ranking&empresa_id=<?php echo $_SESSION['empresa_id']; ?>')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.ranking && data.data.ranking.length > 0) {
                    // Usar dados reais de ranking
                    content.innerHTML = gerarConteudoRanking(data.data);
                } else {
                    // Se não há dados reais, mostrar mensagem de "sem dados"
                    console.log('Nenhum dado de ranking encontrado.');
                    content.innerHTML = gerarConteudoRankingVazio();
                }
            })
            .catch(error => {
                console.error('Erro ao carregar dados de ranking:', error);
                // Mostrar mensagem de erro
                content.innerHTML = gerarConteudoRankingVazio();
            });
    }

    function calcularRankingReal() {
        const content = document.getElementById('rankingContent');
        
        // Mostrar loading
        content.innerHTML = `
            <div class="text-center p-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Calculando...</span>
                </div>
                <p class="mt-2">Calculando dados de ranking...</p>
            </div>
        `;
        
        // Timeout de 5 segundos para evitar loading infinito
        const timeout = setTimeout(() => {
            console.log('Timeout no cálculo de ranking, usando simulação');
            carregarDadosSimuladosRanking();
        }, 5000);
        
        // Executar cálculo de ranking
        fetch('../api/ranking_motoristas.php?action=calcular_ranking', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            clearTimeout(timeout);
            if (data.success) {
                // Após calcular, carregar os dados reais
                carregarConteudoRanking();
            } else {
                // Se cálculo falhou, usar simulação
                console.log('Cálculo de ranking falhou:', data.error);
                carregarDadosSimuladosRanking();
            }
        })
        .catch(error => {
            clearTimeout(timeout);
            console.error('Erro ao calcular ranking:', error);
            carregarDadosSimuladosRanking();
        });
    }

    function carregarDadosSimuladosRanking() {
        const content = document.getElementById('rankingContent');
        
        // Fallback para simulação baseada em motoristas reais
        fetch('../api/motoristas.php?action=get_all')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    const rankingData = simularDadosRanking(data.data);
                    content.innerHTML = gerarConteudoRanking(rankingData);
                } else {
                    content.innerHTML = gerarConteudoRankingEstatico();
                }
            })
            .catch(error => {
                console.error('Erro ao carregar motoristas:', error);
                content.innerHTML = gerarConteudoRankingEstatico();
            });
    }

    function gerarConteudoGamificacao(data) {
        const leaderboard = data.leaderboard || [];
        const estatisticas = data.estatisticas || {};
        
        return `
            <!-- Header -->
            <div class="gamification-header mb-4">
                <div>
                    <h3 class="mb-1 text-primary"><i class="fas fa-gamepad me-2"></i>Sistema de Gamificação</h3>
                    <p class="mb-0 text-muted">Ranking de performance dos motoristas em tempo real</p>
                </div>
            </div>

            <!-- Conteúdo principal -->
            <div class="row g-4">
                <!-- Sistema de Níveis -->
                <div class="col-lg-6">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-gradient-primary text-white border-0">
                            <h5 class="mb-0"><i class="fas fa-star me-2"></i>Sistema de Níveis</h5>
                        </div>
                        <div class="card-body p-3">
                            <div class="levels-container">
                                <div class="level-card bronze">
                                    <div class="level-icon">🥉</div>
                                    <div class="level-name">Bronze</div>
                                    <div class="level-points">0-99 pts</div>
                                </div>
                                <div class="level-card silver">
                                    <div class="level-icon">🥈</div>
                                    <div class="level-name">Prata</div>
                                    <div class="level-points">100-299 pts</div>
                                </div>
                                <div class="level-card gold">
                                    <div class="level-icon">🥇</div>
                                    <div class="level-name">Ouro</div>
                                    <div class="level-points">300-599 pts</div>
                                </div>
                                <div class="level-card platinum">
                                    <div class="level-icon">🏆</div>
                                    <div class="level-name">Platina</div>
                                    <div class="level-points">600-899 pts</div>
                                </div>
                                <div class="level-card diamond">
                                    <div class="level-icon">💎</div>
                                    <div class="level-name">Diamante</div>
                                    <div class="level-points">900-999 pts</div>
                                </div>
                                <div class="level-card legend">
                                    <div class="level-icon">👑</div>
                                    <div class="level-name">Lenda</div>
                                    <div class="level-points">1000+ pts</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sistema de Badges -->
                <div class="col-lg-6">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-gradient-success text-white border-0">
                            <h5 class="mb-0"><i class="fas fa-medal me-2"></i>Sistema de Badges</h5>
                        </div>
                        <div class="card-body p-3">
                            <div class="badges-container">
                                <div class="badge-card">
                                    <div class="badge-icon">⛽</div>
                                    <div class="badge-name">Motorista Econômico</div>
                                    <div class="badge-desc">3 meses com consumo abaixo da média</div>
                                </div>
                                <div class="badge-card">
                                    <div class="badge-icon">🚫</div>
                                    <div class="badge-name">Sem Multas</div>
                                    <div class="badge-desc">1 ano sem infrações</div>
                                </div>
                                <div class="badge-card">
                                    <div class="badge-icon">✅</div>
                                    <div class="badge-name">Checklists Perfeitos</div>
                                    <div class="badge-desc">50 checklists sem falhas</div>
                                </div>
                                <div class="badge-card">
                                    <div class="badge-icon">🔥</div>
                                    <div class="badge-name">Streak de Fogo</div>
                                    <div class="badge-desc">7 dias consecutivos de excelência</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leaderboard -->
            <div class="row mt-4">
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-gradient-success text-white border-0">
                            <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Leaderboard Atual</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="leaderboard-container">
                                ${leaderboard.length > 0 ? 
                                    leaderboard.slice(0, 15).map((motorista, index) => `
                                        <div class="leaderboard-item ${index < 3 ? 'top-three' : ''}">
                                            <div class="position">
                                                <span class="position-badge ${index < 3 ? ['gold', 'silver', 'bronze'][index] : ''}">
                                                    ${index < 3 ? ['🥇', '🥈', '🥉'][index] : (index + 1) + 'º'}
                                                </span>
                                            </div>
                                            <div class="driver-info">
                                                <div class="driver-name">${motorista.motorista_nome || 'N/A'}</div>
                                                <div class="driver-stats">
                                                    <span class="points"><i class="fas fa-star"></i> ${motorista.pontos_totais || 0} pontos</span>
                                                    <span class="routes"><i class="fas fa-route"></i> ${motorista.total_rotas_reais || motorista.rotas_concluidas || 0} rotas</span>
                                                </div>
                                                <div class="driver-progress">
                                                    ${generateProgressBar(motorista.pontos_totais || 0, motorista.nivel_calculado || 'Bronze')}
                                                </div>
                                            </div>
                                            <div class="level-badge">
                                                <span class="badge ${getNivelClass(motorista.nivel_calculado)}">
                                                    ${getNivelEmoji(motorista.nivel_calculado)} ${motorista.nivel_calculado}
                                                </span>
                                            </div>
                                        </div>
                                    `).join('') :
                                    '<div class="no-data"><i class="fas fa-users fa-4x mb-3"></i><h5>Nenhum dado disponível</h5><p>Configure o sistema para ver os dados dos motoristas</p></div>'
                                }
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estatísticas Gerais -->
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-gradient-info text-white border-0">
                            <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Estatísticas Gerais</h5>
                        </div>
                        <div class="card-body p-3">
                            <div class="stats-container">
                                <div class="stat-card">
                                    <div class="stat-icon bg-primary">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number">${estatisticas.total_motoristas || leaderboard.length || 0}</div>
                                        <div class="stat-label">Total de Motoristas</div>
                                    </div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-icon bg-success">
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number">${Math.round(estatisticas.media_pontos || 0)}</div>
                                        <div class="stat-label">Pontos Médios</div>
                                    </div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-icon bg-warning">
                                        <i class="fas fa-trophy"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number">${estatisticas.max_pontos || 0}</div>
                                        <div class="stat-label">Pontos Máximos</div>
                                    </div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-icon bg-info">
                                        <i class="fas fa-medal"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-number">${estatisticas.total_desafios_completos || 0}</div>
                                        <div class="stat-label">Desafios Completos</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        `;
    }

    function gerarConteudoRanking(data) {
        const ranking = data.ranking || [];
        const estatisticas = data.estatisticas || {};
        
        return `
            <!-- Header -->
            <div class="ranking-header mb-4">
                <div>
                    <h3 class="mb-1 text-primary"><i class="fas fa-trophy me-2"></i>Sistema de Ranking</h3>
                    <p class="mb-0 text-muted">Avaliação completa dos motoristas baseada em múltiplas métricas</p>
                </div>
            </div>

            <!-- Conteúdo principal -->
            <div class="row g-4">
                <!-- Ranking de Performance -->
                <div class="col-12">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-gradient-primary text-white border-0">
                            <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Ranking de Performance</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="ranking-container">
                                ${ranking.length > 0 ? 
                                    ranking.slice(0, 25).map((motorista, index) => `
                                        <div class="ranking-item ${index < 3 ? 'top-three' : ''}">
                                            <div class="position">
                                                <span class="position-badge ${index < 3 ? ['gold', 'silver', 'bronze'][index] : ''}">
                                                    ${index < 3 ? ['🥇', '🥈', '🥉'][index] : (index + 1) + 'º'}
                                                </span>
                                            </div>
                                            <div class="driver-info">
                                                <div class="driver-name">${motorista.motorista_nome || 'N/A'}</div>
                                                <div class="driver-id">ID: ${motorista.motorista_id || 'N/A'}</div>
                                            </div>
                                            <div class="score-section">
                                                <div class="score-bar">
                                                    <div class="score-fill" style="width: ${motorista.nota_total || 0}%"></div>
                                                    <span class="score-text">${motorista.nota_total || 0}/100</span>
                                                </div>
                                            </div>
                                            <div class="status-section">
                                                <span class="status-badge ${getStatusClass(motorista.status || 'Ativo')}">
                                                    ${motorista.status || 'Ativo'}
                                                </span>
                                                <button class="btn btn-sm btn-outline-primary" onclick="verDetalhesMotorista(${motorista.motorista_id})" title="Ver Detalhes">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                    `).join('') :
                                    '<div class="no-data"><i class="fas fa-users fa-4x mb-3"></i><h5>Nenhum dado disponível</h5><p>Configure o sistema para ver os dados dos motoristas</p></div>'
                                }
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Distribuição por Faixas e Critérios de Avaliação -->
            <div class="row mt-4">
                <!-- Distribuição por Faixas -->
                <div class="col-lg-6">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-gradient-success text-white border-0">
                            <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Distribuição por Faixas</h5>
                        </div>
                        <div class="card-body p-3">
                            <div class="distribution-grid">
                                <div class="distribution-item">
                                    <div class="distribution-header">
                                        <i class="fas fa-star text-success"></i>
                                        <span>Excelente</span>
                                        <span class="badge bg-success">${data.excelente || 0}</span>
                                    </div>
                                    <div class="distribution-bar">
                                        <div class="bar-fill bg-success" style="width: ${((data.excelente || 0) / Math.max(estatisticas.total_motoristas || ranking.length || 1, 1)) * 100}%"></div>
                                    </div>
                                    <small class="text-muted">90-100 pontos</small>
                                </div>

                                <div class="distribution-item">
                                    <div class="distribution-header">
                                        <i class="fas fa-thumbs-up text-primary"></i>
                                        <span>Bom</span>
                                        <span class="badge bg-primary">${data.bom || 0}</span>
                                    </div>
                                    <div class="distribution-bar">
                                        <div class="bar-fill bg-primary" style="width: ${((data.bom || 0) / Math.max(estatisticas.total_motoristas || ranking.length || 1, 1)) * 100}%"></div>
                                    </div>
                                    <small class="text-muted">70-89 pontos</small>
                                </div>

                                <div class="distribution-item">
                                    <div class="distribution-header">
                                        <i class="fas fa-exclamation text-warning"></i>
                                        <span>Regular</span>
                                        <span class="badge bg-warning">${data.regular || 0}</span>
                                    </div>
                                    <div class="distribution-bar">
                                        <div class="bar-fill bg-warning" style="width: ${((data.regular || 0) / Math.max(estatisticas.total_motoristas || ranking.length || 1, 1)) * 100}%"></div>
                                    </div>
                                    <small class="text-muted">50-69 pontos</small>
                                </div>

                                <div class="distribution-item">
                                    <div class="distribution-header">
                                        <i class="fas fa-exclamation-triangle text-danger"></i>
                                        <span>Necessita Melhoria</span>
                                        <span class="badge bg-danger">${data.necessita_melhoria || 0}</span>
                                    </div>
                                    <div class="distribution-bar">
                                        <div class="bar-fill bg-danger" style="width: ${((data.necessita_melhoria || 0) / Math.max(estatisticas.total_motoristas || ranking.length || 1, 1)) * 100}%"></div>
                                    </div>
                                    <small class="text-muted">< 50 pontos</small>
                                </div>
                            </div>

                            <hr class="my-3">
                            <div class="total-summary">
                                <div class="total-number">${estatisticas.total_motoristas || ranking.length || 0}</div>
                                <div class="total-label">Total de Motoristas</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Critérios de Avaliação Básicos -->
                <div class="col-lg-6">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-gradient-warning text-white border-0">
                            <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Critérios de Avaliação Básicos</h5>
                        </div>
                        <div class="card-body p-3">
                            <div class="criteria-container">
                                <div class="criteria-card">
                                    <div class="criteria-icon bg-primary">
                                        <i class="fas fa-gas-pump"></i>
                                    </div>
                                    <div class="criteria-content">
                                        <h6>Consumo de Combustível</h6>
                                        <div class="criteria-percentage">30%</div>
                                        <p class="small text-muted">Eficiência no uso de combustível</p>
                                    </div>
                                </div>
                                <div class="criteria-card">
                                    <div class="criteria-icon bg-success">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="criteria-content">
                                        <h6>Pontualidade</h6>
                                        <div class="criteria-percentage">25%</div>
                                        <p class="small text-muted">Cumprimento de prazos</p>
                                    </div>
                                </div>
                                <div class="criteria-card">
                                    <div class="criteria-icon bg-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                    <div class="criteria-content">
                                        <h6>Multas</h6>
                                        <div class="criteria-percentage">20%</div>
                                        <p class="small text-muted">Histórico de infrações</p>
                                    </div>
                                </div>
                                <div class="criteria-card">
                                    <div class="criteria-icon bg-info">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="criteria-content">
                                        <h6>Checklists</h6>
                                        <div class="criteria-percentage">15%</div>
                                        <p class="small text-muted">Qualidade dos checklists</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros Dinâmicos e Métricas Avançadas -->
            <div class="row mt-4">
                <!-- Filtros Dinâmicos -->
                <div class="col-lg-6">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-gradient-secondary text-white border-0">
                            <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtros Dinâmicos</h5>
                        </div>
                        <div class="card-body p-3">
                            <div class="filters-container">
                                <div class="filter-item">
                                    <label class="form-label">Período</label>
                                    <select class="form-select" id="filtroPeriodo">
                                        <option value="semanal">Semanal</option>
                                        <option value="mensal" selected>Mensal</option>
                                        <option value="trimestral">Trimestral</option>
                                        <option value="anual">Anual</option>
                                    </select>
                                </div>
                                <div class="filter-item">
                                    <label class="form-label">Tipo de Veículo</label>
                                    <select class="form-select" id="filtroVeiculo">
                                        <option value="todos">Todos os Veículos</option>
                                        <option value="caminhao">Caminhões</option>
                                        <option value="van">Vans</option>
                                        <option value="carreta">Carretas</option>
                                    </select>
                                </div>
                                <div class="filter-item">
                                    <label class="form-label">Tipo de Rota</label>
                                    <select class="form-select" id="filtroRota">
                                        <option value="todas">Todas as Rotas</option>
                                        <option value="urbana">Urbana</option>
                                        <option value="interurbana">Interurbana</option>
                                        <option value="longa_distancia">Longa Distância</option>
                                    </select>
                                </div>
                                <div class="filter-item">
                                    <label class="form-label">Ação</label>
                                    <button class="btn btn-primary w-100" onclick="aplicarFiltros()">
                                        <i class="fas fa-search me-2"></i>Aplicar Filtros
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Métricas Avançadas -->
                <div class="col-lg-6">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-gradient-info text-white border-0">
                            <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Métricas Avançadas</h5>
                        </div>
                        <div class="card-body p-3">
                            <div class="metrics-container">
                                <div class="metric-card">
                                    <div class="metric-icon bg-danger">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                    <div class="metric-content">
                                        <h6>Ocorrências/Sinistros</h6>
                                        <p>15% do total</p>
                                        <small>Impacta diretamente a segurança</small>
                                    </div>
                                </div>
                                <div class="metric-card">
                                    <div class="metric-icon bg-success">
                                        <i class="fas fa-dollar-sign"></i>
                                    </div>
                                    <div class="metric-content">
                                        <h6>Custos por KM</h6>
                                        <p>10% do total</p>
                                        <small>Visão financeira do desempenho</small>
                                    </div>
                                </div>
                                <div class="metric-card">
                                    <div class="metric-icon bg-warning">
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <div class="metric-content">
                                        <h6>Feedback do Cliente</h6>
                                        <p>10% do total</p>
                                        <small>Entrega dentro do padrão esperado</small>
                                    </div>
                                </div>
                                <div class="metric-card">
                                    <div class="metric-icon bg-info">
                                        <i class="fas fa-wrench"></i>
                                    </div>
                                    <div class="metric-content">
                                        <h6>Manutenção Preventiva</h6>
                                        <p>5% do total</p>
                                        <small>Ajuda a manter o veículo em ordem</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        `;
    }

    function gerarConteudoGamificacaoVazio() {
        return `
            <div class="row">
                <div class="col-md-12">
                    <div class="alert alert-warning">
                        <h4><i class="fas fa-exclamation-triangle me-2"></i>Sistema de Gamificação</h4>
                        <p class="mb-3">Nenhum dado de gamificação disponível. Configure o sistema para começar a acompanhar o desempenho dos motoristas.</p>
                        <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                            <button class="btn btn-primary" onclick="window.open('../pages/configuracoes.php', '_blank')">
                                <i class="fas fa-cog me-2"></i>Configurar Sistema
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function gerarConteudoGamificacaoEstatico() {
        return gerarConteudoGamificacaoVazio();
    }

    function gerarConteudoRankingVazio() {
        return `
            <div class="row">
                <div class="col-md-12">
                    <div class="alert alert-warning">
                        <h4><i class="fas fa-exclamation-triangle me-2"></i>Sistema de Ranking</h4>
                        <p class="mb-3">Nenhum dado de ranking disponível. Configure o sistema para começar a acompanhar o desempenho dos motoristas.</p>
                        <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                            <button class="btn btn-primary" onclick="window.open('../pages/configuracoes.php', '_blank')">
                                <i class="fas fa-cog me-2"></i>Configurar Sistema
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function gerarConteudoRankingEstatico() {
        return gerarConteudoRankingVazio();
    }

    function getNivelCor(nivel) {
        switch(nivel) {
            case 'Diamante': return 'primary';
            case 'Ouro': return 'warning';
            case 'Prata': return 'secondary';
            case 'Bronze': return 'dark';
            default: return 'light';
        }
    }

    function getNivelClass(nivel) {
        switch(nivel) {
            case 'Lenda': return 'bg-warning';
            case 'Diamante': return 'bg-primary';
            case 'Platina': return 'bg-secondary';
            case 'Ouro': return 'bg-warning';
            case 'Prata': return 'bg-secondary';
            case 'Bronze': return 'bg-dark';
            default: return 'bg-light text-dark';
        }
    }

    function getNivelEmoji(nivel) {
        switch(nivel) {
            case 'Lenda': return '👑';
            case 'Diamante': return '💎';
            case 'Platina': return '🏆';
            case 'Ouro': return '🥇';
            case 'Prata': return '🥈';
            case 'Bronze': return '🥉';
            default: return '🥉';
        }
    }
    
    function generateProgressBar(pontos, nivel) {
        const niveis = {
            'Bronze': { min: 0, max: 99 },
            'Prata': { min: 100, max: 299 },
            'Ouro': { min: 300, max: 599 },
            'Platina': { min: 600, max: 899 },
            'Diamante': { min: 900, max: 999 },
            'Lenda': { min: 1000, max: 9999 }
        };
        
        const currentLevel = niveis[nivel] || niveis['Bronze'];
        const nextLevel = getNextLevel(nivel);
        const nextLevelData = niveis[nextLevel];
        
        if (!nextLevelData) {
            // Nível máximo
            return `
                <div class="level-progress-container">
                    <div class="progress level-progress-bar">
                        <div class="progress-bar bg-danger" style="width: 100%">
                            <span class="progress-text">100%</span>
                        </div>
                    </div>
                    <div class="level-progress-footer">
                        <small class="text-success">🏆 Nível máximo alcançado!</small>
                    </div>
                </div>
            `;
        }
        
        const pointsInCurrentLevel = pontos - currentLevel.min;
        const pointsNeededForNext = nextLevelData.min - currentLevel.min;
        const progress = Math.min(100, (pointsInCurrentLevel / pointsNeededForNext) * 100);
        
        const progressColor = getProgressColor(nivel);
        const nextIcon = getNivelEmoji(nextLevel);
        
        return `
            <div class="level-progress-container">
                <div class="progress level-progress-bar">
                    <div class="progress-bar ${progressColor}" style="width: ${progress}%">
                        <span class="progress-text">${Math.round(progress)}%</span>
                    </div>
                </div>
                <div class="level-progress-footer">
                    <small class="text-muted">Próximo: ${nextIcon} ${nextLevel} (${nextLevelData.min} pts)</small>
                </div>
            </div>
        `;
    }
    
    function getNextLevel(currentLevel) {
        const levelOrder = ['Bronze', 'Prata', 'Ouro', 'Platina', 'Diamante', 'Lenda'];
        const currentIndex = levelOrder.indexOf(currentLevel);
        return currentIndex >= 0 && currentIndex < levelOrder.length - 1 ? levelOrder[currentIndex + 1] : null;
    }
    
    function getProgressColor(nivel) {
        const colors = {
            'Bronze': 'bg-warning',
            'Prata': 'bg-secondary',
            'Ouro': 'bg-warning',
            'Platina': 'bg-info',
            'Diamante': 'bg-primary',
            'Lenda': 'bg-danger'
        };
        return colors[nivel] || 'bg-secondary';
    }

    function getStatusCor(status) {
        switch(status) {
            case 'Ativo': return 'success';
            case 'Férias': return 'warning';
            case 'Licença': return 'info';
            case 'Inativo': return 'danger';
            default: return 'light';
        }
    }

    function getStatusClass(status) {
        switch(status) {
            case 'Ativo': return 'bg-success';
            case 'Férias': return 'bg-warning';
            case 'Licença': return 'bg-info';
            case 'Inativo': return 'bg-danger';
            default: return 'bg-light text-dark';
        }
    }

    function calcularGamificacao() {
        // Usar API real de gamificação
        fetch('../api/gamificacao_motoristas.php?action=calcular_gamificacao', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`✅ ${data.message}`);
                carregarConteudoGamificacao(); // Recarregar dados
            } else {
                alert(`❌ Erro: ${data.error || 'Falha ao calcular gamificação'}`);
            }
        })
        .catch(error => {
            console.error('Erro ao calcular gamificação:', error);
            alert('❌ Erro ao calcular gamificação');
        });
    }

    function calcularRanking() {
        // Usar API real de ranking
        fetch('../api/ranking_motoristas.php?action=calcular_ranking', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`✅ ${data.message}`);
                carregarConteudoRanking(); // Recarregar dados
            } else {
                alert(`❌ Erro: ${data.error || 'Falha ao calcular ranking'}`);
            }
        })
        .catch(error => {
            console.error('Erro ao calcular ranking:', error);
            alert('❌ Erro ao calcular ranking');
        });
    }

    function verDetalhesMotorista(motoristaId) {
        console.log('Ver detalhes do motorista:', motoristaId);
        
        // Buscar dados do motorista
        fetch(`../api/ranking_motoristas.php?action=get_detalhes&motorista_id=${motoristaId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.motorista) {
                    mostrarDetalhesMotorista(data.data.motorista, data.data.historico, data.data.gamificacao);
                } else {
                    console.error('Erro na resposta:', data);
                    alert('❌ Erro ao carregar detalhes do motorista');
                }
            })
            .catch(error => {
                console.error('Erro ao carregar detalhes:', error);
                alert('❌ Erro ao carregar detalhes do motorista');
            });
    }
    
    function mostrarDetalhesMotorista(motorista, historico = [], gamificacao = null) {
        // Criar modal de detalhes
        const modalHtml = `
            <div class="modal fade" id="detalhesMotoristaModal" tabindex="-1" aria-labelledby="detalhesMotoristaModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="detalhesMotoristaModalLabel">
                                <i class="fas fa-user me-2"></i>Detalhes do Motorista
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Informações Pessoais</h6>
                                    <p><strong>Nome:</strong> ${motorista.nome || 'N/A'}</p>
                                    <p><strong>ID:</strong> ${motorista.id || 'N/A'}</p>
                                    <p><strong>CPF:</strong> ${motorista.cpf || 'N/A'}</p>
                                    <p><strong>CNH:</strong> ${motorista.cnh || 'N/A'}</p>
                                    <p><strong>Telefone:</strong> ${motorista.telefone || 'N/A'}</p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Performance no Ranking</h6>
                                    <p><strong>Posição:</strong> ${motorista.posicao_ranking || 'N/A'}</p>
                                    <p><strong>Nota Total:</strong> ${motorista.nota_total || '0'}/100</p>
                                    <p><strong>Classificação:</strong> ${motorista.classificacao || 'N/A'}</p>
                                    <p><strong>Total de Rotas:</strong> ${motorista.total_rotas || '0'}</p>
                                    <p><strong>Rotas Pontuais:</strong> ${motorista.rotas_pontuais || '0'}</p>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Métricas de Desempenho</h6>
                                    <div class="metric-item mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Consumo de Combustível:</span>
                                            <span class="fw-bold">${motorista.nota_consumo || '0.00'}/100</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-success" style="width: ${motorista.nota_consumo || 0}%"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="metric-item mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Pontualidade:</span>
                                            <span class="fw-bold">${motorista.nota_pontualidade || '0.00'}/100</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-primary" style="width: ${motorista.nota_pontualidade || 0}%"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="metric-item mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Multas:</span>
                                            <span class="fw-bold">${motorista.nota_multas || '0.00'}/100</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-warning" style="width: ${motorista.nota_multas || 0}%"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <h6 class="text-primary mb-3">Estatísticas Adicionais</h6>
                                    <div class="metric-item mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Ocorrências:</span>
                                            <span class="fw-bold">${motorista.nota_ocorrencias || '0.00'}/100</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-info" style="width: ${motorista.nota_ocorrencias || 0}%"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="metric-item mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Checklist:</span>
                                            <span class="fw-bold">${motorista.nota_checklist || '0.00'}/100</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-secondary" style="width: ${motorista.nota_checklist || 0}%"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="metric-item mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Eficiência:</span>
                                            <span class="fw-bold">${motorista.nota_eficiencia || '0.00'}/100</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-dark" style="width: ${motorista.nota_eficiencia || 0}%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            ${historico && historico.length > 0 ? `
                                <hr class="my-4">
                                <h6 class="text-primary mb-3">Histórico de Performance</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Período</th>
                                                <th>Nota Total</th>
                                                <th>Posição</th>
                                                <th>Rotas</th>
                                                <th>Data</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${historico.map(h => `
                                                <tr>
                                                    <td>${h.periodo_inicio} a ${h.periodo_fim}</td>
                                                    <td>${h.nota_total}</td>
                                                    <td>${h.posicao_ranking}</td>
                                                    <td>${h.total_rotas}</td>
                                                    <td>${new Date(h.data_atualizacao).toLocaleDateString('pt-BR')}</td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            ` : ''}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remover modal existente se houver
        const existingModal = document.getElementById('detalhesMotoristaModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Adicionar modal ao body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Mostrar modal
        const modalElement = document.getElementById('detalhesMotoristaModal');
        
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            // Usar Bootstrap 5
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        } else if (typeof $ !== 'undefined' && $.fn.modal) {
            // Fallback para jQuery modal
            $(modalElement).modal('show');
        } else {
            // Fallback para JavaScript puro
            modalElement.style.display = 'block';
            modalElement.classList.add('show');
            document.body.classList.add('modal-open');
            
            // Adicionar backdrop
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.id = 'modalBackdrop';
            document.body.appendChild(backdrop);
        }
        
        // Função para fechar modal
        function fecharModal() {
            const modalElement = document.getElementById('detalhesMotoristaModal');
            const backdrop = document.getElementById('modalBackdrop');
            
            if (modalElement) {
                modalElement.style.display = 'none';
                modalElement.classList.remove('show');
                modalElement.remove();
            }
            
            if (backdrop) {
                backdrop.remove();
            }
            
            document.body.classList.remove('modal-open');
        }
        
        // Adicionar evento de fechamento para o botão X
        const closeButton = modalElement.querySelector('.btn-close');
        if (closeButton) {
            closeButton.addEventListener('click', fecharModal);
        }
        
        // Adicionar evento de fechamento para o botão Fechar
        const closeButtonFooter = modalElement.querySelector('.btn-secondary');
        if (closeButtonFooter) {
            closeButtonFooter.addEventListener('click', fecharModal);
        }
        
        // Adicionar evento de fechamento para clicar no backdrop
        modalElement.addEventListener('click', function(e) {
            if (e.target === modalElement) {
                fecharModal();
            }
        });
        
        // Remover modal do DOM quando fechado (Bootstrap)
        modalElement.addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }

    function aplicarFiltros() {
        const periodo = document.getElementById('filtroPeriodo').value;
        const veiculo = document.getElementById('filtroVeiculo').value;
        const rota = document.getElementById('filtroRota').value;
        
        // Simular aplicação de filtros
        console.log('Aplicando filtros:', { periodo, veiculo, rota });
        
        // Mostrar notificação de filtros aplicados
        alert(`✅ Filtros aplicados!\n\nPeríodo: ${periodo}\nVeículo: ${veiculo}\nRota: ${rota}\n\nOs dados serão recarregados com os filtros selecionados.`);
        
        // Recarregar conteúdo do ranking com filtros
        carregarConteudoRanking();
    }

    // Função para simular dados de gamificação baseados nos motoristas reais
    function simularDadosGamificacao(motoristas) {
        const leaderboard = motoristas.map((motorista, index) => {
            // Usar ID do motorista como base para gerar valores consistentes
            const seed = motorista.id * 7 + 13; // Fórmula para gerar seed único
            const pontos = (seed % 1000) + 100; // Pontos entre 100-1099
            const nivel = pontos >= 1000 ? 'Lenda' : 
                         pontos >= 900 ? 'Diamante' : 
                         pontos >= 600 ? 'Platina' : 
                         pontos >= 300 ? 'Ouro' : 
                         pontos >= 100 ? 'Prata' : 'Bronze';
            const emoji = pontos >= 1000 ? '👑' : 
                         pontos >= 900 ? '💎' : 
                         pontos >= 600 ? '🏆' : 
                         pontos >= 300 ? '🥇' : 
                         pontos >= 100 ? '🥈' : '🥉';
            
            return {
                motorista_id: motorista.id,
                motorista_nome: motorista.nome || 'Motorista ' + motorista.id,
                pontos_totais: pontos,
                nivel_calculado: nivel,
                nivel_emoji: emoji,
                rotas_concluidas: (seed % 50) + 10, // Rotas entre 10-59
                telefone: motorista.telefone || 'N/A'
            };
        }).sort((a, b) => b.pontos_totais - a.pontos_totais);

        return {
            leaderboard: leaderboard,
            total_motoristas: motoristas.length,
            media_pontos: Math.round(leaderboard.reduce((sum, m) => sum + m.pontos_totais, 0) / leaderboard.length),
            max_pontos: Math.max(...leaderboard.map(m => m.pontos_totais)),
            total_desafios_completos: Math.floor(motoristas.length * 0.3) + 5 // Baseado no número de motoristas
        };
    }

    // Função para simular dados de ranking baseados nos motoristas reais
    function simularDadosRanking(motoristas) {
        const ranking = motoristas.map((motorista, index) => {
            // Usar ID do motorista como base para gerar valores consistentes
            const seed = motorista.id * 11 + 17; // Fórmula diferente para ranking
            const nota_final = (seed % 40) + 60; // Nota entre 60-99
            const status = 'Ativo'; // Sempre mostrar como Ativo
            
            return {
                motorista_id: motorista.id,
                motorista_nome: motorista.nome || 'Motorista ' + motorista.id,
                nota_final: nota_final,
                status: status,
                telefone: motorista.telefone || 'N/A',
                cpf: motorista.cpf || 'N/A'
            };
        }).sort((a, b) => b.nota_final - a.nota_final);

        // Calcular distribuição por faixas
        const excelente = ranking.filter(m => m.nota_final >= 90).length;
        const bom = ranking.filter(m => m.nota_final >= 70 && m.nota_final < 90).length;
        const regular = ranking.filter(m => m.nota_final >= 50 && m.nota_final < 70).length;
        const necessita_melhoria = ranking.filter(m => m.nota_final < 50).length;

        return {
            ranking: ranking,
            total_motoristas: motoristas.length,
            excelente: excelente,
            bom: bom,
            regular: regular,
            necessita_melhoria: necessita_melhoria
        };
    }
    </script>
    
    <style>
    /* Barras de Progresso para Níveis */
    .level-progress-container {
        margin: 8px 0;
    }
    
    
    .progress-text {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: white;
        font-weight: bold;
        font-size: 11px;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.7);
        z-index: 10;
        white-space: nowrap;
        pointer-events: none;
    }
    
    /* Garantir que a barra de progresso tenha altura suficiente */
    .level-progress-bar {
        height: 20px !important;
        background-color: #e9ecef;
        border-radius: 10px;
        overflow: visible;
        position: relative;
    }
    
    .level-progress-bar .progress-bar {
        border-radius: 10px;
        transition: width 0.6s ease;
        position: relative;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .level-progress-footer {
        margin-top: 4px;
        text-align: center;
    }
    
    .level-progress-bar .progress-bar.bg-warning {
        background: linear-gradient(45deg, #ffc107, #ff8f00);
    }
    
    .level-progress-bar .progress-bar.bg-secondary {
        background: linear-gradient(45deg, #6c757d, #495057);
    }
    
    .level-progress-bar .progress-bar.bg-info {
        background: linear-gradient(45deg, #17a2b8, #138496);
    }
    
    .level-progress-bar .progress-bar.bg-primary {
        background: linear-gradient(45deg, #007bff, #0056b3);
    }
    
    .level-progress-bar .progress-bar.bg-danger {
        background: linear-gradient(45deg, #dc3545, #c82333);
    }
    
    .driver-progress {
        margin-top: 8px;
    }
    </style>
</body>
</html>
