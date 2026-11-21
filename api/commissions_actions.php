<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
require_once '../includes/api_json.php';
require_once '../includes/comissoes_helpers.php';

configure_session();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    api_json_error('Não autorizado.', 401);
}

require_once '../includes/permissions.php';
if (!can_view_financial_data()) {
    api_json_error('Sem permissão para alterar comissões.', 403);
}

header('Content-Type: application/json');
api_require_csrf_json();

try {
    $conn = getConnection();
    $empresa_id = (int) $_SESSION['empresa_id'];
    ensureCommissionPaymentsTable($conn);

    $action = $_POST['action'] ?? null;
    if (!$action) {
        throw new InvalidArgumentException('Ação não informada.');
    }

    if ($action === 'mark_paid_bulk') {
        $rawIds = $_POST['rota_ids'] ?? '[]';
        $ids = is_array($rawIds) ? $rawIds : (json_decode((string) $rawIds, true) ?: []);
        $ids = array_values(array_filter(array_map('intval', $ids), static fn($v) => $v > 0));
        if (empty($ids)) {
            throw new InvalidArgumentException('Nenhuma comissão selecionada.');
        }
        $dataPag = $_POST['data_pagamento'] ?? null;
        $result = markCommissionsBulk($conn, $empresa_id, $ids, 'pago', $dataPag);
        echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $rota_id = isset($_POST['rota_id']) ? (int) $_POST['rota_id'] : 0;
    if ($rota_id < 1) {
        throw new InvalidArgumentException('Parâmetros inválidos.');
    }

    $stmt = $conn->prepare(
        'SELECT id, comissao FROM rotas WHERE id = :rota_id AND empresa_id = :empresa_id AND comissao > 0 AND status = \'aprovado\''
    );
    $stmt->execute([':rota_id' => $rota_id, ':empresa_id' => $empresa_id]);
    $rota = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$rota) {
        throw new InvalidArgumentException('Comissão não encontrada.');
    }

    if ($action === 'mark_paid') {
        markCommissionStatus(
            $conn,
            $rota_id,
            $empresa_id,
            'pago',
            (float) $rota['comissao'],
            $_POST['data_pagamento'] ?? null
        );
    } elseif ($action === 'mark_pending') {
        markCommissionStatus($conn, $rota_id, $empresa_id, 'pendente', (float) $rota['comissao'], null);
    } else {
        throw new InvalidArgumentException('Ação inválida.');
    }

    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    error_log('commissions_actions: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Erro interno.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('commissions_actions: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Não foi possível processar a solicitação.'], JSON_UNESCAPED_UNICODE);
}
