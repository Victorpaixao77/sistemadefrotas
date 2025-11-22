<?php
// API de dados dos motoristas

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

// Verifica ID específico do motorista
$motoristId = isset($_GET['id']) ? $_GET['id'] : null;

// Verifica o parâmetro de ação
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Obtém o empresa_id da sessão
$empresa_id = isset($_SESSION["empresa_id"]) ? $_SESSION["empresa_id"] : null;

// Trata diferentes ações
switch ($action) {
    case 'view':
        if ($motoristId) {
            echo json_encode(getMotoristById($motoristId));
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'ID do motorista é obrigatório']);
        }
        break;
        
    case 'get_contract_types':
        echo json_encode(getContractTypes());
        break;
        
    case 'get_availability_status':
        echo json_encode(getAvailabilityStatus());
        break;
        
    case 'get_cnh_categories':
        echo json_encode(getCNHCategories());
        break;
        
    case 'list':
        // Get optional parameters
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        $search = isset($_GET['search']) ? $_GET['search'] : null;
        $name = isset($_GET['name']) ? $_GET['name'] : null;
        if ($search === null && $name !== null) {
            $search = $name;
        }
        
        // Return list of motorists
        echo json_encode(getMotoristsList($limit, $page, $status, $search));
        break;
        
    case 'routes':
        // Return route history for a motorist
        if ($motoristId) {
            echo json_encode(getMotoristRouteHistory($motoristId));
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'ID do motorista é obrigatório']);
        }
        break;
        
    case 'metrics':
        // Return performance metrics for a motorist
        if ($motoristId) {
            echo json_encode(getMotoristMetrics($motoristId));
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'ID do motorista é obrigatório']);
        }
        break;
        
    case 'documents':
        // Return document status for a motorist
        if ($motoristId) {
            echo json_encode(getMotoristDocuments($motoristId));
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'ID do motorista é obrigatório']);
        }
        break;
        
    case 'commission_summary':
        // Return commission summary for all motorists
        echo json_encode(getCommissionSummary());
        break;
        
    case 'add':
        $data = $_POST;
        echo json_encode(addMotorist($data));
        break;
        
    case 'update':
        if (!$motoristId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID do motorista é obrigatório']);
            break;
        }
        $data = $_POST;
        echo json_encode(updateMotorist($motoristId, $data));
        break;

    case 'delete':
        if (!$motoristId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID do motorista é obrigatório']);
            break;
        }
        echo json_encode(deleteMotorist($motoristId));
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Ação inválida']);
        break;
}

/**
 * Get list of motorists with filters
 * 
 * @param int $limit Maximum number of records
 * @param int $page Page number
 * @param string $status Filter by status
 * @param string $search Text search across multiple fields
 * @return array List of motorists and pagination info
 */
