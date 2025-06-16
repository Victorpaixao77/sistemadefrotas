<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Set page title
$page_title = "Painel Inteligente (IA)";

// Requer autenticação
require_authentication();

require_once '../IA/analise.php';
require_once '../IA/alertas.php';
require_once '../IA/recomendacoes.php';
require_once '../IA/insights.php';
require_once '../IA/notificacoes.php';
require_once '../IA/config.php';
require_once '../IA/AnaliseAvancada.php';

// Inicializa o log de erros
error_log("Iniciando carregamento do painel IA");
error_log("Session data: " . print_r($_SESSION, true));

try {
    // Obtém conexão com o banco
    $pdo = getConnection();
    if (!$pdo) {
        throw new Exception("Falha ao conectar ao banco de dados");
    }
    error_log("Conexão com banco de dados estabelecida com sucesso");

    // Obtém ID da empresa
    $empresa_id = $_SESSION['empresa_id'] ?? null;
    if (!$empresa_id) {
        error_log("ID da empresa não encontrado na sessão");
        throw new Exception("ID da empresa não encontrado na sessão");
    }
    error_log("ID da empresa obtido: " . $empresa_id);

    // Inicializa classes
    $analise = new Analise($pdo, $empresa_id);
    $alertas = new Alertas($pdo, $empresa_id);
    $recomendacoes = new Recomendacoes($pdo, $empresa_id);
    $insights = new Insights($pdo, $empresa_id);
    $notificacoes = new Notificacoes($pdo, $empresa_id);
    $config = new ConfiguracoesIA($empresa_id);
    $analise_avancada = new AnaliseAvancada($pdo, $empresa_id);

    error_log("Classes inicializadas com sucesso");

    // Obtém dados
    try {
        $dados_consumo = $analise->analisarConsumo();
        error_log("Dados de consumo obtidos: " . json_encode($dados_consumo));
    } catch (Exception $e) {
        error_log("Erro ao obter dados de consumo: " . $e->getMessage());
        $dados_consumo = [];
    }

    try {
        $dados_manutencao = $analise->analisarManutencao();
        error_log("Dados de manutenção obtidos: " . json_encode($dados_manutencao));
    } catch (Exception $e) {
        error_log("Erro ao obter dados de manutenção: " . $e->getMessage());
        $dados_manutencao = [];
    }

    try {
        $dados_rotas = $analise->analisarRotas();
        error_log("Dados de rotas obtidos: " . json_encode($dados_rotas));
    } catch (Exception $e) {
        error_log("Erro ao obter dados de rotas: " . $e->getMessage());
        $dados_rotas = [];
    }

    try {
        $alertas_sistema = $alertas->obterTodosAlertas();
        error_log("Alertas obtidos: " . json_encode($alertas_sistema));
    } catch (Exception $e) {
        error_log("Erro ao obter alertas: " . $e->getMessage());
        $alertas_sistema = [];
    }

    try {
        $recomendacoes_sistema = $recomendacoes->obterTodasRecomendacoes();
        error_log("Recomendações obtidas: " . json_encode($recomendacoes_sistema));
    } catch (Exception $e) {
        error_log("Erro ao obter recomendações: " . $e->getMessage());
        $recomendacoes_sistema = [];
    }

    try {
        $insights_sistema = $insights->obterTodosInsights();
        error_log("Insights obtidos: " . json_encode($insights_sistema));
    } catch (Exception $e) {
        error_log("Erro ao obter insights: " . $e->getMessage());
        $insights_sistema = [];
    }

    try {
        $previsoes_custos = $analise_avancada->preverCustosFuturos();
        error_log("Previsões de custos obtidas: " . json_encode($previsoes_custos));
    } catch (Exception $e) {
        error_log("Erro ao obter previsões de custos: " . $e->getMessage());
        $previsoes_custos = [];
    }

    try {
        $eficiencia_motoristas = $analise_avancada->analisarEficienciaMotoristas();
        error_log("Análise de eficiência dos motoristas obtida: " . json_encode($eficiencia_motoristas));
    } catch (Exception $e) {
        error_log("Erro ao obter análise de eficiência dos motoristas: " . $e->getMessage());
        $eficiencia_motoristas = [];
    }

    try {
        $vida_util_veiculos = $analise_avancada->analisarVidaUtilVeiculos();
        error_log("Análise de vida útil dos veículos obtida: " . json_encode($vida_util_veiculos));
    } catch (Exception $e) {
        error_log("Erro ao obter análise de vida útil dos veículos: " . $e->getMessage());
        $vida_util_veiculos = [];
    }

    try {
        $otimizacao_rotas = $analise_avancada->analisarOtimizacaoRotas();
        error_log("Análise de otimização de rotas obtida: " . json_encode($otimizacao_rotas));
    } catch (Exception $e) {
        error_log("Erro ao obter análise de otimização de rotas: " . $e->getMessage());
        $otimizacao_rotas = [];
    }

    error_log("Todos os dados foram obtidos com sucesso");

} catch (Exception $e) {
    error_log("Erro crítico no painel IA: " . $e->getMessage());
    // Define arrays vazios para evitar erros no template
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
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Sistema de Frotas</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="/sistema-frotas/css/styles.css">
    <link rel="stylesheet" href="/sistema-frotas/css/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                        </div>
                    </div>

                    <!-- Card de Alertas -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-bell"></i> Alertas Inteligentes</h3>
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
                        </div>
                    </div>

                    <!-- Card de Insights -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-brain"></i> Insights</h3>
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
                        </div>
                    </div>

                    <!-- Após o card de Insights -->
                    <!-- Card de Previsão de Custos -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-line"></i> Previsão de Custos</h3>
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
                        </div>
                    </div>

                    <!-- Card de Eficiência dos Motoristas -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-user-tie"></i> Eficiência dos Motoristas</h3>
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
                        </div>
                    </div>

                    <!-- Card de Vida Útil dos Veículos -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-car"></i> Vida Útil dos Veículos</h3>
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
                        </div>
                    </div>

                    <!-- Card de Otimização de Rotas -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-route"></i> Otimização de Rotas</h3>
                        </div>
                        <div class="card-body">
                            <div class="routes-list">
                                <?php if (!empty($otimizacao_rotas)): ?>
                                    <?php foreach ($otimizacao_rotas as $rota): ?>
                                        <div class="route-item">
                                            <h4><?php echo $rota['origem']; ?> → <?php echo $rota['destino']; ?></h4>
                                            <p>
                                                <strong>Viagens:</strong> <?php echo $rota['num_viagens']; ?><br>
                                                <strong>Distância Média:</strong> <?php echo number_format($rota['distancia_media'], 1, ',', '.'); ?> km<br>
                                                <strong>Tempo Médio:</strong> <?php echo number_format($rota['tempo_medio'], 1, ',', '.'); ?> horas<br>
                                                <strong>Consumo Médio:</strong> <?php echo number_format($rota['consumo_medio'], 2, ',', '.'); ?> L/km<br>
                                                <strong>Índice de Eficiência:</strong> <?php echo number_format($rota['indice_eficiencia'], 2, ',', '.'); ?><br>
                                                <strong>Sugestões:</strong><br>
                                                <?php foreach ($rota['sugestoes_otimizacao'] as $sugestao): ?>
                                                    • <?php echo $sugestao; ?><br>
                                                <?php endforeach; ?>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>Nenhum dado disponível</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .ai-analysis-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            padding: 1rem;
        }

        .analysis-item {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
        }

        .analysis-item h4 {
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .analysis-item p {
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .recommendations-list,
        .alerts-list,
        .insights-container {
            padding: 1rem;
        }

        .loading-spinner {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }

        .loading-spinner i {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .btn-primary {
            background: var(--accent-primary);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--accent-primary-dark);
        }

        .alerts-list {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
            margin-top: 1.5rem;
        }
        .alert-item {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 1.2rem 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border-left: 6px solid var(--border-color);
            margin-bottom: 0.5rem;
        }
        .alert-item.critico { border-left-color: #e53935; }
        .alert-item.manutencao { border-left-color: #ff9800; }
        .alert-item.documento { border-left-color: #1976d2; }
        .alert-item.consumo { border-left-color: #43a047; }
        .alert-item.info { border-left-color:rgb(209, 2, 181); }
        .alert-item.alerta { border-left-color:rgb(2, 167, 16); }
        .alert-item h4 {
            margin: 0 0 0.3rem 0;
            font-size: 1.1rem;
            color: var(--text-primary);
        }
        .alert-item p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.98rem;
        }
        /* Espaçamento entre cards de recomendação e insights também */
        .recommendations-list, .insights-container {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
            margin-top: 1.5rem;
        }
        .recommendation-item, .insight-item {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 1.2rem 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            margin-bottom: 0.5rem;
            border-left: 6px solid var(--border-color);
        }
        .recommendation-item.otimizacao { border-left-color: #43a047; }
        .recommendation-item.manutencao { border-left-color: #ff9800; }
        .recommendation-item.economia { border-left-color: #1976d2; }
        .recommendation-item.seguranca { border-left-color: #e53935; }
        .insight-item.padrao { border-left-color: #0288d1; }
        .insight-item.tendencia { border-left-color: #7b1fa2; }
        .insight-item.anomalia { border-left-color: #fbc02d; }

        /* Estilos para os novos cards */
        .cost-item, .driver-item, .vehicle-item, .route-item {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 1.2rem 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            margin-bottom: 0.5rem;
            border-left: 6px solid var(--border-color);
        }

        .cost-item { border-left-color: #1976d2; }
        .driver-item { border-left-color: #43a047; }
        .vehicle-item { border-left-color: #ff9800; }
        .route-item { border-left-color: #7b1fa2; }

        .cost-item h4, .driver-item h4, .vehicle-item h4, .route-item h4 {
            margin: 0 0 0.5rem 0;
            color: var(--text-primary);
        }

        .cost-item p, .driver-item p, .vehicle-item p, .route-item p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.5;
        }
    </style>

    <script>
        // Função para atualizar os dados
        function atualizarDados() {
            try {
                console.log('Atualizando dados...');
                fetch('../IA/atualizar_dados.php', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'include',
                    cache: 'no-cache'
                })
                .then(response => {
                    if (!response.ok) {
                        if (response.status === 401) {
                            window.location.href = '/sistema-frotas/login.php';
                            return;
                        }
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        console.log('Dados atualizados com sucesso');
                        // Atualiza os cards com os novos dados
                        if (data.data) {
                            atualizarCardAnalise(data.data.analise);
                            atualizarCardRecomendacoes(data.data.recomendacoes);
                            atualizarCardAlertas(data.data.alertas);
                            atualizarCardInsights(data.data.insights);
                        }
                    } else {
                        console.error('Erro ao atualizar dados:', data.message);
                        if (data.message === 'Usuário não está logado') {
                            window.location.href = '/sistema-frotas/login.php';
                        }
                    }
                })
                .catch(error => {
                    console.error('Erro na requisição:', error);
                });
            } catch (error) {
                console.error('Erro ao atualizar dados:', error);
            }
        }

        // Funções para atualizar cada card
        function atualizarCardAnalise(dados) {
            const container = document.querySelector('.analysis-data');
            if (!container) return;

            if (dados && dados.length > 0) {
                let html = '';
                dados.forEach(consumo => {
                    html += `<p><strong>${consumo.placa}:</strong> ${consumo.total_litros.toFixed(2)} litros</p>`;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<p>Nenhum dado de consumo disponível</p>';
            }
        }

        function atualizarCardRecomendacoes(dados) {
            const container = document.querySelector('.recommendations-list');
            if (!container) return;

            if (dados && dados.length > 0) {
                let html = '';
                dados.forEach(recomendacao => {
                    // Escolher cor por tipo
                    let cor = 'otimizacao';
                    if (recomendacao.tipo === 'manutencao' || (recomendacao.mensagem && recomendacao.mensagem.toLowerCase().includes('manuten')) ) {
                        cor = 'manutencao';
                    } else if (recomendacao.tipo === 'economia' || (recomendacao.mensagem && recomendacao.mensagem.toLowerCase().includes('economia')) ) {
                        cor = 'economia';
                    } else if (recomendacao.tipo === 'seguranca' || (recomendacao.mensagem && recomendacao.mensagem.toLowerCase().includes('segurança')) ) {
                        cor = 'seguranca';
                    }
                    html += `
                        <div class="recommendation-item ${cor}">
                            <h4>${recomendacao.mensagem}</h4>
                            ${recomendacao.dados ? `
                                <p>
                                    ${Object.entries(recomendacao.dados).map(([key, value]) => 
                                        `<strong>${key.charAt(0).toUpperCase() + key.slice(1)}:</strong> ${value}<br>`
                                    ).join('')}
                                </p>
                            ` : ''}
                        </div>
                    `;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<p>Nenhuma recomendação disponível</p>';
            }
        }

        function atualizarCardAlertas(dados) {
            const container = document.querySelector('.alerts-list');
            if (!container) return;

            if (dados && dados.length > 0) {
                let html = '';
                dados.forEach(alerta => {
                    // Escolher cor por tipo/prioridade
                    let cor = 'info';
                    if (alerta.tipo === 'manutencao' || (alerta.mensagem && alerta.mensagem.toLowerCase().includes('manuten')) ) {
                        cor = 'manutencao';
                    } else if (alerta.tipo === 'documento' || (alerta.mensagem && alerta.mensagem.toLowerCase().includes('cnh')) ) {
                        cor = 'documento';
                    } else if (alerta.tipo === 'consumo' || (alerta.mensagem && alerta.mensagem.toLowerCase().includes('consumo')) ) {
                        cor = 'consumo';
                    } else if (alerta.prioridade === 'critico' || (alerta.mensagem && alerta.mensagem.toLowerCase().includes('crítico')) ) {
                        cor = 'critico';
                    } else if (alerta.tipo === 'alerta' || (alerta.mensagem && alerta.mensagem.toLowerCase().includes('alerta')) ) {
                        cor = 'alerta';
                    }
                    html += `
                        <div class="alert-item ${cor}">
                            <h4>${alerta.mensagem}</h4>
                            ${alerta.dados ? `
                                <p>
                                    ${Object.entries(alerta.dados).map(([key, value]) => 
                                        `<strong>${key.charAt(0).toUpperCase() + key.slice(1)}:</strong> ${value}<br>`
                                    ).join('')}
                                </p>
                            ` : ''}
                        </div>
                    `;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<p>Nenhum alerta disponível</p>';
            }
        }

        function atualizarCardInsights(dados) {
            const container = document.querySelector('.insights-container');
            if (!container) return;

            if (dados && dados.length > 0) {
                let html = '';
                dados.forEach(insight => {
                    // Escolher cor por tipo
                    let cor = 'padrao';
                    if (insight.tipo === 'tendencia' || (insight.mensagem && insight.mensagem.toLowerCase().includes('tendência')) ) {
                        cor = 'tendencia';
                    } else if (insight.tipo === 'anomalia' || (insight.mensagem && insight.mensagem.toLowerCase().includes('anomalia')) ) {
                        cor = 'anomalia';
                    }
                    html += `
                        <div class="insight-item ${cor}">
                            <h4>${insight.mensagem}</h4>
                            ${insight.dados ? `
                                <p>
                                    ${Object.entries(insight.dados).map(([key, value]) => 
                                        `<strong>${key.charAt(0).toUpperCase() + key.slice(1)}:</strong> ${value}<br>`
                                    ).join('')}
                                </p>
                            ` : ''}
                        </div>
                    `;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<p>Nenhum insight disponível</p>';
            }
        }

        // Configurar atualização automática a cada 5 minutos
        setInterval(atualizarDados, 5 * 60 * 1000);

        // Atualizar dados quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            atualizarDados();
        });
    </script>

    <!-- SCRIPTS GLOBAIS: Inclua-os no final do <body> para garantir que o DOM já está carregado -->
    <script src="/sistema-frotas/js/theme.js"></script>
    <script src="/sistema-frotas/js/sidebar.js"></script>
    <script src="/sistema-frotas/js/header.js"></script>
    <script>
        // Inicialização explícita dos menus do header, se necessário
        if (typeof initNotifications === 'function') initNotifications();
        if (typeof initProfileDropdown === 'function') initProfileDropdown();
    </script>
</body>
</html> 