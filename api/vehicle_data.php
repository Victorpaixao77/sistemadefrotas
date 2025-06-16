<?php
// Vehicle data API

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

// Ensure the request is authenticated
require_authentication();

// Check for specific vehicle ID
$vehicleId = isset($_GET['id']) ? $_GET['id'] : null;

// Check for action parameter
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Handle different actions
switch ($action) {
    case 'view':
        if ($vehicleId) {
            echo json_encode(getVehicleData($vehicleId));
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Vehicle ID is required']);
        }
        break;
        
    case 'add':
        // Process add vehicle request (POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            echo json_encode(addVehicle($data));
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
        
    case 'update':
        // Process update vehicle request (POST/PUT)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
            $data = json_decode(file_get_contents('php://input'), true);
            if ($vehicleId) {
                echo json_encode(updateVehicle($vehicleId, $data));
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Vehicle ID is required']);
            }
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
        
    case 'delete':
        // Process delete vehicle request (DELETE)
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || 
            ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['_method']) && $_GET['_method'] === 'DELETE')) {
            if ($vehicleId) {
                echo json_encode(deleteVehicle($vehicleId));
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Vehicle ID is required']);
            }
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;
        
    case 'stats':
        // Return vehicle statistics
        echo json_encode(getVehicleStats());
        break;
        
    case 'maintenance':
        // Return maintenance history for a vehicle
        if ($vehicleId) {
            echo json_encode(getVehicleMaintenanceHistory($vehicleId));
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Vehicle ID is required']);
        }
        break;
        
    case 'list':
    default:
        // Return list of vehicles
        echo json_encode(getVehiclesList());
        break;
}

/**
 * Get list of vehicles with optional filtering
 * 
 * @return array List of vehicles and pagination info
 */
function getVehiclesList() {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        
        // Get pagination parameters
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
        $offset = ($page - 1) * $limit;
        
        // Get total count
        $sql_count = "SELECT COUNT(*) as total FROM veiculos WHERE empresa_id = :empresa_id";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_count->execute();
        $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get vehicles with pagination
        $sql = "SELECT v.*, 
                s.nome as status_nome, 
                tc.nome as tipo_combustivel_nome,
                cv.nome as cavalo_nome,
                cv.eixos as cavalo_eixos,
                cv.tracao as cavalo_tracao,
                cr.nome as carreta_nome,
                cr.capacidade_media as carreta_capacidade
                FROM veiculos v 
                LEFT JOIN status_veiculos s ON v.status_id = s.id 
                LEFT JOIN tipos_combustivel tc ON v.tipo_combustivel_id = tc.id 
                LEFT JOIN tipos_cavalos cv ON v.id_cavalo = cv.id
                LEFT JOIN tipos_carretas cr ON v.id_carreta = cr.id
                WHERE v.empresa_id = :empresa_id
                ORDER BY v.id DESC
                LIMIT :limit OFFSET :offset";
                
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'data' => $vehicles,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => ceil($total / $limit)
            ]
        ];
        
    } catch(PDOException $e) {
        error_log("Error in getVehiclesList: " . $e->getMessage());
        http_response_code(500);
        return ['error' => 'Database error occurred'];
    }
}

/**
 * Get vehicle details by ID
 * 
 * @param string $id Vehicle ID
 * @return array Vehicle details or error message
 */
