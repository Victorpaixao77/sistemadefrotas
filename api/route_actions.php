<?php
// API de ações das rotas

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
    switch ($action) {
        case 'add':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            echo json_encode(addRoute($data));
            break;
            
        case 'update':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }
            
            $id = isset($_GET['id']) ? $_GET['id'] : null;
            if (!$id) {
                throw new Exception('ID da rota não fornecido');
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            echo json_encode(updateRoute($id, $data));
            break;
            
        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método não permitido');
            }
            
            $id = isset($_GET['id']) ? $_GET['id'] : null;
            if (!$id) {
                throw new Exception('ID da rota não fornecido');
            }
            
            echo json_encode(deleteRoute($id));
            break;
            
        case 'get_motoristas':
            echo json_encode(getMotoristas());
            break;
            
        case 'get_veiculos':
            echo json_encode(getVeiculos());
            break;
            
        case 'get_clientes':
            echo json_encode(getClientes());
            break;
            
        case 'get_estados':
            echo json_encode(getEstados());
            break;
            
        case 'get_cidades':
            $uf = isset($_GET['uf']) ? $_GET['uf'] : null;
            if (!$uf) {
                throw new Exception('UF não fornecida');
            }
            echo json_encode(getCidades($uf));
            break;
            
        case 'get_motorista_comissao':
            $id = isset($_GET['id']) ? $_GET['id'] : null;
            if (!$id) {
                throw new Exception('ID do motorista não fornecido');
            }
            echo json_encode(getMotoristaComissao($id));
            break;
            
        case 'save_expenses':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                echo json_encode(['success' => false, 'error' => 'Dados não fornecidos']);
                exit;
            }
            
            try {
                $conn = getConnection();
                $empresa_id = $_SESSION['empresa_id'];

                // Check if expenses already exist for this route
                $sql = "SELECT id FROM despesas_viagem WHERE rota_id = :rota_id";
                $checkStmt = $conn->prepare($sql);
                $checkStmt->bindParam(':rota_id', $data['rota_id'], PDO::PARAM_INT);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    // Update existing expenses
                    $sql = "UPDATE despesas_viagem SET 
                        descarga = :descarga, 
                        pedagios = :pedagios, 
                        caixinha = :caixinha, 
                        estacionamento = :estacionamento, 
                        lavagem = :lavagem, 
                        borracharia = :borracharia, 
                        eletrica_mecanica = :eletrica_mecanica, 
                        adiantamento = :adiantamento
                        WHERE rota_id = :rota_id";
                        
                    $stmt = $conn->prepare($sql);
                } else {
                    // Insert new expenses
                    $sql = "INSERT INTO despesas_viagem (
                        rota_id, empresa_id, descarga, pedagios, caixinha, 
                        estacionamento, lavagem, borracharia, 
                        eletrica_mecanica, adiantamento
                    ) VALUES (
                        :rota_id, :empresa_id, :descarga, :pedagios, :caixinha, 
                        :estacionamento, :lavagem, :borracharia, 
                        :eletrica_mecanica, :adiantamento
                    )";
                        
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
                }
                
                // Bind parameters for both INSERT and UPDATE
                $stmt->bindParam(':rota_id', $data['rota_id'], PDO::PARAM_INT);
                $stmt->bindParam(':descarga', $data['descarga'], PDO::PARAM_STR);
                $stmt->bindParam(':pedagios', $data['pedagios'], PDO::PARAM_STR);
                $stmt->bindParam(':caixinha', $data['caixinha'], PDO::PARAM_STR);
                $stmt->bindParam(':estacionamento', $data['estacionamento'], PDO::PARAM_STR);
                $stmt->bindParam(':lavagem', $data['lavagem'], PDO::PARAM_STR);
                $stmt->bindParam(':borracharia', $data['borracharia'], PDO::PARAM_STR);
                $stmt->bindParam(':eletrica_mecanica', $data['eletrica_mecanica'], PDO::PARAM_STR);
                $stmt->bindParam(':adiantamento', $data['adiantamento'], PDO::PARAM_STR);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true]);
                } else {
                    throw new Exception($stmt->errorInfo()[2]);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Erro ao salvar despesas: ' . $e->getMessage()]);
            }
            break;
            
        case 'delete_expenses':
            $rota_id = isset($_POST['rota_id']) ? intval($_POST['rota_id']) : 0;
            if ($rota_id > 0) {
                try {
                    $conn = getConnection();
                    $sql = "DELETE FROM despesas_viagem WHERE rota_id = :rota_id AND empresa_id = :empresa_id";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':rota_id', $rota_id, PDO::PARAM_INT);
                    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
                    $stmt->execute();
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => 'Erro ao excluir despesas: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'ID da rota inválido']);
            }
            exit;
            
        default:
            throw new Exception('Ação inválida');
    }
} catch (Exception $e) {
    error_log("Erro na API de rotas: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => DEBUG_MODE ? $e->getMessage() : 'Erro ao processar a requisição'
    ]);
}

