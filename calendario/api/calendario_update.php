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

// Validar campos obrigatórios
if (empty($input['id']) || empty($input['title']) || empty($input['category']) || empty($input['start'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Campos obrigatórios faltando']);
    exit;
}

try {
    $conn = getConnection();
    
    // Verificar se o evento pertence à empresa do usuário
    $check_sql = "SELECT id FROM calendario_eventos WHERE id = :id AND empresa_id = :empresa_id";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(':id', $input['id']);
    $check_stmt->bindParam(':empresa_id', $empresa_id);
    $check_stmt->execute();
    
    if (!$check_stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Evento não encontrado']);
        exit;
    }
    
    // Buscar categoria_id
    $categoria_sql = "SELECT id FROM categorias_calendario WHERE nome = :nome_categoria AND empresa_id = 1 LIMIT 1";
    $categoria_stmt = $conn->prepare($categoria_sql);
    $categoria_stmt->bindParam(':nome_categoria', $input['category']);
    $categoria_stmt->execute();
    
    $categoria_result = $categoria_stmt->fetch(PDO::FETCH_ASSOC);
    $categoria_id = $categoria_result ? $categoria_result['id'] : 6; // Personalizado
    
    // Preparar variáveis para bindParam
    $data_fim = $input['end'] ?: null;
    $descricao = $input['description'] ?: null;
    
    // Atualizar evento
    $update_sql = "UPDATE calendario_eventos SET
                    titulo = :titulo,
                    categoria_id = :categoria_id,
                    data_inicio = :data_inicio,
                    data_fim = :data_fim,
                    descricao = :descricao,
                    cor = :cor
                    WHERE id = :id AND empresa_id = :empresa_id";
    
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bindParam(':titulo', $input['title']);
    $update_stmt->bindParam(':categoria_id', $categoria_id);
    $update_stmt->bindParam(':data_inicio', $input['start']);
    $update_stmt->bindParam(':data_fim', $data_fim);
    $update_stmt->bindParam(':descricao', $descricao);
    $update_stmt->bindParam(':cor', $input['color']);
    $update_stmt->bindParam(':id', $input['id']);
    $update_stmt->bindParam(':empresa_id', $empresa_id);
    
    $result = $update_stmt->execute();
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Evento atualizado com sucesso'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao atualizar evento']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
}
?>
