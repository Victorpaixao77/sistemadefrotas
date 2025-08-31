<?php
// API para buscar posições de pneus
ob_start();
header('Content-Type: application/json');

// Definir constantes se não existirem
if (!defined('DB_SERVER')) define('DB_SERVER', 'localhost:3307');
if (!defined('DB_USERNAME')) define('DB_USERNAME', 'root');
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', '');
if (!defined('DB_NAME')) define('DB_NAME', 'sistema_frotas');

// Incluir conexão
require_once dirname(__DIR__, 2) . '/includes/db_connect.php';

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticação
if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Usuário não autenticado']);
    exit;
}

try {
    $conn = getConnection();
    
    // Buscar todas as posições de pneus
    $sql = "SELECT id, nome FROM posicoes_pneus ORDER BY nome";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $posicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    ob_clean();
    echo json_encode(['success' => true, 'posicoes' => $posicoes]);
    
} catch (Exception $e) {
    ob_clean();
    error_log("Erro na API de posições: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
