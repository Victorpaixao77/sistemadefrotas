<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Verificar se está logado
verificarLogin();

// Obter dados do usuário
$usuario = obterUsuarioLogado();
$empresa_id = obterEmpresaId();

// Obter conexão
$pdo = getDB();

// Buscar estatísticas reais de atendimentos
try {
    // Total de atendimentos abertos
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM seguro_atendimentos 
        WHERE seguro_empresa_id = ? AND status = 'aberto'
    ");
    $stmt->execute([$empresa_id]);
    $atendimentosAbertos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total em andamento
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM seguro_atendimentos 
        WHERE seguro_empresa_id = ? AND status = 'em_andamento'
    ");
    $stmt->execute([$empresa_id]);
    $atendimentosAndamento = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total fechados
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM seguro_atendimentos 
        WHERE seguro_empresa_id = ? AND status = 'fechado'
    ");
    $stmt->execute([$empresa_id]);
    $atendimentosFechados = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Tempo médio de resolução (em dias)
    $stmt = $pdo->prepare("
        SELECT AVG(DATEDIFF(data_fechamento, data_abertura)) as media_dias
        FROM seguro_atendimentos 
        WHERE seguro_empresa_id = ? AND status = 'fechado'
        AND data_fechamento IS NOT NULL
    ");
    $stmt->execute([$empresa_id]);
    $tempoMedio = $stmt->fetch(PDO::FETCH_ASSOC)['media_dias'] ?? 0;
    
} catch (Exception $e) {
    error_log("Erro ao carregar estatísticas de atendimento: " . $e->getMessage());
    $atendimentosAbertos = 0;
    $atendimentosAndamento = 0;
    $atendimentosFechados = 0;
    $tempoMedio = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Seguro - Atendimento</title>
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
        .support-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .support-card .card-body {
            padding: 2rem;
        }
        .support-icon {
            font-size: 3rem;
            opacity: 0.8;
        }
        .open-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        .pending-card {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
        }
        .closed-card {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
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
        .ticket-item {
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
        }
        .ticket-item.high {
            border-left-color: #dc3545;
        }
        .ticket-item.medium {
            border-left-color: #ffc107;
        }
        .ticket-item.low {
            border-left-color: #28a745;
        }
        .priority-high {
            background-color: #dc3545;
            color: white;
        }
        .priority-medium {
            background-color: #ffc107;
            color: #212529;
        }
        .priority-low {
            background-color: #28a745;
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
                <a class="nav-link active" href="atendimento.php">
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
                            <i class="fas fa-headset me-2"></i>
                            Central de Atendimento
                        </h2>
                        <p class="text-muted mb-0">Gestão de Chamados e Suporte</p>
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

        <!-- Support Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card support-card">
                    <div class="card-body text-center">
                        <i class="fas fa-headset support-icon"></i>
                        <h3 class="mt-3 mb-1"><?php echo $atendimentosAbertos + $atendimentosAndamento + $atendimentosFechados; ?></h3>
                        <p class="mb-0">Total de Atendimentos</p>
                        <small class="opacity-75">Todos os registros</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card open-card">
                    <div class="card-body text-center">
                        <i class="fas fa-folder-open support-icon"></i>
                        <h3 class="mt-3 mb-1"><?php echo $atendimentosAbertos; ?></h3>
                        <p class="mb-0">Abertos</p>
                        <small class="opacity-75">Aguardando atendimento</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card pending-card">
                    <div class="card-body text-center">
                        <i class="fas fa-tasks support-icon"></i>
                        <h3 class="mt-3 mb-1"><?php echo $atendimentosAndamento; ?></h3>
                        <p class="mb-0">Em Andamento</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card closed-card">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle support-icon"></i>
                        <h3 class="mt-3 mb-1"><?php echo $atendimentosFechados; ?></h3>
                        <p class="mb-0">Resolvidos</p>
                        <small class="opacity-75">Tempo médio: <?php echo number_format($tempoMedio, 1); ?> dias</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Tickets -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Chamados Recentes
                        </h5>
                    </div>
                    <div class="card-body" id="chamadosRecentesContainer">
                        <div class="text-center py-4">
                            <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                            <p class="text-muted mt-2">Carregando chamados recentes...</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie me-2"></i>
                            Estatísticas
                        </h5>
                    </div>
                    <div class="card-body" id="estatisticasContainer">
                        <div class="text-center py-4">
                            <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                            <p class="text-muted mt-2">Carregando estatísticas...</p>
                            </div>
                            </div>
                        </div>
            </div>
        </div>

        <!-- Search and Actions -->
        <div class="search-container">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control" placeholder="Buscar chamado..." id="ticketSearch">
                    </div>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="statusFilter">
                        <option value="">Todos os Status</option>
                        <option value="aberto">Aberto</option>
                        <option value="em_andamento">Em Andamento</option>
                        <option value="aguardando">Aguardando</option>
                        <option value="resolvido">Resolvido</option>
                        <option value="fechado">Fechado</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="priorityFilter">
                        <option value="">Todas as Prioridades</option>
                        <option value="urgente">Urgente</option>
                        <option value="alta">Alta</option>
                        <option value="media">Média</option>
                        <option value="baixa">Baixa</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="categoryFilter">
                        <option value="">Todas as Categorias</option>
                        <option value="sinistro">Sinistro</option>
                        <option value="cobertura">Cobertura</option>
                        <option value="pagamento">Pagamento</option>
                        <option value="relatorio">Relatório</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="d-flex gap-2 justify-content-end">
                        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#newTicketModal">
                            <i class="fas fa-plus me-1"></i>
                            Novo Chamado
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tickets Table -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="8%">ID</th>
                            <th width="12%">Data</th>
                            <th width="20%">Cliente</th>
                            <th width="25%">Assunto</th>
                            <th width="12%">Categoria</th>
                            <th width="8%">Prioridade</th>
                            <th width="8%">Status</th>
                            <th width="7%">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaHistoricoAtendimentos">
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="fas fa-spinner fa-spin me-2"></i>
                                Carregando atendimentos...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginação e Controles -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div>
                    <span class="text-muted">Mostrando <strong id="infoInicioAtend">0</strong> a <strong id="infoFimAtend">0</strong> de <strong id="infoTotalAtend">0</strong> atendimentos</span>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <select class="form-select form-select-sm" id="atendimentosPorPagina" style="width: 120px;">
                        <option value="10" selected>10 / página</option>
                        <option value="20">20 / página</option>
                        <option value="50">50 / página</option>
                    </select>
                    <nav>
                        <ul class="pagination pagination-sm mb-0" id="paginacaoAtendimentos">
                            <!-- Gerado via JavaScript -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Novo Chamado -->
    <div class="modal fade" id="newTicketModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>
                        Novo Chamado
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="newTicketForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="client" class="form-label">Cliente <span class="text-danger">*</span></label>
                                <select class="form-select" id="client" required>
                                    <option value="">Selecione o cliente...</option>
                                    <!-- Clientes serão carregados dinamicamente via JavaScript -->
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">Tipo de Atendimento <span class="text-danger">*</span></label>
                                <select class="form-select" id="category" required>
                                    <option value="">Selecione...</option>
                                    <option value="suporte">Suporte</option>
                                    <option value="reclamacao">Reclamação</option>
                                    <option value="duvida">Dúvida</option>
                                    <option value="venda">Venda</option>
                                    <option value="acompanhamento">Acompanhamento</option>
                                    <option value="outros">Outros</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="priority" class="form-label">Prioridade <span class="text-danger">*</span></label>
                                <select class="form-select" id="priority" required>
                                    <option value="baixa">Baixa</option>
                                    <option value="media" selected>Média</option>
                                    <option value="alta">Alta</option>
                                    <option value="urgente">Urgente</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" disabled>
                                    <option value="aberto" selected>Aberto</option>
                                </select>
                                <small class="text-muted">Novos chamados sempre iniciam como "Aberto"</small>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="subject" class="form-label">Assunto</label>
                            <input type="text" class="form-control" id="subject" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Descrição</label>
                            <textarea class="form-control" id="description" rows="4" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="attachments" class="form-label">Anexos</label>
                            <input type="file" class="form-control" id="attachments" multiple>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary-custom" onclick="saveTicket()">Criar Chamado</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detalhes do Atendimento -->
    <div class="modal fade" id="modalDetalhesAtendimento" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Detalhes do Atendimento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="conteudoDetalhesAtendimento">
                    <!-- Será preenchido via JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-warning" onclick="abrirEditarAtendimentoModal()">
                        <i class="fas fa-edit me-1"></i>
                        Editar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar Atendimento -->
    <div class="modal fade" id="modalEditarAtendimento" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar / Finalizar Atendimento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formEditarAtendimento">
                        <input type="hidden" id="editarAtendimentoId">
                        
                        <!-- Informações do Atendimento -->
                        <div class="alert alert-info" id="infoAtendimentoEditar">
                            <!-- Será preenchido via JavaScript -->
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="editarTipo" class="form-label">Tipo</label>
                                <select class="form-select" id="editarTipo">
                                    <option value="suporte">Suporte</option>
                                    <option value="reclamacao">Reclamação</option>
                                    <option value="duvida">Dúvida</option>
                                    <option value="venda">Venda</option>
                                    <option value="acompanhamento">Acompanhamento</option>
                                    <option value="outros">Outros</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="editarPrioridade" class="form-label">Prioridade</label>
                                <select class="form-select" id="editarPrioridade">
                                    <option value="baixa">Baixa</option>
                                    <option value="media">Média</option>
                                    <option value="alta">Alta</option>
                                    <option value="urgente">Urgente</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="editarStatus" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="editarStatus" required>
                                    <option value="aberto">Aberto</option>
                                    <option value="em_andamento">Em Andamento</option>
                                    <option value="aguardando">Aguardando</option>
                                    <option value="resolvido">Resolvido</option>
                                    <option value="fechado">Fechado</option>
                                    <option value="cancelado">Cancelado</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editarAssunto" class="form-label">Assunto</label>
                            <input type="text" class="form-control" id="editarAssunto" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editarDescricao" class="form-label">Descrição Original</label>
                            <textarea class="form-control" id="editarDescricao" rows="3" readonly></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editarSolucao" class="form-label">Solução / Resposta</label>
                            <textarea class="form-control" id="editarSolucao" rows="5" placeholder="Descreva a solução ou resposta para este atendimento..."></textarea>
                            <small class="text-muted">Este campo será visível para o cliente</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarEdicaoAtendimento()">
                        <i class="fas fa-save me-1"></i>
                        Salvar Alterações
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/menu-responsivo.js"></script>
    <script src="js/temas.js"></script>
    <script>
        console.log('=== SISTEMA SEGURO - ATENDIMENTO - INICIALIZADO ===');
        
        // ===== CARREGAR CLIENTES =====
        let todosClientes = [];
        
        async function carregarClientes() {
            try {
                console.log('Carregando clientes...');
                const response = await fetch('api/listar_clientes.php?situacao=ativo');
                const data = await response.json();
                
                if (data.sucesso) {
                    todosClientes = data.clientes;
                    console.log('Clientes carregados:', todosClientes.length);
                    
                    // Preencher select de clientes
                    const selectCliente = document.getElementById('client');
                    selectCliente.innerHTML = '<option value="">Selecione o cliente...</option>';
                    
                    todosClientes.forEach(cliente => {
                        const option = document.createElement('option');
                        option.value = cliente.id;
                        option.textContent = `${cliente.codigo} - ${cliente.nome_razao_social}`;
                        selectCliente.appendChild(option);
                    });
                } else {
                    console.error('Erro ao carregar clientes:', data.mensagem);
                }
            } catch (error) {
                console.error('Erro na requisição de clientes:', error);
            }
        }
        
        // ===== CARREGAR ATENDIMENTOS =====
        let todosAtendimentos = [];
        
        let paginaAtualAtend = 1;
        let porPaginaAtend = 10;

        async function carregarAtendimentos() {
            try {
                console.log('Carregando atendimentos...');
                const response = await fetch(`api/atendimentos.php?pagina=${paginaAtualAtend}&por_pagina=${porPaginaAtend}`);
                const data = await response.json();
                
                if (data.success) {
                    todosAtendimentos = data.atendimentos;
                    console.log('Atendimentos carregados:', todosAtendimentos.length);
                    renderizarTabelaAtendimentos();
                    renderizarChamadosRecentes();
                    renderizarEstatisticas();
                    
                    // Atualizar info e paginação
                    if (data.paginacao) {
                        atualizarInfoAtendimentos(data.paginacao);
                        renderizarPaginacaoAtendimentos(data.paginacao);
                    }
                } else {
                    console.error('Erro ao carregar atendimentos');
                }
            } catch (error) {
                console.error('Erro na requisição de atendimentos:', error);
            }
        }
        
        // ===== RENDERIZAR CHAMADOS RECENTES =====
        function renderizarChamadosRecentes() {
            const container = document.getElementById('chamadosRecentesContainer');
            
            if (!todosAtendimentos || todosAtendimentos.length === 0) {
                container.innerHTML = '<div class="text-center py-4"><i class="fas fa-inbox me-2"></i>Nenhum chamado encontrado</div>';
                return;
            }
            
            // Pegar os 2 atendimentos mais recentes
            const recentes = todosAtendimentos.slice(0, 2);
            
            let html = '';
            
            recentes.forEach(atend => {
                const prioridadeClass = {
                    'baixa': 'low',
                    'media': 'medium',
                    'alta': 'high',
                    'urgente': 'high'
                };
                
                const prioridadeBadge = {
                    'baixa': 'priority-low',
                    'media': 'priority-medium',
                    'alta': 'priority-high',
                    'urgente': 'priority-high'
                };
                
                const statusTexto = {
                    'aberto': 'Aberto',
                    'em_andamento': 'Em Andamento',
                    'aguardando': 'Aguardando',
                    'resolvido': 'Resolvido',
                    'fechado': 'Fechado',
                    'cancelado': 'Cancelado'
                };
                
                html += `
                    <div class="ticket-item ${prioridadeClass[atend.prioridade] || 'medium'}" onclick="visualizarAtendimento(${atend.id})" style="cursor: pointer;">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">#${atend.id} - ${atend.assunto || atend.titulo || 'Sem assunto'}</h6>
                                <p class="mb-1 text-muted">Cliente: ${atend.cliente_nome || 'Não identificado'}</p>
                                <small class="text-muted">Criado em: ${atend.data_abertura_fmt || '-'}</small>
                            </div>
                            <div class="text-end">
                                <span class="badge ${prioridadeBadge[atend.prioridade] || 'priority-medium'}">${atend.prioridade ? atend.prioridade.charAt(0).toUpperCase() + atend.prioridade.slice(1) : 'Média'}</span>
                                <br>
                                <small class="text-muted">${statusTexto[atend.status] || atend.status}</small>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            if (html === '') {
                container.innerHTML = '<div class="text-center py-4"><i class="fas fa-inbox me-2"></i>Nenhum chamado recente</div>';
            } else {
                container.innerHTML = html;
            }
        }
        
        // ===== RENDERIZAR ESTATÍSTICAS =====
        function renderizarEstatisticas() {
            const container = document.getElementById('estatisticasContainer');
            
            if (!todosAtendimentos || todosAtendimentos.length === 0) {
                container.innerHTML = '<div class="text-center py-4"><i class="fas fa-inbox me-2"></i>Nenhum dado disponível</div>';
                return;
            }
            
            // Calcular estatísticas
            const total = todosAtendimentos.length;
            const resolvidos = todosAtendimentos.filter(a => a.status === 'resolvido' || a.status === 'fechado').length;
            const emAndamento = todosAtendimentos.filter(a => a.status === 'em_andamento').length;
            const aguardando = todosAtendimentos.filter(a => a.status === 'aguardando').length;
            const abertos = todosAtendimentos.filter(a => a.status === 'aberto').length;
            
            const percentResolvidos = total > 0 ? Math.round((resolvidos / total) * 100) : 0;
            const percentAndamento = total > 0 ? Math.round((emAndamento / total) * 100) : 0;
            const percentAguardando = total > 0 ? Math.round(((aguardando + abertos) / total) * 100) : 0;
            
            // Calcular tempo médio de resolução (apenas atendimentos resolvidos/fechados com data de fechamento)
            let tempoMedio = 0;
            let countResolvidos = 0;
            
            todosAtendimentos.forEach(atend => {
                if ((atend.status === 'resolvido' || atend.status === 'fechado') && atend.data_fechamento) {
                    // Calcular diferença em horas
                    const abertura = new Date(atend.data_abertura);
                    const fechamento = new Date(atend.data_fechamento);
                    const diffHoras = Math.abs(fechamento - abertura) / 36e5; // Converte milissegundos para horas
                    tempoMedio += diffHoras;
                    countResolvidos++;
                }
            });
            
            tempoMedio = countResolvidos > 0 ? tempoMedio / countResolvidos : 0;
            const horas = Math.floor(tempoMedio);
            const minutos = Math.round((tempoMedio - horas) * 60);
            
            const html = `
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Resolvidos/Fechados</span>
                        <span><strong>${resolvidos}</strong> (${percentResolvidos}%)</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-success" style="width: ${percentResolvidos}%"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Em Andamento</span>
                        <span><strong>${emAndamento}</strong> (${percentAndamento}%)</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-warning" style="width: ${percentAndamento}%"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Aguardando/Abertos</span>
                        <span><strong>${aguardando + abertos}</strong> (${percentAguardando}%)</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-danger" style="width: ${percentAguardando}%"></div>
                    </div>
                </div>
                <hr>
                <div class="text-center">
                    <h6>Tempo Médio de Resolução</h6>
                    <h4 class="text-primary">${countResolvidos > 0 ? `${horas}h ${minutos}min` : 'N/A'}</h4>
                    ${countResolvidos > 0 ? `<small class="text-muted">Baseado em ${countResolvidos} atendimento(s)</small>` : '<small class="text-muted">Nenhum atendimento finalizado</small>'}
                </div>
                <hr>
                <div class="text-center">
                    <small class="text-muted">Total de Atendimentos: <strong>${total}</strong></small>
                </div>
            `;
            
            container.innerHTML = html;
        }
        
        // ===== RENDERIZAR TABELA DE ATENDIMENTOS =====
        function renderizarTabelaAtendimentos() {
            const tbody = document.getElementById('tabelaHistoricoAtendimentos');
            
            if (!todosAtendimentos || todosAtendimentos.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4"><i class="fas fa-inbox me-2"></i>Nenhum atendimento encontrado</td></tr>';
                return;
            }
            
            let html = '';
            
            todosAtendimentos.forEach(atend => {
                const prioridadeClass = {
                    'baixa': 'priority-low',
                    'media': 'priority-medium',
                    'alta': 'priority-high',
                    'urgente': 'priority-high'
                };
                
                const statusClass = {
                    'aberto': 'bg-success',
                    'em_andamento': 'bg-warning',
                    'aguardando': 'bg-info',
                    'resolvido': 'bg-success',
                    'fechado': 'bg-secondary',
                    'cancelado': 'bg-danger'
                };
                
                const statusText = {
                    'aberto': 'Em Aberto',
                    'em_andamento': 'Em Andamento',
                    'aguardando': 'Aguardando',
                    'resolvido': 'Resolvido',
                    'fechado': 'Fechado',
                    'cancelado': 'Cancelado'
                };
                
                // Gerar cor aleatória para o avatar (baseado no ID)
                const colors = ['primary', 'success', 'warning', 'info', 'danger'];
                const colorIndex = atend.id % colors.length;
                const avatarColor = colors[colorIndex];
                
                html += `
                    <tr>
                        <td>#${atend.id}</td>
                        <td>${atend.data_abertura_fmt || '-'}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-${avatarColor} rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 30px; height: 30px;">
                                    <i class="fas fa-user text-white"></i>
                                </div>
                                ${atend.cliente_nome || 'Cliente não identificado'}
                            </div>
                        </td>
                        <td>${atend.assunto || atend.titulo || '-'}</td>
                        <td>${atend.tipo || '-'}</td>
                        <td><span class="badge ${prioridadeClass[atend.prioridade] || 'priority-medium'}">${atend.prioridade || 'média'}</span></td>
                        <td><span class="badge ${statusClass[atend.status] || 'bg-secondary'}">${statusText[atend.status] || atend.status}</span></td>
                        <td>
                            <div class="btn-group" role="group">
                                <button class="btn btn-sm btn-outline-primary" title="Visualizar" onclick="visualizarAtendimento(${atend.id})">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-warning" title="Editar" onclick="editarAtendimento(${atend.id})">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }
        
        // ===== VISUALIZAR ATENDIMENTO =====
        let atendimentoAtual = null;
        
        function visualizarAtendimento(id) {
            const atendimento = todosAtendimentos.find(a => a.id == id);
            if (!atendimento) {
                alert('Atendimento não encontrado!');
                return;
            }
            
            atendimentoAtual = atendimento;
            mostrarDetalhesAtendimento(atendimento);
        }
        
        function mostrarDetalhesAtendimento(atend) {
            const statusTexto = {
                'aberto': 'Aberto',
                'em_andamento': 'Em Andamento',
                'aguardando': 'Aguardando',
                'resolvido': 'Resolvido',
                'fechado': 'Fechado',
                'cancelado': 'Cancelado'
            };
            
            const statusClass = {
                'aberto': 'success',
                'em_andamento': 'primary',
                'aguardando': 'warning',
                'resolvido': 'success',
                'fechado': 'secondary',
                'cancelado': 'danger'
            };
            
            const prioridadeClass = {
                'baixa': 'info',
                'media': 'warning',
                'alta': 'danger',
                'urgente': 'danger'
            };
            
            const conteudo = `
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informações do Atendimento #${atend.id}</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="30%">Protocolo:</th>
                                        <td><strong>${atend.protocolo || 'AT-' + atend.id}</strong></td>
                                    </tr>
                                    <tr>
                                        <th>Cliente:</th>
                                        <td>${atend.cliente_nome || 'Não identificado'}</td>
                                    </tr>
                                    <tr>
                                        <th>Data Abertura:</th>
                                        <td>${atend.data_abertura_fmt || '-'}</td>
                                    </tr>
                                    <tr>
                                        <th>Data Fechamento:</th>
                                        <td>${atend.data_fechamento_fmt || 'Ainda não finalizado'}</td>
                                    </tr>
                                    <tr>
                                        <th>Tipo:</th>
                                        <td><span class="badge bg-info">${atend.tipo || '-'}</span></td>
                                    </tr>
                                    <tr>
                                        <th>Prioridade:</th>
                                        <td><span class="badge bg-${prioridadeClass[atend.prioridade] || 'secondary'}">${atend.prioridade || 'média'}</span></td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td><span class="badge bg-${statusClass[atend.status] || 'secondary'}">${statusTexto[atend.status] || atend.status}</span></td>
                                    </tr>
                                    <tr>
                                        <th>Responsável:</th>
                                        <td>${atend.usuario_nome || '-'}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-12 mb-3">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-file-alt me-2"></i>Assunto</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-0"><strong>${atend.assunto || atend.titulo || 'Sem assunto'}</strong></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-12 mb-3">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-comment-alt me-2"></i>Descrição</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-0" style="white-space: pre-wrap;">${atend.descricao || 'Sem descrição'}</p>
                            </div>
                        </div>
                    </div>
                    
                    ${atend.solucao || atend.resposta ? `
                    <div class="col-md-12 mb-3">
                        <div class="card border-success">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0"><i class="fas fa-check-circle me-2"></i>Solução / Resposta</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-0" style="white-space: pre-wrap;">${atend.solucao || atend.resposta}</p>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('conteudoDetalhesAtendimento').innerHTML = conteudo;
            new bootstrap.Modal(document.getElementById('modalDetalhesAtendimento')).show();
        }
        
        // ===== EDITAR ATENDIMENTO =====
        function editarAtendimento(id) {
            const atendimento = todosAtendimentos.find(a => a.id == id);
            if (!atendimento) {
                alert('Atendimento não encontrado!');
                return;
            }
            
            atendimentoAtual = atendimento;
            abrirModalEdicao();
        }
        
        function abrirEditarAtendimentoModal() {
            abrirModalEdicao();
        }
        
        function abrirModalEdicao() {
            if (!atendimentoAtual) {
                alert('Nenhum atendimento selecionado!');
                return;
            }
            
            // Fechar modal de detalhes se estiver aberto
            const modalDetalhes = bootstrap.Modal.getInstance(document.getElementById('modalDetalhesAtendimento'));
            if (modalDetalhes) {
                modalDetalhes.hide();
            }
            
            // Preencher formulário de edição
            document.getElementById('editarAtendimentoId').value = atendimentoAtual.id;
            document.getElementById('editarTipo').value = atendimentoAtual.tipo || 'suporte';
            document.getElementById('editarPrioridade').value = atendimentoAtual.prioridade || 'media';
            document.getElementById('editarStatus').value = atendimentoAtual.status || 'aberto';
            document.getElementById('editarAssunto').value = atendimentoAtual.assunto || atendimentoAtual.titulo || '';
            document.getElementById('editarDescricao').value = atendimentoAtual.descricao || '';
            document.getElementById('editarSolucao').value = atendimentoAtual.solucao || atendimentoAtual.resposta || '';
            
            // Info do atendimento
            document.getElementById('infoAtendimentoEditar').innerHTML = `
                <strong>Atendimento #${atendimentoAtual.id}</strong> - ${atendimentoAtual.protocolo || 'AT-' + atendimentoAtual.id}<br>
                <strong>Cliente:</strong> ${atendimentoAtual.cliente_nome || 'Não identificado'}<br>
                <strong>Data Abertura:</strong> ${atendimentoAtual.data_abertura_fmt || '-'}
            `;
            
            // Abrir modal de edição
            new bootstrap.Modal(document.getElementById('modalEditarAtendimento')).show();
        }
        
        async function salvarEdicaoAtendimento() {
            const id = document.getElementById('editarAtendimentoId').value;
            const tipo = document.getElementById('editarTipo').value;
            const prioridade = document.getElementById('editarPrioridade').value;
            const status = document.getElementById('editarStatus').value;
            const solucao = document.getElementById('editarSolucao').value;
            
            if (!id) {
                alert('Atendimento não identificado!');
                return;
            }
            
            try {
                const response = await fetch('api/atendimentos.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: id,
                        tipo: tipo,
                        prioridade: prioridade,
                        status: status,
                        resposta: solucao
                    })
                });
                
                const resultado = await response.json();
                
                if (resultado.success) {
                    alert('✅ Atendimento atualizado com sucesso!');
                    
                    // Fechar modal
                    bootstrap.Modal.getInstance(document.getElementById('modalEditarAtendimento')).hide();
                    
                    // Recarregar tudo
                    carregarAtendimentos();
                } else {
                    alert('❌ Erro ao atualizar atendimento: ' + (resultado.error || resultado.mensagem));
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('❌ Erro ao salvar alterações!');
            }
        }
        
        // Tornar funções globais
        window.abrirEditarAtendimentoModal = abrirEditarAtendimentoModal;
        window.salvarEdicaoAtendimento = salvarEdicaoAtendimento;
        
        // ===== FUNCIONALIDADE DE BUSCA =====
        document.getElementById('ticketSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('.table-container tbody tr');
            
            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // ===== FUNCIONALIDADE DE FILTROS =====
        function applyFilters() {
            const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
            const priorityFilter = document.getElementById('priorityFilter').value.toLowerCase();
            const categoryFilter = document.getElementById('categoryFilter').value.toLowerCase();
            const tableRows = document.querySelectorAll('.table-container tbody tr');
            
            tableRows.forEach(row => {
                const statusBadge = row.querySelector('.badge.bg-warning, .badge.bg-secondary, .badge.bg-success, .badge.bg-info, .badge.bg-danger');
                const priorityBadge = row.querySelector('.badge.priority-high, .badge.priority-medium, .badge.priority-low');
                const categoryText = row.cells[4] ? row.cells[4].textContent.toLowerCase() : '';
                
                let showRow = true;
                
                if (statusFilter && statusBadge) {
                    const statusText = statusBadge.textContent.toLowerCase();
                    if (!statusText.includes(statusFilter)) {
                        showRow = false;
                    }
                }
                
                if (priorityFilter && priorityBadge) {
                    const priorityText = priorityBadge.textContent.toLowerCase();
                    if (!priorityText.includes(priorityFilter)) {
                        showRow = false;
                    }
                }
                
                if (categoryFilter && !categoryText.includes(categoryFilter)) {
                    showRow = false;
                }
                
                row.style.display = showRow ? '' : 'none';
            });
        }

        // Aplicar filtros quando mudarem
        document.getElementById('statusFilter').addEventListener('change', applyFilters);
        document.getElementById('priorityFilter').addEventListener('change', applyFilters);
        document.getElementById('categoryFilter').addEventListener('change', applyFilters);

        // ===== FUNÇÃO PARA SALVAR NOVO CHAMADO =====
        async function saveTicket() {
            const form = document.getElementById('newTicketForm');
            
            // Validar formulário
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const clienteId = document.getElementById('client').value;
            const categoria = document.getElementById('category').value;
            const prioridade = document.getElementById('priority').value;
            const assunto = document.getElementById('subject').value;
            const descricao = document.getElementById('description').value;
            
            if (!clienteId) {
                alert('Selecione um cliente!');
                return;
            }
            
            try {
                const response = await fetch('api/atendimentos.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        cliente_id: clienteId,
                        tipo: categoria,
                        prioridade: prioridade,
                        assunto: assunto,
                        descricao: descricao
                    })
                });
                
                const resultado = await response.json();
                
                if (resultado.success) {
                    alert('✅ Chamado criado com sucesso!\n\nProtocolo: ' + (resultado.protocolo || '#' + resultado.atendimento_id));
            
            // Fechar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('newTicketModal'));
            modal.hide();
            
            // Limpar formulário
            form.reset();
                    
                    // Recarregar tudo (tabela, chamados recentes e estatísticas)
                    carregarAtendimentos();
                } else {
                    alert('❌ Erro ao criar chamado: ' + (resultado.error || resultado.mensagem || 'Erro desconhecido'));
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('❌ Erro ao criar chamado!');
            }
        }
        
        // Tornar funções globais
        window.saveTicket = saveTicket;
        window.visualizarAtendimento = visualizarAtendimento;
        window.editarAtendimento = editarAtendimento;
        
        // ===== PAGINAÇÃO DE ATENDIMENTOS =====
        
        function renderizarPaginacaoAtendimentos(pag) {
            const container = document.getElementById('paginacaoAtendimentos');
            let html = '';

            html += `<li class="page-item ${pag.pagina_atual === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="irParaPaginaAtend(${pag.pagina_atual - 1}); return false;">Anterior</a>
            </li>`;

            const maxBotoes = 5;
            let inicio = Math.max(1, pag.pagina_atual - 2);
            let fim = Math.min(pag.total_paginas, inicio + maxBotoes - 1);

            if (fim - inicio < maxBotoes - 1) {
                inicio = Math.max(1, fim - maxBotoes + 1);
            }

            for (let i = inicio; i <= fim; i++) {
                html += `<li class="page-item ${i === pag.pagina_atual ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="irParaPaginaAtend(${i}); return false;">${i}</a>
                </li>`;
            }

            html += `<li class="page-item ${pag.pagina_atual === pag.total_paginas ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="irParaPaginaAtend(${pag.pagina_atual + 1}); return false;">Próxima</a>
            </li>`;

            container.innerHTML = html;
        }

        function atualizarInfoAtendimentos(pag) {
            document.getElementById('infoInicioAtend').textContent = pag.inicio;
            document.getElementById('infoFimAtend').textContent = pag.fim;
            document.getElementById('infoTotalAtend').textContent = pag.total;
        }

        function irParaPaginaAtend(pagina) {
            paginaAtualAtend = pagina;
            carregarAtendimentos();
        }

        window.irParaPaginaAtend = irParaPaginaAtend;
        
        // ===== INICIALIZAR =====
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Iniciando carregamento de dados...');
            carregarClientes();
            carregarAtendimentos();
            
            // Listener para mudança de registros por página
            document.getElementById('atendimentosPorPagina').addEventListener('change', function() {
                porPaginaAtend = parseInt(this.value);
                paginaAtualAtend = 1;
                carregarAtendimentos();
            });
        });
    </script>
</body>
</html>
