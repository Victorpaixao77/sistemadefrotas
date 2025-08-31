<?php
// Corrigir caminhos dos arquivos de configuração
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';

// Verifica autenticação
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['empresa_id'])) {
    header('Location: ../pages_adm/login.php');
    exit;
}

try {
    // Obter conexão com banco
    $conn = getConnection();
    
    // Incluir classes IA
    require_once 'analise.php';
    require_once 'alertas.php';
    require_once 'recomendacoes.php';
    require_once 'insights.php';
    require_once 'notificacoes.php';
    require_once 'config.php';
    require_once 'AnaliseAvancada.php';
    
    // Inicializa classes
    $analise = new Analise($conn, $_SESSION['empresa_id']);
    $alertas = new Alertas($conn, $_SESSION['empresa_id']);
    $recomendacoes = new Recomendacoes($conn, $_SESSION['empresa_id']);
    $insights = new Insights($conn, $_SESSION['empresa_id']);
    $notificacoes = new Notificacoes($_SESSION['empresa_id']);
    $config = new ConfiguracoesIA($_SESSION['empresa_id']);
    $analise_avancada = new AnaliseAvancada($conn, $_SESSION['empresa_id']);

    // Obtém dados
    $dadosConsumo = $analise->analisarConsumo();
    $dadosManutencao = $analise->analisarManutencao();
    $dadosRotas = $analise->analisarRotas();
    $alertasSistema = $alertas->obterTodosAlertas();
    $recomendacoesSistema = $recomendacoes->obterTodasRecomendacoes();
    $insightsSistema = $insights->obterTodosInsights();
    $estatisticas = $notificacoes->obterEstatisticas();
    $previsoes = $analise_avancada->preverCustosFuturos();
    
} catch (Exception $e) {
    error_log("Erro no painel IA: " . $e->getMessage());
    $dadosConsumo = [];
    $dadosManutencao = [];
    $dadosRotas = [];
    $alertasSistema = [];
    $recomendacoesSistema = [];
    $insightsSistema = [];
    $estatisticas = ['pendentes' => 0, 'alta_prioridade' => 0];
    $previsoes = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Inteligente - Sistema de Frotas</title>
    <link rel="icon" type="image/png" href="../logo.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link href="css/painel.css" rel="stylesheet">
    
    <style>
        .dashboard-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .stats-card {
            text-align: center;
            position: relative;
        }
        
        .stats-card .icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .stats-card .number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stats-card .label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .alert-item {
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            border-left: 4px solid;
        }
        
        .alert-item.alta {
            background: #ffe6e6;
            border-left-color: #dc3545;
        }
        
        .alert-item.media {
            background: #fff3cd;
            border-left-color: #ffc107;
        }
        
        .alert-item.baixa {
            background: #d1ecf1;
            border-left-color: #17a2b8;
        }
        
        .insight-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .insight-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .insight-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
        }
        
        .insight-icon.consumo { background: #dc3545; }
        .insight-icon.manutencao { background: #ffc107; }
        .insight-icon.rota { background: #17a2b8; }
        .insight-icon.custo { background: #28a745; }
        .insight-icon.seguranca { background: #6f42c1; }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <!-- Cabeçalho -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">Painel Inteligente</h1>
            <div>
                <a href="../pages/ia_painel.php" class="btn btn-primary me-2">
                    <i class="fas fa-arrow-left"></i> Voltar ao Painel Principal
                </a>
                <button id="marcarTodasLidas" class="btn btn-outline-primary">
                    <i class="fas fa-check-double"></i> Marcar todas como lidas
                </button>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="dashboard-card stats-card">
                    <div class="icon text-primary">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="number" id="totalNotificacoes"><?php echo $estatisticas['pendentes'] ?? 0; ?></div>
                    <div class="label">Notificações Pendentes</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card stats-card">
                    <div class="icon text-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="number" id="altaPrioridade"><?php echo $estatisticas['alta_prioridade'] ?? 0; ?></div>
                    <div class="label">Alertas de Alta Prioridade</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card stats-card">
                    <div class="icon text-success">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <div class="number" id="totalRecomendacoes"><?php echo count($recomendacoesSistema); ?></div>
                    <div class="label">Recomendações</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card stats-card">
                    <div class="icon text-info">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="number" id="totalInsights"><?php echo count($insightsSistema); ?></div>
                    <div class="label">Insights</div>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="dashboard-card">
                    <h5 class="mb-3">Consumo de Combustível</h5>
                    <div class="chart-container">
                        <canvas id="consumoChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="dashboard-card">
                    <h5 class="mb-3">Gastos com Manutenção</h5>
                    <div class="chart-container">
                        <canvas id="manutencaoChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alertas e Recomendações -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="dashboard-card">
                    <h5 class="mb-3">Alertas do Sistema</h5>
                    <div id="alertasContainer">
                        <?php if (empty($alertasSistema)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-check-circle fa-3x mb-3"></i>
                                <p>Nenhum alerta pendente</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($alertasSistema as $alerta): ?>
                                <div class="alert-item <?php echo $alerta['prioridade']; ?>">
                                    <h5><?php echo htmlspecialchars($alerta['titulo']); ?></h5>
                                    <p><?php echo htmlspecialchars($alerta['mensagem']); ?></p>
                                    <small><?php echo date('d/m/Y H:i', strtotime($alerta['data_criacao'] ?? 'now')); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="dashboard-card">
                    <h5 class="mb-3">Recomendações</h5>
                    <div id="recomendacoesContainer">
                        <?php if (empty($recomendacoesSistema)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-thumbs-up fa-3x mb-3"></i>
                                <p>Nenhuma recomendação no momento</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recomendacoesSistema as $rec): ?>
                                <div class="insight-card">
                                    <div class="insight-header">
                                        <div class="insight-icon <?php echo $rec['tipo']; ?>">
                                            <i class="fas fa-<?php echo getIconForType($rec['tipo']); ?>"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-0"><?php echo htmlspecialchars($rec['mensagem']); ?></h5>
                                            <small class="text-muted"><?php echo htmlspecialchars($rec['veiculo'] ?? $rec['rota'] ?? ''); ?></small>
                                        </div>
                                    </div>
                                    <?php if (!empty($rec['acoes'])): ?>
                                        <ul class="mb-0">
                                            <?php foreach ($rec['acoes'] as $acao): ?>
                                                <li><?php echo htmlspecialchars($acao); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Insights -->
        <div class="row">
            <div class="col-12">
                <div class="dashboard-card">
                    <h5 class="mb-3">Insights</h5>
                    <div id="insightsContainer">
                        <?php if (empty($insightsSistema)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-chart-bar fa-3x mb-3"></i>
                                <p>Nenhum insight disponível no momento</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($insightsSistema as $insight): ?>
                                <div class="insight-card">
                                    <div class="insight-header">
                                        <div class="insight-icon <?php echo $insight['tipo']; ?>">
                                            <i class="fas fa-<?php echo getIconForType($insight['tipo']); ?>"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-0"><?php echo htmlspecialchars($insight['mensagem']); ?></h5>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($insight['veiculo'] ? "{$insight['veiculo']} - {$insight['modelo']}" : ($insight['rota'] ?? '')); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <?php if (!empty($insight['dados'])): ?>
                                        <div class="mt-3">
                                            <pre class="mb-0"><code><?php echo htmlspecialchars(json_encode($insight['dados'], JSON_PRETTY_PRINT)); ?></code></pre>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="js/painel.js"></script>
    
    <script>
        // Inicializar gráficos se houver dados
        document.addEventListener('DOMContentLoaded', function() {
            // Dados dos gráficos (se disponíveis)
            const dadosConsumo = <?php echo json_encode($dadosConsumo); ?>;
            const dadosManutencao = <?php echo json_encode($dadosManutencao); ?>;
            
            // Inicializar gráficos se houver dados
            if (dadosConsumo && dadosConsumo.length > 0) {
                // Código para inicializar gráfico de consumo
            }
            
            if (dadosManutencao && dadosManutencao.length > 0) {
                // Código para inicializar gráfico de manutenção
            }
        });
    </script>
</body>
</html>

<?php
function getIconForType($type) {
    $icons = [
        'consumo' => 'gas-pump',
        'manutencao' => 'tools',
        'rota' => 'route',
        'custo' => 'dollar-sign',
        'documento' => 'file-alt',
        'seguranca' => 'shield-alt'
    ];
    return $icons[$type] ?? 'info-circle';
}
?> 