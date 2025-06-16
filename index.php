<?php
// Include configuration and functions first
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Configure session before starting it
configure_session();

// Start the session
session_start();

// Check if user is logged in and has empresa_id
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    // Clear session and redirect to login
    session_unset();
    session_destroy();
    header("location: login.php");
    exit;
}

// Get user data from session
$nome_usuario = $_SESSION['nome'] ?? 'Usuário';
$empresa_id = $_SESSION['empresa_id'];

// Verify if empresa is still active
try {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT status FROM empresa_clientes WHERE id = :empresa_id AND status = 'ativo'");
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0 || $stmt->fetch()['status'] !== 'ativo') {
        session_unset();
        session_destroy();
        header("location: login.php?error=empresa_inativa");
        exit;
    }
} catch(PDOException $e) {
    // Log error but don't show to user
    error_log("Erro ao verificar status da empresa: " . $e->getMessage());
}

// Get company data
$empresa = getCompanyData();

// Set page title
$page_title = "Dashboard";

// ====== INDICADORES ACUMULADOS ======
try {
    // Conexão já criada: $conn
    // 1. Total de Veículos
    $total_veiculos = $conn->query("SELECT COUNT(*) FROM veiculos WHERE empresa_id = $empresa_id")->fetchColumn();

    // 2. Total de Motoristas/Colaboradores
    $total_motoristas = $conn->query("SELECT COUNT(*) FROM motoristas WHERE empresa_id = $empresa_id")->fetchColumn();

    // 3. Total de Rotas Realizadas
    $total_rotas = $conn->query("SELECT COUNT(*) FROM rotas WHERE empresa_id = $empresa_id")->fetchColumn();

    // 4. Total de Abastecimentos
    $total_abastecimentos = $conn->query("SELECT COUNT(*) FROM abastecimentos WHERE empresa_id = $empresa_id")->fetchColumn();

    // 5. Despesas de Viagem
    $total_desp_viagem = $conn->query("SELECT COALESCE(SUM(
        COALESCE(arla,0) + COALESCE(pedagios,0) + COALESCE(caixinha,0) + 
        COALESCE(estacionamento,0) + COALESCE(lavagem,0) + COALESCE(borracharia,0) + 
        COALESCE(eletrica_mecanica,0) + COALESCE(adiantamento,0)
    ),0) FROM despesas_viagem WHERE empresa_id = $empresa_id")->fetchColumn();

    // 6. Despesas Fixas
    $total_desp_fixas = $conn->query("SELECT COALESCE(SUM(valor),0) FROM despesas_fixas WHERE empresa_id = $empresa_id")->fetchColumn();

    // 7. Contas Pagas
    $total_contas_pagas = $conn->query("SELECT COALESCE(SUM(valor),0) FROM contas_pagar WHERE empresa_id = $empresa_id AND status_id = 2")->fetchColumn();

    // 8. Manutenções de Veículos
    $total_manutencoes = $conn->query("SELECT COALESCE(SUM(valor),0) FROM manutencoes WHERE empresa_id = $empresa_id")->fetchColumn();

    // 9. Manutenções de Pneus
    $total_pneu_manutencao = $conn->query("SELECT COALESCE(SUM(custo),0) FROM pneu_manutencao WHERE empresa_id = $empresa_id")->fetchColumn();

    // 10. Parcelas de Financiamento
    $total_parcelas_financiamento = $conn->query("SELECT COALESCE(SUM(valor),0) FROM parcelas_financiamento WHERE empresa_id = $empresa_id AND status_id = 2")->fetchColumn();

    // 11. Total de Faturamento (Fretes)
    $total_fretes = $conn->query("SELECT COALESCE(SUM(frete),0) FROM rotas WHERE empresa_id = $empresa_id")->fetchColumn();

    // 12. Total de Comissões
    $total_comissoes = $conn->query("SELECT COALESCE(SUM(comissao),0) FROM rotas WHERE empresa_id = $empresa_id")->fetchColumn();

    // 13. Lucro Líquido Geral
    $lucro_liquido = $total_fretes
        - $total_comissoes
        - $total_desp_viagem
        - $total_desp_fixas
        - $total_parcelas_financiamento
        - $total_contas_pagas
        - $total_manutencoes
        - $total_pneu_manutencao;

} catch (Exception $e) {
    $total_veiculos = $total_motoristas = $total_rotas = $total_abastecimentos = 0;
    $total_desp_viagem = $total_desp_fixas = $total_contas_pagas = 0;
    $total_manutencoes = $total_pneu_manutencao = $total_parcelas_financiamento = 0;
    $total_fretes = $total_comissoes = $lucro_liquido = 0;
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
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Sortable.js for drag-and-drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
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
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include __DIR__ . '/includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include 'includes/header.php'; ?>
            
            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1>Bem-vindo, <?php echo htmlspecialchars($nome_usuario); ?>!</h1>
                </div>
                
                <!-- Dashboard KPIs -->
                <div class="dashboard-grid mb-4">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Total de Veículos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $total_veiculos; ?></span>
                                <span class="metric-subtitle">Veículos cadastrados</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Motoristas/Colaboradores</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $total_motoristas; ?></span>
                                <span class="metric-subtitle">Motoristas ativos</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Rotas Realizadas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $total_rotas; ?></span>
                                <span class="metric-subtitle">Total de rotas</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Abastecimentos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $total_abastecimentos; ?></span>
                                <span class="metric-subtitle">Total de abastecimentos</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Despesas de Viagem</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_desp_viagem, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Custos variáveis</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Despesas Fixas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_desp_fixas, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Pagas acumuladas</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Contas Pagas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_contas_pagas, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Outros pagamentos</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Manutenções de Veículos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_manutencoes, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Veículos</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Manutenções de Pneus</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_pneu_manutencao, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Pneus</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Parcelas de Financiamento</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_parcelas_financiamento, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Pagas acumuladas</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Faturamento (Fretes)</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_fretes, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Receita bruta</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Comissões</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_comissoes, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Pagas a motoristas</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card" style="background: #e6f9ed; border: 2px solid #2ecc40;">
                        <div class="card-header">
                            <h3 style="color: #218838;">Lucro Líquido Geral</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" style="color: #218838; font-size: 2rem; font-weight: bold;">R$ <?php echo number_format($lucro_liquido, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Lucro acumulado</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Gráfico Financeiro -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Análise Financeira</h4>
                    </div>
                    <div class="card-body">
                        <canvas id="financialChart"></canvas>
                    </div>
                </div>

                <!-- Gráfico de Distribuição de Despesas -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Distribuição de Despesas (<?= date('Y') ?>)</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <canvas id="expensesDistributionChart"></canvas>
                            </div>
                            <div class="col-md-4">
                                <div id="expensesDistributionLegend" class="mt-3"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráfico de Comissões -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Comissões Pagas (<?= date('Y') ?>)</h4>
                    </div>
                    <div class="card-body">
                        <canvas id="commissionsChart"></canvas>
                    </div>
                </div>

                <!-- Gráfico de Faturamento Líquido -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Faturamento Líquido (<?= date('Y') ?>)</h4>
                    </div>
                    <div class="card-body">
                        <canvas id="netRevenueChart"></canvas>
                    </div>
                </div>
                
                <!-- Recent Activities -->
                <div class="card">
                    <div class="card-header">
                        <h2>Atividades Recentes</h2>
                    </div>
                    <div class="card-body">
                        <p>Nenhuma atividade recente encontrada.</p>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="quick-actions">
                    <div class="card">
                        <div class="card-header">
                            <h2>Ações Rápidas</h2>
                        </div>
                        <div class="card-body">
                            <div class="action-buttons">
                                <a href="pages/veiculos/novo.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Novo Veículo
                                </a>
                                <a href="pages/abastecimentos/novo.php" class="btn btn-success">
                                    <i class="fas fa-gas-pump"></i> Registrar Abastecimento
                                </a>
                                <a href="pages/manutencoes/nova.php" class="btn btn-warning">
                                    <i class="fas fa-wrench"></i> Nova Manutenção
                                </a>
                                <a href="pages/relatorios/geral.php" class="btn btn-info">
                                    <i class="fas fa-chart-bar"></i> Relatórios
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <!-- JavaScript Files -->
    <script src="js/dashboard.js"></script>
    <script src="js/theme.js"></script>
    <script src="js/sidebar.js"></script>
    <script>
        // Função para inicializar o gráfico de distribuição de despesas
        async function initExpensesDistributionChart() {
            try {
                const response = await fetch('/sistema-frotas/api/expenses_distribution.php');
                if (!response.ok) {
                    throw new Error('Erro ao carregar dados de distribuição de despesas');
                }
                
                const data = await response.json();
                
                // Formatar os valores para exibição
                const formattedData = {
                    labels: data.labels,
                    datasets: [{
                        ...data.datasets[0],
                        data: data.datasets[0].data.map(value => 
                            new Intl.NumberFormat('pt-BR', {
                                style: 'currency',
                                currency: 'BRL'
                            }).format(value)
                        )
                    }]
                };
                
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
                                display: false
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
                
                // Criar legenda personalizada
                const legendContainer = document.getElementById('expensesDistributionLegend');
                data.labels.forEach((label, index) => {
                    const color = data.datasets[0].backgroundColor[index];
                    const value = new Intl.NumberFormat('pt-BR', {
                        style: 'currency',
                        currency: 'BRL'
                    }).format(data.datasets[0].data[index]);
                    
                    const legendItem = document.createElement('div');
                    legendItem.className = 'd-flex align-items-center mb-2';
                    legendItem.innerHTML = `
                        <div class="me-2" style="width: 20px; height: 20px; background-color: ${color}; border-radius: 50%;"></div>
                        <div>
                            <div class="small text-muted">${label}</div>
                            <div class="fw-bold">${value}</div>
                        </div>
                    `;
                    legendContainer.appendChild(legendItem);
                });
                
            } catch (error) {
                console.error('Erro ao carregar gráfico de distribuição de despesas:', error);
            }
        }

        // Função para inicializar o gráfico de comissões
        async function initCommissionsChart() {
            try {
                const response = await fetch('/sistema-frotas/api/commissions_analytics.php');
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
                const response = await fetch('/sistema-frotas/api/net_revenue_analytics.php');
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

        // Inicializar os gráficos quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            initExpensesDistributionChart();
            initCommissionsChart();
            initNetRevenueChart();
        });
    </script>
</body>
</html>
