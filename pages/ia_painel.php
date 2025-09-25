<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Set page title
$page_title = "Painel Inteligente (IA)";

// Requer autenticação
require_authentication();

// Validar empresa_id com tratamento de erro
if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) {
    error_log("Erro: Empresa não identificada na sessão. Sessão: " . print_r($_SESSION, true));
    // Redirecionar para login em vez de throw exception
    header("Location: /sistema-frotas/login.php");
    exit;
}
$empresa_id = $_SESSION['empresa_id'];

// Parâmetros de paginação
$items_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Inicializa arrays vazios para evitar erros
$dados_consumo = [];
$dados_manutencao = [];
$dados_rotas = [];
$alertas_sistema = [];
$recomendacoes_sistema = [];
$insights_sistema = [];
$previsoes_custos = [];
$eficiencia_motoristas = [];
$vida_util_veiculos = [];
$otimizacao_rotas = [];

// Totais para paginação
$total_alertas = 0;
$total_recomendacoes = 0;
$total_insights = 0;
$total_previsoes = 0;
$total_motoristas = 0;
$total_veiculos = 0;
$total_rotas = 0;

try {
    // Obtém conexão com o banco usando a função padrão
    $conn = getConnection();
    
    // Verificar se a empresa tem dados básicos
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM veiculos WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $total_veiculos_empresa = $stmt->fetch()['total'];
    
    if ($total_veiculos_empresa > 0) {
        // Incluir classes IA apenas se há veículos
require_once '../IA/analise.php';
require_once '../IA/alertas.php';
require_once '../IA/recomendacoes.php';
require_once '../IA/insights.php';
require_once '../IA/notificacoes.php';
require_once '../IA/config.php';
require_once '../IA/AnaliseAvancada.php';

        // Inicializa classes com tratamento de erro
        try {
            $analise = new Analise($conn, $empresa_id);
            $alertas = new Alertas($conn, $empresa_id);
            $recomendacoes = new Recomendacoes($conn, $empresa_id);
            $insights = new Insights($conn, $empresa_id);
            $notificacoes = new Notificacoes($empresa_id);
    $config = new ConfiguracoesIA($empresa_id);
            $analise_avancada = new AnaliseAvancada($conn, $empresa_id);

            // Obtém dados com tratamento individual de erro
    try {
        $dados_consumo = $analise->analisarConsumo();
    } catch (Exception $e) {
        error_log("Erro ao obter dados de consumo: " . $e->getMessage());
        $dados_consumo = [];
    }

    try {
        $dados_manutencao = $analise->analisarManutencao();
    } catch (Exception $e) {
        error_log("Erro ao obter dados de manutenção: " . $e->getMessage());
        $dados_manutencao = [];
    }

    try {
        $dados_rotas = $analise->analisarRotas();
    } catch (Exception $e) {
        error_log("Erro ao obter dados de rotas: " . $e->getMessage());
        $dados_rotas = [];
    }

    try {
                $todos_alertas = $alertas->obterTodosAlertas();
                $todos_alertas = sortByDate($todos_alertas, 'data_criacao');
                $total_alertas = count($todos_alertas);
                $alertas_sistema = array_slice($todos_alertas, $offset, $items_per_page);
    } catch (Exception $e) {
        error_log("Erro ao obter alertas: " . $e->getMessage());
        $alertas_sistema = [];
    }

    try {
                $todas_recomendacoes = $recomendacoes->obterTodasRecomendacoes();
                $todas_recomendacoes = sortByDate($todas_recomendacoes, 'data_criacao');
                $total_recomendacoes = count($todas_recomendacoes);
                $recomendacoes_sistema = array_slice($todas_recomendacoes, $offset, $items_per_page);
    } catch (Exception $e) {
        error_log("Erro ao obter recomendações: " . $e->getMessage());
        $recomendacoes_sistema = [];
    }

    try {
                $todos_insights = $insights->obterTodosInsights();
                $todos_insights = sortByDate($todos_insights, 'data_criacao');
                $total_insights = count($todos_insights);
                $insights_sistema = array_slice($todos_insights, $offset, $items_per_page);
    } catch (Exception $e) {
        error_log("Erro ao obter insights: " . $e->getMessage());
        $insights_sistema = [];
    }

    try {
                $todas_previsoes = $analise_avancada->preverCustosFuturos();
                $todas_previsoes = sortByDate($todas_previsoes, 'data_analise');
                $total_previsoes = count($todas_previsoes);
                $previsoes_custos = array_slice($todas_previsoes, $offset, $items_per_page);
    } catch (Exception $e) {
        error_log("Erro ao obter previsões de custos: " . $e->getMessage());
        $previsoes_custos = [];
    }

    try {
                $todos_motoristas = $analise_avancada->analisarEficienciaMotoristas();
                $todos_motoristas = sortByDate($todos_motoristas, 'data_analise');
                $total_motoristas = count($todos_motoristas);
                $eficiencia_motoristas = array_slice($todos_motoristas, $offset, $items_per_page);
    } catch (Exception $e) {
        error_log("Erro ao obter análise de eficiência dos motoristas: " . $e->getMessage());
        $eficiencia_motoristas = [];
    }

    try {
                $todos_veiculos = $analise_avancada->analisarVidaUtilVeiculos();
                $todos_veiculos = sortByDate($todos_veiculos, 'data_analise');
                $total_veiculos = count($todos_veiculos);
                $vida_util_veiculos = array_slice($todos_veiculos, $offset, $items_per_page);
    } catch (Exception $e) {
        error_log("Erro ao obter análise de vida útil dos veículos: " . $e->getMessage());
        $vida_util_veiculos = [];
    }

    try {
                $todas_rotas = $analise_avancada->analisarOtimizacaoRotas();
                $todas_rotas = sortByDate($todas_rotas, 'data_analise');
                $total_rotas = count($todas_rotas);
                $otimizacao_rotas = array_slice($todas_rotas, $offset, $items_per_page);
    } catch (Exception $e) {
        error_log("Erro ao obter análise de otimização de rotas: " . $e->getMessage());
        $otimizacao_rotas = [];
    }

        } catch (Exception $e) {
            error_log("Erro ao inicializar classes IA: " . $e->getMessage());
        }
    } else {
        error_log("Empresa {$empresa_id} não possui veículos cadastrados");
    }

} catch (Exception $e) {
    error_log("Erro crítico no painel IA: " . $e->getMessage());
    // Mantém arrays vazios para evitar erros no template
}

