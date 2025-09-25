<?php
// Configuração de erro
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Inclui arquivos necessários
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';

// Configura e inicia a sessão
configure_session();
session_start();

// Define o tipo de conteúdo como JSON
header('Content-Type: application/json');

// Verifica se o usuário está logado usando a função is_logged_in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

try {
    // Verifica o método da requisição
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Busca parcelas de um financiamento específico
            if (isset($_GET['id']) && isset($_GET['action']) && $_GET['action'] === 'parcelas') {
                $conn = getConnection();
                
                // Log para debug
                error_log("Buscando parcelas para financiamento ID: " . $_GET['id']);
                
                // Primeiro, busca os dados do financiamento para criar as parcelas se não existirem
                $sql_financiamento = "SELECT * FROM financiamentos WHERE id = :id AND empresa_id = :empresa_id";
                $stmt_financiamento = $conn->prepare($sql_financiamento);
                $stmt_financiamento->execute([
                    'id' => $_GET['id'],
                    'empresa_id' => $_SESSION['empresa_id']
                ]);
                $financiamento = $stmt_financiamento->fetch(PDO::FETCH_ASSOC);
                
                if (!$financiamento) {
                    throw new Exception("Financiamento não encontrado");
                }
                
                // Verifica se já existem parcelas
                $sql_check = "SELECT COUNT(*) FROM parcelas_financiamento WHERE financiamento_id = :id";
                $stmt_check = $conn->prepare($sql_check);
                $stmt_check->execute(['id' => $_GET['id']]);
                $count = $stmt_check->fetchColumn();
                
                // Se não existirem parcelas, cria-as
                if ($count == 0) {
                    $data_inicio = new DateTime($financiamento['data_inicio']);
                    $valor_parcela = $financiamento['valor_parcela'];
                    $numero_parcelas = $financiamento['numero_parcelas'];
                    
                    // Prepara a inserção das parcelas
                    $sql_insert = "INSERT INTO parcelas_financiamento 
                                 (financiamento_id, numero_parcela, valor, data_vencimento, status_id, empresa_id) 
                                 VALUES (:financiamento_id, :numero_parcela, :valor, :data_vencimento, 1, :empresa_id)";
                    $stmt_insert = $conn->prepare($sql_insert);
                    
                    // Cria cada parcela
                    for ($i = 1; $i <= $numero_parcelas; $i++) {
                        $data_vencimento = clone $data_inicio;
                        $data_vencimento->modify("+" . ($i - 1) . " months");
                        
                        $stmt_insert->execute([
                            'financiamento_id' => $_GET['id'],
                            'numero_parcela' => $i,
                            'valor' => $valor_parcela,
                            'data_vencimento' => $data_vencimento->format('Y-m-d'),
                            'empresa_id' => $financiamento['empresa_id']
                        ]);
                    }
                }
                
                // Busca todas as parcelas
                $sql = "SELECT pf.*, 
                        fp.nome as forma_pagamento_nome,
                        sp.nome as status_nome
                        FROM parcelas_financiamento pf
                        LEFT JOIN formas_pagamento fp ON pf.forma_pagamento_id = fp.id
                        LEFT JOIN status_pagamento sp ON pf.status_id = sp.id
                        WHERE pf.financiamento_id = :id
                        ORDER BY pf.numero_parcela ASC";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute(['id' => $_GET['id']]);
                $parcelas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $parcelas]);
            }
            // Busca um financiamento específico
            else if (isset($_GET['id'])) {
                $conn = getConnection();
                
                $sql = "SELECT f.*, 
                        CONCAT(v.placa, ' - ', v.modelo) as veiculo_nome,
                        b.nome as banco_nome,
                        s.nome as status_nome,
                        ec.razao_social as empresa_nome
                        FROM financiamentos f
                        LEFT JOIN veiculos v ON f.veiculo_id = v.id
                        LEFT JOIN bancos b ON f.banco_id = b.id
                        LEFT JOIN status_pagamento s ON f.status_pagamento_id = s.id
                        LEFT JOIN empresa_clientes ec ON f.empresa_id = ec.id
                        WHERE f.id = :id AND f.empresa_id = :empresa_id";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    'id' => $_GET['id'],
                    'empresa_id' => $_SESSION['empresa_id']
                ]);
                $financiamento = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($financiamento) {
                    echo json_encode(['success' => true, 'data' => $financiamento]);
                } else {
                    throw new Exception('Financiamento não encontrado');
                }
            } else {
                throw new Exception('ID do financiamento não fornecido');
            }
            break;
            
        case 'POST':
            // Registrar pagamento de parcela
            if (isset($_POST['action'])) {
                if ($_POST['action'] === 'registrar_pagamento') {
                    $conn = getConnection();
                    
                    // Validação dos campos obrigatórios
                    $required_fields = [
                        'financiamento_id' => 'ID do Financiamento',
                        'numero_parcela' => 'Número da Parcela',
                        'valor' => 'Valor',
                        'data_pagamento' => 'Data do Pagamento',
                        'forma_pagamento_id' => 'Forma de Pagamento',
                        'empresa_id' => 'ID da Empresa'
                    ];
                    
                    $missing_fields = [];
                    foreach ($required_fields as $field => $label) {
                        if (!isset($_POST[$field]) || empty($_POST[$field])) {
                            $missing_fields[] = $label;
                        }
                    }
                    
                    if (!empty($missing_fields)) {
                        throw new Exception("Campos obrigatórios não preenchidos: " . implode(", ", $missing_fields));
                    }

                    // Processar o upload do comprovante
                    $comprovante_path = null;
                    if (isset($_FILES['comprovante_pagamento']) && $_FILES['comprovante_pagamento']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = '../uploads/comprovantes/';
                        
                        // Criar diretório se não existir
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        // Gerar nome único para o arquivo
                        $file_extension = pathinfo($_FILES['comprovante_pagamento']['name'], PATHINFO_EXTENSION);
                        $file_name = uniqid('comprovante_') . '.' . $file_extension;
                        $file_path = $upload_dir . $file_name;
                        
                        // Mover o arquivo
                        if (move_uploaded_file($_FILES['comprovante_pagamento']['tmp_name'], $file_path)) {
                            $comprovante_path = 'uploads/comprovantes/' . $file_name;
                        } else {
                            throw new Exception("Erro ao fazer upload do comprovante");
                        }
                    }
                    
                    // Atualiza a parcela
                    $sql = "UPDATE parcelas_financiamento SET 
                            data_pagamento = :data_pagamento,
                            forma_pagamento_id = :forma_pagamento_id,
                            status_id = 2, -- Status Pago
                            comprovante_pagamento = :comprovante_pagamento,
                            observacoes = :observacoes
                            WHERE financiamento_id = :financiamento_id 
                            AND numero_parcela = :numero_parcela
                            AND empresa_id = :empresa_id";
                    
                    $data = [
                        'financiamento_id' => $_POST['financiamento_id'],
                        'numero_parcela' => $_POST['numero_parcela'],
                        'data_pagamento' => $_POST['data_pagamento'],
                        'forma_pagamento_id' => $_POST['forma_pagamento_id'],
                        'comprovante_pagamento' => $comprovante_path,
                        'observacoes' => !empty($_POST['observacoes']) ? $_POST['observacoes'] : null,
                        'empresa_id' => $_SESSION['empresa_id']
                    ];
                    
                    $stmt = $conn->prepare($sql);
                    $result = $stmt->execute($data);
                    
                    if (!$result) {
                        throw new Exception("Erro ao registrar pagamento: " . implode(", ", $stmt->errorInfo()));
                    }
                    
                    if ($stmt->rowCount() === 0) {
                        throw new Exception("Parcela não encontrada ou sem permissão para editar");
                    }
                    
                    echo json_encode(['success' => true, 'message' => 'Pagamento registrado com sucesso']);
                }
                // Reverter pagamento
                else if ($_POST['action'] === 'reverter_pagamento') {
                    $conn = getConnection();
                    
                    // Validação dos campos obrigatórios
                    $required_fields = ['financiamento_id', 'numero_parcela', 'empresa_id'];
                    
                    foreach ($required_fields as $field) {
                        if (!isset($_POST[$field]) || empty($_POST[$field])) {
                            throw new Exception("Campo obrigatório não preenchido: " . $field);
                        }
                    }
                    
                    // Reverte o pagamento da parcela
                    $sql = "UPDATE parcelas_financiamento SET 
                            data_pagamento = NULL,
                            forma_pagamento_id = NULL,
                            status_id = 1, -- Status Pendente
                            comprovante_pagamento = NULL,
                            observacoes = NULL
                            WHERE financiamento_id = :financiamento_id 
                            AND numero_parcela = :numero_parcela
                            AND empresa_id = :empresa_id";
                    
                    $data = [
                        'financiamento_id' => $_POST['financiamento_id'],
                        'numero_parcela' => $_POST['numero_parcela'],
                        'empresa_id' => $_SESSION['empresa_id']
                    ];
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->execute($data);
                    
                    if ($stmt->rowCount() === 0) {
                        throw new Exception("Parcela não encontrada ou já está pendente");
                    }
                    
                    echo json_encode(['success' => true, 'message' => 'Pagamento revertido com sucesso']);
                }
                // Salvar financiamento
                else if ($_POST['action'] === 'save_financiamento') {
                    $conn = getConnection();
                    
                    try {
                        // Validação dos campos obrigatórios
                        $required_fields = ['veiculo_id', 'banco_id', 'valor_total', 'numero_parcelas', 
                                          'valor_parcela', 'data_inicio', 'status_pagamento_id', 'empresa_id'];
                        
                        foreach ($required_fields as $field) {
                            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                                throw new Exception("Campo obrigatório não preenchido: " . $field);
                            }
                        }
                        
                        // Prepara os dados para inserção/atualização
                        $data = [
                            'veiculo_id' => $_POST['veiculo_id'],
                            'banco_id' => $_POST['banco_id'],
                            'valor_total' => $_POST['valor_total'],
                            'numero_parcelas' => $_POST['numero_parcelas'],
                            'valor_parcela' => $_POST['valor_parcela'],
                            'data_inicio' => $_POST['data_inicio'],
                            'taxa_juros' => !empty($_POST['taxa_juros']) ? $_POST['taxa_juros'] : null,
                            'status_pagamento_id' => $_POST['status_pagamento_id'],
                            'data_proxima_parcela' => !empty($_POST['data_proxima_parcela']) ? $_POST['data_proxima_parcela'] : null,
                            'contrato' => !empty($_POST['contrato']) ? $_POST['contrato'] : null,
                            'observacoes' => !empty($_POST['observacoes']) ? $_POST['observacoes'] : null,
                            'empresa_id' => $_SESSION['empresa_id']
                        ];
                        
                        // Verifica se é uma atualização ou inserção
                        if (!empty($_POST['financiamentoId'])) {
                            // Atualização
                            $sql = "UPDATE financiamentos SET 
                                    veiculo_id = :veiculo_id,
                                    banco_id = :banco_id,
                                    valor_total = :valor_total,
                                    numero_parcelas = :numero_parcelas,
                                    valor_parcela = :valor_parcela,
                                    data_inicio = :data_inicio,
                                    taxa_juros = :taxa_juros,
                                    status_pagamento_id = :status_pagamento_id,
                                    data_proxima_parcela = :data_proxima_parcela,
                                    contrato = :contrato,
                                    observacoes = :observacoes
                                    WHERE id = :id AND empresa_id = :empresa_id";
                            
                            $data['id'] = $_POST['financiamentoId'];
                            
                            $stmt = $conn->prepare($sql);
                            $result = $stmt->execute($data);
                            
                            if (!$result) {
                                throw new Exception("Erro ao atualizar financiamento: " . implode(", ", $stmt->errorInfo()));
                            }
                            
                            // Verifica se o financiamento existe antes de continuar
                            $sql_check = "SELECT COUNT(*) FROM financiamentos WHERE id = :id AND empresa_id = :empresa_id";
                            $stmt_check = $conn->prepare($sql_check);
                            $stmt_check->execute([
                                'id' => $_POST['financiamentoId'],
                                'empresa_id' => $_SESSION['empresa_id']
                            ]);
                            
                            if ($stmt_check->fetchColumn() == 0) {
                                throw new Exception("Financiamento não encontrado ou sem permissão para editar");
                            }
                            
                            // Atualiza as parcelas existentes
                            $sql_check = "SELECT COUNT(*) FROM parcelas_financiamento WHERE financiamento_id = :id";
                            $stmt_check = $conn->prepare($sql_check);
                            $stmt_check->execute(['id' => $_POST['financiamentoId']]);
                            $count = $stmt_check->fetchColumn();
                            
                            if ($count > 0) {
                                // Atualiza o valor das parcelas existentes
                                $sql_update = "UPDATE parcelas_financiamento SET 
                                             valor = :valor_parcela 
                                             WHERE financiamento_id = :id";
                                $stmt_update = $conn->prepare($sql_update);
                                $result = $stmt_update->execute([
                                    'valor_parcela' => $_POST['valor_parcela'],
                                    'id' => $_POST['financiamentoId']
                                ]);
                                
                                if (!$result) {
                                    throw new Exception("Erro ao atualizar parcelas: " . implode(", ", $stmt_update->errorInfo()));
                                }
                                
                                // Se o número de parcelas aumentou, cria as novas parcelas
                                if ($count < $_POST['numero_parcelas']) {
                                    $data_inicio = new DateTime($_POST['data_inicio']);
                                    $valor_parcela = $_POST['valor_parcela'];
                                    
                                    // Prepara a inserção das novas parcelas
                                    $sql_insert = "INSERT INTO parcelas_financiamento 
                                                 (financiamento_id, numero_parcela, valor, data_vencimento, status_id, empresa_id) 
                                                 VALUES (:financiamento_id, :numero_parcela, :valor, :data_vencimento, 1, :empresa_id)";
                                    $stmt_insert = $conn->prepare($sql_insert);
                                    
                                    // Cria as novas parcelas
                                    for ($i = $count + 1; $i <= $_POST['numero_parcelas']; $i++) {
                                        $data_vencimento = clone $data_inicio;
                                        $data_vencimento->modify("+" . ($i - 1) . " months");
                                        
                                        $result = $stmt_insert->execute([
                                            'financiamento_id' => $_POST['financiamentoId'],
                                            'numero_parcela' => $i,
                                            'valor' => $valor_parcela,
                                            'data_vencimento' => $data_vencimento->format('Y-m-d'),
                                            'empresa_id' => $_SESSION['empresa_id']
                                        ]);
                                        
                                        if (!$result) {
                                            throw new Exception("Erro ao criar nova parcela: " . implode(", ", $stmt_insert->errorInfo()));
                                        }
                                    }
                                }
                                // Se o número de parcelas diminuiu, remove as parcelas extras
                                else if ($count > $_POST['numero_parcelas']) {
                                    $sql_delete = "DELETE FROM parcelas_financiamento 
                                                 WHERE financiamento_id = :id 
                                                 AND numero_parcela > :numero_parcelas";
                                    $stmt_delete = $conn->prepare($sql_delete);
                                    $result = $stmt_delete->execute([
                                        'id' => $_POST['financiamentoId'],
                                        'numero_parcelas' => $_POST['numero_parcelas']
                                    ]);
                                    
                                    if (!$result) {
                                        throw new Exception("Erro ao remover parcelas extras: " . implode(", ", $stmt_delete->errorInfo()));
                                    }
                                }
                            }
                            
                            echo json_encode(['success' => true, 'message' => 'Financiamento atualizado com sucesso']);
                        } else {
                            // Inserção
                            $sql = "INSERT INTO financiamentos (
                                        veiculo_id, banco_id, valor_total, numero_parcelas, 
                                        valor_parcela, data_inicio, taxa_juros, status_pagamento_id,
                                        data_proxima_parcela, contrato, observacoes, empresa_id
                                    ) VALUES (
                                        :veiculo_id, :banco_id, :valor_total, :numero_parcelas,
                                        :valor_parcela, :data_inicio, :taxa_juros, :status_pagamento_id,
                                        :data_proxima_parcela, :contrato, :observacoes, :empresa_id
                                    )";
                            
                            $stmt = $conn->prepare($sql);
                            $result = $stmt->execute($data);
                            
                            if (!$result) {
                                throw new Exception("Erro ao inserir financiamento: " . implode(", ", $stmt->errorInfo()));
                            }
                            
                            $financiamento_id = $conn->lastInsertId();
                            
                            // Cria as parcelas iniciais
                            $data_inicio = new DateTime($_POST['data_inicio']);
                            $valor_parcela = $_POST['valor_parcela'];
                            $numero_parcelas = $_POST['numero_parcelas'];
                            
                            // Log para debug
                            error_log("Status inicial do financiamento: " . $_POST['status_pagamento_id']);
                            
                            $sql_insert = "INSERT INTO parcelas_financiamento 
                                         (financiamento_id, numero_parcela, valor, data_vencimento, status_id, empresa_id, data_pagamento) 
                                         VALUES (:financiamento_id, :numero_parcela, :valor, :data_vencimento, :status_id, :empresa_id, :data_pagamento)";
                            $stmt_insert = $conn->prepare($sql_insert);
                            
                            for ($i = 1; $i <= $numero_parcelas; $i++) {
                                $data_vencimento = clone $data_inicio;
                                $data_vencimento->modify("+" . ($i - 1) . " months");
                                
                                // Define o status e data de pagamento baseado no status inicial do financiamento
                                if ($i === 1) {
                                    // Para a primeira parcela, usa o status do financiamento
                                    $status_id = $_POST['status_pagamento_id'];
                                    // Se o status for pago (2), define a data de pagamento
                                    $data_pagamento = ($status_id == 2) ? $data_inicio->format('Y-m-d') : null;
                                    
                                    // Log para debug
                                    error_log("Primeira parcela - Status: " . $status_id . ", Data Pagamento: " . ($data_pagamento ?? 'null'));
                                } else {
                                    // Para as demais parcelas, sempre pendente
                                    $status_id = 1; // Status pendente
                                    $data_pagamento = null;
                                }
                                
                                $params = [
                                    'financiamento_id' => $financiamento_id,
                                    'numero_parcela' => $i,
                                    'valor' => $valor_parcela,
                                    'data_vencimento' => $data_vencimento->format('Y-m-d'),
                                    'status_id' => $status_id,
                                    'empresa_id' => $_SESSION['empresa_id'],
                                    'data_pagamento' => $data_pagamento
                                ];
                                
                                // Log para debug
                                error_log("Inserindo parcela " . $i . " com status " . $status_id);
                                
                                $result = $stmt_insert->execute($params);
                                
                                if (!$result) {
                                    throw new Exception("Erro ao criar parcela inicial: " . implode(", ", $stmt_insert->errorInfo()));
                                }
                            }
                            
                            echo json_encode(['success' => true, 'message' => 'Financiamento salvo com sucesso']);
                        }
                    } catch (Exception $e) {
                        error_log("Erro ao salvar financiamento: " . $e->getMessage());
                        throw new Exception("Erro ao salvar financiamento: " . $e->getMessage());
                    }
                }
            }
            break;
            
        case 'DELETE':
            if (isset($_GET['id'])) {
                $conn = getConnection();
                $sql = "DELETE FROM financiamentos WHERE id = :id AND empresa_id = :empresa_id";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    'id' => $_GET['id'],
                    'empresa_id' => $_SESSION['empresa_id']
                ]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Financiamento excluído com sucesso']);
                } else {
                    throw new Exception('Financiamento não encontrado');
                }
            } else {
                throw new Exception('ID do financiamento não fornecido');
            }
            break;
            
        default:
            throw new Exception('Método não permitido');
    }
} catch (Exception $e) {
    error_log("Erro na API de financiamentos: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("Request data: " . print_r($_REQUEST, true));
    error_log("Session data: " . print_r($_SESSION, true));
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao processar a requisição: ' . $e->getMessage(),
        'error_details' => [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
    exit;
} 