function getMotoristsList($limit = 5, $page = 1, $status = null, $search = null) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        
        // Calculate offset
        $offset = ($page - 1) * $limit;
        
        // Build WHERE clause
        $where = ['m.empresa_id = :empresa_id'];
        $params = [':empresa_id' => $empresa_id];
        
        if ($status) {
            $where[] = 'm.disponibilidade_id = :status';
            $params[':status'] = $status;
        }
        
        if ($search) {
            $search = trim($search);
            $searchLike = '%' . $search . '%';
            
            $searchConditions = [];
            $params[':search_nome'] = $searchLike;
            $searchConditions[] = 'm.nome LIKE :search_nome';
            
            $params[':search_email'] = $searchLike;
            $searchConditions[] = 'm.email LIKE :search_email';
            
            $params[':search_cnh'] = $searchLike;
            $searchConditions[] = 'm.cnh LIKE :search_cnh';
            
            $params[':search_cpf_mask'] = $searchLike;
            $searchConditions[] = 'm.cpf LIKE :search_cpf_mask';
            
            $params[':search_phone_mask'] = $searchLike;
            $searchConditions[] = 'm.telefone LIKE :search_phone_mask';
            
            $searchDigits = preg_replace('/\D+/', '', $search);
            if ($searchDigits !== '') {
                $params[':search_cpf_digits'] = '%' . $searchDigits . '%';
                $searchConditions[] = "REPLACE(REPLACE(REPLACE(REPLACE(m.cpf, '.', ''), '-', ''), ' ', ''), '/', '') LIKE :search_cpf_digits";
                
                $params[':search_phone_digits'] = '%' . $searchDigits . '%';
                $searchConditions[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(m.telefone, '(', ''), ')', ''), '-', ''), ' ', ''), '+', '') LIKE :search_phone_digits";
            }
            
            if (!empty($searchConditions)) {
                $where[] = '(' . implode(' OR ', $searchConditions) . ')';
            }
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Get total count
        $sql_count = "SELECT COUNT(*) as total FROM motoristas m WHERE $whereClause";
        $stmt_count = $conn->prepare($sql_count);
        foreach ($params as $key => $value) {
            $stmt_count->bindValue($key, $value);
        }
        $stmt_count->execute();
        $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get motorists with pagination
        $sql = "SELECT m.*, 
                dm.nome as disponibilidade_nome,
                tc.nome as tipo_contrato_nome,
                cc.nome as categoria_cnh_nome
                FROM motoristas m 
                LEFT JOIN disponibilidades_motoristas dm ON m.disponibilidade_id = dm.id 
                LEFT JOIN tipos_contrato tc ON m.tipo_contrato_id = tc.id 
                LEFT JOIN categorias_cnh cc ON m.categoria_cnh_id = cc.id 
                WHERE $whereClause
                ORDER BY m.nome ASC
                LIMIT :limit OFFSET :offset";
                
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $motorists = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get summary data
        $sql_summary = "SELECT 
            COUNT(*) as total_motorists,
            SUM(CASE WHEN disponibilidade_id = 1 THEN 1 ELSE 0 END) as motorists_ativos,
            SUM(CASE WHEN disponibilidade_id = 2 THEN 1 ELSE 0 END) as motorists_ferias,
            SUM(CASE WHEN disponibilidade_id = 3 THEN 1 ELSE 0 END) as motorists_licenca,
            SUM(CASE WHEN disponibilidade_id = 4 THEN 1 ELSE 0 END) as motorists_inativos,
            SUM(CASE WHEN disponibilidade_id = 5 THEN 1 ELSE 0 END) as motorists_afastados
            FROM motoristas 
            WHERE empresa_id = :empresa_id";
            
        $stmt_summary = $conn->prepare($sql_summary);
        $stmt_summary->bindValue(':empresa_id', $empresa_id);
        $stmt_summary->execute();
        $summary = $stmt_summary->fetch(PDO::FETCH_ASSOC);
        
        // Get commission data from ROTAS table (not motoristas)
        $sql_commission = "SELECT 
            SUM(CASE WHEN r.comissao IS NOT NULL AND r.comissao > 0 THEN r.comissao ELSE 0 END) as total_comissao_mes,
            COUNT(CASE WHEN r.comissao IS NOT NULL AND r.comissao > 0 THEN 1 END) as rotas_com_comissao_mes,
            COUNT(DISTINCT r.motorista_id) as motorists_com_comissao_mes
            FROM rotas r 
            INNER JOIN motoristas m ON r.motorista_id = m.id 
            WHERE r.empresa_id = :empresa_id 
            AND r.status = 'aprovado'
            AND MONTH(r.data_rota) = MONTH(CURRENT_DATE())
            AND YEAR(r.data_rota) = YEAR(CURRENT_DATE())";
            
        $stmt_commission = $conn->prepare($sql_commission);
        $stmt_commission->bindValue(':empresa_id', $empresa_id);
        $stmt_commission->execute();
        $commission = $stmt_commission->fetch(PDO::FETCH_ASSOC);
        
        // Merge summary and commission data
        $summary = array_merge($summary, $commission);
        
        return [
            'success' => true,
            'motorists' => $motorists,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ],
            'summary' => $summary
        ];
        
    } catch(PDOException $e) {
        error_log("Error in getMotoristsList: " . $e->getMessage());
        http_response_code(500);
        return ['success' => false, 'error' => 'Erro ao buscar lista de motoristas'];
    }
}

/**
 * Get motorist route history
 * 
 * @param int $motoristId Motorist ID
 * @return array Route history data
 */
