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

// Check for "Remember Me" cookie (only if not coming from logout)
if (isset($_COOKIE['remember_user']) && !isset($_SESSION['loggedin']) && !isset($_GET['logout'])) {
    try {
        $cookie_value = base64_decode($_COOKIE['remember_user']);
        $parts = explode(':', $cookie_value);
        
        if (count($parts) == 2) {
            $user_id = $parts[0];
            $user_email = $parts[1];
            
            $conn = getConnection();
            $sql = "SELECT u.id, u.nome, u.email, u.empresa_id, u.status as usuario_status, u.foto_perfil, u.tipo_usuario 
                   FROM usuarios u 
                   JOIN empresa_clientes e ON u.empresa_id = e.id 
                   WHERE u.id = :user_id AND u.email = :user_email AND u.status = 'ativo'";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->bindParam(":user_email", $user_email, PDO::PARAM_STR);
            
            if ($stmt->execute() && $stmt->rowCount() == 1) {
                $row = $stmt->fetch();
                
                // Store data in session variables
                $_SESSION["loggedin"] = true;
                $_SESSION["id"] = $row["id"];
                $_SESSION["usuario_id"] = $row["id"];
                $_SESSION["nome"] = $row["nome"];
                $_SESSION["email"] = $row["email"];
                $_SESSION["empresa_id"] = $row["empresa_id"];
                $_SESSION["foto_perfil"] = $row["foto_perfil"];
                $_SESSION["tipo_usuario"] = $row["tipo_usuario"];
                
                // Redirect to index
                header("location: index.php");
                exit;
            } else {
                // Invalid cookie, clear it
                setcookie("remember_user", "", time() - 3600, "/", "", false, true);
            }
        }
    } catch (Exception $e) {
        // Clear invalid cookie
        setcookie("remember_user", "", time() - 3600, "/", "", false, true);
    }
}

// Clear any existing session data if not properly logged in
if (!isset($_SESSION['empresa_id'])) {
    session_unset();
    session_destroy();
    session_start();
}

$error = '';
$success_message = '';
$saved_username = '';
$remember_checked = '';

// Check if user just logged out
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    $success_message = 'Você foi desconectado com sucesso.';
}

