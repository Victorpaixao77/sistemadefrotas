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
                <a class="nav-link" href="contratos.php">
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
                            <th width="15%">CPF/CNPJ</th>
                            <th width="28%">Nome/Razão Social</th>
                            <th width="12%">MATRÍCULA</th>
                            <th width="15%">Cidade</th>
                            <th width="5%">UF</th>
                            <th width="13%">% do Cliente</th>
                            <th width="7%">Situação</th>
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
                        <!-- Campo hidden para ID do cliente -->
                        <input type="hidden" id="clienteId" value="">
                        
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
                                            <option value="aguardando_ativacao">🕐 Aguardando Ativação</option>
                                            <option value="ativo">✅ Ativo</option>
                                            <option value="inativo">❌ Inativo</option>
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
                                    <div class="col-md-6 mb-3">
                                        <label for="matricula" class="form-label">MATRÍCULA (Código do Cliente) <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="matricula" placeholder="Ex: 370" required>
                                        <small class="text-muted">Código único de identificação do cliente</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="unidade" class="form-label">Unidade</label>
                                        <input type="text" class="form-control" id="unidade" placeholder="Nome da unidade" readonly>
                                        <small class="text-muted">Unidade da empresa</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contratos -->
                        <div class="card mb-3">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="fas fa-file-contract me-2"></i>Contratos</h6>
                                <button type="button" class="btn btn-sm btn-primary" id="btnAdicionarContrato">
                                    <i class="fas fa-plus me-1"></i>Adicionar Contrato
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info mb-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Importante:</strong> Cada contrato possui um CONJUNTO único, 
                                    placa(s) vinculada(s) e sua própria porcentagem de recorrência.
                                    </div>
                                
                                <!-- Tabela de contratos -->
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover" id="tabelaContratos">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="12%">CONJUNTO</th>
                                                <th width="20%">Placa(s)</th>
                                                <th width="10%">% Rec.</th>
                                                <th width="10%">Data Início</th>
                                                <th width="12%">Valor Mensal</th>
                                                <th width="18%">Situação</th>
                                                <th width="18%">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody id="listaContratos">
                                            <tr id="semContratos">
                                                <td colspan="7" class="text-center text-muted">
                                                    <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                                    Nenhum contrato cadastrado. Clique em "Adicionar Contrato" para começar.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
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
                    
                    <!-- Legenda de cores -->
                    <div class="alert alert-light border mb-3">
                        <strong><i class="fas fa-info-circle me-2"></i>Legenda:</strong><br>
                        <small>
                            🟢 <strong>Fundo branco:</strong> Documento vinculado a contrato (comissão: % empresa + % contrato)<br>
                            🟡 <strong>Fundo amarelo:</strong> CONJUNTO não vinculado (comissão: apenas % empresa)
                            <i class="fas fa-info-circle text-warning ms-1"></i>
                        </small>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover table-sm" style="font-size: 13px;">
                            <thead class="table-light">
                                <tr>
                                    <th>Unidade</th>
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

    <!-- Modal Adicionar/Editar Contrato -->
    <div class="modal fade" id="modalContrato" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="tituloModalContrato">
                        <i class="fas fa-file-contract me-2"></i>Adicionar Contrato
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formContrato">
                        <input type="hidden" id="contratoId">
                        <input type="hidden" id="contratoClienteId">
                        
                        <div class="mb-3">
                            <label for="contratoMatricula" class="form-label">
                                CONJUNTO <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="contratoMatricula" required
                                   placeholder="Ex: 382">
                            <small class="text-muted">Identificador único do contrato (CONJUNTO)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="contratoPlaca" class="form-label">
                                Placa(s) <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="contratoPlaca" required
                                   placeholder="ABC1D23 ou ABC1D23, DEF4G56">
                            <small class="text-muted">Digite uma ou mais placas separadas por vírgula</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="contratoPorcentagem" class="form-label">
                                % Recorrência <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="contratoPorcentagem" required
                                       min="0" max="100" step="0.01" placeholder="0.00">
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted">Porcentagem de comissão para este contrato</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="contratoDataInicio" class="form-label">
                                    Data de Início
                                </label>
                                <input type="date" class="form-control" id="contratoDataInicio">
                                <small class="text-muted">Quando o contrato iniciou</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="contratoValor" class="form-label">
                                    Valor Mensal
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="number" class="form-control" id="contratoValor"
                                           min="0" step="0.01" placeholder="0.00">
                                </div>
                                <small class="text-muted">Valor mensal do contrato</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="contratoSituacao" class="form-label">
                                Situação <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="contratoSituacao" required>
                                <option value="aguardando_ativacao">Aguardando Ativação</option>
                                <option value="ativo">Ativo</option>
                                <option value="aguardando_link">Aguardando Link</option>
                                <option value="aguardando_vistoria">Aguardando Vistoria</option>
                                <option value="devolvido_para_unidade">Devolvido para Unidade</option>
                                <option value="aguardando_assinatura">Aguardando Assinatura</option>
                                <option value="desistencia">Desistência</option>
                                <option value="negociar_cliente">Negociar Cliente</option>
                            </select>
                            <small class="text-muted">Status atual do contrato</small>
                        </div>
                        
                        <!-- Campos de Controle -->
                        <div class="mb-3">
                            <label for="contratoTipoOs" class="form-label">
                                Tipo de OS
                            </label>
                            <select class="form-select" id="contratoTipoOs">
                                <option value="">Selecione...</option>
                                <option value="ABRIR OS">ABRIR OS</option>
                                <option value="TROCA DE TITULARIDADE">TROCA DE TITULARIDADE</option>
                                <option value="REATIVAÇÃO DE PLACA">REATIVAÇÃO DE PLACA</option>
                                <option value="PREENCHER TIPO DE OS">PREENCHER TIPO DE OS</option>
                                <option value="SEM ORDEM">SEM ORDEM</option>
                            </select>
                            <small class="text-muted">Tipo de Ordem de Serviço</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="contratoEnvioWhatsapp" class="form-label">
                                    Envio WhatsApp
                                </label>
                                <select class="form-select" id="contratoEnvioWhatsapp">
                                    <option value="nao">Não</option>
                                    <option value="sim">Sim</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="contratoEnvioEmail" class="form-label">
                                    Envio E-mail
                                </label>
                                <select class="form-select" id="contratoEnvioEmail">
                                    <option value="nao">Não</option>
                                    <option value="sim">Sim</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label for="contratoPlanilha" class="form-label">
                                    Planilha
                                </label>
                                <select class="form-select" id="contratoPlanilha">
                                    <option value="nao">Não</option>
                                    <option value="sim">Sim</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="contratoObservacoes" class="form-label">Observações</label>
                            <textarea class="form-control" id="contratoObservacoes" rows="3"
                                      placeholder="Informações adicionais sobre o contrato..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarContrato()">
                        <i class="fas fa-save me-1"></i>
                        Salvar Contrato
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
                        porcentagem: c.porcentagem_total_contratos || '0.00',
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
            // Por padrão, mostrar apenas 'ativo' e 'aguardando_ativacao'
            // Ao clicar em "Ver Todos", mostrar também 'inativo'
            let clientes;
            if (mostrarTodos) {
                clientes = todosClientes; // Mostra todos (ativo, inativo, aguardando_ativacao)
            } else {
                // Mostra apenas ativo e aguardando_ativacao
                clientes = todosClientes.filter(c => c.situacao === 'ativo' || c.situacao === 'aguardando_ativacao');
            }
            
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
                tbody.innerHTML = '<tr><td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin"></i> Carregando clientes...</td></tr>';
            } else if (clientesPagina.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center">Nenhum cliente encontrado</td></tr>';
            } else {
                clientesPagina.forEach(cliente => {
                    let badgeClass, badgeText;
                    if (cliente.situacao === 'ativo') {
                        badgeClass = 'bg-success';
                        badgeText = 'Ativo';
                    } else if (cliente.situacao === 'inativo') {
                        badgeClass = 'bg-danger';
                        badgeText = 'Inativo';
                    } else {
                        badgeClass = 'bg-warning';
                        badgeText = 'Aguardando Ativação';
                    }
                    
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
                headerSubtitle.textContent = 'Mostrando Todos os Clientes (Ativos, Inativos e Aguardando Ativação)';
            } else {
                headerSubtitle.textContent = 'Mostrando Clientes Ativos e Aguardando Ativação';
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
            console.log('🔍 Abrindo posição financeira para cliente ID:', id);
            
            try {
                // Buscar dados completos do cliente (incluindo percentuais)
                console.log('📡 Buscando dados do cliente...');
                const responseCliente = await fetch('api/obter_cliente.php?id=' + id);
                console.log('📊 Response cliente status:', responseCliente.status);
                
                const dataCliente = await responseCliente.json();
                console.log('📋 Dados do cliente:', dataCliente);
                
                if (!dataCliente.sucesso) {
                    console.error('❌ Erro ao carregar cliente:', dataCliente);
                    alert('Erro ao carregar dados do cliente');
                    return;
                }
                
                const clienteCompleto = dataCliente.cliente;
                console.log('✅ Cliente carregado:', clienteCompleto);
            
                // Buscar documentos financeiros
                console.log('📡 Buscando documentos financeiros...');
                const responseDoc = await fetch('api/obter_documentos_financeiros.php?cliente_id=' + id);
                console.log('📊 Response documentos status:', responseDoc.status);
                
                const textDoc = await responseDoc.text();
                console.log('📄 Response text (primeiros 300 chars):', textDoc.substring(0, 300));
                
                let dataDoc;
                try {
                    dataDoc = JSON.parse(textDoc);
                    console.log('📋 Documentos parsed:', dataDoc);
                } catch (e) {
                    console.error('❌ Erro ao fazer parse do JSON:', e);
                    console.error('📄 Texto completo:', textDoc);
                    alert('Erro ao carregar documentos financeiros: resposta inválida da API\n\nVerifique o console (F12) para detalhes.');
                    return;
                }
                
                if (dataDoc.sucesso) {
                    console.log('✅ Abrindo modal com', dataDoc.documentos.length, 'documentos');
                    mostrarModalFinanceiro(clienteCompleto, dataDoc.documentos);
                } else {
                    console.error('❌ API retornou erro:', dataDoc.mensagem);
                    if (dataDoc.erro_detalhado) {
                        console.error('🔴 Erro SQL detalhado:', dataDoc.erro_detalhado);
                    }
                    alert('Erro ao carregar documentos financeiros: ' + dataDoc.mensagem + (dataDoc.erro_detalhado ? '\n\nDetalhes: ' + dataDoc.erro_detalhado : ''));
                    return;
                }
            } catch (error) {
                console.error('❌ Erro geral:', error);
                alert('Erro ao buscar dados financeiros: ' + error.message);
            }
        }
        
        async function abrirAtendimento(id) {
            const cliente = todosClientes.find(c => c.id === id);
            
            console.log('Abrindo atendimentos para cliente:', id);
            
            // Abrir modal de atendimentos do cliente
            try {
                const response = await fetch(`api/atendimentos.php?cliente_id=${id}`);
                console.log('Response status:', response.status);
                
                const text = await response.text();
                console.log('Response text:', text.substring(0, 200));
                
                let resultado;
                try {
                    resultado = JSON.parse(text);
                    console.log('Resultado parsed:', resultado);
                } catch (e) {
                    console.error('Erro ao fazer parse do JSON:', e);
                    alert('Erro ao carregar atendimentos: resposta inválida da API');
                    return;
                }
                
                // Verificar ambos os formatos: success (inglês) e sucesso (português)
                if (resultado.success || resultado.sucesso) {
                    mostrarModalAtendimentos(cliente, resultado.atendimentos || []);
                } else {
                    console.error('Erro na API:', resultado);
                    alert('Erro ao carregar atendimentos: ' + (resultado.error || resultado.erro || resultado.mensagem || 'Erro desconhecido'));
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao carregar atendimentos: ' + error.message);
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
            
            // Limpar campo hidden do ID
            document.getElementById('clienteId').value = '';
            
            // Alterar título do modal
            document.getElementById('modalNovoClienteLabel').innerHTML = '<i class="fas fa-user-plus me-2"></i>Cadastro de Cliente';
            
            // Limpar o formulário
            document.getElementById('formNovoCliente').reset();
            
            // Preencher unidade automaticamente
            document.getElementById('unidade').value = unidadeEmpresa || 'Matriz';
            
            // Limpar lista de contratos
            const tbody = document.getElementById('listaContratos');
            tbody.innerHTML = `
                <tr id="semContratos">
                    <td colspan="4" class="text-center text-muted">
                        <i class="fas fa-inbox fa-2x mb-2"></i><br>
                        Salve o cliente primeiro e depois adicione contratos.
                    </td>
                </tr>
            `;
            
            // Abrir o modal
            modalNovoCliente.show();
        });
        
        // Função para preencher modal em modo edição
        async function preencherModalEdicao(cliente) {
            clienteEditandoId = cliente.id;
            
            // Preencher campo hidden do ID
            document.getElementById('clienteId').value = cliente.id;
            
            // Alterar título do modal
            const nomeCliente = cliente.nome_razao_social || cliente.nome || 'Cliente';
            document.getElementById('modalNovoClienteLabel').innerHTML = '<i class="fas fa-user-edit me-2"></i>Editar Cliente - ' + nomeCliente;
            
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
            
            // Dados Adicionais
            document.getElementById('matricula').value = cliente.matricula || '';
            document.getElementById('unidade').value = unidadeEmpresa || 'Carregando...';
            document.getElementById('observacoes').value = cliente.observacoes || '';
            
            // Situação - garantir que tenha um valor válido
            const situacaoCliente = cliente.situacao || 'ativo';
            console.log('Situação do cliente:', situacaoCliente);
            document.getElementById('situacao').value = situacaoCliente;
            
            // Carregar contratos do cliente
            await carregarContratos(cliente.id);
            
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
                // Campos que não existem mais foram removidos:
                // - identificador (removido)
                // - placa (gerenciado por contratos)
                // - conjunto (gerenciado por contratos)
                matricula: document.getElementById('matricula').value,
                // Porcentagem agora é gerenciada pelos contratos individuais
                porcentagemRecorrencia: 0,
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
                            <tr><th>Situação:</th><td><span class="badge bg-${
                                cliente.situacao === 'ativo' ? 'success' : 
                                cliente.situacao === 'inativo' ? 'danger' : 'warning'
                            }">${
                                cliente.situacao === 'ativo' ? '✅ Ativo' : 
                                cliente.situacao === 'inativo' ? '❌ Inativo' : '🕐 Aguardando Ativação'
                            }</span></td></tr>
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
                            <tr><th width="30%">MATRÍCULA:</th><td>${cliente.matricula || '-'}</td></tr>
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
                    
                    // Verificar se documento tem contrato vinculado
                    const temContrato = doc.contrato_id !== null && doc.contrato_id !== undefined;
                    const porcentagemContrato = parseFloat(doc.porcentagem_contrato || 0);
                    
                    // Calcular comissão conforme vinculação:
                    // COM CONTRATO: valor pago * (% empresa + % contrato)
                    // SEM CONTRATO: valor pago * (% empresa apenas)
                    let comissao;
                    let percentualUsado;
                    let avisoSemContrato = '';
                    
                    if (temContrato) {
                        // Documento vinculado a contrato
                        percentualUsado = percentualEmpresa + porcentagemContrato;
                        comissao = valorPago * (percentualUsado / 100);
                    } else {
                        // Documento SEM contrato vinculado (apenas % empresa)
                        percentualUsado = percentualEmpresa;
                        comissao = valorPago * (percentualUsado / 100);
                        avisoSemContrato = ' <i class="fas fa-info-circle text-warning" title="CONJUNTO não vinculado a contrato. Comissão calculada apenas com % da empresa (' + percentualEmpresa.toFixed(2) + '%)"></i>';
                    }
                    
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
                        <tr ${!temContrato ? 'style="background-color: #fff3cd;"' : ''}>
                            <td>${doc.unidade || '-'}</td>
                            <td><strong>${doc.matricula || '-'}</strong></td>
                            <td><strong>${doc.conjunto || '-'}</strong>${avisoSemContrato}</td>
                            <td>${doc.numero_documento || '-'}</td>
                            <td>${doc.associado || '-'}</td>
                            <td>${doc.classe || '-'}</td>
                            <td>${doc.data_emissao || '-'}</td>
                            <td>${doc.data_vencimento || '-'}</td>
                            <td>R$ ${valorDoc.toFixed(2).replace('.', ',')}</td>
                            <td>${doc.placa || '-'}</td>
                            <td><span class="badge ${badgeStatus[doc.status] || 'bg-secondary'}">${doc.status || '-'}</span></td>
                            <td>R$ ${valorPago.toFixed(2).replace('.', ',')}</td>
                            <td>${doc.data_baixa || '-'}</td>
                            <td>
                                <strong style="color: ${temContrato ? '#28a745' : '#ffc107'};">
                                    R$ ${comissao.toFixed(2).replace('.', ',')}
                                </strong>
                                ${!temContrato ? '<br><small class="text-muted">(' + percentualUsado.toFixed(2) + '% empresa)</small>' : '<br><small class="text-muted">(' + percentualUsado.toFixed(2) + '%)</small>'}
                            </td>
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
        
        // ===== GESTÃO DE CONTRATOS =====
        
        let clienteAtualId = null;
        let contratoEmEdicaoId = null;
        
        // Abrir modal para adicionar contrato
        document.getElementById('btnAdicionarContrato').addEventListener('click', function() {
            // Pegar o ID do cliente do modal
            const clienteId = document.getElementById('clienteId').value;
            
            if (!clienteId) {
                alert('Salve o cliente antes de adicionar contratos');
                return;
            }
            
            clienteAtualId = clienteId;
            contratoEmEdicaoId = null;
            
            // Limpar formulário
            document.getElementById('formContrato').reset();
            document.getElementById('contratoId').value = '';
            document.getElementById('contratoClienteId').value = clienteId;
            document.getElementById('tituloModalContrato').innerHTML = '<i class="fas fa-file-contract me-2"><\/i>Adicionar Contrato';
            
            // Abrir modal de contrato (mantendo o modal do cliente aberto em background)
            const modalContrato = new bootstrap.Modal(document.getElementById('modalContrato'), {
                backdrop: 'static',
                keyboard: false
            });
            modalContrato.show();
        });
        
        // Carregar contratos de um cliente
        async function carregarContratos(clienteId) {
            try {
                const response = await fetch('api/contratos_clientes.php?cliente_id=' + clienteId);
                const data = await response.json();
                
                // Verificar se há aviso sobre tabela não existir
                if (data.aviso) {
                    console.warn('⚠️ Aviso:', data.aviso);
                }
                
                const tbody = document.getElementById('listaContratos');
                tbody.innerHTML = '';
                
                if (data.sucesso && data.contratos && data.contratos.length > 0) {
                    const semContratos = document.getElementById('semContratos');
                    if (semContratos) {
                        semContratos.style.display = 'none';
                    }
                    
                    data.contratos.forEach(contrato => {
                        const tr = document.createElement('tr');
                        tr.id = 'contrato-' + contrato.id;
                        tr.setAttribute('data-contrato-id', contrato.id);
                        
                        // Formatar data
                        const dataInicio = contrato.data_inicio ? 
                            new Date(contrato.data_inicio).toLocaleDateString('pt-BR') : '-';
                        
                        // Formatar valor
                        const valor = contrato.valor ? 
                            'R$ ' + parseFloat(contrato.valor).toFixed(2).replace('.', ',') : '-';
                        
                        // Definir badge da situação
                        const situacoes = {
                            'aguardando_ativacao': { badge: 'warning', texto: 'Aguardando Ativação' },
                            'ativo': { badge: 'success', texto: 'Ativo' },
                            'aguardando_link': { badge: 'info', texto: 'Aguardando Link' },
                            'aguardando_vistoria': { badge: 'primary', texto: 'Aguardando Vistoria' },
                            'devolvido_para_unidade': { badge: 'secondary', texto: 'Devolvido' },
                            'aguardando_assinatura': { badge: 'warning', texto: 'Aguardando Assinatura' },
                            'desistencia': { badge: 'danger', texto: 'Desistência' },
                            'negociar_cliente': { badge: 'dark', texto: 'Negociar Cliente' }
                        };
                        
                        const sit = situacoes[contrato.situacao] || { badge: 'secondary', texto: contrato.situacao || 'N/A' };
                        const badgeSituacao = '<span class="badge bg-' + sit.badge + '">' + sit.texto + '<\/span>';
                        
                        tr.innerHTML = '<td class="contrato-matricula"><strong>' + contrato.matricula + '</strong><\/td>' +
                            '<td class="contrato-placa">' + (contrato.placa || '-') + '<\/td>' +
                            '<td class="contrato-porcentagem"><span class="badge bg-info">' + parseFloat(contrato.porcentagem_recorrencia).toFixed(2) + '%<\/span><\/td>' +
                            '<td class="contrato-data-inicio">' + dataInicio + '<\/td>' +
                            '<td class="contrato-valor"><strong>' + valor + '<\/strong><\/td>' +
                            '<td class="contrato-situacao">' + badgeSituacao + '<\/td>' +
                            '<td>' +
                                '<button type="button" class="btn btn-sm btn-warning me-1" onclick="event.stopPropagation(); editarContratoInline(' + contrato.id + '); return false;" title="Editar">' +
                                    '<i class="fas fa-edit"><\/i>' +
                                '<\/button> ' +
                                '<button type="button" class="btn btn-sm btn-danger" onclick="event.stopPropagation(); excluirContrato(' + contrato.id + '); return false;" title="Excluir">' +
                                    '<i class="fas fa-trash"><\/i>' +
                                '<\/button>' +
                            '<\/td>';
                        tbody.appendChild(tr);
                    });
                } else {
                    tbody.innerHTML = '<tr id="semContratos">' +
                        '<td colspan="7" class="text-center text-muted">' +
                            '<i class="fas fa-inbox fa-2x mb-2"><\/i><br>' +
                            'Nenhum contrato cadastrado. Clique em "Adicionar Contrato" para começar.' +
                        '<\/td>' +
                    '<\/tr>';
                }
            } catch (error) {
                console.error('Erro ao carregar contratos:', error);
                
                // Se erro de JSON, mostrar aviso sobre tabela
                if (error.message && error.message.includes('JSON')) {
                    const tbody = document.getElementById('listaContratos');
                    tbody.innerHTML = '<tr>' +
                        '<td colspan="7" class="text-center text-warning">' +
                            '<i class="fas fa-exclamation-triangle fa-2x mb-2"><\/i><br>' +
                            '<strong>Tabela de contratos não foi criada ainda<\/strong><br>' +
                            '<small>Execute o script de criação da tabela<\/small>' +
                        '<\/td>' +
                    '<\/tr>';
                }
            }
        }
        
        // Salvar contrato (criar ou atualizar)
        async function salvarContrato() {
            const form = document.getElementById('formContrato');
            
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const contratoId = document.getElementById('contratoId').value;
            const clienteId = document.getElementById('contratoClienteId').value;
            const matricula = document.getElementById('contratoMatricula').value.trim();
            const placa = document.getElementById('contratoPlaca').value.trim();
            const porcentagem = document.getElementById('contratoPorcentagem').value;
            const dataInicio = document.getElementById('contratoDataInicio').value;
            const valor = document.getElementById('contratoValor').value;
            const situacao = document.getElementById('contratoSituacao').value;
            const tipoOs = document.getElementById('contratoTipoOs').value.trim();
            const envioWhatsapp = document.getElementById('contratoEnvioWhatsapp').value;
            const envioEmail = document.getElementById('contratoEnvioEmail').value;
            const planilha = document.getElementById('contratoPlanilha').value;
            const observacoes = document.getElementById('contratoObservacoes').value.trim();
            
            if (!matricula || !placa || !porcentagem) {
                alert('Preencha todos os campos obrigatórios');
                return;
            }
            
            try {
                const metodo = contratoId ? 'PUT' : 'POST';
                const dados = {
                    cliente_id: clienteId,
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
                
                if (contratoId) {
                    dados.id = contratoId;
                }
                
                const response = await fetch('api/contratos_clientes.php', {
                    method: metodo,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(dados)
                });
                
                const resultado = await response.json();
                
                if (resultado.sucesso) {
                    alert(contratoId ? 'Contrato atualizado com sucesso!' : 'Contrato cadastrado com sucesso!');
                    
                    // Fechar apenas o modal de contrato
                    const modalContratoInstance = bootstrap.Modal.getInstance(document.getElementById('modalContrato'));
                    if (modalContratoInstance) {
                        modalContratoInstance.hide();
                    }
                    
                    // Recarregar lista de contratos
                    await carregarContratos(clienteId);
                    
                    // Garantir que o modal do cliente permanece aberto
                    const modalCliente = document.getElementById('modalNovoCliente');
                    if (modalCliente && !modalCliente.classList.contains('show')) {
                        new bootstrap.Modal(modalCliente).show();
                    }
                } else {
                    alert('Erro: ' + (resultado.erro || 'Erro desconhecido'));
                }
            } catch (error) {
                console.error('Erro ao salvar contrato:', error);
                alert('Erro ao salvar contrato');
            }
        }
        
        // Editar contrato inline (diretamente na tabela)
        async function editarContratoInline(contratoId) {
            console.log('🔧 Iniciando edição inline do contrato ID:', contratoId);
            
            try {
                const url = 'api/contratos_clientes.php?id=' + contratoId;
                console.log('📡 Fazendo fetch para:', url);
                
                const response = await fetch(url);
                console.log('📊 Response status:', response.status);
                
                // Verificar se a resposta é JSON válida
                const text = await response.text();
                console.log('📄 Response text (primeiros 200 chars):', text.substring(0, 200));
                
                let data;
                
                try {
                    data = JSON.parse(text);
                    console.log('✅ JSON parsed:', data);
                } catch (e) {
                    console.error('❌ Erro ao fazer parse do JSON:', text.substring(0, 500));
                    alert('Erro ao carregar contrato.\n\nA API retornou HTML em vez de JSON.\n\nVerifique o console para detalhes.');
                    return;
                }
                
                if (data.sucesso && data.contrato) {
                    const contrato = data.contrato;
                    console.log('📋 Contrato carregado:', contrato);
                    
                    const tr = document.getElementById('contrato-' + contratoId);
                    
                    // Salvar valores originais
                    tr.setAttribute('data-original-matricula', contrato.matricula);
                    tr.setAttribute('data-original-placa', contrato.placa || '');
                    tr.setAttribute('data-original-porcentagem', contrato.porcentagem_recorrencia);
                    tr.setAttribute('data-original-data-inicio', contrato.data_inicio || '');
                    tr.setAttribute('data-original-valor', contrato.valor || '');
                    tr.setAttribute('data-original-situacao', contrato.situacao || 'aguardando_ativacao');
                    
                    // Formatar data para input[type=date]
                    const dataInicioFormatada = contrato.data_inicio || '';
                    const situacaoAtual = contrato.situacao || 'aguardando_ativacao';
                    
                    // Substituir células por inputs editáveis
                    tr.innerHTML = '<td>' +
                            '<input type="text" class="form-control form-control-sm" id="edit-matricula-' + contratoId + '" value="' + contrato.matricula + '" required>' +
                        '<\/td>' +
                        '<td>' +
                            '<input type="text" class="form-control form-control-sm" id="edit-placa-' + contratoId + '" value="' + (contrato.placa || '') + '" placeholder="ABC1D23" required>' +
                        '<\/td>' +
                        '<td>' +
                            '<div class="input-group input-group-sm">' +
                                '<input type="number" class="form-control" id="edit-porcentagem-' + contratoId + '" value="' + parseFloat(contrato.porcentagem_recorrencia).toFixed(2) + '" min="0" max="100" step="0.01" required>' +
                                '<span class="input-group-text">%<\/span>' +
                            '<\/div>' +
                        '<\/td>' +
                        '<td>' +
                            '<input type="date" class="form-control form-control-sm" id="edit-data-inicio-' + contratoId + '" value="' + dataInicioFormatada + '">' +
                        '<\/td>' +
                        '<td>' +
                            '<input type="number" class="form-control form-control-sm" id="edit-valor-' + contratoId + '" value="' + (contrato.valor || '') + '" min="0" step="0.01" placeholder="0.00">' +
                        '<\/td>' +
                        '<td>' +
                            '<select class="form-select form-select-sm" id="edit-situacao-' + contratoId + '">' +
                                '<option value="aguardando_ativacao"' + (situacaoAtual == 'aguardando_ativacao' ? ' selected' : '') + '>Aguardando Ativação<\/option>' +
                                '<option value="ativo"' + (situacaoAtual == 'ativo' ? ' selected' : '') + '>Ativo<\/option>' +
                                '<option value="aguardando_link"' + (situacaoAtual == 'aguardando_link' ? ' selected' : '') + '>Aguardando Link<\/option>' +
                                '<option value="aguardando_vistoria"' + (situacaoAtual == 'aguardando_vistoria' ? ' selected' : '') + '>Aguardando Vistoria<\/option>' +
                                '<option value="devolvido_para_unidade"' + (situacaoAtual == 'devolvido_para_unidade' ? ' selected' : '') + '>Devolvido<\/option>' +
                                '<option value="aguardando_assinatura"' + (situacaoAtual == 'aguardando_assinatura' ? ' selected' : '') + '>Aguardando Assinatura<\/option>' +
                                '<option value="desistencia"' + (situacaoAtual == 'desistencia' ? ' selected' : '') + '>Desistência<\/option>' +
                                '<option value="negociar_cliente"' + (situacaoAtual == 'negociar_cliente' ? ' selected' : '') + '>Negociar Cliente<\/option>' +
                            '<\/select>' +
                        '<\/td>' +
                        '<td>' +
                            '<button type="button" class="btn btn-sm btn-success me-1" onclick="event.stopPropagation(); salvarContratoInline(' + contratoId + '); return false;" title="Salvar">' +
                                '<i class="fas fa-check"><\/i>' +
                            '<\/button> ' +
                            '<button type="button" class="btn btn-sm btn-secondary" onclick="event.stopPropagation(); cancelarEdicaoInline(' + contratoId + '); return false;" title="Cancelar">' +
                                '<i class="fas fa-times"><\/i>' +
                            '<\/button>' +
                        '<\/td>';
                } else {
                    alert('Erro ao carregar contrato');
                }
            } catch (error) {
                console.error('❌ Erro ao editar contrato:', error);
                
                if (error.message === 'Failed to fetch') {
                    alert('⚠️ Erro de conexão com a API.\n\n' +
                          'Possíveis causas:\n' +
                          '- Servidor não está respondendo\n' +
                          '- Arquivo api/contratos_clientes.php não existe\n' +
                          '- Erro de permissões\n\n' +
                          'Verifique o console (F12) para mais detalhes.');
                } else {
                    alert('Erro ao carregar dados do contrato: ' + error.message);
                }
                
                // NÃO fechar o modal do cliente
                return;
            }
        }
        
        // Salvar edição inline
        async function salvarContratoInline(contratoId) {
            const matricula = document.getElementById('edit-matricula-' + contratoId).value.trim();
            const placa = document.getElementById('edit-placa-' + contratoId).value.trim();
            const porcentagem = document.getElementById('edit-porcentagem-' + contratoId).value;
            const dataInicio = document.getElementById('edit-data-inicio-' + contratoId).value;
            const valor = document.getElementById('edit-valor-' + contratoId).value;
            const situacao = document.getElementById('edit-situacao-' + contratoId).value;
            
            if (!matricula || !placa || !porcentagem || !situacao) {
                alert('Preencha todos os campos obrigatórios');
                return;
            }
            
            try {
                const response = await fetch('api/contratos_clientes.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: contratoId,
                        matricula: matricula,
                        placa: placa,
                        porcentagem_recorrencia: porcentagem,
                        data_inicio: dataInicio || null,
                        valor: valor || null,
                        situacao: situacao,
                        observacoes: ''
                    })
                });
                
                const resultado = await response.json();
                
                if (resultado.sucesso) {
                    alert('Contrato atualizado com sucesso!');
                    
                    // Recarregar lista de contratos
                    const clienteId = document.getElementById('clienteId').value;
                    await carregarContratos(clienteId);
                } else {
                    alert('Erro: ' + (resultado.erro || 'Erro desconhecido'));
                }
            } catch (error) {
                console.error('Erro ao salvar contrato:', error);
                alert('Erro ao salvar contrato');
            }
        }
        
        // Cancelar edição inline
        async function cancelarEdicaoInline(contratoId) {
            // Recarregar lista de contratos para restaurar visualização
            const clienteId = document.getElementById('clienteId').value;
            await carregarContratos(clienteId);
        }
        
        // Excluir contrato
        async function excluirContrato(contratoId) {
            if (!confirm('Tem certeza que deseja excluir este contrato?')) {
                return;
            }
            
            try {
                const response = await fetch('api/contratos_clientes.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: contratoId })
                });
                
                const resultado = await response.json();
                
                if (resultado.sucesso) {
                    alert('Contrato removido com sucesso!');
                    
                    // Recarregar lista de contratos
                    const clienteId = document.getElementById('clienteId').value;
                    if (clienteId) {
                        await carregarContratos(clienteId);
                    }
                } else {
                    alert('Erro: ' + (resultado.erro || 'Erro desconhecido'));
                }
            } catch (error) {
                console.error('Erro ao excluir contrato:', error);
                alert('Erro ao excluir contrato');
            }
        }
        
        // Prevenir que o modal do cliente seja fechado quando o modal de contrato for fechado
        document.getElementById('modalContrato').addEventListener('hidden.bs.modal', function(e) {
            // Reabrir o modal do cliente se ele foi fechado acidentalmente
            const modalCliente = document.getElementById('modalNovoCliente');
            const clienteId = document.getElementById('clienteId').value;
            
            if (clienteId && modalCliente && !modalCliente.classList.contains('show')) {
                console.log('🔄 Reabrindo modal do cliente após fechar contrato');
                new bootstrap.Modal(modalCliente).show();
            }
        });
        
        // Expor funções globalmente
        window.salvarContrato = salvarContrato;
        window.editarContratoInline = editarContratoInline;
        window.salvarContratoInline = salvarContratoInline;
        window.cancelarEdicaoInline = cancelarEdicaoInline;
        window.excluirContrato = excluirContrato;
        window.carregarContratos = carregarContratos;
    </script>
</body>
</html>