function getVehicleData($id) {
    try {
        $conn = getConnection();
        
        // Primeiro busca os dados básicos do veículo
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
        $stmt->execute([
            'id' => $id,
            'empresa_id' => $_SESSION['empresa_id']
        ]);
        
        $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$vehicle) {
            throw new Exception('Veículo não encontrado');
        }

        // Inicializa os arrays de histórico
        $historico_carretas = [];
        $historico_km = [];

        // Verifica se a tabela de histórico de carretas existe antes de tentar buscar os dados
        $check_table = $conn->query("SHOW TABLES LIKE 'historico_troca_carreta'");
        if ($check_table->rowCount() > 0) {
            // Busca o histórico de troca de carretas
            $sql_historico = "SELECT h.*, 
                             tc.nome as carreta_nome,
                             tc.capacidade_media as carreta_capacidade
                             FROM historico_troca_carreta h
                             LEFT JOIN tipos_carretas tc ON h.id_carreta = tc.id
                             WHERE h.veiculo_id = :veiculo_id
                             ORDER BY h.data_troca DESC";
            
            $stmt_historico = $conn->prepare($sql_historico);
            $stmt_historico->execute(['veiculo_id' => $id]);
            $historico_carretas = $stmt_historico->fetchAll(PDO::FETCH_ASSOC);
        }

        // Verifica se a tabela de histórico de quilometragem existe antes de tentar buscar os dados
        $check_table = $conn->query("SHOW TABLES LIKE 'historico_quilometragem'");
        if ($check_table->rowCount() > 0) {
            // Busca o histórico de quilometragem
            $sql_km = "SELECT * FROM historico_quilometragem 
                      WHERE veiculo_id = :veiculo_id 
                      ORDER BY data_registro DESC";
            
            $stmt_km = $conn->prepare($sql_km);
            $stmt_km->execute(['veiculo_id' => $id]);
            $historico_km = $stmt_km->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return [
            'success' => true,
            'data' => [
                'veiculo' => $vehicle,
                'historico_carretas' => $historico_carretas,
                'historico_km' => $historico_km
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Erro ao buscar dados do veículo: " . $e->getMessage());
        throw new Exception($e->getMessage());
    }
}

/**
 * Get vehicle maintenance history
 * 
 * @param string $id Vehicle ID
 * @return array Maintenance history
 */
function getVehicleMaintenanceHistory($id) {
    // In production, would fetch from database
    $maintenanceHistory = [
        '1' => [
            [
                'id' => '1',
                'date' => '2025-04-28',
                'type' => 'Preventiva',
                'description' => 'Troca de óleo e filtros',
                'mileage' => '45890',
                'cost' => '580.00',
                'mechanic' => 'Oficina ABC',
                'notes' => 'Serviço realizado conforme programado'
            ],
            [
                'id' => '2',
                'date' => '2025-03-15',
                'type' => 'Corretiva',
                'description' => 'Substituição de pastilhas de freio',
                'mileage' => '42350',
                'cost' => '420.00',
                'mechanic' => 'Oficina ABC',
                'notes' => 'Desgaste acima do normal'
            ],
            [
                'id' => '3',
                'date' => '2025-01-15',
                'type' => 'Preventiva',
                'description' => 'Revisão completa: óleo, filtros, fluidos, suspensão',
                'mileage' => '35840',
                'cost' => '1250.00',
                'mechanic' => 'Concessionária Mercedes',
                'notes' => 'Revisão oficial da montadora'
            ]
        ],
        '2' => [
            [
                'id' => '1',
                'date' => '2025-04-15',
                'type' => 'Preventiva',
                'description' => 'Troca de óleo e filtros',
                'mileage' => '32150',
                'cost' => '540.00',
                'mechanic' => 'Oficina ABC',
                'notes' => 'Serviço realizado conforme programado'
            ],
            [
                'id' => '2',
                'date' => '2025-03-10',
                'type' => 'Preventiva',
                'description' => 'Revisão completa: óleo, filtros, fluidos, suspensão',
                'mileage' => '28900',
                'cost' => '1350.00',
                'mechanic' => 'Concessionária Volvo',
                'notes' => 'Revisão oficial da montadora'
            ]
        ]
    ];
    
    // Check if vehicle exists
    if (isset($maintenanceHistory[$id])) {
        // Get only the last 5 records
        $records = array_slice($maintenanceHistory[$id], 0, 5);
        
        return [
            'vehicleId' => $id,
            'maintenanceRecords' => $records,
            'summary' => [
                'totalRecords' => count($records),
                'totalCost' => array_sum(array_column($records, 'cost')),
                'preventiveCount' => count(array_filter($records, function($item) {
                    return $item['type'] === 'Preventiva';
                })),
                'correctiveCount' => count(array_filter($records, function($item) {
                    return $item['type'] === 'Corretiva';
                }))
            ]
        ];
    } else {
        http_response_code(404);
        return ['error' => 'Vehicle not found or no maintenance records available'];
    }
}

/**
 * Get vehicle statistics
 * 
 * @return array Vehicle statistics
 */
function getVehicleStats() {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        
        // Get total vehicles
        $sql_total = "SELECT COUNT(*) as total FROM veiculos WHERE empresa_id = :empresa_id";
        $stmt_total = $conn->prepare($sql_total);
        $stmt_total->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_total->execute();
        $total = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get status distribution
        $sql_status = "SELECT 
            s.nome as status_nome,
            COUNT(*) as count 
            FROM veiculos v
            LEFT JOIN status_veiculos s ON v.status_id = s.id
            WHERE v.empresa_id = :empresa_id 
            GROUP BY v.status_id, s.nome";
        
        $stmt_status = $conn->prepare($sql_status);
        $stmt_status->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_status->execute();
        
        $status_dist = [];
        while ($row = $stmt_status->fetch(PDO::FETCH_ASSOC)) {
            $status_dist[$row['status_nome']] = (int)$row['count'];
        }
        
        // Get total mileage
        $sql_mileage = "SELECT COALESCE(SUM(km_atual), 0) as total FROM veiculos WHERE empresa_id = :empresa_id";
        $stmt_mileage = $conn->prepare($sql_mileage);
        $stmt_mileage->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_mileage->execute();
        $total_mileage = (float)$stmt_mileage->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Debug log
        error_log("Stats data: " . json_encode([
            'totalVehicles' => $total,
            'statusDistribution' => $status_dist,
            'totalMileage' => $total_mileage
        ]));
        
        return [
            'totalVehicles' => (int)$total,
            'statusDistribution' => $status_dist,
            'totalMileage' => $total_mileage
        ];
        
    } catch(PDOException $e) {
        error_log("Error in getVehicleStats: " . $e->getMessage());
        error_log("SQL State: " . $e->errorInfo[0]);
        error_log("Error Code: " . $e->errorInfo[1]);
        error_log("Message: " . $e->errorInfo[2]);
        http_response_code(500);
        return ['error' => 'Database error occurred'];
    } catch(Exception $e) {
        error_log("General error in getVehicleStats: " . $e->getMessage());
        http_response_code(500);
        return ['error' => 'An error occurred while getting vehicle statistics'];
    }
}

/**
 * Add a new vehicle
 * 
 * @param array $data Vehicle data
 * @return array Operation result
 */
function addVehicle($data) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        
        // Validate required fields
        $requiredFields = ['plate', 'model', 'year'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                http_response_code(400);
                return ['error' => "Field '$field' is required"];
            }
        }
        
        // Handle file uploads
        $documento_path = null;
        if (isset($_FILES['documento']) && $_FILES['documento']['error'] === UPLOAD_ERR_OK) {
            $documento_path = handleFileUpload($_FILES['documento'], 'documentos');
        }
        
        $foto_path = null;
        if (isset($_FILES['foto_veiculo']) && $_FILES['foto_veiculo']['error'] === UPLOAD_ERR_OK) {
            $foto_path = handleFileUpload($_FILES['foto_veiculo'], 'fotos');
        }
        
        // Prepare SQL
        $sql = "INSERT INTO veiculos (
                    empresa_id, placa, modelo, marca, ano, cor,
                    status_id, km_atual, tipo_combustivel_id, chassi,
                    renavam, id_cavalo, id_carreta, capacidade_carga, capacidade_passageiros,
                    numero_motor, proprietario, foto_veiculo, potencia_motor,
                    numero_eixos, carroceria_id, documento,
                    observacoes
                ) VALUES (
                    :empresa_id, :placa, :modelo, :marca, :ano, :cor,
                    :status_id, :km_atual, :tipo_combustivel_id, :chassi,
                    :renavam, :id_cavalo, :id_carreta, :capacidade_carga, :capacidade_passageiros,
                    :numero_motor, :proprietario, :foto_veiculo, :potencia_motor,
                    :numero_eixos, :carroceria_id, :documento,
                    :observacoes
                )";
                
        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':placa', $data['plate']);
        $stmt->bindParam(':modelo', $data['model']);
        $stmt->bindParam(':marca', $data['marca']);
        $stmt->bindParam(':ano', $data['year'], PDO::PARAM_INT);
        $stmt->bindParam(':cor', $data['cor']);
        $stmt->bindParam(':status_id', $data['status'], PDO::PARAM_INT);
        $stmt->bindParam(':km_atual', $data['mileage']);
        $stmt->bindParam(':tipo_combustivel_id', $data['tipo_combustivel'], PDO::PARAM_INT);
        $stmt->bindParam(':chassi', $data['chassi']);
        $stmt->bindParam(':renavam', $data['renavam']);
        $stmt->bindParam(':id_cavalo', $data['id_cavalo'], PDO::PARAM_INT);
        $stmt->bindParam(':id_carreta', $data['id_carreta'], PDO::PARAM_INT);
        $stmt->bindParam(':capacidade_carga', $data['capacidade_carga']);
        $stmt->bindParam(':capacidade_passageiros', $data['capacidade_passageiros'], PDO::PARAM_INT);
        $stmt->bindParam(':numero_motor', $data['numero_motor']);
        $stmt->bindParam(':proprietario', $data['proprietario']);
        $stmt->bindParam(':foto_veiculo', $foto_path);
        $stmt->bindParam(':potencia_motor', $data['potencia_motor']);
        $stmt->bindParam(':numero_eixos', $data['numero_eixos'], PDO::PARAM_INT);
        $stmt->bindParam(':carroceria_id', $data['carroceria_id'], PDO::PARAM_INT);
        $stmt->bindParam(':documento', $documento_path);
        $stmt->bindParam(':observacoes', $data['observacoes']);
        
        $stmt->execute();
        $id = $conn->lastInsertId();
        
        return [
            'success' => true,
            'message' => 'Vehicle added successfully',
            'id' => $id
        ];
        
    } catch(PDOException $e) {
        error_log("Error in addVehicle: " . $e->getMessage());
        http_response_code(500);
        return ['error' => 'Database error occurred'];
    }
}

