<?php
// API para buscar posições de pneus
ob_start();
header('Content-Type: application/json');

try {
    // Incluir configuração de sessão do sistema principal
    require_once dirname(__DIR__, 2) . '/includes/config.php';
    require_once dirname(__DIR__, 2) . '/includes/db_connect.php';

    // Configurar e iniciar sessão
    configure_session();
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Verificar autenticação
    if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) {
        ob_clean();
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'error' => 'Usuário não autenticado',
            'debug' => [
                'session_status' => session_status(),
                'has_empresa_id' => isset($_SESSION['empresa_id']),
                'session_id' => session_id()
            ]
        ]);
        exit;
    }
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Erro ao inicializar: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit;
}

try {
    $conn = getConnection();
    
    // Buscar todas as posições de pneus
    $sql = "SELECT id, nome FROM posicoes_pneus ORDER BY nome";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $posicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log para debug
    error_log("API posicoes_pneus: " . count($posicoes) . " posições encontradas");
    
    ob_clean();
    echo json_encode([
        'success' => true, 
        'posicoes' => $posicoes,
        'total' => count($posicoes),
        'debug' => [
            'empresa_id' => $_SESSION['empresa_id'] ?? 'não definido',
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    ob_clean();
    error_log("Erro na API de posições: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
