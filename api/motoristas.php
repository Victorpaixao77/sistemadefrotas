<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';

// Configure session
configure_session();
session_start();

// Check authentication
require_authentication();

$conn = getConnection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            createMotorist($conn);
            break;
        case 'get':
            getMotorist($conn);
            break;
        case 'update':
            updateMotorist($conn);
            break;
        case 'delete':
            deleteMotorist($conn);
            break;
        case 'list':
            listMotorists($conn);
            break;
        case 'get_all':
            getAllMotorists($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não especificada']);
            break;
    }
} catch (Exception $e) {
    error_log("Erro na API de motoristas: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

function createMotorist($conn) {
    $empresa_id = $_SESSION['empresa_id'];
    
    // Validate required fields
    $required_fields = ['nome', 'cpf', 'telefone', 'cnh', 'categoria_cnh_id', 'data_validade_cnh', 'disponibilidade_id'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => "Campo obrigatório não preenchido: $field"]);
            return;
        }
    }
    
    // Check if CPF already exists
    $stmt = $conn->prepare("SELECT id FROM motoristas WHERE cpf = :cpf AND empresa_id = :empresa_id");
    $stmt->bindParam(':cpf', $_POST['cpf']);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'CPF já cadastrado']);
        return;
    }
    
    // Check if CNH already exists
    $stmt = $conn->prepare("SELECT id FROM motoristas WHERE cnh = :cnh AND empresa_id = :empresa_id");
    $stmt->bindParam(':cnh', $_POST['cnh']);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'CNH já cadastrada']);
        return;
    }
    
    // Insert new motorist
    $sql = "INSERT INTO motoristas (
        empresa_id, nome, cpf, telefone, email, endereco, 
        cnh, categoria_cnh_id, data_validade_cnh, disponibilidade_id, observacoes, 
        data_cadastro
    ) VALUES (
        :empresa_id, :nome, :cpf, :telefone, :email, :endereco,
        :cnh, :categoria_cnh_id, :data_validade_cnh, :disponibilidade_id, :observacoes,
        NOW()
    )";
    
    $stmt = $conn->prepare($sql);
    
    $nome = $_POST['nome'];
    $cpf = $_POST['cpf'];
    $telefone = $_POST['telefone'];
    $email = $_POST['email'] ?? null;
    $endereco = $_POST['endereco'] ?? null;
    $cnh = $_POST['cnh'];
    $categoria_cnh_id = $_POST['categoria_cnh_id'];
    $data_validade_cnh = $_POST['data_validade_cnh'];
    $disponibilidade_id = $_POST['disponibilidade_id'];
    $observacoes = $_POST['observacoes'] ?? null;
    
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->bindParam(':nome', $nome);
    $stmt->bindParam(':cpf', $cpf);
    $stmt->bindParam(':telefone', $telefone);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':endereco', $endereco);
    $stmt->bindParam(':cnh', $cnh);
    $stmt->bindParam(':categoria_cnh_id', $categoria_cnh_id);
    $stmt->bindParam(':data_validade_cnh', $data_validade_cnh);
    $stmt->bindParam(':disponibilidade_id', $disponibilidade_id);
    $stmt->bindParam(':observacoes', $observacoes);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Motorista criado com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao criar motorista']);
    }
}

function getMotorist($conn) {
    $id = $_GET['id'] ?? null;
    $empresa_id = $_SESSION['empresa_id'];
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
        return;
    }
    
    $sql = "SELECT m.*, 
            d.nome as disponibilidade_nome,
            c.nome as categoria_cnh_nome
            FROM motoristas m
            LEFT JOIN disponibilidades d ON m.disponibilidade_id = d.id
            LEFT JOIN categorias_cnh c ON m.categoria_cnh_id = c.id
            WHERE m.id = :id AND m.empresa_id = :empresa_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    
    $motorist = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($motorist) {
        echo json_encode(['success' => true, 'data' => $motorist]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Motorista não encontrado']);
    }
}

