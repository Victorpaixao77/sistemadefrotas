<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Verificar se está logado
verificarLogin();

// Obter dados do usuário
$usuario = obterUsuarioLogado();
$empresa_id = obterEmpresaId();

// Buscar dados de lucratividade
try {
    $db = getDB();
    
    // Buscar empresa
    $stmt = $db->prepare("SELECT * FROM seguro_empresa_clientes WHERE id = ?");
    $stmt->execute([$empresa_id]);
    $empresa = $stmt->fetch();
    
    $dia_fechamento = $empresa['dia_fechamento'] ?? 25;
    $porcentagem_fixa = floatval($empresa['porcentagem_fixa']);
    
    // Comissão total (acumulado)
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(
                (sf.valor_pago * ? / 100) + (sf.valor_pago * c.porcentagem_recorrencia / 100)
            ), 0) as total_comissao
        FROM seguro_financeiro sf
        INNER JOIN seguro_clientes c ON (COALESCE(NULLIF(sf.seguro_cliente_id, 0), sf.cliente_id) = c.id)
        WHERE sf.seguro_empresa_id = ?
          AND sf.status = 'pago'
          AND sf.valor_pago > 0
          AND sf.data_baixa IS NOT NULL
          AND (sf.cliente_nao_encontrado IS NULL OR sf.cliente_nao_encontrado = 'nao')
    ");
    $stmt->execute([$porcentagem_fixa, $empresa_id]);
    $comissaoTotal = $stmt->fetchColumn();
    
    // Comissão do mês atual
    $mes_atual = date('Y-m');
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(
                (sf.valor_pago * ? / 100) + (sf.valor_pago * c.porcentagem_recorrencia / 100)
            ), 0) as comissao_mes
        FROM seguro_financeiro sf
        INNER JOIN seguro_clientes c ON (COALESCE(NULLIF(sf.seguro_cliente_id, 0), sf.cliente_id) = c.id)
        WHERE sf.seguro_empresa_id = ?
          AND sf.status = 'pago'
          AND sf.valor_pago > 0
          AND sf.data_baixa IS NOT NULL
          AND DATE_FORMAT(sf.data_baixa, '%Y-%m') = ?
          AND (sf.cliente_nao_encontrado IS NULL OR sf.cliente_nao_encontrado = 'nao')
    ");
    $stmt->execute([$porcentagem_fixa, $empresa_id, $mes_atual]);
    $comissaoMesAtual = $stmt->fetchColumn();
    
    // Total de clientes ativos
    $stmt = $db->prepare("SELECT COUNT(*) FROM seguro_clientes WHERE seguro_empresa_id = ? AND situacao = 'ativo'");
    $stmt->execute([$empresa_id]);
    $clientesAtivos = $stmt->fetchColumn();
    
    // Ticket médio (comissão média por cliente)
    $ticketMedio = $clientesAtivos > 0 ? $comissaoTotal / $clientesAtivos : 0;
    
} catch (PDOException $e) {
    error_log("Erro ao buscar dados de lucratividade: " . $e->getMessage());
    $comissaoTotal = 0;
    $comissaoMesAtual = 0;
    $clientesAtivos = 0;
    $ticketMedio = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Seguro - Lucratividade</title>
    <script src="js/tema-instantaneo.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/temas.css" rel="stylesheet">
    <link href="css/tema-escuro-forcado.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            z-index: 1000;
            transition: transform 0.3s ease;
            overflow-y: auto;
            transform: translateX(-100%);
        }
        
        .sidebar .nav-link {
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin: 5px 10px;
            transition: all 0.3s;
            text-decoration: none;
            display: block;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .sidebar.show {
            transform: translateX(0) !important;
        }
        
        .main-content {
            margin-left: 0;
            padding: 20px;
            transition: margin-left 0.3s ease, width 0.3s ease;
            min-height: 100vh;
            width: 100%;
        }
        
        .main-content.menu-open {
            margin-left: 250px;
            width: calc(100% - 250px);
        }
        
        .menu-toggle {
            background: #667eea;
            border: none;
            color: white;
            font-size: 1.5rem;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 15px;
            display: block;
        }
        
        .menu-toggle:hover {
            background: #5a6fd8;
            transform: scale(1.05);
        }
        
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }
        
        .sidebar-overlay.show {
            display: block !important;
        }
        
        @media (min-width: 769px) {
            .sidebar-overlay {
                display: none !important;
            }
        }
        
        @media (max-width: 768px) {
            .main-content.menu-open {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s;
            margin-bottom: 20px;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stat-card-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }
        
        .stat-card-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .stat-card-info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        
        .stat-card .card-body {
            padding: 2rem;
        }
        
        .stat-icon {
            font-size: 3rem;
            opacity: 0.8;
        }
        
        .header {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .chart-container {
            position: relative;
            height: 350px;
        }
        
        .metric-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .metric-value {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .metric-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="p-3">
            <h4 class="text-white text-center mb-4">
                <i class="fas fa-shield-alt me-2"></i>
                Sistema Seguro
            </h4>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="empresa.php">
                    <i class="fas fa-building me-2"></i>
                    Empresa
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="clientes.php">
                    <i class="fas fa-users me-2"></i>
                    Clientes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="contratos.php">
                    <i class="fas fa-file-contract me-2"></i>
                    Contratos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="financeiro.php">
                    <i class="fas fa-chart-line me-2"></i>
                    Financeiro
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="atendimento.php">
                    <i class="fas fa-headset me-2"></i>
                    Atendimento
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="lucratividade.php">
                    <i class="fas fa-chart-pie me-2"></i>
                    Lucratividade
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="relatorios.php">
                    <i class="fas fa-file-alt me-2"></i>
                    Relatórios
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <button class="menu-toggle me-3" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h2 class="mb-0">
                        <i class="fas fa-chart-pie me-2"></i>
                        Análise de Lucratividade
                    </h2>
                </div>
                <div class="d-flex align-items-center">
                    <span class="me-3">Bem-vindo, <?php echo htmlspecialchars($usuario['nome']); ?></span>
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            Perfil
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="perfil.php"><i class="fas fa-user me-2"></i>Meu Perfil</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Configurações</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair do Sistema</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtro de Período -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Data Início:</label>
                        <input type="month" id="dataInicio" class="form-control" value="<?php echo date('Y-m', strtotime('-5 months')); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Data Fim:</label>
                        <input type="month" id="dataFim" class="form-control" value="<?php echo date('Y-m'); ?>">
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary w-100" onclick="atualizarDados()">
                            <i class="fas fa-sync-alt me-2"></i>
                            Atualizar Análise
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-success w-100" onclick="exportarRelatorio()">
                            <i class="fas fa-file-excel me-2"></i>
                            Exportar Excel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cards de Métricas Principais -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card-success">
                    <div class="card-body text-center">
                        <i class="fas fa-dollar-sign stat-icon"></i>
                        <h3 class="mt-3 mb-1" id="comissaoTotal">R$ <?php echo number_format($comissaoTotal, 2, ',', '.'); ?></h3>
                        <p class="mb-0">Comissão Total</p>
                        <small>Acumulado</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-alt stat-icon"></i>
                        <h3 class="mt-3 mb-1" id="comissaoMes">R$ <?php echo number_format($comissaoMesAtual, 2, ',', '.'); ?></h3>
                        <p class="mb-0">Comissão do Mês</p>
                        <small id="legendaComissaoMes"><?php echo strftime('%B/%Y', strtotime(date('Y-m-01'))); ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card-warning">
                    <div class="card-body text-center">
                        <i class="fas fa-users stat-icon"></i>
                        <h3 class="mt-3 mb-1"><?php echo number_format($clientesAtivos, 0, ',', '.'); ?></h3>
                        <p class="mb-0">Clientes Ativos</p>
                        <small>Gerando comissão</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card-info">
                    <div class="card-body text-center">
                        <i class="fas fa-chart-line stat-icon"></i>
                        <h3 class="mt-3 mb-1" id="ticketMedio">R$ <?php echo number_format($ticketMedio, 2, ',', '.'); ?></h3>
                        <p class="mb-0">Ticket Médio</p>
                        <small>Por cliente</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="row mb-4">
            <div class="col-md-8 mb-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-area me-2 text-success"></i>
                            Evolução de Comissões
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartEvolucaoComissoes"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie me-2 text-primary"></i>
                            Distribuição por Status
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartDistribuicao"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Análise Detalhada -->
        <div class="row mb-4">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2 text-warning"></i>
                            Comissões por Mês
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="chartComissoesMes"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-trophy me-2 text-info"></i>
                            Top 10 Clientes por Comissão
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                            <table class="table table-hover" id="tabelaTopClientes">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Pos.</th>
                                        <th>Cliente</th>
                                        <th>Comissão</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="3" class="text-center">
                                            <i class="fas fa-spinner fa-spin"></i> Carregando...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Indicadores de Performance -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-tachometer-alt me-2 text-danger"></i>
                            Indicadores de Performance (KPIs)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row" id="kpisContainer">
                            <div class="col-md-3 mb-3">
                                <div class="metric-card">
                                    <div class="metric-value" id="crescimentoMes">-</div>
                                    <div class="metric-label">Crescimento Mensal</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="metric-card">
                                    <div class="metric-value" id="mediaCliente">-</div>
                                    <div class="metric-label">Média por Cliente</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="metric-card">
                                    <div class="metric-value" id="projecaoAnual">-</div>
                                    <div class="metric-label">Projeção Anual</div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="metric-card">
                                    <div class="metric-value" id="margemLucro">-</div>
                                    <div class="metric-label">Margem de Lucro</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/menu-responsivo.js"></script>
    <script>
        // ============================================
        // LUCRATIVIDADE - ANÁLISE COMPLETA
        // ============================================
        
        let chartEvolucao, chartDistribuicao, chartComissoesMes;
        
        document.addEventListener('DOMContentLoaded', function() {
            carregarDadosLucratividade();
        });
        
        async function carregarDadosLucratividade(dataInicio = null, dataFim = null) {
            // Se não foram passadas datas, pegar dos inputs
            if (!dataInicio) {
                dataInicio = document.getElementById('dataInicio').value;
            }
            if (!dataFim) {
                dataFim = document.getElementById('dataFim').value;
            }
            
            console.log('🔄 Carregando dados de', dataInicio, 'até', dataFim);
            
            try {
                await Promise.all([
                    carregarResumo(dataInicio, dataFim),
                    carregarEvolucaoComissoes(dataInicio, dataFim),
                    carregarDistribuicaoClientes(dataInicio, dataFim),
                    carregarComissoesPorMes(dataInicio, dataFim),
                    carregarTopClientesComissao(dataInicio, dataFim),
                    carregarKPIs(dataInicio, dataFim)
                ]);
            } catch (error) {
                console.error('Erro ao carregar dados:', error);
            }
        }
        
        async function carregarResumo(dataInicio, dataFim) {
            try {
                const response = await fetch(`api/lucratividade_resumo.php?dataInicio=${dataInicio}&dataFim=${dataFim}`);
                const data = await response.json();
                
                if (data.sucesso) {
                    // Atualizar Comissão Total
                    document.getElementById('comissaoTotal').textContent = 
                        'R$ ' + parseFloat(data.comissao_total).toLocaleString('pt-BR', {minimumFractionDigits: 2});
                    
                    // Atualizar Comissão do Mês
                    document.getElementById('comissaoMes').textContent = 
                        'R$ ' + parseFloat(data.comissao_mes).toLocaleString('pt-BR', {minimumFractionDigits: 2});
                    
                    // Atualizar legenda do mês
                    const [ano, mes] = data.mes_referencia.split('-');
                    const meses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
                                   'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
                    document.getElementById('legendaComissaoMes').textContent = 
                        meses[parseInt(mes) - 1] + '/' + ano;
                    
                    // Atualizar Ticket Médio
                    document.getElementById('ticketMedio').textContent = 
                        'R$ ' + parseFloat(data.ticket_medio).toLocaleString('pt-BR', {minimumFractionDigits: 2});
                    
                    console.log('✅ Resumo atualizado:', {
                        comissao_total: data.comissao_total,
                        comissao_mes: data.comissao_mes,
                        ticket_medio: data.ticket_medio,
                        mes_referencia: data.mes_referencia
                    });
                }
            } catch (error) {
                console.error('Erro ao carregar resumo:', error);
            }
        }
        
        async function carregarEvolucaoComissoes(dataInicio, dataFim) {
            try {
                const response = await fetch(`api/lucratividade_evolucao.php?dataInicio=${dataInicio}&dataFim=${dataFim}`);
                const data = await response.json();
                
                if (data.sucesso) {
                    renderizarGraficoEvolucao(data.dados);
                }
            } catch (error) {
                console.error('Erro:', error);
            }
        }
        
        function renderizarGraficoEvolucao(dados) {
            // Destruir gráfico anterior se existir
            if (chartEvolucao) {
                chartEvolucao.destroy();
            }
            
            const meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
            const labels = dados.map(d => {
                const [ano, mes] = d.mes.split('-');
                return meses[parseInt(mes) - 1] + '/' + ano.substring(2);
            });
            const valores = dados.map(d => parseFloat(d.comissao));
            
            const ctx = document.getElementById('chartEvolucaoComissoes').getContext('2d');
            chartEvolucao = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Comissões (R$)',
                        data: valores,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#28a745',
                        pointRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'R$ ' + context.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                }
                            }
                        }
                    }
                }
            });
        }
        
        async function carregarDistribuicaoClientes(dataInicio, dataFim) {
            try {
                const response = await fetch(`api/lucratividade_distribuicao.php?dataInicio=${dataInicio}&dataFim=${dataFim}`);
                const data = await response.json();
                
                if (data.sucesso) {
                    renderizarGraficoDistribuicao(data.dados);
                }
            } catch (error) {
                console.error('Erro:', error);
            }
        }
        
        function renderizarGraficoDistribuicao(dados) {
            // Destruir gráfico anterior se existir
            if (chartDistribuicao) {
                chartDistribuicao.destroy();
            }
            
            const ctx = document.getElementById('chartDistribuicao').getContext('2d');
            chartDistribuicao = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Ativos', 'Inativos'],
                    datasets: [{
                        data: [dados.ativos, dados.inativos],
                        backgroundColor: ['#28a745', '#6c757d']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        async function carregarComissoesPorMes(dataInicio, dataFim) {
            try {
                const url = `api/dashboard_comissoes_6_meses.php?dataInicio=${dataInicio}&dataFim=${dataFim}`;
                console.log('📊 Carregando Comissões por Mês:', url);
                
                const response = await fetch(url);
                const data = await response.json();
                
                console.log('📊 Resposta Comissões por Mês:', data);
                
                if (data.sucesso && data.dados && data.dados.length > 0) {
                    renderizarGraficoComissoesMes(data.dados);
                } else {
                    console.warn('⚠️ Sem dados para o gráfico de comissões por mês');
                    // Limpar gráfico se não houver dados
                    if (chartComissoesMes) {
                        chartComissoesMes.destroy();
                    }
                }
            } catch (error) {
                console.error('❌ Erro ao carregar comissões por mês:', error);
            }
        }
        
        function renderizarGraficoComissoesMes(dados) {
            console.log('🎨 Renderizando gráfico de comissões por mês com', dados.length, 'meses');
            
            // Destruir gráfico anterior se existir
            if (chartComissoesMes) {
                chartComissoesMes.destroy();
            }
            
            const meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
            const labels = dados.map(d => {
                const [ano, mes] = d.mes.split('-');
                return meses[parseInt(mes) - 1] + '/' + ano.substring(2);
            });
            const valores = dados.map(d => parseFloat(d.total_comissao));
            
            console.log('Labels:', labels);
            console.log('Valores:', valores);
            
            const ctx = document.getElementById('chartComissoesMes').getContext('2d');
            chartComissoesMes = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Comissão Mensal',
                        data: valores,
                        backgroundColor: 'rgba(255, 193, 7, 0.8)',
                        borderColor: '#ffc107',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'R$ ' + context.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                }
                            }
                        }
                    }
                }
            });
        }
        
        async function carregarTopClientesComissao(dataInicio, dataFim) {
            try {
                const response = await fetch(`api/lucratividade_top_clientes.php?dataInicio=${dataInicio}&dataFim=${dataFim}`);
                const data = await response.json();
                
                if (data.sucesso && data.clientes.length > 0) {
                    renderizarTopClientes(data.clientes);
                }
            } catch (error) {
                console.error('Erro:', error);
            }
        }
        
        function renderizarTopClientes(clientes) {
            let html = '';
            const icones = ['🥇', '🥈', '🥉'];
            
            clientes.forEach((cliente, index) => {
                const icone = index < 3 ? icones[index] : `${index + 1}º`;
                html += `
                    <tr>
                        <td><strong>${icone}</strong></td>
                        <td>${cliente.nome}</td>
                        <td><span class="badge bg-success">R$ ${parseFloat(cliente.comissao).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span></td>
                    </tr>
                `;
            });
            
            document.querySelector('#tabelaTopClientes tbody').innerHTML = html;
        }
        
        async function carregarKPIs(dataInicio, dataFim) {
            try {
                const response = await fetch(`api/lucratividade_kpis.php?dataInicio=${dataInicio}&dataFim=${dataFim}`);
                const data = await response.json();
                
                if (data.sucesso) {
                    document.getElementById('crescimentoMes').textContent = data.crescimento_mensal + '%';
                    document.getElementById('mediaCliente').textContent = 'R$ ' + parseFloat(data.media_cliente).toLocaleString('pt-BR', {minimumFractionDigits: 2});
                    document.getElementById('projecaoAnual').textContent = 'R$ ' + parseFloat(data.projecao_anual).toLocaleString('pt-BR', {minimumFractionDigits: 2});
                    document.getElementById('margemLucro').textContent = data.margem_lucro + '%';
                }
            } catch (error) {
                console.error('Erro:', error);
            }
        }
        
        function atualizarDados() {
            const dataInicio = document.getElementById('dataInicio').value;
            const dataFim = document.getElementById('dataFim').value;
            
            console.log('🔄 Atualizando dados do período:', dataInicio, 'até', dataFim);
            
            // Recarregar todos os dados com o filtro de período
            carregarDadosLucratividade(dataInicio, dataFim);
        }
        
        function exportarRelatorio() {
            alert('Funcionalidade de exportação será implementada em breve!');
            // TODO: Implementar exportação para Excel
        }
    </script>
    <script src="js/temas.js"></script>
</body>
</html>

