<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_once '../includes/csrf.php';
require_once '../includes/api_json.php';
require_once '../includes/sf_cache.php';
require_once '../includes/upload_comprovante.php';
require_once __DIR__ . '/../includes/bi_cache_invalidate.php';
require_once __DIR__ . '/../includes/despesas_fixas_list.php';

configure_session();
session_start();
require_authentication();
if (function_exists('session_write_close')) { session_write_close(); }

$conn = getConnection();

header('Content-Type: application/json; charset=utf-8');
if (DEBUG_MODE) {
    ini_set('display_errors', '0');
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'list':
            $search = isset($_GET['search']) ? (string) $_GET['search'] : '';
            $veiculo = isset($_GET['veiculo']) ? (string) $_GET['veiculo'] : '';
            $tipo = isset($_GET['tipo']) ? (string) $_GET['tipo'] : '';
            $status = isset($_GET['status']) ? (string) $_GET['status'] : '';
            $pagamento = isset($_GET['pagamento']) ? (string) $_GET['pagamento'] : '';
            $filterByMonth = isset($_GET['year'], $_GET['month']);
            $year = $filterByMonth ? (int) $_GET['year'] : null;
            $month = $filterByMonth ? (int) $_GET['month'] : null;
            $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;
            if (!in_array($per_page, [5, 10, 25, 50, 100], true)) {
                $per_page = 10;
            }
            $sortKey = isset($_GET['sort']) ? trim((string) $_GET['sort']) : 'vencimento';
            $dir = (isset($_GET['dir']) && strtoupper(trim((string) $_GET['dir'])) === 'ASC') ? 'ASC' : 'DESC';

            $cacheKey = 'df_list_' . md5(json_encode([
                (int) $_SESSION['empresa_id'],
                $search, $veiculo, $tipo, $status, $pagamento, $filterByMonth, $year, $month,
                $page, $per_page, $sortKey, $dir,
            ]));
            $cached = sf_file_cache_get($cacheKey, 60);
            if (is_array($cached)) {
                echo json_encode($cached);
                break;
            }

            $payload = despesas_fixas_fetch_list($conn, (int) $_SESSION['empresa_id'], [
                'search' => $search,
                'veiculo' => $veiculo,
                'tipo' => $tipo,
                'status' => $status,
                'pagamento' => $pagamento,
                'filter_by_month' => $filterByMonth,
                'year' => $year,
                'month' => $month,
                'page' => $page,
                'per_page' => $per_page,
                'sort' => $sortKey,
                'dir' => $dir,
            ]);
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
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                throw new Exception('Método não permitido');
            }
            api_require_csrf_json();
            $deleteId = $_POST['id'] ?? null;
            if ($deleteId === null || $deleteId === '') {
                throw new Exception('ID não fornecido');
            }
            
            $sql = "DELETE FROM despesas_fixas WHERE id = :id AND empresa_id = :empresa_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':id', $deleteId);
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
    echo json_encode(['success' => false, 'message' => 'Não foi possível processar a solicitação.']);
}
