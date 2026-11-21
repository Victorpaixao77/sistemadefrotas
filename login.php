<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/csrf.php';
require_once 'includes/remember_token.php';
require_once 'includes/security.php';
require_once 'includes/notificacoes_helpers.php';
require_once 'includes/sf_saas.php';

/**
 * Finaliza um login já autenticado (senha + 2FA OK): regenera a sessão,
 * grava variáveis, trata "lembrar-me", detecta novo acesso e redireciona.
 */
function sf_login_finalize(PDO $conn, array $row, bool $remember, string $username, array $cfg): void
{
    // Anti session-fixation
    session_regenerate_id(true);

    $tem_acesso_global = !empty($row['acesso_todas_empresas']) && $row['acesso_todas_empresas'] == 1;
    $_SESSION["acesso_todas_empresas"] = $tem_acesso_global;
    $_SESSION["loggedin"] = true;
    $_SESSION["id"] = $row["id"];
    $_SESSION["usuario_id"] = $row["id"];
    $_SESSION["nome"] = $row["nome"];
    $_SESSION["email"] = $row["email"];
    $_SESSION["foto_perfil"] = $row["foto_perfil"];
    $_SESSION["tipo_usuario"] = $row["tipo_usuario"];
    $_SESSION["ultima_atividade"] = time();

    // "Lembrar-me" (token seguro)
    if ($remember) {
        sf_remember_issue($conn, (int) $row["id"]);
        setcookie("saved_login", base64_encode(json_encode(['username' => $username])), time() + (30 * 24 * 60 * 60), "/", "", false, true);
    } else {
        sf_remember_clear($conn);
        setcookie("saved_login", "", time() - 3600, "/", "", false, true);
    }

    // Detecção de novo acesso + e-mail de alerta
    $ip = sf_client_ip();
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $geo = !empty($cfg['novo_acesso_email']) ? sf_geo_lookup($ip) : [];
    $conhecido = sf_acesso_e_conhecido((int) $row['id'], $ip, $ua, $geo);
    if (!$conhecido) {
        if (!empty($cfg['novo_acesso_email']) && !empty($row['email'])) {
            sf_enviar_email_novo_acesso($cfg, $row['email'], (string) $row['nome'], $ip, $geo);
        }
        $empresaNotif = (int) ($row['empresa_id'] ?? 0);
        if ($empresaNotif > 0) {
            sf_notif_registrar_novo_acesso($conn, $empresaNotif, (int) $row['id'], (string) $row['nome'], $ip, $geo);
        } elseif ($tem_acesso_global) {
            sf_notif_guardar_novo_acesso_sessao((string) $row['nome'], $ip, $geo);
        }
    }

    if ($tem_acesso_global) {
        registrarLogAcesso($row["id"], null, 'login', 'sucesso', 'Login realizado - aguardando seleção de empresa');
        header("location: selecionar_empresa.php");
        exit;
    }

    $saasCheck = sf_saas_gate_empresa_cliente($conn, (int) $row['empresa_id'], false);
    if (!$saasCheck['ok']) {
        $_SESSION['login_error'] = $saasCheck['message'] ?? 'Acesso indisponível para esta empresa.';
        header('location: login.php');
        exit;
    }

    $_SESSION["empresa_id"] = $row["empresa_id"];
    registrarLogAcesso($row["id"], $row["empresa_id"], 'login', 'sucesso', 'Login realizado com sucesso');
    // Sempre iniciar na tela inicial com o menu lateral recolhido.
    unset($_SESSION['redirect_url']);
    $_SESSION['sf_sidebar_reset'] = 1;
    header("location: index.php");
    exit;
}

// Configure session before starting it
configure_session();

// Start session
session_start();

// If user is already logged in and has empresa_id, redirect to index
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && isset($_SESSION['empresa_id'])) {
    header("location: index.php");
    exit;
}