function getMotoristRouteHistory($motoristId) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        
        // Verificar se o motorista existe
        $check_sql = "SELECT id FROM motoristas WHERE id = :motorista_id AND empresa_id = :empresa_id";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bindValue(':motorista_id', $motoristId);
        $check_stmt->bindValue(':empresa_id', $empresa_id);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() === 0) {
            return [
                'success' => false,
                'error' => 'Motorista não encontrado'
            ];
        }
        
        // Buscar rotas do motorista
        $sql = "SELECT 
                r.data_rota as data,
                r.origem,
                r.destino,
                v.placa as veiculo,
                CASE WHEN r.km_chegada > r.km_saida THEN (r.km_chegada - r.km_saida) ELSE 0 END as km_percorrido,
                COALESCE(s.nome, 'Não definido') as status
            FROM rotas r
            LEFT JOIN veiculos v ON r.veiculo_id = v.id
            LEFT JOIN status_rotas s ON r.status_id = s.id
            WHERE r.motorista_id = :motorista_id 
            AND r.empresa_id = :empresa_id
            ORDER BY r.data_rota DESC
            LIMIT 10";
            
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':motorista_id', $motoristId);
        $stmt->bindValue(':empresa_id', $empresa_id);
        $stmt->execute();
        
        $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatar os dados para exibição
        foreach ($routes as &$route) {
            $route['km_percorrido'] = number_format($route['km_percorrido'], 0, ',', '.');
            $route['data'] = date('d/m/Y', strtotime($route['data']));
            // Garantir que campos vazios mostrem '-'
            $route['origem'] = $route['origem'] ?: '-';
            $route['destino'] = $route['destino'] ?: '-';
            $route['veiculo'] = $route['veiculo'] ?: '-';
        }
        
        // Log para debug
        error_log("Rotas encontradas para motorista $motoristId: " . count($routes));
        
        return [
            'success' => true,
            'routes' => $routes
        ];
        
    } catch(PDOException $e) {
        error_log("Error in getMotoristRouteHistory: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Erro ao buscar histórico de rotas: ' . $e->getMessage()
        ];
    }
}

/**
 * Get motorist performance metrics
 * 
 * @param int $motoristId Motorist ID
 * @return array Performance metrics data
 */
function getMotoristMetrics($motoristId) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        
        // Get overall metrics
        $sql = "SELECT 
                COUNT(r.id) as total_trips,
                SUM(CASE WHEN r.no_prazo = 1 THEN 1 ELSE 0 END) as rotas_no_prazo,
                SUM(CASE WHEN r.km_chegada > r.km_saida THEN (r.km_chegada - r.km_saida) ELSE 0 END) as total_distance,
                COALESCE(SUM(r.frete), 0) as total_faturamento,
                COALESCE(SUM(r.comissao), 0) as total_comissao
            FROM rotas r
            WHERE r.motorista_id = :motorista_id 
            AND r.empresa_id = :empresa_id";
            
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':motorista_id', $motoristId);
        $stmt->bindValue(':empresa_id', $empresa_id);
        $stmt->execute();
        
        $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calcular avaliação média baseada em métricas
        $total_trips = intval($metrics['total_trips']);
        $pontualidade = ($total_trips > 0) ? ($metrics['rotas_no_prazo'] / $total_trips) * 100 : 0;
        $rentabilidade = ($metrics['total_faturamento'] > 0) ? 
            (($metrics['total_faturamento'] - $metrics['total_comissao']) / $metrics['total_faturamento']) * 100 : 0;
        
        // Calcular avaliação média como média ponderada de pontualidade e rentabilidade
        $average_rating = ($pontualidade * 0.6 + $rentabilidade * 0.4) / 10; // Dividido por 10 para ficar na escala 0-10
        
        // Get monthly metrics for the last 6 months
        $sql_monthly = "SELECT 
                DATE_FORMAT(r.data_rota, '%Y-%m') as month,
                COUNT(r.id) as trips,
                SUM(CASE WHEN r.no_prazo = 1 THEN 1 ELSE 0 END) as rotas_no_prazo,
                COALESCE(SUM(r.frete), 0) as total_faturamento,
                COALESCE(SUM(r.comissao), 0) as total_comissao
            FROM rotas r
            WHERE r.motorista_id = :motorista_id 
            AND r.empresa_id = :empresa_id
            AND r.data_rota >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(r.data_rota, '%Y-%m')
            ORDER BY month DESC";
            
        $stmt_monthly = $conn->prepare($sql_monthly);
        $stmt_monthly->bindValue(':motorista_id', $motoristId);
        $stmt_monthly->bindValue(':empresa_id', $empresa_id);
        $stmt_monthly->execute();
        
        $monthly_data = $stmt_monthly->fetchAll(PDO::FETCH_ASSOC);
        
        // Processar dados mensais para incluir avaliação
        $monthly_metrics = array_map(function($item) {
            $total_trips = intval($item['trips']);
            $pontualidade = ($total_trips > 0) ? ($item['rotas_no_prazo'] / $total_trips) * 100 : 0;
            $rentabilidade = ($item['total_faturamento'] > 0) ? 
                (($item['total_faturamento'] - $item['total_comissao']) / $item['total_faturamento']) * 100 : 0;
            
            $rating = ($pontualidade * 0.6 + $rentabilidade * 0.4) / 10;
            
            return [
                'month' => $item['month'],
                'trips' => $total_trips,
                'rating' => round($rating, 1)
            ];
        }, $monthly_data);
        
        return [
            'success' => true,
            'average_rating' => round($average_rating, 1),
            'total_trips' => $total_trips,
            'total_distance' => floatval($metrics['total_distance']),
            'average_consumption' => 0, // Não temos dados de consumo no momento
            'monthly_metrics' => $monthly_metrics
        ];
        
    } catch(PDOException $e) {
        error_log("Error in getMotoristMetrics: " . $e->getMessage());
        http_response_code(500);
        return ['success' => false, 'error' => 'Erro ao buscar métricas de desempenho'];
    }
}

