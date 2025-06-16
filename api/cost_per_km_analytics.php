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
    
    // Query para buscar despesas por dia
    $query_despesas = "
        SELECT 
            DAY(data) as dia,
            SUM(total) as total_despesas
        FROM (
            -- Despesas de Viagem
            SELECT 
                created_at as data,
                COALESCE(arla,0) + COALESCE(pedagios,0) + COALESCE(caixinha,0) + 
                COALESCE(estacionamento,0) + COALESCE(lavagem,0) + COALESCE(borracharia,0) + 
                COALESCE(eletrica_mecanica,0) + COALESCE(adiantamento,0) as total
            FROM despesas_viagem
            WHERE empresa_id = :empresa_id_viagem 
            AND MONTH(created_at) = :month_viagem
            AND YEAR(created_at) = :year_viagem
            
            UNION ALL
            
            -- Despesas Fixas
            SELECT 
                vencimento as data,
                valor as total
            FROM despesas_fixas
            WHERE empresa_id = :empresa_id_fixas 
            AND MONTH(vencimento) = :month_fixas
            AND YEAR(vencimento) = :year_fixas
            
            UNION ALL
            
            -- Manutenções de Veículos
            SELECT 
                data_manutencao as data,
                valor as total
            FROM manutencoes
            WHERE empresa_id = :empresa_id_manut 
            AND MONTH(data_manutencao) = :month_manut
            AND YEAR(data_manutencao) = :year_manut
            
            UNION ALL
            
            -- Manutenções de Pneus
            SELECT 
                data_manutencao as data,
                custo as total
            FROM pneu_manutencao
            WHERE empresa_id = :empresa_id_pneus 
            AND MONTH(data_manutencao) = :month_pneus
            AND YEAR(data_manutencao) = :year_pneus
            
            UNION ALL
            
            -- Parcelas de Financiamento
            SELECT 
                data_vencimento as data,
                valor as total
            FROM parcelas_financiamento
            WHERE empresa_id = :empresa_id_financ 
            AND MONTH(data_vencimento) = :month_financ
            AND YEAR(data_vencimento) = :year_financ
            AND status_id = 2

            UNION ALL

            -- Abastecimentos
            SELECT 
                data_abastecimento as data,
                valor_total as total
            FROM abastecimentos
            WHERE empresa_id = :empresa_id_abast
            AND MONTH(data_abastecimento) = :month_abast
            AND YEAR(data_abastecimento) = :year_abast
        ) as todas_despesas
        GROUP BY DAY(data)
        ORDER BY dia";
    
    // Query para buscar quilometragem por dia
    $query_km = "
        SELECT 
            DAY(data_rota) as dia,
            SUM(km_chegada - km_saida) as total_km
        FROM rotas
        WHERE empresa_id = :empresa_id_km
        AND MONTH(data_rota) = :month_km
        AND YEAR(data_rota) = :year_km
        AND no_prazo = 1
        GROUP BY DAY(data_rota)
        ORDER BY dia";
    
    // Executar queries
    $stmt_despesas = $conn->prepare($query_despesas);
    $stmt_km = $conn->prepare($query_km);
    
    // Bind parameters para despesas
    $stmt_despesas->bindParam(':empresa_id_viagem', $empresa_id);
    $stmt_despesas->bindParam(':month_viagem', $current_month);
    $stmt_despesas->bindParam(':year_viagem', $current_year);
    $stmt_despesas->bindParam(':empresa_id_fixas', $empresa_id);
    $stmt_despesas->bindParam(':month_fixas', $current_month);
    $stmt_despesas->bindParam(':year_fixas', $current_year);
    $stmt_despesas->bindParam(':empresa_id_manut', $empresa_id);
    $stmt_despesas->bindParam(':month_manut', $current_month);
    $stmt_despesas->bindParam(':year_manut', $current_year);
    $stmt_despesas->bindParam(':empresa_id_pneus', $empresa_id);
    $stmt_despesas->bindParam(':month_pneus', $current_month);
    $stmt_despesas->bindParam(':year_pneus', $current_year);
    $stmt_despesas->bindParam(':empresa_id_financ', $empresa_id);
    $stmt_despesas->bindParam(':month_financ', $current_month);
    $stmt_despesas->bindParam(':year_financ', $current_year);
    $stmt_despesas->bindParam(':empresa_id_abast', $empresa_id);
    $stmt_despesas->bindParam(':month_abast', $current_month);
    $stmt_despesas->bindParam(':year_abast', $current_year);
    
    // Bind parameters para quilometragem
    $stmt_km->bindParam(':empresa_id_km', $empresa_id);
    $stmt_km->bindParam(':month_km', $current_month);
    $stmt_km->bindParam(':year_km', $current_year);
    
    $stmt_despesas->execute();
    $stmt_km->execute();
    
    $despesas_data = $stmt_despesas->fetchAll(PDO::FETCH_ASSOC);
    $km_data = $stmt_km->fetchAll(PDO::FETCH_ASSOC);
    
    // Preparar os dados para o gráfico
    $labels = [];
    $data = [];
    
    // Inicializar arrays com zero
    $despesas = array_fill(1, 31, 0);
    $km = array_fill(1, 31, 0);
    
    // Preencher dados reais
    foreach ($despesas_data as $row) {
        $despesas[$row['dia']] = floatval($row['total_despesas']);
    }
    
    foreach ($km_data as $row) {
        $km[$row['dia']] = floatval($row['total_km']);
    }
    
    // Calcular custo por km por dia
    for ($i = 1; $i <= 31; $i++) {
        $labels[] = $i;
        if ($km[$i] > 0) {
            $custo_por_km = $despesas[$i] / $km[$i];
            $data[] = $custo_por_km;
        } else {
            $data[] = 0;
        }
    }
    
    // Retornar os dados no formato esperado pelo Chart.js
    echo json_encode([
        'labels' => $labels,
        'datasets' => [
            [
                'label' => 'Custo por KM',
                'data' => $data,
                'borderColor' => '#e74c3c',
                'backgroundColor' => 'rgba(231, 76, 60, 0.1)',
                'fill' => true,
                'tension' => 0.4
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Cost per KM Analytics API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar dados de custo por KM: ' . $e->getMessage()]);
    exit;
} 