<?php
require_once 'config.php';

// Log da sessão para debug
error_log("=== Página de login ===");
error_log("Session ID: " . session_id());
error_log("Session Name: " . session_name());
error_log("Session Status: " . session_status());
error_log("Session Data: " . print_r($_SESSION, true));
error_log("HTTP_REFERER: " . ($_SERVER['HTTP_REFERER'] ?? 'não definido'));
error_log("HTTP_COOKIE: " . ($_SERVER['HTTP_COOKIE'] ?? 'não definido'));

// Se já estiver logado, redireciona para a página inicial
if (isset($_SESSION['motorista_id'])) {
    error_log("Motorista já logado, redirecionando para index.php");
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("=== Tentativa de login ===");
    error_log("POST Data: " . print_r($_POST, true));
    
    $nome = sanitizar_input($_POST['nome'] ?? '');
    $senha = sanitizar_input($_POST['senha'] ?? '');
    
    if (empty($nome) || empty($senha)) {
        $error = 'Por favor, preencha todos os campos.';
        error_log("Campos vazios - Nome: " . $nome . ", Senha: " . $senha);
    } else {
        if (login_motorista($nome, $senha)) {
            error_log("Login bem-sucedido para o motorista: " . $nome);
            error_log("Session Data após login: " . print_r($_SESSION, true));
            header('Location: index.php');
            exit;
        } else {
            $error = 'Nome ou senha inválidos.';
            error_log("Login falhou para o motorista: " . $nome);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Frotas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo img {
            max-width: 150px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-logo">
                <h2>Sistema de Frotas</h2>
                <p class="text-muted">Área do Motorista</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="nome" class="form-label">Nome</label>
                    <input type="text" class="form-control" id="nome" name="nome" required>
                </div>
                
                <div class="mb-3">
                    <label for="senha" class="form-label">Senha</label>
                    <input type="password" class="form-control" id="senha" name="senha" required>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">Entrar</button>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 