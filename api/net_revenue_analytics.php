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
    $current_year = date('Y');
    
    // Query para buscar receitas (fretes) por mês
    $query_receitas = "
        SELECT 
            MONTH(data_rota) as mes,
            SUM(frete) as total_frete
        FROM rotas
        WHERE empresa_id = :empresa_id_receitas
        AND YEAR(data_rota) = :year_receitas
        AND no_prazo = 1
        GROUP BY MONTH(data_rota)
        ORDER BY mes";
    
    // Query para buscar despesas por mês
    $query_despesas = "
        SELECT 
            mes,
            SUM(total) as total_despesas
        FROM (
            -- Despesas de Viagem
            SELECT 
                MONTH(created_at) as mes,
                SUM(COALESCE(arla,0) + COALESCE(pedagios,0) + COALESCE(caixinha,0) + 
                    COALESCE(estacionamento,0) + COALESCE(lavagem,0) + COALESCE(borracharia,0) + 
                    COALESCE(eletrica_mecanica,0) + COALESCE(adiantamento,0)) as total
            FROM despesas_viagem
            WHERE empresa_id = :empresa_id_viagem 
            AND YEAR(created_at) = :year_viagem
            GROUP BY MONTH(created_at)
            
            UNION ALL
            
            -- Despesas Fixas
            SELECT 
                MONTH(vencimento) as mes,
                SUM(valor) as total
            FROM despesas_fixas
            WHERE empresa_id = :empresa_id_fixas 
            AND YEAR(vencimento) = :year_fixas
            GROUP BY MONTH(vencimento)
            
            UNION ALL
            
            -- Manutenções de Veículos
            SELECT 
                MONTH(data_manutencao) as mes,
                SUM(valor) as total
            FROM manutencoes
            WHERE empresa_id = :empresa_id_manut 
            AND YEAR(data_manutencao) = :year_manut
            GROUP BY MONTH(data_manutencao)
            
            UNION ALL
            
            -- Manutenções de Pneus
            SELECT 
                MONTH(data_manutencao) as mes,
                SUM(custo) as total
            FROM pneu_manutencao
            WHERE empresa_id = :empresa_id_pneus 
            AND YEAR(data_manutencao) = :year_pneus
            GROUP BY MONTH(data_manutencao)
            
            UNION ALL
            
            -- Contas Pagas
            SELECT 
                MONTH(data_pagamento) as mes,
                SUM(valor) as total
            FROM contas_pagar
            WHERE empresa_id = :empresa_id_contas 
            AND YEAR(data_pagamento) = :year_contas
            GROUP BY MONTH(data_pagamento)
            
            UNION ALL
            
            -- Parcelas de Financiamento
            SELECT 
                MONTH(data_vencimento) as mes,
                SUM(valor) as total
            FROM parcelas_financiamento
            WHERE empresa_id = :empresa_id_financ 
            AND YEAR(data_vencimento) = :year_financ
            AND status_id = 2
            GROUP BY MONTH(data_vencimento)
        ) as todas_despesas
        GROUP BY mes
        ORDER BY mes";
    
    // Query para buscar comissões por mês
    $query_comissoes = "
        SELECT 
            MONTH(data_rota) as mes,
            SUM(comissao) as total_comissao
        FROM rotas
        WHERE empresa_id = :empresa_id_comissoes
        AND YEAR(data_rota) = :year_comissoes
        AND no_prazo = 1
        GROUP BY MONTH(data_rota)
        ORDER BY mes";
    
    // Executar queries
    $stmt_receitas = $conn->prepare($query_receitas);
    $stmt_despesas = $conn->prepare($query_despesas);
    $stmt_comissoes = $conn->prepare($query_comissoes);
    
    // Bind parameters para cada query
    $stmt_receitas->bindParam(':empresa_id_receitas', $empresa_id);
    $stmt_receitas->bindParam(':year_receitas', $current_year);
    
    // Bind parameters para despesas
    $stmt_despesas->bindParam(':empresa_id_viagem', $empresa_id);
    $stmt_despesas->bindParam(':year_viagem', $current_year);
    $stmt_despesas->bindParam(':empresa_id_fixas', $empresa_id);
    $stmt_despesas->bindParam(':year_fixas', $current_year);
    $stmt_despesas->bindParam(':empresa_id_manut', $empresa_id);
    $stmt_despesas->bindParam(':year_manut', $current_year);
    $stmt_despesas->bindParam(':empresa_id_pneus', $empresa_id);
    $stmt_despesas->bindParam(':year_pneus', $current_year);
    $stmt_despesas->bindParam(':empresa_id_contas', $empresa_id);
    $stmt_despesas->bindParam(':year_contas', $current_year);
    $stmt_despesas->bindParam(':empresa_id_financ', $empresa_id);
    $stmt_despesas->bindParam(':year_financ', $current_year);
    
    $stmt_comissoes->bindParam(':empresa_id_comissoes', $empresa_id);
    $stmt_comissoes->bindParam(':year_comissoes', $current_year);
    
    $stmt_receitas->execute();
    $stmt_despesas->execute();
    $stmt_comissoes->execute();
    
    $receitas_data = $stmt_receitas->fetchAll(PDO::FETCH_ASSOC);
    $despesas_data = $stmt_despesas->fetchAll(PDO::FETCH_ASSOC);
    $comissoes_data = $stmt_comissoes->fetchAll(PDO::FETCH_ASSOC);
    
    // Preparar os dados para o gráfico
    $labels = [];
    $data = [];
    
    // Array com nomes dos meses em português
    $meses = [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Março',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro'
    ];
    
    // Inicializar arrays com zero
    $receitas = array_fill(1, 12, 0);
    $despesas = array_fill(1, 12, 0);
    $comissoes = array_fill(1, 12, 0);
    
    // Preencher dados reais
    foreach ($receitas_data as $row) {
        $receitas[$row['mes']] = floatval($row['total_frete']);
    }
    
    foreach ($despesas_data as $row) {
        $despesas[$row['mes']] = floatval($row['total_despesas']);
    }
    
    foreach ($comissoes_data as $row) {
        $comissoes[$row['mes']] = floatval($row['total_comissao']);
    }
    
    // Calcular lucro líquido por mês
    for ($i = 1; $i <= 12; $i++) {
        $labels[] = $meses[$i];
        $lucro = $receitas[$i] - ($despesas[$i] + $comissoes[$i]);
        $data[] = $lucro;
    }
    
    // Retornar os dados no formato esperado pelo Chart.js
    echo json_encode([
        'labels' => $labels,
        'datasets' => [
            [
                'label' => 'Faturamento Líquido',
                'data' => $data,
                'borderColor' => '#2ecc40',
                'backgroundColor' => 'rgba(46, 204, 64, 0.1)',
                'fill' => true,
                'tension' => 0.4
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Net Revenue Analytics API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar dados de faturamento líquido: ' . $e->getMessage()]);
    exit;
} 