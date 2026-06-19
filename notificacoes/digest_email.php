<?php
/**
 * Digest diário por e-mail (cron).
 * 0 8 * * * php /caminho/notificacoes/digest_email.php
 */
require_once dirname(__DIR__) . '/includes/db_connect.php';
require_once dirname(__DIR__) . '/includes/notificacoes_helpers.php';
require_once dirname(__DIR__) . '/includes/security.php';

require_once dirname(__DIR__) . '/includes/security.php';

$hoje = date('Y-m-d');
$horaAtual = date('H:i:00');

try {
    $conn = getConnection();
    sf_notif_ensure_schema($conn);

    $st = $conn->prepare("
        SELECT p.usuario_id, p.empresa_id, u.email, u.nome
        FROM notificacoes_preferencias p
        JOIN usuarios u ON u.id = p.usuario_id
        WHERE p.email_digest = 1 AND p.email_digest_hora <= :hora
    ");
    $st->execute([':hora' => $horaAtual]);
    $enviados = 0;

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $uid = (int) $row['usuario_id'];
        $eid = (int) $row['empresa_id'];
        $email = trim((string) ($row['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        $chk = $conn->prepare('SELECT id FROM notificacoes_digest_envio WHERE usuario_id = :u AND empresa_id = :e AND data_ref = :d');
        $chk->execute([':u' => $uid, ':e' => $eid, ':d' => $hoje]);
        if ($chk->fetch()) {
            continue;
        }

        $op = sf_notif_contar_nao_lidas_usuario($conn, $eid, $uid, 'operacional');
        $ia = sf_notif_contar_nao_lidas_usuario($conn, $eid, $uid, 'ia');
        if ($op + $ia < 1) {
            continue;
        }

        $nome = (string) ($row['nome'] ?? 'Gestor');
        $url = sf_app_url('pages/notificacoes.php');
        $body = "Olá {$nome},\n\nResumo Frotec ({$hoje}):\n";
        $body .= "- Operacionais: {$op}\n- Insights IA: {$ia}\n\n";
        $body .= "Acesse: {$url}\n";

        $cfg = function_exists('sf_security_config') ? sf_security_config($eid) : [];
        $bodyHtml = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
        if (!function_exists('sf_smtp_send') || empty($cfg) || !sf_smtp_send($cfg, $email, 'Resumo de notificações — Frotec', $bodyHtml)) {
            continue;
        }

        $ins = $conn->prepare('INSERT INTO notificacoes_digest_envio (usuario_id, empresa_id, data_ref) VALUES (:u, :e, :d)');
        $ins->execute([':u' => $uid, ':e' => $eid, ':d' => $hoje]);
        $enviados++;
    }

    echo "Digest enviados: {$enviados}\n";
} catch (Throwable $e) {
    error_log('digest_email: ' . $e->getMessage());
    exit(1);
}