/**
 * Get motorist document status
 * 
 * @param int $motoristId Motorist ID
 * @return array Document status data
 */
function getMotoristDocuments($motoristId) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        
        // Get motorist data
        $sql = "SELECT 
                m.cnh,
                m.data_validade_cnh,
                m.data_contratacao,
                m.tipo_contrato_id,
                tc.nome as tipo_contrato_nome
            FROM motoristas m
            LEFT JOIN tipos_contrato tc ON m.tipo_contrato_id = tc.id
            WHERE m.id = :motorista_id 
            AND m.empresa_id = :empresa_id";
            
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':motorista_id', $motoristId);
        $stmt->bindValue(':empresa_id', $empresa_id);
        $stmt->execute();
        
        $motorist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check CNH status
        $cnh_expiry = new DateTime($motorist['data_validade_cnh']);
        $today = new DateTime();
        $days_until_expiry = $today->diff($cnh_expiry)->days;
        
        $cnh_status = 'Válido';
        if ($cnh_expiry < $today) {
            $cnh_status = 'Vencido';
        } elseif ($days_until_expiry <= 30) {
            $cnh_status = 'Próximo ao vencimento';
        }
        
        return [
            'success' => true,
            'cnh' => [
                'number' => $motorist['cnh'],
                'expiry_date' => $motorist['data_validade_cnh'],
                'status' => $cnh_status
            ],
            'contract' => [
                'type' => $motorist['tipo_contrato_nome'],
                'date' => $motorist['data_contratacao'],
                'status' => 'Ativo' // You might want to add contract status logic
            ]
        ];
        
    } catch(PDOException $e) {
        error_log("Error in getMotoristDocuments: " . $e->getMessage());
        http_response_code(500);
        return ['success' => false, 'error' => 'Erro ao buscar status dos documentos'];
    }
}

/**
 * Get list of contract types
 * 
 * @return array List of contract types
 */
function getContractTypes() {
    try {
        $conn = getConnection();
        
        // First check if table exists
        $sql_check = "SHOW TABLES LIKE 'tipos_contrato'";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->execute();
        
        if ($stmt_check->rowCount() === 0) {
            // Create table if it doesn't exist
            $sql_create = "CREATE TABLE IF NOT EXISTS tipos_contrato (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(100) NOT NULL,
                descricao TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            $conn->exec($sql_create);
            
            // Insert default values
            $sql_insert = "INSERT INTO tipos_contrato (nome, descricao) VALUES 
                ('CLT', 'Contrato CLT padrão'),
                ('PJ', 'Pessoa Jurídica'),
                ('Autônomo', 'Prestador de serviços autônomo'),
                ('Temporário', 'Contrato por tempo determinado'),
                ('Terceirizado', 'Funcionário terceirizado')";
            $conn->exec($sql_insert);
        }
        
        // Get all contract types
        $sql = "SELECT id, nome FROM tipos_contrato ORDER BY nome";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log the result for debugging
        error_log("Contract types found: " . count($result));
        
        return [
            'success' => true,
            'types' => $result
        ];
        
    } catch(PDOException $e) {
        error_log("Error in getContractTypes: " . $e->getMessage());
        return ['success' => false, 'error' => 'Erro ao buscar tipos de contrato: ' . $e->getMessage()];
    }
}

/**
 * Get list of availability status
 * 
 * @return array List of availability status
 */
function getAvailabilityStatus() {
    try {
        $conn = getConnection();
        
        // Get all availability status from the correct table
        $sql = "SELECT id, nome FROM disponibilidades_motoristas ORDER BY nome";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log the result for debugging
        error_log("Availability status count: " . count($result));
        
        return [
            'success' => true,
            'status' => $result
        ];
        
    } catch(PDOException $e) {
        error_log("Error in getAvailabilityStatus: " . $e->getMessage());
        return ['success' => false, 'error' => 'Erro ao buscar status de disponibilidade: ' . $e->getMessage()];
    }
}

