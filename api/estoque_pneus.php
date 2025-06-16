<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Check if user is logged in, if not redirect to login page
require_authentication();

// Get empresa_id from session
$empresa_id = $_SESSION['empresa_id'];

// Get database connection
try {
    $conn = getConnection();
} catch (PDOException $e) {
    error_log("Erro de conexão com o banco: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => true, 'message' => 'Erro de conexão com o banco de dados']);
    exit;
}

// Get action from request
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Process the requested action
switch ($action) {
    case 'get_estoque':
        try {
            $status = isset($_GET['status']) ? $_GET['status'] : '';
            
            $sql = "SELECT 
                p.id as pneu_id,
                p.empresa_id,
                p.numero_serie,
                p.marca,
                p.modelo,
                p.medida,
                p.sulco_inicial,
                p.dot,
                p.km_instalacao,
                p.data_instalacao,
                p.vida_util_km,
                p.numero_recapagens,
                p.data_ultima_recapagem,
                p.lote,
                p.data_entrada,
                p.status_id,
                p.observacoes,
                p.created_at as pneu_created_at,
                p.updated_at as pneu_updated_at,
                s.nome as status_nome,
                ep.disponivel
            FROM pneus p
            LEFT JOIN status_pneus s ON p.status_id = s.id
            LEFT JOIN estoque_pneus ep ON ep.pneu_id = p.id
            WHERE p.empresa_id = :empresa_id
            AND NOT EXISTS (
                SELECT 1 FROM pneus_alocacao pa 
                WHERE pa.pneu_id = p.id 
                AND pa.status = 'alocado'
            )";
            
            $params = [':empresa_id' => $empresa_id];
            
            if ($status !== '') {
                $sql .= " AND p.status_id = :status";
                $params[':status'] = $status;
            }
            
            $sql .= " ORDER BY p.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $pneus = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'pneus' => $pneus]);
        } catch (PDOException $e) {
            error_log("Erro ao buscar estoque de pneus: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['error' => true, 'message' => 'Erro ao buscar dados do estoque']);
        }
        break;
        
    case 'update_status':
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['pneu_id']) || !isset($data['status_id']) || !isset($data['disponivel'])) {
                throw new Exception('Dados incompletos');
            }
            
            // Verifica se o pneu pertence à empresa
            $stmt = $conn->prepare("SELECT id FROM pneus WHERE id = :pneu_id AND empresa_id = :empresa_id");
            $stmt->bindParam(':pneu_id', $data['pneu_id']);
            $stmt->bindParam(':empresa_id', $empresa_id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Pneu não encontrado');
            }
            
            // Atualiza o status e disponibilidade
            $stmt = $conn->prepare("UPDATE estoque_pneus 
                                  SET status_id = :status_id, 
                                      disponivel = :disponivel,
                                      updated_at = NOW()
                                  WHERE pneu_id = :pneu_id");
            
            $stmt->bindParam(':pneu_id', $data['pneu_id']);
            $stmt->bindParam(':status_id', $data['status_id']);
            $stmt->bindParam(':disponivel', $data['disponivel']);
            $stmt->execute();
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso']);
        } catch (Exception $e) {
            error_log("Erro ao atualizar status do pneu: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['error' => true, 'message' => $e->getMessage()]);
        }
        break;
        
    default:
        header('Content-Type: application/json');
        echo json_encode(['error' => true, 'message' => 'Ação inválida']);
        break;
} 