// Função para gerar paginação
function generatePagination($total_items, $items_per_page, $current_page, $section) {
    try {
        // Garantir que todos os parâmetros sejam do tipo correto
        $total_items = (int)$total_items;
        $items_per_page = (int)$items_per_page;
        $current_page = (int)$current_page;
        $section = (string)$section;
        
        // Validações básicas
        if ($total_items <= 0 || $items_per_page <= 0 || $current_page <= 0) {
            return '';
        }
        
    $total_pages = ceil($total_items / $items_per_page);
    if ($total_pages <= 1) return '';
    
    $pagination = '<div class="pagination">';
    
    // Botão anterior
    if ($current_page > 1) {
        $pagination .= '<a href="?page=' . ($current_page - 1) . '&section=' . $section . '" class="page-link">&laquo; Anterior</a>';
    }
    
    // Páginas
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    // Mostrar primeira página se não estiver no range
    if ($start_page > 1) {
        $pagination .= '<a href="?page=1&section=' . $section . '" class="page-link">1</a>';
        if ($start_page > 2) {
            $pagination .= '<span class="page-ellipsis">...</span>';
        }
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        $active_class = ($i == $current_page) ? 'active' : '';
        $pagination .= '<a href="?page=' . $i . '&section=' . $section . '" class="page-link ' . $active_class . '">' . $i . '</a>';
    }
    
    // Mostrar última página se não estiver no range
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $pagination .= '<span class="page-ellipsis">...</span>';
        }
        $pagination .= '<a href="?page=' . $total_pages . '&section=' . $section . '" class="page-link">' . $total_pages . '</a>';
    }
    
    // Botão próximo
    if ($current_page < $total_pages) {
        $pagination .= '<a href="?page=' . ($current_page + 1) . '&section=' . $section . '" class="page-link">Próximo &raquo;</a>';
    }
    
    $pagination .= '</div>';
    return $pagination;
    } catch (Exception $e) {
        error_log("Erro na função generatePagination: " . $e->getMessage());
        return '';
    }
}