/**
 * Get list of CNH categories
 * 
 * @return array List of CNH categories
 */
function getCNHCategories() {
    try {
        $conn = getConnection();
        
        $sql = "SELECT id, nome FROM categorias_cnh ORDER BY nome";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        
        return [
            'success' => true,
            'categories' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
        
    } catch(PDOException $e) {
        error_log("Error in getCNHCategories: " . $e->getMessage());
        return ['success' => false, 'error' => 'Erro ao buscar categorias de CNH'];
    }
}

/**
 * Get motorist details by ID
 * 
 * @param int $id Motorist ID
 * @return array Motorist data with success status
 */
function getMotoristById($id) {
    try {
        $conn = getConnection();
        $empresa_id = intval($_SESSION['empresa_id']);
        
        if (!$empresa_id) {
            error_log("Invalid empresa_id in session: " . $_SESSION['empresa_id']);
            return ['success' => false, 'error' => 'ID da empresa inválido'];
        }
        
        $sql = "SELECT m.*,
                dm.nome as disponibilidade_nome,
                tc.nome as tipo_contrato_nome,
                cc.nome as categoria_cnh_nome
                FROM motoristas m
                LEFT JOIN disponibilidades_motoristas dm ON m.disponibilidade_id = dm.id
                LEFT JOIN tipos_contrato tc ON m.tipo_contrato_id = tc.id
                LEFT JOIN categorias_cnh cc ON m.categoria_cnh_id = cc.id
                WHERE m.id = :id AND m.empresa_id = :empresa_id";
                
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $motorist = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$motorist) {
            error_log("Motorist not found. ID: $id, Empresa: $empresa_id");
            return ['success' => false, 'error' => 'Motorista não encontrado'];
        }
        
        return [
            'success' => true,
            'data' => $motorist
        ];
        
    } catch(PDOException $e) {
        error_log("Error in getMotoristById: " . $e->getMessage());
        return ['success' => false, 'error' => 'Erro ao buscar dados do motorista'];
    }
}

/**
 * Add a new motorist
 * 
 * @param array $data Motorist data
 * @return array Response with success status and message
 */
function addMotorist($data) {
    try {
        $conn = getConnection();
        
        // Validate required fields
        if (empty($data['nome']) || empty($data['cpf'])) {
            return ['success' => false, 'error' => 'Nome e CPF são obrigatórios'];
        }
        
        // Get empresa_id from session
        $empresa_id = $_SESSION['empresa_id'];
        
        // Process uploaded files
        $foto_motorista_path = null;
        $cnh_arquivo_path = null;
        $contrato_arquivo_path = null;
        
        if (isset($_FILES['foto_motorista']) && $_FILES['foto_motorista']['error'] === UPLOAD_ERR_OK) {
            $foto_motorista_path = handleFileUpload($_FILES['foto_motorista'], 'foto');
        }
        
        if (isset($_FILES['cnh_arquivo']) && $_FILES['cnh_arquivo']['error'] === UPLOAD_ERR_OK) {
            $cnh_arquivo_path = handleFileUpload($_FILES['cnh_arquivo'], 'cnh');
        }
        
        if (isset($_FILES['contrato_arquivo']) && $_FILES['contrato_arquivo']['error'] === UPLOAD_ERR_OK) {
            $contrato_arquivo_path = handleFileUpload($_FILES['contrato_arquivo'], 'contrato');
        }
        
        $sql = "INSERT INTO motoristas (
            empresa_id,
            nome,
            cpf,
            cnh,
            categoria_cnh_id,
            data_validade_cnh,
            telefone,
            telefone_emergencia,
            email,
            endereco,
            data_contratacao,
            tipo_contrato_id,
            disponibilidade_id,
            porcentagem_comissao,
            observacoes,
            cnh_arquivo,
            contrato_arquivo,
            foto_motorista
        ) VALUES (
            :empresa_id,
            :nome,
            :cpf,
            :cnh,
            :categoria_cnh_id,
            :data_validade_cnh,
            :telefone,
            :telefone_emergencia,
            :email,
            :endereco,
            :data_contratacao,
            :tipo_contrato_id,
            :disponibilidade_id,
            :porcentagem_comissao,
            :observacoes,
            :cnh_arquivo,
            :contrato_arquivo,
            :foto_motorista
        )";
        
        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindValue(':empresa_id', $empresa_id);
        $stmt->bindValue(':nome', $data['nome']);
        $stmt->bindValue(':cpf', $data['cpf']);
        $stmt->bindValue(':cnh', $data['cnh'] ?? null);
        $stmt->bindValue(':categoria_cnh_id', $data['categoria_cnh_id'] ?? null);
        $stmt->bindValue(':data_validade_cnh', $data['data_validade_cnh'] ?? null);
        $stmt->bindValue(':telefone', $data['telefone'] ?? null);
        $stmt->bindValue(':telefone_emergencia', $data['telefone_emergencia'] ?? null);
        $stmt->bindValue(':email', $data['email'] ?? null);
        $stmt->bindValue(':endereco', $data['endereco'] ?? null);
        $stmt->bindValue(':data_contratacao', $data['data_contratacao'] ?? null);
        $stmt->bindValue(':tipo_contrato_id', $data['tipo_contrato_id'] ?? null);
        $stmt->bindValue(':disponibilidade_id', $data['disponibilidade_id'] ?? null);
        $stmt->bindValue(':porcentagem_comissao', $data['porcentagem_comissao'] ?? 10.00);
        $stmt->bindValue(':observacoes', $data['observacoes'] ?? null);
        $stmt->bindValue(':cnh_arquivo', $cnh_arquivo_path);
        $stmt->bindValue(':contrato_arquivo', $contrato_arquivo_path);
        $stmt->bindValue(':foto_motorista', $foto_motorista_path);
        
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Motorista adicionado com sucesso',
            'id' => $conn->lastInsertId()
        ];
        
    } catch(PDOException $e) {
        error_log("Error in addMotorist: " . $e->getMessage());
        return ['success' => false, 'error' => 'Erro ao adicionar motorista: ' . $e->getMessage()];
    }
}