/**
 * Update a vehicle
 * 
 * @param string $id Vehicle ID
 * @param array $data Updated vehicle data
 * @return array Operation result
 */
function updateVehicle($id, $data) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        
        // Check if vehicle exists and belongs to the company
        $check_sql = "SELECT id FROM veiculos WHERE id = :id AND empresa_id = :empresa_id";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $check_stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $check_stmt->execute();
        
        if (!$check_stmt->fetch()) {
            http_response_code(404);
            return ['error' => 'Vehicle not found'];
        }
        
        // Handle file uploads
        $documento_path = null;
        if (isset($_FILES['documento']) && $_FILES['documento']['error'] === UPLOAD_ERR_OK) {
            $documento_path = handleFileUpload($_FILES['documento'], 'documentos');
        }
        
        $foto_path = null;
        if (isset($_FILES['foto_veiculo']) && $_FILES['foto_veiculo']['error'] === UPLOAD_ERR_OK) {
            $foto_path = handleFileUpload($_FILES['foto_veiculo'], 'fotos');
        }
        
        // Prepare SQL
        $sql = "UPDATE veiculos SET 
                    placa = :placa,
                    modelo = :modelo,
                    marca = :marca,
                    ano = :ano,
                    cor = :cor,
                    status_id = :status_id,
                    km_atual = :km_atual,
                    tipo_combustivel_id = :tipo_combustivel_id,
                    chassi = :chassi,
                    renavam = :renavam,
                    id_cavalo = :id_cavalo,
                    id_carreta = :id_carreta,
                    capacidade_carga = :capacidade_carga,
                    capacidade_passageiros = :capacidade_passageiros,
                    numero_motor = :numero_motor,
                    proprietario = :proprietario,
                    potencia_motor = :potencia_motor,
                    numero_eixos = :numero_eixos,
                    carroceria_id = :carroceria_id,
                    observacoes = :observacoes";
        
        // Add file fields only if new files were uploaded
        if ($documento_path) {
            $sql .= ", documento = :documento";
        }
        if ($foto_path) {
            $sql .= ", foto_veiculo = :foto_veiculo";
        }
        
        $sql .= " WHERE id = :id AND empresa_id = :empresa_id";
                
        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':placa', $data['plate']);
        $stmt->bindParam(':modelo', $data['model']);
        $stmt->bindParam(':marca', $data['marca']);
        $stmt->bindParam(':ano', $data['year'], PDO::PARAM_INT);
        $stmt->bindParam(':cor', $data['cor']);
        $stmt->bindParam(':status_id', $data['status'], PDO::PARAM_INT);
        $stmt->bindParam(':km_atual', $data['mileage']);
        $stmt->bindParam(':tipo_combustivel_id', $data['tipo_combustivel'], PDO::PARAM_INT);
        $stmt->bindParam(':chassi', $data['chassi']);
        $stmt->bindParam(':renavam', $data['renavam']);
        $stmt->bindParam(':id_cavalo', $data['id_cavalo'], PDO::PARAM_INT);
        $stmt->bindParam(':id_carreta', $data['id_carreta'], PDO::PARAM_INT);
        $stmt->bindParam(':capacidade_carga', $data['capacidade_carga']);
        $stmt->bindParam(':capacidade_passageiros', $data['capacidade_passageiros'], PDO::PARAM_INT);
        $stmt->bindParam(':numero_motor', $data['numero_motor']);
        $stmt->bindParam(':proprietario', $data['proprietario']);
        $stmt->bindParam(':potencia_motor', $data['potencia_motor']);
        $stmt->bindParam(':numero_eixos', $data['numero_eixos'], PDO::PARAM_INT);
        $stmt->bindParam(':carroceria_id', $data['carroceria_id'], PDO::PARAM_INT);
        $stmt->bindParam(':observacoes', $data['observacoes']);
        
        // Bind file parameters if new files were uploaded
        if ($documento_path) {
            $stmt->bindParam(':documento', $documento_path);
        }
        if ($foto_path) {
            $stmt->bindParam(':foto_veiculo', $foto_path);
        }
        
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Vehicle updated successfully',
            'id' => $id
        ];
        
    } catch(PDOException $e) {
        error_log("Error in updateVehicle: " . $e->getMessage());
        http_response_code(500);
        return ['error' => 'Database error occurred'];
    }
}