// Função para ordenar arrays por data (mais recentes primeiro)
function sortByDate($array, $date_key = 'data') {
    if (empty($array)) return $array;
    
    usort($array, function($a, $b) use ($date_key) {
        $date_a = isset($a[$date_key]) ? strtotime($a[$date_key]) : 0;
        $date_b = isset($b[$date_key]) ? strtotime($b[$date_key]) : 0;
        return $date_b - $date_a; // Ordem decrescente (mais recente primeiro)
    });
    
    return $array;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Sistema de Frotas</title>
    <link rel="icon" type="image/png" href="../logo.png">
    
    <!-- CSS -->
    <link rel="stylesheet" href="/sistema-frotas/css/styles.css">
    <link rel="stylesheet" href="/sistema-frotas/css/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/sistema-frotas/css/theme.css">
    
    <style>
        /* Estilos específicos para o Painel IA */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .dashboard-card {
            background: var(--bg-secondary);
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
            color: white;
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .card-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-header i {
            font-size: 20px;
        }
        
        .card-body {
            padding: 20px;
            background: var(--bg-primary);
        }
        
        /* Estilos para Análise de Dados */
        .ai-analysis-section {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .analysis-item {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid var(--accent-primary);
        }
        
        .analysis-item h4 {
            color: var(--accent-primary);
            margin: 0 0 8px 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .analysis-item p {
            color: var(--text-secondary);
            margin: 0 0 10px 0;
            font-size: 14px;
        }
        
        .analysis-data p {
            background: var(--bg-primary);
            padding: 8px 12px;
            border-radius: 6px;
            margin: 5px 0;
            font-size: 13px;
            border: 1px solid var(--border-color);
        }
        
        /* Estilos para Recomendações */
        .recommendations-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .recommendation-item {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid var(--accent-success);
            transition: all 0.3s ease;
        }
        
        .recommendation-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .recommendation-item h4 {
            color: var(--accent-success);
            margin: 0 0 10px 0;
            font-size: 15px;
            font-weight: 600;
        }
        
        .recommendation-item p {
            color: var(--text-primary);
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
        }
        
        /* Estilos para Alertas */
        .alerts-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .alert-item {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 15px;
            transition: all 0.3s ease;
        }
        
        .alert-item.alta {
            border-left: 4px solid var(--accent-danger);
        }
        
        .alert-item.media {
            border-left: 4px solid var(--accent-warning);
        }
        
        .alert-item.baixa {
            border-left: 4px solid var(--accent-primary);
        }
        
        .alert-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .alert-item.alta h4 {
            color: var(--accent-danger);
        }
        
        .alert-item.media h4 {
            color: var(--accent-warning);
        }
        
        .alert-item.baixa h4 {
            color: var(--accent-primary);
        }
        
        .alert-item h4 {
            margin: 0 0 10px 0;
            font-size: 15px;
            font-weight: 600;
        }
        
        .alert-item p {
            color: var(--text-primary);
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
        }
        
        /* Estilos para Insights */
        .insights-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .insight-item {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid var(--accent-info);
            transition: all 0.3s ease;
        }
        
        .insight-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .insight-item h4 {
            color: var(--accent-info);
            margin: 0 0 10px 0;
            font-size: 15px;
            font-weight: 600;
        }
        
        .insight-item p {
            color: var(--text-primary);
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
        }
        
        /* Estilos para Previsão de Custos */
        .costs-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .cost-item {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid var(--accent-warning);
            transition: all 0.3s ease;
        }
        
        .cost-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .cost-item h4 {
            color: var(--accent-warning);
            margin: 0 0 10px 0;
            font-size: 15px;
            font-weight: 600;
        }
        
        .cost-item p {
            color: var(--text-primary);
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
        }
        
        /* Estilos para Eficiência dos Motoristas */
        .drivers-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .driver-item {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid var(--accent-success);
            transition: all 0.3s ease;
        }
        
        .driver-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .driver-item h4 {
            color: var(--accent-success);
            margin: 0 0 10px 0;
            font-size: 15px;
            font-weight: 600;
        }
        
        .driver-item p {
            color: var(--text-primary);
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
        }
        
        /* Estilos para Vida Útil dos Veículos */
        .vehicles-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .vehicle-item {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid var(--accent-primary);
            transition: all 0.3s ease;
        }
        
        .vehicle-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .vehicle-item h4 {
            color: var(--accent-primary);
            margin: 0 0 10px 0;
            font-size: 15px;
            font-weight: 600;
        }
        
        .vehicle-item p {
            color: var(--text-primary);
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
        }
        
        /* Estilos para Otimização de Rotas */
        .routes-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .route-item {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid var(--accent-secondary);
            transition: all 0.3s ease;
        }
        
        .route-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .route-item h4 {
            color: var(--accent-secondary);
            margin: 0 0 10px 0;
            font-size: 15px;
            font-weight: 600;
        }
        
        .route-item p {
            color: var(--text-primary);
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
        }
        
        /* Responsividade */
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .card-header {
                padding: 15px;
            }
            
            .card-body {
                padding: 15px;
            }
            
            .card-header h3 {
                font-size: 16px;
            }
        }
        
        /* Animações */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .dashboard-card {
            animation: fadeInUp 0.6s ease-out;
        }
        
        .dashboard-card:nth-child(1) { animation-delay: 0.1s; }
        .dashboard-card:nth-child(2) { animation-delay: 0.2s; }
        .dashboard-card:nth-child(3) { animation-delay: 0.3s; }
        .dashboard-card:nth-child(4) { animation-delay: 0.4s; }
        .dashboard-card:nth-child(5) { animation-delay: 0.5s; }
        .dashboard-card:nth-child(6) { animation-delay: 0.6s; }
        .dashboard-card:nth-child(7) { animation-delay: 0.7s; }
        .dashboard-card:nth-child(8) { animation-delay: 0.8s; }
        .dashboard-card:nth-child(9) { animation-delay: 0.9s; }
        .dashboard-card:nth-child(10) { animation-delay: 1.0s; }

        /* Paginação */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
            padding: 15px 0;
            border-top: 1px solid #e0e0e0;
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 12px;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            color: #666;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .page-link:hover {
            background: #f8f9fa;
            border-color: #007bff;
            color: #007bff;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 123, 255, 0.1);
        }

        .page-link.active {
            background: #007bff;
            border-color: #007bff;
            color: #fff;
            box-shadow: 0 2px 4px rgba(0, 123, 255, 0.2);
        }

        .page-link.active:hover {
            background: #0056b3;
            border-color: #0056b3;
            transform: none;
        }

        .page-ellipsis {
            color: #6c757d;
            padding: 0 8px;
            font-weight: 500;
        }

        /* Contador de itens */
        .item-count {
            background: #e9ecef;
            color: #6c757d;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        /* Melhorias nos cards com paginação */
        .dashboard-card .card-body {
            min-height: 200px;
        }

        .recommendations-list,
        .alerts-list,
        .insights-container,
        .costs-list,
        .drivers-list,
        .vehicles-list,
        .routes-list {
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 10px;
        }

        /* Responsividade da paginação */
        @media (max-width: 768px) {
            .pagination {
                flex-wrap: wrap;
                gap: 6px;
            }

            .page-link {
                min-width: 32px;
                height: 32px;
                padding: 0 8px;
                font-size: 13px;
            }

            .item-count {
                font-size: 11px;
                padding: 3px 6px;
            }
        }

        @media (max-width: 480px) {
            .pagination {
                gap: 4px;
            }

            .page-link {
                min-width: 28px;
                height: 28px;
                padding: 0 6px;
                font-size: 12px;
            }
        }

        /* Estilos do rodapé */
        .footer {
            background-color: var(--bg-secondary);
            border-top: 1px solid var(--border-color);
            padding: 20px 0;
            margin-top: 40px;
            text-align: center;
        }

        .footer p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 14px;
        }

    </style>
</head>

<body>
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1><?php echo $page_title; ?></h1>
                </div>
                
                <?php if ($total_veiculos_empresa == 0): ?>
                <div class="alert alert-info">
                    <h3><i class="fas fa-info-circle"></i> Nenhum veículo cadastrado</h3>
                    <p>Para utilizar o painel inteligente, é necessário cadastrar pelo menos um veículo na sua empresa.</p>
                    <a href="vehicles.php" class="btn btn-primary">Cadastrar Veículo</a>
                </div>
                <?php else: ?>
                
                <!-- Widgets de Métricas em Tempo Real -->
                <div class="metrics-widgets">
                    <div class="metric-widget">
                        <div class="metric-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="metric-content">
                            <h3 id="total-veiculos">-</h3>
                            <p>Veículos Ativos</p>
                        </div>
                    </div>
                    <div class="metric-widget">
                        <div class="metric-icon">
                            <i class="fas fa-route"></i>
                        </div>
                        <div class="metric-content">
                            <h3 id="total-rotas-hoje">-</h3>
                            <p>Rotas Hoje</p>
                        </div>
                    </div>
                    <div class="metric-widget">
                        <div class="metric-icon">
                            <i class="fas fa-gas-pump"></i>
                        </div>
                        <div class="metric-content">
                            <h3 id="consumo-hoje">-</h3>
                            <p>Litros Hoje</p>
                        </div>
                    </div>
                    <div class="metric-widget">
                        <div class="metric-icon">
                            <i class="fas fa-wrench"></i>
                        </div>
                        <div class="metric-content">
                            <h3 id="manutencoes-pendentes">-</h3>
                            <p>Manutenções Pendentes</p>
                        </div>
                    </div>
                    <div class="metric-widget">
                        <div class="metric-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="metric-content">
                            <h3 id="alertas-ativos">-</h3>
                            <p>Alertas Ativos</p>
                        </div>
                    </div>
                    <div class="metric-widget">
                        <div class="metric-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="metric-content">
                            <h3 id="eficiencia-media">-</h3>
                            <p>Eficiência Média</p>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-grid">
                    <!-- Card de Gráficos Interativos -->
                    <div class="dashboard-card full-width">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-line"></i> Análise Visual de Dados</h3>
                            <div class="chart-controls">
                                <select id="chart-period" onchange="handleChartPeriodChange()">
                                    <option value="7">Últimos 7 dias</option>
                                    <option value="30" selected>Últimos 30 dias</option>
                                    <option value="90">Últimos 90 dias</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="charts-container">
                                <div class="chart-row">
                                    <div class="chart-item">
                                        <h4>Consumo de Combustível por Veículo</h4>
                                        <canvas id="consumoChart" width="400" height="200"></canvas>
                                    </div>
                                    <div class="chart-item">
                                        <h4>Eficiência dos Motoristas</h4>
                                        <canvas id="eficienciaChart" width="400" height="200"></canvas>
                                </div>
                                    </div>
                                <div class="chart-row">
                                    <div class="chart-item">
                                        <h4>Custos por Categoria</h4>
                                        <canvas id="custosChart" width="400" height="200"></canvas>
                                </div>
                                    <div class="chart-item">
                                        <h4>Manutenções por Mês</h4>
                                        <canvas id="manutencaoChart" width="400" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card de Recomendações -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-lightbulb"></i> Recomendações</h3>
                            <?php if ($total_recomendacoes > 0): ?>
                                <span class="item-count"><?php echo $total_recomendacoes; ?> itens</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="recommendations-list">
                                <?php if (!empty($recomendacoes_sistema)): ?>
                                    <?php foreach ($recomendacoes_sistema as $recomendacao): ?>
                                        <div class="recommendation-item <?php echo $recomendacao['tipo']; ?>">
                                            <h4><?php echo $recomendacao['mensagem']; ?></h4>
                                            <?php if (isset($recomendacao['dados'])): ?>
                                                <p>
                                                    <?php foreach ($recomendacao['dados'] as $key => $value): ?>
                                                        <strong><?php echo ucfirst($key); ?>:</strong> <?php echo $value; ?><br>
                                                    <?php endforeach; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>Nenhuma recomendação disponível</p>
                                <?php endif; ?>
                            </div>
                            <?php echo generatePagination($total_recomendacoes, $items_per_page, $current_page, 'recomendacoes'); ?>
                        </div>
                    </div>

                    <!-- Card de Alertas -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-bell"></i> Alertas Inteligentes</h3>
                            <div class="card-actions">
                            <?php if ($total_alertas > 0): ?>
                                <span class="item-count"><?php echo $total_alertas; ?> itens</span>
                            <?php endif; ?>
                                <div class="export-buttons">
                                    <button class="btn btn-sm btn-export" onclick="exportData('alertas', 'pdf')" title="Exportar PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </button>
                                    <button class="btn btn-sm btn-export" onclick="exportData('alertas', 'excel')" title="Exportar Excel">
                                        <i class="fas fa-file-excel"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="alerts-list">
                                <?php if (!empty($alertas_sistema)): ?>
                                    <?php foreach ($alertas_sistema as $alerta): ?>
                                        <div class="alert-item <?php echo $alerta['prioridade']; ?>" data-alert-id="<?php echo $alerta['id']; ?>">
                                            <div class="alert-header">
                                                <h4><?php echo htmlspecialchars($alerta['titulo']); ?></h4>
                                                <div class="alert-actions">
                                                    <button class="btn-action btn-tratar" onclick="marcarAlerta(<?php echo $alerta['id']; ?>, 'tratado')" title="Marcar como Tratado">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button class="btn-action btn-resolver" onclick="marcarAlerta(<?php echo $alerta['id']; ?>, 'resolvido')" title="Marcar como Resolvido">
                                                        <i class="fas fa-check-double"></i>
                                                    </button>
                                                    <button class="btn-action btn-ignorar" onclick="marcarAlerta(<?php echo $alerta['id']; ?>, 'ignorado')" title="Ignorar">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="alert-content">
                                                <p><?php echo htmlspecialchars($alerta['mensagem']); ?></p>
                                                <?php if (isset($alerta['dados']) && !empty($alerta['dados'])): ?>
                                                    <div class="alert-details">
                                                    <?php foreach ($alerta['dados'] as $key => $value): ?>
                                                            <span class="detail-item">
                                                                <strong><?php echo ucfirst($key); ?>:</strong> <?php echo htmlspecialchars($value); ?>
                                                            </span>
                                                    <?php endforeach; ?>
                                                    </div>
                                            <?php endif; ?>
                                                <div class="alert-meta">
                                                    <small>
                                                        <i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($alerta['data_criacao'])); ?>
                                                        <?php if (isset($alerta['veiculo_placa'])): ?>
                                                            | <i class="fas fa-truck"></i> <?php echo $alerta['veiculo_placa']; ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="no-alerts">
                                        <i class="fas fa-check-circle"></i>
                                        <p>Nenhum alerta ativo no momento</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php echo generatePagination($total_alertas, $items_per_page, $current_page, 'alertas'); ?>
                        </div>
                    </div>

                    <!-- Card de Insights -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-brain"></i> Insights</h3>
                            <?php if ($total_insights > 0): ?>
                                <span class="item-count"><?php echo $total_insights; ?> itens</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="insights-container">
                                <?php if (!empty($insights_sistema)): ?>
                                    <?php foreach ($insights_sistema as $insight): ?>
                                        <div class="insight-item <?php echo $insight['tipo']; ?>">
                                            <h4><?php echo $insight['mensagem']; ?></h4>
                                            <?php if (isset($insight['dados'])): ?>
                                                <p>
                                                    <?php foreach ($insight['dados'] as $key => $value): ?>
                                                        <strong><?php echo ucfirst($key); ?>:</strong> <?php echo $value; ?><br>
                                                    <?php endforeach; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>Nenhum insight disponível</p>
                                <?php endif; ?>
                            </div>
                            <?php echo generatePagination($total_insights, $items_per_page, $current_page, 'insights'); ?>
                        </div>
                    </div>

                    <!-- Após o card de Insights -->
                    <!-- Card de Previsões de IA Avançadas -->
                    <div class="dashboard-card full-width">
                        <div class="card-header">
                            <h3><i class="fas fa-brain"></i> Previsões Inteligentes de IA</h3>
                            <div class="prediction-controls">
                                <button class="btn btn-sm" onclick="refreshPredictions()">
                                    <i class="fas fa-sync-alt"></i> Atualizar
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="predictions-grid">
                                <div class="prediction-card">
                                    <h4><i class="fas fa-gas-pump"></i> Previsão de Combustível</h4>
                                    <div class="prediction-content">
                                        <div class="prediction-value" id="fuel-prediction">R$ 0,00</div>
                                        <div class="prediction-period">Próximos 30 dias</div>
                                        <div class="prediction-trend" id="fuel-trend">
                                            <i class="fas fa-arrow-up"></i> +5.2%
                                        </div>
                            </div>
                                </div>
                                
                                <div class="prediction-card">
                                    <h4><i class="fas fa-wrench"></i> Previsão de Manutenção</h4>
                                    <div class="prediction-content">
                                        <div class="prediction-value" id="maintenance-prediction">R$ 0,00</div>
                                        <div class="prediction-period">Próximos 30 dias</div>
                                        <div class="prediction-trend" id="maintenance-trend">
                                            <i class="fas fa-arrow-down"></i> -2.1%
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="prediction-card">
                                    <h4><i class="fas fa-route"></i> Eficiência de Rotas</h4>
                                    <div class="prediction-content">
                                        <div class="prediction-value" id="efficiency-prediction">85%</div>
                                        <div class="prediction-period">Próximos 7 dias</div>
                                        <div class="prediction-trend" id="efficiency-trend">
                                            <i class="fas fa-arrow-up"></i> +3.5%
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="prediction-card">
                                    <h4><i class="fas fa-exclamation-triangle"></i> Risco de Falhas</h4>
                                    <div class="prediction-content">
                                        <div class="prediction-value" id="risk-prediction">12%</div>
                                        <div class="prediction-period">Próximos 14 dias</div>
                                        <div class="prediction-trend" id="risk-trend">
                                            <i class="fas fa-arrow-down"></i> -1.8%
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Gráfico de Previsões -->
                            <div class="prediction-chart">
                                <h4>Evolução das Previsões</h4>
                                <canvas id="predictionChart" width="800" height="300"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Card de Eficiência dos Motoristas -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-user-tie"></i> Eficiência dos Motoristas</h3>
                            <?php if ($total_motoristas > 0): ?>
                                <span class="item-count"><?php echo $total_motoristas; ?> itens</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="drivers-list">
                                <?php if (!empty($eficiencia_motoristas)): ?>
                                    <?php foreach ($eficiencia_motoristas as $motorista): ?>
                                        <div class="driver-item">
                                            <h4><?php echo $motorista['motorista']; ?></h4>
                                            <p>
                                                <strong>Veículo:</strong> <?php echo $motorista['veiculo']; ?><br>
                                                <strong>Viagens:</strong> <?php echo $motorista['num_viagens']; ?><br>
                                                <strong>Média de Distância:</strong> <?php echo number_format($motorista['media_distancia'], 1, ',', '.'); ?> km<br>
                                                <strong>Consumo Médio:</strong> <?php echo number_format($motorista['consumo_medio'], 2, ',', '.'); ?> L/km<br>
                                                <strong>Índice de Eficiência:</strong> <?php echo number_format($motorista['indice_eficiencia'], 2, ',', '.'); ?>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>Nenhum dado disponível</p>
                                <?php endif; ?>
                            </div>
                            <?php echo generatePagination($total_motoristas, $items_per_page, $current_page, 'motoristas'); ?>
                        </div>
                    </div>

                    <!-- Card de Vida Útil dos Veículos -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-car"></i> Vida Útil dos Veículos</h3>
                            <?php if ($total_veiculos > 0): ?>
                                <span class="item-count"><?php echo $total_veiculos; ?> itens</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="vehicles-list">
                                <?php if (!empty($vida_util_veiculos)): ?>
                                    <?php foreach ($vida_util_veiculos as $veiculo): ?>
                                        <div class="vehicle-item">
                                            <h4><?php echo $veiculo['veiculo']; ?> (<?php echo $veiculo['modelo']; ?>)</h4>
                                            <p>
                                                <strong>Idade:</strong> <?php echo $veiculo['idade']; ?> anos<br>
                                                <strong>KM Atual:</strong> <?php echo number_format($veiculo['km_atual'], 0, ',', '.'); ?> km<br>
                                                <strong>Custo Médio Mensal:</strong> R$ <?php echo number_format($veiculo['custo_medio_mensal'], 2, ',', '.'); ?><br>
                                                <strong>Custo por KM:</strong> R$ <?php echo number_format($veiculo['custo_por_km'], 2, ',', '.'); ?><br>
                                                <strong>Recomendação:</strong> <?php echo $veiculo['recomendacao']; ?>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>Nenhum dado disponível</p>
                                <?php endif; ?>
                            </div>
                            <?php echo generatePagination($total_veiculos, $items_per_page, $current_page, 'veiculos'); ?>
                        </div>
                    </div>

                    <!-- Card de Otimização de Rotas -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-route"></i> Otimização de Rotas</h3>
                            <?php if ($total_rotas > 0): ?>
                                <span class="item-count"><?php echo $total_rotas; ?> itens</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="routes-list">
                                <?php if (!empty($otimizacao_rotas)): ?>
                                    <?php foreach ($otimizacao_rotas as $rota): ?>
                                        <div class="route-item">
                                            <h4><?php echo $rota['origem']; ?> → <?php echo $rota['destino']; ?></h4>
                                            <p>
                                                <strong>Distância Atual:</strong> <?php echo number_format($rota['distancia_atual'], 1, ',', '.'); ?> km<br>
                                                <strong>Distância Otimizada:</strong> <?php echo number_format($rota['distancia_otimizada'], 1, ',', '.'); ?> km<br>
                                                <strong>Economia:</strong> <?php echo number_format($rota['economia_km'], 1, ',', '.'); ?> km<br>
                                                <strong>Economia de Combustível:</strong> R$ <?php echo number_format($rota['economia_combustivel'], 2, ',', '.'); ?>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>Nenhum dado disponível</p>
                                <?php endif; ?>
                            </div>
                            <?php echo generatePagination($total_rotas, $items_per_page, $current_page, 'rotas'); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Rodapé -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 Sistema de Gestão de Frotas - Todos os direitos reservados</p>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js" 
            onerror="console.warn('Font Awesome CDN falhou, usando fallback')"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js" 
            onerror="console.warn('Chart.js CDN falhou, carregando fallback')"></script>
    <script src="/sistema-frotas/js/theme.js"></script>
    <script src="/sistema-frotas/js/sidebar.js"></script>
    <script src="/sistema-frotas/js/dashboard.js"></script>
    
    <!-- Fallback para Chart.js se CDN falhar -->
    <script>
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js não carregado, tentando fallback...');
        // Tentar carregar de outro CDN
        var script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js';
        script.onerror = function() {
            console.error('Chart.js não disponível, gráficos não funcionarão');
        };
        document.head.appendChild(script);
    }
    </script>
    
    <script>
    // Variáveis globais para gráficos
    let charts = {};
    let updateInterval;
    
    // Inicializar painel IA
        document.addEventListener('DOMContentLoaded', function() {
        loadMetrics();
        initializeCharts();
        refreshPredictions();
        startRealTimeUpdates();
    });
    
    // Carregar métricas em tempo real
    function loadMetrics() {
        console.log('Carregando métricas da API...');
        
        fetch('../IA/api/ia_metrics.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        console.log('Métricas carregadas da API:', data.metrics);
                        updateMetrics(data.metrics);
                    } else {
                        console.error('Erro na API:', data.error);
                        loadFallbackMetrics();
                    }
                } catch (e) {
                    console.error('Erro ao parsear JSON:', e);
                    console.error('Resposta recebida:', text);
                    loadFallbackMetrics();
                }
            })
            .catch(error => {
                console.error('Erro ao carregar métricas:', error);
                loadFallbackMetrics();
            });
    }
    
    // Fallback para métricas
    function loadFallbackMetrics() {
        updateMetrics({
            veiculos_ativos: <?php echo $total_veiculos_empresa; ?>,
            rotas_hoje: 0,
            consumo_hoje: 0,
            manutencoes_pendentes: <?php echo $total_alertas; ?>,
            alertas_ativos: <?php echo $total_alertas; ?>,
            eficiencia_media: 85
        });
    }
    
    // Atualizar widgets de métricas
    function updateMetrics(metrics) {
        document.getElementById('total-veiculos').textContent = metrics.veiculos_ativos || 0;
        document.getElementById('total-rotas-hoje').textContent = metrics.rotas_hoje || 0;
        document.getElementById('consumo-hoje').textContent = (metrics.consumo_hoje || 0) + 'L';
        document.getElementById('manutencoes-pendentes').textContent = metrics.manutencoes_pendentes || 0;
        document.getElementById('alertas-ativos').textContent = metrics.alertas_ativos || 0;
        document.getElementById('eficiencia-media').textContent = (metrics.eficiencia_media || 0) + '%';
    }
    
    // Inicializar gráficos
    function initializeCharts() {
        // Aguardar Chart.js carregar
        if (typeof Chart === 'undefined') {
            setTimeout(initializeCharts, 100);
            return;
        }
        
        // Gráfico de Consumo
        const consumoCtx = document.getElementById('consumoChart').getContext('2d');
        charts.consumo = new Chart(consumoCtx, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: 'Litros',
                    data: [],
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
        
        // Gráfico de Eficiência
        const eficienciaCtx = document.getElementById('eficienciaChart').getContext('2d');
        charts.eficiencia = new Chart(eficienciaCtx, {
            type: 'doughnut',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.6)',
                        'rgba(255, 99, 132, 0.6)',
                        'rgba(255, 205, 86, 0.6)'
                    ]
                }]
            },
            options: { responsive: true }
        });
        
        // Gráfico de Custos
        const custosCtx = document.getElementById('custosChart').getContext('2d');
        charts.custos = new Chart(custosCtx, {
            type: 'pie',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.6)',
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(255, 205, 86, 0.6)',
                        'rgba(75, 192, 192, 0.6)'
                    ]
                }]
            },
            options: { responsive: true }
        });
        
        // Gráfico de Manutenção
        const manutencaoCtx = document.getElementById('manutencaoChart').getContext('2d');
        charts.manutencao = new Chart(manutencaoCtx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Manutenções',
                    data: [],
                    borderColor: 'rgba(255, 99, 132, 1)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
        
        // Gráfico de Previsões
        const predictionCtx = document.getElementById('predictionChart').getContext('2d');
        charts.prediction = new Chart(predictionCtx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Combustível',
                        data: [],
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        tension: 0.4
                    },
                    {
                        label: 'Manutenção',
                        data: [],
                        borderColor: 'rgba(255, 99, 132, 1)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
        
        loadChartData();
    }
    
    // Carregar dados dos gráficos
    function loadChartData() {
        const period = document.getElementById('chart-period').value;
        console.log('Carregando gráficos da API...');
        console.log('Período selecionado:', period, 'dias');
        
        fetch(`../IA/api/ia_charts.php?period=${period}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        console.log('Gráficos carregados da API:', data.charts);
                        updateCharts(data.charts);
                    } else {
                        console.error('Erro na API de gráficos:', data.error);
                        loadFallbackCharts();
                    }
                } catch (e) {
                    console.error('Erro ao parsear JSON dos gráficos:', e);
                    console.error('Resposta recebida:', text);
                    loadFallbackCharts();
                }
            })
            .catch(error => {
                console.error('Erro ao carregar gráficos:', error);
                loadFallbackCharts();
            });
    }
    
    // Fallback para gráficos
    function loadFallbackCharts() {
        updateCharts({
            consumo: {
                labels: ['Veículo A', 'Veículo B', 'Veículo C'],
                data: [120, 150, 90]
            },
            eficiencia: {
                labels: ['Eficiente', 'Médio', 'Baixo'],
                data: [60, 30, 10]
            },
            custos: {
                labels: ['Combustível', 'Manutenção', 'Multas'],
                data: [2500, 1200, 300]
            },
            manutencao: {
                labels: ['Jan', 'Fev', 'Mar', 'Abr'],
                data: [5, 8, 3, 7]
            },
            prediction: {
                labels: ['Semana 1', 'Semana 2', 'Semana 3', 'Semana 4'],
                combustivel: [1000, 1200, 1100, 1300],
                manutencao: [500, 600, 550, 650]
            }
        });
    }
    
    // Atualizar gráficos
    function updateCharts(chartData) {
        console.log('Atualizando gráficos com dados:', chartData);
        
        if (charts.consumo && chartData.consumo) {
            console.log('Atualizando gráfico de consumo:', chartData.consumo);
            charts.consumo.data.labels = chartData.consumo.labels;
            charts.consumo.data.datasets[0].data = chartData.consumo.data;
            charts.consumo.update();
        }
        
        if (charts.eficiencia && chartData.eficiencia) {
            charts.eficiencia.data.labels = chartData.eficiencia.labels;
            charts.eficiencia.data.datasets[0].data = chartData.eficiencia.data;
            charts.eficiencia.update();
        }
        
        if (charts.custos && chartData.custos) {
            charts.custos.data.labels = chartData.custos.labels;
            charts.custos.data.datasets[0].data = chartData.custos.data;
            charts.custos.update();
        }
        
        if (charts.manutencao && chartData.manutencao) {
            charts.manutencao.data.labels = chartData.manutencao.labels;
            charts.manutencao.data.datasets[0].data = chartData.manutencao.data;
            charts.manutencao.update();
        }
        
        if (charts.prediction && chartData.prediction) {
            charts.prediction.data.labels = chartData.prediction.labels;
            charts.prediction.data.datasets[0].data = chartData.prediction.combustivel;
            charts.prediction.data.datasets[1].data = chartData.prediction.manutencao;
            charts.prediction.update();
        }
    }
    
    // Iniciar atualizações em tempo real
    function startRealTimeUpdates() {
        // Atualizar métricas a cada 30 segundos
        updateInterval = setInterval(() => {
            loadMetrics();
        }, 30000);
    }
    
    // Parar atualizações em tempo real
    function stopRealTimeUpdates() {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
    }
    
    // Atualizar gráficos quando período mudar
    function handleChartPeriodChange() {
        loadChartData();
    }
    
    // Atualizar previsões
    function refreshPredictions() {
        console.log('Carregando previsões da API...');
        
        fetch('../IA/api/ia_predictions.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        console.log('Previsões carregadas da API:', data.predictions);
                        updatePredictionCards(data.predictions);
                    } else {
                        console.error('Erro na API de previsões:', data.error);
                        loadFallbackPredictions();
                    }
                } catch (e) {
                    console.error('Erro ao parsear JSON das previsões:', e);
                    console.error('Resposta recebida:', text);
                    loadFallbackPredictions();
                }
            })
            .catch(error => {
                console.error('Erro ao carregar previsões:', error);
                loadFallbackPredictions();
            });
    }
    
    // Fallback para previsões
    function loadFallbackPredictions() {
        updatePredictionCards({
            combustivel: 'R$ 2.500,00',
            manutencao: 'R$ 1.200,00',
            eficiencia: '85%',
            risco: '15%'
        });
    }
    
    // Atualizar cards de previsão
    function updatePredictionCards(predictions) {
        document.getElementById('fuel-prediction').textContent = predictions.combustivel;
        document.getElementById('maintenance-prediction').textContent = predictions.manutencao;
        document.getElementById('efficiency-prediction').textContent = predictions.eficiencia;
        document.getElementById('risk-prediction').textContent = predictions.risco;
    }
    
    // Exportar dados
    function exportData(type, format) {
        const period = document.getElementById('chart-period').value;
        const url = `../api/ia_export.php?type=${type}&format=${format}&period=${period}`;
        window.open(url, '_blank');
    }
    
    // Função para marcar alertas
    function marcarAlerta(alertId, action) {
        const observacoes = prompt(`Observações para marcar como ${action}:`);
        if (observacoes === null) return; // Usuário cancelou
        
        const formData = new FormData();
        formData.append('id', alertId);
        formData.append('observacoes', observacoes);
        
        fetch(`../api/alertas_sistema.php?action=marcar_${action}`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remover o alerta da interface
                const alertElement = document.querySelector(`[data-alert-id="${alertId}"]`);
                if (alertElement) {
                    alertElement.style.opacity = '0.5';
                    alertElement.style.textDecoration = 'line-through';
                    setTimeout(() => {
                        alertElement.remove();
                        // Recarregar a página para atualizar contadores
                        location.reload();
                    }, 1000);
                }
                
                // Mostrar notificação de sucesso
                showNotification(`Alerta marcado como ${action} com sucesso!`, 'success');
            } else {
                showNotification(`Erro ao marcar alerta: ${data.error}`, 'error');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showNotification('Erro ao processar solicitação', 'error');
        });
    }
    
    // Função para mostrar notificações
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);
        
        // Animar entrada
        setTimeout(() => notification.classList.add('show'), 100);
        
        // Remover após 3 segundos
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    </script>
    
    <style>
    /* Estilos para alertas melhorados */
    .alert-item {
        border: 1px solid #ddd;
        border-radius: 8px;
        margin-bottom: 15px;
        padding: 15px;
        background: #fff;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .alert-item:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    
    .alert-item.alta {
        border-left: 4px solid #e74c3c;
        background: #fdf2f2;
    }
    
    .alert-item.media {
        border-left: 4px solid #f39c12;
        background: #fef9e7;
    }
    
    .alert-item.baixa {
        border-left: 4px solid #3498db;
        background: #f0f8ff;
    }
    
    .alert-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .alert-header h4 {
        margin: 0;
        color: #2c3e50;
        font-size: 16px;
    }
    
    .alert-actions {
        display: flex;
        gap: 5px;
    }
    
    .btn-action {
        background: none;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 5px 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 12px;
    }
    
    .btn-tratar:hover {
        background: #3498db;
        color: white;
        border-color: #3498db;
    }
    
    .btn-resolver:hover {
        background: #27ae60;
        color: white;
        border-color: #27ae60;
    }
    
    .btn-ignorar:hover {
        background: #e74c3c;
        color: white;
        border-color: #e74c3c;
    }
    
    .alert-content p {
        margin: 0 0 10px 0;
        color: #555;
    }
    
    .alert-details {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 10px 0;
    }
    
    .detail-item {
        background: #f8f9fa;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        color: #666;
    }
    
    .alert-meta {
        margin-top: 10px;
        color: #888;
    }
    
    .alert-meta i {
        margin-right: 5px;
    }
    
    .no-alerts {
        text-align: center;
        padding: 40px 20px;
        color: #888;
    }
    
    .no-alerts i {
        font-size: 48px;
        color: #27ae60;
        margin-bottom: 15px;
    }
    
    /* Notificações */
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 15px 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 1000;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 300px;
    }
    
    .notification.show {
        transform: translateX(0);
    }
    
    .notification-success {
        border-left: 4px solid #27ae60;
        background: #f0fff4;
    }
    
    .notification-error {
        border-left: 4px solid #e74c3c;
        background: #fff5f5;
    }
    
    .notification-info {
        border-left: 4px solid #3498db;
        background: #f0f8ff;
    }
    
    /* Widgets de Métricas */
    .metrics-widgets {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .metric-widget {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        gap: 15px;
        transition: transform 0.3s ease;
    }
    
    .metric-widget:hover {
        transform: translateY(-5px);
    }
    
    .metric-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
    }
    
    .metric-widget:nth-child(1) .metric-icon { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .metric-widget:nth-child(2) .metric-icon { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
    .metric-widget:nth-child(3) .metric-icon { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
    .metric-widget:nth-child(4) .metric-icon { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
    .metric-widget:nth-child(5) .metric-icon { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
    .metric-widget:nth-child(6) .metric-icon { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }
    
    .metric-content h3 {
        font-size: 28px;
        font-weight: bold;
        margin: 0;
        color: #2c3e50;
    }
    
    .metric-content p {
        margin: 5px 0 0 0;
        color: #7f8c8d;
        font-size: 14px;
    }
    
    /* Gráficos */
    .full-width {
        grid-column: 1 / -1;
    }
    
    .chart-controls {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .chart-controls select {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        background: white;
    }
    
    .charts-container {
        display: flex;
        flex-direction: column;
        gap: 30px;
    }
    
    .chart-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    .chart-item {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
    }
    
    .chart-item h4 {
        margin: 0 0 15px 0;
        color: #2c3e50;
        font-size: 16px;
    }
    
    .chart-item canvas {
        max-height: 300px;
    }
    
    /* Previsões de IA */
    .prediction-controls {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .predictions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .prediction-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-left: 4px solid #3498db;
    }
    
    .prediction-card h4 {
        margin: 0 0 15px 0;
        color: #2c3e50;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .prediction-content {
        text-align: center;
    }
    
    .prediction-value {
        font-size: 32px;
        font-weight: bold;
        color: #2c3e50;
        margin-bottom: 5px;
    }
    
    .prediction-period {
        color: #7f8c8d;
        font-size: 14px;
        margin-bottom: 10px;
    }
    
    .prediction-trend {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        font-size: 14px;
        font-weight: 500;
    }
    
    .prediction-trend i {
        font-size: 12px;
    }
    
    .prediction-trend:has(.fa-arrow-up) {
        color: #27ae60;
    }
    
    .prediction-trend:has(.fa-arrow-down) {
        color: #e74c3c;
    }
    
    .prediction-chart {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .prediction-chart h4 {
        margin: 0 0 20px 0;
        color: #2c3e50;
    }
    
    /* Ações de Card */
    .card-actions {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .export-buttons {
        display: flex;
        gap: 5px;
    }
    
    .btn-export {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        color: #6c757d;
        padding: 6px 10px;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .btn-export:hover {
        background: #e9ecef;
        color: #495057;
    }
    
    /* Responsividade */
    @media (max-width: 768px) {
        .metrics-widgets {
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        }
        
        .chart-row {
            grid-template-columns: 1fr;
        }
        
        .predictions-grid {
            grid-template-columns: 1fr;
        }
        
        .metric-widget {
            flex-direction: column;
            text-align: center;
        }
        
        .metric-icon {
            width: 50px;
            height: 50px;
            font-size: 20px;
        }
    }
    </style>
</body>
</html> 