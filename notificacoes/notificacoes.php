<?php
require_once '../includes/db_connect.php';
require_once '../IA/ia_regras.php';

// Verificar se a sessão já está ativa antes de iniciá-la
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$empresa_id = $_SESSION['empresa_id'] ?? 1; // Ajuste conforme sua lógica de sessão
header('Content-Type: application/json');
$conn = getConnection();

$todas = isset($_GET['todas']) && $_GET['todas'] == '1';

try {
    if ($todas) {
        // Para "Ver todas": mostrar apenas notificações dos últimos 30 dias
        $stmt = $conn->prepare("SELECT * FROM notificacoes 
                               WHERE empresa_id = ? 
                               AND data_criacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                               ORDER BY data_criacao DESC 
                               LIMIT 100");
        $stmt->execute([$empresa_id]);
    } else {
        // Para notificações não lidas: mostrar apenas dos últimos 7 dias
        $stmt = $conn->prepare("SELECT * FROM notificacoes 
                               WHERE empresa_id = ? 
                               AND lida = 0 
                               AND data_criacao >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                               ORDER BY data_criacao DESC 
                               LIMIT 50");
        $stmt->execute([$empresa_id]);
    }
    
    $notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filtrar notificações duplicadas baseado em tipo, título e data
    $notificacoes_filtradas = [];
    $chaves_vistas = [];
    
    foreach ($notificacoes as $notif) {
        // Criar chave única baseada em tipo, título e data (dia)
        $data_dia = date('Y-m-d', strtotime($notif['data_criacao']));
        $chave = $notif['tipo'] . '_' . $notif['titulo'] . '_' . $data_dia;
        
        if (!in_array($chave, $chaves_vistas)) {
            $chaves_vistas[] = $chave;
            $notificacoes_filtradas[] = $notif;
        }
    }
    
    echo json_encode([
        'success' => true, 
        'notificacoes' => $notificacoes_filtradas,
        'total_original' => count($notificacoes),
        'total_filtrado' => count($notificacoes_filtradas)
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao buscar notificações: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar notificações']);
} 