<?php
// Maintenance data API

// Initialize session and include necessary files
session_start();
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// Get optional parameters
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$vehicle = isset($_GET['vehicle']) ? $_GET['vehicle'] : null;
$type = isset($_GET['type']) ? $_GET['type'] : null;
$dateStart = isset($_GET['date_start']) ? $_GET['date_start'] : null;
$dateEnd = isset($_GET['date_end']) ? $_GET['date_end'] : null;

// Calculate offset for pagination
$offset = ($page - 1) * $limit;

// Get maintenance data
$empresa_id = $_SESSION['empresa_id'];
$data = getMaintenanceData($empresa_id, $limit, $offset, $vehicle, $type, $dateStart, $dateEnd);

// Return data
echo json_encode($data);

/**
 * Get maintenance data
 * 
 * @param int $empresa_id Company ID
 * @param int $limit Maximum number of records to return
 * @param int $offset Offset for pagination
 * @param string $vehicle Filter by vehicle
 * @param string $type Filter by maintenance type
 * @param string $dateStart Filter by start date
 * @param string $dateEnd Filter by end date
 * @return array Maintenance data
 */
function getMaintenanceData($empresa_id, $limit, $offset, $vehicle, $type, $dateStart, $dateEnd) {
    try {
        $conn = getConnection();
        
        // Build query with filters
        $sql = "SELECT m.*, v.placa as vehicle_placa, v.modelo as vehicle_modelo 
                FROM manutencoes m 
                LEFT JOIN veiculos v ON m.veiculo_id = v.id 
                WHERE m.empresa_id = :empresa_id";
        
        $params = ['empresa_id' => $empresa_id];
        
        if ($vehicle) {
            $sql .= " AND v.placa = :vehicle";
            $params['vehicle'] = $vehicle;
        }
        
        if ($type) {
            $sql .= " AND m.tipo = :type";
            $params['type'] = $type;
        }
        
        if ($dateStart) {
            $sql .= " AND m.data_manutencao >= :date_start";
            $params['date_start'] = $dateStart;
        }
        
        if ($dateEnd) {
            $sql .= " AND m.data_manutencao <= :date_end";
            $params['date_end'] = $dateEnd;
        }
        
        $sql .= " ORDER BY m.data_manutencao DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        
        if ($vehicle) $stmt->bindParam(':vehicle', $vehicle);
        if ($type) $stmt->bindParam(':type', $type);
        if ($dateStart) $stmt->bindParam(':date_start', $dateStart);
        if ($dateEnd) $stmt->bindParam(':date_end', $dateEnd);
        
        $stmt->execute();
        $maintenanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) FROM manutencoes m 
                     LEFT JOIN veiculos v ON m.veiculo_id = v.id 
                     WHERE m.empresa_id = :empresa_id";
        
        $countParams = ['empresa_id' => $empresa_id];
        
        if ($vehicle) {
            $countSql .= " AND v.placa = :vehicle";
            $countParams['vehicle'] = $vehicle;
        }
        
        if ($type) {
            $countSql .= " AND m.tipo = :type";
            $countParams['type'] = $type;
        }
        
        if ($dateStart) {
            $countSql .= " AND m.data_manutencao >= :date_start";
            $countParams['date_start'] = $dateStart;
        }
        
        if ($dateEnd) {
            $countSql .= " AND m.data_manutencao <= :date_end";
            $countParams['date_end'] = $dateEnd;
        }
        
        $countStmt = $conn->prepare($countSql);
        $countStmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        
        if ($vehicle) $countStmt->bindParam(':vehicle', $vehicle);
        if ($type) $countStmt->bindParam(':type', $type);
        if ($dateStart) $countStmt->bindParam(':date_start', $dateStart);
        if ($dateEnd) $countStmt->bindParam(':date_end', $dateEnd);
        
        $countStmt->execute();
        $totalRecords = $countStmt->fetchColumn();
        
        // Format data
        $formattedData = [];
        foreach ($maintenanceData as $item) {
            $formattedData[] = [
                'id' => $item['id'],
                'date' => $item['data_manutencao'],
                'vehicle' => $item['vehicle_placa'] ?? 'N/A',
                'vehicleName' => $item['vehicle_modelo'] ?? 'N/A',
                'type' => $item['tipo'] ?? 'N/A',
                'description' => $item['descricao'] ?? 'N/A',
                'value' => floatval($item['valor'] ?? 0),
                'mechanic' => $item['mecanica'] ?? 'N/A'
            ];
        }
        
        // Calculate summary
        $summarySql = "SELECT 
                        COUNT(*) as totalMaintenance,
                        SUM(valor) as totalCost,
                        SUM(CASE WHEN tipo = 'corretiva' THEN 1 ELSE 0 END) as corretiva,
                        SUM(CASE WHEN tipo = 'preventiva' THEN 1 ELSE 0 END) as preventiva
                       FROM manutencoes 
                       WHERE empresa_id = :empresa_id";
        
        $summaryParams = ['empresa_id' => $empresa_id];
        
        if ($dateStart) {
            $summarySql .= " AND data_manutencao >= :date_start";
            $summaryParams['date_start'] = $dateStart;
        }
        
        if ($dateEnd) {
            $summarySql .= " AND data_manutencao <= :date_end";
            $summaryParams['date_end'] = $dateEnd;
        }
        
        $summaryStmt = $conn->prepare($summarySql);
        $summaryStmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        
        if ($dateStart) $summaryStmt->bindParam(':date_start', $dateStart);
        if ($dateEnd) $summaryStmt->bindParam(':date_end', $dateEnd);
        
        $summaryStmt->execute();
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
        
    return [
            'data' => $formattedData,
        'pagination' => [
                'page' => ceil($offset / $limit) + 1,
            'limit' => $limit,
            'total' => $totalRecords,
            'totalPages' => ceil($totalRecords / $limit)
        ],
        'summary' => [
                'totalMaintenance' => intval($summary['totalMaintenance']),
                'totalCost' => floatval($summary['totalCost']),
            'typeBreakdown' => [
                    'corretiva' => intval($summary['corretiva']),
                    'preventiva' => intval($summary['preventiva'])
                ]
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Erro ao buscar dados de manutenção: " . $e->getMessage());
        return [
            'data' => [],
            'pagination' => [
                'page' => 1,
                'limit' => $limit,
                'total' => 0,
                'totalPages' => 0
            ],
            'summary' => [
                'totalMaintenance' => 0,
                'totalCost' => 0,
                'typeBreakdown' => [
                    'corretiva' => 0,
                    'preventiva' => 0
                ]
            ]
        ];
    }
}
