<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

configure_session();
session_start();

$page_title = 'Resetar Senha';
$message = '';
$error = '';

// If user is already logged in, redirect to index
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("location: index.php");
    exit;
}

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    header("location: login.php");
    exit;
}

$token = $_GET['token'];

// Verify token
try {
    $conn = getConnection();
    $sql = "SELECT id, nome, email FROM usuarios WHERE reset_token = :token AND reset_expires > NOW() AND status = 'ativo'";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(":token", $token, PDO::PARAM_STR);
    
    if ($stmt->execute() && $stmt->rowCount() == 1) {
        $user = $stmt->fetch();
        
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $password = trim($_POST["password"]);
            $confirm_password = trim($_POST["confirm_password"]);
            
            if (empty($password)) {
                $error = "Por favor, digite uma nova senha.";
            } else if (strlen($password) < 6) {
                $error = "A senha deve ter pelo menos 6 caracteres.";
            } else if ($password !== $confirm_password) {
                $error = "As senhas não coincidem.";
            } else {
                // Update password and clear reset token
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql_update = "UPDATE usuarios SET senha = :password, reset_token = NULL, reset_expires = NULL WHERE id = :id";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bindParam(":password", $hashed_password, PDO::PARAM_STR);
                $stmt_update->bindParam(":id", $user['id'], PDO::PARAM_INT);
                
                if ($stmt_update->execute()) {
                    $message = "Senha alterada com sucesso! Você pode fazer login agora.";
                    $show_form = false;
                } else {
                    $error = "Erro ao alterar senha. Tente novamente.";
                }
            }
        }
    } else {
        $error = "Token inválido ou expirado. Solicite um novo link de recuperação.";
        $show_form = false;
    }
} catch(PDOException $e) {
    $error = "Erro: " . $e->getMessage();
    $show_form = false;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Sistema de Gestão de Frotas</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="icon" type="image/png" href="logo.png">
    
    <style>
        body {
            background: #101522;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        .login-container {
            background: #1a2332;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        
        .login-header {
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: #ffffff;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #b8c2d0;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            color: #ffffff;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #2d3748;
            border-radius: 5px;
            background: #243041;
            color: #ffffff;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
        }
        
        .btn-primary {
            width: 100%;
            padding: 12px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-secondary {
            width: 100%;
            padding: 12px;
            background: transparent;
            color: #b8c2d0;
            border: 1px solid #2d3748;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            box-sizing: border-box;
        }
        
        .btn-secondary:hover {
            background: #243041;
            color: #ffffff;
        }
        
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .back-link {
            margin-top: 20px;
            text-align: center;
        }
        
        .back-link a {
            color: #3b82f6;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><i class="fas fa-lock"></i> Resetar Senha</h1>
            <p>Digite sua nova senha</p>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="message success">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="message error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($show_form) && $show_form !== false): ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="password">Nova Senha</label>
                <input type="password" id="password" name="password" placeholder="Digite sua nova senha" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirmar Nova Senha</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirme sua nova senha" required>
            </div>
            
            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i> Alterar Senha
            </button>
        </form>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="login.php"><i class="fas fa-arrow-left"></i> Voltar ao Login</a>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></script>
</body>
</html>
