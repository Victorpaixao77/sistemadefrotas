<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ensure the request is authenticated
require_authentication();

// Create database connection
$conn = getConnection();

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Debug session state
error_log("Session state in manutencoes.php API: " . print_r($_SESSION, true));

switch ($method) {
    case 'GET':
        // Handle GET request (fetch maintenance)
        try {
            $id = isset($_GET['id']) ? intval($_GET['id']) : null;
            
            if ($id) {
                // Fetch specific maintenance
                $sql = "SELECT * FROM manutencoes WHERE id = :id AND empresa_id = :empresa_id";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->bindParam(':empresa_id', $_SESSION['empresa_id'], PDO::PARAM_INT);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    echo json_encode(['success' => true, 'data' => $result]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Manutenção não encontrada']);
                }
            } else {
                // Fetch all maintenances
                $sql = "SELECT * FROM manutencoes WHERE empresa_id = :empresa_id ORDER BY data_manutencao DESC";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':empresa_id', $_SESSION['empresa_id'], PDO::PARAM_INT);
                $stmt->execute();
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $result]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao buscar manutenções: ' . $e->getMessage()]);
        }
        break;

    case 'POST':
        // Handle POST request (create maintenance)
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                throw new Exception('Dados inválidos');
            }
            
            // Required fields validation
            $required_fields = ['data_manutencao', 'veiculo_id', 'tipo_manutencao_id', 'componente_id', 
                              'status_manutencao_id', 'km_atual', 'valor', 'descricao', 'descricao_servico', 
                              'responsavel_aprovacao'];
            
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    throw new Exception("Campo obrigatório não preenchido: $field");
                }
            }
            
            // Insert maintenance record
            $sql = "INSERT INTO manutencoes (
                        empresa_id, data_manutencao, veiculo_id, tipo_manutencao_id, 
                        componente_id, status_manutencao_id, km_atual, fornecedor,
                        valor, custo_total, nota_fiscal, descricao, descricao_servico,
                        observacoes, responsavel_aprovacao
                    ) VALUES (
                        :empresa_id, :data_manutencao, :veiculo_id, :tipo_manutencao_id,
                        :componente_id, :status_manutencao_id, :km_atual, :fornecedor,
                        :valor, :custo_total, :nota_fiscal, :descricao, :descricao_servico,
                        :observacoes, :responsavel_aprovacao
                    )";
            
            $stmt = $conn->prepare($sql);
            
            $stmt->bindParam(':empresa_id', $_SESSION['empresa_id'], PDO::PARAM_INT);
            $stmt->bindParam(':data_manutencao', $data['data_manutencao']);
            $stmt->bindParam(':veiculo_id', $data['veiculo_id'], PDO::PARAM_INT);
            $stmt->bindParam(':tipo_manutencao_id', $data['tipo_manutencao_id'], PDO::PARAM_INT);
            $stmt->bindParam(':componente_id', $data['componente_id'], PDO::PARAM_INT);
            $stmt->bindParam(':status_manutencao_id', $data['status_manutencao_id'], PDO::PARAM_INT);
            $stmt->bindParam(':km_atual', $data['km_atual'], PDO::PARAM_INT);
            $stmt->bindParam(':fornecedor', $data['fornecedor']);
            $stmt->bindParam(':valor', $data['valor'], PDO::PARAM_STR);
            $stmt->bindParam(':custo_total', $data['custo_total'], PDO::PARAM_STR);
            $stmt->bindParam(':nota_fiscal', $data['nota_fiscal']);
            $stmt->bindParam(':descricao', $data['descricao']);
            $stmt->bindParam(':descricao_servico', $data['descricao_servico']);
            $stmt->bindParam(':observacoes', $data['observacoes']);
            $stmt->bindParam(':responsavel_aprovacao', $data['responsavel_aprovacao']);
            
            $stmt->execute();
            $id = $conn->lastInsertId();
            
            echo json_encode(['success' => true, 'message' => 'Manutenção cadastrada com sucesso', 'id' => $id]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'PUT':
        // Handle PUT request (update maintenance)
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || !isset($data['id'])) {
                throw new Exception('Dados inválidos ou ID não fornecido');
            }
            
            // Verify if maintenance exists and belongs to the company
            $check_sql = "SELECT id FROM manutencoes WHERE id = :id AND empresa_id = :empresa_id";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);
            $check_stmt->bindParam(':empresa_id', $_SESSION['empresa_id'], PDO::PARAM_INT);
            $check_stmt->execute();
            
            if (!$check_stmt->fetch()) {
                throw new Exception('Manutenção não encontrada ou não pertence à empresa');
            }
            
            // Update maintenance record
            $sql = "UPDATE manutencoes SET 
                        data_manutencao = :data_manutencao,
                        veiculo_id = :veiculo_id,
                        tipo_manutencao_id = :tipo_manutencao_id,
                        componente_id = :componente_id,
                        status_manutencao_id = :status_manutencao_id,
                        km_atual = :km_atual,
                        fornecedor = :fornecedor,
                        valor = :valor,
                        custo_total = :custo_total,
                        nota_fiscal = :nota_fiscal,
                        descricao = :descricao,
                        descricao_servico = :descricao_servico,
                        observacoes = :observacoes,
                        responsavel_aprovacao = :responsavel_aprovacao
                    WHERE id = :id AND empresa_id = :empresa_id";
            
            $stmt = $conn->prepare($sql);
            
            $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);
            $stmt->bindParam(':empresa_id', $_SESSION['empresa_id'], PDO::PARAM_INT);
            $stmt->bindParam(':data_manutencao', $data['data_manutencao']);
            $stmt->bindParam(':veiculo_id', $data['veiculo_id'], PDO::PARAM_INT);
            $stmt->bindParam(':tipo_manutencao_id', $data['tipo_manutencao_id'], PDO::PARAM_INT);
            $stmt->bindParam(':componente_id', $data['componente_id'], PDO::PARAM_INT);
            $stmt->bindParam(':status_manutencao_id', $data['status_manutencao_id'], PDO::PARAM_INT);
            $stmt->bindParam(':km_atual', $data['km_atual'], PDO::PARAM_INT);
            $stmt->bindParam(':fornecedor', $data['fornecedor']);
            $stmt->bindParam(':valor', $data['valor'], PDO::PARAM_STR);
            $stmt->bindParam(':custo_total', $data['custo_total'], PDO::PARAM_STR);
            $stmt->bindParam(':nota_fiscal', $data['nota_fiscal']);
            $stmt->bindParam(':descricao', $data['descricao']);
            $stmt->bindParam(':descricao_servico', $data['descricao_servico']);
            $stmt->bindParam(':observacoes', $data['observacoes']);
            $stmt->bindParam(':responsavel_aprovacao', $data['responsavel_aprovacao']);
            
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Manutenção atualizada com sucesso']);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'DELETE':
        // Handle DELETE request
        try {
            $id = isset($_GET['id']) ? intval($_GET['id']) : null;
            
            if (!$id) {
                throw new Exception('ID não fornecido');
            }
            
            // Verify if maintenance exists and belongs to the company
            $check_sql = "SELECT id FROM manutencoes WHERE id = :id AND empresa_id = :empresa_id";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $check_stmt->bindParam(':empresa_id', $_SESSION['empresa_id'], PDO::PARAM_INT);
            $check_stmt->execute();
            
            if (!$check_stmt->fetch()) {
                throw new Exception('Manutenção não encontrada ou não pertence à empresa');
            }
            
            // Delete maintenance record
            $sql = "DELETE FROM manutencoes WHERE id = :id AND empresa_id = :empresa_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':empresa_id', $_SESSION['empresa_id'], PDO::PARAM_INT);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Manutenção excluída com sucesso']);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
        break;
} 