// Check for "Remember Me" cookie (only if not coming from logout).
// Usa token seguro (selector:validator) — ver includes/remember_token.php.
if (isset($_COOKIE['remember_user']) && !isset($_SESSION['loggedin']) && !isset($_GET['logout'])) {
    try {
        $conn = getConnection();
        $remember_user_id = sf_remember_validate($conn);

        if ($remember_user_id !== null) {
            $sql = "SELECT u.id, u.nome, u.email, u.empresa_id, u.status as usuario_status, u.foto_perfil, u.tipo_usuario,
                           u.acesso_todas_empresas
                   FROM usuarios u
                   LEFT JOIN empresa_clientes e ON u.empresa_id = e.id
                   WHERE u.id = :user_id AND u.status = 'ativo'";

            $stmt = $conn->prepare($sql);
            $stmt->bindParam(":user_id", $remember_user_id, PDO::PARAM_INT);

            if ($stmt->execute() && $stmt->rowCount() == 1) {
                $row = $stmt->fetch();

                // Verificar se tem acesso a todas as empresas
                $tem_acesso_global = !empty($row['acesso_todas_empresas']) && $row['acesso_todas_empresas'] == 1;
                $_SESSION["acesso_todas_empresas"] = $tem_acesso_global;

                // Store data in session variables
                $_SESSION["loggedin"] = true;
                $_SESSION["id"] = $row["id"];
                $_SESSION["usuario_id"] = $row["id"];
                $_SESSION["nome"] = $row["nome"];
                $_SESSION["email"] = $row["email"];
                $_SESSION["foto_perfil"] = $row["foto_perfil"];
                $_SESSION["tipo_usuario"] = $row["tipo_usuario"];

                // Se tem acesso global, redirecionar para seleção de empresa
                if ($tem_acesso_global) {
                    registrarLogAcesso($row["id"], null, 'login', 'sucesso', 'Login automático via cookie - aguardando seleção de empresa');
                    header("location: selecionar_empresa.php");
                } else {
                    $empresa_id_login = $row["empresa_id"];
                    $_SESSION["empresa_id"] = $empresa_id_login;
                    registrarLogAcesso($row["id"], $empresa_id_login, 'login', 'sucesso', 'Login automático via cookie Remember Me');
                    $_SESSION['sf_sidebar_reset'] = 1;
                    header("location: index.php");
                }
                exit;
            }

            // Usuário sumiu/inativo — revoga o token.
            sf_remember_clear($conn);
        }
    } catch (Exception $e) {
        error_log('Remember-me auto-login: ' . $e->getMessage());
    }
}

// Limpar sessão “órfã” só no GET: no POST preservamos a sessão para validar o CSRF do formulário.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_SESSION['empresa_id'])) {
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

// Sessão encerrada por inatividade
if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $error = 'Sua sessão foi encerrada por inatividade. Faça login novamente.';
}

