<?php
/**
 * API - Obter Cliente
 * Retorna dados completos de um cliente específico
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
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'ID do cliente não informado'
    ]);
    exit;
}

try {
    $db = getDB();
    $empresa_id = obterEmpresaId();
    $cliente_id = intval($_GET['id']);
    
    // Buscar cliente com percentual e unidade da empresa
    $stmt = $db->prepare("
        SELECT 
            c.*,
            e.porcentagem_fixa as percentual_empresa,
            e.unidade as unidade_empresa
        FROM seguro_clientes c
        LEFT JOIN seguro_empresa_clientes e ON c.seguro_empresa_id = e.id
        WHERE c.id = ? AND c.seguro_empresa_id = ?
    ");
    $stmt->execute([$cliente_id, $empresa_id]);
    $cliente = $stmt->fetch();
    
    if ($cliente) {
        echo json_encode([
            'sucesso' => true,
            'cliente' => $cliente
        ]);
    } else {
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Cliente não encontrado'
        ]);
    }
    
} catch(PDOException $e) {
    error_log("Erro ao obter cliente: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao buscar cliente'
    ]);
}
?>

