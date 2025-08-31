<?php
/**
 * API para Filtros do Modo Flexível
 * Gerencia opções de filtros e aplicação de filtros
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Prevenir qualquer saída antes do JSON
ob_start();

try {
    // Carregar configurações do banco usando caminho absoluto
    $config = require __DIR__ . '/../config/database.php';
    
    // Criar conexão PDO
    $dsn = sprintf(
        "mysql:host=%s;port=%d;dbname=%s;charset=%s",
        $config['host'],
        $config['port'],
        $config['database'],
        $config['charset']
    );
    
    $pdo = new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        $config['options']
    );
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'get_options':
            $options = [
                'marcas' => getMarcas($pdo),
                'tamanhos' => getTamanhos($pdo),
                'veiculos' => getVeiculos($pdo),
                'status' => getStatus(),
                'posicoes' => getPosicoes()
            ];
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'data' => ['options' => $options]
            ]);
            break;
            
        case 'apply_filters':
            $filters = $input['filters'] ?? [];
            $filteredData = getFilteredPneus($pdo, $filters);
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'data' => [
                    'data' => $filteredData,
                    'total' => count($filteredData),
                    'filters_applied' => $filters
                ]
            ]);
            break;
            
        case 'get_filtered_data':
            $filters = $input['filters'] ?? [];
            $page = $input['page'] ?? 1;
            $limit = $input['limit'] ?? 50;
            $offset = ($page - 1) * $limit;
            
            $filteredData = getFilteredPneus($pdo, $filters, $limit, $offset);
            $total = getFilteredCount($pdo, $filters);
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'data' => [
                    'data' => $filteredData,
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        default:
            ob_clean();
            echo json_encode([
                'success' => false,
                'error' => 'Ação não reconhecida'
            ]);
    }
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function getMarcas($pdo) {
    $query = "SELECT DISTINCT marca FROM pneus 
             WHERE ativo = 1 AND marca IS NOT NULL AND marca != '' 
             ORDER BY marca";
    $stmt = $pdo->query($query);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $marcas = [];
    foreach ($result as $row) {
        $marcas[] = $row['marca'];
    }
    
    return $marcas;
}

function getTamanhos($pdo) {
    $query = "SELECT DISTINCT tamanho FROM pneus 
             WHERE ativo = 1 AND tamanho IS NOT NULL AND tamanho != '' 
             ORDER BY tamanho";
    $stmt = $pdo->query($query);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $tamanhos = [];
    foreach ($result as $row) {
        $tamanhos[] = $row['tamanho'];
    }
    
    return $tamanhos;
}

function getVeiculos($pdo) {
    $query = "SELECT DISTINCT v.placa, v.modelo 
             FROM veiculos v 
             INNER JOIN pneus p ON v.id = p.veiculo_id 
             WHERE v.ativo = 1 AND p.ativo = 1 
             ORDER BY v.placa";
    $stmt = $pdo->query($query);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $veiculos = [];
    foreach ($result as $row) {
        $veiculos[] = $row['placa'] . ' - ' . $row['modelo'];
    }
    
    return $veiculos;
}

function getStatus() {
    return [
        'disponivel' => 'Disponível',
        'em_uso' => 'Em Uso',
        'manutencao' => 'Manutenção',
        'critico' => 'Crítico',
        'alerta' => 'Alerta'
    ];
}

function getPosicoes() {
    return [
        'dianteira-esquerda' => 'Dianteira Esquerda',
        'dianteira-direita' => 'Dianteira Direita',
        'traseira-esquerda' => 'Traseira Esquerda',
        'traseira-direita' => 'Traseira Direita',
        'eixo-auxiliar' => 'Eixo Auxiliar'
    ];
}

function getFilteredPneus($pdo, $filters, $limit = null, $offset = null) {
    $whereConditions = ['p.ativo = 1'];
    $params = [];
    
    // Filtro de busca
    if (!empty($filters['search'])) {
        $search = '%' . $filters['search'] . '%';
        $whereConditions[] = "(p.codigo LIKE ? OR p.marca LIKE ? OR p.modelo LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }
    
    // Filtro de status
    if (!empty($filters['status']) && is_array($filters['status'])) {
        $placeholders = str_repeat('?,', count($filters['status']) - 1) . '?';
        $whereConditions[] = "p.status IN ($placeholders)";
        $params = array_merge($params, $filters['status']);
    }
    
    // Filtro de marca
    if (!empty($filters['marca'])) {
        $whereConditions[] = "p.marca = ?";
        $params[] = $filters['marca'];
    }
    
    // Filtro de tamanho
    if (!empty($filters['tamanho'])) {
        $whereConditions[] = "p.tamanho = ?";
        $params[] = $filters['tamanho'];
    }
    
    // Filtro de preço
    if (!empty($filters['preco_min'])) {
        $whereConditions[] = "p.preco >= ?";
        $params[] = $filters['preco_min'];
    }
    
    if (!empty($filters['preco_max'])) {
        $whereConditions[] = "p.preco <= ?";
        $params[] = $filters['preco_max'];
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $sql = "SELECT p.*, sp.nome as status_nome 
            FROM pneus p 
            LEFT JOIN status_pneus sp ON p.status_id = sp.id 
            WHERE $whereClause 
            ORDER BY p.numero_serie";
    
    if ($limit !== null) {
        $sql .= " LIMIT $limit";
        if ($offset !== null) {
            $sql .= " OFFSET $offset";
        }
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formattedData = [];
    foreach ($result as $row) {
        $formattedData[] = formatPneuData($row);
    }
    
    return $formattedData;
}

function getFilteredCount($pdo, $filters) {
    $whereConditions = ['p.ativo = 1'];
    $params = [];
    
    // Aplicar os mesmos filtros para contar
    if (!empty($filters['search'])) {
        $search = '%' . $filters['search'] . '%';
        $whereConditions[] = "(p.codigo LIKE ? OR p.marca LIKE ? OR p.modelo LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }
    
    if (!empty($filters['status']) && is_array($filters['status'])) {
        $placeholders = str_repeat('?,', count($filters['status']) - 1) . '?';
        $whereConditions[] = "p.status IN ($placeholders)";
        $params = array_merge($params, $filters['status']);
    }
    
    if (!empty($filters['marca'])) {
        $whereConditions[] = "p.marca = ?";
        $params[] = $filters['marca'];
    }
    
    if (!empty($filters['tamanho'])) {
        $whereConditions[] = "p.tamanho = ?";
        $params[] = $filters['tamanho'];
    }
    
    if (!empty($filters['preco_min'])) {
        $whereConditions[] = "p.preco >= ?";
        $params[] = $filters['preco_min'];
    }
    
    if (!empty($filters['preco_max'])) {
        $whereConditions[] = "p.preco <= ?";
        $params[] = $filters['preco_max'];
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $sql = "SELECT COUNT(*) as total FROM pneus p WHERE $whereClause";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['total'];
}

function formatPneuData($row) {
    return [
        'id' => $row['id'],
        'numero_serie' => $row['numero_serie'],
        'marca' => $row['marca'],
        'modelo' => $row['modelo'],
        'tamanho' => $row['tamanho'],
        'status' => $row['status_nome'],
        'estado' => determineEstado($row),
        'preco' => number_format($row['preco'] ?? 0, 2, ',', '.'),
        'quilometragem' => number_format($row['quilometragem'] ?? 0, 0, ',', '.'),
        'data_instalacao' => $row['data_instalacao'] ? date('d/m/Y', strtotime($row['data_instalacao'])) : null
    ];
}

function determineEstado($pneu) {
    $quilometragem = $pneu['quilometragem'] ?? 0;
    $vida_util = $pneu['vida_util_km'] ?? 100000;
    
    if ($quilometragem >= $vida_util * 0.9) {
        return 'critico';
    } elseif ($quilometragem >= $vida_util * 0.7) {
        return 'alerta';
    } else {
        return 'bom';
    }
}
?> 