<?php
require_once '../../includes/conexao.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$empresa_adm_id = isset($_GET['empresa_adm_id']) ? (int)$_GET['empresa_adm_id'] : 0;

try {
    // Buscar empresa_cliente_id
    $stmt = $pdo->prepare("SELECT id FROM empresa_clientes WHERE empresa_adm_id = ? LIMIT 1");
    $stmt->execute([$empresa_adm_id]);
    $empresa_cliente = $stmt->fetch();
    
    if (!$empresa_cliente) {
        echo json_encode(['success' => false, 'error' => 'Empresa não encontrada']);
        exit;
    }
    
    $empresa_cliente_id = $empresa_cliente['id'];
    
    // Buscar usuários da empresa (excluindo usuários ocultos)
    $stmt = $pdo->prepare("
        SELECT u.*
        FROM usuarios u
        WHERE u.empresa_id = ? 
        AND (u.is_oculto IS NULL OR u.is_oculto = 0)
        ORDER BY u.nome
    ");
    $stmt->execute([$empresa_cliente_id]);
    $usuarios = $stmt->fetchAll();
    
    // Formatar dados
    foreach ($usuarios as &$usuario) {
        if (isset($usuario['data_cadastro']) && $usuario['data_cadastro']) {
            try {
                $usuario['data_cadastro_formatada'] = date('d/m/Y', strtotime($usuario['data_cadastro']));
            } catch (Exception $e) {
                $usuario['data_cadastro_formatada'] = '-';
            }
        } else {
            $usuario['data_cadastro_formatada'] = '-';
        }
        // Remover senha
        if (isset($usuario['senha'])) {
            unset($usuario['senha']);
        }
    }
    
    echo json_encode([
        'success' => true,
        'usuarios' => $usuarios
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
