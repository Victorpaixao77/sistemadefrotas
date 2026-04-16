<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/api_json.php';

configure_session();
session_start();

if (!isset($_SESSION['empresa_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];
$conn = getConnection();

// Protege mutações (POST/PUT/PATCH/DELETE) com CSRF.
api_require_csrf_json();

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
        // Login do app = e-mail cadastrado no motorista (campo nome em usuarios_motoristas)
        if (empty($_POST['motorista_id']) || empty($_POST['senha'])) {
            echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
            exit;
        }

        $motorista_id = (int) $_POST['motorista_id'];

        $stEmail = $conn->prepare('SELECT email FROM motoristas WHERE id = :id AND empresa_id = :eid LIMIT 1');
        $stEmail->execute([':id' => $motorista_id, ':eid' => $empresa_id]);
        $rowM = $stEmail->fetch(PDO::FETCH_ASSOC);
        $emailRaw = isset($rowM['email']) ? trim((string) $rowM['email']) : '';
        if ($emailRaw === '') {
            echo json_encode(['success' => false, 'error' => 'Cadastre o e-mail do motorista antes de criar o acesso ao app.']);
            exit;
        }
        if (!filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error' => 'E-mail do motorista inválido. Corrija no cadastro de motoristas.']);
            exit;
        }
        $nomeLogin = strtolower($emailRaw);

        // Verificar se o motorista já tem usuário
        $stmt = $conn->prepare('SELECT id FROM usuarios_motoristas WHERE motorista_id = :motorista_id');
        $stmt->bindParam(':motorista_id', $motorista_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Este motorista já possui um usuário cadastrado']);
            exit;
        }

        // Verificar se o e-mail (login) já está em uso por outro usuário motorista da empresa
        $stmt = $conn->prepare('SELECT id FROM usuarios_motoristas WHERE nome = :nome AND empresa_id = :empresa_id');
        $stmt->bindParam(':nome', $nomeLogin);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Este e-mail já está em uso como login de outro usuário motorista.']);
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
            $stmt->bindParam(':motorista_id', $motorista_id, PDO::PARAM_INT);
            $stmt->bindParam(':nome', $nomeLogin);
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
        if (empty($_POST['id'])) {
            echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
            exit;
        }

        $uid = (int) $_POST['id'];
        $stUm = $conn->prepare('SELECT motorista_id FROM usuarios_motoristas WHERE id = :id AND empresa_id = :eid LIMIT 1');
        $stUm->execute([':id' => $uid, ':eid' => $empresa_id]);
        $umRow = $stUm->fetch(PDO::FETCH_ASSOC);
        if (!$umRow) {
            echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
            exit;
        }
        $mid = (int) $umRow['motorista_id'];
        $stEmail = $conn->prepare('SELECT email FROM motoristas WHERE id = :id AND empresa_id = :eid LIMIT 1');
        $stEmail->execute([':id' => $mid, ':eid' => $empresa_id]);
        $rowM = $stEmail->fetch(PDO::FETCH_ASSOC);
        $emailRaw = isset($rowM['email']) ? trim((string) $rowM['email']) : '';
        if ($emailRaw === '' || !filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error' => 'E-mail do motorista inválido ou vazio. Atualize o cadastro do motorista.']);
            exit;
        }
        $nomeLogin = strtolower($emailRaw);

        $stmt = $conn->prepare('SELECT id FROM usuarios_motoristas WHERE nome = :nome AND id != :id AND empresa_id = :empresa_id');
        $stmt->bindParam(':nome', $nomeLogin);
        $stmt->bindParam(':id', $uid, PDO::PARAM_INT);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Este e-mail já está em uso como login de outro usuário motorista.']);
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

            $stmt->bindParam(':id', $uid, PDO::PARAM_INT);
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->bindParam(':nome', $nomeLogin);
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
                SELECT um.id, um.nome, um.status, um.data_cadastro, um.motorista_id,
                       m.email AS email_motorista
                FROM usuarios_motoristas um
                INNER JOIN motoristas m ON m.id = um.motorista_id AND m.empresa_id = um.empresa_id
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