<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Start session
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Usuário não está autenticado',
        'redirect' => '../login.php'
    ]);
    exit;
}

// Handle POST requests
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
        case 'update':
            handleSaveVehicle();
            break;
            
        case 'delete':
            handleDeleteVehicle();
            break;
            
        case 'get':
            handleGetVehicle();
            break;
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Ação inválida'
            ]);
            exit;
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido'
    ]);
    exit;
}

function handleSaveVehicle() {
    try {
        $conn = getConnection();
        
        // Get form data
        $id = $_POST['id'] ?? null;
        $data = [
            'empresa_id' => $_SESSION['empresa_id'],
            'placa' => strtoupper($_POST['placa']),
            'modelo' => $_POST['modelo'],
            'marca' => $_POST['marca'] ?? null,
            'ano' => $_POST['ano'] ?? null,
            'cor' => $_POST['cor'] ?? null,
            'chassi' => $_POST['chassi'] ?? null,
            'renavam' => $_POST['renavam'] ?? null,
            'km_atual' => $_POST['km_atual'] ?? 0,
            'documento' => $_POST['documento'] ?? null,
            'observacoes' => $_POST['observacoes'] ?? null,
            'id_cavalo' => $_POST['id_cavalo'] ?: null,
            'id_carreta' => $_POST['id_carreta'] ?: null,
            'capacidade_carga' => $_POST['capacidade_carga'] ?: null,
            'capacidade_passageiros' => $_POST['capacidade_passageiros'] ?: null,
            'numero_motor' => $_POST['numero_motor'] ?? null,
            'proprietario' => $_POST['proprietario'] ?? null,
            'tipo_combustivel_id' => $_POST['tipo_combustivel_id'] ?: null,
            'potencia_motor' => $_POST['potencia_motor'] ?? null,
            'numero_eixos' => $_POST['numero_eixos'] ?: null,
            'carroceria_id' => $_POST['carroceria_id'] ?: null,
            'status_id' => $_POST['status_id'] ?: null
        ];

        // Handle file upload for foto_veiculo if present
        if (isset($_FILES['foto_veiculo']) && $_FILES['foto_veiculo']['error'] == 0) {
            $uploadDir = '../uploads/veiculos/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileExtension = pathinfo($_FILES['foto_veiculo']['name'], PATHINFO_EXTENSION);
            $newFileName = uniqid() . '.' . $fileExtension;
            $uploadFile = $uploadDir . $newFileName;
            
            if (move_uploaded_file($_FILES['foto_veiculo']['tmp_name'], $uploadFile)) {
                $data['foto_veiculo'] = 'uploads/veiculos/' . $newFileName;
            }
        }

        // Handle file upload for documento if present
        if (isset($_FILES['documento']) && $_FILES['documento']['error'] == 0) {
            $uploadDir = '../uploads/veiculos/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileExtension = pathinfo($_FILES['documento']['name'], PATHINFO_EXTENSION);
            $newFileName = uniqid() . '.' . $fileExtension;
            $uploadFile = $uploadDir . $newFileName;
            if (move_uploaded_file($_FILES['documento']['tmp_name'], $uploadFile)) {
                $data['documento'] = 'uploads/veiculos/' . $newFileName;
            }
        }
        
        if ($id) {
            // Update existing vehicle
            $sql = "UPDATE veiculos SET 
                    placa = :placa,
                    modelo = :modelo,
                    marca = :marca,
                    ano = :ano,
                    cor = :cor,
                    chassi = :chassi,
                    renavam = :renavam,
                    km_atual = :km_atual,
                    documento = :documento,
                    observacoes = :observacoes,
                    id_cavalo = :id_cavalo,
                    id_carreta = :id_carreta,
                    capacidade_carga = :capacidade_carga,
                    capacidade_passageiros = :capacidade_passageiros,
                    numero_motor = :numero_motor,
                    proprietario = :proprietario,
                    tipo_combustivel_id = :tipo_combustivel_id,
                    potencia_motor = :potencia_motor,
                    numero_eixos = :numero_eixos,
                    carroceria_id = :carroceria_id,
                    status_id = :status_id";
            
            if (isset($data['foto_veiculo'])) {
                $sql .= ", foto_veiculo = :foto_veiculo";
            }
            
            $sql .= " WHERE id = :id AND empresa_id = :empresa_id";
            $data['id'] = $id;
            
        } else {
            // Insert new vehicle
            $fields = ['empresa_id', 'placa', 'modelo', 'marca', 'ano', 'cor', 'chassi', 
                      'renavam', 'km_atual', 'documento', 'observacoes', 'id_cavalo', 
                      'id_carreta', 'capacidade_carga', 'capacidade_passageiros', 
                      'numero_motor', 'proprietario', 'tipo_combustivel_id', 
                      'potencia_motor', 'numero_eixos', 'carroceria_id', 'status_id'];
            
            if (isset($data['foto_veiculo'])) {
                $fields[] = 'foto_veiculo';
            }
            
            $sql = "INSERT INTO veiculos (" . implode(', ', $fields) . ") 
                    VALUES (:" . implode(', :', $fields) . ")";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($data);
        
        $response = [
            'success' => true,
            'message' => $id ? 'Veículo atualizado com sucesso!' : 'Veículo cadastrado com sucesso!',
            'id' => $id ?: $conn->lastInsertId()
        ];
        
    } catch(PDOException $e) {
        $response = [
            'success' => false,
            'message' => 'Erro ao salvar veículo: ' . $e->getMessage()
        ];
    }
    
    echo json_encode($response);
    exit;
}

function handleDeleteVehicle() {
    try {
        $conn = getConnection();
        $id = $_POST['id'] ?? null;
        
        if (!$id) {
            throw new Exception('ID do veículo não fornecido');
        }
        
        // First get the vehicle photo path if exists
        $stmt = $conn->prepare("SELECT foto_veiculo FROM veiculos WHERE id = :id AND empresa_id = :empresa_id");
        $stmt->execute(['id' => $id, 'empresa_id' => $_SESSION['empresa_id']]);
        $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete the vehicle
        $stmt = $conn->prepare("DELETE FROM veiculos WHERE id = :id AND empresa_id = :empresa_id");
        $stmt->execute(['id' => $id, 'empresa_id' => $_SESSION['empresa_id']]);
        
        // If vehicle had a photo, delete it
        if ($vehicle && $vehicle['foto_veiculo']) {
            $photoPath = '../' . $vehicle['foto_veiculo'];
            if (file_exists($photoPath)) {
                unlink($photoPath);
            }
        }
        
        $response = [
            'success' => true,
            'message' => 'Veículo excluído com sucesso!'
        ];
        
    } catch(Exception $e) {
        $response = [
            'success' => false,
            'message' => 'Erro ao excluir veículo: ' . $e->getMessage()
        ];
    }
    
    echo json_encode($response);
    exit;
}

function handleGetVehicle() {
    try {
        $conn = getConnection();
        $id = $_POST['id'] ?? null;
        
        if (!$id) {
            throw new Exception('ID do veículo não fornecido');
        }
        
        $sql = "SELECT v.*, 
                s.nome as status_nome,
                tc.nome as tipo_combustivel_nome,
                cv.nome as cavalo_nome,
                cv.eixos as cavalo_eixos,
                cv.tracao as cavalo_tracao,
                cr.nome as carreta_nome,
                cr.capacidade_media as carreta_capacidade,
                c.nome as carroceria_nome
                FROM veiculos v
                LEFT JOIN status_veiculos s ON v.status_id = s.id
                LEFT JOIN tipos_combustivel tc ON v.tipo_combustivel_id = tc.id
                LEFT JOIN tipos_cavalos cv ON v.id_cavalo = cv.id
                LEFT JOIN tipos_carretas cr ON v.id_carreta = cr.id
                LEFT JOIN carrocerias c ON v.carroceria_id = c.id
                WHERE v.id = :id AND v.empresa_id = :empresa_id";
                
        $stmt = $conn->prepare($sql);
        $stmt->execute(['id' => $id, 'empresa_id' => $_SESSION['empresa_id']]);
        $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$vehicle) {
            throw new Exception('Veículo não encontrado');
        }
        
        $response = [
            'success' => true,
            'data' => $vehicle
        ];
        
    } catch(Exception $e) {
        $response = [
            'success' => false,
            'message' => 'Erro ao buscar veículo: ' . $e->getMessage()
        ];
    }
    
    echo json_encode($response);
    exit;
} 