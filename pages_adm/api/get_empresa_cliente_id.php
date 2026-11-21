<?php
require_once '../../includes/conexao.php';
require_once __DIR__ . '/../includes/adm_session.php';
require_once __DIR__ . '/../includes/adm_post_guard.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

// Endpoint faz get-or-create — exige POST + CSRF para não ser acionável por link/CSRF.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !adm_csrf_valid_post()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Requisição inválida. Recarregue a página.']);
    exit;
}

$empresa_adm_id = isset($_POST['empresa_adm_id']) ? (int)$_POST['empresa_adm_id'] : 0;

if ($empresa_adm_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID da empresa inválido']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM empresa_clientes WHERE empresa_adm_id = ? LIMIT 1");
    $stmt->execute([$empresa_adm_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && isset($result['id'])) {
        echo json_encode([
            'success' => true, 
            'empresa_cliente_id' => (int)$result['id']
        ]);
    } else {
        // Verificar se a empresa_adm existe e criar empresa_cliente se necessário
        $stmt_check = $pdo->prepare("SELECT id, razao_social, cnpj, telefone, email FROM empresa_adm WHERE id = ? LIMIT 1");
        $stmt_check->execute([$empresa_adm_id]);
        $empresa_adm = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$empresa_adm) {
            echo json_encode(['success' => false, 'error' => 'Empresa administrativa não encontrada']);
        } else {
            // Criar empresa_cliente automaticamente se não existir
            try {
                $pdo->beginTransaction();
                $stmt_create = $pdo->prepare("INSERT INTO empresa_clientes (empresa_adm_id, razao_social, cnpj, telefone, email, status) VALUES (?, ?, ?, ?, ?, 'ativo')");
                $stmt_create->execute([
                    $empresa_adm['id'],
                    $empresa_adm['razao_social'],
                    $empresa_adm['cnpj'],
                    $empresa_adm['telefone'],
                    $empresa_adm['email']
                ]);
                $empresa_cliente_id = $pdo->lastInsertId();
                $pdo->commit();
                
                echo json_encode([
                    'success' => true, 
                    'empresa_cliente_id' => (int)$empresa_cliente_id,
                    'created' => true
                ]);
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Erro ao criar empresa_cliente: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Não foi possível preparar os dados da empresa.']);
            }
        }
    }
} catch (PDOException $e) {
    error_log("Erro em get_empresa_cliente_id.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar empresa.']);
}
?>
