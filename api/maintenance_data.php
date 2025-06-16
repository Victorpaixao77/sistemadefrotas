<?php
// Maintenance data API

// Initialize session and include necessary files
session_start();
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is authenticated (commented out for development)
// if (!isLoggedIn()) {
//     http_response_code(401);
//     echo json_encode(['error' => 'Unauthorized access']);
//     exit;
// }

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
$data = getMaintenanceData($limit, $offset, $vehicle, $type, $dateStart, $dateEnd);

// Return data
echo json_encode($data);

/**
 * Get maintenance data
 * 
 * @param int $limit Maximum number of records to return
 * @param int $offset Offset for pagination
 * @param string $vehicle Filter by vehicle
 * @param string $type Filter by maintenance type
 * @param string $dateStart Filter by start date
 * @param string $dateEnd Filter by end date
 * @return array Maintenance data
 */
function getMaintenanceData($limit, $offset, $vehicle, $type, $dateStart, $dateEnd) {
    // In a real application, this would fetch from the database using filters
    // For now, return sample data
    
    // Sample maintenance data
    $maintenanceData = [
        [
            'id' => 1,
            'date' => '2025-05-06',
            'vehicle' => '333333',
            'vehicleName' => 'Mercedes-Benz Actros',
            'type' => 'corretiva',
            'description' => 'Troca de fluido de freio',
            'value' => 50.00,
            'mechanic' => 'Oficina ABC'
        ],
        [
            'id' => 2,
            'date' => '2025-05-05',
            'vehicle' => '333333',
            'vehicleName' => 'Mercedes-Benz Actros',
            'type' => 'corretiva',
            'description' => 'Substituição de filtro de ar',
            'value' => 80.00,
            'mechanic' => 'Oficina ABC'
        ],
        [
            'id' => 3,
            'date' => '2025-05-02',
            'vehicle' => '333333',
            'vehicleName' => 'Mercedes-Benz Actros',
            'type' => 'corretiva',
            'description' => 'Reparo no sistema de suspensão',
            'value' => 1222.00,
            'mechanic' => 'Oficina XYZ'
        ],
        [
            'id' => 4,
            'date' => '2025-04-28',
            'vehicle' => '444444',
            'vehicleName' => 'Volvo FH 540',
            'type' => 'preventiva',
            'description' => 'Troca de óleo e filtros',
            'value' => 540.00,
            'mechanic' => 'Oficina ABC'
        ],
        [
            'id' => 5,
            'date' => '2025-04-15',
            'vehicle' => '444444',
            'vehicleName' => 'Volvo FH 540',
            'type' => 'preventiva',
            'description' => 'Verificação de sistema elétrico',
            'value' => 350.00,
            'mechanic' => 'Oficina XYZ'
        ]
    ];
    
    // Apply filters
    $filteredData = array_filter($maintenanceData, function($item) use ($vehicle, $type, $dateStart, $dateEnd) {
        $matchesVehicle = $vehicle ? $item['vehicle'] === $vehicle : true;
        $matchesType = $type ? $item['type'] === $type : true;
        $matchesDateStart = $dateStart ? strtotime($item['date']) >= strtotime($dateStart) : true;
        $matchesDateEnd = $dateEnd ? strtotime($item['date']) <= strtotime($dateEnd) : true;
        
        return $matchesVehicle && $matchesType && $matchesDateStart && $matchesDateEnd;
    });
    
    // Sort by date (newest first)
    usort($filteredData, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    // Calculate total records (for pagination)
    $totalRecords = count($filteredData);
    
    // Apply pagination
    $paginatedData = array_slice($filteredData, $offset, $limit);
    
    // Return data with pagination info
    return [
        'data' => $paginatedData,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalRecords,
            'totalPages' => ceil($totalRecords / $limit)
        ],
        'summary' => [
            'totalMaintenance' => $totalRecords,
            'totalCost' => array_reduce($filteredData, function($sum, $item) {
                return $sum + $item['value'];
            }, 0),
            'typeBreakdown' => [
                'corretiva' => count(array_filter($filteredData, function($item) {
                    return $item['type'] === 'corretiva';
                })),
                'preventiva' => count(array_filter($filteredData, function($item) {
                    return $item['type'] === 'preventiva';
                }))
            ]
        ]
    ];
}
