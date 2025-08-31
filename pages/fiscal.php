<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Obter dados da empresa do usuário logado
$empresa_id = $_SESSION['empresa_id'] ?? 1;
$user_id = $_SESSION['user_id'];

// Dados simulados para demonstração (até as tabelas serem criadas)
$stats = [
    'total_nfe' => 0,
    'nfe_pendentes' => 0,
    'nfe_autorizadas' => 0,
    'total_cte' => 0,
    'cte_pendentes' => 0,
    'cte_autorizados' => 0,
    'total_mdfe' => 0,
    'mdfe_pendentes' => 0,
    'mdfe_encerrados' => 0
];

$documentos_recentes = [];
$alertas = [];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Fiscal - Sistema de Frotas</title>
    
    <!-- CSS do Sistema -->
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/fontawesome.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon.ico">
</head>
<body>
    <!-- Navbar -->
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../includes/sidebar.php'; ?>
            
            <!-- Conteúdo Principal -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-receipt text-primary"></i> Sistema Fiscal
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="alert('Funcionalidade em desenvolvimento')">
                                <i class="fas fa-upload"></i> Importar NF-e
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success" onclick="alert('Funcionalidade em desenvolvimento')">
                                <i class="fas fa-truck"></i> Emitir CT-e
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info" onclick="alert('Funcionalidade em desenvolvimento')">
                                <i class="fas fa-file-alt"></i> Emitir MDF-e
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Aviso de Configuração -->
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <h5 class="alert-heading">
                        <i class="fas fa-info-circle"></i> Sistema Fiscal em Configuração
                    </h5>
                    <p>O sistema fiscal está sendo configurado. As funcionalidades estarão disponíveis em breve.</p>
                    <hr>
                    <p class="mb-0">Para configurar o sistema, execute o script SQL: <code>fiscal/database/schema_fiscal.sql</code></p>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>

                <!-- KPIs do Dashboard -->
                <div class="row mb-4">
                    <!-- NF-e -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Notas Fiscais
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= $stats['total_nfe'] ?>
                                        </div>
                                        <div class="text-xs text-muted">
                                            <?= $stats['nfe_pendentes'] ?> pendentes, 
                                            <?= $stats['nfe_autorizadas'] ?> autorizadas
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-receipt fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CT-e -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Conhecimentos de Transporte
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= $stats['total_cte'] ?>
                                        </div>
                                        <div class="text-xs text-muted">
                                            <?= $stats['cte_pendentes'] ?> pendentes, 
                                            <?= $stats['cte_autorizados'] ?> autorizados
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-truck fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- MDF-e -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Manifestos de Documentos
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= $stats['total_mdfe'] ?>
                                        </div>
                                        <div class="text-xs text-muted">
                                            <?= $stats['mdfe_pendentes'] ?> pendentes, 
                                            <?= $stats['mdfe_encerrados'] ?> encerrados
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status SEFAZ -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Status SEFAZ
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="sefazStatus">
                                            <span class="badge bg-secondary">Configurando</span>
                                        </div>
                                        <div class="text-xs text-muted">
                                            Última verificação: <span id="ultimaVerificacao">N/A</span>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-globe fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Documentos Recentes -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-clock"></i> Documentos Recentes
                                </h6>
                                <div class="dropdown no-arrow">
                                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                                        <a class="dropdown-item" href="../fiscal/pages/nfe.php">
                                            <i class="fas fa-receipt fa-sm fa-fw mr-2 text-gray-400"></i>Ver Todas NF-e
                                        </a>
                                        <a class="dropdown-item" href="../fiscal/pages/cte.php">
                                            <i class="fas fa-truck fa-sm fa-fw mr-2 text-gray-400"></i>Ver Todos CT-e
                                        </a>
                                        <a class="dropdown-item" href="../fiscal/pages/mdfe.php">
                                            <i class="fas fa-file-alt fa-sm fa-fw mr-2 text-gray-400"></i>Ver Todos MDF-e
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-gray-300 mb-3"></i>
                                    <p class="text-gray-500">Nenhum documento fiscal encontrado.</p>
                                    <p class="text-gray-400">Configure o banco de dados para começar a usar o sistema fiscal.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ações Rápidas -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-bolt"></i> Ações Rápidas
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <a href="../fiscal/pages/nfe.php" class="btn btn-outline-primary btn-lg w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                            <i class="fas fa-receipt fa-3x mb-2"></i>
                                            <span>Gestão de NF-e</span>
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="../fiscal/pages/cte.php" class="btn btn-outline-success btn-lg w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                            <i class="fas fa-truck fa-3x mb-2"></i>
                                            <span>Gestão de CT-e</span>
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="../fiscal/pages/mdfe.php" class="btn btn-outline-info btn-lg w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                            <i class="fas fa-file-alt fa-3x mb-2"></i>
                                            <span>Gestão de MDF-e</span>
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="../fiscal/pages/eventos.php" class="btn btn-outline-warning btn-lg w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                                            <i class="fas fa-calendar-alt fa-3x mb-2"></i>
                                            <span>Eventos Fiscais</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/jquery-3.6.0.min.js"></script>
    
    <script>
        // Função para download de documentos
        function downloadDocument(type, id) {
            alert(`Download ${type.toUpperCase()} ID: ${id} - Funcionalidade em desenvolvimento`);
        }

        // Atualizar status SEFAZ periodicamente
        setInterval(() => {
            const statusElement = document.getElementById('sefazStatus');
            const ultimaElement = document.getElementById('ultimaVerificacao');
            
            if (statusElement && ultimaElement) {
                const now = new Date();
                ultimaElement.textContent = now.toLocaleTimeString('pt-BR');
            }
        }, 30000); // A cada 30 segundos
    </script>
</body>
</html>
