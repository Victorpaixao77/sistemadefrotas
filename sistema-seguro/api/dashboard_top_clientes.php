<?php
require_once '../config/database.php';
require_once '../config/auth.php';

// Verificar se está logado
verificarLogin();

// Obter empresa_id
$empresa_id = obterEmpresaId();

try {
    $db = getDB();
    
    // Buscar top 5 clientes que mais pagaram
    $sql = "
        SELECT 
            c.nome_razao_social as nome,
            c.cpf_cnpj,
            COALESCE(SUM(sf.valor_pago), 0) as total_pago
        FROM seguro_clientes c
        LEFT JOIN seguro_financeiro sf ON (
            (COALESCE(NULLIF(sf.seguro_cliente_id, 0), sf.cliente_id) = c.id)
            AND sf.status = 'pago'
            AND sf.valor_pago > 0
            AND sf.data_baixa IS NOT NULL
            AND sf.cliente_nao_encontrado IS NULL
        )
        WHERE c.seguro_empresa_id = ?
          AND c.situacao = 'ativo'
        GROUP BY c.id, c.nome_razao_social, c.cpf_cnpj
        HAVING total_pago > 0
        ORDER BY total_pago DESC
        LIMIT 5
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$empresa_id]);
    
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'sucesso' => true,
        'clientes' => $clientes
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar top clientes: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao buscar dados',
        'clientes' => []
    ]);
} catch (Exception $e) {
    error_log("Erro geral: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao processar requisição',
        'clientes' => []
    ]);
}
?>
