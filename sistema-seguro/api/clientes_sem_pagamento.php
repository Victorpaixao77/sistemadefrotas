<?php
/**
 * API - Clientes Sem Pagamento no Período
 * Retorna clientes que não tiveram documentos pagos no período especificado
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/auth.php';

// Verificar autenticação
if (!isset($_SESSION['seguro_logado']) || $_SESSION['seguro_logado'] !== true) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Usuário não autenticado'
    ]);
    exit;
}

try {
    $db = getDB();
    $empresa_id = obterEmpresaId();
    
    // Parâmetros
    $data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
    $data_fim = $_GET['data_fim'] ?? date('Y-m-d');
    
    // Buscar todos os clientes da empresa
    $stmtClientes = $db->prepare("
        SELECT 
            c.id,
            c.codigo,
            c.identificador,
            c.cpf_cnpj,
            c.nome_razao_social,
            c.matricula,
            c.cidade,
            c.uf,
            c.telefone,
            c.celular,
            c.porcentagem_recorrencia,
            c.situacao,
            e.unidade
        FROM seguro_clientes c
        INNER JOIN seguro_empresa_clientes e ON c.seguro_empresa_id = e.id
        WHERE c.seguro_empresa_id = ?
        ORDER BY c.nome_razao_social ASC
    ");
    $stmtClientes->execute([$empresa_id]);
    $todosClientes = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar clientes que TIVERAM pagamento no período
    // Usa COALESCE para pegar o primeiro valor não-nulo entre seguro_cliente_id e cliente_id
    // NULLIF trata 0 como NULL
    // Usa data_baixa (data real do pagamento) em vez de data_pagamento
    $stmtComPagamento = $db->prepare("
        SELECT DISTINCT 
            COALESCE(NULLIF(sf.seguro_cliente_id, 0), sf.cliente_id) as cliente_id
        FROM seguro_financeiro sf
        WHERE sf.seguro_empresa_id = ?
          AND COALESCE(NULLIF(sf.seguro_cliente_id, 0), sf.cliente_id) IS NOT NULL
          AND sf.data_baixa IS NOT NULL
          AND sf.data_baixa BETWEEN ? AND ?
          AND sf.status = 'pago'
          AND sf.valor > 0
    ");
    $stmtComPagamento->execute([$empresa_id, $data_inicio, $data_fim]);
    $clientesComPagamento = $stmtComPagamento->fetchAll(PDO::FETCH_COLUMN);
    
    // Filtrar clientes SEM pagamento (excluir os que tiveram pagamento)
    $clientesSemPagamento = array_filter($todosClientes, function($cliente) use ($clientesComPagamento) {
        return !in_array($cliente['id'], $clientesComPagamento);
    });
    
    // Reindexar array
    $clientesSemPagamento = array_values($clientesSemPagamento);
    
    // Calcular resumo
    $totalClientes = count($clientesSemPagamento);
    $clientesAtivos = 0;
    $clientesInativos = 0;
    
    foreach ($clientesSemPagamento as $cliente) {
        if ($cliente['situacao'] === 'ativo') {
            $clientesAtivos++;
        } else {
            $clientesInativos++;
        }
    }
    
    echo json_encode([
        'sucesso' => true,
        'clientes' => $clientesSemPagamento,
        'resumo' => [
            'total_clientes' => $totalClientes,
            'clientes_ativos' => $clientesAtivos,
            'clientes_inativos' => $clientesInativos
        ],
        'periodo' => [
            'data_inicio' => $data_inicio,
            'data_fim' => $data_fim
        ],
        'debug' => [
            'query_usado' => 'COALESCE(NULLIF(seguro_cliente_id, 0), cliente_id)',
            'total_clientes_cadastrados' => count($todosClientes),
            'clientes_com_pagamento' => count($clientesComPagamento),
            'ids_clientes_com_pagamento' => $clientesComPagamento,
            'clientes_sem_pagamento' => $totalClientes
        ]
    ]);
    
} catch(PDOException $e) {
    error_log("Erro ao buscar clientes sem pagamento: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao buscar dados: ' . $e->getMessage()
    ]);
}
?>

