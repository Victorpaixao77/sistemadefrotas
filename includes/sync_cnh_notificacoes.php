<?php
require_once __DIR__ . '/sf_paths.php';
/**
 * Sincroniza alertas de CNH vencendo (30/60/90 dias) para o sino de notificações do header.
 * Não acesse este arquivo diretamente pela URL.
 * Ele é incluído por: pages/motorists.php ou api/notifications.php (ao buscar notificações).
 */
if (!isset($conn)) {
    header('Content-Type: text/plain; charset=utf-8');
    die('Este arquivo não deve ser acessado diretamente. Use a página Motoristas ou o sistema de notificações.');
}
if (!isset($_SESSION['empresa_id']) || !isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    return;
}
$user_id = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? $_SESSION['usuario_id'] ?? 0);
if ($user_id <= 0) {
    return;
}
$empresa_id = (int)$_SESSION['empresa_id'];

try {
    $conn->query("SELECT 1 FROM notifications LIMIT 1");
} catch (Exception $e) {
    return;
}

$hoje = date('Y-m-d');
$sql = "SELECT id, nome, data_validade_cnh, cnh,
        DATEDIFF(data_validade_cnh, CURRENT_DATE) as dias
        FROM motoristas
        WHERE empresa_id = :eid AND data_validade_cnh IS NOT NULL
        AND data_validade_cnh >= CURRENT_DATE
        AND DATEDIFF(data_validade_cnh, CURRENT_DATE) <= 90
        ORDER BY data_validade_cnh ASC";
try {
$stmt = $conn->prepare($sql);
$stmt->execute(['eid' => $empresa_id]);
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($lista)) {
    return;
}

$em_30 = []; $em_60 = []; $em_90 = [];
foreach ($lista as $r) {
    $d = (int)$r['dias'];
    $nome = $r['nome'] . ' (' . $d . ' dias)';
    if ($d <= 30) $em_30[] = $nome;
    elseif ($d <= 60) $em_60[] = $nome;
    else $em_90[] = $nome;
}

$msg = [];
if (!empty($em_30)) $msg[] = 'Até 30 dias: ' . implode(', ', array_slice($em_30, 0, 5));
if (!empty($em_60)) $msg[] = '31–60 dias: ' . implode(', ', array_slice($em_60, 0, 3));
if (!empty($em_90)) $msg[] = '61–90 dias: ' . implode(', ', array_slice($em_90, 0, 3));
$message = implode("\n", $msg);
$title = 'CNH vencendo';
$type = count($em_30) > 0 ? 'warning' : 'info';

$stmt = $conn->prepare("SELECT id FROM notifications WHERE user_id = :uid AND title = :title AND DATE(created_at) = :hoje LIMIT 1");
$stmt->execute(['uid' => $user_id, 'title' => $title, 'hoje' => $hoje]);
if ($stmt->fetch()) {
    return;
}
$stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, data, created_at, read_at) VALUES (:uid, :title, :message, :type, :data, NOW(), NULL)");
$stmt->execute([
    'uid' => $user_id,
    'title' => $title,
    'message' => $message,
    'type' => $type,
    'data' => json_encode(['url' => sf_app_url('pages/motorists.php'), 'total' => count($lista)])
]);
} catch (Exception $e) {
    error_log("sync_cnh_notificacoes: " . $e->getMessage());
}
