<?php
// Dashboard data API

// Initialize session and include necessary files
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Start session
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    // Check for detail parameter
    $detail = isset($_GET['detail']) ? $_GET['detail'] : null;

    // If detail parameter is provided, return detailed data for specific widget
    if ($detail) {
        echo json_encode(getDetailedData($detail));
        exit;
    }

    // Otherwise return general dashboard data
    echo json_encode(getDashboardData());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    error_log("Dashboard API Error: " . $e->getMessage());
    exit;
}

/**
 * Get general dashboard data
 * 
 * @return array Dashboard data
 */
function getDashboardData() {
    // In a real application, this would fetch from the database
    // For now, we'll return sample data matching the UI mockup
    
    return [
        'vehicleCount' => 2,
        'motoristCount' => 2,
        'supplyData' => [
            'count' => 2,
            'total' => 355.56
        ],
        'liquidValue' => 12082.50,
        'routes' => [
            'completed' => 2,
            'total' => 3
        ],
        'productivity' => 92,
        'maintenanceData' => [
            [
                'date' => '2025-05-06',
                'vehicle' => '333333',
                'type' => 'corretiva',
                'value' => 50.00
            ],
            [
                'date' => '2025-05-05',
                'vehicle' => '333333',
                'type' => 'corretiva',
                'value' => 80.00
            ],
            [
                'date' => '2025-05-02',
                'vehicle' => '333333',
                'type' => 'corretiva',
                'value' => 1222.00
            ]
        ]
    ];
}

/**
 * Get detailed data for a specific dashboard widget
 * 
 * @param string $widget Widget identifier
 * @return array Detailed widget data
 */
function getDetailedData($widget) {
    // Return different data based on the requested widget
    switch ($widget) {
        case 'vehicles':
            return getVehicleDetailedData();
        
        case 'motorists':
            return getMotoristDetailedData();
        
        case 'supply':
            return getSupplyDetailedData();
        
        case 'routes':
            return getRouteDetailedData();
        
        case 'productivity':
            return getProductivityDetailedData();
        
        case 'liquid-value':
            return getFinancialDetailedData();
        
        default:
            return ['error' => 'Unknown widget type'];
    }
}

/**
 * Get detailed vehicle data
 * 
 * @return array Vehicle data
 */
function getVehicleDetailedData() {
    // Sample vehicle data
    return [
        'vehicles' => [
            [
                'plate' => 'ABC-1234',
                'model' => 'Mercedes-Benz Actros',
                'year' => '2022',
                'status' => 'Ativo',
                'mileage' => '45,890',
                'lastMaintenance' => '28/04/2025'
            ],
            [
                'plate' => 'DEF-5678',
                'model' => 'Volvo FH 540',
                'year' => '2023',
                'status' => 'Ativo',
                'mileage' => '32,150',
                'lastMaintenance' => '15/04/2025'
            ]
        ],
        'chart' => [
            'title' => 'Quilometragem por Veículo',
            'labels' => ['ABC-1234', 'DEF-5678'],
            'data' => [45890, 32150],
            'colors' => ['#3b82f6', '#10b981']
        ]
    ];
}

/**
 * Get detailed motorist data
 * 
 * @return array Motorist data
 */
function getMotoristDetailedData() {
    // Sample motorist data
    return [
        'motorists' => [
            [
                'name' => 'João Silva',
                'license' => 'AE12345678',
                'status' => 'Ativo',
                'trips' => '28',
                'rating' => '4.8',
                'lastTrip' => '07/05/2025'
            ],
            [
                'name' => 'Maria Oliveira',
                'license' => 'AE87654321',
                'status' => 'Ativo',
                'trips' => '32',
                'rating' => '4.9',
                'lastTrip' => '08/05/2025'
            ]
        ],
        'chart' => [
            'title' => 'Desempenho dos Motoristas',
            'labels' => ['João Silva', 'Maria Oliveira'],
            'datasets' => [
                [
                    'label' => 'Viagens Concluídas',
                    'data' => [28, 32],
                    'backgroundColor' => '#3b82f6'
                ],
                [
                    'label' => 'Avaliação (x10)',
                    'data' => [48, 49],
                    'backgroundColor' => '#10b981'
                ]
            ]
        ]
    ];
}

/**
 * Get detailed supply data
 * 
 * @return array Supply data
 */
