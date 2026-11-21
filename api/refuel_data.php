<?php
// Refuel data API

require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/refuel_list.php';

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
if (function_exists('session_write_close')) { session_write_close(); }

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
            echo json_encode(refuel_fetch_list($conn, (int) $empresa_id, [
                'page' => isset($_GET['page']) ? (int) $_GET['page'] : 1,
                'limit' => isset($_GET['limit']) ? (int) $_GET['limit'] : 10,
                'search' => $_GET['search'] ?? '',
                'veiculo' => $_GET['veiculo'] ?? '',
                'motorista' => $_GET['motorista'] ?? '',
                'rota_id' => $_GET['rota_id'] ?? '',
                'combustivel' => $_GET['combustivel'] ?? '',
                'pagamento' => $_GET['pagamento'] ?? '',
                'year' => $_GET['year'] ?? null,
                'month' => $_GET['month'] ?? null,
                'sort' => $_GET['sort'] ?? 'data_abastecimento',
                'dir' => $_GET['dir'] ?? 'DESC',
            ]));
            break;

        case 'filter_options':
            // Buscar veículos distintos
            $sqlVehicles = "SELECT DISTINCT 
                    v.id, 
                    v.placa, 
                    v.modelo
                FROM abastecimentos a
                INNER JOIN veiculos v ON a.veiculo_id = v.id
                WHERE a.empresa_id = :empresa_id AND a.status = 'aprovado'
                ORDER BY v.placa ASC";
            $stmtVehicles = $conn->prepare($sqlVehicles);
            $stmtVehicles->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmtVehicles->execute();
            $vehicles = $stmtVehicles->fetchAll(PDO::FETCH_ASSOC);

            // Buscar motoristas distintos
            $sqlDrivers = "SELECT DISTINCT 
                    m.id, 
                    m.nome
                FROM abastecimentos a
                INNER JOIN motoristas m ON a.motorista_id = m.id
                WHERE a.empresa_id = :empresa_id AND a.status = 'aprovado'
                ORDER BY m.nome ASC";
            $stmtDrivers = $conn->prepare($sqlDrivers);
            $stmtDrivers->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmtDrivers->execute();
            $drivers = $stmtDrivers->fetchAll(PDO::FETCH_ASSOC);

            // Buscar combustíveis distintos
            $sqlFuels = "SELECT DISTINCT a.tipo_combustivel 
                FROM abastecimentos a
                WHERE a.empresa_id = :empresa_id AND a.status = 'aprovado' AND a.tipo_combustivel IS NOT NULL AND a.tipo_combustivel <> ''
                ORDER BY a.tipo_combustivel ASC";
            $stmtFuels = $conn->prepare($sqlFuels);
            $stmtFuels->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmtFuels->execute();
            $fuels = array_column($stmtFuels->fetchAll(PDO::FETCH_ASSOC), 'tipo_combustivel');

            // Buscar formas de pagamento distintas
            $sqlPayments = "SELECT DISTINCT a.forma_pagamento 
                FROM abastecimentos a
                WHERE a.empresa_id = :empresa_id AND a.status = 'aprovado' AND a.forma_pagamento IS NOT NULL AND a.forma_pagamento <> ''
                ORDER BY a.forma_pagamento ASC";
            $stmtPayments = $conn->prepare($sqlPayments);
            $stmtPayments->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmtPayments->execute();
            $payments = array_column($stmtPayments->fetchAll(PDO::FETCH_ASSOC), 'forma_pagamento');

            echo json_encode([
                'success' => true,
                'data' => [
                    'vehicles' => $vehicles,
                    'drivers' => $drivers,
                    'fuels' => $fuels,
                    'payments' => $payments
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
            $year = !empty($_GET['year']) ? (int) $_GET['year'] : null;
            $month = !empty($_GET['month']) ? (int) $_GET['month'] : null;
            echo json_encode([
                'success' => true,
                'data' => refuel_fetch_summary($conn, (int) $empresa_id, $year, $month),
            ]);
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
