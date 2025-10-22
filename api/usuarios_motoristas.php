<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

configure_session();
session_start();

if (!isset($_SESSION['empresa_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];
$conn = getConnection();

// Processar requisições - suportar JSON e POST
$input = file_get_contents('php://input');
$jsonData = json_decode($input, true);

// Determinar a action de diferentes fontes
$action = $_POST['action'] ?? $_GET['action'] ?? ($jsonData['action'] ?? '');

// Log para debug
error_log('API usuarios_motoristas.php - Action: ' . $action);
error_log('API usuarios_motoristas.php - Input: ' . $input);

switch ($action) {
    case 'create':
        // Validar dados
        if (empty($_POST['motorista_id']) || empty($_POST['nome']) || empty($_POST['senha'])) {
            echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
            exit;
        }

        // Verificar se o motorista já tem usuário
        $stmt = $conn->prepare('SELECT id FROM usuarios_motoristas WHERE motorista_id = :motorista_id');
        $stmt->bindParam(':motorista_id', $_POST['motorista_id'], PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Este motorista já possui um usuário cadastrado']);
            exit;
        }

        // Verificar se o nome de usuário já existe
        $stmt = $conn->prepare('SELECT id FROM usuarios_motoristas WHERE nome = :nome AND empresa_id = :empresa_id');
        $stmt->bindParam(':nome', $_POST['nome']);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Este nome de usuário já está em uso']);
            exit;
        }

        // Inserir novo usuário
        try {
            $stmt = $conn->prepare('
                INSERT INTO usuarios_motoristas (
                    empresa_id, motorista_id, nome, senha, status
                ) VALUES (
                    :empresa_id, :motorista_id, :nome, :senha, "ativo"
                )
            ');

            $senha_hash = password_hash($_POST['senha'], PASSWORD_DEFAULT);

            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->bindParam(':motorista_id', $_POST['motorista_id'], PDO::PARAM_INT);
            $stmt->bindParam(':nome', $_POST['nome']);
            $stmt->bindParam(':senha', $senha_hash);

            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erro ao cadastrar usuário']);
            }
        } catch (PDOException $e) {
            error_log('Erro ao cadastrar usuário: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro ao cadastrar usuário']);
        }
        break;

    case 'update':
        // Validar dados
        if (empty($_POST['id']) || empty($_POST['nome'])) {
            echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
            exit;
        }

        // Verificar se o nome de usuário já existe em outro usuário
        $stmt = $conn->prepare('SELECT id FROM usuarios_motoristas WHERE nome = :nome AND id != :id AND empresa_id = :empresa_id');
        $stmt->bindParam(':nome', $_POST['nome']);
        $stmt->bindParam(':id', $_POST['id'], PDO::PARAM_INT);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Este nome de usuário já está em uso por outro usuário']);
            exit;
        }

        // Atualizar usuário
        try {
            $status = $_POST['status'] ?? 'ativo';

            // Se senha foi fornecida, atualizar senha
            if (!empty($_POST['senha'])) {
                $stmt = $conn->prepare('
                    UPDATE usuarios_motoristas 
                    SET nome = :nome, senha = :senha, status = :status
                    WHERE id = :id AND empresa_id = :empresa_id
                ');
                $senha_hash = password_hash($_POST['senha'], PASSWORD_DEFAULT);
                $stmt->bindParam(':senha', $senha_hash);
            } else {
                $stmt = $conn->prepare('
                    UPDATE usuarios_motoristas 
                    SET nome = :nome, status = :status
                    WHERE id = :id AND empresa_id = :empresa_id
                ');
            }

            $stmt->bindParam(':id', $_POST['id'], PDO::PARAM_INT);
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->bindParam(':nome', $_POST['nome']);
            $stmt->bindParam(':status', $status);

            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erro ao atualizar usuário']);
            }
        } catch (PDOException $e) {
            error_log('Erro ao atualizar usuário: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro ao atualizar usuário']);
        }
        break;

    case 'get':
        if (empty($_GET['id'])) {
            echo json_encode(['success' => false, 'error' => 'ID não fornecido']);
            exit;
        }

        try {
            $stmt = $conn->prepare('
                SELECT um.id, um.nome, um.status, um.data_cadastro, um.motorista_id
                FROM usuarios_motoristas um 
                WHERE um.id = :id AND um.empresa_id = :empresa_id
            ');
            $stmt->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($usuario) {
                echo json_encode(['success' => true, 'data' => $usuario]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
            }
        } catch (PDOException $e) {
            error_log('Erro ao buscar usuário: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro ao buscar usuário']);
        }
        break;

    case 'delete':
        // Usar dados do JSON já decodificado no início
        $data = $jsonData;
        
        // Se não conseguiu via JSON, tentar via POST
        if (empty($data['id']) && !empty($_POST['id'])) {
            $data = $_POST;
        }
        
        // Log para debug
        error_log('Delete usuario motorista - ID recebido: ' . ($data['id'] ?? 'VAZIO'));
        
        if (empty($data['id'])) {
            echo json_encode(['success' => false, 'error' => 'ID não fornecido']);
            exit;
        }

        try {
            $stmt = $conn->prepare('
                DELETE FROM usuarios_motoristas 
                WHERE id = :id AND empresa_id = :empresa_id
            ');
            $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erro ao excluir usuário']);
            }
        } catch (PDOException $e) {
            error_log('Erro ao excluir usuário: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro ao excluir usuário']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Ação inválida']);
        break;
} 