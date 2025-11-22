<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

configure_session();
session_start();
require_authentication();

$page_title = "Lucratividade - Dashboard Avançado (Exemplo)";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    <link rel="icon" type="image/png" href="../logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-waterfall"></script>
    
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .kpi-card {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-left: 4px solid var(--accent-primary);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }
        
        .kpi-card.success {
            border-left-color: var(--accent-success);
        }
        
        .kpi-card.warning {
            border-left-color: var(--accent-warning);
        }
        
        .kpi-card.danger {
            border-left-color: var(--accent-danger);
        }
        
        .kpi-card.info {
            border-left-color: var(--accent-secondary);
        }
        
        .kpi-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .kpi-title {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .kpi-icon {
            font-size: 1.5rem;
            opacity: 0.7;
        }
        
        .kpi-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 5px;
        }
        
        .kpi-subtitle {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .kpi-trend {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
            font-size: 0.85rem;
        }
        
        .kpi-trend.positive {
            color: var(--accent-success);
        }
        
        .kpi-trend.negative {
            color: var(--accent-danger);
        }
        
        .filters-section {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-group label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.9rem;
        }
        
        .chart-container {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
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
        
        .btn-export {
            background: var(--accent-primary);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.3s;
        }
        
        .btn-export:hover {
            background: var(--accent-secondary);
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-success {
            background: rgba(16, 185, 129, 0.2);
            color: var(--accent-success);
        }
        
        .badge-warning {
            background: rgba(245, 158, 11, 0.2);
            color: var(--accent-warning);
        }
        
        .badge-danger {
            background: rgba(239, 68, 68, 0.2);
            color: var(--accent-danger);
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar_pages.php'; ?>
    <div class="main-content">
        <?php include '../includes/header.php'; ?>
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h1><i class="fas fa-chart-line"></i> <?php echo $page_title; ?></h1>
                <div class="dashboard-actions">
                    <div class="view-controls">
                        <button id="exportBtn" class="btn-restore-layout" title="Exportar">
                            <i class="fas fa-file-export"></i>
                        </button>
                        <button id="helpBtn" class="btn-help" title="Ajuda">
                            <i class="fas fa-question-circle"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Filtros Avançados -->
            <div class="filters-section">
                <div class="filter-group">
                    <label><i class="fas fa-calendar"></i> Período</label>
                    <select id="periodFilter">
                        <option value="month">Mensal</option>
                        <option value="quarter">Trimestral</option>
                        <option value="semester">Semestral</option>
                        <option value="year">Anual</option>
                        <option value="custom">Personalizado</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-truck"></i> Veículo</label>
                    <select id="vehicleFilter">
                        <option value="">Todos os Veículos</option>
                        <option value="1">ABC-1234 - Caminhão 1</option>
                        <option value="2">DEF-5678 - Caminhão 2</option>
                        <option value="3">GHI-9012 - Caminhão 3</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-user"></i> Motorista</label>
                    <select id="driverFilter">
                        <option value="">Todos os Motoristas</option>
                        <option value="1">João Silva</option>
                        <option value="2">Maria Santos</option>
                        <option value="3">Pedro Oliveira</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-users"></i> Cliente</label>
                    <select id="clientFilter">
                        <option value="">Todos os Clientes</option>
                        <option value="1">Cliente A</option>
                        <option value="2">Cliente B</option>
                        <option value="3">Cliente C</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-route"></i> Tipo de Frete</label>
                    <select id="freightTypeFilter">
                        <option value="">Todos os Tipos</option>
                        <option value="fcl">FCL</option>
                        <option value="lcl">LCL</option>
                        <option value="express">Expresso</option>
                    </select>
                </div>
                <div class="filter-group" style="display: flex; align-items: flex-end;">
                    <button class="btn-export" onclick="aplicarFiltros()" style="width: 100%;">
                        <i class="fas fa-filter"></i> Aplicar Filtros
                    </button>
                </div>
            </div>
            
            <!-- KPIs Principais -->
            <div class="dashboard-grid">
                <div class="kpi-card success">
                    <div class="kpi-header">
                        <span class="kpi-title">Lucro Líquido</span>
                        <i class="fas fa-dollar-sign kpi-icon"></i>
                    </div>
                    <div class="kpi-value">R$ 125.450,00</div>
                    <div class="kpi-subtitle">Período atual</div>
                    <div class="kpi-trend positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+12.5% vs mês anterior</span>
                    </div>
                </div>
                
                <div class="kpi-card info">
                    <div class="kpi-header">
                        <span class="kpi-title">ROI (Retorno sobre Investimento)</span>
                        <i class="fas fa-chart-pie kpi-icon"></i>
                    </div>
                    <div class="kpi-value">18.5%</div>
                    <div class="kpi-subtitle">Taxa de retorno</div>
                    <div class="kpi-trend positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+2.3% vs mês anterior</span>
                    </div>
                </div>
                
                <div class="kpi-card">
                    <div class="kpi-header">
                        <span class="kpi-title">Ticket Médio por Rota</span>
                        <i class="fas fa-receipt kpi-icon"></i>
                    </div>
                    <div class="kpi-value">R$ 3.250,00</div>
                    <div class="kpi-subtitle">Média por rota</div>
                    <div class="kpi-trend positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+5.2% vs mês anterior</span>
                    </div>
                </div>
                
                <div class="kpi-card warning">
                    <div class="kpi-header">
                        <span class="kpi-title">Custo por Quilômetro</span>
                        <i class="fas fa-road kpi-icon"></i>
                    </div>
                    <div class="kpi-value">R$ 2,85</div>
                    <div class="kpi-subtitle">Por km rodado</div>
                    <div class="kpi-trend negative">
                        <i class="fas fa-arrow-down"></i>
                        <span>-3.1% vs mês anterior</span>
                    </div>
                </div>
                
                <div class="kpi-card">
                    <div class="kpi-header">
                        <span class="kpi-title">Margem Operacional</span>
                        <i class="fas fa-percentage kpi-icon"></i>
                    </div>
                    <div class="kpi-value">24.8%</div>
                    <div class="kpi-subtitle">Margem líquida</div>
                    <div class="kpi-trend positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+1.8% vs mês anterior</span>
                    </div>
                </div>
                
                <div class="kpi-card info">
                    <div class="kpi-header">
                        <span class="kpi-title">Taxa de Ocupação</span>
                        <i class="fas fa-box kpi-icon"></i>
                    </div>
                    <div class="kpi-value">78.5%</div>
                    <div class="kpi-subtitle">Rotas com carga</div>
                    <div class="kpi-trend positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+4.2% vs mês anterior</span>
                    </div>
                </div>
            </div>
            
            <!-- Gráfico de Cascata (Waterfall) -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title"><i class="fas fa-chart-bar"></i> Análise de Fluxo Financeiro (Waterfall)</h3>
                    <button class="btn-export" onclick="exportChart('waterfallChart')">
                        <i class="fas fa-download"></i> Exportar
                    </button>
                </div>
                <canvas id="waterfallChart" height="80"></canvas>
            </div>
            
            <!-- Gráficos em Grid -->
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px;">
                <!-- Gráfico de Tendência Anual -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title"><i class="fas fa-chart-line"></i> Tendência Anual</h3>
                    </div>
                    <canvas id="trendChart"></canvas>
                </div>
                
                <!-- Gráfico de Pareto -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title"><i class="fas fa-chart-pie"></i> Análise de Pareto (80/20)</h3>
                    </div>
                    <canvas id="paretoChart"></canvas>
                </div>
            </div>
            
            <!-- Análises Detalhadas -->
            <div class="analysis-section">
                <h2 style="margin-bottom: 20px; color: var(--text-primary);">
                    <i class="fas fa-chart-bar"></i> Análises Detalhadas
                </h2>
                
                <div class="analysis-grid">
                    <!-- Ranking de Veículos -->
                    <div class="analysis-card">
                        <h4><i class="fas fa-truck"></i> Top 5 Veículos Mais Rentáveis</h4>
                        <ul class="ranking-list">
                            <li class="ranking-item">
                                <div>
                                    <span class="ranking-position">1º</span>
                                    <span>ABC-1234 - Caminhão 1</span>
                                </div>
                                <div>
                                    <strong style="color: var(--accent-success);">R$ 45.200,00</strong>
                                    <span class="badge badge-success">+15%</span>
                                </div>
                            </li>
                            <li class="ranking-item">
                                <div>
                                    <span class="ranking-position">2º</span>
                                    <span>DEF-5678 - Caminhão 2</span>
                                </div>
                                <div>
                                    <strong style="color: var(--accent-success);">R$ 38.500,00</strong>
                                    <span class="badge badge-success">+12%</span>
                                </div>
                            </li>
                            <li class="ranking-item">
                                <div>
                                    <span class="ranking-position">3º</span>
                                    <span>GHI-9012 - Caminhão 3</span>
                                </div>
                                <div>
                                    <strong style="color: var(--accent-success);">R$ 32.100,00</strong>
                                    <span class="badge badge-success">+8%</span>
                                </div>
                            </li>
                            <li class="ranking-item">
                                <div>
                                    <span class="ranking-position">4º</span>
                                    <span>JKL-3456 - Caminhão 4</span>
                                </div>
                                <div>
                                    <strong style="color: var(--accent-warning);">R$ 28.900,00</strong>
                                    <span class="badge badge-warning">+3%</span>
                                </div>
                            </li>
                            <li class="ranking-item">
                                <div>
                                    <span class="ranking-position">5º</span>
                                    <span>MNO-7890 - Caminhão 5</span>
                                </div>
                                <div>
                                    <strong style="color: var(--accent-warning);">R$ 25.400,00</strong>
                                    <span class="badge badge-warning">+1%</span>
                                </div>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Ranking de Motoristas -->
                    <div class="analysis-card">
                        <h4><i class="fas fa-user"></i> Top 5 Motoristas Mais Rentáveis</h4>
                        <ul class="ranking-list">
                            <li class="ranking-item">
                                <div>
                                    <span class="ranking-position">1º</span>
                                    <span>João Silva</span>
                                </div>
                                <div>
                                    <strong style="color: var(--accent-success);">R$ 52.300,00</strong>
                                    <span class="badge badge-success">+18%</span>
                                </div>
                            </li>
                            <li class="ranking-item">
                                <div>
                                    <span class="ranking-position">2º</span>
                                    <span>Maria Santos</span>
                                </div>
                                <div>
                                    <strong style="color: var(--accent-success);">R$ 48.100,00</strong>
                                    <span class="badge badge-success">+14%</span>
                                </div>
                            </li>
                            <li class="ranking-item">
                                <div>
                                    <span class="ranking-position">3º</span>
                                    <span>Pedro Oliveira</span>
                                </div>
                                <div>
                                    <strong style="color: var(--accent-success);">R$ 41.800,00</strong>
                                    <span class="badge badge-success">+10%</span>
                                </div>
                            </li>
                            <li class="ranking-item">
                                <div>
                                    <span class="ranking-position">4º</span>
                                    <span>Ana Costa</span>
                                </div>
                                <div>
                                    <strong style="color: var(--accent-warning);">R$ 38.200,00</strong>
                                    <span class="badge badge-warning">+6%</span>
                                </div>
                            </li>
                            <li class="ranking-item">
                                <div>
                                    <span class="ranking-position">5º</span>
                                    <span>Carlos Souza</span>
                                </div>
                                <div>
                                    <strong style="color: var(--accent-warning);">R$ 35.600,00</strong>
                                    <span class="badge badge-warning">+4%</span>
                                </div>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Ranking de Clientes -->
                    <div class="analysis-card">
                        <h4><i class="fas fa-users"></i> Top 5 Clientes Mais Rentáveis</h4>
                        <ul class="ranking-list">
                            <li class="ranking-item">
                                <div>
                                    <span class="ranking-position">1º</span>
                                    <span>Cliente A</span>
                                </div>
                                <div>
                                    <strong style="color: var(--accent-success);">R$ 68.500,00</strong>
                                    <span class="badge badge-success">+22%</span>
                                </div>
                            </li>
                            <li class="ranking-item">
                                <div>
                                    <span class="ranking-position">2º</span>
                                    <span>Cliente B</span>
                                </div>
                                <div>
                                    <strong style="color: var(--accent-success);">R$ 55.200,00</strong>
                                    <span class="badge badge-success">+16%</span>
                                </div>
                            </li>
                            <li class="ranking-item">
                                <div>
                                    <span class="ranking-position">3º</span>
                                    <span>Cliente C</span>
                                </div>
                                <div>
                                    <strong style="color: var(--accent-success);">R$ 42.800,00</strong>
                                    <span class="badge badge-success">+12%</span>
                                </div>
                            </li>
                            <li class="ranking-item">
                                <div>
                                    <span class="ranking-position">4º</span>
                                    <span>Cliente D</span>
                                </div>
                                <div>
                                    <strong style="color: var(--accent-warning);">R$ 38.100,00</strong>
                                    <span class="badge badge-warning">+7%</span>
                                </div>
                            </li>
                            <li class="ranking-item">
                                <div>
                                    <span class="ranking-position">5º</span>
                                    <span>Cliente E</span>
                                </div>
                                <div>
                                    <strong style="color: var(--accent-warning);">R$ 32.400,00</strong>
                                    <span class="badge badge-warning">+5%</span>
                                </div>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Análise por Tipo de Frete -->
                    <div class="analysis-card">
                        <h4><i class="fas fa-route"></i> Lucratividade por Tipo de Frete</h4>
                        <ul class="ranking-list">
                            <li class="ranking-item">
                                <div>
                                    <span><i class="fas fa-box"></i> FCL</span>
                                </div>
                                <div>
                                    <strong style="color: var(--accent-success);">R$ 85.200,00</strong>
                                    <span class="badge badge-success">Margem: 28%</span>
                                </div>
                            </li>
                            <li class="ranking-item">
                                <div>
                                    <span><i class="fas fa-boxes"></i> LCL</span>
                                </div>
                                <div>
                                    <strong style="color: var(--accent-success);">R$ 45.300,00</strong>
                                    <span class="badge badge-success">Margem: 22%</span>
                                </div>
                            </li>
                            <li class="ranking-item">
                                <div>
                                    <span><i class="fas fa-shipping-fast"></i> Expresso</span>
                                </div>
                                <div>
                                    <strong style="color: var(--accent-warning);">R$ 28.100,00</strong>
                                    <span class="badge badge-warning">Margem: 18%</span>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Breakdown de Custos -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title"><i class="fas fa-chart-pie"></i> Distribuição Detalhada de Custos</h3>
                </div>
                <canvas id="costBreakdownChart"></canvas>
            </div>
        </div>
    </div>
    
    <script>
        // Dados mockados para demonstração
        const mockData = {
            lucro: 125450,
            receita: 505000,
            despesas: 379550,
            roi: 18.5,
            ticketMedio: 3250,
            custoKm: 2.85,
            margem: 24.8,
            ocupacao: 78.5
        };
        
        // Gráfico de Cascata (Waterfall)
        const waterfallCtx = document.getElementById('waterfallChart').getContext('2d');
        new Chart(waterfallCtx, {
            type: 'bar',
            data: {
                labels: ['Receita Inicial', 'Despesas Combustível', 'Despesas Manutenção', 'Despesas Fixas', 'Despesas Viagem', 'Lucro Final'],
                datasets: [{
                    label: 'Valor (R$)',
                    data: [505000, -202000, -85000, -62500, -30050, 125450],
                    backgroundColor: [
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(16, 185, 129, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'R$ ' + Math.abs(context.parsed.y).toLocaleString('pt-BR', {minimumFractionDigits: 2});
                            }
                        }
                    }
                },
                scales: {
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
        
        // Gráfico de Tendência Anual
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                datasets: [{
                    label: 'Lucro 2024',
                    data: [95000, 105000, 98000, 112000, 108000, 115000, 118000, 120000, 122000, 123500, 124000, 125450],
                    borderColor: 'rgba(16, 185, 129, 1)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Lucro 2023',
                    data: [85000, 92000, 88000, 95000, 98000, 102000, 105000, 108000, 110000, 112000, 113000, 115000],
                    borderColor: 'rgba(59, 130, 246, 1)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': R$ ' + context.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            }
                        }
                    }
                }
            }
        });
        
        // Gráfico de Pareto
        const paretoCtx = document.getElementById('paretoChart').getContext('2d');
        new Chart(paretoCtx, {
            type: 'bar',
            data: {
                labels: ['Combustível', 'Manutenção', 'Despesas Fixas', 'Despesas Viagem', 'Outros'],
                datasets: [{
                    label: 'Valor (R$)',
                    data: [202000, 85000, 62500, 30050, 0],
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(107, 114, 128, 0.8)'
                    ]
                }, {
                    label: 'Acumulado (%)',
                    type: 'line',
                    data: [53.2, 75.6, 92.1, 100, 100],
                    borderColor: 'rgba(16, 185, 129, 1)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    yAxisID: 'y1',
                    fill: false
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true
                    },
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
        
        // Gráfico de Distribuição de Custos
        const costBreakdownCtx = document.getElementById('costBreakdownChart').getContext('2d');
        new Chart(costBreakdownCtx, {
            type: 'doughnut',
            data: {
                labels: ['Combustível', 'Manutenção', 'Despesas Fixas', 'Despesas Viagem', 'Financiamento', 'Outros'],
                datasets: [{
                    data: [202000, 85000, 62500, 30050, 15000, 0],
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(236, 72, 153, 0.8)',
                        'rgba(107, 114, 128, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return label + ': R$ ' + value.toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
        
        function aplicarFiltros() {
            alert('Filtros aplicados! (Funcionalidade de exemplo)');
        }
        
        function exportChart(chartId) {
            alert('Exportando gráfico: ' + chartId + ' (Funcionalidade de exemplo)');
        }
        
        // Configurar botão de ajuda
        document.getElementById('helpBtn')?.addEventListener('click', function() {
            alert('Modal de ajuda será implementado aqui');
        });
        
        // Configurar botão de exportar
        document.getElementById('exportBtn')?.addEventListener('click', function() {
            alert('Funcionalidade de exportação será implementada aqui');
        });
    </script>
</body>
</html>

