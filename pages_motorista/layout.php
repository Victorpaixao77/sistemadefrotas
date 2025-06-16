<?php
require_once 'config.php';

// Verificar se o motorista está logado
if (!isset($_SESSION['motorista_id'])) {
    header('Location: login.php');
    exit;
}

// Obter dados do motorista
$motorista_id = $_SESSION['motorista_id'];
$motorista_nome = $_SESSION['motorista_nome'];
$motorista_foto = $_SESSION['motorista_foto'] ?? 'default.jpg';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Frotas - Área do Motorista</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="css/motorista.css" rel="stylesheet">
</head>
<body>
    <!-- Cabeçalho -->
    <header class="header">
        <div class="container">
            <div class="profile-section">
                <img src="uploads/fotos/<?php echo $motorista_foto; ?>" alt="Foto do Motorista" class="profile-image">
                <div class="profile-info">
                    <h2>Bem-vindo, <?php echo htmlspecialchars($motorista_nome); ?></h2>
                    <p>Área do Motorista</p>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Menu de Navegação -->
    <nav class="container">
        <div class="nav-menu">
            <div class="nav-item">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    Início
                </a>
            </div>
            <div class="nav-item">
                <a href="rotas.php" class="nav-link">
                    <i class="fas fa-route"></i>
                    Rotas
                </a>
            </div>
            <div class="nav-item">
                <a href="abastecimento.php" class="nav-link">
                    <i class="fas fa-gas-pump"></i>
                    Abastecimento
                </a>
            </div>
            <div class="nav-item">
                <a href="checklist.php" class="nav-link">
                    <i class="fas fa-clipboard-check"></i>
                    Checklist
                </a>
            </div>
            <div class="nav-item">
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    Sair
                </a>
            </div>
        </div>
    </nav>
    
    <!-- Conteúdo Principal -->
    <main class="container">
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?> alert-dismissible fade show">
                <?php 
                echo $_SESSION['flash_message'];
                unset($_SESSION['flash_message']);
                unset($_SESSION['flash_type']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- O conteúdo específico de cada página será inserido aqui -->
        <?php if (isset($content)) echo $content; ?>
    </main>
    
    <!-- Rodapé -->
    <footer class="container text-center mt-5 mb-3">
        <p class="text-muted">&copy; <?php echo date('Y'); ?> Sistema de Frotas. Todos os direitos reservados.</p>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="js/motorista.js"></script>
    
    <!-- Scripts específicos da página -->
    <?php if (isset($scripts)) echo $scripts; ?>
</body>
</html> 