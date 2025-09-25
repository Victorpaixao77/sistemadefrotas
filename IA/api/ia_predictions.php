<?php
// Verificar se está sendo executado via web
if (php_sapi_name() === 'cli') {
    echo "Esta API deve ser executada via web server\n";
    exit();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/db_connect.php';
    require_once __DIR__ . '/../../includes/functions.php';

    // Configure session
    configure_session();
    session_start();

    // Verificar autenticação
    if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autorizado']);
        exit();
    }

    $empresa_id = $_SESSION['empresa_id'];
    $conn = getConnection();
    
    $predictions = [];
    
    // 1. Previsão de Combustível (próximos 30 dias)
    $stmt = $conn->prepare("
        SELECT 
            AVG(daily_consumption) as media_diaria,
            STDDEV(daily_consumption) as desvio_padrao
        FROM (
            SELECT 
                DATE(a.data_abastecimento) as data,
                SUM(a.valor_total) as daily_consumption
            FROM abastecimentos a
            JOIN rotas r ON a.rota_id = r.id
            JOIN veiculos v ON r.veiculo_id = v.id
            WHERE v.empresa_id = ?
            AND a.data_abastecimento >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(a.data_abastecimento)
        ) as daily_stats
    ");
    $stmt->execute([$empresa_id]);
    $fuel_stats = $stmt->fetch();
    
    $media_diaria = $fuel_stats['media_diaria'] ?: 0;
    $desvio_padrao = $fuel_stats['desvio_padrao'] ?: 0;
    $previsao_combustivel = ($media_diaria * 30) + ($desvio_padrao * 2);
    
    $predictions['combustivel'] = 'R$ ' . number_format($previsao_combustivel, 2, ',', '.');
    
    // 2. Previsão de Manutenção (próximos 30 dias)
    $stmt = $conn->prepare("
        SELECT 
            AVG(daily_maintenance) as media_diaria,
            STDDEV(daily_maintenance) as desvio_padrao
        FROM (
            SELECT 
                DATE(m.data_manutencao) as data,
                SUM(m.valor) as daily_maintenance
            FROM manutencoes m
            JOIN veiculos v ON m.veiculo_id = v.id
            WHERE v.empresa_id = ?
            AND m.data_manutencao >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(m.data_manutencao)
        ) as daily_stats
    ");
    $stmt->execute([$empresa_id]);
    $maintenance_stats = $stmt->fetch();
    
    $media_manutencao = $maintenance_stats['media_diaria'] ?: 0;
    $desvio_manutencao = $maintenance_stats['desvio_padrao'] ?: 0;
    $previsao_manutencao = ($media_manutencao * 30) + ($desvio_manutencao * 1.5);
    
    $predictions['manutencao'] = 'R$ ' . number_format($previsao_manutencao, 2, ',', '.');
    
    // 3. Eficiência de Rotas (próximos 7 dias)
    $stmt = $conn->prepare("
        SELECT 
            AVG(r.distancia_km / NULLIF(a.litros, 0)) as eficiencia_media,
            COUNT(DISTINCT r.id) as total_rotas
        FROM rotas r
        JOIN veiculos v ON r.veiculo_id = v.id
        LEFT JOIN abastecimentos a ON r.id = a.rota_id
        WHERE v.empresa_id = ?
        AND r.data_saida >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND a.litros > 0
    ");
    $stmt->execute([$empresa_id]);
    $efficiency_stats = $stmt->fetch();
    
    $eficiencia_atual = $efficiency_stats['eficiencia_media'] ?: 0;
    $tendencia_eficiencia = $eficiencia_atual > 10 ? 'alta' : ($eficiencia_atual > 7 ? 'media' : 'baixa');
    
    $predictions['eficiencia'] = round($eficiencia_atual, 1) . '%';
    
    // 4. Risco de Falhas (próximos 14 dias)
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_alertas,
            COUNT(CASE WHEN prioridade = 'alta' THEN 1 END) as alertas_alta,
            COUNT(CASE WHEN tipo = 'manutencao' THEN 1 END) as alertas_manutencao
        FROM alertas_sistema
        WHERE empresa_id = ?
        AND status = 'ativo'
        AND data_criacao >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$empresa_id]);
    $risk_stats = $stmt->fetch();
    
    $total_alertas = $risk_stats['total_alertas'] ?: 0;
    $alertas_alta = $risk_stats['alertas_alta'] ?: 0;
    $alertas_manutencao = $risk_stats['alertas_manutencao'] ?: 0;
    
    // Cálculo do risco baseado em alertas
    $risco_base = min(($total_alertas * 2) + ($alertas_alta * 5) + ($alertas_manutencao * 3), 100);
    $risco_ajustado = max($risco_base, 5); // Mínimo de 5%
    
    $predictions['risco'] = $risco_ajustado . '%';
    
    // 5. Tendências
    $predictions['tendencia_combustivel'] = $previsao_combustivel > ($media_diaria * 30) ? 'alta' : 'baixa';
    $predictions['tendencia_manutencao'] = $previsao_manutencao > ($media_manutencao * 30) ? 'alta' : 'baixa';
    $predictions['tendencia_eficiencia'] = $tendencia_eficiencia;
    $predictions['tendencia_risco'] = $risco_ajustado > 50 ? 'alta' : 'baixa';
    
    // 6. Insights Preditivos
    $insights = [];
    
    if ($risco_ajustado > 70) {
        $insights[] = "Alto risco de falhas detectado. Considere revisão preventiva.";
    }
    
    if ($eficiencia_atual < 8) {
        $insights[] = "Eficiência baixa detectada. Treinamento de motoristas recomendado.";
    }
    
    if ($previsao_combustivel > ($media_diaria * 30 * 1.2)) {
        $insights[] = "Aumento previsto no consumo de combustível. Verifique rotas.";
    }
    
    if (empty($insights)) {
        $insights[] = "Sistema operando dentro dos parâmetros normais.";
    }
    
    $predictions['insights'] = $insights;
    
    // 7. Recomendações Específicas
    $recomendacoes = [];
    
    if ($alertas_manutencao > 3) {
        $recomendacoes[] = "Agendar manutenção preventiva para evitar falhas";
    }
    
    if ($eficiencia_atual < 10) {
        $recomendacoes[] = "Implementar treinamento de eco-driving";
    }
    
    if ($risco_ajustado > 60) {
        $recomendacoes[] = "Revisar políticas de manutenção e monitoramento";
    }
    
    $predictions['recomendacoes'] = $recomendacoes;
    
    echo json_encode([
        'success' => true,
        'predictions' => $predictions,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Erro na API de previsões: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno do servidor',
        'message' => $e->getMessage()
    ]);
}
?>
