<?php
/**
 * API Cidades - Lista cidades por UF (App Android)
 * GET: ?uf=SP retorna cidades do estado (para seleção em rotas)
 */

require_once __DIR__ . '/../config.php';

$uf = isset($_GET['uf']) ? strtoupper(trim($_GET['uf'])) : '';
if (strlen($uf) !== 2) {
    api_success(['cidades' => []]);
}

try {
    $conn = getConnection();
    $stmt = $conn->prepare('SELECT id, nome, uf FROM cidades WHERE uf = :uf ORDER BY nome');
    $stmt->execute([':uf' => $uf]);
    $cidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    api_success(['cidades' => $cidades]);
} catch (PDOException $e) {
    error_log('API cidades: ' . $e->getMessage());
    api_success(['cidades' => []]);
}
