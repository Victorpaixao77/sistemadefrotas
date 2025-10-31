<?php
/**
 * API - Vincular Documento da Quarentena ao Cliente
 * Procura cliente pela MATRÍCULA e CONJUNTO e vincula o documento
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/auth.php';

// Verificar se está logado
verificarLogin();

try {
    $db = getDB();
    $empresa_id = obterEmpresaId();
    
    // Receber ID do documento
    $documento_id = $_POST['documento_id'] ?? null;
    
    if (!$documento_id) {
        throw new Exception('ID do documento não informado');
    }
    
    // Buscar documento em quarentena
    $stmtDoc = $db->prepare("
        SELECT 
            id, 
            matricula, 
            conjunto,
            numero_documento,
            associado
        FROM seguro_financeiro 
        WHERE id = ? 
        AND seguro_empresa_id = ?
        AND cliente_nao_encontrado = 'sim'
    ");
    $stmtDoc->execute([$documento_id, $empresa_id]);
    $documento = $stmtDoc->fetch(PDO::FETCH_ASSOC);
    
    if (!$documento) {
        throw new Exception('Documento não encontrado na quarentena');
    }
    
    $matricula = $documento['matricula'];
    $conjunto = $documento['conjunto'];
    
    if (empty($matricula) || empty($conjunto)) {
        throw new Exception('Documento sem MATRÍCULA ou CONJUNTO. Não é possível vincular automaticamente.');
    }
    
    // Buscar cliente pela MATRÍCULA
    $stmtCliente = $db->prepare("
        SELECT id, nome_razao_social
        FROM seguro_clientes 
        WHERE matricula = ? 
        AND seguro_empresa_id = ?
        LIMIT 1
    ");
    $stmtCliente->execute([$matricula, $empresa_id]);
    $cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        throw new Exception("Cliente com MATRÍCULA {$matricula} não encontrado. Cadastre o cliente primeiro.");
    }
    
    $cliente_id = $cliente['id'];
    $cliente_nome = $cliente['nome_razao_social'];
    
    // Verificar se existe contrato com o CONJUNTO
    $stmtContrato = $db->prepare("
        SELECT id, porcentagem_recorrencia
        FROM seguro_contratos_clientes 
        WHERE cliente_id = ? 
        AND matricula COLLATE utf8mb4_general_ci = ?
        AND ativo = 'sim'
        LIMIT 1
    ");
    $stmtContrato->execute([$cliente_id, $conjunto]);
    $contrato = $stmtContrato->fetch(PDO::FETCH_ASSOC);
    
    $avisoContrato = '';
    if (!$contrato) {
        $avisoContrato = " (ATENÇÃO: Contrato com CONJUNTO {$conjunto} não encontrado. A comissão será calculada apenas com % da empresa)";
    }
    
    // Atualizar documento: vincular ao cliente
    $stmtUpdate = $db->prepare("
        UPDATE seguro_financeiro 
        SET 
            cliente_id = ?,
            cliente_nao_encontrado = 'nao'
        WHERE id = ?
    ");
    $stmtUpdate->execute([$cliente_id, $documento_id]);
    
    // Registrar log
    registrarLog(
        obterUsuarioId(),
        'vincular_documento',
        'seguro_financeiro',
        $documento_id,
        "Documento vinculado ao cliente {$cliente_nome} (ID: {$cliente_id})"
    );
    
    echo json_encode([
        'sucesso' => true,
        'mensagem' => "Documento vinculado com sucesso ao cliente: {$cliente_nome}{$avisoContrato}",
        'cliente_id' => $cliente_id,
        'cliente_nome' => $cliente_nome,
        'tem_contrato' => $contrato ? true : false
    ], JSON_UNESCAPED_UNICODE);
    
} catch(Exception $e) {
    error_log("Erro ao vincular documento: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

