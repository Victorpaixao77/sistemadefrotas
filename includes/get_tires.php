<?php
require_once 'config.php';
require_once 'functions.php';

// Configurar sessão
configure_session();
session_start();

// Verificar se o usuário está autenticado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

try {
    $pdo = getConnection();
    $empresa_id = $_SESSION['empresa_id'];
    
    // Se foi solicitado um pneu específico
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   s.nome as status_nome
            FROM pneus p
            LEFT JOIN status_pneus s ON s.id = p.status_id
            WHERE p.id = ? AND p.empresa_id = ?
        ");
        $stmt->execute([$_GET['id'], $empresa_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        // Paginação
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 5; // Registros por página
        $offset = ($page - 1) * $limit;
        
        // Conta o total de registros
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pneus WHERE empresa_id = ?");
        $stmt->execute([$empresa_id]);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Buscar pneus paginados
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   s.nome as status_nome
            FROM pneus p
            LEFT JOIN status_pneus s ON s.id = p.status_id
            WHERE p.empresa_id = ?
            ORDER BY p.id DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$empresa_id, $limit, $offset]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'current_page' => $page,
                'per_page' => $limit,
                'total_pages' => ceil($total / $limit)
            ]
        ]);
    }
} catch (Exception $e) {
    error_log('Erro em get_tires.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 