/**
 * Adiciona uma nova rota
 */
function addRoute($data) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        
        // Log para debug
        error_log("Dados recebidos: " . print_r($data, true));
        
        // Valida campos obrigatórios
        if (empty($data['motorista_id'])) {
            throw new Exception('Motorista é obrigatório');
        }
        if (empty($data['veiculo_id'])) {
            throw new Exception('Veículo é obrigatório');
        }
        if (empty($data['estado_origem'])) {
            throw new Exception('Estado de origem é obrigatório');
        }
        if (empty($data['cidade_origem_id'])) {
            throw new Exception('Cidade de origem é obrigatória');
        }
        if (empty($data['estado_destino'])) {
            throw new Exception('Estado de destino é obrigatório');
        }
        if (empty($data['cidade_destino_id'])) {
            throw new Exception('Cidade de destino é obrigatória');
        }
        if (empty($data['data_saida'])) {
            throw new Exception('Data de saída é obrigatória');
        }
        
        // Prepara SQL
        $sql = "INSERT INTO rotas (
            empresa_id, veiculo_id, motorista_id, 
            estado_origem, cidade_origem_id, estado_destino, cidade_destino_id,
            data_saida, data_chegada, km_saida, km_chegada, distancia_km,
            observacoes, data_rota, no_prazo, frete, comissao,
            km_vazio, total_km, percentual_vazio, eficiencia_viagem,
            peso_carga, descricao_carga, status, fonte
        ) VALUES (
            :empresa_id, :veiculo_id, :motorista_id,
            :estado_origem, :cidade_origem_id, :estado_destino, :cidade_destino_id,
            :data_saida, :data_chegada, :km_saida, :km_chegada, :distancia_km,
            :observacoes, :data_rota, :no_prazo, :frete, :comissao,
            :km_vazio, :total_km, :percentual_vazio, :eficiencia_viagem,
            :peso_carga, :descricao_carga, :status, :fonte
        )";
        
        $stmt = $conn->prepare($sql);
        
        // Vincula parâmetros
        $params = [
            ':empresa_id' => $empresa_id,
            ':veiculo_id' => $data['veiculo_id'],
            ':motorista_id' => $data['motorista_id'],
            ':estado_origem' => $data['estado_origem'],
            ':cidade_origem_id' => $data['cidade_origem_id'],
            ':estado_destino' => $data['estado_destino'],
            ':cidade_destino_id' => $data['cidade_destino_id'],
            ':data_saida' => $data['data_saida'],
            ':data_chegada' => $data['data_chegada'] ?? null,
            ':km_saida' => $data['km_saida'] ?? null,
            ':km_chegada' => $data['km_chegada'] ?? null,
            ':distancia_km' => $data['distancia_km'] ?? null,
            ':observacoes' => $data['observacoes'] ?? null,
            ':data_rota' => $data['data_rota'] ?? null,
            ':no_prazo' => $data['no_prazo'] ?? 0,
            ':frete' => $data['frete'] ?? null,
            ':comissao' => $data['comissao'] ?? null,
            ':km_vazio' => $data['km_vazio'] ?? null,
            ':total_km' => $data['total_km'] ?? null,
            ':percentual_vazio' => $data['percentual_vazio'] ?? null,
            ':eficiencia_viagem' => $data['eficiencia_viagem'] ?? null,
            ':peso_carga' => $data['peso_carga'] ?? null,
            ':descricao_carga' => $data['descricao_carga'] ?? null,
            ':status' => 'aprovado',
            ':fonte' => 'gestor'
        ];
        
        // Log para debug
        error_log("SQL: " . $sql);
        error_log("Parâmetros: " . print_r($params, true));
        
        // Executa a query
        foreach ($params as $key => &$val) {
            $stmt->bindValue($key, $val);
        }
        
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Rota adicionada com sucesso',
            'id' => $conn->lastInsertId()
        ];
        
    } catch(PDOException $e) {
        error_log("Erro em addRoute: " . $e->getMessage());
        throw new Exception('Erro ao adicionar rota: ' . $e->getMessage());
    }
}

