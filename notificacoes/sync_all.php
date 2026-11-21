<?php
/**
 * Sincronização completa de notificações (operacional + IA).
 * CLI: php notificacoes/sync_all.php
 * HTTP: autenticado (sessão) — usado pelo polling do header.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db_connect.php';
require_once dirname(__DIR__) . '/includes/notificacoes_helpers.php';
require_once dirname(__DIR__) . '/includes/notificacoes_sync_operacional.php';
require_once dirname(__DIR__) . '/includes/notificacoes_sync_ia.php';

$isCli = (PHP_SAPI === 'cli');
$skipHeavy = false;

if (!$isCli) {
    configure_session();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    header('Content-Type: application/json');
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || empty($_SESSION['empresa_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Não autenticado']);
        exit;
    }
    $empresa_id_sync = (int) $_SESSION['empresa_id'];
    $usuario_id_sync = function_exists('sf_notif_usuario_id') ? sf_notif_usuario_id() : (int) ($_SESSION['usuario_id'] ?? 0);
    // Throttle: a varredura pesada (frota, CNH, manutenção, IA) leva ~segundos.
    // O header já re-sincroniza a cada 5 min; em cargas dentro dessa janela devolvemos
    // apenas as contagens (rápido), evitando 4s de trabalho repetido a cada página.
    $now = time();
    $skipHeavy = isset($_SESSION['last_notif_sync']) && ($now - (int) $_SESSION['last_notif_sync']) < 300;
    if (!$skipHeavy) {
        $_SESSION['last_notif_sync'] = $now;
    }
    if (function_exists('session_write_close')) {
        session_write_close();
    }
    $empresa_ids = [$empresa_id_sync];
} else {
    $conn = getConnection();
    $empresa_ids = $conn->query('SELECT id FROM empresas ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
}

$totalOp = 0;
$totalIa = 0;

try {
    if (!$skipHeavy) {
        foreach ($empresa_ids as $eid) {
            $empresa_id = (int) $eid;
            if ($empresa_id <= 0) {
                continue;
            }
            $conn = getConnection();
            $totalOp += sf_notif_sync_operacional_completo($conn, $empresa_id);
            $totalIa += sf_notif_sync_ia_completo($conn, $empresa_id);
        }
    }
} catch (Throwable $e) {
    error_log('notificacoes/sync_all.php: ' . $e->getMessage());
    if ($isCli) {
        fwrite(STDERR, "Erro: sync falhou\n");
        exit(1);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao sincronizar notificações']);
    exit;
}

$result = [
    'success' => true,
    'operacional_criadas' => $totalOp,
    'ia_criadas' => $totalIa,
];

if ($isCli) {
    echo "Sync notificações: operacional={$totalOp}, ia={$totalIa}\n";
} else {
    $conn = getConnection();
    $result['operacional'] = sf_notif_contar_nao_lidas_usuario($conn, $empresa_id_sync, $usuario_id_sync, 'operacional');
    $result['ia'] = sf_notif_contar_nao_lidas_usuario($conn, $empresa_id_sync, $usuario_id_sync, 'ia');
    echo json_encode($result);
}
