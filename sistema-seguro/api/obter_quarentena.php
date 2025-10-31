<?php
/**
 * API - Obter Documentos em Quarentena Financeira
 * Retorna todos os documentos sem cliente (cliente_id = NULL)
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/auth.php';

// Verificar se está logado
verificarLogin();

try {
    $db = getDB();
    $empresa_id = obterEmpresaId();
    
    // Buscar unidade da empresa
    $stmtUnidade = $db->prepare("SELECT unidade FROM seguro_empresa_clientes WHERE id = ?");
    $stmtUnidade->execute([$empresa_id]);
    $empresaData = $stmtUnidade->fetch();
    $unidade = $empresaData['unidade'] ?? '-';
    
    // Verificar se colunas 'ponteiro' e 'proposals' existem
    $stmt = $db->query("SHOW COLUMNS FROM seguro_financeiro LIKE 'ponteiro'");
    $temPonteiro = $stmt->fetch() ? true : false;
    
    $stmt = $db->query("SHOW COLUMNS FROM seguro_financeiro LIKE 'proposals'");
    $temProposals = $stmt->fetch() ? true : false;
    
    // Montar SQL dinamicamente
    $sqlPonteiro = $temPonteiro ? "ponteiro," : "'' as ponteiro,";
    $sqlProposals = $temProposals ? "COALESCE(proposals, '') as proposals," : "'' as proposals,";
    
    // Buscar documentos em quarentena (cliente_id = NULL)
    $sql = "
        SELECT 
            id,
            $sqlPonteiro
            numero_documento,
            associado,
            classe,
            DATE_FORMAT(data_emissao, '%d/%m/%Y') as data_emissao,
            DATE_FORMAT(data_vencimento, '%d/%m/%Y') as data_vencimento,
            COALESCE(valor, 0) as valor,
            COALESCE(placa, '') as placa,
            COALESCE(conjunto, '') as conjunto,
            COALESCE(matricula, '') as matricula,
            COALESCE(status, 'pendente') as status,
            COALESCE(valor_pago, 0) as valor_pago,
            DATE_FORMAT(data_baixa, '%d/%m/%Y') as data_baixa,
            COALESCE(unidade, ?) as unidade,
            $sqlProposals
            DATE_FORMAT(data_cadastro, '%d/%m/%Y %H:%i') as data_importacao
        FROM seguro_financeiro 
        WHERE cliente_nao_encontrado = 'sim'
        AND seguro_empresa_id = ?
        ORDER BY data_cadastro DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$unidade, $empresa_id]);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular totais
    $totalValor = array_sum(array_column($documentos, 'valor'));
    $totalPago = array_sum(array_column($documentos, 'valor_pago'));
    
    echo json_encode([
        'success' => true,
        'documentos' => $documentos,
        'total' => count($documentos),
        'resumo' => [
            'total_documentos' => count($documentos),
            'valor_total' => $totalValor,
            'valor_pago' => $totalPago,
            'valor_pendente' => $totalValor - $totalPago
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch(PDOException $e) {
    error_log("Erro ao obter quarentena: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar documentos em quarentena'
    ], JSON_UNESCAPED_UNICODE);
}
?>

