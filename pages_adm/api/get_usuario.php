<?php
require_once '../../includes/conexao.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$usuario_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch();
    
    if ($usuario) {
        // Não retornar a senha
        unset($usuario['senha']);
        echo json_encode([
            'success' => true,
            'usuario' => $usuario
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