if (!empty($_SESSION['login_error'])) {
    $error = (string) $_SESSION['login_error'];
    unset($_SESSION['login_error']);
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

$client_ip = sf_client_ip();

// ETAPA 2FA: usuário já passou senha e está confirmando o código de 6 dígitos.
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['twofa_code'])) {
    try {
        if (!csrf_token_validate($_POST['csrf_token'] ?? null)) {
            $error = 'Sessão expirada. Recarregue a página e tente novamente.';
        } elseif (empty($_SESSION['2fa_pending']['usuario_id'])) {
            $error = 'Sessão de verificação expirada. Faça login novamente.';
        } else {
            $conn = getConnection();
            $pending = $_SESSION['2fa_pending'];
            $stmt = $conn->prepare("SELECT u.id, u.nome, u.email, u.empresa_id, u.foto_perfil, u.tipo_usuario,
                                           u.acesso_todas_empresas, u.two_factor_secret
                                    FROM usuarios u WHERE u.id = :id AND u.status = 'ativo' LIMIT 1");
            $stmt->execute([':id' => $pending['usuario_id']]);
            $row = $stmt->fetch();

            if ($row && sf_totp_verify((string) $row['two_factor_secret'], (string) $_POST['twofa_code'])) {
                $cfg = sf_security_config(!empty($row['empresa_id']) ? (int) $row['empresa_id'] : null);
                $remember = !empty($pending['remember']);
                $username = (string) ($pending['username'] ?? '');
                unset($_SESSION['2fa_pending']);
                sf_login_attempt_reset((string) $row['email'], $client_ip);
                sf_login_finalize($conn, $row, $remember, $username, $cfg);
            } else {
                registrarLogAcesso($pending['usuario_id'], 0, 'tentativa_login_falha', 'falha', '2FA: código inválido');
                $error = 'Código de verificação inválido.';
                $twofa_stage = true;
            }
        }
    } catch (Throwable $e) {
        error_log('Login 2FA: ' . $e->getMessage());
        $error = 'Não foi possível validar o código. Tente novamente.';
        $twofa_stage = true;
    }
}
// ETAPA CREDENCIAIS: e-mail + senha.
elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"] ?? '');
    $password = trim($_POST["password"] ?? '');
    $remember = isset($_POST["remember"]) ? true : false;

    $_SESSION['login_last_username'] = $username;

    // Bloqueio persistente por e-mail + IP.
    $attempt = sf_login_attempt_status($username, $client_ip);

    $recaptcha_cfg = sf_login_recaptcha_config();
    $recaptcha_must = $recaptcha_cfg['active']
        && $attempt['tentativas'] >= $recaptcha_cfg['threshold'];

    if ($attempt['bloqueado']) {
        $mins = (int) ceil($attempt['segundos_restantes'] / 60);
        $error = "Muitas tentativas de login. Tente novamente em {$mins} minuto(s).";
    } elseif (!csrf_token_validate($_POST['csrf_token'] ?? null)) {
        $error = 'Sessão expirada ou token inválido. Recarregue a página e tente novamente.';
    } elseif ($recaptcha_must && !sf_recaptcha_verify($recaptcha_cfg['secret_key'], (string) ($_POST['g-recaptcha-response'] ?? ''), $client_ip)) {
        $error = 'Confirme que você não é um robô.';
    } else {
        try {
            $conn = getConnection();
            if (sf_platform_maintenance_mode($conn)) {
                $error = 'Plataforma em manutenção programada. Tente novamente em alguns minutos.';
            } else {
            $sql = "SELECT u.id, u.nome, u.email, u.senha, u.empresa_id, u.status as usuario_status, u.foto_perfil, u.tipo_usuario,
                           u.acesso_todas_empresas, u.is_oculto, u.two_factor_enabled, u.two_factor_secret, u.two_factor_confirmed_at
                   FROM usuarios u
                   LEFT JOIN empresa_clientes e ON u.empresa_id = e.id
                   WHERE u.email = :username";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(":username", $username, PDO::PARAM_STR);
            $stmt->execute();

            if ($stmt->rowCount() == 1) {
                $row = $stmt->fetch();
                $cfg = sf_security_config(!empty($row['empresa_id']) ? (int) $row['empresa_id'] : null);

                if ($row['usuario_status'] !== 'ativo') {
                    $error = "Usuário inativo. Entre em contato com o suporte.";
                } elseif (!password_verify($password, $row['senha'])) {
                    $_SESSION['login_fail_count'] = (int) ($_SESSION['login_fail_count'] ?? 0) + 1;
                    sf_login_attempt_fail($username, $client_ip, (int) $cfg['login_max_tentativas'], (int) $cfg['login_bloqueio_min']);
                    registrarLogAcesso($row["id"], $row["empresa_id"], 'tentativa_login_falha', 'falha', 'Senha inválida');
                    $error = "Senha inválida.";
                }
                // Geo-bloqueio
                elseif (!empty($cfg['geo_block_enabled']) && !sf_geo_allowed($client_ip, (string) $cfg['geo_paises_permitidos'])) {
                    registrarLogAcesso($row["id"], $row["empresa_id"], 'tentativa_login_falha', 'falha', 'Bloqueio geográfico (IP ' . $client_ip . ')');
                    $error = "Acesso bloqueado para a sua região.";
                }
                // Whitelist de IP para administradores
                elseif (!empty($cfg['ip_whitelist_enabled']) && in_array($row['tipo_usuario'], ['admin'], true) && !sf_admin_ip_allowed((int) $row['empresa_id'], $client_ip)) {
                    registrarLogAcesso($row["id"], $row["empresa_id"], 'tentativa_login_falha', 'falha', 'IP não autorizado para admin (' . $client_ip . ')');
                    $error = "Seu IP não está autorizado a acessar o painel administrativo.";
                } else {
                    // Credenciais OK
                    $_SESSION['login_fail_count'] = 0;
                    unset($_SESSION['login_last_username']);
                    sf_login_attempt_reset($username, $client_ip);

                    // 2FA ativo e confirmado → exigir código antes de liberar.
                    if (!empty($row['two_factor_enabled']) && !empty($row['two_factor_confirmed_at']) && !empty($row['two_factor_secret'])) {
                        $_SESSION['2fa_pending'] = [
                            'usuario_id' => (int) $row['id'],
                            'remember' => $remember,
                            'username' => $username,
                        ];
                        $twofa_stage = true;
                    } else {
                        sf_login_finalize($conn, $row, $remember, $username, $cfg);
                    }
                }
            } else {
                registrarLogAcesso(0, 0, 'tentativa_login_falha', 'falha', 'Usuário não encontrado: ' . $username);
                $_SESSION['login_fail_count'] = (int) ($_SESSION['login_fail_count'] ?? 0) + 1;
                sf_login_attempt_fail($username, $client_ip, 5, 15);
                $error = "Usuário ou senha inválidos.";
            }
            }
        } catch (PDOException $e) {
            error_log('Login PDOException: ' . $e->getMessage());
            $error = "Não foi possível concluir o login. Tente novamente mais tarde.";
        }
    }
}

