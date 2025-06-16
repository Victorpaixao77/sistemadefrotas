<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

configure_session();
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_authentication();
$empresa_id = $_SESSION['empresa_id'] ?? null;
if (!$empresa_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID da empresa não encontrado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? null);
if ($method === 'POST' && !$action) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;
}

try {
    $conn = getConnection();
    if ($action === 'get_config') {
        $stmt = $conn->prepare('SELECT nome_personalizado, logo_empresa FROM configuracoes WHERE empresa_id = :empresa_id LIMIT 1');
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode([
            'success' => true, 
            'nome_personalizado' => $row ? $row['nome_personalizado'] : 'Desenvolvimento',
            'logo_empresa' => $row ? $row['logo_empresa'] : null
        ]);
        exit;
    }
    if ($action === 'update_nome') {
        $input = json_decode(file_get_contents('php://input'), true);
        $nome = trim($input['nome_personalizado'] ?? '');
        if (!$nome) throw new Exception('Nome não informado');
        // Atualiza ou insere
        $stmt = $conn->prepare('SELECT id FROM configuracoes WHERE empresa_id = :empresa_id LIMIT 1');
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetch()) {
            $stmt2 = $conn->prepare('UPDATE configuracoes SET nome_personalizado = :nome, data_atualizacao = NOW() WHERE empresa_id = :empresa_id');
            $stmt2->bindParam(':nome', $nome);
            $stmt2->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt2->execute();
        } else {
            $stmt2 = $conn->prepare('INSERT INTO configuracoes (empresa_id, nome_personalizado, data_criacao, data_atualizacao) VALUES (:empresa_id, :nome, NOW(), NOW())');
            $stmt2->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt2->bindParam(':nome', $nome);
            $stmt2->execute();
        }
        echo json_encode(['success' => true]);
        exit;
    }
    if ($action === 'upload_logo') {
        if (!isset($_FILES['logo'])) {
            echo json_encode(['success' => false, 'error' => 'Nenhum arquivo enviado']);
            exit;
        }

        $file = $_FILES['logo'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowed_types)) {
            echo json_encode(['success' => false, 'error' => 'Tipo de arquivo não permitido']);
            exit;
        }

        if ($file['size'] > $max_size) {
            echo json_encode(['success' => false, 'error' => 'Arquivo muito grande']);
            exit;
        }

        $upload_dir = '../uploads/logos/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $filename = uniqid() . '_' . basename($file['name']);
        $filepath = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $stmt = $conn->prepare('UPDATE configuracoes SET logo_empresa = :logo_path WHERE empresa_id = :empresa_id');
            $stmt->bindParam(':logo_path', $filename);
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'logo_path' => 'uploads/logos/' . $filename]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erro ao salvar no banco de dados']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Erro ao fazer upload do arquivo']);
        }
        exit;
    }
    if ($action === 'cadastrar_motorista') {
        if (!isset($_POST['nome_motorista']) || !isset($_POST['senha_motorista'])) {
            echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
            exit;
        }

        $nome = trim($_POST['nome_motorista']);
        $senha = $_POST['senha_motorista'];

        if (strlen($nome) < 3) {
            echo json_encode(['success' => false, 'error' => 'Nome muito curto']);
            exit;
        }

        if (strlen($senha) < 6) {
            echo json_encode(['success' => false, 'error' => 'Senha muito curta']);
            exit;
        }

        // Verifica se já existe um motorista com este nome
        $stmt = $conn->prepare('SELECT id FROM usuarios_motoristas WHERE nome = :nome AND empresa_id = :empresa_id');
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'error' => 'Já existe um motorista com este nome']);
            exit;
        }

        // Processa a foto se foi enviada
        $foto_path = null;
        if (isset($_FILES['foto_motorista']) && $_FILES['foto_motorista']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['foto_motorista'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($file['type'], $allowed_types)) {
                echo json_encode(['success' => false, 'error' => 'Tipo de arquivo não permitido']);
                exit;
            }

            if ($file['size'] > $max_size) {
                echo json_encode(['success' => false, 'error' => 'Arquivo muito grande']);
                exit;
            }

            $upload_dir = '../uploads/motoristas/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $filename = uniqid() . '_' . basename($file['name']);
            $filepath = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $foto_path = 'uploads/motoristas/' . $filename;
            }
        }

        // Cadastra o motorista
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('INSERT INTO usuarios_motoristas (empresa_id, nome, senha, foto_perfil, status, data_cadastro) VALUES (:empresa_id, :nome, :senha, :foto, 1, NOW())');
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':senha', $senha_hash);
        $stmt->bindParam(':foto', $foto_path);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erro ao cadastrar motorista']);
        }
        exit;
    }
    throw new Exception('Ação inválida');
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 