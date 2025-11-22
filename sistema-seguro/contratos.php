<?php
require_once 'config/database.php';
require_once 'config/auth.php';

verificarLogin();
$usuario = obterUsuarioLogado();
$empresa_id = obterEmpresaId();

// Buscar dados da empresa
$db = getDB();
$stmt = $db->prepare("SELECT razao_social, nome_fantasia, unidade, porcentagem_fixa FROM seguro_empresa_clientes WHERE id = ?");
$stmt->execute([$empresa_id]);
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);
$nomeEmpresa = $empresa['nome_fantasia'] ?: $empresa['razao_social'];
$porcentagemEmpresa = floatval($empresa['porcentagem_fixa'] ?? 0);

// Buscar estatísticas
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_contratos,
        SUM(CASE WHEN situacao = 'ativo' THEN 1 ELSE 0 END) as ativos,
        SUM(CASE WHEN situacao = 'aguardando_ativacao' THEN 1 ELSE 0 END) as aguardando,
        SUM(valor) as valor_total_mensal
    FROM seguro_contratos_clientes 
    WHERE empresa_id = ? AND ativo = 'sim'
");
$stmt->execute([$empresa_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contratos - Sistema Seguro</title>
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
        .profit-card {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
        }
        .search-container {
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        .table-container {
            border-radius: 15px;
            padding: 20px;
        }
        .badge-situacao {
            padding: 6px 12px;
            font-size: 0.85rem;
            font-weight: 500;
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
                <a class="nav-link active" href="contratos.php">
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
                            <i class="fas fa-file-contract me-2"></i>
                            Gestão de Contratos
                        </h2>
                        <p class="text-muted mb-0">Visualize e gerencie todos os contratos cadastrados</p>
                    </div>
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
                            <li><a class="dropdown-item" href="empresa.php"><i class="fas fa-cog me-2"></i>Configurações</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair do Sistema</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <div class="card-body text-center">
                        <i class="fas fa-file-contract financial-icon"></i>
                        <h3 class="mt-3 mb-1"><?php echo $stats['total_contratos']; ?></h3>
                        <p class="mb-0">Total de Contratos</p>
                        <small class="opacity-75">Cadastrados no sistema</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card revenue-card">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle financial-icon"></i>
                        <h3 class="mt-3 mb-1"><?php echo $stats['ativos']; ?></h3>
                        <p class="mb-0">Contratos Ativos</p>
                        <small class="opacity-75">Em vigência</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card profit-card">
                    <div class="card-body text-center">
                        <i class="fas fa-clock financial-icon"></i>
                        <h3 class="mt-3 mb-1"><?php echo $stats['aguardando']; ?></h3>
                        <p class="mb-0">Aguardando Ativação</p>
                        <small class="opacity-75">Pendentes</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white;">
                    <div class="card-body text-center">
                        <i class="fas fa-dollar-sign financial-icon"></i>
                        <h3 class="mt-3 mb-1">R$ <?php echo number_format($stats['valor_total_mensal'] ?? 0, 2, ',', '.'); ?></h3>
                        <p class="mb-0">Valor Total Mensal</p>
                        <small class="opacity-75">Soma dos contratos</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Search and Filters -->
        <div class="search-container">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control" placeholder="Buscar contrato..." id="filtroSearch">
                    </div>
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="filtroSituacao">
                        <option value="">Todas Situações</option>
                        <option value="aguardando_ativacao">Aguardando Ativação</option>
                        <option value="ativo">Ativo</option>
                        <option value="aguardando_link">Aguardando Link</option>
                        <option value="aguardando_vistoria">Aguardando Vistoria</option>
                        <option value="devolvido_para_unidade">Devolvido</option>
                        <option value="aguardando_assinatura">Aguardando Assinatura</option>
                        <option value="desistencia">Desistência</option>
                        <option value="negociar_cliente">Negociar Cliente</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" id="dataInicioFrom" placeholder="Data Início (De)">
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" id="dataInicioTo" placeholder="Data Início (Até)">
                </div>
                <div class="col-md-2">
                    <select class="form-select" id="filtroOrdem">
                        <option value="data_criacao_desc">Mais Recentes</option>
                        <option value="data_criacao_asc">Mais Antigos</option>
                        <option value="cliente_nome">Cliente (A-Z)</option>
                        <option value="valor_desc">Maior Valor</option>
                        <option value="data_inicio_desc">Data Início (Recente)</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-primary w-100" onclick="aplicarFiltros()" title="Aplicar Filtros">
                        <i class="fas fa-filter"></i>
                    </button>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-12 text-end">
                    <button class="btn btn-outline-secondary btn-sm" onclick="limparFiltros()" title="Limpar Filtros">
                        <i class="fas fa-times me-1"></i>Limpar
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Tabela de Contratos -->
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Lista de Contratos</h5>
                <div>
                    <span class="badge bg-secondary me-2">
                        Total: <strong id="totalContratos"><?php echo $stats['total_contratos']; ?></strong>
                    </span>
                    <button class="btn btn-sm btn-success" onclick="exportarExcel()">
                        <i class="fas fa-file-excel me-1"></i>Exportar Excel
                    </button>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="tabelaContratos">
                    <thead class="table-light">
                        <tr>
                            <th>Cliente</th>
                            <th>CONJUNTO</th>
                            <th>Placa(s)</th>
                            <th>Data Início</th>
                            <th>% Rec.</th>
                            <th>Valor Mensal</th>
                            <th>
                                Total 
                                <i class="fas fa-info-circle text-primary ms-1" 
                                   style="cursor: pointer;" 
                                   data-bs-toggle="tooltip" 
                                   data-bs-placement="top"
                                   title="Total = (% Empresa <?php echo number_format($porcentagemEmpresa, 2, ',', '.'); ?>% + % Recorrência) × Valor Mensal"></i>
                            </th>
                            <th>Tipo OS</th>
                            <th style="text-align:center;">OS</th>
                            <th style="text-align:center;">WhatsApp</th>
                            <th style="text-align:center;">E-mail</th>
                            <th style="text-align:center;">Planilha</th>
                            <th>Situação</th>
                            <th style="text-align:center;">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="listaContratos">
                        <tr>
                            <td colspan="14" class="text-center py-5">
                                <i class="fas fa-spinner fa-spin fa-2x text-primary mb-3"></i>
                                <p class="text-muted">Carregando contratos...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginação -->
            <div class="p-3 border-top">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <label class="me-2">Registros por página:</label>
                        <select class="form-select form-select-sm d-inline-block" style="width: auto;" id="registrosPorPagina" onchange="renderizarTabela(); renderizarPaginacao();">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0" id="paginacao"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Editar Contrato -->
    <div class="modal fade" id="modalEditarContrato" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Editar Contrato
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formEditarContrato">
                        <input type="hidden" id="editContratoId">
                        
                        <!-- Informações do Cliente (somente leitura) -->
                        <div class="alert alert-info mb-3">
                            <strong><i class="fas fa-user me-2"></i>Cliente:</strong> 
                            <span id="editClienteNome"></span>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editMatricula" class="form-label">
                                    CONJUNTO <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="editMatricula" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="editPlaca" class="form-label">
                                    Placa(s) <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="editPlaca" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="editPorcentagem" class="form-label">
                                    % Recorrência <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="editPorcentagem" required
                                           min="0" max="100" step="0.01">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="editDataInicio" class="form-label">
                                    Data de Início
                                </label>
                                <input type="date" class="form-control" id="editDataInicio">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="editValor" class="form-label">
                                    Valor Mensal
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="editValor"
                                           min="0" step="0.01">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editSituacao" class="form-label">
                                Situação <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="editSituacao" required>
                                <option value="aguardando_ativacao">Aguardando Ativação</option>
                                <option value="ativo">Ativo</option>
                                <option value="aguardando_link">Aguardando Link</option>
                                <option value="aguardando_vistoria">Aguardando Vistoria</option>
                                <option value="devolvido_para_unidade">Devolvido para Unidade</option>
                                <option value="aguardando_assinatura">Aguardando Assinatura</option>
                                <option value="desistencia">Desistência</option>
                                <option value="negociar_cliente">Negociar Cliente</option>
                            </select>
                        </div>
                        
                        <!-- Campos de Controle -->
                        <div class="mb-3">
                            <label for="editTipoOs" class="form-label">
                                Tipo de OS
                            </label>
                            <select class="form-select" id="editTipoOs">
                                <option value="">Selecione...</option>
                                <option value="ABRIR OS">ABRIR OS</option>
                                <option value="TROCA DE TITULARIDADE">TROCA DE TITULARIDADE</option>
                                <option value="REATIVAÇÃO DE PLACA">REATIVAÇÃO DE PLACA</option>
                                <option value="PREENCHER TIPO DE OS">PREENCHER TIPO DE OS</option>
                                <option value="SEM ORDEM">SEM ORDEM</option>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="editEnvioWhatsapp" class="form-label">
                                    <i class="fab fa-whatsapp text-success"></i> Envio WhatsApp
                                </label>
                                <select class="form-select" id="editEnvioWhatsapp">
                                    <option value="nao">Não</option>
                                    <option value="sim">Sim</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="editEnvioEmail" class="form-label">
                                    <i class="fas fa-envelope text-primary"></i> Envio E-mail
                                </label>
                                <select class="form-select" id="editEnvioEmail">
                                    <option value="nao">Não</option>
                                    <option value="sim">Sim</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="editPlanilha" class="form-label">
                                    <i class="fas fa-table text-info"></i> Planilha
                                </label>
                                <select class="form-select" id="editPlanilha">
                                    <option value="nao">Não</option>
                                    <option value="sim">Sim</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editObservacoes" class="form-label">Observações</label>
                            <textarea class="form-control" id="editObservacoes" rows="3"
                                      placeholder="Informações adicionais sobre o contrato..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" onclick="salvarEdicaoContrato()">
                        <i class="fas fa-save me-1"></i>Salvar Alterações
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/menu-responsivo.js"></script>
    <script src="js/temas.js"></script>
    <script>
        let todosContratos = [];
        let contratosFiltrados = [];
        let paginaAtual = 1;
        let registrosPorPagina = 25;
        let porcentagemEmpresa = <?php echo $porcentagemEmpresa; ?>;
        
        // Carregar contratos
        async function carregarContratos() {
            try {
                console.log('🔄 Carregando contratos...');
                const response = await fetch('api/listar_todos_contratos.php');
                console.log('📡 Response status:', response.status);
                
                // Verificar se resposta é JSON
                const contentType = response.headers.get('content-type');
                console.log('📋 Content-Type:', contentType);
                
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('❌ Resposta não é JSON:', text.substring(0, 500));
                    throw new Error('API retornou resposta não-JSON. Verifique o console.');
                }
                
                const data = await response.json();
                console.log('📦 Dados recebidos:', data);
                
                if (data.sucesso) {
                    todosContratos = data.contratos || [];
                    contratosFiltrados = todosContratos;
                    
                    console.log('✅ Contratos carregados:', todosContratos.length);
                    
                    // Atualizar porcentagem da empresa se vier na resposta
                    if (data.porcentagem_empresa !== undefined) {
                        porcentagemEmpresa = parseFloat(data.porcentagem_empresa) || 0;
                        console.log('💰 Porcentagem empresa:', porcentagemEmpresa);
                    }
                    
                    document.getElementById('totalContratos').textContent = todosContratos.length;
                    
                    renderizarTabela();
                    renderizarPaginacao();
                } else {
                    console.error('❌ API retornou erro:', data);
                    const erroMsg = data.erro_detalhado || data.erro || 'Erro ao carregar contratos';
                    throw new Error(erroMsg);
                }
            } catch (error) {
                console.error('❌ Erro ao carregar contratos:', error);
                const tbody = document.getElementById('listaContratos');
                tbody.innerHTML = '<tr><td colspan="14" class="text-center text-danger py-5">' +
                    '<i class="fas fa-exclamation-circle fa-2x mb-3"></i>' +
                    '<p><strong>Erro ao carregar contratos:</strong></p>' +
                    '<p>' + error.message + '</p>' +
                    '<p><small>Verifique o console do navegador (F12) para mais detalhes</small></p>' +
                    '</td></tr>';
            }
        }
        
        // Renderizar tabela
        function renderizarTabela() {
            const tbody = document.getElementById('listaContratos');
            registrosPorPagina = parseInt(document.getElementById('registrosPorPagina').value);
            
            const inicio = (paginaAtual - 1) * registrosPorPagina;
            const fim = inicio + registrosPorPagina;
            const contratosPagina = contratosFiltrados.slice(inicio, fim);
            
            if (contratosPagina.length === 0) {
                tbody.innerHTML = '<tr><td colspan="14" class="text-center py-5">' +
                    '<i class="fas fa-inbox fa-3x text-muted mb-3"></i>' +
                    '<p class="text-muted">Nenhum contrato encontrado</p>' +
                    '</td></tr>';
                return;
            }
            
            let html = '';
            contratosPagina.forEach(contrato => {
                // Badges de situação
                const situacoes = {
                    'aguardando_ativacao': { badge: 'warning', texto: 'Aguardando Ativação', icon: 'clock' },
                    'ativo': { badge: 'success', texto: 'Ativo', icon: 'check-circle' },
                    'aguardando_link': { badge: 'info', texto: 'Aguardando Link', icon: 'link' },
                    'aguardando_vistoria': { badge: 'primary', texto: 'Aguardando Vistoria', icon: 'clipboard-check' },
                    'devolvido_para_unidade': { badge: 'secondary', texto: 'Devolvido', icon: 'undo' },
                    'aguardando_assinatura': { badge: 'warning', texto: 'Aguardando Assinatura', icon: 'pen' },
                    'desistencia': { badge: 'danger', texto: 'Desistência', icon: 'times-circle' },
                    'negociar_cliente': { badge: 'dark', texto: 'Negociar', icon: 'handshake' }
                };
                
                const sit = situacoes[contrato.situacao] || { badge: 'secondary', texto: contrato.situacao || 'N/A', icon: 'question' };
                
                // Formatar data de início
                const dataInicio = contrato.data_inicio ? 
                    new Date(contrato.data_inicio).toLocaleDateString('pt-BR') : '-';
                
                // Formatar valor
                const valorNum = parseFloat(contrato.valor) || 0;
                const valor = valorNum > 0 ? 
                    'R$ ' + valorNum.toFixed(2).replace('.', ',') : '-';
                
                // Calcular Total: (porcentagem_empresa + porcentagem_recorrencia) * valor
                const porcentagemRec = parseFloat(contrato.porcentagem_recorrencia) || 0;
                const porcentagemTotal = porcentagemEmpresa + porcentagemRec;
                const totalCalculado = valorNum > 0 ? (porcentagemTotal / 100) * valorNum : 0;
                const totalFormatado = totalCalculado > 0 ? 
                    'R$ ' + totalCalculado.toFixed(2).replace('.', ',') : '-';
                
                // Ícones para sim/não
                const iconWhatsapp = contrato.envio_whatsapp === 'sim' ? 
                    '<i class="fas fa-check-circle text-success" title="Sim"></i>' : 
                    '<i class="fas fa-times-circle text-danger" title="Não"></i>';
                const iconEmail = contrato.envio_email === 'sim' ? 
                    '<i class="fas fa-check-circle text-success" title="Sim"></i>' : 
                    '<i class="fas fa-times-circle text-danger" title="Não"></i>';
                const iconPlanilha = contrato.planilha === 'sim' ? 
                    '<i class="fas fa-check-circle text-success" title="Sim"></i>' : 
                    '<i class="fas fa-times-circle text-danger" title="Não"></i>';
                
                html += `
                    <tr>
                        <td>
                            <strong>${contrato.cliente_nome || 'Cliente não encontrado'}</strong><br>
                            <small class="text-muted">Mat. Cliente: ${contrato.cliente_matricula || contrato.cliente_codigo || '-'}</small>
                        </td>
                        <td><strong>${contrato.matricula}</strong></td>
                        <td>${contrato.placa || '-'}</td>
                        <td>${dataInicio}</td>
                        <td><span class="badge bg-info">${porcentagemRec.toFixed(2)}%</span></td>
                        <td><strong>${valor}</strong></td>
                        <td><strong style="color: #28a745;">${totalFormatado}</strong></td>
                        <td><small>${contrato.tipo_os || '-'}</small></td>
                        <td style="text-align:center;">
                            ${contrato.atendimentos_abertos > 0 ? 
                                `<span class="badge bg-warning text-dark mb-1" title="${contrato.atendimentos_abertos} atendimento(s) aberto(s)">
                                    <i class="fas fa-exclamation-triangle"></i> ${contrato.atendimentos_abertos} Aberto${contrato.atendimentos_abertos > 1 ? 's' : ''}
                                </span><br>` : 
                                contrato.total_atendimentos > 0 ?
                                `<span class="badge bg-secondary mb-1" title="${contrato.total_atendimentos} atendimento(s) histórico">
                                    <i class="fas fa-history"></i> ${contrato.total_atendimentos}
                                </span><br>` : ''
                            }
                            <button class="btn btn-sm btn-success" onclick="abrirAtendimento('${contrato.matricula}', '${contrato.cliente_nome}', ${contrato.cliente_id})" title="Abrir nova OS para este conjunto">
                                <i class="fas fa-plus"></i> Novo
                            </button>
                            ${contrato.atendimentos_abertos > 0 ? 
                                `<button class="btn btn-sm btn-primary mt-1" onclick="verAtendimentos('${contrato.matricula}')" title="Ver atendimentos deste conjunto">
                                    <i class="fas fa-eye"></i> Ver
                                </button>` : ''
                            }
                        </td>
                        <td style="text-align:center;">${iconWhatsapp}</td>
                        <td style="text-align:center;">${iconEmail}</td>
                        <td style="text-align:center;">${iconPlanilha}</td>
                        <td>
                            <span class="badge bg-${sit.badge} badge-situacao">
                                <i class="fas fa-${sit.icon} me-1"></i>${sit.texto}
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <button class="btn btn-sm btn-primary me-1" onclick="editarContrato(${contrato.id})" title="Editar Contrato">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-info" onclick="verDetalhesContrato(${contrato.id})" title="Ver no Cliente">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
        }
        
        // Renderizar paginação
        function renderizarPaginacao() {
            const totalPaginas = Math.ceil(contratosFiltrados.length / registrosPorPagina);
            const paginacao = document.getElementById('paginacao');
            
            if (totalPaginas <= 1) {
                paginacao.innerHTML = '';
                return;
            }
            
            let html = '';
            
            // Botão anterior
            html += `<li class="page-item ${paginaAtual === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="mudarPagina(${paginaAtual - 1}); return false;">Anterior</a>
            </li>`;
            
            // Páginas
            for (let i = 1; i <= totalPaginas; i++) {
                if (i === 1 || i === totalPaginas || (i >= paginaAtual - 2 && i <= paginaAtual + 2)) {
                    html += `<li class="page-item ${i === paginaAtual ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="mudarPagina(${i}); return false;">${i}</a>
                    </li>`;
                } else if (i === paginaAtual - 3 || i === paginaAtual + 3) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }
            
            // Botão próximo
            html += `<li class="page-item ${paginaAtual === totalPaginas ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="mudarPagina(${paginaAtual + 1}); return false;">Próximo</a>
            </li>`;
            
            paginacao.innerHTML = html;
        }
        
        // Mudar página
        function mudarPagina(pagina) {
            const totalPaginas = Math.ceil(contratosFiltrados.length / registrosPorPagina);
            if (pagina < 1 || pagina > totalPaginas) return;
            
            paginaAtual = pagina;
            renderizarTabela();
            renderizarPaginacao();
        }
        
        // Aplicar filtros
        function aplicarFiltros() {
            const search = document.getElementById('filtroSearch').value.toLowerCase();
            const situacao = document.getElementById('filtroSituacao').value;
            const ordem = document.getElementById('filtroOrdem').value;
            const dataInicioFrom = document.getElementById('dataInicioFrom').value;
            const dataInicioTo = document.getElementById('dataInicioTo').value;
            
            // Filtrar
            contratosFiltrados = todosContratos.filter(contrato => {
                const matchSearch = !search || 
                    (contrato.cliente_nome && contrato.cliente_nome.toLowerCase().includes(search)) ||
                    (contrato.matricula && contrato.matricula.toLowerCase().includes(search)) ||
                    (contrato.placa && contrato.placa.toLowerCase().includes(search));
                
                const matchSituacao = !situacao || contrato.situacao === situacao;
                
                // Filtro por data de início
                let matchDataInicio = true;
                if (dataInicioFrom || dataInicioTo) {
                    if (!contrato.data_inicio) {
                        matchDataInicio = false; // Se não tem data de início e foi filtrado, exclui
                    } else {
                        const dataContrato = new Date(contrato.data_inicio);
                        const dataFrom = dataInicioFrom ? new Date(dataInicioFrom) : null;
                        const dataTo = dataInicioTo ? new Date(dataInicioTo) : null;
                        
                        if (dataFrom && dataContrato < dataFrom) {
                            matchDataInicio = false;
                        }
                        if (dataTo) {
                            // Adiciona 1 dia para incluir a data final completa
                            const dataToCompleta = new Date(dataTo);
                            dataToCompleta.setHours(23, 59, 59, 999);
                            if (dataContrato > dataToCompleta) {
                                matchDataInicio = false;
                            }
                        }
                    }
                }
                
                return matchSearch && matchSituacao && matchDataInicio;
            });
            
            // Ordenar
            contratosFiltrados.sort((a, b) => {
                switch(ordem) {
                    case 'data_criacao_desc':
                        return new Date(b.data_criacao) - new Date(a.data_criacao);
                    case 'data_criacao_asc':
                        return new Date(a.data_criacao) - new Date(b.data_criacao);
                    case 'cliente_nome':
                        return (a.cliente_nome || '').localeCompare(b.cliente_nome || '');
                    case 'valor_desc':
                        return (parseFloat(b.valor) || 0) - (parseFloat(a.valor) || 0);
                    case 'data_inicio_desc':
                        return new Date(b.data_inicio || 0) - new Date(a.data_inicio || 0);
                    default:
                        return 0;
                }
            });
            
            paginaAtual = 1;
            renderizarTabela();
            renderizarPaginacao();
            
            // Atualizar total na interface
            document.getElementById('totalContratos').textContent = contratosFiltrados.length;
        }
        
        // Limpar filtros
        function limparFiltros() {
            document.getElementById('filtroSearch').value = '';
            document.getElementById('filtroSituacao').value = '';
            document.getElementById('filtroOrdem').value = 'data_criacao_desc';
            document.getElementById('dataInicioFrom').value = '';
            document.getElementById('dataInicioTo').value = '';
            
            contratosFiltrados = todosContratos;
            paginaAtual = 1;
            
            document.getElementById('totalContratos').textContent = todosContratos.length;
            renderizarTabela();
            renderizarPaginacao();
        }
        
        // Editar contrato
        async function editarContrato(contratoId) {
            try {
                console.log('Editando contrato ID:', contratoId);
                
                // Buscar dados do contrato
                const response = await fetch(`api/contratos_clientes.php?id=${contratoId}`);
                const data = await response.json();
                
                if (!data.sucesso) {
                    throw new Error(data.erro || 'Erro ao carregar contrato');
                }
                
                const contrato = data.contrato;
                console.log('Dados do contrato:', contrato);
                
                // Buscar dados do cliente
                const clienteResponse = await fetch(`api/listar_clientes.php?id=${contrato.cliente_id}`);
                const clienteData = await clienteResponse.json();
                const cliente = clienteData.cliente || clienteData.clientes?.[0];
                
                // Preencher formulário
                document.getElementById('editContratoId').value = contrato.id;
                document.getElementById('editClienteNome').textContent = cliente?.nome_razao_social || 'Cliente não encontrado';
                document.getElementById('editMatricula').value = contrato.matricula || '';
                document.getElementById('editPlaca').value = contrato.placa || '';
                document.getElementById('editPorcentagem').value = contrato.porcentagem_recorrencia || '';
                document.getElementById('editDataInicio').value = contrato.data_inicio || '';
                document.getElementById('editValor').value = contrato.valor || '';
                document.getElementById('editSituacao').value = contrato.situacao || 'aguardando_ativacao';
                document.getElementById('editTipoOs').value = contrato.tipo_os || '';
                document.getElementById('editEnvioWhatsapp').value = contrato.envio_whatsapp || 'nao';
                document.getElementById('editEnvioEmail').value = contrato.envio_email || 'nao';
                document.getElementById('editPlanilha').value = contrato.planilha || 'nao';
                document.getElementById('editObservacoes').value = contrato.observacoes || '';
                
                // Abrir modal
                const modal = new bootstrap.Modal(document.getElementById('modalEditarContrato'));
                modal.show();
                
            } catch (error) {
                console.error('Erro ao editar contrato:', error);
                alert('Erro ao carregar dados do contrato: ' + error.message);
            }
        }
        
        // Salvar edição do contrato
        async function salvarEdicaoContrato() {
            const form = document.getElementById('formEditarContrato');
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const contratoId = document.getElementById('editContratoId').value;
            const matricula = document.getElementById('editMatricula').value.trim();
            const placa = document.getElementById('editPlaca').value.trim();
            const porcentagem = document.getElementById('editPorcentagem').value;
            const dataInicio = document.getElementById('editDataInicio').value;
            const valor = document.getElementById('editValor').value;
            const situacao = document.getElementById('editSituacao').value;
            const tipoOs = document.getElementById('editTipoOs').value.trim();
            const envioWhatsapp = document.getElementById('editEnvioWhatsapp').value;
            const envioEmail = document.getElementById('editEnvioEmail').value;
            const planilha = document.getElementById('editPlanilha').value;
            const observacoes = document.getElementById('editObservacoes').value.trim();
            
            if (!matricula || !placa || !porcentagem) {
                alert('Preencha todos os campos obrigatórios');
                return;
            }
            
            try {
                const dados = {
                    id: contratoId,
                    matricula: matricula,
                    placa: placa,
                    porcentagem_recorrencia: porcentagem,
                    data_inicio: dataInicio || null,
                    valor: valor || null,
                    situacao: situacao,
                    tipo_os: tipoOs || null,
                    envio_whatsapp: envioWhatsapp,
                    envio_email: envioEmail,
                    planilha: planilha,
                    observacoes: observacoes
                };
                
                const response = await fetch('api/contratos_clientes.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(dados)
                });
                
                const resultado = await response.json();
                
                if (resultado.sucesso) {
                    alert('Contrato atualizado com sucesso!');
                    
                    // Fechar modal
                    const modalInstance = bootstrap.Modal.getInstance(document.getElementById('modalEditarContrato'));
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                    
                    // Recarregar lista de contratos
                    await carregarContratos();
                } else {
                    alert('Erro: ' + (resultado.erro || 'Erro desconhecido'));
                }
            } catch (error) {
                console.error('Erro ao salvar contrato:', error);
                alert('Erro ao salvar contrato: ' + error.message);
            }
        }
        
        // Abrir atendimento para um conjunto específico
        function abrirAtendimento(matricula, clienteNome, clienteId) {
            // Redirecionar para a página de atendimentos com dados pré-preenchidos
            const params = new URLSearchParams({
                cliente_id: clienteId,
                matricula: matricula,
                action: 'novo'
            });
            window.location.href = 'atendimento.php?' + params.toString();
        }
        
        // Ver atendimentos de um conjunto específico
        function verAtendimentos(matricula) {
            // Redirecionar para a página de atendimentos filtrada por matrícula
            window.location.href = 'atendimento.php?matricula=' + matricula;
        }
        
        // Ver detalhes do contrato
        function verDetalhesContrato(contratoId) {
            // Redirecionar para a página de clientes com o contrato selecionado
            const contrato = todosContratos.find(c => c.id == contratoId);
            if (contrato && contrato.cliente_id) {
                window.location.href = 'clientes.php?cliente_id=' + contrato.cliente_id;
            }
        }
        
        // Exportar para Excel
        function exportarExcel() {
            if (contratosFiltrados.length === 0) {
                alert('Nenhum contrato para exportar!');
                return;
            }
            
            let csv = 'Cliente;Matrícula;CONJUNTO;Placa(s);Data Início;% Recorrência;Valor Mensal;Total;Tipo OS;WhatsApp;E-mail;Planilha;Situação\n';
            
            contratosFiltrados.forEach(c => {
                const valorNum = parseFloat(c.valor) || 0;
                const porcentagemRec = parseFloat(c.porcentagem_recorrencia) || 0;
                const porcentagemTotal = porcentagemEmpresa + porcentagemRec;
                const totalCalculado = valorNum > 0 ? (porcentagemTotal / 100) * valorNum : 0;
                
                csv += `${c.cliente_nome || ''};`;
                csv += `${c.matricula || ''};`;
                csv += `${c.matricula};`;
                csv += `${c.placa || ''};`;
                csv += `${c.data_inicio ? new Date(c.data_inicio).toLocaleDateString('pt-BR') : ''};`;
                csv += `${porcentagemRec.toFixed(2)}%;`;
                csv += `${valorNum > 0 ? valorNum.toFixed(2) : ''};`;
                csv += `${totalCalculado > 0 ? totalCalculado.toFixed(2) : ''};`;
                csv += `${c.tipo_os || ''};`;
                csv += `${c.envio_whatsapp === 'sim' ? 'Sim' : 'Não'};`;
                csv += `${c.envio_email === 'sim' ? 'Sim' : 'Não'};`;
                csv += `${c.planilha === 'sim' ? 'Sim' : 'Não'};`;
                csv += `${c.situacao}\n`;
            });
            
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'contratos_' + new Date().toISOString().split('T')[0] + '.csv';
            link.click();
        }
        
        // Inicializar tooltips
        function inicializarTooltips() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
        
        // Carregar ao iniciar
        document.addEventListener('DOMContentLoaded', function() {
            carregarContratos();
            inicializarTooltips();
            
            // Filtro em tempo real
            document.getElementById('filtroSearch').addEventListener('input', aplicarFiltros);
            
            // Aplicar filtro ao pressionar Enter nos campos de data
            document.getElementById('dataInicioFrom').addEventListener('change', aplicarFiltros);
            document.getElementById('dataInicioTo').addEventListener('change', aplicarFiltros);
        });
    </script>
</body>
</html>