/**
 * Update an existing motorist
 * 
 * @param int $id Motorist ID
 * @param array $data Updated motorist data
 * @return array Response with success status and message
 */
function updateMotorist($id, $data) {
    try {
        $conn = getConnection();
        
        // Validate required fields
        if (empty($data['nome']) || empty($data['cpf'])) {
            return ['success' => false, 'error' => 'Nome e CPF são obrigatórios'];
        }
        
        // Get empresa_id from session
        $empresa_id = $_SESSION['empresa_id'];
        
        // Verify motorist belongs to the company
        $check_sql = "SELECT id, cnh_arquivo, contrato_arquivo, foto_motorista FROM motoristas WHERE id = ? AND empresa_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([$id, $empresa_id]);
        
        if ($check_stmt->rowCount() === 0) {
            return ['success' => false, 'error' => 'Motorista não encontrado'];
        }
        
        $current_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Process uploaded files
        $foto_motorista_path = $current_data['foto_motorista'];
        $cnh_arquivo_path = $current_data['cnh_arquivo'];
        $contrato_arquivo_path = $current_data['contrato_arquivo'];
        
        if (isset($_FILES['foto_motorista']) && $_FILES['foto_motorista']['error'] === UPLOAD_ERR_OK) {
            // Delete old file if exists
            if ($foto_motorista_path && file_exists($foto_motorista_path)) {
                unlink($foto_motorista_path);
            }
            $foto_motorista_path = handleFileUpload($_FILES['foto_motorista'], 'foto');
        }
        
        if (isset($_FILES['cnh_arquivo']) && $_FILES['cnh_arquivo']['error'] === UPLOAD_ERR_OK) {
            // Delete old file if exists
            if ($cnh_arquivo_path && file_exists($cnh_arquivo_path)) {
                unlink($cnh_arquivo_path);
            }
            $cnh_arquivo_path = handleFileUpload($_FILES['cnh_arquivo'], 'cnh');
        }
        
        if (isset($_FILES['contrato_arquivo']) && $_FILES['contrato_arquivo']['error'] === UPLOAD_ERR_OK) {
            // Delete old file if exists
            if ($contrato_arquivo_path && file_exists($contrato_arquivo_path)) {
                unlink($contrato_arquivo_path);
            }
            $contrato_arquivo_path = handleFileUpload($_FILES['contrato_arquivo'], 'contrato');
        }
        
        $sql = "UPDATE motoristas SET
            nome = :nome,
            cpf = :cpf,
            cnh = :cnh,
            categoria_cnh_id = :categoria_cnh_id,
            data_validade_cnh = :data_validade_cnh,
            telefone = :telefone,
            telefone_emergencia = :telefone_emergencia,
            email = :email,
            endereco = :endereco,
            data_contratacao = :data_contratacao,
            tipo_contrato_id = :tipo_contrato_id,
            disponibilidade_id = :disponibilidade_id,
            porcentagem_comissao = :porcentagem_comissao,
            observacoes = :observacoes,
            cnh_arquivo = :cnh_arquivo,
            contrato_arquivo = :contrato_arquivo,
            foto_motorista = :foto_motorista
            WHERE id = :id AND empresa_id = :empresa_id";
        
        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':empresa_id', $empresa_id);
        $stmt->bindValue(':nome', $data['nome']);
        $stmt->bindValue(':cpf', $data['cpf']);
        $stmt->bindValue(':cnh', $data['cnh'] ?? null);
        $stmt->bindValue(':categoria_cnh_id', $data['categoria_cnh_id'] ?? null);
        $stmt->bindValue(':data_validade_cnh', $data['data_validade_cnh'] ?? null);
        $stmt->bindValue(':telefone', $data['telefone'] ?? null);
        $stmt->bindValue(':telefone_emergencia', $data['telefone_emergencia'] ?? null);
        $stmt->bindValue(':email', $data['email'] ?? null);
        $stmt->bindValue(':endereco', $data['endereco'] ?? null);
        $stmt->bindValue(':data_contratacao', $data['data_contratacao'] ?? null);
        $stmt->bindValue(':tipo_contrato_id', $data['tipo_contrato_id'] ?? null);
        $stmt->bindValue(':disponibilidade_id', $data['disponibilidade_id'] ?? null);
        $stmt->bindValue(':porcentagem_comissao', $data['porcentagem_comissao'] ?? 10.00);
        $stmt->bindValue(':observacoes', $data['observacoes'] ?? null);
        $stmt->bindValue(':cnh_arquivo', $cnh_arquivo_path);
        $stmt->bindValue(':contrato_arquivo', $contrato_arquivo_path);
        $stmt->bindValue(':foto_motorista', $foto_motorista_path);
        
        $stmt->execute();
        
        return [
            'success' => true,
            'message' => 'Motorista atualizado com sucesso'
        ];
        
    } catch(PDOException $e) {
        error_log("Error in updateMotorist: " . $e->getMessage());
        return ['success' => false, 'error' => 'Erro ao atualizar motorista: ' . $e->getMessage()];
    }
}

