<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Require authentication
require_authentication();

// Create database connection
$conn = getConnection();

// Set content type to JSON
header('Content-Type: application/json');

// Get the action from the request
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'list':
            $empresa_id = $_SESSION['empresa_id'];
            $data = $_GET['data'] ?? '';
            if (empty($data)) {
                // Retorna todos os veículos da empresa para uso geral (ex: despesas fixas)
                $sql = "SELECT id, placa, modelo FROM veiculos WHERE empresa_id = :empresa_id ORDER BY placa";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
                $stmt->execute();
                $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'veiculos' => $veiculos]);
                exit;
            }
            // --- MANTÉM O CÓDIGO EXISTENTE PARA QUANDO HÁ DATA ---
            // Buscar veículos que têm rotas na data selecionada para o motorista
            $sql = "SELECT DISTINCT v.* 
                   FROM veiculos v 
                   INNER JOIN rotas r ON v.id = r.veiculo_id 
                   WHERE r.empresa_id = :empresa_id 
                   AND r.motorista_id = :motorista_id 
                   AND r.data_rota = :data_rota 
                   AND r.status = 'pendente'
                   ORDER BY v.placa";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':empresa_id', $_SESSION['empresa_id']);
            $stmt->bindParam(':motorista_id', $_SESSION['motorista_id']);
            $stmt->bindParam(':data_rota', $data);
            $stmt->execute();
            $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'veiculos' => $veiculos
            ]);
            break;
            
        case 'get':
            // Get a specific vehicle by ID
            $id = isset($_GET['id']) ? $_GET['id'] : null;
            
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
            $stmt->execute([
                'id' => $id,
                'empresa_id' => $_SESSION['empresa_id']
            ]);
            
            $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$vehicle) {
                throw new Exception('Veículo não encontrado');
            }
            
            echo json_encode([
                'success' => true,
                'data' => $vehicle
            ]);
            break;
            
        case 'get_compatible_carretas':
            $cavalo_id = $_GET['cavalo_id'] ?? null;
            
            if (!$cavalo_id) {
                throw new Exception('ID do cavalo não fornecido');
            }
            
            // Busca as carretas compatíveis com o cavalo selecionado
            $sql = "SELECT tc.* 
                    FROM tipos_carretas tc
                    INNER JOIN cavalos_carretas cc ON tc.id = cc.id_carreta
                    WHERE cc.id_cavalo = :cavalo_id
                    ORDER BY tc.nome";
                    
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':cavalo_id', $cavalo_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $carretas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Retorna as carretas em formato JSON
            echo json_encode($carretas);
            exit;
            
        case 'save':
            $data = json_decode(file_get_contents('php://input'), true);
            $result = saveVehicle($data);
            echo json_encode($result);
            break;
            
        case 'list_vehicles':
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
            $result = listVehicles($page, $limit);
            echo json_encode($result);
            break;
            
        default:
            throw new Exception('Ação não suportada');
    }
} catch (Exception $e) {
    error_log("Error in veiculos.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Função para salvar veículo
function saveVehicle($data) {
    try {
        $conn = getConnection();
        
        // Prepara os dados
        $id = $data['id'] ?? null;
        $placa = $data['placa'];
        $modelo = $data['modelo'];
        $marca = $data['marca'] ?? null;
        $ano = $data['ano'];
        $cor = $data['cor'] ?? null;
        $status_id = $data['status_id'] ?? 1;
        $id_cavalo = $data['id_cavalo'] ?? null;
        $id_carreta = $data['id_carreta'] ?? null;
        $km_atual = $data['km_atual'] ?? 0;
        $tipo_combustivel_id = $data['tipo_combustivel_id'] ?? null;
        $chassi = $data['chassi'] ?? null;
        $renavam = $data['renavam'] ?? null;
        $capacidade_carga = $data['capacidade_carga'] ?? null;
        $capacidade_passageiros = $data['capacidade_passageiros'] ?? null;
        $numero_motor = $data['numero_motor'] ?? null;
        $proprietario = $data['proprietario'] ?? null;
        $potencia_motor = $data['potencia_motor'] ?? null;
        $numero_eixos = $data['numero_eixos'] ?? null;
        $carroceria_id = $data['carroceria_id'] ?? null;
        $observacoes = $data['observacoes'] ?? null;
        $empresa_id = $_SESSION['empresa_id'];
        
        if ($id) {
            // Atualização
            $sql = "UPDATE veiculos SET 
                    placa = :placa,
                    modelo = :modelo,
                    marca = :marca,
                    ano = :ano,
                    cor = :cor,
                    status_id = :status_id,
                    id_cavalo = :id_cavalo,
                    id_carreta = :id_carreta,
                    km_atual = :km_atual,
                    tipo_combustivel_id = :tipo_combustivel_id,
                    chassi = :chassi,
                    renavam = :renavam,
                    capacidade_carga = :capacidade_carga,
                    capacidade_passageiros = :capacidade_passageiros,
                    numero_motor = :numero_motor,
                    proprietario = :proprietario,
                    potencia_motor = :potencia_motor,
                    numero_eixos = :numero_eixos,
                    carroceria_id = :carroceria_id,
                    observacoes = :observacoes
                    WHERE id = :id AND empresa_id = :empresa_id";
        } else {
            // Inserção
            $sql = "INSERT INTO veiculos (
                    placa, modelo, marca, ano, cor, status_id, id_cavalo, id_carreta,
                    km_atual, tipo_combustivel_id, chassi, renavam, capacidade_carga,
                    capacidade_passageiros, numero_motor, proprietario, potencia_motor,
                    numero_eixos, carroceria_id, observacoes, empresa_id
                ) VALUES (
                    :placa, :modelo, :marca, :ano, :cor, :status_id, :id_cavalo, :id_carreta,
                    :km_atual, :tipo_combustivel_id, :chassi, :renavam, :capacidade_carga,
                    :capacidade_passageiros, :numero_motor, :proprietario, :potencia_motor,
                    :numero_eixos, :carroceria_id, :observacoes, :empresa_id
                )";
        }
        
        $stmt = $conn->prepare($sql);
        
        // Bind dos parâmetros
        $stmt->bindParam(':placa', $placa);
        $stmt->bindParam(':modelo', $modelo);
        $stmt->bindParam(':marca', $marca);
        $stmt->bindParam(':ano', $ano);
        $stmt->bindParam(':cor', $cor);
        $stmt->bindParam(':status_id', $status_id);
        $stmt->bindParam(':id_cavalo', $id_cavalo);
        $stmt->bindParam(':id_carreta', $id_carreta);
        $stmt->bindParam(':km_atual', $km_atual);
        $stmt->bindParam(':tipo_combustivel_id', $tipo_combustivel_id);
        $stmt->bindParam(':chassi', $chassi);
        $stmt->bindParam(':renavam', $renavam);
        $stmt->bindParam(':capacidade_carga', $capacidade_carga);
        $stmt->bindParam(':capacidade_passageiros', $capacidade_passageiros);
        $stmt->bindParam(':numero_motor', $numero_motor);
        $stmt->bindParam(':proprietario', $proprietario);
        $stmt->bindParam(':potencia_motor', $potencia_motor);
        $stmt->bindParam(':numero_eixos', $numero_eixos);
        $stmt->bindParam(':carroceria_id', $carroceria_id);
        $stmt->bindParam(':observacoes', $observacoes);
        $stmt->bindParam(':empresa_id', $empresa_id);
        
        if ($id) {
            $stmt->bindParam(':id', $id);
        }
        
        $stmt->execute();
        
        // Se for uma inserção, pega o ID do novo registro
        if (!$id) {
            $id = $conn->lastInsertId();
        }
        
        // Se houver troca de carreta, registra no histórico
        if ($id_carreta) {
            $sql = "INSERT INTO historico_troca_carreta (
                    veiculo_id, id_cavalo, id_carreta, data_troca
                ) VALUES (
                    :veiculo_id, :id_cavalo, :id_carreta, NOW()
                )";
                
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':veiculo_id', $id);
            $stmt->bindParam(':id_cavalo', $id_cavalo);
            $stmt->bindParam(':id_carreta', $id_carreta);
            $stmt->execute();
        }
        
        return [
            'success' => true,
            'message' => $id ? 'Veículo atualizado com sucesso' : 'Veículo cadastrado com sucesso',
            'id' => $id
        ];
        
    } catch (PDOException $e) {
        error_log("Erro ao salvar veículo: " . $e->getMessage());
        throw new Exception("Erro ao salvar veículo: " . $e->getMessage());
    }
}

// Função para listar veículos
function listVehicles($page = 1, $limit = 10) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        
        // Calcula o offset
        $offset = ($page - 1) * $limit;
        
        // Busca os veículos com os dados do cavalo e carreta
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
                WHERE v.empresa_id = :empresa_id 
                ORDER BY v.id DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Busca o total de veículos para paginação
        $sql = "SELECT COUNT(*) as total FROM veiculos WHERE empresa_id = :empresa_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return [
            'success' => true,
            'data' => $veiculos,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'current_page' => $page
        ];
        
    } catch (PDOException $e) {
        error_log("Erro ao listar veículos: " . $e->getMessage());
        throw new Exception("Erro ao listar veículos: " . $e->getMessage());
    }
} 