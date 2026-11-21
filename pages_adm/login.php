<?php
require_once __DIR__ . '/../includes/conexao.php';
require_once __DIR__ . '/includes/adm_session.php';
require_once __DIR__ . '/../includes/sf_paths.php';
sf_project_include('includes/csrf.php');
sf_project_include('includes/security.php');

if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$erro = '';
$twofa_stage = false;
$email_preenchido = '';

function adm_login_finalize(PDO $pdo, array $usuario): void
{
    $_SESSION['admin_id'] = (int) $usuario['id'];
    $_SESSION['admin_nome'] = $usuario['nome'];
    $_SESSION['admin_email'] = $usuario['email'];
    $_SESSION['empresa_id'] = 1;

    if (is_file(__DIR__ . '/lib/adm_events.php')) {
        require_once __DIR__ . '/lib/adm_events.php';
        adm_event_log($pdo, 'auth', 'login', 'Login ADM: ' . $usuario['email'], 'info');
    }

    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['twofa_code'])) {
    if (empty($_SESSION['adm_2fa_pending']['admin_id'])) {
        $erro = 'Sessão de verificação expirada. Faça login novamente.';
    } else {
        $pendingId = (int) $_SESSION['adm_2fa_pending']['admin_id'];
        $stmt = $pdo->prepare('SELECT * FROM usuarios_adm WHERE id = ? LIMIT 1');
        $stmt->execute([$pendingId]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($usuario && !empty($usuario['two_factor_secret']) && sf_totp_verify((string) $usuario['two_factor_secret'], (string) $_POST['twofa_code'])) {
            unset($_SESSION['adm_2fa_pending']);
            adm_login_finalize($pdo, $usuario);
        } else {
            $erro = 'Código de verificação inválido.';
            $twofa_stage = true;
            $email_preenchido = (string) ($_SESSION['adm_2fa_pending']['email'] ?? '');
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'] ?? '';
    $email_preenchido = (string) $email;

    if (empty($email) || empty($senha)) {
        $erro = 'Por favor, preencha todos os campos.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT * FROM usuarios_adm WHERE email = ?');
            $stmt->execute([$email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                if (!empty($usuario['two_factor_enabled']) && !empty($usuario['two_factor_confirmed_at']) && !empty($usuario['two_factor_secret'])) {
                    $_SESSION['adm_2fa_pending'] = [
                        'admin_id' => (int) $usuario['id'],
                        'email' => (string) $usuario['email'],
                    ];
                    $twofa_stage = true;
                } else {
                    adm_login_finalize($pdo, $usuario);
                }
            } else {
                $erro = 'E-mail ou senha inválidos.';
            }
        } catch (PDOException $e) {
            $erro = 'Erro ao tentar fazer login. Por favor, tente novamente.';
            error_log('Erro de login ADM: ' . $e->getMessage());
        }
    }
}

$asset_v = function_exists('sf_asset_v') ? sf_asset_v() : '1';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrativo — Frotec</title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars(sf_app_url('logo.png')); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('css/login-frotec.css?v=' . $asset_v)); ?>">
</head>
<body class="login-page">
    <div class="login-page__bg" aria-hidden="true"></div>
    <main class="login-page__main">
        <a href="<?php echo htmlspecialchars(sf_app_url('login.php')); ?>" class="login-page__back">
            <i class="fas fa-arrow-left" aria-hidden="true"></i> Login do sistema
        </a>

        <div class="login-card">
            <div class="login-brand">
                <div class="login-brand__logo-wrap">
                    <img src="<?php echo htmlspecialchars(sf_app_url('logo.png')); ?>" alt="" class="login-brand__logo" width="90" height="90" decoding="async">
                </div>
                <h1 class="login-brand__name">Frotec ADM</h1>
                <p class="login-brand__tagline">Painel administrativo — acesso restrito</p>
            </div>

            <?php if ($erro !== ''): ?>
            <div class="login-alert login-alert--error" role="alert"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($twofa_stage): ?>
            <form id="login-form" class="login-form" method="post" action="">
                <p style="font-size:0.9rem;color:#94a3b8;margin:0 0 1rem">Digite o código do aplicativo autenticador.</p>
                <div class="login-field">
                    <label for="twofa_code">Código 2FA</label>
                    <input type="text" id="twofa_code" name="twofa_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" required autofocus>
                </div>
                <button type="submit" class="login-submit">Verificar</button>
            </form>
            <?php else: ?>
            <form id="login-form" class="login-form" method="post" action="" autocomplete="on">
                <div class="login-field">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" autocomplete="username" placeholder="admin@frotec.com.br" required
                        value="<?php echo htmlspecialchars($email_preenchido, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="login-field">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" autocomplete="current-password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="login-submit">Entrar</button>
            </form>
            <?php endif; ?>
        </div>

        <p class="login-fine">Gestão de clientes, planos e configurações globais do Frotec.</p>
    </main>
    <script>
    document.querySelector('.login-form')?.addEventListener('submit', function () {
        var btn = this.querySelector('button[type="submit"]');
        if (btn) { btn.disabled = true; btn.textContent = 'Aguarde…'; }
    });
    </script>
</body>
</html>
