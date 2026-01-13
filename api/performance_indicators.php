<?php
/**
 * API - Indicadores de Desempenho
 * Retorna dados dos últimos 12 meses para gráficos e indicadores
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../includes/config.php';
require_once '../includes/functions.php';

configure_session();
session_start();

if (!isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];

try {
    $conn = getConnection();
    
    // Gerar array com os últimos 12 meses
    $meses = [];
    $data_formatada = [];
    for ($i = 11; $i >= 0; $i--) {
        $data = date('Y-m', strtotime("-$i months"));
        $meses[] = $data;
        $data_formatada[$data] = [
            'mes_ano' => $data,
            'mes_nome' => date('M/Y', strtotime($data . '-01')),
            'total_abastecimentos' => 0,
            'total_gasto_abastecimentos' => 0,
            'total_rotas' => 0,
            'total_km_rodados' => 0,
            'total_frete' => 0,
            'total_comissao' => 0,
            'lucro_operacional' => 0,
            'total_despesas_viagem' => 0,
            'quantidade_veiculos_ativos' => 0
        ];
    }
    
    // 1. Total de Abastecimentos por mês (incluindo ARLA)
    $sql_abastecimentos = "
        SELECT 
            DATE_FORMAT(data_abastecimento, '%Y-%m') as mes_ano,
            COUNT(*) as total_abastecimentos,
            COALESCE(SUM(valor_total + COALESCE(
                CASE WHEN inclui_arla = 1 THEN valor_total_arla ELSE 0 END, 0
            )), 0) as total_gasto
        FROM abastecimentos
        WHERE empresa_id = :empresa_id
        AND status = 'aprovado'
        AND data_abastecimento >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(data_abastecimento, '%Y-%m')
    ";
    
    $stmt = $conn->prepare($sql_abastecimentos);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->execute();
    $abastecimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($abastecimentos as $row) {
        if (isset($data_formatada[$row['mes_ano']])) {
            $data_formatada[$row['mes_ano']]['total_abastecimentos'] = (int)$row['total_abastecimentos'];
            $data_formatada[$row['mes_ano']]['total_gasto_abastecimentos'] = (float)$row['total_gasto'];
        }
    }
    
    // 2. Total de Rotas por mês
    $sql_rotas = "
        SELECT 
            DATE_FORMAT(data_saida, '%Y-%m') as mes_ano,
            COUNT(*) as total_rotas,
            COALESCE(SUM(distancia_km), 0) as total_km,
            COALESCE(SUM(frete), 0) as total_frete,
            COALESCE(SUM(comissao), 0) as total_comissao
        FROM rotas
        WHERE empresa_id = :empresa_id
        AND data_saida IS NOT NULL
        AND data_saida >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(data_saida, '%Y-%m')
    ";
    
    $stmt = $conn->prepare($sql_rotas);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->execute();
    $rotas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($rotas as $row) {
        if (isset($data_formatada[$row['mes_ano']])) {
            $data_formatada[$row['mes_ano']]['total_rotas'] = (int)$row['total_rotas'];
            $data_formatada[$row['mes_ano']]['total_km_rodados'] = (float)$row['total_km'];
            $data_formatada[$row['mes_ano']]['total_frete'] = (float)$row['total_frete'];
            $data_formatada[$row['mes_ano']]['total_comissao'] = (float)$row['total_comissao'];
        }
    }
    
    // 3. Despesas de Viagem por mês
    $sql_despesas = "
        SELECT 
            DATE_FORMAT(r.data_saida, '%Y-%m') as mes_ano,
            COALESCE(SUM(dv.total_despviagem), 0) as total_despesas
        FROM despesas_viagem dv
        INNER JOIN rotas r ON r.id = dv.rota_id
        WHERE r.empresa_id = :empresa_id
        AND r.data_saida IS NOT NULL
        AND r.data_saida >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(r.data_saida, '%Y-%m')
    ";
    
    $stmt = $conn->prepare($sql_despesas);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->execute();
    $despesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($despesas as $row) {
        if (isset($data_formatada[$row['mes_ano']])) {
            $data_formatada[$row['mes_ano']]['total_despesas_viagem'] = (float)$row['total_despesas'];
        }
    }
    
    // Calcular lucro operacional por mês
    foreach ($data_formatada as &$mes) {
        $mes['lucro_operacional'] = $mes['total_frete'] - $mes['total_comissao'] - 
                                     $mes['total_gasto_abastecimentos'] - $mes['total_despesas_viagem'];
    }
    unset($mes);
    
    // 5. Quantidade de veículos ativos por mês (veículos que tiveram rotas)
    foreach ($meses as $mes) {
        $sql_veiculos_ativos = "
            SELECT COUNT(DISTINCT v.id) as quantidade
            FROM veiculos v
            INNER JOIN rotas r ON r.veiculo_id = v.id
            WHERE v.empresa_id = :empresa_id
            AND DATE_FORMAT(r.data_saida, '%Y-%m') = :mes
        ";
        $stmt = $conn->prepare($sql_veiculos_ativos);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':mes', $mes);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (isset($data_formatada[$mes])) {
            $data_formatada[$mes]['quantidade_veiculos_ativos'] = (int)($result['quantidade'] ?? 0);
        }
    }
    
    // 4. Veículos mais utilizados (últimos 12 meses)
    $sql_veiculos = "
        SELECT 
            v.id,
            v.placa,
            v.modelo,
            COUNT(DISTINCT r.id) as total_rotas,
            COALESCE(SUM(r.distancia_km), 0) as total_km
        FROM veiculos v
        INNER JOIN rotas r ON r.veiculo_id = v.id
        WHERE v.empresa_id = :empresa_id
        AND r.data_saida >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY v.id, v.placa, v.modelo
        ORDER BY total_rotas DESC, total_km DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($sql_veiculos);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->execute();
    $veiculos_top = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Converter array associativo para array indexado
    $resultado = array_values($data_formatada);
    
    // Dados do mês atual
    $mes_atual = date('Y-m');
    $dados_mes_atual = isset($data_formatada[$mes_atual]) ? $data_formatada[$mes_atual] : [
        'mes_ano' => $mes_atual,
        'mes_nome' => date('M/Y'),
        'total_abastecimentos' => 0,
        'total_gasto_abastecimentos' => 0,
        'total_rotas' => 0,
        'total_km_rodados' => 0,
        'total_frete' => 0,
        'total_comissao' => 0,
        'lucro_operacional' => 0,
        'total_despesas_viagem' => 0,
        'quantidade_veiculos_ativos' => 0
    ];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'historico_mensal' => $resultado,
            'mes_atual' => $dados_mes_atual,
            'veiculos_top' => $veiculos_top,
            'labels' => array_column($resultado, 'mes_nome')
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao buscar indicadores de desempenho: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar dados: ' . $e->getMessage()
    ]);
}

