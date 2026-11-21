<?php
// API de dados das rotas

// Inclui arquivos de configuração e funções primeiro
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/routes_list.php';

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
if (function_exists('session_write_close')) { session_write_close(); }

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
            $delivery = isset($_GET['delivery']) ? trim((string) $_GET['delivery']) : '';
            if ($delivery === '' && !empty($_GET['status'])) {
                $legacy = trim((string) $_GET['status']);
                if ($legacy === 'no_prazo') {
                    $delivery = 'no_prazo';
                } elseif ($legacy === 'atrasado') {
                    $delivery = 'atrasado';
                }
            }
            $result = routes_fetch_list($conn, (int) $empresa_id, [
                'page' => isset($_GET['page']) ? (int) $_GET['page'] : 1,
                'limit' => isset($_GET['limit']) ? (int) $_GET['limit'] : 10,
                'search' => $_GET['search'] ?? '',
                'delivery' => $delivery,
                'driver' => $_GET['driver'] ?? '',
                'date' => $_GET['date'] ?? '',
                'date_from' => $_GET['date_from'] ?? '',
                'date_to' => $_GET['date_to'] ?? '',
                'sort' => $_GET['sort'] ?? 'data_rota',
                'dir' => $_GET['dir'] ?? 'DESC',
            ]);
            echo json_encode($result);
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
                    'error' => 'Erro ao buscar despesas: '
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
