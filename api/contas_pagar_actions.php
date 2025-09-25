<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Configurar log de erros
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

// Garantir que a saída será sempre JSON
header('Content-Type: application/json');

// Configurar sessão
configure_session();
session_start();

// Verificar se o usuário está autenticado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// Obter conexão com o banco de dados
$conn = getConnection();

// Obter ação
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            // Validar dados
            $required_fields = ['fornecedor', 'descricao', 'valor', 'data_vencimento', 'status_id'];
            
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Campo obrigatório não preenchido: $field");
                }
            }
            
            // Processar upload do recibo
            $recibo_arquivo = null;
            if (isset($_FILES['recibo_arquivo']) && $_FILES['recibo_arquivo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/recibos/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['recibo_arquivo']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    throw new Exception("Formato de arquivo não permitido. Use PDF, JPG, JPEG ou PNG.");
                }
                
                $recibo_arquivo = uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $recibo_arquivo;
                
                if (!move_uploaded_file($_FILES['recibo_arquivo']['tmp_name'], $upload_path)) {
                    throw new Exception("Erro ao fazer upload do recibo");
                }
            }
            
            // Preparar SQL
            $sql = "INSERT INTO contas_pagar (
                fornecedor, descricao, valor, data_vencimento, data_pagamento,
                status_id, forma_pagamento_id, banco_id, observacoes, empresa_id,
                recibo_arquivo
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $_POST['fornecedor'],
                $_POST['descricao'],
                $_POST['valor'],
                $_POST['data_vencimento'],
                !empty($_POST['data_pagamento']) ? $_POST['data_pagamento'] : null,
                $_POST['status_id'],
                !empty($_POST['forma_pagamento_id']) ? $_POST['forma_pagamento_id'] : null,
                !empty($_POST['banco_id']) ? $_POST['banco_id'] : null,
                !empty($_POST['observacoes']) ? $_POST['observacoes'] : null,
                $_SESSION['empresa_id'],
                $recibo_arquivo
            ]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Conta adicionada com sucesso']);
            } else {
                throw new Exception("Erro ao adicionar conta");
            }
            break;
            
        case 'update':
            // Validar dados
            $required_fields = ['id', 'fornecedor', 'descricao', 'valor', 'data_vencimento', 'status_id'];
            
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Campo obrigatório não preenchido: $field");
                }
            }
            
            // Processar upload do recibo
            $recibo_arquivo = null;
            if (isset($_FILES['recibo_arquivo']) && $_FILES['recibo_arquivo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/recibos/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['recibo_arquivo']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    throw new Exception("Formato de arquivo não permitido. Use PDF, JPG, JPEG ou PNG.");
                }
                
                $recibo_arquivo = uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $recibo_arquivo;
                
                if (!move_uploaded_file($_FILES['recibo_arquivo']['tmp_name'], $upload_path)) {
                    throw new Exception("Erro ao fazer upload do recibo");
                }
                
                // Remover recibo antigo se existir
                $sql_old = "SELECT recibo_arquivo FROM contas_pagar WHERE id = ? AND empresa_id = ?";
                $stmt_old = $conn->prepare($sql_old);
                $stmt_old->execute([$_POST['id'], $_SESSION['empresa_id']]);
                $old_recibo = $stmt_old->fetch(PDO::FETCH_ASSOC);
                
                if ($old_recibo && $old_recibo['recibo_arquivo']) {
                    $old_path = $upload_dir . $old_recibo['recibo_arquivo'];
                    if (file_exists($old_path)) {
                        unlink($old_path);
                    }
                }
            }
            
            // Preparar SQL
            $sql = "UPDATE contas_pagar SET
                fornecedor = ?,
                descricao = ?,
                valor = ?,
                data_vencimento = ?,
                data_pagamento = ?,
                status_id = ?,
                forma_pagamento_id = ?,
                banco_id = ?,
                observacoes = ?" .
                ($recibo_arquivo ? ", recibo_arquivo = ?" : "") . "
            WHERE id = ? AND empresa_id = ?";
            
            $params = [
                $_POST['fornecedor'],
                $_POST['descricao'],
                $_POST['valor'],
                $_POST['data_vencimento'],
                !empty($_POST['data_pagamento']) ? $_POST['data_pagamento'] : null,
                $_POST['status_id'],
                !empty($_POST['forma_pagamento_id']) ? $_POST['forma_pagamento_id'] : null,
                !empty($_POST['banco_id']) ? $_POST['banco_id'] : null,
                !empty($_POST['observacoes']) ? $_POST['observacoes'] : null
            ];
            
            if ($recibo_arquivo) {
                $params[] = $recibo_arquivo;
            }
            
            $params[] = $_POST['id'];
            $params[] = $_SESSION['empresa_id'];
            
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Conta atualizada com sucesso']);
            } else {
                throw new Exception("Erro ao atualizar conta");
            }
            break;
            
        case 'delete':
            // Validar ID
            $id = $_GET['id'] ?? 0;
            if (empty($id)) {
                throw new Exception("ID não fornecido");
            }
            
            // Remover recibo se existir
            $sql_recibo = "SELECT recibo_arquivo FROM contas_pagar WHERE id = ? AND empresa_id = ?";
            $stmt_recibo = $conn->prepare($sql_recibo);
            $stmt_recibo->execute([$id, $_SESSION['empresa_id']]);
            $recibo = $stmt_recibo->fetch(PDO::FETCH_ASSOC);
            
            if ($recibo && $recibo['recibo_arquivo']) {
                $upload_dir = '../uploads/recibos/';
                $recibo_path = $upload_dir . $recibo['recibo_arquivo'];
                if (file_exists($recibo_path)) {
                    unlink($recibo_path);
                }
            }
            
            // Preparar SQL
            $sql = "DELETE FROM contas_pagar WHERE id = ? AND empresa_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id, $_SESSION['empresa_id']]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Conta excluída com sucesso']);
            } else {
                throw new Exception("Erro ao excluir conta");
            }
            break;
            
        case 'get':
            // Validar ID
            $id = $_GET['id'] ?? 0;
            if (empty($id)) {
                throw new Exception("ID não fornecido");
            }
            
            // Buscar conta
            $sql = "SELECT cp.*, 
                s.nome as status_nome,
                fp.nome as forma_pagamento_nome,
                b.nome as banco_nome
            FROM contas_pagar cp
            LEFT JOIN status_contas_pagar s ON cp.status_id = s.id
            LEFT JOIN formas_pagamento fp ON cp.forma_pagamento_id = fp.id
            LEFT JOIN bancos b ON cp.banco_id = b.id
            WHERE cp.id = ? AND cp.empresa_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id, $_SESSION['empresa_id']]);
            $conta = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($conta) {
                echo json_encode(['success' => true, 'data' => $conta]);
            } else {
                throw new Exception("Conta não encontrada");
            }
            break;
            
        default:
            throw new Exception("Ação inválida");
    }
} catch (Exception $e) {
    error_log("Erro em contas_pagar_actions.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 