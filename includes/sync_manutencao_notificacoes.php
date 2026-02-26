<?php
/**
 * Sincroniza alertas de Planos de Manutenção para a tabela de notificações (sino do header).
 * Incluir após maintenance_alertas_score.php em manutencoes.php.
 * Usa $alertas_proximas e $alertas_inteligentes já preenchidos.
 */
if (!isset($conn) || !isset($_SESSION['user_id']) && !isset($_SESSION['id'])) {
    return;
}
$user_id = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
if ($user_id <= 0) {
    return;
}
$alertas_proximas = $alertas_proximas ?? [];
$alertas_inteligentes = $alertas_inteligentes ?? [];

// Verificar se tabela notifications existe
try {
    $conn->query("SELECT 1 FROM notifications LIMIT 1");
} catch (Exception $e) {
    return;
}

$hoje = date('Y-m-d');
$agora = date('Y-m-d H:i:s');

// Notificação consolidada: "X manutenções por plano vencidas/próximas"
$vencidos = array_filter($alertas_proximas, function ($a) { return !empty($a['vencido']); });
$proximos = array_filter($alertas_proximas, function ($a) { return empty($a['vencido']); });
$total_inteligentes = count($alertas_inteligentes);

$mensagens = [];
if (count($vencidos) > 0) {
    $mensagens[] = count($vencidos) . ' manutenção(ões) por plano vencida(s): ' .
        implode(', ', array_map(function ($a) {
            return $a['placa'] . ' - ' . $a['componente'];
        }, array_slice($vencidos, 0, 5)));
}
if (count($proximos) > 0 && count($mensagens) < 2) {
    $mensagens[] = count($proximos) . ' próxima(s): ' .
        implode(', ', array_map(function ($a) {
            return $a['placa'] . ' - ' . $a['componente'];
        }, array_slice($proximos, 0, 3)));
}
if ($total_inteligentes > 0) {
    $mensagens[] = $total_inteligentes . ' alerta(s) inteligente(s) (ex.: 3+ corretivas mesmo componente).';
}

if (empty($mensagens)) {
    return;
}

$title = 'Manutenção – Planos e alertas';
$message = implode("\n", $mensagens);
$type = count($vencidos) > 0 ? 'warning' : 'info';

// Evitar duplicar: só criar se não existir notificação igual hoje
try {
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
        'data' => json_encode(['url' => '/sistema-frotas/pages/manutencoes.php', 'vencidos' => count($vencidos), 'proximos' => count($proximos)])
    ]);
} catch (Exception $e) {
    error_log("sync_manutencao_notificacoes: " . $e->getMessage());
}
