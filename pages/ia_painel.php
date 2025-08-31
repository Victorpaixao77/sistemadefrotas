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
                
                <div class="dashboard-grid">
                    <!-- Card de Análise de Dados -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-line"></i> Análise de Dados</h3>
                        </div>
                        <div class="card-body">
                            <div class="ai-analysis-section">
                                <div class="analysis-item">
                                    <h4>Consumo de Combustível</h4>
                                    <p>Análise de padrões e otimização</p>
                                    <div class="analysis-data">
                                        <?php if (!empty($dados_consumo)): ?>
                                            <?php foreach ($dados_consumo as $consumo): ?>
                                                <p><strong><?php echo $consumo['placa']; ?>:</strong> 
                                                   <?php echo number_format($consumo['total_litros'], 2); ?> litros</p>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p>Nenhum dado de consumo disponível</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="analysis-item">
                                    <h4>Manutenções</h4>
                                    <p>Previsão e recomendações</p>
                                    <div class="analysis-data">
                                        <?php if (!empty($dados_manutencao)): ?>
                                            <?php foreach ($dados_manutencao as $manutencao): ?>
                                                <p><strong><?php echo $manutencao['placa']; ?>:</strong> 
                                                   <?php echo $manutencao['tipo_manutencao']; ?></p>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p>Nenhum dado de manutenção disponível</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="analysis-item">
                                    <h4>Rotas</h4>
                                    <p>Otimização e eficiência</p>
                                    <div class="analysis-data">
                                        <?php if (!empty($dados_rotas)): ?>
                                            <?php foreach ($dados_rotas as $rota): ?>
                                                <p><strong><?php echo $rota['origem']; ?> → <?php echo $rota['destino']; ?>:</strong> 
                                                   <?php echo $rota['num_viagens']; ?> viagens</p>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p>Nenhum dado de rota disponível</p>
                                        <?php endif; ?>
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
                            <?php if ($total_alertas > 0): ?>
                                <span class="item-count"><?php echo $total_alertas; ?> itens</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="alerts-list">
                                <?php if (!empty($alertas_sistema)): ?>
                                    <?php foreach ($alertas_sistema as $alerta): ?>
                                        <div class="alert-item <?php echo $alerta['prioridade']; ?>">
                                            <h4><?php echo $alerta['mensagem']; ?></h4>
                                            <?php if (isset($alerta['dados'])): ?>
                                                <p>
                                                    <?php foreach ($alerta['dados'] as $key => $value): ?>
                                                        <strong><?php echo ucfirst($key); ?>:</strong> <?php echo $value; ?><br>
                                                    <?php endforeach; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>Nenhum alerta disponível</p>
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
                    <!-- Card de Previsão de Custos -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-line"></i> Previsão de Custos</h3>
                            <?php if ($total_previsoes > 0): ?>
                                <span class="item-count"><?php echo $total_previsoes; ?> itens</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="costs-list">
                                <?php if (!empty($previsoes_custos)): ?>
                                    <?php foreach ($previsoes_custos as $previsao): ?>
                                        <div class="cost-item">
                                            <h4><?php echo $previsao['veiculo']; ?> (<?php echo $previsao['modelo']; ?>)</h4>
                                            <p>
                                                <strong>Previsão Combustível (3 meses):</strong> R$ <?php echo number_format($previsao['previsao_combustivel'], 2, ',', '.'); ?><br>
                                                <strong>Previsão Manutenção (3 meses):</strong> R$ <?php echo number_format($previsao['previsao_manutencao'], 2, ',', '.'); ?><br>
                                                <strong>Total Previsto:</strong> R$ <?php echo number_format($previsao['total_previsto'], 2, ',', '.'); ?>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>Nenhuma previsão disponível</p>
                                <?php endif; ?>
                            </div>
                            <?php echo generatePagination($total_previsoes, $items_per_page, $current_page, 'previsoes'); ?>
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

    <!-- JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script src="/sistema-frotas/js/dashboard.js"></script>
    <script src="/sistema-frotas/js/theme.js"></script>
    <script>
        // Verificar se o tema está funcionando
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Tema atual:', document.body.classList.contains('light-theme') ? 'Claro' : 'Escuro');
            console.log('Variáveis CSS:', {
                '--bg-sidebar': getComputedStyle(document.documentElement).getPropertyValue('--bg-sidebar'),
                '--bg-primary': getComputedStyle(document.documentElement).getPropertyValue('--bg-primary'),
                '--text-primary': getComputedStyle(document.documentElement).getPropertyValue('--text-primary')
            });
        });
    </script>
</body>
</html> 