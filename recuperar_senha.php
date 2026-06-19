<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/csrf.php';

configure_session();
session_start();

$page_title = 'Recuperar senha';
$message = '';
$error = '';
$recovery_url = '';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_token_validate($_POST['csrf_token'] ?? null)) {
        $error = 'Sessão expirada ou token inválido. Recarregue a página e tente novamente.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));

        if ($email === '') {
            $error = 'Por favor, digite seu e-mail.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Por favor, digite um e-mail válido.';
        } else {
            try {
                $conn = getConnection();

                $sql = 'SELECT id, nome, email FROM usuarios WHERE email = :email AND status = \'ativo\'';
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':email', $email, PDO::PARAM_STR);

                if ($stmt->execute() && $stmt->rowCount() === 1) {
                    $user = $stmt->fetch();

                    $reset_token = bin2hex(random_bytes(32));
                    $reset_expires = date('Y-m-d H:i:s', time() + 3600);

                    $sql_update = 'UPDATE usuarios SET reset_token = :token, reset_expires = :expires WHERE id = :id';
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bindParam(':token', $reset_token, PDO::PARAM_STR);
                    $stmt_update->bindParam(':expires', $reset_expires, PDO::PARAM_STR);
                    $stmt_update->bindParam(':id', $user['id'], PDO::PARAM_INT);

                    if ($stmt_update->execute()) {
                        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                            || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443')
                            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
                        $scheme = $https ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
                        $pathPrefix = $basePath !== '' ? $basePath : '';
                        $reset_link = $scheme . '://' . $host . $pathPrefix . '/reset_senha.php?token=' . rawurlencode($reset_token);

                        $message = 'ok';
                        $recovery_url = $reset_link;
                    } else {
                        $error = 'Erro ao gerar token de recuperação. Tente novamente.';
                    }
                } else {
                    $error = 'E-mail não encontrado ou usuário inativo.';
                }
            } catch (PDOException $e) {
                $error = 'Erro: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?> — Frotec</title>
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

    <a href="#recover-form" class="login-skip visually-hidden">Ir para o formulário</a>

    <main class="login-page__main login-page__main--recover">
        <a href="login.php" class="login-page__back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Voltar ao login</a>

        <div class="login-card">
            <div class="login-brand">
                <div class="login-brand__logo-wrap">
                    <img src="logo.png" alt="" class="login-brand__logo" width="90" height="90" decoding="async">
                </div>
                <h1 class="login-brand__name">Recuperar senha</h1>
                <p class="login-brand__tagline">Informe o e-mail da sua conta. Se existir cadastro ativo, enviaremos um link para redefinir a senha.</p>
            </div>

            <?php if ($message === 'ok' && $recovery_url !== ''): ?>
                <div class="login-alert login-alert--success login-alert--rich" role="status">
                    <p>Link de recuperação gerado. Abra a página abaixo para definir uma nova senha.</p>
                    <p><a href="<?php echo htmlspecialchars($recovery_url, ENT_QUOTES, 'UTF-8'); ?>">Abrir página de nova senha</a></p>
                    <p class="login-recover-hint">O link expira em 1 hora. Em produção, o mesmo endereço pode ser enviado automaticamente por e-mail.</p>
                    <p class="login-recover-hint"><a href="recuperar_senha.php">Enviar link para outro e-mail</a></p>
                </div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="login-alert login-alert--error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($message !== 'ok'): ?>
            <form id="recover-form" class="login-form" method="post" action="" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token_get(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="login-field">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" autocomplete="email" inputmode="email" placeholder="nome@empresa.com.br" required>
                </div>
                <button type="submit" class="login-submit"><i class="fas fa-paper-plane" aria-hidden="true"></i> Enviar link</button>
            </form>
            <?php endif; ?>
        </div>

        <p class="login-fine">Precisa de ajuda? Entre em contato com o administrador da sua empresa.</p>
    </main>
    <script>
        document.querySelector('.login-form')?.addEventListener('submit', function () {
            var btn = this.querySelector('button[type="submit"]');
            if (!btn) return;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Enviando…';
        });
    </script>
</body>
</html>