// Check for saved login data cookie (sempre, exceto se for login automático)
if (isset($_COOKIE['saved_login']) && !isset($_SESSION['loggedin'])) {
    try {
        $saved_data = json_decode(base64_decode($_COOKIE['saved_login']), true);
        if ($saved_data && isset($saved_data['username'])) {
            $saved_username = htmlspecialchars($saved_data['username']);
            $remember_checked = 'checked';
        }
    } catch (Exception $e) {
        // Cookie inválido, limpar
        setcookie("saved_login", "", time() - 3600, "/", "", false, true);
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    $remember = isset($_POST["remember"]) ? true : false;
    
    try {
        $conn = getConnection();
        
        // Prepare a select statement
        $sql = "SELECT u.id, u.nome, u.email, u.senha, u.empresa_id, u.status as usuario_status, u.foto_perfil, u.tipo_usuario 
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
                        $_SESSION["tipo_usuario"] = $row["tipo_usuario"];
                        
                        // Handle "Remember Me" functionality
                        if ($remember) {
                            // Set cookie for 30 days
                            $cookie_value = base64_encode($row["id"] . ":" . $row["email"]);
                            setcookie("remember_user", $cookie_value, time() + (30 * 24 * 60 * 60), "/", "", false, true);
                            
                            // Save login data for form restoration
                            $saved_data = json_encode(['username' => $username]);
                            $saved_cookie_value = base64_encode($saved_data);
                            setcookie("saved_login", $saved_cookie_value, time() + (30 * 24 * 60 * 60), "/", "", false, true);
                        } else {
                            // Clear remember me cookie if not checked
                            setcookie("remember_user", "", time() - 3600, "/", "", false, true);
                            setcookie("saved_login", "", time() - 3600, "/", "", false, true);
                        }
                        
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
    <!-- Favicon -->
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
        
        .success-message {
            color: #fff;
            background: #28a745;
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
            top: 90%; right: 18%; font-size: 1.1em; color: #1e7ecb; opacity: 0.13;}
        .phrase9 {
            top: 10%; right: 35%; font-size: 1.05em; color: #fff; opacity: 0.13;}
        .phrase10 {
            top: 25%; left: 8%; font-size: 1.2em; color: #3fa6ff; opacity: 0.17;}
        .phrase11 {
            top: 45%; right: 5%; font-size: 1.1em; color: #fff; opacity: 0.15;}
        .phrase12 {
            bottom: 35%; left: 4%; font-size: 1.15em; color: #1e7ecb; opacity: 0.14;}
        .phrase13 {
            top: 70%; left: 12%; font-size: 1.0em; color: #3fa6ff; opacity: 0.16;}
        .phrase14 {
            top: 32%; right: 15%; font-size: 1.05em; color: #fff; opacity: 0.13;}
        .phrase15 {
            bottom: 25%; right: 20%; font-size: 1.1em; color: #3fa6ff; opacity: 0.18;}
        .phrase16 {
            top: 15%; left: 25%; font-size: 1.0em; color: #1e7ecb; opacity: 0.15;}
        .phrase17 {
            bottom: 50%; right: 65%; font-size: 1.05em; color: #fff; opacity: 0.14;}
        .phrase18 {
            top: 85%; left: 25%; font-size: 1.1em; color: #3fa6ff; opacity: 0.17;}
        
        /* Media query para tablets/iPad (768px - 1024px) */
        @media (min-width: 701px) and (max-width: 1024px) {
            .bg-phrase { 
                font-size: 0.8em !important; 
                max-width: 180px; 
                opacity: 0.1 !important;
            }
            .login-background-texts { 
                position: fixed; 
                inset: 0;
                z-index: 0;
            }
            .login-container {
                position: relative;
                z-index: 1;
            }
            /* Reposicionar frases para não sobrepor o formulário centralizado */
            /* Esconder frases que ficam na área central (30% - 70% vertical) */
            .phrase4, .phrase7, .phrase9, .phrase14, .phrase16, .phrase17 {
                display: none !important;
            }
            .phrase1 { top: 5% !important; left: 5% !important; }
            .phrase2 { top: 10% !important; right: 5% !important; }
            .phrase3 { bottom: 5% !important; left: 5% !important; }
            .phrase5 { bottom: 10% !important; right: 5% !important; }
            .phrase6 { top: 15% !important; right: 3% !important; }
            .phrase8 { display: none !important; }
            .phrase10 { top: 8% !important; left: 6% !important; }
            .phrase11 { top: 20% !important; right: 6% !important; }
            .phrase12 { bottom: 8% !important; left: 6% !important; }
            .phrase13 { top: 25% !important; left: 7% !important; }
            .phrase15 { bottom: 12% !important; right: 8% !important; }
            .phrase18 { bottom: 8% !important; left: 30% !important; }
        }
        
        @media (max-width: 700px) {
            .bg-phrase { 
                font-size: 0.75em !important; 
                max-width: 140px; 
                opacity: 0.1 !important;
            }
            .login-background-texts { 
                position: fixed; 
                inset: 0;
                z-index: 0;
            }
            .login-container {
                position: relative;
                z-index: 1;
            }
            /* Ajustar posicionamento das frases para não sobrepor o formulário */
            .phrase1 { top: 2% !important; left: 2% !important; }
            .phrase2 { top: 5% !important; right: 2% !important; }
            .phrase3 { bottom: 2% !important; left: 2% !important; }
            .phrase4 { top: 8% !important; left: 1% !important; font-size: 0.9em !important; }
            .phrase5 { bottom: 5% !important; right: 2% !important; }
            .phrase6 { top: 12% !important; right: 1% !important; }
            .phrase7 { bottom: 8% !important; left: 50% !important; transform: translateX(-50%); }
            .phrase8 { display: none; }
            .phrase9 { top: 3% !important; right: 30% !important; }
            .phrase10 { top: 6% !important; left: 3% !important; }
            .phrase11 { top: 10% !important; right: 3% !important; }
            .phrase12 { bottom: 3% !important; left: 3% !important; }
            .phrase13 { top: 15% !important; left: 4% !important; }
            .phrase14 { top: 9% !important; right: 8% !important; }
            .phrase15 { bottom: 6% !important; right: 10% !important; }
            .phrase16 { top: 4% !important; left: 20% !important; }
            .phrase17 { display: none; }
            .phrase18 { bottom: 4% !important; left: 20% !important; }
        }
        
        @media (max-width: 500px) {
            .bg-phrase { 
                font-size: 0.65em !important; 
                max-width: 120px;
                opacity: 0.08 !important;
            }
            /* Esconder mais frases em telas muito pequenas */
            .phrase4, .phrase6, .phrase9, .phrase10, .phrase11, .phrase13, .phrase14, .phrase15, .phrase16, .phrase18 {
                display: none;
            }
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
        <!-- Frases adicionais -->
        <div class="bg-phrase phrase10">Transforme dados em decisões inteligentes.</div>
        <div class="bg-phrase phrase11">Tenha sua frota sob controle em qualquer lugar.</div>
        <div class="bg-phrase phrase12">Automatize processos e reduza retrabalho.</div>
        <div class="bg-phrase phrase13">Mais tempo para focar no crescimento do seu negócio.</div>
        <div class="bg-phrase phrase14">Relatórios claros e completos em poucos cliques.</div>
        <div class="bg-phrase phrase15">Do cadastro ao controle financeiro, tudo em um só sistema.</div>
        <div class="bg-phrase phrase16">Tecnologia feita para simplificar a gestão de frotas.</div>
        <div class="bg-phrase phrase17">Gestão inteligente que cresce junto com a sua empresa.</div>
        <div class="bg-phrase phrase18">Monitoramento ágil, decisões rápidas.</div>
    </div>
    <div class="login-container">
        <img src="logo.png" alt="Logo" class="login-logo">
        <div class="login-title">FROTEC</div>
        <div class="login-greeting">Bem-vindo de volta! Acesse sua conta</div>

        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="success-message" id="successMessage"><?php echo $success_message; ?></div>
        <?php endif; ?>
        

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="username">E-mail</label>
                <input type="email" id="username" name="username" placeholder="Digite seu e-mail" value="<?php echo $saved_username; ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password" placeholder="Digite sua senha" required>
            </div>
            <div class="form-options">
                <label><input type="checkbox" name="remember" id="remember" <?php echo $remember_checked; ?>> Lembre-me</label>
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
        
        // Fazer mensagem de sucesso desaparecer automaticamente
        document.addEventListener('DOMContentLoaded', function() {
            const successMessage = document.getElementById('successMessage');
            if (successMessage) {
                setTimeout(function() {
                    successMessage.style.opacity = '0';
                    successMessage.style.transition = 'opacity 0.5s ease-out';
                    setTimeout(function() {
                        successMessage.remove();
                    }, 500);
                }, 3000); // Desaparece após 3 segundos
            }
        });
    </script>
</body>
</html> 