<?php
/**
 * Ações do Copiloto — propor, listar, confirmar, cancelar.
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sf_ia_platform.php';

configure_session();
session_start();
require_authentication();

header('Content-Type: application/json; charset=utf-8');

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
$usuario_id = (int) ($_SESSION['usuario_id'] ?? 0);
$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? 'listar'));

try {
    $conn = getConnection();
    sf_ia_ensure_schema($conn);

    if ($action === 'listar') {
        $status = trim((string) ($_GET['status'] ?? 'pendente'));
        echo json_encode([
            'success' => true,
            'data' => sf_ia_acoes_list($conn, $empresa_id, $status !== 'todos' ? $status : null),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (in_array($action, ['confirmar', 'cancelar', 'propor'], true) && !csrf_token_validate($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Sessão expirada. Atualize a página.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'confirmar') {
        $id = (int) ($_POST['id'] ?? 0);
        $res = sf_ia_confirmar_acao($conn, $empresa_id, $usuario_id, $id);
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'cancelar') {
        $id = (int) ($_POST['id'] ?? 0);
        $ok = sf_ia_cancelar_acao($conn, $empresa_id, $id);
        echo json_encode(['success' => $ok, 'error' => $ok ? null : 'Não foi possível cancelar.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'propor') {
        $tipo = preg_replace('/[^a-z_]/', '', (string) ($_POST['tipo'] ?? ''));
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $resumo = trim((string) ($_POST['resumo'] ?? ''));
        $paramsRaw = $_POST['params'] ?? '{}';
        $params = is_array($paramsRaw) ? $paramsRaw : (json_decode((string) $paramsRaw, true) ?: []);
        if ($tipo === '' || $titulo === '') {
            echo json_encode(['success' => false, 'error' => 'Dados incompletos.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $newId = sf_ia_propor_acao($conn, $empresa_id, $usuario_id, $tipo, $titulo, $resumo, $params);
        echo json_encode(['success' => $newId !== null, 'id' => $newId], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Ação inválida.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('ia_acoes.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao processar ação.'], JSON_UNESCAPED_UNICODE);
}
