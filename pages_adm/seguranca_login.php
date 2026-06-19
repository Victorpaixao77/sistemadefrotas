<?php
require_once '../includes/conexao.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../includes/db_connect.php';
require_once '../includes/security.php';

$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = sf_sistema_login_config_save([
        'recaptcha_enabled' => isset($_POST['recaptcha_enabled']),
        'recaptcha_site_key' => trim((string) ($_POST['recaptcha_site_key'] ?? '')),
        'recaptcha_secret_key' => trim((string) ($_POST['recaptcha_secret_key'] ?? '')),
        'recaptcha_apos_tentativas' => (int) ($_POST['recaptcha_apos_tentativas'] ?? 3),
    ]);
    if ($ok) {
        $mensagem = 'Configurações de login salvas com sucesso.';
        $tipo_mensagem = 'success';
    } else {
        $mensagem = 'Não foi possível salvar. Verifique se a migração sql/sistema_login_config.sql foi aplicada.';
        $tipo_mensagem = 'error';
    }
}

$cfg = sf_sistema_login_config();
$secret_masked = !empty($cfg['recaptcha_secret_key']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login / reCAPTCHA — Painel ADM</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content { margin-left: 250px; padding: 20px; background: #f8f9fa; min-height: 100vh; }
        .adm-card { background: #fff; border-radius: 8px; padding: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.08); max-width: 720px; }
        .adm-card h1 { margin: 0 0 0.35rem; font-size: 1.35rem; color: #2c3e50; }
        .adm-card .lead { color: #5a6268; font-size: 0.92rem; margin: 0 0 1.25rem; line-height: 1.5; }
        .adm-alert { padding: 12px 14px; border-radius: 6px; margin-bottom: 1rem; font-size: 0.9rem; }
        .adm-alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .adm-alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.35rem; color: #333; font-size: 0.9rem; }
        .form-group input[type="text"], .form-group input[type="number"] {
            width: 100%; max-width: 100%; padding: 0.55rem 0.65rem; border: 1px solid #ced4da; border-radius: 6px; font-size: 0.95rem;
        }
        .form-check { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; }
        .form-hint { font-size: 0.82rem; color: #6c757d; margin-top: 0.35rem; line-height: 1.45; }
        .form-hint code { background: #f1f3f5; padding: 0.1em 0.35em; border-radius: 4px; font-size: 0.85em; }
        .form-hint.warn { background: #fff8e6; border: 1px solid #ffe08a; padding: 0.65rem 0.75rem; border-radius: 6px; color: #664d03; }
        .btn-primary { background: #007bff; color: #fff; border: none; padding: 0.55rem 1.1rem; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn-primary:hover { background: #0069d9; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="adm-card">
            <h1><i class="fas fa-shield-halved"></i> Login compartilhado — reCAPTCHA</h1>
            <p class="lead">
                Configuração <strong>global</strong> para <code>login.php</code> (todos os clientes/empresas).
                O widget usa <strong>reCAPTCHA v2 — Caixa de seleção “Não sou um robô”</strong>.
            </p>

            <?php if ($mensagem !== ''): ?>
            <div class="adm-alert <?php echo htmlspecialchars($tipo_mensagem, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <div class="form-check">
                    <input type="checkbox" name="recaptcha_enabled" id="recaptcha_enabled" value="1"
                        <?php echo !empty($cfg['recaptcha_enabled']) ? 'checked' : ''; ?>>
                    <label for="recaptcha_enabled">Ativar reCAPTCHA após tentativas de login falhas</label>
                </div>

                <div class="form-group">
                    <label for="recaptcha_apos_tentativas">Exibir após quantas tentativas falhas (por e-mail + IP)</label>
                    <input type="number" id="recaptcha_apos_tentativas" name="recaptcha_apos_tentativas"
                        min="1" max="10" value="<?php echo (int) ($cfg['recaptcha_apos_tentativas'] ?? 3); ?>">
                </div>

                <div class="form-group">
                    <label for="recaptcha_site_key">Site Key (pública)</label>
                    <input type="text" id="recaptcha_site_key" name="recaptcha_site_key"
                        value="<?php echo htmlspecialchars((string) ($cfg['recaptcha_site_key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="6Lc...">
                </div>

                <div class="form-group">
                    <label for="recaptcha_secret_key">Secret Key</label>
                    <input type="text" id="recaptcha_secret_key" name="recaptcha_secret_key"
                        value=""
                        placeholder="<?php echo $secret_masked ? '•••••••• (deixe em branco para manter)' : '6Lc...'; ?>"
                        autocomplete="new-password">
                </div>

                <div class="form-hint warn">
                    <strong>Erro “tipo de chave é inválido”?</strong> No
                    <a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener">Google reCAPTCHA Admin</a>,
                    crie um site do tipo <strong>reCAPTCHA v2 → “Caixa de seleção ‘Não sou um robô’”</strong> (não use v3 nem “invisível”).
                    Adicione o domínio <code>localhost</code> (desenvolvimento) e o domínio de produção.
                </div>

                <p class="form-hint" style="margin-top:1rem;">
                    Opcional no servidor: variáveis <code>SF_RECAPTCHA_SITE_KEY</code>,
                    <code>SF_RECAPTCHA_SECRET_KEY</code> e <code>SF_RECAPTCHA_APOS_TENTATIVAS</code>
                    (têm prioridade sobre este painel).
                </p>

                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Salvar</button>
            </form>
        </div>
    </div>
</body>
</html>
