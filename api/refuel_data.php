<?php
// Refuel data API

require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Ensure the request is authenticated
require_authentication();

// Get empresa_id from session
$empresa_id = isset($_SESSION["empresa_id"]) ? $_SESSION["empresa_id"] : null;

if (!$empresa_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'ID da empresa não encontrado'
    ]);
    exit;
}

// Check for specific refuel ID
$refuelId = isset($_GET['id']) ? $_GET['id'] : null;

// Check for action parameter
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

try {
    $conn = getConnection();
    
    switch ($action) {
        case 'list':
            // Parâmetros de paginação
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
            $offset = ($page - 1) * $limit;

            // Prepara a consulta base para contar o total de registros
            $sql_count = "SELECT COUNT(*) as total
                FROM abastecimentos a
                LEFT JOIN veiculos v ON a.veiculo_id = v.id
                LEFT JOIN motoristas m ON a.motorista_id = m.id
                WHERE a.empresa_id = :empresa_id AND a.status = 'aprovado'";
            
            // Prepara a consulta base para buscar os registros
            $sql = "SELECT 
                    a.*,
                    v.placa as veiculo_placa,
                    m.nome as motorista_nome,
                    co.nome as cidade_origem_nome,
                    cd.nome as cidade_destino_nome
                FROM abastecimentos a
                LEFT JOIN veiculos v ON a.veiculo_id = v.id
                LEFT JOIN motoristas m ON a.motorista_id = m.id
                LEFT JOIN rotas r ON a.rota_id = r.id
                LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                WHERE a.empresa_id = :empresa_id AND a.status = 'aprovado'";
            
            // Adiciona filtros se fornecidos
            $params = [':empresa_id' => $empresa_id];
            
            if (!empty($_GET['search'])) {
                $search = '%' . $_GET['search'] . '%';
                $sql .= " AND (v.placa LIKE :search OR m.nome LIKE :search OR a.posto LIKE :search)";
                $params[':search'] = $search;
                $sql_count .= " AND (v.placa LIKE :search OR m.nome LIKE :search OR a.posto LIKE :search)";
            }
            
            if (!empty($_GET['veiculo'])) {
                $sql .= " AND a.veiculo_id = :veiculo";
                $params[':veiculo'] = $_GET['veiculo'];
                $sql_count .= " AND a.veiculo_id = :veiculo";
            }
            
            if (!empty($_GET['motorista'])) {
                $sql .= " AND a.motorista_id = :motorista";
                $params[':motorista'] = $_GET['motorista'];
                $sql_count .= " AND a.motorista_id = :motorista";
            }

            if (!empty($_GET['combustivel'])) {
                $sql .= " AND a.tipo_combustivel = :combustivel";
                $params[':combustivel'] = $_GET['combustivel'];
                $sql_count .= " AND a.tipo_combustivel = :combustivel";
            }

            if (!empty($_GET['pagamento'])) {
                $sql .= " AND a.forma_pagamento = :pagamento";
                $params[':pagamento'] = $_GET['pagamento'];
                $sql_count .= " AND a.forma_pagamento = :pagamento";
            }
            
            if (!empty($_GET['year']) && !empty($_GET['month'])) {
                $sql .= " AND YEAR(a.data_abastecimento) = :year AND MONTH(a.data_abastecimento) = :month";
                $params[':year'] = $_GET['year'];
                $params[':month'] = $_GET['month'];
                $sql_count .= " AND YEAR(a.data_abastecimento) = :year AND MONTH(a.data_abastecimento) = :month";
            }
            
            // Executa a consulta de contagem
            $stmt_count = $conn->prepare($sql_count);
            foreach ($params as $key => &$val) {
                $stmt_count->bindValue($key, $val);
            }
            $stmt_count->execute();
            $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Ordena por data mais recente e adiciona paginação
            $sql .= " ORDER BY a.data_abastecimento DESC, a.id DESC LIMIT :limit OFFSET :offset";
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;
            
            $stmt = $conn->prepare($sql);
            foreach ($params as $key => &$val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->execute();
            
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Log para debug
            error_log("Dados dos abastecimentos: " . print_r($result, true));
            
            echo json_encode([
                'success' => true,
                'data' => $result,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'totalPages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        case 'get':
            if (!$refuelId) {
                throw new Exception('ID do abastecimento não fornecido');
            }
            
            $sql = "SELECT 
                    a.*,
                    v.placa as veiculo_placa,
                    v.modelo as veiculo_modelo,
                    m.nome as motorista_nome,
                    r.data_rota,
                    co.nome as cidade_origem_nome,
                    cd.nome as cidade_destino_nome
                FROM abastecimentos a
                LEFT JOIN veiculos v ON a.veiculo_id = v.id
                LEFT JOIN motoristas m ON a.motorista_id = m.id
                LEFT JOIN rotas r ON a.rota_id = r.id
                LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                WHERE a.id = :id AND a.empresa_id = :empresa_id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':id', $refuelId);
            $stmt->bindValue(':empresa_id', $empresa_id);
            $stmt->execute();
            
            $refuel = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$refuel) {
                throw new Exception('Abastecimento não encontrado');
            }
            
            echo json_encode([
                'success' => true,
                'data' => $refuel
            ]);
            break;
            
        case 'summary':
            $sql = "SELECT 
                    COUNT(*) as total_abastecimentos,
                    SUM(litros) as total_litros,
                    SUM(valor_total) as total_gasto,
                    AVG(valor_litro) as media_valor_litro,
                    CASE 
                        WHEN COUNT(*) > 1 
                        AND MAX(km_atual) > MIN(km_atual) 
                        AND SUM(litros) > 0 
                        THEN (MAX(km_atual) - MIN(km_atual)) / SUM(litros)
                        ELSE 0 
                    END as media_km_litro
                FROM abastecimentos 
                WHERE empresa_id = :empresa_id AND status = 'aprovado'";

            $params = [':empresa_id' => $empresa_id];

            // Sempre filtra pelo mês/ano atual se não vierem parâmetros
            $year = !empty($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
            $month = !empty($_GET['month']) ? intval($_GET['month']) : intval(date('n'));
            $sql .= " AND YEAR(data_abastecimento) = :year AND MONTH(data_abastecimento) = :month";
            $params[':year'] = $year;
            $params[':month'] = $month;

            $stmt = $conn->prepare($sql);
            foreach ($params as $key => &$val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'consumption_chart':
            $sql = "SELECT 
                    DATE_FORMAT(data_abastecimento, '%Y-%m') as mes,
                    SUM(litros) as total_litros,
                    SUM(valor_total) as valor_total,
                    AVG(valor_litro) as media_valor_litro
                FROM abastecimentos 
                WHERE empresa_id = :empresa_id
                AND status = 'aprovado'";

            $params = [':empresa_id' => $empresa_id];

            if (!empty($_GET['year']) && !empty($_GET['month'])) {
                $sql .= " AND YEAR(data_abastecimento) = :year AND MONTH(data_abastecimento) = :month";
                $params[':year'] = $_GET['year'];
                $params[':month'] = $_GET['month'];
            } else {
                $sql .= " AND data_abastecimento >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)";
            }

            $sql .= " GROUP BY mes ORDER BY mes";

            $stmt = $conn->prepare($sql);
            foreach ($params as $key => &$val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->execute();
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Formata os dados para o gráfico
            $labels = [];
            $values = [];
            foreach ($data as $row) {
                $labels[] = date('m/Y', strtotime($row['mes'] . '-01'));
                $values[] = floatval($row['total_litros']);
            }
            
            echo json_encode([
                'success' => true,
                'labels' => $labels,
                'values' => $values
            ]);
            break;
            
        case 'efficiency_chart':
            $sql = "SELECT 
                    v.placa,
                    COUNT(a.id) as total_abastecimentos,
                    SUM(a.litros) as total_litros,
                    SUM(a.valor_total) as total_valor,
                    CASE 
                        WHEN COUNT(a.id) > 1 
                        AND MAX(a.km_atual) > MIN(a.km_atual) 
                        AND SUM(a.litros) > 0 
                        THEN (MAX(a.km_atual) - MIN(a.km_atual)) / SUM(a.litros)
                        ELSE 0 
                    END as media_km_por_litro
                FROM abastecimentos a
                JOIN veiculos v ON a.veiculo_id = v.id
                WHERE a.empresa_id = :empresa_id 
                AND a.km_atual > 0
                AND a.litros > 0
                AND a.status = 'aprovado'";

            $params = [':empresa_id' => $empresa_id];

            if (!empty($_GET['year']) && !empty($_GET['month'])) {
                $sql .= " AND YEAR(a.data_abastecimento) = :year AND MONTH(a.data_abastecimento) = :month";
                $params[':year'] = $_GET['year'];
                $params[':month'] = $_GET['month'];
            }

            $sql .= " GROUP BY v.placa, a.veiculo_id
                    HAVING total_abastecimentos >= 2
                    ORDER BY media_km_por_litro DESC
                    LIMIT 10";

            $stmt = $conn->prepare($sql);
            foreach ($params as $key => &$val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->execute();
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Formata os dados para o gráfico
            $labels = [];
            $values = [];
            foreach ($data as $row) {
                $labels[] = $row['placa'];
                $values[] = floatval($row['media_km_por_litro']);
            }
            
            echo json_encode([
                'success' => true,
                'labels' => $labels,
                'values' => $values
            ]);
            break;
            
        case 'anomalies_chart':
            $sql = "SELECT 
                    litros, 
                    valor_litro
                FROM 
                    abastecimentos
                WHERE 
                    empresa_id = :empresa_id
                    AND status = 'aprovado'";
            
            $params = [':empresa_id' => $empresa_id];
            
            if (!empty($_GET['year']) && !empty($_GET['month'])) {
                $sql .= " AND YEAR(data_abastecimento) = :year AND MONTH(data_abastecimento) = :month";
                $params[':year'] = $_GET['year'];
                $params[':month'] = $_GET['month'];
            }
            
            $stmt = $conn->prepare($sql);
            foreach ($params as $key => &$val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->execute();
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
            break;
            
        case 'driver_consumption_chart':
            $sql = "SELECT 
                    m.nome AS motorista,
                    SUM(a.litros) AS total_litros
                FROM 
                    abastecimentos a
                JOIN 
                    motoristas m ON a.motorista_id = m.id
                WHERE 
                    a.empresa_id = :empresa_id
                    AND a.status = 'aprovado'";
            
            $params = [':empresa_id' => $empresa_id];
            
            if (!empty($_GET['year']) && !empty($_GET['month'])) {
                $sql .= " AND YEAR(a.data_abastecimento) = :year AND MONTH(a.data_abastecimento) = :month";
                $params[':year'] = $_GET['year'];
                $params[':month'] = $_GET['month'];
            }
            
            $sql .= " GROUP BY m.nome ORDER BY total_litros DESC";
            
            $stmt = $conn->prepare($sql);
            foreach ($params as $key => &$val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->execute();
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
            break;
            
        case 'vehicle_efficiency_chart':
            $sql = "SELECT 
                    v.placa AS veiculo,
                    ROUND(SUM(a.valor_total) / MAX(a.km_atual), 2) AS custo_por_km
                FROM 
                    abastecimentos a
                JOIN 
                    veiculos v ON a.veiculo_id = v.id
                WHERE 
                    a.empresa_id = :empresa_id
                    AND a.status = 'aprovado'";
            
            $params = [':empresa_id' => $empresa_id];
            
            if (!empty($_GET['year']) && !empty($_GET['month'])) {
                $sql .= " AND YEAR(a.data_abastecimento) = :year AND MONTH(a.data_abastecimento) = :month";
                $params[':year'] = $_GET['year'];
                $params[':month'] = $_GET['month'];
            }
            
            $sql .= " GROUP BY v.placa ORDER BY custo_por_km DESC";
            
            $stmt = $conn->prepare($sql);
            foreach ($params as $key => &$val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->execute();
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
            break;
            
        case 'monthly_cost_chart':
            $sql = "SELECT 
                    DATE_FORMAT(data_abastecimento, '%Y-%m') AS mes,
                    SUM(valor_total) AS total_gasto
                FROM 
                    abastecimentos
                WHERE 
                    empresa_id = :empresa_id";
            
            $params = [':empresa_id' => $empresa_id];
            
            if (!empty($_GET['year']) && !empty($_GET['month'])) {
                $sql .= " AND YEAR(data_abastecimento) = :year AND MONTH(data_abastecimento) = :month";
                $params[':year'] = $_GET['year'];
                $params[':month'] = $_GET['month'];
            }
            
            $sql .= " GROUP BY mes ORDER BY mes ASC";
            
            $stmt = $conn->prepare($sql);
            foreach ($params as $key => &$val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->execute();
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
            break;
            
        case 'get_motoristas_by_veiculo_data':
            $veiculo_id = isset($_GET['veiculo_id']) ? intval($_GET['veiculo_id']) : 0;
            $data = isset($_GET['data']) ? $_GET['data'] : null;
            if ($veiculo_id && $data) {
                $sql = "SELECT DISTINCT m.id, m.nome FROM rotas r
                        INNER JOIN motoristas m ON r.motorista_id = m.id
                        WHERE r.empresa_id = :empresa_id
                        AND r.veiculo_id = :veiculo_id
                        AND r.data_rota >= :data
                        ORDER BY m.nome";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
                $stmt->bindParam(':veiculo_id', $veiculo_id, PDO::PARAM_INT);
                $stmt->bindParam(':data', $data);
                $stmt->execute();
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Parâmetros insuficientes']);
            }
            exit;
        case 'get_rotas_by_veiculo_motorista_data':
            $veiculo_id = isset($_GET['veiculo_id']) ? intval($_GET['veiculo_id']) : 0;
            $motorista_id = isset($_GET['motorista_id']) ? intval($_GET['motorista_id']) : 0;
            $data = isset($_GET['data']) ? $_GET['data'] : null;
            if ($veiculo_id && $motorista_id && $data) {
                $sql = "SELECT r.id, r.data_rota, co.nome as cidade_origem_nome, cd.nome as cidade_destino_nome
                        FROM rotas r
                        LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                        LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                        WHERE r.empresa_id = :empresa_id
                        AND r.veiculo_id = :veiculo_id
                        AND r.motorista_id = :motorista_id
                        AND r.data_rota >= :data
                        ORDER BY r.data_rota DESC";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
                $stmt->bindParam(':veiculo_id', $veiculo_id, PDO::PARAM_INT);
                $stmt->bindParam(':motorista_id', $motorista_id, PDO::PARAM_INT);
                $stmt->bindParam(':data', $data);
                $stmt->execute();
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Parâmetros insuficientes']);
            }
            exit;
            
        case 'get_veiculos_by_data':
            $data = isset($_GET['data']) ? $_GET['data'] : null;
            if ($data) {
                $sql = "SELECT DISTINCT v.id, v.placa, v.modelo
                        FROM rotas r
                        INNER JOIN veiculos v ON r.veiculo_id = v.id
                        WHERE r.empresa_id = :empresa_id
                        AND r.data_rota >= :data
                        ORDER BY v.placa";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
                $stmt->bindParam(':data', $data);
                $stmt->execute();
                $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $veiculos]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Data não informada']);
            }
            exit;
            
        default:
            throw new Exception('Ação inválida');
    }
} catch (Exception $e) {
    error_log("Erro na API de dados de abastecimentos: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => DEBUG_MODE ? $e->getMessage() : 'Erro ao processar a requisição'
    ]);
} 