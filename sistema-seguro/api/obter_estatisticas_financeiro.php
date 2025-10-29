<?php
/**
 * API - Obter Estatísticas do Financeiro
 * Retorna dados reais para os dashboards
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/auth.php';

// Verificar se está logado
verificarLogin();

try {
    $db = getDB();
    $empresa_id = obterEmpresaId();
    
    // Total de receitas (documentos pagos)
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_receitas,
            COALESCE(SUM(valor_pago), 0) as valor_total_receitas
        FROM seguro_financeiro 
        WHERE seguro_empresa_id = ?
        AND status = 'pago'
    ");
    $stmt->execute([$empresa_id]);
    $receitas = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Total de despesas pendentes
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_despesas,
            COALESCE(SUM(valor - valor_pago), 0) as valor_total_despesas
        FROM seguro_financeiro 
        WHERE seguro_empresa_id = ?
        AND status IN ('pendente', 'vencido')
    ");
    $stmt->execute([$empresa_id]);
    $despesas = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Saldo (receitas - despesas)
    $saldo = $receitas['valor_total_receitas'] - $despesas['valor_total_despesas'];
    
    // Total de documentos
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM seguro_financeiro 
        WHERE seguro_empresa_id = ?
    ");
    $stmt->execute([$empresa_id]);
    $totalDocs = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Documentos em quarentena
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_quarentena,
            COALESCE(SUM(valor), 0) as valor_quarentena
        FROM seguro_financeiro 
        WHERE seguro_empresa_id = ?
        AND cliente_nao_encontrado = 'sim'
    ");
    $stmt->execute([$empresa_id]);
    $quarentena = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Últimas transações
    $stmt = $db->prepare("
        SELECT 
            sf.numero_documento,
            sf.associado,
            sf.valor,
            sf.status,
            DATE_FORMAT(sf.data_vencimento, '%d/%m/%Y') as data,
            CASE 
                WHEN sf.cliente_nao_encontrado = 'sim' THEN 'Quarentena'
                ELSE sc.razao_social
            END as cliente
        FROM seguro_financeiro sf
        LEFT JOIN seguro_clientes sc ON sf.cliente_id = sc.id
        WHERE sf.seguro_empresa_id = ?
        ORDER BY sf.data_cadastro DESC
        LIMIT 10
    ");
    $stmt->execute([$empresa_id]);
    $ultimas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Dados para gráfico (últimos 6 meses)
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(data_vencimento, '%Y-%m') as mes,
            COUNT(*) as quantidade,
            SUM(CASE WHEN status = 'pago' THEN valor_pago ELSE 0 END) as recebido,
            SUM(CASE WHEN status IN ('pendente', 'vencido') THEN (valor - valor_pago) ELSE 0 END) as pendente
        FROM seguro_financeiro
        WHERE seguro_empresa_id = ?
        AND data_vencimento >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(data_vencimento, '%Y-%m')
        ORDER BY mes ASC
    ");
    $stmt->execute([$empresa_id]);
    $grafico = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'estatisticas' => [
            'receitas' => [
                'total' => (int)$receitas['total_receitas'],
                'valor' => (float)$receitas['valor_total_receitas']
            ],
            'despesas' => [
                'total' => (int)$despesas['total_despesas'],
                'valor' => (float)$despesas['valor_total_despesas']
            ],
            'saldo' => (float)$saldo,
            'total_documentos' => (int)$totalDocs['total'],
            'quarentena' => [
                'total' => (int)$quarentena['total_quarentena'],
                'valor' => (float)$quarentena['valor_quarentena']
            ]
        ],
        'ultimas_transacoes' => $ultimas,
        'grafico_mensal' => $grafico
    ], JSON_UNESCAPED_UNICODE);
    
} catch(PDOException $e) {
    error_log("Erro ao obter estatísticas: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar estatísticas'
    ], JSON_UNESCAPED_UNICODE);
}
?>

