<?php
require_once '../config/database.php';
require_once '../config/auth.php';

verificarLogin();
$empresa_id = obterEmpresaId();

try {
    $db = getDB();
    
    // Buscar porcentagem da empresa
    $stmt = $db->prepare("SELECT porcentagem_fixa FROM seguro_empresa_clientes WHERE id = ?");
    $stmt->execute([$empresa_id]);
    $porcentagem_fixa = floatval($stmt->fetchColumn());
    
    // Obter período dos parâmetros ou usar padrão
    $dataFim = $_GET['dataFim'] ?? date('Y-m');
    $dataInicio = $_GET['dataInicio'] ?? date('Y-m', strtotime("-11 months"));
    
    // Comissão do período
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(
            (sf.valor_pago * ? / 100) + (sf.valor_pago * c.porcentagem_recorrencia / 100)
        ), 0) as comissao
        FROM seguro_financeiro sf
        INNER JOIN seguro_clientes c ON (COALESCE(NULLIF(sf.seguro_cliente_id, 0), sf.cliente_id) = c.id)
        WHERE sf.seguro_empresa_id = ?
          AND sf.status = 'pago'
          AND sf.valor_pago > 0
          AND sf.data_baixa IS NOT NULL
          AND DATE_FORMAT(sf.data_baixa, '%Y-%m') >= ?
          AND DATE_FORMAT(sf.data_baixa, '%Y-%m') <= ?
          AND (sf.cliente_nao_encontrado IS NULL OR sf.cliente_nao_encontrado = 'nao')
    ");
    $stmt->execute([$porcentagem_fixa, $empresa_id, $dataInicio, $dataFim]);
    $comissao_periodo = floatval($stmt->fetchColumn());
    
    // Calcular meses no período
    $inicio = new DateTime($dataInicio . '-01');
    $fim = new DateTime($dataFim . '-01');
    $intervalo = $inicio->diff($fim);
    $meses_periodo = ($intervalo->y * 12) + $intervalo->m + 1;
    
    // Média mensal do período
    $media_mensal = $meses_periodo > 0 ? $comissao_periodo / $meses_periodo : 0;
    
    // Para crescimento, comparar último mês vs primeiro mês do período
    $comissao_primeiro_mes = 0;
    $comissao_ultimo_mes = 0;
    
    if ($meses_periodo >= 2) {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(
                (sf.valor_pago * ? / 100) + (sf.valor_pago * c.porcentagem_recorrencia / 100)
            ), 0) as comissao
            FROM seguro_financeiro sf
            INNER JOIN seguro_clientes c ON (COALESCE(NULLIF(sf.seguro_cliente_id, 0), sf.cliente_id) = c.id)
            WHERE sf.seguro_empresa_id = ?
              AND sf.status = 'pago'
              AND sf.valor_pago > 0
              AND sf.data_baixa IS NOT NULL
              AND DATE_FORMAT(sf.data_baixa, '%Y-%m') = ?
              AND (sf.cliente_nao_encontrado IS NULL OR sf.cliente_nao_encontrado = 'nao')
        ");
        $stmt->execute([$porcentagem_fixa, $empresa_id, $dataInicio]);
        $comissao_primeiro_mes = floatval($stmt->fetchColumn());
        
        $stmt->execute([$porcentagem_fixa, $empresa_id, $dataFim]);
        $comissao_ultimo_mes = floatval($stmt->fetchColumn());
    }
    
    // Calcular crescimento (primeiro vs último mês)
    $crescimento_mensal = 0;
    if ($comissao_primeiro_mes > 0) {
        $crescimento_mensal = (($comissao_ultimo_mes - $comissao_primeiro_mes) / $comissao_primeiro_mes) * 100;
    } elseif ($comissao_ultimo_mes > 0) {
        $crescimento_mensal = 100;
    }
    
    // Total de clientes ativos
    $stmt = $db->prepare("SELECT COUNT(*) FROM seguro_clientes WHERE seguro_empresa_id = ? AND situacao = 'ativo'");
    $stmt->execute([$empresa_id]);
    $total_clientes = intval($stmt->fetchColumn());
    
    // Média por cliente (baseada na média mensal)
    $media_cliente = $total_clientes > 0 ? $media_mensal / $total_clientes : 0;
    
    // Projeção anual (baseada na média mensal)
    $projecao_anual = $media_mensal * 12;
    
    // Margem de lucro (simplificado - porcentagem da empresa)
    $margem_lucro = $porcentagem_fixa;
    
    echo json_encode([
        'sucesso' => true,
        'crescimento_mensal' => round($crescimento_mensal, 1),
        'media_cliente' => round($media_cliente, 2),
        'projecao_anual' => round($projecao_anual, 2),
        'margem_lucro' => round($margem_lucro, 1)
    ]);
    
} catch (Exception $e) {
    error_log("Erro: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao buscar dados',
        'crescimento_mensal' => 0,
        'media_cliente' => 0,
        'projecao_anual' => 0,
        'margem_lucro' => 0
    ]);
}
?>

