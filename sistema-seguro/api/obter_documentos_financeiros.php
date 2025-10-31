<?php
/**
 * API - Obter Documentos Financeiros do Cliente
 * Retorna todos os documentos financeiros relacionados ao cliente
 */

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/auth.php';

// Verificar se está logado
if (!isset($_SESSION['seguro_logado']) || $_SESSION['seguro_logado'] !== true) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Usuário não autenticado'
    ]);
    exit;
}

// Verificar se ID foi informado
if (!isset($_GET['cliente_id']) || empty($_GET['cliente_id'])) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'ID do cliente não informado'
    ]);
    exit;
}

try {
    $db = getDB();
    $empresa_id = obterEmpresaId();
    $cliente_id = intval($_GET['cliente_id']);
    
    // Verificar se o cliente pertence à empresa
    $stmt = $db->prepare("
        SELECT id FROM seguro_clientes 
        WHERE id = ? AND seguro_empresa_id = ?
    ");
    $stmt->execute([$cliente_id, $empresa_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Cliente não encontrado'
        ]);
        exit;
    }
    
    // Buscar unidade da empresa
    $stmtUnidade = $db->prepare("SELECT unidade FROM seguro_empresa_clientes WHERE id = ?");
    $stmtUnidade->execute([$empresa_id]);
    $empresaData = $stmtUnidade->fetch();
    $unidadeEmpresa = $empresaData['unidade'] ?? '-';
    
    // Verificar se colunas 'ponteiro' e 'proposals' existem
    $stmt = $db->query("SHOW COLUMNS FROM seguro_financeiro LIKE 'ponteiro'");
    $temPonteiro = $stmt->fetch() ? true : false;
    
    $stmt = $db->query("SHOW COLUMNS FROM seguro_financeiro LIKE 'proposals'");
    $temProposals = $stmt->fetch() ? true : false;
    
    // Montar SQL dinamicamente conforme campos existentes
    $sqlPonteiro = $temPonteiro ? "sf.ponteiro," : "'' as ponteiro,";
    $sqlProposals = $temProposals ? "COALESCE(sf.proposals, '') as proposals," : "'' as proposals,";
    
    // Buscar documentos financeiros com verificação de contrato
    $sql = "
        SELECT 
            $sqlPonteiro
            sf.numero_documento,
            sf.associado,
            sf.classe,
            DATE_FORMAT(sf.data_emissao, '%d/%m/%Y') as data_emissao,
            DATE_FORMAT(sf.data_vencimento, '%d/%m/%Y') as data_vencimento,
            COALESCE(sf.valor, 0) as valor,
            COALESCE(sf.placa, '') as placa,
            COALESCE(sf.conjunto, '') as conjunto,
            COALESCE(sf.matricula, '') as matricula,
            COALESCE(sf.status, 'pendente') as status,
            COALESCE(sf.valor_pago, 0) as valor_pago,
            DATE_FORMAT(sf.data_baixa, '%d/%m/%Y') as data_baixa,
            COALESCE(sf.unidade, ?) as unidade,
            $sqlProposals
            -- Verificar se o CONJUNTO existe nos contratos do cliente
            (SELECT cc.id 
             FROM seguro_contratos_clientes cc 
             WHERE cc.cliente_id = COALESCE(sf.cliente_id, sf.seguro_cliente_id)
             AND cc.matricula COLLATE utf8mb4_general_ci = sf.conjunto COLLATE utf8mb4_general_ci
             AND cc.ativo = 'sim'
             LIMIT 1) as contrato_id,
            -- Buscar porcentagem do contrato (se existir)
            (SELECT cc.porcentagem_recorrencia 
             FROM seguro_contratos_clientes cc 
             WHERE cc.cliente_id = COALESCE(sf.cliente_id, sf.seguro_cliente_id)
             AND cc.matricula COLLATE utf8mb4_general_ci = sf.conjunto COLLATE utf8mb4_general_ci
             AND cc.ativo = 'sim'
             LIMIT 1) as porcentagem_contrato
        FROM seguro_financeiro sf
        WHERE (sf.cliente_id = ? OR sf.seguro_cliente_id = ?)
        AND sf.seguro_empresa_id = ?
        ORDER BY sf.data_vencimento DESC, sf.data_emissao DESC
    ";
    
    error_log("SQL gerado: " . $sql);
    error_log("Parâmetros: unidade={$unidadeEmpresa}, cliente_id={$cliente_id}, empresa_id={$empresa_id}");
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$unidadeEmpresa, $cliente_id, $cliente_id, $empresa_id]);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Documentos encontrados: " . count($documentos));
    
    echo json_encode([
        'sucesso' => true,
        'documentos' => $documentos,
        'total' => count($documentos)
    ]);
    
} catch(PDOException $e) {
    error_log("ERRO PDO ao obter documentos financeiros: " . $e->getMessage());
    error_log("SQL: " . $sql);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao buscar documentos financeiros',
        'erro_detalhado' => $e->getMessage()
    ]);
} catch(Exception $e) {
    error_log("ERRO GERAL ao obter documentos financeiros: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao buscar documentos financeiros',
        'erro_detalhado' => $e->getMessage()
    ]);
}
?>

