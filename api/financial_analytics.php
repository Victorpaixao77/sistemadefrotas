<?php
// Financial Analytics API

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
    $conn = getConnection();
    $empresa_id = $_SESSION['empresa_id'];
    $current_year = date('Y');
    
    // Get revenue data (completed routes)
    $revenue_query = "
        SELECT 
            MONTH(data_saida) as month,
            SUM(frete) as total
        FROM rotas 
        WHERE empresa_id = :empresa_id 
        AND YEAR(data_saida) = :year
        GROUP BY MONTH(data_saida)
        ORDER BY month";
    
    $stmt = $conn->prepare($revenue_query);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->bindParam(':year', $current_year);
    $stmt->execute();
    $revenue_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get expenses data from all sources
    $expenses_query = "
        SELECT 
            month,
            SUM(total) as total
        FROM (
            -- Despesas de Viagem
            SELECT 
                MONTH(created_at) as month,
                SUM(COALESCE(total_despviagem, 0)) as total
            FROM despesas_viagem
            WHERE empresa_id = :empresa_id_1 
            AND YEAR(created_at) = :year_1
            GROUP BY MONTH(created_at)
            
            UNION ALL
            
            -- Despesas Fixas
            SELECT 
                MONTH(data_pagamento) as month,
                SUM(valor) as total
            FROM despesas_fixas
            WHERE empresa_id = :empresa_id_2 
            AND YEAR(data_pagamento) = :year_2
            GROUP BY MONTH(data_pagamento)
            
            UNION ALL
            
            -- Manutenções de Veículos
            SELECT 
                MONTH(data_manutencao) as month,
                SUM(valor) as total
            FROM manutencoes
            WHERE empresa_id = :empresa_id_3 
            AND YEAR(data_manutencao) = :year_3
            GROUP BY MONTH(data_manutencao)
            
            UNION ALL
            
            -- Manutenções de Pneus
            SELECT 
                MONTH(data_manutencao) as month,
                SUM(custo) as total
            FROM pneu_manutencao
            WHERE empresa_id = :empresa_id_4 
            AND YEAR(data_manutencao) = :year_4
            GROUP BY MONTH(data_manutencao)
            
            UNION ALL
            
            -- Contas Pagas
            SELECT 
                MONTH(data_pagamento) as month,
                SUM(valor) as total
            FROM contas_pagar
            WHERE empresa_id = :empresa_id_5 
            AND YEAR(data_pagamento) = :year_5
            GROUP BY MONTH(data_pagamento)
            
            UNION ALL
            
            -- Parcelas de Financiamento
            SELECT 
                MONTH(data_vencimento) as month,
                SUM(valor) as total
            FROM parcelas_financiamento
            WHERE empresa_id = :empresa_id_6 
            AND YEAR(data_vencimento) = :year_6
            AND status_id = 2
            GROUP BY MONTH(data_vencimento)
        ) as combined_expenses
        GROUP BY month
        ORDER BY month";
    
    $stmt = $conn->prepare($expenses_query);
    
    // Bind parameters for each subquery
    for ($i = 1; $i <= 6; $i++) {
        $stmt->bindParam(':empresa_id_' . $i, $empresa_id);
        $stmt->bindParam(':year_' . $i, $current_year);
    }
    
    $stmt->execute();
    $expenses_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process the data
    $months = range(1, 12);
    $revenue_by_month = array_fill_keys($months, 0);
    $expenses_by_month = array_fill_keys($months, 0);
    
    // Fill revenue data
    foreach ($revenue_data as $row) {
        $revenue_by_month[$row['month']] = (float)$row['total'];
    }
    
    // Fill expenses data
    foreach ($expenses_data as $row) {
        $expenses_by_month[$row['month']] = (float)$row['total'];
    }
    
    // Prepare the response
    $response = [
        'labels' => array_map(function($month) {
            return date('M', mktime(0, 0, 0, $month, 1));
        }, $months),
        'faturamento' => array_values($revenue_by_month),
        'despesas' => array_values($expenses_by_month)
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Financial Analytics API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
    exit;
} 