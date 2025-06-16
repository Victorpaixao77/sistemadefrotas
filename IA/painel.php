<?php
require_once '../config/config.php';
require_once '../config/functions.php';

// Verifica autenticação
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Inicializa classes
$analise = new Analise($pdo, $_SESSION['empresa_id']);
$alertas = new Alertas($pdo, $_SESSION['empresa_id']);
$recomendacoes = new Recomendacoes($pdo, $_SESSION['empresa_id']);
$insights = new Insights($pdo, $_SESSION['empresa_id']);
$notificacoes = new Notificacoes($pdo, $_SESSION['empresa_id']);

// Obtém dados
$dadosConsumo = $analise->analisarConsumo();
$dadosManutencao = $analise->analisarManutencao();
$dadosRotas = $analise->analisarRotas();
$alertasSistema = $alertas->obterTodosAlertas();
$recomendacoesSistema = $recomendacoes->obterTodasRecomendacoes();
$insightsSistema = $insights->obterTodosInsights();
$estatisticas = $notificacoes->obterEstatisticas();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Inteligente - Sistema de Frotas</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link href="css/painel.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <!-- Cabeçalho -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">Painel Inteligente</h1>
            <div>
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
                    <div class="number" id="totalNotificacoes"><?php echo $estatisticas['pendentes']; ?></div>
                    <div class="label">Notificações Pendentes</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card stats-card">
                    <div class="icon text-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="number" id="altaPrioridade"><?php echo $estatisticas['alta_prioridade']; ?></div>
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
                        <?php foreach ($alertasSistema as $alerta): ?>
                            <div class="alert-item <?php echo $alerta['prioridade']; ?>">
                                <h5><?php echo $alerta['titulo']; ?></h5>
                                <p><?php echo $alerta['mensagem']; ?></p>
                                <small><?php echo date('d/m/Y H:i', strtotime($alerta['data'])); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="dashboard-card">
                    <h5 class="mb-3">Recomendações</h5>
                    <div id="recomendacoesContainer">
                        <?php foreach ($recomendacoesSistema as $rec): ?>
                            <div class="insight-card">
                                <div class="insight-header">
                                    <div class="insight-icon <?php echo $rec['tipo']; ?>">
                                        <i class="fas fa-<?php echo getIconForType($rec['tipo']); ?>"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0"><?php echo $rec['mensagem']; ?></h5>
                                        <small class="text-muted"><?php echo $rec['veiculo'] ?? $rec['rota']; ?></small>
                                    </div>
                                </div>
                                <?php if (!empty($rec['acoes'])): ?>
                                    <ul class="mb-0">
                                        <?php foreach ($rec['acoes'] as $acao): ?>
                                            <li><?php echo $acao; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
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
                        <?php foreach ($insightsSistema as $insight): ?>
                            <div class="insight-card">
                                <div class="insight-header">
                                    <div class="insight-icon <?php echo $insight['tipo']; ?>">
                                        <i class="fas fa-<?php echo getIconForType($insight['tipo']); ?>"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0"><?php echo $insight['mensagem']; ?></h5>
                                        <small class="text-muted">
                                            <?php echo $insight['veiculo'] ? "{$insight['veiculo']} - {$insight['modelo']}" : $insight['rota']; ?>
                                        </small>
                                    </div>
                                </div>
                                <?php if (!empty($insight['dados'])): ?>
                                    <div class="mt-3">
                                        <pre class="mb-0"><code><?php echo json_encode($insight['dados'], JSON_PRETTY_PRINT); ?></code></pre>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="js/painel.js"></script>
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