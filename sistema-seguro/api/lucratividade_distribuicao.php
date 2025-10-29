<?php
require_once '../config/database.php';
require_once '../config/auth.php';

verificarLogin();
$empresa_id = obterEmpresaId();

try {
    $db = getDB();
    
    // Clientes ativos
    $stmt = $db->prepare("SELECT COUNT(*) FROM seguro_clientes WHERE seguro_empresa_id = ? AND situacao = 'ativo'");
    $stmt->execute([$empresa_id]);
    $ativos = $stmt->fetchColumn();
    
    // Clientes inativos
    $stmt = $db->prepare("SELECT COUNT(*) FROM seguro_clientes WHERE seguro_empresa_id = ? AND situacao = 'inativo'");
    $stmt->execute([$empresa_id]);
    $inativos = $stmt->fetchColumn();
    
    echo json_encode([
        'sucesso' => true,
        'dados' => [
            'ativos' => intval($ativos),
            'inativos' => intval($inativos)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Erro: " . $e->getMessage());
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao buscar dados']);
}
?>

