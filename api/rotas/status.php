<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Iniciar sessão
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Verificar se os dados necessários foram enviados
if (!isset($_POST['id']) || !isset($_POST['status'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados incompletos']);
    exit;
}

$rota_id = $_POST['id'];
$status = $_POST['status'];
$empresa_id = $_SESSION['empresa_id'];

// Validar status
$status_permitidos = ['pendente', 'aprovado', 'rejeitado'];
if (!in_array($status, $status_permitidos)) {
    http_response_code(400);
    echo json_encode(['error' => 'Status inválido']);
    exit;
}

try {
    $conn = getConnection();
    
    // Verificar se a rota pertence à empresa do usuário
    $stmt = $conn->prepare("SELECT id FROM rotas WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$rota_id, $empresa_id]);
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Rota não encontrada ou sem permissão']);
        exit;
    }
    
    // Atualizar o status da rota
    $stmt = $conn->prepare("UPDATE rotas SET status = ? WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$status, $rota_id, $empresa_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao atualizar status']);
    }
    
} catch (PDOException $e) {
    error_log("Erro ao atualizar status da rota: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao processar a solicitação']);
}
?> 