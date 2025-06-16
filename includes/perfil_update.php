<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'db_connect.php';

configure_session();
session_start();

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['empresa_id'])) {
    header('Location: ../login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$empresa_id = $_SESSION['empresa_id'];

$conn = getConnection();
$errors = [];
$acao = isset($_POST['acao']) ? $_POST['acao'] : '';

if ($acao === 'nome') {
    $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    if ($nome === '') {
        $errors[] = 'O nome não pode estar em branco.';
    }
    if (count($errors) === 0) {
        $stmt = $conn->prepare('UPDATE usuarios SET nome = :nome WHERE id = :id AND empresa_id = :empresa_id');
        if ($stmt->execute([':nome' => $nome, ':id' => $usuario_id, ':empresa_id' => $empresa_id])) {
            $_SESSION['nome'] = $nome;
            setFlashMessage('success', 'Nome atualizado com sucesso!');
        } else {
            setFlashMessage('error', 'Erro ao atualizar o nome.');
        }
    } else {
        setFlashMessage('error', implode('<br>', $errors));
    }
}
elseif ($acao === 'email') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    if ($email === '') {
        $errors[] = 'O e-mail não pode estar em branco.';
    }
    // Verificar se o e-mail já existe para outro usuário
    $stmt = $conn->prepare('SELECT id FROM usuarios WHERE email = :email AND id != :id AND empresa_id = :empresa_id');
    $stmt->bindValue(':email', $email);
    $stmt->bindValue(':id', $usuario_id);
    $stmt->bindValue(':empresa_id', $empresa_id);
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        $errors[] = 'Este e-mail já está em uso por outro usuário.';
    }
    if (count($errors) === 0) {
        $stmt = $conn->prepare('UPDATE usuarios SET email = :email WHERE id = :id AND empresa_id = :empresa_id');
        if ($stmt->execute([':email' => $email, ':id' => $usuario_id, ':empresa_id' => $empresa_id])) {
            $_SESSION['email'] = $email;
            setFlashMessage('success', 'E-mail atualizado com sucesso!');
        } else {
            setFlashMessage('error', 'Erro ao atualizar o e-mail.');
        }
    } else {
        setFlashMessage('error', implode('<br>', $errors));
    }
}
elseif ($acao === 'senha') {
    $nova_senha = isset($_POST['nova_senha']) ? $_POST['nova_senha'] : '';
    $confirmar_senha = isset($_POST['confirmar_senha']) ? $_POST['confirmar_senha'] : '';
    if ($nova_senha === '' || $confirmar_senha === '') {
        $errors[] = 'Preencha ambos os campos de senha.';
    }
    if ($nova_senha !== $confirmar_senha) {
        $errors[] = 'As senhas não coincidem.';
    }
    if (strlen($nova_senha) < 6) {
        $errors[] = 'A nova senha deve ter pelo menos 6 caracteres.';
    }
    if (count($errors) === 0) {
        $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE usuarios SET senha = :senha WHERE id = :id AND empresa_id = :empresa_id');
        if ($stmt->execute([':senha' => $hash, ':id' => $usuario_id, ':empresa_id' => $empresa_id])) {
            setFlashMessage('success', 'Senha atualizada com sucesso!');
        } else {
            setFlashMessage('error', 'Erro ao atualizar a senha.');
        }
    } else {
        setFlashMessage('error', implode('<br>', $errors));
    }
}
elseif ($acao === 'foto') {
    $foto_perfil = null;
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['foto_perfil']['tmp_name'];
        $fileName = basename($_FILES['foto_perfil']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($fileExt, $allowed)) {
            $newFileName = 'perfil_' . $usuario_id . '_' . time() . '.' . $fileExt;
            $destPath = '../uploads/perfil/' . $newFileName;
            if (!is_dir('../uploads/perfil')) {
                mkdir('../uploads/perfil', 0777, true);
            }
            if (move_uploaded_file($fileTmp, $destPath)) {
                $foto_perfil = $newFileName;
            } else {
                $errors[] = 'Erro ao salvar a foto de perfil.';
            }
        } else {
            $errors[] = 'Formato de imagem não suportado. Use JPG, PNG ou GIF.';
        }
    } else {
        $errors[] = 'Selecione uma imagem para upload.';
    }
    if (count($errors) === 0 && $foto_perfil) {
        $stmt = $conn->prepare('UPDATE usuarios SET foto_perfil = :foto_perfil WHERE id = :id AND empresa_id = :empresa_id');
        if ($stmt->execute([':foto_perfil' => $foto_perfil, ':id' => $usuario_id, ':empresa_id' => $empresa_id])) {
            $_SESSION['foto_perfil'] = $foto_perfil;
            setFlashMessage('success', 'Foto de perfil atualizada com sucesso!');
        } else {
            setFlashMessage('error', 'Erro ao atualizar a foto de perfil.');
        }
    } else if (count($errors) > 0) {
        setFlashMessage('error', implode('<br>', $errors));
    }
} else {
    setFlashMessage('error', 'Ação inválida.');
}

header('Location: ../pages/perfil.php');
exit; 