// Estado para renderização do formulário.
$twofa_stage = $twofa_stage ?? !empty($_SESSION['2fa_pending']);
$login_email_hint = trim((string) ($_SESSION['login_last_username'] ?? ($_POST['username'] ?? '')));
$recaptcha_cfg = sf_login_recaptcha_config();
$recaptcha_show = sf_login_recaptcha_should_show($login_email_hint !== '' ? $login_email_hint : null, $client_ip);
$recaptcha_site_key = $recaptcha_cfg['site_key'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar — Frotec</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/login-frotec.css?v=<?php echo htmlspecialchars(sf_asset_v(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/png" href="logo.png">
</head>
<body class="login-page">
    <div class="login-page__bg" aria-hidden="true"></div>

    <div class="login-page__quotes" aria-hidden="true">
        <div class="login-bg-phrase login-bg-phrase--1">Simples para quem dirige.<br>Poderoso para quem gerencia.</div>
        <div class="login-bg-phrase login-bg-phrase--2">Chega de planilhas!<br>Gestão de frotas simples, visual e eficiente.</div>
        <div class="login-bg-phrase login-bg-phrase--3">Ideal para pequenas e médias empresas.<br>Organize veículos, motoristas, rotas e despesas sem complicações.</div>
        <div class="login-bg-phrase login-bg-phrase--4">Mais Segurança</div>
        <div class="login-bg-phrase login-bg-phrase--5">Aumente a Eficiência</div>
        <div class="login-bg-phrase login-bg-phrase--6">Reduza Custos</div>
        <div class="login-bg-phrase login-bg-phrase--7">Tempo economizado</div>
        <div class="login-bg-phrase login-bg-phrase--8">Redução de custos</div>
        <div class="login-bg-phrase login-bg-phrase--9">Aumento médio de eficiência</div>
        <div class="login-bg-phrase login-bg-phrase--10">Transforme dados em decisões inteligentes.</div>
        <div class="login-bg-phrase login-bg-phrase--11">Tenha sua frota sob controle em qualquer lugar.</div>
        <div class="login-bg-phrase login-bg-phrase--12">Automatize processos e reduza retrabalho.</div>
        <div class="login-bg-phrase login-bg-phrase--13">Mais tempo para focar no crescimento do seu negócio.</div>
        <div class="login-bg-phrase login-bg-phrase--14">Relatórios claros e completos em poucos cliques.</div>
        <div class="login-bg-phrase login-bg-phrase--15">Do cadastro ao controle financeiro, tudo em um só sistema.</div>
        <div class="login-bg-phrase login-bg-phrase--16">Tecnologia feita para simplificar a gestão de frotas.</div>
        <div class="login-bg-phrase login-bg-phrase--17">Gestão inteligente que cresce junto com a sua empresa.</div>
        <div class="login-bg-phrase login-bg-phrase--18">Monitoramento ágil, decisões rápidas.</div>
    </div>

    <a href="#login-form" class="login-skip visually-hidden">Ir para o formulário</a>

    <main class="login-page__main">
        <a href="<?php echo htmlspecialchars(sf_marketing_site_url(), ENT_QUOTES, 'UTF-8'); ?>" class="login-page__back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Voltar ao site</a>

        <div class="login-card">
            <div class="login-brand">
                <div class="login-brand__logo-wrap">
                    <img src="logo.png" alt="" class="login-brand__logo" width="90" height="90" decoding="async">
                </div>
                <h1 class="login-brand__name">Frotec</h1>
                <p class="login-brand__tagline">Área do cliente — entre com seu e-mail corporativo</p>
            </div>

        <?php if (!empty($error)): ?>
            <div class="login-alert login-alert--error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="login-alert login-alert--success" id="successMessage" role="status"><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (!empty($twofa_stage)): ?>
        <form id="login-form" class="login-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token_get(), ENT_QUOTES, 'UTF-8'); ?>">
            <p class="login-brand__tagline" style="margin-bottom:8px">Digite o código de 6 dígitos do seu aplicativo autenticador.</p>
            <div class="login-field">
                <label for="twofa_code">Código de verificação</label>
                <input type="text" id="twofa_code" name="twofa_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" autofocus required>
            </div>
            <button type="submit" class="login-submit">Verificar</button>
            <div class="login-row" style="justify-content:center;margin-top:10px">
                <a class="login-forgot" href="login.php?logout=1">Cancelar</a>
            </div>
        </form>
        <?php else: ?>
        <form id="login-form" class="login-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token_get(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="login-field">
                <label for="username">E-mail</label>
                <input type="email" id="username" name="username" autocomplete="username" inputmode="email" placeholder="nome@empresa.com.br" value="<?php echo $saved_username; ?>" required>
            </div>
            <div class="login-field">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password" autocomplete="current-password" placeholder="••••••••" required>
            </div>
            <div class="login-row">
                <label class="login-remember"><input type="checkbox" name="remember" id="remember" <?php echo $remember_checked; ?>> Lembrar-me</label>
                <a class="login-forgot" href="recuperar_senha.php">Esqueceu a senha?</a>
            </div>
            <?php if (!empty($recaptcha_show)): ?>
            <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($recaptcha_site_key, ENT_QUOTES, 'UTF-8'); ?>" style="margin:8px 0"></div>
            <script src="https://www.google.com/recaptcha/api.js" async defer></script>
            <p class="login-fine" style="margin:0.35rem 0 0;font-size:0.75rem;">Proteção anti-robô (reCAPTCHA v2).</p>
            <?php endif; ?>
            <button type="submit" class="login-submit">Entrar</button>
        </form>
        <?php endif; ?>
        </div>

        <p class="login-fine">Gestão de frotas em um só lugar. Em caso de dúvida, fale com o suporte da sua empresa.</p>
    </main>
    <script>
        document.querySelector('.login-form')?.addEventListener('submit', function () {
            const submitButton = this.querySelector('button[type="submit"]');
            if (!submitButton) return;
            submitButton.disabled = true;
            submitButton.textContent = 'Entrando…';
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