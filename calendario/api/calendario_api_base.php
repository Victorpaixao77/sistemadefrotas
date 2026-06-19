<?php
/**
 * Bootstrap compartilhado das APIs do calendário.
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/db_connect.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';

function calendario_api_bootstrap(): void
{
    configure_session();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function calendario_api_require_auth(): int
{
    calendario_api_bootstrap();
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || empty($_SESSION['empresa_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Não autenticado']);
        exit;
    }
    return (int) $_SESSION['empresa_id'];
}

function calendario_api_require_get(): int
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
        exit;
    }
    return calendario_api_require_auth();
}

function calendario_api_require_post_json(): int
{
    calendario_api_bootstrap();
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || empty($_SESSION['empresa_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Não autenticado']);
        exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
        exit;
    }
    api_require_csrf_json();
    return (int) $_SESSION['empresa_id'];
}

function calendario_api_fail(string $logContext, Throwable $e): void
{
    error_log($logContext . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor']);
}

function calendario_categoria_id(PDO $conn, int $empresa_id, string $nome): int
{
    $st = $conn->prepare('SELECT id FROM categorias_calendario WHERE nome = :nome AND empresa_id = :e LIMIT 1');
    $st->execute([':nome' => $nome, ':e' => $empresa_id]);
    $id = $st->fetchColumn();
    if ($id) {
        return (int) $id;
    }
    $st = $conn->prepare('SELECT id FROM categorias_calendario WHERE nome = :nome AND empresa_id = :e LIMIT 1');
    $st->execute([':nome' => 'Personalizado', ':e' => $empresa_id]);
    $fallback = $st->fetchColumn();
    return $fallback ? (int) $fallback : 0;
}

function calendario_ensure_categorias(PDO $conn, int $empresa_id): void
{
    $defaults = [
        ['CNH', 'Vencimento de CNH', '#ef4444'],
        ['Multas', 'Vencimento de multas', '#f59e0b'],
        ['Contas', 'Contas a pagar', '#3b82f6'],
        ['Financiamento', 'Parcelas de financiamento', '#8b5cf6'],
        ['Manutenção', 'Manutenção de veículos', '#10b981'],
        ['Personalizado', 'Eventos do usuário', '#3788d8'],
    ];
    $ins = $conn->prepare('INSERT IGNORE INTO categorias_calendario (nome, descricao, cor_padrao, empresa_id) VALUES (:n, :d, :c, :e)');
    foreach ($defaults as $d) {
        $ins->execute([':n' => $d[0], ':d' => $d[1], ':c' => $d[2], ':e' => $empresa_id]);
    }
}

function calendario_evento_manual_only(PDO $conn, int $id, int $empresa_id): bool
{
    $st = $conn->prepare('SELECT origem_tipo FROM calendario_eventos WHERE id = :id AND empresa_id = :e LIMIT 1');
    $st->execute([':id' => $id, ':e' => $empresa_id]);
    $tipo = $st->fetchColumn();
    return $tipo === false ? false : ($tipo === 'personalizado' || $tipo === null || $tipo === '');
}
