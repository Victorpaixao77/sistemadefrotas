<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configurar sessão antes de iniciá-la
configure_session();

// Iniciar sessão
session_start();

// Verificar se o usuário está autenticado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Verificar se o usuário tem permissão para acessar esta página
if (!hasPermission('ia')) {
    header('Location: ../index.php');
    exit;
}

// Obter dados da empresa
$empresa = getEmpresaData($_SESSION['empresa_id']);
$empresa_nome = $empresa['nome'] ?? 'Empresa';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🤖 IA Avançada - <?php echo htmlspecialchars($empresa_nome); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .ia-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        .alert-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            border-left: 5px solid #dc3545;
        }
        
        .alert-card.alerta {
            border-left-color: #ffc107;
        }
        
        .alert-card.info {
            border-left-color: #17a2b8;
        }
        
        .alert-card.sucesso {
            border-left-color: #28a745;
        }
        
        .ranking-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            border-left: 5px solid #667eea;
        }
        
        .ranking-item.alto-risco {
            border-left-color: #dc3545;
        }
        
        .ranking-item.medio-risco {
            border-left-color: #ffc107;
        }
        
        .ranking-item.baixo-risco {
            border-left-color: #28a745;
        }
        
        .btn-ia {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            border-radius: 25px;
            padding: 12px 25px;
            font-weight: bold;
            transition: transform 0.3s ease;
        }
        
        .btn-ia:hover {
            transform: translateY(-2px);
            color: white;
        }
        
        .btn-ia.btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        
        .btn-ia.btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .loading {
            text-align: center;
            padding: 50px;
        }
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
            color: #667eea;
        }
        
        .progress-ia {
            height: 25px;
            border-radius: 15px;
            background: #e9ecef;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 15px;
            transition: width 0.3s ease;
        }
        
        .feature-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            height: 100%;
        }
        
        .feature-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="container-fluid bg-primary text-white py-3 mb-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2><i class="fas fa-robot me-2"></i>IA Avançada - Custos e Fraudes</h2>
                    <p class="mb-0">Análise inteligente de lucratividade e detecção de fraudes</p>
                </div>
                <div class="col-md-6 text-end">
                    <button class="btn btn-light me-2" onclick="executarAnaliseCompleta()">
                        <i class="fas fa-play me-2"></i>Executar Análise
                    </button>
                    <a href="ia_painel.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i>Voltar ao IA
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Estatísticas Gerais -->
        <div class="row mb-4" id="estatisticas-container">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number" id="total-rotas">-</div>
                    <div class="stats-label">Total de Rotas</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number" id="total-abastecimentos">-</div>
                    <div class="stats-label">Abastecimentos</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number" id="total-motoristas">-</div>
                    <div class="stats-label">Motoristas</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number" id="alertas-recentes">-</div>
                    <div class="stats-label">Alertas Recentes</div>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div class="loading" id="loading" style="display: none;">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <p class="mt-3">Executando análise da IA...</p>
        </div>

        <!-- Funcionalidades da IA -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h5>Previsão de Lucro</h5>
                    <p>Análise inteligente de custos e lucratividade por rota</p>
                    <button class="btn btn-ia" onclick="analisarCustos()">
                        <i class="fas fa-calculator me-2"></i>Analisar Custos
                    </button>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h5>Detecção de Fraudes</h5>
                    <p>Identificação automática de abastecimentos suspeitos</p>
                    <button class="btn btn-ia btn-danger" onclick="detectarFraudes()">
                        <i class="fas fa-shield-alt me-2"></i>Detectar Fraudes
                    </button>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h5>Ranking de Risco</h5>
                    <p>Classificação de motoristas por nível de risco</p>
                    <button class="btn btn-ia btn-success" onclick="verRankingRisco()">
                        <i class="fas fa-list-ol me-2"></i>Ver Ranking
                    </button>
                </div>
            </div>
        </div>

        <!-- Alertas Recentes -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="ia-card">
                    <h4><i class="fas fa-bell me-2"></i>Alertas Recentes da IA</h4>
                    <p class="mb-0">Notificações e alertas gerados pela inteligência artificial</p>
                </div>
                
                <div id="alertas-container">
                    <!-- Alertas serão carregados aqui -->
                </div>
            </div>
        </div>

        <!-- Ranking de Risco -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="ia-card">
                    <h4><i class="fas fa-exclamation-triangle me-2"></i>Ranking de Risco dos Motoristas</h4>
                    <p class="mb-0">Classificação baseada em padrões de comportamento e custos</p>
                </div>
                
                <div id="ranking-container">
                    <!-- Ranking será carregado aqui -->
                </div>
            </div>
        </div>

        <!-- Análise de Custos -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="ia-card">
                    <h4><i class="fas fa-chart-pie me-2"></i>Análise de Custos</h4>
                    <p class="mb-0">Distribuição de custos operacionais</p>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <canvas id="custosChart" height="300"></canvas>
                    </div>
                    <div class="col-md-6">
                        <div id="custos-detalhados">
                            <!-- Detalhes dos custos serão carregados aqui -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Detalhes -->
    <div class="modal fade" id="modalDetalhes" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-title">
                        <i class="fas fa-info-circle me-2"></i>Detalhes da Análise
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modal-body">
                    <!-- Conteúdo será carregado aqui -->
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let custosChart = null;

        // Carregar dashboard ao inicializar
        document.addEventListener('DOMContentLoaded', function() {
            carregarDashboard();
        });

        // Função para carregar dashboard
        function carregarDashboard() {
            fetch('../api/ia_custos_fraudes.php?action=dashboard_ia')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        exibirEstatisticas(data.data.estatisticas);
                        exibirAlertas(data.data.alertas_recentes);
                    } else {
                        console.error('Erro ao carregar dashboard:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                });
        }

        // Função para exibir estatísticas
        function exibirEstatisticas(stats) {
            document.getElementById('total-rotas').textContent = stats.total_rotas || 0;
            document.getElementById('total-abastecimentos').textContent = stats.total_abastecimentos || 0;
            document.getElementById('total-motoristas').textContent = stats.total_motoristas || 0;
            document.getElementById('alertas-recentes').textContent = stats.alertas_recentes || 0;
        }

        // Função para exibir alertas
        function exibirAlertas(alertas) {
            const container = document.getElementById('alertas-container');
            
            if (alertas.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5>Nenhum alerta recente</h5>
                        <p class="text-muted">A IA não detectou problemas nos últimos 7 dias.</p>
                    </div>
                `;
                return;
            }

            let html = '';
            alertas.forEach(alerta => {
                const tipoClass = alerta.tipo === 'alerta' ? 'alerta' : 
                                 alerta.tipo === 'info' ? 'info' : 'sucesso';
                
                html += `
                    <div class="alert-card ${tipoClass}">
                        <div class="d-flex align-items-start">
                            <div class="me-3">
                                <i class="fas fa-${alerta.tipo === 'alerta' ? 'exclamation-triangle' : 
                                                    alerta.tipo === 'info' ? 'info-circle' : 'check-circle'} fa-2x"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${alerta.titulo}</h6>
                                <p class="mb-2">${alerta.mensagem}</p>
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    ${new Date(alerta.data_criacao).toLocaleString('pt-BR')}
                                </small>
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Função para executar análise completa
        function executarAnaliseCompleta() {
            const loading = document.getElementById('loading');
            loading.style.display = 'block';

            fetch('../api/ia_custos_fraudes.php?action=executar_analise')
                .then(response => response.json())
                .then(data => {
                    loading.style.display = 'none';
                    
                    if (data.success) {
                        alert('Análise executada com sucesso!');
                        carregarDashboard();
                    } else {
                        alert('Erro ao executar análise: ' + (data.error || 'Erro desconhecido'));
                    }
                })
                .catch(error => {
                    loading.style.display = 'none';
                    console.error('Erro:', error);
                    alert('Erro ao executar análise');
                });
        }

        // Função para analisar custos
        function analisarCustos() {
            fetch('../api/ia_custos_fraudes.php?action=analise_custos')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        exibirModalDetalhes('Análise de Custos', data.data);
                        atualizarGraficoCustos(data.data.custos);
                    } else {
                        alert('Erro ao analisar custos: ' + (data.error || 'Erro desconhecido'));
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao analisar custos');
                });
        }

        // Função para detectar fraudes
        function detectarFraudes() {
            fetch('../api/ia_custos_fraudes.php?action=detectar_fraude&abastecimento_id=1')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        exibirModalDetalhes('Detecção de Fraudes', data.data);
                    } else {
                        alert('Erro ao detectar fraudes: ' + (data.error || 'Erro desconhecido'));
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao detectar fraudes');
                });
        }

        // Função para ver ranking de risco
        function verRankingRisco() {
            fetch('../api/ia_custos_fraudes.php?action=ranking_risco')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        exibirRankingRisco(data.data);
                    } else {
                        alert('Erro ao carregar ranking: ' + (data.error || 'Erro desconhecido'));
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao carregar ranking');
                });
        }

        // Função para exibir ranking de risco
        function exibirRankingRisco(ranking) {
            const container = document.getElementById('ranking-container');
            
            if (ranking.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                        <h5>Nenhum dado encontrado</h5>
                        <p class="text-muted">Não há dados suficientes para gerar o ranking.</p>
                    </div>
                `;
                return;
            }

            let html = '';
            ranking.slice(0, 10).forEach(motorista => {
                const riscoClass = motorista.categoria_risco === 'Alto Risco' ? 'alto-risco' : 
                                  motorista.categoria_risco === 'Médio Risco' ? 'medio-risco' : 'baixo-risco';
                
                html += `
                    <div class="ranking-item ${riscoClass}">
                        <div class="row align-items-center">
                            <div class="col-md-1">
                                <div class="text-center">
                                    <strong>#${motorista.posicao}</strong>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <h6 class="mb-1">${motorista.motorista_nome}</h6>
                                <small class="text-muted">${motorista.total_rotas} rotas</small>
                            </div>
                            <div class="col-md-2">
                                <div class="progress-ia mb-2">
                                    <div class="progress-fill" style="width: ${motorista.score_risco}%"></div>
                                </div>
                                <small>Score: ${motorista.score_risco}/100</small>
                            </div>
                            <div class="col-md-2">
                                <span class="badge bg-${riscoClass === 'alto-risco' ? 'danger' : 
                                                        riscoClass === 'medio-risco' ? 'warning' : 'success'}">
                                    ${motorista.categoria_risco}
                                </span>
                            </div>
                            <div class="col-md-2">
                                <small>Atraso: ${motorista.taxa_atraso}%</small><br>
                                <small>Multas: ${motorista.total_multas}</small>
                            </div>
                            <div class="col-md-2">
                                <small>Custo/km: R$ ${motorista.custo_medio_km}</small>
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Função para atualizar gráfico de custos
        function atualizarGraficoCustos(custos) {
            const ctx = document.getElementById('custosChart').getContext('2d');
            
            if (custosChart) {
                custosChart.destroy();
            }

            const labels = custos.map(c => c.categoria);
            const valores = custos.map(c => c.total);

            custosChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: valores,
                        backgroundColor: [
                            '#667eea',
                            '#764ba2',
                            '#f093fb',
                            '#f5576c',
                            '#4facfe',
                            '#00f2fe'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Função para exibir modal de detalhes
        function exibirModalDetalhes(titulo, dados) {
            document.getElementById('modal-title').innerHTML = `<i class="fas fa-info-circle me-2"></i>${titulo}`;
            
            let html = '';
            if (dados.custos) {
                html = `
                    <div class="row">
                        <div class="col-md-12">
                            <h6>Distribuição de Custos</h6>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Categoria</th>
                                        <th>Valor</th>
                                        <th>Quantidade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${dados.custos.map(custo => `
                                        <tr>
                                            <td>${custo.categoria}</td>
                                            <td>R$ ${parseFloat(custo.total).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                                            <td>${custo.quantidade}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                            <div class="alert alert-info">
                                <strong>Total de Custos:</strong> R$ ${parseFloat(dados.total_custos).toLocaleString('pt-BR', {minimumFractionDigits: 2})}
                            </div>
                        </div>
                    </div>
                `;
            } else if (dados.status) {
                html = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Detalhes da Detecção</h6>
                            <p><strong>Motorista:</strong> ${dados.motorista}</p>
                            <p><strong>Veículo:</strong> ${dados.veiculo}</p>
                            <p><strong>Score de Risco:</strong> ${dados.score_risco}/100</p>
                            <p><strong>Status:</strong> <span class="badge bg-${dados.status === 'alto_risco' ? 'danger' : 
                                                                                   dados.status === 'medio_risco' ? 'warning' : 'success'}">${dados.status}</span></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Fraudes Detectadas</h6>
                            ${dados.fraudes_detectadas.length > 0 ? `
                                <ul class="list-group">
                                    ${dados.fraudes_detectadas.map(fraude => `
                                        <li class="list-group-item">
                                            <strong>${fraude.descricao}</strong><br>
                                            <small class="text-muted">Severidade: ${fraude.severidade}</small>
                                        </li>
                                    `).join('')}
                                </ul>
                            ` : '<p class="text-success">Nenhuma fraude detectada</p>'}
                        </div>
                    </div>
                `;
            }

            document.getElementById('modal-body').innerHTML = html;
            
            const modal = new bootstrap.Modal(document.getElementById('modalDetalhes'));
            modal.show();
        }
    </script>
</body>
</html>
