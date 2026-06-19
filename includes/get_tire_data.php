<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/pneus_helpers.php';

header('Content-Type: application/json; charset=utf-8');

configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_authentication();
pneus_require_page_access();

try {
    $pdo = getConnection();
    $empresa_id = pneus_empresa_id();
    $type = isset($_GET['type']) ? (string) $_GET['type'] : '';

    switch ($type) {
        case 'status':
            $stmt = $pdo->prepare('SELECT id, nome FROM status_pneus ORDER BY nome');
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'posicoes':
            $stmt = $pdo->prepare('SELECT id, nome FROM posicoes_pneus ORDER BY nome');
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'veiculos':
            $stmt = $pdo->prepare('SELECT id, placa AS nome FROM veiculos WHERE empresa_id = ? ORDER BY placa');
            $stmt->execute([$empresa_id]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'filiais':
            require_once __DIR__ . '/pneus_mercado_helpers.php';
            pneus_filial_garantir_matriz($pdo, $empresa_id);
            $data = pneus_filiais_listar($pdo, $empresa_id);
            break;

        default:
            throw new InvalidArgumentException('Tipo de dados não especificado');
    }

    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('get_tire_data.php: ' . $e->getMessage());
    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    echo json_encode(['success' => false, 'error' => 'Erro ao carregar dados auxiliares'], JSON_UNESCAPED_UNICODE);
}
