<?php
require_once 'config/database.php';
session_start();

// Se já estiver logado, redireciona para o dashboard
if (isset($_SESSION['seguro_logado']) && $_SESSION['seguro_logado'] === true) {
    header('Location: dashboard.php');
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
            $db = getDB();
            
            // TENTATIVA 1: Buscar usuário específico do Sistema Seguro
            $stmt = $db->prepare("
                SELECT 
                    su.id,
                    su.nome,
                    su.email,
                    su.senha,
                    su.nivel_acesso,
                    su.seguro_empresa_id,
                    su.status as usuario_status,
                    sec.razao_social,
                    sec.nome_fantasia,
                    sec.empresa_adm_id,
                    sec.status as empresa_status,
                    ea.plano,
                    ea.tem_acesso_seguro,
                    'seguro' as tipo_login
                FROM seguro_usuarios su
                INNER JOIN seguro_empresa_clientes sec ON su.seguro_empresa_id = sec.id
                INNER JOIN empresa_adm ea ON sec.empresa_adm_id = ea.id
                WHERE su.email = ?
            ");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();
            
            // TENTATIVA 2: Se não encontrou em seguro_usuarios, buscar em usuarios (sistema frotas)
            if (!$usuario) {
                $stmt = $db->prepare("
                    SELECT 
                        u.id,
                        u.nome,
                        u.email,
                        u.senha,
                        'operador' as nivel_acesso,
                        u.empresa_id,
                        u.status as usuario_status,
                        ec.razao_social,
                        ec.razao_social as nome_fantasia,
                        ec.empresa_adm_id,
                        ec.status as empresa_status,
                        ea.plano,
                        ea.tem_acesso_seguro,
                        'frotas' as tipo_login
                    FROM usuarios u
                    INNER JOIN empresa_clientes ec ON u.empresa_id = ec.id
                    INNER JOIN empresa_adm ea ON ec.empresa_adm_id = ea.id
                    WHERE u.email = ? AND u.status = 'ativo'
                ");
                $stmt->execute([$email]);
                $usuario = $stmt->fetch();
                
                // Se encontrou usuário do sistema de frotas, buscar/criar empresa no Sistema Seguro
                if ($usuario) {
                    // Verificar se já existe empresa no Sistema Seguro
                    $stmtEmpresa = $db->prepare("SELECT id FROM seguro_empresa_clientes WHERE empresa_adm_id = ?");
                    $stmtEmpresa->execute([$usuario['empresa_adm_id']]);
                    $empresaSeguro = $stmtEmpresa->fetch();
                    
                    if ($empresaSeguro) {
                        $usuario['seguro_empresa_id'] = $empresaSeguro['id'];
                    } else {
                        // Empresa não existe no Sistema Seguro, não pode acessar ainda
                        $usuario = null;
                    }
                }
            }

            if ($usuario) {
                // Verificar senha
                if (password_verify($senha, $usuario['senha'])) {
                    // Verificar se a empresa tem acesso ao Sistema Seguro
                    if ($usuario['tem_acesso_seguro'] !== 'sim') {
                        $erro = "Sua empresa não possui acesso ao Sistema Seguro. É necessário plano Premium ou Enterprise.";
                    }
                    // Verificar se a empresa está ativa
                    elseif ($usuario['empresa_status'] !== 'ativo') {
                        $erro = "Sua empresa está inativa. Entre em contato com o administrador.";
                    }
                    // Verificar se o plano é Premium ou Enterprise
                    elseif ($usuario['plano'] !== 'premium' && $usuario['plano'] !== 'enterprise') {
                        $erro = "Sua empresa não possui plano Premium ou Enterprise. Faça upgrade para acessar o Sistema Seguro.";
                    }
                    // Verificar se usuário está ativo
                    elseif ($usuario['usuario_status'] !== 'ativo') {
                        $erro = "Usuário inativo. Entre em contato com o administrador.";
                    }
                    else {
                        // Login bem-sucedido!
                        $_SESSION['seguro_logado'] = true;
                        $_SESSION['seguro_usuario_id'] = $usuario['id'];
                        $_SESSION['seguro_usuario_nome'] = $usuario['nome'];
                        $_SESSION['seguro_usuario_email'] = $usuario['email'];
                        $_SESSION['seguro_usuario_nivel'] = $usuario['nivel_acesso'];
                        $_SESSION['seguro_empresa_id'] = $usuario['seguro_empresa_id'];
                        $_SESSION['seguro_empresa_nome'] = $usuario['razao_social'];
                        $_SESSION['seguro_empresa_fantasia'] = $usuario['nome_fantasia'] ?? $usuario['razao_social'];
                        $_SESSION['empresa_adm_id'] = $usuario['empresa_adm_id'];
                        $_SESSION['tipo_login'] = $usuario['tipo_login']; // 'seguro' ou 'frotas'
                        
                        // Se for login do sistema frotas, também manter sessão do frotas
                        if ($usuario['tipo_login'] === 'frotas') {
                            $_SESSION['loggedin'] = true;
                            $_SESSION['id'] = $usuario['id'];
                            $_SESSION['usuario_id'] = $usuario['id'];
                            $_SESSION['nome'] = $usuario['nome'];
                            $_SESSION['email'] = $usuario['email'];
                            $_SESSION['empresa_id'] = $usuario['empresa_id'];
                        }
                        
                        // Atualizar último acesso (se for usuário do Sistema Seguro)
                        if ($usuario['tipo_login'] === 'seguro') {
                            $stmtUpdate = $db->prepare("UPDATE seguro_usuarios SET ultimo_acesso = NOW() WHERE id = ?");
                            $stmtUpdate->execute([$usuario['id']]);
                        }
                        
                        // Registrar log de acesso
                        $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
                        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';
                        
                        $stmtLog = $db->prepare("
                            INSERT INTO seguro_logs 
                            (seguro_empresa_id, usuario_id, acao, modulo, descricao, ip, user_agent, data_hora)
                            VALUES (?, ?, 'login', 'autenticacao', ?, ?, ?, NOW())
                        ");
                        $stmtLog->execute([
                            $usuario['seguro_empresa_id'],
                            $usuario['id'],
                            'Login realizado com sucesso - Tipo: ' . $usuario['tipo_login'],
                            $ip,
                            substr($userAgent, 0, 255)
                        ]);
                        
                        // Redirecionar para o dashboard
                        header('Location: dashboard.php');
                        exit;
                    }
                } else {
                    $erro = "E-mail ou senha inválidos.";
                }
            } else {
                $erro = "E-mail ou senha inválidos ou sua empresa não tem acesso ao Sistema Seguro.";
            }
        } catch (PDOException $e) {
            $erro = "Erro ao tentar fazer login: " . $e->getMessage();
            error_log("Erro de login Sistema Seguro: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema Seguro</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Arial, sans-serif;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 450px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 50px 40px;
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-logo i {
            font-size: 70px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .login-title {
            color: #333;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .login-subtitle {
            color: #666;
            font-size: 16px;
            margin-bottom: 35px;
            text-align: center;
        }
        
        .error-message {
            color: #fff;
            background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
            font-size: 14px;
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-group label {
            color: #555;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 16px;
        }
        
        .form-group input[type="email"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 14px 14px 14px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            color: #333;
            outline: none;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-options label {
            color: #666;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .form-options input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .form-options a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }
        
        .form-options a:hover {
            color: #764ba2;
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .btn-login:disabled {
            background: #ccc;
            cursor: not-allowed;
            box-shadow: none;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #e0e0e0;
            color: #999;
            font-size: 13px;
        }
        
        .login-footer i {
            color: #667eea;
            margin-right: 5px;
        }
        
        @media (max-width: 500px) {
            .login-container {
                padding: 35px 25px;
            }
            
            .login-title {
                font-size: 24px;
            }
            
            .login-subtitle {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <i class="fas fa-shield-alt"></i>
        </div>
        
        <div class="login-title">Sistema Seguro</div>
        <div class="login-subtitle">Gestão de Clientes Comissionados</div>
        
        <?php if ($erro): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label for="email">E-mail</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="seu@email.com" 
                        required 
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        autocomplete="email"
                    >
                </div>
            </div>
            
            <div class="form-group">
                <label for="senha">Senha</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input 
                        type="password" 
                        id="senha" 
                        name="senha" 
                        placeholder="••••••••" 
                        required
                        autocomplete="current-password"
                    >
                </div>
            </div>
            
            <div class="form-options">
                <label>
                    <input type="checkbox" name="remember">
                    Lembrar-me
                </label>
                <a href="#" onclick="alert('Entre em contato com o administrador'); return false;">
                    Esqueceu a senha?
                </a>
            </div>
            
            <button type="submit" class="btn-login" id="btnSubmit">
                <i class="fas fa-sign-in-alt"></i> Entrar
            </button>
        </form>
        
        <div class="login-footer">
            <i class="fas fa-shield-check"></i>
            Acesso seguro e criptografado
        </div>
    </div>
    
    <script>
        // Prevenir múltiplos envios do formulário
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitButton = document.getElementById('btnSubmit');
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Entrando...';
            
            // Se houver erro, reabilitar o botão após 3 segundos
            setTimeout(function() {
                if (submitButton.disabled) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = '<i class="fas fa-sign-in-alt"></i> Entrar';
                }
            }, 3000);
        });
        
        // Auto-focus no campo de email
        document.getElementById('email').focus();
    </script>
</body>
</html>
