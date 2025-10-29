<?php
require_once '../config/database.php';
require_once '../config/auth.php';

// Verificar se está logado
verificarLogin();

// Obter empresa_id
$empresa_id = obterEmpresaId();

try {
    $db = getDB();
    
    // Obter período dos parâmetros ou usar padrão (últimos 6 meses)
    $dataInicio = $_GET['dataInicio'] ?? date('Y-m', strtotime("-5 months"));
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
        list($ano, $mes_num) = explode('-', $mes);
        
        // Calcular data inicial e final do mês
        $data_inicio = "$ano-$mes_num-01";
        $data_fim = date('Y-m-t', strtotime($data_inicio));
        
        // Buscar dia de fechamento da empresa
        $stmt = $db->prepare("SELECT dia_fechamento, porcentagem_fixa FROM seguro_empresa_clientes WHERE id = ?");
        $stmt->execute([$empresa_id]);
        $empresa = $stmt->fetch();
        
        $dia_fechamento = $empresa['dia_fechamento'] ?? 25;
        $porcentagem_fixa = floatval($empresa['porcentagem_fixa']);
        
        // Se o mês atual está dentro do mês de referência com base no dia_fechamento
        $ano_ref = date('Y');
        $mes_ref = date('m');
        $dia_atual = date('d');
        
        // Se estamos antes do dia de fechamento, a comissão é do mês anterior
        if ($dia_atual < $dia_fechamento) {
            $data_ref = date('Y-m-d', strtotime('-1 month'));
            $ano_ref = date('Y', strtotime('-1 month'));
            $mes_ref = date('m', strtotime('-1 month'));
        }
        
        // Buscar todos os documentos pagos no mês que estejam relacionados a clientes
        $sql = "
            SELECT 
                sf.valor_pago,
                c.porcentagem_recorrencia
            FROM seguro_financeiro sf
            INNER JOIN seguro_clientes c ON (COALESCE(NULLIF(sf.seguro_cliente_id, 0), sf.cliente_id) = c.id)
            WHERE sf.seguro_empresa_id = ?
              AND sf.status = 'pago'
              AND sf.valor_pago > 0
              AND sf.data_baixa IS NOT NULL
              AND (sf.cliente_nao_encontrado IS NULL OR sf.cliente_nao_encontrado = 'nao')
              AND DATE_FORMAT(sf.data_baixa, '%Y-%m') = ?
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$empresa_id, $mes]);
        
        $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total_comissao = 0;
        $qtd_docs = count($documentos);
        
        foreach ($documentos as $doc) {
            $valor_pago = floatval($doc['valor_pago']);
            $porcentagem_cliente = floatval($doc['porcentagem_recorrencia']);
            
            // Calcular comissão: (valor_pago * %_empresa) + (valor_pago * %_cliente)
            $comissao = ($valor_pago * $porcentagem_fixa / 100) + ($valor_pago * $porcentagem_cliente / 100);
            $total_comissao += $comissao;
        }
        
        $resultado[] = [
            'mes' => $mes,
            'total_comissao' => round($total_comissao, 2),
            'qtd_documentos' => $qtd_docs,
            'porcentagem_empresa' => $porcentagem_fixa
        ];
    }
    
    echo json_encode([
        'sucesso' => true,
        'dados' => $resultado
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar comissões: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao buscar dados de comissões'
    ]);
} catch (Exception $e) {
    error_log("Erro geral: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao processar requisição'
    ]);
}
?>
