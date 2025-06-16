<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Set page title
$page_title = "Título da Página";
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Sortable.js for drag-and-drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
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
                    <h1><?php echo $page_title; ?></h1>
                    <div class="dashboard-actions">
                        <button id="addWidgetBtn" class="btn-add-widget">
                            <i class="fas fa-plus"></i> Adicionar Widget
                        </button>
                        <div class="view-controls">
                            <button id="restoreLayoutBtn" class="btn-restore-layout" title="Restaurar Layout">
                                <i class="fas fa-undo"></i>
                            </button>
                            <button id="toggleLayoutBtn" class="btn-toggle-layout" title="Alternar Layout">
                                <i class="fas fa-columns"></i>
                            </button>
                            <button id="helpBtn" class="btn-help" title="Ajuda para navegação">
                                <i class="fas fa-question-circle"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Main Content Goes Here -->
                
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>
    
    <!-- JavaScript Files -->
    <script src="../js/dashboard.js"></script>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/sortable.js"></script>
    <script src="../js/charts.js"></script>
    <script src="../js/header.js"></script>
</body>
</html>