<?php
// API de ações dos motoristas

// Inclui arquivos de configuração e funções primeiro
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Configura a sessão antes de iniciá-la
configure_session();

// Inicializa a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define o tipo de conteúdo como JSON
header('Content-Type: application/json');

// Garante que a requisição está autenticada
require_authentication();

// Obtém o empresa_id da sessão
$empresa_id = isset($_SESSION["empresa_id"]) ? $_SESSION["empresa_id"] : null;

// Verifica o parâmetro de ação
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    // Trata diferentes ações
    switch ($action) {
        case 'get_categorias_cnh':
            echo json_encode(getCategoriasCNH());
            break;
            
        case 'get_tipos_contrato':
            echo json_encode(getTiposContrato());
            break;
            
        case 'get_disponibilidades':
            echo json_encode(getDisponibilidades());
            break;
            
        case 'add':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            echo json_encode(addMotorist($data));
            break;
            
        case 'update':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }
            
            $id = isset($_GET['id']) ? $_GET['id'] : null;
            if (!$id) {
                throw new Exception('ID do motorista não fornecido');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            echo json_encode(updateMotorist($id, $data));
            break;
            
        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }
            
            $id = isset($_GET['id']) ? $_GET['id'] : null;
            if (!$id) {
                throw new Exception('ID do motorista não fornecido');
            }
            
            echo json_encode(deleteMotorist($id));
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Obtém lista de categorias de CNH
 * 
 * @return array Lista de categorias
 */
function getCategoriasCNH() {
    try {
        $conn = getConnection();
        
        $sql = "SELECT id, nome FROM categorias_cnh ORDER BY nome";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        
        return [
            'success' => true,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
        
    } catch(PDOException $e) {
        error_log("Erro em getCategoriasCNH: " . $e->getMessage());
        http_response_code(500);
        return [
            'success' => false,
            'error' => DEBUG_MODE ? $e->getMessage() : 'Erro ao buscar categorias de CNH'
        ];
    }
}

/**
 * Obtém lista de tipos de contrato
 * 
 * @return array Lista de tipos de contrato
 */
function getTiposContrato() {
    try {
        $conn = getConnection();
        
        $sql = "SELECT id, nome FROM tipos_contrato ORDER BY nome";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        
        return [
            'success' => true,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
        
    } catch(PDOException $e) {
        error_log("Erro em getTiposContrato: " . $e->getMessage());
        http_response_code(500);
        return [
            'success' => false,
            'error' => DEBUG_MODE ? $e->getMessage() : 'Erro ao buscar tipos de contrato'
        ];
    }
}

/**
 * Obtém lista de disponibilidades
 * 
 * @return array Lista de disponibilidades
 */
function getDisponibilidades() {
    try {
        $conn = getConnection();
        
        $sql = "SELECT id, nome FROM disponibilidades_motoristas ORDER BY nome";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        
        return [
            'success' => true,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
        
    } catch(PDOException $e) {
        error_log("Erro em getDisponibilidades: " . $e->getMessage());
        http_response_code(500);
        return [
            'success' => false,
            'error' => DEBUG_MODE ? $e->getMessage() : 'Erro ao buscar disponibilidades'
        ];
    }
}

/**
 * Adiciona um novo motorista
 * 
 * @param array $data Dados do motorista
 * @return array Resultado da operação
 */
function addMotorist($data) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        
        // Valida campos obrigatórios
        if (empty($data['nome'])) {
            throw new Exception('Nome é obrigatório');
        }
        if (empty($data['cpf'])) {
            throw new Exception('CPF é obrigatório');
        }
        
        // Prepara SQL
        $sql = "INSERT INTO motoristas (
            empresa_id, nome, cpf, cnh, categoria_cnh_id, 
            data_validade_cnh, telefone, telefone_emergencia, 
            email, endereco, data_contratacao, observacoes, 
            tipo_contrato_id, disponibilidade_id, porcentagem_comissao
        ) VALUES (
            :empresa_id, :nome, :cpf, :cnh, :categoria_cnh_id,
            :data_validade_cnh, :telefone, :telefone_emergencia,
            :email, :endereco, :data_contratacao, :observacoes,
            :tipo_contrato_id, :disponibilidade_id, :porcentagem_comissao
        )";
        
        $stmt = $conn->prepare($sql);
        
        // Vincula parâmetros
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->bindParam(':nome', $data['nome']);
        $stmt->bindParam(':cpf', $data['cpf']);
        $stmt->bindParam(':cnh', $data['cnh']);
        $stmt->bindParam(':categoria_cnh_id', $data['categoria_cnh_id']);
        $stmt->bindParam(':data_validade_cnh', $data['data_validade_cnh']);
        $stmt->bindParam(':telefone', $data['telefone']);
        $stmt->bindParam(':telefone_emergencia', $data['telefone_emergencia']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':endereco', $data['endereco']);
        $stmt->bindParam(':data_contratacao', $data['data_contratacao']);
        $stmt->bindParam(':observacoes', $data['observacoes']);
        $stmt->bindParam(':tipo_contrato_id', $data['tipo_contrato_id']);
        $stmt->bindParam(':disponibilidade_id', $data['disponibilidade_id']);
        
        // Trata o campo porcentagem_comissao
        $porcentagem_comissao = !empty($data['porcentagem_comissao']) ? $data['porcentagem_comissao'] : null;
        $stmt->bindParam(':porcentagem_comissao', $porcentagem_comissao);
        
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Motorista adicionado com sucesso',
            'id' => $conn->lastInsertId()
        ];
        
    } catch(PDOException $e) {
        error_log("Erro em addMotorist: " . $e->getMessage());
        http_response_code(500);
        return [
            'success' => false,
            'error' => DEBUG_MODE ? $e->getMessage() : 'Erro ao adicionar motorista'
        ];
    }
}

