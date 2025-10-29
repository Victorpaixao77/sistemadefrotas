<?php
require_once '../config/database.php';
require_once '../config/auth.php';

verificarLogin();
$empresa_id = obterEmpresaId();

try {
    $db = getDB();
    
    // Buscar dados da empresa
    $stmt = $db->prepare("SELECT dia_fechamento, porcentagem_fixa FROM seguro_empresa_clientes WHERE id = ?");
    $stmt->execute([$empresa_id]);
    $empresa = $stmt->fetch();
    
    $porcentagem_fixa = floatval($empresa['porcentagem_fixa']);
    
    // Obter período dos parâmetros ou usar padrão (últimos 12 meses)
    $dataInicio = $_GET['dataInicio'] ?? date('Y-m', strtotime("-11 months"));
    $dataFim = $_GET['dataFim'] ?? date('Y-m');
    
    // Gerar lista de meses no período
    $meses = [];
    $inicio = new DateTime($dataInicio . '-01');
    $fim = new DateTime($dataFim . '-01');
    
    while ($inicio <= $fim) {
        $meses[] = $inicio->format('Y-m');
        $inicio->modify('+1 month');
    }
    
    $resultado = [];
    
    foreach ($meses as $mes) {
        $stmt = $db->prepare("
            SELECT 
                COALESCE(SUM(
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
        $stmt->execute([$porcentagem_fixa, $empresa_id, $mes]);
        
        $comissao = $stmt->fetchColumn();
        
        $resultado[] = [
            'mes' => $mes,
            'comissao' => round(floatval($comissao), 2)
        ];
    }
    
    echo json_encode([
        'sucesso' => true,
        'dados' => $resultado
    ]);
    
} catch (Exception $e) {
    error_log("Erro: " . $e->getMessage());
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao buscar dados']);
}
?>

