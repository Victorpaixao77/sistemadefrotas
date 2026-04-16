<?php
// API de dados das rotas

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

if (!$empresa_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'ID da empresa não encontrado'
    ]);
    exit;
}

// Verifica o parâmetro de ação
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    $conn = getConnection();
    
    switch ($action) {
        case 'list':
            // Parâmetros de paginação (por página: 5, 10, 25, 50, 100; padrão 10)
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            if (!in_array($limit, [5, 10, 25, 50, 100], true)) {
                $limit = 10;
            }
            $offset = ($page - 1) * $limit;
            
            // Count total records first
            $count_sql = "SELECT COUNT(*) as total 
                        FROM rotas r
                        LEFT JOIN motoristas m ON r.motorista_id = m.id
                        LEFT JOIN veiculos v ON r.veiculo_id = v.id
                        LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                        LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                        WHERE r.empresa_id = :empresa_id
                        AND r.status = 'aprovado'";
            
            // Prepara a consulta base
            $sql = "SELECT 
                    r.*,
                    m.nome as motorista_nome,
                    v.placa as veiculo_placa,
                    co.nome as cidade_origem_nome,
                    cd.nome as cidade_destino_nome
                FROM rotas r
                LEFT JOIN motoristas m ON r.motorista_id = m.id
                LEFT JOIN veiculos v ON r.veiculo_id = v.id
                LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                WHERE r.empresa_id = :empresa_id
                AND r.status = 'aprovado'";
            
            // Adiciona filtros se fornecidos
            $params = [':empresa_id' => $empresa_id];
            
            if (!empty($_GET['search'])) {
                $rawSearch = trim($_GET['search']);
                $normalizedSearch = function_exists('mb_strtolower')
                    ? mb_strtolower($rawSearch, 'UTF-8')
                    : strtolower($rawSearch);
                $searchConditions = [];

                $searchLike = '%' . $normalizedSearch . '%';

                $params[':search_origin'] = $searchLike;
                $searchConditions[] = 'LOWER(COALESCE(co.nome, "")) LIKE :search_origin';

                $params[':search_destination'] = $searchLike;
                $searchConditions[] = 'LOWER(COALESCE(cd.nome, "")) LIKE :search_destination';

                $params[':search_driver_name'] = $searchLike;
                $searchConditions[] = 'LOWER(COALESCE(m.nome, "")) LIKE :search_driver_name';

                $params[':search_vehicle_plate'] = $searchLike;
                $searchConditions[] = 'LOWER(COALESCE(v.placa, "")) LIKE :search_vehicle_plate';

                $params[':search_vehicle_model'] = $searchLike;
                $searchConditions[] = 'LOWER(COALESCE(v.modelo, "")) LIKE :search_vehicle_model';

                $params[':search_combined_route'] = $searchLike;
                $searchConditions[] = 'LOWER(CONCAT_WS(" ", COALESCE(co.nome, ""), COALESCE(cd.nome, ""))) LIKE :search_combined_route';

                $rawLike = '%' . $rawSearch . '%';
                $params[':search_route_id'] = $rawLike;
                $searchConditions[] = 'CAST(r.id AS CHAR) LIKE :search_route_id';

                $params[':search_driver_cpf_mask'] = $rawLike;
                $searchConditions[] = 'm.cpf LIKE :search_driver_cpf_mask';

                $params[':search_date_br'] = $rawLike;
                $searchConditions[] = "DATE_FORMAT(r.data_rota, '%d/%m/%Y') LIKE :search_date_br";

                $params[':search_date_iso'] = $rawLike;
                $searchConditions[] = "DATE_FORMAT(r.data_rota, '%Y-%m-%d') LIKE :search_date_iso";

                $searchDigits = preg_replace('/\D+/', '', $rawSearch);
                if ($searchDigits !== '') {
                    $digitsLike = '%' . $searchDigits . '%';

                    $params[':search_driver_cpf_digits'] = $digitsLike;
                    $searchConditions[] = "REPLACE(REPLACE(REPLACE(m.cpf, '.', ''), '-', ''), ' ', '') LIKE :search_driver_cpf_digits";

                    $params[':search_plate_digits'] = $digitsLike;
                    $searchConditions[] = "REPLACE(REPLACE(REPLACE(REPLACE(v.placa, '-', ''), ' ', ''), '.', ''), '/', '') LIKE :search_plate_digits";

                    $params[':search_route_id_digits'] = $digitsLike;
                    $searchConditions[] = 'CAST(r.id AS CHAR) LIKE :search_route_id_digits';
                }

                if (!empty($searchConditions)) {
                    $searchClause = '(' . implode(' OR ', $searchConditions) . ')';
                    $sql .= " AND $searchClause";
                    $count_sql .= " AND $searchClause";
                }
            }
            
            // Filtro por entrega no prazo / atrasado (parâmetro delivery; status legado)
            $delivery = isset($_GET['delivery']) ? trim((string) $_GET['delivery']) : '';
            if ($delivery === '' && !empty($_GET['status'])) {
                $legacy = trim((string) $_GET['status']);
                if ($legacy === 'no_prazo') {
                    $delivery = 'no_prazo';
                } elseif ($legacy === 'atrasado') {
                    $delivery = 'atrasado';
                }
            }
            if ($delivery === 'no_prazo') {
                $sql .= ' AND r.no_prazo = 1';
                $count_sql .= ' AND r.no_prazo = 1';
            } elseif ($delivery === 'atrasado') {
                $sql .= ' AND r.no_prazo = 0';
                $count_sql .= ' AND r.no_prazo = 0';
            }
            
            if (!empty($_GET['driver'])) {
                $sql .= " AND r.motorista_id = :driver";
                $count_sql .= " AND r.motorista_id = :driver";
                $params[':driver'] = $_GET['driver'];
            }
            
            if (!empty($_GET['date'])) {
                $sql .= " AND DATE(r.data_rota) = :date";
                $count_sql .= " AND DATE(r.data_rota) = :date";
                $params[':date'] = $_GET['date'];
            }
            
            if (!empty($_GET['date_from'])) {
                $sql .= " AND DATE(r.data_rota) >= :date_from";
                $count_sql .= " AND DATE(r.data_rota) >= :date_from";
                $params[':date_from'] = $_GET['date_from'];
            }
            
            if (!empty($_GET['date_to'])) {
                $sql .= " AND DATE(r.data_rota) <= :date_to";
                $count_sql .= " AND DATE(r.data_rota) <= :date_to";
                $params[':date_to'] = $_GET['date_to'];
            }
            
            // Get total count
            $stmt_count = $conn->prepare($count_sql);
            foreach ($params as $key => &$val) {
                $stmt_count->bindValue($key, $val);
            }
            $stmt_count->execute();
            $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
            
            $sortKey = isset($_GET['sort']) ? trim((string) $_GET['sort']) : 'data_rota';
            $allowedSort = [
                'data_rota' => 'r.data_rota',
                'motorista_nome' => 'm.nome',
                'veiculo_placa' => 'v.placa',
                'rota' => 'CONCAT(COALESCE(co.nome, ""), " ", COALESCE(cd.nome, ""))',
                'distancia_km' => 'r.distancia_km',
                'frete' => 'r.frete',
                'id' => 'r.id',
                'data_saida' => 'r.data_saida',
                'data_chegada' => 'r.data_chegada',
                'km_saida' => 'r.km_saida',
                'km_chegada' => 'r.km_chegada',
                'km_vazio' => 'r.km_vazio',
                'total_km' => 'r.total_km',
                'comissao' => 'r.comissao',
                'eficiencia_viagem' => 'r.eficiencia_viagem',
                'percentual_vazio' => 'r.percentual_vazio',
                'peso_carga' => 'r.peso_carga',
                'descricao_carga' => 'r.descricao_carga',
                'no_prazo' => 'r.no_prazo',
            ];
            $orderCol = $allowedSort[$sortKey] ?? 'r.data_rota';
            $dir = (isset($_GET['dir']) && strtoupper(trim((string) $_GET['dir'])) === 'ASC') ? 'ASC' : 'DESC';

            $sql .= ' ORDER BY ' . $orderCol . ' ' . $dir . ', r.id DESC LIMIT :limit OFFSET :offset';
            
            $stmt = $conn->prepare($sql);
            foreach ($params as $key => &$val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        case 'view':
            $id = isset($_GET['id']) ? $_GET['id'] : null;
            if (!$id) {
                throw new Exception('ID da rota não fornecido');
            }
            
            $sql = "SELECT 
                    r.*,
                    m.nome as motorista_nome,
                    v.placa as veiculo_placa,
                    co.nome as cidade_origem_nome,
                    cd.nome as cidade_destino_nome
                FROM rotas r
                LEFT JOIN motoristas m ON r.motorista_id = m.id
                LEFT JOIN veiculos v ON r.veiculo_id = v.id
                LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                WHERE r.id = :id AND r.empresa_id = :empresa_id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':id', $id);
            $stmt->bindValue(':empresa_id', $empresa_id);
            $stmt->execute();
            
            $route = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$route) {
                throw new Exception('Rota não encontrada');
            }
            
            echo json_encode([
                'success' => true,
                'data' => $route
            ]);
            break;
            
        case 'summary':
            require_once __DIR__ . '/../includes/cache.php';
            $cache = new CacheManager(__DIR__ . '/../cache/');
            $cacheKey = 'route_summary_' . $empresa_id;
            if (!empty($_GET['date_from']) && !empty($_GET['date_to'])) {
                $cacheKey .= '_d_' . $_GET['date_from'] . '_' . $_GET['date_to'];
            } else {
                $cacheKey .= '_30d';
            }
            $result = $cache->get($cacheKey);
            if ($result === null) {
                $sql = "SELECT 
                    COUNT(*) as total_routes,
                    SUM(CASE WHEN no_prazo = 1 THEN 1 ELSE 0 END) as rotas_no_prazo,
                    SUM(CASE WHEN no_prazo = 0 THEN 1 ELSE 0 END) as rotas_atrasadas,
                    SUM(distancia_km) as total_distance,
                    SUM(frete) as total_frete,
                    AVG(NULLIF(eficiencia_viagem, 0)) as media_eficiencia,
                    AVG(NULLIF(percentual_vazio, 0)) as media_percentual_vazio
                FROM rotas 
                WHERE empresa_id = :empresa_id
                AND status = 'aprovado'";
                $params_summary = [':empresa_id' => $empresa_id];
                if (!empty($_GET['date_from']) && !empty($_GET['date_to'])) {
                    $sql .= " AND DATE(data_saida) >= :date_from AND DATE(data_saida) <= :date_to";
                    $params_summary[':date_from'] = $_GET['date_from'];
                    $params_summary[':date_to'] = $_GET['date_to'];
                } else {
                    $sql .= " AND data_saida >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)";
                }
                $stmt = $conn->prepare($sql);
                foreach ($params_summary as $key => $val) {
                    $stmt->bindValue($key, $val);
                }
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $cache->set($cacheKey, $result, 120);
            }
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;
            
        case 'active':
            $sql = "SELECT 
                    r.*,
                    m.nome as motorista_nome,
                    v.placa as veiculo_placa,
                    co.nome as cidade_origem_nome,
                    cd.nome as cidade_destino_nome
                FROM rotas r
                LEFT JOIN motoristas m ON r.motorista_id = m.id
                LEFT JOIN veiculos v ON r.veiculo_id = v.id
                LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                WHERE r.empresa_id = :empresa_id 
                AND r.data_chegada > NOW()
                AND r.status = 'aprovado'
                ORDER BY r.data_saida ASC
                LIMIT 5";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':empresa_id', $empresa_id);
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
            break;
            
        case 'get_expenses':
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'ID da rota não fornecido']);
                exit;
            }
            
            try {
                $query = "SELECT * FROM despesas_viagem WHERE rota_id = :rota_id AND empresa_id = :empresa_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':rota_id', $id, PDO::PARAM_INT);
                $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
                $stmt->execute();
                
                $expenses = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'expenses' => $expenses
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Erro ao buscar despesas: ' . $e->getMessage()
                ]);
            }
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
} catch (Exception $e) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log("Erro na API de dados de rotas: " . $e->getMessage());
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => DEBUG_MODE ? $e->getMessage() : 'Erro ao processar a requisição'
    ]);
}
