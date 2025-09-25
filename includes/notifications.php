<?php
/**
 * Sistema de Notificações
 * Implementa notificações para usuários do sistema
 */

class NotificationManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Criar notificação
     */
    public function create($user_id, $title, $message, $type = 'info', $data = null) {
        try {
            $sql = "INSERT INTO notifications (user_id, title, message, type, data, created_at, read_at) 
                    VALUES (:user_id, :title, :message, :type, :data, NOW(), NULL)";
            
        $stmt = $this->conn->prepare($sql);
        $data_json = $data ? json_encode($data) : null;
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':data', $data_json);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro ao criar notificação: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obter notificações do usuário
     */
    public function getUserNotifications($user_id, $limit = 10, $unread_only = false) {
        try {
            $sql = "SELECT * FROM notifications WHERE user_id = :user_id";
            
            if ($unread_only) {
                $sql .= " AND read_at IS NULL";
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT :limit";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decodificar dados JSON se existirem
            foreach ($notifications as &$notification) {
                if ($notification['data']) {
                    $notification['data'] = json_decode($notification['data'], true);
                }
            }
            
            return $notifications;
        } catch (Exception $e) {
            error_log("Erro ao buscar notificações: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Marcar notificação como lida
     */
    public function markAsRead($notification_id, $user_id) {
        try {
            $sql = "UPDATE notifications SET read_at = NOW() 
                    WHERE id = :notification_id AND user_id = :user_id";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':notification_id', $notification_id);
            $stmt->bindParam(':user_id', $user_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro ao marcar notificação como lida: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Marcar todas as notificações como lidas
     */
    public function markAllAsRead($user_id) {
        try {
            $sql = "UPDATE notifications SET read_at = NOW() 
                    WHERE user_id = :user_id AND read_at IS NULL";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':user_id', $user_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro ao marcar todas as notificações como lidas: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Contar notificações não lidas
     */
    public function getUnreadCount($user_id) {
        try {
            $sql = "SELECT COUNT(*) as count FROM notifications 
                    WHERE user_id = :user_id AND read_at IS NULL";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'];
        } catch (Exception $e) {
            error_log("Erro ao contar notificações não lidas: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Deletar notificação
     */
    public function delete($notification_id, $user_id) {
        try {
            $sql = "DELETE FROM notifications 
                    WHERE id = :notification_id AND user_id = :user_id";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':notification_id', $notification_id);
            $stmt->bindParam(':user_id', $user_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro ao deletar notificação: " . $e->getMessage());
            return false;
        }
    }
}

// Funções helper para notificações específicas do sistema

/**
 * Notificar sobre novo badge conquistado
 */
function notifyBadgeEarned($conn, $user_id, $badge_name, $motorista_nome) {
    $notification = new NotificationManager($conn);
    return $notification->create(
        $user_id,
        "🏆 Novo Badge Conquistado!",
        "O motorista {$motorista_nome} conquistou o badge: {$badge_name}",
        'success',
        ['type' => 'badge', 'badge_name' => $badge_name, 'motorista' => $motorista_nome]
    );
}

/**
 * Notificar sobre novo nível alcançado
 */
function notifyLevelUp($conn, $user_id, $nivel, $motorista_nome) {
    $notification = new NotificationManager($conn);
    return $notification->create(
        $user_id,
        "🎉 Novo Nível Alcançado!",
        "O motorista {$motorista_nome} alcançou o nível: {$nivel}",
        'success',
        ['type' => 'level_up', 'nivel' => $nivel, 'motorista' => $motorista_nome]
    );
}

/**
 * Notificar sobre ranking atualizado
 */
function notifyRankingUpdate($conn, $user_id, $motorista_nome, $posicao) {
    $notification = new NotificationManager($conn);
    return $notification->create(
        $user_id,
        "📊 Ranking Atualizado",
        "O motorista {$motorista_nome} está na posição {$posicao} do ranking",
        'info',
        ['type' => 'ranking', 'posicao' => $posicao, 'motorista' => $motorista_nome]
    );
}

/**
 * Notificar sobre desafio completado
 */
function notifyChallengeCompleted($conn, $user_id, $desafio_nome, $motorista_nome) {
    $notification = new NotificationManager($conn);
    return $notification->create(
        $user_id,
        "🎯 Desafio Completado!",
        "O motorista {$motorista_nome} completou o desafio: {$desafio_nome}",
        'success',
        ['type' => 'challenge', 'desafio' => $desafio_nome, 'motorista' => $motorista_nome]
    );
}
?>
