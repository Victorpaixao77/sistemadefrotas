<?php
require_once __DIR__ . '/includes/adm_auth.php';
require_once __DIR__ . '/includes/adm_post_guard.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/security.php';

$page_title = 'Login / reCAPTCHA';

$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!adm_csrf_valid_post()) {
        $mensagem = 'Token CSRF inválido. Recarregue a página e tente novamente.';
        $tipo_mensagem = 'error';
    } else {
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
}

$cfg = sf_sistema_login_config();
$secret_masked = !empty($cfg['recaptcha_secret_key']);

require_once __DIR__ . '/includes/layout_start.php';
?>

<div class="adm-form-card">
    <h2><i class="fas fa-shield-halved"></i> Login compartilhado — reCAPTCHA</h2>
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
        <?php echo adm_csrf_field(); ?>
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
            crie um site do tipo <strong>reCAPTCHA v2 → “Caixa de seleção ‘Não sou um robô’”</strong>.
            Adicione o domínio <code>localhost</code> (desenvolvimento) e o domínio de produção.
        </div>

        <p class="form-hint" style="margin-top:1rem;">
            Opcional no servidor: variáveis <code>SF_RECAPTCHA_SITE_KEY</code>,
            <code>SF_RECAPTCHA_SECRET_KEY</code> e <code>SF_RECAPTCHA_APOS_TENTATIVAS</code>
            (têm prioridade sobre este painel).
        </p>

        <button type="submit" class="fornc-btn fornc-btn--primary" style="margin-top:0.75rem">
            <i class="fas fa-save"></i> Salvar
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