/**
 * Atualiza um motorista existente
 * 
 * @param int $id ID do motorista
 * @param array $data Dados atualizados do motorista
 * @return array Resultado da operação
 */
function updateMotorist($id, $data) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        
        // Valida campos obrigatórios
        if (empty($data['nome'])) {
            throw new Exception('Nome é obrigatório');
        }
        if (empty($data['cpf'])) {
            throw new Exception('CPF é obrigatório');
        }
        
        // Prepara SQL
        $sql = "UPDATE motoristas SET 
            nome = :nome,
            cpf = :cpf,
            cnh = :cnh,
            categoria_cnh_id = :categoria_cnh_id,
            data_validade_cnh = :data_validade_cnh,
            telefone = :telefone,
            telefone_emergencia = :telefone_emergencia,
            email = :email,
            endereco = :endereco,
            data_contratacao = :data_contratacao,
            observacoes = :observacoes,
            tipo_contrato_id = :tipo_contrato_id,
            disponibilidade_id = :disponibilidade_id,
            porcentagem_comissao = :porcentagem_comissao
            WHERE id = :id AND empresa_id = :empresa_id";
        
        $stmt = $conn->prepare($sql);
        
        // Vincula parâmetros
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->bindParam(':nome', $data['nome']);
        $stmt->bindParam(':cpf', $data['cpf']);
        $stmt->bindParam(':cnh', $data['cnh']);
        $stmt->bindParam(':categoria_cnh_id', $data['categoria_cnh_id']);
        $stmt->bindParam(':data_validade_cnh', $data['data_validade_cnh']);
        $stmt->bindParam(':telefone', $data['telefone']);
        $stmt->bindParam(':telefone_emergencia', $data['telefone_emergencia']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':endereco', $data['endereco']);
        $stmt->bindParam(':data_contratacao', $data['data_contratacao']);
        $stmt->bindParam(':observacoes', $data['observacoes']);
        $stmt->bindParam(':tipo_contrato_id', $data['tipo_contrato_id']);
        $stmt->bindParam(':disponibilidade_id', $data['disponibilidade_id']);
        $stmt->bindParam(':porcentagem_comissao', $data['porcentagem_comissao']);
        
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Motorista atualizado com sucesso'
        ];
        
    } catch(PDOException $e) {
        error_log("Erro em updateMotorist: " . $e->getMessage());
        http_response_code(500);
        return [
            'success' => false,
            'error' => DEBUG_MODE ? $e->getMessage() : 'Erro ao atualizar motorista'
        ];
    }
}

/**
 * Exclui um motorista
 * 
 * @param int $id ID do motorista
 * @return array Resultado da operação
 */
function deleteMotorist($id) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        
        // Verifica se motorista existe
        $check_sql = "SELECT COUNT(*) as count FROM motoristas WHERE id = :id AND empresa_id = :empresa_id";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $check_stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $check_stmt->execute();
        
        if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] == 0) {
            throw new Exception('Motorista não encontrado');
        }
        
        // Exclui o motorista
        $sql = "DELETE FROM motoristas WHERE id = :id AND empresa_id = :empresa_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Motorista excluído com sucesso'
        ];
        
    } catch(PDOException $e) {
        error_log("Erro em deleteMotorist: " . $e->getMessage());
        http_response_code(500);
        return [
            'success' => false,
            'error' => DEBUG_MODE ? $e->getMessage() : 'Erro ao excluir motorista'
        ];
    }
} 