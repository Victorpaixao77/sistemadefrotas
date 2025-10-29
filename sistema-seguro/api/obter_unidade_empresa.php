<?php
/**
 * API - Obter Unidade da Empresa
 * Retorna a unidade cadastrada na empresa para usar no cadastro de clientes
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

try {
    $db = getDB();
    $empresa_id = obterEmpresaId();
    
    // Buscar unidade da empresa
    $stmt = $db->prepare("
        SELECT unidade, razao_social, porcentagem_fixa
        FROM seguro_empresa_clientes 
        WHERE id = ?
    ");
    $stmt->execute([$empresa_id]);
    $empresa = $stmt->fetch();
    
    if ($empresa) {
        echo json_encode([
            'sucesso' => true,
            'unidade' => $empresa['unidade'] ?? 'Matriz',
            'empresa' => $empresa['razao_social'],
            'porcentagem_fixa' => $empresa['porcentagem_fixa']
        ]);
    } else {
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Empresa não encontrada'
        ]);
    }
    
} catch(PDOException $e) {
    error_log("Erro ao obter unidade: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao buscar dados da empresa'
    ]);
}
?>

