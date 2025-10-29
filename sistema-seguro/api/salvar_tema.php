<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

// Verificar se está logado
verificarLogin();

$usuario_id = obterUsuarioId();

try {
    $db = getDB();
    
    // Obter dados do POST
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['tema'])) {
        throw new Exception('Tema não informado');
    }
    
    $tema = $data['tema'];
    
    // Validar tema
    $temas_validos = ['claro', 'escuro', 'auto'];
    if (!in_array($tema, $temas_validos)) {
        throw new Exception('Tema inválido');
    }
    
    // Atualizar tema do usuário
    $stmt = $db->prepare("UPDATE seguro_usuarios SET tema = ? WHERE id = ?");
    $stmt->execute([$tema, $usuario_id]);
    
    // Registrar log
    registrarLog(obterEmpresaId(), $usuario_id, 'atualizar', 'configuracoes', "Tema alterado para: $tema");
    
    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Tema atualizado com sucesso!',
        'tema' => $tema
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao salvar tema: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => $e->getMessage()
    ]);
}
?>