function updateMotorist($conn) {
    $id = $_POST['id'] ?? null;
    $empresa_id = $_SESSION['empresa_id'];
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
        return;
    }
    
    // Validate required fields
    $required_fields = ['nome', 'cpf', 'telefone', 'cnh', 'categoria_cnh_id', 'data_validade_cnh', 'disponibilidade_id'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => "Campo obrigatório não preenchido: $field"]);
            return;
        }
    }
    
    // Check if CPF already exists for another motorist
    $stmt = $conn->prepare("SELECT id FROM motoristas WHERE cpf = :cpf AND empresa_id = :empresa_id AND id != :id");
    $stmt->bindParam(':cpf', $_POST['cpf']);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'CPF já cadastrado para outro motorista']);
        return;
    }
    
    // Check if CNH already exists for another motorist
    $stmt = $conn->prepare("SELECT id FROM motoristas WHERE cnh = :cnh AND empresa_id = :empresa_id AND id != :id");
    $stmt->bindParam(':cnh', $_POST['cnh']);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'CNH já cadastrada para outro motorista']);
        return;
    }
    
    // Update motorist
    $sql = "UPDATE motoristas SET 
            nome = :nome, cpf = :cpf, 
            telefone = :telefone, email = :email, endereco = :endereco,
            cnh = :cnh, categoria_cnh_id = :categoria_cnh_id, 
            data_validade_cnh = :data_validade_cnh, disponibilidade_id = :disponibilidade_id, 
            observacoes = :observacoes
            WHERE id = :id AND empresa_id = :empresa_id";
    
    $stmt = $conn->prepare($sql);
    
    $nome = $_POST['nome'];
    $cpf = $_POST['cpf'];
    $telefone = $_POST['telefone'];
    $email = $_POST['email'] ?? null;
    $endereco = $_POST['endereco'] ?? null;
    $cnh = $_POST['cnh'];
    $categoria_cnh_id = $_POST['categoria_cnh_id'];
    $data_validade_cnh = $_POST['data_validade_cnh'];
    $disponibilidade_id = $_POST['disponibilidade_id'];
    $observacoes = $_POST['observacoes'] ?? null;
    
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->bindParam(':nome', $nome);
    $stmt->bindParam(':cpf', $cpf);
    $stmt->bindParam(':telefone', $telefone);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':endereco', $endereco);
    $stmt->bindParam(':cnh', $cnh);
    $stmt->bindParam(':categoria_cnh_id', $categoria_cnh_id);
    $stmt->bindParam(':data_validade_cnh', $data_validade_cnh);
    $stmt->bindParam(':disponibilidade_id', $disponibilidade_id);
    $stmt->bindParam(':observacoes', $observacoes);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Motorista atualizado com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar motorista']);
    }
}

function deleteMotorist($conn) {
    $id = $_POST['id'] ?? null;
    $empresa_id = $_SESSION['empresa_id'];
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID não fornecido']);
        return;
    }
    
    // Check if motorist exists and belongs to the company
    $stmt = $conn->prepare("SELECT id FROM motoristas WHERE id = :id AND empresa_id = :empresa_id");
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Motorista não encontrado']);
        return;
    }
    
    // Check if motorist is associated with any trips or other records
    // You might want to add additional checks here based on your business logic
    
    // Delete motorist
    $stmt = $conn->prepare("DELETE FROM motoristas WHERE id = :id AND empresa_id = :empresa_id");
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':empresa_id', $empresa_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Motorista excluído com sucesso']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir motorista']);
    }
}

function listMotorists($conn) {
    $empresa_id = $_SESSION['empresa_id'];
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 10;
    $offset = ($page - 1) * $limit;
    
    // Get total count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM motoristas WHERE empresa_id = :empresa_id");
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get motorists with pagination
    $sql = "SELECT m.*, 
            d.nome as disponibilidade_nome,
            c.nome as categoria_cnh_nome
            FROM motoristas m
            LEFT JOIN disponibilidades d ON m.disponibilidade_id = d.id
            LEFT JOIN categorias_cnh c ON m.categoria_cnh_id = c.id
            WHERE m.empresa_id = :empresa_id
            ORDER BY m.nome ASC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $motorists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $motorists,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit)
    ]);
}

function getAllMotorists($conn) {
    $empresa_id = $_SESSION['empresa_id'];
    
    // Get all motorists without pagination
    $sql = "SELECT m.id, m.nome, m.cpf, m.telefone, m.email, m.cnh,
            d.nome as disponibilidade_nome,
            c.nome as categoria_cnh_nome
            FROM motoristas m
            LEFT JOIN disponibilidades d ON m.disponibilidade_id = d.id
            LEFT JOIN categorias_cnh c ON m.categoria_cnh_id = c.id
            WHERE m.empresa_id = :empresa_id
            ORDER BY m.nome ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    
    $motorists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $motorists,
        'total' => count($motorists)
    ]);
}
?> 
