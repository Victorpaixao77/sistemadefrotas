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
            
        case 'import_nfe_xml':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método não permitido']);
                break;
            }
            try {
                $result = importNFeXml();
                echo json_encode($result);
            } catch (Throwable $e) {
                if (defined('DEBUG_MODE') && DEBUG_MODE) {
                    error_log('importNFeXml: ' . $e->getMessage());
                }
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;
            
        case 'save_expenses':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                echo json_encode(['success' => false, 'message' => 'Dados não fornecidos']);
                exit;
            }
            
            try {
                $conn = getConnection();
                $empresa_id = $_SESSION['empresa_id'];
                $rota_id = isset($data['rota_id']) ? (int)$data['rota_id'] : 0;

                // Garantir isolamento: rota deve pertencer à empresa do usuário
                $checkRota = $conn->prepare("SELECT id FROM rotas WHERE id = :rota_id AND empresa_id = :empresa_id LIMIT 1");
                $checkRota->bindParam(':rota_id', $rota_id, PDO::PARAM_INT);
                $checkRota->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
                $checkRota->execute();
                if ($checkRota->rowCount() === 0) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Rota não encontrada']);
                    exit;
                }

                // Check if expenses already exist for this route (só da mesma empresa)
                $sql = "SELECT id FROM despesas_viagem WHERE rota_id = :rota_id AND empresa_id = :empresa_id";
                $checkStmt = $conn->prepare($sql);
                $checkStmt->bindParam(':rota_id', $rota_id, PDO::PARAM_INT);
                $checkStmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
                $checkStmt->execute();
                
                if ($checkStmt->rowCount() > 0) {
                    // Update existing expenses (sempre com empresa_id)
                    $sql = "UPDATE despesas_viagem SET 
                        descarga = :descarga, 
                        pedagios = :pedagios, 
                        caixinha = :caixinha, 
                        estacionamento = :estacionamento, 
                        lavagem = :lavagem, 
                        borracharia = :borracharia, 
                        eletrica_mecanica = :eletrica_mecanica, 
                        adiantamento = :adiantamento
                        WHERE rota_id = :rota_id AND empresa_id = :empresa_id";
                        
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
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
                
                $stmt->bindParam(':rota_id', $rota_id, PDO::PARAM_INT);
                $stmt->bindParam(':descarga', $data['descarga'], PDO::PARAM_STR);
                $stmt->bindParam(':pedagios', $data['pedagios'], PDO::PARAM_STR);
                $stmt->bindParam(':caixinha', $data['caixinha'], PDO::PARAM_STR);
                $stmt->bindParam(':estacionamento', $data['estacionamento'], PDO::PARAM_STR);
                $stmt->bindParam(':lavagem', $data['lavagem'], PDO::PARAM_STR);
                $stmt->bindParam(':borracharia', $data['borracharia'], PDO::PARAM_STR);
                $stmt->bindParam(':eletrica_mecanica', $data['eletrica_mecanica'], PDO::PARAM_STR);
                $stmt->bindParam(':adiantamento', $data['adiantamento'], PDO::PARAM_STR);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Despesas salvas com sucesso']);
                } else {
                    throw new Exception($stmt->errorInfo()[2]);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Erro ao salvar despesas.', 'error' => $e->getMessage()]);
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
                    echo json_encode(['success' => false, 'message' => 'Erro ao excluir despesas.', 'error' => $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'ID da rota inválido']);
            }
            exit;
            
        default:
            throw new Exception('Ação inválida');
    }
} catch (Exception $e) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log("Erro na API de rotas: " . $e->getMessage());
    }
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
        
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Dados recebidos: " . print_r($data, true));
        }
        
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
        
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("SQL: " . $sql);
            error_log("Parâmetros: " . print_r($params, true));
        }
        
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
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Erro em addRoute: " . $e->getMessage());
        }
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
        
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Dados recebidos para atualização: " . print_r($data, true));
        }
        
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
        
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("SQL Update: " . $sql);
            error_log("Parâmetros Update: " . print_r($params, true));
        }
        
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
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Erro em updateRoute: " . $e->getMessage());
        }
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
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Erro em deleteRoute: " . $e->getMessage());
        }
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
        if (defined('DEBUG_MODE') && DEBUG_MODE) { error_log("Erro em getMotoristas: " . $e->getMessage()); }
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
        if (defined('DEBUG_MODE') && DEBUG_MODE) { error_log("Erro em getVeiculos: " . $e->getMessage()); }
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
        if (defined('DEBUG_MODE') && DEBUG_MODE) { error_log("Erro em getClientes: " . $e->getMessage()); }
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
        if (defined('DEBUG_MODE') && DEBUG_MODE) { error_log("Erro em getEstados: " . $e->getMessage()); }
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
        if (defined('DEBUG_MODE') && DEBUG_MODE) { error_log("Erro em getCidades: " . $e->getMessage()); }
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
        if (defined('DEBUG_MODE') && DEBUG_MODE) { error_log("Erro em getMotoristaComissao: " . $e->getMessage()); }
        throw new Exception('Erro ao buscar comissão do motorista');
    }
}

