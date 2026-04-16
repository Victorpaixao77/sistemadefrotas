<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_once '../includes/csrf.php';
require_once '../includes/sf_cache.php';
require_once '../includes/upload_comprovante.php';
require_once __DIR__ . '/../includes/bi_cache_invalidate.php';

configure_session();
session_start();
require_authentication();

$conn = getConnection();

header('Content-Type: application/json; charset=utf-8');
if (DEBUG_MODE) {
    ini_set('display_errors', '0');
}

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
            $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
            if (!in_array($per_page, [5, 10, 25, 50, 100], true)) {
                $per_page = 10;
            }
            $limit = $per_page;
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

            $allowedSort = [
                'vencimento' => 'df.vencimento',
                'veiculo_placa' => 'v.placa',
                'tipo_nome' => 'td.nome',
                'descricao' => 'df.descricao',
                'valor' => 'df.valor',
                'status_nome' => 'sp.nome',
                'data_pagamento' => 'df.data_pagamento',
                'forma_pagamento_nome' => 'fp.nome',
                'repetir' => 'df.repetir_automaticamente',
            ];
            $sortKey = isset($_GET['sort']) ? trim((string) $_GET['sort']) : 'vencimento';
            if ($sortKey === '' || !isset($allowedSort[$sortKey])) {
                $sortKey = 'vencimento';
            }
            $orderCol = $allowedSort[$sortKey];
            $dir = (isset($_GET['dir']) && strtoupper(trim((string) $_GET['dir'])) === 'ASC') ? 'ASC' : 'DESC';
            
            // Add pagination to main query
            $sql .= " ORDER BY " . $orderCol . " " . $dir . ", df.id DESC LIMIT :limit OFFSET :offset";
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

            $cacheKey = 'df_list_' . md5(json_encode([
                (int) $_SESSION['empresa_id'],
                $search, $veiculo, $tipo, $status, $pagamento, $year, $month,
                $page, $per_page, $sortKey, $dir,
            ]));
            $cached = sf_file_cache_get($cacheKey, 60);
            if (is_array($cached)) {
                echo json_encode($cached);
                break;
            }

            $metrics = getDespesasMetrics($conn, $_SESSION['empresa_id'], $year, $month);
            $charts = getDespesasChartData($conn, $_SESSION['empresa_id'], $year, $month);

            $payload = [
                'success' => true,
                'despesas' => $despesas,
                'metrics' => $metrics,
                'charts' => $charts,
                'pagina_atual' => $page,
                'total_paginas' => $total_pages,
                'total_registros' => $total_records,
            ];
            sf_file_cache_set($cacheKey, $payload);
            echo json_encode($payload);
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
            
            bi_cache_invalidate_empresa($conn, (int) $_SESSION['empresa_id']);
            echo json_encode(['success' => true, 'message' => 'Registro excluído.']);
            break;
            
        default:
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                api_require_csrf_json();
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

                if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] === UPLOAD_ERR_OK) {
                    $root = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
                    $uploadAbs = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'comprovantes';
                    $data['comprovante'] = sf_save_comprovante_upload($_FILES['comprovante'], $uploadAbs, 'uploads/comprovantes', false);
                }

                sf_log_debug('despesas_fixas POST empresa=' . (int) $_SESSION['empresa_id']);
                
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
                
                if ($stmt->execute()) {
                    bi_cache_invalidate_empresa($conn, (int) $_SESSION['empresa_id']);
                    echo json_encode(['success' => true, 'message' => 'Salvo com sucesso.']);
                } else {
                    sf_log_debug('despesas_fixas DB error: ' . json_encode($stmt->errorInfo()));
                    throw new Exception('Erro ao salvar despesa no banco de dados');
                }
            } else {
                throw new Exception('Método não permitido');
            }
            break;
    }
} catch (Exception $e) {
    sf_log_debug('despesas_fixas: ' . $e->getMessage());
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
        sf_log_debug('getDespesasChartData: ' . $e->getMessage());
        return [
            'tipos' => ['labels' => [], 'valores' => []],
            'status' => ['labels' => [], 'valores' => []],
            'veiculos' => ['labels' => [], 'valores' => []],
            'pagamentos' => ['labels' => [], 'valores' => []]
        ];
    }
} 