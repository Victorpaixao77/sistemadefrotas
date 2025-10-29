<?php
/**
 * API - Resumo de Lucratividade
 * Retorna dados dos cards principais com filtro de período
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/auth.php';

verificarLogin();
$empresa_id = obterEmpresaId();

try {
    $db = getDB();
    
    // Buscar dados da empresa
    $stmt = $db->prepare("SELECT porcentagem_fixa FROM seguro_empresa_clientes WHERE id = ?");
    $stmt->execute([$empresa_id]);
    $porcentagem_fixa = floatval($stmt->fetchColumn());
    
    // Obter período dos parâmetros
    $dataInicio = $_GET['dataInicio'] ?? null;
    $dataFim = $_GET['dataFim'] ?? null;
    
    // Comissão Total do período
    $sql = "
        SELECT 
            COALESCE(SUM(
                (sf.valor_pago * ? / 100) + (sf.valor_pago * c.porcentagem_recorrencia / 100)
            ), 0) as total_comissao
        FROM seguro_financeiro sf
        INNER JOIN seguro_clientes c ON (COALESCE(NULLIF(sf.seguro_cliente_id, 0), sf.cliente_id) = c.id)
        WHERE sf.seguro_empresa_id = ?
          AND sf.status = 'pago'
          AND sf.valor_pago > 0
          AND sf.data_baixa IS NOT NULL
          AND (sf.cliente_nao_encontrado IS NULL OR sf.cliente_nao_encontrado = 'nao')
    ";
    
    $params = [$porcentagem_fixa, $empresa_id];
    
    // Adicionar filtro de período se fornecido
    if ($dataInicio && $dataFim) {
        $sql .= " AND DATE_FORMAT(sf.data_baixa, '%Y-%m') >= ?";
        $sql .= " AND DATE_FORMAT(sf.data_baixa, '%Y-%m') <= ?";
        $params[] = $dataInicio;
        $params[] = $dataFim;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $comissaoTotal = floatval($stmt->fetchColumn());
    
    // Comissão do último mês do período (ou mês atual se sem filtro)
    $mesReferencia = $dataFim ?? date('Y-m');
    
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(
                (sf.valor_pago * ? / 100) + (sf.valor_pago * c.porcentagem_recorrencia / 100)
            ), 0) as comissao_mes
        FROM seguro_financeiro sf
        INNER JOIN seguro_clientes c ON (COALESCE(NULLIF(sf.seguro_cliente_id, 0), sf.cliente_id) = c.id)
        WHERE sf.seguro_empresa_id = ?
          AND sf.status = 'pago'
          AND sf.valor_pago > 0
          AND sf.data_baixa IS NOT NULL
          AND DATE_FORMAT(sf.data_baixa, '%Y-%m') = ?
          AND (sf.cliente_nao_encontrado IS NULL OR sf.cliente_nao_encontrado = 'nao')
    ");
    $stmt->execute([$porcentagem_fixa, $empresa_id, $mesReferencia]);
    $comissaoMes = floatval($stmt->fetchColumn());
    
    // Total de clientes ativos
    $stmt = $db->prepare("SELECT COUNT(*) FROM seguro_clientes WHERE seguro_empresa_id = ? AND situacao = 'ativo'");
    $stmt->execute([$empresa_id]);
    $clientesAtivos = intval($stmt->fetchColumn());
    
    // Ticket médio (comissão total / clientes ativos)
    $ticketMedio = $clientesAtivos > 0 ? $comissaoTotal / $clientesAtivos : 0;
    
    // Calcular meses no período para ticket médio mensal
    if ($dataInicio && $dataFim) {
        $inicio = new DateTime($dataInicio . '-01');
        $fim = new DateTime($dataFim . '-01');
        $intervalo = $inicio->diff($fim);
        $meses_periodo = ($intervalo->y * 12) + $intervalo->m + 1;
        
        // Ticket médio mensal por cliente
        if ($clientesAtivos > 0 && $meses_periodo > 0) {
            $ticketMedio = ($comissaoTotal / $meses_periodo) / $clientesAtivos;
        }
    }
    
    echo json_encode([
        'sucesso' => true,
        'comissao_total' => round($comissaoTotal, 2),
        'comissao_mes' => round($comissaoMes, 2),
        'clientes_ativos' => $clientesAtivos,
        'ticket_medio' => round($ticketMedio, 2),
        'mes_referencia' => $mesReferencia
    ]);
    
} catch (Exception $e) {
    error_log("Erro em lucratividade_resumo: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao buscar dados',
        'comissao_total' => 0,
        'comissao_mes' => 0,
        'clientes_ativos' => 0,
        'ticket_medio' => 0
    ]);
}
?>

