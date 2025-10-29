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
    <title>Sistema Seguro - Cadastro de Clientes</title>
    <script src="js/tema-instantaneo.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/temas.css" rel="stylesheet">
    <link href="css/tema-escuro-forcado.css" rel="stylesheet">
    <style>
        body {
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
        
        /* Header */
        .header {
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        /* Menu Toggle Button - SEMPRE VISÍVEL */
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
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        /* Search Container */
        .search-container {
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        /* Table Container */
        .table-container {
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        /* Action Icons */
        .action-icons {
            display: flex;
            gap: 3px;
            flex-wrap: nowrap;
            justify-content: center;
        }
        
        .action-icon {
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .action-icon:hover {
            transform: scale(1.1);
        }
        
        .icon-view { background-color: #17a2b8; color: white; }
        .icon-edit { background-color: #ffc107; color: #212529; }
        .icon-money { background-color: #28a745; color: white; }
        .icon-support { background-color: #17a2b8; color: white; }
        .icon-equipment { background-color: #6c757d; color: white; }
        
        /* Pagination */
        .pagination .page-link {
            border-radius: 8px;
            margin: 0 2px;
            border: 1px solid #dee2e6;
        }
        
        .pagination .page-item.active .page-link {
            background-color: #667eea;
            border-color: #667eea;
        }
        
        /* Buttons */
        .btn-action {
            background: #28a745;
            border: none;
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            margin: 2px;
            transition: all 0.3s;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .btn-search { background: #28a745; border: none; color: white; }
        .btn-options { background: #6c757d; border: none; color: white; }
        .btn-view { background: #17a2b8; border: none; color: white; }
        .btn-refresh { background: #ffc107; border: none; color: #212529; }
        .btn-success { background: #28a745; border: none; color: white; }
        
        /* Modal */
        .modal-xl {
            max-width: 1140px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
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
                <a class="nav-link active" href="clientes.php">
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
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <h2 class="mb-1">
                            <i class="fas fa-users me-2"></i>
                            Cadastro de Clientes
                        </h2>
                        <p class="text-muted mb-0">Mostrando Apenas Clientes Ativos</p>
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
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Configurações</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair do Sistema</a></li>
                        </ul>
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
                        <input type="text" class="form-control" placeholder="Busca Rápida" id="quickSearch">
                    </div>
                </div>
                <div class="col-md-9">
                    <div class="d-flex gap-2 justify-content-end">
                        <button class="btn btn-action" id="btnNovo">
                            <i class="fas fa-plus me-1"></i>
                            Novo
                        </button>
                        <button class="btn btn-action btn-search" id="btnPesquisar">
                            <i class="fas fa-search me-1"></i>
                            Pesquisar
                        </button>
                        <div class="d-flex align-items-center">
                            <button class="btn btn-view me-2" id="btnVisualizar">
                                <i class="fas fa-eye me-1"></i>
                                Visualizar
                            </button>
                            <input type="number" class="form-control form-control-sm" value="10" min="5" max="100" id="registrosPorPagina" style="width: 60px;">
                        </div>
                        <button class="btn btn-refresh" id="btnVerTodos">
                            <i class="fas fa-sync-alt me-1"></i>
                            Ver Todos
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Clients Table -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="5%"></th>
                            <th width="8%">Identificador</th>
                            <th width="15%">CPF/CNPJ</th>
                            <th width="25%">Nome/Razão Social</th>
                            <th width="12%">MATRÍCULA</th>
                            <th width="12%">Cidade</th>
                            <th width="5%">UF</th>
                            <th width="13%">% do Cliente</th>
                            <th width="5%">Situação</th>
                        </tr>
                    </thead>
                    <tbody id="clientesTableBody">
                        <!-- Os clientes serão carregados dinamicamente via JavaScript -->
                    </tbody>
                </table>
            </div>
            
            <!-- Paginação e Info -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div id="infoRegistros" class="text-muted">
                    Mostrando <strong id="infoInicio">0</strong> a <strong id="infoFim">0</strong> de <strong id="infoTotal">0</strong> registros
                </div>
                <nav aria-label="Navegação de página">
                    <ul class="pagination pagination-sm mb-0" id="paginationContainer">
                        <!-- Paginação será gerada dinamicamente -->
                    </ul>
                </nav>
            </div>
        </div>

    <!-- Modal de Cadastro de Cliente -->
    <div class="modal fade" id="modalNovoCliente" tabindex="-1" aria-labelledby="modalNovoClienteLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title" id="modalNovoClienteLabel">
                        <i class="fas fa-user-plus me-2"></i>
                        Cadastro de Cliente
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                <div class="modal-body">
                    <form id="formNovoCliente">
                        <!-- Informações Básicas -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informações Básicas</h6>
                                    </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="tipoPessoa" class="form-label">Tipo de Pessoa <span class="text-danger">*</span></label>
                                        <select class="form-select" id="tipoPessoa" required>
                                            <option value="">Selecione...</option>
                                            <option value="fisica">Pessoa Física</option>
                                            <option value="juridica">Pessoa Jurídica</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="cpfCnpj" class="form-label">CPF/CNPJ <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="cpfCnpj" placeholder="000.000.000-00" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="situacao" class="form-label">Situação <span class="text-danger">*</span></label>
                                        <select class="form-select" id="situacao" required>
                                            <option value="ativo">Ativo</option>
                                            <option value="inativo">Inativo</option>
                                        </select>
                                    </div>
                                    </div>
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label for="nomeRazao" class="form-label">Nome/Razão Social <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="nomeRazao" placeholder="Nome completo ou razão social" required>
                                </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="sigla" class="form-label">Sigla/Nome Fantasia</label>
                                        <input type="text" class="form-control" id="sigla" placeholder="Sigla ou nome fantasia">
                                    </div>
                                    </div>
                                    </div>
                                    </div>

                        <!-- Endereço -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Endereço</h6>
                                    </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label for="cep" class="form-label">CEP</label>
                                        <input type="text" class="form-control" id="cep" placeholder="00000-000">
                                </div>
                                    <div class="col-md-7 mb-3">
                                        <label for="logradouro" class="form-label">Logradouro</label>
                                        <input type="text" class="form-control" id="logradouro" placeholder="Rua, avenida, etc.">
                                    </div>
                                    <div class="col-md-2 mb-3">
                                        <label for="numero" class="form-label">Número</label>
                                        <input type="text" class="form-control" id="numero" placeholder="Nº">
                                    </div>
                                    </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="complemento" class="form-label">Complemento</label>
                                        <input type="text" class="form-control" id="complemento" placeholder="Apto, sala, etc.">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="bairro" class="form-label">Bairro</label>
                                        <input type="text" class="form-control" id="bairro" placeholder="Bairro">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="cidade" class="form-label">Cidade</label>
                                        <input type="text" class="form-control" id="cidade" placeholder="Cidade">
                                </div>
                                    <div class="col-md-1 mb-3">
                                        <label for="uf" class="form-label">UF</label>
                                        <select class="form-select" id="uf">
                                            <option value="">-</option>
                                            <option value="AC">AC</option>
                                            <option value="AL">AL</option>
                                            <option value="AP">AP</option>
                                            <option value="AM">AM</option>
                                            <option value="BA">BA</option>
                                            <option value="CE">CE</option>
                                            <option value="DF">DF</option>
                                            <option value="ES">ES</option>
                                            <option value="GO">GO</option>
                                            <option value="MA">MA</option>
                                            <option value="MT">MT</option>
                                            <option value="MS">MS</option>
                                            <option value="MG">MG</option>
                                            <option value="PA">PA</option>
                                            <option value="PB">PB</option>
                                            <option value="PR">PR</option>
                                            <option value="PE">PE</option>
                                            <option value="PI">PI</option>
                                            <option value="RJ">RJ</option>
                                            <option value="RN">RN</option>
                                            <option value="RS">RS</option>
                                            <option value="RO">RO</option>
                                            <option value="RR">RR</option>
                                            <option value="SC">SC</option>
                                            <option value="SP">SP</option>
                                            <option value="SE">SE</option>
                                            <option value="TO">TO</option>
                                        </select>
                                    </div>
                                    </div>
                                    </div>
                                    </div>

                        <!-- Contatos -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-phone me-2"></i>Contatos</h6>
                                    </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="telefone" class="form-label">Telefone</label>
                                        <input type="text" class="form-control" id="telefone" placeholder="(00) 0000-0000">
                                </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="celular" class="form-label">Celular</label>
                                        <input type="text" class="form-control" id="celular" placeholder="(00) 00000-0000">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="email" class="form-label">E-mail</label>
                                        <input type="email" class="form-control" id="email" placeholder="email@exemplo.com">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dados Adicionais -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-id-card me-2"></i>Dados Adicionais</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label for="identificador" class="form-label">Identificador</label>
                                        <input type="text" class="form-control" id="identificador" placeholder="ID do cliente">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="placa" class="form-label">PLACA</label>
                                        <input type="text" class="form-control" id="placa" placeholder="ABC1D23" maxlength="100">
                                        <small class="text-muted">Pode ter múltiplas placas separadas por vírgula</small>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="conjunto" class="form-label">CONJUNTO</label>
                                        <input type="text" class="form-control" id="conjunto" placeholder="Ex: 382">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="matricula" class="form-label">MATRÍCULA</label>
                                        <input type="text" class="form-control" id="matricula" placeholder="Ex: 370">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Configuração Sistema Financeiro -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-dollar-sign me-2"></i>Configuração Sistema Financeiro</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="unidade" class="form-label">Unidade</label>
                                        <input type="text" class="form-control" id="unidade" readonly style="background-color: #e9ecef;">
                                        <small class="text-muted">Unidade cadastrada na empresa (somente leitura)</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="porcentagemRecorrencia" class="form-label">Porcentagem RECORRENCIA (%)</label>
                                        <input type="number" class="form-control" id="porcentagemRecorrencia" placeholder="0.00" min="0" max="100" step="0.01">
                                        <small class="text-muted">Defina a porcentagem de recorrência para este cliente</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Observações -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Observações</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="observacoes" class="form-label">Observações Gerais</label>
                                        <textarea class="form-control" id="observacoes" rows="3" placeholder="Digite observações sobre o cliente..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" id="btnSalvarCliente">
                        <i class="fas fa-save me-1"></i>
                        Salvar Cliente
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Visualizar Cliente -->
    <div class="modal fade" id="modalVisualizarCliente" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Detalhes do Cliente</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="conteudoVisualizarCliente">
                    <!-- Será preenchido via JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Posição Financeira -->
    <div class="modal fade" id="modalPosicaoFinanceira" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #218838 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-chart-line me-2"></i>Posição Financeira</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="infoClienteFinanceiro" style="margin-bottom: 20px;"></div>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm" style="font-size: 13px;">
                            <thead class="table-light">
                                <tr>
                                    <th>Unidade</th>
                                    <th>Identificador</th>
                                    <th>N° DOC</th>
                                    <th>ASSOCIADO</th>
                                    <th>CLASSE</th>
                                    <th>EMISSÃO</th>
                                    <th>VENCIMENTO</th>
                                    <th>VALOR</th>
                                    <th>PLACA</th>
                                    <th>CONJUNTO</th>
                                    <th>MATRÍCULA</th>
                                    <th>SITUAÇÃO</th>
                                    <th>VALOR PAGO</th>
                                    <th>DATA DA BAIXA</th>
                                    <th>COMISSÃO</th>
                                </tr>
                            </thead>
                            <tbody id="tabelaDocumentosFinanceiros">
                                <!-- Será preenchido via JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    <div id="resumoFinanceiro" style="margin-top: 20px;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-success"><i class="fas fa-file-excel me-1"></i>Exportar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Atendimentos do Cliente -->
    <div class="modal fade" id="modalAtendimentos" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-headset me-2"></i>Atendimentos do Cliente</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Informações do Cliente -->
                    <div class="alert alert-info" id="infoClienteAtendimento">
                        Carregando...
                    </div>

                    <!-- Botão Novo Atendimento -->
                    <div class="mb-3">
                        <button class="btn btn-success" id="btnNovoAtendimento" onclick="abrirNovoAtendimento()">
                            <i class="fas fa-plus me-1"></i>
                            Novo Atendimento
                        </button>
                    </div>

                    <!-- Tabela de Atendimentos -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Data Abertura</th>
                                    <th>Assunto</th>
                                    <th>Tipo</th>
                                    <th>Prioridade</th>
                                    <th>Status</th>
                                    <th>Responsável</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="tabelaAtendimentos">
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
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Novo Atendimento -->
    <div class="modal fade" id="modalNovoAtendimento" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Novo Atendimento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formNovoAtendimento">
                        <input type="hidden" id="novoAtendimentoClienteId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="tipoAtendimento" class="form-label">Tipo de Atendimento</label>
                                <select class="form-select" id="tipoAtendimento" required>
                                    <option value="suporte">Suporte</option>
                                    <option value="reclamacao">Reclamação</option>
                                    <option value="duvida">Dúvida</option>
                                    <option value="venda">Venda</option>
                                    <option value="acompanhamento">Acompanhamento</option>
                                    <option value="outros">Outros</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="prioridadeAtendimento" class="form-label">Prioridade</label>
                                <select class="form-select" id="prioridadeAtendimento" required>
                                    <option value="baixa">Baixa</option>
                                    <option value="media" selected>Média</option>
                                    <option value="alta">Alta</option>
                                    <option value="urgente">Urgente</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="assuntoAtendimento" class="form-label">Assunto</label>
                            <input type="text" class="form-control" id="assuntoAtendimento" required placeholder="Ex: Dúvida sobre pagamento">
                        </div>
                        
                        <div class="mb-3">
                            <label for="descricaoAtendimento" class="form-label">Descrição</label>
                            <textarea class="form-control" id="descricaoAtendimento" rows="5" required placeholder="Descreva o atendimento..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="salvarNovoAtendimento()">
                        <i class="fas fa-save me-1"></i>
                        Salvar Atendimento
                    </button>
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
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Atendimento</h5>
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

    <!-- Modal Equipamentos do Cliente -->
    <div class="modal fade" id="modalEquipamentos" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #6c757d 0%, #495057 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-cogs me-2"></i>Equipamentos do Cliente</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Informações do Cliente -->
                    <div class="alert alert-info" id="infoClienteEquipamento">
                        Carregando...
                    </div>

                    <!-- Botão Novo Equipamento -->
                    <div class="mb-3">
                        <button class="btn btn-success" id="btnNovoEquipamento" onclick="abrirNovoEquipamento()">
                            <i class="fas fa-plus me-1"></i>
                            Novo Equipamento
                        </button>
                    </div>

                    <!-- Tabela de Equipamentos -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th width="8%">#</th>
                                    <th width="15%">Tipo</th>
                                    <th width="20%">Descrição</th>
                                    <th width="12%">Marca/Modelo</th>
                                    <th width="12%">Nº Série</th>
                                    <th width="10%">Situação</th>
                                    <th width="12%">Data Cadastro</th>
                                    <th width="11%">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="tabelaEquipamentos">
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
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Novo Equipamento -->
    <div class="modal fade" id="modalNovoEquipamento" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Cadastrar Equipamento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formNovoEquipamento">
                        <input type="hidden" id="novoEquipamentoClienteId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="equipTipo" class="form-label">Tipo de Equipamento <span class="text-danger">*</span></label>
                                <select class="form-select" id="equipTipo" required>
                                    <option value="">Selecione...</option>
                                    <option value="Câmera">Câmera</option>
                                    <option value="DVR/NVR">DVR/NVR</option>
                                    <option value="Alarme">Alarme</option>
                                    <option value="Sensor">Sensor</option>
                                    <option value="Central">Central de Alarme</option>
                                    <option value="Controle">Controle de Acesso</option>
                                    <option value="Rastreador">Rastreador</option>
                                    <option value="Outros">Outros</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="equipSituacao" class="form-label">Situação <span class="text-danger">*</span></label>
                                <select class="form-select" id="equipSituacao" required>
                                    <option value="ativo">Ativo</option>
                                    <option value="inativo">Inativo</option>
                                    <option value="manutencao">Em Manutenção</option>
                                    <option value="substituido">Substituído</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="equipDescricao" class="form-label">Descrição <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="equipDescricao" required placeholder="Ex: Câmera IP externa HD">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="equipMarca" class="form-label">Marca</label>
                                <input type="text" class="form-control" id="equipMarca" placeholder="Ex: Intelbras">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="equipModelo" class="form-label">Modelo</label>
                                <input type="text" class="form-control" id="equipModelo" placeholder="Ex: VHD 1220 B">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="equipNumeroSerie" class="form-label">Número de Série</label>
                                <input type="text" class="form-control" id="equipNumeroSerie" placeholder="Ex: ABC123456">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="equipDataInstalacao" class="form-label">Data de Instalação</label>
                                <input type="date" class="form-control" id="equipDataInstalacao">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="equipLocalizacao" class="form-label">Localização</label>
                            <input type="text" class="form-control" id="equipLocalizacao" placeholder="Ex: Entrada principal, Portaria, etc.">
                        </div>
                        
                        <div class="mb-3">
                            <label for="equipObservacoes" class="form-label">Observações</label>
                            <textarea class="form-control" id="equipObservacoes" rows="3" placeholder="Informações adicionais sobre o equipamento..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="salvarNovoEquipamento()">
                        <i class="fas fa-save me-1"></i>
                        Salvar Equipamento
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Visualizar Equipamento -->
    <div class="modal fade" id="modalVisualizarEquipamento" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Detalhes do Equipamento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="conteudoVisualizarEquipamento">
                    <!-- Será preenchido via JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-warning" onclick="abrirEditarEquipamentoModal()">
                        <i class="fas fa-edit me-1"></i>
                        Editar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar Equipamento -->
    <div class="modal fade" id="modalEditarEquipamento" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Equipamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formEditarEquipamento">
                        <input type="hidden" id="editarEquipamentoId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editEquipTipo" class="form-label">Tipo de Equipamento <span class="text-danger">*</span></label>
                                <select class="form-select" id="editEquipTipo" required>
                                    <option value="">Selecione...</option>
                                    <option value="Câmera">Câmera</option>
                                    <option value="DVR/NVR">DVR/NVR</option>
                                    <option value="Alarme">Alarme</option>
                                    <option value="Sensor">Sensor</option>
                                    <option value="Central">Central de Alarme</option>
                                    <option value="Controle">Controle de Acesso</option>
                                    <option value="Rastreador">Rastreador</option>
                                    <option value="Outros">Outros</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editEquipSituacao" class="form-label">Situação <span class="text-danger">*</span></label>
                                <select class="form-select" id="editEquipSituacao" required>
                                    <option value="ativo">Ativo</option>
                                    <option value="inativo">Inativo</option>
                                    <option value="manutencao">Em Manutenção</option>
                                    <option value="substituido">Substituído</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editEquipDescricao" class="form-label">Descrição <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editEquipDescricao" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editEquipMarca" class="form-label">Marca</label>
                                <input type="text" class="form-control" id="editEquipMarca">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editEquipModelo" class="form-label">Modelo</label>
                                <input type="text" class="form-control" id="editEquipModelo">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editEquipNumeroSerie" class="form-label">Número de Série</label>
                                <input type="text" class="form-control" id="editEquipNumeroSerie">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editEquipDataInstalacao" class="form-label">Data de Instalação</label>
                                <input type="date" class="form-control" id="editEquipDataInstalacao">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editEquipLocalizacao" class="form-label">Localização</label>
                            <input type="text" class="form-control" id="editEquipLocalizacao">
                        </div>
                        
                        <div class="mb-3">
                            <label for="editEquipObservacoes" class="form-label">Observações</label>
                            <textarea class="form-control" id="editEquipObservacoes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarEdicaoEquipamento()">
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
        console.log('=== SISTEMA SEGURO - CLIENTES - SCRIPT PRINCIPAL ===');

        // ===== SISTEMA DE GESTÃO DE CLIENTES =====
        
        // Carregar clientes do banco de dados
        let todosClientes = [];
        let carregando = true;
        
        // Função para carregar clientes do banco
        async function carregarClientesDoBanco() {
            try {
                console.log('Carregando clientes do banco...');
                // Buscar TODAS as clientes (sem paginação no servidor)
                const response = await fetch('api/listar_clientes.php?situacao=todos&todas=true');
                const data = await response.json();
                
                if (data.sucesso) {
                    console.log('✅ Total de clientes carregados:', data.clientes.length);
                    todosClientes = data.clientes.map(c => ({
                        id: c.id,
                        codigo: c.codigo,
                        identificador: c.identificador || c.codigo || '-',
                        cpfCnpj: c.cpf_cnpj,
                        nome: c.nome_razao_social,
                        matricula: c.matricula || '-',
                        bairro: c.bairro || '-',
                        cidade: c.cidade || '-',
                        uf: c.uf || '-',
                        porcentagem: c.porcentagem_recorrencia || '0.00',
                        situacao: c.situacao
                    }));
                    carregando = false;
                    renderizarTabela();
                } else {
                    console.error('Erro ao carregar clientes:', data.mensagem);
                    carregando = false;
                    renderizarTabela();
                }
            } catch (error) {
                console.error('Erro na requisição:', error);
                carregando = false;
                renderizarTabela();
            }
        }
        
        // Variáveis de controle
        let paginaAtual = 1;
        let registrosPorPagina = parseInt(document.getElementById('registrosPorPagina').value);
        let mostrarTodos = false;
        let clientesFiltrados = [];
        let termoBusca = '';
        
        // Função para obter clientes filtrados
        function obterClientesFiltrados() {
            let clientes = mostrarTodos ? todosClientes : todosClientes.filter(c => c.situacao === 'ativo');
            
            if (termoBusca) {
                clientes = clientes.filter(cliente => {
                    return Object.values(cliente).some(valor => 
                        String(valor).toLowerCase().includes(termoBusca.toLowerCase())
                    );
                });
            }
            
            return clientes;
        }
        
        // Função para renderizar a tabela
        function renderizarTabela() {
            clientesFiltrados = obterClientesFiltrados();
            const totalRegistros = clientesFiltrados.length;
            const totalPaginas = Math.ceil(totalRegistros / registrosPorPagina);
            
            // Debug
            console.log(`📊 Paginação:`, {
                totalRegistros,
                registrosPorPagina,
                totalPaginas,
                paginaAtual
            });
            
            // Ajustar página atual se necessário
            if (paginaAtual > totalPaginas && totalPaginas > 0) {
                paginaAtual = totalPaginas;
            }
            if (paginaAtual < 1) {
                paginaAtual = 1;
            }
            
            const inicio = (paginaAtual - 1) * registrosPorPagina;
            const fim = Math.min(inicio + registrosPorPagina, totalRegistros);
            const clientesPagina = clientesFiltrados.slice(inicio, fim);
            
            // Renderizar tabela
            const tbody = document.getElementById('clientesTableBody');
            tbody.innerHTML = '';
            
            if (carregando) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center"><i class="fas fa-spinner fa-spin"></i> Carregando clientes...</td></tr>';
            } else if (clientesPagina.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center">Nenhum cliente encontrado</td></tr>';
            } else {
                clientesPagina.forEach(cliente => {
                    const badgeClass = cliente.situacao === 'ativo' ? 'bg-success' : 'bg-danger';
                    const badgeText = cliente.situacao === 'ativo' ? 'Ativo' : 'Inativo';
                    
                    const row = `
                        <tr>
                            <td>
                                <div class="action-icons">
                                    <div class="action-icon icon-view" title="Visualizar" onclick="abrirVisualizar(${cliente.id})">
                                        <i class="fas fa-eye"></i>
                                    </div>
                                    <div class="action-icon icon-edit" title="Editar" onclick="abrirEditar(${cliente.id})">
                                        <i class="fas fa-edit"></i>
                                    </div>
                                    <div class="action-icon icon-money" title="Posição Financeira" onclick="abrirFinanceiro(${cliente.id})">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <div class="action-icon icon-support" title="Atendimentos" onclick="abrirAtendimento(${cliente.id})">
                                        <i class="fas fa-headset"></i>
                                    </div>
                                    <div class="action-icon icon-equipment" title="Equipamento" onclick="abrirEquipamento(${cliente.id})">
                                        <i class="fas fa-cogs"></i>
                                    </div>
                                </div>
                            </td>
                            <td>${cliente.identificador}</td>
                            <td>${cliente.cpfCnpj}</td>
                            <td>${cliente.nome}</td>
                            <td>${cliente.matricula}</td>
                            <td>${cliente.cidade}</td>
                            <td>${cliente.uf}</td>
                            <td><span class="badge bg-info">${parseFloat(cliente.porcentagem).toFixed(2)}%</span></td>
                            <td><span class="badge ${badgeClass}">${badgeText}</span></td>
                        </tr>
                    `;
                    tbody.innerHTML += row;
                });
            }
            
            // Atualizar informações
            document.getElementById('infoInicio').textContent = totalRegistros > 0 ? inicio + 1 : 0;
            document.getElementById('infoFim').textContent = fim;
            document.getElementById('infoTotal').textContent = totalRegistros;
            
            // Atualizar título do header
            const headerSubtitle = document.querySelector('.header p.text-muted');
            if (mostrarTodos) {
                headerSubtitle.textContent = `Mostrando Todos os Clientes (Ativos e Inativos)`;
            } else {
                headerSubtitle.textContent = `Mostrando Apenas Clientes Ativos`;
            }
            
            // Renderizar paginação
            renderizarPaginacao(totalPaginas);
        }
        
        // Função para renderizar paginação
        function renderizarPaginacao(totalPaginas) {
            const paginationContainer = document.getElementById('paginationContainer');
            paginationContainer.innerHTML = '';
            
            if (totalPaginas <= 1) return;
            
            // Botão Primeira
            const btnFirst = `
                <li class="page-item ${paginaAtual === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="irParaPagina(1); return false;">‹‹</a>
                </li>
            `;
            paginationContainer.innerHTML += btnFirst;
            
            // Botão Anterior
            const btnPrev = `
                <li class="page-item ${paginaAtual === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="irParaPagina(${paginaAtual - 1}); return false;">‹</a>
                </li>
            `;
            paginationContainer.innerHTML += btnPrev;
            
            // Páginas
            let startPage = Math.max(1, paginaAtual - 2);
            let endPage = Math.min(totalPaginas, paginaAtual + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                const pageBtn = `
                    <li class="page-item ${i === paginaAtual ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="irParaPagina(${i}); return false;">${i}</a>
                    </li>
                `;
                paginationContainer.innerHTML += pageBtn;
            }
            
            // Botão Próximo
            const btnNext = `
                <li class="page-item ${paginaAtual === totalPaginas ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="irParaPagina(${paginaAtual + 1}); return false;">›</a>
                </li>
            `;
            paginationContainer.innerHTML += btnNext;
            
            // Botão Última
            const btnLast = `
                <li class="page-item ${paginaAtual === totalPaginas ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="irParaPagina(${totalPaginas}); return false;">››</a>
                </li>
            `;
            paginationContainer.innerHTML += btnLast;
        }
        
        // Função para ir para uma página
        function irParaPagina(pagina) {
            console.log(`🔄 Mudando para a página ${pagina}`);
            paginaAtual = pagina;
            renderizarTabela();
        }
        
        // Funções dos ícones de ação
        function abrirVisualizar(id) {
            const cliente = todosClientes.find(c => c.id === id);
            if (!cliente) return;
            
            // Buscar dados completos do banco
            fetch('api/obter_cliente.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.sucesso) {
                        mostrarModalVisualizar(data.cliente);
                    } else {
                        alert('Erro ao carregar dados do cliente');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao buscar cliente');
                });
        }
        
        function abrirEditar(id) {
            // Buscar dados completos do banco
            fetch('api/obter_cliente.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.sucesso) {
                        preencherModalEdicao(data.cliente);
                    } else {
                        alert('Erro ao carregar dados do cliente');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao buscar cliente');
                });
        }
        
        async function abrirFinanceiro(id) {
            try {
                // Buscar dados completos do cliente (incluindo percentuais)
                const responseCliente = await fetch('api/obter_cliente.php?id=' + id);
                const dataCliente = await responseCliente.json();
                
                if (!dataCliente.sucesso) {
                    alert('Erro ao carregar dados do cliente');
                    return;
                }
                
                const clienteCompleto = dataCliente.cliente;
                
                // Buscar documentos financeiros
                const responseDoc = await fetch('api/obter_documentos_financeiros.php?cliente_id=' + id);
                const dataDoc = await responseDoc.json();
                
                if (dataDoc.sucesso) {
                    mostrarModalFinanceiro(clienteCompleto, dataDoc.documentos);
                } else {
                    alert('Erro ao carregar documentos financeiros');
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao buscar dados financeiros');
            }
        }
        
        async function abrirAtendimento(id) {
            const cliente = todosClientes.find(c => c.id === id);
            
            // Abrir modal de atendimentos do cliente
            try {
                const response = await fetch(`api/atendimentos.php?cliente_id=${id}`);
                const resultado = await response.json();
                
                if (resultado.success) {
                    mostrarModalAtendimentos(cliente, resultado.atendimentos);
                } else {
                    alert('Erro ao carregar atendimentos');
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao carregar atendimentos');
            }
        }
        
        // ============================================
        // MODAL DE EQUIPAMENTOS
        // ============================================
        
        let equipamentosClienteAtual = [];
        let equipamentoAtual = null;
        let clienteEquipamentoAtual = null;
        
        async function abrirEquipamento(id) {
            const cliente = todosClientes.find(c => c.id === id);
            if (!cliente) {
                alert('Cliente não encontrado!');
                return;
            }
            
            clienteEquipamentoAtual = cliente;
            
            // Buscar equipamentos do cliente
            try {
                const response = await fetch(`api/equipamentos.php?cliente_id=${id}`);
                const data = await response.json();
                
                if (data.sucesso) {
                    equipamentosClienteAtual = data.equipamentos || [];
                    mostrarModalEquipamentos(cliente, equipamentosClienteAtual);
                } else {
                    console.error('Erro ao carregar equipamentos:', data.mensagem);
                    equipamentosClienteAtual = [];
                    mostrarModalEquipamentos(cliente, []);
                }
            } catch (error) {
                console.error('Erro:', error);
                equipamentosClienteAtual = [];
                mostrarModalEquipamentos(cliente, []);
            }
        }
        
        function mostrarModalEquipamentos(cliente, equipamentos) {
            // Preencher informações do cliente
            document.getElementById('infoClienteEquipamento').innerHTML = `
                <strong>Cliente:</strong> ${cliente.nome || cliente.nome_razao_social}<br>
                <strong>Código:</strong> ${cliente.codigo}<br>
                <strong>CPF/CNPJ:</strong> ${cliente.cpfCnpj || cliente.cpf_cnpj || '-'}
            `;
            
            // Guardar ID do cliente para novo equipamento
            document.getElementById('btnNovoEquipamento').setAttribute('data-cliente-id', cliente.id);
            
            // Preencher tabela de equipamentos
            const tbody = document.getElementById('tabelaEquipamentos');
            
            if (equipamentos && equipamentos.length > 0) {
                let html = '';
                
                equipamentos.forEach(equip => {
                    const badgeSituacao = {
                        'ativo': 'bg-success',
                        'inativo': 'bg-danger',
                        'manutencao': 'bg-warning',
                        'substituido': 'bg-secondary'
                    };
                    
                    const situacaoTexto = {
                        'ativo': 'Ativo',
                        'inativo': 'Inativo',
                        'manutencao': 'Manutenção',
                        'substituido': 'Substituído'
                    };
                    
                    html += `
                        <tr>
                            <td>#${equip.id}</td>
                            <td>${equip.tipo || '-'}</td>
                            <td>${equip.descricao || '-'}</td>
                            <td>${equip.marca || '-'}${equip.modelo ? ' / ' + equip.modelo : ''}</td>
                            <td>${equip.numero_serie || '-'}</td>
                            <td><span class="badge ${badgeSituacao[equip.situacao] || 'bg-secondary'}">${situacaoTexto[equip.situacao] || equip.situacao}</span></td>
                            <td>${equip.data_cadastro_fmt || '-'}</td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-outline-primary" onclick="visualizarEquipamento(${equip.id})" title="Ver Detalhes">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-warning" onclick="editarEquipamentoDireto(${equip.id})" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = html;
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4"><i class="fas fa-inbox me-2"></i>Nenhum equipamento cadastrado</td></tr>';
            }
            
            new bootstrap.Modal(document.getElementById('modalEquipamentos')).show();
        }
        
        function abrirNovoEquipamento() {
            const clienteId = document.getElementById('btnNovoEquipamento').getAttribute('data-cliente-id');
            
            // Fechar modal de lista
            bootstrap.Modal.getInstance(document.getElementById('modalEquipamentos')).hide();
            
            // Limpar formulário
            document.getElementById('formNovoEquipamento').reset();
            document.getElementById('novoEquipamentoClienteId').value = clienteId;
            
            // Abrir modal de novo equipamento
            new bootstrap.Modal(document.getElementById('modalNovoEquipamento')).show();
        }
        
        async function salvarNovoEquipamento() {
            const form = document.getElementById('formNovoEquipamento');
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const equipamento = {
                cliente_id: document.getElementById('novoEquipamentoClienteId').value,
                tipo: document.getElementById('equipTipo').value,
                descricao: document.getElementById('equipDescricao').value,
                marca: document.getElementById('equipMarca').value,
                modelo: document.getElementById('equipModelo').value,
                numero_serie: document.getElementById('equipNumeroSerie').value,
                data_instalacao: document.getElementById('equipDataInstalacao').value,
                localizacao: document.getElementById('equipLocalizacao').value,
                situacao: document.getElementById('equipSituacao').value,
                observacoes: document.getElementById('equipObservacoes').value
            };
            
            try {
                const response = await fetch('api/equipamentos.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(equipamento)
                });
                
                const resultado = await response.json();
                
                if (resultado.sucesso) {
                    alert('✅ Equipamento cadastrado com sucesso!');
                    
                    // Fechar modal
                    bootstrap.Modal.getInstance(document.getElementById('modalNovoEquipamento')).hide();
                    
                    // Recarregar lista de equipamentos
                    if (clienteEquipamentoAtual) {
                        setTimeout(() => {
                            abrirEquipamento(clienteEquipamentoAtual.id);
                        }, 500);
                    }
                } else {
                    alert('❌ Erro ao salvar equipamento: ' + (resultado.mensagem || 'Erro desconhecido'));
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('❌ Erro ao salvar equipamento!');
            }
        }
        
        function visualizarEquipamento(id) {
            const equipamento = equipamentosClienteAtual.find(e => e.id === id);
            if (!equipamento) {
                alert('Equipamento não encontrado!');
                return;
            }
            
            equipamentoAtual = equipamento;
            
            // Fechar modal de lista
            bootstrap.Modal.getInstance(document.getElementById('modalEquipamentos')).hide();
            
            const situacaoTexto = {
                'ativo': 'Ativo',
                'inativo': 'Inativo',
                'manutencao': 'Em Manutenção',
                'substituido': 'Substituído'
            };
            
            const situacaoClass = {
                'ativo': 'success',
                'inativo': 'danger',
                'manutencao': 'warning',
                'substituido': 'secondary'
            };
            
            const conteudo = `
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informações do Equipamento #${equipamento.id}</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="30%">Tipo:</th>
                                        <td><strong>${equipamento.tipo || '-'}</strong></td>
                                    </tr>
                                    <tr>
                                        <th>Descrição:</th>
                                        <td>${equipamento.descricao || '-'}</td>
                                    </tr>
                                    <tr>
                                        <th>Marca:</th>
                                        <td>${equipamento.marca || '-'}</td>
                                    </tr>
                                    <tr>
                                        <th>Modelo:</th>
                                        <td>${equipamento.modelo || '-'}</td>
                                    </tr>
                                    <tr>
                                        <th>Número de Série:</th>
                                        <td>${equipamento.numero_serie || '-'}</td>
                                    </tr>
                                    <tr>
                                        <th>Data de Instalação:</th>
                                        <td>${equipamento.data_instalacao_fmt || equipamento.data_instalacao || '-'}</td>
                                    </tr>
                                    <tr>
                                        <th>Localização:</th>
                                        <td>${equipamento.localizacao || '-'}</td>
                                    </tr>
                                    <tr>
                                        <th>Situação:</th>
                                        <td><span class="badge bg-${situacaoClass[equipamento.situacao] || 'secondary'}">${situacaoTexto[equipamento.situacao] || equipamento.situacao}</span></td>
                                    </tr>
                                    <tr>
                                        <th>Data de Cadastro:</th>
                                        <td>${equipamento.data_cadastro_fmt || '-'}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    ${equipamento.observacoes ? `
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Observações</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-0" style="white-space: pre-wrap;">${equipamento.observacoes}</p>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('conteudoVisualizarEquipamento').innerHTML = conteudo;
            new bootstrap.Modal(document.getElementById('modalVisualizarEquipamento')).show();
        }
        
        function editarEquipamentoDireto(id) {
            const equipamento = equipamentosClienteAtual.find(e => e.id === id);
            if (!equipamento) {
                alert('Equipamento não encontrado!');
                return;
            }
            
            equipamentoAtual = equipamento;
            
            // Fechar modal de lista
            bootstrap.Modal.getInstance(document.getElementById('modalEquipamentos')).hide();
            
            // Abrir modal de edição
            abrirEditarEquipamentoModal();
        }
        
        function abrirEditarEquipamentoModal() {
            if (!equipamentoAtual) {
                alert('Nenhum equipamento selecionado!');
                return;
            }
            
            // Fechar modal de detalhes se estiver aberto
            const modalDetalhes = bootstrap.Modal.getInstance(document.getElementById('modalVisualizarEquipamento'));
            if (modalDetalhes) {
                modalDetalhes.hide();
            }
            
            // Preencher formulário de edição
            document.getElementById('editarEquipamentoId').value = equipamentoAtual.id;
            document.getElementById('editEquipTipo').value = equipamentoAtual.tipo || '';
            document.getElementById('editEquipDescricao').value = equipamentoAtual.descricao || '';
            document.getElementById('editEquipMarca').value = equipamentoAtual.marca || '';
            document.getElementById('editEquipModelo').value = equipamentoAtual.modelo || '';
            document.getElementById('editEquipNumeroSerie').value = equipamentoAtual.numero_serie || '';
            document.getElementById('editEquipDataInstalacao').value = equipamentoAtual.data_instalacao || '';
            document.getElementById('editEquipLocalizacao').value = equipamentoAtual.localizacao || '';
            document.getElementById('editEquipSituacao').value = equipamentoAtual.situacao || 'ativo';
            document.getElementById('editEquipObservacoes').value = equipamentoAtual.observacoes || '';
            
            // Abrir modal de edição
            new bootstrap.Modal(document.getElementById('modalEditarEquipamento')).show();
        }
        
        async function salvarEdicaoEquipamento() {
            const form = document.getElementById('formEditarEquipamento');
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const equipamento = {
                id: document.getElementById('editarEquipamentoId').value,
                tipo: document.getElementById('editEquipTipo').value,
                descricao: document.getElementById('editEquipDescricao').value,
                marca: document.getElementById('editEquipMarca').value,
                modelo: document.getElementById('editEquipModelo').value,
                numero_serie: document.getElementById('editEquipNumeroSerie').value,
                data_instalacao: document.getElementById('editEquipDataInstalacao').value,
                localizacao: document.getElementById('editEquipLocalizacao').value,
                situacao: document.getElementById('editEquipSituacao').value,
                observacoes: document.getElementById('editEquipObservacoes').value
            };
            
            try {
                const response = await fetch('api/equipamentos.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(equipamento)
                });
                
                const resultado = await response.json();
                
                if (resultado.sucesso) {
                    alert('✅ Equipamento atualizado com sucesso!');
                    
                    // Fechar modal
                    bootstrap.Modal.getInstance(document.getElementById('modalEditarEquipamento')).hide();
                    
                    // Recarregar lista de equipamentos
                    if (clienteEquipamentoAtual) {
                        setTimeout(() => {
                            abrirEquipamento(clienteEquipamentoAtual.id);
                        }, 500);
                    }
                } else {
                    alert('❌ Erro ao atualizar equipamento: ' + (resultado.mensagem || 'Erro desconhecido'));
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('❌ Erro ao salvar alterações!');
            }
        }
        
        // Tornar funções globais
        window.abrirNovoEquipamento = abrirNovoEquipamento;
        window.salvarNovoEquipamento = salvarNovoEquipamento;
        window.visualizarEquipamento = visualizarEquipamento;
        window.editarEquipamentoDireto = editarEquipamentoDireto;
        window.abrirEditarEquipamentoModal = abrirEditarEquipamentoModal;
        window.salvarEdicaoEquipamento = salvarEdicaoEquipamento;
        
        // Event Listeners dos Botões
        
        // Busca rápida
        document.getElementById('quickSearch').addEventListener('input', function(e) {
            termoBusca = e.target.value;
            paginaAtual = 1;
            renderizarTabela();
        });
        
        // Botão Pesquisar
        document.getElementById('btnPesquisar').addEventListener('click', function() {
            const termo = document.getElementById('quickSearch').value;
            if (termo) {
                alert('Pesquisando por: ' + termo);
            } else {
                alert('Digite um termo de busca!');
            }
        });
        
        // Botão Ver Todos
        document.getElementById('btnVerTodos').addEventListener('click', function() {
            mostrarTodos = !mostrarTodos;
            paginaAtual = 1;
            
            // Alterar aparência do botão
            if (mostrarTodos) {
                this.classList.remove('btn-refresh');
                this.classList.add('btn-success');
                this.innerHTML = '<i class="fas fa-check-circle me-1"></i> Mostrando Todos';
            } else {
                this.classList.remove('btn-success');
                this.classList.add('btn-refresh');
                this.innerHTML = '<i class="fas fa-sync-alt me-1"></i> Ver Todos';
            }
            
            renderizarTabela();
        });
        
        // Botão Visualizar (altera registros por página)
        document.getElementById('btnVisualizar').addEventListener('click', function() {
            registrosPorPagina = parseInt(document.getElementById('registrosPorPagina').value);
            paginaAtual = 1;
            renderizarTabela();
        });
        
        // Input registros por página (Enter)
        document.getElementById('registrosPorPagina').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                registrosPorPagina = parseInt(this.value);
                paginaAtual = 1;
                renderizarTabela();
            }
        });
        
        // Botões de Exportar, Importar e Relatório foram removidos da interface
        
        // Tornar funções globais
        window.irParaPagina = irParaPagina;
        window.abrirVisualizar = abrirVisualizar;
        window.abrirEditar = abrirEditar;
        window.abrirFinanceiro = abrirFinanceiro;
        window.abrirAtendimento = abrirAtendimento;
        window.abrirEquipamento = abrirEquipamento;
        
        // Renderizar tabela inicialmente (mostrando "Carregando...")
        renderizarTabela();
        
        // Carregar clientes do banco
        carregarClientesDoBanco();

        // ===== MODAL DE CADASTRO DE CLIENTE =====
        
        // Abrir modal ao clicar no botão Novo
        const btnNovo = document.getElementById('btnNovo');
        const modalNovoCliente = new bootstrap.Modal(document.getElementById('modalNovoCliente'));
        
        // Buscar unidade da empresa
        let unidadeEmpresa = '';
        
        async function buscarUnidadeEmpresa() {
            try {
                const response = await fetch('api/obter_unidade_empresa.php');
                const data = await response.json();
                if (data.sucesso) {
                    unidadeEmpresa = data.unidade;
                    console.log('Unidade da empresa:', unidadeEmpresa);
                }
            } catch (error) {
                console.error('Erro ao buscar unidade:', error);
                unidadeEmpresa = 'Matriz'; // Valor padrão
            }
        }
        
        // Buscar unidade ao carregar a página
        buscarUnidadeEmpresa();
        
        // Variável para controlar se está editando
        let clienteEditandoId = null;
        
        btnNovo.addEventListener('click', function() {
            // Modo cadastro (não edição)
            clienteEditandoId = null;
            
            // Alterar título do modal
            document.getElementById('modalNovoClienteLabel').innerHTML = '<i class="fas fa-user-plus me-2"></i>Cadastro de Cliente';
            
            // Limpar o formulário
            document.getElementById('formNovoCliente').reset();
            
            // Preencher unidade automaticamente
            document.getElementById('unidade').value = unidadeEmpresa || 'Matriz';
            
            // Abrir o modal
            modalNovoCliente.show();
        });
        
        // Função para preencher modal em modo edição
        async function preencherModalEdicao(cliente) {
            clienteEditandoId = cliente.id;
            
            // Alterar título do modal
            document.getElementById('modalNovoClienteLabel').innerHTML = '<i class="fas fa-user-edit me-2"></i>Editar Cliente - Código: ' + cliente.codigo;
            
            // Preencher todos os campos
            document.getElementById('tipoPessoa').value = cliente.tipo_pessoa;
            document.getElementById('cpfCnpj').value = cliente.cpf_cnpj;
            document.getElementById('nomeRazao').value = cliente.nome_razao_social;
            document.getElementById('sigla').value = cliente.sigla_fantasia || '';
            document.getElementById('cep').value = cliente.cep || '';
            document.getElementById('logradouro').value = cliente.logradouro || '';
            document.getElementById('numero').value = cliente.numero || '';
            document.getElementById('complemento').value = cliente.complemento || '';
            document.getElementById('bairro').value = cliente.bairro || '';
            document.getElementById('cidade').value = cliente.cidade || '';
            document.getElementById('uf').value = cliente.uf || '';
            document.getElementById('telefone').value = cliente.telefone || '';
            document.getElementById('celular').value = cliente.celular || '';
            document.getElementById('email').value = cliente.email || '';
            document.getElementById('identificador').value = cliente.identificador || '';
            document.getElementById('placa').value = cliente.placa || '';
            document.getElementById('conjunto').value = cliente.conjunto || '';
            document.getElementById('matricula').value = cliente.matricula || '';
            
            // SEMPRE buscar a unidade atual da empresa (não usar a antiga do cliente)
            document.getElementById('unidade').value = unidadeEmpresa || 'Carregando...';
            
            document.getElementById('porcentagemRecorrencia').value = cliente.porcentagem_recorrencia || '0.00';
            document.getElementById('observacoes').value = cliente.observacoes || '';
            document.getElementById('situacao').value = cliente.situacao;
            
            // Abrir modal
            modalNovoCliente.show();
        }

        // Salvar cliente
        document.getElementById('btnSalvarCliente').addEventListener('click', function() {
            const form = document.getElementById('formNovoCliente');
            
            // Validar formulário
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            // Coletar dados
            const cliente = {
                tipoPessoa: document.getElementById('tipoPessoa').value,
                cpfCnpj: document.getElementById('cpfCnpj').value,
                situacao: document.getElementById('situacao').value,
                nomeRazao: document.getElementById('nomeRazao').value,
                sigla: document.getElementById('sigla').value,
                cep: document.getElementById('cep').value,
                logradouro: document.getElementById('logradouro').value,
                numero: document.getElementById('numero').value,
                complemento: document.getElementById('complemento').value,
                bairro: document.getElementById('bairro').value,
                cidade: document.getElementById('cidade').value,
                uf: document.getElementById('uf').value,
                telefone: document.getElementById('telefone').value,
                celular: document.getElementById('celular').value,
                email: document.getElementById('email').value,
                identificador: document.getElementById('identificador').value,
                placa: document.getElementById('placa').value,
                conjunto: document.getElementById('conjunto').value,
                matricula: document.getElementById('matricula').value,
                unidade: document.getElementById('unidade').value,
                porcentagemRecorrencia: document.getElementById('porcentagemRecorrencia').value,
                observacoes: document.getElementById('observacoes').value
            };
            
            console.log('Dados do cliente:', cliente);
            
            // Desabilitar botão para evitar duplo clique
            const btnSalvar = document.getElementById('btnSalvarCliente');
            btnSalvar.disabled = true;
            btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Salvando...';
            
            // Se está editando, adicionar o ID ao objeto
            if (clienteEditandoId) {
                cliente.id = clienteEditandoId;
            }
            
            // Enviar para API
            const endpoint = clienteEditandoId ? 'api/editar_cliente.php' : 'api/salvar_cliente.php';
            
            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(cliente)
            })
            .then(response => response.json())
            .then(data => {
                btnSalvar.disabled = false;
                btnSalvar.innerHTML = '<i class="fas fa-save me-1"></i> Salvar Cliente';
                
                if (data.sucesso) {
                    const acao = clienteEditandoId ? 'atualizado' : 'cadastrado';
                    alert(`✅ Cliente ${acao} com sucesso!\n\nCódigo: ${data.codigo}\nNome: ${cliente.nomeRazao}`);
                    modalNovoCliente.hide();
                    
                    // Recarregar clientes do banco
                    carregarClientesDoBanco();
                } else {
                    alert('❌ Erro ao salvar cliente:\n\n' + data.mensagem);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                btnSalvar.disabled = false;
                btnSalvar.innerHTML = '<i class="fas fa-save me-1"></i> Salvar Cliente';
                alert('❌ Erro ao salvar cliente!\n\nVerifique o console (F12) para mais detalhes.');
            });
        });

        // ===== MÁSCARAS DE ENTRADA =====
        
        // Máscara para CPF/CNPJ
        document.getElementById('cpfCnpj').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            if (value.length <= 11) {
                // CPF: 000.000.000-00
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            } else {
                // CNPJ: 00.000.000/0000-00
                value = value.replace(/^(\d{2})(\d)/, '$1.$2');
                value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
                value = value.replace(/(\d{4})(\d)/, '$1-$2');
            }
            
            e.target.value = value;
        });

        // Máscara para CEP
        document.getElementById('cep').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/^(\d{5})(\d)/, '$1-$2');
            e.target.value = value;
        });

        // Máscara para Telefone
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/^(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{4})(\d)/, '$1-$2');
            e.target.value = value;
        });

        // Máscara para Celular
        document.getElementById('celular').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/^(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{5})(\d)/, '$1-$2');
            e.target.value = value;
        });

        // ===== BUSCA DE CEP =====
        
        document.getElementById('cep').addEventListener('blur', function() {
            const cep = this.value.replace(/\D/g, '');
            
            if (cep.length === 8) {
                // Buscar CEP na API ViaCEP
                fetch(`https://viacep.com.br/ws/${cep}/json/`)
                    .then(response => response.json())
                    .then(data => {
                        if (!data.erro) {
                            document.getElementById('logradouro').value = data.logradouro || '';
                            document.getElementById('bairro').value = data.bairro || '';
                            document.getElementById('cidade').value = data.localidade || '';
                            document.getElementById('uf').value = data.uf || '';
                            // Focar no campo número
                            document.getElementById('numero').focus();
                        } else {
                            alert('CEP não encontrado!');
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao buscar CEP:', error);
                    });
            }
        });

        // ===== AJUSTE DO TIPO DE PESSOA =====
        
        document.getElementById('tipoPessoa').addEventListener('change', function() {
            const tipoPessoa = this.value;
            const cpfCnpjLabel = document.querySelector('label[for="cpfCnpj"]');
            const nomeRazaoLabel = document.querySelector('label[for="nomeRazao"]');
            const siglaLabel = document.querySelector('label[for="sigla"]');
            
            if (tipoPessoa === 'fisica') {
                cpfCnpjLabel.innerHTML = 'CPF <span class="text-danger">*</span>';
                nomeRazaoLabel.innerHTML = 'Nome Completo <span class="text-danger">*</span>';
                siglaLabel.textContent = 'Apelido';
                document.getElementById('cpfCnpj').placeholder = '000.000.000-00';
            } else if (tipoPessoa === 'juridica') {
                cpfCnpjLabel.innerHTML = 'CNPJ <span class="text-danger">*</span>';
                nomeRazaoLabel.innerHTML = 'Razão Social <span class="text-danger">*</span>';
                siglaLabel.textContent = 'Nome Fantasia';
                document.getElementById('cpfCnpj').placeholder = '00.000.000/0000-00';
            }
        });

        // ===== MODAL DE VISUALIZAR CLIENTE =====
        
        function mostrarModalVisualizar(cliente) {
            const conteudo = `
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <h5 class="text-primary"><i class="fas fa-info-circle me-2"></i>Informações Básicas</h5>
                        <table class="table table-bordered">
                            <tr><th width="30%">Código:</th><td>${cliente.codigo || '-'}</td></tr>
                            <tr><th>Tipo de Pessoa:</th><td>${cliente.tipo_pessoa === 'fisica' ? 'Pessoa Física' : 'Pessoa Jurídica'}</td></tr>
                            <tr><th>CPF/CNPJ:</th><td>${cliente.cpf_cnpj}</td></tr>
                            <tr><th>Nome/Razão Social:</th><td><strong>${cliente.nome_razao_social}</strong></td></tr>
                            <tr><th>Sigla/Fantasia:</th><td>${cliente.sigla_fantasia || '-'}</td></tr>
                            <tr><th>Situação:</th><td><span class="badge bg-${cliente.situacao === 'ativo' ? 'success' : 'danger'}">${cliente.situacao === 'ativo' ? 'Ativo' : 'Inativo'}</span></td></tr>
                        </table>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <h5 class="text-primary"><i class="fas fa-map-marker-alt me-2"></i>Endereço</h5>
                        <table class="table table-bordered">
                            <tr><th width="30%">CEP:</th><td>${cliente.cep || '-'}</td></tr>
                            <tr><th>Logradouro:</th><td>${cliente.logradouro || '-'}</td></tr>
                            <tr><th>Número:</th><td>${cliente.numero || '-'}</td></tr>
                            <tr><th>Complemento:</th><td>${cliente.complemento || '-'}</td></tr>
                            <tr><th>Bairro:</th><td>${cliente.bairro || '-'}</td></tr>
                            <tr><th>Cidade:</th><td>${cliente.cidade || '-'}</td></tr>
                            <tr><th>UF:</th><td>${cliente.uf || '-'}</td></tr>
                        </table>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <h5 class="text-primary"><i class="fas fa-phone me-2"></i>Contatos</h5>
                        <table class="table table-bordered">
                            <tr><th width="30%">Telefone:</th><td>${cliente.telefone || '-'}</td></tr>
                            <tr><th>Celular:</th><td>${cliente.celular || '-'}</td></tr>
                            <tr><th>E-mail:</th><td>${cliente.email || '-'}</td></tr>
                        </table>
                        
                        <h5 class="text-primary mt-3"><i class="fas fa-id-card me-2"></i>Dados Adicionais</h5>
                        <table class="table table-bordered">
                            <tr><th width="30%">Identificador:</th><td>${cliente.identificador || '-'}</td></tr>
                            <tr><th>PLACA:</th><td>${cliente.placa || '-'}</td></tr>
                            <tr><th>CONJUNTO:</th><td>${cliente.conjunto || '-'}</td></tr>
                            <tr><th>MATRÍCULA:</th><td>${cliente.matricula || '-'}</td></tr>
                        </table>
                        
                        <h5 class="text-primary mt-3"><i class="fas fa-dollar-sign me-2"></i>Financeiro</h5>
                        <table class="table table-bordered">
                            <tr><th width="30%">Unidade:</th><td>${cliente.unidade || '-'}</td></tr>
                            <tr><th>% Recorrência:</th><td>${cliente.porcentagem_recorrencia || '0.00'}%</td></tr>
                        </table>
                    </div>
                    
                    ${cliente.observacoes ? `
                    <div class="col-md-12">
                        <h5 class="text-primary"><i class="fas fa-sticky-note me-2"></i>Observações</h5>
                        <div class="alert alert-info">${cliente.observacoes}</div>
                    </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('conteudoVisualizarCliente').innerHTML = conteudo;
            new bootstrap.Modal(document.getElementById('modalVisualizarCliente')).show();
        }

        // ===== MODAL DE POSIÇÃO FINANCEIRA =====
        
        function mostrarModalFinanceiro(cliente, documentos) {
            // Buscar percentuais da empresa e do cliente
            const percentualEmpresa = parseFloat(cliente.percentual_empresa || 0);
            const percentualCliente = parseFloat(cliente.porcentagem_recorrencia || 0);
            const percentualTotal = percentualEmpresa + percentualCliente;
            
            console.log('Cliente para modal financeiro:', cliente);
            
            // Informações do cliente (usando nomes corretos dos campos)
            const infoCliente = `
                <div class="alert alert-info">
                    <strong><i class="fas fa-user me-2"></i>Cliente:</strong> ${cliente.nome_razao_social || cliente.nome || 'N/A'} (${cliente.identificador || cliente.codigo || 'N/A'})<br>
                    <strong><i class="fas fa-id-card me-2"></i>CPF/CNPJ:</strong> ${cliente.cpf_cnpj || cliente.cpfCnpj || 'N/A'}<br>
                    <strong><i class="fas fa-building me-2"></i>Unidade:</strong> ${cliente.unidade_empresa || cliente.unidade || 'N/A'}<br>
                    <strong><i class="fas fa-percent me-2"></i>Comissão:</strong> ${percentualTotal.toFixed(2)}% (Empresa: ${percentualEmpresa.toFixed(2)}% + Cliente: ${percentualCliente.toFixed(2)}%)
                </div>
            `;
            document.getElementById('infoClienteFinanceiro').innerHTML = infoCliente;
            
            // Preencher tabela de documentos
            const tbody = document.getElementById('tabelaDocumentosFinanceiros');
            
            if (documentos && documentos.length > 0) {
                let html = '';
                let totalValor = 0;
                let totalPago = 0;
                let totalComissao = 0;
                
                documentos.forEach(doc => {
                    const valorDoc = parseFloat(doc.valor || 0);
                    const valorPago = parseFloat(doc.valor_pago || 0);
                    
                    // Calcular comissão: valor pago * (% empresa + % cliente)
                    const comissao = valorPago * (percentualTotal / 100);
                    
                    totalValor += valorDoc;
                    totalPago += valorPago;
                    totalComissao += comissao;
                    
                    const badgeStatus = {
                        'pendente': 'bg-warning',
                        'pago': 'bg-success',
                        'vencido': 'bg-danger',
                        'cancelado': 'bg-secondary'
                    };
                    
                    html += `
                        <tr>
                            <td>${doc.unidade || '-'}</td>
                            <td>${doc.identificador || '-'}</td>
                            <td>${doc.numero_documento || '-'}</td>
                            <td>${doc.associado || '-'}</td>
                            <td>${doc.classe || '-'}</td>
                            <td>${doc.data_emissao || '-'}</td>
                            <td>${doc.data_vencimento || '-'}</td>
                            <td>R$ ${valorDoc.toFixed(2).replace('.', ',')}</td>
                            <td>${doc.placa || '-'}</td>
                            <td>${doc.conjunto || '-'}</td>
                            <td>${doc.matricula || '-'}</td>
                            <td><span class="badge ${badgeStatus[doc.status] || 'bg-secondary'}">${doc.status || '-'}</span></td>
                            <td>R$ ${valorPago.toFixed(2).replace('.', ',')}</td>
                            <td>${doc.data_baixa || '-'}</td>
                            <td><strong style="color: #28a745;">R$ ${comissao.toFixed(2).replace('.', ',')}</strong></td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = html;
                
                // Resumo financeiro
                const resumo = `
                    <div class="row">
                        <div class="col-md-3">
                            <div class="alert alert-primary">
                                <strong>Total dos Documentos:</strong><br>
                                R$ ${totalValor.toFixed(2).replace('.', ',')}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="alert alert-success">
                                <strong>Total Pago:</strong><br>
                                R$ ${totalPago.toFixed(2).replace('.', ',')}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="alert alert-warning">
                                <strong>Comissão (${percentualTotal.toFixed(2)}%):</strong><br>
                                R$ ${totalComissao.toFixed(2).replace('.', ',')}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="alert alert-info">
                                <strong>Líquido para Cliente:</strong><br>
                                R$ ${(totalPago - totalComissao).toFixed(2).replace('.', ',')}
                            </div>
                        </div>
                    </div>
                `;
                document.getElementById('resumoFinanceiro').innerHTML = resumo;
            } else {
                tbody.innerHTML = '<tr><td colspan="15" class="text-center">Nenhum documento financeiro encontrado</td></tr>';
                document.getElementById('resumoFinanceiro').innerHTML = '';
            }
            
            new bootstrap.Modal(document.getElementById('modalPosicaoFinanceira')).show();
        }

        // ============================================
        // MODAL DE ATENDIMENTOS
        // ============================================
        
        function mostrarModalAtendimentos(cliente, atendimentos) {
            // Armazenar atendimentos globalmente
            atendimentosClienteAtual = atendimentos || [];
            
            // Preencher informações do cliente
            document.getElementById('infoClienteAtendimento').innerHTML = `
                <strong>Cliente:</strong> ${cliente.nome || cliente.nome_razao_social}<br>
                <strong>Código:</strong> ${cliente.codigo}<br>
                <strong>CPF/CNPJ:</strong> ${cliente.cpfCnpj || cliente.cpf_cnpj || '-'}<br>
                <strong>Identificador:</strong> ${cliente.identificador || '-'}
            `;
            
            // Preencher tabela de atendimentos
            const tbody = document.getElementById('tabelaAtendimentos');
            
            if (atendimentos && atendimentos.length > 0) {
                let html = '';
                
                atendimentos.forEach(atend => {
                    const badgeStatus = {
                        'aberto': 'bg-success',
                        'em_andamento': 'bg-primary',
                        'aguardando': 'bg-warning',
                        'resolvido': 'bg-success',
                        'fechado': 'bg-secondary',
                        'cancelado': 'bg-danger'
                    };
                    
                    const badgePrioridade = {
                        'baixa': 'bg-info',
                        'media': 'bg-warning',
                        'alta': 'bg-danger',
                        'urgente': 'bg-danger'
                    };
                    
                    html += `
                        <tr>
                            <td>#${atend.id}</td>
                            <td>${atend.data_abertura_fmt || '-'}</td>
                            <td>${atend.assunto || atend.titulo || '-'}</td>
                            <td>${atend.tipo || '-'}</td>
                            <td><span class="badge ${badgePrioridade[atend.prioridade] || 'bg-secondary'}">${atend.prioridade || 'média'}</span></td>
                            <td><span class="badge ${badgeStatus[atend.status] || 'bg-secondary'}">${atend.status || 'aberto'}</span></td>
                            <td>${atend.usuario_nome || '-'}</td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-outline-primary" onclick="verDetalhesAtendimentoCliente(${atend.id})" title="Ver Detalhes">
                                    <i class="fas fa-eye"></i>
                                </button>
                                    <button class="btn btn-sm btn-outline-warning" onclick="editarAtendimentoCliente(${atend.id})" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = html;
            } else {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4"><i class="fas fa-inbox me-2"></i>Nenhum atendimento encontrado</td></tr>';
            }
            
            // Guardar ID do cliente para novo atendimento
            document.getElementById('btnNovoAtendimento').setAttribute('data-cliente-id', cliente.id);
            
            new bootstrap.Modal(document.getElementById('modalAtendimentos')).show();
        }
        
        // Variável global para armazenar o atendimento atual
        let atendimentoAtual = null;
        let atendimentosClienteAtual = [];
        
        // Função para visualizar atendimento do modal de clientes
        async function verDetalhesAtendimentoCliente(id) {
            // Buscar na lista de atendimentos já carregados
            const atendimento = atendimentosClienteAtual.find(a => a.id == id);
            
            if (atendimento) {
                atendimentoAtual = atendimento;
                
                // Fechar modal de atendimentos do cliente
                const modalAtendimentos = bootstrap.Modal.getInstance(document.getElementById('modalAtendimentos'));
                if (modalAtendimentos) {
                    modalAtendimentos.hide();
                }
                
                // Mostrar detalhes
                mostrarDetalhesAtendimento(atendimento);
            } else {
                // Se não encontrou, buscar na API
                try {
                    const response = await fetch(`api/atendimentos.php`);
                    const data = await response.json();
                    
                    if (data.success) {
                        const atendimentoEncontrado = data.atendimentos.find(a => a.id == id);
                        
                        if (atendimentoEncontrado) {
                            atendimentoAtual = atendimentoEncontrado;
                            mostrarDetalhesAtendimento(atendimentoEncontrado);
                        } else {
                            alert('Atendimento não encontrado!');
                        }
                    } else {
                        alert('Erro ao buscar atendimento');
                    }
                } catch (error) {
                    console.error('Erro:', error);
                    alert('Erro ao carregar detalhes do atendimento');
                }
            }
        }
        
        // Função para editar atendimento do modal de clientes
        function editarAtendimentoCliente(id) {
            // Buscar na lista de atendimentos já carregados
            const atendimento = atendimentosClienteAtual.find(a => a.id == id);
            
            if (atendimento) {
                atendimentoAtual = atendimento;
                
                // Fechar modal de atendimentos do cliente
                const modalAtendimentos = bootstrap.Modal.getInstance(document.getElementById('modalAtendimentos'));
                if (modalAtendimentos) {
                    modalAtendimentos.hide();
                }
                
                // Abrir modal de edição diretamente
                abrirEditarAtendimentoModal();
            } else {
                alert('Atendimento não encontrado!');
            }
        }
        
        async function verDetalhesAtendimento(id) {
            try {
                // Buscar detalhes do atendimento na API
                const response = await fetch(`api/atendimentos.php?cliente_id=0`);
                const data = await response.json();
                
                if (data.success) {
                    const atendimento = data.atendimentos.find(a => a.id == id);
                    
                    if (atendimento) {
                        atendimentoAtual = atendimento;
                        mostrarDetalhesAtendimento(atendimento);
                    } else {
                        alert('Atendimento não encontrado!');
                    }
                } else {
                    alert('Erro ao buscar atendimento');
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao carregar detalhes do atendimento');
            }
        }
        
        // Tornar funções globais
        window.verDetalhesAtendimentoCliente = verDetalhesAtendimentoCliente;
        window.editarAtendimentoCliente = editarAtendimentoCliente;
        
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
        
        function abrirEditarAtendimentoModal() {
            if (!atendimentoAtual) {
                alert('Nenhum atendimento selecionado!');
                return;
            }
            
            // Fechar modal de detalhes
            bootstrap.Modal.getInstance(document.getElementById('modalDetalhesAtendimento')).hide();
            
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
                    
                    // Fechar modal de edição
                    bootstrap.Modal.getInstance(document.getElementById('modalEditarAtendimento')).hide();
                    
                    // Se tiver cliente_id, recarregar lista de atendimentos do cliente
                    if (atendimentoAtual && atendimentoAtual.seguro_cliente_id) {
                        setTimeout(() => {
                            if (typeof abrirAtendimento === 'function') {
                                abrirAtendimento(atendimentoAtual.seguro_cliente_id);
                            }
                        }, 500);
                    }
                } else {
                    alert('❌ Erro ao atualizar atendimento: ' + (resultado.error || resultado.mensagem));
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('❌ Erro ao salvar alterações!');
            }
        }
        
        // Tornar funções globais
        window.verDetalhesAtendimento = verDetalhesAtendimento;
        window.abrirEditarAtendimentoModal = abrirEditarAtendimentoModal;
        window.salvarEdicaoAtendimento = salvarEdicaoAtendimento;
        
        function abrirNovoAtendimento() {
            const clienteId = document.getElementById('btnNovoAtendimento').getAttribute('data-cliente-id');
            // Fechar modal de lista
            bootstrap.Modal.getInstance(document.getElementById('modalAtendimentos')).hide();
            // Abrir modal de novo atendimento
            document.getElementById('novoAtendimentoClienteId').value = clienteId;
            new bootstrap.Modal(document.getElementById('modalNovoAtendimento')).show();
        }
        
        async function salvarNovoAtendimento() {
            const clienteId = document.getElementById('novoAtendimentoClienteId').value;
            const tipo = document.getElementById('tipoAtendimento').value;
            const prioridade = document.getElementById('prioridadeAtendimento').value;
            const assunto = document.getElementById('assuntoAtendimento').value;
            const descricao = document.getElementById('descricaoAtendimento').value;
            
            if (!assunto || !descricao) {
                alert('Preencha o assunto e a descrição!');
                return;
            }
            
            try {
                const response = await fetch('api/atendimentos.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        cliente_id: clienteId,
                        tipo: tipo,
                        prioridade: prioridade,
                        assunto: assunto,
                        descricao: descricao
                    })
                });
                
                const resultado = await response.json();
                
                if (resultado.success) {
                    alert('Atendimento criado com sucesso!');
                    bootstrap.Modal.getInstance(document.getElementById('modalNovoAtendimento')).hide();
                    // Reabrir modal de atendimentos
                    abrirAtendimento(clienteId);
                } else {
                    alert('Erro ao criar atendimento: ' + (resultado.error || 'Erro desconhecido'));
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao criar atendimento');
            }
        }
    </script>
</body>
</html>