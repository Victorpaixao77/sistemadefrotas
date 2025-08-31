<?php
/**
 * Processador de Notificações de Pneus
 * Recebe alertas do sistema de IA e os insere no banco de dados
 */

require_once __DIR__ . '/../includes/db_connect.php';
session_start();

// Permitir CORS para requisições AJAX
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Obter dados da requisição
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Dados inválidos recebidos');
    }
    
    // Validar campos obrigatórios
    $camposObrigatorios = ['tipo', 'titulo', 'mensagem'];
    foreach ($camposObrigatorios as $campo) {
        if (!isset($input[$campo]) || empty($input[$campo])) {
            throw new Exception("Campo obrigatório não informado: $campo");
        }
    }
    
    // Obter empresa_id
    $empresa_id = $_SESSION['empresa_id'] ?? 1;
    
    // Conectar ao banco
    $conn = getConnection();
    
    // Verificar se a notificação já existe (evitar duplicatas)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notificacoes 
                           WHERE empresa_id = ? AND tipo = ? AND titulo = ? 
                           AND data_criacao > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute([$empresa_id, $input['tipo'], $input['titulo']]);
    
    if ($stmt->fetchColumn() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Notificação já existe (evitando duplicata)'
        ]);
        exit;
    }
    
    // Inserir notificação
    $stmt = $conn->prepare("INSERT INTO notificacoes 
                           (empresa_id, tipo, titulo, mensagem, ia_mensagem, data_criacao, lida) 
                           VALUES (?, ?, ?, ?, ?, NOW(), 0)");
    
    $ia_mensagem = $input['ia_mensagem'] ?? null;
    $stmt->execute([
        $empresa_id,
        $input['tipo'],
        $input['titulo'],
        $input['mensagem'],
        $ia_mensagem
    ]);
    
    $notificacao_id = $conn->lastInsertId();
    
    // Log da notificação
    error_log("Notificação de pneu inserida: ID $notificacao_id - {$input['titulo']}");
    
    // Se for uma notificação crítica, adicionar informações extras
    if (isset($input['prioridade']) && $input['prioridade'] === 'alta') {
        // Registrar alerta crítico em log separado
        error_log("ALERTA CRÍTICO DE PNEU: {$input['titulo']} - {$input['mensagem']}");
        
        // Aqui você pode adicionar lógica adicional para alertas críticos
        // Por exemplo: enviar email, SMS, etc.
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Notificação processada com sucesso',
        'notificacao_id' => $notificacao_id
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao processar notificação de pneu: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 