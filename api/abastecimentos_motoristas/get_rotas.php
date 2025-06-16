<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Verificar autenticação
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

try {
    $conn = getConnection();
    $empresa_id = $_SESSION['empresa_id'];
    
    // Parâmetros da requisição
    $veiculo_id = isset($_GET['veiculo_id']) ? intval($_GET['veiculo_id']) : null;
    $motorista_id = isset($_GET['motorista_id']) ? intval($_GET['motorista_id']) : null;
    $data = isset($_GET['data']) ? $_GET['data'] : null;
    
    if (!$veiculo_id || !$motorista_id || !$data) {
        throw new Exception('Parâmetros inválidos');
    }
    
    // Buscar rotas para o veículo, motorista e data específicos
    $sql = "SELECT 
                r.id,
                r.data_rota,
                co.nome as cidade_origem_nome,
                cd.nome as cidade_destino_nome
            FROM rotas r
            LEFT JOIN cidades co ON r.cidade_origem_id = co.id
            LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
            WHERE r.empresa_id = :empresa_id
            AND r.veiculo_id = :veiculo_id
            AND r.motorista_id = :motorista_id
            AND DATE(r.data_rota) = :data
            ORDER BY r.data_rota DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':empresa_id' => $empresa_id,
        ':veiculo_id' => $veiculo_id,
        ':motorista_id' => $motorista_id,
        ':data' => $data
    ]);
    
    $rotas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $rotas
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 