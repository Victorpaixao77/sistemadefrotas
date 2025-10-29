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
    
    // Obter período dos parâmetros
    $dataInicio = $_GET['dataInicio'] ?? null;
    $dataFim = $_GET['dataFim'] ?? null;
    
    // Top 10 clientes por comissão gerada
    $sql = "
        SELECT 
            c.nome_razao_social as nome,
            COALESCE(SUM(
                (sf.valor_pago * ? / 100) + (sf.valor_pago * c.porcentagem_recorrencia / 100)
            ), 0) as comissao
        FROM seguro_clientes c
        LEFT JOIN seguro_financeiro sf ON (
            COALESCE(NULLIF(sf.seguro_cliente_id, 0), sf.cliente_id) = c.id
            AND sf.status = 'pago'
            AND sf.valor_pago > 0
            AND sf.data_baixa IS NOT NULL
            AND (sf.cliente_nao_encontrado IS NULL OR sf.cliente_nao_encontrado = 'nao')
    ";
    
    // Adicionar filtro de data se fornecido
    if ($dataInicio && $dataFim) {
        $sql .= "
            AND DATE_FORMAT(sf.data_baixa, '%Y-%m') >= ?
            AND DATE_FORMAT(sf.data_baixa, '%Y-%m') <= ?
        ";
    }
    
    $sql .= "
        )
        WHERE c.seguro_empresa_id = ?
          AND c.situacao = 'ativo'
        GROUP BY c.id, c.nome_razao_social
        HAVING comissao > 0
        ORDER BY comissao DESC
        LIMIT 10
    ";
    
    $params = [$porcentagem_fixa];
    if ($dataInicio && $dataFim) {
        $params[] = $dataInicio;
        $params[] = $dataFim;
    }
    $params[] = $empresa_id;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'sucesso' => true,
        'clientes' => $clientes
    ]);
    
} catch (Exception $e) {
    error_log("Erro: " . $e->getMessage());
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao buscar dados', 'clientes' => []]);
}
?>

