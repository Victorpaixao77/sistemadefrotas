<?php
/**
 * Documentos do Copiloto — upload, listagem e busca RAG.
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sf_ia_platform.php';

configure_session();
session_start();
require_authentication();

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
$usuario_id = (int) ($_SESSION['usuario_id'] ?? 0);
$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? 'listar'));

try {
    $conn = getConnection();
    sf_ia_ensure_schema($conn);

    if ($action === 'listar') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'data' => sf_ia_documentos_list($conn, $empresa_id)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'buscar') {
        header('Content-Type: application/json; charset=utf-8');
        $q = trim((string) ($_GET['q'] ?? ''));
        echo json_encode(['success' => true, 'data' => sf_ia_rag_search($conn, $empresa_id, $q)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'upload') {
        header('Content-Type: application/json; charset=utf-8');
        if (!csrf_token_validate($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Sessão expirada.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (empty($_FILES['arquivo'])) {
            echo json_encode(['success' => false, 'error' => 'Envie um arquivo.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $texto = trim((string) ($_POST['texto'] ?? ''));
        $res = sf_ia_documento_upload($conn, $empresa_id, $usuario_id, $_FILES['arquivo'], $texto !== '' ? $texto : null);
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Ação inválida.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('ia_documentos.php: ' . $e->getMessage());
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao processar documento.'], JSON_UNESCAPED_UNICODE);
}
