<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

try {
    $conn = getConnection();
    $stmt = $conn->query('SELECT nome FROM tipos_combustivel ORDER BY nome');
    $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'tipos' => $tipos]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 