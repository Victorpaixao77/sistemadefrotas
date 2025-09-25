<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/notifications.php';

configure_session();
session_start();

header('Content-Type: application/json');

// Verificar se o usuário está autenticado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];
$user_id = $_SESSION['user_id'] ?? null;
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $conn = getConnection();
    $notification = new NotificationManager($conn);
    
    switch ($action) {
        case 'get_unread':
            $notifications = $notification->getUserNotifications($user_id, 10, true);
            echo json_encode([
                'success' => true,
                'notifications' => $notifications
            ]);
            break;
            
        case 'get_all':
            $notifications = $notification->getUserNotifications($user_id, 50, false);
            echo json_encode([
                'success' => true,
                'notifications' => $notifications
            ]);
            break;
            
        case 'mark_as_read':
            $notification_id = $_POST['notification_id'] ?? $_GET['notification_id'] ?? 0;
            $result = $notification->markAsRead($notification_id, $user_id);
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Notificação marcada como lida' : 'Erro ao marcar notificação'
            ]);
            break;
            
        case 'mark_all_read':
            $result = $notification->markAllAsRead($user_id);
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Todas as notificações foram marcadas como lidas' : 'Erro ao marcar notificações'
            ]);
            break;
            
        case 'get_count':
            $count = $notification->getUnreadCount($user_id);
            echo json_encode([
                'success' => true,
                'count' => $count
            ]);
            break;
            
        case 'create':
            $title = $_POST['title'] ?? '';
            $message = $_POST['message'] ?? '';
            $type = $_POST['type'] ?? 'info';
            $data = $_POST['data'] ?? null;
            
            if ($title && $message) {
                $result = $notification->create($user_id, $title, $message, $type, $data);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Notificação criada com sucesso' : 'Erro ao criar notificação'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Título e mensagem são obrigatórios'
                ]);
            }
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Ação inválida'
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>
