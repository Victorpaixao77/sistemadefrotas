<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

// Verificar se está logado
verificarLogin();

$usuario_id = obterUsuarioId();
$empresa_id = obterEmpresaId();

try {
    $db = getDB();
    
    // Buscar tema do usuário
    $stmt = $db->prepare("SELECT tema FROM seguro_usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch();
    
    $tema_usuario = $usuario['tema'] ?? 'claro';
    
    // Buscar cores da empresa
    $stmt = $db->prepare("
        SELECT 
            tema,
            cor_primaria,
            cor_secundaria,
            cor_destaque
        FROM seguro_empresa_clientes 
        WHERE id = ?
    ");
    $stmt->execute([$empresa_id]);
    $empresa = $stmt->fetch();
    
    // Definir tema final (prioridade para preferência do usuário)
    $tema_final = $tema_usuario;
    
    // Se o tema for 'auto', usar o do sistema (navegador)
    if ($tema_usuario === 'auto') {
        $tema_final = 'auto'; // O JavaScript irá detectar
    }
    
    echo json_encode([
        'sucesso' => true,
        'tema' => $tema_final,
        'tema_usuario' => $tema_usuario,
        'tema_empresa' => $empresa['tema'] ?? 'claro',
        'cores' => [
            'primaria' => $empresa['cor_primaria'] ?? '#667eea',
            'secundaria' => $empresa['cor_secundaria'] ?? '#764ba2',
            'destaque' => $empresa['cor_destaque'] ?? '#28a745'
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao obter tema: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => $e->getMessage(),
        'tema' => 'claro',
        'cores' => [
            'primaria' => '#667eea',
            'secundaria' => '#764ba2',
            'destaque' => '#28a745'
        ]
    ]);
}
?>

