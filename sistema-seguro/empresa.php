<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Verificar se está logado
verificarLogin();

// Obter dados do usuário
$usuario = obterUsuarioLogado();
$empresa_id = obterEmpresaId();

$mensagem = '';
$tipo_mensagem = '';

// DEBUG: Verificar se POST está chegando
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST recebido em empresa.php - Campos: " . count($_POST));
    error_log("Action: " . ($_POST['action'] ?? 'não definido'));
}

// Buscar dados da empresa
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM seguro_empresa_clientes WHERE id = ?");
    $stmt->execute([$empresa_id]);
    $empresa = $stmt->fetch();
    
    if (!$empresa) {
        die("Erro: Empresa não encontrada");
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar empresa: " . $e->getMessage());
    die("Erro ao carregar dados da empresa");
}

// Processar formulário de atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'atualizar') {
    
    // Verificar se todos os campos estão chegando
    $campos_recebidos = count($_POST);
    error_log("Empresa.php - POST recebido com $campos_recebidos campos");
    
    // Debug das cores
    error_log("Cor Primária recebida: " . ($_POST['cor_primaria'] ?? 'NÃO ENVIADO'));
    error_log("Cor Secundária recebida: " . ($_POST['cor_secundaria'] ?? 'NÃO ENVIADO'));
    error_log("Cor Destaque recebida: " . ($_POST['cor_destaque'] ?? 'NÃO ENVIADO'));
    
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("
            UPDATE seguro_empresa_clientes 
            SET 
                razao_social = ?,
                nome_fantasia = ?,
                cnpj = ?,
                inscricao_estadual = ?,
                inscricao_municipal = ?,
                cep = ?,
                endereco = ?,
                numero = ?,
                complemento = ?,
                bairro = ?,
                cidade = ?,
                estado = ?,
                telefone = ?,
                celular = ?,
                email = ?,
                site = ?,
                responsavel = ?,
                porcentagem_fixa = ?,
                unidade = ?,
                dia_fechamento = ?,
                cor_primaria = ?,
                cor_secundaria = ?,
                cor_destaque = ?,
                observacoes = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $_POST['razao_social'],
            $_POST['nome_fantasia'],
            $_POST['cnpj'],
            $_POST['inscricao_estadual'],
            $_POST['inscricao_municipal'],
            $_POST['cep'],
            $_POST['endereco'],
            $_POST['numero'],
            $_POST['complemento'],
            $_POST['bairro'],
            $_POST['cidade'],
            $_POST['uf'],
            $_POST['telefone'],
            $_POST['celular'],
            $_POST['email'],
            $_POST['site'],
            $_POST['responsavel'],
            $_POST['porcentagem_fixa'],
            $_POST['unidade'],
            $_POST['dia_fechamento'] ?? 25,
            $_POST['cor_primaria'] ?? '#667eea',
            $_POST['cor_secundaria'] ?? '#764ba2',
            $_POST['cor_destaque'] ?? '#28a745',
            $_POST['observacoes'],
            $empresa_id
        ]);
        
        // Registrar log
        registrarLog($empresa_id, obterUsuarioId(), 'atualizar', 'empresa', 'Dados da empresa atualizados');
        
        $db->commit();
        
        // Recarregar dados
        $stmt = $db->prepare("SELECT * FROM seguro_empresa_clientes WHERE id = ?");
        $stmt->execute([$empresa_id]);
        $empresa = $stmt->fetch();
        
        $mensagem = "Dados da empresa atualizados com sucesso!";
        $tipo_mensagem = "success";
        
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Erro ao atualizar empresa: " . $e->getMessage());
        $mensagem = "Erro ao atualizar dados da empresa: " . $e->getMessage();
        $tipo_mensagem = "error";
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Erro geral ao atualizar empresa: " . $e->getMessage());
        $mensagem = "Erro ao processar atualização: " . $e->getMessage();
        $tipo_mensagem = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Seguro - Minha Empresa</title>
    <script src="js/tema-instantaneo.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/menu-responsivo.css" rel="stylesheet">
    <link href="css/temas.css" rel="stylesheet">
    <link href="css/tema-escuro-forcado.css" rel="stylesheet">
    <style>
        /* Estilos adaptados para tema claro/escuro */
        .form-section {
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
        }
        .section-title {
            color: var(--cor-primaria, #667eea);
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--cor-primaria, #667eea);
        }
        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .percentage-input {
            position: relative;
        }
        .percentage-input .input-group-text {
            background: var(--cor-primaria, #667eea);
            color: white;
            border: none;
        }
        .percentage-input input {
            border-left: none;
        }
        .info-box {
            background: var(--hover-bg, #e7f3ff);
            border-left: 4px solid var(--cor-primaria, #667eea);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        /* Tema escuro - ajuste de info-box */
        :root[data-theme="escuro"] .info-box {
            background: var(--bg-card);
            border-left-color: var(--cor-primaria);
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
                <a class="nav-link active" href="empresa.php">
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
                    <h2 class="mb-0">
                        <i class="fas fa-building me-2"></i>
                        Minha Empresa
                    </h2>
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

        <!-- Info Box -->
        <div class="info-box">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Informação:</strong> Cadastre os dados da sua empresa. Estas informações serão utilizadas em relatórios, contratos e documentos fiscais.
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $tipo_mensagem === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                <?php echo htmlspecialchars($mensagem); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form id="companyForm" method="POST">
            <input type="hidden" name="action" value="atualizar">
            <!-- Dados Básicos -->
            <div class="form-section">
                <h4 class="section-title">
                    <i class="fas fa-building me-2"></i>
                    Dados Básicos
                </h4>
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="razaoSocial" class="form-label">Razão Social <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="razaoSocial" name="razao_social" placeholder="Digite a razão social" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="nomeFantasia" class="form-label">Nome Fantasia</label>
                        <input type="text" class="form-control" id="nomeFantasia" name="nome_fantasia" placeholder="Digite o nome fantasia">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="cnpj" class="form-label">CNPJ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="cnpj" name="cnpj" placeholder="00.000.000/0000-00" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="inscricaoEstadual" class="form-label">Inscrição Estadual</label>
                        <input type="text" class="form-control" id="inscricaoEstadual" name="inscricao_estadual" placeholder="000.000.000.000">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="inscricaoMunicipal" class="form-label">Inscrição Municipal</label>
                        <input type="text" class="form-control" id="inscricaoMunicipal" name="inscricao_municipal" placeholder="0000000">
                    </div>
                </div>
            </div>

            <!-- Endereço -->
            <div class="form-section">
                <h4 class="section-title">
                    <i class="fas fa-map-marker-alt me-2"></i>
                    Endereço
                </h4>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="cep" class="form-label">CEP <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="cep" name="cep" placeholder="00000-000" required>
                    </div>
                    <div class="col-md-7 mb-3">
                        <label for="endereco" class="form-label">Endereço <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="endereco" name="endereco" placeholder="Rua, Avenida, etc." required>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="numero" class="form-label">Número</label>
                        <input type="text" class="form-control" id="numero" name="numero" placeholder="Nº">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="complemento" class="form-label">Complemento</label>
                        <input type="text" class="form-control" id="complemento" name="complemento" placeholder="Sala, Andar, etc.">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="bairro" class="form-label">Bairro</label>
                        <input type="text" class="form-control" id="bairro" name="bairro" placeholder="Digite o bairro">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="cidade" class="form-label">Cidade <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="cidade" name="cidade" placeholder="Digite a cidade" required>
                    </div>
                    <div class="col-md-1 mb-3">
                        <label for="uf" class="form-label">UF <span class="text-danger">*</span></label>
                        <select class="form-select" id="uf" name="uf" required>
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

            <!-- Contato -->
            <div class="form-section">
                <h4 class="section-title">
                    <i class="fas fa-phone me-2"></i>
                    Contato
                </h4>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="telefone" class="form-label">Telefone <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="telefone" name="telefone" placeholder="(00) 0000-0000" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="celular" class="form-label">Celular</label>
                        <input type="text" class="form-control" id="celular" name="celular" placeholder="(00) 00000-0000">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="contato@empresa.com.br" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="site" class="form-label">Site</label>
                        <input type="url" class="form-control" id="site" name="site" placeholder="https://www.empresa.com.br">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="responsavel" class="form-label">Responsável</label>
                        <input type="text" class="form-control" id="responsavel" name="responsavel" placeholder="Nome do responsável">
                    </div>
                </div>
            </div>

            <!-- Configurações Financeiras -->
            <div class="form-section">
                <h4 class="section-title">
                    <i class="fas fa-dollar-sign me-2"></i>
                    Configurações Financeiras
                </h4>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="porcentagemFixa" class="form-label">
                            Porcentagem Fixa <span class="text-danger">*</span>
                            <i class="fas fa-info-circle ms-1" data-bs-toggle="tooltip" title="Porcentagem fixa aplicada sobre os valores dos serviços/produtos"></i>
                        </label>
                        <div class="input-group percentage-input">
                            <input type="number" class="form-control" id="porcentagemFixa" name="porcentagem_fixa" placeholder="0.00" step="0.01" min="0" max="100" required>
                            <span class="input-group-text">
                                <i class="fas fa-percentage me-1"></i>%
                            </span>
                        </div>
                        <small class="text-muted">Digite um valor entre 0 e 100</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="unidade" class="form-label">
                            Unidade <span class="text-danger">*</span>
                            <i class="fas fa-info-circle ms-1" data-bs-toggle="tooltip" title="Identificação da unidade ou empresa no sistema terceirizado"></i>
                        </label>
                        <input type="text" class="form-control" id="unidade" name="unidade" placeholder="Digite o nome da unidade" required>
                        <small class="text-muted">Esta unidade será vinculada aos clientes comissionados</small>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="diaFechamento" class="form-label">
                            Dia de Fechamento Mensal <span class="text-danger">*</span>
                            <i class="fas fa-info-circle ms-1" data-bs-toggle="tooltip" title="Pagamentos recebidos após este dia entram na comissão do mês seguinte"></i>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-calendar-day"></i>
                            </span>
                            <input type="number" class="form-control" id="diaFechamento" name="dia_fechamento" placeholder="Ex: 25" min="1" max="31" required value="<?php echo $empresa['dia_fechamento'] ?? '25'; ?>">
                            <span class="input-group-text">do mês</span>
                        </div>
                        <small class="text-muted">Pagamentos após o dia <strong id="diaExemplo"><?php echo $empresa['dia_fechamento'] ?? '25'; ?></strong> vão para a comissão do mês seguinte</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>Como funciona:</strong><br>
                            <small>
                                Se o dia de fechamento for <strong>dia 25</strong>:<br>
                                • Pagamento em 24/10 → Comissão de Outubro<br>
                                • Pagamento em 26/10 → Comissão de Novembro
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configurações de Tema e Cores -->
            <div class="form-section">
                <h4 class="section-title">
                    <i class="fas fa-palette me-2"></i>
                    Configurações de Tema e Cores
                </h4>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Personalização Visual:</strong> Configure as cores que serão aplicadas em todo o sistema para sua empresa.
                    Cada usuário pode escolher entre tema claro e escuro individualmente.
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="corPrimaria" class="form-label">
                            Cor Primária
                            <i class="fas fa-info-circle ms-1" data-bs-toggle="tooltip" title="Cor principal usada no sistema (botões, destaques)"></i>
                        </label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color" id="corPrimaria" name="cor_primaria" value="#667eea">
                            <input type="text" class="form-control" id="corPrimariaTexto" value="#667eea" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$">
                        </div>
                        <small class="text-muted">Cor padrão: #667eea (Azul)</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="corSecundaria" class="form-label">
                            Cor Secundária
                            <i class="fas fa-info-circle ms-1" data-bs-toggle="tooltip" title="Cor secundária usada em gradientes e destaques"></i>
                        </label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color" id="corSecundaria" name="cor_secundaria" value="#764ba2">
                            <input type="text" class="form-control" id="corSecundariaTexto" value="#764ba2" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$">
                        </div>
                        <small class="text-muted">Cor padrão: #764ba2 (Roxo)</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="corDestaque" class="form-label">
                            Cor de Destaque
                            <i class="fas fa-info-circle ms-1" data-bs-toggle="tooltip" title="Cor usada para elementos de sucesso e confirmação"></i>
                        </label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color" id="corDestaque" name="cor_destaque" value="#28a745">
                            <input type="text" class="form-control" id="corDestaqueTexto" value="#28a745" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$">
                        </div>
                        <small class="text-muted">Cor padrão: #28a745 (Verde)</small>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="card" style="background: linear-gradient(135deg, var(--cor-primaria, #667eea) 0%, var(--cor-secundaria, #764ba2) 100%);">
                            <div class="card-body text-white text-center">
                                <h5><i class="fas fa-eye me-2"></i>Pré-visualização do Gradiente</h5>
                                <p class="mb-0">As cores escolhidas serão aplicadas nos cabeçalhos e menus do sistema</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <button type="button" class="btn btn-warning" onclick="restaurarCoresPadrao()">
                                <i class="fas fa-undo me-2"></i>
                                Restaurar Cores Padrão
                            </button>
                            <a href="debug_cores.php" class="btn btn-info" target="_blank">
                                <i class="fas fa-bug me-2"></i>
                                Testar Cores (Debug)
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Observações -->
            <div class="form-section">
                <h4 class="section-title">
                    <i class="fas fa-clipboard me-2"></i>
                    Observações
                </h4>
                <div class="row">
                    <div class="col-12 mb-3">
                        <label for="observacoes" class="form-label">Observações Gerais</label>
                        <textarea class="form-control" id="observacoes" name="observacoes" rows="4" placeholder="Digite observações adicionais sobre a empresa..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Botões de Ação -->
            <div class="d-flex justify-content-end gap-3">
                <button type="submit" class="btn btn-primary-custom">
                    <i class="fas fa-save me-2"></i>
                    Salvar Dados da Empresa
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/menu-responsivo.js"></script>
    <script>
        // Popular campos com dados do banco
        document.addEventListener('DOMContentLoaded', function() {
            const empresa = <?php echo json_encode($empresa); ?>;
            
            // Dados Básicos
            document.getElementById('razaoSocial').value = empresa.razao_social || '';
            document.getElementById('nomeFantasia').value = empresa.nome_fantasia || '';
            document.getElementById('cnpj').value = empresa.cnpj || '';
            document.getElementById('inscricaoEstadual').value = empresa.inscricao_estadual || '';
            document.getElementById('inscricaoMunicipal').value = empresa.inscricao_municipal || '';
            
            // Endereço
            document.getElementById('cep').value = empresa.cep || '';
            document.getElementById('endereco').value = empresa.endereco || '';
            document.getElementById('numero').value = empresa.numero || '';
            document.getElementById('complemento').value = empresa.complemento || '';
            document.getElementById('bairro').value = empresa.bairro || '';
            document.getElementById('cidade').value = empresa.cidade || '';
            document.getElementById('uf').value = empresa.estado || '';
            
            // Contato
            document.getElementById('telefone').value = empresa.telefone || '';
            document.getElementById('celular').value = empresa.celular || '';
            document.getElementById('email').value = empresa.email || '';
            document.getElementById('site').value = empresa.site || '';
            document.getElementById('responsavel').value = empresa.responsavel || '';
            
            // Configurações Financeiras
            document.getElementById('porcentagemFixa').value = empresa.porcentagem_fixa || '0.00';
            document.getElementById('unidade').value = empresa.unidade || '';
            document.getElementById('diaFechamento').value = empresa.dia_fechamento || '25';
            
            // Configurações de Tema e Cores
            const corPrimaria = empresa.cor_primaria || '#667eea';
            const corSecundaria = empresa.cor_secundaria || '#764ba2';
            const corDestaque = empresa.cor_destaque || '#28a745';
            
            console.log('=== CARREGANDO CORES DO BANCO ===');
            console.log('Cor Primária do banco:', corPrimaria);
            console.log('Cor Secundária do banco:', corSecundaria);
            console.log('Cor Destaque do banco:', corDestaque);
            
            document.getElementById('corPrimaria').value = corPrimaria;
            document.getElementById('corPrimariaTexto').value = corPrimaria;
            document.getElementById('corSecundaria').value = corSecundaria;
            document.getElementById('corSecundariaTexto').value = corSecundaria;
            document.getElementById('corDestaque').value = corDestaque;
            document.getElementById('corDestaqueTexto').value = corDestaque;
            
            // Observações
            document.getElementById('observacoes').value = empresa.observacoes || '';
        });
        
        // Inicializar tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Máscara para CNPJ
        document.getElementById('cnpj').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/^(\d{2})(\d)/, '$1.$2');
            value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
            value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
            value = value.replace(/(\d{4})(\d)/, '$1-$2');
            e.target.value = value;
        });

        // Atualizar exemplo do dia de fechamento
        document.getElementById('diaFechamento').addEventListener('input', function(e) {
            const dia = e.target.value;
            if (dia >= 1 && dia <= 31) {
                document.getElementById('diaExemplo').textContent = dia;
            }
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

        // Buscar CEP
        document.getElementById('cep').addEventListener('blur', function() {
            const cep = this.value.replace(/\D/g, '');
            
            if (cep.length === 8) {
                fetch(`https://viacep.com.br/ws/${cep}/json/`)
                    .then(response => response.json())
                    .then(data => {
                        if (!data.erro) {
                            document.getElementById('endereco').value = data.logradouro;
                            document.getElementById('bairro').value = data.bairro;
                            document.getElementById('cidade').value = data.localidade;
                            document.getElementById('uf').value = data.uf;
                        } else {
                            alert('CEP não encontrado!');
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao buscar CEP:', error);
                    });
            }
        });

        // Sincronização dos Color Pickers com Inputs de Texto
        // Cor Primária
        document.getElementById('corPrimaria').addEventListener('input', function(e) {
            document.getElementById('corPrimariaTexto').value = e.target.value;
        });
        document.getElementById('corPrimariaTexto').addEventListener('input', function(e) {
            const cor = e.target.value;
            if (/^#[0-9A-Fa-f]{6}$/.test(cor)) {
                document.getElementById('corPrimaria').value = cor;
            }
        });

        // Cor Secundária
        document.getElementById('corSecundaria').addEventListener('input', function(e) {
            document.getElementById('corSecundariaTexto').value = e.target.value;
        });
        document.getElementById('corSecundariaTexto').addEventListener('input', function(e) {
            const cor = e.target.value;
            if (/^#[0-9A-Fa-f]{6}$/.test(cor)) {
                document.getElementById('corSecundaria').value = cor;
            }
        });

        // Cor de Destaque
        document.getElementById('corDestaque').addEventListener('input', function(e) {
            document.getElementById('corDestaqueTexto').value = e.target.value;
        });
        document.getElementById('corDestaqueTexto').addEventListener('input', function(e) {
            const cor = e.target.value;
            if (/^#[0-9A-Fa-f]{6}$/.test(cor)) {
                document.getElementById('corDestaque').value = cor;
            }
        });

        // Função para restaurar cores padrão
        function restaurarCoresPadrao() {
            if (confirm('Deseja restaurar as cores padrão do sistema?')) {
                // Cores padrão
                const coresPadrao = {
                    primaria: '#667eea',
                    secundaria: '#764ba2',
                    destaque: '#28a745'
                };
                
                // Aplicar nos inputs
                document.getElementById('corPrimaria').value = coresPadrao.primaria;
                document.getElementById('corPrimariaTexto').value = coresPadrao.primaria;
                document.getElementById('corSecundaria').value = coresPadrao.secundaria;
                document.getElementById('corSecundariaTexto').value = coresPadrao.secundaria;
                document.getElementById('corDestaque').value = coresPadrao.destaque;
                document.getElementById('corDestaqueTexto').value = coresPadrao.destaque;
                
                // Feedback visual
                alert('✅ Cores padrão restauradas! Clique em "Salvar Dados da Empresa" para confirmar.');
            }
        }

        // Salvar formulário
        document.getElementById('companyForm').addEventListener('submit', function(e) {
            console.log('=== SUBMIT DO FORMULÁRIO ===');
            
            // Debug: Verificar se cores estão sendo enviadas
            console.log('Cores no formulário:');
            console.log('  Primária:', document.getElementById('corPrimaria').value);
            console.log('  Secundária:', document.getElementById('corSecundaria').value);
            console.log('  Destaque:', document.getElementById('corDestaque').value);
            
            // Validar porcentagem fixa antes de enviar
            const porcentagem = parseFloat(document.getElementById('porcentagemFixa').value);
            if (isNaN(porcentagem) || porcentagem < 0 || porcentagem > 100) {
                e.preventDefault();
                alert('A porcentagem fixa deve estar entre 0 e 100!');
                return false;
            }
            
            // Validar campos obrigatórios
            const razaoSocial = document.getElementById('razaoSocial').value.trim();
            const cnpj = document.getElementById('cnpj').value.trim();
            const unidade = document.getElementById('unidade').value.trim();
            
            if (!razaoSocial || !cnpj || !unidade) {
                e.preventDefault();
                alert('Por favor, preencha todos os campos obrigatórios!');
                return false;
            }
            
            // Desabilitar botão para evitar duplo envio
            const btnSalvar = this.querySelector('button[type="submit"]');
            btnSalvar.disabled = true;
            btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Salvando...';
            
            // Tudo OK, permitir envio do formulário
            console.log('Formulário validado, enviando...');
            console.log('Action:', this.querySelector('input[name="action"]').value);
            console.log('Total de campos no formulário:', new FormData(this).entries().length);
            
            return true;
        });
    </script>
    <script src="js/temas.js"></script>
</body>
</html>