/**
 * Importa XML de NF-e e cria uma rota com os dados extraídos
 */
function importNFeXml() {
    $empresa_id = $_SESSION['empresa_id'];
    $conn = getConnection();
    $xmlContent = null;
    if (!empty($_FILES['xml_file']['tmp_name']) && is_uploaded_file($_FILES['xml_file']['tmp_name'])) {
        $xmlContent = file_get_contents($_FILES['xml_file']['tmp_name']);
    } else {
        $input = file_get_contents('php://input');
        $json = @json_decode($input, true);
        if (!empty($json['xml'])) $xmlContent = $json['xml'];
        elseif (preg_match('/^\s*<\?xml|<nfeProc|<NFe/i', $input)) $xmlContent = $input;
    }
    if (empty($xmlContent)) {
        return ['success' => false, 'message' => 'Nenhum arquivo XML enviado. Envie o arquivo da NF-e.'];
    }

    // Remover namespace padrão para o SimpleXML encontrar os nós (emit/dest/enderEmit/enderDest)
    $xmlContent = preg_replace('/\sxmlns=["\'][^"\']*["\']/', '', $xmlContent);

    libxml_use_internal_errors(true);
    $xml = @simplexml_load_string($xmlContent);
    if ($xml === false) {
        $err = libxml_get_last_error();
        return ['success' => false, 'message' => 'XML inválido: ' . ($err ? $err->message : 'erro')];
    }
    $ns = 'http://www.portalfiscal.inf.br/nfe';

    // infNFe: sem namespace (após remoção do xmlns) ou com namespace
    $infNFe = null;
    if (isset($xml->NFe->infNFe)) {
        $infNFe = $xml->NFe->infNFe;
    } elseif (isset($xml->infNFe)) {
        $infNFe = $xml->infNFe;
    }
    if (!$infNFe) {
        $root = $xml->children($ns);
        if (isset($root->NFe)) {
            $nfeEl = $root->NFe;
            $infNFe = isset($nfeEl->infNFe) ? $nfeEl->infNFe : $nfeEl->children($ns)->infNFe ?? null;
        }
        if (!$infNFe && isset($root->infNFe)) $infNFe = $root->infNFe;
    }
    if (!$infNFe) {
        $xml->registerXPathNamespace('nfe', $ns);
        $infNFe = $xml->xpath('//nfe:infNFe')[0] ?? $xml->xpath('//infNFe')[0] ?? null;
    }
    if (!$infNFe) {
        return ['success' => false, 'message' => 'XML não contém infNFe (não é uma NF-e reconhecida).'];
    }

    $inf = $infNFe->children($ns);
    if (!$inf || !isset($inf->emit)) {
        $inf = $infNFe;
    }
    $ide = $inf->ide ?? null;
    $emit = $inf->emit ?? null;
    $enderEmit = ($emit && isset($emit->enderEmit)) ? $emit->enderEmit : null;
    $dest = $inf->dest ?? null;
    $enderDest = ($dest && isset($dest->enderDest)) ? $dest->enderDest : null;

    $total = $inf->total->ICMSTot ?? null;
    $infAdic = $inf->infAdic ?? null;
    $infCpl = $infAdic && isset($infAdic->infCpl) ? (string)$infAdic->infCpl : '';

    $cMunOrigem = $enderEmit && isset($enderEmit->cMun) ? (string)$enderEmit->cMun : '';
    $xMunOrigem = $enderEmit && isset($enderEmit->xMun) ? (string)$enderEmit->xMun : '';
    $ufOrigem = $enderEmit && isset($enderEmit->UF) ? strtoupper((string)$enderEmit->UF) : '';
    $cMunDestino = $enderDest && isset($enderDest->cMun) ? (string)$enderDest->cMun : '';
    $xMunDestino = $enderDest && isset($enderDest->xMun) ? (string)$enderDest->xMun : '';
    $ufDestino = $enderDest && isset($enderDest->UF) ? strtoupper((string)$enderDest->UF) : '';

    if (empty($ufOrigem) || empty($ufDestino)) {
        return ['success' => false, 'message' => 'Não foi possível identificar origem e/ou destino no XML.'];
    }
    $cidade_origem_id = resolveCidadeByIbgeOrNome($conn, $cMunOrigem, $xMunOrigem, $ufOrigem);
    $cidade_destino_id = resolveCidadeByIbgeOrNome($conn, $cMunDestino, $xMunDestino, $ufDestino);
    if (!$cidade_origem_id || !$cidade_destino_id) {
        return ['success' => false, 'message' => 'Cidade não encontrada. Cadastre: ' . $xMunOrigem . '/' . $ufOrigem . ' e ' . $xMunDestino . '/' . $ufDestino];
    }
    $data_saida = date('Y-m-d');
    if ($ide && isset($ide->dhEmi)) {
        $dh = (string)$ide->dhEmi;
        if ($dh !== '' && ($ts = strtotime($dh))) $data_saida = date('Y-m-d', $ts);
    } elseif ($ide && isset($ide->dhSaiEnt)) {
        $dh = (string)$ide->dhSaiEnt;
        if ($dh !== '' && ($ts = strtotime($dh))) $data_saida = date('Y-m-d', $ts);
    }

    $descricao_carga = '';
    if (isset($inf->det)) {
        foreach ($inf->det as $det) {
            $prod = isset($det->prod) ? $det->prod : null;
            if ($prod) {
                $xProd = isset($prod->xProd) ? (string)$prod->xProd : '';
                $qCom = isset($prod->qCom) ? (string)$prod->qCom : '';
                $uCom = isset($prod->uCom) ? (string)$prod->uCom : '';
                if ($descricao_carga !== '') $descricao_carga .= '; ';
                $descricao_carga .= trim($xProd);
                if ($qCom !== '' && $uCom !== '') $descricao_carga .= ' - ' . $qCom . ' ' . $uCom;
            }
        }
    }
    if ($descricao_carga === '') $descricao_carga = 'Importado da NF-e';
    $vNF = 0;
    if ($total && isset($total->vNF)) $vNF = (float)(string)$total->vNF;
    $chave = (string)($infNFe->attributes()->Id ?? '');
    $observacoes = 'Importado da NF-e. ';
    if (preg_match('/NFe(\d{44})/', $chave, $m)) $observacoes .= 'Chave: ' . $m[1];
    if ($vNF > 0) $observacoes .= ' Valor NF: R$ ' . number_format($vNF, 2, ',', '.');
    $motorista_id = null;
    $veiculo_id = null;
    $km_saida = null;
    $km_chegada = null;
    $distancia_km = null;
    if (preg_match('/Motorista:\s*([^-]+)\s*-\s*CPF:\s*([\d.\s-]+)/ui', $infCpl, $m)) {
        $cpf = preg_replace('/\D/', '', trim($m[2]));
        if (strlen($cpf) >= 11) {
            $stmt = $conn->prepare("SELECT id FROM motoristas WHERE empresa_id = :e AND REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = :c LIMIT 1");
            $stmt->execute([':e' => $empresa_id, ':c' => $cpf]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) $motorista_id = (int)$row['id'];
        }
    }
    if (preg_match('/Placa:\s*([A-Za-z0-9-]+)/ui', $infCpl, $m)) {
        $placa = strtoupper(preg_replace('/\s+/', '', trim($m[1])));
        $stmt = $conn->prepare("SELECT id FROM veiculos WHERE empresa_id = :e AND UPPER(REPLACE(placa,'-','')) = UPPER(REPLACE(:p,'-','')) LIMIT 1");
        $stmt->execute([':e' => $empresa_id, ':p' => $placa]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $veiculo_id = (int)$row['id'];
    }
    if (preg_match('/\bEI[:\s]*(\d+)/ui', $infCpl, $m)) $km_saida = (int)$m[1];
    if (preg_match('/\bEF[:\s]*(\d+)/ui', $infCpl, $m)) { $km_chegada = (int)$m[1]; if ($km_saida !== null) $distancia_km = $km_chegada - $km_saida; }
    if ($km_saida === null && preg_match('/\bKM[:\s]*(\d+)/ui', $infCpl, $m)) $km_saida = (int)$m[1];
    if (!$motorista_id) {
        $stmt = $conn->prepare("SELECT id FROM motoristas WHERE empresa_id = :e ORDER BY nome LIMIT 1");
        $stmt->execute([':e' => $empresa_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $motorista_id = $row ? (int)$row['id'] : null;
        if ($motorista_id && $infCpl !== '') $observacoes .= ' [Motorista do XML não encontrado no cadastro; usado o primeiro da lista.]';
    }
    if (!$veiculo_id) {
        $stmt = $conn->prepare("SELECT id FROM veiculos WHERE empresa_id = :e ORDER BY placa LIMIT 1");
        $stmt->execute([':e' => $empresa_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $veiculo_id = $row ? (int)$row['id'] : null;
        if ($veiculo_id && $infCpl !== '') $observacoes .= ' [Veículo do XML não encontrado no cadastro; usado o primeiro da lista.]';
    }
    if (!$motorista_id || !$veiculo_id) {
        return ['success' => false, 'message' => 'Cadastre ao menos um motorista e um veículo.'];
    }
    $stmt = $conn->prepare("SELECT uf FROM cidades WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $cidade_origem_id]);
    $estado_origem = $stmt->fetchColumn() ?: $ufOrigem;
    $stmt->execute([':id' => $cidade_destino_id]);
    $estado_destino = $stmt->fetchColumn() ?: $ufDestino;
    $data = [
        'motorista_id' => $motorista_id,
        'veiculo_id' => $veiculo_id,
        'estado_origem' => $estado_origem,
        'cidade_origem_id' => $cidade_origem_id,
        'estado_destino' => $estado_destino,
        'cidade_destino_id' => $cidade_destino_id,
        'data_saida' => $data_saida,
        'data_chegada' => $data_saida,
        'km_saida' => $km_saida,
        'km_chegada' => $km_chegada,
        'distancia_km' => $distancia_km,
        'observacoes' => $observacoes,
        'data_rota' => $data_saida,
        'no_prazo' => 0,
        'frete' => $vNF > 0 ? $vNF : null,
        'comissao' => null,
        'km_vazio' => null,
        'total_km' => $distancia_km,
        'percentual_vazio' => null,
        'eficiencia_viagem' => null,
        'peso_carga' => null,
        'descricao_carga' => $descricao_carga,
    ];
    return addRoute($data);
}

function resolveCidadeByIbgeOrNome($conn, $codigoIbge, $nomeCidade, $uf) {
    if (strlen($codigoIbge) >= 5) {
        try {
            $stmt = $conn->prepare("SELECT id FROM cidades WHERE codigo_ibge = :ibge LIMIT 1");
            $stmt->execute([':ibge' => $codigoIbge]);
            $id = $stmt->fetchColumn();
            if ($id) return (int)$id;
        } catch (Throwable $e) {
            // coluna codigo_ibge pode não existir
        }
    }
    $nome = trim($nomeCidade);
    $uf = strtoupper(substr($uf, 0, 2));
    if ($nome === '' || $uf === '') return null;
    $stmt = $conn->prepare("SELECT id FROM cidades WHERE UPPER(TRIM(nome)) = UPPER(:nome) AND UPPER(uf) = :uf LIMIT 1");
    $stmt->execute([':nome' => $nome, ':uf' => $uf]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
} 