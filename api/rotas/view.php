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

// Verificar se o ID da rota foi fornecido
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID da rota não fornecido']);
    exit;
}

$rota_id = $_GET['id'];
$empresa_id = $_SESSION['empresa_id'];

try {
    $conn = getConnection();
    
    // Buscar detalhes da rota
    $stmt = $conn->prepare("
        SELECT r.*, 
               m.nome as motorista_nome,
               v.placa as veiculo_placa,
               co.nome as cidade_origem_nome,
               cd.nome as cidade_destino_nome
        FROM rotas r
        LEFT JOIN motoristas m ON r.motorista_id = m.id
        LEFT JOIN veiculos v ON r.veiculo_id = v.id
        LEFT JOIN cidades co ON r.cidade_origem_id = co.id
        LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
        WHERE r.id = ? AND r.empresa_id = ?
    ");
    $stmt->execute([$rota_id, $empresa_id]);
    $rota = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$rota) {
        http_response_code(404);
        echo json_encode(['error' => 'Rota não encontrada']);
        exit;
    }
    
    // Buscar despesas da viagem
    $stmt = $conn->prepare("
        SELECT * FROM despesas_viagem 
        WHERE rota_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$rota_id]);
    $despesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Adicionar despesas ao resultado
    $rota['despesas'] = $despesas;
    
    echo json_encode(['success' => true, 'data' => $rota]);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar detalhes da rota: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar detalhes da rota']);
}
?> 