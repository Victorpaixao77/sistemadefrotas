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

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_error.log');

// Debug session state
error_log("Session state in despesas_fixas.php: " . print_r($_SESSION, true));

// Get the action from the request
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'list':
            // Get filter parameters
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            $veiculo = isset($_GET['veiculo']) ? $_GET['veiculo'] : '';
            $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
            $status = isset($_GET['status']) ? $_GET['status'] : '';
            $pagamento = isset($_GET['pagamento']) ? $_GET['pagamento'] : '';
            $year = isset($_GET['year']) ? $_GET['year'] : date('Y');
            $month = isset($_GET['month']) ? $_GET['month'] : date('m');
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = 5; // Registros por página
            $offset = ($page - 1) * $limit;
            
            // Base query for counting total records
            $sql_count = "SELECT COUNT(*) as total 
                         FROM despesas_fixas df
                         LEFT JOIN veiculos v ON df.veiculo_id = v.id
                         LEFT JOIN tipos_despesa_fixa td ON df.tipo_despesa_id = td.id
                         WHERE df.empresa_id = :empresa_id";
            
            // Base query for fetching records
            $sql = "SELECT df.*, v.placa as veiculo_placa, td.nome as tipo_nome, 
                           sp.nome as status_nome, fp.nome as forma_pagamento_nome
                    FROM despesas_fixas df
                    LEFT JOIN veiculos v ON df.veiculo_id = v.id
                    LEFT JOIN tipos_despesa_fixa td ON df.tipo_despesa_id = td.id
                    LEFT JOIN status_pagamento sp ON df.status_pagamento_id = sp.id
                    LEFT JOIN formas_pagamento fp ON df.forma_pagamento_id = fp.id
                    WHERE df.empresa_id = :empresa_id";
            
            $params = [':empresa_id' => $_SESSION['empresa_id']];
            
            // Add filters to both queries
            if ($search) {
                $sql_count .= " AND (v.placa LIKE :search OR td.nome LIKE :search OR df.descricao LIKE :search)";
                $sql .= " AND (v.placa LIKE :search OR td.nome LIKE :search OR df.descricao LIKE :search)";
                $params[':search'] = "%$search%";
            }
            
            if ($veiculo) {
                $sql_count .= " AND df.veiculo_id = :veiculo";
                $sql .= " AND df.veiculo_id = :veiculo";
                $params[':veiculo'] = $veiculo;
            }
            
            if ($tipo) {
                $sql_count .= " AND df.tipo_despesa_id = :tipo";
                $sql .= " AND df.tipo_despesa_id = :tipo";
                $params[':tipo'] = $tipo;
            }
            
            if ($status) {
                $sql_count .= " AND df.status_pagamento_id = :status";
                $sql .= " AND df.status_pagamento_id = :status";
                $params[':status'] = $status;
            }
            
            if ($pagamento) {
                $sql_count .= " AND df.forma_pagamento_id = :pagamento";
                $sql .= " AND df.forma_pagamento_id = :pagamento";
                $params[':pagamento'] = $pagamento;
            }
            
            if ($year && $month) {
                $sql_count .= " AND YEAR(df.vencimento) = :year AND MONTH(df.vencimento) = :month";
                $sql .= " AND YEAR(df.vencimento) = :year AND MONTH(df.vencimento) = :month";
                $params[':year'] = $year;
                $params[':month'] = $month;
            }
            
            // Get total records
            $stmt_count = $conn->prepare($sql_count);
            foreach ($params as $key => &$val) {
                $stmt_count->bindValue($key, $val);
            }
            $stmt_count->execute();
            $total_records = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
            $total_pages = ceil($total_records / $limit);
            
            // Add pagination to main query
            $sql .= " ORDER BY df.vencimento DESC, df.id DESC LIMIT :limit OFFSET :offset";
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;
            
            // Execute main query
            $stmt = $conn->prepare($sql);
            foreach ($params as $key => &$val) {
                if ($key === ':limit' || $key === ':offset') {
                    $stmt->bindValue($key, $val, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $val);
                }
            }
            $stmt->execute();
            $despesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get metrics
            $metrics = getDespesasMetrics($conn, $_SESSION['empresa_id'], $year, $month);
            
            // Get chart data
            $charts = getDespesasChartData($conn, $_SESSION['empresa_id'], $year, $month);
            
            echo json_encode([
                'despesas' => $despesas,
                'metrics' => $metrics,
                'charts' => $charts,
                'pagina_atual' => $page,
                'total_paginas' => $total_pages,
                'total_registros' => $total_records
            ]);
            break;
            
        case 'get':
            if (!isset($_GET['id'])) {
                throw new Exception('ID não fornecido');
            }
            
            $sql = "SELECT * FROM despesas_fixas WHERE id = :id AND empresa_id = :empresa_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':id', $_GET['id']);
            $stmt->bindValue(':empresa_id', $_SESSION['empresa_id']);
            $stmt->execute();
            
            $despesa = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$despesa) {
                throw new Exception('Despesa não encontrada');
            }
            
            echo json_encode($despesa);
            break;
            
        case 'delete':
            if (!isset($_GET['id'])) {
                throw new Exception('ID não fornecido');
            }
            
            $sql = "DELETE FROM despesas_fixas WHERE id = :id AND empresa_id = :empresa_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':id', $_GET['id']);
            $stmt->bindValue(':empresa_id', $_SESSION['empresa_id']);
            $stmt->execute();
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Handle form submission
                $despesaId = isset($_POST['id']) ? $_POST['id'] : null;
                
                // Validate required fields
                $required_fields = ['veiculo_id', 'tipo_despesa_id', 'valor', 'vencimento', 'status_pagamento_id', 'forma_pagamento_id'];
                foreach ($required_fields as $field) {
                    if (!isset($_POST[$field]) || empty($_POST[$field])) {
                        throw new Exception("Campo obrigatório não preenchido: $field");
                    }
                }
                
                // Prepare base data
                $data = [
                    'empresa_id' => $_SESSION['empresa_id'],
                    'veiculo_id' => $_POST['veiculo_id'],
                    'tipo_despesa_id' => $_POST['tipo_despesa_id'],
                    'valor' => $_POST['valor'],
                    'vencimento' => $_POST['vencimento'],
                    'repetir_automaticamente' => isset($_POST['repetir_automaticamente']) ? $_POST['repetir_automaticamente'] : 0,
                    'ano_referencia' => date('Y', strtotime($_POST['vencimento'])),
                    'mes_pago' => null,
                    'status_pagamento_id' => $_POST['status_pagamento_id'],
                    'data_pagamento' => !empty($_POST['data_pagamento']) ? $_POST['data_pagamento'] : null,
                    'forma_pagamento_id' => $_POST['forma_pagamento_id'],
                    'descricao' => isset($_POST['descricao']) ? $_POST['descricao'] : null,
                    'notificar_vencimento' => isset($_POST['notificar_vencimento']) ? $_POST['notificar_vencimento'] : 0
                ];

                // Processar o upload do comprovante
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
                        $data['comprovante'] = 'uploads/comprovantes/' . $file_name;
                    } else {
                        throw new Exception("Erro ao fazer upload do comprovante");
                    }
                }
                
                // Log data for debugging
                error_log("Session data: " . print_r($_SESSION, true));
                error_log("POST data: " . print_r($_POST, true));
                error_log("Prepared data: " . print_r($data, true));
                
                if ($despesaId) {
                    // Update
                    $data['id'] = $despesaId;
                    $fields = [];
                    $params = [];
                    foreach ($data as $key => $value) {
                        if ($key !== 'id' && $key !== 'empresa_id') {
                            $fields[] = "$key = :$key";
                            $params[":$key"] = $value;
                        }
                    }
                    $params[':id'] = $despesaId;
                    $params[':empresa_id'] = $_SESSION['empresa_id'];
                    
                    $sql = "UPDATE despesas_fixas SET " . implode(", ", $fields) . 
                           " WHERE id = :id AND empresa_id = :empresa_id";
                           
                    // Execute update
                    $stmt = $conn->prepare($sql);
                    foreach ($params as $key => &$val) {
                        $stmt->bindValue($key, $val);
                    }
                } else {
                    // Insert - only include non-null values
                    $fields = [];
                    $values = [];
                    $params = [];
                    foreach ($data as $key => $value) {
                        if ($value !== null) {
                            $fields[] = $key;
                            $values[] = ":$key";
                            $params[":$key"] = $value;
                        }
                    }
                    
                    $sql = "INSERT INTO despesas_fixas (" . implode(", ", $fields) . ") 
                            VALUES (" . implode(", ", $values) . ")";
                            
                    // Prepare statement
                    $stmt = $conn->prepare($sql);
                    foreach ($params as $key => &$val) {
                        $stmt->bindValue($key, $val);
                    }
                }
                
                error_log("SQL Query: " . $sql);
                error_log("Parameters: " . print_r($params, true));
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true]);
                } else {
                    error_log("Database error: " . print_r($stmt->errorInfo(), true));
                    throw new Exception('Erro ao salvar despesa no banco de dados');
                }
            } else {
                throw new Exception('Método não permitido');
            }
            break;
    }
} catch (Exception $e) {
    error_log("Error in despesas_fixas.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Helper functions
function getDespesasMetrics($conn, $empresa_id, $year, $month) {
    // Total de despesas
    $sql = "SELECT COUNT(*) as total_despesas,
                   SUM(CASE WHEN status_pagamento_id = 1 THEN 1 ELSE 0 END) as total_pendentes,
                   SUM(CASE WHEN status_pagamento_id = 3 THEN 1 ELSE 0 END) as total_vencidas,
                   SUM(valor) as valor_total
            FROM despesas_fixas
            WHERE empresa_id = :empresa_id
            AND YEAR(vencimento) = :year AND MONTH(vencimento) = :month";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':empresa_id', $empresa_id);
    $stmt->bindValue(':year', $year);
    $stmt->bindValue(':month', $month);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getDespesasChartData($conn, $empresa_id, $year, $month) {
    try {
        // Dados para o gráfico de tipos de despesa
        $sql_tipos = "SELECT 
            td.nome, 
            COUNT(*) as total, 
            SUM(df.valor) as valor_total
            FROM despesas_fixas df
            JOIN tipos_despesa_fixa td ON df.tipo_despesa_id = td.id
            WHERE df.empresa_id = :empresa_id
            AND YEAR(df.vencimento) = :year 
            AND MONTH(df.vencimento) = :month
            GROUP BY td.id, td.nome
            ORDER BY valor_total DESC";
        
        $stmt = $conn->prepare($sql_tipos);
        $stmt->bindValue(':empresa_id', $empresa_id);
        $stmt->bindValue(':year', $year);
        $stmt->bindValue(':month', $month);
        $stmt->execute();
        $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Dados para o gráfico de status
        $sql_status = "SELECT 
            sp.nome as status, 
            COUNT(*) as total,
            SUM(df.valor) as valor_total
            FROM despesas_fixas df
            JOIN status_pagamento sp ON df.status_pagamento_id = sp.id
            WHERE df.empresa_id = :empresa_id
            AND YEAR(df.vencimento) = :year 
            AND MONTH(df.vencimento) = :month
            GROUP BY sp.id, sp.nome";
        
        $stmt = $conn->prepare($sql_status);
        $stmt->bindValue(':empresa_id', $empresa_id);
        $stmt->bindValue(':year', $year);
        $stmt->bindValue(':month', $month);
        $stmt->execute();
        $status = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Top 5 veículos com mais despesas
        $sql_veiculos = "SELECT 
            v.placa,
            COUNT(*) as total_despesas,
            SUM(df.valor) as valor_total
            FROM despesas_fixas df
            JOIN veiculos v ON df.veiculo_id = v.id
            WHERE df.empresa_id = :empresa_id
            AND YEAR(df.vencimento) = :year 
            AND MONTH(df.vencimento) = :month
            GROUP BY v.id, v.placa
            ORDER BY valor_total DESC
            LIMIT 5";
        
        $stmt = $conn->prepare($sql_veiculos);
        $stmt->bindValue(':empresa_id', $empresa_id);
        $stmt->bindValue(':year', $year);
        $stmt->bindValue(':month', $month);
        $stmt->execute();
        $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Dados para o gráfico de formas de pagamento
        $sql_pagamento = "SELECT 
            fp.nome,
            COUNT(*) as total,
            SUM(df.valor) as valor_total
            FROM despesas_fixas df
            JOIN formas_pagamento fp ON df.forma_pagamento_id = fp.id
            WHERE df.empresa_id = :empresa_id
            AND YEAR(df.vencimento) = :year 
            AND MONTH(df.vencimento) = :month
            GROUP BY fp.id, fp.nome
            ORDER BY valor_total DESC";
        
        $stmt = $conn->prepare($sql_pagamento);
        $stmt->bindValue(':empresa_id', $empresa_id);
        $stmt->bindValue(':year', $year);
        $stmt->bindValue(':month', $month);
        $stmt->execute();
        $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatar dados para os gráficos
        return [
            'tipos' => [
                'labels' => array_map(function($item) { return $item['nome']; }, $tipos),
                'valores' => array_map(function($item) { return floatval($item['valor_total']); }, $tipos)
            ],
            'status' => [
                'labels' => array_map(function($item) { return $item['status']; }, $status),
                'valores' => array_map(function($item) { return floatval($item['valor_total']); }, $status)
            ],
            'veiculos' => [
                'labels' => array_map(function($item) { return $item['placa']; }, $veiculos),
                'valores' => array_map(function($item) { return floatval($item['valor_total']); }, $veiculos)
            ],
            'pagamentos' => [
                'labels' => array_map(function($item) { return $item['nome']; }, $pagamentos),
                'valores' => array_map(function($item) { return floatval($item['valor_total']); }, $pagamentos)
            ]
        ];
    } catch(PDOException $e) {
        error_log("Erro ao buscar dados dos gráficos: " . $e->getMessage());
        return [
            'tipos' => ['labels' => [], 'valores' => []],
            'status' => ['labels' => [], 'valores' => []],
            'veiculos' => ['labels' => [], 'valores' => []],
            'pagamentos' => ['labels' => [], 'valores' => []]
        ];
    }
} 