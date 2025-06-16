<?php
// Configurações de sessão
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.cookie_lifetime', 3600);
ini_set('session.cookie_secure', 0);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

// Define o nome da sessão
session_name('sistema_frotas_session');

// Define parâmetros do cookie da sessão
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/sistema-frotas',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Inicia a sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limpa qualquer saída anterior para garantir JSON puro
if (ob_get_length()) ob_clean();

// Habilita exibição de erros para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Define o tipo de conteúdo como JSON
header('Content-Type: application/json');

try {
    // Log detalhado da sessão
    error_log("=== Início da requisição ===");
    error_log("Session ID: " . session_id());
    error_log("Session Name: " . session_name());
    error_log("Session Status: " . session_status());
    error_log("Session Data: " . print_r($_SESSION, true));
    error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
    error_log("Request URI: " . $_SERVER['REQUEST_URI']);
    error_log("Query String: " . $_SERVER['QUERY_STRING']);
    error_log("HTTP_REFERER: " . ($_SERVER['HTTP_REFERER'] ?? 'não definido'));
    error_log("HTTP_COOKIE: " . ($_SERVER['HTTP_COOKIE'] ?? 'não definido'));
    
    // Verifica se é uma requisição GET
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método não permitido');
    }
    
    // Verifica se a ação é 'list'
    if (!isset($_GET['action']) || $_GET['action'] !== 'list') {
        throw new Exception('Ação inválida');
    }
    
    // Verifica se a data foi fornecida
    if (!isset($_GET['data'])) {
        throw new Exception('Data não fornecida');
    }
    
    // Verifica se o motorista está logado
    if (!isset($_SESSION['motorista_id']) || !isset($_SESSION['empresa_id'])) {
        error_log("Sessão inválida - Dados da sessão:");
        error_log("motorista_id: " . (isset($_SESSION['motorista_id']) ? $_SESSION['motorista_id'] : 'não definido'));
        error_log("empresa_id: " . (isset($_SESSION['empresa_id']) ? $_SESSION['empresa_id'] : 'não definido'));
        error_log("tipo_usuario: " . (isset($_SESSION['tipo_usuario']) ? $_SESSION['tipo_usuario'] : 'não definido'));
        throw new Exception('Sessão inválida ou expirada');
    }
    
    // Obtém a data selecionada
    $data = $_GET['data'];
    
    // Log dos parâmetros
    error_log("Parâmetros recebidos:");
    error_log("Data: " . $data);
    error_log("Motorista ID: " . $_SESSION['motorista_id']);
    error_log("Empresa ID: " . $_SESSION['empresa_id']);
    
    // Conecta ao banco de dados
    require_once '../db.php';
    $conn = getConnection();
    
    // Query para buscar veículos
    $sql = "SELECT DISTINCT v.* 
            FROM veiculos v 
            INNER JOIN rotas r ON v.id = r.veiculo_id 
            WHERE v.empresa_id = :empresa_id 
            AND r.motorista_id = :motorista_id 
            AND r.data_rota >= :data_rota 
            AND r.status = 'pendente' 
            ORDER BY v.placa";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':empresa_id', $_SESSION['empresa_id']);
    $stmt->bindValue(':motorista_id', $_SESSION['motorista_id']);
    $stmt->bindValue(':data_rota', $data);
    
    // Log da query
    error_log("SQL Query: " . $sql);
    error_log("Parâmetros da query:");
    error_log("empresa_id: " . $_SESSION['empresa_id']);
    error_log("motorista_id: " . $_SESSION['motorista_id']);
    error_log("data_rota: " . $data);
    
    $stmt->execute();
    $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log dos resultados
    error_log("Número de veículos encontrados: " . count($veiculos));
    error_log("Veículos: " . print_r($veiculos, true));
    
    // Retorna os veículos
    echo json_encode([
        'success' => true,
        'data' => $veiculos
    ]);
    exit;
    
} catch (PDOException $e) {
    error_log("Erro PDO: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    if (ob_get_length()) ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao carregar veículos: ' . $e->getMessage()
    ]);
    exit;
} catch (Exception $e) {
    error_log("Erro: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    if (ob_get_length()) ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
} 