<?php
// Configurar sessão igual ao sistema principal
session_name('sistema_frotas_session');
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/sistema-frotas',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];
$usuario_id = $_SESSION['usuario_id'];

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Obter dados do POST
$input = json_decode(file_get_contents('php://input'), true);
$event_id = $input['id'] ?? null;

if (!$event_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID do evento não fornecido']);
    exit;
}

try {
    $conn = getConnection();
    
    // Verificar se o evento pertence à empresa do usuário
    $check_sql = "SELECT id FROM calendario_eventos WHERE id = :id AND empresa_id = :empresa_id";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(':id', $event_id);
    $check_stmt->bindParam(':empresa_id', $empresa_id);
    $check_stmt->execute();
    
    if (!$check_stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Evento não encontrado']);
        exit;
    }
    
    // Excluir evento
    $delete_sql = "DELETE FROM calendario_eventos WHERE id = :id AND empresa_id = :empresa_id";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bindParam(':id', $event_id);
    $delete_stmt->bindParam(':empresa_id', $empresa_id);
    
    $result = $delete_stmt->execute();
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Evento excluído com sucesso'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao excluir evento']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
}
?>
