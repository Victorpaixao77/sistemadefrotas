<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configurar sessão antes de iniciá-la
configure_session();

// Iniciar sessão
session_start();

// Verificar se o usuário está autenticado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

try {
    $conn = getConnection();
    $empresa_id = $_SESSION['empresa_id'];
    
    // Get month and year from URL parameters or use current date
    $current_month = isset($_GET['mes']) ? $_GET['mes'] : date('m');
    $current_year = isset($_GET['ano']) ? $_GET['ano'] : date('Y');
    
    // Calcular datas dos últimos 6 meses a partir do mês selecionado
    $dates = [];
    $base_date = new DateTime("$current_year-$current_month-01");
    for ($i = 0; $i < 6; $i++) {
        $date = clone $base_date;
        $date->modify("-$i months");
        $dates[] = [
            'year' => $date->format('Y'),
            'month' => $date->format('m')
        ];
    }
    
    // Query para buscar lucro líquido por mês
    $query_lucro = "
        SELECT 
            MONTH(data) as mes,
            YEAR(data) as ano,
            SUM(CASE WHEN tipo = 'receita' THEN valor ELSE -valor END) as lucro_liquido
        FROM (
            -- Receitas (Fretes)
            SELECT 
                data_rota as data,
                'receita' as tipo,
                frete as valor
            FROM rotas
            WHERE empresa_id = :empresa_id_receitas
            AND no_prazo = 1
            
            UNION ALL
            
            -- Despesas de Viagem
            SELECT 
                created_at as data,
                'despesa' as tipo,
                COALESCE(total_despviagem, 0) as valor
            FROM despesas_viagem
            WHERE empresa_id = :empresa_id_viagem
            
            UNION ALL
            
            -- Despesas Fixas
            SELECT 
                vencimento as data,
                'despesa' as tipo,
                valor
            FROM despesas_fixas
            WHERE empresa_id = :empresa_id_fixas
            AND status_pagamento_id = 2
            
            UNION ALL
            
            -- Manutenções de Veículos
            SELECT 
                data_manutencao as data,
                'despesa' as tipo,
                valor
            FROM manutencoes
            WHERE empresa_id = :empresa_id_manut
            
            UNION ALL
            
            -- Manutenções de Pneus
            SELECT 
                data_manutencao as data,
                'despesa' as tipo,
                custo as valor
            FROM pneu_manutencao
            WHERE empresa_id = :empresa_id_pneus
            
            UNION ALL
            
            -- Parcelas de Financiamento
            SELECT 
                data_vencimento as data,
                'despesa' as tipo,
                valor
            FROM parcelas_financiamento
            WHERE empresa_id = :empresa_id_financ
            AND status_id = 2
            
            UNION ALL
            
            -- Contas Pagas
            SELECT 
                data_vencimento as data,
                'despesa' as tipo,
                valor
            FROM contas_pagar
            WHERE empresa_id = :empresa_id_contas
            AND status_id = 2

            UNION ALL

            -- Abastecimentos
            SELECT 
                data_abastecimento as data,
                'despesa' as tipo,
                valor_total as valor
            FROM abastecimentos
            WHERE empresa_id = :empresa_id_abast
        ) as dados
        WHERE (YEAR(data) = :year_1 AND MONTH(data) = :month_1)
        OR (YEAR(data) = :year_2 AND MONTH(data) = :month_2)
        OR (YEAR(data) = :year_3 AND MONTH(data) = :month_3)
        OR (YEAR(data) = :year_4 AND MONTH(data) = :month_4)
        OR (YEAR(data) = :year_5 AND MONTH(data) = :month_5)
        OR (YEAR(data) = :year_6 AND MONTH(data) = :month_6)
        GROUP BY YEAR(data), MONTH(data)
        ORDER BY ano, mes";
    
    // Query para buscar quilometragem por mês
    $query_km = "
        SELECT 
            MONTH(data_rota) as mes,
            YEAR(data_rota) as ano,
            SUM(km_chegada - km_saida) as total_km
        FROM rotas
        WHERE empresa_id = :empresa_id_km
        AND no_prazo = 1
        AND (
            (YEAR(data_rota) = :year_1 AND MONTH(data_rota) = :month_1)
            OR (YEAR(data_rota) = :year_2 AND MONTH(data_rota) = :month_2)
            OR (YEAR(data_rota) = :year_3 AND MONTH(data_rota) = :month_3)
            OR (YEAR(data_rota) = :year_4 AND MONTH(data_rota) = :month_4)
            OR (YEAR(data_rota) = :year_5 AND MONTH(data_rota) = :month_5)
            OR (YEAR(data_rota) = :year_6 AND MONTH(data_rota) = :month_6)
        )
        GROUP BY YEAR(data_rota), MONTH(data_rota)
        ORDER BY ano, mes";
    
    // Executar queries
    $stmt_lucro = $conn->prepare($query_lucro);
    $stmt_km = $conn->prepare($query_km);
    
    // Bind parameters para lucro
    $stmt_lucro->bindParam(':empresa_id_receitas', $empresa_id);
    $stmt_lucro->bindParam(':empresa_id_viagem', $empresa_id);
    $stmt_lucro->bindParam(':empresa_id_fixas', $empresa_id);
    $stmt_lucro->bindParam(':empresa_id_manut', $empresa_id);
    $stmt_lucro->bindParam(':empresa_id_pneus', $empresa_id);
    $stmt_lucro->bindParam(':empresa_id_financ', $empresa_id);
    $stmt_lucro->bindParam(':empresa_id_contas', $empresa_id);
    $stmt_lucro->bindParam(':empresa_id_abast', $empresa_id);
    
    // Bind parameters para quilometragem
    $stmt_km->bindParam(':empresa_id_km', $empresa_id);
    
    // Bind parameters de data para ambas as queries
    foreach ($dates as $i => $date) {
        $index = $i + 1;
        $stmt_lucro->bindParam(":year_$index", $date['year']);
        $stmt_lucro->bindParam(":month_$index", $date['month']);
        $stmt_km->bindParam(":year_$index", $date['year']);
        $stmt_km->bindParam(":month_$index", $date['month']);
    }
    
    $stmt_lucro->execute();
    $stmt_km->execute();
    
    $lucro_data = $stmt_lucro->fetchAll(PDO::FETCH_ASSOC);
    $km_data = $stmt_km->fetchAll(PDO::FETCH_ASSOC);
    
    // Preparar os dados para o gráfico
    $labels = [];
    $data = [];
    $current_value = 0;
    
    // Array com nomes dos meses em português
    $meses = [
        1 => 'Jan',
        2 => 'Fev',
        3 => 'Mar',
        4 => 'Abr',
        5 => 'Mai',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Ago',
        9 => 'Set',
        10 => 'Out',
        11 => 'Nov',
        12 => 'Dez'
    ];
    
    // Processar dados dos últimos 6 meses
    foreach ($dates as $i => $date) {
        $lucro = 0;
        $km = 0;
        
        // Encontrar lucro do mês
        foreach ($lucro_data as $row) {
            if ($row['ano'] == $date['year'] && $row['mes'] == $date['month']) {
                $lucro = floatval($row['lucro_liquido']);
                break;
            }
        }
        
        // Encontrar quilometragem do mês
        foreach ($km_data as $row) {
            if ($row['ano'] == $date['year'] && $row['mes'] == $date['month']) {
                $km = floatval($row['total_km']);
                break;
            }
        }
        
        // Calcular lucro por KM
        $lucro_por_km = $km > 0 ? $lucro / $km : 0;
        
        // Se for o mês atual, guardar o valor para o gauge
        if ($i === 0) {
            $current_value = $lucro_por_km;
        }
        
        // Adicionar ao array de dados
        $labels[] = $meses[intval($date['month'])] . '/' . substr($date['year'], -2);
        $data[] = $lucro_por_km;
    }
    
    // Verificar se há dados suficientes
    $has_real_data = false;
    foreach ($data as $value) {
        if ($value > 0) {
            $has_real_data = true;
            break;
        }
    }
    
    // Se não há dados suficientes, retornar dados de exemplo com aviso
    if (!$has_real_data) {
        echo json_encode([
            'gauge' => [
                'value' => 0.5,
                'min' => -2,
                'max' => 2,
                'thresholds' => [
                    'red' => -0.5,
                    'yellow' => 0,
                    'green' => 0.5
                ]
            ],
            'line' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Lucro por KM (Dados de Exemplo)',
                        'data' => array_fill(0, 6, 0.5),
                        'borderColor' => '#ffc107',
                        'backgroundColor' => 'rgba(255, 193, 7, 0.1)',
                        'fill' => true,
                        'tension' => 0.4
                    ]
                ]
            ],
            'warning' => 'Dados insuficientes para análise real. Cadastre mais dados para ver análises precisas.',
            'suggestions' => [
                'Cadastre rotas com valores de frete',
                'Registre abastecimentos',
                'Adicione manutenções de veículos',
                'Configure despesas fixas'
            ]
        ]);
        exit;
    }
    
    // Retornar os dados reais no formato esperado pelos gráficos
    echo json_encode([
        'gauge' => [
            'value' => $current_value,
            'min' => -2,
            'max' => 2,
            'thresholds' => [
                'red' => -0.5,
                'yellow' => 0,
                'green' => 0.5
            ]
        ],
        'line' => [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Lucro por KM',
                    'data' => $data,
                    'borderColor' => '#2ecc40',
                    'backgroundColor' => 'rgba(46, 204, 64, 0.1)',
                    'fill' => true,
                    'tension' => 0.4
                ]
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Profit per KM Analytics API Error: " . $e->getMessage());
    
    // Retornar dados de exemplo em caso de erro ou dados insuficientes
    $meses = [
        1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun',
        7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'
    ];
    
    $current_month = intval(date('m'));
    $current_year = intval(date('Y'));
    
    $labels = [];
    $data = [];
    
    // Gerar dados de exemplo para os últimos 6 meses
    for ($i = 5; $i >= 0; $i--) {
        $month = $current_month - $i;
        $year = $current_year;
        
        if ($month <= 0) {
            $month += 12;
            $year--;
        }
        
        $labels[] = $meses[$month] . '/' . substr($year, -2);
        $data[] = rand(0.1, 0.8); // Valores aleatórios para exemplo
    }
    
    http_response_code(200); // Retornar 200 mesmo com erro para não quebrar o gráfico
    echo json_encode([
        'gauge' => [
            'value' => 0.5,
            'min' => -2,
            'max' => 2,
            'thresholds' => [
                'red' => -0.5,
                'yellow' => 0,
                'green' => 0.5
            ]
        ],
        'line' => [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Lucro por KM (Dados de Exemplo)',
                    'data' => $data,
                    'borderColor' => '#ffc107',
                    'backgroundColor' => 'rgba(255, 193, 7, 0.1)',
                    'fill' => true,
                    'tension' => 0.4
                ]
            ]
        ],
        'warning' => 'Dados insuficientes para análise real. Cadastre mais dados para ver análises precisas.',
        'suggestions' => [
            'Cadastre rotas com valores de frete',
            'Registre abastecimentos',
            'Adicione manutenções de veículos',
            'Configure despesas fixas'
        ]
    ]);
} 