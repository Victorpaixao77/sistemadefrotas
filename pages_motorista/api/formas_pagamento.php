<?php
require_once '../config.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['motorista_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

// Verificar se é uma requisição GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

// Obter parâmetros
$action = $_GET['action'] ?? '';

// Validar ação
if ($action !== 'list') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ação inválida']);
    exit;
}

try {
    $conn = getConnection();
    
    // Buscar formas de pagamento
    $sql = "SELECT * FROM formas_pagamento ORDER BY nome";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $formas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log para debug
    error_log('Formas de pagamento encontradas: ' . print_r($formas, true));
    
    // Retornar formas
    echo json_encode([
        'success' => true,
        'formas' => $formas
    ]);
    
} catch (Exception $e) {
    error_log('Erro ao buscar formas de pagamento: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar formas de pagamento: ' . $e->getMessage()
    ]);
} 