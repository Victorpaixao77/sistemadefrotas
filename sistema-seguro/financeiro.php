<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Verificar se está logado
verificarLogin();

// Obter dados do usuário
$usuario = obterUsuarioLogado();
$empresa_id = obterEmpresaId();

// Obter conexão com banco
$pdo = getDB();

// Buscar estatísticas reais do banco
try {
    // Buscar porcentagem fixa e dia de fechamento da empresa
    $stmt = $pdo->prepare("
        SELECT porcentagem_fixa, dia_fechamento 
        FROM seguro_empresa_clientes 
        WHERE id = ?
    ");
    $stmt->execute([$empresa_id]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    $percentual_empresa = floatval($empresa['porcentagem_fixa'] ?? 0);
    $dia_fechamento = intval($empresa['dia_fechamento'] ?? 25);
    
    // Total de receitas (documentos pagos) com comissão
    // IMPORTANTE: Calcula comissão APENAS de documentos com cliente vinculado (não em quarentena)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_receitas,
            COALESCE(SUM(sf.valor_pago), 0) as valor_receitas,
            COALESCE(SUM(
                sf.valor_pago * ((? + COALESCE(sc.porcentagem_recorrencia, 0)) / 100)
            ), 0) as comissao_geral
        FROM seguro_financeiro sf
        INNER JOIN seguro_clientes sc ON sf.cliente_id = sc.id
        WHERE sf.seguro_empresa_id = ? 
        AND sf.valor_pago > 0
        AND sf.data_baixa IS NOT NULL
        AND (sf.cliente_nao_encontrado IS NULL OR sf.cliente_nao_encontrado = 'nao')
    ");
    $stmt->execute([$percentual_empresa, $empresa_id]);
    $receitas = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Comissão do mês atual considerando dia de fechamento
    // IMPORTANTE: Calcula comissão APENAS de documentos com cliente vinculado (não em quarentena)
    // Lógica: Se dia_fechamento = 25, comissão de Outubro é de 26/09 até 25/10
    
    // Calcular período do mês atual de comissão
    $ano_atual = date('Y');
    $mes_atual = date('m');
    $dia_atual = date('d');
    
    // Se estamos antes do dia de fechamento, o período é do mês passado até hoje
    // Se estamos depois do dia de fechamento, o período é do mês atual até o próximo fechamento
    if ($dia_atual <= $dia_fechamento) {
        // Ainda estamos no período do mês anterior
        $mes_referencia = date('m', strtotime('-1 month'));
        $ano_referencia = date('Y', strtotime('-1 month'));
        $data_inicio = "$ano_referencia-$mes_referencia-" . str_pad($dia_fechamento + 1, 2, '0', STR_PAD_LEFT);
        $data_fim = "$ano_atual-$mes_atual-" . str_pad($dia_fechamento, 2, '0', STR_PAD_LEFT);
    } else {
        // Já estamos no período do mês atual
        $data_inicio = "$ano_atual-$mes_atual-" . str_pad($dia_fechamento + 1, 2, '0', STR_PAD_LEFT);
        $mes_proximo = date('m', strtotime('+1 month'));
        $ano_proximo = date('Y', strtotime('+1 month'));
        $data_fim = "$ano_proximo-$mes_proximo-" . str_pad($dia_fechamento, 2, '0', STR_PAD_LEFT);
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_docs_mes,
            COALESCE(SUM(sf.valor_pago), 0) as valor_pago_mes,
            COALESCE(SUM(
                sf.valor_pago * ((? + COALESCE(sc.porcentagem_recorrencia, 0)) / 100)
            ), 0) as comissao_mensal
        FROM seguro_financeiro sf
        INNER JOIN seguro_clientes sc ON sf.cliente_id = sc.id
        WHERE sf.seguro_empresa_id = ? 
        AND sf.valor_pago > 0
        AND sf.data_baixa IS NOT NULL
        AND (sf.cliente_nao_encontrado IS NULL OR sf.cliente_nao_encontrado = 'nao')
        AND sf.data_baixa BETWEEN ? AND ?
    ");
    $stmt->execute([$percentual_empresa, $empresa_id, $data_inicio, $data_fim]);
    $comissaoMensal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Documentos em quarentena
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_quarentena,
            COALESCE(SUM(valor), 0) as valor_quarentena
        FROM seguro_financeiro 
        WHERE seguro_empresa_id = ? AND cliente_nao_encontrado = 'sim'
    ");
    $stmt->execute([$empresa_id]);
    $quarentena = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Erro ao carregar estatísticas: " . $e->getMessage());
    $receitas = ['total_receitas' => 0, 'valor_receitas' => 0, 'comissao_geral' => 0];
    $comissaoMensal = ['comissao_mensal' => 0];
    $percentual_empresa = 0;
    $quarentena = ['total_quarentena' => 0, 'valor_quarentena' => 0];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Seguro - Financeiro</title>
    <script src="js/tema-instantaneo.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/menu-responsivo.css" rel="stylesheet">
    <link href="css/temas.css" rel="stylesheet">
    <link href="css/tema-escuro-forcado.css" rel="stylesheet">
    <style>
        .header {
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .financial-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .financial-card .card-body {
            padding: 2rem;
        }
        .financial-icon {
            font-size: 3rem;
            opacity: 0.8;
        }
        .revenue-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        .expense-card {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
            color: white;
        }
        .profit-card {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
        }
        .table-container {
            border-radius: 15px;
            padding: 20px;
        }
        .search-container {
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 24px;
            font-weight: 600;
            color: white;
        }
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .chart-container {
            position: relative;
            height: 300px;
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
                <a class="nav-link active" href="financeiro.php">
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
                    <div>
                        <h2 class="mb-1">
                            <i class="fas fa-chart-line me-2"></i>
                            Financeiro
                        </h2>
                        <p class="text-muted mb-0">Gestão Financeira e Relatórios</p>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <span class="me-3">Bem-vindo, Admin</span>
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

        <!-- Financial Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card revenue-card">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle financial-icon"></i>
                        <h3 class="mt-3 mb-1">R$ <?php echo number_format($receitas['valor_receitas'], 2, ',', '.'); ?></h3>
                        <p class="mb-0">Receitas Pagas</p>
                        <small class="opacity-75"><?php echo $receitas['total_receitas']; ?> documento(s)</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card expense-card">
                    <div class="card-body text-center">
                        <i class="fas fa-dollar-sign financial-icon"></i>
                        <h3 class="mt-3 mb-1">R$ <?php echo number_format($receitas['comissao_geral'], 2, ',', '.'); ?></h3>
                        <p class="mb-0">Comissão Geral</p>
                        <small class="opacity-75">
                            <?php echo $receitas['total_receitas']; ?> doc(s) | 
                            % Emp: <?php echo number_format($percentual_empresa, 2); ?>%
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card profit-card">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-alt financial-icon"></i>
                        <h3 class="mt-3 mb-1" id="comissaoMensalCard">R$ <?php echo number_format($comissaoMensal['comissao_mensal'], 2, ',', '.'); ?></h3>
                        <p class="mb-0">Comissão Mensal</p>
                        <small class="opacity-75" id="mesReferenciaCard">
                            <?php 
                            $meses = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 
                                      'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
                            echo $meses[date('n')] . '/' . date('Y'); 
                            ?>
                            <br><?php echo $comissaoMensal['total_docs_mes']; ?> doc(s) do mês
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); color: white;">
                    <div class="card-body text-center">
                        <i class="fas fa-exclamation-triangle financial-icon"></i>
                        <h3 class="mt-3 mb-1"><?php echo $quarentena['total_quarentena']; ?></h3>
                        <p class="mb-0">Em Quarentena</p>
                        <small class="opacity-75">R$ <?php echo number_format($quarentena['valor_quarentena'], 2, ',', '.'); ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-area me-2"></i>
                            Fluxo de Caixa - Últimos 12 Meses
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="cashFlowChart"></canvas>
                            <div id="semDadosFluxo" style="display:none; text-align:center; padding:50px;">
                                <i class="fas fa-chart-area" style="font-size:48px; color:#ddd;"></i>
                                <p style="color:#999; margin-top:10px;">Sem dados disponíveis</p>
                                <small style="color:#999;">Importe documentos para visualizar o gráfico</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie me-2"></i>
                            Distribuição de Receitas
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                            <div id="semDadosReceita" style="display:none; text-align:center; padding:50px;">
                                <i class="fas fa-chart-pie" style="font-size:48px; color:#ddd;"></i>
                                <p style="color:#999; margin-top:10px;">Sem dados disponíveis</p>
                                <small style="color:#999;">Importe documentos para visualizar o gráfico</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Actions -->
        <div class="search-container">
            <!-- Linha de Botões de Ação -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="d-flex gap-2 justify-content-end flex-wrap">
                        <button class="btn btn-warning" onclick="abrirQuarentena()">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Quarentena
                        </button>
                        <button class="btn btn-danger" onclick="abrirClientesSemPagamento()">
                            <i class="fas fa-user-times me-1"></i>
                            Clientes Sem Pagamento
                        </button>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalImportarRetorno">
                            <i class="fas fa-file-upload me-1"></i>
                            Importar Retorno
                        </button>
                        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#modalHistoricoImportacoes">
                            <i class="fas fa-history me-1"></i>
                            Histórico de Importações
                        </button>
                    </div>
                </div>
            </div>

            <!-- Linha de Filtros -->
            <div class="row align-items-center">
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control" placeholder="Buscar transação..." id="transactionSearch">
                    </div>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="statusFilter">
                        <option value="">Todos Status</option>
                        <option value="pago">Pago</option>
                        <option value="pendente">Pendente</option>
                        <option value="vencido">Vencido</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" id="dateFrom" placeholder="Data Inicial">
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" id="dateTo" placeholder="Data Final">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" onclick="aplicarFiltros()">
                        <i class="fas fa-filter me-1"></i>
                        Aplicar
                    </button>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-outline-secondary w-100" onclick="limparFiltros()" title="Limpar Filtros">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>N° Doc</th>
                            <th>Data Venc.</th>
                            <th>Associado</th>
                            <th>Classe</th>
                            <th>Placa</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaTransacoes">
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="fas fa-spinner fa-spin me-2"></i>
                                Carregando documentos...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginação e Controles -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div>
                    <span class="text-muted">Mostrando <strong id="infoInicioDoc">0</strong> a <strong id="infoFimDoc">0</strong> de <strong id="infoTotalDoc">0</strong> documentos</span>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <select class="form-select form-select-sm" id="documentosPorPagina" style="width: 100px;">
                        <option value="10" selected>10 / página</option>
                        <option value="20">20 / página</option>
                        <option value="50">50 / página</option>
                        <option value="100">100 / página</option>
                    </select>
                    <nav>
                        <ul class="pagination pagination-sm mb-0" id="paginacaoDocumentos">
                            <!-- Gerado via JavaScript -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Quarentena Financeira -->
    <div class="modal fade" id="modalQuarentena" tabindex="-1">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Quarentena Financeira - Documentos sem Cliente
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Resumo -->
                    <div class="row mb-4" id="resumoQuarentena">
                        <div class="col-md-3">
                            <div class="card text-white bg-warning">
                                <div class="card-body">
                                    <h6 class="card-title">📋 Total de Documentos</h6>
                                    <h3 id="qtdQuarentena">-</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-danger">
                                <div class="card-body">
                                    <h6 class="card-title">💰 Valor Total</h6>
                                    <h3 id="valorQuarentena">R$ 0,00</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-success">
                                <div class="card-body">
                                    <h6 class="card-title">✅ Valor Pago</h6>
                                    <h3 id="pagoQuarentena">R$ 0,00</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-info">
                                <div class="card-body">
                                    <h6 class="card-title">⏳ Valor Pendente</h6>
                                    <h3 id="pendenteQuarentena">R$ 0,00</h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Alert Informativo -->
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-info-circle me-2"></i>O que é Quarentena Financeira?</h6>
                        <p class="mb-0">Documentos que foram importados mas não têm cliente correspondente no sistema. 
                        Você deve cadastrar os clientes faltantes e depois vincular estes documentos a eles.</p>
                    </div>

                    <!-- Tabela de Documentos -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-warning">
                                <tr>
                                    <th>Unidade</th>
                                    <th>MATRÍCULA</th>
                                    <th>CONJUNTO</th>
                                    <th>N° Doc</th>
                                    <th>Associado</th>
                                    <th>Classe</th>
                                    <th>Emissão</th>
                                    <th>Vencimento</th>
                                    <th>Valor</th>
                                    <th>Placa</th>
                                    <th>Status</th>
                                    <th>Importado em</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="tabelaQuarentena">
                                <tr>
                                    <td colspan="13" class="text-center">
                                        <i class="fas fa-spinner fa-spin"></i> Carregando...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-primary" onclick="exportarQuarentena()">
                        <i class="fas fa-file-excel me-1"></i>
                        Exportar para Excel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Clientes Sem Pagamento -->
    <div class="modal fade" id="modalClientesSemPagamento" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-user-times me-2"></i>
                        Clientes Sem Pagamento no Período
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Filtro de Período -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Data Início:</label>
                            <input type="date" id="dataInicioSemPagamento" class="form-control" value="<?php echo date('Y-m-01'); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Data Fim:</label>
                            <input type="date" id="dataFimSemPagamento" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button class="btn btn-primary w-100" onclick="carregarClientesSemPagamento()">
                                <i class="fas fa-search me-1"></i>
                                Buscar
                            </button>
                        </div>
                    </div>

                    <!-- Resumo -->
                    <div class="row mb-4" id="resumoSemPagamento">
                        <div class="col-md-4">
                            <div class="card text-white bg-danger">
                                <div class="card-body">
                                    <h6 class="card-title">👥 Total de Clientes</h6>
                                    <h3 id="qtdSemPagamento">-</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-white bg-warning">
                                <div class="card-body">
                                    <h6 class="card-title">📋 Clientes Ativos</h6>
                                    <h3 id="qtdAtivosSemPagamento">-</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-white bg-info">
                                <div class="card-body">
                                    <h6 class="card-title">⏸️ Clientes Inativos</h6>
                                    <h3 id="qtdInativosSemPagamento">-</h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Alert Informativo -->
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i>O que são Clientes Sem Pagamento?</h6>
                        <p class="mb-0">Lista de clientes cadastrados que não tiveram nenhum documento pago no período selecionado. 
                        Use esta lista para fazer cobranças e acompanhamento.</p>
                    </div>

                    <!-- Tabela de Clientes -->
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th width="8%">Identificador</th>
                                    <th width="12%">CPF/CNPJ</th>
                                    <th width="25%">Nome/Razão Social</th>
                                    <th width="12%">Matrícula</th>
                                    <th width="15%">Cidade</th>
                                    <th width="8%">Telefone</th>
                                    <th width="10%">% Cliente</th>
                                    <th width="10%">Situação</th>
                                </tr>
                            </thead>
                            <tbody id="tabelaClientesSemPagamento">
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <i class="fas fa-spinner fa-spin"></i> Carregando...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-success" onclick="exportarClientesSemPagamento()">
                        <i class="fas fa-file-excel me-1"></i>
                        Exportar para Excel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Importar Arquivo Retorno -->
    <div class="modal fade" id="modalImportarRetorno" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-file-upload me-2"></i>
                        Importar Arquivo Retorno - Documentos Financeiros
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Instruções:</strong> Selecione um arquivo Excel (.xlsx, .xls) ou CSV (.csv) com os documentos financeiros.
                        <br><strong>Formato aceito:</strong> INGLÊS (do banco) ou PORTUGUÊS
                        <br><strong>Campos obrigatórios:</strong> N° DOC (DocNumber), MATRÍCULA (IdRegistration), CONJUNTO (IdSet), EMISSÃO (DateEmissao), VENCIMENTO (ExpirationDate)
                    </div>

                    <!-- Área de Upload -->
                    <div class="mb-4">
                        <label for="arquivoRetorno" class="form-label">
                            <i class="fas fa-file-excel me-2"></i>
                            Selecione o Arquivo
                        </label>
                        <input type="file" class="form-control" id="arquivoRetorno" accept=".xlsx,.xls,.csv" required>
                    </div>

                    <!-- Preview dos Dados -->
                    <div id="previewDados" style="display: none;">
                        <h6 class="mb-3"><i class="fas fa-eye me-2"></i>Preview dos Dados (primeiras 5 linhas):</h6>
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm table-bordered" id="tabelaPreview">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>MATRÍCULA</th>
                                        <th>CONJUNTO</th>
                                        <th>N° DOC</th>
                                        <th>ASSOCIADO</th>
                                        <th>CLASSE</th>
                                        <th>EMISSÃO</th>
                                        <th>VENCIMENTO</th>
                                        <th>VALOR</th>
                                        <th>PLACA</th>
                                        <th>SITUAÇÃO</th>
                                        <th>VALOR PAGO</th>
                                        <th>DATA BAIXA</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="corpoTabelaPreview"></tbody>
                            </table>
                        </div>
                        
                        <div class="alert alert-success mt-3" id="resumoImportacao">
                            <!-- Será preenchido via JavaScript -->
                        </div>
                    </div>

                    <!-- Área de Progresso -->
                    <div id="areaProgresso" style="display: none;">
                        <div class="progress" style="height: 25px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 id="barraProgresso" 
                                 role="progressbar" 
                                 style="width: 0%">
                                0%
                            </div>
                        </div>
                        <p class="text-center mt-2" id="textoProgresso">Processando...</p>
                    </div>

                    <!-- Resultado da Importação -->
                    <div id="resultadoImportacao" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnProcessarPreview" style="display: none;" onclick="processarArquivo()">
                        <i class="fas fa-eye me-1"></i>
                        Visualizar Dados
                    </button>
                    <button type="button" class="btn btn-success" id="btnImportar" style="display: none;" onclick="importarDocumentos()">
                        <i class="fas fa-check me-1"></i>
                        Confirmar Importação
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Histórico de Importações -->
    <div class="modal fade" id="modalHistoricoImportacoes" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-history me-2"></i>
                        Histórico de Importações
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Histórico de todos os arquivos CSV importados no sistema.
                    </div>

                    <!-- Filtros -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Período:</label>
                            <select class="form-select" id="filtroHistoricoPeriodo">
                                <option value="7">Últimos 7 dias</option>
                                <option value="30" selected>Últimos 30 dias</option>
                                <option value="90">Últimos 90 dias</option>
                                <option value="0">Todos</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status:</label>
                            <select class="form-select" id="filtroHistoricoStatus">
                                <option value="todos">Todos</option>
                                <option value="sucesso">Sucesso</option>
                                <option value="com_erros">Com Erros</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button class="btn btn-primary w-100" onclick="carregarHistoricoImportacoes()">
                                <i class="fas fa-search me-1"></i>
                                Buscar
                            </button>
                        </div>
                    </div>

                    <!-- Tabela de Histórico -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>Arquivo</th>
                                    <th>Total</th>
                                    <th>Processados</th>
                                    <th>Erros</th>
                                    <th>Usuário</th>
                                    <th>Status</th>
                                    <th>Detalhes</th>
                                </tr>
                            </thead>
                            <tbody id="historicoImportacoesBody">
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="fas fa-spinner fa-spin me-2"></i>
                                        Carregando histórico...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="js/menu-responsivo.js"></script>
    <script src="js/temas.js"></script>
    <script>
        // Variáveis globais para os gráficos
        let graficoFluxoCaixa;
        let graficoCategorias;
        
        // Aplicar filtros e atualizar comissão mensal
        async function aplicarFiltros() {
            const search = document.getElementById('transactionSearch').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            
            const rows = document.querySelectorAll('#tabelaTransacoes tr');
            
            rows.forEach(row => {
                const texto = row.textContent.toLowerCase();
                const statusCell = row.querySelector('.badge');
                const status = statusCell ? statusCell.textContent.toLowerCase() : '';
                
                let mostrar = true;
                
                // Filtro de busca
                if (search && !texto.includes(search)) {
                    mostrar = false;
                }
                
                // Filtro de status
                if (statusFilter && !status.includes(statusFilter.toLowerCase())) {
                    mostrar = false;
                }
                
                row.style.display = mostrar ? '' : 'none';
            });
            
            // Se houver filtro de data, atualizar comissão mensal
            if (dateFrom && dateTo) {
                await atualizarComissaoMensal(dateFrom, dateTo);
            } else if (dateFrom || dateTo) {
                alert('Por favor, selecione ambas as datas (Data Inicial e Data Final)');
            }
        }
        
        // Atualizar comissão mensal com base nas datas
        async function atualizarComissaoMensal(dataInicio, dataFim) {
            try {
                const response = await fetch(`api/calcular_comissao_periodo.php?data_inicio=${dataInicio}&data_fim=${dataFim}`);
                const resultado = await response.json();
                
                if (resultado.sucesso) {
                    // Atualizar card de comissão mensal
                    document.getElementById('comissaoMensalCard').textContent = 
                        'R$ ' + parseFloat(resultado.comissao).toFixed(2).replace('.', ',');
                    
                    // Atualizar descrição com período
                    const dataInicioFmt = formatarDataBR(dataInicio);
                    const dataFimFmt = formatarDataBR(dataFim);
                    document.getElementById('mesReferenciaCard').textContent = 
                        `${dataInicioFmt} a ${dataFimFmt}`;
                    
                    console.log('✅ Comissão atualizada para o período:', resultado);
                } else {
                    alert('Erro ao calcular comissão: ' + (resultado.mensagem || 'Erro desconhecido'));
                }
            } catch (error) {
                console.error('Erro ao calcular comissão:', error);
                alert('Erro ao calcular comissão do período');
            }
        }
        
        // Formatar data para formato brasileiro
        function formatarDataBR(data) {
            if (!data) return '';
            const partes = data.split('-');
            return `${partes[2]}/${partes[1]}/${partes[0]}`;
        }
        
        // Limpar filtros e voltar ao mês atual
        function limparFiltros() {
            // Limpar campos
            document.getElementById('transactionSearch').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            
            // Mostrar todas as linhas
            const rows = document.querySelectorAll('#tabelaTransacoes tr');
            rows.forEach(row => {
                row.style.display = '';
            });
            
            // Recarregar página para restaurar valores originais
            location.reload();
        }
        
        // Visualizar documento individual
        function verDocumento(id) {
            alert(`Visualizando documento #${id}\n\nFuncionalidade será implementada em breve!`);
            // TODO: Criar modal com detalhes completos do documento
        }
        
        // Gráfico de Fluxo de Caixa - Carregar dados reais
        async function carregarGraficoFluxoCaixa() {
            try {
                const response = await fetch('api/dashboard_comissoes_6_meses.php');
                const data = await response.json();
                
                if (data.sucesso && data.dados && data.dados.length > 0) {
                    // Verificar se há pelo menos um valor > 0
                    const temDados = data.dados.some(d => parseFloat(d.total_comissao) > 0);
                    
                    if (temDados) {
                        const meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
                        const labels = data.dados.map(d => {
                            const [ano, mes] = d.mes.split('-');
                            return meses[parseInt(mes) - 1] + '/' + ano.substring(2);
                        });
                        const valores = data.dados.map(d => parseFloat(d.total_comissao));
                        
                        const cashFlowCtx = document.getElementById('cashFlowChart').getContext('2d');
                        graficoFluxoCaixa = new Chart(cashFlowCtx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Comissões',
                                    data: valores,
                                    borderColor: '#28a745',
                                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                    tension: 0.4,
                                    fill: true,
                                    pointBackgroundColor: '#28a745',
                                    pointRadius: 4
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            callback: function(value) {
                                                return 'R$ ' + value.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    } else {
                        // Sem dados, mostrar mensagem
                        mostrarSemDados('cashFlowChart', 'semDadosFluxo');
                    }
                } else {
                    // Sem dados, mostrar mensagem
                    mostrarSemDados('cashFlowChart', 'semDadosFluxo');
                }
            } catch (error) {
                console.error('Erro ao carregar fluxo de caixa:', error);
                mostrarSemDados('cashFlowChart', 'semDadosFluxo');
            }
        }

        // Gráfico de Distribuição de Receitas - Apenas se houver dados
        async function carregarGraficoDistribuicao() {
            try {
                // Verificar se há documentos
                const response = await fetch('api/obter_estatisticas_financeiro.php');
                const data = await response.json();
                
                if (data.sucesso && data.total_documentos > 0) {
                    // Tem dados, renderizar gráfico (distribuição por classe)
                    const responseClasses = await fetch('api/obter_distribuicao_classes.php');
                    const dataClasses = await responseClasses.json();
                    
                    if (dataClasses.sucesso && dataClasses.classes && dataClasses.classes.length > 0) {
                        const labels = dataClasses.classes.map(c => c.classe || 'Sem Classe');
                        const valores = dataClasses.classes.map(c => parseFloat(c.total));
                        const cores = ['#667eea', '#764ba2', '#28a745', '#ffc107', '#dc3545'];
                        
                        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
                        new Chart(revenueCtx, {
                            type: 'doughnut',
                            data: {
                                labels: labels,
                                datasets: [{
                                    data: valores,
                                    backgroundColor: cores.slice(0, labels.length)
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
                    } else {
                        mostrarSemDados('revenueChart', 'semDadosReceita');
                    }
                } else {
                    // Sem dados, mostrar mensagem
                    mostrarSemDados('revenueChart', 'semDadosReceita');
                }
            } catch (error) {
                console.error('Erro ao carregar distribuição:', error);
                mostrarSemDados('revenueChart', 'semDadosReceita');
            }
        }
        
        // Função auxiliar para mostrar "sem dados"
        function mostrarSemDados(canvasId, divId) {
            document.getElementById(canvasId).style.display = 'none';
            document.getElementById(divId).style.display = 'block';
        }
        
        // Carregar gráficos após o DOM estar pronto
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🎨 Inicializando gráficos do financeiro...');
            carregarGraficoFluxoCaixa();
            carregarGraficoDistribuicao();
        });

        // Funcionalidade de busca
        document.getElementById('transactionSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('tbody tr');
            
            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // ============================================
        // QUARENTENA FINANCEIRA
        // ============================================
        
        let dadosQuarentena = [];
        
        // Abrir modal de quarentena
        async function abrirQuarentena() {
            const modal = new bootstrap.Modal(document.getElementById('modalQuarentena'));
            modal.show();
            
            // Carregar dados
            await carregarQuarentena();
        }
        
        // Carregar documentos em quarentena
        async function carregarQuarentena() {
            try {
                const response = await fetch('api/obter_quarentena.php');
                const resultado = await response.json();
                
                if (resultado.success) {
                    dadosQuarentena = resultado.documentos || [];
                    
                    // Atualizar resumo
                    document.getElementById('qtdQuarentena').textContent = resultado.resumo.total_documentos;
                    document.getElementById('valorQuarentena').textContent = 'R$ ' + parseFloat(resultado.resumo.valor_total).toFixed(2).replace('.', ',');
                    document.getElementById('pagoQuarentena').textContent = 'R$ ' + parseFloat(resultado.resumo.valor_pago).toFixed(2).replace('.', ',');
                    document.getElementById('pendenteQuarentena').textContent = 'R$ ' + parseFloat(resultado.resumo.valor_pendente).toFixed(2).replace('.', ',');
                    
                    // Preencher tabela
                    const tbody = document.getElementById('tabelaQuarentena');
                    if (dadosQuarentena.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="13" class="text-center text-success"><i class="fas fa-check-circle me-2"></i>Nenhum documento em quarentena! 🎉</td></tr>';
                    } else {
                        let html = '';
                        dadosQuarentena.forEach(doc => {
                            const badgeStatus = {
                                'pendente': 'bg-warning',
                                'pago': 'bg-success',
                                'vencido': 'bg-danger',
                                'cancelado': 'bg-secondary'
                            };
                            
                            html += `
                                <tr style="background-color: #fff3cd;">
                                    <td>${doc.unidade || '-'}</td>
                                    <td><strong>${doc.matricula || '-'}</strong></td>
                                    <td><strong>${doc.conjunto || '-'}</strong></td>
                                    <td>${doc.numero_documento}</td>
                                    <td>${doc.associado}</td>
                                    <td>${doc.classe || '-'}</td>
                                    <td>${doc.data_emissao || '-'}</td>
                                    <td>${doc.data_vencimento || '-'}</td>
                                    <td><strong>R$ ${parseFloat(doc.valor).toFixed(2).replace('.', ',')}</strong></td>
                                    <td>${doc.placa || '-'}</td>
                                    <td><span class="badge ${badgeStatus[doc.status] || 'bg-secondary'}">${doc.status}</span></td>
                                    <td><small>${doc.data_importacao}</small></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="vincularCliente(${doc.id})" title="Vincular a Cliente">
                                            <i class="fas fa-link"></i>
                                        </button>
                                    </td>
                                </tr>
                            `;
                        });
                        tbody.innerHTML = html;
                    }
                } else {
                    throw new Error(resultado.error || 'Erro ao carregar quarentena');
                }
            } catch (error) {
                console.error('Erro:', error);
                document.getElementById('tabelaQuarentena').innerHTML = 
                    '<tr><td colspan="13" class="text-center text-danger"><i class="fas fa-exclamation-circle me-2"></i>Erro ao carregar dados</td></tr>';
            }
        }
        
        // Vincular documento a um cliente
        async function vincularCliente(docId) {
            if (!confirm('Tentar vincular este documento ao cliente correspondente?\n\nO sistema buscará automaticamente o cliente pela MATRÍCULA e CONJUNTO.')) {
                return;
            }
            
            try {
                // Mostrar loading
                const btnVincular = event.target.closest('button');
                btnVincular.disabled = true;
                btnVincular.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                
                const formData = new FormData();
                formData.append('documento_id', docId);
                
                const response = await fetch('api/vincular_documento_cliente.php', {
                    method: 'POST',
                    body: formData
                });
                
                const resultado = await response.json();
                
                if (resultado.sucesso) {
                    // Sucesso!
                    let mensagem = resultado.mensagem;
                    
                    if (!resultado.tem_contrato) {
                        mensagem += '\n\n⚠️ ATENÇÃO: Não foi encontrado um contrato ativo com o CONJUNTO informado.\nCadastre o contrato para que a comissão seja calculada corretamente.';
                    }
                    
                    alert(mensagem);
                    
                    // Recarregar quarentena
                    await carregarQuarentena();
                } else {
                    alert('❌ Erro ao vincular documento:\n\n' + resultado.mensagem);
                    btnVincular.disabled = false;
                    btnVincular.innerHTML = '<i class="fas fa-link"></i>';
                }
            } catch (error) {
                console.error('Erro ao vincular:', error);
                alert('❌ Erro ao vincular documento: ' + error.message);
            }
        }
        
        // Exportar quarentena para Excel
        function exportarQuarentena() {
            if (dadosQuarentena.length === 0) {
                alert('Não há documentos em quarentena para exportar!');
                return;
            }
            
            // Criar CSV
            let csv = 'Unidade;Identificador;Nº Doc;Associado;Classe;Emissão;Vencimento;Valor;Placa;Conjunto;Matrícula;Status;Importado em\n';
            
            dadosQuarentena.forEach(doc => {
                csv += `${doc.unidade || ''};`;
                csv += `${doc.identificador};`;
                csv += `${doc.numero_documento};`;
                csv += `${doc.associado};`;
                csv += `${doc.classe || ''};`;
                csv += `${doc.data_emissao || ''};`;
                csv += `${doc.data_vencimento || ''};`;
                csv += `${doc.valor};`;
                csv += `${doc.placa || ''};`;
                csv += `${doc.conjunto || ''};`;
                csv += `${doc.matricula || ''};`;
                csv += `${doc.status};`;
                csv += `${doc.data_importacao}\n`;
            });
            
            // Download
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'quarentena_financeira_' + new Date().toISOString().split('T')[0] + '.csv';
            link.click();
        }

        // ============================================
        // CLIENTES SEM PAGAMENTO NO PERÍODO
        // ============================================
        
        let dadosClientesSemPagamento = [];
        
        // Abrir modal de clientes sem pagamento
        async function abrirClientesSemPagamento() {
            const modal = new bootstrap.Modal(document.getElementById('modalClientesSemPagamento'));
            modal.show();
            
            // Carregar dados automaticamente
            await carregarClientesSemPagamento();
        }
        
        // Carregar clientes sem pagamento
        async function carregarClientesSemPagamento() {
            const tbody = document.getElementById('tabelaClientesSemPagamento');
            
            try {
                const dataInicio = document.getElementById('dataInicioSemPagamento').value;
                const dataFim = document.getElementById('dataFimSemPagamento').value;
                
                console.log('Carregando clientes sem pagamento:', dataInicio, 'até', dataFim);
                
                if (!dataInicio || !dataFim) {
                    alert('Por favor, selecione o período!');
                    return;
                }
                
                // Mostrar loading
                tbody.innerHTML = '<tr><td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin"></i> Carregando clientes...</td></tr>';
                
                const url = `api/clientes_sem_pagamento.php?data_inicio=${dataInicio}&data_fim=${dataFim}`;
                console.log('URL da API:', url);
                
                const response = await fetch(url);
                console.log('Response status:', response.status);
                
                const resultado = await response.json();
                console.log('Resultado da API:', resultado);
                
                if (resultado.sucesso) {
                    dadosClientesSemPagamento = resultado.clientes || [];
                    
                    console.log('Total de clientes sem pagamento:', dadosClientesSemPagamento.length);
                    
                    // Atualizar resumo
                    document.getElementById('qtdSemPagamento').textContent = resultado.resumo.total_clientes;
                    document.getElementById('qtdAtivosSemPagamento').textContent = resultado.resumo.clientes_ativos;
                    document.getElementById('qtdInativosSemPagamento').textContent = resultado.resumo.clientes_inativos;
                    
                    // Debug adicional
                    if (resultado.debug) {
                        console.log('Debug da API:', resultado.debug);
                    }
                    
                    // Preencher tabela
                    if (dadosClientesSemPagamento.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-success"><i class="fas fa-check-circle me-2"></i>✅ Excelente! Todos os clientes tiveram pagamento no período!</td></tr>';
                    } else {
                        let html = '';
                        dadosClientesSemPagamento.forEach(cliente => {
                            const badgeClass = cliente.situacao === 'ativo' ? 'bg-success' : 'bg-secondary';
                            const telefone = cliente.telefone || cliente.celular || '-';
                            
                            html += `
                                <tr>
                                    <td>${cliente.identificador || cliente.codigo || '-'}</td>
                                    <td>${cliente.cpf_cnpj}</td>
                                    <td><strong>${cliente.nome_razao_social}</strong></td>
                                    <td>${cliente.matricula || '-'}</td>
                                    <td>${cliente.cidade || '-'}/${cliente.uf || '-'}</td>
                                    <td>${telefone}</td>
                                    <td><span class="badge bg-info">${parseFloat(cliente.porcentagem_recorrencia || 0).toFixed(2)}%</span></td>
                                    <td><span class="badge ${badgeClass}">${cliente.situacao.toUpperCase()}</span></td>
                                </tr>
                            `;
                        });
                        tbody.innerHTML = html;
                    }
                } else {
                    console.error('Erro na API:', resultado.mensagem);
                    alert('Erro ao carregar dados: ' + resultado.mensagem);
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger"><i class="fas fa-exclamation-circle me-2"></i>' + resultado.mensagem + '</td></tr>';
                }
            } catch (error) {
                console.error('Erro ao carregar clientes sem pagamento:', error);
                alert('Erro ao carregar dados: ' + error.message);
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger"><i class="fas fa-exclamation-circle me-2"></i>Erro ao carregar dados: ' + error.message + '</td></tr>';
            }
        }
        
        // Exportar clientes sem pagamento para Excel
        function exportarClientesSemPagamento() {
            if (dadosClientesSemPagamento.length === 0) {
                alert('Não há clientes sem pagamento para exportar!');
                return;
            }
            
            const dataInicio = document.getElementById('dataInicioSemPagamento').value;
            const dataFim = document.getElementById('dataFimSemPagamento').value;
            
            // Criar CSV
            let csv = 'Identificador;CPF/CNPJ;Nome/Razão Social;Matrícula;Cidade;UF;Telefone;% Cliente;Situação\n';
            
            dadosClientesSemPagamento.forEach(cliente => {
                const telefone = cliente.telefone || cliente.celular || '';
                csv += `${cliente.identificador || cliente.codigo || ''};`;
                csv += `${cliente.cpf_cnpj};`;
                csv += `${cliente.nome_razao_social};`;
                csv += `${cliente.matricula || ''};`;
                csv += `${cliente.cidade || ''};`;
                csv += `${cliente.uf || ''};`;
                csv += `${telefone};`;
                csv += `${parseFloat(cliente.porcentagem_recorrencia || 0).toFixed(2)}%;`;
                csv += `${cliente.situacao.toUpperCase()}\n`;
            });
            
            // Download
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `clientes_sem_pagamento_${dataInicio}_a_${dataFim}.csv`;
            link.click();
        }

        // ============================================
        // IMPORTAÇÃO DE ARQUIVO RETORNO
        // ============================================
        
        let dadosImportacao = [];
        let nomeArquivoAtual = '';

        // Quando selecionar arquivo
        document.getElementById('arquivoRetorno').addEventListener('change', function(e) {
            const arquivo = e.target.files[0];
            if (!arquivo) return;

            // Guardar nome do arquivo
            nomeArquivoAtual = arquivo.name;

            document.getElementById('btnProcessarPreview').style.display = 'inline-block';
            document.getElementById('previewDados').style.display = 'none';
            document.getElementById('btnImportar').style.display = 'none';
            document.getElementById('resultadoImportacao').style.display = 'none';
            dadosImportacao = [];
        });

        // Processar arquivo e mostrar preview
        function processarArquivo() {
            const arquivoInput = document.getElementById('arquivoRetorno');
            const arquivo = arquivoInput.files[0];

            if (!arquivo) {
                alert('Por favor, selecione um arquivo!');
                return;
            }

            const reader = new FileReader();

            reader.onload = function(e) {
                try {
                    let dados;
                    
                    // Verificar tipo de arquivo
                    if (arquivo.name.endsWith('.csv')) {
                        // Processar CSV
                        dados = processarCSV(e.target.result);
                    } else {
                        // Processar Excel
                        const data = new Uint8Array(e.target.result);
                        const workbook = XLSX.read(data, { type: 'array' });
                        const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                        dados = XLSX.utils.sheet_to_json(firstSheet, { header: 1 });
                    }

                    if (!dados || dados.length < 2) {
                        alert('Arquivo vazio ou inválido!');
                        return;
                    }

                    // Processar os dados
                    processarDados(dados);

                } catch (error) {
                    console.error('Erro ao processar arquivo:', error);
                    alert('Erro ao processar arquivo: ' + error.message);
                }
            };

            if (arquivo.name.endsWith('.csv')) {
                reader.readAsText(arquivo);
            } else {
                reader.readAsArrayBuffer(arquivo);
            }
        }

        // Processar CSV
        function processarCSV(texto) {
            const linhas = texto.split('\n');
            return linhas.map(linha => {
                // Split por tab ou ponto-e-vírgula
                return linha.split(/\t|;/).map(cell => cell.trim());
            });
        }

        // Processar dados e mostrar preview
        function processarDados(dados) {
            const cabecalho = dados[0];
            const linhasDados = dados.slice(1);
            
            console.log('📋 DEBUG - Cabeçalho recebido:', cabecalho);
            console.log('📋 DEBUG - Total de linhas de dados:', linhasDados.length);
            
            dadosImportacao = [];
            let clientesNaoEncontrados = [];

            // Mapear índices das colunas (aceita TODAS as variações incluindo INGLÊS)
            const colunas = {
                unidade: encontrarIndiceColuna(cabecalho, [
                    'unity', 'unidade', 'unidad', 'unit'
                ]),
                ponteiro: encontrarIndiceColuna(cabecalho, [
                    'ponteiro', 'pointer', 'pont'
                ]),
                identificador: encontrarIndiceColuna(cabecalho, [
                    'identificador', 'id', 'ident', 'codigo', 'code'
                ]),
                ndoc: encontrarIndiceColuna(cabecalho, [
                    // INGLÊS (vem do banco):
                    'docnumber', 'doc number',
                    // PORTUGUÊS:
                    'n doc', 'ndoc', 'n° doc', 'n?doc', 'n  doc', 'numero documento', 
                    'numero doc', 'num doc', 'nro doc', 'nr doc', 'documento', 'n documento'
                ]),
                associado: encontrarIndiceColuna(cabecalho, [
                    // INGLÊS (vem do banco):
                    'customer', 'client',
                    // PORTUGUÊS:
                    'associado', 'cliente', 'clientes', 'nome', 'razao social'
                ]),
                classe: encontrarIndiceColuna(cabecalho, [
                    // INGLÊS (vem do banco):
                    'financialclass', 'financial class', 'class',
                    // PORTUGUÊS:
                    'classe', 'tipo', 'categoria'
                ]),
                emissao: encontrarIndiceColuna(cabecalho, [
                    // INGLÊS (vem do banco):
                    'dateemissao', 'date emissao', 'emission date', 'issue date',
                    // PORTUGUÊS:
                    'emiss', 'emissao', 'emissão', 'emiss o', 'emiss?o', 
                    'data emissao', 'data emissão', 'dt emissao', 'dt emissão'
                ]),
                vencimento: encontrarIndiceColuna(cabecalho, [
                    // INGLÊS (vem do banco):
                    'expirationdate', 'expiration date', 'due date', 'duedate',
                    // PORTUGUÊS:
                    'vencimento', 'venc', 'vencimen', 'vencim', 
                    'data vencimento', 'dt vencimento', 'dt venc'
                ]),
                valor: encontrarIndiceColuna(cabecalho, [
                    // INGLÊS (vem do banco):
                    'valuetitle', 'value title', 'value', 'amount',
                    // PORTUGUÊS:
                    'valor', 'vlr', 'val', 'total'
                ]),
                placa: encontrarIndiceColuna(cabecalho, [
                    // INGLÊS (vem do banco):
                    'boardschassis', 'boards chassis', 'board', 'plate',
                    // PORTUGUÊS:
                    'placa', 'placas', 'veiculo', 'veículo'
                ]),
                conjunto: encontrarIndiceColuna(cabecalho, [
                    // INGLÊS (vem do banco):
                    'idset', 'id set', 'set',
                    // PORTUGUÊS:
                    'conjunto', 'conj', 'conjunt', 'grupo'
                ]),
                matricula: encontrarIndiceColuna(cabecalho, [
                    // INGLÊS (vem do banco):
                    'idregistration', 'id registration', 'registration',
                    // PORTUGUÊS:
                    'matr', 'matricula', 'matrícula', 'matr cula', 'matr?cula', 
                    'registro', 'reg'
                ]),
                situacao: encontrarIndiceColuna(cabecalho, [
                    // INGLÊS (vem do banco):
                    'situation', 'status',
                    // PORTUGUÊS:
                    'situa', 'situacao', 'situação', 'situa o', 'situa?o', 
                    'estado', 'sit'
                ]),
                valorPago: encontrarIndiceColuna(cabecalho, [
                    // INGLÊS (vem do banco):
                    'lowervalue', 'lower value', 'paid value', 'paid',
                    // PORTUGUÊS:
                    'valor pago', 'vlr pago', 'pago', 'valor pa', 'vlr pa', 
                    'valor recebido', 'recebido', 'pgt', 'pagamento'
                ]),
                dataBaixa: encontrarIndiceColuna(cabecalho, [
                    // INGLÊS (vem do banco):
                    'lowerdate', 'lower date', 'payment date', 'paid date',
                    // PORTUGUÊS:
                    'data da baixa', 'data baixa', 'baixa', 'dt baixa', 
                    'data pagamento', 'dt pagamento', 'dt pgt'
                ]),
                proposals: encontrarIndiceColuna(cabecalho, [
                    'proposals', 'proposta', 'propostas'
                ])
            };

            // Verificar se encontrou as colunas OBRIGATÓRIAS
            const camposFaltando = [];
            
            if (colunas.ndoc === -1) {
                camposFaltando.push('N° Documento (DocNumber)');
            }
            if (colunas.matricula === -1) {
                camposFaltando.push('MATRÍCULA (IdRegistration)');
            }
            if (colunas.conjunto === -1) {
                camposFaltando.push('CONJUNTO (IdSet)');
            }
            if (colunas.emissao === -1) {
                camposFaltando.push('Data de Emissão (DateEmissao)');
            }
            if (colunas.vencimento === -1) {
                camposFaltando.push('Data de Vencimento (ExpirationDate)');
            }
            
            // Debug: Mostrar quais colunas foram encontradas
            console.log('🔍 DEBUG - Colunas mapeadas:', {
                ndoc: colunas.ndoc,
                matricula: colunas.matricula,
                conjunto: colunas.conjunto,
                emissao: colunas.emissao,
                vencimento: colunas.vencimento
            });
            
            if (camposFaltando.length > 0) {
                console.error('❌ Campos faltando:', camposFaltando);
                console.error('📋 Cabeçalho normalizado:', cabecalho.map(c => normalizarNomeColuna(c)));
                
                alert('❌ Erro: Campos obrigatórios não encontrados:\n\n' + 
                      camposFaltando.join('\n') + 
                      '\n\nVerifique se o arquivo está no formato correto.\n\n' +
                      'Cabeçalho encontrado: ' + cabecalho.join(', '));
                return;
            }

            console.log('✅ Todos os campos obrigatórios foram encontrados!');
            
            // Processar cada linha
            for (let i = 0; i < linhasDados.length; i++) {
                const linha = linhasDados[i];
                
                console.log(`📄 Processando linha ${i + 1}:`, linha);
                
                // Pular linhas vazias
                if (!linha || linha.length < 2) {
                    console.log(`⏭️ Linha ${i + 1} pulada (vazia ou com poucos dados)`);
                    continue;
                }
                
                // Verificar se tem número do documento (obrigatório)
                if (!linha[colunas.ndoc]) {
                    console.log(`⏭️ Linha ${i + 1} pulada (sem número de documento)`);
                    continue;
                }

                const documento = {
                    unidade: limparTexto(linha[colunas.unidade]),
                    ponteiro: limparTexto(linha[colunas.ponteiro]),
                    numero_documento: limparTexto(linha[colunas.ndoc]),
                    associado: limparTexto(linha[colunas.associado]),
                    classe: limparTexto(linha[colunas.classe]),
                    data_emissao: formatarData(linha[colunas.emissao]),
                    data_vencimento: formatarData(linha[colunas.vencimento]),
                    valor: limparValor(linha[colunas.valor]),
                    placa: limparTexto(linha[colunas.placa]),
                    conjunto: limparTexto(linha[colunas.conjunto]),
                    matricula: limparTexto(linha[colunas.matricula]),
                    status: normalizarStatus(linha[colunas.situacao]),
                    valor_pago: limparValor(linha[colunas.valorPago]),
                    data_baixa: formatarData(linha[colunas.dataBaixa]),
                    proposals: limparTexto(linha[colunas.proposals]),
                    linha: i + 2, // +2 porque linha 1 é cabeçalho e arrays começam em 0
                    clienteExiste: null, // Será verificado depois
                    contratoExiste: null,
                    documentoDuplicado: false
                };

                console.log(`✅ Documento ${i + 1} adicionado:`, {
                    numero: documento.numero_documento,
                    matricula: documento.matricula,
                    conjunto: documento.conjunto
                });
                
                dadosImportacao.push(documento);
            }

            console.log(`📊 Total de documentos processados: ${dadosImportacao.length}`);
            
            // Verificar quais clientes existem
            verificarClientesExistentes();
        }

        // Função auxiliar para normalizar nome de coluna (remove acentos, espaços, caracteres especiais)
        function normalizarNomeColuna(nome) {
            if (!nome) return '';
            
            let normalizado = String(nome)
                .toLowerCase()
                .trim()
                // Remover acentos
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                // Substituir ? por espaço (erro comum do Excel)
                .replace(/\?/g, ' ')
                // Remover múltiplos espaços
                .replace(/\s+/g, ' ')
                // Remover caracteres especiais exceto espaço
                .replace(/[^a-z0-9\s]/g, '')
                .trim();
            
            return normalizado;
        }

        // Função auxiliar para encontrar índice de coluna (muito tolerante a variações)
        function encontrarIndiceColuna(cabecalho, possiveisNomes) {
            for (let i = 0; i < cabecalho.length; i++) {
                const nomeColuna = normalizarNomeColuna(cabecalho[i]);
                
                for (const nome of possiveisNomes) {
                    const nomeBusca = normalizarNomeColuna(nome);
                    
                    // Busca exata
                    if (nomeColuna === nomeBusca) {
                        return i;
                    }
                    
                    // Busca por inclusão (aceita truncados)
                    if (nomeColuna.includes(nomeBusca) || nomeBusca.includes(nomeColuna)) {
                        return i;
                    }
                    
                    // Busca sem espaços (para aceitar "ndoc", "n doc", "n?doc")
                    const colunaSemEspaco = nomeColuna.replace(/\s/g, '');
                    const buscaSemEspaco = nomeBusca.replace(/\s/g, '');
                    if (colunaSemEspaco === buscaSemEspaco || 
                        colunaSemEspaco.includes(buscaSemEspaco) || 
                        buscaSemEspaco.includes(colunaSemEspaco)) {
                        return i;
                    }
                }
            }
            return -1;
        }

        // Limpar texto
        function limparTexto(texto) {
            if (!texto) return '';
            return String(texto).trim();
        }

        // Limpar e formatar valor
        function limparValor(valor) {
            if (!valor) return '0.00';
            let valorStr = String(valor).replace(/[^\d,.-]/g, '');
            valorStr = valorStr.replace(',', '.');
            return parseFloat(valorStr) || 0;
        }

        // Formatar data
        function formatarData(data) {
            if (!data) return null;
            
            let dataStr = String(data).trim();
            
            // Se já estiver em formato MySQL (YYYY-MM-DD)
            if (/^\d{4}-\d{2}-\d{2}/.test(dataStr)) {
                return dataStr.substring(0, 10);
            }
            
            // Formato DD/MM/YYYY
            if (/^\d{2}\/\d{2}\/\d{4}/.test(dataStr)) {
                const partes = dataStr.split(/[\s\/]/);
                return `${partes[2]}-${partes[1]}-${partes[0]}`;
            }
            
            // Tentar converter como número (Excel usa números seriais)
            if (!isNaN(data)) {
                const dataExcel = new Date((data - 25569) * 86400 * 1000);
                const ano = dataExcel.getFullYear();
                const mes = String(dataExcel.getMonth() + 1).padStart(2, '0');
                const dia = String(dataExcel.getDate()).padStart(2, '0');
                return `${ano}-${mes}-${dia}`;
            }
            
            return null;
        }

        // Normalizar status
        function normalizarStatus(status) {
            if (!status) return 'pendente';
            const statusStr = String(status).toLowerCase().trim();
            
            if (statusStr.includes('pag')) return 'pago';
            if (statusStr.includes('pend')) return 'pendente';
            if (statusStr.includes('venc')) return 'vencido';
            if (statusStr.includes('cancel')) return 'cancelado';
            
            return 'pendente';
        }

        // Verificar quais clientes existem no banco
        async function verificarClientesExistentes() {
            try {
                console.log('🔍 Verificando existência de clientes/contratos...');
                
                // Extrair MATRÍCULA e CONJUNTO únicos (NOVA LÓGICA)
                const documentosParaVerificar = dadosImportacao.map(doc => ({
                    matricula: doc.matricula,
                    conjunto: doc.conjunto,
                    numero_documento: doc.numero_documento,
                    data_emissao: doc.data_emissao,
                    data_vencimento: doc.data_vencimento
                }));
                
                console.log('📋 Documentos para verificar:', documentosParaVerificar);
                
                // Fazer requisição para verificar
                const response = await fetch('api/verificar_clientes_contratos.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json; charset=utf-8'
                    },
                    body: JSON.stringify({ documentos: documentosParaVerificar })
                });
                
                const resultado = await response.json();
                console.log('✅ Resultado da verificação:', resultado);
                
                if (resultado.success) {
                    // Atualizar dados com status do cliente/contrato
                    dadosImportacao.forEach(doc => {
                        const chave = `${doc.matricula}_${doc.conjunto}`;
                        const verificacao = resultado.clientes[chave];
                        
                        doc.clienteExiste = verificacao?.existe || false;
                        doc.dadosCliente = verificacao?.dados || null;
                        doc.contratoExiste = verificacao?.contrato_existe || false;
                        doc.documentoDuplicado = verificacao?.documento_duplicado || false;
                        
                        console.log(`Cliente MATRÍCULA ${doc.matricula} + CONJUNTO ${doc.conjunto}: ${doc.clienteExiste ? 'ENCONTRADO' : 'NÃO ENCONTRADO'}`);
                    });
                }
                
                // Mostrar preview com os dados atualizados
                mostrarPreview();
                
            } catch (error) {
                console.error('Erro ao verificar clientes:', error);
                // Continuar mesmo com erro
                mostrarPreview();
            }
        }

        // Mostrar preview dos dados
        function mostrarPreview() {
            const tbody = document.getElementById('corpoTabelaPreview');
            tbody.innerHTML = '';

            // Mostrar no máximo 5 linhas no preview
            const linhasPreview = dadosImportacao.slice(0, 5);

            linhasPreview.forEach(doc => {
                const tr = document.createElement('tr');
                
                // Destacar linha conforme situação
                let statusValidacao = '';
                let corFundo = '';
                let titulo = '';
                
                if (doc.documentoDuplicado) {
                    corFundo = '#e2e3e5'; // Cinza - duplicado
                    titulo = '⏭️ Documento duplicado - será pulado';
                    statusValidacao = '<i class="fas fa-copy text-secondary" title="Duplicado"></i>';
                } else if (doc.clienteExiste === false) {
                    corFundo = '#fff3cd'; // Amarelo - quarentena
                    titulo = '⚠️ Cliente não encontrado - irá para Quarentena';
                    statusValidacao = '<i class="fas fa-exclamation-triangle text-warning" title="Cliente não encontrado"></i>';
                } else {
                    corFundo = '#d4edda'; // Verde - OK
                    titulo = '✅ Cliente encontrado - será importado';
                    statusValidacao = '<i class="fas fa-check-circle text-success" title="Cliente encontrado"></i>';
                }
                
                tr.style.backgroundColor = corFundo;
                tr.title = titulo;
                
                const statusClass = doc.status === 'pago' ? 'success' : doc.status === 'pendente' ? 'warning' : 'danger';
                
                tr.innerHTML = `
                    <td><strong>${doc.matricula || '-'}</strong>${doc.clienteExiste === false ? ' <i class="fas fa-exclamation-triangle text-warning"></i>' : ''}</td>
                    <td><strong>${doc.conjunto || '-'}</strong></td>
                    <td>${doc.numero_documento}</td>
                    <td>${doc.associado}</td>
                    <td>${doc.classe}</td>
                    <td>${doc.data_emissao || '-'}</td>
                    <td>${doc.data_vencimento || '-'}</td>
                    <td>R$ ${parseFloat(doc.valor).toFixed(2)}</td>
                    <td>${doc.placa}</td>
                    <td><span class="badge bg-${statusClass}">${doc.status}</span></td>
                    <td>R$ ${parseFloat(doc.valor_pago).toFixed(2)}</td>
                    <td>${doc.data_baixa || '-'}</td>
                    <td>${statusValidacao}</td>
                `;
                
                tbody.appendChild(tr);
            });

            // Contar clientes não encontrados
            const clientesNaoEncontrados = dadosImportacao.filter(doc => doc.clienteExiste === false).length;
            
            // Mostrar resumo
            const totalValor = dadosImportacao.reduce((sum, doc) => sum + parseFloat(doc.valor), 0);
            const totalPago = dadosImportacao.reduce((sum, doc) => sum + parseFloat(doc.valor_pago), 0);
            
            // Contar documentos por situação
            const documentosDuplicados = dadosImportacao.filter(doc => doc.documentoDuplicado).length;
            const documentosQuarentena = dadosImportacao.filter(doc => doc.clienteExiste === false && !doc.documentoDuplicado).length;
            const documentosOK = dadosImportacao.filter(doc => doc.clienteExiste === true && !doc.documentoDuplicado).length;
            
            let resumoHTML = `
                <strong><i class="fas fa-chart-line me-2"></i>Resumo da Importação:</strong><br>
                📋 Total de documentos: <strong>${dadosImportacao.length}</strong><br>
                ✅ Serão importados: <strong>${documentosOK}</strong> (cliente encontrado)<br>
                ⏭️ Duplicados (pulados): <strong>${documentosDuplicados}</strong><br>
                ⚠️ Quarentena: <strong>${documentosQuarentena}</strong> (cliente não encontrado)<br>
                <hr>
                💰 Valor total: <strong>R$ ${totalValor.toFixed(2).replace('.', ',')}</strong><br>
                ✅ Valor pago: <strong>R$ ${totalPago.toFixed(2).replace('.', ',')}</strong><br>
                ⏳ Valor pendente: <strong>R$ ${(totalValor - totalPago).toFixed(2).replace('.', ',')}</strong>
            `;
            
            if (documentosQuarentena > 0) {
                resumoHTML += `<br><hr><div class="text-warning">
                    <strong><i class="fas fa-exclamation-triangle me-2"></i>Atenção:</strong><br>
                    ⚠️ <strong>${documentosQuarentena}</strong> documento(s) com cliente/contrato não encontrado<br>
                    <small>Estes irão para <strong>"Quarentena Financeira"</strong> para futura correção</small><br>
                    <small class="text-muted">Certifique-se de que os clientes possuem MATRÍCULA e CONTRATOS com CONJUNTO cadastrados</small>
                </div>`;
            }
            
            if (documentosDuplicados > 0) {
                resumoHTML += `<br><div class="text-secondary">
                    <strong><i class="fas fa-copy me-2"></i>Duplicados:</strong><br>
                    ⏭️ <strong>${documentosDuplicados}</strong> documento(s) já existem no sistema<br>
                    <small>Estes serão pulados automaticamente (mesmo N° DOC + Emissão + Vencimento)</small>
                </div>`;
            }
            
            document.getElementById('resumoImportacao').innerHTML = resumoHTML;

            document.getElementById('previewDados').style.display = 'block';
            document.getElementById('btnImportar').style.display = 'inline-block';
        }

        // Importar documentos para o banco
        async function importarDocumentos() {
            if (dadosImportacao.length === 0) {
                alert('Nenhum dado para importar!');
                return;
            }

            // Confirmar importação
            if (!confirm(`Deseja importar ${dadosImportacao.length} documento(s) financeiro(s)?`)) {
                return;
            }

            // Mostrar progresso
            document.getElementById('areaProgresso').style.display = 'block';
            document.getElementById('btnImportar').disabled = true;
            document.getElementById('previewDados').style.display = 'none';

            let totalImportados = 0;
            let totalPulados = 0;
            let totalEmQuarentena = 0;
            let todosErros = [];

            // Processar em lotes
            const tamanhoLote = 10;
            for (let i = 0; i < dadosImportacao.length; i += tamanhoLote) {
                const lote = dadosImportacao.slice(i, i + tamanhoLote);
                
                try {
                    const response = await fetch('api/importar_documentos.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json; charset=utf-8'
                        },
                        body: JSON.stringify({ 
                            documentos: lote,
                            nome_arquivo: nomeArquivoAtual,
                            eh_primeiro_lote: i === 0,
                            eh_ultimo_lote: (i + tamanhoLote) >= dadosImportacao.length,
                            total_documentos: dadosImportacao.length
                        })
                    });

                    // Verificar se a resposta é OK
                    if (!response.ok) {
                        throw new Error(`Erro HTTP: ${response.status}`);
                    }

                    const texto = await response.text();
                    
                    // Tentar parsear JSON
                    let resultado;
                    try {
                        resultado = JSON.parse(texto);
                    } catch (e) {
                        console.error('Resposta não é JSON:', texto);
                        throw new Error('Resposta inválida do servidor');
                    }
                    
                    if (resultado.success) {
                        totalImportados += resultado.importados || 0;
                        totalPulados += resultado.pulados || 0;
                        totalEmQuarentena += resultado.emQuarentena || 0;
                        if (resultado.erros && resultado.erros.length > 0) {
                            todosErros = todosErros.concat(resultado.erros);
                        }
                    } else {
                        throw new Error(resultado.error || 'Erro desconhecido');
                    }

                } catch (error) {
                    console.error('Erro ao importar lote:', error);
                    todosErros.push(`Erro no lote: ${error.message}`);
                }

                // Atualizar progresso
                const progresso = Math.round(((i + lote.length) / dadosImportacao.length) * 100);
                document.getElementById('barraProgresso').style.width = progresso + '%';
                document.getElementById('barraProgresso').textContent = progresso + '%';
                document.getElementById('textoProgresso').textContent = 
                    `Processando... ${i + lote.length} de ${dadosImportacao.length}`;
            }

            // Ocultar progresso
            document.getElementById('areaProgresso').style.display = 'none';

            // Mostrar resultado detalhado
            let resultadoHTML = '';
            
            if (totalImportados > 0 || totalPulados > 0) {
                resultadoHTML += `
                    <div class="alert alert-success">
                        <h5 class="alert-heading"><i class="fas fa-check-circle me-2"></i>Importação Concluída!</h5>
                        <hr>
                        <p class="mb-1">✅ <strong>${totalImportados}</strong> documento(s) importado(s) com sucesso</p>
                        ${totalPulados > 0 ? `<p class="mb-1">⏭️ <strong>${totalPulados}</strong> documento(s) duplicado(s) pulado(s)</p>` : ''}
                        ${totalEmQuarentena > 0 ? `<p class="mb-1">⚠️ <strong>${totalEmQuarentena}</strong> documento(s) em <strong>Quarentena Financeira</strong> (cliente não encontrado)</p>` : ''}
                        <p class="mb-0">📊 <strong>${dadosImportacao.length}</strong> documento(s) processado(s) no total</p>
                    </div>
                `;
            }
            
            if (todosErros.length > 0) {
                resultadoHTML += `
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Atenção - ${todosErros.length} documento(s) com problema:</h6>
                        <div class="mt-2" style="max-height: 200px; overflow-y: auto; font-size: 0.9em;">
                            ${todosErros.slice(0, 10).map(erro => `<div>• ${erro}</div>`).join('')}
                            ${todosErros.length > 10 ? `<div class="mt-2"><em>... e mais ${todosErros.length - 10} erro(s)</em></div>` : ''}
                        </div>
                    </div>
                `;
            }
            
            if (totalImportados === 0 && todosErros.length === 0) {
                resultadoHTML += `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Nenhum documento foi importado. Todos já existem no sistema.
                    </div>
                `;
            }

            document.getElementById('resultadoImportacao').innerHTML = resultadoHTML;
            document.getElementById('resultadoImportacao').style.display = 'block';

            // Reabilitar botão
            document.getElementById('btnImportar').disabled = false;

            // Se teve sucesso, fechar modal e recarregar
            if (totalImportados > 0) {
                setTimeout(() => {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('modalImportarRetorno'));
                    if (modal) modal.hide();
                    location.reload(); // Recarregar para mostrar novos dados
                }, 3000);
            }
        }

        // Limpar modal ao fechar
        document.getElementById('modalImportarRetorno').addEventListener('hidden.bs.modal', function() {
            document.getElementById('arquivoRetorno').value = '';
            document.getElementById('previewDados').style.display = 'none';
            document.getElementById('btnProcessarPreview').style.display = 'none';
            document.getElementById('btnImportar').style.display = 'none';
            document.getElementById('areaProgresso').style.display = 'none';
            document.getElementById('resultadoImportacao').style.display = 'none';
            dadosImportacao = [];
        });

        // ========================================
        // PAGINAÇÃO DE DOCUMENTOS
        // ========================================

        let paginaAtualDoc = 1;
        let porPaginaDoc = 10;
        let totalDocumentos = 0;

        // Carregar documentos ao iniciar
        document.addEventListener('DOMContentLoaded', function() {
            carregarDocumentos();
            
            // Listener para mudança de registros por página
            document.getElementById('documentosPorPagina').addEventListener('change', function() {
                porPaginaDoc = parseInt(this.value);
                paginaAtualDoc = 1;
                carregarDocumentos();
            });
        });

        async function carregarDocumentos() {
            const tbody = document.getElementById('tabelaTransacoes');
            tbody.innerHTML = '<tr><td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin"></i> Carregando...</td></tr>';

            try {
                const response = await fetch(`api/listar_documentos.php?pagina=${paginaAtualDoc}&por_pagina=${porPaginaDoc}`);
                const data = await response.json();

                if (data.sucesso && data.documentos.length > 0) {
                    totalDocumentos = data.paginacao.total;
                    renderizarDocumentos(data.documentos);
                    renderizarPaginacaoDocumentos(data.paginacao);
                    atualizarInfoDocumentos(data.paginacao);
                } else {
                    tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">
                        <i class="fas fa-inbox me-2"></i>
                        Nenhum documento financeiro encontrado.
                    </td></tr>`;
                    document.getElementById('paginacaoDocumentos').innerHTML = '';
                    document.getElementById('infoTotalDoc').textContent = '0';
                }
            } catch (error) {
                console.error('Erro ao carregar documentos:', error);
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Erro ao carregar documentos</td></tr>';
            }
        }

        function renderizarDocumentos(documentos) {
            const tbody = document.getElementById('tabelaTransacoes');
            let html = '';

            documentos.forEach(doc => {
                const badgeClass = {
                    'pago': 'bg-success',
                    'pendente': 'bg-warning',
                    'vencido': 'bg-danger',
                    'cancelado': 'bg-secondary'
                }[doc.status] || 'bg-secondary';

                const quarentenaIcon = doc.cliente_nao_encontrado === 'sim' ? 
                    '<i class="fas fa-exclamation-triangle text-warning ms-1" title="Em Quarentena"></i>' : '';

                html += `
                    <tr>
                        <td><strong>#${doc.numero_documento}</strong></td>
                        <td>${doc.data_vencimento}</td>
                        <td>${doc.associado} ${quarentenaIcon}</td>
                        <td>${doc.classe || '-'}</td>
                        <td>${doc.placa || '-'}</td>
                        <td><strong>R$ ${parseFloat(doc.valor).toFixed(2).replace('.', ',')}</strong></td>
                        <td><span class="badge ${badgeClass}">${doc.status}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" title="Visualizar" onclick="verDocumento(${doc.id})">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        }

        function renderizarPaginacaoDocumentos(pag) {
            const container = document.getElementById('paginacaoDocumentos');
            let html = '';

            // Anterior
            html += `<li class="page-item ${pag.pagina_atual === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="irParaPaginaDoc(${pag.pagina_atual - 1}); return false;">Anterior</a>
            </li>`;

            // Páginas
            const maxBotoes = 5;
            let inicio = Math.max(1, pag.pagina_atual - 2);
            let fim = Math.min(pag.total_paginas, inicio + maxBotoes - 1);

            if (fim - inicio < maxBotoes - 1) {
                inicio = Math.max(1, fim - maxBotoes + 1);
            }

            for (let i = inicio; i <= fim; i++) {
                html += `<li class="page-item ${i === pag.pagina_atual ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="irParaPaginaDoc(${i}); return false;">${i}</a>
                </li>`;
            }

            // Próxima
            html += `<li class="page-item ${pag.pagina_atual === pag.total_paginas ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="irParaPaginaDoc(${pag.pagina_atual + 1}); return false;">Próxima</a>
            </li>`;

            container.innerHTML = html;
        }

        function atualizarInfoDocumentos(pag) {
            document.getElementById('infoInicioDoc').textContent = pag.inicio;
            document.getElementById('infoFimDoc').textContent = pag.fim;
            document.getElementById('infoTotalDoc').textContent = pag.total;
        }

        function irParaPaginaDoc(pagina) {
            paginaAtualDoc = pagina;
            carregarDocumentos();
        }

        function verDocumento(id) {
            alert('Visualizar documento #' + id);
        }

        // ========================================
        // HISTÓRICO DE IMPORTAÇÕES
        // ========================================

        // Carregar histórico quando abrir o modal
        document.getElementById('modalHistoricoImportacoes').addEventListener('shown.bs.modal', function() {
            carregarHistoricoImportacoes();
        });

        async function carregarHistoricoImportacoes() {
            const tbody = document.getElementById('historicoImportacoesBody');
            tbody.innerHTML = '<tr><td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin"></i> Carregando...</td></tr>';

            try {
                const periodo = document.getElementById('filtroHistoricoPeriodo').value;
                const status = document.getElementById('filtroHistoricoStatus').value;

                const response = await fetch(`api/historico_importacoes.php?periodo=${periodo}&status=${status}`);
                const data = await response.json();

                if (data.sucesso && data.importacoes && data.importacoes.length > 0) {
                    let html = '';
                    
                    data.importacoes.forEach(imp => {
                        const statusBadge = imp.total_erros > 0 ? 
                            '<span class="badge bg-warning">Com Erros</span>' : 
                            '<span class="badge bg-success">Sucesso</span>';

                        const percentual = imp.total_registros > 0 ? 
                            Math.round((imp.processados / imp.total_registros) * 100) : 0;

                        html += `
                            <tr>
                                <td>${imp.data_hora_formatada}</td>
                                <td><i class="fas fa-file-csv me-1 text-success"></i>${imp.nome_arquivo}</td>
                                <td class="text-center">${imp.total_registros}</td>
                                <td class="text-center">
                                    ${imp.processados}
                                    <small class="text-muted">(${percentual}%)</small>
                                </td>
                                <td class="text-center">
                                    ${imp.total_erros > 0 ? 
                                        `<span class="badge bg-danger">${imp.total_erros}</span>` : 
                                        '<span class="text-muted">0</span>'}
                                </td>
                                <td>${imp.usuario_nome}</td>
                                <td>${statusBadge}</td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        ${imp.detalhes ? 
                                            `<button class="btn btn-outline-primary" onclick='verDetalhesImportacao(${JSON.stringify(imp)})' title="Ver Detalhes">
                                                <i class="fas fa-eye"></i>
                                            </button>` : ''}
                                        <button class="btn btn-outline-success" onclick="baixarImportacao(${imp.id || 0}, '${imp.nome_arquivo}')" title="Baixar CSV">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    });

                    tbody.innerHTML = html;
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="fas fa-inbox me-2"></i>
                                Nenhuma importação encontrada no período selecionado
                            </td>
                        </tr>
                    `;
                }
            } catch (error) {
                console.error('Erro ao carregar histórico:', error);
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center text-danger py-4">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Erro ao carregar histórico de importações
                        </td>
                    </tr>
                `;
            }
        }

        function verDetalhesImportacao(importacao) {
            const detalhes = importacao.detalhes ? JSON.parse(importacao.detalhes) : null;
            let mensagem = `Detalhes da Importação:\n\n`;
            mensagem += `Arquivo: ${importacao.nome_arquivo}\n`;
            mensagem += `Data: ${importacao.data_hora_formatada}\n`;
            mensagem += `Total: ${importacao.total_registros}\n`;
            mensagem += `Processados: ${importacao.processados}\n`;
            mensagem += `Erros: ${importacao.total_erros}\n`;
            
            if (detalhes && detalhes.erros && detalhes.erros.length > 0) {
                mensagem += `\nPrimeiros erros:\n`;
                detalhes.erros.slice(0, 5).forEach((erro, i) => {
                    mensagem += `${i + 1}. ${erro}\n`;
                });
            }
            
            alert(mensagem);
        }

        function baixarImportacao(id, nomeArquivo) {
            // Criar URL para download
            const url = `api/baixar_importacao.php?id=${id}&nome_arquivo=${encodeURIComponent(nomeArquivo)}`;
            
            // Abrir em nova aba para download
            window.open(url, '_blank');
        }
    </script>
</body>
</html>