/**
 * Handle file upload
 * 
 * @param array $file File data from $_FILES
 * @param string $type Type of file (cnh or contrato)
 * @return string|null Path to saved file or null if error
 */
function handleFileUpload($file, $type) {
    try {
        // Validate file type
        $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'];
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('Tipo de arquivo não permitido. Apenas PDF, DOC, DOCX, JPG e PNG são aceitos.');
        }
        
        // Create upload directory if it doesn't exist
        $upload_dir = '../uploads/motoristas/' . $type;
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . $type . '.' . $extension;
        $filepath = $upload_dir . '/' . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Erro ao salvar arquivo.');
        }
        
        return $filepath;
        
    } catch (Exception $e) {
        error_log("Error in handleFileUpload: " . $e->getMessage());
        return null;
    }
}

/**
 * Delete a motorist
 * 
 * @param int $id Motorist ID
 * @return array Operation result
 */
function deleteMotorist($id) {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        
        error_log("Attempting to delete motorist ID: $id for empresa_id: $empresa_id");
        
        // First check if the motorist exists and belongs to the company
        $sql_check = "SELECT id, disponibilidade_id FROM motoristas WHERE id = :id AND empresa_id = :empresa_id";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bindValue(':id', $id);
        $stmt_check->bindValue(':empresa_id', $empresa_id);
        $stmt_check->execute();
        
        $motorist = $stmt_check->fetch(PDO::FETCH_ASSOC);
        if (!$motorist) {
            error_log("Motorist not found or doesn't belong to company. ID: $id, Empresa: $empresa_id");
            return ['success' => false, 'error' => 'Motorista não encontrado'];
        }
        
        // Check if motorist is active or on a trip
        if (in_array($motorist['disponibilidade_id'], [1, 7])) { // 1 = Ativo, 7 = Em Viagem
            error_log("Cannot delete motorist ID: $id - Motorist is active or on trip");
            return ['success' => false, 'error' => 'Não é possível excluir motorista que está ativo ou em viagem'];
        }
        
        // Start transaction
        $conn->beginTransaction();
        
        try {
            // Delete the motorist
            $sql_delete = "DELETE FROM motoristas WHERE id = :id AND empresa_id = :empresa_id";
            $stmt_delete = $conn->prepare($sql_delete);
            $stmt_delete->bindValue(':id', $id);
            $stmt_delete->bindValue(':empresa_id', $empresa_id);
            $stmt_delete->execute();
            
            if ($stmt_delete->rowCount() > 0) {
                $conn->commit();
                error_log("Successfully deleted motorist ID: $id");
                return ['success' => true, 'message' => 'Motorista excluído com sucesso'];
            } else {
                $conn->rollBack();
                error_log("Failed to delete motorist ID: $id - No rows affected");
                return ['success' => false, 'error' => 'Erro ao excluir motorista'];
            }
            
        } catch (PDOException $e) {
            $conn->rollBack();
            throw $e;
        }
        
    } catch(PDOException $e) {
        error_log("Database error in deleteMotorist: " . $e->getMessage());
        return ['success' => false, 'error' => 'Erro ao excluir motorista: ' . $e->getMessage()];
    } catch(Exception $e) {
        error_log("General error in deleteMotorist: " . $e->getMessage());
        return ['success' => false, 'error' => 'Erro ao excluir motorista'];
    }
}

