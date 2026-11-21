<?php
require_once __DIR__ . '/includes/adm_auth.php';
require_once __DIR__ . '/includes/adm_paths.php';
sf_project_include('includes/sf_paths.php');

$page_title = 'Minha conta';
$adm_asset_v = function_exists('sf_asset_v') ? sf_asset_v() : '1';

$stmt = $pdo->prepare('SELECT two_factor_enabled, two_factor_confirmed_at FROM usuarios_adm WHERE id = ?');
$stmt->execute([(int) $_SESSION['admin_id']]);
$tfa = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

require_once __DIR__ . '/includes/layout_start.php';
$cc_title = 'Segurança da conta';
$cc_subtitle = 'Autenticação em dois fatores (2FA) para o painel ADM';
include __DIR__ . '/components/page_header.php';
?>
<div class="adm-panel">
    <div class="adm-panel__head"><h2><i class="fas fa-shield-halved"></i> Autenticação em dois fatores</h2></div>
    <div class="adm-panel__body">
        <p id="tfa-status" style="margin-bottom:1rem">
            <?php if (!empty($tfa['two_factor_enabled']) && !empty($tfa['two_factor_confirmed_at'])): ?>
            <span class="adm-cc-score adm-cc-score--high">2FA ativo</span>
            <?php else: ?>
            <span class="adm-cc-score adm-cc-score--mid">2FA desativado</span>
            <?php endif; ?>
        </p>
        <div id="tfa-setup" style="display:none;margin-bottom:1rem">
            <p>Escaneie no Google Authenticator / Authy:</p>
            <div id="tfa-qr" style="margin:0 0 12px;display:inline-block;background:#fff;padding:10px;border-radius:8px"></div>
            <p style="font-size:.82rem;color:var(--text-secondary)">Não consegue escanear? Digite esta chave manualmente no app:</p>
            <p><code id="tfa-uri" style="word-break:break-all;font-size:0.8rem"></code></p>
            <p><label>Código de confirmação <input type="text" id="tfa-code" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" style="padding:0.4rem"></label></p>
            <button type="button" class="adm-cc-btn adm-cc-btn--primary" id="tfa-confirm">Confirmar 2FA</button>
        </div>
        <div class="adm-cc-filters adm-cc-filters--flat">
            <?php if (empty($tfa['two_factor_enabled'])): ?>
            <button type="button" class="adm-cc-btn adm-cc-btn--primary" id="tfa-start">Ativar 2FA</button>
            <?php else: ?>
            <button type="button" class="adm-cc-btn" id="tfa-disable">Desativar 2FA</button>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
$adm_extra_scripts = <<<'JS'
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
    function post(action, extra) {
        var fd = new FormData();
        fd.append('action', action);
        if (extra) {
            Object.keys(extra).forEach(function (k) { fd.append(k, extra[k]); });
        }
        return fetch('api/adm_2fa.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': window.__ADM_CSRF__ || '' },
            body: fd,
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); });
    }
    var startBtn = document.getElementById('tfa-start');
    if (startBtn) {
        startBtn.addEventListener('click', function () {
            post('start').then(function (res) {
                if (!res.success) { alert(res.message || res.error || 'Erro'); return; }
                document.getElementById('tfa-setup').style.display = 'block';
                document.getElementById('tfa-uri').textContent = res.secret || res.otpauth || '';
                var qrBox = document.getElementById('tfa-qr');
                qrBox.innerHTML = '';
                if (res.otpauth && typeof QRCode !== 'undefined') {
                    try {
                        new QRCode(qrBox, { text: res.otpauth, width: 190, height: 190, correctLevel: QRCode.CorrectLevel.M });
                    } catch (e) {
                        qrBox.style.display = 'none';
                    }
                } else {
                    qrBox.style.display = 'none';
                }
                document.getElementById('tfa-code').focus();
            });
        });
    }
    var confirmBtn = document.getElementById('tfa-confirm');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
            post('confirm', { code: document.getElementById('tfa-code').value }).then(function (res) {
                if (res.success) location.reload();
                else alert(res.message || res.error || 'Código inválido');
            });
        });
    }
    var disableBtn = document.getElementById('tfa-disable');
    if (disableBtn) {
        disableBtn.addEventListener('click', function () {
            if (!confirm('Desativar 2FA nesta conta?')) return;
            post('disable').then(function (res) {
                if (res.success) location.reload();
            });
        });
    }
})();
</script>
JS;
require_once __DIR__ . '/includes/layout_end.php';