function getSupplyDetailedData() {
    // Sample supply data
    return [
        'supplies' => [
            [
                'date' => '05/05/2025',
                'vehicle' => 'ABC-1234',
                'fuelType' => 'Diesel S10',
                'liters' => '180.5',
                'value' => '987.56',
                'station' => 'Posto ABC'
            ],
            [
                'date' => '03/05/2025',
                'vehicle' => 'DEF-5678',
                'fuelType' => 'Diesel S10',
                'liters' => '150.0',
                'value' => '825.00',
                'station' => 'Posto XYZ'
            ]
        ],
        'chart' => [
            'title' => 'Consumo de Combustível (Últimos 6 Meses)',
            'labels' => ['Dez', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai'],
            'datasets' => [
                [
                    'label' => 'Litros',
                    'data' => [720, 680, 750, 710, 690, 730],
                    'backgroundColor' => '#3b82f6'
                ],
                [
                    'label' => 'Custo (R$)',
                    'data' => [3600, 3400, 3750, 3550, 3450, 3650],
                    'backgroundColor' => '#f59e0b',
                    'yAxisID' => 'y2'
                ]
            ],
            'options' => [
                'scales' => [
                    'y' => [
                        'title' => ['text' => 'Litros'],
                        'position' => 'left'
                    ],
                    'y2' => [
                        'title' => ['text' => 'Custo (R$)'],
                        'position' => 'right',
                        'grid' => ['drawOnChartArea' => false]
                    ]
                ]
            ]
        ],
        'summary' => [
            'totalLiters' => '3350.5',
            'totalCost' => '18427.75',
            'avgCostPerLiter' => '5.50'
        ]
    ];
}

/**
 * Get detailed route data
 * 
 * @return array Route data
 */
function getRouteDetailedData() {
    // Sample route data
    return [
        'routes' => [
            [
                'id' => '1',
                'origin' => 'São Paulo, SP',
                'destination' => 'Rio de Janeiro, RJ',
                'distance' => '430',
                'status' => 'Concluída',
                'driver' => 'João Silva',
                'vehicle' => 'ABC-1234',
                'date' => '06/05/2025'
            ],
            [
                'id' => '2',
                'origin' => 'Rio de Janeiro, RJ',
                'destination' => 'Belo Horizonte, MG',
                'distance' => '435',
                'status' => 'Concluída',
                'driver' => 'Maria Oliveira',
                'vehicle' => 'DEF-5678',
                'date' => '07/05/2025'
            ],
            [
                'id' => '3',
                'origin' => 'Belo Horizonte, MG',
                'destination' => 'São Paulo, SP',
                'distance' => '585',
                'status' => 'Em andamento',
                'driver' => 'João Silva',
                'vehicle' => 'ABC-1234',
                'date' => '09/05/2025'
            ]
        ],
        'chart' => [
            'title' => 'Rotas por Status',
            'type' => 'pie',
            'labels' => ['Concluídas', 'Em andamento', 'Programadas'],
            'data' => [2, 1, 0],
            'colors' => ['#10b981', '#3b82f6', '#f59e0b']
        ],
        'summary' => [
            'totalRoutes' => 3,
            'completedRoutes' => 2,
            'totalDistance' => '1450',
            'completionRate' => '66.67'
        ]
    ];
}

/**
 * Get detailed productivity data
 * 
 * @return array Productivity data
 */
function getProductivityDetailedData() {
    // Sample productivity data
    return [
        'current' => 92,
        'previous' => 87,
        'change' => 5.75,
        'breakdown' => [
            [
                'category' => 'Eficiência de Combustível',
                'value' => 90,
                'previous' => 85
            ],
            [
                'category' => 'Tempo de Entrega',
                'value' => 94,
                'previous' => 89
            ],
            [
                'category' => 'Quilometragem Diária',
                'value' => 88,
                'previous' => 84
            ],
            [
                'category' => 'Manutenção Preventiva',
                'value' => 95,
                'previous' => 90
            ]
        ],
        'chart' => [
            'title' => 'Produtividade ao Longo do Tempo',
            'labels' => ['Jan', 'Fev', 'Mar', 'Abr', 'Mai'],
            'datasets' => [
                [
                    'label' => 'Produtividade (%)',
                    'data' => [80, 83, 85, 87, 92],
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)'
                ],
                [
                    'label' => 'Meta (%)',
                    'data' => [85, 85, 85, 90, 90],
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'transparent',
                    'borderDashed' => true
                ]
            ]
        ]
    ];
}

/**
 * Get detailed financial data
 * 
 * @return array Financial data
 */
function getFinancialDetailedData() {
    // Sample financial data
    return [
        'current' => [
            'revenue' => 58750.00,
            'expenses' => 46667.50,
            'profit' => 12082.50,
            'margin' => 20.57
        ],
        'lastMonth' => [
            'revenue' => 55230.00,
            'expenses' => 44960.50,
            'profit' => 10269.50,
            'margin' => 18.59
        ],
        'chart' => [
            'title' => 'Receita vs. Despesas (Últimos 6 Meses)',
            'labels' => ['Dez', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai'],
            'datasets' => [
                [
                    'label' => 'Receita',
                    'data' => [52480, 53150, 54920, 56780, 55230, 58750],
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)'
                ],
                [
                    'label' => 'Despesas',
                    'data' => [43270, 43980, 45120, 46350, 44960, 46670],
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)'
                ]
            ]
        ],
        'expenses' => [
            [
                'category' => 'Combustível',
                'value' => 18650.00,
                'percentage' => 39.96
            ],
            [
                'category' => 'Manutenção',
                'value' => 8760.50,
                'percentage' => 18.77
            ],
            [
                'category' => 'Salários',
                'value' => 12450.00,
                'percentage' => 26.68
            ],
            [
                'category' => 'Impostos',
                'value' => 4250.00,
                'percentage' => 9.11
            ],
            [
                'category' => 'Outros',
                'value' => 2557.00,
                'percentage' => 5.48
            ]
        ]
    ];
}
