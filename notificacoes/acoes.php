<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db_connect.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/notificacoes_helpers.php';
require_once dirname(__DIR__) . '/includes/bi_cache_invalidate.php';

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

api_require_csrf_json();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$empresa_id = (int) $_SESSION['empresa_id'];
$acao = (string) ($_POST['acao'] ?? '');
$ref = (int) ($_POST['ref'] ?? 0);
$notif_id = (int) ($_POST['notif_id'] ?? 0);

$map = [
    'aprovar_rota' => ['aprovado', 'rotas'],
    'rejeitar_rota' => ['rejeitado', 'rotas'],
    'aprovar_abastecimento' => ['aprovado', 'abastecimentos'],
    'rejeitar_abastecimento' => ['rejeitado', 'abastecimentos'],
    'aprovar_despesa' => ['aprovado', 'despesas_viagem'],
    'rejeitar_despesa' => ['rejeitado', 'despesas_viagem'],
];

if ($ref <= 0 || !isset($map[$acao])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ação inválida']);
    exit;
}

[$status, $entidade] = $map[$acao];

try {
    $conn = getConnection();
    if ($entidade === 'rotas') {
        $st = $conn->prepare('UPDATE rotas SET status = :s WHERE id = :id AND empresa_id = :e');
    } elseif ($entidade === 'despesas_viagem') {
        $st = $conn->prepare('UPDATE despesas_viagem SET status = :s WHERE id = :id AND empresa_id = :e');
    } else {
        $st = $conn->prepare('UPDATE abastecimentos SET status = :s WHERE id = :id AND empresa_id = :e');
    }
    $st->execute([':s' => $status, ':id' => $ref, ':e' => $empresa_id]);
    if ($st->rowCount() < 1) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Registro não encontrado']);
        exit;
    }

    if ($notif_id > 0) {
        $conn->prepare('UPDATE notificacoes SET lida = 1 WHERE id = :id AND empresa_id = :e')
            ->execute([':id' => $notif_id, ':e' => $empresa_id]);
    }

    bi_cache_invalidate_empresa($conn, $empresa_id);
    require_once dirname(__DIR__) . '/includes/notificacoes_sync_operacional.php';
    sf_notif_sync_resolver_pendencias_resolvidas($conn, $empresa_id);

    $uid = sf_notif_usuario_id();
    echo json_encode([
        'success' => true,
        'operacional' => sf_notif_contar_nao_lidas_usuario($conn, $empresa_id, $uid, 'operacional'),
        'ia' => sf_notif_contar_nao_lidas_usuario($conn, $empresa_id, $uid, 'ia'),
    ]);
} catch (Throwable $e) {
    error_log('notificacoes/acoes.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao executar ação']);
}
