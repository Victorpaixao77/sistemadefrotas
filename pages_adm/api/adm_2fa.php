<?php
/**
 * 2FA TOTP para administradores (usuarios_adm).
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/conexao.php';
require_once __DIR__ . '/../includes/adm_session.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/api_json.php';
require_once __DIR__ . '/../../includes/security.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    api_json_unauthorized();
}

api_require_csrf_json();

$adminId = (int) $_SESSION['admin_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'start':
            $secret = sf_totp_generate_secret();
            $stmt = $pdo->prepare('UPDATE usuarios_adm SET two_factor_secret = ?, two_factor_enabled = 0, two_factor_confirmed_at = NULL WHERE id = ?');
            $stmt->execute([$secret, $adminId]);
            $email = (string) ($_SESSION['admin_email'] ?? ('admin' . $adminId));
            echo json_encode([
                'success' => true,
                'secret' => $secret,
                'otpauth' => sf_totp_uri($secret, $email, 'Frotec ADM'),
            ]);
            break;

        case 'confirm':
            $code = (string) ($_POST['code'] ?? '');
            $stmt = $pdo->prepare('SELECT two_factor_secret FROM usuarios_adm WHERE id = ? LIMIT 1');
            $stmt->execute([$adminId]);
            $secret = (string) $stmt->fetchColumn();
            if ($secret === '') {
                api_json_error('Inicie a configuração do 2FA novamente.', 422);
            }
            if (!sf_totp_verify($secret, $code)) {
                api_json_error('Código inválido.', 422);
            }
            $upd = $pdo->prepare('UPDATE usuarios_adm SET two_factor_enabled = 1, two_factor_confirmed_at = NOW() WHERE id = ?');
            $upd->execute([$adminId]);
            echo json_encode(['success' => true]);
            break;

        case 'disable':
            $stmt = $pdo->prepare('UPDATE usuarios_adm SET two_factor_enabled = 0, two_factor_secret = NULL, two_factor_confirmed_at = NULL WHERE id = ?');
            $stmt->execute([$adminId]);
            echo json_encode(['success' => true]);
            break;

        case 'status':
            $stmt = $pdo->prepare('SELECT two_factor_enabled, two_factor_confirmed_at IS NOT NULL AS confirmed FROM usuarios_adm WHERE id = ?');
            $stmt->execute([$adminId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            echo json_encode(['success' => true, 'data' => $row]);
            break;

        default:
            api_json_error('Ação inválida.', 400);
    }
} catch (Throwable $e) {
    error_log('pages_adm/api/adm_2fa.php: ' . $e->getMessage());
    api_json_error('Erro ao processar 2FA.', 500);
}
