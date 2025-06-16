<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Check if user is logged in
require_authentication();

header('Content-Type: application/json');

try {
    // Verifica se é uma requisição POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    // Obtém os dados do POST
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Dados inválidos');
    }

    // Valida os dados necessários
    $required_fields = ['pneu_id'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Campo obrigatório não fornecido: $field");
        }
    }

    $pdo = getConnection();
    
    // Inicia a transação
    $pdo->beginTransaction();

    try {
        // Verifica se já existe um registro básico para este pneu
        $stmt = $pdo->prepare('SELECT id FROM eixo_pneus WHERE pneu_id = ?');
        $stmt->execute([$data['pneu_id']]);
        $eixo_pneu = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$eixo_pneu) {
            // Cria um registro básico em eixo_pneus
            $stmt = $pdo->prepare('INSERT INTO eixo_pneus (pneu_id) VALUES (?)');
            $stmt->execute([$data['pneu_id']]);
        }

        // Confirma a transação
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Registro básico criado com sucesso']);
    } catch (Exception $e) {
        // Em caso de erro, desfaz a transação
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 