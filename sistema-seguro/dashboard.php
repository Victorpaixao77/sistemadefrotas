<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Verificar se está logado
verificarLogin();

// Obter dados do usuário
$usuario = obterUsuarioLogado();
$empresa_id = obterEmpresaId();

// Buscar estatísticas do dashboard
try {
    $db = getDB();
    
    // Total de clientes ativos
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM seguro_clientes WHERE seguro_empresa_id = ? AND situacao = 'ativo'");
    $stmt->execute([$empresa_id]);
    $totalClientesAtivos = $stmt->fetchColumn();
    
    // Total de clientes inativos
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM seguro_clientes WHERE seguro_empresa_id = ? AND situacao = 'inativo'");
    $stmt->execute([$empresa_id]);
    $totalClientesInativos = $stmt->fetchColumn();
    
    // Total de atendimentos abertos
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM seguro_atendimentos WHERE seguro_empresa_id = ? AND status IN ('aberto', 'em_andamento')");
    $stmt->execute([$empresa_id]);
    $totalAtendimentosAbertos = $stmt->fetchColumn();
    
    // Total de atendimentos fechados
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM seguro_atendimentos WHERE seguro_empresa_id = ? AND status IN ('resolvido', 'fechado')");
    $stmt->execute([$empresa_id]);
    $totalAtendimentosFechados = $stmt->fetchColumn();
    
    // Receitas pendentes
    $stmt = $db->prepare("SELECT COALESCE(SUM(valor), 0) as total FROM seguro_financeiro WHERE seguro_empresa_id = ? AND tipo IN ('receita', 'recorrencia') AND status = 'pendente'");
    $stmt->execute([$empresa_id]);
    $receitasPendentes = $stmt->fetchColumn();
    
    // Receitas pagas
    $stmt = $db->prepare("SELECT COALESCE(SUM(valor), 0) as total FROM seguro_financeiro WHERE seguro_empresa_id = ? AND tipo IN ('receita', 'recorrencia') AND status = 'pago'");
    $stmt->execute([$empresa_id]);
    $receitasPagas = $stmt->fetchColumn();
    
    // Total de equipamentos
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM seguro_equipamentos WHERE seguro_empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $totalEquipamentos = $stmt->fetchColumn();
    
    // Clientes cadastrados nos últimos 30 dias
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM seguro_clientes WHERE seguro_empresa_id = ? AND data_cadastro >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute([$empresa_id]);
    $clientesRecentes = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Erro ao buscar estatísticas: " . $e->getMessage());
    $totalClientesAtivos = 0;
    $totalClientesInativos = 0;
    $totalAtendimentosAbertos = 0;
    $totalAtendimentosFechados = 0;
    $receitasPendentes = 0;
    $receitasPagas = 0;
    $totalEquipamentos = 0;
    $clientesRecentes = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Seguro - Dashboard</title>
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
        
        /* Sidebar ABERTO */
        .sidebar.show {
            transform: translateX(0) !important;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 0;
            padding: 20px;
            transition: margin-left 0.3s ease, width 0.3s ease;
            min-height: 100vh;
            width: 100%;
        }
        
        /* Main Content quando menu está aberto - DESKTOP */
        .main-content.menu-open {
            margin-left: 250px;
            width: calc(100% - 250px);
        }
        
        /* Menu Toggle Button */
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
        
        /* Overlay - só aparece em mobile */
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
        
        /* Responsive - Desktop (telas grandes) */
        @media (min-width: 769px) {
            /* Em desktop, não mostra o overlay */
            .sidebar-overlay {
                display: none !important;
            }
        }
        
        /* Responsive - Mobile (telas pequenas) */
        @media (max-width: 768px) {
            /* Em mobile, o conteúdo não se move e mantém largura total */
            .main-content.menu-open {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }
        
        /* Cards */
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
            height: 300px;
        }
        .table-responsive {
            max-height: 400px;
            overflow-y: auto;
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
                <a class="nav-link active" href="dashboard.php">
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
                <a class="nav-link" href="lucratividade.php">
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
                        <i class="fas fa-tachometer-alt me-2"></i>
                        Dashboard
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

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="fas fa-users stat-icon text-primary"></i>
                        <h3 class="mt-3 mb-1"><?php echo number_format($totalClientesAtivos, 0, ',', '.'); ?></h3>
                        <p class="mb-0">Clientes Ativos</p>
                        <small class="text-muted"><?php echo $totalClientesInativos; ?> inativos</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="fas fa-headset stat-icon text-warning"></i>
                        <h3 class="mt-3 mb-1"><?php echo number_format($totalAtendimentosAbertos, 0, ',', '.'); ?></h3>
                        <p class="mb-0">Atendimentos Abertos</p>
                        <small class="text-muted"><?php echo $totalAtendimentosFechados; ?> fechados</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="fas fa-dollar-sign stat-icon text-success"></i>
                        <h3 class="mt-3 mb-1">R$ <?php echo number_format($receitasPendentes, 2, ',', '.'); ?></h3>
                        <p class="mb-0">Receitas Pendentes</p>
                        <small class="text-muted">R$ <?php echo number_format($receitasPagas, 2, ',', '.'); ?> pagas</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <i class="fas fa-cogs stat-icon text-info"></i>
                        <h3 class="mt-3 mb-1"><?php echo number_format($totalEquipamentos, 0, ',', '.'); ?></h3>
                        <p class="mb-0">Equipamentos Ativos</p>
                        <small class="text-muted"><?php echo $clientesRecentes; ?> novos (30d)</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Tables Row 1 -->
        <div class="row mb-4">
            <div class="col-md-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>
                            Gráfico de Vendas (Clientes Cadastrados)
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="salesChart" height="300"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-bell me-2"></i>
                            Notificações
                        </h5>
                    </div>
                    <div class="card-body" id="notificacoesContainer">
                        <div class="text-center py-3">
                            <i class="fas fa-spinner fa-spin text-muted"></i>
                            <p class="text-muted mt-2 mb-0">Carregando notificações...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Tables Row 2 -->
        <div class="row mb-4">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-area me-2"></i>
                            Evolução de Comissões (Últimos 6 Meses)
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="comissionChart" height="300"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>
                            Equipamentos por Status
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="equipamentosChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Tables Row 3 -->
        <div class="row mb-4">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-trophy me-2"></i>
                            Top 5 Clientes que Mais Pagam
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="topClientesTable">
                                <thead>
                                    <tr>
                                        <th>Posição</th>
                                        <th>Cliente</th>
                                        <th>CPF/CNPJ</th>
                                        <th>Total Pago</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="4" class="text-center">
                                            <i class="fas fa-spinner fa-spin"></i> Carregando...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-percent me-2"></i>
                            Taxa de Resolução de Atendimentos
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="taxaResolucaoContainer">
                            <div class="text-center py-5">
                                <i class="fas fa-spinner fa-spin"></i> Carregando...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>
                            Atividades Recentes
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Usuário</th>
                                        <th>Ação</th>
                                        <th>Descrição</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="atividadesRecentes">
                                    <tr>
                                        <td colspan="5" class="text-center py-3">
                                            <i class="fas fa-spinner fa-spin text-muted"></i> Carregando...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
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
        // CARREGAR DADOS REAIS DO DASHBOARD
        // ============================================
        
        let graficoVendas = null;
        let graficoComissoes = null;
        let graficoEquipamentos = null;
        
        // Carregar tudo ao iniciar
        document.addEventListener('DOMContentLoaded', function() {
            carregarGraficoVendas();
            carregarGraficoComissoes();
            carregarGraficoEquipamentos();
            carregarTop5Clientes();
            carregarTaxaResolucao();
            carregarNotificacoes();
            carregarAtividades();
        });
        
        // ===== GRÁFICO DE VENDAS (Clientes Cadastrados por Mês) =====
        async function carregarGraficoVendas() {
            try {
                const response = await fetch('api/dashboard_clientes_mes.php');
                const data = await response.json();
                
                if (data.sucesso) {
                    renderizarGraficoVendas(data.dados);
                } else {
                    console.error('Erro ao carregar dados do gráfico');
                    renderizarGraficoVazio();
                }
            } catch (error) {
                console.error('Erro:', error);
                renderizarGraficoVazio();
            }
        }
        
        function renderizarGraficoVendas(dados) {
            const meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
            
            // Preparar labels e data
            const labels = dados.map(d => {
                const [ano, mes] = d.mes.split('-');
                return meses[parseInt(mes) - 1] + '/' + ano.substring(2);
            });
            
            const valores = dados.map(d => parseInt(d.total));
            
            const ctx = document.getElementById('salesChart').getContext('2d');
            graficoVendas = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Clientes Cadastrados',
                        data: valores,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#667eea',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
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
                                    return 'Clientes: ' + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
        
        function renderizarGraficoVazio() {
            const ctx = document.getElementById('salesChart').getContext('2d');
            graficoVendas = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                    datasets: [{
                        label: 'Clientes Cadastrados',
                        data: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // ===== GRÁFICO DE EVOLUÇÃO DE COMISSÕES (Últimos 6 Meses) =====
        async function carregarGraficoComissoes() {
            try {
                const response = await fetch('api/dashboard_comissoes_6_meses.php');
                const data = await response.json();
                
                if (data.sucesso) {
                    renderizarGraficoComissoes(data.dados);
                } else {
                    console.error('Erro ao carregar dados de comissões');
                }
            } catch (error) {
                console.error('Erro:', error);
            }
        }
        
        function renderizarGraficoComissoes(dados) {
            const meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
            
            const labels = dados.map(d => {
                const [ano, mes] = d.mes.split('-');
                return meses[parseInt(mes) - 1] + '/' + ano.substring(2);
            });
            
            const valores = dados.map(d => parseFloat(d.total_comissao) || 0);
            
            const ctx = document.getElementById('comissionChart').getContext('2d');
            graficoComissoes = new Chart(ctx, {
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
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
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
                                    return 'R$ ' + context.parsed.y.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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

        // ===== GRÁFICO DE EQUIPAMENTOS POR STATUS =====
        async function carregarGraficoEquipamentos() {
            try {
                const response = await fetch('api/dashboard_equipamentos_status.php');
                const data = await response.json();
                
                if (data.sucesso) {
                    renderizarGraficoEquipamentos(data.dados);
                } else {
                    console.error('Erro ao carregar dados de equipamentos');
                }
            } catch (error) {
                console.error('Erro:', error);
            }
        }
        
        function renderizarGraficoEquipamentos(dados) {
            const labels = dados.map(d => d.situacao.charAt(0).toUpperCase() + d.situacao.slice(1));
            const valores = dados.map(d => parseInt(d.total));
            
            const ctx = document.getElementById('equipamentosChart').getContext('2d');
            graficoEquipamentos = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: valores,
                        backgroundColor: [
                            'rgba(40, 167, 69, 0.8)',
                            'rgba(108, 117, 125, 0.8)',
                            'rgba(255, 193, 7, 0.8)',
                            'rgba(220, 53, 69, 0.8)'
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
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.parsed;
                                }
                            }
                        }
                    }
                }
            });
        }

        // ===== TOP 5 CLIENTES QUE MAIS PAGAM =====
        async function carregarTop5Clientes() {
            try {
                const response = await fetch('api/dashboard_top_clientes.php');
                const data = await response.json();
                
                if (data.sucesso && data.clientes.length > 0) {
                    renderizarTop5Clientes(data.clientes);
                } else {
                    document.getElementById('topClientesTable').innerHTML = 
                        '<tr><td colspan="4" class="text-center py-3"><i class="fas fa-inbox me-2"></i>Nenhum dado disponível</td></tr>';
                }
            } catch (error) {
                console.error('Erro:', error);
            }
        }
        
        function renderizarTop5Clientes(clientes) {
            let html = '';
            const iconesPosicao = ['🥇', '🥈', '🥉', '4', '5'];
            
            clientes.forEach((cliente, index) => {
                html += `
                    <tr>
                        <td><strong>${iconesPosicao[index]}</strong></td>
                        <td><strong>${cliente.nome}</strong></td>
                        <td>${cliente.cpf_cnpj}</td>
                        <td><span class="badge bg-success">R$ ${parseFloat(cliente.total_pago).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span></td>
                    </tr>
                `;
            });
            
            document.getElementById('topClientesTable').innerHTML = html;
        }

        // ===== TAXA DE RESOLUÇÃO DE ATENDIMENTOS =====
        async function carregarTaxaResolucao() {
            try {
                const response = await fetch('api/dashboard_taxa_resolucao.php');
                const data = await response.json();
                
                if (data.sucesso) {
                    renderizarTaxaResolucao(data);
                } else {
                    console.error('Erro ao carregar taxa de resolução');
                }
            } catch (error) {
                console.error('Erro:', error);
            }
        }
        
        function renderizarTaxaResolucao(data) {
            const taxa = parseFloat(data.taxa_resolucao) || 0;
            const cor = taxa >= 80 ? 'success' : taxa >= 60 ? 'warning' : 'danger';
            
            const html = `
                <div class="text-center py-4">
                    <div class="mb-3">
                        <h2 class="display-4 text-${cor}">
                            <i class="fas fa-percent"></i> ${taxa.toFixed(1)}%
                        </h2>
                    </div>
                    <div class="progress" style="height: 30px;">
                        <div class="progress-bar bg-${cor}" role="progressbar" style="width: ${taxa}%" aria-valuenow="${taxa}" aria-valuemin="0" aria-valuemax="100">
                            ${taxa.toFixed(1)}%
                        </div>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">
                            ${data.atendimentos_fechados} de ${data.total_atendimentos} atendimentos resolvidos
                        </small>
                    </div>
                </div>
            `;
            
            document.getElementById('taxaResolucaoContainer').innerHTML = html;
        }
        
        // ===== NOTIFICAÇÕES =====
        async function carregarNotificacoes() {
            try {
                const response = await fetch('api/dashboard_notificacoes.php');
                const data = await response.json();
                
                if (data.sucesso && data.notificacoes.length > 0) {
                    renderizarNotificacoes(data.notificacoes);
                } else {
                    document.getElementById('notificacoesContainer').innerHTML = 
                        '<div class="text-center py-4"><i class="fas fa-inbox me-2"></i>Nenhuma notificação</div>';
                }
            } catch (error) {
                console.error('Erro:', error);
                document.getElementById('notificacoesContainer').innerHTML = 
                    '<div class="text-center py-4 text-muted"><i class="fas fa-exclamation-circle me-2"></i>Erro ao carregar</div>';
            }
        }
        
        function renderizarNotificacoes(notificacoes) {
            let html = '<div class="list-group list-group-flush">';
            
            notificacoes.forEach(notif => {
                const icones = {
                    'cliente': '<i class="fas fa-user-plus text-success me-2 mt-1"></i>',
                    'atendimento': '<i class="fas fa-headset text-info me-2 mt-1"></i>',
                    'financeiro': '<i class="fas fa-dollar-sign text-warning me-2 mt-1"></i>',
                    'equipamento': '<i class="fas fa-cogs text-primary me-2 mt-1"></i>',
                    'default': '<i class="fas fa-bell text-secondary me-2 mt-1"></i>'
                };
                
                html += `
                    <div class="list-group-item border-0 px-0">
                        <div class="d-flex">
                            ${icones[notif.tipo] || icones.default}
                            <div>
                                <small class="text-muted">${notif.tempo}</small>
                                <p class="mb-1">${notif.mensagem}</p>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            document.getElementById('notificacoesContainer').innerHTML = html;
        }
        
        // ===== ATIVIDADES RECENTES =====
        async function carregarAtividades() {
            try {
                const response = await fetch('api/dashboard_atividades.php');
                const data = await response.json();
                
                if (data.sucesso && data.atividades.length > 0) {
                    renderizarAtividades(data.atividades);
                } else {
                    document.getElementById('atividadesRecentes').innerHTML = 
                        '<tr><td colspan="5" class="text-center py-4"><i class="fas fa-inbox me-2"></i>Nenhuma atividade registrada</td></tr>';
                }
            } catch (error) {
                console.error('Erro:', error);
                document.getElementById('atividadesRecentes').innerHTML = 
                    '<tr><td colspan="5" class="text-center text-danger"><i class="fas fa-exclamation-circle me-2"></i>Erro ao carregar</td></tr>';
            }
        }
        
        function renderizarAtividades(atividades) {
            let html = '';
            
            atividades.forEach(ativ => {
                const badgeClass = {
                    'criar': 'bg-success',
                    'editar': 'bg-warning',
                    'deletar': 'bg-danger',
                    'login': 'bg-info',
                    'logout': 'bg-secondary',
                    'default': 'bg-primary'
                };
                
                html += `
                    <tr>
                        <td>${ativ.data_formatada}</td>
                        <td>${ativ.usuario}</td>
                        <td>${ativ.acao}</td>
                        <td>${ativ.descricao}</td>
                        <td><span class="badge ${badgeClass[ativ.tipo_acao] || badgeClass.default}">Concluído</span></td>
                    </tr>
                `;
            });
            
            document.getElementById('atividadesRecentes').innerHTML = html;
        }
    </script>
    <script src="js/temas.js"></script>
</body>
</html>
