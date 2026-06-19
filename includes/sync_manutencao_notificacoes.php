<?php
require_once __DIR__ . '/sf_paths.php';
require_once __DIR__ . '/notificacoes_helpers.php';
/**
 * Sincroniza alertas de Planos de Manutenção para a tabela notificacoes (sino do header).
 * Incluir após maintenance_alertas_score.php em manutencoes.php.
 */
if (!isset($conn) || (!isset($_SESSION['user_id']) && !isset($_SESSION['id']))) {
    return;
}
$user_id = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
if ($user_id <= 0 || empty($_SESSION['empresa_id'])) {
    return;
}

$empresa_id = (int) $_SESSION['empresa_id'];
$alertas_proximas = $alertas_proximas ?? [];
$alertas_inteligentes = $alertas_inteligentes ?? [];

$vencidos = array_filter($alertas_proximas, static function ($a) {
    return !empty($a['vencido']);
});
$proximos = array_filter($alertas_proximas, static function ($a) {
    return empty($a['vencido']);
});
$total_inteligentes = count($alertas_inteligentes);

$mensagens = [];
if (count($vencidos) > 0) {
    $mensagens[] = count($vencidos) . ' manutenção(ões) por plano vencida(s): ' .
        implode(', ', array_map(static function ($a) {
            return $a['placa'] . ' - ' . $a['componente'];
        }, array_slice($vencidos, 0, 5)));
}
if (count($proximos) > 0 && count($mensagens) < 2) {
    $mensagens[] = count($proximos) . ' próxima(s): ' .
        implode(', ', array_map(static function ($a) {
            return $a['placa'] . ' - ' . $a['componente'];
        }, array_slice($proximos, 0, 3)));
}
if ($total_inteligentes > 0) {
    $mensagens[] = $total_inteligentes . ' alerta(s) inteligente(s) (ex.: 3+ corretivas mesmo componente).';
}

if ($mensagens === []) {
    return;
}

$titulo = 'Manutenção – planos e alertas';
$mensagem = implode("\n", $mensagens);
$ia = count($vencidos) > 0
    ? 'Priorize as manutenções vencidas para reduzir risco de parada não programada.'
    : 'Revise os planos de manutenção próximos do vencimento na página de manutenções.';

try {
    if (!sf_notificacoes_sistema_ativo($conn, $empresa_id)) {
        return;
    }
    sf_notif_inserir_se_novo($conn, $empresa_id, 'manutencao', $titulo, $mensagem, $ia, null, 24);
} catch (Throwable $e) {
    error_log('sync_manutencao_notificacoes: ' . $e->getMessage());
}
