<?php
/**
 * API — Entidades para geração de QR Code (veículos, pneus, implementos, motoristas).
 * Devolve a lista + o caminho da tela que o QR deve abrir. Só leitura, por empresa.
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';

configure_session();
session_start();
require_authentication();
if (function_exists('session_write_close')) { session_write_close(); }

header('Content-Type: application/json; charset=utf-8');

$conn = getConnection();
$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if ($empresa_id < 1) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Não autorizado']); exit; }

$tipo = $_GET['tipo'] ?? 'veiculos';

try {
    $itens = [];
    if ($tipo === 'veiculos') {
        $st = $conn->prepare("SELECT id, placa, modelo FROM veiculos WHERE empresa_id = :e ORDER BY placa");
        $st->execute([':e' => $empresa_id]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $itens[] = ['id' => (int) $r['id'], 'label' => $r['placa'] ?: ('Veículo ' . $r['id']),
                'sub' => (string) ($r['modelo'] ?? ''), 'path' => 'pages/veiculo_timeline.php?veiculo_id=' . (int) $r['id']];
        }
    } elseif ($tipo === 'pneus') {
        $st = $conn->prepare("SELECT id, numero_serie, marca, modelo FROM pneus WHERE empresa_id = :e ORDER BY id DESC LIMIT 500");
        $st->execute([':e' => $empresa_id]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $itens[] = ['id' => (int) $r['id'], 'label' => $r['numero_serie'] ?: ('Pneu ' . $r['id']),
                'sub' => trim(($r['marca'] ?? '') . ' ' . ($r['modelo'] ?? '')), 'path' => 'pages/pneu_ficha.php?id=' . (int) $r['id']];
        }
    } elseif ($tipo === 'implementos') {
        $st = $conn->prepare("SELECT id, placa, tipo FROM implementos WHERE empresa_id = :e ORDER BY placa");
        $st->execute([':e' => $empresa_id]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $itens[] = ['id' => (int) $r['id'], 'label' => $r['placa'] ?: ('Implemento ' . $r['id']),
                'sub' => (string) ($r['tipo'] ?? ''), 'path' => 'pages/implementos.php'];
        }
    } elseif ($tipo === 'motoristas') {
        $st = $conn->prepare("SELECT id, nome FROM motoristas WHERE empresa_id = :e ORDER BY nome");
        $st->execute([':e' => $empresa_id]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $itens[] = ['id' => (int) $r['id'], 'label' => $r['nome'] ?: ('Motorista ' . $r['id']),
                'sub' => '', 'path' => 'pages/gestao_motoristas.php'];
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tipo inválido']);
        exit;
    }

    echo json_encode(['success' => true, 'tipo' => $tipo, 'itens' => $itens], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('qr_entidades.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar as entidades.']);
}
