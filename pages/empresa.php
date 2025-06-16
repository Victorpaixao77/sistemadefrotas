<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Start session
session_start();

// Check if user is logged in and has empresa_id
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    // Clear session and redirect to login
    session_unset();
    session_destroy();
    header("location: /sistema-frotas/login.php");
    exit;
}

// Verify if empresa is still active
try {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT status FROM empresa_clientes WHERE id = :empresa_id AND status = 'ativo'");
    $stmt->bindParam(':empresa_id', $_SESSION['empresa_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0 || $stmt->fetch()['status'] !== 'ativo') {
        session_unset();
        session_destroy();
        header("location: /sistema-frotas/login.php?error=empresa_inativa");
        exit;
    }
} catch(PDOException $e) {
    // Log error but don't show to user
    error_log("Erro ao verificar status da empresa: " . $e->getMessage());
}

// Set page title
$page_title = "Empresa";

// Get company data from database
$companyData = getCompanyData();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $conn = getConnection();
        
        // Prepare update data
        $data = array(
            'razao_social' => $_POST['razao_social'],
            'nome_fantasia' => $_POST['nome_fantasia'],
            'cnpj' => $_POST['cnpj'],
            'inscricao_estadual' => $_POST['inscricao_estadual'],
            'telefone' => $_POST['telefone'],
            'email' => $_POST['email'],
            'endereco' => $_POST['endereco'],
            'cidade' => $_POST['cidade'],
            'estado' => $_POST['estado'],
            'cep' => $_POST['cep'],
            'responsavel' => $_POST['responsavel']
        );
        
        // Update existing company
        $sql = "UPDATE empresa_clientes SET 
                razao_social = :razao_social,
                nome_fantasia = :nome_fantasia,
                cnpj = :cnpj,
                inscricao_estadual = :inscricao_estadual,
                telefone = :telefone,
                email = :email,
                endereco = :endereco,
                cidade = :cidade,
                estado = :estado,
                cep = :cep,
                responsavel = :responsavel
                WHERE id = :id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $_SESSION['empresa_id']);
        
        // Bind all parameters
        foreach ($data as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        
        if ($stmt->execute()) {
            setFlashMessage('success', 'Dados da empresa atualizados com sucesso!');
            $companyData = getCompanyData(); // Refresh data
        } else {
            setFlashMessage('error', 'Erro ao atualizar os dados da empresa.');
        }
    } catch(PDOException $e) {
        setFlashMessage('error', 'Erro ao atualizar: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Sistema de Gestão de Frotas</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    
    <!-- jQuery and jQuery Mask Plugin -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Sortable.js for drag-and-drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
    <style>
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition-speed) ease;
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            background: var(--bg-primary);
        }
        
        .sidebar-collapsed .main-content {
            margin-left: var(--sidebar-collapsed-width);
            width: calc(100% - var(--sidebar-collapsed-width));
        }
        
        .dashboard-content {
            padding: 20px;
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }

        /* Estilos do Menu de Perfil */
        .profile-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            width: 280px;
            background-color: var(--card-bg);
            border-radius: var(--card-border-radius);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            z-index: 1000;
        }

        .profile-dropdown.show {
            display: block;
        }

        .user-profile {
            position: relative;
        }

        .profile-dropdown-icon {
            transition: transform 0.3s ease;
        }

        .user-profile.active .profile-dropdown-icon {
            transform: rotate(180deg);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include '../includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include '../includes/header.php'; ?>
            
            <!-- Page Content -->
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1>Dados da Empresa</h1>
                </div>
                
                <?php echo displayFlashMessage(); ?>
                
                <!-- Main Content -->
                <div class="card">
                    <div class="card-header">
                        <h2>Dados da Empresa</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="form-grid">
                            <div class="form-group">
                                <label for="razaoSocial">Razão Social *</label>
                                <input type="text" id="razaoSocial" name="razao_social" 
                                       value="<?php echo htmlspecialchars($companyData['razao_social'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="nomeFantasia">Nome Fantasia</label>
                                <input type="text" id="nomeFantasia" name="nome_fantasia" 
                                       value="<?php echo htmlspecialchars($companyData['nome_fantasia'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="cnpj">CNPJ *</label>
                                <input type="text" id="cnpj" name="cnpj" 
                                       value="<?php echo htmlspecialchars($companyData['cnpj'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="inscricaoEstadual">Inscrição Estadual</label>
                                <input type="text" id="inscricaoEstadual" name="inscricao_estadual" 
                                       value="<?php echo htmlspecialchars($companyData['inscricao_estadual'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="responsavel">Responsável</label>
                                <input type="text" id="responsavel" name="responsavel" 
                                       value="<?php echo htmlspecialchars($companyData['responsavel'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="telefone">Telefone</label>
                                <input type="text" id="telefone" name="telefone" 
                                       value="<?php echo htmlspecialchars($companyData['telefone'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="email">E-mail</label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($companyData['email'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="endereco">Endereço</label>
                                <input type="text" id="endereco" name="endereco" 
                                       value="<?php echo htmlspecialchars($companyData['endereco'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="cidade">Cidade</label>
                                <input type="text" id="cidade" name="cidade" 
                                       value="<?php echo htmlspecialchars($companyData['cidade'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="estado">Estado</label>
                                <select id="estado" name="estado">
                                    <option value="">Selecione...</option>
                                    <?php
                                    $estados = array(
                                        'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
                                        'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal',
                                        'ES' => 'Espírito Santo', 'GO' => 'Goiás', 'MA' => 'Maranhão',
                                        'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais',
                                        'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná', 'PE' => 'Pernambuco',
                                        'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
                                        'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima',
                                        'SC' => 'Santa Catarina', 'SP' => 'São Paulo', 'SE' => 'Sergipe',
                                        'TO' => 'Tocantins'
                                    );
                                    
                                    foreach ($estados as $uf => $nome) {
                                        $selected = ($companyData['estado'] ?? '') === $uf ? 'selected' : '';
                                        echo "<option value=\"$uf\" $selected>$nome</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="cep">CEP</label>
                                <input type="text" id="cep" name="cep" 
                                       value="<?php echo htmlspecialchars($companyData['cep'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                                <button type="reset" class="btn btn-secondary">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>
    
    <!-- JavaScript Files -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
        $(document).ready(function() {
            // Aplicar máscaras nos campos
            $('#cnpj').mask('00.000.000/0000-00');
            $('#cep').mask('00000-000');
            $('#telefone').mask('(00) 00000-0000');
            $('#inscricaoEstadual').mask('000.000.000.000');
        });
    </script>
</body>
</html>