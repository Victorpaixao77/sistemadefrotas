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

// Debug da sessão
error_log("=== DEBUG SESSÃO ===");
error_log("Session ID: " . session_id());
error_log("Session Name: " . session_name());
error_log("Session Status: " . session_status());
error_log("Session Data: " . print_r($_SESSION, true));

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['empresa_id'])) {
    error_log("❌ Sessão inválida - Dados da sessão:");
    error_log("usuario_id: " . (isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 'não definido'));
    error_log("empresa_id: " . (isset($_SESSION['empresa_id']) ? $_SESSION['empresa_id'] : 'não definido'));
    
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];
$usuario_id = $_SESSION['usuario_id'];

error_log("✅ Sessão OK - Usuário: $usuario_id, Empresa: $empresa_id");

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Debug dos dados recebidos
error_log("=== DEBUG DADOS ===");
error_log("POST Data: " . print_r($_POST, true));
error_log("Raw Input: " . file_get_contents('php://input'));

// Obter dados do POST diretamente (como outras APIs)
$title = $_POST['title'] ?? '';
$category = $_POST['category'] ?? '';
$start = $_POST['start'] ?? '';
$end = $_POST['end'] ?? '';
$description = $_POST['description'] ?? '';
$color = $_POST['color'] ?? '#3788d8';

error_log("Campos extraídos - Title: '$title', Category: '$category', Start: '$start'");

// Validar campos obrigatórios
if (empty($title) || empty($category) || empty($start)) {
    error_log("❌ Campos obrigatórios faltando");
    http_response_code(400);
    echo json_encode(['error' => 'Campos obrigatórios faltando']);
    exit;
}

error_log("✅ Todos os campos obrigatórios preenchidos");

try {
    $conn = getConnection();
    error_log("✅ Conexão com banco OK");
    
    // Buscar categoria_id
    $categoria_sql = "SELECT id FROM categorias_calendario WHERE nome = :nome_categoria AND empresa_id = 1 LIMIT 1";
    $categoria_stmt = $conn->prepare($categoria_sql);
    $categoria_stmt->bindParam(':nome_categoria', $category);
    $categoria_stmt->execute();
    
    $categoria_result = $categoria_stmt->fetch(PDO::FETCH_ASSOC);
    $categoria_id = $categoria_result ? $categoria_result['id'] : 6; // Personalizado
    
    error_log("✅ Categoria encontrada: $categoria_id");
    
    // Inserir evento
    $sql = "INSERT INTO calendario_eventos (
                titulo, categoria_id, data_inicio, data_fim, descricao, 
                cor, empresa_id, usuario_id
            ) VALUES (
                :titulo, :categoria_id, :data_inicio, :data_fim, :descricao,
                :cor, :empresa_id, :usuario_id
            )";
    
    // Preparar variáveis para bindParam
    $data_fim = $end ?: null;
    $descricao = $description ?: null;
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':titulo', $title);
    $stmt->bindParam(':categoria_id', $categoria_id);
    $stmt->bindParam(':data_inicio', $start);
    $stmt->bindParam(':data_fim', $data_fim);
    $stmt->bindParam(':descricao', $descricao);
    $stmt->bindParam(':cor', $color);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->bindParam(':usuario_id', $usuario_id);
    
    $result = $stmt->execute();
    
    if ($result) {
        $eventId = $conn->lastInsertId();
        error_log("✅ Evento inserido com sucesso! ID: $eventId");
        echo json_encode([
            'success' => true,
            'message' => 'Evento criado com sucesso',
            'event_id' => $eventId
        ]);
    } else {
        error_log("❌ Erro ao inserir evento");
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao inserir evento']);
    }
    
} catch (Exception $e) {
    error_log("❌ ERRO: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
}

error_log("=== FINALIZANDO API ===");
?>
