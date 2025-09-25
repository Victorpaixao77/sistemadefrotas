<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';

configure_session();
session_start();

// Verificar se o usuário está autenticado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];

// Verificar se é uma requisição GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'list') {
        $veiculo_id = $_GET['veiculo_id'] ?? '';
        $data = $_GET['data'] ?? '';
        
        if (empty($veiculo_id) || empty($data)) {
            echo json_encode(['success' => false, 'message' => 'Veículo ou data não informados']);
            exit;
        }

        try {
            $conn = getConnection();
            
            // Buscar rotas do veículo na data selecionada ou posterior
            $sql = "SELECT r.id, r.data_rota, co.nome as cidade_origem_nome, cd.nome as cidade_destino_nome 
                   FROM rotas r 
                   INNER JOIN cidades co ON r.cidade_origem_id = co.id 
                   INNER JOIN cidades cd ON r.cidade_destino_id = cd.id 
                   WHERE r.empresa_id = :empresa_id 
                   AND r.motorista_id = :motorista_id 
                   AND r.veiculo_id = :veiculo_id 
                   AND r.data_rota >= :data_rota 
                   AND r.status = 'pendente'
                   ORDER BY r.data_rota";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':empresa_id', $empresa_id);
            $stmt->bindParam(':motorista_id', $motorista_id);
            $stmt->bindParam(':veiculo_id', $veiculo_id);
            $stmt->bindParam(':data_rota', $data);
            $stmt->execute();
            
            $rotas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $rotas
            ]);
            
        } catch (PDOException $e) {
            error_log('Erro ao buscar rotas: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao buscar rotas'
            ]);
        }
    }
} 