<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

configure_session();
session_start();
require_authentication();

$nome_usuario = $_SESSION['nome'] ?? 'Usuário';
$empresa_id = (int) $_SESSION['empresa_id'];
$page_title = 'Calendário';
$empresa = getCompanyData();
$asset_v = sf_asset_v();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendário - <?php echo htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="icon" type="image/png" href="../logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo htmlspecialchars($asset_v, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="../css/theme.css?v=<?php echo htmlspecialchars($asset_v, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="../css/responsive.css?v=<?php echo htmlspecialchars($asset_v, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="assets/css/calendario.css?v=<?php echo htmlspecialchars($asset_v, ENT_QUOTES, 'UTF-8'); ?>">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="calendar-content">
                <div class="calendar-header">
                    <h1><i class="fas fa-calendar-alt"></i> Calendário de Eventos</h1>
                    <div class="calendar-controls">
                        <button type="button" class="btn btn-primary" id="addEventBtn">
                            <i class="fas fa-plus"></i> Novo Evento
                        </button>
                        <button type="button" class="btn btn-secondary" id="refreshEventsBtn">
                            <i class="fas fa-sync-alt"></i> Atualizar
                        </button>
                        <button type="button" class="btn btn-secondary" id="syncStatusBtn" title="Status da sincronização no banco">
                            <i class="fas fa-info-circle"></i> Status
                        </button>
                        <button type="button" class="btn btn-secondary" id="forceSyncBtn" title="Recriar eventos sincronizados (multas/CNH) no banco">
                            <i class="fas fa-database"></i> Sync BD
                        </button>
                    </div>
                </div>
                <div class="calendar-filters">
                    <div class="filter-group">
                        <label>Filtrar por categoria:</label>
                        <div class="filter-options">
                            <label class="filter-option"><input type="checkbox" value="CNH" checked> CNH</label>
                            <label class="filter-option"><input type="checkbox" value="Multas" checked> Multas</label>
                            <label class="filter-option"><input type="checkbox" value="Contas" checked> Contas a Pagar</label>
                            <label class="filter-option"><input type="checkbox" value="Financiamento" checked> Financiamento</label>
                            <label class="filter-option"><input type="checkbox" value="Manutenção" checked> Manutenção</label>
                            <label class="filter-option"><input type="checkbox" value="Personalizado" checked> Personalizado</label>
                        </div>
                    </div>
                </div>
                <div class="calendar-container">
                    <div id="calendar"></div>
                </div>
                <div id="eventModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 id="modalTitle">Detalhes do evento</h2>
                            <span class="close-modal" aria-label="Fechar">&times;</span>
                        </div>
                        <div class="modal-body">
                            <p id="eventAutoNotice" class="cal-auto-notice" style="display:none;">
                                Evento gerado automaticamente pelo sistema — edite o registro na tela de origem.
                            </p>
                            <form id="eventForm">
                                <input type="hidden" id="eventId">
                                <input type="hidden" id="eventSource">
                                <div class="form-group">
                                    <label for="eventTitle">Título</label>
                                    <input type="text" id="eventTitle" required>
                                </div>
                                <div class="form-group">
                                    <label for="eventCategory">Categoria</label>
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
                                    <label for="eventStart">Data de início</label>
                                    <input type="datetime-local" id="eventStart" required>
                                </div>
                                <div class="form-group">
                                    <label for="eventEnd">Data de fim</label>
                                    <input type="datetime-local" id="eventEnd">
                                </div>
                                <div class="form-group">
                                    <label for="eventDescription">Descrição</label>
                                    <textarea id="eventDescription" rows="3"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="eventColor">Cor</label>
                                    <input type="color" id="eventColor" value="#3788d8">
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer cal-modal-footer">
                            <button type="button" class="btn btn-secondary" id="cancelEventBtn">Cancelar</button>
                            <button type="button" class="btn btn-danger" id="deleteEventBtn">Excluir</button>
                            <button type="button" class="btn btn-info" id="openSourceLinkBtn" style="display:none;">
                                <i class="fas fa-external-link-alt"></i> Abrir no sistema
                            </button>
                            <button type="submit" form="eventForm" class="btn btn-primary" id="saveEventBtn">Salvar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="../js/theme.js?v=<?php echo htmlspecialchars($asset_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="../js/sidebar.js?v=<?php echo htmlspecialchars($asset_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales/pt-br.global.min.js"></script>
    <?php require_once __DIR__ . '/../includes/sf_api_base.php'; sf_render_api_scripts(); ?>
    <script src="assets/js/calendario.js?v=<?php echo htmlspecialchars($asset_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
