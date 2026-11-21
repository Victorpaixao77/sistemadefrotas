<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/adm_api.php';

$pdo = adm_api_boot();
$cutoff = date('Y-m-d H:i:s', time() - 300);

$stmt = $pdo->prepare("
    SELECT s.*,
           u.nome AS usuario_nome,
           ua.nome AS admin_nome,
           ec.razao_social AS empresa_nome,
           ea.razao_social AS adm_nome
    FROM adm_sessoes_ativas s
    LEFT JOIN usuarios u ON u.id = s.usuario_id
    LEFT JOIN usuarios_adm ua ON ua.id = s.admin_id
    LEFT JOIN empresa_clientes ec ON ec.id = s.empresa_cliente_id
    LEFT JOIN empresa_adm ea ON ea.id = s.empresa_adm_id
    WHERE s.ultimo_ping >= ?
    ORDER BY s.ultimo_ping DESC
    LIMIT 200
");
$stmt->execute([$cutoff]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sessions = [];
foreach ($rows as $r) {
    $r['tempo_online_seg'] = max(0, time() - strtotime((string) $r['iniciado_em']));
    if (!empty($r['admin_id'])) {
        $r['tipo'] = 'admin';
        $r['usuario_label'] = ($r['admin_nome'] ?? 'Admin') . ' (ADM)';
        $r['empresa_label'] = 'Painel administrativo';
    } else {
        $r['tipo'] = 'usuario';
        $r['usuario_label'] = $r['usuario_nome'] ?? '—';
        $r['empresa_label'] = $r['empresa_nome'] ?: ($r['adm_nome'] ?? '—');
    }
    $sessions[] = $r;
}

$recentLogins = [];
try {
    $st = $pdo->query("
        SELECT l.data_acesso AS ultimo_ping, l.ip_address, l.user_agent, l.tipo_acesso,
               u.nome AS usuario_nome, ec.razao_social AS empresa_nome
        FROM log_acessos l
        LEFT JOIN usuarios u ON u.id = l.usuario_id
        LEFT JOIN empresa_clientes ec ON ec.id = l.empresa_id
        WHERE l.data_acesso >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
          AND l.status = 'sucesso'
        ORDER BY l.data_acesso DESC
        LIMIT 40
    ");
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $recentLogins[] = [
            'tipo' => 'historico',
            'usuario_label' => $row['usuario_nome'] ?? '—',
            'empresa_label' => $row['empresa_nome'] ?? '—',
            'pagina' => '[' . $row['tipo_acesso'] . ']',
            'tempo_online_seg' => max(0, time() - strtotime((string) $row['ultimo_ping'])),
            'ip_address' => $row['ip_address'],
            'user_agent' => $row['user_agent'],
            'ultimo_ping' => $row['ultimo_ping'],
        ];
    }
} catch (Throwable $e) {
}

adm_api_ok([
    'sessions' => $sessions,
    'recent_logins' => $recentLogins,
    'total_online' => count($sessions),
    'total_recent' => count($recentLogins),
]);