/**
 * Handle file upload
 * 
 * @param array $file The uploaded file data
 * @param string $directory The target directory
 * @return string|null The file path if successful, null otherwise
 */
function handleFileUpload($file, $directory) {
    try {
        // Create directory if it doesn't exist
        $upload_dir = __DIR__ . "/../uploads/$directory/";
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return "uploads/$directory/$filename";
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error uploading file: " . $e->getMessage());
        return null;
    }
}

/**
 * Delete a vehicle
 * 
 * @param string $id Vehicle ID
 * @return array Operation result
 */
function deleteVehicle($id) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        
        // Check if vehicle exists and belongs to the company
        $check_sql = "SELECT id FROM veiculos WHERE id = :id AND empresa_id = :empresa_id";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $check_stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $check_stmt->execute();
        
        if (!$check_stmt->fetch()) {
            http_response_code(404);
            return ['error' => 'Vehicle not found'];
        }
        
        // Delete vehicle
        $sql = "DELETE FROM veiculos WHERE id = :id AND empresa_id = :empresa_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Vehicle deleted successfully',
            'id' => $id
        ];
        
    } catch(PDOException $e) {
        error_log("Error in deleteVehicle: " . $e->getMessage());
        http_response_code(500);
        return ['error' => 'Database error occurred'];
    }
}
