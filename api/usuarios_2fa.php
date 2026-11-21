<?php
/**
 * 2FA (TOTP) por usuário.
 *
 * Ações:
 *  - start   (POST): gera segredo para o PRÓPRIO usuário e devolve otpauth URI p/ QR.
 *  - confirm (POST): valida o código de 6 dígitos e ativa o 2FA (próprio usuário).
 *  - disable (POST): desativa o 2FA do próprio usuário, ou de outro (se admin).
 *  - status  (GET) : lista status 2FA dos usuários da empresa.
 *
 * Regra: configurar/confirmar só é permitido para a própria conta (apenas o dono
 * consegue escanear o QR). Administradores podem desativar o 2FA de terceiros.
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
require_once '../includes/api_json.php';
require_once '../includes/security.php';

configure_session();
session_start();

header('Content-Type: application/json');
require_authentication();
if (function_exists('session_write_close')) { session_write_close(); }
api_require_csrf_json();

$empresa_id = (int) $_SESSION['empresa_id'];
$meu_id = (int) ($_SESSION['usuario_id'] ?? 0);
$conn = getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function sf_2fa_is_admin(PDO $conn, int $userId, int $empresaId): bool
{
    $stmt = $conn->prepare('SELECT (is_admin = 1 OR pode_editar_usuarios_sistema = 1) AS adm FROM usuarios WHERE id = :id AND empresa_id = :e LIMIT 1');
    $stmt->execute([':id' => $userId, ':e' => $empresaId]);
    return (bool) $stmt->fetchColumn();
}

try {
    switch ($action) {
        case 'start':
            // Sempre para a própria conta.
            $secret = sf_totp_generate_secret();
            $stmt = $conn->prepare('UPDATE usuarios SET two_factor_secret = :s, two_factor_enabled = 0, two_factor_confirmed_at = NULL WHERE id = :id AND empresa_id = :e');
            $stmt->execute([':s' => $secret, ':id' => $meu_id, ':e' => $empresa_id]);

            $email = (string) ($_SESSION['email'] ?? ('usuario' . $meu_id));
            echo json_encode([
                'success' => true,
                'secret' => $secret,
                'otpauth' => sf_totp_uri($secret, $email, 'Frotec'),
            ]);
            break;

        case 'confirm':
            $code = (string) ($_POST['code'] ?? '');
            $stmt = $conn->prepare('SELECT two_factor_secret FROM usuarios WHERE id = :id AND empresa_id = :e LIMIT 1');
            $stmt->execute([':id' => $meu_id, ':e' => $empresa_id]);
            $secret = (string) $stmt->fetchColumn();

            if ($secret === '') {
                api_json_error('Inicie a configuração do 2FA novamente.', 422);
            }
            if (!sf_totp_verify($secret, $code)) {
                api_json_error('Código inválido. Verifique o app autenticador.', 422);
            }
            $upd = $conn->prepare('UPDATE usuarios SET two_factor_enabled = 1, two_factor_confirmed_at = NOW() WHERE id = :id AND empresa_id = :e');
            $upd->execute([':id' => $meu_id, ':e' => $empresa_id]);
            registrarLogAcesso($meu_id, $empresa_id, 'login', 'sucesso', '2FA ativado');
            echo json_encode(['success' => true]);
            break;

        case 'disable':
            $alvo = (int) ($_POST['usuario_id'] ?? $meu_id);
            if ($alvo !== $meu_id && !sf_2fa_is_admin($conn, $meu_id, $empresa_id)) {
                api_json_error('Sem permissão para desativar o 2FA de outro usuário.', 403);
            }
            $stmt = $conn->prepare('UPDATE usuarios SET two_factor_enabled = 0, two_factor_secret = NULL, two_factor_confirmed_at = NULL WHERE id = :id AND empresa_id = :e');
            $stmt->execute([':id' => $alvo, ':e' => $empresa_id]);
            registrarLogAcesso($meu_id, $empresa_id, 'login', 'sucesso', '2FA desativado para usuário #' . $alvo);
            echo json_encode(['success' => true]);
            break;

        case 'status':
            $stmt = $conn->prepare('SELECT id, two_factor_enabled FROM usuarios WHERE empresa_id = :e');
            $stmt->execute([':e' => $empresa_id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default:
            api_json_error('Ação inválida.', 400);
    }
} catch (Throwable $e) {
    error_log('api/usuarios_2fa.php: ' . $e->getMessage());
    api_json_error('Erro ao processar 2FA.', 500);
}
