<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Verificar se está logado
verificarLogin();

// Obter dados do usuário
$usuario = obterUsuarioLogado();
$empresa_id = obterEmpresaId();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Seguro - Relatórios</title>
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
        
        .header {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .report-card {
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .report-card:hover {
            border-color: #667eea;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .report-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        
        .btn-gradient:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4191 100%);
            color: white;
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
                <a class="nav-link active" href="relatorios.php">
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
                        <i class="fas fa-file-alt me-2"></i>
                        Relatórios Gerenciais
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

        <!-- Tipos de Relatórios -->
        <div class="row mb-4">
            <div class="col-md-4 mb-4">
                <div class="card report-card text-center" onclick="abrirRelatorioClientes()">
                    <div class="card-body">
                        <i class="fas fa-users report-icon text-primary"></i>
                        <h4>Relatório de Clientes</h4>
                        <p class="text-muted">Lista completa de clientes com filtros personalizados</p>
                        <button class="btn btn-primary">
                            <i class="fas fa-file-pdf me-2"></i>
                            Gerar Relatório
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card report-card text-center" onclick="abrirRelatorioFinanceiro()">
                    <div class="card-body">
                        <i class="fas fa-dollar-sign report-icon text-success"></i>
                        <h4>Relatório Financeiro</h4>
                        <p class="text-muted">Comissões, pagamentos e análise financeira</p>
                        <button class="btn btn-success">
                            <i class="fas fa-file-excel me-2"></i>
                            Gerar Relatório
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card report-card text-center" onclick="abrirRelatorioAtendimentos()">
                    <div class="card-body">
                        <i class="fas fa-headset report-icon text-warning"></i>
                        <h4>Relatório de Atendimentos</h4>
                        <p class="text-muted">Histórico de chamados e performance</p>
                        <button class="btn btn-warning">
                            <i class="fas fa-file-pdf me-2"></i>
                            Gerar Relatório
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card report-card text-center" onclick="abrirRelatorioEquipamentos()">
                    <div class="card-body">
                        <i class="fas fa-cogs report-icon text-info"></i>
                        <h4>Relatório de Equipamentos</h4>
                        <p class="text-muted">Inventário e status dos equipamentos</p>
                        <button class="btn btn-info">
                            <i class="fas fa-file-excel me-2"></i>
                            Gerar Relatório
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card report-card text-center" onclick="abrirRelatorioComissoes()">
                    <div class="card-body">
                        <i class="fas fa-chart-line report-icon text-danger"></i>
                        <h4>Relatório de Comissões</h4>
                        <p class="text-muted">Análise detalhada de comissões mensais</p>
                        <button class="btn btn-danger">
                            <i class="fas fa-file-pdf me-2"></i>
                            Gerar Relatório
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card report-card text-center" onclick="abrirRelatorioPersonalizado()">
                    <div class="card-body">
                        <i class="fas fa-magic report-icon text-secondary"></i>
                        <h4>Relatório Personalizado</h4>
                        <p class="text-muted">Crie seu próprio relatório customizado</p>
                        <button class="btn btn-secondary">
                            <i class="fas fa-tools me-2"></i>
                            Customizar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Histórico de Relatórios Gerados -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Relatórios Gerados Recentemente
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Tipo</th>
                                <th>Período</th>
                                <th>Usuário</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="tabelaHistorico">
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <i class="fas fa-inbox me-2"></i>
                                    Nenhum relatório gerado ainda
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Filtros de Relatório -->
    <div class="modal fade" id="modalFiltrosRelatorio" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-filter me-2"></i>
                        Filtros do Relatório
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data Início:</label>
                            <input type="date" id="dataInicioRelatorio" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data Fim:</label>
                            <input type="date" id="dataFimRelatorio" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Formato de Exportação:</label>
                            <select id="formatoExportacao" class="form-select">
                                <option value="pdf">PDF</option>
                                <option value="excel">Excel (XLSX)</option>
                                <option value="csv">CSV</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status (para filtros aplicáveis):</label>
                            <select id="statusFiltro" class="form-select">
                                <option value="todos">Todos</option>
                                <option value="ativo">Ativos</option>
                                <option value="inativo">Inativos</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="incluirGraficos" checked>
                                <label class="form-check-label" for="incluirGraficos">
                                    Incluir gráficos no relatório
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="incluirDetalhes" checked>
                                <label class="form-check-label" for="incluirDetalhes">
                                    Incluir detalhamento completo
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-gradient" onclick="gerarRelatorio()">
                        <i class="fas fa-download me-2"></i>
                        Gerar e Baixar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/menu-responsivo.js"></script>
    <script>
        let tipoRelatorioAtual = '';
        
        function abrirRelatorioClientes() {
            tipoRelatorioAtual = 'clientes';
            abrirModalFiltros();
        }
        
        function abrirRelatorioFinanceiro() {
            tipoRelatorioAtual = 'financeiro';
            abrirModalFiltros();
        }
        
        function abrirRelatorioAtendimentos() {
            tipoRelatorioAtual = 'atendimentos';
            abrirModalFiltros();
        }
        
        function abrirRelatorioEquipamentos() {
            tipoRelatorioAtual = 'equipamentos';
            abrirModalFiltros();
        }
        
        function abrirRelatorioComissoes() {
            tipoRelatorioAtual = 'comissoes';
            abrirModalFiltros();
        }
        
        function abrirRelatorioPersonalizado() {
            tipoRelatorioAtual = 'personalizado';
            abrirModalFiltros();
        }
        
        function abrirModalFiltros() {
            // Definir datas padrão
            const hoje = new Date();
            const primeiroDiaMes = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
            
            document.getElementById('dataInicioRelatorio').value = primeiroDiaMes.toISOString().split('T')[0];
            document.getElementById('dataFimRelatorio').value = hoje.toISOString().split('T')[0];
            
            const modal = new bootstrap.Modal(document.getElementById('modalFiltrosRelatorio'));
            modal.show();
        }
        
        async function gerarRelatorio() {
            const dataInicio = document.getElementById('dataInicioRelatorio').value;
            const dataFim = document.getElementById('dataFimRelatorio').value;
            const formato = document.getElementById('formatoExportacao').value;
            const status = document.getElementById('statusFiltro').value;
            const incluirGraficos = document.getElementById('incluirGraficos').checked;
            const incluirDetalhes = document.getElementById('incluirDetalhes').checked;
            
            if (!dataInicio || !dataFim) {
                alert('Por favor, selecione o período do relatório!');
                return;
            }
            
            // Construir URL da API
            const params = new URLSearchParams({
                tipo: tipoRelatorioAtual,
                data_inicio: dataInicio,
                data_fim: dataFim,
                formato: formato,
                status: status,
                graficos: incluirGraficos ? '1' : '0',
                detalhes: incluirDetalhes ? '1' : '0'
            });
            
            try {
                // Mostrar loading
                const btnGerar = event.target;
                btnGerar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Gerando...';
                btnGerar.disabled = true;
                
                // Fazer download do relatório
                window.location.href = `api/gerar_relatorio.php?${params.toString()}`;
                
                // Fechar modal após 2 segundos
                setTimeout(() => {
                    bootstrap.Modal.getInstance(document.getElementById('modalFiltrosRelatorio')).hide();
                    btnGerar.innerHTML = '<i class="fas fa-download me-2"></i>Gerar e Baixar';
                    btnGerar.disabled = false;
                }, 2000);
                
            } catch (error) {
                console.error('Erro ao gerar relatório:', error);
                alert('Erro ao gerar relatório. Tente novamente.');
            }
        }
        
        // Carregar histórico ao abrir a página
        document.addEventListener('DOMContentLoaded', function() {
            carregarHistoricoRelatorios();
        });
        
        async function carregarHistoricoRelatorios() {
            try {
                const response = await fetch('api/historico_relatorios.php');
                const data = await response.json();
                
                console.log('Histórico carregado:', data);
                
                if (data.sucesso && data.historico && data.historico.length > 0) {
                    renderizarHistorico(data.historico);
                } else {
                    // Histórico vazio
                    const tbody = document.getElementById('historicoRelatorios');
                    if (tbody) {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Nenhum relatório gerado ainda</td></tr>';
                    }
                }
            } catch (error) {
                console.error('Erro ao carregar histórico:', error);
                const tbody = document.getElementById('historicoRelatorios');
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Erro ao carregar histórico</td></tr>';
                }
            }
        }
        
        function renderizarHistorico(relatorios) {
            let html = '';
            
            relatorios.forEach(rel => {
                const badgeClass = rel.status === 'concluido' ? 'bg-success' : 'bg-warning';
                
                html += `
                    <tr>
                        <td>${rel.data}</td>
                        <td><span class="badge bg-primary">${rel.tipo}</span></td>
                        <td>${rel.periodo}</td>
                        <td>${rel.usuario}</td>
                        <td><span class="badge ${badgeClass}">${rel.status}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="baixarRelatorio(${rel.id})">
                                <i class="fas fa-download"></i> Baixar
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            document.getElementById('tabelaHistorico').innerHTML = html;
        }
        
        function baixarRelatorio(id) {
            window.location.href = `api/download_relatorio.php?id=${id}`;
        }
    </script>
    <script src="js/temas.js"></script>
</body>
</html>

