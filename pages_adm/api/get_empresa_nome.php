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
    $stmt = $pdo->prepare("SELECT razao_social FROM empresa_adm WHERE id = ? LIMIT 1");
    $stmt->execute([$empresa_adm_id]);
    $result = $stmt->fetch();
    
    if ($result) {
        echo json_encode(['success' => true, 'razao_social' => $result['razao_social']]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Empresa não encontrada']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