/**
 * Get commission summary for all motorists
 * 
 * @return array Commission summary data
 */
function getCommissionSummary() {
    try {
        $conn = getConnection();
        $empresa_id = $_SESSION['empresa_id'];
        
        // Get commission data with financial calculations
        $sql = "SELECT 
            COUNT(*) as total_motorists,
            SUM(CASE WHEN porcentagem_comissao IS NOT NULL THEN porcentagem_comissao ELSE 0 END) as total_comissao_percentual,
            COUNT(CASE WHEN porcentagem_comissao IS NOT NULL THEN 1 END) as motorists_com_comissao,
            AVG(CASE WHEN porcentagem_comissao IS NOT NULL THEN porcentagem_comissao ELSE 0 END) as media_comissao,
            MIN(CASE WHEN porcentagem_comissao IS NOT NULL THEN porcentagem_comissao ELSE 0 END) as min_comissao,
            MAX(CASE WHEN porcentagem_comissao IS NOT NULL THEN porcentagem_comissao ELSE 0 END) as max_comissao,
            SUM(CASE WHEN disponibilidade_id = 1 AND porcentagem_comissao IS NOT NULL THEN porcentagem_comissao ELSE 0 END) as comissao_motorists_ativos,
            COUNT(CASE WHEN disponibilidade_id = 1 AND porcentagem_comissao IS NOT NULL THEN 1 END) as total_ativos_com_comissao
            FROM motoristas 
            WHERE empresa_id = :empresa_id";
            
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':empresa_id', $empresa_id);
        $stmt->execute();
        $commission_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get distribution by commission ranges
        $sql_ranges = "SELECT 
            CASE 
                WHEN porcentagem_comissao < 5 THEN '0-5%'
                WHEN porcentagem_comissao < 10 THEN '5-10%'
                WHEN porcentagem_comissao < 15 THEN '10-15%'
                WHEN porcentagem_comissao < 20 THEN '15-20%'
                ELSE '20%+'
            END as range_comissao,
            COUNT(*) as quantidade
            FROM motoristas 
            WHERE empresa_id = :empresa_id AND porcentagem_comissao IS NOT NULL
            GROUP BY 
                CASE 
                    WHEN porcentagem_comissao < 5 THEN '0-5%'
                    WHEN porcentagem_comissao < 10 THEN '5-10%'
                    WHEN porcentagem_comissao < 15 THEN '10-15%'
                    WHEN porcentagem_comissao < 20 THEN '15-20%'
                    ELSE '20%+'
                END
            ORDER BY 
                CASE 
                    WHEN porcentagem_comissao < 5 THEN 1
                    WHEN porcentagem_comissao < 10 THEN 2
                    WHEN porcentagem_comissao < 15 THEN 3
                    WHEN porcentagem_comissao < 20 THEN 4
                    ELSE 5
                END";
                
        $stmt_ranges = $conn->prepare($sql_ranges);
        $stmt_ranges->bindValue(':empresa_id', $empresa_id);
        $stmt_ranges->execute();
        $commission_ranges = $stmt_ranges->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => $commission_data,
            'ranges' => $commission_ranges
        ];
        
    } catch(PDOException $e) {
        error_log("Error in getCommissionSummary: " . $e->getMessage());
        http_response_code(500);
        return ['success' => false, 'error' => 'Erro ao buscar resumo de comissões'];
    }
}
