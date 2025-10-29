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
    
    // Buscar documentos financeiros (sempre usa unidade da empresa)
    $stmt = $db->prepare("
        SELECT 
            identificador_original as identificador,
            numero_documento as numero_documento,
            associado as associado,
            classe as classe,
            DATE_FORMAT(data_emissao, '%d/%m/%Y') as data_emissao,
            DATE_FORMAT(data_vencimento, '%d/%m/%Y') as data_vencimento,
            COALESCE(valor, 0) as valor,
            COALESCE(placa, '') as placa,
            COALESCE(conjunto, '') as conjunto,
            COALESCE(matricula, '') as matricula,
            COALESCE(status, 'pendente') as status,
            COALESCE(valor_pago, 0) as valor_pago,
            DATE_FORMAT(data_baixa, '%d/%m/%Y') as data_baixa,
            ? as unidade
        FROM seguro_financeiro 
        WHERE cliente_id = ? 
        AND seguro_empresa_id = ?
        ORDER BY data_vencimento DESC, data_emissao DESC
    ");
    $stmt->execute([$unidadeEmpresa, $cliente_id, $empresa_id]);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'sucesso' => true,
        'documentos' => $documentos,
        'total' => count($documentos)
    ]);
    
} catch(PDOException $e) {
    error_log("Erro ao obter documentos financeiros: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao buscar documentos financeiros'
    ]);
}
?>

