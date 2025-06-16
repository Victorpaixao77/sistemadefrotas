<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Não precisa de sessão para cidades
$estado = $_GET['estado'] ?? '';

if (empty($estado)) {
    echo json_encode([]);
    exit;
}

try {
    $conn = getConnection();
    $stmt = $conn->prepare('SELECT id, nome FROM cidades WHERE uf = :estado ORDER BY nome');
    $stmt->bindParam(':estado', $estado);
    $stmt->execute();
    $cidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($cidades);
} catch (PDOException $e) {
    error_log('Erro ao buscar cidades: ' . $e->getMessage());
    echo json_encode([]);
} 