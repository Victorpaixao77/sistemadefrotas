<?php
/**
 * API — Planejamento de Carga: capacidades reais dos veículos para o cálculo de distribuição.
 * Só leitura, por empresa.
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

try {
    $st = $conn->prepare("SELECT id, placa, modelo,
            COALESCE(capacidade_carga, 0) AS capacidade_carga,
            COALESCE(numero_eixos, 0) AS numero_eixos
        FROM veiculos WHERE empresa_id = :e ORDER BY placa");
    $st->execute([':e' => $empresa_id]);
    $veiculos = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $veiculos[] = [
            'id' => (int) $r['id'], 'placa' => $r['placa'], 'modelo' => $r['modelo'],
            'capacidade_carga' => (float) $r['capacidade_carga'],
            'numero_eixos' => (int) $r['numero_eixos'],
        ];
    }
    echo json_encode(['success' => true, 'veiculos' => $veiculos], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('planejamento_carga.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar os veículos.']);
}
