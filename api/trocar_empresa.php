<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

configure_session();
session_start();

header('Content-Type: application/json');

// Verificar se está logado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

// Verificar se tem acesso a todas as empresas
if (empty($_SESSION['acesso_todas_empresas']) || $_SESSION['acesso_todas_empresas'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Você não tem permissão para trocar de empresa']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'listar') {
    // Listar todas as empresas ativas
    try {
        $conn = getConnection();
        $stmt = $conn->query("SELECT id, razao_social, nome_fantasia FROM empresa_clientes WHERE status = 'ativo' ORDER BY razao_social");
        $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $empresa_atual_id = $_SESSION['empresa_id'] ?? null;
        
        echo json_encode([
            'success' => true,
            'empresas' => $empresas,
            'empresa_atual' => $empresa_atual_id
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} elseif ($action === 'trocar' && isset($_POST['empresa_id'])) {
    // Trocar de empresa
    $empresa_id = (int)$_POST['empresa_id'];
    
    try {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT id, razao_social FROM empresa_clientes WHERE id = ? AND status = 'ativo'");
        $stmt->execute([$empresa_id]);
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($empresa) {
            // Atualizar sessão
            $_SESSION['empresa_id'] = $empresa_id;
            $_SESSION['empresa_nome'] = $empresa['razao_social'];
            
            // Registrar log
            registrarLogAcesso($_SESSION['usuario_id'], $empresa_id, 'trocar_empresa', 'sucesso', 'Empresa alterada para: ' . $empresa['razao_social']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Empresa alterada com sucesso',
                'empresa' => $empresa
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Empresa não encontrada ou inativa']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Ação inválida']);
}
?>


