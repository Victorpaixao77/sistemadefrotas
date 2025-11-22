<?php
// Expenses Distribution API

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
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

try {
    $conn = getConnection();
    $empresa_id = $_SESSION['empresa_id'];
    $current_year = date('Y');
    
    // Query para buscar todas as despesas acumuladas no ano
    $query = "
        SELECT 
            'Despesas de Viagem' as categoria,
            SUM(COALESCE(total_despviagem, 0)) as total
        FROM despesas_viagem
        WHERE empresa_id = :empresa_id_1 
        AND YEAR(created_at) = :year_1
        
        UNION ALL
        
        SELECT 
            'Despesas Fixas' as categoria,
            SUM(valor) as total
        FROM despesas_fixas
        WHERE empresa_id = :empresa_id_2 
        AND YEAR(vencimento) = :year_2
        
        UNION ALL
        
        SELECT 
            'Manutenções de Veículos' as categoria,
            SUM(valor) as total
        FROM manutencoes
        WHERE empresa_id = :empresa_id_3 
        AND YEAR(data_manutencao) = :year_3
        
        UNION ALL
        
        SELECT 
            'Manutenções de Pneus' as categoria,
            SUM(custo) as total
        FROM pneu_manutencao
        WHERE empresa_id = :empresa_id_4 
        AND YEAR(data_manutencao) = :year_4
        
        UNION ALL
        
        SELECT 
            'Contas Pagas' as categoria,
            SUM(valor) as total
        FROM contas_pagar
        WHERE empresa_id = :empresa_id_5 
        AND YEAR(data_pagamento) = :year_5
        
        UNION ALL
        
        SELECT 
            'Parcelas de Financiamento' as categoria,
            SUM(valor) as total
        FROM parcelas_financiamento
        WHERE empresa_id = :empresa_id_6 
        AND YEAR(data_vencimento) = :year_6
        AND status_id = 2";
    
    $stmt = $conn->prepare($query);
    
    // Bind parameters for each subquery
    for ($i = 1; $i <= 6; $i++) {
        $stmt->bindParam(':empresa_id_' . $i, $empresa_id);
        $stmt->bindParam(':year_' . $i, $current_year);
    }
    
    $stmt->execute();
    $expenses_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Preparar os dados para o gráfico
    $labels = [];
    $data = [];
    $backgroundColors = [
        '#FF6384', // Rosa
        '#36A2EB', // Azul
        '#FFCE56', // Amarelo
        '#4BC0C0', // Turquesa
        '#9966FF', // Roxo
        '#FF9F40'  // Laranja
    ];
    
    foreach ($expenses_data as $row) {
        if ($row['total'] > 0) { // Só incluir categorias com valores positivos
            $labels[] = $row['categoria'];
            $data[] = floatval($row['total']);
        }
    }
    
    // Retornar os dados no formato esperado pelo Chart.js
    echo json_encode([
        'labels' => $labels,
        'datasets' => [
            [
                'data' => $data,
                'backgroundColor' => array_slice($backgroundColors, 0, count($labels)),
                'borderWidth' => 1
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Expenses Distribution API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar dados de distribuição de despesas: ' . $e->getMessage()]);
    exit;
} 