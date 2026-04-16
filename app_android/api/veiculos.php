<?php
/**
 * API Veículos - Lista veículos da empresa do motorista (App Android)
 * GET: lista veículos (id, placa, modelo)
 */

require_once __DIR__ . '/../config.php';
require_motorista_token();

$empresa_id = get_empresa_id();

try {
    $conn = getConnection();
    $stmt = $conn->prepare('
        SELECT id, placa, modelo
        FROM veiculos
        WHERE empresa_id = :e
        ORDER BY placa
    ');
    $stmt->execute([':e' => $empresa_id]);
    $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    api_success(['veiculos' => $veiculos]);
} catch (PDOException $e) {
    error_log('API veiculos: ' . $e->getMessage());
    api_error('Erro ao listar veículos.', 500);
}
