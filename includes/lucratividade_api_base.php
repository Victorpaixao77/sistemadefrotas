<?php
/**
 * Bootstrap comum para APIs da página Lucratividade.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/lucratividade_funcoes.php';

configure_session();
session_start();

header('Content-Type: application/json; charset=utf-8');

require_authentication();

if (!can_access_lucratividade()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão para acessar lucratividade.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if ($empresa_id < 1) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$mes = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('m');
$ano = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');
if ($mes < 1 || $mes > 12) {
    $mes = (int) date('m');
}
if ($ano < 2000 || $ano > 2100) {
    $ano = (int) date('Y');
}

if (function_exists('session_write_close')) {
    session_write_close();
}
