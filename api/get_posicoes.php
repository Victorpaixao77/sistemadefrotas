<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Configurar sessão
configure_session();
session_start();

// Verificar se o usuário está autenticado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

header('Content-Type: application/json');

// Configurar log de erros
error_log("Iniciando get_posicoes.php");

try {
    $conn = getConnection();
    error_log("Conexão com banco de dados estabelecida");
    
    $stmt = $conn->prepare("SELECT id, nome FROM posicoes_pneus ORDER BY nome");
    error_log("Query preparada");
    
    $stmt->execute();
    error_log("Query executada");
    
    $posicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Posições encontradas: " . count($posicoes));
    error_log("Dados das posições: " . json_encode($posicoes));
    
    echo json_encode($posicoes);
    error_log("Resposta enviada com sucesso");
} catch (Exception $e) {
    error_log("Erro em get_posicoes.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar posições: ' . $e->getMessage()
    ]);
} 