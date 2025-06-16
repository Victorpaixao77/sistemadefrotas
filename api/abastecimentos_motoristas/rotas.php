<?php
// Refuel Motoristas Rotas API

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Configure session before starting it
if (function_exists('configure_session')) {
    configure_session();
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Ensure the request is authenticated
if (function_exists('require_authentication')) {
    require_authentication();
}

// Get empresa_id from session
$empresa_id = isset($_SESSION["empresa_id"]) ? $_SESSION["empresa_id"] : null;
if (!$empresa_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'ID da empresa não encontrado'
    ]);
    exit;
}

try {
    $conn = getConnection();
    $veiculo_id = isset($_GET['veiculo_id']) ? intval($_GET['veiculo_id']) : 0;
    $motorista_id = isset($_GET['motorista_id']) ? intval($_GET['motorista_id']) : 0;
    $data = isset($_GET['data']) ? $_GET['data'] : null;
    if ($veiculo_id && $motorista_id && $data) {
        $sql = "SELECT r.id, r.data_rota, co.nome as cidade_origem_nome, cd.nome as cidade_destino_nome
                FROM rotas r
                LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                WHERE r.empresa_id = :empresa_id
                AND r.veiculo_id = :veiculo_id
                AND r.motorista_id = :motorista_id
                AND r.data_rota >= :data
                ORDER BY r.data_rota DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':veiculo_id', $veiculo_id, PDO::PARAM_INT);
        $stmt->bindParam(':motorista_id', $motorista_id, PDO::PARAM_INT);
        $stmt->bindParam(':data', $data);
        $stmt->execute();
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Parâmetros insuficientes']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 