/**
 * Atualiza uma rota existente
 */
function updateRoute($id, $data) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        
        // Log para debug
        error_log("Dados recebidos para atualização: " . print_r($data, true));
        
        // Valida campos obrigatórios
        if (empty($data['motorista_id'])) {
            throw new Exception('Motorista é obrigatório');
        }
        if (empty($data['veiculo_id'])) {
            throw new Exception('Veículo é obrigatório');
        }
        if (empty($data['estado_origem'])) {
            throw new Exception('Estado de origem é obrigatório');
        }
        if (empty($data['cidade_origem_id'])) {
            throw new Exception('Cidade de origem é obrigatória');
        }
        if (empty($data['estado_destino'])) {
            throw new Exception('Estado de destino é obrigatório');
        }
        if (empty($data['cidade_destino_id'])) {
            throw new Exception('Cidade de destino é obrigatória');
        }
        if (empty($data['data_saida'])) {
            throw new Exception('Data de saída é obrigatória');
        }
        
        // Prepara SQL
        $sql = "UPDATE rotas SET 
            veiculo_id = :veiculo_id,
            motorista_id = :motorista_id,
            estado_origem = :estado_origem,
            cidade_origem_id = :cidade_origem_id,
            estado_destino = :estado_destino,
            cidade_destino_id = :cidade_destino_id,
            data_saida = :data_saida,
            data_chegada = :data_chegada,
            km_saida = :km_saida,
            km_chegada = :km_chegada,
            distancia_km = :distancia_km,
            observacoes = :observacoes,
            data_rota = :data_rota,
            no_prazo = :no_prazo,
            frete = :frete,
            comissao = :comissao,
            km_vazio = :km_vazio,
            total_km = :total_km,
            percentual_vazio = :percentual_vazio,
            eficiencia_viagem = :eficiencia_viagem,
            peso_carga = :peso_carga,
            descricao_carga = :descricao_carga
            WHERE id = :id AND empresa_id = :empresa_id";
        
        $stmt = $conn->prepare($sql);
        
        // Vincula parâmetros
        $params = [
            ':id' => $id,
            ':empresa_id' => $empresa_id,
            ':veiculo_id' => $data['veiculo_id'],
            ':motorista_id' => $data['motorista_id'],
            ':estado_origem' => $data['estado_origem'],
            ':cidade_origem_id' => $data['cidade_origem_id'],
            ':estado_destino' => $data['estado_destino'],
            ':cidade_destino_id' => $data['cidade_destino_id'],
            ':data_saida' => $data['data_saida'],
            ':data_chegada' => $data['data_chegada'] ?? null,
            ':km_saida' => $data['km_saida'] ?? null,
            ':km_chegada' => $data['km_chegada'] ?? null,
            ':distancia_km' => $data['distancia_km'] ?? null,
            ':observacoes' => $data['observacoes'] ?? null,
            ':data_rota' => $data['data_rota'] ?? null,
            ':no_prazo' => $data['no_prazo'] ?? 0,
            ':frete' => $data['frete'] ?? null,
            ':comissao' => $data['comissao'] ?? null,
            ':km_vazio' => $data['km_vazio'] ?? null,
            ':total_km' => $data['total_km'] ?? null,
            ':percentual_vazio' => $data['percentual_vazio'] ?? null,
            ':eficiencia_viagem' => $data['eficiencia_viagem'] ?? null,
            ':peso_carga' => $data['peso_carga'] ?? null,
            ':descricao_carga' => $data['descricao_carga'] ?? null
        ];
        
        // Log para debug
        error_log("SQL Update: " . $sql);
        error_log("Parâmetros Update: " . print_r($params, true));
        
        // Executa a query
        foreach ($params as $key => &$val) {
            $stmt->bindValue($key, $val);
        }
        
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Rota atualizada com sucesso'
        ];
        
    } catch(PDOException $e) {
        error_log("Erro em updateRoute: " . $e->getMessage());
        throw new Exception('Erro ao atualizar rota: ' . $e->getMessage());
    }
}

