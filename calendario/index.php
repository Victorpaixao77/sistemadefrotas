<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Start the session
session_start();

// Check if user is logged in and has empresa_id
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    // Clear session and redirect to login
    session_unset();
    session_destroy();
    header("location: ../login.php");
    exit;
}

// Get user data from session
$nome_usuario = $_SESSION['nome'] ?? 'Usuário';
$empresa_id = $_SESSION['empresa_id'];

// Set page title
$page_title = "Calendário";

// Get company data
$empresa = getCompanyData();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendário - <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/png" href="../logo.png">
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <link rel="stylesheet" href="assets/css/calendario.css">
    
    <!-- FullCalendar CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    
    <!-- FullCalendar JS -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales/pt-br.global.min.js"></script>
    
    <!-- SweetAlert2 for notifications -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include '../includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include '../includes/header.php'; ?>
            
            <!-- Calendar Content -->
            <div class="calendar-content">
                <div class="calendar-header">
                    <h1><i class="fas fa-calendar-alt"></i> Calendário de Eventos</h1>
                    <div class="calendar-controls">
                        <button class="btn btn-primary" id="addEventBtn">
                            <i class="fas fa-plus"></i> Novo Evento
                        </button>
                        <button class="btn btn-secondary" id="refreshEventsBtn">
                            <i class="fas fa-sync-alt"></i> Atualizar
                        </button>
                        <button class="btn btn-info" id="syncStatusBtn" title="Verificar Status da Sincronização">
                            <i class="fas fa-info-circle"></i> Status
                        </button>
                        <button class="btn btn-warning" id="forceSyncBtn" title="Forçar Sincronização Completa">
                            <i class="fas fa-sync"></i> Sincronizar
                        </button>
                    </div>
                </div>
                
                <!-- Calendar Filters -->
                <div class="calendar-filters">
                    <div class="filter-group">
                        <label>Filtrar por categoria:</label>
                        <div class="filter-options">
                            <label class="filter-option">
                                <input type="checkbox" value="CNH" checked> CNH
                            </label>
                            <label class="filter-option">
                                <input type="checkbox" value="Multas" checked> Multas
                            </label>
                            <label class="filter-option">
                                <input type="checkbox" value="Contas" checked> Contas a Pagar
                            </label>
                            <label class="filter-option">
                                <input type="checkbox" value="Financiamento" checked> Financiamento
                            </label>
                            <label class="filter-option">
                                <input type="checkbox" value="Manutenção" checked> Manutenção
                            </label>
                            <label class="filter-option">
                                <input type="checkbox" value="Personalizado" checked> Personalizado
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Calendar Container -->
                <div class="calendar-container">
                    <div id="calendar"></div>
                </div>
                
                <!-- Event Details Modal -->
                <div id="eventModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 id="modalTitle">Detalhes do Evento</h2>
                            <span class="close">&times;</span>
                        </div>
                        <div class="modal-body">
                            <form id="eventForm">
                                <input type="hidden" id="eventId">
                                <input type="hidden" id="eventSource">
                                
                                <div class="form-group">
                                    <label for="eventTitle">Título:</label>
                                    <input type="text" id="eventTitle" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="eventCategory">Categoria:</label>
                                    <select id="eventCategory" required>
                                        <option value="CNH">CNH</option>
                                        <option value="Multas">Multas</option>
                                        <option value="Contas">Contas a Pagar</option>
                                        <option value="Financiamento">Financiamento</option>
                                        <option value="Manutenção">Manutenção</option>
                                        <option value="Personalizado">Personalizado</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="eventStart">Data de Início:</label>
                                    <input type="datetime-local" id="eventStart" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="eventEnd">Data de Fim:</label>
                                    <input type="datetime-local" id="eventEnd">
                                </div>
                                
                                <div class="form-group">
                                    <label for="eventDescription">Descrição:</label>
                                    <textarea id="eventDescription" rows="3"></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="eventColor">Cor:</label>
                                    <input type="color" id="eventColor" value="#3788d8">
                                </div>
                                
                                <div class="form-group">
                                    <label for="eventReminder">Lembrete:</label>
                                    <select id="eventReminder">
                                        <option value="0">Sem lembrete</option>
                                        <option value="15">15 minutos antes</option>
                                        <option value="30">30 minutos antes</option>
                                        <option value="60">1 hora antes</option>
                                        <option value="1440">1 dia antes</option>
                                        <option value="10080">1 semana antes</option>
                                    </select>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Salvar</button>
                                    <button type="button" class="btn btn-danger" id="deleteEventBtn">Excluir</button>
                                    <button type="button" class="btn btn-secondary" id="cancelEventBtn">Cancelar</button>
                                </div>
                            </form>
                        </div>
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
    
    <!-- FullCalendar Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales/pt-br.global.min.js"></script>
    
    <!-- Calendar Script -->
    <script src="assets/js/calendario.js"></script>
</body>
</html>
