<?php
/**
 * API - Calcular Comissão por Período
 * Calcula a comissão total para um período específico
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/auth.php';

// Verificar se está logado
verificarLogin();

$db = getDB();
$empresa_id = obterEmpresaId();

// Verificar parâmetros
if (!isset($_GET['data_inicio']) || !isset($_GET['data_fim'])) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Datas de início e fim são obrigatórias'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data_inicio = $_GET['data_inicio'];
$data_fim = $_GET['data_fim'];

try {
    // Buscar porcentagem fixa da empresa
    $stmt = $db->prepare("
        SELECT porcentagem_fixa 
        FROM seguro_empresa_clientes 
        WHERE id = ?
    ");
    $stmt->execute([$empresa_id]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    $percentual_empresa = floatval($empresa['porcentagem_fixa'] ?? 0);
    
    // Calcular comissão do período
    // IMPORTANTE: Calcula comissão APENAS de documentos com cliente vinculado (não em quarentena)
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_documentos,
            COALESCE(SUM(sf.valor_pago), 0) as valor_total_pago,
            COALESCE(SUM(
                sf.valor_pago * ((? + COALESCE(sc.porcentagem_recorrencia, 0)) / 100)
            ), 0) as comissao_periodo
        FROM seguro_financeiro sf
        INNER JOIN seguro_clientes sc ON sf.cliente_id = sc.id
        WHERE sf.seguro_empresa_id = ? 
        AND sf.valor_pago > 0
        AND sf.data_baixa IS NOT NULL
        AND (sf.cliente_nao_encontrado IS NULL OR sf.cliente_nao_encontrado = 'nao')
        AND sf.data_baixa BETWEEN ? AND ?
    ");
    $stmt->execute([$percentual_empresa, $empresa_id, $data_inicio, $data_fim]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'sucesso' => true,
        'comissao' => floatval($resultado['comissao_periodo']),
        'total_documentos' => intval($resultado['total_documentos']),
        'valor_total_pago' => floatval($resultado['valor_total_pago']),
        'percentual_empresa' => $percentual_empresa,
        'periodo' => [
            'inicio' => $data_inicio,
            'fim' => $data_fim
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro ao calcular comissão: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao calcular comissão: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

