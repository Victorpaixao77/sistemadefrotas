<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

configure_session();
session_start();
require_authentication();

$page_title = "Lucratividade 2.0 - Inteligência de Negócio (Demo)";
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
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@2.1.0"></script>
    
    <style>
        :root {
            --card-bg: #ffffff;
            --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --text-muted: #6b7280;
            --text-main: #111827;
            --accent-sim: #8b5cf6; /* Purple for simulation */
            --accent-forecast: #3b82f6; /* Blue for forecast */
        }

        body {
            background-color: #f3f4f6;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .dashboard-content {
            padding: 2rem;
            max-width: 1600px;
            margin: 0 auto;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .dashboard-header h1 {
            font-size: 1.875rem;
            font-weight: 800;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .badge-beta {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Grid Layouts */
        .grid-cols-12 { display: grid; grid-template-columns: repeat(12, 1fr); gap: 1.5rem; }
        .col-span-12 { grid-column: span 12; }
        .col-span-8 { grid-column: span 8; }
        .col-span-6 { grid-column: span 6; }
        .col-span-4 { grid-column: span 4; }
        .col-span-3 { grid-column: span 3; }

        @media (max-width: 1280px) {
            .col-span-3 { grid-column: span 6; }
        }
        @media (max-width: 1024px) {
            .col-span-8, .col-span-6, .col-span-4 { grid-column: span 12; }
        }

        /* Cards */
        .card {
            background: var(--card-bg);
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Simulation Section */
        .sim-panel {
            background: linear-gradient(to bottom right, #ffffff, #f5f3ff);
            border: 1px solid #e9d5ff;
        }

        .slider-group {
            margin-bottom: 1.5rem;
        }

        .slider-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .slider-label {
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-main);
        }

        .slider-value {
            font-weight: 700;
            color: var(--accent-sim);
        }

        input[type=range] {
            width: 100%;
            height: 6px;
            background: #e5e7eb;
            border-radius: 3px;
            outline: none;
            -webkit-appearance: none;
        }

        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            background: var(--accent-sim);
            border-radius: 50%;
            cursor: pointer;
            transition: transform 0.1s;
        }

        input[type=range]::-webkit-slider-thumb:hover {
            transform: scale(1.2);
        }

        .sim-result {
            text-align: center;
            padding: 1rem;
            background: rgba(139, 92, 246, 0.1);
            border-radius: 0.75rem;
            margin-top: auto;
        }

        .sim-result-label { font-size: 0.875rem; color: var(--text-muted); }
        .sim-result-value { font-size: 2rem; font-weight: 800; color: var(--accent-sim); }
        .sim-result-diff { font-size: 0.875rem; font-weight: 600; }

        /* Forecast Section */
        .forecast-insight {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            color: #1e40af;
        }

        /* Offenders Table */
        .table-container {
            overflow-x: auto;
        }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 0.75rem 1rem; font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); border-bottom: 1px solid #e5e7eb; }
        td { padding: 1rem; border-bottom: 1px solid #f3f4f6; font-size: 0.875rem; color: var(--text-main); }
        tr:last-child td { border-bottom: none; }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-danger { background: #fee2e2; color: #ef4444; }
        .status-warning { background: #fef3c7; color: #d97706; }

        /* KPI Mini Cards */
        .kpi-mini {
            padding: 1rem;
            border-radius: 0.75rem;
            background: #fff;
            border: 1px solid #e5e7eb;
        }
        .kpi-mini-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; }
        .kpi-mini-value { font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin: 0.25rem 0; }
        .kpi-mini-trend { font-size: 0.75rem; font-weight: 600; }
        .trend-up { color: #10b981; }
        .trend-down { color: #ef4444; }

    </style>
</head>
<body>
    <?php include '../includes/sidebar_pages.php'; ?>
    <div class="main-content">
        <?php include '../includes/header.php'; ?>
        
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h1>
                    <i class="fas fa-brain" style="color: var(--accent-sim);"></i> 
                    Inteligência de Negócio
                    <span class="badge-beta">BETA</span>
                </h1>
                <div style="display: flex; gap: 1rem;">
                    <button class="btn btn-secondary" onclick="resetSimulation()">
                        <i class="fas fa-undo"></i> Resetar Simulação
                    </button>
                    <button class="btn btn-primary">
                        <i class="fas fa-file-export"></i> Relatório Executivo
                    </button>
                </div>
            </div>

            <!-- Section 1: Simulation & Forecast -->
            <div class="grid-cols-12" style="margin-bottom: 2rem;">
                <!-- Simulator -->
                <div class="col-span-4 card sim-panel">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-sliders-h"></i> Simulador de Cenários</h3>
                        <i class="fas fa-question-circle text-muted" title="Ajuste os parâmetros para ver o impacto no lucro projetado."></i>
                    </div>
                    
                    <div class="slider-group">
                        <div class="slider-header">
                            <span class="slider-label">Preço do Combustível</span>
                            <span class="slider-value" id="val-fuel">0%</span>
                        </div>
                        <input type="range" id="slider-fuel" min="-20" max="20" value="0" oninput="updateSimulation()">
                        <small class="text-muted">Impacto direto no custo variável</small>
                    </div>

                    <div class="slider-group">
                        <div class="slider-header">
                            <span class="slider-label">Valor do Frete Médio</span>
                            <span class="slider-value" id="val-freight">0%</span>
                        </div>
                        <input type="range" id="slider-freight" min="-20" max="20" value="0" oninput="updateSimulation()">
                        <small class="text-muted">Impacto direto na receita</small>
                    </div>

                    <div class="slider-group">
                        <div class="slider-header">
                            <span class="slider-label">Custo de Manutenção</span>
                            <span class="slider-value" id="val-maint">0%</span>
                        </div>
                        <input type="range" id="slider-maint" min="-20" max="20" value="0" oninput="updateSimulation()">
                        <small class="text-muted">Impacto em custos de frota</small>
                    </div>

                    <div class="sim-result">
                        <div class="sim-result-label">Lucro Líquido Projetado (Mensal)</div>
                        <div class="sim-result-value" id="sim-profit">R$ 45.200</div>
                        <div class="sim-result-diff trend-up" id="sim-diff">+0.0% vs Atual</div>
                    </div>
                </div>

                <!-- AI Forecast -->
                <div class="col-span-8 card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-chart-line"></i> Projeção Inteligente (AI Forecast)</h3>
                        <span class="badge-beta" style="background: #eff6ff; color: #3b82f6;">Confiança: Alta</span>
                    </div>
                    
                    <div class="forecast-insight" id="forecast-text">
                        <i class="fas fa-lightbulb"></i> 
                        Baseado no histórico de 12 meses, sua tendência é de <strong>crescimento de 5%</strong>. 
                        Atenção para sazonalidade em Dezembro.
                    </div>

                    <div style="flex: 1; min-height: 300px;">
                        <canvas id="forecastChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Section 2: Financial Health Overview -->
            <div class="grid-cols-12" style="margin-bottom: 2rem;">
                <div class="col-span-3 kpi-mini">
                    <div class="kpi-mini-label">Receita Total</div>
                    <div class="kpi-mini-value">R$ 152.400</div>
                    <div class="kpi-mini-trend trend-up"><i class="fas fa-arrow-up"></i> 12% vs mês ant.</div>
                </div>
                <div class="col-span-3 kpi-mini">
                    <div class="kpi-mini-label">Custo Total</div>
                    <div class="kpi-mini-value">R$ 107.200</div>
                    <div class="kpi-mini-trend trend-down"><i class="fas fa-arrow-up"></i> 8% vs mês ant.</div>
                </div>
                <div class="col-span-3 kpi-mini">
                    <div class="kpi-mini-label">Margem Líquida</div>
                    <div class="kpi-mini-value">29.6%</div>
                    <div class="kpi-mini-trend trend-up"><i class="fas fa-arrow-up"></i> 1.2% vs mês ant.</div>
                </div>
                <div class="col-span-3 kpi-mini">
                    <div class="kpi-mini-label">ROI</div>
                    <div class="kpi-mini-value">15.2%</div>
                    <div class="kpi-mini-trend trend-up"><i class="fas fa-arrow-up"></i> 0.5% vs mês ant.</div>
                </div>
            </div>

            <!-- Section 3: Offenders & Waterfall -->
            <div class="grid-cols-12">
                <!-- Offenders Table -->
                <div class="col-span-8 card">
                    <div class="card-header">
                        <h3 class="card-title" style="color: #ef4444;"><i class="fas fa-exclamation-triangle"></i> Ofensores de Lucratividade</h3>
                        <button class="btn btn-sm btn-outline">Ver Todos</button>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Rota / Veículo</th>
                                    <th>Motorista</th>
                                    <th>Receita</th>
                                    <th>Custo</th>
                                    <th>Prejuízo</th>
                                    <th>Diagnóstico IA</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>SP-RJ (Rota 104)</strong><br><small class="text-muted">ABC-1234</small></td>
                                    <td>João Silva</td>
                                    <td>R$ 4.200</td>
                                    <td>R$ 4.850</td>
                                    <td class="text-danger font-bold">- R$ 650</td>
                                    <td><span class="status-badge status-danger">Consumo Excessivo (+15%)</span></td>
                                </tr>
                                <tr>
                                    <td><strong>MG-SP (Rota 201)</strong><br><small class="text-muted">DEF-5678</small></td>
                                    <td>Pedro Santos</td>
                                    <td>R$ 3.800</td>
                                    <td>R$ 4.100</td>
                                    <td class="text-danger font-bold">- R$ 300</td>
                                    <td><span class="status-badge status-warning">Tempo de Espera Alto</span></td>
                                </tr>
                                <tr>
                                    <td><strong>PR-SC (Rota 305)</strong><br><small class="text-muted">GHI-9012</small></td>
                                    <td>Maria Costa</td>
                                    <td>R$ 5.100</td>
                                    <td>R$ 5.250</td>
                                    <td class="text-danger font-bold">- R$ 150</td>
                                    <td><span class="status-badge status-warning">Manutenção Não Prevista</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Waterfall Chart -->
                <div class="col-span-4 card">
                    <div class="card-header">
                        <h3 class="card-title">Composição do Custo</h3>
                    </div>
                    <div style="flex: 1; min-height: 250px;">
                        <canvas id="waterfallChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Base Data (Mock)
        const baseData = {
            revenue: 152400,
            costs: {
                fuel: 45000,     // Variable
                maint: 15000,    // Variable
                fixed: 30000,    // Fixed
                personnel: 17200 // Fixed
            },
            profit: 45200
        };

        let forecastChart = null;
        let waterfallChart = null;

        document.addEventListener('DOMContentLoaded', () => {
            Chart.register(ChartDataLabels);
            initCharts();
            updateSimulation(); // Initial calc
        });

        // --- Simulation Logic ---
        function updateSimulation() {
            // Get slider values
            const fuelPct = parseInt(document.getElementById('slider-fuel').value);
            const freightPct = parseInt(document.getElementById('slider-freight').value);
            const maintPct = parseInt(document.getElementById('slider-maint').value);

            // Update UI labels
            document.getElementById('val-fuel').textContent = (fuelPct > 0 ? '+' : '') + fuelPct + '%';
            document.getElementById('val-freight').textContent = (freightPct > 0 ? '+' : '') + freightPct + '%';
            document.getElementById('val-maint').textContent = (maintPct > 0 ? '+' : '') + maintPct + '%';

            // Calculate new values
            const newRevenue = baseData.revenue * (1 + (freightPct / 100));
            const newFuelCost = baseData.costs.fuel * (1 + (fuelPct / 100));
            const newMaintCost = baseData.costs.maint * (1 + (maintPct / 100));
            
            const totalCost = newFuelCost + newMaintCost + baseData.costs.fixed + baseData.costs.personnel;
            const newProfit = newRevenue - totalCost;

            // Update Result Card
            const profitEl = document.getElementById('sim-profit');
            const diffEl = document.getElementById('sim-diff');

            profitEl.textContent = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(newProfit);
            
            const diffPct = ((newProfit - baseData.profit) / baseData.profit) * 100;
            diffEl.textContent = (diffPct > 0 ? '+' : '') + diffPct.toFixed(1) + '% vs Atual';
            diffEl.className = 'sim-result-diff ' + (diffPct >= 0 ? 'trend-up' : 'trend-down');

            // Update Forecast Chart with simulated data
            updateForecastChart(newProfit);
        }

        function resetSimulation() {
            document.getElementById('slider-fuel').value = 0;
            document.getElementById('slider-freight').value = 0;
            document.getElementById('slider-maint').value = 0;
            updateSimulation();
        }

        // --- Charts ---
        function initCharts() {
            // Forecast Chart
            const ctxForecast = document.getElementById('forecastChart').getContext('2d');
            forecastChart = new Chart(ctxForecast, {
                type: 'line',
                data: {
                    labels: ['Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez (Atual)', 'Jan (Proj)', 'Fev (Proj)', 'Mar (Proj)'],
                    datasets: [{
                        label: 'Lucro Real',
                        data: [38000, 41000, 39500, 42000, 44000, 45200, null, null, null],
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Projeção (Simulada)',
                        data: [null, null, null, null, null, 45200, 46500, 47800, 49000], // Initial dummy data
                        borderColor: '#8b5cf6',
                        borderDash: [5, 5],
                        tension: 0.4,
                        pointStyle: 'circle',
                        pointRadius: 5,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#8b5cf6'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        annotation: {
                            annotations: {
                                line1: {
                                    type: 'line',
                                    xMin: 5,
                                    xMax: 5,
                                    borderColor: '#9ca3af',
                                    borderWidth: 2,
                                    borderDash: [2, 2],
                                    label: {
                                        display: true,
                                        content: 'Hoje',
                                        position: 'start'
                                    }
                                }
                            }
                        }
                    }
                }
            });

            // Waterfall Chart
            const ctxWaterfall = document.getElementById('waterfallChart').getContext('2d');
            waterfallChart = new Chart(ctxWaterfall, {
                type: 'doughnut',
                data: {
                    labels: ['Combustível', 'Pessoal', 'Manutenção', 'Fixos', 'Lucro'],
                    datasets: [{
                        data: [45000, 17200, 15000, 30000, 45200],
                        backgroundColor: ['#ef4444', '#f59e0b', '#f97316', '#6b7280', '#10b981'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right' },
                        datalabels: {
                            color: '#fff',
                            formatter: (value, ctx) => {
                                let sum = 0;
                                let dataArr = ctx.chart.data.datasets[0].data;
                                dataArr.map(data => { sum += data; });
                                let percentage = (value*100 / sum).toFixed(0)+"%";
                                return percentage;
                            }
                        }
                    }
                }
            });
        }

        function updateForecastChart(simulatedProfit) {
            if (!forecastChart) return;

            // Simple projection logic: apply the simulated profit as the baseline for future months with slight growth
            const p1 = simulatedProfit * 1.02; // +2%
            const p2 = simulatedProfit * 1.04; // +4%
            const p3 = simulatedProfit * 1.06; // +6%

            // Update the "Projection" dataset (index 1)
            // Index 5 is "Dez (Atual)", so 6, 7, 8 are Jan, Feb, Mar
            forecastChart.data.datasets[1].data = [null, null, null, null, null, baseData.profit, p1, p2, p3];
            forecastChart.update();
        }
    </script>
</body>
</html>
