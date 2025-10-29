<?php
/**
 * API - Listar Documentos Financeiros
 * Retorna documentos com paginação
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/auth.php';

verificarLogin();

try {
    $db = getDB();
    $empresa_id = obterEmpresaId();
    
    $pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
    $por_pagina = isset($_GET['por_pagina']) ? intval($_GET['por_pagina']) : 10;
    $offset = ($pagina - 1) * $por_pagina;
    
    // Contar total
    $stmtCount = $db->prepare("
        SELECT COUNT(*) as total
        FROM seguro_financeiro sf
        WHERE sf.seguro_empresa_id = ?
    ");
    $stmtCount->execute([$empresa_id]);
    $total = $stmtCount->fetchColumn();
    
    // Buscar documentos paginados
    $stmt = $db->prepare("
        SELECT 
            sf.id,
            sf.numero_documento,
            sf.associado,
            sf.classe,
            DATE_FORMAT(sf.data_vencimento, '%d/%m/%Y') as data_vencimento,
            sf.valor,
            sf.placa,
            sf.status,
            sf.cliente_nao_encontrado,
            COALESCE(sc.razao_social, sc.nome_razao_social, 'Cliente não identificado') as cliente
        FROM seguro_financeiro sf
        LEFT JOIN seguro_clientes sc ON sf.cliente_id = sc.id
        WHERE sf.seguro_empresa_id = ?
        ORDER BY sf.data_cadastro DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$empresa_id, $por_pagina, $offset]);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_paginas = ceil($total / $por_pagina);
    
    echo json_encode([
        'sucesso' => true,
        'documentos' => $documentos,
        'paginacao' => [
            'pagina_atual' => $pagina,
            'por_pagina' => $por_pagina,
            'total' => $total,
            'total_paginas' => $total_paginas,
            'inicio' => $offset + 1,
            'fim' => min($offset + $por_pagina, $total)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao listar documentos: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao buscar documentos',
        'documentos' => []
    ]);
}
?>

