<?php
// API de ações dos abastecimentos

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

if (!$empresa_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'ID da empresa não encontrado'
    ]);
    exit;
}

// Get the action from POST or GET
$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
} else {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
}

// Log the request data for debugging
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Action: " . $action);
error_log("POST Data: " . print_r($_POST, true));

try {
    // Validate action
    if (empty($action)) {
        throw new Exception('Ação não especificada');
    }

    $conn = getConnection();
    
    switch ($action) {
        case 'add':
            // Obtém os dados do corpo da requisição
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Valida os dados obrigatórios
            $required_fields = [
                'data_abastecimento', 'veiculo_id', 'motorista_id', 'posto', 
                'litros', 'valor_litro', 'valor_total', 'km_atual', 
                'tipo_combustivel', 'forma_pagamento', 'rota_id'
            ];
            
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Campo obrigatório não preenchido: $field");
                }
            }
            
            // Valida campos ARLA se inclui_arla for true
            if (!empty($data['inclui_arla']) && $data['inclui_arla'] == '1') {
                $required_fields_arla = ['litros_arla', 'valor_litro_arla', 'valor_total_arla'];
                foreach ($required_fields_arla as $field) {
                    if (empty($data[$field])) {
                        throw new Exception("Campo obrigatório não preenchido: $field");
                    }
                }
            }
            
            // Definir status conforme perfil
            $status = 'pendente';
            $fonte = 'motorista';
            
            // Log para debug
            error_log("=== DEBUG ABASTECIMENTO ===");
            error_log("Session data: " . print_r($_SESSION, true));
            error_log("tipo_usuario: " . ($_SESSION['tipo_usuario'] ?? 'NÃO DEFINIDO'));
            error_log("is_admin: " . ($_SESSION['is_admin'] ?? 'NÃO DEFINIDO'));
            
            // Verificação mais robusta do tipo de usuário
            if (isset($_SESSION['tipo_usuario']) && in_array($_SESSION['tipo_usuario'], ['admin', 'gestor'])) {
                $status = 'aprovado';
                $fonte = 'gestor';
                error_log("Usuário identificado como GESTOR - Status: $status, Fonte: $fonte");
            } elseif (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) {
                $status = 'aprovado';
                $fonte = 'gestor';
                error_log("Usuário identificado como ADMIN - Status: $status, Fonte: $fonte");
            } else {
                error_log("Usuário identificado como MOTORISTA - Status: $status, Fonte: $fonte");
            }
            
            // Insere o novo abastecimento
            $sql = "INSERT INTO abastecimentos (
                empresa_id, veiculo_id, motorista_id, posto,
                data_abastecimento, litros, valor_litro, valor_total,
                km_atual, tipo_combustivel, forma_pagamento, observacoes,
                rota_id, data_cadastro, comprovante, status, fonte,
                inclui_arla, litros_arla, valor_litro_arla, valor_total_arla
            ) VALUES (
                :empresa_id, :veiculo_id, :motorista_id, :posto,
                :data_abastecimento, :litros, :valor_litro, :valor_total,
                :km_atual, :tipo_combustivel, :forma_pagamento, :observacoes,
                :rota_id, NOW(), :comprovante, :status, :fonte,
                :inclui_arla, :litros_arla, :valor_litro_arla, :valor_total_arla
            )";
            
            // Processar o upload do comprovante
            $comprovante_path = null;
            if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/comprovantes/';
                
                // Criar diretório se não existir
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Gerar nome único para o arquivo
                $file_extension = pathinfo($_FILES['comprovante']['name'], PATHINFO_EXTENSION);
                $file_name = uniqid('comprovante_') . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;
                
                // Mover o arquivo
                if (move_uploaded_file($_FILES['comprovante']['tmp_name'], $file_path)) {
                    $comprovante_path = 'uploads/comprovantes/' . $file_name;
                } else {
                    throw new Exception("Erro ao fazer upload do comprovante");
                }
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':empresa_id', $empresa_id);
            $stmt->bindValue(':veiculo_id', $data['veiculo_id']);
            $stmt->bindValue(':motorista_id', $data['motorista_id']);
            $stmt->bindValue(':posto', $data['posto']);
            $stmt->bindValue(':data_abastecimento', $data['data_abastecimento']);
            $stmt->bindValue(':litros', $data['litros']);
            $stmt->bindValue(':valor_litro', $data['valor_litro']);
            $stmt->bindValue(':valor_total', $data['valor_total']);
            $stmt->bindValue(':km_atual', $data['km_atual']);
            $stmt->bindValue(':tipo_combustivel', $data['tipo_combustivel']);
            $stmt->bindValue(':forma_pagamento', $data['forma_pagamento']);
            $stmt->bindValue(':observacoes', $data['observacoes'] ?? null);
            $stmt->bindValue(':rota_id', $data['rota_id']);
            $stmt->bindValue(':comprovante', $comprovante_path);
            $stmt->bindValue(':status', $status);
            $stmt->bindValue(':fonte', $fonte);
            $stmt->bindValue(':inclui_arla', !empty($data['inclui_arla']) ? 1 : 0);
            $stmt->bindValue(':litros_arla', !empty($data['litros_arla']) ? $data['litros_arla'] : null);
            $stmt->bindValue(':valor_litro_arla', !empty($data['valor_litro_arla']) ? $data['valor_litro_arla'] : null);
            $stmt->bindValue(':valor_total_arla', !empty($data['valor_total_arla']) ? $data['valor_total_arla'] : null);
            
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Abastecimento registrado com sucesso',
                'id' => $conn->lastInsertId()
            ]);
            break;
            
        case 'edit':
            // Obtém o ID do abastecimento
            $id = isset($_GET['id']) ? $_GET['id'] : null;
            if (!$id) {
                throw new Exception('ID do abastecimento não fornecido');
            }
            
            // Obtém os dados do corpo da requisição
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Valida os dados obrigatórios
            $required_fields = [
                'data_abastecimento', 'veiculo_id', 'motorista_id', 'posto', 
                'litros', 'valor_litro', 'valor_total', 'km_atual', 
                'tipo_combustivel', 'forma_pagamento', 'rota_id'
            ];
            
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Campo obrigatório não preenchido: $field");
                }
            }
            
            // Valida campos ARLA se inclui_arla for true
            if (!empty($data['inclui_arla']) && $data['inclui_arla'] == '1') {
                $required_fields_arla = ['litros_arla', 'valor_litro_arla', 'valor_total_arla'];
                foreach ($required_fields_arla as $field) {
                    if (empty($data[$field])) {
                        throw new Exception("Campo obrigatório não preenchido: $field");
                    }
                }
            }
            
            // Atualiza o abastecimento
            $sql = "UPDATE abastecimentos SET 
                veiculo_id = :veiculo_id,
                motorista_id = :motorista_id,
                posto = :posto,
                data_abastecimento = :data_abastecimento,
                litros = :litros,
                valor_litro = :valor_litro,
                valor_total = :valor_total,
                km_atual = :km_atual,
                tipo_combustivel = :tipo_combustivel,
                forma_pagamento = :forma_pagamento,
                observacoes = :observacoes,
                rota_id = :rota_id,
                comprovante = :comprovante,
                inclui_arla = :inclui_arla,
                litros_arla = :litros_arla,
                valor_litro_arla = :valor_litro_arla,
                valor_total_arla = :valor_total_arla
            WHERE id = :id AND empresa_id = :empresa_id";
            
            // Processar o upload do comprovante
            $comprovante_path = null;
            if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/comprovantes/';
                
                // Criar diretório se não existir
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Gerar nome único para o arquivo
                $file_extension = pathinfo($_FILES['comprovante']['name'], PATHINFO_EXTENSION);
                $file_name = uniqid('comprovante_') . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;
                
                // Mover o arquivo
                if (move_uploaded_file($_FILES['comprovante']['tmp_name'], $file_path)) {
                    $comprovante_path = 'uploads/comprovantes/' . $file_name;
                    
                    // Remover comprovante antigo se existir
                    $sql_old = "SELECT comprovante FROM abastecimentos WHERE id = ? AND empresa_id = ?";
                    $stmt_old = $conn->prepare($sql_old);
                    $stmt_old->execute([$id, $empresa_id]);
                    $old_comprovante = $stmt_old->fetch(PDO::FETCH_ASSOC);
                    
                    if ($old_comprovante && $old_comprovante['comprovante']) {
                        $old_file = '../' . $old_comprovante['comprovante'];
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                    }
                } else {
                    throw new Exception("Erro ao fazer upload do comprovante");
                }
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':id', $id);
            $stmt->bindValue(':empresa_id', $empresa_id);
            $stmt->bindValue(':veiculo_id', $data['veiculo_id']);
            $stmt->bindValue(':motorista_id', $data['motorista_id']);
            $stmt->bindValue(':posto', $data['posto']);
            $stmt->bindValue(':data_abastecimento', $data['data_abastecimento']);
            $stmt->bindValue(':litros', $data['litros']);
            $stmt->bindValue(':valor_litro', $data['valor_litro']);
            $stmt->bindValue(':valor_total', $data['valor_total']);
            $stmt->bindValue(':km_atual', $data['km_atual']);
            $stmt->bindValue(':tipo_combustivel', $data['tipo_combustivel']);
            $stmt->bindValue(':forma_pagamento', $data['forma_pagamento']);
            $stmt->bindValue(':observacoes', $data['observacoes'] ?? null);
            $stmt->bindValue(':rota_id', $data['rota_id']);
            $stmt->bindValue(':comprovante', $comprovante_path);
            $stmt->bindValue(':inclui_arla', !empty($data['inclui_arla']) ? 1 : 0);
            $stmt->bindValue(':litros_arla', !empty($data['litros_arla']) ? $data['litros_arla'] : null);
            $stmt->bindValue(':valor_litro_arla', !empty($data['valor_litro_arla']) ? $data['valor_litro_arla'] : null);
            $stmt->bindValue(':valor_total_arla', !empty($data['valor_total_arla']) ? $data['valor_total_arla'] : null);
            
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Abastecimento atualizado com sucesso'
            ]);
            break;
            
        case 'delete':
            // Obtém o ID do abastecimento
            $id = isset($_GET['id']) ? $_GET['id'] : null;
            if (!$id) {
                throw new Exception('ID do abastecimento não fornecido');
            }
            
            // Exclui o abastecimento
            $sql = "DELETE FROM abastecimentos WHERE id = :id AND empresa_id = :empresa_id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':id', $id);
            $stmt->bindValue(':empresa_id', $empresa_id);
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'message' => 'Abastecimento excluído com sucesso'
            ]);
            break;
            
        case 'get_veiculos':
            // Obtém lista de veículos
            $sql = "SELECT id, placa, modelo FROM veiculos WHERE empresa_id = :empresa_id ORDER BY placa";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':empresa_id', $empresa_id);
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
            break;
            
        case 'get_motoristas':
            // Obtém lista de motoristas
            $sql = "SELECT id, nome FROM motoristas WHERE empresa_id = :empresa_id ORDER BY nome";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':empresa_id', $empresa_id);
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
            break;
            
        case 'get_rotas':
            try {
                // Get filter parameters
                $data = isset($_GET['data']) ? $_GET['data'] : date('Y-m-d');
                $veiculo_id = isset($_GET['veiculo_id']) ? $_GET['veiculo_id'] : null;
                $motorista_id = isset($_GET['motorista_id']) ? $_GET['motorista_id'] : null;
                
                // Base query
                $sql = "SELECT DISTINCT 
                           r.id,
                           CONCAT(
                               co.nome, '/', r.estado_origem, 
                               ' → ',
                               cd.nome, '/', r.estado_destino
                           ) as descricao_rota,
                           r.data_saida,
                           r.veiculo_id,
                           r.motorista_id
                       FROM rotas r 
                       LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                       LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                       WHERE r.empresa_id = :empresa_id 
                       AND DATE(r.data_saida) >= DATE(:data)";
                
                $params = [
                    ':empresa_id' => $empresa_id,
                    ':data' => $data
                ];
                
                // Add filters if provided
                if ($veiculo_id) {
                    $sql .= " AND r.veiculo_id = :veiculo_id";
                    $params[':veiculo_id'] = $veiculo_id;
                }
                
                if ($motorista_id) {
                    $sql .= " AND r.motorista_id = :motorista_id";
                    $params[':motorista_id'] = $motorista_id;
                }
                
                $sql .= " ORDER BY r.data_saida DESC";
                
                $stmt = $conn->prepare($sql);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
                
                echo json_encode([
                    'success' => true,
                    'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
                ]);
            } catch (Exception $e) {
                error_log("Erro ao buscar rotas: " . $e->getMessage());
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Erro ao buscar rotas'
                ]);
            }
            break;

        case 'get':
            // Obtém o ID do abastecimento
            $id = isset($_GET['id']) ? $_GET['id'] : null;
            if (!$id) {
                throw new Exception('ID do abastecimento não fornecido');
            }
            
            // Busca os dados do abastecimento
            $sql = "SELECT a.*, DATE(a.data_abastecimento) as data_rota_filtro
                    FROM abastecimentos a
                    WHERE a.id = :id AND a.empresa_id = :empresa_id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':id', $id);
            $stmt->bindValue(':empresa_id', $empresa_id);
            $stmt->execute();
            
            $abastecimento = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$abastecimento) {
                throw new Exception('Abastecimento não encontrado');
            }
            
            echo json_encode([
                'success' => true,
                'data' => $abastecimento
            ]);
            break;

        case 'create':
            try {
                // Log the incoming data
                error_log("Creating new refueling with data: " . print_r($_POST, true));

                // Validate required fields
                $required_fields = [
                    'data_abastecimento' => 'Data do abastecimento',
                    'veiculo_id' => 'Veículo',
                    'motorista_id' => 'Motorista',
                    'posto' => 'Posto',
                    'litros' => 'Litros',
                    'valor_litro' => 'Valor por litro',
                    'valor_total' => 'Valor total',
                    'km_atual' => 'Quilometragem atual',
                    'tipo_combustivel' => 'Tipo de combustível',
                    'forma_pagamento' => 'Forma de pagamento',
                    'rota_id' => 'Rota'
                ];

                $missing_fields = [];
                foreach ($required_fields as $field => $label) {
                    if (empty($_POST[$field])) {
                        $missing_fields[] = $label;
                    }
                }

                if (!empty($missing_fields)) {
                    throw new Exception("Campos obrigatórios não preenchidos: " . implode(", ", $missing_fields));
                }

                // Validate numeric fields
                $numeric_fields = ['litros', 'valor_litro', 'valor_total', 'km_atual'];
                foreach ($numeric_fields as $field) {
                    if (!is_numeric(str_replace(',', '.', $_POST[$field]))) {
                        throw new Exception("O campo {$required_fields[$field]} deve ser um número válido");
                    }
                }

                // Format numeric values
                $_POST['litros'] = str_replace(',', '.', $_POST['litros']);
                $_POST['valor_litro'] = str_replace(',', '.', $_POST['valor_litro']);
                $_POST['valor_total'] = str_replace(',', '.', $_POST['valor_total']);

                // Definir status conforme perfil
                $status = 'pendente';
                $fonte = 'motorista';
                
                // Log para debug
                error_log("=== DEBUG ABASTECIMENTO CREATE ===");
                error_log("Session data: " . print_r($_SESSION, true));
                error_log("tipo_usuario: " . ($_SESSION['tipo_usuario'] ?? 'NÃO DEFINIDO'));
                error_log("is_admin: " . ($_SESSION['is_admin'] ?? 'NÃO DEFINIDO'));
                
                // Verificação mais robusta do tipo de usuário
                if (isset($_SESSION['tipo_usuario']) && in_array($_SESSION['tipo_usuario'], ['admin', 'gestor'])) {
                    $status = 'aprovado';
                    $fonte = 'gestor';
                    error_log("Usuário identificado como GESTOR - Status: $status, Fonte: $fonte");
                } elseif (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) {
                    $status = 'aprovado';
                    $fonte = 'gestor';
                    error_log("Usuário identificado como ADMIN - Status: $status, Fonte: $fonte");
                } else {
                    error_log("Usuário identificado como MOTORISTA - Status: $status, Fonte: $fonte");
                }

                // Insert new refueling
                $sql = "INSERT INTO abastecimentos (
                    empresa_id, data_abastecimento, veiculo_id, motorista_id,
                    posto, litros, valor_litro, valor_total, km_atual,
                    tipo_combustivel, forma_pagamento, observacoes, rota_id,
                    data_cadastro, status, fonte, inclui_arla, litros_arla, 
                    valor_litro_arla, valor_total_arla
                ) VALUES (
                    :empresa_id, :data_abastecimento, :veiculo_id, :motorista_id,
                    :posto, :litros, :valor_litro, :valor_total, :km_atual,
                    :tipo_combustivel, :forma_pagamento, :observacoes, :rota_id,
                    NOW(), :status, :fonte, :inclui_arla, :litros_arla, 
                    :valor_litro_arla, :valor_total_arla
                )";

                $stmt = $conn->prepare($sql);
                
                // Log the SQL and parameters
                error_log("SQL: " . $sql);
                error_log("Parameters: " . print_r([
                    'empresa_id' => $empresa_id,
                    'data_abastecimento' => $_POST['data_abastecimento'],
                    'veiculo_id' => $_POST['veiculo_id'],
                    'motorista_id' => $_POST['motorista_id'],
                    'posto' => $_POST['posto'],
                    'litros' => $_POST['litros'],
                    'valor_litro' => $_POST['valor_litro'],
                    'valor_total' => $_POST['valor_total'],
                    'km_atual' => $_POST['km_atual'],
                    'tipo_combustivel' => $_POST['tipo_combustivel'],
                    'forma_pagamento' => $_POST['forma_pagamento'],
                    'observacoes' => $_POST['observacoes'] ?? null,
                    'rota_id' => $_POST['rota_id'],
                    'status' => $status,
                    'fonte' => $fonte
                ], true));

                $stmt->bindValue(':empresa_id', $empresa_id);
                $stmt->bindValue(':data_abastecimento', $_POST['data_abastecimento']);
                $stmt->bindValue(':veiculo_id', $_POST['veiculo_id']);
                $stmt->bindValue(':motorista_id', $_POST['motorista_id']);
                $stmt->bindValue(':posto', $_POST['posto']);
                $stmt->bindValue(':litros', $_POST['litros']);
                $stmt->bindValue(':valor_litro', $_POST['valor_litro']);
                $stmt->bindValue(':valor_total', $_POST['valor_total']);
                $stmt->bindValue(':km_atual', $_POST['km_atual']);
                $stmt->bindValue(':tipo_combustivel', $_POST['tipo_combustivel']);
                $stmt->bindValue(':forma_pagamento', $_POST['forma_pagamento']);
                $stmt->bindValue(':observacoes', $_POST['observacoes'] ?? null);
                $stmt->bindValue(':rota_id', $_POST['rota_id']);
                $stmt->bindValue(':status', $status);
                $stmt->bindValue(':fonte', $fonte);
                $stmt->bindValue(':inclui_arla', !empty($_POST['inclui_arla']) ? 1 : 0);
                $stmt->bindValue(':litros_arla', !empty($_POST['litros_arla']) ? str_replace(',', '.', $_POST['litros_arla']) : null);
                $stmt->bindValue(':valor_litro_arla', !empty($_POST['valor_litro_arla']) ? str_replace(',', '.', $_POST['valor_litro_arla']) : null);
                $stmt->bindValue(':valor_total_arla', !empty($_POST['valor_total_arla']) ? str_replace(',', '.', $_POST['valor_total_arla']) : null);

                $stmt->execute();

                echo json_encode([
                    'success' => true,
                    'message' => 'Abastecimento registrado com sucesso',
                    'id' => $conn->lastInsertId()
                ]);
            } catch (Exception $e) {
                error_log("Erro ao criar abastecimento: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            break;

        case 'update':
            try {
                // Validate ID
                if (empty($_POST['id'])) {
                    throw new Exception("ID do abastecimento não fornecido");
                }

                // Debug: verificar dados recebidos (apenas em caso de erro)
                // error_log("Dados recebidos no update: " . print_r($_POST, true));
                
                // Validate required fields
                $required_fields = [
                    'data_abastecimento', 'veiculo_id', 'posto',
                    'litros', 'valor_litro', 'valor_total', 'km_atual',
                    'tipo_combustivel', 'forma_pagamento'
                ];
                
                // Validação específica para motorista_id
                if (!isset($_POST['motorista_id']) || $_POST['motorista_id'] === '' || $_POST['motorista_id'] === null || $_POST['motorista_id'] === '0') {
                    error_log("Motorista ID não encontrado ou vazio: " . var_export($_POST['motorista_id'] ?? 'NOT_SET', true));
                    throw new Exception("Campo obrigatório não preenchido: motorista_id");
                }
                
                // Validação específica para rota_id
                if (!isset($_POST['rota_id']) || $_POST['rota_id'] === '' || $_POST['rota_id'] === null || $_POST['rota_id'] === '0') {
                    error_log("Rota ID não encontrado ou vazio: " . var_export($_POST['rota_id'] ?? 'NOT_SET', true));
                    throw new Exception("Campo obrigatório não preenchido: rota_id");
                }

                foreach ($required_fields as $field) {
                    if (!isset($_POST[$field]) || $_POST[$field] === '' || $_POST[$field] === null) {
                        error_log("Campo obrigatório vazio: $field = " . var_export($_POST[$field] ?? 'NULL', true));
                        throw new Exception("Campo obrigatório não preenchido: $field");
                    }
                }

                // Update refueling
                $sql = "UPDATE abastecimentos SET
                    data_abastecimento = :data_abastecimento,
                    veiculo_id = :veiculo_id,
                    motorista_id = :motorista_id,
                    posto = :posto,
                    litros = :litros,
                    valor_litro = :valor_litro,
                    valor_total = :valor_total,
                    km_atual = :km_atual,
                    tipo_combustivel = :tipo_combustivel,
                    forma_pagamento = :forma_pagamento,
                    observacoes = :observacoes,
                    rota_id = :rota_id,
                    inclui_arla = :inclui_arla,
                    litros_arla = :litros_arla,
                    valor_litro_arla = :valor_litro_arla,
                    valor_total_arla = :valor_total_arla
                WHERE id = :id AND empresa_id = :empresa_id";

                $stmt = $conn->prepare($sql);
                $stmt->bindValue(':id', $_POST['id']);
                $stmt->bindValue(':empresa_id', $empresa_id);
                $stmt->bindValue(':data_abastecimento', $_POST['data_abastecimento']);
                $stmt->bindValue(':veiculo_id', $_POST['veiculo_id']);
                $stmt->bindValue(':motorista_id', $_POST['motorista_id']);
                $stmt->bindValue(':posto', $_POST['posto']);
                $stmt->bindValue(':litros', $_POST['litros']);
                $stmt->bindValue(':valor_litro', $_POST['valor_litro']);
                $stmt->bindValue(':valor_total', $_POST['valor_total']);
                $stmt->bindValue(':km_atual', $_POST['km_atual']);
                $stmt->bindValue(':tipo_combustivel', $_POST['tipo_combustivel']);
                $stmt->bindValue(':forma_pagamento', $_POST['forma_pagamento']);
                $stmt->bindValue(':observacoes', $_POST['observacoes'] ?? null);
                $stmt->bindValue(':rota_id', $_POST['rota_id']);
                $stmt->bindValue(':inclui_arla', !empty($_POST['inclui_arla']) ? 1 : 0);
                $stmt->bindValue(':litros_arla', !empty($_POST['litros_arla']) ? str_replace(',', '.', $_POST['litros_arla']) : null);
                $stmt->bindValue(':valor_litro_arla', !empty($_POST['valor_litro_arla']) ? str_replace(',', '.', $_POST['valor_litro_arla']) : null);
                $stmt->bindValue(':valor_total_arla', !empty($_POST['valor_total_arla']) ? str_replace(',', '.', $_POST['valor_total_arla']) : null);

                $stmt->execute();

                echo json_encode([
                    'success' => true,
                    'message' => 'Abastecimento atualizado com sucesso'
                ]);
            } catch (Exception $e) {
                error_log("Erro ao atualizar abastecimento: " . $e->getMessage());
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            break;

        default:
            throw new Exception('Ação inválida');
    }
} catch (Exception $e) {
    error_log("Erro na API de ações de abastecimentos: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => DEBUG_MODE ? $e->getMessage() : 'Erro ao processar a requisição'
    ]);
} 