<?php
declare(strict_types=1);

function adm_event_log(
    PDO $pdo,
    string $modulo,
    string $tipo,
    string $mensagem,
    string $severidade = 'info',
    ?int $empresaClienteId = null,
    ?int $empresaAdmId = null,
    ?int $usuarioId = null,
    ?array $meta = null
): void {
    try {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if (is_string($ip) && strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500) : null;
        $adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;

        $stmt = $pdo->prepare('INSERT INTO adm_eventos
            (empresa_cliente_id, empresa_adm_id, usuario_id, admin_id, modulo, tipo, severidade, mensagem, meta, ip_address, user_agent)
            VALUES (:ec, :ea, :uid, :aid, :mod, :tipo, :sev, :msg, :meta, :ip, :ua)');
        $stmt->execute([
            ':ec' => $empresaClienteId,
            ':ea' => $empresaAdmId,
            ':uid' => $usuarioId,
            ':aid' => $adminId,
            ':mod' => substr($modulo, 0, 64),
            ':tipo' => substr($tipo, 0, 64),
            ':sev' => in_array($severidade, ['info', 'aviso', 'erro', 'critico'], true) ? $severidade : 'info',
            ':msg' => substr($mensagem, 0, 500),
            ':meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            ':ip' => $ip,
            ':ua' => $ua,
        ]);
    } catch (Throwable $e) {
        error_log('adm_event_log: ' . $e->getMessage());
    }
}
