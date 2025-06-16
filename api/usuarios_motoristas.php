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

// Processar requisições
$action = $_POST['action'] ?? $_GET['action'] ?? '';

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

    case 'delete':
        $data = json_decode(file_get_contents('php://input'), true);
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