<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db_connect.php';
require_once dirname(__DIR__) . '/includes/notificacoes_helpers.php';
require_once dirname(__DIR__) . '/includes/notificacoes_sync_operacional.php';

ob_start();
configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || empty($_SESSION['empresa_id'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Usuário não autenticado']);
    exit;
}

$empresa_id = (int) $_SESSION['empresa_id'];
$usuario_id = sf_notif_usuario_id();
$escopo = sf_notif_escopo_valido($_GET['escopo'] ?? 'todos');
$todas = isset($_GET['todas']) && $_GET['todas'] === '1';
$tipoFiltro = trim((string) ($_GET['tipo'] ?? ''));
$severidadeFiltro = trim((string) ($_GET['severidade'] ?? ''));
$dias = SF_NOTIF_DIAS_JANELA;

try {
    $conn = getConnection();
    sf_notif_ensure_schema($conn);
    $sistema_ativo = sf_notificacoes_sistema_ativo($conn, $empresa_id);

    if (!$sistema_ativo) {
        ob_clean();
        echo json_encode(['success' => true, 'sistema_ativo' => false, 'notificacoes' => [], 'total_nao_lidas' => 0, 'escopo' => $escopo]);
        exit;
    }

    sf_notif_sync_cnh($conn, $empresa_id);

    $sql = 'SELECT * FROM notificacoes WHERE empresa_id = :empresa_id';
    $params = [':empresa_id' => $empresa_id];

    [$inSql, $tipos] = sf_notif_escopo_tipos($escopo);
    if ($inSql !== '') {
        $sql .= ' AND tipo IN (' . $inSql . ')';
        foreach ($tipos as $i => $t) {
            $params[':tipo' . $i] = $t;
        }
    }
    if ($tipoFiltro !== '') {
        $sql .= ' AND tipo = :tipo_filtro';
        $params[':tipo_filtro'] = $tipoFiltro;
    }
    if ($severidadeFiltro !== '' && in_array($severidadeFiltro, ['info', 'aviso', 'critico'], true)) {
        $sql .= ' AND severidade = :sev';
        $params[':sev'] = $severidadeFiltro;
    }

    if ($todas) {
        $sql .= ' AND data_criacao >= DATE_SUB(NOW(), INTERVAL 365 DAY) ORDER BY FIELD(severidade, \'critico\', \'aviso\', \'info\'), data_criacao DESC LIMIT 500';
    } else {
        $diasSql = (int) SF_NOTIF_DIAS_JANELA;
        $sql .= ' AND lida = 0 AND data_criacao >= DATE_SUB(NOW(), INTERVAL ' . $diasSql . ' DAY) ORDER BY FIELD(severidade, \'critico\', \'aviso\', \'info\'), data_criacao DESC LIMIT 200';
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $notificacoes = sf_notif_filtrar_para_usuario($notificacoes, $conn, $usuario_id, $empresa_id);
    $total_nao_lidas = sf_notif_contar_nao_lidas_usuario($conn, $empresa_id, $usuario_id, $escopo, $dias);

    if ($todas) {
        $notificacoes_filtradas = $notificacoes;
    } else {
        $notificacoes_filtradas = sf_notif_deduplicar_lista($notificacoes);
    }

    $notificacoes_filtradas = sf_notif_anexar_urls($notificacoes_filtradas);

    ob_clean();
    echo json_encode([
        'success' => true,
        'sistema_ativo' => true,
        'escopo' => $escopo,
        'notificacoes' => $notificacoes_filtradas,
        'total_nao_lidas' => $total_nao_lidas,
        'total_real_nao_lidas' => $total_nao_lidas,
        'total_original' => count($notificacoes),
        'total_filtrado' => count($notificacoes_filtradas),
    ]);
} catch (Throwable $e) {
    ob_clean();
    error_log('notificacoes/notificacoes.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao buscar notificações']);
}
