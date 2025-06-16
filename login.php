<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Configure session before starting it
configure_session();

// Start session
session_start();

// If user is already logged in and has empresa_id, redirect to index
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && isset($_SESSION['empresa_id'])) {
    header("location: index.php");
    exit;
}

// Clear any existing session data if not properly logged in
if (!isset($_SESSION['empresa_id'])) {
    session_unset();
    session_destroy();
    session_start();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    
    try {
        $conn = getConnection();
        
        // Prepare a select statement
        $sql = "SELECT u.id, u.nome, u.email, u.senha, u.empresa_id, u.status as usuario_status, u.foto_perfil 
               FROM usuarios u 
               JOIN empresa_clientes e ON u.empresa_id = e.id 
               WHERE u.email = :username";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bindParam(":username", $username, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                if ($stmt->rowCount() == 1) {
                    $row = $stmt->fetch();
                    
                    // Check if user is active
                    if ($row['usuario_status'] !== 'ativo') {
                        $error = "Usuário inativo. Entre em contato com o suporte.";
                    }
                    // Verify password
                    else if (password_verify($password, $row['senha'])) {
                        // Store data in session variables
                        $_SESSION["loggedin"] = true;
                        $_SESSION["id"] = $row["id"];
                        $_SESSION["usuario_id"] = $row["id"];
                        $_SESSION["nome"] = $row["nome"];
                        $_SESSION["email"] = $row["email"];
                        $_SESSION["empresa_id"] = $row["empresa_id"];
                        $_SESSION["foto_perfil"] = $row["foto_perfil"];
                        
                        // Redirect user to the original URL or index page
                        $redirect_url = isset($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : 'index.php';
                        unset($_SESSION['redirect_url']); // Limpa a URL de redirecionamento
                        header("location: " . $redirect_url);
                        exit;
                    } else {
                        $error = "Senha inválida.";
                    }
                } else {
                    $error = "Usuário não encontrado.";
                }
            } else {
                $error = "Oops! Algo deu errado. Por favor, tente novamente mais tarde.";
            }
        }
    } catch(PDOException $e) {
        $error = "Erro: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Gestão de Frotas</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
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
        .login-background-texts {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
        }
        .bg-phrase {
            position: absolute;
            color: #3fa6ff;
            opacity: 0.18;
            font-weight: 700;
            font-family: 'Segoe UI', Arial, sans-serif;
            text-shadow: 0 2px 12px #000, 0 1px 0 #fff1;
            user-select: none;
            line-height: 1.3;
            max-width: 320px;
            transition: opacity 0.3s;
        }
        .phrase1 {
            top: 7%; left: 7%; font-size: 1.3em; color: #3fa6ff; opacity: 0.22;}
        .phrase2 {
            top: 18%; right: 8%; font-size: 1.1em; color: #fff; opacity: 0.16;}
        .phrase3 {
            bottom: 10%; left: 6%; font-size: 1.05em; color: #3fa6ff; opacity: 0.19;}
        .phrase4 {
            top: 38%; left: 2%; font-size: 1.7em; color: #1e7ecb; opacity: 0.13;}
        .phrase5 {
            bottom: 18%; right: 7%; font-size: 1.3em; color: #3fa6ff; opacity: 0.18;}
        .phrase6 {
            top: 60%; right: 3%; font-size: 1.2em; color: #fff; opacity: 0.15;}
        .phrase7 {
            bottom: 5%; left: 40%; font-size: 1.1em; color: #3fa6ff; opacity: 0.16;}
        .phrase8 {
            top: 80%; right: 18%; font-size: 1.1em; color: #1e7ecb; opacity: 0.13;}
        .phrase9 {
            top: 10%; right: 35%; font-size: 1.05em; color: #fff; opacity: 0.13;}
        @media (max-width: 700px) {
            .bg-phrase { font-size: 0.9em !important; max-width: 180px; }
            .phrase1, .phrase2, .phrase3, .phrase4, .phrase5, .phrase6, .phrase7, .phrase8, .phrase9 {
                left: unset; right: unset; top: unset; bottom: unset;
                position: static; display: block; margin: 8px auto; text-align: center;
            }
            .login-background-texts { position: static; margin-bottom: 12px; }
        }
    </style>
</head>
<body>
    <div class="login-background-texts">
        <div class="bg-phrase phrase1">Simples para quem dirige.<br>Poderoso para quem gerencia.</div>
        <div class="bg-phrase phrase2">Chega de planilhas!<br>Gestão de frotas simples, visual e eficiente.</div>
        <div class="bg-phrase phrase3">Ideal para pequenas e médias empresas.<br>Organize veículos, motoristas, rotas e despesas sem complicações.</div>
        <div class="bg-phrase phrase4">Mais Segurança</div>
        <div class="bg-phrase phrase5">Aumente a Eficiência</div>
        <div class="bg-phrase phrase6">Reduza Custos</div>
        <div class="bg-phrase phrase7">Tempo economizado</div>
        <div class="bg-phrase phrase8">Redução de custos</div>
        <div class="bg-phrase phrase9">Aumento médio de eficiência</div>
    </div>
    <div class="login-container">
        <img src="logo.png" alt="Logo" class="login-logo">
        <div class="login-title">FROTEC</div>
        <div class="login-greeting">Bem-vindo de volta! Acesse sua conta</div>

        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="username">E-mail</label>
                <input type="email" id="username" name="username" placeholder="Digite seu e-mail" required>
            </div>
            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password" placeholder="Digite sua senha" required>
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