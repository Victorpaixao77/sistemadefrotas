<?php
require_once '../includes/conexao.php';
session_start();

// Se já estiver logado, redireciona para o painel
if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'];

    if (empty($email) || empty($senha)) {
        $erro = "Por favor, preencha todos os campos.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM usuarios_adm WHERE email = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                $_SESSION['admin_id'] = $usuario['id'];
                $_SESSION['admin_nome'] = $usuario['nome'];
                $_SESSION['admin_email'] = $usuario['email'];
                
                // Definir empresa_id padrão para administrador geral (ID 1)
                // Em um sistema multi-tenant, isso seria dinâmico baseado no usuário
                $_SESSION['empresa_id'] = 1; // Empresa administrativa padrão
                
                // Registrar login no log
                $log_file = '../logs/admin_login.log';
                $log_message = date('Y-m-d H:i:s') . " - Admin ID: {$usuario['id']} - Email: {$usuario['email']} - IP: {$_SERVER['REMOTE_ADDR']}\n";
                file_put_contents($log_file, $log_message, FILE_APPEND);
                
                header('Location: index.php');
                exit;
            } else {
                $erro = "E-mail ou senha inválidos.";
            }
        } catch (PDOException $e) {
            $erro = "Erro ao tentar fazer login. Por favor, tente novamente.";
            error_log("Erro de login: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrativo - Sistema de Frotas</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
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
            width: 100%;
            max-width: 400px;
            background: #181f2f;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
            padding: 40px 32px 32px 32px;
            margin: 40px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .login-logo {
            width: 90px;
            margin-bottom: 18px;
        }
        .login-title {
            color: #3fa6ff;
            font-size: 1.7em;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: 1px;
            text-align: center;
        }
        .login-greeting {
            color: #b0b8c9;
            font-size: 1.1em;
            margin-bottom: 28px;
            text-align: center;
        }
        .form-group {
            width: 100%;
            margin-bottom: 18px;
        }
        .form-group label {
            color: #b0b8c9;
            font-size: 1em;
            margin-bottom: 5px;
            display: block;
        }
        .form-group input[type="email"],
        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #26304a;
            border-radius: 6px;
            background: #141a29;
            color: #eaf1fb;
            font-size: 1em;
            outline: none;
            transition: border 0.2s;
        }
        .form-group input:focus {
            border-color: #3fa6ff;
        }
        .form-options {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }
        .form-options label {
            color: #b0b8c9;
            font-size: 0.97em;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }
        .form-options a {
            color: #3fa6ff;
            text-decoration: none;
            font-size: 0.97em;
            transition: color 0.2s;
        }
        .form-options a:hover {
            color: #1e7ecb;
            text-decoration: underline;
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(90deg, #3fa6ff 0%, #1e7ecb 100%);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(63,166,255,0.10);
            transition: background 0.2s, box-shadow 0.2s;
        }
        .btn-login:hover {
            background: linear-gradient(90deg, #1e7ecb 0%, #3fa6ff 100%);
            box-shadow: 0 4px 16px rgba(63,166,255,0.18);
        }
        .error-message {
            color: #fff;
            background: #dc3545;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 18px;
            width: 100%;
            text-align: center;
            font-size: 1em;
        }
        @media (max-width: 500px) {
            .login-container {
                padding: 24px 8px 18px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <img src="../logo.png" alt="Logo" class="login-logo">
        <div class="login-title">FROTEC</div>
        <div class="login-greeting">Bem-vindo de volta! Acesso Administrativo</div>
        <?php if ($erro): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">E-mail:</label>
                <input type="email" id="email" name="email" placeholder="Digite seu e-mail" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            <div class="form-group">
                <label for="senha">Senha:</label>
                <input type="password" id="senha" name="senha" placeholder="Digite sua senha" required>
            </div>
            <div class="form-options">
                <label><input type="checkbox" name="remember"> Lembre-me</label>
                <a href="recuperar_senha.php">Esqueceu a senha?</a>
            </div>
            <button type="submit" class="btn-login">Entrar</button>
        </form>
    </div>
    <script>
        // Prevenir múltiplos envios do formulário
        document.querySelector('form').addEventListener('submit', function(e) {
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.textContent = 'Entrando...';
        });
    </script>
</body>
</html> 