/**
 * Exclui uma rota
 */
function deleteRoute($id) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        
        // Verifica se rota existe
        $check_sql = "SELECT COUNT(*) as count FROM rotas WHERE id = :id AND empresa_id = :empresa_id";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $check_stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $check_stmt->execute();
        
        if ($check_stmt->fetch(PDO::FETCH_ASSOC)['count'] == 0) {
            throw new Exception('Rota não encontrada');
        }
        
        // Exclui a rota
        $sql = "DELETE FROM rotas WHERE id = :id AND empresa_id = :empresa_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Rota excluída com sucesso'
        ];
        
    } catch(PDOException $e) {
        error_log("Erro em deleteRoute: " . $e->getMessage());
        throw new Exception('Erro ao excluir rota');
    }
}

/**
 * Obtém lista de motoristas
 */
function getMotoristas() {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        
        $sql = "SELECT id, nome FROM motoristas WHERE empresa_id = :empresa_id ORDER BY nome";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return [
            'success' => true,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
        
    } catch(PDOException $e) {
        error_log("Erro em getMotoristas: " . $e->getMessage());
        throw new Exception('Erro ao buscar motoristas');
    }
}

/**
 * Obtém lista de veículos
 */
function getVeiculos() {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        
        $sql = "SELECT id, placa, modelo FROM veiculos WHERE empresa_id = :empresa_id ORDER BY placa";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return [
            'success' => true,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
        
    } catch(PDOException $e) {
        error_log("Erro em getVeiculos: " . $e->getMessage());
        throw new Exception('Erro ao buscar veículos');
    }
}

/**
 * Obtém lista de clientes
 */
function getClientes() {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        
        $sql = "SELECT id, nome FROM clientes WHERE empresa_id = :empresa_id ORDER BY nome";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return [
            'success' => true,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
        
    } catch(PDOException $e) {
        error_log("Erro em getClientes: " . $e->getMessage());
        throw new Exception('Erro ao buscar clientes');
    }
}

/**
 * Obtém lista de estados
 */
function getEstados() {
    try {
        $conn = getConnection();
        
        $sql = "SELECT id, uf, nome FROM estados ORDER BY nome";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        
        $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => $estados
        ];
        
    } catch(PDOException $e) {
        error_log("Erro em getEstados: " . $e->getMessage());
        throw new Exception('Erro ao buscar estados');
    }
}

/**
 * Obtém lista de cidades por UF
 */
function getCidades($uf) {
    try {
        $conn = getConnection();
        
        $sql = "SELECT id, nome FROM cidades WHERE uf = :uf ORDER BY nome";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':uf', $uf);
        $stmt->execute();
        
        $cidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => $cidades
        ];
        
    } catch(PDOException $e) {
        error_log("Erro em getCidades: " . $e->getMessage());
        throw new Exception('Erro ao buscar cidades');
    }
}

/**
 * Obtém a porcentagem de comissão do motorista
 */
function getMotoristaComissao($id) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        
        $sql = "SELECT porcentagem_comissao FROM motoristas WHERE id = :id AND empresa_id = :empresa_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'porcentagem_comissao' => $result ? $result['porcentagem_comissao'] : null
        ];
        
    } catch(PDOException $e) {
        error_log("Erro em getMotoristaComissao: " . $e->getMessage());
        throw new Exception('Erro ao buscar comissão do motorista');
    }
} 